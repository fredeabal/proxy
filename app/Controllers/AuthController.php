<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\UserModel;
use App\Services\TotpService;
use CodeIgniter\HTTP\RedirectResponse;

class AuthController extends BaseController
{
    /**
     * Muestra el formulario de login.
     */
    public function login(): string|RedirectResponse
    {
        if (session()->get('is_logged_in')) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login');
    }

    /**
     * Procesa la autenticación con protección contra fuerza bruta (Throttler).
     */
    public function attemptLogin(): RedirectResponse
    {
        $session = session();
        $throttler = service('throttler');
        $ip = $this->request->getIPAddress();
        $throttleKey = 'login_' . md5($ip);

        // Límite: 5 intentos por minuto por dirección IP
        if ($throttler->check($throttleKey, 5, MINUTE) === false) {
            AuditLogModel::log('login_blocked', "IP bloqueada por intentos excesivos ({$ip})", null, $ip);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Demasiados intentos fallidos de inicio de sesión. Por motivos de seguridad, espera 1 minuto.');
        }

        // Validación de datos de entrada
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $email    = (string) $this->request->getPost('email');
        $password = (string) $this->request->getPost('password');

        $userModel = new UserModel();
        $user      = $userModel->where('email', $email)->first();

        if (! $user || ! password_verify($password, $user['password'])) {
            AuditLogModel::log('login_failed', "Intento fallido para el correo: {$email}", null, $ip);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Las credenciales proporcionadas no son válidas.');
        }

        // Autenticación exitosa: regenerar ID de sesión para prevenir Session Fixation
        $session->regenerate(true);

        // Fase 9: Si el administrador tiene 2FA (TOTP) activo, solicitar código de segundo factor
        if (! empty($user['two_factor_secret'])) {
            $session->set([
                '2fa_pending_user_id' => (int) $user['id'],
                'is_logged_in'        => false,
            ]);

            AuditLogModel::log('login_2fa_challenge', "Reto 2FA solicitado para ({$user['email']})", (int) $user['id'], $ip);

            return redirect()->to('/login/2fa');
        }

        $session->set([
            'user_id'      => $user['id'],
            'user_name'    => $user['name'],
            'user_email'   => $user['email'],
            'user_role'    => $user['role'],
            'is_logged_in' => true,
        ]);

        AuditLogModel::log('login_success', "Inicio de sesión exitoso ({$user['email']})", $user['id'], $ip);

        $redirectUrl = $session->get('redirect_url') ?? '/dashboard';
        $session->remove('redirect_url');

        return redirect()->to($redirectUrl)->with('success', "¡Bienvenido de nuevo, {$user['name']}!");
    }

    /**
     * Muestra la pantalla de verificación del código TOTP de 6 dígitos (Fase 9).
     */
    public function verify2faForm(): string|RedirectResponse
    {
        $session = session();
        if ($session->get('is_logged_in')) {
            return redirect()->to('/dashboard');
        }

        if (! $session->get('2fa_pending_user_id')) {
            return redirect()->to('/login');
        }

        return view('auth/verify_2fa');
    }

    /**
     * Verifica el código TOTP de 6 dígitos para completar el inicio de sesión (Fase 9).
     */
    public function attemptVerify2fa(): RedirectResponse
    {
        $session = session();
        $pendingUserId = (int) $session->get('2fa_pending_user_id');

        if ($pendingUserId <= 0) {
            return redirect()->to('/login')->with('error', 'La sesión de verificación 2FA ha expirado.');
        }

        $throttler = service('throttler');
        $ip = $this->request->getIPAddress();
        $throttleKey = 'login_2fa_' . md5($ip);

        if ($throttler->check($throttleKey, 5, MINUTE) === false) {
            AuditLogModel::log('login_2fa_blocked', "IP bloqueada por intentos excesivos de 2FA ({$ip})", $pendingUserId, $ip);
            return redirect()->back()->with('error', 'Demasiados intentos de verificación 2FA. Espera 1 minuto.');
        }

        $code = trim((string) $this->request->getPost('totp_code'));
        $userModel = new UserModel();
        $user = $userModel->find($pendingUserId);

        if (! $user || empty($user['two_factor_secret'])) {
            $session->remove('2fa_pending_user_id');
            return redirect()->to('/login')->with('error', 'Usuario no válido.');
        }

        $totp = new TotpService();
        if (! $totp->verifyCode((string) $user['two_factor_secret'], $code)) {
            AuditLogModel::log('login_2fa_failed', "Código 2FA inválido para ({$user['email']})", (int) $user['id'], $ip);
            return redirect()->back()->with('error', 'El código de autenticación de 6 dígitos es incorrecto o ha expirado.');
        }

        // 2FA superado: regenerar sesión y elevar a autenticado completo
        $session->remove('2fa_pending_user_id');
        $session->regenerate(true);

        $session->set([
            'user_id'      => $user['id'],
            'user_name'    => $user['name'],
            'user_email'   => $user['email'],
            'user_role'    => $user['role'],
            'is_logged_in' => true,
        ]);

        AuditLogModel::log('login_2fa_success', "Inicio de sesión con 2FA exitoso ({$user['email']})", (int) $user['id'], $ip);

        $redirectUrl = $session->get('redirect_url') ?? '/dashboard';
        $session->remove('redirect_url');

        return redirect()->to($redirectUrl)->with('success', "¡Bienvenido de nuevo, {$user['name']}!");
    }

    /**
     * Cierra la sesión activa.
     */
    public function logout(): RedirectResponse
    {
        $userId = session()->get('user_id');
        $ip = $this->request->getIPAddress();

        AuditLogModel::log('logout', 'Cierre de sesión', $userId, $ip);

        session()->destroy();

        return redirect()->to('/login')->with('success', 'Has cerrado la sesión correctamente.');
    }
}

