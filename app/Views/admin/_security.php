<?php
/**
 * Pestaña "Seguridad & 2FA" de Administración:
 *  - Autenticación en Dos Pasos (TOTP RFC 6238)
 *  - Ajustes Generales (Correo por defecto Let's Encrypt / ACME)
 *
 * Variables: $twoFactorEnabled, $pendingSecret, $totpQrCode, $defaultEmail
 */
?>
<!-- Autenticación en Dos Pasos (2FA TOTP RFC 6238) -->
<div class="card border shadow-none mb-4">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
        <i class="ti ti-shield-lock fs-5"></i>
      </div>
      <h5 class="card-title fw-semibold mb-0">Autenticación en Dos Pasos (2FA)</h5>
    </div>
    <?php if (!empty($twoFactorEnabled)): ?>
      <span class="badge border bg-success-subtle text-success border-success">2FA Activo</span>
    <?php else: ?>
      <span class="badge border bg-secondary-subtle text-secondary border-secondary">2FA Inactivo</span>
    <?php endif; ?>
  </div>
  <div class="card-body p-4">
    <?php if (!empty($twoFactorEnabled)): ?>
      <div class="mb-3">
        <h6 class="fw-semibold mb-1">Tu cuenta de administrador está protegida con TOTP (RFC 6238)</h6>
        <p class="small text-muted mb-0">
          Se solicitará el código de 6 dígitos de tu app en cada inicio de sesión.
        </p>
      </div>
      <form method="post" action="<?= base_url('admin/2fa/disable') ?>" class="d-flex justify-content-center mt-4 pt-2">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger btn-sm px-4">Desactivar</button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= base_url('admin/2fa/enable') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="totp_secret" value="<?= esc($pendingSecret ?? '') ?>">
        
        <div class="row g-3">
          <!-- Clave Secreta Base32 con botón para ver QR en modal -->
          <div class="col-12 col-md-6">
            <label for="totpSecretField" class="form-label fw-semibold">Clave Secreta Base32 (Google Authenticator / Authy)</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="ti ti-key"></i></span>
              <input type="text"
                     id="totpSecretField"
                     class="form-control font-monospace"
                     value="<?= esc($pendingSecret ?? '') ?>"
                     readonly>
              <?php if (!empty($totpQrCode)): ?>
                <button type="button"
                        class="btn btn-outline-secondary px-3"
                        data-bs-toggle="modal"
                        data-bs-target="#totpQrModal"
                        title="Generar código QR">
                  <i class="ti ti-qrcode fs-5"></i>
                </button>
              <?php endif; ?>
            </div>
            <div class="form-text small text-muted">
              Escanea el QR o introduce la clave en tu app.
            </div>
          </div>

          <!-- Código de Verificación -->
          <div class="col-12 col-md-6">
            <label for="totp_code" class="form-label fw-semibold">Código de Verificación (6 dígitos) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="ti ti-lock"></i></span>
              <input type="text"
                     id="totp_code"
                     name="totp_code"
                     class="form-control font-monospace"
                     inputmode="numeric"
                     maxlength="6"
                     placeholder="Ej. 123456"
                     required>
            </div>
            <div class="form-text small text-muted">
              Introduce el código de 6 dígitos de tu app.
            </div>
          </div>

          <div class="col-12 d-flex justify-content-center align-items-center gap-2 mt-4 pt-2">
            <a href="<?= base_url('admin?tab=security') ?>" class="btn btn-dark btn-sm">Cancelar</a>
            <button type="submit" class="btn btn-primary btn-sm px-3">Activar</button>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- Ajustes Generales (Let's Encrypt / ACME) -->
<div class="card border shadow-none mb-4">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
    <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
      <i class="ti ti-adjustments fs-5"></i>
    </div>
    <h5 class="card-title fw-semibold mb-0">Ajustes Generales</h5>
  </div>
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('admin/general') ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-12">
          <label for="default_ssl_email" class="form-label fw-semibold">Correo por Defecto para Let's Encrypt</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-mail"></i></span>
            <input type="email"
                   id="default_ssl_email"
                   name="default_ssl_email"
                   class="form-control <?= session('errors.default_ssl_email') ? 'is-invalid' : '' ?>"
                   placeholder="admin@empresa.com"
                   value="<?= esc(old('default_ssl_email', $defaultEmail)) ?>">
          </div>
          <div class="form-text small text-muted">
            Dirección empleada de forma predeterminada para avisos de caducidad en la emisión de certificados SSL.
          </div>
        </div>

        <div class="col-12 d-flex justify-content-center align-items-center gap-2 mt-4 pt-2">
          <a href="<?= base_url('admin?tab=security') ?>" class="btn btn-dark btn-sm">Cancelar</a>
          <button type="submit" class="btn btn-primary btn-sm px-3">Guardar</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- HTTPS Nativo del Panel de Administración (proxy-manager) -->
<?php
$panelSslCfg  = $panelSsl ?? ['domain' => '127.0.0.1', 'port' => '81', 'ssl_enabled' => false, 'ssl_mode' => 'self_signed', 'force_https' => true];
$panelSslOn   = !empty($panelSslCfg['ssl_enabled']);
$panelSslMode = (string) ($panelSslCfg['ssl_mode'] ?? 'self_signed');
?>
<div class="card border shadow-none mb-0">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
        <i class="ti ti-lock-access fs-5"></i>
      </div>
      <h5 class="card-title fw-semibold mb-0">HTTPS del Panel de Administración</h5>
    </div>
    <?php if ($panelSslOn): ?>
      <span class="badge border bg-success-subtle text-success border-success">
        HTTPS Activo (<?= $panelSslMode === 'letsencrypt' ? "Let's Encrypt" : 'Autofirmado' ?>)
      </span>
    <?php else: ?>
      <span class="badge border bg-secondary-subtle text-secondary border-secondary">Solo HTTP</span>
    <?php endif; ?>
  </div>
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('admin/panel-ssl') ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-12 col-md-8">
          <label for="panel_domain" class="form-label fw-semibold">Dominio o IP del Panel <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-world"></i></span>
            <input type="text"
                   id="panel_domain"
                   name="panel_domain"
                   class="form-control font-monospace <?= session('errors.panel_domain') ? 'is-invalid' : '' ?>"
                   placeholder="panel.midominio.com o 192.168.1.100"
                   value="<?= esc(old('panel_domain', $panelSslCfg['domain'])) ?>"
                   required>
          </div>
          <div class="form-text small text-muted">
            Nombre de dominio dedicado (ej. <code>panel.midominio.com</code>) o dirección IP del servidor.
          </div>
        </div>

        <div class="col-12 col-md-4">
          <label for="panel_port" class="form-label fw-semibold">Puerto de Acceso <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-plug"></i></span>
            <input type="number"
                   id="panel_port"
                   name="panel_port"
                   class="form-control font-monospace <?= session('errors.panel_port') ? 'is-invalid' : '' ?>"
                   min="1"
                   max="65535"
                   value="<?= esc(old('panel_port', $panelSslCfg['port'])) ?>"
                   required>
          </div>
          <div class="form-text small text-muted">
            Puerto estándar del panel (<code>81</code>) o <code>443</code> para dominio dedicado.
          </div>
        </div>

        <div class="col-12 col-md-6">
          <div class="form-check form-switch mt-2">
            <input class="form-check-input"
                   type="checkbox"
                   role="switch"
                   id="panel_ssl_enabled"
                   name="panel_ssl_enabled"
                   value="1"
                   <?= old('panel_ssl_enabled', $panelSslOn ? '1' : '0') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="panel_ssl_enabled">
              Activar SSL/TLS (HTTPS) para el panel
            </label>
          </div>
          <div class="form-text small text-muted">
            Configura el vhost <code>proxy-manager</code> en Nginx con cifrado TLS 1.2/1.3.
          </div>
        </div>

        <div class="col-12 col-md-6">
          <div class="form-check form-switch mt-2">
            <input class="form-check-input"
                   type="checkbox"
                   role="switch"
                   id="panel_force_https"
                   name="panel_force_https"
                   value="1"
                   <?= old('panel_force_https', !empty($panelSslCfg['force_https']) ? '1' : '0') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="panel_force_https">
              Forzar redirección HTTP → HTTPS
            </label>
          </div>
          <div class="form-text small text-muted">
            Redirige automáticamente peticiones HTTP al protocolo seguro (puerto 80 → 443 o error 497 en puertos personalizados).
          </div>
        </div>

        <div class="col-12 col-md-6">
          <label for="panel_ssl_mode" class="form-label fw-semibold">Tipo de Certificado SSL</label>
          <select id="panel_ssl_mode" name="panel_ssl_mode" class="select2 form-control">
            <?php $selMode = old('panel_ssl_mode', $panelSslMode); ?>
            <option value="letsencrypt" <?= $selMode === 'letsencrypt' ? 'selected' : '' ?>>
              Let's Encrypt (ACME automático - requiere dominio público)
            </option>
            <option value="self_signed" <?= $selMode === 'self_signed' ? 'selected' : '' ?>>
              Autofirmado (Self-Signed - ideal para IP o red local)
            </option>
          </select>
          <div class="form-text small text-muted">
            Si usas Let's Encrypt, se empleará el correo configurado en Ajustes Generales.
          </div>
        </div>

        <div class="col-12 d-flex justify-content-center align-items-center gap-2 mt-4 pt-2">
          <a href="<?= base_url('admin?tab=security') ?>" class="btn btn-dark btn-sm">Cancelar</a>
          <button type="submit" class="btn btn-primary btn-sm px-3">Aplicar y Recargar Nginx</button>
        </div>
      </div>
    </form>
  </div>
</div>

