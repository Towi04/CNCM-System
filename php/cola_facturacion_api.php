<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
require_once __DIR__ . '/cola_facturacion_helper.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

if (!cola_facturacion_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'listar') {
    hay_json_response([
        'status' => 'ok',
        'total' => cola_facturacion_contar($pdo, $idPlantel),
        'items' => cola_facturacion_listar($pdo, $idPlantel),
    ]);
    exit;
}

if ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idPrereg = (int) ($_POST['id_preregistro'] ?? 0);
    $file = !empty($_FILES['factura_constancia']['tmp_name']) ? $_FILES['factura_constancia'] : null;
    $res = cola_facturacion_guardar($pdo, $idPrereg, $idPlantel, $_POST, $file);
    hay_json_response(array_merge([
        'status' => $res['ok'] ? 'ok' : 'error',
        'seccion' => 'cola_facturacion',
    ], $res));
    exit;
}

if ($action === 'quitar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = cola_facturacion_quitar_solicitud($pdo, (int) ($_POST['id_preregistro'] ?? 0), $idPlantel);
    hay_json_response(array_merge([
        'status' => $res['ok'] ? 'ok' : 'error',
        'seccion' => 'cola_facturacion',
    ], $res));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
