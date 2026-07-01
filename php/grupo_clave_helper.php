<?php

/**
 * Claves de grupo: IS102, EK120, PER-TOEFL, etc.
 * La clave base no cambia al avanzar de fase; las fusiones se registran en BD (no en la clave).
 */

/** @var array<string, string> */
const GRUPO_AREAS = [
    'I' => 'Inglés',
    'K' => 'Infantil (crea pareja IK + CK)',
    'C' => 'Computación',
    'PA' => 'Prepa abierta',
    'PE' => 'Prepa escolarizada',
];

/** Prefijo de secuencia compartida para parejas infantiles IK{n} / CK{n}. */
const GRUPO_INFANTIL_SEQ_PREFIJO = 'KIDS';

/** @var array<string, string> */
const GRUPO_HORARIOS = [
    'S' => 'Sábados',
    'D' => 'Domingos',
    'M' => 'Matutino',
    'V' => 'Vespertino',
];

function grupo_clave_ensure_schema(PDO $pdo): void
{
    plantel_ensure_column($pdo, 'grupos', 'codigo_area', 'VARCHAR(4) NULL', 'clave');
    plantel_ensure_column($pdo, 'grupos', 'codigo_horario', "CHAR(1) NULL COMMENT 'S,D,M,V'", 'codigo_area');
    plantel_ensure_column($pdo, 'grupos', 'es_extensivo', 'TINYINT(1) NOT NULL DEFAULT 0', 'codigo_horario');
    plantel_ensure_column($pdo, 'grupos', 'es_personalizado', 'TINYINT(1) NOT NULL DEFAULT 0', 'es_extensivo');
    plantel_ensure_column($pdo, 'grupos', 'numero_secuencial', 'SMALLINT UNSIGNED NULL', 'es_personalizado');
    plantel_ensure_column($pdo, 'grupos', 'fusiones_total', 'TINYINT UNSIGNED NOT NULL DEFAULT 0', 'numero_secuencial');
    plantel_ensure_column($pdo, 'grupos', 'fusion_desfase', "ENUM('ninguno','adelanto','atraso') NOT NULL DEFAULT 'ninguno'", 'fusiones_total');
    plantel_ensure_column($pdo, 'grupos', 'id_grupo_pareja_infantil', 'INT UNSIGNED NULL', 'fusion_desfase');

    grupo_clave_ensure_secuencia_tabla($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_fusion_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo_resultante INT UNSIGNED NOT NULL,
            id_grupo_origen INT UNSIGNED NOT NULL,
            clave_grupo_origen VARCHAR(50) NOT NULL,
            desfase ENUM(\'ninguno\',\'adelanto\',\'atraso\') NOT NULL DEFAULT \'ninguno\',
            misma_fase TINYINT(1) NOT NULL DEFAULT 1,
            notas TEXT NULL,
            fusionado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            id_usuario INT UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY idx_gf_resultante (id_grupo_resultante)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

/** Tabla de consecutivos por plantel + prefijo (IS, CM, CD, etc.). */
function grupo_clave_ensure_secuencia_tabla(PDO $pdo): void
{
    if (!plantel_table_exists($pdo, 'grupo_clave_secuencia')
        || !plantel_column_exists($pdo, 'grupo_clave_secuencia', 'id_plantel')) {
        $pdo->exec('DROP TABLE IF EXISTS grupo_clave_secuencia');
        $pdo->exec(
            'CREATE TABLE grupo_clave_secuencia (
                id_plantel INT UNSIGNED NOT NULL,
                prefijo VARCHAR(24) NOT NULL,
                ultimo_numero SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (id_plantel, prefijo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}

function grupo_clave_normalizar_plantel(PDO $pdo, int $idPlantel): int
{
    if ($idPlantel <= 0 && function_exists('plantel_scope_id')) {
        $idPlantel = (int) plantel_scope_id($pdo);
    }
    if ($idPlantel <= 0) {
        throw new InvalidArgumentException('Plantel no válido para generar clave de grupo');
    }

    return $idPlantel;
}

/**
 * Prefijo para secuencia: IS, EIS, PER-TOEFL (sin número).
 */
function grupo_clave_armar_prefijo(string $area, string $horario, bool $extensivo, bool $personalizado, string $nombrePer = ''): string
{
    if ($personalizado) {
        $n = strtoupper(trim($nombrePer));
        if ($n === '') {
            return 'PER';
        }
        return str_starts_with($n, 'PER-') ? $n : 'PER-' . $n;
    }

    $area = strtoupper($area);
    $horario = strtoupper($horario);
    if (!isset(GRUPO_AREAS[$area]) && !in_array($area, ['PA', 'PE'], true)) {
        throw new InvalidArgumentException('Área de grupo no válida: ' . $area);
    }
    if (!isset(GRUPO_HORARIOS[$horario])) {
        throw new InvalidArgumentException('Horario no válido: ' . $horario);
    }

    return ($extensivo ? 'E' : '') . $area . $horario;
}

/** Sincroniza consecutivos desde grupos del plantel (por prefijo IS, CD, CM, etc.). */
function grupo_clave_sincronizar_secuencias(PDO $pdo, int $idPlantel = 0): void
{
    static $sincronizado = [];
    $idPlantel = $idPlantel > 0 ? grupo_clave_normalizar_plantel($pdo, $idPlantel) : 0;
    $cacheKey = $idPlantel > 0 ? (string) $idPlantel : '_todos';
    if (!empty($sincronizado[$cacheKey])) {
        return;
    }
    $sincronizado[$cacheKey] = true;

    grupo_clave_ensure_schema($pdo);

    $sql = "SELECT id_plantel, clave, codigo_area, codigo_horario, es_extensivo, es_personalizado, numero_secuencial
            FROM grupos
            WHERE es_personalizado = 0 AND clave IS NOT NULL AND clave <> '' AND id_plantel IS NOT NULL";
    $params = [];
    if ($idPlantel > 0) {
        $sql .= ' AND id_plantel = ?';
        $params[] = $idPlantel;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);

    /** @var array<string, int> $maxPorPlantelPrefijo clave "id|prefijo" => max */
    $maxPorPlantelPrefijo = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $idPl = (int) ($g['id_plantel'] ?? 0);
        if ($idPl <= 0) {
            continue;
        }
        $prefijo = '';
        $num = (int) ($g['numero_secuencial'] ?? 0);
        if (!empty($g['codigo_area']) && !empty($g['codigo_horario'])) {
            try {
                $prefijo = grupo_clave_armar_prefijo(
                    (string) $g['codigo_area'],
                    (string) $g['codigo_horario'],
                    (int) ($g['es_extensivo'] ?? 0) === 1,
                    false,
                    ''
                );
            } catch (Throwable $e) {
                $prefijo = '';
            }
        }
        if ($prefijo === '') {
            $clave = strtoupper(trim((string) ($g['clave'] ?? '')));
            if (preg_match('/^(E?(?:PA|PE|I|K|C)[SDMV])(\d+)$/i', $clave, $m)) {
                $prefijo = strtoupper($m[1]);
                $num = max($num, (int) $m[2]);
            } elseif (preg_match('/^IK(\d+)$/i', $clave, $m)) {
                $prefijo = GRUPO_INFANTIL_SEQ_PREFIJO;
                $num = max($num, (int) $m[1]);
            } elseif (preg_match('/^CK(\d+)$/i', $clave, $m)) {
                $prefijo = GRUPO_INFANTIL_SEQ_PREFIJO;
                $num = max($num, (int) $m[1]);
            }
        }
        if ($prefijo === '' || $num <= 0) {
            continue;
        }
        $key = $idPl . '|' . $prefijo;
        $maxPorPlantelPrefijo[$key] = max($maxPorPlantelPrefijo[$key] ?? 0, $num);
    }
    $ins = $pdo->prepare(
        'INSERT INTO grupo_clave_secuencia (id_plantel, prefijo, ultimo_numero) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE ultimo_numero = GREATEST(ultimo_numero, VALUES(ultimo_numero))'
    );
    foreach ($maxPorPlantelPrefijo as $key => $num) {
        [$idPl, $prefijo] = explode('|', $key, 2);
        $ins->execute([(int) $idPl, $prefijo, $num]);
    }
}

/** Siguiente número sin consumir la secuencia (solo vista previa). */
function grupo_clave_peek_siguiente_numero(PDO $pdo, int $idPlantel, string $prefijo): int
{
    $idPlantel = grupo_clave_normalizar_plantel($pdo, $idPlantel);
    grupo_clave_ensure_schema($pdo);
    grupo_clave_sincronizar_secuencias($pdo, $idPlantel);
    $st = $pdo->prepare(
        'SELECT ultimo_numero FROM grupo_clave_secuencia WHERE id_plantel = ? AND prefijo = ? LIMIT 1'
    );
    $st->execute([$idPlantel, $prefijo]);
    $ultimo = (int) $st->fetchColumn();

    return $ultimo + 1;
}

function grupo_clave_siguiente_numero(PDO $pdo, int $idPlantel, string $prefijo): int
{
    $idPlantel = grupo_clave_normalizar_plantel($pdo, $idPlantel);
    grupo_clave_sincronizar_secuencias($pdo, $idPlantel);
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        $pdo->prepare(
            'INSERT INTO grupo_clave_secuencia (id_plantel, prefijo, ultimo_numero) VALUES (?, ?, 0)
             ON DUPLICATE KEY UPDATE prefijo = prefijo'
        )->execute([$idPlantel, $prefijo]);
        $pdo->prepare(
            'UPDATE grupo_clave_secuencia SET ultimo_numero = ultimo_numero + 1
             WHERE id_plantel = ? AND prefijo = ?'
        )->execute([$idPlantel, $prefijo]);
        $st = $pdo->prepare(
            'SELECT ultimo_numero FROM grupo_clave_secuencia WHERE id_plantel = ? AND prefijo = ?'
        );
        $st->execute([$idPlantel, $prefijo]);
        $n = (int) $st->fetchColumn();
        if ($ownTx) {
            $pdo->commit();
        }

        return $n;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Vista previa de clave (no incrementa consecutivo).
 *
 * @return array{clave:string,prefijo:string,numero_secuencial:int,codigo_area:string,codigo_horario:string,es_extensivo:int,es_personalizado:int}
 */
function grupo_clave_vista_previa(
    PDO $pdo,
    int $idPlantel,
    string $area,
    string $horario,
    bool $extensivo = false,
    bool $personalizado = false,
    string $nombrePersonalizado = ''
): array {
    $idPlantel = grupo_clave_normalizar_plantel($pdo, $idPlantel);
    if (grupo_es_area_infantil($area) && !$personalizado && !$extensivo) {
        return grupo_infantil_vista_previa($pdo, $idPlantel);
    }
    $prefijo = grupo_clave_armar_prefijo($area, $horario, $extensivo, $personalizado, $nombrePersonalizado);

    if ($personalizado) {
        return [
            'clave' => $prefijo,
            'prefijo' => $prefijo,
            'numero_secuencial' => 0,
            'codigo_area' => 'PER',
            'codigo_horario' => '',
            'es_extensivo' => $extensivo ? 1 : 0,
            'es_personalizado' => 1,
            'id_plantel' => $idPlantel,
        ];
    }

    $num = grupo_clave_peek_siguiente_numero($pdo, $idPlantel, $prefijo);

    return [
        'clave' => $prefijo . $num,
        'prefijo' => $prefijo,
        'numero_secuencial' => $num,
        'codigo_area' => $area,
        'codigo_horario' => $horario,
        'es_extensivo' => $extensivo ? 1 : 0,
        'es_personalizado' => 0,
        'id_plantel' => $idPlantel,
    ];
}

function grupo_es_area_infantil(string $area): bool
{
    return strtoupper(trim($area)) === 'K';
}

/** id_especialidad infantil: ingles → ING-K, computacion → COMP-K. */
function grupo_id_especialidad_infantil(PDO $pdo, string $materia): ?int
{
    $clave = match (strtolower(trim($materia))) {
        'computacion', 'comp', 'informatica' => 'COMP-K',
        default => 'ING-K',
    };
    $st = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE activo = 1 AND UPPER(clave) = ? LIMIT 1');
    $st->execute([$clave]);

    $id = (int) $st->fetchColumn();

    return $id > 0 ? $id : null;
}

/**
 * Vista previa pareja infantil IK{n} + CK{n} (misma secuencia).
 *
 * @return array{clave:string,clave_ingles:string,clave_computacion:string,prefijo:string,numero_secuencial:int,es_pareja_infantil:int}
 */
function grupo_infantil_vista_previa(PDO $pdo, int $idPlantel): array
{
    $idPlantel = grupo_clave_normalizar_plantel($pdo, $idPlantel);
    $num = grupo_clave_peek_siguiente_numero($pdo, $idPlantel, GRUPO_INFANTIL_SEQ_PREFIJO);
    $claveIng = 'IK' . $num;
    $claveComp = 'CK' . $num;

    return [
        'clave' => $claveIng . ' + ' . $claveComp,
        'clave_ingles' => $claveIng,
        'clave_computacion' => $claveComp,
        'prefijo' => 'IK/CK',
        'numero_secuencial' => $num,
        'codigo_area' => 'K',
        'codigo_horario' => '',
        'es_extensivo' => 0,
        'es_personalizado' => 0,
        'es_pareja_infantil' => 1,
        'id_plantel' => $idPlantel,
    ];
}

/**
 * Reserva el siguiente número de pareja infantil (incrementa secuencia KIDS).
 */
function grupo_infantil_siguiente_numero(PDO $pdo, int $idPlantel): int
{
    return grupo_clave_siguiente_numero($pdo, $idPlantel, GRUPO_INFANTIL_SEQ_PREFIJO);
}

/** @return array{numero_secuencial:int,clave_ingles:string,clave_computacion:string} */
function grupo_infantil_generar_claves(PDO $pdo, int $idPlantel): array
{
    $num = grupo_infantil_siguiente_numero($pdo, $idPlantel);

    return [
        'numero_secuencial' => $num,
        'clave_ingles' => 'IK' . $num,
        'clave_computacion' => 'CK' . $num,
    ];
}

/**
 * Crea la pareja IK + CK con horario y fases compartidos.
 *
 * @param list<int> $diasSemana
 * @return array{ok:bool,message?:string,id_grupo_ingles?:int,id_grupo_computacion?:int,clave_ingles?:string,clave_computacion?:string}
 */
function grupo_infantil_crear_pareja(
    PDO $pdo,
    int $idPlantel,
    string $fechaInicio,
    ?int $idProfesor,
    ?int $idFaseIngles,
    ?int $idFaseComputacion,
    array $diasSemana,
    string $horaInicio,
    string $horaFin,
    ?string $horarioTexto
): array {
    grupo_clave_ensure_schema($pdo);
    asistencia_ensure_schema($pdo);

    $idEspIng = grupo_id_especialidad_infantil($pdo, 'ingles');
    $idEspComp = grupo_id_especialidad_infantil($pdo, 'computacion');
    if (!$idEspIng || !$idEspComp) {
        return ['ok' => false, 'message' => 'Faltan especialidades ING-K y COMP-K en el catálogo'];
    }

    if ($idFaseIngles === null || $idFaseIngles <= 0) {
        $idFaseIngles = grupo_primera_fase($pdo, $idEspIng);
    }
    if ($idFaseComputacion === null || $idFaseComputacion <= 0) {
        $idFaseComputacion = grupo_primera_fase($pdo, $idEspComp);
    }

    $claves = grupo_infantil_generar_claves($pdo, $idPlantel);
    $num = (int) $claves['numero_secuencial'];

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $ins = $pdo->prepare(
            'INSERT INTO grupos (
                id_plantel, clave, fecha_inicio, id_profesor, aula, id_especialidad, id_fase_actual,
                moodle_nivel, horario_texto, codigo_area, codigo_horario, es_extensivo, es_personalizado, numero_secuencial
            ) VALUES (?, ?, ?, ?, NULL, ?, ?, NULL, ?, ?, ?, 0, 0, ?)'
        );

        $ins->execute([
            $idPlantel, $claves['clave_ingles'], $fechaInicio, $idProfesor, $idEspIng, $idFaseIngles,
            $horarioTexto, 'I', null, $num,
        ]);
        $idGrupoIng = (int) $pdo->lastInsertId();

        $ins->execute([
            $idPlantel, $claves['clave_computacion'], $fechaInicio, $idProfesor, $idEspComp, $idFaseComputacion,
            $horarioTexto, 'C', null, $num,
        ]);
        $idGrupoComp = (int) $pdo->lastInsertId();

        if (function_exists('tutor_asignar_grupo')) {
            tutor_asignar_grupo($pdo, $idGrupoIng);
            tutor_asignar_grupo($pdo, $idGrupoComp);
        }

        $pdo->prepare('UPDATE grupos SET id_grupo_pareja_infantil = ? WHERE id_grupo = ?')
            ->execute([$idGrupoComp, $idGrupoIng]);
        $pdo->prepare('UPDATE grupos SET id_grupo_pareja_infantil = ? WHERE id_grupo = ?')
            ->execute([$idGrupoIng, $idGrupoComp]);

        if ($diasSemana !== []) {
            grupo_guardar_horarios($pdo, $idGrupoIng, $diasSemana, $horaInicio, $horaFin);
            grupo_guardar_horarios($pdo, $idGrupoComp, $diasSemana, $horaInicio, $horaFin);
        }

        if ($ownTx) {
            $pdo->commit();
        }

        return [
            'ok' => true,
            'id_grupo_ingles' => $idGrupoIng,
            'id_grupo_computacion' => $idGrupoComp,
            'clave_ingles' => $claves['clave_ingles'],
            'clave_computacion' => $claves['clave_computacion'],
            'numero_secuencial' => $num,
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** id_especialidad principal según código de área del grupo. */
function grupo_area_id_especialidad(PDO $pdo, string $area): ?int
{
    $area = strtoupper(trim($area));
    if ($area === 'K') {
        return grupo_id_especialidad_infantil($pdo, 'ingles');
    }
    $candidatos = match ($area) {
        'I' => ['ING', 'INGLES', 'INGLÉS', 'ING-K'],
        'C' => ['COMP', 'COMPUTACION', 'INFORMATICA', 'COMP-K'],
        'PA' => ['PA', 'PREPA-ABIERTA', 'PREPA_ABIERTA'],
        'PE' => ['PE', 'PREPA-ESC', 'PREPA_ESCOLARIZADA'],
        default => [],
    };
    if ($candidatos === []) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT id_especialidad FROM especialidades WHERE activo = 1 AND UPPER(clave) = ? LIMIT 1'
    );
    foreach ($candidatos as $c) {
        $st->execute([strtoupper($c)]);
        $id = (int) $st->fetchColumn();
        if ($id > 0) {
            return $id;
        }
    }

    return null;
}

/** Primera fase (inicio) de una especialidad. */
function grupo_primera_fase(PDO $pdo, int $idEspecialidad): ?int
{
    if ($idEspecialidad <= 0) {
        return null;
    }
    fase_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT id_fase FROM especialidad_fases WHERE id_especialidad = ? AND activo = 1
         ORDER BY orden ASC, id_fase ASC LIMIT 1'
    );
    $st->execute([$idEspecialidad]);
    $id = (int) $st->fetchColumn();

    return $id > 0 ? $id : null;
}

/** @param list<int> $diasSemana 0=Dom … 6=Sáb */
function grupo_guardar_horarios(PDO $pdo, int $idGrupo, array $diasSemana, string $horaInicio, string $horaFin): void
{
    asistencia_ensure_schema($pdo);
    $pdo->prepare('DELETE FROM grupo_horarios WHERE id_grupo = ?')->execute([$idGrupo]);
    $ins = $pdo->prepare(
        'INSERT INTO grupo_horarios (id_grupo, dia_semana, hora_inicio, hora_fin, activo) VALUES (?, ?, ?, ?, 1)'
    );
    foreach ($diasSemana as $d) {
        $d = (int) $d;
        if ($d >= 0 && $d <= 6) {
            $ins->execute([$idGrupo, $d, $horaInicio, $horaFin]);
        }
    }
}

function grupo_horario_texto_desde_dias(array $diasSemana, string $horaInicio, string $horaFin): string
{
    $labels = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $partes = [];
    foreach ($diasSemana as $d) {
        $d = (int) $d;
        if ($d >= 0 && $d <= 6) {
            $partes[] = $labels[$d];
        }
    }
    $hi = substr($horaInicio, 0, 5);
    $hf = substr($horaFin, 0, 5);

    return implode(', ', $partes) . ' ' . $hi . '–' . $hf;
}

/**
 * Genera clave nueva (IS102, EK120, PER-TOEFL sin número incremental en personalizado).
 */
function grupo_clave_generar(
    PDO $pdo,
    int $idPlantel,
    string $area,
    string $horario,
    bool $extensivo = false,
    bool $personalizado = false,
    string $nombrePersonalizado = ''
): array {
    $idPlantel = grupo_clave_normalizar_plantel($pdo, $idPlantel);
    $prefijo = grupo_clave_armar_prefijo($area, $horario, $extensivo, $personalizado, $nombrePersonalizado);

    if ($personalizado) {
        $clave = $prefijo;
        $num = 0;
    } else {
        $num = grupo_clave_siguiente_numero($pdo, $idPlantel, $prefijo);
        $clave = $prefijo . $num;
    }

    return [
        'clave' => $clave,
        'prefijo' => $prefijo,
        'numero_secuencial' => $num,
        'codigo_area' => $personalizado ? 'PER' : $area,
        'codigo_horario' => $personalizado ? '' : $horario,
        'es_extensivo' => $extensivo ? 1 : 0,
        'es_personalizado' => $personalizado ? 1 : 0,
        'id_plantel' => $idPlantel,
    ];
}

/**
 * Etiqueta visible: clave + insignias de fusión (mejor que asteriscos en la clave).
 */
function grupo_clave_etiqueta(array $grupo): string
{
    $clave = trim($grupo['clave'] ?? '');
    $fusiones = (int) ($grupo['fusiones_total'] ?? 0);
    if ($fusiones <= 0) {
        return $clave;
    }

    $desfase = $grupo['fusion_desfase'] ?? 'ninguno';
    $suf = ' · Fusión ×' . $fusiones;
    if ($desfase === 'adelanto') {
        $suf .= ' ↑';
    } elseif ($desfase === 'atraso') {
        $suf .= ' ↓';
    }

    return $clave . $suf;
}

/** HTML con clave y badges para tablas. */
function grupo_clave_html(array $grupo): string
{
    $clave = htmlspecialchars(trim($grupo['clave'] ?? ''), ENT_QUOTES, 'UTF-8');
    $fusiones = (int) ($grupo['fusiones_total'] ?? 0);
    if ($fusiones <= 0) {
        return '<strong>' . $clave . '</strong>';
    }
    $desfase = $grupo['fusion_desfase'] ?? 'ninguno';
    $badges = '<span class="grupo-badge grupo-badge--fusion" title="Fusiones registradas">×' . $fusiones . '</span>';
    if ($desfase === 'adelanto') {
        $badges .= '<span class="grupo-badge grupo-badge--adelanto" title="Grupo integrado adelantando">↑</span>';
    } elseif ($desfase === 'atraso') {
        $badges .= '<span class="grupo-badge grupo-badge--atraso" title="Grupo integrado atrasando">↓</span>';
    }

    return '<strong>' . $clave . '</strong> ' . $badges;
}

/**
 * Registra fusión de dos grupos en el grupo que conserva la clave.
 */
function grupo_registrar_fusion(
    PDO $pdo,
    int $idGrupoResultante,
    int $idGrupoOrigen,
    string $desfase = 'ninguno',
    bool $mismaFase = true,
    ?int $idUsuario = null,
    string $notas = ''
): array {
    $desfase = in_array($desfase, ['ninguno', 'adelanto', 'atraso'], true) ? $desfase : 'ninguno';
    if ($mismaFase) {
        $desfase = 'ninguno';
    }

    $gO = $pdo->prepare('SELECT clave, fusiones_total FROM grupos WHERE id_grupo = ?');
    $gO->execute([$idGrupoOrigen]);
    $origen = $gO->fetch(PDO::FETCH_ASSOC);
    if (!$origen) {
        return ['ok' => false, 'message' => 'Grupo origen no encontrado'];
    }

    $pdo->prepare(
        'INSERT INTO grupo_fusion_log (id_grupo_resultante, id_grupo_origen, clave_grupo_origen, desfase, misma_fase, notas, id_usuario)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $idGrupoResultante,
        $idGrupoOrigen,
        $origen['clave'],
        $desfase,
        $mismaFase ? 1 : 0,
        $notas ?: null,
        $idUsuario,
    ]);

    if ($mismaFase || $desfase === 'ninguno') {
        $pdo->prepare('UPDATE grupos SET fusiones_total = fusiones_total + 1 WHERE id_grupo = ?')
            ->execute([$idGrupoResultante]);
    } else {
        $pdo->prepare(
            'UPDATE grupos SET fusiones_total = fusiones_total + 1, fusion_desfase = ? WHERE id_grupo = ?'
        )->execute([$desfase, $idGrupoResultante]);
    }

    $st = $pdo->prepare('SELECT fusiones_total FROM grupos WHERE id_grupo = ?');
    $st->execute([$idGrupoResultante]);
    $total = (int) $st->fetchColumn();

    return ['ok' => true, 'message' => 'Fusión registrada', 'fusiones_total' => $total];
}

/** Día de la semana PHP (0=dom … 6=sáb) en que imparte el grupo. */
function grupo_dia_clase_semana(string $codigoHorario): int
{
    return match (strtoupper($codigoHorario)) {
        'S' => 6,
        'D' => 0,
        default => -1, // entre semana: se cuenta por día hábil en calendario
    };
}
