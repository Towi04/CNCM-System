<?php

/**
 * Captura de calificaciones por parcial (piloto inglés).
 */

/** @return array<string, string> */
function calificaciones_criterios_etiquetas(): array
{
    return [
        'listening' => 'Listening',
        'reading' => 'Reading',
        'writing' => 'Writing',
        'speaking' => 'Speaking',
        'grammar' => 'Grammar',
        'vocabulary' => 'Vocabulary',
    ];
}

function calificaciones_puede_capturar_grupo(PDO $pdo, int $idGrupo): bool
{
    if ($idGrupo <= 0) {
        return false;
    }
    $idPlantel = plantel_scope_id($pdo);
    if (!plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel)) {
        return false;
    }
    $rol = rbac_rol_efectivo();
    if (in_array($rol, ['supervisor', 'gerente', 'admin', 'director'], true)) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('calificaciones_editar_coordinacion')) {
        return true;
    }
    if ($rol !== 'profesor') {
        return false;
    }

    return grupo_docente_profesor_imparte($pdo, $idGrupo, (int) ($_SESSION['user_id'] ?? 0));
}

/** @return array<string, mixed>|null */
function calificaciones_cargar_grupo(PDO $pdo, int $idGrupo): ?array
{
    $st = $pdo->prepare(
        'SELECT g.*, e.nombre AS esp_nombre, e.clave AS esp_clave,
                u.nombre AS prof_nombre, u.apellido AS prof_apellido
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
         WHERE g.id_grupo = ? AND g.id_plantel = ?'
    );
    $st->execute([$idGrupo, plantel_scope_id($pdo)]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function calificaciones_fase_sugerida(PDO $pdo, array $grupo): int
{
    if (!empty($grupo['id_fase_actual'])) {
        return (int) $grupo['id_fase_actual'];
    }
    $idEsp = (int) ($grupo['id_especialidad'] ?? 0);
    if ($idEsp <= 0) {
        return 0;
    }
    $fases = fase_listar($pdo, $idEsp);
    if ($fases === []) {
        return 0;
    }
    $pos = academico_posicion_grupo($pdo, $grupo);
    $idx = min(count($fases) - 1, max(0, (int) $pos['indice_parcial']));

    return (int) $fases[$idx]['id_fase'];
}

function calificaciones_obtener_rubrica(PDO $pdo, int $idGrupo, int $idFase): array
{
    try {
        $st = $pdo->prepare(
            'SELECT criterios_json FROM grupo_rubrica_parcial WHERE id_grupo = ? AND id_fase = ?'
        );
        $st->execute([$idGrupo, $idFase]);
        $json = $st->fetchColumn();
        if ($json) {
            $decoded = json_decode((string) $json, true);
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }
    } catch (PDOException $e) {
        // tabla nueva
    }

    return academico_rubrica_default();
}

function calificaciones_rubrica_guardada(PDO $pdo, int $idGrupo, int $idFase): bool
{
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM grupo_rubrica_parcial WHERE id_grupo = ? AND id_fase = ? LIMIT 1'
        );
        $st->execute([$idGrupo, $idFase]);

        return (bool) $st->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/** @param list<array{codigo: string, peso_pct: float, obligatorio?: bool}> $criterios */
function calificaciones_guardar_rubrica(PDO $pdo, int $idGrupo, int $idFase, array $criterios, int $idUsuario): array
{
    $suma = 0.0;
    foreach ($criterios as $c) {
        $suma += (float) ($c['peso_pct'] ?? 0);
    }
    if (abs($suma - 100) > 0.05 && $suma > 0) {
        return ['ok' => false, 'message' => 'Los pesos deben sumar 100% (actual: ' . round($suma, 1) . '%)'];
    }

    $json = json_encode($criterios, JSON_UNESCAPED_UNICODE);
    $pdo->prepare(
        'INSERT INTO grupo_rubrica_parcial (id_grupo, id_fase, criterios_json, actualizado_por)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE criterios_json = VALUES(criterios_json), actualizado_por = VALUES(actualizado_por)'
    )->execute([$idGrupo, $idFase, $json, $idUsuario]);

    return ['ok' => true, 'message' => 'Rúbrica guardada'];
}

/** @return list<array<string, mixed>> */
function calificaciones_listar_alumnos(PDO $pdo, int $idGrupo, int $idFase): array
{
    $st = $pdo->prepare(
        "SELECT a.id_alumno, a.numero_control,
                TRIM(CONCAT(COALESCE(a.nombres, a.nombre, ''), ' ', COALESCE(a.apellido_paterno, a.apellido, ''))) AS nombre_completo,
                c.id_calificacion, c.notas_json, c.promedio, c.aprobado, c.observaciones,
                ag.en_riesgo_academico
         FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         LEFT JOIN alumno_calificacion_parcial c ON c.id_alumno = a.id_alumno AND c.id_fase = ?
         WHERE ag.id_grupo = ? AND ag.activo = 1
         ORDER BY nombre_completo"
    );
    $st->execute([$idFase, $idGrupo]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $notas = [];
        if (!empty($r['notas_json'])) {
            $decoded = json_decode((string) $r['notas_json'], true);
            if (is_array($decoded)) {
                $notas = $decoded;
            }
        }
        $r['notas'] = $notas;
        unset($r['notas_json']);
        $out[] = $r;
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function calificaciones_alumno_por_fase(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    $st = $pdo->prepare(
        'SELECT c.id_fase, c.promedio, c.aprobado, c.observaciones, c.actualizado_en,
                f.clave_fase, f.nombre_fase, f.orden,
                e.nombre AS especialidad_nombre, g.clave AS grupo_clave
         FROM alumno_calificacion_parcial c
         INNER JOIN especialidad_fases f ON f.id_fase = c.id_fase
         INNER JOIN especialidades e ON e.id_especialidad = f.id_especialidad
         LEFT JOIN grupos g ON g.id_grupo = c.id_grupo
         INNER JOIN alumnos a ON a.id_alumno = c.id_alumno
         WHERE c.id_alumno = ? AND a.id_plantel = ?
         ORDER BY e.nombre, f.orden'
    );
    $st->execute([$idAlumno, $idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @param array<string, float|int|string|null> $notas
 * @param list<array{codigo: string, peso_pct: float}> $criterios
 */
function calificaciones_guardar_alumno(
    PDO $pdo,
    int $idAlumno,
    int $idFase,
    int $idGrupo,
    array $notas,
    array $criterios,
    int $idUsuario,
    ?string $observaciones = null
): array {
    if (!calificaciones_rubrica_guardada($pdo, $idGrupo, $idFase)) {
        return [
            'ok' => false,
            'message' => 'Defina y guarde la ponderación del parcial antes de capturar calificaciones.',
        ];
    }

    foreach ($notas as $k => $v) {
        if ($v === '' || $v === null) {
            unset($notas[$k]);
            continue;
        }
        $n = (float) $v;
        if ($n < 1 || $n > 10) {
            return ['ok' => false, 'message' => 'Las notas deben estar entre 1 y 10'];
        }
        $notas[$k] = $n;
    }

    $calc = academico_calcular_promedio($notas, $criterios);
    $promedio = $calc['promedio'];
    $aprobado = $calc['aprobado'] ? 1 : 0;
    $json = json_encode($notas, JSON_UNESCAPED_UNICODE);

    $pdo->prepare(
        'INSERT INTO alumno_calificacion_parcial
         (id_alumno, id_fase, id_grupo, notas_json, promedio, aprobado, capturado_por, editado_por, observaciones)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           id_grupo = VALUES(id_grupo),
           notas_json = VALUES(notas_json),
           promedio = VALUES(promedio),
           aprobado = VALUES(aprobado),
           editado_por = VALUES(editado_por),
           observaciones = VALUES(observaciones),
           actualizado_en = NOW()'
    )->execute([
        $idAlumno,
        $idFase,
        $idGrupo,
        $json,
        $promedio,
        $aprobado,
        $idUsuario,
        $idUsuario,
        $observaciones !== '' ? $observaciones : null,
    ]);

    if ($promedio !== null) {
        $pdo->prepare(
            'INSERT INTO alumno_calificaciones_fase (id_alumno, id_fase, calificacion, observaciones)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion), observaciones = COALESCE(VALUES(observaciones), observaciones)'
        )->execute([$idAlumno, $idFase, $promedio, $observaciones]);
    }

    if ($aprobado && $idGrupo > 0) {
        $pdo->prepare(
            'UPDATE alumno_grupos SET en_riesgo_academico = 0 WHERE id_alumno = ? AND id_grupo = ?'
        )->execute([$idAlumno, $idGrupo]);
    }

    return [
        'ok' => true,
        'message' => 'Calificación guardada',
        'promedio' => $promedio,
        'aprobado' => (bool) $calc['aprobado'],
    ];
}
