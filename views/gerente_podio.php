<?php
require_once __DIR__ . '/../config.php';
if (!rbac_cap('menu_podio_ventas') && !gerente_puede_panel()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$scopePlantel = null;
if (!empty($_GET['plantel']) && (int) $_GET['plantel'] > 0 && rbac_cap('menu_gerente_dashboard')) {
    $scopePlantel = (int) $_GET['plantel'];
} elseif (!empty($_GET['solo_plantel'])) {
    $scopePlantel = plantel_scope_id($pdo);
}

$podio = gerente_podio_asesores($pdo, $scopePlantel);
$items = $podio['items'] ?? [];
$medallas = ['🥇', '🥈', '🥉'];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-trophy"></i> Podio de asesores</h2>
    <p style="color:#666;">
      Semana <?php echo htmlspecialchars($podio['desde'] ?? ''); ?> — <?php echo htmlspecialchars($podio['hasta'] ?? ''); ?>.
      Puntos: entrevista×2 + pre-registro×3 + inscrito×5.
    </p>
  </div>

  <?php if (rbac_cap('menu_gerente_dashboard')): ?>
  <div class="catalog-toolbar">
    <button type="button" class="<?php echo $scopePlantel ? '' : 'primary'; ?>" onclick="cargarSeccion('gerente_podio')">Todos los planteles</button>
    <button type="button" class="<?php echo $scopePlantel ? 'primary' : ''; ?>" onclick="cargarSeccion('gerente_podio','solo_plantel=1')">Solo mi plantel</button>
  </div>
  <?php endif; ?>

  <?php if (!empty($podio['error'])): ?>
    <p class="catalog-alert catalog-alert--error"><?php echo htmlspecialchars($podio['error']); ?></p>
  <?php elseif (empty($items)): ?>
    <p style="color:#888; padding:20px;">Sin actividad en el periodo.</p>
  <?php else: ?>
    <div style="display:grid; gap:12px; margin-top:16px;">
      <?php foreach ($items as $i => $row): ?>
        <div class="welcome-card" style="display:flex; align-items:center; gap:16px; padding:14px 18px; <?php echo $i < 3 ? 'border-left:4px solid #ffc107;' : ''; ?>">
          <div style="font-size:1.8rem; width:40px; text-align:center;">
            <?php echo $medallas[$i] ?? (string) ($i + 1); ?>
          </div>
          <div style="flex:1;">
            <strong><?php echo htmlspecialchars(trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? ''))); ?></strong>
            <span style="color:#666;"> · <?php echo htmlspecialchars($row['plantel'] ?? ''); ?></span>
            <div style="font-size:0.9rem; color:#555; margin-top:4px;">
              <?php echo (int) ($row['entrevistas'] ?? 0); ?> entrevistas ·
              <?php echo (int) ($row['preregistros'] ?? 0); ?> pre-registros ·
              <?php echo (int) ($row['inscritos'] ?? 0); ?> inscritos
            </div>
          </div>
          <div style="font-size:1.4rem; font-weight:700; color:#1565c0;">
            <?php echo (int) ($row['puntos'] ?? 0); ?> pts
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
