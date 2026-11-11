<?php
/**
 * Pestaña "Bloqueos e IPs" de Administración:
 *  - Métricas rápidas de bloqueos (Total, Auto-Ban, Temporales, Permanentes)
 *  - Bloqueo Geográfico por País (GeoIP2 / MaxMind GeoLite2)
 *  - Formulario de bloqueo manual (IP o rango CIDR)
 *  - Lista negra activa con tabla responsive y tarjetas móviles
 *
 * Variables: $bannedIps, $geoipEnabled, $geoipMode, $geoipCountries, $geoipDbExists, $geoipDbPath
 */
use App\Helpers\GeoIpHelper;

$bannedList = $bannedIps ?? [];
$totalBanned = count($bannedList);
$autoBannedCount = count(array_filter($bannedList, static fn($b) => ($b['created_by'] ?? '') === 'system'));
$permBannedCount = count(array_filter($bannedList, static fn($b) => (int) ($b['is_permanent'] ?? 0) === 1));
$tempBannedCount = max(0, $totalBanned - $permBannedCount);

$allCountries = GeoIpHelper::getCountryList();
$selectedCountries = $geoipCountries ?? [];
$isGeoipActive = !empty($geoipEnabled);
$currentGeoipMode = $geoipMode ?? 'blocklist';
?>
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

<!-- Bloqueo Geográfico por País (GeoIP2) -->
<div class="card border shadow-none mb-4">
  <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
      <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
        <i class="ti ti-map-pin fs-5"></i>
      </div>
      <h5 class="card-title fw-semibold mb-0">Bloqueo por País (GeoIP2)</h5>
    </div>
    <div class="d-flex align-items-center gap-2">
      <form method="post" action="<?= base_url('admin/bans/geoip-download') ?>" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit"
                class="btn btn-primary btn-sm px-2 py-1 d-inline-flex align-items-center justify-content-center"
                title="Actualizar base de datos MaxMind GeoLite2"
                aria-label="Actualizar base de datos MaxMind GeoLite2">
          <i class="ti ti-refresh fs-5"></i>
        </button>
      </form>
    </div>
  </div>
  <div class="card-body p-3 p-md-4">
    <form method="post" action="<?= base_url('admin/bans/geoip') ?>">
      <?= csrf_field() ?>
      <div class="row g-3 align-items-end">
        <!-- Estado (Select2) -->
        <div class="col-12 col-md-3">
          <label for="geoip_enabled" class="form-label fw-semibold">Estado</label>
          <div class="input-group flex-nowrap">
            <span class="input-group-text bg-light"><i class="ti ti-power"></i></span>
            <div class="flex-grow-1 select2-unified-wrapper">
              <select id="geoip_enabled" name="geoip_enabled" class="select2 form-control">
                <option value="0" <?= !$isGeoipActive ? 'selected' : '' ?>>Desactivado</option>
                <option value="1" <?= $isGeoipActive ? 'selected' : '' ?>>Activado</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Modo de Operación: Lista Negra vs Lista Blanca -->
        <div class="col-12 col-md-3">
          <label for="geoip_mode" class="form-label fw-semibold">Modo de Operación</label>
          <div class="input-group flex-nowrap">
            <span class="input-group-text bg-light"><i class="ti ti-adjustments-horizontal"></i></span>
            <div class="flex-grow-1 select2-unified-wrapper">
              <select id="geoip_mode" name="geoip_mode" class="select2 form-control">
                <option value="blocklist" <?= $currentGeoipMode === 'blocklist' ? 'selected' : '' ?>>
                  Lista Negra
                </option>
                <option value="allowlist" <?= $currentGeoipMode === 'allowlist' ? 'selected' : '' ?>>
                  Lista Blanca
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Selector Múltiple de Países + Botón Guardar Icono Primary -->
        <div class="col-12 col-md-6">
          <label for="geoip_countries" class="form-label fw-semibold">
            Países
            <span class="text-muted fw-normal small">(<?= count($selectedCountries) ?> seleccionados)</span>
          </label>
          <div class="d-flex align-items-stretch gap-2">
            <div class="input-group flex-grow-1 flex-nowrap">
              <span class="input-group-text bg-light"><i class="ti ti-world"></i></span>
              <div class="flex-grow-1 select2-unified-wrapper">
                <select id="geoip_countries"
                        name="geoip_countries[]"
                        class="form-control select2"
                        multiple="multiple"
                        data-placeholder="Seleccionar países...">
                  <?php foreach ($allCountries as $code => $country): ?>
                    <option value="<?= esc($code) ?>" <?= in_array($code, $selectedCountries, true) ? 'selected' : '' ?>>
                      <?= esc($country['name']) ?> (<?= esc($code) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button type="submit"
                    class="btn btn-primary px-3 d-inline-flex align-items-center justify-content-center flex-shrink-0"
                    title="Guardar Filtro GeoIP"
                    aria-label="Guardar Filtro GeoIP">
              <i class="ti ti-device-floppy fs-5"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="form-text small text-muted mt-2">
        Inspección por IP con MaxMind GeoLite2. Bloquea o permite países enteros según el modo configurado.
      </div>
    </form>
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
          <div class="input-group flex-nowrap">
            <span class="input-group-text bg-light"><i class="ti ti-clock"></i></span>
            <div class="flex-grow-1 select2-unified-wrapper">
              <select id="ban_duration" name="duration" class="select2 form-control">
                <option value="15">15 minutos</option>
                <option value="30">30 minutos</option>
                <option value="60">1 hora</option>
                <option value="1440">24 horas</option>
                <option value="10080">7 días</option>
                <option value="0" selected>Permanente</option>
              </select>
            </div>
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
        Acepta IPs (<code>203.0.113.50</code>) o rangos CIDR (<code>203.0.113.0/24</code>).
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
    <h5 class="card-title fw-semibold mb-0">Lista Negra Activa (IPs Bloqueadas)</h5>
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
              <th class="text-center">Origen</th>
              <th class="text-center">Intentos</th>
              <th class="text-center">Expiración</th>
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
                <td class="text-center">
                  <span class="small text-muted"><?= ($ban['created_by'] ?? '') === 'system' ? 'Auto-Ban' : 'Manual' ?></span>
                </td>
                <td class="text-center font-monospace small text-muted">
                  <?= (int) ($ban['failed_attempts'] ?? 0) ?>
                </td>
                <td class="text-center">
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

