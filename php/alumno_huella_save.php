<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    hay_json_response(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    hay_json_response(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

if (!huella_puede_editar_alumno()) {
    http_response_code(403);
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idAlumno = (int) ($_POST['id_alumno'] ?? 0);
$codigo = $_POST['codigo_huella'] ?? '';
$idPlantel = plantel_id_activo();

if ($idAlumno <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'Alumno inválido']);
    exit;
}

$res = huella_asignar_alumno($pdo, $idAlumno, $codigo, $idPlantel);
hay_json_response([
    'status' => $res['ok'] ? 'ok' : 'error',
    'message' => $res['message'],
    'codigo_huella' => $res['codigo_huella'] ?? '',
    'seccion' => 'alumno_detalle',
    'params' => 'id=' . $idAlumno,
]);
