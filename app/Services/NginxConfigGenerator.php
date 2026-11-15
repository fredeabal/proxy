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
        $hostType    = strtolower(trim((string) ($proxy['host_type'] ?? 'proxy')));
        if (!in_array($hostType, ['proxy', 'redirect'], true)) {
            throw new InvalidArgumentException("Tipo de host inválido: {$hostType}");
        }

        $isRedirect = ($hostType === 'redirect');
        $isLb       = !$isRedirect && !empty($proxy['load_balancing_enabled']);

        if ($isRedirect) {
            $redirectUrl = trim((string) ($proxy['redirect_url'] ?? ''));
            $rules = new ProxyHostRules();
            if (!$rules->valid_redirect_url($redirectUrl, $err)) {
                throw new InvalidArgumentException($err ?? 'URL de redirección inválida.');
            }
            $redirectCode = (int) ($proxy['redirect_code'] ?? 301);
            if (!in_array($redirectCode, [301, 302, 307, 308], true)) {
                throw new InvalidArgumentException("Código de redirección inválido: {$redirectCode}");
            }
        } else {
            $scheme = strtolower(trim((string) ($proxy['scheme'] ?? 'http')));
            if (!in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException("Esquema inválido: {$scheme}");
            }

            if ($isLb) {
                if (!isset($proxy['upstreams']) && $id > 0) {
                    $upstreamModel = new \App\Models\ProxyUpstreamModel();
                    $proxy['upstreams'] = $upstreamModel->getForProxy($id);
                }
                if (empty($proxy['upstreams'])) {
                    throw new InvalidArgumentException("El modo de balanceo de carga requiere al menos un servidor backend configurado.");
                }
            } else {
                $forwardHost = trim((string) ($proxy['forward_host'] ?? ''));
                $this->assertSafeForwardHost($forwardHost);

                $forwardPort = (int) ($proxy['forward_port'] ?? 80);
                if ($forwardPort < 1 || $forwardPort > 65535) {
                    throw new InvalidArgumentException("Puerto de destino fuera de rango: {$forwardPort}");
                }
            }

            if (!isset($proxy['locations']) && $id > 0) {
                $locationModel = new \App\Models\ProxyLocationModel();
                $proxy['locations'] = $locationModel->getForProxy($id);
            }
        }

        // Cargar Access List asociada si existe
        if (!empty($proxy['access_list_id']) && !isset($proxy['access_list'])) {
            $alModel = new \App\Models\AccessListModel();
            $proxy['access_list'] = $alModel->getWithRelations((int) $proxy['access_list_id']);
        }

        $sslEnabled = !empty($proxy['ssl_enabled']);
        $forceHttps = !empty($proxy['force_https']) && $sslEnabled;
        $acmeWebroot = rtrim($this->config->acmeWebroot, '/');
        $logsPath = rtrim($this->config->logsPath, '/');
        $accessLogLine = $id > 0 ? "    access_log {$logsPath}/proxy_host_{$id}_access.log;" : "    access_log {$logsPath}/access.log;";
        $errorLogLine  = $id > 0 ? "    error_log  {$logsPath}/proxy_host_{$id}_error.log;" : "    error_log  {$logsPath}/error.log;";

        $lines = [];
        $lines[] = '# ==============================================================================';
        $lines[] = "# Reverse Proxy Manager - " . ($isRedirect ? 'Redirection Host' : 'Proxy Host') . " [ID: {$id}]";
        $lines[] = "# Dominio: {$primaryDomain}";
        $lines[] = '# Generado automáticamente - NO EDITAR MANUALMENTE';
        $lines[] = '# Fecha: ' . date('Y-m-d H:i:s');
        $lines[] = '# ==============================================================================';
        $lines[] = '';

        if (!empty($proxy['rate_limit_enabled'])) {
            $lines[] = $this->renderRateLimitZone($proxy);
        }

        if ($isLb) {
            $lines[] = $this->renderUpstreamBlock($proxy);
        }

        if ($sslEnabled) {
            // -------------------------------------------------------------
            // Bloque HTTP (Puerto 80) para SSL
            // -------------------------------------------------------------
            $lines[] = 'server {';
            $lines[] = '    listen 80;';
            $lines[] = '    listen [::]:80;';
            $lines[] = "    server_name {$serverNames};";
            $lines[] = '';
            $lines[] = $accessLogLine;
            $lines[] = $errorLogLine;
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
            } elseif ($isRedirect) {
                $lines[] = $this->renderRedirectBlock($proxy, 4);
            } else {
                $customLocs = $this->renderCustomLocations($proxy, 4);
                if ($customLocs !== '') {
                    $lines[] = $customLocs;
                    $lines[] = '';
                }
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
            $lines[] = $accessLogLine;
            $lines[] = $errorLogLine;
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
            if ($isRedirect) {
                $lines[] = $this->renderRedirectBlock($proxy, 4);
            } else {
                $customLocs = $this->renderCustomLocations($proxy, 4);
                if ($customLocs !== '') {
                    $lines[] = $customLocs;
                    $lines[] = '';
                }
                $lines[] = $this->renderProxyPassBlock($proxy, 4);
            }
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
            $lines[] = $accessLogLine;
            $lines[] = $errorLogLine;
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
            if ($isRedirect) {
                $lines[] = $this->renderRedirectBlock($proxy, 4);
            } else {
                $customLocs = $this->renderCustomLocations($proxy, 4);
                if ($customLocs !== '') {
                    $lines[] = $customLocs;
                    $lines[] = '';
                }
                $lines[] = $this->renderProxyPassBlock($proxy, 4);
            }
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
     * Renderiza el bloque `upstream` para balanceo de carga entre múltiples backends.
     *
     * @param array $proxy
     * @return string
     * @throws InvalidArgumentException
     */
    public function renderUpstreamBlock(array $proxy): string
    {
        $id = (int) ($proxy['id'] ?? 0);
        $primaryDomain = strtolower(trim((string) ($proxy['domain'] ?? '')));
        $upstreamName = $id > 0 ? "upstream_proxy_{$id}" : "upstream_proxy_" . preg_replace('/[^a-z0-9]/', '_', $primaryDomain);

        $algorithm = strtolower(trim((string) ($proxy['lb_algorithm'] ?? 'round_robin')));
        if (!in_array($algorithm, ['round_robin', 'least_conn', 'ip_hash'], true)) {
            throw new InvalidArgumentException("Algoritmo de balanceo inválido: {$algorithm}");
        }

        $upstreams = $proxy['upstreams'] ?? [];
        if (empty($upstreams)) {
            throw new InvalidArgumentException("El modo de balanceo de carga requiere al menos un servidor backend configurado.");
        }

        $lines = [];
        $lines[] = '# ------------------------------------------------------------------------------';
        $lines[] = "# Upstream Pool [ID: {$id}] - Algoritmo: {$algorithm}";
        $lines[] = '# ------------------------------------------------------------------------------';
        $lines[] = "upstream {$upstreamName} {";

        if ($algorithm === 'least_conn') {
            $lines[] = '    least_conn;';
        } elseif ($algorithm === 'ip_hash') {
            $lines[] = '    ip_hash;';
        }

        foreach ($upstreams as $srv) {
            $host = trim((string) ($srv['server_host'] ?? ''));
            $this->assertSafeForwardHost($host);

            $port = (int) ($srv['server_port'] ?? 80);
            if ($port < 1 || $port > 65535) {
                throw new InvalidArgumentException("Puerto fuera de rango en upstream {$host}: {$port}");
            }

            $weight = isset($srv['weight']) && (int) $srv['weight'] > 0 ? (int) $srv['weight'] : 1;
            $maxFails = isset($srv['max_fails']) && (int) $srv['max_fails'] >= 0 ? (int) $srv['max_fails'] : 3;
            $failTimeout = isset($srv['fail_timeout']) && (int) $srv['fail_timeout'] > 0 ? (int) $srv['fail_timeout'] : 10;
            $isBackup = !empty($srv['is_backup']);
            $isDown = !empty($srv['is_down']);

            $serverLine = "    server " . $this->formatHostForUrl($host) . ":{$port}";
            if ($weight > 1 && $algorithm !== 'ip_hash') {
                $serverLine .= " weight={$weight}";
            }
            if ($maxFails !== 1 || $failTimeout !== 10) {
                $serverLine .= " max_fails={$maxFails} fail_timeout={$failTimeout}s";
            }
            if ($isBackup && $algorithm !== 'ip_hash') {
                $serverLine .= ' backup';
            }
            if ($isDown) {
                $serverLine .= ' down';
            }
            $serverLine .= ';';
            $lines[] = $serverLine;
        }

        $lines[] = '    keepalive 32;';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Renderiza el bloque `location /` con proxy_pass, timeouts, límites de subida,
     * verificación SSL backend, headers validados contra lista blanca y soporte WebSockets.
     */
    protected function renderProxyPassBlock(array $proxy, int $indent = 4): string
    {
        $pad = str_repeat(' ', $indent);
        $padInner = str_repeat(' ', $indent + 4);

        $id     = (int) ($proxy['id'] ?? 0);
        $scheme = strtolower(trim((string) ($proxy['scheme'] ?? 'http')));
        $isLb   = !empty($proxy['load_balancing_enabled']);
        $ws     = !empty($proxy['websocket_enabled']);

        if ($isLb) {
            $primaryDomain = strtolower(trim((string) ($proxy['domain'] ?? '')));
            $upstreamName  = $id > 0 ? "upstream_proxy_{$id}" : "upstream_proxy_" . preg_replace('/[^a-z0-9]/', '_', $primaryDomain);
            $proxyPassUrl  = "{$scheme}://{$upstreamName}";
        } else {
            $host = trim((string) ($proxy['forward_host'] ?? '127.0.0.1'));
            $port = (int) ($proxy['forward_port'] ?? 80);
            $proxyPassUrl = "{$scheme}://" . $this->formatHostForUrl($host) . ":{$port}";
        }

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
        $lines[] = "{$padInner}# Control GeoIP2 (si está habilitado globalmente)";
        $lines[] = "{$padInner}if (\$geoip_blocked = 1) {";
        $lines[] = "{$padInner}    return 403;";
        $lines[] = "{$padInner}}";

        $accessListDirectives = $this->renderAccessListDirectives($proxy, $indent + 4);
        if ($accessListDirectives !== '') {
            $lines[] = '';
            $lines[] = $accessListDirectives;
        }

        $rateLimitDirectives = $this->renderRateLimitDirectives($proxy, $indent + 4);
        if ($rateLimitDirectives !== '') {
            $lines[] = '';
            $lines[] = $rateLimitDirectives;
        }

        $lines[] = '';
        $lines[] = "{$padInner}client_max_body_size {$maxBodyDirective};";
        $lines[] = "{$padInner}proxy_pass {$proxyPassUrl};";

        if ($isLb) {
            $lines[] = "{$padInner}proxy_next_upstream error timeout invalid_header http_502 http_503;";
            $lines[] = "{$padInner}proxy_next_upstream_tries 3;";
            $lines[] = "{$padInner}proxy_next_upstream_timeout 10s;";
        }

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
            if ($isLb) {
                $sniName = '$host';
            } else {
                $host = trim((string) ($proxy['forward_host'] ?? '127.0.0.1'));
                $sniName = filter_var($host, FILTER_VALIDATE_IP) !== false ? '$host' : $host;
            }
            $lines[] = '';
            $lines[] = "{$padInner}# Verificación SSL y SNI hacia el backend";
            $lines[] = "{$padInner}proxy_ssl_server_name on;";
            $lines[] = "{$padInner}proxy_ssl_name {$sniName};";
            $lines[] = "{$padInner}proxy_ssl_verify {$verifyState};";
            if ($sslVerify) {
                $lines[] = "{$padInner}proxy_ssl_trusted_certificate /etc/ssl/certs/ca-certificates.crt;";
                $lines[] = "{$padInner}proxy_ssl_verify_depth 2;";
            }
        }

        if ($ws) {
            $lines[] = '';
            $lines[] = "{$padInner}# Soporte para WebSockets";
            $lines[] = "{$padInner}proxy_http_version 1.1;";
            $lines[] = "{$padInner}proxy_set_header Upgrade \$http_upgrade;";
            $lines[] = "{$padInner}proxy_set_header Connection \"upgrade\";";
        } elseif ($isLb) {
            $lines[] = '';
            $lines[] = "{$padInner}# Reutilización de conexiones keepalive hacia el upstream";
            $lines[] = "{$padInner}proxy_http_version 1.1;";
            $lines[] = "{$padInner}proxy_set_header Connection \"\";";
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
     * Renderiza la directiva `limit_req_zone` a nivel http para Rate Limiting por IP.
     */
    protected function renderRateLimitZone(array $proxy): string
    {
        $id       = (int) ($proxy['id'] ?? 0);
        $requests = max(1, min(10000, (int) ($proxy['rate_limit_requests'] ?? 10)));
        $unit     = strtolower(trim((string) ($proxy['rate_limit_unit'] ?? 's')));
        if (!in_array($unit, ['s', 'm'], true)) {
            $unit = 's';
        }

        $zoneName = $id > 0 ? "rate_limit_proxy_{$id}" : 'rate_limit_proxy_' . preg_replace('/[^a-z0-9]/', '_', strtolower((string) ($proxy['domain'] ?? 'host')));

        return "limit_req_zone \$binary_remote_addr zone={$zoneName}:10m rate={$requests}r/{$unit};\n";
    }

    /**
     * Renderiza las directivas `limit_req` y `limit_req_status` dentro de un bloque location.
     */
    protected function renderRateLimitDirectives(array $proxy, int $indent = 8): string
    {
        if (empty($proxy['rate_limit_enabled'])) {
            return '';
        }

        $pad      = str_repeat(' ', $indent);
        $id       = (int) ($proxy['id'] ?? 0);
        $zoneName = $id > 0 ? "rate_limit_proxy_{$id}" : 'rate_limit_proxy_' . preg_replace('/[^a-z0-9]/', '_', strtolower((string) ($proxy['domain'] ?? 'host')));
        $burst    = max(0, min(10000, (int) ($proxy['rate_limit_burst'] ?? 20)));
        $nodelay  = !isset($proxy['rate_limit_nodelay']) || !empty($proxy['rate_limit_nodelay']);

        $limitLine = "{$pad}limit_req zone={$zoneName}";
        if ($burst > 0) {
            $limitLine .= " burst={$burst}";
            if ($nodelay) {
                $limitLine .= ' nodelay';
            }
        }
        $limitLine .= ';';

        return implode("\n", [
            "{$pad}# Rate Limiting por IP",
            $limitLine,
            "{$pad}limit_req_status 429;",
        ]);
    }

    /**
     * Renderiza las directivas de Access List (IP/CIDR allow/deny y auth_basic).
     */
    protected function renderAccessListDirectives(array $proxy, int $indent = 8): string
    {
        $accessList = $proxy['access_list'] ?? null;
        if (empty($accessList) || !is_array($accessList)) {
            return '';
        }

        $clients = $accessList['clients'] ?? [];
        $users   = $accessList['users'] ?? [];

        if (empty($clients) && empty($users)) {
            return '';
        }

        $pad   = str_repeat(' ', $indent);
        $lines = [];
        $lines[] = "{$pad}# Access List: " . preg_replace('/[\r\n]/', '', (string) ($accessList['name'] ?? 'Custom'));

        if (!empty($clients) && !empty($users)) {
            $satisfy = !empty($accessList['satisfy_any']) ? 'any' : 'all';
            $lines[] = "{$pad}satisfy {$satisfy};";
        }

        if (!empty($clients)) {
            $hasExplicitAll = false;
            $hasAllow       = false;
            foreach ($clients as $client) {
                $dir  = strtolower(trim((string) ($client['directive'] ?? 'allow')));
                $addr = trim((string) ($client['address'] ?? ''));
                if (!in_array($dir, ['allow', 'deny'], true) || !ProxyHostRules::validCidrOrIp($addr)) {
                    continue;
                }
                if ($addr === 'all') {
                    $hasExplicitAll = true;
                }
                if ($dir === 'allow') {
                    $hasAllow = true;
                }
                $lines[] = "{$pad}{$dir} {$addr};";
            }
            if ($hasAllow && !$hasExplicitAll) {
                $lines[] = "{$pad}deny all;";
            }
        }

        if (!empty($users)) {
            $alId = (int) ($accessList['id'] ?? $proxy['access_list_id'] ?? 0);
            $htpasswdPath = rtrim($this->config->accessListsPath, '/') . "/access_list_{$alId}.htpasswd";
            $this->assertSafePath($htpasswdPath);
            $realm = preg_replace('/[^a-zA-Z0-9 ._\-]/', '', (string) ($accessList['name'] ?? 'Restricted Area'));
            $realm = $realm !== '' ? $realm : 'Restricted Area';

            $lines[] = "{$pad}auth_basic \"{$realm}\";";
            $lines[] = "{$pad}auth_basic_user_file {$htpasswdPath};";
        }

        return implode("\n", $lines);
    }

    /**
     * Renderiza el bloque `location /` para Redirection Hosts (301/302/307/308).
     */
    protected function renderRedirectBlock(array $proxy, int $indent = 4): string
    {
        $pad      = str_repeat(' ', $indent);
        $padInner = str_repeat(' ', $indent + 4);

        $redirectUrl  = trim((string) ($proxy['redirect_url'] ?? ''));
        $redirectCode = (int) ($proxy['redirect_code'] ?? 301);
        $preservePath = !isset($proxy['preserve_path']) || !empty($proxy['preserve_path']);

        if ($preservePath && !str_contains($redirectUrl, '$request_uri')) {
            $target = rtrim($redirectUrl, '/') . '$request_uri';
        } else {
            $target = $redirectUrl;
        }

        $lines   = [];
        $lines[] = "{$pad}location / {";
        $lines[] = "{$padInner}# Control GeoIP2 (si está habilitado globalmente)";
        $lines[] = "{$padInner}if (\$geoip_blocked = 1) {";
        $lines[] = "{$padInner}    return 403;";
        $lines[] = "{$padInner}}";

        $accessListDirectives = $this->renderAccessListDirectives($proxy, $indent + 4);
        if ($accessListDirectives !== '') {
            $lines[] = '';
            $lines[] = $accessListDirectives;
        }

        $rateLimitDirectives = $this->renderRateLimitDirectives($proxy, $indent + 4);
        if ($rateLimitDirectives !== '') {
            $lines[] = '';
            $lines[] = $rateLimitDirectives;
        }

        $lines[] = '';
        $lines[] = "{$padInner}# Redirección HTTP {$redirectCode}";
        $lines[] = "{$padInner}return {$redirectCode} {$target};";
        $lines[] = "{$pad}}";

        return implode("\n", $lines);
    }

    /**
     * Renderiza bloques `location` personalizados adicionales para rutas específicas.
     */
    protected function renderCustomLocations(array $proxy, int $indent = 4): string
    {
        $locations = $proxy['locations'] ?? [];
        if (empty($locations) || !is_array($locations)) {
            return '';
        }

        $pad      = str_repeat(' ', $indent);
        $padInner = str_repeat(' ', $indent + 4);
        $blocks   = [];

        foreach ($locations as $loc) {
            $path = trim((string) ($loc['path'] ?? ''));
            if (!ProxyHostRules::validLocationPath($path, $pathErr)) {
                throw new InvalidArgumentException($pathErr ?? "Ruta location inválida: {$path}");
            }

            $locScheme = strtolower(trim((string) ($loc['scheme'] ?? 'http')));
            if (!in_array($locScheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException("Esquema inválido en location {$path}: {$locScheme}");
            }

            $locHost = trim((string) ($loc['forward_host'] ?? ''));
            $this->assertSafeForwardHost($locHost);

            $locPort = (int) ($loc['forward_port'] ?? 80);
            if ($locPort < 1 || $locPort > 65535) {
                throw new InvalidArgumentException("Puerto fuera de rango en location {$path}: {$locPort}");
            }

            $locProxyPass = "{$locScheme}://" . $this->formatHostForUrl($locHost) . ":{$locPort}";
            $locWs        = !empty($loc['websocket_enabled']);

            $lines   = [];
            $lines[] = "{$pad}location {$path} {";
            $lines[] = "{$padInner}if (\$geoip_blocked = 1) {";
            $lines[] = "{$padInner}    return 403;";
            $lines[] = "{$padInner}}";

            $accessListDirectives = $this->renderAccessListDirectives($proxy, $indent + 4);
            if ($accessListDirectives !== '') {
                $lines[] = '';
                $lines[] = $accessListDirectives;
            }

            $rateLimitDirectives = $this->renderRateLimitDirectives($proxy, $indent + 4);
            if ($rateLimitDirectives !== '') {
                $lines[] = '';
                $lines[] = $rateLimitDirectives;
            }

            $lines[] = '';
            $lines[] = "{$padInner}proxy_pass {$locProxyPass};";
            $lines[] = "{$padInner}proxy_set_header Host \$host;";
            $lines[] = "{$padInner}proxy_set_header X-Real-IP \$remote_addr;";
            $lines[] = "{$padInner}proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;";
            $lines[] = "{$padInner}proxy_set_header X-Forwarded-Proto \$scheme;";
            $lines[] = "{$padInner}proxy_set_header X-Forwarded-Host \$host;";
            $lines[] = "{$padInner}proxy_set_header X-Forwarded-Port \$server_port;";

            if ($locWs) {
                $lines[] = "{$padInner}proxy_http_version 1.1;";
                $lines[] = "{$padInner}proxy_set_header Upgrade \$http_upgrade;";
                $lines[] = "{$padInner}proxy_set_header Connection \"upgrade\";";
            }

            if (!empty($loc['custom_headers'])) {
                $locCustomHeaders = $this->renderCustomHeaders(['custom_headers' => $loc['custom_headers']], $indent + 4);
                if ($locCustomHeaders !== '') {
                    $lines[] = $locCustomHeaders;
                }
            }

            $lines[] = "{$pad}}";
            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Genera la configuración Nginx para un bloque `stream` (Proxy TCP/UDP).
     *
     * @throws InvalidArgumentException
     */
    public function generateStream(array $stream): string
    {
        $id           = (int) ($stream['id'] ?? 0);
        $incomingPort = (int) ($stream['incoming_port'] ?? 0);
        if ($incomingPort < 1 || $incomingPort > 65535) {
            throw new InvalidArgumentException("Puerto de entrada inválido en stream: {$incomingPort}");
        }

        $forwardHost = trim((string) ($stream['forward_host'] ?? ''));
        $this->assertSafeForwardHost($forwardHost);

        $forwardPort = (int) ($stream['forward_port'] ?? 0);
        if ($forwardPort < 1 || $forwardPort > 65535) {
            throw new InvalidArgumentException("Puerto de destino inválido en stream: {$forwardPort}");
        }

        $protocol = strtolower(trim((string) ($stream['protocol'] ?? 'tcp')));
        if (!in_array($protocol, ['tcp', 'udp', 'tcp_udp'], true)) {
            throw new InvalidArgumentException("Protocolo de stream inválido: {$protocol}");
        }

        $target = $this->formatHostForUrl($forwardHost) . ":{$forwardPort}";

        $lines   = [];
        $lines[] = '# ==============================================================================';
        $lines[] = "# Reverse Proxy Manager - Stream TCP/UDP [ID: {$id}]";
        $lines[] = "# Puerto Entrada: {$incomingPort} ({$protocol}) -> {$target}";
        $lines[] = '# Generado automáticamente - NO EDITAR MANUALMENTE';
        $lines[] = '# Fecha: ' . date('Y-m-d H:i:s');
        $lines[] = '# ==============================================================================';
        $lines[] = '';
        $lines[] = 'server {';

        if ($protocol === 'tcp' || $protocol === 'tcp_udp') {
            $lines[] = "    listen {$incomingPort};";
            $lines[] = "    listen [::]:{$incomingPort};";
        }
        if ($protocol === 'udp' || $protocol === 'tcp_udp') {
            $lines[] = "    listen {$incomingPort} udp;";
            $lines[] = "    listen [::]:{$incomingPort} udp;";
        }

        $lines[] = "    proxy_pass {$target};";
        $lines[] = '    proxy_connect_timeout 10s;';
        if ($protocol === 'udp') {
            $lines[] = '    proxy_responses 1;';
        } else {
            $lines[] = '    proxy_timeout 600s;';
        }
        $lines[] = '}';
        $lines[] = '';

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
            404 => [
                'title' => '404 Not Found',
                'desc'  => 'El recurso solicitado no est&aacute; disponible.',
            ],
            502 => [
                'title' => '502 Bad Gateway',
                'desc'  => 'El servicio no responde temporalmente.',
            ],
            503 => [
                'title' => '503 Service Unavailable',
                'desc'  => 'Servicio no disponible moment&aacute;neamente.',
            ],
            504 => [
                'title' => '504 Gateway Timeout',
                'desc'  => 'Tiempo de espera agotado al conectar con el servicio.',
            ],
        ];

        $style = 'body{font-family:system-ui,-apple-system,sans-serif;background:#0b1114;color:#e1e7ec;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:1rem;box-sizing:border-box}'
               . 'div{text-align:center;padding:2.5rem;border:1px solid #374853;border-radius:12px;background:#151c24;max-width:440px;width:100%;box-shadow:0 10px 25px rgba(0,0,0,0.5)}'
               . 'h1{color:#ef4444;font-size:2.2rem;margin:0 0 0.75rem;font-weight:700;line-height:1.2}'
               . 'p{color:#94a3b8;font-size:0.95rem;line-height:1.5;margin:0}';

        $lines = [];
        $lines[] = "{$pad}# Páginas de error amigables (conservando código HTTP original)";
        $lines[] = "{$pad}# Modo Stealth: mapear 403 (ej. directivas deny de IPs) a 404 Not Found";
        $lines[] = "{$pad}error_page 403 =404 /proxy_error_404.html;";
        foreach ($errors as $code => $info) {
            $lines[] = "{$pad}error_page {$code} /proxy_error_{$code}.html;";
        }

        foreach ($errors as $code => $info) {
            $html = "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>{$info['title']}</title>"
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
            $this->assertSafePath((string) $proxy['certificate_path']);
            $this->assertSafePath((string) $proxy['private_key_path']);
            $cert = $proxy['certificate_path'];
            $key  = $proxy['private_key_path'];
        }

        return ['cert' => $cert, 'key' => $key];
    }

    /**
     * Formatea un host para URL/upstream de Nginx, envolviendo direcciones IPv6 entre corchetes.
     */
    protected function formatHostForUrl(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return "[{$host}]";
        }

        return $host;
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

    /**
     * Genera la configuración Nginx para el vhost del panel de administración (proxy-manager).
     *
     * @param array $panel Configuración del panel:
     *                     - domain (string): dominio, IP, localhost o '_'
     *                     - port (int): puerto de escucha (1-65535)
     *                     - ssl_enabled (bool): activar HTTPS
     *                     - ssl_mode (string): 'letsencrypt' | 'self_signed'
     *                     - force_https (bool): redirigir HTTP a HTTPS
     *                     - ssl_cert_path (string, opcional)
     *                     - ssl_key_path (string, opcional)
     *                     - root (string, opcional)
     *                     - php_sock (string, opcional)
     * @throws InvalidArgumentException
     */
    public function generatePanelVhost(array $panel): string
    {
        $domain = strtolower(trim((string) ($panel['domain'] ?? '_')));
        $this->assertSafePanelServerName($domain);

        $port = (int) ($panel['port'] ?? 81);
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException("Puerto del panel fuera de rango: {$port}");
        }

        $sslEnabled = !empty($panel['ssl_enabled']);
        $sslMode    = strtolower(trim((string) ($panel['ssl_mode'] ?? 'self_signed')));
        if (!in_array($sslMode, ['letsencrypt', 'self_signed'], true)) {
            throw new InvalidArgumentException("Modo SSL del panel inválido: {$sslMode}");
        }

        if ($sslEnabled && $sslMode === 'letsencrypt') {
            $isIp = filter_var($domain, FILTER_VALIDATE_IP) !== false;
            if ($isIp || $domain === 'localhost' || $domain === '_') {
                throw new InvalidArgumentException("Let's Encrypt requiere un nombre de dominio válido (no IP ni localhost).");
            }
        }

        $forceHttps  = !empty($panel['force_https']) && $sslEnabled;
        $acmeWebroot = rtrim($this->config->acmeWebroot, '/');
        $root        = trim((string) ($panel['root'] ?? '/var/www/proxy-manager/public'));
        $this->assertSafePath($root);

        $defaultPhpSock = '/var/run/php/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-fpm.sock';
        $phpSock        = trim((string) ($panel['php_sock'] ?? $defaultPhpSock));
        $this->assertSafePath($phpSock);

        $lines   = [];
        $lines[] = '# ==============================================================================';
        $lines[] = '# Reverse Proxy Manager - Panel de Administración (proxy-manager)';
        $lines[] = "# Dominio/Host: {$domain} | Puerto: {$port} | SSL: " . ($sslEnabled ? $sslMode : 'off');
        $lines[] = '# Generado automáticamente - NO EDITAR MANUALMENTE';
        $lines[] = '# Fecha: ' . date('Y-m-d H:i:s');
        $lines[] = '# ==============================================================================';
        $lines[] = '';

        // Si el panel está en el puerto 443 con SSL y redirección forzada, abrir bloque HTTP 80 -> 443
        if ($sslEnabled && $port === 443 && $forceHttps) {
            $lines[] = 'server {';
            $lines[] = '    listen 80;';
            $lines[] = '    listen [::]:80;';
            $lines[] = "    server_name {$domain};";
            $lines[] = '';
            $lines[] = '    # Endpoint global para retos ACME HTTP-01 de Let\'s Encrypt';
            $lines[] = '    location ^~ /.well-known/acme-challenge/ {';
            $lines[] = '        default_type "text/plain";';
            $lines[] = "        root {$acmeWebroot};";
            $lines[] = '    }';
            $lines[] = '';
            $lines[] = '    location / {';
            $lines[] = '        return 301 https://$host$request_uri;';
            $lines[] = '    }';
            $lines[] = '}';
            $lines[] = '';
        }

        $lines[] = 'server {';
        if ($sslEnabled) {
            $lines[] = "    listen {$port} ssl;";
            $lines[] = "    listen [::]:{$port} ssl;";
        } else {
            $lines[] = "    listen {$port};";
            $lines[] = "    listen [::]:{$port};";
        }
        $lines[] = "    server_name {$domain};";
        $lines[] = '';

        if ($sslEnabled) {
            $certPath = trim((string) ($panel['ssl_cert_path'] ?? ''));
            $keyPath  = trim((string) ($panel['ssl_key_path'] ?? ''));

            if ($certPath === '' || $keyPath === '') {
                if ($sslMode === 'letsencrypt') {
                    $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";
                    $keyPath  = "/etc/letsencrypt/live/{$domain}/privkey.pem";
                } else {
                    $certPath = '/etc/nginx/ssl/proxy-manager.crt';
                    $keyPath  = '/etc/nginx/ssl/proxy-manager.key';
                }
            }

            $this->assertSafePath($certPath);
            $this->assertSafePath($keyPath);

            $lines[] = "    ssl_certificate {$certPath};";
            $lines[] = "    ssl_certificate_key {$keyPath};";
            $lines[] = '    ssl_protocols TLSv1.2 TLSv1.3;';
            $lines[] = '    ssl_ciphers HIGH:!aNULL:!MD5;';
            $lines[] = '    ssl_prefer_server_ciphers on;';
            $lines[] = '    ssl_session_cache shared:SSL:10m;';
            $lines[] = '    ssl_session_timeout 1d;';
            $lines[] = '';

            if ($forceHttps && $port !== 443) {
                $lines[] = '    # Redirigir peticiones HTTP enviadas al puerto HTTPS del panel';
                $lines[] = '    error_page 497 =307 https://$host:$server_port$request_uri;';
                $lines[] = '';
            }
        }

        $lines[] = "    root {$root};";
        $lines[] = '    index index.php index.html;';
        $lines[] = '';
        $lines[] = '    client_max_body_size 64M;';
        $lines[] = '';
        $lines[] = '    # Endpoint global para retos ACME HTTP-01 de Let\'s Encrypt';
        $lines[] = '    location ^~ /.well-known/acme-challenge/ {';
        $lines[] = '        default_type "text/plain";';
        $lines[] = "        root {$acmeWebroot};";
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    location / {';
        $lines[] = '        try_files $uri $uri/ /index.php$is_args$args;';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    # Modo Stealth: mapear 403 a 404';
        $lines[] = '    error_page 403 =404 /index.php;';
        $lines[] = '';
        $lines[] = '    location ~ \.php$ {';
        $lines[] = '        include snippets/fastcgi-php.conf;';
        $lines[] = "        fastcgi_pass unix:{$phpSock};";
        $lines[] = '        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;';
        $lines[] = '        include fastcgi_params;';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    location ~ /\.ht {';
        $lines[] = '        deny all;';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    location ~ /\.(?!well-known).* {';
        $lines[] = '        deny all;';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    error_log  /var/log/nginx/proxy_manager_error.log;';
        $lines[] = '    access_log /var/log/nginx/proxy_manager_access.log;';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Valida que el server_name del panel sea un dominio válido, IPv4/IPv6, 'localhost' o '_'.
     */
    protected function assertSafePanelServerName(string $serverName): void
    {
        if ($serverName === '' || preg_match('/[;\r\n\s{}\"\'\`]/', $serverName)) {
            throw new InvalidArgumentException("El dominio/IP del panel contiene caracteres prohibidos: {$serverName}");
        }

        if ($serverName === '_' || $serverName === 'localhost' || filter_var($serverName, FILTER_VALIDATE_IP) !== false) {
            return;
        }

        $this->assertSafeDomain($serverName);
    }
}

