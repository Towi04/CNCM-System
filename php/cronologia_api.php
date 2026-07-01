<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !cronologia_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$filtros = [
    'id_especialidad' => (int) ($_GET['id_especialidad'] ?? 0),
    'id_profesor' => (int) ($_GET['id_profesor'] ?? 0),
    'id_grupo' => (int) ($_GET['id_grupo'] ?? 0),
    'q' => trim($_GET['q'] ?? ''),
    'semanas_proyeccion' => (int) ($_GET['semanas_proyeccion'] ?? 8),
    'estado' => trim($_GET['estado'] ?? ''),
    'semanas_atras' => (int) ($_GET['semanas_atras'] ?? 6),
    'semanas_adelante' => (int) ($_GET['semanas_adelante'] ?? 14),
];

$vista = trim($_GET['vista'] ?? 'matriz');

if ($vista === 'matriz') {
    $matriz = cronologia_matriz($pdo, $idPlantel, $filtros);
    hay_json_response([
        'status' => 'ok',
        'vista' => 'matriz',
        ...$matriz,
    ]);
    exit;
}

$grupos = cronologia_listar_grupos($pdo, $idPlantel, $filtros);

hay_json_response([
    'status' => 'ok',
    'grupos' => $grupos,
    'total' => count($grupos),
]);
