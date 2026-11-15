<?php
/**
 * Formulario de creación/edición de Stream TCP/UDP.
 * Variables: $stream (array), $isEdit (bool), $preview (string|null)
 */
$isEdit    = !empty($isEdit);
$actionUrl = $isEdit ? base_url('streams/update/' . (int) $stream['id']) : base_url('streams/create');
?>
<div class="page-header">
  <h4 class="page-header-title"><?= $isEdit ? 'Editar Stream TCP/UDP' : 'Nuevo Stream TCP/UDP' ?></h4>
  <div class="page-header-actions">
    <a href="<?= base_url('streams') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
      <i class="ti ti-arrow-left"></i> Volver
    </a>
  </div>
</div>

<div class="card border shadow-none mb-4">
  <div class="card-body p-3 p-md-4">
    <form method="post" action="<?= esc($actionUrl) ?>">
      <?= csrf_field() ?>

      <div class="row g-3">
        <div class="col-12 col-md-3">
          <label for="incoming_port" class="form-label fw-semibold">Puerto Entrada <span class="text-danger">*</span></label>
          <input type="number" id="incoming_port" name="incoming_port" class="form-control font-monospace" min="1" max="65535" required
                 placeholder="ej. 5432"
                 value="<?= esc(old('incoming_port', $stream['incoming_port'] ?? '')) ?>">
        </div>

        <div class="col-12 col-md-4">
          <label for="forward_host" class="form-label fw-semibold">IP o Hostname Destino <span class="text-danger">*</span></label>
          <input type="text" id="forward_host" name="forward_host" class="form-control font-monospace" required
                 placeholder="ej. 10.0.0.20"
                 value="<?= esc(old('forward_host', $stream['forward_host'] ?? '')) ?>">
        </div>

        <div class="col-6 col-md-2">
          <label for="forward_port" class="form-label fw-semibold">Puerto Destino <span class="text-danger">*</span></label>
          <input type="number" id="forward_port" name="forward_port" class="form-control font-monospace" min="1" max="65535" required
                 placeholder="5432"
                 value="<?= esc(old('forward_port', $stream['forward_port'] ?? '')) ?>">
        </div>

        <div class="col-6 col-md-3">
          <label for="protocol" class="form-label fw-semibold">Protocolo <span class="text-danger">*</span></label>
          <?php $proto = old('protocol', $stream['protocol'] ?? 'tcp'); ?>
          <select id="protocol" name="protocol" class="select2 form-control">
            <option value="tcp" <?= $proto === 'tcp' ? 'selected' : '' ?>>TCP</option>
            <option value="udp" <?= $proto === 'udp' ? 'selected' : '' ?>>UDP</option>
            <option value="tcp_udp" <?= $proto === 'tcp_udp' ? 'selected' : '' ?>>TCP + UDP</option>
          </select>
        </div>

        <div class="col-12 col-md-8">
          <label for="description" class="form-label fw-semibold">Descripción (Opcional)</label>
          <input type="text" id="description" name="description" class="form-control"
                 placeholder="ej. PostgreSQL interno o SSH Bastion"
                 value="<?= esc(old('description', $stream['description'] ?? '')) ?>">
        </div>

        <div class="col-12 col-md-4 d-flex align-items-end">
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1"
                   <?= old('enabled', $stream['enabled'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="enabled">Stream Habilitado</label>
          </div>
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-center gap-2 mt-4 pt-2">
        <a href="<?= base_url('streams') ?>" class="btn btn-dark btn-sm">
          <i class="ti ti-x me-1"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="ti ti-device-floppy me-1"></i> <?= $isEdit ? 'Guardar' : 'Crear' ?>
        </button>
      </div>
    </form>
  </div>
</div>

<?php if ($isEdit && !empty($preview)): ?>
  <div class="card border shadow-none">
    <div class="card-header bg-transparent py-2">
      <h6 class="mb-0 fw-semibold"><i class="ti ti-file-code me-1"></i> Vista previa Nginx (<code>stream {}</code>)</h6>
    </div>
    <div class="card-body p-3">
      <pre class="nginx-terminal mb-0 font-monospace"><?= esc($preview) ?></pre>
    </div>
  </div>
<?php endif; ?>
