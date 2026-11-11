<!-- Cabecera de Página -->
<style>
@keyframes healthSpin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.health-spinning {
  animation: healthSpin 0.9s linear infinite;
  display: inline-block;
}
.health-check-trigger {
  cursor: pointer;
  transition: transform 0.15s ease-in-out, opacity 0.15s ease-in-out;
}
.health-check-trigger:hover {
  transform: scale(1.15);
  opacity: 1 !important;
}
</style>
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
            <th class="text-center">Estado</th>
            <th class="text-end pe-4" style="width: 50px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($proxies)): ?>
            <tr>
              <td colspan="7" class="text-center py-5">
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
                <!-- Dominio y Aliases con el Mundo (Health Check) al principio -->
                <td class="ps-4">
                  <div class="d-flex align-items-center gap-2">
                    <button type="button" 
                            class="round-32 d-flex align-items-center justify-content-center bg-body-tertiary text-muted border rounded-circle flex-shrink-0 health-badge-<?= $item['id'] ?> p-0"
                            style="cursor: pointer; transition: all 0.2s;"
                            onclick="event.stopPropagation(); checkHostHealth(<?= $item['id'] ?>, true);"
                            data-host-id="<?= $item['id'] ?>"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            data-bs-title="Health Check: Pendiente (Clic para comprobar)">
                      <i class="ti ti-world fs-4 health-icon-<?= $item['id'] ?>"></i>
                    </button>
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
                    <span class="badge bg-secondary-subtle text-secondary font-monospace text-uppercase align-self-center">
                      <?= esc($item['scheme']) ?>
                    </span>
                    <?php if (!empty($item['load_balancing_enabled']) && !empty($item['upstreams'])): ?>
                      <div class="d-flex flex-column" style="gap: 1px;">
                        <?php foreach ($item['upstreams'] as $srv): ?>
                          <span class="font-monospace text-body fw-medium target-item-<?= $item['id'] ?>" 
                                data-host="<?= esc($srv['server_host']) ?>" 
                                data-port="<?= esc($srv['server_port']) ?>" 
                                style="font-size: 0.78rem; line-height: 1.25; transition: color 0.2s;">
                            <?= esc($srv['server_host']) ?>:<?= esc($srv['server_port']) ?>
                          </span>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="fw-semibold font-monospace single-target-<?= $item['id'] ?>" style="transition: color 0.2s;">
                        <?= esc($item['forward_host']) ?>:<?= esc($item['forward_port']) ?>
                      </span>
                      <?php if ($isLocalhost): ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary fs-1" title="Destino local en el mismo host">local</span>
                      <?php endif; ?>
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

                <!-- Estado -->
                <td class="text-center" onclick="event.stopPropagation();">
                  <a href="<?= base_url('proxy-hosts/toggle/' . $item['id']) ?>" 
                     onclick="toggleProxyStatus(event, <?= $item['id'] ?>);"
                     class="text-decoration-none status-badge-link-<?= $item['id'] ?>"
                     title="Clic para cambiar estado">
                    <?php if ($item['enabled']): ?>
                      <span class="badge badge-table bg-success-subtle text-success border border-success status-badge-<?= $item['id'] ?>">
                        <i class="ti ti-check"></i> Activo
                      </span>
                    <?php else: ?>
                      <span class="badge badge-table bg-secondary-subtle text-secondary border border-secondary status-badge-<?= $item['id'] ?>">
                        <i class="ti ti-x"></i> Inactivo
                      </span>
                    <?php endif; ?>
                  </a>
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
                        <a class="dropdown-item d-flex align-items-center gap-2" href="javascript:void(0)" onclick="checkHostHealth(<?= $item['id'] ?>, true)">
                          <i class="ti ti-activity fs-4"></i> Health Check
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2 menu-toggle-btn-<?= $item['id'] ?>" href="<?= base_url('proxy-hosts/toggle/' . $item['id']) ?>" onclick="toggleProxyStatus(event, <?= $item['id'] ?>);">
                          <i class="ti <?= $item['enabled'] ? 'ti-ban' : 'ti-check' ?> fs-4 menu-toggle-icon-<?= $item['id'] ?>"></i> <span class="menu-toggle-label-<?= $item['id'] ?>"><?= $item['enabled'] ? 'Desactivar' : 'Activar' ?></span>
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
              <td colspan="7" class="text-center py-4 text-muted">
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
          <!-- Cabecera: Mundo (Health Check) al principio + Dominio + Acciones -->
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2 overflow-hidden">
              <button type="button" 
                      class="round-32 rounded-circle bg-body-tertiary text-muted border d-flex align-items-center justify-content-center flex-shrink-0 health-badge-<?= $item['id'] ?> p-0"
                      onclick="event.stopPropagation(); checkHostHealth(<?= $item['id'] ?>, true);"
                      data-host-id="<?= $item['id'] ?>"
                      data-bs-toggle="tooltip"
                      data-bs-placement="top"
                      data-bs-title="Health Check: Pendiente (Toca para comprobar)">
                <i class="ti ti-world fs-5 health-icon-<?= $item['id'] ?>"></i>
              </button>
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
                  <a class="dropdown-item d-flex align-items-center gap-2" href="javascript:void(0)" onclick="checkHostHealth(<?= $item['id'] ?>, true)">
                    <i class="ti ti-activity fs-4"></i> Health Check
                  </a>
                </li>
                <li>
                  <a class="dropdown-item d-flex align-items-center gap-2 menu-toggle-btn-<?= $item['id'] ?>" href="<?= base_url('proxy-hosts/toggle/' . $item['id']) ?>" onclick="toggleProxyStatus(event, <?= $item['id'] ?>);">
                    <i class="ti <?= $item['enabled'] ? 'ti-ban' : 'ti-check' ?> fs-4 menu-toggle-icon-<?= $item['id'] ?>"></i> <span class="menu-toggle-label-<?= $item['id'] ?>"><?= $item['enabled'] ? 'Desactivar' : 'Activar' ?></span>
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
              <?php if (!empty($item['load_balancing_enabled']) && !empty($item['upstreams'])): ?>
                <div class="d-inline-flex flex-column align-top ms-1" style="gap: 1px;">
                  <?php foreach ($item['upstreams'] as $srv): ?>
                    <span class="font-monospace text-body fw-medium target-item-<?= $item['id'] ?>" 
                          data-host="<?= esc($srv['server_host']) ?>" 
                          data-port="<?= esc($srv['server_port']) ?>" 
                          style="font-size: 0.78rem; line-height: 1.25; transition: color 0.2s;">
                      <?= esc($item['scheme']) ?>://<?= esc($srv['server_host']) ?>:<?= esc($srv['server_port']) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="font-monospace text-body single-target-<?= $item['id'] ?>" style="transition: color 0.2s;"><?= esc($item['scheme']) ?>://<?= esc($item['forward_host']) ?>:<?= esc($item['forward_port']) ?></span>
              <?php endif; ?>
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
            <div class="mt-1 d-flex align-items-center justify-content-between">
              <strong>Estado:</strong> 
              <a href="<?= base_url('proxy-hosts/toggle/' . $item['id']) ?>" 
                 onclick="event.stopPropagation(); toggleProxyStatus(event, <?= $item['id'] ?>);"
                 class="text-decoration-none status-badge-link-<?= $item['id'] ?>"
                 title="Clic para cambiar estado">
                <?php if ($item['enabled']): ?>
                  <span class="badge badge-table bg-success-subtle text-success border border-success status-badge-<?= $item['id'] ?>">
                    <i class="ti ti-check"></i> Activo
                  </span>
                <?php else: ?>
                  <span class="badge badge-table bg-secondary-subtle text-secondary border border-secondary status-badge-<?= $item['id'] ?>">
                    <i class="ti ti-x"></i> Inactivo
                  </span>
                <?php endif; ?>
              </a>
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

// --- Health Check & AJAX Toggle Management ---
const healthCache = {};
const healthQueue = [];
let isQueueRunning = false;

function enqueueHealthCheck(id) {
  if (!healthQueue.includes(id) && !healthCache[id]) {
    healthQueue.push(id);
    if (!isQueueRunning) {
      processHealthQueue();
    }
  }
}

function processHealthQueue() {
  if (healthQueue.length === 0) {
    isQueueRunning = false;
    return;
  }
  isQueueRunning = true;
  const id = healthQueue.shift();
  checkHostHealth(id, false).finally(() => {
    setTimeout(processHealthQueue, 150); // Carga mínima: 150ms entre comprobaciones secuenciales
  });
}

function checkHostHealth(id, manual = false) {
  const badges = document.querySelectorAll('.health-badge-' + id);
  const icons = document.querySelectorAll('.health-icon-' + id);

  if (!manual && healthCache[id]) {
    applyHealthUI(id, healthCache[id]);
    return Promise.resolve(healthCache[id]);
  }

  // Estado cargando
  badges.forEach(b => {
    b.className = 'round-32 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 p-0 border bg-body-tertiary text-primary border-primary-subtle health-badge-' + id;
    updateElementTooltip(b, 'Comprobando salud del backend...');
  });
  icons.forEach(el => {
    el.className = 'ti ti-loader-2 fs-4 text-primary health-spinning health-icon-' + id;
  });

  return fetch('<?= base_url('proxy-hosts/health-check') ?>/' + id, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.json())
  .then(data => {
    healthCache[id] = data;
    applyHealthUI(id, data);
    return data;
  })
  .catch(err => {
    const errorData = { status: 'offline', detail: 'Sin respuesta / Error de red' };
    healthCache[id] = errorData;
    applyHealthUI(id, errorData);
    return errorData;
  });
}

function applyHealthUI(id, data) {
  const badges = document.querySelectorAll('.health-badge-' + id);
  const icons = document.querySelectorAll('.health-icon-' + id);

  let badgeClass = 'round-32 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 p-0 border health-badge-' + id;
  let iconClass = 'ti fs-4 health-icon-' + id;
  let tooltipText = '';

  if (data.status === 'online') {
    badgeClass += ' bg-success-subtle text-success border-success';
    iconClass += ' ti-world text-success';
    tooltipText = 'Online: ' + (data.detail || 'Operativo');
  } else if (data.status === 'warning' || data.status === 'error') {
    badgeClass += ' bg-warning-subtle text-warning border-warning';
    iconClass += ' ti-world text-warning';
    tooltipText = (data.status === 'warning' ? 'Atención: ' : 'Alerta: ') + (data.detail || 'Error en respuesta');
  } else {
    badgeClass += ' bg-danger-subtle text-danger border-danger';
    iconClass += ' ti-world-off text-danger';
    tooltipText = 'Offline: ' + (data.detail || 'Inaccesible');
  }

  badges.forEach(b => {
    b.className = badgeClass;
    updateElementTooltip(b, tooltipText);
  });
  icons.forEach(el => {
    el.className = iconClass;
  });

  // Resaltar en rojo los destinos/IPs caídos
  if (data.servers && data.servers.length > 0) {
    data.servers.forEach(srv => {
      const targetEls = document.querySelectorAll('.target-item-' + id + '[data-host="' + srv.host + '"][data-port="' + srv.port + '"]');
      targetEls.forEach(el => {
        if (srv.is_online) {
          el.classList.remove('text-danger', 'fw-bold');
          el.classList.add('text-body');
          el.removeAttribute('title');
        } else {
          el.classList.remove('text-body');
          el.classList.add('text-danger', 'fw-bold');
          el.setAttribute('title', 'Servidor KO (Sin respuesta)');
        }
      });
    });
  } else {
    const allTargets = document.querySelectorAll('.single-target-' + id + ', .target-item-' + id);
    allTargets.forEach(el => {
      if (data.is_online) {
        el.classList.remove('text-danger', 'fw-bold');
        el.removeAttribute('title');
      } else {
        el.classList.remove('text-body');
        el.classList.add('text-danger', 'fw-bold');
        el.setAttribute('title', 'Servidor KO (Sin respuesta)');
      }
    });
  }
}

function updateElementTooltip(element, title) {
  element.setAttribute('data-bs-original-title', title);
  element.setAttribute('data-bs-title', title);
  element.setAttribute('title', title);

  if (window.bootstrap && window.bootstrap.Tooltip) {
    let inst = window.bootstrap.Tooltip.getInstance(element);
    if (!inst) {
      inst = new window.bootstrap.Tooltip(element);
    }
    if (inst.setContent) {
      inst.setContent({ '.tooltip-inner': title });
    }
  }
}

function toggleProxyStatus(event, id) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }

  const badges = document.querySelectorAll('.status-badge-' + id);
  const links = document.querySelectorAll('.status-badge-link-' + id);
  const menuIcons = document.querySelectorAll('.menu-toggle-icon-' + id);
  const menuLabels = document.querySelectorAll('.menu-toggle-label-' + id);

  links.forEach(l => l.style.pointerEvents = 'none');
  badges.forEach(b => {
    b.innerHTML = '<i class="ti ti-loader-2 health-spinning"></i> Guardando...';
  });

  fetch('<?= base_url('proxy-hosts/toggle') ?>/' + id, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.json())
  .then(data => {
    links.forEach(l => l.style.pointerEvents = '');
    if (data.success) {
      const isEnabled = data.enabled;

      badges.forEach(b => {
        b.className = 'badge badge-table border status-badge-' + id + ' ' + 
          (isEnabled ? 'bg-success-subtle text-success border-success' : 'bg-secondary-subtle text-secondary border-secondary');
        b.innerHTML = isEnabled ? '<i class="ti ti-check"></i> Activo' : '<i class="ti ti-x"></i> Inactivo';
      });

      menuIcons.forEach(icon => {
        icon.className = 'ti ' + (isEnabled ? 'ti-ban' : 'ti-check') + ' fs-4 menu-toggle-icon-' + id;
      });
      menuLabels.forEach(label => {
        label.textContent = isEnabled ? 'Desactivar' : 'Activar';
      });

      // Actualizar atributo data-status para filtros
      document.querySelectorAll('tr.proxy-row, .proxy-card-item').forEach(row => {
        if (row.querySelector('.status-badge-' + id)) {
          row.setAttribute('data-status', isEnabled ? 'active' : 'disabled');
        }
      });

      // Actualizar contador métrico si existe
      const metricEl = document.getElementById('metricActiveCount');
      if (metricEl) {
        let cur = parseInt(metricEl.textContent.trim(), 10) || 0;
        metricEl.textContent = isEnabled ? (cur + 1) : Math.max(0, cur - 1);
      }

      // Re-verificar salud
      checkHostHealth(id, true);
    } else {
      if (window.Swal) {
        Swal.fire({
          icon: 'error',
          title: 'Error de Nginx',
          text: data.message || 'No se pudo alternar el estado.',
          confirmButtonText: 'Entendido'
        });
      } else {
        alert(data.message || 'Error al cambiar estado.');
      }
      location.reload();
    }
  })
  .catch(err => {
    links.forEach(l => l.style.pointerEvents = '');
    location.reload();
  });
}

// Búsqueda y filtrado en tiempo real sin botón
document.addEventListener("DOMContentLoaded", function() {
  // Inicializar tooltips de Bootstrap
  if (window.bootstrap && window.bootstrap.Tooltip) {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
      new window.bootstrap.Tooltip(el);
    });
  }

  // Soporte universal para dropdowns con estrategia fixed (evita cortes de bordes en tablas y cards)
  if (window.bootstrap && window.bootstrap.Dropdown) {
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => {
      new window.bootstrap.Dropdown(el, { popperConfig: (cfg) => Object.assign({}, cfg, { strategy: 'fixed' }) });
    });
  }

  // Encolar Health Check automático con mínima carga (secuencial en segundo plano)
  document.querySelectorAll('[data-host-id]').forEach(el => {
    const hostId = el.getAttribute('data-host-id');
    if (hostId) {
      enqueueHealthCheck(parseInt(hostId, 10));
    }
  });

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
