<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\CertificateModel;
use App\Models\ProxyHostModel;
use App\Models\ProxyUpstreamModel;
use App\Services\NginxManager;
use App\Services\SSLManager;
use App\Validation\ProxyHostRules;
use CodeIgniter\HTTP\ResponseInterface;

class ProxyHostController extends BaseController
{
    protected ProxyHostModel $proxyModel;
    protected ProxyUpstreamModel $upstreamModel;
    protected CertificateModel $certModel;
    protected NginxManager $nginxManager;
    protected SSLManager $sslManager;

    public function __construct()
    {
        $this->proxyModel    = new ProxyHostModel();
        $this->upstreamModel = new ProxyUpstreamModel();
        $this->certModel     = new CertificateModel();
        $this->nginxManager  = new NginxManager();
        $this->sslManager    = new SSLManager(null, $this->certModel, $this->proxyModel, $this->nginxManager);
    }

    /**
     * Listado general de Proxy Hosts con soporte para búsqueda y filtrado.
     */
    public function index(): string
    {
        $search = trim((string) $this->request->getGet('q'));
        $filterStatus = $this->request->getGet('status');

        $builder = $this->proxyModel;

        if ($search !== '') {
            $builder = $builder->groupStart()
                ->like('domain', $search)
                ->orLike('domain_names', $search)
                ->orLike('forward_host', $search)
                ->groupEnd();
        }

        if ($filterStatus === 'active') {
            $builder = $builder->where('enabled', 1);
        } elseif ($filterStatus === 'disabled') {
            $builder = $builder->where('enabled', 0);
        }

        $proxies = $builder->orderBy('id', 'DESC')->findAll();

        // Cargar upstreams para hosts con balanceo de carga / pool
        $proxyIds = array_column($proxies, 'id');
        $upstreamsByProxy = [];
        if (!empty($proxyIds)) {
            $allUpstreams = $this->upstreamModel->whereIn('proxy_host_id', $proxyIds)->findAll();
            foreach ($allUpstreams as $ups) {
                $upstreamsByProxy[$ups['proxy_host_id']][] = $ups;
            }
        }
        foreach ($proxies as &$p) {
            $p['upstreams'] = $upstreamsByProxy[$p['id']] ?? [];
        }
        unset($p);

        // Resumen métricas
        $totalCount    = $this->proxyModel->countAllResults(false);
        $activeCount   = $this->proxyModel->where('enabled', 1)->countAllResults(false);
        $sslCount      = $this->proxyModel->where('ssl_enabled', 1)->countAllResults(false);
        $wsCount       = $this->proxyModel->where('websocket_enabled', 1)->countAllResults(false);

        $data = [
            'title'        => 'Proxy Hosts',
            'activeMenu'   => 'proxy-hosts',
            'proxies'      => $proxies,
            'search'       => $search,
            'filterStatus' => $filterStatus,
            'totalCount'   => $totalCount,
            'activeCount'  => $activeCount,
            'sslCount'     => $sslCount,
            'wsCount'      => $wsCount,
        ];

        return view('template/header', $data)
            . view('proxy-hosts/index', $data)
            . view('template/footer');
    }

    /**
     * Muestra el formulario para crear un nuevo Proxy Host.
     */
    public function new(): string
    {
        $data = [
            'title'           => 'Nuevo Proxy Host',
            'activeMenu'      => 'proxy-hosts',
            'proxy'           => [
                'id'                     => null,
                'domain'                 => '',
                'domain_names'           => '',
                'scheme'                 => 'http',
                'forward_host'           => '',
                'forward_port'           => '',
                'ssl_enabled'            => 0,
                'websocket_enabled'      => 0,
                'force_https'            => 0,
                'enabled'                => 1,
                'custom_headers'         => '',
                'proxy_connect_timeout'  => 60,
                'proxy_read_timeout'     => 60,
                'proxy_send_timeout'     => 60,
                'client_max_body_size'   => 10,
                'proxy_ssl_verify'       => 0,
                'hsts_enabled'           => 0,
                'load_balancing_enabled' => 0,
                'lb_algorithm'           => 'round_robin',
            ],
            'upstreams'       => old('upstreams', []),
            'certificate'     => null,
            'certificates'    => $this->certModel->orderBy('domain', 'ASC')->findAll(),
            'allowedHeaders'  => array_values(array_unique(array_column(ProxyHostRules::ALLOWED_CUSTOM_HEADERS, 'name'))),
            'combinedDomains' => old('domain', ''),
            'isEdit'          => false,
            'extraScripts'    => ['assets/js/proxy-form.js'],
        ];

        return view('template/header', $data)
            . view('proxy-hosts/form', $data)
            . view('template/footer', $data);
    }

    /**
     * Procesa la creación de un nuevo Proxy Host con despliegue atómico en Nginx.
     */
    public function create(): ResponseInterface
    {
        $rawDomain = trim((string) $this->request->getPost('domain'));
        $rawAliases = (string) $this->request->getPost('domain_names');

        // Unificación estilo NPM / CloudPanel: permite escribir "ejemplo.com, www.ejemplo.com"
        $domainParts = preg_split('/[\s,]+/', $rawDomain, -1, PREG_SPLIT_NO_EMPTY);
        if (count($domainParts) > 1) {
            $primaryDomain = strtolower($domainParts[0]);
            $extraAliases  = array_slice($domainParts, 1);
            $combinedAliases = !empty($rawAliases) 
                ? implode(', ', array_merge($extraAliases, preg_split('/[\s,]+/', $rawAliases, -1, PREG_SPLIT_NO_EMPTY)))
                : implode(', ', $extraAliases);
            $normalizedAliases = $this->normalizeAliases($combinedAliases);
        } else {
            $primaryDomain = strtolower($rawDomain);
            $normalizedAliases = $this->normalizeAliases($rawAliases);
        }

        $rawHeaders = trim((string) $this->request->getPost('custom_headers'));
        $connectTimeout = $this->request->getPost('proxy_connect_timeout');
        $readTimeout    = $this->request->getPost('proxy_read_timeout');
        $sendTimeout    = $this->request->getPost('proxy_send_timeout');
        $maxBodySize    = $this->request->getPost('client_max_body_size');

        $isLb = $this->request->getPost('load_balancing_enabled') ? 1 : 0;
        $lbAlgorithm = strtolower(trim((string) $this->request->getPost('lb_algorithm')));
        if (!in_array($lbAlgorithm, ['round_robin', 'least_conn', 'ip_hash'], true)) {
            $lbAlgorithm = 'round_robin';
        }

        $parsedUpstreams = [];
        if ($isLb) {
            $lbResult = $this->parseUpstreamsFromRequest();
            if ($lbResult['error'] !== null) {
                return redirect()->back()->withInput()->with('error', $lbResult['error']);
            }
            $parsedUpstreams = $lbResult['servers'];
        }

        $forwardHost = trim((string) $this->request->getPost('forward_host'));
        $forwardPort = (int) $this->request->getPost('forward_port');

        if ($isLb && empty($forwardHost) && !empty($parsedUpstreams)) {
            $forwardHost = $parsedUpstreams[0]['server_host'];
            $forwardPort = $parsedUpstreams[0]['server_port'];
        }

        $data = [
            'domain'                 => $primaryDomain,
            'domain_names'           => $normalizedAliases,
            'scheme'                 => strtolower(trim((string) $this->request->getPost('scheme'))),
            'forward_host'           => $forwardHost,
            'forward_port'           => $forwardPort,
            'load_balancing_enabled' => $isLb,
            'lb_algorithm'           => $lbAlgorithm,
            'websocket_enabled'      => $this->request->getPost('websocket_enabled') ? 1 : 0,
            'enabled'                => $this->request->getPost('enabled') !== null ? ($this->request->getPost('enabled') ? 1 : 0) : 1,
            'custom_headers'         => $rawHeaders !== '' ? $rawHeaders : null,
            'proxy_connect_timeout'  => ($connectTimeout !== null && $connectTimeout !== '') ? $connectTimeout : 60,
            'proxy_read_timeout'     => ($readTimeout !== null && $readTimeout !== '') ? $readTimeout : 60,
            'proxy_send_timeout'     => ($sendTimeout !== null && $sendTimeout !== '') ? $sendTimeout : 60,
            'client_max_body_size'   => ($maxBodySize !== null && $maxBodySize !== '') ? $maxBodySize : 10,
            'proxy_ssl_verify'       => $this->request->getPost('proxy_ssl_verify') ? 1 : 0,
            'hsts_enabled'           => $this->request->getPost('hsts_enabled') ? 1 : 0,
        ];

        $ssl = $this->parseSslSelection($primaryDomain);
        if ($ssl['error']) {
            return redirect()->back()->withInput()->with('error', $ssl['error']);
        }
        $data = array_merge($data, $ssl['fields']);

        if (!$this->proxyModel->insert($data)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->proxyModel->errors());
        }

        // Normalizar cabeceras canónicas tras superar la validación
        $normalizedHeaders = $this->sanitizeCustomHeaders((string) $rawHeaders);
        if ($normalizedHeaders !== $data['custom_headers']) {
            $data['custom_headers'] = $normalizedHeaders;
            $this->proxyModel->update((int) $this->proxyModel->getInsertID(), ['custom_headers' => $normalizedHeaders]);
        }

        $newId = (int) $this->proxyModel->getInsertID();
        $data['id'] = $newId;

        if ($isLb && !empty($parsedUpstreams)) {
            $this->upstreamModel->syncForProxy($newId, $parsedUpstreams);
            $data['upstreams'] = $parsedUpstreams;
        }

        // Despliegue atómico en Nginx
        $nginxResult = $this->nginxManager->applyHost($data);
        if (!$nginxResult['success']) {
            // Rollback en base de datos si Nginx falla
            if ($isLb) {
                $this->upstreamModel->where('proxy_host_id', $newId)->delete();
            }
            $this->proxyModel->delete($newId);

            AuditLogModel::log(
                'proxy_create_failed',
                "Fallo al desplegar Nginx para [ID: {$newId}] {$data['domain']}: " . ($nginxResult['error'] ?? 'Desconocido')
            );

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al desplegar en Nginx: ' . ($nginxResult['error'] ?? 'Validación fallida.'));
        }

        // Registro en auditoría
        $targetDesc = $isLb 
            ? "Upstream [{$lbAlgorithm}] (" . count($parsedUpstreams) . " servidores)"
            : "{$data['scheme']}://{$data['forward_host']}:{$data['forward_port']}";
        AuditLogModel::log(
            'proxy_create',
            "Creado y desplegado Proxy Host [ID: {$newId}] {$data['domain']} -> {$targetDesc}"
        );

        // Emisión de certificado Let's Encrypt (opción "Solicitar nuevo" del paso SSL)
        $sslError = $this->issueNewCertificateIfRequested($newId, $ssl);
        if ($sslError !== null) {
            return redirect()->to(base_url('proxy-hosts'))
                ->with('error', "Proxy Host \"{$data['domain']}\" creado en HTTP, pero no se pudo emitir el certificado: {$sslError}");
        }

        return redirect()->to(base_url('proxy-hosts'))
            ->with('success', "Proxy Host \"{$data['domain']}\" configurado y aplicado en Nginx con éxito.");
    }

    /**
     * Muestra el formulario para editar un Proxy Host existente.
     */
    public function edit(int $id): string|ResponseInterface
    {
        $proxy = $this->proxyModel->find($id);

        if (!$proxy) {
            return redirect()->to(base_url('proxy-hosts'))
                ->with('error', 'El Proxy Host solicitado no existe.');
        }

        $combinedDefault = $proxy['domain'] . (!empty($proxy['domain_names']) ? ', ' . $proxy['domain_names'] : '');
        $certificate = $this->certModel->where('domain', $proxy['domain'])->first();
        $vhostConfig = $this->nginxManager->previewConfig($proxy);

        $hostDomain = strtolower(trim((string) $proxy['domain']));
        $allCertificates = $this->certModel->orderBy('domain', 'ASC')->findAll();
        // Solo listar certificados que cubran el dominio de este host
        $matchingCertificates = array_values(array_filter($allCertificates, function ($cert) use ($hostDomain) {
            return $this->doesCertificateCoverDomain((string) $cert['domain'], $hostDomain);
        }));

        $upstreams = $this->upstreamModel->getForProxy($id);

        $data = [
            'title'           => 'Editar Proxy Host',
            'activeMenu'      => 'proxy-hosts',
            'proxy'           => $proxy,
            'upstreams'       => old('upstreams', $upstreams),
            'certificate'     => $certificate,
            'certificates'    => $matchingCertificates,
            'allowedHeaders'  => array_values(array_unique(array_column(ProxyHostRules::ALLOWED_CUSTOM_HEADERS, 'name'))),
            'combinedDomains' => old('domain', $combinedDefault),
            'isEdit'          => true,
            'vhostConfig'     => $vhostConfig,
            'extraScripts'    => ['assets/js/proxy-form.js'],
        ];

        return view('template/header', $data)
            . view('proxy-hosts/form', $data)
            . view('template/footer', $data);
    }

    /**
     * Procesa la actualización de un Proxy Host existente con despliegue atómico en Nginx.
     */
    public function update(int $id): ResponseInterface
    {
        $existing = $this->proxyModel->find($id);
        if (!$existing) {
            return redirect()->to(base_url('proxy-hosts'))
                ->with('error', 'El Proxy Host solicitado no existe.');
        }

        $rawDomain = trim((string) $this->request->getPost('domain'));
        $rawAliases = (string) $this->request->getPost('domain_names');

        // Unificación estilo NPM / CloudPanel: permite escribir "ejemplo.com, www.ejemplo.com"
        $domainParts = preg_split('/[\s,]+/', $rawDomain, -1, PREG_SPLIT_NO_EMPTY);
        if (count($domainParts) > 1) {
            $primaryDomain = strtolower($domainParts[0]);
            $extraAliases  = array_slice($domainParts, 1);
            $combinedAliases = !empty($rawAliases) 
                ? implode(', ', array_merge($extraAliases, preg_split('/[\s,]+/', $rawAliases, -1, PREG_SPLIT_NO_EMPTY)))
                : implode(', ', $extraAliases);
            $normalizedAliases = $this->normalizeAliases($combinedAliases);
        } else {
            $primaryDomain = strtolower($rawDomain);
            $normalizedAliases = $this->normalizeAliases($rawAliases);
        }

        $rawHeaders = trim((string) $this->request->getPost('custom_headers'));
        $connectTimeout = $this->request->getPost('proxy_connect_timeout');
        $readTimeout    = $this->request->getPost('proxy_read_timeout');
        $sendTimeout    = $this->request->getPost('proxy_send_timeout');
        $maxBodySize    = $this->request->getPost('client_max_body_size');

        $isLb = $this->request->getPost('load_balancing_enabled') ? 1 : 0;
        $lbAlgorithm = strtolower(trim((string) $this->request->getPost('lb_algorithm')));
        if (!in_array($lbAlgorithm, ['round_robin', 'least_conn', 'ip_hash'], true)) {
            $lbAlgorithm = 'round_robin';
        }

        $parsedUpstreams = [];
        if ($isLb) {
            $lbResult = $this->parseUpstreamsFromRequest();
            if ($lbResult['error'] !== null) {
                return redirect()->back()->withInput()->with('error', $lbResult['error']);
            }
            $parsedUpstreams = $lbResult['servers'];
        }

        $forwardHost = trim((string) $this->request->getPost('forward_host'));
        $forwardPort = (int) $this->request->getPost('forward_port');

        if ($isLb && empty($forwardHost) && !empty($parsedUpstreams)) {
            $forwardHost = $parsedUpstreams[0]['server_host'];
            $forwardPort = $parsedUpstreams[0]['server_port'];
        }

        $data = [
            'id'                     => $id,
            'domain'                 => $primaryDomain,
            'domain_names'           => $normalizedAliases,
            'scheme'                 => strtolower(trim((string) $this->request->getPost('scheme'))),
            'forward_host'           => $forwardHost,
            'forward_port'           => $forwardPort,
            'load_balancing_enabled' => $isLb,
            'lb_algorithm'           => $lbAlgorithm,
            'websocket_enabled'      => $this->request->getPost('websocket_enabled') ? 1 : 0,
            'enabled'                => $this->request->getPost('enabled') !== null ? ($this->request->getPost('enabled') ? 1 : 0) : 0,
            'custom_headers'         => $rawHeaders !== '' ? $rawHeaders : null,
            'proxy_connect_timeout'  => ($connectTimeout !== null && $connectTimeout !== '') ? $connectTimeout : ($existing['proxy_connect_timeout'] ?? 60),
            'proxy_read_timeout'     => ($readTimeout !== null && $readTimeout !== '') ? $readTimeout : ($existing['proxy_read_timeout'] ?? 60),
            'proxy_send_timeout'     => ($sendTimeout !== null && $sendTimeout !== '') ? $sendTimeout : ($existing['proxy_send_timeout'] ?? 60),
            'client_max_body_size'   => ($maxBodySize !== null && $maxBodySize !== '') ? $maxBodySize : ($existing['client_max_body_size'] ?? 10),
            'proxy_ssl_verify'       => $this->request->getPost('proxy_ssl_verify') ? 1 : 0,
            'hsts_enabled'           => $this->request->getPost('hsts_enabled') ? 1 : 0,
        ];

        $ssl = $this->parseSslSelection($primaryDomain);
        if ($ssl['error']) {
            return redirect()->back()->withInput()->with('error', $ssl['error']);
        }
        $data = array_merge($data, $ssl['fields']);

        // Ignorar el ID actual en validación de unicidad al actualizar
        $this->proxyModel->setValidationRule(
            'domain',
            "required|min_length[3]|max_length[253]|valid_rfc1123_domain|is_unique[proxy_hosts.domain,id,{$id}]"
        );

        if (!$this->proxyModel->update($id, $data)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->proxyModel->errors());
        }

        // Normalizar cabeceras canónicas tras superar la validación
        $normalizedHeaders = $this->sanitizeCustomHeaders((string) $rawHeaders);
        if ($normalizedHeaders !== $data['custom_headers']) {
            $data['custom_headers'] = $normalizedHeaders;
            $this->proxyModel->update($id, ['custom_headers' => $normalizedHeaders]);
        }

        $previousUpstreams = $this->upstreamModel->getForProxy($id);
        if ($isLb) {
            $this->upstreamModel->syncForProxy($id, $parsedUpstreams);
            $data['upstreams'] = $parsedUpstreams;
        } else {
            $this->upstreamModel->where('proxy_host_id', $id)->delete();
            $data['upstreams'] = [];
        }

        // Aplicación atómica en Nginx
        $nginxResult = $this->nginxManager->applyHost($data);
        if (!$nginxResult['success']) {
            // Revertir en base de datos al estado anterior
            $this->proxyModel->update($id, $existing);
            $this->upstreamModel->syncForProxy($id, $previousUpstreams);
            $existing['upstreams'] = $previousUpstreams;
            $this->nginxManager->applyHost($existing);

            AuditLogModel::log(
                'proxy_update_failed',
                "Fallo al actualizar Nginx para [ID: {$id}] {$data['domain']}: " . ($nginxResult['error'] ?? 'Desconocido')
            );

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al aplicar cambios en Nginx: ' . ($nginxResult['error'] ?? 'Validación fallida.'));
        }

        // Registro en auditoría
        $targetDesc = $isLb 
            ? "Upstream [{$lbAlgorithm}] (" . count($parsedUpstreams) . " servidores)"
            : "{$data['scheme']}://{$data['forward_host']}:{$data['forward_port']}";
        AuditLogModel::log(
            'proxy_update',
            "Actualizado y desplegado Proxy Host [ID: {$id}] {$data['domain']} -> {$targetDesc}"
        );

        // Emisión de certificado Let's Encrypt (opción "Solicitar nuevo" del paso SSL)
        $sslError = $this->issueNewCertificateIfRequested($id, $ssl);
        if ($sslError !== null) {
            return redirect()->to(base_url('proxy-hosts'))
                ->with('error', "Proxy Host \"{$data['domain']}\" actualizado en HTTP, pero no se pudo emitir el certificado: {$sslError}");
        }

        return redirect()->to(base_url('proxy-hosts'))
            ->with('success', "Proxy Host \"{$data['domain']}\" actualizado y aplicado en Nginx con éxito.");
    }

    /**
     * Alterna rápidamente el estado (Activo / Inactivo) con reflejo en Nginx.
     */
    public function toggle(int $id): ResponseInterface
    {
        $existing = $this->proxyModel->find($id);
        if (!$existing) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => false, 'message' => 'Proxy Host no encontrado.']);
            }
            return redirect()->to(base_url('proxy-hosts'))->with('error', 'Proxy Host no encontrado.');
        }

        $newStatus = $this->proxyModel->toggleStatus($id);
        $statusLabel = $newStatus ? 'activado' : 'desactivado';

        $updated = $this->proxyModel->find($id);
        if ($newStatus) {
            $nginxResult = $this->nginxManager->applyHost($updated);
        } else {
            $nginxResult = $this->nginxManager->disableHost($id);
        }

        if (!$nginxResult['success']) {
            // Revertir estado si falla Nginx
            $this->proxyModel->update($id, ['enabled' => $existing['enabled']]);

            AuditLogModel::log(
                'proxy_toggle_failed',
                "Fallo al alternar estado en Nginx para [ID: {$id}]: " . ($nginxResult['error'] ?? 'Desconocido')
            );

            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Error en Nginx: ' . ($nginxResult['error'] ?? 'Fallo al alternar.'),
                ]);
            }
            return redirect()->to(base_url('proxy-hosts'))->with('error', 'Error en Nginx: ' . ($nginxResult['error'] ?? ''));
        }

        AuditLogModel::log(
            'proxy_toggle',
            "Proxy Host [ID: {$id}] {$existing['domain']} fue {$statusLabel} en Nginx."
        );

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'enabled' => $newStatus,
                'message' => "Proxy Host \"{$existing['domain']}\" {$statusLabel} en Nginx."
            ]);
        }

        return redirect()->to(base_url('proxy-hosts'))
            ->with('success', "Proxy Host \"{$existing['domain']}\" {$statusLabel} en Nginx.");
    }

    /**
     * Realiza un Health Check ultra ligero vía AJAX hacia el backend de destino o upstreams.
     */
    public function healthCheck(int $id): ResponseInterface
    {
        $proxy = $this->proxyModel->find($id);
        if (!$proxy) {
            return $this->response->setJSON([
                'success' => false,
                'status'  => 'offline',
                'detail'  => 'Host no encontrado',
            ]);
        }

        $targets = [];
        if (!empty($proxy['load_balancing_enabled'])) {
            $upstreams = $this->upstreamModel->getForProxy($id);
            foreach ($upstreams as $up) {
                $targets[] = [
                    'scheme' => $proxy['scheme'] ?? 'http',
                    'host'   => $up['server_host'],
                    'port'   => (int) $up['server_port'],
                ];
            }
        }

        if (empty($targets)) {
            $targets[] = [
                'scheme' => $proxy['scheme'] ?? 'http',
                'host'   => $proxy['forward_host'],
                'port'   => (int) $proxy['forward_port'],
            ];
        }

        $startTime    = microtime(true);
        $totalTargets = count($targets);
        $onlineCount  = 0;
        $bestLatency  = 999999;
        $lastDetail   = '';
        $httpCode     = 0;
        $servers      = [];

        foreach ($targets as $t) {
            $h = $t['host'];
            $p = (int) $t['port'];

            if ($p === 80 || $p === 8080) {
                $sch = 'http';
            } elseif ($p === 443 || $p === 8443) {
                $sch = 'https';
            } else {
                $sch = strtolower($t['scheme'] ?: 'http');
            }

            $ch = curl_init("{$sch}://{$h}:{$p}/");
            curl_setopt_array($ch, [
                CURLOPT_NOBODY         => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 1.5,
                CURLOPT_TIMEOUT        => 1.5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 2,
                CURLOPT_USERAGENT      => 'NetCrew-HealthCheck/1.0',
            ]);
            curl_exec($ch);
            $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $time  = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $errNo = curl_errno($ch);
            $errMsg = curl_error($ch);

            $lat = round(($time > 0 ? $time : 0.05) * 1000);
            $isTargetOnline = false;

            if ($errNo === 0 && $code > 0 && $code < 500) {
                $onlineCount++;
                $isTargetOnline = true;
                $httpCode = $code;
                if ($lat < $bestLatency) {
                    $bestLatency = $lat;
                }
            } else {
                // Si cURL falla, prueba socket TCP directo
                $fp = @fsockopen($h, $p, $sErr, $sMsg, 0.8);
                if ($fp) {
                    fclose($fp);
                    $onlineCount++;
                    $isTargetOnline = true;
                    if ($lat < $bestLatency) {
                        $bestLatency = $lat;
                    }
                } else {
                    $lastDetail = !empty($errMsg) ? $errMsg : ($sMsg ?: 'Conexión rechazada');
                }
            }

            $servers[] = [
                'host'      => $h,
                'port'      => $p,
                'is_online' => $isTargetOnline,
            ];
        }

        if ($bestLatency === 999999) {
            $bestLatency = round((microtime(true) - $startTime) * 1000);
        }

        if ($totalTargets > 1) {
            // Pool de múltiples servidores
            if ($onlineCount === $totalTargets) {
                // Ambos / todos responden bien -> Verde
                $status   = 'online';
                $isOnline = true;
                $detail   = "Pool 100% operativo: {$onlineCount}/{$totalTargets} activos ({$bestLatency}ms)";
            } elseif ($onlineCount > 0) {
                // Solo responde uno -> Amarillo
                $status   = 'warning';
                $isOnline = true;
                $detail   = "Parcial: {$onlineCount}/{$totalTargets} activo ({$bestLatency}ms)";
            } else {
                // No responde ninguno -> Rojo
                $status   = 'offline';
                $isOnline = false;
                $detail   = "Caído: 0/{$totalTargets} activos (" . ($lastDetail ?: 'Sin respuesta') . ")";
            }
        } else {
            // Destino único
            if ($onlineCount > 0) {
                $status   = 'online';
                $isOnline = true;
                $detail   = $httpCode > 0 ? "HTTP {$httpCode} ({$bestLatency}ms)" : "Puerto {$targets[0]['port']} activo ({$bestLatency}ms)";
            } else {
                $status   = 'offline';
                $isOnline = false;
                $detail   = $lastDetail ?: 'No responde / Conexión rechazada';
            }
        }

        return $this->response->setJSON([
            'success'    => true,
            'id'         => $id,
            'status'     => $status, // online | error | offline
            'is_online'  => $isOnline,
            'http_code'  => $httpCode,
            'detail'     => $detail,
            'latency_ms' => $bestLatency,
            'servers'    => $servers,
        ]);
    }

    /**
     * Elimina un Proxy Host y remueve su configuración de Nginx.
     */
    public function delete(int $id): ResponseInterface
    {
        $existing = $this->proxyModel->find($id);
        if (!$existing) {
            return redirect()->to(base_url('proxy-hosts'))
                ->with('error', 'El Proxy Host no existe o ya fue eliminado.');
        }

        // Remover de Nginx primero
        $this->nginxManager->removeHost($id);

        $this->upstreamModel->where('proxy_host_id', $id)->delete();
        $this->proxyModel->delete($id);

        AuditLogModel::log(
            'proxy_delete',
            "Eliminado Proxy Host [ID: {$id}] {$existing['domain']} ({$existing['scheme']}://{$existing['forward_host']}:{$existing['forward_port']})"
        );

        return redirect()->to(base_url('proxy-hosts'))
            ->with('success', "Proxy Host \"{$existing['domain']}\" eliminado.");
    }

    /**
     * Devuelve la vista previa de la configuración Nginx generada en formato JSON.
     */
    public function preview(int $id): ResponseInterface
    {
        $proxy = $this->proxyModel->find($id);
        if (!$proxy) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Proxy Host no encontrado.',
            ]);
        }

        try {
            $config = $this->nginxManager->previewConfig($proxy);
            return $this->response->setJSON([
                'success' => true,
                'domain'  => $proxy['domain'],
                'config'  => $config,
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error al generar configuración: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Actualiza directamente el archivo Vhost de Nginx (estilo CloudPanel) con validación atómica `nginx -t`.
     */
    public function updateVhost(int $id): ResponseInterface
    {
        $proxy = $this->proxyModel->find($id);
        if (!$proxy) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Proxy Host no encontrado.',
            ]);
        }

        $vhostContent = (string) $this->request->getPost('vhost');
        if (trim($vhostContent) === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'El contenido del Vhost no puede estar vacío.',
            ]);
        }

        $isEnabled = !empty($proxy['enabled']);
        $result = $this->nginxManager->applyVhost($id, $vhostContent, $isEnabled);

        if (!$result['success']) {
            AuditLogModel::log(
                'proxy_vhost_error',
                "Error al guardar Vhost Nginx para [ID: {$id}] {$proxy['domain']}: " . ($result['error'] ?? 'Desconocido')
            );

            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => $result['error'] ?? 'La sintaxis de Nginx es inválida.',
                'output'  => $result['output'] ?? '',
            ]);
        }

        // Guardar custom_vhost en la base de datos
        $this->proxyModel->update($id, [
            'custom_vhost' => $vhostContent,
        ]);

        AuditLogModel::log(
            'proxy_vhost_update',
            "Vhost Nginx personalizado aplicado para [ID: {$id}] {$proxy['domain']}"
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Configuración de Nginx (Vhost) validada y aplicada con éxito.',
        ]);
    }

    /**
     * Restablece el Vhost Nginx a la plantilla automática predeterminada basada en los campos visuales.
     */
    public function resetVhost(int $id): ResponseInterface
    {
        $proxy = $this->proxyModel->find($id);
        if (!$proxy) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Proxy Host no encontrado.',
            ]);
        }

        // Limpiar custom_vhost en la base de datos
        $this->proxyModel->update($id, [
            'custom_vhost' => null,
        ]);

        $proxy['custom_vhost'] = null;
        $result = $this->nginxManager->applyHost($proxy);

        if (!$result['success']) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error al restablecer Vhost: ' . ($result['error'] ?? 'Desconocido'),
            ]);
        }

        AuditLogModel::log(
            'proxy_vhost_reset',
            "Vhost Nginx restablecido a la plantilla estándar para [ID: {$id}] {$proxy['domain']}"
        );

        $freshVhost = $this->nginxManager->previewConfig($proxy);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Vhost Nginx restablecido a la plantilla estándar con éxito.',
            'vhost'   => $freshVhost,
        ]);
    }

    /**
     * Interpreta el selector de certificado SSL del formulario (estilo NPM / CloudPanel).
     *
     * Valores de `ssl_certificate`: "none" | "new" | <id de certificado existente>.
     * Sin el campo se mantiene compatibilidad con los interruptores heredados (`ssl_enabled`).
     *
     * @return array{mode: string, fields: array, force: int, email: string, error: ?string}
     */
    protected function parseSslSelection(string $primaryDomain = ''): array
    {
        $selection = trim((string) $this->request->getPost('ssl_certificate'));
        $force     = $this->request->getPost('force_https') ? 1 : 0;
        $email     = trim((string) $this->request->getPost('ssl_email'));
        $result    = ['mode' => 'none', 'fields' => [], 'force' => $force, 'email' => $email, 'error' => null];

        if ($selection === '') {
            $enabled = $this->request->getPost('ssl_enabled') ? 1 : 0;
            $result['mode']   = 'legacy';
            $result['fields'] = ['ssl_enabled' => $enabled, 'force_https' => $enabled ? $force : 0];
            return $result;
        }

        if ($selection === 'none') {
            $result['fields'] = ['ssl_enabled' => 0, 'force_https' => 0, 'certificate_id' => null];
            return $result;
        }

        if ($selection === 'new') {
            if (!$this->request->getPost('ssl_tos')) {
                $result['error'] = "Debes aceptar los términos de servicio de Let's Encrypt para solicitar el certificado.";
                return $result;
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['error'] = 'El correo de contacto de Let\'s Encrypt no es válido.';
                return $result;
            }
            // Se aplica primero en HTTP; SSL se activa al emitirse el certificado.
            $result['mode']   = 'new';
            $result['fields'] = ['ssl_enabled' => 0, 'force_https' => 0, 'certificate_id' => null];
            return $result;
        }

        if (ctype_digit($selection)) {
            $cert = $this->certModel->find((int) $selection);
            if ($cert) {
                if ($primaryDomain !== '' && !$this->doesCertificateCoverDomain((string) $cert['domain'], $primaryDomain)) {
                    $result['error'] = "El certificado SSL seleccionado ({$cert['domain']}) no es válido para el dominio {$primaryDomain}.";
                    return $result;
                }
                $result['mode']   = 'existing';
                $result['fields'] = ['ssl_enabled' => 1, 'force_https' => $force, 'certificate_id' => (int) $selection];
                return $result;
            }
        }

        $result['error'] = 'El certificado SSL seleccionado no es válido.';
        return $result;
    }

    /**
     * Comprueba si el dominio de un certificado SSL cubre el dominio del Proxy Host
     * (coincidencia exacta, variante www o certificado wildcard *.dominio.com).
     */
    public function doesCertificateCoverDomain(string $certDomain, string $hostDomain): bool
    {
        $certDomain = strtolower(trim($certDomain));
        $hostDomain = strtolower(trim($hostDomain));

        if ($certDomain === '' || $hostDomain === '') {
            return false;
        }

        if ($certDomain === $hostDomain || "www.{$certDomain}" === $hostDomain || $certDomain === "www.{$hostDomain}") {
            return true;
        }

        // Soporte wildcard: *.ejemplo.com cubre app.ejemplo.com y ejemplo.com
        if (str_starts_with($certDomain, '*.')) {
            $base = substr($certDomain, 2);
            if ($hostDomain === $base || str_ends_with($hostDomain, '.' . $base)) {
                return true;
            }
        }

        // Permitir mismo dominio raíz (ej. certificados SAN multi-subdominio bajo empresa.com), pero bloquear dominios ajenos (esweb.es en pepe.com)
        $certParts = explode('.', ltrim($certDomain, '*.'));
        $hostParts = explode('.', ltrim($hostDomain, '*.'));
        if (count($certParts) >= 2 && count($hostParts) >= 2) {
            $certBase = implode('.', array_slice($certParts, -2));
            $hostBase = implode('.', array_slice($hostParts, -2));
            if ($certBase === $hostBase) {
                return true;
            }
        }

        return false;
    }

    /**
     * Si el usuario eligió "Solicitar nuevo", emite el certificado y lo vincula al host.
     *
     * @return string|null Mensaje de error, o null si todo fue bien / no aplica.
     */
    protected function issueNewCertificateIfRequested(int $hostId, array $ssl): ?string
    {
        if ($ssl['mode'] !== 'new') {
            return null;
        }

        $host = $this->proxyModel->find($hostId);
        if (!$host) {
            return 'Proxy Host no encontrado.';
        }

        try {
            $aliases = $this->proxyModel->getAliasesList($host);
            $result  = $this->sslManager->issueCertificate(
                $host['domain'],
                $aliases,
                $ssl['email'] !== '' ? $ssl['email'] : null
            );
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        if (!$result['success']) {
            return (string) ($result['error'] ?? 'Error desconocido.');
        }

        $this->proxyModel->update($hostId, [
            'certificate_id' => $result['certificate_id'],
            'ssl_enabled'    => 1,
            'force_https'    => $ssl['force'],
        ]);

        $applied = $this->nginxManager->applyHost($this->proxyModel->find($hostId));
        return $applied['success'] ? null : (string) $applied['error'];
    }

    /**
     * Limpia y normaliza la cadena de aliases separados por coma.
     */
    protected function normalizeAliases(string $raw): ?string
    {
        if (trim($raw) === '') {
            return null;
        }

        $items = preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($items)) {
            return null;
        }

        $unique = array_unique(array_map('strtolower', array_map('trim', $items)));
        return implode(', ', $unique);
    }

    /**
     * Sanitiza y normaliza cabeceras personalizadas utilizando la lista blanca de ProxyHostRules.
     */
    protected function sanitizeCustomHeaders(string $raw): ?string
    {
        $parsed = ProxyHostRules::parseAndValidateHeaders($raw);
        return $parsed['valid'] ? $parsed['normalized'] : null;
    }

    /**
     * Renueva el certificado SSL vinculado a este Proxy Host.
     */
    public function renewSsl(int $id): ResponseInterface
    {
        $proxy = $this->proxyModel->find($id);
        if (!$proxy) {
            return redirect()->to(base_url('proxy-hosts'))
                ->with('error', 'Proxy host no encontrado.');
        }

        $certId = $proxy['certificate_id'] ?? null;
        if (!$certId) {
            $cert = $this->certModel->where('domain', $proxy['domain'])->first();
            $certId = $cert['id'] ?? null;
        }

        if (!$certId) {
            return redirect()->to(base_url('proxy-hosts/edit/' . $id))
                ->with('error', 'El host no tiene ningún certificado SSL vinculado.');
        }

        $result = $this->sslManager->renewCertificate((int) $certId);
        $referer = $this->request->getServer('HTTP_REFERER');
        $fallbackUrl = base_url('proxy-hosts');
        $redirectUrl = $fallbackUrl;

        // Validar que el HTTP_REFERER sea interno y pertenezca a este host para prevenir Open Redirect
        if (!empty($referer)) {
            $parsedReferer = parse_url($referer);
            $parsedApp = parse_url(base_url());
            if (isset($parsedReferer['host']) && isset($parsedApp['host']) && strtolower($parsedReferer['host']) === strtolower($parsedApp['host'])) {
                $redirectUrl = $referer;
            }
        }

        if (!$result['success']) {
            return redirect()->to($redirectUrl)
                ->with('error', $result['error'] ?? 'Error al renovar el certificado SSL.');
        }

        // Recargar Nginx con el nuevo certificado
        $this->nginxManager->applyHost($this->proxyModel->find($id));

        return redirect()->to($redirectUrl)
            ->with('success', $result['message'] ?? 'Certificado SSL renovado con éxito.');
    }

    /**
     * Parsea y valida los servidores backend para balanceo de carga desde la petición POST.
     *
     * @return array{error: ?string, servers: array}
     */
    protected function parseUpstreamsFromRequest(): array
    {
        $rawServers = $this->request->getPost('upstreams');
        if (!is_array($rawServers) || empty($rawServers)) {
            return [
                'error'   => 'Debes configurar al menos un servidor cuando el balanceo de carga está habilitado.',
                'servers' => [],
            ];
        }

        $rawServers = array_slice($rawServers, 0, 2);
        $rules = new ProxyHostRules();
        $validatedServers = [];

        foreach ($rawServers as $idx => $srv) {
            $host = trim((string) ($srv['server_host'] ?? ''));
            $port = (int) ($srv['server_port'] ?? 0);

            // Permitir ignorar filas vacías (ej. segundo servidor no configurado)
            if ($host === '') {
                continue;
            }

            $hostError = null;
            if (!$rules->valid_forward_host($host, $hostError)) {
                return [
                    'error'   => 'Servidor backend #' . ($idx + 1) . ": {$hostError}",
                    'servers' => [],
                ];
            }

            if ($port < 1 || $port > 65535) {
                return [
                    'error'   => 'Servidor backend #' . ($idx + 1) . ': El puerto debe estar entre 1 y 65535.',
                    'servers' => [],
                ];
            }

            $weight = isset($srv['weight']) && (int) $srv['weight'] > 0 ? min(100, (int) $srv['weight']) : 1;
            $maxFails = isset($srv['max_fails']) && $srv['max_fails'] !== '' ? max(0, min(100, (int) $srv['max_fails'])) : 3;
            $failTimeout = isset($srv['fail_timeout']) && (int) $srv['fail_timeout'] > 0 ? min(3600, (int) $srv['fail_timeout']) : 10;
            $role = strtolower(trim((string) ($srv['role'] ?? '')));
            $isBackup = ($role === 'backup' || !empty($srv['is_backup'])) ? 1 : 0;
            $isDown = ($role === 'down' || !empty($srv['is_down'])) ? 1 : 0;

            $validatedServers[] = [
                'server_host'  => $host,
                'server_port'  => $port,
                'weight'       => $weight,
                'max_fails'    => $maxFails,
                'fail_timeout' => $failTimeout,
                'is_backup'    => $isBackup,
                'is_down'      => $isDown,
            ];
        }

        if (empty($validatedServers)) {
            return [
                'error'   => 'Debes configurar al menos un servidor backend válido.',
                'servers' => [],
            ];
        }

        return [
            'error'   => null,
            'servers' => $validatedServers,
        ];
    }
}
