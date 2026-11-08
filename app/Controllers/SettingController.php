<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\TotpService;
use CodeIgniter\HTTP\ResponseInterface;

class SettingController extends BaseController
{
    protected SettingModel $settingModel;
    protected UserModel $userModel;
    protected TotpService $totpService;

    public function __construct()
    {
        $this->settingModel  = new SettingModel();
        $this->userModel     = new UserModel();
        $this->totpService   = new TotpService();
    }

    // ---------------------------------------------------------------------
    // Vista principal de Configuración (Seguridad 2FA, Ajustes Generales, SMTP)
    // ---------------------------------------------------------------------
    public function index(): string
    {
        $defaultEmail = (string) $this->settingModel->getVal('default_ssl_email', '');

        // Ajustes SMTP
        $smtpSettings = [
            'smtp_host'       => (string) $this->settingModel->getVal('smtp_host', ''),
            'smtp_port'       => (string) $this->settingModel->getVal('smtp_port', '587'),
            'smtp_user'       => (string) $this->settingModel->getVal('smtp_user', ''),
            'smtp_pass'       => (string) $this->settingModel->getVal('smtp_pass', ''),
            'smtp_crypto'     => (string) $this->settingModel->getVal('smtp_crypto', 'tls'),
            'smtp_from_email' => (string) $this->settingModel->getVal('smtp_from_email', ''),
            'smtp_from_name'  => (string) $this->settingModel->getVal('smtp_from_name', 'Proxy Manager'),
        ];

        // Estado 2FA (TOTP) del administrador actual (Fase 9)
        $userId = (int) session()->get('user_id');
        $user   = $userId > 0 ? $this->userModel->find($userId) : null;
        $twoFactorEnabled = ! empty($user['two_factor_secret']);
        $pendingSecret    = '';
        $totpUri          = '';
        $totpQrCode       = '';

        if (! $twoFactorEnabled) {
            $pendingSecret = (string) session()->get('pending_totp_secret');
            if ($pendingSecret === '') {
                $pendingSecret = $this->totpService->generateSecret();
                session()->set('pending_totp_secret', $pendingSecret);
            }
            $totpUri = $this->totpService->getProvisioningUri(
                (string) ($user['email'] ?? 'admin@demo.com'),
                $pendingSecret
            );
            $totpQrCode = $this->totpService->getQrCodeDataUri($totpUri);
        }

        $data = [
            'title'            => 'Configuración',
            'activeMenu'       => 'settings',
            'defaultEmail'     => $defaultEmail,
            'smtpSettings'     => $smtpSettings,
            'twoFactorEnabled' => $twoFactorEnabled,
            'pendingSecret'    => $pendingSecret,
            'totpUri'          => $totpUri,
            'totpQrCode'       => $totpQrCode,
        ];

        return view('template/header', $data)
            . view('settings/index', $data)
            . view('template/footer', $data);
    }

    // ---------------------------------------------------------------------
    // Guarda los ajustes generales y de seguridad
    // ---------------------------------------------------------------------
    public function updateGeneral(): ResponseInterface
    {
        $rules = [
            'default_ssl_email' => 'permit_empty|valid_email|max_length[190]',
        ];

        $messages = [
            'default_ssl_email' => [
                'valid_email' => 'El correo por defecto para Let\'s Encrypt no tiene un formato válido.',
            ],
        ];

        if (!$this->validate($rules, $messages)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $email = trim((string) $this->request->getPost('default_ssl_email'));
        $this->settingModel->setVal('default_ssl_email', $email !== '' ? $email : null);

        AuditLogModel::log(
            'settings_general_update',
            "Actualizada configuración general" . ($email !== '' ? " (email SSL: {$email})" : '')
        );

        return redirect()->to(base_url('settings'))
            ->with('success', 'Ajustes generales guardados correctamente.');
    }

    // ---------------------------------------------------------------------
    // Guarda la configuración del servidor de correo SMTP
    // ---------------------------------------------------------------------
    public function updateSmtp(): ResponseInterface
    {
        $rules = [
            'smtp_host'       => 'permit_empty|max_length[253]',
            'smtp_port'       => 'permit_empty|is_natural_no_zero|less_than_equal_to[65535]',
            'smtp_user'       => 'permit_empty|max_length[190]',
            'smtp_crypto'     => 'permit_empty|in_list[tls,ssl,none]',
            'smtp_from_email' => 'permit_empty|valid_email|max_length[190]',
            'smtp_from_name'  => 'permit_empty|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $fields = [
            'smtp_host'       => trim((string) $this->request->getPost('smtp_host')),
            'smtp_port'       => trim((string) $this->request->getPost('smtp_port')),
            'smtp_user'       => trim((string) $this->request->getPost('smtp_user')),
            'smtp_crypto'     => trim((string) $this->request->getPost('smtp_crypto')),
            'smtp_from_email' => trim((string) $this->request->getPost('smtp_from_email')),
            'smtp_from_name'  => trim((string) $this->request->getPost('smtp_from_name')),
        ];

        // Si se especificó contraseña nueva, actualizarla
        $pass = (string) $this->request->getPost('smtp_pass');
        if ($pass !== '') {
            $fields['smtp_pass'] = $pass;
        }

        foreach ($fields as $key => $val) {
            $this->settingModel->setVal($key, $val !== '' ? $val : null);
        }

        AuditLogModel::log('settings_smtp_update', 'Actualizada configuración del servidor SMTP');

        return redirect()->to(base_url('settings'))
            ->with('success', 'Configuración SMTP guardada correctamente.');
    }

    // ---------------------------------------------------------------------
    // Prueba de conexión y envío de correo SMTP
    // ---------------------------------------------------------------------
    public function testSmtp(): ResponseInterface
    {
        $testEmail = trim((string) $this->request->getPost('test_email'));
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            return redirect()->to(base_url('settings'))
                ->with('error', 'Por favor introduce un correo válido para la prueba.');
        }

        $emailService = service('email');

        $host   = (string) $this->settingModel->getVal('smtp_host', '');
        $port   = (int) $this->settingModel->getVal('smtp_port', 587);
        $user   = (string) $this->settingModel->getVal('smtp_user', '');
        $pass   = (string) $this->settingModel->getVal('smtp_pass', '');
        $crypto = (string) $this->settingModel->getVal('smtp_crypto', 'tls');
        $from   = (string) $this->settingModel->getVal('smtp_from_email', 'noreply@localhost');
        $name   = (string) $this->settingModel->getVal('smtp_from_name', 'Proxy Manager');

        if ($host === '') {
            return redirect()->to(base_url('settings'))
                ->with('error', 'Primero debes configurar el servidor SMTP (Host).');
        }

        $config = [
            'protocol'   => 'smtp',
            'SMTPHost'   => $host,
            'SMTPPort'   => $port,
            'SMTPUser'   => $user,
            'SMTPPass'   => $pass,
            'SMTPCrypto' => $crypto === 'none' ? '' : $crypto,
            'mailType'   => 'html',
            'charset'    => 'utf-8',
            'newline'    => "\r\n",
            'CRLF'       => "\r\n",
        ];

        $emailService->initialize($config);
        $emailService->setFrom($from, $name);
        $emailService->setTo($testEmail);
        $emailService->setSubject('Prueba de Conexión SMTP - Proxy Manager');
        $emailService->setMessage('<p>¡Hola!</p><p>Este es un correo de prueba enviado desde <strong>Proxy Manager</strong> confirmando que la configuración SMTP funciona correctamente.</p>');

        if (!@$emailService->send(false)) {
            $debugger = strip_tags($emailService->printDebugger(['headers']));
            return redirect()->to(base_url('settings'))
                ->with('error', 'Error al enviar el correo SMTP: ' . substr($debugger, 0, 200));
        }

        AuditLogModel::log('settings_smtp_test', "Correo de prueba SMTP enviado a {$testEmail}");

        return redirect()->to(base_url('settings'))
            ->with('success', "Correo de prueba enviado con éxito a {$testEmail}.");
    }

    // ---------------------------------------------------------------------
    // Activa la autenticación de dos factores (2FA / TOTP) para el administrador (Fase 9)
    // ---------------------------------------------------------------------
    public function enable2fa(): ResponseInterface
    {
        $userId = (int) session()->get('user_id');
        $user   = $this->userModel->find($userId);

        if (! $user) {
            return redirect()->to(base_url('login'));
        }

        $secret = trim((string) ($this->request->getPost('totp_secret') ?: session()->get('pending_totp_secret')));
        $code   = trim((string) $this->request->getPost('totp_code'));

        if ($secret === '' || ! $this->totpService->verifyCode($secret, $code)) {
            return redirect()->to(base_url('settings'))
                ->with('error', 'El código TOTP de 6 dígitos introducido no es válido. Verifica la hora de tu dispositivo.');
        }

        $this->userModel->update($userId, ['two_factor_secret' => $secret]);
        session()->remove('pending_totp_secret');

        AuditLogModel::log('2fa_enabled', "Activada autenticación en dos pasos (2FA TOTP) para {$user['email']}");

        return redirect()->to(base_url('settings'))
            ->with('success', 'Autenticación en dos pasos (2FA / TOTP) activada correctamente.');
    }

    // ---------------------------------------------------------------------
    // Desactiva la autenticación de dos factores (2FA / TOTP) para el administrador (Fase 9)
    // ---------------------------------------------------------------------
    public function disable2fa(): ResponseInterface
    {
        $userId = (int) session()->get('user_id');
        $user   = $this->userModel->find($userId);

        if (! $user) {
            return redirect()->to(base_url('login'));
        }

        $this->userModel->update($userId, ['two_factor_secret' => null]);

        AuditLogModel::log('2fa_disabled', "Desactivada autenticación en dos pasos (2FA TOTP) para {$user['email']}");

        return redirect()->to(base_url('settings'))
            ->with('success', 'Autenticación en dos pasos (2FA) desactivada.');
    }
}
