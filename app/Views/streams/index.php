<?php
/**
 * Listado de Streams TCP/UDP.
 * Variables: $streams (array)
 */
?>
<div class="page-header">
  <h4 class="page-header-title">Streams TCP / UDP</h4>
  <div class="page-header-actions">
    <a href="<?= base_url('streams/new') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
      <i class="ti ti-plus"></i> Nuevo Stream
    </a>
  </div>
</div>

<div class="card border shadow-none">
  <div class="card-body p-3 p-md-4">
    <?php if (empty($streams)): ?>
      <div class="text-center py-5 text-muted">
        <div class="round-50 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3">
          <i class="ti ti-arrows-transfer-up fs-7"></i>
        </div>
        <h5 class="fw-semibold">No hay Streams TCP/UDP configurados todavía</h5>
        <p class="text-muted small mb-3">Crea un stream para reenviar tráfico TCP o UDP (PostgreSQL, SSH, DNS, etc.).</p>
        <a href="<?= base_url('streams/new') ?>" class="btn btn-primary btn-sm">
          <i class="ti ti-plus me-1"></i> Crear primer Stream
        </a>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Puerto Entrada</th>
              <th>Destino</th>
              <th>Protocolo</th>
              <th>Descripción</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($streams as $s): ?>
              <tr>
                <td class="fw-bold font-monospace">:<?= (int) $s['incoming_port'] ?></td>
                <td class="font-monospace"><?= esc($s['forward_host']) ?>:<?= (int) $s['forward_port'] ?></td>
                <td>
                  <?php
                    $protoLabel = match ($s['protocol']) {
                        'udp'     => 'UDP',
                        'tcp_udp' => 'TCP + UDP',
                        default   => 'TCP',
                    };
                  ?>
                  <span class="badge bg-info-subtle text-info"><?= $protoLabel ?></span>
                </td>
                <td class="text-muted small"><?= esc($s['description'] ?? '—') ?></td>
                <td>
                  <?php if (!empty($s['enabled'])): ?>
                    <span class="badge bg-success-subtle text-success">Activo</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">Inactivo</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <a href="<?= base_url('streams/toggle/' . (int) $s['id']) ?>" class="btn btn-outline-secondary btn-sm" title="Activar/Desactivar">
                      <i class="ti ti-power"></i>
                    </a>
                    <a href="<?= base_url('streams/edit/' . (int) $s['id']) ?>" class="btn btn-outline-primary btn-sm" title="Editar">
                      <i class="ti ti-pencil"></i>
                    </a>
                    <form method="post" action="<?= base_url('streams/delete/' . (int) $s['id']) ?>" onsubmit="return confirm('¿Eliminar este Stream TCP/UDP?');">
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar">
                        <i class="ti ti-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
