<?php
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!rbac_cap('menu_gerente_cartas')) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = trim($_POST['action'] ?? '');
if ($action !== 'guardar' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
    exit;
}

gerente_cartas_ensure_schema($pdo);
$idPlantel = plantel_scope_id($pdo);
$periodo = trim($_POST['periodo_mes'] ?? gerente_semana_actual());
if (!preg_match('/^\d{4}-W\d{2}$/', $periodo) && !preg_match('/^\d{4}-\d{2}$/', $periodo)) {
    $periodo = gerente_semana_actual();
}
$idRegistra = (int) ($_SESSION['user_id'] ?? 0);
$asesores = array_map('intval', (array) ($_POST['asesores'] ?? []));

$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM asesor_cartas_periodo WHERE id_plantel = ? AND periodo_mes = ?')
        ->execute([$idPlantel, $periodo]);
    $ins = $pdo->prepare(
        'INSERT INTO asesor_cartas_periodo (id_plantel, id_usuario_asesor, periodo_mes, registrado_por)
         VALUES (?,?,?,?)'
    );
    foreach ($asesores as $idAsesor) {
        if ($idAsesor > 0) {
            $ins->execute([$idPlantel, $idAsesor, $periodo, $idRegistra ?: null]);
        }
    }
    $pdo->commit();
    hay_json_response(['status' => 'ok', 'message' => 'Designación guardada para ' . $periodo]);
} catch (Throwable $e) {
    $pdo->rollBack();
    hay_json_response(['status' => 'error', 'message' => 'No se pudo guardar: ' . $e->getMessage()]);
}
