<?php
require_once __DIR__ . '/_bootstrap.php';
/** @var PDO $pdo */
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert">No autorizado.</div>';
    return;
}
$idEval = (int) ($_GET['id_eval'] ?? 0);
if ($idEval <= 0) {
    echo '<div class="alert">Evaluación no indicada.</div>';
    return;
}
$eval = hay_eval_obtener_periodo($pdo, $idEval);
if (!$eval) {
    echo '<div class="alert">No encontrada.</div>';
    return;
}
$puedeGestionar = hay_eval_puede_gestionar();
$esPropio = (int) $eval['id_usuario'] === (int) $_SESSION['user_id'];
if (!$puedeGestionar && (!$esPropio || ($eval['estado'] ?? '') !== 'cerrado')) {
    echo '<div class="alert">No autorizado.</div>';
    return;
}
$rubrica = hay_eval_rubrica_completa($pdo, (int) $eval['id_area']);
$respuestas = hay_eval_cargar_respuestas($pdo, $idEval);
$cerrado = ($eval['estado'] ?? '') === 'cerrado' || !$puedeGestionar;
$stU = $pdo->prepare('SELECT nombre, apellido FROM usuarios WHERE id_usuario = ?');
$stU->execute([(int) $eval['id_usuario']]);
$u = $stU->fetch(PDO::FETCH_ASSOC);
$nombreColab = trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''));
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_eval.css">

<div class="hay-eval-wrap">
  <button type="button" class="secondary" onclick="cargarSeccion('<?php echo $puedeGestionar ? 'hay_evaluacion_admin' : 'mi_evaluacion'; ?>')">← Volver</button>
  <h2>Evaluación: <?php echo htmlspecialchars($nombreColab, ENT_QUOTES, 'UTF-8'); ?></h2>
  <p>Periodo <?php echo (int) $eval['mes']; ?>/<?php echo (int) $eval['anio']; ?>
    — <?php echo $cerrado ? '<strong>Cerrado</strong>' : 'Borrador'; ?></p>

  <form id="hay-eval-form">
    <input type="hidden" name="id_eval" value="<?php echo $idEval; ?>">
    <?php foreach ($rubrica['rubros'] ?? [] as $rub): ?>
    <div class="hay-rubro-block">
      <div class="hay-rubro-head"><?php echo htmlspecialchars($rub['titulo'], ENT_QUOTES, 'UTF-8'); ?></div>
      <?php foreach ($rub['aspectos'] ?? [] as $asp):
        $idAsp = (int) $asp['id_aspecto'];
        $selId = (int) ($respuestas[$idAsp]['id_opcion'] ?? 0);
      ?>
      <div class="hay-eval-form-aspecto">
        <label><?php echo htmlspecialchars($asp['nombre'], ENT_QUOTES, 'UTF-8'); ?></label>
        <select name="respuestas[<?php echo $idAsp; ?>]" class="hay-ev-aspecto-sel" data-aspecto="<?php echo $idAsp; ?>"<?php echo $cerrado ? ' disabled' : ''; ?>>
          <option value="">— Seleccione —</option>
          <?php foreach ($asp['opciones'] ?? [] as $op): ?>
          <option value="<?php echo (int) $op['id_opcion']; ?>" data-pts="<?php echo (int) $op['puntos']; ?>"
            <?php echo (int) $op['id_opcion'] === $selId ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($op['etiqueta'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int) $op['puntos']; ?> pts)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <p class="hay-eval-total">Total: <span id="hay-ev-total"><?php echo (int) $eval['puntos_total']; ?></span> pts</p>
    <?php if (!$cerrado): ?>
    <label>Observaciones</label>
    <textarea name="observaciones" rows="3" style="width:100%; padding:10px; border-radius:8px; border:1px solid #ddd;"><?php echo htmlspecialchars($eval['observaciones'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
      <button type="button" class="primary" id="hay-ev-guardar">Guardar borrador</button>
      <button type="button" class="secondary" id="hay-ev-sync">Sincronizar Moodle</button>
      <button type="button" class="secondary" id="hay-ev-cerrar">Cerrar evaluación</button>
    </div>
    <?php endif; ?>
  </form>
  <div id="hay-ev-msg" class="catalog-alert" style="display:none; margin-top:12px;"></div>
</div>

<script>
(function () {
  const api = 'php/hay_eval_api.php';
  const idEval = <?php echo $idEval; ?>;

  function recalcTotal() {
    let t = 0;
    document.querySelectorAll('.hay-ev-aspecto-sel').forEach((sel) => {
      const opt = sel.selectedOptions[0];
      if (opt?.dataset?.pts) t += parseInt(opt.dataset.pts, 10);
    });
    const el = document.getElementById('hay-ev-total');
    if (el) el.textContent = String(t);
  }
  document.querySelectorAll('.hay-ev-aspecto-sel').forEach((s) => s.addEventListener('change', recalcTotal));

  function respuestasMap() {
    const m = {};
    document.querySelectorAll('.hay-ev-aspecto-sel').forEach((sel) => {
      const asp = sel.dataset.aspecto;
      const v = sel.value;
      if (asp && v) m[asp] = v;
    });
    return m;
  }

  document.getElementById('hay-ev-guardar')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'guardar_respuestas');
    fd.append('id_eval', idEval);
    fd.append('respuestas', JSON.stringify(respuestasMap()));
    const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
    const msg = document.getElementById('hay-ev-msg');
    if (msg) {
      msg.style.display = 'block';
      msg.className = 'catalog-alert ' + (data.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');
      msg.textContent = data.message || (data.status === 'ok' ? 'Guardado' : 'Error');
    }
    if (data.puntos_total != null) document.getElementById('hay-ev-total').textContent = data.puntos_total;
  });

  document.getElementById('hay-ev-sync')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'sync_moodle');
    fd.append('id_eval', idEval);
    const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
    alert(data.message || (data.status === 'ok' ? 'OK' : 'Error'));
    if (data.status === 'ok') location.reload();
  });

  document.getElementById('hay-ev-cerrar')?.addEventListener('click', async () => {
    if (!confirm('¿Cerrar esta evaluación? El colaborador podrá verla en Mi evaluación.')) return;
    const fd = new FormData();
    fd.append('action', 'cerrar_periodo');
    fd.append('id_eval', idEval);
    fd.append('observaciones', document.querySelector('[name=observaciones]')?.value || '');
    const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
    if (data.status === 'ok') {
      alert('Cerrada. Nivel: ' + (data.nivel?.nombre_display || '—'));
      cargarSeccion('hay_evaluacion_admin');
    } else alert(data.message || 'Error');
  });
})();
</script>
