<?php
/**
 * Vista Unificada de Administración del Sistema.
 *
 * Pestañas (partials modulares):
 *  1. Seguridad & 2FA (_security.php)
 *  2. IPs Bloqueadas (_bans.php)
 *  3. Servidor SMTP (_smtp.php)
 *  4. Copias de Seguridad (_backups.php)
 *  5. Auditoría (_audit.php)
 *  6. Logs Nginx (_logs.php)
 *
 * Modales (_modals.php)
 *
 * Variables disponibles:
 *  - $currentTab (string: 'security', 'bans', 'smtp', 'backups', 'audit', 'logs')
 *  - $defaultEmail (string)
 *  - $twoFactorEnabled (bool)
 *  - $pendingSecret (string)
 *  - $totpUri (string)
 *  - $totpQrCode (string)
 *  - $bannedIps (array)
 *  - $smtpSettings (array)
 *  - $backups (array)
 *  - $retentionCount (int)
 *  - $totalBackups (int)
 *  - $totalSizeHuman (string)
 *  - $auditLogs (array)
 *  - $proxies (array)
 *  - $nginxLog (array)
 *  - $selectedType (string)
 *  - $selectedHost (string)
 */

helper('audit');
$actionBadgeClass = static fn(string $action): string => audit_badge_class($action);
$totpQrCode = $totpQrCode ?? '';
?>

<!-- Cabecera Principal de Administración -->
<div class="page-header">
  <h4 class="page-header-title">Administración</h4>
  <div class="page-header-actions">
    <a href="<?= base_url('dashboard') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
      <i class="ti ti-arrow-left"></i> Volver
    </a>
  </div>
</div>

<!-- =========================================================================
     NAVEGACIÓN POR PESTAÑAS (TABS)
     ========================================================================= -->
<ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'security' ? 'active' : '' ?>"
            id="tab-security-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-security"
            type="button"
            role="tab"
            aria-controls="tab-security"
            aria-selected="<?= $currentTab === 'security' ? 'true' : 'false' ?>">
      <i class="ti ti-shield-lock me-1"></i> Seguridad &amp; 2FA
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'bans' ? 'active' : '' ?>"
            id="tab-bans-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-bans"
            type="button"
            role="tab"
            aria-controls="tab-bans"
            aria-selected="<?= $currentTab === 'bans' ? 'true' : 'false' ?>">
      <i class="ti ti-shield-lock me-1"></i> Bloqueos e IPs
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'smtp' ? 'active' : '' ?>"
            id="tab-smtp-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-smtp"
            type="button"
            role="tab"
            aria-controls="tab-smtp"
            aria-selected="<?= $currentTab === 'smtp' ? 'true' : 'false' ?>">
      <i class="ti ti-mail-fast me-1"></i> Servidor SMTP
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'backups' ? 'active' : '' ?>"
            id="tab-backups-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-backups"
            type="button"
            role="tab"
            aria-controls="tab-backups"
            aria-selected="<?= $currentTab === 'backups' ? 'true' : 'false' ?>">
      <i class="ti ti-archive me-1"></i> Backups
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'audit' ? 'active' : '' ?>"
            id="tab-audit-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-audit"
            type="button"
            role="tab"
            aria-controls="tab-audit"
            aria-selected="<?= $currentTab === 'audit' ? 'true' : 'false' ?>">
      <i class="ti ti-history me-1"></i> Auditoría
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'logs' ? 'active' : '' ?>"
            id="tab-logs-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-logs"
            type="button"
            role="tab"
            aria-controls="tab-logs"
            aria-selected="<?= $currentTab === 'logs' ? 'true' : 'false' ?>">
      <i class="ti ti-file-text me-1"></i> Logs Nginx
    </button>
  </li>
</ul>

<!-- =========================================================================
     CONTENIDO DE LAS PESTAÑAS (VISTAS PARCIALES)
     ========================================================================= -->
<div class="tab-content" id="adminTabsContent">

  <!-- 1. PESTAÑA: SEGURIDAD & 2FA -->
  <div class="tab-pane fade <?= $currentTab === 'security' ? 'show active' : '' ?>" id="tab-security" role="tabpanel" aria-labelledby="tab-security-btn">
    <?= view('admin/_security', get_defined_vars()) ?>
  </div>

  <!-- 2. PESTAÑA: IPs BLOQUEADAS (FAIL2BAN INTERNO) -->
  <div class="tab-pane fade <?= $currentTab === 'bans' ? 'show active' : '' ?>" id="tab-bans" role="tabpanel" aria-labelledby="tab-bans-btn">
    <?= view('admin/_bans', get_defined_vars()) ?>
  </div>

  <!-- 3. PESTAÑA: SERVIDOR SMTP -->
  <div class="tab-pane fade <?= $currentTab === 'smtp' ? 'show active' : '' ?>" id="tab-smtp" role="tabpanel" aria-labelledby="tab-smtp-btn">
    <?= view('admin/_smtp', get_defined_vars()) ?>
  </div>

  <!-- 4. PESTAÑA: COPIAS DE SEGURIDAD (BACKUPS) -->
  <div class="tab-pane fade <?= $currentTab === 'backups' ? 'show active' : '' ?>" id="tab-backups" role="tabpanel" aria-labelledby="tab-backups-btn">
    <?= view('admin/_backups', get_defined_vars()) ?>
  </div>

  <!-- 5. PESTAÑA: AUDITORÍA (AUDIT LOGS) -->
  <div class="tab-pane fade <?= $currentTab === 'audit' ? 'show active' : '' ?>" id="tab-audit" role="tabpanel" aria-labelledby="tab-audit-btn">
    <?= view('admin/_audit', get_defined_vars()) ?>
  </div>

  <!-- 6. PESTAÑA: LOGS NGINX -->
  <div class="tab-pane fade <?= $currentTab === 'logs' ? 'show active' : '' ?>" id="tab-logs" role="tabpanel" aria-labelledby="tab-logs-btn">
    <?= view('admin/_logs', get_defined_vars()) ?>
  </div>

</div>

<!-- Modales de Administración -->
<?= view('admin/_modals', get_defined_vars()) ?>

<!-- Scripts interactivos de Administración (Tabs, Copiado, Auditoría y Test SMTP) -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // 0. Menús de acciones dentro de tablas: posición fija para que no queden recortados ni bajo otros bloques
    if (window.bootstrap && window.bootstrap.Dropdown) {
      document.querySelectorAll('#tab-backups [data-bs-toggle="dropdown"]').forEach(el => {
        new window.bootstrap.Dropdown(el, { popperConfig: (cfg) => Object.assign({}, cfg, { strategy: 'fixed' }) });
      });
    }

    // 1. Manejo de Pestañas y Sincronización con URL (?tab=...)
    const tabButtons = document.querySelectorAll('#adminTabs button[data-bs-toggle="tab"]');
    tabButtons.forEach(btn => {
      btn.addEventListener('shown.bs.tab', function (e) {
        const targetId = e.target.getAttribute('data-bs-target').replace('#tab-', '');
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('tab', targetId);
        window.history.replaceState({}, '', newUrl.toString());
      });
    });

    // 2. Copiar clave secreta TOTP al portapapeles
    const btnCopy = document.getElementById('btnCopySecret');
    const secretField = document.getElementById('totpSecretField');
    if (btnCopy && secretField) {
      btnCopy.addEventListener('click', function () {
        const icon = btnCopy.querySelector('i');
        navigator.clipboard.writeText(secretField.value).then(() => {
          if (icon) icon.className = 'ti ti-check text-success fs-5';
          btnCopy.classList.add('border-success');
          setTimeout(() => {
            if (icon) icon.className = 'ti ti-copy fs-5';
            btnCopy.classList.remove('border-success');
          }, 2000);
        }).catch(() => {
          secretField.select();
          document.execCommand('copy');
        });
      });
    }

    // 3. Indicador de carga en envío de prueba SMTP
    const testSmtpForm = document.getElementById('testSmtpForm');
    const btnTestSmtp  = document.getElementById('btnTestSmtp');
    if (testSmtpForm && btnTestSmtp) {
      testSmtpForm.addEventListener('submit', function () {
        btnTestSmtp.disabled = true;
        btnTestSmtp.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Enviando...';
      });
    }

    // 4. Filtrado en tiempo real de Auditoría (Buscador + Select de Acción)
    const auditSearch = document.getElementById('auditSearchInput');
    const auditFilter = document.getElementById('auditActionFilter');
    const auditRows   = document.querySelectorAll('.audit-item');
    const auditNoRow  = document.getElementById('auditNoResultsRow');
    const auditNoMob  = document.getElementById('auditMobileNoResults');

    function applyAuditFilter() {
      const q = (auditSearch ? auditSearch.value.toLowerCase().trim() : '');
      const action = (auditFilter ? auditFilter.value.toLowerCase().trim() : 'all');
      let visible = 0;

      auditRows.forEach(row => {
        const rowAction = (row.getAttribute('data-action') || '').toLowerCase();
        const text = row.textContent.toLowerCase();
        const matchesAction = (action === 'all' || rowAction === action);
        const matchesQuery  = (q === '' || text.includes(q));

        if (matchesAction && matchesQuery) {
          row.classList.remove('d-none');
          visible++;
        } else {
          row.classList.add('d-none');
        }
      });

      if (auditNoRow) auditNoRow.classList.toggle('d-none', visible > 0 || auditRows.length === 0);
      if (auditNoMob) auditNoMob.classList.toggle('d-none', visible > 0 || auditRows.length === 0);
    }

    if (auditSearch) auditSearch.addEventListener('input', applyAuditFilter);
    if (auditFilter) {
      if (window.jQuery && typeof window.jQuery.fn.select2 === 'function') {
        window.jQuery(auditFilter).on('change', applyAuditFilter);
      } else {
        auditFilter.addEventListener('change', applyAuditFilter);
      }
    }

    // 5. Filtrado en tiempo real de IPs y Rangos CIDR Bloqueados
    const banSearch = document.getElementById('banSearchInput');
    const banRows   = document.querySelectorAll('.ban-item');
    const banNoRow  = document.getElementById('banNoResultsRow');

    if (banSearch && banRows.length > 0) {
      banSearch.addEventListener('input', function () {
        const q = banSearch.value.toLowerCase().trim();
        let visible = 0;
        banRows.forEach(row => {
          const matches = (q === '' || row.textContent.toLowerCase().includes(q));
          row.classList.toggle('d-none', !matches);
          if (matches) visible++;
        });
        if (banNoRow) banNoRow.classList.toggle('d-none', visible > 0);
      });
    }

    // 6. Inicialización de select2 para selector de países GeoIP
    const geoipSelect = document.getElementById('geoip_countries');
    if (geoipSelect && window.jQuery && typeof window.jQuery.fn.select2 === 'function') {
      window.jQuery(geoipSelect).select2({
        width: '100%',
        placeholder: 'Busca y selecciona uno o varios países...',
        allowClear: true
      });
    }
  });
</script>
