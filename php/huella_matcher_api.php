<?php
declare(strict_types=1);

/**
 * API para el servicio local FingerJet (PC de recepción Windows).
 * Autenticación: HAY_FINGERJET_MATCHER_KEY en config.local.php
 */
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!huella_fingerjet_config()['enabled']) {
    hay_json_response(['ok' => false, 'status' => 'error', 'message' => 'FingerJet no habilitado en HAY'], 503);
    exit;
}

if (!huella_matcher_api_key_valida()) {
    hay_json_response(['ok' => false, 'status' => 'error', 'message' => 'Clave de matcher inválida'], 403);
    exit;
}

$accion = trim($_GET['action'] ?? $_POST['action'] ?? '');
$idPlantel = (int) ($_GET['id_plantel'] ?? $_POST['id_plantel'] ?? 0);

if ($accion === 'health') {
    $cfg = huella_fingerjet_config();
    hay_json_response([
        'ok' => true,
        'status' => 'ok',
        'fingerjet_enabled' => true,
        'mode' => $cfg['mode'],
        'matcher_url' => $cfg['matcher_url'],
    ]);
    exit;
}

if ($accion === 'gallery') {
    if ($idPlantel <= 0) {
        hay_json_response(['ok' => false, 'status' => 'error', 'message' => 'id_plantel requerido'], 400);
        exit;
    }
    hay_json_response(huella_matcher_galeria($pdo, $idPlantel));
    exit;
}

if ($accion === 'registrar_fmd' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    $fmd = trim((string) ($_POST['template_fmd'] ?? ''));
    $formato = trim((string) ($_POST['fmd_formato'] ?? 'DP_FMD'));
    if ($idPlantel <= 0 || $idAlumno <= 0) {
        hay_json_response(['ok' => false, 'status' => 'error', 'message' => 'Parámetros inválidos'], 400);
        exit;
    }
    $res = huella_registrar_fmd($pdo, $idAlumno, $idPlantel, $fmd, $formato);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

hay_json_response(['ok' => false, 'status' => 'error', 'message' => 'Acción no válida'], 400);
