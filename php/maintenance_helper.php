<?php

/**
 * Modo mantenimiento controlado (flag en storage/maintenance.flag).
 * Los supervisores pueden entrar; el resto ve la pantalla de actualización.
 */

function hay_maintenance_flag_path(): string
{
    $root = defined('HAY_ROOT') ? HAY_ROOT : dirname(__DIR__);

    return $root . '/storage/maintenance.flag';
}

function hay_maintenance_activo(): bool
{
    return is_file(hay_maintenance_flag_path());
}

function hay_maintenance_activar(string $motivo = ''): bool
{
    $path = hay_maintenance_flag_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $payload = [
        'desde' => date('c'),
        'motivo' => trim($motivo),
        'por' => (int) ($_SESSION['user_id'] ?? 0),
    ];

    return @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n") !== false;
}

function hay_maintenance_desactivar(): bool
{
    $path = hay_maintenance_flag_path();
    if (!is_file($path)) {
        return true;
    }

    return @unlink($path);
}

/** @return array{desde?:string,motivo?:string,por?:int}|null */
function hay_maintenance_meta(): ?array
{
    $path = hay_maintenance_flag_path();
    if (!is_file($path)) {
        return null;
    }
    $raw = trim((string) @file_get_contents($path));
    if ($raw === '') {
        return [];
    }
    $json = json_decode($raw, true);

    return is_array($json) ? $json : ['motivo' => $raw];
}

function hay_maintenance_script_permitido(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = strtolower(basename($script));
    $allow = [
        'index.php',
        'login_process.php',
        'logout.php',
        'olvido_password.php',
        'solicitar_recuperacion.php',
        'restablecer_password.php',
    ];

    return in_array($base, $allow, true);
}

function hay_maintenance_es_supervisor_sesion(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        if (is_file(__DIR__ . '/session_helper.php')) {
            require_once __DIR__ . '/session_helper.php';
            if (function_exists('hay_session_start')) {
                hay_session_start();
            }
        }
    }
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    if (function_exists('rbac_es_supervisor_cuenta')) {
        return rbac_es_supervisor_cuenta();
    }
    $rol = strtolower(trim((string) ($_SESSION['rol_real'] ?? $_SESSION['rol'] ?? '')));

    return $rol === 'supervisor';
}

function hay_maintenance_puede_gestionar(): bool
{
    return hay_maintenance_es_supervisor_sesion();
}
