<?php
/**
 * Vista de Gestión de Copias de Seguridad (Backups).
 *
 * Variables disponibles:
 *  - $backups (array)
 *  - $retentionCount (int)
 *  - $totalBackups (int)
 *  - $totalSizeHuman (string)
 */
?>

<!-- Cabecera de Página -->
<div class="page-header">
  <h4 class="page-header-title">Copias de Seguridad</h4>
  <div class="page-header-actions">
    <form method="post" action="<?= base_url('backups/create') ?>" class="mb-0">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
        <i class="ti ti-plus"></i> Crear
      </button>
    </form>
  </div>
</div>

<!-- Tarjetas de Resumen (Mobile-first: 2 col en móvil, 4 en desktop) -->
<div class="row g-3 g-md-4 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted text-uppercase fs-2">Backups</span>
          <div class="round-40 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
            <i class="ti ti-archive fs-5"></i>
          </div>
        </div>
        <h3 class="fw-bold mb-1"><?= (int) $totalBackups ?></h3>
        <span class="text-muted small">Copias disponibles</span>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted text-uppercase fs-2">Almacenamiento</span>
          <div class="round-40 d-flex align-items-center justify-content-center bg-info-subtle text-info rounded-circle">
            <i class="ti ti-server fs-5"></i>
          </div>
        </div>
        <h3 class="fw-bold mb-1"><?= esc($totalSizeHuman) ?></h3>
        <span class="text-muted small">En <code>writable/backups</code></span>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted text-uppercase fs-2">Retención</span>
          <div class="round-40 d-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle">
            <i class="ti ti-rotate-clockwise fs-5"></i>
          </div>
        </div>
        <h3 class="fw-bold mb-1"><?= (int) $retentionCount ?></h3>
        <span class="text-muted small">Máximo de copias locales</span>
      </div>
    </div>
  </div>

  <div class="col-6 col-xl-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-semibold text-muted text-uppercase fs-2">Integridad</span>
          <div class="round-40 d-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle">
            <i class="ti ti-shield-check fs-5"></i>
          </div>
        </div>
        <h3 class="fw-bold mb-1">VACUUM</h3>
        <span class="text-success small fw-medium">SQLite + SHA-256</span>
      </div>
    </div>
  </div>
</div>

<!-- =========================================================================
     AJUSTES DE RETENCIÓN DE BACKUPS
     ========================================================================= -->
<div class="card border shadow-none mb-4">
  <div class="card-header bg-transparent border-bottom py-3">
    <h5 class="card-title fw-semibold mb-0">Política de Retención</h5>
  </div>
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('backups/retention') ?>">
      <?= csrf_field() ?>
      <div class="row">
        <div class="col-12 col-md-8 col-lg-6">
          <label for="backup_retention_count" class="form-label fw-semibold">Retención de Copias de Seguridad Locales <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="ti ti-history"></i></span>
            <input type="number"
                   id="backup_retention_count"
                   name="backup_retention_count"
                   class="form-control font-monospace <?= session('errors.backup_retention_count') ? 'is-invalid' : '' ?>"
                   placeholder="Ej. 10"
                   min="1"
                   max="100"
                   value="<?= esc(old('backup_retention_count', $retentionCount)) ?>">
            <button type="submit" class="btn btn-primary btn-sm px-3">
              <i class="ti ti-device-floppy"></i> Guardar
            </button>
          </div>
          <div class="form-text small text-muted">
            Número máximo de archivos a conservar (1–100). 
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- =========================================================================
     LISTADO DE COPIAS DE SEGURIDAD
     ========================================================================= -->
<div class="card border shadow-none mb-0" id="backupsManagerCard">
  <!-- Buscador en tiempo real integrado en la cabecera del card -->
  <div class="card-body p-3 border-bottom">
    <div class="row g-2 align-items-center">
      <div class="col-12">
        <div class="input-group">
          <span class="input-group-text bg-transparent"><i class="ti ti-search"></i></span>
          <input type="search"
                 id="backupSearchInput"
                 class="form-control"
                 placeholder="Buscar por nombre de archivo o fecha...">
        </div>
      </div>
    </div>
  </div>

  <!-- Tabla de Escritorio (>= 768px) -->
  <div class="table-responsive d-none d-md-block">
    <table class="table table-hover align-middle mb-0" id="backupsTable">
      <thead class="table-light">
        <tr>
          <th class="ps-4">Archivo de Backup</th>
          <th>Tamaño</th>
          <th>Fecha de Creación</th>
          <th class="text-end pe-4">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($backups)): ?>
          <?php foreach ($backups as $b): ?>
            <tr class="backup-item">
              <td class="ps-4">
                <div class="d-flex align-items-center gap-2">
                  <i class="ti ti-file-zip text-primary fs-5"></i>
                  <span class="fw-semibold font-monospace"><?= esc($b['filename']) ?></span>
                </div>
              </td>
              <td class="font-monospace small"><?= esc($b['size_formatted']) ?></td>
              <td class="text-muted small font-monospace"><?= esc($b['created_at']) ?></td>
              <td class="text-end pe-4">
                <div class="d-inline-flex align-items-center gap-2">
                  <a href="<?= base_url('backups/download/' . urlencode($b['filename'])) ?>"
                     class="btn btn-outline-secondary btn-sm">
                    Descargar
                  </a>
                  <button type="button"
                          class="btn btn-primary btn-sm btn-restore-backup"
                          data-filename="<?= esc($b['filename']) ?>">
                    Restaurar
                  </button>
                  <button type="button"
                          class="btn btn-danger btn-sm btn-delete-backup"
                          data-filename="<?= esc($b['filename']) ?>">
                    Eliminar
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr id="backupNoResultsRow" class="d-none">
            <td colspan="4" class="text-center py-4 text-muted">
              No se encontraron copias de seguridad que coincidan con la búsqueda.
            </td>
          </tr>
        <?php else: ?>
          <tr>
            <td colspan="4" class="text-center py-5">
              <div class="round-48 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-2">
                <i class="ti ti-database-export fs-5"></i>
              </div>
              <p class="text-muted mb-0">Aún no se ha generado ninguna copia de seguridad.</p>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Tarjetas Móviles Directas (< 768px - Patrón DeCA) -->
<div class="d-block d-md-none mt-3" id="backupMobileCards">
  <?php if (!empty($backups)): ?>
    <?php foreach ($backups as $b): ?>
      <div class="card border shadow-none mb-2 p-3 mobile-card-item backup-item">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
          <span class="fw-semibold font-monospace text-break"><?= esc($b['filename']) ?></span>
          <span class="badge border bg-success-subtle text-success border-success"><?= esc($b['size_formatted']) ?></span>
        </div>
        <div class="small text-muted mb-2">
          <div>Fecha: <span class="text-body font-monospace"><?= esc($b['created_at']) ?></span></div>
        </div>
        <div class="d-flex align-items-center justify-content-center gap-2 border-top pt-2">
          <a href="<?= base_url('backups/download/' . urlencode($b['filename'])) ?>"
             class="btn btn-outline-secondary btn-sm">
            Descargar
          </a>
          <button type="button"
                  class="btn btn-primary btn-sm btn-restore-backup"
                  data-filename="<?= esc($b['filename']) ?>">
            Restaurar
          </button>
          <button type="button"
                  class="btn btn-danger btn-sm btn-delete-backup"
                  data-filename="<?= esc($b['filename']) ?>">
            Eliminar
          </button>
        </div>
      </div>
    <?php endforeach; ?>
    <div id="backupMobileNoResults" class="card border shadow-none p-4 text-center text-muted d-none">
      No se encontraron copias de seguridad que coincidan con la búsqueda.
    </div>
  <?php endif; ?>
</div>

<!-- Formularios ocultos para Restauración y Eliminación con CSRF -->
<form id="restoreBackupForm" method="post" action="<?= base_url('backups/restore') ?>" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="filename" id="restoreBackupFilename" value="">
</form>

<form id="deleteBackupForm" method="post" action="<?= base_url('backups/delete') ?>" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="filename" id="deleteBackupFilename" value="">
</form>

