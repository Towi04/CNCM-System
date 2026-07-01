<?php
/**
 * DEPRECADO
 *
 * Este archivo fue el primer borrador de conexión a Moodle.
 * La integración oficial vive en `php/moodle_helper.php` y se configura en `config.local.php`.
 *
 * Mantengo este archivo para no romper includes viejos, pero sin exponer tokens en repo.
 */

require_once __DIR__ . '/../config.php';

/** @return array<string,mixed> */
function conectarAPIMoodle(string $functionName, $params = []): array
{
    if (!function_exists('moodle_api_call')) {
        return ['error' => 'moodle_helper.php no disponible'];
    }
    $res = moodle_api_call((string) $functionName, is_array($params) ? $params : []);
    if (!empty($res['ok'])) {
        return (array) ($res['data'] ?? []);
    }
    return ['error' => (string) ($res['message'] ?? 'Error')];
}