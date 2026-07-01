<?php

/**
 * Modelo operativo CNCM: esquema unificado (catálogo dual, roles, protocolo, planes, cartas).
 */

function operativo_cncm_ensure_schema(PDO $pdo): void
{
    operativo_cncm_tarifas_dual($pdo);
    operativo_cncm_roles_migrar($pdo);
    operativo_cncm_cartas_schema($pdo);
    operativo_cncm_inscripcion_auth_schema($pdo);
    operativo_cncm_pagos_campos($pdo);
    if (function_exists('plan_version_ensure_schema')) {
        plan_version_ensure_schema($pdo);
    }
    if (function_exists('curso_personalizado_ensure_schema')) {
        curso_personalizado_ensure_schema($pdo);
    }
}

/** Precio referencia + apoyo educativo por especialidad. */
function operativo_cncm_tarifas_dual(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }
    catalog_ensure_schema($pdo);

    $cols = [
        ['costo_inscripcion_referencia', 'DECIMAL(12,2) NULL', 'costo_inscripcion'],
        ['costo_inscripcion_apoyo', 'DECIMAL(12,2) NULL', 'costo_inscripcion_referencia'],
        ['costo_mensualidad_referencia', 'DECIMAL(12,2) NULL', 'costo_mensualidad'],
        ['costo_mensualidad_apoyo', 'DECIMAL(12,2) NULL', 'costo_mensualidad_referencia'],
        ['costo_pronto_pago_referencia', 'DECIMAL(12,2) NULL', 'costo_pronto_pago'],
        ['costo_pronto_pago_apoyo', 'DECIMAL(12,2) NULL', 'costo_pronto_pago_referencia'],
        ['costo_semanal_referencia', 'DECIMAL(12,2) NULL', 'costo_semanal'],
        ['costo_semanal_apoyo', 'DECIMAL(12,2) NULL', 'costo_semanal_referencia'],
        ['descuento_adelanto_4meses', 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'costo_semanal_apoyo'],
        ['descuento_adelanto_12meses', 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'descuento_adelanto_4meses'],
        ['fecha_inicio_venta', 'DATE NULL', 'inscripcion_abierta'],
        ['fecha_fin_venta', 'DATE NULL', 'fecha_inicio_venta'],
    ];
    foreach ($cols as [$col, $def, $after]) {
        plantel_ensure_column($pdo, 'especialidades', $col, $def, $after);
    }

    if (hay_meta_get($pdo, 'cncm_tarifas_dual_migrated') !== '1') {
        $pdo->exec(
            "UPDATE especialidades SET
                costo_inscripcion_apoyo = costo_inscripcion,
                costo_mensualidad_apoyo = costo_mensualidad,
                costo_pronto_pago_apoyo = costo_pronto_pago,
                costo_semanal_apoyo = costo_semanal
             WHERE costo_inscripcion_apoyo IS NULL OR costo_inscripcion_apoyo = 0"
        );
        $pdo->exec(
            "UPDATE especialidades SET
                costo_inscripcion_referencia = ROUND(costo_inscripcion_apoyo * 2, 2),
                costo_mensualidad_referencia = ROUND(costo_mensualidad_apoyo * 1.5, 2),
                costo_pronto_pago_referencia = ROUND(costo_pronto_pago_apoyo * 1.5, 2),
                costo_semanal_referencia = ROUND(costo_semanal_apoyo * 1.5, 2)
             WHERE costo_inscripcion_referencia IS NULL OR costo_inscripcion_referencia = 0"
        );
        operativo_cncm_seed_robotica($pdo);
        hay_meta_set($pdo, 'cncm_tarifas_dual_migrated', '1');
    }

    operativo_cncm_ocultar_temporales_vencidas($pdo);

    $aeCols = [
        ['costo_inscripcion_referencia', 'DECIMAL(12,2) NULL', 'costo_inscripcion'],
        ['costo_inscripcion_apoyo', 'DECIMAL(12,2) NULL', 'costo_inscripcion_referencia'],
        ['costo_mensualidad_referencia', 'DECIMAL(12,2) NULL', 'costo_mensualidad'],
        ['costo_mensualidad_apoyo', 'DECIMAL(12,2) NULL', 'costo_mensualidad_referencia'],
        ['costo_pronto_pago_referencia', 'DECIMAL(12,2) NULL', 'costo_pronto_pago'],
        ['costo_pronto_pago_apoyo', 'DECIMAL(12,2) NULL', 'costo_pronto_pago_referencia'],
        ['costo_semanal_referencia', 'DECIMAL(12,2) NULL', 'costo_semanal'],
        ['costo_semanal_apoyo', 'DECIMAL(12,2) NULL', 'costo_semanal_referencia'],
        ['usa_tarifa_cartas', 'TINYINT(1) NOT NULL DEFAULT 0', 'costo_semanal_apoyo'],
    ];
    foreach ($aeCols as [$col, $def, $after]) {
        plantel_ensure_column($pdo, 'alumno_especialidades', $col, $def, $after);
    }
}

function operativo_cncm_seed_robotica(PDO $pdo): void
{
    $st = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE clave = ? LIMIT 1');
    $st->execute(['ROB']);
    if ($st->fetchColumn()) {
        return;
    }
    $pdo->prepare(
        'INSERT INTO especialidades (
            clave, nombre, descripcion, modalidad, duracion_fase_semanas,
            edad_min, costo_inscripcion, costo_inscripcion_apoyo, costo_inscripcion_referencia,
            costo_mensualidad, costo_mensualidad_apoyo, costo_mensualidad_referencia,
            costo_pronto_pago, costo_pronto_pago_apoyo, costo_pronto_pago_referencia,
            costo_semanal, costo_semanal_apoyo, costo_semanal_referencia,
            duracion_meses, es_fija, visible, activo, orden
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        'ROB', 'Robótica', 'Curso regular de robótica CNCM', 'regular', 4,
        13, 700, 700, 1400,
        1200, 1200, 1800,
        1100, 1100, 1650,
        350, 350, 525,
        12, 1, 1, 1, 15,
    ]);
}

function operativo_cncm_ocultar_temporales_vencidas(PDO $pdo): void
{
    $pdo->exec(
        "UPDATE especialidades SET visible = 0
         WHERE es_fija = 0 AND fecha_fin_venta IS NOT NULL AND fecha_fin_venta < CURDATE() AND visible = 1"
    );
}

function operativo_cncm_cartas_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS especialidad_tarifa_cartas (
            id_especialidad INT UNSIGNED NOT NULL,
            costo_inscripcion_ref DECIMAL(12,2) NOT NULL DEFAULT 0,
            costo_inscripcion_apoyo DECIMAL(12,2) NOT NULL DEFAULT 450,
            costo_mensualidad_ref DECIMAL(12,2) NOT NULL DEFAULT 0,
            costo_mensualidad_apoyo DECIMAL(12,2) NOT NULL DEFAULT 0,
            actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_especialidad)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS inscripcion_cartas_campana (
            id_campana INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            minimo_inscripcion DECIMAL(12,2) NOT NULL DEFAULT 450,
            vigente_desde DATE NOT NULL,
            vigente_hasta DATE NULL,
            id_gerente_definio INT UNSIGNED NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_campana),
            KEY idx_icc_plantel (id_plantel, vigente_desde)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS inscripcion_cartas_reparto (
            id_reparto INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_pago INT UNSIGNED NOT NULL,
            id_asesor INT UNSIGNED NOT NULL,
            rol ENUM(\'repartidor\',\'cierre\') NOT NULL,
            monto_comision DECIMAL(12,2) NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_reparto),
            KEY idx_icr_pago (id_pago)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function operativo_cncm_inscripcion_auth_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS inscripcion_autorizacion (
            id_auth INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_alumno INT UNSIGNED NULL,
            id_preregistro INT UNSIGNED NULL,
            id_grupo INT UNSIGNED NULL,
            id_especialidad INT UNSIGNED NULL,
            tipo ENUM(\'edad\',\'ubicacion\',\'ambos\') NOT NULL DEFAULT \'edad\',
            estado ENUM(\'pendiente\',\'aprobada\',\'rechazada\') NOT NULL DEFAULT \'pendiente\',
            motivo TEXT NULL,
            id_solicita INT UNSIGNED NOT NULL,
            id_autoriza INT UNSIGNED NULL,
            autorizado_en DATETIME NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_auth),
            KEY idx_ia_estado (id_plantel, estado),
            KEY idx_ia_alumno (id_alumno)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function operativo_cncm_pagos_campos(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }
    pago_ensure_schema($pdo);
    plantel_ensure_column($pdo, 'alumno_pagos', 'monto_referencia', 'DECIMAL(12,2) NULL', 'monto');
    plantel_ensure_column($pdo, 'alumno_pagos', 'monto_apoyo', 'DECIMAL(12,2) NULL', 'monto_referencia');
    plantel_ensure_column($pdo, 'alumno_pagos', 'etiqueta_apoyo', 'VARCHAR(120) NULL', 'monto_apoyo');
    plantel_ensure_column($pdo, 'alumno_pagos', 'cobro_precio_lista', 'TINYINT(1) NOT NULL DEFAULT 0', 'etiqueta_apoyo');
    plantel_ensure_column($pdo, 'alumno_pagos', 'medio_pago', "ENUM('efectivo','tarjeta_debito','tarjeta_credito','transferencia') NULL", 'forma_pago');
    plantel_ensure_column($pdo, 'alumno_pagos', 'origen_cartas', 'TINYINT(1) NOT NULL DEFAULT 0', 'cobro_precio_lista');
    plantel_ensure_column($pdo, 'alumno_pagos', 'comision_asesor_manual', 'DECIMAL(12,2) NULL', 'origen_cartas');
    plantel_ensure_column($pdo, 'alumno_pagos', 'comision_gerente_sobre', 'DECIMAL(12,2) NULL', 'comision_asesor_manual');
    plantel_ensure_column($pdo, 'alumno_pagos', 'excluir_tabulador', 'TINYINT(1) NOT NULL DEFAULT 0', 'comision_gerente_sobre');
    plantel_ensure_column($pdo, 'alumno_pagos', 'id_autoriza_director', 'INT UNSIGNED NULL', 'excluir_tabulador');
    plantel_ensure_column($pdo, 'alumno_pagos', 'motivo_descuento', 'VARCHAR(255) NULL', 'id_autoriza_director');

    plantel_ensure_column($pdo, 'ventas_movimiento', 'origen_cartas', 'TINYINT(1) NOT NULL DEFAULT 0', 'cuenta_tabulador');
    plantel_ensure_column($pdo, 'ventas_movimiento', 'excluir_tabulador', 'TINYINT(1) NOT NULL DEFAULT 0', 'origen_cartas');
    plantel_ensure_column($pdo, 'ventas_movimiento', 'comision_gerente_sobre', 'DECIMAL(12,2) NULL', 'comision_gerente');
}

/** Migra gerente (plantel) → director; crea roles director/coordinador/gerente ventas. */
function operativo_cncm_roles_migrar(PDO $pdo): void
{
    if (!function_exists('rbac_db_ensure_schema')) {
        return;
    }
    rbac_db_ensure_schema($pdo);

    if (hay_meta_get($pdo, 'cncm_roles_migrated_v1') === '1') {
        return;
    }

    $rolesNuevos = [
        ['director', 'Director de plantel', 'Administración operativa del plantel', 2],
        ['gerente', 'Gerente de ventas', 'Área comercial y comisiones', 3],
        ['coordinador', 'Coordinador académico', 'Jefe de maestros; calificaciones y planeaciones', 4],
    ];
    foreach ($rolesNuevos as [$clave, $nombre, $desc, $orden]) {
        $st = $pdo->prepare('SELECT id_rol FROM roles WHERE clave = ? LIMIT 1');
        $st->execute([$clave]);
        if (!$st->fetchColumn()) {
            $pdo->prepare(
                'INSERT INTO roles (clave, nombre, descripcion, acceso_total, es_sistema, activo, orden)
                 VALUES (?,?,?,0,1,1,?)'
            )->execute([$clave, $nombre, $desc, $orden]);
        }
    }

    $pdo->exec("UPDATE usuarios SET rol = 'director' WHERE rol = 'gerente'");

    if (function_exists('rbac_db_agregar_privilegios_rol')) {
        $capsDirector = [
            'menu_admin', 'menu_alumnos', 'menu_asistencia', 'menu_grupos', 'menu_especialidades',
            'menu_consulta_adeudo', 'menu_punto_venta', 'menu_venta_productos', 'menu_reportes',
            'menu_calendario', 'menu_calendario_consulta', 'menu_certificaciones', 'menu_preregistro',
            'menu_ventas', 'menu_caja', 'descuento_inscripcion_director', 'permiso_docente_aprobar_final',
            'inscripcion_autorizar_edad', 'inscripcion_autorizar_ubicacion',
        ];
        $capsGerente = [
            'menu_preregistro', 'menu_entrevistas', 'menu_grupos_fases', 'menu_cert_preregistro',
            'menu_ubicacion_asesor',
            'menu_reporte_inscritos', 'menu_comisiones_admin', 'menu_comisiones_consulta',
            'menu_gerente_escuelas', 'menu_reporte_escuelas', 'menu_reporte_presentados',
            'menu_ventas', 'cartas_definir_minimo', 'descuento_inscripcion_gerente',
            'menu_calendario_consulta',
        ];
        $capsCoord = [
            'menu_alumnos', 'menu_grupos', 'menu_especialidades', 'menu_asistencia',
            'calificaciones_editar_coordinacion', 'planeaciones_revisar', 'profesor_contratar',
            'permiso_docente_proponer', 'inscripcion_autorizar_edad', 'inscripcion_autorizar_ubicacion',
            'menu_academico', 'menu_mi_evaluacion', 'menu_matriz_entrenamiento',
        ];
        rbac_db_agregar_privilegios_rol($pdo, 'director', $capsDirector);
        rbac_db_agregar_privilegios_rol($pdo, 'gerente', $capsGerente);
        rbac_db_agregar_privilegios_rol($pdo, 'coordinador', $capsCoord);
        rbac_db_agregar_privilegios_rol($pdo, 'supervisor', ['catalogo_editar_costos', 'cartas_excepcion_minimo']);
        rbac_db_agregar_privilegios_rol($pdo, 'admin', [
            'inscripcion_solicitar_autorizacion', 'menu_caja', 'cobro_precio_lista', 'ticket_apoyo_educativo',
        ]);
        rbac_db_agregar_privilegios_rol($pdo, 'asesor', [
            'descuento_inscripcion_asesor', 'inscripcion_solicitar_autorizacion',
        ]);
    }

    hay_meta_set($pdo, 'cncm_roles_migrated_v1', '1');
}

/** @return array<string, float|null> */
function operativo_cncm_tarifas_especialidad(array $esp, bool $cartas = false): array
{
    if ($cartas) {
        return [
            'inscripcion_ref' => (float) ($esp['cartas_inscripcion_ref'] ?? $esp['costo_inscripcion_referencia'] ?? 0),
            'inscripcion_apoyo' => (float) ($esp['cartas_inscripcion_apoyo'] ?? 450),
            'mensualidad_ref' => (float) ($esp['cartas_mensualidad_ref'] ?? $esp['costo_mensualidad_referencia'] ?? 0),
            'mensualidad_apoyo' => (float) ($esp['cartas_mensualidad_apoyo'] ?? $esp['costo_mensualidad_apoyo'] ?? 0),
        ];
    }

    return [
        'inscripcion_ref' => (float) ($esp['costo_inscripcion_referencia'] ?? $esp['costo_inscripcion'] ?? 0),
        'inscripcion_apoyo' => (float) ($esp['costo_inscripcion_apoyo'] ?? $esp['costo_inscripcion'] ?? 0),
        'mensualidad_ref' => (float) ($esp['costo_mensualidad_referencia'] ?? $esp['costo_mensualidad'] ?? 0),
        'mensualidad_apoyo' => (float) ($esp['costo_mensualidad_apoyo'] ?? $esp['costo_mensualidad'] ?? 0),
        'pronto_ref' => (float) ($esp['costo_pronto_pago_referencia'] ?? $esp['costo_pronto_pago'] ?? 0),
        'pronto_apoyo' => (float) ($esp['costo_pronto_pago_apoyo'] ?? $esp['costo_pronto_pago'] ?? 0),
        'semanal_ref' => (float) ($esp['costo_semanal_referencia'] ?? $esp['costo_semanal'] ?? 0),
        'semanal_apoyo' => (float) ($esp['costo_semanal_apoyo'] ?? $esp['costo_semanal'] ?? 0),
    ];
}

function operativo_cncm_puede_editar_costos(): bool
{
    return function_exists('rbac_cap') && rbac_cap('catalogo_editar_costos');
}

function operativo_cncm_medio_pago_forma(string $medio): string
{
    return match ($medio) {
        'tarjeta_debito' => 'Tarjeta débito',
        'tarjeta_credito' => 'Tarjeta crédito',
        'transferencia' => 'Transferencia',
        default => 'Efectivo',
    };
}

/** Guarda tarifas promoción cartas por especialidad. */
function operativo_cncm_guardar_cartas(PDO $pdo, int $idEspecialidad, array $data): void
{
    operativo_cncm_ensure_schema($pdo);
    if ($idEspecialidad <= 0) {
        return;
    }
    $pdo->prepare(
        'INSERT INTO especialidad_tarifa_cartas (
            id_especialidad, costo_inscripcion_ref, costo_inscripcion_apoyo,
            costo_mensualidad_ref, costo_mensualidad_apoyo
        ) VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            costo_inscripcion_ref = VALUES(costo_inscripcion_ref),
            costo_inscripcion_apoyo = VALUES(costo_inscripcion_apoyo),
            costo_mensualidad_ref = VALUES(costo_mensualidad_ref),
            costo_mensualidad_apoyo = VALUES(costo_mensualidad_apoyo)'
    )->execute([
        $idEspecialidad,
        catalog_money($data['cartas_inscripcion_ref'] ?? 0),
        catalog_money($data['cartas_inscripcion_apoyo'] ?? 450),
        catalog_money($data['cartas_mensualidad_ref'] ?? 0),
        catalog_money($data['cartas_mensualidad_apoyo'] ?? 0),
    ]);
}

/** Persiste campos operativos CNCM en alumno_pagos tras INSERT. */
function operativo_cncm_cartas_minimo_vigente(PDO $pdo, int $idPlantel): float
{
    operativo_cncm_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT minimo_inscripcion FROM inscripcion_cartas_campana
         WHERE id_plantel = ? AND activo = 1 AND vigente_desde <= CURDATE()
           AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())
         ORDER BY vigente_desde DESC LIMIT 1'
    );
    $st->execute([$idPlantel]);
    $min = $st->fetchColumn();

    return $min !== false ? (float) $min : 450.0;
}

/** Resuelve asesor repartidor desde escuela de origen del pre-registro. */
function operativo_cncm_resolver_asesor_repartidor(PDO $pdo, ?int $idPreregistro): int
{
    if ($idPreregistro === null || $idPreregistro <= 0) {
        return 0;
    }
    if (function_exists('preregistro_ensure_schema')) {
        preregistro_ensure_schema($pdo);
    }
    $st = $pdo->prepare('SELECT id_escuela_origen FROM preregistros WHERE id_preregistro = ? LIMIT 1');
    $st->execute([$idPreregistro]);
    $idEscuela = (int) $st->fetchColumn();
    if ($idEscuela > 0 && function_exists('escuelas_ultimo_asesor_visita')) {
        return escuelas_ultimo_asesor_visita($pdo, $idEscuela);
    }

    return 0;
}

/**
 * Registra reparto cartas (repartidor + cierre) para un pago de inscripción.
 */
function operativo_cncm_registrar_cartas_reparto(
    PDO $pdo,
    int $idPago,
    int $idPlantel,
    int $idAsesorCierre,
    ?int $idAsesorRepartidor,
    float $montoPagado,
    float $comisionCierre = 150.0,
    float $comisionRepartidor = 100.0
): void {
    if ($idPago <= 0 || $idAsesorCierre <= 0) {
        return;
    }
    operativo_cncm_ensure_schema($pdo);
    $pdo->prepare('DELETE FROM inscripcion_cartas_reparto WHERE id_pago = ?')->execute([$idPago]);

    $minimo = operativo_cncm_cartas_minimo_vigente($pdo, $idPlantel);
    $extra = max(0.0, $montoPagado - $minimo);
    $montoCierre = catalog_money($comisionCierre) + catalog_money($extra);
    $montoRepartidor = catalog_money($comisionRepartidor);

    $ins = $pdo->prepare(
        'INSERT INTO inscripcion_cartas_reparto (id_pago, id_asesor, rol, monto_comision) VALUES (?,?,?,?)'
    );
    $ins->execute([$idPago, $idAsesorCierre, 'cierre', $montoCierre]);

    if ($idAsesorRepartidor > 0) {
        $ins->execute([$idPago, $idAsesorRepartidor, 'repartidor', $montoRepartidor]);
    }
}

function operativo_cncm_pago_aplicar_meta(PDO $pdo, int $idPago, array $data): void
{
    if ($idPago <= 0) {
        return;
    }
    operativo_cncm_ensure_schema($pdo);
    $pdo->prepare(
        'UPDATE alumno_pagos SET
            monto_referencia = ?, monto_apoyo = ?, etiqueta_apoyo = ?,
            cobro_precio_lista = ?, medio_pago = ?, origen_cartas = ?,
            comision_asesor_manual = ?, comision_gerente_sobre = ?,
            excluir_tabulador = ?, id_autoriza_director = ?
         WHERE id_pago = ?'
    )->execute([
        isset($data['monto_referencia']) ? catalog_money($data['monto_referencia']) : null,
        isset($data['monto_apoyo']) ? catalog_money($data['monto_apoyo']) : null,
        !empty($data['etiqueta_apoyo']) ? trim((string) $data['etiqueta_apoyo']) : null,
        !empty($data['cobro_precio_lista']) ? 1 : 0,
        !empty($data['medio_pago']) ? (string) $data['medio_pago'] : null,
        !empty($data['origen_cartas']) ? 1 : 0,
        isset($data['comision_asesor_manual']) ? catalog_money($data['comision_asesor_manual']) : null,
        isset($data['comision_gerente_sobre']) ? catalog_money($data['comision_gerente_sobre']) : null,
        !empty($data['excluir_tabulador']) ? 1 : 0,
        (int) ($data['id_autoriza_director'] ?? 0) ?: null,
        $idPago,
    ]);
}
