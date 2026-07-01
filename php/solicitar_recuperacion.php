<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/auth_helpers.php';

auth_ensure_email_column($pdo);
auth_ensure_password_reset_table($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../olvido_password.php');
    exit;
}

$emailRaw = trim($_POST['email'] ?? '');
$redirectOk = '../olvido_password.php?ok=1';

if ($emailRaw === '' || !auth_is_institutional_email($emailRaw)) {
    header('Location: ../olvido_password.php?error=1');
    exit;
}

$email = strtolower($emailRaw);

$stmt = $pdo->prepare(
    'SELECT id_usuario, nombre, email FROM usuarios WHERE LOWER(email) = ? LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && !empty($user['email'])) {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare('DELETE FROM password_resets WHERE id_usuario = ?')->execute([(int) $user['id_usuario']]);

    $ins = $pdo->prepare(
        'INSERT INTO password_resets (id_usuario, token_hash, expires_at) VALUES (?, ?, ?)'
    );
    $ins->execute([(int) $user['id_usuario'], $hash, $expires]);

    $base = rtrim(auth_app_base_url(), '/');
    $link = $base . '/restablecer.php?token=' . urlencode($token);
    $nombre = htmlspecialchars($user['nombre'], ENT_QUOTES, 'UTF-8');

    $subject = 'Recuperación de contraseña - Sistema HAY';
    $body = '<p>Hola ' . $nombre . ',</p>'
        . '<p>Recibimos una solicitud para restablecer tu contraseña del Sistema HAY.</p>'
        . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Haz clic aquí para crear una nueva contraseña</a></p>'
        . '<p>Este enlace expira en 1 hora. Si no solicitaste el cambio, ignora este mensaje.</p>'
        . '<p style="font-size:12px;color:#666;">Enlace directo: ' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</p>';

    require_once __DIR__ . '/mail_helper.php';

    if (!mail_is_configured()) {
        mail_log('Recuperación: falta config.mail.php con SMTP');
        header('Location: ../olvido_password.php?error=config');
        exit;
    }

    $enviado = auth_send_mail($user['email'], $subject, $body);
    if (!$enviado) {
        $err = mail_last_error_code();
        $q = ($err === 'rcpt_mailbox') ? 'buzon' : 'envio';
        header('Location: ../olvido_password.php?error=' . $q);
        exit;
    }

    header('Location: ' . $redirectOk);
    exit;
}

header('Location: ' . $redirectOk);
exit;
