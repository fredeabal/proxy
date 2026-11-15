<?php

namespace App\Validation;

class ProxyHostRules
{
    /**
     * Lista blanca inmutable de cabeceras HTTP permitidas (Fase 6).
     * Previene la inyección de directivas arbitrarias de Nginx o cabeceras peligrosas.
     *
     * @var array<string, array{name: string, directive: string}>
     */
    public const ALLOWED_CUSTOM_HEADERS = [
        // Cabeceras de Seguridad y HSTS (add_header)
        'strict-transport-security'         => ['name' => 'Strict-Transport-Security', 'directive' => 'add_header'],
        'hsts'                              => ['name' => 'Strict-Transport-Security', 'directive' => 'add_header'],
        'x-frame-options'                   => ['name' => 'X-Frame-Options', 'directive' => 'add_header'],
        'x-content-type-options'            => ['name' => 'X-Content-Type-Options', 'directive' => 'add_header'],
        'x-xss-protection'                  => ['name' => 'X-XSS-Protection', 'directive' => 'add_header'],
        'referrer-policy'                   => ['name' => 'Referrer-Policy', 'directive' => 'add_header'],
        'permissions-policy'                => ['name' => 'Permissions-Policy', 'directive' => 'add_header'],
        'content-security-policy'           => ['name' => 'Content-Security-Policy', 'directive' => 'add_header'],
        'cross-origin-opener-policy'        => ['name' => 'Cross-Origin-Opener-Policy', 'directive' => 'add_header'],
        'cross-origin-embedder-policy'      => ['name' => 'Cross-Origin-Embedder-Policy', 'directive' => 'add_header'],
        'cross-origin-resource-policy'      => ['name' => 'Cross-Origin-Resource-Policy', 'directive' => 'add_header'],
        'x-permitted-cross-domain-policies' => ['name' => 'X-Permitted-Cross-Domain-Policies', 'directive' => 'add_header'],
        'x-download-options'                => ['name' => 'X-Download-Options', 'directive' => 'add_header'],
        'x-robots-tag'                      => ['name' => 'X-Robots-Tag', 'directive' => 'add_header'],

        // Cabeceras CORS y Caché (add_header)
        'access-control-allow-origin'       => ['name' => 'Access-Control-Allow-Origin', 'directive' => 'add_header'],
        'access-control-allow-methods'      => ['name' => 'Access-Control-Allow-Methods', 'directive' => 'add_header'],
        'access-control-allow-headers'      => ['name' => 'Access-Control-Allow-Headers', 'directive' => 'add_header'],
        'access-control-allow-credentials'  => ['name' => 'Access-Control-Allow-Credentials', 'directive' => 'add_header'],
        'access-control-expose-headers'     => ['name' => 'Access-Control-Expose-Headers', 'directive' => 'add_header'],
        'access-control-max-age'            => ['name' => 'Access-Control-Max-Age', 'directive' => 'add_header'],
        'cache-control'                     => ['name' => 'Cache-Control', 'directive' => 'add_header'],
        'x-custom-header'                   => ['name' => 'X-Custom-Header', 'directive' => 'add_header'],
        'x-custom'                          => ['name' => 'X-Custom', 'directive' => 'add_header'],
        'x-request-id'                      => ['name' => 'X-Request-ID', 'directive' => 'add_header'],

        // Cabeceras de Proxy / Upstream hacia el Backend (proxy_set_header)
        'x-forwarded-for'                   => ['name' => 'X-Forwarded-For', 'directive' => 'proxy_set_header'],
        'x-real-ip'                         => ['name' => 'X-Real-IP', 'directive' => 'proxy_set_header'],
        'x-forwarded-proto'                 => ['name' => 'X-Forwarded-Proto', 'directive' => 'proxy_set_header'],
        'x-forwarded-host'                  => ['name' => 'X-Forwarded-Host', 'directive' => 'proxy_set_header'],
        'x-forwarded-port'                  => ['name' => 'X-Forwarded-Port', 'directive' => 'proxy_set_header'],
        'x-original-url'                    => ['name' => 'X-Original-URL', 'directive' => 'proxy_set_header'],
        'x-forwarded-user'                  => ['name' => 'X-Forwarded-User', 'directive' => 'proxy_set_header'],
    ];

    /**
     * Variables seguras de Nginx permitidas únicamente en cabeceras de tipo proxy_set_header.
     */
    public const SAFE_PROXY_VARIABLES = [
        '$remote_addr',
        '$proxy_add_x_forwarded_for',
        '$scheme',
        '$host',
        '$server_port',
        '$request_uri',
        '$http_upgrade',
    ];

    /**
     * Valida que un dominio cumpla con la RFC 1123 (opcionalmente con prefijo wildcard '*.')
     * y rechace cualquier carácter potencialmente peligroso para directivas de Nginx.
     */
    public function valid_rfc1123_domain(string $domain, ?string &$error = null): bool
    {
        $domain = trim($domain);

        if (empty($domain)) {
            $error = 'El dominio no puede estar vacío.';
            return false;
        }

        // Rechazar caracteres peligrosos o inyecciones CRLF / Nginx
        if (preg_match('/[\s;{}"\'\\\\#\$]/', $domain)) {
            $error = 'El dominio contiene caracteres no válidos o peligrosos.';
            return false;
        }

        // Longitud máxima de FQDN según RFC 1035 / RFC 1123
        if (strlen($domain) > 253) {
            $error = 'El dominio excede la longitud máxima permitida (253 caracteres).';
            return false;
        }

        // Validar formato estándar o wildcard (ej. *.midominio.com o midominio.com)
        $pattern = '/^(?:\*\.)?(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z0-9-]{2,63}$/';
        if (!preg_match($pattern, $domain)) {
            $error = 'El dominio no tiene un formato RFC 1123 válido (ej. app.empresa.com o *.empresa.com).';
            return false;
        }

        return true;
    }

    /**
     * Valida la lista de aliases o dominios secundarios (separados por coma, espacio o salto de línea).
     */
    public function valid_domain_aliases(?string $aliases, ?string &$error = null, array $data = []): bool
    {
        if ($aliases === null || trim($aliases) === '') {
            return true;
        }

        $list = preg_split('/[\s,]+/', trim($aliases), -1, PREG_SPLIT_NO_EMPTY);
        $primaryDomain = strtolower(trim($data['domain'] ?? ''));
        $seen = [];

        foreach ($list as $alias) {
            $aliasClean = strtolower(trim($alias));

            if (!$this->valid_rfc1123_domain($aliasClean, $aliasError)) {
                $error = "Alias inválido [{$alias}]: {$aliasError}";
                return false;
            }

            if ($primaryDomain !== '' && $aliasClean === $primaryDomain) {
                $error = "El alias [{$alias}] no puede ser idéntico al dominio principal.";
                return false;
            }

            if (isset($seen[$aliasClean])) {
                $error = "El alias [{$alias}] está duplicado en la lista.";
                return false;
            }

            $seen[$aliasClean] = true;
        }

        return true;
    }

    /**
     * Valida el host o IP de destino y previene ataques SSRF hacia servicios de metadatos de Cloud.
     */
    public function valid_forward_host(string $host, ?string &$error = null): bool
    {
        $host = trim($host);

        if (empty($host)) {
            $error = 'El host de destino es obligatorio.';
            return false;
        }

        // Rechazar caracteres prohibidos (inyección en upstream/proxy_pass)
        if (preg_match('/[\s;{}"\'\\\\#\$]/', $host)) {
            $error = 'El host de destino contiene caracteres no válidos.';
            return false;
        }

        // Detección SSRF: Bloquear rangos de metadatos cloud (169.254.0.0/16), broadcast, 0.0.0.0 e IPv6 link-local/mapped
        $hostLower = strtolower($host);
        if (
            in_array($hostLower, ['0.0.0.0', '255.255.255.255', '::', '::0'], true)
            || str_starts_with($hostLower, 'fe80:')
            || str_contains($hostLower, '169.254.')
        ) {
            $error = 'Destino bloqueado por seguridad: Dirección de red reservada, enlace local o metadatos cloud no permitida.';
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ipLong = ip2long($host);
            // 169.254.0.0/16 en formato entero
            $linkLocalStart = ip2long('169.254.0.0');
            $linkLocalEnd   = ip2long('169.254.255.255');

            if ($ipLong !== false && $ipLong >= $linkLocalStart && $ipLong <= $linkLocalEnd) {
                $error = 'Destino bloqueado por seguridad: Las direcciones de enlace local/metadatos cloud (169.254.x.x) no están permitidas.';
                return false;
            }

            // Destino IPv4 o IPv6 válido
            return true;
        }

        // Bloquear metadatos cloud por hostname
        $blockedHosts = [
            'metadata.google.internal',
            'metadata.internal',
            'instance-data',
        ];
        if (in_array(strtolower($host), $blockedHosts, true)) {
            $error = 'Destino bloqueado por seguridad: Host de metadatos cloud restringido.';
            return false;
        }

        // Validar formato de hostname (permite hostnames simples como "backend", "web_app", "localhost", o FQDN)
        $hostPattern = '/^[a-zA-Z0-9](?:[a-zA-Z0-9\-_]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9\-_]{0,61}[a-zA-Z0-9])?)*$/';
        if (!preg_match($hostPattern, $host)) {
            $error = 'El host de destino no es una IP o un nombre de host válido.';
            return false;
        }

        return true;
    }

    /**
     * Valida las cabeceras HTTP personalizadas contra la lista blanca inmutable
     * y bloquea cualquier intento de inyectar bloques o directivas libres de Nginx.
     */
    public function valid_custom_headers(?string $headers, ?string &$error = null): bool
    {
        $parsed = self::parseAndValidateHeaders((string) $headers);
        if (!$parsed['valid']) {
            $error = $parsed['error'];
            return false;
        }

        return true;
    }

    /**
     * Analiza, valida contra lista blanca y normaliza las cabeceras personalizadas.
     *
     * @param string $raw Texto multilínea con formato "Cabecera: Valor"
     * @return array{
     *     valid: bool,
     *     error: ?string,
     *     normalized: ?string,
     *     headers: list<array{name: string, value: string, directive: string}>
     * }
     */
    public static function parseAndValidateHeaders(string $raw): array
    {
        $clean = trim($raw);
        if ($clean === '') {
            return [
                'valid'      => true,
                'error'      => null,
                'normalized' => null,
                'headers'    => [],
            ];
        }

        $lines = preg_split('/\r\n|\r|\n/', $clean);
        $normalizedLines = [];
        $parsedHeaders   = [];

        foreach ($lines as $idx => $line) {
            $lineNum = $idx + 1;
            $line    = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // 1. Prohibir caracteres de control, bloques, comillas dobles, backslashes o backticks
            if (preg_match('/[\x00-\x1F\x7F{}"\\\\`]/', $line)) {
                return [
                    'valid'      => false,
                    'error'      => "Línea {$lineNum}: contiene caracteres prohibidos por seguridad ({, }, \", \\, `).",
                    'normalized' => null,
                    'headers'    => [],
                ];
            }

            // 2. Exigir formato "Cabecera: Valor" (prohíbe directivas libres de Nginx como 'return 200;' o 'include ...')
            if (!preg_match('/^([a-zA-Z0-9\-_]+)\s*:\s*(.+)$/', $line, $matches)) {
                return [
                    'valid'      => false,
                    'error'      => "Línea {$lineNum}: formato inválido. No se permiten directivas libres de Nginx; usa 'Cabecera: Valor'.",
                    'normalized' => null,
                    'headers'    => [],
                ];
            }

            $rawName  = trim($matches[1]);
            $rawValue = trim($matches[2]);
            $keyLower = strtolower($rawName);

            // 3. Verificar contra lista blanca inmutable
            if (!isset(self::ALLOWED_CUSTOM_HEADERS[$keyLower])) {
                return [
                    'valid'      => false,
                    'error'      => "La cabecera \"{$rawName}\" no está permitida en la lista blanca de seguridad.",
                    'normalized' => null,
                    'headers'    => [],
                ];
            }

            // 4. Prohibir punto y coma al final de línea o seguido de directivas de Nginx
            if (str_ends_with($rawValue, ';') || preg_match('/;\s*(return|rewrite|proxy_pass|include|root|alias|set|lua_|perl_|add_header|proxy_set_header|location|server|error_page)\b/i', $rawValue)) {
                return [
                    'valid'      => false,
                    'error'      => "Línea {$lineNum}: el valor de \"{$rawName}\" contiene patrones de inyección de directivas Nginx.",
                    'normalized' => null,
                    'headers'    => [],
                ];
            }

            $meta = self::ALLOWED_CUSTOM_HEADERS[$keyLower];

            // 5. Controlar variables de Nginx ($): prohibidas salvo variables seguras en proxy_set_header
            if (str_contains($rawValue, '$')) {
                if ($meta['directive'] !== 'proxy_set_header' || !in_array($rawValue, self::SAFE_PROXY_VARIABLES, true)) {
                    return [
                        'valid'      => false,
                        'error'      => "Línea {$lineNum}: no se permite el uso de variables arbitrarias ($) en la cabecera \"{$meta['name']}\".",
                        'normalized' => null,
                        'headers'    => [],
                    ];
                }
            }

            $canonicalName     = $meta['name'];
            $normalizedLines[] = "{$canonicalName}: {$rawValue}";
            $parsedHeaders[]   = [
                'name'      => $canonicalName,
                'value'     => $rawValue,
                'directive' => $meta['directive'],
            ];
        }

        return [
            'valid'      => true,
            'error'      => null,
            'normalized' => !empty($normalizedLines) ? implode("\n", $normalizedLines) : null,
            'headers'    => $parsedHeaders,
        ];
    }
}

