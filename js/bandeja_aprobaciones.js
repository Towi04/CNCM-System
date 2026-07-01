(function () {
  const cfg = window.HAY_BANDEJA_CONFIG || {};
  const api = cfg.api || 'php/bandeja_aprobaciones_api.php';
  let filtro = cfg.filtroInicial || '';

  const elLista = document.getElementById('bandeja-lista');
  const elLoading = document.getElementById('bandeja-loading');
  const elMsg = document.getElementById('bandeja-msg');
  const elFiltros = document.getElementById('bandeja-filtros');

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function showMsg(ok, text) {
    if (!elMsg) return;
    elMsg.style.display = 'block';
    elMsg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    elMsg.textContent = text || '';
  }

  function setLoading(on) {
    if (elLoading) elLoading.hidden = !on;
    if (elLista) elLista.style.opacity = on ? '0.5' : '1';
  }

  function updateChips(resumen) {
    const map = {
      'chip-total': resumen.total,
      'chip-permisos': resumen.permisos,
      'chip-inscripciones': resumen.inscripciones,
      'chip-grupos': resumen.grupos,
    };
    Object.keys(map).forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.textContent = String(map[id] ?? 0);
    });
  }

  function renderPermiso(item) {
    const p = item.payload || {};
    const grupos = (p.grupos || []).map((g) => g.clave).join(', ');
    let html = '<div class="bandeja-item" data-tipo="permiso_profesor">';
    html += '<div class="bandeja-item__head">';
    html += '<span class="bandeja-item__tipo bandeja-item__tipo--permiso_profesor">' + esc(item.tipo_label) + '</span>';
    html += '<span class="bandeja-item__fecha">' + esc(item.fecha_fmt) + '</span></div>';
    html += '<h3 class="bandeja-item__titulo">' + esc(item.titulo) + '</h3>';
    if (item.subtitulo) html += '<p class="bandeja-item__sub">' + esc(item.subtitulo) + '</p>';
    if (item.detalle || grupos) {
      html += '<p class="bandeja-item__det">' + esc(item.detalle || ('Grupos: ' + grupos)) + '</p>';
    }
    html += '<div class="bandeja-item__acciones">';
    if (p.puede_suplencia) {
      html += '<button type="button" class="secondary btn-bandeja-suplencia" data-titular="' + esc(p.id_profesor) + '"';
      html += ' data-desde="' + esc(p.fecha_inicio) + '" data-hasta="' + esc(p.fecha_fin) + '"';
      html += ' data-notas="' + esc('Permiso #' + p.id_solicitud + ': ' + (p.motivo || '')) + '"';
      html += ' data-grupo="' + esc((p.grupos && p.grupos[0]) ? p.grupos[0].id_grupo : 0) + '">';
      html += '<i class="fas fa-exchange-alt"></i> Registrar suplencia</button>';
    }
    html += '<input type="text" class="bandeja-item__comentario" placeholder="Comentario (opcional)" data-id="' + esc(p.id_solicitud) + '">';
    html += '<button type="button" class="primary btn-bandeja-permiso-ok" data-id="' + esc(p.id_solicitud) + '">Aprobar</button>';
    html += '<button type="button" class="secondary btn-bandeja-permiso-no" data-id="' + esc(p.id_solicitud) + '">Rechazar</button>';
    html += '</div></div>';
    return html;
  }

  function renderInscripcion(item) {
    const p = item.payload || {};
    let html = '<div class="bandeja-item" data-tipo="inscripcion">';
    html += '<div class="bandeja-item__head">';
    html += '<span class="bandeja-item__tipo bandeja-item__tipo--inscripcion">' + esc(item.tipo_label) + '</span>';
    html += '<span class="bandeja-item__fecha">' + esc(item.fecha_fmt) + '</span></div>';
    html += '<h3 class="bandeja-item__titulo">' + esc(item.titulo) + '</h3>';
    if (item.subtitulo) html += '<p class="bandeja-item__sub">' + esc(item.subtitulo) + '</p>';
    if (item.detalle) html += '<p class="bandeja-item__det">' + esc(item.detalle) + '</p>';
    html += '<div class="bandeja-item__acciones">';
    html += '<button type="button" class="primary btn-bandeja-insc-ok" data-id="' + esc(p.id_auth) + '">Aprobar</button>';
    html += '<button type="button" class="secondary btn-bandeja-insc-no" data-id="' + esc(p.id_auth) + '">Rechazar</button>';
    html += '</div></div>';
    return html;
  }

  function renderGrupo(item) {
    const p = item.payload || {};
    const disabled = p.cumple_minimo ? '' : ' disabled title="No alcanza el mínimo de alumnos"';
    let html = '<div class="bandeja-item" data-tipo="grupo_apertura">';
    html += '<div class="bandeja-item__head">';
    html += '<span class="bandeja-item__tipo bandeja-item__tipo--grupo_apertura">' + esc(item.tipo_label) + '</span>';
    html += '<span class="bandeja-item__fecha">Inicio ' + esc(item.fecha_fmt) + '</span></div>';
    html += '<h3 class="bandeja-item__titulo">' + esc(item.titulo) + '</h3>';
    if (item.subtitulo) html += '<p class="bandeja-item__sub">' + esc(item.subtitulo) + '</p>';
    if (item.detalle) html += '<p class="bandeja-item__det">' + esc(item.detalle) + '</p>';
    html += '<div class="bandeja-item__acciones">';
    html += '<button type="button" class="primary btn-bandeja-grupo-ok" data-id="' + esc(p.id_grupo) + '"' + disabled + '>Autorizar apertura</button>';
    html += '<button type="button" class="secondary btn-bandeja-grupo-pos" data-id="' + esc(p.id_grupo) + '" data-fecha="' + esc(p.fecha_inicio) + '">Posponer</button>';
    html += '</div></div>';
    return html;
  }

  function renderItems(items) {
    if (!elLista) return;
    if (!items || items.length === 0) {
      elLista.innerHTML = '<div class="bandeja-vacio"><i class="fas fa-check-circle"></i> No hay pendientes en esta categoría.</div>';
      return;
    }
    elLista.innerHTML = items.map((it) => {
      if (it.tipo === 'permiso_profesor') return renderPermiso(it);
      if (it.tipo === 'inscripcion') return renderInscripcion(it);
      if (it.tipo === 'grupo_apertura') return renderGrupo(it);
      return '';
    }).join('');
  }

  async function cargar() {
    setLoading(true);
    try {
      const url = api + '?action=listar' + (filtro ? '&filtro=' + encodeURIComponent(filtro) : '');
      const { data } = await hayFetchJson(url);
      if (data.status !== 'ok') {
        showMsg(false, data.message || 'Error al cargar');
        return;
      }
      if (data.resumen) updateChips(data.resumen);
      renderItems(data.items || []);
    } catch (e) {
      showMsg(false, e.message || 'Error de red');
    } finally {
      setLoading(false);
    }
  }

  async function postAction(body) {
    const fd = new FormData();
    Object.keys(body).forEach((k) => fd.append(k, body[k]));
    const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
    showMsg(data.status === 'ok', data.message || '');
    if (data.status === 'ok') {
      await cargar();
    }
    return data;
  }

  function bindEvents() {
    elFiltros?.addEventListener('click', (e) => {
      const btn = e.target.closest('.bandeja-chip');
      if (!btn) return;
      filtro = btn.dataset.filtro || '';
      elFiltros.querySelectorAll('.bandeja-chip').forEach((c) => c.classList.toggle('active', c === btn));
      cargar();
    });

    document.getElementById('bandeja-refrescar')?.addEventListener('click', () => cargar());

    elLista?.addEventListener('click', async (e) => {
      const supl = e.target.closest('.btn-bandeja-suplencia');
      if (supl && typeof cargarSeccion === 'function') {
        cargarSeccion('director_nomina', {
          tab: 'suplencias',
          sup_titular: supl.dataset.titular || '',
          sup_desde: supl.dataset.desde || '',
          sup_hasta: supl.dataset.hasta || '',
          sup_notas: supl.dataset.notas || '',
          sup_grupo: supl.dataset.grupo || '',
        });
        return;
      }

      const permOk = e.target.closest('.btn-bandeja-permiso-ok');
      if (permOk) {
        const id = permOk.dataset.id;
        const com = elLista.querySelector('.bandeja-item__comentario[data-id="' + id + '"]');
        await postAction({
          action: 'resolver_permiso',
          id_solicitud: id,
          estado: 'aprobado',
          comentario: com ? com.value : '',
        });
        return;
      }

      const permNo = e.target.closest('.btn-bandeja-permiso-no');
      if (permNo) {
        const id = permNo.dataset.id;
        const com = elLista.querySelector('.bandeja-item__comentario[data-id="' + id + '"]');
        await postAction({
          action: 'resolver_permiso',
          id_solicitud: id,
          estado: 'rechazado',
          comentario: com ? com.value : '',
        });
        return;
      }

      const inscOk = e.target.closest('.btn-bandeja-insc-ok');
      if (inscOk) {
        await postAction({ action: 'resolver_inscripcion', id_auth: inscOk.dataset.id, estado: 'aprobada' });
        return;
      }

      const inscNo = e.target.closest('.btn-bandeja-insc-no');
      if (inscNo) {
        const motivo = prompt('Motivo del rechazo (opcional):') || '';
        await postAction({
          action: 'resolver_inscripcion',
          id_auth: inscNo.dataset.id,
          estado: 'rechazada',
          motivo,
        });
        return;
      }

      const grpOk = e.target.closest('.btn-bandeja-grupo-ok');
      if (grpOk && !grpOk.disabled) {
        if (!confirm('¿Autorizar la apertura de este grupo en la fecha programada?')) return;
        await postAction({ action: 'autorizar_grupo', id_grupo: grpOk.dataset.id });
        return;
      }

      const grpPos = e.target.closest('.btn-bandeja-grupo-pos');
      if (grpPos) {
        const fechaActual = grpPos.dataset.fecha || '';
        const nueva = prompt(
          'Nueva fecha de inicio (AAAA-MM-DD).\nActual: ' + fechaActual
            + '\n\nLas colegiaturas ya pagadas se moverán al nuevo periodo.',
          ''
        );
        if (!nueva || !nueva.trim()) return;
        const motivo = prompt('Motivo del posponimiento (opcional):', 'No se alcanzó el mínimo de alumnos') || '';
        await postAction({
          action: 'posponer_grupo',
          id_grupo: grpPos.dataset.id,
          nueva_fecha: nueva.trim(),
          motivo,
        });
      }
    });
  }

  bindEvents();
  cargar();
})();
