<?php
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!academico_alumno_portal_puede()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) ($_SESSION['user_id'] ?? 0);
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'avisos_listar') {
    hay_json_response([
        'status' => 'ok',
        'avisos' => alumno_aviso_listar_staff($pdo, $idPlantel),
    ]);
    exit;
}

if ($action === 'aviso_guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'id_aviso' => (int) ($_POST['id_aviso'] ?? 0),
        'titulo' => $_POST['titulo'] ?? '',
        'mensaje' => $_POST['mensaje'] ?? '',
        'id_grupo' => $_POST['id_grupo'] ?? '',
        'vigente_hasta' => $_POST['vigente_hasta'] ?? '',
        'activo' => (int) ($_POST['activo'] ?? 1),
    ];
    $res = alumno_aviso_guardar_staff($pdo, $idPlantel, $data);
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => 'Guardado', 'id_aviso' => $res['id_aviso'] ?? 0]
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

if ($action === 'salas') {
    hay_json_response([
        'status' => 'ok',
        'salas' => academico_alumno_portal_chat_salas($pdo, $idPlantel),
    ]);
    exit;
}

if ($action === 'mensajes') {
    $idSala = (int) ($_GET['id_sala'] ?? 0);
    if ($idSala <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Sala inválida']);
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
    if ($idSala <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Sala inválida']);
        exit;
    }
    $nombre = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''));
    $res = alumno_portal_chat_enviar_staff($pdo, $idSala, $idUsuario, $texto, $nombre !== '' ? $nombre : 'Staff');
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => 'Enviado']
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
