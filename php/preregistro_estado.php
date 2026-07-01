<?php
require __DIR__ . '/../config.php';



if (!preregistro_puede_acceder()) {

    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);

    exit;

}



$id = (int) ($_POST['id_preregistro'] ?? 0);

$estado = trim((string) ($_POST['estado'] ?? ''));

$idPlantel = plantel_id_activo();



$estadosValidos = ['activo', 'pendiente', 'perdido', 'inscrito'];

if ($id <= 0 || !in_array($estado, $estadosValidos, true)) {

    hay_json_response(['status' => 'error', 'message' => 'Datos inválidos']);

    exit;

}



$categoria = trim((string) ($_POST['categoria_perdido'] ?? ''));

$motivo = trim((string) ($_POST['motivo_perdido'] ?? ''));

$categoriaPend = trim((string) ($_POST['categoria_pendiente'] ?? ''));

$motivoPend = trim((string) ($_POST['motivo_pendiente'] ?? ''));

$fechaRecordatorio = trim((string) ($_POST['fecha_recordatorio'] ?? ''));



if ($estado === 'perdido') {

    $cats = array_keys(preregistro_labels()['categoria_perdido']);

    if (!in_array($categoria, $cats, true)) {

        hay_json_response(['status' => 'error', 'message' => 'Selecciona una categoría de pérdida']);

        exit;

    }

    if ($motivo === '') {

        hay_json_response(['status' => 'error', 'message' => 'Indica el motivo de la pérdida']);

        exit;

    }

}



if ($estado === 'pendiente') {

    $catsP = array_keys(preregistro_labels()['categoria_pendiente']);

    if (!in_array($categoriaPend, $catsP, true)) {

        hay_json_response(['status' => 'error', 'message' => 'Selecciona por qué queda pendiente']);

        exit;

    }

    if ($motivoPend === '') {

        hay_json_response(['status' => 'error', 'message' => 'Indica las observaciones del seguimiento']);

        exit;

    }

    if ($fechaRecordatorio !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRecordatorio)) {

        hay_json_response(['status' => 'error', 'message' => 'Fecha de recordatorio inválida']);

        exit;

    }

}



if ($estado === 'inscrito') {

    hay_json_response([

        'status' => 'error',

        'message' => 'Use el asistente de inscripción para elegir grupo y registrar el pago',

        'requiere_wizard' => true,

    ]);

    exit;

}



try {

    $stmt = $pdo->prepare('SELECT * FROM preregistros WHERE id_preregistro = ? AND id_plantel = ? LIMIT 1');

    $stmt->execute([$id, $idPlantel]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {

        hay_json_response(['status' => 'error', 'message' => 'Pre-registro no encontrado']);

        exit;

    }



    // Pendiente dispara notificaciones (ensure_schema DDL) — no usar transacción ahí.
    if ($estado === 'pendiente') {
        preregistro_registrar_pendiente(
            $pdo,
            $id,
            $idPlantel,
            $categoriaPend,
            $motivoPend,
            $fechaRecordatorio !== '' ? $fechaRecordatorio : null
        );
    } else {
        $pdo->beginTransaction();
        $pdo->prepare(
            'UPDATE preregistros SET estado = ?, categoria_perdido = ?, motivo_perdido = ?, fecha_estado = NOW(),
             categoria_pendiente = NULL, motivo_pendiente = NULL, fecha_recordatorio = NULL
             WHERE id_preregistro = ?'
        )->execute([
            $estado,
            $estado === 'perdido' ? $categoria : null,
            $estado === 'perdido' ? $motivo : null,
            $id,
        ]);

        if ($estado === 'activo') {
            $pdo->prepare(
                'UPDATE preregistro_alertas SET resuelta = 1
                 WHERE id_preregistro = ? AND tipo = \'general\' AND mensaje LIKE \'Seguimiento pendiente:%\''
            )->execute([$id]);
        }

        if ($estado === 'perdido') {
            $pdo->prepare('UPDATE preregistro_alertas SET resuelta = 1 WHERE id_preregistro = ?')->execute([$id]);
        }

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    }



    $msgs = [

        'pendiente' => 'Prospecto movido a pendientes' . ($fechaRecordatorio ? ' · recordatorio ' . date('d/m/Y', strtotime($fechaRecordatorio)) : ''),

        'perdido' => 'Prospecto marcado como perdido',

        'activo' => 'Prospecto reactivado',

    ];



    hay_json_response([

        'status' => 'ok',

        'message' => $msgs[$estado] ?? 'Estado actualizado',

        'seccion' => 'pre_registro_alumnos',

    ]);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    hay_json_response(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);

}

