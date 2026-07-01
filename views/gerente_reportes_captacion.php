<?php
require_once __DIR__ . '/../config.php';
if (!rbac_cap('menu_gerente_reportes')) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$desde = trim($_GET['desde'] ?? date('Y-m-01'));
$hasta = trim($_GET['hasta'] ?? date('Y-m-d'));
$rep = gerente_reporte_captacion($pdo, $idPlantel, $desde, $hasta);

$labelsMedio = [
    'redes_sociales' => 'Redes sociales',
    'publicidad' => 'Publicidad',
    'cartas' => 'Cartas',
    'pasando' => 'Pasando',
    'recomendado' => 'Recomendado',
    'otro' => 'Otro',
];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-chart-pie"></i> Reportes de captación</h2>
    <p style="color:#666;">Origen, edades e inscripciones/entrevistas por día.</p>
  </div>

  <form class="catalog-toolbar" onsubmit="event.preventDefault(); cargarSeccion('gerente_reportes_captacion','desde='+encodeURIComponent(document.getElementById('gr-desde').value)+'&hasta='+encodeURIComponent(document.getElementById('gr-hasta').value));">
    <div>
      <label>Desde</label>
      <input type="date" id="gr-desde" value="<?php echo htmlspecialchars($desde); ?>">
    </div>
    <div>
      <label>Hasta</label>
      <input type="date" id="gr-hasta" value="<?php echo htmlspecialchars($hasta); ?>">
    </div>
    <div style="align-self:end;">
      <button type="submit" class="primary">Actualizar</button>
      <button type="button" style="margin-left:8px;" onclick="cargarSeccion('gerente_reporte_geografico','desde='+encodeURIComponent(document.getElementById('gr-desde').value)+'&hasta='+encodeURIComponent(document.getElementById('gr-hasta').value))">
        <i class="fas fa-map-marked-alt"></i> Ver geográfico
      </button>
    </div>
  </form>

  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; margin-top:16px;">
    <div class="welcome-card" style="padding:16px;">
      <h3>Cómo se enteraron</h3>
      <?php if (empty($rep['origen'])): ?>
        <p style="color:#888;">Sin datos.</p>
      <?php else: ?>
        <ul><?php foreach ($rep['origen'] as $o): ?>
          <li><?php echo htmlspecialchars($labelsMedio[$o['origen']] ?? $o['origen']); ?>: <strong><?php echo (int) $o['total']; ?></strong></li>
        <?php endforeach; ?></ul>
      <?php endif; ?>
    </div>

    <div class="welcome-card" style="padding:16px;">
      <h3>Edades</h3>
      <?php if (empty($rep['edades'])): ?>
        <p style="color:#888;">Sin datos.</p>
      <?php else: ?>
        <ul><?php foreach ($rep['edades'] as $e): ?>
          <li><?php echo htmlspecialchars($e['rango']); ?>: <strong><?php echo (int) $e['total']; ?></strong></li>
        <?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>

  <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px;">
    <div class="welcome-card" style="padding:16px;">
      <h3>Inscripciones por día</h3>
      <?php if (empty($rep['inscripciones_dia'])): ?>
        <p style="color:#888;">Sin datos.</p>
      <?php else: ?>
        <table class="catalog-table"><thead><tr><th>Día</th><th>Total</th></tr></thead><tbody>
        <?php foreach ($rep['inscripciones_dia'] as $d): ?>
          <tr><td><?php echo htmlspecialchars($d['dia']); ?></td><td><?php echo (int) $d['total']; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    </div>
    <div class="welcome-card" style="padding:16px;">
      <h3>Entrevistas por día</h3>
      <?php if (empty($rep['entrevistas_dia'])): ?>
        <p style="color:#888;">Sin datos.</p>
      <?php else: ?>
        <table class="catalog-table"><thead><tr><th>Día</th><th>Total</th></tr></thead><tbody>
        <?php foreach ($rep['entrevistas_dia'] as $d): ?>
          <tr><td><?php echo htmlspecialchars($d['dia']); ?></td><td><?php echo (int) $d['total']; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    </div>
  </div>
</div>
