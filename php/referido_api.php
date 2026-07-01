<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !preregistro_puede_acceder()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'buscar') {
    $q = trim($_GET['q'] ?? '');
    hay_json_response([
        'status' => 'ok',
        'alumnos' => referido_buscar_alumno_activo($pdo, $idPlantel, $q),
    ]);
    exit;
}

if ($action === 'preview_beneficio') {
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    $idRef = (int) ($_GET['id_alumno_referidor'] ?? 0);
    $st = $pdo->prepare('SELECT * FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1');
    $st->execute([$idRef, $idPlantel]);
    $al = $st->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        hay_json_response(['status' => 'error', 'message' => 'Referidor no encontrado']);
        exit;
    }
    $monto = referido_calcular_beneficio($pdo, $idEsp, $al);
    hay_json_response([
        'status' => 'ok',
        'monto' => $monto,
        'monto_fmt' => catalog_format_mxn($monto),
    ]);
    exit;
}

if ($action === 'marcar_firma' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id_referido'] ?? 0);
    referido_marcar_firma($pdo, $id, $idPlantel, !empty($_POST['copia_impresa']));
    hay_json_response(['status' => 'ok', 'message' => 'Firma registrada']);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
