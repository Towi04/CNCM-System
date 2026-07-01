<?php
require __DIR__ . '/../config.php';

if (!catalog_puede_administrar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id = (int) ($_POST['id_producto'] ?? 0);
if ($id <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

try {
    $pdo->prepare('UPDATE productos SET activo = 0, visible = 0, descontinuado = 1 WHERE id_producto = ?')->execute([$id]);
    hay_json_response([
        'status' => 'ok',
        'message' => 'Producto descontinuado y oculto',
        'seccion' => 'admin_productos',
    ]);
} catch (PDOException $e) {
    hay_json_response(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
}
