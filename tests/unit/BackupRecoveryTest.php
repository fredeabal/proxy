<?php

namespace Tests\Unit;

use App\Models\AuditLogModel;
use App\Models\CertificateModel;
use App\Models\ProxyHostModel;
use App\Services\BackupManager;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use InvalidArgumentException;
use ZipArchive;

/**
 * Pruebas unitarias y de integración para la Fase 8: Backups y Recuperación ante Desastres.
 *
 * @internal
 */
final class BackupRecoveryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $seed      = 'App\Database\Seeds\AdminSeeder';

    protected string $testBackupDir = '';

    protected array $adminSession = [
        'is_logged_in' => true,
        'user_id'      => 1,
        'user_name'    => 'Administrador',
        'user_email'   => 'admin@demo.com',
        'user_role'    => 'admin',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->testBackupDir = WRITEPATH . 'backups/test_' . uniqid();
        @mkdir($this->testBackupDir, 0750, true);
    }

    protected function tearDown(): void
    {
        if ($this->testBackupDir !== '' && is_dir($this->testBackupDir)) {
            foreach (glob($this->testBackupDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            foreach (glob($this->testBackupDir . '/.*') ?: [] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($this->testBackupDir);
        }
        parent::tearDown();
    }

    protected function withCsrf(array $data): array
    {
        $security = service('security');
        $data[$security->getTokenName()] = $security->getHash();
        return $data;
    }

    public function testGuestCannotAccessBackups(): void
    {
        $result = $this->withSession([])->get('backups');
        $result->assertRedirectTo('/login');
    }

    public function testAuthenticatedAdminCanViewBackupsPage(): void
    {
        $redirect = $this->withSession($this->adminSession)->get('backups');
        $redirect->assertRedirectTo(base_url('admin?tab=backups'));

        $result = $this->withSession($this->adminSession)->get('admin', ['tab' => 'backups']);
        $result->assertStatus(200);
        $result->assertSee('Administración');
        $result->assertSee('Política de Retención');
    }

    public function testCreateAtomicVacuumBackupAndRestoreDisasterRecovery(): void
    {
        $proxyModel = new ProxyHostModel();
        $certModel  = new CertificateModel();

        $certId = $certModel->insert([
            'domain'           => 'resiliencia.empresa.com',
            'certificate_path' => '/etc/letsencrypt/live/resiliencia.empresa.com/fullchain.pem',
            'private_key_path' => '/etc/letsencrypt/live/resiliencia.empresa.com/privkey.pem',
            'issuer'           => "Let's Encrypt",
            'expires_at'       => date('Y-m-d H:i:s', strtotime('+60 days')),
            'auto_renew'       => 1,
            'status'           => 'valid',
        ]);

        $hostId = $proxyModel->insert([
            'domain'                => 'resiliencia.empresa.com',
            'domain_names'          => 'www.resiliencia.empresa.com',
            'scheme'                => 'https',
            'forward_host'          => '10.0.0.50',
            'forward_port'          => 8443,
            'ssl_enabled'           => 1,
            'websocket_enabled'     => 1,
            'force_https'           => 1,
            'enabled'               => 1,
            'certificate_id'        => $certId,
            'proxy_connect_timeout' => 90,
            'client_max_body_size'  => 25,
        ]);

        $manager = new BackupManager(null, $this->testBackupDir);

        // 1. Generar backup atómico
        $backupRes = $manager->createBackup();
        $this->assertTrue($backupRes['success']);
        $this->assertNotEmpty($backupRes['filename']);
        $this->assertFileExists($backupRes['path']);

        // Verificar contenido del ZIP (database.sqlite, manifest.json, nginx/*.conf, certificates.json)
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($backupRes['path']) === true);
        $this->assertNotFalse($zip->getFromName('database.sqlite'));
        $this->assertNotFalse($zip->getFromName('manifest.json'));
        $this->assertNotFalse($zip->getFromName("nginx/proxy-host-{$hostId}.conf"));
        $this->assertNotFalse($zip->getFromName('certificates/certificates.json'));
        $zip->close();

        // 2. Simular desastre: borrar el Proxy Host y el Certificado de la BD activa
        $proxyModel->delete($hostId);
        $certModel->delete($certId);
        $this->assertNull($proxyModel->find($hostId));
        $this->assertNull($certModel->find($certId));

        // 3. Restaurar desde el backup y verificar recuperación íntegra
        $restoreRes = $manager->restoreBackup($backupRes['filename']);
        $this->assertTrue($restoreRes['success']);

        $restoredHost = $proxyModel->find($hostId);
        $restoredCert = $certModel->find($certId);
        $this->assertNotNull($restoredHost);
        $this->assertNotNull($restoredCert);
        $this->assertSame('resiliencia.empresa.com', $restoredHost['domain']);
        $this->assertSame(90, (int) $restoredHost['proxy_connect_timeout']);
        $this->assertSame(25, (int) $restoredHost['client_max_body_size']);

        // Verificar auditoría de backup_create y backup_restore
        $auditModel = new AuditLogModel();
        $this->assertNotNull($auditModel->where('action', 'backup_create')->first());
        $this->assertNotNull($auditModel->where('action', 'backup_restore')->first());
    }

    public function testRestoreAbortsWhenBackupIntegrityIsCompromised(): void
    {
        $manager = new BackupManager(null, $this->testBackupDir);
        $backupRes = $manager->createBackup();
        $this->assertTrue($backupRes['success']);

        // Corromper intencionadamente database.sqlite dentro del ZIP para invalidar su hash SHA-256
        $zip = new ZipArchive();
        $zip->open($backupRes['path']);
        $zip->addFromString('database.sqlite', 'CORRUPTED_DATA_NOT_SQLITE');
        $zip->close();

        $validation = $manager->validateBackupArchive($backupRes['filename']);
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('SHA-256', (string) $validation['error']);

        $restoreRes = $manager->restoreBackup($backupRes['filename']);
        $this->assertFalse($restoreRes['success']);

        $auditModel = new AuditLogModel();
        $this->assertNotNull($auditModel->where('action', 'backup_restore_failed')->first());
    }

    public function testConfigurableRetentionPrunesOldestBackups(): void
    {
        $manager = new BackupManager(null, $this->testBackupDir);
        $manager->setRetentionCount(2);
        $this->assertSame(2, $manager->getRetentionCount());

        // Crear 4 copias de seguridad (createBackup aplica automáticamente la retención de 2)
        for ($i = 0; $i < 4; $i++) {
            $res = $manager->createBackup();
            $this->assertTrue($res['success']);
            usleep(1000);
        }

        $remaining = $manager->listBackups();
        $this->assertCount(2, $remaining);
    }

    public function testBlocksPathTraversalInBackupOperations(): void
    {
        $manager = new BackupManager(null, $this->testBackupDir);

        $badFilenames = [
            '../../etc/passwd',
            '../database/database.sqlite',
            'backup_../../evil.zip',
            'not_a_backup.zip',
            'backup_evil.sh',
        ];

        foreach ($badFilenames as $bad) {
            try {
                $manager->resolveSafeBackupPath($bad);
                $this->fail("El nombre malicioso no fue bloqueado: {$bad}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Acceso denegado', $e->getMessage());
            }
        }
    }

    public function testControllerUpdateRetentionSettingsFlow(): void
    {
        $postData = $this->withCsrf([
            'backup_retention_count' => '15',
        ]);

        $result = $this->withSession($this->adminSession)->post('admin/backups/retention', $postData);
        $result->assertRedirectTo(base_url('admin?tab=backups'));

        $manager = new BackupManager(null, $this->testBackupDir);
        $this->assertSame(15, $manager->getRetentionCount());
    }

    public function testRestoreBackupRestoresProxyUpstreamsAndIgnoresUnknownColumns(): void
    {
        $proxyModel = new ProxyHostModel();
        $certModel  = new CertificateModel();
        $db         = \Config\Database::connect();

        $certId = $certModel->insert([
            'domain'           => 'lb.example.com',
            'certificate_path' => '/etc/letsencrypt/live/lb.example.com/fullchain.pem',
            'private_key_path' => '/etc/letsencrypt/live/lb.example.com/privkey.pem',
            'issuer'           => "Let's Encrypt",
            'status'           => 'valid',
        ], true);

        $proxyId = $proxyModel->insert([
            'domain'                 => 'lb.example.com',
            'scheme'                 => 'http',
            'forward_host'           => '10.0.0.10',
            'forward_port'           => 8080,
            'ssl_enabled'            => 1,
            'certificate_id'         => $certId,
            'enabled'                => 1,
            'load_balancing_enabled' => 1,
            'lb_algorithm'           => 'round_robin',
        ], true);

        $db->table('proxy_upstreams')->insert([
            'proxy_host_id' => $proxyId,
            'server_host'   => '10.0.0.11',
            'server_port'   => 8081,
            'weight'        => 2,
            'max_fails'     => 3,
            'fail_timeout'  => 10,
            'is_backup'     => 0,
            'is_down'       => 0,
        ]);

        $manager   = new BackupManager(null, $this->testBackupDir);
        $backupRes = $manager->createBackup();
        $this->assertTrue($backupRes['success']);

        // Añadir una columna extra en el SQLite del backup para verificar que restoreBackup filtra columnas desconocidas
        $zipPath = $manager->resolveSafeBackupPath($backupRes['filename']);
        $zip     = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);

        $tmpSqlite = tempnam(sys_get_temp_dir(), 'bkp_extra_col_');
        file_put_contents($tmpSqlite, $zip->getFromName('database.sqlite'));
        $sqlite = new \SQLite3($tmpSqlite);
        $prefixedTable = $db->prefixTable('proxy_hosts');
        $sqlite->exec("ALTER TABLE {$prefixedTable} ADD COLUMN legacy_extra_col TEXT DEFAULT NULL");
        $sqlite->exec("UPDATE {$prefixedTable} SET legacy_extra_col = 'legacy'");
        $sqlite->close();

        $newDbBytes = file_get_contents($tmpSqlite);
        @unlink($tmpSqlite);
        $zip->deleteName('database.sqlite');
        $zip->addFromString('database.sqlite', $newDbBytes);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $manifest['sha256_database'] = hash('sha256', $newDbBytes);
        $zip->deleteName('manifest.json');
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();

        // Borrar datos actuales y restaurar
        $db->table('proxy_upstreams')->emptyTable();
        $db->table('proxy_hosts')->emptyTable();
        $db->table('certificates')->emptyTable();

        $restoreRes = $manager->restoreBackup($backupRes['filename']);
        $this->assertTrue($restoreRes['success'], $restoreRes['error'] ?? '');

        $restoredUpstreams = $db->table('proxy_upstreams')->where('proxy_host_id', $proxyId)->get()->getResultArray();
        $this->assertCount(1, $restoredUpstreams);
        $this->assertSame('10.0.0.11', $restoredUpstreams[0]['server_host']);
    }
}
