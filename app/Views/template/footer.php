      </div>
    </div>
  </div>

  <!-- =====================================================================
       SCRIPTS GLOBALES MODERNIZE
       ===================================================================== -->
  <script src="<?= base_url('assets/libs/jquery/dist/jquery.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/simplebar/dist/simplebar.min.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/app.init.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/theme.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/app.min.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/sidebarmenu.js') ?>"></script>
  <script src="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/select2/dist/js/select2.full.min.js') ?>"></script>
  <script src="<?= base_url('assets/js/forms/select2.init.js') ?>"></script>
  <script src="<?= base_url('assets/libs/jquery-steps/build/jquery.steps.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/jquery-validation/dist/jquery.validate.min.js') ?>"></script>

  <?php if (!empty($extraScripts)): ?>
    <?php foreach ((array) $extraScripts as $script): ?>
      <?php $scriptVer = file_exists(FCPATH . $script) ? filemtime(FCPATH . $script) : time(); ?>
      <script src="<?= base_url($script . '?v=' . $scriptVer) ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Script para alternar modo claro / oscuro -->
  <script>
    (function() {
      const toggleBtn = document.getElementById('themeToggleBtn');
      const themeIcon = document.getElementById('themeIcon');
      
      function updateThemeIcon(theme) {
        if (!themeIcon) return;
        if (theme === 'dark') {
          themeIcon.className = 'ti ti-sun fs-5 text-warning';
        } else {
          themeIcon.className = 'ti ti-moon fs-5 text-dark';
        }
      }

      const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
      updateThemeIcon(currentTheme);

      if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
          const activeTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
          document.documentElement.setAttribute('data-bs-theme', activeTheme);
          localStorage.setItem('theme', activeTheme);
          updateThemeIcon(activeTheme);
        });
      }
    })();
  </script>

  <!-- Notificaciones SweetAlert2 automáticas estilo NetCrew / Deca -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
      const toastSuccess = <?= (session()->has('success') || session()->has('message')) ? json_encode(session('success') ?? session('message')) : 'null' ?>;
      const toastError = <?= session()->has('error') ? json_encode(session('error')) : 'null' ?>;
      const toastErrors = <?= session()->has('errors') ? json_encode(session('errors')) : 'null' ?>;

      const systemAlert = Swal.mixin({
        position: 'center',
        showConfirmButton: false,
        buttonsStyling: false,
        timer: 4000,
        timerProgressBar: true,
        background: isDark ? '#0b1114' : '#f8f9fa',
        color: isDark ? '#ffffff' : '#0b1114',
        showCloseButton: false
      });

      function escapeHtml(str) {
        if (!str) return '';
        const p = document.createElement('p');
        p.textContent = str;
        return p.innerHTML;
      }

      if (toastSuccess) {
        systemAlert.fire({ 
          icon: 'success', 
          title: '¡Completado!', 
          html: `<div class="text-center">${escapeHtml(toastSuccess)}</div>` 
        });
      }
      if (toastError) {
        systemAlert.fire({ 
          icon: 'error', 
          title: 'Error', 
          html: `<div class="text-center">${escapeHtml(toastError)}</div>` 
        });
      }
      if (toastErrors) {
        const errorList = typeof toastErrors === 'object' && toastErrors !== null
          ? (Array.isArray(toastErrors) ? toastErrors : Object.values(toastErrors))
          : [toastErrors];
        const errorContent = errorList.length > 1
          ? `<ul class="text-start mb-0 ps-3" style="display: inline-block;">` + errorList.map(e => `<li>${escapeHtml(e)}</li>`).join('') + `</ul>`
          : `<div class="text-center">${escapeHtml(errorList[0])}</div>`;
        systemAlert.fire({ 
          icon: 'error', 
          title: 'Error de Validación', 
          html: errorContent,
          timer: 5000
        });
      }
    });
  </script>
</body>
</html>

