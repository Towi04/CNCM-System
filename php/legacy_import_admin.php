<?php

/**
 * Importación legado → CNCM (acceso directo por URL, fuera del panel).
 * En el panel use views/legacy_import_admin.php vía cargarSeccion.
 */
require dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/auth_helpers.php';

header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<p>Debe iniciar sesión primero.</p>';
    exit;
}

if (!legacy_import_admin_puede()) {
    http_response_code(403);
    echo '<p>Solo supervisión puede ejecutar la importación del legado.</p>';
    exit;
}

$conn = legacy_import_legacy_connection();
$leg = $conn['ok'] ? $conn['pdo'] : null;
$ctx = legacy_import_admin_handle($pdo, $leg, $_POST);
$fases = legacy_import_admin_fases();
$legacyDb = defined('LEGACY_DB_NAME') ? LEGACY_DB_NAME : '?';
$dashUrl = hay_asset_url('dashboard.php');

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars(app_page_title('Importar legado'), ENT_QUOTES, 'UTF-8'); ?></title>
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
    .btn-warn { background: #b45309; color: #fff; margin-left: 8px; text-decoration: none; display: inline-block; }
    label { display: block; margin: 8px 0; }
    select, input[type=checkbox] { margin-right: 6px; }
    .hint { font-size: 13px; color: #666; }
  </style>
</head>
<body>
  <h1>Importar datos: <?php echo htmlspecialchars($legacyDb, ENT_QUOTES, 'UTF-8'); ?> → CNCM</h1>

  <?php if (!$conn['ok']): ?>
    <p class="err"><?php echo htmlspecialchars($conn['error'], ENT_QUOTES, 'UTF-8'); ?></p>
  <?php else: ?>
    <p class="ok">Conexión al legado: OK. Registros mapeados: <strong><?php echo (int) $ctx['mapCount']; ?></strong></p>
  <?php endif; ?>

  <p class="hint">No borra la base legado. Ejecute primero simulación, luego importación real fase por fase.</p>

  <div class="box">
    <form method="post">
      <label>
        Fase
        <select name="fase">
          <?php foreach ($fases as $f): ?>
            <option value="<?php echo htmlspecialchars($f['key'], ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><input type="checkbox" name="dry_run" value="1" checked> Solo simular (no escribe en CNCM)</label>
      <label><input type="checkbox" name="reset_map" value="1"> Reiniciar mapa antes (solo reimportación total)</label>
      <p style="margin-top:14px;">
        <button type="submit" class="btn btn-primary" <?php echo $leg ? '' : 'disabled'; ?>>Ejecutar</button>
        <a href="<?php echo htmlspecialchars($dashUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-warn">Volver al panel</a>
      </p>
    </form>
  </div>

  <?php if (!empty($ctx['error'])): ?>
    <p class="err"><?php echo htmlspecialchars($ctx['error'], ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <?php if (!empty($ctx['resultado'])): ?>
    <h2>Resultado</h2>
    <table>
      <tr><th>Fase</th><th>Insertados</th><th>Omitidos</th><th>Errores</th></tr>
      <?php foreach ($ctx['resultado'] as $nombre => $st): ?>
        <tr>
          <td><?php echo htmlspecialchars((string) $nombre, ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo (int) ($st['inserted'] ?? 0); ?></td>
          <td><?php echo (int) ($st['skipped'] ?? 0); ?></td>
          <td><?php echo (int) ($st['errors'] ?? 0); ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <?php if (!empty($ctx['logs'])): ?>
    <h2>Últimos mensajes</h2>
    <table>
      <tr><th>Hora</th><th>Fase</th><th>Nivel</th><th>Mensaje</th></tr>
      <?php foreach ($ctx['logs'] as $l): ?>
        <tr>
          <td><?php echo htmlspecialchars((string) ($l['creado_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) ($l['fase'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) ($l['nivel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) ($l['mensaje'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</body>
</html>
