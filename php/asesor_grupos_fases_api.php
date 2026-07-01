<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !asesor_puede_grupos_fases()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$action = trim($_GET['action'] ?? '');

if ($action === 'fases') {
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    if ($idEsp <= 0) {
        hay_json_response(['status' => 'ok', 'fases' => []]);
        exit;
    }
    hay_json_response(['status' => 'ok', 'fases' => fase_listar($pdo, $idEsp)]);
    exit;
}

if ($action === 'buscar') {
    $grupos = asesor_grupos_por_fase($pdo, $idPlantel, [
        'id_fase' => (int) ($_GET['id_fase'] ?? 0),
        'clave_fase' => trim($_GET['clave_fase'] ?? ''),
        'id_especialidad' => (int) ($_GET['id_especialidad'] ?? 0),
        'solo_futuro' => !empty($_GET['solo_futuro']),
    ]);
    hay_json_response(['status' => 'ok', 'grupos' => $grupos, 'total' => count($grupos)]);
    exit;
}

if ($action === 'especialidades') {
    $st = $pdo->query('SELECT id_especialidad, nombre, clave FROM especialidades WHERE activo = 1 ORDER BY nombre');
    hay_json_response(['status' => 'ok', 'especialidades' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
