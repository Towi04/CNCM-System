<?php require_once __DIR__ . '/php/branding.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(app_page_title('Recuperar contraseña'), ENT_QUOTES, 'UTF-8'); ?></title>
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
            <h1>Recuperar acceso</h1>

            <p class="login-hint">
                Ingresa tu correo institucional registrado. Te enviaremos un enlace para restablecer tu contraseña.
            </p>

            <?php if (isset($_GET['ok'])): ?>
                <p class="login-alert login-alert--success" role="status">
                    Si el correo está registrado en el sistema, recibirás un mensaje con el enlace de recuperación en unos minutos (revisa también spam).
                </p>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'config'): ?>
                <p class="login-alert login-alert--error" role="alert">
                    Falta configurar <strong>config.mail.php</strong> con SMTP de Google Workspace
                    (<code>smtp.gmail.com</code>, puerto 587, TLS) y la <strong>contraseña de aplicación</strong>
                    de la cuenta que envía (no la contraseña normal de Gmail).
                </p>
            <?php elseif (isset($_GET['error']) && $_GET['error'] === 'buzon'): ?>
                <p class="login-alert login-alert--error" role="alert">
                    El servidor SMTP rechazó el destinatario. Si usan <strong>Google Workspace</strong>, configure
                    <code>config.mail.php</code> con <code>smtp.gmail.com</code> y contraseña de aplicación.
                    Si usan cPanel, el buzón debe existir en <em>Cuentas de correo</em> del hosting.
                </p>
            <?php elseif (isset($_GET['error']) && $_GET['error'] === 'envio'): ?>
                <p class="login-alert login-alert--error" role="alert">
                    No se pudo enviar el correo. Verifica la configuración SMTP o contacta a soporte técnico.
                    <?php
                    if (defined('MAIL_DEBUG') && MAIL_DEBUG && is_file(__DIR__ . '/logs/mail.log')) {
                        $lines = @file(__DIR__ . '/logs/mail.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        if ($lines) {
                            $last = htmlspecialchars(end($lines), ENT_QUOTES, 'UTF-8');
                            echo '<br><small style="opacity:.9;">Detalle técnico: ' . $last . '</small>';
                        }
                    }
                    ?>
                </p>
            <?php elseif (isset($_GET['error'])): ?>
                <p class="login-alert login-alert--error" role="alert">
                    No fue posible procesar la solicitud. Usa un correo institucional (@cncm.edu.mx) registrado en el sistema.
                </p>
            <?php endif; ?>

            <form action="php/solicitar_recuperacion.php" method="POST">
                <div class="input-group">
                    <label for="email">Correo electrónico</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        autocomplete="email"
                        placeholder="usuario@cncm.edu.mx"
                    >
                </div>

                <button type="submit" class="btn-login">Enviar enlace</button>
            </form>

            <a class="login-back" href="index.php">&larr; Volver al inicio de sesión</a>
        </div>
    </section>
</div>

</body>
</html>
