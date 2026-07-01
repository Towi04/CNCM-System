<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

require_once __DIR__ . '/moodle_inscripcion_helper.php';

if (!moodle_inscripcion_puede_gestionar()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$idPlantel = plantel_scope_id($pdo);

if ($action === 'cursos') {
    $idEsp = (int) ($_GET['id_especialidad'] ?? $_POST['id_especialidad'] ?? 0);
    hay_json_response([
        'status' => 'ok',
        'cursos' => moodle_inscripcion_cursos_opciones($idEsp > 0 ? $idEsp : null),
    ]);
    exit;
}

if ($action === 'cursos_grupo') {
    $idGrupo = (int) ($_GET['id_grupo'] ?? $_POST['id_grupo'] ?? 0);
    $data = moodle_inscripcion_cursos_para_grupo($pdo, $idGrupo);
    hay_json_response([
        'status' => !empty($data['ok']) ? 'ok' : 'error',
        'message' => $data['message'] ?? '',
        'cursos' => $data['cursos'] ?? [],
        'especialidad' => $data['especialidad'] ?? '',
        'id_especialidad' => $data['id_especialidad'] ?? 0,
    ]);
    exit;
}

if ($action === 'inscribir_alumno') {
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $idEsp = (int) ($_POST['id_especialidad'] ?? 0) ?: null;
    $res = moodle_inscripcion_alumno_curso($pdo, $idAlumno, $courseId, $idPlantel, $idEsp);
    hay_json_response([
        'status' => !empty($res['ok']) ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
    ]);
    exit;
}

if ($action === 'inscribir_grupo') {
    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $res = moodle_inscripcion_grupo_curso($pdo, $idGrupo, $courseId, $idPlantel);
    hay_json_response([
        'status' => !empty($res['ok']) ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
        'inscritos' => $res['inscritos'] ?? 0,
        'omitidos' => $res['omitidos'] ?? 0,
        'errores' => $res['errores'] ?? 0,
        'detalle' => $res['detalle'] ?? [],
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
