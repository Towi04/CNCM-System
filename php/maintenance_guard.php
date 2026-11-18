<?php
/**
 * Si existe storage/maintenance.flag:
 * - Login / recuperación de contraseña siguen accesibles.
 * - Supervisores con sesión activa pasan.
 * - El resto ve pantalla 503 de actualización.
 * No aplica en CLI (cron, deploy, migraciones).
 */
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    return;
}

require_once __DIR__ . '/maintenance_helper.php';

if (!hay_maintenance_activo()) {
    return;
}

if (hay_maintenance_script_permitido()) {
    return;
}

if (hay_maintenance_es_supervisor_sesion()) {
    return;
}

http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
header('Retry-After: 120');
$loginUrl = 'index.php?login=1';
$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if (str_contains($script, '/php/') || str_contains($script, '/views/')) {
    $loginUrl = '../index.php?login=1';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mantenimiento — CNCM</title>
  <style>
    body{margin:0;font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;min-height:100vh;align-items:center;justify-content:center}
    main{max-width:28rem;padding:2rem;text-align:center}
    h1{font-size:1.35rem;margin:0 0 .75rem}
    p{margin:0 0 1rem;opacity:.85;line-height:1.5}
    a{color:#93c5fd}
  </style>
</head>
<body>
  <main>
    <h1>Sistema en actualización</h1>
    <p>Estamos aplicando mejoras. Los alumnos y el personal operativo no pueden entrar por ahora. Intente de nuevo en unos minutos.</p>
    <p><a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Si eres supervisión, inicia sesión aquí</a></p>
  </main>
</body>
</html>
<?php
exit;
