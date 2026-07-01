<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

if (!graduacion_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'check_alumno';

if ($action === 'sync_alerts') {
    if (!graduacion_puede_decidir()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $res = graduacion_generar_alertas_automaticas($pdo);
    hay_json_response([
        'status' => 'ok',
        'message' => 'Alertas de graduación actualizadas',
        'data' => $res,
        'seccion' => 'graduacion_alertas',
    ]);
    exit;
}

if ($action === 'decidir_alerta') {
    if (!graduacion_puede_decidir()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $res = graduacion_decidir_alerta(
        $pdo,
        (int) ($_POST['id_alerta'] ?? 0),
        (string) ($_POST['estado'] ?? ''),
        (string) ($_POST['motivo_decision'] ?? ''),
        (int) $_SESSION['user_id']
    );
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'seccion' => 'graduacion_alertas',
    ]);
    exit;
}

$idAlumno = (int) ($_GET['id_alumno'] ?? $_POST['id_alumno'] ?? 0);
$idEsp = (int) ($_GET['id_especialidad'] ?? $_POST['id_especialidad'] ?? 0);

if ($idAlumno <= 0 || $idEsp <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

$res = graduacion_puede_solicitar($pdo, $idAlumno, $idEsp);
hay_json_response([
    'status' => $res['ok'] ? 'ok' : 'warning',
    'message' => $res['message'],
    'pendientes' => $res['pendientes'] ?? [],
]);
