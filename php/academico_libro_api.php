<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'listar_alumno') {
    if (!function_exists('alumno_portal_puede_ver') || !alumno_portal_puede_ver()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Sin permiso']);
        exit;
    }
    $idAlumno = alumno_portal_id_sesion();
    $libros = academico_libro_listar_alumno($pdo, $idAlumno);
    $uid = (int) $_SESSION['user_id'];
    foreach ($libros as &$lb) {
        $lb['stream_token'] = academico_libro_stream_token((int) $lb['id_version'], $uid);
    }
    unset($lb);
    echo json_encode(['status' => 'ok', 'libros' => $libros], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!academico_libro_puede_gestionar()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Sin permiso para gestionar libros']);
    exit;
}

academico_libro_ensure_schema($pdo);

if ($action === 'listar') {
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    echo json_encode([
        'status' => 'ok',
        'libros' => academico_libro_listar($pdo, $idEsp > 0 ? $idEsp : null),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'versiones') {
    $idLibro = (int) ($_GET['id_libro'] ?? 0);
    echo json_encode([
        'status' => 'ok',
        'versiones' => academico_libro_versiones($pdo, $idLibro),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'crear_libro' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = academico_libro_crear(
        $pdo,
        (int) ($_POST['id_especialidad'] ?? 0),
        (string) ($_POST['tipo'] ?? ''),
        (string) ($_POST['titulo'] ?? '')
    );
    echo json_encode(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message'], 'id_libro' => $res['id_libro'] ?? null]);
    exit;
}

if ($action === 'subir_version' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = academico_libro_version_subir(
        $pdo,
        (int) ($_POST['id_libro'] ?? 0),
        (string) ($_POST['etiqueta'] ?? ''),
        $_FILES['pdf'] ?? [],
        !empty($_POST['activo_alumno']),
        !empty($_POST['activo_rag'])
    );
    echo json_encode(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message'], 'id_version' => $res['id_version'] ?? null]);
    exit;
}

if ($action === 'activar_version' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = academico_libro_version_activar($pdo, (int) ($_POST['id_version'] ?? 0), (string) ($_POST['modo'] ?? ''));
    echo json_encode(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'indexar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = academico_libro_version_indexar($pdo, (int) ($_POST['id_version'] ?? 0), true);
    echo json_encode([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'paginas' => $res['paginas'] ?? null,
        'chunks' => $res['chunks'] ?? null,
        'embeddings' => $res['embeddings'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'sync_moodle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/moodle_material_helper.php';
    $res = moodle_sync_academico_material($pdo, (int) ($_POST['id_especialidad'] ?? 0) ?: null);
    echo json_encode(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res), JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
