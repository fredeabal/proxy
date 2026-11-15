<?php

namespace Tests\Unit;

use App\Models\ProxyHostModel;
use App\Services\NginxConfigGenerator;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use InvalidArgumentException;

/**
 * Pruebas unitarias y de integración para el modo Redirección (301/302).
 *
 * @internal
 */
final class ProxyRedirectModeTest extends CIUnitTestCase
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

    public function testNginxGeneratorCreates301RedirectPreservingPath(): void
    {
        $generator = new NginxConfigGenerator();

        $config = $generator->generate([
            'id'                     => 50,
            'domain'                 => 'antiguo.empresa.com',
            'domain_names'           => 'www.antiguo.empresa.com',
            'redirect_enabled'       => 1,
            'redirect_url'           => 'https://nuevo.empresa.com/',
            'redirect_code'          => 301,
            'redirect_preserve_path' => 1,
            'ssl_enabled'            => 0,
        ]);

        $this->assertStringContainsString('server_name antiguo.empresa.com www.antiguo.empresa.com;', $config);
        $this->assertStringContainsString('return 301 https://nuevo.empresa.com$request_uri;', $config);
        $this->assertStringNotContainsString('proxy_pass', $config);
        $this->assertStringNotContainsString('upstream ', $config);
    }

    public function testNginxGeneratorCreates302RedirectWithoutPath(): void
    {
        $generator = new NginxConfigGenerator();

        $config = $generator->generate([
            'id'                     => 51,
            'domain'                 => 'promo.empresa.com',
            'redirect_enabled'       => 1,
            'redirect_url'           => 'https://destino.empresa.com/landing',
            'redirect_code'          => 302,
            'redirect_preserve_path' => 0,
            'ssl_enabled'            => 0,
        ]);

        $this->assertStringContainsString('return 302 https://destino.empresa.com/landing;', $config);
        $this->assertStringNotContainsString('$request_uri', $config);
        $this->assertStringNotContainsString('proxy_pass', $config);
    }

    public function testNginxGeneratorRejectsUnsafeRedirectUrl(): void
    {
        $generator = new NginxConfigGenerator();

        $this->expectException(InvalidArgumentException::class);

        $generator->generate([
            'id'               => 52,
            'domain'           => 'malo.empresa.com',
            'redirect_enabled' => 1,
            'redirect_url'     => 'https://evil.com; return 200 ok',
            'redirect_code'    => 301,
        ]);
    }

    public function testControllerCreateUpdateAndHealthCheckRedirectHost(): void
    {
        $postData = $this->withCsrf([
            'domain'                 => 'redir.empresa.com',
            'proxy_mode'             => 'redirect',
            'redirect_code'          => '301',
            'redirect_url'           => 'https://nuevo.empresa.com',
            'redirect_preserve_path' => '1',
            'ssl_certificate'        => 'none',
        ]);

        $result = $this->withSession($this->adminSession)->post('proxy-hosts/create', $postData);
        $result->assertRedirectTo(base_url('proxy-hosts'));

        $hostModel = new ProxyHostModel();
        $savedHost = $hostModel->where('domain', 'redir.empresa.com')->first();

        $this->assertNotNull($savedHost);
        $this->assertSame(1, (int) $savedHost['redirect_enabled']);
        $this->assertSame(0, (int) $savedHost['load_balancing_enabled']);
        $this->assertSame('https://nuevo.empresa.com', $savedHost['redirect_url']);
        $this->assertSame(301, (int) $savedHost['redirect_code']);
        $this->assertSame(1, (int) $savedHost['redirect_preserve_path']);

        // Health check responde estructura válida para redirección
        $healthResult = $this->withSession($this->adminSession)->get('proxy-hosts/health-check/' . $savedHost['id']);
        $healthJson   = json_decode($healthResult->getJSON(), true);
        $this->assertTrue($healthJson['success']);
        $this->assertContains($healthJson['status'], ['online', 'offline']);
        $this->assertArrayHasKey('detail', $healthJson);

        // Actualizar a 302 sin conservar ruta
        $updateData = $this->withCsrf([
            'domain'          => 'redir.empresa.com',
            'proxy_mode'      => 'redirect',
            'redirect_code'   => '302',
            'redirect_url'    => 'https://otro.empresa.com/promo',
            'ssl_certificate' => 'none',
        ]);

        $updateResult = $this->withSession($this->adminSession)->post('proxy-hosts/update/' . $savedHost['id'], $updateData);
        $this->assertNull(session('error'), 'Error en sesión: ' . var_export(session('error'), true));
        $this->assertNull(session('errors'), 'Errores en sesión: ' . var_export(session('errors'), true));
        $updateResult->assertRedirectTo(base_url('proxy-hosts'));

        $reloaded = $hostModel->find((int) $savedHost['id']);
        $this->assertSame(302, (int) $reloaded['redirect_code']);
        $this->assertSame('https://otro.empresa.com/promo', $reloaded['redirect_url']);
        $this->assertSame(0, (int) $reloaded['redirect_preserve_path']);

        // Eliminar
        $delResult = $this->withSession($this->adminSession)->post('proxy-hosts/delete/' . $savedHost['id'], $this->withCsrf([]));
        $delResult->assertRedirectTo(base_url('proxy-hosts'));
        $this->assertNull($hostModel->find((int) $savedHost['id']));
    }
}
