<?php

/**

 * Diagnóstico de conexión Google Workspace (Admin SDK).

 *

 * - ?paso=config  → archivos locales (sin sesión HAY)

 * - ?paso=api     → prueba real contra Google (requiere sesión supervisor/admin)

 */

require __DIR__ . '/../config.php';



header('Content-Type: application/json; charset=utf-8');



require_once __DIR__ . '/google_helper.php';



$paso = trim((string) ($_GET['paso'] ?? 'api'));

if ($paso === '') {

    $paso = 'api';

}



function google_test_puede_acceder(): bool

{

    $idU = (int) ($_SESSION['user_id'] ?? 0);

    if ($idU <= 0) {

        return false;

    }



    global $pdo;

    if (isset($pdo) && $pdo instanceof PDO && function_exists('rbac_reparar_sesion_desde_cuenta_bd')) {

        rbac_reparar_sesion_desde_cuenta_bd($pdo, $idU);

    }

    if (function_exists('rbac_supervisor_aplicar_sesion')) {

        rbac_supervisor_aplicar_sesion();

    }



    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {

        return true;

    }

    if (function_exists('rbac_es_supervisor') && rbac_es_supervisor()) {

        return true;

    }

    if (function_exists('rbac_cap') && rbac_cap('admin_usuarios')) {

        return true;

    }



    $real = function_exists('rbac_rol_real') ? rbac_rol_real() : '';



    return in_array($real, ['supervisor', 'director', 'gerente', 'admin'], true);

}



function google_test_sesion_diagnostico(): array

{

    $idU = (int) ($_SESSION['user_id'] ?? 0);

    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : (string) ($_SESSION['rol'] ?? '');

    $rolReal = function_exists('rbac_rol_real') ? rbac_rol_real() : $rol;

    $cookieName = function_exists('hay_session_name') ? hay_session_name() : 'HAYSESSID';



    return [

        'sesion_activa' => $idU > 0,

        'user_id' => $idU > 0 ? $idU : null,

        'rol_efectivo' => $rol !== '' ? $rol : null,

        'rol_real' => $rolReal !== '' ? $rolReal : null,

        'cookie_hay' => !empty($_COOKIE[$cookieName]),

        'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),

        'hint' => 'Abra la prueba en el mismo navegador y dominio donde inició sesión (ej. https://www.cncm.edu.mx/hay/php/google_test.php?paso=api).',

    ];

}



if ($paso === 'config') {

    $cfg = google_config_status();

    $out = [

        'status' => $cfg['ok'] ? 'ok' : 'error',

        'message' => $cfg['message'],

        'config' => $cfg,

        'paso' => 'config',

    ];

    if (!$cfg['ok']) {

        $out['autoload'] = google_autoload_diagnostico();

    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    exit;

}



if (!google_test_puede_acceder()) {

    http_response_code(403);

    echo json_encode([

        'status' => 'error',

        'message' => 'No autorizado en HAY: inicie sesión como supervisor o personal con permiso de administración de usuarios.',

        'tipo' => 'hay_sesion',

        'paso' => $paso,

        'diagnostico' => google_test_sesion_diagnostico(),

    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    exit;

}



$res = google_test_connection();

echo json_encode([

    'status' => !empty($res['ok']) ? 'ok' : 'error',

    'message' => $res['message'] ?? '',

    'detail' => $res['detail'] ?? null,

    'tipo' => 'google_api',

    'paso' => 'api',

], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

