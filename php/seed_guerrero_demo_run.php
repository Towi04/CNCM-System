<?php
/**
 * Ejecutar seed demo Guerrero desde navegador.
 * URL correcta: .../php/seed_guerrero_demo_run.php
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../config.php';

$puede = function_exists('rbac_cap')
    ? rbac_cap('seed_datos')
    : in_array($_SESSION['rol'] ?? '', ['admin', 'gerente', 'supervisor'], true);

if (!isset($_SESSION['user_id']) || !$puede) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Solo supervisora, gerente o administrador. Inicie sesión y vuelva a intentar.</p>';
    echo '<p>Su rol actual: <strong>' . htmlspecialchars((string) ($_SESSION['rol'] ?? 'sin sesión'), ENT_QUOTES, 'UTF-8') . '</strong></p>';
    exit;
}

$self = htmlspecialchars((string) ($_SERVER['SCRIPT_NAME'] ?? 'seed_guerrero_demo_run.php'), ENT_QUOTES, 'UTF-8');
$confirmado = isset($_POST['confirm']) || (isset($_GET['confirm']) && (string) $_GET['confirm'] !== '');

if (!$confirmado) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Seed Guerrero demo</title></head>';
    echo '<body style="font-family:sans-serif;padding:24px;max-width:760px;line-height:1.45;">';
    echo '<h1>Cargar datos demo — plantel Guerrero</h1>';
    echo '<p>Genera un conjunto amplio de datos falsos <strong>solo en Guerrero</strong>:</p>';
    echo '<ul>';
    echo '<li>Aulas (A1–A5 + laboratorio)</li>';
    echo '<li>Profesores y personal demo</li>';
    echo '<li>Varios grupos de distintas especialidades con fechas de inicio de hace meses</li>';
    echo '<li>Alumnos con pagos, asistencias y calificaciones por fase cursada</li>';
    echo '<li>Preregistros demográficos diversos + entrevistas de asesor</li>';
    echo '<li>Cortes de caja, reporte semanal y rol de aulas publicado</li>';
    echo '</ul>';
    echo '<p>Contraseña de usuarios demo: <code>1234</code></p>';
    echo '<p>Es <em>idempotente</em>: si ya corre con la etiqueta <code>seed_guerrero_demo_2026</code>, no duplica grupos/preregistros.</p>';
    echo '<form method="post" action="' . $self . '" style="margin:20px 0;">';
    echo '<input type="hidden" name="confirm" value="1">';
    echo '<button type="submit" style="display:inline-block;padding:12px 20px;background:#0d47a1;color:#fff;border:0;border-radius:8px;font-size:1rem;cursor:pointer;">Ejecutar seed Guerrero</button>';
    echo '</form>';
    echo '<p style="color:#666;font-size:0.9rem;">Si el botón falla, abra directamente:<br><code>' . $self . '?confirm=1</code></p>';
    echo '</body></html>';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
ini_set('max_execution_time', '300');

$seedPath = __DIR__ . '/../scripts/seed_guerrero_demo.php';
if (!is_file($seedPath)) {
    http_response_code(500);
    echo '<pre style="white-space:pre-wrap;font-family:monospace;">ERROR: No se encontró el archivo del seed en el servidor:' . "\n"
        . htmlspecialchars($seedPath, ENT_QUOTES, 'UTF-8') . "\n\n"
        . 'Suba también scripts/seed_guerrero_demo.php</pre>';
    exit;
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ejecutando seed Guerrero</title></head>';
echo '<body style="font-family:monospace;padding:24px;background:#111;color:#d7ffd7;">';
echo '<h1 style="font-family:sans-serif;color:#fff;">Ejecutando seed Guerrero…</h1>';
echo '<pre style="white-space:pre-wrap;line-height:1.4;">';

try {
    ob_start();
    require $seedPath;
    $out = ob_get_clean();
    echo htmlspecialchars($out, ENT_QUOTES, 'UTF-8');
} catch (Throwable $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo 'ERROR:' . "\n\n"
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n"
        . htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8');
}

echo '</pre>';
echo '<p style="font-family:sans-serif;"><a href="' . $self . '" style="color:#9cf;">Volver</a></p>';
echo '</body></html>';
