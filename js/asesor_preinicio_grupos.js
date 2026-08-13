(function () {
  const cfg = window.HAY_ASESOR_PREINICIO || {};
  const api = cfg.api || 'php/grupo_preinicio_api.php';
  let idGrupoActivo = 0;
  let claveGrupoActivo = '';
  let alumnosCache = [];
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
        claveGrupoActivo = g.clave || '';
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

  function telefonosAlumno(a) {
    const principal = a.telefono || '';
    const opcional = a.telefono2 || a.celular || '';
    return [principal, opcional].filter(Boolean);
  }

  function imprimirLista() {
    if (!alumnosCache.length) {
      msg('Seleccione un grupo con alumnos para imprimir.', false);
      return;
    }
    const filas = alumnosCache.map((a, i) => {
      const tels = telefonosAlumno(a);
      return '<tr>' +
        '<td style="text-align:center;">' + (i + 1) + '</td>' +
        '<td>' + esc(a.numero_control || '') + '</td>' +
        '<td>' + esc(a.nombre || '') + '</td>' +
        '<td>' + esc(tels[0] || '—') + '</td>' +
        '<td>' + esc(tels[1] || '—') + '</td>' +
        '<td>' + (Number(a.contactado) === 1 ? 'Sí' : '') + '</td>' +
        '</tr>';
    }).join('');
    const html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Contacto pre-inicio</title>' +
      '<style>body{font-family:Arial,sans-serif;font-size:12px;margin:16px;}h1{font-size:16px;margin:0 0 8px;}' +
      'table{width:100%;border-collapse:collapse;}th,td{border:1px solid #333;padding:4px 6px;}' +
      'th{background:#f0f0f0;}@media print{.noprint{display:none;}}</style></head><body>' +
      '<button class="noprint" onclick="window.print()">Imprimir</button>' +
      '<h1>Contacto pre-inicio — ' + esc(claveGrupoActivo || ('Grupo ' + idGrupoActivo)) + '</h1>' +
      '<p>Tel = principal · Tel 2 = opcional</p>' +
      '<table><thead><tr><th>#</th><th>Control</th><th>Nombre</th><th>Tel</th><th>Tel 2</th><th>Contactado</th></tr></thead>' +
      '<tbody>' + filas + '</tbody></table></body></html>';
    const w = window.open('', 'apg_print', 'width=900,height=700,scrollbars=yes');
    if (!w) {
      alert('Permita ventanas emergentes para imprimir la lista.');
      return;
    }
    w.document.write(html);
    w.document.close();
    setTimeout(() => { try { w.print(); } catch (_e) {} }, 250);
  }

  async function cargarAlumnos(idGrupo, clave) {
    const titulo = document.getElementById('apg-titulo-alumnos');
    if (titulo) titulo.textContent = clave ? 'Alumnos — ' + clave : 'Alumnos del grupo';
    const cont = document.getElementById('apg-lista-alumnos');
    const btnPrint = document.getElementById('apg-imprimir');
    if (!cont) return;
    cont.innerHTML = '<p style="color:#888;">Cargando…</p>';
    if (btnPrint) btnPrint.style.display = 'none';
    const d = await fetchJson(api + '?accion=listar_alumnos&id_grupo=' + encodeURIComponent(idGrupo));
    if (d.status === 'error') {
      alumnosCache = [];
      cont.innerHTML = '<p class="catalog-alert catalog-alert--error">' + esc(d.message) + '</p>';
      return;
    }
    cont.innerHTML = '';
    alumnosCache = d.alumnos || [];
    if (!alumnosCache.length) {
      cont.innerHTML = '<p style="color:#888;">Sin alumnos inscritos en este grupo.</p>';
      return;
    }
    if (btnPrint) btnPrint.style.display = '';
    alumnosCache.forEach((a) => {
      const row = document.createElement('div');
      row.className = 'apg-alumno-row' + (Number(a.contactado) === 1 ? ' is-done' : '');
      const tels = telefonosAlumno(a);
      const telTxt = tels.length
        ? ' · Tel: ' + tels[0] + (tels[1] ? ' · Tel 2: ' + tels[1] : '')
        : '';
      row.innerHTML =
        '<strong>' + esc(a.nombre) + '</strong> <span class="apg-alumno-meta">' +
        esc(a.numero_control) + esc(telTxt) +
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
    document.getElementById('apg-imprimir')?.addEventListener('click', () => imprimirLista());
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
