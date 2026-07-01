<?php

/**
 * Planificación de fusiones de grupos — Fases 1–3.
 * Fase 3: infantil dual (ING-K + COMP-K), temario previo y graduación por grupo origen.
 */

define('GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO', 5);

function grupo_fusion_ensure_schema(PDO $pdo): void
{
    if (function_exists('grupo_clave_ensure_schema')) {
        grupo_clave_ensure_schema($pdo);
    }
    grupo_plan_ensure_schema($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_fusion_plan (
            id_fusion_plan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_especialidad INT UNSIGNED NOT NULL,
            id_grupo_a INT UNSIGNED NOT NULL,
            id_grupo_b INT UNSIGNED NOT NULL,
            id_grupo_resultante INT UNSIGNED NOT NULL COMMENT "Grupo que conserva clave y recibe alumnos",
            id_grupo_origen INT UNSIGNED NOT NULL COMMENT "Grupo absorbido",
            id_grupo_atrasado INT UNSIGNED NOT NULL,
            id_grupo_adelantado INT UNSIGNED NOT NULL,
            id_fase_destino INT UNSIGNED NOT NULL,
            fases_pendientes_json JSON NULL,
            simulacion_json JSON NULL,
            estado ENUM(\'borrador\',\'planificada\',\'activa\',\'separada\',\'completada\',\'cancelada\')
                NOT NULL DEFAULT \'borrador\',
            fecha_prevista DATE NULL,
            notas TEXT NULL,
            id_fusion_log INT UNSIGNED NULL,
            id_usuario_crea INT UNSIGNED NULL,
            confirmado_en DATETIME NULL,
            activado_en DATETIME NULL,
            separado_en DATETIME NULL,
            cancelado_en DATETIME NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_fusion_plan),
            KEY idx_gfp_plantel_estado (id_plantel, estado),
            KEY idx_gfp_grupo_a (id_grupo_a),
            KEY idx_gfp_grupo_b (id_grupo_b),
            KEY idx_gfp_resultante (id_grupo_resultante)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_fusion_pendiente_fase (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_fusion_plan INT UNSIGNED NOT NULL,
            id_grupo INT UNSIGNED NOT NULL COMMENT "Grupo que debe impartir/retomar la fase",
            id_fase INT UNSIGNED NOT NULL,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            estado ENUM(\'pendiente\',\'impartida\',\'completada\') NOT NULL DEFAULT \'pendiente\',
            PRIMARY KEY (id),
            KEY idx_gfpf_plan (id_fusion_plan),
            KEY idx_gfpf_grupo (id_grupo, estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_fusion_alumno (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_fusion_plan INT UNSIGNED NOT NULL,
            id_alumno INT UNSIGNED NOT NULL,
            id_grupo_procedencia INT UNSIGNED NOT NULL,
            debe_retomar TINYINT(1) NOT NULL DEFAULT 0,
            separado TINYINT(1) NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_gfa_plan_alumno (id_fusion_plan, id_alumno),
            KEY idx_gfa_plan (id_fusion_plan)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column(
            $pdo,
            'grupo_fusion_plan',
            'tipo',
            "ENUM('simple','kids_dual') NOT NULL DEFAULT 'simple'",
            'estado'
        );
        plantel_ensure_column(
            $pdo,
            'grupo_fusion_plan',
            'id_plan_vinculado',
            'INT UNSIGNED NULL COMMENT "Plan pareja (dual kids)"',
            'tipo'
        );
        plantel_ensure_column(
            $pdo,
            'grupo_fusion_alumno',
            'id_grupo_graduacion',
            'INT UNSIGNED NULL COMMENT "Grupo origen para alertas/graduación"',
            'id_grupo_procedencia'
        );
    }
}

function grupo_fusion_puede_gestionar(): bool
{
    return function_exists('grupo_plan_puede_editar') && grupo_plan_puede_editar();
}

function grupo_fusion_puede_ver(): bool
{
    if (function_exists('cronologia_puede_ver') && cronologia_puede_ver()) {
        return true;
    }
    return function_exists('grupo_plan_puede_editar') && grupo_plan_puede_editar();
}

/** @return list<array<string, mixed>> */
function grupo_fusion_listar_especialidades(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        'SELECT DISTINCT e.id_especialidad, e.nombre, e.clave
         FROM especialidades e
         INNER JOIN grupos g ON g.id_especialidad = e.id_especialidad AND g.id_plantel = ?
         WHERE e.activo = 1
         ORDER BY e.nombre'
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{ingles: int, computacion: int, disponible: bool} */
function grupo_fusion_kids_config(PDO $pdo): array
{
    $kids = function_exists('combo_ids_kids') ? combo_ids_kids($pdo) : ['ingles' => 0, 'computacion' => 0];

    return [
        'ingles' => (int) ($kids['ingles'] ?? 0),
        'computacion' => (int) ($kids['computacion'] ?? 0),
        'disponible' => (int) ($kids['ingles'] ?? 0) > 0 && (int) ($kids['computacion'] ?? 0) > 0,
    ];
}

function grupo_fusion_es_especialidad_kids(PDO $pdo, int $idEspecialidad): bool
{
    if ($idEspecialidad <= 0) {
        return false;
    }
    $kids = grupo_fusion_kids_config($pdo);

    return in_array($idEspecialidad, [$kids['ingles'], $kids['computacion']], true);
}

/** Contexto de plan de parciales / temario comprimido (solo coordinación). */
function grupo_fusion_temario_contexto(PDO $pdo, int $idGrupo): array
{
    if ($idGrupo <= 0 || !function_exists('grupo_plan_pendientes_retomar')) {
        return ['pendientes' => [], 'plan_mes' => null, 'tiene_compresion' => false];
    }

    $pendientes = grupo_plan_pendientes_retomar($pdo, $idGrupo);
    $anio = (int) date('Y');
    $mes = (int) date('n');
    $planMes = function_exists('grupo_plan_obtener') ? grupo_plan_obtener($pdo, $idGrupo, $anio, $mes) : null;
    $tieneCompresion = false;
    if ($planMes) {
        $temario = $planMes['fases_temario'] ?? [];
        $tieneCompresion = count($temario) > 1;
    }

    $pendOut = [];
    foreach ($pendientes as $p) {
        $pendOut[] = [
            'id_plan' => (int) ($p['id_plan'] ?? 0),
            'anio' => (int) ($p['anio'] ?? 0),
            'mes' => (int) ($p['mes'] ?? 0),
            'clave_registro' => (string) ($p['clave_registro'] ?? ''),
            'temas_retomar' => (string) ($p['temas_retomar'] ?? ''),
            'nota' => (string) ($p['nota_coordinador'] ?? ''),
        ];
    }

    $planOut = null;
    if ($planMes) {
        $planOut = [
            'anio' => $anio,
            'mes' => $mes,
            'clave_registro' => (string) ($planMes['clave_registro'] ?? ''),
            'fases_temario' => array_map(static fn ($f) => (string) ($f['clave_fase'] ?? ''), $planMes['fases_temario'] ?? []),
            'temas_retomar' => (string) ($planMes['temas_retomar'] ?? ''),
            'nota_coordinador' => (string) ($planMes['nota_coordinador'] ?? ''),
            'compresion' => $tieneCompresion,
        ];
    }

    return [
        'pendientes' => $pendOut,
        'plan_mes' => $planOut,
        'tiene_compresion' => $tieneCompresion || $pendOut !== [],
    ];
}

/** Alumnos inscritos en inglés kids y computación kids a la vez dentro de un grupo. */
function grupo_fusion_alumnos_dual_grupo(PDO $pdo, int $idGrupoIng, int $idEspComp): array
{
    if ($idGrupoIng <= 0 || $idEspComp <= 0) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT DISTINCT ag1.id_alumno
         FROM alumno_grupos ag1
         INNER JOIN alumno_grupos ag2 ON ag2.id_alumno = ag1.id_alumno AND ag2.activo = 1
         INNER JOIN grupos gc ON gc.id_grupo = ag2.id_grupo AND gc.id_especialidad = ?
         WHERE ag1.id_grupo = ? AND ag1.activo = 1'
    );
    $st->execute([$idEspComp, $idGrupoIng]);

    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Sugiere grupos de computación kids emparejables con un grupo de inglés kids.
 *
 * @return list<array<string, mixed>>
 */
function grupo_fusion_parejas_comp_sugeridas(PDO $pdo, int $idPlantel, int $idGrupoIng, int $idEspComp): array
{
    $gIng = grupo_fusion_cargar_grupo($pdo, $idPlantel, $idGrupoIng);
    if (!$gIng) {
        return [];
    }
    $alDual = grupo_fusion_alumnos_dual_grupo($pdo, $idGrupoIng, $idEspComp);
    if ($alDual === []) {
        return [];
    }

    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.codigo_horario,
                COUNT(DISTINCT ag.id_alumno) AS alumnos_dual
         FROM grupos g
         INNER JOIN alumno_grupos ag ON ag.id_grupo = g.id_grupo AND ag.activo = 1
         WHERE g.id_plantel = ? AND g.id_especialidad = ?
           AND ag.id_alumno IN (' . implode(',', array_fill(0, count($alDual), '?')) . ')
         GROUP BY g.id_grupo, g.clave, g.codigo_horario
         ORDER BY alumnos_dual DESC, g.clave ASC
         LIMIT 8'
    );
    $params = array_merge([$idPlantel, $idEspComp], $alDual);
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[] = [
            'id_grupo' => (int) $row['id_grupo'],
            'clave' => (string) ($row['clave'] ?? ''),
            'codigo_horario' => (string) ($row['codigo_horario'] ?? ''),
            'alumnos_dual' => (int) ($row['alumnos_dual'] ?? 0),
            'mismo_horario' => ($row['codigo_horario'] ?? '') === ($gIng['codigo_horario'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $fases
 * @return array{indice: int, id_fase: int, clave: string, nombre: string, origen: string}
 */
function grupo_fusion_indice_fase(PDO $pdo, array $grupo, array $fases): array
{
    $idActual = (int) ($grupo['id_fase_actual'] ?? 0);
    if ($idActual > 0) {
        foreach ($fases as $i => $f) {
            if ((int) ($f['id_fase'] ?? 0) === $idActual) {
                return [
                    'indice' => $i,
                    'id_fase' => $idActual,
                    'clave' => (string) ($f['clave_fase'] ?? ''),
                    'nombre' => (string) ($f['nombre_fase'] ?? ''),
                    'origen' => 'registrada',
                ];
            }
        }
    }

    $pos = academico_posicion_grupo($pdo, $grupo);
    $idx = min(count($fases) - 1, max(0, (int) $pos['indice_parcial']));
    $f = $fases[$idx] ?? [];

    return [
        'indice' => $idx,
        'id_fase' => (int) ($f['id_fase'] ?? 0),
        'clave' => (string) ($f['clave_fase'] ?? ''),
        'nombre' => (string) ($f['nombre_fase'] ?? ''),
        'origen' => 'calendario',
    ];
}

function grupo_fusion_es_repaso(array $fase): bool
{
    $clave = strtoupper(trim((string) ($fase['clave_fase'] ?? '')));
    $nombre = mb_strtolower(trim((string) ($fase['nombre_fase'] ?? '')));
    if (str_contains($nombre, 'repaso') || str_contains($nombre, 'review')) {
        return true;
    }
    if (preg_match('/[-+]4$/', $clave)) {
        return true;
    }

    return false;
}

/**
 * @param array{
 *   id_especialidad?: int,
 *   id_profesor?: int,
 *   q?: string,
 *   estado?: string,
 *   solo_recomendados?: bool
 * } $filtros
 * @return array<string, mixed>
 */
function grupo_fusion_matriz(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    fase_ensure_schema($pdo);

    $idEsp = (int) ($filtros['id_especialidad'] ?? 0);
    if ($idEsp <= 0) {
        return [
            'fases' => [],
            'grupos' => [],
            'total' => 0,
            'recomendados' => 0,
            'umbral_alumnos' => GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO,
            'id_especialidad' => 0,
        ];
    }

    $fases = fase_listar($pdo, $idEsp);
    $params = [$idPlantel, $idEsp];
    $sql = 'SELECT g.*, e.nombre AS esp_nombre, e.clave AS esp_clave,
                   CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre,
                   gh.hora_inicio, gh.hora_fin,
                   (SELECT COUNT(*) FROM alumno_grupos ag
                    INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.id_plantel = g.id_plantel
                    WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1) AS total_alumnos
            FROM grupos g
            LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
            LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
            LEFT JOIN (
                SELECT id_grupo, MIN(hora_inicio) AS hora_inicio, MAX(hora_fin) AS hora_fin
                FROM grupo_horarios WHERE activo = 1 GROUP BY id_grupo
            ) gh ON gh.id_grupo = g.id_grupo
            WHERE g.id_plantel = ? AND g.id_especialidad = ?';

    if (!empty($filtros['id_profesor'])) {
        $sql .= ' AND g.id_profesor = ?';
        $params[] = (int) $filtros['id_profesor'];
    }
    if (!empty($filtros['q'])) {
        $sql .= ' AND g.clave LIKE ?';
        $params[] = '%' . trim((string) $filtros['q']) . '%';
    }

    $sql .= ' ORDER BY total_alumnos ASC, g.clave ASC';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $planesMap = grupo_fusion_map_planes_abiertos($pdo, $idPlantel);

    $gruposOut = [];
    $recomendados = 0;
    $filtroEstado = trim((string) ($filtros['estado'] ?? 'activo'));
    $soloRecom = !empty($filtros['solo_recomendados']);

    foreach ($rows as $g) {
        $totalAlumnos = (int) ($g['total_alumnos'] ?? 0);
        $estadoGrupo = cronologia_estado_grupo($pdo, $g, $totalAlumnos);

        if ($filtroEstado === 'activo' && $estadoGrupo !== 'activo' && $estadoGrupo !== 'programado') {
            continue;
        }
        if ($filtroEstado === 'fin_curso' && $estadoGrupo !== 'fin_curso') {
            continue;
        }

        $posFase = grupo_fusion_indice_fase($pdo, $g, $fases);
        $recomienda = $estadoGrupo === 'activo'
            && $totalAlumnos > 0
            && $totalAlumnos <= GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO;

        if ($soloRecom && !$recomienda) {
            continue;
        }
        if ($recomienda) {
            $recomendados++;
        }

        $hi = substr((string) ($g['hora_inicio'] ?? ''), 0, 5);
        $hf = substr((string) ($g['hora_fin'] ?? ''), 0, 5);
        $horario = ($hi !== '' && $hf !== '') ? ($hi . '–' . $hf) : trim((string) ($g['horario_texto'] ?? ''));

        $celdas = [];
        foreach ($fases as $i => $f) {
            $celdas[] = [
                'id_fase' => (int) ($f['id_fase'] ?? 0),
                'indice' => $i,
                'estado' => $i < $posFase['indice'] ? 'pasada' : ($i === $posFase['indice'] ? 'actual' : 'futura'),
                'es_repaso' => grupo_fusion_es_repaso($f),
            ];
        }

        $gruposOut[] = [
            'id_grupo' => (int) $g['id_grupo'],
            'clave' => $g['clave'] ?? '',
            'total_alumnos' => $totalAlumnos,
            'recomienda_fusion' => $recomienda,
            'aula' => trim((string) ($g['aula'] ?? '')) ?: '—',
            'horario' => $horario ?: '—',
            'dia' => cronologia_dia_horario_label($g['codigo_horario'] ?? ''),
            'profesor_nombre' => trim($g['profesor_nombre'] ?? '') ?: '—',
            'estado_grupo' => $estadoGrupo,
            'fusiones_total' => (int) ($g['fusiones_total'] ?? 0),
            'fusion_desfase' => (string) ($g['fusion_desfase'] ?? 'ninguno'),
            'indice_fase' => $posFase['indice'],
            'id_fase_actual' => $posFase['id_fase'],
            'fase_clave' => $posFase['clave'],
            'fase_nombre' => $posFase['nombre'],
            'fase_origen' => $posFase['origen'],
            'celdas' => $celdas,
            'planes' => $planesMap[(int) $g['id_grupo']] ?? [],
            'temario' => grupo_fusion_temario_contexto($pdo, (int) $g['id_grupo']),
        ];
    }

    $fasesOut = [];
    foreach ($fases as $i => $f) {
        $fasesOut[] = [
            'id_fase' => (int) ($f['id_fase'] ?? 0),
            'indice' => $i,
            'clave_fase' => (string) ($f['clave_fase'] ?? ''),
            'nombre_fase' => (string) ($f['nombre_fase'] ?? ''),
            'es_repaso' => grupo_fusion_es_repaso($f),
            'nivel_cefr' => (string) ($f['nivel_cefr'] ?? ''),
        ];
    }

    return [
        'fases' => $fasesOut,
        'grupos' => $gruposOut,
        'total' => count($gruposOut),
        'recomendados' => $recomendados,
        'umbral_alumnos' => GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO,
        'id_especialidad' => $idEsp,
    ];
}

/**
 * @return array<string, mixed>
 */
function grupo_fusion_cargar_grupo(PDO $pdo, int $idPlantel, int $idGrupo): ?array
{
    $st = $pdo->prepare(
        'SELECT g.*,
                (SELECT COUNT(*) FROM alumno_grupos ag
                 INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.id_plantel = g.id_plantel
                 WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1) AS total_alumnos
         FROM grupos g WHERE g.id_grupo = ? AND g.id_plantel = ? LIMIT 1'
    );
    $st->execute([$idGrupo, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Simula fusión de dos grupos de la misma especialidad.
 *
 * @return array<string, mixed>
 */
function grupo_fusion_simular(PDO $pdo, int $idPlantel, int $idGrupoA, int $idGrupoB, ?int $idFaseDestino = null): array
{
    if ($idGrupoA <= 0 || $idGrupoB <= 0 || $idGrupoA === $idGrupoB) {
        return ['ok' => false, 'message' => 'Seleccione dos grupos distintos.'];
    }

    $gA = grupo_fusion_cargar_grupo($pdo, $idPlantel, $idGrupoA);
    $gB = grupo_fusion_cargar_grupo($pdo, $idPlantel, $idGrupoB);
    if (!$gA || !$gB) {
        return ['ok' => false, 'message' => 'Uno o ambos grupos no existen en este plantel.'];
    }

    $idEspA = (int) ($gA['id_especialidad'] ?? 0);
    $idEspB = (int) ($gB['id_especialidad'] ?? 0);
    if ($idEspA <= 0 || $idEspA !== $idEspB) {
        return [
            'ok' => false,
            'message' => 'Ambos grupos deben ser de la misma especialidad (niños / dual requiere fase 3).',
        ];
    }

    $fases = fase_listar($pdo, $idEspA);
    if ($fases === []) {
        return ['ok' => false, 'message' => 'La especialidad no tiene fases configuradas.'];
    }

    $posA = grupo_fusion_indice_fase($pdo, $gA, $fases);
    $posB = grupo_fusion_indice_fase($pdo, $gB, $fases);
    $idxA = $posA['indice'];
    $idxB = $posB['indice'];

    $idxDestino = -1;
    if ($idFaseDestino !== null && $idFaseDestino > 0) {
        foreach ($fases as $i => $f) {
            if ((int) ($f['id_fase'] ?? 0) === $idFaseDestino) {
                $idxDestino = $i;
                break;
            }
        }
        if ($idxDestino < 0) {
            return ['ok' => false, 'message' => 'Fase destino no válida para esta especialidad.'];
        }
    } else {
        $idxDestino = min(count($fases) - 1, max($idxA, $idxB) + 1);
    }

    if ($idxDestino <= max($idxA, $idxB) && $idxA !== $idxB) {
        $idxDestino = min(count($fases) - 1, max($idxA, $idxB) + 1);
    }

    $grupoAtrasado = $idxA <= $idxB ? 'A' : 'B';
    $idxAtrasado = min($idxA, $idxB);
    $idxAdelantado = max($idxA, $idxB);
    $idGrupoAtrasado = $grupoAtrasado === 'A' ? $idGrupoA : $idGrupoB;
    $idGrupoAdelantado = $grupoAtrasado === 'A' ? $idGrupoB : $idGrupoA;
    $claveAtrasado = $grupoAtrasado === 'A' ? ($gA['clave'] ?? '') : ($gB['clave'] ?? '');
    $claveAdelantado = $grupoAtrasado === 'A' ? ($gB['clave'] ?? '') : ($gA['clave'] ?? '');

    $fasesSaltadas = [];
    if ($idxDestino > $idxAtrasado + 1) {
        for ($i = $idxAtrasado + 1; $i < $idxDestino; $i++) {
            if (!isset($fases[$i])) {
                continue;
            }
            $fasesSaltadas[] = [
                'indice' => $i,
                'id_fase' => (int) ($fases[$i]['id_fase'] ?? 0),
                'clave_fase' => (string) ($fases[$i]['clave_fase'] ?? ''),
                'nombre_fase' => (string) ($fases[$i]['nombre_fase'] ?? ''),
                'es_repaso' => grupo_fusion_es_repaso($fases[$i]),
            ];
        }
    }

    $faseDest = $fases[$idxDestino] ?? null;
    $repasosOmitidos = array_values(array_filter($fasesSaltadas, static fn (array $f): bool => !empty($f['es_repaso'])));

    $alumnosA = (int) ($gA['total_alumnos'] ?? 0);
    $alumnosB = (int) ($gB['total_alumnos'] ?? 0);
    $combinados = $alumnosA + $alumnosB;

    $notas = [];
    if ($alumnosA <= GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO && $alumnosA > 0) {
        $notas[] = sprintf(
            '%s tiene %d alumno(s) — dentro del umbral usual de fusión (≤%d).',
            $gA['clave'] ?? 'Grupo A',
            $alumnosA,
            GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO
        );
    }
    if ($alumnosB <= GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO && $alumnosB > 0) {
        $notas[] = sprintf(
            '%s tiene %d alumno(s) — dentro del umbral usual de fusión (≤%d).',
            $gB['clave'] ?? 'Grupo B',
            $alumnosB,
            GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO
        );
    }
    $notas[] = sprintf('Combinados quedarían %d alumnos activos en un solo grupo.', $combinados);
    if ($fasesSaltadas !== []) {
        $notas[] = sprintf(
            'El grupo %s deberá retomar %d fase(s) omitidas al separarse antes de graduarse.',
            $claveAtrasado,
            count($fasesSaltadas)
        );
    }
    if ($repasosOmitidos !== []) {
        $notas[] = 'Incluye mes(es) de repaso que podrían omitirse al fusionar (inglés).';
    }
    if ($idxA === $idxB) {
        $notas[] = 'Ambos grupos están en la misma fase; pueden continuar juntos al siguiente parcial.';
    }

    $fasesOpciones = [];
    $idxSugerido = min(count($fases) - 1, max($idxA, $idxB) + 1);
    foreach ($fases as $i => $f) {
        $fasesOpciones[] = [
            'indice' => $i,
            'id_fase' => (int) ($f['id_fase'] ?? 0),
            'clave_fase' => (string) ($f['clave_fase'] ?? ''),
            'nombre_fase' => (string) ($f['nombre_fase'] ?? ''),
            'es_repaso' => grupo_fusion_es_repaso($f),
            'sugerida' => $i === $idxSugerido,
        ];
    }

    return [
        'ok' => true,
        'grupo_a' => [
            'id_grupo' => $idGrupoA,
            'clave' => $gA['clave'] ?? '',
            'total_alumnos' => $alumnosA,
            'indice_fase' => $idxA,
            'fase_clave' => $posA['clave'],
            'fase_nombre' => $posA['nombre'],
            'recomienda_fusion' => $alumnosA > 0 && $alumnosA <= GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO,
        ],
        'grupo_b' => [
            'id_grupo' => $idGrupoB,
            'clave' => $gB['clave'] ?? '',
            'total_alumnos' => $alumnosB,
            'indice_fase' => $idxB,
            'fase_clave' => $posB['clave'],
            'fase_nombre' => $posB['nombre'],
            'recomienda_fusion' => $alumnosB > 0 && $alumnosB <= GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO,
        ],
        'alumnos_combinados' => $combinados,
        'fase_destino' => [
            'indice' => $idxDestino,
            'id_fase' => (int) ($faseDest['id_fase'] ?? 0),
            'clave_fase' => (string) ($faseDest['clave_fase'] ?? ''),
            'nombre_fase' => (string) ($faseDest['nombre_fase'] ?? ''),
            'es_repaso' => $faseDest ? grupo_fusion_es_repaso($faseDest) : false,
        ],
        'grupo_atrasado_clave' => $claveAtrasado,
        'grupo_adelantado_clave' => $claveAdelantado,
        'id_grupo_atrasado' => $idGrupoAtrasado,
        'id_grupo_adelantado' => $idGrupoAdelantado,
        'fases_saltadas' => $fasesSaltadas,
        'fases_pendientes_retomar' => $fasesSaltadas,
        'repasos_omitidos' => $repasosOmitidos,
        'notas' => $notas,
        'fases_opciones' => $fasesOpciones,
        'temario_a' => grupo_fusion_temario_contexto($pdo, $idGrupoA),
        'temario_b' => grupo_fusion_temario_contexto($pdo, $idGrupoB),
    ];
}

/** @return array<string, list<array<string, mixed>>> */
function grupo_fusion_map_planes_abiertos(PDO $pdo, int $idPlantel): array
{
    grupo_fusion_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT p.*, fd.clave_fase AS dest_clave, fd.nombre_fase AS dest_nombre,
                ga.clave AS clave_a, gb.clave AS clave_b, gr.clave AS clave_resultante
         FROM grupo_fusion_plan p
         LEFT JOIN especialidad_fases fd ON fd.id_fase = p.id_fase_destino
         LEFT JOIN grupos ga ON ga.id_grupo = p.id_grupo_a
         LEFT JOIN grupos gb ON gb.id_grupo = p.id_grupo_b
         LEFT JOIN grupos gr ON gr.id_grupo = p.id_grupo_resultante
         WHERE p.id_plantel = ? AND p.estado IN (\'borrador\',\'planificada\',\'activa\',\'separada\')
         ORDER BY p.creado_en DESC'
    );
    $st->execute([$idPlantel]);
    $map = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        foreach (['id_grupo_a', 'id_grupo_b', 'id_grupo_resultante', 'id_grupo_origen', 'id_grupo_atrasado', 'id_grupo_adelantado'] as $col) {
            $idG = (int) ($row[$col] ?? 0);
            if ($idG > 0) {
                $map[$idG][] = grupo_fusion_formatear_plan($row);
            }
        }
    }

    return $map;
}

/** @param array<string, mixed> $row */
function grupo_fusion_formatear_plan(array $row): array
{
    return [
        'id_fusion_plan' => (int) ($row['id_fusion_plan'] ?? 0),
        'estado' => (string) ($row['estado'] ?? ''),
        'clave_a' => (string) ($row['clave_a'] ?? ''),
        'clave_b' => (string) ($row['clave_b'] ?? ''),
        'clave_resultante' => (string) ($row['clave_resultante'] ?? ''),
        'dest_clave' => (string) ($row['dest_clave'] ?? ''),
        'dest_nombre' => (string) ($row['dest_nombre'] ?? ''),
        'fecha_prevista' => $row['fecha_prevista'] ?? null,
        'tipo' => (string) ($row['tipo'] ?? 'simple'),
        'id_plan_vinculado' => (int) ($row['id_plan_vinculado'] ?? 0) ?: null,
    ];
}

/** @return array<string, mixed>|null */
function grupo_fusion_obtener(PDO $pdo, int $idPlantel, int $idPlan): ?array
{
    grupo_fusion_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT p.*, fd.clave_fase AS dest_clave, fd.nombre_fase AS dest_nombre,
                ga.clave AS clave_a, gb.clave AS clave_b,
                gr.clave AS clave_resultante, go.clave AS clave_origen,
                gat.clave AS clave_atrasado, gad.clave AS clave_adelantado,
                e.nombre AS esp_nombre
         FROM grupo_fusion_plan p
         LEFT JOIN especialidad_fases fd ON fd.id_fase = p.id_fase_destino
         LEFT JOIN grupos ga ON ga.id_grupo = p.id_grupo_a
         LEFT JOIN grupos gb ON gb.id_grupo = p.id_grupo_b
         LEFT JOIN grupos gr ON gr.id_grupo = p.id_grupo_resultante
         LEFT JOIN grupos go ON go.id_grupo = p.id_grupo_origen
         LEFT JOIN grupos gat ON gat.id_grupo = p.id_grupo_atrasado
         LEFT JOIN grupos gad ON gad.id_grupo = p.id_grupo_adelantado
         LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
         WHERE p.id_fusion_plan = ? AND p.id_plantel = ? LIMIT 1'
    );
    $st->execute([$idPlan, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $row['fases_pendientes'] = json_decode((string) ($row['fases_pendientes_json'] ?? '[]'), true) ?: [];
    $row['simulacion'] = json_decode((string) ($row['simulacion_json'] ?? '{}'), true) ?: [];

    $stP = $pdo->prepare(
        'SELECT pf.*, f.clave_fase, f.nombre_fase
         FROM grupo_fusion_pendiente_fase pf
         INNER JOIN especialidad_fases f ON f.id_fase = pf.id_fase
         WHERE pf.id_fusion_plan = ? ORDER BY pf.orden ASC'
    );
    $stP->execute([$idPlan]);
    $row['pendientes_fase'] = $stP->fetchAll(PDO::FETCH_ASSOC);

    $stA = $pdo->prepare(
        'SELECT fa.*, a.numero_control,
                TRIM(CONCAT(COALESCE(a.nombres, a.nombre, \'\'), \' \', COALESCE(a.apellido_paterno, a.apellido, \'\'))) AS alumno
         FROM grupo_fusion_alumno fa
         INNER JOIN alumnos a ON a.id_alumno = fa.id_alumno
         WHERE fa.id_fusion_plan = ? ORDER BY fa.debe_retomar DESC, alumno ASC'
    );
    $stA->execute([$idPlan]);
    $row['alumnos_fusion'] = $stA->fetchAll(PDO::FETCH_ASSOC);

    $idVinc = (int) ($row['id_plan_vinculado'] ?? 0);
    if ($idVinc > 0) {
        $stV = $pdo->prepare(
            'SELECT p.*, fd.clave_fase AS dest_clave, ga.clave AS clave_a, gb.clave AS clave_b,
                    gr.clave AS clave_resultante, e.clave AS esp_clave
             FROM grupo_fusion_plan p
             LEFT JOIN especialidad_fases fd ON fd.id_fase = p.id_fase_destino
             LEFT JOIN grupos ga ON ga.id_grupo = p.id_grupo_a
             LEFT JOIN grupos gb ON gb.id_grupo = p.id_grupo_b
             LEFT JOIN grupos gr ON gr.id_grupo = p.id_grupo_resultante
             LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
             WHERE p.id_fusion_plan = ? AND p.id_plantel = ? LIMIT 1'
        );
        $stV->execute([$idVinc, $idPlantel]);
        $vinc = $stV->fetch(PDO::FETCH_ASSOC);
        $row['plan_vinculado'] = $vinc ? grupo_fusion_formatear_plan($vinc) : null;
    }

    return $row;
}

/** @return list<array<string, mixed>> */
function grupo_fusion_listar_planes(PDO $pdo, int $idPlantel, ?string $estado = null, ?int $idEspecialidad = null): array
{
    grupo_fusion_ensure_schema($pdo);
    $params = [$idPlantel];
    $sql = 'SELECT p.*, fd.clave_fase AS dest_clave, ga.clave AS clave_a, gb.clave AS clave_b,
                   gr.clave AS clave_resultante
            FROM grupo_fusion_plan p
            LEFT JOIN especialidad_fases fd ON fd.id_fase = p.id_fase_destino
            LEFT JOIN grupos ga ON ga.id_grupo = p.id_grupo_a
            LEFT JOIN grupos gb ON gb.id_grupo = p.id_grupo_b
            LEFT JOIN grupos gr ON gr.id_grupo = p.id_grupo_resultante
            WHERE p.id_plantel = ?';
    if ($estado !== null && $estado !== '') {
        $sql .= ' AND p.estado = ?';
        $params[] = $estado;
    } else {
        $sql .= ' AND p.estado NOT IN (\'cancelada\', \'completada\')';
    }
    if ($idEspecialidad !== null && $idEspecialidad > 0) {
        $sql .= ' AND p.id_especialidad = ?';
        $params[] = $idEspecialidad;
    }
    $sql .= ' ORDER BY FIELD(p.estado, \'activa\', \'planificada\', \'borrador\', \'separada\'), p.creado_en DESC LIMIT 100';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function grupo_fusion_grupo_en_plan_activo(PDO $pdo, int $idPlantel, int $idGrupo): ?array
{
    $st = $pdo->prepare(
        'SELECT id_fusion_plan FROM grupo_fusion_plan
         WHERE id_plantel = ? AND estado IN (\'planificada\', \'activa\')
           AND (id_grupo_a = ? OR id_grupo_b = ? OR id_grupo_resultante = ? OR id_grupo_origen = ?)
         LIMIT 1'
    );
    $st->execute([$idPlantel, $idGrupo, $idGrupo, $idGrupo, $idGrupo]);
    $id = (int) $st->fetchColumn();

    return $id > 0 ? ['id_fusion_plan' => $id] : null;
}

/**
 * @param array<string, mixed> $sim
 * @return array{ok: bool, message?: string, id_fusion_plan?: int}
 */
function grupo_fusion_guardar_plan(
    PDO $pdo,
    int $idPlantel,
    array $sim,
    int $idGrupoResultante,
    ?string $fechaPrevista = null,
    string $notas = '',
    ?int $idUsuario = null,
    ?int $idPlanExistente = null,
    string $tipo = 'simple',
    ?int $idPlanVinculado = null
): array {
    if (!grupo_fusion_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso para gestionar fusiones'];
    }
    if (empty($sim['ok'])) {
        return ['ok' => false, 'message' => 'Simulación inválida'];
    }

    grupo_fusion_ensure_schema($pdo);

    $idA = (int) ($sim['grupo_a']['id_grupo'] ?? 0);
    $idB = (int) ($sim['grupo_b']['id_grupo'] ?? 0);
    if (!in_array($idGrupoResultante, [$idA, $idB], true)) {
        $alA = (int) ($sim['grupo_a']['total_alumnos'] ?? 0);
        $alB = (int) ($sim['grupo_b']['total_alumnos'] ?? 0);
        $idGrupoResultante = $alA >= $alB ? $idA : $idB;
    }
    $idOrigen = $idGrupoResultante === $idA ? $idB : $idA;

    foreach ([$idA, $idB] as $idG) {
        $conf = grupo_fusion_grupo_en_plan_activo($pdo, $idPlantel, $idG);
        if ($conf && (!$idPlanExistente || (int) $conf['id_fusion_plan'] !== $idPlanExistente)) {
            return ['ok' => false, 'message' => 'Uno de los grupos ya tiene un plan activo o planificado'];
        }
    }

    $gR = grupo_fusion_cargar_grupo($pdo, $idPlantel, $idGrupoResultante);
    if (!$gR) {
        return ['ok' => false, 'message' => 'Grupo resultante no válido'];
    }

    $idEsp = (int) ($gR['id_especialidad'] ?? 0);
    $idDest = (int) ($sim['fase_destino']['id_fase'] ?? 0);
    $fasesPend = $sim['fases_pendientes_retomar'] ?? $sim['fases_saltadas'] ?? [];
    $jsonPend = json_encode($fasesPend, JSON_UNESCAPED_UNICODE);
    $jsonSim = json_encode($sim, JSON_UNESCAPED_UNICODE);

    $fechaPrevista = ($fechaPrevista !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPrevista))
        ? $fechaPrevista
        : null;

    $tipo = $tipo === 'kids_dual' ? 'kids_dual' : 'simple';

    if ($idPlanExistente) {
        $plan = grupo_fusion_obtener($pdo, $idPlantel, $idPlanExistente);
        if (!$plan || !in_array($plan['estado'], ['borrador', 'planificada'], true)) {
            return ['ok' => false, 'message' => 'Solo se pueden editar planes en borrador o planificados'];
        }
        $pdo->prepare(
            'UPDATE grupo_fusion_plan SET
                id_grupo_a = ?, id_grupo_b = ?, id_grupo_resultante = ?, id_grupo_origen = ?,
                id_grupo_atrasado = ?, id_grupo_adelantado = ?, id_fase_destino = ?,
                fases_pendientes_json = ?, simulacion_json = ?, fecha_prevista = ?, notas = ?,
                tipo = ?, id_plan_vinculado = ?
             WHERE id_fusion_plan = ? AND id_plantel = ?'
        )->execute([
            $idA, $idB, $idGrupoResultante, $idOrigen,
            (int) ($sim['id_grupo_atrasado'] ?? 0),
            (int) ($sim['id_grupo_adelantado'] ?? 0),
            $idDest, $jsonPend, $jsonSim, $fechaPrevista, trim($notas) ?: null,
            $tipo, $idPlanVinculado,
            $idPlanExistente, $idPlantel,
        ]);

        return ['ok' => true, 'message' => 'Plan actualizado', 'id_fusion_plan' => $idPlanExistente];
    }

    $pdo->prepare(
        'INSERT INTO grupo_fusion_plan (
            id_plantel, id_especialidad, id_grupo_a, id_grupo_b, id_grupo_resultante, id_grupo_origen,
            id_grupo_atrasado, id_grupo_adelantado, id_fase_destino, fases_pendientes_json, simulacion_json,
            estado, fecha_prevista, notas, id_usuario_crea, tipo, id_plan_vinculado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'borrador\', ?, ?, ?, ?, ?)'
    )->execute([
        $idPlantel, $idEsp, $idA, $idB, $idGrupoResultante, $idOrigen,
        (int) ($sim['id_grupo_atrasado'] ?? 0),
        (int) ($sim['id_grupo_adelantado'] ?? 0),
        $idDest, $jsonPend, $jsonSim, $fechaPrevista, trim($notas) ?: null, $idUsuario,
        $tipo, $idPlanVinculado,
    ]);

    return ['ok' => true, 'message' => 'Plan guardado como borrador', 'id_fusion_plan' => (int) $pdo->lastInsertId()];
}

function grupo_fusion_confirmar(PDO $pdo, int $idPlantel, int $idPlan, ?int $idUsuario = null): array
{
    if (!grupo_fusion_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $plan = grupo_fusion_obtener($pdo, $idPlantel, $idPlan);
    if (!$plan || $plan['estado'] !== 'borrador') {
        return ['ok' => false, 'message' => 'Solo se confirman planes en borrador'];
    }

    $pdo->prepare(
        'UPDATE grupo_fusion_plan SET estado = \'planificada\', confirmado_en = NOW() WHERE id_fusion_plan = ? AND id_plantel = ?'
    )->execute([$idPlan, $idPlantel]);

    return ['ok' => true, 'message' => 'Plan confirmado — listo para activar cuando se lleve a cabo la fusión'];
}

function grupo_fusion_cancelar(PDO $pdo, int $idPlantel, int $idPlan): array
{
    if (!grupo_fusion_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $plan = grupo_fusion_obtener($pdo, $idPlantel, $idPlan);
    if (!$plan || !in_array($plan['estado'], ['borrador', 'planificada'], true)) {
        return ['ok' => false, 'message' => 'Solo se cancelan planes en borrador o planificados'];
    }

    $pdo->prepare(
        'UPDATE grupo_fusion_plan SET estado = \'cancelada\', cancelado_en = NOW() WHERE id_fusion_plan = ? AND id_plantel = ?'
    )->execute([$idPlan, $idPlantel]);

    return ['ok' => true, 'message' => 'Plan cancelado'];
}

function grupo_fusion_activar(PDO $pdo, int $idPlantel, int $idPlan, ?int $idUsuario = null): array
{
    if (!grupo_fusion_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $plan = grupo_fusion_obtener($pdo, $idPlantel, $idPlan);
    if (!$plan || $plan['estado'] !== 'planificada') {
        return ['ok' => false, 'message' => 'Solo se activan planes confirmados (estado planificada)'];
    }

    $idResultante = (int) $plan['id_grupo_resultante'];
    $idOrigen = (int) $plan['id_grupo_origen'];
    $idAtrasado = (int) $plan['id_grupo_atrasado'];
    $idDest = (int) $plan['id_fase_destino'];
    $fasesPend = $plan['fases_pendientes'] ?? [];
    $hayPendientes = $fasesPend !== [];

    $stAl = $pdo->prepare('SELECT id_alumno FROM alumno_grupos WHERE id_grupo = ? AND activo = 1');
    $stAl->execute([$idOrigen]);
    $alumnosOrigen = array_map('intval', $stAl->fetchAll(PDO::FETCH_COLUMN));

    if ($alumnosOrigen === []) {
        return ['ok' => false, 'message' => 'El grupo origen no tiene alumnos activos para fusionar'];
    }

    $pdo->beginTransaction();
    try {
        foreach ($alumnosOrigen as $idAlumno) {
            if (function_exists('alumno_grupo_desactivar_misma_especialidad')) {
                $desactivados = alumno_grupo_desactivar_misma_especialidad($pdo, $idAlumno, $idResultante, $idPlantel);
                foreach ($desactivados as $idGr) {
                    $pdo->prepare(
                        'UPDATE alumno_grupos SET activo = 0, fecha_baja = CURDATE() WHERE id_alumno = ? AND id_grupo = ?'
                    )->execute([$idAlumno, $idGr]);
                }
            }
            if (!function_exists('alumno_asignar_grupo')) {
                throw new RuntimeException('Función alumno_asignar_grupo no disponible');
            }
            alumno_asignar_grupo($pdo, $idAlumno, $idResultante);

            $debeRetomar = $hayPendientes && $idOrigen === $idAtrasado ? 1 : 0;
            $idGrupoGrad = $idOrigen;
            $pdo->prepare(
                'INSERT INTO grupo_fusion_alumno (id_fusion_plan, id_alumno, id_grupo_procedencia, debe_retomar, id_grupo_graduacion)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE debe_retomar = VALUES(debe_retomar), id_grupo_graduacion = VALUES(id_grupo_graduacion)'
            )->execute([$idPlan, $idAlumno, $idOrigen, $debeRetomar, $idGrupoGrad]);
        }

        $pdo->prepare('UPDATE grupos SET id_fase_actual = ? WHERE id_grupo = ? AND id_plantel = ?')
            ->execute([$idDest, $idResultante, $idPlantel]);

        $mismaFase = !$hayPendientes;
        $desfase = $hayPendientes ? 'adelanto' : 'ninguno';
        $notasLog = trim((string) ($plan['notas'] ?? ''));
        $notasLog .= ($notasLog ? ' · ' : '') . 'Plan fusión #' . $idPlan;

        $resFusion = grupo_registrar_fusion(
            $pdo,
            $idResultante,
            $idOrigen,
            $desfase,
            $mismaFase,
            $idUsuario,
            $notasLog
        );
        if (empty($resFusion['ok'])) {
            throw new RuntimeException($resFusion['message'] ?? 'Error al registrar fusión');
        }
        $idLog = (int) $pdo->lastInsertId();

        $pdo->prepare('DELETE FROM grupo_fusion_pendiente_fase WHERE id_fusion_plan = ?')->execute([$idPlan]);
        $orden = 0;
        foreach ($fasesPend as $f) {
            $idF = (int) ($f['id_fase'] ?? 0);
            if ($idF <= 0) {
                continue;
            }
            $pdo->prepare(
                'INSERT INTO grupo_fusion_pendiente_fase (id_fusion_plan, id_grupo, id_fase, orden)
                 VALUES (?, ?, ?, ?)'
            )->execute([$idPlan, $idAtrasado, $idF, $orden++]);
        }

        $pdo->prepare(
            'UPDATE grupo_fusion_plan SET estado = \'activa\', activado_en = NOW(), id_fusion_log = ?
             WHERE id_fusion_plan = ?'
        )->execute([$idLog > 0 ? $idLog : null, $idPlan]);

        $pdo->commit();

        return [
            'ok' => true,
            'message' => sprintf(
                'Fusión activada: %d alumno(s) movidos a %s. Fase destino aplicada.',
                count($alumnosOrigen),
                $plan['clave_resultante'] ?? ''
            ),
            'alumnos_movidos' => count($alumnosOrigen),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('grupo_fusion_activar: ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Error al activar: ' . $e->getMessage()];
    }
}

function grupo_fusion_separar(PDO $pdo, int $idPlantel, int $idPlan, ?int $idUsuario = null): array
{
    if (!grupo_fusion_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $plan = grupo_fusion_obtener($pdo, $idPlantel, $idPlan);
    if (!$plan || $plan['estado'] !== 'activa') {
        return ['ok' => false, 'message' => 'Solo se separan fusiones activas'];
    }

    $idGrupoRetomo = (int) $plan['id_grupo_atrasado'];
    $pendientes = $plan['pendientes_fase'] ?? [];
    if ($pendientes === []) {
        return ['ok' => false, 'message' => 'No hay fases pendientes de retomar — nada que separar'];
    }

    $stAl = $pdo->prepare(
        'SELECT id_alumno FROM grupo_fusion_alumno
         WHERE id_fusion_plan = ? AND debe_retomar = 1 AND separado = 0'
    );
    $stAl->execute([$idPlan]);
    $alumnos = array_map('intval', $stAl->fetchAll(PDO::FETCH_COLUMN));
    if ($alumnos === []) {
        return ['ok' => false, 'message' => 'No hay alumnos marcados para retomar temas'];
    }

    $primeraFase = (int) ($pendientes[0]['id_fase'] ?? 0);

    $pdo->beginTransaction();
    try {
        foreach ($alumnos as $idAlumno) {
            if (function_exists('alumno_grupo_desactivar_misma_especialidad')) {
                $desactivados = alumno_grupo_desactivar_misma_especialidad($pdo, $idAlumno, $idGrupoRetomo, $idPlantel);
                foreach ($desactivados as $idGr) {
                    $pdo->prepare(
                        'UPDATE alumno_grupos SET activo = 0, fecha_baja = CURDATE() WHERE id_alumno = ? AND id_grupo = ?'
                    )->execute([$idAlumno, $idGr]);
                }
            }
            alumno_asignar_grupo($pdo, $idAlumno, $idGrupoRetomo);
            $pdo->prepare(
                'UPDATE grupo_fusion_alumno SET separado = 1 WHERE id_fusion_plan = ? AND id_alumno = ?'
            )->execute([$idPlan, $idAlumno]);
        }

        if ($primeraFase > 0) {
            $pdo->prepare('UPDATE grupos SET id_fase_actual = ? WHERE id_grupo = ? AND id_plantel = ?')
                ->execute([$primeraFase, $idGrupoRetomo, $idPlantel]);
        }

        $pdo->prepare(
            'UPDATE grupo_fusion_plan SET estado = \'separada\', separado_en = NOW() WHERE id_fusion_plan = ?'
        )->execute([$idPlan]);

        $pdo->commit();

        return [
            'ok' => true,
            'message' => sprintf(
                '%d alumno(s) separados al grupo %s para retomar %d fase(s) pendiente(s).',
                count($alumnos),
                $plan['clave_atrasado'] ?? '',
                count($pendientes)
            ),
            'alumnos_separados' => count($alumnos),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('grupo_fusion_separar: ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Error al separar: ' . $e->getMessage()];
    }
}

function grupo_fusion_completar_pendiente(PDO $pdo, int $idPlantel, int $idPendiente): array
{
    if (!grupo_fusion_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $st = $pdo->prepare(
        'SELECT pf.*, p.id_plantel, p.id_fusion_plan
         FROM grupo_fusion_pendiente_fase pf
         INNER JOIN grupo_fusion_plan p ON p.id_fusion_plan = pf.id_fusion_plan
         WHERE pf.id = ? AND p.id_plantel = ?'
    );
    $st->execute([$idPendiente, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'message' => 'Pendiente no encontrado'];
    }

    $pdo->prepare('UPDATE grupo_fusion_pendiente_fase SET estado = \'completada\' WHERE id = ?')
        ->execute([$idPendiente]);

    $stC = $pdo->prepare(
        'SELECT COUNT(*) FROM grupo_fusion_pendiente_fase
         WHERE id_fusion_plan = ? AND estado <> \'completada\''
    );
    $stC->execute([(int) $row['id_fusion_plan']]);
    if ((int) $stC->fetchColumn() === 0) {
        $pdo->prepare(
            'UPDATE grupo_fusion_plan SET estado = \'completada\' WHERE id_fusion_plan = ?'
        )->execute([(int) $row['id_fusion_plan']]);
    }

    return ['ok' => true, 'message' => 'Fase marcada como completada'];
}

/** Grupo de referencia para graduación/alertas (conserva grupo origen tras fusión). */
function grupo_fusion_graduacion_grupo_alumno(PDO $pdo, int $idAlumno, int $idGrupoActual): int
{
    grupo_fusion_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT fa.id_grupo_graduacion
         FROM grupo_fusion_alumno fa
         INNER JOIN grupo_fusion_plan p ON p.id_fusion_plan = fa.id_fusion_plan
         WHERE fa.id_alumno = ? AND fa.separado = 0
           AND p.estado IN (\'activa\', \'separada\')
           AND fa.id_grupo_graduacion IS NOT NULL AND fa.id_grupo_graduacion > 0
         ORDER BY p.activado_en DESC, fa.creado_en DESC
         LIMIT 1'
    );
    $st->execute([$idAlumno]);
    $id = (int) $st->fetchColumn();

    return $id > 0 ? $id : $idGrupoActual;
}

/**
 * Matriz dual infantil: inglés + computación con sugerencias de pareja.
 *
 * @param array<string, mixed> $filtros
 * @return array<string, mixed>
 */
function grupo_fusion_matriz_kids_dual(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    $kids = grupo_fusion_kids_config($pdo);
    if (!$kids['disponible']) {
        return [
            'ok' => false,
            'message' => 'No están configuradas las especialidades infantiles ING-K y COMP-K.',
        ];
    }

    $filtrosIng = array_merge($filtros, ['id_especialidad' => $kids['ingles']]);
    $filtrosComp = array_merge($filtros, ['id_especialidad' => $kids['computacion']]);
    $ingles = grupo_fusion_matriz($pdo, $idPlantel, $filtrosIng);
    $computacion = grupo_fusion_matriz($pdo, $idPlantel, $filtrosComp);

    $parejas = [];
    foreach ($ingles['grupos'] ?? [] as $g) {
        $sug = grupo_fusion_parejas_comp_sugeridas($pdo, $idPlantel, (int) $g['id_grupo'], $kids['computacion']);
        foreach ($sug as $s) {
            $parejas[] = array_merge($s, [
                'id_grupo_ing' => (int) $g['id_grupo'],
                'clave_ing' => (string) ($g['clave'] ?? ''),
            ]);
        }
    }

    return [
        'ok' => true,
        'modo' => 'kids_dual',
        'kids' => $kids,
        'ingles' => $ingles,
        'computacion' => $computacion,
        'parejas_sugeridas' => $parejas,
        'total_ing' => (int) ($ingles['total'] ?? 0),
        'total_comp' => (int) ($computacion['total'] ?? 0),
        'recomendados_ing' => (int) ($ingles['recomendados'] ?? 0),
        'recomendados_comp' => (int) ($computacion['recomendados'] ?? 0),
    ];
}

/**
 * Simula fusión dual infantil (inglés + computación en paralelo).
 *
 * @return array<string, mixed>
 */
function grupo_fusion_simular_dual(
    PDO $pdo,
    int $idPlantel,
    int $idGrupoIngA,
    int $idGrupoIngB,
    int $idGrupoCompA,
    int $idGrupoCompB,
    ?int $idFaseDestinoIng = null,
    ?int $idFaseDestinoComp = null
): array {
    $kids = grupo_fusion_kids_config($pdo);
    if (!$kids['disponible']) {
        return ['ok' => false, 'message' => 'Especialidades infantiles no configuradas.'];
    }

    $simIng = grupo_fusion_simular($pdo, $idPlantel, $idGrupoIngA, $idGrupoIngB, $idFaseDestinoIng);
    if (!$simIng['ok']) {
        return $simIng;
    }
    $simComp = grupo_fusion_simular($pdo, $idPlantel, $idGrupoCompA, $idGrupoCompB, $idFaseDestinoComp);
    if (!$simComp['ok']) {
        return $simComp;
    }

    $dualA = grupo_fusion_alumnos_dual_grupo($pdo, $idGrupoIngA, $kids['computacion']);
    $dualB = grupo_fusion_alumnos_dual_grupo($pdo, $idGrupoIngB, $kids['computacion']);
    $dualUnion = array_values(array_unique(array_merge($dualA, $dualB)));

    $notas = [
        'Fusión dual infantil: se crearán dos planes vinculados (inglés y computación).',
        sprintf('%d alumno(s) cursan ambas materias en los grupos de inglés seleccionados.', count($dualUnion)),
    ];
    if ($dualUnion === []) {
        $notas[] = 'Advertencia: no hay alumnos dual detectados — verifique que los grupos de cómputo correspondan.';
    }

    foreach ([$simIng, $simComp] as $sim) {
        if (($sim['temario_a']['tiene_compresion'] ?? false) || ($sim['temario_b']['tiene_compresion'] ?? false)) {
            $notas[] = 'Hay temario comprimido planificado — revise el plan de parciales antes de activar.';
            break;
        }
    }

    return [
        'ok' => true,
        'modo' => 'kids_dual',
        'ingles' => $simIng,
        'computacion' => $simComp,
        'alumnos_dual' => count($dualUnion),
        'notas' => $notas,
    ];
}

/**
 * Guarda par de planes vinculados para fusión dual infantil.
 *
 * @return array{ok: bool, message?: string, id_fusion_plan_ing?: int, id_fusion_plan_comp?: int}
 */
function grupo_fusion_guardar_plan_dual(
    PDO $pdo,
    int $idPlantel,
    array $simDual,
    int $idGrupoResultanteIng,
    int $idGrupoResultanteComp,
    ?string $fechaPrevista = null,
    string $notas = '',
    ?int $idUsuario = null
): array {
    if (empty($simDual['ok']) || ($simDual['modo'] ?? '') !== 'kids_dual') {
        return ['ok' => false, 'message' => 'Simulación dual inválida'];
    }

    $simIng = $simDual['ingles'] ?? [];
    $simComp = $simDual['computacion'] ?? [];
    if (empty($simIng['ok']) || empty($simComp['ok'])) {
        return ['ok' => false, 'message' => 'Simulación de inglés o computación inválida'];
    }

    $notasIng = trim($notas);
    $notasComp = trim($notas);
    if ($notasIng !== '') {
        $notasIng .= ' · ';
    }
    $notasIng .= 'Pareja dual COMP (se vincula al guardar)';
    if ($notasComp !== '') {
        $notasComp .= ' · ';
    }
    $notasComp .= 'Pareja dual ING (vinculado automáticamente)';

    $pdo->beginTransaction();
    try {
        $resIng = grupo_fusion_guardar_plan(
            $pdo,
            $idPlantel,
            $simIng,
            $idGrupoResultanteIng,
            $fechaPrevista,
            $notasIng,
            $idUsuario,
            null,
            'kids_dual',
            null
        );
        if (!$resIng['ok']) {
            throw new RuntimeException($resIng['message'] ?? 'No se pudo guardar plan de inglés');
        }
        $idIng = (int) ($resIng['id_fusion_plan'] ?? 0);

        $resComp = grupo_fusion_guardar_plan(
            $pdo,
            $idPlantel,
            $simComp,
            $idGrupoResultanteComp,
            $fechaPrevista,
            $notasComp,
            $idUsuario,
            null,
            'kids_dual',
            $idIng
        );
        if (!$resComp['ok']) {
            throw new RuntimeException($resComp['message'] ?? 'No se pudo guardar plan de computación');
        }
        $idComp = (int) ($resComp['id_fusion_plan'] ?? 0);

        $pdo->prepare('UPDATE grupo_fusion_plan SET id_plan_vinculado = ? WHERE id_fusion_plan = ? AND id_plantel = ?')
            ->execute([$idComp, $idIng, $idPlantel]);

        $pdo->commit();

        return [
            'ok' => true,
            'message' => 'Planes dual guardados (inglés #' . $idIng . ' + computación #' . $idComp . ')',
            'id_fusion_plan_ing' => $idIng,
            'id_fusion_plan_comp' => $idComp,
            'id_fusion_plan' => $idIng,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => $e->getMessage()];
    }
}
