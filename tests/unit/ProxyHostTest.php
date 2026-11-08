<?php

namespace Tests\Unit;

use App\Models\AuditLogModel;
use App\Models\ProxyHostModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Pruebas unitarias y de integración para la Fase 3: Gestión de Proxy Hosts.
 * 
 * @internal
 */
final class ProxyHostTest extends CIUnitTestCase
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

    public function testGuestCannotAccessProxyHosts(): void
    {
        $result = $this->withSession([])->get('proxy-hosts');
        $result->assertRedirectTo('/login');
    }

    public function testAuthenticatedUserCanViewProxyHostsList(): void
    {
        $result = $this->withSession($this->adminSession)->get('proxy-hosts');
        $result->assertStatus(200);
        $result->assertSee('Proxy Hosts');
        $result->assertSee('Crear');
    }

    public function testCreateValidProxyHost(): void
    {
        $postData = $this->withCsrf([
            'domain'            => 'panel.empresa.com',
            'domain_names'      => 'www.empresa.com, admin.empresa.com',
            'scheme'            => 'http',
            'forward_host'      => '127.0.0.1',
            'forward_port'      => '8080',
            'ssl_enabled'       => '1',
            'websocket_enabled' => '1',
            'force_https'       => '1',
            'enabled'           => '1',
            'custom_headers'    => "X-Frame-Options: SAMEORIGIN\nX-Custom: test",
        ]);

        $result = $this->withSession($this->adminSession)->post('proxy-hosts/create', $postData);
        $result->assertRedirectTo(base_url('proxy-hosts'));

        // Verificar persistencia en base de datos
        $model = new ProxyHostModel();
        $saved = $model->where('domain', 'panel.empresa.com')->first();
        $this->assertNotNull($saved);
        $this->assertSame('http', $saved['scheme']);
        $this->assertSame('127.0.0.1', $saved['forward_host']);
        $this->assertSame(8080, (int) $saved['forward_port']);
        $this->assertSame(1, (int) $saved['ssl_enabled']);
        $this->assertSame(1, (int) $saved['websocket_enabled']);

        // Verificar registro de auditoría
        $auditModel = new AuditLogModel();
        $log = $auditModel->where('action', 'proxy_create')->orderBy('id', 'DESC')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('panel.empresa.com', $log['description']);
    }

    public function testRejectsInjectionCharactersInDomain(): void
    {
        $invalidDomains = [
            'midominio.com; rm -rf /',
            'midominio.com{proxy_pass}',
            'midominio .com',
            "midominio.com\r\ninjected_header: 1",
            'dominio"quote.com',
        ];

        foreach ($invalidDomains as $badDomain) {
            $postData = $this->withCsrf([
                'domain'       => $badDomain,
                'scheme'       => 'http',
                'forward_host' => '127.0.0.1',
                'forward_port' => '8080',
            ]);

            $result = $this->withSession($this->adminSession)->post('proxy-hosts/create', $postData);
            $result->assertSessionHas('errors');
        }
    }

    public function testBlocksSsrfCloudMetadataIp(): void
    {
        $postData = $this->withCsrf([
            'domain'       => 'ssrf-test.com',
            'scheme'       => 'http',
            'forward_host' => '169.254.169.254', // IP de metadatos cloud prohibida
            'forward_port' => '80',
        ]);

        $result = $this->withSession($this->adminSession)->post('proxy-hosts/create', $postData);
        $result->assertSessionHas('errors');

        $model = new ProxyHostModel();
        $saved = $model->where('domain', 'ssrf-test.com')->first();
        $this->assertNull($saved);
    }

    public function testRejectsInvalidPort(): void
    {
        $postData = $this->withCsrf([
            'domain'       => 'port-test.com',
            'scheme'       => 'http',
            'forward_host' => '127.0.0.1',
            'forward_port' => '99999', // Puerto superior a 65535
        ]);

        $result = $this->withSession($this->adminSession)->post('proxy-hosts/create', $postData);
        $result->assertSessionHas('errors');
    }

    public function testToggleProxyStatus(): void
    {
        $model = new ProxyHostModel();
        $id = $model->insert([
            'domain'       => 'toggle-test.com',
            'scheme'       => 'http',
            'forward_host' => '127.0.0.1',
            'forward_port' => 3000,
            'enabled'      => 1,
        ]);

        // Toggle para deshabilitar
        $result = $this->withSession($this->adminSession)->get("proxy-hosts/toggle/{$id}");
        $result->assertRedirectTo(base_url('proxy-hosts'));

        $updated = $model->find($id);
        $this->assertSame(0, (int) $updated['enabled']);

        // Toggle para volver a habilitar
        $this->withSession($this->adminSession)->get("proxy-hosts/toggle/{$id}");
        $reEnabled = $model->find($id);
        $this->assertSame(1, (int) $reEnabled['enabled']);
    }

    public function testDeleteProxyHost(): void
    {
        $model = new ProxyHostModel();
        $id = $model->insert([
            'domain'       => 'to-delete.com',
            'scheme'       => 'http',
            'forward_host' => '127.0.0.1',
            'forward_port' => 4000,
            'enabled'      => 1,
        ]);

        $result = $this->withSession($this->adminSession)->post("proxy-hosts/delete/{$id}", $this->withCsrf([]));
        $result->assertRedirectTo(base_url('proxy-hosts'));

        $this->assertNull($model->find($id));
    }

    public function testUnifiedSingleDomainInputSeparatesPrimaryAndAliases(): void
    {
        $postData = $this->withCsrf([
            'domain'       => 'app.cloud.io, api.cloud.io, admin.cloud.io',
            'scheme'       => 'https',
            'forward_host' => '10.0.0.5',
            'forward_port' => '8443',
            'enabled'      => '1',
        ]);

        $result = $this->withSession($this->adminSession)->post('proxy-hosts/create', $postData);
        $result->assertRedirectTo(base_url('proxy-hosts'));

        $model = new ProxyHostModel();
        $saved = $model->where('domain', 'app.cloud.io')->first();
        $this->assertNotNull($saved);
        $this->assertSame('app.cloud.io', $saved['domain']);
        $this->assertSame('api.cloud.io, admin.cloud.io', $saved['domain_names']);
        $this->assertSame('https', $saved['scheme']);
        $this->assertSame('10.0.0.5', $saved['forward_host']);
        $this->assertSame(8443, (int) $saved['forward_port']);
    }
}
