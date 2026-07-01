<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/exam/load.php';

$errorCarga = null;
try {
    $svc = new \HayExam\AnswerSheetService($pdo, dirname(__DIR__));
    $svc->getPlantelConfig();
} catch (Throwable $e) {
    $errorCarga = $e->getMessage();
}
?>
<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/examenes.css">

<div class="result-container exam-calificar">
  <div class="result-header">
    <h2><i class="fas fa-camera"></i> Calificar examen — Escanear hoja</h2>
    <p style="color:#666;margin-top:6px;">
      Use la hoja <strong>Answer Sheet v<?php echo (int) \HayExam\AnswerSheetLayout::VERSION; ?></strong>
      (con marcas de registro en las esquinas y bordes).
      Seleccione el grupo o alumnos individuales; revise y corrija las respuestas leídas antes de registrar.
    </p>
    <p style="margin-top:8px;">
      <a href="php/exam/descargar.php?tipo=hoja" target="_blank" class="btn-outline" style="display:inline-block;padding:6px 14px;">
        <i class="fas fa-print"></i> Imprimir hoja Answer Sheet
      </a>
    </p>
  </div>

  <?php if ($errorCarga): ?>
    <div class="exam-msg err" style="display:block;">
      <strong>Configure la base de datos.</strong><br>
      <?php echo htmlspecialchars($errorCarga); ?>
      <br><small>Ejecute <code>sql/exam_calificaciones_schema.sql</code> en phpMyAdmin.</small>
    </div>
  <?php else: ?>

  <div id="calif-msg" class="exam-msg"></div>

  <div class="calif-grid">
    <section class="calif-panel">
      <h3>1. Examen y alumnos</h3>

      <label for="input-codigo-examen">Código del examen</label>
      <div class="calif-buscar-row">
        <input type="text" id="input-codigo-examen" maxlength="20" placeholder="Ej. K7M2P"
          style="flex:1;padding:10px;border-radius:8px;border:1px solid #ccc;text-transform:uppercase;font-weight:600;letter-spacing:1px;">
        <button type="button" class="primary" id="btn-buscar-examen"><i class="fas fa-search"></i> Buscar</button>
      </div>
      <div id="exam-info" class="exam-info-box" style="display:none;"></div>

      <label for="sel-fase" id="lbl-fase" style="margin-top:12px;">Fase a registrar</label>
      <select id="sel-fase" disabled style="width:100%;padding:10px;border-radius:8px;border:1px solid #ccc;margin-bottom:10px;">
        <option value="">— Busque un examen primero —</option>
      </select>

      <label for="sel-grupo">Grupo</label>
      <div class="calif-buscar-row">
        <select id="sel-grupo" disabled style="flex:1;padding:10px;border-radius:8px;border:1px solid #ccc;">
          <option value="">— Busque un examen primero —</option>
        </select>
        <button type="button" id="btn-cargar-alumnos" disabled><i class="fas fa-users"></i> Cargar</button>
      </div>

      <div id="alumnos-toolbar" class="calif-alumnos-toolbar" style="display:none;">
        <button type="button" class="secondary" id="btn-sel-todos">Todos</button>
        <button type="button" class="secondary" id="btn-sel-pendientes">Solo pendientes</button>
        <button type="button" class="secondary" id="btn-sel-ninguno">Ninguno</button>
        <button type="button" class="primary" id="btn-iniciar-cola"><i class="fas fa-list-ol"></i> Evaluar seleccionados</button>
      </div>

      <div id="alumnos-lista-wrap" style="display:none;margin-top:8px;">
        <div class="calif-alumnos-list" id="alumnos-lista"></div>
      </div>

      <details style="margin-top:12px;">
        <summary style="cursor:pointer;font-weight:600;font-size:0.9rem;">Evaluar alumno(s) individual(es)</summary>
        <div style="margin-top:8px;">
          <label for="input-cn-uno">Agregar CN suelto (alumno que no presentó con el grupo)</label>
          <div style="display:flex;gap:8px;margin-top:4px;">
            <input type="text" id="input-cn-uno" maxlength="8" placeholder="CN"
              style="flex:1;padding:8px;border-radius:8px;border:1px solid #ccc;font-family:monospace;">
            <button type="button" id="btn-agregar-cn"><i class="fas fa-plus"></i> Agregar</button>
          </div>
          <textarea id="textarea-cola-cn" rows="2" placeholder="CN adicionales (uno por línea)"
            style="width:100%;padding:8px;border-radius:8px;border:1px solid #ccc;font-family:monospace;margin-top:6px;"></textarea>
        </div>
      </details>

      <div id="cola-estado" class="exam-info-box" style="display:none;margin-top:10px;">
        <strong id="cola-actual-label"></strong>
        <ul id="cola-lista" class="calif-cola-list"></ul>
      </div>

      <label for="input-cn-activo" style="margin-top:12px;">CN del alumno actual</label>
      <input type="text" id="input-cn-activo" maxlength="8"
        style="width:100%;padding:10px;border-radius:8px;border:1px solid #ccc;font-family:monospace;font-weight:600;">
    </section>

    <section class="calif-panel">
      <h3>2. Capturar hoja</h3>
      <div class="calif-cam-wrap">
        <video id="calif-video" autoplay playsinline muted style="width:100%;max-height:280px;background:#111;border-radius:8px;"></video>
        <canvas id="calif-preview" class="calif-preview" style="display:none;"></canvas>
      </div>
      <div class="calif-actions">
        <button type="button" class="primary" id="btn-cam-start"><i class="fas fa-video"></i> Activar cámara</button>
        <button type="button" id="btn-capture" disabled><i class="fas fa-camera"></i> Capturar y leer</button>
        <label class="btn-outline" style="cursor:pointer;margin:0;">
          <i class="fas fa-image"></i> Subir foto
          <input type="file" id="file-foto" accept="image/*" capture="environment" style="display:none;">
        </label>
      </div>
    </section>
  </div>

  <section class="calif-panel" id="panel-resultado" style="display:none;margin-top:16px;">
    <h3>3. Revisar respuestas leídas</h3>
    <p id="scan-alumno" class="calif-scan-alumno"></p>
    <p id="scan-resumen" class="calif-scan-resumen"></p>

    <div class="calif-editor-tabs">
      <button type="button" class="calif-tab active" data-tab="mc">Opción múltiple (41)</button>
      <button type="button" class="calif-tab" data-tab="writing">Writing</button>
      <button type="button" class="calif-tab" data-tab="speaking">Speaking</button>
    </div>

    <div id="editor-mc" class="calif-editor-pane active"></div>
    <div id="editor-writing" class="calif-editor-pane"></div>
    <div id="editor-speaking" class="calif-editor-pane"></div>

    <div style="margin-top:14px;">
      <button type="button" class="primary" id="btn-registrar"><i class="fas fa-save"></i> Registrar calificación</button>
      <button type="button" id="btn-saltar" style="margin-left:8px;">Saltar alumno</button>
    </div>
  </section>

  <section class="calif-panel" style="margin-top:16px;">
    <h3>Calificaciones registradas</h3>
    <div id="tabla-calif" style="font-size:0.9rem;color:#666;">Busque un examen para ver resultados.</div>
  </section>

  <?php endif; ?>
</div>

<script src="js/answer-sheet-scanner.js"></script>
<script>
(function () {
  const API = 'php/exam/calificar_api.php';
  const MC_OPTS = ['', 'A', 'B', 'C', 'D'];
  const RUB_OPTS = ['', 'A', 'B', 'C', 'D', 'E'];
  const MC_COUNT = 41;

  const msg = document.getElementById('calif-msg');
  const inputCodigo = document.getElementById('input-codigo-examen');
  const selFase = document.getElementById('sel-fase');
  const selGrupo = document.getElementById('sel-grupo');
  const examInfo = document.getElementById('exam-info');
  const video = document.getElementById('calif-video');
  const preview = document.getElementById('calif-preview');
  const panelRes = document.getElementById('panel-resultado');
  const inputCnActivo = document.getElementById('input-cn-activo');
  const colaEstado = document.getElementById('cola-estado');
  const colaLista = document.getElementById('cola-lista');
  const colaLabel = document.getElementById('cola-actual-label');

  let stream = null;
  let lastScan = null;
  let examActual = null;
  let colaAlumnos = [];
  let indiceCola = 0;
  let alumnosGrupo = [];
  let layoutData = null;
  let claveMc = {};

  function padCN(s) {
    s = String(s || '').replace(/\D/g, '');
    if (s.length > 5) s = s.slice(-5);
    while (s.length < 5) s = '0' + s;
    return s;
  }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function showMsg(t, ok) {
    if (!msg) return;
    msg.textContent = t;
    msg.className = 'exam-msg ' + (ok ? 'ok' : 'err');
    msg.style.display = 'block';
    setTimeout(function () { msg.style.display = 'none'; }, 9000);
  }

  function getExamenId() {
    return examActual ? examActual.id_examen : '';
  }

  function cnActual() {
    return padCN(inputCnActivo.value);
  }

  function actualizarVistaCola() {
    if (!colaAlumnos.length) {
      colaEstado.style.display = 'none';
      return;
    }
    colaEstado.style.display = 'block';
    colaLabel.textContent = 'Alumno ' + (indiceCola + 1) + ' de ' + colaAlumnos.length;
    colaLista.innerHTML = '';
    colaAlumnos.forEach(function (a, i) {
      const li = document.createElement('li');
      li.textContent = a.cn + ' — ' + (a.nombre || '') + (i < indiceCola ? ' ✓' : (i === indiceCola ? ' ← actual' : ''));
      if (i === indiceCola) li.className = 'actual';
      colaLista.appendChild(li);
    });
    const actual = colaAlumnos[indiceCola];
    if (actual) {
      inputCnActivo.value = actual.cn;
    }
  }

  function iniciarColaDesdeSeleccion() {
    const seleccionados = [];
    document.querySelectorAll('#alumnos-lista input[type=checkbox]:checked').forEach(function (cb) {
      const idx = parseInt(cb.dataset.idx, 10);
      if (alumnosGrupo[idx]) seleccionados.push(alumnosGrupo[idx]);
    });
    const extra = padCN(document.getElementById('input-cn-uno').value);
    const extras = String(document.getElementById('textarea-cola-cn').value || '')
      .split(/[\s,;]+/).map(padCN).filter(function (c) { return c && c !== '00000'; });
    if (extra && extra !== '00000') extras.push(extra);
    extras.forEach(function (cn) {
      if (!seleccionados.some(function (a) { return a.cn === cn; })) {
        seleccionados.push({ cn: cn, nombre: '(individual)', id_alumno: 0, calificado: false });
      }
    });
    if (!seleccionados.length) {
      showMsg('Seleccione al menos un alumno o agregue un CN individual.', false);
      return;
    }
    colaAlumnos = seleccionados;
    indiceCola = 0;
    actualizarVistaCola();
    showMsg('Cola lista: ' + colaAlumnos.length + ' alumno(s). Escanee la hoja del primero.', true);
  }

  function avanzarCola() {
    panelRes.style.display = 'none';
    lastScan = null;
    preview.style.display = 'none';
    video.style.display = 'block';
    if (!colaAlumnos.length) return;
    indiceCola++;
    if (indiceCola >= colaAlumnos.length) {
      showMsg('Cola terminada.', true);
      indiceCola = colaAlumnos.length;
      colaLabel.textContent = 'Cola completada (' + colaAlumnos.length + ' alumnos)';
      if (selGrupo.value) cargarAlumnosGrupo();
      return;
    }
    actualizarVistaCola();
    showMsg('Siguiente: CN ' + colaAlumnos[indiceCola].cn, true);
  }

  async function cargarClaveExamen() {
    const id = getExamenId();
    if (!id) { claveMc = {}; return; }
    try {
      const res = await fetch(API + '?action=clave&id_examen=' + encodeURIComponent(id), {
        headers: { 'X-Requested-With': 'fetch' }
      });
      const data = await res.json();
      if (data.status !== 'ok') { claveMc = {}; return; }
      claveMc = {};
      Object.keys(data.clave || {}).forEach(function (k) {
        claveMc[parseInt(k, 10)] = String(data.clave[k]).toUpperCase();
      });
    } catch (e) {
      claveMc = {};
    }
  }

  function mcStatus(q, val, scan) {
    const det = scan && scan.mc_details && scan.mc_details[q];
    if (det && det.ambiguous && !val) return 'ambig';
    if (!val) return 'empty';
    const clave = claveMc[q];
    if (!clave) return '';
    return val === clave ? 'correct' : 'wrong';
  }

  function contarMcDesdeObjeto(mc) {
    let ok = 0;
    let answered = 0;
    for (let q = 1; q <= MC_COUNT; q++) {
      const val = mc[q] || mc[String(q)] || '';
      if (val) {
        answered++;
        if (claveMc[q] && val === claveMc[q]) ok++;
      }
    }
    return { ok: ok, answered: answered, total: Object.keys(claveMc).length || MC_COUNT };
  }

  function actualizarResumenMc(mc) {
    const c = contarMcDesdeObjeto(mc);
    const el = document.getElementById('scan-resumen');
    if (!el) return;
    const maxClave = c.total;
    el.innerHTML = 'MC respondidas: <strong>' + c.answered + '</strong> / ' + MC_COUNT
      + ' · Correctas: <strong>' + c.ok + '</strong> / ' + maxClave
      + ' <span style="color:#666;">(' + (maxClave ? Math.round(c.ok / maxClave * 100) : 0) + '% MC)</span>'
      + ' — Revise las marcadas en <span class="calif-mc-wrong" style="padding:0 4px;border-radius:3px;">rojo</span> antes de registrar.';
  }

  function refrescarClasesMcItem(label, q, val, scan) {
    const det = scan && scan.mc_details && scan.mc_details[q];
    label.className = 'calif-mc-item';
    const st = mcStatus(q, val, scan);
    if (st) label.classList.add('calif-mc-' + st);
    if (det && det.ambiguous && !val) label.classList.add('calif-opt-ambig');
    if (!val && !(det && det.ambiguous)) label.classList.add('calif-opt-empty');
  }

  async function loadLayout() {
    const res = await fetch(API + '?action=layout', { headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();
    if (data.status !== 'ok') throw new Error(data.message || 'No se pudo cargar layout');
    layoutData = data.layout;
    AnswerSheetScanner.setLayout(layoutData);
  }

  async function cargarGrupos() {
    const res = await fetch(API + '?action=grupos', { headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();
    if (data.status !== 'ok') return;
    selGrupo.innerHTML = '<option value="">— Seleccione grupo —</option>';
    (data.grupos || []).forEach(function (g) {
      const o = document.createElement('option');
      o.value = g.id_grupo;
      o.textContent = (g.clave || '') + (g.esp_nombre ? ' · ' + g.esp_nombre : '') + ' (' + (g.num_alumnos || 0) + ')';
      selGrupo.appendChild(o);
    });
    selGrupo.disabled = false;
    document.getElementById('btn-cargar-alumnos').disabled = false;
  }

  function renderAlumnosLista() {
    const wrap = document.getElementById('alumnos-lista-wrap');
    const list = document.getElementById('alumnos-lista');
    const toolbar = document.getElementById('alumnos-toolbar');
    if (!alumnosGrupo.length) {
      wrap.style.display = 'none';
      toolbar.style.display = 'none';
      list.innerHTML = '<p style="color:#888;">Sin alumnos activos en este grupo.</p>';
      return;
    }
    wrap.style.display = 'block';
    toolbar.style.display = 'flex';
    let html = '<table class="calif-alumnos-table"><thead><tr><th></th><th>CN</th><th>Alumno</th><th>Estado</th></tr></thead><tbody>';
    alumnosGrupo.forEach(function (a, i) {
      const checked = !a.calificado ? ' checked' : '';
      const estado = a.calificado ? '<span class="tag-ok">Calificado</span>' : '<span class="tag-pend">Pendiente</span>';
      html += '<tr><td><input type="checkbox" data-idx="' + i + '"' + checked + '></td>';
      html += '<td>' + esc(a.cn) + '</td><td>' + esc(a.nombre_completo || a.nombre) + '</td><td>' + estado + '</td></tr>';
    });
    html += '</tbody></table>';
    list.innerHTML = html;
  }

  async function cargarAlumnosGrupo() {
    const gid = selGrupo.value;
    if (!gid) { showMsg('Seleccione un grupo.', false); return; }
    const url = API + '?action=alumnos_grupo&id_grupo=' + encodeURIComponent(gid)
      + (getExamenId() ? '&id_examen=' + encodeURIComponent(getExamenId()) : '');
    const res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();
    if (data.status !== 'ok') throw new Error(data.message || 'Error al cargar alumnos');
    alumnosGrupo = (data.alumnos || []).map(function (a) {
      return { cn: a.cn, nombre: a.nombre_completo, id_alumno: a.id_alumno, calificado: !!a.calificado };
    });
    renderAlumnosLista();
    showMsg('Alumnos cargados: ' + alumnosGrupo.length, true);
  }

  function llenarFases(fases) {
    selFase.innerHTML = '';
    if (!fases || !fases.length) {
      selFase.disabled = true;
      selFase.innerHTML = '<option value="">— Sin fases —</option>';
      return;
    }
    fases.forEach(function (f) {
      const o = document.createElement('option');
      o.value = f;
      o.textContent = f;
      selFase.appendChild(o);
    });
    selFase.disabled = false;
    if (fases.length === 1) selFase.value = fases[0];
  }

  async function buscarExamen() {
    const codigo = inputCodigo.value.trim().toUpperCase();
    if (!codigo) { showMsg('Escriba el código del examen.', false); return; }
    try {
      const res = await fetch(API + '?action=buscar_examen&id_examen=' + encodeURIComponent(codigo), {
        headers: { 'X-Requested-With': 'fetch' }
      });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message || 'Examen no encontrado');
      examActual = data.examen;
      inputCodigo.value = examActual.id_examen;
      document.getElementById('lbl-fase').textContent = examActual.es_nivel
        ? 'Nivel a registrar (A1, B1+, etc.)' : 'Fase a registrar';
      examInfo.style.display = 'block';
      examInfo.innerHTML = '<strong>' + esc(examActual.nombre_examen) + '</strong><br>'
        + '<span style="color:#666;">Código: ' + esc(examActual.id_examen) + ' · ' + esc(examActual.tipo) + '</span>';
      llenarFases(examActual.fases || []);
      await loadLayout();
      await cargarClaveExamen();
      await cargarGrupos();
      if (selGrupo.value) await cargarAlumnosGrupo();
      cargarTabla();
      showMsg('Examen listo. Seleccione grupo y alumnos a evaluar.', true);
    } catch (e) {
      examActual = null;
      examInfo.style.display = 'none';
      showMsg(e.message || 'Examen no encontrado', false);
    }
  }

  async function cargarTabla() {
    const id = getExamenId();
    const el = document.getElementById('tabla-calif');
    if (!id) { el.textContent = 'Busque un examen para ver resultados.'; return; }
    const res = await fetch(API + '?action=calificaciones&id_examen=' + encodeURIComponent(id), {
      headers: { 'X-Requested-With': 'fetch' }
    });
    const data = await res.json();
    if (data.status !== 'ok' || !data.items.length) {
      el.innerHTML = '<p>Sin calificaciones aún para este examen.</p>';
      return;
    }
    let html = '<table class="banco-table"><thead><tr><th>CN</th><th>Alumno</th><th>MC</th><th>Writing</th><th>Speaking</th><th>Final</th><th>Fase</th></tr></thead><tbody>';
    data.items.forEach(function (r) {
      html += '<tr><td>' + esc(r.numero_control) + '</td><td>' + esc(r.apellido + ', ' + r.nombre) + '</td>';
      html += '<td>' + r.correctas_mc + '/' + r.max_mc + ' (' + r.calificacion_mc + '%)</td>';
      html += '<td>' + r.calificacion_writing + '%</td><td>' + r.calificacion_speaking + '%</td>';
      html += '<td><strong>' + r.calificacion_final + '</strong></td><td>' + esc(r.fase) + '</td></tr>';
    });
    html += '</tbody></table>';
    el.innerHTML = html;
  }

  function mcSelectClass(q, scan, val) {
    const st = mcStatus(q, val || ((scan.mc && scan.mc[q]) || ''), scan);
    if (st === 'correct') return 'calif-mc-correct';
    if (st === 'wrong') return 'calif-mc-wrong';
    if (st === 'ambig') return 'calif-opt-ambig';
    if (st === 'empty') return 'calif-opt-empty';
    return '';
  }

  function buildMcEditor(scan) {
    const el = document.getElementById('editor-mc');
    const columns = (layoutData && layoutData.mc_columns && layoutData.mc_columns.length)
      ? layoutData.mc_columns
      : [{ title: 'Preguntas', from: 1, to: MC_COUNT }];
    let html = '';
    if (!Object.keys(claveMc).length) {
      html += '<p class="calif-clave-aviso">No se cargó la clave del examen; las respuestas correctas no se mostrarán.</p>';
    }
    html += '<div class="calif-mc-toolbar">';
    html += '<button type="button" class="secondary calif-filter-btn active" data-filter="all">Todas</button>';
    html += '<button type="button" class="secondary calif-filter-btn" data-filter="wrong">Incorrectas</button>';
    html += '<button type="button" class="secondary calif-filter-btn" data-filter="ambig">Dudosas</button>';
    html += '<button type="button" class="secondary calif-filter-btn" data-filter="empty">Sin respuesta</button>';
    html += '</div>';
    columns.forEach(function (col) {
      html += '<div class="calif-mc-section"><div class="calif-mc-section-hdr">' + esc(col.title) + '</div><div class="calif-mc-grid">';
      for (let q = col.from; q <= col.to; q++) {
        const val = (scan.mc && scan.mc[q]) || '';
        const cls = mcSelectClass(q, scan, val);
        const clave = claveMc[q] ? claveMc[q] : '—';
        const st = mcStatus(q, val, scan);
        html += '<label class="calif-mc-item ' + cls + '" data-q="' + q + '" data-status="' + st + '">';
        html += '<span class="q">' + q + '</span>';
        html += '<select data-mc="' + q + '">';
        MC_OPTS.forEach(function (o) {
          html += '<option value="' + o + '"' + (o === val ? ' selected' : '') + '>' + (o || '—') + '</option>';
        });
        html += '</select>';
        html += '<span class="calif-clave" title="Respuesta correcta del examen">' + clave + '</span>';
        html += '</label>';
      }
      html += '</div></div>';
    });
    html += '<p class="calif-legend">'
      + '<span class="calif-mc-correct" style="padding:0 4px;border-radius:3px;">Verde</span> = correcta · '
      + '<span class="calif-mc-wrong" style="padding:0 4px;border-radius:3px;">Rojo</span> = incorrecta · '
      + '<span class="calif-opt-ambig" style="padding:0 4px;border-radius:3px;">Amarillo</span> = lectura dudosa · '
      + '<span class="calif-opt-empty" style="padding:0 4px;border-radius:3px;">Gris</span> = sin respuesta · '
      + 'Letra a la derecha = clave del examen</p>';
    el.innerHTML = html;

    el.querySelectorAll('select[data-mc]').forEach(function (sel) {
      sel.addEventListener('change', function () {
        const q = parseInt(sel.dataset.mc, 10);
        const label = sel.closest('.calif-mc-item');
        refrescarClasesMcItem(label, q, sel.value, scan);
        label.dataset.status = mcStatus(q, sel.value, scan) || 'empty';
        const mc = collectScanFromEditor().mc;
        actualizarResumenMc(mc);
      });
    });

    el.querySelectorAll('.calif-filter-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        el.querySelectorAll('.calif-filter-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        const f = btn.dataset.filter;
        el.querySelectorAll('.calif-mc-item').forEach(function (item) {
          const st = item.dataset.status || '';
          let show = true;
          if (f === 'wrong') show = st === 'wrong';
          else if (f === 'ambig') show = st === 'ambig';
          else if (f === 'empty') show = st === 'empty';
          item.style.display = show ? '' : 'none';
        });
      });
    });

    actualizarResumenMc(scan.mc || {});
  }

  function buildRubricEditor(containerId, group, scan, aspects, numQ) {
    const el = document.getElementById(containerId);
    const values = scan[group] || {};
    const details = scan[group + '_details'] || {};
    let html = '';
    for (let q = 0; q < numQ; q++) {
      html += '<div class="calif-rub-block"><h4>' + group.charAt(0).toUpperCase() + group.slice(1) + ' ' + (q + 1) + '</h4>';
      html += '<table class="calif-rub-table"><thead><tr><th>Aspecto</th><th>Calificación (A–E)</th></tr></thead><tbody>';
      (aspects || []).forEach(function (label, i) {
        const val = (values[q] && values[q][i]) || '';
        const amb = details[q] && details[q][i] && details[q][i].ambiguous;
        html += '<tr><td>' + esc(label) + '</td><td>';
        html += '<select data-rub="' + group + '" data-q="' + q + '" data-aspect="' + i + '"' + (amb ? ' class="calif-opt-ambig"' : '') + ' style="min-width:80px;">';
        RUB_OPTS.forEach(function (opt) {
          html += '<option value="' + opt + '"' + (opt === val ? ' selected' : '') + '>' + (opt || '—') + '</option>';
        });
        html += '</select></td></tr>';
      });
      html += '</tbody></table></div>';
    }
    el.innerHTML = html;
  }

  function collectScanFromEditor() {
    const mc = {};
    document.querySelectorAll('#editor-mc select[data-mc]').forEach(function (sel) {
      const q = parseInt(sel.dataset.mc, 10);
      if (sel.value) mc[q] = sel.value;
    });
    function collectRub(group) {
      const out = {};
      document.querySelectorAll('select[data-rub="' + group + '"]').forEach(function (sel) {
        const q = parseInt(sel.dataset.q, 10);
        const i = parseInt(sel.dataset.aspect, 10);
        if (!out[q]) out[q] = {};
        if (sel.value) out[q][i] = sel.value;
      });
      return out;
    }
    return {
      mc: mc,
      writing: collectRub('writing'),
      speaking: collectRub('speaking'),
    };
  }

  async function mostrarResultado(scan) {
    lastScan = scan;
    const cn = cnActual();
    if (!cn || cn === '00000') {
      showMsg('Indique el CN del alumno antes de registrar.', false);
      return;
    }
    if (!Object.keys(claveMc).length) await cargarClaveExamen();
    panelRes.style.display = 'block';
    const nombre = colaAlumnos[indiceCola] ? colaAlumnos[indiceCola].nombre : '';
    document.getElementById('scan-alumno').textContent = 'CN: ' + cn + (nombre ? ' · ' + nombre : '') + ' · Examen: ' + getExamenId();
    document.getElementById('scan-resumen').textContent = 'Cargando clave del examen…';

    buildMcEditor(scan);
    const aspectsW = (layoutData && layoutData.writing_aspects) || [];
    const aspectsS = (layoutData && layoutData.speaking_aspects) || [];
    buildRubricEditor('editor-writing', 'writing', scan, aspectsW, layoutData?.writing_questions || 2);
    buildRubricEditor('editor-speaking', 'speaking', scan, aspectsS, layoutData?.speaking_questions || 2);

    preview.style.display = 'block';
    video.style.display = 'none';
  }

  async function procesarCanvas(scanResult) {
    if (!getExamenId()) { showMsg('Busque un examen primero.', false); return; }
    if (!cnActual() || cnActual() === '00000') {
      showMsg('Indique el CN del alumno o inicie la cola.', false);
      return;
    }
    preview.width = scanResult.warped.width;
    preview.height = scanResult.warped.height;
    preview.getContext('2d').drawImage(scanResult.warped, 0, 0);
    await mostrarResultado(scanResult.data);
    showMsg('Hoja leída (' + (scanResult.mcCount || 0) + ' MC). Revise las respuestas.', true);
  }

  document.querySelectorAll('.calif-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('.calif-tab').forEach(function (t) { t.classList.remove('active'); });
      document.querySelectorAll('.calif-editor-pane').forEach(function (p) { p.classList.remove('active'); });
      tab.classList.add('active');
      document.getElementById('editor-' + tab.dataset.tab).classList.add('active');
    });
  });

  document.getElementById('btn-buscar-examen').addEventListener('click', buscarExamen);
  inputCodigo.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); buscarExamen(); }
  });
  document.getElementById('btn-cargar-alumnos').addEventListener('click', function () { cargarAlumnosGrupo().catch(function (e) { showMsg(e.message, false); }); });
  selGrupo.addEventListener('change', function () { if (selGrupo.value) cargarAlumnosGrupo().catch(function () {}); });

  document.getElementById('btn-sel-todos').addEventListener('click', function () {
    document.querySelectorAll('#alumnos-lista input[type=checkbox]').forEach(function (cb) { cb.checked = true; });
  });
  document.getElementById('btn-sel-pendientes').addEventListener('click', function () {
    document.querySelectorAll('#alumnos-lista input[type=checkbox]').forEach(function (cb) {
      const idx = parseInt(cb.dataset.idx, 10);
      cb.checked = alumnosGrupo[idx] && !alumnosGrupo[idx].calificado;
    });
  });
  document.getElementById('btn-sel-ninguno').addEventListener('click', function () {
    document.querySelectorAll('#alumnos-lista input[type=checkbox]').forEach(function (cb) { cb.checked = false; });
  });
  document.getElementById('btn-iniciar-cola').addEventListener('click', iniciarColaDesdeSeleccion);

  document.getElementById('btn-agregar-cn').addEventListener('click', function () {
    const cn = padCN(document.getElementById('input-cn-uno').value);
    if (!cn || cn === '00000') return;
    const ta = document.getElementById('textarea-cola-cn');
    const actual = ta.value.trim();
    ta.value = actual ? actual + '\n' + cn : cn;
    document.getElementById('input-cn-uno').value = '';
  });

  document.getElementById('btn-cam-start').addEventListener('click', async function () {
    try {
      if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }
      });
      video.srcObject = stream;
      document.getElementById('btn-capture').disabled = false;
    } catch (e) {
      showMsg('No se pudo acceder a la cámara: ' + e.message, false);
    }
  });

  document.getElementById('btn-capture').addEventListener('click', async function () {
    try {
      await loadLayout();
      const scanResult = await AnswerSheetScanner.scanVideoFrame(video);
      await procesarCanvas(scanResult);
    } catch (e) {
      showMsg('Error al leer: ' + e.message, false);
    }
  });

  document.getElementById('file-foto').addEventListener('change', async function () {
    if (!this.files[0]) return;
    try {
      await loadLayout();
      const scanResult = await AnswerSheetScanner.scanFile(this.files[0]);
      await procesarCanvas(scanResult);
    } catch (e) {
      showMsg('Error al leer foto: ' + e.message, false);
    }
    this.value = '';
  });

  document.getElementById('btn-registrar').addEventListener('click', async function () {
    if (!lastScan || !getExamenId()) return;
    if (!selFase.value) { showMsg('Seleccione la fase a registrar.', false); return; }
    const cn = cnActual();
    if (!cn || cn === '00000') { showMsg('Indique el CN del alumno.', false); return; }
    const edited = collectScanFromEditor();
    const body = {
      id_examen: getExamenId(),
      fase: selFase.value,
      numero_control: cn,
      mc: edited.mc,
      writing: edited.writing,
      speaking: edited.speaking,
    };
    try {
      const res = await fetch(API + '?action=procesar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      const r = data.resultado;
      showMsg(r.alumno + ' — Final: ' + r.calificacion_final + ' (MC ' + r.correctas_mc + '/' + r.max_mc + ')', true);
      cargarTabla();
      avanzarCola();
    } catch (e) {
      showMsg(e.message || 'Error al registrar', false);
    }
  });

  document.getElementById('btn-saltar').addEventListener('click', avanzarCola);

  loadLayout().catch(function () {});
})();
</script>
