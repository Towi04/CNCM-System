<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}
if (!audifonos_puede_gestionar()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) $_SESSION['user_id'];
$accion = trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'resumen'));

if ($accion === 'resumen') {
    hay_json_response(['status' => 'ok'] + audifonos_resumen($pdo, $idPlantel));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hay_json_response(['status' => 'error', 'message' => 'Método no válido'], 405);
    exit;
}

if ($accion === 'stock') {
    $res = audifonos_actualizar_stock($pdo, $idPlantel, (int) ($_POST['cantidad_total'] ?? -1));
} elseif ($accion === 'prestar') {
    $res = audifonos_prestar(
        $pdo,
        $idPlantel,
        (int) ($_POST['id_profesor'] ?? 0),
        (int) ($_POST['cantidad'] ?? 0),
        $idUsuario,
        (string) ($_POST['notas'] ?? '')
    );
} elseif ($accion === 'devolver') {
    $res = audifonos_devolver(
        $pdo,
        $idPlantel,
        (int) ($_POST['id_prestamo'] ?? 0),
        (int) ($_POST['cantidad'] ?? 0),
        $idUsuario,
        !empty($_POST['con_falla']),
        (string) ($_POST['falla_reportada'] ?? ''),
        (string) ($_POST['notas'] ?? '')
    );
} else {
    hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
    exit;
}

hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);
