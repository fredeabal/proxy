<?php

namespace Tests\Unit;

use App\Models\AuditLogModel;
use App\Services\MaintenanceManager;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Pruebas unitarias y de integración para el Panel de Mantenimiento (Sesiones, Caché, Logs y BD).
 *
 * @internal
 */
final class MaintenanceManagerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $seed      = 'App\Database\Seeds\AdminSeeder';

    protected string $testDir;
    protected string $testSessions;
    protected string $testCache;
    protected string $testLogs;
    protected string $testDebugbar;

    protected array $adminSession = [
        'is_logged_in' => true,
        'user_id'      => 1,
        'user_name'    => 'Administrador',
        'user_email'   => 'admin@demo.com',
        'user_role'    => 'admin',
    ];

    protected function withCsrf(array $data = []): array
    {
        $security = service('security');
        $data[$security->getTokenName()] = $security->getHash();
        return $data;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = WRITEPATH . 'test_maintenance_' . bin2hex(random_bytes(4));
        $this->testSessions = $this->testDir . '/session';
        $this->testCache    = $this->testDir . '/cache';
        $this->testLogs     = $this->testDir . '/logs';
        $this->testDebugbar = $this->testDir . '/debugbar';

        @mkdir($this->testSessions, 0777, true);
        @mkdir($this->testCache, 0777, true);
        @mkdir($this->testLogs, 0777, true);
        @mkdir($this->testDebugbar, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testGetOverviewStatsReturnsExpectedStructure(): void
    {
        $manager = new MaintenanceManager($this->testSessions, $this->testCache, $this->testLogs, $this->testDebugbar);
        $stats = $manager->getOverviewStats();

        $this->assertArrayHasKey('sessions', $stats);
        $this->assertArrayHasKey('cache', $stats);
        $this->assertArrayHasKey('logs', $stats);
        $this->assertArrayHasKey('storage', $stats);

        $this->assertSame(0, $stats['sessions']['total_files']);
        $this->assertSame(0, $stats['cache']['total_files']);
        $this->assertSame(0, $stats['logs']['ci_files_count']);
    }

    public function testSessionStatsAndClearing(): void
    {
        $manager = new MaintenanceManager($this->testSessions, $this->testCache, $this->testLogs, $this->testDebugbar);

        // Crear una sesión activa (reciente) y una expirada (hace 3 horas)
        $expiredFile = $this->testSessions . '/ci_session_expired123';
        file_put_contents($expiredFile, 'session_data_expired');
        touch($expiredFile, time() - 10000);

        $activeFile = $this->testSessions . '/ci_session_active456';
        file_put_contents($activeFile, 'session_data_active');
        touch($activeFile, time());

        $stats = $manager->getSessionStats();
        $this->assertSame(2, $stats['total_files']);
        $this->assertSame(1, $stats['expired_count']);
        $this->assertSame(1, $stats['active_count']);

        // Limpiar solo expiradas
        $cleanExpired = $manager->clearSessions(true, false);
        $this->assertSame(1, $cleanExpired['deleted']);
        $this->assertFileDoesNotExist($expiredFile);
        $this->assertFileExists($activeFile);

        // Limpiar todas
        $cleanAll = $manager->clearSessions(false, false);
        $this->assertSame(1, $cleanAll['deleted']);
        $this->assertFileDoesNotExist($activeFile);
    }

    public function testCacheClearingPreservesSystemFiles(): void
    {
        $manager = new MaintenanceManager($this->testSessions, $this->testCache, $this->testLogs, $this->testDebugbar);

        $cacheItem = $this->testCache . '/throttler_login_test';
        $indexHtml = $this->testCache . '/index.html';
        file_put_contents($cacheItem, 'throttler_data');
        file_put_contents($indexHtml, '<html></html>');

        $stats = $manager->getCacheStats();
        $this->assertSame(1, $stats['total_files']);
        $this->assertSame(1, $stats['throttler_files']);

        $result = $manager->clearCache();
        $this->assertSame(1, $result['deleted_files']);
        $this->assertFileDoesNotExist($cacheItem);
        $this->assertFileExists($indexHtml);
    }

    public function testLogsClearingCanKeepTodayFile(): void
    {
        $manager = new MaintenanceManager($this->testSessions, $this->testCache, $this->testLogs, $this->testDebugbar);

        $todayFile = $this->testLogs . '/log-' . date('Y-m-d') . '.log';
        $oldFile   = $this->testLogs . '/log-2026-01-01.log';
        file_put_contents($todayFile, 'today log content');
        file_put_contents($oldFile, 'old log content');

        $stats = $manager->getLogsStats();
        $this->assertSame(2, $stats['ci_files_count']);

        // Purgar conservando hoy
        $result = $manager->clearAppLogs(true);
        $this->assertSame(1, $result['deleted_files']);
        $this->assertTrue($result['kept_today']);
        $this->assertFileExists($todayFile);
        $this->assertFileDoesNotExist($oldFile);

        // Purgar todos los logs
        $resultAll = $manager->clearAppLogs(false);
        $this->assertSame(1, $resultAll['deleted_files']);
        $this->assertFileDoesNotExist($todayFile);
    }

    public function testDatabaseOptimizationRunsSuccessfully(): void
    {
        $manager = new MaintenanceManager($this->testSessions, $this->testCache, $this->testLogs, $this->testDebugbar);
        $result = $manager->optimizeDatabase();

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['freed_human']);
    }

    public function testAuthenticatedAdminCanAccessMaintenanceTab(): void
    {
        $result = $this->withSession($this->adminSession)->get('admin', ['tab' => 'maintenance']);

        $result->assertStatus(200);
        $result->assertSee('Mantenimiento');
        $result->assertSee('Sesiones');
        $result->assertSee('Caché');
        $result->assertSee('Logs');
        $result->assertSee('Limpiar Todo');
    }

    public function testMaintenanceRoutesRequireAdminRole(): void
    {
        // Un usuario normal no debe poder ejecutar endpoints de mantenimiento
        $normalUserSession = [
            'is_logged_in' => true,
            'user_id'      => 2,
            'user_name'    => 'Usuario Regular',
            'user_email'   => 'user@demo.com',
            'user_role'    => 'user',
        ];

        $postCache = $this->withSession($normalUserSession)->post('admin/maintenance/cache', $this->withCsrf([]));
        $postCache->assertRedirectTo('dashboard');
    }

    public function testAdminCanTriggerMaintenancePostActions(): void
    {
        $postCache = $this->withSession($this->adminSession)->post('admin/maintenance/cache', $this->withCsrf([]));
        $postCache->assertRedirectTo(base_url('admin?tab=maintenance'));
        $postCache->assertSessionHas('success');

        $postSessions = $this->withSession($this->adminSession)->post('admin/maintenance/sessions', $this->withCsrf([
            'mode' => 'expired',
        ]));
        $postSessions->assertRedirectTo(base_url('admin?tab=maintenance'));
        $postSessions->assertSessionHas('success');

        $postLogs = $this->withSession($this->adminSession)->post('admin/maintenance/logs', $this->withCsrf([
            'keep_today' => '1',
        ]));
        $postLogs->assertRedirectTo(base_url('admin?tab=maintenance'));
        $postLogs->assertSessionHas('success');

        $postOptimize = $this->withSession($this->adminSession)->post('admin/maintenance/optimize-db', $this->withCsrf([]));
        $postOptimize->assertRedirectTo(base_url('admin?tab=maintenance'));
        $postOptimize->assertSessionHas('success');
    }
}
