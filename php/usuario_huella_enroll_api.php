<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!huella_puede_enrolar_usuario()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) ($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0);

if ($action === 'estado') {
    if ($idUsuario <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Usuario inválido']);
        exit;
    }
    $est = huella_estado_usuario($pdo, $idUsuario, $idPlantel);
    hay_json_response(array_merge(['status' => $est['ok'] ? 'ok' : 'error'], $est));
    exit;
}

if ($action === 'registrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($idUsuario <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Usuario inválido']);
        exit;
    }

    $samplesRaw = $_POST['samples'] ?? '';
    $samples = [];
    if (is_string($samplesRaw) && $samplesRaw !== '') {
        $decoded = json_decode($samplesRaw, true);
        $samples = is_array($decoded) ? $decoded : [$samplesRaw];
    } elseif (is_array($samplesRaw)) {
        $samples = $samplesRaw;
    }

    $codigo = trim((string) ($_POST['codigo_huella'] ?? ''));
    $dedo = trim((string) ($_POST['dedo'] ?? 'indice_derecho'));

    $res = huella_registrar_enrollment_usuario($pdo, $idUsuario, $idPlantel, $samples, $codigo, $dedo);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
