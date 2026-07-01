<?php
/**
 * Cursos Moodle por bloque de fases (temario).
 * Defina moodle_course_id en la 1ª fase de cada bloque (ej. fases 1, 5, 9…).
 */

function fase_ensure_moodle_columns(PDO $pdo): void
{
    if (function_exists('fase_ensure_schema')) {
        fase_ensure_schema($pdo);
    }
    plantel_ensure_column($pdo, 'especialidad_fases', 'moodle_course_id', 'INT UNSIGNED NULL', 'activo');
    plantel_ensure_column($pdo, 'especialidad_fases', 'moodle_shortname', 'VARCHAR(80) NULL', 'moodle_course_id');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_moodle_curso (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_especialidad INT UNSIGNED NOT NULL,
            id_fase INT UNSIGNED NULL,
            moodle_course_id INT UNSIGNED NOT NULL,
            moodle_user_id INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_amc_alumno_esp_curso (id_alumno, id_especialidad, moodle_course_id),
            KEY idx_amc_alumno (id_alumno)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** @return array{ok:bool,message:string,id?:int,orden?:int} */
function moodle_fase_datos(PDO $pdo, int $idFase): array
{
    fase_ensure_moodle_columns($pdo);
    $st = $pdo->prepare(
        'SELECT id_fase, id_especialidad, orden, moodle_course_id, moodle_shortname, clave_fase, nombre_fase
         FROM especialidad_fases WHERE id_fase = ? AND activo = 1 LIMIT 1'
    );
    $st->execute([$idFase]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'message' => 'Fase no encontrada'];
    }

    return [
        'ok' => true,
        'message' => 'OK',
        'id' => (int) $row['id_fase'],
        'id_especialidad' => (int) $row['id_especialidad'],
        'orden' => (int) ($row['orden'] ?? 0),
        'moodle_course_id' => (int) ($row['moodle_course_id'] ?? 0) ?: null,
        'fase' => $row,
    ];
}

/**
 * Curso Moodle que aplica a una fase: última fase con curso definido cuyo orden ≤ orden destino.
 */
function moodle_curso_id_para_fase(PDO $pdo, int $idEspecialidad, int $idFase): ?int
{
    fase_ensure_moodle_columns($pdo);

    $target = moodle_fase_datos($pdo, $idFase);
    if (empty($target['ok'])) {
        return null;
    }
    $ordenObj = (int) ($target['orden'] ?? 0);
    if ($ordenObj <= 0) {
        return null;
    }

    $st = $pdo->prepare(
        'SELECT moodle_course_id FROM especialidad_fases
         WHERE id_especialidad = ? AND activo = 1
           AND moodle_course_id IS NOT NULL AND moodle_course_id > 0
           AND orden <= ?
         ORDER BY orden DESC
         LIMIT 1'
    );
    $st->execute([$idEspecialidad, $ordenObj]);
    $id = (int) $st->fetchColumn();

    return $id > 0 ? $id : null;
}

function moodle_alumno_ya_inscrito_curso(PDO $pdo, int $idAlumno, int $idEsp, int $courseId): bool
{
    fase_ensure_moodle_columns($pdo);
    $st = $pdo->prepare(
        'SELECT 1 FROM alumno_moodle_curso
         WHERE id_alumno = ? AND id_especialidad = ? AND moodle_course_id = ? LIMIT 1'
    );
    $st->execute([$idAlumno, $idEsp, $courseId]);

    return (bool) $st->fetchColumn();
}

function moodle_alumno_resolver_user_id(PDO $pdo, int $idAlumno, int $idPlantel): int
{
    if (function_exists('usuario_crear_cuenta_alumno')) {
        $cuenta = usuario_crear_cuenta_alumno($pdo, $idAlumno, $idPlantel);
        if (empty($cuenta['ok']) && empty($cuenta['vinculado'])) {
            return 0;
        }
    }

    $st = $pdo->prepare(
        'SELECT numero_control, nombres, apellido_paterno, apellido_materno, email
         FROM alumnos WHERE id_alumno = ? LIMIT 1'
    );
    $st->execute([$idAlumno]);
    $al = $st->fetch(PDO::FETCH_ASSOC);
    if (!$al || !function_exists('moodle_user_payload_from_alumno')) {
        return 0;
    }

    $payload = moodle_user_payload_from_alumno($al);
    $found = moodle_user_find_by_username_or_email($payload['username'], $payload['email']);

    return (int) ($found[0]['id'] ?? 0);
}

/**
 * Inscribe al alumno en el curso Moodle del bloque de la fase indicada.
 *
 * @return array{ok:bool,message:string,omitido?:bool,course_id?:int}
 */
function moodle_alumno_inscribir_curso_fase(
    PDO $pdo,
    int $idAlumno,
    int $idEspecialidad,
    int $idFase,
    ?int $idPlantel = null
): array {
    if (!function_exists('moodle_enabled') || !moodle_enabled()) {
        return ['ok' => true, 'message' => 'Moodle no configurado', 'omitido' => true];
    }

    fase_ensure_moodle_columns($pdo);
    $idPlantel = $idPlantel ?? plantel_id_activo();

    $courseId = moodle_curso_id_para_fase($pdo, $idEspecialidad, $idFase);
    if ($courseId === null || $courseId <= 0) {
        return ['ok' => true, 'message' => 'Sin curso Moodle para esta fase', 'omitido' => true];
    }

    if (moodle_alumno_ya_inscrito_curso($pdo, $idAlumno, $idEspecialidad, $courseId)) {
        return ['ok' => true, 'message' => 'Ya inscrito en curso Moodle #' . $courseId, 'course_id' => $courseId];
    }

    $moodleUid = moodle_alumno_resolver_user_id($pdo, $idAlumno, $idPlantel);
    if ($moodleUid <= 0) {
        return ['ok' => false, 'message' => 'No se pudo resolver usuario Moodle del alumno'];
    }

    $enrol = moodle_enrol_user_in_course($moodleUid, $courseId);
    if (empty($enrol['ok'])) {
        return [
            'ok' => false,
            'message' => (string) ($enrol['message'] ?? 'Error al inscribir en Moodle'),
            'course_id' => $courseId,
        ];
    }

    $pdo->prepare(
        'INSERT INTO alumno_moodle_curso (id_alumno, id_especialidad, id_fase, moodle_course_id, moodle_user_id)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE id_fase = VALUES(id_fase), moodle_user_id = VALUES(moodle_user_id)'
    )->execute([$idAlumno, $idEspecialidad, $idFase, $courseId, $moodleUid]);

    return [
        'ok' => true,
        'message' => 'Inscrito en curso Moodle #' . $courseId,
        'course_id' => $courseId,
    ];
}

/** Tras asignar grupo: curso según fase de entrada del grupo. */
function moodle_alumno_inscribir_por_grupo(PDO $pdo, int $idAlumno, int $idGrupo): array
{
    $st = $pdo->prepare(
        'SELECT id_especialidad, id_fase_actual FROM grupos WHERE id_grupo = ? LIMIT 1'
    );
    $st->execute([$idGrupo]);
    $g = $st->fetch(PDO::FETCH_ASSOC);
    if (!$g || empty($g['id_especialidad'])) {
        return ['ok' => true, 'message' => 'Grupo sin especialidad', 'omitido' => true];
    }

    $idEsp = (int) $g['id_especialidad'];
    $idFase = (int) ($g['id_fase_actual'] ?? 0);
    if ($idFase <= 0) {
        $st2 = $pdo->prepare(
            'SELECT id_fase FROM especialidad_fases
             WHERE id_especialidad = ? AND activo = 1 ORDER BY orden ASC, id_fase ASC LIMIT 1'
        );
        $st2->execute([$idEsp]);
        $idFase = (int) $st2->fetchColumn();
    }
    if ($idFase <= 0) {
        return ['ok' => true, 'message' => 'Sin fase para Moodle', 'omitido' => true];
    }

    return moodle_alumno_inscribir_curso_fase($pdo, $idAlumno, $idEsp, $idFase);
}

/** Cuando el grupo avanza de fase: inscribir alumnos activos al nuevo bloque si cambió el curso. */
function moodle_grupo_sync_cursos_tras_avance(PDO $pdo, int $idGrupo, int $idFaseNueva): void
{
    if (!function_exists('moodle_enabled') || !moodle_enabled()) {
        return;
    }

    $st = $pdo->prepare(
        'SELECT ag.id_alumno, g.id_especialidad
         FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         WHERE ag.id_grupo = ? AND ag.activo = 1'
    );
    $st->execute([$idGrupo]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idAlumno = (int) ($row['id_alumno'] ?? 0);
        $idEsp = (int) ($row['id_especialidad'] ?? 0);
        if ($idAlumno <= 0 || $idEsp <= 0) {
            continue;
        }
        try {
            moodle_alumno_inscribir_curso_fase($pdo, $idAlumno, $idEsp, $idFaseNueva);
        } catch (Throwable $e) {
            error_log('moodle_grupo_sync_cursos: alumno ' . $idAlumno . ' — ' . $e->getMessage());
        }
    }
}

/** Cobertura de cursos Moodle configurados por especialidad (fases con moodle_course_id). */
function moodle_fase_cobertura_especialidad(PDO $pdo, ?int $idEspecialidad = null): array
{
    fase_ensure_moodle_columns($pdo);
    $sql = 'SELECT e.id_especialidad, e.clave, e.nombre,
                   COUNT(f.id_fase) AS total_fases,
                   SUM(CASE WHEN f.moodle_course_id IS NOT NULL AND f.moodle_course_id > 0 THEN 1 ELSE 0 END) AS fases_con_curso
            FROM especialidades e
            LEFT JOIN especialidad_fases f ON f.id_especialidad = e.id_especialidad AND f.activo = 1
            WHERE e.activo = 1';
    $params = [];
    if ($idEspecialidad > 0) {
        $sql .= ' AND e.id_especialidad = ?';
        $params[] = $idEspecialidad;
    }
    $sql .= ' GROUP BY e.id_especialidad ORDER BY e.orden, e.nombre';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $total = (int) ($r['total_fases'] ?? 0);
        $con = (int) ($r['fases_con_curso'] ?? 0);
        $rows[] = [
            'id_especialidad' => (int) $r['id_especialidad'],
            'clave' => $r['clave'] ?? '',
            'nombre' => $r['nombre'] ?? '',
            'total_fases' => $total,
            'fases_con_curso' => $con,
            'pct' => $total > 0 ? round(100 * $con / $total, 1) : 0,
        ];
    }

    return $rows;
}

/**
 * Sincroniza inscripción Moodle de alumnos activos según fase actual del grupo.
 *
 * @return array{ok:bool,message:string,procesados:int,ok_count:int,err_count:int}
 */
function moodle_plantel_sync_grupos_activos(PDO $pdo, int $idPlantel, ?int $idEspecialidad = null): array
{
    if (!function_exists('moodle_enabled') || !moodle_enabled()) {
        return ['ok' => false, 'message' => 'Moodle no configurado', 'procesados' => 0, 'ok_count' => 0, 'err_count' => 0];
    }
    $sql = 'SELECT g.id_grupo, g.clave, g.id_fase_actual, g.id_especialidad
            FROM grupos g
            WHERE g.id_plantel = ?';
    $params = [$idPlantel];
    if ($idEspecialidad > 0) {
        $sql .= ' AND g.id_especialidad = ?';
        $params[] = $idEspecialidad;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $grupos = $st->fetchAll(PDO::FETCH_ASSOC);
    $procesados = 0;
    $okCount = 0;
    $errCount = 0;
    foreach ($grupos as $g) {
        $idGrupo = (int) ($g['id_grupo'] ?? 0);
        $idFase = (int) ($g['id_fase_actual'] ?? 0);
        if ($idGrupo <= 0 || $idFase <= 0) {
            continue;
        }
        $stA = $pdo->prepare(
            'SELECT ag.id_alumno FROM alumno_grupos ag WHERE ag.id_grupo = ? AND ag.activo = 1'
        );
        $stA->execute([$idGrupo]);
        foreach ($stA->fetchAll(PDO::FETCH_COLUMN) as $idAlumno) {
            $procesados++;
            $res = moodle_alumno_inscribir_curso_fase(
                $pdo,
                (int) $idAlumno,
                (int) ($g['id_especialidad'] ?? 0),
                $idFase,
                $idPlantel
            );
            if (!empty($res['ok']) && empty($res['omitido'])) {
                $okCount++;
            } elseif (empty($res['ok'])) {
                $errCount++;
            }
        }
    }

    return [
        'ok' => $errCount === 0 || $okCount > 0,
        'message' => "Sincronizados {$okCount} de {$procesados} alumno(s)" . ($errCount > 0 ? ", {$errCount} error(es)" : ''),
        'procesados' => $procesados,
        'ok_count' => $okCount,
        'err_count' => $errCount,
    ];
}

function moodle_nivel_puede_administrar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('menu_especialidades')) {
        return true;
    }

    return in_array(rbac_rol_efectivo(), ['supervisor', 'director', 'coordinador', 'admin'], true);
}
