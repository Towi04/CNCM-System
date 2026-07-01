<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autenticado'], 401);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

if ($action === 'preview_clave') {
    $area = strtoupper(trim((string) ($_GET['codigo_area'] ?? $_POST['codigo_area'] ?? 'I')));
    $horario = strtoupper(trim((string) ($_GET['codigo_horario'] ?? $_POST['codigo_horario'] ?? 'S')));
    $tipo = (string) ($_GET['tipo_grupo'] ?? $_POST['tipo_grupo'] ?? 'regular');
    $extensivo = $tipo === 'extensivo';
    $personalizado = $tipo === 'personalizado';
    $nombrePer = trim((string) ($_GET['nombre_personalizado'] ?? $_POST['nombre_personalizado'] ?? ''));
    $idPlantel = plantel_scope_id($pdo);
    try {
        $prev = grupo_clave_vista_previa($pdo, $idPlantel, $area, $horario, $extensivo, $personalizado, $nombrePer);
        hay_json_response(['status' => 'ok', 'preview' => $prev]);
    } catch (Throwable $e) {
        hay_json_response(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'fases_especialidad') {
    $area = strtoupper(trim((string) ($_GET['codigo_area'] ?? '')));
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    if ($idEsp <= 0) {
        $idEsp = (int) (grupo_area_id_especialidad($pdo, $area) ?? 0);
    }
    if ($idEsp <= 0) {
        hay_json_response(['status' => 'ok', 'fases' => [], 'id_especialidad' => null]);
        exit;
    }
    fase_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT id_fase, clave_fase, nombre_fase, orden FROM especialidad_fases
         WHERE id_especialidad = ? AND activo = 1 ORDER BY orden ASC, id_fase ASC'
    );
    $st->execute([$idEsp]);
    hay_json_response([
        'status' => 'ok',
        'id_especialidad' => $idEsp,
        'fases' => $st->fetchAll(PDO::FETCH_ASSOC),
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
