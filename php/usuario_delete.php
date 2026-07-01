<?php
require_once __DIR__ . '/../config.php';
global $pdo;
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($_POST['id_usuario'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

// No permitir eliminarse a sí mismo
if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $id) {
    echo json_encode(['status' => 'error', 'message' => 'No puedes eliminar tu propio usuario'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ? LIMIT 1");
    $stmt->execute([$id]);
    echo json_encode(['status' => 'ok', 'seccion' => 'ver_usuarios'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

