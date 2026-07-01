<?php
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$idU = (int) ($_SESSION['user_id'] ?? 0);
if ($idU <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'pasos'));

if ($action === 'pasos') {
    $key = trim((string) ($_GET['tour_key'] ?? 'inicio'));
    hay_json_response([
        'status' => 'ok',
        'tour_key' => $key,
        'pasos' => tour_pasos_para($key),
        'completado' => tour_completado($pdo, $idU, $key),
    ]);
    exit;
}

if ($action === 'completar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = trim((string) ($_POST['tour_key'] ?? ''));
    if ($key === '') {
        hay_json_response(['status' => 'error', 'message' => 'tour_key requerido']);
        exit;
    }
    tour_marcar($pdo, $idU, $key, true);
    hay_json_response(['status' => 'ok', 'message' => 'Tour marcado como completado']);
    exit;
}

if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    tour_reset_usuario($pdo, $idU);
    hay_json_response(['status' => 'ok', 'message' => 'Tours reiniciados. Se mostrarán de nuevo al entrar a cada vista.']);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
