<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !venta_producto_puede_acceder()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'alumnos') {
    $q = trim($_GET['q'] ?? '');
    $sql = "SELECT id_alumno, numero_control, matricula,
            CONCAT(nombres, ' ', apellido_paterno, ' ', IFNULL(apellido_materno,'')) AS nombre_completo
            FROM alumnos
            WHERE id_plantel = ? AND estado = 'activo'
              AND numero_control NOT LIKE 'PUB-%'";
    $params = [$idPlantel];
    if ($q !== '') {
        $sql .= ' AND (numero_control LIKE ? OR matricula LIKE ? OR nombres LIKE ? OR apellido_paterno LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    $sql .= ' ORDER BY apellido_paterno, nombres LIMIT 50';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    hay_json_response(['status' => 'ok', 'alumnos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'productos') {
    hay_json_response([
        'status' => 'ok',
        'productos' => catalog_listar_productos_venta($pdo, $idPlantel),
    ]);
    exit;
}

if ($action === 'vender' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($items)) {
        $items = [];
    }
    $res = venta_producto_registrar_carrito($pdo, [
        'id_alumno' => (int) ($_POST['id_alumno'] ?? 0),
        'cliente_nombre' => trim($_POST['cliente_nombre'] ?? ''),
        'forma_pago' => trim($_POST['forma_pago'] ?? 'Efectivo'),
        'items' => $items,
    ]);
    if (!$res['ok']) {
        hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'Error']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'message' => $res['message'] ?? 'Venta registrada',
        'folio' => $res['folio'] ?? '',
        'id_pago' => $res['id_pago'] ?? 0,
        'total_fmt' => $res['total_fmt'] ?? '',
        'ticket_url' => hay_asset_url('views/ticket_pago.php?id_pago=' . (int) ($res['id_pago'] ?? 0) . '&print=1'),
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
