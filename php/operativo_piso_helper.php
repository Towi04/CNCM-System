<?php

/**
 * Piso operativo recepción: entrega de documentos y atajos de cobranza.
 */

function operativo_piso_puede_ver(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    return (function_exists('documento_puede_entregar') && documento_puede_entregar())
        || (function_exists('operativo_busqueda_puede') && operativo_busqueda_puede());
}

/** @return list<array{clave:string,titulo:string,valor:string,enlace:string,query?:string,prioridad:string,icon:string}> */
function operativo_piso_atajos_cobranza(PDO $pdo, int $idPlantel): array
{
    $atajos = [];

    if (function_exists('rbac_cap') && rbac_cap('menu_punto_venta')) {
        $atajos[] = [
            'clave' => 'pos',
            'titulo' => 'Punto de venta',
            'valor' => 'Cobrar',
            'enlace' => 'punto_venta',
            'prioridad' => 'normal',
            'icon' => 'fa-cash-register',
        ];
    }

    if (function_exists('rbac_cap') && rbac_cap('menu_consulta_adeudo')) {
        $atajos[] = [
            'clave' => 'adeudo',
            'titulo' => 'Consulta de adeudo',
            'valor' => 'Buscar',
            'enlace' => 'consulta_adeudo',
            'prioridad' => 'normal',
            'icon' => 'fa-calculator',
        ];
    }

    if (function_exists('documento_contar_pendientes_plantel')) {
        $nConst = documento_contar_pendientes_plantel($pdo, $idPlantel);
        if ($nConst > 0 && function_exists('documento_puede_marcar_pagada') && documento_puede_marcar_pagada()) {
            $atajos[] = [
                'clave' => 'constancias_cobro',
                'titulo' => 'Constancias por cobrar',
                'valor' => (string) $nConst,
                'enlace' => 'constancia_recepcion',
                'prioridad' => 'alta',
                'icon' => 'fa-file-invoice-dollar',
            ];
        }
    }

    if (function_exists('cola_facturacion_contar') && cola_facturacion_puede_ver()) {
        $nFact = cola_facturacion_contar($pdo, $idPlantel);
        if ($nFact > 0) {
            $atajos[] = [
                'clave' => 'facturas',
                'titulo' => 'Cola de facturación',
                'valor' => (string) $nFact,
                'enlace' => 'cola_facturacion',
                'prioridad' => 'alta',
                'icon' => 'fa-file-invoice',
            ];
        }
    }

    if (function_exists('documento_contar_pendientes_entrega_plantel')) {
        $nEnt = documento_contar_pendientes_entrega_plantel($pdo, $idPlantel);
        if ($nEnt > 0) {
            $atajos[] = [
                'clave' => 'entrega',
                'titulo' => 'Documentos por entregar',
                'valor' => (string) $nEnt,
                'enlace' => 'piso_operativo',
                'query' => 'tab=entrega',
                'prioridad' => 'alta',
                'icon' => 'fa-hand-holding',
            ];
        }
    }

    if (function_exists('notificaciones_cartera_vencida')) {
        $nCar = count(notificaciones_cartera_vencida($pdo, $idPlantel));
        if ($nCar > 0 && function_exists('reporte_cartera_puede_ver') && reporte_cartera_puede_ver()) {
            $atajos[] = [
                'clave' => 'cartera',
                'titulo' => 'Cartera vencida',
                'valor' => (string) min($nCar, 99) . ($nCar > 99 ? '+' : ''),
                'enlace' => 'reporte_vencimientos',
                'prioridad' => 'alta',
                'icon' => 'fa-exclamation-circle',
            ];
        }
    }

    if (function_exists('reporte_financiero_puede_ver') && reporte_financiero_puede_ver()) {
        $corteHoy = function_exists('reporte_corte_caja_obtener')
            ? reporte_corte_caja_obtener($pdo, $idPlantel, date('Y-m-d'), 'B')
            : null;
        $atajos[] = [
            'clave' => 'corte',
            'titulo' => 'Corte de caja (hoy)',
            'valor' => $corteHoy ? 'Listo' : 'Pendiente',
            'enlace' => 'corte_caja',
            'prioridad' => $corteHoy ? 'normal' : 'media',
            'icon' => 'fa-coins',
        ];
    }

    if (function_exists('documento_puede_mostrador') && documento_puede_mostrador()) {
        $atajos[] = [
            'clave' => 'mostrador',
            'titulo' => 'Mostrador documentos',
            'valor' => 'Buscar',
            'enlace' => 'documento_mostrador',
            'prioridad' => 'normal',
            'icon' => 'fa-id-card',
        ];
    }

    return $atajos;
}

/** @return array{entrega_total:int, entrega_diplomas:int, entrega_constancias:int, atajos:list<array<string,string>>} */
function operativo_piso_resumen(PDO $pdo, int $idPlantel): array
{
    return [
        'entrega_total' => function_exists('documento_contar_pendientes_entrega_plantel')
            ? documento_contar_pendientes_entrega_plantel($pdo, $idPlantel)
            : 0,
        'entrega_diplomas' => function_exists('documento_contar_pendientes_entrega_plantel')
            ? documento_contar_pendientes_entrega_plantel($pdo, $idPlantel, 'diploma')
            : 0,
        'entrega_constancias' => function_exists('documento_contar_pendientes_entrega_plantel')
            ? documento_contar_pendientes_entrega_plantel($pdo, $idPlantel, 'constancia')
            : 0,
        'atajos' => operativo_piso_atajos_cobranza($pdo, $idPlantel),
    ];
}
