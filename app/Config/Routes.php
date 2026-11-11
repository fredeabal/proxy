<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Rutas públicas de autenticación
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::attemptLogin');
$routes->get('login/2fa', 'AuthController::verify2faForm');
$routes->post('login/2fa', 'AuthController::attemptVerify2fa');
$routes->match(['get', 'post'], 'logout', 'AuthController::logout');

// Redirección raíz
$routes->get('/', static function () {
    return redirect()->to(session()->get('is_logged_in') ? '/dashboard' : '/login');
});

// Rutas protegidas bajo el filtro de autenticación 'auth'
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('dashboard', 'DashboardController::index');

    // Fase 3 y 4: Gestión de Proxy Hosts e Integración Nginx
    $routes->get('proxy-hosts', 'ProxyHostController::index');
    $routes->get('proxy-hosts/new', 'ProxyHostController::new');
    $routes->post('proxy-hosts/create', 'ProxyHostController::create');
    $routes->get('proxy-hosts/edit/(:num)', 'ProxyHostController::edit/$1');
    $routes->post('proxy-hosts/update/(:num)', 'ProxyHostController::update/$1');
    $routes->get('proxy-hosts/toggle/(:num)', 'ProxyHostController::toggle/$1');
    $routes->get('proxy-hosts/health-check/(:num)', 'ProxyHostController::healthCheck/$1');
    $routes->post('proxy-hosts/delete/(:num)', 'ProxyHostController::delete/$1');
    $routes->get('proxy-hosts/preview/(:num)', 'ProxyHostController::preview/$1');
    $routes->post('proxy-hosts/renew-ssl/(:num)', 'ProxyHostController::renewSsl/$1');
    $routes->post('proxy-hosts/vhost/(:num)', 'ProxyHostController::updateVhost/$1');
    $routes->post('proxy-hosts/vhost-reset/(:num)', 'ProxyHostController::resetVhost/$1');
    // Gestión de Certificados SSL
    $routes->get('certificates', 'CertificateController::index');
    $routes->post('certificates/renew/(:num)', 'CertificateController::renew/$1');
    $routes->get('certificates/renew-all', 'CertificateController::renewAll');
    $routes->post('certificates/delete/(:num)', 'CertificateController::delete/$1');

    // Perfil del Usuario Autenticado (Disponible para admin y user)
    $routes->get('profile', 'ProfileController::index');
    $routes->post('profile/update', 'ProfileController::update');
    $routes->post('profile/update-avatar', 'ProfileController::updateAvatar');
    $routes->post('profile/delete-avatar', 'ProfileController::deleteAvatar');

    // Rutas exclusivas para Administradores ('admin')
    $routes->group('', ['filter' => 'admin'], static function (RouteCollection $routes) {
        // Gestión de Usuarios
        $routes->get('users', 'UsersController::index');
        $routes->get('users/new', 'UsersController::create');
        $routes->post('users/store', 'UsersController::store');
        $routes->get('users/edit/(:num)', 'UsersController::edit/$1');
        $routes->post('users/update/(:num)', 'UsersController::update/$1');
        $routes->post('users/delete/(:num)', 'UsersController::delete/$1');

        // Fase 7: Logs de Nginx y Monitorización
        $routes->get('logs', static fn() => redirect()->to(base_url('admin?tab=logs')));
        $routes->get('logs/nginx', 'LogController::nginxLog');

        // Administración Unificada (Seguridad/2FA, SMTP, Backups y Auditoría)
        $routes->get('admin', 'AdminController::index');
        $routes->post('admin/general', 'AdminController::updateGeneral');
        $routes->post('admin/smtp', 'AdminController::updateSmtp');
        $routes->post('admin/smtp/test', 'AdminController::testSmtp');
        $routes->post('admin/2fa/enable', 'AdminController::enable2fa');
        $routes->post('admin/2fa/disable', 'AdminController::disable2fa');
        $routes->post('admin/backups/create', 'AdminController::createBackup');
        $routes->post('admin/backups/retention', 'AdminController::updateRetention');
        $routes->post('admin/backups/restore', 'AdminController::restoreBackup');
        $routes->post('admin/backups/delete', 'AdminController::deleteBackup');
        $routes->get('admin/backups/download/(:segment)', 'AdminController::downloadBackup/$1');
        $routes->post('admin/bans/add', 'AdminController::banIp');
        $routes->post('admin/bans/unban/(:num)', 'AdminController::unbanIp/$1');
        $routes->post('admin/bans/geoip', 'AdminController::updateGeoIp');
        $routes->post('admin/bans/geoip-download', 'AdminController::downloadGeoIpDb');

        // Mantenimiento del Sistema (Sesiones, Caché, Logs y BD)
        $routes->post('admin/maintenance/sessions', 'AdminController::clearSessions');
        $routes->post('admin/maintenance/cache', 'AdminController::clearCache');
        $routes->post('admin/maintenance/logs', 'AdminController::clearLogs');
        $routes->post('admin/maintenance/audit', 'AdminController::clearAuditLogs');
        $routes->post('admin/maintenance/optimize-db', 'AdminController::optimizeDatabase');
        $routes->post('admin/maintenance/full', 'AdminController::runFullMaintenance');

        // Redirecciones y compatibilidad legacy (/settings y /backups)
        $routes->get('settings', static fn() => redirect()->to(base_url('admin?tab=security')));
        $routes->post('settings/general', 'AdminController::updateGeneral');
        $routes->post('settings/smtp', 'AdminController::updateSmtp');
        $routes->post('settings/smtp/test', 'AdminController::testSmtp');
        $routes->post('settings/2fa/enable', 'AdminController::enable2fa');
        $routes->post('settings/2fa/disable', 'AdminController::disable2fa');

        $routes->get('backups', static fn() => redirect()->to(base_url('admin?tab=backups')));
        $routes->post('backups/create', 'AdminController::createBackup');
        $routes->post('backups/retention', 'AdminController::updateRetention');
        $routes->post('backups/restore', 'AdminController::restoreBackup');
        $routes->post('backups/delete', 'AdminController::deleteBackup');
        $routes->get('backups/download/(:segment)', 'AdminController::downloadBackup/$1');
    });
});
