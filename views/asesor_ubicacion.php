<?php
require_once __DIR__ . '/../config.php';
if (!ubicacion_puede_asesor_gestionar()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para ver ubicaciones.</div>';
    return;
}

$idDetalle = (int) ($_GET['id'] ?? 0);
$filtroEstado = $_GET['estado'] ?? '';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/ubicacion.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap ub-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-map-signs"></i> Examen de ubicación</h2>
    <p style="color:#666; margin:0;">Alumnos que presentaron examen de ubicación. Cuando coordinación autorice el nivel y los grupos, contacte al prospecto y asígnelo al grupo correspondiente.</p>
  </div>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Estado</label>
      <select id="ub-asesor-filtro">
        <option value="">Pendientes y autorizados</option>
        <?php foreach (ubicacion_estados_etiquetas() as $k => $lbl): ?>
          <option value="<?php echo htmlspecialchars($k); ?>"<?php echo $filtroEstado === $k ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($lbl); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="button" class="primary" id="btn-ub-asesor-listar">Actualizar</button>
  </div>

  <div id="ub-asesor-msg" class="catalog-alert" style="display:none;"></div>

  <div class="ub-layout">
    <div class="ub-lista" id="ub-asesor-lista"></div>

    <div class="ub-editor" id="ub-asesor-editor"<?php echo $idDetalle > 0 ? '' : ' hidden'; ?>>
      <h3>Seguimiento de ubicación</h3>
      <input type="hidden" id="ub-asesor-id" value="<?php echo $idDetalle; ?>">

      <div id="ub-asesor-info"></div>

      <div id="ub-asesor-pendiente" class="ub-alumno-banner ub-alumno-banner--warn" style="display:none;">
        <strong>En evaluación.</strong> Coordinación aún no autoriza el nivel ni los grupos. Contacte al alumno para informarle y espere la autorización.
      </div>

      <div id="ub-asesor-asignar" style="display:none;">
        <h4 style="margin:14px 0 8px;">Inscribir en grupo autorizado</h4>
        <p style="font-size:0.85rem; color:#666;">Seleccione uno de los grupos que coordinación autorizó para este alumno.</p>
        <div class="field" style="margin-bottom:12px;">
          <label>Grupo</label>
          <select id="ub-asesor-grupo" style="width:100%; max-width:420px; padding:8px;"></select>
        </div>
        <button type="button" class="primary" id="btn-ub-asesor-inscribir">
          <i class="fas fa-user-check"></i> Inscribir alumno en grupo
        </button>
      </div>

      <div id="ub-asesor-usado" class="ub-alumno-banner ub-alumno-banner--ok" style="display:none;">
        El alumno ya fue inscrito en un grupo autorizado.
      </div>
    </div>
  </div>
</div>

<script>
window.HAY_ASESOR_UBICACION = <?php echo json_encode(['api' => hay_asset_url('php/ubicacion_api.php')], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/asesor_ubicacion.js?v=20260605'), ENT_QUOTES, 'UTF-8'); ?>"></script>
