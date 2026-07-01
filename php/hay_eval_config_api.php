<?php

declare(strict_types=1);
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

if (!hay_eval_puede_configurar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'listar_areas') {
    hay_json_response(['status' => 'ok', 'areas' => hay_eval_listar_areas($pdo, false)]);
    exit;
}

if ($action === 'rubrica') {
    $idArea = (int) ($_GET['id_area'] ?? 0);
    if ($idArea <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'id_area requerido']);
        exit;
    }
    hay_json_response(['status' => 'ok', 'rubrica' => hay_eval_rubrica_completa($pdo, $idArea)]);
    exit;
}

if ($action === 'guardar_area' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $roles = $_POST['roles'] ?? [];
    if (is_string($roles)) {
        $roles = array_filter(array_map('trim', explode(',', $roles)));
    }
    $res = hay_eval_guardar_area($pdo, [
        'id_area' => (int) ($_POST['id_area'] ?? 0),
        'clave' => $_POST['clave'] ?? '',
        'nombre' => $_POST['nombre'] ?? '',
        'descripcion' => $_POST['descripcion'] ?? '',
        'activo' => $_POST['activo'] ?? 1,
        'roles' => $roles,
        'moodle_course_examen_id' => (int) ($_POST['moodle_course_examen_id'] ?? 0),
        'alias_especialidad' => $_POST['alias_especialidad'] ?? '',
    ]);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'guardar_aspecto' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        hay_eval_ensure_schema($pdo);
        $res = hay_eval_guardar_aspecto($pdo, $_POST);
        hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    } catch (Throwable $e) {
        error_log('hay_eval guardar_aspecto: ' . $e->getMessage());
        hay_json_response([
            'status' => 'error',
            'message' => 'No se pudo guardar el aspecto. Verifique que el código no esté duplicado.',
        ], 500);
    }
    exit;
}

if ($action === 'guardar_opcion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = hay_eval_guardar_opcion($pdo, $_POST);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'desactivar_opcion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    hay_eval_desactivar_opcion($pdo, (int) ($_POST['id_opcion'] ?? 0));
    hay_json_response(['status' => 'ok']);
    exit;
}

if ($action === 'desactivar_aspecto' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    hay_eval_desactivar_aspecto($pdo, (int) ($_POST['id_aspecto'] ?? 0));
    hay_json_response(['status' => 'ok']);
    exit;
}

if ($action === 'publicar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = hay_eval_publicar_version($pdo, (int) ($_POST['id_area'] ?? 0), (int) $_SESSION['user_id']);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'seed_profesor_ingles' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $forzar = !empty($_POST['forzar']) && (string) $_POST['forzar'] !== '0';
    $res = hay_eval_seed_profesor_ingles($pdo, $forzar);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'guardar_nivel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = hay_eval_guardar_nivel($pdo, $_POST);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'listar_niveles') {
    $idArea = (int) ($_GET['id_area'] ?? 0);
    hay_json_response(['status' => 'ok', 'niveles' => hay_eval_listar_niveles($pdo, $idArea)]);
    exit;
}

if ($action === 'guardar_capacitacion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = hay_eval_guardar_capacitacion($pdo, $_POST);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'listar_capacitaciones') {
    $idArea = (int) ($_GET['id_area'] ?? 0);
    hay_json_response([
        'status' => 'ok',
        'capacitaciones' => hay_eval_listar_capacitaciones($pdo, $idArea),
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
