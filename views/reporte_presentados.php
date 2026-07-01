<?php

require_once __DIR__ . '/../config.php';

if (!reporte_presentados_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}

?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/reporte_presentados.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-user-graduate"></i> Reporte de presentados</h2>
    <p style="color:#666; margin:0;">Alumnos inscritos antes del primer día de clase y asistencia en la apertura del grupo.</p>
  </div>

  <div class="catalog-toolbar">
    <div class="field"><label>Desde (inicio grupo)</label><input type="date" id="rp-desde"></div>
    <div class="field"><label>Hasta (inicio grupo)</label><input type="date" id="rp-hasta"></div>
    <div class="field">
      <label>Asesor (ID usuario)</label>
      <input type="number" id="rp-asesor" placeholder="Opcional" min="0">
    </div>
    <button type="button" class="primary" id="rp-buscar">Actualizar</button>
  </div>

  <div id="rp-resumen" class="catalog-alert catalog-alert--ok" style="margin-bottom:12px;"></div>

  <div class="catalog-table-wrap hay-dt-panel">
    <table class="catalog-table" id="rp-tabla">
      <thead>
        <tr>
          <th>Grupo</th>
          <th>Inicio grupo</th>
          <th>1er día clase</th>
          <th>Control</th>
          <th>Nombre</th>
          <th>Inscripción</th>
          <th>Asesor</th>
          <th>Presentó</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
window.HAY_REPORTE_PRESENTADOS = <?php echo json_encode([
    'api' => hay_asset_url('php/reporte_presentados_api.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/reporte_presentados.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>if (window.hayReportePresentadosInit) window.hayReportePresentadosInit();</script>
