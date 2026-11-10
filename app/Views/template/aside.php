    <!-- =====================================================================
         BARRA LATERAL DE NAVEGACIÓN (ASIDE / SIDEBAR)
         ===================================================================== -->
    <?php
      $uri = service('uri');
      $firstSegment = $uri->getTotalSegments() > 0 ? $uri->getSegment(1) : 'dashboard';
      $currentSection = $activeMenu ?? ($firstSegment ?: 'dashboard');
      $currentTab = service('request')->getGet('tab') ?: '';
    ?>
    <aside class="left-sidebar with-vertical">
      <div>
        <div class="brand-logo d-flex align-items-center justify-content-between px-4 py-3">
          <a href="<?= base_url('dashboard') ?>" class="text-nowrap logo-img d-flex align-items-center gap-2 text-decoration-none">
            <span class="round-40 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
              <i class="ti ti-server fs-6"></i>
            </span>
            <span class="fw-bold fs-5 text-dark ms-1">Proxy Manager</span>
          </a>
          <a href="javascript:void(0)" class="sidebartoggler ms-auto text-decoration-none fs-5 d-block d-xl-none">
            <i class="ti ti-x"></i>
          </a>
        </div>

        <nav class="sidebar-nav scroll-sidebar" data-simplebar>
          <ul id="sidebarnav">
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">Gestión</span>
            </li>

            <li class="sidebar-item">
              <a class="sidebar-link <?= $currentSection === 'dashboard' ? 'active' : '' ?>" href="<?= base_url('dashboard') ?>" aria-expanded="false">
                <span>
                  <i class="ti ti-layout-dashboard"></i>
                </span>
                <span class="hide-menu">Dashboard</span>
              </a>
            </li>

            <li class="sidebar-item">
              <a class="sidebar-link <?= $currentSection === 'proxy-hosts' ? 'active' : '' ?>" href="<?= base_url('proxy-hosts') ?>" aria-expanded="false">
                <span>
                  <i class="ti ti-world"></i>
                </span>
                <span class="hide-menu">Proxy Hosts</span>
              </a>
            </li>

            <li class="nav-small-cap mt-4">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">Sistema</span>
            </li>

            <li class="sidebar-item">
              <a class="sidebar-link <?= $currentSection === 'admin' ? 'active' : '' ?>" href="<?= base_url('admin') ?>" aria-expanded="false">
                <span>
                  <i class="ti ti-settings"></i>
                </span>
                <span class="hide-menu">Administración</span>
              </a>
            </li>
          </ul>
        </nav>
      </div>
    </aside>
    <!-- Fin del Menú Lateral -->
    <div class="page-wrapper">

