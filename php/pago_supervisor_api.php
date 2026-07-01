<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!pago_supervisor_puede()) {
    echo json_encode(['ok' => false, 'message' => 'Sin permiso']);
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$idUsuario = (int) ($_SESSION['user_id'] ?? 0);
$idPlantel = plantel_id_activo();

try {
    switch ($accion) {
        case 'anular':
            $idPago = (int) ($_POST['id_pago'] ?? 0);
            $motivo = trim((string) ($_POST['motivo'] ?? ''));
            echo json_encode(pago_supervisor_anular($pdo, $idPago, $motivo, $idUsuario));
            break;

        case 'editar':
            $idPago = (int) ($_POST['id_pago'] ?? 0);
            $motivo = trim((string) ($_POST['motivo'] ?? ''));
            $cambios = [
                'monto' => isset($_POST['monto']) ? (float) $_POST['monto'] : null,
                'concepto' => $_POST['concepto'] ?? null,
            ];
            echo json_encode(pago_supervisor_editar($pdo, $idPago, $cambios, $motivo, $idUsuario));
            break;

        case 'reporte':
            $filtros = [
                'desde' => $_GET['desde'] ?? $_POST['desde'] ?? date('Y-m-01'),
                'hasta' => $_GET['hasta'] ?? $_POST['hasta'] ?? date('Y-m-d'),
            ];
            echo json_encode(['ok' => true, 'data' => pago_supervisor_reporte_anulados($pdo, $idPlantel, $filtros)]);
            break;

        default:
            echo json_encode(['ok' => false, 'message' => 'Acción no válida']);
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
