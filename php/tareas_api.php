<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=UTF-8');

if (!tareas_puede_usar()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) $_SESSION['user_id'];
$accion = trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'listar'));

try {
    tareas_ensure_schema($pdo);

    if ($accion === 'listar') {
        $filtro = trim((string) ($_GET['filtro'] ?? 'pendientes'));
        if (!in_array($filtro, ['pendientes', 'vencidas', 'hechas'], true)) {
            $filtro = 'pendientes';
        }
        hay_json_response([
            'status' => 'ok',
            'items' => tareas_listar($pdo, $idPlantel, $filtro),
            'personal' => tareas_personal_plantel($pdo, $idPlantel),
            'filtro' => $filtro,
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        hay_json_response(['status' => 'error', 'message' => 'Método no válido'], 405);
        exit;
    }

    if ($accion === 'crear') {
        $res = tareas_crear($pdo, $idPlantel, $idUsuario, $_POST);
    } elseif ($accion === 'hecha') {
        $res = tareas_marcar_hecha(
            $pdo,
            $idPlantel,
            (int) ($_POST['id'] ?? 0),
            $idUsuario
        );
    } elseif ($accion === 'posponer') {
        $res = tareas_posponer(
            $pdo,
            $idPlantel,
            (int) ($_POST['id'] ?? 0),
            trim((string) ($_POST['fecha'] ?? ''))
        );
    } else {
        hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
        exit;
    }

    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res, $res['ok'] ? 200 : 400);
} catch (Throwable $e) {
    error_log('tareas_api: ' . $e->getMessage());
    hay_json_response(['status' => 'error', 'message' => 'No se pudo procesar la tarea'], 500);
}
