<?php

namespace Tests\Unit;

use App\Models\SettingModel;
use App\Services\NginxConfigGenerator;
use App\Services\NginxManager;
use App\Services\NginxValidator;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Nginx as NginxConfig;
use InvalidArgumentException;

/**
 * Pruebas unitarias y de integración para HTTPS nativo del panel de administración.
 *
 * @internal
 */
final class PanelSslTest extends CIUnitTestCase
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

    public function testGeneratePanelVhostHttpOnly(): void
    {
        $generator = new NginxConfigGenerator();
        $config = $generator->generatePanelVhost([
            'domain'      => '192.168.1.50',
            'port'        => 81,
            'ssl_enabled' => false,
            'php_sock'    => '/var/run/php/php8.2-fpm.sock',
        ]);

        $this->assertStringContainsString('listen 81;', $config);
        $this->assertStringContainsString('listen [::]:81;', $config);
        $this->assertStringContainsString('server_name 192.168.1.50;', $config);
        $this->assertStringContainsString('fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;', $config);
        $this->assertStringContainsString('error_page 403 =404 /index.php;', $config);
        $this->assertStringNotContainsString('ssl_certificate', $config);
    }

    public function testGeneratePanelVhostSelfSignedOnPort81(): void
    {
        $generator = new NginxConfigGenerator();
        $config = $generator->generatePanelVhost([
            'domain'      => '192.168.1.50',
            'port'        => 81,
            'ssl_enabled' => true,
            'ssl_mode'    => 'self_signed',
            'force_https' => true,
            'php_sock'    => '/var/run/php/php8.2-fpm.sock',
        ]);

        $this->assertStringContainsString('listen 81 ssl;', $config);
        $this->assertStringContainsString('listen [::]:81 ssl;', $config);
        $this->assertStringContainsString('ssl_certificate /etc/nginx/ssl/proxy-manager.crt;', $config);
        $this->assertStringContainsString('ssl_certificate_key /etc/nginx/ssl/proxy-manager.key;', $config);
        $this->assertStringContainsString('ssl_protocols TLSv1.2 TLSv1.3;', $config);
        $this->assertStringContainsString('error_page 497 =307 https://$host:$server_port$request_uri;', $config);
    }

    public function testGeneratePanelVhostLetsEncryptOnPort443WithRedirect(): void
    {
        $generator = new NginxConfigGenerator();
        $config = $generator->generatePanelVhost([
            'domain'      => 'panel.empresa.com',
            'port'        => 443,
            'ssl_enabled' => true,
            'ssl_mode'    => 'letsencrypt',
            'force_https' => true,
            'php_sock'    => '/var/run/php/php8.2-fpm.sock',
        ]);

        $this->assertStringContainsString('listen 80;', $config);
        $this->assertStringContainsString('return 301 https://$host$request_uri;', $config);
        $this->assertStringContainsString('listen 443 ssl;', $config);
        $this->assertStringContainsString('server_name panel.empresa.com;', $config);
        $this->assertStringContainsString('ssl_certificate /etc/letsencrypt/live/panel.empresa.com/fullchain.pem;', $config);
        $this->assertStringContainsString('ssl_certificate_key /etc/letsencrypt/live/panel.empresa.com/privkey.pem;', $config);
        $this->assertStringNotContainsString('error_page 497', $config);
    }

    public function testRejectsLetsEncryptWithIpAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $generator = new NginxConfigGenerator();
        $generator->generatePanelVhost([
            'domain'      => '10.0.0.5',
            'port'        => 81,
            'ssl_enabled' => true,
            'ssl_mode'    => 'letsencrypt',
        ]);
    }

    public function testRejectsInjectionInPanelDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $generator = new NginxConfigGenerator();
        $generator->generatePanelVhost([
            'domain' => "panel.com; return 200 'pwned';",
            'port'   => 81,
        ]);
    }

    public function testValidatorAllowsFastcgiOnlyWhenExplicitlyEnabled(): void
    {
        $generator = new NginxConfigGenerator();
        $config    = $generator->generatePanelVhost([
            'domain'      => '127.0.0.1',
            'port'        => 81,
            'ssl_enabled' => true,
            'ssl_mode'    => 'self_signed',
        ]);

        $validator = new NginxValidator();
        $this->assertFalse($validator->validateStructure($config, false)['valid']);
        $this->assertTrue($validator->validateStructure($config, true)['valid']);
    }

    public function testNginxManagerApplyPanelVhostInSimulatedDev(): void
    {
        $baseDir = WRITEPATH . 'test_panel_ssl_' . uniqid();
        @mkdir($baseDir . '/sites-available', 0755, true);
        @mkdir($baseDir . '/sites-enabled', 0755, true);
        @mkdir($baseDir . '/staging', 0755, true);

        $cfg = new NginxConfig();
        $cfg->sitesAvailable = $baseDir . '/sites-available';
        $cfg->sitesEnabled   = $baseDir . '/sites-enabled';
        $cfg->stagingPath    = $baseDir . '/staging';
        $cfg->executorBinary = '/non/existent/proxy-executor';
        $cfg->simulateInDev  = true;

        $generator = new NginxConfigGenerator($cfg);
        $manager   = new NginxManager($cfg, $generator, new NginxValidator($cfg));

        $vhost = $generator->generatePanelVhost([
            'domain'      => 'panel.local',
            'port'        => 8443,
            'ssl_enabled' => true,
            'ssl_mode'    => 'self_signed',
            'force_https' => true,
        ]);

        $result = $manager->applyPanelVhost($vhost);
        $this->assertTrue($result['success']);
        $this->assertFileExists($baseDir . '/sites-available/proxy-manager');
        $this->assertTrue(is_link($baseDir . '/sites-enabled/proxy-manager'));

        // Limpieza
        @unlink($baseDir . '/sites-enabled/proxy-manager');
        @unlink($baseDir . '/sites-available/proxy-manager');
        @rmdir($baseDir . '/sites-enabled');
        @rmdir($baseDir . '/sites-available');
        @rmdir($baseDir . '/staging');
        @rmdir($baseDir);
    }

    public function testAdminUpdatePanelSslEndpointPersistsSettings(): void
    {
        $result = $this->withSession($this->adminSession)
            ->post('admin/panel-ssl', $this->withCsrf([
                'panel_domain'      => '192.168.10.20',
                'panel_port'        => '81',
                'panel_ssl_enabled' => '1',
                'panel_ssl_mode'    => 'self_signed',
                'panel_force_https' => '1',
            ]));

        $result->assertRedirectTo(base_url('admin?tab=security'));

        $settingModel = new SettingModel();
        $this->assertSame('192.168.10.20', $settingModel->getVal('panel_domain'));
        $this->assertSame('81', $settingModel->getVal('panel_port'));
        $this->assertSame('1', $settingModel->getVal('panel_ssl_enabled'));
        $this->assertSame('self_signed', $settingModel->getVal('panel_ssl_mode'));
        $this->assertSame('1', $settingModel->getVal('panel_force_https'));
    }

    public function testAdminUpdatePanelSslRejectsLetsEncryptForIp(): void
    {
        $result = $this->withSession($this->adminSession)
            ->post('admin/panel-ssl', $this->withCsrf([
                'panel_domain'      => '192.168.10.20',
                'panel_port'        => '81',
                'panel_ssl_enabled' => '1',
                'panel_ssl_mode'    => 'letsencrypt',
            ]));

        $result->assertRedirectTo(base_url('admin?tab=security'));
        $result->assertSessionHas('error');
    }
}
