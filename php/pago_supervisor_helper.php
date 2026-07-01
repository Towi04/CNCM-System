<?php

/**
 * Anulación y edición de pagos por supervisor (con motivo y auditoría).
 */

function pago_supervisor_puede(): bool
{
    if (function_exists('rbac_rol_real') && rbac_rol_real() === 'supervisor') {
        return true;
    }
    if (function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() === 'supervisor') {
        return true;
    }
    if (function_exists('combo_puede_administrar') && combo_puede_administrar()) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('pago_supervisor_editar');
}

function pago_supervisor_ensure_schema(PDO $pdo): void
{
    pago_ensure_schema($pdo);
}

function pago_supervisor_snapshot(array $pago): array
{
    $keys = [
        'id_pago', 'id_alumno', 'id_especialidad', 'tipo', 'monto', 'forma_pago',
        'concepto', 'periodo_ref', 'folio', 'cubrio', 'creado_en', 'estado',
    ];
    $out = [];
    foreach ($keys as $k) {
        if (array_key_exists($k, $pago)) {
            $out[$k] = $pago[$k];
        }
    }

    return $out;
}

function pago_supervisor_obtener(PDO $pdo, int $idPago): ?array
{
    pago_supervisor_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM alumno_pagos WHERE id_pago = ? LIMIT 1');
    $st->execute([$idPago]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function pago_supervisor_alumno_en_plantel(PDO $pdo, int $idAlumno, int $idPlantel): bool
{
    if ($idAlumno <= 0 || $idPlantel <= 0) {
        return false;
    }
    $st = $pdo->prepare('SELECT 1 FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1');
    $st->execute([$idAlumno, $idPlantel]);

    return (bool) $st->fetchColumn();
}

function pago_supervisor_registrar_movimiento(
    PDO $pdo,
    int $idPago,
    int $idAlumno,
    string $tipo,
    string $motivo,
    int $idUsuario,
    ?array $snapshot = null,
    ?int $idPagoNuevo = null
): void {
    $pdo->prepare(
        'INSERT INTO alumno_pago_movimiento
         (id_pago, id_pago_nuevo, id_alumno, tipo, snapshot_json, motivo, id_usuario)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([
        $idPago,
        $idPagoNuevo,
        $idAlumno,
        $tipo,
        $snapshot ? json_encode($snapshot, JSON_UNESCAPED_UNICODE) : null,
        $motivo,
        $idUsuario,
    ]);
}

function pago_supervisor_sync_inscripcion_tras_cambio(PDO $pdo, array $pago): void
{
    if (($pago['tipo'] ?? '') !== 'inscripcion') {
        return;
    }
    $idAlumno = (int) $pago['id_alumno'];
    pago_sync_inscripcion_global($pdo, $idAlumno);
    $idAe = (int) ($pago['id_alumno_especialidad'] ?? 0);
    $idEsp = (int) ($pago['id_especialidad'] ?? 0);
    if ($idAe > 0 && $idEsp > 0) {
        pago_actualizar_inscripcion_cubierta($pdo, $idAlumno, $idEsp, $idAe);
    }
}

function pago_supervisor_anular(PDO $pdo, int $idPago, string $motivo, int $idUsuario): array
{
    if (!pago_supervisor_puede()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $motivo = trim($motivo);
    if ($motivo === '') {
        return ['ok' => false, 'message' => 'Indique el motivo de la anulación'];
    }
    $pago = pago_supervisor_obtener($pdo, $idPago);
    if (!$pago) {
        return ['ok' => false, 'message' => 'Pago no encontrado'];
    }
    if (($pago['estado'] ?? 'activo') === 'anulado') {
        return ['ok' => false, 'message' => 'El pago ya está anulado'];
    }
    $idAlumno = (int) $pago['id_alumno'];
    $idPlantel = plantel_id_activo();
    if (!pago_supervisor_alumno_en_plantel($pdo, $idAlumno, $idPlantel)) {
        return ['ok' => false, 'message' => 'El pago no pertenece a este plantel'];
    }

    $pdo->prepare(
        'UPDATE alumno_pagos SET estado = \'anulado\', anulado_en = NOW(), anulado_por = ?, anulado_motivo = ?
         WHERE id_pago = ?'
    )->execute([$idUsuario, $motivo, $idPago]);

    pago_supervisor_registrar_movimiento(
        $pdo,
        $idPago,
        $idAlumno,
        'anular',
        $motivo,
        $idUsuario,
        pago_supervisor_snapshot($pago)
    );

    pago_supervisor_sync_inscripcion_tras_cambio($pdo, $pago);

    return ['ok' => true, 'message' => 'Pago anulado'];
}

function pago_supervisor_editar(PDO $pdo, int $idPago, array $cambios, string $motivo, int $idUsuario): array
{
    if (!pago_supervisor_puede()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $motivo = trim($motivo);
    if ($motivo === '') {
        return ['ok' => false, 'message' => 'Indique el motivo de la edición'];
    }
    $pago = pago_supervisor_obtener($pdo, $idPago);
    if (!$pago) {
        return ['ok' => false, 'message' => 'Pago no encontrado'];
    }
    if (($pago['estado'] ?? 'activo') === 'anulado') {
        return ['ok' => false, 'message' => 'No se puede editar un pago anulado'];
    }

    $idAlumno = (int) $pago['id_alumno'];
    $idPlantel = plantel_id_activo();
    if (!pago_supervisor_alumno_en_plantel($pdo, $idAlumno, $idPlantel)) {
        return ['ok' => false, 'message' => 'El pago no pertenece a este plantel'];
    }

    $nuevoMonto = isset($cambios['monto']) ? (float) $cambios['monto'] : (float) $pago['monto'];
    $nuevoConcepto = trim((string) ($cambios['concepto'] ?? $pago['concepto'] ?? ''));
    if ($nuevoMonto <= 0) {
        return ['ok' => false, 'message' => 'Monto inválido'];
    }

    $tipoMov = 'editar_monto';
    if (abs($nuevoMonto - (float) $pago['monto']) < 0.009 && $nuevoConcepto !== (string) ($pago['concepto'] ?? '')) {
        $tipoMov = 'editar_concepto';
    } elseif (abs($nuevoMonto - (float) $pago['monto']) < 0.009 && $nuevoConcepto === (string) ($pago['concepto'] ?? '')) {
        return ['ok' => false, 'message' => 'No hay cambios que aplicar'];
    }

    $snapshot = pago_supervisor_snapshot($pago);
    $data = [
        'id_alumno' => $idAlumno,
        'id_especialidad' => $pago['id_especialidad'],
        'id_alumno_especialidad' => $pago['id_alumno_especialidad'],
        'tipo' => $pago['tipo'],
        'id_producto' => $pago['id_producto'],
        'folio' => $pago['folio'],
        'monto' => $nuevoMonto,
        'forma_pago_efectivo' => $pago['forma_pago'],
        'cuenta_contable' => $pago['cuenta_contable'],
        'concepto' => $nuevoConcepto !== '' ? $nuevoConcepto : ($pago['concepto'] ?? ''),
        'periodo_ref' => $pago['periodo_ref'],
        'aplico_pronto_pago' => $pago['aplico_pronto_pago'],
        'id_beca' => $pago['id_beca'],
        'id_promocion' => $pago['id_promocion'],
        'monto_descuento' => $pago['monto_descuento'],
        'motivo_descuento' => $pago['motivo_descuento'],
        'id_autoriza' => $pago['id_autoriza'],
        'cubrio' => $pago['cubrio'],
        'creado_en' => $pago['creado_en'],
        'origen_cartas' => !empty($pago['origen_cartas']),
    ];

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE alumno_pagos SET estado = \'anulado\', anulado_en = NOW(), anulado_por = ?, anulado_motivo = ?
             WHERE id_pago = ? AND estado = \'activo\''
        )->execute([$idUsuario, 'Edición: ' . $motivo, $idPago]);

        $resNuevo = pago_registrar($pdo, $data);
        if (!$resNuevo['ok']) {
            $pdo->rollBack();
            return $resNuevo;
        }

        $idNuevo = (int) ($resNuevo['id_pago'] ?? 0);
        $pdo->prepare('UPDATE alumno_pagos SET id_pago_reemplazo = ? WHERE id_pago = ?')
            ->execute([$idNuevo, $idPago]);

        pago_supervisor_registrar_movimiento(
            $pdo,
            $idPago,
            $idAlumno,
            $tipoMov,
            $motivo,
            $idUsuario,
            $snapshot,
            $idNuevo
        );

        pago_supervisor_sync_inscripcion_tras_cambio($pdo, $pago);

        $pdo->commit();

        return ['ok' => true, 'message' => 'Pago corregido', 'id_pago_nuevo' => $idNuevo];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => 'Error al corregir el pago: ' . $e->getMessage()];
    }
}

/**
 * @param array{desde?:string,hasta?:string,agrupar?:string} $filtros
 * @return array{filas:list<array>,resumen:array}
 */
function pago_supervisor_reporte_anulados(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    pago_supervisor_ensure_schema($pdo);
    $desde = $filtros['desde'] ?? date('Y-m-01');
    $hasta = $filtros['hasta'] ?? date('Y-m-d');

    $sql = 'SELECT m.*, ap.folio, ap.monto, ap.tipo, ap.concepto, ap.creado_en AS fecha_pago,
                   a.numero_control, TRIM(CONCAT(a.nombres, \' \', a.apellido_paterno)) AS alumno_nombre,
                   CONCAT(u.nombre, \' \', u.apellido) AS usuario_nombre
            FROM alumno_pago_movimiento m
            INNER JOIN alumno_pagos ap ON ap.id_pago = m.id_pago
            INNER JOIN alumnos a ON a.id_alumno = m.id_alumno
            LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
            WHERE a.id_plantel = ? AND DATE(m.creado_en) BETWEEN ? AND ?';
    $params = [$idPlantel, $desde, $hasta];
    $sql .= ' ORDER BY m.creado_en DESC';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);

    $porTipo = ['anular' => 0, 'editar_monto' => 0, 'editar_concepto' => 0];
    foreach ($filas as $f) {
        $t = (string) ($f['tipo'] ?? '');
        if (isset($porTipo[$t])) {
            $porTipo[$t]++;
        }
    }

    return [
        'filas' => $filas,
        'resumen' => [
            'total' => count($filas),
            'anulaciones' => $porTipo['anular'],
            'ediciones' => $porTipo['editar_monto'] + $porTipo['editar_concepto'],
            'desde' => $desde,
            'hasta' => $hasta,
        ],
    ];
}
