<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if (!asistencia_puede_tomar() && !asistencia_puede_eliminar_registro()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$accion = trim($_POST['accion'] ?? $_GET['accion'] ?? 'listar');

if ($accion === 'listar') {
    $fecha = trim($_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d'));
    $vista = trim($_GET['vista'] ?? $_POST['vista'] ?? 'checados');
    if ($vista === 'faltantes') {
        alumno_bajas_automaticas_semana_actual($pdo, $idPlantel);
    }
    $q = trim($_GET['q'] ?? $_POST['q'] ?? '');
    $opts = [
        'vista' => trim($_GET['vista'] ?? $_POST['vista'] ?? 'checados'),
        'tipo' => trim($_GET['tipo'] ?? $_POST['tipo'] ?? 'ambos'),
        'hora_desde' => trim($_GET['hora_desde'] ?? $_POST['hora_desde'] ?? ''),
        'hora_hasta' => trim($_GET['hora_hasta'] ?? $_POST['hora_hasta'] ?? ''),
        'id_grupo' => (int) ($_GET['id_grupo'] ?? $_POST['id_grupo'] ?? 0),
        'todos_grupos' => !empty($_GET['todos_grupos'] ?? $_POST['todos_grupos'] ?? ''),
    ];
    $data = asistencia_listar_registros_dia($pdo, $idPlantel, $fecha, $q, $opts);
    hay_json_response(['status' => 'ok'] + $data);
    exit;
}

if ($accion === 'eliminar') {
    if (!asistencia_puede_eliminar_registro()) {
        hay_json_response(['status' => 'error', 'message' => 'Solo dirección puede eliminar registros']);
        exit;
    }
    $tipo = trim($_POST['tipo'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $res = asistencia_eliminar_registro($pdo, $tipo, $id, $idPlantel, (int) $_SESSION['user_id']);
    hay_json_response([
        'status' => ($res['ok'] ?? false) ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
    ]);
    exit;
}

if ($accion === 'buscar_alumno') {
    if (!asistencia_puede_tomar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $q = trim($_GET['q'] ?? $_POST['q'] ?? '');
    if (mb_strlen($q) < 2) {
        hay_json_response(['status' => 'ok', 'alumnos' => []]);
        exit;
    }
    $alumnos = asistencia_buscar_alumnos($pdo, $q, $idPlantel, 12);
    hay_json_response(['status' => 'ok', 'alumnos' => $alumnos]);
    exit;
}

if ($accion === 'guardar_nota_falta') {
    if (!asistencia_puede_tomar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);
    $estado = trim($_POST['estado_contacto'] ?? 'pendiente');
    $obs = trim($_POST['observacion'] ?? '');
    $res = asistencia_guardar_nota_falta(
        $pdo,
        $idAlumno,
        $idGrupo,
        $idPlantel,
        $fecha,
        $estado,
        $obs,
        (int) $_SESSION['user_id']
    );
    hay_json_response([
        'status' => ($res['ok'] ?? false) ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
        'estado_contacto' => $res['estado_contacto'] ?? '',
        'observacion' => $res['observacion'] ?? '',
    ]);
    exit;
}

if ($accion === 'registrar_recepcion') {
    if (!asistencia_puede_tomar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
    $idGrupo = (int) ($_POST['id_grupo'] ?? 0) ?: null;
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    $busqueda = trim($_POST['q'] ?? $_POST['numero_control'] ?? '');

    if ($idAlumno > 0) {
        $res = asistencia_registrar_recepcion_alumno($pdo, $idAlumno, $idPlantel, $fecha, (int) $_SESSION['user_id'], $idGrupo);
    } else {
        $res = asistencia_registrar_recepcion_por_busqueda($pdo, $busqueda, $idPlantel, $fecha, (int) $_SESSION['user_id'], $idGrupo);
    }
    $status = ($res['ok'] ?? false) ? 'ok' : 'error';
    if (($res['code'] ?? '') === 'multiples') {
        $status = 'multiples';
    }
    hay_json_response([
        'status' => $status,
        'code' => $res['code'] ?? null,
        'message' => $res['message'] ?? '',
        'duplicado' => $res['duplicado'] ?? false,
        'alumnos' => $res['alumnos'] ?? [],
        'alumno' => [
            'id_alumno' => $res['id_alumno'] ?? null,
            'nombre' => $res['nombre'] ?? '',
            'numero_control' => $res['numero_control'] ?? '',
            'grupo' => $res['grupo'] ?? '',
            'aula' => $res['aula'] ?? '',
        ],
    ]);
    exit;
}

if ($accion === 'registrar_baja') {
    if (!asistencia_puede_tomar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);
    $tipo = trim($_POST['tipo_baja'] ?? 'temporal');
    $motivo = trim($_POST['motivo'] ?? '');
    $res = alumno_registrar_baja_grupo($pdo, $idAlumno, $idGrupo, $idPlantel, $tipo, $motivo, (int) $_SESSION['user_id']);
    hay_json_response(['status' => ($res['ok'] ?? false) ? 'ok' : 'error', 'message' => $res['message'] ?? '']);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
