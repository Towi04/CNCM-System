<?php
require_once __DIR__ . '/../config.php';
if (!venta_producto_puede_acceder()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para venta de productos.</div>';
    return;
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/punto_venta.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/venta_productos.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="vp-legacy">
  <div class="pv-top-selectors">
    <div class="pv-selector-block">
      <label class="pv-label-required">Selecciona al alumno que va a comprar:*</label>
      <select id="vp-sel-alumno" class="pv-select">
        <option value="">Selecciona un alumno</option>
      </select>
    </div>
    <div class="pv-selector-block">
      <label class="pv-label-required">� ingresa el nombre de la persona:*</label>
      <input type="text" id="vp-cliente-nombre" class="pv-input" placeholder="Nombre para ticket (p�blico general)" autocomplete="off">
    </div>
  </div>

  <div class="pv-main">
    <section class="pv-left">
      <div class="pv-tabs">
        <span class="pv-tab pv-tab--active">Agregar productos al carrito</span>
      </div>
      <div class="pv-left-body">
        <label class="pv-label">Selecciona el producto para agregarlo al carrito:</label>
        <select id="vp-sel-producto" class="pv-select">
          <option value="">Selecciona un producto</option>
        </select>
        <p class="vp-hint" id="vp-prod-info" hidden></p>

        <div class="pv-table-wrap vp-carrito-wrap">
          <table class="pv-table" id="vp-tabla-carrito">
            <thead>
              <tr>
                <th>Acciones</th>
                <th>Nombre</th>
                <th>Cantidad</th>
                <th>Precio unitario</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="5" class="pv-empty">No se encontr� ning�n registro</td></tr>
            </tbody>
          </table>
        </div>
        <p class="vp-total-carrito">TOTAL: <strong id="vp-total">$ 0.00</strong></p>
      </div>
    </section>

    <aside class="pv-right">
      <div class="pv-tabs">
        <span class="pv-tab pv-tab--active">Cerrar venta</span>
      </div>
      <div class="pv-right-body">
        <form id="vp-form-venta">
          <label class="pv-label">Forma Pago:</label>
          <select id="vp-forma" class="pv-select">
            <option value="Efectivo" selected>Efectivo</option>
          </select>
          <p class="vp-nota-efectivo">Los productos se cobran en efectivo (Cuenta B).</p>
          <button type="submit" class="vp-btn-terminar" id="vp-btn-terminar" disabled>Terminar venta</button>
        </form>
        <div id="vp-msg" class="catalog-alert" style="display:none; margin-top:12px;"></div>
      </div>
    </aside>
  </div>
</div>

<script>
window.HAY_VP_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/venta_productos_api.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/venta_productos.js?v=20260608'), ENT_QUOTES, 'UTF-8'); ?>"></script>
