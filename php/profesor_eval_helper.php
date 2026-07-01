<?php

/**
 * Evaluación 360 de profesores activos + métricas automáticas (retención, asistencia).
 */

function profesor_eval_puede_gestionar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('hay_eval_gestionar')) {
        return true;
    }
    return in_array(rbac_rol_efectivo(), ['coordinador', 'director', 'supervisor'], true);
}

function profesor_eval_ensure_schema(PDO $pdo): void
{
    asistencia_ensure_schema($pdo);
    academico_ensure_schema($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS profesor_eval_periodo (
            id_eval INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            anio SMALLINT UNSIGNED NOT NULL,
            mes TINYINT UNSIGNED NOT NULL,
            estado ENUM(\'borrador\',\'cerrado\') NOT NULL DEFAULT \'borrador\',
            metricas_auto JSON NOT NULL,
            criterios_manual JSON NOT NULL,
            puntos_auto INT UNSIGNED NOT NULL DEFAULT 0,
            puntos_manual INT UNSIGNED NOT NULL DEFAULT 0,
            puntos_total INT UNSIGNED NOT NULL DEFAULT 0,
            nivel VARCHAR(20) NULL,
            observaciones TEXT NULL,
            evaluado_por INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_eval),
            UNIQUE KEY uq_prof_eval_periodo (id_usuario, id_plantel, anio, mes),
            KEY idx_prof_eval_plantel (id_plantel, anio, mes)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/**
 * Rúbrica HAY (matriz Excel) agrupada por bloques.
 *
 * @return array<string, array{titulo:string, items:list<array{codigo:string,nombre:string,maximo:int}>}>
 */
function profesor_eval_rubrica_categorias(): array
{
    return [
        'formacion' => [
            'titulo' => 'Formación y certificación',
            'items' => [
                ['codigo' => 'mcerl', 'nombre' => 'Nivel en el MCERL', 'maximo' => 250],
                ['codigo' => 'certificacion', 'nombre' => 'Certificación', 'maximo' => 250],
                ['codigo' => 'licenciatura', 'nombre' => 'Licenciatura', 'maximo' => 300],
            ],
        ],
        'experiencia' => [
            'titulo' => 'Experiencia docente',
            'items' => [
                ['codigo' => 'exp_prescolar', 'nombre' => 'Preescolar', 'maximo' => 80],
                ['codigo' => 'exp_secundaria', 'nombre' => 'Secundaria', 'maximo' => 80],
                ['codigo' => 'exp_preparatoria', 'nombre' => 'Preparatoria', 'maximo' => 80],
                ['codigo' => 'exp_universidad', 'nombre' => 'Universidad', 'maximo' => 80],
                ['codigo' => 'module_1', 'nombre' => 'Module 1', 'maximo' => 260],
                ['codigo' => 'module_2', 'nombre' => 'Module 2', 'maximo' => 260],
                ['codigo' => 'module_3', 'nombre' => 'Module 3', 'maximo' => 260],
            ],
        ],
        'tecnologia' => [
            'titulo' => 'Tecnología',
            'items' => [
                ['codigo' => 'windows', 'nombre' => 'Windows', 'maximo' => 250],
                ['codigo' => 'word', 'nombre' => 'Word', 'maximo' => 250],
                ['codigo' => 'powerpoint', 'nombre' => 'Power Point', 'maximo' => 150],
                ['codigo' => 'excel', 'nombre' => 'Excel', 'maximo' => 100],
                ['codigo' => 'videollamadas', 'nombre' => 'Plataformas videollamada', 'maximo' => 250],
                ['codigo' => 'apps_web', 'nombre' => 'Apps y páginas web', 'maximo' => 150],
                ['codigo' => 'moodle', 'nombre' => 'Manejo Moodle', 'maximo' => 200],
            ],
        ],
        'operacion' => [
            'titulo' => 'Operación y disponibilidad',
            'items' => [
                ['codigo' => 'asesorias', 'nombre' => 'Disponibilidad asesorías', 'maximo' => 150],
                ['codigo' => 'personalizados', 'nombre' => 'Clases personalizadas', 'maximo' => 100],
                ['codigo' => 'planes_estudio', 'nombre' => 'Desarrollo planes de estudio', 'maximo' => 180],
                ['codigo' => 'fusiones', 'nombre' => 'Manejo de fusiones', 'maximo' => 120],
                ['codigo' => 'viaje', 'nombre' => 'Disponibilidad de viaje', 'maximo' => 100],
                ['codigo' => 'apoyo_faltas', 'nombre' => 'Apoyo (cubrir faltas)', 'maximo' => 100],
                ['codigo' => 'proyecto_final', 'nombre' => 'Proyecto final', 'maximo' => 120],
                ['codigo' => 'planeaciones', 'nombre' => 'Entrega de planeaciones', 'maximo' => 150],
                ['codigo' => 'carga_horaria', 'nombre' => 'Carga horaria', 'maximo' => 120],
            ],
        ],
        'desempeno' => [
            'titulo' => 'Desempeño y seguimiento',
            'items' => [
                ['codigo' => 'juntas', 'nombre' => 'Asistencia a juntas', 'maximo' => 80],
                ['codigo' => 'inteligencia_emocional', 'nombre' => 'Inteligencia emocional', 'maximo' => 80],
                ['codigo' => 'supervision', 'nombre' => 'Supervisión', 'maximo' => 120],
                ['codigo' => 'eval_4to_mes', 'nombre' => 'Evaluación 4.º mes', 'maximo' => 150],
                ['codigo' => 'eval_clase', 'nombre' => 'Evaluación de clase', 'maximo' => 150],
            ],
        ],
        'soft_skills' => [
            'titulo' => 'Know-how y competencias',
            'items' => [
                ['codigo' => 'know_how', 'nombre' => 'Know-how', 'maximo' => 300],
                ['codigo' => 'problem_solving', 'nombre' => 'Problem solving', 'maximo' => 200],
                ['codigo' => 'accountability', 'nombre' => 'Accountability', 'maximo' => 200],
                ['codigo' => 'environment', 'nombre' => 'Environment', 'maximo' => 80],
            ],
        ],
    ];
}

/** @return list<array{codigo:string,nombre:string,maximo:int,categoria?:string}> */
function profesor_eval_criterios_manual(): array
{
    $flat = [];
    foreach (profesor_eval_rubrica_categorias() as $key => $cat) {
        foreach ($cat['items'] as $item) {
            $flat[] = $item + ['categoria' => $key];
        }
    }

    return $flat;
}

function profesor_eval_max_posible(): int
{
    $sum = 0;
    foreach (profesor_eval_criterios_auto() as $c) {
        $sum += $c['maximo'];
    }
    foreach (profesor_eval_criterios_manual() as $c) {
        $sum += $c['maximo'];
    }

    return $sum;
}

/** @return list<array{codigo:string,nombre:string,maximo:int}> */
function profesor_eval_criterios_auto(): array
{
    return [
        ['codigo' => 'retencion', 'nombre' => 'Retención de alumnos', 'maximo' => 100],
        ['codigo' => 'asistencia_alumnos', 'nombre' => 'Asistencia de alumnos (promedio)', 'maximo' => 80],
        ['codigo' => 'puntualidad', 'nombre' => 'Puntualidad del profesor', 'maximo' => 80],
        ['codigo' => 'entrega_calificaciones', 'nombre' => 'Entrega de calificaciones', 'maximo' => 150],
    ];
}

function profesor_eval_periodo_rango(int $anio, int $mes): array
{
    $mes = max(1, min(12, $mes));
    $ini = sprintf('%04d-%02d-01', $anio, $mes);
    $fin = (new DateTimeImmutable($ini))->modify('last day of this month')->format('Y-m-d');

    return ['desde' => $ini, 'hasta' => $fin];
}

function profesor_eval_puntos_por_retencion(float $pct): int
{
    if ($pct >= 95) {
        return 100;
    }
    if ($pct >= 85) {
        return 80;
    }
    if ($pct >= 75) {
        return 50;
    }
    if ($pct >= 70) {
        return 20;
    }

    return 0;
}

function profesor_eval_puntos_por_asistencia(float $pct): int
{
    if ($pct >= 90) {
        return 80;
    }
    if ($pct >= 80) {
        return 60;
    }
    if ($pct >= 70) {
        return 40;
    }
    if ($pct >= 60) {
        return 20;
    }

    return 0;
}

function profesor_eval_puntos_por_puntualidad(float $pct): int
{
    if ($pct >= 90) {
        return 80;
    }
    if ($pct >= 75) {
        return 50;
    }
    if ($pct >= 50) {
        return 20;
    }

    return 0;
}

function profesor_eval_puntos_por_entrega_cal(float $pct): int
{
    if ($pct >= 100) {
        return 100;
    }
    if ($pct >= 90) {
        return 80;
    }
    if ($pct >= 75) {
        return 50;
    }
    if ($pct >= 50) {
        return 20;
    }

    return 0;
}

function profesor_eval_nivel_desde_total(int $total, ?int $maxPosible = null): string
{
    $max = $maxPosible ?? profesor_eval_max_posible();
    $pct = $max > 0 ? (100 * $total / $max) : 0;

    if ($pct >= 90) {
        return 'Excelente (D)';
    }
    if ($pct >= 75) {
        return 'Muy bueno (C+)';
    }
    if ($pct >= 60) {
        return 'Bueno (C)';
    }
    if ($pct >= 45) {
        return 'Regular (C-)';
    }

    return 'Mejorable (B+)';
}

/** @return array<string, mixed> */
function profesor_eval_calcular_metricas_auto(PDO $pdo, int $idProfesor, int $idPlantel, int $anio, int $mes): array
{
    $rango = profesor_eval_periodo_rango($anio, $mes);
    $desde = $rango['desde'];
    $hasta = $rango['hasta'];

    $stGr = $pdo->prepare(
        'SELECT g.id_grupo, g.id_fase_actual
         FROM grupos g
         WHERE g.id_plantel = ? AND g.id_profesor = ?'
    );
    $stGr->execute([$idPlantel, $idProfesor]);
    $grupos = $stGr->fetchAll(PDO::FETCH_ASSOC);

    $totalAlumnos = 0;
    $alumnosActivos = 0;
    foreach ($grupos as $g) {
        $stA = $pdo->prepare(
            'SELECT ag.id_alumno, ag.activo, a.estado
             FROM alumno_grupos ag
             INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
             WHERE ag.id_grupo = ?'
        );
        $stA->execute([(int) $g['id_grupo']]);
        foreach ($stA->fetchAll(PDO::FETCH_ASSOC) as $al) {
            ++$totalAlumnos;
            if ((int) ($al['activo'] ?? 0) === 1 && ($al['estado'] ?? '') === 'activo') {
                ++$alumnosActivos;
            }
        }
    }
    $pctRet = $totalAlumnos > 0 ? round(100 * $alumnosActivos / $totalAlumnos, 1) : 0.0;

    $idsGrupo = array_map(static fn ($g) => (int) $g['id_grupo'], $grupos);
    $pctAsist = 0.0;
    if ($idsGrupo !== []) {
        $ph = implode(',', array_fill(0, count($idsGrupo), '?'));
        $params = array_merge($idsGrupo, [$desde, $hasta]);
        $stAs = $pdo->prepare(
            "SELECT COUNT(*) AS total, COALESCE(SUM(presente), 0) AS presentes
             FROM asistencias
             WHERE id_grupo IN ($ph) AND fecha BETWEEN ? AND ?"
        );
        $stAs->execute($params);
        $rowAs = $stAs->fetch(PDO::FETCH_ASSOC) ?: [];
        $totAs = (int) ($rowAs['total'] ?? 0);
        $pctAsist = $totAs > 0 ? round(100 * (int) ($rowAs['presentes'] ?? 0) / $totAs, 1) : 0.0;
    }

    $stPun = $pdo->prepare(
        'SELECT hora_llegada FROM asistencia_personal
         WHERE id_usuario = ? AND id_plantel = ? AND fecha BETWEEN ? AND ?'
    );
    $stPun->execute([$idProfesor, $idPlantel, $desde, $hasta]);
    $puntual = 0;
    $regPun = 0;
    foreach ($stPun->fetchAll(PDO::FETCH_ASSOC) as $p) {
        ++$regPun;
        $h = $p['hora_llegada'] ?? '';
        if ($h !== '' && $h <= '08:10:00') {
            ++$puntual;
        }
    }
    $pctPun = $regPun > 0 ? round(100 * $puntual / $regPun, 1) : 0.0;

    $esperadas = 0;
    $capturadas = 0;
    foreach ($grupos as $g) {
        $idGrupo = (int) $g['id_grupo'];
        $idFase = (int) ($g['id_fase_actual'] ?? 0);
        if ($idFase <= 0) {
            continue;
        }
        $stAl = $pdo->prepare(
            'SELECT ag.id_alumno FROM alumno_grupos ag
             INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.estado = \'activo\'
             WHERE ag.id_grupo = ? AND ag.activo = 1'
        );
        $stAl->execute([$idGrupo]);
        $alumnos = $stAl->fetchAll(PDO::FETCH_COLUMN);
        foreach ($alumnos as $idAlumno) {
            ++$esperadas;
            $stC = $pdo->prepare(
                'SELECT 1 FROM alumno_calificacion_parcial
                 WHERE id_alumno = ? AND id_fase = ? AND id_grupo = ? AND promedio IS NOT NULL LIMIT 1'
            );
            $stC->execute([(int) $idAlumno, $idFase, $idGrupo]);
            if ($stC->fetchColumn()) {
                ++$capturadas;
            }
        }
    }
    $pctCal = $esperadas > 0 ? round(100 * $capturadas / $esperadas, 1) : 0.0;

    $metricas = [
        'retencion' => [
            'valor_pct' => $pctRet,
            'detalle' => "$alumnosActivos de $totalAlumnos alumnos activos en sus grupos",
            'puntos_sugeridos' => profesor_eval_puntos_por_retencion($pctRet),
        ],
        'asistencia_alumnos' => [
            'valor_pct' => $pctAsist,
            'detalle' => 'Registros de asistencia en el periodo',
            'puntos_sugeridos' => profesor_eval_puntos_por_asistencia($pctAsist),
        ],
        'puntualidad' => [
            'valor_pct' => $pctPun,
            'detalle' => "$puntual de $regPun checadas antes de 08:10",
            'puntos_sugeridos' => profesor_eval_puntos_por_puntualidad($pctPun),
        ],
        'entrega_calificaciones' => [
            'valor_pct' => $pctCal,
            'detalle' => "$capturadas de $esperadas calificaciones del parcial actual",
            'puntos_sugeridos' => profesor_eval_puntos_por_entrega_cal($pctCal),
        ],
    ];

    return [
        'metricas' => $metricas,
        'grupos' => count($grupos),
    ];
}

/** @param array<string, int|float> $puntosAuto @param array<string, int|float> $puntosManual */
function profesor_eval_sumar_puntos(array $puntosAuto, array $puntosManual): array
{
    $auto = 0;
    foreach (profesor_eval_criterios_auto() as $c) {
        $auto += (int) round((float) ($puntosAuto[$c['codigo']] ?? 0));
    }
    $manual = 0;
    foreach (profesor_eval_criterios_manual() as $c) {
        $manual += (int) round((float) ($puntosManual[$c['codigo']] ?? 0));
    }
    $total = $auto + $manual;

    $max = profesor_eval_max_posible();

    return [
        'puntos_auto' => $auto,
        'puntos_manual' => $manual,
        'puntos_total' => $total,
        'puntos_max' => $max,
        'porcentaje' => $max > 0 ? round(100 * $total / $max, 1) : 0,
        'nivel' => profesor_eval_nivel_desde_total($total, $max),
    ];
}

/** Evaluación cerrada más reciente para el portal del profesor. */
function profesor_eval_ultima_cerrada(PDO $pdo, int $idUsuario, int $idPlantel): ?array
{
    profesor_eval_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM profesor_eval_periodo
         WHERE id_usuario = ? AND id_plantel = ? AND estado = \'cerrado\'
         ORDER BY anio DESC, mes DESC LIMIT 1'
    );
    $st->execute([$idUsuario, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['metricas_auto'] = json_decode((string) ($row['metricas_auto'] ?? '{}'), true) ?: [];
    $row['criterios_manual'] = json_decode((string) ($row['criterios_manual'] ?? '{}'), true) ?: [];
    $row['totales'] = [
        'puntos_total' => (int) $row['puntos_total'],
        'puntos_max' => profesor_eval_max_posible(),
        'nivel' => $row['nivel'],
    ];

    return $row;
}

/** @return list<array<string, mixed>> */
function profesor_eval_listar_profesores(PDO $pdo, int $idPlantel, int $anio, int $mes): array
{
    profesor_eval_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT u.id_usuario, u.nombre, u.apellido, u.email,
                e.id_eval, e.estado AS eval_estado, e.puntos_total, e.nivel, e.actualizado_en
         FROM usuarios u
         LEFT JOIN profesor_eval_periodo e
           ON e.id_usuario = u.id_usuario AND e.id_plantel = ? AND e.anio = ? AND e.mes = ?
         WHERE u.rol = 'profesor' AND u.suspendido = 0 AND u.id_plantel = ?
         ORDER BY u.apellido, u.nombre"
    );
    $st->execute([$idPlantel, $anio, $mes, $idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function profesor_eval_obtener(PDO $pdo, int $idUsuario, int $idPlantel, int $anio, int $mes): ?array
{
    $st = $pdo->prepare(
        'SELECT * FROM profesor_eval_periodo
         WHERE id_usuario = ? AND id_plantel = ? AND anio = ? AND mes = ? LIMIT 1'
    );
    $st->execute([$idUsuario, $idPlantel, $anio, $mes]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['metricas_auto'] = json_decode((string) ($row['metricas_auto'] ?? '{}'), true) ?: [];
    $row['criterios_manual'] = json_decode((string) ($row['criterios_manual'] ?? '{}'), true) ?: [];

    return $row;
}

function profesor_eval_guardar(
    PDO $pdo,
    int $idUsuario,
    int $idPlantel,
    int $anio,
    int $mes,
    array $puntosAuto,
    array $puntosManual,
    array $metricasAuto,
    string $observaciones,
    bool $cerrar,
    int $idEvaluador
): array {
    profesor_eval_ensure_schema($pdo);

    foreach (profesor_eval_criterios_auto() as $c) {
        $max = $c['maximo'];
        $v = (int) round((float) ($puntosAuto[$c['codigo']] ?? 0));
        if ($v < 0 || $v > $max) {
            return ['ok' => false, 'message' => "Puntos inválidos en {$c['nombre']} (0–$max)"];
        }
    }
    foreach (profesor_eval_criterios_manual() as $c) {
        $max = $c['maximo'];
        $v = (int) round((float) ($puntosManual[$c['codigo']] ?? 0));
        if ($v < 0 || $v > $max) {
            return ['ok' => false, 'message' => "Puntos inválidos en {$c['nombre']} (0–$max)"];
        }
    }

    $totales = profesor_eval_sumar_puntos($puntosAuto, $puntosManual);
    $estado = $cerrar ? 'cerrado' : 'borrador';

    $existe = profesor_eval_obtener($pdo, $idUsuario, $idPlantel, $anio, $mes);
    if ($existe && ($existe['estado'] ?? '') === 'cerrado') {
        return ['ok' => false, 'message' => 'La evaluación está cerrada y no puede modificarse.'];
    }

    $jsonAuto = json_encode($metricasAuto, JSON_UNESCAPED_UNICODE);
    $jsonManual = json_encode($puntosManual, JSON_UNESCAPED_UNICODE);

    if ($existe) {
        $pdo->prepare(
            'UPDATE profesor_eval_periodo SET
                estado = ?, metricas_auto = ?, criterios_manual = ?,
                puntos_auto = ?, puntos_manual = ?, puntos_total = ?, nivel = ?,
                observaciones = ?, evaluado_por = ?
             WHERE id_eval = ?'
        )->execute([
            $estado,
            $jsonAuto,
            $jsonManual,
            $totales['puntos_auto'],
            $totales['puntos_manual'],
            $totales['puntos_total'],
            $totales['nivel'],
            trim($observaciones),
            $idEvaluador,
            (int) $existe['id_eval'],
        ]);
        $idEval = (int) $existe['id_eval'];
    } else {
        $pdo->prepare(
            'INSERT INTO profesor_eval_periodo (
                id_usuario, id_plantel, anio, mes, estado, metricas_auto, criterios_manual,
                puntos_auto, puntos_manual, puntos_total, nivel, observaciones, evaluado_por
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $idUsuario,
            $idPlantel,
            $anio,
            $mes,
            $estado,
            $jsonAuto,
            $jsonManual,
            $totales['puntos_auto'],
            $totales['puntos_manual'],
            $totales['puntos_total'],
            $totales['nivel'],
            trim($observaciones),
            $idEvaluador,
        ]);
        $idEval = (int) $pdo->lastInsertId();
    }

    if ($cerrar && function_exists('profesor_eval_vincular_hay_global')) {
        $sync = profesor_eval_vincular_hay_global($pdo, $idEval, $idEvaluador);
        if (!$sync['ok']) {
            error_log('profesor_eval_vincular_hay: ' . ($sync['message'] ?? ''));
        }
    }

    return [
        'ok' => true,
        'id_eval' => $idEval,
        'totales' => $totales,
        'estado' => $estado,
    ];
}

/**
 * Al cerrar evaluación 360, refleja puntos totales en hay_eval_periodo (puntos HAY globales).
 *
 * @return array{ok:bool,message:string,id_hay_eval?:int}
 */
function profesor_eval_vincular_hay_global(PDO $pdo, int $idProfEval, int $idEvaluador): array
{
    profesor_eval_ensure_schema($pdo);
    if (!function_exists('hay_eval_ensure_schema')) {
        return ['ok' => false, 'message' => 'Módulo HAY no disponible'];
    }
    hay_eval_ensure_schema($pdo);
    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'hay_eval_periodo', 'id_profesor_eval_origen', 'INT UNSIGNED NULL', 'evaluado_por');
    }

    $st = $pdo->prepare('SELECT * FROM profesor_eval_periodo WHERE id_eval = ? LIMIT 1');
    $st->execute([$idProfEval]);
    $pe = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pe || ($pe['estado'] ?? '') !== 'cerrado') {
        return ['ok' => false, 'message' => 'Evaluación 360 no cerrada'];
    }

    $idUsuario = (int) ($pe['id_usuario'] ?? 0);
    $idPlantel = (int) ($pe['id_plantel'] ?? 0);
    $anio = (int) ($pe['anio'] ?? 0);
    $mes = (int) ($pe['mes'] ?? 0);
    $puntos = (int) ($pe['puntos_total'] ?? 0);
    if ($idUsuario <= 0 || $idPlantel <= 0 || $anio <= 0 || $mes <= 0) {
        return ['ok' => false, 'message' => 'Datos de periodo incompletos'];
    }

    $idArea = hay_eval_area_usuario($pdo, $idUsuario);
    if (!$idArea && function_exists('hay_eval_area_por_clave')) {
        $area = hay_eval_area_por_clave($pdo, 'PROF_INGLES');
        $idArea = $area ? (int) ($area['id_area'] ?? 0) : 0;
    }
    if ($idArea <= 0) {
        return ['ok' => false, 'message' => 'Sin área HAY para el profesor'];
    }

    $nivel = hay_eval_nivel_desde_puntos($pdo, $idArea, $puntos);
    $idNivel = $nivel ? (int) ($nivel['id_nivel'] ?? 0) : null;
    $nota = 'Sincronizado desde Evaluación 360 (#' . $idProfEval . ') — nivel ' . ($pe['nivel'] ?? '');

    $stH = $pdo->prepare(
        'SELECT id_eval, estado FROM hay_eval_periodo
         WHERE id_usuario=? AND id_plantel=? AND id_area=? AND anio=? AND mes=? LIMIT 1'
    );
    $stH->execute([$idUsuario, $idPlantel, $idArea, $anio, $mes]);
    $hay = $stH->fetch(PDO::FETCH_ASSOC);

    if ($hay) {
        $pdo->prepare(
            'UPDATE hay_eval_periodo SET estado=\'cerrado\', puntos_total=?, id_nivel_resultado=?,
                observaciones=?, evaluado_por=?, id_profesor_eval_origen=?
             WHERE id_eval=?'
        )->execute([
            $puntos,
            $idNivel,
            $nota,
            $idEvaluador > 0 ? $idEvaluador : null,
            $idProfEval,
            (int) $hay['id_eval'],
        ]);
        $idHay = (int) $hay['id_eval'];
    } else {
        $pdo->prepare(
            'INSERT INTO hay_eval_periodo (
                id_usuario, id_plantel, id_area, anio, mes, estado, puntos_total,
                id_nivel_resultado, observaciones, evaluado_por, id_profesor_eval_origen
            ) VALUES (?,?,?,?,?,\'cerrado\',?,?,?,?,?)'
        )->execute([
            $idUsuario,
            $idPlantel,
            $idArea,
            $anio,
            $mes,
            $puntos,
            $idNivel,
            $nota,
            $idEvaluador > 0 ? $idEvaluador : null,
            $idProfEval,
        ]);
        $idHay = (int) $pdo->lastInsertId();
    }

    $pdo->prepare('UPDATE profesor_eval_periodo SET observaciones = CONCAT(COALESCE(observaciones,\'\'), ?) WHERE id_eval = ?')
        ->execute(["\n[HAY sync #{$idHay}]", $idProfEval]);

    return [
        'ok' => true,
        'message' => 'Puntos vinculados a HAY global',
        'id_hay_eval' => $idHay,
    ];
}

function profesor_eval_puntos_hay_globales(PDO $pdo, int $idUsuario, int $idPlantel): ?array
{
    profesor_eval_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT anio, mes, puntos_total, nivel, estado
         FROM profesor_eval_periodo
         WHERE id_usuario = ? AND id_plantel = ? AND estado = \'cerrado\'
         ORDER BY anio DESC, mes DESC LIMIT 1'
    );
    $st->execute([$idUsuario, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}
