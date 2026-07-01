<?php
require __DIR__ . '/../config.php';

if (!catalog_puede_administrar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'delete';

if ($action === 'preview') {
    $id = (int) ($_GET['id_especialidad'] ?? 0);
    if ($id <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }

    $esp = catalog_especialidad_obtener_basico($pdo, $id);
    if (!$esp) {
        hay_json_response(['status' => 'error', 'message' => 'Especialidad no encontrada']);
        exit;
    }

    $numGrupos = catalog_contar_grupos_por_especialidad($pdo, $id);
    $sustitutos = catalog_listar_especialidades_activas($pdo, $id);

    hay_json_response([
        'status' => 'ok',
        'especialidad' => $esp,
        'num_grupos' => $numGrupos,
        'grupos_muestra' => $numGrupos > 0
            ? catalog_listar_grupos_muestra_especialidad($pdo, $id)
            : [],
        'sustitutos' => $sustitutos,
        'requiere_sustituto' => $numGrupos > 0,
    ]);
    exit;
}

$id = (int) ($_POST['id_especialidad'] ?? 0);
$idSustituta = (int) ($_POST['id_especialidad_sustituta'] ?? 0);

if ($id <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

$result = catalog_especialidad_desactivar_con_sustitucion(
    $pdo,
    $id,
    $idSustituta > 0 ? $idSustituta : null
);

if (!$result['ok']) {
    hay_json_response(['status' => 'error', 'message' => $result['message']]);
    exit;
}

hay_json_response([
    'status' => 'ok',
    'message' => $result['message'],
    'grupos_actualizados' => $result['grupos_actualizados'] ?? 0,
    'seccion' => 'admin_especialidades',
]);
