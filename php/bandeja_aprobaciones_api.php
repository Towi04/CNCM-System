<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
require_once __DIR__ . '/bandeja_aprobaciones_helper.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

if (!bandeja_aprobaciones_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) $_SESSION['user_id'];
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'listar') {
    $filtro = trim($_GET['filtro'] ?? $_POST['filtro'] ?? '');
    $filtro = in_array($filtro, ['permiso_profesor', 'inscripcion', 'grupo_apertura'], true) ? $filtro : null;
    hay_json_response([
        'status' => 'ok',
        'resumen' => bandeja_aprobaciones_resumen($pdo, $idPlantel),
        'items' => bandeja_aprobaciones_listar($pdo, $idPlantel, $filtro),
    ]);
    exit;
}

if ($action === 'resolver_permiso' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!profesor_portal_puede_revisar_permisos()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso para permisos'], 403);
        exit;
    }
    $res = profesor_portal_resolver_permiso(
        $pdo,
        (int) ($_POST['id_solicitud'] ?? 0),
        (string) ($_POST['estado'] ?? ''),
        (string) ($_POST['comentario'] ?? ''),
        $idUsuario
    );
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'seccion' => 'bandeja_aprobaciones',
    ]);
    exit;
}

if ($action === 'resolver_inscripcion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!inscripcion_protocolo_puede_autorizar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso para inscripciones'], 403);
        exit;
    }
    $res = inscripcion_protocolo_resolver(
        $pdo,
        (int) ($_POST['id_auth'] ?? 0),
        (string) ($_POST['estado'] ?? ''),
        trim((string) ($_POST['motivo'] ?? '')) ?: null
    );
    hay_json_response(array_merge([
        'status' => $res['ok'] ? 'ok' : 'error',
        'seccion' => 'bandeja_aprobaciones',
    ], $res));
    exit;
}

if ($action === 'autorizar_grupo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!grupo_apertura_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso para apertura'], 403);
        exit;
    }
    $res = grupo_apertura_autorizar($pdo, (int) ($_POST['id_grupo'] ?? 0), $idUsuario, $idPlantel);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'seccion' => 'bandeja_aprobaciones',
    ]);
    exit;
}

if ($action === 'posponer_grupo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!grupo_apertura_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso para apertura'], 403);
        exit;
    }
    $res = grupo_apertura_posponer(
        $pdo,
        (int) ($_POST['id_grupo'] ?? 0),
        trim((string) ($_POST['nueva_fecha'] ?? '')),
        trim((string) ($_POST['motivo'] ?? '')),
        $idUsuario,
        $idPlantel
    );
    hay_json_response(array_merge([
        'status' => $res['ok'] ? 'ok' : 'error',
        'seccion' => 'bandeja_aprobaciones',
    ], $res));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
