(function () {

  const cfg = window.HAY_CORTE_CONFIG || {};

  const api = cfg.api || 'php/reporte_financiero_api.php';

  let corteData = null;

  let cuenta = 'B';



  const elFecha = document.getElementById('corte-fecha');

  const elLoading = document.getElementById('corte-loading');

  const elMsg = document.getElementById('corte-msg');



  function fmtMxn(n) {

    return '$ ' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  }



  function recalc() {

    if (!corteData) return;

    const retiros = parseFloat(document.getElementById('corte-retiros')?.value) || 0;

    const billetes = parseFloat(document.getElementById('corte-billetes')?.value) || 0;

    const monedas = parseFloat(document.getElementById('corte-monedas')?.value) || 0;

    const comprobantes = parseFloat(document.getElementById('corte-comprobantes')?.value) || 0;

    const contado = billetes + monedas;

    const ingreso = corteData.ingreso_sistema || 0;

    const terminal = corteData.terminal || 0;

    const transferencia = corteData.transferencia || 0;

    const esperadoEfectivo = Math.max(0, ingreso - terminal - transferencia);

    const diferencia = contado - esperadoEfectivo;

    const entregar = ingreso - terminal - transferencia - contado - retiros + comprobantes;

    const elDif = document.getElementById('corte-diferencia');

    const elEnt = document.getElementById('corte-entregar');

    if (elDif) elDif.textContent = fmtMxn(diferencia);

    if (elEnt) elEnt.textContent = fmtMxn(entregar);

  }



  async function cargar() {

    if (!elFecha) return;

    if (elLoading) elLoading.hidden = false;

    if (elMsg) elMsg.textContent = '';



    const url = new URL(api, window.location.href);

    url.searchParams.set('accion', 'corte_caja');

    url.searchParams.set('fecha', elFecha.value);

    url.searchParams.set('cuenta', cuenta);



    try {

      const res = await fetch(url.toString(), { credentials: 'same-origin' });

      const data = await res.json();

      if (data.status !== 'ok') throw new Error(data.message || 'Error');

      corteData = data;

      document.getElementById('corte-ingreso').textContent = fmtMxn(data.ingreso_sistema);

      document.getElementById('corte-terminal').textContent = fmtMxn(data.terminal);

      document.getElementById('corte-transferencia').textContent = fmtMxn(data.transferencia);

      const g = data.guardado || {};

      document.getElementById('corte-retiros').value = g.retiros ?? 0;

      document.getElementById('corte-billetes').value = g.billetes ?? g.efectivo_contado ?? 0;

      document.getElementById('corte-monedas').value = g.monedas ?? 0;

      document.getElementById('corte-comprobantes').value = g.comprobantes ?? 0;

      document.getElementById('corte-notas').value = g.notas ?? '';

      if (elMsg) {

        elMsg.textContent = g.usuario_nombre

          ? 'Último corte guardado por: ' + g.usuario_nombre

          : 'Sin corte guardado para esta fecha y cuenta.';

      }

      recalc();

    } catch (err) {

      if (elMsg) elMsg.textContent = err.message || 'Error al cargar';

    } finally {

      if (elLoading) elLoading.hidden = true;

    }

  }



  async function guardar() {

    if (!elFecha) return;

    const fd = new FormData();

    fd.append('accion', 'corte_caja');

    fd.append('fecha', elFecha.value);

    fd.append('cuenta', cuenta);

    fd.append('retiros', document.getElementById('corte-retiros')?.value || '0');

    fd.append('billetes', document.getElementById('corte-billetes')?.value || '0');

    fd.append('monedas', document.getElementById('corte-monedas')?.value || '0');

    fd.append('comprobantes', document.getElementById('corte-comprobantes')?.value || '0');

    fd.append('notas', document.getElementById('corte-notas')?.value || '');



    const res = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });

    const data = await res.json();

    if (data.status === 'ok') {

      if (elMsg) elMsg.textContent = data.message || 'Corte guardado correctamente';

      cargar();

    } else if (elMsg) {

      elMsg.textContent = data.message || 'No se pudo guardar';

    }

  }



  document.querySelectorAll('#corte-cuentas [data-cuenta]').forEach((btn) => {

    btn.addEventListener('click', () => {

      document.querySelectorAll('#corte-cuentas [data-cuenta]').forEach((b) => b.classList.remove('active'));

      btn.classList.add('active');

      cuenta = btn.getAttribute('data-cuenta') || 'B';

      cargar();

    });

  });



  elFecha?.addEventListener('change', cargar);

  document.getElementById('corte-recargar')?.addEventListener('click', cargar);

  document.getElementById('corte-guardar')?.addEventListener('click', guardar);

  ['corte-retiros', 'corte-billetes', 'corte-monedas', 'corte-comprobantes'].forEach((id) => {

    document.getElementById(id)?.addEventListener('input', recalc);

  });



  document.querySelector('[data-seccion="reporte_ventas"]')?.addEventListener('click', (e) => {

    e.preventDefault();

    if (typeof window.cargarSeccion === 'function') {

      window.cargarSeccion('reporte_ventas');

    }

  });



  cargar();

})();

