<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id']) || !grupo_division_puede()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

$accion = trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'listar'));
$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) $_SESSION['user_id'];

try {
    if ($accion === 'listar') {
        hay_json_response([
            'status' => 'ok',
            'grupos' => grupo_division_listar_grupos($pdo, $idPlantel),
            'borradores' => grupo_division_listar_borradores($pdo, $idPlantel),
            'umbral' => GRUPO_DIVISION_UMBRAL,
        ]);
        exit;
    }

    if ($accion === 'obtener') {
        $division = grupo_division_obtener($pdo, $idPlantel, (int) ($_GET['id'] ?? 0));
        if (!$division) {
            hay_json_response(['status' => 'error', 'message' => 'Borrador no encontrado'], 404);
            exit;
        }
        hay_json_response(['status' => 'ok', 'division' => $division]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        hay_json_response(['status' => 'error', 'message' => 'Método no permitido'], 405);
        exit;
    }

    if ($accion === 'proponer') {
        $res = grupo_division_proponer(
            $pdo,
            $idPlantel,
            (int) ($_POST['id_grupo'] ?? 0),
            $idUsuario
        );
    } elseif ($accion === 'confirmar') {
        $original = json_decode((string) ($_POST['asignacion_original_json'] ?? '[]'), true);
        $nuevo = json_decode((string) ($_POST['asignacion_nuevo_json'] ?? '[]'), true);
        if (!is_array($original) || !is_array($nuevo)) {
            hay_json_response(['status' => 'error', 'message' => 'Asignaciones no válidas'], 400);
            exit;
        }
        $res = grupo_division_confirmar(
            $pdo,
            $idPlantel,
            (int) ($_POST['id'] ?? 0),
            $original,
            $nuevo,
            $idUsuario
        );
    } else {
        hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
        exit;
    }

    hay_json_response([
        'status' => !empty($res['ok']) ? 'ok' : 'error',
        'message' => $res['message'] ?? 'Operación terminada',
        'id' => $res['id'] ?? null,
        'division' => $res['division'] ?? null,
    ], !empty($res['ok']) ? 200 : 400);
} catch (Throwable $e) {
    error_log('grupo_division_api ' . $accion . ': ' . $e->getMessage());
    hay_json_response(['status' => 'error', 'message' => 'No se pudo completar la operación.'], 500);
}
