<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!function_exists('ubicacion_examen_puede_administrar') || !ubicacion_examen_puede_administrar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

ubicacion_examen_ensure_schema($pdo);

if ($action === 'listar') {
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    hay_json_response([
        'status' => 'ok',
        'items' => ubicacion_examen_listar($pdo, $idEsp > 0 ? $idEsp : null, false),
    ]);
    exit;
}

if ($action === 'fases') {
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    $fases = [];
    if ($idEsp > 0) {
        $st = $pdo->prepare(
            'SELECT id_fase, clave_fase, nombre_fase FROM especialidad_fases
             WHERE id_especialidad = ? ORDER BY orden ASC, id_fase ASC'
        );
        $st->execute([$idEsp]);
        $fases = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    hay_json_response(['status' => 'ok', 'fases' => $fases]);
    exit;
}

if ($action === 'cursos_moodle') {
    $items = function_exists('moodle_list_courses') ? moodle_list_courses() : [];
    hay_json_response([
        'status' => 'ok',
        'cursos' => $items,
        'moodle_enabled' => function_exists('moodle_enabled') && moodle_enabled(),
    ]);
    exit;
}

if ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idEx = (int) ($_POST['id_examen'] ?? 0);
    $res = ubicacion_examen_guardar($pdo, [
        'id_especialidad' => (int) ($_POST['id_especialidad'] ?? 0),
        'id_fase' => (int) ($_POST['id_fase'] ?? 0),
        'nombre' => trim((string) ($_POST['nombre'] ?? '')),
        'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
        'moodle_idnumber' => trim((string) ($_POST['moodle_idnumber'] ?? $_POST['moodle_course_id'] ?? '')),
        'moodle_course_id' => (int) ($_POST['moodle_course_id'] ?? 0),
        'moodle_shortname' => trim((string) ($_POST['moodle_shortname'] ?? '')),
        'activo' => !empty($_POST['activo']),
        'orden' => (int) ($_POST['orden'] ?? 0),
    ], $idEx > 0 ? $idEx : null);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idEx = (int) ($_POST['id_examen'] ?? 0);
    if ($idEx <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Examen inválido']);
        exit;
    }
    $res = ubicacion_examen_eliminar($pdo, $idEx);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no reconocida']);
