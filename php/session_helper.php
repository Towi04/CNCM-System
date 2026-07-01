<?php



/**

 * Sesión con cookie en la raíz de HAY (/hay/), no en /hay/php/ (evita login que “no hace nada”).

 */

function hay_session_bootstrap_config(): void

{

    if (defined('HAY_WEB_ROOT')) {

        return;

    }

    $local = dirname(__DIR__) . '/config.local.php';

    if (is_file($local)) {

        require $local;

    }

}



function hay_app_cookie_path(): string

{

    hay_session_bootstrap_config();



    if (defined('HAY_WEB_ROOT') && HAY_WEB_ROOT !== '') {

        $root = (string) HAY_WEB_ROOT;

        if ($root[0] !== '/') {

            $root = '/' . $root;

        }

        return rtrim($root, '/') . '/';

    }



    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

    $dir = str_replace('\\', '/', dirname($script));

    if ($dir === '/' || $dir === '.') {

        return '/';

    }

    if (substr($dir, -4) === '/php') {

        $dir = dirname($dir);

    }

    if (substr($dir, -6) === '/views') {

        $dir = dirname($dir);

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

    $dir = dirname(__DIR__) . '/storage/sessions';

    if (!is_dir($dir)) {

        @mkdir($dir, 0700, true);

    }

    if (is_dir($dir) && is_writable($dir)) {

        return $dir;

    }



    return null;

}



function hay_session_name(): string

{

    return 'HAYSESSID';

}



function hay_session_cookie_paths_to_clear(): array

{

    $paths = ['/hay/', '/hay/php/', '/hay/views/', '/'];

    $current = hay_app_cookie_path();

    if ($current !== '' && !in_array($current, $paths, true)) {

        $paths[] = $current;

    }



    return array_values(array_unique($paths));

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



function hay_session_preparar_nuevo_login(): void

{

    if (session_status() === PHP_SESSION_NONE) {

        hay_session_start();

    }



    $_SESSION = [];

    hay_session_purge_phpsessid_legacy();



    if (session_status() === PHP_SESSION_ACTIVE) {

        session_regenerate_id(true);

    }

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

