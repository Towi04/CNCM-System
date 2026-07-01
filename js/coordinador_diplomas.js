(function () {

  const cfg = window.HAY_DOC_DIPLOMA || {};

  const api = cfg.api || 'php/documento_api.php';

  const pdfBase = cfg.pdf || 'documento_pdf.php';

  let diplomas = [];



  function esc(s) {

    const d = document.createElement('div');

    d.textContent = s == null ? '' : String(s);

    return d.innerHTML;

  }



  async function cargarLista() {

    const idGrupo = document.getElementById('doc-dip-grupo')?.value || '';

    const box = document.getElementById('doc-dip-lista');

    const btnZip = document.getElementById('btn-doc-dip-zip');

    if (!idGrupo) {

      box.innerHTML = '';

      if (btnZip) btnZip.disabled = true;

      return;

    }

    const r = await fetch(api + '?accion=diplomas_grupo&id_grupo=' + encodeURIComponent(idGrupo), { credentials: 'same-origin' });

    const data = await r.json();

    diplomas = data.diplomas || [];

    if (btnZip) btnZip.disabled = diplomas.length === 0;

    if (!diplomas.length) {

      box.innerHTML = '<p style="color:#888;">Sin diplomas generados para este grupo.</p>';

      return;

    }

    box.innerHTML = diplomas.map((d) => `

      <div class="doc-item">

        <strong>${esc(d.alumno_nombre)}</strong> · ${esc(d.numero_control)} · ${esc(d.folio)}

        <a class="secondary" href="${pdfBase}?id=${d.id_documento}" target="_blank" rel="noopener">PDF</a>

      </div>

    `).join('');

  }



  async function generar() {

    const idGrupo = document.getElementById('doc-dip-grupo')?.value || '';

    if (!idGrupo) { alert('Seleccione un grupo'); return; }

    if (!confirm('¿Generar diplomas para todos los alumnos del grupo?')) return;

    const fd = new FormData();

    fd.append('accion', 'diplomas_generar');

    fd.append('id_grupo', idGrupo);

    const r = await fetch(api, { method: 'POST', credentials: 'same-origin', body: fd });

    const data = await r.json();

    alert(data.message || '');

    if (data.status === 'ok') cargarLista();

  }



  document.getElementById('doc-dip-grupo')?.addEventListener('change', cargarLista);

  document.getElementById('btn-doc-dip-generar')?.addEventListener('click', generar);

  document.getElementById('btn-doc-dip-zip')?.addEventListener('click', () => {

    diplomas.forEach((d, i) => {

      setTimeout(() => window.open(pdfBase + '?id=' + d.id_documento, '_blank'), i * 400);

    });

  });

})();

