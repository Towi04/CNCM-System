<?php

/**
 * Nómina institucional: configuración de pago y liquidaciones por periodo.
 */

function nomina_ensure_schema(PDO $pdo): void
{
    usuario_ensure_schema($pdo);
    hay_eval_ensure_schema($pdo);
    ventas_comision_ensure_schema($pdo);
    asistencia_ensure_schema($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS personal_pago_config (
            id_config INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            tipo_pago ENUM(\'fijo_quincena\',\'fijo_mes\',\'por_hora\',\'tabulador_asesor\',\'nivel_hay\') NOT NULL DEFAULT \'fijo_quincena\',
            monto_fijo DECIMAL(12,2) NULL,
            tarifa_hora DECIMAL(12,2) NULL,
            id_hay_nivel INT UNSIGNED NULL,
            id_hay_area INT UNSIGNED NULL,
            notas VARCHAR(255) NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_config),
            UNIQUE KEY uq_ppc_usuario_plantel (id_usuario, id_plantel),
            KEY idx_ppc_plantel (id_plantel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'personal_pago_config', 'alcance', "ENUM('principal','docencia') NOT NULL DEFAULT 'principal'", 'id_plantel');
    }
    try {
        $pdo->exec('ALTER TABLE personal_pago_config DROP INDEX uq_ppc_usuario_plantel');
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec(
            'ALTER TABLE personal_pago_config ADD UNIQUE KEY uq_ppc_usuario_plantel_alcance (id_usuario, id_plantel, alcance)'
        );
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec('ALTER TABLE personal_pago_config DROP INDEX uq_ppc_usuario_plantel_alcance');
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec(
            'ALTER TABLE personal_pago_config ADD UNIQUE KEY uq_ppc_usuario_plantel_alcance_area (id_usuario, id_plantel, alcance, id_hay_area)'
        );
    } catch (Throwable $e) {
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS nomina_liquidacion (
            id_liquidacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            tipo_periodo ENUM(\'semana\',\'quincena\',\'mes\') NOT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NOT NULL,
            etiqueta VARCHAR(120) NULL,
            estado ENUM(\'borrador\',\'cerrada\') NOT NULL DEFAULT \'borrador\',
            total DECIMAL(14,2) NOT NULL DEFAULT 0,
            creado_por INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_liquidacion),
            UNIQUE KEY uq_nomina_periodo (id_plantel, tipo_periodo, fecha_inicio, fecha_fin),
            KEY idx_nomina_plantel (id_plantel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS nomina_linea (
            id_linea INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_liquidacion INT UNSIGNED NOT NULL,
            id_usuario INT UNSIGNED NOT NULL,
            rol VARCHAR(40) NULL,
            area_nombre VARCHAR(80) NULL,
            nivel_nombre VARCHAR(80) NULL,
            tipo_pago VARCHAR(40) NULL,
            concepto VARCHAR(255) NOT NULL,
            cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
            tarifa DECIMAL(12,2) NOT NULL DEFAULT 0,
            importe DECIMAL(12,2) NOT NULL DEFAULT 0,
            detalle_json JSON NULL,
            PRIMARY KEY (id_linea),
            KEY idx_nl_liquidacion (id_liquidacion),
            KEY idx_nl_usuario (id_usuario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function nomina_puede_gestionar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('menu_nomina_gestionar')) {
        return true;
    }
    return in_array(rbac_rol_efectivo(), ['director', 'supervisor'], true);
}

function nomina_puede_ajustar_manual(): bool
{
    return rbac_rol_efectivo() === 'director' || (function_exists('rbac_rol_real') && rbac_rol_real() === 'director');
}

function nomina_puede_ver_ajustes(): bool
{
    return nomina_puede_gestionar();
}

/** @return list<string> */
function nomina_roles_personal(): array
{
    return ['profesor', 'asesor', 'admin', 'coordinador', 'gerente', 'director', 'supervisor'];
}

/** @return array<string, string> */
function nomina_tipos_pago_labels(): array
{
    return [
        'fijo_quincena' => 'Salario fijo por quincena',
        'fijo_mes' => 'Salario fijo mensual',
        'por_hora' => 'Por hora de clase (profesor)',
        'tabulador_asesor' => 'Tabulador ventas (asesor)',
        'nivel_hay' => 'Según nivel HAY del área',
    ];
}

/** @return array{desde: string, hasta: string, etiqueta: string} */
function nomina_rango_periodo(string $tipo, ?string $fechaRef = null): array
{
    $ref = new DateTimeImmutable(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fechaRef) ? $fechaRef : date('Y-m-d'));
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    if ($tipo === 'semana') {
        $dow = (int) $ref->format('w');
        $desde = $ref->modify('-' . $dow . ' days');
        $hasta = $desde->modify('+6 days');
        $etiqueta = 'Semana ' . $desde->format('d/m') . ' – ' . $hasta->format('d/m/Y');
    } elseif ($tipo === 'quincena') {
        $day = (int) $ref->format('j');
        if ($day <= 15) {
            $desde = $ref->modify('first day of this month');
            $hasta = new DateTimeImmutable($ref->format('Y-m-15'));
            $etiqueta = '1ª quincena ' . $meses[(int) $ref->format('n')] . ' ' . $ref->format('Y');
        } else {
            $desde = new DateTimeImmutable($ref->format('Y-m-16'));
            $hasta = $ref->modify('last day of this month');
            $etiqueta = '2ª quincena ' . $meses[(int) $ref->format('n')] . ' ' . $ref->format('Y');
        }
    } else {
        $desde = $ref->modify('first day of this month');
        $hasta = $ref->modify('last day of this month');
        $etiqueta = ucfirst($meses[(int) $ref->format('n')]) . ' ' . $ref->format('Y');
    }

    return [
        'desde' => $desde->format('Y-m-d'),
        'hasta' => $hasta->format('Y-m-d'),
        'etiqueta' => $etiqueta,
    ];
}

/** @return array<string, mixed> */
function nomina_config_docencia_default(int $idHayArea = 0): array
{
    return [
        'tipo_pago' => 'por_hora',
        'monto_fijo' => null,
        'tarifa_hora' => 100.0,
        'id_hay_nivel' => null,
        'id_hay_area' => $idHayArea ?: null,
        'notas' => 'Docencia adicional — horas impartidas en el periodo',
    ];
}

/** @return list<array<string,mixed>> */
function nomina_configs_docencia_usuario(PDO $pdo, int $idPlantel, int $idUsuario, int $idHayAreaFallback = 0): array
{
    nomina_ensure_schema($pdo);
    $areas = function_exists('hay_eval_areas_usuario') ? hay_eval_areas_usuario($pdo, $idUsuario) : [];
    if (!$areas) {
        $st = $pdo->prepare(
            'SELECT * FROM personal_pago_config WHERE id_usuario = ? AND id_plantel = ? AND alcance = \'docencia\' ORDER BY id_config'
        );
        $st->execute([$idUsuario, $idPlantel]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            return array_map(static fn ($r) => nomina_config_desde_fila($r), $rows);
        }

        return [nomina_config_docencia_default($idHayAreaFallback)];
    }

    $configs = [];
    foreach ($areas as $a) {
        $idArea = (int) $a['id_area'];
        $st = $pdo->prepare(
            'SELECT * FROM personal_pago_config
             WHERE id_usuario = ? AND id_plantel = ? AND alcance = \'docencia\' AND id_hay_area = ? LIMIT 1'
        );
        $st->execute([$idUsuario, $idPlantel, $idArea]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $configs[] = nomina_config_desde_fila($row);
        } else {
            $def = nomina_config_docencia_default($idArea);
            $def['id_hay_area'] = $idArea;
            $def['area_nombre'] = (string) ($a['nombre'] ?? '');
            $configs[] = $def;
        }
    }

    return $configs;
}

/** @return list<array<string, mixed>> */
function nomina_personal_plantel(PDO $pdo, int $idPlantel): array
{
    nomina_ensure_schema($pdo);
    $roles = nomina_roles_personal();
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $params = array_merge([$idPlantel, $idPlantel, $idPlantel], $roles);
    $st = $pdo->prepare(
        "SELECT u.id_usuario, u.nombre, u.apellido, u.rol, u.id_hay_area,
                ppc.id_config, ppc.tipo_pago, ppc.monto_fijo, ppc.tarifa_hora,
                ppc.id_hay_nivel, ppc.id_hay_area AS cfg_hay_area, ppc.notas AS cfg_notas,
                ppcd.id_config AS id_config_doc, ppcd.tipo_pago AS tipo_pago_doc,
                ppcd.monto_fijo AS monto_fijo_doc, ppcd.tarifa_hora AS tarifa_hora_doc,
                ppcd.id_hay_nivel AS id_hay_nivel_doc, ppcd.id_hay_area AS cfg_hay_area_doc,
                ppcd.notas AS cfg_notas_doc
         FROM usuarios u
         LEFT JOIN personal_pago_config ppc
           ON ppc.id_usuario = u.id_usuario AND ppc.id_plantel = ? AND ppc.alcance = 'principal'
         LEFT JOIN personal_pago_config ppcd
           ON ppcd.id_usuario = u.id_usuario AND ppcd.id_plantel = ? AND ppcd.alcance = 'docencia'
         WHERE (u.id_plantel = ? OR u.rol = 'supervisor')
           AND u.rol IN ($placeholders)
           AND COALESCE(u.suspendido, 0) = 0
         ORDER BY u.rol, u.nombre, u.apellido"
    );
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $cfg = nomina_config_desde_fila($r);
        $r['config'] = $cfg;
        $r['config_docencia'] = !empty($r['id_config_doc'])
            ? nomina_config_desde_fila([
                'id_config' => $r['id_config_doc'],
                'tipo_pago' => $r['tipo_pago_doc'],
                'monto_fijo' => $r['monto_fijo_doc'],
                'tarifa_hora' => $r['tarifa_hora_doc'],
                'id_hay_nivel' => $r['id_hay_nivel_doc'],
                'cfg_hay_area' => $r['cfg_hay_area_doc'],
                'cfg_notas' => $r['cfg_notas_doc'],
                'rol' => $r['rol'],
                'id_hay_area' => $r['id_hay_area'],
            ])
            : nomina_config_docencia_default((int) ($r['id_hay_area'] ?? 0));
        $r['tipo_pago_label'] = nomina_tipos_pago_labels()[$cfg['tipo_pago']] ?? $cfg['tipo_pago'];
        $r['nombre_completo'] = trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? ''));
    }
    unset($r);

    return $rows;
}

/** @param array<string, mixed> $row */
function nomina_config_desde_fila(array $row): array
{
    if (!empty($row['id_config'])) {
        return [
            'tipo_pago' => (string) ($row['tipo_pago'] ?? 'fijo_quincena'),
            'monto_fijo' => isset($row['monto_fijo']) ? (float) $row['monto_fijo'] : null,
            'tarifa_hora' => isset($row['tarifa_hora']) ? (float) $row['tarifa_hora'] : null,
            'id_hay_nivel' => (int) ($row['id_hay_nivel'] ?? 0) ?: null,
            'id_hay_area' => (int) ($row['cfg_hay_area'] ?? $row['id_hay_area'] ?? 0) ?: null,
            'notas' => $row['cfg_notas'] ?? null,
        ];
    }

    return nomina_config_default_por_rol((string) ($row['rol'] ?? ''), (int) ($row['id_hay_area'] ?? 0));
}

/** @return array<string, mixed> */
function nomina_config_default_por_rol(string $rol, int $idHayArea = 0): array
{
    return match ($rol) {
        'profesor' => ['tipo_pago' => 'por_hora', 'monto_fijo' => null, 'tarifa_hora' => 100.0, 'id_hay_nivel' => null, 'id_hay_area' => $idHayArea ?: null, 'notas' => 'Default — configure tarifa o nivel HAY'],
        'asesor' => ['tipo_pago' => 'tabulador_asesor', 'monto_fijo' => null, 'tarifa_hora' => null, 'id_hay_nivel' => null, 'id_hay_area' => $idHayArea ?: null, 'notas' => 'Tabulador ventas semanal'],
        'gerente' => ['tipo_pago' => 'fijo_quincena', 'monto_fijo' => 0.0, 'tarifa_hora' => null, 'id_hay_nivel' => null, 'id_hay_area' => $idHayArea ?: null, 'notas' => null],
        default => ['tipo_pago' => 'fijo_quincena', 'monto_fijo' => 0.0, 'tarifa_hora' => null, 'id_hay_nivel' => null, 'id_hay_area' => $idHayArea ?: null, 'notas' => null],
    };
}

/** @param array<string, mixed> $data */
function nomina_guardar_config(PDO $pdo, int $idPlantel, int $idUsuario, array $data): array
{
    nomina_ensure_schema($pdo);
    $tipos = array_keys(nomina_tipos_pago_labels());
    $tipo = (string) ($data['tipo_pago'] ?? 'fijo_quincena');
    if (!in_array($tipo, $tipos, true)) {
        return ['ok' => false, 'message' => 'Tipo de pago no válido'];
    }

    $alcance = (string) ($data['alcance'] ?? 'principal');
    if (!in_array($alcance, ['principal', 'docencia'], true)) {
        $alcance = 'principal';
    }
    if ($alcance === 'docencia') {
        $tipo = 'por_hora';
    }

    $idHayArea = !empty($data['id_hay_area']) ? (int) $data['id_hay_area'] : 0;
    if ($alcance === 'docencia' && $idHayArea <= 0 && function_exists('hay_eval_area_principal')) {
        $idHayArea = (int) (hay_eval_area_principal($pdo, $idUsuario) ?: 0);
    }

    $ex = $pdo->prepare(
        'SELECT id_config FROM personal_pago_config
         WHERE id_usuario = ? AND id_plantel = ? AND alcance = ? AND COALESCE(id_hay_area, 0) = ?'
    );
    $ex->execute([$idUsuario, $idPlantel, $alcance, $alcance === 'docencia' ? $idHayArea : 0]);
    $idCfg = (int) $ex->fetchColumn();

    $params = [
        $tipo,
        isset($data['monto_fijo']) && $data['monto_fijo'] !== '' ? (float) $data['monto_fijo'] : null,
        isset($data['tarifa_hora']) && $data['tarifa_hora'] !== '' ? (float) $data['tarifa_hora'] : null,
        !empty($data['id_hay_nivel']) ? (int) $data['id_hay_nivel'] : null,
        $alcance === 'docencia' && $idHayArea > 0 ? $idHayArea : (!empty($data['id_hay_area']) ? (int) $data['id_hay_area'] : null),
        trim((string) ($data['notas'] ?? '')) ?: null,
    ];

    if ($idCfg > 0) {
        $params[] = $idCfg;
        $pdo->prepare(
            'UPDATE personal_pago_config SET tipo_pago=?, monto_fijo=?, tarifa_hora=?, id_hay_nivel=?, id_hay_area=?, notas=?, activo=1
             WHERE id_config=?'
        )->execute($params);
    } else {
        $pdo->prepare(
            'INSERT INTO personal_pago_config (id_usuario, id_plantel, alcance, tipo_pago, monto_fijo, tarifa_hora, id_hay_nivel, id_hay_area, notas)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute(array_merge([$idUsuario, $idPlantel, $alcance], $params));
    }

    return ['ok' => true, 'message' => $alcance === 'docencia' ? 'Tarifa de docencia guardada' : 'Configuración guardada'];
}

/** @return array{horas: float, sesiones: int, grupos: list<string>} */
function nomina_profesor_horas_periodo(PDO $pdo, int $idProfesor, int $idPlantel, string $desde, string $hasta): array
{
    if (function_exists('suplencia_desglose_horas_profesor')) {
        suplencia_ensure_schema($pdo);
        $d = suplencia_desglose_horas_profesor($pdo, $idProfesor, $idPlantel, $desde, $hasta);

        return [
            'horas' => $d['horas_clase'],
            'sesiones' => $d['sesiones'],
            'grupos' => $d['grupos'],
        ];
    }

    asistencia_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT g.clave, gh.dia_semana, gh.hora_inicio, gh.hora_fin
         FROM grupos g
         INNER JOIN grupo_horarios gh ON gh.id_grupo = g.id_grupo AND gh.activo = 1
         WHERE g.id_profesor = ? AND g.id_plantel = ?'
    );
    $st->execute([$idProfesor, $idPlantel]);
    $horarios = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $totalHoras = 0.0;
    $sesiones = 0;
    $grupos = [];
    $ini = new DateTimeImmutable($desde);
    $fin = new DateTimeImmutable($hasta);
    $cursor = $ini;

    while ($cursor <= $fin) {
        $dow = (int) $cursor->format('w');
        foreach ($horarios as $h) {
            if ((int) ($h['dia_semana'] ?? -1) !== $dow) {
                continue;
            }
            $t1 = strtotime((string) $h['hora_inicio']);
            $t2 = strtotime((string) $h['hora_fin']);
            if ($t2 > $t1) {
                $totalHoras += ($t2 - $t1) / 3600;
                $sesiones++;
                $grupos[(string) ($h['clave'] ?? '')] = true;
            }
        }
        $cursor = $cursor->modify('+1 day');
    }

    return [
        'horas' => round($totalHoras, 2),
        'sesiones' => $sesiones,
        'grupos' => array_keys(array_filter($grupos)),
    ];
}

/** @return array{tarifa: float, nivel_nombre: string, area_nombre: string, sueldo_base: float|null} */
function nomina_resolver_nivel_hay(PDO $pdo, int $idUsuario, array $cfg): array
{
    $idArea = (int) ($cfg['id_hay_area'] ?? 0);
    $idNivel = (int) ($cfg['id_hay_nivel'] ?? 0);
    $areaNombre = '';
    $nivelNombre = '';
    $tarifa = (float) ($cfg['tarifa_hora'] ?? 0);
    $sueldoBase = null;

    if ($idNivel > 0) {
        $st = $pdo->prepare(
            'SELECT n.*, a.nombre AS area_nombre FROM hay_nivel_cargo n
             INNER JOIN hay_area a ON a.id_area = n.id_area WHERE n.id_nivel = ?'
        );
        $st->execute([$idNivel]);
        $nv = $st->fetch(PDO::FETCH_ASSOC);
        if ($nv) {
            $nivelNombre = (string) ($nv['nombre_display'] ?? '');
            $areaNombre = (string) ($nv['area_nombre'] ?? '');
            $sueldoBase = isset($nv['sueldo_base']) ? (float) $nv['sueldo_base'] : null;
            if ($tarifa <= 0 && $sueldoBase !== null && $sueldoBase > 0) {
                $tarifa = $sueldoBase;
            }
        }
    } elseif ($idArea > 0) {
        $st = $pdo->prepare('SELECT nombre FROM hay_area WHERE id_area = ?');
        $st->execute([$idArea]);
        $areaNombre = (string) ($st->fetchColumn() ?: '');
        $sb = hay_eval_sueldo_sugerido_usuario($pdo, $idUsuario, $idArea);
        if ($sb !== null) {
            $sueldoBase = $sb;
        }
    }

    return [
        'tarifa' => $tarifa,
        'nivel_nombre' => $nivelNombre,
        'area_nombre' => $areaNombre,
        'sueldo_base' => $sueldoBase,
    ];
}

/**
 * @return array{ok: bool, message: string, id_liquidacion?: int}
 */
function nomina_generar(PDO $pdo, int $idPlantel, string $tipoPeriodo, ?string $fechaRef, int $idUsuarioCrea): array
{
    nomina_ensure_schema($pdo);
    if (!in_array($tipoPeriodo, ['semana', 'quincena', 'mes'], true)) {
        return ['ok' => false, 'message' => 'Periodo no válido'];
    }

    $rango = nomina_rango_periodo($tipoPeriodo, $fechaRef);
    $personal = nomina_personal_plantel($pdo, $idPlantel);

    $ex = $pdo->prepare(
        'SELECT id_liquidacion, estado FROM nomina_liquidacion
         WHERE id_plantel = ? AND tipo_periodo = ? AND fecha_inicio = ? AND fecha_fin = ?'
    );
    $ex->execute([$idPlantel, $tipoPeriodo, $rango['desde'], $rango['hasta']]);
    $liqEx = $ex->fetch(PDO::FETCH_ASSOC);

    if ($liqEx && ($liqEx['estado'] ?? '') === 'cerrada') {
        return ['ok' => false, 'message' => 'La nómina de este periodo ya está cerrada'];
    }

    if ($liqEx) {
        $idLiq = (int) $liqEx['id_liquidacion'];
        suplencia_ensure_schema($pdo);
        $pdo->prepare('DELETE FROM nomina_linea WHERE id_liquidacion = ? AND COALESCE(es_manual, 0) = 0')->execute([$idLiq]);
        $pdo->prepare(
            'UPDATE nomina_liquidacion SET creado_por=?, creado_en=NOW(), etiqueta=?, estado=\'borrador\' WHERE id_liquidacion=?'
        )->execute([$idUsuarioCrea, $rango['etiqueta'], $idLiq]);
    } else {
        $pdo->prepare(
            'INSERT INTO nomina_liquidacion (id_plantel, tipo_periodo, fecha_inicio, fecha_fin, etiqueta, creado_por)
             VALUES (?,?,?,?,?,?)'
        )->execute([$idPlantel, $tipoPeriodo, $rango['desde'], $rango['hasta'], $rango['etiqueta'], $idUsuarioCrea]);
        $idLiq = (int) $pdo->lastInsertId();
    }

    $ins = $pdo->prepare(
        'INSERT INTO nomina_linea (id_liquidacion, id_usuario, rol, area_nombre, nivel_nombre, tipo_pago, concepto, cantidad, tarifa, importe, detalle_json, es_manual, origen)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?)'
    );

    $total = 0.0;
    $lineasCount = 0;

    foreach ($personal as $p) {
        $idUser = (int) $p['id_usuario'];
        $rol = (string) ($p['rol'] ?? '');
        $cfg = $p['config'] ?? nomina_config_default_por_rol($rol);
        $tipoPago = (string) ($cfg['tipo_pago'] ?? 'fijo_quincena');
        $lineas = nomina_calcular_lineas_usuario($pdo, $idPlantel, $idUser, $rol, $cfg, $tipoPeriodo, $rango);

        if ($tipoPago !== 'por_hora') {
            $configsDoc = nomina_configs_docencia_usuario(
                $pdo,
                $idPlantel,
                $idUser,
                (int) ($p['id_hay_area'] ?? 0)
            );
            foreach ($configsDoc as $cfgDoc) {
                $lineas = array_merge(
                    $lineas,
                    nomina_calcular_lineas_docencia($pdo, $idPlantel, $idUser, $cfgDoc, $tipoPeriodo, $rango)
                );
            }
        }

        foreach ($lineas as $ln) {
            $origen = (string) ($ln['origen'] ?? 'calculado');
            $ins->execute([
                $idLiq,
                $idUser,
                $rol,
                $ln['area_nombre'] ?? '',
                $ln['nivel_nombre'] ?? '',
                $ln['tipo_pago'] ?? $tipoPago,
                $ln['concepto'],
                $ln['cantidad'],
                $ln['tarifa'],
                $ln['importe'],
                !empty($ln['detalle']) ? json_encode($ln['detalle'], JSON_UNESCAPED_UNICODE) : null,
                $origen,
            ]);
            $total += (float) $ln['importe'];
            $lineasCount++;
        }
    }

    nomina_recalcular_total($pdo, $idLiq);
    $stTot = $pdo->prepare('SELECT total FROM nomina_liquidacion WHERE id_liquidacion = ?');
    $stTot->execute([$idLiq]);
    $total = (float) ($stTot->fetchColumn() ?: 0);

    if (function_exists('asesoria_nomina_importar')) {
        try {
            asesoria_nomina_importar($pdo, $idLiq, $idPlantel, $rango['desde'], $rango['hasta']);
            nomina_recalcular_total($pdo, $idLiq);
            $stTot->execute([$idLiq]);
            $total = (float) ($stTot->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('asesoria_nomina_importar: ' . $e->getMessage());
        }
    }

    return [
        'ok' => true,
        'message' => 'Nómina generada: ' . $lineasCount . ' conceptos · Total ' . catalog_format_mxn($total),
        'id_liquidacion' => $idLiq,
    ];
}

/**
 * Horas de docencia adicional para personal con nómina principal distinta a por_hora.
 *
 * @return list<array{concepto: string, cantidad: float, tarifa: float, importe: float, tipo_pago: string, area_nombre?: string, nivel_nombre?: string, detalle?: array}>
 */
function nomina_calcular_lineas_docencia(
    PDO $pdo,
    int $idPlantel,
    int $idUsuario,
    array $cfg,
    string $tipoPeriodo,
    array $rango
): array {
    if ($tipoPeriodo === 'mes') {
        return [];
    }
    $hrs = nomina_profesor_horas_periodo($pdo, $idUsuario, $idPlantel, $rango['desde'], $rango['hasta']);

    $nv = ['nivel_nombre' => '', 'area_nombre' => '', 'tarifa' => 0.0];
    $tarifa = (float) ($cfg['tarifa_hora'] ?? 0);
    if (!empty($cfg['id_hay_nivel']) || !empty($cfg['id_hay_area'])) {
        $nv = nomina_resolver_nivel_hay($pdo, $idUsuario, $cfg);
        if ($tarifa <= 0 && $nv['tarifa'] > 0) {
            $tarifa = $nv['tarifa'];
        }
    }
    if ($tarifa <= 0) {
        $tarifa = 100.0;
    }

    $lineas = [];
    if ($hrs['horas'] >= 0.01) {
        $importe = round($hrs['horas'] * $tarifa, 2);
        $lineas[] = [
            'concepto' => 'Docencia' . (!empty($nv['area_nombre']) ? ' (' . $nv['area_nombre'] . ')' : '')
                . ' — ' . $hrs['sesiones'] . ' sesiones',
            'cantidad' => $hrs['horas'],
            'tarifa' => $tarifa,
            'importe' => $importe,
            'tipo_pago' => 'por_hora',
            'nivel_nombre' => $nv['nivel_nombre'],
            'area_nombre' => $nv['area_nombre'],
            'detalle' => ['grupos' => $hrs['grupos'], 'horas' => $hrs['horas'], 'alcance' => 'docencia'],
            'origen' => 'calculado',
        ];
    }
    if (function_exists('suplencia_lineas_apoyo')) {
        $lineas = array_merge($lineas, suplencia_lineas_apoyo($pdo, $idUsuario, $idPlantel, $rango['desde'], $rango['hasta'], $tarifa));
    }

    return $lineas;
}

/**
 * @return list<array{concepto: string, cantidad: float, tarifa: float, importe: float, area_nombre?: string, nivel_nombre?: string, detalle?: array}>
 */
function nomina_calcular_lineas_usuario(
    PDO $pdo,
    int $idPlantel,
    int $idUsuario,
    string $rol,
    array $cfg,
    string $tipoPeriodo,
    array $rango
): array {
    $tipo = (string) ($cfg['tipo_pago'] ?? 'fijo_quincena');
    $lineas = [];
    $nv = ['nivel_nombre' => '', 'area_nombre' => '', 'tarifa' => 0.0, 'sueldo_base' => null];

    if ($tipo === 'por_hora') {
        $hrs = nomina_profesor_horas_periodo($pdo, $idUsuario, $idPlantel, $rango['desde'], $rango['hasta']);
        $tarifa = (float) ($cfg['tarifa_hora'] ?? 0);
        if (!empty($cfg['id_hay_nivel']) || !empty($cfg['id_hay_area'])) {
            $nv = nomina_resolver_nivel_hay($pdo, $idUsuario, $cfg);
            if ($tarifa <= 0 && $nv['tarifa'] > 0) {
                $tarifa = $nv['tarifa'];
            }
        }
        if ($tarifa <= 0) {
            $tarifa = 100.0;
        }
        $importe = round($hrs['horas'] * $tarifa, 2);
        if ($importe > 0 || $hrs['horas'] > 0) {
            $lineas[] = [
                'concepto' => 'Horas de clase (' . $hrs['sesiones'] . ' sesiones)',
                'cantidad' => $hrs['horas'],
                'tarifa' => $tarifa,
                'importe' => $importe,
                'nivel_nombre' => $nv['nivel_nombre'],
                'area_nombre' => $nv['area_nombre'],
                'detalle' => ['grupos' => $hrs['grupos'], 'horas' => $hrs['horas']],
                'origen' => 'calculado',
            ];
        }
        if (function_exists('suplencia_lineas_apoyo')) {
            $lineas = array_merge($lineas, suplencia_lineas_apoyo($pdo, $idUsuario, $idPlantel, $rango['desde'], $rango['hasta'], $tarifa));
        }

        return $lineas;
    }

    if ($tipo === 'tabulador_asesor' && $rol === 'asesor') {
        $periodoVentas = $tipoPeriodo === 'mes' ? 'mes' : 'semana';
        $liq = ventas_liquidacion_asesor($pdo, $idPlantel, $idUsuario, $periodoVentas, $rango['hasta']);
        $lineas[] = [
            'concepto' => 'Sueldo base tabulador (' . (int) ($liq['conteo_tabulador'] ?? 0) . ' inscripciones)',
            'cantidad' => 1,
            'tarifa' => (float) ($liq['sueldo_base'] ?? 0),
            'importe' => (float) ($liq['sueldo_base'] ?? 0),
            'detalle' => ['conteo' => $liq['conteo_tabulador'] ?? 0],
        ];
        if ((float) ($liq['comisiones_total'] ?? 0) > 0) {
            $lineas[] = [
                'concepto' => 'Comisiones ventas',
                'cantidad' => 1,
                'tarifa' => (float) $liq['comisiones_total'],
                'importe' => (float) $liq['comisiones_total'],
                'detalle' => $liq['desglose_tipo'] ?? [],
            ];
        }

        return $lineas;
    }

    if ($tipo === 'nivel_hay') {
        $nv = nomina_resolver_nivel_hay($pdo, $idUsuario, $cfg);
        $monto = (float) ($nv['sueldo_base'] ?? 0);
        if ($tipoPeriodo === 'quincena') {
            $monto = round($monto / 2, 2);
        } elseif ($tipoPeriodo === 'semana') {
            $monto = round($monto / 4, 2);
        }
        if ($monto > 0) {
            $lineas[] = [
                'concepto' => 'Sueldo nivel HAY' . ($nv['nivel_nombre'] ? ' — ' . $nv['nivel_nombre'] : ''),
                'cantidad' => 1,
                'tarifa' => $monto,
                'importe' => $monto,
                'area_nombre' => $nv['area_nombre'],
                'nivel_nombre' => $nv['nivel_nombre'],
            ];
        }

        return $lineas;
    }

    $monto = (float) ($cfg['monto_fijo'] ?? 0);
    if ($tipo === 'fijo_quincena' && $tipoPeriodo === 'mes') {
        $monto = round($monto * 2, 2);
    }
    if ($tipo === 'fijo_mes' && $tipoPeriodo === 'quincena') {
        $monto = round($monto / 2, 2);
    }
    if ($tipo === 'fijo_mes' && $tipoPeriodo === 'semana') {
        $monto = round($monto / 4, 2);
    }
    if ($monto > 0) {
        $lineas[] = [
            'concepto' => nomina_tipos_pago_labels()[$tipo] ?? 'Salario',
            'cantidad' => 1,
            'tarifa' => $monto,
            'importe' => $monto,
        ];
    }

    return $lineas;
}

/** @return array<string, mixed>|null */
function nomina_obtener(PDO $pdo, int $idLiquidacion, int $idPlantel): ?array
{
    nomina_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM nomina_liquidacion WHERE id_liquidacion = ? AND id_plantel = ?');
    $st->execute([$idLiquidacion, $idPlantel]);
    $liq = $st->fetch(PDO::FETCH_ASSOC);
    if (!$liq) {
        return null;
    }

    $stL = $pdo->prepare(
        'SELECT nl.*, CONCAT(u.nombre, \' \', u.apellido) AS nombre_completo
         FROM nomina_linea nl
         INNER JOIN usuarios u ON u.id_usuario = nl.id_usuario
         WHERE nl.id_liquidacion = ?
         ORDER BY nl.rol, u.nombre, nl.concepto'
    );
    $stL->execute([$idLiquidacion]);
    $lineas = $stL->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $porUsuario = [];
    foreach ($lineas as $ln) {
        $uid = (int) $ln['id_usuario'];
        if (!isset($porUsuario[$uid])) {
            $porUsuario[$uid] = [
                'id_usuario' => $uid,
                'nombre' => $ln['nombre_completo'] ?? '',
                'rol' => $ln['rol'] ?? '',
                'tipo_pago' => $ln['tipo_pago'] ?? '',
                'subtotal' => 0.0,
                'lineas' => [],
            ];
        }
        $porUsuario[$uid]['lineas'][] = $ln;
        $porUsuario[$uid]['subtotal'] += (float) ($ln['importe'] ?? 0);
    }

    $liq['lineas'] = $lineas;
    $liq['por_usuario'] = array_values($porUsuario);
    $liq['total_fmt'] = catalog_format_mxn((float) ($liq['total'] ?? 0));
    $liq['ajustes'] = nomina_ajustes_listar($pdo, $idLiquidacion);

    return $liq;
}

/** @return list<array<string, mixed>> */
function nomina_listar(PDO $pdo, int $idPlantel, int $limite = 24): array
{
    nomina_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT l.*, CONCAT(u.nombre, \' \', u.apellido) AS creado_nombre
         FROM nomina_liquidacion l
         LEFT JOIN usuarios u ON u.id_usuario = l.creado_por
         WHERE l.id_plantel = ?
         ORDER BY l.fecha_inicio DESC, l.id_liquidacion DESC
         LIMIT ' . max(1, min(48, $limite))
    );
    $st->execute([$idPlantel]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['total_fmt'] = catalog_format_mxn((float) ($r['total'] ?? 0));
    }
    unset($r);

    return $rows;
}

function nomina_cerrar(PDO $pdo, int $idPlantel, int $idLiquidacion): array
{
    $liq = nomina_obtener($pdo, $idLiquidacion, $idPlantel);
    if (!$liq) {
        return ['ok' => false, 'message' => 'Liquidación no encontrada'];
    }
    if (($liq['estado'] ?? '') === 'cerrada') {
        return ['ok' => false, 'message' => 'Ya estaba cerrada'];
    }
    $pdo->prepare('UPDATE nomina_liquidacion SET estado = \'cerrada\' WHERE id_liquidacion = ?')->execute([$idLiquidacion]);

    return ['ok' => true, 'message' => 'Nómina cerrada'];
}

/** @return array<string, mixed> */
function nomina_catalogo(PDO $pdo): array
{
    nomina_ensure_schema($pdo);
    $areas = hay_eval_listar_areas($pdo);
    $nivelesPorArea = [];
    foreach ($areas as $a) {
        $idArea = (int) ($a['id_area'] ?? 0);
        $nivelesPorArea[$idArea] = hay_eval_listar_niveles($pdo, $idArea);
    }

    return [
        'tipos_pago' => nomina_tipos_pago_labels(),
        'areas' => $areas,
        'niveles_por_area' => $nivelesPorArea,
    ];
}

/** @param list<array<string, mixed>> $filas */
function nomina_enviar_csv(array $filas, array $columnas, string $filename): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, array_values($columnas));
    foreach ($filas as $f) {
        $row = [];
        foreach (array_keys($columnas) as $k) {
            $row[] = $f[$k] ?? '';
        }
        fputcsv($out, $row);
    }
    fclose($out);
}

function nomina_recalcular_total(PDO $pdo, int $idLiquidacion): void
{
    suplencia_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT COALESCE(SUM(importe),0) FROM nomina_linea WHERE id_liquidacion = ?');
    $st->execute([$idLiquidacion]);
    $total = round((float) $st->fetchColumn(), 2);
    $pdo->prepare('UPDATE nomina_liquidacion SET total = ? WHERE id_liquidacion = ?')->execute([$total, $idLiquidacion]);
}

/** @return list<array<string, mixed>> */
function nomina_ajustes_listar(PDO $pdo, int $idLiquidacion): array
{
    suplencia_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT l.*, CONCAT(u.nombre, \' \', u.apellido) AS editor_nombre,
                CONCAT(ua.nombre, \' \', ua.apellido) AS afectado_nombre
         FROM nomina_ajuste_log l
         INNER JOIN usuarios u ON u.id_usuario = l.id_usuario_editor
         INNER JOIN usuarios ua ON ua.id_usuario = l.id_usuario_afectado
         WHERE l.id_liquidacion = ?
         ORDER BY l.creado_en DESC'
    );
    $st->execute([$idLiquidacion]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function nomina_liquidacion_editable(PDO $pdo, int $idLiquidacion, int $idPlantel): ?array
{
    $st = $pdo->prepare('SELECT * FROM nomina_liquidacion WHERE id_liquidacion = ? AND id_plantel = ?');
    $st->execute([$idLiquidacion, $idPlantel]);
    $liq = $st->fetch(PDO::FETCH_ASSOC);
    if (!$liq || ($liq['estado'] ?? '') === 'cerrada') {
        return null;
    }

    return $liq;
}

function nomina_notificar_supervisores_ajuste(
    PDO $pdo,
    int $idPlantel,
    int $idLiquidacion,
    string $titulo,
    string $mensaje
): void {
    if (!function_exists('academico_notificar_usuario')) {
        return;
    }
    $st = $pdo->prepare(
        "SELECT id_usuario FROM usuarios WHERE rol = 'supervisor' AND suspendido = 0
         AND (id_plantel = ? OR id_plantel IS NULL OR id_plantel = 0)"
    );
    $st->execute([$idPlantel]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $idSup) {
        academico_notificar_usuario(
            $pdo,
            (int) $idSup,
            'nomina_ajuste',
            $titulo,
            $mensaje,
            'director_nomina',
            'id_liquidacion=' . $idLiquidacion
        );
    }
}

function nomina_registrar_ajuste_log(
    PDO $pdo,
    int $idLiquidacion,
    ?int $idLinea,
    int $idUsuarioAfectado,
    string $accion,
    ?string $conceptoAntes,
    ?float $importeAntes,
    ?string $conceptoDespues,
    ?float $importeDespues,
    string $observacion,
    int $idEditor
): void {
    suplencia_ensure_schema($pdo);
    $pdo->prepare(
        'INSERT INTO nomina_ajuste_log (id_liquidacion, id_linea, id_usuario_afectado, accion, concepto_antes, importe_antes, concepto_despues, importe_despues, observacion, id_usuario_editor)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idLiquidacion, $idLinea, $idUsuarioAfectado, $accion,
        $conceptoAntes, $importeAntes, $conceptoDespues, $importeDespues,
        $observacion, $idEditor,
    ]);
}

/** @param array<string, mixed> $data */
function nomina_linea_agregar_manual(PDO $pdo, int $idPlantel, int $idLiquidacion, array $data, int $idEditor): array
{
    if (!nomina_puede_ajustar_manual()) {
        return ['ok' => false, 'message' => 'Solo el director puede ajustar la nómina manualmente'];
    }
    $liq = nomina_liquidacion_editable($pdo, $idLiquidacion, $idPlantel);
    if (!$liq) {
        return ['ok' => false, 'message' => 'Liquidación no editable (cerrada o no encontrada)'];
    }

    $idUsuario = (int) ($data['id_usuario'] ?? 0);
    $concepto = trim((string) ($data['concepto'] ?? ''));
    $obs = trim((string) ($data['observacion'] ?? ''));
    $cantidad = (float) ($data['cantidad'] ?? 1);
    $tarifa = (float) ($data['tarifa'] ?? 0);
    $importe = isset($data['importe']) && $data['importe'] !== ''
        ? (float) $data['importe'] : round($cantidad * $tarifa, 2);

    if ($idUsuario <= 0 || $concepto === '' || $obs === '') {
        return ['ok' => false, 'message' => 'Persona, concepto y observación interna son obligatorios'];
    }

    $stU = $pdo->prepare('SELECT rol FROM usuarios WHERE id_usuario = ?');
    $stU->execute([$idUsuario]);
    $rol = (string) ($stU->fetchColumn() ?: '');

    $pdo->prepare(
        'INSERT INTO nomina_linea (id_liquidacion, id_usuario, rol, tipo_pago, concepto, cantidad, tarifa, importe, es_manual, observacion_interna, origen)
         VALUES (?,?,?,?,?,?,?,?,1,?,?)'
    )->execute([$idLiquidacion, $idUsuario, $rol, 'ajuste_manual', $concepto, $cantidad, $tarifa, $importe, $obs, 'manual']);

    $idLinea = (int) $pdo->lastInsertId();
    nomina_registrar_ajuste_log($pdo, $idLiquidacion, $idLinea, $idUsuario, 'agregar', null, null, $concepto, $importe, $obs, $idEditor);
    nomina_recalcular_total($pdo, $idLiquidacion);

    $stN = $pdo->prepare('SELECT nombre, apellido FROM usuarios WHERE id_usuario = ?');
    $stN->execute([$idUsuario]);
    $u = $stN->fetch(PDO::FETCH_ASSOC);
    $nombre = trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''));
    nomina_notificar_supervisores_ajuste(
        $pdo,
        $idPlantel,
        $idLiquidacion,
        'Ajuste manual en nómina',
        'Se agregó concepto a ' . $nombre . ': ' . $concepto . ' (' . catalog_format_mxn($importe) . '). Motivo: ' . $obs
    );

    return ['ok' => true, 'message' => 'Concepto agregado', 'id_linea' => $idLinea];
}

/** @param array<string, mixed> $data */
function nomina_linea_editar_manual(PDO $pdo, int $idPlantel, int $idLinea, array $data, int $idEditor): array
{
    if (!nomina_puede_ajustar_manual()) {
        return ['ok' => false, 'message' => 'Solo el director puede ajustar la nómina manualmente'];
    }
    suplencia_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT nl.*, l.id_plantel, l.estado, l.id_liquidacion FROM nomina_linea nl
         INNER JOIN nomina_liquidacion l ON l.id_liquidacion = nl.id_liquidacion
         WHERE nl.id_linea = ? AND l.id_plantel = ?'
    );
    $st->execute([$idLinea, $idPlantel]);
    $ln = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ln || ($ln['estado'] ?? '') === 'cerrada') {
        return ['ok' => false, 'message' => 'Línea no editable'];
    }
    if ((int) ($ln['es_manual'] ?? 0) !== 1) {
        return ['ok' => false, 'message' => 'Solo se pueden editar conceptos agregados manualmente. Use «Agregar concepto manual» para ajustes.'];
    }

    $obs = trim((string) ($data['observacion'] ?? ''));
    if ($obs === '') {
        return ['ok' => false, 'message' => 'La observación interna es obligatoria'];
    }

    $concepto = trim((string) ($data['concepto'] ?? $ln['concepto']));
    $cantidad = (float) ($data['cantidad'] ?? $ln['cantidad']);
    $tarifa = (float) ($data['tarifa'] ?? $ln['tarifa']);
    $importe = isset($data['importe']) && $data['importe'] !== ''
        ? (float) $data['importe'] : round($cantidad * $tarifa, 2);

    nomina_registrar_ajuste_log(
        $pdo,
        (int) $ln['id_liquidacion'],
        $idLinea,
        (int) $ln['id_usuario'],
        'editar',
        (string) $ln['concepto'],
        (float) $ln['importe'],
        $concepto,
        $importe,
        $obs,
        $idEditor
    );

    $pdo->prepare(
        'UPDATE nomina_linea SET concepto=?, cantidad=?, tarifa=?, importe=?, es_manual=1, observacion_interna=?, origen=\'manual\'
         WHERE id_linea=?'
    )->execute([$concepto, $cantidad, $tarifa, $importe, $obs, $idLinea]);

    nomina_recalcular_total($pdo, (int) $ln['id_liquidacion']);
    nomina_notificar_supervisores_ajuste(
        $pdo,
        $idPlantel,
        (int) $ln['id_liquidacion'],
        'Ajuste manual en nómina',
        'Se modificó concepto «' . $ln['concepto'] . '» → «' . $concepto . '» (' . catalog_format_mxn($importe) . '). Motivo: ' . $obs
    );

    return ['ok' => true, 'message' => 'Línea actualizada'];
}

function nomina_linea_eliminar_manual(PDO $pdo, int $idPlantel, int $idLinea, string $observacion, int $idEditor): array
{
    if (!nomina_puede_ajustar_manual()) {
        return ['ok' => false, 'message' => 'Solo el director puede ajustar la nómina manualmente'];
    }
    $observacion = trim($observacion);
    if ($observacion === '') {
        return ['ok' => false, 'message' => 'Indique el motivo de la eliminación'];
    }

    suplencia_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT nl.*, l.id_plantel, l.estado, l.id_liquidacion FROM nomina_linea nl
         INNER JOIN nomina_liquidacion l ON l.id_liquidacion = nl.id_liquidacion
         WHERE nl.id_linea = ? AND l.id_plantel = ?'
    );
    $st->execute([$idLinea, $idPlantel]);
    $ln = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ln || ($ln['estado'] ?? '') === 'cerrada') {
        return ['ok' => false, 'message' => 'Línea no editable'];
    }
    if ((int) ($ln['es_manual'] ?? 0) !== 1) {
        return ['ok' => false, 'message' => 'Solo se pueden eliminar conceptos agregados manualmente'];
    }

    nomina_registrar_ajuste_log(
        $pdo,
        (int) $ln['id_liquidacion'],
        $idLinea,
        (int) $ln['id_usuario'],
        'eliminar',
        (string) $ln['concepto'],
        (float) $ln['importe'],
        null,
        null,
        $observacion,
        $idEditor
    );

    $pdo->prepare('DELETE FROM nomina_linea WHERE id_linea = ?')->execute([$idLinea]);
    nomina_recalcular_total($pdo, (int) $ln['id_liquidacion']);
    nomina_notificar_supervisores_ajuste(
        $pdo,
        $idPlantel,
        (int) $ln['id_liquidacion'],
        'Ajuste manual en nómina',
        'Se eliminó concepto «' . $ln['concepto'] . '» (' . catalog_format_mxn((float) $ln['importe']) . '). Motivo: ' . $observacion
    );

    return ['ok' => true, 'message' => 'Línea eliminada'];
}

/** HTML imprimible de nómina por persona (sin observaciones internas). */
function nomina_html_sobres(array $liq, ?int $idUsuario = null): string
{
    $plantel = htmlspecialchars($_SESSION['plantel_nombre'] ?? 'Plantel', ENT_QUOTES, 'UTF-8');
    $periodo = htmlspecialchars($liq['etiqueta'] ?? '', ENT_QUOTES, 'UTF-8');
    $usuarios = $liq['por_usuario'] ?? [];
    if ($idUsuario > 0) {
        $usuarios = array_values(array_filter($usuarios, static fn($u) => (int) ($u['id_usuario'] ?? 0) === $idUsuario));
    }

    $bloques = '';
    foreach ($usuarios as $u) {
        $filas = '';
        foreach ($u['lineas'] ?? [] as $ln) {
            $filas .= '<tr><td>' . htmlspecialchars((string) ($ln['concepto'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="text-align:right">' . htmlspecialchars(catalog_format_mxn((float) ($ln['importe'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        if ($filas === '') {
            $filas = '<tr><td colspan="2" style="color:#888">Sin conceptos</td></tr>';
        }
        $bloques .= '<div class="sobre" style="page-break-after:always;margin-bottom:24px;border:1px solid #ccc;padding:16px;border-radius:6px;">'
            . '<h2 style="margin:0 0 4px;font-size:16px;">' . htmlspecialchars($u['nombre'] ?? '', ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<p style="margin:0 0 12px;color:#555;font-size:12px;">' . htmlspecialchars($u['rol'] ?? '', ENT_QUOTES, 'UTF-8') . ' · ' . $plantel . '</p>'
            . '<p style="margin:0 0 12px;font-size:12px;"><strong>Periodo:</strong> ' . $periodo . '</p>'
            . '<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:12px;">'
            . '<thead><tr style="background:#f0f4f8;"><th align="left">Concepto</th><th align="right">Importe</th></tr></thead>'
            . '<tbody>' . $filas . '</tbody>'
            . '<tfoot><tr><td align="right"><strong>Total</strong></td><td align="right"><strong>'
            . htmlspecialchars(catalog_format_mxn((float) ($u['subtotal'] ?? 0)), ENT_QUOTES, 'UTF-8')
            . '</strong></td></tr></tfoot></table></div>';
    }

    return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Nómina ' . $periodo . '</title></head><body style="font-family:DejaVu Sans,sans-serif;font-size:12px;color:#222;">'
        . '<h1 style="font-size:18px;margin-bottom:16px;">Nómina — ' . $plantel . ' · ' . $periodo . '</h1>'
        . $bloques . '</body></html>';
}

function nomina_enviar_pdf_sobres(PDO $pdo, int $idLiquidacion, int $idPlantel, ?int $idUsuario = null): void
{
    $liq = nomina_obtener($pdo, $idLiquidacion, $idPlantel);
    if (!$liq) {
        http_response_code(404);
        echo 'Liquidación no encontrada';
        exit;
    }

    $html = nomina_html_sobres($liq, $idUsuario);
    $filename = 'nomina_sobres_' . $idLiquidacion . '.pdf';

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('Dompdf\Dompdf') && class_exists('Dompdf\Options')) {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            echo $dompdf->output();
            exit;
        }
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo $html . '<p style="margin-top:20px;color:#666;">Dompdf no disponible. Use Imprimir → Guardar como PDF.</p>';
    exit;
}
