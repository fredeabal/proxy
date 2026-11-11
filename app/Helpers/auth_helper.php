<?php

if (! function_exists('current_user_avatar')) {
    /**
     * Obtiene la URL del avatar del usuario en sesión o el avatar por defecto.
     */
    function current_user_avatar(?string $avatar = null): string
    {
        $session = session();
        $userAvatar = $avatar ?? ($session ? $session->get('user_avatar') : null);

        if (! empty($userAvatar) && file_exists(FCPATH . 'uploads/profiles/' . $userAvatar)) {
            return base_url('uploads/profiles/' . esc($userAvatar));
        }

        return base_url('assets/images/profile/default-avatar.png');
    }
}

if (! function_exists('sanitize_svg_file')) {
    /**
     * Sanitiza un archivo SVG para prevenir vulnerabilidades XSS y XXE,
     * y garantiza atributos de renderizado para visualización en elementos <img>.
     */
    function sanitize_svg_file(string $filePath): bool
    {
        if (! file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return false;
        }

        // Eliminar directivas DOCTYPE e ENTITY (prevención de XXE)
        $content = preg_replace('/<!DOCTYPE[^>]*>/is', '', $content);
        $content = preg_replace('/<!ENTITY[^>]*>/is', '', $content);

        // Eliminar etiquetas <script> y sus contenidos
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);

        // Eliminar atributos de evento JS (onload, onerror, onclick, etc.)
        $content = preg_replace('/\s+on[a-z]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/is', '', $content);

        // Neutralizar URLs con pseudo-protocolo javascript: o data: text/html
        $content = preg_replace('/href\s*=\s*(["\'])\s*(javascript:|data:text\/html)[^"\']*\1/is', 'href="#"', $content);

        // Asegurar que la etiqueta raíz <svg> tenga viewBox o dimensiones para evitar que colapse en 0x0px
        if (preg_match('/<svg\b([^>]*)>/is', $content, $matches)) {
            $svgAttrs = $matches[1];
            if (! preg_match('/viewBox\s*=/i', $svgAttrs) && ! (preg_match('/width\s*=/i', $svgAttrs) && preg_match('/height\s*=/i', $svgAttrs))) {
                $content = preg_replace('/<svg\b/i', '<svg viewBox="0 0 100 100"', $content, 1);
            }
        }

        return file_put_contents($filePath, $content) !== false;
    }
}

