<?php

/**
 * Cambio de grupo, fin de curso y bajas vinculadas al reporte semanal.
 */

function alumno_grupo_acciones_puede(): bool
{
    return function_exists('usuario_puede_gestionar_alumnos')
        ? usuario_puede_gestionar_alumnos()
        : (function_exists('asistencia_puede_tomar') && asistencia_puede_tomar());
}

function alumno_grupo_especialidad(PDO $pdo, int $idGrupo): ?int
{
    $st = $pdo->prepare('SELECT id_especialidad FROM grupos WHERE id_grupo = ? LIMIT 1');
    $st->execute([$idGrupo]);
    $v = $st->fetchColumn();
    return $v !== false && $v !== null ? (int) $v : null;
}

/** Desactiva otros grupos de la misma especialidad (cambio de horario). */
function alumno_grupo_desactivar_misma_especialidad(
    PDO $pdo,
    int $idAlumno,
    int $idGrupoNuevo,
    int $idPlantel
): array {
    $idEsp = alumno_grupo_especialidad($pdo, $idGrupoNuevo);
    $st = $pdo->prepare(
        'SELECT ag.id_grupo FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         WHERE ag.id_alumno = ? AND ag.activo = 1 AND ag.id_grupo != ?
           AND g.id_plantel = ? AND (g.id_especialidad <=> ?)'
    );
    $st->execute([$idAlumno, $idGrupoNuevo, $idPlantel, $idEsp]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function alumno_cambiar_grupo(
    PDO $pdo,
    int $idAlumno,
    int $idGrupoNuevo,
    int $idPlantel,
    ?int $idUsuario = null
): array {
    if (!alumno_grupo_acciones_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    if ($idAlumno <= 0 || $idGrupoNuevo <= 0) {
        return ['ok' => false, 'message' => 'Datos inválidos'];
    }
    if (!plantel_grupo_pertenece($pdo, $idGrupoNuevo, $idPlantel)) {
        return ['ok' => false, 'message' => 'Grupo no válido para este plantel'];
    }
    $al = alumno_obtener($pdo, $idAlumno, $idPlantel);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    $desactivados = alumno_grupo_desactivar_misma_especialidad($pdo, $idAlumno, $idGrupoNuevo, $idPlantel);
    foreach ($desactivados as $idGrupoAnt) {
        if (function_exists('pago_extender_colegiatura_meses')) {
            $stO = $pdo->prepare('SELECT id_fase_actual, id_especialidad FROM grupos WHERE id_grupo = ?');
            $stO->execute([$idGrupoAnt]);
            $oldG = $stO->fetch(PDO::FETCH_ASSOC);
            $stN = $pdo->prepare('SELECT id_fase_actual, id_especialidad FROM grupos WHERE id_grupo = ?');
            $stN->execute([$idGrupoNuevo]);
            $newG = $stN->fetch(PDO::FETCH_ASSOC);
            $idEsp = (int) ($newG['id_especialidad'] ?? 0);
            if ($oldG && $newG && $idEsp > 0) {
                $ordOld = pago_fase_orden($pdo, (int) ($oldG['id_fase_actual'] ?? 0));
                $ordNew = pago_fase_orden($pdo, (int) ($newG['id_fase_actual'] ?? 0));
                if ($ordOld > 0 && $ordNew > 0 && $ordNew < $ordOld) {
                    pago_extender_colegiatura_meses($pdo, $idAlumno, $idEsp, $ordOld - $ordNew);
                }
            }
        }
    }

    try {
        alumno_asignar_grupo($pdo, $idAlumno, $idGrupoNuevo);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }

    $g = $pdo->prepare('SELECT clave FROM grupos WHERE id_grupo = ?');
    $g->execute([$idGrupoNuevo]);
    $clave = (string) $g->fetchColumn();

    return [
        'ok' => true,
        'message' => 'Cambio de grupo registrado. Asistencia futura en «' . $clave . '».',
        'id_grupo' => $idGrupoNuevo,
    ];
}

function alumno_fin_curso_grupo(
    PDO $pdo,
    int $idAlumno,
    int $idGrupo,
    int $idPlantel,
    ?int $idUsuario = null,
    string $nota = ''
): array {
    if (!reporte_semanal_puede_ver() && !alumno_grupo_acciones_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    $st = $pdo->prepare(
        'SELECT ag.id_alumno FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         WHERE ag.id_alumno = ? AND ag.id_grupo = ? AND g.id_plantel = ? AND ag.activo = 1 LIMIT 1'
    );
    $st->execute([$idAlumno, $idGrupo, $idPlantel]);
    if (!$st->fetchColumn()) {
        return ['ok' => false, 'message' => 'El alumno no está activo en ese grupo'];
    }

    $fecha = date('Y-m-d');
    reporte_semanal_ensure_schema($pdo);
    reporte_semanal_registrar_movimiento(
        $pdo, $idPlantel, $idAlumno, $idGrupo, 'FC', $fecha,
        null, $nota ?: 'Fin de curso', $idUsuario, 'manual'
    );

    $pdo->prepare(
        'UPDATE alumno_grupos SET activo = 0, fecha_baja = ? WHERE id_alumno = ? AND id_grupo = ?'
    )->execute([$fecha, $idAlumno, $idGrupo]);

    $activos = $pdo->prepare(
        'SELECT COUNT(*) FROM alumno_grupos WHERE id_alumno = ? AND activo = 1'
    );
    $activos->execute([$idAlumno]);
    if ((int) $activos->fetchColumn() === 0) {
        $pdo->prepare('UPDATE alumnos SET estado = \'graduado\' WHERE id_alumno = ? AND estado = \'activo\'')
            ->execute([$idAlumno]);
    }

    return ['ok' => true, 'message' => 'Fin de curso registrado para este grupo'];
}

function alumno_baja_definitiva(PDO $pdo, int $idAlumno, string $motivo): array
{
    $motivo = trim($motivo);
    if ($motivo === '') {
        return ['ok' => false, 'message' => 'Indica el motivo de la baja definitiva'];
    }
    $idPlantel = plantel_id_activo();
    $fecha = date('Y-m-d');
    if (function_exists('reporte_semanal_registrar_movimiento')) {
        reporte_semanal_ensure_schema($pdo);
        $st = $pdo->prepare('SELECT id_grupo FROM alumno_grupos WHERE id_alumno = ? AND activo = 1');
        $st->execute([$idAlumno]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $idG) {
            reporte_semanal_registrar_movimiento(
                $pdo, $idPlantel, $idAlumno, (int) $idG, 'B', $fecha,
                null, '[Definitiva] ' . $motivo, null, 'manual'
            );
        }
    }
    $pdo->prepare(
        'UPDATE alumnos SET estado = \'baja\', fecha_baja_temporal = CURDATE(),
         motivo_baja_temporal = ?, inscripcion_vigente_hasta = NULL
         WHERE id_alumno = ?'
    )->execute(['[Definitiva] ' . $motivo, $idAlumno]);
    $pdo->prepare(
        'UPDATE alumno_grupos SET activo = 0, fecha_baja = CURDATE() WHERE id_alumno = ? AND activo = 1'
    )->execute([$idAlumno]);

    if (function_exists('usuario_suspension_por_baja_alumno')) {
        usuario_suspension_por_baja_alumno($pdo, $idAlumno, '[Definitiva] ' . $motivo);
    }

    return ['ok' => true, 'message' => 'Baja definitiva registrada'];
}

function alumno_registrar_baja_grupo(
    PDO $pdo,
    int $idAlumno,
    int $idGrupo,
    int $idPlantel,
    string $tipoBaja,
    string $motivo,
    ?int $idUsuario = null
): array {
    if (!asistencia_puede_tomar() && !reporte_semanal_puede_ver()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    $tipoBaja = in_array($tipoBaja, ['temporal', 'definitiva'], true) ? $tipoBaja : 'temporal';
    $motivo = trim($motivo);
    if ($motivo === '') {
        return ['ok' => false, 'message' => 'Indique el motivo'];
    }

    $st = $pdo->prepare(
        'SELECT ag.id_alumno FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         WHERE ag.id_alumno = ? AND ag.id_grupo = ? AND g.id_plantel = ? LIMIT 1'
    );
    $st->execute([$idAlumno, $idGrupo, $idPlantel]);
    if (!$st->fetchColumn()) {
        return ['ok' => false, 'message' => 'Alumno no encontrado en el grupo'];
    }

    $fecha = date('Y-m-d');
    reporte_semanal_ensure_schema($pdo);
    reporte_semanal_registrar_movimiento(
        $pdo, $idPlantel, $idAlumno, $idGrupo, 'B', $fecha,
        null, $motivo, $idUsuario, 'manual'
    );

    $pdo->prepare(
        'UPDATE alumno_grupos SET activo = 0, fecha_baja = ? WHERE id_alumno = ? AND id_grupo = ?'
    )->execute([$fecha, $idAlumno, $idGrupo]);

    if ($tipoBaja === 'temporal') {
        $res = alumno_baja_temporal($pdo, $idAlumno, $motivo);
        return [
            'ok' => $res['ok'],
            'message' => ($res['ok'] ? 'Baja temporal registrada. ' : '') . ($res['message'] ?? ''),
        ];
    }

    $res = alumno_baja_definitiva($pdo, $idAlumno, $motivo);
    return $res;
}

/** Aplica bajas automáticas (sin asistencia en la semana) para el plantel. */
function alumno_bajas_automaticas_semana_actual(PDO $pdo, int $idPlantel): void
{
    if (!function_exists('reporte_semanal_sincronizar')) {
        return;
    }
    $sem = reporte_semanal_desde_fecha(date('Y-m-d'));
    reporte_semanal_sincronizar($pdo, $idPlantel, $sem['anio'], $sem['semana']);
}

/** Grupos del plantel para cambio (misma especialidad, excluye actuales del alumno). */
function alumno_grupos_para_cambio(PDO $pdo, int $idAlumno, int $idGrupoActual, int $idPlantel): array
{
    $idEsp = alumno_grupo_especialidad($pdo, $idGrupoActual);
    $sql = 'SELECT g.id_grupo, g.clave, CONCAT(u.nombre, \' \', u.apellido) AS profesor
            FROM grupos g
            LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
            WHERE g.id_plantel = ? AND g.id_grupo != ?';
    $params = [$idPlantel, $idGrupoActual];
    if ($idEsp !== null) {
        $sql .= ' AND g.id_especialidad = ?';
        $params[] = $idEsp;
    }
    $sql .= ' ORDER BY g.clave';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
