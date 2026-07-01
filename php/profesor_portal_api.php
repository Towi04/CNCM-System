<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'solicitar_permiso') {
    if (!profesor_portal_es_profesor()) {
        hay_json_response(['status' => 'error', 'message' => 'Solo profesores'], 403);
        exit;
    }
    $res = profesor_portal_crear_permiso(
        $pdo,
        (int) $_SESSION['user_id'],
        (string) ($_POST['fecha_inicio'] ?? ''),
        (string) ($_POST['fecha_fin'] ?? ''),
        (string) ($_POST['motivo'] ?? '')
    );
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'seccion' => 'profesor_portal',
    ]);
    exit;
}

if ($action === 'resolver_permiso') {
    if (!profesor_portal_puede_revisar_permisos()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $res = profesor_portal_resolver_permiso(
        $pdo,
        (int) ($_POST['id_solicitud'] ?? 0),
        (string) ($_POST['estado'] ?? ''),
        (string) ($_POST['comentario'] ?? ''),
        (int) $_SESSION['user_id']
    );
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'seccion' => 'profesor_permisos_admin',
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
