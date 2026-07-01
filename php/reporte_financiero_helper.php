<?php

function reporte_financiero_puede_ver(): bool
{
    return function_exists('rbac_cap') && rbac_cap('menu_reportes');
}

/** @return array{desde: string, hasta: string} */
function reporte_financiero_rango(?string $desde, ?string $hasta): array
{
    $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $hasta) ? $hasta : date('Y-m-d');
    $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $desde)
        ? $desde
        : date('Y-m-01', strtotime($hasta));

    if ($desde > $hasta) {
        [$desde, $hasta] = [$hasta, $desde];
    }

    return ['desde' => $desde, 'hasta' => $hasta];
}

/** @return array{filas: list<array>, resumen: array<string, mixed>} */
function reporte_ventas_listar(PDO $pdo, int $idPlantel, string $desde, string $hasta, ?string $tipo = null): array
{
    $params = [$idPlantel, $desde . ' 00:00:00', $hasta . ' 23:59:59'];
    $sql = 'SELECT p.id_pago, p.folio, p.tipo, p.monto, p.monto_descuento, p.forma_pago, p.concepto, p.creado_en,
                   a.numero_control,
                   TRIM(CONCAT(COALESCE(a.nombres, a.nombre, \'\'), \' \', COALESCE(a.apellido_paterno, a.apellido, \'\'))) AS alumno,
                   e.nombre AS especialidad,
                   CONCAT(u.nombre, \' \', u.apellido) AS cajero
            FROM alumno_pagos p
            INNER JOIN alumnos a ON a.id_alumno = p.id_alumno
            LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
            LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
            WHERE p.id_plantel = ? AND p.creado_en BETWEEN ? AND ?' . pago_sql_filtro_activos('p');
    if ($tipo !== null && $tipo !== '') {
        $sql .= ' AND p.tipo = ?';
        $params[] = $tipo;
    } else {
        $sql .= " AND p.tipo <> 'producto'";
    }
    $sql .= ' ORDER BY p.creado_en DESC, p.id_pago DESC LIMIT 2000';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);

    $total = 0.0;
    $desc = 0.0;
    $porTipo = [];
    foreach ($filas as $r) {
        $m = (float) ($r['monto'] ?? 0);
        $d = (float) ($r['monto_descuento'] ?? 0);
        $total += $m;
        $desc += $d;
        $t = (string) ($r['tipo'] ?? 'otro');
        $porTipo[$t] = ($porTipo[$t] ?? 0) + $m;
    }

    return [
        'filas' => $filas,
        'resumen' => [
            'total' => $total,
            'total_fmt' => catalog_format_mxn($total),
            'descuentos' => $desc,
            'descuentos_fmt' => catalog_format_mxn($desc),
            'cantidad' => count($filas),
            'por_tipo' => $porTipo,
        ],
    ];
}

/** @return array{filas: list<array>, resumen: array<string, mixed>} */
function reporte_ventas_productos_listar(PDO $pdo, int $idPlantel, string $desde, string $hasta): array
{
    $st = $pdo->prepare(
        'SELECT p.id_pago, p.folio, p.monto, p.forma_pago, p.creado_en, p.concepto,
                pr.nombre AS producto, pr.clave AS producto_clave,
                a.numero_control,
                TRIM(CONCAT(COALESCE(a.nombres, a.nombre, \'\'), \' \', COALESCE(a.apellido_paterno, a.apellido, \'\'))) AS alumno,
                CONCAT(u.nombre, \' \', u.apellido) AS cajero
         FROM alumno_pagos p
         INNER JOIN alumnos a ON a.id_alumno = p.id_alumno
         LEFT JOIN productos pr ON pr.id_producto = p.id_producto
         LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
         WHERE p.id_plantel = ? AND p.tipo = \'producto\'
           AND p.creado_en BETWEEN ? AND ?' . pago_sql_filtro_activos('p') . '
         ORDER BY p.creado_en DESC LIMIT 2000'
    );
    $st->execute([$idPlantel, $desde . ' 00:00:00', $hasta . ' 23:59:59']);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);

    $total = 0.0;
    $unidades = 0;
    $porProducto = [];
    foreach ($filas as $r) {
        $m = (float) ($r['monto'] ?? 0);
        $c = 1;
        $total += $m;
        $unidades += $c;
        $nom = (string) ($r['producto'] ?? 'Sin nombre');
        if (!isset($porProducto[$nom])) {
            $porProducto[$nom] = ['monto' => 0.0, 'cantidad' => 0];
        }
        $porProducto[$nom]['monto'] += $m;
        $porProducto[$nom]['cantidad'] += $c;
    }

    return [
        'filas' => $filas,
        'resumen' => [
            'total' => $total,
            'total_fmt' => catalog_format_mxn($total),
            'unidades' => $unidades,
            'cantidad' => count($filas),
            'por_producto' => $porProducto,
        ],
    ];
}

/** @return array{desde: string, hasta: string, etiqueta: string} */
function reporte_financiero_rango_modo(string $modo, ?string $fechaRef = null): array
{
    $fechaRef = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fechaRef) ? $fechaRef : date('Y-m-d');
    $dt = new DateTimeImmutable($fechaRef);

    switch ($modo) {
        case 'semana':
            $dow = (int) $dt->format('w');
            $desdeDt = $dt->modify('-' . $dow . ' days');
            $hastaDt = $desdeDt->modify('+6 days');
            $etiqueta = 'Semana del ' . $desdeDt->format('d/m/Y') . ' al ' . $hastaDt->format('d/m/Y');
            break;
        case 'mes':
            $desdeDt = $dt->modify('first day of this month');
            $hastaDt = $dt->modify('last day of this month');
            $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $etiqueta = ucfirst($meses[(int) $dt->format('n')]) . ' ' . $dt->format('Y');
            break;
        case 'anio':
            $desdeDt = new DateTimeImmutable($dt->format('Y-01-01'));
            $hastaDt = new DateTimeImmutable($dt->format('Y-12-31'));
            $etiqueta = 'Año ' . $dt->format('Y');
            break;
        default:
            $desdeDt = $hastaDt = $dt;
            $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $etiqueta = (int) $dt->format('j') . ' de ' . $meses[(int) $dt->format('n')] . ' del ' . $dt->format('Y');
            break;
    }

    return [
        'desde' => $desdeDt->format('Y-m-d'),
        'hasta' => $hastaDt->format('Y-m-d'),
        'etiqueta' => $etiqueta,
    ];
}

/** @return list<array<string, mixed>> */
function reporte_ventas_agrupar_por_folio(array $filas): array
{
    $map = [];
    foreach ($filas as $r) {
        $folio = trim((string) ($r['folio'] ?? ''));
        $key = $folio !== '' ? $folio : 'pago_' . ($r['id_pago'] ?? 0);
        if (!isset($map[$key])) {
            $map[$key] = $r;
            $map[$key]['monto'] = (float) ($r['monto'] ?? 0);
            $map[$key]['conceptos'] = [trim((string) ($r['concepto'] ?? ''))];
            continue;
        }
        $map[$key]['monto'] = round((float) $map[$key]['monto'] + (float) ($r['monto'] ?? 0), 2);
        $c = trim((string) ($r['concepto'] ?? ''));
        if ($c !== '' && !in_array($c, $map[$key]['conceptos'], true)) {
            $map[$key]['conceptos'][] = $c;
        }
    }

    $out = [];
    foreach ($map as $row) {
        $conceptos = array_values(array_filter($row['conceptos'] ?? []));
        $row['concepto'] = implode("\n", $conceptos);
        unset($row['conceptos']);
        $out[] = $row;
    }

    usort($out, static function ($a, $b) {
        return strcmp((string) ($b['creado_en'] ?? ''), (string) ($a['creado_en'] ?? ''));
    });

    return $out;
}

/** @return array{filas: list<array>, resumen: array<string, mixed>} */
function reporte_ventas_por_cuenta(
    PDO $pdo,
    int $idPlantel,
    string $desde,
    string $hasta,
    ?string $cuenta = null,
    ?string $buscar = null
): array {
    $params = [$idPlantel, $desde . ' 00:00:00', $hasta . ' 23:59:59'];
    $sql = 'SELECT p.id_pago, p.folio, p.tipo, p.monto, p.monto_descuento, p.forma_pago, p.cuenta_contable,
                   p.concepto, p.cubrio, p.creado_en,
                   a.numero_control,
                   TRIM(CONCAT(COALESCE(a.nombres, a.nombre, \'\'), \' \', COALESCE(a.apellido_paterno, a.apellido, \'\'))) AS alumno,
                   e.nombre AS especialidad,
                   CONCAT(u.nombre, \' \', u.apellido) AS cajero,
                   g.grupo_nombre AS grupo
            FROM alumno_pagos p
            INNER JOIN alumnos a ON a.id_alumno = p.id_alumno
            LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
            LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
            LEFT JOIN (
                SELECT ag.id_alumno,
                       MAX(COALESCE(gr.clave, \'\')) AS grupo_nombre
                FROM alumno_grupos ag
                INNER JOIN grupos gr ON gr.id_grupo = ag.id_grupo
                WHERE ag.activo = 1
                GROUP BY ag.id_alumno
            ) g ON g.id_alumno = a.id_alumno
            WHERE p.id_plantel = ? AND p.creado_en BETWEEN ? AND ?
              AND p.tipo <> \'producto\'' . pago_sql_filtro_activos('p');
    if ($cuenta === 'A' || $cuenta === 'B') {
        $sql .= ' AND COALESCE(p.cuenta_contable, \'B\') = ?';
        $params[] = $cuenta;
    }
    if ($buscar !== null && trim($buscar) !== '') {
        $like = '%' . trim($buscar) . '%';
        $sql .= ' AND (p.folio LIKE ? OR a.numero_control LIKE ? OR a.nombres LIKE ? OR a.apellido_paterno LIKE ? OR p.concepto LIKE ?)';
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }
    $sql .= ' ORDER BY p.creado_en DESC, p.id_pago DESC LIMIT 3000';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $filasRaw = $st->fetchAll(PDO::FETCH_ASSOC);
    $filas = reporte_ventas_agrupar_por_folio($filasRaw);

    $total = 0.0;
    $porForma = [];
    foreach ($filas as $r) {
        $m = (float) ($r['monto'] ?? 0);
        $total += $m;
        $fp = reporte_financiero_etiqueta_forma_pago((string) ($r['forma_pago'] ?? ''));
        $porForma[$fp] = ($porForma[$fp] ?? 0) + $m;
    }

    return [
        'filas' => $filas,
        'resumen' => [
            'total' => $total,
            'total_fmt' => catalog_format_mxn($total),
            'cantidad' => count($filas),
            'por_forma_pago' => $porForma,
        ],
    ];
}

function reporte_financiero_etiqueta_forma_pago(string $forma): string
{
    $f = mb_strtolower(trim($forma));
    if (str_contains($f, 'débito') || str_contains($f, 'debito')) {
        return 'Tarjeta de debito';
    }
    if (str_contains($f, 'crédito') || str_contains($f, 'credito')) {
        return 'Tarjeta de credito';
    }
    if (str_contains($f, 'transfer')) {
        return 'Transferencia';
    }
    if (str_contains($f, 'tarjeta')) {
        return 'Tarjeta de debito';
    }
    if (str_contains($f, 'efectivo')) {
        return 'Efectivo';
    }

    return $forma !== '' ? $forma : 'Efectivo';
}

function reporte_financiero_clasificar_medio(string $forma): string
{
    $f = mb_strtolower(trim($forma));
    if (str_contains($f, 'transfer')) {
        return 'transferencia';
    }
    if (str_contains($f, 'tarjeta') || str_contains($f, 'débito') || str_contains($f, 'debito')
        || str_contains($f, 'crédito') || str_contains($f, 'credito')) {
        return 'terminal';
    }

    return 'efectivo';
}

/** @return array<string, mixed> */
function reporte_corte_caja_calcular(PDO $pdo, int $idPlantel, string $fecha, string $cuenta = 'B'): array
{
    $st = $pdo->prepare(
        'SELECT p.monto, p.forma_pago, COALESCE(p.cuenta_contable, \'B\') AS cuenta_contable
         FROM alumno_pagos p
         WHERE p.id_plantel = ? AND DATE(p.creado_en) = ?' . pago_sql_filtro_activos('p')
    );
    $st->execute([$idPlantel, $fecha]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $ingreso = 0.0;
    $terminal = 0.0;
    $transferencia = 0.0;
    $efectivo = 0.0;
    foreach ($rows as $r) {
        $m = (float) ($r['monto'] ?? 0);
        $ct = (string) ($r['cuenta_contable'] ?? 'B');
        if ($cuenta !== '' && $ct !== $cuenta) {
            continue;
        }
        $ingreso += $m;
        $medio = reporte_financiero_clasificar_medio((string) ($r['forma_pago'] ?? ''));
        if ($medio === 'terminal') {
            $terminal += $m;
        } elseif ($medio === 'transferencia') {
            $transferencia += $m;
        } else {
            $efectivo += $m;
        }
    }

    $guardado = reporte_corte_caja_obtener($pdo, $idPlantel, $fecha, $cuenta);

    return [
        'fecha' => $fecha,
        'cuenta' => $cuenta,
        'ingreso_sistema' => round($ingreso, 2),
        'ingreso_sistema_fmt' => catalog_format_mxn($ingreso),
        'terminal' => round($terminal, 2),
        'terminal_fmt' => catalog_format_mxn($terminal),
        'transferencia' => round($transferencia, 2),
        'transferencia_fmt' => catalog_format_mxn($transferencia),
        'efectivo_sistema' => round($efectivo, 2),
        'efectivo_sistema_fmt' => catalog_format_mxn($efectivo),
        'guardado' => $guardado,
    ];
}

/** @return array<string, mixed>|null */
function reporte_corte_caja_obtener(PDO $pdo, int $idPlantel, string $fecha, string $cuenta = 'B'): ?array
{
    $st = $pdo->prepare(
        'SELECT c.*, CONCAT(u.nombre, \' \', u.apellido) AS usuario_nombre
         FROM corte_caja c
         LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario
         WHERE c.id_plantel = ? AND c.fecha = ? AND c.cuenta = ?
         ORDER BY c.id_corte DESC LIMIT 1'
    );
    $st->execute([$idPlantel, $fecha, $cuenta]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @param array<string, mixed> $data */
function reporte_corte_caja_guardar(PDO $pdo, int $idPlantel, int $idUsuario, array $data): array
{
    $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($data['fecha'] ?? ''))
        ? $data['fecha']
        : date('Y-m-d');
    $cuenta = in_array($data['cuenta'] ?? '', ['A', 'B'], true) ? $data['cuenta'] : 'B';
    $ingreso = catalog_money($data['ingreso_sistema'] ?? 0);
    $retiros = catalog_money($data['retiros'] ?? 0);
    $comprobantes = catalog_money($data['comprobantes'] ?? 0);
    $efectivoContado = isset($data['efectivo_contado']) ? catalog_money($data['efectivo_contado']) : null;
    $notas = trim((string) ($data['notas'] ?? ''));

    $existente = reporte_corte_caja_obtener($pdo, $idPlantel, $fecha, $cuenta);
    if ($existente) {
        $st = $pdo->prepare(
            'UPDATE corte_caja SET id_usuario = ?, ingreso_sistema = ?, retiros = ?, comprobantes = ?,
             efectivo_contado = ?, notas = ?, creado_en = NOW()
             WHERE id_corte = ?'
        );
        $st->execute([
            $idUsuario,
            $ingreso,
            $retiros,
            $comprobantes,
            $efectivoContado,
            $notas !== '' ? $notas : null,
            (int) $existente['id_corte'],
        ]);
        $id = (int) $existente['id_corte'];
    } else {
        $st = $pdo->prepare(
            'INSERT INTO corte_caja (id_plantel, id_usuario, fecha, cuenta, ingreso_sistema, retiros, comprobantes, efectivo_contado, notas)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $idPlantel,
            $idUsuario,
            $fecha,
            $cuenta,
            $ingreso,
            $retiros,
            $comprobantes,
            $efectivoContado,
            $notas !== '' ? $notas : null,
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    return ['ok' => true, 'id_corte' => $id, 'message' => 'Corte guardado'];
}

/** @return array{filas: list<array>, resumen: array<string, mixed>} */
function reporte_apoyos_inscripcion_listar(PDO $pdo, int $idPlantel, string $desde, string $hasta): array
{
    if (function_exists('pago_ensure_schema')) {
        pago_ensure_schema($pdo);
    }
    if (function_exists('operativo_cncm_ensure_schema')) {
        operativo_cncm_ensure_schema($pdo);
    }

    $st = $pdo->prepare(
        'SELECT p.id_pago, p.folio, p.monto, p.monto_descuento, p.motivo_descuento, p.creado_en,
                p.monto_apoyo, p.etiqueta_apoyo,
                a.numero_control,
                TRIM(CONCAT(COALESCE(a.nombres, a.nombre, \'\'), \' \', COALESCE(a.apellido_paterno, a.apellido, \'\'))) AS alumno,
                e.nombre AS especialidad,
                b.motivo AS beca_motivo, b.tipo AS beca_tipo, b.valor AS beca_valor,
                pr.nombre AS promo_nombre,
                COALESCE(CONCAT(ua.nombre, \' \', ua.apellido), CONCAT(ud.nombre, \' \', ud.apellido)) AS autoriza
         FROM alumno_pagos p
         INNER JOIN alumnos a ON a.id_alumno = p.id_alumno
         LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
         LEFT JOIN alumno_becas b ON b.id_beca = p.id_beca
         LEFT JOIN promociones_descuento pr ON pr.id_promocion = p.id_promocion
         LEFT JOIN usuarios ua ON ua.id_usuario = p.id_autoriza
         LEFT JOIN usuarios ud ON ud.id_usuario = p.id_autoriza_director
         WHERE p.id_plantel = ? AND p.tipo = \'inscripcion\'
           AND p.creado_en BETWEEN ? AND ?' . pago_sql_filtro_activos('p') . '
           AND (p.monto_descuento > 0 OR p.id_beca IS NOT NULL OR p.id_promocion IS NOT NULL
                OR p.monto_apoyo > 0 OR p.etiqueta_apoyo IS NOT NULL)
         ORDER BY p.creado_en DESC LIMIT 2000'
    );
    $st->execute([$idPlantel, $desde . ' 00:00:00', $hasta . ' 23:59:59']);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($filas as &$r) {
        if (empty($r['beca_nombre'])) {
            if (!empty($r['promo_nombre'])) {
                $r['beca_nombre'] = $r['promo_nombre'];
            } elseif (!empty($r['beca_motivo'])) {
                $r['beca_nombre'] = $r['beca_motivo'];
                if (!empty($r['beca_tipo']) && isset($r['beca_valor'])) {
                    $r['beca_nombre'] .= ' (' . ($r['beca_tipo'] === 'porcentaje'
                        ? (float) $r['beca_valor'] . '%'
                        : catalog_format_mxn((float) $r['beca_valor'])) . ')';
                }
            } elseif (!empty($r['etiqueta_apoyo'])) {
                $r['beca_nombre'] = $r['etiqueta_apoyo'];
            }
        }
    }
    unset($r);

    $totalDesc = 0.0;
    foreach ($filas as $r) {
        $totalDesc += (float) ($r['monto_descuento'] ?? 0);
    }

    return [
        'filas' => $filas,
        'resumen' => [
            'cantidad' => count($filas),
            'descuento_total' => $totalDesc,
            'descuento_total_fmt' => catalog_format_mxn($totalDesc),
        ],
    ];
}
