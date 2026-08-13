(function () {
  const cfg = window.HAY_MANUALES || {};
  const api = cfg.api || 'php/manuales_stock_api.php';
  const page = cfg.page || document.querySelector('[data-manuales-page]')?.dataset.manualesPage || 'stock';
  const $ = (id) => document.getElementById(id);
  let productos = [];
  let planteles = [];

  function esc(value) {
    const d = document.createElement('div');
    d.textContent = value == null ? '' : String(value);
    return d.innerHTML;
  }

  function msg(text, ok) {
    const el = $('manuales-msg');
    if (!el) return;
    el.hidden = !text;
    el.textContent = text || '';
    el.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
  }

  function fillSelect(sel, rows, valueKey, labelFn, empty) {
    if (!sel) return;
    sel.innerHTML = empty ? '<option value="">' + empty + '</option>' : '';
    rows.forEach((r) => {
      const o = document.createElement('option');
      o.value = r[valueKey];
      o.textContent = labelFn(r);
      sel.appendChild(o);
    });
  }

  async function cargarCatalogos() {
    const { data } = await hayFetchJson(api + '?action=catalogos');
    if (data.status !== 'ok') throw new Error(data.message || 'No se cargaron catálogos');
    productos = data.productos || [];
    planteles = data.planteles || [];
    fillSelect($('manuales-stock-producto'), productos, 'id_producto', (p) => (p.clave ? p.clave + ' · ' : '') + p.nombre);
    fillSelect($('manuales-envio-producto'), productos, 'id_producto', (p) => (p.clave ? p.clave + ' · ' : '') + p.nombre);
    fillSelect($('manuales-stock-plantel'), planteles, 'id_plantel', (p) => p.nombre, '— Bodega central —');
    fillSelect($('manuales-envio-plantel'), planteles, 'id_plantel', (p) => p.nombre, '— Seleccione —');
  }

  async function cargarStock() {
    if (page !== 'stock') return;
    const { data } = await hayFetchJson(api + '?action=stock');
    if (data.status !== 'ok') throw new Error(data.message || 'No se cargó stock');
    const tbody = $('manuales-stock-tabla')?.querySelector('tbody');
    if (!tbody) return;
    const rows = data.items || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="4" style="color:#888;">Sin productos activos.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map((r) => '<tr>'
      + '<td><strong>' + esc(r.nombre) + '</strong><br><small>' + esc(r.clave || '') + '</small></td>'
      + '<td>' + esc(r.bodega || 0) + '</td>'
      + '<td>' + esc(r.transito || 0) + '</td>'
      + '<td>' + esc(r.planteles || 0) + '</td>'
      + '</tr>').join('');
  }

  async function cargarEnvios() {
    if (page !== 'envios') return;
    const { data } = await hayFetchJson(api + '?action=envios');
    if (data.status !== 'ok') throw new Error(data.message || 'No se cargaron envíos');
    const tbody = $('manuales-envios-tabla')?.querySelector('tbody');
    if (!tbody) return;
    const rows = data.items || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7" style="color:#888;">Sin envíos registrados.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map((e) => {
      const recibido = e.recibido_en
        ? esc(String(e.recibido_en).slice(0, 16)) + '<br><small>' + esc(e.recibido_por_nombre || '') + '</small>'
        : '—';
      const accion = e.estado !== 'recibido'
        ? '<button type="button" class="primary btn-manual-confirmar" data-id="' + esc(e.id) + '">Confirmar recepción</button>'
        : '<span style="color:#2e7d32;">Recibido</span>';
      return '<tr>'
        + '<td>' + esc(e.estado || '') + '</td>'
        + '<td><strong>' + esc(e.producto_nombre || '') + '</strong><br><small>' + esc(e.producto_clave || '') + '</small></td>'
        + '<td>' + esc(e.plantel_nombre || '') + '</td>'
        + '<td>' + esc(e.cantidad || 0) + '</td>'
        + '<td>' + esc((e.enviado_en || '').slice(0, 16)) + '<br><small>' + esc(e.enviado_por_nombre || '') + '</small></td>'
        + '<td>' + recibido + '</td>'
        + '<td>' + accion + '</td>'
        + '</tr>';
    }).join('');
  }

  function bindStock() {
    const ubic = $('manuales-stock-ubicacion');
    const plantel = $('manuales-stock-plantel');
    function syncPlantel() {
      if (!ubic || !plantel) return;
      plantel.disabled = ubic.value === 'bodega';
      if (plantel.disabled) plantel.value = '';
    }
    ubic?.addEventListener('change', syncPlantel);
    syncPlantel();

    $('manuales-stock-form')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.currentTarget);
      fd.append('action', 'guardar_stock');
      try {
        const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
        msg(data.message || '', data.status === 'ok');
        if (data.status === 'ok') cargarStock();
      } catch (e) {
        msg(e.message || 'Error al guardar', false);
      }
    });
  }

  function bindEnvios() {
    $('manuales-envio-form')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.currentTarget);
      fd.append('action', 'crear_envio');
      try {
        const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
        msg(data.message || '', data.status === 'ok');
        if (data.status === 'ok') {
          ev.currentTarget.reset();
          cargarEnvios();
        }
      } catch (e) {
        msg(e.message || 'Error al crear envío', false);
      }
    });

    $('manuales-envios-tabla')?.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('.btn-manual-confirmar');
      if (!btn) return;
      if (!confirm('¿Confirmar recepción de manuales en plantel?')) return;
      const fd = new FormData();
      fd.append('action', 'confirmar_envio');
      fd.append('id_envio', btn.dataset.id || '');
      try {
        const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
        msg(data.message || '', data.status === 'ok');
        if (data.status === 'ok') cargarEnvios();
      } catch (e) {
        msg(e.message || 'Error al confirmar', false);
      }
    });
  }

  cargarCatalogos()
    .then(() => Promise.all([cargarStock(), cargarEnvios()]))
    .catch((e) => msg(e.message || 'Error al cargar', false));
  bindStock();
  bindEnvios();
})();
