(function () {
  const cfg = window.HAY_ASESOR_PREINICIO || {};
  const api = cfg.api || 'php/grupo_preinicio_api.php';
  let idGrupoActivo = 0;
  let bound = false;

  function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
  }

  function msg(text, ok) {
    const el = document.getElementById('apg-msg');
    if (!el) return;
    el.style.display = text ? '' : 'none';
    el.className = 'catalog-alert catalog-alert--' + (ok ? 'ok' : 'error');
    el.textContent = text || '';
  }

  async function fetchJson(url, opts) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }, opts || {}));
    return r.json();
  }

  async function cargarGrupos() {
    const dias = document.getElementById('apg-dias')?.value || 21;
    const d = await fetchJson(api + '?accion=listar_grupos&dias=' + encodeURIComponent(dias));
    const cont = document.getElementById('apg-lista-grupos');
    if (!cont) return;
    cont.innerHTML = '';
    if (d.status === 'error') {
      msg(d.message || 'Error al cargar grupos', false);
      return;
    }
    (d.grupos || []).forEach((g) => {
      const card = document.createElement('div');
      card.className = 'apg-grupo-card' + (Number(g.id_grupo) === idGrupoActivo ? ' is-active' : '');
      card.dataset.idGrupo = g.id_grupo;
      card.innerHTML =
        '<h4>' + esc(g.clave) + '</h4>' +
        '<p>' + esc(g.especialidad || '') + '</p>' +
        '<p>Inicio: ' + esc(String(g.fecha_inicio || '').slice(0, 10)) +
        ' · 1er día: ' + esc(String(g.primer_dia_clase || '').slice(0, 10)) + '</p>' +
        '<p>Alumnos: ' + esc(g.total_alumnos) + ' · Contactados: ' + esc(g.contactados) + '</p>';
      card.addEventListener('click', () => {
        idGrupoActivo = Number(g.id_grupo);
        cargarGrupos().then(() => cargarAlumnos(idGrupoActivo, g.clave));
      });
      cont.appendChild(card);
    });
  }

  async function guardarContacto(idGrupo, idAlumno, form) {
    const fd = new FormData(form);
    fd.append('accion', 'guardar_contacto');
    fd.append('id_grupo', String(idGrupo));
    fd.append('id_alumno', String(idAlumno));
    if (!fd.has('contactado')) fd.append('contactado', '0');
    const d = await fetchJson(api, { method: 'POST', body: fd });
    msg(d.message || '', d.status === 'ok');
    if (d.status === 'ok') {
      await cargarGrupos();
      await cargarAlumnos(idGrupo);
    }
  }

  async function cargarAlumnos(idGrupo, clave) {
    const titulo = document.getElementById('apg-titulo-alumnos');
    if (titulo) titulo.textContent = clave ? 'Alumnos — ' + clave : 'Alumnos del grupo';
    const cont = document.getElementById('apg-lista-alumnos');
    if (!cont) return;
    cont.innerHTML = '<p style="color:#888;">Cargando…</p>';
    const d = await fetchJson(api + '?accion=listar_alumnos&id_grupo=' + encodeURIComponent(idGrupo));
    if (d.status === 'error') {
      cont.innerHTML = '<p class="catalog-alert catalog-alert--error">' + esc(d.message) + '</p>';
      return;
    }
    cont.innerHTML = '';
    if (!(d.alumnos || []).length) {
      cont.innerHTML = '<p style="color:#888;">Sin alumnos inscritos en este grupo.</p>';
      return;
    }
    (d.alumnos || []).forEach((a) => {
      const row = document.createElement('div');
      row.className = 'apg-alumno-row' + (Number(a.contactado) === 1 ? ' is-done' : '');
      const tel = [a.telefono, a.celular].filter(Boolean).join(' / ');
      row.innerHTML =
        '<strong>' + esc(a.nombre) + '</strong> <span class="apg-alumno-meta">' +
        esc(a.numero_control) + (tel ? ' · ' + esc(tel) : '') +
        (a.email ? ' · ' + esc(a.email) : '') + '</span>' +
        '<form class="apg-form-contacto">' +
        '<div class="apg-form-grid">' +
        '<div><label><input type="checkbox" name="contactado" value="1"' + (Number(a.contactado) === 1 ? ' checked' : '') + '> Contactado</label></div>' +
        '<div><label>Medio</label><select name="medio">' +
        '<option value="">—</option>' +
        ['telefono', 'whatsapp', 'presencial', 'correo', 'otro'].map((m) =>
          '<option value="' + m + '"' + ((a.medio || '') === m ? ' selected' : '') + '>' + m + '</option>'
        ).join('') +
        '</select></div>' +
        '<div style="grid-column:1/-1;"><label>Notas</label><textarea name="notas" rows="2" maxlength="500">' + esc(a.notas || '') + '</textarea></div>' +
        '<div><button type="submit" class="primary">Guardar</button></div>' +
        '</div></form>';
      row.querySelector('form')?.addEventListener('submit', (e) => {
        e.preventDefault();
        guardarContacto(idGrupo, a.id_alumno, e.target);
      });
      cont.appendChild(row);
    });
  }

  function bindEvents() {
    if (bound) return;
    document.getElementById('apg-recargar')?.addEventListener('click', () => cargarGrupos());
    bound = true;
  }

  window.hayAsesorPreinicioInit = function hayAsesorPreinicioInit() {
    bound = false;
    bindEvents();
    cargarGrupos().catch(() => msg('Error de conexión', false));
  };

  if (document.getElementById('apg-recargar')) {
    window.hayAsesorPreinicioInit();
  }
})();
