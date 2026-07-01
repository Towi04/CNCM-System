<?php
require_once __DIR__ . '/../config.php';
if (!rbac_cap('menu_alumnos') && !rbac_cap('menu_grupos')) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$control = trim($_GET['control'] ?? '');
$calificaciones = [];
$alumno = null;

if ($control !== '') {
    $st = $pdo->prepare(
        'SELECT id_alumno, numero_control,
                TRIM(CONCAT(COALESCE(nombres, nombre, \'\'), \' \', COALESCE(apellido_paterno, apellido, \'\'))) AS nombre
         FROM alumnos WHERE id_plantel = ? AND numero_control = ? LIMIT 1'
    );
    $st->execute([$idPlantel, $control]);
    $alumno = $st->fetch(PDO::FETCH_ASSOC);
    if ($alumno) {
        $calificaciones = calificaciones_alumno_por_fase($pdo, (int) $alumno['id_alumno'], $idPlantel);
    }
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-graduation-cap"></i> Calificaciones por fase</h2>
    <p style="color:#666;">Promedios capturados por parcial/fase de la especialidad que cursa el alumno.</p>
  </div>

  <div class="catalog-toolbar">
    <div>
      <label>No. control</label>
      <input type="search" id="cal-al-control" value="<?php echo htmlspecialchars($control); ?>" placeholder="Ej. 10042">
    </div>
    <div>
      <button type="button" class="primary" id="btn-cal-al-buscar">Buscar</button>
    </div>
  </div>

  <?php if ($control !== '' && !$alumno): ?>
    <p class="catalog-alert catalog-alert--error">No se encontró alumno con control <?php echo htmlspecialchars($control); ?>.</p>
  <?php elseif ($alumno): ?>
    <p><strong><?php echo htmlspecialchars($alumno['nombre']); ?></strong> · Control <?php echo htmlspecialchars($alumno['numero_control']); ?></p>
    <div class="catalog-table-wrap">
      <table class="catalog-table">
        <thead>
          <tr>
            <th>Especialidad</th>
            <th>Fase / parcial</th>
            <th>Grupo</th>
            <th>Promedio</th>
            <th>Estado</th>
            <th>Actualizado</th>
            <th>Observaciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($calificaciones)): ?>
            <tr><td colspan="7" style="color:#888;">Sin calificaciones registradas aún.</td></tr>
          <?php else: foreach ($calificaciones as $c): ?>
            <tr>
              <td><?php echo htmlspecialchars($c['especialidad_nombre'] ?? ''); ?></td>
              <td><strong><?php echo htmlspecialchars($c['clave_fase'] ?? ''); ?></strong> — <?php echo htmlspecialchars($c['nombre_fase'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($c['grupo_clave'] ?? '—'); ?></td>
              <td><?php echo $c['promedio'] !== null ? htmlspecialchars((string)$c['promedio']) : '—'; ?></td>
              <td><?php echo (int)($c['aprobado'] ?? 0) ? '<span style="color:#2e7d32;">Aprobado</span>' : '<span style="color:#c62828;">No aprobado</span>'; ?></td>
              <td><?php echo !empty($c['actualizado_en']) ? htmlspecialchars(date('d/m/Y', strtotime($c['actualizado_en']))) : '—'; ?></td>
              <td><?php echo htmlspecialchars($c['observaciones'] ?? ''); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
document.getElementById('btn-cal-al-buscar')?.addEventListener('click', () => {
  const c = document.getElementById('cal-al-control')?.value?.trim();
  if (!c) return;
  cargarSeccion('alumno_calificaciones', 'control=' + encodeURIComponent(c));
});
document.getElementById('cal-al-control')?.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') document.getElementById('btn-cal-al-buscar')?.click();
});
</script>
