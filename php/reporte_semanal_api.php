<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !reporte_semanal_puede_ver()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$accion = trim($_POST['accion'] ?? $_GET['accion'] ?? 'generar');

if ($accion === 'generar') {
    $anio = (int) ($_GET['anio'] ?? $_POST['anio'] ?? date('Y'));
    $semanaDesde = (int) ($_GET['semana_desde'] ?? $_POST['semana_desde'] ?? 0);
    $semanaHasta = (int) ($_GET['semana_hasta'] ?? $_POST['semana_hasta'] ?? 0);
    $modo = trim($_GET['modo'] ?? $_POST['modo'] ?? 'semana');

    $actual = reporte_semanal_desde_fecha(date('Y-m-d'));
    if ($semanaDesde <= 0) {
        $semanaDesde = $actual['semana'];
    }
    if ($semanaHasta <= 0) {
        $semanaHasta = $semanaDesde;
    }
    if ($anio <= 0) {
        $anio = $actual['anio'];
    }

    $data = reporte_semanal_generar($pdo, $idPlantel, $anio, $semanaDesde, $semanaHasta, $modo);
    hay_json_response(['status' => 'ok', 'actual' => $actual] + $data);
    exit;
}

if ($accion === 'marcar_movimiento') {
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);
    $tipo = trim($_POST['tipo'] ?? '');
    $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
    $nota = trim($_POST['nota'] ?? '');

    if ($idAlumno <= 0 || $idGrupo <= 0 || !in_array($tipo, ['B', 'FC'], true)) {
        hay_json_response(['status' => 'error', 'message' => 'Datos inválidos']);
        exit;
    }

    $ok = reporte_semanal_registrar_movimiento(
        $pdo,
        $idPlantel,
        $idAlumno,
        $idGrupo,
        $tipo,
        $fecha,
        null,
        $nota ?: null,
        (int) $_SESSION['user_id'],
        'manual'
    );

    if ($tipo === 'FC') {
        $pdo->prepare(
            'UPDATE alumno_grupos SET activo = 0, fecha_baja = ? WHERE id_alumno = ? AND id_grupo = ?'
        )->execute([$fecha, $idAlumno, $idGrupo]);
    }

    hay_json_response([
        'status' => $ok ? 'ok' : 'error',
        'message' => $ok ? 'Movimiento registrado' : 'No se pudo registrar',
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
