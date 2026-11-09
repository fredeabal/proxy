<?php

namespace App\Services;

use App\Validation\ProxyHostRules;
use Config\Nginx as NginxConfig;
use InvalidArgumentException;

/**
 * Generador atómico y seguro de configuraciones Nginx para Proxy Hosts.
 * Previene inyección de directivas Nginx mediante sanitización y validación estricta.
 */
class NginxConfigGenerator
{
    protected NginxConfig $config;

    public function __construct(?NginxConfig $config = null)
    {
        $this->config = $config ?? config(NginxConfig::class);
    }

    /**
     * Genera la configuración completa de Nginx para un Proxy Host.
     *
     * @param array $proxy Datos validados del Proxy Host
     * @return string Contenido de la configuración Nginx
     * @throws InvalidArgumentException Si algún dato no supera las verificaciones de seguridad
     */
    public function generate(array $proxy): string
    {
        $id = (int) ($proxy['id'] ?? 0);
        $primaryDomain = strtolower(trim((string) ($proxy['domain'] ?? '')));
        $this->assertSafeDomain($primaryDomain);

        $serverNames = $this->buildServerNames($proxy);
        $scheme = strtolower(trim((string) ($proxy['scheme'] ?? 'http')));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("Esquema inválido: {$scheme}");
        }

        $forwardHost = trim((string) ($proxy['forward_host'] ?? ''));
        $this->assertSafeForwardHost($forwardHost);

        $forwardPort = (int) ($proxy['forward_port'] ?? 80);
        if ($forwardPort < 1 || $forwardPort > 65535) {
            throw new InvalidArgumentException("Puerto de destino fuera de rango: {$forwardPort}");
        }

        $sslEnabled = !empty($proxy['ssl_enabled']);
        $forceHttps = !empty($proxy['force_https']) && $sslEnabled;
        $acmeWebroot = rtrim($this->config->acmeWebroot, '/');

        $lines = [];
        $lines[] = '# ==============================================================================';
        $lines[] = "# Reverse Proxy Manager - Proxy Host [ID: {$id}]";
        $lines[] = "# Dominio: {$primaryDomain}";
        $lines[] = '# Generado automáticamente - NO EDITAR MANUALMENTE';
        $lines[] = '# Fecha: ' . date('Y-m-d H:i:s');
        $lines[] = '# ==============================================================================';
        $lines[] = '';

        if ($sslEnabled) {
            // -------------------------------------------------------------
            // Bloque HTTP (Puerto 80) para SSL
            // -------------------------------------------------------------
            $lines[] = 'server {';
            $lines[] = '    listen 80;';
            $lines[] = '    listen [::]:80;';
            $lines[] = "    server_name {$serverNames};";
            $lines[] = '';
            $lines[] = '    # Reto ACME para renovación de Let\'s Encrypt';
            $lines[] = '    location ^~ /.well-known/acme-challenge/ {';
            $lines[] = '        default_type "text/plain";';
            $lines[] = "        root {$acmeWebroot};";
            $lines[] = '        allow all;';
            $lines[] = '    }';
            $lines[] = '';

            if ($forceHttps) {
                $lines[] = '    # Redirección forzada hacia HTTPS';
                $lines[] = '    location / {';
                $lines[] = '        return 301 https://$host$request_uri;';
                $lines[] = '    }';
            } else {
                $lines[] = $this->renderProxyPassBlock($proxy, 4);
            }

            $lines[] = '}';
            $lines[] = '';

            // -------------------------------------------------------------
            // Bloque HTTPS (Puerto 443)
            // -------------------------------------------------------------
            $certPaths = $this->resolveCertificates($proxy);

            $lines[] = 'server {';
            $lines[] = '    listen 443 ssl;';
            $lines[] = '    listen [::]:443 ssl;';
            $lines[] = "    server_name {$serverNames};";
            $lines[] = '';
            $lines[] = "    ssl_certificate {$certPaths['cert']};";
            $lines[] = "    ssl_certificate_key {$certPaths['key']};";
            $lines[] = '    ssl_protocols TLSv1.2 TLSv1.3;';
            $lines[] = '    ssl_ciphers HIGH:!aNULL:!MD5;';
            $lines[] = '    ssl_prefer_server_ciphers on;';
            $lines[] = '    ssl_session_cache shared:SSL:10m;';
            $lines[] = '    ssl_session_timeout 1d;';
            $lines[] = '';
            $lines[] = '    # Reto ACME en HTTPS';
            $lines[] = '    location ^~ /.well-known/acme-challenge/ {';
            $lines[] = '        default_type "text/plain";';
            $lines[] = "        root {$acmeWebroot};";
            $lines[] = '        allow all;';
            $lines[] = '    }';
            $lines[] = '';
            $lines[] = $this->renderErrorPages(4);
            $lines[] = '';
            $lines[] = $this->renderProxyPassBlock($proxy, 4);
            $lines[] = '}';
        } else {
            // -------------------------------------------------------------
            // Bloque HTTP exclusivo (Puerto 80 sin SSL)
            // -------------------------------------------------------------
            $lines[] = 'server {';
            $lines[] = '    listen 80;';
            $lines[] = '    listen [::]:80;';
            $lines[] = "    server_name {$serverNames};";
            $lines[] = '';
            $lines[] = '    # Reto ACME para emisión inicial de certificados';
            $lines[] = '    location ^~ /.well-known/acme-challenge/ {';
            $lines[] = '        default_type "text/plain";';
            $lines[] = "        root {$acmeWebroot};";
            $lines[] = '        allow all;';
            $lines[] = '    }';
            $lines[] = '';
            $lines[] = $this->renderErrorPages(4);
            $lines[] = '';
            $lines[] = $this->renderProxyPassBlock($proxy, 4);
            $lines[] = '}';
        }

        $lines[] = '';
        return implode("\n", $lines);
    }

    /**
     * Construye la lista unificada de server_name (dominio principal + aliases).
     */
    public function buildServerNames(array $proxy): string
    {
        $domains = [];
        $primary = strtolower(trim((string) ($proxy['domain'] ?? '')));
        if ($primary !== '') {
            $this->assertSafeDomain($primary);
            $domains[] = $primary;
        }

        $aliasesRaw = (string) ($proxy['domain_names'] ?? '');
        if ($aliasesRaw !== '') {
            $parts = preg_split('/[\s,]+/', $aliasesRaw, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $alias) {
                $alias = strtolower(trim($alias));
                if ($alias !== '' && !in_array($alias, $domains, true)) {
                    $this->assertSafeDomain($alias);
                    $domains[] = $alias;
                }
            }
        }

        return implode(' ', $domains);
    }

    /**
     * Renderiza el bloque `location /` con proxy_pass, timeouts, límites de subida,
     * verificación SSL backend, headers validados contra lista blanca y soporte WebSockets.
     */
    protected function renderProxyPassBlock(array $proxy, int $indent = 4): string
    {
        $pad = str_repeat(' ', $indent);
        $padInner = str_repeat(' ', $indent + 4);

        $scheme = strtolower(trim((string) ($proxy['scheme'] ?? 'http')));
        $host   = trim((string) ($proxy['forward_host'] ?? '127.0.0.1'));
        $port   = (int) ($proxy['forward_port'] ?? 80);
        $ws     = !empty($proxy['websocket_enabled']);

        // Timeouts configurables (1 - 3600s)
        $connectTimeout = isset($proxy['proxy_connect_timeout']) && $proxy['proxy_connect_timeout'] !== '' ? (int) $proxy['proxy_connect_timeout'] : 60;
        $readTimeout    = isset($proxy['proxy_read_timeout']) && $proxy['proxy_read_timeout'] !== '' ? (int) $proxy['proxy_read_timeout'] : 60;
        $sendTimeout    = isset($proxy['proxy_send_timeout']) && $proxy['proxy_send_timeout'] !== '' ? (int) $proxy['proxy_send_timeout'] : 60;

        foreach (['proxy_connect_timeout' => $connectTimeout, 'proxy_read_timeout' => $readTimeout, 'proxy_send_timeout' => $sendTimeout] as $name => $val) {
            if ($val < 1 || $val > 3600) {
                throw new InvalidArgumentException("El valor de {$name} está fuera del rango permitido (1-3600s): {$val}");
            }
        }

        // Tamaño máximo de subida (0 = sin límite, 1 - 10240 MB)
        $maxBodySize = isset($proxy['client_max_body_size']) && $proxy['client_max_body_size'] !== '' ? (int) $proxy['client_max_body_size'] : 10;
        if ($maxBodySize < 0 || $maxBodySize > 10240) {
            throw new InvalidArgumentException("El valor de client_max_body_size está fuera del rango permitido (0-10240 MB): {$maxBodySize}");
        }
        $maxBodyDirective = $maxBodySize === 0 ? '0' : "{$maxBodySize}m";

        $lines = [];
        $lines[] = "{$pad}location / {";
        $lines[] = "{$padInner}client_max_body_size {$maxBodyDirective};";
        $lines[] = "{$padInner}proxy_pass {$scheme}://{$host}:{$port};";
        $lines[] = "{$padInner}proxy_set_header Host \$host;";
        $lines[] = "{$padInner}proxy_set_header X-Real-IP \$remote_addr;";
        $lines[] = "{$padInner}proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;";
        $lines[] = "{$padInner}proxy_set_header X-Forwarded-Proto \$scheme;";
        $lines[] = "{$padInner}proxy_set_header X-Forwarded-Host \$host;";
        $lines[] = "{$padInner}proxy_set_header X-Forwarded-Port \$server_port;";
        $lines[] = '';
        $lines[] = "{$padInner}# Timeouts y buffer de proxy";
        $lines[] = "{$padInner}proxy_connect_timeout {$connectTimeout}s;";
        $lines[] = "{$padInner}proxy_send_timeout {$sendTimeout}s;";
        $lines[] = "{$padInner}proxy_read_timeout {$readTimeout}s;";
        $lines[] = "{$padInner}proxy_buffering on;";

        // Verificación SSL y SNI hacia el backend (cuando el esquema es https o se configura explícitamente)
        $sslVerify = !empty($proxy['proxy_ssl_verify']);
        if ($scheme === 'https' || array_key_exists('proxy_ssl_verify', $proxy)) {
            $verifyState = $sslVerify ? 'on' : 'off';
            // Si el destino es una IP, enviamos $host como SNI; si es un dominio interno, enviamos ese dominio
            $sniName = filter_var($host, FILTER_VALIDATE_IP) !== false ? '$host' : $host;
            $lines[] = '';
            $lines[] = "{$padInner}# Verificación SSL y SNI hacia el backend";
            $lines[] = "{$padInner}proxy_ssl_server_name on;";
            $lines[] = "{$padInner}proxy_ssl_name {$sniName};";
            $lines[] = "{$padInner}proxy_ssl_verify {$verifyState};";
        }

        if ($ws) {
            $lines[] = '';
            $lines[] = "{$padInner}# Soporte para WebSockets";
            $lines[] = "{$padInner}proxy_http_version 1.1;";
            $lines[] = "{$padInner}proxy_set_header Upgrade \$http_upgrade;";
            $lines[] = "{$padInner}proxy_set_header Connection \"upgrade\";";
        }

        $customHeaders = $this->renderCustomHeaders($proxy, $indent + 4);
        if ($customHeaders !== '') {
            $lines[] = '';
            $lines[] = "{$padInner}# Cabeceras personalizadas (lista blanca)";
            $lines[] = $customHeaders;
        }

        $lines[] = "{$pad}}";

        return implode("\n", $lines);
    }

    /**
     * Renderiza cabeceras personalizadas validadas estrictamente contra lista blanca.
     *
     * @throws InvalidArgumentException Si alguna cabecera no pertenece a la lista blanca o intenta inyectar directivas
     */
    protected function renderCustomHeaders(array $proxy, int $indent = 8): string
    {
        $raw = (string) ($proxy['custom_headers'] ?? '');
        $parsed = ProxyHostRules::parseAndValidateHeaders($raw);

        if (!$parsed['valid']) {
            throw new InvalidArgumentException($parsed['error'] ?? 'Cabeceras personalizadas no válidas.');
        }

        $pad    = str_repeat(' ', $indent);
        $output = [];
        $hasHstsHeader = false;

        foreach ($parsed['headers'] as $header) {
            $name      = $header['name'];
            $value     = $header['value'];
            $directive = $header['directive'];

            if (strtolower($name) === 'strict-transport-security') {
                $hasHstsHeader = true;
            }

            if ($directive === 'proxy_set_header') {
                if (in_array($value, ProxyHostRules::SAFE_PROXY_VARIABLES, true)) {
                    $output[] = "{$pad}proxy_set_header {$name} {$value};";
                } else {
                    $output[] = "{$pad}proxy_set_header {$name} \"{$value}\";";
                }
            } else {
                $output[] = "{$pad}add_header {$name} \"{$value}\" always;";
            }
        }

        // Si HSTS está activado mediante el interruptor dedicado y SSL está activo
        if (!$hasHstsHeader && !empty($proxy['ssl_enabled']) && !empty($proxy['hsts_enabled'])) {
            array_unshift($output, "{$pad}add_header Strict-Transport-Security \"max-age=31536000; includeSubDomains\" always;");
        }

        return implode("\n", $output);
    }

    /**
     * Renderiza páginas de error amigables para 502, 503 y 504 conservando el código HTTP original.
     */
    protected function renderErrorPages(int $indent = 4): string
    {
        $pad = str_repeat(' ', $indent);
        $padInner = str_repeat(' ', $indent + 4);

        $errors = [
            502 => [
                'title' => '502 Bad Gateway',
                'desc'  => 'El servidor proxy inverso no pudo conectar con el backend de destino.',
            ],
            503 => [
                'title' => '503 Service Unavailable',
                'desc'  => 'El servicio de destino no está disponible temporalmente o está en mantenimiento.',
            ],
            504 => [
                'title' => '504 Gateway Timeout',
                'desc'  => 'El servidor de destino tardó demasiado tiempo en responder a la solicitud.',
            ],
        ];

        $style = 'body{font-family:system-ui,-apple-system,sans-serif;background:#0b1114;color:#e1e7ec;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}'
               . 'div{text-align:center;padding:2.5rem;border:1px solid #374853;border-radius:12px;background:#151c24;max-width:480px;box-shadow:0 10px 25px rgba(0,0,0,0.5)}'
               . 'h1{color:#ef4444;font-size:2.2rem;margin-bottom:0.5rem;font-weight:700}'
               . 'p{color:#94a3b8;font-size:0.95rem;line-height:1.5}';

        $lines = [];
        $lines[] = "{$pad}# Páginas de error amigables (conservando código HTTP original)";
        foreach ($errors as $code => $info) {
            $lines[] = "{$pad}error_page {$code} /proxy_error_{$code}.html;";
        }

        foreach ($errors as $code => $info) {
            $html = "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>{$info['title']}</title>"
                  . "<style>{$style}</style></head><body><div><h1>{$info['title']}</h1><p>{$info['desc']}</p></div></body></html>";

            $lines[] = "{$pad}location = /proxy_error_{$code}.html {";
            $lines[] = "{$padInner}internal;";
            $lines[] = "{$padInner}default_type text/html;";
            $lines[] = "{$padInner}return {$code} '{$html}';";
            $lines[] = "{$pad}}";
        }

        return implode("\n", $lines);
    }

    /**
     * Resuelve las rutas de certificados para SSL.
     */
    protected function resolveCertificates(array $proxy): array
    {
        $primaryDomain = strtolower(trim((string) ($proxy['domain'] ?? '')));

        // Rutas estándar de Let's Encrypt
        $cert = "/etc/letsencrypt/live/{$primaryDomain}/fullchain.pem";
        $key  = "/etc/letsencrypt/live/{$primaryDomain}/privkey.pem";

        // Certificado existente asociado al host (selector SSL)
        if (!empty($proxy['certificate_id'])) {
            $cert = (new \App\Models\CertificateModel())->find((int) $proxy['certificate_id']);
            if ($cert && !empty($cert['certificate_path']) && !empty($cert['private_key_path'])) {
                $this->assertSafePath($cert['certificate_path']);
                $this->assertSafePath($cert['private_key_path']);
                return ['cert' => $cert['certificate_path'], 'key' => $cert['private_key_path']];
            }
        }

        // Si existen certificados simulados o locales definidos en el proxy
        if (!empty($proxy['certificate_path']) && !empty($proxy['private_key_path'])) {
            $cert = $proxy['certificate_path'];
            $key  = $proxy['private_key_path'];
        }

        return ['cert' => $cert, 'key' => $key];
    }

    /**
     * Valida que una ruta de certificado no contenga caracteres inyectables.
     */
    protected function assertSafePath(string $path): void
    {
        if (!preg_match('#^/[a-zA-Z0-9._/\-]+$#', $path) || str_contains($path, '..')) {
            throw new InvalidArgumentException("Ruta de certificado no válida: {$path}");
        }
    }

    /**
     * Valida que un dominio cumpla RFC 1123 y no contenga caracteres inyectables.
     */
    protected function assertSafeDomain(string $domain): void
    {
        if (!preg_match('/^(?!\-)(?:[a-zA-Z0-9\-]{1,63}\.)+[a-zA-Z]{2,63}$/', $domain)) {
            // Permitir 'localhost' para entornos de prueba
            if ($domain !== 'localhost') {
                throw new InvalidArgumentException("Nombre de dominio inválido o inseguro: {$domain}");
            }
        }

        if (preg_match('/[;\r\n\s{}\"\'\`]/', $domain)) {
            throw new InvalidArgumentException("El dominio contiene caracteres prohibidos: {$domain}");
        }
    }

    /**
     * Valida que el host de destino no contenga caracteres inyectables.
     */
    protected function assertSafeForwardHost(string $host): void
    {
        if (preg_match('/[;\r\n\s{}\"\'\`]/', $host)) {
            throw new InvalidArgumentException("El host de destino contiene caracteres prohibidos: {$host}");
        }

        // Validación de IP o nombre de host
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isHost = (bool) preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $host) || $host === 'localhost';

        if (!$isIp && !$isHost) {
            throw new InvalidArgumentException("Host de destino no válido: {$host}");
        }
    }
}

