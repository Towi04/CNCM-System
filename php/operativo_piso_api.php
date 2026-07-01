<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
require_once __DIR__ . '/operativo_piso_helper.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

if (!operativo_piso_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) $_SESSION['user_id'];
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'resumen') {
    hay_json_response([
        'status' => 'ok',
        'resumen' => operativo_piso_resumen($pdo, $idPlantel),
    ]);
    exit;
}

if ($action === 'listar_entrega') {
    $tipo = trim($_GET['tipo'] ?? $_POST['tipo'] ?? '');
    $tipo = in_array($tipo, ['diploma', 'constancia'], true) ? $tipo : null;
    hay_json_response([
        'status' => 'ok',
        'items' => documento_entrega_listar($pdo, $idPlantel, $tipo),
        'total' => documento_contar_pendientes_entrega_plantel($pdo, $idPlantel, $tipo),
    ]);
    exit;
}

if ($action === 'marcar_entrega' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = documento_marcar_entregado(
        $pdo,
        (int) ($_POST['id_documento'] ?? 0),
        $idPlantel,
        $idUsuario
    );
    hay_json_response(array_merge([
        'status' => $res['ok'] ? 'ok' : 'error',
        'seccion' => 'piso_operativo',
    ], $res));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
