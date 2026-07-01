<?php
require __DIR__ . '/../config.php';

if (!alumno_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idAlumno = (int) ($_POST['id_alumno'] ?? 0);
$accion = trim($_POST['accion'] ?? '');

if ($idAlumno <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'Alumno inválido']);
    exit;
}

$chk = $pdo->prepare('SELECT id_alumno FROM alumnos WHERE id_alumno = ? AND id_plantel = ?');
$chk->execute([$idAlumno, plantel_id_activo()]);
if (!$chk->fetchColumn()) {
    hay_json_response(['status' => 'error', 'message' => 'Alumno no encontrado']);
    exit;
}

if ($accion === 'baja_temporal') {
    $res = alumno_baja_temporal($pdo, $idAlumno, $_POST['motivo'] ?? '');
} elseif ($accion === 'baja_definitiva') {
    $res = alumno_baja_definitiva($pdo, $idAlumno, $_POST['motivo'] ?? '');
} elseif ($accion === 'reactivar') {
    $res = alumno_reactivar($pdo, $idAlumno);
} else {
    hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
    exit;
}

hay_json_response([
    'status' => $res['ok'] ? 'ok' : 'error',
    'message' => $res['message'],
    'seccion' => 'alumno_detalle',
    'params' => 'id=' . $idAlumno,
    'inscripcion_vigente_hasta' => $res['inscripcion_vigente_hasta'] ?? null,
]);
