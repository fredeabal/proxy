/**
 * Gestor de Proxy Inverso - Visor de Logs de Nginx y Filtrado de Auditoría (Fase 7)
 */

(function () {
  'use strict';

  function initLogsMonitor() {
    const logCard = document.getElementById('nginxLogCard');
    if (!logCard) return;

    const $ = window.jQuery;
    const endpoint    = logCard.getAttribute('data-endpoint') || '/logs/nginx';
    const typeSelect  = document.getElementById('nginxLogType');
    const hostSelect  = document.getElementById('nginxHostFilter');
    const logSearch   = document.getElementById('nginxLogSearch');
    const refreshBtn  = document.getElementById('btnRefreshNginxLog');
    const viewer      = document.getElementById('nginxLogViewer');
    const filenameEl  = document.getElementById('nginxLogFilename');
    const countEl     = document.getElementById('nginxLogLineCount');

    let currentLines = viewer ? viewer.textContent.split('\n').filter(Boolean) : [];
    if (currentLines.length === 1 && currentLines[0].startsWith('No se encontraron')) currentLines = [];

    // Inicializar Select2 si está disponible
    if ($ && $.fn && typeof $.fn.select2 === 'function') {
      $('#nginxLogType, #nginxHostFilter, #auditActionFilter').select2({
        width: '100%',
        minimumResultsForSearch: 6
      });
      $('#nginxLogType, #nginxHostFilter').on('change', fetchNginxLogs);
      $('#auditActionFilter').on('change', filterAuditLogs);
    }

    function lineClass(line) {
      const m = line.match(/"\s(\d{3})\s/);
      if (m) {
        const code = parseInt(m[1], 10);
        if (code >= 500) return 'log-line-error';
        if (code >= 400) return 'log-line-warn';
        if (code >= 300) return 'log-line-info';
        return '';
      }
      if (/\[(emerg|alert|crit|error)\]/i.test(line)) return 'log-line-error';
      if (/\[warn\]/i.test(line)) return 'log-line-warn';
      return '';
    }

    function renderLogLines() {
      if (!viewer) return;
      const query = (logSearch ? logSearch.value : '').toLowerCase().trim();
      const filtered = query === ''
        ? currentLines
        : currentLines.filter((line) => line.toLowerCase().includes(query));

      viewer.textContent = '';
      if (filtered.length === 0) {
        viewer.textContent = 'No se encontraron entradas en el log para el filtro seleccionado.';
      } else {
        const frag = document.createDocumentFragment();
        filtered.forEach((line) => {
          const span = document.createElement('span');
          span.className = 'log-line ' + lineClass(line);
          span.textContent = line;
          frag.appendChild(span);
        });
        viewer.appendChild(frag);
      }

      if (countEl) {
        countEl.textContent = filtered.length + ' líneas';
      }
    }

    renderLogLines();

    function fetchNginxLogs() {
      const type   = typeSelect ? typeSelect.value : 'access';
      const domain = hostSelect ? hostSelect.value : '';
      const url    = `${endpoint}?type=${encodeURIComponent(type)}&domain=${encodeURIComponent(domain)}`;

      fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then((res) => res.json())
        .then((data) => {
          if (data && data.success) {
            currentLines = Array.isArray(data.lines) ? data.lines : [];
            if (filenameEl && data.file) {
              filenameEl.textContent = data.file;
            }
            renderLogLines();
          }
        })
        .catch(() => {});
    }

    if (typeSelect) typeSelect.addEventListener('change', fetchNginxLogs);
    if (hostSelect) hostSelect.addEventListener('change', fetchNginxLogs);
    if (logSearch) logSearch.addEventListener('input', renderLogLines);
    if (refreshBtn) refreshBtn.addEventListener('click', fetchNginxLogs);

    // Filtrado en tiempo real de la tabla y tarjetas móviles de Auditoría
    const auditSearch = document.getElementById('auditSearchInput');
    const auditAction = document.getElementById('auditActionFilter');
    const auditItems  = document.querySelectorAll('.audit-item');
    const noResultsTr = document.getElementById('auditNoResultsRow');
    const noResultsMb = document.getElementById('auditMobileNoResults');

    function filterAuditLogs() {
      const query  = (auditSearch ? auditSearch.value : '').toLowerCase().trim();
      const action = auditAction ? auditAction.value : 'all';
      let visibleCount = 0;

      auditItems.forEach((item) => {
        const text = item.textContent.toLowerCase();
        const itemAction = item.getAttribute('data-action') || '';

        const matchText   = query === '' || text.includes(query);
        const matchAction = action === 'all' || itemAction === action;

        if (matchText && matchAction) {
          item.classList.remove('d-none');
          visibleCount++;
        } else {
          item.classList.add('d-none');
        }
      });

      if (noResultsTr) {
        noResultsTr.classList.toggle('d-none', visibleCount > 0 || auditItems.length === 0);
      }
      if (noResultsMb) {
        noResultsMb.classList.toggle('d-none', visibleCount > 0 || auditItems.length === 0);
      }
    }

    if (auditSearch) auditSearch.addEventListener('input', filterAuditLogs);
    if (auditAction) auditAction.addEventListener('change', filterAuditLogs);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLogsMonitor);
  } else {
    initLogsMonitor();
  }
})();

