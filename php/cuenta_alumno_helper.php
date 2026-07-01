<?php
/**
 * Gestión de cuentas digitales del alumno (Google, HAY, Moodle) desde la ficha.
 */

/** Recepción, caja, supervisor, gerente y profesor pueden gestionar cuentas de alumnos. */
function cuenta_alumno_puede_gestionar(): bool
{
    if (function_exists('usuario_puede_gestionar_alumnos') && usuario_puede_gestionar_alumnos()) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_caja');
}

/** @return array<string, mixed> */
function cuenta_alumno_fila(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    $st = $pdo->prepare(
        'SELECT id_alumno, id_plantel, numero_control, nombres, apellido_paterno, apellido_materno,
                email, id_usuario, moodle_user_id
         FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$idAlumno, $idPlantel]);
    $al = $st->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    return ['ok' => true, 'alumno' => $al];
}

/** Estado de Google, HAY y Moodle para la ficha del alumno. */
function cuenta_alumno_estado(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    $base = cuenta_alumno_fila($pdo, $idAlumno, $idPlantel);
    if (empty($base['ok'])) {
        return $base;
    }
    $al = (array) $base['alumno'];
    $nc = trim((string) ($al['numero_control'] ?? ''));
    $emailGuardado = strtolower(trim((string) ($al['email'] ?? '')));
    $emailInst = $emailGuardado !== '' ? $emailGuardado : (
        $nc !== '' && function_exists('cuenta_email_alumno')
            ? cuenta_email_alumno($nc)
            : ($nc !== '' ? strtolower($nc) . '@' . INSTITUTIONAL_EMAIL_DOMAIN : '')
    );

    $passInicial = function_exists('cuenta_password_inicial') ? cuenta_password_inicial() : 'Cncm*1234';

    // Google
    $googleCfg = function_exists('google_config_status') ? google_config_status() : ['ok' => false];
    $googleActivo = false;
    $googleMsg = '';
    if (!empty($googleCfg['ok']) && $emailInst !== '') {
        $gEx = google_usuario_existe($emailInst);
        $googleActivo = !empty($gEx['existe']);
        $googleMsg = $googleActivo ? 'Cuenta activa' : 'No encontrada en Google Workspace';
        if (empty($gEx['ok']) && !empty($gEx['message'])) {
            $googleMsg = (string) $gEx['message'];
        }
    } elseif (empty($googleCfg['ok'])) {
        $googleMsg = (string) ($googleCfg['message'] ?? 'Google no configurado');
    }

    // HAY
    $idUsuario = (int) ($al['id_usuario'] ?? 0);
    $hayUser = null;
    if ($idUsuario > 0) {
        $stU = $pdo->prepare('SELECT id_usuario, username, email, suspendido FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stU->execute([$idUsuario]);
        $hayUser = $stU->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Moodle
    $moodleCfg = function_exists('moodle_enabled') && moodle_enabled();
    $idMoodle = (int) ($al['moodle_user_id'] ?? 0);
    $moodleVinculado = $idMoodle > 0;
    $moodleUsername = null;
    $moodleMsg = '';
    if ($moodleCfg) {
        if ($idMoodle > 0 && function_exists('moodle_user_get_by_id')) {
            $mf = moodle_user_get_by_id($idMoodle);
            if (!empty($mf['ok'])) {
                $mu = (array) ($mf['user'] ?? []);
                $moodleUsername = (string) ($mu['username'] ?? '');
                $moodleMsg = 'Moodle vinculado #' . $idMoodle;
            } else {
                $moodleMsg = 'ID vinculado #' . $idMoodle;
            }
        }
        if ($moodleUsername === null && function_exists('moodle_user_payload_from_alumno')) {
            $payload = moodle_user_payload_from_alumno($al);
            $moodleUsername = (string) ($payload['username'] ?? '');
            if ($idMoodle <= 0 && function_exists('moodle_user_id_from_control_map')) {
                $idMoodle = moodle_user_id_from_control_map($nc);
            }
            if ($idMoodle <= 0 && function_exists('moodle_user_find_for_payload')) {
                $find = moodle_user_find_for_payload($payload);
                $idMoodle = (int) ($find['id_moodle'] ?? 0);
            }
            if ($moodleMsg === '') {
                $moodleMsg = $idMoodle > 0
                    ? 'Moodle #' . $idMoodle . ' (detectado, use Vincular para guardar)'
                    : 'Sin usuario Moodle registrado';
            }
        }
    } else {
        $moodleMsg = 'Moodle no configurado';
    }

    // Cursos Moodle inscritos (si hay id)
    $cursosMoodle = [];
    if ($idMoodle > 0 && function_exists('moodle_api_call')) {
        $resC = moodle_api_call('core_enrol_get_users_courses', ['userid' => $idMoodle]);
        if (!empty($resC['ok']) && is_array($resC['data'])) {
            foreach ($resC['data'] as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $cursosMoodle[] = [
                    'id' => (int) ($c['id'] ?? 0),
                    'shortname' => (string) ($c['shortname'] ?? ''),
                    'fullname' => (string) ($c['fullname'] ?? ''),
                ];
            }
        }
    }

    return [
        'ok' => true,
        'id_alumno' => $idAlumno,
        'numero_control' => $nc,
        'email_institucional' => $emailInst,
        'password_inicial' => $passInicial,
        'puede_gestionar' => cuenta_alumno_puede_gestionar(),
        'google' => [
            'configurado' => !empty($googleCfg['ok']),
            'activo' => $googleActivo,
            'email' => $emailInst,
            'mensaje' => $googleMsg,
            'vinculado' => $emailGuardado !== '' && $googleActivo,
        ],
        'hay' => [
            'activo' => $idUsuario > 0 && $hayUser,
            'id_usuario' => $idUsuario > 0 ? $idUsuario : null,
            'username' => $hayUser['username'] ?? ($nc !== '' ? $nc : null),
            'email' => $hayUser['email'] ?? $emailInst,
            'suspendido' => !empty($hayUser['suspendido']),
            'mensaje' => $idUsuario > 0 ? 'Cuenta portal HAY activa' : 'Sin usuario en el portal HAY',
        ],
        'moodle' => [
            'configurado' => $moodleCfg,
            'activo' => $idMoodle > 0,
            'id_moodle' => $idMoodle > 0 ? $idMoodle : null,
            'username' => $moodleUsername !== null && $moodleUsername !== '' ? $moodleUsername : null,
            'mensaje' => $moodleMsg,
            'vinculado_en_hay' => $moodleVinculado,
            'cursos' => $cursosMoodle,
        ],
    ];
}

/**
 * Crea o repara una cuenta: google | hay | moodle | all.
 *
 * @return array<string, mixed>
 */
function cuenta_alumno_provisionar(PDO $pdo, int $idAlumno, int $idPlantel, string $servicio = 'all'): array
{
    if (!cuenta_alumno_puede_gestionar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }

    $base = cuenta_alumno_fila($pdo, $idAlumno, $idPlantel);
    if (empty($base['ok'])) {
        return $base;
    }
    $al = (array) $base['alumno'];
    $nc = trim((string) ($al['numero_control'] ?? ''));
    if ($nc === '') {
        return ['ok' => false, 'message' => 'El alumno no tiene número de control. Complete la inscripción primero.'];
    }

    $servicio = strtolower(trim($servicio));
    $resultados = [];

    if ($servicio === 'all') {
        if (!function_exists('usuario_crear_cuenta_alumno')) {
            return ['ok' => false, 'message' => 'Helper de usuarios no disponible'];
        }
        $res = usuario_crear_cuenta_alumno($pdo, $idAlumno, $idPlantel);
        return [
            'ok' => !empty($res['ok']) || !empty($res['vinculado']),
            'message' => (string) ($res['message'] ?? 'Cuentas provisionadas'),
            'detalle' => ['all' => $res],
            'estado' => cuenta_alumno_estado($pdo, $idAlumno, $idPlantel),
            'moodle_ok' => !empty($res['moodle_ok']),
        ];
    }

    if ($servicio === 'hay') {
        if (!empty($al['id_usuario'])) {
            $resultados['hay'] = ['ok' => true, 'message' => 'Ya tiene cuenta HAY'];
        } elseif (function_exists('usuario_crear_cuenta_alumno')) {
            $res = usuario_crear_cuenta_alumno($pdo, $idAlumno, $idPlantel);
            $resultados['hay'] = $res;
            if (empty($res['ok']) && empty($res['vinculado'])) {
                return ['ok' => false, 'message' => (string) ($res['message'] ?? 'Error HAY'), 'detalle' => $resultados];
            }
        }
    }

    if ($servicio === 'google') {
        if (!function_exists('cuenta_google_asegurar')) {
            return ['ok' => false, 'message' => 'Google no configurado'];
        }
        $email = function_exists('cuenta_email_alumno') ? cuenta_email_alumno($nc) : strtolower($nc) . '@' . INSTITUTIONAL_EMAIL_DOMAIN;
        $nombre = trim((string) ($al['nombres'] ?? ''));
        $apellido = trim((string) (($al['apellido_paterno'] ?? '') . ' ' . ($al['apellido_materno'] ?? '')));
        $res = cuenta_google_asegurar($nombre, $apellido, $email, false);
        if (!empty($res['ok'])) {
            $pdo->prepare('UPDATE alumnos SET email = ? WHERE id_alumno = ?')->execute([$email, $idAlumno]);
        }
        $resultados['google'] = $res;
        if (empty($res['ok'])) {
            return ['ok' => false, 'message' => (string) ($res['message'] ?? 'Error Google'), 'detalle' => $resultados];
        }
    }

    if ($servicio === 'moodle') {
        if (!function_exists('moodle_user_ensure_alumno')) {
            return ['ok' => false, 'message' => 'Moodle no disponible'];
        }
        $res = moodle_user_ensure_alumno($pdo, $idAlumno, $idPlantel);
        $resultados['moodle'] = $res;
        if (empty($res['ok'])) {
            return [
                'ok' => false,
                'message' => (string) ($res['message'] ?? 'Error Moodle'),
                'detalle' => $resultados,
                'hint' => $res['hint'] ?? null,
            ];
        }
    }

    $msgs = [];
    foreach ($resultados as $k => $r) {
        if (!empty($r['message'])) {
            $msgs[] = ucfirst($k) . ': ' . $r['message'];
        }
    }

    return [
        'ok' => true,
        'message' => $msgs !== [] ? implode(' · ', $msgs) : 'Operación completada',
        'detalle' => $resultados,
        'estado' => cuenta_alumno_estado($pdo, $idAlumno, $idPlantel),
    ];
}

/**
 * Restablece contraseña: hay | google | moodle | all.
 *
 * @return array<string, mixed>
 */
function cuenta_alumno_reset_password(PDO $pdo, int $idAlumno, int $idPlantel, string $servicio = 'all'): array
{
    if (!cuenta_alumno_puede_gestionar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }

    $base = cuenta_alumno_fila($pdo, $idAlumno, $idPlantel);
    if (empty($base['ok'])) {
        return $base;
    }
    $al = (array) $base['alumno'];
    $nc = trim((string) ($al['numero_control'] ?? ''));
    $pass = function_exists('cuenta_password_inicial') ? cuenta_password_inicial() : 'Cncm*1234';
    $servicio = strtolower(trim($servicio));
    $resultados = [];

    if ($servicio === 'all' || $servicio === 'hay') {
        if (empty($al['id_usuario'])) {
            $resultados['hay'] = ['ok' => false, 'message' => 'Sin cuenta HAY'];
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $pdo->prepare(
                'UPDATE usuarios SET password = ?, debe_cambiar_password = 1, suspendido = 0 WHERE id_usuario = ?'
            )->execute([$hash, (int) $al['id_usuario']]);
            $resultados['hay'] = ['ok' => true, 'message' => 'Contraseña HAY restablecida a ' . $pass];
        }
    }

    if ($servicio === 'all' || $servicio === 'google') {
        $email = $nc !== '' && function_exists('cuenta_email_alumno')
            ? cuenta_email_alumno($nc)
            : strtolower(trim((string) ($al['email'] ?? '')));
        if ($email === '' || !function_exists('google_reset_password')) {
            $resultados['google'] = ['ok' => false, 'message' => 'Google no disponible o sin correo'];
        } else {
            $resultados['google'] = google_reset_password($email, $pass);
        }
    }

    if ($servicio === 'all' || $servicio === 'moodle') {
        if (!function_exists('moodle_user_reset_password')) {
            $resultados['moodle'] = ['ok' => false, 'message' => 'Moodle no disponible'];
        } else {
            $username = '';
            $idM = (int) ($al['moodle_user_id'] ?? 0);
            if ($idM > 0 && function_exists('moodle_user_get_by_id')) {
                $mf = moodle_user_get_by_id($idM);
                $username = (string) (($mf['user']['username'] ?? '') ?: '');
            }
            if ($username === '') {
                $username = moodle_sanitize_username($nc);
            }
            if ($username === '' && function_exists('moodle_user_payload_from_alumno')) {
                $payload = moodle_user_payload_from_alumno($al);
                $username = (string) ($payload['username'] ?? '');
            }
            if ($username === '') {
                $resultados['moodle'] = ['ok' => false, 'message' => 'Sin username Moodle vinculado'];
            } else {
                $resultados['moodle'] = moodle_user_reset_password($username, $pass);
            }
        }
    }

    $ok = true;
    $errores = [];
    $exitos = [];
    foreach ($resultados as $k => $r) {
        if (!empty($r['ok'])) {
            $exitos[] = $k;
        } else {
            $errores[] = $k . ': ' . ($r['message'] ?? 'Error');
        }
    }
    if ($servicio === 'all') {
        $ok = $exitos !== [];
    } else {
        $ok = $errores === [];
    }

    return [
        'ok' => $ok,
        'message' => $ok
            ? 'Contraseña restablecida a ' . $pass . ' (' . implode(', ', array_keys(array_filter($resultados, static fn ($r) => !empty($r['ok'])))) . ')'
            : implode(' · ', $errores),
        'password' => $pass,
        'detalle' => $resultados,
    ];
}

/** Inscribe al alumno en un curso Moodle por ID interno. */
function cuenta_alumno_enrol_curso(PDO $pdo, int $idAlumno, int $idPlantel, int $courseIdMoodle): array
{
    if (!cuenta_alumno_puede_gestionar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    if ($courseIdMoodle <= 1) {
        return ['ok' => false, 'message' => 'ID de curso Moodle inválido'];
    }
    if (!function_exists('moodle_user_ensure_alumno') || !function_exists('moodle_enrol_user_in_course')) {
        return ['ok' => false, 'message' => 'Moodle no disponible'];
    }

    $mEnsure = moodle_user_ensure_alumno($pdo, $idAlumno, $idPlantel);
    if (empty($mEnsure['ok'])) {
        return [
            'ok' => false,
            'message' => 'Primero cree el usuario Moodle: ' . ($mEnsure['message'] ?? ''),
            'hint' => $mEnsure['hint'] ?? null,
        ];
    }
    $idMoodle = (int) ($mEnsure['id_moodle'] ?? 0);
    if ($idMoodle <= 0) {
        return ['ok' => false, 'message' => 'No se obtuvo id Moodle del alumno'];
    }

    $enrol = moodle_enrol_user_in_course($idMoodle, $courseIdMoodle);

    return [
        'ok' => !empty($enrol['ok']),
        'message' => (string) ($enrol['message'] ?? ''),
        'id_moodle' => $idMoodle,
        'course_id' => $courseIdMoodle,
        'enrol' => $enrol,
        'estado' => cuenta_alumno_estado($pdo, $idAlumno, $idPlantel),
    ];
}

/** Inscribe al alumno en el curso de un examen de ubicación (sin crear solicitud de ubicación). */
function cuenta_alumno_enrol_examen(PDO $pdo, int $idAlumno, int $idPlantel, int $idExamen): array
{
    if (!cuenta_alumno_puede_gestionar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    if (!function_exists('ubicacion_examen_obtener')) {
        return ['ok' => false, 'message' => 'Módulo de ubicación no disponible'];
    }

    if (function_exists('ubicacion_examen_ensure_schema')) {
        ubicacion_examen_ensure_schema($pdo);
    }

    $ex = ubicacion_examen_obtener($pdo, $idExamen);
    if (!$ex || !(int) ($ex['activo'] ?? 0)) {
        return ['ok' => false, 'message' => 'Examen de ubicación no encontrado o inactivo'];
    }

    $courseResolved = function_exists('ubicacion_examen_curso_moodle_resolver')
        ? ubicacion_examen_curso_moodle_resolver($ex)
        : ['ok' => false, 'message' => 'Resolver no disponible'];
    $courseId = (int) ($courseResolved['id'] ?? (int) ($ex['moodle_course_id'] ?? 0));
    if ($courseId <= 1) {
        return [
            'ok' => false,
            'message' => (string) ($courseResolved['message'] ?? 'Curso Moodle del examen no resuelto'),
        ];
    }

    $res = cuenta_alumno_enrol_curso($pdo, $idAlumno, $idPlantel, $courseId);
    if (!empty($res['ok'])) {
        $res['message'] = 'Inscrito al examen «' . ($ex['nombre'] ?? '') . '» (curso Moodle #' . $courseId . ')';
        $res['examen'] = $ex;
    }

    return $res;
}

/** Catálogo de exámenes de ubicación para el selector del perfil. */
function cuenta_alumno_examenes_opciones(PDO $pdo, ?int $idEspecialidad = null): array
{
    if (!function_exists('ubicacion_examen_listar')) {
        return [];
    }
    if (function_exists('ubicacion_examen_ensure_schema')) {
        ubicacion_examen_ensure_schema($pdo);
    }

    return ubicacion_examen_listar($pdo, $idEspecialidad, true);
}

/** Cursos Moodle visibles para el selector del perfil. */
function cuenta_alumno_cursos_opciones(): array
{
    if (!function_exists('moodle_list_courses')) {
        return [];
    }
    $items = moodle_list_courses();
    $out = [];
    foreach ($items as $c) {
        if (!is_array($c)) {
            continue;
        }
        $id = (int) ($c['id'] ?? 0);
        if ($id <= 1) {
            continue;
        }
        $out[] = [
            'id' => $id,
            'shortname' => (string) ($c['shortname'] ?? ''),
            'fullname' => (string) ($c['fullname'] ?? ''),
            'idnumber' => (string) ($c['idnumber'] ?? ''),
        ];
    }

    return $out;
}
