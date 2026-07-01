<?php
require_once __DIR__ . '/../config.php';
if (!ventas_comision_puede_consultar()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}
$esAsesor = rbac_rol_efectivo() === 'asesor';
$api = hay_asset_url('php/ventas_comision_api.php');
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<div class="catalog-wrap" id="vc-consulta-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-coins"></i> Mis comisiones y sueldo base</h2>
    <p style="color:#666; margin:0;">Consulta estimada según inscripciones registradas y tabulador vigente. Los montos son referencia hasta cierre de nómina.</p>
  </div>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Periodo</label>
      <select id="vc-periodo">
        <option value="semana">Semana</option>
        <option value="mes">Mes</option>
        <option value="dia">Día</option>
      </select>
    </div>
    <div class="field"><label>Fecha de referencia</label><input type="date" id="vc-fecha"></div>
    <?php if (!$esAsesor): ?>
    <div class="field">
      <label>Asesor (ID usuario)</label>
      <input type="number" id="vc-asesor" min="0" placeholder="Vacío = usted">
    </div>
    <?php endif; ?>
    <button type="button" class="primary" id="vc-buscar">Actualizar</button>
  </div>

  <div id="vc-resumen" class="catalog-alert catalog-alert--ok" style="margin-bottom:12px;"></div>

  <div id="vc-desglose" class="catalog-toolbar" style="flex-wrap:wrap; gap:12px; margin-bottom:12px;"></div>

  <h3 style="font-size:1rem;">Movimientos del periodo (inscripciones + certificaciones)</h3>
  <div class="catalog-table-wrap hay-dt-panel">
    <table class="catalog-table" id="vc-tabla-movs">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Tipo</th>
          <th>Alumno / ref.</th>
          <th>Monto base</th>
          <th>Comisión</th>
          <th>Cuenta tabulador</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>
<script>
window.__hayVentasComisionConsulta = {
  api: <?php echo json_encode($api, JSON_UNESCAPED_UNICODE); ?>,
  esAsesor: <?php echo $esAsesor ? 'true' : 'false'; ?>
};
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/ventas_comisiones_consulta.js?v=20260611'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>if (window.hayVentasComisionConsultaInit) window.hayVentasComisionConsultaInit();</script>
