(function () {
  if (window.__hayInicioPanelInit) {
    return;
  }
  window.__hayInicioPanelInit = true;

  const api = (window.HAY_INICIO_PANEL && window.HAY_INICIO_PANEL.api)
    || 'php/operativo_panel_api.php';

  let sugTimer = null;

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function navSeccion(seccion, params) {
    if (typeof cargarSeccion !== 'function') {
      return;
    }
    if (params && typeof params === 'object' && !(params instanceof URLSearchParams)) {
      const p = new URLSearchParams();
      Object.keys(params).forEach((k) => {
        if (params[k] != null && params[k] !== '') {
          p.set(k, String(params[k]));
        }
      });
      cargarSeccion(seccion, p);
      return;
    }
    cargarSeccion(seccion, params || null);
  }

  function parseNavParams(raw) {
    const seccion = raw.split('&')[0];
    const params = {};
    raw.split('&').filter(Boolean).forEach((p) => {
      const [k, v] = p.split('=');
      if (k && k !== seccion) {
        params[k] = decodeURIComponent(v || '');
      }
    });
    return { seccion, params };
  }

  document.addEventListener('click', (e) => {
    const kpi = e.target.closest('.inicio-panel__kpi, .inicio-panel__link');
    if (kpi) {
      const raw = kpi.dataset.query || '';
      const seccion = kpi.dataset.seccion || raw.split('&')[0];
      const { params } = parseNavParams(raw || seccion);
      navSeccion(seccion, Object.keys(params).length ? params : null);
      return;
    }

    const act = e.target.closest('[data-inicio-accion]');
    if (act) {
      const seccion = act.getAttribute('data-inicio-accion');
      const control = act.getAttribute('data-control') || '';
      const id = act.getAttribute('data-id-alumno') || '';
      if (seccion === 'consulta_adeudo') {
        navSeccion(seccion, new URLSearchParams({ control }));
      } else if (seccion === 'punto_venta') {
        const p = new URLSearchParams();
        if (control) {
          p.set('control', control);
        }
        if (id) {
          p.set('id_alumno', id);
        }
        navSeccion(seccion, p);
      } else if (seccion === 'documento_mostrador') {
        navSeccion(seccion, new URLSearchParams({ q: control }));
      } else if (seccion === 'alumno_detalle') {
        navSeccion(seccion, new URLSearchParams({ id }));
      }
      return;
    }

    const sug = e.target.closest('.inicio-panel__sug-item');
    if (sug) {
      const inp = document.getElementById('inicio-buscar-q');
      const q = sug.getAttribute('data-q') || '';
      if (inp) {
        inp.value = q;
      }
      hideSugerencias();
      buscarAlumno(q);
    }
  });

  function hideSugerencias() {
    const box = document.getElementById('inicio-buscar-sug');
    if (box) {
      box.hidden = true;
      box.innerHTML = '';
    }
  }

  async function cargarSugerencias(q) {
    const box = document.getElementById('inicio-buscar-sug');
    if (!box || q.length < 2) {
      hideSugerencias();
      return;
    }
    const url = new URL(api, window.location.href);
    url.searchParams.set('accion', 'sugerencias');
    url.searchParams.set('q', q);
    try {
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const data = await res.json();
      const rows = data.sugerencias || [];
      if (!rows.length) {
        hideSugerencias();
        return;
      }
      box.hidden = false;
      box.innerHTML = rows.map((a) => {
        const ctrl = a.numero_control || a.matricula || '';
        const label = '#' + ctrl + ' · ' + (a.nombre_completo || '');
        return `<button type="button" class="inicio-panel__sug-item" data-q="${esc(ctrl || a.nombre_completo || '')}">${esc(label)}</button>`;
      }).join('');
    } catch (err) {
      hideSugerencias();
    }
  }

  function renderResultado(data) {
    const box = document.getElementById('inicio-buscar-resultado');
    if (!box) {
      return;
    }
    if (data.status !== 'ok') {
      let html = `<p class="inicio-panel__buscar-msg inicio-panel__buscar-msg--err">${esc(data.message || 'No encontrado')}</p>`;
      if (data.sugerencias && data.sugerencias.length) {
        html += '<div class="inicio-panel__sug-inline">' + data.sugerencias.map((a) => {
          const ctrl = a.numero_control || a.matricula || '';
          return `<button type="button" class="secondary inicio-panel__sug-item" data-q="${esc(ctrl || a.nombre_completo || '')}">#${esc(ctrl)} ${esc(a.nombre_completo || '')}</button>`;
        }).join('') + '</div>';
      }
      box.innerHTML = html;
      box.hidden = false;
      return;
    }

    const al = data.alumno || {};
    const adeudoClass = data.tiene_adeudo ? 'inicio-panel__adeudo--si' : 'inicio-panel__adeudo--no';
    const grupos = (data.grupos || []).length
      ? data.grupos.map((g) => `<li>${esc(g)}</li>`).join('')
      : '<li style="color:#888;">Sin grupo activo</li>';

    let pendHtml = '';
    if ((data.pendientes || []).length) {
      pendHtml = '<ul class="inicio-panel__pend-list">' + data.pendientes.map((p) =>
        `<li><span>${esc(p.concepto)}</span><strong>${esc(p.saldo_fmt || p.saldo)}</strong></li>`
      ).join('') + '</ul>';
    } else {
      pendHtml = '<p style="color:#888;margin:0;">Sin saldos pendientes registrados.</p>';
    }

    let docsHtml = '';
    if ((data.documentos || []).length) {
      docsHtml = '<ul class="inicio-panel__doc-list">' + data.documentos.map((d) =>
        `<li>${esc(d.folio)} · ${esc(d.tipo)} — ${esc(d.estado)}`
        + (d.pdf_url ? ` <a href="${esc(d.pdf_url)}" target="_blank" rel="noopener">PDF</a>` : '')
        + '</li>'
      ).join('') + '</ul>';
    }

    const ult = data.ultimo_pago;
    const ultHtml = ult
      ? `${esc(ult.fecha_fmt)} · ${esc(ult.monto_fmt)} · ${esc(ult.concepto)}`
      : 'Sin pagos registrados';

    const acciones = [
      `<button type="button" class="primary" data-inicio-accion="consulta_adeudo" data-control="${esc(data.control || '')}"><i class="fas fa-calculator"></i> Adeudo completo</button>`,
    ];
    if (data.puede_pos) {
      acciones.push(`<button type="button" class="primary" data-inicio-accion="punto_venta" data-control="${esc(data.control || '')}" data-id-alumno="${esc(al.id_alumno || '')}"><i class="fas fa-cash-register"></i> Cobrar en POS</button>`);
    }
    if (data.puede_mostrador) {
      acciones.push(`<button type="button" class="secondary" data-inicio-accion="documento_mostrador" data-control="${esc(data.control || '')}"><i class="fas fa-id-card"></i> Documentos</button>`);
    }
    acciones.push(`<button type="button" class="secondary" data-inicio-accion="alumno_detalle" data-id-alumno="${esc(al.id_alumno || '')}"><i class="fas fa-user"></i> Perfil</button>`);

    box.innerHTML = `
      <div class="inicio-panel__res-card">
        <div class="inicio-panel__res-head">
          <div>
            <strong class="inicio-panel__res-nombre">${esc(al.nombre)}</strong>
            <div class="inicio-panel__res-meta">#${esc(al.numero_control)} · ${esc(al.estado || '')}${al.especialidad ? ' · ' + esc(al.especialidad) : ''}</div>
          </div>
          <div class="inicio-panel__adeudo ${adeudoClass}">
            <small>Adeudo total</small>
            <span>${esc(data.adeudo_fmt || '$ 0.00')}</span>
          </div>
        </div>
        <div class="inicio-panel__res-grid">
          <div>
            <h4>Grupos activos</h4>
            <ul class="inicio-panel__grupos">${grupos}</ul>
          </div>
          <div>
            <h4>Último pago</h4>
            <p class="inicio-panel__ult-pago">${ultHtml}</p>
            ${data.constancias_pendientes > 0 ? `<p class="inicio-panel__badge-const">${data.constancias_pendientes} constancia(s) por cobrar</p>` : ''}
          </div>
          <div class="inicio-panel__res-wide">
            <h4>Pendientes</h4>
            ${pendHtml}
          </div>
          ${docsHtml ? `<div class="inicio-panel__res-wide"><h4>Documentos recientes</h4>${docsHtml}</div>` : ''}
        </div>
        <div class="inicio-panel__res-acciones">${acciones.join('')}</div>
      </div>
    `;
    box.hidden = false;
  }

  async function buscarAlumno(q) {
    const box = document.getElementById('inicio-buscar-resultado');
    const qTrim = (q || '').trim();
    if (!qTrim) {
      if (box) {
        box.hidden = true;
        box.innerHTML = '';
      }
      return;
    }
    if (box) {
      box.hidden = false;
      box.innerHTML = '<p class="inicio-panel__buscar-msg"><i class="fas fa-spinner fa-spin"></i> Buscando…</p>';
    }
    hideSugerencias();
    const url = new URL(api, window.location.href);
    url.searchParams.set('accion', 'buscar_alumno');
    url.searchParams.set('q', qTrim);
    try {
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const data = await res.json();
      renderResultado(data);
    } catch (err) {
      renderResultado({ status: 'error', message: err.message || 'Error de conexión' });
    }
  }

  document.addEventListener('input', (e) => {
    if (e.target.id !== 'inicio-buscar-q') {
      return;
    }
    clearTimeout(sugTimer);
    const q = e.target.value.trim();
    sugTimer = setTimeout(() => cargarSugerencias(q), 280);
  });

  document.addEventListener('keydown', (e) => {
    if (e.target.id !== 'inicio-buscar-q') {
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      hideSugerencias();
      buscarAlumno(e.target.value);
    }
    if (e.key === 'Escape') {
      hideSugerencias();
    }
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.inicio-panel__buscar-wrap')) {
      hideSugerencias();
    }
  });

  document.addEventListener('click', (e) => {
    if (e.target.id === 'inicio-buscar-btn' || e.target.closest('#inicio-buscar-btn')) {
      const inp = document.getElementById('inicio-buscar-q');
      buscarAlumno(inp ? inp.value : '');
    }
  });
})();
