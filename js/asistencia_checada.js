/**

 * Terminal de checada — recepción con lector U.areU (solo huella).

 * Reinicializar en cada carga AJAX: window.hayChecadaBoot()

 */

window.hayChecadaBoot = function hayChecadaBoot() {

  if (typeof window.__hayChecadaCleanup === 'function') {

    window.__hayChecadaCleanup();

  }



  const cfg = window.HAY_CHECADA_CONFIG || {};

  const api = (typeof window.hayResolveAssetUrl === 'function'

    ? window.hayResolveAssetUrl(cfg.api || 'php/asistencia_checada_api.php')

    : (cfg.api || 'php/asistencia_checada_api.php'));

  const FP = window.HayFingerprintReader;

  let pollSince = 0;

  let pollTimer = null;

  let driverOk = false;

  let processing = false;

  let pagoEnCurso = false;

  let lastScanAt = 0;

  const SCAN_COOLDOWN_MS = 1200;



  if (!window.__checadaSesionLog) {

    window.__checadaSesionLog = [];

  }



  const espera = () => document.getElementById('checada-espera');

  const resultado = () => document.getElementById('checada-resultado');

  const inner = () => document.getElementById('checada-resultado-inner');

  const bannerWrap = () => document.getElementById('hid-lite-client-banner-wrap');

  const driverOkEl = () => document.getElementById('checada-driver-ok');

  const driverChecking = () => document.getElementById('checada-driver-checking');

  const scanStatus = () => document.getElementById('checada-scan-status');

  const sesionBody = () => document.getElementById('checada-sesion-body');

  const sesionCount = () => document.getElementById('checada-sesion-count');



  const QUALITY_HINTS = {

    1: 'No se detectó imagen. Coloque el dedo de nuevo.',

    2: 'Lectura muy clara. Presione un poco más.',

    3: 'Lectura muy oscura. Limpie el lector o el dedo.',

    8: 'No parece un dedo. Intente de nuevo.',

    9: 'Dedo muy arriba. Centre el dedo en el lector.',

    10: 'Dedo muy abajo. Centre el dedo en el lector.',

    11: 'Dedo muy a la izquierda.',

    12: 'Dedo muy a la derecha.',

    14: 'Muy rápido. Mantenga el dedo un instante.',

    17: 'Muy lento. Levante y vuelva a colocar el dedo.',

    21: 'Dedo húmedo. Seque el dedo e intente de nuevo.',

  };



  function esc(s) {

    const d = document.createElement('div');

    d.textContent = s == null ? '' : String(s);

    return d.innerHTML;

  }



  function sdkDisponible() {

    return FP?.sdkOk?.();

  }



  function capturaBloqueada() {

    return processing || pagoEnCurso;

  }



  function bindReaderEvents() {

    if (!FP) return;

    FP.on('SamplesAcquired', onSamplesAcquired);

    FP.on('QualityReported', (e) => {

      const q = e?.quality;

      const hint = QUALITY_HINTS[q] || (q != null ? 'Calidad de lectura insuficiente (' + q + '). Intente de nuevo.' : '');

      if (hint && !capturaBloqueada()) {

        setScanStatus(hint, 'warn');

      }

    });

    FP.on('ErrorOccurred', () => {

      if (!capturaBloqueada()) {

        setScanStatus('Error en el lector. Retire el dedo e intente de nuevo.', 'err');

      }

    });

    FP.on('CommunicationFailed', () => {

      setDriverUi(false);

      setScanStatus('Se perdió la conexión con el lector. Verifique el HID Lite Client.', 'err');

    });

  }



  async function iniciarCaptura() {

    if (!driverOk || capturaBloqueada()) return;

    if (!sdkDisponible()) return;

    try {

      await FP.startAcquisition('checada', Fingerprint.SampleFormat.Intermediate);

      setScanStatus('', null);

      const sec = espera();

      if (sec) sec.classList.remove('is-compact');

    } catch (err) {

      setScanStatus(FP.parseStartError(err), 'err');

    }

  }



  async function detenerCaptura() {

    if (FP) await FP.stopAcquisition();

  }



  function setScanStatus(text, kind) {

    const el = scanStatus();

    if (!el) return;

    if (!text) {

      el.hidden = true;

      el.textContent = '';

      el.className = 'checada-scan-status';

      return;

    }

    el.hidden = false;

    el.textContent = text;

    el.className = 'checada-scan-status' + (kind ? ' is-' + kind : ' is-info');

  }



  async function apiCall(params, method) {

    method = method || 'GET';

    const url = new URL(api, window.location.href);

    Object.keys(params || {}).forEach((k) => url.searchParams.set(k, params[k]));

    const opts = { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' };

    async function maybeChoose(resp) {
      if (!resp || resp.status !== 'choose' || !resp.codigo_huella) return resp;
      if (params && (params.asistencia_como || params.asistenciaComo)) return resp;
      const comoAlumno = confirm((resp.message || '¿Registrar asistencia como alumno?') + '\n\nAceptar = Alumno\nCancelar = Personal');
      const nuevo = Object.assign({}, params, {
        accion: 'registrar',
        codigo_huella: resp.codigo_huella,
        asistencia_como: comoAlumno ? 'alumno' : 'personal',
      });
      return apiCall(nuevo, 'POST');
    }

    if (method === 'POST') {

      const fd = new FormData();

      Object.keys(params || {}).forEach((k) => fd.append(k, params[k]));

      opts.method = 'POST';

      opts.body = fd;

      const resp = await (await fetch(api, opts)).json();
      return maybeChoose(resp);

    }

    const resp = await (await fetch(url.toString(), opts)).json();
    return maybeChoose(resp);

  }



  function fmtHora(h) {

    if (!h) return '—';

    return String(h).substring(0, 5);

  }



  function extraerMuestra(event) {

    const data = event?.samples;

    if (!data) return '';

    let sample = data;

    if (typeof data === 'string') {

      try {

        const parsed = JSON.parse(data);

        sample = Array.isArray(parsed) ? parsed[0] : parsed;

      } catch (_) {

        sample = data;

      }

    } else if (Array.isArray(data)) {

      sample = data[0];

    }

    if (!sample) return '';

    return typeof sample === 'string' ? sample : JSON.stringify(sample);

  }



  function setDriverUi(ok) {

    driverOk = ok;

    const checking = driverChecking();

    const okEl = driverOkEl();

    const wrap = bannerWrap();

    if (checking) {

      checking.hidden = true;

      checking.style.display = 'none';

    }

    if (wrap) {

      wrap.hidden = ok;

      wrap.style.display = ok ? 'none' : '';

    }

    if (okEl) {

      okEl.hidden = !ok;

      okEl.style.display = ok ? '' : 'none';

    }

    if (ok && espera()) {

      espera().hidden = false;

    }

    if (!ok) {

      detenerCaptura();

      setScanStatus('', null);

    }

  }



  function imprimirTicketPago(ticketUrl) {

    if (!ticketUrl) return;

    const url = typeof window.hayResolveAssetUrl === 'function'

      ? window.hayResolveAssetUrl(ticketUrl)

      : ticketUrl;

    const w = window.open(url, 'ticket_pago_checada', 'width=420,height=640,scrollbars=yes');

    if (!w) {

      alert('Permita ventanas emergentes para imprimir el comprobante.');

    }

  }



  function agregarASesion(data) {

    const p = data.persona || {};

    const adeudo = data.adeudo || {};

    const asist = data.asistencia || {};

    let estado = 'OK';

    let detalle = '';



    if (data.tipo === 'personal') {

      estado = asist.tipo_checada === 'salida' ? 'Salida' : 'Entrada';

    } else if (data.tipo === 'alumno') {

      if (data.ok === false) estado = 'Error';

      else if (data.duplicado) estado = 'Duplicado';

      else estado = 'Entrada';

      if (adeudo.tiene_adeudo) detalle = 'Adeudo ' + (adeudo.total_fmt || '');

    } else {

      estado = 'No reconocida';

      detalle = data.message || '';

    }



    window.__checadaSesionLog.unshift({

      hora: fmtHora(asist.hora || asist.hora_entrada || new Date().toTimeString().slice(0, 5)),

      nombre: p.nombre || '—',

      control: p.numero_control || '—',

      grupo: p.grupo || '—',

      estado,

      detalle,

    });

    if (window.__checadaSesionLog.length > 200) {

      window.__checadaSesionLog.length = 200;

    }

    renderSesionTabla();

  }



  function renderSesionTabla() {

    const tbody = sesionBody();

    const countEl = sesionCount();

    const log = window.__checadaSesionLog || [];

    if (countEl) countEl.textContent = String(log.length);

    if (!tbody) return;

    if (!log.length) {

      tbody.innerHTML = '<tr class="checada-sesion-empty"><td colspan="6">Aún no hay checadas en esta sesión.</td></tr>';

      return;

    }

    tbody.innerHTML = log.map((row) => {

      const cls = row.estado === 'No reconocida' || row.estado === 'Error'

        ? 'checada-sesion-row--err'

        : (row.estado === 'Duplicado' ? 'checada-sesion-row--dup' : '');

      return `<tr class="${cls}">

        <td>${esc(row.hora)}</td>

        <td>${esc(row.nombre)}</td>

        <td>${esc(row.control)}</td>

        <td>${esc(row.grupo)}</td>

        <td>${esc(row.estado)}</td>

        <td>${esc(row.detalle)}</td>

      </tr>`;

    }).join('');

  }



  async function identificarConMatcherLocal(sample) {

    const fj = cfg.fingerjet || {};

    if (!fj.enabled || !fj.matcher_url) {

      return null;

    }

    try {

      const res = await fetch(fj.matcher_url.replace(/\/$/, '') + '/identify', {

        method: 'POST',

        headers: { 'Content-Type': 'application/json' },

        body: JSON.stringify({ sample, id_plantel: fj.id_plantel }),

      });

      if (!res.ok) {

        return { ok: false, message: 'Matcher FingerJet respondió con error ' + res.status };

      }

      return await res.json();

    } catch (_) {

      return {

        ok: false,

        message: 'Servicio FingerJet local no responde en ' + fj.matcher_url

          + '. Verifique que HayFingerprintMatcher esté en ejecución en esta PC.',

      };

    }

  }



  async function identificarMuestra(sample) {

    const fj = cfg.fingerjet || {};

    const usarMatcher = fj.enabled && fj.matcher_url;

    const modo = fj.mode || 'auto';



    if (usarMatcher) {

      setScanStatus('Huella detectada. Identificando…', 'info');

      const local = await identificarConMatcherLocal(sample);

      if (local?.ok && local.codigo_huella) {

        return apiCall({ accion: 'registrar', codigo_huella: local.codigo_huella }, 'POST');

      }

      if (modo === 'required') {

        return {

          ok: false,

          status: 'error',

          tipo: 'desconocido',

          message: local?.message || 'Huella no reconocida por FingerJet',

        };

      }

    }



    return apiCall({ accion: 'identificar_muestra', sample }, 'POST');

  }



  async function onSamplesAcquired(event) {

    const now = Date.now();

    if (capturaBloqueada() || now - lastScanAt < SCAN_COOLDOWN_MS) {

      return;

    }

    const sample = extraerMuestra(event);

    if (!sample) return;



    lastScanAt = now;

    processing = true;

    await detenerCaptura();



    const sec = espera();

    if (sec) sec.classList.add('is-processing');

    setScanStatus('Huella detectada. Identificando…', 'info');



    try {

      const data = await identificarMuestra(sample);

      mostrarResultado(normalizarRespuesta(data));

    } catch (_) {

      mostrarResultado({

        ok: false,

        tipo: 'desconocido',

        message: 'Error de conexión al registrar la checada. Intente de nuevo.',

      });

    } finally {

      processing = false;

      if (sec) sec.classList.remove('is-processing');

    }

  }



  function normalizarRespuesta(data) {

    if (!data || typeof data !== 'object') {

      return { ok: false, tipo: 'desconocido', message: 'Respuesta inválida del servidor' };

    }

    if (data.tipo === 'alumno' || data.tipo === 'personal') {

      return data;

    }

    if (data.ok === false || data.status === 'error') {

      return {

        ok: false,

        tipo: 'desconocido',

        message: data.message || 'Huella no reconocida',

      };

    }

    return data;

  }



  async function verificarDriver() {

    if (typeof window.hayDetectHidDriver !== 'function') {

      setDriverUi(false);

      return;

    }

    const res = await window.hayDetectHidDriver();

    const wasOk = driverOk;

    setDriverUi(!!res.ok);

    if (res.ok && !wasOk) {

      await iniciarCaptura();

    } else if (res.ok && !FP?.isAcquiring?.() && !capturaBloqueada()) {

      await iniciarCaptura();

    }

  }



  function renderAlumno(data) {

    const p = data.persona || {};

    const adeudo = data.adeudo || {};

    const ins = data.inscripciones_pago || [];

    const foto = p.foto

      ? `<img src="${esc(p.foto)}" alt="" class="asist-checada-foto">`

      : `<div class="asist-checada-foto asist-checada-foto--iniciales">${esc(p.iniciales || '?')}</div>`;



    let adeudoHtml = '';

    if (adeudo.tiene_adeudo) {

      const lineas = (adeudo.lineas || []).map((l) => `<li>${esc(l.detalle)} — ${esc(l.saldo)}</li>`).join('');

      adeudoHtml = `

        <div class="asist-checada-adeudo asist-checada-adeudo--warn">

          <strong><i class="fas fa-exclamation-triangle"></i> Adeudo: ${esc(adeudo.total_fmt)}</strong>

          ${lineas ? `<ul>${lineas}</ul>` : ''}

        </div>`;

    } else {

      adeudoHtml = `<div class="asist-checada-adeudo asist-checada-adeudo--ok"><i class="fas fa-check-circle"></i> Sin adeudo de colegiatura</div>`;

    }



    let pagoHtml = '';

    const autoPago = cfg.puede_pago && adeudo.tiene_adeudo;

    if (cfg.puede_pago && adeudo.tiene_adeudo) {

      const opts = ins.map((i) => `<option value="${i.id_especialidad}" data-ae="${i.id_alumno_especialidad}">${esc(i.nombre)}</option>`).join('');

      pagoHtml = `

        <div class="asist-checada-pago" id="checada-panel-pago"${autoPago ? '' : ' hidden'}>

          <h4>Registrar pago</h4>

          <form id="form-checada-pago" data-no-global-ajax>

            <input type="hidden" name="id_alumno" value="${p.id_alumno}">

            <input type="hidden" name="numero_control" value="${esc(p.numero_control)}">

            <input type="hidden" name="origen" value="checada">

            <input type="hidden" name="id_alumno_especialidad" id="checada-pago-ae" value="">

            <label>Tipo</label>

            <select name="tipo"><option value="abono">Abono</option><option value="mensualidad">Mensualidad</option><option value="inscripcion">Inscripción</option></select>

            <label>Especialidad</label>

            <select name="id_especialidad" id="checada-pago-esp">${opts}</select>

            <label>Monto ($)</label>

            <input type="number" name="monto" min="0.01" step="0.01" required>

            <label>Forma de pago</label>

            <select name="forma_pago_efectivo"><option>Efectivo</option><option>Tarjeta débito</option><option>Transferencia</option></select>

            <button type="submit" class="primary">Guardar pago</button>

            <p id="checada-pago-msg" class="asist-checada-msg"></p>

          </form>

        </div>

        ${autoPago ? '' : '<button type="button" class="secondary" id="btn-checada-abrir-pago"><i class="fas fa-dollar-sign"></i> Registrar pago</button>'}`;

    }



    const ok = data.ok !== false;

    const dup = data.duplicado ? ' <span class="asist-checada-badge">Ya había checado hoy</span>' : '';



    return `

      <div class="asist-checada-card asist-checada-card--alumno ${ok ? 'is-ok' : 'is-err'}">

        ${foto}

        <div class="asist-checada-card__body">

          <div class="asist-checada-card__status">${ok ? '<i class="fas fa-check"></i> Asistencia registrada' + dup : esc(data.message)}</div>

          <h3>${esc(p.nombre)}</h3>

          <p class="asist-checada-meta">No. control: <strong>${esc(p.numero_control)}</strong> · Grupo: ${esc(p.grupo || '—')}</p>

          <p class="asist-checada-meta">${esc(p.especialidad || '')} · Hora: ${fmtHora(data.asistencia?.hora)}</p>

          ${adeudoHtml}

          ${pagoHtml}

        </div>

      </div>`;

  }



  function renderPersonal(data) {

    const p = data.persona || {};

    const a = data.asistencia || {};

    const foto = p.foto

      ? `<img src="${esc(p.foto)}" alt="" class="asist-checada-foto">`

      : `<div class="asist-checada-foto asist-checada-foto--iniciales">${esc(p.iniciales || '?')}</div>`;

    const tipo = a.tipo_checada === 'salida' ? 'Salida registrada' : 'Entrada registrada';



    return `

      <div class="asist-checada-card asist-checada-card--personal is-ok">

        ${foto}

        <div class="asist-checada-card__body">

          <div class="asist-checada-card__status"><i class="fas fa-user-check"></i> ${esc(tipo)}</div>

          <h3>${esc(p.nombre)}</h3>

          <p class="asist-checada-meta">${esc(p.rol || 'Personal')}</p>

          <p class="asist-checada-meta">Entrada: ${fmtHora(a.hora_entrada)} · Salida: ${fmtHora(a.hora_salida)}</p>

        </div>

      </div>`;

  }



  function renderError(data) {

    const msg = data.message || 'Huella no reconocida en el sistema';

    return `

      <div class="asist-checada-card is-err">

        <div class="asist-checada-card__body">

          <div class="asist-checada-card__status"><i class="fas fa-times-circle"></i> No reconocida</div>

          <p style="margin:0 0 12px; color:#555;">${esc(msg)}</p>

          <p class="asist-checada-hint" style="margin:0;">

            Si el alumno no usa huella, regístrelo en

            <a href="#" onclick="cargarSeccion('asistencia_faltantes'); return false;">Rondín de asistencia</a>.

          </p>

        </div>

      </div>`;

  }



  function bindPagoForm() {

    document.getElementById('btn-checada-abrir-pago')?.addEventListener('click', () => {

      document.getElementById('checada-panel-pago')?.removeAttribute('hidden');

      pagoEnCurso = true;

      detenerCaptura();

      setScanStatus('Complete el pago o pulse «Siguiente persona» para continuar checando.', 'info');

    });



    const espSel = document.getElementById('checada-pago-esp');

    espSel?.addEventListener('change', () => {

      const ae = document.getElementById('checada-pago-ae');

      if (ae && espSel.selectedOptions[0]) ae.value = espSel.selectedOptions[0].dataset.ae || '';

    });

    espSel?.dispatchEvent(new Event('change'));



    document.getElementById('form-checada-pago')?.addEventListener('submit', async (e) => {

      e.preventDefault();

      e.stopPropagation();

      const msg = document.getElementById('checada-pago-msg');

      const btn = e.target.querySelector('button[type="submit"]');

      const fd = new FormData(e.target);

      if (btn) btn.disabled = true;

      try {

        const res = await fetch(

          (typeof window.hayResolveAssetUrl === 'function'

            ? window.hayResolveAssetUrl(cfg.pago_api || 'php/pago_registrar.php')

            : (cfg.pago_api || 'php/pago_registrar.php')),

          {

            method: 'POST',

            body: fd,

            headers: { 'X-Requested-With': 'fetch' },

            credentials: 'same-origin',

          }

        );

        const data = await res.json();

        if (msg) {

          msg.textContent = data.message || '';

          msg.className = 'asist-checada-msg ' + (data.status === 'ok' ? 'ok' : 'err');

        }

        if (data.status === 'ok') {

          if (data.ticket_url) imprimirTicketPago(data.ticket_url);

          pagoEnCurso = false;

          setScanStatus('Pago registrado. Listo para la siguiente huella.', 'ok');

          setTimeout(() => resetVista(), 800);

        }

      } catch (_) {

        if (msg) msg.textContent = 'Error de conexión';

      } finally {

        if (btn) btn.disabled = false;

      }

    }, true);

  }



  function mostrarResultado(data) {

    agregarASesion(data);

    setScanStatus('', null);



    const secEspera = espera();

    if (secEspera) {

      secEspera.hidden = false;

      secEspera.classList.add('is-compact');

    }

    if (resultado()) resultado().hidden = false;

    const el = inner();

    if (!el) return;



    if (data.tipo === 'alumno') {

      el.innerHTML = renderAlumno(data);

    } else if (data.tipo === 'personal') {

      el.innerHTML = renderPersonal(data);

    } else {

      el.innerHTML = renderError(data);

    }



    bindPagoForm();



    const adeudo = data.adeudo || {};

    const requierePago = cfg.puede_pago && data.tipo === 'alumno' && adeudo.tiene_adeudo;

    pagoEnCurso = !!requierePago;



    if (requierePago) {

      setScanStatus('Registre el pago del alumno o pulse «Siguiente persona» para continuar.', 'info');

    } else {

      setTimeout(() => iniciarCaptura(), 400);

    }

  }



  async function resetVista() {

    pagoEnCurso = false;

    if (resultado()) resultado().hidden = true;

    if (inner()) inner().innerHTML = '';

    setScanStatus('', null);

    const sec = espera();

    if (sec) sec.classList.remove('is-compact');

    if (driverOk) {

      await iniciarCaptura();

    }

  }



  async function iniciarPoll() {

    try {

      const init = await apiCall({ accion: 'ultimo_evento_id' });

      pollSince = init.since || 0;

    } catch (_) {

      pollSince = 0;

    }

    pollTimer = setInterval(async () => {

      if (capturaBloqueada()) return;

      try {

        const data = await apiCall({ accion: 'poll', since: String(pollSince) });

        if (data.since) pollSince = data.since;

        (data.eventos || []).forEach((ev) => {

          if (ev && (ev.tipo === 'alumno' || ev.tipo === 'personal' || ev.ok === false || !ev.ok)) {

            mostrarResultado(normalizarRespuesta(ev));

          }

        });

      } catch (_) { /* ignore */ }

    }, cfg.poll_ms || 2000);

  }



  const btnSig = document.getElementById('btn-checada-siguiente');

  if (btnSig) btnSig.onclick = resetVista;



  bindReaderEvents();

  renderSesionTabla();

  verificarDriver().then(() => {

    iniciarPoll();

  });



  const driverInterval = setInterval(verificarDriver, 30000);

  const clockInterval = setInterval(() => {

    const el = document.getElementById('checada-reloj');

    if (el) {

      const d = new Date();

      el.textContent = d.toLocaleDateString('es-MX') + ' ' + d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });

    }

  }, 30000);



  window.__hayChecadaCleanup = function () {

    clearInterval(pollTimer);

    clearInterval(driverInterval);

    clearInterval(clockInterval);

  };

};


