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
    <h2><i class="fas fa-hand-holding-usd"></i> Apoyos a la inscripción</h2>
    <p style="color:#666;">Alumnos que recibieron descuento real en inscripción (beca, promoción o monto de apoyo).</p>
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
        <tr>
          <th>Fecha</th><th>Alumno</th><th>Control</th><th>Especialidad</th>
          <th>Pagó</th><th>Descuento</th><th>Apoyo / beca</th><th>Autorizó</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
window.HAY_REP_FIN_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/reporte_financiero_api.php'),
    'accion' => 'apoyos_inscripcion',
    'cols' => 8,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/reporte_financiero.js?v=20260608'), ENT_QUOTES, 'UTF-8'); ?>"></script>
