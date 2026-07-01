(function () {
  const cfg = window.HAY_REP_VENT_CONFIG || {};
  const api = cfg.api || 'php/reporte_financiero_api.php';

  let modo = 'dia';
  let cuenta = 'A';
  let ocultarB = false;
  let corteData = null;

  const elFecha = document.getElementById('rep-vent-fecha');
  const elEtiqueta = document.getElementById('rep-vent-etiqueta');
  const elTotal = document.getElementById('rep-vent-total');
  const elLoading = document.getElementById('rep-vent-loading');
  const tbody = document.querySelector('#rep-vent-tabla tbody');
  const modalCorte = document.getElementById('rep-vent-modal-corte');

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function fmtMxn(n) {
    return '$ ' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fmtFechaTabla(s) {
    if (!s) return '—';
    const d = new Date(String(s).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return esc(s);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return dd + '-' + mm + '-' + d.getFullYear();
  }

  function etiquetaForma(forma) {
    const f = String(forma || '').toLowerCase();
    if (f.includes('débito') || f.includes('debito')) return 'Tarjeta de debito';
    if (f.includes('crédito') || f.includes('credito')) return 'Tarjeta de credito';
    if (f.includes('transfer')) return 'Transferencia';
    if (f.includes('tarjeta')) return 'Tarjeta de debito';
    if (f.includes('efectivo')) return 'Efectivo';
    return forma || 'Efectivo';
  }

  function shiftFecha(delta) {
    if (!elFecha || !elFecha.value) return;
    const d = new Date(elFecha.value + 'T12:00:00');
    if (modo === 'dia') d.setDate(d.getDate() + delta);
    else if (modo === 'semana') d.setDate(d.getDate() + delta * 7);
    else if (modo === 'mes') d.setMonth(d.getMonth() + delta);
    else d.setFullYear(d.getFullYear() + delta);
    elFecha.value = d.toISOString().slice(0, 10);
    cargar();
  }

  async function cargar() {
    if (!elFecha) return;
    if (elLoading) elLoading.hidden = false;

    const url = new URL(api, window.location.href);
    url.searchParams.set('accion', 'ventas_cuenta');
    url.searchParams.set('modo', modo);
    url.searchParams.set('fecha', elFecha.value);
    url.searchParams.set('cuenta', cuenta);
    const q = document.getElementById('rep-vent-buscar')?.value?.trim();
    if (q) url.searchParams.set('q', q);

    try {
      const res = await fetch(url.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message || 'Error al cargar');

      if (elEtiqueta) elEtiqueta.textContent = data.etiqueta || '';
      if (elTotal) elTotal.textContent = fmtMxn(data.resumen?.total || 0);

      const filas = data.filas || [];
      if (!tbody) return;
      if (!filas.length) {
        tbody.innerHTML = '<tr><td colspan="9" style="color:#888;">Sin registros en el periodo</td></tr>';
        return;
      }

      tbody.innerHTML = filas.map((r) => {
        const folio = esc(r.folio || '—');
        const idPago = r.id_pago || '';
        return `<tr>
          <td>${folio}${idPago ? `<br><button type="button" class="btn-ticket" data-id="${esc(idPago)}"><i class="fas fa-print"></i></button>` : ''}</td>
          <td>${fmtFechaTabla(r.creado_en)}</td>
          <td>${esc(r.numero_control)}</td>
          <td>${esc(r.alumno)}</td>
          <td class="concepto-multiline">${esc(r.concepto || r.cubrio || '—')}</td>
          <td>${esc(r.grupo || '—')}</td>
          <td>${esc(r.cajero || '—')}</td>
          <td>${esc(etiquetaForma(r.forma_pago))}</td>
          <td>${fmtMxn(r.monto)}</td>
        </tr>`;
      }).join('');

      tbody.querySelectorAll('.btn-ticket').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = btn.getAttribute('data-id');
          if (id) window.open((cfg.ticketBase || 'views/ticket_pago.php') + '?id_pago=' + encodeURIComponent(id), '_blank');
        });
      });
    } catch (err) {
      if (tbody) tbody.innerHTML = `<tr><td colspan="9" style="color:#c62828;">${esc(err.message)}</td></tr>`;
    } finally {
      if (elLoading) elLoading.hidden = true;
    }
  }

  function recalcCorteModal() {
    if (!corteData) return;
    const retiros = parseFloat(document.getElementById('rep-corte-retiros')?.value) || 0;
    const billetes = parseFloat(document.getElementById('rep-corte-billetes')?.value) || 0;
    const monedas = parseFloat(document.getElementById('rep-corte-monedas')?.value) || 0;
    const comprobantes = parseFloat(document.getElementById('rep-corte-comprobantes')?.value) || 0;
    const contado = billetes + monedas;
    const ingreso = corteData.ingreso_sistema || 0;
    const terminal = corteData.terminal || 0;
    const transferencia = corteData.transferencia || 0;
    const esperadoEfectivo = Math.max(0, ingreso - terminal - transferencia);
    const diferencia = contado - esperadoEfectivo;
    const entregar = ingreso - terminal - transferencia - contado - retiros + comprobantes;
    const elDif = document.getElementById('rep-corte-diferencia');
    const elEnt = document.getElementById('rep-corte-entregar');
    if (elDif) elDif.textContent = fmtMxn(diferencia);
    if (elEnt) elEnt.textContent = fmtMxn(entregar);
  }

  async function abrirCorte() {
    if (!elFecha || !modalCorte) return;
    modalCorte.hidden = false;
    const url = new URL(api, window.location.href);
    url.searchParams.set('accion', 'corte_caja');
    url.searchParams.set('fecha', elFecha.value);
    url.searchParams.set('cuenta', cuenta);

    try {
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message || 'Error');
      corteData = data;
      document.getElementById('rep-corte-ingreso').textContent = fmtMxn(data.ingreso_sistema);
      document.getElementById('rep-corte-terminal').textContent = fmtMxn(data.terminal);
      document.getElementById('rep-corte-transferencia').textContent = fmtMxn(data.transferencia);
      const g = data.guardado || {};
      document.getElementById('rep-corte-retiros').value = g.retiros ?? 0;
      document.getElementById('rep-corte-billetes').value = g.billetes ?? g.efectivo_contado ?? 0;
      document.getElementById('rep-corte-monedas').value = g.monedas ?? 0;
      document.getElementById('rep-corte-comprobantes').value = g.comprobantes ?? 0;
      document.getElementById('rep-corte-notas').value = g.notas ?? '';
      document.getElementById('rep-corte-msg').textContent = g.usuario_nombre
        ? 'Último corte: ' + g.usuario_nombre
        : '';
      recalcCorteModal();
    } catch (err) {
      document.getElementById('rep-corte-msg').textContent = err.message || 'Error';
    }
  }

  async function guardarCorte() {
    if (!elFecha) return;
    const fd = new FormData();
    fd.append('accion', 'corte_caja');
    fd.append('fecha', elFecha.value);
    fd.append('cuenta', cuenta);
    fd.append('retiros', document.getElementById('rep-corte-retiros')?.value || '0');
    fd.append('billetes', document.getElementById('rep-corte-billetes')?.value || '0');
    fd.append('monedas', document.getElementById('rep-corte-monedas')?.value || '0');
    fd.append('comprobantes', document.getElementById('rep-corte-comprobantes')?.value || '0');
    fd.append('notas', document.getElementById('rep-corte-notas')?.value || '');

    const res = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    const msg = document.getElementById('rep-corte-msg');
    if (data.status === 'ok') {
      if (msg) msg.textContent = data.message || 'Corte guardado';
      setTimeout(() => { modalCorte.hidden = true; }, 800);
    } else if (msg) {
      msg.textContent = data.message || 'No se pudo guardar';
    }
  }

  function exportExcel() {
    const table = document.getElementById('rep-vent-tabla');
    if (!table) return;
    const html = '<html><head><meta charset="utf-8"></head><body>' + table.outerHTML + '</body></html>';
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'reporte_ventas_' + (elFecha?.value || 'export') + '.xls';
    a.click();
  }

  document.querySelectorAll('#rep-vent-modo-tabs button').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#rep-vent-modo-tabs button').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      modo = btn.getAttribute('data-modo') || 'dia';
      cargar();
    });
  });

  document.querySelectorAll('#rep-vent-cuentas [data-cuenta]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#rep-vent-cuentas [data-cuenta]').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      cuenta = btn.getAttribute('data-cuenta') || 'A';
      ocultarB = false;
      document.getElementById('rep-vent-solo-a').hidden = cuenta !== 'B';
      cargar();
    });
  });

  document.getElementById('rep-vent-solo-a')?.addEventListener('click', () => {
    ocultarB = true;
    cuenta = 'A';
    document.querySelectorAll('#rep-vent-cuentas [data-cuenta]').forEach((b) => {
      b.classList.toggle('active', b.getAttribute('data-cuenta') === 'A');
    });
    cargar();
  });

  document.getElementById('rep-vent-prev')?.addEventListener('click', () => shiftFecha(-1));
  document.getElementById('rep-vent-next')?.addEventListener('click', () => shiftFecha(1));
  elFecha?.addEventListener('change', cargar);
  document.getElementById('rep-vent-buscar')?.addEventListener('input', () => {
    clearTimeout(window._repVentBuscarT);
    window._repVentBuscarT = setTimeout(cargar, 350);
  });
  document.getElementById('rep-vent-excel')?.addEventListener('click', exportExcel);
  document.getElementById('rep-vent-btn-corte')?.addEventListener('click', abrirCorte);
  document.getElementById('rep-corte-cancelar')?.addEventListener('click', () => { modalCorte.hidden = true; });
  document.getElementById('rep-corte-guardar')?.addEventListener('click', guardarCorte);
  ['rep-corte-retiros', 'rep-corte-billetes', 'rep-corte-monedas', 'rep-corte-comprobantes'].forEach((id) => {
    document.getElementById(id)?.addEventListener('input', recalcCorteModal);
  });

  cargar();
})();
