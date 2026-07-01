<?php
require __DIR__ . '/../config.php';

if (!catalog_puede_administrar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id = (int) ($_POST['id_especialidad'] ?? 0);
$campo = trim((string) ($_POST['campo'] ?? ''));

if ($id <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

$permitidos = ['visible', 'activo', 'es_fija'];
if (!in_array($campo, $permitidos, true)) {
    hay_json_response(['status' => 'error', 'message' => 'Campo no válido']);
    exit;
}

$esp = catalog_especialidad_obtener_basico($pdo, $id);
if (!$esp) {
    hay_json_response(['status' => 'error', 'message' => 'Especialidad no encontrada']);
    exit;
}

if ($campo === 'activo' && (int) $esp['activo'] === 1) {
    hay_json_response([
        'status' => 'confirm_desactivar',
        'message' => 'Indique la especialidad sustituta para los grupos vinculados',
        'id_especialidad' => $id,
    ]);
    exit;
}

$valorActual = (int) ($esp[$campo] ?? 0);
$nuevo = $valorActual ? 0 : 1;

if ($campo === 'activo' && $nuevo === 1) {
    $pdo->prepare('UPDATE especialidades SET activo = 1 WHERE id_especialidad = ?')->execute([$id]);
    hay_json_response([
        'status' => 'ok',
        'message' => 'Especialidad activada',
        'campo' => $campo,
        'valor' => 1,
    ]);
    exit;
}

try {
    $pdo->prepare('UPDATE especialidades SET ' . $campo . ' = ? WHERE id_especialidad = ?')->execute([$nuevo, $id]);
} catch (PDOException $e) {
    hay_json_response(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
    exit;
}

$labels = [
    'visible' => $nuevo ? 'visible' : 'oculta',
    'es_fija' => $nuevo ? 'fija' : 'temporal',
    'activo' => $nuevo ? 'activa' : 'inactiva',
];

hay_json_response([
    'status' => 'ok',
    'message' => 'Actualizado: ' . ($labels[$campo] ?? ''),
    'campo' => $campo,
    'valor' => $nuevo,
]);
