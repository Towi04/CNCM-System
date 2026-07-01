<?php
/**
 * Nombre visible del sistema (portal integral CNCM).
 * Los identificadores internos HAY_* en código no cambian.
 */
if (!defined('APP_DISPLAY_NAME')) {
    define('APP_DISPLAY_NAME', 'Portal CNCM');
}
if (!defined('APP_INSTITUTION')) {
    define('APP_INSTITUTION', 'Grupo Educativo CNCM');
}

if (!function_exists('app_display_name')) {
    function app_display_name(): string
    {
        return defined('APP_DISPLAY_NAME') ? (string) APP_DISPLAY_NAME : 'Portal CNCM';
    }
}

if (!function_exists('app_page_title')) {
    function app_page_title(string $section = ''): string
    {
        $base = app_display_name();
        if ($section === '') {
            return $base;
        }

        return $section . ' — ' . $base;
    }
}
