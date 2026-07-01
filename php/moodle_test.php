<?php
/**
 * Diagnóstico Moodle.
 *
 * Sin sesión:
 *   ?paso=conexion  → prueba token Moodle
 *   ?paso=config    → MOODLE_URL / token definidos
 *   ?paso=sesion    → estado de cookie/sesión (debug)
 *
 * Con sesión HAY (supervisor, gerente, admin, coordinación académica) o clave MOODLE_DIAG_KEY:
 *   ?paso=payload&id_alumno=N   (o ?control=NUMERO_CONTROL)
 *   ?paso=crear&id_alumno=N     (o ?control=NUMERO_CONTROL)
 *   ?paso=cursos    → listar cursos Moodle
 *   ?paso=buscar&control=NUMERO  → ¿existe ya en Moodle?
 *   ?paso=enrol&control=N&id_examen=ID → probar inscripción al curso del examen
 */
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$paso = trim((string) ($_GET['paso'] ?? 'conexion'));
if ($paso === '') {
    $paso = 'conexion';
}

function moodle_test_diag_key_ok(): bool
{
    if (!defined('MOODLE_DIAG_KEY') || trim((string) MOODLE_DIAG_KEY) === '') {
        return false;
    }
    $key = trim((string) ($_GET['key'] ?? $_POST['key'] ?? ''));
    if ($key === '') {
        return false;
    }

    return hash_equals((string) MOODLE_DIAG_KEY, $key);
}

function moodle_test_puede_acceder(): bool
{
    if (moodle_test_diag_key_ok()) {
        return true;
    }

    $idU = (int) ($_SESSION['user_id'] ?? 0);
    if ($idU <= 0) {
        return false;
    }

    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO && function_exists('rbac_reparar_sesion_desde_cuenta_bd')) {
        rbac_reparar_sesion_desde_cuenta_bd($pdo, $idU);
    }
    if (function_exists('rbac_supervisor_aplicar_sesion')) {
        rbac_supervisor_aplicar_sesion();
    }

    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if (function_exists('rbac_es_supervisor') && rbac_es_supervisor()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('admin_usuarios')) {
        return true;
    }
    if (function_exists('ubicacion_puede_evaluar') && ubicacion_puede_evaluar()) {
        return true;
    }
    if (function_exists('inscripcion_wizard_puede_inscribir') && inscripcion_wizard_puede_inscribir()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('menu_admin')) {
        return true;
    }

    $real = function_exists('rbac_rol_real') ? rbac_rol_real() : '';

    return in_array($real, ['supervisor', 'director', 'gerente', 'admin'], true);
}

/** @return array<string, mixed> */
function moodle_test_sesion_diagnostico(): array
{
    $idU = (int) ($_SESSION['user_id'] ?? 0);
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : (string) ($_SESSION['rol'] ?? '');
    $rolReal = function_exists('rbac_rol_real') ? rbac_rol_real() : $rol;
    $cookieName = function_exists('hay_session_name') ? hay_session_name() : 'HAYSESSID';
    $tieneCookie = !empty($_COOKIE[$cookieName]);
    $loginUrl = function_exists('hay_asset_url') ? hay_asset_url('index.php') : '/index.php';

    $hint = $tieneCookie
        ? 'Hay cookie de sesión pero user_id vacío: cierre sesión, vuelva a entrar en ' . $loginUrl
        : 'No hay cookie ' . $cookieName . '. Debe iniciar sesión en el sistema en ESTE navegador (mismo dominio) y luego abrir esta URL, o use fetch desde la app con credentials. No use Postman sin cookie.';

    return [
        'sesion_activa' => $idU > 0,
        'user_id' => $idU > 0 ? $idU : null,
        'rol_efectivo' => $idU > 0 && $rol !== '' ? $rol : null,
        'rol_real' => $idU > 0 && $rolReal !== '' ? $rolReal : null,
        'cookie_hay' => $tieneCookie,
        'cookie_name' => $cookieName,
        'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
        'login_url' => $loginUrl,
        'hint' => $hint,
        'pasos' => [
            '1. Entre a ' . $loginUrl . ' e inicie sesión.',
            '2. Sin cerrar el navegador, abra: ' . (function_exists('hay_asset_url') ? hay_asset_url('php/moodle_test.php') : '/php/moodle_test.php') . '?paso=crear&id_alumno=ID',
            '3. Use el mismo host (www.cncm.edu.mx) que en el login.',
            '4. Opcional: defina MOODLE_DIAG_KEY en config.local.php y agregue &key=... para pruebas sin sesión.',
        ],
    ];
}

function moodle_test_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($paso === 'sesion') {
    moodle_test_json([
        'status' => 'ok',
        'message' => 'Diagnóstico de sesión HAY',
        'paso' => 'sesion',
        'autorizado_moodle_test' => moodle_test_puede_acceder(),
        'diagnostico' => moodle_test_sesion_diagnostico(),
    ]);
}

if ($paso === 'config') {
    moodle_test_json([
        'status' => function_exists('moodle_enabled') && moodle_enabled() ? 'ok' : 'error',
        'message' => function_exists('moodle_enabled') && moodle_enabled()
            ? 'Moodle configurado'
            : 'Defina MOODLE_URL y MOODLE_TOKEN en config.local.php',
        'paso' => 'config',
        'moodle_url' => defined('MOODLE_URL') ? MOODLE_URL : '',
        'moodle_base' => function_exists('moodle_base_url') ? moodle_base_url() : '',
        'token_definido' => defined('MOODLE_TOKEN') && trim((string) MOODLE_TOKEN) !== '',
        'diag_key_configurada' => defined('MOODLE_DIAG_KEY') && trim((string) MOODLE_DIAG_KEY) !== '',
    ]);
}

if ($paso === 'conexion') {
    $res = function_exists('moodle_test_connection')
        ? moodle_test_connection()
        : ['ok' => false, 'message' => 'moodle_helper.php no disponible'];

    moodle_test_json([
        'status' => !empty($res['ok']) ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
        'site' => $res['site'] ?? null,
        'paso' => 'conexion',
    ]);
}

if (!moodle_test_puede_acceder()) {
    $diag = moodle_test_sesion_diagnostico();
    $msg = empty($diag['cookie_hay'])
        ? 'No hay sesión HAY en esta petición (cookie ausente). Inicie sesión en el navegador y reintente.'
        : 'Sesión inválida o sin permiso para diagnóstico Moodle.';

    moodle_test_json([
        'status' => 'error',
        'message' => $msg,
        'tipo' => 'hay_sesion',
        'paso' => $paso,
        'diagnostico' => $diag,
    ], 403);
}

if ($paso === 'cursos') {
    $items = function_exists('moodle_list_courses') ? moodle_list_courses() : [];
    moodle_test_json([
        'status' => 'ok',
        'paso' => 'cursos',
        'total' => count($items),
        'cursos' => array_slice($items, 0, 50),
        'hint' => 'Busque shortname "Exam" y use ese id (no el "Número ID del curso" = idnumber).',
    ]);
}

if ($paso === 'resolver_curso') {
    $shortname = trim((string) ($_GET['shortname'] ?? 'Exam'));
    $courseId = (int) ($_GET['course_id'] ?? $_GET['id'] ?? 0);
    if ($shortname === '' && $courseId <= 0) {
        moodle_test_json([
            'status' => 'error',
            'message' => 'Indique shortname=Exam y/o course_id (valor guardado en HAY)',
            'paso' => 'resolver_curso',
        ]);
    }
    $resolved = function_exists('moodle_course_resolve_for_examen')
        ? moodle_course_resolve_for_examen((string) $courseId, $shortname, 0)
        : ['ok' => false, 'message' => 'moodle_helper no disponible'];
    moodle_test_json([
        'status' => !empty($resolved['ok']) ? 'ok' : 'error',
        'paso' => 'resolver_curso',
        'entrada' => ['shortname' => $shortname, 'idnumber' => (string) $courseId],
        'resuelto' => $resolved,
        'nota' => 'Configure idnumber=4 y shortname=Exam en HAY; el sistema resuelve el ID interno (ej. 168) solo.',
    ]);
}

if ($paso === 'examenes') {
    if (function_exists('ubicacion_examen_ensure_schema')) {
        ubicacion_examen_ensure_schema($pdo);
    }
    $items = function_exists('ubicacion_examen_listar') ? ubicacion_examen_listar($pdo, null, false) : [];
    moodle_test_json([
        'status' => 'ok',
        'paso' => 'examenes',
        'total' => count($items),
        'examenes' => $items,
        'hint' => 'Use id_examen de aquí en ?paso=enrol&control=NUMERO&id_examen=ID',
    ]);
}

if ($paso === 'usuario') {
    $ref = function_exists('moodle_test_ref_alumno_desde_request')
        ? moodle_test_ref_alumno_desde_request()
        : trim((string) ($_GET['id_alumno'] ?? ''));
    if ($ref === '') {
        moodle_test_json(['status' => 'error', 'message' => 'Indique control o id_alumno', 'paso' => 'usuario']);
    }
    $idPlantelSesion = plantel_scope_id($pdo);
    $resolved = moodle_alumno_resolver($pdo, $ref, $idPlantelSesion);
    if (empty($resolved['ok'])) {
        moodle_test_json([
            'status' => 'error',
            'message' => (string) ($resolved['message'] ?? 'Alumno no encontrado'),
            'paso' => 'usuario',
            'diagnostico' => $resolved['diagnostico'] ?? null,
        ]);
    }
    $idAlumno = (int) ($resolved['id_alumno'] ?? 0);
    $idPlantel = (int) ($resolved['id_plantel'] ?? $idPlantelSesion);
    $st = $pdo->prepare(
        'SELECT id_alumno, numero_control, nombres, apellido_paterno, apellido_materno, email
         FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$idAlumno, $idPlantel]);
    $al = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $payload = function_exists('moodle_user_payload_from_alumno') ? moodle_user_payload_from_alumno($al) : [];
    $findUser = function_exists('moodle_user_find_for_payload')
        ? moodle_user_find_for_payload($payload)
        : ['ok' => false, 'users' => []];
    $ensure = moodle_user_ensure_alumno($pdo, $idAlumno, $idPlantel);
    moodle_test_json([
        'status' => !empty($ensure['ok']) ? 'ok' : 'error',
        'paso' => 'usuario',
        'payload' => $payload,
        'username_candidatos' => function_exists('moodle_username_candidates_for_payload')
            ? moodle_username_candidates_for_payload($payload)
            : [],
        'busqueda' => $findUser,
        'ensure' => $ensure,
        'hint' => 'Si el usuario existe en mdl_user pero busqueda viene vacía, el token no puede consultar usuarios vía API. '
            . 'Revise core_user_get_users_by_field en el servicio web. Username esperado: '
            . ($payload['username'] ?? '') . '.',
    ]);
}

if ($paso === 'enrol') {
    $ref = function_exists('moodle_test_ref_alumno_desde_request')
        ? moodle_test_ref_alumno_desde_request()
        : trim((string) ($_GET['id_alumno'] ?? ''));
    $idExamen = (int) ($_GET['id_examen'] ?? 0);
    $idnumber = trim((string) ($_GET['idnumber'] ?? $_GET['moodle_idnumber'] ?? ''));
    if ($ref === '' || ($idExamen <= 0 && $idnumber === '')) {
        moodle_test_json([
            'status' => 'error',
            'message' => 'Indique control y id_examen (HAY) o idnumber (Moodle)',
            'paso' => 'enrol',
            'ejemplos' => [
                '?paso=enrol&control=14578&id_examen=1',
                '?paso=enrol&control=14578&idnumber=4',
            ],
        ]);
    }
    if (!function_exists('ubicacion_examen_resolver_peticion')) {
        moodle_test_json(['status' => 'error', 'message' => 'Helpers no disponibles', 'paso' => 'enrol']);
    }
    $idPlantelSesion = plantel_scope_id($pdo);
    $resolved = moodle_alumno_resolver($pdo, $ref, $idPlantelSesion);
    if (empty($resolved['ok'])) {
        moodle_test_json([
            'status' => 'error',
            'message' => (string) ($resolved['message'] ?? 'Alumno no encontrado'),
            'paso' => 'enrol',
            'diagnostico' => $resolved['diagnostico'] ?? null,
        ]);
    }
    $exRes = ubicacion_examen_resolver_peticion($pdo, $idExamen, $idnumber);
    if (empty($exRes['ok'])) {
        moodle_test_json(array_merge([
            'status' => 'error',
            'paso' => 'enrol',
        ], $exRes));
    }
    $ex = (array) ($exRes['examen'] ?? []);
    if (function_exists('ubicacion_examen_reparar_curso_moodle')) {
        $ex = ubicacion_examen_reparar_curso_moodle($pdo, $ex);
    }
    $idAlumno = (int) ($resolved['id_alumno'] ?? 0);
    $idPlantel = (int) ($resolved['id_plantel'] ?? $idPlantelSesion);
    $mEnsure = moodle_user_ensure_alumno($pdo, $idAlumno, $idPlantel);
    $courseResolved = function_exists('ubicacion_examen_curso_moodle_resolver')
        ? ubicacion_examen_curso_moodle_resolver($ex)
        : ['ok' => false, 'message' => 'Resolver no disponible'];
    $falloUsuario = empty($mEnsure['ok']);
    $falloCurso = empty($courseResolved['ok']);
    $out = [
        'paso' => 'enrol',
        'examen' => $exRes,
        'examen_curso_bd' => $ex,
        'alumno_id' => $idAlumno,
        'moodle_user' => $mEnsure,
        'curso' => $courseResolved,
        'fallo_usuario_moodle' => $falloUsuario,
        'fallo_curso_moodle' => $falloCurso,
        'mapa_configurado' => function_exists('moodle_course_map_by_idnumber')
            && moodle_course_map_by_idnumber() !== [],
    ];
    if ($falloUsuario || $falloCurso) {
        $partes = [];
        if ($falloUsuario) {
            $partes[] = 'Usuario Moodle: ' . ($mEnsure['message'] ?? 'no resuelto');
        }
        if ($falloCurso) {
            $partes[] = 'Curso Moodle: ' . ($courseResolved['message'] ?? 'no resuelto');
        }
        moodle_test_json(array_merge([
            'status' => 'error',
            'message' => implode(' | ', $partes),
        ], $out));
    }
    $enrol = moodle_enrol_user_in_course((int) $mEnsure['id_moodle'], (int) $courseResolved['id']);
    moodle_test_json(array_merge([
        'status' => !empty($enrol['ok']) ? 'ok' : 'error',
        'message' => $enrol['message'] ?? '',
        'enrol' => $enrol,
    ], $out));
}

if ($paso === 'buscar') {
    $ref = function_exists('moodle_test_ref_alumno_desde_request')
        ? moodle_test_ref_alumno_desde_request()
        : trim((string) ($_GET['id_alumno'] ?? ''));
    if ($ref === '') {
        moodle_test_json(['status' => 'error', 'message' => 'Indique control o id_alumno', 'paso' => 'buscar']);
    }
    $idPlantelSesion = plantel_scope_id($pdo);
    $resolved = moodle_alumno_resolver($pdo, $ref, $idPlantelSesion);
    if (empty($resolved['ok'])) {
        moodle_test_json([
            'status' => 'error',
            'message' => (string) ($resolved['message'] ?? 'Alumno no encontrado'),
            'paso' => 'buscar',
            'diagnostico' => $resolved['diagnostico'] ?? null,
        ]);
    }
    $al = (array) ($resolved['alumno'] ?? []);
    $payload = function_exists('moodle_user_payload_from_alumno')
        ? moodle_user_payload_from_alumno($al)
        : [];
    $users = function_exists('moodle_user_find_by_username_or_email')
        ? moodle_user_find_by_username_or_email((string) ($payload['username'] ?? ''), (string) ($payload['email'] ?? ''))
        : [];
    moodle_test_json([
        'status' => 'ok',
        'paso' => 'buscar',
        'id_alumno' => (int) ($resolved['id_alumno'] ?? 0),
        'numero_control' => $al['numero_control'] ?? null,
        'payload' => $payload,
        'existe_en_moodle' => !empty($users),
        'usuarios_moodle' => $users,
    ]);
}

if ($paso === 'payload' || $paso === 'crear') {
    $ref = function_exists('moodle_test_ref_alumno_desde_request')
        ? moodle_test_ref_alumno_desde_request()
        : trim((string) ($_GET['id_alumno'] ?? ''));
    $idPlantelSesion = plantel_scope_id($pdo);
    if ($ref === '') {
        moodle_test_json([
            'status' => 'error',
            'message' => 'Indique id_alumno o control (número de control del alumno)',
            'paso' => $paso,
            'ejemplo' => '?paso=' . $paso . '&control=14578',
        ]);
    }
    if (!function_exists('moodle_alumno_resolver')) {
        moodle_test_json(['status' => 'error', 'message' => 'moodle_helper.php no disponible', 'paso' => $paso]);
    }

    $resolved = moodle_alumno_resolver($pdo, $ref, $idPlantelSesion);
    if (empty($resolved['ok'])) {
        moodle_test_json([
            'status' => 'error',
            'message' => (string) ($resolved['message'] ?? 'Alumno no encontrado'),
            'paso' => $paso,
            'ref' => $ref,
            'id_plantel_sesion' => $idPlantelSesion,
            'diagnostico' => $resolved['diagnostico'] ?? null,
            'hint' => 'El listado muestra el número de control en la primera columna; el id interno está en la URL al abrir el alumno (?id=NNN). Pruebe ?control=' . urlencode($ref),
        ]);
    }

    $idAlumno = (int) ($resolved['id_alumno'] ?? 0);
    $idPlantel = (int) ($resolved['id_plantel'] ?? $idPlantelSesion);
    $al = (array) ($resolved['alumno'] ?? []);

    if ($paso === 'payload') {
        $payload = function_exists('moodle_user_payload_from_alumno')
            ? moodle_user_payload_from_alumno($al)
            : null;
        moodle_test_json([
            'status' => 'ok',
            'alumno' => $al,
            'resuelto_por' => $resolved['resuelto_por'] ?? null,
            'id_plantel_usado' => $idPlantel,
            'payload' => $payload,
            'moodle_enabled' => function_exists('moodle_enabled') && moodle_enabled(),
            'paso' => 'payload',
        ]);
    }

    if (!function_exists('moodle_user_ensure_alumno')) {
        moodle_test_json(['status' => 'error', 'message' => 'moodle_helper.php no disponible', 'paso' => 'crear']);
    }
    $res = moodle_user_ensure_alumno($pdo, $idAlumno, $idPlantel);
    $payload = function_exists('moodle_user_payload_from_alumno')
        ? moodle_user_payload_from_alumno($al)
        : null;
    $out = [
        'status' => !empty($res['ok']) ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
        'id_alumno' => $idAlumno,
        'numero_control' => $al['numero_control'] ?? null,
        'id_plantel_usado' => $idPlantel,
        'resuelto_por' => $resolved['resuelto_por'] ?? null,
        'id_moodle' => $res['id_moodle'] ?? null,
        'moodle_raw' => $res['moodle_raw'] ?? null,
        'moodle_intento' => $res['moodle_intento'] ?? null,
        'payload' => $res['payload'] ?? $payload,
        'paso' => 'crear',
    ];
    if (empty($res['ok']) && function_exists('moodle_es_error_bd') && moodle_es_error_bd([
        'message' => $res['message'] ?? '',
        'raw' => $res['moodle_raw'] ?? null,
    ])) {
        $out['tipo'] = 'moodle_bd';
        $out['hint'] = function_exists('moodle_hint_error_bd') ? moodle_hint_error_bd() : null;
    }
    moodle_test_json($out);
}

moodle_test_json([
    'status' => 'error',
    'message' => 'Paso no reconocido. Use paso=conexion, paso=config, paso=sesion, paso=cursos, paso=payload&id_alumno=N o paso=crear&id_alumno=N',
]);
