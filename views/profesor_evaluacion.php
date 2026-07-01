<?php
require_once __DIR__ . '/../config.php';
if (!profesor_eval_puede_gestionar()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}

$idUsuario = (int) ($_GET['id_usuario'] ?? 0);
$anio = (int) ($_GET['anio'] ?? (int) date('Y'));
$mes = (int) ($_GET['mes'] ?? (int) date('n'));
$idPlantel = plantel_scope_id($pdo);

if ($idUsuario <= 0) {
    echo '<div class="catalog-alert catalog-alert--error">Seleccione un profesor desde la lista.</div>';
    return;
}

$st = $pdo->prepare(
    "SELECT id_usuario, nombre, apellido, email FROM usuarios
     WHERE id_usuario = ? AND rol = 'profesor' LIMIT 1"
);
$st->execute([$idUsuario]);
$prof = $st->fetch(PDO::FETCH_ASSOC);
if (!$prof) {
    echo '<div class="catalog-alert catalog-alert--error">Profesor no encontrado.</div>';
    return;
}

$calc = profesor_eval_calcular_metricas_auto($pdo, $idUsuario, $idPlantel, $anio, $mes);
$metricas = $calc['metricas'];
$guardada = profesor_eval_obtener($pdo, $idUsuario, $idPlantel, $anio, $mes);
$cerrada = $guardada && ($guardada['estado'] ?? '') === 'cerrado';

$puntosAutoGuardados = [];
$puntosManualGuardados = [];
if ($guardada) {
    foreach ($metricas as $cod => $m) {
        if (isset($guardada['metricas_auto'][$cod]['puntos'])) {
            $puntosAutoGuardados[$cod] = (int) $guardada['metricas_auto'][$cod]['puntos'];
        }
    }
    $puntosManualGuardados = is_array($guardada['criterios_manual']) ? $guardada['criterios_manual'] : [];
}

$nombreProf = trim(($prof['nombre'] ?? '') . ' ' . ($prof['apellido'] ?? ''));
$meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/profesor_eval.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <button type="button" class="secondary" onclick="cargarSeccion('calificar_usuario', 'anio=<?php echo $anio; ?>&mes=<?php echo $mes; ?>')">← Lista de profesores</button>
    <h2>Evaluación: <?php echo htmlspecialchars($nombreProf); ?></h2>
    <p class="pe-subtitle"><?php echo htmlspecialchars($meses[$mes] ?? (string) $mes); ?> <?php echo $anio; ?>
      · <?php echo (int) $calc['grupos']; ?> grupo(s)</p>
  </div>

  <div id="msg-pe" class="catalog-alert" style="display:none;"></div>

  <?php if ($cerrada): ?>
    <div class="catalog-alert catalog-alert--ok">Evaluación cerrada. Total: <strong><?php echo (int) $guardada['puntos_total']; ?></strong> — <?php echo htmlspecialchars($guardada['nivel'] ?? ''); ?></div>
  <?php endif; ?>

  <form id="form-prof-eval" data-no-global-ajax="1">
    <input type="hidden" name="action" value="guardar">
    <input type="hidden" name="id_usuario" value="<?php echo $idUsuario; ?>">
    <input type="hidden" name="anio" value="<?php echo $anio; ?>">
    <input type="hidden" name="mes" value="<?php echo $mes; ?>">
    <input type="hidden" name="metricas_auto_json" id="metricas-auto-json" value="">

    <section class="pe-section">
      <h3>Métricas automáticas (sistema HAY)</h3>
      <p class="pe-hint">Puede ajustar los puntos sugeridos antes de guardar.</p>
      <div class="pe-grid">
        <?php foreach (profesor_eval_criterios_auto() as $c):
          $cod = $c['codigo'];
          $m = $metricas[$cod] ?? [];
          $sug = (int) ($m['puntos_sugeridos'] ?? 0);
          $val = $puntosAutoGuardados[$cod] ?? $sug;
        ?>
        <div class="pe-card">
          <div class="pe-card__title"><?php echo htmlspecialchars($c['nombre']); ?></div>
          <div class="pe-card__pct"><?php echo htmlspecialchars((string) ($m['valor_pct'] ?? 0)); ?>%</div>
          <div class="pe-card__det"><?php echo htmlspecialchars($m['detalle'] ?? ''); ?></div>
          <label>Puntos (máx. <?php echo (int) $c['maximo']; ?>)
            <input type="number" name="auto_<?php echo htmlspecialchars($cod); ?>"
              class="pe-input-auto" data-cod="<?php echo htmlspecialchars($cod); ?>"
              min="0" max="<?php echo (int) $c['maximo']; ?>" value="<?php echo $val; ?>"
              <?php echo $cerrada ? 'readonly' : ''; ?>>
          </label>
          <button type="button" class="secondary pe-usar-sug" data-cod="<?php echo htmlspecialchars($cod); ?>"
            data-val="<?php echo $sug; ?>" <?php echo $cerrada ? 'disabled' : ''; ?>>Usar sugerido (<?php echo $sug; ?>)</button>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <?php foreach (profesor_eval_rubrica_categorias() as $catKey => $cat): ?>
    <section class="pe-section pe-section--cat">
      <h3><?php echo htmlspecialchars($cat['titulo']); ?></h3>
      <div class="pe-grid pe-grid--manual">
        <?php foreach ($cat['items'] as $c):
          $cod = $c['codigo'];
          $val = (int) ($puntosManualGuardados[$cod] ?? 0);
        ?>
        <label class="pe-manual-row">
          <span><?php echo htmlspecialchars($c['nombre']); ?> <small>(0–<?php echo (int) $c['maximo']; ?>)</small></span>
          <input type="number" name="manual_<?php echo htmlspecialchars($cod); ?>"
            class="pe-input-manual" min="0" max="<?php echo (int) $c['maximo']; ?>"
            value="<?php echo $val; ?>" <?php echo $cerrada ? 'readonly' : ''; ?>>
        </label>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>

    <section class="pe-section">
      <label>Observaciones
        <textarea name="observaciones" rows="3" style="width:100%;" <?php echo $cerrada ? 'readonly' : ''; ?>><?php
          echo htmlspecialchars($guardada['observaciones'] ?? '');
        ?></textarea>
      </label>
    </section>

    <div class="pe-total" id="pe-total-live">
      <?php
      $maxPts = profesor_eval_max_posible();
      if ($guardada): ?>
        Total: <strong><?php echo (int) $guardada['puntos_total']; ?></strong> / <?php echo $maxPts; ?>
        — <?php echo htmlspecialchars($guardada['nivel'] ?? ''); ?>
      <?php else: ?>
        Total estimado: <strong id="pe-total-num">0</strong> / <?php echo $maxPts; ?>
        (<span id="pe-pct-num">0</span>%) — <span id="pe-nivel-num">—</span>
      <?php endif; ?>
    </div>

    <?php if (!$cerrada): ?>
    <div class="pe-actions">
      <button type="submit" class="secondary" name="cerrar" value="0">Guardar borrador</button>
      <button type="button" class="primary" id="btn-pe-cerrar">Cerrar evaluación</button>
      <button type="button" class="secondary" id="btn-pe-recalc">Recalcular métricas</button>
    </div>
    <?php endif; ?>
  </form>
</div>

<script>
(function () {
  const metricasBase = <?php echo json_encode($metricas, JSON_UNESCAPED_UNICODE); ?>;
  const maxPosible = <?php echo (int) profesor_eval_max_posible(); ?>;
  const form = document.getElementById('form-prof-eval');
  const msg = document.getElementById('msg-pe');
  const hidJson = document.getElementById('metricas-auto-json');

  function buildMetricasJson() {
    const out = {};
    Object.keys(metricasBase).forEach((cod) => {
      const inp = form.querySelector('[name="auto_' + cod + '"]');
      out[cod] = Object.assign({}, metricasBase[cod], {
        puntos: inp ? parseInt(inp.value, 10) || 0 : 0,
      });
    });
    return out;
  }

  function updateTotalLive() {
    let auto = 0;
    form.querySelectorAll('.pe-input-auto').forEach((inp) => { auto += parseInt(inp.value, 10) || 0; });
    let manual = 0;
    form.querySelectorAll('.pe-input-manual').forEach((inp) => { manual += parseInt(inp.value, 10) || 0; });
    const total = auto + manual;
    const el = document.getElementById('pe-total-num');
    const nv = document.getElementById('pe-nivel-num');
    const pctEl = document.getElementById('pe-pct-num');
    const pct = maxPosible > 0 ? Math.round(1000 * total / maxPosible) / 10 : 0;
    if (el) el.textContent = String(total);
    if (pctEl) pctEl.textContent = String(pct);
    if (nv) {
      let nivel = 'Mejorable (B+)';
      if (pct >= 90) nivel = 'Excelente (D)';
      else if (pct >= 75) nivel = 'Muy bueno (C+)';
      else if (pct >= 60) nivel = 'Bueno (C)';
      else if (pct >= 45) nivel = 'Regular (C-)';
      nv.textContent = nivel;
    }
  }

  function syncJson() {
    if (hidJson) hidJson.value = JSON.stringify(buildMetricasJson());
  }

  document.querySelectorAll('.pe-usar-sug').forEach((btn) => {
    btn.addEventListener('click', () => {
      const inp = form.querySelector('[name="auto_' + btn.dataset.cod + '"]');
      if (inp) inp.value = btn.dataset.val;
      updateTotalLive();
      syncJson();
    });
  });

  form.querySelectorAll('input[type="number"]').forEach((inp) => {
    inp.addEventListener('input', () => { updateTotalLive(); syncJson(); });
  });

  document.getElementById('btn-pe-recalc')?.addEventListener('click', async () => {
    const q = new URLSearchParams({
      action: 'metricas',
      id_usuario: form.querySelector('[name="id_usuario"]').value,
      anio: form.querySelector('[name="anio"]').value,
      mes: form.querySelector('[name="mes"]').value,
    });
    try {
      const { data } = await hayFetchJson('php/profesor_eval_api.php?' + q.toString());
      if (data.status !== 'ok') throw new Error(data.message);
      Object.keys(data.data.metricas || {}).forEach((cod) => {
        const m = data.data.metricas[cod];
        metricasBase[cod] = m;
        const inp = form.querySelector('[name="auto_' + cod + '"]');
        if (inp) inp.value = m.puntos_sugeridos;
        const card = inp?.closest('.pe-card');
        if (card) {
          const pct = card.querySelector('.pe-card__pct');
          const det = card.querySelector('.pe-card__det');
          if (pct) pct.textContent = m.valor_pct + '%';
          if (det) det.textContent = m.detalle;
        }
      });
      updateTotalLive();
      syncJson();
      if (msg) { msg.className = 'catalog-alert catalog-alert--ok'; msg.textContent = 'Métricas actualizadas'; msg.style.display = 'block'; }
    } catch (e) {
      if (msg) { msg.className = 'catalog-alert catalog-alert--error'; msg.textContent = e.message; msg.style.display = 'block'; }
    }
  });

  async function enviar(cerrar) {
    syncJson();
    const fd = new FormData(form);
    if (cerrar) fd.set('cerrar', '1');
    try {
      const { data } = await hayFetchJson('php/profesor_eval_api.php', { method: 'POST', body: fd });
      if (msg) {
        msg.style.display = 'block';
        msg.className = 'catalog-alert ' + (data.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');
        msg.textContent = data.message || '';
      }
      if (data.status === 'ok' && data.seccion) {
        cargarSeccion(data.seccion, data.query || '');
      }
    } catch (e) {
      if (msg) { msg.style.display = 'block'; msg.className = 'catalog-alert catalog-alert--error'; msg.textContent = e.message; }
    }
  }

  form?.addEventListener('submit', (e) => { e.preventDefault(); enviar(false); });
  document.getElementById('btn-pe-cerrar')?.addEventListener('click', () => {
    if (confirm('¿Cerrar esta evaluación? Podrá consultarla pero no editarla sin reabrir.')) enviar(true);
  });

  syncJson();
  updateTotalLive();
})();
</script>
