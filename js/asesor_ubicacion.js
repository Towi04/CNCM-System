(function () {
  const api = (window.HAY_ASESOR_UBICACION || {}).api || 'php/ubicacion_api.php';
  const msg = document.getElementById('ub-asesor-msg');
  const editor = document.getElementById('ub-asesor-editor');
  let items = [];
  const estLabels = {
    pendiente: 'Pendiente de evaluación',
    autorizado: 'Autorizado — pendiente de inscripción',
    rechazado: 'Rechazado / sin ubicación',
    usado: 'Inscrito en grupo autorizado',
  };

  function show(t, ok) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert catalog-alert--' + (ok ? 'ok' : 'error');
    msg.textContent = t;
  }

  function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function renderLista() {
    const box = document.getElementById('ub-asesor-lista');
    if (!box) return;
    if (!items.length) {
      box.innerHTML = '<p style="color:#888;">No hay alumnos con este filtro.</p>';
      return;
    }
    const activeId = parseInt(document.getElementById('ub-asesor-id')?.value || '0', 10);
    box.innerHTML = items.map((it) => {
      const grps = (it.grupos_autorizados || []).map((g) => g.clave).join(', ');
      const tel = it.telefono || '';
      return '<article class="ub-card' + (activeId === parseInt(it.id_ubicacion, 10) ? ' is-active' : '') + '" data-id="' + it.id_ubicacion + '">' +
        '<span class="ub-badge ub-badge--' + esc(it.estado) + '">' + esc(estLabels[it.estado] || it.estado) + '</span>' +
        '<strong>' + esc(it.alumno_nombre) + '</strong> <span style="color:#888;">#' + esc(it.numero_control || '') + '</span>' +
        '<div style="font-size:0.85rem; color:#555;">' + esc(it.esp_nombre) +
        (it.nivel_detectado ? ' · Nivel ' + esc(it.nivel_detectado) : '') + '</div>' +
        (tel ? '<div style="font-size:0.82rem; color:#666;"><i class="fas fa-phone"></i> ' + esc(tel) + '</div>' : '') +
        (grps ? '<div class="ub-card-grupos">Grupos: ' + esc(grps) + '</div>' : '') +
        '</article>';
    }).join('');
    box.querySelectorAll('.ub-card').forEach((card) => {
      card.addEventListener('click', () => cargarDetalle(parseInt(card.dataset.id, 10)));
    });
  }

  async function cargarLista() {
    const est = document.getElementById('ub-asesor-filtro')?.value || '';
    let url = api + '?action=listar_asesor';
    if (est) url += '&estado=' + encodeURIComponent(est);
    const r = await fetch(url, { credentials: 'same-origin' });
    const d = await r.json();
    if (d.status !== 'ok') {
      show(d.message || 'Error al cargar', false);
      return;
    }
    items = d.items || [];
    renderLista();
  }

  async function cargarDetalle(id) {
    if (!id) return;
    document.getElementById('ub-asesor-id').value = id;
    const r = await fetch(api + '?action=detalle_asesor&id=' + id, { credentials: 'same-origin' });
    const d = await r.json();
    if (d.status !== 'ok' || !d.ubicacion) {
      show(d.message || 'No se pudo cargar', false);
      return;
    }
    const u = d.ubicacion;
    const grps = d.grupos_autorizados || [];
    editor.hidden = false;
    renderLista();

    const info = document.getElementById('ub-asesor-info');
    const tel = u.telefono || '';
    const mail = u.email || '';
    info.innerHTML =
      '<div class="ub-alumno-info">' +
      '<p><strong>' + esc(u.alumno_nombre) + '</strong> · No. ' + esc(u.numero_control || '') + '</p>' +
      '<p>' + esc(u.esp_nombre) + (u.nivel_detectado ? ' · Nivel <strong>' + esc(u.nivel_detectado) + '</strong>' : '') + '</p>' +
      '<p>Estado: <strong>' + esc(estLabels[u.estado] || u.estado) + '</strong></p>' +
      (u.observaciones ? '<p style="font-size:0.88rem; color:#555;">' + esc(u.observaciones).replace(/\n/g, '<br>') + '</p>' : '') +
      '<p style="margin-top:10px;">' +
      (tel ? '<a href="tel:' + esc(tel) + '" class="secondary" style="margin-right:8px;"><i class="fas fa-phone"></i> Llamar</a>' : '') +
      (mail ? '<a href="mailto:' + esc(mail) + '" class="secondary"><i class="fas fa-envelope"></i> Correo</a>' : '') +
      '<button type="button" class="secondary" style="margin-left:8px;" onclick="cargarSeccion(\'alumno_detalle\', \'id=' + esc(u.id_alumno) + '\')"><i class="fas fa-user"></i> Ver perfil</button>' +
      '</p></div>';

    const pend = document.getElementById('ub-asesor-pendiente');
    const asig = document.getElementById('ub-asesor-asignar');
    const usado = document.getElementById('ub-asesor-usado');
    const sel = document.getElementById('ub-asesor-grupo');

    pend.style.display = u.estado === 'pendiente' ? '' : 'none';
    asig.style.display = u.estado === 'autorizado' ? '' : 'none';
    usado.style.display = u.estado === 'usado' ? '' : 'none';

    if (sel) {
      sel.innerHTML = '<option value="">— Elija grupo —</option>';
      grps.forEach((g) => {
        const o = document.createElement('option');
        o.value = g.id_grupo;
        const fase = g.nombre_fase || g.clave_fase || '';
        o.textContent = (g.clave || 'Grupo') + (fase ? ' · ' + fase : '');
        sel.appendChild(o);
      });
    }
  }

  document.getElementById('btn-ub-asesor-listar')?.addEventListener('click', cargarLista);
  document.getElementById('ub-asesor-filtro')?.addEventListener('change', cargarLista);

  document.getElementById('btn-ub-asesor-inscribir')?.addEventListener('click', async () => {
    const idUb = document.getElementById('ub-asesor-id')?.value;
    const idGrupo = document.getElementById('ub-asesor-grupo')?.value;
    if (!idUb || !idGrupo) {
      show('Seleccione un grupo autorizado', false);
      return;
    }
    if (!confirm('¿Inscribir al alumno en el grupo seleccionado?')) return;
    const fd = new FormData();
    fd.append('action', 'asignar_grupo_asesor');
    fd.append('id_ubicacion', idUb);
    fd.append('id_grupo', idGrupo);
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    show(d.message || (d.status === 'ok' ? 'Listo' : 'Error'), d.status === 'ok');
    if (d.status === 'ok') {
      await cargarLista();
      await cargarDetalle(parseInt(idUb, 10));
    }
  });

  cargarLista();
  const initId = parseInt(document.getElementById('ub-asesor-id')?.value || '0', 10);
  if (initId > 0) cargarDetalle(initId);
})();
