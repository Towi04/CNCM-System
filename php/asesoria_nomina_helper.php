<?php

/**
 * Puente asesorías → nómina.
 */

function asesoria_nomina_pendientes(PDO $pdo, int $idPlantel, ?string $desde = null, ?string $hasta = null): array
{
    asesoria_ensure_schema($pdo);
    $sql = 'SELECT p.*, c.fecha, c.hora_inicio, CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre
            FROM asesoria_pago_profesor p
            INNER JOIN asesoria_cita c ON c.id_cita = p.id_cita
            INNER JOIN usuarios u ON u.id_usuario = p.id_profesor
            WHERE c.id_plantel = ? AND p.liquidado = 0';
    $params = [$idPlantel];
    if ($desde && $hasta) {
        $sql .= ' AND c.fecha BETWEEN ? AND ?';
        $params[] = $desde;
        $params[] = $hasta;
    }
    $sql .= ' ORDER BY c.fecha ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function asesoria_nomina_importar(PDO $pdo, int $idLiquidacion, int $idPlantel, ?string $desde = null, ?string $hasta = null): array
{
    if (!function_exists('nomina_puede_generar') || !nomina_puede_generar()) {
        return ['ok' => false, 'message' => 'Sin permiso de nómina'];
    }
    $pend = asesoria_nomina_pendientes($pdo, $idPlantel, $desde, $hasta);
    if ($pend === []) {
        return ['ok' => true, 'message' => 'Sin pagos de asesoría pendientes', 'importados' => 0];
    }
    $importados = 0;
    foreach ($pend as $p) {
        $st = $pdo->prepare(
            'INSERT INTO nomina_linea (id_liquidacion, id_usuario, concepto, cantidad, tarifa, importe, detalle_json, es_manual, origen)
             VALUES (?,?,?,?,?,?,?,0,?)'
        );
        $concepto = 'Asesoría — ' . ($p['fecha'] ?? '') . ' ' . ($p['hora_inicio'] ?? '') . 'h';
        $importe = round((float) $p['importe'], 2);
        $st->execute([
            $idLiquidacion,
            (int) $p['id_profesor'],
            $concepto,
            1,
            $importe,
            $importe,
            json_encode(['id_cita' => $p['id_cita'], 'id_pago_asesoria' => $p['id_pago']], JSON_UNESCAPED_UNICODE),
            'asesoria',
        ]);
        $idLinea = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE asesoria_pago_profesor SET liquidado = 1, id_nomina_linea = ? WHERE id_pago = ?')
            ->execute([$idLinea, (int) $p['id_pago']]);
        $importados++;
    }

    return ['ok' => true, 'message' => "Importados $importados pagos de asesoría", 'importados' => $importados];
}

function asesoria_nomina_resumen_semana(PDO $pdo, int $idPlantel, string $desde, string $hasta): array
{
    $st = $pdo->prepare(
        'SELECT p.id_profesor, CONCAT(u.nombre, \' \', u.apellido) AS nombre,
                COUNT(*) AS sesiones, SUM(p.importe) AS total
         FROM asesoria_pago_profesor p
         INNER JOIN asesoria_cita c ON c.id_cita = p.id_cita
         INNER JOIN usuarios u ON u.id_usuario = p.id_profesor
         WHERE c.id_plantel = ? AND c.fecha BETWEEN ? AND ?
         GROUP BY p.id_profesor, u.nombre
         ORDER BY u.nombre'
    );
    $st->execute([$idPlantel, $desde, $hasta]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
