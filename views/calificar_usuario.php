<?php
require_once __DIR__ . '/../config.php';
if (!profesor_eval_puede_gestionar()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para evaluar profesores.</div>';
    return;
}

$anio = (int) ($_GET['anio'] ?? (int) date('Y'));
$mes = (int) ($_GET['mes'] ?? (int) date('n'));
$idPlantel = plantel_scope_id($pdo);
$rows = profesor_eval_listar_profesores($pdo, $idPlantel, $anio, $mes);

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/profesor_eval.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2>Evaluación 360 — Profesores</h2>
    <form class="pe-periodo-form" method="get" onsubmit="return false;">
      <label>Año
        <input type="number" id="pe-anio" value="<?php echo $anio; ?>" min="2020" max="2100">
      </label>
      <label>Mes
        <select id="pe-mes">
          <?php foreach ($meses as $n => $lbl): ?>
          <option value="<?php echo $n; ?>" <?php echo $n === $mes ? 'selected' : ''; ?>><?php echo htmlspecialchars($lbl); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="button" class="secondary" id="pe-aplicar-periodo">Ver periodo</button>
    </form>
  </div>

  <p class="pe-hint">
    Las métricas de <strong>retención</strong>, <strong>asistencia de alumnos</strong>, <strong>puntualidad</strong> y <strong>entrega de calificaciones</strong>
    se calculan desde el sistema; la coordinación completa criterios manuales y cierra el periodo.
  </p>

  <div class="catalog-table-wrap">
    <?php if ($rows === []): ?>
      <p>No hay profesores activos en este plantel.</p>
    <?php else: ?>
      <table class="catalog-table">
        <thead>
          <tr>
            <th>Profesor</th>
            <th>Estado eval.</th>
            <th>Puntos</th>
            <th>Nivel</th>
            <th>Actualizado</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <strong><?php echo htmlspecialchars(trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? ''))); ?></strong>
              <?php if (!empty($r['email'])): ?>
                <br><small><?php echo htmlspecialchars($r['email']); ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?php if (empty($r['id_eval'])): ?>
                <span class="pe-badge pe-badge--pend">Sin iniciar</span>
              <?php elseif (($r['eval_estado'] ?? '') === 'cerrado'): ?>
                <span class="pe-badge pe-badge--ok">Cerrada</span>
              <?php else: ?>
                <span class="pe-badge pe-badge--draft">Borrador</span>
              <?php endif; ?>
            </td>
            <td><?php echo $r['puntos_total'] !== null ? (int) $r['puntos_total'] : '—'; ?></td>
            <td><?php echo $r['nivel'] ? htmlspecialchars($r['nivel']) : '—'; ?></td>
            <td><?php echo $r['actualizado_en'] ? htmlspecialchars(substr($r['actualizado_en'], 0, 16)) : '—'; ?></td>
            <td>
              <button type="button" class="primary btn-pe-eval"
                data-id="<?php echo (int) $r['id_usuario']; ?>">
                Evaluar
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  document.getElementById('pe-aplicar-periodo')?.addEventListener('click', () => {
    const anio = document.getElementById('pe-anio')?.value || '';
    const mes = document.getElementById('pe-mes')?.value || '';
    cargarSeccion('calificar_usuario', 'anio=' + encodeURIComponent(anio) + '&mes=' + encodeURIComponent(mes));
  });
  document.querySelectorAll('.btn-pe-eval').forEach((btn) => {
    btn.addEventListener('click', () => {
      const anio = document.getElementById('pe-anio')?.value || '';
      const mes = document.getElementById('pe-mes')?.value || '';
      const id = btn.dataset.id;
      cargarSeccion('profesor_evaluacion', 'id_usuario=' + id + '&anio=' + anio + '&mes=' + mes);
    });
  });
})();
</script>
