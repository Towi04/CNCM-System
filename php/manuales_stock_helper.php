<?php

/**
 * Inventario ligero de manuales: stock central, tránsito y recepción por plantel.
 */

function manuales_stock_puede_stock(): bool
{
    return function_exists('rbac_cap') && (rbac_cap('menu_manuales_stock') || rbac_cap('admin_catalogo'));
}

function manuales_stock_puede_envios(): bool
{
    return function_exists('rbac_cap') && (
        rbac_cap('menu_manuales_envios')
        || rbac_cap('menu_venta_productos')
        || rbac_cap('admin_catalogo')
    );
}

function manuales_stock_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    if (function_exists('catalog_ensure_schema')) {
        catalog_ensure_schema($pdo);
    }
    if (function_exists('plantel_ensure_schema')) {
        plantel_ensure_schema($pdo);
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS producto_stock_ubicacion (
            id_stock INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_producto INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NULL,
            cantidad INT NOT NULL DEFAULT 0,
            ubicacion ENUM(\'bodega\',\'transito\',\'plantel\') NOT NULL DEFAULT \'bodega\',
            actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_stock),
            KEY idx_psu_producto (id_producto, ubicacion),
            KEY idx_psu_plantel (id_plantel, ubicacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS producto_envio (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_producto INT UNSIGNED NOT NULL,
            id_plantel_destino INT UNSIGNED NOT NULL,
            cantidad INT UNSIGNED NOT NULL,
            estado ENUM(\'pendiente\',\'en_transito\',\'recibido\') NOT NULL DEFAULT \'pendiente\',
            enviado_por INT UNSIGNED NULL,
            enviado_en DATETIME NULL,
            recibido_por INT UNSIGNED NULL,
            recibido_en DATETIME NULL,
            notas TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_pe_destino (id_plantel_destino, estado),
            KEY idx_pe_producto (id_producto, estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $done = true;
}

/** @return list<array<string,mixed>> */
function manuales_stock_productos(PDO $pdo): array
{
    manuales_stock_ensure_schema($pdo);
    $st = $pdo->query(
        "SELECT id_producto, clave, nombre, precio
         FROM productos
         WHERE COALESCE(activo, 1) = 1
         ORDER BY nombre"
    );

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string,mixed>> */
function manuales_stock_planteles(PDO $pdo): array
{
    manuales_stock_ensure_schema($pdo);
    $st = $pdo->query(
        "SELECT id_plantel, nombre
         FROM planteles
         WHERE COALESCE(activo, 1) = 1
         ORDER BY nombre"
    );

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function manuales_stock_obtener(PDO $pdo, int $idProducto, ?int $idPlantel, string $ubicacion): int
{
    manuales_stock_ensure_schema($pdo);
    $ubicacion = in_array($ubicacion, ['bodega', 'transito', 'plantel'], true) ? $ubicacion : 'bodega';
    if ($idPlantel === null || $idPlantel <= 0) {
        $st = $pdo->prepare(
            'SELECT cantidad FROM producto_stock_ubicacion
             WHERE id_producto = ? AND id_plantel IS NULL AND ubicacion = ?
             ORDER BY id_stock DESC LIMIT 1'
        );
        $st->execute([$idProducto, $ubicacion]);
    } else {
        $st = $pdo->prepare(
            'SELECT cantidad FROM producto_stock_ubicacion
             WHERE id_producto = ? AND id_plantel = ? AND ubicacion = ?
             ORDER BY id_stock DESC LIMIT 1'
        );
        $st->execute([$idProducto, $idPlantel, $ubicacion]);
    }

    return (int) ($st->fetchColumn() ?: 0);
}

function manuales_stock_set(PDO $pdo, int $idProducto, ?int $idPlantel, string $ubicacion, int $cantidad): void
{
    manuales_stock_ensure_schema($pdo);
    $ubicacion = in_array($ubicacion, ['bodega', 'transito', 'plantel'], true) ? $ubicacion : 'bodega';
    $cantidad = max(0, $cantidad);
    if ($idPlantel === null || $idPlantel <= 0) {
        $st = $pdo->prepare(
            'SELECT id_stock FROM producto_stock_ubicacion
             WHERE id_producto = ? AND id_plantel IS NULL AND ubicacion = ?
             ORDER BY id_stock DESC LIMIT 1'
        );
        $st->execute([$idProducto, $ubicacion]);
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE producto_stock_ubicacion SET cantidad = ? WHERE id_stock = ?')->execute([$cantidad, $id]);
            return;
        }
        $pdo->prepare(
            'INSERT INTO producto_stock_ubicacion (id_producto, id_plantel, cantidad, ubicacion) VALUES (?, NULL, ?, ?)'
        )->execute([$idProducto, $cantidad, $ubicacion]);
        return;
    }

    $st = $pdo->prepare(
        'SELECT id_stock FROM producto_stock_ubicacion
         WHERE id_producto = ? AND id_plantel = ? AND ubicacion = ?
         ORDER BY id_stock DESC LIMIT 1'
    );
    $st->execute([$idProducto, $idPlantel, $ubicacion]);
    $id = (int) ($st->fetchColumn() ?: 0);
    if ($id > 0) {
        $pdo->prepare('UPDATE producto_stock_ubicacion SET cantidad = ? WHERE id_stock = ?')->execute([$cantidad, $id]);
        return;
    }
    $pdo->prepare(
        'INSERT INTO producto_stock_ubicacion (id_producto, id_plantel, cantidad, ubicacion) VALUES (?, ?, ?, ?)'
    )->execute([$idProducto, $idPlantel, $cantidad, $ubicacion]);
}

function manuales_stock_ajustar(PDO $pdo, int $idProducto, ?int $idPlantel, string $ubicacion, int $delta): void
{
    $actual = manuales_stock_obtener($pdo, $idProducto, $idPlantel, $ubicacion);
    manuales_stock_set($pdo, $idProducto, $idPlantel, $ubicacion, $actual + $delta);
}

/** @return list<array<string,mixed>> */
function manuales_stock_listar(PDO $pdo): array
{
    manuales_stock_ensure_schema($pdo);
    $sql = "SELECT p.id_producto, p.clave, p.nombre,
                   COALESCE(SUM(CASE WHEN s.ubicacion = 'bodega' AND s.id_plantel IS NULL THEN s.cantidad ELSE 0 END), 0) AS bodega,
                   COALESCE(SUM(CASE WHEN s.ubicacion = 'transito' THEN s.cantidad ELSE 0 END), 0) AS transito,
                   COALESCE(SUM(CASE WHEN s.ubicacion = 'plantel' THEN s.cantidad ELSE 0 END), 0) AS planteles
            FROM productos p
            LEFT JOIN producto_stock_ubicacion s ON s.id_producto = p.id_producto
            WHERE COALESCE(p.activo, 1) = 1
            GROUP BY p.id_producto, p.clave, p.nombre
            ORDER BY p.nombre";
    $st = $pdo->query($sql);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array{ok:bool,message:string,id_envio?:int} */
function manuales_stock_crear_envio(PDO $pdo, int $idProducto, int $idPlantelDestino, int $cantidad, int $idUsuario, string $notas = ''): array
{
    if (!manuales_stock_puede_stock()) {
        return ['ok' => false, 'message' => 'Sin permiso para enviar stock'];
    }
    $cantidad = max(0, $cantidad);
    if ($idProducto <= 0 || $idPlantelDestino <= 0 || $cantidad <= 0) {
        return ['ok' => false, 'message' => 'Producto, plantel y cantidad son obligatorios'];
    }

    manuales_stock_ensure_schema($pdo);
    $pdo->beginTransaction();
    try {
        $central = manuales_stock_obtener($pdo, $idProducto, null, 'bodega');
        if ($central < $cantidad) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Stock central insuficiente'];
        }
        manuales_stock_ajustar($pdo, $idProducto, null, 'bodega', -$cantidad);
        manuales_stock_ajustar($pdo, $idProducto, $idPlantelDestino, 'transito', $cantidad);
        $pdo->prepare(
            'INSERT INTO producto_envio (id_producto, id_plantel_destino, cantidad, estado, enviado_por, enviado_en, notas)
             VALUES (?, ?, ?, \'en_transito\', ?, NOW(), ?)'
        )->execute([$idProducto, $idPlantelDestino, $cantidad, $idUsuario > 0 ? $idUsuario : null, trim($notas) ?: null]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();

        return ['ok' => true, 'message' => 'Envío registrado en tránsito', 'id_envio' => $id];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('manuales_stock_crear_envio: ' . $e->getMessage());

        return ['ok' => false, 'message' => 'No se pudo registrar el envío'];
    }
}

/** @return array{ok:bool,message:string} */
function manuales_stock_confirmar_envio(PDO $pdo, int $idEnvio, int $idPlantel, int $idUsuario): array
{
    if (!manuales_stock_puede_envios()) {
        return ['ok' => false, 'message' => 'Sin permiso para confirmar envíos'];
    }
    manuales_stock_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM producto_envio WHERE id = ? LIMIT 1');
    $st->execute([$idEnvio]);
    $env = $st->fetch(PDO::FETCH_ASSOC);
    if (!$env) {
        return ['ok' => false, 'message' => 'Envío no encontrado'];
    }
    if ((int) ($env['id_plantel_destino'] ?? 0) !== $idPlantel && (!function_exists('rbac_tiene_acceso_total') || !rbac_tiene_acceso_total())) {
        return ['ok' => false, 'message' => 'El envío pertenece a otro plantel'];
    }
    if (($env['estado'] ?? '') === 'recibido') {
        return ['ok' => false, 'message' => 'El envío ya fue recibido'];
    }

    $idProducto = (int) $env['id_producto'];
    $idDestino = (int) $env['id_plantel_destino'];
    $cantidad = (int) $env['cantidad'];
    $pdo->beginTransaction();
    try {
        manuales_stock_ajustar($pdo, $idProducto, $idDestino, 'transito', -$cantidad);
        manuales_stock_ajustar($pdo, $idProducto, $idDestino, 'plantel', $cantidad);
        $pdo->prepare(
            "UPDATE producto_envio
             SET estado = 'recibido', recibido_por = ?, recibido_en = NOW()
             WHERE id = ?"
        )->execute([$idUsuario > 0 ? $idUsuario : null, $idEnvio]);
        $pdo->commit();

        return ['ok' => true, 'message' => 'Recepción confirmada'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('manuales_stock_confirmar_envio: ' . $e->getMessage());

        return ['ok' => false, 'message' => 'No se pudo confirmar la recepción'];
    }
}

/** @return list<array<string,mixed>> */
function manuales_stock_envios(PDO $pdo, ?int $idPlantel = null): array
{
    manuales_stock_ensure_schema($pdo);
    $params = [];
    $where = '';
    if ($idPlantel !== null && $idPlantel > 0 && (!function_exists('rbac_tiene_acceso_total') || !rbac_tiene_acceso_total())) {
        $where = 'WHERE e.id_plantel_destino = ?';
        $params[] = $idPlantel;
    }
    $st = $pdo->prepare(
        "SELECT e.*, p.nombre AS producto_nombre, p.clave AS producto_clave,
                pl.nombre AS plantel_nombre,
                CONCAT(ue.nombre, ' ', ue.apellido) AS enviado_por_nombre,
                CONCAT(ur.nombre, ' ', ur.apellido) AS recibido_por_nombre
         FROM producto_envio e
         INNER JOIN productos p ON p.id_producto = e.id_producto
         INNER JOIN planteles pl ON pl.id_plantel = e.id_plantel_destino
         LEFT JOIN usuarios ue ON ue.id_usuario = e.enviado_por
         LEFT JOIN usuarios ur ON ur.id_usuario = e.recibido_por
         $where
         ORDER BY FIELD(e.estado, 'en_transito', 'pendiente', 'recibido'), e.enviado_en DESC, e.id DESC
         LIMIT 300"
    );
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
