<?php
/**
 * Vista parcial de IPs Bloqueadas (Fail2ban Interno + Cloudflare Real-IP).
 */
$bannedList = $bannedIps ?? [];
$totalBanned = count($bannedList);
$autoBannedCount = count(array_filter($bannedList, static fn($b) => ($b['created_by'] ?? '') === 'system'));
$permBannedCount = count(array_filter($bannedList, static fn($b) => (int) ($b['is_permanent'] ?? 0) === 1));
?>

<!-- Métricas de Protección IP y Cloudflare -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border shadow-none mb-0">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="round-40 d-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle">
          <i class="ti ti-ban fs-5"></i>
        </div>
        <div>
          <span class="text-muted small d-block">IPs Bloqueadas</span>
          <h5 class="fw-bold mb-0"><?= $totalBanned ?></h5>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border shadow-none mb-0">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="round-40 d-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle">
          <i class="ti ti-shield-exclamation fs-5"></i>
        </div>
        <div>
          <span class="text-muted small d-block">Auto-Ban (Login)</span>
          <h5 class="fw-bold mb-0"><?= $autoBannedCount ?></h5>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border shadow-none mb-0">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="round-40 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
          <i class="ti ti-lock fs-5"></i>
        </div>
        <div>
          <span class="text-muted small d-block">Permanentes</span>
          <h5 class="fw-bold mb-0"><?= $permBannedCount ?></h5>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border shadow-none mb-0">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="round-40 d-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle">
          <i class="ti ti-cloud-lock fs-5"></i>
        </div>
        <div>
          <span class="text-muted small d-block">Cloudflare Real-IP</span>
          <span class="badge bg-success-subtle text-success border border-success">CF-Connecting-IP Activo</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Formulario para Bloquear una IP Manualmente -->
<div class="card border shadow-none mb-4">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
      <div class="round-32 d-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle">
        <i class="ti ti-shield-off fs-5"></i>
      </div>
      <div>
        <h5 class="card-title fw-semibold mb-0">Bloquear Dirección IP</h5>
      </div>
    </div>
    <span class="small text-muted">
      Tu IP actual detectada: <code class="px-2 py-1 rounded bg-light"><?= esc($currentVisitorIp ?? '127.0.0.1') ?></code>
    </span>
  </div>
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('admin/bans/add') ?>">
      <?= csrf_field() ?>
      <div class="row g-3 align-items-end">
        <div class="col-12 col-md-4">
          <label for="ban_ip_address" class="form-label fw-semibold">Dirección IP (IPv4 / IPv6) <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-network"></i></span>
            <input type="text"
                   id="ban_ip_address"
                   name="ip_address"
                   class="form-control font-monospace"
                   placeholder="Ej. 203.0.113.50"
                   value="<?= esc(old('ip_address', '')) ?>"
                   required>
          </div>
        </div>

        <div class="col-12 col-md-3">
          <label for="ban_duration" class="form-label fw-semibold">Duración del Bloqueo</label>
          <select id="ban_duration" name="duration" class="form-select">
            <option value="30">30 minutos</option>
            <option value="60">1 hora</option>
            <option value="1440">24 horas</option>
            <option value="10080">7 días</option>
            <option value="0" selected>Permanente</option>
          </select>
        </div>

        <div class="col-12 col-md-5">
          <label for="ban_reason" class="form-label fw-semibold">Motivo</label>
          <div class="input-group">
            <input type="text"
                   id="ban_reason"
                   name="reason"
                   class="form-control"
                   placeholder="Ej. Bot malicioso / Escaneo de vulnerabilidades"
                   value="<?= esc(old('reason', '')) ?>">
            <button type="submit" class="btn btn-danger d-inline-flex align-items-center gap-1 px-3">
              <i class="ti ti-ban"></i> Bloquear IP
            </button>
          </div>
        </div>
      </div>
      <div class="form-text small text-muted mt-2">
        Las IPs con 5 intentos fallidos de inicio de sesión o 2FA se bloquean automáticamente durante 30 minutos y se sincronizan con las reglas <code>deny</code> de Nginx.
      </div>
    </form>
  </div>
</div>

<!-- Tabla de IPs Bloqueadas Activas -->
<div class="card border shadow-none mb-0">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
        <i class="ti ti-list-details fs-5"></i>
      </div>
      <h5 class="card-title fw-semibold mb-0">Lista Negra Activa (Panel &amp; Nginx)</h5>
    </div>
    <span class="badge bg-primary-subtle text-primary border border-primary"><?= $totalBanned ?> activas</span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($bannedList)): ?>
      <div class="text-center py-5 px-3">
        <div class="round-48 d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle mb-3">
          <i class="ti ti-shield-check fs-4"></i>
        </div>
        <h6 class="fw-semibold mb-1">No hay direcciones IP bloqueadas actualmente</h6>
        <p class="text-muted small mb-0">
          Cualquier IP que falle 5 veces en el inicio de sesión o que agregues manualmente aparecerá aquí.
        </p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-4">Dirección IP</th>
              <th>Motivo</th>
              <th>Origen</th>
              <th>Intentos</th>
              <th>Expiración</th>
              <th class="text-end pe-4">Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bannedList as $ban): ?>
              <tr>
                <td class="ps-4">
                  <code class="fw-bold text-danger"><?= esc($ban['ip_address']) ?></code>
                </td>
                <td>
                  <span class="small"><?= esc($ban['reason'] ?? 'Bloqueo de seguridad') ?></span>
                </td>
                <td>
                  <?php if (($ban['created_by'] ?? '') === 'system'): ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning">Auto-Ban</span>
                  <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger">Manual</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-secondary-subtle text-secondary"><?= (int) ($ban['failed_attempts'] ?? 0) ?></span>
                </td>
                <td>
                  <?php if ((int) ($ban['is_permanent'] ?? 0) === 1): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger">Permanente</span>
                  <?php else: ?>
                    <span class="small text-muted"><?= esc((string) ($ban['expires_at'] ?? '')) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end pe-4">
                  <form method="post" action="<?= base_url('admin/bans/unban/' . (int) $ban['id']) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1">
                      <i class="ti ti-lock-open"></i> Desbloquear
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
