<?php

namespace Tests\Unit;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class UserRoleAndProfileTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $seed      = 'App\Database\Seeds\AdminSeeder';

    private array $adminSession;
    private array $userSession;
    private int $adminId;
    private int $normalUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $userModel = new UserModel();

        // Obtener o asegurar admin existente
        $admin = $userModel->where('role', 'admin')->first();
        if (! $admin) {
            $this->adminId = (int) $userModel->insert([
                'name'     => 'Administrador Test',
                'email'    => 'admin@test.local',
                'password' => 'secret12345',
                'role'     => 'admin',
            ]);
        } else {
            $this->adminId = (int) $admin['id'];
        }

        // Crear usuario con rol 'user'
        $normalUser = $userModel->where('email', 'normaluser@test.local')->first();
        if (! $normalUser) {
            $this->normalUserId = (int) $userModel->insert([
                'name'     => 'Operador Usuario',
                'email'    => 'normaluser@test.local',
                'password' => 'secret12345',
                'role'     => 'user',
            ]);
        } else {
            $this->normalUserId = (int) $normalUser['id'];
        }

        $this->adminSession = [
            'user_id'      => $this->adminId,
            'user_name'    => 'Administrador Test',
            'user_email'   => 'admin@test.local',
            'user_role'    => 'admin',
            'is_logged_in' => true,
        ];

        $this->userSession = [
            'user_id'      => $this->normalUserId,
            'user_name'    => 'Operador Usuario',
            'user_email'   => 'normaluser@test.local',
            'user_role'    => 'user',
            'is_logged_in' => true,
        ];
    }

    public function testAdminCanAccessUsersList(): void
    {
        $res = $this->withSession($this->adminSession)->get('users');
        $res->assertStatus(200);
        $res->assertSee('Usuarios');
        $res->assertSee('normaluser@test.local');
    }

    public function testNormalUserCannotAccessAdminOrUsers(): void
    {
        // No puede entrar a /admin
        $resAdmin = $this->withSession($this->userSession)->get('admin');
        $resAdmin->assertStatus(302);
        $resAdmin->assertRedirectTo('/dashboard');

        // No puede entrar a /users
        $resUsers = $this->withSession($this->userSession)->get('users');
        $resUsers->assertStatus(302);
        $resUsers->assertRedirectTo('/dashboard');
    }

    public function testNormalUserCanAccessProfile(): void
    {
        $res = $this->withSession($this->userSession)->get('profile');
        $res->assertStatus(200);
        $res->assertSee('Mi Perfil');
        $res->assertSee('Operador Usuario');
        $res->assertSee('normaluser@test.local');
    }

    public function testUserProfileUpdate(): void
    {
        $postData = [
            csrf_token()       => csrf_hash(),
            'name'             => 'Operador Modificado',
            'email'            => 'normaluser_mod@test.local',
            'password'         => '',
            'password_confirm' => '',
        ];

        $res = $this->withSession($this->userSession)->post('profile/update', $postData);
        $res->assertStatus(302);
        $res->assertRedirectTo('/profile');

        $userModel = new UserModel();
        $updated = $userModel->find($this->normalUserId);
        $this->assertSame('Operador Modificado', $updated['name']);
        $this->assertSame('normaluser_mod@test.local', $updated['email']);
    }

    public function testAdminCanCreateAndEditUser(): void
    {
        // Crear nuevo usuario
        $postData = [
            csrf_token()       => csrf_hash(),
            'name'             => 'Nuevo Operador',
            'email'            => 'nuevo@test.local',
            'role'             => 'user',
            'password'         => 'password123',
            'password_confirm' => 'password123',
        ];

        $res = $this->withSession($this->adminSession)->post('users/store', $postData);
        $res->assertStatus(302);
        $res->assertRedirectTo('/users');

        $userModel = new UserModel();
        $created = $userModel->where('email', 'nuevo@test.local')->first();
        $this->assertNotNull($created);
        $this->assertSame('user', $created['role']);

        // Editar usuario
        $editData = [
            csrf_token()       => csrf_hash(),
            'name'             => 'Nuevo Operador Editado',
            'email'            => 'nuevo@test.local',
            'role'             => 'admin',
            'password'         => '',
            'password_confirm' => '',
        ];

        $resEdit = $this->withSession($this->adminSession)->post('users/update/' . $created['id'], $editData);
        $resEdit->assertStatus(302);
        $resEdit->assertRedirectTo('/users');
        $resEdit->assertRedirectTo('/users');

        $updated = $userModel->find($created['id']);
        $this->assertSame('Nuevo Operador Editado', $updated['name']);
        $this->assertSame('admin', $updated['role']);

        // Eliminar usuario
        $resDelete = $this->withSession($this->adminSession)->post('users/delete/' . $created['id'], [
            csrf_token() => csrf_hash(),
        ]);
        $resDelete->assertStatus(302);
        $resDelete->assertRedirectTo('/users');

        $deleted = $userModel->find($created['id']);
        $this->assertNull($deleted);
    }

    public function testAdminCannotDeleteSelf(): void
    {
        $res = $this->withSession($this->adminSession)->post('users/delete/' . $this->adminId, [
            csrf_token() => csrf_hash(),
        ]);

        $res->assertStatus(302);
        $res->assertRedirectTo('/users');

        $userModel = new UserModel();
        $admin = $userModel->find($this->adminId);
        $this->assertNotNull($admin);
    }

    public function testProfileAvatarHelperAndDefault(): void
    {
        helper('auth');
        $defaultUrl = current_user_avatar();
        $this->assertStringContainsString('default-avatar.png', $defaultUrl);
    }

    public function testProfileAvatarDelete(): void
    {
        $userModel = new UserModel();
        $userModel->skipValidation(true)->update($this->normalUserId, ['avatar' => 'test-avatar.png']);

        $res = $this->withSession($this->userSession)->post('profile/delete-avatar', [
            csrf_token() => csrf_hash(),
        ]);

        $res->assertStatus(302);
        $res->assertRedirectTo('/profile');

        $user = $userModel->find($this->normalUserId);
        $this->assertNull($user['avatar']);
    }
}
