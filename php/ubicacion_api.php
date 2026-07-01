<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'solicitar') {
    if (!ubicacion_puede_solicitar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
        exit;
    }
    $res = ubicacion_crear_solicitud(
        $pdo,
        (int) ($_POST['id_alumno'] ?? 0),
        (int) ($_POST['id_especialidad'] ?? 0),
        (int) $_SESSION['user_id'],
        trim($_POST['observaciones'] ?? '') ?: null
    );
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'listar') {
    if (!ubicacion_puede_evaluar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
        exit;
    }
    $estado = trim($_GET['estado'] ?? $_POST['estado'] ?? '');
    hay_json_response([
        'status' => 'ok',
        'items' => ubicacion_listar($pdo, $estado !== '' ? $estado : null),
        'estados' => ubicacion_estados_etiquetas(),
    ]);
    exit;
}

if ($action === 'detalle') {
    if (!ubicacion_puede_evaluar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
        exit;
    }
    $id = (int) ($_GET['id'] ?? 0);
    $st = $pdo->prepare(
        'SELECT u.*, e.nombre AS esp_nombre,
                TRIM(CONCAT(COALESCE(a.nombres, a.nombre, \'\'), \' \', COALESCE(a.apellido_paterno, a.apellido, \'\'))) AS alumno_nombre,
                a.numero_control
         FROM alumno_ubicacion u
         INNER JOIN alumnos a ON a.id_alumno = u.id_alumno
         INNER JOIN especialidades e ON e.id_especialidad = u.id_especialidad
         WHERE u.id_ubicacion = ? AND u.id_plantel = ?'
    );
    $st->execute([$id, plantel_id_activo()]);
    $ub = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ub) {
        hay_json_response(['status' => 'error', 'message' => 'No encontrado']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'ubicacion' => $ub,
        'grupos_autorizados' => ubicacion_grupos_autorizados_detalle($pdo, $id),
        'grupos_disponibles' => ubicacion_grupos_para_autorizar($pdo, (int) $ub['id_especialidad']),
        'niveles' => ubicacion_niveles_sugeridos($pdo, (int) $ub['id_especialidad']),
    ]);
    exit;
}

if ($action === 'autorizar') {
    if (!ubicacion_puede_evaluar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
        exit;
    }
    $grupos = $_POST['id_grupos'] ?? [];
    if (is_string($grupos)) {
        $grupos = array_filter(array_map('intval', explode(',', $grupos)));
    }
    if (!is_array($grupos)) {
        $grupos = [];
    }
    $res = ubicacion_autorizar(
        $pdo,
        (int) ($_POST['id_ubicacion'] ?? 0),
        trim($_POST['nivel_detectado'] ?? ''),
        $grupos,
        trim($_POST['observaciones'] ?? '') ?: null,
        (int) $_SESSION['user_id']
    );
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'rechazar') {
    if (!ubicacion_puede_evaluar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
        exit;
    }
    $res = ubicacion_rechazar(
        $pdo,
        (int) ($_POST['id_ubicacion'] ?? 0),
        trim($_POST['motivo'] ?? 'Sin ubicación'),
        (int) $_SESSION['user_id']
    );
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'grupos_permitidos') {
    $idAlumno = (int) ($_GET['id_alumno'] ?? 0);
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    $ids = ubicacion_grupos_permitidos_inscripcion($pdo, $idAlumno, $idEsp);
    $ub = ubicacion_obtener_activa($pdo, $idAlumno, $idEsp);
    hay_json_response([
        'status' => 'ok',
        'restringido' => $ids !== null,
        'id_grupos' => $ids,
        'ubicacion' => $ub,
    ]);
    exit;
}

if ($action === 'listar_asesor') {
    if (!ubicacion_puede_asesor_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
        exit;
    }
    $estado = trim($_GET['estado'] ?? $_POST['estado'] ?? '');
    hay_json_response([
        'status' => 'ok',
        'items' => ubicacion_listar_asesor($pdo, $estado !== '' ? $estado : null),
        'estados' => ubicacion_estados_etiquetas(),
    ]);
    exit;
}

if ($action === 'detalle_asesor') {
    if (!ubicacion_puede_asesor_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
        exit;
    }
    $id = (int) ($_GET['id'] ?? 0);
    $st = $pdo->prepare(
        'SELECT u.*, e.nombre AS esp_nombre,
                TRIM(CONCAT(COALESCE(a.nombres, a.nombre, \'\'), \' \', COALESCE(a.apellido_paterno, a.apellido, \'\'))) AS alumno_nombre,
                a.numero_control, a.telefono, a.email, a.id_alumno
         FROM alumno_ubicacion u
         INNER JOIN alumnos a ON a.id_alumno = u.id_alumno
         INNER JOIN especialidades e ON e.id_especialidad = u.id_especialidad
         WHERE u.id_ubicacion = ? AND u.id_plantel = ?'
    );
    $st->execute([$id, plantel_id_activo()]);
    $ub = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ub) {
        hay_json_response(['status' => 'error', 'message' => 'No encontrado']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'ubicacion' => $ub,
        'grupos_autorizados' => ubicacion_grupos_autorizados_detalle($pdo, $id),
        'estados' => ubicacion_estados_etiquetas(),
    ]);
    exit;
}

if ($action === 'asignar_grupo_asesor') {
    if (!ubicacion_puede_asesor_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
        exit;
    }
    $res = ubicacion_asesor_asignar_grupo(
        $pdo,
        (int) ($_POST['id_ubicacion'] ?? 0),
        (int) ($_POST['id_grupo'] ?? 0),
        (int) $_SESSION['user_id']
    );
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
