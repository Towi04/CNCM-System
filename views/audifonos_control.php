<?php

require_once __DIR__ . '/../config.php';
rbac_require_cap('menu_audifonos', 'Sin permiso para administrar audífonos.');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-headphones"></i> Control de audífonos</h2>
    <p style="color:#666;">Existencias por plantel, préstamos a profesores y devoluciones completas, parciales o con falla.</p>
  </div>

  <div id="audifonos-msg" class="catalog-alert" style="display:none;"></div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px;">
    <div class="doc-precio-box"><small>Total</small><div id="aud-total" style="font-size:1.8rem;font-weight:700;">0</div></div>
    <div class="doc-precio-box"><small>Prestados</small><div id="aud-prestados" style="font-size:1.8rem;font-weight:700;">0</div></div>
    <div class="doc-precio-box"><small>Disponibles</small><div id="aud-disponibles" style="font-size:1.8rem;font-weight:700;color:#2e7d32;">0</div></div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px;margin-bottom:24px;">
    <form id="aud-stock-form" class="catalog-card" style="padding:16px;">
      <h3 style="margin-top:0;">Ajustar existencia</h3>
      <label>Total de audífonos del plantel
        <input type="number" id="aud-stock-total" name="cantidad_total" min="0" required style="width:100%;margin-top:5px;">
      </label>
      <button type="submit" class="secondary" style="margin-top:12px;"><i class="fas fa-save"></i> Guardar total</button>
    </form>

    <form id="aud-prestar-form" class="catalog-card" style="padding:16px;">
      <h3 style="margin-top:0;">Prestar a profesor</h3>
      <label>Profesor
        <select id="aud-profesor" name="id_profesor" required style="width:100%;margin-top:5px;"></select>
      </label>
      <label style="display:block;margin-top:10px;">Cantidad
        <input type="number" name="cantidad" min="1" required style="width:100%;margin-top:5px;">
      </label>
      <label style="display:block;margin-top:10px;">Notas
        <input type="text" name="notas" maxlength="500" style="width:100%;margin-top:5px;">
      </label>
      <button type="submit" class="primary" style="margin-top:12px;"><i class="fas fa-hand-holding"></i> Registrar préstamo</button>
    </form>
  </div>

  <h3>Préstamos pendientes</h3>
  <div id="aud-prestamos"></div>

  <h3 style="margin-top:26px;">Historial reciente</h3>
  <div class="catalog-table-wrap">
    <table class="catalog-table">
      <thead><tr><th>Profesor</th><th>Cantidad</th><th>Prestado</th><th>Devuelto</th><th>Estado</th><th>Falla / notas</th></tr></thead>
      <tbody id="aud-historial"><tr><td colspan="6">Cargando…</td></tr></tbody>
    </table>
  </div>
</div>

<script>
window.HAY_AUDIFONOS = <?php echo json_encode([
    'api' => hay_asset_url('php/audifonos_api.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/audifonos_control.js?v=20260820'), ENT_QUOTES, 'UTF-8'); ?>"></script>
