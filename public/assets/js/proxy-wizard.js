/**
 * Gestor de Proxy Inverso - Form Wizard & SSL Logic
 * 
 * Gestiona el asistente por pasos (jquery-steps) con navegación libre entre
 * pestañas (1, 2, 3, 4 clicables), validación de campos requeridos y
 * lógica reactiva para el panel de certificados SSL (Let's Encrypt).
 */

(function () {
  'use strict';

  function initProxyWizard() {
    const $ = window.jQuery;
    // Si jQuery o el plugin de pasos aún no están en el DOM, esperar brevemente
    if (!$ || !$.fn || typeof $.fn.steps !== 'function') {
      setTimeout(initProxyWizard, 50);
      return;
    }

    const wizardForm = $('#proxyWizardForm');
    if (!wizardForm.length) {
      return;
    }

    // Evitar doble inicialización
    if (wizardForm.hasClass('wizard-initialized')) {
      return;
    }
    wizardForm.addClass('wizard-initialized');

    const isEditMode = wizardForm.data('mode') === 'edit';
    const finishLabel = isEditMode ? 'Actualizar' : 'Guardar';
    const cancelUrl = wizardForm.data('cancel-url') || '/proxy-hosts';

    // 1. Inicialización de jquery-steps con navegación libre entre pestañas
    wizardForm.steps({
      headerTag: 'h6',
      bodyTag: 'section',
      transitionEffect: 'fade',
      enableAllSteps: true, // Permite que los pasos 1, 2, 3, 4 sean enlaces directos
      titleTemplate: '<span class="step">#index#</span> #title#',
      labels: {
        finish: finishLabel,
        next: 'Siguiente',
        previous: 'Anterior',
        loading: 'Cargando...'
      },
      onInit: function (event, currentIndex) {
        const actionsList = wizardForm.find('.actions ul');
        if (actionsList.length && !actionsList.find('.cancel-step-item').length) {
          actionsList.prepend(
            '<li class="cancel-step-item"><a href="' + cancelUrl + '" class="btn btn-dark btn-sm btn-wizard-cancel">Cancelar</a></li>'
          );
          if (isEditMode) {
            actionsList.append(
              '<li class="delete-step-item"><button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteHost()">Eliminar</button></li>'
            );
          }
        }
      },
      onStepChanging: function (event, currentIndex, newIndex) {
        // Permitir siempre retroceder sin validar
        if (currentIndex > newIndex) {
          return true;
        }

        // Validar campos requeridos de la sección actual antes de avanzar
        let valid = true;
        const currentSection = wizardForm.find('.body').eq(currentIndex);
        currentSection.find('input[required], select[required]').each(function () {
          if (!this.checkValidity()) {
            this.reportValidity();
            valid = false;
            return false;
          }
        });

        return valid;
      },
      onFinishing: function (event, currentIndex) {
        let valid = true;
        const currentSection = wizardForm.find('.body').eq(currentIndex);
        currentSection.find('input[required], select[required]').each(function () {
          if (!this.checkValidity()) {
            this.reportValidity();
            valid = false;
            return false;
          }
        });
        return valid;
      },
      onFinished: function (event, currentIndex) {
        if (typeof window.syncCsrfTokenToForm === 'function') {
          window.syncCsrfTokenToForm(wizardForm[0]);
        }
        wizardForm.submit();
      }
    });

    // 2. Select2 para selección de esquema
    if (typeof $.fn.select2 === 'function') {
      $('#scheme').select2({
        width: '100%',
        minimumResultsForSearch: Infinity
      });
    }

    // 3. Lógica del selector de certificado SSL (Paso 4)
    initSslStepLogic($);

    // 4. Constructor visual Key-Value de cabeceras y Vista Previa Nginx en vivo (Paso 3)
    initCustomHeadersBuilder($);
  }

  /**
   * Catálogo oficial de cabeceras autorizadas en la lista blanca de ProxyHostRules
   */
  const HEADER_CATALOG = [
    // Grupo 1: Seguridad y Navegador (add_header)
    { group: 'Seguridad y Navegador (add_header)', name: 'X-Frame-Options', directive: 'add_header', defaultVal: 'SAMEORIGIN' },
    { group: 'Seguridad y Navegador (add_header)', name: 'X-Content-Type-Options', directive: 'add_header', defaultVal: 'nosniff' },
    { group: 'Seguridad y Navegador (add_header)', name: 'Referrer-Policy', directive: 'add_header', defaultVal: 'strict-origin-when-cross-origin' },
    { group: 'Seguridad y Navegador (add_header)', name: 'Permissions-Policy', directive: 'add_header', defaultVal: 'geolocation=(), microphone=(), camera=()' },
    { group: 'Seguridad y Navegador (add_header)', name: 'Content-Security-Policy', directive: 'add_header', defaultVal: "default-src 'self'" },
    { group: 'Seguridad y Navegador (add_header)', name: 'Strict-Transport-Security', directive: 'add_header', defaultVal: 'max-age=31536000; includeSubDomains' },
    { group: 'Seguridad y Navegador (add_header)', name: 'X-Robots-Tag', directive: 'add_header', defaultVal: 'noindex, nofollow' },
    { group: 'Seguridad y Navegador (add_header)', name: 'X-XSS-Protection', directive: 'add_header', defaultVal: '1; mode=block' },
    { group: 'Seguridad y Navegador (add_header)', name: 'Cross-Origin-Opener-Policy', directive: 'add_header', defaultVal: 'same-origin' },
    { group: 'Seguridad y Navegador (add_header)', name: 'Cross-Origin-Embedder-Policy', directive: 'add_header', defaultVal: 'require-corp' },
    { group: 'Seguridad y Navegador (add_header)', name: 'Cross-Origin-Resource-Policy', directive: 'add_header', defaultVal: 'same-origin' },
    { group: 'Seguridad y Navegador (add_header)', name: 'X-Permitted-Cross-Domain-Policies', directive: 'add_header', defaultVal: 'none' },
    { group: 'Seguridad y Navegador (add_header)', name: 'X-Download-Options', directive: 'add_header', defaultVal: 'noopen' },

    // Grupo 2: CORS y Caché (add_header)
    { group: 'CORS y Caché (add_header)', name: 'Access-Control-Allow-Origin', directive: 'add_header', defaultVal: '*' },
    { group: 'CORS y Caché (add_header)', name: 'Access-Control-Allow-Methods', directive: 'add_header', defaultVal: 'GET, POST, PUT, DELETE, OPTIONS' },
    { group: 'CORS y Caché (add_header)', name: 'Access-Control-Allow-Headers', directive: 'add_header', defaultVal: 'Origin, Content-Type, Accept, Authorization' },
    { group: 'CORS y Caché (add_header)', name: 'Access-Control-Allow-Credentials', directive: 'add_header', defaultVal: 'true' },
    { group: 'CORS y Caché (add_header)', name: 'Access-Control-Expose-Headers', directive: 'add_header', defaultVal: 'Content-Length, Content-Range' },
    { group: 'CORS y Caché (add_header)', name: 'Access-Control-Max-Age', directive: 'add_header', defaultVal: '86400' },
    { group: 'CORS y Caché (add_header)', name: 'Cache-Control', directive: 'add_header', defaultVal: 'no-store, no-cache, must-revalidate' },
    { group: 'CORS y Caché (add_header)', name: 'X-Custom-Header', directive: 'add_header', defaultVal: 'activo' },
    { group: 'CORS y Caché (add_header)', name: 'X-Custom', directive: 'add_header', defaultVal: 'activo' },
    { group: 'CORS y Caché (add_header)', name: 'X-Request-ID', directive: 'add_header', defaultVal: '1' },

    // Grupo 3: Proxy / Upstream hacia el Backend (proxy_set_header)
    { group: 'Upstream hacia Backend (proxy_set_header)', name: 'X-Original-URL', directive: 'proxy_set_header', defaultVal: '$request_uri' },
    { group: 'Upstream hacia Backend (proxy_set_header)', name: 'X-Forwarded-User', directive: 'proxy_set_header', defaultVal: 'admin' },
    { group: 'Upstream hacia Backend (proxy_set_header)', name: 'X-Forwarded-For', directive: 'proxy_set_header', defaultVal: '$proxy_add_x_forwarded_for' },
    { group: 'Upstream hacia Backend (proxy_set_header)', name: 'X-Real-IP', directive: 'proxy_set_header', defaultVal: '$remote_addr' },
    { group: 'Upstream hacia Backend (proxy_set_header)', name: 'X-Forwarded-Proto', directive: 'proxy_set_header', defaultVal: '$scheme' },
    { group: 'Upstream hacia Backend (proxy_set_header)', name: 'X-Forwarded-Host', directive: 'proxy_set_header', defaultVal: '$host' },
    { group: 'Upstream hacia Backend (proxy_set_header)', name: 'X-Forwarded-Port', directive: 'proxy_set_header', defaultVal: '$server_port' }
  ];

  function findHeaderMeta(headerName) {
    const lower = (headerName || '').trim().toLowerCase();
    if (lower === 'hsts') {
      return HEADER_CATALOG.find((h) => h.name === 'Strict-Transport-Security') || HEADER_CATALOG[0];
    }
    return HEADER_CATALOG.find((h) => h.name.toLowerCase() === lower) || null;
  }

  /**
   * Constructor Key-Value de Cabeceras HTTP + Vista Previa en Vivo estilo CloudPanel
   */
  function initCustomHeadersBuilder($) {
    const textarea   = document.getElementById('custom_headers');
    const rowsWrap   = document.getElementById('customHeadersRows');
    const emptyState = document.getElementById('customHeadersEmpty');
    const btnAdd     = document.getElementById('btnAddHeaderRow');
    const previewEl  = document.getElementById('nginxLivePreview');

    if (!textarea || !rowsWrap) return;

    function buildSelectOptionsHtml(selectedName) {
      const groups = {};
      HEADER_CATALOG.forEach((item) => {
        if (!groups[item.group]) groups[item.group] = [];
        groups[item.group].push(item);
      });

      let html = '';
      Object.keys(groups).forEach((groupLabel) => {
        html += '<optgroup label="' + groupLabel.replace(/"/g, '&quot;') + '">';
        groups[groupLabel].forEach((item) => {
          const isSelected = selectedName && item.name.toLowerCase() === selectedName.toLowerCase() ? ' selected' : '';
          html += '<option value="' + item.name + '" data-directive="' + item.directive + '" data-default="' + item.defaultVal.replace(/"/g, '&quot;') + '"' + isSelected + '>' + item.name + '</option>';
        });
        html += '</optgroup>';
      });
      return html;
    }

    function syncTextareaAndPreview() {
      const rows = rowsWrap.querySelectorAll('.custom-header-row');
      const lines = [];

      rows.forEach((row) => {
        const selectEl = row.querySelector('.header-name-select');
        const inputEl  = row.querySelector('.header-value-input');
        const name     = selectEl ? selectEl.value.trim() : '';
        const val      = inputEl ? inputEl.value.trim() : '';
        if (name && val) {
          lines.push(name + ': ' + val);
        }
      });

      textarea.value = lines.join('\n');
      if (emptyState) {
        emptyState.classList.toggle('d-none', rows.length > 0);
      }
      renderNginxPreview();
    }

    function updateBadge(badgeEl, directive) {
      if (!badgeEl) return;
      badgeEl.textContent = directive;
      if (directive === 'proxy_set_header') {
        badgeEl.className = 'badge bg-secondary-subtle text-secondary border border-secondary font-monospace badge-keep-case header-directive-badge';
      } else {
        badgeEl.className = 'badge bg-primary-subtle text-primary border border-primary font-monospace badge-keep-case header-directive-badge';
      }
    }

    function addHeaderRow(initialName, initialValue) {
      // Elegir la primera cabecera disponible que aún no esté usada si no se indica initialName
      let chosenMeta = initialName ? findHeaderMeta(initialName) : null;
      if (!chosenMeta) {
        const usedNames = Array.from(rowsWrap.querySelectorAll('.header-name-select')).map((s) => s.value.toLowerCase());
        chosenMeta = HEADER_CATALOG.find((h) => !usedNames.includes(h.name.toLowerCase())) || HEADER_CATALOG[0];
      }

      const headerName = chosenMeta.name;
      const headerVal  = initialValue !== undefined ? initialValue : chosenMeta.defaultVal;

      const row = document.createElement('div');
      row.className = 'custom-header-row row g-2 align-items-center';
      row.innerHTML =
        '<div class="col-12 col-md-5">' +
          '<select class="form-control select2 header-name-select">' +
            buildSelectOptionsHtml(headerName) +
          '</select>' +
        '</div>' +
        '<div class="col-12 col-md-5">' +
          '<input type="text" class="form-control font-monospace header-value-input" placeholder="Valor de la cabecera..."> ' +
        '</div>' +
        '<div class="col-12 col-md-2 d-flex align-items-center justify-content-between justify-content-md-end gap-2">' +
          '<span class="header-directive-badge"></span>' +
          '<button type="button" class="btn btn-danger btn-sm btn-remove-header" title="Eliminar">' +
            '<i class="ti ti-trash"></i>' +
          '</button>' +
        '</div>';

      const selectEl = row.querySelector('.header-name-select');
      const inputEl  = row.querySelector('.header-value-input');
      const badgeEl  = row.querySelector('.header-directive-badge');
      const removeBtn = row.querySelector('.btn-remove-header');

      inputEl.value = headerVal;
      inputEl.dataset.lastDefault = chosenMeta.defaultVal;
      updateBadge(badgeEl, chosenMeta.directive);

      rowsWrap.appendChild(row);

      function handleHeaderChange() {
        const meta = findHeaderMeta(selectEl.value) || HEADER_CATALOG[0];
        updateBadge(badgeEl, meta.directive);
        // Si el valor actual estaba vacío o coincidía con el valor por defecto anterior, autocompletar con el nuevo por defecto
        if (inputEl.value.trim() === '' || inputEl.value === inputEl.dataset.lastDefault) {
          inputEl.value = meta.defaultVal;
        }
        inputEl.dataset.lastDefault = meta.defaultVal;
        syncTextareaAndPreview();
      }

      if ($ && typeof $.fn.select2 === 'function') {
        $(selectEl).select2({ width: '100%' });
        $(selectEl).on('change', handleHeaderChange);
      } else {
        selectEl.addEventListener('change', handleHeaderChange);
      }

      inputEl.addEventListener('input', syncTextareaAndPreview);

      removeBtn.addEventListener('click', function () {
        if ($ && typeof $.fn.select2 === 'function' && $(selectEl).data('select2')) {
          $(selectEl).select2('destroy');
        }
        row.remove();
        syncTextareaAndPreview();
      });

      syncTextareaAndPreview();
    }

    function renderNginxPreview() {
      if (!previewEl) return;

      const schemeEl  = document.getElementById('scheme');
      const hostEl    = document.getElementById('forward_host');
      const portEl    = document.getElementById('forward_port');
      const wsEl      = document.getElementById('websocket_enabled');
      const sslVerEl  = document.getElementById('proxy_ssl_verify');
      const connEl    = document.getElementById('proxy_connect_timeout');
      const readEl    = document.getElementById('proxy_read_timeout');
      const sendEl    = document.getElementById('proxy_send_timeout');
      const maxBodyEl = document.getElementById('client_max_body_size');

      const scheme  = (schemeEl && schemeEl.value ? schemeEl.value : 'http').toLowerCase();
      const host    = (hostEl && hostEl.value.trim() ? hostEl.value.trim() : '127.0.0.1');
      const port    = (portEl && portEl.value.trim() ? portEl.value.trim() : '8080');
      const ws      = wsEl && wsEl.checked;
      const sslVer  = sslVerEl && sslVerEl.checked;
      const connT   = connEl && connEl.value !== '' ? connEl.value : '60';
      const readT   = readEl && readEl.value !== '' ? readEl.value : '60';
      const sendT   = sendEl && sendEl.value !== '' ? sendEl.value : '60';
      const maxBody = maxBodyEl && maxBodyEl.value !== '' ? parseInt(maxBodyEl.value, 10) : 10;
      const maxBodyStr = maxBody === 0 ? '0' : (isNaN(maxBody) ? '10m' : maxBody + 'm');

      const lines = [];
      lines.push('location / {');
      lines.push('    client_max_body_size ' + maxBodyStr + ';');
      lines.push('    proxy_pass ' + scheme + '://' + host + ':' + port + ';');
      lines.push('    proxy_set_header Host $host;');
      lines.push('    proxy_set_header X-Real-IP $remote_addr;');
      lines.push('    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;');
      lines.push('    proxy_set_header X-Forwarded-Proto $scheme;');
      lines.push('    proxy_set_header X-Forwarded-Host $host;');
      lines.push('    proxy_set_header X-Forwarded-Port $server_port;');
      lines.push('');
      lines.push('    # Timeouts y buffer de proxy');
      lines.push('    proxy_connect_timeout ' + connT + 's;');
      lines.push('    proxy_send_timeout ' + sendT + 's;');
      lines.push('    proxy_read_timeout ' + readT + 's;');
      lines.push('    proxy_buffering on;');

      if (scheme === 'https' || sslVer) {
        lines.push('');
        lines.push('    # Verificación SSL hacia el backend');
        lines.push('    proxy_ssl_server_name on;');
        lines.push('    proxy_ssl_verify ' + (sslVer ? 'on' : 'off') + ';');
      }

      if (ws) {
        lines.push('');
        lines.push('    # Soporte para WebSockets');
        lines.push('    proxy_http_version 1.1;');
        lines.push('    proxy_set_header Upgrade $http_upgrade;');
        lines.push('    proxy_set_header Connection "upgrade";');
      }

      const rawHeaders = textarea.value.trim();
      if (rawHeaders !== '') {
        lines.push('');
        lines.push('    # Cabeceras personalizadas (lista blanca)');
        rawHeaders.split(/\r\n|\r|\n/).forEach((line) => {
          const idx = line.indexOf(':');
          if (idx > 0) {
            const hName = line.slice(0, idx).trim();
            const hVal  = line.slice(idx + 1).trim();
            const meta  = findHeaderMeta(hName);
            if (meta && hVal) {
              if (meta.directive === 'proxy_set_header') {
                const formatted = hVal.startsWith('$') ? hVal : '"' + hVal.replace(/"/g, '\\"') + '"';
                lines.push('    proxy_set_header ' + meta.name + ' ' + formatted + ';');
              } else {
                lines.push('    add_header ' + meta.name + ' "' + hVal.replace(/"/g, '\\"') + '" always;');
              }
            }
          }
        });
      }

      lines.push('}');
      previewEl.textContent = lines.join('\n');
    }

    // Inicializar filas existentes desde el valor actual de #custom_headers
    const initialRaw = textarea.value.trim();
    if (initialRaw !== '') {
      initialRaw.split(/\r\n|\r|\n/).forEach((line) => {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) return;
        const colonIdx = trimmed.indexOf(':');
        if (colonIdx > 0) {
          const name = trimmed.slice(0, colonIdx).trim();
          const val  = trimmed.slice(colonIdx + 1).trim();
          addHeaderRow(name, val);
        }
      });
    } else {
      syncTextareaAndPreview();
    }

    if (btnAdd) {
      btnAdd.addEventListener('click', function () {
        addHeaderRow();
      });
    }

    // Escuchar cambios en los demás controles del formulario para actualizar la vista previa en vivo
    ['scheme', 'forward_host', 'forward_port', 'websocket_enabled', 'proxy_ssl_verify', 'proxy_connect_timeout', 'proxy_read_timeout', 'proxy_send_timeout', 'client_max_body_size'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) {
        el.addEventListener('input', renderNginxPreview);
        el.addEventListener('change', renderNginxPreview);
      }
    });
    if ($ && typeof $.fn.select2 === 'function') {
      $('#scheme').on('change', renderNginxPreview);
    }
  }

  /**
   * Comprueba si el dominio de un certificado cubre el dominio del Proxy Host
   */
  function certCoversDomain(certDomain, hostDomain) {
    const c = (certDomain || '').trim().toLowerCase();
    const h = (hostDomain || '').trim().toLowerCase();
    if (!c || !h) return false;
    if (c === h || 'www.' + c === h || c === 'www.' + h) return true;
    if (c.startsWith('*.')) {
      const base = c.slice(2);
      if (h === base || h.endsWith('.' + base)) return true;
    }
    return false;
  }

  /**
   * Controla los paneles reactivos del Paso 4 (SSL) y filtra certificados por dominio
   */
  function initSslStepLogic($) {
    const select    = document.getElementById('ssl_certificate');
    const domainInp = document.getElementById('domain');
    if (!select) return;

    const infoPanel  = document.getElementById('sslInfoPanel');
    const newPanel   = document.getElementById('sslNewPanel');
    const forcePanel = document.getElementById('sslForcePanel');
    const hstsPanel  = document.getElementById('sslHstsPanel');
    const tos        = document.getElementById('ssl_tos');
    const statusText = { valid: 'Válido', expiring_soon: 'Por Caducar', expired: 'Caducado' };

    // Guardar catálogo original de certificados existentes para filtrar dinámicamente según el dominio
    const allCertOptions = Array.from(select.options)
      .filter((opt) => opt.value !== 'none' && opt.value !== 'new')
      .map((opt) => ({
        value: opt.value,
        text: opt.textContent.trim(),
        domain: opt.dataset.domain || '',
        issuer: opt.dataset.issuer || "Let's Encrypt",
        expires: opt.dataset.expires || '—',
        status: opt.dataset.status || 'valid'
      }));

    function getPrimaryDomain() {
      if (!domainInp) return '';
      const raw = domainInp.value.trim();
      if (!raw) return '';
      return raw.split(/[\s,]+/)[0].toLowerCase();
    }

    function filterCertificatesByDomain() {
      const primaryDomain = getPrimaryDomain();
      const currentVal = select.value;

      // Eliminar opciones dinámicas actuales (manteniendo 'none' y 'new')
      Array.from(select.options).forEach((opt) => {
        if (opt.value !== 'none' && opt.value !== 'new') {
          opt.remove();
        }
      });

      // Añadir únicamente los certificados que cubran el dominio introducido
      let matchingSelected = false;
      allCertOptions.forEach((cert) => {
        if (primaryDomain && certCoversDomain(cert.domain, primaryDomain)) {
          const opt = document.createElement('option');
          opt.value = cert.value;
          opt.textContent = cert.text;
          opt.dataset.domain = cert.domain;
          opt.dataset.issuer = cert.issuer;
          opt.dataset.expires = cert.expires;
          opt.dataset.status = cert.status;
          if (cert.value === currentVal) {
            opt.selected = true;
            matchingSelected = true;
          }
          select.appendChild(opt);
        }
      });

      if (currentVal !== 'none' && currentVal !== 'new' && !matchingSelected) {
        select.value = 'none';
      }

      if ($ && typeof $.fn.select2 === 'function' && $(select).data('select2')) {
        $(select).trigger('change.select2');
      }
      refreshSslPanels();
    }

    function togglePanel(el, show) {
      if (!el) return;
      el.classList.toggle('d-none', !show);
      el.style.display = show ? '' : 'none';
    }

    function refreshSslPanels() {
      const value = select.value;
      const isNew = value === 'new';
      const isExisting = value !== 'none' && value !== 'new';

      togglePanel(newPanel, isNew);
      togglePanel(infoPanel, isExisting);
      togglePanel(forcePanel, isNew || isExisting);
      togglePanel(hstsPanel, isNew || isExisting);
      if (tos) tos.required = isNew;

      if (isExisting) {
        const opt = select.options[select.selectedIndex];
        const domEl = document.getElementById('sslInfoDomain');
        const issEl = document.getElementById('sslInfoIssuer');
        const expEl = document.getElementById('sslInfoExpires');
        const badge = document.getElementById('sslInfoStatus');

        if (opt) {
          if (domEl) domEl.textContent = opt.dataset.domain || '';
          if (issEl) issEl.textContent = opt.dataset.issuer || '';
          if (expEl) expEl.textContent = opt.dataset.expires || '';

          if (badge) {
            const st = opt.dataset.status || 'valid';
            badge.textContent = statusText[st] || st;
            badge.className = 'badge border ' + (st === 'valid'
              ? 'bg-success-subtle text-success border-success'
              : (st === 'expiring_soon' ? 'bg-warning-subtle text-warning border-warning' : 'bg-danger-subtle text-danger border-danger'));
          }
        }
      }
    }

    if (typeof $.fn.select2 === 'function') {
      $(select).select2({ width: '100%', minimumResultsForSearch: Infinity });
      $(select).on('change', refreshSslPanels);
    }
    select.addEventListener('change', refreshSslPanels);

    if (domainInp) {
      domainInp.addEventListener('input', filterCertificatesByDomain);
      domainInp.addEventListener('change', filterCertificatesByDomain);
    }

    filterCertificatesByDomain();
  }

  // Arrancar cuando el DOM esté listo o inmediatamente si ya cargó
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProxyWizard);
  } else {
    initProxyWizard();
  }

})();

/**
 * Sincroniza el campo oculto CSRF del formulario con la cookie CSRF vigente del navegador
 */
window.syncCsrfTokenToForm = function (form) {
  if (!form) return;
  const match = document.cookie.match(/(?:^|;\s*)csrf_cookie_name=([^;]+)/);
  if (!match) return;
  const cookieToken = decodeURIComponent(match[1]);
  let csrfInput = form.querySelector('input[name="csrf_test_name"]');
  if (!csrfInput) {
    csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_test_name';
    form.appendChild(csrfInput);
  }
  csrfInput.value = cookieToken;
};

/**
 * Confirmación SweetAlert para eliminar un Proxy Host desde la vista de edición
 */
function confirmDeleteHost() {
  const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
  const domain = document.getElementById('domain') ? document.getElementById('domain').value : '';

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
      const form = document.getElementById('deleteHostForm');
      if (form) {
        if (typeof window.syncCsrfTokenToForm === 'function') {
          window.syncCsrfTokenToForm(form);
        }
        form.submit();
      }
    }
  });
}

/**
 * Envío para renovación de certificado desde el Paso 4
 */
function submitRenewHostSsl() {
  const form = document.getElementById('renewHostSslForm');
  if (!form) return;

  const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
  Swal.fire({
    title: '¿Renovar Certificado SSL?',
    text: 'Se verificará la renovación con Let\'s Encrypt y se recargará Nginx.',
    icon: 'question',
    showCancelButton: true,
    reverseButtons: true,
    buttonsStyling: false,
    background: isDark ? '#0b1114' : '#f8f9fa',
    color: isDark ? '#ffffff' : '#0b1114',
    customClass: {
      cancelButton: 'btn btn-dark btn-sm me-2',
      confirmButton: 'btn btn-primary btn-sm'
    },
    confirmButtonText: 'Renovar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: 'Renovando Certificado...',
        text: 'Por favor espera unos segundos.',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      if (typeof window.syncCsrfTokenToForm === 'function') {
        window.syncCsrfTokenToForm(form);
      }
      form.submit();
    }
  });
}
