<?php
require_once __DIR__ . '/../config.php';
if (!alumno_portal_puede_ver()) {
    echo '<div class="alert">Portal solo para alumnos.</div>';
    return;
}

$idAlumno = alumno_portal_id_o_detener();
if ($idAlumno <= 0) {
    return;
}

$idPlantel = plantel_scope_id($pdo);
$al = alumno_portal_fila($pdo, $idAlumno);
if (!$al) {
    echo '<div class="alert">Alumno no encontrado.</div>';
    return;
}

$banners = marketing_banners_listar($pdo, 'alumno');
$notifs = alumno_portal_notificaciones($pdo, $idAlumno, $idPlantel);
$grupos = alumno_portal_grupos_activos($pdo, $idAlumno);
$pagos = alumno_portal_resumen_pagos($pdo, $idAlumno);
$moodle = alumno_portal_cursos_moodle($pdo, $idAlumno);
$nombre = trim(($al['nombres'] ?? $al['nombre'] ?? '') . ' ' . ($al['apellido_paterno'] ?? $al['apellido'] ?? ''));
$nCursos = count($moodle['cursos'] ?? []);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <?php if (function_exists('alumno_portal_es_vista_simulada') && alumno_portal_es_vista_simulada()): ?>
  <div class="alert" style="background:#fff8e1;border:1px solid #f0c040;color:#5c4a00;margin-bottom:16px;">
    <i class="fas fa-eye"></i> Vista de capacitación — previsualizando a
    <strong><?php echo htmlspecialchars($nombre); ?></strong>
    (control <?php echo htmlspecialchars($al['numero_control'] ?? ''); ?>).
    <button type="button" class="secondary" id="sim-cambiar-alumno" style="margin-left:8px;">Cambiar alumno</button>
  </div>
  <?php endif; ?>
  <div class="catalog-header">
    <h2><i class="fas fa-home"></i> Bienvenido, <?php echo htmlspecialchars($nombre); ?></h2>
    <p style="color:#666;">
      Control <?php echo htmlspecialchars($al['numero_control'] ?? ''); ?>
      · <?php echo htmlspecialchars($al['plantel_nombre'] ?? $_SESSION['plantel_nombre'] ?? ''); ?>
    </p>
  </div>

  <?php if (!empty($banners)): ?>
  <div style="display:grid; gap:12px; margin-bottom:20px;">
    <?php foreach ($banners as $b): ?>
      <div class="welcome-card" style="padding:0; overflow:hidden;">
        <?php if (!empty($b['imagen_url'])): ?>
          <?php if (!empty($b['enlace_url'])): ?>
            <a href="<?php echo htmlspecialchars($b['enlace_url']); ?>" target="_blank" rel="noopener">
              <img src="<?php echo htmlspecialchars($b['imagen_url']); ?>" alt="<?php echo htmlspecialchars($b['texto_alt'] ?? $b['titulo']); ?>" style="width:100%; max-height:180px; object-fit:cover;">
            </a>
          <?php else: ?>
            <img src="<?php echo htmlspecialchars($b['imagen_url']); ?>" alt="<?php echo htmlspecialchars($b['texto_alt'] ?? $b['titulo']); ?>" style="width:100%; max-height:180px; object-fit:cover;">
          <?php endif; ?>
        <?php else: ?>
          <div style="padding:16px;">
            <strong><?php echo htmlspecialchars($b['titulo']); ?></strong>
            <?php if (!empty($b['enlace_url'])): ?>
              — <a href="<?php echo htmlspecialchars($b['enlace_url']); ?>" target="_blank" rel="noopener">Ver más</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:20px;">
    <?php if ($pagos['ok'] && ($pagos['adeudo'] ?? 0) > 0): ?>
    <div class="welcome-card" style="padding:12px; border-left:4px solid #c62828;">
      <div style="font-size:0.8rem;color:#666;">Adeudo</div>
      <strong><?php echo catalog_format_mxn($pagos['adeudo']); ?></strong>
    </div>
    <?php endif; ?>
    <?php if ($nCursos > 0): ?>
    <div class="welcome-card" style="padding:12px;">
      <div style="font-size:0.8rem;color:#666;">Cursos Moodle</div>
      <strong><?php echo (int) $nCursos; ?></strong>
    </div>
    <?php endif; ?>
    <?php if (!empty($grupos)): ?>
    <div class="welcome-card" style="padding:12px;">
      <div style="font-size:0.8rem;color:#666;">Grupos activos</div>
      <strong><?php echo count($grupos); ?></strong>
    </div>
    <?php endif; ?>
  </div>

  <div class="ap-portal-grid" style="margin-bottom:20px;">
    <button type="button" class="ap-card-btn" onclick="cargarSeccion('alumno_mis_calificaciones')">
      <i class="fas fa-star" style="color:#ffc107;"></i>
      <strong style="display:block;margin-top:6px;">Mis calificaciones</strong>
    </button>
    <button type="button" class="ap-card-btn" onclick="cargarSeccion('alumno_estado_cuenta','id=<?php echo (int) $idAlumno; ?>')">
      <i class="fas fa-file-invoice-dollar" style="color:#2e7d32;"></i>
      <strong style="display:block;margin-top:6px;">Mis pagos</strong>
    </button>
    <button type="button" class="ap-card-btn" onclick="cargarSeccion('alumno_mis_cursos')">
      <i class="fas fa-laptop" style="color:#1565c0;"></i>
      <strong style="display:block;margin-top:6px;">Mis cursos Moodle</strong>
    </button>
    <button type="button" class="ap-card-btn" onclick="cargarSeccion('alumno_chat')">
      <i class="fas fa-comments" style="color:#6a1b9a;"></i>
      <strong style="display:block;margin-top:6px;">Mensajes</strong>
    </button>
    <button type="button" class="ap-card-btn" onclick="cargarSeccion('alumno_promociones')">
      <i class="fas fa-gift" style="color:#ef6c00;"></i>
      <strong style="display:block;margin-top:6px;">Promociones</strong>
    </button>
    <button type="button" class="ap-card-btn" onclick="cargarSeccion('alumno_mi_perfil')">
      <i class="fas fa-id-card" style="color:#455a64;"></i>
      <strong style="display:block;margin-top:6px;">Mi perfil</strong>
    </button>
    <button type="button" class="ap-card-btn" onclick="cargarSeccion('alumno_mis_cuentas')">
      <i class="fas fa-cloud" style="color:#0277bd;"></i>
      <strong style="display:block;margin-top:6px;">Cuentas digitales</strong>
    </button>
  </div>

  <?php if (!empty($grupos)): ?>
  <div class="welcome-card" style="padding:16px; margin-bottom:16px;">
    <h3 style="margin:0 0 10px;"><i class="fas fa-users"></i> Mis grupos</h3>
    <ul style="margin:0; padding-left:18px;">
      <?php foreach ($grupos as $g): ?>
        <li>
          <strong><?php echo htmlspecialchars($g['clave'] ?? ''); ?></strong>
          — <?php echo htmlspecialchars($g['especialidad'] ?? ''); ?>
          <?php if (!empty($g['profesor'])): ?>
            · Prof. <?php echo htmlspecialchars(trim($g['profesor'])); ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="welcome-card" style="padding:0; overflow:hidden;">
    <h3 style="margin:0; padding:14px 16px; border-bottom:1px solid #eee;"><i class="fas fa-bell"></i> Avisos</h3>
    <?php if (empty($notifs)): ?>
      <p style="padding:16px; color:#888; margin:0;">No hay avisos nuevos.</p>
    <?php else: ?>
      <ul class="ap-notif-list">
        <?php foreach (array_slice($notifs, 0, 12) as $it): ?>
          <li class="ap-notif-item ap-notif-item--<?php echo htmlspecialchars($it['prioridad'] ?? 'media'); ?>">
            <strong><?php echo htmlspecialchars($it['titulo'] ?? ''); ?></strong>
            <p style="margin:4px 0 0; color:#555; font-size:0.92rem;"><?php echo htmlspecialchars($it['mensaje'] ?? ''); ?></p>
            <?php if (!empty($it['enlace'])): ?>
              <button type="button" class="secondary" style="margin-top:6px; font-size:0.85rem;"
                onclick="cargarSeccion('<?php echo htmlspecialchars(strtok($it['enlace'], '&')); ?>','<?php echo htmlspecialchars(str_contains($it['enlace'], '&') ? substr(strstr($it['enlace'], '&'), 1) : ''); ?>')">
                Ver detalle
              </button>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
<?php if (function_exists('alumno_portal_es_vista_simulada') && alumno_portal_es_vista_simulada()): ?>
<script>
document.getElementById('sim-cambiar-alumno')?.addEventListener('click', async () => {
  const fd = new FormData();
  fd.append('action', 'set_alumno_simulacion');
  fd.append('id_alumno', '0');
  await fetch(<?php echo json_encode(hay_asset_url('php/alumno_portal_api.php'), JSON_UNESCAPED_UNICODE); ?>, {
    method: 'POST',
    body: fd,
    headers: { 'X-Requested-With': 'fetch' },
  });
  if (typeof cargarSeccion === 'function') {
    cargarSeccion('alumno_portal_inicio');
  }
});
</script>
<?php endif; ?>
