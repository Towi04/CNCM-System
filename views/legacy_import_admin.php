<?php
require_once __DIR__ . '/../config.php';
$rolReal = function_exists('rbac_rol_real') ? rbac_rol_real() : '';
if (!in_array($rolReal, ['supervisor', 'gerente'], true)) {
    echo '<div class="alert">Solo supervisión o gerencia.</div>';
    return;
}
include dirname(__DIR__) . '/php/legacy_import_admin.php';
