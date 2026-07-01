<?php
require_once __DIR__ . '/../config.php';
if (!grupo_plan_puede_editar()) {
    echo '<div class="alert">Solo coordinación puede planificar parciales del grupo.</div>';
    return;
}

$idGrupo = (int) ($_GET['id_grupo'] ?? 0);
$idPlantel = plantel_id_activo();

if ($idGrupo <= 0) {
    $stGr = $pdo->prepare('SELECT id_grupo, clave FROM grupos WHERE id_plantel = ? ORDER BY clave ASC');
    $stGr->execute([$idPlantel]);
    $gruposPick = $stGr->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <link rel="stylesheet" href="css/admin_catalogo.css">
    <div class="catalog-wrap" style="max-width:520px;">
      <h2>Plan de parciales</h2>
      <p style="color:#666;">Seleccione el grupo a planificar.</p>
      <label><strong>Grupo</strong></label>
      <select id="gp-pick-grupo" style="width:100%; padding:10px; margin:8px 0 14px; border-radius:8px; border:1px solid #ddd;">
        <option value="">— Elija un grupo —</option>
        <?php foreach ($gruposPick as $g): ?>
        <option value="<?php echo (int) $g['id_grupo']; ?>"><?php echo htmlspecialchars($g['clave']); ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="primary" id="gp-pick-go">Continuar</button>
    </div>
    <script>
    document.getElementById('gp-pick-go')?.addEventListener('click', () => {
      const id = document.getElementById('gp-pick-grupo')?.value;
      if (!id) { alert('Seleccione un grupo.'); return; }
      cargarSeccion('grupo_plan', 'id_grupo=' + encodeURIComponent(id));
    });
    </script>
    <?php
    return;
}

$st = $pdo->prepare(
    'SELECT g.*, e.nombre AS esp_nombre, e.clave AS esp_clave
     FROM grupos g
     LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
     WHERE g.id_grupo = ? AND g.id_plantel = ?'
);
$st->execute([$idGrupo, $idPlantel]);
$grupo = $st->fetch(PDO::FETCH_ASSOC);

if (!$grupo) {
    echo '<div class="alert">Grupo no encontrado en este plantel. <button type="button" class="secondary" onclick="cargarSeccion(\'grupo_plan\')">Elegir otro grupo</button></div>';
    return;
}

$idEsp = (int) ($grupo['id_especialidad'] ?? 0);
$fases = $idEsp ? fase_listar($pdo, $idEsp) : [];
$anio = (int) ($_GET['anio'] ?? date('Y'));
$mes = (int) ($_GET['mes'] ?? date('n'));
$planActual = grupo_plan_obtener($pdo, $idGrupo, $anio, $mes);
$historial = grupo_plan_listar_grupo($pdo, $idGrupo, $anio);
$pendientes = grupo_plan_pendientes_retomar($pdo, $idGrupo);
$pos = academico_posicion_grupo($pdo, $grupo);

$idsTemarioSel = [];
if ($planActual) {
    foreach ($planActual['fases_temario'] as $ft) {
        $idsTemarioSel[] = (int) $ft['id_fase'];
    }
} elseif (!empty($grupo['id_fase_actual'])) {
    $idsTemarioSel = [(int) $grupo['id_fase_actual']];
}
$idRegistroSel = $planActual ? (int) $planActual['id_fase_registro'] : (int) ($grupo['id_fase_actual'] ?? ($fases[0]['id_fase'] ?? 0));
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/grupo_plan.css">
<link rel="stylesheet" href="css/hay_icon_buttons.css">

<div class="catalog-wrap grupo-plan-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-calendar-check"></i> Plan de parciales — <?php echo grupo_clave_html($grupo); ?></h2>
    <button type="button" class="secondary" onclick="cargarSeccion('grupos')">Volver a grupos</button>
  </div>

  <p class="grupo-plan-hint">
    <strong>Solo coordinación.</strong> El alumno siempre ve su parcial en orden normal (ej. «A1 - Parcial 3» en el mes que corresponda).
    Aquí puede planear impartir temario de varios parciales en un mismo mes antes de una fusión; el sistema <em>no</em> muestra al alumno que se adelantó.
    Deje nota de qué temas retomar cuando el grupo termine ese mes intensivo.
  </p>

  <?php if ($pendientes !== []): ?>
    <div class="grupo-plan-alerta">
      <h4><i class="fas fa-bookmark"></i> Pendiente de retomar en clase</h4>
      <ul>
        <?php foreach ($pendientes as $p): ?>
          <li>
            <strong><?php echo grupo_plan_mes_label((int)$p['mes']) . ' ' . (int)$p['anio']; ?></strong>
            (registro <?php echo htmlspecialchars($p['clave_registro'] ?? ''); ?>):
            <?php echo htmlspecialchars(mb_strimwidth($p['temas_retomar'] ?: $p['nota_coordinador'], 0, 200, '…')); ?>
            <button type="button" class="btn-icon-only btn-icon-only--ok btn-retomado" data-id="<?php echo (int)$p['id_plan']; ?>" title="Marcar atendido" style="margin-left:6px;">
              <i class="fas fa-check"></i>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="grupo-plan-card">
    <h3>Posición del grupo (calendario)</h3>
    <p style="margin:0; font-size:0.9rem; color:#546e7a;">
      Sesiones lectivas: <strong><?php echo (int)$pos['semanas_lectivas']; ?></strong> ·
      Semana <strong><?php echo (int)$pos['semana_parcial']; ?></strong> del parcial en curso
      <?php if (!empty($grupo['id_fase_actual'])):
        foreach ($fases as $f) {
          if ((int)$f['id_fase'] === (int)$grupo['id_fase_actual']) {
            echo ' · Fase grupo: <code>' . htmlspecialchars($f['clave_fase'] ?? '') . '</code>';
            break;
          }
        }
      endif; ?>
    </p>
  </div>

  <div class="grupo-plan-card">
    <h3>Planificar <?php echo grupo_plan_mes_label($mes) . ' ' . $anio; ?></h3>

    <div class="catalog-toolbar" style="margin-bottom:14px;">
      <div class="field">
        <label>Año</label>
        <input type="number" id="plan-anio" value="<?php echo $anio; ?>" min="2020" max="2040" style="width:100px;">
      </div>
      <div class="field">
        <label>Mes</label>
        <select id="plan-mes" style="min-width:140px;">
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?php echo $m; ?>"<?php echo $m === $mes ? ' selected' : ''; ?>><?php echo grupo_plan_mes_label($m); ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <button type="button" class="secondary" id="btn-plan-cambiar-mes">Ir a mes</button>
    </div>

    <form id="form-plan-periodo">
      <input type="hidden" name="id_grupo" value="<?php echo $idGrupo; ?>">

      <p class="grupo-plan-hint">1. Elija el <strong>parcial que se registra al alumno</strong> este mes (lo que verá en su historial).</p>
      <div class="grupo-plan-fases-check" id="fases-registro">
        <?php foreach ($fases as $f): ?>
          <label>
            <input type="radio" name="id_fase_registro" value="<?php echo (int)$f['id_fase']; ?>"
              <?php echo (int)$f['id_fase'] === $idRegistroSel ? 'checked' : ''; ?> required>
            <span><code><?php echo htmlspecialchars($f['clave_fase'] ?? ''); ?></code><br>
              <?php echo htmlspecialchars($f['nombre_fase']); ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <p class="grupo-plan-hint">2. Marque <strong>todo el temario que impartirá</strong> este mes (puede ser más de un parcial si comprime antes de fusionar).</p>
      <div class="grupo-plan-fases-check" id="fases-temario">
        <?php foreach ($fases as $f): ?>
          <label>
            <input type="checkbox" name="fases_temario[]" value="<?php echo (int)$f['id_fase']; ?>"
              <?php echo in_array((int)$f['id_fase'], $idsTemarioSel, true) ? 'checked' : ''; ?>>
            <span><code><?php echo htmlspecialchars($f['clave_fase'] ?? ''); ?></code>
              <?php echo htmlspecialchars($f['nombre_fase']); ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="catalog-form-grid">
        <div class="full">
          <label>Temas a retomar después (visible solo coordinación)</label>
          <textarea id="plan-temas-retomar" rows="3" placeholder="Ej. Repasar vocabulario de A1-4, listening de semana 3…"><?php echo htmlspecialchars($planActual['temas_retomar'] ?? ''); ?></textarea>
        </div>
        <div class="full">
          <label>Nota interna coordinador</label>
          <textarea id="plan-nota" rows="2" placeholder="Fusión prevista con IS105, cubrimos dos parciales en noviembre…"><?php echo htmlspecialchars($planActual['nota_coordinador'] ?? ''); ?></textarea>
        </div>
        <div class="full">
          <label style="display:flex; align-items:center; gap:8px; font-weight:normal;">
            <input type="checkbox" id="plan-pendiente" <?php echo !empty($planActual['pendiente_retomar']) ? 'checked' : ''; ?>>
            Recordar retomar estos temas al cerrar el mes
          </label>
        </div>
      </div>

      <div id="plan-msg" class="catalog-alert" style="display:none; margin-top:12px;"></div>

      <div style="margin-top:14px; display:flex; gap:10px;">
        <button type="submit" class="primary"><i class="fas fa-save"></i> Guardar plan del mes</button>
        <a href="#" class="secondary" style="padding:10px 16px; text-decoration:none;" onclick="cargarSeccion('esp_fases', 'id_especialidad=<?php echo $idEsp; ?>'); return false;">Ver temarios</a>
      </div>
    </form>
  </div>

  <?php if ($historial !== []): ?>
    <div class="grupo-plan-card grupo-plan-historial">
      <h3>Historial <?php echo $anio; ?></h3>
      <?php foreach ($historial as $h): ?>
        <div class="grupo-plan-historial-item">
          <div>
            <strong><?php echo grupo_plan_mes_label((int)$h['mes']); ?></strong>
            <span class="grupo-plan-tag"><?php echo htmlspecialchars($h['clave_registro'] ?? ''); ?> registro</span>
            <?php if (!empty($h['pendiente_retomar'])): ?>
              <span class="grupo-plan-tag grupo-plan-tag--retomar">Retomar</span>
            <?php endif; ?>
          </div>
          <div style="flex:1; color:#607d8b;">
            <?php echo htmlspecialchars(grupo_plan_resumen_coordinador($h)); ?>
            <?php if (!empty($h['temas_retomar'])): ?>
              <br><em>Retomar:</em> <?php echo htmlspecialchars(mb_strimwidth($h['temas_retomar'], 0, 160, '…')); ?>
            <?php endif; ?>
          </div>
          <button type="button" class="btn-icon-only btn-icon-only--edit btn-edit-mes" title="Editar"
            data-anio="<?php echo (int)$h['anio']; ?>" data-mes="<?php echo (int)$h['mes']; ?>">
            <i class="fas fa-pen"></i>
          </button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
(function() {
  const idGrupo = <?php echo $idGrupo; ?>;

  document.getElementById('btn-plan-cambiar-mes').onclick = () => {
    const a = document.getElementById('plan-anio').value;
    const m = document.getElementById('plan-mes').value;
    cargarSeccion('grupo_plan', 'id_grupo=' + idGrupo + '&anio=' + a + '&mes=' + m);
  };

  document.querySelectorAll('.btn-edit-mes').forEach(btn => {
    btn.onclick = () => cargarSeccion('grupo_plan', 'id_grupo=' + idGrupo + '&anio=' + btn.dataset.anio + '&mes=' + btn.dataset.mes);
  });

  document.querySelectorAll('.btn-retomado').forEach(btn => {
    btn.onclick = async () => {
      const fd = new FormData();
      fd.append('action', 'marcar_retomado');
      fd.append('id_plan', btn.dataset.id);
      fd.append('id_grupo', idGrupo);
      const r = await fetch('php/grupo_plan_api.php', { method: 'POST', body: fd });
      const d = await r.json();
      if (d.status === 'ok') cargarSeccion('grupo_plan', 'id_grupo=' + idGrupo);
    };
  });

  document.getElementById('fases-registro').addEventListener('change', e => {
    if (e.target.name !== 'id_fase_registro') return;
    const id = e.target.value;
    const cb = document.querySelector('#fases-temario input[value="' + id + '"]');
    if (cb) cb.checked = true;
  });

  document.getElementById('form-plan-periodo').onsubmit = async (e) => {
    e.preventDefault();
    const msg = document.getElementById('plan-msg');
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id_grupo', idGrupo);
    fd.append('anio', document.getElementById('plan-anio').value);
    fd.append('mes', document.getElementById('plan-mes').value);
    const reg = document.querySelector('input[name="id_fase_registro"]:checked');
    if (!reg) { msg.style.display = 'block'; msg.className = 'catalog-alert catalog-alert--error'; msg.textContent = 'Seleccione parcial de registro'; return; }
    fd.append('id_fase_registro', reg.value);
    const tem = [];
    document.querySelectorAll('#fases-temario input:checked').forEach(c => tem.push(c.value));
    fd.append('fases_temario', JSON.stringify(tem));
    fd.append('temas_retomar', document.getElementById('plan-temas-retomar').value);
    fd.append('nota_coordinador', document.getElementById('plan-nota').value);
    if (document.getElementById('plan-pendiente').checked) fd.append('pendiente_retomar', '1');
    const r = await fetch('php/grupo_plan_api.php', { method: 'POST', body: fd });
    const d = await r.json();
    msg.style.display = 'block';
    msg.className = 'catalog-alert catalog-alert--' + (d.status === 'ok' ? 'ok' : 'error');
    msg.textContent = d.message || '';
    if (d.status === 'ok') setTimeout(() => cargarSeccion('grupo_plan', 'id_grupo=' + idGrupo + '&anio=' + fd.get('anio') + '&mes=' + fd.get('mes')), 600);
  };
})();
</script>
