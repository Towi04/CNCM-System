(function () {
  const cfg = window.HAY_REP_FIN_CONFIG || {};
  const api = cfg.api || 'php/reporte_financiero_api.php';
  const accion = cfg.accion || 'ventas';

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function fmtFecha(s) {
    if (!s) return '—';
    const d = new Date(String(s).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return esc(s);
    return d.toLocaleDateString('es-MX') + ' ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
  }

  function fmtMxn(n) {
    return '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  async function cargar() {
    const desde = document.getElementById('rep-fin-desde')?.value || '';
    const hasta = document.getElementById('rep-fin-hasta')?.value || '';
    const tipo = document.getElementById('rep-fin-tipo')?.value || '';
    const loading = document.getElementById('rep-fin-loading');
    const resumen = document.getElementById('rep-fin-resumen');
    const tbody = document.querySelector('#rep-fin-tabla tbody');
    if (loading) loading.hidden = false;

    const url = new URL(api, window.location.href);
    url.searchParams.set('accion', accion);
    url.searchParams.set('desde', desde);
    url.searchParams.set('hasta', hasta);
    if (tipo) url.searchParams.set('tipo', tipo);

    try {
      const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message || 'Error');

      if (resumen && data.resumen) {
        if (accion === 'ventas') {
          resumen.innerHTML = `<strong>${data.resumen.cantidad}</strong> pagos · Total <strong>${esc(data.resumen.total_fmt || fmtMxn(data.resumen.total))}</strong>`
            + (data.resumen.descuentos > 0 ? ` · Descuentos ${esc(data.resumen.descuentos_fmt)}` : '');
        } else if (accion === 'productos') {
          resumen.innerHTML = `<strong>${data.resumen.cantidad}</strong> ventas · <strong>${data.resumen.unidades}</strong> unidades · Total <strong>${esc(data.resumen.total_fmt)}</strong>`;
        } else {
          resumen.innerHTML = `<strong>${data.resumen.cantidad}</strong> alumnos con apoyo · Descuento total <strong>${esc(data.resumen.descuento_total_fmt)}</strong>`;
        }
      }

      if (tbody) {
        const filas = data.filas || [];
        if (!filas.length) {
          tbody.innerHTML = `<tr><td colspan="${cfg.cols || 8}" style="color:#888;">Sin registros en el periodo</td></tr>`;
        } else if (accion === 'ventas') {
          tbody.innerHTML = filas.map((r) => `<tr>
            <td>${fmtFecha(r.creado_en)}</td>
            <td>${esc(r.folio || '—')}</td>
            <td>${esc(r.tipo)}</td>
            <td>${esc(r.alumno)}</td>
            <td>${esc(r.numero_control)}</td>
            <td>${esc(r.especialidad || '—')}</td>
            <td>${fmtMxn(r.monto)}</td>
            <td>${esc(r.forma_pago)}</td>
            <td>${esc(r.cajero || '—')}</td>
          </tr>`).join('');
        } else if (accion === 'productos') {
          tbody.innerHTML = filas.map((r) => `<tr>
            <td>${fmtFecha(r.creado_en)}</td>
            <td>${esc(r.producto || r.concepto || '—')}</td>
            <td>${esc(r.alumno)}</td>
            <td>${esc(r.numero_control)}</td>
            <td>${fmtMxn(r.monto)}</td>
            <td>${esc(r.forma_pago)}</td>
            <td>${esc(r.cajero || '—')}</td>
          </tr>`).join('');
        } else {
          tbody.innerHTML = filas.map((r) => `<tr>
            <td>${fmtFecha(r.creado_en)}</td>
            <td>${esc(r.alumno)}</td>
            <td>${esc(r.numero_control)}</td>
            <td>${esc(r.especialidad || '—')}</td>
            <td>${fmtMxn(r.monto)}</td>
            <td>${fmtMxn(r.monto_descuento)}</td>
            <td>${esc(r.beca_nombre || r.promo_nombre || r.motivo_descuento || '—')}</td>
            <td>${esc(r.autoriza || '—')}</td>
          </tr>`).join('');
        }
      }
    } catch (err) {
      if (resumen) resumen.textContent = err.message || 'Error al cargar';
      if (tbody) tbody.innerHTML = `<tr><td colspan="${cfg.cols || 8}" style="color:#c62828;">${esc(err.message)}</td></tr>`;
    } finally {
      if (loading) loading.hidden = true;
    }
  }

  document.getElementById('btn-rep-fin-generar')?.addEventListener('click', cargar);
  cargar();
})();
