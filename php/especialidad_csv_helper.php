<?php

/**
 * Exportación / importación masiva de tarifas de especialidades (CSV).
 */

/** @return list<string> */
function especialidad_csv_columnas(): array
{
    return [
        'clave',
        'nombre',
        'descripcion',
        'modalidad',
        'edad_min',
        'edad_max',
        'duracion_fase_semanas',
        'duracion_meses',
        'duracion_semanas',
        'inscripcion_por_cuatrimestre',
        'parciales_por_cuatrimestre',
        'costo_inscripcion_referencia',
        'costo_inscripcion_apoyo',
        'costo_mensualidad_referencia',
        'costo_mensualidad_apoyo',
        'costo_pronto_pago_referencia',
        'costo_pronto_pago_apoyo',
        'costo_semanal_referencia',
        'costo_semanal_apoyo',
        'costo_cuatrimestre',
        'costo_anual',
        'cartas_inscripcion_ref',
        'cartas_inscripcion_apoyo',
        'cartas_mensualidad_ref',
        'cartas_mensualidad_apoyo',
        'cartas_pronto_pago_ref',
        'cartas_pronto_pago_apoyo',
        'cartas_semanal_ref',
        'cartas_semanal_apoyo',
        'cartas_anual_ref',
        'cartas_anual_apoyo',
        'es_fija',
        'visible',
        'activo',
        'inscripcion_abierta',
        'orden',
    ];
}

/** @return list<array<string, mixed>> */
function especialidad_csv_filas(PDO $pdo): array
{
    catalog_ensure_schema($pdo);
    operativo_cncm_ensure_schema($pdo);
    $rows = $pdo->query(
        'SELECT e.*,
                c.costo_inscripcion_ref AS cartas_inscripcion_ref,
                c.costo_inscripcion_apoyo AS cartas_inscripcion_apoyo,
                c.costo_mensualidad_ref AS cartas_mensualidad_ref,
                c.costo_mensualidad_apoyo AS cartas_mensualidad_apoyo,
                c.costo_pronto_pago_ref AS cartas_pronto_pago_ref,
                c.costo_pronto_pago_apoyo AS cartas_pronto_pago_apoyo,
                c.costo_semanal_ref AS cartas_semanal_ref,
                c.costo_semanal_apoyo AS cartas_semanal_apoyo,
                c.costo_anual_ref AS cartas_anual_ref,
                c.costo_anual_apoyo AS cartas_anual_apoyo
         FROM especialidades e
         LEFT JOIN especialidad_tarifa_cartas c ON c.id_especialidad = e.id_especialidad
         ORDER BY e.orden ASC, e.nombre ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    $cols = especialidad_csv_columnas();
    foreach ($rows as $r) {
        $line = [];
        foreach ($cols as $col) {
            $line[$col] = $r[$col] ?? '';
        }
        $out[] = $line;
    }

    return $out;
}

function especialidad_csv_enviar_descarga(PDO $pdo, bool $soloPlantilla = false): void
{
    $cols = especialidad_csv_columnas();
    $filename = $soloPlantilla
        ? 'plantilla_especialidades_costos.csv'
        : 'especialidades_costos_' . date('Ymd_His') . '.csv';

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
        foreach (especialidad_csv_filas($pdo) as $row) {
            $line = [];
            foreach ($cols as $c) {
                $line[] = $row[$c] ?? '';
            }
            fputcsv($out, $line);
        }
    } else {
        // Fila de ejemplo
        fputcsv($out, [
            'CK', 'Computación Infantil', 'Colegiatura congelada al inscribirse.', 'kids',
            '8', '12', '4', '12', '48', '0', '0',
            '1400', '700', '1080', '580', '1030', '530', '350', '160',
            '', '',
            '1400', '450', '1080', '630', '1030', '530', '350', '160', '', '',
            '1', '1', '1', '1', '10',
        ]);
    }
    fclose($out);
    exit;
}

/**
 * @return array{ok:bool, message:string, actualizadas?:int, errores?:list<string>}
 */
function especialidad_csv_importar(PDO $pdo, string $tmpPath): array
{
    if (!catalog_puede_editar_costos()) {
        return ['ok' => false, 'message' => 'Solo supervisión puede actualizar costos masivamente'];
    }
    if (!is_readable($tmpPath)) {
        return ['ok' => false, 'message' => 'No se pudo leer el CSV'];
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
    if (!isset($map['clave'])) {
        fclose($fh);
        return ['ok' => false, 'message' => 'El CSV debe incluir la columna clave'];
    }

    $actualizadas = 0;
    $errores = [];
    $lineNum = 1;
    operativo_cncm_ensure_schema($pdo);

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

        $clave = catalog_normalizar_clave($get('clave'));
        if ($clave === '') {
            continue;
        }
        $st = $pdo->prepare('SELECT * FROM especialidades WHERE UPPER(clave) = ? LIMIT 1');
        $st->execute([$clave]);
        $esp = $st->fetch(PDO::FETCH_ASSOC);
        if (!$esp) {
            $errores[] = "Línea {$lineNum}: clave «{$clave}» no existe";
            continue;
        }
        $id = (int) $esp['id_especialidad'];
        $stC = $pdo->prepare('SELECT * FROM especialidad_tarifa_cartas WHERE id_especialidad = ? LIMIT 1');
        $stC->execute([$id]);
        $cartas = $stC->fetch(PDO::FETCH_ASSOC) ?: [];
        $esp = array_merge($esp, [
            'cartas_inscripcion_ref' => $cartas['costo_inscripcion_ref'] ?? null,
            'cartas_inscripcion_apoyo' => $cartas['costo_inscripcion_apoyo'] ?? null,
            'cartas_mensualidad_ref' => $cartas['costo_mensualidad_ref'] ?? null,
            'cartas_mensualidad_apoyo' => $cartas['costo_mensualidad_apoyo'] ?? null,
            'cartas_pronto_pago_ref' => $cartas['costo_pronto_pago_ref'] ?? null,
            'cartas_pronto_pago_apoyo' => $cartas['costo_pronto_pago_apoyo'] ?? null,
            'cartas_semanal_ref' => $cartas['costo_semanal_ref'] ?? null,
            'cartas_semanal_apoyo' => $cartas['costo_semanal_apoyo'] ?? null,
            'cartas_anual_ref' => $cartas['costo_anual_ref'] ?? null,
            'cartas_anual_apoyo' => $cartas['costo_anual_apoyo'] ?? null,
        ]);

        $moneyOrKeep = static function (string $col, $actual) use ($get) {
            $v = $get($col);
            if ($v === '') {
                return catalog_money($actual ?? 0);
            }

            return catalog_money($v);
        };
        $intOrKeep = static function (string $col, $actual) use ($get) {
            $v = $get($col);
            if ($v === '') {
                return $actual;
            }

            return (int) $v;
        };
        $strOrKeep = static function (string $col, $actual) use ($get) {
            $v = $get($col);

            return $v === '' ? $actual : $v;
        };

        $nombre = (string) $strOrKeep('nombre', $esp['nombre']);
        $descripcion = (string) $strOrKeep('descripcion', $esp['descripcion'] ?? '');
        $modalidad = (string) $strOrKeep('modalidad', $esp['modalidad'] ?? 'regular');
        $mods = array_keys(catalog_modalidades_etiquetas());
        if (!in_array($modalidad, $mods, true)) {
            $modalidad = $esp['modalidad'] ?? 'regular';
        }

        $inscApoyo = $moneyOrKeep('costo_inscripcion_apoyo', $esp['costo_inscripcion_apoyo'] ?? $esp['costo_inscripcion']);
        $inscRef = $moneyOrKeep('costo_inscripcion_referencia', $esp['costo_inscripcion_referencia'] ?? $inscApoyo);
        $menApoyo = $moneyOrKeep('costo_mensualidad_apoyo', $esp['costo_mensualidad_apoyo'] ?? $esp['costo_mensualidad']);
        $menRef = $moneyOrKeep('costo_mensualidad_referencia', $esp['costo_mensualidad_referencia'] ?? $menApoyo);
        $ppApoyo = $moneyOrKeep('costo_pronto_pago_apoyo', $esp['costo_pronto_pago_apoyo'] ?? $esp['costo_pronto_pago']);
        $ppRef = $moneyOrKeep('costo_pronto_pago_referencia', $esp['costo_pronto_pago_referencia'] ?? $ppApoyo);
        $semApoyo = $moneyOrKeep('costo_semanal_apoyo', $esp['costo_semanal_apoyo'] ?? $esp['costo_semanal']);
        $semRef = $moneyOrKeep('costo_semanal_referencia', $esp['costo_semanal_referencia'] ?? $semApoyo);

        $cuat = $get('costo_cuatrimestre');
        $anual = $get('costo_anual');
        $costoCuat = $cuat === '' ? ($esp['costo_cuatrimestre'] ?? null) : catalog_money($cuat);
        $costoAnual = $anual === '' ? ($esp['costo_anual'] ?? null) : catalog_money($anual);
        if ($costoCuat !== null && (float) $costoCuat <= 0) {
            $costoCuat = null;
        }
        if ($costoAnual !== null && (float) $costoAnual <= 0) {
            $costoAnual = null;
        }

        try {
            $pdo->prepare(
                'UPDATE especialidades SET
                    nombre=?, descripcion=?, modalidad=?,
                    edad_min=?, edad_max=?, duracion_fase_semanas=?,
                    duracion_meses=?, duracion_semanas=?,
                    inscripcion_por_cuatrimestre=?, parciales_por_cuatrimestre=?,
                    costo_inscripcion=?, costo_inscripcion_referencia=?, costo_inscripcion_apoyo=?,
                    costo_mensualidad=?, costo_mensualidad_referencia=?, costo_mensualidad_apoyo=?,
                    costo_pronto_pago=?, costo_pronto_pago_referencia=?, costo_pronto_pago_apoyo=?,
                    costo_semanal=?, costo_semanal_referencia=?, costo_semanal_apoyo=?,
                    costo_cuatrimestre=?, costo_anual=?,
                    es_fija=?, visible=?, activo=?, inscripcion_abierta=?, orden=?
                 WHERE id_especialidad=?'
            )->execute([
                $nombre,
                $descripcion,
                $modalidad,
                $intOrKeep('edad_min', $esp['edad_min']),
                $intOrKeep('edad_max', $esp['edad_max']),
                max(1, (int) $intOrKeep('duracion_fase_semanas', $esp['duracion_fase_semanas'] ?? 4)),
                max(1, (int) $intOrKeep('duracion_meses', $esp['duracion_meses'] ?? 12)),
                $intOrKeep('duracion_semanas', $esp['duracion_semanas']),
                (int) $intOrKeep('inscripcion_por_cuatrimestre', $esp['inscripcion_por_cuatrimestre'] ?? 0) ? 1 : 0,
                max(0, (int) $intOrKeep('parciales_por_cuatrimestre', $esp['parciales_por_cuatrimestre'] ?? 0)),
                $inscApoyo, $inscRef, $inscApoyo,
                $menApoyo, $menRef, $menApoyo,
                $ppApoyo, $ppRef, $ppApoyo,
                $semApoyo, $semRef, $semApoyo,
                $costoCuat, $costoAnual,
                (int) $intOrKeep('es_fija', $esp['es_fija'] ?? 1) ? 1 : 0,
                (int) $intOrKeep('visible', $esp['visible'] ?? 1) ? 1 : 0,
                (int) $intOrKeep('activo', $esp['activo'] ?? 1) ? 1 : 0,
                (int) $intOrKeep('inscripcion_abierta', $esp['inscripcion_abierta'] ?? 1) ? 1 : 0,
                max(0, (int) $intOrKeep('orden', $esp['orden'] ?? 0)),
                $id,
            ]);

            operativo_cncm_guardar_cartas($pdo, $id, [
                'cartas_inscripcion_ref' => $get('cartas_inscripcion_ref') !== '' ? $get('cartas_inscripcion_ref') : ($esp['cartas_inscripcion_ref'] ?? 0),
                'cartas_inscripcion_apoyo' => $get('cartas_inscripcion_apoyo') !== '' ? $get('cartas_inscripcion_apoyo') : ($esp['cartas_inscripcion_apoyo'] ?? 450),
                'cartas_mensualidad_ref' => $get('cartas_mensualidad_ref') !== '' ? $get('cartas_mensualidad_ref') : ($esp['cartas_mensualidad_ref'] ?? 0),
                'cartas_mensualidad_apoyo' => $get('cartas_mensualidad_apoyo') !== '' ? $get('cartas_mensualidad_apoyo') : ($esp['cartas_mensualidad_apoyo'] ?? 0),
                'cartas_pronto_pago_ref' => $get('cartas_pronto_pago_ref') !== '' ? $get('cartas_pronto_pago_ref') : ($esp['cartas_pronto_pago_ref'] ?? 0),
                'cartas_pronto_pago_apoyo' => $get('cartas_pronto_pago_apoyo') !== '' ? $get('cartas_pronto_pago_apoyo') : ($esp['cartas_pronto_pago_apoyo'] ?? 0),
                'cartas_semanal_ref' => $get('cartas_semanal_ref') !== '' ? $get('cartas_semanal_ref') : ($esp['cartas_semanal_ref'] ?? 0),
                'cartas_semanal_apoyo' => $get('cartas_semanal_apoyo') !== '' ? $get('cartas_semanal_apoyo') : ($esp['cartas_semanal_apoyo'] ?? 0),
                'cartas_anual_ref' => $get('cartas_anual_ref') !== '' ? $get('cartas_anual_ref') : ($esp['cartas_anual_ref'] ?? 0),
                'cartas_anual_apoyo' => $get('cartas_anual_apoyo') !== '' ? $get('cartas_anual_apoyo') : ($esp['cartas_anual_apoyo'] ?? 0),
            ]);
            $actualizadas++;
        } catch (Throwable $e) {
            $errores[] = "Línea {$lineNum} ({$clave}): " . $e->getMessage();
        }
    }
    fclose($fh);

    $msg = "{$actualizadas} especialidad(es) actualizada(s)";
    if ($errores !== []) {
        $msg .= '. Con avisos: ' . count($errores);
    }

    return [
        'ok' => $actualizadas > 0 || $errores === [],
        'message' => $msg,
        'actualizadas' => $actualizadas,
        'errores' => array_slice($errores, 0, 20),
    ];
}
