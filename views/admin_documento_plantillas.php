<?php

require_once __DIR__ . '/../config.php';

if (!documento_puede_configurar_plantillas()) {

    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';

    return;

}

$idPlantel = plantel_scope_id($pdo);

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/documento_emitido.css?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>">



<div class="catalog-wrap doc-plantilla-wrap">

  <div class="catalog-header">

    <h2><i class="fas fa-file-image"></i> Plantillas de constancias y diplomas</h2>

    <p style="color:#666;">Suba la imagen de fondo y ubique cada campo en milímetros (como certificados Moodle). Incluya firma digital y QR.</p>

  </div>



  <div class="catalog-toolbar doc-plantilla-toolbar">

    <div>

      <label>Plantilla</label>

      <select id="doc-pl-select"><option value="">— Nueva —</option></select>

    </div>

    <div>

      <label>Tipo</label>

      <select id="doc-pl-tipo">

        <option value="constancia">Constancia</option>

        <option value="diploma">Diploma</option>

      </select>

    </div>

    <div>

      <label>Nombre</label>

      <input type="text" id="doc-pl-nombre" placeholder="Ej. Constancia oficial 2026">

    </div>

    <div>

      <label>Vigencia (días)</label>

      <input type="number" id="doc-pl-vigencia" value="90" min="1" max="3650">

    </div>

    <div>

      <label>Fondo (JPG/PNG)</label>

      <input type="file" id="doc-pl-fondo" accept="image/*">

    </div>

    <div>

      <label>Firma digital</label>

      <input type="file" id="doc-pl-firma" accept="image/*">

    </div>

    <div style="align-self:flex-end;">

      <button type="button" class="primary" id="btn-doc-pl-guardar">Guardar plantilla</button>

    </div>

  </div>



  <div class="doc-pl-layout">

    <div class="doc-pl-campos">

      <h3>Campos en el documento</h3>

      <p style="font-size:0.88rem;color:#666;">Coordenadas en mm desde la esquina superior izquierda (carta ≈ 216×279 mm).</p>

      <div id="doc-pl-campos-list"></div>

      <button type="button" class="secondary" id="btn-doc-pl-add-campo">+ Agregar campo</button>

    </div>

    <div class="doc-pl-preview" id="doc-pl-preview">

      <p style="color:#888;">Vista previa aproximada</p>

    </div>

  </div>

</div>



<script>

window.HAY_DOC_PLANTILLA = <?php echo json_encode([

    'api' => hay_asset_url('php/documento_api.php'),

    'campos_constancia' => documento_campos_disponibles('constancia'),

    'campos_diploma' => documento_campos_disponibles('diploma'),

    'plantillas' => documento_plantillas_listar($pdo, null, $idPlantel),

], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/admin_documento_plantillas.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>

