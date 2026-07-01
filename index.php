<?php
require_once __DIR__ . '/php/session_helper.php';
hay_session_start();

if (!empty($_GET['salir'])) {
    hay_session_no_cache_headers();
    hay_session_destroy_completa();
    header('Location: index.php?sesion=1');
    exit;
}

if (!empty($_GET['force_login'])) {
    hay_session_no_cache_headers();
    hay_session_destroy_completa();
    header('Location: index.php?cookie_fix=1');
    exit;
}

if (!empty($_SESSION['user_id']) && empty($_GET['force_login']) && empty($_GET['sesion']) && empty($_GET['login'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema HAY - Iniciar sesión</title>
    <link rel="icon" href="src/logobco.png" type="image/png">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="login-page">

<div class="login-shell">
    <aside class="login-brand" aria-hidden="false">
        <img src="src/logobco.png" alt="Grupo Educativo CNCM">
    </aside>

    <section class="login-panel">
        <div class="login-form-wrap">
            <h1>¡Bienvenido!</h1>

            <?php if (isset($_GET['error'])): ?>
                <p class="login-alert login-alert--error" role="alert"><?php
                    if (!empty($_GET['msg'])) {
                        echo htmlspecialchars($_GET['msg']);
                    } elseif (($_GET['error'] ?? '') === 'sesion') {
                        echo 'No se pudo guardar la sesión. Vuelva a intentar o use otro navegador.';
                    } elseif (($_GET['error'] ?? '') === 'db') {
                        echo 'No hay conexión con la base de datos. Revise config.local.php en el servidor.';
                    } else {
                        echo 'Usuario o contraseña incorrectos.';
                    }
                ?></p>
            <?php endif; ?>

            <?php if (isset($_GET['sesion'])): ?>
                <p class="login-alert login-alert--success" role="status">Sesi&oacute;n cerrada correctamente.</p>
            <?php endif; ?>

            <?php if (!empty($_GET['cookie_fix'])): ?>
                <p class="login-alert login-alert--success" role="status">Cookies de sesi&oacute;n reiniciadas. Inicie sesi&oacute;n de nuevo.</p>
            <?php endif; ?>

            <?php if (isset($_GET['reset'])): ?>
                <p class="login-alert login-alert--success" role="status">Contraseña actualizada. Ya puedes iniciar sesión.</p>
            <?php endif; ?>

            <form id="form-login" action="php/login_process.php" method="POST" autocomplete="on">
                <div class="input-group">
                    <label for="usuario">Correo electrónico</label>
                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        required
                        autocomplete="username"
                        placeholder="usuario o usuario@cncm.edu.mx"
                        inputmode="email"
                    >
                </div>

                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <div class="password-input-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        >
                        <button
                            type="button"
                            class="btn-toggle-password"
                            data-target="password"
                            aria-label="Mostrar contraseña"
                            title="Mostrar contraseña"
                        >
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <a class="login-forgot" href="olvido_password.php">¿Olvidaste tu contraseña?</a>

                <?php if (!empty($_SESSION['user_id'])): ?>
                <p class="login-alert login-alert--error" role="alert">
                    Hay una sesión abierta en este navegador.
                    <a href="index.php?salir=1">Cerrar sesión</a> antes de entrar con otra cuenta.
                </p>
                <?php endif; ?>

                <button type="submit" class="btn-login">Iniciar sesión</button>
            </form>

            <div id="loading-area" style="display:none;" aria-live="polite">
                <img src="src/loading.gif" alt="Cargando...">
            </div>

            <p class="login-footnote" style="margin-top:1.25rem;font-size:12px;color:#666;line-height:1.5;">
                Si el panel funciona en inc&oacute;gnito pero no en Chrome normal,
                <a href="index.php?force_login=1&amp;cookie_fix=1">reinicie las cookies de sesi&oacute;n</a>
                o borre los datos del sitio <code>cncm.edu.mx</code> en Configuraci&oacute;n &rarr; Privacidad.
            </p>
        </div>
    </section>
</div>

<script src="js/login.js"></script>
</body>
</html>
