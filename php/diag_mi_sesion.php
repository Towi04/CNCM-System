<?php

/**

 * Diagnóstico de sesión del usuario actual (cualquier rol logueado).

 */

require_once __DIR__ . '/session_helper.php';

hay_session_start();



header('Content-Type: text/plain; charset=utf-8');



$cookieName = session_name();

echo "=== HAY diag_mi_sesion ===\n\n";

echo 'session_status: ' . session_status() . " (1=disabled, 2=active)\n";

echo 'session_id: ' . (session_id() ?: '(vacío)') . "\n";

echo 'cookie_name: ' . $cookieName . "\n";

echo 'cookie_path: ' . hay_app_cookie_path() . "\n";

echo 'cookie_enviada: ' . (isset($_COOKIE[$cookieName]) ? 'SI (' . substr((string) $_COOKIE[$cookieName], 0, 8) . '…)' : 'NO') . "\n";

echo 'cookie PHPSESSID (legacy): ' . (isset($_COOKIE['PHPSESSID']) ? 'SI — BORRE datos del sitio' : 'NO') . "\n";

echo 'cookies recibidas: ' . implode(', ', array_keys($_COOKIE)) . "\n";

echo 'HTTPS: ' . (hay_request_is_https() ? 'SI' : 'NO') . "\n";

echo 'host: ' . ($_SERVER['HTTP_HOST'] ?? '') . "\n";

echo 'script: ' . ($_SERVER['SCRIPT_NAME'] ?? '') . "\n\n";



try {

    require __DIR__ . '/../config.php';

} catch (Throwable $e) {

    echo "Error cargando config: " . $e->getMessage() . "\n";

    exit;

}



if (empty($_SESSION['user_id'])) {

    http_response_code(401);

    echo "No hay sesión activa (user_id vacío).\n\n";
    echo "Compruebe:\n";
    echo "  1. Entró al panel en https://www.cncm.edu.mx/hay/ (use siempre www).\n";
    echo "  2. Abra este diagnóstico en la MISMA pestaña/navegador (no incógnito aparte).\n";
    echo "  3. cookie_enviada arriba debe decir SI; si dice NO, la cookie no llega (dominio/ruta distinta).\n";
    echo "  4. Tras subir config.php actualizado, cierre sesión, Ctrl+F5 y vuelva a entrar.\n";
    exit;

}



echo "=== Sesión del usuario ===\n\n";

echo 'user_id: ' . $_SESSION['user_id'] . "\n";

echo 'nombre: ' . ($_SESSION['fullname'] ?? $_SESSION['nombre'] ?? '') . "\n";

echo 'rol sesión: ' . rbac_rol_efectivo() . "\n";

echo 'rol_real: ' . rbac_rol_real() . "\n";

echo 'id_rol sesión: ' . ($_SESSION['id_rol'] ?? '(vacío)') . "\n";

echo 'rbac_acceso_total: ' . (!empty($_SESSION['rbac_acceso_total']) ? '1' : '0') . "\n";



if (isset($pdo) && $pdo instanceof PDO) {

    echo 'rbac_jerarquia_v3_done: ' . (hay_meta_get($pdo, 'rbac_jerarquia_v3_done') ?? '(vacío)') . "\n";

    $st = $pdo->prepare(
        'SELECT u.rol, u.id_rol, u.id_plantel, r.clave AS rol_clave, COALESCE(r.acceso_total, 0) AS acceso_total
         FROM usuarios u
         LEFT JOIN roles r ON r.id_rol = u.id_rol
         WHERE u.id_usuario = ? LIMIT 1'
    );

    $st->execute([(int) $_SESSION['user_id']]);

    $u = $st->fetch(PDO::FETCH_ASSOC);

    if ($u) {

        echo 'rol BD: ' . ($u['rol'] ?? '') . "\n";

        echo 'id_rol BD: ' . ($u['id_rol'] ?? '') . "\n";

        echo 'id_plantel BD: ' . ($u['id_plantel'] ?? '') . "\n";

        echo 'rol_clave BD: ' . ($u['rol_clave'] ?? '') . "\n";

        echo 'acceso_total BD: ' . ($u['acceso_total'] ?? '') . "\n";

    }

    $caps = $_SESSION['rbac_caps'] ?? [];

    echo 'privilegios en sesión: ' . (is_array($caps) ? count($caps) : 0) . "\n";

    echo 'menu_preregistro: ' . (rbac_cap('menu_preregistro') ? 'SI' : 'NO') . "\n";

    echo 'preregistro_puede_acceder: ' . (preregistro_puede_acceder() ? 'SI' : 'NO') . "\n";

    if (is_array($caps) && $caps !== []) {

        echo "\nPrimeros privilegios:\n";

        foreach (array_slice($caps, 0, 12) as $c) {

            echo '  - ' . $c . "\n";

        }

    }

    $den = $_SESSION['rbac_usuario_deniega'] ?? [];

    if (is_array($den) && $den !== []) {

        echo "\nDenegados (usuario_privilegios):\n";

        foreach ($den as $c) {

            echo '  ! ' . $c . "\n";

        }

    }

}

