<?php

require __DIR__ . '/../config.php';



if (empty($_SESSION['user_id'])) {

    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);

    exit;

}



$action = $_POST['action'] ?? $_GET['action'] ?? '';



try {

    if (function_exists('soporte_ensure_schema')) {

        soporte_ensure_schema($pdo);

    }

    if (function_exists('academico_ensure_schema')) {

        academico_ensure_schema($pdo);

    }



    if ($action === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {

        $res = soporte_enviar_reporte($pdo, [

            'id_usuario' => (int) $_SESSION['user_id'],

            'id_plantel' => plantel_scope_id($pdo),

            'tipo' => $_POST['tipo'] ?? 'error',

            'mensaje' => $_POST['mensaje'] ?? '',

        ], $_FILES);

        hay_json_response([

            'status' => $res['ok'] ? 'ok' : 'error',

            'message' => $res['message'],

            'id_reporte' => $res['id_reporte'] ?? null,

        ]);

        exit;

    }



    if ($action === 'mis_reportes') {

        $lista = soporte_listar_recientes($pdo, (int) $_SESSION['user_id'], 15);

        hay_json_response(['status' => 'ok', 'reportes' => $lista]);

        exit;

    }

} catch (Throwable $e) {

    error_log('soporte_api: ' . $e->getMessage());

    hay_json_response(['status' => 'error', 'message' => 'No se pudo procesar el reporte. Intente de nuevo.'], 500);

    exit;

}



hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);

