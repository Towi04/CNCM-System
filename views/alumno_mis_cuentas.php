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
$est = function_exists('cuenta_alumno_estado') ? cuenta_alumno_estado($pdo, $idAlumno, $idPlantel) : ['ok' => false];
$moodleUrl = defined('MOODLE_URL') ? rtrim((string) MOODLE_URL, '/') : '';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-cloud"></i> Mis cuentas digitales</h2>
    <p style="color:#666;">Correo institucional, portal HAY y Moodle.</p>
  </div>

  <button type="button" class="secondary" style="margin-bottom:12px;" onclick="cargarSeccion('alumno_portal_inicio')">← Inicio</button>

  <?php if (empty($est['ok'])): ?>
    <div class="alert"><?php echo htmlspecialchars($est['message'] ?? 'No hay información de cuentas.'); ?></div>
  <?php else: ?>
    <div style="display:grid; gap:12px;">
      <div class="welcome-card" style="padding:16px;">
        <h3 style="margin:0 0 8px;"><i class="fab fa-google"></i> Google Workspace</h3>
        <p><strong>Correo:</strong> <?php echo htmlspecialchars($est['email_institucional'] ?? '—'); ?></p>
        <p style="color:#666;"><?php echo htmlspecialchars($est['google']['mensaje'] ?? ''); ?></p>
        <p><span class="<?php echo !empty($est['google']['activo']) ? 'text-ok' : 'text-warn'; ?>">
          <?php echo !empty($est['google']['activo']) ? '● Activa' : '○ Pendiente o no encontrada'; ?>
        </span></p>
      </div>

      <div class="welcome-card" style="padding:16px;">
        <h3 style="margin:0 0 8px;"><i class="fas fa-user-circle"></i> Portal HAY</h3>
        <p><strong>Usuario:</strong> <?php echo htmlspecialchars($est['hay']['username'] ?? '—'); ?></p>
        <p style="color:#666;"><?php echo htmlspecialchars($est['hay']['mensaje'] ?? ''); ?></p>
        <button type="button" class="secondary" onclick="cargarSeccion('cambiar_password')">Cambiar contraseña HAY</button>
      </div>

      <div class="welcome-card" style="padding:16px;">
        <h3 style="margin:0 0 8px;"><i class="fas fa-graduation-cap"></i> Moodle</h3>
        <?php if (!empty($est['moodle']['username'])): ?>
          <p><strong>Usuario:</strong> <?php echo htmlspecialchars($est['moodle']['username']); ?></p>
        <?php endif; ?>
        <p style="color:#666;"><?php echo htmlspecialchars($est['moodle']['mensaje'] ?? ''); ?></p>
        <?php if ($moodleUrl !== ''): ?>
          <a href="<?php echo htmlspecialchars($moodleUrl); ?>" target="_blank" rel="noopener" class="primary" style="display:inline-block;padding:8px 14px;text-decoration:none;border-radius:8px;margin-top:8px;">
            Abrir Moodle
          </a>
          <button type="button" class="secondary" style="margin-left:8px;" onclick="cargarSeccion('alumno_mis_cursos')">Ver mis cursos</button>
        <?php endif; ?>
        <?php if (!empty($est['moodle']['cursos'])): ?>
          <ul style="margin:12px 0 0; padding-left:18px;">
            <?php foreach ($est['moodle']['cursos'] as $c): ?>
              <li><?php echo htmlspecialchars($c['fullname'] ?? $c['shortname'] ?? ''); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <p style="color:#888; font-size:0.85rem; margin-top:16px;">
      Si no puede acceder a Google o Moodle, use <strong>Mensajes</strong> para contactar a recepción o acuda al plantel.
    </p>
  <?php endif; ?>
</div>

<style>
.text-ok { color: #2e7d32; font-weight: 600; }
.text-warn { color: #ef6c00; font-weight: 600; }
</style>
