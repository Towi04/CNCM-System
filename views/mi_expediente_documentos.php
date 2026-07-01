<?php
require_once __DIR__ . '/../config.php';
if (!expediente_documental_puede_ver_mi_expediente()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}
$idUser = (int) ($_SESSION['user_id'] ?? 0);
$entidades = expediente_documental_entidades_usuario($pdo, $idUser);
$tipoPre = trim((string) ($_GET['tipo'] ?? ''));
$idPre = (int) ($_GET['id'] ?? 0);
$apiUrl = hay_asset_url('php/expediente_documental_api.php');
$streamUrl = hay_asset_url('php/expediente_documento_stream.php');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css')); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css')); ?>">

<div class="catalog-wrap" id="exp-mi-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-folder-open"></i> Mi expediente documental</h2>
    <p style="color:#666;">Suba y actualice los documentos que le solicite la institución. Formatos: PDF, JPG, PNG (máx. 8 MB).</p>
  </div>

  <div id="exp-mi-msg" class="catalog-alert" style="display:none;"></div>

  <?php if (count($entidades) > 1): ?>
  <div style="margin-bottom:14px;">
    <label>Perfil</label>
    <select id="exp-mi-entidad" class="catalog-input">
      <?php foreach ($entidades as $e): ?>
      <option value="<?php echo htmlspecialchars($e['tipo'] . ':' . $e['id']); ?>"
        <?php echo ($tipoPre === $e['tipo'] && $idPre === (int) $e['id']) ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($e['label']); ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php elseif (count($entidades) === 1): ?>
  <input type="hidden" id="exp-mi-entidad" value="<?php echo htmlspecialchars($entidades[0]['tipo'] . ':' . $entidades[0]['id']); ?>">
  <?php else: ?>
  <div class="alert">No hay expediente asociado a su cuenta.</div>
  <?php endif; ?>

  <div id="exp-mi-lista"></div>
</div>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/expediente_documentos.js')); ?>"></script>
<script>
window.hayExpedienteMiInit = function () {
  hayExpediente.initMi({
    api: <?php echo json_encode($apiUrl); ?>,
    stream: <?php echo json_encode($streamUrl); ?>,
    wrap: document.getElementById('exp-mi-wrap'),
  });
};
hayExpedienteMiInit();
</script>
