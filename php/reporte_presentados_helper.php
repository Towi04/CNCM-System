<?php

function reporte_presentados_puede_ver(): bool
{
    if (function_exists('rbac_usuario_en_roles') && rbac_usuario_en_roles(['gerente', 'supervisor', 'admin'])) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_reporte_presentados');
}

/**
 * Primer día con clase según horario del grupo (a partir de fecha_inicio).
 */
function reporte_presentados_primer_dia_clase(PDO $pdo, int $idGrupo): ?string
{
    if (!function_exists('reporte_semanal_fechas_clase')) {
        return null;
    }
    $st = $pdo->prepare('SELECT fecha_inicio FROM grupos WHERE id_grupo = ? LIMIT 1');
    $st->execute([$idGrupo]);
    $fechaInicio = $st->fetchColumn();
    if (!$fechaInicio) {
        return null;
    }
    $inicio = (string) $fechaInicio;
    $fin = date('Y-m-d', strtotime($inicio . ' +90 days'));
    $fechas = reporte_semanal_fechas_clase($pdo, $idGrupo, $inicio, $fin);

    return $fechas[0] ?? null;
}

/**
 * Expresión SQL para resolver asesor de comisión (misma lógica que reporte_inscritos).
 */
function reporte_presentados_sql_asesor_comision(): string
{
    return 'COALESCE(
        CASE WHEN pr.comision_cncm = 1 THEN NULL ELSE pr.id_usuario_asesor END,
        ent.id_usuario_asesor,
        a.id_usuario_asesor,
        pr.id_usuario_registro
    )';
}

/**
 * @param array{id_usuario_asesor?:int,desde?:string,hasta?:string} $filtros
 * @return array{filas:list<array>,resumen:array}
 */
function reporte_presentados_listar(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    if (function_exists('alumno_ensure_schema')) {
        alumno_ensure_schema($pdo);
    }
    if (function_exists('preregistro_ensure_schema')) {
        preregistro_ensure_schema($pdo);
    }
    if (function_exists('asistencia_ensure_schema')) {
        asistencia_ensure_schema($pdo);
    }

    $desde = !empty($filtros['desde']) ? (string) $filtros['desde'] : date('Y-m-01');
    $hasta = !empty($filtros['hasta']) ? (string) $filtros['hasta'] : date('Y-m-d', strtotime('+3 months'));
    $idAsesor = !empty($filtros['id_usuario_asesor']) ? (int) $filtros['id_usuario_asesor'] : 0;

    $stGrupos = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.fecha_inicio, e.nombre AS especialidad
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_plantel = ? AND g.fecha_inicio BETWEEN ? AND ?
         ORDER BY g.fecha_inicio ASC, g.clave ASC'
    );
    $stGrupos->execute([$idPlantel, $desde, $hasta]);
    $grupos = $stGrupos->fetchAll(PDO::FETCH_ASSOC);

    $asesorExpr = reporte_presentados_sql_asesor_comision();
    $fechaAltaExpr = function_exists('plantel_column_exists') && plantel_column_exists($pdo, 'alumnos', 'creado_en')
        ? 'COALESCE(a.creado_en, CONCAT(a.fecha_alta, \' 00:00:00\'))'
        : 'CONCAT(a.fecha_alta, \' 00:00:00\')';
    $fechaInscripcionExpr = 'COALESCE(ag.fecha_inicio, DATE(' . $fechaAltaExpr . '))';

    $filas = [];
    $totalInscritos = 0;
    $totalPresentados = 0;
    $gruposConClase = 0;
    $gruposSinHorario = 0;

    foreach ($grupos as $grupo) {
        $idGrupo = (int) $grupo['id_grupo'];
        $primerDia = reporte_presentados_primer_dia_clase($pdo, $idGrupo);
        if ($primerDia === null) {
            $gruposSinHorario++;
            continue;
        }
        $gruposConClase++;

        $sql = "SELECT a.id_alumno, a.numero_control, {$fechaInscripcionExpr} AS fecha_inscripcion,
                       TRIM(CONCAT(a.nombres, ' ', a.apellido_paterno, ' ', IFNULL(a.apellido_materno,''))) AS nombre,
                       {$asesorExpr} AS id_asesor_comision,
                       CASE WHEN pr.comision_cncm = 1 THEN 'CNCM'
                            ELSE COALESCE(
                                CONCAT(uc.nombre, ' ', uc.apellido),
                                CONCAT(ue.nombre, ' ', ue.apellido),
                                CONCAT(ua.nombre, ' ', ua.apellido)
                            )
                       END AS asesor_nombre,
                       (SELECT ast.presente FROM asistencias ast
                        WHERE ast.id_alumno = a.id_alumno AND ast.id_grupo = ag.id_grupo
                          AND ast.fecha = ? LIMIT 1) AS presente_primer_dia
                FROM alumno_grupos ag
                INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
                LEFT JOIN preregistros pr ON pr.id_alumno_vinculado = a.id_alumno
                LEFT JOIN usuarios ua ON ua.id_usuario = pr.id_usuario_registro
                LEFT JOIN asesor_entrevistas ent ON ent.id_entrevista = pr.id_entrevista_origen
                LEFT JOIN usuarios uc ON uc.id_usuario = pr.id_usuario_asesor
                LEFT JOIN usuarios ue ON ue.id_usuario = ent.id_usuario_asesor
                WHERE ag.id_grupo = ? AND ag.activo = 1
                  AND {$fechaInscripcionExpr} <= ?";
        $params = [$primerDia, $idGrupo, $primerDia];
        if ($idAsesor > 0) {
            $sql .= " AND {$asesorExpr} = ?";
            $params[] = $idAsesor;
        }
        $sql .= " ORDER BY {$fechaInscripcionExpr} ASC";

        $stAl = $pdo->prepare($sql);
        $stAl->execute($params);
        foreach ($stAl->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $presentado = (int) ($row['presente_primer_dia'] ?? 0) === 1;
            $totalInscritos++;
            if ($presentado) {
                $totalPresentados++;
            }
            $filas[] = [
                'id_grupo' => $idGrupo,
                'grupo_clave' => $grupo['clave'],
                'especialidad' => $grupo['especialidad'],
                'fecha_inicio_grupo' => $grupo['fecha_inicio'],
                'primer_dia_clase' => $primerDia,
                'id_alumno' => (int) $row['id_alumno'],
                'numero_control' => $row['numero_control'],
                'nombre' => $row['nombre'],
                'fecha_inscripcion' => $row['fecha_inscripcion'],
                'asesor_nombre' => $row['asesor_nombre'],
                'presentado' => $presentado ? 1 : 0,
            ];
        }
    }

    $pct = $totalInscritos > 0 ? round(100 * $totalPresentados / $totalInscritos, 1) : 0.0;

    return [
        'filas' => $filas,
        'resumen' => [
            'total_inscritos' => $totalInscritos,
            'total_presentados' => $totalPresentados,
            'pct_presentados' => $pct,
            'grupos' => $gruposConClase,
            'grupos_sin_horario' => $gruposSinHorario,
        ],
    ];
}
