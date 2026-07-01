<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!huella_puede_enrolar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$idPlantel = plantel_id_activo();
$idAlumno = (int) ($_GET['id_alumno'] ?? $_POST['id_alumno'] ?? 0);

if ($action === 'estado') {
    if ($idAlumno <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Alumno inválido']);
        exit;
    }
    $est = huella_estado_alumno($pdo, $idAlumno, $idPlantel);
    hay_json_response(array_merge(['status' => $est['ok'] ? 'ok' : 'error'], $est));
    exit;
}

if ($action === 'registrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($idAlumno <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Alumno inválido']);
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

    $res = huella_registrar_enrollment($pdo, $idAlumno, $idPlantel, $samples, $codigo, $dedo);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'registrar_pin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($idAlumno <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Alumno inválido']);
        exit;
    }
    $codigo = trim((string) ($_POST['codigo_huella'] ?? ''));
    $res = huella_registrar_pin_manual($pdo, $idAlumno, $codigo, $idPlantel);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
        'codigo_huella' => $res['codigo_huella'] ?? '',
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
