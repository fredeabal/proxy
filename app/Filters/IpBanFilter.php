<?php

namespace App\Filters;

use App\Models\BannedIpModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filtro global de protección contra IPs bloqueadas (Fail2ban interno + Cloudflare Real-IP).
 *
 * Modo Stealth (Engaño / Apariencia de no existencia):
 * En lugar de responder con un 403 Forbidden (que delata que el servidor detectó y bloqueó al atacante),
 * lanza una excepción estándar de página no encontrada (404 Not Found), simulando que la URL o el
 * panel no existen en esa dirección.
 */
class IpBanFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $ip = $request->getIPAddress();
        $banModel = new BannedIpModel();
        $ban = $banModel->isBanned($ip);

        if ($ban !== null) {
            // Fingir que la página / servicio no existe (Stealth 404)
            throw PageNotFoundException::forPageNotFound();
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

