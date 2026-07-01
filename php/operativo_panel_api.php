<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id']) || !operativo_busqueda_puede()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$accion = trim($_GET['accion'] ?? $_POST['accion'] ?? '');
$q = trim($_GET['q'] ?? $_POST['q'] ?? '');

if ($accion === 'sugerencias') {
    hay_json_response([
        'status' => 'ok',
        'sugerencias' => operativo_busqueda_sugerencias($pdo, $q, $idPlantel),
    ]);
    exit;
}

if ($accion === 'buscar_alumno') {
    $res = operativo_busqueda_rapida_alumno($pdo, $q, $idPlantel);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
