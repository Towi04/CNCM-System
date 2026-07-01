<?php

/**
 * Guarda comisiones del expediente (POST tradicional, respuesta HTML).
 */
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_id']) || !certificacion_puede_editar_comisiones()) {
    http_response_code(403);
    echo '<p class="catalog-alert catalog-alert--error">Sin permiso.</p>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<p class="catalog-alert catalog-alert--error">Método no permitido.</p>';
    exit;
}

$idSolicitud = (int) ($_POST['id_solicitud'] ?? 0);
$idPlantel = plantel_scope_id($pdo);

$res = comision_cert_actualizar_solicitud($pdo, $idSolicitud, $idPlantel, [
    'precio_cobrado' => $_POST['precio_cobrado'] ?? 0,
    'comision_asesor' => $_POST['comision_asesor'] ?? 0,
    'comision_gerente' => $_POST['comision_gerente'] ?? 0,
    'motivo' => $_POST['motivo'] ?? 'Ajuste en expediente',
]);

$fragmentOnly = !empty($_POST['fragment']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'text/html'));

if ($fragmentOnly) {
    header('Content-Type: text/html; charset=utf-8');
    if (!$res['ok']) {
        echo '<p class="catalog-alert catalog-alert--error">' . htmlspecialchars($res['message'], ENT_QUOTES, 'UTF-8') . '</p>';
        exit;
    }
    $_GET['id_solicitud'] = (string) $idSolicitud;
    require __DIR__ . '/certificacion_expediente_comisiones.php';
    exit;
}

header('Location: ' . hay_asset_url('dashboard.php') . '?s=certificaciones&cert_msg=' . urlencode($res['message']));
exit;
