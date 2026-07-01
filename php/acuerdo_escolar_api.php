<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';



if (!isset($_SESSION['user_id'])) {

    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);

    exit;

}



$action = trim($_GET['action'] ?? $_POST['action'] ?? '');



if ($action === 'pendiente') {

    $idAlumno = alumno_portal_id_sesion();

    if ($idAlumno <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Cuenta sin alumno vinculado']);

        exit;

    }

    $pend = acuerdo_pendiente_para_alumno($pdo, $idAlumno);

    hay_json_response([

        'status' => 'ok',

        'pendiente' => $pend !== null,

        'acuerdo' => $pend,

    ]);

    exit;

}



if ($action === 'aceptar' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $idAlumno = alumno_portal_id_sesion();

    if ($idAlumno <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Cuenta sin alumno vinculado']);

        exit;

    }

    if (empty($_POST['acepto']) || (string) $_POST['acepto'] !== '1') {

        hay_json_response(['status' => 'error', 'message' => 'Debe marcar que acepta el acuerdo escolar']);

        exit;

    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $res = acuerdo_registrar_aceptacion($pdo, $idAlumno, (int) $_SESSION['user_id'], is_string($ip) ? $ip : null);

    $debePerfil = function_exists('alumno_debe_completar_perfil')

        && alumno_debe_completar_perfil($pdo, (int) $_SESSION['user_id']);

    hay_json_response([

        'status' => $res['ok'] ? 'ok' : 'error',

        'message' => $res['message'] ?? '',

        'debe_completar_perfil' => $debePerfil,

    ]);

    exit;

}



if (!acuerdo_escolar_puede_publicar()) {

    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);

    exit;

}



if ($action === 'listar') {

    hay_json_response([

        'status' => 'ok',

        'versiones' => acuerdo_escolar_listar($pdo),

        'activo' => acuerdo_version_activo_nuevos($pdo),

    ]);

    exit;

}



if ($action === 'publicar' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $label = trim((string) ($_POST['version_label'] ?? ''));

    $contenido = (string) ($_POST['contenido'] ?? '');

    $idPlantel = (int) ($_POST['id_plantel'] ?? 0);

    $res = acuerdo_publicar_nueva_version($pdo, $label, $contenido, $idPlantel > 0 ? $idPlantel : null);

    hay_json_response([

        'status' => $res['ok'] ? 'ok' : 'error',

        'message' => $res['message'] ?? '',

        'id_acuerdo_version' => $res['id_acuerdo_version'] ?? null,

        'version_label' => $res['version_label'] ?? '',

        'alumnos_marcados' => $res['alumnos_marcados'] ?? 0,

    ]);

    exit;

}



hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);


