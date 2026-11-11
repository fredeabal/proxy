<?php

namespace Tests\Unit;

use App\Models\ProxyHostModel;
use App\Models\ProxyUpstreamModel;
use App\Services\NginxConfigGenerator;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use InvalidArgumentException;

/**
 * Pruebas unitarias y de integración para Balanceo de Carga y Upstreams.
 *
 * @internal
 */
final class ProxyLoadBalancingTest extends CIUnitTestCase
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

    public function testNginxGeneratorCreatesRoundRobinUpstream(): void
    {
        $generator = new NginxConfigGenerator();

        $proxy = [
            'id'                     => 10,
            'domain'                 => 'app.empresa.com',
            'scheme'                 => 'http',
            'load_balancing_enabled' => 1,
            'lb_algorithm'           => 'round_robin',
            'upstreams'              => [
                [
                    'server_host'  => '192.168.1.10',
                    'server_port'  => 8080,
                    'weight'       => 2,
                    'max_fails'    => 3,
                    'fail_timeout' => 10,
                ],
                [
                    'server_host'  => '192.168.1.11',
                    'server_port'  => 8081,
                    'weight'       => 1,
                    'max_fails'    => 5,
                    'fail_timeout' => 15,
                    'is_backup'    => 1,
                ],
            ],
        ];

        $config = $generator->generate($proxy);

        // Debe contener el bloque upstream
        $this->assertStringContainsString('upstream upstream_proxy_10 {', $config);
        $this->assertStringContainsString('server 192.168.1.10:8080 weight=2 max_fails=3 fail_timeout=10s;', $config);
        $this->assertStringContainsString('server 192.168.1.11:8081 max_fails=5 fail_timeout=15s backup;', $config);
        $this->assertStringContainsString('keepalive 32;', $config);

        // proxy_pass debe apuntar al upstream
        $this->assertStringContainsString('proxy_pass http://upstream_proxy_10;', $config);
        $this->assertStringContainsString('proxy_next_upstream error timeout invalid_header http_502 http_503;', $config);
    }

    public function testNginxGeneratorCreatesLeastConnAndIpHashUpstreams(): void
    {
        $generator = new NginxConfigGenerator();

        // 1. Least Conn
        $leastConnProxy = [
            'id'                     => 11,
            'domain'                 => 'least.empresa.com',
            'scheme'                 => 'http',
            'load_balancing_enabled' => 1,
            'lb_algorithm'           => 'least_conn',
            'upstreams'              => [
                ['server_host' => '10.0.0.1', 'server_port' => 8000],
                ['server_host' => '10.0.0.2', 'server_port' => 8000],
            ],
        ];

        $configLeast = $generator->generate($leastConnProxy);
        $this->assertStringContainsString('upstream upstream_proxy_11 {', $configLeast);
        $this->assertStringContainsString('least_conn;', $configLeast);

        // 2. IP Hash
        $ipHashProxy = [
            'id'                     => 12,
            'domain'                 => 'iphash.empresa.com',
            'scheme'                 => 'http',
            'load_balancing_enabled' => 1,
            'lb_algorithm'           => 'ip_hash',
            'upstreams'              => [
                ['server_host' => '10.0.0.1', 'server_port' => 8000, 'weight' => 5], // weight debe suprimirse en ip_hash
                ['server_host' => '10.0.0.2', 'server_port' => 8000, 'is_down' => 1],
            ],
        ];

        $configIpHash = $generator->generate($ipHashProxy);
        $this->assertStringContainsString('upstream upstream_proxy_12 {', $configIpHash);
        $this->assertStringContainsString('ip_hash;', $configIpHash);
        $this->assertStringNotContainsString('weight=5', $configIpHash);
        $this->assertStringContainsString('down;', $configIpHash);
    }

    public function testNginxGeneratorRejectsInvalidUpstreamData(): void
    {
        $generator = new NginxConfigGenerator();

        // Host peligroso con caracteres prohibidos
        $this->expectException(InvalidArgumentException::class);
        $generator->generate([
            'id'                     => 13,
            'domain'                 => 'evil.empresa.com',
            'scheme'                 => 'http',
            'load_balancing_enabled' => 1,
            'lb_algorithm'           => 'round_robin',
            'upstreams'              => [
                ['server_host' => '192.168.1.10; injection', 'server_port' => 80],
            ],
        ]);
    }

    public function testNginxGeneratorRejectsEmptyUpstreamsWhenLbEnabled(): void
    {
        $generator = new NginxConfigGenerator();

        $this->expectException(InvalidArgumentException::class);
        $generator->generate([
            'id'                     => 14,
            'domain'                 => 'empty.empresa.com',
            'scheme'                 => 'http',
            'load_balancing_enabled' => 1,
            'lb_algorithm'           => 'round_robin',
            'upstreams'              => [],
        ]);
    }

    public function testProxyUpstreamModelSyncAndGet(): void
    {
        $hostModel = new ProxyHostModel();
        $upstreamModel = new ProxyUpstreamModel();

        $hostId = (int) $hostModel->insert([
            'domain'                 => 'cluster.empresa.com',
            'scheme'                 => 'http',
            'forward_host'           => '192.168.1.20',
            'forward_port'           => 3000,
            'load_balancing_enabled' => 1,
            'lb_algorithm'           => 'round_robin',
            'enabled'                => 1,
        ]);

        $this->assertGreaterThan(0, $hostId);

        $servers = [
            [
                'server_host'  => '192.168.1.20',
                'server_port'  => 3000,
                'weight'       => 1,
                'max_fails'    => 3,
                'fail_timeout' => 10,
            ],
            [
                'server_host'  => '192.168.1.21',
                'server_port'  => 3001,
                'weight'       => 2,
                'max_fails'    => 4,
                'fail_timeout' => 20,
                'is_backup'    => 1,
            ],
        ];

        $synced = $upstreamModel->syncForProxy($hostId, $servers);
        $this->assertTrue($synced);

        $saved = $upstreamModel->getForProxy($hostId);
        $this->assertCount(2, $saved);
        $this->assertSame('192.168.1.20', $saved[0]['server_host']);
        $this->assertSame(3000, (int) $saved[0]['server_port']);
        $this->assertSame('192.168.1.21', $saved[1]['server_host']);
        $this->assertSame(1, (int) $saved[1]['is_backup']);

        // Limpieza
        $hostModel->delete($hostId);
    }

    public function testControllerCreateAndUpdateProxyHostWithUpstreams(): void
    {
        $postData = $this->withCsrf([
            'domain'                 => 'loadbalancer.empresa.com',
            'scheme'                 => 'http',
            'load_balancing_enabled' => '1',
            'lb_algorithm'           => 'least_conn',
            'upstreams'              => [
                0 => [
                    'server_host'  => '10.10.10.1',
                    'server_port'  => '8080',
                    'weight'       => '3',
                    'max_fails'    => '2',
                    'fail_timeout' => '15',
                ],
                1 => [
                    'server_host'  => '10.10.10.2',
                    'server_port'  => '8080',
                    'weight'       => '1',
                    'max_fails'    => '3',
                    'fail_timeout' => '10',
                    'is_backup'    => '1',
                ],
            ],
            'ssl_certificate'        => 'none',
        ]);

        $result = $this->withSession($this->adminSession)->post('proxy-hosts/create', $postData);
        $result->assertRedirectTo(base_url('proxy-hosts'));

        $hostModel = new ProxyHostModel();
        $upstreamModel = new ProxyUpstreamModel();

        $savedHost = $hostModel->where('domain', 'loadbalancer.empresa.com')->first();
        $this->assertNotNull($savedHost);
        $this->assertSame(1, (int) $savedHost['load_balancing_enabled']);
        $this->assertSame('least_conn', $savedHost['lb_algorithm']);

        $upstreams = $upstreamModel->getForProxy((int) $savedHost['id']);
        $this->assertCount(2, $upstreams);
        $this->assertSame('10.10.10.1', $upstreams[0]['server_host']);
        $this->assertSame(8080, (int) $upstreams[0]['server_port']);
        $this->assertSame(3, (int) $upstreams[0]['weight']);

        // Actualizar cambiando a round_robin (máximo 2 servidores) con puerto 80 en ambos
        $updateData = $this->withCsrf([
            'domain'                 => 'loadbalancer.empresa.com',
            'scheme'                 => 'http',
            'forward_host'           => '10.10.10.1',
            'forward_port'           => '8080', // Valor residual que el navegador enviaba
            'load_balancing_enabled' => '1',
            'lb_algorithm'           => 'round_robin',
            'upstreams'              => [
                0 => ['server_host' => '10.10.10.1', 'server_port' => '80', 'weight' => '1'],
                1 => ['server_host' => '10.10.10.2', 'server_port' => '80', 'weight' => '1', 'role' => 'backup'],
            ],
            'ssl_certificate'        => 'none',
        ]);

        $updateResult = $this->withSession($this->adminSession)->post('proxy-hosts/update/' . $savedHost['id'], $updateData);
        $updateResult->assertRedirectTo(base_url('proxy-hosts'));

        $reloadedHost = $hostModel->find((int) $savedHost['id']);
        $this->assertSame(80, (int) $reloadedHost['forward_port']);

        $updatedUpstreams = $upstreamModel->getForProxy((int) $savedHost['id']);
        $this->assertCount(2, $updatedUpstreams);
        $this->assertSame(80, (int) $updatedUpstreams[0]['server_port']);
        $this->assertSame(80, (int) $updatedUpstreams[1]['server_port']);
        $this->assertSame(1, (int) $updatedUpstreams[1]['is_backup']);

        // Eliminar y verificar borrado en cascada
        $delResult = $this->withSession($this->adminSession)->post('proxy-hosts/delete/' . $savedHost['id'], $this->withCsrf([]));
        $delResult->assertRedirectTo(base_url('proxy-hosts'));

        $this->assertCount(0, $upstreamModel->getForProxy((int) $savedHost['id']));
    }

    public function testHealthCheckEndpointReturnsJson(): void
    {
        $hostModel = new ProxyHostModel();
        $hostId = $hostModel->insert([
            'domain'                 => 'health.test.local',
            'scheme'                 => 'http',
            'forward_host'           => '127.0.0.1',
            'forward_port'           => 65432,
            'load_balancing_enabled' => 0,
            'enabled'                => 1,
        ]);

        $result = $this->withSession($this->adminSession)->get('proxy-hosts/health-check/' . $hostId);
        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertSame($hostId, (int) $json['id']);
        $this->assertArrayHasKey('status', $json);
        $this->assertArrayHasKey('detail', $json);
        $this->assertSame('offline', $json['status']);
    }
}
