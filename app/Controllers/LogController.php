<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\ProxyHostModel;
use App\Services\SystemMonitor;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

class LogController extends BaseController
{
    protected SystemMonitor $systemMonitor;
    protected AuditLogModel $auditModel;
    protected ProxyHostModel $proxyModel;

    public function __construct()
    {
        $this->systemMonitor = new SystemMonitor();
        $this->auditModel    = new AuditLogModel();
        $this->proxyModel    = new ProxyHostModel();
    }

    // ---------------------------------------------------------------------
    // Endpoint JSON para consultar logs de Nginx con filtro por host
    // ---------------------------------------------------------------------
    public function nginxLog(): ResponseInterface
    {
        $type   = strtolower(trim((string) ($this->request->getGet('type') ?: 'access')));
        $domain = trim((string) ($this->request->getGet('domain') ?: ''));
        $file   = trim((string) ($this->request->getGet('file') ?: ''));

        try {
            // Si se solicita un archivo explícito, validar canónicamente contra Path Traversal
            if ($file !== '') {
                $this->systemMonitor->resolveSafeLogPath($file);
            }

            $result = $this->systemMonitor->readNginxLog(
                $type,
                $domain !== '' ? $domain : null,
                150
            );

            return $this->response->setJSON([
                'success'   => true,
                'file'      => $result['file'],
                'lines'     => $result['lines'],
                'simulated' => $result['simulated'],
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}

