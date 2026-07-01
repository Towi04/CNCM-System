<?php
/**
 * Ejecutar seed operativo desde navegador.
 * URL: php/seed_datos_operativos_run.php?confirm=1
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !(function_exists('rbac_cap') ? rbac_cap('seed_datos') : in_array($_SESSION['rol'] ?? '', ['admin', 'gerente', 'supervisor'], true))) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Solo supervisora, gerente o administrador.</p>';
    exit;
}

if (empty($_GET['confirm'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Seed operativo</title></head><body style="font-family:sans-serif;padding:24px;max-width:720px;">';
    echo '<h1>Cargar datos operativos de prueba</h1>';
    echo '<p>Agrega a los alumnos demo:</p><ul>';
    echo '<li>Pagos de inscripción y colegiaturas (últimos 6 meses, con algunos adeudos)</li>';
    echo '<li>Asistencias de sábado (~6 meses)</li>';
    echo '<li>Calificaciones del parcial actual</li>';
    echo '<li>Preregistros de ejemplo por plantel</li>';
    echo '<li>Evaluación 360 cerrada (mes anterior) para profesores demo</li>';
    echo '</ul>';
    echo '<p><strong>Requisito:</strong> ejecutar antes el seed base (<code>seed_datos_prueba.php</code>).</p>';
    echo '<p><a href="?confirm=1" style="display:inline-block;padding:12px 20px;background:#1565c0;color:#fff;text-decoration:none;border-radius:8px;">Ejecutar seed operativo</a></p>';
    echo '</body></html>';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

try {
    ob_start();
    require __DIR__ . '/../scripts/seed_datos_operativos.php';
    echo ob_get_clean();
} catch (Throwable $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo "ERROR:\n\n" . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine();
}
