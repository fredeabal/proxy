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
$isLb    = (bool) old('load_balancing_enabled', $proxy['load_balancing_enabled'] ?? 0);
$currentLbAlgo = old('lb_algorithm', $proxy['lb_algorithm'] ?? 'round_robin');
$existingUpstreams = old('upstreams', $upstreams ?? []);
$hostType = old('host_type', $proxy['host_type'] ?? 'proxy');
$isRedirect = ($hostType === 'redirect');
$rateLimitEnabled = (bool) old('rate_limit_enabled', $proxy['rate_limit_enabled'] ?? 0);
$rateLimitNodelay = (bool) old('rate_limit_nodelay', $proxy['rate_limit_nodelay'] ?? 1);
$selectedAccessList = (string) old('access_list_id', $proxy['access_list_id'] ?? '');
$existingLocations = old('locations', $locations ?? []);
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

              <!-- Tipo de Host -->
              <div class="col-12 col-md-4">
                <label for="host_type" class="form-label fw-semibold">Tipo de Host</label>
                <select id="host_type" name="host_type" class="select2 form-control">
                  <option value="proxy" <?= !$isRedirect ? 'selected' : '' ?>>Proxy Inverso</option>
                  <option value="redirect" <?= $isRedirect ? 'selected' : '' ?>>Redirección HTTP (301/302)</option>
                </select>
              </div>

              <!-- CAMPOS REDIRECCIÓN -->
              <div class="col-12 col-md-5 redirect-target-col <?= !$isRedirect ? 'd-none' : '' ?>">
                <label for="redirect_url" class="form-label fw-semibold">URL de Destino <span class="text-danger">*</span></label>
                <input type="url" id="redirect_url" name="redirect_url" class="form-control font-monospace"
                       placeholder="https://nuevo-dominio.com"
                       value="<?= esc(old('redirect_url', $proxy['redirect_url'] ?? '')) ?>">
              </div>
              <div class="col-6 col-md-3 redirect-target-col <?= !$isRedirect ? 'd-none' : '' ?>">
                <label for="redirect_code" class="form-label fw-semibold">Código HTTP</label>
                <?php $rCode = (int) old('redirect_code', $proxy['redirect_code'] ?? 301); ?>
                <select id="redirect_code" name="redirect_code" class="select2 form-control">
                  <option value="301" <?= $rCode === 301 ? 'selected' : '' ?>>301 (Permanente)</option>
                  <option value="302" <?= $rCode === 302 ? 'selected' : '' ?>>302 (Temporal)</option>
                  <option value="307" <?= $rCode === 307 ? 'selected' : '' ?>>307 (Temporal estricto)</option>
                  <option value="308" <?= $rCode === 308 ? 'selected' : '' ?>>308 (Permanente estricto)</option>
                </select>
              </div>
              <div class="col-12 redirect-target-col <?= !$isRedirect ? 'd-none' : '' ?>">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="preserve_path" name="preserve_path" value="1"
                         <?= old('preserve_path', $proxy['preserve_path'] ?? 1) ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="preserve_path">Preservar ruta y parámetros (<code>$request_uri</code>)</label>
                </div>
              </div>

              <!-- Modo y Protocolo (Solo Proxy Inverso) -->
              <div class="col-12 col-md-5 proxy-target-col <?= $isRedirect ? 'd-none' : '' ?>">
                <label for="load_balancing_enabled" class="form-label fw-semibold">Modo</label>
                <select id="load_balancing_enabled" name="load_balancing_enabled" class="select2 form-control">
                  <option value="0" <?= !$isLb ? 'selected' : '' ?>>Servidor Único</option>
                  <option value="1" <?= $isLb ? 'selected' : '' ?>>Balanceo de Carga (Upstream Pool)</option>
                </select>
              </div>

              <div class="col-12 col-md-3 proxy-target-col <?= $isRedirect ? 'd-none' : '' ?>">
                <label for="scheme" class="form-label fw-semibold">Protocolo <span class="text-danger">*</span></label>
                <select id="scheme" name="scheme" class="select2 form-control <?= session('errors.scheme') ? 'is-invalid' : '' ?>">
                  <?php $currentScheme = old('scheme', $proxy['scheme'] ?? 'http'); ?>
                  <option value="http" <?= $currentScheme === 'http' ? 'selected' : '' ?>>http://</option>
                  <option value="https" <?= $currentScheme === 'https' ? 'selected' : '' ?>>https://</option>
                </select>
              </div>

              <!-- CAMPOS MODO 1: DESTINO ÚNICO (Estilo estándar limpio) -->
              <div class="col-8 col-md-8 single-target-col proxy-target-col <?= ($isLb || $isRedirect) ? 'd-none' : '' ?>">
                <label for="forward_host" class="form-label fw-semibold">IP o Hostname <span class="text-danger">*</span></label>
                <input type="text" 
                       id="forward_host" 
                       name="forward_host" 
                       class="form-control font-monospace <?= session('errors.forward_host') ? 'is-invalid' : '' ?>" 
                       placeholder="ej. 127.0.0.1 o backend.local" 
                       value="<?= esc(old('forward_host', $proxy['forward_host'] ?? '')) ?>">
              </div>

              <div class="col-4 col-md-4 single-target-col proxy-target-col <?= ($isLb || $isRedirect) ? 'd-none' : '' ?>">
                <label for="forward_port" class="form-label fw-semibold">Puerto <span class="text-danger">*</span></label>
                <input type="number" 
                       id="forward_port" 
                       name="forward_port" 
                       class="form-control font-monospace <?= session('errors.forward_port') ? 'is-invalid' : '' ?>" 
                       placeholder="8080" 
                       min="1" 
                       max="65535" 
                       value="<?= esc(old('forward_port', $proxy['forward_port'] ?? '')) ?>">
              </div>

              <!-- CAMPO MODO 2: ALGORITMO DE BALANCEO -->
              <div class="col-12 col-md-6 lb-target-col <?= !$isLb ? 'd-none' : '' ?>">
                <label for="lb_algorithm" class="form-label fw-semibold">Algoritmo de Distribución</label>
                <select id="lb_algorithm" name="lb_algorithm" class="select2 form-control">
                  <option value="round_robin" <?= $currentLbAlgo === 'round_robin' ? 'selected' : '' ?>>Round Robin (Equitativo por turnos)</option>
                  <option value="least_conn" <?= $currentLbAlgo === 'least_conn' ? 'selected' : '' ?>>Least Connections (Menor carga activa)</option>
                  <option value="ip_hash" <?= $currentLbAlgo === 'ip_hash' ? 'selected' : '' ?>>IP Hash (Persistencia por IP de cliente)</option>
                </select>
              </div>

              <!-- LISTA DE SERVIDORES (MÁXIMO 2) -->
              <?php
                $up0 = $existingUpstreams[0] ?? [
                    'server_host' => $proxy['forward_host'] ?? '',
                    'server_port' => $proxy['forward_port'] ?? 80,
                    'role'        => 'active',
                ];
                $up1 = $existingUpstreams[1] ?? [
                    'server_host' => '',
                    'server_port' => 80,
                    'role'        => 'active',
                ];
                $isUp0Backup = !empty($up0['is_backup']) || (($up0['role'] ?? '') === 'backup');
                $isUp1Backup = !empty($up1['is_backup']) || (($up1['role'] ?? '') === 'backup');
              ?>
              <div id="loadBalancingPanel" class="col-12 lb-target-col <?= !$isLb ? 'd-none' : '' ?>">
                <div class="pt-2">
                  <!-- Servidor 1 -->
                  <div class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-5">
                      <label class="form-label small fw-semibold mb-1">IP o Hostname <span class="text-danger">*</span></label>
                      <input type="text" id="upstream_0_host" name="upstreams[0][server_host]" class="form-control font-monospace" placeholder="ej. 192.168.0.2" value="<?= esc($up0['server_host'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small fw-semibold mb-1">Puerto <span class="text-danger">*</span></label>
                      <input type="number" id="upstream_0_port" name="upstreams[0][server_port]" class="form-control font-monospace" placeholder="8080" min="1" max="65535" value="<?= esc($up0['server_port'] ?? 80) ?>">
                    </div>
                    <div class="col-6 col-md-4">
                      <label class="form-label small fw-semibold mb-1">Tipo de Servidor</label>
                      <select name="upstreams[0][role]" class="select2 form-control">
                        <option value="active" <?= !$isUp0Backup ? 'selected' : '' ?>>Primario (Balanceo)</option>
                        <option value="backup" <?= $isUp0Backup ? 'selected' : '' ?>>Backup (Reserva)</option>
                      </select>
                    </div>
                    <input type="hidden" name="upstreams[0][weight]" value="1">
                    <input type="hidden" name="upstreams[0][max_fails]" value="3">
                    <input type="hidden" name="upstreams[0][fail_timeout]" value="10">
                  </div>

                  <!-- Servidor 2 -->
                  <div class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-5">
                      <label class="form-label small fw-semibold mb-1">IP o Hostname</label>
                      <input type="text" id="upstream_1_host" name="upstreams[1][server_host]" class="form-control font-monospace" placeholder="ej. 192.168.0.3" value="<?= esc($up1['server_host'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                      <label class="form-label small fw-semibold mb-1">Puerto</label>
                      <input type="number" id="upstream_1_port" name="upstreams[1][server_port]" class="form-control font-monospace" placeholder="8080" min="1" max="65535" value="<?= esc($up1['server_port'] ?? 80) ?>">
                    </div>
                    <div class="col-6 col-md-4">
                      <label class="form-label small fw-semibold mb-1">Tipo de Servidor</label>
                      <select name="upstreams[1][role]" class="select2 form-control">
                        <option value="active" <?= !$isUp1Backup ? 'selected' : '' ?>>Primario (Balanceo)</option>
                        <option value="backup" <?= $isUp1Backup ? 'selected' : '' ?>>Backup (Reserva)</option>
                      </select>
                    </div>
                    <input type="hidden" name="upstreams[1][weight]" value="1">
                    <input type="hidden" name="upstreams[1][max_fails]" value="3">
                    <input type="hidden" name="upstreams[1][fail_timeout]" value="10">
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
                  <input class="form-check-input" type="checkbox" role="switch" id="websocket_enabled" name="websocket_enabled" value="1" <?= $isWs ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="websocket_enabled">Soporte WebSockets</label>
                </div>
                <div class="form-text text-muted ps-4">
                  Habilita cabeceras <code>Upgrade</code> y <code>Connection</code> para Socket.io y WebSockets.
                </div>
              </div>

              <div class="col-12 col-md-6">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" <?= $isEnabled ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="enabled">Host Habilitado en Nginx</label>
                </div>
                <div class="form-text text-muted ps-4">
                  Activa o desactiva este proxy en el servidor Nginx sin perder su configuración.
                </div>
              </div>
            </div>
          </div>

          <hr class="my-4 opacity-10">

          <!-- SECCIÓN 2B: ACCESS LIST Y RATE LIMITING -->
          <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
                <i class="ti ti-shield-lock fs-5"></i>
              </div>
              <h5 class="card-title fw-semibold mb-0">Control de Acceso y Rate Limiting</h5>
            </div>

            <div class="row g-3">
              <!-- Access List -->
              <div class="col-12 col-md-6">
                <label for="access_list_id" class="form-label fw-semibold">Access List (IP / Auth Basic)</label>
                <select id="access_list_id" name="access_list_id" class="select2 form-control">
                  <option value="">Ninguna (Acceso público)</option>
                  <?php foreach (($accessLists ?? []) as $al): ?>
                    <option value="<?= (int) $al['id'] ?>" <?= $selectedAccessList === (string) $al['id'] ? 'selected' : '' ?>>
                      <?= esc($al['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text text-muted">
                  Aplica reglas IP/CIDR y autenticación básica desde <a href="<?= base_url('access-lists') ?>">Access Lists</a>.
                </div>
              </div>

              <!-- Rate Limiting Switch -->
              <div class="col-12 col-md-6 d-flex align-items-center">
                <div>
                  <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch" id="rate_limit_enabled" name="rate_limit_enabled" value="1" <?= $rateLimitEnabled ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="rate_limit_enabled">Activar Rate Limiting por IP</label>
                  </div>
                  <div class="form-text text-muted ps-4">
                    Protege el servicio contra abuso y ráfagas mediante <code>limit_req_zone</code> y <code>burst</code>.
                  </div>
                </div>
              </div>

              <!-- Rate Limiting Opciones -->
              <div class="col-12 <?= !$rateLimitEnabled ? 'd-none' : '' ?>" id="rateLimitPanel">
                <div class="row g-3 p-3 rounded border">
                  <div class="col-6 col-md-3">
                    <label for="rate_limit_requests" class="form-label small fw-semibold">Peticiones</label>
                    <input type="number" id="rate_limit_requests" name="rate_limit_requests" class="form-control font-monospace" min="1" max="10000"
                           value="<?= esc(old('rate_limit_requests', $proxy['rate_limit_requests'] ?? 10)) ?>">
                  </div>
                  <div class="col-6 col-md-3">
                    <label for="rate_limit_unit" class="form-label small fw-semibold">Por Unidad</label>
                    <?php $rlUnit = old('rate_limit_unit', $proxy['rate_limit_unit'] ?? 's'); ?>
                    <select id="rate_limit_unit" name="rate_limit_unit" class="select2 form-control">
                      <option value="s" <?= $rlUnit === 's' ? 'selected' : '' ?>>Segundo (r/s)</option>
                      <option value="m" <?= $rlUnit === 'm' ? 'selected' : '' ?>>Minuto (r/m)</option>
                    </select>
                  </div>
                  <div class="col-6 col-md-3">
                    <label for="rate_limit_burst" class="form-label small fw-semibold">Burst (Ráfaga)</label>
                    <input type="number" id="rate_limit_burst" name="rate_limit_burst" class="form-control font-monospace" min="0" max="10000"
                           value="<?= esc(old('rate_limit_burst', $proxy['rate_limit_burst'] ?? 20)) ?>">
                  </div>
                  <div class="col-6 col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                      <input class="form-check-input" type="checkbox" role="switch" id="rate_limit_nodelay" name="rate_limit_nodelay" value="1" <?= $rateLimitNodelay ? 'checked' : '' ?>>
                      <label class="form-check-label small fw-semibold" for="rate_limit_nodelay"><code>nodelay</code></label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- SECCIÓN 2C: MÚLTIPLES BLOQUES LOCATION (Solo Proxy Inverso) -->
          <div class="mb-4 proxy-target-col <?= $isRedirect ? 'd-none' : '' ?>" id="customLocationsSection">
            <hr class="my-4 opacity-10">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <div class="d-flex align-items-center gap-2">
                <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
                  <i class="ti ti-folders fs-5"></i>
                </div>
                <h5 class="card-title fw-semibold mb-0">Rutas Personalizadas (<code>location</code>)</h5>
              </div>
              <button type="button" class="btn btn-outline-primary btn-sm" id="addCustomLocationBtn">
                <i class="ti ti-plus"></i> Añadir Ruta
              </button>
            </div>

            <div id="customLocationsContainer" class="d-flex flex-column gap-2">
              <?php foreach ($existingLocations as $idx => $loc): ?>
                <div class="row g-2 align-items-center custom-location-row p-2 rounded border">
                  <div class="col-12 col-md-3">
                    <input type="text" name="locations[<?= (int) $idx ?>][path]" class="form-control font-monospace form-control-sm"
                           placeholder="/api" value="<?= esc($loc['path'] ?? '') ?>">
                  </div>
                  <div class="col-4 col-md-2">
                    <select name="locations[<?= (int) $idx ?>][scheme]" class="form-select form-select-sm">
                      <option value="http" <?= ($loc['scheme'] ?? 'http') === 'http' ? 'selected' : '' ?>>http://</option>
                      <option value="https" <?= ($loc['scheme'] ?? '') === 'https' ? 'selected' : '' ?>>https://</option>
                    </select>
                  </div>
                  <div class="col-5 col-md-3">
                    <input type="text" name="locations[<?= (int) $idx ?>][forward_host]" class="form-control font-monospace form-control-sm"
                           placeholder="127.0.0.1" value="<?= esc($loc['forward_host'] ?? '') ?>">
                  </div>
                  <div class="col-3 col-md-2">
                    <input type="number" name="locations[<?= (int) $idx ?>][forward_port]" class="form-control font-monospace form-control-sm"
                           placeholder="3000" min="1" max="65535" value="<?= esc($loc['forward_port'] ?? '') ?>">
                  </div>
                  <div class="col-8 col-md-1">
                    <div class="form-check" title="WebSockets">
                      <input class="form-check-input" type="checkbox" name="locations[<?= (int) $idx ?>][websocket_enabled]" value="1"
                             <?= !empty($loc['websocket_enabled']) ? 'checked' : '' ?>>
                      <label class="form-check-label small">WS</label>
                    </div>
                  </div>
                  <div class="col-4 col-md-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-location-btn" title="Eliminar ruta">
                      <i class="ti ti-x"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="form-text text-muted mt-1">
              Define rutas específicas (ej. <code>/api</code> o <code>^~ /static</code>) hacia otro puerto o servidor dentro del mismo dominio.
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
                  <div class="col-12 col-md-6">
                    <div class="form-check form-switch mb-1">
                      <input class="form-check-input" type="checkbox" role="switch" id="force_https" name="force_https" value="1" <?= $isForce ? 'checked' : '' ?>>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
  const hostTypeSelect = document.getElementById('host_type');
  const rateLimitCheck = document.getElementById('rate_limit_enabled');
  const rateLimitPanel = document.getElementById('rateLimitPanel');

  function syncHostTypeUi() {
    if (!hostTypeSelect) return;
    const isRedirect = hostTypeSelect.value === 'redirect';
    document.querySelectorAll('.redirect-target-col').forEach(el => el.classList.toggle('d-none', !isRedirect));
    document.querySelectorAll('.proxy-target-col').forEach(el => {
      if (isRedirect) {
        el.classList.add('d-none');
      } else if (!el.classList.contains('single-target-col') && !el.classList.contains('lb-target-col')) {
        el.classList.remove('d-none');
      }
    });
    if (!isRedirect) {
      const lbSel = document.getElementById('load_balancing_enabled');
      if (lbSel) lbSel.dispatchEvent(new Event('change'));
    }
  }

  hostTypeSelect?.addEventListener('change', syncHostTypeUi);
  if (window.jQuery) {
    window.jQuery('#host_type').on('change.select2 change', syncHostTypeUi);
  }

  rateLimitCheck?.addEventListener('change', function () {
    rateLimitPanel?.classList.toggle('d-none', !this.checked);
  });

  let locIdx = <?= count($existingLocations) ?>;
  document.getElementById('addCustomLocationBtn')?.addEventListener('click', function () {
    const html = `
      <div class="row g-2 align-items-center custom-location-row p-2 rounded border">
        <div class="col-12 col-md-3">
          <input type="text" name="locations[${locIdx}][path]" class="form-control font-monospace form-control-sm" placeholder="/api">
        </div>
        <div class="col-4 col-md-2">
          <select name="locations[${locIdx}][scheme]" class="form-select form-select-sm">
            <option value="http">http://</option>
            <option value="https">https://</option>
          </select>
        </div>
        <div class="col-5 col-md-3">
          <input type="text" name="locations[${locIdx}][forward_host]" class="form-control font-monospace form-control-sm" placeholder="127.0.0.1">
        </div>
        <div class="col-3 col-md-2">
          <input type="number" name="locations[${locIdx}][forward_port]" class="form-control font-monospace form-control-sm" placeholder="3000" min="1" max="65535">
        </div>
        <div class="col-8 col-md-1">
          <div class="form-check" title="WebSockets">
            <input class="form-check-input" type="checkbox" name="locations[${locIdx}][websocket_enabled]" value="1">
            <label class="form-check-label small">WS</label>
          </div>
        </div>
        <div class="col-4 col-md-1 text-end">
          <button type="button" class="btn btn-outline-danger btn-sm remove-location-btn" title="Eliminar ruta"><i class="ti ti-x"></i></button>
        </div>
      </div>`;
    document.getElementById('customLocationsContainer')?.insertAdjacentHTML('beforeend', html);
    locIdx++;
  });

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.remove-location-btn');
    if (btn) {
      btn.closest('.custom-location-row')?.remove();
    }
  });
});
</script>
