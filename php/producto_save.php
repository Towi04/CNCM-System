<?php
require __DIR__ . '/../config.php';

if (!catalog_puede_administrar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hay_json_response(['status' => 'error', 'message' => 'Método inválido']);
    exit;
}

$id = (int) ($_POST['id_producto'] ?? 0);
$clave = catalog_normalizar_clave((string) ($_POST['clave'] ?? ''), 40);
$nombre = trim((string) ($_POST['nombre'] ?? ''));
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
$precio = catalog_money($_POST['precio'] ?? 0);
$claveSat = trim((string) ($_POST['clave_sat'] ?? '01010101'));
$unidadSat = trim((string) ($_POST['unidad_sat'] ?? 'H87'));
$gratisProf = isset($_POST['gratis_profesor']) ? 1 : 0;
$visible = isset($_POST['visible']) ? 1 : 0;
$descontinuado = isset($_POST['descontinuado']) ? 1 : 0;
$activo = isset($_POST['activo']) ? 1 : 0;
$stockMinimo = max(0, (int) ($_POST['stock_minimo'] ?? 5));
$orden = max(0, (int) ($_POST['orden'] ?? 0));
$controlaInventario = isset($_POST['controla_inventario']) ? 1 : 0;

if ($clave === '' || $nombre === '') {
    hay_json_response(['status' => 'error', 'message' => 'Clave y nombre son obligatorios']);
    exit;
}

if ($descontinuado && $visible) {
    $visible = 0;
}

try {
    if ($id > 0) {
        $dup = $pdo->prepare('SELECT id_producto FROM productos WHERE clave = ? AND id_producto <> ? LIMIT 1');
        $dup->execute([$clave, $id]);
        if ($dup->fetchColumn()) {
            hay_json_response(['status' => 'error', 'message' => 'Esa clave ya existe']);
            exit;
        }
        $stmt = $pdo->prepare(
            'UPDATE productos SET clave=?, nombre=?, descripcion=?, precio=?, clave_sat=?, unidad_sat=?,
             gratis_profesor=?, visible=?, descontinuado=?, activo=?, stock_minimo=?, controla_inventario=?, orden=? WHERE id_producto=?'
        );
        $stmt->execute([
            $clave, $nombre, $descripcion, $precio, $claveSat, $unidadSat,
            $gratisProf, $visible, $descontinuado, $activo, $stockMinimo, $controlaInventario, $orden, $id,
        ]);
    } else {
        $dup = $pdo->prepare('SELECT id_producto FROM productos WHERE clave = ? LIMIT 1');
        $dup->execute([$clave]);
        if ($dup->fetchColumn()) {
            hay_json_response(['status' => 'error', 'message' => 'Esa clave ya existe']);
            exit;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO productos (
                clave, nombre, descripcion, precio, clave_sat, unidad_sat,
                gratis_profesor, visible, descontinuado, activo, stock_minimo, controla_inventario, orden
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $clave, $nombre, $descripcion, $precio, $claveSat, $unidadSat,
            $gratisProf, $visible, $descontinuado, $activo, $stockMinimo, $controlaInventario, $orden,
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    foreach (plantel_list($pdo, false) as $pl) {
        catalog_ensure_inventario_row($pdo, $id, (int) $pl['id_plantel']);
    }

    hay_json_response([
        'status' => 'ok',
        'message' => 'Producto guardado',
        'seccion' => 'admin_productos',
        'id_producto' => $id,
    ]);
} catch (PDOException $e) {
    hay_json_response(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
}
