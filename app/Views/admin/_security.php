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
      <form method="post" action="<?= base_url('admin/2fa/disable') ?>" class="d-flex justify-content-center pt-2">
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

          <div class="col-12 d-flex justify-content-center align-items-center gap-2 pt-2">
            <a href="<?= base_url('admin?tab=security') ?>" class="btn btn-dark btn-sm">Cancelar</a>
            <button type="submit" class="btn btn-primary btn-sm px-3">Activar</button>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- Ajustes Generales (Let's Encrypt / ACME) -->
<div class="card border shadow-none mb-0">
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

        <div class="col-12 d-flex justify-content-center align-items-center gap-2 pt-2">
          <a href="<?= base_url('admin?tab=security') ?>" class="btn btn-dark btn-sm">Cancelar</a>
          <button type="submit" class="btn btn-primary btn-sm px-3">Guardar</button>
        </div>
      </div>
    </form>
  </div>
</div>

