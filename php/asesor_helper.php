<?php

/**
 * Rol asesor de ventas: entrevistas, consulta de grupos/fases, pre-registro certificaciones.
 */

function asesor_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS asesor_entrevistas (
            id_entrevista INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_usuario_asesor INT UNSIGNED NOT NULL COMMENT "Asesor al que se contabiliza",
            id_usuario_registra INT UNSIGNED NOT NULL COMMENT "Quién capturó (asesor o gerente)",
            origen ENUM(\'propia\',\'registrada_supervisor\') NOT NULL DEFAULT \'propia\',
            nombres VARCHAR(120) NOT NULL,
            apellido_paterno VARCHAR(80) NULL,
            apellido_materno VARCHAR(80) NULL,
            telefono VARCHAR(40) NULL,
            email VARCHAR(160) NULL,
            observaciones TEXT NULL,
            estado ENUM(\'contacto\',\'preregistro\',\'inscrito\',\'descartado\') NOT NULL DEFAULT \'contacto\',
            id_preregistro INT UNSIGNED NULL,
            id_alumno INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_entrevista),
            KEY idx_ent_plantel_asesor (id_plantel, id_usuario_asesor, creado_en),
            KEY idx_ent_estado (estado),
            KEY idx_ent_prereg (id_preregistro)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    if (function_exists('certificacion_campos_ensure_schema')) {
        certificacion_campos_ensure_schema($pdo);
    }

    asesor_asegurar_privilegio_ubicacion($pdo);
}

function asesor_asegurar_privilegio_ubicacion(PDO $pdo): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    try {
        foreach (['asesor', 'gerente', 'admin', 'director'] as $clave) {
            $st = $pdo->prepare('SELECT id_rol FROM roles WHERE clave = ? LIMIT 1');
            $st->execute([$clave]);
            $idRol = (int) $st->fetchColumn();
            if ($idRol > 0) {
                $pdo->prepare('INSERT IGNORE INTO role_privilegios (id_rol, privilegio) VALUES (?, ?)')
                    ->execute([$idRol, 'menu_ubicacion_asesor']);
            }
        }
    } catch (Throwable $e) {
        error_log('asesor_asegurar_privilegio_ubicacion: ' . $e->getMessage());
    }
}

function asesor_puede_entrevistas(): bool
{
    if (function_exists('rbac_usuario_en_roles') && rbac_usuario_en_roles(['asesor', 'gerente', 'supervisor', 'admin'])) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_entrevistas');
}

function asesor_puede_grupos_fases(): bool
{
    if (function_exists('rbac_usuario_en_roles') && rbac_usuario_en_roles(['asesor', 'gerente', 'supervisor', 'admin'])) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_grupos_fases');
}

function asesor_puede_cert_preregistro(): bool
{
    if (function_exists('rbac_usuario_en_roles') && rbac_usuario_en_roles(['asesor', 'gerente', 'supervisor', 'admin'])) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_cert_preregistro');
}

/** Gerente/supervisor puede registrar entrevista a nombre de un asesor. */
function asesor_puede_registrar_entrevista_ajena(): bool
{
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
    return in_array($rol, ['supervisor', 'gerente', 'admin'], true);
}

/**
 * Opciones del selector de asesor (entrevistas): el usuario actual primero y seleccionado por defecto.
 *
 * @return list<array{id_usuario:int, nombre:string, apellido:string, rol:string, es_yo:bool}>
 */
function asesor_entrevistas_opciones_asesor(PDO $pdo, int $idPlantel, int $idUsuario): array
{
    if ($idUsuario <= 0) {
        return [];
    }

    $equipo = gerente_asesores_plantel($pdo, $idPlantel);
    $porId = [];
    foreach ($equipo as $a) {
        $porId[(int) $a['id_usuario']] = $a;
    }

    if (!isset($porId[$idUsuario])) {
        $st = $pdo->prepare(
            'SELECT id_usuario, nombre, apellido, rol FROM usuarios
             WHERE id_usuario = ? AND (suspendido IS NULL OR suspendido = 0) LIMIT 1'
        );
        $st->execute([$idUsuario]);
        $yo = $st->fetch(PDO::FETCH_ASSOC);
        if ($yo) {
            $porId[$idUsuario] = $yo;
        }
    }

    if ($porId === []) {
        return [];
    }

    $out = [];
    if (isset($porId[$idUsuario])) {
        $yo = $porId[$idUsuario];
        $yo['es_yo'] = true;
        $out[] = $yo;
        unset($porId[$idUsuario]);
    }
    foreach ($porId as $a) {
        $a['es_yo'] = false;
        $out[] = $a;
    }

    return $out;
}

/**
 * Fecha aproximada en que el grupo entra a la fase (índice 0 = primera fase del programa).
 */
function asesor_fecha_inicio_fase(PDO $pdo, array $grupo, int $indiceFaseObjetivo, ?DateTimeInterface $hastaMax = null): ?string
{
    $inicio = new DateTimeImmutable($grupo['fecha_inicio'] ?? 'today');
    $hastaMax = $hastaMax ?? $inicio->modify('+3 years');
    $indiceFaseObjetivo = max(0, $indiceFaseObjetivo);

    for ($d = $inicio; $d <= $hastaMax; $d = $d->modify('+7 days')) {
        $pos = academico_posicion_grupo($pdo, $grupo, $d);
        if ((int) $pos['indice_parcial'] >= $indiceFaseObjetivo) {
            return $d->format('Y-m-d');
        }
    }

    return null;
}

/** Horario legible del grupo. */
function asesor_grupo_horario_txt(PDO $pdo, int $idGrupo): string
{
    $st = $pdo->prepare(
        'SELECT dia_semana, hora_inicio, hora_fin FROM grupo_horarios
         WHERE id_grupo = ? AND activo = 1 ORDER BY dia_semana, hora_inicio'
    );
    $st->execute([$idGrupo]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        return '—';
    }
    $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $parts = [];
    foreach ($rows as $r) {
        $d = $dias[(int) ($r['dia_semana'] ?? 0)] ?? '?';
        $hi = substr((string) ($r['hora_inicio'] ?? ''), 0, 5);
        $hf = substr((string) ($r['hora_fin'] ?? ''), 0, 5);
        $parts[] = $d . ' ' . $hi . ($hf !== '' ? '–' . $hf : '');
    }

    return implode(' · ', $parts);
}

/**
 * Grupos activos o por iniciar que entrarán (o están) en una fase.
 *
 * @param array{id_fase?: int, clave_fase?: string, id_especialidad?: int, solo_futuro?: bool} $filtros
 * @return list<array<string, mixed>>
 */
function asesor_grupos_por_fase(PDO $pdo, int $idPlantel, array $filtros): array
{
    $idFase = (int) ($filtros['id_fase'] ?? 0);
    $claveFase = trim((string) ($filtros['clave_fase'] ?? ''));
    if ($idFase <= 0 && $claveFase === '') {
        return [];
    }

    $hoy = new DateTimeImmutable('today');
    $params = [$idPlantel];
    $sql = 'SELECT g.*, e.nombre AS esp_nombre,
                   f.clave_fase, f.nombre_fase,
                   CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre
            FROM grupos g
            LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
            LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual
            LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
            WHERE g.id_plantel = ?';

    if (!empty($filtros['id_especialidad'])) {
        $sql .= ' AND g.id_especialidad = ?';
        $params[] = (int) $filtros['id_especialidad'];
    }

    $sql .= ' ORDER BY g.fecha_inicio ASC, g.clave ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $grupos = $st->fetchAll(PDO::FETCH_ASSOC);
    $out = [];

    foreach ($grupos as $g) {
        $idEsp = (int) ($g['id_especialidad'] ?? 0);
        $fases = $idEsp > 0 ? fase_listar($pdo, $idEsp) : [];
        if ($fases === []) {
            continue;
        }

        $idxObjetivo = -1;
        foreach ($fases as $i => $f) {
            if ($idFase > 0 && (int) $f['id_fase'] === $idFase) {
                $idxObjetivo = $i;
                break;
            }
            if ($claveFase !== '' && strcasecmp((string) ($f['clave_fase'] ?? ''), $claveFase) === 0) {
                $idxObjetivo = $i;
                break;
            }
        }
        if ($idxObjetivo < 0) {
            continue;
        }

        $faseObj = $fases[$idxObjetivo];
        $fechaEntrada = asesor_fecha_inicio_fase($pdo, $g, $idxObjetivo);
        if ($fechaEntrada === null) {
            continue;
        }
        $fechaEntradaDt = new DateTimeImmutable($fechaEntrada);
        $posHoy = academico_posicion_grupo($pdo, $g, $hoy);
        $idxHoy = (int) $posHoy['indice_parcial'];
        $estadoGrupo = 'programado';
        if ($fechaEntradaDt <= $hoy) {
            $estadoGrupo = $idxHoy === $idxObjetivo ? 'cursando_ahora' : ($idxHoy > $idxObjetivo ? 'ya_paso' : 'por_entrar');
        }

        if ($estadoGrupo === 'ya_paso') {
            continue;
        }

        $out[] = [
            'id_grupo' => (int) $g['id_grupo'],
            'clave' => $g['clave'] ?? '',
            'esp_nombre' => $g['esp_nombre'] ?? '',
            'fecha_inicio_grupo' => $g['fecha_inicio'] ?? '',
            'fecha_entrada_fase' => $fechaEntrada,
            'fase_clave' => $faseObj['clave_fase'] ?? '',
            'fase_nombre' => $faseObj['nombre_fase'] ?? '',
            'estado_grupo' => $estadoGrupo,
            'profesor_nombre' => trim($g['profesor_nombre'] ?? '') ?: '—',
            'horario' => asesor_grupo_horario_txt($pdo, (int) $g['id_grupo']),
            'aula' => $g['aula'] ?? '',
        ];
    }

    usort($out, static function ($a, $b) {
        return strcmp((string) $a['fecha_entrada_fase'], (string) $b['fecha_entrada_fase']);
    });

    return $out;
}

/** @return array{ok:bool,message:string,id_entrevista?:int} */
function asesor_entrevista_guardar(PDO $pdo, int $idPlantel, array $data): array
{
    asesor_ensure_schema($pdo);
    $sinDatos = !empty($data['sin_datos']) && asesor_puede_registrar_entrevista_ajena();
    $nombres = trim((string) ($data['nombres'] ?? ''));
    if ($nombres === '') {
        if ($sinDatos) {
            $nombres = 'Cliente sin datos';
        } else {
            return ['ok' => false, 'message' => 'Indique al menos el nombre'];
        }
    }

    $idAsesor = (int) ($data['id_usuario_asesor'] ?? $_SESSION['user_id'] ?? 0);
    $idRegistra = (int) ($data['id_usuario_registra'] ?? $_SESSION['user_id'] ?? 0);
    if ($idAsesor <= 0 || $idRegistra <= 0) {
        return ['ok' => false, 'message' => 'Sesión inválida'];
    }

    $origen = ($idRegistra !== $idAsesor && asesor_puede_registrar_entrevista_ajena())
        ? 'registrada_supervisor'
        : 'propia';

    if ($idRegistra !== $idAsesor && !asesor_puede_registrar_entrevista_ajena()) {
        $idRegistra = $idAsesor;
        $origen = 'propia';
    }

    $st = $pdo->prepare(
        'INSERT INTO asesor_entrevistas (
            id_plantel, id_usuario_asesor, id_usuario_registra, origen,
            nombres, apellido_paterno, apellido_materno, telefono, email, observaciones
        ) VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        $idPlantel,
        $idAsesor,
        $idRegistra,
        $origen,
        $nombres,
        trim((string) ($data['apellido_paterno'] ?? '')) ?: null,
        trim((string) ($data['apellido_materno'] ?? '')) ?: null,
        trim((string) ($data['telefono'] ?? '')) ?: null,
        trim((string) ($data['email'] ?? '')) ?: null,
        trim((string) ($data['observaciones'] ?? '')) ?: null,
    ]);

    return ['ok' => true, 'message' => 'Entrevista registrada', 'id_entrevista' => (int) $pdo->lastInsertId()];
}

function asesor_entrevista_periodo_desde(string $periodo): string
{
    return match ($periodo) {
        'semana' => (new DateTimeImmutable('monday this week'))->format('Y-m-d 00:00:00'),
        'mes' => (new DateTimeImmutable('first day of this month'))->format('Y-m-d 00:00:00'),
        default => (new DateTimeImmutable('today'))->format('Y-m-d 00:00:00'),
    };
}

/** @return list<array<string, mixed>> */
function asesor_entrevista_listar(
    PDO $pdo,
    int $idPlantel,
    int $idAsesor,
    ?string $estado = null,
    ?string $periodo = null
): array {
    asesor_ensure_schema($pdo);
    $params = [$idPlantel, $idAsesor];
    $sql = 'SELECT e.*,
                   CONCAT(ur.nombre, \' \', ur.apellido) AS registrado_por
            FROM asesor_entrevistas e
            LEFT JOIN usuarios ur ON ur.id_usuario = e.id_usuario_registra
            WHERE e.id_plantel = ? AND e.id_usuario_asesor = ?';
    if ($periodo !== null && $periodo !== '') {
        $sql .= ' AND e.creado_en >= ?';
        $params[] = asesor_entrevista_periodo_desde($periodo);
    }
    if ($estado !== null && $estado !== '') {
        $sql .= ' AND e.estado = ?';
        $params[] = $estado;
    }
    $sql .= ' ORDER BY e.creado_en DESC LIMIT 300';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed> */
function asesor_entrevista_estadisticas(PDO $pdo, int $idPlantel, int $idAsesor, string $periodo): array
{
    asesor_ensure_schema($pdo);
    $desde = asesor_entrevista_periodo_desde($periodo);

    $st = $pdo->prepare(
        'SELECT estado, COUNT(*) AS total FROM asesor_entrevistas
         WHERE id_plantel = ? AND id_usuario_asesor = ? AND creado_en >= ?
         GROUP BY estado'
    );
    $st->execute([$idPlantel, $idAsesor, $desde]);
    $porEstado = [];
    $total = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $porEstado[$r['estado']] = (int) $r['total'];
        $total += (int) $r['total'];
    }

    return [
        'periodo' => $periodo,
        'desde' => $desde,
        'total_entrevistas' => $total,
        'contacto' => (int) ($porEstado['contacto'] ?? 0),
        'preregistro' => (int) ($porEstado['preregistro'] ?? 0),
        'inscrito' => (int) ($porEstado['inscrito'] ?? 0),
        'descartado' => (int) ($porEstado['descartado'] ?? 0),
    ];
}

/** Vincula entrevista con pre-registro y actualiza estado. */
function asesor_entrevista_vincular_preregistro(PDO $pdo, int $idEntrevista, int $idPreregistro, int $idPlantel): bool
{
    asesor_ensure_schema($pdo);
    $st = $pdo->prepare(
        'UPDATE asesor_entrevistas SET id_preregistro = ?, estado = \'preregistro\', actualizado_en = NOW()
         WHERE id_entrevista = ? AND id_plantel = ?'
    );
    $st->execute([$idPreregistro, $idEntrevista, $idPlantel]);

    return $st->rowCount() > 0;
}
