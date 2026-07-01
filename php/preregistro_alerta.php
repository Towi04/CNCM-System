<?php
require __DIR__ . '/../config.php';

if (!preregistro_puede_acceder()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');
$idAlerta = (int) ($_POST['id_alerta'] ?? $_GET['id_alerta'] ?? 0);
$idPlantel = plantel_id_activo();

if ($action === 'marcar_leida' && $idAlerta > 0) {
    $pdo->prepare(
        'UPDATE preregistro_alertas SET leida = 1 WHERE id_alerta = ? AND id_plantel = ?'
    )->execute([$idAlerta, $idPlantel]);
    hay_json_response(['status' => 'ok']);
    exit;
}

if ($action === 'resolver' && $idAlerta > 0) {
    $pdo->prepare(
        'UPDATE preregistro_alertas SET resuelta = 1, leida = 1 WHERE id_alerta = ? AND id_plantel = ?'
    )->execute([$idAlerta, $idPlantel]);
    hay_json_response(['status' => 'ok', 'seccion' => 'pre_registro_alumnos']);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
