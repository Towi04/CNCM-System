<?php

require_once __DIR__ . '/../config.php';

if (!escuelas_puede_ver_reporte()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}

escuelas_ensure_schema($pdo);
$escuelas = escuelas_listar($pdo, plantel_scope_id($pdo));

?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/reporte_escuelas.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-chart-bar"></i> Reporte de escuelas</h2>
    <p style="color:#666; margin:0;">Visitas, cartas entregadas y captación por escuela de origen.</p>
  </div>

  <div class="catalog-toolbar">
    <div class="field"><label>Desde</label><input type="date" id="re-desde"></div>
    <div class="field"><label>Hasta</label><input type="date" id="re-hasta"></div>
    <div class="field">
      <label>Escuela</label>
      <select id="re-escuela">
        <option value="">Todas</option>
        <?php foreach ($escuelas as $e): ?>
          <option value="<?php echo (int) $e['id_escuela']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="button" class="primary" id="re-buscar">Actualizar</button>
  </div>

  <div id="re-resumen" class="catalog-alert catalog-alert--ok" style="margin-bottom:12px;"></div>

  <div class="catalog-table-wrap hay-dt-panel">
    <table class="catalog-table" id="re-tabla">
      <thead>
        <tr>
          <th>Escuela</th>
          <th>Municipio</th>
          <th>Visitas</th>
          <th>Cartas</th>
          <th>Pre-registros</th>
          <th>Inscritos</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
window.HAY_REPORTE_ESCUELAS = <?php echo json_encode([
    'api' => hay_asset_url('php/escuelas_api.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/reporte_escuelas.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>if (window.hayReporteEscuelasInit) window.hayReporteEscuelasInit();</script>
