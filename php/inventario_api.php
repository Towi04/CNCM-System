<?php
require __DIR__ . '/../config.php';

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

if ($action === 'listar_pendientes') {
    if (!catalog_puede_confirmar_inventario()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $idPlantel = (int) ($_GET['id_plantel'] ?? plantel_id_activo());
    hay_json_response([
        'status' => 'ok',
        'items' => catalog_movimientos_pendientes($pdo, $idPlantel),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hay_json_response(['status' => 'error', 'message' => 'Método inválido']);
    exit;
}

$idProducto = (int) ($_POST['id_producto'] ?? 0);
$idPlantel = (int) ($_POST['id_plantel'] ?? plantel_id_activo());
$cantidad = max(1, (int) ($_POST['cantidad'] ?? 0));
$notas = trim((string) ($_POST['notas'] ?? ''));
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($idProducto <= 0 || $idPlantel <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'Producto y plantel son obligatorios']);
    exit;
}

try {
    if ($action === 'registrar_entrada') {
        if (!catalog_puede_administrar()) {
            hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO producto_movimientos (id_producto, id_plantel, tipo, cantidad, notas, estado, id_usuario_registro)
             VALUES (?, ?, \'entrada\', ?, ?, \'pendiente\', ?)'
        );
        $stmt->execute([$idProducto, $idPlantel, $cantidad, $notas, $userId]);
        hay_json_response([
            'status' => 'ok',
            'message' => 'Entrada registrada; el director del plantel debe confirmarla',
            'seccion' => 'admin_productos',
        ]);
        exit;
    }

    if ($action === 'confirmar_entrada') {
        if (!catalog_puede_confirmar_inventario()) {
            hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $idMov = (int) ($_POST['id_movimiento'] ?? 0);
        $cantidadConfirm = max(0, (int) ($_POST['cantidad_confirmada'] ?? 0));
        if ($idMov <= 0) {
            hay_json_response(['status' => 'error', 'message' => 'Movimiento inválido']);
            exit;
        }
        if ($cantidadConfirm > 0) {
            $pdo->prepare('UPDATE producto_movimientos SET cantidad = ? WHERE id_movimiento = ? AND estado = \'pendiente\'')
                ->execute([$cantidadConfirm, $idMov]);
        }
        $res = catalog_aplicar_movimiento($pdo, $idMov);
        hay_json_response([
            'status' => $res['ok'] ? 'ok' : 'error',
            'message' => $res['message'],
            'seccion' => 'admin_productos',
        ]);
        exit;
    }

    if ($action === 'merma' || $action === 'ajuste') {
        if (!catalog_puede_administrar()) {
            hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $tipo = $action === 'merma' ? 'merma' : 'ajuste';
        $stmt = $pdo->prepare(
            'INSERT INTO producto_movimientos (id_producto, id_plantel, tipo, cantidad, notas, estado, id_usuario_registro)
             VALUES (?, ?, ?, ?, ?, \'pendiente\', ?)'
        );
        $stmt->execute([$idProducto, $idPlantel, $tipo, $cantidad, $notas, $userId]);
        $idMov = (int) $pdo->lastInsertId();
        $res = catalog_aplicar_movimiento($pdo, $idMov);
        if (!$res['ok']) {
            $pdo->prepare('UPDATE producto_movimientos SET estado = \'cancelado\' WHERE id_movimiento = ?')->execute([$idMov]);
        }
        hay_json_response([
            'status' => $res['ok'] ? 'ok' : 'error',
            'message' => $res['message'],
            'seccion' => 'admin_productos',
        ]);
        exit;
    }

    if ($action === 'stock_minimo') {
        if (!catalog_puede_administrar()) {
            hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
            exit;
        }
        $min = max(0, (int) ($_POST['stock_minimo'] ?? 5));
        catalog_ensure_inventario_row($pdo, $idProducto, $idPlantel);
        $pdo->prepare(
            'UPDATE producto_inventario SET stock_minimo = ? WHERE id_producto = ? AND id_plantel = ?'
        )->execute([$min, $idProducto, $idPlantel]);
        hay_json_response(['status' => 'ok', 'message' => 'Stock mínimo actualizado', 'seccion' => 'admin_productos']);
        exit;
    }

    hay_json_response(['status' => 'error', 'message' => 'Acción no reconocida']);
} catch (PDOException $e) {
    hay_json_response(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
}
