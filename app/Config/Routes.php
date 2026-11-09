<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Rutas públicas de autenticación
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::attemptLogin');
$routes->get('login/2fa', 'AuthController::verify2faForm');
$routes->post('login/2fa', 'AuthController::attemptVerify2fa');
$routes->get('logout', 'AuthController::logout');

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
    $routes->post('proxy-hosts/delete/(:num)', 'ProxyHostController::delete/$1');
    $routes->get('proxy-hosts/preview/(:num)', 'ProxyHostController::preview/$1');
    $routes->post('proxy-hosts/renew-ssl/(:num)', 'ProxyHostController::renewSsl/$1');
    $routes->post('proxy-hosts/vhost/(:num)', 'ProxyHostController::updateVhost/$1');
    $routes->post('proxy-hosts/vhost-reset/(:num)', 'ProxyHostController::resetVhost/$1');
    // Redirección y compatibilidad SSL
    $routes->get('certificates', static function () {
        return redirect()->to(base_url('proxy-hosts'));
    });
    $routes->post('certificates/renew/(:num)', 'CertificateController::renew/$1');
    $routes->get('certificates/renew-all', 'CertificateController::renewAll');
    $routes->post('certificates/delete/(:num)', 'CertificateController::delete/$1');

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
