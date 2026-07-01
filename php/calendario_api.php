<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !calendario_puede_ver_menu()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso para calendarios']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$anio = (int) ($_POST['anio'] ?? $_GET['anio'] ?? date('Y'));
$mes = (int) ($_POST['mes'] ?? $_GET['mes'] ?? date('n'));
$modelo = calendario_modelo_normalizar($_POST['modelo'] ?? $_GET['modelo'] ?? 'regular');

function calendario_api_requiere_modelo(string $modelo): void
{
    if (!calendario_puede_editar_modelo($modelo)) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso para este calendario']);
        exit;
    }
}

if ($action === 'modelos') {
    hay_json_response([
        'status' => 'ok',
        'modelos' => calendario_modelos_lectivos(),
        'editables' => calendario_modelos_editables_usuario(),
        'puede_admin' => calendario_puede_editar_administrativo(),
    ]);
    exit;
}

if ($action === 'listar') {
    calendario_api_requiere_modelo($modelo);
    $dias = [];
    try {
        $st = $pdo->prepare(
            'SELECT id, fecha, tipo, aplica_a, etiqueta, plantel_abierto, fecha_recuperacion
             FROM calendario_dia_lectivo
             WHERE anio = ? AND modelo = ? AND id_plantel IS NULL ORDER BY fecha'
        );
        $st->execute([$anio, $modelo]);
        $dias = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // tabla nueva
    }
    hay_json_response([
        'status' => 'ok',
        'anio' => $anio,
        'modelo' => $modelo,
        'publicado' => academico_calendario_anio_publicado($pdo, $anio, $modelo),
        'dias' => $dias,
        'sugerencias' => $modelo === 'regular' ? academico_calendario_sugerencias($anio) : [],
        'tipos' => calendario_tipos_etiquetas($modelo),
        'permite_recuperacion' => calendario_modelo_permite_recuperacion($modelo),
    ]);
    exit;
}

if ($action === 'mes_grid') {
    calendario_api_requiere_modelo($modelo);
    hay_json_response([
        'status' => 'ok',
        'anio' => $anio,
        'mes' => $mes,
        'modelo' => $modelo,
        'publicado' => academico_calendario_anio_publicado($pdo, $anio, $modelo),
        'dias' => calendario_dias_del_mes($pdo, $anio, $mes, null, $modelo),
        'tipos' => calendario_tipos_etiquetas($modelo),
        'colores' => calendario_tipos_colores(),
        'permite_recuperacion' => calendario_modelo_permite_recuperacion($modelo),
    ]);
    exit;
}

if ($action === 'guardar_dia') {
    calendario_api_requiere_modelo($modelo);
    $fecha = trim($_POST['fecha'] ?? '');
    $tipo = $_POST['tipo'] ?? 'sin_clase_abierto';
    $aplica = $_POST['aplica_a'] ?? 'todos';
    $etiqueta = trim($_POST['etiqueta'] ?? '');
    $fechaRec = trim($_POST['fecha_recuperacion'] ?? '');
    $plantelAbierto = !empty($_POST['plantel_abierto']) ? 1 : 0;

    $res = calendario_guardar_dia($pdo, $fecha, $tipo, $aplica, $etiqueta, $fechaRec ?: null, $plantelAbierto, null, $modelo);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
    ]);
    exit;
}

if ($action === 'guardar_rango') {
    calendario_api_requiere_modelo($modelo);
    $ini = trim($_POST['fecha_inicio'] ?? '');
    $fin = trim($_POST['fecha_fin'] ?? '');
    $res = calendario_guardar_rango(
        $pdo,
        $ini,
        $fin,
        $_POST['tipo'] ?? 'cierre_plantel',
        $_POST['aplica_a'] ?? 'todos',
        trim($_POST['etiqueta'] ?? 'Cierre vacacional'),
        !empty($_POST['plantel_abierto']) ? 1 : 0,
        null,
        $modelo
    );
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'eliminar_dia') {
    calendario_api_requiere_modelo($modelo);
    $id = (int) ($_POST['id'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '');
    if ($id > 0) {
        $pdo->prepare('DELETE FROM calendario_dia_lectivo WHERE id = ? AND modelo = ? AND id_plantel IS NULL')
            ->execute([$id, $modelo]);
    } elseif ($fecha !== '') {
        $pdo->prepare('DELETE FROM calendario_dia_lectivo WHERE fecha = ? AND modelo = ? AND id_plantel IS NULL')
            ->execute([$fecha, $modelo]);
    }
    hay_json_response(['status' => 'ok', 'message' => 'Día restaurado a lectivo normal']);
    exit;
}

if ($action === 'importar_sugerencias') {
    calendario_api_requiere_modelo($modelo);
    if ($modelo !== 'regular') {
        hay_json_response(['status' => 'error', 'message' => 'Sugerencias solo para calendario regular']);
        exit;
    }
    foreach (academico_calendario_sugerencias($anio) as $s) {
        $pa = (int) (($s['tipo'] ?? '') === 'sin_clase_abierto');
        calendario_guardar_dia(
            $pdo,
            $s['fecha'],
            $s['tipo'],
            $s['aplica_a'],
            $s['etiqueta'],
            null,
            $pa,
            null,
            $modelo
        );
    }
    $pdo->prepare(
        'INSERT INTO calendario_escolar_anio (anio, modelo, publicado) VALUES (?, ?, 0)
         ON DUPLICATE KEY UPDATE anio = anio'
    )->execute([$anio, $modelo]);
    hay_json_response(['status' => 'ok', 'message' => 'Sugerencias importadas al borrador']);
    exit;
}

if ($action === 'publicar') {
    calendario_api_requiere_modelo($modelo);
    $pdo->prepare(
        'INSERT INTO calendario_escolar_anio (anio, modelo, publicado, actualizado_por, actualizado_en)
         VALUES (?, ?, 1, ?, NOW())
         ON DUPLICATE KEY UPDATE publicado = 1, actualizado_por = VALUES(actualizado_por), actualizado_en = NOW()'
    )->execute([$anio, $modelo, (int) $_SESSION['user_id']]);
    $etiq = calendario_modelos_lectivos()[$modelo] ?? $modelo;
    hay_json_response(['status' => 'ok', 'message' => 'Calendario ' . $etiq . ' ' . $anio . ' publicado']);
    exit;
}

if ($action === 'mensaje_alumno') {
    $idAlumno = (int) ($_GET['id_alumno'] ?? $_POST['id_alumno'] ?? 0);
    $fecha = trim($_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d'));
    if ($idAlumno <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Alumno inválido']);
        exit;
    }
    $msg = calendario_mensaje_para_alumno($pdo, $idAlumno, $fecha, plantel_id_activo());
    hay_json_response(['status' => 'ok', 'mensaje' => $msg]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
