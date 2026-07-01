<?php
require_once __DIR__ . '/../config.php';
if (!alumno_portal_puede_ver()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idAlumno = alumno_portal_id_o_detener();
if ($idAlumno <= 0) {
    return;
}

$idPlantel = plantel_scope_id($pdo);
$calificaciones = calificaciones_alumno_por_fase($pdo, $idAlumno, $idPlantel);
$al = alumno_portal_fila($pdo, $idAlumno);
$nombre = $al ? trim(($al['nombres'] ?? '') . ' ' . ($al['apellido_paterno'] ?? '')) : '';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-star"></i> Mis calificaciones</h2>
    <?php if ($al): ?>
      <p style="color:#666;"><?php echo htmlspecialchars($nombre); ?> · <?php echo htmlspecialchars($al['numero_control'] ?? ''); ?></p>
    <?php endif; ?>
  </div>

  <button type="button" class="secondary" style="margin-bottom:12px;" onclick="cargarSeccion('alumno_portal_inicio')">← Inicio</button>

  <div class="catalog-table-wrap">
    <table class="catalog-table">
      <thead>
        <tr>
          <th>Especialidad</th>
          <th>Fase / parcial</th>
          <th>Grupo</th>
          <th>Promedio</th>
          <th>Estado</th>
          <th>Actualizado</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($calificaciones)): ?>
          <tr><td colspan="6" style="color:#888;">Sin calificaciones registradas aún.</td></tr>
        <?php else: foreach ($calificaciones as $c): ?>
          <tr>
            <td><?php echo htmlspecialchars($c['especialidad_nombre'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars(($c['clave_fase'] ?? '') . ' ' . ($c['nombre_fase'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($c['grupo_clave'] ?? '—'); ?></td>
            <td><strong><?php echo htmlspecialchars(number_format((float) ($c['promedio'] ?? 0), 1)); ?></strong></td>
            <td><?php echo (int) ($c['aprobado'] ?? 0) ? 'Aprobado' : 'No aprobado'; ?></td>
            <td><?php echo !empty($c['actualizado_en']) ? date('d/m/Y', strtotime($c['actualizado_en'])) : '—'; ?></td>
          </tr>
          <?php if (!empty($c['observaciones'])): ?>
          <tr><td colspan="6" style="font-size:0.88rem;color:#666;padding-left:24px;"><em><?php echo htmlspecialchars($c['observaciones']); ?></em></td></tr>
          <?php endif; ?>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
