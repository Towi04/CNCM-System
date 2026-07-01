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

/** @return list<array<string, mixed>> */
function moodle_inscripcion_cursos_opciones(?int $idEspecialidad = null): array
{
    if (!function_exists('moodle_list_courses')) {
        return [];
    }
    $items = moodle_list_courses();
    $out = [];
    $faseCursos = [];
    if ($idEspecialidad > 0 && function_exists('fase_ensure_moodle_columns')) {
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            fase_ensure_moodle_columns($pdo);
            $st = $pdo->prepare(
                'SELECT moodle_course_id, clave_fase, nombre_fase
                 FROM especialidad_fases
                 WHERE id_especialidad = ? AND activo = 1
                   AND moodle_course_id IS NOT NULL AND moodle_course_id > 0
                 ORDER BY orden ASC'
            );
            $st->execute([$idEspecialidad]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $faseCursos[(int) $f['moodle_course_id']] = $f;
            }
        }
    }

    foreach ($items as $c) {
        if (!is_array($c)) {
            continue;
        }
        $id = (int) ($c['id'] ?? 0);
        if ($id <= 1) {
            continue;
        }
        $fase = $faseCursos[$id] ?? null;
        $out[] = [
            'id' => $id,
            'shortname' => (string) ($c['shortname'] ?? ''),
            'fullname' => (string) ($c['fullname'] ?? ''),
            'idnumber' => (string) ($c['idnumber'] ?? ''),
            'fase' => $fase ? ($fase['clave_fase'] ?? $fase['nombre_fase'] ?? '') : '',
        ];
    }

    usort($out, static function ($a, $b) {
        return strcasecmp((string) ($a['fullname'] ?? ''), (string) ($b['fullname'] ?? ''));
    });

    return $out;
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

    if (function_exists('cuenta_alumno_enrol_curso')) {
        $res = cuenta_alumno_enrol_curso($pdo, $idAlumno, $idPlantel, $courseId);
        if (!empty($res['ok']) && $idEspecialidad > 0 && function_exists('fase_ensure_moodle_columns')) {
            fase_ensure_moodle_columns($pdo);
            $idMoodle = (int) ($res['id_moodle'] ?? 0);
            $pdo->prepare(
                'INSERT INTO alumno_moodle_curso (id_alumno, id_especialidad, id_fase, moodle_course_id, moodle_user_id)
                 VALUES (?,?,NULL,?,?)
                 ON DUPLICATE KEY UPDATE moodle_user_id = VALUES(moodle_user_id)'
            )->execute([$idAlumno, $idEspecialidad, $courseId, $idMoodle > 0 ? $idMoodle : null]);
        }

        return [
            'ok' => !empty($res['ok']),
            'message' => (string) ($res['message'] ?? 'Listo'),
        ];
    }

    return ['ok' => false, 'message' => 'Módulo de cuentas no disponible'];
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
