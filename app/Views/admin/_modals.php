<?php
/**
 * Modales de Administración:
 *  - Modal para ampliar código QR de 2FA
 *
 * Variables: $totpQrCode, $pendingSecret
 */
?>
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

