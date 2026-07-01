<?php
require_once __DIR__ . '/../config.php';
$tipo = (string) ($_GET['tipo'] ?? 'coordinador');
$idCiclo = (int) ($_GET['id_ciclo'] ?? 0);
$idProfesor = (int) ($_GET['id_profesor'] ?? 0);
$idGrupo = (int) ($_GET['id_grupo'] ?? 0);

if ($tipo === 'auto') {
    $idProfesor = (int) ($_SESSION['user_id'] ?? 0);
}
if (!profesor_360_puede_evaluar_como($tipo) && !profesor_360_puede_gestionar()) {
    echo '<div class="alert">Sin permiso para esta evaluación.</div>';
    return;
}
$ciclo = profesor_360_obtener_ciclo($pdo, $idCiclo);
if (!$ciclo) {
    echo '<div class="alert">Ciclo no encontrado.</div>';
    return;
}
$rub = profesor_360_rubrica_por_tipo($pdo, '360_' . ($tipo === 'coordinador' ? 'coordinador' : $tipo));
if (!$rub) {
    echo '<div class="alert">Rúbrica no configurada.</div>';
    return;
}
$st = $pdo->prepare('SELECT nombre, apellido FROM usuarios WHERE id_usuario = ?');
$st->execute([$idProfesor]);
$prof = $st->fetch(PDO::FETCH_ASSOC);
$api = hay_asset_url('php/profesor_360_api.php');
$labels = ['alumno' => 'Alumno → Profesor', 'coordinador' => 'Coordinador → Profesor', 'auto' => 'Auto-evaluación', 'adjunto' => 'Profesor adjunto'];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css')); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/profesor_eval.css')); ?>">

<div class="catalog-wrap">
  <h2><?php echo htmlspecialchars($labels[$tipo] ?? $tipo); ?></h2>
  <p>Profesor: <strong><?php echo htmlspecialchars(trim(($prof['nombre'] ?? '') . ' ' . ($prof['apellido'] ?? ''))); ?></strong>
    · Ciclo <?php echo (int) $ciclo['mes']; ?>/<?php echo (int) $ciclo['anio']; ?></p>

  <form id="form-eval-360" data-no-global-ajax="1" style="max-width:640px;">
    <input type="hidden" name="action" value="guardar_eval">
    <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo); ?>">
    <input type="hidden" name="id_ciclo" value="<?php echo $idCiclo; ?>">
    <input type="hidden" name="id_profesor" value="<?php echo $idProfesor; ?>">
    <input type="hidden" name="id_grupo" value="<?php echo $idGrupo; ?>">
    <?php foreach ($rub['criterios'] as $c): ?>
    <div class="field" style="margin-bottom:10px;">
      <label><?php echo htmlspecialchars($c['nombre']); ?> (0–<?php echo (int) $c['maximo']; ?>)</label>
      <input type="number" name="puntaje_<?php echo htmlspecialchars($c['codigo']); ?>"
             min="0" max="<?php echo (int) $c['maximo']; ?>" step="0.5" value="0" style="width:100%;">
    </div>
    <?php endforeach; ?>
    <textarea name="observaciones" rows="4" placeholder="Observaciones (anónimas para el profesor en evaluaciones de alumnos)" style="width:100%;"></textarea>
    <div style="display:flex;gap:8px;margin-top:12px;">
      <button type="submit" name="cerrar" value="0" class="secondary">Guardar borrador</button>
      <button type="submit" name="cerrar" value="1" class="primary">Enviar y cerrar</button>
    </div>
  </form>
</div>
<script>
document.getElementById('form-eval-360')?.addEventListener('submit', async (e)=>{
  const btn = e.submitter;
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.set('cerrar', btn?.value === '1' ? '1' : '0');
  const r = await fetch(<?php echo json_encode($api); ?>, {method:'POST', body:fd, credentials:'same-origin'});
  const d = await r.json();
  alert(d.message || '');
  if(d.status==='ok' && btn?.value==='1') {
    if(typeof cargarSeccion==='function') cargarSeccion('profesor_360_mis_resultados');
  }
});
</script>
