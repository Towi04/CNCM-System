<?php

/**
 * Flujo de inscripción: pago de inscripción antes de asignar grupo.
 */

function inscripcion_flow_ensure_schema(PDO $pdo): void
{
    plantel_ensure_column($pdo, 'preregistros', 'id_alumno_vinculado', 'INT UNSIGNED NULL', 'id_especialidad');
    if (function_exists('ventas_comision_ensure_schema')) {
        ventas_comision_ensure_schema($pdo);
    }
}

function inscripcion_flow_tx_commit(PDO $pdo, bool $ownTx): void
{
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->commit();
    }
}

function inscripcion_flow_tx_rollback(PDO $pdo, bool $ownTx): void
{
    if ($ownTx && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (PDOException $e) {
            // Transacción ya cerrada por capa interna; ignorar.
        }
    }
}

/** Suma pagada de inscripción vinculada al enrolamiento activo (evita pagos huérfanos de intentos anteriores). */
function inscripcion_monto_pagado_enrolamiento(
    PDO $pdo,
    int $idAlumno,
    int $idEspecialidad,
    ?int $idAe = null
): float {
    if ($idAlumno <= 0 || $idEspecialidad <= 0) {
        return 0.0;
    }

    if ($idAe === null || $idAe <= 0) {
        $st = $pdo->prepare(
            'SELECT id_alumno_especialidad FROM alumno_especialidades
             WHERE id_alumno = ? AND id_especialidad = ? AND activo = 1
             ORDER BY id_alumno_especialidad DESC LIMIT 1'
        );
        $st->execute([$idAlumno, $idEspecialidad]);
        $idAe = (int) $st->fetchColumn() ?: null;
    }

    if ($idAe > 0) {
        $pag = $pdo->prepare(
            "SELECT COALESCE(SUM(monto), 0) FROM alumno_pagos
             WHERE id_alumno = ? AND tipo = 'inscripcion' AND id_alumno_especialidad = ?" . pago_sql_filtro_activos()
        );
        $pag->execute([$idAlumno, $idAe]);
    } else {
        $pag = $pdo->prepare(
            "SELECT COALESCE(SUM(monto), 0) FROM alumno_pagos
             WHERE id_alumno = ? AND id_especialidad = ? AND tipo = 'inscripcion'
               AND id_alumno_especialidad IS NULL" . pago_sql_filtro_activos()
        );
        $pag->execute([$idAlumno, $idEspecialidad]);
    }

    return round((float) $pag->fetchColumn(), 2);
}

/** Enlaza apartados del pre-registro que quedaron sin id_alumno_especialidad. */
function inscripcion_vincular_apartados_pendientes(PDO $pdo, int $idAlumno, int $idEspecialidad, int $idAe): void
{
    if ($idAlumno <= 0 || $idEspecialidad <= 0 || $idAe <= 0) {
        return;
    }

    $pdo->prepare(
        "UPDATE alumno_pagos SET id_alumno_especialidad = ?
         WHERE id_alumno = ? AND id_especialidad = ? AND tipo = 'inscripcion'
           AND id_alumno_especialidad IS NULL AND concepto LIKE 'Apartado pre-registro%'"
    )->execute([$idAe, $idAlumno, $idEspecialidad]);
}

/**
 * Costo, pagado y saldo de inscripción para el enrolamiento activo.
 *
 * @return array{costo:float,pagado:float,saldo:float,id_alumno_especialidad:int}
 */
function inscripcion_estado_cobro(PDO $pdo, int $idAlumno, int $idEspecialidad): array
{
    if ($idAlumno <= 0 || $idEspecialidad <= 0) {
        return ['costo' => 0.0, 'pagado' => 0.0, 'saldo' => 0.0, 'id_alumno_especialidad' => 0];
    }

    pago_ensure_schema($pdo);

    $st = $pdo->prepare(
        'SELECT id_alumno_especialidad, costo_inscripcion
         FROM alumno_especialidades
         WHERE id_alumno = ? AND id_especialidad = ? AND activo = 1
         ORDER BY id_alumno_especialidad DESC LIMIT 1'
    );
    $st->execute([$idAlumno, $idEspecialidad]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $idAe = (int) ($row['id_alumno_especialidad'] ?? 0);

    if ($row) {
        $costo = (float) ($row['costo_inscripcion'] ?? 0);
    } else {
        $esp = $pdo->prepare('SELECT costo_inscripcion FROM especialidades WHERE id_especialidad = ? LIMIT 1');
        $esp->execute([$idEspecialidad]);
        $costo = (float) $esp->fetchColumn();
    }

    if ($idAe > 0) {
        inscripcion_vincular_apartados_pendientes($pdo, $idAlumno, $idEspecialidad, $idAe);
    }

    $pagado = inscripcion_monto_pagado_enrolamiento($pdo, $idAlumno, $idEspecialidad, $idAe > 0 ? $idAe : null);
    $saldo = max(0.0, round($costo - $pagado, 2));

    if ($idAe > 0) {
        pago_actualizar_inscripcion_cubierta($pdo, $idAlumno, $idEspecialidad, $idAe);
    }

    return [
        'costo' => $costo,
        'pagado' => $pagado,
        'saldo' => $saldo,
        'id_alumno_especialidad' => $idAe,
    ];
}

/** Saldo pendiente de inscripción para una especialidad (0 = cubierta). */
function inscripcion_saldo_pendiente(PDO $pdo, int $idAlumno, int $idEspecialidad): float
{
    if ($idAlumno <= 0 || $idEspecialidad <= 0) {
        return 0.0;
    }

    pago_ensure_schema($pdo);
    pago_asegurar_inscripcion_desde_alumno($pdo, $idAlumno);

    return inscripcion_estado_cobro($pdo, $idAlumno, $idEspecialidad)['saldo'];
}

/** Suma pagada de inscripción para una especialidad. */
function inscripcion_monto_pagado(PDO $pdo, int $idAlumno, int $idEspecialidad, ?int $idAe = null): float
{
    return inscripcion_monto_pagado_enrolamiento($pdo, $idAlumno, $idEspecialidad, $idAe);
}

/**
 * Crea o actualiza la colegiatura del alumno al momento de inscribir (con descuento si aplica).
 */
function inscripcion_preparar_tarifa_inscripcion(
    PDO $pdo,
    int $idAlumno,
    int $idEspecialidad,
    ?int $idReglaDescuento = null
): int {
    if ($idAlumno <= 0 || $idEspecialidad <= 0) {
        return 0;
    }
    pago_ensure_schema($pdo);

    $st = $pdo->prepare(
        'SELECT id_alumno_especialidad FROM alumno_especialidades
         WHERE id_alumno = ? AND id_especialidad = ?
         ORDER BY id_alumno_especialidad DESC LIMIT 1'
    );
    $st->execute([$idAlumno, $idEspecialidad]);
    $idAe = (int) $st->fetchColumn();

    if ($idAe <= 0) {
        $idAe = pago_crear_inscripcion($pdo, $idAlumno, $idEspecialidad, 'mensual', date('Y-m-d'), true);
    }

    if ($idAe > 0 && $idReglaDescuento > 0) {
        if (function_exists('combo_alumno_establecer_regla_pref')) {
            combo_alumno_establecer_regla_pref($pdo, $idAlumno, $idReglaDescuento);
        }
        if (function_exists('combo_aplicar_regla_id')) {
            combo_aplicar_regla_id($pdo, $idAlumno, $idReglaDescuento, true);
        }
        if (function_exists('combo_aplicar_tarifa_regla_especialidad')) {
            combo_aplicar_tarifa_regla_especialidad($pdo, $idAlumno, $idEspecialidad, $idReglaDescuento);
        }
        pago_actualizar_inscripcion_cubierta($pdo, $idAlumno, $idEspecialidad, $idAe);
    }

    return $idAe;
}

/** Resumen de inscripción para el asistente. */
function inscripcion_resumen_alumno(PDO $pdo, int $idAlumno, ?int $idEspecialidad = null): array
{
    $idPlantel = plantel_id_activo();
    $al = alumno_obtener($pdo, $idAlumno, $idPlantel);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    if ($idEspecialidad === null || $idEspecialidad <= 0) {
        $idEspecialidad = (int) ($al['id_especialidad'] ?? 0) ?: null;
    }

    if (!$idEspecialidad) {
        return ['ok' => false, 'message' => 'El alumno no tiene especialidad definida'];
    }

    $esProspecto = function_exists('alumno_es_prospecto') && alumno_es_prospecto($al);
    if (!$esProspecto) {
        pago_asegurar_inscripcion_desde_alumno($pdo, $idAlumno);
    }
    $inscs = pago_inscripciones_alumno($pdo, $idAlumno);
    $insRow = null;
    foreach ($inscs as $ins) {
        if ((int) $ins['id_especialidad'] === $idEspecialidad) {
            $insRow = $ins;
            break;
        }
    }

    $esp = $pdo->prepare('SELECT nombre, clave, costo_inscripcion FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $esp->execute([$idEspecialidad]);
    $espMeta = $esp->fetch(PDO::FETCH_ASSOC) ?: [];

    $costoInsc = (float) ($insRow['costo_inscripcion'] ?? $espMeta['costo_inscripcion'] ?? 0);
    $idAe = (int) ($insRow['id_alumno_especialidad'] ?? 0);

    $estadoCobro = inscripcion_estado_cobro($pdo, $idAlumno, $idEspecialidad);
    if ($idAe <= 0) {
        $idAe = (int) ($estadoCobro['id_alumno_especialidad'] ?? 0);
    }
    $costoInsc = (float) ($estadoCobro['costo'] ?? $costoInsc);
    $pagadoInsc = (float) ($estadoCobro['pagado'] ?? 0);
    $saldo = (float) ($estadoCobro['saldo'] ?? 0);

    pago_sync_inscripcion_global($pdo, $idAlumno);
    $montoApartado = 0.0;
    if (!empty($al['id_preregistro'])) {
        $prSt = $pdo->prepare(
            'SELECT monto_apartado, tiene_apartado, folio_apartado FROM preregistros WHERE id_preregistro = ? LIMIT 1'
        );
        $prSt->execute([(int) $al['id_preregistro']]);
        $prRow = $prSt->fetch(PDO::FETCH_ASSOC);
        if ($prRow && (int) ($prRow['tiene_apartado'] ?? 0)) {
            $montoApartado = (float) ($prRow['monto_apartado'] ?? 0);
        }
    }
    $apSt = $pdo->prepare(
        "SELECT COALESCE(SUM(monto), 0) FROM alumno_pagos
         WHERE id_alumno = ? AND id_especialidad = ? AND tipo = 'inscripcion'
           AND concepto LIKE 'Apartado pre-registro%'" . pago_sql_filtro_activos()
    );
    $apSt->execute([$idAlumno, $idEspecialidad]);
    $apartadoAplicado = (float) $apSt->fetchColumn();

    $nombre = trim(
        ($al['nombres'] ?? $al['nombre'] ?? '') . ' '
        . ($al['apellido_paterno'] ?? $al['apellido'] ?? '')
    );

    $creditoApartado = ($apartadoAplicado <= 0.009 && $montoApartado > 0) ? $montoApartado : 0.0;
    $saldoMostrar = $saldo;
    if ($creditoApartado > 0 && $saldoMostrar > 0) {
        $saldoMostrar = max(0.0, round($saldoMostrar - $creditoApartado, 2));
    }

    return [
        'ok' => true,
        'id_alumno' => $idAlumno,
        'id_especialidad' => $idEspecialidad,
        'numero_control' => $al['numero_control'] ?? '',
        'nombre' => $nombre,
        'especialidad' => $espMeta['nombre'] ?? '',
        'costo_inscripcion' => $costoInsc,
        'pagado_inscripcion' => $pagadoInsc,
        'monto_apartado' => $montoApartado,
        'apartado_aplicado' => $apartadoAplicado,
        'credito_apartado_pendiente' => $creditoApartado,
        'saldo_inscripcion' => $saldoMostrar,
        'saldo_inscripcion_bruto' => $saldo,
        'inscripcion_cubierta' => $saldoMostrar <= 0.009,
        'es_prospecto' => $esProspecto,
        'id_alumno_especialidad' => (int) ($insRow['id_alumno_especialidad'] ?? 0),
    ];
}

function inscripcion_puede_asignar_grupo(PDO $pdo, int $idAlumno, int $idEspecialidad): bool
{
    if ($idAlumno <= 0 || $idEspecialidad <= 0) {
        return false;
    }

    return inscripcion_saldo_pendiente($pdo, $idAlumno, $idEspecialidad) <= 0.009;
}

/** Grupo de curso personalizado (no exige cobro de inscripción regular). */
function inscripcion_grupo_es_personalizado(PDO $pdo, int $idGrupo): bool
{
    if ($idGrupo <= 0) {
        return false;
    }

    $st = $pdo->prepare(
        'SELECT es_personalizado, codigo_area, clave FROM grupos WHERE id_grupo = ? LIMIT 1'
    );
    $st->execute([$idGrupo]);
    $g = $st->fetch(PDO::FETCH_ASSOC);
    if (!$g) {
        return false;
    }

    if ((int) ($g['es_personalizado'] ?? 0) === 1) {
        return true;
    }

    $area = strtoupper(trim((string) ($g['codigo_area'] ?? '')));
    if ($area === 'PER') {
        return true;
    }

    $clave = strtoupper(trim((string) ($g['clave'] ?? '')));

    return str_starts_with($clave, 'PER-') || str_starts_with($clave, 'PER');
}

/**
 * Grupos disponibles agrupados para el asistente de inscripción.
 *
 * @return array<string, mixed>
 */
function inscripcion_grupos_disponibles(PDO $pdo, int $idAlumno, int $idEspecialidad, ?int $idPlantel = null): array
{
    $idPlantel = $idPlantel ?? plantel_id_activo();
    if ($idEspecialidad <= 0) {
        return [
            'proximos' => [],
            'en_curso' => [],
            'por_comenzar' => [],
            'ubicacion' => [],
            'otros' => [],
            'personalizados' => [],
            'ubicacion_pendiente' => false,
            'restringido' => false,
            'message' => 'Seleccione una especialidad',
        ];
    }

    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.fecha_inicio, g.id_fase_actual, g.es_personalizado,
                g.personalizado_costo_acordado, g.personalizado_descripcion,
                f.clave_fase, f.nombre_fase, e.nombre AS esp_nombre,
                COALESCE(e.es_plantilla_personalizado, 0) AS es_plantilla_personalizado
         FROM grupos g
         LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_plantel = ? AND g.id_especialidad = ?
         ORDER BY g.fecha_inicio ASC, g.clave ASC'
    );
    $st->execute([$idPlantel, $idEspecialidad]);
    $todos = $st->fetchAll(PDO::FETCH_ASSOC);

    $permitidos = ubicacion_grupos_permitidos_inscripcion($pdo, $idAlumno, $idEspecialidad);
    $ub = ubicacion_obtener_activa($pdo, $idAlumno, $idEspecialidad);
    $ubicacionPendiente = $ub && ($ub['estado'] ?? '') === 'pendiente';

    $hoy = date('Y-m-d');
    $proximos = [];
    $enCurso = [];
    $ubicacion = [];

    if ($ubicacionPendiente) {
        return [
            'por_comenzar' => [],
            'en_curso' => [],
            'ubicacion' => [],
            'otros' => [],
            'personalizados' => [],
            'ubicacion_pendiente' => true,
            'restringido' => true,
            'message' => 'Examen de ubicación pendiente. Coordinación debe autorizar grupos.',
        ];
    }

    foreach ($todos as $g) {
        if ((int) ($g['es_personalizado'] ?? 0) === 1) {
            continue;
        }
        $idG = (int) $g['id_grupo'];
        if (is_array($permitidos) && !in_array($idG, $permitidos, true)) {
            continue;
        }

        $fechaInicio = (string) ($g['fecha_inicio'] ?? '');
        $esUbicacion = is_array($permitidos) && in_array($idG, $permitidos, true);
        $yaInicio = $fechaInicio !== '' && $fechaInicio < $hoy;

        if ($esUbicacion) {
            $ubicacion[] = $g;
        } elseif ($yaInicio) {
            $enCurso[] = $g;
        } else {
            // Sin fecha o fecha hoy/futura = próximo a iniciar / programado
            $proximos[] = $g;
        }
    }

    // Compatibilidad con JS anterior
    $porComenzar = $proximos;
    $otros = $enCurso;

    $personalizados = [];
    foreach ($todos as $g) {
        if ((int) ($g['es_personalizado'] ?? 0) !== 1) {
            continue;
        }
        $idG = (int) $g['id_grupo'];
        if (is_array($permitidos) && !in_array($idG, $permitidos, true)) {
            continue;
        }
        $personalizados[] = $g;
    }

    return [
        'proximos' => $proximos,
        'en_curso' => $enCurso,
        'por_comenzar' => $porComenzar,
        'ubicacion' => $ubicacion,
        'otros' => $otros,
        'personalizados' => $personalizados,
        'ubicacion_pendiente' => false,
        'restringido' => $permitidos !== null,
        'total' => count($proximos) + count($enCurso) + count($ubicacion) + count($personalizados),
    ];
}

/** Código de área de grupo según clave de especialidad. */
function inscripcion_area_grupo_desde_especialidad(array $esp): string
{
    $clave = strtoupper(trim((string) ($esp['clave'] ?? '')));
    if (str_contains($clave, 'KID') || $clave === 'K') {
        return 'K';
    }
    if (str_contains($clave, 'COMP') || str_starts_with($clave, 'C')) {
        return 'C';
    }
    if (str_contains($clave, 'PA')) {
        return 'PA';
    }
    if (str_contains($clave, 'PE')) {
        return 'PE';
    }

    return 'I';
}

/**
 * Crea un grupo personalizado o valida uno existente para inscribir al alumno.
 *
 * @return array<string, mixed>
 */
function inscripcion_resolver_grupo_personalizado(
    PDO $pdo,
    int $idAlumno,
    int $idEspecialidad,
    int $idPlantel,
    float $costoAcordado,
    ?int $idGrupoExistente = null
): array {
    if ($idAlumno <= 0 || $idEspecialidad <= 0) {
        return ['ok' => false, 'message' => 'Alumno y especialidad son obligatorios'];
    }
    if ($costoAcordado <= 0) {
        return ['ok' => false, 'message' => 'Indique el costo acordado del curso personalizado'];
    }

    require_once __DIR__ . '/grupo_clave_helper.php';
    if (function_exists('ventas_comision_ensure_schema')) {
        ventas_comision_ensure_schema($pdo);
    }
    grupo_clave_ensure_schema($pdo);
    plantel_ensure_column($pdo, 'grupos', 'personalizado_costo_acordado', 'DECIMAL(10,2) NULL', 'personalizado_descripcion');

    if ($idGrupoExistente > 0) {
        $st = $pdo->prepare(
            'SELECT id_grupo, clave, id_especialidad, es_personalizado, personalizado_costo_acordado
             FROM grupos WHERE id_grupo = ? AND id_plantel = ? LIMIT 1'
        );
        $st->execute([$idGrupoExistente, $idPlantel]);
        $g = $st->fetch(PDO::FETCH_ASSOC);
        if (!$g || (int) ($g['es_personalizado'] ?? 0) !== 1) {
            return ['ok' => false, 'message' => 'El grupo seleccionado no es personalizado'];
        }
        if ((int) ($g['id_especialidad'] ?? 0) !== $idEspecialidad) {
            return ['ok' => false, 'message' => 'El grupo personalizado no corresponde a la especialidad del alumno'];
        }
        $costoGrupo = (float) ($g['personalizado_costo_acordado'] ?? 0);
        if ($costoGrupo > 0 && abs($costoGrupo - $costoAcordado) > 0.02) {
            return [
                'ok' => false,
                'message' => 'El costo debe ser ' . catalog_format_mxn($costoGrupo) . ' (colegiatura del grupo)',
                'costo_grupo' => $costoGrupo,
            ];
        }

        return [
            'ok' => true,
            'id_grupo' => (int) $g['id_grupo'],
            'clave' => $g['clave'] ?? '',
            'creado' => false,
        ];
    }

    $al = alumno_obtener($pdo, $idAlumno, $idPlantel);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    $espSt = $pdo->prepare('SELECT clave, nombre FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $espSt->execute([$idEspecialidad]);
    $esp = $espSt->fetch(PDO::FETCH_ASSOC) ?: [];

    $nc = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) ($al['numero_control'] ?? ''))));
    $nombrePer = $nc !== '' ? $nc : ('A' . $idAlumno);
    $area = inscripcion_area_grupo_desde_especialidad($esp);

    $faseSt = $pdo->prepare(
        'SELECT id_fase FROM especialidad_fases WHERE id_especialidad = ? ORDER BY orden ASC, id_fase ASC LIMIT 1'
    );
    $faseSt->execute([$idEspecialidad]);
    $idFase = (int) $faseSt->fetchColumn() ?: null;

    $nombreCompleto = trim(
        ($al['nombres'] ?? $al['nombre'] ?? '') . ' '
        . ($al['apellido_paterno'] ?? $al['apellido'] ?? '') . ' '
        . ($al['apellido_materno'] ?? '')
    );
    $desc = substr($nombreCompleto, 0, 200);

    $intentos = 0;
    $suffix = '';
    do {
        $gen = grupo_clave_generar($pdo, $idPlantel, $area, 'S', false, true, $nombrePer . $suffix);
        $clave = $gen['clave'];
        $dup = $pdo->prepare('SELECT id_grupo FROM grupos WHERE id_plantel = ? AND clave = ? LIMIT 1');
        $dup->execute([$idPlantel, $clave]);
        if (!$dup->fetchColumn()) {
            break;
        }
        $intentos++;
        $suffix = '-' . $intentos;
    } while ($intentos < 20);

    if ($intentos >= 20) {
        return ['ok' => false, 'message' => 'No se pudo generar una clave única para el grupo personalizado'];
    }

    $fechaInicio = date('Y-m-d');
    $stmt = $pdo->prepare(
        'INSERT INTO grupos (
            id_plantel, clave, fecha_inicio, id_profesor, aula, id_especialidad, id_fase_actual,
            horario_texto, codigo_area, codigo_horario, es_extensivo, es_personalizado, numero_secuencial,
            personalizado_descripcion, personalizado_costo_acordado
        ) VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?, NULL, 0, 1, ?, ?, ?)'
    );
    $stmt->execute([
        $idPlantel,
        $clave,
        $fechaInicio,
        $idEspecialidad,
        $idFase ?: null,
        'Personalizado — ' . ($esp['nombre'] ?? ''),
        $gen['codigo_area'],
        $gen['numero_secuencial'] ?: null,
        $desc,
        round($costoAcordado, 2),
    ]);

    $idGrupoNuevo = (int) $pdo->lastInsertId();
    if ($idGrupoNuevo > 0 && function_exists('grupo_apertura_inicializar')) {
        grupo_apertura_inicializar($pdo, $idGrupoNuevo);
    }

    return [
        'ok' => true,
        'id_grupo' => $idGrupoNuevo,
        'clave' => $clave,
        'creado' => true,
    ];
}

/**
 * Crea o reutiliza alumno desde pre-registro sin marcar prospecto como inscrito.
 *
 * @return array<string, mixed>
 */
function inscripcion_flow_iniciar_desde_prereg(PDO $pdo, int $idPreregistro, int $idPlantel): array
{
    inscripcion_flow_ensure_schema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM preregistros WHERE id_preregistro = ? AND id_plantel = ? LIMIT 1');
    $stmt->execute([$idPreregistro, $idPlantel]);
    $pr = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pr) {
        return ['ok' => false, 'message' => 'Pre-registro no encontrado'];
    }

    $edadAuth = preregistro_edad_requiere_autorizacion($pdo, $pr);

    if (!empty($pr['id_alumno_vinculado'])) {
        $idAlumno = (int) $pr['id_alumno_vinculado'];
        $alV = alumno_obtener($pdo, $idAlumno, $idPlantel);
        if ($alV && (!function_exists('alumno_es_prospecto') || !alumno_es_prospecto($alV))) {
            preregistro_aplicar_apartado_a_alumno($pdo, $idPreregistro);
        }
        $res = inscripcion_resumen_alumno($pdo, $idAlumno, (int) ($pr['id_especialidad'] ?? 0) ?: null);
        if (!$res['ok']) {
            return $res;
        }
        $res['id_preregistro'] = $idPreregistro;
        $res['ya_existia'] = true;
        $res['edad_requiere_autorizacion'] = $edadAuth['requiere'];
        $res['edad_autorizacion_mensaje'] = $edadAuth['mensaje'];
        if (function_exists('inscripcion_asistente_meta_autorizacion')) {
            $res = array_merge($res, inscripcion_asistente_meta_autorizacion());
        }

        return $res;
    }

    $ins = alumno_inscribir_desde_preregistro($pdo, $idPreregistro, false);
    if (!$ins['ok']) {
        return $ins;
    }

    $idAlumno = (int) $ins['id_alumno'];
    $res = inscripcion_resumen_alumno($pdo, $idAlumno, (int) ($pr['id_especialidad'] ?? 0) ?: null);
    $res['id_preregistro'] = $idPreregistro;
    $res['numero_control'] = $ins['numero_control'] ?? $res['numero_control'] ?? '';
    $res['edad_requiere_autorizacion'] = $edadAuth['requiere'];
    $res['edad_autorizacion_mensaje'] = $edadAuth['mensaje'];
    if (function_exists('inscripcion_asistente_meta_autorizacion')) {
        $res = array_merge($res, inscripcion_asistente_meta_autorizacion());
    }

    return $res;
}

/**
 * Confirma inscripción al grupo tras pago cubierto.
 *
 * @return array<string, mixed>
 */
function inscripcion_flow_confirmar_grupo(
    PDO $pdo,
    int $idAlumno,
    int $idGrupo,
    ?int $idPreregistro = null,
    bool $flujoPersonalizado = false
): array {
    $idPlantel = plantel_id_activo();

    if (!plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel)) {
        return ['ok' => false, 'message' => 'El grupo no pertenece a este plantel'];
    }

    $g = $pdo->prepare('SELECT id_especialidad, es_personalizado FROM grupos WHERE id_grupo = ? LIMIT 1');
    $g->execute([$idGrupo]);
    $grupoRow = $g->fetch(PDO::FETCH_ASSOC);
    $idEsp = (int) ($grupoRow['id_especialidad'] ?? 0);
    if ($idEsp <= 0) {
        return ['ok' => false, 'message' => 'Grupo sin especialidad'];
    }

    $esGrupoPersonalizado = $flujoPersonalizado || inscripcion_grupo_es_personalizado($pdo, $idGrupo);
    if (!$esGrupoPersonalizado && !inscripcion_puede_asignar_grupo($pdo, $idAlumno, $idEsp)) {
        return [
            'ok' => false,
            'message' => 'Debe cubrir la inscripción antes de asignar al grupo',
            'saldo' => inscripcion_saldo_pendiente($pdo, $idAlumno, $idEsp),
        ];
    }

    if (function_exists('inscripcion_protocolo_validar_grupo')) {
        $proto = inscripcion_protocolo_validar_grupo($pdo, $idAlumno, $idGrupo);
        if (!$proto['ok']) {
            return [
                'ok' => false,
                'message' => $proto['message'] ?? 'Requiere autorización académica',
                'requiere_auth' => !empty($proto['requiere_auth']),
                'tipo_auth' => $proto['tipo'] ?? null,
            ];
        }
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        if (function_exists('alumno_asignar_numero_control_inscripcion')) {
            alumno_asignar_numero_control_inscripcion($pdo, $idAlumno);
        }
        if (function_exists('usuario_crear_cuenta_alumno')) {
            $cuenta = usuario_crear_cuenta_alumno($pdo, $idAlumno, $idPlantel);
            if (!$cuenta['ok'] && empty($cuenta['vinculado'])) {
                inscripcion_flow_tx_rollback($pdo, $ownTx);
                return [
                    'ok' => false,
                    'message' => $cuenta['message'] ?? 'No se pudo crear la cuenta del alumno',
                ];
            }
            // Moodle puede quedar pendiente; recepción puede completar desde ficha → Cuentas digitales.
        }

        $asign = ubicacion_asignar_grupo_validado($pdo, $idAlumno, $idGrupo);
        if (!$asign['ok']) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);
            return $asign;
        }

        $comboMsg = '';
        $combo = pago_aplicar_reglas_combo($pdo, $idAlumno);
        if (!empty($combo['aplicada'])) {
            $comboMsg = ' · ' . $combo['nombre'];
        }

        if ($idPreregistro > 0) {
            $pdo->prepare(
                'UPDATE preregistros SET estado = \'inscrito\', fecha_estado = NOW() WHERE id_preregistro = ?'
            )->execute([$idPreregistro]);

            $pr = $pdo->prepare('SELECT * FROM preregistros WHERE id_preregistro = ? LIMIT 1');
            $pr->execute([$idPreregistro]);
            $row = $pr->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $faltan = preregistro_factura_campos_pendientes($row);
                if ((int) $row['requiere_factura'] && count($faltan) > 0) {
                    preregistro_notificar_factura_recepcion(
                        $pdo,
                        $idPreregistro,
                        $idPlantel,
                        $faltan
                    );
                }
                $pdo->prepare(
                    'UPDATE preregistro_alertas SET resuelta = 1
                     WHERE id_preregistro = ? AND tipo IN (\'curso_no_abierto\',\'curso_abierto_seguimiento\')'
                )->execute([$idPreregistro]);
            }
        }

        inscripcion_flow_tx_commit($pdo, $ownTx);

        return [
            'ok' => true,
            'message' => 'Alumno inscrito al grupo' . $comboMsg,
            'id_alumno' => $idAlumno,
            'id_grupo' => $idGrupo,
        ];
    } catch (Throwable $e) {
        inscripcion_flow_tx_rollback($pdo, $ownTx);
        return ['ok' => false, 'message' => 'Error BD: ' . $e->getMessage()];
    }
}

/**
 * Cobro de inscripción (si aplica) + asignación al grupo en un solo paso.
 *
 * @return array<string, mixed>
 */
function inscripcion_flow_completar(
    PDO $pdo,
    int $idAlumno,
    int $idGrupo,
    ?int $idPreregistro,
    float $montoCobrar,
    string $formaPago,
    ?int $idAlumnoReferidor = null,
    ?float $montoPersonalizado = null,
    ?int $idReglaDescuento = null,
    array $gruposCombo = [],
    bool $flujoPersonalizado = false
): array {
    $idPlantel = plantel_id_activo();

    if (!plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel)) {
        return ['ok' => false, 'message' => 'El grupo no pertenece a este plantel'];
    }

    $g = $pdo->prepare('SELECT clave, id_especialidad, es_personalizado FROM grupos WHERE id_grupo = ? LIMIT 1');
    $g->execute([$idGrupo]);
    $grupo = $g->fetch(PDO::FETCH_ASSOC);
    if (!$grupo) {
        return ['ok' => false, 'message' => 'Grupo no encontrado'];
    }
    $esPersonalizado = $flujoPersonalizado || (int) ($grupo['es_personalizado'] ?? 0) === 1;

    $idEsp = (int) ($grupo['id_especialidad'] ?? 0);
    if ($idEsp <= 0) {
        return ['ok' => false, 'message' => 'Grupo sin especialidad'];
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
    if (!$esPersonalizado) {
        inscripcion_preparar_tarifa_inscripcion(
            $pdo,
            $idAlumno,
            $idEsp,
            $idReglaDescuento > 0 ? $idReglaDescuento : null
        );

        if ($idPreregistro > 0 && function_exists('preregistro_aplicar_apartado_a_alumno')) {
            preregistro_aplicar_apartado_a_alumno($pdo, $idPreregistro);
        }
    }

    $resumen = inscripcion_resumen_alumno($pdo, $idAlumno, $idEsp);
    if (!$resumen['ok']) {
        inscripcion_flow_tx_rollback($pdo, $ownTx);
        return $resumen;
    }

    $saldo = (float) ($resumen['saldo_inscripcion'] ?? 0);
    $idAe = (int) ($resumen['id_alumno_especialidad'] ?? 0);
    $idPago = 0;
    $ticket = null;

    if ($esPersonalizado) {
        $costoBruto = $montoPersonalizado ?? $montoCobrar;
        if ($costoBruto <= 0) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);
            return ['ok' => false, 'message' => 'Indique el costo acordado del curso personalizado'];
        }
        $credito = (float) ($resumen['credito_apartado_pendiente'] ?? 0);
        $montoPer = max(0.0, round($costoBruto - $credito, 2));
        if ($montoPer <= 0) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);
            return ['ok' => false, 'message' => 'El crédito de apartado cubre el costo acordado'];
        }
        if ($montoCobrar + 0.01 < $montoPer) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);
            return [
                'ok' => false,
                'message' => 'Debe cobrar el total del personalizado: ' . catalog_format_mxn($montoPer),
            ];
        }
        $folio = pago_generar_folio_inscripcion($pdo, $idPlantel);
        $pago = pago_registrar($pdo, [
            'id_alumno' => $idAlumno,
            'id_especialidad' => $idEsp,
            'id_alumno_especialidad' => $idAe ?: null,
            'tipo' => 'otro',
            'monto' => $montoPer,
            'concepto' => 'Curso personalizado — ' . ($grupo['clave'] ?? 'grupo'),
            'forma_pago_efectivo' => $formaPago !== '' ? $formaPago : 'Efectivo',
            'folio' => $folio,
        ]);
        if (!$pago['ok']) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);
            return $pago;
        }
        $idPago = (int) ($pago['id_pago'] ?? 0);
        $montoCobrar = $montoPer;
        $ticket = pago_datos_ticket_inscripcion($pdo, $idPago, $idPlantel);
        if ($ticket) {
            $ticket['grupo'] = $grupo['clave'] ?? '';
            $ticket['recibio'] = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''));
        }
    } elseif ($saldo > 0.009) {
        if ($montoCobrar <= 0) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);
            return [
                'ok' => false,
                'message' => 'Debe cobrar el saldo de inscripción: ' . catalog_format_mxn($saldo),
                'saldo_inscripcion' => $saldo,
            ];
        }
        if ($montoCobrar + 0.01 < $saldo) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);
            return [
                'ok' => false,
                'message' => 'El monto debe cubrir el saldo pendiente (' . catalog_format_mxn($saldo) . ')',
                'saldo_inscripcion' => $saldo,
            ];
        }
        if ($montoCobrar > $saldo + 0.01) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);
            return [
                'ok' => false,
                'message' => 'El monto no puede exceder el saldo (' . catalog_format_mxn($saldo) . ')',
            ];
        }

        $folio = pago_generar_folio_inscripcion($pdo, $idPlantel);
        $pago = pago_registrar($pdo, [
            'id_alumno' => $idAlumno,
            'id_especialidad' => $idEsp,
            'id_alumno_especialidad' => $idAe ?: null,
            'tipo' => 'inscripcion',
            'monto' => min($montoCobrar, $saldo),
            'concepto' => 'Inscripción — ' . ($grupo['clave'] ?? 'grupo'),
            'forma_pago_efectivo' => $formaPago !== '' ? $formaPago : 'Efectivo',
            'folio' => $folio,
        ]);

        if (!$pago['ok']) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);
            return $pago;
        }

        $idPago = (int) ($pago['id_pago'] ?? 0);
        $ticket = pago_datos_ticket_inscripcion($pdo, $idPago, $idPlantel);
        if ($ticket) {
            $ticket['grupo'] = $grupo['clave'] ?? '';
            $ticket['recibio'] = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''));
        }
    }

    $puedeAsignar = ($esPersonalizado && $idPago > 0)
        || inscripcion_puede_asignar_grupo($pdo, $idAlumno, $idEsp);
    if (!$puedeAsignar) {
        inscripcion_flow_tx_rollback($pdo, $ownTx);
        return [
            'ok' => false,
            'message' => 'La inscripción aún no está cubierta',
            'saldo_inscripcion' => inscripcion_saldo_pendiente($pdo, $idAlumno, $idEsp),
        ];
    }

    $confirm = inscripcion_flow_confirmar_grupo($pdo, $idAlumno, $idGrupo, $idPreregistro, $esPersonalizado);
    if (!$confirm['ok']) {
        inscripcion_flow_tx_rollback($pdo, $ownTx);
        return $confirm;
    }

    foreach ($gruposCombo as $gc) {
        $idEspExtra = (int) ($gc['id_especialidad'] ?? 0);
        $idGrExtra = (int) ($gc['id_grupo'] ?? 0);
        if ($idEspExtra <= 0 || $idGrExtra <= 0 || $idGrExtra === $idGrupo) {
            continue;
        }
        if (!plantel_grupo_pertenece($pdo, $idGrExtra, $idPlantel)) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);
            return ['ok' => false, 'message' => 'Uno de los grupos del combo no pertenece a este plantel'];
        }
        pago_crear_inscripcion($pdo, $idAlumno, $idEspExtra, 'mensual', date('Y-m-d'), true);
        $confExtra = inscripcion_flow_confirmar_grupo($pdo, $idAlumno, $idGrExtra, null, false);
        if (!$confExtra['ok']) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);
            return $confExtra;
        }
    }

    inscripcion_flow_tx_commit($pdo, $ownTx);

    $out = array_merge($confirm, [
        'id_pago' => $idPago,
        'ticket' => $ticket,
        'ticket_url' => $idPago > 0
            ? 'views/ticket_pago_inscripcion.php?id_pago=' . $idPago . '&print=1'
            : null,
        'grupo_clave' => $grupo['clave'] ?? '',
    ]);
    } catch (Throwable $e) {
        inscripcion_flow_tx_rollback($pdo, $ownTx);
        throw $e;
    }

    if ($idAlumnoReferidor > 0 && $idAlumnoReferidor !== $idAlumno) {
        $ref = referido_aplicar_tras_inscripcion(
            $pdo,
            $idPlantel,
            $idAlumno,
            $idAlumnoReferidor,
            $idEsp,
            $idGrupo,
            $idPago
        );
        if ($ref['ok']) {
            $out['referido'] = $ref;
            $out['ticket_referidor_url'] = $ref['ticket_url'] ?? null;
        } else {
            $out['referido_error'] = $ref['message'] ?? 'No se aplicó beneficio al referidor';
        }
    }

    if (function_exists('ventas_registrar_movimiento_inscripcion')) {
        try {
            $montoVentas = $montoCobrar > 0 ? $montoCobrar : (float) ($resumen['costo_inscripcion'] ?? 0);
            if ($montoVentas <= 0 && $esPersonalizado) {
                $montoVentas = (float) ($montoPersonalizado ?? $montoCobrar ?? 0);
            }
            $vm = ventas_registrar_movimiento_inscripcion(
                $pdo,
                $idPlantel,
                $idAlumno,
                $idGrupo,
                max($montoVentas, 0.01),
                $idPago > 0 ? $idPago : null,
                $idPreregistro
            );
            if (!empty($vm['ok']) && !empty($vm['comision_asesor'])) {
                $out['comision_asesor'] = $vm['comision_asesor'];
                $out['comision_asesor_fmt'] = catalog_format_mxn($vm['comision_asesor']);
            } elseif (empty($vm['ok'])) {
                $out['ventas_error'] = $vm['message'] ?? 'No se registró movimiento de comisión';
            }
        } catch (Throwable $e) {
            error_log('ventas_registrar_movimiento_inscripcion: ' . $e->getMessage());
            $out['ventas_error'] = 'Inscripción OK; no se registró comisión de venta.';
        }
    }

    if (!empty($out['ok']) && function_exists('gerente_notificar_inscripcion')) {
        try {
            gerente_notificar_inscripcion($pdo, $idPlantel, $idAlumno, $idGrupo, $idPreregistro);
        } catch (Throwable $e) {
            error_log('gerente_notificar_inscripcion: ' . $e->getMessage());
        }
    }

    return $out;
}

/**
 * Cobro de inscripción + ubicación (sin grupo): acceso al examen Moodle.
 *
 * @return array<string, mixed>
 */
function inscripcion_flow_completar_ubicacion(
    PDO $pdo,
    int $idAlumno,
    int $idEspecialidad,
    int $idExamen,
    ?int $idPreregistro,
    float $montoCobrar,
    string $formaPago,
    ?int $idAlumnoReferidor = null,
    ?int $idReglaDescuento = null
): array {
    $idPlantel = plantel_id_activo();
    if ($idAlumno <= 0 || $idEspecialidad <= 0 || $idExamen <= 0) {
        return ['ok' => false, 'message' => 'Alumno, especialidad y examen son obligatorios'];
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        inscripcion_preparar_tarifa_inscripcion(
            $pdo,
            $idAlumno,
            $idEspecialidad,
            $idReglaDescuento > 0 ? $idReglaDescuento : null
        );

        if ($idPreregistro > 0 && function_exists('preregistro_aplicar_apartado_a_alumno')) {
            preregistro_aplicar_apartado_a_alumno($pdo, $idPreregistro);
        }

        $resumen = inscripcion_resumen_alumno($pdo, $idAlumno, $idEspecialidad);
        if (!$resumen['ok']) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);

            return $resumen;
        }

        $saldo = (float) ($resumen['saldo_inscripcion'] ?? 0);
        $idAe = (int) ($resumen['id_alumno_especialidad'] ?? 0);
        $idPago = 0;
        $ticket = null;

        if ($saldo > 0.009) {
            if ($montoCobrar <= 0) {
                inscripcion_flow_tx_rollback($pdo, $ownTx);

                return [
                    'ok' => false,
                    'message' => 'Debe cobrar el saldo de inscripción: ' . catalog_format_mxn($saldo),
                    'saldo_inscripcion' => $saldo,
                ];
            }
            if ($montoCobrar + 0.01 < $saldo) {
                inscripcion_flow_tx_rollback($pdo, $ownTx);

                return [
                    'ok' => false,
                    'message' => 'El monto debe cubrir el saldo pendiente (' . catalog_format_mxn($saldo) . ')',
                ];
            }

            $espNom = (string) ($resumen['especialidad'] ?? 'especialidad');
            $folio = pago_generar_folio_inscripcion($pdo, $idPlantel);
            $pago = pago_registrar($pdo, [
                'id_alumno' => $idAlumno,
                'id_especialidad' => $idEspecialidad,
                'id_alumno_especialidad' => $idAe ?: null,
                'tipo' => 'inscripcion',
                'monto' => min($montoCobrar, $saldo),
                'concepto' => 'Inscripción por ubicación — ' . $espNom,
                'forma_pago_efectivo' => $formaPago !== '' ? $formaPago : 'Efectivo',
                'folio' => $folio,
            ]);
            if (!$pago['ok']) {
                inscripcion_flow_tx_rollback($pdo, $ownTx);

                return $pago;
            }
            $idPago = (int) ($pago['id_pago'] ?? 0);
            $ticket = pago_datos_ticket_inscripcion($pdo, $idPago, $idPlantel);
            if ($ticket) {
                $ticket['grupo'] = 'Ubicación (sin grupo)';
                $ticket['recibio'] = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''));
            }
        }

        if (!inscripcion_puede_asignar_grupo($pdo, $idAlumno, $idEspecialidad)) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);

            return [
                'ok' => false,
                'message' => 'La inscripción aún no está cubierta',
                'saldo_inscripcion' => inscripcion_saldo_pendiente($pdo, $idAlumno, $idEspecialidad),
            ];
        }

        if (function_exists('alumno_asignar_numero_control_inscripcion')) {
            alumno_asignar_numero_control_inscripcion($pdo, $idAlumno);
        }

        $ubRes = ubicacion_inscripcion_con_examen($pdo, $idAlumno, $idEspecialidad, $idExamen);
        if (!$ubRes['ok']) {
            inscripcion_flow_tx_rollback($pdo, $ownTx);

            return $ubRes;
        }

        if ($idPreregistro > 0) {
            $pdo->prepare(
                'UPDATE preregistros SET estado = \'inscrito\', fecha_estado = NOW() WHERE id_preregistro = ?'
            )->execute([$idPreregistro]);
        }

        inscripcion_flow_tx_commit($pdo, $ownTx);

        $out = [
            'ok' => true,
            'message' => ($ubRes['message'] ?? 'Inscripción por ubicación completada')
                . '. Coordinación autorizará el grupo tras el examen.',
            'id_alumno' => $idAlumno,
            'id_ubicacion' => $ubRes['id_ubicacion'] ?? null,
            'examen' => $ubRes['examen'] ?? '',
            'es_ubicacion' => true,
            'moodle_inscrito' => !empty($ubRes['moodle_inscrito']),
            'moodle_warning' => $ubRes['moodle_warning'] ?? null,
            'id_pago' => $idPago,
            'ticket' => $ticket,
            'ticket_url' => $idPago > 0
                ? 'views/ticket_pago_inscripcion.php?id_pago=' . $idPago . '&print=1'
                : null,
        ];
    } catch (Throwable $e) {
        inscripcion_flow_tx_rollback($pdo, $ownTx);

        return ['ok' => false, 'message' => 'Error BD: ' . $e->getMessage()];
    }

    if ($idAlumnoReferidor > 0 && $idAlumnoReferidor !== $idAlumno && function_exists('referido_aplicar_tras_inscripcion')) {
        $ref = referido_aplicar_tras_inscripcion(
            $pdo,
            $idPlantel,
            $idAlumno,
            $idAlumnoReferidor,
            $idEspecialidad,
            0,
            $idPago
        );
        if ($ref['ok']) {
            $out['referido'] = $ref;
        }
    }

    if (!empty($out['ok']) && function_exists('gerente_notificar_inscripcion')) {
        try {
            gerente_notificar_inscripcion($pdo, $idPlantel, $idAlumno, 0, $idPreregistro);
        } catch (Throwable $e) {
            error_log('gerente_notificar_inscripcion ubicacion: ' . $e->getMessage());
        }
    }

    return $out;
}
