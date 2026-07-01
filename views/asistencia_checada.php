<?php
require_once __DIR__ . '/../config.php';
if (!asistencia_puede_checada()) {
    echo '<div class="alert">No autorizado para la terminal de checada.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$plantelNombre = $_SESSION['plantel_nombre'] ?? 'Plantel';
$puedePago = rbac_cap('menu_consulta_adeudo') || rbac_cap('menu_punto_venta');
$huellaCfg = huella_config_js();
$hidLinks = hay_hid_lite_client_links();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/asistencia.css'), ENT_QUOTES, 'UTF-8'); ?>">

<?php
$hid_banner_context = 'checada de asistencia en recepción';
require __DIR__ . '/partials/hid_lite_client_banner.php';
?>

<div class="asist-checada-wrap">
  <header class="asist-checada-header">
    <div>
      <h2><i class="fas fa-fingerprint"></i> Checada con huella</h2>
      <p class="asist-checada-sub"><?php echo htmlspecialchars($plantelNombre); ?> · <span id="checada-reloj"><?php echo date('d/m/Y H:i'); ?></span></p>
    </div>
    <div class="asist-checada-header__actions">
      <button type="button" class="primary" onclick="cargarSeccion('asistencia_faltantes')">
        <i class="fas fa-door-open"></i> Rondín (sin huella)
      </button>
      <button type="button" class="secondary" onclick="cargarSeccion('asistencia_registros')">
        <i class="fas fa-list"></i> Registros / corregir
      </button>
      <button type="button" onclick="cargarSeccion('asistencia')"><i class="fas fa-arrow-left"></i> Asistencias</button>
    </div>
  </header>

  <div id="checada-driver-ok" class="checada-driver-ok" hidden>
    <i class="fas fa-check-circle"></i>
    <strong>Lector listo.</strong> Esperando que alguien checque con huella…
  </div>

  <div id="checada-scan-status" class="checada-scan-status" hidden aria-live="polite"></div>

  <div id="checada-driver-checking" class="checada-driver-checking">
    <i class="fas fa-spinner fa-spin"></i> Verificando driver del lector…
  </div>

  <?php $fjCfg = huella_fingerjet_config(); if ($fjCfg['enabled']): ?>
  <p class="asist-checada-hint" style="margin-bottom:14px; padding:10px 12px; background:#e8f4fd; border-radius:8px; border:1px solid #90caf9;">
    <i class="fas fa-microchip"></i> Identificación <strong>FingerJet</strong> activa
    (modo <?php echo htmlspecialchars($fjCfg['mode']); ?>).
    El servicio local debe estar en ejecución en esta PC:
    <code><?php echo htmlspecialchars($fjCfg['matcher_url']); ?>/health</code>
  </p>
  <?php endif; ?>

  <p class="asist-checada-hint" style="margin-bottom:14px;">
    Si un alumno no usa huella (preferencia personal o el lector no lee bien), registre su asistencia en
    <a href="#" onclick="cargarSeccion('asistencia_faltantes'); return false;">Rondín de asistencia</a>
    con su número de control.
  </p>

  <div class="asist-checada-layout">
    <section class="asist-checada-espera" id="checada-espera" hidden>
      <div class="asist-checada-pulse" aria-hidden="true"><i class="fas fa-fingerprint"></i></div>
      <h3>Esperando checada…</h3>
      <p>Coloque el dedo en el lector U.areU.</p>
      <p class="asist-checada-hint">La asistencia se registra automáticamente al detectar la huella.</p>
    </section>

    <section class="asist-checada-resultado" id="checada-resultado" hidden>
      <div id="checada-resultado-inner"></div>
      <div class="asist-checada-resultado__actions">
        <button type="button" class="primary" id="btn-checada-siguiente">Siguiente persona</button>
      </div>
    </section>
  </div>

  <section class="checada-sesion-log" id="checada-sesion-log">
    <header class="checada-sesion-log__header">
      <h3><i class="fas fa-list-ul"></i> Checadas de esta sesión (<span id="checada-sesion-count">0</span>)</h3>
      <p class="asist-checada-hint">Se conserva mientras permanezca en esta pantalla. No se actualiza sola.</p>
    </header>
    <div class="checada-sesion-log__table-wrap">
      <table class="checada-sesion-table">
        <thead>
          <tr>
            <th>Hora</th>
            <th>Nombre</th>
            <th>No. control</th>
            <th>Grupo</th>
            <th>Estado</th>
            <th>Notas</th>
          </tr>
        </thead>
        <tbody id="checada-sesion-body">
          <tr class="checada-sesion-empty"><td colspan="6">Aún no hay checadas en esta sesión.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script>
window.HAY_CHECADA_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/asistencia_checada_api.php'),
    'pago_api' => hay_asset_url('php/pago_registrar.php'),
    'puede_pago' => $puedePago,
    'poll_ms' => 2000,
    'websdk_js' => $huellaCfg['websdk_js'],
    'fingerprint_js' => $huellaCfg['fingerprint_js'],
    'lite_client_url' => $hidLinks['url'],
    'lite_client_local_url' => $hidLinks['local_url'],
    'fingerjet' => huella_fingerjet_config_js($idPlantel),
], JSON_UNESCAPED_UNICODE); ?>;
(function () {
  const cfg = window.HAY_CHECADA_CONFIG;
  if (!cfg || typeof window.hayResolveAssetUrl !== 'function') return;
  ['api', 'pago_api', 'websdk_js', 'fingerprint_js'].forEach(function (k) {
    if (cfg[k]) cfg[k] = window.hayResolveAssetUrl(cfg[k]);
  });
})();
</script>
<script src="<?php echo htmlspecialchars($huellaCfg['websdk_js'], ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($huellaCfg['fingerprint_js'], ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/hay_fingerprint_reader.js?v=20260605'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/hid_driver_check.js?v=20260605'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/asistencia_checada.js?v=20260606'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
if (typeof window.hayChecadaBoot === 'function') window.hayChecadaBoot();
</script>
