<?php
/**
 * Listado de Access Lists (Control de acceso por IP/CIDR y Auth Basic).
 * Variables: $accessLists (array)
 */
?>
<div class="page-header">
  <h4 class="page-header-title">Access Lists</h4>
  <div class="page-header-actions">
    <a href="<?= base_url('access-lists/new') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
      <i class="ti ti-plus"></i> Nueva Access List
    </a>
  </div>
</div>

<div class="card border shadow-none">
  <div class="card-body p-3 p-md-4">
    <?php if (empty($accessLists)): ?>
      <div class="text-center py-5 text-muted">
        <div class="round-50 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3">
          <i class="ti ti-shield-check fs-7"></i>
        </div>
        <h5 class="fw-semibold">No hay Access Lists configuradas todavía</h5>
        <p class="text-muted small mb-3">Define listas reutilizables de IPs/CIDR permitidas o usuarios con contraseña (.htpasswd).</p>
        <a href="<?= base_url('access-lists/new') ?>" class="btn btn-primary btn-sm">
          <i class="ti ti-plus me-1"></i> Crear primera Access List
        </a>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Nombre</th>
              <th>Reglas IP / CIDR</th>
              <th>Usuarios Auth Basic</th>
              <th>Evaluación</th>
              <th>Hosts</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($accessLists as $al): ?>
              <tr>
                <td class="fw-semibold"><?= esc($al['name']) ?></td>
                <td><span class="badge bg-info-subtle text-info"><?= count($al['clients'] ?? []) ?> reglas</span></td>
                <td><span class="badge bg-primary-subtle text-primary"><?= count($al['users'] ?? []) ?> usuarios</span></td>
                <td>
                  <?php if (!empty($al['satisfy_any'])): ?>
                    <span class="badge bg-warning-subtle text-warning">Satisfy Any (IP o Auth)</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">Satisfy All (IP y Auth)</span>
                  <?php endif; ?>
                </td>
                <td><?= (int) ($al['host_count'] ?? 0) ?></td>
                <td class="text-end pe-3" onclick="event.stopPropagation();">
                  <div class="dropdown dropstart d-inline-block">
                    <a href="javascript:void(0)"
                       class="text-muted p-1 d-inline-block"
                       data-bs-toggle="dropdown"
                       data-bs-popper-config='{"strategy":"fixed"}'
                       aria-expanded="false"
                       title="Acciones">
                      <i class="ti ti-dots-vertical fs-5"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('access-lists/edit/' . (int) $al['id']) ?>">
                          <i class="ti ti-pencil fs-4"></i> Editar
                        </a>
                      </li>
                      <li><hr class="dropdown-divider my-1"></li>
                      <li>
                        <form method="post" action="<?= base_url('access-lists/delete/' . (int) $al['id']) ?>" class="m-0" onsubmit="return confirm('¿Eliminar esta Access List? Se desvinculará de los hosts asociados.');">
                          <?= csrf_field() ?>
                          <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2">
                            <i class="ti ti-trash fs-4"></i> Eliminar
                          </button>
                        </form>
                      </li>
                    </ul>
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
