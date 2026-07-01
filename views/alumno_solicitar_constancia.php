<?php

require_once __DIR__ . '/../config.php';

if (!alumno_portal_puede_ver()) {

    echo '<div class="alert">Sin permiso.</div>';

    return;

}

$idAlumno = alumno_portal_id_o_detener();

if ($idAlumno <= 0) {

    return;

}

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/documento_emitido.css?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>">



<div class="catalog-wrap doc-alumno-wrap">

  <div class="catalog-header">

    <h2><i class="fas fa-file-certificate"></i> Solicitar constancia</h2>

    <p style="color:#666;">Seleccione la información que debe incluir. Pase a recepción a pagar; podrá descargarla cuando esté marcada como pagada.</p>

  </div>



  <button type="button" class="secondary" style="margin-bottom:12px;" onclick="cargarSeccion('alumno_portal_inicio')">← Inicio</button>



  <div id="doc-alumno-precio" class="doc-precio-box"></div>



  <fieldset class="doc-opciones-fieldset">

    <legend>Información a incluir</legend>

    <div id="doc-alumno-opciones" class="doc-opciones-grid"></div>

  </fieldset>



  <div id="doc-alumno-manuales" class="doc-manuales"></div>



  <button type="button" class="primary" id="btn-doc-solicitar"><i class="fas fa-paper-plane"></i> Enviar solicitud</button>



  <h3 style="margin-top:24px;">Mis constancias</h3>

  <div id="doc-alumno-lista" class="doc-lista"></div>

</div>



<script>

window.HAY_DOC_ALUMNO = <?php echo json_encode([

    'api' => hay_asset_url('php/documento_api.php'),

    'pdf' => hay_asset_url('documento_pdf.php'),

], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/alumno_solicitar_constancia.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>

