<?php
require __DIR__ . '/../config.php';



if (!alumno_puede_ver()) {

    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);

    exit;

}



$idAlumno = (int) ($_POST['id_alumno'] ?? 0);

$idGrupo = (int) ($_POST['id_grupo'] ?? 0);

$idPlantel = plantel_id_activo();



if ($idAlumno <= 0 || $idGrupo <= 0) {

    hay_json_response(['status' => 'error', 'message' => 'Alumno y grupo son obligatorios']);

    exit;

}



if (!plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel)) {

    hay_json_response(['status' => 'error', 'message' => 'El grupo no pertenece a este plantel']);

    exit;

}



$chk = $pdo->prepare('SELECT id_alumno FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1');

$chk->execute([$idAlumno, $idPlantel]);

if (!$chk->fetchColumn()) {

    hay_json_response(['status' => 'error', 'message' => 'Alumno no encontrado']);

    exit;

}



$g = $pdo->prepare('SELECT id_especialidad FROM grupos WHERE id_grupo = ? LIMIT 1');

$g->execute([$idGrupo]);

$idEsp = (int) $g->fetchColumn();

if ($idEsp <= 0) {

    hay_json_response(['status' => 'error', 'message' => 'Grupo sin especialidad']);

    exit;

}

$esGrupoPersonalizado = inscripcion_grupo_es_personalizado($pdo, $idGrupo);

if (!$esGrupoPersonalizado && !inscripcion_puede_asignar_grupo($pdo, $idAlumno, $idEsp)) {

    hay_json_response([

        'status' => 'error',

        'message' => 'Debe cubrir la inscripción antes de asignar al grupo',

        'saldo' => inscripcion_saldo_pendiente($pdo, $idAlumno, $idEsp),

        'requiere_wizard' => true,

    ]);

    exit;

}



    $res = inscripcion_flow_confirmar_grupo($pdo, $idAlumno, $idGrupo);

    if ($res['ok'] && function_exists('tutor_asignar_grupo')) {
        tutor_asignar_grupo($pdo, $idGrupo);
    }

    hay_json_response([

    'status' => $res['ok'] ? 'ok' : 'error',

    'message' => $res['message'],

    'seccion' => 'alumno_detalle',

    'params' => 'id=' . $idAlumno,

]);

