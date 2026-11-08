<?php

namespace Tests\Unit;

use App\Models\AuditLogModel;
use App\Services\SystemMonitor;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use InvalidArgumentException;

/**
 * Pruebas unitarias y de integración para la Fase 7: Logs y Monitorización.
 *
 * @internal
 */
final class LogMonitorTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $seed      = 'App\Database\Seeds\AdminSeeder';

    protected array $adminSession = [
        'is_logged_in' => true,
        'user_id'      => 1,
        'user_name'    => 'Administrador',
        'user_email'   => 'admin@demo.com',
        'user_role'    => 'admin',
    ];

    public function testGuestCannotAccessLogs(): void
    {
        $result = $this->withSession([])->get('logs');
        $result->assertRedirectTo('/login');
    }

    public function testAuthenticatedAdminCanViewLogsAndMonitoringPage(): void
    {
        $redirect = $this->withSession($this->adminSession)->get('logs');
        $redirect->assertRedirectTo(base_url('admin?tab=logs'));

        $result = $this->withSession($this->adminSession)->get('admin', ['tab' => 'logs']);
        $result->assertStatus(200);
        $result->assertSee('Logs Nginx');
        $result->assertSee('access.log');
    }

    public function testSystemMonitorMetricsAndUptime(): void
    {
        $monitor = new SystemMonitor();

        $nginx = $monitor->getNginxStatus();
        $this->assertArrayHasKey('active', $nginx);
        $this->assertArrayHasKey('status', $nginx);

        $cpu = $monitor->getCpuLoad();
        $this->assertArrayHasKey('1m', $cpu);
        $this->assertArrayHasKey('5m', $cpu);
        $this->assertArrayHasKey('15m', $cpu);

        $mem = $monitor->getMemoryUsage();
        $this->assertArrayHasKey('percent', $mem);
        $this->assertGreaterThan(0, $mem['total_mb']);

        $disk = $monitor->getDiskUsage();
        $this->assertArrayHasKey('percent', $disk);
        $this->assertGreaterThan(0, $disk['total_gb']);

        $uptime = $monitor->getUptime();
        $this->assertGreaterThan(0, $uptime['seconds']);
        $this->assertNotEmpty($uptime['formatted']);
    }

    public function testReadNginxLogFiltersByHostDomain(): void
    {
        $tempDir = WRITEPATH . 'logs/test_nginx_' . uniqid();
        @mkdir($tempDir, 0755, true);

        file_put_contents($tempDir . '/access.log', implode("\n", [
            '10.0.0.1 - - [08/Nov/2026:18:00:00 +0100] "GET / HTTP/2.0" 200 512 "-" "Mozilla" host="app.empresa.com"',
            '10.0.0.2 - - [08/Nov/2026:18:01:00 +0100] "GET /v1 HTTP/2.0" 200 128 "-" "Mozilla" host="api.empresa.com"',
        ]));

        $monitor = new SystemMonitor(null, $tempDir);

        $all = $monitor->readNginxLog('access');
        $this->assertCount(2, $all['lines']);
        $this->assertFalse($all['simulated']);

        $filtered = $monitor->readNginxLog('access', 'api.empresa.com');
        $this->assertCount(1, $filtered['lines']);
        $this->assertStringContainsString('api.empresa.com', $filtered['lines'][0]);

        @unlink($tempDir . '/access.log');
        @rmdir($tempDir);
    }

    public function testBlocksPathTraversalPayloadsInLogViewer(): void
    {
        $monitor = new SystemMonitor();

        $traversalPayloads = [
            '../../etc/passwd',
            '../database/database.sqlite',
            '/var/log/../../etc/shadow',
            '..\\..\\windows\\win.ini',
            'access.log/../../etc/passwd',
            'config.php',
            "access.log\0.txt",
        ];

        foreach ($traversalPayloads as $payload) {
            try {
                $monitor->resolveSafeLogPath($payload);
                $this->fail("El payload de Path Traversal no fue bloqueado: {$payload}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Acceso denegado', $e->getMessage());
            }
        }
    }

    public function testControllerNginxLogEndpointAndPathTraversalRejection(): void
    {
        $ok = $this->withSession($this->adminSession)->get('logs/nginx', [
            'type'   => 'access',
            'domain' => 'panel.empresa.com',
        ]);
        $ok->assertStatus(200);
        $json = json_decode($ok->response()->getBody(), true);
        $this->assertTrue($json['success']);
        $this->assertSame('access.log', $json['file']);

        // Intento de Path Traversal mediante parámetro file
        $bad = $this->withSession($this->adminSession)->get('logs/nginx', [
            'type' => 'access',
            'file' => '../../etc/passwd',
        ]);
        $bad->assertStatus(400);
        $badJson = json_decode($bad->response()->getBody(), true);
        $this->assertFalse($badJson['success']);
    }
}
