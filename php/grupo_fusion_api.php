<?php

declare(strict_types=1);



require __DIR__ . '/../config.php';



if (!isset($_SESSION['user_id']) || !grupo_fusion_puede_ver()) {

    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);

    exit;

}



$idPlantel = plantel_scope_id($pdo);

$idUsuario = (int) ($_SESSION['user_id'] ?? 0);

$accion = trim($_GET['accion'] ?? $_POST['accion'] ?? 'matriz');



if ($accion === 'especialidades') {

    hay_json_response([

        'status' => 'ok',

        'especialidades' => grupo_fusion_listar_especialidades($pdo, $idPlantel),

        'umbral_alumnos' => GRUPO_FUSION_MAX_ALUMNOS_RECOMENDADO,

        'puede_gestionar' => grupo_fusion_puede_gestionar(),

        'kids' => grupo_fusion_kids_config($pdo),

    ]);

    exit;

}



if ($accion === 'temario') {

    $idGrupo = (int) ($_GET['id_grupo'] ?? 0);

    if ($idGrupo <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Grupo no indicado']);

        exit;

    }

    hay_json_response([

        'status' => 'ok',

        'id_grupo' => $idGrupo,

        'temario' => grupo_fusion_temario_contexto($pdo, $idGrupo),

    ]);

    exit;

}



if ($accion === 'simular') {

    $modo = trim($_GET['modo'] ?? $_POST['modo'] ?? 'simple');

    if ($modo === 'kids_dual') {

        $idIngA = (int) ($_GET['id_grupo_ing_a'] ?? $_POST['id_grupo_ing_a'] ?? 0);

        $idIngB = (int) ($_GET['id_grupo_ing_b'] ?? $_POST['id_grupo_ing_b'] ?? 0);

        $idCompA = (int) ($_GET['id_grupo_comp_a'] ?? $_POST['id_grupo_comp_a'] ?? 0);

        $idCompB = (int) ($_GET['id_grupo_comp_b'] ?? $_POST['id_grupo_comp_b'] ?? 0);

        $idDestIng = (int) ($_GET['id_fase_destino_ing'] ?? $_POST['id_fase_destino_ing'] ?? 0) ?: null;

        $idDestComp = (int) ($_GET['id_fase_destino_comp'] ?? $_POST['id_fase_destino_comp'] ?? 0) ?: null;

        try {

            $res = grupo_fusion_simular_dual(

                $pdo,

                $idPlantel,

                $idIngA,

                $idIngB,

                $idCompA,

                $idCompB,

                $idDestIng,

                $idDestComp

            );

            if (!$res['ok']) {

                hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'No se pudo simular']);

                exit;

            }

            hay_json_response(['status' => 'ok', 'simulacion' => $res, 'puede_gestionar' => grupo_fusion_puede_gestionar()]);

        } catch (Throwable $e) {

            error_log('grupo_fusion_api simular_dual: ' . $e->getMessage());

            hay_json_response(['status' => 'error', 'message' => 'Error al simular fusión dual.'], 500);

        }

        exit;

    }



    $idA = (int) ($_GET['id_grupo_a'] ?? $_POST['id_grupo_a'] ?? 0);

    $idB = (int) ($_GET['id_grupo_b'] ?? $_POST['id_grupo_b'] ?? 0);

    $idDest = (int) ($_GET['id_fase_destino'] ?? $_POST['id_fase_destino'] ?? 0) ?: null;



    try {

        $res = grupo_fusion_simular($pdo, $idPlantel, $idA, $idB, $idDest);

        if (!$res['ok']) {

            hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'No se pudo simular']);

            exit;

        }

        hay_json_response(['status' => 'ok', 'simulacion' => $res, 'puede_gestionar' => grupo_fusion_puede_gestionar()]);

    } catch (Throwable $e) {

        error_log('grupo_fusion_api simular: ' . $e->getMessage());

        hay_json_response(['status' => 'error', 'message' => 'Error al simular la fusión.'], 500);

    }

    exit;

}



if ($accion === 'listar_planes') {

    $estado = trim($_GET['estado'] ?? '');

    $idEsp = (int) ($_GET['id_especialidad'] ?? 0) ?: null;

    try {

        $planes = grupo_fusion_listar_planes($pdo, $idPlantel, $estado !== '' ? $estado : null, $idEsp);

        hay_json_response([

            'status' => 'ok',

            'planes' => array_map('grupo_fusion_formatear_plan', $planes),

            'puede_gestionar' => grupo_fusion_puede_gestionar(),

        ]);

    } catch (Throwable $e) {

        error_log('grupo_fusion_api listar_planes: ' . $e->getMessage());

        hay_json_response(['status' => 'error', 'message' => 'Error al listar planes.'], 500);

    }

    exit;

}



if ($accion === 'obtener') {

    $idPlan = (int) ($_GET['id_fusion_plan'] ?? $_POST['id_fusion_plan'] ?? 0);

    if ($idPlan <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Plan no indicado']);

        exit;

    }

    try {

        $plan = grupo_fusion_obtener($pdo, $idPlantel, $idPlan);

        if (!$plan) {

            hay_json_response(['status' => 'error', 'message' => 'Plan no encontrado']);

            exit;

        }

        hay_json_response([

            'status' => 'ok',

            'plan' => $plan,

            'puede_gestionar' => grupo_fusion_puede_gestionar(),

        ]);

    } catch (Throwable $e) {

        error_log('grupo_fusion_api obtener: ' . $e->getMessage());

        hay_json_response(['status' => 'error', 'message' => 'Error al cargar el plan.'], 500);

    }

    exit;

}



$postAcciones = ['guardar', 'confirmar', 'activar', 'separar', 'cancelar', 'completar_pendiente'];

if (in_array($accion, $postAcciones, true)) {

    if (!grupo_fusion_puede_gestionar()) {

        hay_json_response(['status' => 'error', 'message' => 'Sin permiso para gestionar fusiones']);

        exit;

    }



    try {

        if ($accion === 'guardar') {

            $modo = trim($_POST['modo'] ?? 'simple');

            if ($modo === 'kids_dual') {

                $idIngA = (int) ($_POST['id_grupo_ing_a'] ?? 0);

                $idIngB = (int) ($_POST['id_grupo_ing_b'] ?? 0);

                $idCompA = (int) ($_POST['id_grupo_comp_a'] ?? 0);

                $idCompB = (int) ($_POST['id_grupo_comp_b'] ?? 0);

                $idResIng = (int) ($_POST['id_grupo_resultante_ing'] ?? 0);

                $idResComp = (int) ($_POST['id_grupo_resultante_comp'] ?? 0);

                $fechaPrev = trim($_POST['fecha_prevista'] ?? '') ?: null;

                $notas = trim($_POST['notas'] ?? '');

                $idDestIng = (int) ($_POST['id_fase_destino_ing'] ?? 0) ?: null;

                $idDestComp = (int) ($_POST['id_fase_destino_comp'] ?? 0) ?: null;



                $simDual = grupo_fusion_simular_dual(

                    $pdo,

                    $idPlantel,

                    $idIngA,

                    $idIngB,

                    $idCompA,

                    $idCompB,

                    $idDestIng,

                    $idDestComp

                );

                if (!$simDual['ok']) {

                    hay_json_response(['status' => 'error', 'message' => $simDual['message'] ?? 'Simulación dual inválida']);

                    exit;

                }



                $res = grupo_fusion_guardar_plan_dual(

                    $pdo,

                    $idPlantel,

                    $simDual,

                    $idResIng,

                    $idResComp,

                    $fechaPrev,

                    $notas,

                    $idUsuario

                );

                if (!$res['ok']) {

                    hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'No se pudo guardar']);

                    exit;

                }

                $idPlan = (int) ($res['id_fusion_plan_ing'] ?? 0);

                hay_json_response([

                    'status' => 'ok',

                    'message' => $res['message'] ?? 'Planes dual guardados',

                    'id_fusion_plan' => $idPlan,

                    'id_fusion_plan_ing' => $res['id_fusion_plan_ing'] ?? null,

                    'id_fusion_plan_comp' => $res['id_fusion_plan_comp'] ?? null,

                    'plan' => $idPlan > 0 ? grupo_fusion_obtener($pdo, $idPlantel, $idPlan) : null,

                ]);

                exit;

            }



            $idA = (int) ($_POST['id_grupo_a'] ?? 0);

            $idB = (int) ($_POST['id_grupo_b'] ?? 0);

            $idDest = (int) ($_POST['id_fase_destino'] ?? 0) ?: null;

            $idResultante = (int) ($_POST['id_grupo_resultante'] ?? 0);

            $fechaPrev = trim($_POST['fecha_prevista'] ?? '') ?: null;

            $notas = trim($_POST['notas'] ?? '');

            $idPlanExistente = (int) ($_POST['id_fusion_plan'] ?? 0) ?: null;



            $sim = grupo_fusion_simular($pdo, $idPlantel, $idA, $idB, $idDest);

            if (!$sim['ok']) {

                hay_json_response(['status' => 'error', 'message' => $sim['message'] ?? 'Simulación inválida']);

                exit;

            }



            $res = grupo_fusion_guardar_plan(

                $pdo,

                $idPlantel,

                $sim,

                $idResultante,

                $fechaPrev,

                $notas,

                $idUsuario,

                $idPlanExistente

            );

            if (!$res['ok']) {

                hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'No se pudo guardar']);

                exit;

            }

            $idPlan = (int) ($res['id_fusion_plan'] ?? 0);

            hay_json_response([

                'status' => 'ok',

                'message' => $res['message'] ?? 'Plan guardado',

                'id_fusion_plan' => $idPlan,

                'plan' => $idPlan > 0 ? grupo_fusion_obtener($pdo, $idPlantel, $idPlan) : null,

            ]);

            exit;

        }



        if ($accion === 'confirmar') {

            $idPlan = (int) ($_POST['id_fusion_plan'] ?? 0);

            $res = grupo_fusion_confirmar($pdo, $idPlantel, $idPlan, $idUsuario);

            if (!$res['ok']) {

                hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'No se pudo confirmar']);

                exit;

            }

            hay_json_response([

                'status' => 'ok',

                'message' => $res['message'],

                'plan' => grupo_fusion_obtener($pdo, $idPlantel, $idPlan),

            ]);

            exit;

        }



        if ($accion === 'activar') {

            $idPlan = (int) ($_POST['id_fusion_plan'] ?? 0);

            $res = grupo_fusion_activar($pdo, $idPlantel, $idPlan, $idUsuario);

            if (!$res['ok']) {

                hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'No se pudo activar']);

                exit;

            }

            hay_json_response([

                'status' => 'ok',

                'message' => $res['message'],

                'alumnos_movidos' => $res['alumnos_movidos'] ?? 0,

                'plan' => grupo_fusion_obtener($pdo, $idPlantel, $idPlan),

            ]);

            exit;

        }



        if ($accion === 'separar') {

            $idPlan = (int) ($_POST['id_fusion_plan'] ?? 0);

            $res = grupo_fusion_separar($pdo, $idPlantel, $idPlan, $idUsuario);

            if (!$res['ok']) {

                hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'No se pudo separar']);

                exit;

            }

            hay_json_response([

                'status' => 'ok',

                'message' => $res['message'],

                'alumnos_separados' => $res['alumnos_separados'] ?? 0,

                'plan' => grupo_fusion_obtener($pdo, $idPlantel, $idPlan),

            ]);

            exit;

        }



        if ($accion === 'cancelar') {

            $idPlan = (int) ($_POST['id_fusion_plan'] ?? 0);

            $res = grupo_fusion_cancelar($pdo, $idPlantel, $idPlan);

            if (!$res['ok']) {

                hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'No se pudo cancelar']);

                exit;

            }

            hay_json_response(['status' => 'ok', 'message' => $res['message']]);

            exit;

        }



        if ($accion === 'completar_pendiente') {

            $idPend = (int) ($_POST['id_pendiente'] ?? 0);

            $res = grupo_fusion_completar_pendiente($pdo, $idPlantel, $idPend);

            if (!$res['ok']) {

                hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'No se pudo completar']);

                exit;

            }

            hay_json_response(['status' => 'ok', 'message' => $res['message'] ?? 'Pendiente completado']);

            exit;

        }

    } catch (Throwable $e) {

        error_log('grupo_fusion_api ' . $accion . ': ' . $e->getMessage());

        hay_json_response(['status' => 'error', 'message' => 'Error en la operación.'], 500);

    }

    exit;

}



$filtros = [

    'id_especialidad' => (int) ($_GET['id_especialidad'] ?? 0),

    'id_profesor' => (int) ($_GET['id_profesor'] ?? 0),

    'q' => trim($_GET['q'] ?? ''),

    'estado' => trim($_GET['estado'] ?? 'activo'),

    'solo_recomendados' => !empty($_GET['solo_recomendados']),

];

$modoMatriz = trim($_GET['modo'] ?? 'simple');



try {

    if ($modoMatriz === 'kids_dual') {

        $dual = grupo_fusion_matriz_kids_dual($pdo, $idPlantel, $filtros);

        if (empty($dual['ok'])) {

            hay_json_response(['status' => 'error', 'message' => $dual['message'] ?? 'Modo dual no disponible']);

            exit;

        }

        hay_json_response([

            'status' => 'ok',

            'accion' => 'matriz',

            'modo' => 'kids_dual',

            'puede_gestionar' => grupo_fusion_puede_gestionar(),

            ...$dual,

        ]);

        exit;

    }



    $matriz = grupo_fusion_matriz($pdo, $idPlantel, $filtros);

    hay_json_response([

        'status' => 'ok',

        'accion' => 'matriz',

        'modo' => 'simple',

        'puede_gestionar' => grupo_fusion_puede_gestionar(),

        ...$matriz,

    ]);

} catch (Throwable $e) {

    error_log('grupo_fusion_api matriz: ' . $e->getMessage());

    hay_json_response(['status' => 'error', 'message' => 'Error al cargar la planilla de fusiones.'], 500);

}

