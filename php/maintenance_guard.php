<?php
/**
 * Si existe storage/maintenance.flag, muestra página de mantenimiento (HTTP 503).
 * No aplica en CLI (cron, deploy, migraciones).
 */
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    return;
}

$hayRoot = defined('HAY_ROOT') ? HAY_ROOT : dirname(__DIR__);
$flag = $hayRoot . '/storage/maintenance.flag';
if (!is_file($flag)) {
    return;
}

http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
header('Retry-After: 120');
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
    p{margin:0;opacity:.85;line-height:1.5}
  </style>
</head>
<body>
  <main>
    <h1>Sistema en mantenimiento</h1>
    <p>Estamos aplicando una actualización. Intente de nuevo en unos minutos.</p>
  </main>
</body>
</html>
<?php
exit;
