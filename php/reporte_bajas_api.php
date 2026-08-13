<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

if (!reporte_bajas_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'listar'));
$idPlantel = plantel_scope_id($pdo);

if ($action === 'listar') {
    $data = reporte_bajas_listar($pdo, $idPlantel, [
        'periodo' => $_GET['periodo'] ?? $_POST['periodo'] ?? 'mes',
        'desde' => $_GET['desde'] ?? $_POST['desde'] ?? '',
        'hasta' => $_GET['hasta'] ?? $_POST['hasta'] ?? '',
    ]);
    hay_json_response(['status' => 'ok'] + $data);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
