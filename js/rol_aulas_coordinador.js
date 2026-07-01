(function () {
  const cfg = window.HAY_ROL_AULAS_CONFIG || {};
  const api = cfg.api || 'php/rol_aula_api.php';
  let publicacion = null;
  let aulas = [];
  let cambiosPendientes = [];
  let dragGrupo = null;

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function mesAnio() {
    return {
      mes: document.getElementById('rol-mes').value,
      anio: document.getElementById('rol-anio').value,
    };
  }

  function setEstado(txt, tipo) {
    const el = document.getElementById('rol-estado');
    if (!el) return;
    el.textContent = txt || '';
    el.className = 'rol-estado' + (tipo ? ' rol-estado--' + tipo : '');
  }

  function showConflictos(list) {
    const box = document.getElementById('rol-conflictos');
    if (!box) return;
    if (!list || !list.length) {
      box.hidden = true;
      return;
    }
    box.hidden = false;
    box.innerHTML = '<strong>Conflictos detectados:</strong><ul>'
      + list.map((c) => '<li>' + esc(c.mensaje || c.grupo || '') + '</li>').join('')
      + '</ul>';
  }

  function horarioLabel(a) {
    if (a.horario_texto) return a.horario_texto;
    const hs = a.horarios || [];
    if (!hs.length) return 'Sin horario';
    return hs.map((h) => 'D' + h.dia_semana + ' ' + String(h.hora_inicio).slice(0, 5) + '-' + String(h.hora_fin).slice(0, 5)).join(', ');
  }

  function asignacionesPorAula() {
    const map = {};
    (publicacion?.asignaciones || []).forEach((a) => {
      const key = a.id_aula ? String(a.id_aula) : '_sin';
      if (!map[key]) map[key] = [];
      map[key].push(a);
    });
    return map;
  }

  function queueCambio(idGrupo, idAula) {
    const idx = cambiosPendientes.findIndex((c) => c.id_grupo === idGrupo);
    const item = { id_grupo: idGrupo, id_aula: idAula || null };
    if (idx >= 0) cambiosPendientes[idx] = item;
    else cambiosPendientes.push(item);
  }

  function render() {
    if (!publicacion) {
      document.getElementById('rol-grupos').innerHTML = '';
      document.getElementById('rol-aulas').innerHTML = '';
      document.getElementById('rol-pendientes').innerHTML = '';
      return;
    }

    const porAula = asignacionesPorAula();
    const editable = publicacion.estado !== 'publicado';

    document.getElementById('rol-grupos').innerHTML = (publicacion.asignaciones || []).map((a) => `
      <div class="rol-grupo-card${a.es_manual ? ' rol-manual' : ''}" draggable="${editable ? 'true' : 'false'}" data-grupo="${a.id_grupo}">
        <strong>${esc(a.grupo_clave)}</strong>
        <span>${esc(a.esp_nombre || '')} · ${esc(a.total_alumnos)} alumnos</span>
        <span>${esc(horarioLabel(a))}</span>
      </div>
    `).join('');

    document.getElementById('rol-aulas').innerHTML = aulas.map((aula) => {
      const list = porAula[String(aula.id_aula)] || [];
      const cap = aula.capacidad_flexible ? aula.capacidad + ' (flex)' : aula.capacidad;
      return `<div class="rol-aula-card" data-aula="${aula.id_aula}">
        <h4>${esc(aula.codigo)}</h4>
        <div class="rol-aula-meta">Cap. ${esc(cap)} · ${esc(aula.tipo_label || '')}</div>
        <div class="rol-dropzone" data-aula="${aula.id_aula}">
          ${list.map((g) => asignadoHtml(g, editable)).join('')}
        </div>
      </div>`;
    }).join('');

    const pend = porAula._sin || [];
    document.getElementById('rol-pendientes').innerHTML = pend.map((g) => asignadoHtml(g, editable)).join('')
      || '<span style="color:#888;font-size:0.85rem;">Ningún grupo pendiente</span>';

    if (editable) bindDnD();
    bindSelectores();
  }

  function asignadoHtml(g, editable) {
    return `<div class="rol-asignado${g.es_manual ? ' rol-manual' : ''}" data-grupo="${g.id_grupo}">
      <span>${esc(g.grupo_clave)} <small>(${esc(g.total_alumnos)})</small></span>
      ${editable ? `<button type="button" class="btn-quitar" data-grupo="${g.id_grupo}">Quitar</button>` : ''}
    </div>`;
  }

  function bindDnD() {
    document.querySelectorAll('.rol-grupo-card[draggable=true]').forEach((card) => {
      card.addEventListener('dragstart', () => { dragGrupo = card.dataset.grupo; });
      card.addEventListener('dragend', () => { dragGrupo = null; });
    });
    document.querySelectorAll('.rol-dropzone, .rol-aula-card').forEach((zone) => {
      zone.addEventListener('dragover', (ev) => {
        ev.preventDefault();
        zone.classList.add('rol-aula--over');
      });
      zone.addEventListener('dragleave', () => zone.classList.remove('rol-aula--over'));
      zone.addEventListener('drop', async (ev) => {
        ev.preventDefault();
        zone.classList.remove('rol-aula--over');
        if (!dragGrupo) return;
        const idAula = zone.dataset.aula || zone.closest('[data-aula]')?.dataset.aula || '';
        await aplicarCambio(parseInt(dragGrupo, 10), idAula ? parseInt(idAula, 10) : null);
      });
    });
  }

  function bindSelectores() {
    document.querySelectorAll('.btn-quitar').forEach((btn) => {
      btn.addEventListener('click', () => aplicarCambio(parseInt(btn.dataset.grupo, 10), null));
    });
  }

  async function aplicarCambio(idGrupo, idAula) {
    if (!publicacion || publicacion.estado === 'publicado') return;
    queueCambio(idGrupo, idAula);
    const fd = new FormData();
    fd.append('accion', 'guardar_asignaciones');
    fd.append('id_publicacion', publicacion.id_publicacion);
    fd.append('cambios', JSON.stringify([{ id_grupo: idGrupo, id_aula: idAula }]));
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await r.json();
    if (data.status !== 'ok') {
      setEstado(data.message || 'Error al guardar', 'error');
      return;
    }
    await cargar(false);
    setEstado('Cambio guardado. Valide antes de publicar.');
  }

  async function cargar(resetMsg) {
    const { mes, anio } = mesAnio();
    const r = await fetch(api + '?accion=obtener&mes=' + mes + '&anio=' + anio, { credentials: 'same-origin' });
    const data = await r.json();
    publicacion = data.publicacion;
    aulas = data.aulas || [];
    cambiosPendientes = [];
    if (!publicacion) {
      setEstado('No hay rol para ' + String(mes).padStart(2, '0') + '/' + anio + '. Use «Generar rol».', 'warn');
      render();
      return;
    }
    const st = publicacion.estado === 'publicado' ? 'Publicado' : 'Borrador';
    if (resetMsg !== false) {
      setEstado('Periodo ' + String(mes).padStart(2, '0') + '/' + anio + ' — ' + st + ' · ' + (publicacion.asignaciones?.length || 0) + ' grupos');
    }
    document.getElementById('btn-rol-publicar').disabled = publicacion.estado === 'publicado';
    document.getElementById('btn-rol-generar').disabled = publicacion.estado === 'publicado';
    showConflictos([]);
    render();
  }

  document.getElementById('btn-rol-generar')?.addEventListener('click', async () => {
    if (!confirm('¿Generar rol de aulas? Se reemplazarán las asignaciones del borrador de este mes.')) return;
    const { mes, anio } = mesAnio();
    const fd = new FormData();
    fd.append('accion', 'generar');
    fd.append('mes', mes);
    fd.append('anio', anio);
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await r.json();
    setEstado(data.message || '', data.status !== 'ok' ? 'error' : '');
    if (data.status === 'ok') cargar();
  });

  document.getElementById('btn-rol-validar')?.addEventListener('click', async () => {
    if (!publicacion) return;
    const r = await fetch(api + '?accion=validar&id_publicacion=' + publicacion.id_publicacion, { credentials: 'same-origin' });
    const data = await r.json();
    setEstado(data.message || '', data.status !== 'ok' ? 'error' : '');
    showConflictos(data.conflictos || []);
  });

  document.getElementById('btn-rol-publicar')?.addEventListener('click', async () => {
    if (!publicacion) return;
    if (!confirm('¿Publicar el rol? Se notificará a profesores y alumnos.')) return;
    const fd = new FormData();
    fd.append('accion', 'publicar');
    fd.append('id_publicacion', publicacion.id_publicacion);
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await r.json();
    setEstado(data.message || '', data.status !== 'ok' ? 'error' : '');
    showConflictos(data.conflictos || []);
    if (data.status === 'ok') cargar();
  });

  document.getElementById('rol-mes')?.addEventListener('change', () => cargar());
  document.getElementById('rol-anio')?.addEventListener('change', () => cargar());

  document.getElementById('btn-rol-pdf')?.addEventListener('click', () => {
    const { mes, anio } = mesAnio();
    const pdf = cfg.pdf || 'php/rol_aula_pdf.php';
    const url = new URL(pdf, window.location.href);
    url.searchParams.set('mes', mes);
    url.searchParams.set('anio', anio);
    window.open(url.toString(), '_blank');
  });

  cargar();
})();
