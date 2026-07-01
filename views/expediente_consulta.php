<?php
require_once __DIR__ . '/../config.php';
if (!expediente_documental_puede_consultar()) {
    echo '<div class="alert">Sin permiso para consultar expedientes.</div>';
    return;
}
$puedeEval = expediente_documental_puede_evaluar();
$tipoPre = trim((string) ($_GET['tipo'] ?? ''));
$idPre = (int) ($_GET['id'] ?? 0);
$apiUrl = hay_asset_url('php/expediente_documental_api.php');
$streamUrl = hay_asset_url('php/expediente_documento_stream.php');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css')); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css')); ?>">

<div class="catalog-wrap" id="exp-cons-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-search"></i> Consulta de expedientes</h2>
    <p style="color:#666;">Busque alumnos, candidatos o personal y revise los documentos entregados.</p>
  </div>

  <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
    <input type="search" id="exp-cons-q" placeholder="Nombre, correo, número de control…" style="flex:1;min-width:200px;">
    <button type="button" class="primary" id="exp-cons-buscar">Buscar</button>
  </div>
  <div id="exp-cons-resultados" style="margin-bottom:16px;"></div>

  <input type="hidden" id="exp-cons-tipo" value="<?php echo htmlspecialchars($tipoPre); ?>">
  <input type="hidden" id="exp-cons-id" value="<?php echo (int) $idPre; ?>">

  <div id="exp-cons-msg" class="catalog-alert" style="display:none;"></div>
  <div id="exp-cons-detalle"></div>
</div>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/expediente_documentos.js')); ?>"></script>
<script>
window.hayExpedienteConsInit = function () {
  hayExpediente.initConsulta({
    api: <?php echo json_encode($apiUrl); ?>,
    stream: <?php echo json_encode($streamUrl); ?>,
    puedeEvaluar: <?php echo $puedeEval ? 'true' : 'false'; ?>,
  });
};
hayExpedienteConsInit();
</script>
