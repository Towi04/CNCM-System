<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id']) || !moodle_nivel_puede_administrar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$idPlantel = plantel_scope_id($pdo);

if ($action === 'cobertura') {
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    hay_json_response([
        'status' => 'ok',
        'cobertura' => moodle_fase_cobertura_especialidad($pdo, $idEsp > 0 ? $idEsp : null),
        'moodle_habilitado' => function_exists('moodle_enabled') && moodle_enabled(),
    ]);
    exit;
}

if ($action === 'sync_plantel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idEsp = (int) ($_POST['id_especialidad'] ?? 0);
    $res = moodle_plantel_sync_grupos_activos($pdo, $idPlantel, $idEsp > 0 ? $idEsp : null);
    hay_json_response([
        'status' => !empty($res['ok']) ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
        'procesados' => $res['procesados'] ?? 0,
        'ok_count' => $res['ok_count'] ?? 0,
        'err_count' => $res['err_count'] ?? 0,
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
