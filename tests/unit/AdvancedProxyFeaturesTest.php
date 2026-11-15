<?php

namespace Tests\Unit;

use App\Models\AccessListModel;
use App\Models\ProxyHostModel;
use App\Models\ProxyLocationModel;
use App\Models\StreamModel;
use App\Services\NginxConfigGenerator;
use App\Services\NginxManager;
use App\Services\NginxValidator;
use App\Services\SystemMonitor;
use App\Validation\ProxyHostRules;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Nginx as NginxConfig;
use InvalidArgumentException;

class AdvancedProxyFeaturesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected NginxConfig $nginxConfig;
    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = WRITEPATH . 'test_adv_' . uniqid() . '/';
        @mkdir($this->testDir . 'sites-available', 0755, true);
        @mkdir($this->testDir . 'sites-enabled', 0755, true);
        @mkdir($this->testDir . 'streams-available', 0755, true);
        @mkdir($this->testDir . 'streams-enabled', 0755, true);
        @mkdir($this->testDir . 'access_lists', 0755, true);
        @mkdir($this->testDir . 'staging', 0755, true);
        @mkdir($this->testDir . 'logs', 0755, true);

        $this->nginxConfig = new NginxConfig();
        $this->nginxConfig->sitesAvailable   = $this->testDir . 'sites-available';
        $this->nginxConfig->sitesEnabled     = $this->testDir . 'sites-enabled';
        $this->nginxConfig->streamsAvailable = $this->testDir . 'streams-available';
        $this->nginxConfig->streamsEnabled   = $this->testDir . 'streams-enabled';
        $this->nginxConfig->accessListsPath  = sys_get_temp_dir() . '/rpm_test_al_' . uniqid();
        @mkdir($this->nginxConfig->accessListsPath, 0755, true);
        $this->nginxConfig->stagingPath      = $this->testDir . 'staging';
        $this->nginxConfig->logsPath         = $this->testDir . 'logs';
        $this->nginxConfig->simulateInDev    = true;
        $this->nginxConfig->executorBinary   = '/non/existent/proxy-executor';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        $this->removeDirectory($this->nginxConfig->accessListsPath);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    // -------------------------------------------------------------------------
    // 1. Rate Limiting
    // -------------------------------------------------------------------------
    public function testRateLimitingGeneratesZoneAndDirectives(): void
    {
        $generator = new NginxConfigGenerator($this->nginxConfig);
        $config = $generator->generate([
            'id'                  => 12,
            'domain'              => 'api.example.com',
            'scheme'              => 'http',
            'forward_host'        => '127.0.0.1',
            'forward_port'        => 3000,
            'rate_limit_enabled'  => 1,
            'rate_limit_requests' => 25,
            'rate_limit_unit'     => 's',
            'rate_limit_burst'    => 50,
            'rate_limit_nodelay'  => 1,
        ]);

        $this->assertStringContainsString('limit_req_zone $binary_remote_addr zone=rate_limit_proxy_12:10m rate=25r/s;', $config);
        $this->assertStringContainsString('limit_req zone=rate_limit_proxy_12 burst=50 nodelay;', $config);
        $this->assertStringContainsString('limit_req_status 429;', $config);
    }

    // -------------------------------------------------------------------------
    // 2. Access Lists y Auth Basic
    // -------------------------------------------------------------------------
    public function testAccessListModelAndConfigGeneration(): void
    {
        $alModel = new AccessListModel();
        $alId = $alModel->insert([
            'name'        => 'Admin Internal',
            'satisfy_any' => 1,
            'pass_auth'   => 0,
        ]);
        $this->assertIsInt($alId);

        $alModel->replaceClients($alId, [
            ['directive' => 'allow', 'address' => '192.168.1.0/24'],
            ['directive' => 'allow', 'address' => '10.0.0.5'],
        ]);

        $hash = AccessListModel::hashHtpasswd('SuperSecret123');
        $this->assertStringStartsWith('{SHA}', $hash);

        $alModel->replaceAuthUsers($alId, [
            ['username' => 'admin', 'password_hash' => $hash],
        ]);

        $htpasswd = $alModel->buildHtpasswdContent($alId);
        $this->assertSame("admin:{$hash}\n", $htpasswd);

        $manager = new NginxManager($this->nginxConfig);
        $syncRes = $manager->syncAccessList($alId, $htpasswd);
        $this->assertTrue($syncRes['success']);
        $this->assertFileExists($this->nginxConfig->accessListsPath . "/access_list_{$alId}.htpasswd");

        $generator = new NginxConfigGenerator($this->nginxConfig);
        $config = $generator->generate([
            'id'             => 15,
            'domain'         => 'panel.example.com',
            'scheme'         => 'http',
            'forward_host'   => '127.0.0.1',
            'forward_port'   => 8080,
            'access_list_id' => $alId,
        ]);

        $this->assertStringContainsString('satisfy any;', $config);
        $this->assertStringContainsString('allow 192.168.1.0/24;', $config);
        $this->assertStringContainsString('allow 10.0.0.5;', $config);
        $this->assertStringContainsString('deny all;', $config);
        $this->assertStringContainsString('auth_basic "Admin Internal";', $config);
        $this->assertStringContainsString("auth_basic_user_file {$this->nginxConfig->accessListsPath}/access_list_{$alId}.htpasswd;", $config);
    }

    public function testCidrValidation(): void
    {
        $this->assertTrue(ProxyHostRules::validCidrOrIp('192.168.0.0/16'));
        $this->assertTrue(ProxyHostRules::validCidrOrIp('2001:db8::/32'));
        $this->assertTrue(ProxyHostRules::validCidrOrIp('all'));
        $this->assertFalse(ProxyHostRules::validCidrOrIp('192.168.1.1/33'));
        $this->assertFalse(ProxyHostRules::validCidrOrIp('999.999.999.999'));
    }

    // -------------------------------------------------------------------------
    // 3. Múltiples bloques location por host
    // -------------------------------------------------------------------------
    public function testMultipleCustomLocationsGenerationAndValidation(): void
    {
        $proxyModel = new ProxyHostModel();
        $proxyId = $proxyModel->insert([
            'domain'       => 'app.example.com',
            'scheme'       => 'http',
            'forward_host' => '127.0.0.1',
            'forward_port' => 8080,
        ]);

        $locModel = new ProxyLocationModel();
        $locModel->replaceForProxy($proxyId, [
            [
                'path'              => '/api',
                'scheme'            => 'http',
                'forward_host'      => '10.0.0.10',
                'forward_port'      => 3000,
                'websocket_enabled' => 1,
            ],
            [
                'path'              => '^~ /static',
                'scheme'            => 'http',
                'forward_host'      => '10.0.0.11',
                'forward_port'      => 9000,
                'websocket_enabled' => 0,
            ],
        ]);

        $generator = new NginxConfigGenerator($this->nginxConfig);
        $config = $generator->generate($proxyModel->find($proxyId));

        $this->assertStringContainsString('location /api {', $config);
        $this->assertStringContainsString('proxy_pass http://10.0.0.10:3000;', $config);
        $this->assertStringContainsString('location ^~ /static {', $config);
        $this->assertStringContainsString('proxy_pass http://10.0.0.11:9000;', $config);
        $this->assertStringContainsString('location / {', $config);

        // Validar seguridad de rutas
        $this->assertFalse(ProxyHostRules::validLocationPath('/'));
        $this->assertFalse(ProxyHostRules::validLocationPath('/api; evil_directive;'));
        $this->assertFalse(ProxyHostRules::validLocationPath('/../etc/passwd'));
        $this->assertTrue(ProxyHostRules::validLocationPath('/api/v1'));
    }

    // -------------------------------------------------------------------------
    // 4. Redirection Hosts y Streams TCP/UDP
    // -------------------------------------------------------------------------
    public function testRedirectionHostGeneratesReturnWithoutProxyPass(): void
    {
        $generator = new NginxConfigGenerator($this->nginxConfig);
        $config = $generator->generate([
            'id'            => 22,
            'domain'        => 'old.example.com',
            'host_type'     => 'redirect',
            'redirect_url'  => 'https://new.example.com',
            'redirect_code' => 302,
            'preserve_path' => 1,
        ]);

        $this->assertStringContainsString('return 302 https://new.example.com$request_uri;', $config);
        $this->assertStringNotContainsString('proxy_pass', $config);

        $validator = new NginxValidator($this->nginxConfig);
        $struct = $validator->validateStructure($config);
        $this->assertTrue($struct['valid'], $struct['error'] ?? '');

        // Rechazar inyección en redirect_url
        $rules = new ProxyHostRules();
        $this->assertFalse($rules->valid_redirect_url("https://evil.com;\nadd_header X-Test 1;"));
    }

    public function testStreamModelAndNginxGeneration(): void
    {
        $streamModel = new StreamModel();
        $streamId = $streamModel->insert([
            'incoming_port' => 5432,
            'forward_host'  => '10.0.0.25',
            'forward_port'  => 5432,
            'protocol'      => 'tcp_udp',
            'enabled'       => 1,
            'description'   => 'Postgres',
        ]);
        $this->assertIsInt($streamId);

        $stream = $streamModel->find($streamId);
        $manager = new NginxManager($this->nginxConfig);
        $res = $manager->applyStream($stream);
        $this->assertTrue($res['success'], $res['error'] ?? '');

        $this->assertStringContainsString('listen 5432;', $res['config']);
        $this->assertStringContainsString('listen 5432 udp;', $res['config']);
        $this->assertStringContainsString('proxy_pass 10.0.0.25:5432;', $res['config']);
        $this->assertFileExists($this->nginxConfig->streamsAvailable . "/{$streamId}.conf");
        $this->assertFileExists($this->nginxConfig->streamsEnabled . "/{$streamId}.conf");

        $manager->removeStream($streamId);
        $this->assertFileDoesNotExist($this->nginxConfig->streamsAvailable . "/{$streamId}.conf");
    }

    // -------------------------------------------------------------------------
    // 5. Logs dedicados por Proxy Host
    // -------------------------------------------------------------------------
    public function testDedicatedLogsGeneratedAndReadBySystemMonitor(): void
    {
        $proxyModel = new ProxyHostModel();
        $proxyId = $proxyModel->insert([
            'domain'       => 'logs.example.com',
            'scheme'       => 'http',
            'forward_host' => '127.0.0.1',
            'forward_port' => 8080,
        ]);

        $generator = new NginxConfigGenerator($this->nginxConfig);
        $config = $generator->generate($proxyModel->find($proxyId));

        $this->assertStringContainsString("access_log {$this->nginxConfig->logsPath}/proxy_host_{$proxyId}_access.log;", $config);
        $this->assertStringContainsString("error_log  {$this->nginxConfig->logsPath}/proxy_host_{$proxyId}_error.log;", $config);

        $dedicatedAccess = $this->nginxConfig->logsPath . "/proxy_host_{$proxyId}_access.log";
        file_put_contents($dedicatedAccess, "1.2.3.4 - - [15/Nov/2026:10:00:00 +0000] \"GET /health HTTP/1.1\" 200 2\n");

        $monitor = new SystemMonitor($this->nginxConfig);
        $result  = $monitor->readNginxLog('access', 'logs.example.com', 50);

        $this->assertFalse($result['simulated']);
        $this->assertSame("proxy_host_{$proxyId}_access.log", $result['file']);
        $this->assertCount(1, $result['lines']);
        $this->assertStringContainsString('/health', $result['lines'][0]);
    }
}
