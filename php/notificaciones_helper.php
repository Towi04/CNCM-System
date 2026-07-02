<?php

/**
 * Alertas del panel de inicio según rol del usuario.
 */

define('INSCRIPCION_VIGENCIA_MESES', 8);
define('ASISTENCIA_SEMANAS_ALERTA', 2);

function notificaciones_perfil_usuario(): string
{
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
    if ($rol === 'director') {
        return 'director';
    }
    if ($rol === 'gerente') {
        return 'gerente';
    }
    if ($rol === 'asesor') {
        return 'ventas';
    }
    if (in_array($rol, ['coordinador', 'coordinacion'], true)) {
        return 'coordinador';
    }
    if ($rol === 'profesor') {
        return 'profesor';
    }
    if ($rol === 'supervisor') {
        return 'supervisor';
    }
    if ($rol === 'admin') {
        return 'recepcion';
    }
    return 'general';
}

/** @return list<string> */
function notificaciones_perfiles_visibles(): array
{
    $p = notificaciones_perfil_usuario();
    if ($p === 'recepcion') {
        return ['recepcion', 'general'];
    }
    if ($p === 'director') {
        return ['director', 'recepcion', 'general'];
    }
    if ($p === 'gerente') {
        return ['gerente', 'general'];
    }
    return [$p, 'general'];
}

/**
 * @return array<int, array{tipo:string, titulo:string, mensaje:string, enlace?:string, prioridad:string}>
 */
function notificaciones_panel_inicio(PDO $pdo, int $idPlantel): array
{
    $items = notificaciones_panel_inicio_raw($pdo, $idPlantel);
    $idUsuario = (int) ($_SESSION['user_id'] ?? 0);
    if ($idUsuario > 0) {
        $items = notificaciones_panel_preparar_lista($pdo, $idUsuario, $idPlantel, $items);
    }

    return $items;
}

/**
 * Avisos del panel según rol (sin filtrar leídos/archivados).
 *
 * @return array<int, array<string, mixed>>
 */
function notificaciones_panel_inicio_raw(PDO $pdo, int $idPlantel): array
{
    $perfiles = notificaciones_perfiles_visibles();
    $items = [];

    if (in_array('director', $perfiles, true)) {
        $items = array_merge($items, notificaciones_cartera_vencida($pdo, $idPlantel));
    }
    if (in_array('recepcion', $perfiles, true)) {
        $items = array_merge($items, notificaciones_inscripcion_por_vencer($pdo, $idPlantel));
        $items = array_merge($items, notificaciones_posible_baja($pdo, $idPlantel));
        $items = array_merge($items, notificaciones_recepcion_factura($pdo, $idPlantel));
        if (function_exists('notificaciones_constancias_pendientes')) {
            $items = array_merge($items, notificaciones_constancias_pendientes($pdo, $idPlantel));
        }
        if (function_exists('notificaciones_documentos_entrega')) {
            $items = array_merge($items, notificaciones_documentos_entrega($pdo, $idPlantel));
        }
    }
    if (in_array('ventas', $perfiles, true)) {
        $items = array_merge($items, notificaciones_asesor_preregistros($pdo, $idPlantel));
    }
    if (in_array('gerente', $perfiles, true)) {
        $items = array_merge($items, notificaciones_gerente_plantel($pdo, $idPlantel));
    }
    if (in_array('coordinador', $perfiles, true)) {
        $items = array_merge($items, notificaciones_grupos_por_graduar($pdo, $idPlantel));
        $items = array_merge($items, notificaciones_coordinador_plantel($pdo, $idPlantel));
    }
    if (in_array('profesor', $perfiles, true)) {
        $items = array_merge($items, notificaciones_grupos_por_graduar($pdo, $idPlantel));
    }
    if (in_array('supervisor', $perfiles, true)) {
        $items = array_merge($items, notificaciones_tarifa_supervisor($pdo, $idPlantel));
    }
    if (in_array('director', $perfiles, true) && function_exists('graduacion_generar_alertas_automaticas')) {
        $claveGrad = 'grad_alertas_ts_' . $idPlantel;
        $ultGrad = function_exists('hay_meta_get') ? hay_meta_get($pdo, $claveGrad) : null;
        $haceGrad = $ultGrad ? (time() - (int) strtotime($ultGrad)) : 999999;
        if ($haceGrad > 43200) {
            graduacion_generar_alertas_automaticas($pdo, $idPlantel);
            if (function_exists('hay_meta_set')) {
                hay_meta_set($pdo, $claveGrad, date('Y-m-d H:i:s'));
            }
        }
        $items = array_merge($items, notificaciones_graduacion_alertas($pdo, $idPlantel));
    }
    if (in_array('director', $perfiles, true) && function_exists('grupo_avance_listar_riesgo_plantel')) {
        $items = array_merge($items, notificaciones_riesgo_academico($pdo, $idPlantel));
    }

    usort($items, function ($a, $b) {
        $ord = ['alta' => 0, 'media' => 1, 'baja' => 2];
        return ($ord[$a['prioridad']] ?? 9) <=> ($ord[$b['prioridad']] ?? 9);
    });

    return $items;
}

/** @return array<int, array<string, mixed>> */
function notificaciones_panel_lista_completa(PDO $pdo, int $idUsuario, int $idPlantel, int $limiteBd = 50): array
{
    notificaciones_panel_ensure_schema($pdo);
    $items = [];
    if ($idUsuario > 0 && function_exists('notificaciones_usuario_bd')) {
        $items = array_merge($items, notificaciones_usuario_bd($pdo, $idUsuario, $limiteBd));
    }
    $items = array_merge($items, notificaciones_panel_inicio_raw($pdo, $idPlantel));
    if ($idUsuario > 0) {
        $items = notificaciones_panel_preparar_lista($pdo, $idUsuario, $idPlantel, $items);
    }

    return $items;
}

/** Alertas de constancias solicitadas pendientes de cobro en caja. */
function notificaciones_constancias_pendientes(PDO $pdo, int $idPlantel): array
{
    if (!function_exists('documento_contar_pendientes_plantel')) {
        return [];
    }
    $n = documento_contar_pendientes_plantel($pdo, $idPlantel);
    if ($n <= 0) {
        return [];
    }

    return [[
        'tipo' => 'constancia_pendiente',
        'titulo' => 'Constancias por cobrar',
        'mensaje' => $n . ' solicitud(es) esperan pago en punto de venta',
        'enlace' => 'piso_operativo',
        'prioridad' => 'alta',
        'agregada' => true,
    ]];
}

/** Documentos emitidos pendientes de entrega física en recepción. */
function notificaciones_documentos_entrega(PDO $pdo, int $idPlantel): array
{
    if (!function_exists('documento_contar_pendientes_entrega_plantel') || !documento_puede_entregar()) {
        return [];
    }
    $n = documento_contar_pendientes_entrega_plantel($pdo, $idPlantel);
    if ($n <= 0) {
        return [];
    }
    $nDip = documento_contar_pendientes_entrega_plantel($pdo, $idPlantel, 'diploma');
    $det = $nDip > 0 ? $nDip . ' diploma(s)' : '';
    $nCon = $n - $nDip;
    if ($nCon > 0) {
        $det .= ($det !== '' ? ' · ' : '') . $nCon . ' constancia(s)';
    }

    return [[
        'tipo' => 'documento_entrega',
        'titulo' => 'Documentos por entregar',
        'mensaje' => $n . ' documento(s) listos para entrega en mostrador' . ($det !== '' ? ' (' . $det . ')' : ''),
        'enlace' => 'piso_operativo&tab=entrega',
        'prioridad' => 'alta',
        'agregada' => true,
    ]];
}

/** @return array<int, array<string, string>> */
function notificaciones_cartera_vencida(PDO $pdo, int $idPlantel): array
{
    $cacheKey = 'hay_notif_cartera_' . $idPlantel;
    if (!empty($_SESSION[$cacheKey]) && (time() - (int) ($_SESSION[$cacheKey]['ts'] ?? 0)) < 300) {
        return $_SESSION[$cacheKey]['items'] ?? [];
    }

    $alumnos = $pdo->prepare(
        "SELECT id_alumno FROM alumnos WHERE id_plantel = ? AND estado = 'activo' ORDER BY id_alumno LIMIT 150"
    );
    $alumnos->execute([$idPlantel]);
    $conAdeudo = 0;
    $totalAdeudo = 0.0;
    $revisados = 0;
    foreach ($alumnos->fetchAll(PDO::FETCH_COLUMN) as $idAl) {
        $revisados++;
        $ec = pago_estado_cuenta($pdo, (int) $idAl);
        if (!$ec['ok']) {
            continue;
        }
        $adeudo = (float) ($ec['resumen']['adeudo_colegiatura'] ?? 0);
        if ($adeudo > 0.5) {
            $conAdeudo++;
            $totalAdeudo += $adeudo;
        }
    }

    $items = [];
    if ($conAdeudo > 0) {
        $msg = $conAdeudo . ' alumnos con adeudo · Total ' . catalog_format_mxn($totalAdeudo);
        if ($revisados >= 150) {
            $msg .= ' (muestra de 150 activos; ver detalle en consulta)';
        }
        $items = [[
            'tipo' => 'cartera',
            'titulo' => 'Cartera vencida',
            'mensaje' => $msg,
            'enlace' => 'consulta_adeudo',
            'prioridad' => 'alta',
            'agregada' => true,
        ]];
    }

    $_SESSION[$cacheKey] = ['ts' => time(), 'items' => $items];

    return $items;
}

/** @return array<int, array<string, string>> */
function notificaciones_inscripcion_por_vencer(PDO $pdo, int $idPlantel): array
{
    pago_ensure_schema($pdo);
    $limite = date('Y-m-d', strtotime('+30 days'));
    $stmt = $pdo->prepare(
        "SELECT a.id_alumno, a.numero_control, a.nombres, a.apellido_paterno, a.inscripcion_vigente_hasta, a.estado
         FROM alumnos a
         WHERE a.id_plantel = ? AND a.inscripcion_vigente_hasta IS NOT NULL
           AND a.inscripcion_vigente_hasta <= ? AND a.inscripcion_vigente_hasta >= CURDATE()
           AND (a.estado = 'baja' OR a.inscripcion_global_pagada = 1)
         ORDER BY a.inscripcion_vigente_hasta ASC LIMIT 15"
    );
    $stmt->execute([$idPlantel, $limite]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $items = [];
    foreach ($rows as $r) {
        $nombre = trim(($r['nombres'] ?? '') . ' ' . ($r['apellido_paterno'] ?? ''));
        $dias = (int) ((strtotime($r['inscripcion_vigente_hasta']) - time()) / 86400);
        $items[] = [
            'tipo' => 'inscripcion_caduca',
            'titulo' => 'Inscripción por caducar',
            'mensaje' => ($r['numero_control'] ?? '') . ' ' . $nombre . ' — vigente hasta '
                . date('d/m/Y', strtotime($r['inscripcion_vigente_hasta'])) . ' (' . $dias . ' días)',
            'enlace' => 'consulta_adeudo&control=' . urlencode((string) ($r['numero_control'] ?? $r['id_alumno'])),
            'prioridad' => 'media',
        ];
    }
    return $items;
}

/** @return array<int, array<string, string>> */
function notificaciones_posible_baja(PDO $pdo, int $idPlantel): array
{
    asistencia_ensure_schema($pdo);
    $semanas = ASISTENCIA_SEMANAS_ALERTA;
    $desde = date('Y-m-d', strtotime('-' . ($semanas * 7) . ' days'));

    $stmt = $pdo->prepare(
        "SELECT a.id_alumno, a.numero_control, a.nombres, a.apellido_paterno,
                MAX(ast.fecha) AS ultima_asistencia
         FROM alumnos a
         INNER JOIN alumno_grupos ag ON ag.id_alumno = a.id_alumno AND ag.activo = 1
         LEFT JOIN asistencias ast ON ast.id_alumno = a.id_alumno AND ast.presente = 1
         WHERE a.id_plantel = ? AND a.estado = 'activo'
         GROUP BY a.id_alumno
         HAVING ultima_asistencia IS NULL OR ultima_asistencia < ?
         ORDER BY ultima_asistencia ASC
         LIMIT 12"
    );
    $stmt->execute([$idPlantel, $desde]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $nombre = trim(($r['nombres'] ?? '') . ' ' . ($r['apellido_paterno'] ?? ''));
        $ult = $r['ultima_asistencia']
            ? date('d/m/Y', strtotime($r['ultima_asistencia']))
            : 'sin registro';
        $items[] = [
            'tipo' => 'posible_baja',
            'titulo' => 'Sin asistencia reciente',
            'mensaje' => ($r['numero_control'] ?? '') . ' ' . $nombre . ' — última: ' . $ult,
            'enlace' => 'alumno_detalle&id=' . (int) $r['id_alumno'],
            'prioridad' => 'media',
        ];
    }
    return $items;
}

/** Alertas de pre-registro solo del asesor que los creó (dashboard inicio, no lista pre-registro). */
function notificaciones_asesor_preregistros(PDO $pdo, int $idPlantel): array
{
    preregistro_ensure_schema($pdo);
    plantel_ensure_column($pdo, 'preregistros', 'fecha_compromiso_contacto', 'DATE NULL', 'espera_apertura_curso');

    $idAsesor = (int) ($_SESSION['user_id'] ?? 0);
    if ($idAsesor <= 0) {
        return [];
    }

    $items = [];

    $stmt = $pdo->prepare(
        "SELECT id_preregistro, nombres, apellido_paterno, telefono
         FROM preregistros
         WHERE id_plantel = ? AND id_usuario_registro = ?
           AND estado IN ('activo','pendiente') AND espera_apertura_curso = 1
         ORDER BY creado_en DESC LIMIT 8"
    );
    $stmt->execute([$idPlantel, $idAsesor]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $items[] = [
            'tipo' => 'espera_curso',
            'titulo' => 'Espera apertura de curso',
            'mensaje' => trim($r['nombres'] . ' ' . $r['apellido_paterno']) . ($r['telefono'] ? ' · ' . $r['telefono'] : ''),
            'enlace' => 'pre_registro_alumnos',
            'prioridad' => 'media',
        ];
    }

    $stmt2 = $pdo->prepare(
        "SELECT id_preregistro, nombres, apellido_paterno, fecha_compromiso_contacto, motivo_pendiente
         FROM preregistros
         WHERE id_plantel = ? AND id_usuario_registro = ?
           AND estado = 'pendiente'
           AND (fecha_recordatorio IS NULL OR fecha_recordatorio <= CURDATE())
         ORDER BY fecha_recordatorio ASC, creado_en DESC LIMIT 10"
    );
    $stmt2->execute([$idPlantel, $idAsesor]);
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $msg = trim($r['nombres'] . ' ' . $r['apellido_paterno']);
        if (!empty($r['motivo_pendiente'])) {
            $msg .= ' — ' . mb_substr((string) $r['motivo_pendiente'], 0, 120);
        }
        if (!empty($r['fecha_compromiso_contacto'])) {
            $msg .= ' · contacto ' . date('d/m/Y', strtotime($r['fecha_compromiso_contacto']));
        }
        $items[] = [
            'tipo' => 'pendiente_seguimiento',
            'titulo' => 'Pre-registro pendiente',
            'mensaje' => $msg,
            'enlace' => 'pre_registro_alumnos',
            'prioridad' => 'alta',
        ];
    }

    $stmt3 = $pdo->prepare(
        "SELECT id_preregistro, nombres, apellido_paterno, fecha_compromiso_contacto
         FROM preregistros
         WHERE id_plantel = ? AND id_usuario_registro = ?
           AND estado IN ('activo','pendiente')
           AND fecha_compromiso_contacto IS NOT NULL
           AND fecha_compromiso_contacto <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
         ORDER BY fecha_compromiso_contacto ASC LIMIT 8"
    );
    $stmt3->execute([$idPlantel, $idAsesor]);
    foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $items[] = [
            'tipo' => 'compromiso_contacto',
            'titulo' => 'Contacto programado',
            'mensaje' => trim($r['nombres'] . ' ' . $r['apellido_paterno']) . ' — '
                . date('d/m/Y', strtotime($r['fecha_compromiso_contacto'])),
            'enlace' => 'pre_registro_alumnos',
            'prioridad' => 'alta',
        ];
    }

    return $items;
}

/** Alertas del plantel para gerente de ventas (equipo + inscripciones). */
function notificaciones_gerente_plantel(PDO $pdo, int $idPlantel): array
{
    if (!function_exists('gerente_notificaciones_panel')) {
        return [];
    }

    return gerente_notificaciones_panel($pdo, $idPlantel);
}

/** @return array<int, array<string, string>> */
function notificaciones_recepcion_factura(PDO $pdo, int $idPlantel): array
{
    preregistro_ensure_schema($pdo);
    $stmt = $pdo->prepare(
        "SELECT p.id_preregistro, p.nombres, p.apellido_paterno, a.numero_control
         FROM preregistros p
         LEFT JOIN alumnos a ON a.id_alumno = p.id_alumno_vinculado
         WHERE p.id_plantel = ? AND p.requiere_factura = 1 AND p.factura_datos_pendientes = 1
           AND p.estado <> 'perdido'
         ORDER BY p.actualizado_en DESC, p.creado_en DESC
         LIMIT 12"
    );
    $stmt->execute([$idPlantel]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $nombre = trim(($r['nombres'] ?? '') . ' ' . ($r['apellido_paterno'] ?? ''));
        $control = trim((string) ($r['numero_control'] ?? ''));
        $det = $control !== '' ? 'No. ' . $control . ' — ' . $nombre : $nombre;
        $items[] = [
            'tipo' => 'factura_pendiente',
            'titulo' => 'Completar datos de factura',
            'mensaje' => $det . '. Suba constancia y datos fiscales o quite la marca de factura.',
            'enlace' => 'cola_facturacion&id=' . (int) $r['id_preregistro'],
            'prioridad' => 'alta',
        ];
    }

    return $items;
}

/** Alertas académicas para coordinación del plantel. */
function notificaciones_coordinador_plantel(PDO $pdo, int $idPlantel): array
{
    if (!function_exists('rbac_cap') || (!rbac_cap('planeaciones_revisar') && !rbac_cap('inscripcion_autorizar_edad'))) {
        $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';
        if (!in_array($rol, ['coordinador', 'coordinacion', 'director'], true)) {
            return [];
        }
    }

    $items = [];

    if (function_exists('profesor_portal_permisos_pendientes')) {
        $permisos = profesor_portal_permisos_pendientes($pdo, $idPlantel);
        $nPerm = count($permisos);
        if ($nPerm > 0) {
            $primero = $permisos[0];
            $nombre = trim(($primero['nombre'] ?? '') . ' ' . ($primero['apellido'] ?? ''));
            $items[] = [
                'tipo' => 'permisos_profesor',
                'titulo' => 'Permisos de profesores pendientes',
                'mensaje' => $nPerm . ' solicitud(es)' . ($nombre !== '' ? ' · primera: ' . $nombre : ''),
                'enlace' => 'bandeja_aprobaciones&filtro=permiso_profesor',
                'prioridad' => 'alta',
                'agregada' => true,
            ];
        }
    }

    if (function_exists('inscripcion_protocolo_pendientes')) {
        $auths = inscripcion_protocolo_pendientes($pdo, $idPlantel);
        foreach (array_slice($auths, 0, 6) as $a) {
            $nombre = trim(($a['nombres'] ?? '') . ' ' . ($a['apellido_paterno'] ?? ''));
            $tipo = ($a['tipo'] ?? '') === 'ubicacion' ? 'ubicación' : (($a['tipo'] ?? '') === 'edad' ? 'edad' : ($a['tipo'] ?? 'inscripción'));
            $items[] = [
                'tipo' => 'inscripcion_auth',
                'titulo' => 'Autorización de inscripción',
                'mensaje' => ($a['numero_control'] ?? '') . ' ' . $nombre
                    . ' · grupo ' . ($a['grupo_clave'] ?? '—') . ' · ' . $tipo,
                'enlace' => 'bandeja_aprobaciones&filtro=inscripcion',
                'prioridad' => 'alta',
            ];
        }
    }

    if (function_exists('planeacion_contar_pendientes')) {
        $nPlan = planeacion_contar_pendientes($pdo, $idPlantel);
        if ($nPlan > 0) {
            $items[] = [
                'tipo' => 'planeacion_pendiente',
                'titulo' => 'Planeaciones por revisar',
                'mensaje' => $nPlan . ' planeación(es) de clase esperan revisión de coordinación',
                'enlace' => 'planeaciones_revision',
                'prioridad' => 'alta',
                'agregada' => true,
            ];
        }
    }

    return $items;
}

/** Alertas de colegiaturas personalizadas (beneficios por alumno). */
function notificaciones_tarifa_supervisor(PDO $pdo, int $idPlantel): array
{
    if (!function_exists('alumno_tarifa_supervisor_ensure_schema')) {
        return [];
    }
    alumno_tarifa_supervisor_ensure_schema($pdo);
    if (function_exists('alumno_tarifa_supervisor_aplicar_vencidas')) {
        alumno_tarifa_supervisor_aplicar_vencidas($pdo);
    }

    $items = [];
    $st = $pdo->prepare(
        'SELECT ae.override_vigente_hasta, ae.override_motivo, ae.costo_mensualidad,
                a.id_alumno, a.numero_control, a.nombres, a.apellido_paterno,
                e.nombre AS especialidad
         FROM alumno_especialidades ae
         INNER JOIN alumnos a ON a.id_alumno = ae.id_alumno
         INNER JOIN especialidades e ON e.id_especialidad = ae.id_especialidad
         WHERE a.id_plantel = ? AND ae.activo = 1 AND ae.override_supervisor = 1
           AND ae.override_vigente_hasta IS NOT NULL
           AND ae.override_vigente_hasta BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
         ORDER BY ae.override_vigente_hasta ASC
         LIMIT 10'
    );
    $st->execute([$idPlantel]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $nombre = trim(($r['nombres'] ?? '') . ' ' . ($r['apellido_paterno'] ?? ''));
        $dias = (int) ((strtotime((string) $r['override_vigente_hasta']) - time()) / 86400);
        $items[] = [
            'tipo' => 'tarifa_supervisor_vence',
            'titulo' => 'Beneficio de colegiatura por vencer',
            'mensaje' => ($r['numero_control'] ?? '') . ' ' . $nombre . ' · ' . ($r['especialidad'] ?? '')
                . ' — vence ' . date('d/m/Y', strtotime((string) $r['override_vigente_hasta']))
                . ' (' . $dias . ' días) · men. ' . catalog_format_mxn((float) ($r['costo_mensualidad'] ?? 0)),
            'enlace' => 'alumno_detalle&id=' . (int) $r['id_alumno'],
            'prioridad' => $dias <= 3 ? 'alta' : 'media',
        ];
    }

    return $items;
}

/** @return array<int, array<string, string>> */
function notificaciones_grupos_por_graduar(PDO $pdo, int $idPlantel): array
{
    $depto = $_SESSION['departamento'] ?? '';
    $extra = '';
    $params = [$idPlantel];
    if ($depto && ($_SESSION['rol'] ?? '') === 'profesor') {
        $extra = ' AND e.clave LIKE ?';
        $map = [
            'ingles' => 'ING%',
            'computacion' => 'COMP%',
        ];
        if (isset($map[$depto])) {
            $params[] = $map[$depto];
        }
    }

    $stmt = $pdo->prepare(
        "SELECT g.id_grupo, g.clave, g.fecha_inicio, e.nombre AS especialidad,
                DATE_ADD(g.fecha_inicio, INTERVAL COALESCE(e.duracion_meses, 12) MONTH) AS fecha_fin_estimada
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_plantel = ? AND g.fecha_inicio IS NOT NULL
           AND DATE_ADD(g.fecha_inicio, INTERVAL COALESCE(e.duracion_meses, 12) MONTH)
               BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 45 DAY)
           {$extra}
         ORDER BY fecha_fin_estimada ASC LIMIT 10"
    );
    $stmt->execute($params);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $items[] = [
            'tipo' => 'graduacion',
            'titulo' => 'Grupo próximo a terminar',
            'mensaje' => ($g['clave'] ?? 'Grupo') . ' · ' . ($g['especialidad'] ?? '')
                . ' — fin est. ' . date('d/m/Y', strtotime($g['fecha_fin_estimada'])),
            'enlace' => 'grupos',
            'prioridad' => 'media',
        ];
    }
    return $items;
}

/** @return array<int, array<string, string>> */
function notificaciones_riesgo_academico(PDO $pdo, int $idPlantel): array
{
    $lista = grupo_avance_listar_riesgo_plantel($pdo, $idPlantel);
    $n = count($lista);
    if ($n === 0) {
        return [];
    }
    return [
        [
            'tipo' => 'riesgo_academico',
            'titulo' => 'Riesgo académico',
            'mensaje' => $n . ' alumno(s) requieren seguimiento (avanzaron sin ≥ 6)',
            'enlace' => 'academico_riesgo',
            'prioridad' => 'alta',
            'agregada' => true,
        ],
    ];
}

/** @return array<int, array<string, string>> */
function notificaciones_graduacion_alertas(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM graduacion_alerta
         WHERE id_plantel = ? AND estado = 'pendiente'"
    );
    $st->execute([$idPlantel]);
    $n = (int) $st->fetchColumn();
    if ($n <= 0) {
        return [];
    }
    return [[
        'tipo' => 'graduacion_pendiente',
        'titulo' => 'Graduación por validar',
        'mensaje' => $n . ' alumno(s) en revisión final antes de proyecto final',
        'enlace' => 'graduacion_alertas',
        'prioridad' => 'alta',
        'agregada' => true,
    ]];
}

function notificaciones_panel_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $path = dirname(__DIR__) . '/sql/migrations/045_notificacion_panel_archivo.sql';
    if (!is_file($path) || !function_exists('hay_schema_ejecutar_sql')) {
        return;
    }
    try {
        $sql = file_get_contents($path);
        if ($sql !== false && trim($sql) !== '') {
            hay_schema_ejecutar_sql($pdo, $sql);
        }
    } catch (Throwable $e) {
        error_log('notificaciones_panel_ensure_schema: ' . $e->getMessage());
    }
}

/** Clave estable para ocultar un aviso del panel. */
function notificaciones_item_clave(array $it, int $idPlantel = 0): string
{
    if (!empty($it['id_notificacion'])) {
        return 'db:' . (int) $it['id_notificacion'];
    }
    if (!empty($it['clave'])) {
        return (string) $it['clave'];
    }
    $tipo = (string) ($it['tipo'] ?? 'aviso');
    if (!empty($it['ref_id'])) {
        return $tipo . ':' . (string) $it['ref_id'];
    }
    if (!empty($it['agregada'])) {
        return $tipo . ':p' . max(0, $idPlantel);
    }
    $hash = substr(
        hash('sha256', ($it['titulo'] ?? '') . '|' . ($it['mensaje'] ?? '') . '|' . ($it['enlace'] ?? '')),
        0,
        24
    );

    return $tipo . ':' . $hash;
}

/** @return list<string> */
function notificaciones_panel_claves_ocultas(PDO $pdo, int $idUsuario): array
{
    notificaciones_panel_ensure_schema($pdo);
    try {
        $st = $pdo->prepare(
            'SELECT clave FROM notificacion_panel_oculta WHERE id_usuario = ?'
        );
        $st->execute([$idUsuario]);

        return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'clave');
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Enriquece avisos con clave/fuente y quita los ya leídos u archivados.
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function notificaciones_panel_preparar_lista(PDO $pdo, int $idUsuario, int $idPlantel, array $items): array
{
    notificaciones_panel_ensure_schema($pdo);
    $ocultas = notificaciones_panel_claves_ocultas($pdo, $idUsuario);
    $out = [];
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        if (empty($it['clave'])) {
            $it['clave'] = notificaciones_item_clave($it, $idPlantel);
        }
        if (empty($it['fuente'])) {
            $it['fuente'] = !empty($it['id_notificacion']) ? 'bd' : 'panel';
        }
        if (in_array((string) $it['clave'], $ocultas, true)) {
            continue;
        }
        $out[] = $it;
    }

    return $out;
}

/** @return array{status:string,message?:string} */
function notificaciones_panel_ocultar(
    PDO $pdo,
    int $idUsuario,
    string $clave,
    string $estado = 'leida',
    ?int $idNotificacion = null,
    ?int $idPlantel = null
): array {
    notificaciones_panel_ensure_schema($pdo);
    $clave = trim($clave);
    if ($clave === '') {
        return ['status' => 'error', 'message' => 'Aviso inválido'];
    }
    if (!in_array($estado, ['leida', 'archivada'], true)) {
        return ['status' => 'error', 'message' => 'Estado inválido'];
    }

    try {
        if ($idNotificacion > 0 || str_starts_with($clave, 'db:')) {
            $idDb = $idNotificacion > 0 ? $idNotificacion : (int) substr($clave, 3);
            if ($idDb > 0) {
                $pdo->prepare(
                    'UPDATE notificacion_usuario
                     SET leida = 1, archivada = ?
                     WHERE id = ? AND id_usuario = ?'
                )->execute([$estado === 'archivada' ? 1 : 0, $idDb, $idUsuario]);
            }
        }

        $pdo->prepare(
            'INSERT INTO notificacion_panel_oculta (id_usuario, clave, estado, id_plantel)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE estado = VALUES(estado), creado_en = CURRENT_TIMESTAMP'
        )->execute([
            $idUsuario,
            $clave,
            $estado,
            $idPlantel > 0 ? $idPlantel : null,
        ]);

        return ['status' => 'ok', 'message' => $estado === 'archivada' ? 'Aviso archivado' : 'Marcado como leído'];
    } catch (PDOException $e) {
        error_log('notificaciones_panel_ocultar: ' . $e->getMessage());

        return ['status' => 'error', 'message' => 'No se pudo actualizar el aviso'];
    }
}

/**
 * @param list<string> $claves
 * @return array{status:string,message?:string,procesados?:int}
 */
function notificaciones_panel_ocultar_varios(
    PDO $pdo,
    int $idUsuario,
    array $claves,
    string $estado = 'leida',
    ?int $idPlantel = null
): array {
    $n = 0;
    foreach ($claves as $clave) {
        if (!is_string($clave) || trim($clave) === '') {
            continue;
        }
        $idNotif = null;
        if (str_starts_with($clave, 'db:')) {
            $idNotif = (int) substr($clave, 3);
        }
        $res = notificaciones_panel_ocultar($pdo, $idUsuario, $clave, $estado, $idNotif, $idPlantel);
        if (($res['status'] ?? '') === 'ok') {
            $n++;
        }
    }

    return [
        'status' => 'ok',
        'message' => $n . ' aviso(s) actualizado(s)',
        'procesados' => $n,
    ];
}

/** @return array{status:string,message?:string,procesados?:int} */
function notificaciones_panel_ocultar_todos(
    PDO $pdo,
    int $idUsuario,
    int $idPlantel,
    string $estado = 'leida'
): array {
    $items = notificaciones_panel_lista_completa($pdo, $idUsuario, $idPlantel, 50);
    $claves = [];
    foreach ($items as $it) {
        if (!is_array($it) || empty($it['clave'])) {
            continue;
        }
        $claves[] = (string) $it['clave'];
    }

    return notificaciones_panel_ocultar_varios($pdo, $idUsuario, $claves, $estado, $idPlantel);
}
