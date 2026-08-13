<?php
require_once __DIR__ . '/../config.php';

if (!reporte_bajas_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para ver reporte de bajas.</div>';
    return;
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-user-slash"></i> Reporte de bajas</h2>
    <p style="color:#666; margin:0;">Alumnos en estado baja por semana, mes, año o rango personalizado.</p>
  </div>

  <div id="rep-bajas-msg" class="catalog-alert" hidden></div>

  <div class="catalog-toolbar" style="align-items:flex-end;">
    <label class="field">Periodo
      <select id="rep-bajas-periodo">
        <option value="semana">Semana actual</option>
        <option value="mes" selected>Mes actual</option>
        <option value="anio">Año actual</option>
        <option value="rango">Rango</option>
      </select>
    </label>
    <label class="field">Desde
      <input type="date" id="rep-bajas-desde">
    </label>
    <label class="field">Hasta
      <input type="date" id="rep-bajas-hasta">
    </label>
    <button type="button" class="primary" id="rep-bajas-filtrar"><i class="fas fa-filter"></i> Filtrar</button>
  </div>

  <div id="rep-bajas-resumen" class="catalog-alert catalog-alert--ok" style="margin:12px 0;"></div>

  <div class="catalog-table-wrap">
    <table class="catalog-table" id="rep-bajas-tabla">
      <thead>
        <tr>
          <th>Fecha baja</th>
          <th>Alumno</th>
          <th>Tipo</th>
          <th>Vigencia inscripción</th>
          <th>Motivo</th>
          <th>Grupo / especialidad</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
window.HAY_REPORTE_BAJAS = <?php echo json_encode([
    'api' => hay_asset_url('php/reporte_bajas_api.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/reporte_bajas.js?v=20260813'), ENT_QUOTES, 'UTF-8'); ?>"></script>
