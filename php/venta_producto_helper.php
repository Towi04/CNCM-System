<?php

/**
 * Venta de productos en recepción: carrito, inventario opcional, público general.
 */

function venta_producto_puede_acceder(): bool
{
    return function_exists('rbac_cap') && rbac_cap('menu_venta_productos');
}

function venta_producto_ensure_schema(PDO $pdo): void
{
    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column(
            $pdo,
            'productos',
            'controla_inventario',
            'TINYINT(1) NOT NULL DEFAULT 1 COMMENT \'0=servicio/sin stock (TOEFL, copias)\'',
            'stock_minimo'
        );
        plantel_ensure_column(
            $pdo,
            'alumno_pagos',
            'cliente_nombre',
            'VARCHAR(160) NULL COMMENT \'Nombre en ticket si no es alumno registrado\'',
            'concepto'
        );
    }
}

/** Productos disponibles para venta según inventario del plantel. */
function catalog_listar_productos_venta(PDO $pdo, int $idPlantel): array
{
    venta_producto_ensure_schema($pdo);

    $st = $pdo->prepare(
        'SELECT p.id_producto, p.clave, p.nombre, p.descripcion, p.precio,
                COALESCE(p.controla_inventario, 1) AS controla_inventario,
                COALESCE(i.existencia, 0) AS existencia
         FROM productos p
         LEFT JOIN producto_inventario i ON i.id_producto = p.id_producto AND i.id_plantel = ?
         WHERE p.activo = 1 AND p.visible = 1 AND p.descontinuado = 0
           AND (
             COALESCE(p.controla_inventario, 1) = 0
             OR COALESCE(i.existencia, 0) > 0
           )
         ORDER BY p.orden ASC, p.nombre ASC'
    );
    $st->execute([$idPlantel]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['precio'] = (float) ($r['precio'] ?? 0);
        $r['existencia'] = (int) ($r['existencia'] ?? 0);
        $r['controla_inventario'] = (int) ($r['controla_inventario'] ?? 1);
        $r['sin_limite'] = $r['controla_inventario'] === 0 ? 1 : 0;
    }
    unset($r);

    return $rows;
}

/** Alumno ficticio por plantel para ventas al público (sin registro de cliente). */
function venta_producto_id_alumno_publico(PDO $pdo, int $idPlantel): int
{
    alumno_ensure_schema($pdo);
    $control = 'PUB-' . str_pad((string) $idPlantel, 4, '0', STR_PAD_LEFT);

    $st = $pdo->prepare(
        'SELECT id_alumno FROM alumnos WHERE id_plantel = ? AND numero_control = ? LIMIT 1'
    );
    $st->execute([$idPlantel, $control]);
    $id = (int) ($st->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }

    $pdo->prepare(
        'INSERT INTO alumnos (
            id_plantel, numero_control, matricula, nombres, apellido_paterno,
            nombre, apellido, estado, fecha_alta
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())'
    )->execute([
        $idPlantel,
        $control,
        $control,
        'PÚBLICO',
        'GENERAL',
        'PÚBLICO',
        'GENERAL',
        'activo',
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array{
 *     id_alumno?: int,
 *     cliente_nombre?: string,
 *     forma_pago?: string,
 *     items: list<array{id_producto: int, cantidad?: int}>
 * } $opts
 * @return array{ok:bool, message?:string, folio?:string, id_pago?:int, pagos?:list<int>}
 */
function venta_producto_registrar_carrito(PDO $pdo, array $opts): array
{
    venta_producto_ensure_schema($pdo);

    $idPlantel = plantel_scope_id($pdo);
    $idAlumno = (int) ($opts['id_alumno'] ?? 0);
    $clienteNombre = trim((string) ($opts['cliente_nombre'] ?? ''));
    $formaPago = trim((string) ($opts['forma_pago'] ?? 'Efectivo'));
    $items = $opts['items'] ?? [];

    if (!is_array($items) || $items === []) {
        return ['ok' => false, 'message' => 'Agregue al menos un producto al carrito'];
    }

    if ($idAlumno <= 0) {
        if ($clienteNombre === '') {
            return ['ok' => false, 'message' => 'Seleccione un alumno o escriba el nombre del comprador'];
        }
        $idAlumno = venta_producto_id_alumno_publico($pdo, $idPlantel);
    } elseif (!plantel_enforce_alumno($pdo, $idAlumno, $idPlantel)) {
        return ['ok' => false, 'message' => 'El alumno no pertenece a este plantel'];
    } else {
        $clienteNombre = '';
    }

    if ($formaPago === '') {
        $formaPago = 'Efectivo';
    }

    $lineasCarrito = [];
    $total = 0.0;

    foreach ($items as $it) {
        $idProd = (int) ($it['id_producto'] ?? 0);
        $cant = max(1, (int) ($it['cantidad'] ?? 1));
        if ($idProd <= 0) {
            continue;
        }

        $st = $pdo->prepare(
            'SELECT p.*, COALESCE(i.existencia, 0) AS existencia
             FROM productos p
             LEFT JOIN producto_inventario i ON i.id_producto = p.id_producto AND i.id_plantel = ?
             WHERE p.id_producto = ? AND p.activo = 1 LIMIT 1'
        );
        $st->execute([$idPlantel, $idProd]);
        $prod = $st->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            return ['ok' => false, 'message' => 'Producto no encontrado o inactivo'];
        }

        $controlaInv = (int) ($prod['controla_inventario'] ?? 1) === 1;
        $existencia = (int) ($prod['existencia'] ?? 0);
        if ($controlaInv && $existencia < $cant) {
            return [
                'ok' => false,
                'message' => 'Stock insuficiente para «' . ($prod['nombre'] ?? '') . '» (disponible: ' . $existencia . ')',
            ];
        }

        $precio = catalog_money($prod['precio'] ?? 0);
        $sub = round($precio * $cant, 2);
        $total += $sub;
        $lineasCarrito[] = [
            'id_producto' => $idProd,
            'nombre' => (string) ($prod['nombre'] ?? ''),
            'cantidad' => $cant,
            'precio' => $precio,
            'subtotal' => $sub,
            'controla_inventario' => $controlaInv,
        ];
    }

    if ($lineasCarrito === []) {
        return ['ok' => false, 'message' => 'Carrito vacío'];
    }

    $folio = 'VP-' . time();
    $desgloseTicket = [];
    foreach ($lineasCarrito as $ln) {
        $desc = $ln['nombre'] . ($ln['cantidad'] > 1 ? ' × ' . $ln['cantidad'] : '');
        $desgloseTicket[] = [
            'descripcion' => $desc,
            'monto' => $ln['subtotal'],
            'monto_fmt' => pago_ticket_format_mxn($ln['subtotal']),
        ];
    }
    $cubrioCompleto = pago_desglose_a_cubrio($desgloseTicket);

    $pagosIds = [];
    $pdo->beginTransaction();
    try {
        foreach ($lineasCarrito as $idx => $ln) {
            if ($ln['controla_inventario']) {
                $resInv = pago_descontar_inventario($pdo, $ln['id_producto'], $ln['cantidad'], 'Venta de productos');
                if (!$resInv['ok']) {
                    throw new RuntimeException($resInv['message'] ?? 'Error de inventario');
                }
            }

            $concepto = $ln['nombre'] . ($ln['cantidad'] > 1 ? ' (×' . $ln['cantidad'] . ')' : '');
            $res = pago_registrar($pdo, [
                'id_alumno' => $idAlumno,
                'tipo' => 'producto',
                'id_producto' => $ln['id_producto'],
                'cantidad' => $ln['cantidad'],
                'monto' => $ln['subtotal'],
                'folio' => $folio,
                'forma_pago_efectivo' => $formaPago,
                'cuenta_contable' => 'B',
                'concepto' => $concepto,
                'cubrio' => $idx === 0 ? $cubrioCompleto : '',
                'cliente_nombre' => $clienteNombre !== '' ? $clienteNombre : null,
                'omitir_inventario' => true,
            ]);
            if (!$res['ok']) {
                throw new RuntimeException($res['message'] ?? 'No se pudo registrar la venta');
            }
            $pagosIds[] = (int) $res['id_pago'];
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();

        return ['ok' => false, 'message' => $e->getMessage()];
    }

    return [
        'ok' => true,
        'message' => 'Venta registrada',
        'folio' => $folio,
        'id_pago' => $pagosIds[0] ?? 0,
        'pagos' => $pagosIds,
        'total' => round($total, 2),
        'total_fmt' => catalog_format_mxn($total),
    ];
}
