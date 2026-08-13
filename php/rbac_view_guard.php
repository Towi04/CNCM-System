<?php



/**

 * Guardas RBAC al inicio de vistas sensibles.

 */

function rbac_require_cap(string $cap, string $mensaje = 'No tiene permiso para ver esta sección.'): void

{

    if (!function_exists('rbac_cap') || !rbac_cap($cap)) {

        echo '<div class="alert alert-error" role="alert">' . htmlspecialchars($mensaje) . '</div>';

        exit;

    }

}



function rbac_require_any_cap(array $caps, string $mensaje = 'No tiene permiso para ver esta sección.'): void

{

    foreach ($caps as $c) {

        if (function_exists('rbac_cap') && rbac_cap($c)) {

            return;

        }

    }

    echo '<div class="alert alert-error" role="alert">' . htmlspecialchars($mensaje) . '</div>';

    exit;

}



function rbac_require_login(): void

{

    if (empty($_SESSION['user_id'])) {

        header('Location: index.php');

        exit;

    }

}



/** Mapa sección → capacidad mínima (usado por vistas vía rbac_guard_seccion). */

function rbac_seccion_caps(): array

{

    return [

        'punto_venta' => 'menu_punto_venta',

        'venta_productos' => 'menu_venta_productos',

        'consulta_adeudo' => 'menu_consulta_adeudo',

        'admin_especialidades' => 'admin_catalogo',

        'admin_productos' => 'admin_catalogo',

        'admin_planteles' => 'admin_planteles',

        'admin_roles' => 'admin_roles',

        'reporte_ventas' => 'menu_reportes',

        'reporte_ventas_productos' => 'menu_reportes',

        'corte_caja' => 'menu_reportes',

        'pagos_transferencias' => 'menu_transferencias_ver',

        'reporte_bajas' => 'menu_reporte_bajas',

        'manuales_stock' => 'menu_manuales_stock',

        'manuales_envios' => 'menu_manuales_envios',

        'cronologia_grupos' => 'menu_cronologia',

        'reporte_riesgo_academico' => 'menu_riesgo_reporte',

        'ventas_comisiones_admin' => 'menu_comisiones_admin',

        'ventas_comisiones_consulta' => 'menu_comisiones_consulta',

        'inscripcion_autorizaciones' => 'inscripcion_autorizar_edad',

        'curso_personalizado_admin' => 'curso_personalizado_gestionar',

        'esp_fases' => 'menu_especialidades',

        'planeacion_prompts' => 'menu_especialidades',

        'pre_registro_alumnos' => 'menu_preregistro',

        'supervisor_acuerdo_escolar' => 'menu_supervisor_acuerdo',

    ];

}



function rbac_guard_seccion(string $seccion): void

{

    rbac_require_login();

    $map = rbac_seccion_caps();

    if (!isset($map[$seccion])) {

        return;

    }

    $cap = $map[$seccion];

    if ($cap === 'inscripcion_autorizar_edad') {

        if (function_exists('inscripcion_protocolo_puede_autorizar') && inscripcion_protocolo_puede_autorizar()) {

            return;

        }

        rbac_require_cap($cap);

        return;

    }

    if ($seccion === 'pre_registro_alumnos' && function_exists('preregistro_puede_acceder') && preregistro_puede_acceder()) {

        return;

    }

    if ($seccion === 'admin_roles' && function_exists('rbac_puede_centro_permisos') && rbac_puede_centro_permisos()) {

        return;

    }

    if ($seccion === 'pagos_transferencias') {
        if (
            (function_exists('rbac_cap') && (rbac_cap('menu_transferencias_ver') || rbac_cap('menu_transferencias_confirmar')))
            || (function_exists('pago_transferencia_puede_ver') && pago_transferencia_puede_ver())
        ) {
            return;
        }
    }

    if ($seccion === 'ventas_comisiones_admin' && function_exists('rbac_cap') && rbac_cap('menu_comisiones_nomina_print')) {
        return;
    }

    if ($seccion === 'manuales_envios' && function_exists('manuales_stock_puede_envios') && manuales_stock_puede_envios()) {
        return;
    }

    if ($seccion === 'cronologia_grupos' && function_exists('cronologia_puede_ver') && cronologia_puede_ver()) {
        return;
    }

    rbac_require_cap($cap);

}


