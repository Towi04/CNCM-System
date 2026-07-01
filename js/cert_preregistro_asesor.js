(function () {
  const cfg = window.HAY_CERT_PREREG || {};
  const api = cfg.api || 'php/certificacion_api.php';

  const BASE_KEYS = new Set([
    'nombre', 'nombres', 'apellido_paterno', 'apellido_materno',
    'telefono', 'email', 'nombre_completo', 'certificacion',
  ]);

  let camposAsesor = [];
  let bound = false;

  async function fetchJson(url, opts) {
    if (typeof window.hayFetchJson === 'function') {
      const { data } = await window.hayFetchJson(url, Object.assign({
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' },
      }, opts || {}));
      return data;
    }
    const r = await fetch(url, Object.assign({
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' },
    }, opts || {}));
    return r.json();
  }

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
  }

  function showMsg(text, ok) {
    const el = document.getElementById('cpr-msg');
    if (!el) return;
    el.hidden = false;
    el.textContent = text;
    el.className = ok ? 'catalog-alert catalog-alert--ok' : 'catalog-alert catalog-alert--error';
  }

  function filtrarCampos(campos) {
    return (campos || []).filter((c) => {
      const clave = c.clave_campo || '';
      if (!clave || BASE_KEYS.has(clave)) return false;
      if (c.categoria === 'acceso_supervisor') return false;
      return true;
    });
  }

  function inputHtml(c) {
    const name = 'cf_' + c.clave_campo;
    const req = c.obligatorio ? ' required' : '';
    if (c.tipo === 'bool') {
      return '<input type="checkbox" name="' + name + '">';
    }
    if (c.tipo === 'date') {
      return '<input type="date" name="' + name + '"' + req + '>';
    }
    if (c.tipo === 'time') {
      return '<input type="time" name="' + name + '"' + req + '>';
    }
    if (c.tipo === 'email') {
      return '<input type="email" name="' + name + '"' + req + '>';
    }
    if (c.tipo === 'phone') {
      return '<input type="tel" name="' + name + '"' + req + '>';
    }
    return '<input name="' + name + '"' + req + '>';
  }

  async function cargarCatalogo() {
    const d = await fetchJson(api + '?action=catalogo');
    const sel = document.getElementById('cpr-producto');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Seleccione —</option>';
    (d.certificaciones || []).forEach((c) => {
      const o = document.createElement('option');
      o.value = c.id_producto;
      o.textContent = c.nombre + (c.precio ? ' ($' + c.precio + ')' : '');
      sel.appendChild(o);
    });
    const btn = document.getElementById('cpr-btn-nuevo');
    if (btn) btn.disabled = false;
  }

  async function mostrarPrecioReferencia(idProducto) {
    const box = document.getElementById('cpr-precio-ref');
    if (!box) return;
    try {
      const d = await fetchJson(api + '?action=comision_defaults&id_producto=' + idProducto);
      if (d.status !== 'ok' || !d.defaults) {
        box.hidden = true;
        return;
      }
      const p = d.defaults.precio;
      box.hidden = false;
      box.textContent = p > 0
        ? 'Precio de lista: $' + Number(p).toFixed(2) + '. Las comisiones se aplican según la configuración del supervisor.'
        : 'Las comisiones se aplican según la configuración del supervisor.';
    } catch (_e) {
      box.hidden = true;
    }
  }

  async function cargarCampos(idProducto) {
    await mostrarPrecioReferencia(idProducto);
    const d = await fetchJson(api + '?action=detalle&id_producto=' + idProducto);
    camposAsesor = filtrarCampos(d.campos_asesor || []);
    const box = document.getElementById('cpr-campos-extra');
    if (!box) return;
    box.innerHTML = '';
    if (camposAsesor.length) {
      const tit = document.createElement('p');
      tit.style.cssText = 'width:100%; margin:8px 0 4px; color:#666; font-size:0.9rem;';
      tit.textContent = 'Datos adicionales de la certificación';
      box.appendChild(tit);
    }
    camposAsesor.forEach((c) => {
      const div = document.createElement('div');
      div.className = 'field';
      const req = c.obligatorio ? ' *' : '';
      div.innerHTML = '<label>' + esc(c.etiqueta) + req + '</label>' + inputHtml(c);
      box.appendChild(div);
    });
  }

  async function onNuevoRegistro() {
    const id = document.getElementById('cpr-producto')?.value;
    if (!id) {
      showMsg('Seleccione una certificación.', false);
      return;
    }
    const hid = document.getElementById('cpr-id-producto');
    if (hid) hid.value = id;
    const wrap = document.getElementById('cpr-form-wrap');
    if (wrap) wrap.hidden = false;
    const msg = document.getElementById('cpr-msg');
    if (msg) msg.hidden = true;
    await cargarCampos(id);
  }

  async function onSubmit(ev) {
    ev.preventDefault();
    const form = ev.target;
    const fd = new FormData(form);
    fd.append('action', 'crear_solicitud');
    const datos = {};
    camposAsesor.forEach((c) => {
      const k = 'cf_' + c.clave_campo;
      if (c.tipo === 'bool') {
        datos[c.clave_campo] = fd.get(k) ? 1 : 0;
      } else if (fd.get(k)) {
        datos[c.clave_campo] = fd.get(k);
      }
    });
    fd.append('datos_formulario', JSON.stringify(datos));
    const btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;
    try {
      const d = await fetchJson(api, { method: 'POST', body: fd });
      if (d.status === 'ok') {
        showMsg(d.message || 'Solicitud registrada correctamente.', true);
        form.reset();
        const id = document.getElementById('cpr-producto')?.value;
        if (id) await cargarCampos(id);
      } else {
        showMsg(d.message || 'No se pudo registrar la solicitud.', false);
      }
    } catch (err) {
      showMsg(err.message || 'Error de conexión al registrar.', false);
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  function bindEvents() {
    if (bound) return;
    const btnNuevo = document.getElementById('cpr-btn-nuevo');
    const form = document.getElementById('cpr-form');
    if (!btnNuevo || !form) return;
    btnNuevo.addEventListener('click', onNuevoRegistro);
    form.addEventListener('submit', onSubmit);
    bound = true;
  }

  window.hayCertPreregistroInit = function hayCertPreregistroInit() {
    bound = false;
    bindEvents();
    cargarCatalogo().catch((err) => showMsg(err.message || 'Error al cargar catálogo.', false));
  };
})();
