<?php

require_once __DIR__ . '/session_helper.php';
hay_session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/maintenance_helper.php';
require_once __DIR__ . '/supervisor_deploy_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!supervisor_deploy_puede()) {
    echo json_encode(['status' => 'error', 'message' => 'Solo supervisión'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = strtolower(trim((string) ($_POST['action'] ?? $_GET['action'] ?? '')));
$csrf = (string) ($_POST['csrf'] ?? $_GET['csrf'] ?? '');

if ($action === 'estado') {
    $ramas = supervisor_deploy_configurado()
        ? supervisor_deploy_estado_ramas()
        : ['ok' => false, 'message' => 'Sin HAY_GITHUB_TOKEN en config.local.php'];
    echo json_encode([
        'status' => 'ok',
        'csrf' => supervisor_deploy_csrf_token(),
        'mantenimiento' => hay_maintenance_activo(),
        'meta' => hay_maintenance_meta(),
        'github_ok' => supervisor_deploy_configurado(),
        'repo' => supervisor_deploy_repo(),
        'ramas' => $ramas,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST requerido'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!supervisor_deploy_csrf_ok($csrf)) {
    echo json_encode(['status' => 'error', 'message' => 'CSRF inválido; recargue la sección'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'mantenimiento_on') {
    $motivo = trim((string) ($_POST['motivo'] ?? 'Actualización supervisada'));
    $ok = hay_maintenance_activar($motivo);
    echo json_encode([
        'status' => $ok ? 'ok' : 'error',
        'message' => $ok ? 'Modo mantenimiento activado' : 'No se pudo crear maintenance.flag',
        'mantenimiento' => hay_maintenance_activo(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'mantenimiento_off') {
    $ok = hay_maintenance_desactivar();
    echo json_encode([
        'status' => $ok ? 'ok' : 'error',
        'message' => $ok ? 'Modo mantenimiento desactivado' : 'No se pudo quitar el flag',
        'mantenimiento' => hay_maintenance_activo(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'promover') {
    $confirm = (string) ($_POST['confirm'] ?? '');
    $res = supervisor_deploy_promover_a_main($confirm);
    echo json_encode([
        'status' => !empty($res['ok']) ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
        'html_url' => $res['html_url'] ?? null,
        'sha' => $res['sha'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción desconocida'], JSON_UNESCAPED_UNICODE);
