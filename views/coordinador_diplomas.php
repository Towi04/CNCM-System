<?php

require_once __DIR__ . '/../config.php';

if (!documento_puede_gestionar_diplomas()) {

    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';

    return;

}

$idPlantel = plantel_scope_id($pdo);

$st = $pdo->prepare('SELECT id_grupo, clave FROM grupos WHERE id_plantel = ? ORDER BY clave');

$st->execute([$idPlantel]);

$grupos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/documento_emitido.css?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>">



<div class="catalog-wrap">

  <div class="catalog-header">

    <h2><i class="fas fa-award"></i> Diplomas por grupo</h2>

    <p style="color:#666;">Genere diplomas al finalizar un curso. Imprima o descargue todos los PDF del grupo.</p>

  </div>



  <div class="catalog-toolbar">

    <div>

      <label>Grupo</label>

      <select id="doc-dip-grupo">

        <option value="">Seleccione…</option>

        <?php foreach ($grupos as $g): ?>

          <option value="<?php echo (int) $g['id_grupo']; ?>"><?php echo htmlspecialchars($g['clave']); ?></option>

        <?php endforeach; ?>

      </select>

    </div>

    <div style="align-self:flex-end; display:flex; gap:8px;">

      <button type="button" class="primary" id="btn-doc-dip-generar"><i class="fas fa-magic"></i> Generar diplomas</button>

      <button type="button" class="secondary" id="btn-doc-dip-zip" disabled><i class="fas fa-download"></i> Descargar todos</button>

    </div>

  </div>



  <div id="doc-dip-lista" class="doc-lista"></div>

</div>



<script>

window.HAY_DOC_DIPLOMA = <?php echo json_encode([

    'api' => hay_asset_url('php/documento_api.php'),

    'pdf' => hay_asset_url('documento_pdf.php'),

], JSON_UNESCAPED_UNICODE); ?>;

</script>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/coordinador_diplomas.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>

