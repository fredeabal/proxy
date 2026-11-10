<?php
/**
 * Pestaña "Copias de Seguridad (Backups)" de Administración:
 *  - Política de retención y creación manual de backup
 *  - Tabla responsive y tarjetas móviles con acciones (descargar, restaurar, eliminar)
 *  - Formularios ocultos de acción (restore, delete)
 *
 * Variables: $retentionCount, $backups
 */
?>
<!-- Política de Retención -->
<div class="card border shadow-none mb-4">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
    <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
      <i class="ti ti-history fs-5"></i>
    </div>
    <h5 class="card-title fw-semibold mb-0">Política de Retención</h5>
  </div>
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('admin/backups/retention') ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-12">
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
          </div>
          <div class="form-text small text-muted">
            Máximo de copias a conservar (1–100). Las más antiguas se eliminan automáticamente.
          </div>
        </div>

        <div class="col-12 d-flex justify-content-center align-items-center gap-2 pt-2">
          <a href="<?= base_url('admin?tab=backups') ?>" class="btn btn-dark btn-sm">Cancelar</a>
          <button type="submit" class="btn btn-primary btn-sm px-3">Guardar</button>
          <button type="submit"
                  formaction="<?= base_url('admin/backups/create') ?>"
                  formnovalidate
                  class="btn btn-primary btn-sm px-3 d-inline-flex align-items-center gap-1">
            <i class="ti ti-plus"></i> Crear Backup
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Listado de Copias de Seguridad (SIN columna contenido) -->
<div class="card border shadow-none mb-4" id="backupsManagerCard">
  <!-- Buscador en tiempo real integrado -->
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
                  <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
                    <i class="ti ti-file-zip fs-5"></i>
                  </div>
                  <span class="fw-semibold font-monospace"><?= esc($b['filename']) ?></span>
                </div>
              </td>
              <td>
                <span class="badge border badge-action badge-keep-case bg-secondary-subtle text-secondary border-secondary font-monospace">
                  <?= esc($b['size_formatted'] ?? ($b['size_human'] ?? '—')) ?>
                </span>
              </td>
              <td class="font-monospace text-muted small">
                <?= esc($b['created_at']) ?>
              </td>
              <td class="text-end pe-4">
                <div class="dropdown">
                  <a href="javascript:void(0)"
                     class="text-muted p-2 d-inline-flex align-items-center justify-content-center text-decoration-none"
                     data-bs-toggle="dropdown"
                     aria-expanded="false"
                     title="Acciones">
                    <i class="ti ti-dots-vertical fs-5"></i>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('admin/backups/download/' . rawurlencode($b['filename'])) ?>">
                        <i class="ti ti-download fs-4"></i> Descargar
                      </a>
                    </li>
                    <li>
                      <button type="button" class="dropdown-item d-flex align-items-center gap-2 btn-restore-backup" data-filename="<?= esc($b['filename']) ?>">
                        <i class="ti ti-rotate-clockwise fs-4"></i> Restaurar
                      </button>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                      <button type="button" class="dropdown-item text-danger d-flex align-items-center gap-2 btn-delete-backup" data-filename="<?= esc($b['filename']) ?>">
                        <i class="ti ti-trash fs-4"></i> Eliminar
                      </button>
                    </li>
                  </ul>
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
                <i class="ti ti-archive fs-5"></i>
              </div>
              <p class="text-muted mb-0">No hay copias de seguridad generadas todavía.</p>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Tarjetas Móviles (< 768px) -->
  <div class="d-block d-md-none p-3" id="backupMobileCards">
    <?php if (!empty($backups)): ?>
      <?php foreach ($backups as $b): ?>
        <div class="card border shadow-none mb-2 p-3 mobile-card-item backup-item">
          <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <span class="fw-semibold font-monospace small text-truncate" style="max-width: 180px;">
              <?= esc($b['filename']) ?>
            </span>
            <span class="badge border badge-action badge-keep-case bg-secondary-subtle text-secondary border-secondary font-monospace">
              <?= esc($b['size_formatted'] ?? ($b['size_human'] ?? '—')) ?>
            </span>
          </div>
          <div class="small text-muted font-monospace mb-3">
            <i class="ti ti-calendar me-1"></i><?= esc($b['created_at']) ?>
          </div>
          <div class="d-flex justify-content-end pt-2 border-top">
            <div class="dropdown">
              <a href="javascript:void(0)"
                 class="text-muted p-2 d-inline-flex align-items-center justify-content-center text-decoration-none"
                 data-bs-toggle="dropdown"
                 aria-expanded="false"
                 title="Acciones">
                <i class="ti ti-dots-vertical fs-5"></i>
              </a>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                <li>
                  <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('admin/backups/download/' . rawurlencode($b['filename'])) ?>">
                    <i class="ti ti-download fs-4"></i> Descargar
                  </a>
                </li>
                <li>
                  <button type="button" class="dropdown-item d-flex align-items-center gap-2 btn-restore-backup" data-filename="<?= esc($b['filename']) ?>">
                    <i class="ti ti-rotate-clockwise fs-4"></i> Restaurar
                  </button>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                  <button type="button" class="dropdown-item text-danger d-flex align-items-center gap-2 btn-delete-backup" data-filename="<?= esc($b['filename']) ?>">
                    <i class="ti ti-trash fs-4"></i> Eliminar
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <div id="backupMobileNoResults" class="card border shadow-none p-4 text-center text-muted d-none">
        No se encontraron copias de seguridad que coincidan.
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Formularios ocultos de acción de backups -->
<form id="restoreBackupForm" method="post" action="<?= base_url('admin/backups/restore') ?>" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="filename" id="restoreBackupFilename" value="">
</form>

<form id="deleteBackupForm" method="post" action="<?= base_url('admin/backups/delete') ?>" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="filename" id="deleteBackupFilename" value="">
</form>

