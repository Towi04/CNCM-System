<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}
if (!docente_prospecto_puede_gestionar()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'save') {
    $id = (int) ($_POST['id_prospecto'] ?? 0);
    $res = docente_prospecto_guardar($pdo, $_POST, (int) $_SESSION['user_id'], $id);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'id_prospecto' => (int) ($res['id_prospecto'] ?? 0),
        'seccion' => 'docente_prospectos',
    ]);
    exit;
}

if ($action === 'save_showclass') {
    $id = (int) ($_POST['id_prospecto'] ?? 0);
    $puntajes = [];
    foreach (docente_prospecto_showclass_rubrica($pdo, $id) as $r) {
        $cod = $r['codigo'];
        $puntajes[$cod] = (float) ($_POST['puntaje_' . $cod] ?? 0);
    }
    $res = docente_prospecto_guardar_showclass(
        $pdo,
        $id,
        $puntajes,
        (string) ($_POST['comentarios'] ?? ''),
        (int) $_SESSION['user_id']
    );
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'data' => $res,
        'seccion' => 'docente_prospectos',
    ]);
    exit;
}

if ($action === 'save_decision') {
    $res = docente_prospecto_guardar_decision(
        $pdo,
        (int) ($_POST['id_prospecto'] ?? 0),
        (string) ($_POST['decision_final'] ?? ''),
        (string) ($_POST['categoria_no_contratacion'] ?? ''),
        (string) ($_POST['motivo_no_contratacion'] ?? ''),
        (string) ($_POST['recontactar_en'] ?? ''),
        (int) ($_POST['segunda_oportunidad'] ?? 0) === 1,
        (int) $_SESSION['user_id']
    );
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'seccion' => 'docente_prospectos',
    ]);
    exit;
}

if ($action === 'check_matches') {
    $rows = docente_prospecto_coincidencias(
        $pdo,
        trim((string) ($_GET['email'] ?? '')),
        trim((string) ($_GET['telefono'] ?? '')),
        trim((string) ($_GET['curp'] ?? '')),
        (int) ($_GET['id_prospecto'] ?? 0)
    );
    hay_json_response(['status' => 'ok', 'matches' => $rows]);
    exit;
}

if ($action === 'crear_acceso') {
    $res = docente_prospecto_crear_acceso_candidato($pdo, (int) ($_POST['id_prospecto'] ?? 0), (int) $_SESSION['user_id']);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'password_inicial' => $res['password_inicial'] ?? null,
        'seccion' => 'docente_prospectos',
    ]);
    exit;
}

if ($action === 'contratar') {
    $res = docente_prospecto_contratar(
        $pdo,
        (int) ($_POST['id_prospecto'] ?? 0),
        (string) ($_POST['email_google'] ?? ''),
        (int) $_SESSION['user_id']
    );
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message'], 'seccion' => 'docente_prospectos']);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
