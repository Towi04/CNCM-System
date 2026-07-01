<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$accion = trim($_GET['accion'] ?? $_POST['accion'] ?? '');

try {
    if ($accion === 'listar') {
        if (!escuelas_puede_gestionar() && !escuelas_puede_ver_reporte()) {
            hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $soloActivas = empty($_GET['incluir_inactivas']);
        hay_json_response(['status' => 'ok', 'escuelas' => escuelas_listar($pdo, $idPlantel, $soloActivas)]);
        exit;
    }

    if ($accion === 'reporte') {
        if (!escuelas_puede_ver_reporte()) {
            hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $filtros = [];
        if (!empty($_GET['desde'])) {
            $filtros['desde'] = $_GET['desde'];
        }
        if (!empty($_GET['hasta'])) {
            $filtros['hasta'] = $_GET['hasta'];
        }
        if (!empty($_GET['id_escuela'])) {
            $filtros['id_escuela'] = (int) $_GET['id_escuela'];
        }
        $data = escuelas_reporte($pdo, $idPlantel, $filtros);
        hay_json_response(['status' => 'ok', 'filas' => $data['filas'], 'resumen' => $data['resumen']]);
        exit;
    }

    if ($accion === 'guardar_escuela' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!escuelas_puede_gestionar()) {
            hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $res = escuelas_guardar($pdo, $idPlantel, $_POST);
        hay_json_response([
            'status' => $res['ok'] ? 'ok' : 'error',
            'message' => $res['message'],
            'id_escuela' => $res['id_escuela'] ?? null,
        ]);
        exit;
    }

    if ($accion === 'eliminar_escuela' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!escuelas_puede_gestionar()) {
            hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $res = escuelas_eliminar($pdo, $idPlantel, (int) ($_POST['id_escuela'] ?? 0));
        hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
        exit;
    }

    if ($accion === 'guardar_visita' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!escuelas_puede_gestionar()) {
            hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $res = escuelas_guardar_visita($pdo, $idPlantel, $_POST);
        hay_json_response([
            'status' => $res['ok'] ? 'ok' : 'error',
            'message' => $res['message'],
            'id_visita' => $res['id_visita'] ?? null,
        ]);
        exit;
    }

    hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
} catch (Throwable $e) {
    error_log('escuelas_api: ' . $e->getMessage());
    hay_json_response(['status' => 'error', 'message' => 'Error en escuelas.'], 500);
}
