(function () {
  const cfg = window.HAY_GERENTE_ESCUELAS || {};
  const api = cfg.api || 'php/escuelas_api.php';
  let escuelas = [];
  let bound = false;

  function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
  }

  function msg(text, ok) {
    const el = document.getElementById('ge-msg');
    if (!el) return;
    el.style.display = text ? '' : 'none';
    el.className = 'catalog-alert catalog-alert--' + (ok ? 'ok' : 'error');
    el.textContent = text || '';
  }

  async function fetchJson(url, opts) {
    const r = await fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }, opts || {}));
    return r.json();
  }

  function limpiarForm() {
    const f = document.getElementById('ge-form-escuela');
    if (!f) return;
    f.reset();
    document.getElementById('ge-id-escuela').value = '0';
    document.getElementById('ge-form-titulo').textContent = 'Datos de escuela';
    const activo = f.querySelector('[name="activo"]');
    if (activo) activo.checked = true;
  }

  function llenarSelectVisitas() {
    const sel = document.getElementById('ge-visita-escuela');
    if (!sel) return;
    const cur = sel.value;
    sel.innerHTML = '<option value="">Seleccione</option>';
    escuelas.forEach((e) => {
      const opt = document.createElement('option');
      opt.value = e.id_escuela;
      opt.textContent = e.nombre;
      sel.appendChild(opt);
    });
    if (cur) sel.value = cur;
  }

  function renderLista() {
    const cont = document.getElementById('ge-lista-escuelas');
    if (!cont) return;
    cont.innerHTML = '';
    escuelas.forEach((e) => {
      const div = document.createElement('div');
      div.className = 'ge-item';
      div.innerHTML = '<strong>' + esc(e.nombre) + '</strong>' +
        '<small>' + esc(e.municipio || '—') + (Number(e.activo) ? '' : ' · Inactiva') + '</small>';
      div.addEventListener('click', () => {
        document.getElementById('ge-id-escuela').value = e.id_escuela;
        document.getElementById('ge-nombre').value = e.nombre || '';
        document.querySelector('[name="direccion"]').value = e.direccion || '';
        document.querySelector('[name="colonia"]').value = e.colonia || '';
        document.querySelector('[name="municipio"]').value = e.municipio || '';
        document.querySelector('[name="contacto_nombre"]').value = e.contacto_nombre || '';
        document.querySelector('[name="contacto_telefono"]').value = e.contacto_telefono || '';
        const activo = document.querySelector('[name="activo"]');
        if (activo) activo.checked = Number(e.activo) === 1;
        document.getElementById('ge-form-titulo').textContent = 'Editar escuela';
        document.getElementById('ge-visita-escuela').value = e.id_escuela;
      });
      cont.appendChild(div);
    });
    llenarSelectVisitas();
  }

  async function cargar() {
    const d = await fetchJson(api + '?accion=listar');
    if (d.status === 'error') {
      msg(d.message || 'Error', false);
      return;
    }
    escuelas = d.escuelas || [];
    renderLista();
  }

  function bindEvents() {
    if (bound) return;
    document.getElementById('ge-nueva')?.addEventListener('click', limpiarForm);
    document.getElementById('ge-cancelar')?.addEventListener('click', limpiarForm);

    document.getElementById('ge-form-escuela')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      fd.append('accion', 'guardar_escuela');
      if (!fd.has('activo')) fd.append('activo', '0');
      const d = await fetchJson(api, { method: 'POST', body: fd });
      msg(d.message || '', d.status === 'ok');
      if (d.status === 'ok') {
        limpiarForm();
        await cargar();
      }
    });

    document.getElementById('ge-form-visita')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      fd.append('accion', 'guardar_visita');
      const d = await fetchJson(api, { method: 'POST', body: fd });
      msg(d.message || '', d.status === 'ok');
      if (d.status === 'ok') e.target.reset();
    });

    bound = true;
  }

  window.hayGerenteEscuelasInit = function hayGerenteEscuelasInit() {
    bound = false;
    bindEvents();
    const fv = document.querySelector('#ge-form-visita [name="fecha_visita"]');
    if (fv && !fv.value) fv.value = new Date().toISOString().slice(0, 10);
    cargar().catch(() => msg('Error de conexión', false));
  };

  if (document.getElementById('ge-nueva')) {
    window.hayGerenteEscuelasInit();
  }
})();
