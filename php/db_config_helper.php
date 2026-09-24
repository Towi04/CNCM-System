<?php

/**
 * Credenciales MySQL — misma lógica que config.php (para diagnósticos y scripts).
 *
 * Orden de prioridad:
 * 1) Constantes ya definidas (p. ej. vía config.local.php en Neubox)
 * 2) Variables de entorno / archivo .env (HAY_DB_*)
 * 3) Valores por defecto del hosting (mismo fallback histórico de este helper)
 *
 * @return array{host:string,db:string,user:string,pass:string}
 */
function hay_db_credentials(): array
{
    hay_load_dotenv_once();

    if (!defined('HAY_DB_NAME') && !defined('HAY_DB_USER')) {
        $local = dirname(__DIR__) . '/config.local.php';
        if (is_file($local)) {
            require $local;
        }
    }

    // Fallback histórico (Neubox): si no hay config.local.php ni .env, usa estos valores.
    $host = hay_db_resolve_setting('HAY_DB_HOST', 'localhost');
    $db = hay_db_resolve_setting('HAY_DB_NAME', 'cncmedum_hay_system');
    $user = hay_db_resolve_setting('HAY_DB_USER', 'cncmedum_tovar');
    $pass = hay_db_resolve_setting('HAY_DB_PASS', 'ZXCVqwer1234!"#$');

    return [
        'host' => $host,
        'db' => $db,
        'user' => $user,
        'pass' => $pass,
    ];
}

/**
 * ¿Hay alguna fuente de credenciales configurada (sin contar defaults vacíos)?
 */
function hay_db_credentials_configured(): bool
{
    hay_load_dotenv_once();
    if (defined('HAY_DB_USER') || defined('HAY_DB_PASS') || defined('HAY_DB_NAME')) {
        return true;
    }
    foreach (['HAY_DB_USER', 'HAY_DB_PASS', 'HAY_DB_NAME'] as $key) {
        $v = hay_env_get($key);
        if ($v !== null && $v !== '') {
            return true;
        }
    }
    $root = defined('HAY_ROOT') ? HAY_ROOT : dirname(__DIR__);

    return is_file($root . '/config.local.php') || is_file($root . '/.env');
}

/**
 * define() > getenv/$_ENV (tras cargar .env) > default.
 */
function hay_db_resolve_setting(string $constName, string $default): string
{
    if (defined($constName)) {
        return (string) constant($constName);
    }
    $env = hay_env_get($constName);
    if ($env !== null && $env !== '') {
        return $env;
    }

    return $default;
}

function hay_env_get(string $key): ?string
{
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return (string) $v;
    }
    if (isset($_ENV[$key]) && (string) $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && (string) $_SERVER[$key] !== '') {
        return (string) $_SERVER[$key];
    }

    return null;
}

/**
 * Carga opcional de `.env` en la raíz (sin dependencia Composer).
 * No sobrescribe variables ya definidas en el entorno del servidor.
 */
function hay_load_dotenv_once(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $root = defined('HAY_ROOT') ? HAY_ROOT : dirname(__DIR__);
    $path = $root . '/.env';
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
            continue;
        }
        if (
            (str_starts_with($val, '"') && str_ends_with($val, '"'))
            || (str_starts_with($val, "'") && str_ends_with($val, "'"))
        ) {
            $val = substr($val, 1, -1);
        }
        if (hay_env_get($key) !== null) {
            continue;
        }
        putenv($key . '=' . $val);
        $_ENV[$key] = $val;
    }
}

/**
 * Conexión PDO global (disponible tras incluir config.php).
 */
function hay_pdo(): PDO
{
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Conexión PDO no inicializada. Incluya config.php primero.');
    }

    return $pdo;
}
