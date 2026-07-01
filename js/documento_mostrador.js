(function () {

  const cfg = window.HAY_DOC_MOSTRADOR || {};

  const api = cfg.api || 'php/documento_api.php';

  const elQ = document.getElementById('doc-most-q');

  const elAlumno = document.getElementById('doc-most-alumno');

  const elLista = document.getElementById('doc-most-resultados');

  const elMsg = document.getElementById('doc-most-msg');



  function esc(s) {

    const d = document.createElement('div');

    d.textContent = s == null ? '' : String(s);

    return d.innerHTML;

  }



  function fmtFecha(s) {

    if (!s) return '—';

    return String(s).replace('T', ' ').slice(0, 16);

  }



  function renderDoc(d) {

    const acciones = [];

    if (d.puede_imprimir && d.pdf_url) {

      acciones.push(`<a class="primary" href="${esc(d.pdf_url)}" target="_blank" rel="noopener"><i class="fas fa-print"></i> Reimprimir PDF</a>`);

    }

    if (d.verify_url) {

      acciones.push(`<a class="secondary" href="${esc(d.verify_url)}" target="_blank" rel="noopener"><i class="fas fa-qrcode"></i> Verificación QR</a>`);

      acciones.push(`<button type="button" class="secondary btn-doc-copiar" data-url="${esc(d.verify_url)}"><i class="fas fa-link"></i> Copiar enlace</button>`);

    }

    if (d.puede_entregar) {
      acciones.push(`<button type="button" class="primary btn-doc-entregar" data-id="${d.id_documento}"><i class="fas fa-check"></i> Marcar entregado</button>`);
    } else if (d.entregado) {
      acciones.push('<span style="color:#2e7d32; font-size:0.88rem;"><i class="fas fa-check-circle"></i> Entregado</span>');
    }

    if (!acciones.length) {

      acciones.push('<span style="color:#888;">Pendiente de pago — cobrar en punto de venta</span>');

    }



    const vig = d.vigente ? '<span style="color:#2e7d32;">Vigente</span>' : (d.estado === 'pagada' ? '<span style="color:#9e9e9e;">Vencida</span>' : '');



    return `

      <div class="doc-item doc-estado-${esc(d.estado)}">

        <div>

          <strong>${esc(d.folio)}</strong> · ${esc(d.tipo_label)}

          <div style="font-size:0.9rem; color:#555; margin-top:4px;">

            ${esc(d.estado_label)} ${vig ? ' · ' + vig : ''}

          </div>

          <div style="font-size:0.85rem; color:#777; margin-top:2px;">

            Solicitada: ${esc(fmtFecha(d.solicitado_en))}

            ${d.pagado_en ? ' · Emitida: ' + esc(fmtFecha(d.pagado_en)) : ''}

            ${d.entregado_en ? ' · Entregada: ' + esc(fmtFecha(d.entregado_en)) : ''}

          </div>

        </div>

        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">

          ${acciones.join('')}

        </div>

      </div>

    `;

  }



  async function buscar() {

    const q = (elQ?.value || '').trim();

    if (!q) {

      if (elMsg) elMsg.textContent = 'Escriba número de control, nombre o folio.';

      return;

    }

    if (elMsg) elMsg.textContent = 'Buscando…';

    if (elLista) elLista.innerHTML = '';

    if (elAlumno) elAlumno.hidden = true;



    try {

      const url = new URL(api, window.location.href);

      url.searchParams.set('accion', 'mostrador_buscar');

      url.searchParams.set('q', q);

      const res = await fetch(url.toString(), { credentials: 'same-origin' });

      const data = await res.json();

      if (data.status !== 'ok') throw new Error(data.message || 'No encontrado');



      const al = data.alumno || {};

      if (elAlumno) {

        elAlumno.hidden = false;

        elAlumno.innerHTML = `<strong>${esc(al.nombre_completo || al.alumno_nombre || '')}</strong> · Control ${esc(al.numero_control || '—')}`;

      }



      const docs = data.documentos || [];

      if (!docs.length) {

        if (elLista) elLista.innerHTML = '<p style="color:#888;">Sin documentos registrados para este alumno.</p>';

      } else if (elLista) {

        elLista.innerHTML = docs.map(renderDoc).join('');

        elLista.querySelectorAll('.btn-doc-copiar').forEach((btn) => {

          btn.addEventListener('click', async () => {

            const link = btn.getAttribute('data-url') || '';

            try {

              await navigator.clipboard.writeText(link);

              btn.textContent = 'Copiado';

              setTimeout(() => { btn.innerHTML = '<i class="fas fa-link"></i> Copiar enlace'; }, 1500);

            } catch (e) {

              prompt('Copie el enlace:', link);

            }

          });

        });

        elLista.querySelectorAll('.btn-doc-entregar').forEach((btn) => {
          btn.addEventListener('click', async () => {
            if (!confirm('¿Confirmar entrega física del documento?')) return;
            btn.disabled = true;
            const fd = new FormData();
            fd.append('action', 'marcar_entrega');
            fd.append('id_documento', btn.dataset.id || '');
            try {
              const pisoApi = (window.HAY_DOC_MOSTRADOR && window.HAY_DOC_MOSTRADOR.piso_api)
                || 'php/operativo_piso_api.php';
              const { data } = await hayFetchJson(pisoApi, { method: 'POST', body: fd });
              if (elMsg) elMsg.textContent = data.message || '';
              if (data.status === 'ok') buscar();
              else btn.disabled = false;
            } catch (err) {
              if (elMsg) elMsg.textContent = err.message || 'Error';
              btn.disabled = false;
            }
          });
        });

      }



      if (elMsg) {

        elMsg.textContent = data.modo === 'folio'

          ? 'Resultado por folio / token.'

          : docs.length + ' documento(s) encontrado(s).';

      }

    } catch (err) {

      if (elAlumno) elAlumno.hidden = true;

      if (elLista) elLista.innerHTML = '';

      if (elMsg) elMsg.textContent = err.message || 'Error al buscar';

    }

  }



  document.getElementById('doc-most-btn')?.addEventListener('click', buscar);

  elQ?.addEventListener('keydown', (e) => {

    if (e.key === 'Enter') {

      e.preventDefault();

      buscar();

    }

  });

  if (cfg.q_inicial && elQ) {

    elQ.value = cfg.q_inicial;

    buscar();

  }

})();

