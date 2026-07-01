<?php

function reporte_inscritos_puede_ver(): bool
{
    if (function_exists('rbac_usuario_en_roles') && rbac_usuario_en_roles(['asesor', 'gerente', 'supervisor', 'admin'])) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('menu_reporte_inscritos')) {
        return true;
    }

    return false;
}

/**
 * @param array{id_usuario_asesor?:int,desde?:string,hasta?:string} $filtros
 * @return array{filas:list<array>,resumen:array}
 */
function reporte_inscritos_listar(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    if (function_exists('alumno_ensure_schema')) {
        alumno_ensure_schema($pdo);
    }
    if (function_exists('preregistro_ensure_schema')) {
        preregistro_ensure_schema($pdo);
    }
    referido_ensure_schema($pdo);

    $params = [$idPlantel];
    $fechaAltaExpr = function_exists('plantel_column_exists') && plantel_column_exists($pdo, 'alumnos', 'creado_en')
        ? 'COALESCE(a.creado_en, CONCAT(a.fecha_alta, \' 00:00:00\'))'
        : 'CONCAT(a.fecha_alta, \' 00:00:00\')';
    $joinReferido = plantel_table_exists($pdo, 'inscripcion_referidos')
        ? 'LEFT JOIN inscripcion_referidos ir ON ir.id_alumno_inscrito = a.id_alumno
            LEFT JOIN alumnos ar ON ar.id_alumno = ir.id_alumno_referidor'
        : '';
    $colsReferido = plantel_table_exists($pdo, 'inscripcion_referidos')
        ? 'ir.id_referido, ir.monto_beneficio,
           ar.numero_control AS referidor_control,
           TRIM(CONCAT(ar.nombres, \' \', ar.apellido_paterno)) AS referidor_nombre'
        : 'NULL AS id_referido, NULL AS monto_beneficio,
           NULL AS referidor_control, NULL AS referidor_nombre';
    $sql = "SELECT a.id_alumno, a.numero_control, {$fechaAltaExpr} AS fecha_alta,
                   TRIM(CONCAT(a.nombres, ' ', a.apellido_paterno, ' ', IFNULL(a.apellido_materno,''))) AS nombre,
                   e.nombre AS especialidad,
                   pr.id_usuario_registro AS id_asesor_captura,
                   CONCAT(ua.nombre, ' ', ua.apellido) AS asesor_captura_nombre,
                   COALESCE(
                       CASE WHEN pr.comision_cncm = 1 THEN NULL ELSE pr.id_usuario_asesor END,
                       ent.id_usuario_asesor,
                       a.id_usuario_asesor,
                       pr.id_usuario_registro
                   ) AS id_asesor_comision,
                   CASE WHEN pr.comision_cncm = 1 THEN 'CNCM'
                        ELSE COALESCE(
                            CONCAT(uc.nombre, ' ', uc.apellido),
                            CONCAT(ue.nombre, ' ', ue.apellido),
                            CONCAT(ua.nombre, ' ', ua.apellido)
                        )
                   END AS asesor_nombre,
                   pr.comision_cncm,
                   {$colsReferido}
            FROM alumnos a
            LEFT JOIN especialidades e ON e.id_especialidad = a.id_especialidad
            LEFT JOIN preregistros pr ON pr.id_alumno_vinculado = a.id_alumno
            LEFT JOIN usuarios ua ON ua.id_usuario = pr.id_usuario_registro
            LEFT JOIN asesor_entrevistas ent ON ent.id_entrevista = pr.id_entrevista_origen
            LEFT JOIN usuarios uc ON uc.id_usuario = pr.id_usuario_asesor
            LEFT JOIN usuarios ue ON ue.id_usuario = ent.id_usuario_asesor
            {$joinReferido}
            WHERE a.id_plantel = ?";

    if (!empty($filtros['id_usuario_asesor'])) {
        $sql .= ' AND COALESCE(
            CASE WHEN pr.comision_cncm = 1 THEN NULL ELSE pr.id_usuario_asesor END,
            ent.id_usuario_asesor,
            a.id_usuario_asesor,
            pr.id_usuario_registro
        ) = ?';
        $params[] = (int) $filtros['id_usuario_asesor'];
    }
    if (!empty($filtros['desde'])) {
        $sql .= " AND DATE({$fechaAltaExpr}) >= ?";
        $params[] = $filtros['desde'];
    }
    if (!empty($filtros['hasta'])) {
        $sql .= " AND DATE({$fechaAltaExpr}) <= ?";
        $params[] = $filtros['hasta'];
    }

    $sql .= " ORDER BY {$fechaAltaExpr} DESC LIMIT 500";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);

    $total = count($filas);
    $conReferido = 0;
    foreach ($filas as $f) {
        if (!empty($f['id_referido'])) {
            $conReferido++;
        }
    }

    return [
        'filas' => $filas,
        'resumen' => [
            'total' => $total,
            'con_referido' => $conReferido,
            'sin_referido' => $total - $conReferido,
        ],
    ];
}
