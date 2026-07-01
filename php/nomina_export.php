<?php

declare(strict_types=1);



require __DIR__ . '/../config.php';



if (!isset($_SESSION['user_id']) || !nomina_puede_gestionar()) {

    http_response_code(403);

    echo 'No autorizado';

    exit;

}



$idPlantel = plantel_scope_id($pdo);

$idLiq = (int) ($_GET['id_liquidacion'] ?? 0);

$modo = trim($_GET['modo'] ?? 'detalle');



$liq = nomina_obtener($pdo, $idLiq, $idPlantel);

if (!$liq) {

    http_response_code(404);

    echo 'Liquidación no encontrada';

    exit;

}



if ($modo === 'resumen') {

    $filas = [];

    foreach ($liq['por_usuario'] ?? [] as $u) {

        $filas[] = [

            'nombre' => $u['nombre'] ?? '',

            'rol' => $u['rol'] ?? '',

            'tipo_pago' => $u['tipo_pago'] ?? '',

            'subtotal' => round((float) ($u['subtotal'] ?? 0), 2),

        ];

    }

    nomina_enviar_csv($filas, [

        'nombre' => 'Nombre',

        'rol' => 'Rol',

        'tipo_pago' => 'Tipo pago',

        'subtotal' => 'Subtotal',

    ], 'nomina_resumen_' . $idLiq . '.csv');

    exit;

}



$filas = [];

foreach ($liq['lineas'] ?? [] as $ln) {

    $filas[] = [

        'nombre' => $ln['nombre_completo'] ?? '',

        'rol' => $ln['rol'] ?? '',

        'area_nombre' => $ln['area_nombre'] ?? '',

        'nivel_nombre' => $ln['nivel_nombre'] ?? '',

        'tipo_pago' => $ln['tipo_pago'] ?? '',

        'concepto' => $ln['concepto'] ?? '',

        'cantidad' => $ln['cantidad'] ?? '',

        'tarifa' => $ln['tarifa'] ?? '',

        'importe' => $ln['importe'] ?? '',

    ];

}



nomina_enviar_csv($filas, [

    'nombre' => 'Nombre',

    'rol' => 'Rol',

    'area_nombre' => 'Área HAY',

    'nivel_nombre' => 'Nivel',

    'tipo_pago' => 'Tipo pago',

    'concepto' => 'Concepto',

    'cantidad' => 'Cantidad',

    'tarifa' => 'Tarifa',

    'importe' => 'Importe',

], 'nomina_detalle_' . $idLiq . '.csv');

