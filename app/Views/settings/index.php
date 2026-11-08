<?php
/**
 * Vista de Configuración del Sistema (Seguridad 2FA, SMTP y Ajustes Generales).
 *
 * Variables disponibles:
 *  - $defaultEmail (string)
 *  - $smtpSettings (array)
 *  - $twoFactorEnabled (bool)
 *  - $pendingSecret (string)
 *  - $totpUri (string)
 */
?>

<!-- Cabecera de Página -->
<div class="page-header">
  <h4 class="page-header-title">Configuración</h4>
  <div class="page-header-actions">
    <a href="<?= base_url('dashboard') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
      <i class="ti ti-arrow-left"></i> Volver
    </a>
  </div>
</div>

<!-- =========================================================================
     1. SEGURIDAD DE LA CUENTA (2FA / TOTP RFC 6238)
     ========================================================================= -->
<div class="card border shadow-none mb-4">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
        <i class="ti ti-shield-lock fs-5"></i>
      </div>
      <h5 class="card-title fw-semibold mb-0">Seguridad: Autenticación en Dos Pasos (2FA)</h5>
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
      <form method="post" action="<?= base_url('settings/2fa/disable') ?>" class="d-flex justify-content-center pt-2">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger btn-sm px-4">Desactivar</button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= base_url('settings/2fa/enable') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="totp_secret" value="<?= esc($pendingSecret ?? '') ?>">
        <div class="row g-3">
          <!-- Clave Secreta Base32 con botón para ver QR en modal -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold">Clave Secreta Base32 (Google Authenticator / Authy)</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="ti ti-key"></i></span>
              <input type="text"
                     class="form-control font-monospace"
                     value="<?= esc($pendingSecret ?? '') ?>"
                     readonly
                     id="settingsTotpSecret">
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

          <div class="col-12 d-flex justify-content-center align-items-center gap-2 pt-2">
            <a href="<?= base_url('settings') ?>" class="btn btn-dark btn-sm">Cancelar</a>
            <button type="submit" class="btn btn-primary btn-sm px-3">Activar</button>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- =========================================================================
     2. AJUSTES GENERALES (Let's Encrypt / ACME)
     ========================================================================= -->
<div class="card border shadow-none mb-4">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
    <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
      <i class="ti ti-adjustments fs-5"></i>
    </div>
    <h5 class="card-title fw-semibold mb-0">Ajustes Generales</h5>
  </div>
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('settings/general') ?>">
      <?= csrf_field() ?>
      <div class="row">
        <div class="col-12 col-md-8 col-lg-6">
          <label for="default_ssl_email" class="form-label fw-semibold">Correo por Defecto para Let's Encrypt</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-mail"></i></span>
            <input type="email"
                   id="default_ssl_email"
                   name="default_ssl_email"
                   class="form-control <?= session('errors.default_ssl_email') ? 'is-invalid' : '' ?>"
                   placeholder="admin@empresa.com"
                   value="<?= esc(old('default_ssl_email', $defaultEmail)) ?>">
            <button type="submit" class="btn btn-primary btn-sm px-3">Guardar</button>
          </div>
          <div class="form-text small text-muted">
            Dirección empleada de forma predeterminada para avisos de caducidad en la emisión de certificados SSL.
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- =========================================================================
     3. CONFIGURACIÓN DEL SERVIDOR SMTP (CORREO ELECTRÓNICO)
     ========================================================================= -->
<div class="card border shadow-none mb-4">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
    <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
      <i class="ti ti-mail-fast fs-5"></i>
    </div>
    <h5 class="card-title fw-semibold mb-0">Servidor de Correo (SMTP)</h5>
  </div>
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('settings/smtp') ?>">
      <?= csrf_field() ?>
      <div class="row g-4">
        <!-- Servidor Host -->
        <div class="col-12 col-md-8">
          <label for="smtp_host" class="form-label fw-semibold">Servidor SMTP (Host)</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-server"></i></span>
            <input type="text"
                   id="smtp_host"
                   name="smtp_host"
                   class="form-control font-monospace"
                   placeholder="smtp.empresa.com"
                   value="<?= esc(old('smtp_host', $smtpSettings['smtp_host'] ?? '')) ?>">
          </div>
        </div>

        <!-- Puerto -->
        <div class="col-12 col-md-4">
          <label for="smtp_port" class="form-label fw-semibold">Puerto</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-plug"></i></span>
            <input type="number"
                   id="smtp_port"
                   name="smtp_port"
                   class="form-control font-monospace"
                   placeholder="587"
                   min="1"
                   max="65535"
                   value="<?= esc(old('smtp_port', $smtpSettings['smtp_port'] ?? '587')) ?>">
          </div>
        </div>

        <!-- Usuario -->
        <div class="col-12 col-md-4">
          <label for="smtp_user" class="form-label fw-semibold">Usuario SMTP</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-user"></i></span>
            <input type="text"
                   id="smtp_user"
                   name="smtp_user"
                   class="form-control"
                   placeholder="usuario@empresa.com"
                   value="<?= esc(old('smtp_user', $smtpSettings['smtp_user'] ?? '')) ?>">
          </div>
        </div>

        <!-- Contraseña -->
        <div class="col-12 col-md-4">
          <label for="smtp_pass" class="form-label fw-semibold">Contraseña SMTP</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-lock"></i></span>
            <input type="password"
                   id="smtp_pass"
                   name="smtp_pass"
                   class="form-control"
                   placeholder="<?= !empty($smtpSettings['smtp_pass']) ? '••••••••' : 'Contraseña' ?>">
          </div>
          <?php if (!empty($smtpSettings['smtp_pass'])): ?>
            <div class="form-text small text-muted">Dejar vacío para mantener la contraseña actual.</div>
          <?php endif; ?>
        </div>

        <!-- Cifrado -->
        <div class="col-12 col-md-4">
          <label for="smtp_crypto" class="form-label fw-semibold">Cifrado</label>
          <select id="smtp_crypto" name="smtp_crypto" class="form-select select2">
            <?php $crypto = old('smtp_crypto', $smtpSettings['smtp_crypto'] ?? 'tls'); ?>
            <option value="tls" <?= $crypto === 'tls' ? 'selected' : '' ?>>STARTTLS (tls - Puerto recomendado 587)</option>
            <option value="ssl" <?= $crypto === 'ssl' ? 'selected' : '' ?>>SSL / TLS implícito (ssl - Puerto recomendado 465)</option>
            <option value="none" <?= $crypto === 'none' ? 'selected' : '' ?>>Sin cifrado (none - Puerto 25)</option>
          </select>
        </div>

        <!-- Email Remitente -->
        <div class="col-12 col-md-6">
          <label for="smtp_from_email" class="form-label fw-semibold">Correo Remitente (From)</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-at"></i></span>
            <input type="email"
                   id="smtp_from_email"
                   name="smtp_from_email"
                   class="form-control"
                   placeholder="alertas@empresa.com"
                   value="<?= esc(old('smtp_from_email', $smtpSettings['smtp_from_email'] ?? '')) ?>">
          </div>
        </div>

        <!-- Nombre Remitente -->
        <div class="col-12 col-md-6">
          <label for="smtp_from_name" class="form-label fw-semibold">Nombre del Remitente</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-id"></i></span>
            <input type="text"
                   id="smtp_from_name"
                   name="smtp_from_name"
                   class="form-control"
                   placeholder="Proxy Manager"
                   value="<?= esc(old('smtp_from_name', $smtpSettings['smtp_from_name'] ?? 'Proxy Manager')) ?>">
          </div>
        </div>

        <div class="col-12 d-flex justify-content-between align-items-center pt-2">
          <a href="<?= base_url('dashboard') ?>" class="btn btn-dark btn-sm">Cancelar</a>
          <button type="submit" class="btn btn-primary btn-sm px-4">Guardar</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- =========================================================================
     4. PRUEBA DE ENVÍO SMTP
     ========================================================================= -->
<div class="card border shadow-none mb-0">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
    <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
      <i class="ti ti-send fs-5"></i>
    </div>
    <h5 class="card-title fw-semibold mb-0">Probar Conexión SMTP</h5>
  </div>
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('settings/smtp/test') ?>" id="testSmtpForm">
      <?= csrf_field() ?>
      <div class="row">
        <div class="col-12 col-md-8 col-lg-6">
          <label for="test_email" class="form-label fw-semibold">Enviar Correo de Prueba a</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-mail-forward"></i></span>
            <input type="email"
                   id="test_email"
                   name="test_email"
                   class="form-control"
                   placeholder="destinatario@empresa.com"
                   required>
            <button type="submit" id="btnTestSmtp" class="btn btn-outline-secondary btn-sm px-3 d-inline-flex align-items-center gap-1">
              <i class="ti ti-send"></i> Enviar
            </button>
          </div>
          <div class="form-text small text-muted">
            Se enviará un correo de verificación inmediato usando los parámetros SMTP guardados.
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const testForm = document.getElementById('testSmtpForm');
    const testBtn  = document.getElementById('btnTestSmtp');

    if (testForm && testBtn) {
      testForm.addEventListener('submit', function () {
        testBtn.disabled = true;
        testBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Enviando...';
      });
    }
  });
</script>

<!-- Modal para Código QR 2FA -->
<?php if (!empty($totpQrCode)): ?>
<div class="modal fade" id="totpQrModal" tabindex="-1" aria-labelledby="totpQrModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border">
      <div class="modal-header border-bottom py-3">
        <h6 class="modal-title fw-semibold mb-0" id="totpQrModalLabel">Código QR 2FA</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="p-2 bg-white rounded-3 d-inline-block border shadow-sm mb-3">
          <img src="<?= $totpQrCode ?>" alt="Código QR 2FA" style="width: 200px; height: 200px; display: block;">
        </div>
        <p class="small text-muted mb-2">Escanea el código con tu app autenticadora.</p>
        <span class="font-monospace small bg-light-subtle p-1 px-2 rounded border"><?= esc($pendingSecret ?? '') ?></span>
      </div>
      <div class="modal-footer border-top py-2 justify-content-center">
        <button type="button" class="btn btn-dark btn-sm" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
