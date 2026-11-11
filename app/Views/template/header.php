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

            <!-- Menú Desplegable de Usuario (Estilo DeCA / Modernize) -->
            <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-end gap-2">
              <li class="nav-item dropdown">
                <a class="nav-link pe-0" href="javascript:void(0)" id="dropUser" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="d-flex align-items-center gap-2">
                    <div class="user-profile-img">
                      <img src="<?= current_user_avatar() ?>" class="round-32 rounded-circle object-fit-cover border border-primary-subtle" width="32" height="32" alt="user" />
                    </div>
                    <span class="d-none d-md-block fw-semibold text-body fs-3"><?= esc($displayName) ?></span>
                  </div>
                </a>
                <div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up py-0" aria-labelledby="dropUser">
                  <div class="profile-dropdown position-relative" data-simplebar>
                    <div class="py-3 px-7 pb-0 d-flex align-items-center justify-content-between">
                      <h5 class="mb-0 fs-4 fw-semibold">Perfil de Usuario</h5>
                      <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 fs-1 fw-semibold">
                        <i class="ti <?= (session()->get('user_role') === 'admin') ? 'ti-shield' : 'ti-user' ?> me-1"></i><?= (session()->get('user_role') === 'admin') ? 'Administrador' : 'Usuario' ?>
                      </span>
                    </div>
                    <div class="d-flex align-items-center py-9 mx-7 border-bottom">
                      <img src="<?= current_user_avatar() ?>" class="round-40 rounded-circle object-fit-cover border border-2 border-primary-subtle flex-shrink-0" width="40" height="40" alt="user" />
                      <div class="ms-3 overflow-hidden">
                        <h5 class="mb-1 fs-3 fw-semibold text-truncate"><?= esc($displayName) ?></h5>
                        <span class="d-block text-muted fs-2 text-truncate"><i class="ti ti-mail me-1"></i><?= esc($displayEmail) ?></span>
                      </div>
                    </div>
                    <div class="message-body">
                      <a href="<?= base_url('profile') ?>" class="py-8 px-7 d-flex align-items-center dropdown-item text-decoration-none">
                        <span class="d-flex align-items-center justify-content-center bg-primary-subtle rounded-1 p-6 text-primary flex-shrink-0">
                          <i class="ti ti-user fs-6"></i>
                        </span>
                        <div class="w-75 d-inline-block v-middle ps-3">
                          <h6 class="mb-1 fw-semibold">Mi Perfil</h6>
                          <span class="d-block text-muted fs-2">Configuración de cuenta y acceso</span>
                        </div>
                      </a>
                    </div>
                    <div class="d-grid py-3 px-7">
                      <form action="<?= base_url('logout') ?>" method="post" class="m-0 p-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2 py-2">
                          <i class="ti ti-logout fs-4"></i> Cerrar Sesión
                        </button>
                      </form>
                    </div>
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

