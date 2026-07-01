<?php

/**
 * Fragmento HTML: comisiones en expediente (sin JSON API).
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id']) || !certificacion_puede_acceder()) {
    echo '<p class="catalog-alert catalog-alert--error">Sin permiso.</p>';
    exit;
}

$idSolicitud = (int) ($_GET['id_solicitud'] ?? 0);
$idPlantel = plantel_scope_id($pdo);
$sol = $idSolicitud > 0 ? certificacion_obtener_solicitud($pdo, $idSolicitud, $idPlantel) : null;

if (!$sol) {
    echo '<p class="catalog-alert catalog-alert--error">Solicitud no encontrada.</p>';
    exit;
}

$puedeEditar = certificacion_puede_editar_comisiones();
$historial = comision_cert_historial_solicitud($pdo, $idSolicitud);
$precio = catalog_money($sol['precio_cobrado'] ?? $sol['precio'] ?? 0);
$comA = catalog_money($sol['comision_asesor'] ?? 0);
$comG = catalog_money($sol['comision_gerente'] ?? 0);
$idPago = (int) ($sol['id_pago'] ?? 0);
$saveUrl = hay_asset_url('php/certificacion_comision_save.php');
$partialUrl = hay_asset_url('php/certificacion_expediente_comisiones.php');

include __DIR__ . '/../views/partials/cert_expediente_comisiones.php';
