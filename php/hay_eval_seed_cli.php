<?php

/** CLI: php php/hay_eval_seed_cli.php [--forzar] */
define('HAY_SKIP_SCHEMA_BOOTSTRAP', false);
require __DIR__ . '/../config.php';

if (php_sapi_name() !== 'cli') {
    echo "Solo CLI\n";
    exit(1);
}

$forzar = in_array('--forzar', $argv ?? [], true);
$res = hay_eval_seed_profesor_ingles($pdo, $forzar);
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit(empty($res['ok']) ? 1 : 0);
