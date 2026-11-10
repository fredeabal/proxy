<?php
/**
 * Vista Unificada de Administración del Sistema.
 *
 * Pestañas:
 *  1. Seguridad & 2FA (TOTP RFC 6238 + Ajustes Generales / SSL email)
 *  2. Servidor SMTP (Configuración de correo saliente + Prueba inmediata)
 *  3. Copias de Seguridad (Métricas, retención y listado sin columna de contenido)
 *  4. Auditoría (Registro de eventos con buscador y filtro por acción)
 *
 * Variables disponibles:
 *  - $currentTab (string: 'security', 'smtp', 'backups', 'audit')
 *  - $defaultEmail (string)
 *  - $twoFactorEnabled (bool)
 *  - $pendingSecret (string)
 *  - $totpUri (string)
 *  - $smtpSettings (array)
 *  - $backups (array)
 *  - $retentionCount (int)
 *  - $totalBackups (int)
 *  - $totalSizeHuman (string)
 *  - $auditLogs (array)
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
      <i class="ti ti-ban me-1"></i> IPs Bloqueadas
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
     CONTENIDO DE LAS PESTAÑAS
     ========================================================================= -->
<div class="tab-content" id="adminTabsContent">

  <!-- =======================================================================
       1. PESTAÑA: SEGURIDAD & 2FA
       ======================================================================= -->
  <div class="tab-pane fade <?= $currentTab === 'security' ? 'show active' : '' ?>" id="tab-security" role="tabpanel" aria-labelledby="tab-security-btn">
    
    <!-- Autenticación en Dos Pasos (2FA TOTP RFC 6238) -->
    <div class="card border shadow-none mb-4">
      <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
            <i class="ti ti-shield-lock fs-5"></i>
          </div>
          <h5 class="card-title fw-semibold mb-0">Autenticación en Dos Pasos (2FA)</h5>
        </div>
        <?php if (!empty($twoFactorEnabled)): ?>
          <span class="badge border bg-success-subtle text-success border-success">2FA Activo</span>
        <?php else: ?>
          <span class="badge border bg-secondary-subtle text-secondary border-secondary">2FA Inactivo</span>
        <?php endif; ?>
      </div>
      <div class="card-body p-4">
        <?php if (!empty($twoFactorEnabled)): ?>
          <div class="mb-3">
            <h6 class="fw-semibold mb-1">Tu cuenta de administrador está protegida con TOTP (RFC 6238)</h6>
            <p class="small text-muted mb-0">
              Se solicitará el código de 6 dígitos de tu app en cada inicio de sesión.
            </p>
          </div>
          <form method="post" action="<?= base_url('admin/2fa/disable') ?>" class="d-flex justify-content-center pt-2">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm px-4">Desactivar</button>
          </form>
        <?php else: ?>
          <form method="post" action="<?= base_url('admin/2fa/enable') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="totp_secret" value="<?= esc($pendingSecret ?? '') ?>">
            
            <div class="row g-3">
              <!-- Clave Secreta Base32 con botón para ver QR en modal -->
              <div class="col-12 col-md-6">
                <label for="totpSecretField" class="form-label fw-semibold">Clave Secreta Base32 (Google Authenticator / Authy)</label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="ti ti-key"></i></span>
                  <input type="text"
                         id="totpSecretField"
                         class="form-control font-monospace"
                         value="<?= esc($pendingSecret ?? '') ?>"
                         readonly>
                  <?php if (!empty($totpQrCode)): ?>
                    <button type="button"
                            class="btn btn-outline-secondary px-3"
                            data-bs-toggle="modal"
                            data-bs-target="#totpQrModal"
                            title="Generar código QR">
                      <i class="ti ti-qrcode fs-5"></i>
                    </button>
                  <?php endif; ?>
                </div>
                <div class="form-text small text-muted">
                  Escanea el QR o introduce la clave en tu app.
                </div>
              </div>

              <!-- Código de Verificación -->
              <div class="col-12 col-md-6">
                <label for="totp_code" class="form-label fw-semibold">Código de Verificación (6 dígitos) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="ti ti-lock"></i></span>
                  <input type="text"
                         id="totp_code"
                         name="totp_code"
                         class="form-control font-monospace"
                         inputmode="numeric"
                         maxlength="6"
                         placeholder="Ej. 123456"
                         required>
                </div>
                <div class="form-text small text-muted">
                  Introduce el código de 6 dígitos de tu app.
                </div>
              </div>

              <div class="col-12 d-flex justify-content-center align-items-center gap-2 pt-2">
                <a href="<?= base_url('admin?tab=security') ?>" class="btn btn-dark btn-sm">Cancelar</a>
                <button type="submit" class="btn btn-primary btn-sm px-3">Activar</button>
              </div>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Ajustes Generales (Let's Encrypt / ACME) -->
    <div class="card border shadow-none mb-0">
      <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
        <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
          <i class="ti ti-adjustments fs-5"></i>
        </div>
        <h5 class="card-title fw-semibold mb-0">Ajustes Generales</h5>
      </div>
      <div class="card-body p-4">
        <form method="post" action="<?= base_url('admin/general') ?>">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-12">
              <label for="default_ssl_email" class="form-label fw-semibold">Correo por Defecto para Let's Encrypt</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-mail"></i></span>
                <input type="email"
                       id="default_ssl_email"
                       name="default_ssl_email"
                       class="form-control <?= session('errors.default_ssl_email') ? 'is-invalid' : '' ?>"
                       placeholder="admin@empresa.com"
                       value="<?= esc(old('default_ssl_email', $defaultEmail)) ?>">
              </div>
              <div class="form-text small text-muted">
                Dirección empleada de forma predeterminada para avisos de caducidad en la emisión de certificados SSL.
              </div>
            </div>

            <div class="col-12 d-flex justify-content-center align-items-center gap-2 pt-2">
              <a href="<?= base_url('admin?tab=security') ?>" class="btn btn-dark btn-sm">Cancelar</a>
              <button type="submit" class="btn btn-primary btn-sm px-3">Guardar</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- =======================================================================
       1.B PESTAÑA: IPs BLOQUEADAS (FAIL2BAN INTERNO + CLOUDFLARE REAL-IP)
       ======================================================================= -->
  <?php
  $bannedList = $bannedIps ?? [];
  $totalBanned = count($bannedList);
  $autoBannedCount = count(array_filter($bannedList, static fn($b) => ($b['created_by'] ?? '') === 'system'));
  $permBannedCount = count(array_filter($bannedList, static fn($b) => (int) ($b['is_permanent'] ?? 0) === 1));
  $tempBannedCount = max(0, $totalBanned - $permBannedCount);
  ?>
  <div class="tab-pane fade <?= $currentTab === 'bans' ? 'show active' : '' ?>" id="tab-bans" role="tabpanel" aria-labelledby="tab-bans-btn">

    <!-- Tarjetas Resumen de Métricas Rápidas (Mobile-First: metrics-summary-row) -->
    <div class="row g-2 g-md-3 mb-3 mb-md-4 metrics-summary-row">
      <div class="col-6 col-md-3">
        <div class="card border shadow-none mb-0 h-100">
          <div class="card-body p-3 d-flex align-items-center gap-3">
            <div class="round-40 d-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle flex-shrink-0">
              <i class="ti ti-ban fs-5"></i>
            </div>
            <div>
              <span class="text-muted fs-2 text-uppercase fw-semibold d-block">Bloqueadas</span>
              <h4 class="fw-bold mb-0"><?= $totalBanned ?></h4>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border shadow-none mb-0 h-100">
          <div class="card-body p-3 d-flex align-items-center gap-3">
            <div class="round-40 d-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle flex-shrink-0">
              <i class="ti ti-shield-lock fs-5"></i>
            </div>
            <div>
              <span class="text-muted fs-2 text-uppercase fw-semibold d-block">Auto-Ban</span>
              <h4 class="fw-bold mb-0"><?= $autoBannedCount ?></h4>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border shadow-none mb-0 h-100">
          <div class="card-body p-3 d-flex align-items-center gap-3">
            <div class="round-40 d-flex align-items-center justify-content-center bg-info-subtle text-info rounded-circle flex-shrink-0">
              <i class="ti ti-clock fs-5"></i>
            </div>
            <div>
              <span class="text-muted fs-2 text-uppercase fw-semibold d-block">Temporales</span>
              <h4 class="fw-bold mb-0"><?= $tempBannedCount ?></h4>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border shadow-none mb-0 h-100">
          <div class="card-body p-3 d-flex align-items-center gap-3">
            <div class="round-40 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
              <i class="ti ti-lock fs-5"></i>
            </div>
            <div>
              <span class="text-muted fs-2 text-uppercase fw-semibold d-block">Permanentes</span>
              <h4 class="fw-bold mb-0"><?= $permBannedCount ?></h4>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Formulario para Bloquear una IP o Rango CIDR Manualmente -->
    <div class="card border shadow-none mb-4">
      <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
        <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
          <i class="ti ti-ban fs-5"></i>
        </div>
        <h5 class="card-title fw-semibold mb-0">Bloquear IP o Rango CIDR</h5>
      </div>
      <div class="card-body p-3 p-md-4">
        <form method="post" action="<?= base_url('admin/bans/add') ?>">
          <?= csrf_field() ?>
          <div class="row g-3 align-items-end">
            <!-- IP o Rango CIDR -->
            <div class="col-12 col-md-4">
              <label for="ban_ip_address" class="form-label fw-semibold">IP o Rango CIDR <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-world"></i></span>
                <input type="text"
                       id="ban_ip_address"
                       name="ip_address"
                       class="form-control font-monospace"
                       placeholder="Ej. 203.0.113.0/24"
                       value="<?= esc(old('ip_address', '')) ?>"
                       required>
              </div>
            </div>

            <!-- Duración -->
            <div class="col-12 col-md-3">
              <label for="ban_duration" class="form-label fw-semibold">Duración</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-clock"></i></span>
                <select id="ban_duration" name="duration" class="form-select">
                  <option value="15">15 minutos</option>
                  <option value="30">30 minutos</option>
                  <option value="60">1 hora</option>
                  <option value="1440">24 horas</option>
                  <option value="10080">7 días</option>
                  <option value="0" selected>Permanente</option>
                </select>
              </div>
            </div>

            <!-- Motivo + Botón Icono Primary -->
            <div class="col-12 col-md-5">
              <label for="ban_reason" class="form-label fw-semibold">Motivo</label>
              <div class="d-flex align-items-stretch gap-2">
                <div class="input-group">
                  <span class="input-group-text bg-light"><i class="ti ti-file-text"></i></span>
                  <input type="text"
                         id="ban_reason"
                         name="reason"
                         class="form-control"
                         placeholder="Ej. Bot malicioso"
                         value="<?= esc(old('reason', '')) ?>">
                </div>
                <button type="submit"
                        class="btn btn-primary px-3 d-inline-flex align-items-center justify-content-center flex-shrink-0"
                        title="Bloquear IP o Rango CIDR"
                        aria-label="Bloquear IP o Rango CIDR">
                  <i class="ti ti-ban fs-5"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="form-text small text-muted mt-2">
            Acepta IPs (<code>203.0.113.50</code>) o rangos CIDR (<code>203.0.113.0/24</code>). Auto-bloqueo de 30 min tras 5 fallos.
          </div>
        </form>
      </div>
    </div>

    <!-- Tabla de IPs y Rangos Bloqueados Activos -->
    <div class="card border shadow-none mb-0">
      <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
        <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
          <i class="ti ti-shield-lock fs-5"></i>
        </div>
        <h5 class="card-title fw-semibold mb-0">Lista Negra Activa</h5>
      </div>

      <?php if (!empty($bannedList)): ?>
        <!-- Buscador rápido en tiempo real -->
        <div class="card-body p-3 border-bottom">
          <div class="input-group">
            <span class="input-group-text bg-transparent"><i class="ti ti-search"></i></span>
            <input type="search"
                   id="banSearchInput"
                   class="form-control"
                   placeholder="Buscar por IP, rango o motivo...">
          </div>
        </div>
      <?php endif; ?>

      <div class="card-body p-0">
        <?php if (empty($bannedList)): ?>
          <div class="text-center py-5 px-3">
            <div class="round-48 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-2">
              <i class="ti ti-shield-check fs-5"></i>
            </div>
            <p class="text-muted mb-0">Sin IPs ni rangos bloqueados actualmente.</p>
          </div>
        <?php else: ?>
          <!-- Vista Escritorio (>= 768px) -->
          <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0" id="bannedIpsTable">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">IP / Rango CIDR</th>
                  <th>Motivo</th>
                  <th>Origen</th>
                  <th class="text-center">Intentos</th>
                  <th>Expiración</th>
                  <th class="text-end pe-4">Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($bannedList as $ban): ?>
                  <?php $isCidr = str_contains((string) $ban['ip_address'], '/'); ?>
                  <tr class="ban-item">
                    <td class="ps-4">
                      <div class="d-flex align-items-center gap-2">
                        <div class="round-32 d-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle flex-shrink-0">
                          <i class="ti <?= $isCidr ? 'ti-world' : 'ti-ban' ?> fs-5"></i>
                        </div>
                        <span class="fw-semibold font-monospace text-body text-break"><?= esc($ban['ip_address']) ?></span>
                      </div>
                    </td>
                    <td>
                      <span class="small text-body text-break"><?= esc($ban['reason'] ?? 'Bloqueo de seguridad') ?></span>
                    </td>
                    <td>
                      <span class="small text-muted"><?= ($ban['created_by'] ?? '') === 'system' ? 'Auto-Ban' : 'Manual' ?></span>
                    </td>
                    <td class="text-center font-monospace small text-muted">
                      <?= (int) ($ban['failed_attempts'] ?? 0) ?>
                    </td>
                    <td>
                      <?php if ((int) ($ban['is_permanent'] ?? 0) === 1): ?>
                        <span class="small fw-semibold text-danger">Permanente</span>
                      <?php else: ?>
                        <span class="small text-muted font-monospace"><?= esc((string) ($ban['expires_at'] ?? '')) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                      <form method="post" action="<?= base_url('admin/bans/unban/' . (int) $ban['id']) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit"
                                class="btn btn-primary btn-sm px-2 py-1 d-inline-flex align-items-center justify-content-center"
                                title="Desbloquear <?= esc($ban['ip_address']) ?>"
                                aria-label="Desbloquear <?= esc($ban['ip_address']) ?>">
                          <i class="ti ti-lock-open fs-5"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <tr id="banNoResultsRow" class="d-none">
                  <td colspan="6" class="text-center py-4 text-muted">
                    No se encontraron bloqueos que coincidan con la búsqueda.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tarjetas Móviles (< 768px) -->
          <div class="d-block d-md-none p-3" id="banMobileCards">
            <?php foreach ($bannedList as $ban): ?>
              <div class="card border shadow-none mb-2 p-3 mobile-card-item ban-item">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                  <span class="fw-semibold font-monospace text-body text-break"><?= esc($ban['ip_address']) ?></span>
                  <span class="small fw-semibold <?= (int) ($ban['is_permanent'] ?? 0) === 1 ? 'text-danger' : 'text-warning' ?>">
                    <?= (int) ($ban['is_permanent'] ?? 0) === 1 ? 'Permanente' : 'Temporal' ?>
                  </span>
                </div>
                <div class="small text-body mb-2 text-break"><?= esc($ban['reason'] ?? 'Bloqueo de seguridad') ?></div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                  <span class="small text-muted">
                    <?= ($ban['created_by'] ?? '') === 'system' ? 'Auto-Ban (' . (int) ($ban['failed_attempts'] ?? 0) . ' intentos)' : 'Manual' ?>
                  </span>
                  <form method="post" action="<?= base_url('admin/bans/unban/' . (int) $ban['id']) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="btn btn-primary btn-sm btn-touch-target d-inline-flex align-items-center justify-content-center"
                            title="Desbloquear <?= esc($ban['ip_address']) ?>"
                            aria-label="Desbloquear <?= esc($ban['ip_address']) ?>">
                      <i class="ti ti-lock-open fs-5"></i>
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- =======================================================================
       2. PESTAÑA: SERVIDOR SMTP
       ======================================================================= -->
  <div class="tab-pane fade <?= $currentTab === 'smtp' ? 'show active' : '' ?>" id="tab-smtp" role="tabpanel" aria-labelledby="tab-smtp-btn">
    
    <!-- Configuración del Servidor SMTP -->
    <div class="card border shadow-none mb-4">
      <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
        <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
          <i class="ti ti-mail-fast fs-5"></i>
        </div>
        <h5 class="card-title fw-semibold mb-0">Servidor de Correo (SMTP)</h5>
      </div>
      <div class="card-body p-4">
        <form method="post" action="<?= base_url('admin/smtp') ?>">
          <?= csrf_field() ?>
          <div class="row g-4">
            <!-- Servidor Host -->
            <div class="col-12 col-md-8">
              <label for="smtp_host" class="form-label fw-semibold">Servidor SMTP (Host)</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-server"></i></span>
                <input type="text"
                       id="smtp_host"
                       name="smtp_host"
                       class="form-control font-monospace"
                       placeholder="smtp.empresa.com"
                       value="<?= esc(old('smtp_host', $smtpSettings['smtp_host'] ?? '')) ?>">
              </div>
            </div>

            <!-- Puerto -->
            <div class="col-12 col-md-4">
              <label for="smtp_port" class="form-label fw-semibold">Puerto</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-plug"></i></span>
                <input type="number"
                       id="smtp_port"
                       name="smtp_port"
                       class="form-control font-monospace"
                       placeholder="587"
                       min="1"
                       max="65535"
                       value="<?= esc(old('smtp_port', $smtpSettings['smtp_port'] ?? '587')) ?>">
              </div>
            </div>

            <!-- Usuario -->
            <div class="col-12 col-md-4">
              <label for="smtp_user" class="form-label fw-semibold">Usuario SMTP</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-user"></i></span>
                <input type="text"
                       id="smtp_user"
                       name="smtp_user"
                       class="form-control"
                       placeholder="usuario@empresa.com"
                       value="<?= esc(old('smtp_user', $smtpSettings['smtp_user'] ?? '')) ?>">
              </div>
            </div>

            <!-- Contraseña -->
            <div class="col-12 col-md-4">
              <label for="smtp_pass" class="form-label fw-semibold">Contraseña SMTP</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-lock"></i></span>
                <input type="password"
                       id="smtp_pass"
                       name="smtp_pass"
                       class="form-control"
                       placeholder="<?= !empty($smtpSettings['smtp_pass']) ? '••••••••' : 'Contraseña' ?>">
              </div>
              <?php if (!empty($smtpSettings['smtp_pass'])): ?>
                <div class="form-text small text-muted">Dejar vacío para mantener la contraseña actual.</div>
              <?php endif; ?>
            </div>

            <!-- Cifrado -->
            <div class="col-12 col-md-4">
              <label for="smtp_crypto" class="form-label fw-semibold">Cifrado</label>
              <select id="smtp_crypto" name="smtp_crypto" class="form-select select2">
                <?php $crypto = old('smtp_crypto', $smtpSettings['smtp_crypto'] ?? 'tls'); ?>
                <option value="tls" <?= $crypto === 'tls' ? 'selected' : '' ?>>STARTTLS (tls - Puerto recomendado 587)</option>
                <option value="ssl" <?= $crypto === 'ssl' ? 'selected' : '' ?>>SSL / TLS implícito (ssl - Puerto recomendado 465)</option>
                <option value="none" <?= $crypto === 'none' ? 'selected' : '' ?>>Sin cifrado (none - Puerto 25)</option>
              </select>
            </div>

            <!-- Email Remitente -->
            <div class="col-12 col-md-6">
              <label for="smtp_from_email" class="form-label fw-semibold">Correo Remitente (From)</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-at"></i></span>
                <input type="email"
                       id="smtp_from_email"
                       name="smtp_from_email"
                       class="form-control"
                       placeholder="alertas@empresa.com"
                       value="<?= esc(old('smtp_from_email', $smtpSettings['smtp_from_email'] ?? '')) ?>">
              </div>
            </div>

            <!-- Nombre Remitente -->
            <div class="col-12 col-md-6">
              <label for="smtp_from_name" class="form-label fw-semibold">Nombre del Remitente</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-id"></i></span>
                <input type="text"
                       id="smtp_from_name"
                       name="smtp_from_name"
                       class="form-control"
                       placeholder="Proxy Manager"
                       value="<?= esc(old('smtp_from_name', $smtpSettings['smtp_from_name'] ?? 'Proxy Manager')) ?>">
              </div>
            </div>

            <div class="col-12 d-flex justify-content-center gap-2 pt-2">
              <a href="<?= base_url('admin') ?>" class="btn btn-dark btn-sm">Cancelar</a>
              <button type="submit" class="btn btn-primary btn-sm px-4">Guardar</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Probar Conexión SMTP -->
    <div class="card border shadow-none mb-0">
      <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
        <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
          <i class="ti ti-send fs-5"></i>
        </div>
        <h5 class="card-title fw-semibold mb-0">Probar Conexión SMTP</h5>
      </div>
      <div class="card-body p-4">
        <form method="post" action="<?= base_url('admin/smtp/test') ?>" id="testSmtpForm">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-12">
              <label for="test_email" class="form-label fw-semibold">Enviar Correo de Prueba a</label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-mail-forward"></i></span>
                <input type="email"
                       id="test_email"
                       name="test_email"
                       class="form-control"
                       placeholder="destinatario@empresa.com"
                       required>
              </div>
            </div>

            <div class="col-12 d-flex justify-content-center gap-2 pt-2">
              <button type="submit" id="btnTestSmtp" class="btn btn-primary btn-sm px-3 d-inline-flex align-items-center gap-1">Enviar
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- =======================================================================
       3. PESTAÑA: COPIAS DE SEGURIDAD (BACKUPS)
       ======================================================================= -->
  <div class="tab-pane fade <?= $currentTab === 'backups' ? 'show active' : '' ?>" id="tab-backups" role="tabpanel" aria-labelledby="tab-backups-btn">
    
    <!-- Política de Retención -->
    <div class="card border shadow-none mb-4">
      <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
        <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
          <i class="ti ti-history fs-5"></i>
        </div>
        <h5 class="card-title fw-semibold mb-0">Política de Retención</h5>
      </div>
      <div class="card-body p-4">
        <form method="post" action="<?= base_url('admin/backups/retention') ?>">
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-12">
              <label for="backup_retention_count" class="form-label fw-semibold">Retención de Copias de Seguridad Locales <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light"><i class="ti ti-history"></i></span>
                <input type="number"
                       id="backup_retention_count"
                       name="backup_retention_count"
                       class="form-control font-monospace <?= session('errors.backup_retention_count') ? 'is-invalid' : '' ?>"
                       placeholder="Ej. 10"
                       min="1"
                       max="100"
                       value="<?= esc(old('backup_retention_count', $retentionCount)) ?>">
              </div>
              <div class="form-text small text-muted">
                Máximo de copias a conservar (1–100). Las más antiguas se eliminan automáticamente.
              </div>
            </div>

            <div class="col-12 d-flex justify-content-center align-items-center gap-2 pt-2">
              <a href="<?= base_url('admin?tab=backups') ?>" class="btn btn-dark btn-sm">Cancelar</a>
              <button type="submit" class="btn btn-primary btn-sm px-3">Guardar</button>
              <button type="submit"
                      formaction="<?= base_url('admin/backups/create') ?>"
                      formnovalidate
                      class="btn btn-primary btn-sm px-3 d-inline-flex align-items-center gap-1">
                <i class="ti ti-plus"></i> Crear Backup
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Listado de Copias de Seguridad (SIN columna contenido) -->
    <div class="card border shadow-none mb-4" id="backupsManagerCard">
      <!-- Buscador en tiempo real integrado -->
      <div class="card-body p-3 border-bottom">
        <div class="row g-2 align-items-center">
          <div class="col-12">
            <div class="input-group">
              <span class="input-group-text bg-transparent"><i class="ti ti-search"></i></span>
              <input type="search"
                     id="backupSearchInput"
                     class="form-control"
                     placeholder="Buscar por nombre de archivo o fecha...">
            </div>
          </div>
        </div>
      </div>

      <!-- Tabla de Escritorio (>= 768px) -->
      <div class="table-responsive d-none d-md-block">
        <table class="table table-hover align-middle mb-0" id="backupsTable">
          <thead class="table-light">
            <tr>
              <th class="ps-4">Archivo de Backup</th>
              <th>Tamaño</th>
              <th>Fecha de Creación</th>
              <th class="text-end pe-4">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($backups)): ?>
              <?php foreach ($backups as $b): ?>
                <tr class="backup-item">
                  <td class="ps-4">
                    <div class="d-flex align-items-center gap-2">
                      <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
                        <i class="ti ti-file-zip fs-5"></i>
                      </div>
                      <span class="fw-semibold font-monospace"><?= esc($b['filename']) ?></span>
                    </div>
                  </td>
                  <td>
                    <span class="badge border badge-action badge-keep-case bg-secondary-subtle text-secondary border-secondary font-monospace">
                      <?= esc($b['size_formatted'] ?? ($b['size_human'] ?? '—')) ?>
                    </span>
                  </td>
                  <td class="font-monospace text-muted small">
                    <?= esc($b['created_at']) ?>
                  </td>
                  <td class="text-end pe-4">
                    <div class="dropdown">
                      <a href="javascript:void(0)"
                         class="text-muted p-2 d-inline-flex align-items-center justify-content-center text-decoration-none"
                         data-bs-toggle="dropdown"
                         aria-expanded="false"
                         title="Acciones">
                        <i class="ti ti-dots-vertical fs-5"></i>
                      </a>
                      <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                        <li>
                          <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('admin/backups/download/' . rawurlencode($b['filename'])) ?>">
                            <i class="ti ti-download fs-4"></i> Descargar
                          </a>
                        </li>
                        <li>
                          <button type="button" class="dropdown-item d-flex align-items-center gap-2 btn-restore-backup" data-filename="<?= esc($b['filename']) ?>">
                            <i class="ti ti-rotate-clockwise fs-4"></i> Restaurar
                          </button>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                          <button type="button" class="dropdown-item text-danger d-flex align-items-center gap-2 btn-delete-backup" data-filename="<?= esc($b['filename']) ?>">
                            <i class="ti ti-trash fs-4"></i> Eliminar
                          </button>
                        </li>
                      </ul>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr id="backupNoResultsRow" class="d-none">
                <td colspan="4" class="text-center py-4 text-muted">
                  No se encontraron copias de seguridad que coincidan con la búsqueda.
                </td>
              </tr>
            <?php else: ?>
              <tr>
                <td colspan="4" class="text-center py-5">
                  <div class="round-48 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-2">
                    <i class="ti ti-archive fs-5"></i>
                  </div>
                  <p class="text-muted mb-0">No hay copias de seguridad generadas todavía.</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Tarjetas Móviles (< 768px) -->
      <div class="d-block d-md-none p-3" id="backupMobileCards">
        <?php if (!empty($backups)): ?>
          <?php foreach ($backups as $b): ?>
            <div class="card border shadow-none mb-2 p-3 mobile-card-item backup-item">
              <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <span class="fw-semibold font-monospace small text-truncate" style="max-width: 180px;">
                  <?= esc($b['filename']) ?>
                </span>
                <span class="badge border badge-action badge-keep-case bg-secondary-subtle text-secondary border-secondary font-monospace">
                  <?= esc($b['size_formatted'] ?? ($b['size_human'] ?? '—')) ?>
                </span>
              </div>
              <div class="small text-muted font-monospace mb-3">
                <i class="ti ti-calendar me-1"></i><?= esc($b['created_at']) ?>
              </div>
              <div class="d-flex justify-content-end pt-2 border-top">
                <div class="dropdown">
                      <a href="javascript:void(0)"
                         class="text-muted p-2 d-inline-flex align-items-center justify-content-center text-decoration-none"
                         data-bs-toggle="dropdown"
                         aria-expanded="false"
                         title="Acciones">
                        <i class="ti ti-dots-vertical fs-5"></i>
                      </a>
                      <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                        <li>
                          <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('admin/backups/download/' . rawurlencode($b['filename'])) ?>">
                            <i class="ti ti-download fs-4"></i> Descargar
                          </a>
                        </li>
                        <li>
                          <button type="button" class="dropdown-item d-flex align-items-center gap-2 btn-restore-backup" data-filename="<?= esc($b['filename']) ?>">
                            <i class="ti ti-rotate-clockwise fs-4"></i> Restaurar
                          </button>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                          <button type="button" class="dropdown-item text-danger d-flex align-items-center gap-2 btn-delete-backup" data-filename="<?= esc($b['filename']) ?>">
                            <i class="ti ti-trash fs-4"></i> Eliminar
                          </button>
                        </li>
                      </ul>
                    </div>
              </div>
            </div>
          <?php endforeach; ?>
          <div id="backupMobileNoResults" class="card border shadow-none p-4 text-center text-muted d-none">
            No se encontraron copias de seguridad que coincidan.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Formularios ocultos de acción de backups -->
    <form id="restoreBackupForm" method="post" action="<?= base_url('admin/backups/restore') ?>" class="d-none">
      <?= csrf_field() ?>
      <input type="hidden" name="filename" id="restoreBackupFilename" value="">
    </form>

    <form id="deleteBackupForm" method="post" action="<?= base_url('admin/backups/delete') ?>" class="d-none">
      <?= csrf_field() ?>
      <input type="hidden" name="filename" id="deleteBackupFilename" value="">
    </form>
  </div>

  <!-- =======================================================================
       4. PESTAÑA: AUDITORÍA (AUDIT LOGS)
       ======================================================================= -->
  <div class="tab-pane fade <?= $currentTab === 'audit' ? 'show active' : '' ?>" id="tab-audit" role="tabpanel" aria-labelledby="tab-audit-btn">
    
    <div class="card border shadow-none mb-0">
      <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
        <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle">
          <i class="ti ti-clipboard-list fs-5"></i>
        </div>
        <h5 class="card-title fw-semibold mb-0">Registro de Eventos y Auditoría</h5>
      </div>

      <!-- Buscador y filtro de acciones en tiempo real -->
      <div class="card-body p-3 border-bottom">
        <div class="row g-2 align-items-center">
          <div class="col-12 col-md-8">
            <div class="input-group">
              <span class="input-group-text bg-transparent"><i class="ti ti-search"></i></span>
              <input type="search"
                     id="auditSearchInput"
                     class="form-control"
                     placeholder="Buscar por acción, usuario, descripción o dirección IP...">
            </div>
          </div>

          <div class="col-12 col-md-4">
            <select id="auditActionFilter" class="select2 form-control" aria-label="Filtrar por tipo de acción">
              <option value="all">Todas las acciones</option>
              <?php
                $uniqueActions = array_values(array_unique(array_map('strtolower', array_column($auditLogs, 'action'))));
                sort($uniqueActions);
                foreach ($uniqueActions as $act):
              ?>
                <option value="<?= esc($act) ?>"><?= esc($act) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Tabla de Escritorio (>= 768px) -->
      <div class="table-responsive d-none d-md-block">
        <table class="table table-hover align-middle mb-0" id="auditLogsTable">
          <thead class="table-light">
            <tr>
              <th class="ps-4">Acción</th>
              <th>Usuario</th>
              <th>Descripción</th>
              <th class="text-end pe-4">Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($auditLogs)): ?>
              <?php foreach ($auditLogs as $log): ?>
                <?php
                  $actionName = strtolower(trim((string) ($log['action'] ?? 'unknown')));
                  $userName   = (string) ($log['user_name'] ?: ($log['user_email'] ?: 'Sistema'));
                  $badgeCls   = $actionBadgeClass($actionName);
                ?>
                <tr class="audit-item" data-action="<?= esc($actionName) ?>">
                  <td class="ps-4">
                    <span class="badge border badge-action <?= $badgeCls ?> font-monospace" title="<?= esc($actionName) ?>"><?= esc(audit_label($actionName)) ?></span>
                  </td>
                  <td class="fw-medium"><?= esc($userName) ?></td>
                  <td class="text-break"><?= esc($log['description'] ?? '—') ?></td>
                  <td class="text-end pe-4 text-muted small font-monospace"><?= esc($log['created_at'] ?? '—') ?></td>
                </tr>
              <?php endforeach; ?>
              <tr id="auditNoResultsRow" class="d-none">
                <td colspan="4" class="text-center py-4 text-muted">
                  No se encontraron registros de auditoría que coincidan con el filtro.
                </td>
              </tr>
            <?php else: ?>
              <tr>
                <td colspan="4" class="text-center py-5">
                  <div class="round-48 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-2">
                    <i class="ti ti-history fs-5"></i>
                  </div>
                  <p class="text-muted mb-0">No hay eventos registrados en la auditoría todavía.</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Tarjetas Móviles (< 768px) -->
      <div class="d-block d-md-none p-3" id="auditMobileCards">
        <?php if (!empty($auditLogs)): ?>
          <?php foreach ($auditLogs as $log): ?>
            <?php
              $actionName = strtolower(trim((string) ($log['action'] ?? 'unknown')));
              $userName   = (string) ($log['user_name'] ?: ($log['user_email'] ?: 'Sistema'));
              $badgeCls   = $actionBadgeClass($actionName);
            ?>
            <div class="card border shadow-none mb-2 p-3 mobile-card-item audit-item" data-action="<?= esc($actionName) ?>">
              <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <span class="badge border badge-action <?= $badgeCls ?> font-monospace" title="<?= esc($actionName) ?>"><?= esc(audit_label($actionName)) ?></span>
                <span class="text-muted small font-monospace"><?= esc($log['created_at'] ?? '') ?></span>
              </div>
              <div class="small text-body mb-2 text-break"><?= esc($log['description'] ?? '—') ?></div>
              <div class="small text-muted border-top pt-2">
                Usuario: <strong class="text-body"><?= esc($userName) ?></strong>
              </div>
            </div>
          <?php endforeach; ?>
          <div id="auditMobileNoResults" class="card border shadow-none p-4 text-center text-muted d-none">
            No se encontraron registros que coincidan con la búsqueda.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- =======================================================================
       5. PESTAÑA: LOGS NGINX
       ======================================================================= -->
  <div class="tab-pane fade <?= $currentTab === 'logs' ? 'show active' : '' ?>" id="tab-logs" role="tabpanel" aria-labelledby="tab-logs-btn">
    <?= view('admin/_logs', get_defined_vars()) ?>
  </div>

</div>

<!-- Modal para Ampliar Código QR 2FA -->
<?php if (!empty($totpQrCode)): ?>
<div class="modal fade" id="totpQrModal" tabindex="-1" aria-labelledby="totpQrModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border">
      <div class="modal-header border-bottom py-3">
        <h6 class="modal-title fw-semibold mb-0" id="totpQrModalLabel">Código QR 2FA</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="p-2 bg-white rounded-3 d-inline-block border shadow-sm mb-3">
          <img src="<?= $totpQrCode ?>" alt="Código QR 2FA" style="width: 200px; height: 200px; display: block;">
        </div>
        <p class="small text-muted mb-2">Escanea el código con tu app autenticadora.</p>
        <span class="font-monospace small bg-light-subtle p-1 px-2 rounded border"><?= esc($pendingSecret ?? '') ?></span>
      </div>
      <div class="modal-footer border-top py-2 justify-content-center">
        <button type="button" class="btn btn-dark btn-sm" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

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
  });
</script>
