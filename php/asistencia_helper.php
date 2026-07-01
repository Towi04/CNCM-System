<?php

/**
 * Asistencias: huella digital, recepción en aula, horarios de grupo.
 */

function asistencia_ensure_schema(PDO $pdo): void
{
    alumno_ensure_schema($pdo);

    plantel_ensure_column($pdo, 'grupos', 'id_profesor', 'INT UNSIGNED NULL', 'fecha_inicio');
    plantel_ensure_column($pdo, 'grupos', 'aula', 'VARCHAR(60) NULL', 'id_profesor');
    plantel_ensure_column($pdo, 'alumnos', 'codigo_huella', 'VARCHAR(40) NULL', 'foto');
    plantel_ensure_column($pdo, 'usuarios', 'codigo_huella', 'VARCHAR(40) NULL', 'id_usuario');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_horarios (
            id_horario INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo INT UNSIGNED NOT NULL,
            dia_semana TINYINT UNSIGNED NOT NULL COMMENT "0=Dom..6=Sab, 1=Lun en convencion alternativa usamos PHP w",
            hora_inicio TIME NOT NULL,
            hora_fin TIME NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_horario),
            KEY idx_gh_grupo (id_grupo),
            KEY idx_gh_dia (dia_semana)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS asistencias (
            id_asistencia BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo INT UNSIGNED NOT NULL,
            id_alumno INT UNSIGNED NOT NULL,
            fecha DATE NOT NULL,
            anio SMALLINT UNSIGNED NOT NULL,
            semana TINYINT UNSIGNED NOT NULL,
            presente TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_asistencia),
            UNIQUE KEY uq_asistencia (id_alumno, fecha),
            KEY idx_asist_grupo_fecha (id_grupo, fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    asistencia_migrate_columns($pdo);
    asistencia_migrate_unique_key($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS asistencia_personal (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            fecha DATE NOT NULL,
            hora_llegada TIME NOT NULL,
            hora_salida TIME NULL,
            origen ENUM(\'huella\',\'manual\',\'recepcion\') NOT NULL DEFAULT \'huella\',
            id_usuario_registro INT UNSIGNED NULL,
            nota VARCHAR(255) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_personal_fecha (id_usuario, fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    plantel_ensure_column($pdo, 'asistencia_personal', 'hora_salida', 'TIME NULL', 'hora_llegada');
    plantel_ensure_column($pdo, 'asistencia_personal', 'id_usuario_registro', 'INT UNSIGNED NULL', 'origen');
    plantel_ensure_column($pdo, 'asistencia_personal', 'nota', 'VARCHAR(255) NULL', 'id_usuario_registro');
    plantel_ensure_column($pdo, 'asistencia_personal', 'actualizado_en', 'DATETIME NULL', 'creado_en');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS huella_eventos (
            id_evento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NULL,
            codigo_huella VARCHAR(40) NOT NULL,
            canal ENUM(\'lector_fijo\',\'movil\',\'prueba\') NOT NULL DEFAULT \'lector_fijo\',
            id_usuario_operador INT UNSIGNED NULL,
            tipo ENUM(\'alumno\',\'personal\',\'desconocido\') NOT NULL DEFAULT \'desconocido\',
            id_referencia INT UNSIGNED NULL,
            procesado TINYINT(1) NOT NULL DEFAULT 0,
            mensaje VARCHAR(255) NULL,
            fecha_hora DATETIME NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_evento),
            KEY idx_he_fecha (fecha_hora),
            KEY idx_he_codigo (codigo_huella)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    plantel_ensure_column($pdo, 'huella_eventos', 'canal', "ENUM('lector_fijo','movil','prueba') NOT NULL DEFAULT 'lector_fijo'", 'codigo_huella');
    plantel_ensure_column($pdo, 'huella_eventos', 'id_usuario_operador', 'INT UNSIGNED NULL', 'canal');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS asistencia_falta_seguimiento (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_grupo INT UNSIGNED NOT NULL,
            fecha DATE NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            estado_contacto VARCHAR(30) NOT NULL DEFAULT \'pendiente\',
            observacion TEXT NULL,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_falta_dia (id_alumno, id_grupo, fecha),
            KEY idx_falta_fecha_plantel (fecha, id_plantel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS huella_codigos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tipo ENUM(\'alumno\',\'usuario\') NOT NULL,
            id_referencia INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NULL,
            codigo_huella VARCHAR(40) NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_huella_codigo_plantel (codigo_huella, id_plantel),
            KEY idx_huella_ref (tipo, id_referencia)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function asistencia_migrate_columns(PDO $pdo): void
{
    $cols = [
        'origen' => "VARCHAR(20) NOT NULL DEFAULT 'recepcion' AFTER presente",
        'hora_llegada' => 'TIME NULL AFTER origen',
        'id_usuario_registro' => 'INT UNSIGNED NULL AFTER hora_llegada',
    ];
    foreach ($cols as $col => $def) {
        plantel_ensure_column($pdo, 'asistencias', $col, $def, 'presente');
    }
}

function asistencia_migrate_unique_key(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT INDEX_NAME FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = 'asistencias'
           AND index_name = 'uq_asistencia' AND NON_UNIQUE = 0 LIMIT 1"
    );
    if (!$stmt->fetchColumn()) {
        return;
    }
    $cols = $pdo->query(
        "SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = 'asistencias' AND index_name = 'uq_asistencia'"
    )->fetchColumn();
    if ($cols === 'id_alumno,fecha') {
        try {
            $pdo->exec('ALTER TABLE asistencias DROP INDEX uq_asistencia');
            $pdo->exec('ALTER TABLE asistencias ADD UNIQUE KEY uq_asist_alumno_grupo_fecha (id_alumno, id_grupo, fecha)');
        } catch (PDOException $e) {
            // ya migrado o restricción
        }
    }
}

function asistencia_puede_tomar(): bool
{
    if (function_exists('rbac_cap')) {
        return rbac_cap('asistencia_lista_grupo');
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
    return in_array($rol, ['admin', 'gerente', 'profesor', 'supervisor'], true);
}

function asistencia_puede_movil(): bool
{
    return false;
}

function asistencia_puede_ver_puntualidad(): bool
{
    return function_exists('rbac_cap') ? rbac_cap('asistencia_puntualidad') : false;
}

function asistencia_puede_registrar_personal_manual(): bool
{
    return function_exists('rbac_cap') ? rbac_cap('asistencia_personal_manual') : false;
}

/** Sesión móvil desbloqueada (operador se autenticó en el celular). */
function asistencia_movil_sesion_activa(): bool
{
    $hasta = (int) ($_SESSION['asistencia_movil_ok_until'] ?? 0);
    return $hasta > time();
}

function asistencia_movil_desbloquear(int $minutos = 15): void
{
    $_SESSION['asistencia_movil_ok_until'] = time() + ($minutos * 60);
}

function asistencia_movil_cerrar(): void
{
    unset($_SESSION['asistencia_movil_ok_until']);
}

function asistencia_calc_semana(string $fecha): array
{
    $stmt = null;
    return [
        (int) date('Y', strtotime($fecha)),
        (int) date('W', strtotime($fecha)),
    ];
}

/** Día semana MySQL WEEK mode 0 = domingo=0; PHP date('w') igual */
function asistencia_dia_semana(string $fecha): int
{
    return (int) date('w', strtotime($fecha));
}

/**
 * Grupos con clase en una fecha (por horario) o todos si sin horario.
 * @return array<int, array<string, mixed>>
 */
function asistencia_sesiones_del_dia(PDO $pdo, int $idPlantel, string $fecha, array $filtros = []): array
{
    $dow = asistencia_dia_semana($fecha);
    $sql = "SELECT g.id_grupo, g.clave, g.aula, g.id_profesor,
            CONCAT(p.nombre, ' ', p.apellido) AS profesor_nombre,
            gh.hora_inicio, gh.hora_fin, gh.id_horario,
            e.nombre AS especialidad_nombre
            FROM grupos g
            LEFT JOIN usuarios p ON p.id_usuario = g.id_profesor
            LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
            LEFT JOIN grupo_horarios gh ON gh.id_grupo = g.id_grupo AND gh.dia_semana = ? AND gh.activo = 1
            WHERE g.id_plantel = ?";
    $params = [$dow, $idPlantel];

    if (!empty($filtros['id_grupo'])) {
        $sql .= ' AND g.id_grupo = ?';
        $params[] = (int) $filtros['id_grupo'];
    }
    if (!empty($filtros['id_profesor'])) {
        $idProfF = (int) $filtros['id_profesor'];
        $sql .= ' AND ' . grupo_docente_sql_filtro_profesor('g');
        $params[] = $idProfF;
        $params[] = $idProfF;
    }
    if (!empty($filtros['aula'])) {
        $sql .= ' AND g.aula LIKE ?';
        $params[] = '%' . $filtros['aula'] . '%';
    }
    if (!empty($filtros['solo_con_clase_hoy'])) {
        $sql .= ' AND gh.id_horario IS NOT NULL';
    }

    $sql .= ' ORDER BY gh.hora_inicio ASC, g.clave ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    $seen = [];
    foreach ($rows as $r) {
        $gid = (int) $r['id_grupo'];
        if (isset($seen[$gid])) {
            continue;
        }
        $seen[$gid] = true;

        $stats = asistencia_resumen_grupo($pdo, $gid, $fecha);
        $out[] = array_merge($r, $stats);
    }

    if (empty($out) && !empty($filtros['solo_con_clase_hoy'])) {
        return asistencia_sesiones_del_dia($pdo, $idPlantel, $fecha, array_merge($filtros, ['solo_con_clase_hoy' => false]));
    }

    return $out;
}

function asistencia_resumen_grupo(PDO $pdo, int $idGrupo, string $fecha): array
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT ag.id_alumno) FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.estado = \'activo\''
    );
    $stmt->execute([$idGrupo]);
    $total = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM asistencias
         WHERE id_grupo = ? AND fecha = ? AND presente = 1"
    );
    $stmt->execute([$idGrupo, $fecha]);
    $presentes = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM asistencias
         WHERE id_grupo = ? AND fecha = ? AND presente = 1 AND origen IN ('huella','movil')"
    );
    $stmt->execute([$idGrupo, $fecha]);
    $porHuella = (int) $stmt->fetchColumn();

    return [
        'total_alumnos' => $total,
        'presentes' => $presentes,
        'por_huella' => $porHuella,
        'faltantes_estimados' => max(0, $total - $presentes),
    ];
}

/** @return array<int, array<string, mixed>> */
function asistencia_lista_alumnos_grupo(PDO $pdo, int $idGrupo, string $fecha): array
{
    $stmt = $pdo->prepare(
        "SELECT a.id_alumno, a.nombres, a.nombre, a.apellido_paterno, a.apellido, a.apellido_materno,
                a.numero_control, a.codigo_huella,
                asi.presente, asi.origen, asi.hora_llegada, asi.id_asistencia
         FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         LEFT JOIN asistencias asi ON asi.id_alumno = a.id_alumno AND asi.id_grupo = ? AND asi.fecha = ?
         WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.estado = 'activo'
         ORDER BY a.apellido_paterno, a.apellido, a.nombres, a.nombre"
    );
    $stmt->execute([$idGrupo, $fecha, $idGrupo]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['nombre_completo'] = alumno_nombre_completo($r);
        $r['ya_registrado'] = $r['id_asistencia'] !== null;
        $r['presente'] = $r['id_asistencia'] ? (int) $r['presente'] : 0;
        $r['bloqueado'] = in_array($r['origen'], ['huella', 'movil'], true) && (int) $r['presente'] === 1;
    }
    unset($r);
    return $rows;
}

function asistencia_resolver_grupo_activo(PDO $pdo, int $idAlumno, int $idPlantel, ?string $fechaHora = null): ?int
{
    $fechaHora = $fechaHora ?: date('Y-m-d H:i:s');
    $fecha = date('Y-m-d', strtotime($fechaHora));
    $hora = date('H:i:s', strtotime($fechaHora));
    $dow = asistencia_dia_semana($fecha);

    $stmt = $pdo->prepare(
        'SELECT gh.id_grupo FROM alumno_grupos ag
         INNER JOIN grupo_horarios gh ON gh.id_grupo = ag.id_grupo AND gh.dia_semana = ? AND gh.activo = 1
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         WHERE ag.id_alumno = ? AND ag.activo = 1 AND g.id_plantel = ?
           AND ? BETWEEN gh.hora_inicio AND gh.hora_fin
         ORDER BY gh.hora_inicio LIMIT 1'
    );
    $stmt->execute([$dow, $idAlumno, $idPlantel, $hora]);
    $gid = $stmt->fetchColumn();
    if ($gid) {
        return (int) $gid;
    }

    $stmt = $pdo->prepare(
        'SELECT ag.id_grupo FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         WHERE ag.id_alumno = ? AND ag.activo = 1 AND g.id_plantel = ?
         ORDER BY ag.fecha_inicio DESC LIMIT 1'
    );
    $stmt->execute([$idAlumno, $idPlantel]);
    $gid = $stmt->fetchColumn();
    return $gid ? (int) $gid : null;
}

function asistencia_registrar_huella_alumno(
    PDO $pdo,
    int $idAlumno,
    int $idGrupo,
    int $idPlantel,
    string $fechaHora,
    string $origen = 'huella'
): array {
    $origen = in_array($origen, ['huella', 'movil'], true) ? $origen : 'huella';
    $fecha = date('Y-m-d', strtotime($fechaHora));
    $hora = date('H:i:s', strtotime($fechaHora));
    [$anio, $semana] = asistencia_calc_semana($fecha);

    $stmt = $pdo->prepare(
        'SELECT id_asistencia, origen, presente, id_grupo FROM asistencias
         WHERE id_alumno = ? AND fecha = ? LIMIT 1'
    );
    $stmt->execute([$idAlumno, $fecha]);
    $ex = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ex && in_array($ex['origen'], ['huella', 'movil'], true) && (int) $ex['presente'] === 1) {
        return ['ok' => true, 'message' => 'Asistencia ya registrada', 'duplicado' => true];
    }

    if ($ex) {
        $pdo->prepare(
            'UPDATE asistencias SET presente = 1, origen = ?,
             hora_llegada = COALESCE(hora_llegada, ?), id_grupo = ?
             WHERE id_asistencia = ?'
        )->execute([$origen, $hora, $idGrupo, $ex['id_asistencia']]);
    } else {
        $pdo->prepare(
            'INSERT INTO asistencias (id_grupo, id_alumno, fecha, anio, semana, presente, origen, hora_llegada)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?)'
        )->execute([$idGrupo, $idAlumno, $fecha, $anio, $semana, $origen, $hora]);
    }

    $msg = $origen === 'movil' ? 'Asistencia registrada (móvil)' : 'Asistencia registrada por huella';
    return ['ok' => true, 'message' => $msg, 'duplicado' => false];
}

function asistencia_registrar_huella_personal(
    PDO $pdo,
    int $idUsuario,
    int $idPlantel,
    string $fechaHora,
    bool $esSalida = false
): array {
    $fecha = date('Y-m-d', strtotime($fechaHora));
    $hora = date('H:i:s', strtotime($fechaHora));

    $ex = $pdo->prepare('SELECT id, hora_llegada, hora_salida FROM asistencia_personal WHERE id_usuario = ? AND fecha = ? LIMIT 1');
    $ex->execute([$idUsuario, $fecha]);
    $row = $ex->fetch(PDO::FETCH_ASSOC);

    if ($esSalida) {
        if (!$row) {
            $pdo->prepare(
                'INSERT INTO asistencia_personal (id_usuario, id_plantel, fecha, hora_llegada, hora_salida, origen)
                 VALUES (?, ?, ?, ?, ?, \'huella\')'
            )->execute([$idUsuario, $idPlantel, $fecha, $hora, $hora]);
        } else {
            $pdo->prepare('UPDATE asistencia_personal SET hora_salida = ? WHERE id = ?')->execute([$hora, $row['id']]);
        }
        return ['ok' => true, 'message' => 'Salida de personal registrada', 'tipo' => 'salida'];
    }

    if ($row && !empty($row['hora_llegada']) && empty($row['hora_salida'])) {
        $pdo->prepare('UPDATE asistencia_personal SET hora_salida = ? WHERE id = ?')->execute([$hora, $row['id']]);
        return ['ok' => true, 'message' => 'Salida registrada (segunda checada)', 'tipo' => 'salida'];
    }

    $pdo->prepare(
        'INSERT INTO asistencia_personal (id_usuario, id_plantel, fecha, hora_llegada, origen)
         VALUES (?, ?, ?, ?, \'huella\')
         ON DUPLICATE KEY UPDATE hora_llegada = IF(hora_llegada IS NULL, VALUES(hora_llegada), hora_llegada)'
    )->execute([$idUsuario, $idPlantel, $fecha, $hora]);

    return ['ok' => true, 'message' => 'Entrada de personal registrada', 'tipo' => 'entrada'];
}

/**
 * @param 'lector_fijo'|'movil'|'prueba' $canal
 */
function asistencia_procesar_codigo_huella(
    PDO $pdo,
    string $codigo,
    int $idPlantel,
    ?string $fechaHora = null,
    string $canal = 'lector_fijo',
    ?int $idOperador = null,
    ?array $identHint = null
): array {
    $codigo = trim($codigo);
    if ($codigo === '') {
        return ['ok' => false, 'message' => 'Código vacío'];
    }
    $fechaHora = $fechaHora ?: date('Y-m-d H:i:s');

    $log = $pdo->prepare(
        'INSERT INTO huella_eventos (id_plantel, codigo_huella, canal, id_usuario_operador, fecha_hora)
         VALUES (?, ?, ?, ?, ?)'
    );
    $log->execute([$idPlantel, $codigo, $canal, $idOperador, $fechaHora]);
    $idEvento = (int) $pdo->lastInsertId();

    $ref = null;
    if ($identHint && !empty($identHint['id_referencia']) && !empty($identHint['tipo'])) {
        $tipoHint = (string) $identHint['tipo'];
        if ($tipoHint === 'personal' || $tipoHint === 'usuario') {
            $ref = [
                'tipo' => 'usuario',
                'id_referencia' => (int) $identHint['id_referencia'],
                'nombre' => trim((string) ($identHint['nombre'] ?? '')),
            ];
        } elseif ($tipoHint === 'alumno') {
            $ref = [
                'tipo' => 'alumno',
                'id_referencia' => (int) $identHint['id_referencia'],
                'nombre' => trim((string) ($identHint['nombre'] ?? '')),
            ];
        }
    }
    if (!$ref) {
        $ref = asistencia_resolver_codigo_huella($pdo, $codigo, $idPlantel);
    }
    if (!$ref) {
        asistencia_log_evento($pdo, $idEvento, 'desconocido', null, 0, 'Código no reconocido');
        return ['ok' => false, 'message' => 'Huella no reconocida en el sistema'];
    }

    if ($ref['tipo'] === 'alumno') {
        $idAlumno = (int) $ref['id_referencia'];
        $idGrupo = asistencia_resolver_grupo_activo($pdo, $idAlumno, $idPlantel, $fechaHora);
        if (!$idGrupo) {
            asistencia_log_evento($pdo, $idEvento, 'alumno', $idAlumno, 0, 'Sin grupo activo');
            return [
                'ok' => false,
                'message' => 'El alumno no tiene un grupo activo asignado. Verifique inscripción al grupo en el sistema.',
            ];
        }
        $origen = $canal === 'movil' ? 'movil' : 'huella';
        $res = asistencia_registrar_huella_alumno($pdo, $idAlumno, $idGrupo, $idPlantel, $fechaHora, $origen);
        asistencia_log_evento($pdo, $idEvento, 'alumno', $idAlumno, 1, $res['message']);
        $res['id_alumno'] = $idAlumno;
        $res['id_grupo'] = $idGrupo;
        $res['nombre'] = $ref['nombre'] ?? '';
        return $res;
    }

    // Personal: solo lector fijo en plantel (no celular del propio profesor)
    if ($canal === 'movil') {
        asistencia_log_evento($pdo, $idEvento, 'personal', (int) $ref['id_referencia'], 0, 'Personal no permitido desde móvil');
        return [
            'ok' => false,
            'message' => 'La asistencia del personal solo se registra en el lector del plantel o por dirección.',
        ];
    }

    $idUser = (int) $ref['id_referencia'];
    if ($idOperador > 0 && $idOperador === $idUser && $canal !== 'lector_fijo') {
        asistencia_log_evento($pdo, $idEvento, 'personal', $idUser, 0, 'Auto-registro bloqueado');
        return ['ok' => false, 'message' => 'No puede registrar su propia asistencia desde este dispositivo.'];
    }

    $res = asistencia_registrar_huella_personal($pdo, $idUser, $idPlantel, $fechaHora);
    asistencia_log_evento($pdo, $idEvento, 'personal', $idUser, 1, $res['message']);
    $res['id_usuario'] = $idUser;
    $res['nombre'] = $ref['nombre'] ?? '';
    return $res;
}

/** @return array{tipo:string,id_referencia:int,nombre:string}|null */
function asistencia_resolver_codigo_huella(PDO $pdo, string $codigo, int $idPlantel): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id_usuario AS id, CONCAT(nombre, \' \', apellido) AS nombre
         FROM usuarios
         WHERE codigo_huella = ?
           AND rol NOT IN (\'alumno\')
           AND (id_plantel = ? OR id_plantel IS NULL)
         LIMIT 1'
    );
    $stmt->execute([$codigo, $idPlantel]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return ['tipo' => 'usuario', 'id_referencia' => (int) $row['id'], 'nombre' => trim($row['nombre'])];
    }

    $codigoNorm = huella_normalizar_codigo($codigo);
    if ($codigoNorm !== '' && preg_match('/^P(\d+)$/i', $codigoNorm, $m)) {
        $stmt = $pdo->prepare(
            'SELECT id_usuario AS id, CONCAT(nombre, \' \', apellido) AS nombre
             FROM usuarios WHERE id_usuario = ? AND rol NOT IN (\'alumno\') LIMIT 1'
        );
        $stmt->execute([(int) $m[1]]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return ['tipo' => 'usuario', 'id_referencia' => (int) $row['id'], 'nombre' => trim($row['nombre'])];
        }
    }

    $stmt = $pdo->prepare(
        'SELECT id_alumno AS id, CONCAT(COALESCE(apellido_paterno,apellido,\'\'), \' \', COALESCE(nombres,nombre,\'\')) AS nombre
         FROM alumnos WHERE id_plantel = ? AND codigo_huella = ? AND estado = \'activo\' LIMIT 1'
    );
    $stmt->execute([$idPlantel, $codigo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return ['tipo' => 'alumno', 'id_referencia' => (int) $row['id'], 'nombre' => trim($row['nombre'])];
    }

    return null;
}

function asistencia_sync_codigo_huella(
    PDO $pdo,
    string $tipo,
    int $idReferencia,
    ?string $codigo,
    ?int $idPlantel = null
): void {
    $tipo = $tipo === 'usuario' ? 'usuario' : 'alumno';
    $pdo->prepare('UPDATE huella_codigos SET activo = 0 WHERE tipo = ? AND id_referencia = ?')->execute([$tipo, $idReferencia]);
    $codigo = trim((string) $codigo);
    if ($codigo === '') {
        return;
    }
    $pdo->prepare(
        'INSERT INTO huella_codigos (tipo, id_referencia, id_plantel, codigo_huella, activo)
         VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE activo = 1, id_referencia = VALUES(id_referencia), tipo = VALUES(tipo)'
    )->execute([$tipo, $idReferencia, $idPlantel, $codigo]);
}

function asistencia_log_evento(PDO $pdo, int $idEvento, string $tipo, ?int $idRef, int $ok, string $msg): void
{
    $pdo->prepare(
        'UPDATE huella_eventos SET tipo = ?, id_referencia = ?, procesado = ?, mensaje = ? WHERE id_evento = ?'
    )->execute([$tipo, $idRef, $ok ? 1 : 0, mb_substr($msg, 0, 255), $idEvento]);
}

function asistencia_guardar_recepcion(
    PDO $pdo,
    int $idGrupo,
    string $fecha,
    array $presentesIds,
    int $idUsuario
): array {
    if (!plantel_grupo_pertenece($pdo, $idGrupo)) {
        return ['ok' => false, 'message' => 'Grupo no válido'];
    }

    [$anio, $semana] = asistencia_calc_semana($fecha);
    $alumnos = asistencia_lista_alumnos_grupo($pdo, $idGrupo, $fecha);
    $idsGrupo = array_map(fn($a) => (int) $a['id_alumno'], $alumnos);

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            "INSERT INTO asistencias (id_grupo, id_alumno, fecha, anio, semana, presente, origen, hora_llegada, id_usuario_registro)
             VALUES (?, ?, ?, ?, ?, ?, 'recepcion', NULL, ?)
             ON DUPLICATE KEY UPDATE
               presente = VALUES(presente),
               origen = 'recepcion',
               hora_llegada = NULL,
               id_usuario_registro = VALUES(id_usuario_registro)"
        );
        foreach ($alumnos as $row) {
            $idA = (int) $row['id_alumno'];
            if (!empty($row['bloqueado'])) {
                continue;
            }
            $pres = in_array($idA, $presentesIds, true) ? 1 : 0;
            $ins->execute([$idGrupo, $idA, $fecha, $anio, $semana, $pres, $idUsuario]);
        }
        $pdo->commit();
        return ['ok' => true, 'message' => 'Lista de recepción guardada'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Recepción marca presente a un alumno (rondín en salones) por no. control o id.
 * No sustituye una checada con huella ya registrada.
 */
function asistencia_registrar_recepcion_alumno(
    PDO $pdo,
    int $idAlumno,
    int $idPlantel,
    string $fecha,
    int $idUsuario,
    ?int $idGrupo = null
): array {
    if (!asistencia_puede_tomar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $fecha = date('Y-m-d');
    }
    $hora = date('H:i:s');
    $fechaHora = $fecha . ' ' . $hora;

    $al = alumno_obtener($pdo, $idAlumno, $idPlantel);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado en este plantel'];
    }

    $stmt = $pdo->prepare(
        'SELECT id_asistencia, presente, origen FROM asistencias
         WHERE id_alumno = ? AND fecha = ? AND presente = 1 LIMIT 1'
    );
    $stmt->execute([$idAlumno, $fecha]);
    $ex = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ex && in_array($ex['origen'] ?? '', ['huella', 'movil'], true)) {
        return [
            'ok' => true,
            'message' => 'El alumno ya checó con huella hoy',
            'duplicado' => true,
            'id_alumno' => $idAlumno,
            'nombre' => $al['nombre_completo'] ?? '',
        ];
    }

    $idGrupo = $idGrupo > 0 ? $idGrupo : (asistencia_resolver_grupo_activo($pdo, $idAlumno, $idPlantel, $fechaHora) ?? 0);
    if ($idGrupo <= 0) {
        return ['ok' => false, 'message' => 'Alumno sin grupo activo en este horario'];
    }

    [$anio, $semana] = asistencia_calc_semana($fecha);

    if ($ex) {
        $pdo->prepare(
            'UPDATE asistencias SET presente = 1, origen = \'recepcion\', hora_llegada = ?,
             id_grupo = ?, id_usuario_registro = ? WHERE id_asistencia = ?'
        )->execute([$hora, $idGrupo, $idUsuario, $ex['id_asistencia']]);
    } else {
        $pdo->prepare(
            'INSERT INTO asistencias (id_grupo, id_alumno, fecha, anio, semana, presente, origen, hora_llegada, id_usuario_registro)
             VALUES (?, ?, ?, ?, ?, 1, \'recepcion\', ?, ?)'
        )->execute([$idGrupo, $idAlumno, $fecha, $anio, $semana, $hora, $idUsuario]);
    }

    $g = $pdo->prepare('SELECT clave, aula FROM grupos WHERE id_grupo = ? LIMIT 1');
    $g->execute([$idGrupo]);
    $gr = $g->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'ok' => true,
        'message' => 'Asistencia registrada por recepción',
        'duplicado' => false,
        'id_alumno' => $idAlumno,
        'id_grupo' => $idGrupo,
        'nombre' => $al['nombre_completo'] ?? '',
        'numero_control' => $al['numero_control'] ?? '',
        'grupo' => $gr['clave'] ?? '',
        'aula' => $gr['aula'] ?? '',
    ];
}

function asistencia_estados_contacto_falta(): array
{
    return [
        'pendiente' => 'Pendiente de contacto',
        'contactado' => 'Contactado',
        'no_contesto' => 'No contestó',
        'asesoria_agendada' => 'Asesoría agendada',
        'baja_clases' => 'Ya no continuará',
        'posponer' => 'Posponer llamada',
        'duelo' => 'Situación delicada (duelo, etc.)',
    ];
}

/** @return list<array{id_alumno:int, nombre:string, numero_control:string, matricula:string}> */
function asistencia_buscar_alumnos(PDO $pdo, string $q, int $idPlantel, int $limit = 15): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $limit = max(1, min(30, $limit));
    $like = '%' . $q . '%';
    $idNum = ctype_digit($q) ? (int) $q : 0;

    $sql = "SELECT a.id_alumno, a.nombres, a.nombre, a.apellido_paterno, a.apellido, a.apellido_materno,
                   a.numero_control, a.matricula, a.codigo_huella
            FROM alumnos a
            WHERE a.id_plantel = ? AND a.estado = 'activo'
              AND (
                a.numero_control = ? OR a.matricula = ? OR a.codigo_huella = ? OR a.id_alumno = ?
                OR a.numero_control LIKE ? OR a.matricula LIKE ?
                OR a.nombres LIKE ? OR a.nombre LIKE ?
                OR a.apellido_paterno LIKE ? OR a.apellido LIKE ? OR a.apellido_materno LIKE ?
                OR CONCAT(COALESCE(a.apellido_paterno, a.apellido, ''), ' ', COALESCE(a.apellido_materno, ''), ' ', COALESCE(a.nombres, a.nombre, '')) LIKE ?
                OR CONCAT(COALESCE(a.nombres, a.nombre, ''), ' ', COALESCE(a.apellido_paterno, a.apellido, '')) LIKE ?
              )
            ORDER BY
              (a.numero_control = ? OR a.numero_control LIKE ?) DESC,
              a.apellido_paterno, a.nombres, a.nombre
            LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $idPlantel, $q, $q, $q, $idNum,
        $like, $like, $like, $like, $like, $like, $like, $like, $like,
        $q, $q . '%',
    ]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = [
            'id_alumno' => (int) $r['id_alumno'],
            'nombre' => alumno_nombre_completo($r),
            'numero_control' => (string) ($r['numero_control'] ?? ''),
            'matricula' => (string) ($r['matricula'] ?? ''),
        ];
    }
    return $out;
}

function asistencia_alumno_coincide_busqueda(array $alumno, string $q): bool
{
    $q = trim($q);
    if ($q === '') {
        return true;
    }
    $qLower = mb_strtolower($q);
    $campos = [
        (string) ($alumno['numero_control'] ?? ''),
        (string) ($alumno['nombre_completo'] ?? ''),
        (string) ($alumno['nombres'] ?? ''),
        (string) ($alumno['nombre'] ?? ''),
        (string) ($alumno['apellido_paterno'] ?? ''),
        (string) ($alumno['apellido'] ?? ''),
        (string) ($alumno['apellido_materno'] ?? ''),
    ];
    foreach ($campos as $c) {
        if ($c !== '' && str_contains(mb_strtolower($c), $qLower)) {
            return true;
        }
    }
    foreach (preg_split('/\s+/u', $qLower, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $tok) {
        $found = false;
        foreach ($campos as $c) {
            if ($c !== '' && str_contains(mb_strtolower($c), $tok)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return false;
        }
    }
    return true;
}

/** @param list<array{id_alumno:int, id_grupo:int}> $pares */
function asistencia_cargar_notas_faltas(PDO $pdo, int $idPlantel, string $fecha, array $pares): array
{
    if (empty($pares)) {
        return [];
    }
    $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d');
    $keys = [];
    $params = [$idPlantel, $fecha];
    foreach ($pares as $p) {
        $ida = (int) ($p['id_alumno'] ?? 0);
        $idg = (int) ($p['id_grupo'] ?? 0);
        if ($ida <= 0 || $idg <= 0) {
            continue;
        }
        $keys[] = "($ida, $idg)";
    }
    if (empty($keys)) {
        return [];
    }
    $sql = 'SELECT id, id_alumno, id_grupo, estado_contacto, observacion, actualizado_en, id_usuario
            FROM asistencia_falta_seguimiento
            WHERE id_plantel = ? AND fecha = ? AND (id_alumno, id_grupo) IN (' . implode(',', $keys) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = (int) $r['id_alumno'] . '-' . (int) $r['id_grupo'];
        $map[$k] = [
            'id' => (int) $r['id'],
            'estado_contacto' => $r['estado_contacto'] ?? 'pendiente',
            'observacion' => $r['observacion'] ?? '',
            'actualizado_en' => $r['actualizado_en'] ?? null,
            'id_usuario' => $r['id_usuario'] ? (int) $r['id_usuario'] : null,
        ];
    }
    return $map;
}

function asistencia_guardar_nota_falta(
    PDO $pdo,
    int $idAlumno,
    int $idGrupo,
    int $idPlantel,
    string $fecha,
    string $estadoContacto,
    string $observacion,
    int $idUsuario
): array {
    if (!asistencia_puede_tomar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    if ($idAlumno <= 0 || $idGrupo <= 0) {
        return ['ok' => false, 'message' => 'Alumno o grupo inválido'];
    }
    $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d');
    $estados = array_keys(asistencia_estados_contacto_falta());
    if (!in_array($estadoContacto, $estados, true)) {
        $estadoContacto = 'pendiente';
    }
    $observacion = trim($observacion);
    if (mb_strlen($observacion) > 2000) {
        $observacion = mb_substr($observacion, 0, 2000);
    }

    $st = $pdo->prepare(
        'SELECT id_grupo FROM grupos WHERE id_grupo = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$idGrupo, $idPlantel]);
    if (!$st->fetchColumn()) {
        return ['ok' => false, 'message' => 'Grupo no encontrado'];
    }
    $al = alumno_obtener($pdo, $idAlumno, $idPlantel);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    $pdo->prepare(
        'INSERT INTO asistencia_falta_seguimiento
            (id_alumno, id_grupo, fecha, id_plantel, estado_contacto, observacion, id_usuario)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            estado_contacto = VALUES(estado_contacto),
            observacion = VALUES(observacion),
            id_usuario = VALUES(id_usuario),
            actualizado_en = CURRENT_TIMESTAMP'
    )->execute([$idAlumno, $idGrupo, $fecha, $idPlantel, $estadoContacto, $observacion ?: null, $idUsuario]);

    return [
        'ok' => true,
        'message' => 'Seguimiento de falta guardado',
        'estado_contacto' => $estadoContacto,
        'observacion' => $observacion,
    ];
}

function asistencia_registrar_recepcion_por_control(
    PDO $pdo,
    string $numeroControl,
    int $idPlantel,
    string $fecha,
    int $idUsuario,
    ?int $idGrupo = null
): array {
    return asistencia_registrar_recepcion_por_busqueda($pdo, $numeroControl, $idPlantel, $fecha, $idUsuario, $idGrupo);
}

function asistencia_registrar_recepcion_por_busqueda(
    PDO $pdo,
    string $q,
    int $idPlantel,
    string $fecha,
    int $idUsuario,
    ?int $idGrupo = null
): array {
    $q = trim($q);
    if ($q === '') {
        return ['ok' => false, 'message' => 'Indique número de control, nombre o apellido'];
    }
    $coincidencias = asistencia_buscar_alumnos($pdo, $q, $idPlantel, 10);
    if (empty($coincidencias)) {
        return ['ok' => false, 'message' => 'No se encontró alumno con «' . $q . '»'];
    }
    if (count($coincidencias) > 1) {
        return [
            'ok' => false,
            'code' => 'multiples',
            'message' => 'Hay varios alumnos con ese criterio; elija uno de la lista',
            'alumnos' => $coincidencias,
        ];
    }
    return asistencia_registrar_recepcion_alumno(
        $pdo,
        (int) $coincidencias[0]['id_alumno'],
        $idPlantel,
        $fecha,
        $idUsuario,
        $idGrupo
    );
}

function asistencia_format_hora(?string $hora): string
{
    if (!$hora) {
        return '—';
    }
    return substr($hora, 0, 5);
}

function asistencia_origen_label(?string $origen): string
{
    return [
        'huella' => 'Huella',
        'movil' => 'Móvil',
        'recepcion' => 'Recepción',
    ][$origen ?? ''] ?? '—';
}

/** Primera clase del profesor en la fecha (para puntualidad HAY). */
function asistencia_hora_esperada_profesor(PDO $pdo, int $idProfesor, int $idPlantel, string $fecha): ?string
{
    $dow = asistencia_dia_semana($fecha);
    $stmt = $pdo->prepare(
        'SELECT MIN(gh.hora_inicio) FROM grupo_horarios gh
         INNER JOIN grupos g ON g.id_grupo = gh.id_grupo
         WHERE g.id_profesor = ? AND g.id_plantel = ? AND gh.dia_semana = ? AND gh.activo = 1'
    );
    $stmt->execute([$idProfesor, $idPlantel, $dow]);
    $h = $stmt->fetchColumn();
    return $h ? (string) $h : null;
}

/**
 * Escala alineada al Excel HAY (puntualidad).
 * @return array{codigo:string,etiqueta:string,minutos:int|null,hora_llegada:?string,hora_esperada:?string}
 */
function asistencia_puntualidad_profesor(PDO $pdo, int $idProfesor, int $idPlantel, string $fecha): array
{
    $horaEsperada = asistencia_hora_esperada_profesor($pdo, $idProfesor, $idPlantel, $fecha);
    $stmt = $pdo->prepare(
        'SELECT hora_llegada, hora_salida, origen FROM asistencia_personal
         WHERE id_usuario = ? AND id_plantel = ? AND fecha = ? LIMIT 1'
    );
    $stmt->execute([$idProfesor, $idPlantel, $fecha]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['hora_llegada'])) {
        return [
            'codigo' => 'sin_registro',
            'etiqueta' => 'Sin registro de entrada',
            'minutos' => null,
            'hora_llegada' => null,
            'hora_esperada' => $horaEsperada,
        ];
    }

    $horaLlegada = $row['hora_llegada'];
    if (!$horaEsperada) {
        return [
            'codigo' => 'sin_horario',
            'etiqueta' => 'Entrada ' . asistencia_format_hora($horaLlegada) . ' (sin horario de grupo)',
            'minutos' => null,
            'hora_llegada' => $horaLlegada,
            'hora_esperada' => null,
        ];
    }

    $diffMin = (int) round((strtotime($fecha . ' ' . $horaLlegada) - strtotime($fecha . ' ' . $horaEsperada)) / 60);

    if ($diffMin <= -10) {
        $codigo = '10_min_antes';
        $etiqueta = '10 min antes o más';
    } elseif ($diffMin <= 0) {
        $codigo = 'a_la_hora';
        $etiqueta = 'A la hora';
    } elseif ($diffMin <= 5) {
        $codigo = '5_min_tarde';
        $etiqueta = '5 min tarde';
    } else {
        $codigo = 'mas_5_tarde';
        $etiqueta = 'Más de 5 min tarde';
    }

    return [
        'codigo' => $codigo,
        'etiqueta' => $etiqueta,
        'minutos' => $diffMin,
        'hora_llegada' => $horaLlegada,
        'hora_esperada' => $horaEsperada,
        'hora_salida' => $row['hora_salida'] ?? null,
        'origen' => $row['origen'] ?? null,
    ];
}

/** @return list<array<string,mixed>> */
function asistencia_lista_puntualidad_dia(PDO $pdo, int $idPlantel, string $fecha): array
{
    $stmt = $pdo->prepare(
        "SELECT DISTINCT u.id_usuario, u.nombre, u.apellido, u.codigo_huella, u.rol
         FROM usuarios u
         WHERE u.rol NOT IN ('alumno', 'asesor')
           AND (u.id_plantel = ? OR u.id_plantel IS NULL)
           AND (
             u.rol IN ('admin', 'gerente', 'supervisor')
             OR EXISTS (
               SELECT 1 FROM grupos g
               WHERE g.id_plantel = ? AND g.id_profesor = u.id_usuario
             )
           )
         ORDER BY u.rol, u.nombre, u.apellido"
    );
    $stmt->execute([$idPlantel, $idPlantel]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $id = (int) $u['id_usuario'];
        $punt = asistencia_puntualidad_profesor($pdo, $id, $idPlantel, $fecha);
        $out[] = array_merge($u, $punt);
    }
    return $out;
}

function asistencia_huella_api_key_valida(): bool
{
    if (!defined('HAY_HUELLA_API_KEY') || HAY_HUELLA_API_KEY === '') {
        return true;
    }
    $key = $_POST['api_key'] ?? $_GET['api_key'] ?? $_SERVER['HTTP_X_HAY_HUELLA_KEY'] ?? '';
    return hash_equals((string) HAY_HUELLA_API_KEY, (string) $key);
}

function huella_normalizar_codigo(string $codigo): string
{
    return preg_replace('/\s+/', '', trim($codigo));
}

function huella_puede_editar_alumno(): bool
{
    if (function_exists('usuario_puede_gestionar_alumnos') && usuario_puede_gestionar_alumnos()) {
        return true;
    }
    return function_exists('asistencia_puede_tomar') && asistencia_puede_tomar();
}

function huella_puede_editar_usuario(): bool
{
    if (!function_exists('rbac_cap')) {
        return in_array($_SESSION['rol'] ?? '', ['admin', 'gerente', 'supervisor'], true);
    }
    return rbac_cap('asistencia_config_huella') || rbac_cap('admin_usuarios');
}

/**
 * @return string|null Mensaje de error si el código ya está en uso
 */
function huella_validar_codigo_unico(
    PDO $pdo,
    string $codigo,
    int $idPlantel,
    ?int $excluirAlumno = null,
    ?int $excluirUsuario = null
): ?string {
    $codigo = huella_normalizar_codigo($codigo);
    if ($codigo === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id_alumno, nombres, apellido_paterno, nombre, apellido FROM alumnos
         WHERE id_plantel = ? AND codigo_huella = ? AND estado = \'activo\''
        . ($excluirAlumno > 0 ? ' AND id_alumno <> ?' : '') . ' LIMIT 1'
    );
    $params = [$idPlantel, $codigo];
    if ($excluirAlumno > 0) {
        $params[] = $excluirAlumno;
    }
    $stmt->execute($params);
    $al = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($al) {
        $nom = alumno_nombre_completo($al);
        return 'El PIN ya está asignado al alumno: ' . $nom;
    }

    $stmt = $pdo->prepare(
        'SELECT id_usuario, nombre, apellido FROM usuarios WHERE codigo_huella = ?'
        . ($excluirUsuario > 0 ? ' AND id_usuario <> ?' : '')
        . ' AND (id_plantel = ? OR id_plantel IS NULL) LIMIT 1'
    );
    $params = [$codigo];
    if ($excluirUsuario > 0) {
        $params[] = $excluirUsuario;
    }
    $params[] = $idPlantel;
    $stmt->execute($params);
    $us = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($us) {
        return 'El PIN ya está asignado al usuario: ' . trim($us['nombre'] . ' ' . $us['apellido']);
    }

    return null;
}

function huella_asignar_alumno(PDO $pdo, int $idAlumno, ?string $codigo, int $idPlantel): array
{
    asistencia_ensure_schema($pdo);
    $chk = $pdo->prepare('SELECT id_alumno FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1');
    $chk->execute([$idAlumno, $idPlantel]);
    if (!$chk->fetchColumn()) {
        return ['ok' => false, 'message' => 'Alumno no encontrado en este plantel'];
    }

    $codigo = huella_normalizar_codigo((string) $codigo);
    if ($codigo !== '') {
        $err = huella_validar_codigo_unico($pdo, $codigo, $idPlantel, $idAlumno, null);
        if ($err) {
            return ['ok' => false, 'message' => $err];
        }
        if (!preg_match('/^[0-9A-Za-z\-]{1,40}$/', $codigo)) {
            return ['ok' => false, 'message' => 'PIN inválido (use números o letras, máx. 40 caracteres)'];
        }
    }

    $pdo->prepare('UPDATE alumnos SET codigo_huella = ? WHERE id_alumno = ?')
        ->execute([$codigo !== '' ? $codigo : null, $idAlumno]);
    asistencia_sync_codigo_huella($pdo, 'alumno', $idAlumno, $codigo !== '' ? $codigo : null, $idPlantel);

    return [
        'ok' => true,
        'message' => $codigo !== '' ? 'PIN de huella guardado para el alumno' : 'PIN eliminado',
        'codigo_huella' => $codigo,
    ];
}

function huella_asignar_usuario(PDO $pdo, int $idUsuario, ?string $codigo, ?int $idPlantel = null): array
{
    asistencia_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT id_usuario, id_plantel FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $stmt->execute([$idUsuario]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return ['ok' => false, 'message' => 'Usuario no encontrado'];
    }

    $idPlantel = $idPlantel ?? (int) ($u['id_plantel'] ?? plantel_id_activo());
    $codigo = huella_normalizar_codigo((string) $codigo);

    if ($codigo !== '') {
        $err = huella_validar_codigo_unico($pdo, $codigo, $idPlantel, null, $idUsuario);
        if ($err) {
            return ['ok' => false, 'message' => $err];
        }
        if (!preg_match('/^[0-9A-Za-z\-]{1,40}$/', $codigo)) {
            return ['ok' => false, 'message' => 'PIN inválido (use números o letras, máx. 40 caracteres)'];
        }
    }

    $pdo->prepare('UPDATE usuarios SET codigo_huella = ? WHERE id_usuario = ?')
        ->execute([$codigo !== '' ? $codigo : null, $idUsuario]);
    asistencia_sync_codigo_huella($pdo, 'usuario', $idUsuario, $codigo !== '' ? $codigo : null, $idPlantel);

    return [
        'ok' => true,
        'message' => $codigo !== '' ? 'PIN de huella guardado' : 'PIN eliminado',
        'codigo_huella' => $codigo,
    ];
}

function asistencia_puede_checada(): bool
{
    if (function_exists('rbac_cap')) {
        return rbac_cap('asistencia_checada');
    }
    $rol = rbac_rol_efectivo();
    return in_array($rol, ['admin', 'gerente', 'supervisor', 'profesor'], true);
}

function asistencia_puede_eliminar_registro(): bool
{
    if (function_exists('rbac_cap')) {
        return rbac_cap('asistencia_eliminar_registro');
    }
    $rol = rbac_rol_efectivo();
    return in_array($rol, ['admin', 'gerente', 'supervisor'], true);
}

/**
 * @return array<string, mixed>
 */
function asistencia_enriquecer_checada(PDO $pdo, array $res, int $idPlantel): array
{
    $payload = [
        'ok' => (bool) ($res['ok'] ?? false),
        'message' => $res['message'] ?? '',
        'duplicado' => (bool) ($res['duplicado'] ?? false),
        'tipo' => $res['tipo'] ?? null,
        'nombre' => $res['nombre'] ?? '',
        'fecha_hora' => date('Y-m-d H:i:s'),
    ];

    if (!empty($res['id_evento'])) {
        $payload['id_evento'] = (int) $res['id_evento'];
    }

    if (!empty($res['id_alumno'])) {
        $idAlumno = (int) $res['id_alumno'];
        $al = alumno_obtener($pdo, $idAlumno, $idPlantel);
        if (!$al) {
            return array_merge($payload, ['ok' => false, 'message' => 'Alumno no encontrado']);
        }

        $foto = null;
        if (!empty($al['foto'])) {
            $foto = alumno_foto_public_url($al['foto']);
        }

        $grupoClave = '';
        $idGrupo = (int) ($res['id_grupo'] ?? 0);
        if ($idGrupo > 0) {
            $g = $pdo->prepare('SELECT clave FROM grupos WHERE id_grupo = ? LIMIT 1');
            $g->execute([$idGrupo]);
            $grupoClave = (string) ($g->fetchColumn() ?: '');
        }

        $ec = pago_estado_cuenta($pdo, $idAlumno);
        $adeudo = 0.0;
        $lineasAdeudo = [];
        if (!empty($ec['ok'])) {
            $adeudo = (float) ($ec['resumen']['adeudo_colegiatura'] ?? 0);
            $lineasAdeudo = array_slice($ec['lineas_adeudo'] ?? [], 0, 5);
        }

        $stmt = $pdo->prepare(
            'SELECT id_asistencia, hora_llegada FROM asistencias
             WHERE id_alumno = ? AND fecha = CURDATE() AND presente = 1
             ORDER BY id_asistencia DESC LIMIT 1'
        );
        $stmt->execute([$idAlumno]);
        $asist = $stmt->fetch(PDO::FETCH_ASSOC);

        $payload['tipo'] = 'alumno';
        $payload['persona'] = [
            'id_alumno' => $idAlumno,
            'nombre' => $al['nombre_completo'] ?? trim(($al['nombres'] ?? '') . ' ' . ($al['apellido_paterno'] ?? '')),
            'numero_control' => $al['numero_control'] ?? '',
            'especialidad' => $al['especialidad_nombre'] ?? '',
            'grupo' => $grupoClave,
            'estado' => $al['estado'] ?? '',
            'foto' => $foto,
            'iniciales' => mb_strtoupper(mb_substr($al['nombres'] ?? 'A', 0, 1) . mb_substr($al['apellido_paterno'] ?? 'L', 0, 1)),
        ];
        $payload['adeudo'] = [
            'total' => $adeudo,
            'tiene_adeudo' => $adeudo > 0.009,
            'total_fmt' => catalog_format_mxn($adeudo),
            'lineas' => array_map(static function ($l) {
                return [
                    'detalle' => $l['detalle'] ?? ($l['especialidad'] ?? ''),
                    'saldo' => catalog_format_mxn((float) ($l['saldo'] ?? 0)),
                ];
            }, $lineasAdeudo),
        ];
        $payload['asistencia'] = [
            'id_asistencia' => $asist ? (int) $asist['id_asistencia'] : null,
            'hora' => $asist['hora_llegada'] ?? null,
            'registrada' => (bool) $asist,
        ];
        $payload['inscripciones_pago'] = [];
        foreach (pago_inscripciones_alumno($pdo, $idAlumno) as $ins) {
            $payload['inscripciones_pago'][] = [
                'id_especialidad' => (int) ($ins['id_especialidad'] ?? 0),
                'id_alumno_especialidad' => (int) ($ins['id_alumno_especialidad'] ?? 0),
                'nombre' => $ins['especialidad_nombre'] ?? '',
            ];
        }
        return $payload;
    }

    if (!empty($res['id_usuario'])) {
        $idUser = (int) $res['id_usuario'];
        $stmt = $pdo->prepare(
            'SELECT id_usuario, nombre, apellido, rol, avatar, codigo_huella FROM usuarios WHERE id_usuario = ? LIMIT 1'
        );
        $stmt->execute([$idUser]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return array_merge($payload, ['ok' => false, 'message' => 'Usuario no encontrado']);
        }

        $ap = $pdo->prepare(
            'SELECT id, hora_llegada, hora_salida FROM asistencia_personal
             WHERE id_usuario = ? AND fecha = CURDATE() LIMIT 1'
        );
        $ap->execute([$idUser]);
        $row = $ap->fetch(PDO::FETCH_ASSOC);

        $avatar = user_avatar_src($u['avatar'] ?? null);
        if ($avatar && !preg_match('#^https?://#i', $avatar)) {
            $avatar = hay_asset_url($avatar);
        }

        $payload['tipo'] = 'personal';
        $payload['persona'] = [
            'id_usuario' => $idUser,
            'nombre' => trim($u['nombre'] . ' ' . $u['apellido']),
            'rol' => rbac_etiqueta_rol($u['rol'] ?? ''),
            'foto' => $avatar,
            'iniciales' => mb_strtoupper(mb_substr($u['nombre'] ?? 'U', 0, 1) . mb_substr($u['apellido'] ?? 'S', 0, 1)),
        ];
        $payload['asistencia'] = [
            'id_registro' => $row ? (int) $row['id'] : null,
            'hora_entrada' => $row['hora_llegada'] ?? null,
            'hora_salida' => $row['hora_salida'] ?? null,
            'tipo_checada' => $res['tipo'] ?? 'entrada',
        ];
        return $payload;
    }

    $payload['tipo'] = 'desconocido';
    return $payload;
}

/**
 * @return array<string, mixed>
 */
function asistencia_procesar_checada_web(
    PDO $pdo,
    string $codigo,
    int $idPlantel,
    ?int $idOperador = null,
    ?array $identHint = null
): array {
    $res = asistencia_procesar_codigo_huella($pdo, $codigo, $idPlantel, null, 'lector_fijo', $idOperador, $identHint);
    if (!empty($res['tipo']) && $res['tipo'] === 'salida') {
        $res['id_usuario'] = $res['id_usuario'] ?? null;
    }
    return asistencia_enriquecer_checada($pdo, $res, $idPlantel);
}

/** @return list<array<string, mixed>> */
function asistencia_poll_eventos_checada(PDO $pdo, int $idPlantel, int $sinceId): array
{
    $stmt = $pdo->prepare(
        'SELECT id_evento, codigo_huella, tipo, id_referencia, procesado, mensaje, fecha_hora
         FROM huella_eventos
         WHERE id_plantel = ? AND id_evento > ?
         ORDER BY id_evento ASC LIMIT 15'
    );
    $stmt->execute([$idPlantel, $sinceId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];

    foreach ($rows as $row) {
        $base = [
            'ok' => (int) $row['procesado'] === 1,
            'message' => $row['mensaje'] ?? '',
            'id_evento' => (int) $row['id_evento'],
            'fecha_hora' => $row['fecha_hora'],
        ];
        if ((int) $row['procesado'] !== 1 || empty($row['id_referencia'])) {
            $out[] = asistencia_enriquecer_checada($pdo, array_merge($base, [
                'ok' => false,
                'message' => $row['mensaje'] ?: 'Código no reconocido',
            ]), $idPlantel);
            continue;
        }
        if ($row['tipo'] === 'alumno') {
            $idAlumno = (int) $row['id_referencia'];
            $idGrupo = asistencia_resolver_grupo_activo($pdo, $idAlumno, $idPlantel, $row['fecha_hora']) ?? 0;
            $ref = asistencia_resolver_codigo_huella($pdo, $row['codigo_huella'], $idPlantel);
            $out[] = asistencia_enriquecer_checada($pdo, array_merge($base, [
                'id_alumno' => $idAlumno,
                'id_grupo' => $idGrupo,
                'nombre' => $ref['nombre'] ?? '',
            ]), $idPlantel);
            continue;
        }
        if ($row['tipo'] === 'personal') {
            $out[] = asistencia_enriquecer_checada($pdo, array_merge($base, [
                'id_usuario' => (int) $row['id_referencia'],
                'tipo' => str_contains((string) ($row['mensaje'] ?? ''), 'Salida') ? 'salida' : 'entrada',
            ]), $idPlantel);
            continue;
        }
        $out[] = asistencia_enriquecer_checada($pdo, array_merge($base, ['ok' => false]), $idPlantel);
    }

    return $out;
}

/** @return array{alumnos:list<array>, personal:list<array>, faltantes_grupos:list<array>, total:int, vista:string} */
function asistencia_listar_registros_dia(PDO $pdo, int $idPlantel, string $fecha, string $q = '', array $opts = []): array
{
    $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d');
    $vista = in_array($opts['vista'] ?? 'checados', ['checados', 'faltantes', 'todos'], true)
        ? ($opts['vista'] ?? 'checados') : 'checados';
    $tipo = in_array($opts['tipo'] ?? 'ambos', ['alumno', 'personal', 'ambos'], true)
        ? ($opts['tipo'] ?? 'ambos') : 'ambos';
    $horaDesde = trim($opts['hora_desde'] ?? '');
    $horaHasta = trim($opts['hora_hasta'] ?? '');
    $idGrupo = (int) ($opts['id_grupo'] ?? 0);
    $like = '%' . $q . '%';

    $alumnos = [];
    $personal = [];
    $faltantesGrupos = [];

    if ($vista === 'faltantes' || $vista === 'todos') {
        $filtrosSesion = ['solo_con_clase_hoy' => empty($opts['todos_grupos'])];
        if ($idGrupo > 0) {
            $filtrosSesion['id_grupo'] = $idGrupo;
        }
        $sesiones = asistencia_sesiones_del_dia($pdo, $idPlantel, $fecha, $filtrosSesion);
        foreach ($sesiones as $s) {
            $gid = (int) $s['id_grupo'];
            $lista = asistencia_lista_alumnos_grupo($pdo, $gid, $fecha);
            $falt = array_values(array_filter($lista, static fn($a) => (int) ($a['presente'] ?? 0) !== 1));
            if ($q !== '') {
                $falt = array_values(array_filter($falt, static function ($a) use ($q) {
                    return asistencia_alumno_coincide_busqueda($a, $q);
                }));
            }
            if ($vista === 'faltantes' && empty($falt)) {
                continue;
            }
            $paresNota = array_map(static fn($a) => [
                'id_alumno' => (int) $a['id_alumno'],
                'id_grupo' => $gid,
            ], $falt);
            $notasMap = asistencia_cargar_notas_faltas($pdo, $idPlantel, $fecha, $paresNota);
            $faltantesGrupos[] = [
                'id_grupo' => $gid,
                'clave' => $s['clave'] ?? '',
                'aula' => $s['aula'] ?? '',
                'hora_inicio' => $s['hora_inicio'] ?? null,
                'hora_fin' => $s['hora_fin'] ?? null,
                'profesor_nombre' => trim($s['profesor_nombre'] ?? ''),
                'especialidad_nombre' => $s['especialidad_nombre'] ?? '',
                'total_inscritos' => count($lista),
                'total_faltantes' => count($falt),
                'faltantes' => array_map(static function ($a) use ($gid, $notasMap) {
                    $k = (int) $a['id_alumno'] . '-' . $gid;
                    $nota = $notasMap[$k] ?? null;
                    return [
                        'id_alumno' => (int) $a['id_alumno'],
                        'numero_control' => $a['numero_control'] ?? '',
                        'nombre' => $a['nombre_completo'] ?? '',
                        'codigo_huella' => $a['codigo_huella'] ?? '',
                        'nota' => $nota,
                        'estado_contacto' => $nota['estado_contacto'] ?? 'pendiente',
                        'observacion' => $nota['observacion'] ?? '',
                    ];
                }, $falt),
            ];
        }
    }

    if ($vista === 'checados' || $vista === 'todos') {
        if ($tipo === 'alumno' || $tipo === 'ambos') {
            $sqlAl = 'SELECT a.id_asistencia AS id, \'alumno\' AS tipo, a.fecha, a.hora_llegada, a.origen,
                             al.id_alumno, al.numero_control,
                             CONCAT(COALESCE(al.apellido_paterno,al.apellido,\'\'), \' \', COALESCE(al.nombres,al.nombre,\'\')) AS nombre,
                             g.clave AS grupo_clave, g.id_grupo
                      FROM asistencias a
                      INNER JOIN alumnos al ON al.id_alumno = a.id_alumno
                      INNER JOIN grupos g ON g.id_grupo = a.id_grupo
                      WHERE g.id_plantel = ? AND a.fecha = ? AND a.presente = 1';
            $paramsAl = [$idPlantel, $fecha];
            if ($idGrupo > 0) {
                $sqlAl .= ' AND g.id_grupo = ?';
                $paramsAl[] = $idGrupo;
            }
            if ($q !== '') {
                $sqlAl .= ' AND (al.numero_control LIKE ? OR al.nombres LIKE ? OR al.apellido_paterno LIKE ? OR al.apellido LIKE ?)';
                $paramsAl = array_merge($paramsAl, [$like, $like, $like, $like]);
            }
            if ($horaDesde !== '') {
                $sqlAl .= ' AND a.hora_llegada >= ?';
                $paramsAl[] = $horaDesde;
            }
            if ($horaHasta !== '') {
                $sqlAl .= ' AND a.hora_llegada <= ?';
                $paramsAl[] = $horaHasta;
            }
            $sqlAl .= ' ORDER BY a.hora_llegada DESC, a.id_asistencia DESC';
            $st = $pdo->prepare($sqlAl);
            $st->execute($paramsAl);
            $alumnos = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($tipo === 'personal' || $tipo === 'ambos') {
            $sqlPer = 'SELECT ap.id, \'personal\' AS tipo, ap.fecha, ap.hora_llegada, ap.hora_salida, ap.origen,
                              u.id_usuario, u.rol,
                              CONCAT(u.nombre, \' \', u.apellido) AS nombre
                       FROM asistencia_personal ap
                       INNER JOIN usuarios u ON u.id_usuario = ap.id_usuario
                       WHERE ap.id_plantel = ? AND ap.fecha = ?';
            $paramsPer = [$idPlantel, $fecha];
            if ($q !== '') {
                $sqlPer .= ' AND (u.nombre LIKE ? OR u.apellido LIKE ?)';
                $paramsPer = array_merge($paramsPer, [$like, $like]);
            }
            if ($horaDesde !== '') {
                $sqlPer .= ' AND ap.hora_llegada >= ?';
                $paramsPer[] = $horaDesde;
            }
            if ($horaHasta !== '') {
                $sqlPer .= ' AND ap.hora_llegada <= ?';
                $paramsPer[] = $horaHasta;
            }
            $sqlPer .= ' ORDER BY ap.hora_llegada DESC, ap.id DESC';
            $st2 = $pdo->prepare($sqlPer);
            $st2->execute($paramsPer);
            $personal = $st2->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    return [
        'alumnos' => $alumnos,
        'personal' => $personal,
        'faltantes_grupos' => $faltantesGrupos,
        'estados_contacto' => asistencia_estados_contacto_falta(),
        'total' => count($alumnos) + count($personal) + array_sum(array_map(static fn($g) => count($g['faltantes']), $faltantesGrupos)),
        'vista' => $vista,
    ];
}

function asistencia_eliminar_registro(PDO $pdo, string $tipo, int $id, int $idPlantel, int $idUsuario): array
{
    if (!asistencia_puede_eliminar_registro()) {
        return ['ok' => false, 'message' => 'No autorizado para eliminar registros'];
    }

    if ($tipo === 'alumno') {
        $st = $pdo->prepare(
            'SELECT a.id_asistencia FROM asistencias a
             INNER JOIN grupos g ON g.id_grupo = a.id_grupo
             WHERE a.id_asistencia = ? AND g.id_plantel = ? LIMIT 1'
        );
        $st->execute([$id, $idPlantel]);
        if (!$st->fetchColumn()) {
            return ['ok' => false, 'message' => 'Registro no encontrado'];
        }
        $pdo->prepare('DELETE FROM asistencias WHERE id_asistencia = ?')->execute([$id]);
        return ['ok' => true, 'message' => 'Asistencia de alumno eliminada'];
    }

    if ($tipo === 'personal') {
        $st = $pdo->prepare('SELECT id FROM asistencia_personal WHERE id = ? AND id_plantel = ? LIMIT 1');
        $st->execute([$id, $idPlantel]);
        if (!$st->fetchColumn()) {
            return ['ok' => false, 'message' => 'Registro no encontrado'];
        }
        $pdo->prepare('DELETE FROM asistencia_personal WHERE id = ?')->execute([$id]);
        return ['ok' => true, 'message' => 'Asistencia de personal eliminada'];
    }

    return ['ok' => false, 'message' => 'Tipo inválido'];
}
