<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !asesor_puede_entrevistas()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$idUsuario = (int) $_SESSION['user_id'];
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'listar') {
    $idAsesorReq = (int) ($_GET['id_usuario_asesor'] ?? 0);
    $idAsesor = ($idAsesorReq > 0 && asesor_puede_registrar_entrevista_ajena()) ? $idAsesorReq : $idUsuario;
    $estado = trim($_GET['estado'] ?? '');
    $periodo = trim($_GET['periodo'] ?? 'semana');
    if (!in_array($periodo, ['dia', 'semana', 'mes'], true)) {
        $periodo = 'semana';
    }
    hay_json_response([
        'status' => 'ok',
        'entrevistas' => asesor_entrevista_listar(
            $pdo,
            $idPlantel,
            $idAsesor,
            $estado !== '' ? $estado : null,
            $periodo
        ),
    ]);
    exit;
}

if ($action === 'estadisticas') {
    $idAsesorReq = (int) ($_GET['id_usuario_asesor'] ?? 0);
    $idAsesor = ($idAsesorReq > 0 && asesor_puede_registrar_entrevista_ajena()) ? $idAsesorReq : $idUsuario;
    $periodo = trim($_GET['periodo'] ?? 'semana');
    if (!in_array($periodo, ['dia', 'semana', 'mes'], true)) {
        $periodo = 'semana';
    }
    hay_json_response([
        'status' => 'ok',
        'stats' => asesor_entrevista_estadisticas($pdo, $idPlantel, $idAsesor, $periodo),
    ]);
    exit;
}

if ($action === 'asesores' && asesor_puede_registrar_entrevista_ajena()) {
    $st = $pdo->prepare(
        "SELECT id_usuario, nombre, apellido FROM usuarios
         WHERE id_plantel = ? AND rol = 'asesor' AND (suspendido IS NULL OR suspendido = 0)
         ORDER BY nombre, apellido"
    );
    $st->execute([$idPlantel]);
    hay_json_response(['status' => 'ok', 'asesores' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = asesor_entrevista_guardar($pdo, $idPlantel, [
        'id_usuario_asesor' => (int) ($_POST['id_usuario_asesor'] ?? $idUsuario),
        'id_usuario_registra' => $idUsuario,
        'nombres' => $_POST['nombres'] ?? '',
        'apellido_paterno' => $_POST['apellido_paterno'] ?? '',
        'apellido_materno' => $_POST['apellido_materno'] ?? '',
        'telefono' => $_POST['telefono'] ?? '',
        'email' => $_POST['email'] ?? '',
        'observaciones' => $_POST['observaciones'] ?? '',
        'sin_datos' => !empty($_POST['sin_datos']),
    ]);
    hay_json_response($res['ok']
        ? ['status' => 'ok', 'message' => $res['message'], 'id_entrevista' => $res['id_entrevista'] ?? null]
        : ['status' => 'error', 'message' => $res['message'] ?? 'Error']);
    exit;
}

if ($action === 'ir_preregistro') {
    $id = (int) ($_GET['id_entrevista'] ?? 0);
    $st = $pdo->prepare(
        'SELECT * FROM asesor_entrevistas WHERE id_entrevista = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$id, $idPlantel]);
    $e = $st->fetch(PDO::FETCH_ASSOC);
    if (!$e) {
        hay_json_response(['status' => 'error', 'message' => 'Entrevista no encontrada']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'prefill' => [
            'nombres' => $e['nombres'],
            'apellido_paterno' => $e['apellido_paterno'],
            'apellido_materno' => $e['apellido_materno'],
            'telefono' => $e['telefono'],
            'email' => $e['email'],
            'observaciones' => $e['observaciones'],
            'id_entrevista' => (int) $e['id_entrevista'],
        ],
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
