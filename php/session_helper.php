<?php

require_once __DIR__ . '/branding.php';

/**
 * Sesión con cookie en la raíz de la aplicación, no en /php/ (evita login que “no hace nada”).
 */

function hay_session_bootstrap_config(): void

{

    static $done = false;

    if ($done) {

        return;

    }

    $done = true;

    $local = dirname(__DIR__) . '/config.local.php';

    if (is_file($local)) {

        require_once $local;

    }

}



function hay_app_cookie_path(): string

{

    hay_session_bootstrap_config();



    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

    $dir = str_replace('\\', '/', dirname($script));

    if ($dir === '/' || $dir === '.' || $dir === '') {

        // App served at domain root (GCP staging/prod): always cookie path "/"
        // Ignore legacy HAY_WEB_ROOT=/hay/ copied from Neubox.
        return '/';

    }

    if (substr($dir, -4) === '/php') {

        $dir = dirname($dir);

    }

    if (substr($dir, -6) === '/views') {

        $dir = dirname($dir);

    }

    if ($dir === '/' || $dir === '.' || $dir === '') {

        return '/';

    }



    if (defined('HAY_WEB_ROOT') && HAY_WEB_ROOT !== '') {

        $root = (string) HAY_WEB_ROOT;

        if ($root[0] !== '/') {

            $root = '/' . $root;

        }

        $normalized = rtrim($root, '/') . '/';

        // Only honor HAY_WEB_ROOT when the script actually lives under it
        if ($normalized !== '/' && str_starts_with($dir . '/', rtrim($normalized, '/') . '/')) {

            return $normalized;

        }

    }



    return rtrim($dir, '/') . '/';

}



function hay_request_is_https(): bool

{

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {

        return true;

    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {

        return true;

    }



    return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;

}



function hay_session_save_path(): ?string

{

    $candidates = [];

    $dir = dirname(__DIR__) . '/storage/sessions';

    $candidates[] = $dir;

    $tmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/hay_sessions_' . substr(md5(dirname(__DIR__)), 0, 12);

    $candidates[] = $tmp;



    foreach ($candidates as $path) {

        $parent = dirname($path);

        if (!is_dir($parent)) {

            @mkdir($parent, 0775, true);

        }

        if (!is_dir($path)) {

            @mkdir($path, 0770, true);

        }

        if (is_dir($path) && is_writable($path)) {

            return $path;

        }

    }



    return null;

}



function hay_session_name(): string

{

    return 'HAYSESSID';

}



function hay_session_cookie_paths_to_clear(): array
{
    $paths = ['/'];
    $current = hay_app_cookie_path();
    if ($current !== '' && $current !== '/' && !in_array($current, $paths, true)) {
        $paths[] = $current;
    }
    // Rutas legacy (instalación anterior en /hay/)
    foreach (['/hay/', '/hay/php/', '/hay/views/'] as $legacy) {
        if (!in_array($legacy, $paths, true)) {
            $paths[] = $legacy;
        }
    }

    return $paths;
}



function hay_session_cookie_domains_to_clear(): array

{

    $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));

    $domains = [''];

    if ($host === '') {

        return $domains;

    }



    $domains[] = $host;

    if (str_starts_with($host, 'www.')) {

        $base = substr($host, 4);

        if ($base !== '') {

            $domains[] = $base;

            $domains[] = '.' . $base;

        }

    } elseif (substr_count($host, '.') >= 1) {

        $domains[] = 'www.' . $host;

        $domains[] = '.' . $host;

    }



    return array_values(array_unique($domains));

}



function hay_session_expire_cookie(string $name, string $path, string $domain = ''): void

{

    if (headers_sent()) {

        return;

    }



    $secure = hay_request_is_https();

    if (PHP_VERSION_ID >= 70300) {

        setcookie($name, '', [

            'expires' => time() - 42000,

            'path' => $path,

            'domain' => $domain,

            'secure' => $secure,

            'httponly' => true,

            'samesite' => 'Lax',

        ]);

    } else {

        setcookie($name, '', time() - 42000, $path, $domain, $secure, true);

    }

}



/** Solo PHPSESSID heredada (no tocar HAYSESSID activa). */

function hay_session_purge_phpsessid_legacy(): void

{

    if (headers_sent()) {

        return;

    }



    foreach (hay_session_cookie_paths_to_clear() as $path) {

        foreach (hay_session_cookie_domains_to_clear() as $domain) {

            hay_session_expire_cookie('PHPSESSID', $path, $domain);

        }

    }

}



/** Borra todas las cookies de sesión. Usar solo en logout / force_login. */

function hay_session_purge_legacy_cookies(): void

{

    if (headers_sent()) {

        return;

    }



    $names = ['PHPSESSID', hay_session_name()];

    foreach ($names as $name) {

        foreach (hay_session_cookie_paths_to_clear() as $path) {

            foreach (hay_session_cookie_domains_to_clear() as $domain) {

                hay_session_expire_cookie($name, $path, $domain);

            }

        }

    }



    if (session_status() === PHP_SESSION_ACTIVE) {

        $params = session_get_cookie_params();

        hay_session_expire_cookie(session_name(), (string) ($params['path'] ?? hay_app_cookie_path()), (string) ($params['domain'] ?? ''));

    }

}



function hay_session_destroy_completa(): void

{

    if (session_status() === PHP_SESSION_NONE) {

        hay_session_start();

    }



    $_SESSION = [];

    $params = session_get_cookie_params();

    $name = session_name();



    if (session_status() === PHP_SESSION_ACTIVE) {

        session_destroy();

    }



    hay_session_purge_legacy_cookies();

    hay_session_expire_cookie($name, (string) ($params['path'] ?? hay_app_cookie_path()), (string) ($params['domain'] ?? ''));

}



/**
 * Limpia datos de sesión antes de autenticar.
 * NO regenera el id aquí: login_process regenera una sola vez después de
 * llenar $_SESSION. Un doble regenerate_id(true) emite dos Set-Cookie y
 * puede dejar al navegador con el id borrado → primer dashboard en blanco.
 */
function hay_session_preparar_nuevo_login(): void

{

    if (session_status() === PHP_SESSION_NONE) {

        hay_session_start();

    }



    $_SESSION = [];

    hay_session_purge_phpsessid_legacy();

}



function hay_session_start(): void

{

    if (session_status() !== PHP_SESSION_NONE) {

        return;

    }



    hay_session_bootstrap_config();



    $savePath = hay_session_save_path();

    if ($savePath !== null) {

        session_save_path($savePath);

    }



    session_name(hay_session_name());



    $secure = hay_request_is_https();

    $path = hay_app_cookie_path();

    // Reinforce via ini (some SAPIs ignore late session_set_cookie_params)
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_path', $path);
    @ini_set('session.cookie_samesite', 'Lax');
    if ($secure) {
        @ini_set('session.cookie_secure', '1');
    }



    if (PHP_VERSION_ID >= 70300) {

        session_set_cookie_params([

            'lifetime' => 0,

            'path' => $path,

            'domain' => '',

            'secure' => $secure,

            'httponly' => true,

            'samesite' => 'Lax',

        ]);

    } else {

        session_set_cookie_params(0, $path, '', $secure, true);

    }



    session_start();



    if (!empty($_COOKIE['PHPSESSID'])) {

        hay_session_purge_phpsessid_legacy();

    }

}



function hay_session_release_lock(): void

{

    if (session_status() === PHP_SESSION_ACTIVE) {

        session_write_close();

    }

}



function hay_request_debe_liberar_sesion(): bool

{

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    if (str_contains($script, '/views/') || str_contains($script, '_api.php')) {

        return true;

    }



    return false;

}



function hay_session_no_cache_headers(): void

{

    if (headers_sent()) {

        return;

    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    header('Pragma: no-cache');

    header('Expires: 0');

}

/**
 * URL absoluta (https://host/ruta) para Location post-login.
 * Usa el mismo criterio de ruta que la cookie de sesión.
 */
function hay_absolute_url(string $pathFromAppRoot): string
{
    $pathFromAppRoot = str_replace('\\', '/', $pathFromAppRoot);
    $qPos = strpos($pathFromAppRoot, '?');
    $hashPos = strpos($pathFromAppRoot, '#');
    $cut = strlen($pathFromAppRoot);
    if ($qPos !== false) {
        $cut = min($cut, $qPos);
    }
    if ($hashPos !== false) {
        $cut = min($cut, $hashPos);
    }
    $pathOnly = substr($pathFromAppRoot, 0, $cut);
    $suffix = substr($pathFromAppRoot, $cut);

    if (preg_match('#^https?://#i', $pathOnly)) {
        return $pathFromAppRoot;
    }

    $base = hay_app_cookie_path();
    if ($base === '/' || $base === '') {
        $urlPath = '/' . ltrim($pathOnly, '/');
    } else {
        $urlPath = rtrim($base, '/') . '/' . ltrim($pathOnly, '/');
    }
    $urlPath = preg_replace('#/+#', '/', $urlPath) ?: '/';

    $https = function_exists('hay_request_is_https') ? hay_request_is_https() : false;
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scheme = $https ? 'https' : 'http';

    return $scheme . '://' . $host . $urlPath . $suffix;
}

/**
 * Redirect PRG (303) con Location absoluto + HTML de respaldo (evita pantalla en blanco
 * si el cliente no sigue bien un 302 vacío tras POST de login).
 */
function hay_redirect(string $pathFromAppRoot, int $status = 303): void
{
    $url = hay_absolute_url($pathFromAppRoot);
    if (!headers_sent()) {
        hay_session_no_cache_headers();
        header('Location: ' . $url, true, $status);
        header('Content-Type: text/html; charset=utf-8');
    }
    $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
        . '<meta http-equiv="refresh" content="0;url=' . $safe . '">'
        . '<title>Redirigiendo…</title></head><body>'
        . '<p>Redirigiendo… <a href="' . $safe . '">Continuar</a></p>'
        . '</body></html>';
    exit;
}

