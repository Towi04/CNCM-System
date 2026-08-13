<?php
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$cuentaBanco = strtolower(trim((string) ($_POST['cuenta_banco'] ?? '')));
$comprobantePath = null;
if (!empty($_FILES['comprobante']['tmp_name']) && function_exists('pago_transferencia_guardar_comprobante')) {
    $up = pago_transferencia_guardar_comprobante(
        $_FILES['comprobante'],
        (int) ($_POST['id_alumno'] ?? 0)
    );
    if (!$up['ok']) {
        hay_json_response(['status' => 'error', 'message' => $up['message'] ?? 'Error al subir comprobante']);
        exit;
    }
    $comprobantePath = $up['path'] ?? null;
}

$res = pago_registrar($pdo, [
    'id_alumno' => (int) ($_POST['id_alumno'] ?? 0),
    'id_especialidad' => (int) ($_POST['id_especialidad'] ?? 0) ?: null,
    'id_alumno_especialidad' => (int) ($_POST['id_alumno_especialidad'] ?? 0) ?: null,
    'tipo' => trim($_POST['tipo'] ?? 'abono'),
    'monto' => $_POST['monto'] ?? 0,
    'folio' => trim($_POST['folio'] ?? ''),
    'forma_pago_efectivo' => trim($_POST['forma_pago_efectivo'] ?? 'Efectivo'),
    'medio_pago' => trim($_POST['medio_pago'] ?? ''),
    'cuenta_banco' => $cuentaBanco,
    'comprobante_path' => $comprobantePath,
    'concepto' => trim($_POST['concepto'] ?? ''),
    'cubrio' => trim($_POST['cubrio'] ?? ''),
    'periodo_ref' => trim($_POST['periodo_ref'] ?? '') ?: null,
    'creado_en' => trim($_POST['fecha_pago'] ?? '') ?: date('Y-m-d H:i:s'),
]);

$desdeChecada = trim($_POST['origen'] ?? '') === 'checada';
$payload = [
    'status' => $res['ok'] ? 'ok' : 'error',
    'message' => $res['message'],
];

if ($res['ok'] && !empty($res['id_pago'])) {
    $payload['id_pago'] = (int) $res['id_pago'];
    $payload['ticket_url'] = $res['ticket_url'] ?? hay_asset_url(
        'views/ticket_pago.php?id_pago=' . (int) $res['id_pago'] . '&print=1'
    );
    $payload['transfer_pendiente'] = !empty($res['transfer_pendiente']);
    $payload['folio'] = $res['folio'] ?? null;
}

if ($desdeChecada) {
    // ticket_url already set above on success
} else {
    $payload['seccion'] = 'consulta_adeudo';
    $payload['params'] = 'control=' . urlencode($_POST['numero_control'] ?? $_POST['id_alumno'] ?? '');
}

hay_json_response($payload);
