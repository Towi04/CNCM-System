<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión no válida'], 401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hay_json_response(['status' => 'error', 'message' => 'Método no permitido'], 405);
    exit;
}

$idUsuario = (int) $_SESSION['user_id'];
$idPlantel = plantel_scope_id($pdo);
$accion = trim((string) ($_POST['accion'] ?? ''));

if ($accion === 'marcar_leida' || $accion === 'archivar') {
    $clave = trim((string) ($_POST['clave'] ?? ''));
    $idNotif = (int) ($_POST['id_notificacion'] ?? 0);
    if ($clave === '') {
        hay_json_response(['status' => 'error', 'message' => 'Aviso inválido']);
        exit;
    }
    $estado = $accion === 'archivar' ? 'archivada' : 'leida';
    hay_json_response(notificaciones_panel_ocultar($pdo, $idUsuario, $clave, $estado, $idNotif ?: null, $idPlantel));
    exit;
}

if ($accion === 'marcar_todas' || $accion === 'archivar_todas') {
    $estado = $accion === 'archivar_todas' ? 'archivada' : 'leida';
    hay_json_response(notificaciones_panel_ocultar_todos($pdo, $idUsuario, $idPlantel, $estado));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
