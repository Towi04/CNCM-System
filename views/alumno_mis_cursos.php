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

$moodle = alumno_portal_cursos_moodle($pdo, $idAlumno);
$cursos = $moodle['cursos'] ?? [];
$baseUrl = $moodle['moodle_url'] ?? (defined('MOODLE_URL') ? MOODLE_URL : '');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-laptop"></i> Mis cursos Moodle</h2>
    <p style="color:#666;">Acceda a los cursos en los que está inscrito.</p>
  </div>

  <div class="catalog-toolbar">
    <button type="button" class="secondary" onclick="cargarSeccion('alumno_portal_inicio')">← Inicio</button>
    <?php if ($baseUrl !== ''): ?>
    <a href="<?php echo htmlspecialchars($baseUrl); ?>" target="_blank" rel="noopener" class="primary" style="padding:8px 14px; text-decoration:none; border-radius:8px;">
      Abrir Moodle
    </a>
    <?php endif; ?>
  </div>

  <?php if (empty($moodle['ok'])): ?>
    <div class="welcome-card" style="padding:16px;">
      <p style="color:#888;"><?php echo htmlspecialchars($moodle['message'] ?? 'No se encontraron cursos.'); ?></p>
      <?php if ($baseUrl !== ''): ?>
        <p>Puede entrar directamente a <a href="<?php echo htmlspecialchars($baseUrl); ?>" target="_blank" rel="noopener">Moodle</a> con su número de control.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div style="display:grid; gap:12px; margin-top:12px;">
      <?php foreach ($cursos as $c): ?>
        <div class="welcome-card" style="padding:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
          <div>
            <strong><?php echo htmlspecialchars($c['nombre'] ?? ''); ?></strong>
            <?php if (!empty($c['corto'])): ?>
              <br><small style="color:#666;"><?php echo htmlspecialchars($c['corto']); ?></small>
            <?php endif; ?>
          </div>
          <?php if (!empty($c['url'])): ?>
            <a href="<?php echo htmlspecialchars($c['url']); ?>" target="_blank" rel="noopener" class="primary" style="padding:8px 14px; text-decoration:none; border-radius:8px;">
              Entrar al curso
            </a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
