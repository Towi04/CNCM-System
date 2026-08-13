<?php
require_once __DIR__ . '/../config.php';

if (!manuales_stock_puede_stock()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para administrar stock de manuales.</div>';
    return;
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap" data-manuales-page="stock">
  <div class="catalog-header">
    <h2><i class="fas fa-boxes"></i> Stock de manuales</h2>
    <p style="color:#666; margin:0;">Publique o ajuste existencias por producto en bodega central, tránsito o plantel.</p>
  </div>

  <div id="manuales-msg" class="catalog-alert" hidden></div>

  <form id="manuales-stock-form" class="catalog-toolbar" style="align-items:flex-end;" data-no-global-ajax>
    <label class="field">Producto
      <select name="id_producto" id="manuales-stock-producto" required></select>
    </label>
    <label class="field">Ubicación
      <select name="ubicacion" id="manuales-stock-ubicacion">
        <option value="bodega">Bodega central</option>
        <option value="transito">Tránsito</option>
        <option value="plantel">Plantel</option>
      </select>
    </label>
    <label class="field">Plantel
      <select name="id_plantel" id="manuales-stock-plantel"></select>
    </label>
    <label class="field">Cantidad
      <input type="number" name="cantidad" min="0" step="1" value="0" required>
    </label>
    <button type="submit" class="primary"><i class="fas fa-save"></i> Guardar stock</button>
  </form>

  <div class="catalog-table-wrap">
    <table class="catalog-table" id="manuales-stock-tabla">
      <thead>
        <tr><th>Producto</th><th>Bodega central</th><th>En tránsito</th><th>En planteles</th></tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
window.HAY_MANUALES = <?php echo json_encode([
    'api' => hay_asset_url('php/manuales_stock_api.php'),
    'page' => 'stock',
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/manuales_stock.js?v=20260813'), ENT_QUOTES, 'UTF-8'); ?>"></script>
