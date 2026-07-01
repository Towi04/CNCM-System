<?php

/**
 * Credenciales MySQL — misma lógica que config.php (para diagnósticos y scripts).
 *
 * @return array{host:string,db:string,user:string,pass:string}
 */
function hay_db_credentials(): array
{
    if (!defined('HAY_DB_NAME') && !defined('HAY_DB_USER')) {
        $local = dirname(__DIR__) . '/config.local.php';
        if (is_file($local)) {
            require $local;
        }
    }

    return [
        'host' => defined('HAY_DB_HOST') ? (string) HAY_DB_HOST : 'localhost',
        'db' => defined('HAY_DB_NAME') ? (string) HAY_DB_NAME : 'cncmedum_hay_system',
        'user' => defined('HAY_DB_USER') ? (string) HAY_DB_USER : 'cncmedum_tovar',
        'pass' => defined('HAY_DB_PASS') ? (string) HAY_DB_PASS : 'ZXCVqwer1234!"#$',
    ];
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
