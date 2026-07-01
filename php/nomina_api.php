<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$accion = trim($_GET['accion'] ?? $_POST['accion'] ?? '');
$idUser = (int) $_SESSION['user_id'];

if ($accion === 'catalogo') {
    if (!nomina_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'catalogo' => nomina_catalogo($pdo),
        'personal' => nomina_personal_plantel($pdo, $idPlantel),
        'liquidaciones' => nomina_listar($pdo, $idPlantel),
        'puede_ajustar' => nomina_puede_ajustar_manual(),
    ]);
    exit;
}

if ($accion === 'rango') {
    if (!nomina_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $tipo = trim($_GET['tipo_periodo'] ?? $_POST['tipo_periodo'] ?? 'quincena');
    $fecha = trim($_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d'));
    if (!in_array($tipo, ['semana', 'quincena', 'mes'], true)) {
        $tipo = 'quincena';
    }
    hay_json_response(['status' => 'ok', 'rango' => nomina_rango_periodo($tipo, $fecha)]);
    exit;
}

if ($accion === 'generar') {
    if (!nomina_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $tipo = trim($_POST['tipo_periodo'] ?? 'quincena');
    $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
    if (!in_array($tipo, ['semana', 'quincena', 'mes'], true)) {
        hay_json_response(['status' => 'error', 'message' => 'Periodo no válido']);
        exit;
    }
    $res = nomina_generar($pdo, $idPlantel, $tipo, $fecha, $idUser);
    if (!$res['ok']) {
        hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'Error']);
        exit;
    }
    $liq = nomina_obtener($pdo, (int) $res['id_liquidacion'], $idPlantel);
    hay_json_response([
        'status' => 'ok',
        'message' => $res['message'] ?? 'Generada',
        'id_liquidacion' => (int) $res['id_liquidacion'],
        'liquidacion' => $liq,
    ]);
    exit;
}

if ($accion === 'importar_asesorias') {
    if (!nomina_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $idLiq = (int) ($_POST['id_liquidacion'] ?? 0);
    $desde = $_POST['desde'] ?? null;
    $hasta = $_POST['hasta'] ?? null;
    $res = asesoria_nomina_importar($pdo, $idLiq, $idPlantel, $desde, $hasta);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($accion === 'obtener') {
    if (!nomina_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $idLiq = (int) ($_GET['id_liquidacion'] ?? $_POST['id_liquidacion'] ?? 0);
    $liq = nomina_obtener($pdo, $idLiq, $idPlantel);
    if (!$liq) {
        hay_json_response(['status' => 'error', 'message' => 'Liquidación no encontrada']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'liquidacion' => $liq,
        'puede_ajustar' => nomina_puede_ajustar_manual() && ($liq['estado'] ?? '') !== 'cerrada',
    ]);
    exit;
}

if ($accion === 'cerrar') {
    if (!nomina_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $idLiq = (int) ($_POST['id_liquidacion'] ?? 0);
    $res = nomina_cerrar($pdo, $idPlantel, $idLiq);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message'] ?? '']);
    exit;
}

if ($accion === 'guardar_config') {
    if (!nomina_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
    if ($idUsuario <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Usuario no válido']);
        exit;
    }
    $res = nomina_guardar_config($pdo, $idPlantel, $idUsuario, $_POST);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message'] ?? '']);
    exit;
}

if ($accion === 'linea_agregar') {
    $res = nomina_linea_agregar_manual($pdo, $idPlantel, (int) ($_POST['id_liquidacion'] ?? 0), $_POST, $idUser);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);
    exit;
}

if ($accion === 'linea_editar') {
    $res = nomina_linea_editar_manual($pdo, $idPlantel, (int) ($_POST['id_linea'] ?? 0), $_POST, $idUser);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);
    exit;
}

if ($accion === 'linea_eliminar') {
    $res = nomina_linea_eliminar_manual($pdo, $idPlantel, (int) ($_POST['id_linea'] ?? 0), (string) ($_POST['observacion'] ?? ''), $idUser);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);
    exit;
}

if ($accion === 'suplencia_catalogo') {
    if (!suplencia_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'motivos' => suplencia_motivos_labels(),
        'reglas' => suplencia_reglas_labels(),
        'grupos' => suplencia_grupos_plantel($pdo, $idPlantel),
        'profesores' => suplencia_profesores_plantel($pdo, $idPlantel),
        'suplencias' => suplencia_listar($pdo, $idPlantel),
    ]);
    exit;
}

if ($accion === 'suplencia_guardar') {
    $res = suplencia_guardar($pdo, $idPlantel, $_POST, $idUser);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);
    exit;
}

if ($accion === 'suplencia_cancelar') {
    $res = suplencia_cancelar($pdo, (int) ($_POST['id_suplencia'] ?? 0), $idPlantel);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error'] + $res);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
