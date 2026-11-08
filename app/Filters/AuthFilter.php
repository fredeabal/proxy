<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Comprueba si el usuario tiene una sesión válida iniciada.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('is_logged_in')) {
            // Guardar la URL prevista para redirigir tras login exitoso
            $session->set('redirect_url', current_url());

            return redirect()->to('/login')->with('error', 'Debes iniciar sesión para acceder al panel.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after request
    }
}
