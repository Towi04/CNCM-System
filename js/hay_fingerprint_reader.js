/**
 * Gestor único del lector U.areU — evita conflictos entre checada y enrolamiento.
 */
(function () {
  const RELEASE_DELAY_MS = 450;
  const START_RETRIES = 3;

  let reader = null;
  let owner = null;
  let acquiring = false;
  let releasePromise = null;
  /** @type {Record<string, Function>} */
  const handlers = {};

  function sdkOk() {
    return typeof Fingerprint !== 'undefined'
      && typeof Fingerprint.WebApi === 'function'
      && typeof Fingerprint.SampleFormat !== 'undefined';
  }

  function getReader() {
    if (!reader && sdkOk()) {
      reader = new Fingerprint.WebApi();
    }
    return reader;
  }

  function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  function parseStartError(err) {
    const msg = String(err?.message || err || '');
    if (msg.includes('80070057')) {
      return 'El lector está ocupado. Espere un momento e intente de nuevo.';
    }
    if (msg.includes('Communication failure')) {
      return 'No hay comunicación con el HID Lite Client. Verifique que esté en ejecución.';
    }
    return msg || 'No se pudo iniciar el lector';
  }

  function attachHandlers() {
    const r = getReader();
    if (!r) return;
    Object.keys(handlers).forEach((event) => {
      const fn = handlers[event];
      if (typeof fn === 'function') {
        r.on(event, fn);
      }
    });
  }

  function on(event, handler) {
    handlers[event] = handler;
    const r = getReader();
    if (r) r.on(event, handler);
    return api;
  }

  function off(event) {
    if (event) {
      delete handlers[event];
    } else {
      Object.keys(handlers).forEach((k) => delete handlers[k]);
    }
    if (reader?.off) {
      try {
        reader.off(event);
      } catch (_) { /* ignore */ }
    }
    return api;
  }

  async function pingService(timeoutMs) {
    timeoutMs = timeoutMs || 6000;
    if (!sdkOk()) return false;
    if (acquiring) return true;
    const r = getReader();
    if (!r) return false;
    try {
      await Promise.race([
        r.enumerateDevices(),
        sleep(timeoutMs).then(() => Promise.reject(new Error('timeout'))),
      ]);
      return true;
    } catch (_) {
      return false;
    }
  }

  async function stopAcquisition() {
    const r = getReader();
    if (!r || !acquiring) {
      acquiring = false;
      return;
    }
    try {
      await r.stopAcquisition();
    } catch (_) { /* ignore */ }
    acquiring = false;
    await sleep(RELEASE_DELAY_MS);
  }

  async function releaseAll() {
    if (releasePromise) return releasePromise;
    releasePromise = (async () => {
      await stopAcquisition();
      if (reader?.off) {
        try { reader.off(); } catch (_) { /* ignore */ }
      }
      owner = null;
    })().finally(() => {
      releasePromise = null;
    });
    return releasePromise;
  }

  async function startAcquisition(newOwner, format) {
    if (!sdkOk()) {
      throw new Error('SDK DigitalPersona no disponible');
    }
    await stopAcquisition();
    if (owner && owner !== newOwner) {
      await sleep(RELEASE_DELAY_MS);
    }

    const r = getReader();
    if (!r) {
      throw new Error('No se pudo inicializar el lector');
    }

    attachHandlers();

    let lastErr = null;
    for (let i = 0; i < START_RETRIES; i++) {
      try {
        await r.startAcquisition(format);
        owner = newOwner;
        acquiring = true;
        return r;
      } catch (err) {
        lastErr = err;
        await stopAcquisition();
        await sleep(RELEASE_DELAY_MS * (i + 1));
      }
    }
    throw new Error(parseStartError(lastErr));
  }

  const api = {
    sdkOk,
    getReader,
    getOwner: () => owner,
    isAcquiring: () => acquiring,
    releaseAll,
    stopAcquisition,
    startAcquisition,
    pingService,
    attachHandlers,
    on,
    off,
    parseStartError,
  };

  window.HayFingerprintReader = api;
})();
