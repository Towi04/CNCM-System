<?php
require __DIR__ . '/../config.php';



if (empty($_SESSION['user_id'])) {

    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada. Vuelva a iniciar sesión.'], 401);

    exit;

}



if (!preregistro_puede_cobrar()) {

    hay_json_response(['status' => 'error', 'message' => 'Solo recepción o supervisión pueden registrar apartados'], 403);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    hay_json_response(['status' => 'error', 'message' => 'Método inválido'], 405);

    exit;

}



$id = (int) ($_POST['id_preregistro'] ?? 0);

$monto = catalog_money($_POST['monto_apartado'] ?? 0);

$formaPago = trim((string) ($_POST['forma_pago'] ?? 'Efectivo'));

$idPlantel = plantel_id_activo();



if ($id <= 0 || $monto <= 0) {

    hay_json_response(['status' => 'error', 'message' => 'Indica un monto de apartado válido']);

    exit;

}



try {

    $stmt = $pdo->prepare(

        'SELECT * FROM preregistros WHERE id_preregistro = ? AND id_plantel = ? LIMIT 1'

    );

    $stmt->execute([$id, $idPlantel]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {

        hay_json_response(['status' => 'error', 'message' => 'Pre-registro no encontrado']);

        exit;

    }

    if (in_array($row['estado'], ['perdido', 'inscrito'], true)) {

        hay_json_response(['status' => 'error', 'message' => 'No se puede registrar apartado en este estado']);

        exit;

    }



    $folio = preregistro_generar_folio_apartado($pdo, $idPlantel);

    $fecha = date('Y-m-d H:i:s');



    $pdo->prepare(
        'UPDATE preregistros SET tiene_apartado = 1, monto_apartado = ?, folio_apartado = ?, fecha_apartado = ?,
         forma_pago_apartado = ?
         WHERE id_preregistro = ? AND id_plantel = ?'
    )->execute([$monto, $folio, $fecha, $formaPago !== '' ? $formaPago : 'Efectivo', $id, $idPlantel]);

    if (!empty($row['id_alumno_vinculado'])) {
        preregistro_aplicar_apartado_a_alumno($pdo, $id);
    }

    $ticket = preregistro_datos_ticket_apartado($pdo, $id, $idPlantel);

    if ($ticket) {

        $ticket['forma_pago'] = $formaPago !== '' ? $formaPago : 'Efectivo';

        $ticket['recibio'] = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''));

    }



    hay_json_response([

        'status' => 'ok',

        'message' => 'Apartado registrado: ' . catalog_format_mxn($monto),

        'seccion' => 'pre_registro_alumnos',

        'folio' => $folio,

        'ticket' => $ticket,

        'ticket_url' => 'views/ticket_apartado.php?id=' . $id . '&folio=' . urlencode($folio),

    ]);

} catch (PDOException $e) {

    hay_json_response(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);

}

