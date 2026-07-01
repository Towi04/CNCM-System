<?php
require_once __DIR__ . '/../config.php';
if (!rbac_cap('menu_gerente_hay') && !(function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() === 'gerente' && hay_eval_puede_gestionar())) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idUser = (int) ($_SESSION['user_id'] ?? 0);
$resumen = $idUser > 0 && function_exists('hay_eval_resumen_portal_colaborador')
    ? hay_eval_resumen_portal_colaborador($pdo, $idUser)
    : ['ok' => false];

$puedeGestionar = function_exists('hay_eval_puede_gestionar') && hay_eval_puede_gestionar();
$puedeMatrizEquipo = rbac_cap('menu_gerente_matriz');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_eval.css?v=20260608'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap hay-eval-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-layer-group"></i> Evaluación HAY — Gerente</h2>
    <p style="color:#666;">Su evaluación personal, seguimiento del equipo y herramientas de coordinación.</p>
  </div>

  <?php if (!empty($resumen['ok'])): ?>
  <div class="welcome-card" style="padding:16px; margin-bottom:16px; border-left:4px solid #1565c0;">
    <h3 style="margin:0 0 8px;">Mi resumen HAY</h3>
    <p style="margin:0;">
      Área: <strong><?php echo htmlspecialchars($resumen['area_nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong> ·
      Nivel: <strong><?php echo htmlspecialchars($resumen['nivel_actual'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></strong> ·
      Puntos: <strong><?php echo (int) ($resumen['puntos_actuales'] ?? 0); ?></strong> ·
      Capacitaciones pendientes: <strong><?php echo (int) ($resumen['capacitaciones_pendientes'] ?? 0); ?></strong>
    </p>
  </div>
  <?php endif; ?>

  <div class="hay-portal-cards">
    <button type="button" class="hay-portal-card" onclick="cargarSeccion('mi_evaluacion')">
      <i class="fas fa-user-check" style="color:#1565c0;"></i>
      <strong class="hay-portal-card__title">Mi evaluación HAY</strong>
      <span class="hay-portal-card__desc">Misma vista que usa el asesor: niveles, puntos y capacitaciones propias.</span>
    </button>

    <?php if ($puedeMatrizEquipo): ?>
    <button type="button" class="hay-portal-card" onclick="cargarSeccion('gerente_matriz_entrenamiento')">
      <i class="fas fa-graduation-cap" style="color:#2e7d32;"></i>
      <strong class="hay-portal-card__title">Matriz del equipo</strong>
      <span class="hay-portal-card__desc">Avance de capacitaciones de asesores del plantel.</span>
    </button>
    <?php endif; ?>

    <?php if ($puedeGestionar): ?>
    <button type="button" class="hay-portal-card" onclick="cargarSeccion('hay_evaluacion_admin')">
      <i class="fas fa-clipboard-check" style="color:#6a1b9a;"></i>
      <strong class="hay-portal-card__title">Evaluar personal</strong>
      <span class="hay-portal-card__desc">Vista administrativa estándar HAY (evaluaciones mensuales).</span>
    </button>
    <button type="button" class="hay-portal-card" onclick="cargarSeccion('hay_matriz_admin')">
      <i class="fas fa-tasks" style="color:#ef6c00;"></i>
      <strong class="hay-portal-card__title">Matriz admin (todas las áreas)</strong>
      <span class="hay-portal-card__desc">Misma pantalla que usa dirección; marcar capacitaciones por colaborador.</span>
    </button>
    <?php endif; ?>

    <button type="button" class="hay-portal-card" onclick="cargarSeccion('matriz_entrenamiento')">
      <i class="fas fa-book-open" style="color:#00838f;"></i>
      <strong class="hay-portal-card__title">Mi matriz personal</strong>
      <span class="hay-portal-card__desc">Vista individual idéntica a la del asesor.</span>
    </button>
  </div>

  <p style="color:#888; font-size:0.85rem; margin-top:20px;">
    Este portal agrupa accesos del gerente. Las pantallas marcadas como «estándar» reutilizan las mismas vistas del sistema; «Matriz del equipo» es la vista pensada para gerente de ventas.
  </p>
</div>
