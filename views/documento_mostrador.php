<?php

require_once __DIR__ . '/../config.php';

if (!documento_puede_mostrador()) {

    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';

    return;

}

$qInicial = trim($_GET['q'] ?? '');

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/documento_emitido.css?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>">



<div class="catalog-wrap">

  <div class="catalog-header">

    <h2><i class="fas fa-id-card"></i> Mostrador de documentos</h2>

    <p style="color:#666;">Busque por número de control, nombre del alumno o folio del documento. Reimprima constancias y diplomas emitidos o abra la página de verificación QR.</p>

  </div>



  <div class="catalog-toolbar">

    <label style="flex:1; min-width:220px;">

      Buscar

      <input type="search" id="doc-most-q" placeholder="Control, nombre o folio…" autocomplete="off" style="width:100%; max-width:420px; margin-top:4px;">

    </label>

    <button type="button" class="primary" id="doc-most-btn"><i class="fas fa-search"></i> Buscar</button>

  </div>



  <div id="doc-most-alumno" hidden class="doc-precio-box" style="margin-bottom:16px;"></div>



  <div id="doc-most-resultados" class="doc-lista"></div>

  <p id="doc-most-msg" style="color:#888; margin-top:12px;"></p>

</div>



<script>

window.HAY_DOC_MOSTRADOR = <?php echo json_encode([
    'api' => hay_asset_url('php/documento_api.php'),
    'piso_api' => hay_asset_url('php/operativo_piso_api.php'),
    'q_inicial' => $qInicial,
], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/documento_mostrador.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>

