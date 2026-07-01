<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'listar_ciclos') {
    if (!profesor_360_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'ciclos' => profesor_360_listar_ciclos($pdo, plantel_scope_id($pdo)),
    ]);
    exit;
}

if ($action === 'guardar_ciclo') {
    if (!profesor_360_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $res = profesor_360_guardar_ciclo($pdo, $_POST, (int) $_SESSION['user_id']);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message'], 'id_ciclo' => (int) ($res['id_ciclo'] ?? 0)]);
    exit;
}

if ($action === 'publicar_ciclo') {
    if (!profesor_360_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $res = profesor_360_publicar_ciclo($pdo, (int) ($_POST['id_ciclo'] ?? 0));
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'cerrar_ciclo') {
    if (!profesor_360_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $res = profesor_360_cerrar_ciclo($pdo, (int) ($_POST['id_ciclo'] ?? 0));
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'publicar_resultados') {
    if (!profesor_360_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $res = profesor_360_publicar_resultados($pdo, (int) ($_POST['id_ciclo'] ?? 0));
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'guardar_participantes') {
    if (!profesor_360_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $filas = json_decode((string) ($_POST['participantes'] ?? '[]'), true);
    if (!is_array($filas)) {
        $filas = [];
    }
    $res = profesor_360_guardar_participantes($pdo, (int) ($_POST['id_ciclo'] ?? 0), $filas);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'guardar_eval') {
    $tipo = (string) ($_POST['tipo'] ?? '');
    if (!profesor_360_puede_evaluar_como($tipo) && !profesor_360_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $puntajes = [];
    foreach ($_POST as $k => $v) {
        if (str_starts_with($k, 'puntaje_')) {
            $puntajes[substr($k, 8)] = (float) $v;
        }
    }
    $res = profesor_360_guardar_eval(
        $pdo,
        (int) ($_POST['id_ciclo'] ?? 0),
        (int) ($_POST['id_profesor'] ?? 0),
        (int) $_SESSION['user_id'],
        $tipo,
        $puntajes,
        (string) ($_POST['observaciones'] ?? ''),
        (int) ($_POST['id_grupo'] ?? 0) ?: null,
        rbac_rol_efectivo() === 'alumno'
            ? (((int) ($_SESSION['id_alumno_link'] ?? 0)) ?: null)
            : null,
        (int) ($_POST['cerrar'] ?? 0) === 1
    );
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'pct' => $res['pct'] ?? null,
        'seccion' => 'profesor_360_mis_resultados',
    ]);
    exit;
}

if ($action === 'guardar_rubrica') {
    if (!profesor_360_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $criterios = json_decode((string) ($_POST['criterios'] ?? '[]'), true);
    if (!is_array($criterios)) {
        $criterios = [];
    }
    $id = profesor_360_guardar_rubrica($pdo, [
        'id_rubrica' => (int) ($_POST['id_rubrica'] ?? 0),
        'clave' => (string) ($_POST['clave'] ?? ''),
        'nombre' => (string) ($_POST['nombre'] ?? ''),
        'tipo' => (string) ($_POST['tipo'] ?? 'showclass'),
        'criterios' => $criterios,
    ]);
    hay_json_response(['status' => 'ok', 'message' => 'Rúbrica guardada', 'id_rubrica' => $id]);
    exit;
}

if ($action === 'pendientes') {
    $pend = profesor_360_pendientes_usuario($pdo, (int) $_SESSION['user_id'], rbac_rol_efectivo());
    hay_json_response(['status' => 'ok', 'pendientes' => $pend]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
