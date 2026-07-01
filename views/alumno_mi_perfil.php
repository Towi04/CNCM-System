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
$al = alumno_portal_fila($pdo, $idAlumno);
if (!$al) {
    echo '<div class="alert">Alumno no encontrado.</div>';
    return;
}

$grupos = alumno_portal_grupos_activos($pdo, $idAlumno);
$pagos = alumno_portal_resumen_pagos($pdo, $idAlumno);
$fotoUrl = function_exists('alumno_foto_public_url') ? alumno_foto_public_url($al['foto'] ?? null) : null;
$nombre = trim(($al['nombres'] ?? $al['nombre'] ?? '') . ' ' . ($al['apellido_paterno'] ?? $al['apellido'] ?? ''));
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-id-card"></i> Mi perfil</h2>
  </div>

  <button type="button" class="secondary" style="margin-bottom:12px;" onclick="cargarSeccion('alumno_portal_inicio')">← Inicio</button>

  <div class="welcome-card" style="padding:20px; display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
    <?php if ($fotoUrl): ?>
      <img src="<?php echo htmlspecialchars($fotoUrl); ?>" alt="" style="width:100px;height:100px;object-fit:cover;border-radius:12px;">
    <?php else: ?>
      <div style="width:100px;height:100px;border-radius:12px;background:#e0e0e0;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#666;">
        <?php echo htmlspecialchars(mb_strtoupper(mb_substr($nombre, 0, 1))); ?>
      </div>
    <?php endif; ?>
    <div style="flex:1; min-width:200px;">
      <h3 style="margin:0 0 8px;"><?php echo htmlspecialchars($nombre); ?></h3>
      <p style="margin:0 0 4px;"><strong>No. control:</strong> <?php echo htmlspecialchars($al['numero_control'] ?? '—'); ?></p>
      <p style="margin:0 0 4px;"><strong>Plantel:</strong> <?php echo htmlspecialchars($al['plantel_nombre'] ?? ''); ?></p>
      <?php if (!empty($al['email'])): ?>
        <p style="margin:0 0 4px;"><strong>Correo:</strong> <?php echo htmlspecialchars($al['email']); ?></p>
      <?php endif; ?>
      <?php if (!empty($al['telefono'])): ?>
        <p style="margin:0;"><strong>Teléfono:</strong> <?php echo htmlspecialchars($al['telefono']); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($grupos)): ?>
  <div class="welcome-card" style="margin-top:16px; padding:16px;">
    <h3 style="margin:0 0 10px;">Grupos actuales</h3>
    <ul><?php foreach ($grupos as $g): ?>
      <li><?php echo htmlspecialchars($g['clave'] ?? ''); ?> — <?php echo htmlspecialchars($g['especialidad'] ?? ''); ?></li>
    <?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <div class="welcome-card" style="margin-top:16px; padding:16px;">
    <h3 style="margin:0 0 10px;">Resumen de pagos</h3>
    <?php if (!empty($pagos['ok'])): ?>
      <p>Adeudo colegiaturas: <strong><?php echo catalog_format_mxn($pagos['adeudo'] ?? 0); ?></strong></p>
      <p>Total pagado: <?php echo catalog_format_mxn($pagos['pagado'] ?? 0); ?></p>
      <button type="button" class="primary" onclick="cargarSeccion('alumno_estado_cuenta','id=<?php echo (int) $idAlumno; ?>')">Ver estado de cuenta completo</button>
    <?php else: ?>
      <p style="color:#888;">No hay información de pagos disponible.</p>
    <?php endif; ?>
  </div>

  <div class="welcome-card" style="margin-top:16px; padding:16px;">
    <h3 style="margin:0 0 10px;">Acuerdo escolar</h3>
    <?php
    $firmaAcuerdo = function_exists('acuerdo_ultima_aceptacion_alumno')
        ? acuerdo_ultima_aceptacion_alumno($pdo, $idAlumno)
        : null;
    if ($firmaAcuerdo):
        $fechaFirma = $firmaAcuerdo['fecha_aceptacion'] ?? '';
        $lblVer = $firmaAcuerdo['version_label'] ?? '';
    ?>
      <p style="margin:0 0 6px;">
        <i class="fas fa-check-circle" style="color:#2e7d32;"></i>
        Aceptó la versión <strong><?php echo htmlspecialchars($lblVer); ?></strong>
      </p>
      <p style="margin:0 0 10px; color:#666; font-size:0.9rem;">
        Fecha de aceptación: <?php echo $fechaFirma ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $fechaFirma))) : '—'; ?>
      </p>
      <?php if (function_exists('alumno_debe_aceptar_acuerdo') && alumno_debe_aceptar_acuerdo($pdo, (int) $_SESSION['user_id'])): ?>
        <button type="button" class="primary" onclick="cargarSeccion('alumno_acuerdo_aceptar')">Aceptar nueva versión pendiente</button>
      <?php endif; ?>
    <?php else: ?>
      <p style="color:#666;margin:0 0 10px;">Aún no consta aceptación del acuerdo escolar vigente.</p>
      <button type="button" class="primary" onclick="cargarSeccion('alumno_acuerdo_aceptar')">Ver y aceptar acuerdo</button>
    <?php endif; ?>
  </div>

  <div class="welcome-card" style="margin-top:16px; padding:16px;">
    <h3 style="margin:0 0 10px;">Mis gustos e intereses</h3>
    <?php
    $perfil = function_exists('alumno_perfil_obtener') ? alumno_perfil_obtener($pdo, $idAlumno) : null;
    if ($perfil && (int) ($perfil['perfil_completado'] ?? 0) === 1 && !empty($perfil['perfil_gustos'])):
    ?>
      <p style="margin:0 0 10px; white-space:pre-wrap;"><?php echo htmlspecialchars((string) $perfil['perfil_gustos'], ENT_QUOTES, 'UTF-8'); ?></p>
      <button type="button" class="secondary" onclick="cargarSeccion('alumno_perfil_gustos')">Actualizar gustos</button>
    <?php else: ?>
      <p style="color:#666;margin:0 0 10px;">Ayuda al Tutor IA a usar ejemplos que te resulten más familiares.</p>
      <button type="button" class="primary" onclick="cargarSeccion('alumno_perfil_gustos')">Completar mi perfil de gustos</button>
    <?php endif; ?>
  </div>

  <div class="welcome-card" style="margin-top:16px; padding:16px;">
    <h3 style="margin:0 0 10px;">Cuentas digitales</h3>
    <p style="color:#666;margin:0 0 10px;">Correo Google, usuario Moodle y acceso al portal.</p>
    <button type="button" class="primary" onclick="cargarSeccion('alumno_mis_cuentas')">Ver mis cuentas</button>
  </div>

  <p style="color:#888; font-size:0.85rem; margin-top:16px;">
    Para cambiar contraseña use el menú de usuario → Cambiar contraseña.
  </p>
</div>
