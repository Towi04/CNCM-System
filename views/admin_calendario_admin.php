<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_id']) || !calendario_puede_editar_administrativo()) {
    echo '<div class="alert">Sin permiso para el calendario administrativo.</div>';
    return;
}

$anio = (int) ($_GET['anio'] ?? date('Y'));
$mes = (int) ($_GET['mes'] ?? date('n'));
$nombresMes = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$roles = rbac_roles_etiquetas();
$departamentos = [
    'ingles' => 'Inglés',
    'computacion' => 'Computación',
    'preparatoria' => 'Preparatoria',
    'ventas' => 'Ventas',
    'administrativo' => 'Administrativo',
];
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/admin_calendario.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-briefcase"></i> Calendario administrativo</h2>
    <a href="#" data-seccion="admin_calendario" style="font-size:0.9rem;">← Calendarios escolares</a>
  </div>

  <p style="color:#666; max-width:900px;">
    Juntas directivas, capacitaciones y eventos con el personal. Al <strong>publicar</strong>, cada usuario de la audiencia recibe una notificación en el panel de inicio.
  </p>

  <div class="catalog-toolbar">
    <div class="field"><label>Año</label><input type="number" id="ev-anio" value="<?php echo $anio; ?>"></div>
    <div class="field">
      <label>Mes</label>
      <select id="ev-mes">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?php echo $m; ?>"<?php echo $m === $mes ? ' selected' : ''; ?>><?php echo $nombresMes[$m]; ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <button type="button" class="primary" id="btn-ev-listar">Actualizar lista</button>
    <button type="button" class="secondary" id="btn-ev-nuevo">Nuevo evento</button>
  </div>

  <div id="ev-msg" class="catalog-alert" style="display:none;"></div>

  <div id="ev-lista" class="cal-event-list"></div>

  <div class="cal-editor" id="ev-editor" hidden>
    <h3 id="ev-editor-titulo">Evento</h3>
    <input type="hidden" id="ev-id" value="0">
    <div class="catalog-form-grid">
      <div class="full-width" style="grid-column:1/-1;">
        <label>Título</label>
        <input type="text" id="ev-titulo" style="width:100%;">
      </div>
      <div>
        <label>Tipo</label>
        <select id="ev-tipo"></select>
      </div>
      <div>
        <label>Fecha</label>
        <input type="date" id="ev-fecha">
      </div>
      <div>
        <label>Fecha fin (opcional)</label>
        <input type="date" id="ev-fecha-fin">
      </div>
      <div>
        <label>Hora inicio</label>
        <input type="time" id="ev-hora-ini">
      </div>
      <div>
        <label>Hora fin</label>
        <input type="time" id="ev-hora-fin">
      </div>
      <div class="full-width" style="grid-column:1/-1;">
        <label>Lugar</label>
        <input type="text" id="ev-lugar" style="width:100%;">
      </div>
      <div class="full-width" style="grid-column:1/-1;">
        <label>Descripción</label>
        <textarea id="ev-descripcion" rows="3" style="width:100%;"></textarea>
      </div>
    </div>

    <h4 style="margin:16px 0 8px;">Audiencia (quién recibe notificación al publicar)</h4>
    <div id="ev-audiencia" class="cal-audiencia"></div>
    <button type="button" class="secondary" id="btn-ev-add-aud" style="margin-top:8px;">+ Añadir audiencia</button>

    <div style="margin-top:16px; display:flex; gap:8px; flex-wrap:wrap;">
      <button type="button" class="primary" id="btn-ev-guardar">Guardar borrador</button>
      <button type="button" class="primary" id="btn-ev-publicar">Guardar y publicar</button>
      <button type="button" id="btn-ev-cancelar">Cancelar</button>
    </div>
  </div>
</div>

<script>
(function() {
  const api = 'php/calendario_eventos_api.php';
  const roles = <?php echo json_encode($roles, JSON_UNESCAPED_UNICODE); ?>;
  const deptos = <?php echo json_encode($departamentos, JSON_UNESCAPED_UNICODE); ?>;
  let tiposEvento = {};
  let eventos = [];

  const msg = document.getElementById('ev-msg');
  function show(t, ok) {
    msg.style.display = 'block';
    msg.className = 'catalog-alert catalog-alert--' + (ok ? 'ok' : 'error');
    msg.textContent = t;
  }

  function renderLista() {
    const box = document.getElementById('ev-lista');
    if (!eventos.length) {
      box.innerHTML = '<p style="color:#888;">No hay eventos este mes.</p>';
      return;
    }
    box.innerHTML = eventos.map((e) => {
      const pub = e.publicado == 1 ? '<span class="cal-ev-pub">Publicado</span>' : '<span class="cal-ev-draft">Borrador</span>';
      const fechas = e.fecha + (e.fecha_fin && e.fecha_fin !== e.fecha ? ' – ' + e.fecha_fin : '');
      return '<article class="cal-ev-card">' + pub +
        '<h4>' + escapeHtml(e.titulo) + '</h4>' +
        '<p>' + fechas + (e.hora_inicio ? ' ' + e.hora_inicio : '') + '</p>' +
        '<p style="color:#666;">' + escapeHtml(e.descripcion || '') + '</p>' +
        '<button type="button" class="secondary" data-edit="' + e.id + '">Editar</button>' +
        (e.publicado != 1 ? ' <button type="button" class="primary" data-pub="' + e.id + '">Publicar</button>' : '') +
        ' <button type="button" data-del="' + e.id + '">Eliminar</button></article>';
    }).join('');
    box.querySelectorAll('[data-edit]').forEach((b) => b.onclick = () => editar(parseInt(b.dataset.edit, 10)));
    box.querySelectorAll('[data-pub]').forEach((b) => b.onclick = () => publicar(parseInt(b.dataset.pub, 10)));
    box.querySelectorAll('[data-del]').forEach((b) => b.onclick = () => eliminar(parseInt(b.dataset.del, 10)));
  }

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function filaAudiencia(a) {
    const div = document.createElement('div');
    div.className = 'cal-aud-row';
    div.innerHTML =
      '<select class="aud-tipo"><option value="todos">Todo el personal</option>' +
      '<option value="rol">Por rol</option><option value="departamento">Por departamento</option></select>' +
      '<select class="aud-valor" style="display:none;"></select>' +
      '<button type="button" class="aud-quitar">×</button>';
    const selT = div.querySelector('.aud-tipo');
    const selV = div.querySelector('.aud-valor');
    selT.onchange = () => {
      selV.style.display = selT.value === 'todos' ? 'none' : '';
      selV.innerHTML = '';
      if (selT.value === 'rol') {
        Object.keys(roles).forEach((k) => {
          const o = document.createElement('option');
          o.value = k; o.textContent = roles[k];
          selV.appendChild(o);
        });
      }
      if (selT.value === 'departamento') {
        Object.keys(deptos).forEach((k) => {
          const o = document.createElement('option');
          o.value = k; o.textContent = deptos[k];
          selV.appendChild(o);
        });
      }
    };
    div.querySelector('.aud-quitar').onclick = () => div.remove();
    if (a) {
      selT.value = a.tipo;
      selT.dispatchEvent(new Event('change'));
      selV.value = a.valor;
    }
    return div;
  }

  function leerAudiencia() {
    const out = [];
    document.querySelectorAll('.cal-aud-row').forEach((row) => {
      const t = row.querySelector('.aud-tipo').value;
      const v = row.querySelector('.aud-valor').value || '';
      out.push({ tipo: t, valor: t === 'todos' ? '' : v });
    });
    return out;
  }

  function nuevoEvento() {
    document.getElementById('ev-editor').hidden = false;
    document.getElementById('ev-id').value = '0';
    document.getElementById('ev-titulo').value = '';
    document.getElementById('ev-descripcion').value = '';
    document.getElementById('ev-fecha').value = '';
    document.getElementById('ev-fecha-fin').value = '';
    document.getElementById('ev-hora-ini').value = '';
    document.getElementById('ev-hora-fin').value = '';
    document.getElementById('ev-lugar').value = '';
    const aud = document.getElementById('ev-audiencia');
    aud.innerHTML = '';
    aud.appendChild(filaAudiencia({ tipo: 'todos', valor: '' }));
  }

  function editar(id) {
    const e = eventos.find((x) => parseInt(x.id, 10) === id);
    if (!e) return;
    document.getElementById('ev-editor').hidden = false;
    document.getElementById('ev-id').value = e.id;
    document.getElementById('ev-titulo').value = e.titulo;
    document.getElementById('ev-tipo').value = e.tipo;
    document.getElementById('ev-fecha').value = e.fecha;
    document.getElementById('ev-fecha-fin').value = e.fecha_fin || '';
    document.getElementById('ev-hora-ini').value = (e.hora_inicio || '').slice(0, 5);
    document.getElementById('ev-hora-fin').value = (e.hora_fin || '').slice(0, 5);
    document.getElementById('ev-lugar').value = e.lugar || '';
    document.getElementById('ev-descripcion').value = e.descripcion || '';
    const aud = document.getElementById('ev-audiencia');
    aud.innerHTML = '';
    (e.audiencia && e.audiencia.length ? e.audiencia : [{ tipo: 'todos', valor: '' }])
      .forEach((a) => aud.appendChild(filaAudiencia(a)));
  }

  async function listar() {
    const anio = document.getElementById('ev-anio').value;
    const mes = document.getElementById('ev-mes').value;
    const r = await fetch(api + '?action=listar&anio=' + anio + '&mes=' + mes);
    const d = await r.json();
    if (d.status !== 'ok') { show(d.message || 'Error', false); return; }
    tiposEvento = d.tipos || {};
    const sel = document.getElementById('ev-tipo');
    sel.innerHTML = '';
    Object.keys(tiposEvento).forEach((k) => {
      const o = document.createElement('option');
      o.value = k; o.textContent = tiposEvento[k];
      sel.appendChild(o);
    });
    eventos = d.eventos || [];
    renderLista();
  }

  async function guardar(publicar) {
    const fd = new FormData();
    fd.append('action', 'guardar');
    fd.append('id', document.getElementById('ev-id').value);
    fd.append('titulo', document.getElementById('ev-titulo').value);
    fd.append('descripcion', document.getElementById('ev-descripcion').value);
    fd.append('tipo', document.getElementById('ev-tipo').value);
    fd.append('fecha', document.getElementById('ev-fecha').value);
    fd.append('fecha_fin', document.getElementById('ev-fecha-fin').value);
    fd.append('hora_inicio', document.getElementById('ev-hora-ini').value);
    fd.append('hora_fin', document.getElementById('ev-hora-fin').value);
    fd.append('lugar', document.getElementById('ev-lugar').value);
    fd.append('audiencia', JSON.stringify(leerAudiencia()));
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    if (d.status !== 'ok') { show(d.message, false); return; }
    if (publicar && d.id) {
      const fd2 = new FormData();
      fd2.append('action', 'publicar');
      fd2.append('id', d.id);
      const r2 = await fetch(api, { method: 'POST', body: fd2 });
      const d2 = await r2.json();
      show(d2.message, d2.status === 'ok');
    } else {
      show(d.message, true);
    }
    document.getElementById('ev-editor').hidden = true;
    listar();
  }

  async function publicar(id) {
    const fd = new FormData();
    fd.append('action', 'publicar');
    fd.append('id', id);
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    show(d.message, d.status === 'ok');
    listar();
  }

  async function eliminar(id) {
    if (!confirm('¿Eliminar evento?')) return;
    const fd = new FormData();
    fd.append('action', 'eliminar');
    fd.append('id', id);
    await fetch(api, { method: 'POST', body: fd });
    listar();
  }

  document.getElementById('btn-ev-listar').onclick = listar;
  document.getElementById('btn-ev-nuevo').onclick = nuevoEvento;
  document.getElementById('btn-ev-add-aud').onclick = () => document.getElementById('ev-audiencia').appendChild(filaAudiencia());
  document.getElementById('btn-ev-guardar').onclick = () => guardar(false);
  document.getElementById('btn-ev-publicar').onclick = () => guardar(true);
  document.getElementById('btn-ev-cancelar').onclick = () => { document.getElementById('ev-editor').hidden = true; };

  listar();
})();
</script>
