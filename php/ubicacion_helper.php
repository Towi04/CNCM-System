<?php

/**
 * Examen de ubicación (placement): solicitud, autorización de grupos e inscripción controlada.
 */

/** @return array<string, string> */
function ubicacion_estados_etiquetas(): array
{
    return [
        'pendiente' => 'Pendiente de evaluación',
        'autorizado' => 'Autorizado — pendiente de inscripción',
        'rechazado' => 'Rechazado / sin ubicación',
        'usado' => 'Inscrito en grupo autorizado',
    ];
}

function ubicacion_puede_evaluar(): bool
{
    $rol = rbac_rol_efectivo();

    return in_array($rol, ['profesor', 'gerente', 'supervisor', 'admin'], true);
}

function ubicacion_puede_solicitar(): bool
{
    return in_array(rbac_rol_efectivo(), ['admin', 'gerente', 'asesor', 'supervisor'], true)
        || ubicacion_puede_evaluar();
}

function ubicacion_puede_asesor_gestionar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('menu_ubicacion_asesor')) {
        return true;
    }

    return in_array(rbac_rol_efectivo(), ['asesor', 'gerente', 'supervisor', 'admin'], true);
}

/** @return list<string> */
function ubicacion_niveles_sugeridos(PDO $pdo, int $idEspecialidad): array
{
    $niveles = ['A1', 'A1+', 'A2', 'A2+', 'B1', 'B1+', 'B2', 'C1'];
    try {
        $st = $pdo->prepare(
            'SELECT DISTINCT nivel_cefr FROM especialidad_fases
             WHERE id_especialidad = ? AND nivel_cefr IS NOT NULL AND nivel_cefr != \'\'
             ORDER BY orden ASC'
        );
        $st->execute([$idEspecialidad]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) {
            $n = trim((string) $n);
            if ($n !== '' && !in_array($n, $niveles, true)) {
                $niveles[] = $n;
            }
        }
    } catch (PDOException $e) {
        // sin fases
    }

    return $niveles;
}

/** Ubicación activa que restringe inscripción (pendiente o autorizado). */
function ubicacion_obtener_activa(PDO $pdo, int $idAlumno, int $idEspecialidad): ?array
{
    try {
        $st = $pdo->prepare(
            'SELECT u.*, e.nombre AS esp_nombre, e.clave AS esp_clave
             FROM alumno_ubicacion u
             INNER JOIN especialidades e ON e.id_especialidad = u.id_especialidad
             WHERE u.id_alumno = ? AND u.id_especialidad = ? AND u.estado IN (\'pendiente\', \'autorizado\')
             ORDER BY u.id_ubicacion DESC LIMIT 1'
        );
        $st->execute([$idAlumno, $idEspecialidad]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/** @return list<int> */
function ubicacion_grupos_autorizados_ids(PDO $pdo, int $idUbicacion): array
{
    $st = $pdo->prepare('SELECT id_grupo FROM alumno_ubicacion_grupos WHERE id_ubicacion = ?');
    $st->execute([$idUbicacion]);

    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** @return list<array<string, mixed>> */
function ubicacion_grupos_autorizados_detalle(PDO $pdo, int $idUbicacion): array
{
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.id_fase_actual, f.clave_fase, f.nombre_fase
         FROM alumno_ubicacion_grupos ug
         INNER JOIN grupos g ON g.id_grupo = ug.id_grupo
         LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual
         WHERE ug.id_ubicacion = ?
         ORDER BY g.clave'
    );
    $st->execute([$idUbicacion]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function ubicacion_validar_inscripcion_grupo(PDO $pdo, int $idAlumno, int $idGrupo): array
{
    $g = $pdo->prepare(
        'SELECT id_grupo, clave, id_especialidad FROM grupos WHERE id_grupo = ? LIMIT 1'
    );
    $g->execute([$idGrupo]);
    $grupo = $g->fetch(PDO::FETCH_ASSOC);
    if (!$grupo) {
        return ['ok' => false, 'message' => 'Grupo no encontrado'];
    }

    $ub = ubicacion_obtener_activa($pdo, $idAlumno, (int) $grupo['id_especialidad']);
    if (!$ub) {
        return ['ok' => true];
    }

    if ($ub['estado'] === 'pendiente') {
        return [
            'ok' => false,
            'message' => 'El alumno tiene examen de ubicación pendiente. Coordinación debe autorizar grupos antes de inscribir.',
            'ubicacion' => $ub,
        ];
    }

    $ids = ubicacion_grupos_autorizados_ids($pdo, (int) $ub['id_ubicacion']);
    if ($ids === []) {
        return [
            'ok' => false,
            'message' => 'Hay autorización de ubicación sin grupos asignados. Coordinación debe completar la evaluación.',
            'ubicacion' => $ub,
        ];
    }

    if (!in_array($idGrupo, $ids, true)) {
        $claves = ubicacion_grupos_autorizados_detalle($pdo, (int) $ub['id_ubicacion']);
        $lista = implode(', ', array_column($claves, 'clave'));

        return [
            'ok' => false,
            'message' => 'Solo puede inscribir en grupos autorizados por ubicación: ' . $lista,
            'ubicacion' => $ub,
            'grupos_permitidos' => $ids,
        ];
    }

    return ['ok' => true, 'ubicacion' => $ub, 'es_ubicacion' => true];
}

function ubicacion_crear_solicitud(
    PDO $pdo,
    int $idAlumno,
    int $idEspecialidad,
    ?int $idUsuario = null,
    ?string $observaciones = null
): array {
    academico_ensure_schema($pdo);
    $idPlantel = plantel_id_activo();

    $chk = $pdo->prepare('SELECT id_alumno FROM alumnos WHERE id_alumno = ? AND id_plantel = ?');
    $chk->execute([$idAlumno, $idPlantel]);
    if (!$chk->fetchColumn()) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    $ex = ubicacion_obtener_activa($pdo, $idAlumno, $idEspecialidad);
    if ($ex) {
        $etq = ubicacion_estados_etiquetas()[$ex['estado']] ?? $ex['estado'];

        return ['ok' => false, 'message' => 'Ya existe solicitud de ubicación: ' . $etq];
    }

    $pdo->prepare(
        'INSERT INTO alumno_ubicacion (id_alumno, id_plantel, id_especialidad, evaluado_por, fecha_evaluacion, observaciones, estado)
         VALUES (?, ?, ?, NULL, CURDATE(), ?, \'pendiente\')'
    )->execute([$idAlumno, $idPlantel, $idEspecialidad, $observaciones]);

    $idUb = (int) $pdo->lastInsertId();

    if ($idUsuario && function_exists('academico_notificar_usuario')) {
        ubicacion_notificar_coordinadores_nueva($pdo, $idUb, $idAlumno, $idEspecialidad);
    }

    return ['ok' => true, 'message' => 'Solicitud de examen de ubicación registrada', 'id_ubicacion' => $idUb];
}

function ubicacion_notificar_coordinadores_nueva(
    PDO $pdo,
    int $idUbicacion,
    int $idAlumno,
    int $idEspecialidad
): void {
    $a = $pdo->prepare(
        'SELECT TRIM(CONCAT(COALESCE(nombres, nombre, \'\'), \' \', COALESCE(apellido_paterno, apellido, \'\'))) FROM alumnos WHERE id_alumno = ?'
    );
    $a->execute([$idAlumno]);
    $nombre = (string) $a->fetchColumn();
    $e = $pdo->prepare('SELECT nombre FROM especialidades WHERE id_especialidad = ?');
    $e->execute([$idEspecialidad]);
    $esp = (string) $e->fetchColumn();

    $st = $pdo->prepare(
        "SELECT id_usuario FROM usuarios
         WHERE suspendido = 0 AND rol IN ('profesor', 'gerente', 'supervisor')
           AND (id_plantel IS NULL OR id_plantel = ?)"
    );
    $st->execute([plantel_id_activo()]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        academico_notificar_usuario(
            $pdo,
            (int) $uid,
            'ubicacion_pendiente',
            'Examen de ubicación pendiente',
            $nombre . ' — ' . $esp . '. Evalúe y autorice grupos.',
            'ubicacion_coordinacion',
            'id=' . $idUbicacion
        );
    }
}

/**
 * @param list<int> $idGrupos
 */
function ubicacion_autorizar(
    PDO $pdo,
    int $idUbicacion,
    string $nivelDetectado,
    array $idGrupos,
    ?string $observaciones,
    int $idEvaluador
): array {
    $st = $pdo->prepare('SELECT * FROM alumno_ubicacion WHERE id_ubicacion = ? LIMIT 1');
    $st->execute([$idUbicacion]);
    $ub = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ub) {
        return ['ok' => false, 'message' => 'Registro no encontrado'];
    }
    if ($ub['estado'] !== 'pendiente') {
        return ['ok' => false, 'message' => 'Solo se pueden autorizar solicitudes pendientes'];
    }

    $idGrupos = array_values(array_unique(array_filter(array_map('intval', $idGrupos))));
    if ($idGrupos === []) {
        return ['ok' => false, 'message' => 'Seleccione al menos un grupo autorizado'];
    }

    $idEsp = (int) $ub['id_especialidad'];
    foreach ($idGrupos as $idG) {
        $g = $pdo->prepare('SELECT id_especialidad FROM grupos WHERE id_grupo = ? AND id_plantel = ?');
        $g->execute([$idG, (int) $ub['id_plantel']]);
        if ((int) $g->fetchColumn() !== $idEsp) {
            return ['ok' => false, 'message' => 'Todos los grupos deben ser de la misma especialidad'];
        }
    }

    $pdo->prepare(
        'UPDATE alumno_ubicacion SET estado = \'autorizado\', evaluado_por = ?, fecha_evaluacion = CURDATE(),
         nivel_detectado = ?, observaciones = CONCAT(COALESCE(observaciones, \'\'), ?)
         WHERE id_ubicacion = ?'
    )->execute([
        $idEvaluador,
        trim($nivelDetectado) ?: null,
        $observaciones !== null && $observaciones !== '' ? "\n" . $observaciones : '',
        $idUbicacion,
    ]);

    $pdo->prepare('DELETE FROM alumno_ubicacion_grupos WHERE id_ubicacion = ?')->execute([$idUbicacion]);
    $ins = $pdo->prepare('INSERT INTO alumno_ubicacion_grupos (id_ubicacion, id_grupo) VALUES (?, ?)');
    foreach ($idGrupos as $idG) {
        $ins->execute([$idUbicacion, $idG]);
    }

    if (function_exists('academico_notificar_usuario')) {
        $pdo->prepare(
            'INSERT INTO alumno_nota_coordinacion (id_alumno, id_usuario, tipo, nota)
             VALUES (?, ?, \'ubicacion\', ?)'
        )->execute([
            (int) $ub['id_alumno'],
            $idEvaluador,
            'Ubicación autorizada. Nivel: ' . $nivelDetectado . '. Grupos: ' . count($idGrupos) . '.',
        ]);
    }

    ubicacion_notificar_recepcion_autorizada($pdo, (int) $ub['id_alumno'], $idUbicacion);

    return ['ok' => true, 'message' => 'Ubicación autorizada. Recepción ya puede inscribir en los grupos indicados.'];
}

function ubicacion_notificar_recepcion_autorizada(PDO $pdo, int $idAlumno, int $idUbicacion): void
{
    $grupos = ubicacion_grupos_autorizados_detalle($pdo, $idUbicacion);
    $lista = implode(', ', array_column($grupos, 'clave'));
    $st = $pdo->prepare(
        "SELECT id_usuario FROM usuarios WHERE suspendido = 0 AND rol IN ('admin', 'gerente', 'asesor')"
    );
    $st->execute();
    $a = $pdo->prepare('SELECT numero_control, nombres, nombre, apellido_paterno, apellido FROM alumnos WHERE id_alumno = ?');
    $a->execute([$idAlumno]);
    $al = $a->fetch(PDO::FETCH_ASSOC);
    $nom = trim(($al['nombres'] ?? $al['nombre'] ?? '') . ' ' . ($al['apellido_paterno'] ?? $al['apellido'] ?? ''));

    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        academico_notificar_usuario(
            $pdo,
            (int) $uid,
            'ubicacion_autorizada',
            'Ubicación lista — contactar alumno',
            $nom . ' (# ' . ($al['numero_control'] ?? '') . ') — grupos: ' . $lista,
            'asesor_ubicacion',
            'id=' . $idUbicacion
        );
    }
}

function ubicacion_rechazar(PDO $pdo, int $idUbicacion, string $motivo, int $idEvaluador): array
{
    $st = $pdo->prepare('SELECT * FROM alumno_ubicacion WHERE id_ubicacion = ? LIMIT 1');
    $st->execute([$idUbicacion]);
    $ub = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ub || $ub['estado'] !== 'pendiente') {
        return ['ok' => false, 'message' => 'No se puede rechazar este registro'];
    }

    $pdo->prepare(
        'UPDATE alumno_ubicacion SET estado = \'rechazado\', evaluado_por = ?, fecha_evaluacion = CURDATE(),
         observaciones = CONCAT(COALESCE(observaciones, \'\'), ?) WHERE id_ubicacion = ?'
    )->execute([$idEvaluador, "\nRechazado: " . $motivo, $idUbicacion]);

    $pdo->prepare('DELETE FROM alumno_ubicacion_grupos WHERE id_ubicacion = ?')->execute([$idUbicacion]);

    return ['ok' => true, 'message' => 'Solicitud rechazada. Puede inscribir en grupo inicial (A1) sin restricción.'];
}

function ubicacion_marcar_usado(PDO $pdo, int $idAlumno, int $idGrupo): void
{
    $g = $pdo->prepare('SELECT id_especialidad FROM grupos WHERE id_grupo = ?');
    $g->execute([$idGrupo]);
    $idEsp = (int) $g->fetchColumn();
    if ($idEsp <= 0) {
        return;
    }

    $ub = ubicacion_obtener_activa($pdo, $idAlumno, $idEsp);
    if (!$ub || $ub['estado'] !== 'autorizado') {
        return;
    }

    $ids = ubicacion_grupos_autorizados_ids($pdo, (int) $ub['id_ubicacion']);
    if (!in_array($idGrupo, $ids, true)) {
        return;
    }

    $pdo->prepare('UPDATE alumno_ubicacion SET estado = \'usado\' WHERE id_ubicacion = ?')
        ->execute([(int) $ub['id_ubicacion']]);
}

/**
 * Asigna grupo validando ubicación y marca usado si aplica.
 */
function ubicacion_asignar_grupo_validado(
    PDO $pdo,
    int $idAlumno,
    int $idGrupo,
    bool $forzarUbicacionFlag = false
): array {
    $val = ubicacion_validar_inscripcion_grupo($pdo, $idAlumno, $idGrupo);
    if (!$val['ok']) {
        return $val;
    }

    $esUbicacion = !empty($val['es_ubicacion']) || $forzarUbicacionFlag;
    alumno_asignar_grupo($pdo, $idAlumno, $idGrupo, $esUbicacion);
    ubicacion_marcar_usado($pdo, $idAlumno, $idGrupo);

    return ['ok' => true, 'message' => 'Alumno inscrito al grupo', 'ubicacion' => $esUbicacion];
}

/** @return list<array<string, mixed>> */
function ubicacion_listar(PDO $pdo, ?string $estado = null, ?int $idPlantel = null): array
{
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $sql = 'SELECT u.*, e.nombre AS esp_nombre, e.clave AS esp_clave,
                   TRIM(CONCAT(COALESCE(a.nombres, a.nombre, \'\'), \' \', COALESCE(a.apellido_paterno, a.apellido, \'\'))) AS alumno_nombre,
                   a.numero_control,
                   ev.nombre AS eval_nombre, ev.apellido AS eval_apellido,
                   ex.nombre AS examen_nombre, ex.moodle_course_id AS examen_moodle_course_id
            FROM alumno_ubicacion u
            INNER JOIN alumnos a ON a.id_alumno = u.id_alumno
            INNER JOIN especialidades e ON e.id_especialidad = u.id_especialidad
            LEFT JOIN usuarios ev ON ev.id_usuario = u.evaluado_por
            LEFT JOIN ubicacion_examen ex ON ex.id_examen = u.id_examen_ubicacion
            WHERE u.id_plantel = ?';
    $params = [$idPlantel];
    if ($estado !== null && $estado !== '') {
        $sql .= ' AND u.estado = ?';
        $params[] = $estado;
    }
    $sql .= ' ORDER BY FIELD(u.estado, \'pendiente\', \'autorizado\', \'usado\', \'rechazado\'), u.creado_en DESC';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['grupos_autorizados'] = in_array($r['estado'], ['autorizado', 'usado'], true)
            ? ubicacion_grupos_autorizados_detalle($pdo, (int) $r['id_ubicacion'])
            : [];
    }

    return $rows;
}

/** Lista para asesores de ventas (contacto + grupos autorizados). */
function ubicacion_listar_asesor(PDO $pdo, ?string $estado = null, ?int $idPlantel = null): array
{
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $sql = 'SELECT u.*, e.nombre AS esp_nombre, e.clave AS esp_clave,
                   TRIM(CONCAT(COALESCE(a.nombres, a.nombre, \'\'), \' \', COALESCE(a.apellido_paterno, a.apellido, \'\'))) AS alumno_nombre,
                   a.numero_control, a.telefono, a.email,
                   ev.nombre AS eval_nombre, ev.apellido AS eval_apellido,
                   ex.nombre AS examen_nombre
            FROM alumno_ubicacion u
            INNER JOIN alumnos a ON a.id_alumno = u.id_alumno
            INNER JOIN especialidades e ON e.id_especialidad = u.id_especialidad
            LEFT JOIN usuarios ev ON ev.id_usuario = u.evaluado_por
            LEFT JOIN ubicacion_examen ex ON ex.id_examen = u.id_examen_ubicacion
            WHERE u.id_plantel = ?';
    $params = [$idPlantel];
    if ($estado !== null && $estado !== '') {
        $sql .= ' AND u.estado = ?';
        $params[] = $estado;
    } else {
        $sql .= ' AND u.estado IN (\'pendiente\', \'autorizado\', \'usado\')';
    }
    $sql .= ' ORDER BY FIELD(u.estado, \'autorizado\', \'pendiente\', \'usado\', \'rechazado\'), u.creado_en DESC';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $etiquetas = ubicacion_estados_etiquetas();
    foreach ($rows as &$r) {
        $r['estado_label'] = $etiquetas[$r['estado']] ?? $r['estado'];
        $r['grupos_autorizados'] = in_array($r['estado'], ['autorizado', 'usado'], true)
            ? ubicacion_grupos_autorizados_detalle($pdo, (int) $r['id_ubicacion'])
            : [];
    }

    return $rows;
}

/** @return array{ok:bool, message:string} */
function ubicacion_asesor_asignar_grupo(PDO $pdo, int $idUbicacion, int $idGrupo, int $idUsuario): array
{
    if (!ubicacion_puede_asesor_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }

    $st = $pdo->prepare('SELECT * FROM alumno_ubicacion WHERE id_ubicacion = ? LIMIT 1');
    $st->execute([$idUbicacion]);
    $ub = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ub) {
        return ['ok' => false, 'message' => 'Registro no encontrado'];
    }
    if ($ub['estado'] !== 'autorizado') {
        $etq = ubicacion_estados_etiquetas()[$ub['estado']] ?? $ub['estado'];

        return ['ok' => false, 'message' => 'Solo puede inscribir cuando el estado es «Autorizado». Actual: ' . $etq];
    }

    $ids = ubicacion_grupos_autorizados_ids($pdo, $idUbicacion);
    if (!in_array($idGrupo, $ids, true)) {
        return ['ok' => false, 'message' => 'El grupo no está autorizado por coordinación para este alumno'];
    }

    $res = ubicacion_asignar_grupo_validado($pdo, (int) $ub['id_alumno'], $idGrupo, true);
    if (!$res['ok']) {
        return $res;
    }

    if (function_exists('academico_notificar_usuario')) {
        $pdo->prepare(
            'INSERT INTO alumno_nota_coordinacion (id_alumno, id_usuario, tipo, nota)
             VALUES (?, ?, \'ubicacion\', ?)'
        )->execute([
            (int) $ub['id_alumno'],
            $idUsuario,
            'Inscrito por asesor en grupo autorizado (ubicación #' . $idUbicacion . ').',
        ]);
    }

    return ['ok' => true, 'message' => 'Alumno inscrito en el grupo seleccionado'];
}

/** Grupos elegibles para autorizar (misma especialidad, plantel). */
function ubicacion_grupos_para_autorizar(PDO $pdo, int $idEspecialidad, ?int $idPlantel = null): array
{
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.fecha_inicio, g.id_fase_actual, f.clave_fase, f.nombre_fase, f.nivel_cefr
         FROM grupos g
         LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual
         WHERE g.id_plantel = ? AND g.id_especialidad = ?
         ORDER BY g.clave ASC'
    );
    $st->execute([$idPlantel, $idEspecialidad]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Resumen para ficha alumno. */
function ubicacion_resumen_alumno(PDO $pdo, int $idAlumno): array
{
    try {
        if (function_exists('academico_ensure_schema')) {
            academico_ensure_schema($pdo);
        }
        $st = $pdo->prepare(
            'SELECT u.*, e.nombre AS esp_nombre
             FROM alumno_ubicacion u
             INNER JOIN especialidades e ON e.id_especialidad = u.id_especialidad
             WHERE u.id_alumno = ?
             ORDER BY u.creado_en DESC LIMIT 10'
        );
        $st->execute([$idAlumno]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['grupos_autorizados'] = ubicacion_grupos_autorizados_detalle($pdo, (int) $r['id_ubicacion']);
        }
        unset($r);

        return $rows;
    } catch (Throwable $e) {
        error_log('ubicacion_resumen_alumno: ' . $e->getMessage());

        return [];
    }
}

/** IDs de grupo permitidos para selects (null = sin restricción). */
function ubicacion_grupos_permitidos_inscripcion(PDO $pdo, int $idAlumno, int $idEspecialidad): ?array
{
    $ub = ubicacion_obtener_activa($pdo, $idAlumno, $idEspecialidad);
    if (!$ub) {
        return null;
    }
    if ($ub['estado'] === 'pendiente') {
        return [];
    }
    if ($ub['estado'] === 'autorizado') {
        return ubicacion_grupos_autorizados_ids($pdo, (int) $ub['id_ubicacion']);
    }

    return null;
}

function ubicacion_examen_ensure_schema(PDO $pdo): void
{
    if (function_exists('academico_ensure_schema')) {
        academico_ensure_schema($pdo);
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ubicacion_examen (
            id_examen INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_especialidad INT UNSIGNED NOT NULL,
            id_fase INT UNSIGNED NULL,
            nombre VARCHAR(160) NOT NULL,
            descripcion TEXT NULL,
            moodle_course_id INT UNSIGNED NOT NULL,
            moodle_shortname VARCHAR(80) NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            orden INT NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_examen),
            KEY idx_ubex_esp (id_especialidad),
            KEY idx_ubex_fase (id_fase)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    plantel_ensure_column($pdo, 'ubicacion_examen', 'moodle_idnumber', 'VARCHAR(80) NULL', 'moodle_shortname');
    plantel_ensure_column($pdo, 'alumno_ubicacion', 'id_examen_ubicacion', 'INT UNSIGNED NULL', 'id_especialidad');
    plantel_ensure_column($pdo, 'alumno_ubicacion', 'moodle_inscrito', 'TINYINT(1) NOT NULL DEFAULT 0', 'observaciones');
}

/**
 * Resuelve el ID interno del curso Moodle para un examen de ubicación.
 *
 * @return array{ok:bool,message:string,id?:int,shortname?:string,idnumber?:string,resuelto_por?:string,hint?:string}
 */
function ubicacion_examen_curso_moodle_resolver(array $ex): array
{
    $idnumber = trim((string) ($ex['moodle_idnumber'] ?? ''));
    $shortname = trim((string) ($ex['moodle_shortname'] ?? ''));
    $courseId = (int) ($ex['moodle_course_id'] ?? 0);

    if ($idnumber === '' && $courseId > 0 && $courseId < 500 && function_exists('moodle_list_courses')) {
        foreach (moodle_list_courses() as $c) {
            if ((int) ($c['id'] ?? 0) === $courseId) {
                break;
            }
        }
        if (!isset($c) || (int) ($c['id'] ?? 0) !== $courseId) {
            $idnumber = (string) $courseId;
            $courseId = 0;
        }
    }

    if (function_exists('moodle_course_map_lookup')) {
        $mapped = moodle_course_map_lookup(
            $idnumber !== '' ? $idnumber : null,
            $shortname !== '' ? $shortname : null
        );
        if ($mapped !== null) {
            return $mapped;
        }
    }

    if (function_exists('moodle_course_resolve_for_examen')) {
        return moodle_course_resolve_for_examen(
            $idnumber !== '' ? $idnumber : null,
            $shortname !== '' ? $shortname : null,
            $courseId
        );
    }

    if ($courseId > 1) {
        return ['ok' => true, 'id' => $courseId, 'resuelto_por' => 'course_id_legacy'];
    }

    return ['ok' => false, 'message' => 'Curso Moodle no configurado para el examen'];
}

/**
 * Localiza examen de ubicación por id_examen HAY, idnumber Moodle o ID interno de curso.
 *
 * @return array{ok:bool,message:string,examen?:array,resuelto_por?:string,hint?:string,examenes_disponibles?:array}
 */
function ubicacion_examen_resolver_peticion(PDO $pdo, int $idExamen, string $idnumber = ''): array
{
    ubicacion_examen_ensure_schema($pdo);
    $idnumber = trim($idnumber);
    $catalogo = ubicacion_examen_listar($pdo, null, false);

    if ($idExamen > 0) {
        $ex = ubicacion_examen_obtener($pdo, $idExamen);
        if ($ex) {
            return ['ok' => true, 'examen' => $ex, 'resuelto_por' => 'id_examen'];
        }

        foreach ($catalogo as $row) {
            if ((int) ($row['moodle_course_id'] ?? 0) === $idExamen) {
                return [
                    'ok' => true,
                    'examen' => $row,
                    'resuelto_por' => 'moodle_course_id',
                    'hint' => 'Recibió el ID interno del curso Moodle (' . $idExamen . '). '
                        . 'Use id_examen=' . (int) ($row['id_examen'] ?? 0) . ' o idnumber='
                        . trim((string) ($row['moodle_idnumber'] ?? '')),
                ];
            }
        }
    }

    if ($idnumber !== '') {
        foreach ($catalogo as $row) {
            $rowIdNum = trim((string) ($row['moodle_idnumber'] ?? ''));
            if ($rowIdNum !== '' && $rowIdNum === $idnumber) {
                return ['ok' => true, 'examen' => $row, 'resuelto_por' => 'idnumber'];
            }
        }
        foreach ($catalogo as $row) {
            $legacyNum = trim((string) ($row['moodle_course_id'] ?? ''));
            if (($row['moodle_idnumber'] ?? '') === '' || $row['moodle_idnumber'] === null) {
                if ($legacyNum !== '' && $legacyNum === $idnumber) {
                    return [
                        'ok' => true,
                        'examen' => $row,
                        'resuelto_por' => 'idnumber_legacy',
                        'hint' => 'idnumber guardado en moodle_course_id; se corregirá al guardar el examen.',
                    ];
                }
            }
        }
        foreach ($catalogo as $row) {
            if (trim((string) ($row['moodle_shortname'] ?? '')) !== ''
                && strcasecmp(trim((string) ($row['moodle_shortname'] ?? '')), $idnumber) === 0) {
                return ['ok' => true, 'examen' => $row, 'resuelto_por' => 'shortname'];
            }
        }
    }

    return [
        'ok' => false,
        'message' => 'Examen de ubicación no encontrado'
            . ($idExamen > 0 ? ' (id_examen=' . $idExamen . ')' : '')
            . ($idnumber !== '' ? ' (idnumber=' . $idnumber . ')' : ''),
        'hint' => 'Use id_examen del catálogo HAY (ej. 1) o idnumber del curso Moodle (ej. 4). '
            . 'No use el ID interno mdl_course.id (ej. 168).',
        'examenes_disponibles' => array_map(static function (array $row): array {
            return [
                'id_examen' => (int) ($row['id_examen'] ?? 0),
                'nombre' => (string) ($row['nombre'] ?? ''),
                'moodle_idnumber' => (string) ($row['moodle_idnumber'] ?? ''),
                'moodle_course_id' => (int) ($row['moodle_course_id'] ?? 0),
                'moodle_shortname' => (string) ($row['moodle_shortname'] ?? ''),
                'activo' => (int) ($row['activo'] ?? 0),
            ];
        }, $catalogo),
    ];
}

function ubicacion_examen_puede_administrar(): bool
{
    return function_exists('ubicacion_puede_evaluar') && ubicacion_puede_evaluar();
}

/** @return list<array<string,mixed>> */
function ubicacion_examen_listar(PDO $pdo, ?int $idEspecialidad = null, bool $soloActivos = false): array
{
    ubicacion_examen_ensure_schema($pdo);
    $sql = 'SELECT ex.*, e.nombre AS esp_nombre, e.clave AS esp_clave,
                   f.nombre_fase, f.clave_fase
            FROM ubicacion_examen ex
            INNER JOIN especialidades e ON e.id_especialidad = ex.id_especialidad
            LEFT JOIN especialidad_fases f ON f.id_fase = ex.id_fase
            WHERE 1=1';
    $params = [];
    if ($idEspecialidad !== null && $idEspecialidad > 0) {
        $sql .= ' AND ex.id_especialidad = ?';
        $params[] = $idEspecialidad;
    }
    if ($soloActivos) {
        $sql .= ' AND ex.activo = 1';
    }
    $sql .= ' ORDER BY ex.id_especialidad ASC, ex.orden ASC, ex.nombre ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function ubicacion_examen_reparar_curso_moodle(PDO $pdo, array $ex): array
{
    $idExamen = (int) ($ex['id_examen'] ?? 0);
    if ($idExamen <= 0) {
        return $ex;
    }

    $resolved = ubicacion_examen_curso_moodle_resolver($ex);
    if (empty($resolved['ok'])) {
        return $ex;
    }

    $newId = (int) ($resolved['id'] ?? 0);
    $newIdNum = trim((string) ($ex['moodle_idnumber'] ?? ''));
    if ($newIdNum === '' && !empty($resolved['idnumber'])) {
        $newIdNum = trim((string) $resolved['idnumber']);
    }
    if ($newIdNum === '' && (int) ($ex['moodle_course_id'] ?? 0) > 0 && (int) ($ex['moodle_course_id'] ?? 0) < 500) {
        $newIdNum = (string) (int) $ex['moodle_course_id'];
    }

    $storedId = (int) ($ex['moodle_course_id'] ?? 0);
    if ($newId > 0 && ($storedId !== $newId || trim((string) ($ex['moodle_idnumber'] ?? '')) !== $newIdNum)) {
        $pdo->prepare(
            'UPDATE ubicacion_examen SET moodle_course_id = ?, moodle_idnumber = ? WHERE id_examen = ?'
        )->execute([$newId, $newIdNum !== '' ? $newIdNum : null, $idExamen]);
        $ex['moodle_course_id'] = $newId;
        $ex['moodle_idnumber'] = $newIdNum !== '' ? $newIdNum : null;
    }

    return $ex;
}

function ubicacion_examen_obtener(PDO $pdo, int $idExamen): ?array
{
    ubicacion_examen_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT ex.*, e.nombre AS esp_nombre, e.clave AS esp_clave,
                f.nombre_fase, f.clave_fase
         FROM ubicacion_examen ex
         INNER JOIN especialidades e ON e.id_especialidad = ex.id_especialidad
         LEFT JOIN especialidad_fases f ON f.id_fase = ex.id_fase
         WHERE ex.id_examen = ? LIMIT 1'
    );
    $st->execute([$idExamen]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return ubicacion_examen_reparar_curso_moodle($pdo, $row);
}

/** @return array{ok:bool,message:string,id_examen?:int} */
function ubicacion_examen_guardar(PDO $pdo, array $data, ?int $idExamen = null): array
{
    ubicacion_examen_ensure_schema($pdo);

    $idEsp = (int) ($data['id_especialidad'] ?? 0);
    $nombre = trim((string) ($data['nombre'] ?? ''));
    $idnumber = trim((string) ($data['moodle_idnumber'] ?? ''));
    $shortname = trim((string) ($data['moodle_shortname'] ?? ''));
    $courseIdHint = (int) ($data['moodle_course_id'] ?? 0);

    if ($idnumber === '' && $courseIdHint > 0 && $courseIdHint < 1000) {
        $idnumber = (string) $courseIdHint;
    }

    $courseId = 0;
    $idnumberStore = $idnumber !== '' ? $idnumber : null;
    $shortnameStore = $shortname !== '' ? $shortname : null;

    if (function_exists('moodle_course_resolve_for_examen') && function_exists('moodle_enabled') && moodle_enabled()) {
        $resolved = moodle_course_resolve_for_examen($idnumber, $shortname, $courseIdHint);
        if (!empty($resolved['ok'])) {
            $courseId = (int) $resolved['id'];
            if ($shortnameStore === null && !empty($resolved['shortname'])) {
                $shortnameStore = (string) $resolved['shortname'];
            }
            if ($idnumberStore === null && !empty($resolved['idnumber'])) {
                $idnumberStore = (string) $resolved['idnumber'];
            }
        } else {
            $mapped = function_exists('moodle_course_map_lookup') ? moodle_course_map_lookup($idnumber, $shortname) : null;
            if ($mapped !== null) {
                $courseId = (int) $mapped['id'];
                if ($idnumberStore === null && $idnumber !== '') {
                    $idnumberStore = $idnumber;
                }
            } else {
                return [
                    'ok' => false,
                    'message' => (string) ($resolved['message'] ?? 'Curso Moodle no encontrado'),
                    'hint' => (string) ($resolved['hint'] ?? ''),
                ];
            }
        }
    } elseif ($courseIdHint > 1) {
        $courseId = $courseIdHint;
    }

    if ($idEsp <= 0 || $nombre === '' || $courseId <= 0) {
        return ['ok' => false, 'message' => 'Especialidad, nombre, idnumber y shortname del curso Moodle son obligatorios'];
    }

    $idFase = (int) ($data['id_fase'] ?? 0) ?: null;
    $descripcion = trim((string) ($data['descripcion'] ?? '')) ?: null;
    $activo = !empty($data['activo']) ? 1 : 0;
    $orden = (int) ($data['orden'] ?? 0);

    if ($idExamen > 0) {
        $pdo->prepare(
            'UPDATE ubicacion_examen SET id_especialidad = ?, id_fase = ?, nombre = ?, descripcion = ?,
             moodle_course_id = ?, moodle_idnumber = ?, moodle_shortname = ?, activo = ?, orden = ?
             WHERE id_examen = ?'
        )->execute([$idEsp, $idFase, $nombre, $descripcion, $courseId, $idnumberStore, $shortnameStore, $activo, $orden, $idExamen]);

        return [
            'ok' => true,
            'message' => 'Examen actualizado (idnumber ' . ($idnumberStore ?? '—') . ' → curso Moodle #' . $courseId . ')',
            'id_examen' => $idExamen,
            'moodle_course_id' => $courseId,
            'moodle_idnumber' => $idnumberStore,
        ];
    }

    $pdo->prepare(
        'INSERT INTO ubicacion_examen (id_especialidad, id_fase, nombre, descripcion, moodle_course_id, moodle_idnumber, moodle_shortname, activo, orden)
         VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([$idEsp, $idFase, $nombre, $descripcion, $courseId, $idnumberStore, $shortnameStore, $activo, $orden]);

    return [
        'ok' => true,
        'message' => 'Examen registrado (idnumber ' . ($idnumberStore ?? '—') . ' → curso Moodle #' . $courseId . ')',
        'id_examen' => (int) $pdo->lastInsertId(),
        'moodle_course_id' => $courseId,
        'moodle_idnumber' => $idnumberStore,
    ];
}

/** @return array{ok:bool,message:string} */
function ubicacion_examen_eliminar(PDO $pdo, int $idExamen): array
{
    ubicacion_examen_ensure_schema($pdo);
    $chk = $pdo->prepare('SELECT COUNT(*) FROM alumno_ubicacion WHERE id_examen_ubicacion = ?');
    $chk->execute([$idExamen]);
    if ((int) $chk->fetchColumn() > 0) {
        return ['ok' => false, 'message' => 'No se puede eliminar: hay alumnos vinculados a este examen'];
    }
    $pdo->prepare('DELETE FROM ubicacion_examen WHERE id_examen = ?')->execute([$idExamen]);

    return ['ok' => true, 'message' => 'Examen eliminado'];
}

/**
 * Inscribe alumno por ubicación al inscribir (sin grupo): crea solicitud + acceso Moodle al examen.
 *
 * @return array{ok:bool,message:string,id_ubicacion?:int,moodle?:string}
 */
function ubicacion_inscripcion_con_examen(
    PDO $pdo,
    int $idAlumno,
    int $idEspecialidad,
    int $idExamen,
    ?string $observaciones = null
): array {
    ubicacion_examen_ensure_schema($pdo);
    $idPlantel = plantel_id_activo();

    $ex = ubicacion_examen_obtener($pdo, $idExamen);
    if (!$ex || !(int) ($ex['activo'] ?? 0)) {
        return ['ok' => false, 'message' => 'Examen de ubicación no encontrado o inactivo'];
    }
    if ((int) $ex['id_especialidad'] !== $idEspecialidad) {
        return ['ok' => false, 'message' => 'El examen no corresponde a la especialidad del alumno'];
    }

    $activa = ubicacion_obtener_activa($pdo, $idAlumno, $idEspecialidad);
    if ($activa) {
        $etq = ubicacion_estados_etiquetas()[$activa['estado']] ?? $activa['estado'];

        return ['ok' => false, 'message' => 'Ya existe solicitud de ubicación: ' . $etq];
    }

    $obs = trim((string) $observaciones);
    $notaExamen = 'Examen Moodle: ' . ($ex['nombre'] ?? '') . ' (curso #' . (int) $ex['moodle_course_id'] . ')';
    $obsFull = $obs !== '' ? ($obs . "\n" . $notaExamen) : $notaExamen;

    $pdo->prepare(
        'INSERT INTO alumno_ubicacion (id_alumno, id_plantel, id_especialidad, id_examen_ubicacion, evaluado_por,
         fecha_evaluacion, observaciones, estado, moodle_inscrito)
         VALUES (?, ?, ?, ?, NULL, CURDATE(), ?, \'pendiente\', 0)'
    )->execute([$idAlumno, $idPlantel, $idEspecialidad, $idExamen, $obsFull]);

    $idUb = (int) $pdo->lastInsertId();
    $moodleMsg = 'Moodle omitido';
    $moodleInscrito = false;
    $moodleWarning = null;

    if (function_exists('moodle_enabled') && moodle_enabled()) {
        if (function_exists('usuario_crear_cuenta_alumno')) {
            $cuenta = usuario_crear_cuenta_alumno($pdo, $idAlumno, $idPlantel);
            if (empty($cuenta['ok']) && empty($cuenta['vinculado'])) {
                $moodleWarning = [
                    'message' => (string) ($cuenta['message'] ?? 'No se pudo crear cuenta HAY/Google'),
                    'tipo' => 'cuenta_externa',
                ];
                $moodleMsg = (string) $moodleWarning['message'];
            } elseif (empty($cuenta['moodle_ok'])) {
                $moodleWarning = [
                    'message' => (string) ($cuenta['moodle'] ?? $cuenta['message'] ?? 'Google OK; Moodle pendiente'),
                    'tipo' => 'moodle_usuario',
                    'moodle_raw' => $cuenta['moodle_raw'] ?? null,
                ];
                $moodleMsg = (string) $moodleWarning['message'];
            }
        }

        $idMoodle = 0;
        if (function_exists('moodle_user_ensure_alumno')) {
            $mEnsure = moodle_user_ensure_alumno($pdo, $idAlumno, $idPlantel);
            if (!empty($mEnsure['ok'])) {
                $idMoodle = (int) ($mEnsure['id_moodle'] ?? 0);
            } elseif ($moodleWarning === null) {
                $moodleWarning = [
                    'message' => (string) ($mEnsure['message'] ?? 'No se pudo crear usuario Moodle'),
                    'tipo' => 'moodle_usuario',
                    'moodle_raw' => $mEnsure['moodle_raw'] ?? null,
                    'hint' => $mEnsure['hint'] ?? null,
                ];
                $moodleMsg = (string) $moodleWarning['message'];
            }
        }

        $courseResolved = ubicacion_examen_curso_moodle_resolver($ex);
        $courseId = (int) ($courseResolved['id'] ?? (int) ($ex['moodle_course_id'] ?? 0));
        if (empty($courseResolved['ok'])) {
            if ($courseId <= 0) {
                $moodleWarning = [
                    'message' => (string) ($courseResolved['message'] ?? 'Curso Moodle del examen no encontrado'),
                    'course_id' => 0,
                    'moodle_user_id' => $idMoodle,
                ];
                $moodleMsg = (string) $moodleWarning['message'];
            }
        }

        if ($idMoodle <= 0) {
            $moodleWarning = [
                'message' => 'No se pudo crear ni localizar el usuario Moodle (revise permisos del token o cree el usuario manualmente)',
                'course_id' => $courseId,
                'moodle_user_id' => 0,
                'hint' => 'Orden del flujo: 1) Google, 2) crear usuario Moodle, 3) inscribir al curso. '
                    . 'No se inscribe al curso sin id Moodle.',
            ];
            $moodleMsg = (string) $moodleWarning['message'];
        } elseif ($courseId > 0 && function_exists('moodle_enrol_user_in_course')) {
            $enrol = moodle_enrol_user_in_course($idMoodle, $courseId);
            $moodleMsg = (string) ($enrol['message'] ?? '');
            if (!empty($enrol['ok'])) {
                $moodleInscrito = true;
                $pdo->prepare('UPDATE alumno_ubicacion SET moodle_inscrito = 1 WHERE id_ubicacion = ?')
                    ->execute([$idUb]);
            } else {
                $moodleWarning = [
                    'message' => $moodleMsg,
                    'raw' => $enrol['raw'] ?? null,
                    'course_id' => $courseId,
                    'moodle_user_id' => $idMoodle,
                    'role_id' => $enrol['role_id'] ?? null,
                ];
                $moodleMsg = 'Inscripción HAY OK; Moodle pendiente: ' . $moodleMsg;
            }
        }
    }

    ubicacion_notificar_coordinadores_nueva($pdo, $idUb, $idAlumno, $idEspecialidad);

    $msgBase = $moodleInscrito
        ? 'Inscripción por ubicación registrada. Acceso al examen en Moodle listo.'
        : 'Inscripción por ubicación registrada en HAY.'
            . ($moodleWarning ? ' Revise la inscripción al curso Moodle.' : '');

    return [
        'ok' => true,
        'message' => $msgBase . ($moodleMsg !== '' && $moodleMsg !== 'Moodle omitido' ? ' ' . $moodleMsg : ''),
        'id_ubicacion' => $idUb,
        'examen' => $ex['nombre'] ?? '',
        'moodle' => $moodleMsg,
        'moodle_inscrito' => $moodleInscrito,
        'moodle_warning' => $moodleWarning,
    ];
}

