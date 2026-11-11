<?php
/**
 * Pestaña "Servidor SMTP" de Administración:
 *  - Configuración del servidor de correo saliente (Host, Puerto, Cifrado, Credenciales)
 *  - Prueba inmediata de envío de correo
 *
 * Variables: $smtpSettings
 */
?>
<!-- Configuración del Servidor SMTP -->
<div class="card border shadow-none mb-4">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
    <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
      <i class="ti ti-mail-fast fs-5"></i>
    </div>
    <h5 class="card-title fw-semibold mb-0">Servidor de Correo (SMTP)</h5>
  </div>
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('admin/smtp') ?>">
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

        <div class="col-12 d-flex justify-content-center gap-2 mt-4 pt-2">
          <a href="<?= base_url('admin') ?>" class="btn btn-dark btn-sm">Cancelar</a>
          <button type="submit" class="btn btn-primary btn-sm px-4">Guardar</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Probar Conexión SMTP -->
<div class="card border shadow-none mb-0">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
    <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
      <i class="ti ti-send fs-5"></i>
    </div>
    <h5 class="card-title fw-semibold mb-0">Probar Conexión SMTP</h5>
  </div>
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('admin/smtp/test') ?>" id="testSmtpForm">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-12">
          <label for="test_email" class="form-label fw-semibold">Enviar Correo de Prueba a</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-mail-forward"></i></span>
            <input type="email"
                   id="test_email"
                   name="test_email"
                   class="form-control"
                   placeholder="destinatario@empresa.com"
                   required>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-center gap-2 mt-4 pt-2">
          <button type="submit" id="btnTestSmtp" class="btn btn-primary btn-sm px-3 d-inline-flex align-items-center gap-1">Enviar
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

