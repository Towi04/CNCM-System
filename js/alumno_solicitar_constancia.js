(function () {

  const cfg = window.HAY_DOC_ALUMNO || {};

  const api = cfg.api || 'php/documento_api.php';

  const pdfBase = cfg.pdf || 'documento_pdf.php';

  let catalogo = { campos: {}, producto: null };



  function esc(s) {

    const d = document.createElement('div');

    d.textContent = s == null ? '' : String(s);

    return d.innerHTML;

  }



  function camposPorGrupo(campos) {

    const g = {};

    Object.entries(campos || {}).forEach(([k, m]) => {

      const grp = m.grupo || 'Otros';

      if (!g[grp]) g[grp] = [];

      g[grp].push({ id: k, ...m });

    });

    return g;

  }



  function renderOpciones() {

    const box = document.getElementById('doc-alumno-opciones');

    const manuales = document.getElementById('doc-alumno-manuales');

    if (!box) return;

    const grupos = camposPorGrupo(catalogo.campos);

    const defaultOn = ['nombre_completo', 'numero_control', 'especialidad', 'horario', 'calificaciones', 'tiempo_estudio', 'folio', 'qr_verificacion'];

    box.innerHTML = Object.entries(grupos).map(([grp, items]) => `

      <div class="doc-opciones-grupo">

        <strong>${esc(grp)}</strong>

        ${items.map((it) => `

          <label><input type="checkbox" class="doc-opc" value="${esc(it.id)}" ${defaultOn.includes(it.id) ? 'checked' : ''}> ${esc(it.label)}</label>

        `).join('')}

      </div>

    `).join('');



    const manualFields = Object.entries(catalogo.campos || {}).filter(([, m]) => m.manual);

    manuales.innerHTML = manualFields.map(([k, m]) => `

      <label>${esc(m.label)} <input type="text" class="doc-extra" data-campo="${esc(k)}" placeholder="Requerido si marca la casilla"></label>

    `).join('');



    const p = catalogo.producto;

    document.getElementById('doc-alumno-precio').innerHTML = p

      ? `<p><strong>Costo en recepción:</strong> ${esc(p.nombre)} — $${Number(p.precio || 0).toFixed(2)} MXN</p>`

      : '';

  }



  async function cargarCatalogo() {

    const r = await fetch(api + '?accion=catalogo', { credentials: 'same-origin' });

    const data = await r.json();

    if (data.status === 'ok') {

      catalogo.campos = data.campos || {};

      catalogo.producto = data.producto;

      renderOpciones();

    }

  }



  async function cargarLista() {

    const box = document.getElementById('doc-alumno-lista');

    const r = await fetch(api + '?accion=mis_solicitudes', { credentials: 'same-origin' });

    const data = await r.json();

    if (data.status !== 'ok') {

      box.innerHTML = '<p style="color:#b71c1c;">Error al cargar</p>';

      return;

    }

    const docs = data.documentos || [];

    if (!docs.length) {

      box.innerHTML = '<p style="color:#888;">Sin solicitudes aún.</p>';

      return;

    }

    box.innerHTML = docs.map((d) => {

      const puede = d.puede_ver;

      return `<div class="doc-item doc-estado-${esc(d.estado)}">

        <div><strong>${esc(d.folio)}</strong> · ${esc(d.estado)} ${d.vigente_hasta ? '· vigente hasta ' + esc(d.vigente_hasta) : ''}</div>

        <div style="font-size:0.88rem;color:#666;">${esc(d.solicitado_en)}</div>

        ${puede ? `<a class="primary" href="${pdfBase}?id=${d.id_documento}" target="_blank" rel="noopener">Ver / descargar PDF</a>` : '<span style="color:#888;">Pendiente de pago en recepción</span>'}

      </div>`;

    }).join('');

  }



  async function solicitar() {

    const opciones = [...document.querySelectorAll('.doc-opc:checked')].map((c) => c.value);

    const extra = {};

    document.querySelectorAll('.doc-extra').forEach((inp) => {

      if (opciones.includes(inp.dataset.campo)) extra[inp.dataset.campo] = inp.value.trim();

    });

    const fd = new FormData();

    fd.append('accion', 'solicitar');

    fd.append('opciones', JSON.stringify(opciones));

    fd.append('extra', JSON.stringify(extra));

    const r = await fetch(api, { method: 'POST', credentials: 'same-origin', body: fd });

    const data = await r.json();

    alert(data.message || (data.status === 'ok' ? 'Solicitud enviada' : 'Error'));

    if (data.status === 'ok') cargarLista();

  }



  document.getElementById('btn-doc-solicitar')?.addEventListener('click', solicitar);

  cargarCatalogo().then(cargarLista);

})();

