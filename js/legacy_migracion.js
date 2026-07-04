(function () {
  if (window.__hayLegacyMigracionInit) return;
  window.__hayLegacyMigracionInit = true;

  const cfg = window.HAY_LEGACY_MIG || {};
  const api = cfg.api || 'php/legacy_migracion_api.php';

  function apiUrl(path) {
    const base = typeof window.hayResolveAssetUrl === 'function'
      ? window.hayResolveAssetUrl(api)
      : api;
    return base + (path || '');
  }

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function accionBadge(accion) {
    const map = {
      insertar: 'legacy-mig-badge--ok',
      mapear: 'legacy-mig-badge--ok',
      omitir: 'legacy-mig-badge--skip',
      pendiente: 'legacy-mig-badge--warn',
      error: 'legacy-mig-badge--err',
    };
    const cls = map[accion] || '';
    return '<span class="legacy-mig-badge ' + cls + '">' + esc(accion) + '</span>';
  }

  let fases = [];
  let faseActiva = 'verificar';
  let ultimaPreview = null;

  async function fetchJson(url, opts) {
    const res = await fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }, opts || {}));
    return res.json();
  }

  async function cargarEstado() {
    const data = await fetchJson(apiUrl('?action=estado'));
    const el = document.getElementById('legacy-mig-estado');
    if (!el || data.status !== 'ok') {
      if (el) el.innerHTML = '<p class="legacy-mig-alert">No se pudo leer el estado.</p>';
      return;
    }
    const leg = data.conteos_legado || {};
    const hay = data.conteos_hay || {};
    const maps = data.mapas || {};
    let html = '<p><strong>Legado:</strong> ';
    Object.keys(leg).forEach((k) => { html += k + ': ' + leg[k] + ' · '; });
    html += '</p><p><strong>CNCM:</strong> ';
    Object.keys(hay).forEach((k) => { html += k + ': ' + hay[k] + ' · '; });
    html += '</p><p><strong>Importados (mapa):</strong> ';
    Object.keys(maps).forEach((k) => { html += k + ': ' + maps[k] + ' · '; });
    if (data.listo_para_datos) {
      html += '</p><p class="legacy-mig-ok"><i class="fas fa-check-circle"></i> Equivalencias listas para importar datos.</p>';
    } else if (data.legacy_conectado) {
      html += '</p><p class="legacy-mig-warn"><i class="fas fa-exclamation-triangle"></i> Faltan equivalencias de planteles o especialidades.</p>';
    } else {
      html += '</p><p class="legacy-mig-alert">Configure LEGACY_DB_* en config.local.php</p>';
    }
    el.innerHTML = html;
  }

  function renderFases(list) {
    fases = list;
    const nav = document.getElementById('legacy-mig-fases');
    if (!nav) return;
    nav.innerHTML = list.map((f) =>
      '<button type="button" class="legacy-mig-fase-btn' + (f.id === faseActiva ? ' is-active' : '') + '" data-fase="' + esc(f.id) + '">' +
      esc(f.titulo) + '</button>'
    ).join('');
  }

  function faseDef(id) {
    return fases.find((f) => f.id === id) || { titulo: id, descripcion: '' };
  }

  function mostrarFase(id) {
    faseActiva = id;
    document.querySelectorAll('.legacy-mig-fase-btn').forEach((btn) => {
      btn.classList.toggle('is-active', btn.getAttribute('data-fase') === id);
    });
    const def = faseDef(id);
    const head = document.getElementById('legacy-mig-panel-head');
    if (head) {
      head.innerHTML = '<h3>' + esc(def.titulo) + '</h3><p>' + esc(def.descripcion || '') + '</p>';
    }
    const equiv = document.getElementById('legacy-mig-equiv-wrap');
    if (equiv) equiv.hidden = id !== 'equivalencias';
    const btnAplicar = document.getElementById('legacy-mig-btn-aplicar');
    if (btnAplicar) {
      btnAplicar.disabled = id === 'verificar';
      btnAplicar.textContent = id === 'equivalencias' ? 'Solo configuración manual' : 'Aplicar esta fase';
    }
    ultimaPreview = null;
    document.getElementById('legacy-mig-resumen').hidden = true;
    document.getElementById('legacy-mig-advertencias').hidden = true;
    document.getElementById('legacy-mig-tbody').innerHTML =
      '<tr><td colspan="4" style="color:#888;">Pulse Previsualizar para ver cambios propuestos.</td></tr>';
  }

  function renderPreview(data) {
    ultimaPreview = data;
    const resEl = document.getElementById('legacy-mig-resumen');
    const advEl = document.getElementById('legacy-mig-advertencias');
    const tbody = document.getElementById('legacy-mig-tbody');
    const btnAplicar = document.getElementById('legacy-mig-btn-aplicar');

    if (data.fase === 'verificar' && data.estado) {
      resEl.hidden = true;
    } else if (data.resumen) {
      resEl.hidden = false;
      resEl.innerHTML =
        '<strong>Resumen:</strong> ' +
        'Insertar: <em>' + (data.resumen.insertar || 0) + '</em> · ' +
        'Omitir: <em>' + (data.resumen.omitir || 0) + '</em> · ' +
        'Errores: <em>' + (data.resumen.error || 0) + '</em>' +
        (data.listo === false ? ' · <span class="legacy-mig-warn">Equivalencias incompletas</span>' : '');
    }

    if (data.advertencias && data.advertencias.length) {
      advEl.hidden = false;
      advEl.innerHTML = '<strong>Advertencias:</strong><ul>' +
        data.advertencias.slice(0, 12).map((a) => '<li>' + esc(a) + '</li>').join('') +
        '</ul>';
    } else {
      advEl.hidden = true;
    }

    const rows = data.muestras || [];
    if (data.fase === 'verificar' && data.estado) {
      tbody.innerHTML = '<tr><td colspan="4">Revise el cuadro de estado arriba. Configure equivalencias si hay pendientes.</td></tr>';
    } else if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="4">Sin registros de muestra para esta fase (puede que ya esté importada).</td></tr>';
    } else {
      tbody.innerHTML = rows.map((r) =>
        '<tr>' +
        '<td>' + accionBadge(r.accion) + '</td>' +
        '<td>' + esc(r.legacy_label || '') + (r.legacy_id ? ' <small>(#' + r.legacy_id + ')</small>' : '') + '</td>' +
        '<td>' + esc(r.hay_label || '—') + '</td>' +
        '<td>' + esc(r.detalle || '') + '</td>' +
        '</tr>'
      ).join('');
    }

    if (btnAplicar && faseActiva !== 'verificar' && faseActiva !== 'equivalencias') {
      btnAplicar.disabled = data.listo === false;
    }
  }

  async function preview() {
    const data = await fetchJson(apiUrl('?action=preview&fase=' + encodeURIComponent(faseActiva)));
    if (data.status !== 'ok') {
      alert(data.message || 'Error en previsualización');
      return;
    }
    renderPreview(data);
  }

  async function aplicar() {
    if (faseActiva === 'equivalencias' || faseActiva === 'verificar') {
      alert('Configure equivalencias en las pantallas dedicadas.');
      return;
    }
    if (!ultimaPreview) {
      alert('Previsualice primero esta fase.');
      return;
    }
    if (!window.confirm('¿Aplicar la fase «' + faseDef(faseActiva).titulo + '»? Esta acción escribe en la base de datos CNCM.')) {
      return;
    }
    const fd = new FormData();
    fd.append('action', 'aplicar');
    fd.append('fase', faseActiva);
    const data = await fetchJson(apiUrl(), { method: 'POST', body: fd });
    if (data.status !== 'ok') {
      alert(data.message || 'Error al aplicar');
      return;
    }
    alert(data.message || 'Fase aplicada');
    await cargarEstado();
    await preview();
  }

  document.getElementById('legacy-mig-fases')?.addEventListener('click', (e) => {
    const btn = e.target.closest('.legacy-mig-fase-btn');
    if (!btn) return;
    mostrarFase(btn.getAttribute('data-fase'));
  });

  document.getElementById('legacy-mig-btn-preview')?.addEventListener('click', () => preview().catch((e) => alert(e.message)));
  document.getElementById('legacy-mig-btn-aplicar')?.addEventListener('click', () => aplicar().catch((e) => alert(e.message)));

  document.getElementById('legacy-mig-equiv-wrap')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-seccion]');
    if (!btn || typeof cargarSeccion !== 'function') return;
    cargarSeccion(btn.getAttribute('data-seccion'));
  });

  (async () => {
    try {
      const fData = await fetchJson(apiUrl('?action=fases'));
      if (fData.status === 'ok') renderFases(fData.fases || []);
      await cargarEstado();
      mostrarFase('verificar');
      await preview();
    } catch (e) {
      alert(e.message || 'Error al iniciar asistente');
    }
  })();
})();
