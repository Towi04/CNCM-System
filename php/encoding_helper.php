<?php

/**
 * Asegura UTF-8 en respuestas HTML/JSON y conexión MySQL (es-MX).
 */
function hay_utf8_init(?PDO $pdo = null): void
{
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    if (function_exists('mb_http_output')) {
        mb_http_output('UTF-8');
    }
    ini_set('default_charset', 'UTF-8');

    if ($pdo instanceof PDO) {
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
}

function hay_html_utf8_header(): void
{
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
}

function hay_json_response(array $data, int $code = 200): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

/** Raíz web de la aplicación (p. ej. `/` o `/hay/`), aunque el script esté en `/views/`. */
function hay_web_root(): string
{
    if (defined('HAY_WEB_ROOT') && (string) HAY_WEB_ROOT !== '') {
        $root = str_replace('\\', '/', (string) HAY_WEB_ROOT);
        if ($root === '/' || $root === '') {
            return '/';
        }

        return rtrim($root, '/') . '/';
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/dashboard.php');
    $dir = dirname($script);
    if (preg_match('#/views$#', $dir) || preg_match('#/php$#', $dir)) {
        $dir = dirname($dir);
    }

    if ($dir === '/' || $dir === '.' || $dir === '') {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if (preg_match('#^(/.+?)/(views|php)/#', $uri, $m)) {
            return $m[1] . '/';
        }

        return '/';
    }

    return rtrim($dir, '/') . '/';
}

/** URL absoluta desde la raíz del sitio para CSS/JS (evita 404 al cargar vistas por AJAX). */
function hay_asset_url(string $relative): string
{
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $root = hay_web_root();
    if ($root === '/') {
        return '/' . $relative;
    }

    return $root . $relative;
}
