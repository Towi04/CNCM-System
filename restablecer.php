<?php
require __DIR__ . '/config.php';
require __DIR__ . '/php/auth_helpers.php';

auth_ensure_email_column($pdo);
auth_ensure_password_reset_table($pdo);

$token = trim($_GET['token'] ?? '');
$valid = false;
$userId = null;

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        'SELECT id_usuario FROM password_resets
         WHERE token_hash = ? AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $valid = true;
        $userId = (int) $row['id_usuario'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(app_page_title('Nueva contraseña'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="login-page">

<div class="login-shell">
    <aside class="login-brand">
        <img src="src/logobco.png" alt="Grupo Educativo CNCM">
    </aside>

    <section class="login-panel">
        <div class="login-form-wrap">
            <h1>Nueva contraseña</h1>

            <?php if (!$valid): ?>
                <p class="login-alert login-alert--error" role="alert">
                    El enlace no es válido o ya expiró. Solicita uno nuevo desde la pantalla de recuperación.
                </p>
                <a class="login-back" href="olvido_password.php">Solicitar nuevo enlace</a>
            <?php else: ?>
                <?php if (isset($_GET['error'])): ?>
                    <p class="login-alert login-alert--error" role="alert">
                        No se pudo actualizar la contraseña. Usa al menos 6 caracteres y confirma que coincidan.
                    </p>
                <?php endif; ?>

                <form action="php/restablecer_password.php" method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="input-group">
                        <label for="password_nueva">Nueva contraseña</label>
                        <div class="password-input-wrap">
                            <input
                                type="password"
                                id="password_nueva"
                                name="password_nueva"
                                required
                                minlength="6"
                                autocomplete="new-password"
                            >
                            <button type="button" class="btn-toggle-password" data-target="password_nueva" aria-label="Mostrar contraseña">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="password_confirm">Confirmar contraseña</label>
                        <div class="password-input-wrap">
                            <input
                                type="password"
                                id="password_confirm"
                                name="password_confirm"
                                required
                                minlength="6"
                                autocomplete="new-password"
                            >
                            <button type="button" class="btn-toggle-password" data-target="password_confirm" aria-label="Mostrar contraseña">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">Guardar contraseña</button>
                </form>
            <?php endif; ?>

            <a class="login-back" href="index.php">&larr; Volver al inicio de sesión</a>
        </div>
    </section>
</div>

<script src="js/login.js"></script>
</body>
</html>
