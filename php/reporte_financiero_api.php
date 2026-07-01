<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !reporte_financiero_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$accion = trim($_GET['accion'] ?? $_POST['accion'] ?? '');
$rango = reporte_financiero_rango(
    trim($_GET['desde'] ?? $_POST['desde'] ?? ''),
    trim($_GET['hasta'] ?? $_POST['hasta'] ?? '')
);

if ($accion === 'ventas') {
    $tipo = trim($_GET['tipo'] ?? $_POST['tipo'] ?? '');
    $data = reporte_ventas_listar($pdo, $idPlantel, $rango['desde'], $rango['hasta'], $tipo ?: null);
    hay_json_response([
        'status' => 'ok',
        'desde' => $rango['desde'],
        'hasta' => $rango['hasta'],
        'filas' => $data['filas'],
        'resumen' => $data['resumen'],
    ]);
    exit;
}

if ($accion === 'ventas_cuenta') {
    $modo = trim($_GET['modo'] ?? $_POST['modo'] ?? 'dia');
    $fechaRef = trim($_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d'));
    $cuenta = strtoupper(trim($_GET['cuenta'] ?? $_POST['cuenta'] ?? 'A'));
    if (!in_array($cuenta, ['A', 'B'], true)) {
        $cuenta = 'A';
    }
    $buscar = trim($_GET['q'] ?? $_POST['q'] ?? '');
    try {
        $rangoModo = reporte_financiero_rango_modo($modo, $fechaRef);
        $data = reporte_ventas_por_cuenta(
            $pdo,
            $idPlantel,
            $rangoModo['desde'],
            $rangoModo['hasta'],
            $cuenta,
            $buscar !== '' ? $buscar : null
        );
        hay_json_response([
            'status' => 'ok',
            'modo' => $modo,
            'cuenta' => $cuenta,
            'fecha' => $fechaRef,
            'desde' => $rangoModo['desde'],
            'hasta' => $rangoModo['hasta'],
            'etiqueta' => $rangoModo['etiqueta'],
            'filas' => $data['filas'],
            'resumen' => $data['resumen'],
        ]);
    } catch (Throwable $e) {
        error_log('reporte_financiero_api ventas_cuenta: ' . $e->getMessage());
        hay_json_response(['status' => 'error', 'message' => 'Error al cargar ventas por cuenta.'], 500);
    }
    exit;
}

if ($accion === 'corte_caja') {
    $fecha = trim($_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $fecha = date('Y-m-d');
    }
    $cuenta = strtoupper(trim($_GET['cuenta'] ?? $_POST['cuenta'] ?? 'B'));
    if (!in_array($cuenta, ['A', 'B'], true)) {
        $cuenta = 'B';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $calc = reporte_corte_caja_calcular($pdo, $idPlantel, $fecha, $cuenta);
        $payload = array_merge($calc, [
            'retiros' => $_POST['retiros'] ?? 0,
            'comprobantes' => $_POST['comprobantes'] ?? 0,
            'efectivo_contado' => $_POST['efectivo_contado'] ?? null,
            'billetes' => $_POST['billetes'] ?? 0,
            'monedas' => $_POST['monedas'] ?? 0,
            'notas' => $_POST['notas'] ?? '',
            'fecha' => $fecha,
            'cuenta' => $cuenta,
            'ingreso_sistema' => $calc['ingreso_sistema'],
        ]);
        $billetes = catalog_money($_POST['billetes'] ?? 0);
        $monedas = catalog_money($_POST['monedas'] ?? 0);
        if ($billetes > 0 || $monedas > 0) {
            $payload['efectivo_contado'] = round($billetes + $monedas, 2);
        }
        $res = reporte_corte_caja_guardar($pdo, $idPlantel, (int) $_SESSION['user_id'], $payload);
        hay_json_response(array_merge(['status' => 'ok'], $res));
        exit;
    }

    $data = reporte_corte_caja_calcular($pdo, $idPlantel, $fecha, $cuenta);
    hay_json_response(['status' => 'ok'] + $data);
    exit;
}

if ($accion === 'productos') {
    $data = reporte_ventas_productos_listar($pdo, $idPlantel, $rango['desde'], $rango['hasta']);
    hay_json_response([
        'status' => 'ok',
        'desde' => $rango['desde'],
        'hasta' => $rango['hasta'],
        'filas' => $data['filas'],
        'resumen' => $data['resumen'],
    ]);
    exit;
}

if ($accion === 'apoyos_inscripcion') {
    try {
        $data = reporte_apoyos_inscripcion_listar($pdo, $idPlantel, $rango['desde'], $rango['hasta']);
        hay_json_response([
            'status' => 'ok',
            'desde' => $rango['desde'],
            'hasta' => $rango['hasta'],
            'filas' => $data['filas'],
            'resumen' => $data['resumen'],
        ]);
    } catch (Throwable $e) {
        error_log('reporte_financiero_api apoyos_inscripcion: ' . $e->getMessage());
        hay_json_response(['status' => 'error', 'message' => 'Error al cargar apoyos a inscripción.'], 500);
    }
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
