<?php
/**
 * Pestaña "Mantenimiento" de Administración (Diseño Simple y Directo):
 *  - Limpiar sesiones
 *  - Limpiar logs
 *  - Limpiar caché
 *  - Tarjeta inferior para Limpiar todo
 *
 * Variables: $maintenanceStats
 */

$sessions = $maintenanceStats['sessions'] ?? [];
$cache    = $maintenanceStats['cache'] ?? [];
$logs     = $maintenanceStats['logs'] ?? [];
?>

<!-- Encabezado simple -->
<div class="mb-4">
  <h5 class="fw-semibold mb-1">Mantenimiento</h5>
  <p class="text-muted small mb-0">Limpia sesiones inactivas, archivos de registro y caché del sistema.</p>
</div>

<!-- 3 Bloques Simples: Sesiones, Caché, Logs -->
<div class="row g-3">

  <!-- 1. SESIONES -->
  <div class="col-12 col-md-4">
    <div class="card border shadow-none h-100 mb-0">
      <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="round-40 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3">
            <i class="ti ti-users fs-4"></i>
          </div>
          <h6 class="fw-semibold mb-1">Sesiones</h6>
          <p class="text-muted small mb-3">Sesiones de usuario almacenadas.</p>
          <div class="mb-3">
            <span class="fs-4 fw-bold text-dark-emphasis"><?= number_format((int) ($sessions['total_files'] ?? 0)) ?></span>
            <span class="text-muted small ms-1">(<?= esc($sessions['total_size_human'] ?? '0 B') ?>)</span>
          </div>
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm w-100 btn-clean-sessions-all"
                <?= ($sessions['total_files'] ?? 0) <= 1 ? 'disabled' : '' ?>>
          <i class="ti ti-trash me-1"></i> Limpiar sesiones
        </button>
      </div>
    </div>
  </div>

  <!-- 2. CACHÉ -->
  <div class="col-12 col-md-4">
    <div class="card border shadow-none h-100 mb-0">
      <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="round-40 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3">
            <i class="ti ti-bolt fs-4"></i>
          </div>
          <h6 class="fw-semibold mb-1">Caché</h6>
          <p class="text-muted small mb-3">Memoria temporal del sistema.</p>
          <div class="mb-3">
            <span class="fs-4 fw-bold text-dark-emphasis"><?= number_format((int) ($cache['total_files'] ?? 0)) ?></span>
            <span class="text-muted small ms-1">(<?= esc($cache['total_size_human'] ?? '0 B') ?>)</span>
          </div>
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm w-100 btn-clean-cache"
                <?= empty($cache['total_files']) && empty($cache['opcache_enabled']) ? 'disabled' : '' ?>>
          <i class="ti ti-eraser me-1"></i> Limpiar caché
        </button>
      </div>
    </div>
  </div>

  <!-- 3. LOGS -->
  <div class="col-12 col-md-4">
    <div class="card border shadow-none h-100 mb-0">
      <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="round-40 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3">
            <i class="ti ti-file-text fs-4"></i>
          </div>
          <h6 class="fw-semibold mb-1">Logs</h6>
          <p class="text-muted small mb-3">Historial y registros de la app.</p>
          <div class="mb-3">
            <span class="fs-4 fw-bold text-dark-emphasis"><?= number_format((int) ($logs['ci_files_count'] ?? 0)) ?></span>
            <span class="text-muted small ms-1">(<?= esc($logs['ci_total_size_human'] ?? '0 B') ?>)</span>
          </div>
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm w-100 btn-clean-logs-all"
                <?= empty($logs['ci_files_count']) && empty($logs['debugbar_files_count']) ? 'disabled' : '' ?>>
          <i class="ti ti-trash me-1"></i> Limpiar logs
        </button>
      </div>
    </div>
  </div>

</div>

<!-- Card abajo para Limpiar Todo -->
<div class="card border shadow-none mt-3 mb-0">
  <div class="card-body p-3 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
      <div class="round-36 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0" style="width: 36px; height: 36px;">
        <i class="ti ti-brush fs-5"></i>
      </div>
      <div>
        <h6 class="fw-semibold mb-0">Limpiar todo</h6>
        <p class="text-muted small mb-0">Vacía sesiones, caché y registros en una sola acción.</p>
      </div>
    </div>
    <button type="button" id="btnRunFullMaintenance" class="btn btn-primary btn-sm px-3 text-nowrap d-inline-flex align-items-center gap-1">
      <i class="ti ti-brush"></i> Limpiar todo
    </button>
  </div>
</div>

<!-- Formularios de acción (POST con CSRF) -->
<form id="cleanSessionsForm" method="post" action="<?= base_url('admin/maintenance/sessions') ?>" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="mode" id="cleanSessionsMode" value="all">
</form>

<form id="cleanCacheForm" method="post" action="<?= base_url('admin/maintenance/cache') ?>" class="d-none">
  <?= csrf_field() ?>
</form>

<form id="cleanLogsForm" method="post" action="<?= base_url('admin/maintenance/logs') ?>" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="keep_today" id="cleanLogsKeepToday" value="0">
</form>

<form id="fullMaintenanceForm" method="post" action="<?= base_url('admin/maintenance/full') ?>" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="keep_today_logs" value="0">
  <input type="hidden" name="only_expired_sessions" value="0">
</form>
