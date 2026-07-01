<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autenticado'], 401);
    exit;
}

$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
$idAdmin = (int) $_SESSION['user_id'];

if ($action === 'suspender_staff') {
    $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    $res = usuario_suspender_staff($pdo, $idUsuario, $motivo, $idAdmin);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => usuario_suspension_mensaje_api($res),
        'detalle' => $res['detalle'] ?? null,
    ]);
    exit;
}

if ($action === 'reactivar_staff') {
    if (!usuario_suspension_puede_gestionar_staff()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
    $res = usuario_suspension_reactivar($pdo, $idUsuario, $idAdmin, true);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => usuario_suspension_mensaje_api($res),
        'detalle' => $res['detalle'] ?? null,
    ]);
    exit;
}

if ($action === 'suspender_alumno_adeudo') {
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    $res = usuario_suspender_alumno_adeudo($pdo, $idAlumno, $motivo, $idAdmin);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message'] ?? '']);
    exit;
}

if ($action === 'reactivar_alumno') {
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    $res = usuario_reactivar_alumno_acceso($pdo, $idAlumno, $idAdmin);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message'] ?? '']);
    exit;
}

if ($action === 'estado_usuario') {
    $idUsuario = (int) ($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0);
    $u = usuario_por_id($pdo, $idUsuario);
    if (!$u) {
        hay_json_response(['status' => 'error', 'message' => 'No encontrado']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'suspendido' => (int) ($u['suspendido'] ?? 0),
        'suspension_tipo' => $u['suspension_tipo'] ?? null,
        'suspension_motivo' => $u['suspension_motivo'] ?? null,
        'etiqueta' => usuario_suspension_etiqueta($u),
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);

function usuario_suspension_mensaje_api(array $res): string
{
    $msg = (string) ($res['message'] ?? '');
    $det = $res['detalle'] ?? [];
    if (!is_array($det) || $det === []) {
        return $msg;
    }
    $parts = [$msg];
    foreach (['google', 'moodle'] as $k) {
        if (!empty($det[$k]['message'])) {
            $parts[] = ucfirst($k) . ': ' . $det[$k]['message'];
        }
    }

    return implode("\n", $parts);
}
