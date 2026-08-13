<?php
require_once __DIR__ . '/../config.php';

if (!manuales_stock_puede_envios()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para ver envíos de manuales.</div>';
    return;
}
$puedeStock = manuales_stock_puede_stock();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap" data-manuales-page="envios">
  <div class="catalog-header">
    <h2><i class="fas fa-shipping-fast"></i> Envíos de manuales</h2>
    <p style="color:#666; margin:0;">Bodega central envía manuales; el plantel destino confirma la recepción.</p>
  </div>

  <div id="manuales-msg" class="catalog-alert" hidden></div>

  <?php if ($puedeStock): ?>
  <form id="manuales-envio-form" class="catalog-toolbar" style="align-items:flex-end;" data-no-global-ajax>
    <label class="field">Producto
      <select name="id_producto" id="manuales-envio-producto" required></select>
    </label>
    <label class="field">Plantel destino
      <select name="id_plantel_destino" id="manuales-envio-plantel" required></select>
    </label>
    <label class="field">Cantidad
      <input type="number" name="cantidad" min="1" step="1" value="1" required>
    </label>
    <label class="field" style="flex:1;">Notas
      <input type="text" name="notas" placeholder="Guía, caja, observaciones">
    </label>
    <button type="submit" class="primary"><i class="fas fa-paper-plane"></i> Enviar</button>
  </form>
  <?php else: ?>
  <p class="catalog-alert catalog-alert--ok">Puede confirmar recepción de envíos dirigidos a su plantel.</p>
  <?php endif; ?>

  <div class="catalog-table-wrap">
    <table class="catalog-table" id="manuales-envios-tabla">
      <thead>
        <tr>
          <th>Estado</th>
          <th>Producto</th>
          <th>Plantel</th>
          <th>Cantidad</th>
          <th>Enviado</th>
          <th>Recibido</th>
          <th></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
window.HAY_MANUALES = <?php echo json_encode([
    'api' => hay_asset_url('php/manuales_stock_api.php'),
    'page' => 'envios',
    'puedeStock' => $puedeStock,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/manuales_stock.js?v=20260813'), ENT_QUOTES, 'UTF-8'); ?>"></script>
