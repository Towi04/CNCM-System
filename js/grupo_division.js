(function () {
  const cfg = window.HAY_GRUPO_DIVISION || {};
  const api = cfg.api || 'php/grupo_division_api.php';
  const umbral = Number(cfg.umbral || 15);
  let division = null;
  let alumnosPorId = {};
  let original = [];
  let nuevo = [];

  function esc(value) {
    const el = document.createElement('div');
    el.textContent = value == null ? '' : String(value);
    return el.innerHTML;
  }

  function mensaje(texto, error) {
    const el = document.getElementById('gd-msg');
    if (!el) return;
    el.hidden = false;
    el.className = 'gd-msg ' + (error ? 'gd-msg--error' : 'gd-msg--ok');
    el.textContent = texto;
  }

  async function post(accion, datos) {
    const fd = new FormData();
    fd.set('accion', accion);
    Object.entries(datos || {}).forEach(([k, v]) => fd.set(k, v));
    const res = await fetch(api, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' },
    });
    const data = await res.json();
    if (data.status !== 'ok') throw new Error(data.message || 'Error en la operación');
    return data;
  }

  function renderLista(ids, destino) {
    if (!ids.length) return '<p><em>Sin alumnos.</em></p>';
    return ids.map((id) => {
      const a = alumnosPorId[id] || { alumno: 'Alumno #' + id };
      const edad = a.edad_calculada != null ? a.edad_calculada + ' años' : 'edad no registrada';
      return '<div class="gd-alumno"><span><strong>' + esc(a.alumno) + '</strong><br><small>' +
        esc(a.numero_control || '') + ' · ' + esc(edad) + '</small></span><button type="button" data-id="' +
        id + '" data-destino="' + destino + '">' + (destino === 'nuevo' ? '→' : '←') + '</button></div>';
    }).join('');
  }

  function renderEditor() {
    const el = document.getElementById('gd-editor');
    if (!el || !division) return;
    let html = '<h3>' + esc(division.clave_original) + ' → ' + esc(division.clave_nueva) + '</h3>';
    html += '<p class="gd-warning">Borrador: revise las listas. La confirmación desactiva en el grupo original a quienes se muevan, conservando el historial.</p>';
    html += '<div class="gd-columnas"><div class="gd-col"><h4>Grupo original (' + original.length + ')</h4>' +
      '<div id="gd-lista-original">' + renderLista(original, 'nuevo') + '</div></div>';
    html += '<div class="gd-col"><h4>Grupo nuevo (' + nuevo.length + ')</h4>' +
      '<div id="gd-lista-nuevo">' + renderLista(nuevo, 'original') + '</div></div></div>';
    html += '<button type="button" class="primary" id="gd-confirmar" style="margin-top:14px">Confirmar división</button>';
    el.innerHTML = html;
    el.querySelectorAll('.gd-alumno button').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = Number(btn.dataset.id);
        if (btn.dataset.destino === 'nuevo') {
          original = original.filter((x) => x !== id);
          if (!nuevo.includes(id)) nuevo.push(id);
        } else {
          nuevo = nuevo.filter((x) => x !== id);
          if (!original.includes(id)) original.push(id);
        }
        renderEditor();
      });
    });
    document.getElementById('gd-confirmar')?.addEventListener('click', confirmar);
  }

  function abrirDivision(data) {
    division = data;
    alumnosPorId = {};
    (data.alumnos || []).forEach((a) => { alumnosPorId[Number(a.id_alumno)] = a; });
    original = (data.asignacion_original || []).map(Number);
    nuevo = (data.asignacion_nuevo || []).map(Number);
    renderEditor();
  }

  async function abrir(id) {
    try {
      const url = new URL(api, window.location.href);
      url.searchParams.set('accion', 'obtener');
      url.searchParams.set('id', id);
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      abrirDivision(data.division);
    } catch (e) { mensaje(e.message, true); }
  }

  async function proponer(grupo) {
    if (Number(grupo.total_alumnos) <= umbral &&
        !window.confirm('Este grupo tiene ' + grupo.total_alumnos + ' alumnos (el umbral recomendado es más de ' +
          umbral + '). ¿Desea crear el borrador de todos modos?')) return;
    try {
      const data = await post('proponer', { id_grupo: grupo.id_grupo });
      mensaje(data.message, false);
      if (data.division) abrirDivision(data.division);
      await cargar();
    } catch (e) { mensaje(e.message, true); }
  }

  async function confirmar() {
    if (!original.length || !nuevo.length) {
      mensaje('Ambos grupos deben conservar al menos un alumno.', true);
      return;
    }
    if (!window.confirm('¿Confirmar la división? Se actualizarán las asignaciones de grupo.')) return;
    try {
      const data = await post('confirmar', {
        id: division.id,
        asignacion_original_json: JSON.stringify(original),
        asignacion_nuevo_json: JSON.stringify(nuevo),
      });
      mensaje(data.message, false);
      division = null;
      document.getElementById('gd-editor').innerHTML = '<p>División confirmada.</p>';
      await cargar();
    } catch (e) { mensaje(e.message, true); }
  }

  async function cargar() {
    try {
      const url = new URL(api, window.location.href);
      url.searchParams.set('accion', 'listar');
      const res = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      const gruposEl = document.getElementById('gd-grupos');
      const borradoresEl = document.getElementById('gd-borradores');
      const grupos = data.grupos || [];
      gruposEl.innerHTML = grupos.length ? grupos.map((g) =>
        '<button type="button" class="gd-grupo ' + (Number(g.total_alumnos) > umbral ? 'gd-grupo--recomendado' : '') +
        '" data-id="' + g.id_grupo + '"><span><strong>' + esc(g.clave) + '</strong><br><small>' +
        esc(g.especialidad || '') + '</small></span><span>' + g.total_alumnos + ' alumnos</span></button>'
      ).join('') : '<p>No hay grupos.</p>';
      gruposEl.querySelectorAll('.gd-grupo').forEach((btn) => {
        btn.addEventListener('click', () => proponer(grupos.find((g) => Number(g.id_grupo) === Number(btn.dataset.id))));
      });
      const borradores = data.borradores || [];
      borradoresEl.innerHTML = borradores.length ? borradores.map((d) =>
        '<button type="button" class="gd-borrador" data-id="' + d.id + '"><strong>' + esc(d.clave_original) +
        ' → ' + esc(d.clave_nueva) + '</strong><br><small>' + d.total_original + ' / ' + d.total_nuevo +
        ' alumnos</small></button>'
      ).join('') : '<p>No hay borradores.</p>';
      borradoresEl.querySelectorAll('.gd-borrador').forEach((btn) => {
        btn.addEventListener('click', () => abrir(btn.dataset.id));
      });
    } catch (e) { mensaje(e.message, true); }
  }

  cargar();
})();
