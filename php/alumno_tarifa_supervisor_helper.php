<?php

/**
 * Colegiatura personalizada por alumno (solo supervisor): beneficio especial o corrección.
 * Soporta vigencia temporal; al vencer se restaura la tarifa base congelada.
 */

function alumno_tarifa_supervisor_puede(): bool
{
    return function_exists('combo_puede_administrar') && combo_puede_administrar();
}

function alumno_tarifa_supervisor_ensure_schema(PDO $pdo): void
{
    pago_ensure_schema($pdo);
    combo_ensure_schema($pdo);

    plantel_ensure_column($pdo, 'alumno_especialidades', 'override_supervisor', 'TINYINT(1) NOT NULL DEFAULT 0', 'base_costo_semanal');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'override_vigente_hasta', 'DATE NULL', 'override_supervisor');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'override_motivo', 'VARCHAR(255) NULL', 'override_vigente_hasta');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'override_id_usuario', 'INT UNSIGNED NULL', 'override_motivo');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'override_actualizado', 'DATETIME NULL', 'override_id_usuario');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'override_resto_curso', 'TINYINT(1) NOT NULL DEFAULT 0', 'override_actualizado');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_adeudo_condonacion (
            id_condonacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_alumno_especialidad INT UNSIGNED NULL,
            id_especialidad INT UNSIGNED NULL,
            monto_condonado DECIMAL(12,2) NOT NULL DEFAULT 0,
            adeudo_antes DECIMAL(12,2) NOT NULL DEFAULT 0,
            detalle_json JSON NULL,
            motivo VARCHAR(255) NOT NULL,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_condonacion),
            KEY idx_cond_alumno (id_alumno),
            KEY idx_cond_ae (id_alumno_especialidad)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_tarifa_override_hist (
            id_hist INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno_especialidad INT UNSIGNED NOT NULL,
            id_alumno INT UNSIGNED NOT NULL,
            accion ENUM(\'aplicar\',\'restaurar\',\'vencer\',\'condonar\') NOT NULL,
            costo_inscripcion DECIMAL(12,2) NOT NULL DEFAULT 0,
            costo_mensualidad DECIMAL(12,2) NOT NULL DEFAULT 0,
            costo_pronto_pago DECIMAL(12,2) NOT NULL DEFAULT 0,
            costo_semanal DECIMAL(12,2) NOT NULL DEFAULT 0,
            base_inscripcion DECIMAL(12,2) NULL,
            base_mensualidad DECIMAL(12,2) NULL,
            base_pronto_pago DECIMAL(12,2) NULL,
            base_semanal DECIMAL(12,2) NULL,
            vigente_hasta DATE NULL,
            motivo VARCHAR(255) NULL,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_hist),
            KEY idx_tarifa_hist_alumno (id_alumno),
            KEY idx_tarifa_hist_ae (id_alumno_especialidad)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** @return array<string, float> */
function alumno_tarifa_supervisor_tarifas_catalogo(PDO $pdo, int $idEspecialidad): array
{
    $st = $pdo->prepare('SELECT * FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $st->execute([$idEspecialidad]);
    $e = $st->fetch(PDO::FETCH_ASSOC);
    if (!$e) {
        return [
            'inscripcion' => 0.0,
            'mensualidad' => 0.0,
            'pronto_pago' => 0.0,
            'semanal' => 0.0,
        ];
    }
    if (function_exists('operativo_cncm_tarifas_especialidad')) {
        $t = operativo_cncm_tarifas_especialidad($e);
        return [
            'inscripcion' => (float) ($t['inscripcion_apoyo'] ?? $e['costo_inscripcion'] ?? 0),
            'mensualidad' => (float) ($t['mensualidad_apoyo'] ?? $e['costo_mensualidad'] ?? 0),
            'pronto_pago' => (float) ($t['pronto_apoyo'] ?? $e['costo_pronto_pago'] ?? 0),
            'semanal' => (float) ($t['semanal_apoyo'] ?? $e['costo_semanal'] ?? 0),
        ];
    }

    return [
        'inscripcion' => (float) ($e['costo_inscripcion_apoyo'] ?? $e['costo_inscripcion'] ?? 0),
        'mensualidad' => (float) ($e['costo_mensualidad_apoyo'] ?? $e['costo_mensualidad'] ?? 0),
        'pronto_pago' => (float) ($e['costo_pronto_pago_apoyo'] ?? $e['costo_pronto_pago'] ?? 0),
        'semanal' => (float) ($e['costo_semanal_apoyo'] ?? $e['costo_semanal'] ?? 0),
    ];
}

/** @param array<string, mixed> $ae */
function alumno_tarifa_supervisor_tarifas_base(array $ae, PDO $pdo): array
{
    $cat = alumno_tarifa_supervisor_tarifas_catalogo($pdo, (int) $ae['id_especialidad']);

    return [
        'inscripcion' => (float) ($ae['base_costo_inscripcion'] ?? $cat['inscripcion']),
        'mensualidad' => (float) ($ae['base_costo_mensualidad'] ?? $cat['mensualidad']),
        'pronto_pago' => (float) ($ae['base_costo_pronto_pago'] ?? $cat['pronto_pago']),
        'semanal' => (float) ($ae['base_costo_semanal'] ?? $cat['semanal']),
    ];
}

function alumno_tarifa_supervisor_aplicar_vencidas(PDO $pdo, ?int $idAlumno = null): int
{
    alumno_tarifa_supervisor_ensure_schema($pdo);
    $sql = 'SELECT * FROM alumno_especialidades
            WHERE activo = 1 AND override_supervisor = 1
              AND override_vigente_hasta IS NOT NULL AND override_vigente_hasta < CURDATE()';
    $params = [];
    if ($idAlumno !== null && $idAlumno > 0) {
        $sql .= ' AND id_alumno = ?';
        $params[] = $idAlumno;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $n = 0;
    $alumnos = [];
    foreach ($rows as $row) {
        $res = alumno_tarifa_supervisor_restaurar_interno($pdo, $row, 0, 'Beneficio temporal vencido', 'vencer');
        if ($res['ok']) {
            $n++;
            $alumnos[(int) $row['id_alumno']] = true;
        }
    }
    foreach (array_keys($alumnos) as $idA) {
        if (function_exists('pago_aplicar_reglas_combo')) {
            pago_aplicar_reglas_combo($pdo, $idA);
        }
    }

    return $n;
}

/** @return array<int, array<string, mixed>> */
function alumno_tarifa_supervisor_listar(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    alumno_tarifa_supervisor_aplicar_vencidas($pdo, $idAlumno);
    $alumno = alumno_obtener($pdo, $idAlumno, $idPlantel);
    if (!$alumno) {
        return [];
    }
    $items = [];
    foreach (pago_inscripciones_alumno($pdo, $idAlumno) as $ae) {
        $base = alumno_tarifa_supervisor_tarifas_base($ae, $pdo);
        $overrideActivo = (int) ($ae['override_supervisor'] ?? 0) === 1;
        $hasta = $ae['override_vigente_hasta'] ?? null;
        $restoCurso = (int) ($ae['override_resto_curso'] ?? 0) === 1;
        $vigencia = 'permanente';
        if ($overrideActivo && $restoCurso) {
            $vigencia = 'resto_curso';
        } elseif ($overrideActivo && $hasta) {
            $vigencia = strtotime((string) $hasta) >= strtotime(date('Y-m-d')) ? 'temporal' : 'vencido';
        }
        $autorNombre = '';
        if (!empty($ae['override_id_usuario'])) {
            $st = $pdo->prepare('SELECT CONCAT(nombre, \' \', apellido) FROM usuarios WHERE id_usuario = ? LIMIT 1');
            $st->execute([(int) $ae['override_id_usuario']]);
            $autorNombre = trim((string) $st->fetchColumn());
        }
        $items[] = [
            'id_alumno_especialidad' => (int) $ae['id_alumno_especialidad'],
            'id_especialidad' => (int) $ae['id_especialidad'],
            'especialidad' => $ae['especialidad_nombre'] ?? '',
            'clave' => $ae['especialidad_clave'] ?? '',
            'forma_pago' => $ae['forma_pago'] ?? 'mensual',
            'override_activo' => $overrideActivo,
            'override_motivo' => $ae['override_motivo'] ?? '',
            'override_vigente_hasta' => $hasta,
            'override_resto_curso' => $restoCurso,
            'override_vigencia' => $vigencia,
            'override_autor' => $autorNombre,
            'override_actualizado' => $ae['override_actualizado'] ?? null,
            'id_regla_combo' => (int) ($ae['id_regla_combo'] ?? 0) ?: null,
            'tarifa_actual' => [
                'inscripcion' => (float) $ae['costo_inscripcion'],
                'mensualidad' => (float) $ae['costo_mensualidad'],
                'pronto_pago' => (float) $ae['costo_pronto_pago'],
                'semanal' => (float) $ae['costo_semanal'],
            ],
            'tarifa_base' => $base,
        ];
    }

    return $items;
}

/** @return array<int, array<string, mixed>> */
function alumno_tarifa_supervisor_historial(PDO $pdo, int $idAlumno, int $limite = 30): array
{
    alumno_tarifa_supervisor_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT h.*, e.nombre AS especialidad_nombre,
                CONCAT(u.nombre, \' \', u.apellido) AS usuario_nombre
         FROM alumno_tarifa_override_hist h
         INNER JOIN alumno_especialidades ae ON ae.id_alumno_especialidad = h.id_alumno_especialidad
         INNER JOIN especialidades e ON e.id_especialidad = ae.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario
         WHERE h.id_alumno = ?
         ORDER BY h.creado_en DESC
         LIMIT ' . max(1, min(100, $limite))
    );
    $st->execute([$idAlumno]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function alumno_tarifa_supervisor_guardar(PDO $pdo, int $idPlantel, array $data, int $idUsuario): array
{
    if (!alumno_tarifa_supervisor_puede()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    alumno_tarifa_supervisor_ensure_schema($pdo);

    $idAe = (int) ($data['id_alumno_especialidad'] ?? 0);
    $idAlumno = (int) ($data['id_alumno'] ?? 0);
    $motivo = trim((string) ($data['motivo'] ?? ''));
    if ($idAe <= 0 || $idAlumno <= 0) {
        return ['ok' => false, 'message' => 'Datos incompletos'];
    }
    if ($motivo === '') {
        return ['ok' => false, 'message' => 'Indica el motivo del ajuste'];
    }

    $st = $pdo->prepare(
        'SELECT ae.*, a.id_plantel FROM alumno_especialidades ae
         INNER JOIN alumnos a ON a.id_alumno = ae.id_alumno
         WHERE ae.id_alumno_especialidad = ? AND ae.id_alumno = ? AND ae.activo = 1 LIMIT 1'
    );
    $st->execute([$idAe, $idAlumno]);
    $ae = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ae || (int) $ae['id_plantel'] !== $idPlantel) {
        return ['ok' => false, 'message' => 'Especialidad del alumno no encontrada'];
    }

    $inscripcion = round((float) ($data['costo_inscripcion'] ?? $ae['costo_inscripcion']), 2);
    $mensualidad = round((float) ($data['costo_mensualidad'] ?? $ae['costo_mensualidad']), 2);
    $prontoPago = round((float) ($data['costo_pronto_pago'] ?? $ae['costo_pronto_pago']), 2);
    $semanal = round((float) ($data['costo_semanal'] ?? $ae['costo_semanal']), 2);
    foreach (['inscripción' => $inscripcion, 'mensualidad' => $mensualidad, 'pronto pago' => $prontoPago, 'semanal' => $semanal] as $label => $m) {
        if ($m < 0) {
            return ['ok' => false, 'message' => 'El monto de ' . $label . ' no puede ser negativo'];
        }
    }

    $temporal = !empty($data['beneficio_temporal']);
    $restoCurso = !empty($data['beneficio_resto_curso']);
    $mesesTemporal = (int) ($data['meses_temporal'] ?? 0);
    $hasta = null;
    $restoFlag = 0;

    if ($restoCurso) {
        $hasta = alumno_tarifa_supervisor_fecha_resto_curso($pdo, $ae);
        if ($hasta === null) {
            return ['ok' => false, 'message' => 'No se pudo calcular el fin del curso para este alumno'];
        }
        $restoFlag = 1;
    } elseif ($temporal || $mesesTemporal > 0) {
        if ($mesesTemporal > 0) {
            $hasta = date('Y-m-d', strtotime('+' . $mesesTemporal . ' months'));
        } else {
            $hasta = trim((string) ($data['vigente_hasta'] ?? ''));
        }
        if ($hasta === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            return ['ok' => false, 'message' => 'Indica la vigencia del beneficio temporal'];
        }
        if (strtotime($hasta) < strtotime(date('Y-m-d'))) {
            return ['ok' => false, 'message' => 'La fecha de fin debe ser hoy o posterior'];
        }
    }

    combo_respaldar_tarifas_base($pdo, $idAe);
    $base = alumno_tarifa_supervisor_tarifas_base($ae, $pdo);

    $pdo->prepare(
        'UPDATE alumno_especialidades SET
            costo_inscripcion = ?, costo_mensualidad = ?, costo_pronto_pago = ?, costo_semanal = ?,
            override_supervisor = 1, override_vigente_hasta = ?, override_resto_curso = ?,
            override_motivo = ?, override_id_usuario = ?, override_actualizado = NOW()
         WHERE id_alumno_especialidad = ?'
    )->execute([$inscripcion, $mensualidad, $prontoPago, $semanal, $hasta, $restoFlag, $motivo, $idUsuario, $idAe]);

    alumno_tarifa_supervisor_hist_insert($pdo, $idAe, $idAlumno, 'aplicar', [
        'inscripcion' => $inscripcion,
        'mensualidad' => $mensualidad,
        'pronto_pago' => $prontoPago,
        'semanal' => $semanal,
    ], $base, $hasta, $motivo, $idUsuario);

    alumno_tarifa_supervisor_notificar_aplicada($pdo, $idPlantel, $ae, $idAlumno, $motivo, $hasta, $mensualidad, $idUsuario);

    return [
        'ok' => true,
        'message' => $restoCurso
            ? 'Colegiatura personalizada hasta el fin del curso (' . date('d/m/Y', strtotime((string) $hasta)) . ').'
            : ($temporal || $mesesTemporal > 0
                ? 'Colegiatura personalizada hasta ' . date('d/m/Y', strtotime((string) $hasta)) . '. Al vencer volverá a la tarifa normal.'
                : 'Colegiatura personalizada guardada.'),
    ];
}

function alumno_tarifa_supervisor_restaurar(PDO $pdo, int $idPlantel, int $idAe, int $idAlumno, int $idUsuario, string $motivo = 'Restauración manual'): array
{
    if (!alumno_tarifa_supervisor_puede()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    alumno_tarifa_supervisor_ensure_schema($pdo);

    $st = $pdo->prepare(
        'SELECT ae.*, a.id_plantel FROM alumno_especialidades ae
         INNER JOIN alumnos a ON a.id_alumno = ae.id_alumno
         WHERE ae.id_alumno_especialidad = ? AND ae.id_alumno = ? AND ae.activo = 1 LIMIT 1'
    );
    $st->execute([$idAe, $idAlumno]);
    $ae = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ae || (int) $ae['id_plantel'] !== $idPlantel) {
        return ['ok' => false, 'message' => 'Especialidad del alumno no encontrada'];
    }

    $res = alumno_tarifa_supervisor_restaurar_interno($pdo, $ae, $idUsuario, $motivo, 'restaurar');
    if ($res['ok'] && function_exists('pago_aplicar_reglas_combo')) {
        pago_aplicar_reglas_combo($pdo, $idAlumno);
    }

    return $res;
}

/** @param array<string, mixed> $ae */
function alumno_tarifa_supervisor_restaurar_interno(PDO $pdo, array $ae, int $idUsuario, string $motivo, string $accion): array
{
    $idAe = (int) $ae['id_alumno_especialidad'];
    $idAlumno = (int) $ae['id_alumno'];
    $base = alumno_tarifa_supervisor_tarifas_base($ae, $pdo);
    $actual = [
        'inscripcion' => (float) $ae['costo_inscripcion'],
        'mensualidad' => (float) $ae['costo_mensualidad'],
        'pronto_pago' => (float) $ae['costo_pronto_pago'],
        'semanal' => (float) $ae['costo_semanal'],
    ];

    $pdo->prepare(
        'UPDATE alumno_especialidades SET
            costo_inscripcion = ?, costo_mensualidad = ?, costo_pronto_pago = ?, costo_semanal = ?,
            override_supervisor = 0, override_vigente_hasta = NULL, override_resto_curso = 0,
            override_motivo = NULL, override_id_usuario = NULL, override_actualizado = NOW()
         WHERE id_alumno_especialidad = ?'
    )->execute([
        $base['inscripcion'], $base['mensualidad'], $base['pronto_pago'], $base['semanal'], $idAe,
    ]);

    alumno_tarifa_supervisor_hist_insert($pdo, $idAe, $idAlumno, $accion, $base, $actual, null, $motivo, $idUsuario);

    if ($accion === 'vencer') {
        alumno_tarifa_supervisor_notificar_vencida($pdo, (int) ($ae['id_plantel'] ?? 0), $ae, $idAlumno, $motivo);
    }

    return ['ok' => true, 'message' => 'Tarifa restaurada a la normalidad.'];
}

/** @param array<string, mixed> $ae */
function alumno_tarifa_supervisor_fecha_resto_curso(PDO $pdo, array $ae): ?string
{
    $stAl = $pdo->prepare('SELECT inscripcion_vigente_hasta FROM alumnos WHERE id_alumno = ? LIMIT 1');
    $stAl->execute([(int) ($ae['id_alumno'] ?? 0)]);
    $hastaAl = trim((string) ($stAl->fetchColumn() ?: ''));
    if ($hastaAl !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hastaAl)) {
        return $hastaAl;
    }
    $fechaInsc = trim((string) ($ae['fecha_inscripcion'] ?? ''));
    if ($fechaInsc === '') {
        return null;
    }
    $meses = (int) ($ae['duracion_meses'] ?? 0);
    if ($meses <= 0) {
        $stE = $pdo->prepare('SELECT duracion_meses FROM especialidades WHERE id_especialidad = ? LIMIT 1');
        $stE->execute([(int) ($ae['id_especialidad'] ?? 0)]);
        $meses = (int) ($stE->fetchColumn() ?: 0);
    }
    if ($meses <= 0) {
        return null;
    }

    return date('Y-m-d', strtotime($fechaInsc . ' +' . $meses . ' months'));
}

/** @return list<array<string, mixed>> */
function alumno_tarifa_supervisor_condonaciones(PDO $pdo, int $idAlumno, int $limite = 20): array
{
    alumno_tarifa_supervisor_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT c.*, e.nombre AS especialidad_nombre,
                CONCAT(u.nombre, \' \', u.apellido) AS usuario_nombre
         FROM alumno_adeudo_condonacion c
         LEFT JOIN especialidades e ON e.id_especialidad = c.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario
         WHERE c.id_alumno = ?
         ORDER BY c.creado_en DESC
         LIMIT ' . max(1, min(50, $limite))
    );
    $st->execute([$idAlumno]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function alumno_tarifa_supervisor_condonar_adeudo(
    PDO $pdo,
    int $idPlantel,
    int $idAlumno,
    string $motivo,
    int $idUsuario,
    ?int $idAe = null
): array {
    if (!alumno_tarifa_supervisor_puede()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    alumno_tarifa_supervisor_ensure_schema($pdo);
    $motivo = trim($motivo);
    if ($motivo === '') {
        return ['ok' => false, 'message' => 'Indica el motivo de la condonación'];
    }
    $al = alumno_obtener($pdo, $idAlumno, $idPlantel);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }
    if (!function_exists('pago_estado_cuenta')) {
        return ['ok' => false, 'message' => 'Módulo de pagos no disponible'];
    }

    $ec = pago_estado_cuenta($pdo, $idAlumno);
    if (empty($ec['ok'])) {
        return ['ok' => false, 'message' => 'No se pudo calcular el adeudo'];
    }

    $lineas = [];
    $idEspFiltro = 0;
    if ($idAe !== null && $idAe > 0) {
        $stAe = $pdo->prepare('SELECT id_especialidad FROM alumno_especialidades WHERE id_alumno_especialidad = ? LIMIT 1');
        $stAe->execute([$idAe]);
        $idEspFiltro = (int) ($stAe->fetchColumn() ?: 0);
    }
    foreach ($ec['lineas_adeudo'] ?? [] as $ln) {
        $saldo = (float) ($ln['saldo'] ?? 0);
        if ($saldo < 0.01) {
            continue;
        }
        if ($idEspFiltro > 0 && (int) ($ln['id_especialidad'] ?? 0) !== $idEspFiltro) {
            continue;
        }
        $lineas[] = $ln;
    }
    if ($lineas === []) {
        return ['ok' => false, 'message' => 'No hay adeudo pendiente para condonar'];
    }

    $adeudoAntes = round(array_sum(array_map(fn($l) => (float) ($l['saldo'] ?? 0), $lineas)), 2);
    $detallePagos = [];
    $montoCondonado = 0.0;

    foreach ($lineas as $ln) {
        $saldo = round((float) ($ln['saldo'] ?? 0), 2);
        if ($saldo < 0.01) {
            continue;
        }
        $res = pago_registrar($pdo, [
            'id_alumno' => $idAlumno,
            'id_especialidad' => (int) ($ln['id_especialidad'] ?? 0) ?: null,
            'id_alumno_especialidad' => $idAe,
            'tipo' => 'abono',
            'monto' => $saldo,
            'forma_pago_efectivo' => 'Condonación',
            'concepto' => 'Condonación de adeudo — ' . mb_substr($motivo, 0, 120),
            'periodo_ref' => $ln['periodo'] ?? $ln['periodo_ref'] ?? null,
            'motivo_descuento' => $motivo,
            'id_autoriza' => $idUsuario,
        ]);
        if (empty($res['ok'])) {
            return ['ok' => false, 'message' => $res['message'] ?? 'Error al registrar condonación'];
        }
        $detallePagos[] = [
            'id_pago' => (int) ($res['id_pago'] ?? 0),
            'periodo_ref' => $ln['periodo'] ?? $ln['periodo_ref'] ?? null,
            'monto' => $saldo,
        ];
        $montoCondonado += $saldo;
    }

    $idEsp = null;
    if ($idAe !== null && $idAe > 0) {
        $idEsp = $idEspFiltro > 0 ? $idEspFiltro : null;
    }

    $pdo->prepare(
        'INSERT INTO alumno_adeudo_condonacion (
            id_alumno, id_alumno_especialidad, id_especialidad,
            monto_condonado, adeudo_antes, detalle_json, motivo, id_usuario
        ) VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $idAlumno,
        $idAe,
        $idEsp,
        round($montoCondonado, 2),
        $adeudoAntes,
        json_encode($detallePagos, JSON_UNESCAPED_UNICODE),
        $motivo,
        $idUsuario > 0 ? $idUsuario : null,
    ]);

    $idAeHist = $idAe ?? (int) ($lineas[0]['id_alumno_especialidad'] ?? 0);
    if ($idAeHist > 0) {
        alumno_tarifa_supervisor_hist_insert(
            $pdo,
            $idAeHist,
            $idAlumno,
            'condonar',
            ['inscripcion' => 0, 'mensualidad' => 0, 'pronto_pago' => 0, 'semanal' => 0],
            ['inscripcion' => $adeudoAntes, 'mensualidad' => 0, 'pronto_pago' => 0, 'semanal' => 0],
            null,
            $motivo,
            $idUsuario
        );
    }

    alumno_tarifa_supervisor_notificar_condonacion($pdo, $idPlantel, $idAlumno, $montoCondonado, $motivo, $idUsuario);

    return [
        'ok' => true,
        'message' => 'Adeudo condonado por ' . catalog_format_mxn($montoCondonado) . '.',
        'monto_condonado' => round($montoCondonado, 2),
    ];
}

function alumno_tarifa_supervisor_notificar_condonacion(
    PDO $pdo,
    int $idPlantel,
    int $idAlumno,
    float $monto,
    string $motivo,
    int $idUsuario
): void {
    $nombre = alumno_tarifa_supervisor_nombre_alumno($pdo, $idAlumno);
    $msg = $nombre . ' — condonación ' . catalog_format_mxn($monto);
    if ($motivo !== '') {
        $msg .= ' · ' . mb_substr($motivo, 0, 120);
    }
    if ($idUsuario > 0) {
        $stU = $pdo->prepare('SELECT CONCAT(nombre, \' \', apellido) FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stU->execute([$idUsuario]);
        $autor = trim((string) $stU->fetchColumn());
        if ($autor !== '') {
            $msg .= ' (por ' . $autor . ')';
        }
    }
    alumno_tarifa_supervisor_push_notificacion(
        $pdo,
        $idPlantel,
        'tarifa_supervisor_condonacion',
        'Adeudo condonado por supervisor',
        $msg,
        'alumno_detalle',
        'id=' . $idAlumno
    );
}

/** @return list<int> */
function alumno_tarifa_supervisor_ids_supervisor(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        "SELECT id_usuario FROM usuarios
         WHERE activo = 1 AND (suspendido IS NULL OR suspendido = 0)
           AND rol = 'supervisor'
           AND (id_plantel IS NULL OR id_plantel = ?)"
    );
    $st->execute([$idPlantel]);

    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function alumno_tarifa_supervisor_push_notificacion(
    PDO $pdo,
    int $idPlantel,
    string $tipo,
    string $titulo,
    string $mensaje,
    ?string $seccion = 'alumno_detalle',
    ?string $params = null
): void {
    if (!function_exists('academico_notificar_usuario')) {
        return;
    }
    if (function_exists('academico_ensure_schema')) {
        academico_ensure_schema($pdo);
    }
    foreach (alumno_tarifa_supervisor_ids_supervisor($pdo, $idPlantel) as $idU) {
        if ($idU <= 0) {
            continue;
        }
        academico_notificar_usuario($pdo, $idU, $tipo, $titulo, $mensaje, $seccion, $params);
    }
}

/** @param PDO $pdo */
function alumno_tarifa_supervisor_nombre_alumno(PDO $pdo, int $idAlumno): string
{
    $st = $pdo->prepare(
        'SELECT numero_control, nombres, apellido_paterno, nombre, apellido FROM alumnos WHERE id_alumno = ? LIMIT 1'
    );
    $st->execute([$idAlumno]);
    $a = $st->fetch(PDO::FETCH_ASSOC);
    if (!$a) {
        return 'Alumno #' . $idAlumno;
    }
    $nombre = trim(($a['nombres'] ?? $a['nombre'] ?? '') . ' ' . ($a['apellido_paterno'] ?? $a['apellido'] ?? ''));
    $ctrl = trim((string) ($a['numero_control'] ?? ''));

    return ($ctrl !== '' ? $ctrl . ' ' : '') . $nombre;
}

/** @param array<string, mixed> $ae */
function alumno_tarifa_supervisor_notificar_aplicada(
    PDO $pdo,
    int $idPlantel,
    array $ae,
    int $idAlumno,
    string $motivo,
    ?string $hasta,
    float $mensualidad,
    int $idUsuario
): void {
    $nombre = alumno_tarifa_supervisor_nombre_alumno($pdo, $idAlumno);
    $esp = '';
    $st = $pdo->prepare('SELECT nombre FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $st->execute([(int) ($ae['id_especialidad'] ?? 0)]);
    $esp = trim((string) $st->fetchColumn());
    $autor = '';
    if ($idUsuario > 0) {
        $stU = $pdo->prepare('SELECT CONCAT(nombre, \' \', apellido) FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stU->execute([$idUsuario]);
        $autor = trim((string) $stU->fetchColumn());
    }
    $msg = $nombre;
    if ($esp !== '') {
        $msg .= ' · ' . $esp;
    }
    $msg .= ' — mensualidad ' . catalog_format_mxn($mensualidad);
    if ($hasta) {
        $msg .= ' · vigente hasta ' . date('d/m/Y', strtotime($hasta));
    } else {
        $msg .= ' · beneficio permanente';
    }
    if ($motivo !== '') {
        $msg .= ' · ' . mb_substr($motivo, 0, 120);
    }
    if ($autor !== '') {
        $msg .= ' (por ' . $autor . ')';
    }
    alumno_tarifa_supervisor_push_notificacion(
        $pdo,
        $idPlantel,
        'tarifa_supervisor_aplicada',
        'Colegiatura personalizada aplicada',
        $msg,
        'alumno_detalle',
        'id=' . $idAlumno
    );
}

/** @param array<string, mixed> $ae */
function alumno_tarifa_supervisor_notificar_vencida(
    PDO $pdo,
    int $idPlantel,
    array $ae,
    int $idAlumno,
    string $motivo
): void {
    if ($idPlantel <= 0) {
        $st = $pdo->prepare('SELECT id_plantel FROM alumnos WHERE id_alumno = ? LIMIT 1');
        $st->execute([$idAlumno]);
        $idPlantel = (int) $st->fetchColumn();
    }
    if ($idPlantel <= 0) {
        return;
    }
    $nombre = alumno_tarifa_supervisor_nombre_alumno($pdo, $idAlumno);
    $st = $pdo->prepare('SELECT nombre FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $st->execute([(int) ($ae['id_especialidad'] ?? 0)]);
    $esp = trim((string) $st->fetchColumn());
    $msg = $nombre;
    if ($esp !== '') {
        $msg .= ' · ' . $esp;
    }
    $msg .= ' — el beneficio temporal terminó y la tarifa volvió a la normalidad.';
    if ($motivo !== '') {
        $msg .= ' ' . mb_substr($motivo, 0, 100);
    }
    alumno_tarifa_supervisor_push_notificacion(
        $pdo,
        $idPlantel,
        'tarifa_supervisor_vencida',
        'Beneficio de colegiatura vencido',
        $msg,
        'alumno_detalle',
        'id=' . $idAlumno
    );
}

/** @param array<string, float> $tarifa @param array<string, float> $base */
function alumno_tarifa_supervisor_hist_insert(
    PDO $pdo,
    int $idAe,
    int $idAlumno,
    string $accion,
    array $tarifa,
    array $base,
    ?string $hasta,
    string $motivo,
    int $idUsuario
): void {
    $pdo->prepare(
        'INSERT INTO alumno_tarifa_override_hist (
            id_alumno_especialidad, id_alumno, accion,
            costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal,
            base_inscripcion, base_mensualidad, base_pronto_pago, base_semanal,
            vigente_hasta, motivo, id_usuario
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idAe, $idAlumno, $accion,
        $tarifa['inscripcion'], $tarifa['mensualidad'], $tarifa['pronto_pago'], $tarifa['semanal'],
        $base['inscripcion'] ?? null, $base['mensualidad'] ?? null, $base['pronto_pago'] ?? null, $base['semanal'] ?? null,
        $hasta, $motivo, $idUsuario > 0 ? $idUsuario : null,
    ]);
}
