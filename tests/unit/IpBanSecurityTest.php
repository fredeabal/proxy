<?php

namespace Tests\Unit;

use App\Models\BannedIpModel;
use App\Services\NginxManager;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App as AppConfig;

/**
 * Pruebas unitarias y de integración para el sistema de bloqueo de IPs (Fail2ban Interno + Cloudflare Real-IP).
 *
 * @internal
 */
final class IpBanSecurityTest extends CIUnitTestCase
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

    public function testCloudflareProxyIpsConfiguredForRealIpExtraction(): void
    {
        $appConfig = config(AppConfig::class);
        $this->assertNotEmpty($appConfig->proxyIPs);
        $this->assertSame('CF-Connecting-IP', $appConfig->proxyIPs['173.245.48.0/20'] ?? null);
        $this->assertSame('CF-Connecting-IP', $appConfig->proxyIPs['2400:cb00::/32'] ?? null);
    }

    public function testAutoBanAfterFiveFailedAttempts(): void
    {
        $banModel = new BannedIpModel();
        $attackerIp = '198.51.100.44';

        for ($i = 1; $i <= 4; $i++) {
            $bannedNow = $banModel->recordFailedAttempt($attackerIp, 'Intento fallido de login', 5, 30);
            $this->assertFalse($bannedNow, "No debe bloquearse en el intento #{$i}");
            $this->assertNull($banModel->isBanned($attackerIp));
        }

        // Quinto intento fallido: activa bloqueo automático
        $bannedNow = $banModel->recordFailedAttempt($attackerIp, '5 intentos fallidos de login', 5, 30);
        $this->assertTrue($bannedNow);

        $activeBan = $banModel->isBanned($attackerIp);
        $this->assertNotNull($activeBan);
        $this->assertSame($attackerIp, $activeBan['ip_address']);
        $this->assertSame(5, (int) $activeBan['failed_attempts']);
        $this->assertSame(0, (int) $activeBan['is_permanent']);
        $this->assertNotEmpty($activeBan['expires_at']);
    }

    public function testManualBanSyncsNginxDenyDirectivesAndUnbanRemovesIt(): void
    {
        $targetIp = '203.0.113.99';

        $postData = $this->withCsrf([
            'ip_address' => $targetIp,
            'duration'   => '0', // Permanente
            'reason'     => 'Escaneo malicioso detectado',
        ]);

        $res = $this->withSession($this->adminSession)->post('admin/bans/add', $postData);
        $res->assertRedirectTo(base_url('admin?tab=bans'));

        $banModel = new BannedIpModel();
        $ban = $banModel->isBanned($targetIp);
        $this->assertNotNull($ban);
        $this->assertSame(1, (int) $ban['is_permanent']);

        // Comprobar generación de directivas Nginx deny
        $nginxManager = new NginxManager();
        $syncResult = $nginxManager->syncBannedIps($banModel->getActiveBans());
        $this->assertTrue($syncResult['success']);
        $this->assertStringContainsString("deny {$targetIp};", $syncResult['content']);

        // Desbloquear IP desde el controlador
        $unbanRes = $this->withSession($this->adminSession)->post('admin/bans/unban/' . $ban['id'], $this->withCsrf([]));
        $unbanRes->assertRedirectTo(base_url('admin?tab=bans'));
        $this->assertNull($banModel->isBanned($targetIp));
    }

    public function testPreventsAdminFromBanningLoopbackOrOwnIp(): void
    {
        $postData = $this->withCsrf([
            'ip_address' => '127.0.0.1',
            'duration'   => '0',
            'reason'     => 'Prueba bloqueo propio',
        ]);

        $res = $this->withSession($this->adminSession)->post('admin/bans/add', $postData);
        $res->assertRedirectTo(base_url('admin?tab=bans'));
        $res->assertSessionHas('error');

        $banModel = new BannedIpModel();
        $this->assertNull($banModel->isBanned('127.0.0.1'));
    }

    public function testAdminBansTabRendersCorrectly(): void
    {
        $banModel = new BannedIpModel();
        $banModel->banIp('198.51.100.77', 'Bot de prueba', 60, 'admin');

        $res = $this->withSession($this->adminSession)->get('admin?tab=bans');
        $res->assertStatus(200);
        $res->assertSee('IPs Bloqueadas');
        $res->assertSee('198.51.100.77');
        $res->assertSee('Bloquear IP o Rango CIDR');
    }

    public function testCidrRangeBanBlocksMatchingIpsAndPreventsSelfRangeBan(): void
    {
        $cidrRange = '203.0.113.0/24';

        $postData = $this->withCsrf([
            'ip_address' => $cidrRange,
            'duration'   => '1440',
            'reason'     => 'Subred de ataque',
        ]);

        $res = $this->withSession($this->adminSession)->post('admin/bans/add', $postData);
        $res->assertRedirectTo(base_url('admin?tab=bans'));

        $banModel = new BannedIpModel();
        // Una IP dentro del rango /24 debe quedar bloqueada
        $matchedBan = $banModel->isBanned('203.0.113.45');
        $this->assertNotNull($matchedBan);
        $this->assertSame($cidrRange, $matchedBan['ip_address']);

        // Una IP fuera del rango /24 no debe verse afectada
        $this->assertNull($banModel->isBanned('203.0.114.1'));

        // Comprobar generación de directiva CIDR en Nginx
        $nginxManager = new NginxManager();
        $syncResult = $nginxManager->syncBannedIps($banModel->getActiveBans());
        $this->assertTrue($syncResult['success']);
        $this->assertStringContainsString("deny {$cidrRange};", $syncResult['content']);

        // Impedir bloquear un rango CIDR que incluya loopback (127.0.0.0/8)
        $selfRangeData = $this->withCsrf([
            'ip_address' => '127.0.0.0/8',
            'duration'   => '0',
            'reason'     => 'Intento bloquear loopback CIDR',
        ]);
        $resLoop = $this->withSession($this->adminSession)->post('admin/bans/add', $selfRangeData);
        $resLoop->assertRedirectTo(base_url('admin?tab=bans'));
        $resLoop->assertSessionHas('error');
    }
}

