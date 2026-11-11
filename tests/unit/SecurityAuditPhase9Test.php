<?php

namespace Tests\Unit;

use App\Models\UserModel;
use App\Services\NginxConfigGenerator;
use App\Services\TotpService;
use App\Validation\ProxyHostRules;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use InvalidArgumentException;

/**
 * Pruebas de Auditoría de Seguridad, Hardening, 2FA (TOTP) y Pentesting Interno (Fase 9).
 * Valida los 4 Límites de Confianza (Trust Boundaries) del proyecto.
 *
 * @internal
 */
final class SecurityAuditPhase9Test extends CIUnitTestCase
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

    // =====================================================================
    // LÍMITE 1: Web/Usuario -> CodeIgniter (Headers, 2FA TOTP, Auth Bypass)
    // =====================================================================

    public function testHardenedHttpSecurityHeadersArePresent(): void
    {
        $result = $this->withSession($this->adminSession)->get('dashboard');
        $result->assertStatus(200);

        $response = $result->response();
        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
        $this->assertStringContainsString("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'self'", $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testTotpServiceGeneratesValidRfc6238Codes(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret(16);

        $this->assertSame(16, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{16}$/', $secret);

        $code = $totp->getCode($secret, 1000);
        $this->assertSame(6, strlen($code));
        $this->assertTrue($totp->verifyCode($secret, $code, 1, 1000));
        // Ventana adyacente (+1 de 30s) también es válida por tolerancia de reloj
        $this->assertTrue($totp->verifyCode($secret, $code, 1, 1001));
        // Fuera de ventana (+5 intervalos) debe ser rechazado
        $this->assertFalse($totp->verifyCode($secret, $code, 1, 1005));

        $uri = $totp->getProvisioningUri('admin@demo.com', $secret);
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString($secret, $uri);
    }

    public function testFullTwoFactorAuthenticationFlowAndEnforcement(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();
        $validCode = $totp->getCode($secret);

        // 1. Activar 2FA desde /admin/2fa/enable
        $enableRes = $this->withSession($this->adminSession)->post('admin/2fa/enable', $this->withCsrf([
            'totp_secret' => $secret,
            'totp_code'   => $validCode,
        ]));
        $enableRes->assertRedirectTo(base_url('admin?tab=security'));

        $userModel = new UserModel();
        $admin = $userModel->find(1);
        $this->assertSame($secret, $admin['two_factor_secret']);

        // 2. Iniciar sesión con usuario y contraseña correctos -> debe redirigir al reto /login/2fa sin autenticar aún
        $loginRes = $this->withSession([])->post('login', $this->withCsrf([
            'email'    => 'admin@demo.com',
            'password' => 'admin123',
        ]));
        $loginRes->assertRedirectTo('/login/2fa');
        $this->assertFalse((bool) session()->get('is_logged_in'));
        $this->assertSame(1, (int) session()->get('2fa_pending_user_id'));

        // 3. Intentar código 2FA incorrecto -> debe rechazar y mantener is_logged_in = false
        $bad2fa = $this->withSession([
            '2fa_pending_user_id' => 1,
            'is_logged_in'        => false,
        ])->post('login/2fa', $this->withCsrf([
            'totp_code' => '000000',
        ]));
        $bad2fa->assertSessionHas('error');

        // 4. Enviar código 2FA válido -> debe completar login y redirigir a /dashboard
        $good2fa = $this->withSession([
            '2fa_pending_user_id' => 1,
            'is_logged_in'        => false,
        ])->post('login/2fa', $this->withCsrf([
            'totp_code' => $totp->getCode($secret),
        ]));
        $good2fa->assertRedirectTo('/dashboard');

        // 5. Desactivar 2FA
        $disableRes = $this->withSession($this->adminSession)->post('admin/2fa/disable', $this->withCsrf([]));
        $disableRes->assertRedirectTo(base_url('admin?tab=security'));
        $this->assertEmpty($userModel->find(1)['two_factor_secret']);
    }

    // =====================================================================
    // LÍMITE 2: CodeIgniter -> Plantillas Nginx (Fuzzing CRLF e Inyección)
    // =====================================================================

    public function testFuzzingNginxTemplateAgainstCrlfAndDirectiveBreakout(): void
    {
        $generator = new NginxConfigGenerator();

        $fuzzPayloads = [
            ['domain' => "test.com\r\nlocation / { return 200; }"],
            ['domain' => 'test.com; include /etc/passwd;'],
            ['forward_host' => "127.0.0.1;\nproxy_pass http://evil.com"],
            ['forward_host' => '127.0.0.1} server {'],
            ['custom_headers' => "X-Frame-Options: DENY\r\n}\r\nserver {\r\nlisten 9999;"],
            ['custom_headers' => 'X-Frame-Options: "DENY"; lua_code_cache off;'],
        ];

        foreach ($fuzzPayloads as $override) {
            $proxy = array_merge([
                'id'           => 77,
                'domain'       => 'seguro.empresa.com',
                'scheme'       => 'http',
                'forward_host' => '127.0.0.1',
                'forward_port' => 8080,
            ], $override);

            try {
                $generator->generate($proxy);
                $this->fail('El generador Nginx no bloqueó el payload de fuzzing: ' . json_encode($override));
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    // =====================================================================
    // LÍMITE 3: CodeIgniter -> Ejecutor Privilegiado (Whitelist y Flags)
    // =====================================================================

    public function testPrivilegedExecutorRejectsArbitraryCommandsAndPathTraversal(): void
    {
        $executor = ROOTPATH . 'executor/proxy-executor.sh';
        $this->assertFileExists($executor);

        // 1. Acción fuera de la lista blanca
        $out1 = [];
        $code1 = 0;
        exec(escapeshellarg($executor) . ' arbitrary-command 2>&1', $out1, $code1);
        $this->assertNotSame(0, $code1);
        $this->assertStringContainsString('Acción no reconocida', implode("\n", $out1));

        // 2. Inyección de comando en host_id de apply-host
        $out2 = [];
        $code2 = 0;
        exec(escapeshellarg($executor) . ' apply-host "1; id" 2>&1', $out2, $code2);
        $this->assertSame(6, $code2);

        // 3. Inyección de flag en issue-cert (--pre-hook)
        $out3 = [];
        $code3 = 0;
        exec(escapeshellarg($executor) . ' issue-cert "--pre-hook=id" 2>&1', $out3, $code3);
        $this->assertSame(17, $code3);

        // 4. Path Traversal en cert-info
        $out4 = [];
        $code4 = 0;
        exec(escapeshellarg($executor) . ' cert-info "../../etc/passwd" 2>&1', $out4, $code4);
        $this->assertSame(24, $code4);

        // 5. Inyección de comando en host_id de disable-host
        $out5 = [];
        $code5 = 0;
        exec(escapeshellarg($executor) . ' disable-host "1; id" 2>&1', $out5, $code5);
        $this->assertSame(10, $code5);

        // 6. Path traversal en staging_file de disable-host
        $out6 = [];
        $code6 = 0;
        exec(escapeshellarg($executor) . ' disable-host 1 "/etc/hosts" 2>&1', $out6, $code6);
        $this->assertSame(8, $code6);
    }

    // =====================================================================
    // LÍMITE 4: Proxy -> Red Interna (Protección SSRF Ampliada)
    // =====================================================================

    public function testSsrfProtectionBlocksMetadataAndReservedAddresses(): void
    {
        $rules = new ProxyHostRules();

        $ssrfTargets = [
            '169.254.169.254',
            '169.254.0.1',
            '::ffff:169.254.169.254',
            'fe80::1',
            '0.0.0.0',
            '255.255.255.255',
            'metadata.google.internal',
            'metadata.internal',
            'instance-data',
        ];

        foreach ($ssrfTargets as $target) {
            $error = null;
            $this->assertFalse(
                $rules->valid_forward_host($target, $error),
                "El destino SSRF restringido no fue bloqueado: {$target}"
            );
            $this->assertNotEmpty($error);
        }
    }

    public function testGitignoreExcludesSecretsAndDatabases(): void
    {
        $gitignore = (string) file_get_contents(ROOTPATH . '.gitignore');
        $this->assertStringContainsString('.env', $gitignore);
        $this->assertStringContainsString('*.sqlite', $gitignore);
        $this->assertStringContainsString('*.key', $gitignore);
        $this->assertStringContainsString('*.pem', $gitignore);
        $this->assertStringContainsString('/writable/backups/*', $gitignore);
    }
}
