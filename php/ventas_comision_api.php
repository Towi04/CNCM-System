<?php



require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');



if (!isset($_SESSION['user_id'])) {

    hay_json_response(['status' => 'error', 'message' => 'No autenticado']);

    exit;

}



$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

$idPlantel = plantel_id_activo();



$accionesConsultaAdmin = [

    'especialidades_reglas',

    'regla_historial',

    'tabuladores_listar',

    'tabulador_detalle',

    'overrides_listar',

    'asesores_plantel',

];

$accionesEscritura = [

    'regla_guardar',

    'tabulador_guardar',

    'override_guardar',

];



if ($action === 'liquidacion_asesor') {

    if (!ventas_comision_puede_consultar()) {

        hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);

        exit;

    }

    $periodo = (string) ($_GET['periodo'] ?? 'semana');

    $fecha = trim((string) ($_GET['fecha'] ?? '')) ?: null;

    $idAsesor = (int) ($_GET['id_usuario_asesor'] ?? 0);

    if ($idAsesor <= 0) {

        $idAsesor = (int) $_SESSION['user_id'];

    }

    if (rbac_rol_efectivo() === 'asesor' && $idAsesor !== (int) $_SESSION['user_id']) {

        hay_json_response(['status' => 'error', 'message' => 'Solo puede consultar sus propias comisiones']);

        exit;

    }

    $liq = ventas_liquidacion_asesor($pdo, $idPlantel, $idAsesor, $periodo, $fecha);

    hay_json_response(['status' => 'ok', 'data' => $liq]);

    exit;

}



if ($action === 'liquidacion_gerente') {

    if (!ventas_comision_puede_administrar() && !rbac_cap('menu_comisiones_consulta')) {

        hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);

        exit;

    }

    $periodo = (string) ($_GET['periodo'] ?? 'semana');

    $fecha = trim((string) ($_GET['fecha'] ?? '')) ?: null;

    $liq = ventas_liquidacion_gerente($pdo, $idPlantel, $periodo, $fecha);

    hay_json_response(['status' => 'ok', 'data' => $liq]);

    exit;

}



if (in_array($action, $accionesEscritura, true) && !ventas_comision_puede_editar()) {

    hay_json_response(['status' => 'error', 'message' => 'Solo consulta. No tiene permiso para modificar comisiones.']);

    exit;

}



if (in_array($action, array_merge($accionesConsultaAdmin, $accionesEscritura), true)

    && !ventas_comision_puede_administrar()) {

    hay_json_response(['status' => 'error', 'message' => 'Sin permiso de administración']);

    exit;

}



if ($action === 'especialidades_reglas') {

    $st = $pdo->prepare(

        'SELECT id_especialidad, clave, nombre, ventas_comision_asesor, ventas_comision_gerente,

                ventas_comision_asesor_pct, ventas_comision_gerente_pct, ventas_cuenta_tabulador,

                ventas_tipo_comision, es_plantilla_personalizado

         FROM especialidades WHERE activo = 1 ORDER BY nombre'

    );

    $st->execute();

    hay_json_response(['status' => 'ok', 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);

    exit;

}



if ($action === 'regla_guardar') {

    $idEsp = (int) ($_POST['id_especialidad'] ?? 0);

    $res = ventas_regla_especialidad_guardar($pdo, $idEsp, $_POST);

    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));

    exit;

}



if ($action === 'regla_historial') {

    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);

    $st = $pdo->prepare(

        'SELECT * FROM ventas_regla_especialidad_hist WHERE id_especialidad = ? ORDER BY creado_en DESC LIMIT 50'

    );

    $st->execute([$idEsp]);

    hay_json_response(['status' => 'ok', 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);

    exit;

}



if ($action === 'tabuladores_listar') {

    $st = $pdo->prepare(

        'SELECT t.*, (SELECT COUNT(*) FROM ventas_tabulador_tramo tr WHERE tr.id_tabulador = t.id_tabulador) AS num_tramos

         FROM ventas_tabulador t WHERE t.id_plantel = ? ORDER BY t.vigente_desde DESC'

    );

    $st->execute([$idPlantel]);

    hay_json_response(['status' => 'ok', 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);

    exit;

}



if ($action === 'tabulador_detalle') {

    $id = (int) ($_GET['id_tabulador'] ?? 0);

    $tab = ventas_tabulador_por_id($pdo, $id);

    hay_json_response(['status' => $tab ? 'ok' : 'error', 'tabulador' => $tab]);

    exit;

}



if ($action === 'tabulador_guardar') {

    $res = ventas_tabulador_guardar($pdo, $idPlantel, $_POST);

    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));

    exit;

}



if ($action === 'overrides_listar') {

    $st = $pdo->prepare(

        'SELECT o.*, CONCAT(u.nombre, \' \', u.apellido) AS asesor_nombre, t.nombre AS tabulador_nombre

         FROM ventas_override o

         LEFT JOIN usuarios u ON u.id_usuario = o.id_usuario_asesor

         LEFT JOIN ventas_tabulador t ON t.id_tabulador = o.id_tabulador

         WHERE o.id_plantel = ? ORDER BY o.fecha_desde DESC LIMIT 100'

    );

    $st->execute([$idPlantel]);

    hay_json_response(['status' => 'ok', 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);

    exit;

}



if ($action === 'override_guardar') {

    $res = ventas_override_guardar($pdo, $idPlantel, $_POST);

    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));

    exit;

}



if ($action === 'asesores_plantel') {

    try {

        $st = $pdo->prepare(

            "SELECT id_usuario, nombre, apellido FROM usuarios

             WHERE id_plantel = ? AND rol = 'asesor' AND COALESCE(suspendido, 0) = 0 ORDER BY nombre"

        );

        $st->execute([$idPlantel]);

        hay_json_response(['status' => 'ok', 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);

    } catch (Throwable $e) {

        error_log('ventas_comision_api asesores_plantel: ' . $e->getMessage());

        hay_json_response(['status' => 'error', 'message' => 'No se pudo cargar asesores.'], 500);

    }

    exit;

}



hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);

