<?php
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if (!curso_personalizado_puede_gestionar()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$idPlantel = plantel_scope_id($pdo);

if ($action === 'listar') {
    $st = $pdo->prepare(
        "SELECT c.*, a.numero_control,
                CONCAT(a.nombres, ' ', a.apellido_paterno) AS alumno_nombre
         FROM curso_personalizado c
         INNER JOIN alumnos a ON a.id_alumno = c.id_alumno
         WHERE c.id_plantel = ? AND c.estado = 'activo'
         ORDER BY c.creado_en DESC LIMIT 100"
    );
    $st->execute([$idPlantel]);
    hay_json_response(['status' => 'ok', 'cursos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'crear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fechas = [];
    if (!empty($_POST['fechas_json'])) {
        $fechas = json_decode((string) $_POST['fechas_json'], true) ?: [];
    }
    $res = curso_personalizado_crear($pdo, [
        'id_plantel' => $idPlantel,
        'id_alumno' => (int) ($_POST['id_alumno'] ?? 0),
        'titulo' => (string) ($_POST['titulo'] ?? ''),
        'costo_total' => $_POST['costo_total'] ?? 0,
        'num_pagos' => (int) ($_POST['num_pagos'] ?? 1),
        'duracion_semanas' => $_POST['duracion_semanas'] ?? '',
        'id_especialidad_ref' => (int) ($_POST['id_especialidad_ref'] ?? 0),
        'fechas' => $fechas,
    ]);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'pagos') {
    $idCurso = (int) ($_GET['id_curso'] ?? 0);
    if ($idCurso <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Curso requerido']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'pagos' => curso_personalizado_pagos_pendientes($pdo, $idCurso),
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
