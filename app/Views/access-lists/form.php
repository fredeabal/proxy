<?php
/**
 * Formulario de creación/edición de Access List.
 * Variables: $accessList (array), $isEdit (bool)
 */
$isEdit    = !empty($isEdit);
$actionUrl = $isEdit ? base_url('access-lists/update/' . (int) $accessList['id']) : base_url('access-lists/create');
$clients   = old('clients', $accessList['clients'] ?? []);
$users     = old('auth_users', $accessList['users'] ?? []);
?>
<div class="page-header">
  <h4 class="page-header-title"><?= $isEdit ? 'Editar Access List' : 'Nueva Access List' ?></h4>
  <div class="page-header-actions">
    <a href="<?= base_url('access-lists') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
      <i class="ti ti-arrow-left"></i> Volver
    </a>
  </div>
</div>

<div class="card border shadow-none">
  <div class="card-body p-3 p-md-4">
    <form method="post" action="<?= esc($actionUrl) ?>">
      <?= csrf_field() ?>

      <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
          <label for="name" class="form-label fw-semibold">Nombre de la Lista <span class="text-danger">*</span></label>
          <input type="text" id="name" name="name" class="form-control" required
                 placeholder="ej. Oficina + Admin Auth"
                 value="<?= esc(old('name', $accessList['name'] ?? '')) ?>">
        </div>

        <div class="col-12 col-md-6 d-flex align-items-end gap-4">
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="satisfy_any" name="satisfy_any" value="1"
                   <?= old('satisfy_any', $accessList['satisfy_any'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="satisfy_any">Satisfy Any (Permitir si cumple IP O Auth)</label>
          </div>
        </div>
      </div>

      <hr class="my-4 opacity-10">

      <!-- Reglas IP / CIDR -->
      <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h5 class="card-title fw-semibold mb-0"><i class="ti ti-network me-1"></i> Reglas por IP / CIDR</h5>
          <button type="button" class="btn btn-outline-primary btn-sm" id="addClientRuleBtn">
            <i class="ti ti-plus"></i> Añadir Regla IP
          </button>
        </div>

        <div id="clientRulesContainer" class="d-flex flex-column gap-2">
          <?php foreach ($clients as $idx => $c): ?>
            <div class="row g-2 align-items-center client-rule-row">
              <div class="col-4 col-md-3">
                <select name="clients[<?= (int) $idx ?>][directive]" class="form-select">
                  <option value="allow" <?= ($c['directive'] ?? 'allow') === 'allow' ? 'selected' : '' ?>>allow</option>
                  <option value="deny" <?= ($c['directive'] ?? '') === 'deny' ? 'selected' : '' ?>>deny</option>
                </select>
              </div>
              <div class="col-6 col-md-7">
                <input type="text" name="clients[<?= (int) $idx ?>][address]" class="form-control font-monospace"
                       placeholder="ej. 192.168.1.0/24 o 10.0.0.5 o all"
                       value="<?= esc($c['address'] ?? '') ?>">
              </div>
              <div class="col-2">
                <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn" title="Quitar">
                  <i class="ti ti-x"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="form-text text-muted mt-1">
          Si añades reglas <code>allow</code> sin un <code>deny all</code> explícito, se añadirá <code>deny all;</code> automáticamente al final.
        </div>
      </div>

      <hr class="my-4 opacity-10">

      <!-- Usuarios Basic Auth -->
      <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h5 class="card-title fw-semibold mb-0"><i class="ti ti-key me-1"></i> Autenticación Básica (.htpasswd)</h5>
          <button type="button" class="btn btn-outline-primary btn-sm" id="addAuthUserBtn">
            <i class="ti ti-plus"></i> Añadir Usuario
          </button>
        </div>

        <div id="authUsersContainer" class="d-flex flex-column gap-2">
          <?php foreach ($users as $idx => $u): ?>
            <div class="row g-2 align-items-center auth-user-row">
              <div class="col-5 col-md-5">
                <input type="text" name="auth_users[<?= (int) $idx ?>][username]" class="form-control"
                       placeholder="Usuario"
                       value="<?= esc($u['username'] ?? '') ?>">
              </div>
              <div class="col-5 col-md-5">
                <input type="password" name="auth_users[<?= (int) $idx ?>][password]" class="form-control"
                       placeholder="<?= $isEdit ? 'Dejar vacío para mantener contraseña' : 'Contraseña' ?>">
              </div>
              <div class="col-2">
                <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn" title="Quitar">
                  <i class="ti ti-x"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-center gap-2 mt-4 pt-2">
        <a href="<?= base_url('access-lists') ?>" class="btn btn-dark btn-sm">
          <i class="ti ti-x me-1"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="ti ti-device-floppy me-1"></i> <?= $isEdit ? 'Guardar' : 'Crear' ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  let clientIdx = <?= count($clients) ?>;
  let userIdx = <?= count($users) ?>;

  document.getElementById('addClientRuleBtn')?.addEventListener('click', function () {
    const html = `
      <div class="row g-2 align-items-center client-rule-row">
        <div class="col-4 col-md-3">
          <select name="clients[${clientIdx}][directive]" class="form-select">
            <option value="allow">allow</option>
            <option value="deny">deny</option>
          </select>
        </div>
        <div class="col-6 col-md-7">
          <input type="text" name="clients[${clientIdx}][address]" class="form-control font-monospace" placeholder="ej. 192.168.1.0/24 o all">
        </div>
        <div class="col-2">
          <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn"><i class="ti ti-x"></i></button>
        </div>
      </div>`;
    document.getElementById('clientRulesContainer').insertAdjacentHTML('beforeend', html);
    clientIdx++;
  });

  document.getElementById('addAuthUserBtn')?.addEventListener('click', function () {
    const html = `
      <div class="row g-2 align-items-center auth-user-row">
        <div class="col-5 col-md-5">
          <input type="text" name="auth_users[${userIdx}][username]" class="form-control" placeholder="Usuario">
        </div>
        <div class="col-5 col-md-5">
          <input type="password" name="auth_users[${userIdx}][password]" class="form-control" placeholder="Contraseña">
        </div>
        <div class="col-2">
          <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn"><i class="ti ti-x"></i></button>
        </div>
      </div>`;
    document.getElementById('authUsersContainer').insertAdjacentHTML('beforeend', html);
    userIdx++;
  });

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.remove-row-btn');
    if (btn) {
      btn.closest('.row')?.remove();
    }
  });
});
</script>
