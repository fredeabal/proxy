<!DOCTYPE html>
<html lang="es" dir="ltr" data-bs-theme="light" data-color-theme="Orange_Theme" data-layout="vertical" data-boxed-layout="full" data-sidebartype="full" data-sidebar-position="fixed" data-header-position="fixed">
<?php
  $displayName = session()->get('user_name') ?? 'Administrador';
  $displayEmail = session()->get('user_email') ?? 'admin@demo.com';
  $displayRole = ucfirst(session()->get('user_role') ?? 'admin');
?>
<head>
  <script>
    (function() {
      var saved = localStorage.getItem('theme');
      var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title><?= esc($title ?? 'Dashboard') ?> | Proxy Manager</title>

  <!-- Icono Favicon -->
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />

  <!-- CSS Principal Modernize -->
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/select2/dist/css/select2.min.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css?v=' . (file_exists(FCPATH . 'assets/css/custom.css') ? filemtime(FCPATH . 'assets/css/custom.css') : time())) ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/fonts/tabler-icons/tabler-icons.css') ?>" />
</head>

<body>
  <div id="main-wrapper">
    <?= view("template/aside") ?>

    <!-- Cabecera Superior (Topbar) -->
    <header class="topbar">
      <div class="with-vertical">
        <nav class="navbar navbar-expand-lg p-0">
          <ul class="navbar-nav">
            <li class="nav-item nav-icon-hover-bg rounded-circle ms-n2">
              <a class="nav-link sidebartoggler" id="headerCollapse" href="javascript:void(0)">
                <i class="ti ti-menu-2"></i>
              </a>
            </li>
          </ul>

          <div class="d-flex align-items-center justify-content-end ms-auto gap-2" id="navbarNav">
            <!-- Botón Cambiar Tema (Claro / Oscuro) -->
            <button class="btn btn-sm btn-outline-light text-dark border-0 rounded-circle p-2" id="themeToggleBtn" type="button" title="Cambiar tema">
              <i class="ti ti-moon fs-5" id="themeIcon"></i>
            </button>

            <!-- Menú Desplegable de Usuario -->
            <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-center">
              <li class="nav-item dropdown">
                <a class="nav-link pe-0" href="javascript:void(0)" id="dropUser" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="d-flex align-items-center gap-2">
                    <div class="round-32 d-flex align-items-center justify-content-center bg-primary text-white rounded-circle fs-3 fw-bold">
                      <?= esc(substr($displayName, 0, 1)) ?>
                    </div>
                    <span class="d-none d-md-block fw-semibold text-dark fs-3"><?= esc($displayName) ?></span>
                  </div>
                </a>
                <div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up shadow-sm border-0" aria-labelledby="dropUser">
                  <div class="py-3 px-4 border-bottom">
                    <h6 class="mb-1 fs-3 fw-semibold text-dark"><?= esc($displayName) ?></h6>
                    <span class="badge bg-primary-subtle text-primary mb-1"><?= esc($displayRole) ?></span>
                    <p class="mb-0 text-muted small d-flex align-items-center gap-1">
                      <i class="ti ti-mail"></i> <?= esc($displayEmail) ?>
                    </p>
                  </div>
                  <div class="p-3">
                    <form action="<?= base_url('logout') ?>" method="post" class="m-0 p-0">
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="ti ti-logout"></i> Cerrar Sesión
                      </button>
                    </form>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </nav>
      </div>
    </header>

    <!-- Contenedor del Cuerpo de la Página -->
    <div class="body-wrapper">
      <div class="container-fluid">

