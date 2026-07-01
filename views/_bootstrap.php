<?php

/**
 * Inicialización común para vistas cargadas vía AJAX (cargarSeccion).
 * No ejecutar migraciones aquí (solo en dashboard.php al entrar al panel).
 */
require_once __DIR__ . '/../config.php';
$pdo = hay_pdo();

$hayVistaSeccion = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
if (!empty($_SESSION['user_id']) && function_exists('rbac_sincronizar_sesion_usuario')) {
    rbac_sincronizar_sesion_usuario($pdo);
}
if ($hayVistaSeccion !== '' && function_exists('rbac_guard_seccion')) {
    rbac_guard_seccion($hayVistaSeccion);
}
if ($hayVistaSeccion !== '' && function_exists('usuario_suspension_puede_acceder_seccion')
    && !usuario_suspension_puede_acceder_seccion($hayVistaSeccion)) {
    echo '<div class="alert" style="padding:20px; max-width:560px; margin:20px auto;">'
        . '<h3 style="margin:0 0 10px;">Acceso limitado</h3>'
        . '<p>Su cuenta está suspendida por adeudo. Regularice sus pagos y contacte a recepción.</p>'
        . '<button type="button" class="primary" onclick="cargarSeccion(\'alumno_cuenta_suspendida\')">Ver aviso</button>'
        . '</div>';
    return;
}
