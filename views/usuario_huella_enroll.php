<?php
require_once __DIR__ . '/../config.php';
if (!huella_puede_enrolar_usuario()) {
    echo '<div class="alert">No autorizado para registrar huellas del personal.</div>';
    return;
}

$idUsuario = (int) ($_GET['id'] ?? 0);
$idPlantel = plantel_scope_id($pdo);

if ($idUsuario <= 0) {
    echo '<div class="alert">Usuario no indicado.</div>';
    return;
}

$est = huella_estado_usuario($pdo, $idUsuario, $idPlantel);
if (!$est['ok']) {
    echo '<div class="alert">' . htmlspecialchars($est['message'] ?? 'Error') . '</div>';
    return;
}

$cfg = huella_config_js();
$cfg['entity_type'] = 'usuario';
$cfg['api_enroll'] = hay_asset_url('php/usuario_huella_enroll_api.php');
$cfg['redirect_section'] = 'usuario_editar';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/asistencia.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<?php if (!$cfg['sdk_files_ok']): ?>
<div class="catalog-alert catalog-alert--error" style="display:block; margin-bottom:16px;">
  <strong>Faltan archivos SDK en el servidor.</strong>
  Suba la carpeta <code>js/vendor/digitalpersona/</code> completa al hosting.
</div>
<?php endif; ?>

<?php
$hid_banner_context = 'registrar huella del personal con el lector U.areU 5300';
require __DIR__ . '/partials/hid_lite_client_banner.php';
?>

<div class="catalog-wrap huella-enroll-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-fingerprint"></i> Registrar huella del personal</h2>
  </div>

  <div class="huella-enroll-card">
    <div id="huella-enroll-resumen" class="insc-wizard-resumen">
      <strong><?php echo htmlspecialchars($est['nombre']); ?></strong><br>
      Usuario: <?php echo htmlspecialchars($est['username']); ?><br>
      Número de control (huella): <strong><?php echo htmlspecialchars($est['codigo_sugerido']); ?></strong>
      <?php if ($est['huella_registrada']): ?>
        <br><span style="color:#2e7d32;">Huella ya registrada
          <?php if ($est['huella_registrada_en']): ?>
            (<?php echo date('d/m/Y H:i', strtotime($est['huella_registrada_en'])); ?>)
          <?php endif; ?>
        </span>
      <?php endif; ?>
    </div>

    <div id="huella-enroll-status" class="huella-enroll-status" aria-live="polite">
      <p id="huella-reader-msg">Verificando lector U.areU…</p>
    </div>

    <div id="huella-enroll-uareu" class="huella-enroll-panel">
      <h3 style="margin:0 0 8px; font-size:1rem;">Lector U.areU 5300 (recepción)</h3>
      <p style="font-size:0.88rem; color:#555; margin:0 0 12px;">
        Conecte el lector USB. Requiere <strong>HID Authentication Device Client</strong> instalado en esta PC Windows.
        El personal checará entrada y salida en la terminal de recepción.
      </p>

      <label>Dedo a registrar</label>
      <select id="huella-dedo" style="width:100%; padding:10px; margin:6px 0 12px; border-radius:8px; border:1px solid #ddd;">
        <option value="indice_derecho">Índice derecho</option>
        <option value="indice_izquierdo">Índice izquierdo</option>
        <option value="pulgar_derecho">Pulgar derecho</option>
        <option value="pulgar_izquierdo">Pulgar izquierdo</option>
      </select>

      <input type="hidden" id="huella-codigo"
        value="<?php echo htmlspecialchars($est['codigo_huella'] ?: $est['codigo_sugerido']); ?>">

      <div id="huella-scan-progress" data-total-scans="3" style="display:none; margin-bottom:12px;">
        <div class="huella-scan-steps" aria-hidden="true">
          <span class="huella-scan-step" data-step="1">1</span>
          <span class="huella-scan-step" data-step="2">2</span>
          <span class="huella-scan-step" data-step="3">3</span>
        </div>
        <div class="huella-scan-bar"><div id="huella-scan-fill" class="huella-scan-fill"></div></div>
        <p id="huella-scan-text" style="font-size:0.85rem; color:#666; margin:6px 0 0;">Lectura 1 de 3 — coloque el mismo dedo en el lector</p>
      </div>

      <button type="button" class="primary" id="btn-huella-capturar" style="width:100%; margin-bottom:8px;">
        <i class="fas fa-fingerprint"></i> Capturar huella (3 lecturas del mismo dedo)
      </button>
    </div>

    <div id="huella-enroll-msg" class="catalog-alert" style="display:none; margin-top:16px;"></div>

    <div style="display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; margin-top:20px;">
      <button type="button" onclick="cargarSeccion('usuario_editar', 'id=<?php echo (int)$idUsuario; ?>')">Volver al usuario</button>
      <button type="button" onclick="cargarSeccion('ver_usuarios')">Lista de personal</button>
    </div>
  </div>
</div>

<script>
window.HAY_HUELLA_CONFIG = <?php echo json_encode($cfg, JSON_UNESCAPED_UNICODE); ?>;
window.HAY_HUELLA_ENTITY_ID = <?php echo (int) $idUsuario; ?>;
(function () {
  const cfg = window.HAY_HUELLA_CONFIG;
  if (!cfg || typeof window.hayResolveAssetUrl !== 'function') return;
  ['api_enroll', 'websdk_js', 'fingerprint_js'].forEach(function (k) {
    if (cfg[k]) cfg[k] = window.hayResolveAssetUrl(cfg[k]);
  });
})();
</script>
<script src="<?php echo htmlspecialchars($cfg['websdk_js'], ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($cfg['fingerprint_js'], ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/hay_fingerprint_reader.js?v=20260605'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/hid_driver_check.js?v=20260605'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/huella_uareu.js?v=20260606'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
if (typeof window.hayHuellaEnrollBoot === 'function') window.hayHuellaEnrollBoot();
</script>
