<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'No autorizado']);
    exit;
}

$accion = trim((string) ($_POST['accion'] ?? $_GET['accion'] ?? ''));
$idUsuario = (int) ($_SESSION['user_id'] ?? 0);
$idPlantel = plantel_id_activo();

try {
    pago_transferencia_ensure_schema($pdo);

    switch ($accion) {
        case 'listar_pendientes':
            if (!pago_transferencia_puede_confirmar() && !pago_transferencia_puede_ver()) {
                echo json_encode(['ok' => false, 'message' => 'Sin permiso']);
                break;
            }
            $desde = $_GET['desde'] ?? $_POST['desde'] ?? null;
            $hasta = $_GET['hasta'] ?? $_POST['hasta'] ?? null;
            echo json_encode([
                'ok' => true,
                'data' => pago_transferencia_listar_pendientes($pdo, $idPlantel, $desde, $hasta),
            ]);
            break;

        case 'listar_comprobantes':
            if (!pago_transferencia_puede_ver()) {
                echo json_encode(['ok' => false, 'message' => 'Sin permiso']);
                break;
            }
            $desde = $_GET['desde'] ?? $_POST['desde'] ?? date('Y-m-d');
            $hasta = $_GET['hasta'] ?? $_POST['hasta'] ?? date('Y-m-d');
            $estado = $_GET['estado'] ?? $_POST['estado'] ?? null;
            echo json_encode([
                'ok' => true,
                'data' => pago_transferencia_listar_comprobantes($pdo, $idPlantel, $desde, $hasta, $estado),
            ]);
            break;

        case 'confirmar':
            if (!pago_transferencia_puede_confirmar()) {
                echo json_encode(['ok' => false, 'message' => 'Sin permiso']);
                break;
            }
            $idPago = (int) ($_POST['id_pago'] ?? 0);
            echo json_encode(pago_transferencia_confirmar($pdo, $idPago, $idUsuario));
            break;

        case 'rechazar':
            if (!pago_transferencia_puede_confirmar()) {
                echo json_encode(['ok' => false, 'message' => 'Sin permiso']);
                break;
            }
            $idPago = (int) ($_POST['id_pago'] ?? 0);
            $motivo = trim((string) ($_POST['motivo'] ?? ''));
            echo json_encode(pago_transferencia_rechazar($pdo, $idPago, $idUsuario, $motivo));
            break;

        case 'subir_comprobante':
            if (!pago_transferencia_puede_ver() && !(function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() === 'alumno')) {
                echo json_encode(['ok' => false, 'message' => 'Sin permiso']);
                break;
            }
            $idPago = (int) ($_POST['id_pago'] ?? 0);
            if (function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() === 'alumno') {
                $pago = pago_transferencia_obtener($pdo, $idPago);
                $idAlumnoSesion = function_exists('alumno_portal_id_sesion') ? alumno_portal_id_sesion() : 0;
                if (!$pago || (int) ($pago['id_alumno'] ?? 0) !== $idAlumnoSesion) {
                    echo json_encode(['ok' => false, 'message' => 'No puede modificar este pago']);
                    break;
                }
            }
            echo json_encode(pago_transferencia_adjuntar_comprobante($pdo, $idPago, $_FILES['comprobante'] ?? []));
            break;

        case 'registrar':
        case 'registrar_alumno':
            $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
            $esAlumno = function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() === 'alumno';
            if ($esAlumno) {
                $idAlumno = function_exists('alumno_portal_id_sesion') ? alumno_portal_id_sesion() : 0;
                if ($idAlumno <= 0) {
                    echo json_encode(['ok' => false, 'message' => 'Sesión de alumno no válida']);
                    break;
                }
            } else {
                $puedeReg = pago_transferencia_puede_ver()
                    || (function_exists('rbac_cap') && rbac_cap('menu_consulta_adeudo'));
                if (!$puedeReg) {
                    echo json_encode(['ok' => false, 'message' => 'Sin permiso']);
                    break;
                }
            }
            $res = pago_transferencia_registrar($pdo, [
                'id_alumno' => $idAlumno,
                'id_especialidad' => (int) ($_POST['id_especialidad'] ?? 0) ?: null,
                'id_alumno_especialidad' => (int) ($_POST['id_alumno_especialidad'] ?? 0) ?: null,
                'tipo' => trim((string) ($_POST['tipo'] ?? 'abono')) ?: 'abono',
                'monto' => $_POST['monto'] ?? 0,
                'folio' => trim((string) ($_POST['folio'] ?? '')),
                'cuenta_banco' => $_POST['cuenta_banco'] ?? '',
                'concepto' => trim((string) ($_POST['concepto'] ?? '')),
                'periodo_ref' => trim((string) ($_POST['periodo_ref'] ?? '')) ?: null,
                'creado_en' => trim((string) ($_POST['fecha_pago'] ?? '')) ?: date('Y-m-d H:i:s'),
            ], $_FILES['comprobante'] ?? null);
            echo json_encode([
                'ok' => !empty($res['ok']),
                'status' => !empty($res['ok']) ? 'ok' : 'error',
                'message' => $res['message'] ?? '',
                'id_pago' => $res['id_pago'] ?? null,
                'ticket_url' => $res['ticket_url'] ?? null,
                'transfer_pendiente' => $res['transfer_pendiente'] ?? true,
            ]);
            break;

        default:
            echo json_encode(['ok' => false, 'message' => 'Acción no válida']);
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
