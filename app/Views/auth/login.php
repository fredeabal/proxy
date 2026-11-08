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
  <title>Proxy Manager | Acceso</title>

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
          <!-- Columna izquierda: Fondo con Gradiente y Partículas -->
          <div class="col-xl-7 col-xxl-8 d-none d-xl-flex flex-column justify-content-center align-items-center bg-primary-gradient position-relative h-100">
            <!-- Animación de fondo: Red de nodos (Particles.js) -->
            <div id="particles-js" class="position-absolute w-100 h-100 top-0 start-0 z-index-1"></div>

            <div class="px-5 position-relative z-index-2" style="max-width: 650px; width: 100%;">
              <div class="d-inline-flex align-items-center justify-content-center p-3 bg-white bg-opacity-10 rounded-4 mb-4">
                <i class="ti ti-server text-white fs-8"></i>
              </div>
              <h1 class="text-white display-3 fw-bolder mb-3" style="line-height: 1.1; letter-spacing: -1.5px;">
                Proxy Manager
              </h1>
              <p class="text-white fs-5 mb-0 opacity-75" style="max-width: 500px; line-height: 1.6;">
                Administración de proxy inverso, enrutamiento de dominios y certificados SSL en tu servidor Linux.
              </p>
            </div>
          </div>
          
          <!-- Columna derecha: Formulario de Login -->
          <div class="col-12 col-xl-5 col-xxl-4 bg-body d-flex justify-content-center align-items-center p-4 h-100">
            <div class="auth-max-width w-100 col-sm-8 col-md-6 col-xl-9 px-4">
              
              <div class="text-center d-block mb-4 w-100">
                <div class="round-48 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-2">
                  <i class="ti ti-server fs-7"></i>
                </div>
                <h4 class="fw-bold mb-0">Proxy Manager</h4>
              </div>

              <h3 class="mb-1 fw-bold">Iniciar Sesión</h3>
              <p class="text-muted mb-4 fs-3">Introduce tus credenciales para acceder</p>

              <form action="<?= base_url('login') ?>" method="post" autocomplete="off">
                <?= csrf_field() ?>

                <div class="mb-3">
                  <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
                  <div class="input-group">
                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-mail"></i></span>
                    <input type="email" class="form-control" id="email" name="email" inputmode="email" autocomplete="email" placeholder="admin@demo.com" value="<?= old('email') ?>" required autofocus>
                  </div>
                </div>
                <div class="mb-4">
                  <label for="password" class="form-label fw-semibold">Contraseña</label>
                  <div class="input-group">
                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
                  </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-sm w-100 py-2 fs-4 fw-semibold rounded-2 mb-3 d-flex align-items-center justify-content-center shadow-none">
                  <i class="ti ti-login me-2"></i>Iniciar Sesión
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="<?= base_url('assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/simplebar/dist/simplebar.min.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/app.init.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/theme.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/app.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/particles.js/particles.min.js') ?>"></script>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const toastSuccess = <?= session()->has('success') ? json_encode(session('success')) : (session()->has('message') ? json_encode(session('message')) : 'null') ?>;
      const toastError = <?= session()->has('error') ? json_encode(session('error')) : 'null' ?>;
      const toastErrors = <?= session()->has('errors') ? json_encode(session('errors')) : 'null' ?>;
      
      const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
      
      const systemAlert = Swal.mixin({
        position: 'center',
        showConfirmButton: false,
        buttonsStyling: false,
        timer: 4000,
        timerProgressBar: true,
        background: isDark ? '#1e293b' : '#ffffff',
        color: isDark ? '#ffffff' : '#1e293b'
      });
      
      if (toastSuccess) {
        systemAlert.fire({ icon: 'success', title: '¡Completado!', html: `<div class="text-center">${toastSuccess}</div>`, iconColor: '#34c759' });
      }
      if (toastError) {
        systemAlert.fire({ icon: 'error', title: 'Error', html: `<div class="text-center">${toastError}</div>`, iconColor: '#b31b34' });
      }
      if (toastErrors) {
        const errorContent = typeof toastErrors === 'object' && toastErrors !== null
          ? (Array.isArray(toastErrors) ? toastErrors : Object.values(toastErrors)).join('<br>') 
          : toastErrors;
        systemAlert.fire({ icon: 'error', title: 'Error de Validación', html: `<div class="text-center">${errorContent}</div>`, iconColor: '#b31b34' });
      }

      // Inicializar Particles.js
      if (document.getElementById('particles-js') && typeof particlesJS !== 'undefined') {
        particlesJS("particles-js", {
          "particles": {
            "number": { "value": 70, "density": { "enable": true, "value_area": 800 } },
            "color": { "value": "#ffffff" },
            "shape": { "type": "circle" },
            "opacity": { "value": 0.5 },
            "size": { "value": 3, "random": true },
            "line_linked": {
              "enable": true,
              "distance": 150,
              "color": "#ffffff",
              "opacity": 0.4,
              "width": 1
            },
            "move": {
              "enable": true,
              "speed": 4,
              "direction": "none",
              "random": false,
              "straight": false,
              "out_mode": "out"
            }
          },
          "interactivity": {
            "detect_on": "canvas",
            "events": {
              "onhover": { "enable": true, "mode": "repulse" },
              "onclick": { "enable": true, "mode": "push" },
              "resize": true
            }
          },
          "retina_detect": true
        });
      }
    });
  </script>
</body>
</html>
