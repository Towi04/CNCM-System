<?php

/**
 * Reporte operativo de alumnos dados de baja.
 */

function reporte_bajas_puede_ver(): bool
{
    return function_exists('rbac_cap') && rbac_cap('menu_reporte_bajas');
}

/** @return array{desde:string,hasta:string,label:string} */
function reporte_bajas_rango(array $filtros): array
{
    $periodo = (string) ($filtros['periodo'] ?? 'mes');
    $hoy = new DateTimeImmutable('today');

    $desde = trim((string) ($filtros['desde'] ?? ''));
    $hasta = trim((string) ($filtros['hasta'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return ['desde' => $desde, 'hasta' => $hasta, 'label' => $desde . ' a ' . $hasta];
    }

    if ($periodo === 'semana') {
        return [
            'desde' => $hoy->modify('monday this week')->format('Y-m-d'),
            'hasta' => $hoy->modify('sunday this week')->format('Y-m-d'),
            'label' => 'Semana actual',
        ];
    }
    if ($periodo === 'anio') {
        return [
            'desde' => $hoy->format('Y-01-01'),
            'hasta' => $hoy->format('Y-12-31'),
            'label' => 'Año ' . $hoy->format('Y'),
        ];
    }

    return [
        'desde' => $hoy->modify('first day of this month')->format('Y-m-d'),
        'hasta' => $hoy->modify('last day of this month')->format('Y-m-d'),
        'label' => 'Mes actual',
    ];
}

/** @return array{items:list<array<string,mixed>>,resumen:array<string,int>,rango:array<string,string>} */
function reporte_bajas_listar(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    if (function_exists('alumno_ensure_schema')) {
        alumno_ensure_schema($pdo);
    }
    if (function_exists('pago_ensure_schema')) {
        pago_ensure_schema($pdo);
    }
    if (function_exists('combo_ensure_schema')) {
        combo_ensure_schema($pdo);
    }

    $rango = reporte_bajas_rango($filtros);
    $st = $pdo->prepare(
        "SELECT a.id_alumno, a.numero_control,
                TRIM(CONCAT(COALESCE(a.nombres, a.nombre, ''), ' ', COALESCE(a.apellido_paterno, a.apellido, ''), ' ', COALESCE(a.apellido_materno, ''))) AS nombre_completo,
                a.estado, a.fecha_baja_temporal, a.motivo_baja_temporal, a.inscripcion_vigente_hasta,
                e.nombre AS especialidad, g.clave AS grupo_clave
         FROM alumnos a
         LEFT JOIN especialidades e ON e.id_especialidad = a.id_especialidad
         LEFT JOIN grupos g ON g.id_grupo = a.id_grupo
         WHERE a.id_plantel = ? AND a.estado = 'baja'
           AND a.fecha_baja_temporal IS NOT NULL
           AND a.fecha_baja_temporal BETWEEN ? AND ?
         ORDER BY a.fecha_baja_temporal DESC, nombre_completo ASC"
    );
    $st->execute([$idPlantel, $rango['desde'], $rango['hasta']]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $resumen = ['total' => 0, 'temporales' => 0, 'definitivas' => 0];
    foreach ($items as &$row) {
        $vigente = trim((string) ($row['inscripcion_vigente_hasta'] ?? ''));
        $temporal = $vigente !== '' && $vigente !== '0000-00-00';
        $row['tipo_baja'] = $temporal ? 'temporal' : 'definitiva';
        $row['tipo_baja_label'] = $temporal ? 'Temporal' : 'Definitiva';
        $resumen['total']++;
        $resumen[$temporal ? 'temporales' : 'definitivas']++;
    }
    unset($row);

    return ['items' => $items, 'resumen' => $resumen, 'rango' => $rango];
}
