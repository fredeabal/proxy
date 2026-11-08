// =====================================================================
// PWA (Progressive Web App) Manager para NetCrew (iOS & Android)
// =====================================================================

(function () {
  'use strict';

  // 1. Registro del Service Worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker
        .register('/sw.js')
        .then(function (registration) {
          // Service worker registrado correctamente
        })
        .catch(function (error) {
          console.warn('Error al registrar Service Worker PWA:', error);
        });
    });
  }

  // 2. Detección de plataforma y modo de ejecución
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  var isIOS = /iphone|ipad|ipod/.test(navigator.userAgent.toLowerCase());
  var deferredInstallPrompt = null;

  // Si ya está ejecutándose como PWA instalada, no mostramos prompts
  if (isStandalone) {
    return;
  }

  // Capturar evento de instalación nativa en Android / Chromium
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredInstallPrompt = e;
    
    // Si existe algún botón de instalación en la interfaz, mostrarlo
    var installBtns = document.querySelectorAll('.pwa-install-btn');
    installBtns.forEach(function (btn) {
      btn.classList.remove('d-none');
    });
  });

  // Función global para disparar la instalación
  window.installNetCrewPWA = function () {
    if (deferredInstallPrompt) {
      deferredInstallPrompt.prompt();
      deferredInstallPrompt.userChoice.then(function (choiceResult) {
        if (choiceResult.outcome === 'accepted') {
          console.log('Usuario aceptó instalar NetCrew');
        }
        deferredInstallPrompt = null;
      });
    } else if (isIOS) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Instalar en iPhone / iPad',
          html: '<div class="text-start fs-3">' +
                '<p class="mb-2">Para instalar <strong>NetCrew</strong> en la pantalla de inicio de tu dispositivo Apple:</p>' +
                '<ol class="ps-3 mb-0">' +
                '<li class="mb-2">Pulsa el botón de <strong>Compartir</strong> <i class="ti ti-share fs-5 text-primary align-middle"></i> (en la barra inferior de Safari).</li>' +
                '<li class="mb-2">Desplázate hacia abajo y selecciona <strong>"Añadir a pantalla de inicio"</strong> <i class="ti ti-square-plus fs-5 text-primary align-middle"></i>.</li>' +
                '<li>Pulsa <strong>Añadir</strong> en la esquina superior derecha.</li>' +
                '</ol></div>',
          icon: 'info',
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#F38020'
        });
      } else {
        alert('Para instalar en iOS: Pulsa el botón de Compartir en Safari y selecciona "Añadir a pantalla de inicio".');
      }
    } else {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Instalar NetCrew',
          text: 'Para instalar esta aplicación, pulsa en el menú de opciones de tu navegador (tres puntos) y selecciona "Instalar aplicación" o "Añadir a la pantalla de inicio".',
          icon: 'info',
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#F38020'
        });
      }
    }
  };

  // Evento cuando la PWA se ha instalado con éxito
  window.addEventListener('appinstalled', function () {
    var installBtns = document.querySelectorAll('.pwa-install-btn');
    installBtns.forEach(function (btn) {
      btn.classList.add('d-none');
    });
    deferredInstallPrompt = null;
  });
})();

