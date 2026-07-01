<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !cronologia_puede_ver()) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$plantel = plantel_find($pdo, $idPlantel);
$tituloPlantel = trim((string) ($plantel['nombre'] ?? $plantel['clave'] ?? ''));

$filtros = [
    'id_especialidad' => (int) ($_GET['id_especialidad'] ?? 0),
    'id_profesor' => (int) ($_GET['id_profesor'] ?? 0),
    'id_grupo' => (int) ($_GET['id_grupo'] ?? 0),
    'q' => trim($_GET['q'] ?? ''),
    'estado' => trim($_GET['estado'] ?? ''),
    'semanas_atras' => (int) ($_GET['semanas_atras'] ?? 6),
    'semanas_adelante' => (int) ($_GET['semanas_adelante'] ?? 14),
];

try {
    $res = cronologia_generar_pdf($pdo, $idPlantel, $filtros, $tituloPlantel);
    if (!$res['ok']) {
        http_response_code(500);
        echo 'Error al generar documento';
        exit;
    }

    header('Content-Type: ' . $res['mime']);
    header('Content-Disposition: inline; filename="' . $res['filename'] . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $res['contenido'];
} catch (Throwable $e) {
    error_log('cronologia_pdf: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
