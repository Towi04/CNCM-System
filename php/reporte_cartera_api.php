<?php

declare(strict_types=1);



require __DIR__ . '/../config.php';



if (!isset($_SESSION['user_id']) || !reporte_cartera_puede_ver()) {

    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);

    exit;

}



$idPlantel = plantel_scope_id($pdo);

$accion = trim($_GET['accion'] ?? $_POST['accion'] ?? '');

$filtros = reporte_cartera_parse_filtros();



if ($accion === 'catalogo') {

    hay_json_response([

        'status' => 'ok',

        'catalogo' => reporte_cartera_filtros_catalogo($pdo, $idPlantel),

    ]);

    exit;

}



if ($accion === 'vencimientos') {

    $fecha = trim($_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d'));

    $data = reporte_cartera_vencimientos($pdo, $idPlantel, $fecha, $filtros);

    hay_json_response(['status' => 'ok'] + $data);

    exit;

}



if ($accion === 'proyeccion') {

    $modo = trim($_GET['modo'] ?? $_POST['modo'] ?? 'mes');

    if (!in_array($modo, ['dia', 'semana', 'mes'], true)) {

        $modo = 'mes';

    }

    $fechaRef = trim($_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d'));

    $data = reporte_cartera_proyeccion($pdo, $idPlantel, $modo, $fechaRef, $filtros);

    hay_json_response(['status' => 'ok'] + $data);

    exit;

}



hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);

