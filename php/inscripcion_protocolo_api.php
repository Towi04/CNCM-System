<?php
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$idPlantel = plantel_scope_id($pdo);

if ($action === 'listar') {
    if (!inscripcion_protocolo_puede_autorizar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'pendientes' => inscripcion_protocolo_pendientes($pdo, $idPlantel),
    ]);
    exit;
}

if ($action === 'solicitar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = inscripcion_protocolo_solicitar($pdo, [
        'id_plantel' => $idPlantel,
        'id_alumno' => (int) ($_POST['id_alumno'] ?? 0),
        'id_preregistro' => (int) ($_POST['id_preregistro'] ?? 0),
        'id_grupo' => (int) ($_POST['id_grupo'] ?? 0),
        'id_especialidad' => (int) ($_POST['id_especialidad'] ?? 0),
        'tipo' => (string) ($_POST['tipo'] ?? 'edad'),
        'motivo' => (string) ($_POST['motivo'] ?? ''),
    ]);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'resolver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idAuth = (int) ($_POST['id_auth'] ?? 0);
    $estado = (string) ($_POST['estado'] ?? '');
    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    $res = inscripcion_protocolo_resolver($pdo, $idAuth, $estado, $motivo !== '' ? $motivo : null);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'validar') {
    $idAlumno = (int) ($_GET['id_alumno'] ?? 0);
    $idGrupo = (int) ($_GET['id_grupo'] ?? 0);
    if ($idAlumno <= 0 || $idGrupo <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Alumno y grupo requeridos']);
        exit;
    }
    $res = inscripcion_protocolo_validar_grupo($pdo, $idAlumno, $idGrupo);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
