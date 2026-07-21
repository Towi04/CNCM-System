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

$grupoId = (int) ($_POST['id_grupo'] ?? 0);
$idFase = (int) ($_POST['id_fase'] ?? 0);
$duracion = trim((string) ($_POST['duracion'] ?? '50'));
$instrucciones = trim((string) ($_POST['instrucciones_adicionales'] ?? $_POST['instrucciones'] ?? ''));
// Compatibilidad: si llega "tema" legado, se ignora como fuente principal (el temario viene de la fase).
$temaLegado = trim((string) ($_POST['tema'] ?? ''));

if ($grupoId <= 0 || $idFase <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Grupo y fase son requeridos'], JSON_UNESCAPED_UNICODE);
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

if ($fase === []) {
    echo json_encode(['status' => 'error', 'message' => 'Fase no encontrada'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tema = planeacion_prompt_tema_desde_fase($pdo, $fase);
if ($tema === '' && $temaLegado !== '') {
    $tema = $temaLegado;
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
$prompt = planeacion_prompt_resolver(
    $pdo,
    $idEspecialidad,
    $grupo ?: [],
    $fase,
    $tema,
    $duracion,
    $activosTotal,
    $instrucciones
);

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
    'titulo_sugerido' => planeacion_titulo_desde_fase($pdo, $fase),
    'tema_fase' => $tema,
    'model' => $res['model'] ?? null,
    'provider' => $res['provider'] ?? hay_ai_provider(),
], JSON_UNESCAPED_UNICODE);
