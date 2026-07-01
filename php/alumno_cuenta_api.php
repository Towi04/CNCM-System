<?php
/**
 * API — cuentas digitales del alumno (Google, HAY, Moodle, inscripción cursos).
 */
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'estado'));
$idAlumno = (int) ($_GET['id_alumno'] ?? $_POST['id_alumno'] ?? 0);
$idPlantel = plantel_scope_id($pdo);

if ($idAlumno <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'Indique id_alumno']);
    exit;
}

if (!function_exists('cuenta_alumno_estado')) {
    hay_json_response(['status' => 'error', 'message' => 'Módulo de cuentas no disponible']);
    exit;
}

if ($action === 'estado') {
    $puede = cuenta_alumno_puede_gestionar();
    if (!$puede && function_exists('alumno_portal_es_alumno') && alumno_portal_es_alumno()) {
        $puede = alumno_portal_exigir_propio($idAlumno);
    } elseif (!$puede && function_exists('alumno_puede_ver')) {
        $puede = alumno_puede_ver();
    }
    if (!$puede) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $est = cuenta_alumno_estado($pdo, $idAlumno, $idPlantel);
    if (function_exists('alumno_portal_es_alumno') && alumno_portal_es_alumno()) {
        unset($est['password_inicial'], $est['puede_gestionar']);
    }
    hay_json_response([
        'status' => !empty($est['ok']) ? 'ok' : 'error',
        'message' => $est['message'] ?? '',
        'estado' => $est,
    ]);
    exit;
}

if (!cuenta_alumno_puede_gestionar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado para gestionar cuentas']);
    exit;
}

switch ($action) {
    case 'provisionar':
        $servicio = trim((string) ($_POST['servicio'] ?? 'all'));
        $res = cuenta_alumno_provisionar($pdo, $idAlumno, $idPlantel, $servicio);
        hay_json_response([
            'status' => !empty($res['ok']) ? 'ok' : 'error',
            'message' => $res['message'] ?? '',
            'detalle' => $res['detalle'] ?? null,
            'hint' => $res['hint'] ?? null,
            'estado' => $res['estado'] ?? cuenta_alumno_estado($pdo, $idAlumno, $idPlantel),
        ]);
        break;

    case 'reset':
        $servicio = trim((string) ($_POST['servicio'] ?? 'all'));
        $res = cuenta_alumno_reset_password($pdo, $idAlumno, $idPlantel, $servicio);
        hay_json_response([
            'status' => !empty($res['ok']) ? 'ok' : 'error',
            'message' => $res['message'] ?? '',
            'password' => $res['password'] ?? null,
            'detalle' => $res['detalle'] ?? null,
        ]);
        break;

    case 'enrol_curso':
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $res = cuenta_alumno_enrol_curso($pdo, $idAlumno, $idPlantel, $courseId);
        hay_json_response([
            'status' => !empty($res['ok']) ? 'ok' : 'error',
            'message' => $res['message'] ?? '',
            'estado' => $res['estado'] ?? null,
        ]);
        break;

    case 'enrol_examen':
        $idExamen = (int) ($_POST['id_examen'] ?? 0);
        $res = cuenta_alumno_enrol_examen($pdo, $idAlumno, $idPlantel, $idExamen);
        hay_json_response([
            'status' => !empty($res['ok']) ? 'ok' : 'error',
            'message' => $res['message'] ?? '',
            'estado' => $res['estado'] ?? null,
        ]);
        break;

    case 'opciones':
        $idEsp = (int) ($_GET['id_especialidad'] ?? $_POST['id_especialidad'] ?? 0);
        hay_json_response([
            'status' => 'ok',
            'examenes' => cuenta_alumno_examenes_opciones($pdo, $idEsp > 0 ? $idEsp : null),
            'cursos' => cuenta_alumno_cursos_opciones(),
        ]);
        break;

    case 'buscar':
        $res = cuenta_digital_buscar_externas(
            trim((string) ($_POST['google_email'] ?? '')),
            trim((string) ($_POST['moodle_ref'] ?? ''))
        );
        hay_json_response(['status' => 'ok', 'resultado' => $res]);
        break;

    case 'vincular':
        $res = cuenta_digital_vincular_alumno($pdo, $idAlumno, $idPlantel, [
            'google_email' => trim((string) ($_POST['google_email'] ?? '')),
            'moodle_ref' => trim((string) ($_POST['moodle_ref'] ?? '')),
            'username_unificado' => trim((string) ($_POST['username_unificado'] ?? '')),
            'sync_moodle_username' => !empty($_POST['sync_moodle_username']),
        ]);
        hay_json_response([
            'status' => !empty($res['ok']) ? 'ok' : 'error',
            'message' => $res['message'] ?? '',
            'detalle' => $res['detalle'] ?? null,
            'estado' => $res['estado'] ?? null,
        ]);
        break;

    default:
        hay_json_response(['status' => 'error', 'message' => 'Acción no válida: ' . $action]);
}
