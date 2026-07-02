<?php

/**
 * Calendarios escolares por modelo (regular, prepa) y eventos administrativos.
 */

/** @return array<string, string> slug => etiqueta */
function calendario_modelos_lectivos(): array
{
    return [
        'regular' => 'Regular (inglés, cómputo, kids)',
        'prepa_escolarizada' => 'Prepa escolarizada (PE)',
        'prepa_abierta' => 'Prepa abierta (PA)',
    ];
}

function calendario_modelo_normalizar(string $modelo): string
{
    $modelo = strtolower(trim($modelo));
    return array_key_exists($modelo, calendario_modelos_lectivos()) ? $modelo : 'regular';
}

function calendario_modelo_es_prepa(string $modelo): bool
{
    return in_array(calendario_modelo_normalizar($modelo), ['prepa_escolarizada', 'prepa_abierta'], true);
}

function calendario_modelo_permite_recuperacion(string $modelo): bool
{
    return calendario_modelo_normalizar($modelo) === 'regular';
}

function calendario_modelo_usa_vacacion_sabado(string $modelo): bool
{
    return calendario_modelo_normalizar($modelo) === 'regular';
}

/** Modelo de calendario según clave o codigo_area del grupo. */
function calendario_modelo_por_grupo(array $grupo): string
{
    $area = strtoupper(trim((string) ($grupo['codigo_area'] ?? '')));
    if ($area === 'PE') {
        return 'prepa_escolarizada';
    }
    if ($area === 'PA') {
        return 'prepa_abierta';
    }
    $clave = strtoupper(trim((string) ($grupo['clave'] ?? '')));
    if (str_starts_with($clave, 'PE')) {
        return 'prepa_escolarizada';
    }
    if (str_starts_with($clave, 'PA')) {
        return 'prepa_abierta';
    }

    return 'regular';
}

function calendario_es_coordinador_prepa(): bool
{
    $depto = strtolower(trim((string) ($_SESSION['departamento'] ?? '')));

    return $depto === 'preparatoria'
        && in_array(rbac_rol_real(), ['gerente', 'profesor', 'admin'], true);
}

/** @return list<string> modelos que el usuario puede editar */
function calendario_modelos_editables_usuario(): array
{
    if (rbac_rol_real() === 'supervisor' || rbac_rol_efectivo() === 'supervisor') {
        return array_keys(calendario_modelos_lectivos());
    }
    if (calendario_es_coordinador_prepa()) {
        return ['prepa_escolarizada', 'prepa_abierta'];
    }
    if (rbac_rol_efectivo() === 'gerente') {
        return ['regular'];
    }

    return [];
}

function calendario_puede_editar_modelo(string $modelo): bool
{
    return in_array(calendario_modelo_normalizar($modelo), calendario_modelos_editables_usuario(), true);
}

function calendario_puede_editar_administrativo(): bool
{
    if (rbac_rol_real() === 'supervisor' || rbac_rol_efectivo() === 'supervisor') {
        return true;
    }
    if (rbac_rol_efectivo() === 'gerente') {
        return true;
    }

    return strtolower(trim((string) ($_SESSION['departamento'] ?? ''))) === 'administrativo'
        && rbac_rol_efectivo() === 'admin';
}

function calendario_puede_ver_menu(): bool
{
    return !empty(calendario_modelos_editables_usuario()) || calendario_puede_editar_administrativo();
}

/**
 * Modelos lectivos según grupos que el profesor tiene asignados.
 *
 * @return list<string>
 */
function calendario_modelos_impartidos_profesor(PDO $pdo, int $idUsuario, ?int $idPlantel = null): array
{
    $idPlantel = $idPlantel ?? plantel_id_activo();
    if ($idUsuario <= 0 || $idPlantel <= 0) {
        return [];
    }
    try {
        $st = $pdo->prepare(
            'SELECT DISTINCT g.codigo_area, g.clave
             FROM grupos g
             WHERE g.id_profesor = ? AND g.id_plantel = ?'
        );
        $st->execute([$idUsuario, $idPlantel]);
        $modelos = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $modelos[calendario_modelo_por_grupo($g)] = true;
        }

        return array_keys($modelos);
    } catch (PDOException $e) {
        return [];
    }
}

/** Etiqueta corta para capas en la vista combinada. */
function calendario_capa_prefijo(string $capaId): string
{
    $capaId = strtolower(trim($capaId));
    if ($capaId === 'administrativo') {
        return 'ADM';
    }

    return match (calendario_modelo_normalizar($capaId)) {
        'prepa_escolarizada' => 'PE',
        'prepa_abierta' => 'PA',
        'regular' => 'REG',
        default => strtoupper(substr($capaId, 0, 3)),
    };
}

/**
 * Capas que el usuario puede ver en el calendario combinado (solo lectura o edición en otra pantalla).
 *
 * @return list<array{id: string, label: string, tipo: string, editable: bool}>
 */
function calendario_capas_consulta(PDO $pdo, ?int $idUsuario = null, ?int $idPlantel = null): array
{
    $idUsuario = $idUsuario ?? (int) ($_SESSION['user_id'] ?? 0);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $labels = calendario_modelos_lectivos();
    $visibles = [];

    if (rbac_rol_real() === 'supervisor' || rbac_rol_efectivo() === 'supervisor') {
        $visibles = array_keys($labels);
    } else {
        $visibles = array_unique(array_merge(
            calendario_modelos_editables_usuario(),
            calendario_modelos_impartidos_profesor($pdo, $idUsuario, $idPlantel)
        ));
        $rol = rbac_rol_efectivo();
        if ($rol === 'asesor' && function_exists('rbac_cap') && rbac_cap('menu_calendario_consulta')) {
            $visibles = array_unique(array_merge($visibles, [
                'regular',
                'prepa_escolarizada',
                'prepa_abierta',
            ]));
        }
    }

    $capas = [];
    foreach ($visibles as $m) {
        if (!isset($labels[$m])) {
            continue;
        }
        $capas[] = [
            'id' => $m,
            'label' => $labels[$m],
            'tipo' => 'lectivo',
            'editable' => calendario_puede_editar_modelo($m),
            'prefijo' => calendario_capa_prefijo($m),
        ];
    }

    if (calendario_puede_ver_consulta($pdo, $idUsuario)) {
        $capas[] = [
            'id' => 'administrativo',
            'label' => 'Administrativo (juntas, capacitaciones)',
            'tipo' => 'admin',
            'editable' => calendario_puede_editar_administrativo(),
            'prefijo' => 'ADM',
        ];
    }

    return $capas;
}

function calendario_puede_ver_consulta(PDO $pdo, ?int $idUsuario = null): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (calendario_puede_ver_menu()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('menu_calendario_consulta')) {
        return true;
    }
    $idUsuario = $idUsuario ?? (int) $_SESSION['user_id'];
    if (rbac_rol_efectivo() !== 'profesor' && rbac_rol_real() !== 'profesor') {
        return false;
    }

    return !empty(calendario_modelos_impartidos_profesor($pdo, $idUsuario));
}

function calendario_puede_ver_capa(PDO $pdo, string $capaId, ?int $idUsuario = null): bool
{
    $capaId = strtolower(trim($capaId));
    foreach (calendario_capas_consulta($pdo, $idUsuario) as $c) {
        if ($c['id'] === $capaId) {
            return true;
        }
    }

    return false;
}

/** ¿El usuario debe ver este evento administrativo publicado? */
function calendario_usuario_en_audiencia_evento(PDO $pdo, int $idEvento, int $idUsuario): bool
{
    $u = $pdo->prepare('SELECT rol, departamento, id_plantel FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $u->execute([$idUsuario]);
    $user = $u->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return false;
    }

    $st = $pdo->prepare('SELECT tipo_audiencia, valor FROM calendario_evento_audiencia WHERE id_evento = ?');
    $st->execute([$idEvento]);
    $audiencias = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$audiencias) {
        return false;
    }

    foreach ($audiencias as $a) {
        $tipo = $a['tipo_audiencia'];
        $valor = trim((string) $a['valor']);
        if ($tipo === 'todos') {
            return true;
        }
        if ($tipo === 'rol' && $valor === ($user['rol'] ?? '')) {
            return true;
        }
        if ($tipo === 'departamento' && $valor === ($user['departamento'] ?? '')) {
            return true;
        }
        if ($tipo === 'usuario' && (int) $valor === $idUsuario) {
            return true;
        }
    }

    return false;
}

/**
 * Eventos administrativos publicados en un mes, opcionalmente filtrados por audiencia del usuario.
 *
 * @return array<string, list<array<string, mixed>>> fecha Y-m-d => eventos
 */
function calendario_eventos_admin_mapa_mes(
    PDO $pdo,
    int $anio,
    int $mes,
    ?int $idPlantel = null,
    ?int $idUsuarioSoloAudiencia = null
): array {
    calendario_migrate_schema($pdo);
    $mes = max(1, min(12, $mes));
    $desde = sprintf('%d-%02d-01', $anio, $mes);
    $hasta = (new DateTimeImmutable($desde))->modify('last day of this month')->format('Y-m-d');
    $idPlantel = $idPlantel ?? plantel_id_activo();

    $mapa = [];
    try {
        $st = $pdo->prepare(
            'SELECT id, titulo, descripcion, tipo, fecha, fecha_fin, hora_inicio, hora_fin, lugar, publicado
             FROM calendario_evento_admin
             WHERE publicado = 1 AND fecha <= ? AND (fecha_fin IS NULL OR fecha_fin >= ?)
               AND (id_plantel IS NULL OR id_plantel = ?)'
        );
        $st->execute([$hasta, $desde, $idPlantel]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ev) {
            if ($idUsuarioSoloAudiencia !== null
                && $idUsuarioSoloAudiencia > 0
                && !calendario_usuario_en_audiencia_evento($pdo, (int) $ev['id'], $idUsuarioSoloAudiencia)
            ) {
                continue;
            }
            $ini = new DateTimeImmutable($ev['fecha']);
            $fin = new DateTimeImmutable($ev['fecha_fin'] ?: $ev['fecha']);
            $cursor = $ini;
            while ($cursor <= $fin) {
                if ($cursor->format('Y-m') === sprintf('%d-%02d', $anio, $mes)) {
                    $f = $cursor->format('Y-m-d');
                    $mapa[$f][] = $ev;
                }
                $cursor = $cursor->modify('+1 day');
            }
        }
    } catch (PDOException $e) {
        return [];
    }

    return $mapa;
}

/**
 * Mes combinado para vista de consulta (varias capas a la vez).
 *
 * @param list<string> $capasIds
 * @return list<array{fecha: string, dia: int, dow: int, marcas: list<array<string, mixed>>}>
 */
function calendario_mes_combinado(
    PDO $pdo,
    int $anio,
    int $mes,
    array $capasIds,
    ?int $idPlantel = null,
    ?int $idUsuario = null
): array {
    $idUsuario = $idUsuario ?? (int) ($_SESSION['user_id'] ?? 0);
    $capasIds = array_values(array_unique(array_map('strtolower', array_map('trim', $capasIds))));
    $capasIds = array_filter($capasIds, static fn ($c) => calendario_puede_ver_capa($pdo, $c, $idUsuario));

    $mes = max(1, min(12, $mes));
    $inicio = new DateTimeImmutable(sprintf('%d-%02d-01', $anio, $mes));
    $fin = $inicio->modify('last day of this month');

    $lectivos = [];
    foreach ($capasIds as $capa) {
        if ($capa === 'administrativo') {
            continue;
        }
        $dias = calendario_dias_del_mes($pdo, $anio, $mes, $idPlantel, $capa);
        foreach ($dias as $d) {
            if ($d['tipo'] === 'lectivo') {
                continue;
            }
            $lectivos[$d['fecha']][] = [
                'capa' => $capa,
                'prefijo' => calendario_capa_prefijo($capa),
                'tipo' => $d['tipo'],
                'etiqueta' => $d['etiqueta'] ?: calendario_tipos_etiquetas($capa)[$d['tipo']] ?? $d['tipo'],
            ];
        }
    }

    $soloAudiencia = (rbac_rol_efectivo() === 'profesor' && !calendario_puede_editar_administrativo())
        ? $idUsuario
        : null;
    $adminMap = in_array('administrativo', $capasIds, true)
        ? calendario_eventos_admin_mapa_mes($pdo, $anio, $mes, $idPlantel, $soloAudiencia)
        : [];

    $tiposAdmin = calendario_evento_tipos();
    $out = [];
    $cursor = $inicio;
    while ($cursor <= $fin) {
        $f = $cursor->format('Y-m-d');
        $marcas = $lectivos[$f] ?? [];
        foreach ($adminMap[$f] ?? [] as $ev) {
            $tipo = (string) ($ev['tipo'] ?? 'evento');
            $marcas[] = [
                'capa' => 'administrativo',
                'prefijo' => 'ADM',
                'tipo' => 'admin_' . $tipo,
                'etiqueta' => $ev['titulo'],
                'detalle' => trim(
                    ($ev['hora_inicio'] ? substr((string) $ev['hora_inicio'], 0, 5) . ' ' : '')
                    . ($tiposAdmin[$tipo] ?? $tipo)
                    . ($ev['lugar'] ? ' · ' . $ev['lugar'] : '')
                ),
            ];
        }
        $out[] = [
            'fecha' => $f,
            'dia' => (int) $cursor->format('j'),
            'dow' => (int) $cursor->format('w'),
            'marcas' => $marcas,
        ];
        $cursor = $cursor->modify('+1 day');
    }

    return $out;
}

/** @return array<string, string> */
function calendario_tipos_etiquetas(?string $modelo = null): array
{
    $modelo = $modelo ? calendario_modelo_normalizar($modelo) : 'regular';
    $tipos = [
        'cierre_plantel' => 'Cierre de plantel (no labora nadie)',
        'sin_clase_abierto' => 'Sin clases — plantel abierto (cobranza)',
        'asueto' => calendario_modelo_permite_recuperacion($modelo)
            ? 'Asueto / festivo (recorrer entre semana)'
            : 'Asueto / festivo (sin recuperación)',
        'no_lectivo' => 'Sin clases (todos) — legado',
    ];
    if (calendario_modelo_usa_vacacion_sabado($modelo)) {
        $tipos['vacacion_sabado'] = 'Vacación — solo grupos sábado';
    }
    if (calendario_modelo_es_prepa($modelo)) {
        $tipos['vacacion_cuatrimestre'] = 'Vacaciones entre cuatrimestres';
    }

    return $tipos;
}

/** @return array<string, string> */
function calendario_tipos_colores(): array
{
    return [
        'cierre_plantel' => '#37474f',
        'sin_clase_abierto' => '#f9a825',
        'asueto' => '#7b1fa2',
        'vacacion_sabado' => '#1565c0',
        'vacacion_cuatrimestre' => '#00695c',
        'no_lectivo' => '#37474f',
    ];
}

function calendario_migrate_schema(PDO $pdo): void
{
    try {
        $col = $pdo->query("SHOW COLUMNS FROM calendario_dia_lectivo LIKE 'tipo'")->fetch(PDO::FETCH_ASSOC);
        if ($col && stripos($col['Type'], 'enum') !== false) {
            $pdo->exec(
                "ALTER TABLE calendario_dia_lectivo MODIFY tipo VARCHAR(40) NOT NULL DEFAULT 'sin_clase_abierto'"
            );
        }
    } catch (PDOException $e) {
        // tabla aún no existe
    }

    plantel_ensure_column($pdo, 'calendario_dia_lectivo', 'plantel_abierto', 'TINYINT(1) NOT NULL DEFAULT 0', 'etiqueta');
    plantel_ensure_column($pdo, 'calendario_dia_lectivo', 'fecha_recuperacion', 'DATE NULL', 'plantel_abierto');
    plantel_ensure_column($pdo, 'calendario_dia_lectivo', 'modelo', "VARCHAR(32) NOT NULL DEFAULT 'regular'", 'fecha_recuperacion');

    try {
        $idx = $pdo->query("SHOW INDEX FROM calendario_dia_lectivo WHERE Key_name = 'uq_cal_fecha_plantel'")->fetch();
        if ($idx) {
            $pdo->exec('ALTER TABLE calendario_dia_lectivo DROP INDEX uq_cal_fecha_plantel');
        }
        $idx2 = $pdo->query("SHOW INDEX FROM calendario_dia_lectivo WHERE Key_name = 'uq_cal_fecha_plantel_modelo'")->fetch();
        if (!$idx2) {
            $pdo->exec(
                'ALTER TABLE calendario_dia_lectivo ADD UNIQUE KEY uq_cal_fecha_plantel_modelo (fecha, id_plantel, modelo)'
            );
        }
    } catch (PDOException $e) {
        // ignorar si la tabla no existe aún
    }

    try {
        $colMod = $pdo->query("SHOW COLUMNS FROM calendario_escolar_anio LIKE 'modelo'")->fetch();
        if (!$colMod) {
            $pdo->exec(
                "ALTER TABLE calendario_escolar_anio ADD COLUMN modelo VARCHAR(32) NOT NULL DEFAULT 'regular' AFTER anio"
            );
            $pdo->exec('ALTER TABLE calendario_escolar_anio DROP PRIMARY KEY');
            $pdo->exec('ALTER TABLE calendario_escolar_anio ADD PRIMARY KEY (anio, modelo)');
        }
    } catch (PDOException $e) {
        // tabla nueva
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS calendario_evento_admin (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            titulo VARCHAR(160) NOT NULL,
            descripcion TEXT NULL,
            tipo VARCHAR(40) NOT NULL DEFAULT \'evento\',
            fecha DATE NOT NULL,
            fecha_fin DATE NULL,
            hora_inicio TIME NULL,
            hora_fin TIME NULL,
            lugar VARCHAR(160) NULL,
            publicado TINYINT(1) NOT NULL DEFAULT 0,
            id_plantel INT UNSIGNED NULL,
            creado_por INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cev_fecha (fecha),
            KEY idx_cev_pub (publicado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS calendario_evento_audiencia (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_evento INT UNSIGNED NOT NULL,
            tipo_audiencia ENUM(\'todos\',\'rol\',\'departamento\',\'usuario\') NOT NULL,
            valor VARCHAR(80) NOT NULL DEFAULT \'\',
            PRIMARY KEY (id),
            KEY idx_cea_evento (id_evento),
            CONSTRAINT fk_cea_evento FOREIGN KEY (id_evento) REFERENCES calendario_evento_admin (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function calendario_normalizar_tipo(string $tipo): string
{
    $tipo = strtolower(trim($tipo));
    if ($tipo === 'no_lectivo') {
        return 'cierre_plantel';
    }
    $validos = ['cierre_plantel', 'sin_clase_abierto', 'asueto', 'vacacion_sabado', 'vacacion_cuatrimestre'];
    return in_array($tipo, $validos, true) ? $tipo : 'sin_clase_abierto';
}

function calendario_aplica_a_horario(string $aplica, string $codigoHorario): bool
{
    $aplica = strtolower($aplica);
    $h = strtoupper(trim($codigoHorario));

    if ($aplica === 'todos' || $aplica === '') {
        return true;
    }
    if ($aplica === 'sabado' && $h === 'S') {
        return true;
    }
    if ($aplica === 'domingo' && $h === 'D') {
        return true;
    }
    if ($aplica === 'entre_semana' && in_array($h, ['M', 'V'], true)) {
        return true;
    }

    return false;
}

/**
 * @param array<string, mixed>|null $row fila del mapa calendario
 */
function calendario_dia_sin_clase(?array $row, string $codigoHorario): bool
{
    if (!$row) {
        return false;
    }
    $tipo = calendario_normalizar_tipo((string) ($row['tipo'] ?? ''));
    $aplica = (string) ($row['aplica_a'] ?? 'todos');

    if ($tipo === 'cierre_plantel') {
        return true;
    }
    if ($tipo === 'sin_clase_abierto') {
        return true;
    }
    if ($tipo === 'asueto' && calendario_aplica_a_horario($aplica, $codigoHorario)) {
        return true;
    }
    if ($tipo === 'vacacion_sabado' && calendario_aplica_a_horario('sabado', $codigoHorario)) {
        return true;
    }
    if ($tipo === 'vacacion_cuatrimestre') {
        return true;
    }

    return false;
}

function calendario_plantel_abierto_en_fecha(
    PDO $pdo,
    string $fecha,
    ?int $idPlantel = null,
    string $modelo = 'regular'
): bool {
    $anio = (int) date('Y', strtotime($fecha));
    $mapa = academico_calendario_mapa($pdo, $anio, $idPlantel, $modelo);
    $row = $mapa[$fecha] ?? null;
    if (!$row) {
        return true;
    }

    $tipo = calendario_normalizar_tipo((string) ($row['tipo'] ?? ''));
    if ($tipo === 'cierre_plantel') {
        return false;
    }
    if ($tipo === 'sin_clase_abierto') {
        return true;
    }
    if (!empty($row['plantel_abierto'])) {
        return true;
    }

    return true;
}

/**
 * Próximo día con clase programada para el horario del grupo (aprox. por calendario).
 */
function calendario_proxima_fecha_clase(
    PDO $pdo,
    string $codigoHorario,
    ?DateTimeInterface $desde = null,
    ?int $idPlantel = null,
    int $maxDias = 120,
    string $modelo = 'regular'
): ?string {
    $desde = $desde
        ? DateTimeImmutable::createFromInterface($desde)->setTime(0, 0)
        : new DateTimeImmutable('today');
    $h = strtoupper($codigoHorario ?: 'S');
    $diaObjetivo = grupo_dia_clase_semana($h);

    for ($i = 0; $i <= $maxDias; $i++) {
        $d = $desde->modify("+{$i} days");
        $f = $d->format('Y-m-d');
        $anio = (int) $d->format('Y');
        $mapa = academico_calendario_mapa($pdo, $anio, $idPlantel, $modelo);
        $row = $mapa[$f] ?? null;

        if (calendario_dia_sin_clase($row, $h)) {
            continue;
        }

        if ($diaObjetivo >= 0) {
            if ((int) $d->format('w') === $diaObjetivo) {
                return $f;
            }
            continue;
        }

        $dow = (int) $d->format('w');
        if ($dow >= 1 && $dow <= 5) {
            return $f;
        }
    }

    return null;
}

/**
 * Mensaje para recepción / punto de venta cuando no hay clase o el plantel está cerrado.
 *
 * @return array{tipo:string,titulo:string,mensaje:string,plantel_abierto:bool,proxima_clase:?string}|null
 */
function calendario_mensaje_para_alumno(PDO $pdo, int $idAlumno, ?string $fecha = null, ?int $idPlantel = null): ?array
{
    $fecha = $fecha ?: date('Y-m-d');
    $idPlantel = $idPlantel ?? plantel_id_activo();

    $st = $pdo->prepare(
        "SELECT a.id_alumno, g.clave, g.codigo_area, g.codigo_horario, gh.dia_semana, gh.hora_inicio
         FROM alumnos a
         INNER JOIN alumno_grupos ag ON ag.id_alumno = a.id_alumno AND ag.activo = 1
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         LEFT JOIN grupo_horarios gh ON gh.id_grupo = g.id_grupo AND gh.activo = 1
         WHERE a.id_alumno = ? AND a.id_plantel = ?
         ORDER BY gh.hora_inicio ASC
         LIMIT 1"
    );
    $st->execute([$idAlumno, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $horario = $row['codigo_horario'] ?? 'S';
    if (function_exists('grupo_extraer_codigo_horario')) {
        $horario = grupo_extraer_codigo_horario($row['clave'] ?? '') ?: $horario;
    }

    $modelo = calendario_modelo_por_grupo($row);
    $anio = (int) date('Y', strtotime($fecha));
    $mapa = academico_calendario_mapa($pdo, $anio, $idPlantel, $modelo);
    $dia = $mapa[$fecha] ?? null;
    $abierto = calendario_plantel_abierto_en_fecha($pdo, $fecha, $idPlantel, $modelo);
    $sinClase = calendario_dia_sin_clase($dia, $horario);
    $proxima = calendario_proxima_fecha_clase($pdo, $horario, new DateTimeImmutable($fecha . ' +1 day'), $idPlantel, 120, $modelo);

    if (!$sinClase && $abierto) {
        return null;
    }

    $etiqueta = $dia['etiqueta'] ?? 'Calendario escolar';
    $tipoCal = calendario_normalizar_tipo((string) ($dia['tipo'] ?? 'cierre_plantel'));

    if (!$abierto) {
        return [
            'tipo' => 'cierre',
            'titulo' => 'Plantel cerrado',
            'mensaje' => $etiqueta . '. No hay labores ni clases este día.'
                . ($proxima ? ' Próxima sesión de clase aproximada: ' . date('d/m/Y', strtotime($proxima)) . '.' : ''),
            'plantel_abierto' => false,
            'proxima_clase' => $proxima,
        ];
    }

    if ($tipoCal === 'vacacion_sabado' && strtoupper($horario) === 'S') {
        return [
            'tipo' => 'vacacion_sabado',
            'titulo' => 'Vacaciones (sábado)',
            'mensaje' => $etiqueta . '. No hay clase de sábado; la semana de curso se recorre a la siguiente sesión.'
                . ($proxima ? ' Siguiente sábado de clase: ' . date('d/m/Y', strtotime($proxima)) . '.' : ''),
            'plantel_abierto' => true,
            'proxima_clase' => $proxima,
        ];
    }

    if ($tipoCal === 'asueto') {
        $rec = !empty($dia['fecha_recuperacion'])
            ? date('d/m/Y', strtotime($dia['fecha_recuperacion']))
            : null;
        $msg = $etiqueta . '. No hay clase este día.';
        if ($rec) {
            $msg .= ' Recuperación programada: ' . $rec . '.';
        } elseif (in_array(strtoupper($horario), ['M', 'V'], true)) {
            if (calendario_modelo_permite_recuperacion($modelo)) {
                $msg .= ' Coordinación puede programar día de recuperación en el calendario regular.';
            }
        }
        $msg .= ' Puede realizar pagos en recepción.';
        if ($proxima) {
            $msg .= ' Próxima clase: ' . date('d/m/Y', strtotime($proxima)) . '.';
        }

        return [
            'tipo' => 'asueto',
            'titulo' => 'Asueto / día inhábil',
            'mensaje' => $msg,
            'plantel_abierto' => true,
            'proxima_clase' => $proxima,
        ];
    }

    return [
        'tipo' => 'sin_clase',
        'titulo' => 'Sin clases hoy',
        'mensaje' => ($etiqueta ?: 'Día sin clases') . '. El plantel permanece abierto; puede pagar en recepción.'
            . ($proxima ? ' Próxima clase: ' . date('d/m/Y', strtotime($proxima)) . '.' : ''),
        'plantel_abierto' => true,
        'proxima_clase' => $proxima,
    ];
}

/** @return list<array{fecha:string,tipo:string,aplica_a:string,etiqueta:string,plantel_abierto:int,fecha_recuperacion:?string,id:?int}> */
function calendario_dias_del_mes(
    PDO $pdo,
    int $anio,
    int $mes,
    ?int $idPlantel = null,
    string $modelo = 'regular'
): array {
    $mes = max(1, min(12, $mes));
    $modelo = calendario_modelo_normalizar($modelo);
    $inicio = new DateTimeImmutable(sprintf('%d-%02d-01', $anio, $mes));
    $fin = $inicio->modify('last day of this month');
    $mapa = academico_calendario_mapa($pdo, $anio, $idPlantel, $modelo);

    $out = [];
    $cursor = $inicio;
    while ($cursor <= $fin) {
        $f = $cursor->format('Y-m-d');
        $row = $mapa[$f] ?? null;
        $out[] = [
            'fecha' => $f,
            'dia' => (int) $cursor->format('j'),
            'dow' => (int) $cursor->format('w'),
            'tipo' => $row ? calendario_normalizar_tipo((string) $row['tipo']) : 'lectivo',
            'aplica_a' => $row['aplica_a'] ?? 'todos',
            'etiqueta' => $row['etiqueta'] ?? '',
            'plantel_abierto' => (int) ($row['plantel_abierto'] ?? ($row && calendario_normalizar_tipo((string) $row['tipo']) === 'sin_clase_abierto' ? 1 : 0)),
            'fecha_recuperacion' => $row['fecha_recuperacion'] ?? null,
            'id' => isset($row['id']) ? (int) $row['id'] : null,
        ];
        $cursor = $cursor->modify('+1 day');
    }

    return $out;
}

function calendario_guardar_dia(
    PDO $pdo,
    string $fecha,
    string $tipo,
    string $aplicaA,
    string $etiqueta,
    ?string $fechaRecuperacion,
    int $plantelAbierto,
    ?int $idPlantel = null,
    string $modelo = 'regular'
): array {
    calendario_migrate_schema($pdo);
    $fecha = trim($fecha);
    if ($fecha === '') {
        return ['ok' => false, 'message' => 'Fecha requerida'];
    }

    $modelo = calendario_modelo_normalizar($modelo);
    if (!calendario_puede_editar_modelo($modelo)) {
        return ['ok' => false, 'message' => 'Sin permiso para editar este calendario'];
    }

    $tipo = calendario_normalizar_tipo($tipo);
    $y = (int) date('Y', strtotime($fecha));

    if ($tipo === 'lectivo') {
        $pdo->prepare('DELETE FROM calendario_dia_lectivo WHERE fecha = ? AND id_plantel <=> ? AND modelo = ?')
            ->execute([$fecha, $idPlantel, $modelo]);
        return ['ok' => true, 'message' => 'Día lectivo (sin marca especial)'];
    }

    if ($tipo === 'sin_clase_abierto') {
        $plantelAbierto = 1;
    }
    if ($tipo === 'cierre_plantel' || $tipo === 'vacacion_cuatrimestre') {
        if ($tipo === 'cierre_plantel') {
            $plantelAbierto = 0;
        }
        $aplicaA = 'todos';
    }
    if (!calendario_modelo_permite_recuperacion($modelo)) {
        $fechaRecuperacion = null;
    }

    $pdo->prepare(
        'INSERT INTO calendario_escolar_anio (anio, modelo, publicado) VALUES (?, ?, 0)
         ON DUPLICATE KEY UPDATE anio = anio'
    )->execute([$y, $modelo]);

    $pdo->prepare(
        'INSERT INTO calendario_dia_lectivo (anio, fecha, tipo, aplica_a, etiqueta, plantel_abierto, fecha_recuperacion, id_plantel, modelo)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE tipo = VALUES(tipo), aplica_a = VALUES(aplica_a), etiqueta = VALUES(etiqueta),
           plantel_abierto = VALUES(plantel_abierto), fecha_recuperacion = VALUES(fecha_recuperacion)'
    )->execute([
        $y,
        $fecha,
        $tipo,
        $aplicaA,
        $etiqueta !== '' ? $etiqueta : null,
        $plantelAbierto ? 1 : 0,
        $fechaRecuperacion !== '' && $fechaRecuperacion !== null ? $fechaRecuperacion : null,
        $idPlantel,
        $modelo,
    ]);

    return ['ok' => true, 'message' => 'Día guardado'];
}

function calendario_guardar_rango(
    PDO $pdo,
    string $fechaInicio,
    string $fechaFin,
    string $tipo,
    string $aplicaA,
    string $etiqueta,
    int $plantelAbierto,
    ?int $idPlantel = null,
    string $modelo = 'regular'
): array {
    $ini = new DateTimeImmutable($fechaInicio);
    $fin = new DateTimeImmutable($fechaFin);
    if ($fin < $ini) {
        return ['ok' => false, 'message' => 'Rango inválido'];
    }
    $n = 0;
    $cursor = $ini;
    while ($cursor <= $fin) {
        calendario_guardar_dia($pdo, $cursor->format('Y-m-d'), $tipo, $aplicaA, $etiqueta, null, $plantelAbierto, $idPlantel, $modelo);
        $cursor = $cursor->modify('+1 day');
        $n++;
        if ($n > 400) {
            break;
        }
    }

    return ['ok' => true, 'message' => "Se marcaron {$n} días"];
}

/** @return array<string, string> */
function calendario_evento_tipos(): array
{
    return [
        'junta_directiva' => 'Junta directiva',
        'junta_personal' => 'Junta con personal',
        'capacitacion' => 'Capacitación',
        'evento' => 'Evento general',
        'otro' => 'Otro',
    ];
}

/** @return list<int> */
function calendario_evento_resolver_audiencia(PDO $pdo, int $idEvento, ?int $idPlantel = null): array
{
    $st = $pdo->prepare('SELECT tipo_audiencia, valor FROM calendario_evento_audiencia WHERE id_evento = ?');
    $st->execute([$idEvento]);
    $audiencias = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$audiencias) {
        return [];
    }

    $ids = [];
    foreach ($audiencias as $a) {
        $tipo = $a['tipo_audiencia'];
        $valor = trim((string) $a['valor']);
        if ($tipo === 'todos') {
            $sql = 'SELECT id_usuario FROM usuarios WHERE suspendido = 0';
            $params = [];
            if ($idPlantel > 0) {
                $sql .= ' AND (id_plantel IS NULL OR id_plantel = ?)';
                $params[] = $idPlantel;
            }
            $q = $pdo->prepare($sql);
            $q->execute($params);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                $ids[(int) $uid] = true;
            }
            continue;
        }
        if ($tipo === 'rol' && $valor !== '') {
            $sql = 'SELECT id_usuario FROM usuarios WHERE rol = ? AND suspendido = 0';
            $params = [$valor];
            if ($idPlantel > 0) {
                $sql .= ' AND (id_plantel IS NULL OR id_plantel = ?)';
                $params[] = $idPlantel;
            }
            $q = $pdo->prepare($sql);
            $q->execute($params);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                $ids[(int) $uid] = true;
            }
            continue;
        }
        if ($tipo === 'departamento' && $valor !== '') {
            $sql = 'SELECT id_usuario FROM usuarios WHERE departamento = ? AND suspendido = 0';
            $params = [$valor];
            if ($idPlantel > 0) {
                $sql .= ' AND (id_plantel IS NULL OR id_plantel = ?)';
                $params[] = $idPlantel;
            }
            $q = $pdo->prepare($sql);
            $q->execute($params);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                $ids[(int) $uid] = true;
            }
            continue;
        }
        if ($tipo === 'usuario' && (int) $valor > 0) {
            $ids[(int) $valor] = true;
        }
    }

    return array_keys($ids);
}

function calendario_evento_publicar(PDO $pdo, int $idEvento, int $idUsuarioPublica): array
{
    calendario_migrate_schema($pdo);
    $ev = $pdo->prepare('SELECT * FROM calendario_evento_admin WHERE id = ? LIMIT 1');
    $ev->execute([$idEvento]);
    $evento = $ev->fetch(PDO::FETCH_ASSOC);
    if (!$evento) {
        return ['ok' => false, 'message' => 'Evento no encontrado'];
    }

    $pdo->prepare(
        'UPDATE calendario_evento_admin SET publicado = 1, actualizado_en = NOW() WHERE id = ?'
    )->execute([$idEvento]);

    $idPlantel = isset($evento['id_plantel']) ? (int) $evento['id_plantel'] : null;
    $usuarios = calendario_evento_resolver_audiencia($pdo, $idEvento, $idPlantel > 0 ? $idPlantel : null);
    $fechaTxt = date('d/m/Y', strtotime($evento['fecha']));
    if (!empty($evento['fecha_fin']) && $evento['fecha_fin'] !== $evento['fecha']) {
        $fechaTxt .= ' – ' . date('d/m/Y', strtotime($evento['fecha_fin']));
    }
    $msg = trim((string) ($evento['descripcion'] ?? ''));
    if ($msg === '') {
        $msg = 'Revise el calendario administrativo para más detalle.';
    }
    if (!empty($evento['lugar'])) {
        $msg .= ' Lugar: ' . $evento['lugar'] . '.';
    }

    $notificados = 0;
    foreach ($usuarios as $uid) {
        if ($uid === $idUsuarioPublica) {
            continue;
        }
        academico_notificar_usuario(
            $pdo,
            $uid,
            'calendario_admin',
            (string) $evento['titulo'],
            $fechaTxt . ' — ' . $msg,
            'admin_calendario_admin',
            'id=' . $idEvento
        );
        $notificados++;
    }

    return ['ok' => true, 'message' => 'Evento publicado. Notificaciones enviadas: ' . $notificados];
}

/** @return list<array<string, mixed>> */
function notificaciones_usuario_bd(PDO $pdo, int $idUsuario, int $limite = 20): array
{
    if (function_exists('notificaciones_panel_ensure_schema')) {
        notificaciones_panel_ensure_schema($pdo);
    }
    try {
        $st = $pdo->prepare(
            'SELECT id, tipo, titulo, mensaje, enlace_seccion, enlace_params, leida, creado_en
             FROM notificacion_usuario
             WHERE id_usuario = ? AND leida = 0 AND archivada = 0
             ORDER BY creado_en DESC
             LIMIT ' . max(1, min(50, $limite))
        );
        $st->execute([$idUsuario]);
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'archivada') === false) {
            return [];
        }
        try {
            $st = $pdo->prepare(
                'SELECT id, tipo, titulo, mensaje, enlace_seccion, enlace_params, leida, creado_en
                 FROM notificacion_usuario
                 WHERE id_usuario = ? AND leida = 0
                 ORDER BY creado_en DESC
                 LIMIT ' . max(1, min(50, $limite))
            );
            $st->execute([$idUsuario]);
        } catch (PDOException $e2) {
            return [];
        }
    }
    try {
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $enlace = $r['enlace_seccion'] ?? '';
            if (!empty($r['enlace_params'])) {
                $enlace .= '&' . ltrim((string) $r['enlace_params'], '&');
            }
            $id = (int) ($r['id'] ?? 0);
            $out[] = [
                'id_notificacion' => $id,
                'clave' => 'db:' . $id,
                'fuente' => 'bd',
                'tipo' => $r['tipo'] ?? '',
                'titulo' => $r['titulo'],
                'mensaje' => $r['mensaje'],
                'prioridad' => ($r['tipo'] ?? '') === 'calendario_admin' ? 'alta' : 'media',
                'enlace' => $enlace,
            ];
        }

        return $out;
    } catch (PDOException $e) {
        return [];
    }
}
