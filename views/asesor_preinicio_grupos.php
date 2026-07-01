<?php

require_once __DIR__ . '/../config.php';

if (!grupo_preinicio_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}

?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/asesor_preinicio_grupos.css">

<div class="catalog-wrap apg-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-phone-volume"></i> Contacto pre-inicio</h2>
    <p style="color:#666; margin:0;">Grupos próximos a iniciar: confirme contacto con alumnos inscritos antes del primer día de clase.</p>
  </div>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Ventana (días)</label>
      <input type="number" id="apg-dias" value="21" min="7" max="90">
    </div>
    <button type="button" class="primary" id="apg-recargar">Actualizar</button>
  </div>

  <div id="apg-msg" class="catalog-alert" style="display:none; margin-bottom:12px;"></div>

  <div class="apg-layout">
    <div class="apg-grupos">
      <h3>Grupos</h3>
      <div id="apg-lista-grupos" class="apg-card-list"></div>
    </div>
    <div class="apg-alumnos">
      <h3 id="apg-titulo-alumnos">Seleccione un grupo</h3>
      <div id="apg-lista-alumnos"></div>
    </div>
  </div>
</div>

<script>
window.HAY_ASESOR_PREINICIO = <?php echo json_encode([
    'api' => hay_asset_url('php/grupo_preinicio_api.php'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/asesor_preinicio_grupos.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>if (window.hayAsesorPreinicioInit) window.hayAsesorPreinicioInit();</script>
