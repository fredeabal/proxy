/**
 * Reverse Proxy Manager - Manejador de Formulario y Editor Vhost (Estilo CloudPanel)
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // Inicializar Select2 en selects del formulario si aún no están inicializados
    if (window.jQuery && jQuery.fn.select2) {
      $('#scheme, #ssl_certificate').each(function () {
        if (!$(this).data('select2')) {
          $(this).select2({ width: '100%', minimumResultsForSearch: Infinity });
        }
      });
    }

    initCertificateDomainFilter();
    initSslToggles();
    initFormSubmits();
    initVhostEditor();

    // Si la URL contiene #tab-vhost, abrir automáticamente la pestaña Vhost
    if (window.location.hash === '#tab-vhost') {
      const vhostBtn = document.getElementById('tab-vhost-btn');
      if (vhostBtn && window.bootstrap && window.bootstrap.Tab) {
        new window.bootstrap.Tab(vhostBtn).show();
      }
    }
  });

  /**
   * Filtra dinámicamente el selector de certificados SSL según el dominio ingresado.
   * Si un certificado existente no cubre el dominio (ej. esweb.es para pepe.com), no se muestra.
   */
  function initCertificateDomainFilter() {
    const domainInput = document.getElementById('domain');
    const sslSelect   = document.getElementById('ssl_certificate');
    if (!domainInput || !sslSelect) return;

    function doesCertCover(certDomain, hostDomain) {
      certDomain = (certDomain || '').toLowerCase().trim();
      hostDomain = (hostDomain || '').toLowerCase().trim();
      if (!certDomain || !hostDomain) return false;

      // Coincidencia exacta o variante www
      if (certDomain === hostDomain || 'www.' + certDomain === hostDomain || certDomain === 'www.' + hostDomain) {
        return true;
      }

      // Wildcard (*.dominio.com)
      if (certDomain.startsWith('*.')) {
        const base = certDomain.slice(2);
        if (hostDomain === base || hostDomain.endsWith('.' + base)) {
          return true;
        }
      }

      // Mismo dominio base (ej. sub.empresa.com y empresa.com)
      const certParts = certDomain.replace(/^\*\./, '').split('.');
      const hostParts = hostDomain.replace(/^\*\./, '').split('.');
      if (certParts.length >= 2 && hostParts.length >= 2) {
        const certBase = certParts.slice(-2).join('.');
        const hostBase = hostParts.slice(-2).join('.');
        if (certBase === hostBase) {
          return true;
        }
      }

      return false;
    }

    function filterOptions() {
      const rawVal = domainInput.value.trim();
      const primaryDomain = rawVal.split(/[\s,]+/)[0].toLowerCase();
      let selectedOptionInvalid = false;

      const certOptions = sslSelect.querySelectorAll('option[data-cert-domain]');
      certOptions.forEach(opt => {
        const certDomain = opt.dataset.certDomain || '';
        const isValid = primaryDomain !== '' && doesCertCover(certDomain, primaryDomain);

        if (isValid) {
          opt.hidden = false;
          opt.disabled = false;
          opt.style.display = '';
        } else {
          opt.hidden = true;
          opt.disabled = true;
          opt.style.display = 'none';

          if (sslSelect.value === opt.value) {
            selectedOptionInvalid = true;
          }
        }
      });

      // Si la opción que estaba seleccionada ya no es válida para el dominio actual, resetear a 'none'
      if (selectedOptionInvalid) {
        sslSelect.value = 'none';
        sslSelect.dispatchEvent(new Event('change'));
        if (window.jQuery) {
          $(sslSelect).val('none').trigger('change');
        }
      }
      if (window.jQuery && $(sslSelect).data('select2')) {
        $(sslSelect).trigger('change.select2');
      }
    }

    domainInput.addEventListener('input', filterOptions);
    domainInput.addEventListener('change', filterOptions);
    // Ejecutar al cargar la página
    filterOptions();
  }

  /**
   * Controla la visualización condicional de opciones SSL
   */
  function initSslToggles() {
    const sslSelect   = document.getElementById('ssl_certificate');
    const lePanel     = document.getElementById('letsEncryptPanel');
    const sslOptions  = document.getElementById('sslOptionsPanel');

    if (!sslSelect) return;

    function updateSslVisibility() {
      const val = sslSelect.value;
      const hasSsl = val && val !== 'none';
      const isNew  = val === 'new';

      if (lePanel) {
        lePanel.classList.toggle('d-none', !isNew);
      }
      if (sslOptions) {
        sslOptions.classList.toggle('d-none', !hasSsl);
      }
    }

    sslSelect.addEventListener('change', updateSslVisibility);
    if (window.jQuery) {
      $(sslSelect).on('change', updateSslVisibility);
    }
    updateSslVisibility();
  }

  /**
   * Feedback visual y prevención de doble clic en envíos de formulario
   */
  function initFormSubmits() {
    const mainForm = document.getElementById('proxyHostForm');
    if (!mainForm) return;

    mainForm.addEventListener('submit', function (e) {
      const btn = mainForm.querySelector('button[type="submit"]');
      if (btn && !btn.disabled) {
        btn.disabled = true;
        const icon = btn.querySelector('i');
        if (icon) {
          icon.className = 'spinner-border spinner-border-sm me-1';
        }
      }
    });
  }

  /**
   * Editor de Vhost Nginx interactivo con validación atómica (estilo CloudPanel)
   */
  function initVhostEditor() {
    const vhostEditor = document.getElementById('vhostEditor');
    const btnSave     = document.getElementById('btnSaveVhost');
    const btnReset    = document.getElementById('btnResetVhost');
    const alertBox    = document.getElementById('vhostAlertBox');

    if (!vhostEditor || !btnSave) return;

    // Permitir tabular con la tecla TAB dentro del textarea sin perder el foco
    vhostEditor.addEventListener('keydown', function (e) {
      if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end   = this.selectionEnd;
        this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 4;
      }
    });

    // Guardar Vhost personalizado
    btnSave.addEventListener('click', function () {
      const hostId = btnSave.dataset.hostId;
      const saveUrl = btnSave.dataset.saveUrl;
      const vhostContent = vhostEditor.value;

      if (!vhostContent.trim()) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'El contenido del Vhost no puede estar vacío.'
          });
        }
        return;
      }

      // Estado de carga
      btnSave.disabled = true;
      const origHtml = btnSave.innerHTML;
      btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Validando y aplicando...';
      if (alertBox) alertBox.classList.add('d-none');

      const csrfToken = document.querySelector('input[name="csrf_test_name"]')?.value || '';

      const formData = new FormData();
      formData.append('csrf_test_name', csrfToken);
      formData.append('vhost', vhostContent);

      fetch(saveUrl, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
      .then(response => response.json().then(data => ({ status: response.status, body: data })))
      .then(res => {
        btnSave.disabled = false;
        btnSave.innerHTML = origHtml;

        if (res.body.success) {
          if (alertBox) alertBox.classList.add('d-none');
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'success',
              title: '¡Vhost Aplicado!',
              text: res.body.message || 'La configuración de Nginx fue validada y recargada con éxito.',
              timer: 3000,
              showConfirmButton: false
            });
          }
        } else {
          if (alertBox) {
            alertBox.classList.remove('d-none');
            alertBox.innerHTML = '<strong>Error de sintaxis Nginx:</strong><br><pre class="mb-0 text-white mt-1">' + 
              (res.body.message || 'Error desconocido') + '</pre>';
          }
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'error',
              title: 'Error de Sintaxis Nginx',
              text: 'Nginx rechazó la configuración. Se ha mantenido la configuración previa intacta.',
            });
          }
        }
      })
      .catch(err => {
        btnSave.disabled = false;
        btnSave.innerHTML = origHtml;
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'error',
            title: 'Error de Red',
            text: 'No se pudo comunicar con el servidor para validar el Vhost.'
          });
        }
      });
    });

    // Restablecer Vhost a la plantilla estándar
    if (btnReset) {
      btnReset.addEventListener('click', function () {
        const resetUrl = btnReset.dataset.resetUrl;

        if (typeof Swal === 'undefined') {
          if (!confirm('¿Restablecer el Vhost a la plantilla estándar automática? Se perderán las modificaciones manuales.')) return;
          performReset();
        } else {
          const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
          Swal.fire({
            title: '¿Restablecer Vhost?',
            text: 'Se regenerará el archivo Nginx a partir de las opciones del formulario visual, descartando cambios manuales.',
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
            confirmButtonText: 'Restablecer',
            cancelButtonText: 'Cancelar'
          }).then((result) => {
            if (result.isConfirmed) {
              performReset();
            }
          });
        }

        function performReset() {
          btnReset.disabled = true;
          const origHtml = btnReset.innerHTML;
          btnReset.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Restableciendo...';

          const csrfToken = document.querySelector('input[name="csrf_test_name"]')?.value || '';
          const formData = new FormData();
          formData.append('csrf_test_name', csrfToken);

          fetch(resetUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
          })
          .then(r => r.json())
          .then(data => {
            btnReset.disabled = false;
            btnReset.innerHTML = origHtml;

            if (data.success) {
              if (data.vhost) {
                vhostEditor.value = data.vhost;
              }
              if (alertBox) alertBox.classList.add('d-none');
              if (typeof Swal !== 'undefined') {
                Swal.fire({
                  icon: 'success',
                  title: 'Vhost Restablecido',
                  text: data.message || 'Se ha restablecido la configuración estándar de Nginx.',
                  timer: 3000,
                  showConfirmButton: false
                });
              }
            } else {
              if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
              }
            }
          })
          .catch(() => {
            btnReset.disabled = false;
            btnReset.innerHTML = origHtml;
          });
        }
      });
    }
  }
})();
