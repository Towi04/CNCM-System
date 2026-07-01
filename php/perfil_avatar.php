<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int) $_SESSION['user_id'];
user_avatar_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no válido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim($_POST['action'] ?? 'upload');

if ($action === 'remove') {
    $result = user_avatar_remove($pdo, $userId);
    echo json_encode([
        'status' => $result['ok'] ? 'ok' : 'error',
        'message' => $result['message'],
        'avatar_url' => '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare('SELECT avatar FROM usuarios WHERE id_usuario = ? LIMIT 1');
$stmt->execute([$userId]);
$oldPath = trim((string) ($stmt->fetchColumn() ?: ''));

$result = user_avatar_save_upload($userId, $_FILES['avatar'] ?? []);

if (!$result['ok']) {
    echo json_encode([
        'status' => 'error',
        'message' => $result['message'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$newPath = (string) $result['path'];
$absPath = (string) ($result['abs'] ?? '');
if ($absPath === '' || !is_file($absPath)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se encontró el archivo en el servidor después de subirlo. Permisos de uploads/avatars.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$upd = $pdo->prepare('UPDATE usuarios SET avatar = ? WHERE id_usuario = ?');
$upd->execute([$newPath, $userId]);

if ($oldPath !== '' && $oldPath !== $newPath) {
    user_avatar_delete_file($oldPath);
}

user_avatar_refresh_session($pdo, $userId);

$src = user_avatar_public_url($newPath);
if ($src === null) {
    $pdo->prepare('UPDATE usuarios SET avatar = ? WHERE id_usuario = ?')->execute(['', $userId]);
    user_avatar_refresh_session($pdo, $userId);
    echo json_encode([
        'status' => 'error',
        'message' => 'La foto se guardó en disco pero no es accesible por la web. Revise la ruta uploads/avatars.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'status' => 'ok',
    'message' => $result['message'],
    'avatar_url' => $src,
], JSON_UNESCAPED_UNICODE);
