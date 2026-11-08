<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\CertificateModel;
use App\Models\ProxyHostModel;
use App\Services\SystemMonitor;

class DashboardController extends BaseController
{
    /**
     * Muestra el panel principal del sistema con métricas y resúmenes.
     */
    public function index(): string
    {
        $systemMonitor   = new SystemMonitor();
        $proxyHostModel  = new ProxyHostModel();
        $certModel       = new CertificateModel();
        $auditModel      = new AuditLogModel();

        // Métricas de Proxy Hosts
        $totalProxies  = $proxyHostModel->countAllResults(false);
        $activeProxies = $proxyHostModel->where('enabled', 1)->countAllResults(false);

        // Métricas de Certificados
        $totalCerts = $certModel->countAllResults(false);
        // Próximos a caducar en 15 días
        $in15Days = date('Y-m-d H:i:s', strtotime('+15 days'));
        $expiringCerts = $certModel->where('expires_at <=', $in15Days)->countAllResults(false);

        // Métricas del Servidor
        $nginxStatus = $systemMonitor->getNginxStatus();
        $cpuLoad     = $systemMonitor->getCpuLoad();
        $memory      = $systemMonitor->getMemoryUsage();
        $disk        = $systemMonitor->getDiskUsage();

        // Últimos 5 registros de auditoría
        $recentLogs = $auditModel->orderBy('id', 'DESC')->findAll(5);

        $data = [
            'title'          => 'Dashboard',
            'activeMenu'     => 'dashboard',
            'nginxStatus'    => $nginxStatus,
            'cpuLoad'        => $cpuLoad,
            'memory'         => $memory,
            'disk'           => $disk,
            'totalProxies'   => $totalProxies,
            'activeProxies'  => $activeProxies,
            'totalCerts'     => $totalCerts,
            'expiringCerts'  => $expiringCerts,
            'recentLogs'     => $recentLogs,
        ];

        return view('template/header', $data)
            . view('dashboard/index', $data)
            . view('template/footer');
    }
}

