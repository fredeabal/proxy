<?php
/**
 * Formulario de Proxy Host (Creación y Edición Unificadas con Editor Vhost estilo CloudPanel).
 *
 * Variables disponibles:
 *  - $proxy (array)
 *  - $certificates (array)
 *  - $certificate (array|null)
 *  - $combinedDomains (string)
 *  - $isEdit (bool)
 *  - $vhostConfig (string|null)
 */
$isEdit    = !empty($isEdit);
$actionUrl = $isEdit ? base_url('proxy-hosts/update/' . (int)$proxy['id']) : base_url('proxy-hosts/create');
$pageTitle = $isEdit ? 'Editar Proxy Host' : 'Nuevo Proxy Host';

// Selección actual de SSL: old() > certificado vinculado > certificado del dominio > ninguno
$currentSelection = old('ssl_certificate');
if ($currentSelection === null) {
    if (!empty($proxy['certificate_id'])) {
        $currentSelection = (string) $proxy['certificate_id'];
    } elseif (!empty($proxy['ssl_enabled']) && !empty($certificate)) {
        $currentSelection = (string) $certificate['id'];
    } else {
        $currentSelection = 'none';
    }
}
$isForce = (bool) old('force_https', $proxy['force_https'] ?? 0);
$isHsts  = (bool) old('hsts_enabled', $proxy['hsts_enabled'] ?? 0);
$isWs    = (bool) old('websocket_enabled', $proxy['websocket_enabled'] ?? 0);
$isEnabled = (bool) old('enabled', $proxy['enabled'] ?? 1);
$isRedirect = (bool) old('redirect_enabled', $proxy['redirect_enabled'] ?? 0);
$isLb       = !$isRedirect && (bool) old('load_balancing_enabled', $proxy['load_balancing_enabled'] ?? 0);
$currentMode = old('proxy_mode');
if (!in_array($currentMode, ['single', 'load_balancer', 'redirect'], true)) {
    if ($isRedirect) {
        $currentMode = 'redirect';
    } elseif ($isLb) {
        $currentMode = 'load_balancer';
    } else {
        $currentMode = 'single';
    }
}
$isRedirectMode   = ($currentMode === 'redirect');
$isLbMode         = ($currentMode === 'load_balancer');
$isSingleMode     = ($currentMode === 'single');
$redirectCode     = (int) old('redirect_code', $proxy['redirect_code'] ?? 301);
$redirectPreserve = (bool) old('redirect_preserve_path', $proxy['redirect_preserve_path'] ?? 1);
$currentLbAlgo = old('lb_algorithm', $proxy['lb_algorithm'] ?? 'round_robin');
$existingUpstreams = old('upstreams', $upstreams ?? []);
$isRateLimit       = (bool) old('rate_limit_enabled', $proxy['rate_limit_enabled'] ?? 0);
$rateLimitRequests = (int) old('rate_limit_requests', $proxy['rate_limit_requests'] ?? 10);
$rateLimitUnit     = old('rate_limit_unit', $proxy['rate_limit_unit'] ?? 'r/s');
if (!in_array($rateLimitUnit, ['r/s', 'r/m'], true)) {
    $rateLimitUnit = 'r/s';
}
$rateLimitBurst   = (int) old('rate_limit_burst', $proxy['rate_limit_burst'] ?? 20);
$rateLimitNodelay = (bool) old('rate_limit_nodelay', $proxy['rate_limit_nodelay'] ?? 1);
?>

<!-- Cabecera de Página -->
<div class="page-header">
  <h4 class="page-header-title"><?= esc($pageTitle) ?></h4>
  <div class="page-header-actions">
    <a href="<?= base_url('proxy-hosts') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
      <i class="ti ti-arrow-left"></i> Volver
    </a>
  </div>
</div>

<?php if ($isEdit): ?>
  <!-- Pestañas de Navegación estilo CloudPanel (Nav Tabs unificadas) -->
  <ul class="nav nav-tabs mb-4" id="proxyEditTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-general-btn" data-bs-toggle="tab" data-bs-target="#tab-general" type="button" role="tab" aria-controls="tab-general" aria-selected="true">
        <i class="ti ti-adjustments-horizontal me-1"></i> Configuración General
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-vhost-btn" data-bs-toggle="tab" data-bs-target="#tab-vhost" type="button" role="tab" aria-controls="tab-vhost" aria-selected="false">
        <i class="ti ti-file-code me-1"></i> Vhost Nginx
      </button>
    </li>
  </ul>
<?php endif; ?>

<div class="tab-content" id="proxyFormTabContent">
  <!-- =======================================================================
       1. PESTAÑA: CONFIGURACIÓN GENERAL (Tarjeta Única y Limpia)
       ======================================================================= -->
  <div class="tab-pane fade show active" id="tab-general" role="tabpanel" aria-labelledby="tab-general-btn">
    <div class="card border shadow-none mb-4">
      <div class="card-body p-3 p-md-4">
        <form id="proxyHostForm" method="post" action="<?= esc($actionUrl) ?>">
          <?= csrf_field() ?>

          <!-- SECCIÓN 1: ENRUTAMIENTO Y DESTINO -->
          <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
                <i class="ti ti-route fs-5"></i>
              </div>
              <h5 class="card-title fw-semibold mb-0">Enrutamiento y Destino</h5>
            </div>

            <div class="row g-3">
              <!-- Dominio(s) -->
              <div class="col-12">
                <label for="domain" class="form-label fw-semibold">Dominio(s) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="ti ti-world"></i></span>
                  <input type="text" 
                         id="domain" 
                         name="domain" 
                         class="form-control font-monospace <?= session('errors.domain') ? 'is-invalid' : '' ?>" 
                         placeholder="ej. midominio.com, www.midominio.com" 
                         value="<?= esc(old('domain', $combinedDomains ?? $proxy['domain'])) ?>">
                </div>
                <div class="form-text text-muted">
                  Dominio principal y aliases opcionales separados por comas.
                </div>
              </div>

              <!-- Modo y Protocolo -->
              <div class="col-12 col-md-4">
                <label for="proxy_mode" class="form-label fw-semibold">Modo</label>
                <select id="proxy_mode" name="proxy_mode" class="select2 form-control">
                  <option value="single" <?= $isSingleMode ? 'selected' : '' ?>>Servidor Único</option>
                  <option value="load_balancer" <?= $isLbMode ? 'selected' : '' ?>>Balanceo de Carga (Upstream Pool)</option>
                  <option value="redirect" <?= $isRedirectMode ? 'selected' : '' ?>>Redirección (HTTP 301 / 302)</option>
                </select>
              </div>

              <div class="col-12 col-md-2 proxy-target-col <?= $isRedirectMode ? 'd-none' : '' ?>">
                <label for="scheme" class="form-label fw-semibold">Protocolo <span class="text-danger">*</span></label>
                <select id="scheme" name="scheme" class="select2 form-control <?= session('errors.scheme') ? 'is-invalid' : '' ?>" <?= $isRedirectMode ? 'disabled' : '' ?>>
                  <?php $currentScheme = old('scheme', $proxy['scheme'] ?? 'http'); ?>
                  <option value="http" <?= $currentScheme === 'http' ? 'selected' : '' ?>>http://</option>
                  <option value="https" <?= $currentScheme === 'https' ? 'selected' : '' ?>>https://</option>
                </select>
              </div>

              <!-- CAMPOS MODO 1: DESTINO ÚNICO (Estilo estándar limpio) -->
              <div class="col-8 col-md-4 single-target-col <?= !$isSingleMode ? 'd-none' : '' ?>">
                <label for="forward_host" class="form-label fw-semibold">IP o Hostname <span class="text-danger">*</span></label>
                <input type="text" 
                       id="forward_host" 
                       name="forward_host" 
                       class="form-control font-monospace <?= session('errors.forward_host') ? 'is-invalid' : '' ?>" 
                       placeholder="ej. 127.0.0.1 o backend.local" 
                       value="<?= esc(old('forward_host', $proxy['forward_host'] ?? '')) ?>"
                       <?= !$isSingleMode ? 'disabled' : '' ?>>
              </div>

              <?php
                $forwardPortVal = old('forward_port');
                if ($forwardPortVal === null) {
                    $forwardPortVal = (!empty($proxy['forward_port']) && (int)$proxy['forward_port'] > 0) ? (int)$proxy['forward_port'] : ($isRedirectMode ? '' : 80);
                }
              ?>
              <div class="col-4 col-md-2 single-target-col <?= !$isSingleMode ? 'd-none' : '' ?>">
                <label for="forward_port" class="form-label fw-semibold">Puerto <span class="text-danger">*</span></label>
                <input type="number" 
                       id="forward_port" 
                       name="forward_port" 
                       class="form-control font-monospace <?= session('errors.forward_port') ? 'is-invalid' : '' ?>" 
                       placeholder="8080" 
                       min="1" 
                       max="65535" 
                       value="<?= esc((string)$forwardPortVal) ?>"
                       <?= !$isSingleMode ? 'disabled' : '' ?>>
              </div>

              <!-- CAMPOS MODO 3: REDIRECCIÓN HTTP 301 / 302 -->
              <div class="col-12 col-md-3 redirect-target-col <?= !$isRedirectMode ? 'd-none' : '' ?>">
                <label for="redirect_code" class="form-label fw-semibold">Código HTTP</label>
                <select id="redirect_code" name="redirect_code" class="select2 form-control" <?= !$isRedirectMode ? 'disabled' : '' ?>>
                  <option value="301" <?= $redirectCode === 301 ? 'selected' : '' ?>>301 (Permanente)</option>
                  <option value="302" <?= $redirectCode === 302 ? 'selected' : '' ?>>302 (Temporal)</option>
                </select>
              </div>

              <div class="col-12 col-md-5 redirect-target-col <?= !$isRedirectMode ? 'd-none' : '' ?>">
                <label for="redirect_url" class="form-label fw-semibold">URL de Destino <span class="text-danger">*</span></label>
                <input type="text"
                       inputmode="url"
                       id="redirect_url"
                       name="redirect_url"
                       class="form-control font-monospace <?= session('errors.redirect_url') ? 'is-invalid' : '' ?>"
                       placeholder="https://nuevo-dominio.com"
                       value="<?= esc(old('redirect_url', $proxy['redirect_url'] ?? '')) ?>"
                       <?= !$isRedirectMode ? 'disabled' : '' ?>>
              </div>

              <div class="col-12 redirect-target-col <?= !$isRedirectMode ? 'd-none' : '' ?>">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input" type="checkbox" role="switch" id="redirect_preserve_path" name="redirect_preserve_path" value="1" <?= $redirectPreserve ? 'checked' : '' ?> <?= !$isRedirectMode ? 'disabled' : '' ?>>
                  <label class="form-check-label fw-semibold" for="redirect_preserve_path">Conservar ruta y parámetros (<code>$request_uri</code>)</label>
                </div>
                <div class="form-text text-muted ps-4">
                  Si está activo, <code>midominio.com/ruta?a=1</code> redirigirá a <code>destino.com/ruta?a=1</code>.
                </div>
              </div>

              <!-- CAMPO MODO 2: ALGORITMO DE BALANCEO -->
              <div class="col-12 col-md-6 lb-target-col <?= !$isLbMode ? 'd-none' : '' ?>">
                <label for="lb_algorithm" class="form-label fw-semibold">Algoritmo de Distribución</label>
                <select id="lb_algorithm" name="lb_algorithm" class="select2 form-control" <?= !$isLbMode ? 'disabled' : '' ?>>
                  <option value="round_robin" <?= $currentLbAlgo === 'round_robin' ? 'selected' : '' ?>>Round Robin (Equitativo por turnos)</option>
                  <option value="least_conn" <?= $currentLbAlgo === 'least_conn' ? 'selected' : '' ?>>Least Connections (Menor carga activa)</option>
                  <option value="ip_hash" <?= $currentLbAlgo === 'ip_hash' ? 'selected' : '' ?>>IP Hash (Persistencia por IP de cliente)</option>
                </select>
              </div>

              <!-- LISTA DE SERVIDORES (MÁXIMO 2) -->
              <?php
                $up0 = $existingUpstreams[0] ?? [
                    'server_host' => $proxy['forward_host'] ?? '',
                    'server_port' => (!empty($proxy['forward_port']) && (int)$proxy['forward_port'] > 0) ? (int)$proxy['forward_port'] : 80,
                    'role'        => 'active',
                ];
                if (empty($up0['server_port']) || (int)$up0['server_port'] <= 0) {
                    $up0['server_port'] = 80;
                }
                $up1 = $existingUpstreams[1] ?? [
                    'server_host' => '',
                    'server_port' => 80,
                    'role'        => 'active',
                ];
                if (empty($up1['server_port']) || (int)$up1['server_port'] <= 0) {
                    $up1['server_port'] = 80;
                }
                $isUp0Backup = !empty($up0['is_backup']) || (($up0['role'] ?? '') === 'backup');
                $isUp1Backup = !empty($up1['is_backup']) || (($up1['role'] ?? '') === 'backup');
              ?>
              <div id="loadBalancingPanel" class="col-12 lb-target-col <?= !$isLbMode ? 'd-none' : '' ?>">
                <div class="pt-2">
                  <!-- Servidor 1 -->
                  <div class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-5">
                      <label class="form-label small fw-semibold mb-1">IP o Hostname <span class="text-danger">*</span></label>
                      <input type="text" id="upstream_0_host" name="upstreams[0][server_host]" class="form-control font-monospace" placeholder="ej. 192.168.0.2" value="<?= esc($up0['server_host'] ?? '') ?>" <?= !$isLbMode ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small fw-semibold mb-1">Puerto <span class="text-danger">*</span></label>
                      <input type="number" id="upstream_0_port" name="upstreams[0][server_port]" class="form-control font-monospace" placeholder="8080" min="1" max="65535" value="<?= esc((string)$up0['server_port']) ?>" <?= !$isLbMode ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-6 col-md-4">
                      <label class="form-label small fw-semibold mb-1">Tipo de Servidor</label>
                      <select name="upstreams[0][role]" class="select2 form-control" <?= !$isLbMode ? 'disabled' : '' ?>>
                        <option value="active" <?= !$isUp0Backup ? 'selected' : '' ?>>Primario (Balanceo)</option>
                        <option value="backup" <?= $isUp0Backup ? 'selected' : '' ?>>Backup (Reserva)</option>
                      </select>
                    </div>
                    <input type="hidden" name="upstreams[0][weight]" value="1" <?= !$isLbMode ? 'disabled' : '' ?>>
                    <input type="hidden" name="upstreams[0][max_fails]" value="3" <?= !$isLbMode ? 'disabled' : '' ?>>
                    <input type="hidden" name="upstreams[0][fail_timeout]" value="10" <?= !$isLbMode ? 'disabled' : '' ?>>
                  </div>

                  <!-- Servidor 2 -->
                  <div class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-5">
                      <label class="form-label small fw-semibold mb-1">IP o Hostname</label>
                      <input type="text" id="upstream_1_host" name="upstreams[1][server_host]" class="form-control font-monospace" placeholder="ej. 192.168.0.3" value="<?= esc($up1['server_host'] ?? '') ?>" <?= !$isLbMode ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small fw-semibold mb-1">Puerto</label>
                      <input type="number" id="upstream_1_port" name="upstreams[1][server_port]" class="form-control font-monospace" placeholder="8080" min="1" max="65535" value="<?= esc((string)$up1['server_port']) ?>" <?= !$isLbMode ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-6 col-md-4">
                      <label class="form-label small fw-semibold mb-1">Tipo de Servidor</label>
                      <select name="upstreams[1][role]" class="select2 form-control" <?= !$isLbMode ? 'disabled' : '' ?>>
                        <option value="active" <?= !$isUp1Backup ? 'selected' : '' ?>>Primario (Balanceo)</option>
                        <option value="backup" <?= $isUp1Backup ? 'selected' : '' ?>>Backup (Reserva)</option>
                      </select>
                    </div>
                    <input type="hidden" name="upstreams[1][weight]" value="1" <?= !$isLbMode ? 'disabled' : '' ?>>
                    <input type="hidden" name="upstreams[1][max_fails]" value="3" <?= !$isLbMode ? 'disabled' : '' ?>>
                    <input type="hidden" name="upstreams[1][fail_timeout]" value="10" <?= !$isLbMode ? 'disabled' : '' ?>>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <hr class="my-4 opacity-10">

          <!-- SECCIÓN 2: OPCIONES DE CONEXIÓN -->
          <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
                <i class="ti ti-adjustments fs-5"></i>
              </div>
              <h5 class="card-title fw-semibold mb-0">Opciones de Conexión</h5>
            </div>

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" <?= $isEnabled ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="enabled">Host Habilitado en Nginx</label>
                </div>
                <div class="form-text text-muted ps-4">
                  Activa o desactiva este proxy en el servidor Nginx sin perder su configuración.
                </div>
              </div>

              <div class="col-12 col-md-6 proxy-target-col <?= $isRedirectMode ? 'd-none' : '' ?>">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input" type="checkbox" role="switch" id="websocket_enabled" name="websocket_enabled" value="1" <?= $isWs ? 'checked' : '' ?> <?= $isRedirectMode ? 'disabled' : '' ?>>
                  <label class="form-check-label fw-semibold" for="websocket_enabled">Soporte WebSockets</label>
                </div>
                <div class="form-text text-muted ps-4">
                  Habilita cabeceras <code>Upgrade</code> y <code>Connection</code> para Socket.io y WebSockets.
                </div>
              </div>

              <!-- Rate Limiting por Dominio (disponible para todos los modos) -->
              <div class="col-12">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input" type="checkbox" role="switch" id="rate_limit_enabled" name="rate_limit_enabled" value="1" <?= $isRateLimit ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="rate_limit_enabled">Rate Limiting</label>
                </div>
                <div class="form-text text-muted ps-4">
                  Limita las peticiones por IP para este dominio en Nginx (responde HTTP <code>429</code> al superar el límite).
                </div>
              </div>

              <div id="rateLimitPanel" class="col-12 <?= !$isRateLimit ? 'd-none' : '' ?>">
                <div class="row g-3 pt-1 ps-md-4">
                  <div class="col-6 col-md-3">
                    <label for="rate_limit_requests" class="form-label small fw-semibold mb-1">Peticiones <span class="text-danger">*</span></label>
                    <input type="number"
                           id="rate_limit_requests"
                           name="rate_limit_requests"
                           class="form-control font-monospace <?= session('errors.rate_limit_requests') ? 'is-invalid' : '' ?>"
                           min="1"
                           max="10000"
                           value="<?= esc((string) $rateLimitRequests) ?>">
                  </div>

                  <div class="col-6 col-md-3">
                    <label for="rate_limit_unit" class="form-label small fw-semibold mb-1">Intervalo</label>
                    <select id="rate_limit_unit" name="rate_limit_unit" class="select2 form-control">
                      <option value="r/s" <?= $rateLimitUnit === 'r/s' ? 'selected' : '' ?>>Por segundo (r/s)</option>
                      <option value="r/m" <?= $rateLimitUnit === 'r/m' ? 'selected' : '' ?>>Por minuto (r/m)</option>
                    </select>
                  </div>

                  <div class="col-6 col-md-3">
                    <label for="rate_limit_burst" class="form-label small fw-semibold mb-1">Ráfaga (Burst)</label>
                    <input type="number"
                           id="rate_limit_burst"
                           name="rate_limit_burst"
                           class="form-control font-monospace <?= session('errors.rate_limit_burst') ? 'is-invalid' : '' ?>"
                           min="0"
                           max="10000"
                           value="<?= esc((string) $rateLimitBurst) ?>">
                  </div>

                  <div class="col-6 col-md-3">
                    <label for="rate_limit_nodelay" class="form-label small fw-semibold mb-1">Sin retardo (<code>nodelay</code>)</label>
                    <select id="rate_limit_nodelay" name="rate_limit_nodelay" class="select2 form-control">
                      <option value="1" <?= $rateLimitNodelay ? 'selected' : '' ?>>Sí (nodelay)</option>
                      <option value="0" <?= !$rateLimitNodelay ? 'selected' : '' ?>>No (con retardo)</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <hr class="my-4 opacity-10">

          <!-- SECCIÓN 3: CERTIFICADO SSL / HTTPS -->
          <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
                <i class="ti ti-shield-lock fs-5"></i>
              </div>
              <h5 class="card-title fw-semibold mb-0">Certificado SSL / HTTPS</h5>
            </div>

            <div class="row g-3">
              <!-- Selector de Certificado -->
              <div class="col-12">
                <label for="ssl_certificate" class="form-label fw-semibold">Certificado SSL</label>
                <select id="ssl_certificate" name="ssl_certificate" class="select2 form-control">
                  <option value="none" <?= $currentSelection === 'none' ? 'selected' : '' ?>>Ninguno (Solo HTTP)</option>
                  <option value="new" <?= $currentSelection === 'new' ? 'selected' : '' ?>>Solicitar nuevo certificado con Let's Encrypt</option>
                  <?php foreach (($certificates ?? []) as $cert): ?>
                    <?php 
                      $expTs = !empty($cert['expires_at']) ? strtotime($cert['expires_at']) : 0; 
                      $certDomain = strtolower((string)$cert['domain']);
                    ?>
                    <option value="<?= (int) $cert['id'] ?>"
                            data-cert-domain="<?= esc($certDomain) ?>"
                            <?= $currentSelection === (string) $cert['id'] ? 'selected' : '' ?>>
                      <?= esc($cert['domain']) ?> (<?= $expTs ? 'Vence: ' . date('d/m/Y', $expTs) : 'Let\'s Encrypt' ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text text-muted">
                  Selecciona un certificado existente que cubra este dominio o solicita uno nuevo vía Let's Encrypt.
                </div>
              </div>

              <!-- Panel Let's Encrypt (cuando se selecciona "new") -->
              <div class="col-12 d-none" id="letsEncryptPanel">
                <div class="row g-3 pt-1">
                  <div class="col-12 col-md-6">
                    <label for="ssl_email" class="form-label fw-semibold">Correo de contacto (Opcional)</label>
                    <input type="email" id="ssl_email" name="ssl_email" class="form-control" placeholder="admin@midominio.com" value="<?= esc(old('ssl_email', '')) ?>">
                    <div class="form-text text-muted">Para avisos de expiración emitidos por Let's Encrypt.</div>
                  </div>
                  <div class="col-12">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="ssl_tos" name="ssl_tos" value="1" <?= old('ssl_tos') ? 'checked' : '' ?>>
                      <label class="form-check-label fw-semibold" for="ssl_tos">Acepto los términos de servicio de Let's Encrypt</label>
                    </div>
                    <div class="form-text text-muted ps-4">
                      El DNS del dominio debe apuntar a este servidor antes de continuar.
                    </div>
                  </div>
                </div>
              </div>

              <!-- Panel Opciones HTTPS (cuando SSL está activo) -->
              <div class="col-12 d-none" id="sslOptionsPanel">
                <div class="row g-3">
                  <div class="col-12 col-md-6 proxy-target-col <?= $isRedirectMode ? 'd-none' : '' ?>">
                    <div class="form-check form-switch mb-1">
                      <input class="form-check-input" type="checkbox" role="switch" id="force_https" name="force_https" value="1" <?= $isForce ? 'checked' : '' ?> <?= $isRedirectMode ? 'disabled' : '' ?>>
                      <label class="form-check-label fw-semibold" for="force_https">Forzar Redirección HTTPS</label>
                    </div>
                    <div class="form-text text-muted ps-4">
                      Redirige automáticamente todo el tráfico del puerto 80 a HTTPS (puerto 443).
                    </div>
                  </div>

                  <div class="col-12 col-md-6">
                    <div class="form-check form-switch mb-1">
                      <input class="form-check-input" type="checkbox" role="switch" id="hsts_enabled" name="hsts_enabled" value="1" <?= $isHsts ? 'checked' : '' ?>>
                      <label class="form-check-label fw-semibold" for="hsts_enabled">Habilitar HSTS</label>
                    </div>
                    <div class="form-text text-muted ps-4">
                      Añade la cabecera <code>Strict-Transport-Security</code> para exigir conexiones seguras.
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- BOTONES DE ACCIÓN AL PIE (Centrados con 8px según Reglas 1.1 - 1.5) -->
          <div class="d-flex align-items-center justify-content-center gap-2 mt-4 pt-2">
            <a href="<?= base_url('proxy-hosts') ?>" class="btn btn-dark btn-sm d-inline-flex align-items-center gap-1">
              <i class="ti ti-x"></i> Cancelar
            </a>
            <?php if ($isEdit): ?>
              <button type="button" 
                      class="btn btn-danger btn-sm d-inline-flex align-items-center gap-1"
                      onclick="confirmDeleteHost(<?= (int)$proxy['id'] ?>, '<?= esc($proxy['domain'], 'js') ?>')">
                <i class="ti ti-trash"></i> Eliminar
              </button>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
              <i class="ti ti-device-floppy"></i> <?= $isEdit ? 'Guardar' : 'Crear' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if ($isEdit): ?>
    <!-- =======================================================================
         2. PESTAÑA: VHOST NGINX (Estilo CloudPanel)
         ======================================================================= -->
    <div class="tab-pane fade" id="tab-vhost" role="tabpanel" aria-labelledby="tab-vhost-btn">
      <div class="card border shadow-none mb-4">
        <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="d-flex align-items-center gap-2">
            <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
              <i class="ti ti-file-code fs-5"></i>
            </div>
            <div>
              <h5 class="card-title fw-semibold mb-0">Editor de Vhost Nginx</h5>
              <span class="text-muted small font-monospace">/etc/nginx/sites-available/<?= (int)$proxy['id'] ?>.conf</span>
            </div>
          </div>
          <button type="button" 
                  id="btnResetVhost" 
                  class="btn btn-dark btn-sm d-inline-flex align-items-center gap-1"
                  data-reset-url="<?= base_url('proxy-hosts/vhost-reset/' . (int)$proxy['id']) ?>"
                  title="Regenera el archivo Nginx a partir de las opciones del formulario visual">
            <i class="ti ti-rotate-clockwise"></i> Restablecer
          </button>
        </div>

        <div class="card-body p-3 p-md-4">
          <p class="small text-muted mb-3">
            Modifica directamente la configuración de Nginx. Al guardar, el servidor validará atómicamente la sintaxis mediante <code>nginx -t</code> antes de aplicar los cambios.
          </p>

          <!-- Editor de Vhost Nginx -->
          <textarea id="vhostEditor" 
                    class="form-control nginx-terminal nginx-vhost-editor p-3 font-monospace" 
                    rows="20" 
                    spellcheck="false"><?= esc($vhostConfig ?? '') ?></textarea>

          <!-- Cuadro de Alertas de Error de Nginx -->
          <div id="vhostAlertBox" class="alert alert-danger d-none mt-3 font-monospace small mb-0"></div>

          <!-- Barra de Acciones del Vhost (Centrados con 8px según Reglas 1.1 - 1.5) -->
          <div class="d-flex align-items-center justify-content-center gap-2 mt-4 pt-2">
            <a href="<?= base_url('proxy-hosts') ?>" class="btn btn-dark btn-sm d-inline-flex align-items-center gap-1">
              <i class="ti ti-x"></i> Cancelar
            </a>
            <button type="button" 
                    id="btnSaveVhost" 
                    class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1"
                    data-host-id="<?= (int)$proxy['id'] ?>"
                    data-save-url="<?= base_url('proxy-hosts/vhost/' . (int)$proxy['id']) ?>">
              <i class="ti ti-device-floppy"></i> Guardar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Formulario oculto para eliminación -->
    <form id="deleteHostForm" method="post" action="<?= base_url('proxy-hosts/delete/' . (int)$proxy['id']) ?>" class="d-none">
      <?= csrf_field() ?>
    </form>

    <script>
    function confirmDeleteHost(id, domain) {
      const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
      if (typeof Swal === 'undefined') {
        if (confirm(`¿Eliminar la configuración de "${domain}"?`)) {
          document.getElementById('deleteHostForm').submit();
        }
        return;
      }

      Swal.fire({
        title: '¿Eliminar Proxy Host?',
        text: `¿Estás seguro de que deseas eliminar la configuración para "${domain}"? Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        reverseButtons: true,
        buttonsStyling: false,
        background: isDark ? '#0b1114' : '#f8f9fa',
        color: isDark ? '#ffffff' : '#0b1114',
        customClass: {
          cancelButton: 'btn btn-dark btn-sm me-2',
          confirmButton: 'btn btn-danger btn-sm'
        },
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          document.getElementById('deleteHostForm').submit();
        }
      });
    }
    </script>
  <?php endif; ?>
</div>
