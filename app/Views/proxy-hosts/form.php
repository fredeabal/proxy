<?php
/**
 * Formulario de Proxy Host (Creación y Edición Unificadas).
 *
 * Variables disponibles:
 *  - $proxy (array)
 *  - $certificates (array)
 *  - $certificate (array|null)
 *  - $combinedDomains (string)
 *  - $isEdit (bool)
 */
$isEdit    = !empty($isEdit);
$actionUrl = $isEdit ? base_url('proxy-hosts/update/' . $proxy['id']) : base_url('proxy-hosts/create');
$dataMode  = $isEdit ? 'edit' : 'create';
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
$isSslVerify = (bool) old('proxy_ssl_verify', $proxy['proxy_ssl_verify'] ?? 0);
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

<div class="card border shadow-none">
  <div class="card-body p-4">
    <div class="wizard-content">
      <form id="proxyWizardForm" 
            method="post" 
            action="<?= esc($actionUrl) ?>" 
            data-mode="<?= esc($dataMode) ?>"
            data-cancel-url="<?= base_url('proxy-hosts') ?>"
            class="tab-wizard wizard-circle wizard clearfix">

        <!-- ==========================================
             PASO 1: ENRUTAMIENTO (Routing)
             ========================================== -->
        <h6>Enrutamiento</h6>
        <section>
          <?= csrf_field() ?>
          <div class="row g-4 pt-2">
            <!-- 1 solo campo unificado de dominios estilo NPM / CloudPanel -->
            <div class="col-12">
              <label for="domain" class="form-label fw-semibold">Dominio(s) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-world"></i></span>
                <input type="text" 
                       id="domain" 
                       name="domain" 
                       class="form-control font-monospace <?= session('errors.domain') ? 'is-invalid' : '' ?>" 
                       placeholder="ej. midominio.com, www.midominio.com" 
                       value="<?= esc(old('domain', $combinedDomains ?? $proxy['domain'])) ?>" 
                       required>
              </div>
              <div class="form-text small text-muted">
                Ingresa el dominio principal o varios separados por comas o espacios.
              </div>
            </div>

            <!-- Destino del Enrutamiento (Forward Host) -->
            <div class="col-12 col-md-3">
              <label for="scheme" class="form-label fw-semibold">Protocolo <span class="text-danger">*</span></label>
              <select id="scheme" name="scheme" class="select2 form-control <?= session('errors.scheme') ? 'is-invalid' : '' ?>" required>
                <?php $currentScheme = old('scheme', $proxy['scheme'] ?? 'http'); ?>
                <option value="http" <?= $currentScheme === 'http' ? 'selected' : '' ?>>http://</option>
                <option value="https" <?= $currentScheme === 'https' ? 'selected' : '' ?>>https://</option>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label for="forward_host" class="form-label fw-semibold">IP o Hostname Destino <span class="text-danger">*</span></label>
              <input type="text" 
                     id="forward_host" 
                     name="forward_host" 
                     class="form-control font-monospace <?= session('errors.forward_host') ? 'is-invalid' : '' ?>" 
                     placeholder="ej. 127.0.0.1" 
                     value="<?= esc(old('forward_host', $proxy['forward_host'] ?? '')) ?>" 
                     required>
            </div>

            <div class="col-12 col-md-3">
              <label for="forward_port" class="form-label fw-semibold">Puerto <span class="text-danger">*</span></label>
              <input type="number" 
                     id="forward_port" 
                     name="forward_port" 
                     class="form-control font-monospace <?= session('errors.forward_port') ? 'is-invalid' : '' ?>" 
                     placeholder="ej. 8080" 
                     min="1" 
                     max="65535" 
                     value="<?= esc(old('forward_port', $proxy['forward_port'] ?? '')) ?>" 
                     required>
            </div>
          </div>
        </section>

        <!-- ==========================================
             PASO 2: OPCIONES
             ========================================== -->
        <h6>Opciones</h6>
        <section>
          <div class="row g-4 pt-2">
            <!-- Estado Habilitado -->
            <div class="col-12 col-md-4">
              <div class="p-3 rounded-2 border h-100">
                <div class="form-check form-switch mb-2">
                  <?php $isEnabled = (bool) old('enabled', $proxy['enabled'] ?? 1); ?>
                  <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" <?= $isEnabled ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="enabled">Host Habilitado</label>
                </div>
                <div class="small text-muted ps-4">
                  Permite recibir tráfico en este dominio.
                </div>
              </div>
            </div>

            <!-- Soporte WebSockets -->
            <div class="col-12 col-md-4">
              <div class="p-3 rounded-2 border h-100">
                <div class="form-check form-switch mb-2">
                  <?php $isWs = (bool) old('websocket_enabled', $proxy['websocket_enabled'] ?? 0); ?>
                  <input class="form-check-input" type="checkbox" role="switch" id="websocket_enabled" name="websocket_enabled" value="1" <?= $isWs ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="websocket_enabled">Soporte WebSockets</label>
                </div>
                <div class="small text-muted ps-4">
                  Para Socket.IO y conexiones en tiempo real.
                </div>
              </div>
            </div>

            <!-- Verificar SSL hacia el Backend -->
            <div class="col-12 col-md-4">
              <div class="p-3 rounded-2 border h-100">
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" role="switch" id="proxy_ssl_verify" name="proxy_ssl_verify" value="1" <?= $isSslVerify ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="proxy_ssl_verify">Verificar SSL del Backend</label>
                </div>
                <div class="small text-muted ps-4">
                  Desactivar si el backend usa certificados autofirmados.
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ==========================================
             PASO 3: AVANZADO (Advanced)
             ========================================== -->
        <h6>Avanzado</h6>
        <section>
          <div class="row g-4 pt-2">
            <!-- Timeouts y Tamaño Máximo de Subida -->
            <div class="col-12 col-sm-6 col-md-3">
              <label for="proxy_connect_timeout" class="form-label fw-semibold">Timeout Conexión (s)</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-plug-connected"></i></span>
                <input type="number"
                       id="proxy_connect_timeout"
                       name="proxy_connect_timeout"
                       class="form-control font-monospace <?= session('errors.proxy_connect_timeout') ? 'is-invalid' : '' ?>"
                       placeholder="Ej. 60"
                       min="1"
                       max="3600"
                       value="<?= esc(old('proxy_connect_timeout', $proxy['proxy_connect_timeout'] ?? 60)) ?>">
              </div>
              <div class="form-text small text-muted">1–3600 s</div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
              <label for="proxy_read_timeout" class="form-label fw-semibold">Timeout Lectura (s)</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-clock-hour-4"></i></span>
                <input type="number"
                       id="proxy_read_timeout"
                       name="proxy_read_timeout"
                       class="form-control font-monospace <?= session('errors.proxy_read_timeout') ? 'is-invalid' : '' ?>"
                       placeholder="Ej. 60"
                       min="1"
                       max="3600"
                       value="<?= esc(old('proxy_read_timeout', $proxy['proxy_read_timeout'] ?? 60)) ?>">
              </div>
              <div class="form-text small text-muted">1–3600 s</div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
              <label for="proxy_send_timeout" class="form-label fw-semibold">Timeout Envío (s)</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-send"></i></span>
                <input type="number"
                       id="proxy_send_timeout"
                       name="proxy_send_timeout"
                       class="form-control font-monospace <?= session('errors.proxy_send_timeout') ? 'is-invalid' : '' ?>"
                       placeholder="Ej. 60"
                       min="1"
                       max="3600"
                       value="<?= esc(old('proxy_send_timeout', $proxy['proxy_send_timeout'] ?? 60)) ?>">
              </div>
              <div class="form-text small text-muted">1–3600 s</div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
              <label for="client_max_body_size" class="form-label fw-semibold">Subida Máxima (MB)</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-cloud-upload"></i></span>
                <input type="number"
                       id="client_max_body_size"
                       name="client_max_body_size"
                       class="form-control font-monospace <?= session('errors.client_max_body_size') ? 'is-invalid' : '' ?>"
                       placeholder="Ej. 10"
                       min="0"
                       value="<?= esc(old('client_max_body_size', $proxy['client_max_body_size'] ?? 10)) ?>">
              </div>
              <div class="form-text small text-muted">0 = sin límite</div>
            </div>

            <!-- Cabeceras Personalizadas (Constructor Key-Value + Lista Blanca) -->
            <div class="col-12">
              <div class="p-3 rounded-2 border">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-3 mb-3 border-bottom">
                  <div>
                    <label class="form-label fw-semibold mb-0">Cabeceras HTTP Personalizadas</label>
                    <div class="small text-muted">Añade cabeceras de seguridad, CORS o upstream validadas para Nginx.</div>
                  </div>
                  <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="btnAddHeaderRow">
                    <i class="ti ti-plus"></i> Añadir
                  </button>
                </div>

                <!-- Estado vacío cuando no hay cabeceras -->
                <div id="customHeadersEmpty" class="text-center py-3 text-muted small">
                  Sin cabeceras adicionales configuradas. Pulsa <strong>Añadir</strong> para insertar una directiva.
                </div>

                <!-- Contenedor de filas dinámicas Key-Value -->
                <div id="customHeadersRows" class="d-flex flex-column gap-2"></div>

                <!-- Campo oculto sincronizado con el formato "Nombre: Valor" esperado por el backend -->
                <textarea id="custom_headers"
                          name="custom_headers"
                          class="d-none <?= session('errors.custom_headers') ? 'is-invalid' : '' ?>"><?= esc(old('custom_headers', $proxy['custom_headers'] ?? '')) ?></textarea>
              </div>
            </div>

            <!-- Vista Previa en Vivo del Bloque Nginx (Estilo CloudPanel) -->
            <div class="col-12">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="form-label fw-semibold mb-0 d-inline-flex align-items-center gap-2">
                  <i class="ti ti-code text-primary"></i> Vista Previa Directiva Nginx
                </span>
                <span class="badge bg-primary-subtle text-primary border border-primary font-monospace badge-keep-case">location /</span>
              </div>
              <pre id="nginxLivePreview" class="nginx-log-console font-monospace mb-0"></pre>
            </div>
          </div>
        </section>

        <!-- ==========================================
             PASO 4: SSL (Selector de certificado)
             ========================================== -->
        <h6>SSL</h6>
        <section>
          <div class="row g-4 pt-2">
            <div class="col-12">
              <label for="ssl_certificate" class="form-label fw-semibold">Certificado SSL</label>
              <select id="ssl_certificate" name="ssl_certificate" class="select2 form-control">
                <option value="none" <?= $currentSelection === 'none' ? 'selected' : '' ?>>Ninguno (HTTP)</option>
                <option value="new" <?= $currentSelection === 'new' ? 'selected' : '' ?>>Solicitar nuevo certificado con Let's Encrypt</option>
                <?php foreach (($certificates ?? []) as $cert): ?>
                  <?php $expTs = !empty($cert['expires_at']) ? strtotime($cert['expires_at']) : 0; ?>
                  <option value="<?= (int) $cert['id'] ?>"
                          data-domain="<?= esc($cert['domain']) ?>"
                          data-issuer="<?= esc($cert['issuer'] ?: "Let's Encrypt") ?>"
                          data-expires="<?= $expTs ? esc(date('d/m/Y H:i', $expTs)) : '—' ?>"
                          data-status="<?= esc($cert['status']) ?>"
                          <?= $currentSelection === (string) $cert['id'] ? 'selected' : '' ?>>
                    <?= esc($cert['domain']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text small text-muted">
                Selecciona un certificado existente o solicita uno nuevo.
              </div>
            </div>

            <!-- Certificado existente: Tarjeta visual de estado y renovación -->
            <div class="col-12 d-none" id="sslInfoPanel">
              <div class="p-3 rounded-2 border">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-2 bg-primary-subtle text-primary ssl-icon-box">
                      <i class="ti ti-certificate fs-5"></i>
                    </div>
                    <div>
                      <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fw-semibold font-monospace" id="sslInfoDomain"></span>
                        <span class="badge border" id="sslInfoStatus"></span>
                      </div>
                      <div class="small text-muted">
                        Emisor: <span id="sslInfoIssuer" class="fw-medium text-body"></span>
                      </div>
                    </div>
                  </div>
                  <?php if ($isEdit && !empty($proxy['id'])): ?>
                    <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="btnRenewCertInStep" onclick="submitRenewHostSsl()">
                      <i class="ti ti-refresh"></i> Renovar
                    </button>
                  <?php endif; ?>
                </div>

                <div class="row g-2 small">
                  <div class="col-12 col-sm-6">
                    <div class="text-muted mb-1"><i class="ti ti-calendar-time me-1"></i>Fecha de Expiración</div>
                    <div class="fw-semibold font-monospace" id="sslInfoExpires"></div>
                  </div>
                  <div class="col-12 col-sm-6">
                    <div class="text-muted mb-1"><i class="ti ti-shield-check me-1"></i>Protección TLS/SSL</div>
                    <div class="text-body fw-medium">Cifrado activo con renovación automática</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Nuevo certificado: Let's Encrypt -->
            <div class="col-12 d-none" id="sslNewPanel">
              <div class="p-3 rounded-2 border">
                <div class="d-flex align-items-center gap-3 pb-3 mb-3 border-bottom">
                  <div class="d-flex align-items-center justify-content-center rounded-2 bg-primary-subtle text-primary ssl-icon-box">
                    <i class="ti ti-shield-lock fs-5"></i>
                  </div>
                  <div>
                    <h6 class="fw-semibold mb-0">Solicitud de Certificado Let's Encrypt</h6>
                    <span class="small text-muted">Validación HTTP-01 automática para todos los dominios configurados</span>
                  </div>
                </div>

                <div class="row g-3">
                  <div class="col-12 col-md-8">
                    <label for="ssl_email" class="form-label small fw-semibold">Correo de contacto (Opcional)</label>
                    <div class="input-group">
                      <span class="input-group-text bg-light"><i class="ti ti-mail"></i></span>
                      <input type="email" id="ssl_email" name="ssl_email" class="form-control" placeholder="admin@midominio.com" value="<?= esc(old('ssl_email', '')) ?>">
                    </div>
                    <div class="form-text small text-muted">Utilizado por Let's Encrypt exclusivamente para avisos de caducidad.</div>
                  </div>
                  <div class="col-12">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="ssl_tos" name="ssl_tos" value="1" <?= old('ssl_tos') ? 'checked' : '' ?>>
                      <label class="form-check-label fw-semibold" for="ssl_tos">Acepto los términos de servicio de Let's Encrypt</label>
                    </div>
                    <div class="form-text small text-muted ps-4">
                      El DNS de todos los dominios debe apuntar a la IP pública de este servidor. Si la emisión falla, el host se guardará en modo HTTP.
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Forzar HTTPS (solo con certificado) -->
            <div class="col-12 col-md-6 d-none" id="sslForcePanel">
              <div class="p-3 rounded-2 border h-100">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input" type="checkbox" role="switch" id="force_https" name="force_https" value="1" <?= $isForce ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="force_https">Forzar Redirección HTTPS</label>
                </div>
                <div class="form-text small text-muted ps-4">
                  Redirige automáticamente todo el tráfico HTTP (puerto 80) a HTTPS (puerto 443).
                </div>
              </div>
            </div>

            <!-- Cabecera HSTS (solo con certificado) -->
            <div class="col-12 col-md-6 d-none" id="sslHstsPanel">
              <div class="p-3 rounded-2 border h-100">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input" type="checkbox" role="switch" id="hsts_enabled" name="hsts_enabled" value="1" <?= $isHsts ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="hsts_enabled">Habilitar HSTS</label>
                </div>
                <div class="form-text small text-muted ps-4">
                  Añade la cabecera <code>Strict-Transport-Security</code> para exigir conexiones seguras a los navegadores.
                </div>
              </div>
            </div>
          </div>
        </section>

      </form>
    </div>
  </div>
</div>

<!-- Formularios de acción en modo edición (fuera del wizard para evitar anidación de forms) -->
<?php if ($isEdit && !empty($proxy['id'])): ?>
  <form id="deleteHostForm" method="post" action="<?= base_url('proxy-hosts/delete/' . (int)$proxy['id']) ?>" class="d-none">
    <?= csrf_field() ?>
  </form>

  <form id="renewHostSslForm" method="post" action="<?= base_url('proxy-hosts/renew-ssl/' . (int)$proxy['id']) ?>" class="d-none">
    <?= csrf_field() ?>
  </form>
<?php endif; ?>

