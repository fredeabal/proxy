<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminFilter implements FilterInterface
{
    /**
     * Verifica que el usuario tenga rol 'admin'.
     * Si no está autenticado, AuthFilter se encarga; si está autenticado pero con rol 'user',
     * se le restringe el acceso a cualquier sección administrativa.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('is_logged_in')) {
            $session->set('redirect_url', current_url());
            return redirect()->to('/login')->with('error', 'Debes iniciar sesión para acceder.');
        }

        if ($session->get('user_role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Acceso denegado: esta sección está reservada exclusivamente a administradores.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after request
    }
}

