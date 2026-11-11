/**
 * Gestor de Proxy Inverso - Mantenimiento Simplificado
 *
 * Limpieza directa de Sesiones, Caché y Logs con confirmación SweetAlert2.
 */

(function () {
  'use strict';

  function isDarkTheme() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark';
  }

  function getSwalThemeConfig() {
    const dark = isDarkTheme();
    return {
      background: dark ? '#0b1114' : '#f8f9fa',
      color: dark ? '#ffffff' : '#0b1114',
    };
  }

  function initMaintenanceManager() {
    // 1. Limpiar Todo
    const btnFull = document.getElementById('btnRunFullMaintenance');
    if (btnFull) {
      btnFull.addEventListener('click', function () {
        const theme = getSwalThemeConfig();
        Swal.fire({
          title: '¿Limpiar todo?',
          text: 'Se limpiarán las sesiones inactivas, los archivos de log y la memoria caché.',
          icon: 'question',
          showCancelButton: true,
          reverseButtons: true,
          buttonsStyling: false,
          background: theme.background,
          color: theme.color,
          customClass: {
            cancelButton: 'btn btn-dark btn-sm me-2',
            confirmButton: 'btn btn-primary btn-sm'
          },
          cancelButtonText: 'Cancelar',
          confirmButtonText: 'Limpiar Todo'
        }).then((result) => {
          if (result.isConfirmed) {
            const form = document.getElementById('fullMaintenanceForm');
            if (form) form.submit();
          }
        });
      });
    }

    // 2. Limpiar Sesiones
    document.querySelectorAll('.btn-clean-sessions-all').forEach((btn) => {
      btn.addEventListener('click', function () {
        const theme = getSwalThemeConfig();
        Swal.fire({
          title: '¿Limpiar sesiones?',
          text: 'Se eliminarán las sesiones almacenadas. Tu sesión actual no se cerrará.',
          icon: 'warning',
          showCancelButton: true,
          reverseButtons: true,
          buttonsStyling: false,
          background: theme.background,
          color: theme.color,
          customClass: {
            cancelButton: 'btn btn-dark btn-sm me-2',
            confirmButton: 'btn btn-primary btn-sm'
          },
          cancelButtonText: 'Cancelar',
          confirmButtonText: 'Limpiar'
        }).then((result) => {
          if (result.isConfirmed) {
            const form = document.getElementById('cleanSessionsForm');
            if (form) form.submit();
          }
        });
      });
    });

    // 3. Limpiar Caché
    document.querySelectorAll('.btn-clean-cache').forEach((btn) => {
      btn.addEventListener('click', function () {
        const theme = getSwalThemeConfig();
        Swal.fire({
          title: '¿Limpiar caché?',
          text: 'Se vaciará la memoria temporal y la caché del sistema.',
          icon: 'question',
          showCancelButton: true,
          reverseButtons: true,
          buttonsStyling: false,
          background: theme.background,
          color: theme.color,
          customClass: {
            cancelButton: 'btn btn-dark btn-sm me-2',
            confirmButton: 'btn btn-primary btn-sm'
          },
          cancelButtonText: 'Cancelar',
          confirmButtonText: 'Limpiar'
        }).then((result) => {
          if (result.isConfirmed) {
            const form = document.getElementById('cleanCacheForm');
            if (form) form.submit();
          }
        });
      });
    });

    // 4. Limpiar Logs
    document.querySelectorAll('.btn-clean-logs-all').forEach((btn) => {
      btn.addEventListener('click', function () {
        const theme = getSwalThemeConfig();
        Swal.fire({
          title: '¿Limpiar logs?',
          text: 'Se eliminarán los archivos de registro de la aplicación.',
          icon: 'warning',
          showCancelButton: true,
          reverseButtons: true,
          buttonsStyling: false,
          background: theme.background,
          color: theme.color,
          customClass: {
            cancelButton: 'btn btn-dark btn-sm me-2',
            confirmButton: 'btn btn-primary btn-sm'
          },
          cancelButtonText: 'Cancelar',
          confirmButtonText: 'Limpiar'
        }).then((result) => {
          if (result.isConfirmed) {
            const form = document.getElementById('cleanLogsForm');
            if (form) form.submit();
          }
        });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMaintenanceManager);
  } else {
    initMaintenanceManager();
  }
})();
