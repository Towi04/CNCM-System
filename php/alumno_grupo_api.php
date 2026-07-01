<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) $_SESSION['user_id'];
$accion = trim($_POST['accion'] ?? '');

$idAlumno = (int) ($_POST['id_alumno'] ?? 0);
$idGrupo = (int) ($_POST['id_grupo'] ?? 0);

if ($accion === 'cambio_grupo') {
    if (!alumno_grupo_acciones_puede()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $idGrupoNuevo = (int) ($_POST['id_grupo_nuevo'] ?? 0);
    $res = alumno_cambiar_grupo($pdo, $idAlumno, $idGrupoNuevo, $idPlantel, $idUsuario);
    hay_json_response(['status' => ($res['ok'] ?? false) ? 'ok' : 'error', 'message' => $res['message'] ?? '']);
    exit;
}

if ($accion === 'fin_curso') {
    $nota = trim($_POST['nota'] ?? '');
    $res = alumno_fin_curso_grupo($pdo, $idAlumno, $idGrupo, $idPlantel, $idUsuario, $nota);
    hay_json_response(['status' => ($res['ok'] ?? false) ? 'ok' : 'error', 'message' => $res['message'] ?? '']);
    exit;
}

if ($accion === 'baja_grupo') {
    $tipo = trim($_POST['tipo_baja'] ?? 'temporal');
    $motivo = trim($_POST['motivo'] ?? '');
    $res = alumno_registrar_baja_grupo($pdo, $idAlumno, $idGrupo, $idPlantel, $tipo, $motivo, $idUsuario);
    hay_json_response(['status' => ($res['ok'] ?? false) ? 'ok' : 'error', 'message' => $res['message'] ?? '']);
    exit;
}

if ($accion === 'grupos_cambio') {
    $res = alumno_grupos_para_cambio($pdo, $idAlumno, $idGrupo, $idPlantel);
    hay_json_response(['status' => 'ok', 'grupos' => $res]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
