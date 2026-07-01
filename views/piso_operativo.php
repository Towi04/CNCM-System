<?php

require_once __DIR__ . '/../config.php';

if (!operativo_piso_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para el piso operativo.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$resumen = operativo_piso_resumen($pdo, $idPlantel);
$tabInicial = trim($_GET['tab'] ?? '');
if (!in_array($tabInicial, ['entrega', 'atajos'], true)) {
    $tabInicial = $resumen['entrega_total'] > 0 ? 'entrega' : 'atajos';
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/documento_emitido.css?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/piso_operativo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap piso-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-concierge-bell"></i> Piso operativo</h2>
    <button type="button" class="secondary" id="piso-refrescar"><i class="fas fa-sync"></i> Actualizar</button>
  </div>
  <p style="color:#666; margin-top:0;">
    Entrega física de diplomas y constancias, atajos de cobranza y búsqueda rápida de alumno.
  </p>

  <div id="piso-msg" class="catalog-alert" style="display:none;"></div>

  <div class="piso-buscar">
    <label style="flex:1; min-width:220px;">
      Buscar alumno
      <input type="search" id="piso-buscar-q" placeholder="Control, nombre o matrícula…" autocomplete="off">
    </label>
    <button type="button" class="primary" id="piso-buscar-btn"><i class="fas fa-search"></i> Buscar</button>
  </div>
  <div id="piso-buscar-acciones" class="piso-acciones-rapidas" hidden></div>

  <h3 class="catalog-subtitle"><i class="fas fa-bolt"></i> Atajos de cobranza</h3>
  <div id="piso-atajos" class="piso-atajos"></div>

  <h3 class="catalog-subtitle" style="margin-top:8px;">
    <i class="fas fa-hand-holding"></i> Cola de entrega
    <span id="piso-entrega-badge" style="font-size:0.85rem; color:#666; font-weight:normal;"></span>
  </h3>

  <div class="piso-filtros" id="piso-filtros">
    <button type="button" class="piso-chip active" data-tipo="">Todos</button>
    <button type="button" class="piso-chip" data-tipo="diploma">Diplomas</button>
    <button type="button" class="piso-chip" data-tipo="constancia">Constancias</button>
  </div>

  <div id="piso-entrega-loading" class="catalog-alert" style="display:none;">Cargando…</div>
  <div id="piso-entrega-lista" class="piso-entrega-lista"></div>
</div>

<script>
window.HAY_PISO_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/operativo_piso_api.php'),
    'buscarApi' => hay_asset_url('php/operativo_panel_api.php'),
    'tabInicial' => $tabInicial,
    'resumen' => $resumen,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/piso_operativo.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>
