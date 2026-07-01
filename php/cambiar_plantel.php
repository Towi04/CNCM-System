<?php
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = trim($_POST['plantel_id'] ?? $_GET['plantel_id'] ?? '');
if ($raw === '') {
    echo json_encode(['status' => 'error', 'message' => 'Plantel no válido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$plantel = plantel_find($pdo, is_numeric($raw) ? (int) $raw : $raw);
if (!$plantel || (int) $plantel['activo'] !== 1) {
    echo json_encode(['status' => 'error', 'message' => 'Plantel no válido'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!plantel_puede_cambiar_a($pdo, (int) $plantel['id_plantel'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No puede cambiar a ese plantel. Solo ve la información de su sede asignada.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

plantel_set_sesion($plantel);

echo json_encode([
    'status' => 'ok',
    'plantel_id' => (int) $plantel['id_plantel'],
    'plantel_slug' => $plantel['slug'],
    'plantel_nombre' => $plantel['nombre'],
    'plantel_fondo_url' => plantel_fondo_imagen($plantel['slug']),
    'plantel_fondo_clases' => plantel_fondo_clases($plantel['slug']),
], JSON_UNESCAPED_UNICODE);
