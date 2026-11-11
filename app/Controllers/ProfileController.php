<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class ProfileController extends BaseController
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Muestra la pantalla del perfil del usuario autenticado actual.
     */
    public function index(): string|RedirectResponse
    {
        $userId = (int) session()->get('user_id');
        $user = $this->userModel->find($userId);

        if (! $user) {
            return redirect()->to('/login')->with('error', 'Usuario no encontrado.');
        }

        $data = [
            'title'             => 'Mi Perfil',
            'user'              => $user,
            'twoFactorEnabled'  => ! empty($user['two_factor_secret']),
        ];

        return view('template/header', $data)
            . view('profile/index', $data)
            . view('template/footer', $data);
    }

    /**
     * Actualiza datos personales y contraseña del usuario actual.
     */
    public function update(): RedirectResponse
    {
        $userId = (int) session()->get('user_id');
        $user   = $this->userModel->find($userId);

        if (! $user) {
            return redirect()->to('/login')->with('error', 'Usuario no encontrado.');
        }

        $rules = [
            'name'             => 'required|min_length[2]|max_length[100]',
            'email'            => "required|valid_email|is_unique[users.email,id,{$userId}]",
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
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $name     = trim((string) $this->request->getPost('name'));
        $email    = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');

        $updateData = [
            'name'  => $name,
            'email' => $email,
        ];

        if (! empty($password)) {
            $updateData['password'] = $password;
        }

        $this->userModel->setValidationRule(
            'email',
            "required|valid_email|is_unique[users.email,id,{$userId}]"
        );

        if (! $this->userModel->update($userId, $updateData)) {
            return redirect()->back()->withInput()->with('errors', $this->userModel->errors());
        }

        // Actualizar datos de sesión
        session()->set([
            'user_name'  => $name,
            'user_email' => $email,
        ]);

        AuditLogModel::log('profile_updated', "Perfil actualizado ({$email})", $userId);

        return redirect()->to('/profile')->with('success', 'Tu perfil ha sido actualizado correctamente.');
    }

    /**
     * Sube y actualiza el avatar del usuario.
     */
    public function updateAvatar(): RedirectResponse
    {
        $userId = (int) session()->get('user_id');
        $user   = $this->userModel->find($userId);

        if (! $user) {
            return redirect()->to('/login')->with('error', 'Usuario no encontrado.');
        }

        $file = $this->request->getFile('avatar');

        $validationRule = [
            'avatar' => [
                'rules' => 'uploaded[avatar]|mime_in[avatar,image/jpg,image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/svg]|ext_in[avatar,png,jpg,jpeg,gif,webp,svg]|max_size[avatar,3072]',
                'errors' => [
                    'uploaded' => 'Debes seleccionar una imagen.',
                    'mime_in'  => 'Formato de imagen no permitido (solo JPG, PNG, GIF, WEBP, SVG).',
                    'ext_in'   => 'Extensión de imagen no válida.',
                    'max_size' => 'La imagen no puede exceder los 3 MB.',
                ],
            ],
        ];

        if (! $this->validate($validationRule)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $uploadDir = FCPATH . 'uploads/profiles/';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Eliminar avatar anterior si existe
        if (! empty($user['avatar']) && file_exists($uploadDir . $user['avatar'])) {
            unlink($uploadDir . $user['avatar']);
        }

        $newName = $file->getRandomName();
        $file->move($uploadDir, $newName);
        if (pathinfo($newName, PATHINFO_EXTENSION) === 'svg' || strtolower((string) $file->getClientMimeType()) === 'image/svg+xml') {
            sanitize_svg_file($uploadDir . $newName);
        }

        $this->userModel->skipValidation(true)->update($userId, ['avatar' => $newName]);
        session()->set('user_avatar', $newName);

        AuditLogModel::log('avatar_updated', "Avatar de usuario actualizado ({$user['email']})", $userId);

        return redirect()->to('/profile')->with('success', '¡Foto de perfil actualizada correctamente!');
    }

    /**
     * Elimina el avatar y vuelve al avatar predeterminado.
     */
    public function deleteAvatar(): RedirectResponse
    {
        $userId = (int) session()->get('user_id');
        $user   = $this->userModel->find($userId);

        if (! $user) {
            return redirect()->to('/login')->with('error', 'Usuario no encontrado.');
        }

        if (! empty($user['avatar'])) {
            $filePath = FCPATH . 'uploads/profiles/' . $user['avatar'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $this->userModel->skipValidation(true)->update($userId, ['avatar' => null]);
            session()->set('user_avatar', null);

            AuditLogModel::log('avatar_deleted', "Avatar de usuario eliminado ({$user['email']})", $userId);
        }

        return redirect()->to('/profile')->with('success', 'Foto de perfil eliminada correctamente.');
    }
}
