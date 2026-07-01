<?php
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'set_alumno_simulacion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('alumno_portal_establecer_alumno_simulacion')) {
        hay_json_response(['status' => 'error', 'message' => 'Función no disponible']);
        exit;
    }
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    hay_json_response(alumno_portal_establecer_alumno_simulacion($pdo, $idAlumno));
    exit;
}

if (!alumno_portal_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idAlumno = alumno_portal_id_sesion();
if ($idAlumno <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'Sin alumno vinculado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);

if ($action === 'salas') {
    hay_json_response([
        'status' => 'ok',
        'salas' => alumno_portal_ensure_chat_salas($pdo, $idPlantel, $idAlumno),
    ]);
    exit;
}

if ($action === 'mensajes') {
    $idSala = (int) ($_GET['id_sala'] ?? 0);
    if ($idSala <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Sala inválida']);
        exit;
    }
    $salas = alumno_portal_ensure_chat_salas($pdo, $idPlantel, $idAlumno);
    $permitida = false;
    foreach ($salas as $s) {
        if ((int) $s['id_sala'] === $idSala) {
            $permitida = true;
            break;
        }
    }
    if (!$permitida) {
        hay_json_response(['status' => 'error', 'message' => 'No tiene acceso a esta sala']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'mensajes' => alumno_portal_chat_mensajes($pdo, $idSala),
    ]);
    exit;
}

if ($action === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idSala = (int) ($_POST['id_sala'] ?? 0);
    $texto = trim($_POST['mensaje'] ?? '');
    $salas = alumno_portal_ensure_chat_salas($pdo, $idPlantel, $idAlumno);
    $permitida = false;
    foreach ($salas as $s) {
        if ((int) $s['id_sala'] === $idSala) {
            $permitida = true;
            break;
        }
    }
    if (!$permitida) {
        hay_json_response(['status' => 'error', 'message' => 'Sala no permitida']);
        exit;
    }
    $nombre = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''));
    $res = alumno_portal_chat_enviar($pdo, $idSala, $idAlumno, $texto, $nombre !== '' ? $nombre : 'Alumno');
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => 'Enviado']
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
