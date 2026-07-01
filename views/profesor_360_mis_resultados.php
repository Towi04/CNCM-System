<?php
require_once __DIR__ . '/../config.php';
$idUser = (int) ($_SESSION['user_id'] ?? 0);
$rol = rbac_rol_efectivo();

if ($rol === 'profesor') {
    $resultados = profesor_360_resultados_profesor($pdo, $idUser);
    $pendientes = profesor_360_pendientes_usuario($pdo, $idUser, $rol);
} elseif ($rol === 'alumno') {
    $resultados = [];
    $pendientes = profesor_360_pendientes_usuario($pdo, $idUser, $rol);
} elseif (profesor_360_puede_gestionar()) {
    $resultados = [];
    $pendientes = profesor_360_pendientes_usuario($pdo, $idUser, $rol);
} else {
    echo '<div class="alert">Sin acceso.</div>';
    return;
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css')); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/profesor_eval.css')); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-chart-pie"></i> Evaluación 360<?php echo $rol === 'profesor' ? ' — mis resultados' : ''; ?></h2>
    <?php if (profesor_360_puede_gestionar()): ?>
    <button type="button" class="secondary" onclick="cargarSeccion('profesor_360_ciclos')">Gestionar ciclos</button>
    <?php endif; ?>
  </div>

  <?php if ($pendientes !== []): ?>
  <section style="margin-bottom:20px;padding:14px;border:1px solid #c5cae9;border-radius:10px;background:#f8f9ff;">
    <h3>Pendientes de completar</h3>
    <ul>
      <?php foreach ($pendientes as $p): ?>
      <li style="margin-bottom:8px;">
        <?php echo htmlspecialchars($p['tipo']); ?>
        <?php if (!empty($p['profesor_nombre'])): ?> — <?php echo htmlspecialchars($p['profesor_nombre']); ?><?php endif; ?>
        <button type="button" class="primary" style="margin-left:8px;"
          onclick="cargarSeccion('profesor_360_evaluar','tipo=<?php echo urlencode($p['tipo']); ?>&id_ciclo=<?php echo (int) $p['id_ciclo']; ?>&id_profesor=<?php echo (int) ($p['id_profesor'] ?? 0); ?>&id_grupo=<?php echo (int) ($p['id_grupo'] ?? 0); ?>')">
          Evaluar
        </button>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if ($rol === 'profesor' && $resultados === []): ?>
  <p style="color:#666;">Aún no hay resultados publicados. Cuando coordinación publique un ciclo cerrado, verá aquí las observaciones anónimas y sus puntajes.</p>
  <?php endif; ?>

  <?php
  $byPeriod = [];
  foreach ($resultados as $r) {
      $key = ($r['mes'] ?? '') . '/' . ($r['anio'] ?? '');
      $byPeriod[$key][] = $r;
  }
  foreach ($byPeriod as $periodo => $items):
  ?>
  <section style="margin-bottom:18px;padding:14px;border:1px solid #eee;border-radius:10px;">
    <h3>Periodo <?php echo htmlspecialchars($periodo); ?></h3>
    <?php foreach ($items as $ev): ?>
    <div style="margin-bottom:12px;padding:10px;background:#fafafa;border-radius:8px;">
      <strong><?php echo htmlspecialchars($ev['evaluador_label'] ?? $ev['tipo']); ?></strong>
      — <?php echo htmlspecialchars((string) ($ev['pct'] ?? 0)); ?>%
      <?php if (!empty($ev['observaciones'])): ?>
      <p style="margin:6px 0 0;font-style:italic;color:#444;"><?php echo nl2br(htmlspecialchars($ev['observaciones'])); ?></p>
      <?php endif; ?>
      <ul style="margin:6px 0 0;font-size:0.9rem;">
        <?php foreach ($ev['rubrica'] ?? [] as $it): ?>
        <li><?php echo htmlspecialchars($it['nombre'] ?? ''); ?>: <?php echo (float) ($it['puntaje'] ?? 0); ?>/<?php echo (float) ($it['maximo'] ?? 0); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>
</div>
