<?php
/**
 * Diagnóstico temporal de login — eliminar del servidor después de corregir.
 * Acceso: /hay/php/login_diagnostico.php
 */
header('Content-Type: text/plain; charset=utf-8');
define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);

echo "PHP " . PHP_VERSION . "\n\n";

try {
    require __DIR__ . '/../config.php';
    echo "config.php: OK\n";
} catch (Throwable $e) {
    echo "config.php FALLO: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
    exit;
}

try {
    require __DIR__ . '/auth_helpers.php';
    echo "auth_helpers.php: OK\n";
    auth_ensure_email_column($pdo);
    echo "auth_ensure_email_column: OK\n";
} catch (Throwable $e) {
    echo "auth FALLO: " . $e->getMessage() . "\n";
}

echo "\nProbando bootstrap de esquema (como dashboard)...\n";
try {
    hay_bootstrap_schema($pdo);
    echo "hay_bootstrap_schema: terminó (revisa error_log del servidor si algo falló)\n";
} catch (Throwable $e) {
    echo "hay_bootstrap_schema FALLO: " . $e->getMessage() . "\n";
}

echo "\nListo.\n";
