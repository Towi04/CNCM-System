<?php
require_once __DIR__ . '/../config.php';
if (!asistencia_puede_ver_puntualidad()) {
    echo '<div class="alert">No tiene permiso para ver puntualidad del personal.</div>';
    return;
}

$idPlantel = plantel_id_activo();
$fecha = trim($_GET['fecha'] ?? date('Y-m-d'));
$lista = asistencia_lista_puntualidad_dia($pdo, $idPlantel, $fecha);

$codigoClass = [
    '10_min_antes' => 'punt-ok',
    'a_la_hora' => 'punt-ok',
    '5_min_tarde' => 'punt-warn',
    'mas_5_tarde' => 'punt-bad',
    'sin_registro' => 'punt-bad',
    'sin_horario' => 'punt-neutral',
];
?>
<link rel="stylesheet" href="css/asistencia.css">

<div class="asist-wrap">
  <h2><i class="fas fa-clock"></i> Puntualidad del personal (HAY)</h2>
  <p style="color:#666;">Entrada registrada en el lector fijo vs primera clase del día (profesores y personal del plantel).</p>

  <div class="asist-toolbar">
    <div>
      <label>Fecha</label>
      <input type="date" id="punt-fecha" value="<?php echo htmlspecialchars($fecha); ?>">
    </div>
    <div>
      <button type="button" class="primary" id="btn-punt-filtrar">Actualizar</button>
    </div>
    <div>
      <button type="button" onclick="cargarSeccion('asistencia')">Asistencias</button>
    </div>
  </div>

  <table class="asist-punt-tabla">
    <thead>
        <tr>
          <th>Persona</th>
          <th>Rol</th>
          <th>PIN huella</th>
          <th>Primera clase</th>
          <th>Entrada</th>
          <th>Salida</th>
          <th>Resultado</th>
          <th>Min.</th>
        </tr>
    </thead>
    <tbody>
      <?php if (empty($lista)): ?>
        <tr><td colspan="8">No hay personal con checada configurada en este plantel.</td></tr>
      <?php else: ?>
        <?php foreach ($lista as $r):
          $cls = $codigoClass[$r['codigo']] ?? 'punt-neutral';
        ?>
        <tr>
          <td><?php echo htmlspecialchars($r['nombre'] . ' ' . $r['apellido']); ?></td>
          <td><?php echo htmlspecialchars(rbac_etiqueta_rol($r['rol'] ?? '')); ?></td>
          <td><?php echo htmlspecialchars($r['codigo_huella'] ?? '—'); ?></td>
          <td><?php echo $r['hora_esperada'] ? asistencia_format_hora($r['hora_esperada']) : '—'; ?></td>
          <td><?php echo $r['hora_llegada'] ? asistencia_format_hora($r['hora_llegada']) : '—'; ?></td>
          <td><?php echo !empty($r['hora_salida']) ? asistencia_format_hora($r['hora_salida']) : '—'; ?></td>
          <td><span class="asist-punt-badge <?php echo $cls; ?>"><?php echo htmlspecialchars($r['etiqueta']); ?></span></td>
          <td><?php echo $r['minutos'] !== null ? (int) $r['minutos'] : '—'; ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
document.getElementById('btn-punt-filtrar')?.addEventListener('click', () => {
  const f = document.getElementById('punt-fecha').value;
  cargarSeccion('asistencia_puntualidad', new URLSearchParams({ fecha: f }));
});
</script>
