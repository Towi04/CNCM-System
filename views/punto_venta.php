<?php

require_once __DIR__ . '/../config.php';

$pvPreloadControl = trim($_GET['control'] ?? '');
$pvPreloadId = (int) ($_GET['id_alumno'] ?? 0);
?>

<link rel="stylesheet" href="css/admin_catalogo.css">

<link rel="stylesheet" href="css/hay_buttons.css">

<link rel="stylesheet" href="css/punto_venta.css">



<div class="pv-legacy">

  <div class="pv-top-selectors">

    <div class="pv-selector-block">

      <label class="pv-label-required">Selecciona al alumno que va a pagar:*</label>

      <select id="pv-sel-alumno" class="pv-select">

        <option value="">Selecciona un alumno</option>

      </select>

    </div>

    <div class="pv-selector-block">

      <label class="pv-label-required">o Busca un preregistro para realizar un apartado:*</label>

      <select id="pv-sel-prereg" class="pv-select">

        <option value="">Selecciona un pre registro</option>

      </select>

    </div>

  </div>



  <div class="pv-main">

    <section class="pv-left">

      <div class="pv-tabs">

        <span class="pv-tab pv-tab--active">Pagos Pendientes</span>

      </div>

      <div class="pv-left-body">

        <label class="pv-label">Selecciona la especialidad:</label>

        <select id="pv-especialidad" class="pv-select" disabled>

          <option value="">�</option>

        </select>

        <p class="pv-total-pendiente">Total pendiente: <strong id="pv-total-pendiente">$ 0.00</strong></p>



        <div class="pv-table-wrap">

          <table class="pv-table" id="pv-tabla-pendientes">

            <thead>

              <tr>

                <th>CONCEPTO</th>

                <th>MONTO</th>

                <th>SALDO</th>

                <th>FECHA LIMITE</th>

                <th>STATUS</th>

              </tr>

            </thead>

            <tbody id="pv-pendientes-body">

              <tr><td colspan="5" class="pv-empty">No se encontr� ning�n registro</td></tr>

            </tbody>

          </table>

        </div>

        <div class="pv-pager">

          <button type="button" class="pv-pager-btn" disabled aria-label="Anterior">�</button>

          <button type="button" class="pv-pager-btn" disabled aria-label="Siguiente">�</button>

        </div>

      </div>

    </section>



    <section class="pv-right">

      <div class="pv-right-head">

        <h3>Recibir abono</h3>

        <label class="pv-check-manual">

          <input type="checkbox" id="pv-monto-manual"> Monto manual

        </label>

      </div>

      <form id="pv-form-abono" class="pv-abono-form" data-no-global-ajax="1">

        <label>Semanas/Meses a pagar:</label>

        <select id="pv-cantidad-periodos" class="pv-select">

          <?php for ($i = 1; $i <= 12; $i++): ?>

          <option value="<?php echo $i; ?>"><?php echo $i; ?></option>

          <?php endfor; ?>

        </select>



        <label>Monto:</label>

        <input type="number" id="pv-monto" class="pv-input" min="0" step="0.01" placeholder="Ingresa el monto" readonly>



        <div class="pv-apoyo-block" style="margin:12px 0; padding:10px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
          <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
            <input type="checkbox" id="pv-cobro-precio-lista" <?php echo rbac_cap('cobro_precio_lista') ? '' : 'disabled'; ?>>
            Cobrar precio referencia (sin apoyo educativo)
          </label>
          <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
            <input type="checkbox" id="pv-origen-cartas">
            Inscripción por cartas (comisión reparto)
          </label>
          <p id="pv-cartas-info" style="display:none; margin:0 0 8px; font-size:0.85rem; color:#64748b;"></p>
          <p id="pv-apoyo-hint" style="margin:0; font-size:0.85rem; color:#64748b;">Por defecto se cobra con apoyo educativo.</p>
          <div id="pv-ticket-desglose" style="display:none; margin-top:8px; font-size:0.85rem;"></div>
        </div>

        <label>Medio de pago:</label>

        <select id="pv-medio" class="pv-select">
          <option value="">Selecciona una opci�n</option>
          <option value="efectivo">Efectivo</option>
          <option value="tarjeta_debito">Tarjeta d�bito</option>
          <option value="tarjeta_credito">Tarjeta cr�dito</option>
          <option value="transferencia">Transferencia</option>
        </select>



        <label>Paga con:</label>

        <input type="number" id="pv-paga-con" class="pv-input" min="0" step="0.01" placeholder="Ingresa con que paga el alumno">



        <p class="pv-cambio">Cambio: <strong id="pv-cambio">$ 0.00</strong></p>



        <input type="hidden" id="pv-id-alumno" value="0">

        <input type="hidden" id="pv-id-especialidad" value="0">
        <input type="hidden" id="pv-id-solicitud-cert" value="0">
        <input type="hidden" id="pv-id-documento" value="0">



        <button type="submit" class="pv-btn-recibir" id="pv-btn-recibir" disabled>Recibir abono</button>

        <button type="button" class="secondary pv-btn-apartado" id="pv-btn-apartado" style="display:none; width:100%; margin-top:8px;">

          Registrar apartado (preregistro)

        </button>

      </form>

      <div id="pv-msg" class="catalog-alert" style="display:none; margin-top:12px;"></div>

    </section>

  </div>

</div>



<script>

(function () {

  const fmt = (n) => '$ ' + Number(n).toFixed(2);

  const selAlumno = document.getElementById('pv-sel-alumno');

  const selPrereg = document.getElementById('pv-sel-prereg');

  const selEsp = document.getElementById('pv-especialidad');

  const tbody = document.getElementById('pv-pendientes-body');

  const totalPend = document.getElementById('pv-total-pendiente');

  const montoInp = document.getElementById('pv-monto');

  const manualChk = document.getElementById('pv-monto-manual');

  const cantPeriodos = document.getElementById('pv-cantidad-periodos');

  const pagaCon = document.getElementById('pv-paga-con');

  const cambioEl = document.getElementById('pv-cambio');

  const btnRecibir = document.getElementById('pv-btn-recibir');

  const btnApartado = document.getElementById('pv-btn-apartado');

  const msg = document.getElementById('pv-msg');

  const medioSel = document.getElementById('pv-medio');

  const cobroListaChk = document.getElementById('pv-cobro-precio-lista');
  const origenCartasChk = document.getElementById('pv-origen-cartas');
  const cartasInfo = document.getElementById('pv-cartas-info');

  const ticketDesglose = document.getElementById('pv-ticket-desglose');



  let lineasPend = [];

  let alumnoActual = null;

  let preregActual = null;

  let formaPago = 'mensual';

  let lineaCertSeleccionada = null;
  let lineaDocSeleccionada = null;



  async function cargarAlumnos(q) {

    const r = await fetch('php/pago_pos_api.php?action=alumnos&q=' + encodeURIComponent(q || ''));

    const data = await r.json();

    const val = selAlumno.value;

    selAlumno.innerHTML = '<option value="">Selecciona un alumno</option>';

    (data.alumnos || []).forEach((a) => {

      const o = document.createElement('option');

      o.value = a.id_alumno;

      o.textContent = '#' + (a.numero_control || a.matricula) + ' � ' + a.nombre_completo;

      o.dataset.control = a.numero_control || '';

      selAlumno.appendChild(o);

    });

    if (val) selAlumno.value = val;

  }



  async function cargarPreregistros(q) {

    const r = await fetch('php/pago_pos_api.php?action=preregistros&q=' + encodeURIComponent(q || ''));

    const data = await r.json();

    selPrereg.innerHTML = '<option value="">Selecciona un pre registro</option>';

    (data.preregistros || []).forEach((p) => {

      const o = document.createElement('option');

      o.value = p.id_preregistro;

      o.textContent = p.nombre + (p.telefono ? ' � ' + p.telefono : '');

      selPrereg.appendChild(o);

    });

  }



  function calcMontoPeriodos() {

    const n = parseInt(cantPeriodos.value, 10) || 1;

    let sum = 0;

    if (lineasPend.length === 0) {

      sum = 0;

    } else if (lineasPend.length <= n) {

      sum = lineasPend.reduce((a, l) => a + (parseFloat(l.saldo) || 0), 0);

    } else {

      for (let i = 0; i < n; i++) {

        sum += parseFloat(lineasPend[i].saldo) || 0;

      }

    }

    if (!manualChk.checked) {

      montoInp.value = sum > 0 ? sum.toFixed(2) : '';

    }

    actualizarCambio();

  }



  function actualizarCambio() {

    const monto = parseFloat(montoInp.value) || 0;

    const paga = parseFloat(pagaCon.value) || 0;

    const cambio = Math.max(0, paga - monto);

    cambioEl.textContent = fmt(cambio);

  }



  function renderPendientes(lineas, total) {

    lineasPend = lineas || [];

    lineaCertSeleccionada = null;
    lineaDocSeleccionada = null;

    const hidCert = document.getElementById('pv-id-solicitud-cert');
    const hidDoc = document.getElementById('pv-id-documento');

    if (hidCert) hidCert.value = '0';
    if (hidDoc) hidDoc.value = '0';

    totalPend.textContent = fmt(total || 0);

    if (!lineasPend.length) {

      tbody.innerHTML = '<tr><td colspan="5" class="pv-empty">No se encontr� ning�n registro</td></tr>';

      btnRecibir.disabled = true;

      calcMontoPeriodos();

      return;

    }

    tbody.innerHTML = '';

    lineasPend.forEach((ln) => {

      const tr = document.createElement('tr');

      const esCert = ln.tipo === 'certificacion' && ln.id_solicitud_cert;
      const esConst = ln.tipo === 'constancia' && ln.id_documento;

      if (esCert || esConst) {

        tr.classList.add('pv-row-cert');

        tr.style.cursor = 'pointer';

      }

      tr.innerHTML =

        '<td>' + (ln.concepto || '') + ((esCert || esConst) ? ' <small>(clic para cobrar)</small>' : '') + '</td>' +

        '<td>' + fmt(ln.monto) + '</td>' +

        '<td>' + fmt(ln.saldo) + '</td>' +

        '<td>' + (ln.fecha_limite || '�') + '</td>' +

        '<td><span class="pv-status-pend">' + (ln.status || 'Pendiente') + '</span></td>';

      if (esCert) {

        tr.addEventListener('click', () => {

          tbody.querySelectorAll('tr').forEach((r) => r.classList.remove('pv-row-selected'));

          tr.classList.add('pv-row-selected');

          lineaCertSeleccionada = ln;
          lineaDocSeleccionada = null;

          if (hidCert) hidCert.value = String(ln.id_solicitud_cert);
          if (hidDoc) hidDoc.value = '0';

          manualChk.checked = true;

          montoInp.readOnly = false;

          montoInp.value = Number(ln.saldo || 0).toFixed(2);

          actualizarCambio();

        });

      }

      if (esConst) {

        tr.addEventListener('click', () => {

          tbody.querySelectorAll('tr').forEach((r) => r.classList.remove('pv-row-selected'));

          tr.classList.add('pv-row-selected');

          lineaDocSeleccionada = ln;
          lineaCertSeleccionada = null;

          if (hidDoc) hidDoc.value = String(ln.id_documento);
          if (hidCert) hidCert.value = '0';

          manualChk.checked = true;

          montoInp.readOnly = false;

          montoInp.value = Number(ln.saldo || 0).toFixed(2);

          actualizarCambio();

        });

      }

      tbody.appendChild(tr);

    });

    btnRecibir.disabled = false;

    calcMontoPeriodos();

  }



  async function cargarPendientes(idAlumno, idEsp) {

    if (!idAlumno) return;

    const q = 'id_alumno=' + idAlumno + (idEsp ? '&id_especialidad=' + idEsp : '');

    const r = await fetch('php/pago_pos_api.php?action=pendientes&' + q);

    const data = await r.json();

    if (data.status !== 'ok') {

      renderPendientes([], 0);

      return;

    }

    formaPago = data.forma_pago || 'mensual';

    const firstLbl = document.querySelector('.pv-abono-form label');

    if (firstLbl) {

      firstLbl.textContent = (formaPago === 'semanal' ? 'Semanas' : 'Meses') + ' a pagar:';

    }



    selEsp.innerHTML = '<option value="">Todas</option>';

    (data.inscripciones || []).forEach((ins) => {

      const o = document.createElement('option');

      o.value = ins.id_especialidad || '';

      o.textContent = ins.especialidad + ' (adeudo ' + fmt(ins.adeudo) + ')';

      selEsp.appendChild(o);

    });

    selEsp.disabled = false;

    if (idEsp) selEsp.value = String(idEsp);

    renderPendientes(data.lineas, data.total_pendiente);

    if (origenCartasChk && cartasInfo) {
      const idEsc = Number(data.id_escuela_origen || 0);
      const nomEsc = data.escuela_origen_nombre || '';
      if (idEsc > 0) {
        cartasInfo.style.display = 'block';
        cartasInfo.textContent = 'Escuela de origen: ' + nomEsc + ' (cartas)';
        origenCartasChk.disabled = false;
      } else {
        cartasInfo.style.display = 'block';
        cartasInfo.textContent = 'Sin escuela de origen — no se puede marcar cartas hasta registrarla.';
        origenCartasChk.checked = false;
        origenCartasChk.disabled = true;
      }
    }

  }



  selAlumno.addEventListener('change', async () => {

    preregActual = null;

    selPrereg.value = '';

    btnApartado.style.display = 'none';

    const id = parseInt(selAlumno.value, 10);

    document.getElementById('pv-id-alumno').value = id || 0;

    alumnoActual = id || null;

    if (!id) {

      renderPendientes([], 0);

      selEsp.disabled = true;

      return;

    }

    await cargarPendientes(id, 0);

  });



  selPrereg.addEventListener('change', () => {

    const id = parseInt(selPrereg.value, 10);

    if (!id) {

      preregActual = null;

      btnApartado.style.display = 'none';

      return;

    }

    alumnoActual = null;

    selAlumno.value = '';

    preregActual = id;

    btnApartado.style.display = 'block';

    btnRecibir.disabled = true;

    renderPendientes([], 0);

    manualChk.checked = true;

    montoInp.readOnly = false;

    montoInp.placeholder = 'Monto del apartado';

  });



  selEsp.addEventListener('change', () => {

    const idAl = parseInt(selAlumno.value, 10);

    const idEsp = parseInt(selEsp.value, 10) || 0;

    document.getElementById('pv-id-especialidad').value = idEsp;

    if (idAl) cargarPendientes(idAl, idEsp);

  });



  manualChk.addEventListener('change', () => {

    montoInp.readOnly = !manualChk.checked;

    if (!manualChk.checked) calcMontoPeriodos();

  });



  cantPeriodos.addEventListener('change', calcMontoPeriodos);

  montoInp.addEventListener('input', actualizarCambio);

  pagaCon.addEventListener('input', actualizarCambio);



  document.getElementById('pv-form-abono').addEventListener('submit', async (e) => {

    e.preventDefault();

    const idAlumno = parseInt(document.getElementById('pv-id-alumno').value, 10);

    const monto = parseFloat(montoInp.value) || 0;

    const medio = medioSel ? medioSel.value : '';

    if (!idAlumno || monto <= 0 || !medio) {

      alert('Seleccione alumno, monto y medio de pago');

      return;

    }

    const idEsp = parseInt(document.getElementById('pv-id-especialidad').value, 10) || null;

    const fd = new FormData();

    fd.append('action', 'cobrar');

    fd.append('id_alumno', String(idAlumno));

    fd.append('folio', 'PV-' + Date.now());

    fd.append('medio_pago', medio);

    fd.append('monto', String(monto));

    if (cobroListaChk && cobroListaChk.checked) {
      fd.append('cobro_precio_lista', '1');
      fd.append('monto_referencia', String(monto));
    } else {
      fd.append('monto_apoyo', String(monto));
    }

    fd.append('cant_periodos', String(parseInt(cantPeriodos.value, 10) || 1));

    fd.append('id_especialidad', String(idEsp || ''));

    fd.append('distribuir_periodos', manualChk.checked ? '0' : '1');

    const idSolCert = parseInt(document.getElementById('pv-id-solicitud-cert').value, 10) || 0;
    const idDocumento = parseInt(document.getElementById('pv-id-documento').value, 10) || 0;

    if (idSolCert > 0) {
      fd.append('id_solicitud_cert', String(idSolCert));
      fd.append('distribuir_periodos', '0');
    }
    if (idDocumento > 0) {
      fd.append('id_documento', String(idDocumento));
      fd.append('distribuir_periodos', '0');
    }
    if (origenCartasChk && origenCartasChk.checked) {
      fd.append('origen_cartas', '1');
    }

    try {

      const { data } = await hayFetchJson('php/pago_pos_api.php', { method: 'POST', body: fd });

      if (msg) {

        msg.style.display = 'block';

        msg.className = 'catalog-alert ' + (data.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');

        msg.textContent = data.message || '';

      }

      if (data.status === 'ok') {
        pagaCon.value = '';
        if (data.pdf_constancia) {
          window.open(data.pdf_constancia, '_blank', 'noopener');
        }
        if (data.ticket_url) {
          window.open(data.ticket_url, '_blank', 'noopener');
        }
        await cargarPendientes(idAlumno, idEsp || 0);
      }

    } catch (err) {

      if (msg) {

        msg.style.display = 'block';

        msg.className = 'catalog-alert catalog-alert--error';

        msg.textContent = err.message;

      }

    }

  });



  btnApartado.addEventListener('click', async () => {

    if (!preregActual) return;

    const monto = parseFloat(montoInp.value) || 0;

    if (monto <= 0) { alert('Indique el monto del apartado'); return; }

    const fd = new FormData();

    fd.append('action', 'apartado_preregistro');

    fd.append('id_preregistro', String(preregActual));

    fd.append('monto', String(monto));

    try {

      const { data } = await hayFetchJson('php/pago_pos_api.php', { method: 'POST', body: fd });

      alert(data.message || '');

      if (data.status === 'ok') {

        selPrereg.value = '';

        preregActual = null;

        btnApartado.style.display = 'none';

        montoInp.value = '';

      }

    } catch (err) {

      alert(err.message);

    }

  });



  cargarAlumnos('');

  cargarPreregistros('');

  (async function preloadAlumnoPos() {
    const preload = <?php echo json_encode([
        'control' => $pvPreloadControl,
        'id_alumno' => $pvPreloadId,
    ], JSON_UNESCAPED_UNICODE); ?>;
    if (!preload.control && !(preload.id_alumno > 0)) return;
    await cargarAlumnos(preload.control || '');
    if (preload.id_alumno > 0) {
      selAlumno.value = String(preload.id_alumno);
    } else if (preload.control) {
      const opt = Array.from(selAlumno.options).find((o) =>
        (o.dataset.control || '') === preload.control
        || o.textContent.indexOf(preload.control) >= 0
      );
      if (opt) selAlumno.value = opt.value;
    }
    if (selAlumno.value) {
      selAlumno.dispatchEvent(new Event('change'));
    }
  })();

})();

</script>
