<?php

/**
 * Exportación / importación masiva de fases por especialidad (CSV).
 */

/** @return list<string> */
function fase_csv_columnas(): array
{
    return [
        'id_fase',
        'clave_especialidad',
        'clave_fase',
        'nombre_fase',
        'orden',
        'duracion_semanas',
        'objetivo_parcial',
        'descripcion',
        'temas',
        'practicas_sugeridas',
        'asesoria',
        'nivel_cefr',
        'num_parcial',
        'tipo_contenido',
        'eval_listening',
        'eval_reading',
        'eval_writing',
        'eval_speaking',
        'eval_grammar',
        'eval_vocabulary',
        'vocabulario_resumen',
        'gramatica_resumen',
        'moodle_course_id',
        'moodle_shortname',
        'activo',
    ];
}

/** @return list<array<string, mixed>> */
function fase_csv_filas(PDO $pdo, ?int $idEspecialidad = null): array
{
    fase_ensure_schema($pdo);
    if (function_exists('fase_ensure_moodle_columns')) {
        fase_ensure_moodle_columns($pdo);
    }
    $sql = 'SELECT f.*, e.clave AS clave_especialidad
            FROM especialidad_fases f
            INNER JOIN especialidades e ON e.id_especialidad = f.id_especialidad
            WHERE 1=1';
    $params = [];
    if ($idEspecialidad !== null && $idEspecialidad > 0) {
        $sql .= ' AND f.id_especialidad = ?';
        $params[] = $idEspecialidad;
    }
    $sql .= ' ORDER BY e.orden ASC, e.clave ASC, f.orden ASC, f.id_fase ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $cols = fase_csv_columnas();
    $out = [];
    foreach ($rows as $r) {
        $line = [];
        foreach ($cols as $col) {
            if ($col === 'objetivo_parcial') {
                $line[$col] = $r['objetivo_parcial'] ?? $r['descripcion'] ?? '';
            } else {
                $line[$col] = $r[$col] ?? '';
            }
        }
        $out[] = $line;
    }

    return $out;
}

function fase_csv_enviar_descarga(PDO $pdo, ?int $idEspecialidad = null, bool $soloPlantilla = false): void
{
    $cols = fase_csv_columnas();
    $tag = $idEspecialidad ? ('esp' . $idEspecialidad . '_') : '';
    $filename = $soloPlantilla
        ? 'plantilla_fases_especialidad.csv'
        : 'fases_' . $tag . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        return;
    }
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, $cols);
    if (!$soloPlantilla) {
        foreach (fase_csv_filas($pdo, $idEspecialidad) as $row) {
            $line = [];
            foreach ($cols as $c) {
                $line[] = $row[$c] ?? '';
            }
            fputcsv($out, $line);
        }
    } else {
        fputcsv($out, [
            '', 'ING', 'A1-1', 'A1 - Parcial 1', '1', '4',
            'Objetivos del parcial…', '', '', '', '',
            'A1', '1', 'regular',
            '', '', '', '', '', '',
            '', '',
            '', '', '1',
        ]);
    }
    fclose($out);
    exit;
}

/**
 * @return array{ok:bool, message:string, actualizadas?:int, creadas?:int, errores?:list<string>}
 */
function fase_csv_importar(PDO $pdo, string $tmpPath, ?int $forzarIdEsp = null): array
{
    if (!fase_puede_editar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    if (!is_readable($tmpPath)) {
        return ['ok' => false, 'message' => 'No se pudo leer el CSV'];
    }
    fase_ensure_schema($pdo);
    if (function_exists('fase_ensure_moodle_columns')) {
        fase_ensure_moodle_columns($pdo);
    }

    $fh = fopen($tmpPath, 'r');
    if ($fh === false) {
        return ['ok' => false, 'message' => 'No se pudo abrir el CSV'];
    }
    $header = fgetcsv($fh);
    if ($header === false || $header === []) {
        fclose($fh);
        return ['ok' => false, 'message' => 'CSV vacío'];
    }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
    $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
    $map = array_flip($header);
    if (!isset($map['nombre_fase']) && !isset($map['clave_fase'])) {
        fclose($fh);
        return ['ok' => false, 'message' => 'El CSV debe incluir nombre_fase o clave_fase'];
    }

    $get = null;
    $actualizadas = 0;
    $creadas = 0;
    $errores = [];
    $lineNum = 1;

    while (($data = fgetcsv($fh)) !== false) {
        $lineNum++;
        if ($data === [null] || $data === false) {
            continue;
        }
        $get = static function (string $col) use ($map, $data): string {
            if (!isset($map[$col])) {
                return '';
            }
            $i = $map[$col];

            return isset($data[$i]) ? trim((string) $data[$i]) : '';
        };

        $idFase = (int) $get('id_fase');
        $claveEsp = strtoupper(trim($get('clave_especialidad')));
        $idEsp = $forzarIdEsp;
        if (($idEsp === null || $idEsp <= 0) && $claveEsp !== '') {
            $st = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE UPPER(clave) = ? LIMIT 1');
            $st->execute([$claveEsp]);
            $idEsp = (int) $st->fetchColumn();
        }
        if ($idFase > 0) {
            $exist = fase_obtener($pdo, $idFase);
            if ($exist) {
                $idEsp = (int) $exist['id_especialidad'];
            }
        }
        if ($idEsp === null || $idEsp <= 0) {
            $errores[] = "Línea {$lineNum}: no se resolvió especialidad (clave_especialidad / id_fase)";
            continue;
        }

        $nombre = $get('nombre_fase');
        $claveFase = $get('clave_fase');
        if ($nombre === '' && $claveFase === '' && $idFase <= 0) {
            continue;
        }

        if ($idFase <= 0 && $claveFase !== '') {
            $stF = $pdo->prepare(
                'SELECT id_fase FROM especialidad_fases
                 WHERE id_especialidad = ? AND UPPER(clave_fase) = ? AND activo = 1
                 ORDER BY id_fase ASC LIMIT 1'
            );
            $stF->execute([$idEsp, strtoupper($claveFase)]);
            $idFase = (int) $stF->fetchColumn();
        }

        $payload = [
            'id_fase' => $idFase,
            'id_especialidad' => $idEsp,
            'nombre_fase' => $nombre !== '' ? $nombre : ($claveFase !== '' ? $claveFase : 'Fase'),
            'clave_fase' => $claveFase,
            'orden' => $get('orden') !== '' ? (int) $get('orden') : 0,
            'duracion_semanas' => $get('duracion_semanas') !== '' ? (int) $get('duracion_semanas') : null,
            'descripcion' => $get('descripcion') !== '' ? $get('descripcion') : $get('objetivo_parcial'),
            'temas' => $get('temas'),
            'practicas_sugeridas' => $get('practicas_sugeridas'),
            'asesoria' => $get('asesoria'),
            'activo' => $get('activo') === '' ? 1 : ((int) $get('activo') ? 1 : 0),
            'moodle_course_id' => $get('moodle_course_id'),
            'moodle_shortname' => $get('moodle_shortname'),
        ];

        // Campos extendidos (inglés / evaluación) vía UPDATE directo tras fase_guardar
        $res = fase_guardar($pdo, $payload);
        if (!$res['ok']) {
            $errores[] = "Línea {$lineNum}: " . ($res['message'] ?? 'error');
            continue;
        }
        $idSaved = (int) ($res['id_fase'] ?? $idFase);
        if ($idFase <= 0) {
            $creadas++;
            $idSaved = (int) ($res['id_fase'] ?? 0);
            if ($idSaved <= 0) {
                $stLast = $pdo->prepare(
                    'SELECT id_fase FROM especialidad_fases WHERE id_especialidad = ? ORDER BY id_fase DESC LIMIT 1'
                );
                $stLast->execute([$idEsp]);
                $idSaved = (int) $stLast->fetchColumn();
            }
        } else {
            $actualizadas++;
        }

        if ($idSaved > 0) {
            $sets = [];
            $params = [];
            $extraMap = [
                'objetivo_parcial' => 'objetivo_parcial',
                'nivel_cefr' => 'nivel_cefr',
                'num_parcial' => 'num_parcial',
                'tipo_contenido' => 'tipo_contenido',
                'eval_listening' => 'eval_listening',
                'eval_reading' => 'eval_reading',
                'eval_writing' => 'eval_writing',
                'eval_speaking' => 'eval_speaking',
                'eval_grammar' => 'eval_grammar',
                'eval_vocabulary' => 'eval_vocabulary',
                'vocabulario_resumen' => 'vocabulario_resumen',
                'gramatica_resumen' => 'gramatica_resumen',
            ];
            foreach ($extraMap as $csvCol => $dbCol) {
                if (!isset($map[$csvCol])) {
                    continue;
                }
                $val = $get($csvCol);
                // Permitir limpiar dejando vacío solo si la columna viene en el CSV
                $sets[] = "{$dbCol} = ?";
                if ($csvCol === 'num_parcial') {
                    $params[] = $val === '' ? null : (int) $val;
                } elseif ($csvCol === 'tipo_contenido') {
                    $params[] = in_array($val, ['regular', 'proyecto_nivel', 'proyecto_final'], true)
                        ? $val
                        : 'regular';
                } else {
                    $params[] = $val === '' ? null : $val;
                }
            }
            if ($sets !== []) {
                try {
                    $params[] = $idSaved;
                    $pdo->prepare(
                        'UPDATE especialidad_fases SET ' . implode(', ', $sets) . ' WHERE id_fase = ?'
                    )->execute($params);
                } catch (Throwable $e) {
                    $errores[] = "Línea {$lineNum}: extras — " . $e->getMessage();
                }
            }
        }
    }
    fclose($fh);

    $msg = "{$actualizadas} fase(s) actualizada(s), {$creadas} creada(s)";
    if ($errores !== []) {
        $msg .= '. Avisos: ' . count($errores);
    }

    return [
        'ok' => ($actualizadas + $creadas) > 0 || $errores === [],
        'message' => $msg,
        'actualizadas' => $actualizadas,
        'creadas' => $creadas,
        'errores' => array_slice($errores, 0, 20),
    ];
}
