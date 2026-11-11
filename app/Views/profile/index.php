<!-- =========================================================================
     MI PERFIL DE USUARIO
     ========================================================================= -->
<?php
  $isAdmin = ($user['role'] === 'admin');
  $initial = strtoupper(mb_substr($user['name'] ?? $user['email'], 0, 1));
  $hasCustomAvatar = ! empty($user['avatar']) && file_exists(FCPATH . 'uploads/profiles/' . $user['avatar']);
  $avatarSrc = current_user_avatar($user['avatar'] ?? null);
?>
<div class="page-header">
  <h4 class="page-header-title">Mi Perfil</h4>
  <div class="page-header-actions">
    <a href="<?= base_url('dashboard') ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
      <i class="ti ti-arrow-left"></i> Volver
    </a>
  </div>
</div>

<div class="card border shadow-none mb-4">
  <div class="card-body p-3 p-md-4">
    <!-- SECCIÓN: FOTO DE PERFIL / AVATAR -->
    <div class="mb-4 pb-3">
      <div class="d-flex align-items-center gap-2 mb-3">
        <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
          <i class="ti ti-photo fs-5"></i>
        </div>
        <h5 class="card-title fw-semibold mb-0">Foto de Perfil / Avatar</h5>
      </div>

      <form action="<?= base_url('profile/update-avatar') ?>" method="post" enctype="multipart/form-data" id="avatarForm">
        <?= csrf_field() ?>
        
        <div class="dropzone-modern-wrapper p-4 rounded-3 border border-2 border-dashed text-center position-relative">
          <input type="file" id="userAvatarInput" name="avatar" class="visually-hidden" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">

          <!-- Prompt inicial o sin avatar personalizado -->
          <div id="avatarDropzonePrompt" class="<?= $hasCustomAvatar ? 'd-none' : '' ?>">
            <div class="round-48 rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-2 shadow-sm">
              <i class="ti ti-cloud-upload fs-7"></i>
            </div>
            <h6 class="fw-semibold mb-1">Arrastra tu foto de perfil o haz clic para examinar</h6>
            <span class="text-muted small d-block mb-3">Formatos permitidos: PNG, JPG, GIF, WEBP, SVG (Máx. 3 MB)</span>
            <label for="userAvatarInput" class="btn btn-sm btn-outline-primary px-3 mb-0 cursor-pointer" title="Seleccionar Foto">
              <i class="ti ti-folder-open me-1"></i>Seleccionar Foto
            </label>
          </div>

          <!-- Vista previa con avatar cargado / seleccionado -->
          <div id="avatarDropzonePreview" class="<?= $hasCustomAvatar ? '' : 'd-none' ?>">
            <div class="d-flex flex-column align-items-center justify-content-center">
              <div class="mb-3 d-inline-flex align-items-center justify-content-center position-relative">
                <img id="avatarPreviewImg" src="<?= $avatarSrc ?>" alt="Avatar Usuario" class="rounded-circle border border-3 border-primary-subtle shadow-sm object-fit-cover" width="110" height="110">
              </div>
              <div class="d-flex flex-wrap gap-2 justify-content-center">
                <label for="userAvatarInput" class="btn btn-sm btn-outline-primary px-3 mb-0 cursor-pointer" title="Cambiar Foto">
                  <i class="ti ti-refresh me-1"></i>Cambiar Foto
                </label>
                <button type="submit" class="btn btn-sm btn-primary px-3" id="btnSubmitAvatar">
                  <i class="ti ti-cloud-upload me-1"></i> Guardar Avatar
                </button>
                <?php if ($hasCustomAvatar): ?>
                  <button type="button" id="btnDeleteAvatar" class="btn btn-sm btn-outline-danger px-3">
                    <i class="ti ti-trash me-1"></i> Restaurar por defecto
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>

    <form action="<?= base_url('profile/update') ?>" method="post" autocomplete="off">
      <?= csrf_field() ?>

      <!-- SECCIÓN 1: DATOS PERSONALES -->
      <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
            <i class="ti ti-user fs-5"></i>
          </div>
          <h5 class="card-title fw-semibold mb-0">Datos Personales y de Cuenta</h5>
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
                     value="<?= old('name', $user['name']) ?>"
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
                     value="<?= old('email', $user['email']) ?>"
                     required>
            </div>
            <?php if (session('errors.email')): ?>
              <div class="text-danger small mt-1"><?= esc(session('errors.email')) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- SECCIÓN 2: SEGURIDAD (CAMBIAR CONTRASEÑA) -->
      <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="round-32 d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle flex-shrink-0">
            <i class="ti ti-lock fs-5"></i>
          </div>
          <h5 class="card-title fw-semibold mb-0">Seguridad: Cambiar Contraseña</h5>
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label for="password" class="form-label fw-semibold">Nueva Contraseña</label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti ti-lock"></i></span>
              <input type="password"
                     class="form-control <?= session('errors.password') ? 'is-invalid' : '' ?>"
                     id="password"
                     name="password"
                     placeholder="Dejar en blanco para no cambiar">
              <button class="btn btn-outline-secondary btn-toggle-password" type="button" tabindex="-1">
                <i class="ti ti-eye"></i>
              </button>
            </div>
            <?php if (session('errors.password')): ?>
              <div class="text-danger small mt-1"><?= esc(session('errors.password')) ?></div>
            <?php endif; ?>
          </div>

          <div class="col-12 col-md-6">
            <label for="password_confirm" class="form-label fw-semibold">Confirmar Nueva Contraseña</label>
            <div class="input-group">
              <span class="input-group-text"><i class="ti ti-shield-lock"></i></span>
              <input type="password"
                     class="form-control <?= session('errors.password_confirm') ? 'is-invalid' : '' ?>"
                     id="password_confirm"
                     name="password_confirm"
                     placeholder="Repite la nueva contraseña">
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
        <a href="<?= base_url('dashboard') ?>" class="btn btn-dark btn-sm">Cancelar</a>
        <button type="submit" class="btn btn-primary btn-sm px-4">
          <i class="ti ti-device-floppy me-1"></i> Guardar
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const avatarInput = document.getElementById('userAvatarInput');
  const promptBox = document.getElementById('avatarDropzonePrompt');
  const previewBox = document.getElementById('avatarDropzonePreview');
  const previewImg = document.getElementById('avatarPreviewImg');
  const dropzone = document.querySelector('.dropzone-modern-wrapper');

  function handleFile(file) {
    if (file && file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = function(evt) {
        if (previewImg) {
          previewImg.src = evt.target.result;
        }
        if (promptBox) promptBox.classList.add('d-none');
        if (previewBox) previewBox.classList.remove('d-none');
      };
      reader.readAsDataURL(file);
    }
  }

  if (avatarInput) {
    avatarInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      handleFile(file);
    });
  }

  // Soporte Drag & Drop
  if (dropzone && avatarInput) {
    ['dragenter', 'dragover'].forEach(function(eventName) {
      dropzone.addEventListener(eventName, function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add('border-primary', 'bg-primary-subtle');
      }, false);
    });

    ['dragleave', 'drop'].forEach(function(eventName) {
      dropzone.addEventListener(eventName, function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('border-primary', 'bg-primary-subtle');
      }, false);
    });

    dropzone.addEventListener('drop', function(e) {
      const dt = e.dataTransfer;
      const files = dt.files;
      if (files && files.length > 0) {
        avatarInput.files = files;
        handleFile(files[0]);
      }
    }, false);
  }

  // Eliminación de avatar personalizado
  const btnDeleteAvatar = document.getElementById('btnDeleteAvatar');
  if (btnDeleteAvatar) {
    btnDeleteAvatar.addEventListener('click', function(e) {
      e.preventDefault();
      
      Swal.fire({
        title: '¿Eliminar avatar?',
        text: 'Volverás a usar el avatar inicial predeterminado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
          confirmButton: 'btn btn-danger btn-sm me-2',
          cancelButton: 'btn btn-outline-secondary btn-sm'
        },
        buttonsStyling: false
      }).then(function(result) {
        if (result.isConfirmed) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = '<?= base_url('profile/delete-avatar') ?>';
          
          const csrf = document.createElement('input');
          csrf.type = 'hidden';
          csrf.name = '<?= csrf_token() ?>';
          csrf.value = '<?= csrf_hash() ?>';
          
          form.appendChild(csrf);
          document.body.appendChild(form);
          form.submit();
        }
      });
    });
  }

  // Toggle visibilidad de contraseñas
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
