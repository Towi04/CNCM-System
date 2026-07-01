<?php

declare(strict_types=1);
require __DIR__ . '/../config.php';



$action = $_GET['action'] ?? $_POST['action'] ?? '';



function inscripcion_wizard_auth(): bool

{

    return preregistro_puede_acceder() || alumno_puede_ver();

}

function inscripcion_wizard_puede_inscribir(): bool

{

    return preregistro_puede_cobrar() || alumno_puede_ver();

}



if (!inscripcion_wizard_auth()) {

    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);

    exit;

}



$idPlantel = plantel_id_activo();



if ($action === 'iniciar_desde_prereg') {

    if (!inscripcion_wizard_puede_inscribir()) {
        hay_json_response(['status' => 'error', 'message' => 'Los asesores no pueden inscribir ni cobrar; use recepción.'], 403);
        exit;
    }

    $idPrereg = (int) ($_POST['id_preregistro'] ?? $_GET['id_preregistro'] ?? 0);

    if ($idPrereg <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Pre-registro inválido']);

        exit;

    }

    try {
        inscripcion_flow_ensure_schema($pdo);
        $res = inscripcion_flow_iniciar_desde_prereg($pdo, $idPrereg, $idPlantel);
        hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    } catch (Throwable $e) {
        error_log('inscripcion_wizard iniciar_desde_prereg: ' . $e->getMessage());
        hay_json_response(['status' => 'error', 'message' => 'Error al preparar inscripción. Revise especialidad y datos del prospecto.'], 500);
    }

    exit;

}



if ($action === 'resumen') {

    $idAlumno = (int) ($_GET['id_alumno'] ?? $_POST['id_alumno'] ?? 0);

    $idEsp = (int) ($_GET['id_especialidad'] ?? $_POST['id_especialidad'] ?? 0) ?: null;

    $res = inscripcion_resumen_alumno($pdo, $idAlumno, $idEsp);

    hay_json_response(array_merge(
        ['status' => $res['ok'] ? 'ok' : 'error'],
        $res,
        function_exists('inscripcion_asistente_meta_autorizacion') ? inscripcion_asistente_meta_autorizacion() : []
    ));

    exit;

}



if ($action === 'reglas_descuento') {
    hay_json_response([
        'status' => 'ok',
        'reglas' => function_exists('combo_listar_para_inscripcion')
            ? combo_listar_para_inscripcion($pdo)
            : [],
    ]);
    exit;
}

if ($action === 'regla_descuento_detalle') {
    $idRegla = (int) ($_GET['id_regla'] ?? 0);
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    $idAlumno = (int) ($_GET['id_alumno'] ?? 0);
    if ($idRegla <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Regla inválida']);
        exit;
    }
    $det = function_exists('combo_detalle_para_inscripcion')
        ? combo_detalle_para_inscripcion($pdo, $idRegla, $idEsp, $idAlumno)
        : null;
    if (!$det) {
        hay_json_response(['status' => 'error', 'message' => 'Regla no encontrada']);
        exit;
    }
    hay_json_response(['status' => 'ok', 'detalle' => $det]);
    exit;
}

if ($action === 'resumen_descuento') {
    $idAlumno = (int) ($_GET['id_alumno'] ?? $_POST['id_alumno'] ?? 0);
    $idEsp = (int) ($_GET['id_especialidad'] ?? $_POST['id_especialidad'] ?? 0);
    $idRegla = (int) ($_GET['id_regla'] ?? $_POST['id_regla'] ?? 0);
    if ($idAlumno <= 0 || $idEsp <= 0 || $idRegla <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Alumno, especialidad y regla son obligatorios']);
        exit;
    }
    try {
        $res = combo_resumen_descuento_preview($pdo, $idAlumno, $idEsp, $idRegla);
        hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    } catch (Throwable $e) {
        hay_json_response(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'grupo_asesoria_meta') {
    $idGrupo = (int) ($_GET['id_grupo'] ?? $_POST['id_grupo'] ?? 0);
    $idAlumno = (int) ($_GET['id_alumno'] ?? $_POST['id_alumno'] ?? 0);
    if ($idGrupo <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Grupo inválido']);
        exit;
    }
    asesoria_ensure_schema($pdo);
    $meta = inscripcion_asesoria_meta_grupo($pdo, $idGrupo, $idAlumno);
    hay_json_response(array_merge(['status' => 'ok'], $meta));
    exit;
}

if ($action === 'grupos_disponibles') {

    $idAlumno = (int) ($_GET['id_alumno'] ?? $_POST['id_alumno'] ?? 0);

    $idEsp = (int) ($_GET['id_especialidad'] ?? $_POST['id_especialidad'] ?? 0);

    if ($idAlumno <= 0 || $idEsp <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Alumno y especialidad son obligatorios']);

        exit;

    }

    try {
        inscripcion_flow_ensure_schema($pdo);
        $grupos = inscripcion_grupos_disponibles($pdo, $idAlumno, $idEsp, $idPlantel);
        hay_json_response(['status' => 'ok', 'grupos' => $grupos]);
    } catch (Throwable $e) {
        error_log('inscripcion_wizard grupos_disponibles: ' . $e->getMessage());
        hay_json_response([
            'status' => 'error',
            'message' => 'No se pudieron cargar los grupos: ' . $e->getMessage(),
        ], 500);
    }

    exit;

}



if ($action === 'registrar_pago') {

    if (!inscripcion_wizard_puede_inscribir()) {
        hay_json_response(['status' => 'error', 'message' => 'Los asesores no pueden registrar cobros de inscripción.'], 403);
        exit;
    }

    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);

    $idEsp = (int) ($_POST['id_especialidad'] ?? 0);

    $monto = catalog_money($_POST['monto'] ?? 0);

    $formaPago = trim((string) ($_POST['forma_pago'] ?? 'Efectivo'));

    $idAe = (int) ($_POST['id_alumno_especialidad'] ?? 0);



    if ($idAlumno <= 0 || $monto <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Alumno y monto son obligatorios']);

        exit;

    }



    $saldo = inscripcion_saldo_pendiente($pdo, $idAlumno, $idEsp);

    if ($saldo <= 0.009) {

        hay_json_response(['status' => 'error', 'message' => 'La inscripción ya está cubierta']);

        exit;

    }



    if ($monto > $saldo + 0.01) {

        hay_json_response([

            'status' => 'error',

            'message' => 'El monto no puede exceder el saldo de inscripción ($' . number_format($saldo, 2) . ')',

        ]);

        exit;

    }



    $res = pago_registrar($pdo, [

        'id_alumno' => $idAlumno,

        'id_especialidad' => $idEsp ?: null,

        'id_alumno_especialidad' => $idAe ?: null,

        'tipo' => 'inscripcion',

        'monto' => $monto,

        'concepto' => 'Inscripción — asistente',

        'forma_pago_efectivo' => $formaPago,

    ]);



    if (!$res['ok']) {

        hay_json_response(['status' => 'error', 'message' => $res['message'] ?? 'No se pudo registrar el pago']);

        exit;

    }

    if ($idAe > 0 && $idEsp > 0) {
        pago_actualizar_inscripcion_cubierta($pdo, $idAlumno, $idEsp, $idAe);
    }

    $resumen = inscripcion_resumen_alumno($pdo, $idAlumno, $idEsp);

    hay_json_response([

        'status' => 'ok',

        'message' => 'Pago registrado',

        'pago' => $res,

        'resumen' => $resumen,

    ]);

    exit;

}

if ($action === 'examenes_ubicacion') {

    $idEsp = (int) ($_GET['id_especialidad'] ?? $_POST['id_especialidad'] ?? 0);

    if ($idEsp <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Especialidad inválida']);

        exit;

    }

    try {
        if (function_exists('ubicacion_examen_ensure_schema')) {
            ubicacion_examen_ensure_schema($pdo);
        }
        $items = function_exists('ubicacion_examen_listar')
            ? ubicacion_examen_listar($pdo, $idEsp, true)
            : [];
        hay_json_response(['status' => 'ok', 'examenes' => $items]);
    } catch (Throwable $e) {
        hay_json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
    }

    exit;

}



if ($action === 'confirmar_grupo') {

    if (!inscripcion_wizard_puede_inscribir()) {
        hay_json_response(['status' => 'error', 'message' => 'Los asesores no pueden inscribir ni cobrar; use recepción.'], 403);
        exit;
    }

    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);

    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);

    $idPrereg = (int) ($_POST['id_preregistro'] ?? 0);



    if ($idAlumno <= 0 || $idGrupo <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Alumno y grupo son obligatorios']);

        exit;

    }



    $esCursoPersonalizadoConfirm = !empty($_POST['es_curso_personalizado']);
    $res = inscripcion_flow_confirmar_grupo(
        $pdo,
        $idAlumno,
        $idGrupo,
        $idPrereg > 0 ? $idPrereg : null,
        $esCursoPersonalizadoConfirm
    );

    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));

    exit;

}



if ($action === 'completar_inscripcion') {

    if (!inscripcion_wizard_puede_inscribir()) {
        hay_json_response(['status' => 'error', 'message' => 'Los asesores no pueden inscribir ni cobrar; use recepción.'], 403);
        exit;
    }

    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);

    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);

    $idPrereg = (int) ($_POST['id_preregistro'] ?? 0);

    $monto = catalog_money($_POST['monto'] ?? 0);

    $formaPago = trim((string) ($_POST['forma_pago'] ?? 'Efectivo'));

    $esCursoPersonalizado = !empty($_POST['es_curso_personalizado']);

    $esUbicacion = !empty($_POST['es_ubicacion']);

    $idExamenUb = (int) ($_POST['id_examen_ubicacion'] ?? 0);

    $idEspPost = (int) ($_POST['id_especialidad'] ?? 0);



    if ($idAlumno <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Alumno inválido']);

        exit;

    }

    if ($esUbicacion) {
        if ($idEspPost <= 0) {
            hay_json_response(['status' => 'error', 'message' => 'Especialidad inválida']);
            exit;
        }
        if ($idExamenUb <= 0) {
            hay_json_response(['status' => 'error', 'message' => 'Seleccione el examen de ubicación']);
            exit;
        }
        if ($esCursoPersonalizado) {
            hay_json_response(['status' => 'error', 'message' => 'Ubicación no aplica a curso personalizado']);
            exit;
        }

        $idReferidorUb = 0;
        if (!empty($_POST['es_referido']) && (int) ($_POST['id_alumno_referidor'] ?? 0) > 0) {
            $idReferidorUb = (int) $_POST['id_alumno_referidor'];
        }
        $idReglaDescUb = (int) ($_POST['id_regla_colegiatura'] ?? 0);

        try {
            inscripcion_flow_ensure_schema($pdo);
            if (function_exists('ubicacion_examen_ensure_schema')) {
                ubicacion_examen_ensure_schema($pdo);
            }
            $res = inscripcion_flow_completar_ubicacion(
                $pdo,
                $idAlumno,
                $idEspPost,
                $idExamenUb,
                $idPrereg > 0 ? $idPrereg : null,
                $monto,
                $formaPago,
                $idReferidorUb > 0 ? $idReferidorUb : null,
                $idReglaDescUb > 0 ? $idReglaDescUb : null
            );
            hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
        } catch (Throwable $e) {
            error_log('inscripcion_wizard completar_ubicacion: ' . $e->getMessage());
            hay_json_response(['status' => 'error', 'message' => 'Error al inscribir por ubicación: ' . $e->getMessage()], 500);
        }
        exit;
    }

    $montoPersonalizado = null;
    if ($esCursoPersonalizado) {
        $montoPersonalizado = catalog_money($_POST['monto_personalizado'] ?? $_POST['monto'] ?? 0);
        if ($montoPersonalizado <= 0) {
            hay_json_response(['status' => 'error', 'message' => 'Indique el costo acordado del curso personalizado']);
            exit;
        }
        $resGrupo = inscripcion_resolver_grupo_personalizado(
            $pdo,
            $idAlumno,
            $idEspPost,
            $idPlantel,
            $montoPersonalizado,
            $idGrupo > 0 ? $idGrupo : null
        );
        if (!$resGrupo['ok']) {
            hay_json_response(array_merge(['status' => 'error'], $resGrupo));
            exit;
        }
        $idGrupo = (int) $resGrupo['id_grupo'];
        // $monto = neto a cobrar (con apartado descontado); no sobrescribir con el costo bruto.
    } elseif ($idGrupo <= 0) {

        hay_json_response(['status' => 'error', 'message' => 'Seleccione un grupo']);

        exit;

    }



    $idReferidor = 0;
    if (!empty($_POST['es_referido']) && (int) ($_POST['id_alumno_referidor'] ?? 0) > 0) {
        $idReferidor = (int) $_POST['id_alumno_referidor'];
    }

    if ($montoPersonalizado === null && !empty($_POST['monto_personalizado'])) {
        $montoPersonalizado = catalog_money($_POST['monto_personalizado']);
    }

    $idReglaDesc = (int) ($_POST['id_regla_colegiatura'] ?? 0);

    $edadAuth = ['requiere' => false, 'mensaje' => ''];
    if ($idAlumno > 0 && $idGrupo > 0 && function_exists('inscripcion_edad_requiere_autorizacion_grupo')) {
        $edadAuth = inscripcion_edad_requiere_autorizacion_grupo($pdo, $idAlumno, $idGrupo);
    }
    if (!$edadAuth['requiere'] && $idPrereg > 0) {
        $stPr = $pdo->prepare('SELECT * FROM preregistros WHERE id_preregistro = ? AND id_plantel = ? LIMIT 1');
        $stPr->execute([$idPrereg, $idPlantel]);
        $prRow = $stPr->fetch(PDO::FETCH_ASSOC);
        if ($prRow && function_exists('preregistro_edad_requiere_autorizacion')) {
            $edadAuth = preregistro_edad_requiere_autorizacion($pdo, $prRow);
        }
    }
    if ($edadAuth['requiere']) {
        if (!function_exists('inscripcion_verificar_y_aprobar_edad')) {
            require_once __DIR__ . '/inscripcion_protocolo_helper.php';
        }
        $auth = inscripcion_verificar_y_aprobar_edad(
            $pdo,
            $idAlumno,
            $idGrupo,
            trim((string) ($_POST['usuario_autoriza'] ?? '')),
            (string) ($_POST['password_autoriza'] ?? ''),
            $idPrereg > 0 ? $idPrereg : null,
            (string) ($edadAuth['mensaje'] ?? '')
        );
        if (!$auth['ok']) {
            hay_json_response([
                'status' => 'error',
                'message' => $auth['message'] ?? 'Se requiere autorización por edad',
                'requiere_autorizacion_edad' => true,
                'edad_mensaje' => $edadAuth['mensaje'],
            ]);
            exit;
        }
    }

    $gruposCombo = [];
    $rawGruposCombo = $_POST['grupos_combo'] ?? '[]';
    if (is_string($rawGruposCombo) && $rawGruposCombo !== '') {
        $gruposCombo = json_decode($rawGruposCombo, true) ?: [];
    } elseif (is_array($rawGruposCombo)) {
        $gruposCombo = $rawGruposCombo;
    }

    try {
        inscripcion_flow_ensure_schema($pdo);
        $res = inscripcion_flow_completar(
            $pdo,
            $idAlumno,
            $idGrupo,
            $idPrereg > 0 ? $idPrereg : null,
            $monto,
            $formaPago,
            $idReferidor > 0 ? $idReferidor : null,
            $montoPersonalizado,
            $idReglaDesc > 0 ? $idReglaDesc : null,
            $gruposCombo,
            $esCursoPersonalizado
        );
        if ($res['ok'] && function_exists('inscripcion_asesoria_aplicar_semana_extra')) {
            $sem = inscripcion_asesoria_aplicar_semana_extra($pdo, $idAlumno, $idGrupo, [
                'asesoria_semana_extra' => !empty($_POST['asesoria_semana_extra']),
                'asesoria_exonerar_semana' => !empty($_POST['asesoria_exonerar_semana']),
                'forma_pago' => $formaPago,
            ]);
            if (!$sem['ok']) {
                $res['asesoria_semana_error'] = $sem['message'] ?? 'No se cobró semana extra';
            } elseif (!empty($sem['id_pago_semana'])) {
                $res['asesoria_semana_extra'] = $sem;
            }
        }
        if ($res['ok'] && function_exists('inscripcion_asesoria_aplicar_regularizacion')) {
            inscripcion_asesoria_aplicar_regularizacion($pdo, $idAlumno, $idGrupo, [
                'asesoria_horas_regularizacion' => (float) ($_POST['asesoria_horas_regularizacion'] ?? 0),
                'asesoria_notas' => $_POST['asesoria_notas'] ?? '',
            ]);
            $horasReg = (float) ($_POST['asesoria_horas_regularizacion'] ?? 0);
            if ($horasReg > 0) {
                $res['asesoria_creditos'] = $horasReg;
            }
        }
        hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    } catch (Throwable $e) {
        error_log('inscripcion_wizard completar_inscripcion: ' . $e->getMessage());
        hay_json_response([
            'status' => 'error',
            'message' => 'Error al completar inscripción: ' . $e->getMessage(),
        ], 500);
    }

    exit;

}



hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);

