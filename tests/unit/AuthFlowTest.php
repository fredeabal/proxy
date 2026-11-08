<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AuthFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $seed      = 'App\Database\Seeds\AdminSeeder';

    public function testGuestCannotAccessDashboard(): void
    {
        $result = $this->withSession([])->get('dashboard');
        $result->assertRedirectTo('/login');
    }

    public function testLoginPageRendersSuccessfully(): void
    {
        $result = $this->get('login');
        $result->assertStatus(200);
        $result->assertSee('Proxy Manager');
    }

    public function testAuthenticatedUserCanAccessDashboard(): void
    {
        $sessionData = [
            'is_logged_in' => true,
            'user_id'      => 1,
            'user_name'    => 'Administrador',
            'user_email'   => 'admin@demo.com',
            'user_role'    => 'admin',
        ];

        $result = $this->withSession($sessionData)->get('dashboard');
        $result->assertStatus(200);
        $result->assertSee('Recursos del Servidor');
        $result->assertSee('Proxy Hosts');
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $security = service('security');

        $result = $this->post('login', [
            $security->getTokenName() => $security->getHash(),
            'email'                   => 'admin@demo.com',
            'password'                => 'wrongpassword',
        ]);

        $result->assertSessionHas('error');
    }

    public function testLoginSucceedsWithValidCredentials(): void
    {
        $security = service('security');

        $result = $this->post('login', [
            $security->getTokenName() => $security->getHash(),
            'email'                   => 'admin@demo.com',
            'password'                => 'admin123',
        ]);

        $result->assertRedirectTo('/dashboard');
        $result->assertSessionHas('is_logged_in', true);
        $result->assertSessionHas('user_email', 'admin@demo.com');
    }
}
