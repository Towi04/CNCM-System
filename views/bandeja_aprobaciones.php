<?php

require_once __DIR__ . '/../config.php';

if (!bandeja_aprobaciones_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para la bandeja de aprobaciones.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$resumen = bandeja_aprobaciones_resumen($pdo, $idPlantel);
$filtroInicial = trim($_GET['filtro'] ?? '');
if (!in_array($filtroInicial, ['permiso_profesor', 'inscripcion', 'grupo_apertura'], true)) {
    $filtroInicial = '';
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/bandeja_aprobaciones.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap bandeja-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-inbox"></i> Bandeja de aprobaciones</h2>
    <button type="button" class="secondary" id="bandeja-refrescar"><i class="fas fa-sync"></i> Actualizar</button>
  </div>
  <p style="color:#666; margin-top:0;">
    Permisos de profesores, autorizaciones de inscripción y apertura de grupos en un solo lugar.
  </p>

  <div id="bandeja-msg" class="catalog-alert" style="display:none;"></div>

  <div class="bandeja-resumen" id="bandeja-filtros" role="tablist" aria-label="Filtrar bandeja">
    <button type="button" class="bandeja-chip<?php echo $filtroInicial === '' ? ' active' : ''; ?>" data-filtro="">
      Todos <span class="n" id="chip-total"><?php echo (int) $resumen['total']; ?></span>
    </button>
    <button type="button" class="bandeja-chip<?php echo $filtroInicial === 'permiso_profesor' ? ' active' : ''; ?>" data-filtro="permiso_profesor">
      Permisos <span class="n" id="chip-permisos"><?php echo (int) $resumen['permisos']; ?></span>
    </button>
    <button type="button" class="bandeja-chip<?php echo $filtroInicial === 'inscripcion' ? ' active' : ''; ?>" data-filtro="inscripcion">
      Inscripciones <span class="n" id="chip-inscripciones"><?php echo (int) $resumen['inscripciones']; ?></span>
    </button>
    <button type="button" class="bandeja-chip<?php echo $filtroInicial === 'grupo_apertura' ? ' active' : ''; ?>" data-filtro="grupo_apertura">
      Apertura grupos <span class="n" id="chip-grupos"><?php echo (int) $resumen['grupos']; ?></span>
    </button>
  </div>

  <div id="bandeja-loading" class="bandeja-loading" hidden><i class="fas fa-spinner fa-spin"></i> Cargando…</div>
  <div id="bandeja-lista" class="bandeja-lista"></div>
</div>

<script>
window.HAY_BANDEJA_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/bandeja_aprobaciones_api.php'),
    'filtroInicial' => $filtroInicial,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/bandeja_aprobaciones.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>
