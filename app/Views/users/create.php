<!-- =========================================================================
     CREAR USUARIO (Estilo DeCA / NetCrew)
     ========================================================================= -->
<div class="page-header">
  <h4 class="page-header-title">Crear Usuario</h4>
  <div class="page-header-actions">
    <a href="<?= base_url('users') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
      <i class="ti ti-arrow-left"></i> Volver
    </a>
  </div>
</div>

<div class="card border shadow-none mb-4">
  <div class="card-body p-3 p-md-4">
    <form action="<?= base_url('users/store') ?>" method="post" autocomplete="off">
      <?= csrf_field() ?>

      <!-- SECCIÓN 1: DATOS DE LA CUENTA -->
      <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
            <i class="ti ti-user fs-5"></i>
          </div>
          <h5 class="card-title fw-semibold mb-0">Datos de la Cuenta</h5>
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label for="name" class="form-label fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti ti-user"></i></span>
              <input type="text"
                     class="form-control <?= session('errors.name') ? 'is-invalid' : '' ?>"
                     id="name"
                     name="name"
                     placeholder="Ej. Juan Pérez"
                     value="<?= old('name') ?>"
                     required>
            </div>
            <?php if (session('errors.name')): ?>
              <div class="text-danger small mt-1"><?= esc(session('errors.name')) ?></div>
            <?php endif; ?>
          </div>

          <div class="col-12 col-md-6">
            <label for="email" class="form-label fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti ti-mail"></i></span>
              <input type="email"
                     class="form-control <?= session('errors.email') ? 'is-invalid' : '' ?>"
                     id="email"
                     name="email"
                     placeholder="usuario@empresa.com"
                     value="<?= old('email') ?>"
                     required>
            </div>
            <?php if (session('errors.email')): ?>
              <div class="text-danger small mt-1"><?= esc(session('errors.email')) ?></div>
            <?php endif; ?>
          </div>

          <div class="col-12">
            <label for="role" class="form-label fw-semibold">Rol <span class="text-danger">*</span></label>
            <div class="input-group flex-nowrap">
              <span class="input-group-text"><i class="ti ti-shield"></i></span>
              <div class="flex-grow-1 select2-unified-wrapper">
                <select class="select2 form-control <?= session('errors.role') ? 'is-invalid' : '' ?>" id="role" name="role" required>
                  <option value="user" <?= old('role', 'user') === 'user' ? 'selected' : '' ?>>Usuario</option>
                  <option value="admin" <?= old('role') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                </select>
              </div>
            </div>
            <?php if (session('errors.role')): ?>
              <div class="text-danger small mt-1"><?= esc(session('errors.role')) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- SECCIÓN 2: CONTRASEÑA DE ACCESO -->
      <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
            <i class="ti ti-lock fs-5"></i>
          </div>
          <h5 class="card-title fw-semibold mb-0">Contraseña de Acceso</h5>
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label for="password" class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti ti-lock"></i></span>
              <input type="password"
                     class="form-control <?= session('errors.password') ? 'is-invalid' : '' ?>"
                     id="password"
                     name="password"
                     placeholder="Mínimo 8 caracteres"
                     required>
              <button class="btn btn-outline-secondary btn-toggle-password" type="button" tabindex="-1">
                <i class="ti ti-eye"></i>
              </button>
            </div>
            <?php if (session('errors.password')): ?>
              <div class="text-danger small mt-1"><?= esc(session('errors.password')) ?></div>
            <?php endif; ?>
          </div>

          <div class="col-12 col-md-6">
            <label for="password_confirm" class="form-label fw-semibold">Confirmar Contraseña <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti ti-shield-lock"></i></span>
              <input type="password"
                     class="form-control <?= session('errors.password_confirm') ? 'is-invalid' : '' ?>"
                     id="password_confirm"
                     name="password_confirm"
                     placeholder="Repite la contraseña"
                     required>
              <button class="btn btn-outline-secondary btn-toggle-password" type="button" tabindex="-1">
                <i class="ti ti-eye"></i>
              </button>
            </div>
            <?php if (session('errors.password_confirm')): ?>
              <div class="text-danger small mt-1"><?= esc(session('errors.password_confirm')) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-12 d-flex justify-content-center align-items-center gap-2 mt-4 pt-2">
        <a href="<?= base_url('users') ?>" class="btn btn-dark btn-sm">Cancelar</a>
        <button type="submit" class="btn btn-primary btn-sm px-4">
          <i class="ti ti-device-floppy me-1"></i> Guardar
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.btn-toggle-password').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var input = this.closest('.input-group').querySelector('input');
      var icon = this.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'ti ti-eye-off';
      } else {
        input.type = 'password';
        icon.className = 'ti ti-eye';
      }
    });
  });
});
</script>
