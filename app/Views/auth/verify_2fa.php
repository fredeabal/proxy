<!DOCTYPE html>
<html lang="es" dir="ltr" data-color-theme="Blue_Theme" data-layout="vertical" class="auth-no-scroll">
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
  <title>Proxy Manager | Verificación 2FA</title>

  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.css') ?>" />
</head>
<body class="auth-no-scroll">

  <div id="main-wrapper" class="auth-customizer-none auth-screen-fit">
    <div class="position-relative overflow-hidden w-100 h-100">
      <div class="position-relative z-index-5 h-100">
        <div class="row g-0 h-100">
          <!-- Columna izquierda -->
          <div class="col-xl-7 col-xxl-8 d-none d-xl-flex flex-column justify-content-center align-items-center bg-primary-gradient position-relative h-100">
            <div class="px-5 position-relative z-index-2">
              <div class="d-inline-flex align-items-center justify-content-center p-3 bg-white bg-opacity-10 rounded-4 mb-4">
                <i class="ti ti-shield-lock text-white fs-8"></i>
              </div>
              <h1 class="text-white display-3 fw-bolder mb-3">
                Verificación 2FA
              </h1>
              <p class="text-white fs-5 mb-0 opacity-75">
                Protección de segundo factor (TOTP RFC 6238) para la cuenta de administración.
              </p>
            </div>
          </div>

          <!-- Columna derecha: Formulario TOTP -->
          <div class="col-12 col-xl-5 col-xxl-4 bg-body d-flex justify-content-center align-items-center p-4 h-100">
            <div class="auth-max-width w-100 col-sm-8 col-md-6 col-xl-9 px-4">
              <div class="text-center d-block mb-4 w-100">
                <div class="round-48 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-2">
                  <i class="ti ti-shield-check fs-7"></i>
                </div>
                <h4 class="fw-bold mb-0">Autenticación en Dos Pasos</h4>
              </div>

              <p class="text-muted mb-4 fs-3 text-center">
                Introduce el código temporal de 6 dígitos generado por tu aplicación autenticadora.
              </p>

              <form action="<?= base_url('login/2fa') ?>" method="post" autocomplete="off">
                <?= csrf_field() ?>

                <div class="mb-4">
                  <label for="totp_code" class="form-label fw-semibold">Código de Seguridad (6 dígitos)</label>
                  <div class="input-group">
                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-key"></i></span>
                    <input type="text"
                           class="form-control font-monospace text-center"
                           id="totp_code"
                           name="totp_code"
                           inputmode="numeric"
                           autocomplete="one-time-code"
                           maxlength="6"
                           placeholder="Ej. 123456"
                           autofocus>
                  </div>
                </div>

                <div class="d-flex align-items-center justify-content-center gap-2 mt-4 pt-2">
                  <a href="<?= base_url('logout') ?>" class="btn btn-dark btn-sm">Cancelar</a>
                  <button type="submit" class="btn btn-primary btn-sm">Verificar</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="<?= base_url('assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.js') ?>"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const toastError = <?= session()->has('error') ? json_encode(session('error')) : 'null' ?>;
      const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

      if (toastError) {
        Swal.fire({
          position: 'center',
          icon: 'error',
          title: 'Error de Verificación',
          html: `<div class="text-center">${toastError}</div>`,
          showConfirmButton: false,
          timer: 4000,
          timerProgressBar: true,
          background: isDark ? '#0b1114' : '#f8f9fa',
          color: isDark ? '#ffffff' : '#0b1114'
        });
      }
    });
  </script>
</body>
</html>

