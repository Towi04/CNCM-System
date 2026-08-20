<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id']) || !function_exists('rbac_cap') || !rbac_cap('menu_alumno_cambio_plantel')) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

$accion = trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'listar'));
$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) $_SESSION['user_id'];

try {
    if ($accion === 'buscar') {
        if (!alumno_plantel_transfer_puede_solicitar()) {
            hay_json_response(['status' => 'error', 'message' => 'Sin permiso para solicitar'], 403);
            exit;
        }
        hay_json_response([
            'status' => 'ok',
            'alumnos' => alumno_plantel_transfer_buscar_alumnos(
                $pdo,
                $idPlantel,
                trim((string) ($_GET['control'] ?? ''))
            ),
        ]);
        exit;
    }

    if ($accion === 'listar') {
        hay_json_response([
            'status' => 'ok',
            'entrantes' => alumno_plantel_transfer_listar($pdo, $idPlantel, 'entrantes'),
            'historial' => alumno_plantel_transfer_listar($pdo, $idPlantel, 'historial'),
            'planteles' => alumno_plantel_transfer_planteles_destino($pdo, $idPlantel),
            'puede_solicitar' => alumno_plantel_transfer_puede_solicitar(),
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        hay_json_response(['status' => 'error', 'message' => 'Método no permitido'], 405);
        exit;
    }

    if ($accion === 'solicitar') {
        $res = alumno_plantel_transfer_solicitar(
            $pdo,
            (int) ($_POST['id_alumno'] ?? 0),
            $idPlantel,
            (int) ($_POST['id_plantel_destino'] ?? 0),
            trim((string) ($_POST['motivo'] ?? '')),
            $idUsuario
        );
    } elseif ($accion === 'autorizar') {
        $res = alumno_plantel_transfer_ejecutar(
            $pdo,
            (int) ($_POST['id'] ?? 0),
            $idUsuario,
            trim((string) ($_POST['notas_destino'] ?? ''))
        );
    } elseif ($accion === 'rechazar') {
        $res = alumno_plantel_transfer_rechazar(
            $pdo,
            (int) ($_POST['id'] ?? 0),
            $idUsuario,
            trim((string) ($_POST['notas_destino'] ?? ''))
        );
    } elseif ($accion === 'cancelar') {
        $res = alumno_plantel_transfer_cancelar($pdo, (int) ($_POST['id'] ?? 0));
    } else {
        hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
        exit;
    }

    hay_json_response([
        'status' => !empty($res['ok']) ? 'ok' : 'error',
        'message' => $res['message'] ?? 'Operación terminada',
        'id' => $res['id'] ?? null,
    ], !empty($res['ok']) ? 200 : 400);
} catch (Throwable $e) {
    error_log('alumno_plantel_transfer_api ' . $accion . ': ' . $e->getMessage());
    hay_json_response(['status' => 'error', 'message' => 'No se pudo completar la operación.'], 500);
}
