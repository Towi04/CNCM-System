<?php
require __DIR__ . '/../config.php';

if (!grupo_plan_puede_editar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$idGrupo = (int) ($_POST['id_grupo'] ?? $_GET['id_grupo'] ?? 0);

if ($idGrupo > 0 && !plantel_grupo_pertenece($pdo, $idGrupo, plantel_id_activo())) {
    hay_json_response(['status' => 'error', 'message' => 'Grupo no válido']);
    exit;
}

if ($action === 'get') {
    $anio = (int) ($_GET['anio'] ?? date('Y'));
    $mes = (int) ($_GET['mes'] ?? date('n'));
    $plan = grupo_plan_obtener($pdo, $idGrupo, $anio, $mes);
    hay_json_response(['status' => 'ok', 'plan' => $plan]);
    exit;
}

if ($action === 'save') {
    $fasesTemario = $_POST['fases_temario'] ?? [];
    if (is_string($fasesTemario)) {
        $fasesTemario = json_decode($fasesTemario, true) ?: [];
    }
    $res = grupo_plan_guardar(
        $pdo,
        $idGrupo,
        (int) ($_POST['anio'] ?? date('Y')),
        (int) ($_POST['mes'] ?? date('n')),
        (int) ($_POST['id_fase_registro'] ?? 0),
        is_array($fasesTemario) ? $fasesTemario : [],
        trim($_POST['nota_coordinador'] ?? ''),
        trim($_POST['temas_retomar'] ?? ''),
        !empty($_POST['pendiente_retomar']),
        (int) ($_SESSION['user_id'] ?? 0) ?: null
    );
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'marcar_retomado') {
    $idPlan = (int) ($_POST['id_plan'] ?? 0);
    if ($idPlan > 0) {
        grupo_plan_marcar_retomado($pdo, $idPlan);
    }
    hay_json_response(['status' => 'ok', 'message' => 'Marcado como atendido']);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
