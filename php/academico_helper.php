<?php

/** Calificación mínima aprobatoria (escala 1–10). */
define('ACADEMICO_NOTA_MINIMA', 6.0);

/** Semanas lectivas por parcial. */
define('ACADEMICO_SEMANAS_POR_PARCIAL', 4);

/** Criterios obligatorios de la escuela. */
define('ACADEMICO_CRITERIOS_OBLIGATORIOS', [
    'listening', 'reading', 'writing', 'speaking', 'grammar', 'vocabulary',
]);

function academico_ensure_schema(PDO $pdo): void
{
    fase_ensure_schema($pdo);
    alumno_ensure_schema($pdo);
    if (function_exists('grupo_clave_ensure_schema')) {
        grupo_clave_ensure_schema($pdo);
    }
    if (function_exists('grupo_plan_ensure_schema')) {
        grupo_plan_ensure_schema($pdo);
    }

    plantel_ensure_column($pdo, 'grupos', 'id_fase_actual', 'INT UNSIGNED NULL', 'id_especialidad');
    plantel_ensure_column($pdo, 'grupos', 'moodle_nivel', 'VARCHAR(20) NULL', 'id_fase_actual');
    plantel_ensure_column($pdo, 'grupos', 'horario_texto', 'VARCHAR(120) NULL', 'moodle_nivel');

    plantel_ensure_column($pdo, 'alumno_grupos', 'id_fase_entrada', 'INT UNSIGNED NULL', 'id_grupo');
    plantel_ensure_column($pdo, 'alumno_grupos', 'ubicacion_examen', 'TINYINT(1) NOT NULL DEFAULT 0', 'id_fase_entrada');
    plantel_ensure_column($pdo, 'alumno_grupos', 'en_riesgo_academico', 'TINYINT(1) NOT NULL DEFAULT 0', 'ubicacion_examen');
    plantel_ensure_column(
        $pdo,
        'alumno_grupos',
        'omitir_alerta_riesgo',
        'TINYINT(1) NOT NULL DEFAULT 0',
        'en_riesgo_academico'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_ubicacion (
            id_ubicacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            id_especialidad INT UNSIGNED NOT NULL,
            evaluado_por INT UNSIGNED NULL,
            fecha_evaluacion DATE NOT NULL,
            nivel_detectado VARCHAR(20) NULL,
            observaciones TEXT NULL,
            estado ENUM(\'pendiente\',\'autorizado\',\'rechazado\',\'usado\') NOT NULL DEFAULT \'pendiente\',
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_ubicacion),
            KEY idx_ub_alumno (id_alumno)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_ubicacion_grupos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_ubicacion INT UNSIGNED NOT NULL,
            id_grupo INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_ub_grupo (id_ubicacion, id_grupo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_rubrica_parcial (
            id_rubrica INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo INT UNSIGNED NOT NULL,
            id_fase INT UNSIGNED NOT NULL,
            criterios_json JSON NOT NULL,
            actualizado_por INT UNSIGNED NULL,
            actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_rubrica),
            UNIQUE KEY uq_grupo_fase_rubrica (id_grupo, id_fase)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_calificacion_parcial (
            id_calificacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_fase INT UNSIGNED NOT NULL,
            id_grupo INT UNSIGNED NULL,
            notas_json JSON NOT NULL,
            promedio DECIMAL(4,2) NULL,
            aprobado TINYINT(1) NULL,
            capturado_por INT UNSIGNED NULL,
            editado_por INT UNSIGNED NULL,
            observaciones VARCHAR(500) NULL,
            capturado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_calificacion),
            UNIQUE KEY uq_alumno_fase_cal (id_alumno, id_fase),
            KEY idx_cal_fase (id_fase)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_nota_coordinacion (
            id_nota INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_usuario INT UNSIGNED NULL,
            tipo ENUM(\'orientacion_grupo\',\'ubicacion\',\'riesgo_academico\',\'general\') NOT NULL DEFAULT \'general\',
            nota TEXT NOT NULL,
            alumno_acepto_cambio TINYINT(1) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_nota),
            KEY idx_anc_alumno (id_alumno)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_avance_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo INT UNSIGNED NOT NULL,
            id_fase_anterior INT UNSIGNED NULL,
            id_fase_nueva INT UNSIGNED NOT NULL,
            semanas_lectivas INT UNSIGNED NOT NULL,
            avanzado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            automatico TINYINT(1) NOT NULL DEFAULT 1,
            id_usuario INT UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY idx_gal_grupo (id_grupo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS calendario_escolar_anio (
            anio SMALLINT UNSIGNED NOT NULL,
            publicado TINYINT(1) NOT NULL DEFAULT 0,
            notas TEXT NULL,
            actualizado_por INT UNSIGNED NULL,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (anio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS calendario_dia_lectivo (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            anio SMALLINT UNSIGNED NOT NULL,
            fecha DATE NOT NULL,
            tipo VARCHAR(40) NOT NULL DEFAULT \'sin_clase_abierto\',
            aplica_a ENUM(\'todos\',\'sabado\',\'domingo\',\'entre_semana\') NOT NULL DEFAULT \'todos\',
            etiqueta VARCHAR(120) NULL,
            plantel_abierto TINYINT(1) NOT NULL DEFAULT 0,
            fecha_recuperacion DATE NULL,
            id_plantel INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_cal_fecha_plantel (fecha, id_plantel),
            KEY idx_cal_anio (anio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS notificacion_usuario (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT UNSIGNED NOT NULL,
            tipo VARCHAR(60) NOT NULL,
            titulo VARCHAR(160) NOT NULL,
            mensaje TEXT NOT NULL,
            enlace_seccion VARCHAR(80) NULL,
            enlace_params VARCHAR(255) NULL,
            leida TINYINT(1) NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notif_user (id_usuario, leida)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

/**
 * Domingo de Pascua (algoritmo de Meeus / PHP easter_days).
 */
function academico_domingo_pascua(int $anio): DateTimeImmutable
{
    $base = new DateTimeImmutable(sprintf('%d-03-21', $anio));
    if (function_exists('easter_days')) {
        return $base->modify('+' . easter_days($anio) . ' days');
    }
    // Fallback aproximado (Meeus)
    $a = $anio % 19;
    $b = intdiv($anio, 100);
    $c = $anio % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $mes = intdiv($h + $l - 7 * $m + 114, 31);
    $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

    return new DateTimeImmutable(sprintf('%d-%02d-%02d', $anio, $mes, $dia));
}

/**
 * Sugerencias para que dirección publique el calendario (no se aplican solas si hay calendario publicado).
 *
 * @return list<array{fecha: string, tipo: string, aplica_a: string, etiqueta: string}>
 */
function academico_calendario_sugerencias(int $anio): array
{
    $pascua = academico_domingo_pascua($anio);
    $sabadoGloria = $pascua->modify('-1 day');

    return [
        ['fecha' => sprintf('%d-01-01', $anio), 'tipo' => 'cierre_plantel', 'aplica_a' => 'todos', 'etiqueta' => 'Año nuevo'],
        ['fecha' => sprintf('%d-12-24', $anio), 'tipo' => 'sin_clase_abierto', 'aplica_a' => 'todos', 'etiqueta' => 'Nochebuena'],
        ['fecha' => sprintf('%d-12-31', $anio), 'tipo' => 'sin_clase_abierto', 'aplica_a' => 'todos', 'etiqueta' => 'Fin de año'],
        [
            'fecha' => $sabadoGloria->format('Y-m-d'),
            'tipo' => 'vacacion_sabado',
            'aplica_a' => 'sabado',
            'etiqueta' => 'Sábado de Gloria',
        ],
        [
            'fecha' => sprintf('%d-12-%02d', $anio, (int) academico_ultimo_sabado_mes($anio, 12)->format('d')),
            'tipo' => 'vacacion_sabado',
            'aplica_a' => 'sabado',
            'etiqueta' => 'Vacación sábado diciembre (sugerida)',
        ],
        [
            'fecha' => sprintf('%d-01-%02d', $anio, (int) academico_primer_sabado_mes($anio, 1)->format('d')),
            'tipo' => 'vacacion_sabado',
            'aplica_a' => 'sabado',
            'etiqueta' => 'Vacación sábado enero (sugerida)',
        ],
    ];
}

function academico_ultimo_sabado_mes(int $anio, int $mes): DateTimeImmutable
{
    $d = new DateTimeImmutable(sprintf('%d-%02d-01', $anio, $mes));
    $d = $d->modify('last day of this month');
    while ((int) $d->format('w') !== 6) {
        $d = $d->modify('-1 day');
    }
    return $d;
}

function academico_primer_sabado_mes(int $anio, int $mes): DateTimeImmutable
{
    $d = new DateTimeImmutable(sprintf('%d-%02d-01', $anio, $mes));
    while ((int) $d->format('w') !== 6) {
        $d = $d->modify('+1 day');
    }
    return $d;
}

/**
 * Mapa de calendario por fecha.
 *
 * @return array<string, array<string, mixed>> key=Y-m-d, value=row calendario
 */
function academico_calendario_mapa(PDO $pdo, int $anio, ?int $idPlantel = null, string $modelo = 'regular'): array
{
    $modelo = function_exists('calendario_modelo_normalizar')
        ? calendario_modelo_normalizar($modelo)
        : 'regular';
    $map = [];
    try {
        if ($idPlantel > 0) {
            $stmt = $pdo->prepare(
                'SELECT id, fecha, tipo, aplica_a, etiqueta, plantel_abierto, fecha_recuperacion, modelo
                 FROM calendario_dia_lectivo
                 WHERE anio = ? AND modelo = ? AND (id_plantel IS NULL OR id_plantel = ?) AND tipo != \'sugerencia\''
            );
            $stmt->execute([$anio, $modelo, $idPlantel]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, fecha, tipo, aplica_a, etiqueta, plantel_abierto, fecha_recuperacion, modelo
                 FROM calendario_dia_lectivo
                 WHERE anio = ? AND modelo = ? AND id_plantel IS NULL AND tipo != \'sugerencia\''
            );
            $stmt->execute([$anio, $modelo]);
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[$r['fecha']] = $r;
        }
    } catch (PDOException $e) {
        return [];
    }

    if (!academico_calendario_anio_publicado($pdo, $anio, $modelo)
        && $modelo === 'regular'
        && function_exists('academico_calendario_sugerencias')
    ) {
        foreach (academico_calendario_sugerencias($anio) as $s) {
            if (!isset($map[$s['fecha']])) {
                $map[$s['fecha']] = $s;
            }
        }
    }

    return $map;
}

function academico_calendario_anio_publicado(PDO $pdo, int $anio, string $modelo = 'regular'): bool
{
    $modelo = function_exists('calendario_modelo_normalizar')
        ? calendario_modelo_normalizar($modelo)
        : 'regular';
    try {
        $st = $pdo->prepare('SELECT publicado FROM calendario_escolar_anio WHERE anio = ? AND modelo = ?');
        $st->execute([$anio, $modelo]);
        return (int) $st->fetchColumn() === 1;
    } catch (PDOException $e) {
        try {
            $st = $pdo->prepare('SELECT publicado FROM calendario_escolar_anio WHERE anio = ?');
            $st->execute([$anio]);
            return (int) $st->fetchColumn() === 1;
        } catch (PDOException $e2) {
            return false;
        }
    }
}

function academico_fecha_afecta_horario(DateTimeInterface $fecha, string $codigoHorario, array $mapaCalendario): bool
{
    $f = $fecha->format('Y-m-d');
    $row = $mapaCalendario[$f] ?? null;
    if (function_exists('calendario_dia_sin_clase')) {
        return calendario_dia_sin_clase($row, $codigoHorario);
    }

    return (bool) $row;
}

/**
 * Sesiones de clase impartidas (sábado a sábado para S; días hábiles para M/V).
 */
function academico_sesiones_lectivas_desde(
    PDO $pdo,
    DateTimeInterface $inicio,
    string $codigoHorario,
    ?DateTimeInterface $hasta = null,
    ?int $idPlantel = null,
    string $modelo = 'regular'
): int {
    $modelo = function_exists('calendario_modelo_normalizar')
        ? calendario_modelo_normalizar($modelo)
        : 'regular';
    $hasta = $hasta ?? new DateTimeImmutable('today');
    if ($hasta < $inicio) {
        return 0;
    }

    $inicio = DateTimeImmutable::createFromInterface($inicio)->setTime(0, 0);
    $hasta = DateTimeImmutable::createFromInterface($hasta)->setTime(0, 0);
    $h = strtoupper($codigoHorario ?: 'S');
    $diaObjetivo = grupo_dia_clase_semana($h);
    $sesiones = 0;

    $anios = range((int) $inicio->format('Y'), (int) $hasta->format('Y'));
    $mapas = [];
    foreach ($anios as $y) {
        $mapas[$y] = academico_calendario_mapa($pdo, $y, $idPlantel, $modelo);
    }

    $recuperaciones = [];
    if (function_exists('calendario_modelo_permite_recuperacion') && calendario_modelo_permite_recuperacion($modelo)) {
        foreach ($mapas as $mapaAnio) {
            foreach ($mapaAnio as $f => $row) {
                if (!empty($row['fecha_recuperacion']) && calendario_normalizar_tipo((string) ($row['tipo'] ?? '')) === 'asueto') {
                    $recuperaciones[$row['fecha_recuperacion']] = true;
                }
            }
        }
    }

    if ($diaObjetivo >= 0) {
        $cursor = $inicio;
        while ((int) $cursor->format('w') !== $diaObjetivo) {
            $cursor = $cursor->modify('+1 day');
        }
        while ($cursor <= $hasta) {
            $y = (int) $cursor->format('Y');
            $f = $cursor->format('Y-m-d');
            if (!academico_fecha_afecta_horario($cursor, $h, $mapas[$y] ?? [])) {
                $sesiones++;
            }
            $cursor = $cursor->modify('+7 days');
        }
        return $sesiones;
    }

    // Entre semana (M/V): días hábiles; asuetos se recorren; recuperación suma sesión
    $diasContados = [];
    $cursor = $inicio;
    while ($cursor <= $hasta) {
        $dow = (int) $cursor->format('w');
        $f = $cursor->format('Y-m-d');
        if ($dow >= 1 && $dow <= 5) {
            $y = (int) $cursor->format('Y');
            if (!academico_fecha_afecta_horario($cursor, $h, $mapas[$y] ?? [])) {
                $sesiones++;
                $diasContados[$f] = true;
            }
        }
        $cursor = $cursor->modify('+1 day');
    }

    foreach ($recuperaciones as $fRec => $_) {
        if ($fRec < $inicio->format('Y-m-d') || $fRec > $hasta->format('Y-m-d')) {
            continue;
        }
        if (isset($diasContados[$fRec])) {
            continue;
        }
        $dRec = new DateTimeImmutable($fRec);
        $dow = (int) $dRec->format('w');
        if ($dow >= 1 && $dow <= 5) {
            $sesiones++;
            $diasContados[$fRec] = true;
        }
    }

    return $sesiones;
}

/** @deprecated Use academico_sesiones_lectivas_desde */
function academico_semanas_lectivas_desde(DateTimeInterface $inicio, ?DateTimeInterface $hasta = null): int
{
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return 0;
    }
    return academico_sesiones_lectivas_desde($pdo, $inicio, 'S', $hasta);
}

/**
 * Posición del grupo en el programa: índice de parcial (0-based) y semana 1-4.
 *
 * @return array{indice_parcial: int, semana_parcial: int, semanas_lectivas: int}
 */
function academico_posicion_grupo(PDO $pdo, array $grupo, ?DateTimeInterface $hasta = null): array
{
    $inicio = new DateTimeImmutable($grupo['fecha_inicio'] ?? 'today');
    $horario = $grupo['codigo_horario'] ?? 'S';
    $idPlantel = isset($grupo['id_plantel']) ? (int) $grupo['id_plantel'] : null;
    $modelo = function_exists('calendario_modelo_por_grupo')
        ? calendario_modelo_por_grupo($grupo)
        : 'regular';
    $semanas = academico_sesiones_lectivas_desde($pdo, $inicio, $horario, $hasta, $idPlantel, $modelo);
    $indice = intdiv(max(0, $semanas - 1), ACADEMICO_SEMANAS_POR_PARCIAL);
    $semanaParcial = (($semanas - 1) % ACADEMICO_SEMANAS_POR_PARCIAL) + 1;
    if ($semanas === 0) {
        $indice = 0;
        $semanaParcial = 1;
    }

    return [
        'semanas_lectivas' => $semanas,
        'indice_parcial' => $indice,
        'semana_parcial' => $semanaParcial,
    ];
}

/**
 * Calcula promedio ponderado y si aprueba (>= 6).
 *
 * @param array<string, float|int|string> $notas
 * @param list<array{codigo: string, peso_pct: float}> $criterios
 */
function academico_calcular_promedio(array $notas, array $criterios): array
{
    $suma = 0.0;
    $pesoTotal = 0.0;
    foreach ($criterios as $c) {
        $cod = $c['codigo'] ?? '';
        $peso = (float) ($c['peso_pct'] ?? 0);
        if ($cod === '' || $peso <= 0) {
            continue;
        }
        if (!isset($notas[$cod]) || $notas[$cod] === '' || $notas[$cod] === null) {
            continue;
        }
        $valor = (float) $notas[$cod];
        $suma += $valor * $peso;
        $pesoTotal += $peso;
    }
    $promedio = $pesoTotal > 0 ? round($suma / $pesoTotal, 2) : null;

    return [
        'promedio' => $promedio,
        'aprobado' => $promedio !== null && $promedio >= ACADEMICO_NOTA_MINIMA,
    ];
}

/** Rúbrica por defecto: 6 criterios obligatorios repartidos por igual. */
function academico_rubrica_default(): array
{
    $n = count(ACADEMICO_CRITERIOS_OBLIGATORIOS);
    $peso = round(100 / $n, 2);
    $out = [];
    foreach (ACADEMICO_CRITERIOS_OBLIGATORIOS as $cod) {
        $out[] = ['codigo' => $cod, 'peso_pct' => $peso, 'obligatorio' => true];
    }

    return $out;
}

function academico_notificar_usuario(
    PDO $pdo,
    int $idUsuario,
    string $tipo,
    string $titulo,
    string $mensaje,
    ?string $seccion = null,
    ?string $params = null
): void {
    try {
        $pdo->prepare(
            'INSERT INTO notificacion_usuario (id_usuario, tipo, titulo, mensaje, enlace_seccion, enlace_params)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$idUsuario, $tipo, $titulo, $mensaje, $seccion, $params]);
    } catch (PDOException $e) {
        error_log('academico_notificar_usuario: ' . $e->getMessage());
    }
}

function academico_notificar_profesor_alumno_nuevo(
    PDO $pdo,
    int $idGrupo,
    int $idAlumno,
    bool $ubicacion = false
): void {
    $g = $pdo->prepare('SELECT id_profesor, clave FROM grupos WHERE id_grupo = ? LIMIT 1');
    $g->execute([$idGrupo]);
    $grupo = $g->fetch(PDO::FETCH_ASSOC);
    if (!$grupo || empty($grupo['id_profesor'])) {
        return;
    }
    $a = $pdo->prepare('SELECT nombre, apellido, numero_control FROM alumnos WHERE id_alumno = ?');
    $a->execute([$idAlumno]);
    $al = $a->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        return;
    }
    $nombre = trim($al['nombre'] . ' ' . $al['apellido']);
    $titulo = $ubicacion ? 'Alumno por ubicación en tu grupo' : 'Nuevo alumno en tu grupo';
    $msg = $nombre . ' (# ' . ($al['numero_control'] ?? '') . ') se integró al grupo '
        . ($grupo['clave'] ?? '') . '.';
    if ($ubicacion) {
        $msg .= ' Revisa su nivel y ajusta la planeación si es necesario.';
    }
    academico_notificar_usuario(
        $pdo,
        (int) $grupo['id_profesor'],
        'alumno_grupo',
        $titulo,
        $msg,
        'alumnos',
        'id=' . $idAlumno
    );
}
