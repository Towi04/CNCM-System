<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/auth_helpers.php';

auth_ensure_email_column($pdo);
auth_ensure_password_reset_table($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$token = trim($_POST['token'] ?? '');
$nueva = (string) ($_POST['password_nueva'] ?? '');
$confirm = (string) ($_POST['password_confirm'] ?? '');

$fail = function () use ($token) {
    $q = $token !== '' ? '?token=' . urlencode($token) . '&error=1' : '';
    header('Location: ../restablecer.php' . $q);
    exit;
};

if (
    $token === ''
    || !preg_match('/^[a-f0-9]{64}$/i', $token)
    || strlen($nueva) < 6
    || $nueva !== $confirm
) {
    $fail();
}

$hash = hash('sha256', $token);
$stmt = $pdo->prepare(
    'SELECT id, id_usuario FROM password_resets
     WHERE token_hash = ? AND expires_at > NOW()
     ORDER BY id DESC LIMIT 1'
);
$stmt->execute([$hash]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    header('Location: ../restablecer.php');
    exit;
}

$userId = (int) $row['id_usuario'];
$newHash = password_hash($nueva, PASSWORD_BCRYPT);

$upd = $pdo->prepare('UPDATE usuarios SET password = ? WHERE id_usuario = ?');
$upd->execute([$newHash, $userId]);

$pdo->prepare('DELETE FROM password_resets WHERE id_usuario = ?')->execute([$userId]);

if (function_exists('login_security_limpiar_bloqueo')) {
    login_security_limpiar_bloqueo($pdo, $userId);
}

header('Location: ../index.php?reset=1');
exit;
