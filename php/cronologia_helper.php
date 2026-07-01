<?php

function cronologia_puede_ver(): bool
{
    return function_exists('rbac_cap') && (rbac_cap('menu_grupos') || rbac_cap('menu_especialidades'));
}

/**
 * @param array{id_especialidad?: int, id_profesor?: int, id_grupo?: int, q?: string, semanas_proyeccion?: int} $filtros
 * @return list<array<string, mixed>>
 */
function cronologia_listar_grupos(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    $params = [$idPlantel];
    $sql = 'SELECT g.*, e.nombre AS esp_nombre, e.clave AS esp_clave,
                   f.clave_fase AS fase_actual_clave, f.nombre_fase AS fase_actual_nombre,
                   CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre,
                   (SELECT COUNT(*) FROM alumno_grupos ag
                    INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.id_plantel = g.id_plantel
                    WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1) AS total_alumnos
            FROM grupos g
            LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
            LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual
            LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
            WHERE g.id_plantel = ?';

    if (!empty($filtros['id_especialidad'])) {
        $sql .= ' AND g.id_especialidad = ?';
        $params[] = (int) $filtros['id_especialidad'];
    }
    if (!empty($filtros['id_profesor'])) {
        $sql .= ' AND g.id_profesor = ?';
        $params[] = (int) $filtros['id_profesor'];
    }
    if (!empty($filtros['id_grupo'])) {
        $sql .= ' AND g.id_grupo = ?';
        $params[] = (int) $filtros['id_grupo'];
    }
    if (!empty($filtros['q'])) {
        $sql .= ' AND g.clave LIKE ?';
        $params[] = '%' . trim((string) $filtros['q']) . '%';
    }

    $sql .= ' ORDER BY g.fecha_inicio DESC, g.clave';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $proySem = max(0, min(52, (int) ($filtros['semanas_proyeccion'] ?? 8)));
    $hoy = new DateTimeImmutable('today');
    $out = [];

    foreach ($rows as $g) {
        $idEsp = (int) ($g['id_especialidad'] ?? 0);
        $fases = $idEsp > 0 ? fase_listar($pdo, $idEsp) : [];
        $pos = academico_posicion_grupo($pdo, $g, $hoy);
        $idxEsperado = min(count($fases) - 1, max(0, (int) $pos['indice_parcial']));
        $faseEsperada = $fases[$idxEsperado] ?? null;
        $idFaseActual = (int) ($g['id_fase_actual'] ?? 0);
        $idxActual = -1;
        foreach ($fases as $i => $f) {
            if ((int) $f['id_fase'] === $idFaseActual) {
                $idxActual = $i;
                break;
            }
        }
        $enSync = $idxActual === $idxEsperado;

        $proyeccion = [];
        if ($proySem > 0 && $fases !== []) {
            $inicio = new DateTimeImmutable($g['fecha_inicio'] ?? 'today');
            for ($s = 1; $s <= $proySem; $s++) {
                $fechaFut = $hoy->modify('+' . $s . ' weeks');
                $posF = academico_posicion_grupo($pdo, $g, $fechaFut);
                $idxF = min(count($fases) - 1, max(0, (int) $posF['indice_parcial']));
                $proyeccion[] = [
                    'semanas_adelante' => $s,
                    'fecha_ref' => $fechaFut->format('Y-m-d'),
                    'semanas_lectivas' => (int) $posF['semanas_lectivas'],
                    'fase_clave' => $fases[$idxF]['clave_fase'] ?? '',
                    'fase_nombre' => $fases[$idxF]['nombre_fase'] ?? '',
                ];
            }
        }

        $out[] = [
            'id_grupo' => (int) $g['id_grupo'],
            'clave' => $g['clave'] ?? '',
            'fecha_inicio' => $g['fecha_inicio'] ?? '',
            'aula' => $g['aula'] ?? '',
            'esp_nombre' => $g['esp_nombre'] ?? '',
            'profesor_nombre' => trim($g['profesor_nombre'] ?? '') ?: '—',
            'total_alumnos' => (int) ($g['total_alumnos'] ?? 0),
            'semanas_lectivas' => (int) $pos['semanas_lectivas'],
            'semana_parcial' => (int) $pos['semana_parcial'],
            'fase_esperada_clave' => $faseEsperada['clave_fase'] ?? '—',
            'fase_esperada_nombre' => $faseEsperada['nombre_fase'] ?? '—',
            'fase_actual_clave' => $g['fase_actual_clave'] ?? '—',
            'fase_actual_nombre' => $g['fase_actual_nombre'] ?? '—',
            'en_sync' => $enSync,
            'estado' => $enSync ? 'al_dia' : ($idxActual < $idxEsperado ? 'atrasado' : 'adelantado'),
            'proyeccion' => $proyeccion,
        ];
    }

    return $out;
}

/** @return 'activo'|'fin_curso'|'programado' */
function cronologia_estado_grupo(PDO $pdo, array $grupo, int $totalAlumnos): string
{
    $hoy = new DateTimeImmutable('today');
    $inicio = new DateTimeImmutable($grupo['fecha_inicio'] ?? 'today');
    $apertura = (string) ($grupo['estado_apertura'] ?? 'programado');

    if ($inicio > $hoy && in_array($apertura, ['programado', 'pendiente_autorizacion', 'autorizado'], true)) {
        return 'programado';
    }
    if ($totalAlumnos <= 0 && $inicio <= $hoy) {
        return 'fin_curso';
    }

    return 'activo';
}

/**
 * @return list<array{key:string, iso_week:int, year:int, month:int, month_label:string, fecha_ref:string}>
 */
function cronologia_rango_semanas(int $semanasAtras = 6, int $semanasAdelante = 14): array
{
    $meses = [
        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
        5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
        9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
    ];
    $hoy = new DateTimeImmutable('today');
    $lunes = $hoy->modify('monday this week');
    $out = [];
    for ($i = -$semanasAtras; $i <= $semanasAdelante; $i++) {
        $inicioSem = $lunes->modify(($i * 7) . ' days');
        $finSem = $inicioSem->modify('+6 days');
        $isoWeek = (int) $inicioSem->format('W');
        $year = (int) $inicioSem->format('o');
        $month = (int) $inicioSem->format('n');
        $out[] = [
            'key' => sprintf('%d-W%02d', $year, $isoWeek),
            'iso_week' => $isoWeek,
            'year' => $year,
            'month' => $month,
            'month_label' => $meses[$month] ?? '',
            'fecha_ref' => $finSem->format('Y-m-d'),
        ];
    }

    return $out;
}

/** @param list<array<string, mixed>> $semanas */
function cronologia_agrupar_meses(array $semanas): array
{
    $meses = [];
    foreach ($semanas as $s) {
        $mk = $s['year'] . '-' . $s['month'];
        if (!isset($meses[$mk])) {
            $meses[$mk] = [
                'month_label' => $s['month_label'],
                'year' => $s['year'],
                'month' => $s['month'],
                'semanas' => [],
            ];
        }
        $meses[$mk]['semanas'][] = $s;
    }

    return array_values($meses);
}

/** @param array<string, mixed> $fase @param list<array<string, mixed>> $temarioSemanas */
function cronologia_texto_celda_fase(array $fase, array $temarioSemanas): string
{
    $nivel = trim((string) ($fase['nivel_cefr'] ?? ''));
    $clave = trim((string) ($fase['clave_fase'] ?? ''));
    $nombre = trim((string) ($fase['nombre_fase'] ?? ''));
    $extra = '';
    $s1 = $temarioSemanas[0] ?? null;
    if ($s1) {
        $extra = trim((string) ($s1['titulo_leccion'] ?? ''));
        if ($extra === '') {
            $extra = trim((string) ($s1['objetivo'] ?? ''));
        }
        if (mb_strlen($extra) > 24) {
            $extra = mb_substr($extra, 0, 22) . '…';
        }
    }
    if ($nivel !== '') {
        return '(' . $nivel . ')' . ($extra !== '' ? ' ' . $extra : ($nombre !== '' ? ' ' . $nombre : ''));
    }
    if ($extra !== '') {
        return $extra;
    }

    return $clave !== '' ? $clave : ($nombre !== '' ? $nombre : '—');
}

function cronologia_dia_horario_label(?string $codigoHorario): string
{
    return match (strtoupper(trim((string) $codigoHorario))) {
        'S' => 'SAB',
        'D' => 'DOM',
        'M' => 'L–V',
        'V' => 'VIE',
        default => strtoupper(trim((string) $codigoHorario)) ?: '—',
    };
}

/**
 * Matriz estilo planilla: filas = grupos, columnas = semanas ISO.
 *
 * @param array{
 *   id_especialidad?: int,
 *   id_profesor?: int,
 *   id_grupo?: int,
 *   q?: string,
 *   estado?: string,
 *   semanas_atras?: int,
 *   semanas_adelante?: int
 * } $filtros
 * @return array{semanas:list<array<string,mixed>>, meses:list<array<string,mixed>>, grupos:list<array<string,mixed>>, semana_actual:string}
 */
function cronologia_matriz(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    fase_ensure_schema($pdo);
    $semanasAtras = max(0, min(26, (int) ($filtros['semanas_atras'] ?? 6)));
    $semanasAdelante = max(4, min(52, (int) ($filtros['semanas_adelante'] ?? 14)));
    $semanas = cronologia_rango_semanas($semanasAtras, $semanasAdelante);
    $meses = cronologia_agrupar_meses($semanas);
    $hoy = new DateTimeImmutable('today');
    $semanaActual = sprintf('%d-W%02d', (int) $hoy->format('o'), (int) $hoy->format('W'));

    $params = [$idPlantel];
    $sql = 'SELECT g.*, e.nombre AS esp_nombre, e.clave AS esp_clave,
                   f.clave_fase AS fase_actual_clave, f.nombre_fase AS fase_actual_nombre,
                   CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre,
                   gh.hora_inicio, gh.hora_fin, gh.dia_semana,
                   (SELECT COUNT(*) FROM alumno_grupos ag
                    INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.id_plantel = g.id_plantel
                    WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1) AS total_alumnos
            FROM grupos g
            LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
            LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual
            LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
            LEFT JOIN (
                SELECT id_grupo, MIN(hora_inicio) AS hora_inicio, MAX(hora_fin) AS hora_fin, MIN(dia_semana) AS dia_semana
                FROM grupo_horarios WHERE activo = 1 GROUP BY id_grupo
            ) gh ON gh.id_grupo = g.id_grupo
            WHERE g.id_plantel = ?';

    if (!empty($filtros['id_especialidad'])) {
        $sql .= ' AND g.id_especialidad = ?';
        $params[] = (int) $filtros['id_especialidad'];
    }
    if (!empty($filtros['id_profesor'])) {
        $sql .= ' AND g.id_profesor = ?';
        $params[] = (int) $filtros['id_profesor'];
    }
    if (!empty($filtros['id_grupo'])) {
        $sql .= ' AND g.id_grupo = ?';
        $params[] = (int) $filtros['id_grupo'];
    }
    if (!empty($filtros['q'])) {
        $sql .= ' AND g.clave LIKE ?';
        $params[] = '%' . trim((string) $filtros['q']) . '%';
    }

    $sql .= ' ORDER BY gh.hora_inicio ASC, e.nombre ASC, g.clave ASC';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $temarioCache = [];
    $gruposOut = [];

    foreach ($rows as $g) {
        $totalAlumnos = (int) ($g['total_alumnos'] ?? 0);
        $estadoGrupo = cronologia_estado_grupo($pdo, $g, $totalAlumnos);
        $filtroEstado = trim((string) ($filtros['estado'] ?? ''));
        if ($filtroEstado === 'activo' && $estadoGrupo !== 'activo' && $estadoGrupo !== 'programado') {
            continue;
        }
        if ($filtroEstado === 'fin_curso' && $estadoGrupo !== 'fin_curso') {
            continue;
        }

        $idEsp = (int) ($g['id_especialidad'] ?? 0);
        if (!isset($temarioCache[$idEsp])) {
            $temarioCache[$idEsp] = $idEsp > 0 ? fase_temario_semanas_por_especialidad($pdo, $idEsp) : [];
        }
        $fases = $idEsp > 0 ? fase_listar($pdo, $idEsp) : [];
        $celdas = [];

        foreach ($semanas as $sem) {
            $fechaRef = new DateTimeImmutable($sem['fecha_ref']);
            $pos = academico_posicion_grupo($pdo, $g, $fechaRef);
            $idx = min(count($fases) - 1, max(0, (int) $pos['indice_parcial']));
            $fase = $fases[$idx] ?? null;
            $semanaParcial = (int) $pos['semana_parcial'];
            $texto = '';
            $mostrar = false;

            if ($fase && $semanaParcial === 1) {
                $idFase = (int) ($fase['id_fase'] ?? 0);
                $temario = $temarioCache[$idEsp][$idFase] ?? [];
                $texto = cronologia_texto_celda_fase($fase, $temario);
                $mostrar = true;
            } elseif ($fase && $sem['key'] === $semanaActual) {
                $idFase = (int) ($fase['id_fase'] ?? 0);
                $temario = $temarioCache[$idEsp][$idFase] ?? [];
                $texto = cronologia_texto_celda_fase($fase, $temario);
                $mostrar = true;
            }

            $celdas[$sem['key']] = [
                'texto' => $texto,
                'mostrar' => $mostrar,
                'es_actual' => $sem['key'] === $semanaActual,
                'fase_clave' => $fase['clave_fase'] ?? '',
                'semana_parcial' => $semanaParcial,
            ];
        }

        $hi = substr((string) ($g['hora_inicio'] ?? ''), 0, 5);
        $hf = substr((string) ($g['hora_fin'] ?? ''), 0, 5);
        $horario = ($hi !== '' && $hf !== '') ? ($hi . '–' . $hf) : trim((string) ($g['horario_texto'] ?? ''));

        $posHoy = academico_posicion_grupo($pdo, $g, $hoy);
        $idxHoy = min(count($fases) - 1, max(0, (int) $posHoy['indice_parcial']));
        $faseHoy = $fases[$idxHoy] ?? null;

        $gruposOut[] = [
            'id_grupo' => (int) $g['id_grupo'],
            'clave' => $g['clave'] ?? '',
            'esp_nombre' => $g['esp_nombre'] ?? '',
            'esp_clave' => $g['esp_clave'] ?? '',
            'total_alumnos' => $totalAlumnos,
            'aula' => trim((string) ($g['aula'] ?? '')) ?: '—',
            'horario' => $horario ?: '—',
            'dia' => cronologia_dia_horario_label($g['codigo_horario'] ?? ''),
            'profesor_nombre' => trim($g['profesor_nombre'] ?? '') ?: '—',
            'estado_grupo' => $estadoGrupo,
            'fase_actual_clave' => $faseHoy['clave_fase'] ?? ($g['fase_actual_clave'] ?? '—'),
            'fecha_inicio' => $g['fecha_inicio'] ?? '',
            'celdas' => $celdas,
        ];
    }

    return [
        'semanas' => $semanas,
        'meses' => $meses,
        'grupos' => $gruposOut,
        'semana_actual' => $semanaActual,
        'total' => count($gruposOut),
    ];
}

/** @param array<string, mixed> $matriz */
function cronologia_render_planilla_html(array $matriz, string $titulo = 'Cronología de grupos'): string
{
    $meses = $matriz['meses'] ?? [];
    $semanas = $matriz['semanas'] ?? [];
    $grupos = $matriz['grupos'] ?? [];
    $semActual = (string) ($matriz['semana_actual'] ?? '');
    $h = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $numCols = 6 + count($semanas);

    $css = '
    @page { margin: 0.35in 0.3in; size: legal landscape; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 7pt; color: #111; margin: 0; }
    h1 { font-size: 14pt; margin: 0 0 2px; color: #11458B; text-align: center; }
    .sub { text-align: center; color: #666; font-size: 8pt; margin-bottom: 8px; }
    table { border-collapse: collapse; width: 100%; table-layout: fixed; }
    th, td { border: 0.5pt solid #888; padding: 2px 3px; vertical-align: middle; word-wrap: break-word; }
    thead th { background: #c62828; color: #fff; font-weight: bold; text-align: center; font-size: 6.5pt; }
    .th-mes { background: #8b0000 !important; font-size: 7pt; letter-spacing: 0.3px; }
    .th-sem { background: #e0e0e0 !important; color: #222 !important; font-size: 6.5pt; }
    .th-sem.actual { background: #ffeb3b !important; color: #333 !important; font-weight: bold; }
    .esp-bar td { background: #11458B; color: #fff; font-weight: bold; font-size: 7.5pt; text-transform: uppercase; padding: 4px 6px; }
    .horario { background: #f0f0f0; font-weight: bold; text-align: center; font-size: 6.5pt; }
    .al-bajo { background: #ffcdd2; font-weight: bold; }
    .celda { font-size: 5.5pt; line-height: 1.15; text-align: center; min-height: 12px; }
    .celda.actual { background: #fff59d; font-weight: bold; }
    .celda.inicio { background: #e3f2fd; }
    .fin-curso { opacity: 0.55; }
    .meta { font-size: 7pt; color: #444; margin-top: 6px; }
    ';

    $html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>';
    $html .= '<h1>CRONOLOGÍA</h1><div class="sub">' . $h($titulo) . ' · Generado ' . date('d/m/Y H:i') . '</div>';
    $html .= '<table><thead><tr>';
    $html .= '<th rowspan="2" style="width:7%;">Horario</th>';
    $html .= '<th rowspan="2" style="width:6%;">Grupo</th>';
    $html .= '<th rowspan="2" style="width:4%;">Alum.</th>';
    $html .= '<th rowspan="2" style="width:7%;">Aula</th>';
    $html .= '<th rowspan="2" style="width:4%;">Día</th>';
    $html .= '<th rowspan="2" style="width:14%;">Profesor</th>';
    foreach ($meses as $m) {
        $html .= '<th class="th-mes" colspan="' . count($m['semanas']) . '">' . $h($m['month_label'] ?? '') . '</th>';
    }
    $html .= '</tr><tr>';
    foreach ($semanas as $s) {
        $cls = ($s['key'] ?? '') === $semActual ? 'th-sem actual' : 'th-sem';
        $html .= '<th class="' . $cls . '">' . (int) ($s['iso_week'] ?? 0) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    $espActual = '';
    $horarioActual = '';
    $horarioRows = [];

    $flushHorario = static function () use (&$html, &$horarioRows, &$horarioActual): void {
        $rowspan = count($horarioRows);
        foreach ($horarioRows as $i => $row) {
            if ($i === 0 && $rowspan > 1) {
                $html .= str_replace(
                    '<!--HORARIO-->',
                    '<td class="horario" rowspan="' . $rowspan . '">' . htmlspecialchars($horarioActual, ENT_QUOTES, 'UTF-8') . '</td>',
                    $row
                );
            } elseif ($rowspan <= 1) {
                $html .= str_replace(
                    '<!--HORARIO-->',
                    '<td class="horario">' . htmlspecialchars($horarioActual, ENT_QUOTES, 'UTF-8') . '</td>',
                    $row
                );
            } else {
                $html .= str_replace('<!--HORARIO-->', '', $row);
            }
        }
        $horarioRows = [];
    };

    foreach ($grupos as $g) {
        if (($g['esp_nombre'] ?? '') !== $espActual) {
            $flushHorario();
            $horarioActual = '';
            $espActual = (string) ($g['esp_nombre'] ?? '');
            $html .= '<tr class="esp-bar"><td colspan="' . $numCols . '">' . $h($g['esp_nombre'] ?: $g['esp_clave'] ?: 'Sin especialidad') . '</td></tr>';
        }
        if (($g['horario'] ?? '') !== $horarioActual) {
            $flushHorario();
            $horarioActual = (string) ($g['horario'] ?? '');
        }

        $finCls = ($g['estado_grupo'] ?? '') === 'fin_curso' ? ' class="fin-curso"' : '';
        $alCls = ((int) ($g['total_alumnos'] ?? 0)) <= 5 ? ' al-bajo' : '';
        $row = '<tr' . $finCls . '><!--HORARIO-->';
        $row .= '<td><strong>' . $h($g['clave'] ?? '') . '</strong></td>';
        $row .= '<td class="' . trim($alCls) . '">' . (int) ($g['total_alumnos'] ?? 0) . '</td>';
        $row .= '<td>' . $h($g['aula'] ?? '') . '</td>';
        $row .= '<td>' . $h($g['dia'] ?? '') . '</td>';
        $row .= '<td>' . $h($g['profesor_nombre'] ?? '') . '</td>';

        foreach ($semanas as $s) {
            $key = $s['key'] ?? '';
            $c = $g['celdas'][$key] ?? [];
            $cls = 'celda';
            if (!empty($c['es_actual'])) {
                $cls .= ' actual';
            } elseif (!empty($c['mostrar']) && (int) ($c['semana_parcial'] ?? 0) === 1) {
                $cls .= ' inicio';
            }
            $txt = !empty($c['mostrar']) ? (string) ($c['texto'] ?? '') : '';
            $row .= '<td class="' . $cls . '">' . $h($txt) . '</td>';
        }
        $row .= '</tr>';
        $horarioRows[] = $row;
    }
    $flushHorario();

    $html .= '</tbody></table>';
    $html .= '<p class="meta">' . count($grupos) . ' grupo(s) · Semana actual resaltada en amarillo · Celdas azul claro = inicio de parcial</p>';
    $html .= '</body></html>';

    return $html;
}

/**
 * @param array<string, mixed> $filtros
 * @return array{ok:bool, es_pdf:bool, contenido:string, mime:string, filename:string, message?:string}
 */
function cronologia_generar_pdf(PDO $pdo, int $idPlantel, array $filtros, string $tituloPlantel = ''): array
{
    $matriz = cronologia_matriz($pdo, $idPlantel, $filtros);
    $titulo = 'Cronología' . ($tituloPlantel !== '' ? ' — ' . $tituloPlantel : '');
    $html = cronologia_render_planilla_html($matriz, $titulo);
    $filename = 'cronologia_' . date('Y-m-d_His') . '.pdf';

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('Dompdf\Dompdf') && class_exists('Dompdf\Options')) {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('legal', 'landscape');
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

    $bar = '<div style="background:#e8f0fa;padding:8px;margin-bottom:10px;text-align:center;">'
        . '<button type="button" onclick="window.print()" style="background:#11458B;color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-weight:bold;">'
        . 'Imprimir / Guardar como PDF</button></div>';
    $htmlPrint = preg_replace('/<body>/i', '<body>' . $bar, $html, 1);

    return [
        'ok' => true,
        'es_pdf' => false,
        'contenido' => $htmlPrint,
        'mime' => 'text/html; charset=UTF-8',
        'filename' => preg_replace('/\.pdf$/', '.html', $filename),
        'message' => 'Dompdf no instalado; use Imprimir → Guardar como PDF en el navegador.',
    ];
}
