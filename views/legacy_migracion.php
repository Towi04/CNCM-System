<?php
require_once __DIR__ . '/../config.php';
if (!legacy_migracion_puede()) {
    echo '<div class="alert">Solo supervisión, gerencia o dirección puede usar el asistente de migración.</div>';
    return;
}
legacy_import_ensure_schema($pdo);
$apiUrl = hay_asset_url('php/legacy_migracion_api.php');
$mapeoUrl = hay_asset_url('php/legacy_mapeo_api.php');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/legacy_mapeo.css?v=20260703'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap legacy-mig-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-database"></i> Migración legado → Portal CNCM</h2>
    <p style="color:#666; margin:0; max-width:820px;">
      Importe por fases con <strong>previsualización</strong> antes de aplicar. Los planteles y especialidades del nuevo sistema se respetan;
      usted define equivalencias. Los grupos reciben <strong>clave CNCM nueva</strong> y la clave anterior queda en <code>clave_anterior</code>.
    </p>
  </div>

  <div id="legacy-mig-estado" class="legacy-stats legacy-mig-estado">Cargando estado…</div>

  <div class="legacy-mig-layout">
    <nav class="legacy-mig-fases" id="legacy-mig-fases" aria-label="Fases de migración"></nav>

    <section class="legacy-mig-panel">
      <div id="legacy-mig-panel-head" class="legacy-mig-panel-head"></div>
      <div id="legacy-mig-resumen" class="legacy-mig-resumen" hidden></div>
      <div id="legacy-mig-advertencias" class="legacy-mig-advertencias" hidden></div>

      <div id="legacy-mig-equiv-wrap" class="legacy-mig-equiv" hidden>
        <p style="color:#666;">Configure equivalencias antes de importar alumnos, grupos o pagos.</p>
        <div class="legacy-mig-equiv-actions">
          <button type="button" class="primary" data-seccion="legacy_mapeo"><i class="fas fa-building"></i> Planteles y especialidades</button>
          <button type="button" class="primary" data-seccion="legacy_mapeo_grupos"><i class="fas fa-layer-group"></i> Grupos / especialidad</button>
        </div>
      </div>

      <div class="legacy-mig-toolbar">
        <button type="button" class="secondary" id="legacy-mig-btn-preview"><i class="fas fa-search"></i> Previsualizar</button>
        <button type="button" class="primary" id="legacy-mig-btn-aplicar" disabled><i class="fas fa-check"></i> Aplicar esta fase</button>
      </div>

      <div class="catalog-table-wrap hay-dt-panel">
        <table class="catalog-table legacy-mapeo-table" id="legacy-mig-tabla">
          <thead>
            <tr>
              <th>Acción</th>
              <th>Legado</th>
              <th>Destino CNCM</th>
              <th>Detalle</th>
            </tr>
          </thead>
          <tbody id="legacy-mig-tbody">
            <tr><td colspan="4" style="color:#888;">Seleccione una fase y pulse Previsualizar.</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>

<script>
window.HAY_LEGACY_MIG = <?php echo json_encode([
    'api' => $apiUrl,
    'mapeoApi' => $mapeoUrl,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/legacy_migracion.js?v=20260703'), ENT_QUOTES, 'UTF-8'); ?>"></script>
