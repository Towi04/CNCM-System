<?php
/**
 * Vista antigua «Cartas nómina» (solo marcas semanales de asesores).
 * Quedó reemplazada por Escuelas y visitas (quién fue, a qué escuela, cartas).
 */
require_once __DIR__ . '/../config.php';

$puedeEscuelas = function_exists('rbac_cap') && (
    rbac_cap('menu_gerente_escuelas') || rbac_cap('menu_reporte_escuelas') || rbac_cap('menu_gerente_cartas')
);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-envelope-open-text"></i> Cartas nómina (descontinuada)</h2>
  </div>
  <div class="catalog-alert" style="margin-top:12px;">
    Esta vista solo marcaba <strong>qué asesores</strong> salieron en la semana.
    Ahora use <strong>Escuelas y visitas</strong>: ahí registra la escuela, quién fue y las cartas entregadas.
    El <strong>Reporte escuelas</strong> concentra ese historial.
  </div>
  <div class="disc-actions" style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
    <?php if ($puedeEscuelas): ?>
      <button type="button" class="primary" onclick="cargarSeccion('gerente_escuelas')">
        <i class="fas fa-school"></i> Ir a Escuelas y visitas
      </button>
      <button type="button" class="secondary" onclick="cargarSeccion('reporte_escuelas')">
        <i class="fas fa-chart-bar"></i> Reporte escuelas
      </button>
    <?php else: ?>
      <p style="color:#666;">Sin permiso para escuelas. Consulte a supervisión.</p>
    <?php endif; ?>
  </div>
</div>
