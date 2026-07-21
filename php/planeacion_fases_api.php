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
    $idFase = (int) ($f['id_fase'] ?? 0);
    $detalle = function_exists('planeacion_prompt_fase_detalle')
        ? (planeacion_prompt_fase_detalle($pdo, $idFase) ?: $f)
        : $f;
    $semanas = [];
    if (function_exists('fase_temario_semanas')) {
        foreach (fase_temario_semanas($pdo, $idFase) as $s) {
            $tit = trim((string) ($s['titulo_leccion'] ?? ''));
            if ($tit === '') {
                continue;
            }
            $semanas[] = [
                'semana' => (int) ($s['semana'] ?? 0),
                'titulo_leccion' => $tit,
            ];
        }
    }
    $out[] = [
        'id_fase' => $idFase,
        'clave_fase' => $detalle['clave_fase'] ?? ($f['clave_fase'] ?? ''),
        'nombre_fase' => $detalle['nombre_fase'] ?? ($f['nombre_fase'] ?? ''),
        'temas' => trim((string) ($detalle['temas'] ?? '')),
        'objetivo_parcial' => trim((string) ($detalle['objetivo_parcial'] ?? '')),
        'vocabulario_resumen' => trim((string) ($detalle['vocabulario_resumen'] ?? '')),
        'gramatica_resumen' => trim((string) ($detalle['gramatica_resumen'] ?? '')),
        'semanas' => $semanas,
        'titulo_sugerido' => function_exists('planeacion_titulo_desde_fase')
            ? planeacion_titulo_desde_fase($pdo, $detalle)
            : trim((string) (($detalle['clave_fase'] ?? '') . ' ' . ($detalle['nombre_fase'] ?? ''))),
        'sugerida' => $idFase === (int) ($grupo['id_fase_actual'] ?? 0),
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
