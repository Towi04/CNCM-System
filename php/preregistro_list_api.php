<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!preregistro_puede_acceder()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$action = trim($_GET['action'] ?? $_POST['action'] ?? 'datatable');

if ($action === 'sync') {
    preregistro_sync_lista_cache($pdo, $idPlantel, true);
    hay_json_response(['status' => 'ok']);
    exit;
}

$result = preregistro_datatable($pdo, $idPlantel, $_GET);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
