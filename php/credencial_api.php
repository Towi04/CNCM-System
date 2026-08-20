<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_id']) || !credencial_puede_diseñar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$accion = trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? ''));
$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) $_SESSION['user_id'];

if ($accion === 'listar') {
    hay_json_response([
        'status' => 'ok',
        'plantillas' => credencial_plantillas_listar($pdo, $idPlantel),
    ]);
    exit;
}

if ($accion === 'obtener') {
    $plantilla = credencial_plantilla_obtener(
        $pdo,
        (int) ($_GET['id_plantilla'] ?? 0),
        $idPlantel
    );
    hay_json_response($plantilla
        ? ['status' => 'ok', 'plantilla' => $plantilla]
        : ['status' => 'error', 'message' => 'Plantilla no encontrada']);
    exit;
}

if ($accion === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    $data['id_plantel'] = $idPlantel;
    $uploads = [];
    foreach (['frente', 'reverso'] as $lado) {
        $key = 'fondo_' . $lado;
        if (!empty($_FILES[$key]['tmp_name'])) {
            $up = credencial_subir_fondo($_FILES[$key], $lado);
            if (!$up['ok']) {
                hay_json_response(['status' => 'error', 'message' => $up['message'] ?? 'Fondo no válido']);
                exit;
            }
            $uploads[$key . '_path'] = $up['path'];
        }
    }
    $res = credencial_plantilla_guardar($pdo, $data, $idUsuario);
    if ($res['ok'] && $uploads !== []) {
        $sets = [];
        $params = [];
        foreach ($uploads as $col => $path) {
            $sets[] = $col . ' = ?';
            $params[] = $path;
        }
        $params[] = (int) $res['id_plantilla'];
        $params[] = $idPlantel;
        $pdo->prepare(
            'UPDATE credencial_plantilla SET ' . implode(', ', $sets)
            . ' WHERE id_plantilla = ? AND id_plantel = ?'
        )->execute($params);
    }
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
