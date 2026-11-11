<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\BannedIpModel;
use App\Models\ProxyHostModel;
use App\Services\NginxManager;
use App\Services\SystemMonitor;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\BackupManager;
use App\Services\TotpService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

class AdminController extends BaseController
{
    protected SettingModel $settingModel;
    protected UserModel $userModel;
    protected TotpService $totpService;
    protected BackupManager $backupManager;
    protected AuditLogModel $auditModel;
    protected BannedIpModel $banModel;

    public function __construct()
    {
        $this->settingModel  = new SettingModel();
        $this->userModel     = new UserModel();
        $this->totpService   = new TotpService();
        $this->backupManager = new BackupManager();
        $this->auditModel    = new AuditLogModel();
        $this->banModel      = new BannedIpModel();
    }

    // ---------------------------------------------------------------------
    // Vista unificada de Administración (Pestañas: Seguridad/2FA, IPs Bloqueadas, SMTP, Backups, Auditoría)
    // ---------------------------------------------------------------------
    public function index(): string
    {
        $tab = trim((string) ($this->request->getGet('tab') ?: 'security'));
        if (!in_array($tab, ['security', 'bans', 'smtp', 'backups', 'audit', 'logs'], true)) {
            $tab = 'security';
        }

        // 1. Datos de Seguridad y Ajustes Generales
        $defaultEmail = (string) $this->settingModel->getVal('default_ssl_email', '');

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

        // 2. Datos SMTP
        $smtpSettings = [
            'smtp_host'       => (string) $this->settingModel->getVal('smtp_host', ''),
            'smtp_port'       => (string) $this->settingModel->getVal('smtp_port', '587'),
            'smtp_user'       => (string) $this->settingModel->getVal('smtp_user', ''),
            'smtp_pass'       => (string) $this->settingModel->getVal('smtp_pass', ''),
            'smtp_crypto'     => (string) $this->settingModel->getVal('smtp_crypto', 'tls'),
            'smtp_from_email' => (string) $this->settingModel->getVal('smtp_from_email', ''),
            'smtp_from_name'  => (string) $this->settingModel->getVal('smtp_from_name', 'Proxy Manager'),
        ];

        // 3. Datos Backups
        $backups        = $this->backupManager->listBackups();
        $retentionCount = $this->backupManager->getRetentionCount();
        $totalSize      = array_sum(array_column($backups, 'size'));

        // 4. Datos Auditoría
        $auditLogs = $this->auditModel->getLogsWithUsers(200);

        // 5. Datos Logs Nginx y monitorización
        $monitor      = new SystemMonitor();
        $logType      = strtolower(trim((string) ($this->request->getGet('type') ?: 'access')));
        $domainFilter = trim((string) ($this->request->getGet('domain') ?: ''));
        if (!in_array($logType, ['access', 'error'], true)) {
            $logType = 'access';
        }
        try {
            $nginxLog = $monitor->readNginxLog($logType, $domainFilter !== '' ? $domainFilter : null, 150);
        } catch (InvalidArgumentException $e) {
            $domainFilter = '';
            $nginxLog = [
                'file'      => $logType . '.log',
                'lines'     => ['[Error de seguridad] ' . $e->getMessage()],
                'simulated' => false,
            ];
        }

        $activeMenu = 'admin';
        $bannedIps  = $this->banModel->getActiveBans();

        // 7. Configuración GeoIP2 (MaxMind GeoLite2)
        $geoipEnabled   = (string) $this->settingModel->getVal('geoip_enabled', '0') === '1';
        $geoipMode      = (string) $this->settingModel->getVal('geoip_mode', 'blocklist');
        $geoipCountriesRaw = (string) $this->settingModel->getVal('geoip_countries', '');
        $geoipCountries = $geoipCountriesRaw !== '' ? json_decode($geoipCountriesRaw, true) : [];
        if (!is_array($geoipCountries)) {
            $geoipCountries = [];
        }
        $nginxCfg = config('Nginx');
        $geoipDbExists = is_file($nginxCfg->geoipDatabasePath);

        $data = [
            'title'            => 'Administración',
            'activeMenu'       => $activeMenu,
            'currentTab'       => $tab,
            'defaultEmail'     => $defaultEmail,
            'twoFactorEnabled' => $twoFactorEnabled,
            'pendingSecret'    => $pendingSecret,
            'totpUri'          => $totpUri,
            'totpQrCode'       => $totpQrCode,
            'smtpSettings'     => $smtpSettings,
            'backups'          => $backups,
            'retentionCount'   => $retentionCount,
            'totalBackups'     => count($backups),
            'totalSizeHuman'   => $this->backupManager->formatBytes((int) $totalSize),
            'auditLogs'        => $auditLogs,
            'bannedIps'        => $bannedIps,
            'geoipEnabled'     => $geoipEnabled,
            'geoipMode'        => $geoipMode,
            'geoipCountries'   => $geoipCountries,
            'geoipDbExists'    => $geoipDbExists,
            'geoipDbPath'      => $nginxCfg->geoipDatabasePath,
            'currentVisitorIp' => $this->request->getIPAddress(),
            'nginxStatus'      => $monitor->getNginxStatus(),
            'cpuLoad'          => $monitor->getCpuLoad(),
            'memory'           => $monitor->getMemoryUsage(),
            'disk'             => $monitor->getDiskUsage(),
            'uptime'           => $monitor->getUptime(),
            'proxies'          => (new ProxyHostModel())->orderBy('domain', 'ASC')->findAll(),
            'nginxLog'         => $nginxLog,
            'selectedType'     => $logType,
            'selectedHost'     => $domainFilter,
            'extraScripts'     => ['assets/js/backups-manager.js', 'assets/js/logs-monitor.js'],
        ];

        return view('template/header', $data)
            . view('admin/index', $data)
            . view('template/footer', $data);
    }

    // ---------------------------------------------------------------------
    // Guarda los ajustes generales (email Let's Encrypt)
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
            return redirect()->to(base_url('admin?tab=security'))
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $email = trim((string) $this->request->getPost('default_ssl_email'));
        $this->settingModel->setVal('default_ssl_email', $email !== '' ? $email : null);

        AuditLogModel::log(
            'admin_general_update',
            "Actualizada configuración general" . ($email !== '' ? " (email SSL: {$email})" : '')
        );

        return redirect()->to(base_url('admin?tab=security'))
            ->with('success', 'Ajustes generales guardados correctamente.');
    }

    // ---------------------------------------------------------------------
    // Guarda la configuración SMTP
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
            return redirect()->to(base_url('admin?tab=smtp'))
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

        $pass = (string) $this->request->getPost('smtp_pass');
        if ($pass !== '') {
            $fields['smtp_pass'] = $pass;
        }

        foreach ($fields as $key => $val) {
            $this->settingModel->setVal($key, $val !== '' ? $val : null);
        }

        AuditLogModel::log('admin_smtp_update', 'Actualizada configuración del servidor SMTP');

        return redirect()->to(base_url('admin?tab=smtp'))
            ->with('success', 'Configuración SMTP guardada correctamente.');
    }

    // ---------------------------------------------------------------------
    // Envía correo de prueba SMTP
    // ---------------------------------------------------------------------
    public function testSmtp(): ResponseInterface
    {
        $testEmail = trim((string) $this->request->getPost('test_email'));
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            return redirect()->to(base_url('admin?tab=smtp'))
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
            return redirect()->to(base_url('admin?tab=smtp'))
                ->with('error', 'Primero debes configurar el servidor SMTP (Host).');
        }

        // Mitigación SSRF: Bloquear hostnames de metadatos cloud en prueba SMTP
        $lowerHost = strtolower($host);
        if (in_array($lowerHost, ['169.254.169.254', 'metadata.google.internal', 'metadata.internal', 'instance-data'], true)) {
            return redirect()->to(base_url('admin?tab=smtp'))
                ->with('error', 'Host SMTP no permitido por políticas de seguridad.');
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
            return redirect()->to(base_url('admin?tab=smtp'))
                ->with('error', 'Error al enviar el correo SMTP: ' . substr($debugger, 0, 200));
        }

        AuditLogModel::log('admin_smtp_test', "Correo de prueba SMTP enviado a {$testEmail}");

        return redirect()->to(base_url('admin?tab=smtp'))
            ->with('success', "Correo de prueba enviado con éxito a {$testEmail}.");
    }

    // ---------------------------------------------------------------------
    // 2FA / TOTP
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
            return redirect()->to(base_url('admin?tab=security'))
                ->with('error', 'El código TOTP de 6 dígitos introducido no es válido. Verifica la hora de tu dispositivo.');
        }

        $this->userModel->update($userId, ['two_factor_secret' => $secret]);
        session()->remove('pending_totp_secret');

        AuditLogModel::log('2fa_enabled', "Activada autenticación en dos pasos (2FA TOTP) para {$user['email']}");

        return redirect()->to(base_url('admin?tab=security'))
            ->with('success', 'Autenticación en dos pasos (2FA / TOTP) activada correctamente.');
    }

    public function disable2fa(): ResponseInterface
    {
        $userId = (int) session()->get('user_id');
        $user   = $this->userModel->find($userId);

        if (! $user) {
            return redirect()->to(base_url('login'));
        }

        $this->userModel->update($userId, ['two_factor_secret' => null]);

        AuditLogModel::log('2fa_disabled', "Desactivada autenticación en dos pasos (2FA TOTP) para {$user['email']}");

        return redirect()->to(base_url('admin?tab=security'))
            ->with('success', 'Autenticación en dos pasos (2FA) desactivada.');
    }

    // ---------------------------------------------------------------------
    // Backups
    // ---------------------------------------------------------------------
    public function createBackup(): ResponseInterface
    {
        $result = $this->backupManager->createBackup();

        if (!$result['success']) {
            return redirect()->to(base_url('admin?tab=backups'))
                ->with('error', 'No se pudo generar la copia de seguridad: ' . ($result['error'] ?? 'Error desconocido.'));
        }

        return redirect()->to(base_url('admin?tab=backups'))
            ->with('success', "Copia de seguridad \"{$result['filename']}\" creada correctamente.");
    }

    public function updateRetention(): ResponseInterface
    {
        $rules = [
            'backup_retention_count' => 'required|is_natural_no_zero|less_than_equal_to[100]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->to(base_url('admin?tab=backups'))
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $retention = (int) $this->request->getPost('backup_retention_count');
        $this->backupManager->setRetentionCount($retention);

        AuditLogModel::log('admin_backup_retention_update', "Actualizada retención de copias de seguridad: {$retention}");

        return redirect()->to(base_url('admin?tab=backups'))
            ->with('success', 'Política de retención actualizada correctamente.');
    }

    public function restoreBackup(): ResponseInterface
    {
        $filename = trim((string) $this->request->getPost('filename'));
        if ($filename === '') {
            return redirect()->to(base_url('admin?tab=backups'))
                ->with('error', 'No se especificó el archivo de copia de seguridad a restaurar.');
        }

        $result = $this->backupManager->restoreBackup($filename);

        if (!$result['success']) {
            return redirect()->to(base_url('admin?tab=backups'))
                ->with('error', $result['error'] ?? 'Error al restaurar la copia de seguridad.');
        }

        return redirect()->to(base_url('admin?tab=backups'))
            ->with('success', $result['message'] ?? 'Copia de seguridad restaurada con éxito.');
    }

    public function deleteBackup(): ResponseInterface
    {
        $filename = trim((string) $this->request->getPost('filename'));
        if ($filename === '') {
            return redirect()->to(base_url('admin?tab=backups'))
                ->with('error', 'Nombre de archivo no especificado.');
        }

        try {
            if (!$this->backupManager->deleteBackup($filename)) {
                return redirect()->to(base_url('admin?tab=backups'))
                    ->with('error', 'La copia de seguridad solicitada no existe.');
            }
        } catch (InvalidArgumentException $e) {
            return redirect()->to(base_url('admin?tab=backups'))
                ->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('admin?tab=backups'))
            ->with('success', "Copia de seguridad \"{$filename}\" eliminada.");
    }

    public function downloadBackup(string $filename): ResponseInterface
    {
        try {
            $safePath = $this->backupManager->resolveSafeBackupPath($filename);
            if (!is_file($safePath)) {
                return redirect()->to(base_url('admin?tab=backups'))
                    ->with('error', 'El archivo de copia de seguridad no existe.');
            }

            AuditLogModel::log('backup_download', "Descargada copia de seguridad: {$filename}");

            return $this->response->download($safePath, null);
        } catch (InvalidArgumentException $e) {
            return redirect()->to(base_url('admin?tab=backups'))
                ->with('error', $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------
    // Gestión de IPs Bloqueadas (Fail2ban Interno + Cloudflare Real-IP)
    // ---------------------------------------------------------------------
    public function banIp(): ResponseInterface
    {
        $ip       = trim((string) $this->request->getPost('ip_address'));
        $reason   = trim((string) $this->request->getPost('reason'));
        $duration = (int) ($this->request->getPost('duration') ?? 0);

        if ($ip === '' || ! BannedIpModel::isValidIpOrCidr($ip)) {
            return redirect()->to(base_url('admin?tab=bans'))
                ->withInput()
                ->with('error', 'Por favor introduce una dirección IP (IPv4/IPv6) o un rango CIDR válido (ej. 203.0.113.0/24).');
        }

        // Prevenir que el administrador se bloquee a sí mismo o a loopback local (incluyendo dentro de un rango CIDR)
        $myIp = $this->request->getIPAddress();
        if (
            BannedIpModel::ipMatchesTarget($myIp, $ip)
            || BannedIpModel::ipMatchesTarget('127.0.0.1', $ip)
            || BannedIpModel::ipMatchesTarget('::1', $ip)
        ) {
            return redirect()->to(base_url('admin?tab=bans'))
                ->withInput()
                ->with('error', "Por seguridad, no puedes bloquear tu propia dirección IP ({$myIp}) ni un rango CIDR que la incluya.");
        }

        try {
            $this->banModel->banIp(
                $ip,
                $reason !== '' ? $reason : 'Bloqueo manual desde el panel',
                $duration,
                'admin'
            );

            // Sincronizar con Nginx
            (new NginxManager())->syncBannedIps($this->banModel->getActiveBans());

            $durationText = $duration <= 0 ? 'permanentemente' : "durante {$duration} min";
            AuditLogModel::log('ip_banned', "IP bloqueada manualmente {$durationText}: {$ip}");

            return redirect()->to(base_url('admin?tab=bans'))
                ->with('success', "La dirección IP {$ip} ha sido bloqueada {$durationText}.");
        } catch (InvalidArgumentException $e) {
            return redirect()->to(base_url('admin?tab=bans'))
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function unbanIp(int $id): ResponseInterface
    {
        $removed = $this->banModel->unbanById($id);
        if (!$removed) {
            return redirect()->to(base_url('admin?tab=bans'))
                ->with('error', 'El registro de bloqueo solicitado no existe o ya había expirado.');
        }

        // Sincronizar con Nginx
        (new NginxManager())->syncBannedIps($this->banModel->getActiveBans());

        $ip = $removed['ip_address'] ?? (string) $id;
        AuditLogModel::log('ip_unbanned', "IP desbloqueada manualmente: {$ip}");

        return redirect()->to(base_url('admin?tab=bans'))
            ->with('success', "La dirección IP {$ip} ha sido desbloqueada con éxito.");
    }

    /**
     * Guarda la configuración de Bloqueo por País (GeoIP2) y sincroniza con Nginx.
     */
    public function updateGeoIp(): ResponseInterface
    {
        $enabled = $this->request->getPost('geoip_enabled') === '1';
        $mode = $this->request->getPost('geoip_mode') === 'allowlist' ? 'allowlist' : 'blocklist';
        $rawCountries = $this->request->getPost('geoip_countries');
        $countries = [];

        if (is_array($rawCountries)) {
            foreach ($rawCountries as $c) {
                $code = strtoupper(trim((string) $c));
                if (preg_match('/^[A-Z]{2}$/', $code)) {
                    $countries[] = $code;
                }
            }
        }
        $countries = array_values(array_unique($countries));

        // Guardar configuración en Base de Datos
        $this->settingModel->setVal('geoip_enabled', $enabled ? '1' : '0');
        $this->settingModel->setVal('geoip_mode', $mode);
        $this->settingModel->setVal('geoip_countries', json_encode($countries));

        // Sincronizar archivo de configuración en Nginx
        $nginxManager = new NginxManager();
        $syncResult = $nginxManager->syncGeoIp($enabled, $mode, $countries);

        $modeLabel = $mode === 'allowlist' ? 'Lista Blanca (Allowlist)' : 'Lista Negra (Blocklist)';
        $statusLabel = $enabled ? 'activado' : 'desactivado';
        $countryCount = count($countries);

        AuditLogModel::log(
            'geoip_updated',
            "GeoIP2 {$statusLabel}. Modo: {$modeLabel}. Países configurados: {$countryCount}"
        );

        if (!$syncResult['success']) {
            return redirect()->to(base_url('admin?tab=bans'))
                ->with('warning', 'Configuración guardada en BD, pero hubo un aviso al recargar Nginx: ' . ($syncResult['error'] ?? ''));
        }

        return redirect()->to(base_url('admin?tab=bans'))
            ->with('success', "Configuración de GeoIP2 actualizada correctamente ({$statusLabel}).");
    }

    /**
     * Descarga o actualiza la base de datos libre GeoLite2-Country sin requerir License Keys.
     */
    public function downloadGeoIpDb(): ResponseInterface
    {
        $nginxManager = new NginxManager();
        $result = $nginxManager->downloadGeoIpDatabase();

        if (!$result['success']) {
            return redirect()->to(base_url('admin?tab=bans'))
                ->with('error', 'Error al descargar la base de datos GeoIP: ' . ($result['error'] ?? 'desconocido'));
        }

        AuditLogModel::log('geoip_db_updated', 'Base de datos GeoLite2-Country descargada/actualizada con éxito');

        return redirect()->to(base_url('admin?tab=bans'))
            ->with('success', 'Base de datos MaxMind GeoLite2 descargada y lista para su uso.');
    }
}

