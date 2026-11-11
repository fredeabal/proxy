<?php

namespace App\Services;

use App\Models\AuditLogModel;
use CodeIgniter\Cache\FileLocatorCached;
use Config\Cache as CacheConfig;
use Config\Session as SessionConfig;
use Throwable;

/**
 * Gestor de Mantenimiento del Sistema:
 *  - Limpieza y purga de sesiones de usuario (preservando la sesión actual).
 *  - Limpieza de caché de aplicación, limitadores de tasa (throttlers) y OPcache.
 *  - Limpieza de ficheros de registro (logs de CodeIgniter) y volcados de depuración.
 *  - Mantenimiento y purga de registros de auditoría en base de datos.
 *  - Optimización de base de datos SQLite (VACUUM).
 */
class MaintenanceManager
{
    protected string $sessionPath;
    protected string $cachePath;
    protected string $logsPath;
    protected string $debugbarPath;

    public function __construct(
        ?string $sessionPath = null,
        ?string $cachePath = null,
        ?string $logsPath = null,
        ?string $debugbarPath = null
    ) {
        $this->sessionPath  = rtrim($sessionPath ?? (WRITEPATH . 'session'), '/');
        $this->cachePath    = rtrim($cachePath ?? (WRITEPATH . 'cache'), '/');
        $this->logsPath     = rtrim($logsPath ?? (WRITEPATH . 'logs'), '/');
        $this->debugbarPath = rtrim($debugbarPath ?? (WRITEPATH . 'debugbar'), '/');
    }

    /**
     * Recopila todas las métricas globales para el panel de mantenimiento.
     *
     * @return array<string, mixed>
     */
    public function getOverviewStats(): array
    {
        return [
            'sessions' => $this->getSessionStats(),
            'cache'    => $this->getCacheStats(),
            'logs'     => $this->getLogsStats(),
            'storage'  => $this->getStorageHealth(),
        ];
    }

    // =========================================================================
    // 1. GESTIÓN DE SESIONES
    // =========================================================================

    /**
     * Devuelve estadísticas de las sesiones de usuario en disco.
     *
     * @return array<string, mixed>
     */
    public function getSessionStats(): array
    {
        $sessionConfig = config(SessionConfig::class);
        $expiration    = (int) ($sessionConfig->expiration ?? 7200);
        if ($expiration <= 0) {
            $expiration = 7200;
        }

        $currentId   = (string) session_id();
        $totalFiles  = 0;
        $totalSize   = 0;
        $activeCount = 0;
        $expiredCount = 0;
        $now         = time();

        if (is_dir($this->sessionPath)) {
            $files = scandir($this->sessionPath) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html' || $file === '.gitkeep') {
                    continue;
                }

                $filePath = $this->sessionPath . DIRECTORY_SEPARATOR . $file;
                if (!is_file($filePath)) {
                    continue;
                }

                $totalFiles++;
                $size = (int) @filesize($filePath);
                $totalSize += $size;

                $mtime = (int) @filemtime($filePath);
                if (($now - $mtime) > $expiration) {
                    $expiredCount++;
                } else {
                    $activeCount++;
                }
            }
        }

        return [
            'total_files'          => $totalFiles,
            'total_size'           => $totalSize,
            'total_size_human'     => $this->formatBytes($totalSize),
            'active_count'         => $activeCount,
            'expired_count'        => $expiredCount,
            'current_session_id'   => $currentId,
            'current_session_file' => $currentId !== '' ? 'ci_session' . $currentId : '',
            'expiration_seconds'   => $expiration,
            'expiration_minutes'   => (int) round($expiration / 60),
            'driver'               => (string) ($sessionConfig->driver ?? 'FileHandler'),
        ];
    }

    /**
     * Limpia los archivos de sesión.
     *
     * @param bool $onlyExpired   Si es true, borra únicamente sesiones que superaron el tiempo de expiración.
     * @param bool $preserveCurrent Si es true, nunca borra la sesión del usuario actual que ejecuta la acción.
     * @return array{deleted: int, freed_bytes: int, freed_human: string, preserved: int}
     */
    public function clearSessions(bool $onlyExpired = false, bool $preserveCurrent = true): array
    {
        $sessionConfig = config(SessionConfig::class);
        $expiration    = (int) ($sessionConfig->expiration ?? 7200);
        if ($expiration <= 0) {
            $expiration = 7200;
        }

        $currentId    = (string) session_id();
        $currentFile  = $currentId !== '' ? 'ci_session' . $currentId : '';
        $now          = time();
        $deletedCount = 0;
        $freedBytes   = 0;
        $preserved    = 0;

        if (is_dir($this->sessionPath)) {
            $files = scandir($this->sessionPath) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html' || $file === '.gitkeep') {
                    continue;
                }

                $filePath = $this->sessionPath . DIRECTORY_SEPARATOR . $file;
                if (!is_file($filePath)) {
                    continue;
                }

                // Preservar la sesión activa del administrador
                if ($preserveCurrent && $currentId !== '') {
                    if ($file === $currentFile || str_contains($file, $currentId)) {
                        $preserved++;
                        continue;
                    }
                }

                $mtime = (int) @filemtime($filePath);
                $isExpired = ($now - $mtime) > $expiration;

                if ($onlyExpired && !$isExpired) {
                    $preserved++;
                    continue;
                }

                $size = (int) @filesize($filePath);
                if (@unlink($filePath)) {
                    $deletedCount++;
                    $freedBytes += $size;
                }
            }
        }

        $freedHuman = $this->formatBytes($freedBytes);
        $modeText   = $onlyExpired ? 'expiradas' : 'totales (excepto activa)';
        AuditLogModel::log(
            'maintenance_sessions_clear',
            "Limpieza de sesiones ({$modeText}): {$deletedCount} eliminadas, {$preserved} preservadas. Espacio liberado: {$freedHuman}"
        );

        return [
            'deleted'     => $deletedCount,
            'freed_bytes' => $freedBytes,
            'freed_human' => $freedHuman,
            'preserved'   => $preserved,
        ];
    }

    // =========================================================================
    // 2. GESTIÓN DE CACHÉ
    // =========================================================================

    /**
     * Devuelve estadísticas de la caché del sistema.
     *
     * @return array<string, mixed>
     */
    public function getCacheStats(): array
    {
        $cacheConfig    = config(CacheConfig::class);
        $handler        = (string) ($cacheConfig->handler ?? 'file');
        $totalFiles     = 0;
        $totalSize      = 0;
        $throttlerFiles = 0;
        $appCacheFiles  = 0;

        if (is_dir($this->cachePath)) {
            $files = scandir($this->cachePath) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html' || $file === '.gitkeep') {
                    continue;
                }

                $filePath = $this->cachePath . DIRECTORY_SEPARATOR . $file;
                if (!is_file($filePath)) {
                    continue;
                }

                $totalFiles++;
                $size = (int) @filesize($filePath);
                $totalSize += $size;

                if (str_starts_with($file, 'throttler_')) {
                    $throttlerFiles++;
                } else {
                    $appCacheFiles++;
                }
            }
        }

        $opcacheEnabled = extension_loaded('Zend OPcache') && (bool) ini_get('opcache.enable');
        $opcacheMemory  = null;
        if ($opcacheEnabled && function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if (is_array($status) && isset($status['memory_usage']['used_memory'])) {
                $opcacheMemory = $this->formatBytes((int) $status['memory_usage']['used_memory']);
            }
        }

        return [
            'handler'             => $handler,
            'total_files'         => $totalFiles,
            'total_size'          => $totalSize,
            'total_size_human'    => $this->formatBytes($totalSize),
            'throttler_files'     => $throttlerFiles,
            'app_cache_files'     => $appCacheFiles,
            'opcache_enabled'     => $opcacheEnabled,
            'opcache_memory_used' => $opcacheMemory,
        ];
    }

    /**
     * Limpia la caché de la aplicación, rate limiters y reinicia OPcache.
     *
     * @return array{deleted_files: int, freed_bytes: int, freed_human: string, opcache_reset: bool}
     */
    public function clearCache(): array
    {
        $deletedCount = 0;
        $freedBytes   = 0;

        // 1. Limpieza a través del servicio nativo de CodeIgniter 4
        try {
            service('cache')->clean();
        } catch (Throwable $e) {
            // Continuar con limpieza directa en disco si el handler falla
        }

        // 2. Limpieza de FileLocator cache si está en memoria
        try {
            $locator = service('locator');
            if ($locator instanceof FileLocatorCached) {
                $locator->deleteCache();
            }
        } catch (Throwable $e) {
            // Ignorar si no está configurado
        }

        // 3. Purga directa de archivos temporales en el directorio de caché
        if (is_dir($this->cachePath)) {
            $files = scandir($this->cachePath) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html' || $file === '.gitkeep') {
                    continue;
                }

                $filePath = $this->cachePath . DIRECTORY_SEPARATOR . $file;
                if (!is_file($filePath)) {
                    continue;
                }

                $size = (int) @filesize($filePath);
                if (@unlink($filePath)) {
                    $deletedCount++;
                    $freedBytes += $size;
                }
            }
        }

        // 4. Reinicio de OPcache si está disponible
        $opcacheReset = false;
        if (function_exists('opcache_reset')) {
            $opcacheReset = @opcache_reset();
        }

        $freedHuman = $this->formatBytes($freedBytes);
        AuditLogModel::log(
            'maintenance_cache_clear',
            "Caché del sistema vaciada ({$deletedCount} archivos). Espacio liberado: {$freedHuman}" . ($opcacheReset ? " (OPcache reiniciado)" : "")
        );

        return [
            'deleted_files' => $deletedCount,
            'freed_bytes'   => $freedBytes,
            'freed_human'   => $freedHuman,
            'opcache_reset' => $opcacheReset,
        ];
    }

    // =========================================================================
    // 3. GESTIÓN DE LOGS DE APLICACIÓN Y DEPURACIÓN
    // =========================================================================

    /**
     * Devuelve estadísticas de logs de la aplicación y auditoría.
     *
     * @return array<string, mixed>
     */
    public function getLogsStats(): array
    {
        $logFiles   = [];
        $totalSize  = 0;
        $totalFiles = 0;
        $todayFile  = 'log-' . date('Y-m-d') . '.log';

        if (is_dir($this->logsPath)) {
            $files = scandir($this->logsPath) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html' || $file === '.gitkeep') {
                    continue;
                }

                $filePath = $this->logsPath . DIRECTORY_SEPARATOR . $file;
                if (!is_file($filePath)) {
                    continue;
                }

                $totalFiles++;
                $size  = (int) @filesize($filePath);
                $mtime = (int) @filemtime($filePath);
                $totalSize += $size;

                $logFiles[] = [
                    'name'       => $file,
                    'size'       => $size,
                    'size_human' => $this->formatBytes($size),
                    'modified'   => date('Y-m-d H:i:s', $mtime),
                    'is_today'   => ($file === $todayFile),
                ];
            }
        }

        // Ordenar archivos de log por fecha de modificación descendente
        usort($logFiles, static fn(array $a, array $b): int => strcmp($b['name'], $a['name']));

        // Archivos de depuración (debugbar)
        $debugbarFiles = 0;
        $debugbarSize  = 0;
        if (is_dir($this->debugbarPath)) {
            $dFiles = scandir($this->debugbarPath) ?: [];
            foreach ($dFiles as $df) {
                if ($df === '.' || $df === '..' || $df === '.htaccess' || $df === 'index.html' || $df === '.gitkeep') {
                    continue;
                }
                $dfPath = $this->debugbarPath . DIRECTORY_SEPARATOR . $df;
                if (is_file($dfPath)) {
                    $debugbarFiles++;
                    $debugbarSize += (int) @filesize($dfPath);
                }
            }
        }

        // Estadísticas de la tabla de auditoría
        $auditModel = new AuditLogModel();
        $auditCount = 0;
        $auditOldest = null;
        $auditNewest = null;

        try {
            $auditCount = (int) $auditModel->countAllResults();
            if ($auditCount > 0) {
                $oldestRow = $auditModel->select('created_at')->orderBy('id', 'ASC')->first();
                $newestRow = $auditModel->select('created_at')->orderBy('id', 'DESC')->first();
                $auditOldest = $oldestRow['created_at'] ?? null;
                $auditNewest = $newestRow['created_at'] ?? null;
            }
        } catch (Throwable $e) {
            // Ignorar si la tabla no está disponible temporalmente
        }

        return [
            'ci_files'             => $logFiles,
            'ci_files_count'       => $totalFiles,
            'ci_total_size'        => $totalSize,
            'ci_total_size_human'  => $this->formatBytes($totalSize),
            'debugbar_files_count' => $debugbarFiles,
            'debugbar_size'        => $debugbarSize,
            'debugbar_size_human'  => $this->formatBytes($debugbarSize),
            'audit_total_rows'     => $auditCount,
            'audit_oldest_date'    => $auditOldest,
            'audit_newest_date'    => $auditNewest,
        ];
    }

    /**
     * Limpia los archivos de log de la aplicación y volcados de depuración.
     *
     * @param bool $keepToday Si es true, conserva el archivo de registro del día actual.
     * @return array{deleted_files: int, freed_bytes: int, freed_human: string, kept_today: bool}
     */
    public function clearAppLogs(bool $keepToday = true): array
    {
        $deletedCount = 0;
        $freedBytes   = 0;
        $todayFile    = 'log-' . date('Y-m-d') . '.log';
        $keptToday    = false;

        // 1. Limpieza de logs CI
        if (is_dir($this->logsPath)) {
            $files = scandir($this->logsPath) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html' || $file === '.gitkeep') {
                    continue;
                }

                $filePath = $this->logsPath . DIRECTORY_SEPARATOR . $file;
                if (!is_file($filePath)) {
                    continue;
                }

                if ($keepToday && $file === $todayFile) {
                    $keptToday = true;
                    continue;
                }

                $size = (int) @filesize($filePath);
                if (@unlink($filePath)) {
                    $deletedCount++;
                    $freedBytes += $size;
                }
            }
        }

        // 2. Limpieza de debugbar si existe
        if (is_dir($this->debugbarPath)) {
            $dFiles = scandir($this->debugbarPath) ?: [];
            foreach ($dFiles as $df) {
                if ($df === '.' || $df === '..' || $df === '.htaccess' || $df === 'index.html' || $df === '.gitkeep') {
                    continue;
                }

                $dfPath = $this->debugbarPath . DIRECTORY_SEPARATOR . $df;
                if (is_file($dfPath)) {
                    $size = (int) @filesize($dfPath);
                    if (@unlink($dfPath)) {
                        $deletedCount++;
                        $freedBytes += $size;
                    }
                }
            }
        }

        $freedHuman = $this->formatBytes($freedBytes);
        $scopeMsg   = $keepToday ? "conservando log de hoy ({$todayFile})" : "todos los archivos";
        AuditLogModel::log(
            'maintenance_logs_clear',
            "Logs de aplicación eliminados ({$deletedCount} archivos, {$scopeMsg}). Espacio liberado: {$freedHuman}"
        );

        return [
            'deleted_files' => $deletedCount,
            'freed_bytes'   => $freedBytes,
            'freed_human'   => $freedHuman,
            'kept_today'    => $keptToday,
        ];
    }

    /**
     * Limpia registros de la tabla de auditoría en base de datos.
     *
     * @param string $mode 'all' para vaciar todo, 'older_than' para días anteriores
     * @param int    $days Días de antigüedad si el modo es 'older_than'
     * @return array{deleted_rows: int, mode: string}
     */
    public function clearAuditLogs(string $mode = 'older_than', int $days = 30): array
    {
        $auditModel   = new AuditLogModel();
        $deletedCount = 0;

        if ($mode === 'older_than' && $days > 0) {
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $builder = $auditModel->builder();
            $deletedCount = $builder->where('created_at <', $cutoff)->countAllResults(false);
            $builder->where('created_at <', $cutoff)->delete();
        } else {
            $builder = $auditModel->builder();
            $deletedCount = $builder->countAllResults(false);
            $builder->emptyTable();
            $mode = 'all';
        }

        AuditLogModel::log(
            'maintenance_audit_clear',
            "Limpieza de tabla de auditoría ({$deletedCount} registros eliminados, modo: {$mode})"
        );

        return [
            'deleted_rows' => $deletedCount,
            'mode'         => $mode,
        ];
    }

    // =========================================================================
    // 4. OPTIMIZACIÓN DE BASE DE DATOS SQLITE (VACUUM)
    // =========================================================================

    /**
     * Ejecuta SQLite VACUUM para defragmentar y compactar la base de datos.
     *
     * @return array{success: bool, before_bytes: int, after_bytes: int, freed_human: string}
     */
    public function optimizeDatabase(): array
    {
        $dbPath = WRITEPATH . 'database/database.sqlite';
        $beforeSize = is_file($dbPath) ? (int) @filesize($dbPath) : 0;

        $db = db_connect();
        if ($db->DBDriver === 'SQLite3') {
            $db->query('VACUUM;');
            // Limpiar caché de estados de archivo
            clearstatcache(true, $dbPath);
        }

        $afterSize  = is_file($dbPath) ? (int) @filesize($dbPath) : 0;
        $freedBytes = max(0, $beforeSize - $afterSize);
        $freedHuman = $this->formatBytes($freedBytes);

        AuditLogModel::log(
            'maintenance_db_optimize',
            "Optimización de Base de Datos SQLite completada (VACUUM). Espacio compactado: {$freedHuman}"
        );

        return [
            'success'      => true,
            'before_bytes' => $beforeSize,
            'after_bytes'  => $afterSize,
            'freed_human'  => $freedHuman,
        ];
    }

    // =========================================================================
    // 5. MANTENIMIENTO COMPLETO INTEGRADO
    // =========================================================================

    /**
     * Ejecuta una optimización integral en un solo paso:
     *  - Limpia caché del sistema.
     *  - Limpia sesiones inactivas (preservando la del usuario actual).
     *  - Limpia logs antiguos de la aplicación (conservando el del día en curso).
     *
     * @return array<string, mixed>
     */
    public function runFullMaintenance(bool $keepTodayLogs = true, bool $onlyExpiredSessions = true): array
    {
        $cacheResult   = $this->clearCache();
        $sessionResult = $this->clearSessions($onlyExpiredSessions, true);
        $logsResult    = $this->clearAppLogs($keepTodayLogs);

        $totalFreedBytes = $cacheResult['freed_bytes'] + $sessionResult['freed_bytes'] + $logsResult['freed_bytes'];
        $totalFreedHuman = $this->formatBytes($totalFreedBytes);

        AuditLogModel::log(
            'maintenance_full',
            "Mantenimiento integral completado. Total liberado: {$totalFreedHuman} " .
            "(Caché: {$cacheResult['freed_human']}, Sesiones: {$sessionResult['freed_human']}, Logs: {$logsResult['freed_human']})"
        );

        return [
            'success'           => true,
            'cache'             => $cacheResult,
            'sessions'          => $sessionResult,
            'logs'              => $logsResult,
            'total_freed_bytes' => $totalFreedBytes,
            'total_freed_human' => $totalFreedHuman,
        ];
    }

    // =========================================================================
    // 6. ESTADO DE ALMACENAMIENTO Y PERMISOS
    // =========================================================================

    /**
     * Devuelve el estado de almacenamiento en disco y permisos de escritura.
     *
     * @return array<string, mixed>
     */
    public function getStorageHealth(): array
    {
        $freeBytes  = @disk_free_space(WRITEPATH) ?: 0;
        $totalBytes = @disk_total_space(WRITEPATH) ?: 0;
        $usedBytes  = max(0, $totalBytes - $freeBytes);
        $usedPct    = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 1) : 0.0;

        $directories = [
            'session'  => ['path' => $this->sessionPath, 'label' => 'writable/session'],
            'cache'    => ['path' => $this->cachePath, 'label' => 'writable/cache'],
            'logs'     => ['path' => $this->logsPath, 'label' => 'writable/logs'],
            'backups'  => ['path' => WRITEPATH . 'backups', 'label' => 'writable/backups'],
            'database' => ['path' => WRITEPATH . 'database', 'label' => 'writable/database'],
        ];

        $permissions = [];
        foreach ($directories as $key => $dir) {
            $exists   = is_dir($dir['path']);
            $writable = $exists && is_writable($dir['path']);
            $permissions[$key] = [
                'label'    => $dir['label'],
                'exists'   => $exists,
                'writable' => $writable,
            ];
        }

        return [
            'free_bytes'   => $freeBytes,
            'free_human'   => $this->formatBytes((int) $freeBytes),
            'total_bytes'  => $totalBytes,
            'total_human'  => $this->formatBytes((int) $totalBytes),
            'used_bytes'   => $usedBytes,
            'used_human'   => $this->formatBytes((int) $usedBytes),
            'used_percent' => $usedPct,
            'permissions'  => $permissions,
            'php_version'  => PHP_VERSION,
        ];
    }

    /**
     * Formatea un número de bytes a una representación legible (B, KB, MB, GB).
     */
    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}

