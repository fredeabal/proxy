/**
 * Proxy Hosts - Gestión de listado, filtros, health check y acciones AJAX
 */
(function () {
  'use strict';

  const baseUrl = (window.location.origin || '') + (window.location.pathname.startsWith('/index.php') ? '/index.php' : '');

  // --- Health Check & Cache ---
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

    const checkUrl = (window.appBaseUrl || baseUrl).replace(/\/$/, '') + '/proxy-hosts/health-check/' + id;

    return fetch(checkUrl, {
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

    // Resaltar con punto verde/rojo antes de la IP (tanto para pools como para destino único)
    if (data.servers && data.servers.length > 0) {
      data.servers.forEach(srv => {
        const targetEls = document.querySelectorAll(
          '.target-item-' + id + '[data-host="' + srv.host + '"][data-port="' + srv.port + '"], .single-target-' + id
        );
        targetEls.forEach(el => {
          const dot = el.querySelector('.target-dot');
          el.classList.remove('text-danger', 'fw-bold');
          if (srv.is_online) {
            el.removeAttribute('title');
            if (dot) {
              dot.className = 'target-dot rounded-circle bg-success me-1 flex-shrink-0';
              dot.style.opacity = '1';
              dot.setAttribute('title', 'Servidor Operativo');
            }
          } else {
            el.setAttribute('title', 'Servidor KO (Sin respuesta)');
            if (dot) {
              dot.className = 'target-dot rounded-circle bg-danger me-1 flex-shrink-0';
              dot.style.opacity = '1';
              dot.setAttribute('title', 'Servidor KO (Sin respuesta)');
            }
          }
        });
      });
    }

    // Actualizar siempre cualquier elemento de destino único o pool si no se cubrió
    const allTargets = document.querySelectorAll('.single-target-' + id + ', .target-item-' + id);
    allTargets.forEach(el => {
      const dot = el.querySelector('.target-dot');
      if (!dot || (dot.classList.contains('bg-success') || dot.classList.contains('bg-danger'))) {
        return; // ya fue actualizado por la lista de servers
      }
      el.classList.remove('text-danger', 'fw-bold');
      if (data.is_online) {
        el.removeAttribute('title');
        if (dot) {
          dot.className = 'target-dot rounded-circle bg-success me-1 flex-shrink-0';
          dot.style.opacity = '1';
          dot.setAttribute('title', 'Servidor Operativo');
        }
      } else {
        el.setAttribute('title', 'Servidor KO (Sin respuesta)');
        if (dot) {
          dot.className = 'target-dot rounded-circle bg-danger me-1 flex-shrink-0';
          dot.style.opacity = '1';
          dot.setAttribute('title', 'Servidor KO (Sin respuesta)');
        }
      }
    });
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

  // --- Toggle Status (Activo / Inactivo) ---
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

    const toggleUrl = (window.appBaseUrl || baseUrl).replace(/\/$/, '') + '/proxy-hosts/toggle/' + id;

    fetch(toggleUrl, {
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
            title: 'Error de Configuración',
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

  // --- Confirmar Eliminación con SweetAlert2 ---
  function confirmDelete(id, domain) {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    if (typeof Swal === 'undefined') {
      if (confirm(`¿Estás seguro de que deseas eliminar la configuración para "${domain}"? Esta acción no se puede deshacer.`)) {
        const form = document.getElementById('deleteForm');
        form.action = (window.appBaseUrl || baseUrl).replace(/\/$/, '') + '/proxy-hosts/delete/' + id;
        form.submit();
      }
      return;
    }

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
        form.action = (window.appBaseUrl || baseUrl).replace(/\/$/, '') + '/proxy-hosts/delete/' + id;
        form.submit();
      }
    });
  }

  // Exponer globalmente para eventos onclick en HTML
  window.checkHostHealth = checkHostHealth;
  window.toggleProxyStatus = toggleProxyStatus;
  window.confirmDelete = confirmDelete;

  // --- Inicialización DOMContentLoaded ---
  document.addEventListener('DOMContentLoaded', function () {
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

    // Búsqueda y filtrado en tiempo real sin botón
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
      rows.forEach(function (row) {
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
})();

