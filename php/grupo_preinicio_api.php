<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !grupo_preinicio_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$accion = trim($_GET['accion'] ?? $_POST['accion'] ?? 'listar_grupos');

try {
    if ($accion === 'listar_grupos') {
        $dias = (int) ($_GET['dias'] ?? 21);
        $grupos = grupo_preinicio_listar_grupos($pdo, $idPlantel, $dias);
        hay_json_response(['status' => 'ok', 'grupos' => $grupos]);
        exit;
    }

    if ($accion === 'listar_alumnos') {
        $idGrupo = (int) ($_GET['id_grupo'] ?? 0);
        if ($idGrupo <= 0) {
            hay_json_response(['status' => 'error', 'message' => 'Grupo requerido']);
            exit;
        }
        $alumnos = grupo_preinicio_listar_alumnos($pdo, $idGrupo, $idPlantel);
        hay_json_response(['status' => 'ok', 'alumnos' => $alumnos]);
        exit;
    }

    if ($accion === 'guardar_contacto' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $res = grupo_preinicio_guardar_contacto($pdo, $idPlantel, $_POST);
        hay_json_response([
            'status' => $res['ok'] ? 'ok' : 'error',
            'message' => $res['message'],
        ]);
        exit;
    }

    hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
} catch (Throwable $e) {
    error_log('grupo_preinicio_api: ' . $e->getMessage());
    hay_json_response(['status' => 'error', 'message' => 'Error en contacto pre-inicio.'], 500);
}
