<?php
/**
 * API — cuentas digitales del personal (Google, HAY, Moodle).
 */
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'estado'));
$idUsuario = (int) ($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0);

if ($idUsuario <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'Indique id_usuario']);
    exit;
}

if (!function_exists('cuenta_digital_estado_staff')) {
    hay_json_response(['status' => 'error', 'message' => 'Módulo de cuentas no disponible']);
    exit;
}

if ($action === 'estado') {
    if (!cuenta_digital_puede_gestionar_staff()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $est = cuenta_digital_estado_staff($pdo, $idUsuario);
    hay_json_response([
        'status' => !empty($est['ok']) ? 'ok' : 'error',
        'message' => $est['message'] ?? '',
        'estado' => $est,
    ]);
    exit;
}

if (!cuenta_digital_puede_gestionar_staff()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado para gestionar cuentas']);
    exit;
}

switch ($action) {
    case 'provisionar':
        $servicio = trim((string) ($_POST['servicio'] ?? 'all'));
        $msgs = [];
        if ($servicio === 'all' || $servicio === 'google' || $servicio === 'moodle') {
            if (!function_exists('cuenta_externa_provisionar_staff')) {
                hay_json_response(['status' => 'error', 'message' => 'Helper no disponible']);
                exit;
            }
            $st = $pdo->prepare('SELECT email FROM usuarios WHERE id_usuario = ? LIMIT 1');
            $st->execute([$idUsuario]);
            $email = (string) ($st->fetchColumn() ?: '');
            $res = cuenta_externa_provisionar_staff($pdo, $idUsuario, false, $email !== '' ? $email : null);
            hay_json_response([
                'status' => !empty($res['ok']) ? 'ok' : 'error',
                'message' => $res['message'] ?? '',
                'estado' => cuenta_digital_estado_staff($pdo, $idUsuario),
            ]);
            exit;
        }
        hay_json_response(['status' => 'error', 'message' => 'Servicio no válido']);
        break;

    case 'buscar':
        $res = cuenta_digital_buscar_externas(
            trim((string) ($_POST['google_email'] ?? '')),
            trim((string) ($_POST['moodle_ref'] ?? ''))
        );
        hay_json_response(['status' => 'ok', 'resultado' => $res]);
        break;

    case 'vincular':
        $res = cuenta_digital_vincular_staff($pdo, $idUsuario, [
            'google_email' => trim((string) ($_POST['google_email'] ?? '')),
            'moodle_ref' => trim((string) ($_POST['moodle_ref'] ?? '')),
            'username_unificado' => trim((string) ($_POST['username_unificado'] ?? '')),
            'sync_moodle_username' => !empty($_POST['sync_moodle_username']),
        ]);
        hay_json_response([
            'status' => !empty($res['ok']) ? 'ok' : 'error',
            'message' => $res['message'] ?? '',
            'detalle' => $res['detalle'] ?? null,
            'estado' => $res['estado'] ?? null,
        ]);
        break;

    default:
        hay_json_response(['status' => 'error', 'message' => 'Acción no válida: ' . $action]);
}
