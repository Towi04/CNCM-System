<?php
/**
 * Copiar a config.php y ajustar a tu XAMPP local (BD legado).
 * Este extractor NO necesita el Portal HAY; solo PDO MySQL.
 */
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'cncmedum_legado', // o el nombre de tu dump restaurado
    'db_user' => 'root',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',
];
