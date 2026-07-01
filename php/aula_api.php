<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !aula_puede_gestionar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$accion = trim($_POST['accion'] ?? $_GET['accion'] ?? '');

if ($accion === 'listar') {
    hay_json_response([
        'status' => 'ok',
        'aulas' => aula_listar_plantel($pdo, $idPlantel),
        'tipos' => aula_tipos(),
    ]);
    exit;
}

if ($accion === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idAula = (int) ($_POST['id_aula'] ?? 0) ?: null;
    $espIds = json_decode($_POST['especialidades'] ?? '[]', true);
    if (!is_array($espIds)) {
        $espIds = [];
    }
    $res = aula_guardar($pdo, $idPlantel, [
        'codigo' => $_POST['codigo'] ?? '',
        'nombre' => $_POST['nombre'] ?? '',
        'piso' => $_POST['piso'] ?? '',
        'capacidad' => $_POST['capacidad'] ?? 20,
        'tiene_pizarron' => $_POST['tiene_pizarron'] ?? 0,
        'tiene_proyector' => $_POST['tiene_proyector'] ?? 0,
        'tiene_tv' => $_POST['tiene_tv'] ?? 0,
        'tiene_pc' => $_POST['tiene_pc'] ?? 0,
        'tipo_aula' => $_POST['tipo_aula'] ?? 'aula',
        'capacidad_flexible' => $_POST['capacidad_flexible'] ?? 0,
        'todas_especialidades' => $_POST['todas_especialidades'] ?? 1,
        'notas' => $_POST['notas'] ?? '',
        'activo' => $_POST['activo'] ?? 1,
        'especialidades' => $espIds,
    ], $idAula);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'id_aula' => $res['id_aula'] ?? null,
    ]);
    exit;
}

if ($accion === 'subir_foto' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idAula = (int) ($_POST['id_aula'] ?? 0);
    $res = aula_subir_foto($pdo, $idPlantel, $idAula, $_FILES['foto'] ?? null);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'foto' => $res['ok'] ? [
            'id_foto' => $res['id_foto'] ?? null,
            'url' => $res['url'] ?? '',
            'ruta' => $res['ruta'] ?? '',
        ] : null,
    ]);
    exit;
}

if ($accion === 'eliminar_foto' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = aula_eliminar_foto($pdo, $idPlantel, (int) ($_POST['id_foto'] ?? 0));
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($accion === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = aula_eliminar($pdo, $idPlantel, (int) ($_POST['id_aula'] ?? 0));
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
