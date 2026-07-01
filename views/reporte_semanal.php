<?php
require_once __DIR__ . '/../config.php';
if (!reporte_semanal_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para este reporte.</div>';
    return;
}

$actual = reporte_semanal_desde_fecha(date('Y-m-d'));
$anio = (int) ($_GET['anio'] ?? $actual['anio']);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/reporte_semanal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap rep-sem-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-chart-bar"></i> Reporte semanal de asistencia</h2>
    <p class="rep-sem-sub">Semanas domingo–sábado (1–52). Cambios de horario (+C / −C) no afectan el total del plantel.</p>
  </div>

  <div class="rep-sem-toolbar catalog-toolbar">
    <div>
      <label>Vista</label>
      <select id="rep-sem-modo">
        <option value="semana" selected>Semana actual / una semana</option>
        <option value="rango">Rango de semanas</option>
        <option value="mes">Mes completo</option>
        <option value="anio">Año completo</option>
      </select>
    </div>
    <div>
      <label>Año</label>
      <input type="number" id="rep-sem-anio" value="<?php echo $anio; ?>" min="2020" max="2099">
    </div>
    <div class="rep-sem-field rep-sem-field--semana">
      <label>Semana</label>
      <input type="number" id="rep-sem-semana" value="<?php echo (int) $actual['semana']; ?>" min="1" max="52">
    </div>
    <div class="rep-sem-field rep-sem-field--rango" hidden>
      <label>Desde sem.</label>
      <input type="number" id="rep-sem-desde" value="<?php echo (int) $actual['semana']; ?>" min="1" max="52">
    </div>
    <div class="rep-sem-field rep-sem-field--rango" hidden>
      <label>Hasta sem.</label>
      <input type="number" id="rep-sem-hasta" value="<?php echo (int) $actual['semana']; ?>" min="1" max="52">
    </div>
    <div>
      <button type="button" class="primary" id="btn-rep-sem-generar">Generar reporte</button>
    </div>
  </div>

  <p id="rep-sem-periodo" class="rep-sem-periodo"><?php echo htmlspecialchars($actual['etiqueta']); ?></p>

  <div id="rep-sem-loading" class="rep-sem-loading" hidden><i class="fas fa-spinner fa-spin"></i> Calculando…</div>
  <div id="rep-sem-contenido"></div>
</div>

<script>
window.HAY_REP_SEM_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/reporte_semanal_api.php'),
    'actual' => $actual,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/reporte_semanal.js?v=20260603'), ENT_QUOTES, 'UTF-8'); ?>"></script>
