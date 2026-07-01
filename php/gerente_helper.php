<?php
/**
 * Métricas y reportes para rol gerente de ventas.
 */

function gerente_rango_semana(): array
{
    $fin = date('Y-m-d');
    $inicio = date('Y-m-d', strtotime('monday this week'));

    return [$inicio, $fin];
}

/** Filtro SQL: usuario no suspendido (no existe columna activo en usuarios). */
function gerente_sql_usuario_activo(string $alias = 'u'): string
{
    return "({$alias}.suspendido IS NULL OR {$alias}.suspendido = 0)";
}

/** Semana ISO actual (YYYY-Www) para cartas/comisiones. */
function gerente_semana_actual(): string
{
    return date('o-\WW');
}

function gerente_cartas_ensure_schema(PDO $pdo): void
{
    if (function_exists('tour_ensure_schema')) {
        tour_ensure_schema($pdo);
    }
    try {
        $pdo->exec(
            "ALTER TABLE asesor_cartas_periodo
             MODIFY periodo_mes VARCHAR(10) NOT NULL COMMENT 'YYYY-Www semana ISO o YYYY-MM legado'"
        );
    } catch (PDOException $e) {
        // ya aplicado o sin permisos DDL
    }
}

function gerente_fecha_alta_sql(PDO $pdo): string
{
    return function_exists('plantel_column_exists') && plantel_column_exists($pdo, 'alumnos', 'creado_en')
        ? 'COALESCE(a.creado_en, CONCAT(a.fecha_alta, \' 00:00:00\'))'
        : 'CONCAT(a.fecha_alta, \' 00:00:00\')';
}

/** Ranking de asesores: entrevistas, preregistros, inscritos (semana actual). */
function gerente_podio_asesores(PDO $pdo, ?int $idPlantel = null, ?string $desde = null, ?string $hasta = null): array
{
    asesor_ensure_schema($pdo);
    if (function_exists('preregistro_ensure_schema')) {
        preregistro_ensure_schema($pdo);
    }
    alumno_ensure_schema($pdo);

    [$d0, $d1] = gerente_rango_semana();
    $desde = $desde ?? $d0;
    $hasta = $hasta ?? $d1;
    $hastaFin = $hasta . ' 23:59:59';
    $fechaAlta = gerente_fecha_alta_sql($pdo);

    $filtroPlantel = ($idPlantel !== null && $idPlantel > 0) ? ' AND u.id_plantel = ?' : '';
    $paramsBase = [$desde, $hastaFin, $desde, $hastaFin, $desde, $hastaFin];
    $params = $paramsBase;
    if ($filtroPlantel !== '') {
        $params[] = $idPlantel;
    }

    $activoSql = gerente_sql_usuario_activo('u');
    $sql = "
        SELECT u.id_usuario, u.nombre, u.apellido, p.nombre AS plantel,
               COALESCE(e.cnt, 0) AS entrevistas,
               COALESCE(pr.cnt, 0) AS preregistros,
               COALESCE(ins.cnt, 0) AS inscritos,
               (COALESCE(e.cnt,0)*2 + COALESCE(pr.cnt,0)*3 + COALESCE(ins.cnt,0)*5) AS puntos
        FROM usuarios u
        INNER JOIN planteles p ON p.id_plantel = u.id_plantel
        LEFT JOIN (
            SELECT id_usuario_asesor, COUNT(*) AS cnt FROM asesor_entrevistas
            WHERE creado_en >= ? AND creado_en <= ?
            GROUP BY id_usuario_asesor
        ) e ON e.id_usuario_asesor = u.id_usuario
        LEFT JOIN (
            SELECT id_usuario_registro, COUNT(*) AS cnt FROM preregistros
            WHERE creado_en >= ? AND creado_en <= ?
            GROUP BY id_usuario_registro
        ) pr ON pr.id_usuario_registro = u.id_usuario
        LEFT JOIN (
            SELECT COALESCE(
                CASE WHEN pr.comision_cncm = 1 THEN NULL ELSE pr.id_usuario_asesor END,
                ent.id_usuario_asesor,
                a.id_usuario_asesor,
                pr.id_usuario_registro
            ) AS id_asesor, COUNT(*) AS cnt
            FROM alumnos a
            LEFT JOIN preregistros pr ON pr.id_alumno_vinculado = a.id_alumno
            LEFT JOIN asesor_entrevistas ent ON ent.id_entrevista = pr.id_entrevista_origen
            WHERE {$fechaAlta} >= ? AND {$fechaAlta} <= ?
            GROUP BY id_asesor
        ) ins ON ins.id_asesor = u.id_usuario
        WHERE {$activoSql} AND u.rol IN ('asesor','gerente')
        {$filtroPlantel}
        HAVING entrevistas + preregistros + inscritos > 0
        ORDER BY puntos DESC, inscritos DESC, entrevistas DESC
        LIMIT 25
    ";

    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $ex) {
        return ['desde' => $desde, 'hasta' => $hasta, 'items' => [], 'error' => $ex->getMessage()];
    }

    return ['desde' => $desde, 'hasta' => $hasta, 'items' => $rows];
}

/** Captación: origen, edad, inscripciones por día. */
function gerente_reporte_captacion(PDO $pdo, int $idPlantel, string $desde, string $hasta): array
{
    alumno_ensure_schema($pdo);
    if (function_exists('preregistro_ensure_schema')) {
        preregistro_ensure_schema($pdo);
    }
    asesor_ensure_schema($pdo);

    $out = ['origen' => [], 'edades' => [], 'inscripciones_dia' => [], 'entrevistas_dia' => []];
    $fechaAlta = gerente_fecha_alta_sql($pdo);

    try {
        $st = $pdo->prepare(
            "SELECT COALESCE(NULLIF(TRIM(pr.medio_entero),''), 'otro') AS origen, COUNT(*) AS total
             FROM alumnos a
             LEFT JOIN preregistros pr ON pr.id_alumno_vinculado = a.id_alumno
             WHERE a.id_plantel = ? AND DATE({$fechaAlta}) BETWEEN ? AND ?
             GROUP BY origen ORDER BY total DESC LIMIT 15"
        );
        $st->execute([$idPlantel, $desde, $hasta]);
        $out['origen'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $out['origen_error'] = $e->getMessage();
    }

    try {
        $st = $pdo->prepare(
            "SELECT CASE
                WHEN a.edad IS NULL AND a.fecha_nacimiento IS NULL THEN 'Sin dato'
                WHEN COALESCE(a.edad, TIMESTAMPDIFF(YEAR, a.fecha_nacimiento, CURDATE())) < 18 THEN 'Menores de 18'
                WHEN COALESCE(a.edad, TIMESTAMPDIFF(YEAR, a.fecha_nacimiento, CURDATE())) BETWEEN 18 AND 25 THEN '18-25'
                WHEN COALESCE(a.edad, TIMESTAMPDIFF(YEAR, a.fecha_nacimiento, CURDATE())) BETWEEN 26 AND 35 THEN '26-35'
                WHEN COALESCE(a.edad, TIMESTAMPDIFF(YEAR, a.fecha_nacimiento, CURDATE())) BETWEEN 36 AND 45 THEN '36-45'
                ELSE '46+'
             END AS rango, COUNT(*) AS total
             FROM alumnos a
             WHERE a.id_plantel = ? AND DATE({$fechaAlta}) BETWEEN ? AND ?
             GROUP BY rango ORDER BY total DESC"
        );
        $st->execute([$idPlantel, $desde, $hasta]);
        $out['edades'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
    }

    try {
        $st = $pdo->prepare(
            "SELECT DATE({$fechaAlta}) AS dia, COUNT(*) AS total
             FROM alumnos a WHERE a.id_plantel = ? AND DATE({$fechaAlta}) BETWEEN ? AND ?
             GROUP BY dia ORDER BY dia"
        );
        $st->execute([$idPlantel, $desde, $hasta]);
        $out['inscripciones_dia'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
    }

    try {
        $st = $pdo->prepare(
            "SELECT DATE(creado_en) AS dia, COUNT(*) AS total
             FROM asesor_entrevistas WHERE id_plantel = ? AND DATE(creado_en) BETWEEN ? AND ?
             GROUP BY dia ORDER BY dia"
        );
        $st->execute([$idPlantel, $desde, $hasta]);
        $out['entrevistas_dia'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
    }

    return $out;
}

function gerente_puede_panel(): bool
{
    return function_exists('rbac_cap') && rbac_cap('menu_gerente_dashboard');
}

/** @return list<int> */
function gerente_ids_plantel(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        "SELECT id_usuario FROM usuarios
         WHERE id_plantel = ? AND rol = 'gerente' AND activo = 1
           AND (suspendido IS NULL OR suspendido = 0)"
    );
    $st->execute([$idPlantel]);

    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function gerente_push_notificacion(
    PDO $pdo,
    int $idPlantel,
    string $tipo,
    string $titulo,
    string $mensaje,
    ?string $seccion = null,
    ?string $params = null
): void {
    if (!function_exists('academico_notificar_usuario')) {
        return;
    }
    if (function_exists('academico_ensure_schema')) {
        academico_ensure_schema($pdo);
    }
    foreach (gerente_ids_plantel($pdo, $idPlantel) as $idU) {
        if ($idU <= 0) {
            continue;
        }
        academico_notificar_usuario($pdo, $idU, $tipo, $titulo, $mensaje, $seccion, $params);
    }
}

function gerente_notificar_preregistro_nuevo(PDO $pdo, int $idPlantel, int $idPrereg, int $idRegistro): void
{
    preregistro_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT p.nombres, p.apellido_paterno, p.telefono, e.nombre AS especialidad,
                CONCAT(u.nombre, \' \', u.apellido) AS asesor
         FROM preregistros p
         LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
         WHERE p.id_preregistro = ? AND p.id_plantel = ? LIMIT 1'
    );
    $st->execute([$idPrereg, $idPlantel]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return;
    }
    $nombre = trim(($r['nombres'] ?? '') . ' ' . ($r['apellido_paterno'] ?? ''));
    $esp = trim((string) ($r['especialidad'] ?? ''));
    $asesor = trim((string) ($r['asesor'] ?? 'Equipo'));
    $msg = $nombre;
    if ($esp !== '') {
        $msg .= ' · ' . $esp;
    }
    if (!empty($r['telefono'])) {
        $msg .= ' · ' . $r['telefono'];
    }
    $msg .= ' — registrado por ' . $asesor;

    gerente_push_notificacion(
        $pdo,
        $idPlantel,
        'gerente_preregistro',
        'Nuevo pre-registro',
        $msg,
        'pre_registro_nuevo',
        'id=' . $idPrereg
    );
}

function gerente_notificar_inscripcion(
    PDO $pdo,
    int $idPlantel,
    int $idAlumno,
    int $idGrupo,
    ?int $idPreregistro = null
): void {
    alumno_ensure_schema($pdo);
    if ($idGrupo > 0) {
        $st = $pdo->prepare(
            'SELECT a.numero_control,
                    TRIM(CONCAT(COALESCE(a.nombres, a.nombre, \'\'), \' \', COALESCE(a.apellido_paterno, a.apellido, \'\'))) AS nombre,
                    g.clave AS grupo, e.nombre AS especialidad,
                    CONCAT(u.nombre, \' \', u.apellido) AS asesor
             FROM alumnos a
             LEFT JOIN grupos g ON g.id_grupo = ?
             LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
             LEFT JOIN preregistros pr ON pr.id_preregistro = ?
             LEFT JOIN usuarios u ON u.id_usuario = COALESCE(a.id_usuario_asesor, pr.id_usuario_registro)
             WHERE a.id_alumno = ? LIMIT 1'
        );
        $st->execute([$idGrupo, $idPreregistro ?: 0, $idAlumno]);
    } else {
        $st = $pdo->prepare(
            'SELECT a.numero_control,
                    TRIM(CONCAT(COALESCE(a.nombres, a.nombre, \'\'), \' \', COALESCE(a.apellido_paterno, a.apellido, \'\'))) AS nombre,
                    NULL AS grupo, e.nombre AS especialidad,
                    CONCAT(u.nombre, \' \', u.apellido) AS asesor
             FROM alumnos a
             LEFT JOIN especialidades e ON e.id_especialidad = a.id_especialidad
             LEFT JOIN preregistros pr ON pr.id_preregistro = ?
             LEFT JOIN usuarios u ON u.id_usuario = COALESCE(a.id_usuario_asesor, pr.id_usuario_registro)
             WHERE a.id_alumno = ? LIMIT 1'
        );
        $st->execute([$idPreregistro ?: 0, $idAlumno]);
    }
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return;
    }
    $msg = trim($r['nombre'] ?? 'Alumno');
    if (!empty($r['numero_control'])) {
        $msg .= ' (No. ' . $r['numero_control'] . ')';
    }
    if (!empty($r['especialidad'])) {
        $msg .= ' · ' . $r['especialidad'];
    }
    if (!empty($r['grupo'])) {
        $msg .= ' · Grupo ' . $r['grupo'];
    }
    if (!empty($r['asesor'])) {
        $msg .= ' — ' . $r['asesor'];
    }

    gerente_push_notificacion(
        $pdo,
        $idPlantel,
        'gerente_inscripcion',
        $idGrupo > 0 ? 'Nueva inscripción' : 'Inscripción (ubicación)',
        $msg,
        'alumno_detalle',
        'id=' . $idAlumno
    );
}

/**
 * Alertas en panel inicio para gerente (todo el plantel).
 *
 * @return list<array{tipo:string,titulo:string,mensaje:string,enlace?:string,prioridad:string}>
 */
function gerente_notificaciones_panel(PDO $pdo, int $idPlantel): array
{
    preregistro_ensure_schema($pdo);
    alumno_ensure_schema($pdo);
    $items = [];
    $fechaAlta = gerente_fecha_alta_sql($pdo);
    $labelsPend = preregistro_labels()['categoria_pendiente'] ?? [];

    $stIns = $pdo->prepare(
        "SELECT COUNT(*) FROM alumnos a
         WHERE a.id_plantel = ? AND DATE({$fechaAlta}) = CURDATE()"
    );
    $stIns->execute([$idPlantel]);
    $insHoy = (int) $stIns->fetchColumn();
    if ($insHoy > 0) {
        $items[] = [
            'tipo' => 'inscripciones_hoy',
            'titulo' => 'Inscripciones hoy',
            'mensaje' => $insHoy . ' alumno(s) inscrito(s) hoy en este plantel',
            'enlace' => 'gerente_reportes_captacion',
            'prioridad' => 'alta',
        ];
    }

    $stPr = $pdo->prepare(
        "SELECT COUNT(*) FROM preregistros
         WHERE id_plantel = ? AND creado_en >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
           AND estado IN ('activo','pendiente')"
    );
    $stPr->execute([$idPlantel]);
    $prRecientes = (int) $stPr->fetchColumn();
    if ($prRecientes > 0) {
        $items[] = [
            'tipo' => 'prereg_recientes',
            'titulo' => 'Pre-registros recientes',
            'mensaje' => $prRecientes . ' pre-registro(s) del equipo en las últimas 48 h',
            'enlace' => 'gerente_reporte_pendientes',
            'prioridad' => 'media',
        ];
    }

    $stPend = $pdo->prepare(
        "SELECT p.id_preregistro, p.nombres, p.apellido_paterno, p.categoria_pendiente, p.motivo_pendiente,
                CONCAT(u.nombre, ' ', u.apellido) AS asesor
         FROM preregistros p
         LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
         WHERE p.id_plantel = ? AND p.estado = 'pendiente'
           AND (p.fecha_recordatorio IS NULL OR p.fecha_recordatorio <= CURDATE())
         ORDER BY p.fecha_recordatorio ASC, p.creado_en DESC
         LIMIT 8"
    );
    $stPend->execute([$idPlantel]);
    foreach ($stPend->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cat = $labelsPend[$r['categoria_pendiente'] ?? ''] ?? 'Pendiente';
        $msg = trim(($r['nombres'] ?? '') . ' ' . ($r['apellido_paterno'] ?? ''));
        $msg .= ' (' . ($r['asesor'] ?? '—') . ') — ' . $cat;
        if (!empty($r['motivo_pendiente'])) {
            $msg .= ': ' . mb_substr((string) $r['motivo_pendiente'], 0, 80);
        }
        $items[] = [
            'tipo' => 'pendiente_plantel',
            'titulo' => 'Seguimiento pendiente',
            'mensaje' => $msg,
            'enlace' => 'pre_registro_nuevo&id=' . (int) $r['id_preregistro'],
            'prioridad' => 'alta',
        ];
    }

    $stEsp = $pdo->prepare(
        "SELECT COUNT(*) FROM preregistros
         WHERE id_plantel = ? AND espera_apertura_curso = 1 AND estado IN ('activo','pendiente')"
    );
    $stEsp->execute([$idPlantel]);
    $espera = (int) $stEsp->fetchColumn();
    if ($espera > 0) {
        $items[] = [
            'tipo' => 'espera_apertura',
            'titulo' => 'Esperan apertura de curso',
            'mensaje' => $espera . ' prospecto(s) aguardan que se abra un curso',
            'enlace' => 'gerente_reporte_proyeccion',
            'prioridad' => 'media',
        ];
    }

    $stPerd = $pdo->prepare(
        "SELECT COUNT(*) FROM preregistros
         WHERE id_plantel = ? AND estado = 'perdido'
           AND fecha_estado >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
    );
    $stPerd->execute([$idPlantel]);
    $perdidos = (int) $stPerd->fetchColumn();
    if ($perdidos > 0) {
        $items[] = [
            'tipo' => 'perdidos_semana',
            'titulo' => 'No inscritos (7 días)',
            'mensaje' => $perdidos . ' prospecto(s) marcados como perdidos esta semana — revise motivos',
            'enlace' => 'gerente_reporte_proyeccion',
            'prioridad' => 'media',
        ];
    }

    return $items;
}

/**
 * Proyección de demanda: cursos, horarios y sugerencias.
 *
 * @return array<string, mixed>
 */
function gerente_reporte_proyeccion(PDO $pdo, int $idPlantel, string $desde, string $hasta): array
{
    preregistro_ensure_schema($pdo);
    alumno_ensure_schema($pdo);
    catalog_ensure_schema($pdo);

    $out = [
        'desde' => $desde,
        'hasta' => $hasta,
        'cursos_demandados' => [],
        'espera_apertura' => [],
        'horarios_populares' => [],
        'sin_horario' => [],
        'motivos_perdido' => [],
        'sugerencias' => [],
    ];
    $fechaAlta = gerente_fecha_alta_sql($pdo);
    $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    try {
        $st = $pdo->prepare(
            "SELECT COALESCE(e.nombre, 'Sin especialidad') AS curso,
                    COALESCE(e.inscripcion_abierta, 1) AS abierto,
                    COUNT(*) AS total
             FROM preregistros p
             LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
             WHERE p.id_plantel = ? AND p.creado_en >= ? AND p.creado_en <= ?
               AND p.estado IN ('activo','pendiente','inscrito')
             GROUP BY p.id_especialidad, e.nombre, e.inscripcion_abierta
             ORDER BY total DESC LIMIT 15"
        );
        $st->execute([$idPlantel, $desde, $hasta . ' 23:59:59']);
        $out['cursos_demandados'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $out['error_cursos'] = $e->getMessage();
    }

    try {
        $st = $pdo->prepare(
            "SELECT e.nombre AS curso, COUNT(*) AS total,
                    MIN(e.fecha_apertura_prevista) AS apertura_prevista
             FROM preregistros p
             INNER JOIN especialidades e ON e.id_especialidad = p.id_especialidad
             WHERE p.id_plantel = ? AND p.espera_apertura_curso = 1
               AND p.estado IN ('activo','pendiente')
             GROUP BY e.id_especialidad, e.nombre
             ORDER BY total DESC"
        );
        $st->execute([$idPlantel]);
        $out['espera_apertura'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
    }

    try {
        $st = $pdo->prepare(
            "SELECT gh.dia_semana, gh.hora_inicio, COUNT(DISTINCT a.id_alumno) AS total
             FROM alumnos a
             INNER JOIN alumno_grupos ag ON ag.id_alumno = a.id_alumno AND ag.activo = 1
             INNER JOIN grupo_horarios gh ON gh.id_grupo = ag.id_grupo AND gh.activo = 1
             WHERE a.id_plantel = ? AND {$fechaAlta} >= ? AND {$fechaAlta} <= ?
             GROUP BY gh.dia_semana, gh.hora_inicio
             ORDER BY total DESC LIMIT 12"
        );
        $st->execute([$idPlantel, $desde, $hasta . ' 23:59:59']);
        $horarios = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($horarios as &$h) {
            $d = (int) ($h['dia_semana'] ?? 0);
            $hi = substr((string) ($h['hora_inicio'] ?? ''), 0, 5);
            $h['etiqueta'] = ($dias[$d] ?? '?') . ' ' . $hi;
        }
        unset($h);
        $out['horarios_populares'] = $horarios;
    } catch (PDOException $e) {
    }

    try {
        $st = $pdo->prepare(
            "SELECT p.nombres, p.apellido_paterno, p.motivo_pendiente,
                    e.nombre AS curso, CONCAT(u.nombre, ' ', u.apellido) AS asesor
             FROM preregistros p
             LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
             LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
             WHERE p.id_plantel = ? AND p.estado = 'pendiente'
               AND p.categoria_pendiente = 'sin_horario'
             ORDER BY p.creado_en DESC LIMIT 20"
        );
        $st->execute([$idPlantel]);
        $out['sin_horario'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
    }

    try {
        $st = $pdo->prepare(
            "SELECT COALESCE(NULLIF(TRIM(categoria_perdido),''), 'otro') AS motivo, COUNT(*) AS total
             FROM preregistros
             WHERE id_plantel = ? AND estado = 'perdido'
               AND fecha_estado >= ? AND fecha_estado <= ?
             GROUP BY motivo ORDER BY total DESC"
        );
        $st->execute([$idPlantel, $desde, $hasta . ' 23:59:59']);
        $out['motivos_perdido'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
    }

    $labelsPerd = preregistro_labels()['categoria_perdido'] ?? [];
    $sugerencias = [];

    foreach ($out['cursos_demandados'] as $c) {
        if ((int) ($c['abierto'] ?? 1) === 0 && (int) ($c['total'] ?? 0) >= 3) {
            $sugerencias[] = 'Hay ' . (int) $c['total'] . ' interesados en «' . ($c['curso'] ?? '') . '» pero la inscripción está cerrada — considere abrir grupo o avisar cuando haya fecha.';
        }
    }
    foreach ($out['espera_apertura'] as $e) {
        if ((int) ($e['total'] ?? 0) >= 2) {
            $ap = !empty($e['apertura_prevista']) ? ' (apertura prevista ' . date('d/m/Y', strtotime($e['apertura_prevista'])) . ')' : '';
            $sugerencias[] = (int) $e['total'] . ' prospecto(s) esperan «' . ($e['curso'] ?? '') . '»' . $ap . ' — programe contacto al abrir inscripción.';
        }
    }
    if (!empty($out['horarios_populares'][0])) {
        $top = $out['horarios_populares'][0];
        $sugerencias[] = 'Horario con más inscripciones recientes: ' . ($top['etiqueta'] ?? '') . ' (' . (int) ($top['total'] ?? 0) . ' alumnos) — considere replicar este horario.';
    }
    $nSin = count($out['sin_horario']);
    if ($nSin >= 3) {
        $sugerencias[] = $nSin . ' prospectos pendientes por falta de horario compatible — evalúe abrir grupos en turnos vespertinos o fines de semana.';
    }
    if (!empty($out['motivos_perdido'][0])) {
        $m0 = $out['motivos_perdido'][0];
        $lbl = $labelsPerd[$m0['motivo'] ?? ''] ?? ($m0['motivo'] ?? '');
        if ((int) ($m0['total'] ?? 0) >= 2 && $lbl !== '') {
            $sugerencias[] = 'Motivo principal de no inscripción: «' . $lbl . '» (' . (int) $m0['total'] . ' casos) — ajuste estrategia comercial.';
        }
    }
    if ($sugerencias === []) {
        $sugerencias[] = 'Sin patrones fuertes en el periodo seleccionado. Amplíe el rango de fechas o registre más pre-registros con especialidad y pendientes.';
    }

    $out['sugerencias'] = $sugerencias;

    return $out;
}

/** Normaliza etiqueta geográfica para agrupación. */
function gerente_geo_etiqueta(?string $valor, string $sinDato = 'Sin dato'): string
{
    $v = trim((string) $valor);
    if ($v === '') {
        return $sinDato;
    }

    return mb_convert_case(mb_strtolower($v, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

/**
 * Reporte geográfico: colonias, municipios y CP desde pre-registros e inscritos.
 *
 * @param 'preregistros'|'inscritos'|'ambos' $fuente
 * @return array<string, mixed>
 */
function gerente_reporte_geografico(
    PDO $pdo,
    int $idPlantel,
    string $desde,
    string $hasta,
    string $fuente = 'ambos'
): array {
    preregistro_ensure_schema($pdo);
    alumno_ensure_schema($pdo);

    $hastaFin = $hasta . ' 23:59:59';
    $fechaAlta = gerente_fecha_alta_sql($pdo);
    $out = [
        'desde' => $desde,
        'hasta' => $hasta,
        'fuente' => $fuente,
        'resumen' => [
            'preregistros_total' => 0,
            'preregistros_con_municipio' => 0,
            'inscritos_total' => 0,
            'inscritos_con_municipio' => 0,
        ],
        'municipios_prereg' => [],
        'colonias_prereg' => [],
        'cp_prereg' => [],
        'municipios_inscritos' => [],
        'colonias_inscritos' => [],
        'cp_inscritos' => [],
        'colonias_por_municipio' => [],
    ];

    if ($fuente === 'preregistros' || $fuente === 'ambos') {
        try {
            $st = $pdo->prepare(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN NULLIF(TRIM(municipio), '') IS NOT NULL THEN 1 ELSE 0 END) AS con_municipio
                 FROM preregistros
                 WHERE id_plantel = ? AND creado_en >= ? AND creado_en <= ?"
            );
            $st->execute([$idPlantel, $desde, $hastaFin]);
            $sum = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $out['resumen']['preregistros_total'] = (int) ($sum['total'] ?? 0);
            $out['resumen']['preregistros_con_municipio'] = (int) ($sum['con_municipio'] ?? 0);

            $st = $pdo->prepare(
                "SELECT COALESCE(NULLIF(TRIM(municipio), ''), 'Sin dato') AS municipio, COUNT(*) AS total
                 FROM preregistros
                 WHERE id_plantel = ? AND creado_en >= ? AND creado_en <= ?
                 GROUP BY municipio ORDER BY total DESC, municipio ASC LIMIT 25"
            );
            $st->execute([$idPlantel, $desde, $hastaFin]);
            $out['municipios_prereg'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $st = $pdo->prepare(
                "SELECT COALESCE(NULLIF(TRIM(municipio), ''), 'Sin dato') AS municipio,
                        COALESCE(NULLIF(TRIM(colonia), ''), 'Sin colonia') AS colonia,
                        COUNT(*) AS total
                 FROM preregistros
                 WHERE id_plantel = ? AND creado_en >= ? AND creado_en <= ?
                 GROUP BY municipio, colonia ORDER BY total DESC LIMIT 40"
            );
            $st->execute([$idPlantel, $desde, $hastaFin]);
            $out['colonias_prereg'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $st = $pdo->prepare(
                "SELECT COALESCE(NULLIF(TRIM(codigo_postal), ''), 'Sin CP') AS cp,
                        COALESCE(NULLIF(TRIM(municipio), ''), 'Sin dato') AS municipio,
                        COUNT(*) AS total
                 FROM preregistros
                 WHERE id_plantel = ? AND creado_en >= ? AND creado_en <= ?
                 GROUP BY cp, municipio ORDER BY total DESC LIMIT 20"
            );
            $st->execute([$idPlantel, $desde, $hastaFin]);
            $out['cp_prereg'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $st = $pdo->prepare(
                "SELECT COALESCE(NULLIF(TRIM(municipio), ''), 'Sin dato') AS municipio,
                        COALESCE(NULLIF(TRIM(colonia), ''), 'Sin colonia') AS colonia,
                        COUNT(*) AS total
                 FROM preregistros
                 WHERE id_plantel = ? AND creado_en >= ? AND creado_en <= ?
                   AND NULLIF(TRIM(municipio), '') IS NOT NULL
                 GROUP BY municipio, colonia
                 HAVING total >= 1
                 ORDER BY municipio ASC, total DESC"
            );
            $st->execute([$idPlantel, $desde, $hastaFin]);
            $porMun = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $mun = gerente_geo_etiqueta($row['municipio'] ?? '', 'Sin dato');
                if (!isset($porMun[$mun])) {
                    $porMun[$mun] = [];
                }
                if (count($porMun[$mun]) < 5) {
                    $porMun[$mun][] = [
                        'colonia' => gerente_geo_etiqueta($row['colonia'] ?? '', 'Sin colonia'),
                        'total' => (int) ($row['total'] ?? 0),
                    ];
                }
            }
            $out['colonias_por_municipio'] = $porMun;
        } catch (PDOException $e) {
            $out['error_prereg'] = $e->getMessage();
        }
    }

    if ($fuente === 'inscritos' || $fuente === 'ambos') {
        $geoJoin = "
            LEFT JOIN preregistros pr ON pr.id_preregistro = a.id_preregistro
            LEFT JOIN preregistros pr2 ON pr2.id_alumno_vinculado = a.id_alumno AND a.id_preregistro IS NULL
        ";
        $munExpr = "COALESCE(NULLIF(TRIM(pr.municipio), ''), NULLIF(TRIM(pr2.municipio), ''), 'Sin dato')";
        $colExpr = "COALESCE(NULLIF(TRIM(pr.colonia), ''), NULLIF(TRIM(pr2.colonia), ''), 'Sin colonia')";
        $cpExpr = "COALESCE(NULLIF(TRIM(pr.codigo_postal), ''), NULLIF(TRIM(pr2.codigo_postal), ''), 'Sin CP')";

        try {
            $st = $pdo->prepare(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN {$munExpr} <> 'Sin dato' THEN 1 ELSE 0 END) AS con_municipio
                 FROM alumnos a
                 {$geoJoin}
                 WHERE a.id_plantel = ? AND {$fechaAlta} >= ? AND {$fechaAlta} <= ?"
            );
            $st->execute([$idPlantel, $desde, $hastaFin]);
            $sum = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $out['resumen']['inscritos_total'] = (int) ($sum['total'] ?? 0);
            $out['resumen']['inscritos_con_municipio'] = (int) ($sum['con_municipio'] ?? 0);

            $st = $pdo->prepare(
                "SELECT {$munExpr} AS municipio, COUNT(*) AS total
                 FROM alumnos a
                 {$geoJoin}
                 WHERE a.id_plantel = ? AND {$fechaAlta} >= ? AND {$fechaAlta} <= ?
                 GROUP BY municipio ORDER BY total DESC, municipio ASC LIMIT 25"
            );
            $st->execute([$idPlantel, $desde, $hastaFin]);
            $out['municipios_inscritos'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $st = $pdo->prepare(
                "SELECT {$munExpr} AS municipio, {$colExpr} AS colonia, COUNT(*) AS total
                 FROM alumnos a
                 {$geoJoin}
                 WHERE a.id_plantel = ? AND {$fechaAlta} >= ? AND {$fechaAlta} <= ?
                 GROUP BY municipio, colonia ORDER BY total DESC LIMIT 40"
            );
            $st->execute([$idPlantel, $desde, $hastaFin]);
            $out['colonias_inscritos'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $st = $pdo->prepare(
                "SELECT {$cpExpr} AS cp, {$munExpr} AS municipio, COUNT(*) AS total
                 FROM alumnos a
                 {$geoJoin}
                 WHERE a.id_plantel = ? AND {$fechaAlta} >= ? AND {$fechaAlta} <= ?
                 GROUP BY cp, municipio ORDER BY total DESC LIMIT 20"
            );
            $st->execute([$idPlantel, $desde, $hastaFin]);
            $out['cp_inscritos'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            $out['error_inscritos'] = $e->getMessage();
        }
    }

    return $out;
}

function gerente_geo_pct(int $parte, int $total): string
{
    if ($total <= 0) {
        return '—';
    }

    return number_format(100 * $parte / $total, 1) . '%';
}

/** Asesores activos del plantel. */
function gerente_asesores_plantel(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        "SELECT id_usuario, nombre, apellido, rol
         FROM usuarios
         WHERE id_plantel = ? AND rol IN ('asesor','gerente')
           AND (suspendido IS NULL OR suspendido = 0)
         ORDER BY nombre, apellido"
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Pendientes de seguimiento de todos los asesores del plantel.
 *
 * @return array{items:list<array>, resumen_asesor:list<array>, total:int}
 */
function gerente_reporte_pendientes(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    preregistro_ensure_schema($pdo);
    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'preregistros', 'fecha_compromiso_contacto', 'DATE NULL', 'espera_apertura_curso');
    }
    $labels = preregistro_labels();

    $params = [$idPlantel];
    $sql = "SELECT p.*, CONCAT(u.nombre, ' ', u.apellido) AS asesor_nombre,
                   e.nombre AS especialidad_nombre
            FROM preregistros p
            INNER JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
            LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
            WHERE p.id_plantel = ? AND p.estado IN ('activo','pendiente')";

    if (!empty($filtros['id_asesor'])) {
        $sql .= ' AND p.id_usuario_registro = ?';
        $params[] = (int) $filtros['id_asesor'];
    }
    if (!empty($filtros['categoria_pendiente'])) {
        $sql .= ' AND p.categoria_pendiente = ?';
        $params[] = $filtros['categoria_pendiente'];
    }
    if (!empty($filtros['solo_vencidos'])) {
        $sql .= " AND (
            (p.estado = 'pendiente' AND (p.fecha_recordatorio IS NULL OR p.fecha_recordatorio <= CURDATE()))
            OR (p.fecha_compromiso_contacto IS NOT NULL AND p.fecha_compromiso_contacto <= CURDATE())
            OR p.espera_apertura_curso = 1
        )";
    }

    $sql .= ' ORDER BY COALESCE(p.fecha_recordatorio, p.fecha_compromiso_contacto, p.creado_en) ASC, p.creado_en DESC LIMIT 500';

    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return ['items' => [], 'resumen_asesor' => [], 'total' => 0, 'error' => $e->getMessage()];
    }

    $resumen = [];
    foreach ($items as $row) {
        $idA = (int) ($row['id_usuario_registro'] ?? 0);
        if (!isset($resumen[$idA])) {
            $resumen[$idA] = [
                'id_usuario' => $idA,
                'asesor' => $row['asesor_nombre'] ?? '',
                'total' => 0,
            ];
        }
        $resumen[$idA]['total']++;
    }

    return [
        'items' => $items,
        'resumen_asesor' => array_values($resumen),
        'total' => count($items),
        'labels' => $labels,
    ];
}

/**
 * Prospectos perdidos / no inscritos con motivos.
 *
 * @return array<string, mixed>
 */
function gerente_reporte_perdidos(PDO $pdo, int $idPlantel, string $desde, string $hasta, ?int $idAsesor = null): array
{
    preregistro_ensure_schema($pdo);
    $labels = preregistro_labels();
    $hastaFin = $hasta . ' 23:59:59';

    $params = [$idPlantel, $desde, $hastaFin];
    $filtroAsesor = '';
    if ($idAsesor !== null && $idAsesor > 0) {
        $filtroAsesor = ' AND p.id_usuario_registro = ?';
        $params[] = $idAsesor;
    }

    $out = ['items' => [], 'por_motivo' => [], 'por_asesor' => [], 'total' => 0, 'labels' => $labels];

    try {
        $st = $pdo->prepare(
            "SELECT p.*, CONCAT(u.nombre, ' ', u.apellido) AS asesor_nombre,
                    e.nombre AS especialidad_nombre
             FROM preregistros p
             INNER JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
             LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
             WHERE p.id_plantel = ? AND p.estado = 'perdido'
               AND COALESCE(p.fecha_estado, p.actualizado_en, p.creado_en) >= ?
               AND COALESCE(p.fecha_estado, p.actualizado_en, p.creado_en) <= ?
             {$filtroAsesor}
             ORDER BY COALESCE(p.fecha_estado, p.actualizado_en) DESC LIMIT 300"
        );
        $st->execute($params);
        $out['items'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out['total'] = count($out['items']);

        $st2 = $pdo->prepare(
            "SELECT COALESCE(NULLIF(TRIM(p.categoria_perdido), ''), 'otro') AS motivo, COUNT(*) AS total
             FROM preregistros p
             WHERE p.id_plantel = ? AND p.estado = 'perdido'
               AND COALESCE(p.fecha_estado, p.actualizado_en, p.creado_en) >= ?
               AND COALESCE(p.fecha_estado, p.actualizado_en, p.creado_en) <= ?
             {$filtroAsesor}
             GROUP BY motivo ORDER BY total DESC"
        );
        $st2->execute($params);
        $out['por_motivo'] = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $st3 = $pdo->prepare(
            "SELECT p.id_usuario_registro, CONCAT(u.nombre, ' ', u.apellido) AS asesor, COUNT(*) AS total
             FROM preregistros p
             INNER JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
             WHERE p.id_plantel = ? AND p.estado = 'perdido'
               AND COALESCE(p.fecha_estado, p.actualizado_en, p.creado_en) >= ?
               AND COALESCE(p.fecha_estado, p.actualizado_en, p.creado_en) <= ?
             GROUP BY p.id_usuario_registro, asesor ORDER BY total DESC"
        );
        $st3->execute([$idPlantel, $desde, $hastaFin]);
        $out['por_asesor'] = $st3->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $out['error'] = $e->getMessage();
    }

    return $out;
}

/** Resumen matriz HAY del equipo de ventas del plantel. */
function gerente_matriz_resumen_equipo(PDO $pdo, int $idPlantel, ?string $periodo = null): array
{
    if (!function_exists('hay_eval_matriz_usuario')) {
        return ['ok' => false, 'message' => 'Módulo HAY no disponible'];
    }
    hay_eval_ensure_schema($pdo);
    hay_eval_asegurar_area_asesor($pdo);

    $periodo = preg_match('/^\d{4}-\d{2}$/', (string) $periodo) ? $periodo : date('Y-m');
    $area = hay_eval_area_por_clave($pdo, 'ASESOR_VENTAS');
    $idArea = (int) ($area['id_area'] ?? 0);

    $equipo = [];
    foreach (gerente_asesores_plantel($pdo, $idPlantel) as $u) {
        $idU = (int) $u['id_usuario'];
        $matriz = hay_eval_matriz_usuario($pdo, $idU, $periodo);
        $caps = $matriz['capacitaciones'] ?? [];
        $total = count($caps);
        $hechas = 0;
        foreach ($caps as $c) {
            if (!empty($c['completada'])) {
                $hechas++;
            }
        }
        $resumen = hay_eval_resumen_portal_colaborador($pdo, $idU);
        $equipo[] = [
            'id_usuario' => $idU,
            'nombre' => trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')),
            'rol' => $u['rol'] ?? '',
            'capacitaciones_total' => $total,
            'capacitaciones_hechas' => $hechas,
            'pct' => $total > 0 ? round(100 * $hechas / $total) : 0,
            'nivel' => $resumen['nivel_actual'] ?? '—',
            'puntos' => (int) ($resumen['puntos_actuales'] ?? 0),
            'pendientes_cap' => (int) ($resumen['capacitaciones_pendientes'] ?? 0),
        ];
    }

    usort($equipo, static fn($a, $b) => ($b['pct'] <=> $a['pct']) ?: strcmp($a['nombre'], $b['nombre']));

    return [
        'ok' => true,
        'periodo' => $periodo,
        'id_area' => $idArea,
        'area_nombre' => $area['nombre'] ?? 'Asesor de ventas',
        'equipo' => $equipo,
    ];
}

function marketing_banners_listar(PDO $pdo, string $audiencia = 'alumno'): array
{
    if (function_exists('tour_ensure_schema')) {
        tour_ensure_schema($pdo);
    }
    $hoy = date('Y-m-d');
    try {
        $st = $pdo->prepare(
            "SELECT * FROM marketing_banner
             WHERE activo = 1 AND (audiencia = ? OR audiencia = 'todos')
               AND (vigente_desde IS NULL OR vigente_desde <= ?)
               AND (vigente_hasta IS NULL OR vigente_hasta >= ?)
             ORDER BY orden ASC, id_banner DESC"
        );
        $st->execute([$audiencia, $hoy, $hoy]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}
