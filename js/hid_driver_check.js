/**
 * Detecta si el HID Authentication Device Client responde en esta PC.
 * Usa el gestor compartido HayFingerprintReader (una sola instancia WebApi).
 */
window.hayDetectHidDriver = async function hayDetectHidDriver() {
  if (!window.HayFingerprintReader?.sdkOk?.()) {
    return { ok: false, reason: 'sdk' };
  }
  try {
    const ok = await window.HayFingerprintReader.pingService(6000);
    return { ok: !!ok, reason: ok ? 'ok' : 'service' };
  } catch (_) {
    return { ok: false, reason: 'error' };
  }
};
