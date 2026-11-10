<?php

namespace App\Filters;

use App\Models\BannedIpModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtro global de protección contra IPs bloqueadas (Fail2ban interno + Cloudflare Real-IP).
 */
class IpBanFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $ip = $request->getIPAddress();
        $banModel = new BannedIpModel();
        $ban = $banModel->isBanned($ip);

        if ($ban !== null) {
            $reason = esc((string) ($ban['reason'] ?? 'Actividad sospechosa detectada'));
            $expiryText = ((int) ($ban['is_permanent'] ?? 0) === 1)
                ? 'Bloqueo permanente'
                : 'Expira el ' . esc((string) $ban['expires_at']);
            $safeIp = esc($ip);

            $html = "<!DOCTYPE html><html lang=\"es\"><head><meta charset=\"utf-8\"><title>403 Acceso Bloqueado</title>"
                  . "<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0b1114;color:#e1e7ec;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}"
                  . "div{text-align:center;padding:2.5rem;border:1px solid #374853;border-radius:12px;background:#151c24;max-width:500px;box-shadow:0 10px 25px rgba(0,0,0,0.5)}"
                  . "h1{color:#ef4444;font-size:2rem;margin-bottom:0.5rem;font-weight:700}"
                  . "p{color:#94a3b8;font-size:0.95rem;line-height:1.5;margin:0.5rem 0}"
                  . "code{background:#1e293b;color:#f8fafc;padding:0.2rem 0.5rem;border-radius:6px;font-size:0.85rem}"
                  . "</style></head><body><div>"
                  . "<h1>403 IP Bloqueada</h1>"
                  . "<p>Tu dirección IP (<code>{$safeIp}</code>) ha sido bloqueada por el sistema de protección.</p>"
                  . "<p><strong>Motivo:</strong> {$reason}<br><strong>Estado:</strong> {$expiryText}</p>"
                  . "</div></body></html>";

            return service('response')
                ->setStatusCode(403)
                ->setContentType('text/html')
                ->setBody($html);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

