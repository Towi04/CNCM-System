<?php
declare(strict_types=1);

/**
 * API REST — Tutor Académico (panel HAY).
 */
require_once __DIR__ . '/../config.php';

if (!function_exists('tutor_json')) {
    function tutor_json(array $data, int $code = 200): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    error_log('tutor_api shutdown: ' . ($err['message'] ?? '') . ' @ ' . ($err['file'] ?? '') . ':' . ($err['line'] ?? 0));
    tutor_json([
        'status' => 'error',
        'message' => 'Error fatal del servidor al procesar el tutor',
        'detail' => (defined('HAY_DEBUG') && HAY_DEBUG) ? ($err['message'] ?? '') : null,
    ], 500);
});

try {
    tutor_ensure_schema($pdo);

    if (!tutor_schema_listo($pdo)) {
        tutor_json([
            'status' => 'error',
            'message' => 'El módulo Tutor no está instalado en la base de datos. Entre al panel como admin una vez o ejecute las migraciones SQL 018/020.',
        ], 503);
        exit;
    }

    $action = trim($_GET['action'] ?? $_POST['action'] ?? '');
    $service = tutor_service($pdo);

    switch ($action) {
        case 'csrf':
            tutor_require_permiso();
            tutor_json(['status' => 'ok', 'csrf' => tutor_csrf_token()]);
            break;

        case 'tutores':
            $uid = tutor_require_permiso();
            $esp = trim($_GET['especialidad'] ?? '');
            $tutores = $service->listTutores($uid, $esp !== '' ? $esp : null);
            tutor_json([
                'status' => 'ok',
                'tutores' => $tutores,
                'ia_configurada' => function_exists('hay_ai_configured') && hay_ai_configured(),
                'ia_provider' => function_exists('hay_ai_provider_label') ? hay_ai_provider_label() : '',
                'solo_un_tutor' => count($tutores) === 1,
            ]);
            break;

        case 'conversaciones':
            $uid = tutor_require_permiso();
            $archivadas = isset($_GET['archivadas']) && $_GET['archivadas'] === '1';
            tutor_json(['status' => 'ok', 'conversaciones' => $service->listConversaciones($uid, $archivadas)]);
            break;

        case 'conversacion':
            $uid = tutor_require_permiso();
            $id = (int) ($_GET['id_conversacion'] ?? 0);
            if ($id <= 0) {
                tutor_json(['status' => 'error', 'message' => 'id_conversacion requerido'], 400);
                break;
            }
            $res = $service->obtenerConversacion($uid, $id);
            if (!$res['ok']) {
                tutor_json(['status' => 'error', 'message' => $res['message'] ?? 'Error'], 404);
                break;
            }
            tutor_json([
                'status' => 'ok',
                'conversacion' => $res['conversacion'],
                'mensajes' => $res['mensajes'],
            ]);
            break;

        case 'crear':
            $uid = tutor_require_permiso();
            tutor_require_csrf_post();
            $idTutor = (int) ($_POST['id_tutor'] ?? 0);
            if ($idTutor <= 0) {
                tutor_json(['status' => 'error', 'message' => 'id_tutor requerido'], 400);
                break;
            }
            $res = $service->crearConversacion($uid, $idTutor);
            tutor_json($res['ok'] ? ['status' => 'ok'] + $res : ['status' => 'error', 'message' => $res['message'] ?? 'Error'], $res['ok'] ? 200 : 400);
            break;

        case 'mensaje':
            $uid = tutor_require_permiso();
            tutor_require_csrf_post();
            $idConv = (int) ($_POST['id_conversacion'] ?? 0);
            $idTutor = (int) ($_POST['id_tutor'] ?? 0);
            $mensaje = tutor_sanitize_text((string) ($_POST['mensaje'] ?? ''));
            if ($mensaje === '') {
                tutor_json(['status' => 'error', 'message' => 'Escriba un mensaje'], 400);
                break;
            }
            if ($idConv <= 0 && $idTutor <= 0) {
                tutor_json(['status' => 'error', 'message' => 'Seleccione un tutor o conversación'], 400);
                break;
            }
            $res = $service->enviarPregunta($uid, $idConv, $mensaje, $idTutor > 0 ? $idTutor : null);
            if (!$res['ok']) {
                tutor_json([
                    'status' => 'error',
                    'message' => $res['message'] ?? 'Error',
                    'hint' => $res['hint'] ?? null,
                ], 502);
                break;
            }
            tutor_json(['status' => 'ok'] + $res);
            break;

        case 'archivar':
            $uid = tutor_require_permiso();
            tutor_require_csrf_post();
            $idConv = (int) ($_POST['id_conversacion'] ?? 0);
            if ($idConv <= 0) {
                tutor_json(['status' => 'error', 'message' => 'id_conversacion requerido'], 400);
                break;
            }
            $res = $service->archivarConversacion($uid, $idConv);
            tutor_json($res['ok'] ? ['status' => 'ok', 'message' => $res['message']] : ['status' => 'error', 'message' => $res['message']], $res['ok'] ? 200 : 400);
            break;

        default:
            tutor_json([
                'status' => 'error',
                'message' => 'Acción no válida',
                'acciones' => ['csrf', 'tutores', 'conversaciones', 'conversacion', 'crear', 'mensaje', 'archivar'],
            ], 400);
    }
} catch (Throwable $e) {
    error_log('tutor_api fatal: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    tutor_json([
        'status' => 'error',
        'message' => 'Error interno del tutor académico',
        'detail' => (defined('HAY_DEBUG') && HAY_DEBUG) ? $e->getMessage() : null,
    ], 500);
}
