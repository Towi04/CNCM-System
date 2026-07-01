<?php
require_once __DIR__ . '/../config.php';
if (!ubicacion_puede_evaluar()) {
    echo '<div class="alert">Solo coordinación puede evaluar exámenes de ubicación.</div>';
    return;
}

$idDetalle = (int) ($_GET['id'] ?? 0);
$filtroEstado = $_GET['estado'] ?? 'pendiente';
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/ubicacion.css">

<div class="catalog-wrap ub-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-map-signs"></i> Examen de ubicación</h2>
  </div>

  <p class="ub-hint">
    Recepción solicita el examen o lo asigna al inscribir con opción <strong>Ubicación</strong>; usted autoriza el <strong>nivel</strong> y los <strong>grupos</strong> donde puede inscribirse.
    Hasta entonces recepción no puede asignar grupo en esa especialidad.
  </p>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Estado</label>
      <select id="ub-filtro-estado">
        <option value="">Todos</option>
        <?php foreach (ubicacion_estados_etiquetas() as $k => $lbl): ?>
          <option value="<?php echo htmlspecialchars($k); ?>"<?php echo $filtroEstado === $k ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($lbl); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="button" class="primary" id="btn-ub-listar">Actualizar</button>
  </div>

  <div id="ub-msg" class="catalog-alert" style="display:none;"></div>

  <div class="ub-layout">
    <div class="ub-lista" id="ub-lista"></div>

    <div class="ub-editor" id="ub-editor"<?php echo $idDetalle > 0 ? '' : ' hidden'; ?>>
      <h3 id="ub-editor-titulo">Evaluar ubicación</h3>
      <input type="hidden" id="ub-id" value="<?php echo $idDetalle; ?>">

      <div id="ub-alumno-info" class="ub-alumno-info"></div>

      <div class="catalog-form-grid">
        <div>
          <label>Nivel detectado</label>
          <select id="ub-nivel" style="width:100%; padding:8px;"></select>
          <input type="text" id="ub-nivel-otro" placeholder="Otro nivel" style="width:100%; margin-top:6px; display:none;">
        </div>
        <div class="full-width" style="grid-column:1/-1;">
          <label>Observaciones para recepción</label>
          <textarea id="ub-obs" rows="2" style="width:100%;"></textarea>
        </div>
      </div>

      <h4>Grupos autorizados para inscripción</h4>
      <p style="font-size:0.85rem; color:#666;">Marque todos los grupos en los que recepción puede inscribir a este alumno.</p>
      <div id="ub-grupos-checks" class="ub-grupos-checks"></div>

      <div style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap;">
        <button type="button" class="primary" id="btn-ub-autorizar">Autorizar e informar a recepción</button>
        <button type="button" class="secondary" id="btn-ub-rechazar">Rechazar (inscripción normal A1)</button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  const api = 'php/ubicacion_api.php';
  const msg = document.getElementById('ub-msg');
  const editor = document.getElementById('ub-editor');
  let items = [];

  function show(t, ok) {
    msg.style.display = 'block';
    msg.className = 'catalog-alert catalog-alert--' + (ok ? 'ok' : 'error');
    msg.textContent = t;
  }

  function renderLista() {
    const box = document.getElementById('ub-lista');
    if (!items.length) {
      box.innerHTML = '<p style="color:#888;">No hay registros con este filtro.</p>';
      return;
    }
    box.innerHTML = items.map((it) => {
      const badge = 'ub-badge ub-badge--' + it.estado;
      const grps = (it.grupos_autorizados || []).map((g) => g.clave).join(', ');
      return '<article class="ub-card' + (parseInt(document.getElementById('ub-id').value, 10) === parseInt(it.id_ubicacion, 10) ? ' is-active' : '') + '" data-id="' + it.id_ubicacion + '">' +
        '<span class="' + badge + '">' + (it.estado) + '</span>' +
        '<strong>' + escapeHtml(it.alumno_nombre) + '</strong> <span style="color:#888;">#' + escapeHtml(it.numero_control || '') + '</span>' +
        '<div style="font-size:0.85rem; color:#555;">' + escapeHtml(it.esp_nombre) +
        (it.nivel_detectado ? ' · Nivel ' + escapeHtml(it.nivel_detectado) : '') + '</div>' +
        (grps ? '<div class="ub-card-grupos">Grupos: ' + escapeHtml(grps) + '</div>' : '') +
        '<button type="button" class="secondary ub-btn-abrir" style="margin-top:8px;">Abrir</button></article>';
    }).join('');
    box.querySelectorAll('.ub-btn-abrir, .ub-card').forEach((el) => {
      el.addEventListener('click', (e) => {
        if (e.target.classList.contains('ub-btn-abrir') || e.target.closest('.ub-card')) {
          const card = e.target.closest('.ub-card');
          if (card) cargarDetalle(parseInt(card.dataset.id, 10));
        }
      });
    });
  }

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  async function listar() {
    const est = document.getElementById('ub-filtro-estado').value;
    const r = await fetch(api + '?action=listar&estado=' + encodeURIComponent(est));
    const d = await r.json();
    if (d.status !== 'ok') { show(d.message, false); return; }
    items = d.items || [];
    renderLista();
  }

  async function cargarDetalle(id) {
    document.getElementById('ub-id').value = id;
    editor.hidden = false;
    const r = await fetch(api + '?action=detalle&id=' + id);
    const d = await r.json();
    if (d.status !== 'ok') { show(d.message, false); return; }
    const u = d.ubicacion;
    document.getElementById('ub-editor-titulo').textContent = 'Evaluar — ' + u.alumno_nombre;
    document.getElementById('ub-alumno-info').innerHTML =
      '<p><strong>Especialidad:</strong> ' + escapeHtml(u.esp_nombre) +
      ' · <a href="#" data-seccion="alumno_detalle" data-query="id=' + u.id_alumno + '">Ver ficha alumno</a></p>' +
      (u.observaciones ? '<p style="color:#666;">' + escapeHtml(u.observaciones) + '</p>' : '');

    const sel = document.getElementById('ub-nivel');
    sel.innerHTML = '<option value="">— Seleccionar —</option>';
    (d.niveles || []).forEach((n) => {
      const o = document.createElement('option');
      o.value = n;
      o.textContent = n;
      if (u.nivel_detectado === n) o.selected = true;
      sel.appendChild(o);
    });
    const oOtro = document.createElement('option');
    oOtro.value = '__otro__';
    oOtro.textContent = 'Otro…';
    sel.appendChild(oOtro);

    const checks = document.getElementById('ub-grupos-checks');
    const selIds = (d.grupos_autorizados || []).map((g) => parseInt(g.id_grupo, 10));
    checks.innerHTML = (d.grupos_disponibles || []).map((g) => {
      const checked = selIds.includes(parseInt(g.id_grupo, 10)) ? ' checked' : '';
      return '<label class="ub-grupo-check"><input type="checkbox" value="' + g.id_grupo + '"' + checked + '> ' +
        '<strong>' + escapeHtml(g.clave) + '</strong> ' +
        (g.clave_fase ? '<span style="color:#666;">(' + escapeHtml(g.clave_fase) + ')</span>' : '') +
        '</label>';
    }).join('');

    document.getElementById('ub-obs').value = '';
    renderLista();

    if (u.estado !== 'pendiente') {
      document.getElementById('btn-ub-autorizar').disabled = true;
      document.getElementById('btn-ub-rechazar').disabled = u.estado !== 'pendiente';
    } else {
      document.getElementById('btn-ub-autorizar').disabled = false;
      document.getElementById('btn-ub-rechazar').disabled = false;
    }
  }

  document.getElementById('ub-nivel').onchange = () => {
    document.getElementById('ub-nivel-otro').style.display =
      document.getElementById('ub-nivel').value === '__otro__' ? 'block' : 'none';
  };

  document.getElementById('btn-ub-listar').onclick = listar;

  document.getElementById('btn-ub-autorizar').onclick = async () => {
    let nivel = document.getElementById('ub-nivel').value;
    if (nivel === '__otro__') nivel = document.getElementById('ub-nivel-otro').value.trim();
    const grupos = Array.from(document.querySelectorAll('#ub-grupos-checks input:checked')).map((c) => c.value);
    const fd = new FormData();
    fd.append('action', 'autorizar');
    fd.append('id_ubicacion', document.getElementById('ub-id').value);
    fd.append('nivel_detectado', nivel);
    fd.append('observaciones', document.getElementById('ub-obs').value);
    grupos.forEach((g) => fd.append('id_grupos[]', g));
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    show(d.message, d.status === 'ok');
    if (d.status === 'ok') { listar(); editor.hidden = true; }
  };

  document.getElementById('btn-ub-rechazar').onclick = async () => {
    const motivo = prompt('Motivo del rechazo (opcional):', 'Continúa en nivel inicial');
    if (motivo === null) return;
    const fd = new FormData();
    fd.append('action', 'rechazar');
    fd.append('id_ubicacion', document.getElementById('ub-id').value);
    fd.append('motivo', motivo);
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    show(d.message, d.status === 'ok');
    if (d.status === 'ok') { listar(); editor.hidden = true; }
  };

  document.getElementById('ub-alumno-info').addEventListener('click', (e) => {
    const a = e.target.closest('[data-seccion]');
    if (a) {
      e.preventDefault();
      cargarSeccion(a.dataset.seccion, (a.dataset.query || '').replace(/^id=/, 'id='));
    }
  });

  listar();
  <?php if ($idDetalle > 0): ?>cargarDetalle(<?php echo $idDetalle; ?>);<?php endif; ?>
})();
</script>
