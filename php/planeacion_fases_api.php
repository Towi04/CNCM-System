<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

$idGrupo = (int) ($_GET['id_grupo'] ?? 0);
if ($idGrupo <= 0 || !planeacion_puede_grupo($pdo, $idGrupo)) {
    hay_json_response(['status' => 'error', 'message' => 'Grupo no disponible']);
    exit;
}

$grupo = planeacion_grupo_detalle($pdo, $idGrupo);
$fases = planeacion_fases_grupo($pdo, $idGrupo);
$out = [];
foreach ($fases as $f) {
    $out[] = [
        'id_fase' => (int) $f['id_fase'],
        'clave_fase' => $f['clave_fase'] ?? '',
        'nombre_fase' => $f['nombre_fase'] ?? '',
        'sugerida' => (int) ($f['id_fase'] ?? 0) === (int) ($grupo['id_fase_actual'] ?? 0),
    ];
}

hay_json_response([
    'status' => 'ok',
    'grupo' => [
        'id_grupo' => $idGrupo,
        'clave' => $grupo['clave'] ?? '',
        'especialidad' => $grupo['esp_nombre'] ?? '',
        'fase_actual' => $grupo['clave_fase'] ?? '',
    ],
    'fases' => $out,
    'gustos' => function_exists('planeacion_grupo_gustos_resumen')
        ? planeacion_grupo_gustos_resumen($pdo, $idGrupo)
        : null,
]);
