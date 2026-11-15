<?php

namespace Tests\Unit;

use App\Models\ProxyHostModel;
use App\Services\NginxConfigGenerator;
use App\Services\NginxValidator;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use InvalidArgumentException;

/**
 * Pruebas unitarias y de integración para el Rate Limiting por dominio en Proxy Hosts.
 *
 * @internal
 */
final class ProxyRateLimitTest extends CIUnitTestCase
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

    public function testNginxGeneratorOmitsRateLimitWhenDisabled(): void
    {
        $generator = new NginxConfigGenerator();

        $config = $generator->generate([
            'id'                 => 10,
            'domain'             => 'sinlimite.empresa.com',
            'scheme'             => 'http',
            'forward_host'       => '10.0.0.10',
            'forward_port'       => 8080,
            'rate_limit_enabled' => 0,
        ]);

        $this->assertStringNotContainsString('limit_req_zone', $config);
        $this->assertStringNotContainsString('limit_req ', $config);
        $this->assertStringNotContainsString('limit_req_status', $config);
    }

    public function testNginxGeneratorRendersRateLimitForSingleProxyHost(): void
    {
        $generator = new NginxConfigGenerator();
        $validator = new NginxValidator();

        $config = $generator->generate([
            'id'                  => 15,
            'domain'              => 'api.empresa.com',
            'scheme'              => 'http',
            'forward_host'        => '10.0.0.15',
            'forward_port'        => 3000,
            'rate_limit_enabled'  => 1,
            'rate_limit_requests' => 25,
            'rate_limit_unit'     => 'r/s',
            'rate_limit_burst'    => 50,
            'rate_limit_nodelay'  => 1,
        ]);

        $this->assertStringContainsString('limit_req_zone $binary_remote_addr zone=rl_proxy_15:10m rate=25r/s;', $config);
        $this->assertStringContainsString('limit_req zone=rl_proxy_15 burst=50 nodelay;', $config);
        $this->assertStringContainsString('limit_req_status 429;', $config);

        // limit_req_zone debe aparecer antes del bloque server {
        $zonePos   = strpos($config, 'limit_req_zone');
        $serverPos = strpos($config, 'server {');
        $this->assertNotFalse($zonePos);
        $this->assertNotFalse($serverPos);
        $this->assertLessThan($serverPos, $zonePos);

        $struct = $validator->validateStructure($config);
        $this->assertTrue($struct['valid'], $struct['error'] ?? '');
    }

    public function testNginxGeneratorRendersRateLimitInRedirectAndLoadBalancerModes(): void
    {
        $generator = new NginxConfigGenerator();

        $redirectConfig = $generator->generate([
            'id'                  => 20,
            'domain'              => 'redir.empresa.com',
            'redirect_enabled'    => 1,
            'redirect_url'        => 'https://destino.empresa.com/',
            'redirect_code'       => 301,
            'rate_limit_enabled'  => 1,
            'rate_limit_requests' => 60,
            'rate_limit_unit'     => 'r/m',
            'rate_limit_burst'    => 10,
            'rate_limit_nodelay'  => 0,
        ]);

        $this->assertStringContainsString('limit_req_zone $binary_remote_addr zone=rl_proxy_20:10m rate=60r/m;', $redirectConfig);
        $this->assertStringContainsString('limit_req zone=rl_proxy_20 burst=10;', $redirectConfig);
        $this->assertStringNotContainsString('nodelay', $redirectConfig);
        $this->assertStringContainsString('limit_req_status 429;', $redirectConfig);

        $lbConfig = $generator->generate([
            'id'                     => 21,
            'domain'                 => 'lb.empresa.com',
            'scheme'                 => 'http',
            'load_balancing_enabled' => 1,
            'lb_algorithm'           => 'round_robin',
            'upstreams'              => [
                ['server_host' => '10.0.1.1', 'server_port' => 8080],
                ['server_host' => '10.0.1.2', 'server_port' => 8080],
            ],
            'rate_limit_enabled'     => 1,
            'rate_limit_requests'    => 5,
            'rate_limit_unit'        => 'r/s',
            'rate_limit_burst'       => 0,
            'rate_limit_nodelay'     => 1,
        ]);

        $this->assertStringContainsString('limit_req_zone $binary_remote_addr zone=rl_proxy_21:10m rate=5r/s;', $lbConfig);
        // Con burst=0 no debe emitirse burst= ni nodelay (inválido en Nginx sin burst)
        $this->assertStringContainsString('limit_req zone=rl_proxy_21;', $lbConfig);
        $this->assertStringNotContainsString('burst=', $lbConfig);
        $this->assertStringNotContainsString('nodelay', $lbConfig);
    }

    public function testNginxGeneratorRejectsInvalidRateLimitParameters(): void
    {
        $generator = new NginxConfigGenerator();

        $this->expectException(InvalidArgumentException::class);
        $generator->generate([
            'id'                  => 30,
            'domain'              => 'badrate.empresa.com',
            'scheme'              => 'http',
            'forward_host'        => '10.0.0.30',
            'forward_port'        => 80,
            'rate_limit_enabled'  => 1,
            'rate_limit_requests' => 0,
            'rate_limit_unit'     => 'r/s',
        ]);
    }

    public function testNginxGeneratorRejectsInvalidRateLimitUnit(): void
    {
        $generator = new NginxConfigGenerator();

        $this->expectException(InvalidArgumentException::class);
        $generator->generate([
            'id'                  => 31,
            'domain'              => 'badunit.empresa.com',
            'scheme'              => 'http',
            'forward_host'        => '10.0.0.31',
            'forward_port'        => 80,
            'rate_limit_enabled'  => 1,
            'rate_limit_requests' => 10,
            'rate_limit_unit'     => 'r/h',
        ]);
    }

    public function testControllerCreatesAndUpdatesProxyHostWithRateLimit(): void
    {
        $createRes = $this->withSession($this->adminSession)
            ->post('proxy-hosts/create', $this->withCsrf([
                'domain'              => 'ratelimit-test.empresa.com',
                'proxy_mode'          => 'single',
                'scheme'              => 'http',
                'forward_host'        => '127.0.0.1',
                'forward_port'        => '8081',
                'ssl_certificate'     => 'none',
                'enabled'             => '1',
                'rate_limit_enabled'  => '1',
                'rate_limit_requests' => '15',
                'rate_limit_unit'     => 'r/s',
                'rate_limit_burst'    => '30',
                'rate_limit_nodelay'  => '1',
            ]));

        $createRes->assertRedirectTo(base_url('proxy-hosts'));

        $model = new ProxyHostModel();
        $host  = $model->where('domain', 'ratelimit-test.empresa.com')->first();

        $this->assertNotNull($host);
        $this->assertSame(1, (int) $host['rate_limit_enabled']);
        $this->assertSame(15, (int) $host['rate_limit_requests']);
        $this->assertSame('r/s', $host['rate_limit_unit']);
        $this->assertSame(30, (int) $host['rate_limit_burst']);
        $this->assertSame(1, (int) $host['rate_limit_nodelay']);

        $updateRes = $this->withSession($this->adminSession)
            ->post('proxy-hosts/update/' . $host['id'], $this->withCsrf([
                'domain'              => 'ratelimit-test.empresa.com',
                'proxy_mode'          => 'single',
                'scheme'              => 'http',
                'forward_host'        => '127.0.0.1',
                'forward_port'        => '8081',
                'ssl_certificate'     => 'none',
                'enabled'             => '1',
                'rate_limit_enabled'  => '1',
                'rate_limit_requests' => '120',
                'rate_limit_unit'     => 'r/m',
                'rate_limit_burst'    => '5',
                // nodelay desactivado
            ]));

        $updateRes->assertRedirectTo(base_url('proxy-hosts'));

        $updated = $model->find($host['id']);
        $this->assertSame(1, (int) $updated['rate_limit_enabled']);
        $this->assertSame(120, (int) $updated['rate_limit_requests']);
        $this->assertSame('r/m', $updated['rate_limit_unit']);
        $this->assertSame(5, (int) $updated['rate_limit_burst']);
        $this->assertSame(0, (int) $updated['rate_limit_nodelay']);
    }
}
