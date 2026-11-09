<?php

namespace Tests\Unit;

use App\Models\AuditLogModel;
use App\Models\ProxyHostModel;
use App\Services\NginxConfigGenerator;
use App\Services\NginxManager;
use App\Services\NginxValidator;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Nginx as NginxConfig;
use InvalidArgumentException;

/**
 * Pruebas unitarias y de integración para la Fase 4: Integración con Nginx y Ejecutor Privilegiado.
 * 
 * @internal
 */
final class NginxIntegrationTest extends CIUnitTestCase
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

    protected function withCsrf(array $data): array
    {
        $security = service('security');
        $data[$security->getTokenName()] = $security->getHash();
        return $data;
    }

    public function testNginxConfigGeneratorHttpOnly(): void
    {
        $generator = new NginxConfigGenerator();
        $proxy = [
            'id'                => 10,
            'domain'            => 'app.test.com',
            'domain_names'      => 'www.app.test.com',
            'scheme'            => 'http',
            'forward_host'      => '127.0.0.1',
            'forward_port'      => 3000,
            'ssl_enabled'       => 0,
            'websocket_enabled' => 0,
            'force_https'       => 0,
            'custom_headers'    => '',
        ];

        $config = $generator->generate($proxy);

        $this->assertStringContainsString('listen 80;', $config);
        $this->assertStringContainsString('server_name app.test.com www.app.test.com;', $config);
        $this->assertStringContainsString('proxy_pass http://127.0.0.1:3000;', $config);
        $this->assertStringContainsString('location ^~ /.well-known/acme-challenge/', $config);
        $this->assertStringContainsString('error_page 502 /proxy_error_502.html;', $config);
        $this->assertStringContainsString('error_page 503 /proxy_error_503.html;', $config);
        $this->assertStringContainsString('error_page 504 /proxy_error_504.html;', $config);
        $this->assertStringNotContainsString('listen 443', $config);
    }

    public function testNginxConfigGeneratorHttpsWithForceRedirect(): void
    {
        $generator = new NginxConfigGenerator();
        $proxy = [
            'id'                => 20,
            'domain'            => 'seguro.test.com',
            'domain_names'      => '',
            'scheme'            => 'https',
            'forward_host'      => '10.0.0.5',
            'forward_port'      => 8443,
            'ssl_enabled'       => 1,
            'websocket_enabled' => 1,
            'force_https'       => 1,
            'custom_headers'    => "X-Frame-Options: SAMEORIGIN\nX-XSS-Protection: 1; mode=block",
        ];

        $config = $generator->generate($proxy);

        // Bloque 80 con redirección a HTTPS
        $this->assertStringContainsString('listen 80;', $config);
        $this->assertStringContainsString('return 301 https://$host$request_uri;', $config);

        // Bloque 443
        $this->assertStringContainsString('listen 443 ssl;', $config);
        $this->assertStringContainsString('ssl_certificate /etc/letsencrypt/live/seguro.test.com/fullchain.pem;', $config);
        $this->assertStringContainsString('ssl_certificate_key /etc/letsencrypt/live/seguro.test.com/privkey.pem;', $config);
        $this->assertStringContainsString('proxy_pass https://10.0.0.5:8443;', $config);

        // WebSockets
        $this->assertStringContainsString('proxy_set_header Upgrade $http_upgrade;', $config);
        $this->assertStringContainsString('proxy_set_header Connection "upgrade";', $config);

        // Custom headers
        $this->assertStringContainsString('add_header X-Frame-Options "SAMEORIGIN" always;', $config);
    }

    public function testNginxConfigGeneratorRejectsMaliciousDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $generator = new NginxConfigGenerator();
        $proxy = [
            'id'                => 1,
            'domain'            => "malo.com;\nreturn 500;",
            'domain_names'      => '',
            'scheme'            => 'http',
            'forward_host'      => '127.0.0.1',
            'forward_port'      => 80,
            'ssl_enabled'       => 0,
            'websocket_enabled' => 0,
            'force_https'       => 0,
        ];
        $generator->generate($proxy);
    }

    public function testNginxValidatorCatchesBraceMismatch(): void
    {
        $validator = new NginxValidator();
        $badConfig = "server { listen 80; proxy_pass http://127.0.0.1:80; ";
        $result = $validator->validateStructure($badConfig);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Desbalance de llaves', $result['error']);
    }

    public function testNginxValidatorCatchesBannedDirectives(): void
    {
        $validator = new NginxValidator();
        $badConfig = "server { listen 80; proxy_pass http://127.0.0.1:80; lua_code_cache off; }";
        $result = $validator->validateStructure($badConfig);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Directiva prohibida', $result['error']);
    }

    public function testNginxManagerAppliesHostSuccessfully(): void
    {
        $manager = new NginxManager();
        $proxy = [
            'id'                => 99,
            'domain'            => 'prueba-manager.com',
            'domain_names'      => 'alias.prueba-manager.com',
            'scheme'            => 'http',
            'forward_host'      => '127.0.0.1',
            'forward_port'      => 8080,
            'ssl_enabled'       => 0,
            'websocket_enabled' => 0,
            'force_https'       => 0,
            'enabled'           => 1,
            'custom_headers'    => '',
        ];

        $result = $manager->applyHost($proxy);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['config']);
        $this->assertStringContainsString('prueba-manager.com', $result['config']);
    }

    public function testNginxManagerDisableAndRemoveHost(): void
    {
        $manager = new NginxManager();
        $disableRes = $manager->disableHost(99);
        $this->assertTrue($disableRes['success']);

        $removeRes = $manager->removeHost(99);
        $this->assertTrue($removeRes['success']);
    }

    public function testControllerPreviewEndpoint(): void
    {
        $model = new ProxyHostModel();
        $id = $model->insert([
            'domain'            => 'preview-test.com',
            'domain_names'      => 'alias-preview.com',
            'scheme'            => 'http',
            'forward_host'      => '127.0.0.1',
            'forward_port'      => 8000,
            'ssl_enabled'       => 0,
            'websocket_enabled' => 1,
            'force_https'       => 0,
            'enabled'           => 1,
            'custom_headers'    => '',
        ]);

        $result = $this->withSession($this->adminSession)->get("proxy-hosts/preview/{$id}");
        $result->assertStatus(200);

        $json = json_decode($result->response()->getBody(), true);
        $this->assertTrue($json['success']);
        $this->assertSame('preview-test.com', $json['domain']);
        $this->assertStringContainsString('preview-test.com', $json['config']);
        $this->assertStringContainsString('proxy_set_header Upgrade $http_upgrade;', $json['config']);
    }
}

