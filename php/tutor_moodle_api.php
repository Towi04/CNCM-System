<?php
declare(strict_types=1);

/**
 * API REST — Tutor Académico para integración Moodle (iframe / servicios externos).
 *
 * Autenticación: header Authorization: Bearer {TUTOR_MOODLE_API_KEY}
 *               + id_usuario_hay (usuario existente en tabla usuarios)
 *
 * Endpoints (POST JSON o form, action=):
 * - auth         — validar clave y usuario
 * - tutores      — listar tutores
 * - crear        — crear conversación { id_tutor, id_usuario }
 * - mensaje      — enviar pregunta { id_conversacion, id_usuario, mensaje }
 * - historial    — GET id_conversacion + id_usuario
 */
require_once __DIR__ . '/../config.php';
tutor_ensure_schema($pdo);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Tutor-User-Id');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function tutor_moodle_auth(): int
{
    $expected = tutor_moodle_api_key();
    if ($expected === '') {
        tutor_json(['status' => 'error', 'message' => 'TUTOR_MOODLE_API_KEY no configurada en el servidor'], 503);
        exit;
    }

    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        tutor_json(['status' => 'error', 'message' => 'Authorization Bearer requerido'], 401);
        exit;
    }
    if (!hash_equals($expected, trim($m[1]))) {
        tutor_json(['status' => 'error', 'message' => 'Clave API inválida'], 403);
        exit;
    }

    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) {
        $input = [];
    }

    $userId = (int) (
        $_GET['id_usuario'] ?? $_POST['id_usuario'] ?? $input['id_usuario']
        ?? $_SERVER['HTTP_X_TUTOR_USER_ID'] ?? 0
    );
    if ($userId <= 0) {
        tutor_json(['status' => 'error', 'message' => 'id_usuario requerido'], 400);
        exit;
    }

    $stmt = $GLOBALS['pdo']->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND suspendido = 0 LIMIT 1');
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) {
        tutor_json(['status' => 'error', 'message' => 'Usuario HAY no encontrado'], 404);
        exit;
    }

    return $userId;
}

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$service = tutor_service($pdo);

switch ($action) {
    case 'auth':
        $uid = tutor_moodle_auth();
        tutor_json(['status' => 'ok', 'message' => 'Autenticación válida', 'id_usuario' => $uid]);
        break;

    case 'tutores':
        $uid = tutor_moodle_auth();
        tutor_json(['status' => 'ok', 'tutores' => $service->listTutores($uid)]);
        break;

    case 'crear':
        $uid = tutor_moodle_auth();
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        $idTutor = (int) ($input['id_tutor'] ?? 0);
        if ($idTutor <= 0) {
            tutor_json(['status' => 'error', 'message' => 'id_tutor requerido'], 400);
            break;
        }
        $res = $service->crearConversacion($uid, $idTutor);
        tutor_json($res['ok'] ? ['status' => 'ok'] + $res : ['status' => 'error', 'message' => $res['message'] ?? 'Error'], $res['ok'] ? 200 : 400);
        break;

    case 'mensaje':
        $uid = tutor_moodle_auth();
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        $idConv = (int) ($input['id_conversacion'] ?? 0);
        $mensaje = tutor_sanitize_text((string) ($input['mensaje'] ?? ''));
        if ($idConv <= 0 || $mensaje === '') {
            tutor_json(['status' => 'error', 'message' => 'Datos incompletos'], 400);
            break;
        }
        $res = $service->enviarPregunta($uid, $idConv, $mensaje);
        tutor_json($res['ok'] ? ['status' => 'ok'] + $res : ['status' => 'error', 'message' => $res['message'] ?? 'Error'], $res['ok'] ? 200 : 502);
        break;

    case 'historial':
        $uid = tutor_moodle_auth();
        $idConv = (int) ($_GET['id_conversacion'] ?? $_POST['id_conversacion'] ?? 0);
        if ($idConv <= 0) {
            tutor_json(['status' => 'error', 'message' => 'id_conversacion requerido'], 400);
            break;
        }
        $res = $service->obtenerConversacion($uid, $idConv);
        tutor_json($res['ok'] ? ['status' => 'ok'] + $res : ['status' => 'error', 'message' => $res['message'] ?? 'Error'], $res['ok'] ? 200 : 404);
        break;

    default:
        tutor_json([
            'status' => 'error',
            'message' => 'Acción no válida',
            'documentacion' => 'docs/TUTOR_ACADEMICO.md',
            'acciones' => ['auth', 'tutores', 'crear', 'mensaje', 'historial'],
        ], 400);
}
