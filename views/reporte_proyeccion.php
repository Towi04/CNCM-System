<?php
require_once __DIR__ . '/../config.php';
if (!reporte_cartera_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para este reporte.</div>';
    return;
}
$plantelNombre = $_SESSION['plantel_nombre'] ?? 'Plantel';
$hoy = date('Y-m-d');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/reporte_cartera.css?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-chart-line"></i> Reporte de proyección de cobranza</h2>
    <p style="color:#666;"><?php echo htmlspecialchars($plantelNombre); ?> · Estimación de ingresos por colegiaturas en el periodo siguiente · Metas para recepción</p>
  </div>

  <div class="reporte-cartera-tabs" id="proy-modo-tabs" style="margin-bottom:12px;">
    <button type="button" data-modo="dia">Mañana</button>
    <button type="button" data-modo="semana">Próxima semana</button>
    <button type="button" class="active" data-modo="mes">Próximo mes</button>
  </div>

  <div class="catalog-toolbar reporte-cartera-filtros" style="flex-wrap:wrap; gap:12px;">
    <div>
      <label>Referencia (desde hoy)</label>
      <input type="date" id="proy-fecha" value="<?php echo htmlspecialchars($hoy); ?>">
    </div>
    <div>
      <label>Especialidad</label>
      <select id="proy-esp"><option value="">Todas</option></select>
    </div>
    <div>
      <label>Grupo</label>
      <select id="proy-grupo"><option value="">Todos</option></select>
    </div>
    <div>
      <label>Forma pago</label>
      <select id="proy-forma">
        <option value="">Todas</option>
        <option value="mensual">Mensual</option>
        <option value="semanal">Semanal</option>
      </select>
    </div>
    <div>
      <label>Buscar</label>
      <input type="search" id="proy-q" placeholder="Nombre, control o grupo">
    </div>
    <div style="align-self:flex-end; display:flex; gap:8px; flex-wrap:wrap;">
      <button type="button" class="primary" id="btn-proy-cargar"><i class="fas fa-sync"></i> Calcular proyección</button>
      <button type="button" class="secondary" id="btn-proy-export"><i class="fas fa-file-csv"></i> Exportar CSV</button>
      <button type="button" class="secondary" id="btn-proy-print"><i class="fas fa-print"></i> Imprimir</button>
    </div>
  </div>

  <p id="proy-rango" style="font-weight:600; color:#11458B; margin:12px 0;"></p>
  <div id="proy-resumen" class="reporte-cartera-resumen"></div>
  <p id="proy-nota" style="font-size:0.88rem; color:#666; margin-bottom:12px;"></p>

  <div class="catalog-table-wrap">
    <table class="catalog-table" id="proy-tabla">
      <thead>
        <tr>
          <th>Control</th>
          <th>Alumno</th>
          <th>Grupo</th>
          <th>Forma</th>
          <th>Cargo del periodo</th>
          <th>Adeudo previo</th>
          <th>Proyección</th>
          <th>Posible visita</th>
          <th>Hábito de pago</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr><td colspan="10" style="color:#888;">Seleccione periodo y calcule.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
window.HAY_REPORTE_PROYECCION = <?php echo json_encode([
    'api' => hay_asset_url('php/reporte_cartera_api.php'),
    'export' => hay_asset_url('php/reporte_cartera_export.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/reporte_proyeccion.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>
