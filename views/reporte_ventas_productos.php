<?php
require_once __DIR__ . '/../config.php';
if (!reporte_financiero_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para este reporte.</div>';
    return;
}

$rango = reporte_financiero_rango(date('Y-m-01'), date('Y-m-d'));
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-box"></i> Reporte de ventas (productos)</h2>
    <p style="color:#666;">Ventas de productos registradas en punto de venta.</p>
  </div>

  <div class="catalog-toolbar">
    <div><label>Desde</label><input type="date" id="rep-fin-desde" value="<?php echo htmlspecialchars($rango['desde']); ?>"></div>
    <div><label>Hasta</label><input type="date" id="rep-fin-hasta" value="<?php echo htmlspecialchars($rango['hasta']); ?>"></div>
    <div><button type="button" class="primary" id="btn-rep-fin-generar">Generar</button></div>
  </div>

  <p id="rep-fin-loading" hidden><i class="fas fa-spinner fa-spin"></i> Cargando…</p>
  <p id="rep-fin-resumen" class="catalog-alert catalog-alert--ok" style="display:block;"></p>

  <div class="catalog-table-wrap">
    <table class="catalog-table" id="rep-fin-tabla">
      <thead>
        <tr><th>Fecha</th><th>Producto</th><th>Alumno</th><th>Control</th><th>Monto</th><th>Forma pago</th><th>Cajero</th></tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
window.HAY_REP_FIN_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/reporte_financiero_api.php'),
    'accion' => 'productos',
    'cols' => 7,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/reporte_financiero.js?v=20260607'), ENT_QUOTES, 'UTF-8'); ?>"></script>
