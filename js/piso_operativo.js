(function () {
  const cfg = window.HAY_PISO_CONFIG || {};
  const api = cfg.api || 'php/operativo_piso_api.php';
  const buscarApi = cfg.buscarApi || 'php/operativo_panel_api.php';
  let filtroTipo = '';

  const elAtajos = document.getElementById('piso-atajos');
  const elLista = document.getElementById('piso-entrega-lista');
  const elLoading = document.getElementById('piso-entrega-loading');
  const elMsg = document.getElementById('piso-msg');
  const elBadge = document.getElementById('piso-entrega-badge');
  const elFiltros = document.getElementById('piso-filtros');
  const elBuscarQ = document.getElementById('piso-buscar-q');
  const elBuscarAcc = document.getElementById('piso-buscar-acciones');

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

  function navSeccion(seccion, query) {
    if (typeof cargarSeccion !== 'function') return;
    if (query) {
      const p = new URLSearchParams();
      String(query).split('&').forEach((part) => {
        const [k, v] = part.split('=');
        if (k) p.set(k, decodeURIComponent(v || ''));
      });
      cargarSeccion(seccion, p);
      return;
    }
    cargarSeccion(seccion);
  }

  function renderAtajos(atajos) {
    if (!elAtajos) return;
    if (!atajos || !atajos.length) {
      elAtajos.innerHTML = '<p style="color:#888; grid-column:1/-1;">Sin pendientes de cobranza en este momento.</p>';
      return;
    }
    elAtajos.innerHTML = atajos.map((a) => {
      const cls = a.prioridad === 'alta' ? ' piso-atajo--alta' : '';
      return `<button type="button" class="piso-atajo${cls}" data-seccion="${esc(a.enlace)}" data-query="${esc(a.query || '')}">
        <i class="fas ${esc(a.icon || 'fa-circle')} piso-atajo__icon"></i>
        <span class="piso-atajo__val">${esc(a.valor)}</span>
        <span class="piso-atajo__lbl">${esc(a.titulo)}</span>
      </button>`;
    }).join('');
  }

  function fmtFecha(s) {
    if (!s) return '—';
    return String(s).replace('T', ' ').slice(0, 16);
  }

  function renderEntregaItem(d) {
    const tipo = d.tipo || 'constancia';
    let html = `<article class="piso-entrega-item piso-entrega-item--${esc(tipo)}" data-id="${d.id_documento}">`;
    html += '<div>';
    html += `<div class="piso-entrega-tipo">${esc(d.tipo_label || tipo)}</div>`;
    html += `<strong>${esc(d.alumno_nombre || '')}</strong>`;
    html += `<div style="font-size:0.88rem; color:#555; margin-top:4px;">`;
    html += `Control ${esc(d.numero_control || '—')} · Folio <strong>${esc(d.folio || '')}</strong>`;
    if (d.grupo_clave) html += ` · Grupo ${esc(d.grupo_clave)}`;
    html += '</div>';
    html += `<div style="font-size:0.82rem; color:#888; margin-top:4px;">Emitido: ${esc(fmtFecha(d.pagado_en || d.generado_en))}</div>`;
    html += '</div><div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">';
    if (d.pdf_url) {
      html += `<a class="secondary" href="${esc(d.pdf_url)}" target="_blank" rel="noopener"><i class="fas fa-print"></i> Imprimir</a>`;
    }
    html += `<button type="button" class="primary btn-piso-entregar" data-id="${d.id_documento}"><i class="fas fa-check"></i> Marcar entregado</button>`;
    html += `<button type="button" class="secondary btn-piso-pos" data-control="${esc(d.numero_control || '')}"><i class="fas fa-cash-register"></i> POS</button>`;
    html += '</div></article>';
    return html;
  }

  function renderEntrega(items, total) {
    if (elBadge) {
      elBadge.textContent = total > 0 ? `(${total} pendiente${total === 1 ? '' : 's'})` : '(al día)';
    }
    if (!elLista) return;
    if (!items || !items.length) {
      elLista.innerHTML = '<div class="piso-vacio"><i class="fas fa-check-circle"></i> No hay documentos pendientes de entrega física.</div>';
      return;
    }
    elLista.innerHTML = items.map(renderEntregaItem).join('');
  }

  async function cargarEntrega() {
    if (elLoading) elLoading.style.display = 'block';
    try {
      const url = api + '?action=listar_entrega' + (filtroTipo ? '&tipo=' + encodeURIComponent(filtroTipo) : '');
      const { data } = await hayFetchJson(url);
      if (data.status !== 'ok') {
        showMsg(false, data.message || 'Error al cargar entrega');
        return;
      }
      renderEntrega(data.items || [], data.total || 0);
    } catch (e) {
      showMsg(false, e.message || 'Error de red');
    } finally {
      if (elLoading) elLoading.style.display = 'none';
    }
  }

  async function cargarResumen() {
    try {
      const { data } = await hayFetchJson(api + '?action=resumen');
      if (data.status === 'ok' && data.resumen) {
        renderAtajos(data.resumen.atajos || []);
        if (elBadge && !elLista?.children?.length) {
          const t = data.resumen.entrega_total || 0;
          elBadge.textContent = t > 0 ? `(${t} pendiente${t === 1 ? '' : 's'})` : '(al día)';
        }
      }
    } catch (e) {
      /* ignore */
    }
  }

  async function marcarEntrega(id) {
    const fd = new FormData();
    fd.append('action', 'marcar_entrega');
    fd.append('id_documento', id);
    const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
    showMsg(data.status === 'ok', data.message || '');
    if (data.status === 'ok') {
      await Promise.all([cargarEntrega(), cargarResumen()]);
    }
  }

  async function buscarAlumno() {
    const q = (elBuscarQ?.value || '').trim();
    if (!q) {
      showMsg(false, 'Escriba control o nombre del alumno');
      return;
    }
    if (elBuscarAcc) {
      elBuscarAcc.hidden = true;
      elBuscarAcc.innerHTML = '';
    }
    try {
      const url = buscarApi + '?accion=buscar_alumno&q=' + encodeURIComponent(q);
      const { data } = await hayFetchJson(url);
      if (data.status !== 'ok') {
        showMsg(false, data.message || 'No encontrado');
        return;
      }
      const control = data.control || '';
      const id = data.id_alumno || '';
      const btns = [];
      btns.push(`<button type="button" class="secondary" data-nav="consulta_adeudo" data-control="${esc(control)}"><i class="fas fa-calculator"></i> Adeudo</button>`);
      btns.push(`<button type="button" class="primary" data-nav="punto_venta" data-control="${esc(control)}" data-id="${esc(id)}"><i class="fas fa-cash-register"></i> Cobrar en POS</button>`);
      btns.push(`<button type="button" class="secondary" data-nav="documento_mostrador" data-q="${esc(control)}"><i class="fas fa-id-card"></i> Documentos</button>`);
      if (elBuscarAcc) {
        elBuscarAcc.hidden = false;
        elBuscarAcc.innerHTML = `<span style="align-self:center; margin-right:8px;"><strong>${esc(data.nombre || '')}</strong> · #${esc(control)} · Adeudo ${esc(data.adeudo_fmt || '$ 0.00')}</span>` + btns.join('');
      }
      showMsg(true, 'Alumno encontrado — use los atajos para cobrar o ver documentos.');
    } catch (e) {
      showMsg(false, e.message || 'Error al buscar');
    }
  }

  function bindEvents() {
    document.getElementById('piso-refrescar')?.addEventListener('click', () => {
      cargarResumen();
      cargarEntrega();
    });

    elAtajos?.addEventListener('click', (e) => {
      const btn = e.target.closest('.piso-atajo');
      if (!btn) return;
      navSeccion(btn.dataset.seccion || '', btn.dataset.query || '');
    });

    elFiltros?.addEventListener('click', (e) => {
      const chip = e.target.closest('.piso-chip');
      if (!chip) return;
      filtroTipo = chip.dataset.tipo || '';
      elFiltros.querySelectorAll('.piso-chip').forEach((c) => c.classList.toggle('active', c === chip));
      cargarEntrega();
    });

    elLista?.addEventListener('click', (e) => {
      const ent = e.target.closest('.btn-piso-entregar');
      if (ent) {
        if (!confirm('¿Confirmar entrega física al alumno o familiar autorizado?')) return;
        marcarEntrega(ent.dataset.id || '');
        return;
      }
      const pos = e.target.closest('.btn-piso-pos');
      if (pos && pos.dataset.control) {
        navSeccion('punto_venta', 'control=' + encodeURIComponent(pos.dataset.control));
      }
    });

    document.getElementById('piso-buscar-btn')?.addEventListener('click', buscarAlumno);
    elBuscarQ?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        buscarAlumno();
      }
    });

    elBuscarAcc?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-nav]');
      if (!btn) return;
      const seccion = btn.getAttribute('data-nav');
      if (seccion === 'punto_venta') {
        const p = new URLSearchParams();
        if (btn.dataset.control) p.set('control', btn.dataset.control);
        if (btn.dataset.id) p.set('id_alumno', btn.dataset.id);
        navSeccion(seccion, p.toString());
      } else if (seccion === 'consulta_adeudo') {
        navSeccion(seccion, 'control=' + encodeURIComponent(btn.dataset.control || ''));
      } else if (seccion === 'documento_mostrador') {
        navSeccion(seccion, 'q=' + encodeURIComponent(btn.dataset.q || ''));
      }
    });
  }

  renderAtajos((cfg.resumen && cfg.resumen.atajos) || []);
  bindEvents();
  cargarEntrega();
  if (cfg.tabInicial === 'entrega') {
    document.getElementById('piso-entrega-lista')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
})();
