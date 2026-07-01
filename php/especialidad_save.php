<?php
require __DIR__ . '/../config.php';

if (!catalog_puede_administrar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hay_json_response(['status' => 'error', 'message' => 'Método inválido']);
    exit;
}

catalog_ensure_especialidad_operativo($pdo);
operativo_cncm_ensure_schema($pdo);
referido_ensure_schema($pdo);

$id = (int) ($_POST['id_especialidad'] ?? 0);
$clave = catalog_normalizar_clave((string) ($_POST['clave'] ?? ''));
$nombre = trim((string) ($_POST['nombre'] ?? ''));
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
$modalidad = (string) ($_POST['modalidad'] ?? 'regular');
$modalidadesValidas = array_keys(catalog_modalidades_etiquetas());
if (!in_array($modalidad, $modalidadesValidas, true)) {
    $modalidad = 'regular';
}

$duracionFase = max(1, (int) ($_POST['duracion_fase_semanas'] ?? 4));
$edadMin = ($_POST['edad_min'] ?? '') !== '' ? max(0, (int) $_POST['edad_min']) : null;
$edadMax = ($_POST['edad_max'] ?? '') !== '' ? max(0, (int) $_POST['edad_max']) : null;
if ($edadMin !== null && $edadMax !== null && $edadMax < $edadMin) {
    hay_json_response(['status' => 'error', 'message' => 'La edad máxima no puede ser menor que la mínima']);
    exit;
}

$puedeCostos = catalog_puede_editar_costos();
$existente = null;
if ($id > 0) {
    $stEx = $pdo->prepare('SELECT * FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $stEx->execute([$id]);
    $existente = $stEx->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($puedeCostos) {
    $inscripcionApoyo = catalog_money($_POST['costo_inscripcion_apoyo'] ?? $_POST['costo_inscripcion'] ?? 0);
    $inscripcionRef = catalog_money($_POST['costo_inscripcion_referencia'] ?? ($inscripcionApoyo * 2));
    $mensualidadApoyo = catalog_money($_POST['costo_mensualidad_apoyo'] ?? $_POST['costo_mensualidad'] ?? 0);
    $mensualidadRef = catalog_money($_POST['costo_mensualidad_referencia'] ?? ($mensualidadApoyo * 1.5));
    $prontoApoyo = catalog_money($_POST['costo_pronto_pago_apoyo'] ?? $_POST['costo_pronto_pago'] ?? 0);
    $prontoRef = catalog_money($_POST['costo_pronto_pago_referencia'] ?? ($prontoApoyo * 1.5));
    $semanalApoyo = catalog_money($_POST['costo_semanal_apoyo'] ?? $_POST['costo_semanal'] ?? 0);
    $semanalRef = catalog_money($_POST['costo_semanal_referencia'] ?? ($semanalApoyo * 1.5));
} elseif ($existente) {
    $inscripcionApoyo = catalog_money($existente['costo_inscripcion_apoyo'] ?? $existente['costo_inscripcion'] ?? 0);
    $inscripcionRef = catalog_money($existente['costo_inscripcion_referencia'] ?? 0);
    $mensualidadApoyo = catalog_money($existente['costo_mensualidad_apoyo'] ?? $existente['costo_mensualidad'] ?? 0);
    $mensualidadRef = catalog_money($existente['costo_mensualidad_referencia'] ?? 0);
    $prontoApoyo = catalog_money($existente['costo_pronto_pago_apoyo'] ?? $existente['costo_pronto_pago'] ?? 0);
    $prontoRef = catalog_money($existente['costo_pronto_pago_referencia'] ?? 0);
    $semanalApoyo = catalog_money($existente['costo_semanal_apoyo'] ?? $existente['costo_semanal'] ?? 0);
    $semanalRef = catalog_money($existente['costo_semanal_referencia'] ?? 0);
} else {
    $inscripcionApoyo = catalog_money($_POST['costo_inscripcion'] ?? 700);
    $inscripcionRef = $inscripcionApoyo * 2;
    $mensualidadApoyo = catalog_money($_POST['costo_mensualidad'] ?? 0);
    $mensualidadRef = $mensualidadApoyo * 1.5;
    $prontoApoyo = catalog_money($_POST['costo_pronto_pago'] ?? 0);
    $prontoRef = $prontoApoyo * 1.5;
    $semanalApoyo = catalog_money($_POST['costo_semanal'] ?? 0);
    $semanalRef = $semanalApoyo * 1.5;
}

$inscripcion = $inscripcionApoyo;
$mensualidad = $mensualidadApoyo;
$pronto = $prontoApoyo;
$semanal = $semanalApoyo;

$inscripcionPorCuat = isset($_POST['inscripcion_por_cuatrimestre']) ? 1 : 0;
$parcialesCuat = max(0, (int) ($_POST['parciales_por_cuatrimestre'] ?? 0));
$costoCuatrimestre = ($_POST['costo_cuatrimestre'] ?? '') !== '' ? catalog_money($_POST['costo_cuatrimestre']) : null;
$costoAnual = ($_POST['costo_anual'] ?? '') !== '' ? catalog_money($_POST['costo_anual']) : null;
if ($costoCuatrimestre !== null && $costoCuatrimestre <= 0) {
    $costoCuatrimestre = null;
}
if ($costoAnual !== null && $costoAnual <= 0) {
    $costoAnual = null;
}

$fechaInicio = trim((string) ($_POST['fecha_inicio_venta'] ?? '')) ?: null;
$fechaFin = trim((string) ($_POST['fecha_fin_venta'] ?? '')) ?: null;

$duracionMeses = max(1, (int) ($_POST['duracion_meses'] ?? 1));
$duracionSemanas = ($_POST['duracion_semanas'] ?? '') !== '' ? max(1, (int) $_POST['duracion_semanas']) : null;
$esFija = isset($_POST['es_fija']) ? 1 : 0;
$visible = isset($_POST['visible']) ? 1 : 0;
$activo = isset($_POST['activo']) ? 1 : 0;
$inscripcionAbierta = isset($_POST['inscripcion_abierta']) ? 1 : 0;
$orden = max(0, (int) ($_POST['orden'] ?? 0));
$referidoTipo = (string) ($_POST['referido_tipo'] ?? 'semana_colegiatura');
if (!in_array($referidoTipo, ['semana_colegiatura', 'monto_fijo', 'inscripcion_fija'], true)) {
    $referidoTipo = 'semana_colegiatura';
}
$referidoValor = ($_POST['referido_valor'] ?? '') !== '' ? catalog_money($_POST['referido_valor']) : null;

if ($modalidad === 'prep_escolarizada') {
    $inscripcionPorCuat = 1;
}

if ($clave === '' || $nombre === '') {
    hay_json_response(['status' => 'error', 'message' => 'Clave y nombre son obligatorios']);
    exit;
}

$cols = 'clave=?, nombre=?, descripcion=?, modalidad=?, duracion_fase_semanas=?,
         edad_min=?, edad_max=?, costo_inscripcion=?, costo_inscripcion_referencia=?, costo_inscripcion_apoyo=?,
         inscripcion_por_cuatrimestre=?, parciales_por_cuatrimestre=?,
         costo_mensualidad=?, costo_mensualidad_referencia=?, costo_mensualidad_apoyo=?,
         costo_pronto_pago=?, costo_pronto_pago_referencia=?, costo_pronto_pago_apoyo=?,
         costo_semanal=?, costo_semanal_referencia=?, costo_semanal_apoyo=?,
         costo_cuatrimestre=?, costo_anual=?, fecha_inicio_venta=?, fecha_fin_venta=?,
         duracion_meses=?, duracion_semanas=?, es_fija=?, visible=?, inscripcion_abierta=?, activo=?, orden=?,
         referido_tipo=?, referido_valor=?';
$params = [
    $clave, $nombre, $descripcion, $modalidad, $duracionFase,
    $edadMin, $edadMax, $inscripcion, $inscripcionRef, $inscripcionApoyo,
    $inscripcionPorCuat, $parcialesCuat,
    $mensualidad, $mensualidadRef, $mensualidadApoyo,
    $pronto, $prontoRef, $prontoApoyo,
    $semanal, $semanalRef, $semanalApoyo,
    $costoCuatrimestre, $costoAnual, $fechaInicio, $fechaFin,
    $duracionMeses, $duracionSemanas, $esFija, $visible, $inscripcionAbierta, $activo, $orden,
    $referidoTipo, $referidoValor,
];

$tarifaNueva = catalog_tarifa_snapshot_row([
    'costo_inscripcion' => $inscripcion,
    'costo_inscripcion_referencia' => $inscripcionRef,
    'costo_inscripcion_apoyo' => $inscripcionApoyo,
    'costo_mensualidad' => $mensualidad,
    'costo_mensualidad_referencia' => $mensualidadRef,
    'costo_mensualidad_apoyo' => $mensualidadApoyo,
    'costo_pronto_pago' => $pronto,
    'costo_pronto_pago_referencia' => $prontoRef,
    'costo_pronto_pago_apoyo' => $prontoApoyo,
    'costo_semanal' => $semanal,
    'costo_semanal_referencia' => $semanalRef,
    'costo_semanal_apoyo' => $semanalApoyo,
    'costo_cuatrimestre' => $costoCuatrimestre,
    'costo_anual' => $costoAnual,
    'referido_tipo' => $referidoTipo,
    'referido_valor' => $referidoValor,
]);

$histTarifa = ['registrado' => false, 'alumnos_congelados' => 0];

try {
    if ($id > 0) {
        $dup = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE clave = ? AND id_especialidad <> ? LIMIT 1');
        $dup->execute([$clave, $id]);
        if ($dup->fetchColumn()) {
            hay_json_response(['status' => 'error', 'message' => 'Esa clave ya existe']);
            exit;
        }
        if ($puedeCostos) {
            $histTarifa = catalog_registrar_cambio_tarifa(
                $pdo,
                $id,
                $tarifaNueva,
                trim((string) ($_POST['motivo_tarifa'] ?? '')) ?: 'Ajuste de tarifas'
            );
        }
        $stmt = $pdo->prepare('UPDATE especialidades SET ' . $cols . ' WHERE id_especialidad=?');
        $stmt->execute(array_merge($params, [$id]));
        if ($inscripcionAbierta) {
            foreach (plantel_list($pdo, false) as $pl) {
                preregistro_sync_alertas_apertura($pdo, (int) $pl['id_plantel']);
            }
        }
    } else {
        $dup = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE clave = ? LIMIT 1');
        $dup->execute([$clave]);
        if ($dup->fetchColumn()) {
            hay_json_response(['status' => 'error', 'message' => 'Esa clave ya existe']);
            exit;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO especialidades (
                clave, nombre, descripcion, modalidad, duracion_fase_semanas,
                edad_min, edad_max, costo_inscripcion, costo_inscripcion_referencia, costo_inscripcion_apoyo,
                inscripcion_por_cuatrimestre, parciales_por_cuatrimestre,
                costo_mensualidad, costo_mensualidad_referencia, costo_mensualidad_apoyo,
                costo_pronto_pago, costo_pronto_pago_referencia, costo_pronto_pago_apoyo,
                costo_semanal, costo_semanal_referencia, costo_semanal_apoyo,
                costo_cuatrimestre, costo_anual, fecha_inicio_venta, fecha_fin_venta,
                duracion_meses, duracion_semanas, es_fija, visible, inscripcion_abierta, activo, orden,
                referido_tipo, referido_valor
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($params);
        $id = (int) $pdo->lastInsertId();
    }

    if ($puedeCostos && $id > 0) {
        operativo_cncm_guardar_cartas($pdo, $id, $_POST);
    }

    $msg = 'Especialidad guardada';
    if ($id > 0 && !empty($histTarifa['registrado'])) {
        $n = (int) ($histTarifa['alumnos_congelados'] ?? 0);
        $msg .= '. Tarifas del catálogo actualizadas';
        $msg .= $n > 0
            ? " ({$n} alumno(s) activos conservan su colegiatura congelada al inscribirse)."
            : ' (sin alumnos activos con tarifa congelada aún).';
    }

    hay_json_response([
        'status' => 'ok',
        'message' => $msg,
        'seccion' => 'admin_especialidades',
        'id_especialidad' => $id,
        'tarifa_historial' => $histTarifa,
    ]);
} catch (PDOException $e) {
    hay_json_response(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
}
