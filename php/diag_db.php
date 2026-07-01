<?php
/**
 * Prueba solo conexión MySQL (sin cargar todos los helpers).
 * Abrir: https://cncm.edu.mx/hay/php/diag_db.php
 */
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/db_config_helper.php';
$creds = hay_db_credentials();

try {
    $pdo = new PDO(
        'mysql:host=' . $creds['host'] . ';dbname=' . $creds['db'] . ';charset=utf8mb4',
        $creds['user'],
        $creds['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $n = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    echo "DB OK — usuarios: {$n}\n";
    echo 'Usuario MySQL: ' . $creds['user'] . "\n";
    echo 'Base: ' . $creds['db'] . "\n";
    $meta = $pdo->query("SHOW TABLES LIKE 'hay_app_meta'")->fetchColumn();
    if ($meta) {
        $v = $pdo->query("SELECT valor FROM hay_app_meta WHERE clave = 'schema_bootstrap_version' LIMIT 1")->fetchColumn();
        echo 'schema_bootstrap_version: ' . ($v ?: '(vacío)') . "\n";
    } else {
        echo "hay_app_meta: aún no existe (normal antes del primer dashboard)\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'DB ERROR: ' . $e->getMessage() . "\n";
    echo "Revise config.local.php: HAY_DB_USER y HAY_DB_PASS (usuario completo de cPanel, ej. cncmedum_tovar).\n";
}
