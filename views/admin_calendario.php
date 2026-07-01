<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_id']) || !calendario_puede_ver_menu()) {
    echo '<div class="alert">No tiene permiso para editar calendarios escolares.</div>';
    return;
}

$anio = (int) ($_GET['anio'] ?? date('Y'));
$mes = (int) ($_GET['mes'] ?? date('n'));
$modeloInicial = calendario_modelo_normalizar($_GET['modelo'] ?? (calendario_modelos_editables_usuario()[0] ?? 'regular'));
$nombresMes = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$modelosEdit = calendario_modelos_editables_usuario();
$modelosTodos = calendario_modelos_lectivos();
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/admin_calendario.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-calendar-alt"></i> Calendarios escolares</h2>
    <span style="display:flex; gap:12px; flex-wrap:wrap;">
      <?php if (calendario_puede_ver_consulta($pdo)): ?>
        <a href="#" data-seccion="calendario_consulta" style="font-size:0.9rem;">
          <i class="fas fa-calendar-check"></i> Vista combinada
        </a>
      <?php endif; ?>
      <?php if (calendario_puede_editar_administrativo()): ?>
        <a href="#" class="secondary" data-seccion="admin_calendario_admin" style="font-size:0.9rem;">
          <i class="fas fa-briefcase"></i> Calendario administrativo
        </a>
      <?php endif; ?>
    </span>
  </div>

  <div class="cal-tabs" id="cal-tabs">
    <?php foreach ($modelosTodos as $slug => $label): ?>
      <?php if (!in_array($slug, $modelosEdit, true)) continue; ?>
      <button type="button" class="cal-tab<?php echo $slug === $modeloInicial ? ' is-active' : ''; ?>"
        data-modelo="<?php echo htmlspecialchars($slug); ?>">
        <?php echo htmlspecialchars($label); ?>
      </button>
    <?php endforeach; ?>
  </div>

  <p id="cal-ayuda" style="color:#666; margin-top:0; max-width:920px;"></p>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Año</label>
      <input type="number" id="cal-anio" value="<?php echo $anio; ?>" min="2020" max="2040">
    </div>
    <div class="field">
      <label>Mes</label>
      <select id="cal-mes">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?php echo $m; ?>"<?php echo $m === $mes ? ' selected' : ''; ?>><?php echo $nombresMes[$m]; ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <button type="button" class="primary" id="btn-cal-cargar">Ver mes</button>
    <button type="button" class="secondary" id="btn-cal-sugerencias">Importar sugerencias</button>
    <button type="button" class="primary" id="btn-cal-publicar">Publicar este calendario</button>
  </div>

  <div id="cal-estado" class="catalog-alert catalog-alert--warn" style="display:none;"></div>
  <div id="cal-msg" class="catalog-alert" style="display:none;"></div>

  <div class="cal-legend" id="cal-legend"></div>

  <div class="cal-grid-wrap">
    <div class="cal-grid-header">
      <span>Dom</span><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span>
    </div>
    <div class="cal-grid" id="cal-grid"></div>
  </div>

  <div class="cal-editor" id="cal-editor" hidden>
    <h3 id="cal-editor-titulo">Editar día</h3>
    <input type="hidden" id="ed-fecha">
    <div class="catalog-form-grid">
      <div>
        <label>Tipo de día</label>
        <select id="ed-tipo"></select>
      </div>
      <div>
        <label>Aplica a horario</label>
        <select id="ed-aplica">
          <option value="todos">Todos</option>
          <option value="entre_semana">Entre semana (M/V)</option>
          <option value="sabado">Solo sábado (S)</option>
          <option value="domingo">Solo domingo (D)</option>
        </select>
      </div>
      <div>
        <label>Etiqueta</label>
        <input type="text" id="ed-etiqueta" placeholder="Ej. Semana Santa">
      </div>
      <div id="ed-recuperacion-wrap">
        <label>Fecha recuperación (entre semana)</label>
        <input type="date" id="ed-recuperacion">
        <p style="font-size:0.8rem; color:#666; margin:4px 0 0;">Solo calendario regular: clase del asueto en otro día.</p>
      </div>
    </div>
    <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
      <button type="button" class="primary" id="btn-ed-guardar">Guardar día</button>
      <button type="button" id="btn-ed-quitar">Quitar marca</button>
    </div>
  </div>

  <div class="cal-rango-box">
    <h3 style="margin:0 0 10px; font-size:1rem;"><i class="fas fa-calendar-week"></i> Marcar rango</h3>
    <div class="catalog-form-grid">
      <div><label>Desde</label><input type="date" id="rg-inicio"></div>
      <div><label>Hasta</label><input type="date" id="rg-fin"></div>
      <div>
        <label>Tipo</label>
        <select id="rg-tipo"></select>
      </div>
      <div><label>Etiqueta</label><input type="text" id="rg-etiqueta" value="Vacaciones"></div>
    </div>
    <button type="button" class="primary" style="margin-top:10px;" id="btn-rg-guardar">Aplicar al rango</button>
  </div>
</div>

<script>
(function() {
  const api = 'php/calendario_api.php';
  let modelo = <?php echo json_encode($modeloInicial); ?>;
  let permiteRecuperacion = modelo === 'regular';
  const msg = document.getElementById('cal-msg');
  const estado = document.getElementById('cal-estado');
  const hoy = new Date().toISOString().slice(0, 10);

  const colores = {
    lectivo: '#ffffff',
    cierre_plantel: '#37474f',
    sin_clase_abierto: '#fff8e1',
    asueto: '#f3e5f5',
    vacacion_sabado: '#e3f2fd',
    vacacion_cuatrimestre: '#e0f2f1',
  };

  const ayudas = {
    regular: 'Inglés, cómputo y kids: asuetos entre semana pueden recuperarse; vacaciones de sábado recorren la semana.',
    prepa_escolarizada: 'Prepa escolarizada (PE): asuetos sin recuperación; marque vacaciones entre cuatrimestres.',
    prepa_abierta: 'Prepa abierta (PA): mismo criterio que escolarizada — sin recuperación de clase por asueto.',
  };

  function show(t, ok) {
    msg.style.display = 'block';
    msg.className = 'catalog-alert catalog-alert--' + (ok ? 'ok' : 'error');
    msg.textContent = t;
  }

  function actualizarAyuda() {
    document.getElementById('cal-ayuda').textContent = ayudas[modelo] || '';
    document.getElementById('btn-cal-sugerencias').style.display = modelo === 'regular' ? '' : 'none';
  }

  function llenarSelectTipos(sel, tipos, incluirLectivo) {
    sel.innerHTML = '';
    if (incluirLectivo) {
      const o0 = document.createElement('option');
      o0.value = 'lectivo';
      o0.textContent = 'Día lectivo normal (quitar marca)';
      sel.appendChild(o0);
    }
    Object.keys(tipos || {}).forEach((k) => {
      const o = document.createElement('option');
      o.value = k;
      o.textContent = tipos[k];
      sel.appendChild(o);
    });
  }

  document.querySelectorAll('.cal-tab').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.cal-tab').forEach((b) => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      modelo = btn.dataset.modelo;
      permiteRecuperacion = modelo === 'regular';
      actualizarAyuda();
      cargar();
    });
  });

  function pintarLeyenda(tipos) {
    const leg = document.getElementById('cal-legend');
    leg.innerHTML = '';
    Object.keys(colores).forEach((k) => {
      if (k === 'lectivo') return;
      if (!tipos[k] && k !== 'vacacion_cuatrimestre') return;
      const sp = document.createElement('span');
      sp.innerHTML = '<i style="background:' + colores[k] + '"></i> ' + (tipos[k] || k);
      leg.appendChild(sp);
    });
  }

  function renderGrid(dias) {
    const grid = document.getElementById('cal-grid');
    grid.innerHTML = '';
    if (!dias.length) return;
    const primerDow = dias[0].dow;
    for (let i = 0; i < primerDow; i++) {
      const empty = document.createElement('div');
      empty.className = 'cal-day is-empty';
      grid.appendChild(empty);
    }
    dias.forEach((d) => {
      const cell = document.createElement('button');
      cell.type = 'button';
      cell.className = 'cal-day cal-day--' + d.tipo + (d.fecha === hoy ? ' is-today' : '');
      cell.dataset.fecha = d.fecha;
      cell.innerHTML = '<div class="cal-day__num">' + d.dia + '</div>' +
        (d.tipo !== 'lectivo' ? '<div class="cal-day__tag">' + (d.etiqueta || d.tipo) + '</div>' : '');
      cell.onclick = () => abrirEditor(d);
      grid.appendChild(cell);
    });
  }

  function abrirEditor(d) {
    document.getElementById('cal-editor').hidden = false;
    document.getElementById('cal-editor-titulo').textContent = 'Día ' + d.fecha;
    document.getElementById('ed-fecha').value = d.fecha;
    document.getElementById('ed-tipo').value = d.tipo === 'lectivo' ? 'sin_clase_abierto' : d.tipo;
    document.getElementById('ed-aplica').value = d.aplica_a || 'todos';
    document.getElementById('ed-etiqueta').value = d.etiqueta || '';
    document.getElementById('ed-recuperacion').value = d.fecha_recuperacion || '';
    const showRec = permiteRecuperacion && document.getElementById('ed-tipo').value === 'asueto';
    document.getElementById('ed-recuperacion-wrap').style.display = showRec ? 'block' : 'none';
  }

  document.getElementById('ed-tipo').onchange = () => {
    const showRec = permiteRecuperacion && document.getElementById('ed-tipo').value === 'asueto';
    document.getElementById('ed-recuperacion-wrap').style.display = showRec ? 'block' : 'none';
  };

  function appendModelo(fd) {
    fd.append('modelo', modelo);
  }

  async function cargar() {
    const anio = document.getElementById('cal-anio').value;
    const mes = document.getElementById('cal-mes').value;
    const r = await fetch(api + '?action=mes_grid&anio=' + anio + '&mes=' + mes + '&modelo=' + encodeURIComponent(modelo));
    const d = await r.json();
    if (d.status !== 'ok') { show(d.message || 'Error', false); return; }

    permiteRecuperacion = !!d.permite_recuperacion;
    llenarSelectTipos(document.getElementById('ed-tipo'), d.tipos, true);
    llenarSelectTipos(document.getElementById('rg-tipo'), d.tipos, false);

    estado.style.display = 'block';
    estado.className = 'catalog-alert catalog-alert--' + (d.publicado ? 'ok' : 'warn');
    estado.textContent = d.publicado
      ? 'Calendario publicado (' + modelo + ') — grupos y recepción usan estos días.'
      : 'Borrador (' + modelo + ') — publique cuando esté listo.';

    pintarLeyenda(d.tipos || {});
    renderGrid(d.dias || []);
  }

  document.getElementById('btn-cal-cargar').onclick = cargar;

  document.getElementById('btn-ed-guardar').onclick = async () => {
    const fd = new FormData();
    fd.append('action', 'guardar_dia');
    appendModelo(fd);
    fd.append('fecha', document.getElementById('ed-fecha').value);
    fd.append('tipo', document.getElementById('ed-tipo').value);
    fd.append('aplica_a', document.getElementById('ed-aplica').value);
    fd.append('etiqueta', document.getElementById('ed-etiqueta').value);
    if (permiteRecuperacion) fd.append('fecha_recuperacion', document.getElementById('ed-recuperacion').value);
    if (document.getElementById('ed-tipo').value === 'sin_clase_abierto') fd.append('plantel_abierto', '1');
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    show(d.message, d.status === 'ok');
    if (d.status === 'ok') cargar();
  };

  document.getElementById('btn-ed-quitar').onclick = async () => {
    const fd = new FormData();
    fd.append('action', 'eliminar_dia');
    appendModelo(fd);
    fd.append('fecha', document.getElementById('ed-fecha').value);
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    show(d.message, d.status === 'ok');
    if (d.status === 'ok') cargar();
  };

  document.getElementById('btn-rg-guardar').onclick = async () => {
    const fd = new FormData();
    fd.append('action', 'guardar_rango');
    appendModelo(fd);
    fd.append('fecha_inicio', document.getElementById('rg-inicio').value);
    fd.append('fecha_fin', document.getElementById('rg-fin').value);
    fd.append('tipo', document.getElementById('rg-tipo').value);
    fd.append('etiqueta', document.getElementById('rg-etiqueta').value);
    if (document.getElementById('rg-tipo').value === 'sin_clase_abierto') fd.append('plantel_abierto', '1');
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    show(d.message, d.status === 'ok');
    if (d.status === 'ok') cargar();
  };

  document.getElementById('btn-cal-sugerencias').onclick = async () => {
    const fd = new FormData();
    fd.append('action', 'importar_sugerencias');
    appendModelo(fd);
    fd.append('anio', document.getElementById('cal-anio').value);
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    show(d.message, d.status === 'ok');
    if (d.status === 'ok') cargar();
  };

  document.getElementById('btn-cal-publicar').onclick = async () => {
    if (!confirm('¿Publicar este calendario? Afectará parciales y avisos del área correspondiente.')) return;
    const fd = new FormData();
    fd.append('action', 'publicar');
    appendModelo(fd);
    fd.append('anio', document.getElementById('cal-anio').value);
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    show(d.message, d.status === 'ok');
    if (d.status === 'ok') cargar();
  };

  actualizarAyuda();
  cargar();
})();
</script>
