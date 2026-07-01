<?php

/**
 * Comisiones de ventas: tabulador de sueldo base, reglas por especialidad, overrides e historial.
 */

function ventas_comision_puede_editar(): bool
{
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
    return in_array($rol, ['supervisor', 'director', 'admin'], true);
}

/** Acceso a la pantalla de configuración (gerente: solo lectura). */
function ventas_comision_puede_administrar(): bool
{
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
    return in_array($rol, ['supervisor', 'gerente', 'director', 'admin'], true);
}

function ventas_comision_puede_consultar(): bool
{
    if (ventas_comision_puede_administrar()) {
        return true;
    }
    if (function_exists('rbac_usuario_en_roles') && rbac_usuario_en_roles(['asesor', 'gerente', 'supervisor', 'admin'])) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_comisiones_consulta');
}

function ventas_comision_ensure_schema(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }

    plantel_ensure_column($pdo, 'especialidades', 'ventas_comision_asesor', 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'referido_valor');
    plantel_ensure_column($pdo, 'especialidades', 'ventas_comision_gerente', 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'ventas_comision_asesor');
    plantel_ensure_column($pdo, 'especialidades', 'ventas_comision_asesor_pct', 'DECIMAL(5,2) NULL', 'ventas_comision_gerente');
    plantel_ensure_column($pdo, 'especialidades', 'ventas_comision_gerente_pct', 'DECIMAL(5,2) NULL', 'ventas_comision_asesor_pct');
    plantel_ensure_column($pdo, 'especialidades', 'ventas_cuenta_tabulador', 'TINYINT(1) NOT NULL DEFAULT 1', 'ventas_comision_gerente_pct');
    plantel_ensure_column($pdo, 'especialidades', 'ventas_tipo_comision', "ENUM('fija','pct_inscripcion','personalizado_pct') NOT NULL DEFAULT 'fija'", 'ventas_cuenta_tabulador');
    plantel_ensure_column($pdo, 'especialidades', 'es_plantilla_personalizado', 'TINYINT(1) NOT NULL DEFAULT 0', 'ventas_tipo_comision');
    plantel_ensure_column($pdo, 'grupos', 'personalizado_temas', 'TEXT NULL COMMENT \'JSON temas/fases personalizado\'', 'es_personalizado');
    plantel_ensure_column($pdo, 'grupos', 'personalizado_descripcion', 'VARCHAR(200) NULL', 'personalizado_temas');
    plantel_ensure_column($pdo, 'grupos', 'personalizado_costo_acordado', 'DECIMAL(10,2) NULL', 'personalizado_descripcion');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ventas_regla_especialidad_hist (
            id_hist INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_especialidad INT UNSIGNED NOT NULL,
            ventas_comision_asesor DECIMAL(12,2) NOT NULL DEFAULT 0,
            ventas_comision_gerente DECIMAL(12,2) NOT NULL DEFAULT 0,
            ventas_comision_asesor_pct DECIMAL(5,2) NULL,
            ventas_comision_gerente_pct DECIMAL(5,2) NULL,
            ventas_cuenta_tabulador TINYINT(1) NOT NULL DEFAULT 1,
            ventas_tipo_comision VARCHAR(20) NOT NULL DEFAULT \'fija\',
            id_usuario INT UNSIGNED NULL,
            motivo VARCHAR(255) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_hist),
            KEY idx_vreh_esp (id_especialidad, creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ventas_tabulador (
            id_tabulador INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            periodo ENUM(\'dia\',\'semana\',\'mes\') NOT NULL DEFAULT \'semana\',
            vigente_desde DATE NOT NULL,
            vigente_hasta DATE NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            notas TEXT NULL,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_tabulador),
            KEY idx_vtab_plantel (id_plantel, periodo, vigente_desde)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ventas_tabulador_tramo (
            id_tramo INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_tabulador INT UNSIGNED NOT NULL,
            min_inscripciones SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            max_inscripciones SMALLINT UNSIGNED NULL COMMENT \'NULL = sin tope\',
            monto_sueldo DECIMAL(12,2) NOT NULL DEFAULT 0,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id_tramo),
            KEY idx_vtt_tab (id_tabulador, orden)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ventas_override (
            id_override INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_usuario_asesor INT UNSIGNED NULL COMMENT \'NULL = todos los asesores\',
            fecha_desde DATE NOT NULL,
            fecha_hasta DATE NOT NULL,
            periodo ENUM(\'dia\',\'semana\',\'mes\') NOT NULL DEFAULT \'semana\',
            afecta ENUM(\'sueldo_base\',\'solo_comisiones\',\'ambos\') NOT NULL DEFAULT \'sueldo_base\',
            id_tabulador INT UNSIGNED NULL COMMENT \'Tabulador temporal de reemplazo\',
            motivo TEXT NULL,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_override),
            KEY idx_vov_plantel (id_plantel, fecha_desde, fecha_hasta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ventas_movimiento (
            id_movimiento INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_usuario_asesor INT UNSIGNED NOT NULL,
            tipo ENUM(\'inscripcion\',\'certificacion\',\'personalizado\') NOT NULL,
            id_alumno INT UNSIGNED NULL,
            id_especialidad INT UNSIGNED NULL,
            id_grupo INT UNSIGNED NULL,
            id_preregistro INT UNSIGNED NULL,
            id_pago INT UNSIGNED NULL,
            id_solicitud_cert INT UNSIGNED NULL,
            monto_base DECIMAL(12,2) NOT NULL DEFAULT 0,
            comision_asesor DECIMAL(12,2) NOT NULL DEFAULT 0,
            comision_gerente DECIMAL(12,2) NOT NULL DEFAULT 0,
            cuenta_tabulador TINYINT(1) NOT NULL DEFAULT 1,
            regla_snapshot JSON NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_movimiento),
            KEY idx_vm_asesor_fecha (id_usuario_asesor, creado_en),
            KEY idx_vm_plantel_fecha (id_plantel, creado_en),
            KEY idx_vm_tipo (tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    ventas_comision_seed_reglas_default($pdo);
}

/** Reglas por defecto para certificaciones y verano. */
function ventas_comision_seed_reglas_default(PDO $pdo): void
{
    $st = $pdo->query("SELECT id_especialidad, clave FROM especialidades WHERE clave IN ('VERANO','CERT')");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $clave = strtoupper((string) $e['clave']);
        $cuenta = $clave === 'VERANO' ? 0 : 0;
        $pdo->prepare(
            'UPDATE especialidades SET ventas_cuenta_tabulador = ?, ventas_tipo_comision = \'fija\'
             WHERE id_especialidad = ? AND ventas_comision_asesor = 0 AND ventas_comision_gerente = 0'
        )->execute([$cuenta, (int) $e['id_especialidad']]);
    }
    $pdo->exec(
        "UPDATE especialidades SET es_plantilla_personalizado = 1, ventas_tipo_comision = 'personalizado_pct',
         ventas_cuenta_tabulador = 0, ventas_comision_asesor_pct = 10
         WHERE clave = 'PERSONALIZADO' OR nombre LIKE '%personalizado%'"
    );
}

/** @return array<string, mixed>|null */
function ventas_regla_especialidad(PDO $pdo, int $idEspecialidad): ?array
{
    ventas_comision_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $st->execute([$idEspecialidad]);
    $r = $st->fetch(PDO::FETCH_ASSOC);

    return $r ?: null;
}

/** @return array{ok:bool,message:string} */
function ventas_regla_especialidad_guardar(PDO $pdo, int $idEspecialidad, array $data): array
{
    ventas_comision_ensure_schema($pdo);
    $ant = ventas_regla_especialidad($pdo, $idEspecialidad);
    if (!$ant) {
        return ['ok' => false, 'message' => 'Especialidad no encontrada'];
    }

    $tipo = (string) ($data['ventas_tipo_comision'] ?? 'fija');
    if (!in_array($tipo, ['fija', 'pct_inscripcion', 'personalizado_pct'], true)) {
        $tipo = 'fija';
    }

    $pdo->prepare(
        'INSERT INTO ventas_regla_especialidad_hist (
            id_especialidad, ventas_comision_asesor, ventas_comision_gerente,
            ventas_comision_asesor_pct, ventas_comision_gerente_pct,
            ventas_cuenta_tabulador, ventas_tipo_comision, id_usuario, motivo
        ) VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idEspecialidad,
        (float) ($ant['ventas_comision_asesor'] ?? 0),
        (float) ($ant['ventas_comision_gerente'] ?? 0),
        $ant['ventas_comision_asesor_pct'],
        $ant['ventas_comision_gerente_pct'],
        (int) ($ant['ventas_cuenta_tabulador'] ?? 1),
        (string) ($ant['ventas_tipo_comision'] ?? 'fija'),
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
        trim((string) ($data['motivo'] ?? 'Actualización de reglas')),
    ]);

    $pdo->prepare(
        'UPDATE especialidades SET
            ventas_comision_asesor = ?, ventas_comision_gerente = ?,
            ventas_comision_asesor_pct = ?, ventas_comision_gerente_pct = ?,
            ventas_cuenta_tabulador = ?, ventas_tipo_comision = ?,
            es_plantilla_personalizado = ?
         WHERE id_especialidad = ?'
    )->execute([
        catalog_money($data['ventas_comision_asesor'] ?? 0),
        catalog_money($data['ventas_comision_gerente'] ?? 0),
        ($data['ventas_comision_asesor_pct'] ?? '') !== '' ? (float) $data['ventas_comision_asesor_pct'] : null,
        ($data['ventas_comision_gerente_pct'] ?? '') !== '' ? (float) $data['ventas_comision_gerente_pct'] : null,
        !empty($data['ventas_cuenta_tabulador']) ? 1 : 0,
        $tipo,
        !empty($data['es_plantilla_personalizado']) ? 1 : 0,
        $idEspecialidad,
    ]);

    return ['ok' => true, 'message' => 'Reglas guardadas (historial registrado)'];
}

/**
 * Calcula comisiones según regla de especialidad / personalizado / certificación.
 *
 * @return array{comision_asesor:float,comision_gerente:float,cuenta_tabulador:int,regla:array}
 */
function ventas_calcular_comisiones(
    PDO $pdo,
    int $idEspecialidad,
    float $montoPagado,
    bool $esGrupoPersonalizado = false,
    array $opts = []
): array {
    $regla = ventas_regla_especialidad($pdo, $idEspecialidad);
    if (!$regla) {
        return ['comision_asesor' => 0.0, 'comision_gerente' => 0.0, 'cuenta_tabulador' => 1, 'regla' => []];
    }

    $cuenta = (int) ($opts['excluir_tabulador'] ?? 0) ? 0 : (int) ($regla['ventas_cuenta_tabulador'] ?? 1);
    $tipo = (string) ($regla['ventas_tipo_comision'] ?? 'fija');
    if ($esGrupoPersonalizado || (int) ($regla['es_plantilla_personalizado'] ?? 0)) {
        $tipo = 'personalizado_pct';
        $cuenta = 0;
    }

    $comA = 0.0;
    $comG = 0.0;
    if (!empty($opts['comision_asesor_manual'])) {
        $comA = catalog_money($opts['comision_asesor_manual']);
        $comG = catalog_money($opts['comision_gerente_sobre'] ?? 0);
        $cuenta = empty($opts['excluir_tabulador']) ? 1 : 0;
    } elseif (!empty($opts['origen_cartas'])) {
        $comA = catalog_money($opts['comision_cierre'] ?? 150);
        $comG = 0.0;
    } elseif ($tipo === 'personalizado_pct' || $tipo === 'pct_inscripcion') {
        $pctA = (float) ($regla['ventas_comision_asesor_pct'] ?? 10);
        $pctG = (float) ($regla['ventas_comision_gerente_pct'] ?? 0);
        if ($tipo === 'personalizado_pct' && $pctA <= 0) {
            $pctA = 10.0;
        }
        $comA = round($montoPagado * $pctA / 100, 2);
        $comG = $pctG > 0 ? round($montoPagado * $pctG / 100, 2) : catalog_money($regla['ventas_comision_gerente'] ?? 0);
    } else {
        $comA = catalog_money($regla['ventas_comision_asesor'] ?? 250);
        $comG = catalog_money($regla['ventas_comision_gerente'] ?? 0);
        if ($montoPagado > 0.5 && $montoPagado < 700 && empty($opts['director_autorizo'])) {
            if ($montoPagado <= 500 && function_exists('rbac_cap') && rbac_cap('descuento_inscripcion_asesor')) {
                $comA = max(0, $comA - 100);
            }
        }
    }

    return [
        'comision_asesor' => $comA,
        'comision_gerente' => $comG,
        'cuenta_tabulador' => $cuenta,
        'regla' => [
            'tipo' => $tipo,
            'id_especialidad' => $idEspecialidad,
            'nombre' => $regla['nombre'] ?? '',
        ],
    ];
}

function ventas_resolver_id_asesor(PDO $pdo, ?int $idPreregistro, ?int $idSolicitudCert = null): int
{
    if ($idPreregistro > 0 && function_exists('preregistro_id_asesor_comision')) {
        $id = preregistro_id_asesor_comision($pdo, $idPreregistro);
        return $id;
    }
    if ($idPreregistro > 0) {
        $st = $pdo->prepare('SELECT id_usuario_registro FROM preregistros WHERE id_preregistro = ? LIMIT 1');
        $st->execute([$idPreregistro]);
        $id = (int) $st->fetchColumn();
        if ($id > 0) {
            return $id;
        }
    }
    if ($idSolicitudCert > 0) {
        $st = $pdo->prepare(
            'SELECT COALESCE(id_usuario_asesor, id_usuario_registro) FROM certificacion_solicitudes WHERE id_solicitud = ? LIMIT 1'
        );
        $st->execute([$idSolicitudCert]);
        $id = (int) $st->fetchColumn();
        if ($id > 0) {
            return $id;
        }
    }

    return (int) ($_SESSION['user_id'] ?? 0);
}

/** @return array{ok:bool,message:string,id_movimiento?:int} */
function ventas_registrar_movimiento_inscripcion(
    PDO $pdo,
    int $idPlantel,
    int $idAlumno,
    int $idGrupo,
    float $montoPagado,
    ?int $idPago,
    ?int $idPreregistro,
    ?string $creadoEn = null
): array {
    ventas_comision_ensure_schema($pdo);
    $g = $pdo->prepare(
        'SELECT g.id_especialidad, g.es_personalizado, g.clave, e.nombre AS esp_nombre
         FROM grupos g LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_grupo = ? LIMIT 1'
    );
    $g->execute([$idGrupo]);
    $grupo = $g->fetch(PDO::FETCH_ASSOC);
    if (!$grupo) {
        return ['ok' => false, 'message' => 'Grupo no encontrado'];
    }

    $idEsp = (int) ($grupo['id_especialidad'] ?? 0);
    $esPer = (int) ($grupo['es_personalizado'] ?? 0) === 1;

    $pagoMeta = [];
    if ($idPago > 0) {
        $stP = $pdo->prepare(
            'SELECT origen_cartas, comision_asesor_manual, comision_gerente_sobre, excluir_tabulador
             FROM alumno_pagos WHERE id_pago = ? LIMIT 1'
        );
        $stP->execute([$idPago]);
        $pagoMeta = $stP->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $origenCartas = !empty($pagoMeta['origen_cartas']);
    if (!$origenCartas && $idPreregistro > 0) {
        $stPr = $pdo->prepare('SELECT medio_entero FROM preregistros WHERE id_preregistro = ? LIMIT 1');
        $stPr->execute([$idPreregistro]);
        $origenCartas = $stPr->fetchColumn() === 'cartas';
    }

    $calcOpts = [];
    if ($origenCartas) {
        $calcOpts['origen_cartas'] = true;
    }
    if (!empty($pagoMeta['comision_asesor_manual'])) {
        $calcOpts['comision_asesor_manual'] = $pagoMeta['comision_asesor_manual'];
        $calcOpts['comision_gerente_sobre'] = $pagoMeta['comision_gerente_sobre'] ?? 0;
        $calcOpts['excluir_tabulador'] = !empty($pagoMeta['excluir_tabulador']);
    }
    $calc = ventas_calcular_comisiones($pdo, $idEsp, $montoPagado, $esPer, $calcOpts);
    $idAsesor = ventas_resolver_id_asesor($pdo, $idPreregistro);
    if ($idAsesor < 0) {
        return ['ok' => false, 'message' => 'No se pudo identificar al asesor'];
    }

    $tipo = $esPer ? 'personalizado' : 'inscripcion';
    if ($idAsesor === 0) {
        $calc['comision_asesor'] = 0.0;
    }

    $pdo->prepare(
        'INSERT INTO ventas_movimiento (
            id_plantel, id_usuario_asesor, tipo, id_alumno, id_especialidad, id_grupo,
            id_preregistro, id_pago, monto_base, comision_asesor, comision_gerente,
            cuenta_tabulador, origen_cartas, excluir_tabulador, regla_snapshot
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idPlantel,
        $idAsesor,
        $tipo,
        $idAlumno,
        $idEsp ?: null,
        $idGrupo,
        $idPreregistro,
        $idPago,
        $montoPagado,
        $calc['comision_asesor'],
        $calc['comision_gerente'],
        $calc['cuenta_tabulador'],
        $origenCartas ? 1 : 0,
        !empty($pagoMeta['excluir_tabulador']) ? 1 : 0,
        json_encode($calc['regla'], JSON_UNESCAPED_UNICODE),
    ]);

    $idMov = (int) $pdo->lastInsertId();
    if ($creadoEn !== null && $creadoEn !== '' && $idMov > 0) {
        $pdo->prepare('UPDATE ventas_movimiento SET creado_en = ? WHERE id_movimiento = ?')
            ->execute([$creadoEn, $idMov]);
    }

    if ($origenCartas && $idPago > 0 && function_exists('operativo_cncm_registrar_cartas_reparto')) {
        $idRepartidor = function_exists('operativo_cncm_resolver_asesor_repartidor')
            ? operativo_cncm_resolver_asesor_repartidor($pdo, $idPreregistro)
            : 0;
        operativo_cncm_registrar_cartas_reparto(
            $pdo,
            $idPago,
            $idPlantel,
            $idAsesor,
            $idRepartidor > 0 ? $idRepartidor : null,
            $montoPagado
        );
    }

    return [
        'ok' => true,
        'message' => 'Movimiento registrado',
        'id_movimiento' => $idMov,
        'comision_asesor' => $calc['comision_asesor'],
        'comision_gerente' => $calc['comision_gerente'],
    ];
}

function ventas_movimiento_cert_existe(PDO $pdo, int $idSolicitud): bool
{
    ventas_comision_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT 1 FROM ventas_movimiento WHERE id_solicitud_cert = ? LIMIT 1');
    $st->execute([$idSolicitud]);

    return (bool) $st->fetchColumn();
}

function ventas_registrar_movimiento_certificacion(
    PDO $pdo,
    int $idPlantel,
    int $idSolicitud,
    int $idAlumno,
    int $idProducto,
    float $montoPagado,
    ?int $idPago = null
): array {
    ventas_comision_ensure_schema($pdo);
    if (ventas_movimiento_cert_existe($pdo, $idSolicitud)) {
        return ['ok' => true, 'message' => 'Movimiento ya registrado'];
    }
    $st = $pdo->prepare(
        'SELECT comision_asesor, comision_gerente, id_usuario_asesor, precio_cobrado
         FROM certificacion_solicitudes WHERE id_solicitud = ? LIMIT 1'
    );
    $st->execute([$idSolicitud]);
    $sol = $st->fetch(PDO::FETCH_ASSOC);
    if (!$sol) {
        return ['ok' => false, 'message' => 'Solicitud no encontrada'];
    }

    $comA = catalog_money($sol['comision_asesor'] ?? 0);
    $comG = catalog_money($sol['comision_gerente'] ?? 0);
    if ($comA <= 0 && $comG <= 0 && function_exists('comision_cert_defaults_producto')) {
        $def = comision_cert_defaults_producto($pdo, $idProducto);
        $comA = $def['comision_asesor'];
        $comG = $def['comision_gerente'];
    }

    $idAsesor = (int) ($sol['id_usuario_asesor'] ?? 0) ?: ventas_resolver_id_asesor($pdo, null, $idSolicitud);
    if ($idAsesor <= 0) {
        return ['ok' => false, 'message' => 'Asesor no identificado'];
    }

    $pdo->prepare(
        'INSERT INTO ventas_movimiento (
            id_plantel, id_usuario_asesor, tipo, id_alumno, id_especialidad, id_pago,
            id_solicitud_cert, monto_base, comision_asesor, comision_gerente, cuenta_tabulador, regla_snapshot
        ) VALUES (?,?,?,?,NULL,?,?,?,?,?,0,?)'
    )->execute([
        $idPlantel,
        $idAsesor,
        'certificacion',
        $idAlumno,
        $idPago,
        $idSolicitud,
        $montoPagado > 0 ? $montoPagado : catalog_money($sol['precio_cobrado'] ?? 0),
        $comA,
        $comG,
        json_encode(['tipo' => 'certificacion', 'id_producto' => $idProducto], JSON_UNESCAPED_UNICODE),
    ]);

    return ['ok' => true, 'id_movimiento' => (int) $pdo->lastInsertId()];
}

function ventas_periodo_rango(string $periodo, ?string $fechaRef = null): array
{
    $ref = $fechaRef ? new DateTimeImmutable($fechaRef) : new DateTimeImmutable('today');
    if ($periodo === 'dia') {
        $d = $ref->format('Y-m-d');
        return ['desde' => $d . ' 00:00:00', 'hasta' => $d . ' 23:59:59', 'label' => $d];
    }
    if ($periodo === 'mes') {
        $desde = $ref->modify('first day of this month')->format('Y-m-d') . ' 00:00:00';
        $hasta = $ref->modify('last day of this month')->format('Y-m-d') . ' 23:59:59';
        return ['desde' => $desde, 'hasta' => $hasta, 'label' => $ref->format('Y-m')];
    }
    $desde = $ref->modify('monday this week')->format('Y-m-d') . ' 00:00:00';
    $hasta = $ref->modify('sunday this week')->format('Y-m-d') . ' 23:59:59';
    return ['desde' => $desde, 'hasta' => $hasta, 'label' => 'Semana ' . $ref->format('W/Y')];
}

/** Tabulador vigente para fecha (considera override). */
function ventas_tabulador_para_fecha(PDO $pdo, int $idPlantel, int $idAsesor, string $periodo, string $fechaYmd): ?array
{
    ventas_comision_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT o.* FROM ventas_override o
         WHERE o.id_plantel = ? AND o.periodo = ? AND o.fecha_desde <= ? AND o.fecha_hasta >= ?
           AND (o.id_usuario_asesor IS NULL OR o.id_usuario_asesor = ?)
           AND o.afecta IN (\'sueldo_base\',\'ambos\')
           AND o.id_tabulador IS NOT NULL
         ORDER BY o.id_usuario_asesor DESC
         LIMIT 1'
    );
    $st->execute([$idPlantel, $periodo, $fechaYmd, $fechaYmd, $idAsesor]);
    $ov = $st->fetch(PDO::FETCH_ASSOC);
    if ($ov && !empty($ov['id_tabulador'])) {
        return ventas_tabulador_por_id($pdo, (int) $ov['id_tabulador']);
    }

    $st2 = $pdo->prepare(
        'SELECT * FROM ventas_tabulador
         WHERE id_plantel = ? AND periodo = ? AND activo = 1
           AND vigente_desde <= ? AND (vigente_hasta IS NULL OR vigente_hasta >= ?)
         ORDER BY vigente_desde DESC LIMIT 1'
    );
    $st2->execute([$idPlantel, $periodo, $fechaYmd, $fechaYmd]);

    return ventas_tabulador_por_id($pdo, (int) ($st2->fetchColumn() ?: 0)) ?: null;
}

function ventas_tabulador_por_id(PDO $pdo, int $idTabulador): ?array
{
    if ($idTabulador <= 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM ventas_tabulador WHERE id_tabulador = ? LIMIT 1');
    $st->execute([$idTabulador]);
    $tab = $st->fetch(PDO::FETCH_ASSOC);
    if (!$tab) {
        return null;
    }
    $tr = $pdo->prepare(
        'SELECT * FROM ventas_tabulador_tramo WHERE id_tabulador = ? ORDER BY orden, min_inscripciones'
    );
    $tr->execute([$idTabulador]);
    $tab['tramos'] = $tr->fetchAll(PDO::FETCH_ASSOC);

    return $tab;
}

function ventas_sueldo_base_desde_conteo(?array $tabulador, int $conteo): float
{
    if (!$tabulador || empty($tabulador['tramos'])) {
        return 0.0;
    }
    foreach ($tabulador['tramos'] as $t) {
        $min = (int) ($t['min_inscripciones'] ?? 0);
        $max = $t['max_inscripciones'] !== null ? (int) $t['max_inscripciones'] : null;
        if ($conteo >= $min && ($max === null || $conteo <= $max)) {
            return (float) ($t['monto_sueldo'] ?? 0);
        }
    }

    return 0.0;
}

/** Crea movimientos de comisión faltantes a partir de pagos de inscripción ya registrados. */
function ventas_sincronizar_movimientos_asesor(PDO $pdo, int $idPlantel, int $idAsesor): void
{
    ventas_comision_ensure_schema($pdo);
    if (function_exists('preregistro_ensure_schema')) {
        preregistro_ensure_schema($pdo);
    }
    if (function_exists('pago_ensure_schema')) {
        pago_ensure_schema($pdo);
    }

    $st = $pdo->prepare(
        "SELECT ap.id_pago, ap.id_alumno, ap.monto, ap.creado_en, ap.tipo,
                pr.id_preregistro,
                COALESCE(
                    (SELECT ag.id_grupo FROM alumno_grupos ag
                     WHERE ag.id_alumno = a.id_alumno AND ag.activo = 1
                     ORDER BY ag.creado_en DESC LIMIT 1),
                    a.id_grupo
                ) AS id_grupo
         FROM alumno_pagos ap
         INNER JOIN alumnos a ON a.id_alumno = ap.id_alumno AND a.id_plantel = ?
         INNER JOIN preregistros pr ON pr.id_alumno_vinculado = a.id_alumno
           AND COALESCE(
               CASE WHEN pr.comision_cncm = 1 THEN NULL ELSE pr.id_usuario_asesor END,
               (SELECT e2.id_usuario_asesor FROM asesor_entrevistas e2 WHERE e2.id_entrevista = pr.id_entrevista_origen LIMIT 1),
               pr.id_usuario_registro
           ) = ?
         WHERE ap.tipo IN ('inscripcion', 'otro')
           AND NOT EXISTS (SELECT 1 FROM ventas_movimiento vm WHERE vm.id_pago = ap.id_pago)
         ORDER BY ap.creado_en ASC"
    );
    $st->execute([$idPlantel, $idAsesor]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idGrupo = (int) ($row['id_grupo'] ?? 0);
        if ($idGrupo <= 0) {
            $gfb = $pdo->prepare(
                'SELECT g.id_grupo FROM grupos g
                 INNER JOIN alumno_pagos ap2 ON ap2.id_pago = ?
                 WHERE g.id_plantel = ? AND g.id_especialidad = ap2.id_especialidad
                 ORDER BY g.fecha_inicio DESC LIMIT 1'
            );
            $gfb->execute([(int) $row['id_pago'], $idPlantel]);
            $idGrupo = (int) $gfb->fetchColumn();
        }
        if ($idGrupo <= 0) {
            continue;
        }
        $monto = max((float) ($row['monto'] ?? 0), 0.01);
        try {
            ventas_registrar_movimiento_inscripcion(
                $pdo,
                $idPlantel,
                (int) $row['id_alumno'],
                $idGrupo,
                $monto,
                (int) $row['id_pago'],
                (int) ($row['id_preregistro'] ?? 0) ?: null,
                (string) ($row['creado_en'] ?? '')
            );
        } catch (Throwable $e) {
            error_log('ventas_sincronizar_movimiento: ' . $e->getMessage());
        }
    }

    $cert = $pdo->prepare(
        'SELECT cs.id_solicitud, cs.id_alumno, cs.id_producto, cs.precio_cobrado, cs.creado_en,
                COALESCE(cs.id_usuario_asesor, cs.id_usuario_registro) AS id_asesor
         FROM certificacion_solicitudes cs
         WHERE cs.id_plantel = ? AND COALESCE(cs.id_usuario_asesor, cs.id_usuario_registro) = ?
           AND NOT EXISTS (SELECT 1 FROM ventas_movimiento vm WHERE vm.id_solicitud_cert = cs.id_solicitud)'
    );
    $cert->execute([$idPlantel, $idAsesor]);
    foreach ($cert->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!function_exists('ventas_registrar_movimiento_certificacion')) {
            break;
        }
        try {
            $res = ventas_registrar_movimiento_certificacion(
                $pdo,
                $idPlantel,
                (int) $row['id_solicitud'],
                (int) $row['id_alumno'],
                (int) $row['id_producto'],
                (float) ($row['precio_cobrado'] ?? 0)
            );
            $idMov = (int) ($res['id_movimiento'] ?? 0);
            if ($idMov > 0 && !empty($row['creado_en'])) {
                $pdo->prepare('UPDATE ventas_movimiento SET creado_en = ? WHERE id_movimiento = ?')
                    ->execute([$row['creado_en'], $idMov]);
            }
        } catch (Throwable $e) {
            error_log('ventas_sincronizar_cert: ' . $e->getMessage());
        }
    }
}

/**
 * Liquidación de un asesor en un periodo.
 *
 * @return array<string, mixed>
 */
function ventas_liquidacion_asesor(PDO $pdo, int $idPlantel, int $idAsesor, string $periodo, ?string $fechaRef = null): array
{
    ventas_comision_ensure_schema($pdo);
    ventas_sincronizar_movimientos_asesor($pdo, $idPlantel, $idAsesor);
    $rango = ventas_periodo_rango($periodo, $fechaRef);
    $fechaYmd = substr($rango['desde'], 0, 10);

    $st = $pdo->prepare(
        'SELECT m.*, e.nombre AS esp_nombre, a.numero_control, g.clave AS grupo_clave,
                p.nombre AS cert_nombre
         FROM ventas_movimiento m
         LEFT JOIN especialidades e ON e.id_especialidad = m.id_especialidad
         LEFT JOIN alumnos a ON a.id_alumno = m.id_alumno
         LEFT JOIN grupos g ON g.id_grupo = m.id_grupo
         LEFT JOIN certificacion_solicitudes cs ON cs.id_solicitud = m.id_solicitud_cert
         LEFT JOIN productos p ON p.id_producto = cs.id_producto
         WHERE m.id_plantel = ? AND m.id_usuario_asesor = ?
           AND m.creado_en >= ? AND m.creado_en <= ?
         ORDER BY m.creado_en DESC'
    );
    $st->execute([$idPlantel, $idAsesor, $rango['desde'], $rango['hasta']]);
    $movs = $st->fetchAll(PDO::FETCH_ASSOC);

    $conteoTab = 0;
    $sumCom = 0.0;
    $porTipo = [
        'inscripcion' => ['ops' => 0, 'comision' => 0.0],
        'certificacion' => ['ops' => 0, 'comision' => 0.0],
        'personalizado' => ['ops' => 0, 'comision' => 0.0],
    ];
    foreach ($movs as $m) {
        $com = (float) ($m['comision_asesor'] ?? 0);
        $sumCom += $com;
        $tipo = (string) ($m['tipo'] ?? 'inscripcion');
        if (!isset($porTipo[$tipo])) {
            $porTipo[$tipo] = ['ops' => 0, 'comision' => 0.0];
        }
        $porTipo[$tipo]['ops']++;
        $porTipo[$tipo]['comision'] += $com;
        if ((int) ($m['cuenta_tabulador'] ?? 0) === 1 && in_array($tipo, ['inscripcion', 'personalizado'], true)) {
            $conteoTab++;
        }
    }
    foreach ($porTipo as $k => $v) {
        $porTipo[$k]['comision_fmt'] = catalog_format_mxn($v['comision']);
    }

    $tab = ventas_tabulador_para_fecha($pdo, $idPlantel, $idAsesor, $periodo, $fechaYmd);
    $sueldoBase = ventas_sueldo_base_desde_conteo($tab, $conteoTab);

    $u = $pdo->prepare('SELECT nombre, apellido FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $u->execute([$idAsesor]);
    $user = $u->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'periodo' => $periodo,
        'periodo_label' => $rango['label'],
        'asesor' => trim(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')),
        'conteo_tabulador' => $conteoTab,
        'sueldo_base' => $sueldoBase,
        'sueldo_base_fmt' => catalog_format_mxn($sueldoBase),
        'comisiones_total' => $sumCom,
        'comisiones_total_fmt' => catalog_format_mxn($sumCom),
        'total_estimado' => $sueldoBase + $sumCom,
        'total_estimado_fmt' => catalog_format_mxn($sueldoBase + $sumCom),
        'tabulador' => $tab,
        'movimientos' => $movs,
        'desglose_tipo' => $porTipo,
        'total_inscripciones' => (int) ($porTipo['inscripcion']['ops'] ?? 0) + (int) ($porTipo['personalizado']['ops'] ?? 0),
        'total_certificaciones' => (int) ($porTipo['certificacion']['ops'] ?? 0),
    ];
}

/** Sobrecomisiones gerente (suma de todos los asesores del plantel). */
function ventas_liquidacion_gerente(PDO $pdo, int $idPlantel, string $periodo, ?string $fechaRef = null): array
{
    ventas_comision_ensure_schema($pdo);
    $rango = ventas_periodo_rango($periodo, $fechaRef);

    $st = $pdo->prepare(
        'SELECT m.id_usuario_asesor, SUM(m.comision_gerente) AS total_gerente, COUNT(*) AS ops,
                CONCAT(u.nombre, \' \', u.apellido) AS asesor
         FROM ventas_movimiento m
         INNER JOIN usuarios u ON u.id_usuario = m.id_usuario_asesor
         WHERE m.id_plantel = ? AND m.creado_en >= ? AND m.creado_en <= ?
         GROUP BY m.id_usuario_asesor, u.nombre, u.apellido
         ORDER BY total_gerente DESC'
    );
    $st->execute([$idPlantel, $rango['desde'], $rango['hasta']]);
    $porAsesor = $st->fetchAll(PDO::FETCH_ASSOC);
    $total = 0.0;
    foreach ($porAsesor as $r) {
        $total += (float) ($r['total_gerente'] ?? 0);
    }

    return [
        'periodo_label' => $rango['label'],
        'total_sobrecomision' => $total,
        'total_fmt' => catalog_format_mxn($total),
        'por_asesor' => $porAsesor,
    ];
}

/** @return array{ok:bool,message:string,id_tabulador?:int} */
function ventas_tabulador_guardar(PDO $pdo, int $idPlantel, array $data): array
{
    ventas_comision_ensure_schema($pdo);
    $nombre = trim((string) ($data['nombre'] ?? 'Tabulador'));
    $periodo = (string) ($data['periodo'] ?? 'semana');
    if (!in_array($periodo, ['dia', 'semana', 'mes'], true)) {
        $periodo = 'semana';
    }
    $desde = trim((string) ($data['vigente_desde'] ?? date('Y-m-d')));
    $hasta = trim((string) ($data['vigente_hasta'] ?? '')) ?: null;
    $cerrarAnteriores = !empty($data['cerrar_anteriores']);

    if ($cerrarAnteriores) {
        $pdo->prepare(
            'UPDATE ventas_tabulador SET vigente_hasta = DATE_SUB(?, INTERVAL 1 DAY), activo = 0
             WHERE id_plantel = ? AND periodo = ? AND activo = 1 AND vigente_hasta IS NULL'
        )->execute([$desde, $idPlantel, $periodo]);
    }

    $pdo->prepare(
        'INSERT INTO ventas_tabulador (id_plantel, nombre, periodo, vigente_desde, vigente_hasta, activo, notas, id_usuario)
         VALUES (?,?,?,?,?,1,?,?)'
    )->execute([
        $idPlantel,
        $nombre,
        $periodo,
        $desde,
        $hasta,
        trim((string) ($data['notas'] ?? '')) ?: null,
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
    ]);
    $idTab = (int) $pdo->lastInsertId();

    $tramos = $data['tramos'] ?? [];
    if (is_string($tramos)) {
        $tramos = json_decode($tramos, true) ?: [];
    }
    $ins = $pdo->prepare(
        'INSERT INTO ventas_tabulador_tramo (id_tabulador, min_inscripciones, max_inscripciones, monto_sueldo, orden)
         VALUES (?,?,?,?,?)'
    );
    $orden = 0;
    foreach ($tramos as $t) {
        $ins->execute([
            $idTab,
            (int) ($t['min'] ?? $t['min_inscripciones'] ?? 0),
            isset($t['max']) && $t['max'] !== '' && $t['max'] !== null ? (int) $t['max'] : null,
            catalog_money($t['monto'] ?? $t['monto_sueldo'] ?? 0),
            $orden++,
        ]);
    }

    return ['ok' => true, 'message' => 'Tabulador guardado', 'id_tabulador' => $idTab];
}

/** @return array{ok:bool,message:string} */
function ventas_override_guardar(PDO $pdo, int $idPlantel, array $data): array
{
    ventas_comision_ensure_schema($pdo);
    $pdo->prepare(
        'INSERT INTO ventas_override (
            id_plantel, id_usuario_asesor, fecha_desde, fecha_hasta, periodo, afecta,
            id_tabulador, motivo, id_usuario
        ) VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idPlantel,
        !empty($data['id_usuario_asesor']) ? (int) $data['id_usuario_asesor'] : null,
        $data['fecha_desde'] ?? date('Y-m-d'),
        $data['fecha_hasta'] ?? date('Y-m-d'),
        $data['periodo'] ?? 'semana',
        $data['afecta'] ?? 'sueldo_base',
        !empty($data['id_tabulador']) ? (int) $data['id_tabulador'] : null,
        trim((string) ($data['motivo'] ?? '')),
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
    ]);

    return ['ok' => true, 'message' => 'Autorización especial registrada'];
}
