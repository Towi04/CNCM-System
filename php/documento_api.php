<?php

declare(strict_types=1);



require __DIR__ . '/../config.php';



header('Content-Type: application/json; charset=UTF-8');



if (!isset($_SESSION['user_id'])) {

    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);

    exit;

}



$idPlantel = plantel_scope_id($pdo);

$accion = trim($_GET['accion'] ?? $_POST['accion'] ?? '');

$idUser = (int) $_SESSION['user_id'];



if ($accion === 'catalogo') {

    hay_json_response([

        'status' => 'ok',

        'campos' => documento_campos_disponibles('constancia'),

        'campos_diploma' => documento_campos_disponibles('diploma'),

        'plantillas_constancia' => documento_plantillas_listar($pdo, 'constancia', $idPlantel),

        'plantillas_diploma' => documento_plantillas_listar($pdo, 'diploma', $idPlantel),

        'producto' => documento_producto_constancia($pdo),

    ]);

    exit;

}



if ($accion === 'mis_solicitudes') {

    $idAlumno = alumno_portal_id_sesion();

    if ($idAlumno <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Sin alumno vinculado']);

        exit;

    }

    hay_json_response(['status' => 'ok', 'documentos' => documento_listar_alumno($pdo, $idAlumno)]);

    exit;

}



if ($accion === 'solicitar') {

    $idAlumno = alumno_portal_id_sesion();

    if ($idAlumno <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Sin alumno vinculado']);

        exit;

    }

    $opciones = json_decode($_POST['opciones'] ?? '[]', true) ?: [];

    $extra = json_decode($_POST['extra'] ?? '{}', true) ?: [];

    $idPlantilla = (int) ($_POST['id_plantilla'] ?? 0);

    if ($idPlantilla <= 0) {

        $pls = documento_plantillas_listar($pdo, 'constancia', $idPlantel);

        $idPlantilla = (int) ($pls[0]['id_plantilla'] ?? 0);

    }

    $res = documento_solicitar_constancia($pdo, $idAlumno, $idPlantel, $idPlantilla, $opciones, $extra);

    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);

    exit;

}



if ($accion === 'pendientes') {

    if (!documento_puede_marcar_pagada()) {

        hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);

        exit;

    }

    hay_json_response([

        'status' => 'ok',

        'documentos' => documento_listar_pendientes($pdo, $idPlantel, trim($_GET['estado'] ?? '') ?: null),

    ]);

    exit;

}



if ($accion === 'marcar_pagada') {

    $idDoc = (int) ($_POST['id_documento'] ?? 0);

    $idPago = (int) ($_POST['id_pago'] ?? 0) ?: null;

    $res = documento_marcar_pagada($pdo, $idDoc, $idPlantel, $idUser, $idPago);

    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);

    exit;

}



if ($accion === 'plantilla_guardar') {

    $data = $_POST;

    if (!empty($_FILES['fondo']['tmp_name'])) {

        $up = documento_subir_imagen_plantilla($_FILES['fondo'], 'fondo');

        if ($up['ok']) {

            $data['fondo_path'] = $up['path'];

        }

    }

    if (!empty($_FILES['firma']['tmp_name'])) {

        $up = documento_subir_imagen_plantilla($_FILES['firma'], 'firma');

        if ($up['ok']) {

            $data['firma_path'] = $up['path'];

        }

    }

    $res = documento_plantilla_guardar($pdo, $data);

    if ($res['ok'] && !empty($data['fondo_path']) && !empty($res['id_plantilla'])) {

        $pdo->prepare('UPDATE documento_plantilla SET fondo_path=? WHERE id_plantilla=?')

            ->execute([$data['fondo_path'], $res['id_plantilla']]);

    }

    if ($res['ok'] && !empty($data['firma_path']) && !empty($res['id_plantilla'])) {

        $pdo->prepare('UPDATE documento_plantilla SET firma_path=? WHERE id_plantilla=?')

            ->execute([$data['firma_path'], $res['id_plantilla']]);

    }

    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);

    exit;

}



if ($accion === 'plantilla_obtener') {

    $id = (int) ($_GET['id_plantilla'] ?? 0);

    $pl = documento_plantilla_obtener($pdo, $id);

    if (!$pl) {

        hay_json_response(['status' => 'error', 'message' => 'No encontrada']);

        exit;

    }

    hay_json_response(['status' => 'ok', 'plantilla' => $pl]);

    exit;

}



if ($accion === 'diplomas_generar') {

    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);

    $res = documento_generar_diplomas_grupo($pdo, $idGrupo, $idPlantel, $idUser);

    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);

    exit;

}



if ($accion === 'diplomas_grupo') {

    $idGrupo = (int) ($_GET['id_grupo'] ?? 0);

    $st = $pdo->prepare(

        'SELECT d.*, CONCAT(a.nombres, \' \', a.apellido_paterno) AS alumno_nombre, a.numero_control

         FROM alumno_documento d

         INNER JOIN alumnos a ON a.id_alumno = d.id_alumno

         WHERE d.id_grupo = ? AND d.tipo = \'diploma\' ORDER BY a.nombres'

    );

    $st->execute([$idGrupo]);

    hay_json_response(['status' => 'ok', 'diplomas' => $st->fetchAll(PDO::FETCH_ASSOC) ?: []]);

    exit;

}



if ($accion === 'mostrador_buscar') {

    $q = trim($_GET['q'] ?? '');

    $res = documento_mostrador_buscar($pdo, $q, $idPlantel);

    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);

    exit;

}



hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);

