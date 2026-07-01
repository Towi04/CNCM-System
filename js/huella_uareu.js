/**
 * Captura de huella — enrolamiento alumnos / personal.
 * Se reinicializa en cada carga AJAX vía window.hayHuellaEnrollBoot().
 */
window.hayHuellaEnrollBoot = function hayHuellaEnrollBoot() {
  const cfg = window.HAY_HUELLA_CONFIG || {};
  const entityType = cfg.entity_type || 'alumno';
  const entityId = window.HAY_HUELLA_ENTITY_ID || window.HAY_HUELLA_ALUMNO_ID || 0;
  const api = cfg.api_enroll || (entityType === 'usuario'
    ? 'php/usuario_huella_enroll_api.php'
    : 'php/alumno_huella_enroll_api.php');
  const REQUIRED_SCANS = parseInt(
    document.getElementById('huella-scan-progress')?.dataset?.totalScans
      || cfg.required_scans
      || 3,
    10
  );
  const redirectSection = cfg.redirect_section || (entityType === 'usuario'
    ? 'usuario_editar'
    : 'alumno_detalle');
  const redirectParam = 'id=' + entityId;
  const entityField = entityType === 'usuario' ? 'id_usuario' : 'id_alumno';
  const FP = window.HayFingerprintReader;

  let samples = [];
  let sdkReady = false;
  let driverOk = false;
  let waitTimer = null;
  let guardando = false;
  let lastSampleAt = 0;
  const SAMPLE_COOLDOWN_MS = 900;

  const msgEl = () => document.getElementById('huella-enroll-msg');
  const readerMsg = () => document.getElementById('huella-reader-msg');
  const progress = () => document.getElementById('huella-scan-progress');
  const scanText = () => document.getElementById('huella-scan-text');
  const scanFill = () => document.getElementById('huella-scan-fill');
  const btnCapturar = () => document.getElementById('btn-huella-capturar');
  const stepEls = () => document.querySelectorAll('.huella-scan-step');
  const bannerWrap = () => document.getElementById('hid-lite-client-banner-wrap');

  function showMsg(ok, text) {
    const el = msgEl();
    if (!el) return;
    el.style.display = 'block';
    el.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    el.textContent = text || '';
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

  function clearWaitTimer() {
    if (waitTimer) {
      clearTimeout(waitTimer);
      waitTimer = null;
    }
  }

  function setDriverUi(ok) {
    driverOk = ok;
    const wrap = bannerWrap();
    if (wrap) {
      wrap.hidden = ok;
      wrap.style.display = ok ? 'none' : '';
    }
    const btn = btnCapturar();
    if (btn && samples.length === 0) btn.disabled = !sdkReady || !ok;
  }

  function bindReaderEvents() {
    if (!FP) return;
    FP.on('DeviceConnected', () => {
      const el = readerMsg();
      if (el) el.textContent = 'Lector U.areU conectado. Listo para capturar.';
    });
    FP.on('DeviceDisconnected', () => {
      const el = readerMsg();
      if (el) el.textContent = 'Lector desconectado. Conecte el U.areU 5300 por USB.';
    });
    FP.on('SamplesAcquired', onSamplesAcquired);
    FP.on('QualityReported', (e) => {
      const q = e?.quality;
      if (q != null && q !== 0 && samples.length < REQUIRED_SCANS) {
        showMsg(false, 'Lectura rechazada (calidad ' + q + '). Limpie el lector, seque el dedo e intente de nuevo.');
      }
    });
    FP.on('ErrorOccurred', (e) => {
      showMsg(false, 'Error en el lector: ' + (e?.message || 'retire el dedo e intente de nuevo'));
    });
    FP.on('CommunicationFailed', () => {
      setDriverUi(false);
      clearWaitTimer();
      const el = readerMsg();
      if (el) {
        el.innerHTML = '<strong>Sin comunicación con el lector.</strong> Verifique HID Lite Client en la bandeja de Windows.';
      }
    });
  }

  async function verificarDriver() {
    const el = readerMsg();
    if (el) el.textContent = 'Verificando lector U.areU…';
    if (typeof window.hayDetectHidDriver !== 'function') {
      setDriverUi(false);
      if (el) el.textContent = 'No se pudo verificar el driver del lector.';
      return;
    }
    const res = await window.hayDetectHidDriver();
    setDriverUi(!!res.ok);
    if (el) {
      el.textContent = res.ok
        ? 'Lector listo. Pulse «Capturar huella» cuando la persona esté presente.'
        : 'Instale o inicie el HID Authentication Device Client en esta PC Windows (icono en la bandeja).';
    }
  }

  function updateProgress() {
    const p = progress();
    const fill = scanFill();
    const txt = scanText();
    if (p) p.style.display = samples.length > 0 || p.style.display === 'block' ? '' : 'none';
    const pct = Math.min(100, (samples.length / REQUIRED_SCANS) * 100);
    if (fill) fill.style.width = pct + '%';
    stepEls().forEach((step, idx) => {
      const n = idx + 1;
      step.classList.toggle('is-done', n <= samples.length);
      step.classList.toggle('is-active', n === samples.length + 1 && samples.length < REQUIRED_SCANS);
    });
    if (txt) {
      if (samples.length >= REQUIRED_SCANS) {
        txt.textContent = 'Captura completa (' + REQUIRED_SCANS + '/' + REQUIRED_SCANS + '). Guardando…';
      } else {
        txt.textContent = 'Lectura ' + (samples.length + 1) + ' de ' + REQUIRED_SCANS + ' — coloque el mismo dedo en el lector';
      }
    }
  }

  async function onSamplesAcquired(event) {
    if (guardando) return;
    const now = Date.now();
    if (now - lastSampleAt < SAMPLE_COOLDOWN_MS) return;

    clearWaitTimer();
    try {
      const sample = extraerMuestra(event);
      if (!sample) {
        showMsg(false, 'El lector respondió pero la muestra llegó vacía. Intente de nuevo.');
        return;
      }

      const firma = sample.length > 96 ? sample.substring(0, 96) : sample;
      if (samples.some((s) => {
        const otra = s.length > 96 ? s.substring(0, 96) : s;
        return otra === firma;
      })) {
        return;
      }

      lastSampleAt = now;
      await FP.stopAcquisition();

      samples.push(sample);
      updateProgress();
      showMsg(true, 'Lectura ' + samples.length + ' de ' + REQUIRED_SCANS + ' capturada.');

      if (samples.length >= REQUIRED_SCANS) {
        guardando = true;
        await guardarHuella();
      } else {
        showMsg(true, 'Lectura ' + samples.length + ' de ' + REQUIRED_SCANS + ' OK. Coloque el mismo dedo otra vez…');
        scheduleWaitHint();
        await FP.startAcquisition('enroll', Fingerprint.SampleFormat.Intermediate);
      }
    } catch (err) {
      showMsg(false, err.message || 'Error al capturar');
      resetCapture();
    }
  }

  function scheduleWaitHint() {
    clearWaitTimer();
    waitTimer = setTimeout(() => {
      if (samples.length > 0 && samples.length < REQUIRED_SCANS && FP?.isAcquiring?.()) {
        showMsg(false, 'No se detectó el dedo. Coloque el mismo dedo en el lector U.areU (lectura '
          + (samples.length + 1) + ' de ' + REQUIRED_SCANS + ').');
      }
    }, 12000);
  }

  function resetCapture() {
    clearWaitTimer();
    guardando = false;
    samples = [];
    updateProgress();
    const btn = btnCapturar();
    if (btn) btn.disabled = !sdkReady || !driverOk;
  }

  async function guardarHuella() {
    const codigo = document.getElementById('huella-codigo')?.value?.trim() || '';
    const dedo = document.getElementById('huella-dedo')?.value || 'indice_derecho';

    const fd = new FormData();
    fd.append('action', 'registrar');
    fd.append(entityField, String(entityId));
    fd.append('codigo_huella', codigo);
    fd.append('dedo', dedo);
    fd.append('samples', JSON.stringify(samples));

    try {
      const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
      if (data.status !== 'ok') throw new Error(data.message || 'Error al guardar');
      await FP.releaseAll();
      showMsg(true, data.message || 'Huella registrada');
      setTimeout(() => cargarSeccion(redirectSection, redirectParam), 1200);
    } catch (err) {
      showMsg(false, err.message || 'Error al guardar la huella');
      guardando = false;
      resetCapture();
    }
  }

  async function iniciarCaptura() {
    if (!sdkReady || !FP) {
      showMsg(false, 'SDK del lector no disponible.');
      return;
    }
    if (!driverOk) {
      showMsg(false, 'El HID Lite Client no responde. Verifique que esté en ejecución en Windows.');
      await verificarDriver();
      return;
    }
    samples = [];
    const p = progress();
    if (p) p.style.display = '';
    updateProgress();
    const btn = btnCapturar();
    if (btn) btn.disabled = true;
    showMsg(true, 'Coloque el mismo dedo ' + REQUIRED_SCANS + ' veces en el lector U.areU…');

    try {
      await FP.startAcquisition('enroll', Fingerprint.SampleFormat.Intermediate);
      showMsg(true, 'Esperando lectura 1 de ' + REQUIRED_SCANS + '… Coloque el dedo en el lector ahora.');
      scheduleWaitHint();
    } catch (err) {
      showMsg(false, FP.parseStartError(err));
      resetCapture();
    }
  }

  try {
    if (cfg.sdk_files_ok === false) {
      throw new Error('Archivos SDK no encontrados en el servidor');
    }
    if (!FP?.sdkOk?.()) {
      throw new Error('SDK DigitalPersona no disponible');
    }
    bindReaderEvents();
    sdkReady = true;
    verificarDriver();
  } catch (err) {
    sdkReady = false;
    setDriverUi(false);
    const el = readerMsg();
    if (el) {
      el.innerHTML = '<strong>Lector no disponible.</strong> ' + (err.message || '');
    }
  }

  const btn = btnCapturar();
  if (btn) {
    btn.onclick = iniciarCaptura;
  }
};
