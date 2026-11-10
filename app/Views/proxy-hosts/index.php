<!-- Cabecera de Página -->
<div class="page-header">
  <h4 class="page-header-title">Proxy Hosts</h4>
  <div class="page-header-actions">
    <a href="<?= base_url('proxy-hosts/new') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
      <i class="ti ti-plus"></i> Crear
    </a>
  </div>
</div>

<!-- Tarjetas Resumen de Métricas Rápidas -->
<div class="row g-2 g-md-3 mb-3 mb-md-4 metrics-summary-row">
  <div class="col-6 col-md-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="round-40 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
          <i class="ti ti-server fs-5"></i>
        </div>
        <div>
          <span class="text-muted fs-2 text-uppercase fw-semibold">Total</span>
          <h4 class="fw-bold mb-0"><?= esc($totalCount) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="round-40 d-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle">
          <i class="ti ti-circle-check fs-5"></i>
        </div>
        <div>
          <span class="text-muted fs-2 text-uppercase fw-semibold">Activos</span>
          <h4 class="fw-bold mb-0"><?= esc($activeCount) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="round-40 d-flex align-items-center justify-content-center bg-info-subtle text-info rounded-circle">
          <i class="ti ti-shield-lock fs-5"></i>
        </div>
        <div>
          <span class="text-muted fs-2 text-uppercase fw-semibold">Con SSL</span>
          <h4 class="fw-bold mb-0"><?= esc($sslCount) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="round-40 d-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle">
          <i class="ti ti-arrows-exchange fs-5"></i>
        </div>
        <div>
          <span class="text-muted fs-2 text-uppercase fw-semibold">WebSockets</span>
          <h4 class="fw-bold mb-0"><?= esc($wsCount) ?></h4>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 1. Vista de Escritorio / Tablet: Tabla Responsiva con Buscador Integrado (d-none d-md-block) -->
<div class="card border shadow-none mb-4 w-100 d-none d-md-block" id="proxyHostsCard">
  <!-- Filtros y Búsqueda en Tiempo Real dentro del card de la tabla -->
  <div class="card-body p-3 border-bottom">
    <div class="row g-2 align-items-center">
      <div class="col-md-7 col-lg-8">
        <div class="input-group">
          <span class="input-group-text bg-transparent border-end-0">
            <i class="ti ti-search text-muted"></i>
          </span>
          <input type="search" 
                 id="tableSearchInput" 
                 class="form-control border-start-0 ps-0 ps-3" 
                 placeholder="Buscar..." 
                 autocomplete="off">
        </div>
      </div>
      <div class="col-md-5 col-lg-4">
        <select id="statusFilterSelect" class="select2 form-control">
          <option value="all">Todos los estados</option>
          <option value="active">Solo Activos</option>
          <option value="disabled">Solo Inactivos</option>
        </select>
      </div>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-4">Dominio y Aliases</th>
            <th>Destino (Backend)</th>
            <th class="text-center">SSL</th>
            <th class="text-center">Redirect</th>
            <th class="text-center">WebSockets</th>
            <th class="text-end pe-4" style="width: 50px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($proxies)): ?>
            <tr>
              <td colspan="6" class="text-center py-5">
                <div class="py-4">
                  <div class="round-50 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3">
                    <i class="ti ti-world-off fs-7"></i>
                  </div>
                  <h5 class="fw-semibold">No se encontraron Proxy Hosts</h5>
                  <p class="text-muted small mb-0">
                    <?= !empty($search) ? 'No hay resultados que coincidan con la búsqueda.' : 'Aún no has configurado ningún enrutamiento de dominio.' ?>
                  </p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($proxies as $item): ?>
              <?php 
                $aliases = !empty($item['domain_names']) ? preg_split('/[\s,]+/', trim($item['domain_names']), -1, PREG_SPLIT_NO_EMPTY) : [];
                $isLocalhost = in_array(strtolower($item['forward_host']), ['localhost', '127.0.0.1', '::1'], true) || str_starts_with($item['forward_host'], '127.');
              ?>
              <tr class="proxy-row" data-status="<?= $item['enabled'] ? 'active' : 'disabled' ?>" onclick="window.location.href='<?= base_url('proxy-hosts/edit/' . $item['id']) ?>'">
                <!-- Dominio y Aliases -->
                <td class="ps-4">
                  <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('proxy-hosts/toggle/' . $item['id']) ?>" 
                       onclick="event.stopPropagation();"
                       class="round-32 d-flex align-items-center justify-content-center <?= $item['enabled'] ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?> rounded-circle text-decoration-none flex-shrink-0"
                       title="<?= $item['enabled'] ? 'Activo (Clic para desactivar)' : 'Inactivo (Clic para activar)' ?>">
                      <i class="ti ti-world fs-4"></i>
                    </a>
                    <div>
                      <div class="fw-bold text-dark fs-3 d-flex align-items-center gap-1">
                        <?= esc($item['domain']) ?>
                        <a href="<?= esc($item['scheme']) ?>://<?= esc($item['domain']) ?>" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();" class="text-muted text-hover-primary" title="Abrir en nueva pestaña">
                          <i class="ti ti-external-link fs-2"></i>
                        </a>
                      </div>
                      <?php if (!empty($aliases)): ?>
                        <div class="text-muted font-monospace mt-1 d-flex align-items-center gap-1" style="font-size: 0.8rem;" title="<?= esc(implode(', ', $aliases)) ?>">
                          <i class="ti ti-corner-down-right"></i>
                          <span class="text-truncate" style="max-width: 320px;">
                            <?= esc(implode(', ', array_slice($aliases, 0, 2))) ?><?php if (count($aliases) > 2): ?> <span class="opacity-75">+<?= count($aliases) - 2 ?></span><?php endif; ?>
                          </span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>

                <!-- Destino -->
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary-subtle text-secondary font-monospace text-uppercase">
                      <?= esc($item['scheme']) ?>
                    </span>
                    <span class="fw-semibold font-monospace">
                      <?= esc($item['forward_host']) ?>:<?= esc($item['forward_port']) ?>
                    </span>
                    <?php if ($isLocalhost): ?>
                      <span class="badge bg-secondary-subtle text-secondary border border-secondary fs-1" title="Destino local en el mismo host">local</span>
                    <?php endif; ?>
                  </div>
                </td>

                <!-- SSL -->
                <td class="text-center">
                  <?php if ($item['ssl_enabled']): ?>
                    <span class="badge badge-table bg-success-subtle text-success border border-success">
                      <i class="ti ti-lock"></i> Activo
                    </span>
                  <?php else: ?>
                    <span class="badge badge-table bg-secondary-subtle text-secondary border border-secondary">
                      <i class="ti ti-lock-off"></i> Inactivo
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Redirect -->
                <td class="text-center">
                  <?php if ($item['force_https']): ?>
                    <span class="badge badge-table bg-primary-subtle text-primary border border-primary">
                      <i class="ti ti-arrow-right"></i> Sí
                    </span>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>

                <!-- WebSockets -->
                <td class="text-center">
                  <?php if ($item['websocket_enabled']): ?>
                    <span class="badge badge-table bg-warning-subtle text-warning border border-warning">
                      <i class="ti ti-bolt"></i> WS On
                    </span>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>

                <!-- Acciones (Menú de 3 puntos estilo DeCA) -->
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
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('proxy-hosts/edit/' . $item['id']) ?>">
                          <i class="ti ti-pencil fs-4"></i> Editar
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('proxy-hosts/edit/' . $item['id']) ?>#tab-vhost">
                          <i class="ti ti-file-code fs-4"></i> Vhost Nginx
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('proxy-hosts/toggle/' . $item['id']) ?>">
                          <i class="ti <?= $item['enabled'] ? 'ti-ban' : 'ti-check' ?> fs-4"></i> <?= $item['enabled'] ? 'Desactivar' : 'Activar' ?>
                        </a>
                      </li>
                      <?php if ($item['ssl_enabled']): ?>
                        <li>
                          <form method="post" action="<?= base_url('proxy-hosts/renew-ssl/' . $item['id']) ?>" class="m-0">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                              <i class="ti ti-refresh fs-4"></i> Renovar SSL
                            </button>
                          </form>
                        </li>
                      <?php endif; ?>
                      <li><hr class="dropdown-divider my-1"></li>
                      <li>
                        <button type="button" class="dropdown-item text-danger d-flex align-items-center gap-2" onclick="confirmDelete(<?= $item['id'] ?>, '<?= esc($item['domain'], 'js') ?>')">
                          <i class="ti ti-trash fs-4"></i> Eliminar
                        </button>
                      </li>
                    </ul>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr id="noResultsRow" style="display: none;">
              <td colspan="6" class="text-center py-4 text-muted">
                <i class="ti ti-search-off me-1"></i> No se encontraron coincidencias para la búsqueda.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- 2. Vista Móvil: Tarjetas Pegadas al Borde con Lista de Atributos (d-block d-md-none - Patrón DeCA) -->
<div id="proxyCardsContainer" class="d-block d-md-none">
  <!-- Filtros y Búsqueda Móvil -->
  <div class="card border shadow-none mb-2">
    <div class="card-body p-2">
      <div class="row g-2 align-items-center">
        <div class="col-12">
          <div class="input-group">
            <span class="input-group-text bg-transparent border-end-0">
              <i class="ti ti-search text-muted"></i>
            </span>
            <input type="search" 
                   id="mobileSearchInput" 
                   class="form-control border-start-0 ps-0 ps-3" 
                   placeholder="Buscar..." 
                   autocomplete="off">
          </div>
        </div>
        <div class="col-12">
          <select id="mobileStatusSelect" class="select2 form-control">
            <option value="all">Todos los estados</option>
            <option value="active">Solo Activos</option>
            <option value="disabled">Solo Inactivos</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <?php if (empty($proxies)): ?>
    <div class="text-center py-4 text-muted border rounded p-3 mb-3">
      <i class="ti ti-world-off d-block mb-2 text-muted opacity-75 fs-7"></i>
      <span>No hay Proxy Hosts registrados.</span>
    </div>
  <?php else: ?>
    <?php foreach ($proxies as $item): ?>
      <?php 
        $aliases = !empty($item['domain_names']) ? preg_split('/[\s,]+/', trim($item['domain_names']), -1, PREG_SPLIT_NO_EMPTY) : [];
      ?>
      <div class="card border shadow-sm mb-2 proxy-card-item cursor-pointer proxy-row" 
           data-status="<?= $item['enabled'] ? 'active' : 'disabled' ?>"
           style="cursor: pointer;"
           onclick="window.location.href='<?= base_url('proxy-hosts/edit/' . $item['id']) ?>'">
        <div class="card-body p-3">
          <!-- Cabecera: Mundo al lado del dominio + Chevron a la derecha -->
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2 overflow-hidden">
              <a href="<?= base_url('proxy-hosts/toggle/' . $item['id']) ?>" 
                 onclick="event.stopPropagation();"
                 class="round-32 rounded-circle <?= $item['enabled'] ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?> d-flex align-items-center justify-content-center flex-shrink-0 text-decoration-none"
                 title="<?= $item['enabled'] ? 'Activo (Toca para desactivar)' : 'Inactivo (Toca para activar)' ?>">
                <i class="ti ti-world fs-5"></i>
              </a>
              <div class="overflow-hidden">
                <div class="d-flex align-items-center gap-1 text-truncate">
                  <h6 class="fw-bold mb-0 text-body fs-4 text-truncate"><?= esc($item['domain']) ?></h6>
                  <a href="<?= esc($item['scheme']) ?>://<?= esc($item['domain']) ?>" 
                     target="_blank" 
                     rel="noopener noreferrer" 
                     onclick="event.stopPropagation();" 
                     class="text-muted p-1 flex-shrink-0" 
                     title="Abrir en nueva pestaña">
                    <i class="ti ti-external-link fs-2"></i>
                  </a>
                </div>
                <?php if (!empty($aliases)): ?>
                  <div class="text-muted font-monospace text-truncate d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                    <i class="ti ti-corner-down-right flex-shrink-0"></i>
                    <span class="text-truncate"><?= esc(implode(', ', $aliases)) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <div class="dropdown dropstart flex-shrink-0 ms-2" onclick="event.stopPropagation();">
              <a href="javascript:void(0)" 
                 class="text-muted d-inline-flex align-items-center justify-content-center text-decoration-none" 
                 data-bs-toggle="dropdown" 
                 data-bs-popper-config='{"strategy":"fixed"}'
                 aria-expanded="false"
                 style="min-width: 44px; min-height: 44px; margin: -10px -8px -10px 0;"
                 title="Acciones">
                <i class="ti ti-dots-vertical fs-5"></i>
              </a>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                <li>
                  <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('proxy-hosts/edit/' . $item['id']) ?>">
                    <i class="ti ti-pencil fs-4"></i> Editar
                  </a>
                </li>
                <li>
                  <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('proxy-hosts/edit/' . $item['id']) ?>#tab-vhost">
                    <i class="ti ti-file-code fs-4"></i> Vhost Nginx
                  </a>
                </li>
                <li>
                  <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('proxy-hosts/toggle/' . $item['id']) ?>">
                    <i class="ti <?= $item['enabled'] ? 'ti-ban' : 'ti-check' ?> fs-4"></i> <?= $item['enabled'] ? 'Desactivar' : 'Activar' ?>
                  </a>
                </li>
                <?php if ($item['ssl_enabled']): ?>
                  <li>
                    <form method="post" action="<?= base_url('proxy-hosts/renew-ssl/' . $item['id']) ?>" class="m-0">
                      <?= csrf_field() ?>
                      <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                        <i class="ti ti-refresh fs-4"></i> Renovar SSL
                      </button>
                    </form>
                  </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                  <button type="button" class="dropdown-item text-danger d-flex align-items-center gap-2" onclick="confirmDelete(<?= $item['id'] ?>, '<?= esc($item['domain'], 'js') ?>')">
                    <i class="ti ti-trash fs-4"></i> Eliminar
                  </button>
                </li>
              </ul>
            </div>
          </div>

          <!-- Lista de Detalles Abajo en Líneas Separadas (Estilo DeCA) -->
          <div class="text-muted small mt-2">
            <div>
              <strong>Destino:</strong> 
              <span class="font-monospace text-body"><?= esc($item['scheme']) ?>://<?= esc($item['forward_host']) ?>:<?= esc($item['forward_port']) ?></span>
            </div>
            <div class="mt-1">
              <strong>SSL:</strong> 
              <span class="<?= $item['ssl_enabled'] ? 'text-body' : 'text-muted' ?>"><?= $item['ssl_enabled'] ? 'Sí' : 'No' ?></span>
            </div>
            <div class="mt-1">
              <strong>Redirect:</strong> 
              <span class="<?= $item['force_https'] ? 'text-body' : 'text-muted' ?>"><?= $item['force_https'] ? 'Sí' : 'No' ?></span>
            </div>
            <div class="mt-1">
              <strong>WebSockets:</strong> 
              <span class="<?= $item['websocket_enabled'] ? 'text-body' : 'text-muted' ?>"><?= $item['websocket_enabled'] ? 'Sí' : 'No' ?></span>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <div id="mobileNoResults" class="text-center py-4 text-muted border rounded p-3 mb-3" style="display: none;">
      <i class="ti ti-search-off d-block mb-2 text-muted opacity-75 fs-7"></i>
      <span>No se encontraron coincidencias para la búsqueda.</span>
    </div>
  <?php endif; ?>
</div>

<!-- Modal oculto / Formulario para confirmación de eliminación con CSRF -->
<form id="deleteForm" method="post" action="" style="display: none;">
  <?= csrf_field() ?>
</form>

<script>
function confirmDelete(id, domain) {
  const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
  Swal.fire({
    title: '¿Eliminar Proxy Host?',
    text: `¿Estás seguro de que deseas eliminar la configuración para "${domain}"? Esta acción no se puede deshacer.`,
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
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.getElementById('deleteForm');
      form.action = `<?= base_url('proxy-hosts/delete') ?>/${id}`;
      form.submit();
    }
  });
}

// Búsqueda y filtrado en tiempo real sin botón
document.addEventListener("DOMContentLoaded", function() {
  // Soporte universal para dropdowns con estrategia fixed (evita cortes de bordes en tablas y cards)
  if (window.bootstrap && window.bootstrap.Dropdown) {
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => {
      new window.bootstrap.Dropdown(el, { popperConfig: (cfg) => Object.assign({}, cfg, { strategy: 'fixed' }) });
    });
  }

  const searchInput = document.getElementById('tableSearchInput');
  const statusSelect = document.getElementById('statusFilterSelect');
  const mobileSearch = document.getElementById('mobileSearchInput');
  const mobileStatus = document.getElementById('mobileStatusSelect');
  const rows = document.querySelectorAll('.proxy-row');
  const noResultsRow = document.getElementById('noResultsRow');
  const mobileNoResults = document.getElementById('mobileNoResults');

  function filterTable(e) {
    let query = '';
    let status = 'all';

    if (e && (e.target === mobileSearch || e.target === mobileStatus)) {
      query = (mobileSearch ? mobileSearch.value : '').toLowerCase().trim();
      status = mobileStatus ? mobileStatus.value : 'all';
      if (searchInput && searchInput.value !== (mobileSearch ? mobileSearch.value : '')) {
        searchInput.value = mobileSearch ? mobileSearch.value : '';
      }
      if (statusSelect && statusSelect.value !== status) {
        $(statusSelect).val(status).trigger('change.select2');
      }
    } else {
      query = (searchInput ? searchInput.value : '').toLowerCase().trim();
      status = statusSelect ? statusSelect.value : 'all';
      if (mobileSearch && mobileSearch.value !== (searchInput ? searchInput.value : '')) {
        mobileSearch.value = searchInput ? searchInput.value : '';
      }
      if (mobileStatus && mobileStatus.value !== status) {
        $(mobileStatus).val(status).trigger('change.select2');
      }
    }

    let visibleCount = 0;
    rows.forEach(function(row) {
      const rowText = row.innerText.toLowerCase();
      const rowStatus = row.getAttribute('data-status');

      const matchesQuery = query === '' || rowText.includes(query);
      const matchesStatus = status === 'all' || rowStatus === status;

      if (matchesQuery && matchesStatus) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    if (noResultsRow) {
      noResultsRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
    }
    if (mobileNoResults) {
      mobileNoResults.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
    }
  }

  if (searchInput) searchInput.addEventListener('input', filterTable);
  if (statusSelect) {
    statusSelect.addEventListener('change', filterTable);
    if (window.jQuery) $('#statusFilterSelect').on('change', filterTable);
  }
  if (mobileSearch) mobileSearch.addEventListener('input', filterTable);
  if (mobileStatus) {
    mobileStatus.addEventListener('change', filterTable);
    if (window.jQuery) $('#mobileStatusSelect').on('change', filterTable);
  }
});
</script>
