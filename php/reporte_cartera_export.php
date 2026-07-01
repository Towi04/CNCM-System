<?php

declare(strict_types=1);



require __DIR__ . '/../config.php';



if (!isset($_SESSION['user_id']) || !reporte_cartera_puede_ver()) {

    http_response_code(403);

    echo 'No autorizado';

    exit;

}



$idPlantel = plantel_scope_id($pdo);

$tipo = trim($_GET['tipo'] ?? '');

$filtros = reporte_cartera_parse_filtros();

$fecha = trim($_GET['fecha'] ?? date('Y-m-d'));

$modo = trim($_GET['modo'] ?? 'mes');

if (!in_array($modo, ['dia', 'semana', 'mes'], true)) {

    $modo = 'mes';

}



if ($tipo === 'vencimientos') {

    $data = reporte_cartera_vencimientos($pdo, $idPlantel, $fecha, $filtros);

    $filas = $data['filas'] ?? [];

    reporte_cartera_enviar_csv($filas, [

        'semaforo_label' => 'Semáforo',

        'numero_control' => 'Control',

        'nombre' => 'Alumno',

        'telefono' => 'Teléfono',

        'grupo_clave' => 'Grupo',

        'esp_nombre' => 'Especialidad',

        'forma_pago' => 'Forma pago',

        'periodos_vencidos' => 'Periodos vencidos',

        'periodo_mas_antiguo' => 'Periodo más antiguo',

        'meses_atraso' => 'Meses atraso',

        'adeudo' => 'Adeudo',

    ], 'vencimientos_' . date('Y-m-d') . '.csv');

    exit;

}



if ($tipo === 'proyeccion') {

    $data = reporte_cartera_proyeccion($pdo, $idPlantel, $modo, $fecha, $filtros);

    $filas = $data['filas'] ?? [];

    reporte_cartera_enviar_csv($filas, [

        'numero_control' => 'Control',

        'nombre' => 'Alumno',

        'grupo_clave' => 'Grupo',

        'esp_nombre' => 'Especialidad',

        'forma_pago' => 'Forma pago',

        'monto_periodo' => 'Cargo del periodo',

        'adeudo_previo' => 'Adeudo previo',

        'monto_proyectado' => 'Proyección',

        'fecha_probable' => 'Fecha probable pago',

        'habito_dia' => 'Día habitual pago',

        'habito_confianza' => 'Confianza hábito',

    ], 'proyeccion_' . $modo . '_' . date('Y-m-d') . '.csv');

    exit;

}



http_response_code(400);

echo 'Tipo no válido. Use tipo=vencimientos o tipo=proyeccion';

