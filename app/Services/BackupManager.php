<?php

namespace App\Services;

use App\Models\AuditLogModel;
use App\Models\CertificateModel;
use App\Models\ProxyHostModel;
use App\Models\SettingModel;
use CodeIgniter\Database\BaseConnection;
use Config\Nginx as NginxConfig;
use InvalidArgumentException;
use SQLite3;
use Throwable;
use ZipArchive;

/**
 * Gestor de Copias de Seguridad Atómicas y Recuperación ante Desastres (Fase 8).
 *
 * - Realiza instantáneas consistentes en caliente de SQLite mediante `VACUUM INTO`.
 * - Empaqueta configuraciones de Nginx y metadatos de certificados SSL.
 * - Valida integridad estructural y criptográfica (SHA-256 + PRAGMA integrity_check) antes de restaurar.
 * - Aplica política de retención configurable eliminando copias antiguas.
 */
class BackupManager
{
    protected NginxConfig $config;
    protected string $backupDir;
    protected BaseConnection $db;
    protected NginxConfigGenerator $configGenerator;
    protected NginxManager $nginxManager;

    public const DEFAULT_RETENTION = 10;

    public function __construct(
        ?NginxConfig $config = null,
        ?string $backupDir = null,
        ?BaseConnection $db = null,
        ?NginxConfigGenerator $configGenerator = null,
        ?NginxManager $nginxManager = null
    ) {
        $this->config          = $config ?? config(NginxConfig::class);
        $this->backupDir       = rtrim($backupDir ?? (WRITEPATH . 'backups'), '/');
        $this->db              = $db ?? db_connect();
        $this->configGenerator = $configGenerator ?? new NginxConfigGenerator($this->config);
        $this->nginxManager    = $nginxManager ?? new NginxManager($this->config, $this->configGenerator);

        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0750, true);
        }
    }

    /**
     * Devuelve el directorio base configurado para los backups.
     */
    public function getBackupDir(): string
    {
        return $this->backupDir;
    }

    /**
     * Obtiene el número máximo de copias a retener desde la tabla settings.
     */
    public function getRetentionCount(): int
    {
        $settingModel = new SettingModel();
        $val = $settingModel->getVal('backup_retention_count', (string) self::DEFAULT_RETENTION);
        $count = (int) $val;
        return ($count >= 1 && $count <= 100) ? $count : self::DEFAULT_RETENTION;
    }

    /**
     * Actualiza la política de retención de copias de seguridad y aplica la limpieza.
     */
    public function setRetentionCount(int $count): bool
    {
        if ($count < 1 || $count > 100) {
            throw new InvalidArgumentException('La retención debe estar entre 1 y 100 copias de seguridad.');
        }

        $settingModel = new SettingModel();
        $saved = $settingModel->setVal('backup_retention_count', (string) $count);
        $this->pruneOldBackups($count);

        return $saved;
    }

    /**
     * Crea una copia de seguridad atómica completa (SQLite VACUUM INTO + Nginx + Certificados).
     *
     * @return array{success: bool, filename: ?string, path: ?string, size: int, error: ?string}
     */
    public function createBackup(): array
    {
        $now        = microtime(true);
        $usec       = sprintf('%04d', (int) (($now - floor($now)) * 10000));
        $timestamp  = date('Y-m-d_His', (int) $now) . '_' . $usec;
        $randomHex  = bin2hex(random_bytes(2));
        $filename   = "backup_{$timestamp}_{$randomHex}.zip";
        $targetPath = $this->backupDir . DIRECTORY_SEPARATOR . $filename;
        $tempSqlite = $this->backupDir . DIRECTORY_SEPARATOR . ".tmp_sqlite_{$randomHex}.db";

        try {
            if (file_exists($tempSqlite)) {
                @unlink($tempSqlite);
            }

            // 1. Copia atómica en caliente de SQLite mediante VACUUM INTO
            $escapedPath = str_replace("'", "''", $tempSqlite);
            $this->db->query("VACUUM INTO '{$escapedPath}'");

            if (!is_file($tempSqlite) || filesize($tempSqlite) === 0) {
                throw new \RuntimeException('VACUUM INTO no generó un archivo SQLite válido.');
            }

            // Verificar integridad de la instantánea recién creada
            if (!$this->checkSqliteFileIntegrity($tempSqlite)) {
                throw new \RuntimeException('La instantánea SQLite generada no superó PRAGMA integrity_check.');
            }

            $dbSha256 = hash_file('sha256', $tempSqlite);

            // 2. Recopilar Proxy Hosts y Certificados actuales
            $proxyModel = new ProxyHostModel();
            $certModel  = new CertificateModel();
            $proxies    = $proxyModel->orderBy('id', 'ASC')->findAll();
            $certs      = $certModel->orderBy('id', 'ASC')->findAll();

            // 3. Empaquetar en archivo ZIP
            $zip = new ZipArchive();
            if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('No se pudo crear el archivo comprimido de backup.');
            }

            $zip->addFile($tempSqlite, 'database.sqlite');

            $nginxFiles = [];
            foreach ($proxies as $proxy) {
                $confName = 'nginx/proxy-host-' . (int) $proxy['id'] . '.conf';
                try {
                    $confContent = $this->configGenerator->generate($proxy);
                    $zip->addFromString($confName, $confContent);
                    $nginxFiles[] = $confName;
                } catch (Throwable) {
                    // Ignorar si algún host histórico tuviera formato inválido
                }
            }

            $zip->addFromString(
                'certificates/certificates.json',
                (string) json_encode($certs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $manifest = [
                'created_at'         => date('Y-m-d H:i:s'),
                'version'            => '1.0',
                'sha256_database'    => $dbSha256,
                'proxies_count'      => count($proxies),
                'certificates_count' => count($certs),
                'nginx_configs'      => $nginxFiles,
            ];

            $zip->addFromString(
                'manifest.json',
                (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $zip->close();
            @unlink($tempSqlite);
            @chmod($targetPath, 0600);

            $size = (int) @filesize($targetPath);

            // 4. Aplicar retención configurable
            $this->pruneOldBackups($this->getRetentionCount());

            AuditLogModel::log(
                'backup_create',
                "Copia de seguridad creada con éxito: {$filename} (" . $this->formatBytes($size) . ")"
            );

            return [
                'success'  => true,
                'filename' => $filename,
                'path'     => $targetPath,
                'size'     => $size,
                'error'    => null,
            ];
        } catch (Throwable $e) {
            if (file_exists($tempSqlite)) {
                @unlink($tempSqlite);
            }
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }

            AuditLogModel::log(
                'backup_create_failed',
                'Error al generar copia de seguridad: ' . $e->getMessage()
            );

            return [
                'success'  => false,
                'filename' => null,
                'path'     => null,
                'size'     => 0,
                'error'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Lista todas las copias de seguridad disponibles ordenadas de más reciente a más antigua.
     *
     * @return list<array{filename: string, size: int, size_formatted: string, created_at: string, timestamp: int, proxies_count: int, certificates_count: int}>
     */
    public function listBackups(): array
    {
        if (!is_dir($this->backupDir)) {
            return [];
        }

        $files = glob($this->backupDir . DIRECTORY_SEPARATOR . 'backup_*.zip') ?: [];
        $backups = [];

        foreach ($files as $filePath) {
            if (!is_file($filePath)) {
                continue;
            }

            $filename = basename($filePath);
            if (!preg_match('/^backup_[a-zA-Z0-9_\-]+\.zip$/', $filename)) {
                continue;
            }

            $size  = (int) @filesize($filePath);
            $mtime = (int) @filemtime($filePath);
            $proxiesCount = 0;
            $certsCount   = 0;
            $createdAt    = date('Y-m-d H:i:s', $mtime);

            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $manifestRaw = $zip->getFromName('manifest.json');
                if (is_string($manifestRaw)) {
                    $manifest = json_decode($manifestRaw, true);
                    if (is_array($manifest)) {
                        $proxiesCount = (int) ($manifest['proxies_count'] ?? 0);
                        $certsCount   = (int) ($manifest['certificates_count'] ?? 0);
                        if (!empty($manifest['created_at'])) {
                            $createdAt = (string) $manifest['created_at'];
                        }
                    }
                }
                $zip->close();
            }

            $backups[] = [
                'filename'           => $filename,
                'size'               => $size,
                'size_formatted'     => $this->formatBytes($size),
                'created_at'         => $createdAt,
                'timestamp'          => $mtime,
                'proxies_count'      => $proxiesCount,
                'certificates_count' => $certsCount,
            ];
        }

        usort(
            $backups,
            static fn(array $a, array $b): int => ($b['timestamp'] <=> $a['timestamp']) ?: strcmp($b['filename'], $a['filename'])
        );

        return $backups;
    }

    /**
     * Valida la integridad de un archivo de backup antes de restaurarlo.
     * Comprueba firma ZIP, manifest.json, hash SHA-256, PRAGMA integrity_check y tablas requeridas.
     *
     * @return array{valid: bool, error: ?string, manifest: ?array}
     */
    public function validateBackupArchive(string $filename): array
    {
        try {
            $safePath = $this->resolveSafeBackupPath($filename);
        } catch (InvalidArgumentException $e) {
            return ['valid' => false, 'error' => $e->getMessage(), 'manifest' => null];
        }

        if (!is_file($safePath) || !is_readable($safePath)) {
            return ['valid' => false, 'error' => 'El archivo de copia de seguridad no existe o no es legible.', 'manifest' => null];
        }

        $zip = new ZipArchive();
        if ($zip->open($safePath) !== true) {
            return ['valid' => false, 'error' => 'El archivo ZIP está corrupto o no tiene un formato válido.', 'manifest' => null];
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        $dbStream    = $zip->getFromName('database.sqlite');
        $zip->close();

        if ($manifestRaw === false || $dbStream === false) {
            return [
                'valid'    => false,
                'error'    => 'El backup está incompleto: faltan database.sqlite o manifest.json.',
                'manifest' => null,
            ];
        }

        $manifest = json_decode($manifestRaw, true);
        if (!is_array($manifest) || empty($manifest['sha256_database'])) {
            return [
                'valid'    => false,
                'error'    => 'El manifiesto del backup es inválido o carece de firma SHA-256.',
                'manifest' => null,
            ];
        }

        $tempVerifyDb = $this->backupDir . DIRECTORY_SEPARATOR . '.tmp_verify_' . bin2hex(random_bytes(4)) . '.db';
        file_put_contents($tempVerifyDb, $dbStream);

        try {
            $actualHash = hash_file('sha256', $tempVerifyDb);
            if (!hash_equals((string) $manifest['sha256_database'], (string) $actualHash)) {
                @unlink($tempVerifyDb);
                return [
                    'valid'    => false,
                    'error'    => 'Fallo de integridad: El hash SHA-256 de la base de datos no coincide con el manifiesto.',
                    'manifest' => null,
                ];
            }

            if (!$this->checkSqliteFileIntegrity($tempVerifyDb, true)) {
                @unlink($tempVerifyDb);
                return [
                    'valid'    => false,
                    'error'    => 'La base de datos contenida en el backup está corrupta o carece del esquema requerido.',
                    'manifest' => null,
                ];
            }
        } finally {
            if (file_exists($tempVerifyDb)) {
                @unlink($tempVerifyDb);
            }
        }

        return [
            'valid'    => true,
            'error'    => null,
            'manifest' => $manifest,
        ];
    }

    /**
     * Restaura de forma atómica una copia de seguridad tras validar su integridad.
     *
     * @return array{success: bool, message: ?string, error: ?string}
     */
    public function restoreBackup(string $filename): array
    {
        $validation = $this->validateBackupArchive($filename);
        if (!$validation['valid']) {
            AuditLogModel::log(
                'backup_restore_failed',
                "Fallo de validación al restaurar {$filename}: " . ($validation['error'] ?? 'Inválido')
            );
            return [
                'success' => false,
                'message' => null,
                'error'   => $validation['error'],
            ];
        }

        $safePath = $this->resolveSafeBackupPath($filename);
        $tempRestoreDb = $this->backupDir . DIRECTORY_SEPARATOR . '.tmp_restore_' . bin2hex(random_bytes(4)) . '.db';

        $zip = new ZipArchive();
        $zip->open($safePath);
        file_put_contents($tempRestoreDb, (string) $zip->getFromName('database.sqlite'));
        $zip->close();

        try {
            $sourceSqlite = new SQLite3($tempRestoreDb, SQLITE3_OPEN_READONLY);

            // Leer tablas a restaurar desde la instantánea verificada
            $proxies  = $this->fetchAllRows($sourceSqlite, 'proxy_hosts');
            $certs    = $this->fetchAllRows($sourceSqlite, 'certificates');
            $settings = $this->fetchAllRows($sourceSqlite, 'settings');
            $sourceSqlite->close();

            $this->db->transStart();

            // Restaurar certificados, proxy_hosts y settings
            $this->db->table('proxy_hosts')->emptyTable();
            foreach ($proxies as $row) {
                $this->db->table('proxy_hosts')->insert($row);
            }

            $this->db->table('certificates')->emptyTable();
            foreach ($certs as $row) {
                $this->db->table('certificates')->insert($row);
            }

            $this->db->table('settings')->emptyTable();
            foreach ($settings as $row) {
                $this->db->table('settings')->insert($row);
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('La transacción de restauración en SQLite falló.');
            }

            // Re-aplicar configuraciones de Nginx para los hosts activos restaurados
            foreach ($proxies as $proxy) {
                if (!empty($proxy['enabled'])) {
                    $this->nginxManager->applyHost($proxy);
                } else {
                    $this->nginxManager->disableHost((int) $proxy['id']);
                }
            }

            @unlink($tempRestoreDb);

            AuditLogModel::log(
                'backup_restore',
                "Sistema restaurado satisfactoriamente desde la copia de seguridad: {$filename} (" . count($proxies) . " hosts, " . count($certs) . " certificados)."
            );

            return [
                'success' => true,
                'message' => "Copia de seguridad \"{$filename}\" restaurada con éxito.",
                'error'   => null,
            ];
        } catch (Throwable $e) {
            if (file_exists($tempRestoreDb)) {
                @unlink($tempRestoreDb);
            }

            AuditLogModel::log(
                'backup_restore_failed',
                "Error durante la restauración de {$filename}: " . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => null,
                'error'   => 'Error al restaurar copia de seguridad: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Elimina una copia de seguridad validando su ruta canónica.
     */
    public function deleteBackup(string $filename): bool
    {
        $safePath = $this->resolveSafeBackupPath($filename);
        if (!is_file($safePath)) {
            return false;
        }

        $deleted = @unlink($safePath);
        if ($deleted) {
            AuditLogModel::log('backup_delete', "Eliminada copia de seguridad: {$filename}");
        }

        return $deleted;
    }

    /**
     * Elimina las copias más antiguas que excedan el límite de retención configurado.
     *
     * @return int Número de copias antiguas eliminadas
     */
    public function pruneOldBackups(int $maxKeep): int
    {
        $maxKeep = max(1, $maxKeep);
        $backups = $this->listBackups();

        if (count($backups) <= $maxKeep) {
            return 0;
        }

        $toDelete = array_slice($backups, $maxKeep);
        $deletedCount = 0;

        foreach ($toDelete as $item) {
            try {
                $path = $this->resolveSafeBackupPath($item['filename']);
                if (is_file($path) && @unlink($path)) {
                    $deletedCount++;
                }
            } catch (Throwable) {
                // Continuar con el resto
            }
        }

        return $deletedCount;
    }

    /**
     * Valida canónicamente la ruta de un archivo de backup contra Path Traversal.
     *
     * @throws InvalidArgumentException Si el nombre contiene caracteres de escape o sale de writable/backups
     */
    public function resolveSafeBackupPath(string $filename): string
    {
        $filename = trim($filename);

        if (
            $filename === ''
            || str_contains($filename, '..')
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || str_contains($filename, "\0")
            || !preg_match('/^backup_[a-zA-Z0-9_\-]+\.zip$/', $filename)
        ) {
            throw new InvalidArgumentException("Acceso denegado: Nombre de archivo de backup inválido ({$filename}).");
        }

        $realBase = realpath($this->backupDir);
        if ($realBase === false) {
            throw new InvalidArgumentException('Directorio de backups inaccesible.');
        }

        $candidate = $realBase . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($candidate)) {
            $realFile = realpath($candidate);
            if ($realFile === false || !str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR)) {
                throw new InvalidArgumentException('Intento de Path Traversal bloqueado en backups.');
            }
            return $realFile;
        }

        return $candidate;
    }

    /**
     * Ejecuta `PRAGMA integrity_check` y opcionalmente verifica que existan las tablas esenciales.
     */
    protected function checkSqliteFileIntegrity(string $sqlitePath, bool $verifyTables = false): bool
    {
        try {
            $sqlite = new SQLite3($sqlitePath, SQLITE3_OPEN_READONLY);
            $integrity = $sqlite->querySingle('PRAGMA integrity_check;');
            if (strtolower((string) $integrity) !== 'ok') {
                $sqlite->close();
                return false;
            }

            if ($verifyTables) {
                $prefix = $this->db->getPrefix();
                $requiredTables = ['users', 'proxy_hosts', 'certificates', 'settings', 'audit_logs'];
                foreach ($requiredTables as $table) {
                    $prefixed = $prefix . $table;
                    $exists = $sqlite->querySingle(
                        "SELECT name FROM sqlite_master WHERE type='table' AND name IN ('{$table}', '{$prefixed}');"
                    );
                    if (empty($exists)) {
                        $sqlite->close();
                        return false;
                    }
                }
            }

            $sqlite->close();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Extrae todas las filas de una tabla de un archivo SQLite abierto, soportando prefijos de tabla.
     *
     * @return list<array<string, mixed>>
     */
    protected function fetchAllRows(SQLite3 $sqlite, string $table): array
    {
        $prefix = $this->db->getPrefix();
        $prefixed = $prefix . $table;

        $actualTable = $sqlite->querySingle(
            "SELECT name FROM sqlite_master WHERE type='table' AND name IN ('{$prefixed}', '{$table}') LIMIT 1;"
        );

        if (empty($actualTable)) {
            return [];
        }

        $rows = [];
        $res = $sqlite->query("SELECT * FROM {$actualTable}");
        if ($res === false) {
            return [];
        }

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Formatea un tamaño en bytes a representación legible (KB / MB).
     */
    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
