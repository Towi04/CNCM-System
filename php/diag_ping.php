<?php
/**
 * Prueba mínima sin config.php ni migraciones.
 * Abrir: https://cncm.edu.mx/hay/php/diag_ping.php
 * Debe mostrar "HAY OK" en menos de 1 segundo.
 */
header('Content-Type: text/plain; charset=utf-8');
echo "HAY OK\n";
echo 'PHP ' . PHP_VERSION . "\n";
echo 'Hora ' . date('Y-m-d H:i:s') . "\n";
