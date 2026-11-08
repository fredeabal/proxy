/**
 * Gestor de Proxy Inverso - Gestión de Backups y Restauración (Fase 8)
 *
 * Respeta la Norma de Diseño: Botón Cancelar (btn-dark btn-sm) siempre a la izquierda
 * mediante reverseButtons: true en todos los modales SweetAlert2.
 */

(function () {
  'use strict';

  function initBackupsManager() {
    const managerCard = document.getElementById('backupsManagerCard');
    if (!managerCard) return;

    const searchInput = document.getElementById('backupSearchInput');
    const items       = document.querySelectorAll('.backup-item');
    const noResultsTr = document.getElementById('backupNoResultsRow');
    const noResultsMb = document.getElementById('backupMobileNoResults');

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        let visibleCount = 0;

        items.forEach((item) => {
          const text = item.textContent.toLowerCase();
          if (query === '' || text.includes(query)) {
            item.classList.remove('d-none');
            visibleCount++;
          } else {
            item.classList.add('d-none');
          }
        });

        if (noResultsTr) {
          noResultsTr.classList.toggle('d-none', visibleCount > 0 || items.length === 0);
        }
        if (noResultsMb) {
          noResultsMb.classList.toggle('d-none', visibleCount > 0 || items.length === 0);
        }
      });
    }

    document.querySelectorAll('.btn-restore-backup').forEach((btn) => {
      btn.addEventListener('click', function () {
        const filename = this.getAttribute('data-filename');
        if (filename) {
          confirmRestoreBackup(filename);
        }
      });
    });

    document.querySelectorAll('.btn-delete-backup').forEach((btn) => {
      btn.addEventListener('click', function () {
        const filename = this.getAttribute('data-filename');
        if (filename) {
          confirmDeleteBackup(filename);
        }
      });
    });
  }

    function confirmRestoreBackup(filename) {
      const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

      Swal.fire({
        title: '¿Restaurar Copia de Seguridad?',
        text: `Se validará la firma SHA-256 e integridad SQLite de "${filename}" y se restaurarán los Proxy Hosts, certificados y ajustes.`,
        icon: 'question',
        showCancelButton: true,
        reverseButtons: true,
        buttonsStyling: false,
        background: isDark ? '#0b1114' : '#f8f9fa',
        color: isDark ? '#ffffff' : '#0b1114',
        customClass: {
          cancelButton: 'btn btn-dark btn-sm me-2',
          confirmButton: 'btn btn-primary btn-sm'
        },
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Restaurar'
      }).then((result) => {
        if (result.isConfirmed) {
          const form = document.getElementById('restoreBackupForm');
          const input = document.getElementById('restoreBackupFilename');
          if (form && input) {
            input.value = filename;
            form.submit();
          }
        }
      });
    }

    function confirmDeleteBackup(filename) {
      const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

      Swal.fire({
        title: '¿Eliminar Copia de Seguridad?',
        text: `¿Estás seguro de que deseas eliminar permanentemente el archivo "${filename}"?`,
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
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Eliminar'
      }).then((result) => {
        if (result.isConfirmed) {
          const form = document.getElementById('deleteBackupForm');
          const input = document.getElementById('deleteBackupFilename');
          if (form && input) {
            input.value = filename;
            form.submit();
          }
        }
      });
    }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBackupsManager);
  } else {
    initBackupsManager();
  }
})();

