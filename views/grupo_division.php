<?php
require_once __DIR__ . '/../config.php';
rbac_guard_seccion('grupo_division');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<style>
.gd-layout{display:grid;grid-template-columns:minmax(260px,380px) 1fr;gap:18px}.gd-card{background:#fff;border:1px solid #ddd;border-radius:10px;padding:18px}.gd-card h3{margin-top:0}.gd-grupo{display:flex;align-items:center;justify-content:space-between;width:100%;padding:10px;margin:5px 0;text-align:left}.gd-grupo--recomendado{border-left:5px solid #f57c00}.gd-borrador{padding:8px;width:100%;margin:4px 0;text-align:left}.gd-columnas{display:grid;grid-template-columns:1fr 1fr;gap:14px}.gd-col{border:1px solid #ddd;border-radius:8px;padding:10px;min-height:180px}.gd-alumno{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px;border-bottom:1px solid #eee}.gd-alumno button{padding:4px 8px}.gd-msg{padding:10px;border-radius:6px;margin-bottom:12px}.gd-msg--ok{background:#d4edda}.gd-msg--error{background:#f8d7da}.gd-warning{background:#fff3cd;padding:10px;border-radius:6px}@media(max-width:900px){.gd-layout,.gd-columnas{grid-template-columns:1fr}}
</style>

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-code-branch"></i> División de grupos por edad</h2>
    <p>La propuesta coloca primero a los alumnos más jóvenes en el grupo original y crea un grupo nuevo con los mayores. Puede ajustar ambas listas antes de confirmar.</p>
  </div>
  <div id="gd-msg" hidden></div>

  <div class="gd-layout">
    <aside>
      <section class="gd-card">
        <h3>Grupos</h3>
        <p><small>Se recomienda dividir cuando hay más de <?php echo GRUPO_DIVISION_UMBRAL; ?> alumnos.</small></p>
        <div id="gd-grupos">Cargando…</div>
      </section>
      <section class="gd-card" style="margin-top:14px">
        <h3>Borradores</h3>
        <div id="gd-borradores">Cargando…</div>
      </section>
    </aside>

    <section class="gd-card">
      <div id="gd-editor">
        <p>Seleccione un grupo para crear una propuesta o abra un borrador.</p>
      </div>
    </section>
  </div>
</div>

<script>
window.HAY_GRUPO_DIVISION = <?php echo json_encode([
    'api' => hay_asset_url('php/grupo_division_api.php'),
    'umbral' => GRUPO_DIVISION_UMBRAL,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/grupo_division.js?v=20260820'), ENT_QUOTES, 'UTF-8'); ?>"></script>
