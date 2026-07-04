<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';
require_once __DIR__ . '/legacy_migracion_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id']) || !legacy_migracion_puede()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

$leg = legacy_import_pdo_legacy();
$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

if ($action === 'estado') {
    hay_json_response(['status' => 'ok'] + legacy_migracion_estado($pdo, $leg));
    exit;
}

if ($action === 'fases') {
    hay_json_response(['status' => 'ok', 'fases' => legacy_migracion_fases()]);
    exit;
}

if ($leg === null) {
    hay_json_response([
        'status' => 'error',
        'message' => 'Sin conexión al legado. Defina LEGACY_DB_HOST, LEGACY_DB_NAME, LEGACY_DB_USER y LEGACY_DB_PASS en config.local.php',
    ]);
    exit;
}

if ($action === 'preview') {
    $fase = trim((string) ($_GET['fase'] ?? ''));
    if ($fase === '') {
        hay_json_response(['status' => 'error', 'message' => 'Indique fase']);
        exit;
    }
    hay_json_response(legacy_migracion_preview($pdo, $leg, $fase));
    exit;
}

if ($action === 'aplicar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fase = trim((string) ($_POST['fase'] ?? ''));
    if ($fase === '') {
        hay_json_response(['status' => 'error', 'message' => 'Indique fase']);
        exit;
    }
    hay_json_response(legacy_migracion_aplicar($pdo, $leg, $fase));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
