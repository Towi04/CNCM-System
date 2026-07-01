<?php

/**
 * Resumen académico por grupo (piloto reportes Fase 4).
 */

function reporte_academico_puede_ver(): bool
{
    return rbac_cap('reporte_academico_ver');
}

/** @return list<array<string, mixed>> */
function reporte_academico_resumen_grupos(PDO $pdo, int $idPlantel): array
{
    academico_ensure_schema($pdo);
    asistencia_ensure_schema($pdo);

    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.id_fase_actual,
                e.nombre AS esp_nombre,
                CONCAT(u.nombre, \' \', u.apellido) AS profesor,
                (SELECT COUNT(*) FROM alumno_grupos ag WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1) AS num_alumnos
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
         WHERE g.id_plantel = ?
         ORDER BY g.clave ASC
         LIMIT 80'
    );
    $st->execute([$idPlantel]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$g) {
        $idGrupo = (int) $g['id_grupo'];
        $idFase = (int) ($g['id_fase_actual'] ?? 0);

        $stA = $pdo->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(presente), 0) AS presentes
             FROM asistencias
             WHERE id_grupo = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)'
        );
        $stA->execute([$idGrupo]);
        $as = $stA->fetch(PDO::FETCH_ASSOC) ?: [];
        $totalAs = (int) ($as['total'] ?? 0);
        $g['asistencia_pct'] = $totalAs > 0
            ? round(100 * (int) ($as['presentes'] ?? 0) / $totalAs, 1)
            : null;

        $g['promedio_parcial'] = null;
        $g['calif_capturadas'] = 0;
        if ($idFase > 0) {
            $stC = $pdo->prepare(
                'SELECT AVG(c.promedio) AS prom, COUNT(*) AS n
                 FROM alumno_calificacion_parcial c
                 INNER JOIN alumno_grupos ag ON ag.id_alumno = c.id_alumno AND ag.id_grupo = ?
                 WHERE c.id_fase = ? AND c.id_grupo = ? AND c.promedio IS NOT NULL'
            );
            $stC->execute([$idGrupo, $idFase, $idGrupo]);
            $cal = $stC->fetch(PDO::FETCH_ASSOC) ?: [];
            $g['promedio_parcial'] = $cal['prom'] !== null ? round((float) $cal['prom'], 2) : null;
            $g['calif_capturadas'] = (int) ($cal['n'] ?? 0);
        }

        $stR = $pdo->prepare(
            'SELECT COUNT(*) FROM alumno_grupos WHERE id_grupo = ? AND en_riesgo_academico = 1'
        );
        $stR->execute([$idGrupo]);
        $g['en_riesgo'] = (int) $stR->fetchColumn();
    }
    unset($g);

    return $rows;
}

/** @return array<string, mixed> */
function reporte_academico_estadisticas_plantel(array $rows): array
{
    $totalGrupos = count($rows);
    $totalAlumnos = 0;
    $totalRiesgo = 0;
    $asistSum = 0;
    $asistN = 0;
    $promSum = 0;
    $promN = 0;
    $porEsp = [];

    foreach ($rows as $r) {
        $totalAlumnos += (int) ($r['num_alumnos'] ?? 0);
        $totalRiesgo += (int) ($r['en_riesgo'] ?? 0);
        if ($r['asistencia_pct'] !== null) {
            $asistSum += (float) $r['asistencia_pct'];
            $asistN++;
        }
        if ($r['promedio_parcial'] !== null) {
            $promSum += (float) $r['promedio_parcial'];
            $promN++;
        }
        $esp = (string) ($r['esp_nombre'] ?? 'Sin especialidad');
        if (!isset($porEsp[$esp])) {
            $porEsp[$esp] = ['grupos' => 0, 'alumnos' => 0, 'riesgo' => 0];
        }
        $porEsp[$esp]['grupos']++;
        $porEsp[$esp]['alumnos'] += (int) ($r['num_alumnos'] ?? 0);
        $porEsp[$esp]['riesgo'] += (int) ($r['en_riesgo'] ?? 0);
    }

    uasort($porEsp, static fn ($a, $b) => ($b['grupos'] <=> $a['grupos']));

    return [
        'total_grupos' => $totalGrupos,
        'total_alumnos' => $totalAlumnos,
        'total_riesgo' => $totalRiesgo,
        'asistencia_promedio' => $asistN > 0 ? round($asistSum / $asistN, 1) : null,
        'promedio_parcial_plantel' => $promN > 0 ? round($promSum / $promN, 2) : null,
        'por_especialidad' => $porEsp,
    ];
}

/** @return list<array<string, mixed>> */
function reporte_academico_top_grupos(array $rows, string $metric = 'asistencia', int $limite = 12): array
{
    $copy = $rows;
    usort($copy, static function ($a, $b) use ($metric) {
        if ($metric === 'riesgo') {
            return ((int) ($b['en_riesgo'] ?? 0)) <=> ((int) ($a['en_riesgo'] ?? 0));
        }
        if ($metric === 'promedio') {
            return ((float) ($b['promedio_parcial'] ?? 0)) <=> ((float) ($a['promedio_parcial'] ?? 0));
        }

        return ((float) ($b['asistencia_pct'] ?? 0)) <=> ((float) ($a['asistencia_pct'] ?? 0));
    });

    return array_slice($copy, 0, $limite);
}

/** @return array{ok:bool, es_pdf:bool, contenido:string, mime:string, filename:string} */
function reporte_academico_generar_pdf(PDO $pdo, int $idPlantel, array $rows, string $tituloPlantel = ''): array
{
    $stats = reporte_academico_estadisticas_plantel($rows);
    $h = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $css = '@page{margin:0.4in;} body{font-family:DejaVu Sans,Arial,sans-serif;font-size:9pt;color:#111;}
    h1{font-size:16pt;color:#11458B;text-align:center;margin:0 0 4px;}
    .sub{text-align:center;color:#666;margin-bottom:12px;font-size:9pt;}
    table{border-collapse:collapse;width:100%;} th,td{border:0.5pt solid #888;padding:4px 6px;}
    th{background:#11458B;color:#fff;font-size:8pt;} .meta{margin-top:10px;font-size:8pt;color:#444;}';
    $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>';
    $html .= '<h1>Resumen académico por grupo</h1>';
    $html .= '<div class="sub">' . $h($tituloPlantel !== '' ? $tituloPlantel : 'Plantel') . ' · ' . date('d/m/Y H:i') . '</div>';
    $html .= '<p class="meta">' . (int) $stats['total_grupos'] . ' grupos · '
        . (int) $stats['total_alumnos'] . ' alumnos · '
        . (int) $stats['total_riesgo'] . ' en riesgo · Asist. prom. '
        . ($stats['asistencia_promedio'] !== null ? $stats['asistencia_promedio'] . '%' : '—')
        . '</p><table><thead><tr><th>Grupo</th><th>Esp.</th><th>Prof.</th><th>Alum.</th><th>Asist.</th><th>Prom.</th><th>Riesgo</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $html .= '<tr><td><strong>' . $h($r['clave'] ?? '') . '</strong></td>';
        $html .= '<td>' . $h($r['esp_nombre'] ?? '') . '</td>';
        $html .= '<td>' . $h($r['profesor'] ?? '') . '</td>';
        $html .= '<td>' . (int) ($r['num_alumnos'] ?? 0) . '</td>';
        $html .= '<td>' . ($r['asistencia_pct'] !== null ? $r['asistencia_pct'] . '%' : '—') . '</td>';
        $html .= '<td>' . ($r['promedio_parcial'] !== null ? $h((string) $r['promedio_parcial']) : '—') . '</td>';
        $html .= '<td>' . ((int) ($r['en_riesgo'] ?? 0) > 0 ? (int) $r['en_riesgo'] : '—') . '</td></tr>';
    }
    $html .= '</tbody></table></body></html>';
    $filename = 'reporte_academico_' . date('Y-m-d_His') . '.pdf';
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('Dompdf\\Dompdf') && class_exists('Dompdf\\Options')) {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('letter', 'landscape');
            $dompdf->render();

            return [
                'ok' => true,
                'es_pdf' => true,
                'contenido' => $dompdf->output(),
                'mime' => 'application/pdf',
                'filename' => $filename,
            ];
        }
    }
    $bar = '<div style="text-align:center;padding:8px;background:#e8f0fa;margin-bottom:10px;">'
        . '<button onclick="window.print()">Imprimir / Guardar PDF</button></div>';
    $html = preg_replace('/<body>/i', '<body>' . $bar, $html, 1);

    return [
        'ok' => true,
        'es_pdf' => false,
        'contenido' => $html,
        'mime' => 'text/html; charset=UTF-8',
        'filename' => preg_replace('/\\.pdf$/', '.html', $filename),
    ];
}

function reporte_academico_enviar_csv(array $rows, string $filename = 'reporte_academico.csv'): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['Grupo', 'Especialidad', 'Profesor', 'Alumnos', 'Asistencia_90d_pct', 'Promedio_parcial', 'Calif_capturadas', 'En_riesgo']);
    foreach ($rows as $r) {
        fputcsv($fh, [
            $r['clave'] ?? '',
            $r['esp_nombre'] ?? '',
            $r['profesor'] ?? '',
            (int) ($r['num_alumnos'] ?? 0),
            $r['asistencia_pct'] !== null ? $r['asistencia_pct'] : '',
            $r['promedio_parcial'] !== null ? $r['promedio_parcial'] : '',
            (int) ($r['calif_capturadas'] ?? 0),
            (int) ($r['en_riesgo'] ?? 0),
        ]);
    }
    fclose($fh);
}
