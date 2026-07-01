<?php
require_once __DIR__ . '/../config.php';
if (!alumno_portal_puede_ver()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$banners = marketing_banners_listar($pdo, 'alumno');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-gift"></i> Promociones y concursos</h2>
    <p style="color:#666;">Ofertas vigentes de la escuela.</p>
  </div>

  <button type="button" class="secondary" style="margin-bottom:12px;" onclick="cargarSeccion('alumno_portal_inicio')">← Inicio</button>

  <?php if (empty($banners)): ?>
    <div class="welcome-card" style="padding:24px; text-align:center; color:#888;">
      No hay promociones activas por ahora. Vuelve pronto.
    </div>
  <?php else: ?>
    <div style="display:grid; gap:16px;">
      <?php foreach ($banners as $b): ?>
        <div class="welcome-card" style="padding:16px;">
          <h3 style="margin:0 0 8px;"><?php echo htmlspecialchars($b['titulo']); ?></h3>
          <?php if (!empty($b['imagen_url'])): ?>
            <img src="<?php echo htmlspecialchars($b['imagen_url']); ?>" alt="" style="max-width:100%; border-radius:8px; margin-bottom:8px;">
          <?php endif; ?>
          <?php if (!empty($b['enlace_url'])): ?>
            <a href="<?php echo htmlspecialchars($b['enlace_url']); ?>" target="_blank" rel="noopener" class="primary">Ver detalle</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
