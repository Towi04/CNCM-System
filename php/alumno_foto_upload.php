<?php
require __DIR__ . '/../config.php';



header('Content-Type: application/json; charset=utf-8');



if (!isset($_SESSION['user_id'])) {

    echo json_encode(['status' => 'error', 'message' => 'No autorizado'], JSON_UNESCAPED_UNICODE);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode(['status' => 'error', 'message' => 'Método inválido'], JSON_UNESCAPED_UNICODE);

    exit;

}



$idAlumno = (int) ($_POST['id_alumno'] ?? 0);

$idPlantel = plantel_scope_id($pdo);



if ($idAlumno <= 0 || !plantel_enforce_alumno($pdo, $idAlumno, $idPlantel)) {

    echo json_encode(['status' => 'error', 'message' => 'Alumno no encontrado en este plantel'], JSON_UNESCAPED_UNICODE);

    exit;

}



if (!function_exists('usuario_puede_gestionar_alumnos') || !usuario_puede_gestionar_alumnos()) {

    echo json_encode(['status' => 'error', 'message' => 'No tiene permiso para editar fotos de alumnos'], JSON_UNESCAPED_UNICODE);

    exit;

}



$action = trim($_POST['action'] ?? 'upload');



if ($action === 'remove') {

    $st = $pdo->prepare('SELECT foto FROM alumnos WHERE id_alumno = ? LIMIT 1');

    $st->execute([$idAlumno]);

    $old = trim((string) ($st->fetchColumn() ?: ''));

    alumno_foto_delete_file($old);

    alumno_foto_asignar($pdo, $idAlumno, null);

    echo json_encode([

        'status' => 'ok',

        'message' => 'Foto eliminada',

        'foto_url' => '',

    ], JSON_UNESCAPED_UNICODE);

    exit;

}



$st = $pdo->prepare('SELECT foto FROM alumnos WHERE id_alumno = ? LIMIT 1');

$st->execute([$idAlumno]);

$oldPath = trim((string) ($st->fetchColumn() ?: ''));



$result = alumno_foto_save_upload($idAlumno, $_FILES['foto'] ?? []);

if (!$result['ok']) {

    echo json_encode(['status' => 'error', 'message' => $result['message']], JSON_UNESCAPED_UNICODE);

    exit;

}



$newPath = (string) $result['path'];

alumno_foto_asignar($pdo, $idAlumno, $newPath);



if ($oldPath !== '' && $oldPath !== $newPath) {

    alumno_foto_delete_file($oldPath);

}



$url = alumno_foto_public_url($newPath);

if ($url === null) {

    echo json_encode([

        'status' => 'error',

        'message' => 'La foto se guardó pero no se pudo mostrar. Recargue la página.',

    ], JSON_UNESCAPED_UNICODE);

    exit;

}



echo json_encode([

    'status' => 'ok',

    'message' => $result['message'],

    'foto_url' => $url,

], JSON_UNESCAPED_UNICODE);

