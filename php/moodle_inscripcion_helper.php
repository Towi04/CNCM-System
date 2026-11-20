<?php

/**
 * Inscripción manual a cursos Moodle (alumno o grupo completo) — recepción y coordinación.
 */

function moodle_inscripcion_puede_gestionar(): bool
{
    if (function_exists('usuario_puede_gestionar_alumnos') && usuario_puede_gestionar_alumnos()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('menu_caja')) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['coordinador', 'coordinacion', 'admin', 'supervisor', 'director'], true);
}

/**
 * Lista todos los cursos Moodle disponibles (independiente de inscripción HAY).
 * Si se pasa especialidad, anota fases vinculadas (no filtra el catálogo).
 *
 * @return array{cursos:list<array<string,mixed>>, moodle_enabled:bool, message:string, total:int}
 */
function moodle_inscripcion_cursos_opciones_info(?int $idEspecialidad = null): array
{
    if (!function_exists('moodle_enabled') || !moodle_enabled()) {
        return [
            'cursos' => [],
            'moodle_enabled' => false,
            'message' => 'Moodle no está configurado (MOODLE_URL / token).',
            'total' => 0,
        ];
    }
    if (!function_exists('moodle_list_courses')) {
        return [
            'cursos' => [],
            'moodle_enabled' => true,
            'message' => 'Función de listado Moodle no disponible',
            'total' => 0,
        ];
    }

    // Catálogo real de Moodle (sin fallback a mapas HAY/config: recepción debe ver nombres Moodle).
    $items = moodle_list_courses(false);
    $out = [];
    foreach ($items as $c) {
        if (!is_array($c)) {
            continue;
        }
        // Solo cursos que vienen de Moodle (no config HAY)
        if (($c['fuente'] ?? 'moodle') === 'config') {
            continue;
        }
        $id = (int) ($c['id'] ?? 0);
        if ($id <= 1) {
            continue;
        }
        // Preferir visibles; los ocultos al final con etiqueta
        $fullname = trim((string) ($c['fullname'] ?? ''));
        $short = trim((string) ($c['shortname'] ?? ''));
        $nombre = $fullname !== '' ? $fullname : ($short !== '' ? $short : '');
        if ($nombre === '') {
            continue; // sin nombre usable para recepción
        }
        $out[] = [
            'id' => $id,
            'shortname' => $short,
            'fullname' => $nombre,
            'label' => $nombre, // solo nombre para el desplegable
            'idnumber' => (string) ($c['idnumber'] ?? ''),
            'visible' => (int) ($c['visible'] ?? 1),
            'fuente' => 'moodle',
        ];
    }

    usort($out, static function ($a, $b) {
        $va = (int) ($a['visible'] ?? 1) === 1 ? 0 : 1;
        $vb = (int) ($b['visible'] ?? 1) === 1 ? 0 : 1;
        if ($va !== $vb) {
            return $va <=> $vb;
        }

        return strcasecmp((string) ($a['fullname'] ?? ''), (string) ($b['fullname'] ?? ''));
    });

    $msg = $out === []
        ? 'No se pudieron listar cursos desde Moodle. En Moodle → Servicios web, el token necesita: core_course_get_courses (o core_course_search_courses / core_course_get_categories).'
        : count($out) . ' curso(s) disponibles en Moodle.';

    return [
        'cursos' => $out,
        'moodle_enabled' => true,
        'message' => $msg,
        'total' => count($out),
    ];
}

/** @return list<array<string, mixed>> */
function moodle_inscripcion_cursos_opciones(?int $idEspecialidad = null): array
{
    return moodle_inscripcion_cursos_opciones_info($idEspecialidad)['cursos'];
}

/** Cursos Moodle sugeridos para un grupo (fase actual + bloques anteriores). */
function moodle_inscripcion_cursos_para_grupo(PDO $pdo, int $idGrupo): array
{
    $st = $pdo->prepare(
        'SELECT g.id_especialidad, g.id_fase_actual, e.nombre AS especialidad_nombre
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_grupo = ? LIMIT 1'
    );
    $st->execute([$idGrupo]);
    $g = $st->fetch(PDO::FETCH_ASSOC);
    if (!$g) {
        return ['ok' => false, 'message' => 'Grupo no encontrado', 'cursos' => []];
    }
    $idEsp = (int) ($g['id_especialidad'] ?? 0);
    $cursos = moodle_inscripcion_cursos_opciones($idEsp > 0 ? $idEsp : null);

    return [
        'ok' => true,
        'id_especialidad' => $idEsp,
        'especialidad' => (string) ($g['especialidad_nombre'] ?? ''),
        'cursos' => $cursos,
    ];
}

/**
 * Inscribe a un alumno en un curso Moodle (crea usuario si hace falta).
 * @return array{ok: bool, message: string}
 */
function moodle_inscripcion_alumno_curso(
    PDO $pdo,
    int $idAlumno,
    int $courseId,
    ?int $idPlantel = null,
    ?int $idEspecialidad = null
): array {
    if (!moodle_inscripcion_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    if ($idAlumno <= 0 || $courseId <= 1) {
        return ['ok' => false, 'message' => 'Alumno y curso son obligatorios'];
    }
    if (!function_exists('moodle_enabled') || !moodle_enabled()) {
        return ['ok' => false, 'message' => 'Moodle no está configurado'];
    }
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);

    // Enrol directo (no pasar por cuenta_alumno_enrol_curso: su permiso es más estrecho).
    if (!function_exists('moodle_user_ensure_alumno') || !function_exists('moodle_enrol_user_in_course')) {
        return ['ok' => false, 'message' => 'Módulo Moodle no disponible'];
    }
    $mEnsure = moodle_user_ensure_alumno($pdo, $idAlumno, $idPlantel);
    if (empty($mEnsure['ok'])) {
        return [
            'ok' => false,
            'message' => 'No se pudo asegurar usuario Moodle: ' . ($mEnsure['message'] ?? ''),
        ];
    }
    $idMoodle = (int) ($mEnsure['id_moodle'] ?? 0);
    if ($idMoodle <= 0) {
        return ['ok' => false, 'message' => 'No se obtuvo id Moodle del alumno'];
    }
    $enrol = moodle_enrol_user_in_course($idMoodle, $courseId);
    if (empty($enrol['ok'])) {
        return [
            'ok' => false,
            'message' => (string) ($enrol['message'] ?? 'No se pudo inscribir en Moodle'),
        ];
    }

    if (function_exists('fase_ensure_moodle_columns')) {
        fase_ensure_moodle_columns($pdo);
        $idEspStore = $idEspecialidad !== null && $idEspecialidad > 0 ? $idEspecialidad : 0;
        $pdo->prepare(
            'INSERT INTO alumno_moodle_curso
                (id_alumno, id_especialidad, id_fase, moodle_course_id, moodle_user_id, activo, desactivado_en, desactivado_por)
             VALUES (?,?,NULL,?,?,1,NULL,NULL)
             ON DUPLICATE KEY UPDATE
                moodle_user_id = VALUES(moodle_user_id),
                activo = 1,
                desactivado_en = NULL,
                desactivado_por = NULL'
        )->execute([$idAlumno, $idEspStore, $courseId, $idMoodle]);
    }

    return [
        'ok' => true,
        'message' => (string) ($enrol['message'] ?? 'Inscrito en curso Moodle'),
        'id_moodle' => $idMoodle,
        'course_id' => $courseId,
    ];
}

/**
 * Estado Moodle del alumno: cursos activos (con progreso), desactivados y cuenta.
 *
 * @return array<string, mixed>
 */
function moodle_inscripcion_alumno_estado(PDO $pdo, int $idAlumno, ?int $idPlantel = null): array
{
    if ($idAlumno <= 0) {
        return ['ok' => false, 'message' => 'Alumno inválido', 'activos' => [], 'desactivados' => []];
    }
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    if (function_exists('fase_ensure_moodle_columns')) {
        fase_ensure_moodle_columns($pdo);
    }

    $stAl = $pdo->prepare(
        'SELECT id_alumno, moodle_user_id, numero_control, nombres, apellido_paterno
         FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1'
    );
    $stAl->execute([$idAlumno, $idPlantel]);
    $al = $stAl->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado', 'activos' => [], 'desactivados' => []];
    }

    $moodleId = (int) ($al['moodle_user_id'] ?? 0);
    if ($moodleId <= 0 && function_exists('moodle_user_get_by_field') && !empty($al['numero_control'])) {
        $found = moodle_user_get_by_field('username', (string) $al['numero_control']);
        if (!empty($found['id'])) {
            $moodleId = (int) $found['id'];
        }
    }

    $liveById = [];
    if ($moodleId > 0 && function_exists('moodle_user_courses_with_progress')) {
        foreach (moodle_user_courses_with_progress($moodleId) as $c) {
            $liveById[(int) $c['id']] = $c;
            // Sincroniza registro local como activo
            try {
                $pdo->prepare(
                    'INSERT INTO alumno_moodle_curso
                        (id_alumno, id_especialidad, id_fase, moodle_course_id, moodle_user_id, activo)
                     VALUES (?,0,NULL,?,?,1)
                     ON DUPLICATE KEY UPDATE
                        moodle_user_id = VALUES(moodle_user_id),
                        activo = 1,
                        desactivado_en = NULL,
                        desactivado_por = NULL'
                )->execute([$idAlumno, (int) $c['id'], $moodleId]);
            } catch (Throwable $e) {
                // ignore sync errors
            }
        }
    }

    $st = $pdo->prepare(
        'SELECT amc.*, e.nombre AS especialidad_nombre
         FROM alumno_moodle_curso amc
         LEFT JOIN especialidades e ON e.id_especialidad = amc.id_especialidad
         WHERE amc.id_alumno = ?
         ORDER BY amc.activo DESC, amc.creado_en DESC'
    );
    $st->execute([$idAlumno]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $activos = [];
    $desactivados = [];
    $seenCourse = [];

    foreach ($rows as $row) {
        $cid = (int) ($row['moodle_course_id'] ?? 0);
        if ($cid <= 1) {
            continue;
        }
        $live = $liveById[$cid] ?? null;
        $activoLocal = (int) ($row['activo'] ?? 1) === 1;
        $enMoodle = $live !== null;
        $nombreLocal = 'Curso #' . $cid;
        $item = [
            'id_registro' => (int) ($row['id'] ?? 0),
            'moodle_course_id' => $cid,
            'nombre' => $live['nombre'] ?? $nombreLocal,
            'corto' => $live['corto'] ?? '',
            'url' => $live['url'] ?? '',
            'progreso' => $live['progreso'] ?? null,
            'completado' => !empty($live['completado']),
            'ultimo_acceso' => $live['ultimo_acceso'] ?? null,
            'id_especialidad' => (int) ($row['id_especialidad'] ?? 0),
            'especialidad' => (string) ($row['especialidad_nombre'] ?? ''),
            'activo' => $enMoodle ? 1 : 0,
            'en_moodle' => $enMoodle,
            'creado_en' => $row['creado_en'] ?? null,
            'desactivado_en' => $row['desactivado_en'] ?? null,
        ];
        if (isset($seenCourse[$cid])) {
            continue;
        }
        if ($enMoodle) {
            // Si Moodle aún lo tiene, cuenta como activo (aunque el flag local diga lo contrario).
            if (!$activoLocal) {
                try {
                    $pdo->prepare(
                        'UPDATE alumno_moodle_curso SET activo = 1, desactivado_en = NULL, desactivado_por = NULL
                         WHERE id_alumno = ? AND moodle_course_id = ?'
                    )->execute([$idAlumno, $cid]);
                } catch (Throwable $e) {
                    // ignore
                }
            }
            $activos[] = $item;
            $seenCourse[$cid] = true;
        } else {
            $desactivados[] = $item;
            $seenCourse[$cid] = true;
        }
    }

    // Cursos en Moodle sin fila local (por si el INSERT falló)
    foreach ($liveById as $cid => $live) {
        if (isset($seenCourse[$cid])) {
            continue;
        }
        $activos[] = [
            'id_registro' => 0,
            'moodle_course_id' => $cid,
            'nombre' => $live['nombre'],
            'corto' => $live['corto'],
            'url' => $live['url'],
            'progreso' => $live['progreso'],
            'completado' => !empty($live['completado']),
            'ultimo_acceso' => $live['ultimo_acceso'],
            'id_especialidad' => 0,
            'especialidad' => '',
            'activo' => 1,
            'en_moodle' => true,
            'creado_en' => null,
            'desactivado_en' => null,
        ];
        $seenCourse[$cid] = true;
    }

    return [
        'ok' => true,
        'message' => $moodleId > 0 ? 'OK' : 'Sin cuenta Moodle vinculada (se creará al inscribir).',
        'moodle_enabled' => function_exists('moodle_enabled') && moodle_enabled(),
        'moodle_user_id' => $moodleId,
        'moodle_url' => defined('MOODLE_URL') ? (string) MOODLE_URL : '',
        'puede_gestionar' => moodle_inscripcion_puede_gestionar(),
        'activos' => $activos,
        'desactivados' => $desactivados,
    ];
}

/**
 * Desactiva (desinscribe) un curso Moodle del alumno y lo deja en historial.
 *
 * @return array{ok:bool,message:string}
 */
function moodle_inscripcion_alumno_desactivar(
    PDO $pdo,
    int $idAlumno,
    int $courseId,
    ?int $idPlantel = null
): array {
    if (!moodle_inscripcion_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    if ($idAlumno <= 0 || $courseId <= 1) {
        return ['ok' => false, 'message' => 'Alumno y curso son obligatorios'];
    }
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    if (function_exists('fase_ensure_moodle_columns')) {
        fase_ensure_moodle_columns($pdo);
    }

    $stAl = $pdo->prepare('SELECT moodle_user_id, numero_control FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1');
    $stAl->execute([$idAlumno, $idPlantel]);
    $al = $stAl->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }
    $moodleId = (int) ($al['moodle_user_id'] ?? 0);
    if ($moodleId <= 0 && function_exists('moodle_user_get_by_field') && !empty($al['numero_control'])) {
        $found = moodle_user_get_by_field('username', (string) $al['numero_control']);
        $moodleId = (int) ($found['id'] ?? 0);
    }

    $unenrolMsg = '';
    if ($moodleId > 0 && function_exists('moodle_unenrol_user_from_course')) {
        $un = moodle_unenrol_user_from_course($moodleId, $courseId);
        if (empty($un['ok'])) {
            // Aun así marcamos historial local; Moodle puede fallar si el enrol no era manual.
            $unenrolMsg = ' (' . ($un['message'] ?? 'aviso Moodle') . ')';
        }
    }

    $idUsuario = (int) ($_SESSION['user_id'] ?? 0);
    $upd = $pdo->prepare(
        'UPDATE alumno_moodle_curso
         SET activo = 0, desactivado_en = NOW(), desactivado_por = ?
         WHERE id_alumno = ? AND moodle_course_id = ?'
    );
    $upd->execute([$idUsuario > 0 ? $idUsuario : null, $idAlumno, $courseId]);
    if ($upd->rowCount() === 0) {
        $pdo->prepare(
            'INSERT INTO alumno_moodle_curso
                (id_alumno, id_especialidad, moodle_course_id, moodle_user_id, activo, desactivado_en, desactivado_por)
             VALUES (?,0,?,?,0,NOW(),?)'
        )->execute([$idAlumno, $courseId, $moodleId > 0 ? $moodleId : null, $idUsuario > 0 ? $idUsuario : null]);
    }

    return [
        'ok' => true,
        'message' => 'Curso desactivado para el alumno' . $unenrolMsg,
    ];
}

/**
 * Inscribe a todos los alumnos activos del grupo en un curso Moodle.
 * @return array{ok: bool, message: string, inscritos: int, omitidos: int, errores: int, detalle: list<string>}
 */
function moodle_inscripcion_grupo_curso(
    PDO $pdo,
    int $idGrupo,
    int $courseId,
    ?int $idPlantel = null
): array {
    if (!moodle_inscripcion_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso', 'inscritos' => 0, 'omitidos' => 0, 'errores' => 0, 'detalle' => []];
    }
    if ($idGrupo <= 0 || $courseId <= 1) {
        return ['ok' => false, 'message' => 'Grupo y curso son obligatorios', 'inscritos' => 0, 'omitidos' => 0, 'errores' => 0, 'detalle' => []];
    }
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);

    $g = $pdo->prepare('SELECT id_plantel, id_especialidad, clave FROM grupos WHERE id_grupo = ? LIMIT 1');
    $g->execute([$idGrupo]);
    $grupo = $g->fetch(PDO::FETCH_ASSOC);
    if (!$grupo || (int) ($grupo['id_plantel'] ?? 0) !== $idPlantel) {
        return ['ok' => false, 'message' => 'Grupo no encontrado', 'inscritos' => 0, 'omitidos' => 0, 'errores' => 0, 'detalle' => []];
    }
    $idEsp = (int) ($grupo['id_especialidad'] ?? 0);

    $st = $pdo->prepare(
        'SELECT ag.id_alumno, a.nombres, a.apellido_paterno
         FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.id_plantel = ?
         WHERE ag.id_grupo = ? AND ag.activo = 1'
    );
    $st->execute([$idPlantel, $idGrupo]);
    $alumnos = $st->fetchAll(PDO::FETCH_ASSOC);

    if ($alumnos === []) {
        return ['ok' => false, 'message' => 'El grupo no tiene alumnos activos', 'inscritos' => 0, 'omitidos' => 0, 'errores' => 0, 'detalle' => []];
    }

    $inscritos = 0;
    $omitidos = 0;
    $errores = 0;
    $detalle = [];

    foreach ($alumnos as $al) {
        $idAlumno = (int) ($al['id_alumno'] ?? 0);
        $nombre = trim(($al['nombres'] ?? '') . ' ' . ($al['apellido_paterno'] ?? ''));
        $res = moodle_inscripcion_alumno_curso($pdo, $idAlumno, $courseId, $idPlantel, $idEsp);
        if (!empty($res['ok'])) {
            if (str_contains(strtolower($res['message'] ?? ''), 'ya inscrito')) {
                $omitidos++;
                $detalle[] = $nombre . ': ya inscrito';
            } else {
                $inscritos++;
            }
        } else {
            $errores++;
            $detalle[] = $nombre . ': ' . ($res['message'] ?? 'Error');
        }
    }

    $clave = (string) ($grupo['clave'] ?? 'grupo');
    $msg = "Grupo {$clave}: {$inscritos} inscrito(s)";
    if ($omitidos > 0) {
        $msg .= ", {$omitidos} ya estaban inscritos";
    }
    if ($errores > 0) {
        $msg .= ", {$errores} error(es)";
    }

    return [
        'ok' => $errores === 0 || $inscritos > 0,
        'message' => $msg,
        'inscritos' => $inscritos,
        'omitidos' => $omitidos,
        'errores' => $errores,
        'detalle' => $detalle,
    ];
}
