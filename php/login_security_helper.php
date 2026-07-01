<?php

/**
 * Protección contra fuerza bruta en login.
 */

if (!defined('LOGIN_MAX_INTENTOS')) {
    define('LOGIN_MAX_INTENTOS', 5);
}
if (!defined('LOGIN_BLOQUEO_MINUTOS')) {
    define('LOGIN_BLOQUEO_MINUTOS', 30);
}
if (!defined('LOGIN_IP_MAX_INTENTOS')) {
    define('LOGIN_IP_MAX_INTENTOS', 25);
}
if (!defined('LOGIN_IP_VENTANA_MINUTOS')) {
    define('LOGIN_IP_VENTANA_MINUTOS', 15);
}

function login_security_ensure_schema(PDO $pdo): void
{
    if (function_exists('usuario_ensure_schema')) {
        usuario_ensure_schema($pdo);
    }
    plantel_ensure_column($pdo, 'usuarios', 'login_fallidos', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0', 'suspendido');
    plantel_ensure_column($pdo, 'usuarios', 'login_bloqueado_hasta', 'DATETIME NULL', 'login_fallidos');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS usuario_login_intento (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT UNSIGNED NULL,
            username_intento VARCHAR(120) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            exito TINYINT(1) NOT NULL DEFAULT 0,
            motivo VARCHAR(80) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_uli_usuario (id_usuario, creado_en),
            KEY idx_uli_ip (ip, creado_en),
            KEY idx_uli_user_txt (username_intento, creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function login_security_ip_cliente(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return '0.0.0.0';
    }

    return $ip;
}

/** Mensaje de bloqueo o null si puede intentar. */
function login_security_verificar_acceso(PDO $pdo, ?array $usuario, string $usernameIntento): ?string
{
    login_security_ensure_schema($pdo);
    $ip = login_security_ip_cliente();

    if (login_security_ip_bloqueada($pdo, $ip)) {
        return 'Demasiados intentos desde esta red. Espere ' . LOGIN_IP_VENTANA_MINUTOS
            . ' minutos o contacte a recepción / coordinación.';
    }

    if ($usuario) {
        if ((int) ($usuario['suspendido'] ?? 0)) {
            if (function_exists('usuario_suspension_mensaje_login_bloqueado')) {
                $msgSusp = usuario_suspension_mensaje_login_bloqueado($usuario);
                if ($msgSusp !== null) {
                    return $msgSusp;
                }
            } else {
                return login_security_mensaje_suspendido($usuario);
            }
        }

        $hasta = $usuario['login_bloqueado_hasta'] ?? null;
        if ($hasta && strtotime((string) $hasta) > time()) {
            $mins = max(1, (int) ceil((strtotime((string) $hasta) - time()) / 60));

            return 'Cuenta bloqueada por intentos fallidos. Espere ' . $mins
                . ' minuto(s), use «¿Olvidaste tu contraseña?» o pida a recepción que la desbloquee.';
        }
    }

    return null;
}

function login_security_mensaje_suspendido(array $usuario): string
{
    if (($usuario['rol'] ?? '') === 'alumno') {
        return 'Tu cuenta está suspendida. Contacta a recepción.';
    }

    return 'Cuenta suspendida. Contacte a recepción, coordinación o un administrador.';
}

function login_security_ip_bloqueada(PDO $pdo, string $ip): bool
{
    if ($ip === '0.0.0.0') {
        return false;
    }
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM usuario_login_intento
         WHERE ip = ? AND exito = 0
           AND creado_en >= (NOW() - INTERVAL ? MINUTE)'
    );
    $st->execute([$ip, LOGIN_IP_VENTANA_MINUTOS]);

    return (int) $st->fetchColumn() >= LOGIN_IP_MAX_INTENTOS;
}

function login_security_registrar_intento(
    PDO $pdo,
    ?int $idUsuario,
    string $usernameIntento,
    bool $exito,
    string $motivo = ''
): void {
    login_security_ensure_schema($pdo);
    $pdo->prepare(
        'INSERT INTO usuario_login_intento (id_usuario, username_intento, ip, exito, motivo)
         VALUES (?,?,?,?,?)'
    )->execute([
        $idUsuario ?: null,
        mb_substr(trim($usernameIntento), 0, 120),
        login_security_ip_cliente(),
        $exito ? 1 : 0,
        $motivo !== '' ? mb_substr($motivo, 0, 80) : null,
    ]);
}

function login_security_registrar_fallo(PDO $pdo, ?array $usuario, string $usernameIntento, string $motivo = 'password'): void
{
    login_security_ensure_schema($pdo);
    $idUsuario = $usuario ? (int) ($usuario['id_usuario'] ?? 0) : 0;
    login_security_registrar_intento($pdo, $idUsuario ?: null, $usernameIntento, false, $motivo);

    if ($idUsuario <= 0) {
        return;
    }

    $st = $pdo->prepare('SELECT login_fallidos FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$idUsuario]);
    $fallidos = (int) $st->fetchColumn() + 1;

    if ($fallidos >= LOGIN_MAX_INTENTOS) {
        $pdo->prepare(
            'UPDATE usuarios SET login_fallidos = ?, login_bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? MINUTE)
             WHERE id_usuario = ?'
        )->execute([$fallidos, LOGIN_BLOQUEO_MINUTOS, $idUsuario]);
    } else {
        $pdo->prepare('UPDATE usuarios SET login_fallidos = ? WHERE id_usuario = ?')
            ->execute([$fallidos, $idUsuario]);
    }
}

function login_security_registrar_exito(PDO $pdo, int $idUsuario): void
{
    login_security_ensure_schema($pdo);
    $pdo->prepare(
        'UPDATE usuarios SET login_fallidos = 0, login_bloqueado_hasta = NULL WHERE id_usuario = ?'
    )->execute([$idUsuario]);
    login_security_registrar_intento($pdo, $idUsuario, '', true, 'ok');
}

function login_security_limpiar_bloqueo(PDO $pdo, int $idUsuario): void
{
    if ($idUsuario <= 0) {
        return;
    }
    login_security_ensure_schema($pdo);
    $pdo->prepare(
        'UPDATE usuarios SET login_fallidos = 0, login_bloqueado_hasta = NULL WHERE id_usuario = ?'
    )->execute([$idUsuario]);
}

function login_security_usuario_bloqueado_por_intentos(array $usuario): bool
{
    $hasta = $usuario['login_bloqueado_hasta'] ?? null;
    if (!$hasta) {
        return (int) ($usuario['login_fallidos'] ?? 0) >= LOGIN_MAX_INTENTOS;
    }

    return strtotime((string) $hasta) > time();
}

function login_security_puede_desbloquear(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('usuario_desbloquear_login')) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['admin', 'director', 'supervisor', 'gerente', 'coordinador', 'coordinacion', 'recepcion'], true);
}

function login_security_desbloquear(PDO $pdo, int $idUsuario, int $idAdmin): array
{
    if (!login_security_puede_desbloquear()) {
        return ['ok' => false, 'message' => 'Sin permiso para desbloquear cuentas'];
    }
    if ($idUsuario <= 0) {
        return ['ok' => false, 'message' => 'Usuario no válido'];
    }
    login_security_limpiar_bloqueo($pdo, $idUsuario);

    return ['ok' => true, 'message' => 'Cuenta desbloqueada. El usuario ya puede iniciar sesión.'];
}

function login_security_etiqueta_estado(array $usuario): string
{
    if ((int) ($usuario['suspendido'] ?? 0)) {
        return 'suspendido';
    }
    if (login_security_usuario_bloqueado_por_intentos($usuario)) {
        return 'bloqueado_login';
    }

    return 'activo';
}
