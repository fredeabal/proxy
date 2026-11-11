<!-- Cabecera de Página -->
<div class="page-header">
  <div>
    <h4 class="page-header-title">Usuarios</h4>
  </div>
  <div class="page-header-actions">
    <a href="<?= base_url('users/new') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
      <i class="ti ti-plus"></i> Crear
    </a>
  </div>
</div>

<div class="card border shadow-none mb-0">
  <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-2">
      <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
        <i class="ti ti-users fs-5"></i>
      </div>
      <h5 class="card-title fw-semibold mb-0">Usuarios Registrados</h5>
    </div>

    <!-- Buscador en tiempo real / servidor -->
    <div class="d-flex align-items-center gap-2">
      <form method="get" action="<?= base_url('users') ?>" class="m-0">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light"><i class="ti ti-search"></i></span>
          <input type="search"
                 name="q"
                 id="userSearchInput"
                 class="form-control"
                 placeholder="Buscar por nombre o correo..."
                 value="<?= esc($search ?? '') ?>">
          <?php if (! empty($search)): ?>
            <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary" title="Limpiar"><i class="ti ti-x"></i></a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="card-body p-0">
    <?php if (empty($users)): ?>
      <div class="text-center py-5 px-3">
        <div class="round-48 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-2">
          <i class="ti ti-users fs-5"></i>
        </div>
        <p class="text-muted mb-0">No se encontraron usuarios registrados.</p>
      </div>
    <?php else: ?>
      <!-- VISTA ESCRITORIO: TABLA (d-none d-md-block) - Clic en línea para editar -->
      <div class="table-responsive d-none d-md-block">
        <table class="table text-nowrap mb-0 align-middle table-hover" id="usersTable">
          <thead class="table-light">
            <tr>
              <th scope="col" class="ps-4">Nombre</th>
              <th scope="col" class="text-center">Rol</th>
              <th scope="col" class="text-center">Seguridad (2FA)</th>
              <th scope="col" class="text-center">Creado</th>
              <th scope="col" class="text-end pe-3"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <?php
                $isCurrent = ((int) session()->get('user_id') === (int) $u['id']);
                $initial = strtoupper(mb_substr($u['name'] ?? $u['email'], 0, 1));
                $isAdmin = ($u['role'] === 'admin');
              ?>
              <tr style="cursor: pointer;" onclick="window.location.href='<?= base_url('users/edit/' . $u['id']) ?>'" class="cursor-pointer">
                <td class="ps-4 text-wrap mw-300px">
                  <div class="d-flex align-items-center gap-3">
                    <img src="<?= current_user_avatar($u['avatar'] ?? null) ?>" class="round-36 rounded-circle object-fit-cover border border-primary-subtle flex-shrink-0" width="36" height="36" alt="user" />
                    <div class="text-wrap text-break">
                      <div class="fw-semibold mb-0 text-wrap text-break" style="font-size: 0.78rem; line-height: 1.25;"><?= esc($u['name']) ?></div>
                      <span class="text-muted text-break" style="font-size: 0.75rem;"><?= esc($u['email']) ?></span>
                    </div>
                  </div>
                </td>
                <td class="text-center">
                  <?php if ($isAdmin): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                      <i class="ti ti-shield me-1"></i> Administrador
                    </span>
                  <?php else: ?>
                    <span class="badge bg-light text-dark border">
                      <i class="ti ti-user me-1"></i> Usuario
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if (! empty($u['two_factor_secret'])): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                      <i class="ti ti-shield-check me-1"></i> 2FA Activo
                    </span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                      Inactivo
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <span class="text-muted small">
                    <?= !empty($u['created_at']) ? date('d/m/Y H:i', strtotime($u['created_at'])) : '-' ?>
                  </span>
                </td>
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
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('users/edit/' . $u['id']) ?>">
                          <i class="ti ti-pencil fs-4 text-primary"></i> Editar
                        </a>
                      </li>
                      <?php if (! $isCurrent): ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                          <a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="javascript:void(0);" onclick="confirmDeleteUser(<?= (int)$u['id'] ?>, '<?= esc($u['email'], 'js') ?>')">
                            <i class="ti ti-trash fs-4"></i> Eliminar
                          </a>
                        </li>
                      <?php endif; ?>
                    </ul>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- VISTA MÓVIL: TARJETAS (d-block d-md-none) - Clic en tarjeta para editar -->
      <div class="d-block d-md-none p-3" id="userCardsContainer">
        <?php foreach ($users as $u): ?>
          <?php
            $isCurrent = ((int) session()->get('user_id') === (int) $u['id']);
            $initial = strtoupper(mb_substr($u['name'] ?? $u['email'], 0, 1));
            $isAdmin = ($u['role'] === 'admin');
          ?>
          <div class="card border rounded-3 p-3 mb-3 shadow-none position-relative user-card-item cursor-pointer" style="cursor: pointer;" onclick="window.location.href='<?= base_url('users/edit/' . $u['id']) ?>'">
            <div class="d-flex align-items-start gap-3">
              <img src="<?= current_user_avatar($u['avatar'] ?? null) ?>" class="round-40 rounded-circle object-fit-cover border border-primary-subtle flex-shrink-0" width="40" height="40" alt="user" />
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center justify-content-between mb-1">
                  <div class="fw-semibold mb-0 text-truncate" style="font-size: 0.78rem; line-height: 1.25;"><?= esc($u['name']) ?></div>
                  <div class="dropdown dropstart" onclick="event.stopPropagation();">
                    <a href="javascript:void(0)" class="text-muted p-1" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="ti ti-dots-vertical fs-5"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('users/edit/' . $u['id']) ?>">
                          <i class="ti ti-pencil fs-4 text-primary"></i> Editar
                        </a>
                      </li>
                      <?php if (! $isCurrent): ?>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                          <a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="javascript:void(0);" onclick="confirmDeleteUser(<?= (int)$u['id'] ?>, '<?= esc($u['email'], 'js') ?>')">
                            <i class="ti ti-trash fs-4"></i> Eliminar
                          </a>
                        </li>
                      <?php endif; ?>
                    </ul>
                  </div>
                </div>
                <div class="text-muted text-truncate mb-2" style="font-size: 0.75rem;"><?= esc($u['email']) ?></div>
                <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                  <?php if ($isAdmin): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Admin</span>
                  <?php else: ?>
                    <span class="badge bg-light text-dark border">Usuario</span>
                  <?php endif; ?>

                  <?php if (! empty($u['two_factor_secret'])): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">2FA</span>
                  <?php endif; ?>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                  <span class="text-muted small">
                    <?= !empty($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '' ?>
                  </span>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal / Form oculto para eliminación -->
<form id="deleteUserForm" method="post" action="" class="d-none">
  <?= csrf_field() ?>
</form>

<script>
function confirmDeleteUser(id, email) {
  const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
  if (typeof Swal === 'undefined') {
    if (confirm('¿Seguro que deseas eliminar al usuario ' + email + '?')) {
      const form = document.getElementById('deleteUserForm');
      form.action = '<?= base_url('users/delete') ?>/' + id;
      form.submit();
    }
    return;
  }

  Swal.fire({
    title: '¿Eliminar usuario?',
    text: 'Esta acción no se puede deshacer. Se eliminará a ' + email + '.',
    icon: 'warning',
    showCancelButton: true,
    reverseButtons: true,
    buttonsStyling: false,
    background: isDark ? '#0b1114' : '#f8f9fa',
    color: isDark ? '#ffffff' : '#0b1114',
    customClass: {
      cancelButton: 'btn btn-dark btn-sm me-2',
      confirmButton: 'btn btn-danger btn-sm'
    },
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then(function (result) {
    if (result.isConfirmed) {
      const form = document.getElementById('deleteUserForm');
      form.action = '<?= base_url('users/delete') ?>/' + id;
      form.submit();
    }
  });
}
</script>
