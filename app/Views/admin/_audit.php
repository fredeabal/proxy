<?php
/**
 * Pestaña "Auditoría (Audit Logs)" de Administración:
 *  - Registro de eventos del sistema con buscador y filtro en tiempo real por acción
 *  - Tabla responsive y tarjetas móviles con insignias de acción
 *
 * Variables: $auditLogs, $actionBadgeClass
 */
helper('audit');
$actionBadgeClass = $actionBadgeClass ?? static fn(string $action): string => audit_badge_class($action);
?>
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

