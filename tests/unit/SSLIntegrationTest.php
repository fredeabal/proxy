<?php

namespace Tests\Unit;

use App\Models\AuditLogModel;
use App\Models\CertificateModel;
use App\Models\ProxyHostModel;
use App\Services\SSLManager;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use InvalidArgumentException;

/**
 * Pruebas unitarias y de integración para la Fase 5: Certificados SSL (Let's Encrypt / ACME).
 * 
 * @internal
 */
final class SSLIntegrationTest extends CIUnitTestCase
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

    public function testGuestCannotAccessCertificates(): void
    {
        $result = $this->withSession([])->get('certificates');
        $result->assertRedirectTo('/login');
    }

    public function testAuthenticatedUserCanViewCertificatesList(): void
    {
        $result = $this->withSession($this->adminSession)->get('certificates');
        $result->assertOK();
        $result->assertSee('Certificados SSL');
    }

    public function testRenewSslDirectlyFromProxyHost(): void
    {
        $certId = (new CertificateModel())->insert([
            'domain'           => 'renew-host.empresa.com',
            'certificate_path' => '/etc/letsencrypt/live/renew-host.empresa.com/fullchain.pem',
            'private_key_path' => '/etc/letsencrypt/live/renew-host.empresa.com/privkey.pem',
            'issuer'           => "Let's Encrypt",
            'expires_at'       => date('Y-m-d H:i:s', strtotime('+5 days')),
            'auto_renew'       => 1,
            'status'           => 'expiring_soon',
        ]);

        $hostId = (new ProxyHostModel())->insert([
            'domain'         => 'renew-host.empresa.com',
            'scheme'         => 'http',
            'forward_host'   => '127.0.0.1',
            'forward_port'   => 8080,
            'enabled'        => 1,
            'ssl_enabled'    => 1,
            'certificate_id' => $certId,
        ]);

        $result = $this->withSession($this->adminSession)->post("proxy-hosts/renew-ssl/{$hostId}", $this->withCsrf([]));
        $result->assertRedirectTo(base_url('proxy-hosts'));
    }

    protected function hostPost(array $extra = []): array
    {
        return $this->withCsrf(array_merge([
            'domain'       => 'wizard.empresa.com',
            'scheme'       => 'http',
            'forward_host' => '127.0.0.1',
            'forward_port' => '8080',
            'enabled'      => '1',
        ], $extra));
    }

    public function testWizardRequestsNewCertificateFromSslStep(): void
    {
        $result = $this->withSession($this->adminSession)->post('proxy-hosts/create', $this->hostPost([
            'ssl_certificate' => 'new',
            'ssl_tos'         => '1',
            'ssl_email'       => 'admin@empresa.com',
            'force_https'     => '1',
        ]));
        $result->assertRedirectTo(base_url('proxy-hosts'));

        $host = (new ProxyHostModel())->where('domain', 'wizard.empresa.com')->first();
        $cert = (new CertificateModel())->where('domain', 'wizard.empresa.com')->first();
        $this->assertNotNull($cert);
        $this->assertSame(1, (int) $host['ssl_enabled']);
        $this->assertSame(1, (int) $host['force_https']);
        $this->assertSame((int) $cert['id'], (int) $host['certificate_id']);

        $log = (new AuditLogModel())->where('action', 'ssl_issue_success')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('wizard.empresa.com', $log['description']);
    }

    public function testRequestRouteNoLongerExists(): void
    {
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->withSession($this->adminSession)->post('certificates/request', $this->withCsrf(['domain' => 'x.com']));
    }

    public function testWizardNewCertificateRequiresTos(): void
    {
        $this->withSession($this->adminSession)->post('proxy-hosts/create', $this->hostPost([
            'ssl_certificate' => 'new',
        ]));

        $this->assertNull((new ProxyHostModel())->where('domain', 'wizard.empresa.com')->first());
    }

    public function testWizardUsesExistingCertificate(): void
    {
        $certId = (new CertificateModel())->insert([
            'domain'           => 'existente.empresa.com',
            'certificate_path' => '/etc/letsencrypt/live/existente.empresa.com/fullchain.pem',
            'private_key_path' => '/etc/letsencrypt/live/existente.empresa.com/privkey.pem',
            'issuer'           => "Let's Encrypt",
            'expires_at'       => date('Y-m-d H:i:s', strtotime('+60 days')),
            'auto_renew'       => 1,
            'status'           => 'valid',
        ]);

        $this->withSession($this->adminSession)->post('proxy-hosts/create', $this->hostPost([
            'ssl_certificate' => (string) $certId,
            'force_https'     => '1',
        ]));

        $host = (new ProxyHostModel())->where('domain', 'wizard.empresa.com')->first();
        $this->assertNotNull($host);
        $this->assertSame($certId, (int) $host['certificate_id']);
        $this->assertSame(1, (int) $host['ssl_enabled']);

        $preview = $this->withSession($this->adminSession)->get('proxy-hosts/preview/' . $host['id']);
        $json = json_decode($preview->response()->getBody(), true);
        $this->assertStringContainsString('/etc/letsencrypt/live/existente.empresa.com/fullchain.pem', $json['config']);
    }

    public function testWizardNoneDisablesSsl(): void
    {
        $this->withSession($this->adminSession)->post('proxy-hosts/create', $this->hostPost([
            'ssl_certificate' => 'none',
            'force_https'     => '1',
        ]));

        $host = (new ProxyHostModel())->where('domain', 'wizard.empresa.com')->first();
        $this->assertSame(0, (int) $host['ssl_enabled']);
        $this->assertSame(0, (int) $host['force_https']);
        $this->assertNull($host['certificate_id']);
    }

    public function testRejectsMaliciousDomainInSSL(): void
    {
        $sslManager = new SSLManager();

        $this->expectException(InvalidArgumentException::class);
        $sslManager->issueCertificate('dominio.com; rm -rf /');
    }

    public function testRejectsFlagInjectionInSSL(): void
    {
        $sslManager = new SSLManager();

        $this->expectException(InvalidArgumentException::class);
        $sslManager->issueCertificate('--pre-hook');
    }

    public function testRenewCertificate(): void
    {
        $certModel = new CertificateModel();
        $id = $certModel->insert([
            'domain'           => 'renovar.empresa.com',
            'certificate_path' => '/etc/letsencrypt/live/renovar.empresa.com/fullchain.pem',
            'private_key_path' => '/etc/letsencrypt/live/renovar.empresa.com/privkey.pem',
            'issuer'           => "Let's Encrypt",
            'expires_at'       => date('Y-m-d H:i:s', strtotime('+5 days')), // Por caducar
            'auto_renew'       => 1,
            'status'           => 'expiring_soon',
        ]);

        $postData = $this->withCsrf([]);
        $result = $this->withSession($this->adminSession)->post("certificates/renew/{$id}", $postData);
        $result->assertRedirectTo(base_url('certificates'));

        $renewed = $certModel->find($id);
        $this->assertSame('valid', $renewed['status']);
        // Nueva fecha debe ser mayor a 60 días
        $this->assertGreaterThan(time() + (60 * 86400), strtotime($renewed['expires_at']));
    }

    public function testRenewAllExpiringCertificates(): void
    {
        $certModel = new CertificateModel();
        // Insertar uno próximo a expirar (10 días) y uno con 80 días
        $certModel->insert([
            'domain'           => 'expira-pronto.com',
            'certificate_path' => '/etc/letsencrypt/live/expira-pronto.com/fullchain.pem',
            'private_key_path' => '/etc/letsencrypt/live/expira-pronto.com/privkey.pem',
            'issuer'           => "Let's Encrypt",
            'expires_at'       => date('Y-m-d H:i:s', strtotime('+10 days')),
            'auto_renew'       => 1,
            'status'           => 'expiring_soon',
        ]);

        $certModel->insert([
            'domain'           => 'no-expira.com',
            'certificate_path' => '/etc/letsencrypt/live/no-expira.com/fullchain.pem',
            'private_key_path' => '/etc/letsencrypt/live/no-expira.com/privkey.pem',
            'issuer'           => "Let's Encrypt",
            'expires_at'       => date('Y-m-d H:i:s', strtotime('+80 days')),
            'auto_renew'       => 1,
            'status'           => 'valid',
        ]);

        $result = $this->withSession($this->adminSession)->get('certificates/renew-all');
        $result->assertRedirectTo(base_url('certificates'));

        $expiraPronto = $certModel->where('domain', 'expira-pronto.com')->first();
        $this->assertSame('valid', $expiraPronto['status']);
    }

    public function testDeleteCertificate(): void
    {
        $proxyModel = new ProxyHostModel();
        $proxyId = $proxyModel->insert([
            'domain'            => 'borrar-ssl.com',
            'domain_names'      => '',
            'scheme'            => 'http',
            'forward_host'      => '127.0.0.1',
            'forward_port'      => 8080,
            'ssl_enabled'       => 1,
            'websocket_enabled' => 0,
            'force_https'       => 1,
            'enabled'           => 1,
            'custom_headers'    => '',
        ]);

        $certModel = new CertificateModel();
        $certId = $certModel->insert([
            'domain'           => 'borrar-ssl.com',
            'certificate_path' => '/etc/letsencrypt/live/borrar-ssl.com/fullchain.pem',
            'private_key_path' => '/etc/letsencrypt/live/borrar-ssl.com/privkey.pem',
            'issuer'           => "Let's Encrypt",
            'expires_at'       => date('Y-m-d H:i:s', strtotime('+90 days')),
            'auto_renew'       => 1,
            'status'           => 'valid',
        ]);

        $postData = $this->withCsrf([]);
        $result = $this->withSession($this->adminSession)->post("certificates/delete/{$certId}", $postData);
        $result->assertRedirectTo(base_url('certificates'));

        $this->assertNull($certModel->find($certId));

        // Proxy Host debe haber vuelto a ssl_enabled = 0 y force_https = 0
        $updatedProxy = $proxyModel->find($proxyId);
        $this->assertSame(0, (int) $updatedProxy['ssl_enabled']);
        $this->assertSame(0, (int) $updatedProxy['force_https']);
    }
}

