<?php
/**
 * Diagnóstico rápido de sesión / RBAC / menú (solo supervisor o localhost).
 */
require __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

$esLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
if (!$esLocal && (!function_exists('rbac_es_supervisor') || !rbac_es_supervisor())) {
    http_response_code(403);
    echo "Sin permiso.\n";
    exit;
}

echo "=== HAY diag_panel ===\n\n";
echo 'user_id: ' . ($_SESSION['user_id'] ?? '(vacío)') . "\n";
echo 'rol: ' . (function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '')) . "\n";
echo 'rol_real: ' . (function_exists('rbac_rol_real') ? rbac_rol_real() : '') . "\n";
echo 'rbac_acceso_total: ' . (!empty($_SESSION['rbac_acceso_total']) ? '1' : '0') . "\n";
echo 'rbac_alcance_planteles: ' . ($_SESSION['rbac_alcance_planteles'] ?? '') . "\n";
echo 'rbac_tiene_acceso_total(): ' . (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total() ? 'true' : 'false') . "\n";
echo 'rbac_cap(menu_admin): ' . (function_exists('rbac_cap') && rbac_cap('menu_admin') ? 'true' : 'false') . "\n";
echo 'plantel_id: ' . ($_SESSION['plantel_id'] ?? '') . "\n";
echo 'plantel_nombre: ' . ($_SESSION['plantel_nombre'] ?? '') . "\n";

if (isset($pdo) && $pdo instanceof PDO) {
    echo 'planteles_accesibles: ' . count(plantel_list_accesibles($pdo, true)) . "\n";
    echo 'schema_bootstrap_version: ' . (hay_meta_get($pdo, 'schema_bootstrap_version') ?? '(vacío)') . "\n";
    echo 'schema_ddl_runtime: ' . (hay_meta_get($pdo, 'schema_ddl_runtime') ?? '(vacío)') . "\n";
    $rol = rbac_rol_por_clave($pdo, 'supervisor');
    if ($rol) {
        echo 'rol supervisor acceso_total BD: ' . ($rol['acceso_total'] ?? '?') . "\n";
    }
}

echo "\nFunciones críticas:\n";
echo '  rbac_tiene_acceso_total: ' . (function_exists('rbac_tiene_acceso_total') ? 'OK' : 'FALTA') . "\n";
echo '  rbac_cap: ' . (function_exists('rbac_cap') ? 'OK' : 'FALTA') . "\n";

echo "\nURLs:\n";
echo '  HAY_WEB_ROOT: ' . hay_web_root() . "\n";
echo '  navigation.js: ' . hay_asset_url('js/navigation.js') . "\n";
