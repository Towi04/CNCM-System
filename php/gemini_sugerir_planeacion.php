<?php

require_once __DIR__ . '/../config.php';

planeacion_ensure_schema($pdo);



header('Content-Type: application/json; charset=utf-8');



if (!isset($_SESSION['user_id'])) {

    echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada'], JSON_UNESCAPED_UNICODE);

    exit;

}



if (!hay_ai_configured()) {

    echo json_encode([

        'status' => 'error',

        'message' => 'Falta configurar OPENROUTER_API_KEY o GEMINI_API_KEY en config.local.php',

    ], JSON_UNESCAPED_UNICODE);

    exit;

}



$tema = trim($_POST['tema'] ?? '');

$grupoId = (int) ($_POST['id_grupo'] ?? 0);

$idFase = (int) ($_POST['id_fase'] ?? 0);

$duracion = trim($_POST['duracion'] ?? '50');



if ($tema === '' || $grupoId <= 0 || $idFase <= 0) {

    echo json_encode(['status' => 'error', 'message' => 'Grupo, fase y tema son requeridos'], JSON_UNESCAPED_UNICODE);

    exit;

}



if (!planeacion_puede_grupo($pdo, $grupoId)) {

    echo json_encode(['status' => 'error', 'message' => 'Sin permiso para este grupo'], JSON_UNESCAPED_UNICODE);

    exit;

}



$grupo = planeacion_grupo_detalle($pdo, $grupoId);

$fase = planeacion_prompt_fase_detalle($pdo, $idFase) ?: [];

if ($fase === []) {

    foreach (planeacion_fases_grupo($pdo, $grupoId) as $f) {

        if ((int) $f['id_fase'] === $idFase) {

            $fase = planeacion_prompt_fase_detalle($pdo, $idFase) ?: $f;

            break;

        }

    }

}



$activosTotal = 0;

try {

    $stmt = $pdo->prepare(

        'SELECT COUNT(*) FROM alumno_grupos ag

         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno

         WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.estado = \'activo\''

    );

    $stmt->execute([$grupoId]);

    $activosTotal = (int) $stmt->fetchColumn();

} catch (Throwable $e) {

    $activosTotal = 0;

}



$idEspecialidad = (int) ($grupo['id_especialidad'] ?? 0);

$prompt = planeacion_prompt_resolver($pdo, $idEspecialidad, $grupo ?: [], $fase, $tema, $duracion, $activosTotal);



$res = hay_ai_request($prompt);

if (!$res['ok']) {

    echo json_encode([

        'status' => 'error',

        'message' => $res['message'] ?? 'Error de IA',

        'hint' => $res['hint'] ?? null,

        'http_code' => $res['http_code'] ?? 0,

        'model' => $res['model'] ?? null,

        'provider' => $res['provider'] ?? hay_ai_provider(),

    ], JSON_UNESCAPED_UNICODE);

    exit;

}



echo json_encode([

    'status' => 'ok',

    'sugerencia' => $res['text'],

    'model' => $res['model'] ?? null,

    'provider' => $res['provider'] ?? hay_ai_provider(),

], JSON_UNESCAPED_UNICODE);

