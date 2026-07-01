<?php
require_once __DIR__ . '/session_helper.php';
hay_session_start();

/** Evita migraciones pesadas en login (evita error 500 si un ALTER falla en producción). */
define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);

try {
    require __DIR__ . '/../config.php';
} catch (Throwable $e) {
    header('Location: ../index.php?error=db&msg=' . urlencode('Error de conexión a la base de datos.'));
    exit;
}

auth_ensure_email_column($pdo);
login_security_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$user_input = trim($_POST['usuario'] ?? '');
$pass_input = (string) ($_POST['password'] ?? '');

if ($user_input === '' || $pass_input === '') {
    header('Location: ../index.php?error=1');
    exit;
}

$usuario = auth_find_user_by_login($pdo, $user_input);

$bloqueo = login_security_verificar_acceso($pdo, $usuario, $user_input);
if ($bloqueo !== null) {
    header('Location: ../index.php?error=1&msg=' . urlencode($bloqueo));
    exit;
}

if ($usuario && password_verify($pass_input, $usuario['password'])) {
    $msgSusp = null;
    if (function_exists('usuario_suspension_mensaje_login_bloqueado')) {
        $msgSusp = usuario_suspension_mensaje_login_bloqueado($usuario);
    }
    if ($msgSusp === null) {
        $msgSusp = usuario_verificar_suspension_inactividad($pdo, $usuario);
    }
    if ($msgSusp) {
        login_security_registrar_fallo($pdo, $usuario, $user_input, 'suspendido');
        header('Location: ../index.php?error=1&msg=' . urlencode($msgSusp));
        exit;
    }

    $idUsuario = (int) ($usuario['id_usuario'] ?? $usuario['id'] ?? 0);
    if ($idUsuario <= 0) {
        header('Location: ../index.php?error=sesion&msg=' . urlencode('Usuario sin id en base de datos.'));
        exit;
    }

    login_security_registrar_exito($pdo, $idUsuario);

    if (ini_get('session.use_cookies')) {
        hay_session_preparar_nuevo_login();
    } else {
        $_SESSION = [];
    }

    $_SESSION['user_id'] = $idUsuario;
    $_SESSION['nombre'] = $usuario['nombre'] ?? '';
    $_SESSION['departamento'] = $usuario['departamento'] ?? '';
    rbac_inicializar_sesion_tras_login($usuario);
    $_SESSION['apellido'] = $usuario['apellido'] ?? '';
    user_avatar_refresh_session($pdo, $idUsuario);
    $_SESSION['fullname'] = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''));
    $_SESSION['id_alumno_link'] = (int) ($usuario['id_alumno'] ?? 0);

    if (function_exists('usuario_suspension_aplicar_sesion')) {
        usuario_suspension_aplicar_sesion($usuario);
    }

    $preferido = !empty($usuario['id_plantel']) ? (int) $usuario['id_plantel'] : null;
    plantel_inicializar_sesion($pdo, $preferido);
    usuario_actualizar_acceso($pdo, $idUsuario);
    $_SESSION['debe_cambiar_password'] = (int) ($usuario['debe_cambiar_password'] ?? 0);

    session_regenerate_id(true);
    hay_session_no_cache_headers();
    if (function_exists('session_write_close')) {
        session_write_close();
    }
    if (!empty($usuario['debe_cambiar_password'])) {
        header('Location: ../dashboard.php?cambiar_password=1');
        exit;
    }
    header('Location: ../dashboard.php');
    exit;
}

login_security_registrar_fallo($pdo, $usuario, $user_input, 'password');

$restantes = '';
if ($usuario) {
    $fallidos = (int) ($usuario['login_fallidos'] ?? 0) + 1;
    $quedan = max(0, LOGIN_MAX_INTENTOS - $fallidos);
    if ($quedan > 0 && $quedan < LOGIN_MAX_INTENTOS) {
        $restantes = ' Le quedan ' . $quedan . ' intento(s) antes del bloqueo temporal.';
    } elseif ($quedan === 0) {
        $restantes = ' Cuenta bloqueada temporalmente. Use «¿Olvidaste tu contraseña?» o contacte a recepción.';
    }
}

header('Location: ../index.php?error=1&msg=' . urlencode('Usuario o contraseña incorrectos.' . $restantes));
exit;
