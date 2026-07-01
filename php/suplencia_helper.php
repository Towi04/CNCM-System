<?php

/**
 * Suplencias temporales de grupos para nómina docente.
 */

function suplencia_ensure_schema(PDO $pdo): void
{
    nomina_ensure_schema($pdo);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_suplencia (
            id_suplencia INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            id_profesor_titular INT UNSIGNED NOT NULL,
            id_profesor_suplente INT UNSIGNED NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NOT NULL,
            motivo ENUM(\'enfermedad\',\'evento_institucional\',\'apoyo_evento\',\'otro\') NOT NULL DEFAULT \'enfermedad\',
            regla_pago ENUM(\'solo_suplente\',\'ambos\',\'solo_titular_apoyo\') NOT NULL DEFAULT \'solo_suplente\',
            pago_titular_concepto VARCHAR(160) NULL,
            pago_titular_monto DECIMAL(12,2) NULL,
            pago_titular_horas DECIMAL(8,2) NULL,
            notas TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_por INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_suplencia),
            KEY idx_gs_grupo (id_grupo),
            KEY idx_gs_plantel (id_plantel),
            KEY idx_gs_fechas (fecha_inicio, fecha_fin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'nomina_linea', 'es_manual', 'TINYINT(1) NOT NULL DEFAULT 0', 'importe');
        plantel_ensure_column($pdo, 'nomina_linea', 'observacion_interna', 'TEXT NULL', 'es_manual');
        plantel_ensure_column($pdo, 'nomina_linea', 'origen', "VARCHAR(30) NOT NULL DEFAULT 'calculado'", 'observacion_interna');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS nomina_ajuste_log (
            id_log INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_liquidacion INT UNSIGNED NOT NULL,
            id_linea INT UNSIGNED NULL,
            id_usuario_afectado INT UNSIGNED NOT NULL,
            accion ENUM(\'agregar\',\'editar\',\'eliminar\') NOT NULL,
            concepto_antes VARCHAR(255) NULL,
            importe_antes DECIMAL(12,2) NULL,
            concepto_despues VARCHAR(255) NULL,
            importe_despues DECIMAL(12,2) NULL,
            observacion TEXT NOT NULL,
            id_usuario_editor INT UNSIGNED NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_log),
            KEY idx_nal_liq (id_liquidacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function suplencia_puede_gestionar(): bool
{
    if (function_exists('nomina_puede_gestionar') && nomina_puede_gestionar()) {
        return true;
    }
    return in_array(rbac_rol_efectivo(), ['coordinador', 'director', 'gerente'], true);
}

/** @return array<string, string> */
function suplencia_motivos_labels(): array
{
    return [
        'enfermedad' => 'Enfermedad / ausencia',
        'evento_institucional' => 'Evento institucional',
        'apoyo_evento' => 'Apoyo en evento (viaje, etc.)',
        'otro' => 'Otro',
    ];
}

/** @return array<string, string> */
function suplencia_reglas_labels(): array
{
    return [
        'solo_suplente' => 'Pagar al suplente (titular no cobra esas horas)',
        'ambos' => 'Pagar a ambos (suplente imparte + titular recibe apoyo)',
        'solo_titular_apoyo' => 'Solo apoyo al titular (grupo no impartido)',
    ];
}

/** @return list<array<string, mixed>> */
function suplencia_listar(PDO $pdo, int $idPlantel, ?string $desde = null, ?string $hasta = null): array
{
    suplencia_ensure_schema($pdo);
    $sql = 'SELECT s.*, g.clave AS grupo_clave,
                   CONCAT(ut.nombre, \' \', ut.apellido) AS titular_nombre,
                   CONCAT(us.nombre, \' \', us.apellido) AS suplente_nombre
            FROM grupo_suplencia s
            INNER JOIN grupos g ON g.id_grupo = s.id_grupo
            INNER JOIN usuarios ut ON ut.id_usuario = s.id_profesor_titular
            LEFT JOIN usuarios us ON us.id_usuario = s.id_profesor_suplente
            WHERE s.id_plantel = ? AND s.activo = 1';
    $params = [$idPlantel];
    if ($desde && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $sql .= ' AND s.fecha_fin >= ?';
        $params[] = $desde;
    }
    if ($hasta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $sql .= ' AND s.fecha_inicio <= ?';
        $params[] = $hasta;
    }
    $sql .= ' ORDER BY s.fecha_inicio DESC, s.id_suplencia DESC LIMIT 200';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['motivo_label'] = suplencia_motivos_labels()[$r['motivo'] ?? ''] ?? $r['motivo'];
        $r['regla_label'] = suplencia_reglas_labels()[$r['regla_pago'] ?? ''] ?? $r['regla_pago'];
    }
    unset($r);

    return $rows;
}

/** @return array<string, mixed>|null */
function suplencia_activa_grupo_fecha(PDO $pdo, int $idGrupo, string $fecha): ?array
{
    suplencia_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM grupo_suplencia
         WHERE id_grupo = ? AND activo = 1 AND fecha_inicio <= ? AND fecha_fin >= ?
         ORDER BY id_suplencia DESC LIMIT 1'
    );
    $st->execute([$idGrupo, $fecha, $fecha]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @param array<string, mixed> $data */
function suplencia_guardar(PDO $pdo, int $idPlantel, array $data, int $idUsuario): array
{
    suplencia_ensure_schema($pdo);
    if (!suplencia_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }

    $idGrupo = (int) ($data['id_grupo'] ?? 0);
    $idTitular = (int) ($data['id_profesor_titular'] ?? 0);
    $idSuplente = (int) ($data['id_profesor_suplente'] ?? 0) ?: null;
    $desde = trim((string) ($data['fecha_inicio'] ?? ''));
    $hasta = trim((string) ($data['fecha_fin'] ?? ''));
    $motivo = (string) ($data['motivo'] ?? 'enfermedad');
    $regla = (string) ($data['regla_pago'] ?? 'solo_suplente');

    if ($idGrupo <= 0 || $idTitular <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        return ['ok' => false, 'message' => 'Grupo, titular y fechas son obligatorios'];
    }
    if ($hasta < $desde) {
        return ['ok' => false, 'message' => 'La fecha fin debe ser posterior al inicio'];
    }
    if (!in_array($motivo, array_keys(suplencia_motivos_labels()), true)) {
        $motivo = 'enfermedad';
    }
    if (!in_array($regla, array_keys(suplencia_reglas_labels()), true)) {
        $regla = 'solo_suplente';
    }
    if (in_array($regla, ['solo_suplente', 'ambos'], true) && !$idSuplente) {
        return ['ok' => false, 'message' => 'Indique el profesor suplente'];
    }

    $stG = $pdo->prepare('SELECT id_grupo FROM grupos WHERE id_grupo = ? AND id_plantel = ?');
    $stG->execute([$idGrupo, $idPlantel]);
    if (!$stG->fetchColumn()) {
        return ['ok' => false, 'message' => 'Grupo no válido'];
    }

    $concepto = trim((string) ($data['pago_titular_concepto'] ?? '')) ?: null;
    $monto = isset($data['pago_titular_monto']) && $data['pago_titular_monto'] !== ''
        ? (float) $data['pago_titular_monto'] : null;
    $horas = isset($data['pago_titular_horas']) && $data['pago_titular_horas'] !== ''
        ? (float) $data['pago_titular_horas'] : null;
    $notas = trim((string) ($data['notas'] ?? '')) ?: null;
    $id = (int) ($data['id_suplencia'] ?? 0);

    $params = [
        $idGrupo, $idPlantel, $idTitular, $idSuplente, $desde, $hasta, $motivo, $regla,
        $concepto, $monto, $horas, $notas,
    ];

    if ($id > 0) {
        $params[] = $id;
        $pdo->prepare(
            'UPDATE grupo_suplencia SET id_grupo=?, id_plantel=?, id_profesor_titular=?, id_profesor_suplente=?,
             fecha_inicio=?, fecha_fin=?, motivo=?, regla_pago=?, pago_titular_concepto=?, pago_titular_monto=?,
             pago_titular_horas=?, notas=? WHERE id_suplencia=?'
        )->execute($params);
    } else {
        $pdo->prepare(
            'INSERT INTO grupo_suplencia (id_grupo, id_plantel, id_profesor_titular, id_profesor_suplente,
             fecha_inicio, fecha_fin, motivo, regla_pago, pago_titular_concepto, pago_titular_monto,
             pago_titular_horas, notas, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute(array_merge($params, [$idUsuario]));
        $id = (int) $pdo->lastInsertId();
    }

    return ['ok' => true, 'message' => 'Suplencia registrada', 'id_suplencia' => $id];
}

function suplencia_cancelar(PDO $pdo, int $idSuplencia, int $idPlantel): array
{
    suplencia_ensure_schema($pdo);
    if (!suplencia_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $pdo->prepare('UPDATE grupo_suplencia SET activo = 0 WHERE id_suplencia = ? AND id_plantel = ?')
        ->execute([$idSuplencia, $idPlantel]);

    return ['ok' => true, 'message' => 'Suplencia cancelada'];
}

/**
 * @return array{horas_clase: float, sesiones: int, grupos: list<string>, apoyos: list<array>, suplencias: list<array>}
 */
function suplencia_desglose_horas_profesor(PDO $pdo, int $idProfesor, int $idPlantel, string $desde, string $hasta): array
{
    suplencia_ensure_schema($pdo);
    asistencia_ensure_schema($pdo);

    $horasClase = 0.0;
    $sesiones = 0;
    $gruposSet = [];
    $apoyos = [];
    $suplenciasAplicadas = [];

    $ini = new DateTimeImmutable($desde);
    $fin = new DateTimeImmutable($hasta);

    $stTit = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, gh.dia_semana, gh.hora_inicio, gh.hora_fin
         FROM grupos g
         INNER JOIN grupo_horarios gh ON gh.id_grupo = g.id_grupo AND gh.activo = 1
         WHERE g.id_profesor = ? AND g.id_plantel = ?'
    );
    $stTit->execute([$idProfesor, $idPlantel]);
    $horariosTitular = $stTit->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stSup = $pdo->prepare(
        'SELECT s.*, g.clave, gh.dia_semana, gh.hora_inicio, gh.hora_fin
         FROM grupo_suplencia s
         INNER JOIN grupos g ON g.id_grupo = s.id_grupo
         INNER JOIN grupo_horarios gh ON gh.id_grupo = g.id_grupo AND gh.activo = 1
         WHERE s.id_plantel = ? AND s.activo = 1 AND s.id_profesor_suplente = ?
           AND s.fecha_fin >= ? AND s.fecha_inicio <= ?
           AND s.regla_pago IN (\'solo_suplente\', \'ambos\')'
    );
    $stSup->execute([$idPlantel, $idProfesor, $desde, $hasta]);
    $horariosSuplente = $stSup->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $apoyoRegistrado = [];

    $cursor = $ini;
    while ($cursor <= $fin) {
        $fecha = $cursor->format('Y-m-d');
        $dow = (int) $cursor->format('w');

        foreach ($horariosTitular as $h) {
            if ((int) ($h['dia_semana'] ?? -1) !== $dow) {
                continue;
            }
            $idGrupo = (int) ($h['id_grupo'] ?? 0);
            $sup = suplencia_activa_grupo_fecha($pdo, $idGrupo, $fecha);
            $t1 = strtotime((string) $h['hora_inicio']);
            $t2 = strtotime((string) $h['hora_fin']);
            if ($t2 <= $t1) {
                continue;
            }
            $hrs = ($t2 - $t1) / 3600;

            if (!$sup) {
                $horasClase += $hrs;
                $sesiones++;
                $gruposSet[(string) ($h['clave'] ?? '')] = true;
                continue;
            }

            $regla = (string) ($sup['regla_pago'] ?? 'solo_suplente');
            if ($regla === 'solo_suplente') {
                continue;
            }
            if ($regla === 'ambos' || $regla === 'solo_titular_apoyo') {
                $key = (int) $sup['id_suplencia'];
                if (!isset($apoyoRegistrado[$key])) {
                    $apoyoRegistrado[$key] = true;
                    $concepto = trim((string) ($sup['pago_titular_concepto'] ?? ''))
                        ?: 'Apoyo / grupo no impartido — ' . ($h['clave'] ?? '');
                    $apoyos[] = [
                        'id_suplencia' => $key,
                        'id_grupo' => $idGrupo,
                        'grupo_clave' => (string) ($h['clave'] ?? ''),
                        'concepto' => $concepto,
                        'monto_fijo' => isset($sup['pago_titular_monto']) ? (float) $sup['pago_titular_monto'] : null,
                        'horas' => isset($sup['pago_titular_horas']) ? (float) $sup['pago_titular_horas'] : null,
                        'regla' => $regla,
                    ];
                }
            }
        }

        foreach ($horariosSuplente as $h) {
            if ((int) ($h['dia_semana'] ?? -1) !== $dow) {
                continue;
            }
            if ($fecha < ($h['fecha_inicio'] ?? '') || $fecha > ($h['fecha_fin'] ?? '')) {
                continue;
            }
            $t1 = strtotime((string) $h['hora_inicio']);
            $t2 = strtotime((string) $h['hora_fin']);
            if ($t2 <= $t1) {
                continue;
            }
            $horasClase += ($t2 - $t1) / 3600;
            $sesiones++;
            $gruposSet[(string) ($h['clave'] ?? '')] = true;
            $suplenciasAplicadas[(int) ($h['id_suplencia'] ?? 0)] = (string) ($h['clave'] ?? '');
        }

        $cursor = $cursor->modify('+1 day');
    }

    return [
        'horas_clase' => round($horasClase, 2),
        'sesiones' => $sesiones,
        'grupos' => array_keys(array_filter($gruposSet)),
        'apoyos' => $apoyos,
        'suplencias' => $suplenciasAplicadas,
    ];
}

/** @return list<array{concepto: string, cantidad: float, tarifa: float, importe: float, tipo_pago: string, detalle?: array}> */
function suplencia_lineas_apoyo(PDO $pdo, int $idProfesor, int $idPlantel, string $desde, string $hasta, float $tarifa): array
{
    $desglose = suplencia_desglose_horas_profesor($pdo, $idProfesor, $idPlantel, $desde, $hasta);
    $lineas = [];
    foreach ($desglose['apoyos'] as $a) {
        $montoFijo = $a['monto_fijo'] ?? null;
        $horas = $a['horas'] ?? null;
        if ($montoFijo !== null && $montoFijo > 0) {
            $lineas[] = [
                'concepto' => (string) $a['concepto'],
                'cantidad' => 1,
                'tarifa' => $montoFijo,
                'importe' => round($montoFijo, 2),
                'tipo_pago' => 'apoyo',
                'detalle' => ['id_suplencia' => $a['id_suplencia'], 'grupo' => $a['grupo_clave']],
            ];
        } elseif ($horas !== null && $horas > 0) {
            $importe = round($horas * $tarifa, 2);
            $lineas[] = [
                'concepto' => (string) $a['concepto'],
                'cantidad' => $horas,
                'tarifa' => $tarifa,
                'importe' => $importe,
                'tipo_pago' => 'apoyo',
                'detalle' => ['id_suplencia' => $a['id_suplencia'], 'horas' => $horas],
            ];
        }
    }

    return $lineas;
}

/** @return list<array<string, mixed>> */
function suplencia_profesores_plantel(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        "SELECT id_usuario, nombre, apellido, rol FROM usuarios
         WHERE suspendido = 0 AND rol IN ('profesor','coordinador','asesor','admin')
           AND (id_plantel = ? OR rol = 'supervisor')
         ORDER BY nombre, apellido"
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function suplencia_grupos_plantel(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.id_profesor, CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre
         FROM grupos g
         LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
         WHERE g.id_plantel = ?
         ORDER BY g.clave'
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
