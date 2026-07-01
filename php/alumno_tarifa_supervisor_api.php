<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!alumno_tarifa_supervisor_puede()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) ($_SESSION['user_id'] ?? 0);
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'listar') {
    $idAlumno = (int) ($_GET['id_alumno'] ?? 0);
    if ($idAlumno <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Alumno inválido']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'items' => alumno_tarifa_supervisor_listar($pdo, $idAlumno, $idPlantel),
        'historial' => alumno_tarifa_supervisor_historial($pdo, $idAlumno),
        'condonaciones' => alumno_tarifa_supervisor_condonaciones($pdo, $idAlumno),
    ]);
    exit;
}

if ($action === 'guardar') {
    $res = alumno_tarifa_supervisor_guardar($pdo, $idPlantel, $_POST, $idUsuario);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
    ]);
    exit;
}

if ($action === 'restaurar') {
    $idAe = (int) ($_POST['id_alumno_especialidad'] ?? 0);
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    $motivo = trim((string) ($_POST['motivo'] ?? 'Restauración manual por supervisor'));
    $res = alumno_tarifa_supervisor_restaurar($pdo, $idPlantel, $idAe, $idAlumno, $idUsuario, $motivo);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
    ]);
    exit;
}

if ($action === 'condonar') {
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    $idAe = (int) ($_POST['id_alumno_especialidad'] ?? 0);
    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    $res = alumno_tarifa_supervisor_condonar_adeudo(
        $pdo,
        $idPlantel,
        $idAlumno,
        $motivo,
        $idUsuario,
        $idAe > 0 ? $idAe : null
    );
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
