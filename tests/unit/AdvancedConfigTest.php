<?php

namespace Tests\Unit;

use App\Models\ProxyHostModel;
use App\Services\NginxConfigGenerator;
use App\Validation\ProxyHostRules;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use InvalidArgumentException;

/**
 * Pruebas unitarias y de integración para la Fase 6: Configuración avanzada y Headers.
 *
 * @internal
 */
final class AdvancedConfigTest extends CIUnitTestCase
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

    public function testCreateProxyWithAdvancedOptionsAndWhitelistedHeaders(): void
    {
        $postData = $this->withCsrf([
            'domain'                => 'avanzado.empresa.com',
            'scheme'                => 'https',
            'forward_host'          => '10.0.0.20',
            'forward_port'          => '8443',
            'ssl_enabled'           => '1',
            'websocket_enabled'     => '1',
            'force_https'           => '1',
            'enabled'               => '1',
            'proxy_connect_timeout' => '120',
            'proxy_read_timeout'    => '300',
            'proxy_send_timeout'    => '180',
            'client_max_body_size'  => '50',
            'proxy_ssl_verify'      => '1',
            'hsts_enabled'          => '1',
            'custom_headers'        => "x-frame-options: DENY\nX-Content-Type-Options: nosniff\nX-Forwarded-For: \$proxy_add_x_forwarded_for",
        ]);

        $result = $this->withSession($this->adminSession)->post('proxy-hosts/create', $postData);
        $result->assertRedirectTo(base_url('proxy-hosts'));

        $model = new ProxyHostModel();
        $saved = $model->where('domain', 'avanzado.empresa.com')->first();
        $this->assertNotNull($saved);
        $this->assertSame(120, (int) $saved['proxy_connect_timeout']);
        $this->assertSame(300, (int) $saved['proxy_read_timeout']);
        $this->assertSame(180, (int) $saved['proxy_send_timeout']);
        $this->assertSame(50, (int) $saved['client_max_body_size']);
        $this->assertSame(1, (int) $saved['proxy_ssl_verify']);
        $this->assertSame(1, (int) $saved['hsts_enabled']);
        // Nombre normalizado según lista blanca
        $this->assertStringContainsString('X-Frame-Options: DENY', (string) $saved['custom_headers']);

        // Verificar generación de directivas Nginx
        $generator = new NginxConfigGenerator();
        $config = $generator->generate($saved);

        $this->assertStringContainsString('client_max_body_size 50m;', $config);
        $this->assertStringContainsString('proxy_connect_timeout 120s;', $config);
        $this->assertStringContainsString('proxy_read_timeout 300s;', $config);
        $this->assertStringContainsString('proxy_send_timeout 180s;', $config);
        $this->assertStringContainsString('proxy_ssl_server_name on;', $config);
        $this->assertStringContainsString('proxy_ssl_verify on;', $config);
        $this->assertStringContainsString('proxy_set_header Upgrade $http_upgrade;', $config);
        $this->assertStringContainsString('proxy_set_header Connection "upgrade";', $config);
        $this->assertStringContainsString('add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;', $config);
        $this->assertStringContainsString('add_header X-Frame-Options "DENY" always;', $config);
        $this->assertStringContainsString('add_header X-Content-Type-Options "nosniff" always;', $config);
        $this->assertStringContainsString('proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;', $config);
    }

    public function testClientMaxBodySizeZeroMeansUnlimited(): void
    {
        $generator = new NginxConfigGenerator();
        $config = $generator->generate([
            'id'                   => 55,
            'domain'               => 'unlimited.empresa.com',
            'scheme'               => 'https',
            'forward_host'         => '127.0.0.1',
            'forward_port'         => 8443,
            'client_max_body_size' => 0,
            'proxy_ssl_verify'     => 0,
        ]);

        $this->assertStringContainsString('client_max_body_size 0;', $config);
        $this->assertStringContainsString('proxy_ssl_verify off;', $config);
    }

    public function testRejectsNonWhitelistedHeaders(): void
    {
        $postData = $this->withCsrf([
            'domain'         => 'badheader.empresa.com',
            'scheme'         => 'http',
            'forward_host'   => '127.0.0.1',
            'forward_port'   => '8080',
            'custom_headers' => "X-Evil-Backdoor: activo\nSet-Cookie: admin=1",
        ]);

        $result = $this->withSession($this->adminSession)->post('proxy-hosts/create', $postData);
        $result->assertSessionHas('errors');

        $model = new ProxyHostModel();
        $this->assertNull($model->where('domain', 'badheader.empresa.com')->first());
    }

    public function testProhibitsArbitraryNginxDirectivesAndBlocksInHeaders(): void
    {
        $maliciousPayloads = [
            "proxy_pass http://evil.internal:9000;",
            "location /backdoor { return 200 'hacked'; }",
            "X-Frame-Options: SAMEORIGIN\"; return 200;",
            "X-Frame-Options: SAMEORIGIN; return 301 https://evil.com",
            "X-Frame-Options: SAMEORIGIN\ninclude /etc/passwd;",
            "X-Frame-Options: \$ssl_client_raw_cert",
            "lua_code_cache: off",
        ];

        $rules = new ProxyHostRules();

        foreach ($maliciousPayloads as $payload) {
            $error = null;
            $isValid = $rules->valid_custom_headers($payload, $error);
            $this->assertFalse($isValid, "El payload malicioso no fue bloqueado: {$payload}");
            $this->assertNotEmpty($error);
        }
    }

    public function testGeneratorThrowsOnDirectDirectiveInjectionInHeaders(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $generator = new NginxConfigGenerator();
        $generator->generate([
            'id'             => 1,
            'domain'         => 'seguro.com',
            'scheme'         => 'http',
            'forward_host'   => '127.0.0.1',
            'forward_port'   => 8080,
            'custom_headers' => "X-Not-In-Whitelist: 123",
        ]);
    }

    public function testRejectsOutOfRangeTimeouts(): void
    {
        $postData = $this->withCsrf([
            'domain'                => 'timeout-invalido.com',
            'scheme'                => 'http',
            'forward_host'          => '127.0.0.1',
            'forward_port'          => '8080',
            'proxy_connect_timeout' => '99999', // Mayor a 3600s
        ]);

        $result = $this->withSession($this->adminSession)->post('proxy-hosts/create', $postData);
        $result->assertSessionHas('errors');
    }
}

