<?php

/**
 * Parser de scripts/hay_xlsm_dump.txt — catálogo "Opciones a evaluar" (solo Profesor Inglés).
 */

function hay_xlsm_dump_path(): string
{
    return dirname(__DIR__) . '/scripts/hay_xlsm_dump.txt';
}

function hay_xlsm_norm_label(string $s): string
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    $map = [
        'Ú' => 'U', 'ú' => 'u', 'É' => 'E', 'é' => 'e', 'Í' => 'I', 'í' => 'i',
        'Ó' => 'O', 'ó' => 'o', 'Á' => 'A', 'á' => 'a', 'Ñ' => 'N', 'ñ' => 'n',
        '¾' => 'o', '±' => 'n', 'ß' => 's', '╔' => 'E', 'ý' => 'i',
    ];
    $s = strtr($s, $map);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

    return $s;
}

/** @return array<string, array{codigo: string, rubro: string}> */
function hay_xlsm_aspect_meta_map(): array
{
    $rows = [
        ['Nivel en el MCERL', 'MCERL', 'know_how'],
        ['Certificacion', 'CERTIFICACION', 'know_how'],
        ['Certificación', 'CERTIFICACION', 'know_how'],
        ['Prescolar', 'EXP_PRESCOLAR', 'know_how'],
        ['Prescolar y Primaria', 'EXP_PRESCOLAR', 'know_how'],
        ['Secundaria', 'EXP_SECU', 'know_how'],
        ['Secundaria ', 'EXP_SECU', 'know_how'],
        ['Preparatoria', 'EXP_PREPA', 'know_how'],
        ['Universidad', 'EXP_UNI', 'know_how'],
        ['Licenciatura', 'LICENCIATURA', 'know_how'],
        ['Module 1', 'M1', 'know_how'],
        ['Module 2', 'M2', 'know_how'],
        ['Module 3', 'M3', 'know_how'],
        ['Windows', 'WINDOWS', 'know_how'],
        ['Word', 'WORD', 'know_how'],
        ['Power Point', 'POWERPOINT', 'know_how'],
        ['Excel', 'EXCEL', 'know_how'],
        ['Plataformas para videollamadas', 'VIDEOLLAM', 'know_how'],
        ['Conocimiento de apps y Paginas web', 'APPS', 'know_how'],
        ['Conocimiento de apps y Paginas web ', 'APPS', 'know_how'],
        ['Manejo de plataforma Moodle', 'MOODLE', 'know_how'],
        ['Disponibilidad para asesorias', 'ASESORIAS', 'problem_solving'],
        ['Disponibilidad para Personalizados', 'PERSONALIZADOS', 'problem_solving'],
        ['Desarrollo de Planes de Estudios', 'PLANES', 'problem_solving'],
        ['Manejo de Fusiones', 'FUSIONES', 'problem_solving'],
        ['Manejo de Fusiones ', 'FUSIONES', 'problem_solving'],
        ['Disponibilidad de Viaje', 'VIAJE', 'problem_solving'],
        ['Apoyo (cubrir faltas)', 'APOYO', 'problem_solving'],
        ['Proyecto final', 'PROYECTO', 'problem_solving'],
        ['Proyecto final ', 'PROYECTO', 'problem_solving'],
        ['Entrega de planeaciones', 'PLANEACIONES', 'accountability'],
        ['Entrega de calificaciones', 'CALIF', 'accountability'],
        ['Asistencia', 'ASISTENCIA', 'accountability'],
        ['Puntualidad', 'PUNTUALIDAD', 'accountability'],
        ['Juntas', 'JUNTAS', 'accountability'],
        ['Inteligencia Emocional', 'PSICOMETRICO', 'accountability'],
        ['Supervisión', 'SUPERVISION', 'accountability'],
        ['Supervision', 'SUPERVISION', 'accountability'],
        ['4TO MES', 'EVAL_4TO_MES', 'accountability'],
        ['Evaluacion de clase', 'EVAL_CLASE', 'accountability'],
        ['Evaluación de clase', 'EVAL_CLASE', 'accountability'],
        ['RETENCION', 'RETENCION', 'accountability'],
        ['Retención', 'RETENCION', 'accountability'],
        ['Carga horaria', 'HORARIO', 'environment'],
    ];
    $out = [];
    foreach ($rows as [$nombre, $codigo, $rubro]) {
        $out[hay_xlsm_norm_label($nombre)] = ['codigo' => $codigo, 'rubro' => $rubro];
    }

    return $out;
}

function hay_xlsm_aspect_meta(string $nombreAspecto): array
{
    $map = hay_xlsm_aspect_meta_map();
    $key = hay_xlsm_norm_label($nombreAspecto);
    if (isset($map[$key])) {
        return $map[$key];
    }
    $codigo = strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', hay_xlsm_norm_label($nombreAspecto)) ?? 'ASPECTO');
    $codigo = trim($codigo, '_');
    if ($codigo === '') {
        $codigo = 'ASPECTO_' . substr(md5($nombreAspecto), 0, 8);
    }

    return ['codigo' => substr($codigo, 0, 60), 'rubro' => 'know_how'];
}

function hay_xlsm_col_inc(string $col): string
{
    $n = 0;
    $len = strlen($col);
    for ($i = 0; $i < $len; $i++) {
        $n = $n * 26 + (ord($col[$i]) - 64);
    }
    $n++;
    $out = '';
    while ($n > 0) {
        $n--;
        $out = chr(65 + ($n % 26)) . $out;
        $n = intdiv($n, 26);
    }

    return $out;
}

/**
 * @return array{ok: bool, message?: string, aspects?: list<array{nombre: string, codigo: string, rubro: string, opciones: list<array{etiqueta: string, puntos: int}>}>}
 */
function hay_xlsm_parse_opciones_ingles(?string $path = null): array
{
    $path = $path ?? hay_xlsm_dump_path();
    if (!is_readable($path)) {
        return ['ok' => false, 'message' => 'No se encuentra hay_xlsm_dump.txt'];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return ['ok' => false, 'message' => 'No se pudo leer hay_xlsm_dump.txt'];
    }

    $pos = strpos($raw, '=== sheet 3 ===');
    if ($pos === false) {
        return ['ok' => false, 'message' => 'Hoja "Opciones a evaluar" (sheet 3) no encontrada en el dump'];
    }
    $section = substr($raw, $pos);
    $lines = preg_split('/\r\n|\n|\r/', $section) ?: [];

    $headerCells = null;
    $aspectCols = [];
    $aspects = [];

    foreach ($lines as $line) {
        if (!preg_match('/^R(\d+):\s*(.*)$/u', trim($line), $m)) {
            continue;
        }
        $rowNum = (int) $m[1];
        $body = $m[2];
        if ($rowNum < 2) {
            continue;
        }
        $cells = hay_xlsm_parse_row_cells($body);
        if ($rowNum === 2) {
            foreach ($cells as $col => $nombre) {
                if ($nombre === '') {
                    continue;
                }
                $ptsCol = hay_xlsm_col_inc($col);
                $aspectCols[] = ['col' => $col, 'pts_col' => $ptsCol, 'nombre' => $nombre];
                $meta = hay_xlsm_aspect_meta($nombre);
                $aspects[$col] = [
                    'nombre' => $nombre,
                    'codigo' => $meta['codigo'],
                    'rubro' => $meta['rubro'],
                    'opciones' => [],
                ];
            }
            continue;
        }
        if ($rowNum < 3 || !$aspectCols) {
            continue;
        }
        foreach ($aspectCols as $ac) {
            $label = trim((string) ($cells[$ac['col']] ?? ''));
            if ($label === '') {
                continue;
            }
            $ptsRaw = $cells[$ac['pts_col']] ?? '0';
            $pts = (int) preg_replace('/[^\d-]/', '', (string) $ptsRaw);
            if ($pts < 0) {
                $pts = 0;
            }
            $aspects[$ac['col']]['opciones'][] = [
                'etiqueta' => $label,
                'puntos' => $pts,
            ];
        }
    }

    if (!$aspectCols) {
        return ['ok' => false, 'message' => 'No se encontraron aspectos en la fila de encabezados (R2)'];
    }

    $lista = [];
    foreach ($aspectCols as $ac) {
        $a = $aspects[$ac['col']] ?? null;
        if (!$a || empty($a['opciones'])) {
            continue;
        }
        usort($a['opciones'], static function ($x, $y) {
            return $y['puntos'] <=> $x['puntos'];
        });
        $orden = 100;
        foreach ($a['opciones'] as &$op) {
            $op['orden'] = $orden;
            $orden -= 5;
        }
        unset($op);
        $lista[] = $a;
    }

    if ($lista === []) {
        return ['ok' => false, 'message' => 'No hay opciones con puntaje en el dump'];
    }

    return ['ok' => true, 'aspects' => $lista, 'total_aspectos' => count($lista)];
}

/**
 * @return array<string, string>
 */
function hay_xlsm_parse_row_cells(string $body): array
{
    $cells = [];
    foreach (explode('|', $body) as $part) {
        $part = trim($part);
        if (preg_match('/^([A-Z]{1,3})=(.*)$/u', $part, $pm)) {
            $cells[$pm[1]] = trim($pm[2]);
        }
    }

    return $cells;
}
