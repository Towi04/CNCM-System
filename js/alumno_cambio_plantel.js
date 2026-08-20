(function () {
  const cfg = window.HAY_ALUMNO_CAMBIO_PLANTEL || {};
  const api = cfg.api || 'php/alumno_plantel_transfer_api.php';

  function esc(value) {
    const el = document.createElement('div');
    el.textContent = value == null ? '' : String(value);
    return el.innerHTML;
  }

  function mensaje(texto, error) {
    const el = document.getElementById('acp-msg');
    if (!el) return;
    el.hidden = false;
    el.className = 'acp-msg ' + (error ? 'acp-msg--error' : 'acp-msg--ok');
    el.textContent = texto;
  }

  async function solicitarApi(accion, datos) {
    const fd = new FormData();
    fd.set('accion', accion);
    Object.entries(datos || {}).forEach(([k, v]) => fd.set(k, v == null ? '' : v));
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

  function tabla(rows, entrantes) {
    if (!rows.length) return '<p>No hay registros.</p>';
    let html = '<table class="acp-table"><thead><tr><th>Alumno</th><th>Ruta</th><th>Solicitud</th><th>Estado</th>';
    if (entrantes) html += '<th>Acciones</th>';
    html += '</tr></thead><tbody>';
    rows.forEach((r) => {
      html += '<tr><td><strong>' + esc(r.alumno) + '</strong><br><small>' + esc(r.numero_control || '') + '</small></td>';
      html += '<td>' + esc(r.plantel_origen) + ' → ' + esc(r.plantel_destino) + '</td>';
      html += '<td>' + esc(r.solicitado_en || '') + (r.motivo ? '<br><small>' + esc(r.motivo) + '</small>' : '') + '</td>';
      html += '<td><span class="acp-badge acp-badge--' + esc(r.estado) + '">' + esc(r.estado) + '</span>';
      if (r.notas_destino) html += '<br><small>' + esc(r.notas_destino) + '</small>';
      html += '</td>';
      if (entrantes) {
        html += '<td class="acp-actions"><button type="button" class="primary acp-autorizar" data-id="' + r.id + '">Autorizar</button>';
        html += '<button type="button" class="secondary acp-rechazar" data-id="' + r.id + '">Rechazar</button></td>';
      }
      html += '</tr>';
    });
    return html + '</tbody></table>';
  }

  function enlazarEntrantes() {
    document.querySelectorAll('.acp-autorizar').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const notas = window.prompt('Notas para el plantel origen (opcional):', '');
        if (notas === null || !window.confirm('¿Autorizar el cambio? Se desactivarán los grupos activos del alumno.')) return;
        try {
          const data = await solicitarApi('autorizar', { id: btn.dataset.id, notas_destino: notas });
          mensaje(data.message, false);
          await cargar();
        } catch (e) { mensaje(e.message, true); }
      });
    });
    document.querySelectorAll('.acp-rechazar').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const notas = window.prompt('Indique el motivo del rechazo:', '');
        if (notas === null) return;
        try {
          const data = await solicitarApi('rechazar', { id: btn.dataset.id, notas_destino: notas });
          mensaje(data.message, false);
          await cargar();
        } catch (e) { mensaje(e.message, true); }
      });
    });
  }

  async function cargar() {
    try {
      const url = new URL(api, window.location.href);
      url.searchParams.set('accion', 'listar');
      const res = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message || 'No se pudieron cargar las solicitudes');
      const entrantes = document.getElementById('acp-entrantes');
      const historial = document.getElementById('acp-historial');
      if (entrantes) entrantes.innerHTML = tabla(data.entrantes || [], true);
      if (historial) historial.innerHTML = tabla(data.historial || [], false);
      const destino = document.getElementById('acp-destino');
      if (destino) {
        const actual = destino.value;
        destino.innerHTML = '<option value="">Seleccione…</option>' + (data.planteles || []).map((p) =>
          '<option value="' + p.id_plantel + '">' + esc(p.nombre) + '</option>'
        ).join('');
        if (actual) destino.value = actual;
      }
      enlazarEntrantes();
    } catch (e) {
      mensaje(e.message, true);
    }
  }

  document.getElementById('acp-buscar')?.addEventListener('click', async () => {
    const control = document.getElementById('acp-control')?.value.trim() || '';
    const cont = document.getElementById('acp-resultados');
    if (!control || !cont) return;
    cont.textContent = 'Buscando…';
    try {
      const url = new URL(api, window.location.href);
      url.searchParams.set('accion', 'buscar');
      url.searchParams.set('control', control);
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      cont.innerHTML = (data.alumnos || []).length
        ? data.alumnos.map((a) => '<button type="button" data-id="' + a.id_alumno + '" data-control="' +
          esc(a.numero_control || a.matricula || '') + '" data-nombre="' + esc(a.alumno) + '"><strong>' +
          esc(a.numero_control || a.matricula || '') + '</strong> · ' + esc(a.alumno) + '</button>').join('')
        : '<p>Sin coincidencias en este plantel.</p>';
      cont.querySelectorAll('button').forEach((btn) => {
        btn.addEventListener('click', () => {
          document.getElementById('acp-id-alumno').value = btn.dataset.id;
          document.getElementById('acp-seleccion-nombre').textContent = btn.dataset.nombre;
          document.getElementById('acp-seleccion-control').textContent = btn.dataset.control;
          document.getElementById('acp-seleccion').hidden = false;
          cont.innerHTML = '';
        });
      });
    } catch (e) {
      cont.innerHTML = '<p>' + esc(e.message) + '</p>';
    }
  });

  document.getElementById('acp-control')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      document.getElementById('acp-buscar')?.click();
    }
  });

  document.getElementById('acp-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const idAlumno = document.getElementById('acp-id-alumno')?.value || '';
    const idDestino = document.getElementById('acp-destino')?.value || '';
    if (!idAlumno || !idDestino) {
      mensaje('Seleccione alumno y plantel destino.', true);
      return;
    }
    try {
      const data = await solicitarApi('solicitar', {
        id_alumno: idAlumno,
        id_plantel_destino: idDestino,
        motivo: document.getElementById('acp-motivo')?.value.trim() || '',
      });
      mensaje(data.message, false);
      document.getElementById('acp-form').reset();
      document.getElementById('acp-id-alumno').value = '';
      document.getElementById('acp-seleccion').hidden = true;
      await cargar();
    } catch (err) { mensaje(err.message, true); }
  });

  cargar();
})();
