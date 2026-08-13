<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) $_SESSION['user_id'];

if (!manuales_stock_puede_stock() && !manuales_stock_puede_envios()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

if ($action === 'catalogos') {
    hay_json_response([
        'status' => 'ok',
        'productos' => manuales_stock_productos($pdo),
        'planteles' => manuales_stock_planteles($pdo),
        'puede_stock' => manuales_stock_puede_stock(),
    ]);
    exit;
}

if ($action === 'stock') {
    if (!manuales_stock_puede_stock()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    hay_json_response(['status' => 'ok', 'items' => manuales_stock_listar($pdo)]);
    exit;
}

if ($action === 'guardar_stock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!manuales_stock_puede_stock()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $idProducto = (int) ($_POST['id_producto'] ?? 0);
    $cantidad = (int) ($_POST['cantidad'] ?? 0);
    $idPlantelStock = (int) ($_POST['id_plantel'] ?? 0);
    $ubicacion = trim((string) ($_POST['ubicacion'] ?? 'bodega'));
    if ($idProducto <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Producto requerido'], 400);
        exit;
    }
    if ($ubicacion === 'bodega') {
        $idPlantelStock = 0;
    }
    manuales_stock_set($pdo, $idProducto, $idPlantelStock > 0 ? $idPlantelStock : null, $ubicacion, $cantidad);
    hay_json_response(['status' => 'ok', 'message' => 'Stock actualizado']);
    exit;
}

if ($action === 'envios') {
    hay_json_response(['status' => 'ok', 'items' => manuales_stock_envios($pdo, $idPlantel)]);
    exit;
}

if ($action === 'crear_envio' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = manuales_stock_crear_envio(
        $pdo,
        (int) ($_POST['id_producto'] ?? 0),
        (int) ($_POST['id_plantel_destino'] ?? 0),
        (int) ($_POST['cantidad'] ?? 0),
        $idUsuario,
        (string) ($_POST['notas'] ?? '')
    );
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);
    exit;
}

if ($action === 'confirmar_envio' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = manuales_stock_confirmar_envio($pdo, (int) ($_POST['id_envio'] ?? 0), $idPlantel, $idUsuario);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
