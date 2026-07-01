<?php
require __DIR__ . '/../config.php';

if (!combo_puede_administrar()) {
    hay_json_response(['status' => 'error', 'message' => 'Solo el supervisor puede gestionar colegiaturas con descuento']);
    exit;
}

combo_ensure_schema($pdo);

$idAutoriza = (int) ($_SESSION['user_id'] ?? 0);
if ($idAutoriza <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión inválida']);
    exit;
}

$tarifas = json_decode($_POST['tarifas'] ?? '[]', true);
if (!is_array($tarifas)) {
    $tarifas = [];
}

$res = combo_guardar_regla($pdo, [
    'id_regla' => (int) ($_POST['id_regla'] ?? 0),
    'nombre' => $_POST['nombre'] ?? '',
    'claves' => $_POST['claves'] ?? [],
    'motivo' => $_POST['motivo'] ?? '',
    'tipo' => $_POST['tipo'] ?? 'combinacion',
    'categoria_promo' => $_POST['categoria_promo'] ?? '',
    'tarifas' => $tarifas,
], $idAutoriza);

hay_json_response([
    'status' => $res['ok'] ? 'ok' : 'error',
    'message' => $res['message'],
    'seccion' => 'admin_colegiatura_combos',
]);
