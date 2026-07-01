<?php
require_once __DIR__ . '/../config.php';
if (!profesor_360_puede_gestionar()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}
$idPlantel = plantel_scope_id($pdo);
$ciclos = profesor_360_listar_ciclos($pdo, $idPlantel);
$profesores = profesor_360_profesores_plantel($pdo, $idPlantel);
$api = hay_asset_url('php/profesor_360_api.php');
$idCiclo = (int) ($_GET['id_ciclo'] ?? 0);
$ciclo = $idCiclo > 0 ? profesor_360_obtener_ciclo($pdo, $idCiclo) : null;
$participantes = $idCiclo > 0 ? profesor_360_participantes($pdo, $idCiclo) : [];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css')); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css')); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-sync-alt"></i> Ciclos evaluación 360</h2>
    <div style="display:flex;gap:8px;">
      <button type="button" class="secondary" onclick="cargarSeccion('docente_rubricas')">Rúbricas</button>
      <button type="button" class="secondary" onclick="cargarSeccion('calificar_usuario')">Eval. mensual legacy</button>
    </div>
  </div>
  <div id="msg-p360" class="catalog-alert" style="display:none;"></div>

  <section style="margin-bottom:20px;padding:14px;border:1px solid #eee;border-radius:10px;">
    <h3><?php echo $ciclo ? 'Editar ciclo' : 'Nuevo ciclo'; ?></h3>
    <form id="form-ciclo-360" data-no-global-ajax="1" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
      <input type="hidden" name="action" value="guardar_ciclo">
      <input type="hidden" name="id_ciclo" value="<?php echo (int) ($ciclo['id_ciclo'] ?? 0); ?>">
      <label>Año<input type="number" name="anio" value="<?php echo (int) ($ciclo['anio'] ?? date('Y')); ?>" required></label>
      <label>Mes<input type="number" name="mes" min="1" max="12" value="<?php echo (int) ($ciclo['mes'] ?? date('n')); ?>" required></label>
      <label>Título<input type="text" name="titulo" value="<?php echo htmlspecialchars($ciclo['titulo'] ?? ''); ?>" placeholder="Ej. Evaluación marzo"></label>
      <label>Inicio alumnos<input type="datetime-local" name="inicio_alumno" value="<?php echo $ciclo && $ciclo['inicio_alumno'] ? date('Y-m-d\TH:i', strtotime($ciclo['inicio_alumno'])) : ''; ?>"></label>
      <label>Cierre alumnos<input type="datetime-local" name="fin_alumno" value="<?php echo $ciclo && $ciclo['fin_alumno'] ? date('Y-m-d\TH:i', strtotime($ciclo['fin_alumno'])) : ''; ?>"></label>
      <label>Inicio adjunto<input type="datetime-local" name="inicio_adjunto" value="<?php echo $ciclo && $ciclo['inicio_adjunto'] ? date('Y-m-d\TH:i', strtotime($ciclo['inicio_adjunto'])) : ''; ?>"></label>
      <label>Cierre adjunto<input type="datetime-local" name="fin_adjunto" value="<?php echo $ciclo && $ciclo['fin_adjunto'] ? date('Y-m-d\TH:i', strtotime($ciclo['fin_adjunto'])) : ''; ?>"></label>
      <label>Inicio auto-eval<input type="datetime-local" name="inicio_auto" value="<?php echo $ciclo && $ciclo['inicio_auto'] ? date('Y-m-d\TH:i', strtotime($ciclo['inicio_auto'])) : ''; ?>"></label>
      <label>Cierre auto-eval<input type="datetime-local" name="fin_auto" value="<?php echo $ciclo && $ciclo['fin_auto'] ? date('Y-m-d\TH:i', strtotime($ciclo['fin_auto'])) : ''; ?>"></label>
      <label>Inicio coordinador<input type="datetime-local" name="inicio_coord" value="<?php echo $ciclo && $ciclo['inicio_coord'] ? date('Y-m-d\TH:i', strtotime($ciclo['inicio_coord'])) : ''; ?>"></label>
      <label>Cierre coordinador<input type="datetime-local" name="fin_coord" value="<?php echo $ciclo && $ciclo['fin_coord'] ? date('Y-m-d\TH:i', strtotime($ciclo['fin_coord'])) : ''; ?>"></label>
      <div style="grid-column:1/-1;"><button type="submit" class="primary">Guardar ciclo</button></div>
    </form>
    <?php if ($ciclo): ?>
    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
      <button type="button" class="secondary btn-ciclo-acc" data-acc="publicar_ciclo">Abrir evaluaciones</button>
      <button type="button" class="secondary btn-ciclo-acc" data-acc="cerrar_ciclo">Cerrar ciclo</button>
      <button type="button" class="primary btn-ciclo-acc" data-acc="publicar_resultados">Publicar resultados en perfiles</button>
      <span style="align-self:center;color:#666;">Estado: <strong><?php echo htmlspecialchars($ciclo['estado']); ?></strong></span>
    </div>
    <?php endif; ?>
  </section>

  <?php if ($ciclo): ?>
  <section style="margin-bottom:20px;padding:14px;border:1px solid #eee;border-radius:10px;">
    <h3>Profesores y profesor adjunto</h3>
    <form id="form-participantes" data-no-global-ajax="1">
      <input type="hidden" name="action" value="guardar_participantes">
      <input type="hidden" name="id_ciclo" value="<?php echo $idCiclo; ?>">
      <table class="catalog-table">
        <thead><tr><th>Profesor</th><th>Profesor adjunto evaluador</th></tr></thead>
        <tbody id="tbody-part">
          <?php
          $map = [];
          foreach ($participantes as $pp) {
              $map[(int) $pp['id_profesor']] = (int) ($pp['id_adjunto'] ?? 0);
          }
          foreach ($profesores as $pr):
              $idP = (int) $pr['id_usuario'];
              $adj = $map[$idP] ?? 0;
          ?>
          <tr>
            <td><?php echo htmlspecialchars($pr['nombre_completo']); ?>
              <input type="hidden" name="pid" value="<?php echo $idP; ?>"></td>
            <td>
              <select class="sel-adj" data-prof="<?php echo $idP; ?>">
                <option value="">— Sin adjunto —</option>
                <?php foreach ($profesores as $ad): if ((int) $ad['id_usuario'] === $idP) continue; ?>
                <option value="<?php echo (int) $ad['id_usuario']; ?>" <?php echo $adj === (int) $ad['id_usuario'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($ad['nombre_completo']); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <button type="submit" class="primary" style="margin-top:10px;">Guardar asignaciones</button>
    </form>
  </section>
  <?php endif; ?>

  <table class="catalog-table">
    <thead><tr><th>Periodo</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($ciclos as $c): ?>
      <tr>
        <td><?php echo (int) $c['mes']; ?>/<?php echo (int) $c['anio']; ?> — <?php echo htmlspecialchars($c['titulo'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($c['estado']); ?></td>
        <td><button type="button" class="secondary" onclick="cargarSeccion('profesor_360_ciclos','id_ciclo=<?php echo (int) $c['id_ciclo']; ?>')">Gestionar</button></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script>
(function(){
  const api = <?php echo json_encode($api); ?>;
  const msg = document.getElementById('msg-p360');
  function show(m, ok){ msg.style.display='block'; msg.className='catalog-alert '+(ok?'catalog-alert--ok':'catalog-alert--error'); msg.textContent=m; }
  document.getElementById('form-ciclo-360')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const r = await fetch(api,{method:'POST',body:new FormData(e.target),credentials:'same-origin'});
    const d = await r.json(); show(d.message,d.status==='ok');
    if(d.status==='ok' && d.id_ciclo) cargarSeccion('profesor_360_ciclos','id_ciclo='+d.id_ciclo);
  });
  document.querySelectorAll('.btn-ciclo-acc').forEach(b=>b.addEventListener('click', async ()=>{
    const fd = new FormData(); fd.append('action', b.dataset.acc); fd.append('id_ciclo', <?php echo $idCiclo; ?>);
    const r = await fetch(api,{method:'POST',body:fd,credentials:'same-origin'});
    const d = await r.json(); show(d.message,d.status==='ok');
    if(d.status==='ok') cargarSeccion('profesor_360_ciclos','id_ciclo=<?php echo $idCiclo; ?>');
  }));
  document.getElementById('form-participantes')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const filas = [];
    document.querySelectorAll('.sel-adj').forEach(s=>filas.push({id_profesor: parseInt(s.dataset.prof,10), id_adjunto: parseInt(s.value,10)||null}));
    const fd = new FormData(); fd.append('action','guardar_participantes'); fd.append('id_ciclo', <?php echo $idCiclo; ?>);
    fd.append('participantes', JSON.stringify(filas));
    const r = await fetch(api,{method:'POST',body:fd,credentials:'same-origin'});
    const d = await r.json(); show(d.message,d.status==='ok');
  });
})();
</script>
