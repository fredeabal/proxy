<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class UsersController extends BaseController
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Listado de usuarios del sistema (solo accesible por administradores).
     */
    public function index(): string
    {
        $search = trim((string) $this->request->getGet('q'));

        $query = $this->userModel->orderBy('id', 'ASC');

        if ($search !== '') {
            $query = $query->groupStart()
                ->like('name', $search)
                ->orLike('email', $search)
                ->groupEnd();
        }

        $users = $query->findAll();

        $data = [
            'title'  => 'Usuarios',
            'users'  => $users,
            'search' => $search,
        ];

        return view('template/header', $data)
            . view('users/index', $data)
            . view('template/footer', $data);
    }

    /**
     * Formulario de creación de un nuevo usuario.
     */
    public function create(): string
    {
        $data = [
            'title' => 'Crear Usuario',
        ];

        return view('template/header', $data)
            . view('users/create', $data)
            . view('template/footer', $data);
    }

    /**
     * Guarda el nuevo usuario en base de datos.
     */
    public function store(): RedirectResponse
    {
        $rules = [
            'name'             => 'required|min_length[2]|max_length[100]',
            'email'            => 'required|valid_email|is_unique[users.email]',
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
            'role'             => 'required|in_list[admin,user]',
        ];

        $messages = [
            'name' => [
                'required'   => 'El nombre completo es obligatorio.',
                'min_length' => 'El nombre debe tener al menos 2 caracteres.',
            ],
            'email' => [
                'required'    => 'El correo electrónico es obligatorio.',
                'valid_email' => 'El correo electrónico no es válido.',
                'is_unique'   => 'Este correo electrónico ya está registrado.',
            ],
            'password' => [
                'required'   => 'La contraseña es obligatoria.',
                'min_length' => 'La contraseña debe tener al menos 8 caracteres.',
            ],
            'password_confirm' => [
                'required' => 'Debes confirmar la contraseña.',
                'matches'  => 'Las contraseñas no coinciden.',
            ],
            'role' => [
                'required' => 'El rol es obligatorio.',
                'in_list'  => 'El rol seleccionado no es válido.',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name'     => trim((string) $this->request->getPost('name')),
            'email'    => trim((string) $this->request->getPost('email')),
            'password' => (string) $this->request->getPost('password'),
            'role'     => (string) $this->request->getPost('role'),
        ];

        $userId = $this->userModel->insert($data);

        if (! $userId) {
            return redirect()->back()->withInput()->with('errors', $this->userModel->errors());
        }

        AuditLogModel::log('user_created', "Usuario creado: {$data['email']} ({$data['role']})", (int) session()->get('user_id'));

        return redirect()->to('/users')->with('success', 'Usuario creado correctamente.');
    }

    /**
     * Formulario de edición de un usuario existente.
     */
    public function edit(int $id): string|RedirectResponse
    {
        $user = $this->userModel->find($id);

        if (! $user) {
            return redirect()->to('/users')->with('error', 'Usuario no encontrado.');
        }

        $data = [
            'title' => 'Editar Usuario',
            'user'  => $user,
        ];

        return view('template/header', $data)
            . view('users/edit', $data)
            . view('template/footer', $data);
    }

    /**
     * Actualiza los datos de un usuario.
     */
    public function update(int $id): RedirectResponse
    {
        $user = $this->userModel->find($id);

        if (! $user) {
            return redirect()->to('/users')->with('error', 'Usuario no encontrado.');
        }

        $currentUserId = (int) session()->get('user_id');

        $rules = [
            'name'             => 'required|min_length[2]|max_length[100]',
            'email'            => "required|valid_email|is_unique[users.email,id,{$id}]",
            'role'             => 'required|in_list[admin,user]',
            'password'         => 'permit_empty|min_length[8]',
            'password_confirm' => 'permit_empty|matches[password]',
        ];

        $messages = [
            'name' => [
                'required'   => 'El nombre completo es obligatorio.',
                'min_length' => 'El nombre debe tener al menos 2 caracteres.',
            ],
            'email' => [
                'required'    => 'El correo electrónico es obligatorio.',
                'valid_email' => 'El correo electrónico no es válido.',
                'is_unique'   => 'Este correo electrónico ya está en uso por otro usuario.',
            ],
            'password' => [
                'min_length' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            ],
            'password_confirm' => [
                'matches' => 'Las contraseñas no coinciden.',
            ],
            'role' => [
                'required' => 'El rol es obligatorio.',
                'in_list'  => 'El rol seleccionado no es válido.',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $newRole = (string) $this->request->getPost('role');

        // Seguridad: Un admin no puede quitarse su propio rol de admin si es el único
        if ($currentUserId === $id && $newRole !== 'admin') {
            return redirect()->back()->withInput()->with('error', 'No puedes degradar tu propia cuenta de administrador.');
        }

        $data = [
            'name'  => trim((string) $this->request->getPost('name')),
            'email' => trim((string) $this->request->getPost('email')),
            'role'  => $newRole,
        ];

        $password = (string) $this->request->getPost('password');
        if (! empty($password)) {
            $data['password'] = $password;
        }

        $this->userModel->setValidationRule(
            'email',
            "required|valid_email|is_unique[users.email,id,{$id}]"
        );

        if (! $this->userModel->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->userModel->errors());
        }

        // Si el usuario editado es el actual, refrescar sesión
        if ($currentUserId === $id) {
            session()->set([
                'user_name'  => $data['name'],
                'user_email' => $data['email'],
                'user_role'  => $data['role'],
            ]);
        }

        AuditLogModel::log('user_updated', "Usuario actualizado: {$data['email']} (ID: {$id})", $currentUserId);

        return redirect()->to('/users')->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Elimina un usuario (no puede eliminarse a sí mismo).
     */
    public function delete(int $id): RedirectResponse
    {
        $currentUserId = (int) session()->get('user_id');

        if ($currentUserId === $id) {
            return redirect()->to('/users')->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $user = $this->userModel->find($id);

        if (! $user) {
            return redirect()->to('/users')->with('error', 'Usuario no encontrado.');
        }

        // Verificar que no sea el último admin
        if ($user['role'] === 'admin') {
            $adminCount = $this->userModel->where('role', 'admin')->countAllResults();
            if ($adminCount <= 1) {
                return redirect()->to('/users')->with('error', 'No es posible eliminar al único administrador del sistema.');
            }
        }

        $this->userModel->delete($id);

        AuditLogModel::log('user_deleted', "Usuario eliminado: {$user['email']} (ID: {$id})", $currentUserId);

        return redirect()->to('/users')->with('success', "El usuario {$user['email']} ha sido eliminado correctamente.");
    }
}
