<?php

/**
 * Importación legado → HAY desde el navegador (solo administración).
 * URL: php/legacy_import_admin.php
 */
require dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/legacy_import_helper.php';

header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<p>Debe iniciar sesión en HAY primero.</p>';
    exit;
}

$rolReal = function_exists('rbac_rol_real') ? rbac_rol_real() : ($_SESSION['rol'] ?? '');
if (!in_array($rolReal, ['supervisor', 'gerente'], true)) {
    http_response_code(403);
    echo '<p>Solo supervisión o gerencia puede ejecutar la importación del legado.</p>';
    exit;
}

$leg = legacy_import_pdo_legacy();
$hayOk = true;
$legOk = $leg !== null;

$fases = [
    'all' => 'Todo (orden recomendado)',
    'planteles' => 'Planteles ← sucursales',
    'especialidades' => 'Especialidades',
    'usuarios' => 'Usuarios ← users',
    'productos' => 'Productos',
    'grupos' => 'Grupos',
    'grupos_remap_esp' => 'Grupos: aplicar especialidad (equivalencias)',
    'preregistros' => 'Pre-registros',
    'alumnos' => 'Alumnos inscritos',
    'alumno_grupos' => 'Alumnos en grupos',
    'alumno_especialidades' => 'Alumnos ↔ especialidades',
    'pagos' => 'Pagos / abonos históricos',
];

$resultado = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $legOk) {
    $fase = trim($_POST['fase'] ?? 'all');
    $dryRun = !empty($_POST['dry_run']);
    if (isset($_POST['reset_map']) && !$dryRun) {
        legacy_import_reset_map($pdo);
    }
    try {
        @set_time_limit(0);
        legacy_import_ensure_schema($pdo);
        $resultado = legacy_import_run($pdo, $leg, $fase, $dryRun);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$logs = [];
try {
    legacy_import_ensure_schema($pdo);
    $logs = $pdo->query(
        'SELECT fase, nivel, mensaje, creado_en FROM hay_legacy_import_log
         ORDER BY id_log DESC LIMIT 30'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $logs = [];
}

$mapCount = 0;
try {
    $mapCount = (int) $pdo->query('SELECT COUNT(*) FROM hay_legacy_map')->fetchColumn();
} catch (Throwable $e) {
    $mapCount = 0;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Importar legado → HAY</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 720px; margin: 24px auto; padding: 0 16px; color: #222; }
    h1 { font-size: 1.35rem; }
    .ok { color: #2e7d32; }
    .err { color: #c62828; background: #ffebee; padding: 12px; border-radius: 8px; }
    .box { background: #f5f7fa; padding: 16px; border-radius: 10px; margin: 16px 0; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
    .btn { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; }
    .btn-primary { background: #11458b; color: #fff; }
    .btn-warn { background: #b45309; color: #fff; margin-left: 8px; }
    label { display: block; margin: 8px 0; }
    select, input[type=checkbox] { margin-right: 6px; }
    .hint { font-size: 13px; color: #666; }
  </style>
</head>
<body>
  <h1>Importar datos: <?= htmlspecialchars(defined('LEGACY_DB_NAME') ? LEGACY_DB_NAME : '?', ENT_QUOTES, 'UTF-8') ?> → HAY</h1>

  <?php if (!$legOk): ?>
    <p class="err">No hay conexión al legado. Revise LEGACY_DB_* en <code>config.local.php</code>.</p>
  <?php else: ?>
    <p class="ok">Conexión al legado: OK. Registros mapeados en HAY: <strong><?= (int) $mapCount ?></strong></p>
  <?php endif; ?>

  <p class="hint">
    No borra la base legado. Copia datos a <code>cncmedum_hay_system</code> usando el modelo HAY.
    Ejecute primero <strong>simulación</strong>, luego importación real fase por fase si el hosting corta por tiempo.
  </p>

  <div class="box">
    <form method="post">
      <label>
        Fase
        <select name="fase">
          <?php foreach ($fases as $k => $label): ?>
            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><input type="checkbox" name="dry_run" value="1" checked> Solo simular (no escribe en HAY)</label>
      <label><input type="checkbox" name="reset_map" value="1"> Reiniciar mapa antes (solo si va a reimportar todo)</label>
      <p style="margin-top:14px;">
        <button type="submit" class="btn btn-primary">Ejecutar</button>
        <a href="../dashboard.php" class="btn btn-warn" style="text-decoration:none;display:inline-block;">Volver al panel</a>
      </p>
    </form>
  </div>

  <?php if ($error): ?>
    <p class="err"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <?php if ($resultado): ?>
    <h2>Resultado</h2>
    <table>
      <tr><th>Fase</th><th>Insertados</th><th>Omitidos</th><th>Errores</th></tr>
      <?php foreach ($resultado as $nombre => $st): ?>
        <tr>
          <td><?= htmlspecialchars($nombre) ?></td>
          <td><?= (int) $st['inserted'] ?></td>
          <td><?= (int) $st['skipped'] ?></td>
          <td><?= (int) $st['errors'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <?php if ($logs): ?>
    <h2>Últimos mensajes</h2>
    <table>
      <tr><th>Hora</th><th>Fase</th><th>Nivel</th><th>Mensaje</th></tr>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= htmlspecialchars($l['creado_en'] ?? '') ?></td>
          <td><?= htmlspecialchars($l['fase'] ?? '') ?></td>
          <td><?= htmlspecialchars($l['nivel'] ?? '') ?></td>
          <td><?= htmlspecialchars($l['mensaje'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</body>
</html>
