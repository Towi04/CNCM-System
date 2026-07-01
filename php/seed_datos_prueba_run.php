<?php
/**
 * Ejecutar seed de datos de prueba desde el navegador.
 * URL: .../hay_system/php/seed_datos_prueba_run.php?confirm=1
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !(function_exists('rbac_cap') ? rbac_cap('seed_datos') : in_array($_SESSION['rol'] ?? '', ['admin', 'gerente', 'supervisor'], true))) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Solo supervisora, gerente o administrador. Inicie sesión y vuelva a intentar.</p>';
    echo '<p>Su rol actual: <strong>' . htmlspecialchars($_SESSION['rol'] ?? 'sin sesión') . '</strong></p>';
    exit;
}

if (empty($_GET['confirm'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Seed demo</title></head><body style="font-family:sans-serif;padding:24px;max-width:640px;">';
    echo '<h1>Cargar datos de prueba</h1>';
    echo '<p>Crea usuarios, profesores, 3 grupos ING y 5 alumnos por plantel (Guerrero, Fuentes, Salamanca, Celaya).</p>';
    echo '<p><strong>Contraseña de todos:</strong> 1234</p>';
    echo '<p><a href="?confirm=1" style="display:inline-block;padding:12px 20px;background:#c62828;color:#fff;text-decoration:none;border-radius:8px;">Ejecutar ahora</a></p>';
    echo '<p style="color:#666;font-size:0.9rem;">Lista completa en <code>sql/SEED_DATOS_PRUEBA.md</code></p>';
    echo '<p><a href="seed_datos_operativos_run.php" style="color:#1565c0;">Paso 2: cargar pagos, asistencias y calificaciones demo →</a></p>';
    echo '</body></html>';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

try {
  ob_start();
  require __DIR__ . '/../scripts/seed_datos_prueba.php';
  echo ob_get_clean();
} catch (Throwable $e) {
  if (ob_get_level()) {
    ob_end_clean();
  }
  http_response_code(500);
  echo "ERROR al ejecutar seed:\n\n";
  echo $e->getMessage() . "\n\n";
  echo "Archivo: " . $e->getFile() . ':' . $e->getLine() . "\n\n";
  echo $e->getTraceAsString();
}
