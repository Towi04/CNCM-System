(function () {

  const cfg = window.HAY_DOC_RECEPCION || {};

  const api = cfg.api || 'php/documento_api.php';



  function esc(s) {

    const d = document.createElement('div');

    d.textContent = s == null ? '' : String(s);

    return d.innerHTML;

  }



  async function cargar() {

    const tbody = document.querySelector('#doc-rec-tabla tbody');

    if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="color:#888;">Cargando…</td></tr>';

    const r = await fetch(api + '?accion=pendientes&estado=pendiente_pago', { credentials: 'same-origin' });

    const data = await r.json();

    if (data.status !== 'ok') {

      tbody.innerHTML = '<tr><td colspan="7" style="color:#b71c1c;">Error</td></tr>';

      return;

    }

    const docs = (data.documentos || []).filter((d) => d.estado === 'pendiente_pago');

    if (!docs.length) {

      tbody.innerHTML = '<tr><td colspan="7" style="color:#888;">No hay constancias pendientes.</td></tr>';

      return;

    }

    tbody.innerHTML = docs.map((d) => `

      <tr>

        <td><strong>${esc(d.folio)}</strong></td>

        <td>${esc(d.alumno_nombre)}</td>

        <td>${esc(d.numero_control)}</td>

        <td>${esc(d.producto_nombre || 'Constancia')}</td>

        <td>$${Number(d.precio || 0).toFixed(2)}</td>

        <td>${esc(d.solicitado_en)}</td>

        <td><button type="button" class="primary btn-doc-pagar" data-id="${d.id_documento}">Marcar pagada</button></td>

      </tr>

    `).join('');

    tbody.querySelectorAll('.btn-doc-pagar').forEach((btn) => {

      btn.addEventListener('click', async () => {

        if (!confirm('¿Confirmar pago y generar constancia?')) return;

        btn.disabled = true;

        const fd = new FormData();

        fd.append('accion', 'marcar_pagada');

        fd.append('id_documento', btn.dataset.id || '');

        const res = await fetch(api, { method: 'POST', credentials: 'same-origin', body: fd });

        const j = await res.json();

        alert(j.message || '');

        cargar();

      });

    });

  }



  document.getElementById('btn-doc-rec-cargar')?.addEventListener('click', cargar);

  cargar();

})();

