(function () {

  const cfg = window.HAY_DOC_PLANTILLA || {};

  const api = cfg.api || 'php/documento_api.php';

  let campos = [];

  let idActual = 0;



  function esc(s) {

    const d = document.createElement('div');

    d.textContent = s == null ? '' : String(s);

    return d.innerHTML;

  }



  function camposCatalogo() {

    const tipo = document.getElementById('doc-pl-tipo')?.value || 'constancia';

    return tipo === 'diploma' ? cfg.campos_diploma : cfg.campos_constancia;

  }



  function renderCamposList() {

    const box = document.getElementById('doc-pl-campos-list');

    const cat = camposCatalogo();

    box.innerHTML = campos.map((c, i) => {

      const opts = Object.entries(cat || {}).map(([k, m]) =>

        `<option value="${esc(k)}"${k === c.campo ? ' selected' : ''}>${esc(m.label || k)}</option>`

      ).join('');

      return `<div class="doc-pl-campo-row" data-i="${i}">

        <select class="c-campo">${opts}</select>

        <input type="number" class="c-x" placeholder="X mm" value="${c.x_mm ?? 20}" step="0.5" title="X mm">

        <input type="number" class="c-y" placeholder="Y mm" value="${c.y_mm ?? 20}" step="0.5" title="Y mm">

        <input type="number" class="c-fs" placeholder="Pt" value="${c.font_size ?? 11}" step="0.5" title="Tamaño">

        <select class="c-align"><option value="left"${c.align === 'left' ? ' selected' : ''}>Izq</option><option value="center"${c.align === 'center' ? ' selected' : ''}>Centro</option></select>

        <input type="number" class="c-w" placeholder="Ancho mm" value="${c.width_mm ?? 0}" step="1" title="Ancho">

        <button type="button" class="secondary btn-del-c">×</button>

      </div>`;

    }).join('');

    box.querySelectorAll('.btn-del-c').forEach((btn) => {

      btn.addEventListener('click', () => {

        campos.splice(Number(btn.closest('.doc-pl-campo-row')?.dataset.i), 1);

        renderCamposList();

      });

    });

  }



  function leerCamposDesdeDom() {

    return [...document.querySelectorAll('.doc-pl-campo-row')].map((row) => ({

      campo: row.querySelector('.c-campo')?.value || '',

      x_mm: parseFloat(row.querySelector('.c-x')?.value || '0'),

      y_mm: parseFloat(row.querySelector('.c-y')?.value || '0'),

      font_size: parseFloat(row.querySelector('.c-fs')?.value || '11'),

      align: row.querySelector('.c-align')?.value || 'left',

      width_mm: parseFloat(row.querySelector('.c-w')?.value || '0') || undefined,

    }));

  }



  function llenarSelectPlantillas() {

    const sel = document.getElementById('doc-pl-select');

    if (!sel) return;

    sel.innerHTML = '<option value="">— Nueva —</option>';

    (cfg.plantillas || []).forEach((p) => {

      const o = document.createElement('option');

      o.value = p.id_plantilla;

      o.textContent = (p.tipo === 'diploma' ? '[Diploma] ' : '[Constancia] ') + p.nombre;

      sel.appendChild(o);

    });

  }



  async function cargarPlantilla(id) {

    if (!id) {

      idActual = 0;

      campos = [];

      document.getElementById('doc-pl-nombre').value = '';

      renderCamposList();

      return;

    }

    const r = await fetch(api + '?accion=plantilla_obtener&id_plantilla=' + id, { credentials: 'same-origin' });

    const data = await r.json();

    if (data.status !== 'ok') return;

    const p = data.plantilla;

    idActual = Number(p.id_plantilla);

    document.getElementById('doc-pl-tipo').value = p.tipo || 'constancia';

    document.getElementById('doc-pl-nombre').value = p.nombre || '';

    document.getElementById('doc-pl-vigencia').value = p.vigencia_dias || 90;

    campos = Array.isArray(p.campos_json) ? p.campos_json : [];

    renderCamposList();

  }



  async function guardar() {

    campos = leerCamposDesdeDom();

    const fd = new FormData();

    fd.append('accion', 'plantilla_guardar');

    if (idActual) fd.append('id_plantilla', String(idActual));

    fd.append('tipo', document.getElementById('doc-pl-tipo')?.value || 'constancia');

    fd.append('nombre', document.getElementById('doc-pl-nombre')?.value || '');

    fd.append('vigencia_dias', document.getElementById('doc-pl-vigencia')?.value || '90');

    fd.append('campos_json', JSON.stringify(campos));

    const fondo = document.getElementById('doc-pl-fondo')?.files?.[0];

    const firma = document.getElementById('doc-pl-firma')?.files?.[0];

    if (fondo) fd.append('fondo', fondo);

    if (firma) fd.append('firma', firma);

    const r = await fetch(api, { method: 'POST', credentials: 'same-origin', body: fd });

    const data = await r.json();

    alert(data.message || '');

    if (data.status === 'ok') location.reload();

  }



  document.getElementById('doc-pl-select')?.addEventListener('change', (e) => cargarPlantilla(e.target.value));

  document.getElementById('doc-pl-tipo')?.addEventListener('change', () => { campos = []; renderCamposList(); });

  document.getElementById('btn-doc-pl-add-campo')?.addEventListener('click', () => {

    campos = leerCamposDesdeDom();

    campos.push({ campo: 'nombre_completo', x_mm: 25, y_mm: 80, font_size: 12, align: 'center', width_mm: 165 });

    renderCamposList();

  });

  document.getElementById('btn-doc-pl-guardar')?.addEventListener('click', guardar);



  llenarSelectPlantillas();

  renderCamposList();

})();

