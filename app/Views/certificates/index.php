<!-- Cabecera de Página -->
<div class="page-header">
  <h4 class="page-header-title">Certificados SSL</h4>
  <div class="page-header-actions">
    <?php if ($expiringSoon > 0): ?>
      <a href="<?= base_url('certificates/renew-all') ?>" class="btn btn-dark btn-sm d-inline-flex align-items-center gap-1">
        <i class="ti ti-refresh"></i> Renovar
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Métricas Resumen -->
<div class="row g-2 g-md-3 mb-3 mb-md-4 metrics-summary-row">
  <div class="col-6 col-md-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="round-40 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
          <i class="ti ti-certificate fs-5"></i>
        </div>
        <div>
          <span class="text-muted fs-2 text-uppercase fw-semibold">Total</span>
          <h4 class="fw-bold mb-0"><?= $totalCerts ?></h4>
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
          <span class="text-muted fs-2 text-uppercase fw-semibold">Válidos</span>
          <h4 class="fw-bold mb-0 text-success"><?= $validCerts ?></h4>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="round-40 d-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle">
          <i class="ti ti-alert-triangle fs-5"></i>
        </div>
        <div>
          <span class="text-muted fs-2 text-uppercase fw-semibold">Por Caducar</span>
          <h4 class="fw-bold mb-0 text-warning"><?= $expiringSoon ?></h4>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="round-40 d-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle">
          <i class="ti ti-shield-x fs-5"></i>
        </div>
        <div>
          <span class="text-muted fs-2 text-uppercase fw-semibold">Caducados</span>
          <h4 class="fw-bold mb-0 text-danger"><?= $expiredCerts ?></h4>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- =========================================================================
     1. VISTA DESKTOP: TABLA INTEGRADA (d-none d-md-block)
     ========================================================================= -->
<div class="card border shadow-none mb-4 w-100 d-none d-md-block" id="certificatesCard">
  <!-- Cabecera de filtros y búsqueda integrada en el card sin botón de buscar -->
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
                 placeholder="Buscar por dominio o emisor..." 
                 value="<?= esc($search) ?>" 
                 autocomplete="off">
        </div>
      </div>
      <div class="col-md-5 col-lg-4">
        <select id="statusFilterSelect" class="select2 form-control">
          <option value="all">Todos los estados</option>
          <option value="valid">Solo Válidos</option>
          <option value="expiring_soon">Próximos a caducar (&lt; 15 días)</option>
          <option value="expired">Solo Caducados</option>
        </select>
      </div>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-4">Dominio</th>
            <th>Emisor</th>
            <th>Caducidad</th>
            <th class="text-center">Renovación</th>
            <th class="text-center">Estado</th>
            <th class="text-end pe-4">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($certificates)): ?>
            <tr>
              <td colspan="6" class="text-center py-5">
                <div class="py-4">
                  <div class="round-50 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3">
                    <i class="ti ti-certificate-off fs-7"></i>
                  </div>
                  <h5 class="fw-semibold">No hay Certificados SSL</h5>
                  <p class="text-muted small mb-0">
                    Los certificados se solicitan desde el paso SSL al crear o editar un Proxy Host.
                  </p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($certificates as $cert): ?>
              <?php
                $expiresTs = !empty($cert['expires_at']) ? strtotime($cert['expires_at']) : 0;
                $daysRemaining = $expiresTs > 0 ? round(($expiresTs - time()) / 86400) : 0;
                $statusKey = $cert['status'];
                if ($daysRemaining <= 0) {
                    $statusKey = 'expired';
                } elseif ($daysRemaining <= 15) {
                    $statusKey = 'expiring_soon';
                }
              ?>
              <tr class="cert-row" data-status="<?= esc($statusKey) ?>">
                <!-- Dominio -->
                <td class="ps-4">
                  <div class="d-flex align-items-center gap-2">
                    <span class="round-32 d-flex align-items-center justify-content-center <?= $statusKey === 'valid' ? 'bg-success-subtle text-success border border-success' : ($statusKey === 'expiring_soon' ? 'bg-warning-subtle text-warning border border-warning' : 'bg-danger-subtle text-danger border border-danger') ?> rounded-circle">
                      <i class="ti ti-lock fs-4"></i>
                    </span>
                    <div>
                      <div class="fw-semibold d-flex align-items-center gap-1" style="font-size: 0.78rem; line-height: 1.25;">
                        <?= esc($cert['domain']) ?>
                        <a href="https://<?= esc($cert['domain']) ?>" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="text-muted text-hover-primary" 
                           title="Abrir https://<?= esc($cert['domain']) ?> en nueva pestaña">
                          <i class="ti ti-external-link" style="font-size: 0.85rem;"></i>
                        </a>
                      </div>
                      <div class="text-muted font-monospace mt-1 d-flex align-items-center gap-1" style="font-size: 0.75rem;" title="<?= esc($cert['certificate_path']) ?>">
                        <i class="ti ti-file-certificate"></i>
                        <span class="text-truncate" style="max-width: 280px;"><?= esc(basename($cert['certificate_path'] ?: 'fullchain.pem')) ?></span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Emisor -->
                <td>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary font-monospace">
                    <?= esc($cert['issuer'] ?: "Let's Encrypt") ?>
                  </span>
                </td>

                <!-- Caducidad -->
                <td>
                  <div class="fw-semibold font-monospace fs-2">
                    <?= esc(date('d/m/Y', $expiresTs)) ?>
                  </div>
                  <div class="small <?= $daysRemaining <= 15 ? ($daysRemaining <= 0 ? 'text-danger fw-semibold' : 'text-warning fw-semibold') : 'text-muted' ?>">
                    <?php if ($daysRemaining > 0): ?>
                      Quedan <?= $daysRemaining ?> días
                    <?php else: ?>
                      Caducó hace <?= abs($daysRemaining) ?> días
                    <?php endif; ?>
                  </div>
                </td>

                <!-- Renovación Automática -->
                <td class="text-center">
                  <?php if ($cert['auto_renew']): ?>
                    <span class="badge badge-table bg-primary-subtle text-primary border border-primary">
                      <i class="ti ti-refresh"></i> Auto
                    </span>
                  <?php else: ?>
                    <span class="badge badge-table bg-secondary-subtle text-secondary border border-secondary">
                      Manual
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Estado -->
                <td class="text-center">
                  <?php if ($statusKey === 'valid'): ?>
                    <span class="badge badge-table-wide bg-success-subtle text-success border border-success">
                      <i class="ti ti-shield-check"></i> Válido
                    </span>
                  <?php elseif ($statusKey === 'expiring_soon'): ?>
                    <span class="badge badge-table-wide bg-warning-subtle text-warning border border-warning">
                      <i class="ti ti-alert-triangle"></i> Por Caducar
                    </span>
                  <?php else: ?>
                    <span class="badge badge-table-wide bg-danger-subtle text-danger border border-danger">
                      <i class="ti ti-shield-x"></i> Caducado
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Acciones (Menú de 3 puntos con dropstart y fixed strategy) -->
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
                        <form method="post" action="<?= base_url('certificates/renew/' . $cert['id']) ?>" class="m-0">
                          <?= csrf_field() ?>
                          <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                            <i class="ti ti-refresh fs-4"></i> Renovar
                          </button>
                        </form>
                      </li>
                      <li><hr class="dropdown-divider my-1"></li>
                      <li>
                        <button type="button" class="dropdown-item text-danger d-flex align-items-center gap-2" onclick="confirmDeleteCert(<?= $cert['id'] ?>, '<?= esc($cert['domain'], 'js') ?>')">
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
                <i class="ti ti-search-off me-1"></i> No se encontraron certificados que coincidan con la búsqueda.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- =========================================================================
     2. VISTA MÓVIL: TARJETAS PEGADAS AL BORDE (d-block d-md-none)
     ========================================================================= -->
<div id="certCardsContainer" class="d-block d-md-none">
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
                   placeholder="Buscar certificados..." 
                   autocomplete="off">
          </div>
        </div>
        <div class="col-12">
          <select id="mobileStatusSelect" class="select2 form-control">
            <option value="all">Todos los estados</option>
            <option value="valid">Solo Válidos</option>
            <option value="expiring_soon">Próximos a caducar</option>
            <option value="expired">Solo Caducados</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <?php if (empty($certificates)): ?>
    <div class="text-center py-4 text-muted border rounded p-3 mb-3">
      <i class="ti ti-certificate-off d-block mb-2 text-muted opacity-75 fs-7"></i>
      <span>No hay certificados SSL registrados.</span>
    </div>
  <?php else: ?>
    <?php foreach ($certificates as $cert): ?>
      <?php
        $expiresTs = !empty($cert['expires_at']) ? strtotime($cert['expires_at']) : 0;
        $daysRemaining = $expiresTs > 0 ? round(($expiresTs - time()) / 86400) : 0;
        $statusKey = $cert['status'];
        if ($daysRemaining <= 0) {
            $statusKey = 'expired';
        } elseif ($daysRemaining <= 15) {
            $statusKey = 'expiring_soon';
        }
      ?>
      <div class="card border shadow-sm mb-2 cert-card-item cert-row" data-status="<?= esc($statusKey) ?>">
        <div class="card-body p-3">
          <!-- Cabecera: Icono + Dominio con enlace externo + Badge de estado a la derecha -->
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2 overflow-hidden">
              <span class="round-32 rounded-circle <?= $statusKey === 'valid' ? 'bg-success-subtle text-success border border-success' : ($statusKey === 'expiring_soon' ? 'bg-warning-subtle text-warning border border-warning' : 'bg-danger-subtle text-danger border border-danger') ?> d-flex align-items-center justify-content-center flex-shrink-0">
                <i class="ti ti-lock fs-5"></i>
              </span>
              <div class="overflow-hidden">
                <div class="d-flex align-items-center gap-1 text-truncate">
                  <span class="fw-semibold mb-0 text-truncate" style="font-size: 0.78rem; line-height: 1.25;"><?= esc($cert['domain']) ?></span>
                  <a href="https://<?= esc($cert['domain']) ?>" 
                     target="_blank" 
                     rel="noopener noreferrer" 
                     class="text-muted p-1 flex-shrink-0" 
                     title="Abrir https://<?= esc($cert['domain']) ?> en nueva pestaña">
                    <i class="ti ti-external-link" style="font-size: 0.85rem;"></i>
                  </a>
                </div>
                <div class="text-muted font-monospace text-truncate d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                  <i class="ti ti-certificate flex-shrink-0"></i>
                  <span class="text-truncate"><?= esc($cert['issuer'] ?: "Let's Encrypt") ?></span>
                </div>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-2">
              <?php if ($statusKey === 'valid'): ?>
                <span class="badge bg-success-subtle text-success border border-success fs-1">Válido</span>
              <?php elseif ($statusKey === 'expiring_soon'): ?>
                <span class="badge bg-warning-subtle text-warning border border-warning fs-1">Por Caducar</span>
              <?php else: ?>
                <span class="badge bg-danger-subtle text-danger border border-danger fs-1">Caducado</span>
              <?php endif; ?>

              <div class="dropdown dropstart d-inline-block" onclick="event.stopPropagation();">
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
                    <form method="post" action="<?= base_url('certificates/renew/' . $cert['id']) ?>" class="m-0">
                      <?= csrf_field() ?>
                      <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                        <i class="ti ti-refresh fs-4"></i> Renovar
                      </button>
                    </form>
                  </li>
                  <li><hr class="dropdown-divider my-1"></li>
                  <li>
                    <button type="button" class="dropdown-item text-danger d-flex align-items-center gap-2" onclick="confirmDeleteCert(<?= $cert['id'] ?>, '<?= esc($cert['domain'], 'js') ?>')">
                      <i class="ti ti-trash fs-4"></i> Eliminar
                    </button>
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Lista de Detalles Abajo en Líneas Separadas -->
          <div class="text-muted small mt-2">
            <div>
              <strong>Emisor:</strong> 
              <span class="text-body"><?= esc($cert['issuer'] ?: "Let's Encrypt") ?></span>
            </div>
            <div class="mt-1">
              <strong>Caducidad:</strong> 
              <span class="font-monospace text-body"><?= esc(date('d/m/Y H:i', $expiresTs)) ?></span>
              <span class="ms-1 <?= $daysRemaining <= 15 ? ($daysRemaining <= 0 ? 'text-danger fw-semibold' : 'text-warning fw-semibold') : 'text-muted' ?>">
                (<?= $daysRemaining > 0 ? "Quedan {$daysRemaining} días" : "Caducado" ?>)
              </span>
            </div>
            <div class="mt-1">
              <strong>Renovación auto:</strong> 
              <span class="<?= $cert['auto_renew'] ? 'text-body' : 'text-muted' ?>"><?= $cert['auto_renew'] ? 'Sí' : 'No' ?></span>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <div id="mobileNoResults" class="text-center py-4 text-muted border rounded p-3 mb-3" style="display: none;">
      <i class="ti ti-search-off d-block mb-2 text-muted opacity-75 fs-7"></i>
      <span>No se encontraron certificados que coincidan con la búsqueda.</span>
    </div>
  <?php endif; ?>
</div>

<!-- Formulario Oculto de Eliminación -->
<form id="deleteCertForm" method="post" action="" class="d-none">
  <?= csrf_field() ?>
</form>

<script>
function confirmDeleteCert(id, domain) {
  const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
  Swal.fire({
    title: '¿Eliminar Certificado SSL?',
    text: `¿Estás seguro de que deseas eliminar el certificado para "${domain}"? El host volverá a modo HTTP estándar.`,
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
      const form = document.getElementById('deleteCertForm');
      form.action = `<?= base_url('certificates/delete') ?>/${id}`;
      form.submit();
    }
  });
}

// Búsqueda y filtrado en tiempo real sin botón
document.addEventListener("DOMContentLoaded", function() {
  // Dropdowns con posicionamiento fijo para no desbordar ni cortarse con bordes de tarjetas o tablas
  if (window.bootstrap && window.bootstrap.Dropdown) {
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => {
      new window.bootstrap.Dropdown(el, { popperConfig: (cfg) => Object.assign({}, cfg, { strategy: 'fixed' }) });
    });
  }

  const searchInput = document.getElementById('tableSearchInput');
  const statusSelect = document.getElementById('statusFilterSelect');
  const mobileSearch = document.getElementById('mobileSearchInput');
  const mobileStatus = document.getElementById('mobileStatusSelect');
  const rows = document.querySelectorAll('.cert-row');
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

