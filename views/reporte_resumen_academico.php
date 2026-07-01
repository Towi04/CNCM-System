<?php
require_once __DIR__ . '/../config.php';
if (!function_exists('reporte_academico_puede_ver') || !reporte_academico_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para este reporte.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$rows = reporte_academico_resumen_grupos($pdo, $idPlantel);
$stats = reporte_academico_estadisticas_plantel($rows);
$topAsist = reporte_academico_top_grupos($rows, 'asistencia', 10);
$topRiesgo = reporte_academico_top_grupos($rows, 'riesgo', 8);
$exportBase = function_exists('hay_asset_url') ? hay_asset_url('php/reporte_academico_export.php') : 'php/reporte_academico_export.php';
$chartEsp = array_slice($stats['por_especialidad'] ?? [], 0, 8, true);
?>
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2>Resumen académico por grupo</h2>
    <p style="color:#666; margin:0;">Últimos 90 días de asistencia · parcial actual · alumnos en riesgo</p>
    <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
      <a href="<?php echo htmlspecialchars($exportBase . '?format=csv'); ?>" target="_blank" rel="noopener" class="secondary" style="padding:8px 14px; text-decoration:none; border-radius:6px;">
        <i class="fas fa-file-csv"></i> Exportar CSV
      </a>
      <a href="<?php echo htmlspecialchars($exportBase . '?format=pdf'); ?>" target="_blank" rel="noopener" class="secondary" style="padding:8px 14px; text-decoration:none; border-radius:6px;">
        <i class="fas fa-file-pdf"></i> Exportar PDF
      </a>
    </div>
  </div>

  <?php if ($rows !== []): ?>
  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin:16px 0;">
    <div class="welcome-card" style="margin:0;padding:12px;"><small>Grupos</small><div style="font-size:1.4rem;font-weight:700;"><?php echo (int) $stats['total_grupos']; ?></div></div>
    <div class="welcome-card" style="margin:0;padding:12px;"><small>Alumnos</small><div style="font-size:1.4rem;font-weight:700;"><?php echo (int) $stats['total_alumnos']; ?></div></div>
    <div class="welcome-card" style="margin:0;padding:12px;"><small>En riesgo</small><div style="font-size:1.4rem;font-weight:700;color:#c62828;"><?php echo (int) $stats['total_riesgo']; ?></div></div>
    <div class="welcome-card" style="margin:0;padding:12px;"><small>Asist. prom.</small><div style="font-size:1.4rem;font-weight:700;"><?php echo $stats['asistencia_promedio'] !== null ? $stats['asistencia_promedio'] . '%' : '—'; ?></div></div>
  </div>

  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; margin-bottom:20px;">
    <div class="welcome-card" style="margin:0;padding:14px;">
      <h4 style="margin:0 0 10px;font-size:0.95rem;">Asistencia por grupo (top 10)</h4>
      <canvas id="chart-asistencia" height="200"></canvas>
    </div>
    <div class="welcome-card" style="margin:0;padding:14px;">
      <h4 style="margin:0 0 10px;font-size:0.95rem;">Grupos por especialidad</h4>
      <canvas id="chart-especialidad" height="200"></canvas>
    </div>
    <div class="welcome-card" style="margin:0;padding:14px;">
      <h4 style="margin:0 0 10px;font-size:0.95rem;">Alumnos en riesgo por grupo</h4>
      <canvas id="chart-riesgo" height="200"></canvas>
    </div>
  </div>
  <?php endif; ?>

  <div class="catalog-table-wrap">
    <?php if ($rows === []): ?>
      <p>No hay grupos en este plantel. Ejecute el seed de prueba si necesita datos demo.</p>
    <?php else: ?>
      <table class="catalog-table">
        <thead>
          <tr>
            <th>Grupo</th>
            <th>Especialidad</th>
            <th>Profesor</th>
            <th>Alumnos</th>
            <th>Asist. 90d</th>
            <th>Prom. parcial</th>
            <th>Calif.</th>
            <th>Riesgo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($r['clave'] ?? ''); ?></strong></td>
            <td><?php echo htmlspecialchars($r['esp_nombre'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars($r['profesor'] ?? '—'); ?></td>
            <td><?php echo (int) ($r['num_alumnos'] ?? 0); ?></td>
            <td><?php echo $r['asistencia_pct'] !== null ? $r['asistencia_pct'] . '%' : '—'; ?></td>
            <td><?php echo $r['promedio_parcial'] !== null ? htmlspecialchars((string) $r['promedio_parcial']) : '—'; ?></td>
            <td><?php echo (int) ($r['calif_capturadas'] ?? 0); ?> / <?php echo (int) ($r['num_alumnos'] ?? 0); ?></td>
            <td>
              <?php if ((int) ($r['en_riesgo'] ?? 0) > 0): ?>
                <span style="color:#c62828;"><?php echo (int) $r['en_riesgo']; ?></span>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php if ($rows !== []): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  const topAsist = <?php echo json_encode(array_map(static fn ($r) => [
      'clave' => $r['clave'] ?? '',
      'pct' => $r['asistencia_pct'] !== null ? (float) $r['asistencia_pct'] : 0,
  ], $topAsist), JSON_UNESCAPED_UNICODE); ?>;
  const topRiesgo = <?php echo json_encode(array_values(array_filter(array_map(static fn ($r) => [
      'clave' => $r['clave'] ?? '',
      'n' => (int) ($r['en_riesgo'] ?? 0),
  ], $topRiesgo), static fn ($r) => $r['n'] > 0)), JSON_UNESCAPED_UNICODE); ?>;
  const esp = <?php echo json_encode(array_map(static fn ($k, $v) => ['nombre' => $k, 'grupos' => $v['grupos']], array_keys($chartEsp), array_values($chartEsp)), JSON_UNESCAPED_UNICODE); ?>;

  if (typeof Chart === 'undefined') return;

  const c1 = document.getElementById('chart-asistencia');
  if (c1 && topAsist.length) {
    new Chart(c1, {
      type: 'bar',
      data: {
        labels: topAsist.map((x) => x.clave),
        datasets: [{ label: '% asistencia', data: topAsist.map((x) => x.pct), backgroundColor: '#11458B' }],
      },
      options: { responsive: true, scales: { y: { max: 100, beginAtZero: true } } },
    });
  }

  const c2 = document.getElementById('chart-especialidad');
  if (c2 && esp.length) {
    new Chart(c2, {
      type: 'doughnut',
      data: {
        labels: esp.map((x) => x.nombre),
        datasets: [{ data: esp.map((x) => x.grupos), backgroundColor: ['#11458B', '#c62828', '#2e7d32', '#f57c00', '#6a1b9a', '#00838f', '#5d4037', '#455a64'] }],
      },
      options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
    });
  }

  const c3 = document.getElementById('chart-riesgo');
  if (c3 && topRiesgo.length) {
    new Chart(c3, {
      type: 'bar',
      data: {
        labels: topRiesgo.map((x) => x.clave),
        datasets: [{ label: 'Alumnos en riesgo', data: topRiesgo.map((x) => x.n), backgroundColor: '#c62828' }],
      },
      options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } },
    });
  }
})();
</script>
<?php endif; ?>
