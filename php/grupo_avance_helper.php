<?php

/**
 * Avance automático de parcial por grupo (cada 4 sesiones lectivas) y alertas de riesgo académico.
 */

function grupo_avance_puede_gestionar(): bool
{
    $rol = rbac_rol_efectivo();
    return in_array($rol, ['supervisor', 'gerente', 'admin'], true)
        || (function_exists('grupo_plan_puede_editar') && grupo_plan_puede_editar());
}

/** Último conteo de sesiones lectivas registrado al avanzar. */
function grupo_avance_ultimas_semanas_log(PDO $pdo, int $idGrupo): int
{
    try {
        $st = $pdo->prepare(
            'SELECT semanas_lectivas FROM grupo_avance_log WHERE id_grupo = ? ORDER BY avanzado_en DESC LIMIT 1'
        );
        $st->execute([$idGrupo]);
        $v = $st->fetchColumn();
        return $v !== false ? (int) $v : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/** @return list<array<string, mixed>> */
function grupo_avance_fases_ordenadas(PDO $pdo, int $idEspecialidad): array
{
    if ($idEspecialidad <= 0) {
        return [];
    }
    return fase_listar($pdo, $idEspecialidad);
}

/** Índice 0-based de una fase en el programa, o -1. */
function grupo_avance_indice_fase(PDO $pdo, int $idEspecialidad, int $idFase): int
{
    foreach (grupo_avance_fases_ordenadas($pdo, $idEspecialidad) as $i => $f) {
        if ((int) $f['id_fase'] === $idFase) {
            return $i;
        }
    }
    return -1;
}

/**
 * Sincroniza id_fase_actual con la posición del calendario si aún no está definida o va rezagada.
 */
function grupo_avance_sincronizar_fase_calendario(PDO $pdo, array $grupo): bool
{
    $idGrupo = (int) ($grupo['id_grupo'] ?? 0);
    $idEsp = (int) ($grupo['id_especialidad'] ?? 0);
    $fases = grupo_avance_fases_ordenadas($pdo, $idEsp);
    if ($fases === []) {
        return false;
    }

    $pos = academico_posicion_grupo($pdo, $grupo);
    $idxEsperado = min(count($fases) - 1, max(0, (int) $pos['indice_parcial']));
    $idEsperado = (int) $fases[$idxEsperado]['id_fase'];
    $actual = (int) ($grupo['id_fase_actual'] ?? 0);

    if ($actual <= 0) {
        $pdo->prepare('UPDATE grupos SET id_fase_actual = ? WHERE id_grupo = ?')->execute([$idEsperado, $idGrupo]);
        return true;
    }

    $idxActual = grupo_avance_indice_fase($pdo, $idEsp, $actual);
    if ($idxActual >= 0 && $idxEsperado > $idxActual) {
        $pdo->prepare('UPDATE grupos SET id_fase_actual = ? WHERE id_grupo = ?')->execute([$idEsperado, $idGrupo]);
        return true;
    }

    return false;
}

function grupo_avance_debe_avanzar_por_tiempo(PDO $pdo, array $grupo): bool
{
    $pos = academico_posicion_grupo($pdo, $grupo);
    if ($pos['semanas_lectivas'] < ACADEMICO_SEMANAS_POR_PARCIAL) {
        return false;
    }
    $ultima = grupo_avance_ultimas_semanas_log($pdo, (int) $grupo['id_grupo']);
    return ($pos['semanas_lectivas'] - $ultima) >= ACADEMICO_SEMANAS_POR_PARCIAL;
}

/**
 * Avanza el grupo al siguiente parcial.
 *
 * @return array{ok: bool, message: string, avanzado?: bool, id_fase_nueva?: int}
 */
function grupo_avance_ejecutar(
    PDO $pdo,
    int $idGrupo,
    bool $automatico = true,
    ?int $idUsuario = null
): array {
    $st = $pdo->prepare('SELECT * FROM grupos WHERE id_grupo = ? AND id_plantel = ? LIMIT 1');
    $st->execute([$idGrupo, plantel_id_activo()]);
    $grupo = $st->fetch(PDO::FETCH_ASSOC);
    if (!$grupo) {
        return ['ok' => false, 'message' => 'Grupo no encontrado'];
    }

    grupo_avance_sincronizar_fase_calendario($pdo, $grupo);
    $st->execute([$idGrupo, plantel_id_activo()]);
    $grupo = $st->fetch(PDO::FETCH_ASSOC);

    $idEsp = (int) ($grupo['id_especialidad'] ?? 0);
    $fases = grupo_avance_fases_ordenadas($pdo, $idEsp);
    if ($fases === []) {
        return ['ok' => false, 'message' => 'La especialidad no tiene parciales configurados'];
    }

    $idFaseActual = (int) ($grupo['id_fase_actual'] ?? 0);
    if ($idFaseActual <= 0) {
        $idFaseActual = (int) $fases[0]['id_fase'];
    }

    $idx = grupo_avance_indice_fase($pdo, $idEsp, $idFaseActual);
    if ($idx < 0) {
        $idx = 0;
        $idFaseActual = (int) $fases[0]['id_fase'];
    }

    if ($automatico && !grupo_avance_debe_avanzar_por_tiempo($pdo, $grupo)) {
        return ['ok' => true, 'message' => 'Aún no cumple 4 sesiones lectivas en el parcial actual', 'avanzado' => false];
    }

    if ($idx >= count($fases) - 1) {
        return ['ok' => true, 'message' => 'El grupo ya está en el último parcial del programa', 'avanzado' => false];
    }

    $idFaseNueva = (int) $fases[$idx + 1]['id_fase'];
    $pos = academico_posicion_grupo($pdo, $grupo);
    $semanas = (int) $pos['semanas_lectivas'];

    $pdo->prepare('UPDATE grupos SET id_fase_actual = ? WHERE id_grupo = ?')->execute([$idFaseNueva, $idGrupo]);

    $pdo->prepare(
        'INSERT INTO grupo_avance_log (id_grupo, id_fase_anterior, id_fase_nueva, semanas_lectivas, automatico, id_usuario)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $idGrupo,
        $idFaseActual,
        $idFaseNueva,
        $semanas,
        $automatico ? 1 : 0,
        $idUsuario,
    ]);

    $riesgos = grupo_avance_evaluar_riesgo_parcial($pdo, $idGrupo, $idFaseActual);
    if ($riesgos > 0) {
        grupo_avance_notificar_riesgo_coordinadores($pdo, $idGrupo, $idFaseActual, $riesgos);
    }

    $nombreNueva = $fases[$idx + 1]['clave_fase'] ?? $fases[$idx + 1]['nombre_fase'] ?? '';

    if (function_exists('moodle_grupo_sync_cursos_tras_avance')) {
        moodle_grupo_sync_cursos_tras_avance($pdo, $idGrupo, $idFaseNueva);
    }

    return [
        'ok' => true,
        'message' => 'Grupo avanzó a ' . $nombreNueva,
        'avanzado' => true,
        'id_fase_nueva' => $idFaseNueva,
        'alumnos_en_riesgo' => $riesgos,
    ];
}

/**
 * Marca en_riesgo_academico a quienes no tienen calificación aprobatoria en el parcial que cerró el grupo.
 *
 * @return int Cantidad de alumnos marcados o ya en riesgo en este cierre
 */
function grupo_avance_evaluar_riesgo_parcial(PDO $pdo, int $idGrupo, int $idFaseCerrada): int
{
    $st = $pdo->prepare(
        "SELECT ag.id_alumno, ag.en_riesgo_academico, ag.omitir_alerta_riesgo,
                c.aprobado, c.promedio
         FROM alumno_grupos ag
         LEFT JOIN alumno_calificacion_parcial c
           ON c.id_alumno = ag.id_alumno AND c.id_fase = ?
         WHERE ag.id_grupo = ? AND ag.activo = 1"
    );
    $st->execute([$idFaseCerrada, $idGrupo]);
    $marcados = 0;

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((int) ($row['omitir_alerta_riesgo'] ?? 0) === 1) {
            continue;
        }
        $aprobado = (int) ($row['aprobado'] ?? 0);
        $prom = $row['promedio'] !== null ? (float) $row['promedio'] : null;
        $enRiesgo = $aprobado !== 1 || $prom === null || $prom < ACADEMICO_NOTA_MINIMA;

        if (!$enRiesgo) {
            continue;
        }

        $pdo->prepare(
            'UPDATE alumno_grupos SET en_riesgo_academico = 1 WHERE id_grupo = ? AND id_alumno = ?'
        )->execute([$idGrupo, (int) $row['id_alumno']]);
        $marcados++;
    }

    return $marcados;
}

function grupo_avance_notificar_riesgo_coordinadores(
    PDO $pdo,
    int $idGrupo,
    int $idFase,
    int $cantidad
): void {
    $g = $pdo->prepare('SELECT clave FROM grupos WHERE id_grupo = ?');
    $g->execute([$idGrupo]);
    $clave = (string) $g->fetchColumn();
    $f = $pdo->prepare('SELECT clave_fase, nombre_fase FROM especialidad_fases WHERE id_fase = ?');
    $f->execute([$idFase]);
    $fase = $f->fetch(PDO::FETCH_ASSOC);
    $faseLbl = $fase['clave_fase'] ?? $fase['nombre_fase'] ?? 'parcial';

    $st = $pdo->prepare(
        "SELECT id_usuario FROM usuarios
         WHERE suspendido = 0 AND rol IN ('gerente', 'supervisor', 'admin')
           AND (id_plantel IS NULL OR id_plantel = ?)"
    );
    $st->execute([plantel_id_activo()]);
    $msg = $cantidad . ' alumno(s) en riesgo académico al cerrar ' . $faseLbl
        . ' en el grupo ' . $clave . ' (sin calificación ≥ 6).';
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        academico_notificar_usuario(
            $pdo,
            (int) $uid,
            'riesgo_academico',
            'Riesgo académico — ' . $clave,
            $msg,
            'academico_riesgo',
            'id_grupo=' . $idGrupo
        );
    }
}

/**
 * Procesa avance automático de todos los grupos del plantel activo.
 *
 * @return array{procesados: int, avanzados: int, detalle: list<string>}
 */
function grupo_avance_procesar_plantel(PDO $pdo, ?int $idPlantel = null): array
{
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT * FROM grupos WHERE id_plantel = ? AND id_especialidad IS NOT NULL ORDER BY clave'
    );
    $st->execute([$idPlantel]);
    $procesados = 0;
    $avanzados = 0;
    $detalle = [];

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $grupo) {
        grupo_avance_sincronizar_fase_calendario($pdo, $grupo);
        if (!grupo_avance_debe_avanzar_por_tiempo($pdo, $grupo)) {
            continue;
        }
        $procesados++;
        $res = grupo_avance_ejecutar($pdo, (int) $grupo['id_grupo'], true, null);
        if (!empty($res['avanzado'])) {
            $avanzados++;
            $detalle[] = ($grupo['clave'] ?? '') . ': ' . ($res['message'] ?? '');
        }
    }

    return ['procesados' => $procesados, 'avanzados' => $avanzados, 'detalle' => $detalle];
}

/** @return list<array<string, mixed>> */
function grupo_avance_listar_riesgo_plantel(PDO $pdo, ?int $idPlantel = null): array
{
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        "SELECT ag.id_alumno, ag.id_grupo, ag.en_riesgo_academico, ag.omitir_alerta_riesgo,
                g.clave AS grupo_clave, g.id_fase_actual,
                f.clave_fase, f.nombre_fase,
                a.numero_control,
                TRIM(CONCAT(COALESCE(a.nombres, a.nombre, ''), ' ', COALESCE(a.apellido_paterno, a.apellido, ''))) AS nombre_completo,
                c.promedio, c.aprobado, c.id_fase AS id_fase_cal
         FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual
         LEFT JOIN alumno_calificacion_parcial c ON c.id_alumno = ag.id_alumno AND c.id_fase = g.id_fase_actual
         WHERE g.id_plantel = ? AND ag.activo = 1 AND ag.en_riesgo_academico = 1 AND ag.omitir_alerta_riesgo = 0
         ORDER BY g.clave, nombre_completo"
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function grupo_avance_resolver_riesgo(
    PDO $pdo,
    int $idAlumno,
    int $idGrupo,
    string $nota,
    ?bool $alumnoAceptoCambio,
    int $idUsuario
): array {
    $nota = trim($nota);
    if ($nota === '') {
        return ['ok' => false, 'message' => 'Escriba una nota de seguimiento'];
    }

    $pdo->prepare(
        'UPDATE alumno_grupos SET omitir_alerta_riesgo = 1 WHERE id_alumno = ? AND id_grupo = ?'
    )->execute([$idAlumno, $idGrupo]);

    $pdo->prepare(
        'INSERT INTO alumno_nota_coordinacion (id_alumno, id_usuario, tipo, nota, alumno_acepto_cambio)
         VALUES (?, ?, \'riesgo_academico\', ?, ?)'
    )->execute([$idAlumno, $idUsuario, $nota, $alumnoAceptoCambio === null ? null : ($alumnoAceptoCambio ? 1 : 0)]);

    return ['ok' => true, 'message' => 'Seguimiento registrado'];
}
