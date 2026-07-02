<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!supervisor_grupos_historico_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) ($_SESSION['user_id'] ?? 0);

try {
    if ($action === 'contexto') {
        hay_json_response([
            'status' => 'ok',
            'contexto' => supervisor_grupos_historico_contexto($pdo, $idPlantel),
        ]);
        exit;
    }

    if ($action === 'grupos') {
        hay_json_response([
            'status' => 'ok',
            'grupos' => supervisor_grupos_historico_grupos($pdo, $idPlantel),
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        hay_json_response(['status' => 'error', 'message' => 'Metodo no permitido'], 405);
        exit;
    }

    if ($action === 'crear_grupo') {
        $res = supervisor_grupos_historico_crear_grupo($pdo, $idPlantel, $_POST, $idUsuario);
        hay_json_response(['status' => 'ok'] + $res);
        exit;
    }

    if ($action === 'actualizar_clave') {
        $res = supervisor_grupos_historico_actualizar_clave($pdo, $idPlantel, $_POST, $idUsuario);
        hay_json_response(['status' => 'ok'] + $res);
        exit;
    }

    if ($action === 'cargar_alumnos') {
        $res = supervisor_grupos_historico_cargar_alumnos($pdo, $idPlantel, $_POST);
        hay_json_response(['status' => 'ok'] + $res);
        exit;
    }

    if ($action === 'cargar_pagos') {
        $res = supervisor_grupos_historico_cargar_pagos($pdo, $idPlantel, $_POST, $idUsuario);
        hay_json_response(['status' => 'ok'] + $res);
        exit;
    }

    if ($action === 'cargar_calificaciones') {
        $res = supervisor_grupos_historico_cargar_calificaciones($pdo, $idPlantel, $_POST, $idUsuario);
        hay_json_response(['status' => 'ok'] + $res);
        exit;
    }

    hay_json_response(['status' => 'error', 'message' => 'Accion no valida'], 400);
} catch (InvalidArgumentException $e) {
    hay_json_response(['status' => 'error', 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('supervisor_grupos_historico_api: ' . $e->getMessage());
    hay_json_response(['status' => 'error', 'message' => 'No se pudo completar la operacion'], 500);
}
