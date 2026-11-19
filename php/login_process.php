<?php
require_once __DIR__ . '/session_helper.php';
hay_session_start();

/** Evita migraciones pesadas en login (evita error 500 si un ALTER falla en producción). */
define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);

try {
    require __DIR__ . '/../config.php';
} catch (Throwable $e) {
    hay_redirect('index.php?error=db&msg=' . urlencode('Error de conexión a la base de datos.'));
}

auth_ensure_email_column($pdo);
login_security_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hay_redirect('index.php');
}

$user_input = trim($_POST['usuario'] ?? '');
$pass_input = (string) ($_POST['password'] ?? '');

if ($user_input === '' || $pass_input === '') {
    hay_redirect('index.php?error=1');
}

$usuario = auth_find_user_by_login($pdo, $user_input);

$bloqueo = login_security_verificar_acceso($pdo, $usuario, $user_input);
if ($bloqueo !== null) {
    hay_redirect('index.php?error=1&msg=' . urlencode($bloqueo));
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
        hay_redirect('index.php?error=1&msg=' . urlencode($msgSusp));
    }

    $idUsuario = (int) ($usuario['id_usuario'] ?? $usuario['id'] ?? 0);
    if ($idUsuario <= 0) {
        hay_redirect('index.php?error=sesion&msg=' . urlencode('Usuario sin id en base de datos.'));
    }

    login_security_registrar_exito($pdo, $idUsuario);

    if (!function_exists('hay_maintenance_activo') && is_file(__DIR__ . '/maintenance_helper.php')) {
        require_once __DIR__ . '/maintenance_helper.php';
    }
    if (function_exists('hay_maintenance_activo') && hay_maintenance_activo()) {
        $rolLogin = function_exists('rbac_normalizar_rol_clave')
            ? rbac_normalizar_rol_clave((string) ($usuario['rol'] ?? ''))
            : strtolower(trim((string) ($usuario['rol'] ?? '')));
        if ($rolLogin !== 'supervisor') {
            hay_redirect('index.php?error=1&msg=' . urlencode(
                'Sistema en actualización. Solo supervisión puede iniciar sesión ahora.'
            ));
        }
    }

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

    $homePlantel = !empty($usuario['id_plantel']) ? (int) $usuario['id_plantel'] : null;
    $preferido = function_exists('plantel_resolver_preferido_login')
        ? plantel_resolver_preferido_login($pdo, $homePlantel, $idUsuario)
        : $homePlantel;
    plantel_inicializar_sesion($pdo, $preferido);
    usuario_actualizar_acceso($pdo, $idUsuario);
    $_SESSION['debe_cambiar_password'] = (int) ($usuario['debe_cambiar_password'] ?? 0);

    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
        hay_redirect('index.php?error=sesion&msg=' . urlencode('La sesión PHP no quedó activa tras el login.'));
    }

    // Una sola regeneración (después de llenar la sesión) para fijar la cookie final.
    try {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
    } catch (Throwable $e) {
        error_log('HAY login regenerate_id: ' . $e->getMessage());
    }
    hay_session_no_cache_headers();
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    $savePath = function_exists('hay_session_save_path') ? hay_session_save_path() : null;
    if ($savePath === null) {
        hay_redirect('index.php?error=sesion&msg=' . urlencode('storage/sessions no es escribible por el usuario de PHP.'));
    }
    // Ir a / (index) — el usuario confirma que esa URL sí abre el panel;
    // index reenvía a dashboard si la sesión quedó activa.
    if (!empty($usuario['debe_cambiar_password'])) {
        hay_redirect('dashboard.php?cambiar_password=1');
    }
    hay_redirect('index.php');
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

hay_redirect('index.php?error=1&msg=' . urlencode('Usuario o contraseña incorrectos.' . $restantes));
