<?php
require_once __DIR__ . '/_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert">Sesión no válida.</div>';
    return;
}
$puedeMiEval = function_exists('rbac_cap') && rbac_cap('menu_mi_evaluacion');
if (!$puedeMiEval && function_exists('rbac_usuario_en_roles')) {
    $puedeMiEval = rbac_usuario_en_roles(['asesor', 'gerente', 'profesor', 'admin', 'supervisor', 'director', 'coordinador', 'alumno']);
}
if (!$puedeMiEval) {
    echo '<div class="alert">No tiene permiso para ver su evaluación.</div>';
    return;
}

$idUser = (int) $_SESSION['user_id'];
$resumen = hay_eval_resumen_portal_colaborador($pdo, $idUser, (int) ($_GET['id_area'] ?? 0) ?: null);
$areasUsuario = function_exists('hay_eval_areas_usuario') ? hay_eval_areas_usuario($pdo, $idUser) : [];
$idArea = !empty($resumen['ok']) ? (int) $resumen['id_area'] : 0;
$periodos = $resumen['periodos'] ?? [];
$matriz = $resumen['matriz'] ?? ['capacitaciones' => []];
$sueldo = $idArea ? hay_eval_sueldo_sugerido_usuario($pdo, $idUser, $idArea) : null;
$nombre = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
?>
<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/hay_eval.css">

<div class="result-container hay-eval-wrap">
  <div class="result-header">
    <h2><i class="fas fa-user-check"></i> Mi evaluación HAY</h2>
  </div>

  <?php if (empty($resumen['ok'])): ?>
  <div class="welcome-card">
    <p style="color:#666;"><?php echo htmlspecialchars($resumen['message'] ?? 'Sin área HAY asignada.', ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($rol === 'asesor'): ?>
    <p style="color:#888; font-size:0.9rem;">Si acaba de activarse el módulo, cierre sesión y vuelva a entrar, o pida a coordinación que publique la evaluación del área <strong>Asesor de ventas</strong>.</p>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <?php if (count($areasUsuario) > 1): ?>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin:12px 0;">
    <?php foreach ($areasUsuario as $ar): ?>
    <button type="button" class="<?php echo (int) $ar['id_area'] === $idArea ? 'primary' : 'secondary'; ?>"
      onclick="cargarSeccion('mi_evaluacion','id_area=<?php echo (int) $ar['id_area']; ?>')">
      <?php echo htmlspecialchars($ar['nombre']); ?>
    </button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <p style="color:#666;">Hola, <?php echo $nombre; ?>. Área: <strong><?php echo htmlspecialchars($resumen['area_nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></p>

  <div class="hay-portal-resumen" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; margin:16px 0;">
    <div class="welcome-card" style="margin:0;">
      <p style="margin:0; font-size:0.85rem; color:#666;">Puntos (última evaluación cerrada)</p>
      <p style="margin:4px 0 0; font-size:1.6rem; font-weight:700;"><?php echo (int) ($resumen['puntos_actuales'] ?? 0); ?></p>
    </div>
    <div class="welcome-card" style="margin:0;">
      <p style="margin:0; font-size:0.85rem; color:#666;">Nivel actual</p>
      <p style="margin:4px 0 0; font-size:1.2rem; font-weight:700;"><?php echo htmlspecialchars($resumen['nivel_actual'] ?? 'Sin evaluar aún', ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <?php if (!empty($resumen['siguiente_nivel'])): ?>
    <div class="welcome-card" style="margin:0; border-left:4px solid #1565c0;">
      <p style="margin:0; font-size:0.85rem; color:#666;">Para subir a <?php echo htmlspecialchars($resumen['siguiente_nivel']['nombre_display'], ENT_QUOTES, 'UTF-8'); ?></p>
      <p style="margin:4px 0 0; font-weight:600;">Faltan <?php echo (int) ($resumen['puntos_faltan'] ?? 0); ?> puntos</p>
      <p style="margin:4px 0 0; font-size:0.82rem; color:#555;">Meta: <?php echo (int) $resumen['siguiente_nivel']['puntos_min']; ?>–<?php echo (int) $resumen['siguiente_nivel']['puntos_max']; ?> pts</p>
    </div>
    <?php endif; ?>
    <div class="welcome-card" style="margin:0;">
      <p style="margin:0; font-size:0.85rem; color:#666;">Capacitaciones obligatorias pendientes</p>
      <p style="margin:4px 0 0; font-size:1.2rem; font-weight:700;"><?php echo (int) ($resumen['capacitaciones_pendientes'] ?? 0); ?></p>
    </div>
  </div>

  <?php if ($sueldo !== null && $sueldo > 0): ?>
  <p><strong>Sueldo base sugerido (último nivel):</strong> <?php echo catalog_format_mxn($sueldo); ?></p>
  <?php endif; ?>

  <h3 style="margin-top:20px;">Bande de niveles (referencia)</h3>
  <table class="display" style="width:100%; max-width:640px;">
    <thead><tr><th>Nivel</th><th>Rango de puntos</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($resumen['niveles'] ?? [] as $nv): ?>
      <?php
        $esActual = ($resumen['nivel_numero'] ?? 0) === (int) $nv['numero'];
        $esMeta = !empty($resumen['siguiente_nivel']['numero']) && (int) $resumen['siguiente_nivel']['numero'] === (int) $nv['numero'];
      ?>
      <tr<?php echo $esActual ? ' style="background:#e8f5e9;"' : ($esMeta ? ' style="background:#e3f2fd;"' : ''); ?>>
        <td><?php echo htmlspecialchars($nv['nombre_display'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo (int) $nv['puntos_min']; ?> – <?php echo (int) $nv['puntos_max']; ?></td>
        <td><?php
          if ($esActual) {
              echo 'Tu nivel';
          } elseif ($esMeta) {
              echo 'Siguiente meta';
          }
        ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h3 style="margin-top:24px;">Qué puede mejorar</h3>
  <ul style="color:#444; line-height:1.6;">
    <?php if (empty($periodos)): ?>
    <li>Aún no tiene una evaluación mensual <strong>cerrada</strong>; su jefe la registrará en <em>Evaluar personal</em>.</li>
    <?php endif; ?>
    <?php if (!empty($resumen['siguiente_nivel'])): ?>
    <li>Incrementar su puntaje en los rubros de la evaluación para alcanzar <?php echo htmlspecialchars($resumen['siguiente_nivel']['nombre_display'], ENT_QUOTES, 'UTF-8'); ?>.</li>
    <?php endif; ?>
    <?php if ((int) ($resumen['capacitaciones_pendientes'] ?? 0) > 0): ?>
    <li>Completar <?php echo (int) $resumen['capacitaciones_pendientes']; ?> capacitación(es) obligatoria(s) de su matriz de entrenamiento.</li>
    <?php elseif (!empty($matriz['capacitaciones'])): ?>
    <li>Mantener al día su matriz de capacitación (obligatorias y al menos una extra al mes).</li>
    <?php else: ?>
    <li>Coordinación puede definir capacitaciones en la matriz de su área.</li>
    <?php endif; ?>
  </ul>

  <h3 style="margin-top:20px;">Historial de evaluaciones</h3>
  <?php if (!$periodos): ?>
  <p style="color:#888;">Aún no hay evaluaciones cerradas.</p>
  <?php else: ?>
  <table class="display" style="width:100%;">
    <thead><tr><th>Periodo</th><th>Puntos</th><th>Nivel</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($periodos as $p): ?>
      <tr>
        <td><?php echo (int) $p['mes']; ?>/<?php echo (int) $p['anio']; ?></td>
        <td><?php echo (int) $p['puntos_total']; ?></td>
        <td><?php echo htmlspecialchars($p['nivel_nombre'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
        <td>
          <button type="button" class="secondary" onclick="cargarSeccion('hay_evaluacion_form','id_eval=<?php echo (int) $p['id_eval']; ?>')">Ver detalle</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <p style="margin-top:20px;">
    <button type="button" class="btn-guardar" onclick="cargarSeccion('matriz_entrenamiento'<?php echo $idArea ? ',\'id_area=' . $idArea . '\'' : ''; ?>)">Ver matriz de entrenamiento</button>
    <?php if ($rol !== 'asesor'): ?>
    <button type="button" class="secondary" onclick="cargarSeccion('examen_disc')" style="margin-left:8px;">Examen DISC</button>
    <?php endif; ?>
  </p>
  <?php endif; ?>
</div>
