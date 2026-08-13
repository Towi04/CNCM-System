<?php
require_once __DIR__ . '/../config.php';

if (!function_exists('rbac_cap') || !rbac_cap('menu_riesgo_reporte')) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para ver reporte de riesgo académico.</div>';
    return;
}

$items = grupo_avance_reporte_riesgo_plantel($pdo, plantel_scope_id($pdo));
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-exclamation-triangle"></i> Reporte de riesgo académico</h2>
    <p style="color:#666; margin:0;">Alumnos marcados en riesgo y seguimiento registrado por coordinación.</p>
  </div>

  <div class="catalog-table-wrap">
    <table class="catalog-table">
      <thead>
        <tr>
          <th>Estado</th>
          <th>Alumno</th>
          <th>Grupo</th>
          <th>Parcial / calificación</th>
          <th>Atención</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($items === []): ?>
          <tr><td colspan="5" style="color:#888;">Sin casos de riesgo registrados.</td></tr>
        <?php else: ?>
          <?php foreach ($items as $r): ?>
            <?php $seg = $r['seguimiento'] ?? null; ?>
            <tr>
              <td>
                <?php if ($seg): ?>
                  <span style="color:#2e7d32;"><i class="fas fa-check-circle"></i> Atendido</span>
                <?php else: ?>
                  <span style="color:#c62828;"><i class="fas fa-exclamation-circle"></i> Pendiente</span>
                <?php endif; ?>
              </td>
              <td>
                <strong><?php echo htmlspecialchars($r['nombre_completo'] ?? ''); ?></strong><br>
                <small>#<?php echo htmlspecialchars($r['numero_control'] ?? ''); ?></small>
              </td>
              <td>
                <?php echo htmlspecialchars($r['grupo_clave'] ?? ''); ?><br>
                <small><?php echo htmlspecialchars($r['especialidad'] ?? ''); ?></small>
              </td>
              <td>
                <?php echo htmlspecialchars($r['clave_fase'] ?? $r['nombre_fase'] ?? '—'); ?><br>
                <small>
                  <?php if (($r['promedio'] ?? null) !== null): ?>
                    Prom. <?php echo htmlspecialchars((string) $r['promedio']); ?>
                    <?php echo (int) ($r['aprobado'] ?? 0) ? '✓' : '✗'; ?>
                  <?php else: ?>
                    Sin captura
                  <?php endif; ?>
                </small>
              </td>
              <td>
                <?php if ($seg): ?>
                  <strong><?php echo htmlspecialchars($seg['atendido_por_nombre'] ?? '—'); ?></strong>
                  <small><?php echo htmlspecialchars($seg['creado_en'] ?? ''); ?></small>
                  <div style="white-space:pre-wrap; margin-top:6px;"><?php echo htmlspecialchars($seg['nota'] ?? ''); ?></div>
                <?php else: ?>
                  <span style="color:#888;">Sin seguimiento registrado.</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
