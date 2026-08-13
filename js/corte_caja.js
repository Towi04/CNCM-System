(function () {
  'use strict';

  const cfg = window.HAY_CORTE_CONFIG || {};
  const api = cfg.api || 'php/reporte_financiero_api.php';
  let corteData = null;
  let cuenta = 'B';
  let idCorteGuardado = null;
  let syncFromCalc = false;

  const elFecha = document.getElementById('corte-fecha');
  const elLoading = document.getElementById('corte-loading');
  const elMsg = document.getElementById('corte-msg');
  const elReciboEstado = document.getElementById('corte-recibo-estado');
  const elFilasExtra = document.getElementById('corte-filas-extra');
  const elBtnRecibo = document.getElementById('corte-confirmar-recibo');
  const elModal = document.getElementById('corte-modal-recibo');

  function fmtMxn(n) {
    return '$ ' + Number(n || 0).toLocaleString('es-MX', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function parseNum(el) {
    return parseFloat(el?.value) || 0;
  }

  function denomKey(valor) {
    const n = Number(valor);
    if (Number.isNaN(n)) return String(valor);
    if (Math.abs(n - 0.5) < 0.001) return '0.50';
    if (Number.isInteger(n)) return String(n);
    return n.toFixed(2);
  }

  function leerFilasExtra() {
    if (!elFilasExtra) return [];
    return Array.from(elFilasExtra.querySelectorAll('tr[data-extra-row]')).map((tr) => ({
      label: (tr.querySelector('.corte-extra-label')?.value || '').trim(),
      monto: parseNum(tr.querySelector('.corte-extra-monto')),
    })).filter((f) => f.label !== '' || f.monto > 0);
  }

  function sumFilasExtra() {
    return leerFilasExtra().reduce((acc, f) => acc + (f.monto || 0), 0);
  }

  function leerDenominaciones() {
    const out = {};
    document.querySelectorAll('.corte-denom-qty').forEach((inp) => {
      const key = denomKey(inp.getAttribute('data-valor'));
      const qty = parseInt(inp.value, 10) || 0;
      if (qty > 0) out[key] = qty;
    });
    return out;
  }

  function aplicarDenominaciones(denoms) {
    const map = denoms && typeof denoms === 'object' ? denoms : {};
    document.querySelectorAll('.corte-denom-qty').forEach((inp) => {
      const key = denomKey(inp.getAttribute('data-valor'));
      inp.value = map[key] != null ? String(map[key]) : '0';
    });
    actualizarCalculadora(false);
  }

  function actualizarCalculadora(fillInputs) {
    let totalBilletes = 0;
    let totalMonedas = 0;

    document.querySelectorAll('.corte-denom-qty').forEach((inp) => {
      const valor = parseFloat(inp.getAttribute('data-valor')) || 0;
      const qty = parseInt(inp.value, 10) || 0;
      const sub = valor * qty;
      const grupo = inp.getAttribute('data-grupo');
      if (grupo === 'billetes') totalBilletes += sub;
      else totalMonedas += sub;

      const key = denomKey(inp.getAttribute('data-valor'));
      const subEl = document.querySelector('.corte-denom-sub[data-valor="' + key + '"]');
      if (subEl) subEl.textContent = fmtMxn(sub);
    });

    const elTotB = document.getElementById('corte-calc-billetes');
    const elTotM = document.getElementById('corte-calc-monedas');
    const elTot = document.getElementById('corte-calc-total');
    if (elTotB) elTotB.textContent = fmtMxn(totalBilletes);
    if (elTotM) elTotM.textContent = fmtMxn(totalMonedas);
    if (elTot) elTot.textContent = fmtMxn(totalBilletes + totalMonedas);

    if (fillInputs !== false) {
      syncFromCalc = true;
      const elB = document.getElementById('corte-billetes');
      const elM = document.getElementById('corte-monedas');
      if (elB) elB.value = totalBilletes.toFixed(2);
      if (elM) elM.value = totalMonedas.toFixed(2);
      syncFromCalc = false;
      recalc();
    }
  }

  function agregarFilaExtra(label, monto) {
    if (!elFilasExtra) return;
    const tr = document.createElement('tr');
    tr.setAttribute('data-extra-row', '1');
    tr.innerHTML =
      '<th><input type="text" class="corte-extra-label" placeholder="Concepto" value=""></th>' +
      '<td class="corte-extra-cell">' +
      '<input type="number" class="corte-extra-monto" min="0" step="0.01" value="0">' +
      '<button type="button" class="corte-extra-remove" title="Quitar fila" aria-label="Quitar">&times;</button>' +
      '</td>';
    elFilasExtra.appendChild(tr);
    const labelInp = tr.querySelector('.corte-extra-label');
    const montoInp = tr.querySelector('.corte-extra-monto');
    if (labelInp) labelInp.value = label || '';
    if (montoInp) montoInp.value = monto != null ? String(monto) : '0';
    labelInp?.addEventListener('input', recalc);
    montoInp?.addEventListener('input', recalc);
    tr.querySelector('.corte-extra-remove')?.addEventListener('click', () => {
      tr.remove();
      recalc();
    });
  }

  function setFilasExtra(filas) {
    if (!elFilasExtra) return;
    elFilasExtra.innerHTML = '';
    (Array.isArray(filas) ? filas : []).forEach((f) => {
      agregarFilaExtra(f.label || '', f.monto ?? 0);
    });
  }

  function actualizarReciboUi(g) {
    const recibido = !!(g && g.recibido_por);
    if (elBtnRecibo) {
      elBtnRecibo.disabled = !idCorteGuardado || recibido;
      elBtnRecibo.title = recibido
        ? 'Recibo ya confirmado'
        : (idCorteGuardado ? 'Confirmar recepción del dinero' : 'Guarde el corte primero');
    }
    if (!elReciboEstado) return;
    if (recibido) {
      const quien = g.recibido_nombre || g.recibido_usuario || 'Usuario';
      const cuando = g.recibido_en || '';
      elReciboEstado.hidden = false;
      elReciboEstado.textContent = 'Recibido por ' + quien + (cuando ? ' · ' + cuando : '');
    } else {
      elReciboEstado.hidden = true;
      elReciboEstado.textContent = '';
    }
  }

  function recalc() {
    if (!corteData) return;
    const retiros = parseNum(document.getElementById('corte-retiros'));
    const billetes = parseNum(document.getElementById('corte-billetes'));
    const monedas = parseNum(document.getElementById('corte-monedas'));
    const comprobantes = parseNum(document.getElementById('corte-comprobantes'));
    const extras = sumFilasExtra();
    const contado = billetes + monedas;
    const ingreso = corteData.ingreso_sistema || 0;
    const terminal = corteData.terminal || 0;
    const transferencia = corteData.transferencia || 0;
    const esperadoEfectivo = Math.max(0, ingreso - terminal - transferencia);
    const diferencia = contado - esperadoEfectivo;
    const entregar = ingreso - terminal - transferencia - contado - retiros + comprobantes + extras;

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
      idCorteGuardado = g.id_corte ? Number(g.id_corte) : null;

      document.getElementById('corte-retiros').value = g.retiros ?? 0;
      document.getElementById('corte-comprobantes').value = g.comprobantes ?? 0;
      document.getElementById('corte-notas').value = g.notas ?? '';

      setFilasExtra(g.filas_extra || []);

      const denoms = g.denominaciones || g.denominaciones_json || {};
      const hasDenoms = denoms && typeof denoms === 'object' && Object.keys(denoms).length > 0;
      if (hasDenoms) {
        aplicarDenominaciones(denoms);
        actualizarCalculadora(true);
      } else {
        aplicarDenominaciones({});
        document.getElementById('corte-billetes').value = g.billetes ?? 0;
        document.getElementById('corte-monedas').value = g.monedas ?? 0;
      }

      if (elMsg) {
        elMsg.textContent = g.usuario_nombre
          ? 'Último corte guardado por: ' + g.usuario_nombre
          : 'Sin corte guardado para esta fecha y cuenta.';
      }
      actualizarReciboUi(g);
      recalc();
    } catch (err) {
      idCorteGuardado = null;
      actualizarReciboUi(null);
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
    fd.append('filas_extra', JSON.stringify(leerFilasExtra()));
    fd.append('denominaciones', JSON.stringify(leerDenominaciones()));

    const res = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (data.status === 'ok') {
      idCorteGuardado = data.id_corte ? Number(data.id_corte) : idCorteGuardado;
      if (elMsg) elMsg.textContent = data.message || 'Corte guardado correctamente';
      await cargar();
      abrirModalRecibo();
    } else if (elMsg) {
      elMsg.textContent = data.message || 'No se pudo guardar';
    }
  }

  function abrirModalRecibo() {
    if (!idCorteGuardado || !elModal) return;
    const g = corteData?.guardado;
    if (g && g.recibido_por) return;
    document.getElementById('corte-recibo-usuario').value = '';
    document.getElementById('corte-recibo-password').value = '';
    const msg = document.getElementById('corte-recibo-msg');
    if (msg) msg.textContent = '';
    elModal.hidden = false;
  }

  function cerrarModalRecibo() {
    if (elModal) elModal.hidden = true;
  }

  async function enviarConfirmacionRecibo() {
    const msg = document.getElementById('corte-recibo-msg');
    const usuario = document.getElementById('corte-recibo-usuario')?.value || '';
    const password = document.getElementById('corte-recibo-password')?.value || '';
    if (!usuario.trim() || !password) {
      if (msg) msg.textContent = 'Capture usuario y contraseña del receptor.';
      return;
    }

    const fd = new FormData();
    fd.append('accion', 'confirmar_recibo');
    fd.append('fecha', elFecha.value);
    fd.append('cuenta', cuenta);
    fd.append('usuario', usuario.trim());
    fd.append('password', password);

    const res = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (data.status === 'ok') {
      cerrarModalRecibo();
      if (elMsg) elMsg.textContent = data.message || 'Recibo confirmado';
      await cargar();
    } else if (msg) {
      msg.textContent = data.message || 'No se pudo confirmar el recibo';
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
  document.getElementById('corte-add-fila')?.addEventListener('click', () => {
    agregarFilaExtra('', 0);
    elFilasExtra?.querySelector('tr[data-extra-row]:last-child .corte-extra-label')?.focus();
  });
  elBtnRecibo?.addEventListener('click', abrirModalRecibo);
  document.getElementById('corte-recibo-cancelar')?.addEventListener('click', cerrarModalRecibo);
  document.getElementById('corte-recibo-enviar')?.addEventListener('click', enviarConfirmacionRecibo);
  elModal?.addEventListener('click', (e) => {
    if (e.target === elModal) cerrarModalRecibo();
  });

  ['corte-retiros', 'corte-billetes', 'corte-monedas', 'corte-comprobantes'].forEach((id) => {
    document.getElementById(id)?.addEventListener('input', () => {
      if (!syncFromCalc) recalc();
    });
  });

  document.querySelectorAll('.corte-denom-qty').forEach((inp) => {
    inp.addEventListener('input', () => actualizarCalculadora(true));
  });

  document.querySelector('[data-seccion="reporte_ventas"]')?.addEventListener('click', (e) => {
    e.preventDefault();
    if (typeof window.cargarSeccion === 'function') {
      window.cargarSeccion('reporte_ventas');
    }
  });

  cargar();
})();
