<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

if (!function_exists('alumno_portal_puede_ver') || !alumno_portal_puede_ver()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Sin permiso.']);
    exit;
}

$idAlumno = alumno_portal_id_sesion();
if ($idAlumno <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Cuenta sin alumno vinculado.']);
    exit;
}

$res = alumno_perfil_guardar($pdo, $idAlumno, [
    'hobbies' => $_POST['hobbies'] ?? '',
    'materias_favoritas' => $_POST['materias_favoritas'] ?? '',
    'como_aprende' => $_POST['como_aprende'] ?? '',
    'meta' => $_POST['meta'] ?? '',
    'gustos_libre' => $_POST['gustos_libre'] ?? '',
]);

if (empty($res['ok'])) {
    echo json_encode(['status' => 'error', 'message' => $res['message'] ?? 'No se pudo guardar']);
    exit;
}

echo json_encode([
    'status' => 'ok',
    'message' => $res['message'] ?? 'Perfil guardado',
    'redirect' => 'alumno_portal_inicio',
]);
