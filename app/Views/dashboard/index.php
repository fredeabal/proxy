<!-- Cabecera de Página -->
<div class="page-header">
  <h4 class="page-header-title">Panel de Control</h4>
  <div class="page-header-actions">
    <span class="badge <?= $nginxStatus['active'] ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?> px-3 py-1 fs-3 fw-semibold d-inline-flex align-items-center gap-1">
      <i class="ti <?= $nginxStatus['active'] ? 'ti-circle-check' : 'ti-alert-circle' ?>"></i>
      Nginx <?= $nginxStatus['active'] ? 'Activo' : 'Inactivo' ?>
    </span>
  </div>
</div>

<!-- Tarjetas de Métricas (Mobile-first: 2 columnas en móvil, 4 en desktop) -->
<div class="row g-3 g-md-4 mb-4">
  <!-- Proxy Hosts -->
  <div class="col-6 col-xl-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between mb-2 mb-md-3">
          <span class="fw-semibold text-muted text-uppercase fs-2">Proxy Hosts</span>
          <div class="round-40 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
            <i class="ti ti-world fs-5"></i>
          </div>
        </div>
        <h3 class="fw-bold mb-1"><?= (int) $totalProxies ?></h3>
        <span class="text-success small fw-medium"><?= (int) $activeProxies ?> activos</span>
      </div>
    </div>
  </div>

  <!-- Certificados SSL -->
  <div class="col-6 col-xl-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between mb-2 mb-md-3">
          <span class="fw-semibold text-muted text-uppercase fs-2">Certificados</span>
          <div class="round-40 d-flex align-items-center justify-content-center bg-info-subtle text-info rounded-circle">
            <i class="ti ti-certificate fs-5"></i>
          </div>
        </div>
        <h3 class="fw-bold mb-1"><?= (int) $totalCerts ?></h3>
        <?php if ($expiringCerts > 0): ?>
          <span class="text-warning small fw-medium"><?= (int) $expiringCerts ?> por caducar</span>
        <?php else: ?>
          <span class="text-muted small">Todos al día</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Carga CPU -->
  <div class="col-6 col-xl-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between mb-2 mb-md-3">
          <span class="fw-semibold text-muted text-uppercase fs-2">Carga CPU</span>
          <div class="round-40 d-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle">
            <i class="ti ti-cpu fs-5"></i>
          </div>
        </div>
        <h3 class="fw-bold mb-1"><?= esc($cpuLoad['1m']) ?></h3>
        <span class="text-muted small">5m: <?= esc($cpuLoad['5m']) ?> &bull; 15m: <?= esc($cpuLoad['15m']) ?></span>
      </div>
    </div>
  </div>

  <!-- Memoria RAM -->
  <div class="col-6 col-xl-3">
    <div class="card border shadow-none mb-0 h-100">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between mb-2 mb-md-3">
          <span class="fw-semibold text-muted text-uppercase fs-2">Uso RAM</span>
          <div class="round-40 d-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle">
            <i class="ti ti-chart-pie fs-5"></i>
          </div>
        </div>
        <h3 class="fw-bold mb-1"><?= esc($memory['percent']) ?>%</h3>
        <span class="text-muted small"><?= esc($memory['used_mb']) ?> / <?= esc($memory['total_mb']) ?> MB</span>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Barras de Recursos Hardware -->
  <div class="col-lg-7">
    <div class="card border shadow-none h-100 mb-0">
      <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
        <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
          <i class="ti ti-server fs-5"></i>
        </div>
        <h5 class="card-title fw-semibold mb-0">Recursos del Servidor</h5>
      </div>
      <div class="card-body p-4">
        <!-- RAM -->
        <div class="mb-4">
          <div class="d-flex justify-content-between mb-2">
            <span class="fw-semibold small text-muted">Memoria RAM</span>
            <span class="fw-bold fs-3"><?= esc($memory['used_mb']) ?> MB / <?= esc($memory['total_mb']) ?> MB (<?= esc($memory['percent']) ?>%)</span>
          </div>
          <div class="progress" style="height: 8px;">
            <div class="progress-bar <?= $memory['percent'] > 85 ? 'bg-danger' : ($memory['percent'] > 70 ? 'bg-warning' : 'bg-primary') ?>" 
                 style="width: <?= esc($memory['percent']) ?>%"></div>
          </div>
        </div>

        <!-- Disco -->
        <div>
          <div class="d-flex justify-content-between mb-2">
            <span class="fw-semibold small text-muted">Espacio en Disco (/)</span>
            <span class="fw-bold fs-3"><?= esc($disk['used_gb']) ?> GB / <?= esc($disk['total_gb']) ?> GB (<?= esc($disk['percent']) ?>%)</span>
          </div>
          <div class="progress" style="height: 8px;">
            <div class="progress-bar <?= $disk['percent'] > 85 ? 'bg-danger' : ($disk['percent'] > 70 ? 'bg-warning' : 'bg-success') ?>" 
                 style="width: <?= esc($disk['percent']) ?>%"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Acciones Frecuentes -->
  <div class="col-lg-5">
    <div class="card border shadow-none h-100 mb-0">
      <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
        <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
          <i class="ti ti-bolt fs-5"></i>
        </div>
        <h5 class="card-title fw-semibold mb-0">Acciones Frecuentes</h5>
      </div>
      <div class="card-body p-4 d-flex flex-column justify-content-center gap-3">
        <a href="<?= base_url('proxy-hosts/new') ?>" class="quick-action-tile">
          <div class="d-flex align-items-center gap-3">
            <div class="action-icon bg-primary-subtle text-primary">
              <i class="ti ti-plus fs-5"></i>
            </div>
            <div>
              <div class="action-title">Nuevo Proxy Host</div>
              <p class="action-desc">Enrutar dominio o subdominio hacia un puerto o IP</p>
            </div>
          </div>
          <i class="ti ti-chevron-right fs-4 action-arrow"></i>
        </a>

        <a href="<?= base_url('proxy-hosts') ?>" class="quick-action-tile">
          <div class="d-flex align-items-center gap-3">
            <div class="action-icon bg-success-subtle text-success">
              <i class="ti ti-world fs-5"></i>
            </div>
            <div>
              <div class="action-title">Proxy Hosts</div>
              <p class="action-desc">Gestionar dominios, enrutamientos y certificados SSL</p>
            </div>
          </div>
          <i class="ti ti-chevron-right fs-4 action-arrow"></i>
        </a>
      </div>
    </div>
  </div>
</div>

