<?php
/**
 * Pestaña "Logs Nginx" de Administración: visor de logs en tiempo real.
 * Variables: $nginxStatus, $proxies, $nginxLog, $selectedType, $selectedHost
 */
?>
<!-- Visor de logs -->
<div class="card border shadow-none mb-0" id="nginxLogCard" data-endpoint="<?= base_url('logs/nginx') ?>">
  <div class="card-body p-3 border-bottom">
    <div class="row g-2 align-items-center">
      <div class="col-12 col-md-4">
        <select id="nginxLogType" class="select2 form-control" aria-label="Tipo de log">
          <option value="access" <?= $selectedType === 'access' ? 'selected' : '' ?>>access.log</option>
          <option value="error" <?= $selectedType === 'error' ? 'selected' : '' ?>>error.log</option>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <select id="nginxHostFilter" class="select2 form-control" aria-label="Filtrar por dominio">
          <option value="">Todos los dominios</option>
          <?php foreach ($proxies as $p): ?>
            <option value="<?= esc($p['domain']) ?>" <?= $selectedHost === $p['domain'] ? 'selected' : '' ?>>
              <?= esc($p['domain']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <div class="input-group">
          <span class="input-group-text bg-transparent"><i class="ti ti-search"></i></span>
          <input type="search"
                 id="nginxLogSearch"
                 class="form-control"
                 placeholder="Buscar por IP, ruta, estado o texto...">
        </div>
      </div>

    </div>
  </div>

  <div class="card-body p-3">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
      <span class="small text-muted font-monospace text-truncate" id="nginxLogFilename"><?= esc($nginxLog['file'] ?? 'access.log') ?></span>
      <span class="small text-muted text-nowrap" id="nginxLogLineCount"><?= count($nginxLog['lines'] ?? []) ?> líneas</span>
    </div>

    <pre id="nginxLogViewer" class="nginx-terminal nginx-log-console font-monospace mb-0"><?php
      if (!empty($nginxLog['lines'])) {
          echo esc(implode("\n", $nginxLog['lines']));
      } else {
          echo 'No se encontraron entradas en el log para el filtro seleccionado.';
      }
    ?></pre>
  </div>
</div>
