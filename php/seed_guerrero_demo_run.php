<?php
/**
 * Ejecutar seed demo Guerrero desde navegador.
 * URL: php/seed_guerrero_demo_run.php?confirm=1
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
    echo '<p>Solo supervisora, gerente o administrador.</p>';
    exit;
}

if (empty($_GET['confirm'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Seed Guerrero demo</title></head>';
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
    echo '<p><a href="?confirm=1" style="display:inline-block;padding:12px 20px;background:#0d47a1;color:#fff;text-decoration:none;border-radius:8px;">Ejecutar seed Guerrero</a></p>';
    echo '</body></html>';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);

try {
    ob_start();
    require __DIR__ . '/../scripts/seed_guerrero_demo.php';
    echo ob_get_clean();
} catch (Throwable $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo "ERROR:\n\n" . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine();
}
