<?php

require_once __DIR__ . '/../config.php';

if (!cola_facturacion_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para la cola de facturación.</div>';
    return;
}

$idFocus = (int) ($_GET['id'] ?? 0);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/cola_facturacion.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap cola-fact-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-file-invoice"></i> Cola de facturación</h2>
    <button type="button" class="secondary" id="cola-fact-refrescar"><i class="fas fa-sync"></i> Actualizar</button>
  </div>
  <p style="color:#666; margin-top:0;">
    Prospectos o alumnos con solicitud de factura sin datos fiscales completos. Capture RFC, razón social, constancia fiscal o quite la solicitud si ya no aplica.
  </p>

  <div id="cola-fact-msg" class="catalog-alert" style="display:none;"></div>

  <div class="cola-fact-resumen">
    <span class="cola-fact-badge"><i class="fas fa-clock"></i> Pendientes: <span id="cola-fact-total">…</span></span>
  </div>

  <div id="cola-fact-loading" class="cola-fact-loading" hidden><i class="fas fa-spinner fa-spin"></i> Cargando…</div>
  <div id="cola-fact-lista" class="cola-fact-lista"></div>
</div>

<script>
window.HAY_COLA_FACT_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/cola_facturacion_api.php'),
    'focusId' => $idFocus,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/cola_facturacion.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>
