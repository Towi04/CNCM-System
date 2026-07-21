<?php

/**
 * Colegiaturas: adeudo, abonos, pronto pago (día 6), productos aparte.
 */

define('PAGO_DIA_PRONTO', 6);
define('PAGO_INSCRIPCION_VIGENCIA_MESES', 8);
define('PAGO_INSCRIPCION_VIGENCIA_PREP_ABIERTA_MESES', 6);

function pago_es_prep_abierta(array $espMeta): bool
{
    return ($espMeta['modalidad'] ?? '') === 'prep_abierta';
}

/** Meses que se respeta la inscripción pagada en baja temporal. */
function pago_inscripcion_vigencia_meses(array $espMeta): int
{
    return pago_es_prep_abierta($espMeta) ? PAGO_INSCRIPCION_VIGENCIA_PREP_ABIERTA_MESES : PAGO_INSCRIPCION_VIGENCIA_MESES;
}

function pago_inscripcion_vigencia_meses_alumno(PDO $pdo, int $idAlumno): int
{
    pago_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT e.modalidad FROM alumno_especialidades ae
         INNER JOIN especialidades e ON e.id_especialidad = ae.id_especialidad
         WHERE ae.id_alumno = ? AND ae.activo = 1'
    );
    $st->execute([$idAlumno]);
    $mods = $st->fetchAll(PDO::FETCH_COLUMN);
    if ($mods === []) {
        $st2 = $pdo->prepare(
            'SELECT e.modalidad FROM alumnos a
             INNER JOIN especialidades e ON e.id_especialidad = a.id_especialidad
             WHERE a.id_alumno = ? LIMIT 1'
        );
        $st2->execute([$idAlumno]);
        $mod = $st2->fetchColumn();
        if ($mod) {
            $mods = [(string) $mod];
        }
    }
    if ($mods === []) {
        return PAGO_INSCRIPCION_VIGENCIA_MESES;
    }
    $soloPrepAbierta = true;
    foreach ($mods as $mod) {
        if ($mod !== 'prep_abierta') {
            $soloPrepAbierta = false;
            break;
        }
    }

    return $soloPrepAbierta ? PAGO_INSCRIPCION_VIGENCIA_PREP_ABIERTA_MESES : PAGO_INSCRIPCION_VIGENCIA_MESES;
}

function pago_sql_filtro_activos(string $alias = 'ap'): string
{
    return " AND ({$alias}.estado = 'activo' OR {$alias}.estado IS NULL)";
}

function pago_ensure_schema(PDO $pdo): void
{
    alumno_ensure_schema($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_especialidades (
            id_alumno_especialidad INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_especialidad INT UNSIGNED NOT NULL,
            forma_pago ENUM(\'mensual\',\'semanal\') NOT NULL DEFAULT \'mensual\',
            fecha_inscripcion DATE NOT NULL,
            costo_inscripcion DECIMAL(12,2) NOT NULL DEFAULT 0,
            costo_mensualidad DECIMAL(12,2) NOT NULL DEFAULT 0,
            costo_pronto_pago DECIMAL(12,2) NOT NULL DEFAULT 0,
            costo_semanal DECIMAL(12,2) NOT NULL DEFAULT 0,
            duracion_meses SMALLINT UNSIGNED NOT NULL DEFAULT 12,
            duracion_semanas SMALLINT UNSIGNED NULL,
            inscripcion_cubierta TINYINT(1) NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_alumno_especialidad),
            UNIQUE KEY uq_alumno_esp (id_alumno, id_especialidad),
            KEY idx_ae_alumno (id_alumno)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    pago_migrate_alumno_pagos_columns($pdo);
    pago_migrate_becas_promos($pdo);
    pago_migrate_alumno_inscripcion_global($pdo);
    pago_migrate_pago_auditoria($pdo);
    pago_seed_promociones($pdo);
    pago_sync_inscripciones_desde_alumnos($pdo);
}

function pago_migrate_pago_auditoria(PDO $pdo): void
{
    plantel_ensure_column($pdo, 'alumno_pagos', 'estado', "ENUM('activo','anulado') NOT NULL DEFAULT 'activo'", 'creado_en');
    plantel_ensure_column($pdo, 'alumno_pagos', 'anulado_en', 'DATETIME NULL', 'estado');
    plantel_ensure_column($pdo, 'alumno_pagos', 'anulado_por', 'INT UNSIGNED NULL', 'anulado_en');
    plantel_ensure_column($pdo, 'alumno_pagos', 'anulado_motivo', 'VARCHAR(500) NULL', 'anulado_por');
    plantel_ensure_column($pdo, 'alumno_pagos', 'id_pago_reemplazo', 'INT UNSIGNED NULL', 'anulado_motivo');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_pago_movimiento (
            id_mov INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_pago INT UNSIGNED NOT NULL,
            id_pago_nuevo INT UNSIGNED NULL,
            id_alumno INT UNSIGNED NOT NULL,
            tipo ENUM(\'anular\',\'editar_monto\',\'editar_concepto\') NOT NULL,
            snapshot_json JSON NULL,
            motivo VARCHAR(500) NOT NULL,
            id_usuario INT UNSIGNED NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_mov),
            KEY idx_apm_pago (id_pago),
            KEY idx_apm_alumno (id_alumno),
            KEY idx_apm_fecha (creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function pago_migrate_becas_promos(PDO $pdo): void
{
    plantel_ensure_column($pdo, 'alumno_pagos', 'id_beca', 'INT UNSIGNED NULL', 'aplico_pronto_pago');
    plantel_ensure_column($pdo, 'alumno_pagos', 'id_promocion', 'INT UNSIGNED NULL', 'id_beca');
    plantel_ensure_column($pdo, 'alumno_pagos', 'monto_descuento', 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'id_promocion');
    plantel_ensure_column($pdo, 'alumno_pagos', 'motivo_descuento', 'VARCHAR(255) NULL', 'monto_descuento');
    plantel_ensure_column($pdo, 'alumno_pagos', 'id_autoriza', 'INT UNSIGNED NULL', 'motivo_descuento');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_becas (
            id_beca INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_alumno_especialidad INT UNSIGNED NULL,
            aplicar_a ENUM(\'inscripcion\',\'colegiatura\',\'ambos\') NOT NULL DEFAULT \'colegiatura\',
            tipo ENUM(\'porcentaje\',\'monto_fijo\') NOT NULL DEFAULT \'porcentaje\',
            valor DECIMAL(12,2) NOT NULL DEFAULT 0,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NULL,
            motivo VARCHAR(255) NOT NULL,
            id_autoriza INT UNSIGNED NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_beca),
            KEY idx_beca_alumno (id_alumno)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS promociones_descuento (
            id_promocion INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clave VARCHAR(40) NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            aplicar_a ENUM(\'inscripcion\',\'colegiatura\',\'ambos\') NOT NULL DEFAULT \'colegiatura\',
            tipo ENUM(\'porcentaje\',\'monto_fijo\') NOT NULL DEFAULT \'porcentaje\',
            valor DECIMAL(12,2) NOT NULL DEFAULT 0,
            requiere_motivo TINYINT(1) NOT NULL DEFAULT 1,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_promocion),
            UNIQUE KEY uq_promo_clave (clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function pago_migrate_alumno_inscripcion_global(PDO $pdo): void
{
    plantel_ensure_column($pdo, 'alumnos', 'inscripcion_global_pagada', 'TINYINT(1) NOT NULL DEFAULT 0', 'id_preregistro');
    plantel_ensure_column($pdo, 'alumnos', 'fecha_inscripcion_global', 'DATE NULL', 'inscripcion_global_pagada');
    plantel_ensure_column($pdo, 'alumnos', 'inscripcion_vigente_hasta', 'DATE NULL', 'fecha_inscripcion_global');
    plantel_ensure_column($pdo, 'alumnos', 'fecha_baja_temporal', 'DATE NULL', 'inscripcion_vigente_hasta');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'cuatrimestre_actual', 'VARCHAR(10) NULL', 'inscripcion_cubierta');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'colegiatura_meses_pausa', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0', 'cuatrimestre_actual');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'colegiatura_meses_extension', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0', 'colegiatura_meses_pausa');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'colegiatura_pausa_desde', 'DATE NULL', 'colegiatura_meses_extension');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'creado_en', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'activo');
}

function pago_seed_promociones(PDO $pdo): void
{
    $n = (int) $pdo->query('SELECT COUNT(*) FROM promociones_descuento')->fetchColumn();
    if ($n > 0) {
        return;
    }
    $ins = $pdo->prepare(
        'INSERT INTO promociones_descuento (clave, nombre, aplicar_a, tipo, valor, requiere_motivo) VALUES (?,?,?,?,?,?)'
    );
    $rows = [
        ['HERMANO', 'Descuento hermanos', 'colegiatura', 'porcentaje', 10, 1],
        ['PROMO_VERANO', 'Promoción verano', 'inscripcion', 'monto_fijo', 100, 1],
        ['CONVENIO', 'Convenio institucional', 'ambos', 'porcentaje', 15, 1],
    ];
    foreach ($rows as $r) {
        $ins->execute($r);
    }
}

function pago_migrate_alumno_pagos_columns(PDO $pdo): void
{
    plantel_ensure_column($pdo, 'alumno_pagos', 'tipo', "ENUM('inscripcion','mensualidad','semanal','abono','producto','otro') NOT NULL DEFAULT 'abono' AFTER id_especialidad", 'id_especialidad');
    plantel_ensure_column($pdo, 'alumno_pagos', 'id_producto', 'INT UNSIGNED NULL AFTER tipo', 'tipo');
    plantel_ensure_column($pdo, 'alumno_pagos', 'id_alumno_especialidad', 'INT UNSIGNED NULL AFTER id_producto', 'id_producto');
    plantel_ensure_column($pdo, 'alumno_pagos', 'periodo_ref', 'VARCHAR(20) NULL AFTER id_alumno_especialidad', 'id_alumno_especialidad');
    plantel_ensure_column($pdo, 'alumno_pagos', 'aplico_pronto_pago', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER periodo_ref', 'periodo_ref');
    plantel_ensure_column($pdo, 'alumno_pagos', 'id_plantel', 'INT UNSIGNED NULL AFTER id_alumno', 'id_alumno');
    plantel_ensure_column($pdo, 'alumno_pagos', 'cuenta_contable', "CHAR(1) NULL COMMENT 'A=tarjeta/transfer/factura B=efectivo sin factura' AFTER forma_pago", 'forma_pago');
    plantel_ensure_column($pdo, 'alumno_pagos', 'cliente_nombre', "VARCHAR(160) NULL COMMENT 'Comprador si no es alumno' AFTER concepto", 'concepto');
    plantel_ensure_column($pdo, 'alumno_pagos', 'cubrio', 'TEXT NULL', 'cliente_nombre');
    plantel_ensure_column($pdo, 'alumno_pagos', 'id_solicitud_cert', 'INT UNSIGNED NULL COMMENT \'Certificación cobrada en PV\'', 'id_producto');
    plantel_ensure_column($pdo, 'alumno_pagos', 'fecha_pago', 'DATE NULL AFTER creado_en', 'creado_en');
    plantel_ensure_column($pdo, 'alumno_pagos', 'estado', "ENUM('activo','anulado') NOT NULL DEFAULT 'activo'", 'creado_en');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS corte_caja (
            id_corte INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_usuario INT UNSIGNED NOT NULL,
            fecha DATE NOT NULL,
            cuenta CHAR(1) NOT NULL DEFAULT \'B\',
            ingreso_sistema DECIMAL(12,2) NOT NULL DEFAULT 0,
            retiros DECIMAL(12,2) NOT NULL DEFAULT 0,
            comprobantes DECIMAL(12,2) NOT NULL DEFAULT 0,
            efectivo_contado DECIMAL(12,2) NULL,
            notas TEXT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_corte),
            KEY idx_corte_plantel_fecha (id_plantel, fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    pago_backfill_cuenta_contable($pdo);
    pago_reparar_abonos_periodo_mal_asignado($pdo);
}

/** Abonos con periodo_ref quedaban ligados a un solo mes; limpiar para FIFO. */
function pago_reparar_abonos_periodo_mal_asignado(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $n = (int) $pdo->query(
        "SELECT COUNT(*) FROM alumno_pagos WHERE tipo = 'abono' AND periodo_ref IS NOT NULL AND periodo_ref <> ''"
    )->fetchColumn();
    if ($n === 0) {
        return;
    }

    $pdo->exec(
        "UPDATE alumno_pagos
         SET periodo_ref = NULL
         WHERE tipo = 'abono' AND periodo_ref IS NOT NULL AND periodo_ref <> ''"
    );
}

function pago_backfill_cuenta_contable(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $n = (int) $pdo->query(
        "SELECT COUNT(*) FROM alumno_pagos WHERE cuenta_contable IS NULL OR cuenta_contable = ''"
    )->fetchColumn();
    if ($n === 0) {
        return;
    }

    $rows = $pdo->query(
        "SELECT p.id_pago, p.id_alumno, p.forma_pago, p.tipo
         FROM alumno_pagos p
         WHERE p.cuenta_contable IS NULL OR p.cuenta_contable = ''"
    )->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare('UPDATE alumno_pagos SET cuenta_contable = ? WHERE id_pago = ?');
    foreach ($rows as $r) {
        $req = pago_alumno_requiere_factura($pdo, (int) $r['id_alumno']);
        $cuenta = pago_resolver_cuenta_contable(
            (string) ($r['forma_pago'] ?? 'Efectivo'),
            $req,
            (string) ($r['tipo'] ?? 'abono')
        );
        $st->execute([$cuenta, (int) $r['id_pago']]);
    }
}

function pago_sync_inscripciones_desde_alumnos(PDO $pdo): void
{
    $rows = $pdo->query(
        "SELECT a.id_alumno, a.id_especialidad, a.forma_pago, a.fecha_alta, a.id_plantel
         FROM alumnos a
         WHERE a.id_especialidad IS NOT NULL AND a.estado = 'activo'"
    )->fetchAll(PDO::FETCH_ASSOC);

    $ins = $pdo->prepare(
        'INSERT IGNORE INTO alumno_especialidades (
            id_alumno, id_especialidad, forma_pago, fecha_inscripcion,
            costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal,
            duracion_meses, duracion_semanas
        ) VALUES (?,?,?,?,?,?,?,?,?,?)'
    );

    foreach ($rows as $a) {
        $esp = $pdo->prepare('SELECT * FROM especialidades WHERE id_especialidad = ? LIMIT 1');
        $esp->execute([(int) $a['id_especialidad']]);
        $e = $esp->fetch(PDO::FETCH_ASSOC);
        if (!$e) {
            continue;
        }
        $ins->execute([
            (int) $a['id_alumno'],
            (int) $a['id_especialidad'],
            $a['forma_pago'] ?? 'mensual',
            $a['fecha_alta'] ?? date('Y-m-d'),
            $e['costo_inscripcion'],
            $e['costo_mensualidad'],
            $e['costo_pronto_pago'],
            $e['costo_semanal'],
            $e['duracion_meses'],
            $e['duracion_semanas'],
        ]);
    }

    $pdo->exec(
        "UPDATE alumno_pagos SET tipo = 'abono' WHERE tipo IS NULL OR tipo = ''"
    );
}

/** @return array<int, array<string, mixed>> */
function pago_inscripciones_alumno(PDO $pdo, int $idAlumno): array
{
    try {
        if (function_exists('pago_ensure_schema')) {
            // Crear tabla si falta sin bloquear
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS alumno_especialidades (
                    id_alumno_especialidad INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    id_alumno INT UNSIGNED NOT NULL,
                    id_especialidad INT UNSIGNED NOT NULL,
                    forma_pago ENUM(\'mensual\',\'semanal\') NOT NULL DEFAULT \'mensual\',
                    fecha_inscripcion DATE NOT NULL,
                    costo_inscripcion DECIMAL(12,2) NOT NULL DEFAULT 0,
                    costo_mensualidad DECIMAL(12,2) NOT NULL DEFAULT 0,
                    costo_pronto_pago DECIMAL(12,2) NOT NULL DEFAULT 0,
                    costo_semanal DECIMAL(12,2) NOT NULL DEFAULT 0,
                    duracion_meses SMALLINT UNSIGNED NOT NULL DEFAULT 12,
                    duracion_semanas SMALLINT UNSIGNED NULL,
                    inscripcion_cubierta TINYINT(1) NOT NULL DEFAULT 0,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id_alumno_especialidad),
                    UNIQUE KEY uq_alumno_esp (id_alumno, id_especialidad)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        $stmt = $pdo->prepare(
            'SELECT ae.*, e.nombre AS especialidad_nombre, e.clave AS especialidad_clave
             FROM alumno_especialidades ae
             INNER JOIN especialidades e ON e.id_especialidad = ae.id_especialidad
             WHERE ae.id_alumno = ? AND ae.activo = 1
             ORDER BY ae.fecha_inscripcion ASC'
        );
        $stmt->execute([$idAlumno]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('pago_inscripciones_alumno: ' . $e->getMessage());
        return [];
    }
}

function pago_es_colegiatura(string $tipo): bool
{
    return in_array($tipo, ['inscripcion', 'mensualidad', 'semanal', 'abono'], true);
}

/** @return array<int, array<string, mixed>> */
function pago_listar_alumno(PDO $pdo, int $idAlumno): array
{
    $stmt = $pdo->prepare(
        'SELECT ap.*, e.nombre AS especialidad_nombre,
                CONCAT(u.nombre, " ", u.apellido) AS recibio_nombre,
                p.nombre AS producto_nombre
         FROM alumno_pagos ap
         LEFT JOIN especialidades e ON e.id_especialidad = ap.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = ap.id_usuario
         LEFT JOIN productos p ON p.id_producto = ap.id_producto
         WHERE ap.id_alumno = ?' . pago_sql_filtro_activos('ap') . '
         ORDER BY COALESCE(ap.fecha_pago, DATE(ap.creado_en)) ASC, ap.creado_en ASC'
    );
    $stmt->execute([$idAlumno]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<int, array<string, mixed>> */
function pago_listar_alumno_todos(PDO $pdo, int $idAlumno): array
{
    $stmt = $pdo->prepare(
        'SELECT ap.*, e.nombre AS especialidad_nombre,
                CONCAT(u.nombre, " ", u.apellido) AS recibio_nombre,
                CONCAT(ua.nombre, " ", ua.apellido) AS anulo_nombre,
                p.nombre AS producto_nombre
         FROM alumno_pagos ap
         LEFT JOIN especialidades e ON e.id_especialidad = ap.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = ap.id_usuario
         LEFT JOIN usuarios ua ON ua.id_usuario = ap.anulado_por
         LEFT JOIN productos p ON p.id_producto = ap.id_producto
         WHERE ap.id_alumno = ?
         ORDER BY COALESCE(ap.fecha_pago, DATE(ap.creado_en)) ASC, ap.creado_en ASC'
    );
    $stmt->execute([$idAlumno]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Pronto pago según el mes que se paga vs la fecha del pago (adelanto = sí; mes vencido = no). */
function pago_aplica_pronto_pago(string $periodoYm, string $fechaReferencia): bool
{
    $mesRef = date('Y-m', strtotime($fechaReferencia));
    $diaRef = (int) date('d', strtotime($fechaReferencia));
    if ($periodoYm > $mesRef) {
        return true;
    }
    if ($periodoYm < $mesRef) {
        return false;
    }

    return $diaRef <= PAGO_DIA_PRONTO;
}

/** Fecha desde la cual corre la colegiatura (inicio del grupo, no la inscripción administrativa). */
function pago_fecha_inicio_colegiaturas(PDO $pdo, int $idAlumno, int $idEspecialidad, array $ins): string
{
    $st = $pdo->prepare(
        'SELECT g.fecha_inicio
         FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         WHERE ag.id_alumno = ? AND ag.activo = 1 AND g.id_especialidad = ?
         ORDER BY g.fecha_inicio ASC
         LIMIT 1'
    );
    $st->execute([$idAlumno, $idEspecialidad]);
    $fecha = $st->fetchColumn();

    if (!$fecha) {
        $st2 = $pdo->prepare(
            'SELECT g.fecha_inicio
             FROM alumnos a
             INNER JOIN grupos g ON g.id_grupo = a.id_grupo
             WHERE a.id_alumno = ? AND g.id_especialidad = ?
             LIMIT 1'
        );
        $st2->execute([$idAlumno, $idEspecialidad]);
        $fecha = $st2->fetchColumn();
    }

    if ($fecha && $fecha !== '') {
        return (string) $fecha;
    }

    return (string) ($ins['fecha_inscripcion'] ?? date('Y-m-d'));
}

/**
 * Grupo activo del alumno para cobro de colegiaturas en una especialidad.
 * @return array{id_grupo: int, fecha_inicio: string, codigo_horario: string, id_plantel: int}|null
 */
function pago_grupo_colegiaturas(PDO $pdo, int $idAlumno, int $idEspecialidad): ?array
{
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.fecha_inicio, g.codigo_horario, g.id_plantel
         FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         WHERE ag.id_alumno = ? AND ag.activo = 1 AND g.id_especialidad = ?
         ORDER BY g.fecha_inicio ASC
         LIMIT 1'
    );
    $st->execute([$idAlumno, $idEspecialidad]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return [
            'id_grupo' => (int) $row['id_grupo'],
            'fecha_inicio' => (string) $row['fecha_inicio'],
            'codigo_horario' => (string) ($row['codigo_horario'] ?? 'S'),
            'id_plantel' => (int) ($row['id_plantel'] ?? 0),
        ];
    }

    $st2 = $pdo->prepare(
        'SELECT g.id_grupo, g.fecha_inicio, g.codigo_horario, g.id_plantel
         FROM alumnos a
         INNER JOIN grupos g ON g.id_grupo = a.id_grupo
         WHERE a.id_alumno = ? AND g.id_especialidad = ?
         LIMIT 1'
    );
    $st2->execute([$idAlumno, $idEspecialidad]);
    $row2 = $st2->fetch(PDO::FETCH_ASSOC);
    if (!$row2) {
        return null;
    }

    return [
        'id_grupo' => (int) $row2['id_grupo'],
        'fecha_inicio' => (string) $row2['fecha_inicio'],
        'codigo_horario' => (string) ($row2['codigo_horario'] ?? 'S'),
        'id_plantel' => (int) ($row2['id_plantel'] ?? 0),
    ];
}

/**
 * Factor proporcional del primer mes cuando el grupo inicia a mitad de mes (sesiones lectivas).
 * @return array{factor: float, es_parcial: bool, sesiones: int, sesiones_mes: int, motivo: string}
 */
function pago_factor_mes_parcial(
    PDO $pdo,
    string $fechaInicioColeg,
    string $periodoYm,
    string $codigoHorario = 'S',
    ?int $idPlantel = null,
    ?array $espMeta = null
): array {
    if ($espMeta && pago_es_prep_abierta($espMeta)) {
        return [
            'factor' => 1.0,
            'es_parcial' => false,
            'sesiones' => 0,
            'sesiones_mes' => 0,
            'motivo' => '',
        ];
    }

    $mesInicio = date('Y-m', strtotime($fechaInicioColeg));
    if ($periodoYm !== $mesInicio) {
        return [
            'factor' => 1.0,
            'es_parcial' => false,
            'sesiones' => 0,
            'sesiones_mes' => 0,
            'motivo' => '',
        ];
    }

    $diaInicio = (int) date('d', strtotime($fechaInicioColeg));
    if ($diaInicio <= 1) {
        return [
            'factor' => 1.0,
            'es_parcial' => false,
            'sesiones' => 0,
            'sesiones_mes' => 0,
            'motivo' => '',
        ];
    }

    $inicioMes = new DateTimeImmutable($periodoYm . '-01');
    $finMes = $inicioMes->modify('last day of this month');
    $inicioColeg = new DateTimeImmutable($fechaInicioColeg);
    $horario = strtoupper($codigoHorario ?: 'S');
    $idPlantel = $idPlantel ?? plantel_id_activo();

    $sesionesMes = academico_sesiones_lectivas_desde($pdo, $inicioMes, $horario, $finMes, $idPlantel);
    $sesionesRestantes = academico_sesiones_lectivas_desde($pdo, $inicioColeg, $horario, $finMes, $idPlantel);

    if ($sesionesMes <= 0) {
        $ultimoDia = (int) $finMes->format('d');
        $diasRestantes = max(1, $ultimoDia - $diaInicio + 1);
        $factor = round($diasRestantes / $ultimoDia, 4);

        return [
            'factor' => min(1.0, max(0.01, $factor)),
            'es_parcial' => true,
            'sesiones' => 0,
            'sesiones_mes' => 0,
            'motivo' => 'Proporcional por inicio ' . date('d/m/Y', strtotime($fechaInicioColeg))
                . ' (' . round($factor * 100, 1) . '% del mes)',
        ];
    }

    $factor = round($sesionesRestantes / $sesionesMes, 4);

    return [
        'factor' => min(1.0, max(0.01, $factor)),
        'es_parcial' => $sesionesRestantes < $sesionesMes,
        'sesiones' => $sesionesRestantes,
        'sesiones_mes' => $sesionesMes,
        'motivo' => $sesionesRestantes < $sesionesMes
            ? "Proporcional: {$sesionesRestantes}/{$sesionesMes} sesiones (inicio "
                . date('d/m/Y', strtotime($fechaInicioColeg)) . ')'
            : '',
    ];
}

function pago_aplicar_factor_monto(float $monto, array $factorInfo): float
{
    if (empty($factorInfo['es_parcial'])) {
        return $monto;
    }
    $f = (float) ($factorInfo['factor'] ?? 1.0);

    return round($monto * min(1.0, max(0.01, $f)), 2);
}

/** Semanas ISO desde una fecha de inicio hasta corte (sin duplicar). */
function pago_semanas_en_rango(string $desde, string $hasta): array
{
    $start = strtotime($desde);
    $end = strtotime($hasta);
    if ($start === false || $end === false || $start > $end) {
        return [];
    }
    $periodos = [];
    $seen = [];
    $cursor = $start;
    $n = 0;
    while ($cursor <= $end && $n < 60) {
        $w = date('Y', $cursor) . '-W' . str_pad((string) date('W', $cursor), 2, '0', STR_PAD_LEFT);
        if (!isset($seen[$w])) {
            $periodos[] = $w;
            $seen[$w] = true;
        }
        $cursor = strtotime('+1 week', $cursor);
        $n++;
    }

    return $periodos;
}

/**
 * Al posponer un grupo, mueve colegiaturas del mes/semanas del inicio anterior al nuevo inicio.
 * @return array{ok: bool, remap_count: int}
 */
function pago_remap_colegiaturas_por_pospon_grupo(
    PDO $pdo,
    int $idGrupo,
    string $fechaAnterior,
    string $fechaNueva
): array {
    $g = $pdo->prepare('SELECT id_especialidad FROM grupos WHERE id_grupo = ? LIMIT 1');
    $g->execute([$idGrupo]);
    $idEsp = (int) $g->fetchColumn();
    if ($idEsp <= 0) {
        return ['ok' => true, 'remap_count' => 0];
    }

    $mesAnterior = date('Y-m', strtotime($fechaAnterior));
    $mesNuevo = date('Y-m', strtotime($fechaNueva));
    $remapCount = 0;

    $stAl = $pdo->prepare(
        'SELECT ag.id_alumno FROM alumno_grupos ag WHERE ag.id_grupo = ? AND ag.activo = 1'
    );
    $stAl->execute([$idGrupo]);
    $alumnos = $stAl->fetchAll(PDO::FETCH_COLUMN) ?: [];

    foreach ($alumnos as $idAlumno) {
        $idAlumno = (int) $idAlumno;
        if ($idAlumno <= 0) {
            continue;
        }

        if ($mesAnterior !== $mesNuevo) {
            $up = $pdo->prepare(
                "UPDATE alumno_pagos SET periodo_ref = ?
                 WHERE id_alumno = ? AND id_especialidad = ?
                   AND tipo IN ('mensualidad','abono')
                   AND periodo_ref = ?"
            );
            $up->execute([$mesNuevo, $idAlumno, $idEsp, $mesAnterior]);
            $remapCount += $up->rowCount();
        }

        $finMesAnt = date('Y-m-t', strtotime($fechaAnterior));
        $finMesNue = date('Y-m-t', strtotime($fechaNueva));
        $semanasAnt = pago_semanas_en_rango($fechaAnterior, $finMesAnt);
        $semanasNue = pago_semanas_en_rango($fechaNueva, $finMesNue);
        $mapSem = [];
        $maxMap = min(count($semanasAnt), count($semanasNue));
        for ($i = 0; $i < $maxMap; $i++) {
            $mapSem[$semanasAnt[$i]] = $semanasNue[$i];
        }
        foreach ($mapSem as $oldW => $newW) {
            if ($oldW === $newW) {
                continue;
            }
            $upW = $pdo->prepare(
                "UPDATE alumno_pagos SET periodo_ref = ?
                 WHERE id_alumno = ? AND id_especialidad = ?
                   AND tipo = 'semanal' AND periodo_ref = ?"
            );
            $upW->execute([$newW, $idAlumno, $idEsp, $oldW]);
            $remapCount += $upW->rowCount();
        }
    }

    return ['ok' => true, 'remap_count' => $remapCount];
}

function pago_monto_mes_esperado(
    string $periodoYm,
    string $fechaCorte,
    float $mensual,
    float $pronto,
    array $pagosDelPeriodo
): array {
    $diaCorte = (int) date('d', strtotime($fechaCorte));
    $mesCorte = date('Y-m', strtotime($fechaCorte));

    foreach ($pagosDelPeriodo as $p) {
        $fechaPago = date('Y-m-d', strtotime($p['creado_en']));
        $diaPago = (int) date('d', strtotime($fechaPago));
        if (pago_aplica_pronto_pago($periodoYm, $fechaPago)) {
            $motivo = $periodoYm > date('Y-m', strtotime($fechaPago))
                ? 'Pago anticipado del ' . date('d/m/Y', strtotime($fechaPago))
                : 'Pagó el ' . date('d/m/Y', strtotime($fechaPago)) . ' (día ' . $diaPago . ' ≤ ' . PAGO_DIA_PRONTO . ')';

            return [
                'monto' => $pronto,
                'tarifa' => 'pronto_pago',
                'motivo' => $motivo,
            ];
        }

        return [
            'monto' => $mensual,
            'tarifa' => 'mensualidad',
            'motivo' => $periodoYm < date('Y-m', strtotime($fechaPago))
                ? 'Mes vencido'
                : 'Pagó después del día ' . PAGO_DIA_PRONTO,
        ];
    }

    if ($periodoYm > $mesCorte) {
        return [
            'monto' => $pronto,
            'tarifa' => 'pronto_pago',
            'motivo' => 'Pago anticipado — aplica pronto pago',
        ];
    }

    if ($periodoYm === $mesCorte && $diaCorte <= PAGO_DIA_PRONTO) {
        return [
            'monto' => $pronto,
            'tarifa' => 'pronto_pago',
            'motivo' => 'Aún en periodo de pronto pago (hoy día ' . $diaCorte . ')',
        ];
    }

    return [
        'monto' => $mensual,
        'tarifa' => 'mensualidad',
        'motivo' => $periodoYm < $mesCorte ? 'Mes vencido' : 'Después del día ' . PAGO_DIA_PRONTO,
    ];
}

/** Periodos mensuales desde inscripción hasta fecha corte */
function pago_periodos_mensuales(string $fechaInscripcion, string $fechaCorte, int $duracionMeses): array
{
    $inicio = new DateTime(date('Y-m-01', strtotime($fechaInscripcion)));
    $fin = new DateTime(date('Y-m-01', strtotime($fechaCorte)));
    $periodos = [];
    $cursor = clone $inicio;
    $n = 0;
    while ($cursor <= $fin && $n < max($duracionMeses, 1) + 24) {
        $periodos[] = $cursor->format('Y-m');
        $cursor->modify('+1 month');
        $n++;
        if ($duracionMeses > 0 && $n >= $duracionMeses) {
            break;
        }
    }
    return $periodos;
}

/**
 * Plan mensual sin proporcional (prepa abierta: mes completo inicio y fin).
 * @return list<array{periodo: string, factor: float, es_parcial: bool, motivo_parcial: string}>
 */
function pago_plan_periodos_mensuales_completos(
    string $fechaInicioColeg,
    int $duracionMeses,
    int $mesesPausa = 0,
    int $mesesExtension = 0
): array {
    $mesInicio = date('Y-m', strtotime($fechaInicioColeg));
    $totalMeses = $duracionMeses + $mesesExtension;
    $cursor = new DateTime($mesInicio . '-01');
    $plan = [];
    $mesesSaltados = 0;
    $n = 0;
    while ($n < $totalMeses && $n < 96) {
        if ($mesesSaltados < $mesesPausa) {
            $cursor->modify('+1 month');
            $mesesSaltados++;
            continue;
        }
        $plan[] = [
            'periodo' => $cursor->format('Y-m'),
            'factor' => 1.0,
            'es_parcial' => false,
            'motivo_parcial' => '',
        ];
        $cursor->modify('+1 month');
        $n++;
    }

    return $plan;
}

/**
 * Plan de colegiaturas mensuales: duracion_meses en equivalentes completos,
 * con primer y último mes proporcionales si el grupo inicia a mitad de mes.
 * @return list<array{periodo: string, factor: float, es_parcial: bool, motivo_parcial: string}>
 */
function pago_plan_periodos_mensuales(
    PDO $pdo,
    string $fechaInicioColeg,
    int $duracionMeses,
    string $codigoHorario = 'S',
    ?int $idPlantel = null,
    int $mesesPausa = 0,
    int $mesesExtension = 0,
    ?array $espMeta = null
): array {
    if ($duracionMeses <= 0) {
        return [];
    }
    if ($espMeta && pago_es_prep_abierta($espMeta)) {
        return pago_plan_periodos_mensuales_completos($fechaInicioColeg, $duracionMeses, $mesesPausa, $mesesExtension);
    }
    $mesInicio = date('Y-m', strtotime($fechaInicioColeg));
    $factorInfo = pago_factor_mes_parcial($pdo, $fechaInicioColeg, $mesInicio, $codigoHorario, $idPlantel, $espMeta);
    $factorInicio = (float) ($factorInfo['factor'] ?? 1.0);
    $parcialInicio = !empty($factorInfo['es_parcial']);

    $target = (float) $duracionMeses + (float) $mesesExtension;
    $cargos = [];
    $acum = 0.0;
    $iter = 0;

    while ($acum < $target - 0.001 && $iter < 96) {
        $restante = $target - $acum;
        if ($iter === 0 && $parcialInicio) {
            $factor = $factorInicio;
        } elseif ($parcialInicio && $restante <= $factorInicio + 0.01) {
            $factor = round($restante, 4);
        } elseif ($restante < 1.0) {
            $factor = round($restante, 4);
        } else {
            $factor = 1.0;
        }
        $cargos[] = [
            'factor' => $factor,
            'es_parcial' => $factor < 0.999,
            'motivo_parcial' => ($iter === 0 && $parcialInicio)
                ? (string) ($factorInfo['motivo'] ?? 'Proporcional')
                : ($factor < 0.999 && $parcialInicio ? 'Cierre proporcional del programa' : ''),
        ];
        $acum += $factor;
        $iter++;
    }

    $cursor = new DateTime($mesInicio . '-01');
    $plan = [];
    $idx = 0;
    $mesesSaltados = 0;
    while ($idx < count($cargos) && $mesesSaltados <= $mesesPausa + 48) {
        $ym = $cursor->format('Y-m');
        if ($mesesSaltados < $mesesPausa) {
            $cursor->modify('+1 month');
            $mesesSaltados++;
            continue;
        }
        $plan[] = array_merge($cargos[$idx], ['periodo' => $ym]);
        $idx++;
        $cursor->modify('+1 month');
    }

    return $plan;
}

function pago_colegiatura_pausa_alumno(array $ins, array $alumno): array
{
    $mesesPausa = (int) ($ins['colegiatura_meses_pausa'] ?? 0);
    $mesesExtension = (int) ($ins['colegiatura_meses_extension'] ?? 0);
    $pausaDesde = $ins['colegiatura_pausa_desde'] ?? null;

    if (($alumno['estado'] ?? '') === 'baja' && !empty($alumno['fecha_baja_temporal'])) {
        $pausaDesde = $pausaDesde ?: $alumno['fecha_baja_temporal'];
        $inicio = new DateTimeImmutable((string) $pausaDesde);
        $hoy = new DateTimeImmutable('today');
        if ($hoy > $inicio) {
            $diff = $inicio->diff($hoy);
            $mesesActivos = ($diff->y * 12) + $diff->m + ($diff->d > 0 ? 1 : 0);
            $mesesPausa += max(0, $mesesActivos);
        }
    }

    return [
        'meses_pausa' => $mesesPausa,
        'meses_extension' => $mesesExtension,
        'pausa_desde' => $pausaDesde,
    ];
}

function pago_marcar_pausa_colegiatura(PDO $pdo, int $idAlumno): void
{
    pago_ensure_schema($pdo);
    $pdo->prepare(
        'UPDATE alumno_especialidades SET colegiatura_pausa_desde = CURDATE()
         WHERE id_alumno = ? AND activo = 1 AND colegiatura_pausa_desde IS NULL'
    )->execute([$idAlumno]);
}

function pago_reanudar_colegiatura_tras_baja(PDO $pdo, int $idAlumno, ?string $fechaReactivacion = null): void
{
    pago_ensure_schema($pdo);
    $fechaReactivacion = $fechaReactivacion ?: date('Y-m-d');
    $st = $pdo->prepare(
        'SELECT id_alumno_especialidad, colegiatura_pausa_desde, colegiatura_meses_pausa
         FROM alumno_especialidades WHERE id_alumno = ? AND activo = 1'
    );
    $st->execute([$idAlumno]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $desde = $row['colegiatura_pausa_desde'] ?? null;
        if (!$desde) {
            continue;
        }
        $inicio = new DateTimeImmutable((string) $desde);
        $fin = new DateTimeImmutable($fechaReactivacion);
        if ($fin <= $inicio) {
            continue;
        }
        $diff = $inicio->diff($fin);
        $meses = ($diff->y * 12) + $diff->m + ($diff->d > 0 ? 1 : 0);
        $total = (int) ($row['colegiatura_meses_pausa'] ?? 0) + max(1, $meses);
        $pdo->prepare(
            'UPDATE alumno_especialidades SET colegiatura_meses_pausa = ?, colegiatura_pausa_desde = NULL
             WHERE id_alumno_especialidad = ?'
        )->execute([$total, (int) $row['id_alumno_especialidad']]);
    }
}

function pago_extender_colegiatura_meses(PDO $pdo, int $idAlumno, int $idEspecialidad, int $mesesExtra): void
{
    if ($mesesExtra <= 0) {
        return;
    }
    pago_ensure_schema($pdo);
    $pdo->prepare(
        'UPDATE alumno_especialidades SET colegiatura_meses_extension = colegiatura_meses_extension + ?
         WHERE id_alumno = ? AND id_especialidad = ? AND activo = 1'
    )->execute([$mesesExtra, $idAlumno, $idEspecialidad]);
}

function pago_fase_orden(PDO $pdo, int $idFase): int
{
    if ($idFase <= 0) {
        return 0;
    }
    $st = $pdo->prepare('SELECT orden FROM especialidad_fases WHERE id_fase = ? LIMIT 1');
    $st->execute([$idFase]);

    return (int) $st->fetchColumn();
}


/** Semanas desde inscripción (domingo inicio) hasta corte */
function pago_periodos_semanales(string $fechaInscripcion, string $fechaCorte, int $duracionSemanas): array
{
    $start = strtotime($fechaInscripcion);
    $end = strtotime($fechaCorte);
    $periodos = [];
    $n = 0;
    $maxSem = $duracionSemanas > 0 ? $duracionSemanas : 520;
    while ($start <= $end && $n < $maxSem) {
        $periodos[] = date('Y', $start) . '-W' . str_pad((string) date('W', $start), 2, '0', STR_PAD_LEFT);
        $start = strtotime('+1 week', $start);
        $n++;
    }
    return $periodos;
}

/**
 * Estado de cuenta / adeudo completo.
 * @return array<string, mixed>
 */
function pago_estado_cuenta(PDO $pdo, int $idAlumno, ?string $fechaCorte = null): array
{
    $fechaCorte = $fechaCorte ?: date('Y-m-d');
    if (function_exists('alumno_tarifa_supervisor_aplicar_vencidas')) {
        alumno_tarifa_supervisor_aplicar_vencidas($pdo, $idAlumno);
    }
    $alumno = alumno_obtener($pdo, $idAlumno, plantel_id_activo());
    if (!$alumno) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    $inscripciones = pago_inscripciones_alumno($pdo, $idAlumno);
    if (empty($inscripciones)) {
        pago_asegurar_inscripcion_desde_alumno($pdo, $idAlumno);
        $inscripciones = pago_inscripciones_alumno($pdo, $idAlumno);
    }

    $todosPagos = pago_listar_alumno($pdo, $idAlumno);
    $pagosColeg = [];
    $pagosProductos = [];
    foreach ($todosPagos as $p) {
        if (($p['tipo'] ?? 'abono') === 'producto') {
            $pagosProductos[] = $p;
        } else {
            $pagosColeg[] = $p;
        }
    }

    $lineasDebe = [];
    $desgloseEspecialidades = [];
    $becas = pago_becas_vigentes($pdo, $idAlumno, $fechaCorte);
    $inscripcionGlobalOk = pago_inscripcion_global_vigente($alumno, $pagosColeg);
    $yaCobroInscripcionEnCalculo = false;

    foreach ($inscripciones as $ins) {
        $idAe = (int) $ins['id_alumno_especialidad'];
        $idEsp = (int) $ins['id_especialidad'];
        $pagosEsp = array_filter($pagosColeg, function ($p) use ($idEsp, $idAe) {
            if (!empty($p['id_alumno_especialidad'])) {
                return (int) $p['id_alumno_especialidad'] === $idAe;
            }
            return (int) ($p['id_especialidad'] ?? 0) === $idEsp || empty($p['id_especialidad']);
        });

        $abonosSinPeriodo = 0.0;
        $pagosPorPeriodo = [];
        foreach ($pagosEsp as $p) {
            $tipoP = $p['tipo'] ?? 'abono';
            if ($tipoP === 'inscripcion') {
                continue;
            }
            if (!empty($p['periodo_ref'])) {
                $pagosPorPeriodo[$p['periodo_ref']][] = $p;
            } else {
                $abonosSinPeriodo += (float) $p['monto'];
            }
        }

        $subDebe = [];
        $subEsperado = 0.0;
        $subEsperadoColeg = 0.0;
        $subPagadoExplicito = 0.0;
        $subPagadoColeg = 0.0;
        $fechaInicioColeg = pago_fecha_inicio_colegiaturas($pdo, $idAlumno, $idEsp, $ins);
        $grupoColeg = pago_grupo_colegiaturas($pdo, $idAlumno, $idEsp);
        $codigoHorario = $grupoColeg['codigo_horario'] ?? 'S';
        $idPlantelGrupo = $grupoColeg['id_plantel'] ?? plantel_id_activo();

        $espMeta = pago_get_especialidad_meta($pdo, $idEsp);
        $evalInsc = pago_evaluar_cobro_inscripcion(
            $espMeta,
            $inscripcionGlobalOk,
            $yaCobroInscripcionEnCalculo,
            (int) $ins['inscripcion_cubierta']
        );
        if ($evalInsc['cobrar']) {
            if ($evalInsc['marca_global']) {
                $yaCobroInscripcionEnCalculo = true;
            }
            $pagosInsc = array_filter($pagosEsp, fn($p) => ($p['tipo'] ?? '') === 'inscripcion');
            $pagadoInsc = array_sum(array_map(fn($p) => (float) $p['monto'], $pagosInsc));
            $esperadoInsc = pago_monto_con_becas(
                (float) $ins['costo_inscripcion'],
                'inscripcion',
                $becas,
                $idAe
            );
            $saldoInsc = max(0, $esperadoInsc - $pagadoInsc);
            if ($saldoInsc > 0.009) {
                $linea = [
                    'tipo' => 'inscripcion',
                    'periodo' => $evalInsc['periodo'] ?? 'INSCRIPCIÓN',
                    'especialidad' => $ins['especialidad_nombre'],
                    'id_especialidad' => $idEsp,
                    'monto_esperado' => $esperadoInsc,
                    'monto_pagado' => $pagadoInsc,
                    'saldo' => $saldoInsc,
                    'tarifa' => 'inscripcion',
                    'detalle' => $evalInsc['detalle'] ?? 'Inscripción',
                ];
                $lineasDebe[] = $linea;
                $subDebe[] = $linea;
                $subEsperado += $esperadoInsc;
            }
            $subPagadoExplicito += $pagadoInsc;
        }

        if ($ins['forma_pago'] === 'semanal') {
            $semanas = pago_periodos_semanales(
                $fechaInicioColeg,
                $fechaCorte,
                (int) ($ins['duracion_semanas'] ?? 0)
            );
            $costoSem = (float) $ins['costo_semanal'];
            foreach ($semanas as $sem) {
                $pagosSem = $pagosPorPeriodo[$sem] ?? [];
                $pagado = array_sum(array_map(fn($p) => (float) $p['monto'], $pagosSem));
                $saldo = max(0, $costoSem - $pagado);
                $subEsperado += $costoSem;
                $subEsperadoColeg += $costoSem;
                $subPagadoExplicito += $pagado;
                $subPagadoColeg += $pagado;
                if ($saldo > 0.009) {
                    $linea = [
                        'tipo' => 'semanal',
                        'periodo' => $sem,
                        'especialidad' => $ins['especialidad_nombre'],
                        'id_especialidad' => $idEsp,
                        'monto_esperado' => $costoSem,
                        'monto_pagado' => $pagado,
                        'saldo' => $saldo,
                        'tarifa' => 'semanal',
                        'detalle' => 'Semana ' . $sem,
                    ];
                    $lineasDebe[] = $linea;
                    $subDebe[] = $linea;
                }
            }
        } else {
            $pausaInfo = pago_colegiatura_pausa_alumno($ins, $alumno);
            $planMeses = pago_plan_periodos_mensuales(
                $pdo,
                $fechaInicioColeg,
                (int) $ins['duracion_meses'],
                $codigoHorario,
                $idPlantelGrupo,
                (int) ($pausaInfo['meses_pausa'] ?? 0),
                (int) ($pausaInfo['meses_extension'] ?? 0),
                $espMeta
            );
            $mesCorte = date('Y-m', strtotime($fechaCorte));
            foreach ($planMeses as $pm) {
                $ym = (string) ($pm['periodo'] ?? '');
                if ($ym === '' || $ym > $mesCorte) {
                    continue;
                }
                $pagosMes = $pagosPorPeriodo[$ym] ?? [];
                $info = pago_monto_mes_esperado(
                    $ym,
                    $fechaCorte,
                    (float) $ins['costo_mensualidad'],
                    (float) $ins['costo_pronto_pago'],
                    $pagosMes
                );
                $montoBase = (float) $info['monto'] * (float) ($pm['factor'] ?? 1.0);
                $esperado = pago_monto_con_becas(round($montoBase, 2), 'colegiatura', $becas, $idAe);
                $pagado = array_sum(array_map(fn($p) => (float) $p['monto'], $pagosMes));
                $saldo = max(0, $esperado - $pagado);
                $detalleMes = date('m/Y', strtotime($ym . '-01')) . ' — ' . $info['motivo'];
                if (!empty($pm['motivo_parcial'])) {
                    $detalleMes .= ' · ' . $pm['motivo_parcial'];
                }
                $subEsperado += $esperado;
                $subEsperadoColeg += $esperado;
                $subPagadoExplicito += $pagado;
                $subPagadoColeg += $pagado;
                if ($saldo > 0.009) {
                    $linea = [
                        'tipo' => 'mensualidad',
                        'periodo' => $ym,
                        'especialidad' => $ins['especialidad_nombre'],
                        'id_especialidad' => $idEsp,
                        'monto_esperado' => $esperado,
                        'monto_pagado' => $pagado,
                        'saldo' => $saldo,
                        'tarifa' => $info['tarifa'],
                        'detalle' => $detalleMes,
                        'es_parcial' => !empty($pm['es_parcial']),
                    ];
                    $lineasDebe[] = $linea;
                    $subDebe[] = $linea;
                }
            }
        }

        $debeInscripcion = array_values(array_filter($subDebe, fn($l) => ($l['tipo'] ?? '') === 'inscripcion'));
        $debeColeg = array_values(array_filter($subDebe, fn($l) => ($l['tipo'] ?? '') !== 'inscripcion'));
        $fifo = pago_fifo_aplicar_abonos($debeColeg, $abonosSinPeriodo);
        $subPagadoAbonos = $fifo['aplicado'];
        $abonosRestantes = $fifo['restante'];
        $subDebe = array_merge($debeInscripcion, $debeColeg);
        $subPagadoColeg += $subPagadoAbonos;
        $saldoEsp = array_sum(array_map(fn($l) => (float) ($l['saldo'] ?? 0), $subDebe));
        $adeudoColegEsp = array_sum(array_map(
            fn($l) => (($l['tipo'] ?? '') !== 'inscripcion') ? (float) ($l['saldo'] ?? 0) : 0,
            $subDebe
        ));

        if ($abonosSinPeriodo > 0 && $subPagadoAbonos > 0) {
            foreach ($subDebe as &$ld) {
                $ld['nota_abonos'] = 'Abonos FIFO: ' . catalog_format_mxn($subPagadoAbonos);
            }
            unset($ld);
        }

        $reglaNombre = null;
        if (!empty($ins['id_regla_combo'])) {
            $rn = $pdo->prepare('SELECT nombre FROM reglas_colegiatura_combo WHERE id_regla = ?');
            $rn->execute([(int) $ins['id_regla_combo']]);
            $reglaNombre = $rn->fetchColumn() ?: null;
        }

        $desgloseEspecialidades[] = [
            'especialidad' => $ins['especialidad_nombre'],
            'forma_pago' => $ins['forma_pago'],
            'fecha_inscripcion' => $ins['fecha_inscripcion'],
            'fecha_inicio_colegiaturas' => $fechaInicioColeg,
            'regla_combo' => $reglaNombre,
            'tarifas_congeladas' => [
                'inscripcion' => (float) $ins['costo_inscripcion'],
                'mensualidad' => (float) $ins['costo_mensualidad'],
                'pronto_pago' => (float) $ins['costo_pronto_pago'],
                'semanal' => (float) $ins['costo_semanal'],
            ],
            'total_esperado' => $subEsperado,
            'total_esperado_colegiatura' => $subEsperadoColeg,
            'pagado_periodos' => $subPagadoExplicito,
            'pagado_colegiatura' => $subPagadoColeg,
            'abonos_aplicados' => $subPagadoAbonos,
            'abonos_sin_asignar' => $abonosRestantes,
            'adeudo' => $saldoEsp,
            'adeudo_colegiatura' => $adeudoColegEsp,
            'lineas_pendientes' => $subDebe,
        ];
    }

    $pagosInscripcion = [];
    $pagosColegiaturaSolo = [];
    foreach ($pagosColeg as $p) {
        if (($p['tipo'] ?? '') === 'inscripcion') {
            $pagosInscripcion[] = $p;
        } else {
            $pagosColegiaturaSolo[] = $p;
        }
    }

    $totalEsperadoColeg = array_sum(array_map(
        fn($d) => (float) ($d['total_esperado_colegiatura'] ?? 0),
        $desgloseEspecialidades
    ));
    $totalPagadoColeg = array_sum(array_map(fn($p) => (float) $p['monto'], $pagosColegiaturaSolo));
    $adeudoColeg = max(0, round(array_sum(array_map(
        fn($d) => (float) ($d['adeudo_colegiatura'] ?? 0),
        $desgloseEspecialidades
    )), 2));
    $totalProductos = array_sum(array_map(fn($p) => (float) $p['monto'], $pagosProductos));

    return [
        'ok' => true,
        'alumno' => $alumno,
        'fecha_corte' => $fechaCorte,
        'dia_pronto_pago' => PAGO_DIA_PRONTO,
        'inscripciones' => $desgloseEspecialidades,
        'lineas_adeudo' => $lineasDebe,
        'pagos_colegiatura' => $pagosColegiaturaSolo,
        'pagos_inscripcion' => $pagosInscripcion,
        'pagos_productos' => $pagosProductos,
        'resumen' => [
            'colegiatura_esperada' => $totalEsperadoColeg,
            'colegiatura_pagada' => $totalPagadoColeg,
            'adeudo_colegiatura' => $adeudoColeg,
            'productos_pagados' => $totalProductos,
            'nota_productos' => 'Los productos no se restan del adeudo de colegiatura.',
        ],
    ];
}

function pago_asegurar_inscripcion_desde_alumno(PDO $pdo, int $idAlumno): void
{
    $a = $pdo->prepare('SELECT * FROM alumnos WHERE id_alumno = ? LIMIT 1');
    $a->execute([$idAlumno]);
    $al = $a->fetch(PDO::FETCH_ASSOC);
    if (!$al || empty($al['id_especialidad'])) {
        $grupos = $pdo->prepare(
            'SELECT DISTINCT g.id_especialidad FROM alumno_grupos ag
             INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
             WHERE ag.id_alumno = ? AND ag.activo = 1 AND g.id_especialidad IS NOT NULL'
        );
        $grupos->execute([$idAlumno]);
        foreach ($grupos->fetchAll(PDO::FETCH_COLUMN) as $idEsp) {
            pago_crear_inscripcion($pdo, $idAlumno, (int) $idEsp, $al['forma_pago'] ?? 'mensual', $al['fecha_alta'] ?? date('Y-m-d'));
        }
        return;
    }
    pago_crear_inscripcion(
        $pdo,
        $idAlumno,
        (int) $al['id_especialidad'],
        $al['forma_pago'] ?? 'mensual',
        $al['fecha_alta'] ?? date('Y-m-d')
    );
}

/**
 * Crea o reactiva inscripción del alumno a una especialidad.
 * Al crear, copia tarifas del catálogo a alumno_especialidades (colegiatura congelada).
 * Si ya existe el vínculo, NO actualiza montos — solo activo y forma de pago.
 */
function pago_crear_inscripcion(
    PDO $pdo,
    int $idAlumno,
    int $idEspecialidad,
    string $formaPago,
    string $fechaInscripcion,
    bool $congelarTarifas = true
): int {
    $esp = $pdo->prepare('SELECT * FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $esp->execute([$idEspecialidad]);
    $e = $esp->fetch(PDO::FETCH_ASSOC);
    if (!$e) {
        return 0;
    }

    $ex = $pdo->prepare(
        'SELECT id_alumno_especialidad FROM alumno_especialidades
         WHERE id_alumno = ? AND id_especialidad = ? LIMIT 1'
    );
    $ex->execute([$idAlumno, $idEspecialidad]);
    $idAe = (int) $ex->fetchColumn();

    if ($idAe > 0) {
        $pdo->prepare('UPDATE alumno_especialidades SET activo = 1, forma_pago = ? WHERE id_alumno_especialidad = ?')
            ->execute([$formaPago, $idAe]);
        if (function_exists('pago_aplicar_reglas_combo')) {
            pago_aplicar_reglas_combo($pdo, $idAlumno);
        }
        return $idAe;
    }

    $tarifas = function_exists('operativo_cncm_tarifas_especialidad')
        ? operativo_cncm_tarifas_especialidad($e)
        : [];
    $insApoyo = $tarifas['inscripcion_apoyo'] ?? (float) ($e['costo_inscripcion_apoyo'] ?? $e['costo_inscripcion'] ?? 0);
    $menApoyo = $tarifas['mensualidad_apoyo'] ?? (float) ($e['costo_mensualidad_apoyo'] ?? $e['costo_mensualidad'] ?? 0);
    $proApoyo = $tarifas['pronto_apoyo'] ?? (float) ($e['costo_pronto_pago_apoyo'] ?? $e['costo_pronto_pago'] ?? 0);
    $semApoyo = $tarifas['semanal_apoyo'] ?? (float) ($e['costo_semanal_apoyo'] ?? $e['costo_semanal'] ?? 0);

    $pdo->prepare(
        'INSERT INTO alumno_especialidades (
            id_alumno, id_especialidad, forma_pago, fecha_inscripcion,
            costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal,
            costo_inscripcion_referencia, costo_inscripcion_apoyo,
            costo_mensualidad_referencia, costo_mensualidad_apoyo,
            costo_pronto_pago_referencia, costo_pronto_pago_apoyo,
            costo_semanal_referencia, costo_semanal_apoyo,
            duracion_meses, duracion_semanas
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idAlumno, $idEspecialidad, $formaPago, $fechaInscripcion,
        $insApoyo, $menApoyo, $proApoyo, $semApoyo,
        $tarifas['inscripcion_ref'] ?? $insApoyo * 2,
        $insApoyo,
        $tarifas['mensualidad_ref'] ?? $menApoyo * 1.5,
        $menApoyo,
        $tarifas['pronto_ref'] ?? $proApoyo * 1.5,
        $proApoyo,
        $tarifas['semanal_ref'] ?? $semApoyo * 1.5,
        $semApoyo,
        $e['duracion_meses'], $e['duracion_semanas'],
    ]);
    $idAe = (int) $pdo->lastInsertId();
    if (function_exists('plan_version_asignar_alumno')) {
        plan_version_asignar_alumno($pdo, $idAlumno, $idEspecialidad);
    }
    if (function_exists('acuerdo_asignar_alumno')) {
        acuerdo_asignar_alumno($pdo, $idAlumno);
    }
    if (function_exists('pago_aplicar_reglas_combo')) {
        pago_aplicar_reglas_combo($pdo, $idAlumno);
    }
    return $idAe;
}

function pago_generar_folio_inscripcion(PDO $pdo, int $idPlantel): string
{
    $pref = 'INS-' . str_pad((string) $idPlantel, 2, '0', STR_PAD_LEFT) . '-';
    $st = $pdo->prepare(
        'SELECT folio FROM alumno_pagos
         WHERE id_plantel = ? AND folio LIKE ?
         ORDER BY id_pago DESC LIMIT 1'
    );
    $st->execute([$idPlantel, $pref . '%']);
    $ultimo = (string) $st->fetchColumn();
    $seq = 1;
    if ($ultimo !== '' && preg_match('/(\d+)$/', $ultimo, $m)) {
        $seq = (int) $m[1] + 1;
    }

    return $pref . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
}

/** Formato de moneda para ticket térmico ($ 370.00). */
function pago_ticket_format_mxn(float $monto): string
{
    return '$ ' . number_format($monto, 2, '.', ',');
}

/** Etiqueta legible de un periodo de colegiatura. */
function pago_periodo_etiqueta(string $periodo, string $tipo = 'semanal'): string
{
    $periodo = trim($periodo);
    if ($periodo === '' || strtoupper($periodo) === 'INSCRIPCIÓN') {
        return 'Inscripción';
    }
    if (preg_match('/^(\d{4})-W(\d+)$/i', $periodo, $m)) {
        return 'Colegiatura de semana #' . (int) $m[2] . ' del ' . $m[1];
    }
    if (preg_match('/^(\d{4})-(\d{2})$/', $periodo, $m)) {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];
        $mes = $meses[(int) $m[2]] ?? $m[2];

        return 'Colegiatura de ' . $mes . ' del ' . $m[1];
    }
    if ($tipo === 'inscripcion') {
        return 'Inscripción';
    }
    if ($tipo === 'producto') {
        return $periodo;
    }

    return $periodo;
}

/**
 * Genera líneas de desglose FIFO para un pago (antes de guardar cubrio).
 * @return list<array{descripcion:string, monto:float, monto_fmt:string}>
 */
function pago_generar_desglose_pago(
    PDO $pdo,
    int $idAlumno,
    float $monto,
    string $tipo,
    ?string $periodoRef,
    ?int $idEspecialidad,
    ?int $idAlumnoEspecialidad,
    string $concepto = ''
): array {
    $monto = round(max(0, $monto), 2);
    if ($monto <= 0) {
        return [];
    }

    if ($tipo === 'producto') {
        $desc = trim($concepto) ?: 'Producto';

        return [[
            'descripcion' => $desc,
            'monto' => $monto,
            'monto_fmt' => pago_ticket_format_mxn($monto),
        ]];
    }

    if ($tipo === 'inscripcion') {
        $desc = trim($concepto) ?: 'Inscripción';

        return [[
            'descripcion' => $desc,
            'monto' => $monto,
            'monto_fmt' => pago_ticket_format_mxn($monto),
        ]];
    }

    if ($periodoRef !== null && $periodoRef !== '' && $tipo !== 'abono') {
        $desc = trim($concepto) ?: pago_periodo_etiqueta($periodoRef, $tipo);

        return [[
            'descripcion' => $desc,
            'monto' => $monto,
            'monto_fmt' => pago_ticket_format_mxn($monto),
        ]];
    }

    $ec = pago_estado_cuenta($pdo, $idAlumno);
    if (empty($ec['ok'])) {
        $desc = trim($concepto) ?: pago_label_tipo($tipo);

        return [[
            'descripcion' => $desc,
            'monto' => $monto,
            'monto_fmt' => pago_ticket_format_mxn($monto),
        ]];
    }

    $pendientes = [];
    foreach ($ec['lineas_adeudo'] ?? [] as $ln) {
        $saldo = (float) ($ln['saldo'] ?? 0);
        if ($saldo <= 0.009) {
            continue;
        }
        if ($idEspecialidad > 0 && !empty($ln['id_especialidad']) && (int) $ln['id_especialidad'] !== $idEspecialidad) {
            continue;
        }
        $pendientes[] = $ln;
    }

    if ($pendientes === []) {
        $desc = trim($concepto) ?: ($periodoRef ? pago_periodo_etiqueta($periodoRef, $tipo) : pago_label_tipo($tipo));

        return [[
            'descripcion' => $desc,
            'monto' => $monto,
            'monto_fmt' => pago_ticket_format_mxn($monto),
        ]];
    }

    usort($pendientes, static function ($a, $b) {
        return strcmp((string) ($a['periodo'] ?? ''), (string) ($b['periodo'] ?? ''));
    });

    $lineas = [];
    $rest = $monto;
    foreach ($pendientes as $ln) {
        if ($rest <= 0.001) {
            break;
        }
        $saldo = (float) ($ln['saldo'] ?? 0);
        if ($saldo <= 0.001) {
            continue;
        }
        $ap = round(min($rest, $saldo), 2);
        $periodo = (string) ($ln['periodo'] ?? '');
        $tipoLn = (string) ($ln['tipo'] ?? 'abono');
        $desc = trim((string) ($ln['detalle'] ?? ''));
        if ($desc === '' || preg_match('/^Semana \d{4}-W/i', $desc)) {
            $desc = pago_periodo_etiqueta($periodo, $tipoLn);
        }
        $lineas[] = [
            'descripcion' => $desc,
            'monto' => $ap,
            'monto_fmt' => pago_ticket_format_mxn($ap),
        ];
        $rest = round($rest - $ap, 2);
    }

    if ($rest > 0.009) {
        $lineas[] = [
            'descripcion' => trim($concepto) ?: 'Abono',
            'monto' => $rest,
            'monto_fmt' => pago_ticket_format_mxn($rest),
        ];
    }

    return $lineas;
}

/**
 * Distribuye un cobro del POS en periodos pendientes (por montos, no por conteo de pagos).
 * @return list<array<string, mixed>>
 */
function pago_construir_items_cobro(
    PDO $pdo,
    int $idAlumno,
    float $montoTotal,
    ?int $idEspecialidad = null,
    ?int $maxPeriodos = null
): array {
    $montoTotal = round(max(0, $montoTotal), 2);
    if ($montoTotal <= 0) {
        return [];
    }

    $ec = pago_estado_cuenta($pdo, $idAlumno);
    if (empty($ec['ok'])) {
        return [[
            'tipo' => 'abono',
            'monto' => $montoTotal,
            'id_especialidad' => $idEspecialidad,
            'concepto' => 'Abono',
            'periodo_ref' => null,
        ]];
    }

    $pendientes = [];
    foreach ($ec['inscripciones'] ?? [] as $ins) {
        foreach ($ins['lineas_pendientes'] ?? [] as $ln) {
            $saldo = (float) ($ln['saldo'] ?? 0);
            if ($saldo <= 0.009) {
                continue;
            }
            if ($idEspecialidad > 0 && (int) ($ln['id_especialidad'] ?? 0) !== $idEspecialidad) {
                continue;
            }
            $pendientes[] = $ln;
        }
    }

    usort($pendientes, static function ($a, $b) {
        return strcmp((string) ($a['periodo'] ?? ''), (string) ($b['periodo'] ?? ''));
    });

    $items = [];
    $rest = $montoTotal;
    $n = 0;
    foreach ($pendientes as $ln) {
        if ($rest <= 0.001) {
            break;
        }
        if ($maxPeriodos !== null && $maxPeriodos > 0 && $n >= $maxPeriodos) {
            break;
        }
        $saldo = (float) ($ln['saldo'] ?? 0);
        if ($saldo <= 0.001) {
            continue;
        }
        $ap = round(min($rest, $saldo), 2);
        $tipoLn = (string) ($ln['tipo'] ?? 'mensualidad');
        $periodo = (string) ($ln['periodo'] ?? '');
        $items[] = [
            'tipo' => in_array($tipoLn, ['inscripcion', 'mensualidad', 'semanal'], true) ? $tipoLn : 'abono',
            'monto' => $ap,
            'periodo_ref' => $periodo !== '' ? $periodo : null,
            'id_especialidad' => (int) ($ln['id_especialidad'] ?? 0) ?: $idEspecialidad,
            'concepto' => trim((string) ($ln['detalle'] ?? '')) ?: pago_periodo_etiqueta($periodo, $tipoLn),
        ];
        $rest = round($rest - $ap, 2);
        $n++;
    }

    if ($rest > 0.009) {
        $items[] = [
            'tipo' => 'abono',
            'monto' => $rest,
            'id_especialidad' => $idEspecialidad,
            'concepto' => 'Abono',
            'periodo_ref' => null,
        ];
    }

    if ($items === []) {
        $items[] = [
            'tipo' => 'abono',
            'monto' => $montoTotal,
            'id_especialidad' => $idEspecialidad,
            'concepto' => 'Abono punto de venta',
            'periodo_ref' => null,
        ];
    }

    return $items;
}

function pago_alumno_requiere_factura(PDO $pdo, int $idAlumno): bool
{
    if ($idAlumno <= 0) {
        return false;
    }
    $st = $pdo->prepare('SELECT id_preregistro FROM alumnos WHERE id_alumno = ? LIMIT 1');
    $st->execute([$idAlumno]);
    $idPr = (int) ($st->fetchColumn() ?: 0);
    if ($idPr <= 0) {
        return false;
    }
    $st = $pdo->prepare('SELECT requiere_factura FROM preregistros WHERE id_preregistro = ? LIMIT 1');
    $st->execute([$idPr]);

    return (int) ($st->fetchColumn() ?: 0) === 1;
}

/** Cuenta A: tarjeta, transferencia o alumno con factura. Cuenta B: efectivo sin factura / productos. */
function pago_resolver_cuenta_contable(string $formaPago, bool $requiereFactura, string $tipo = 'abono'): string
{
    if ($tipo === 'producto') {
        return 'B';
    }
    if ($requiereFactura) {
        return 'A';
    }
    $f = mb_strtolower(trim($formaPago));
    if (
        str_contains($f, 'tarjeta')
        || str_contains($f, 'transfer')
        || str_contains($f, 'débito')
        || str_contains($f, 'debito')
        || str_contains($f, 'crédito')
        || str_contains($f, 'credito')
    ) {
        return 'A';
    }

    return 'B';
}

/** @param list<array{descripcion:string, monto:float}> $lineas */
function pago_desglose_a_cubrio(array $lineas): string
{
    $out = [];
    foreach ($lineas as $ln) {
        $out[] = trim($ln['descripcion']) . ' — ' . pago_ticket_format_mxn((float) $ln['monto']);
    }

    return implode("\n", $out);
}

/**
 * Interpreta cubrio guardado o genera una línea única.
 * @return list<array{descripcion:string, monto:float, monto_fmt:string}>
 */
function pago_parse_desglose_ticket(
    string $cubrio,
    float $montoTotal,
    string $concepto,
    string $tipo
): array {
    $cubrio = trim($cubrio);
    if ($cubrio !== '') {
        $lineas = [];
        foreach (preg_split('/\r\n|\r|\n/', $cubrio) as $row) {
            $row = trim($row);
            if ($row === '') {
                continue;
            }
            if (preg_match('/^(.+?)\s*[—\-–]\s*\$\s*([\d,]+\.\d{2})\s*$/u', $row, $m)) {
                $lineas[] = [
                    'descripcion' => trim($m[1]),
                    'monto' => (float) str_replace(',', '', $m[2]),
                    'monto_fmt' => pago_ticket_format_mxn((float) str_replace(',', '', $m[2])),
                ];
                continue;
            }
            if (preg_match('/\$\s*([\d,]+\.\d{2})/', $row, $m)) {
                $lineas[] = [
                    'descripcion' => trim(preg_replace('/\s*[—\-–]\s*\$\s*[\d,]+\.\d{2}\s*$/u', '', $row)),
                    'monto' => (float) str_replace(',', '', $m[1]),
                    'monto_fmt' => pago_ticket_format_mxn((float) str_replace(',', '', $m[1])),
                ];
                continue;
            }
            $lineas[] = [
                'descripcion' => $row,
                'monto' => 0.0,
                'monto_fmt' => '',
            ];
        }
        if ($lineas !== []) {
            $sum = round(array_sum(array_column($lineas, 'monto')), 2);
            if ($sum <= 0.009 && $montoTotal > 0) {
                $lineas[0]['monto'] = $montoTotal;
                $lineas[0]['monto_fmt'] = pago_ticket_format_mxn($montoTotal);
            }

            return $lineas;
        }
    }

    $desc = trim($concepto) ?: pago_label_tipo($tipo);

    return [[
        'descripcion' => $desc,
        'monto' => $montoTotal,
        'monto_fmt' => pago_ticket_format_mxn($montoTotal),
    ]];
}

/** @return array<string, mixed>|null */
function pago_datos_ticket(PDO $pdo, int $idPago, int $idPlantel): ?array
{
    $st = $pdo->prepare(
        'SELECT p.*, a.numero_control, a.nombres, a.apellido_paterno, a.apellido_materno,
                e.nombre AS especialidad_nombre,
                CONCAT(u.nombre, \' \', u.apellido) AS cajero_nombre
         FROM alumno_pagos p
         INNER JOIN alumnos a ON a.id_alumno = p.id_alumno
         LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
         WHERE p.id_pago = ? AND p.id_plantel = ? LIMIT 1'
    );
    $st->execute([$idPago, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $plantel = plantel_find($pdo, $idPlantel);
    $nombre = trim(
        ($row['nombres'] ?? '') . ' '
        . ($row['apellido_paterno'] ?? '') . ' '
        . ($row['apellido_materno'] ?? '')
    );
    if (!empty($row['cliente_nombre'])) {
        $nombre = trim((string) $row['cliente_nombre']);
    }
    $tipo = (string) ($row['tipo'] ?? 'abono');
    $conceptoDefault = match ($tipo) {
        'inscripcion' => 'Inscripción',
        'mensualidad' => 'Mensualidad',
        'abono' => 'Abono',
        default => ucfirst($tipo),
    };
    $concepto = trim((string) ($row['concepto'] ?? '')) ?: $conceptoDefault;
    $monto = (float) ($row['monto'] ?? 0);
    $fechaRaw = $row['creado_en'] ?? date('Y-m-d H:i:s');
    $ts = strtotime($fechaRaw) ?: time();
    $desglose = pago_parse_desglose_ticket(
        (string) ($row['cubrio'] ?? ''),
        $monto,
        $concepto,
        $tipo
    );
    $plantelTicket = plantel_datos_ticket($plantel);

    return [
        'id_pago' => (int) $row['id_pago'],
        'id_alumno' => (int) $row['id_alumno'],
        'folio' => $row['folio'] ?? ('PAG-' . $idPago),
        'fecha' => $fechaRaw,
        'fecha_fmt' => date('d-m-Y', $ts),
        'hora_fmt' => date('H:i:s', $ts),
        'monto' => $monto,
        'monto_fmt' => pago_ticket_format_mxn($monto),
        'alumno' => $nombre,
        'numero_control' => $row['numero_control'] ?? '',
        'especialidad' => $row['especialidad_nombre'] ?? '—',
        'concepto' => $concepto,
        'tipo' => $tipo,
        'forma_pago' => $row['forma_pago'] ?? 'Efectivo',
        'cajero' => trim($row['cajero_nombre'] ?? ''),
        'recibio' => trim($row['cajero_nombre'] ?? ''),
        'plantel' => $plantel['nombre'] ?? 'CNCM',
        'plantel_ticket' => $plantelTicket,
        'desglose' => $desglose,
        'grupo' => '',
    ];
}

function pago_ticket_url_absoluta(string $relative = ''): string
{
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $root = function_exists('hay_web_root') ? hay_web_root() : '/';
    $path = rtrim($root, '/') . '/' . $relative;
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if ($host === '') {
        return $path;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    $scheme = $https ? 'https' : 'http';

    return $scheme . '://' . $host . $path;
}

function pago_ticket_moodle_url(): string
{
    if (function_exists('moodle_base_url')) {
        $url = moodle_base_url();
        if ($url !== '') {
            return $url;
        }
    }
    if (defined('MOODLE_URL') && trim((string) MOODLE_URL) !== '') {
        return rtrim((string) MOODLE_URL, '/');
    }

    return 'https://www.cncm.edu.mx/courses';
}

/** @return list<array{plataforma:string,url:string,usuario:string,password:string}> */
function pago_ticket_accesos_inscripcion(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    if ($idAlumno <= 0) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT a.id_alumno, a.id_plantel, a.numero_control, a.nombres, a.nombre,
                a.apellido_paterno, a.apellido_materno, a.apellido, a.email,
                u.username AS hay_username, u.email AS hay_email
         FROM alumnos a
         LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
         WHERE a.id_alumno = ? AND a.id_plantel = ?
         LIMIT 1'
    );
    $st->execute([$idAlumno, $idPlantel]);
    $al = $st->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        return [];
    }

    $numeroControl = trim((string) ($al['numero_control'] ?? ''));
    $password = function_exists('cuenta_password_inicial')
        ? cuenta_password_inicial()
        : (function_exists('usuario_password_default_moodle')
            ? usuario_password_default_moodle($numeroControl)
            : 'Cncm*1234');
    $correo = strtolower(trim((string) ($al['email'] ?? '')));
    if ($correo === '' && $numeroControl !== '') {
        $correo = function_exists('cuenta_email_alumno')
            ? cuenta_email_alumno($numeroControl)
            : strtolower($numeroControl) . '@' . INSTITUTIONAL_EMAIL_DOMAIN;
    }
    $dominio = function_exists('cuenta_dominio_email')
        ? cuenta_dominio_email()
        : (defined('GOOGLE_WORKSPACE_DOMAIN') ? (string) GOOGLE_WORKSPACE_DOMAIN : INSTITUTIONAL_EMAIL_DOMAIN);
    $hayUser = trim((string) ($al['hay_username'] ?? '')) ?: $numeroControl;
    $moodleUser = $numeroControl;
    if (function_exists('moodle_user_payload_from_alumno')) {
        $payload = moodle_user_payload_from_alumno($al);
        $moodleUser = (string) ($payload['username'] ?? $moodleUser);
    } elseif (function_exists('moodle_username_from_numero_control')) {
        $moodleUser = moodle_username_from_numero_control($numeroControl);
    }

    return [
        [
            'plataforma' => 'Google Workspace',
            'url' => 'https://mail.google.com/a/' . rawurlencode($dominio),
            'usuario' => $correo,
            'password' => $password,
        ],
        [
            'plataforma' => 'Sistema CNCM',
            'url' => pago_ticket_url_absoluta('dashboard.php'),
            'usuario' => $hayUser,
            'password' => $password,
        ],
        [
            'plataforma' => 'Moodle',
            'url' => pago_ticket_moodle_url(),
            'usuario' => $moodleUser,
            'password' => $password,
        ],
    ];
}

/** @return array<string, mixed>|null */
function pago_datos_ticket_inscripcion(PDO $pdo, int $idPago, int $idPlantel): ?array
{
    $ticket = pago_datos_ticket($pdo, $idPago, $idPlantel);
    if (!$ticket || ($ticket['tipo'] ?? '') !== 'inscripcion') {
        return null;
    }

    $ticket['accesos'] = pago_ticket_accesos_inscripcion($pdo, (int) ($ticket['id_alumno'] ?? 0), $idPlantel);

    return $ticket;
}

function pago_registrar(PDO $pdo, array $data): array
{
    $idAlumno = (int) ($data['id_alumno'] ?? 0);
    $montoBruto = catalog_money($data['monto'] ?? 0);
    $tipo = $data['tipo'] ?? 'abono';
    if ($montoBruto <= 0 || $idAlumno <= 0) {
        return ['ok' => false, 'message' => 'Alumno y monto son obligatorios'];
    }

    $descuento = 0.0;
    $monto = $montoBruto;
    $idPromo = (int) ($data['id_promocion'] ?? 0);
    if ($idPromo > 0) {
        $pr = pago_promocion_aplicar($pdo, $idPromo, $montoBruto);
        $monto = $pr['monto'];
        $descuento = $pr['descuento'] ?? 0;
    }

    if ($tipo === 'producto' && !empty($data['id_producto']) && empty($data['omitir_inventario'])) {
        $cant = max(1, (int) ($data['cantidad'] ?? 1));
        $resInv = pago_descontar_inventario($pdo, (int) $data['id_producto'], $cant);
        if (!$resInv['ok']) {
            return $resInv;
        }
    }

    $fechaPago = $data['creado_en'] ?? date('Y-m-d H:i:s');
    $periodoRef = $data['periodo_ref'] ?? null;
    if ($tipo === 'mensualidad' && !empty($periodoRef)) {
        $aplicoPronto = pago_aplica_pronto_pago((string) $periodoRef, date('Y-m-d', strtotime($fechaPago))) ? 1 : 0;
    } else {
        $aplicoPronto = (int) ($data['aplico_pronto_pago'] ?? 0);
    }

    // Los abonos sin periodo explícito se reparten por FIFO al calcular adeudo; no ligarlos a un solo mes.

    $cubrio = trim((string) ($data['cubrio'] ?? ''));
    if ($cubrio === '') {
        $desglose = pago_generar_desglose_pago(
            $pdo,
            $idAlumno,
            $monto,
            $tipo,
            $periodoRef,
            (int) ($data['id_especialidad'] ?? 0) ?: null,
            (int) ($data['id_alumno_especialidad'] ?? 0) ?: null,
            trim((string) ($data['concepto'] ?? ''))
        );
        $cubrio = pago_desglose_a_cubrio($desglose);
    }

    $requiereFactura = pago_alumno_requiere_factura($pdo, $idAlumno);
    $formaPago = (string) ($data['forma_pago_efectivo'] ?? 'Efectivo');
    $cuentaContable = $data['cuenta_contable'] ?? pago_resolver_cuenta_contable($formaPago, $requiereFactura, $tipo);

    $idSolCert = (int) ($data['id_solicitud_cert'] ?? 0) ?: null;

    $stmt = $pdo->prepare(
        'INSERT INTO alumno_pagos (
            id_alumno, id_plantel, id_especialidad, tipo, id_producto, id_alumno_especialidad, id_solicitud_cert,
            folio, monto, forma_pago, cuenta_contable, concepto, cliente_nombre, cubrio, periodo_ref, aplico_pronto_pago,
            id_beca, id_promocion, monto_descuento, motivo_descuento, id_autoriza, id_usuario, creado_en
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $idAlumno,
        plantel_id_activo(),
        $data['id_especialidad'] ?: null,
        $tipo,
        $data['id_producto'] ?: null,
        $data['id_alumno_especialidad'] ?: null,
        $idSolCert,
        $data['folio'] ?? null,
        $monto,
        $formaPago,
        $cuentaContable,
        $data['concepto'] ?? '',
        !empty($data['cliente_nombre']) ? trim((string) $data['cliente_nombre']) : null,
        $cubrio,
        $periodoRef,
        $aplicoPronto,
        $data['id_beca'] ?: null,
        $idPromo ?: null,
        $descuento,
        $data['motivo_descuento'] ?? null,
        $data['id_autoriza'] ?: null,
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
        $fechaPago,
    ]);

    $idPago = (int) $pdo->lastInsertId();

    if (function_exists('operativo_cncm_pago_aplicar_meta')) {
        operativo_cncm_pago_aplicar_meta($pdo, $idPago, $data);
    }

    if ($tipo === 'inscripcion') {
        if (!empty($data['id_alumno_especialidad'])) {
            $idAe = (int) $data['id_alumno_especialidad'];
            $idEspInsc = (int) ($data['id_especialidad'] ?? 0);
            if ($idEspInsc <= 0) {
                $aeSt = $pdo->prepare(
                    'SELECT id_especialidad FROM alumno_especialidades WHERE id_alumno_especialidad = ? LIMIT 1'
                );
                $aeSt->execute([$idAe]);
                $idEspInsc = (int) $aeSt->fetchColumn();
            }
            if ($idEspInsc > 0) {
                pago_actualizar_inscripcion_cubierta($pdo, $idAlumno, $idEspInsc, $idAe);
            }
        }
        pago_sync_inscripcion_global($pdo, $idAlumno);
    }

    return [
        'ok' => true,
        'message' => 'Pago registrado',
        'id_pago' => $idPago,
        'periodo_ref' => $periodoRef,
        'monto' => $monto,
        'descuento' => $descuento,
    ];
}

/** Marca inscripcion_cubierta solo si los pagos de inscripción cubren el costo congelado. */
function pago_actualizar_inscripcion_cubierta(PDO $pdo, int $idAlumno, int $idEspecialidad, int $idAe): void
{
    if ($idAlumno <= 0 || $idAe <= 0) {
        return;
    }

    $ins = $pdo->prepare(
        'SELECT costo_inscripcion FROM alumno_especialidades WHERE id_alumno_especialidad = ? LIMIT 1'
    );
    $ins->execute([$idAe]);
    $costo = (float) $ins->fetchColumn();
    if ($costo <= 0) {
        return;
    }

    $pag = $pdo->prepare(
        "SELECT COALESCE(SUM(monto), 0) FROM alumno_pagos
         WHERE id_alumno = ? AND tipo = 'inscripcion' AND id_alumno_especialidad = ?" . pago_sql_filtro_activos()
    );
    $pag->execute([$idAlumno, $idAe]);
    $pagado = (float) $pag->fetchColumn();

    $cubierta = $pagado >= ($costo - 0.009) ? 1 : 0;
    $pdo->prepare('UPDATE alumno_especialidades SET inscripcion_cubierta = ? WHERE id_alumno_especialidad = ?')
        ->execute([$cubierta, $idAe]);
    pago_sync_inscripcion_global($pdo, $idAlumno);
}

/** Ajusta inscripcion_global_pagada según cobertura real (no marcar con apartados parciales). */
function pago_sync_inscripcion_global(PDO $pdo, int $idAlumno): void
{
    if ($idAlumno <= 0) {
        return;
    }

    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM alumno_especialidades WHERE id_alumno = ? AND activo = 1'
    );
    $st->execute([$idAlumno]);
    $activas = (int) $st->fetchColumn();

    if ($activas <= 0) {
        return;
    }

    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM alumno_especialidades
         WHERE id_alumno = ? AND activo = 1 AND inscripcion_cubierta = 0'
    );
    $st->execute([$idAlumno]);
    $pendientes = (int) $st->fetchColumn();

    if ($pendientes > 0) {
        $pdo->prepare('UPDATE alumnos SET inscripcion_global_pagada = 0 WHERE id_alumno = ?')
            ->execute([$idAlumno]);

        return;
    }

    $pdo->prepare(
        'UPDATE alumnos SET inscripcion_global_pagada = 1,
         fecha_inscripcion_global = COALESCE(fecha_inscripcion_global, CURDATE())
         WHERE id_alumno = ?'
    )->execute([$idAlumno]);
}

function pago_sugerir_periodo_fifo(PDO $pdo, int $idAlumno, int $idAe): ?string
{
    $ec = pago_estado_cuenta($pdo, $idAlumno);
    if (!$ec['ok']) {
        return null;
    }
    foreach ($ec['lineas_adeudo'] as $ln) {
        if ($idAe > 0 && (int) ($ln['id_especialidad'] ?? 0) !== 0) {
            // match by specialty via inscripciones — use first pending line
        }
        if ((float) ($ln['saldo'] ?? 0) > 0.01) {
            return $ln['periodo'] ?? null;
        }
    }
    return null;
}

function pago_descontar_inventario(PDO $pdo, int $idProducto, int $cantidad, string $notas = 'Venta punto de venta'): array
{
    $idPlantel = plantel_id_activo();
    venta_producto_ensure_schema($pdo);

    $stCtrl = $pdo->prepare('SELECT COALESCE(controla_inventario, 1) FROM productos WHERE id_producto = ? LIMIT 1');
    $stCtrl->execute([$idProducto]);
    if ((int) ($stCtrl->fetchColumn() ?: 1) === 0) {
        return ['ok' => true, 'sin_inventario' => true];
    }

    $stmt = $pdo->prepare(
        'SELECT existencia FROM producto_inventario WHERE id_producto = ? AND id_plantel = ? LIMIT 1'
    );
    $stmt->execute([$idProducto, $idPlantel]);
    $exist = $stmt->fetchColumn();
    if ($exist === false || (int) $exist < $cantidad) {
        return ['ok' => false, 'message' => 'Stock insuficiente para el producto'];
    }
    $pdo->prepare(
        'UPDATE producto_inventario SET existencia = existencia - ? WHERE id_producto = ? AND id_plantel = ?'
    )->execute([$cantidad, $idProducto, $idPlantel]);
    $pdo->prepare(
        'INSERT INTO producto_movimientos (id_producto, id_plantel, tipo, cantidad, notas, estado, id_usuario_registro)
         VALUES (?,?,\'salida\',?,?,\'aplicado\',?)'
    )->execute([
        $idProducto, $idPlantel, $cantidad, $notas, (int) ($_SESSION['user_id'] ?? 0) ?: null,
    ]);
    return ['ok' => true];
}

function pago_buscar_alumno_control(PDO $pdo, string $q, int $idPlantel): ?array
{
    $q = trim($q);
    if ($q === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id_alumno FROM alumnos WHERE id_plantel = ? AND (
            numero_control = ? OR matricula = ? OR id_alumno = ?
        ) LIMIT 1'
    );
    $idNum = ctype_digit($q) ? (int) $q : 0;
    $stmt->execute([$idPlantel, $q, $q, $idNum]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare(
            'SELECT id_alumno FROM alumnos WHERE id_plantel = ? AND (
                nombres LIKE ? OR apellido_paterno LIKE ? OR CONCAT(nombres, " ", apellido_paterno) LIKE ?
            ) LIMIT 1'
        );
        $stmt->execute([$idPlantel, $like, $like, $like]);
        $id = $stmt->fetchColumn();
    }
    return $id ? alumno_obtener($pdo, (int) $id, $idPlantel) : null;
}

function pago_label_tipo(string $tipo): string
{
    return [
        'inscripcion' => 'Inscripción',
        'mensualidad' => 'Mensualidad',
        'semanal' => 'Semanal',
        'abono' => 'Abono',
        'producto' => 'Producto',
        'otro' => 'Otro',
    ][$tipo] ?? $tipo;
}

function pago_get_especialidad_meta(PDO $pdo, int $idEsp): array
{
    $stmt = $pdo->prepare(
        'SELECT id_especialidad, clave, nombre, inscripcion_por_cuatrimestre, modalidad
         FROM especialidades WHERE id_especialidad = ? LIMIT 1'
    );
    $stmt->execute([$idEsp]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function pago_inscripcion_global_vigente(array $alumno, array $pagosColeg): bool
{
    if (empty($alumno['inscripcion_global_pagada'])) {
        return false;
    }
    if (($alumno['estado'] ?? '') === 'activo') {
        return true;
    }
    $hasta = $alumno['inscripcion_vigente_hasta'] ?? null;

    return $hasta && strtotime($hasta) >= strtotime(date('Y-m-d'));
}

/**
 * @return array{cobrar:bool, marca_global:bool, periodo?:string, detalle?:string}
 */
function pago_evaluar_cobro_inscripcion(
    array $espMeta,
    bool $inscripcionGlobalOk,
    bool $yaCobroInscripcionEnCalculo,
    int $inscripcionCubierta
): array {
    if ($inscripcionCubierta) {
        return ['cobrar' => false, 'marca_global' => false];
    }
    if (!empty($espMeta['inscripcion_por_cuatrimestre'])) {
        $cuat = pago_cuatrimestre_actual();
        return [
            'cobrar' => true,
            'marca_global' => false,
            'periodo' => 'INSC-' . $cuat,
            'detalle' => 'Inscripción cuatrimestre ' . $cuat,
        ];
    }
    if ($inscripcionGlobalOk || $yaCobroInscripcionEnCalculo) {
        return ['cobrar' => false, 'marca_global' => false];
    }
    return [
        'cobrar' => true,
        'marca_global' => true,
        'periodo' => 'INSCRIPCIÓN',
        'detalle' => 'Inscripción única (vigencia ' . pago_inscripcion_vigencia_meses($espMeta) . ' meses en baja temporal)',
    ];
}

function pago_cuatrimestre_actual(): string
{
    $m = (int) date('n');
    $y = date('Y');
    if ($m <= 4) {
        return $y . '-1';
    }
    if ($m <= 8) {
        return $y . '-2';
    }
    return $y . '-3';
}

/** @return array<int, array<string, mixed>> */
function pago_becas_vigentes(PDO $pdo, int $idAlumno, string $fecha): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM alumno_becas WHERE id_alumno = ? AND activo = 1
         AND fecha_inicio <= ? AND (fecha_fin IS NULL OR fecha_fin >= ?)'
    );
    $stmt->execute([$idAlumno, $fecha, $fecha]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pago_monto_con_becas(float $monto, string $concepto, array $becas, int $idAe): float
{
    foreach ($becas as $b) {
        $aplica = $b['aplicar_a'] ?? 'colegiatura';
        if ($aplica !== 'ambos' && $aplica !== $concepto && !($concepto === 'mensualidad' && $aplica === 'colegiatura')) {
            continue;
        }
        if (!empty($b['id_alumno_especialidad']) && (int) $b['id_alumno_especialidad'] !== $idAe) {
            continue;
        }
        if (($b['tipo'] ?? '') === 'porcentaje') {
            $monto -= $monto * ((float) $b['valor'] / 100);
        } else {
            $monto -= (float) $b['valor'];
        }
    }
    return max(0, round($monto, 2));
}

/**
 * Aplica abonos a periodos más antiguos primero (FIFO).
 * @param array<int, array<string, mixed>> $lineas
 * @return array{aplicado: float, restante: float}
 */
function pago_fifo_aplicar_abonos(array &$lineas, float $abonos): array
{
    if ($abonos <= 0 || empty($lineas)) {
        return ['aplicado' => 0.0, 'restante' => $abonos];
    }
    usort($lineas, function ($a, $b) {
        return strcmp((string) ($a['periodo'] ?? ''), (string) ($b['periodo'] ?? ''));
    });
    $aplicado = 0.0;
    $rest = $abonos;
    foreach ($lineas as &$ln) {
        if ($rest <= 0.001) {
            break;
        }
        $saldo = max(0, (float) ($ln['saldo'] ?? 0));
        if ($saldo <= 0.001) {
            continue;
        }
        $ap = min($rest, $saldo);
        $ln['monto_pagado'] = (float) ($ln['monto_pagado'] ?? 0) + $ap;
        $ln['saldo'] = round($saldo - $ap, 2);
        $rest -= $ap;
        $aplicado += $ap;
    }
    unset($ln);
    return ['aplicado' => round($aplicado, 2), 'restante' => round(max(0, $rest), 2)];
}

function pago_verificar_autorizador(PDO $pdo, string $usuario, string $password): array
{
    $usuario = trim($usuario);
    if ($usuario === '' || $password === '') {
        return ['ok' => false, 'message' => 'Usuario y contraseña requeridos'];
    }
    require_once __DIR__ . '/auth_helpers.php';
    $u = auth_find_user_by_login($pdo, $usuario);
    if (!$u || !password_verify($password, $u['password'])) {
        return ['ok' => false, 'message' => 'Autorización rechazada'];
    }
    if (!in_array($u['rol'] ?? '', ['admin', 'gerente'], true)) {
        return ['ok' => false, 'message' => 'Solo director o gerente pueden autorizar becas'];
    }
    return ['ok' => true, 'id_usuario' => (int) $u['id_usuario'], 'nombre' => trim($u['nombre'] . ' ' . $u['apellido'])];
}

function pago_promocion_aplicar(PDO $pdo, int $idPromo, float $monto): array
{
    $stmt = $pdo->prepare('SELECT * FROM promociones_descuento WHERE id_promocion = ? AND activo = 1 LIMIT 1');
    $stmt->execute([$idPromo]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        return ['monto' => $monto, 'descuento' => 0];
    }
    $desc = ($p['tipo'] === 'porcentaje')
        ? round($monto * ((float) $p['valor'] / 100), 2)
        : min($monto, (float) $p['valor']);
    return ['monto' => max(0, $monto - $desc), 'descuento' => $desc, 'promo' => $p];
}

function pago_marcar_baja_temporal(PDO $pdo, int $idAlumno): void
{
    $meses = pago_inscripcion_vigencia_meses_alumno($pdo, $idAlumno);
    $hasta = date('Y-m-d', strtotime('+' . $meses . ' months'));
    $pdo->prepare(
        'UPDATE alumnos SET estado = \'baja\', fecha_baja_temporal = CURDATE(),
         inscripcion_vigente_hasta = ? WHERE id_alumno = ?'
    )->execute([$hasta, $idAlumno]);
}

function alumno_inscribir_desde_preregistro(PDO $pdo, int $idPreregistro, bool $finalizar = true): array
{
    $stmt = $pdo->prepare('SELECT * FROM preregistros WHERE id_preregistro = ? LIMIT 1');
    $stmt->execute([$idPreregistro]);
    $pr = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pr) {
        return ['ok' => false, 'message' => 'Pre-registro no encontrado'];
    }
    if (!empty($pr['id_alumno_vinculado'])) {
        return [
            'ok' => true,
            'message' => 'Alumno ya vinculado al pre-registro',
            'id_alumno' => (int) $pr['id_alumno_vinculado'],
            'ya_existia' => true,
        ];
    }

    $idPlantel = (int) $pr['id_plantel'];
    $nc = $finalizar ? alumno_generar_numero_control($pdo, $idPlantel) : null;
    $pdo->prepare(
        'INSERT INTO alumnos (
            id_plantel, numero_control, nombres, apellido_paterno, apellido_materno,
            nombre, apellido, telefono, email, fecha_nacimiento, edad,
            estado, forma_pago, id_especialidad, id_preregistro, fecha_alta
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE())'
    )->execute([
        $idPlantel,
        $nc,
        $pr['nombres'],
        $pr['apellido_paterno'],
        $pr['apellido_materno'] ?? '',
        $pr['nombres'],
        trim($pr['apellido_paterno'] . ' ' . ($pr['apellido_materno'] ?? '')),
        $pr['telefono'] ?? null,
        $pr['email'] ?? null,
        !empty($pr['fecha_nacimiento']) ? $pr['fecha_nacimiento'] : null,
        isset($pr['edad']) && (int) $pr['edad'] > 0 ? (int) $pr['edad'] : null,
        'activo',
        'mensual',
        $pr['id_especialidad'] ?: null,
        $idPreregistro,
    ]);
    $idAlumno = (int) $pdo->lastInsertId();

    if (!empty($pr['id_escuela_origen']) && function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'alumnos', 'id_escuela_origen', 'INT UNSIGNED NULL', 'id_especialidad');
        $pdo->prepare('UPDATE alumnos SET id_escuela_origen = ? WHERE id_alumno = ?')
            ->execute([(int) $pr['id_escuela_origen'], $idAlumno]);
    }

    if (function_exists('preregistro_sync_alumno_asesor')) {
        preregistro_sync_alumno_asesor($pdo, $idPreregistro);
    }
    $idAsesorCom = function_exists('preregistro_id_asesor_comision')
        ? preregistro_id_asesor_comision($pdo, $idPreregistro)
        : 0;
    if ($idAsesorCom > 0) {
        $pdo->prepare('UPDATE alumnos SET id_usuario_asesor = ? WHERE id_alumno = ?')
            ->execute([$idAsesorCom, $idAlumno]);
    } elseif ($idAsesorCom === 0 && function_exists('preregistro_ensure_schema')) {
        $stCncm = $pdo->prepare('SELECT comision_cncm FROM preregistros WHERE id_preregistro = ? LIMIT 1');
        $stCncm->execute([$idPreregistro]);
        if ((int) $stCncm->fetchColumn() === 1) {
            $pdo->prepare('UPDATE alumnos SET id_usuario_asesor = NULL WHERE id_alumno = ?')
                ->execute([$idAlumno]);
        }
    }

    $fotoAlumno = alumno_foto_copiar_desde_preregistro($pr['foto'] ?? null, $idAlumno);
    if ($fotoAlumno) {
        alumno_foto_asignar($pdo, $idAlumno, $fotoAlumno);
    }

    if ($finalizar && !empty($pr['id_especialidad'])) {
        pago_crear_inscripcion($pdo, $idAlumno, (int) $pr['id_especialidad'], 'mensual', date('Y-m-d'), true);
        preregistro_aplicar_apartado_a_alumno($pdo, $idPreregistro);
        pago_aplicar_reglas_combo($pdo, $idAlumno);
    }

    plantel_ensure_column($pdo, 'preregistros', 'id_alumno_vinculado', 'INT UNSIGNED NULL', 'id_especialidad');
    if ($finalizar) {
        $pdo->prepare('UPDATE preregistros SET id_alumno_vinculado = ?, estado = \'inscrito\' WHERE id_preregistro = ?')
            ->execute([$idAlumno, $idPreregistro]);
    } else {
        $pdo->prepare('UPDATE preregistros SET id_alumno_vinculado = ? WHERE id_preregistro = ?')
            ->execute([$idAlumno, $idPreregistro]);
    }

    $cuenta = ['ok' => false, 'message' => ''];
    $msgUser = '';
    if ($finalizar) {
        if (function_exists('acuerdo_asignar_alumno')) {
            acuerdo_asignar_alumno($pdo, $idAlumno);
        }
        $cuenta = usuario_crear_cuenta_alumno($pdo, $idAlumno, $idPlantel);
        $msgUser = $cuenta['ok'] ? ' · ' . $cuenta['message'] : '';
    }

    return [
        'ok' => true,
        'message' => $finalizar
            ? ('Alumno #' . $nc . ' inscrito con colegiatura congelada' . $msgUser)
            : 'Prospecto vinculado — la colegiatura y el número de control se asignan al inscribir al grupo',
        'id_alumno' => $idAlumno,
        'numero_control' => $nc ?? '',
        'usuario' => $cuenta,
        'es_prospecto' => !$finalizar,
    ];
}
