<?php

/**
 * Reportes de cartera para recepción: vencimientos y proyección de cobranza.
 */

function reporte_cartera_puede_ver(): bool
{
    return function_exists('reporte_financiero_puede_ver') && reporte_financiero_puede_ver();
}

/** @return array{desde: string, hasta: string, etiqueta: string} */
function reporte_cartera_rango_proyeccion_siguiente(string $modo, ?string $fechaRef = null): array
{
    $fechaRef = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fechaRef) ? $fechaRef : date('Y-m-d');
    $dt = new DateTimeImmutable($fechaRef);
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    switch ($modo) {
        case 'semana':
            $dow = (int) $dt->format('w');
            $finSemanaActual = $dt->modify('+' . (6 - $dow) . ' days');
            $desdeDt = $finSemanaActual->modify('+1 day');
            $hastaDt = $desdeDt->modify('+6 days');
            $etiqueta = 'Próxima semana: ' . $desdeDt->format('d/m/Y') . ' – ' . $hastaDt->format('d/m/Y');
            break;
        case 'mes':
            $desdeDt = $dt->modify('first day of next month');
            $hastaDt = $desdeDt->modify('last day of this month');
            $etiqueta = 'Próximo mes: ' . ucfirst($meses[(int) $desdeDt->format('n')]) . ' ' . $desdeDt->format('Y');
            break;
        default:
            $desdeDt = $hastaDt = $dt->modify('+1 day');
            $etiqueta = 'Mañana: ' . $desdeDt->format('j') . ' de '
                . $meses[(int) $desdeDt->format('n')] . ' ' . $desdeDt->format('Y');
            break;
    }

    return [
        'desde' => $desdeDt->format('Y-m-d'),
        'hasta' => $hastaDt->format('Y-m-d'),
        'etiqueta' => $etiqueta,
    ];
}

/** @return array{semaforo: string, label: string, meses_atraso: float} */
function reporte_cartera_semaforo(float $mesesAtraso): array
{
    if ($mesesAtraso > 3) {
        return ['semaforo' => 'rojo', 'label' => 'Más de 3 meses', 'meses_atraso' => $mesesAtraso];
    }
    if ($mesesAtraso >= 2) {
        return ['semaforo' => 'naranja', 'label' => '2–3 meses', 'meses_atraso' => $mesesAtraso];
    }

    return ['semaforo' => 'amarillo', 'label' => '1 mes', 'meses_atraso' => $mesesAtraso];
}

/** @param list<array<string, mixed>> $lineas */
function reporte_cartera_meses_atraso_desde_lineas(array $lineas, string $fechaCorte): float
{
    if ($lineas === []) {
        return 0;
    }
    $hoy = new DateTimeImmutable($fechaCorte);
    $maxMeses = 0.0;

    foreach ($lineas as $l) {
        if (($l['tipo'] ?? '') === 'inscripcion') {
            continue;
        }
        $periodo = (string) ($l['periodo'] ?? '');
        if ($periodo === '') {
            continue;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            $ini = new DateTimeImmutable($periodo . '-01');
            $diff = ($hoy->format('Y') - $ini->format('Y')) * 12
                + ((int) $hoy->format('n') - (int) $ini->format('n'));
            $maxMeses = max($maxMeses, (float) max(1, $diff));
        } elseif (preg_match('/^(\d{4})-W(\d{2})$/', $periodo, $m)) {
            $ini = new DateTimeImmutable();
            $ini = $ini->setISODate((int) $m[1], (int) $m[2]);
            $semanas = max(1, (int) floor($ini->diff($hoy)->days / 7));
            $maxMeses = max($maxMeses, round($semanas / 4, 1));
        }
    }

    return $maxMeses > 0 ? $maxMeses : 1.0;
}

/** @return list<int> */
function reporte_cartera_alumnos_activos_ids(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        "SELECT id_alumno FROM alumnos WHERE id_plantel = ? AND estado = 'activo' ORDER BY apellido_paterno, apellido_materno, nombres, nombre"
    );
    $st->execute([$idPlantel]);

    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function reporte_cartera_nombre_alumno(array $al): string
{
    $parts = array_filter([
        trim((string) ($al['nombres'] ?? $al['nombre'] ?? '')),
        trim((string) ($al['apellido_paterno'] ?? $al['apellido'] ?? '')),
        trim((string) ($al['apellido_materno'] ?? '')),
    ]);

    return implode(' ', $parts) ?: 'Sin nombre';
}

/** @return array{dia_mediana: int|null, dia_promedio: float|null, muestras: int, confianza: string} */
function reporte_cartera_habitual_pago(PDO $pdo, int $idAlumno, string $formaPago): array
{
    $tipo = $formaPago === 'semanal' ? 'semanal' : 'mensualidad';
    $st = $pdo->prepare(
        "SELECT creado_en FROM alumno_pagos
         WHERE id_alumno = ? AND tipo = ?" . pago_sql_filtro_activos() . "
         ORDER BY creado_en DESC LIMIT 12"
    );
    $st->execute([$idAlumno, $tipo]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
    if ($rows === []) {
        $st2 = $pdo->prepare(
            "SELECT creado_en FROM alumno_pagos
             WHERE id_alumno = ? AND tipo IN ('mensualidad','semanal','abono')" . pago_sql_filtro_activos() . "
             ORDER BY creado_en DESC LIMIT 12"
        );
        $st2->execute([$idAlumno]);
        $rows = $st2->fetchAll(PDO::FETCH_COLUMN);
    }
    if ($rows === []) {
        return ['dia_mediana' => null, 'dia_promedio' => null, 'muestras' => 0, 'confianza' => 'sin_datos'];
    }

    $dias = [];
    foreach ($rows as $f) {
        $dias[] = (int) date('j', strtotime((string) $f));
    }
    sort($dias);
    $n = count($dias);
    $mediana = $n % 2 === 1
        ? $dias[(int) floor($n / 2)]
        : (int) round(($dias[$n / 2 - 1] + $dias[$n / 2]) / 2);
    $promedio = array_sum($dias) / $n;
    $confianza = $n >= 6 ? 'alta' : ($n >= 3 ? 'media' : 'baja');

    return [
        'dia_mediana' => $mediana,
        'dia_promedio' => round($promedio, 1),
        'muestras' => $n,
        'confianza' => $confianza,
    ];
}

function reporte_cartera_predecir_fecha_pago(
    string $formaPago,
    array $habito,
    string $desde,
    string $hasta
): ?string {
    if (empty($habito['dia_mediana'])) {
        return null;
    }
    $dia = (int) $habito['dia_mediana'];
    $desdeDt = new DateTimeImmutable($desde);
    $hastaDt = new DateTimeImmutable($hasta);

    if ($formaPago === 'semanal') {
        $cursor = $desdeDt;
        while ($cursor <= $hastaDt) {
            if ((int) $cursor->format('j') === $dia || (int) $cursor->format('w') === ($dia % 7)) {
                return $cursor->format('Y-m-d');
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $desdeDt->format('Y-m-d');
    }

    $ym = $desdeDt->format('Y-m');
    $ultimo = (int) $desdeDt->format('t');
    $diaReal = min($dia, $ultimo);
    $candidata = $ym . '-' . str_pad((string) $diaReal, 2, '0', STR_PAD_LEFT);
    $cDt = new DateTimeImmutable($candidata);
    if ($cDt >= $desdeDt && $cDt <= $hastaDt) {
        return $candidata;
    }
    if ($desdeDt->format('Y-m') !== $hastaDt->format('Y-m')) {
        $ym2 = $hastaDt->format('Y-m');
        $ultimo2 = (int) $hastaDt->format('t');
        $diaReal2 = min($dia, $ultimo2);
        $candidata2 = $ym2 . '-' . str_pad((string) $diaReal2, 2, '0', STR_PAD_LEFT);
        $cDt2 = new DateTimeImmutable($candidata2);
        if ($cDt2 >= $desdeDt && $cDt2 <= $hastaDt) {
            return $candidata2;
        }
    }

    return null;
}

/** @return bool */
function reporte_cartera_periodo_en_rango(string $periodo, string $desde, string $hasta): bool
{
    if (preg_match('/^\d{4}-\d{2}$/', $periodo)) {
        $p = $periodo . '-01';
        $fin = (new DateTimeImmutable($periodo . '-01'))->modify('last day of this month')->format('Y-m-d');

        return $fin >= $desde && $p <= $hasta;
    }
    if (preg_match('/^(\d{4})-W(\d{2})$/', $periodo, $m)) {
        $ini = (new DateTimeImmutable())->setISODate((int) $m[1], (int) $m[2]);
        $fin = $ini->modify('+6 days');

        return $fin->format('Y-m-d') >= $desde && $ini->format('Y-m-d') <= $hasta;
    }

    return false;
}

/**
 * @return array{filas: list<array>, resumen: array<string, mixed>}
 */
/** @return array<string, mixed> */
function reporte_cartera_parse_filtros(): array
{
    $src = array_merge($_GET, $_POST);

    return [
        'id_especialidad' => (int) ($src['id_especialidad'] ?? 0),
        'id_grupo' => (int) ($src['id_grupo'] ?? 0),
        'semaforo' => trim((string) ($src['semaforo'] ?? '')),
        'forma_pago' => trim((string) ($src['forma_pago'] ?? '')),
        'q' => trim((string) ($src['q'] ?? '')),
    ];
}

/** @return array<string, mixed> */
function reporte_cartera_filtros_catalogo(PDO $pdo, int $idPlantel): array
{
    $esp = $pdo->query('SELECT id_especialidad, nombre, clave FROM especialidades WHERE activo = 1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave FROM grupos g
         WHERE g.id_plantel = ? ORDER BY g.clave'
    );
    $st->execute([$idPlantel]);

    return [
        'especialidades' => $esp ?: [],
        'grupos' => $st->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'semaforos' => [
            ['id' => 'amarillo', 'label' => 'Amarillo (1 mes)'],
            ['id' => 'naranja', 'label' => 'Naranja (2–3 meses)'],
            ['id' => 'rojo', 'label' => 'Rojo (+3 meses)'],
        ],
        'formas_pago' => [
            ['id' => 'mensual', 'label' => 'Mensual'],
            ['id' => 'semanal', 'label' => 'Semanal'],
        ],
    ];
}

/** @return array<int, array{id_especialidad: int, esp_nombre: string, id_grupo: int, grupo_clave: string}> */
function reporte_cartera_meta_alumnos(PDO $pdo, int $idPlantel, array $idsAlumnos): array
{
    if ($idsAlumnos === []) {
        return [];
    }
    $idsAlumnos = array_values(array_unique(array_map('intval', $idsAlumnos)));
    $ph = implode(',', array_fill(0, count($idsAlumnos), '?'));
    $st = $pdo->prepare(
        "SELECT a.id_alumno,
                COALESCE(e.id_especialidad, 0) AS id_especialidad,
                COALESCE(e.nombre, '') AS esp_nombre,
                COALESCE(g.id_grupo, 0) AS id_grupo,
                COALESCE(g.clave, '') AS grupo_clave
         FROM alumnos a
         LEFT JOIN alumno_grupos ag ON ag.id_alumno = a.id_alumno AND ag.activo = 1
         LEFT JOIN grupos g ON g.id_grupo = ag.id_grupo AND g.id_plantel = a.id_plantel
         LEFT JOIN especialidades e ON e.id_especialidad = COALESCE(g.id_especialidad, a.id_especialidad)
         WHERE a.id_plantel = ? AND a.id_alumno IN ($ph)
         ORDER BY ag.id_grupo DESC"
    );
    $st->execute(array_merge([$idPlantel], $idsAlumnos));
    $map = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = (int) $r['id_alumno'];
        if (!isset($map[$id])) {
            $map[$id] = [
                'id_especialidad' => (int) ($r['id_especialidad'] ?? 0),
                'esp_nombre' => (string) ($r['esp_nombre'] ?? ''),
                'id_grupo' => (int) ($r['id_grupo'] ?? 0),
                'grupo_clave' => (string) ($r['grupo_clave'] ?? ''),
            ];
        }
    }

    return $map;
}

/** @param list<array<string, mixed>> $filas */
function reporte_cartera_enriquecer_filas(PDO $pdo, int $idPlantel, array $filas): array
{
    if ($filas === []) {
        return [];
    }
    $meta = reporte_cartera_meta_alumnos($pdo, $idPlantel, array_column($filas, 'id_alumno'));
    foreach ($filas as &$f) {
        $m = $meta[(int) ($f['id_alumno'] ?? 0)] ?? [];
        $f['id_especialidad'] = (int) ($m['id_especialidad'] ?? 0);
        $f['esp_nombre'] = (string) ($m['esp_nombre'] ?? '');
        $f['id_grupo'] = (int) ($m['id_grupo'] ?? 0);
        $f['grupo_clave'] = (string) ($m['grupo_clave'] ?? '');
    }
    unset($f);

    return $filas;
}

/** @param list<array<string, mixed>> $filas @param array<string, mixed> $filtros */
function reporte_cartera_aplicar_filtros(array $filas, array $filtros): array
{
    $idEsp = (int) ($filtros['id_especialidad'] ?? 0);
    $idGrupo = (int) ($filtros['id_grupo'] ?? 0);
    $semaforo = trim((string) ($filtros['semaforo'] ?? ''));
    $forma = trim((string) ($filtros['forma_pago'] ?? ''));
    $q = mb_strtolower(trim((string) ($filtros['q'] ?? '')));

    return array_values(array_filter($filas, static function ($f) use ($idEsp, $idGrupo, $semaforo, $forma, $q) {
        if ($idEsp > 0 && (int) ($f['id_especialidad'] ?? 0) !== $idEsp) {
            return false;
        }
        if ($idGrupo > 0 && (int) ($f['id_grupo'] ?? 0) !== $idGrupo) {
            return false;
        }
        if ($semaforo !== '' && ($f['semaforo'] ?? '') !== $semaforo) {
            return false;
        }
        if ($forma !== '' && ($f['forma_pago'] ?? '') !== $forma) {
            return false;
        }
        if ($q !== '') {
            $hay = mb_strtolower(
                ($f['nombre'] ?? '') . ' ' . ($f['numero_control'] ?? '') . ' ' . ($f['grupo_clave'] ?? '')
            );
            if (!str_contains($hay, $q)) {
                return false;
            }
        }

        return true;
    }));
}

/** @param list<array<string, mixed>> $filas */
function reporte_cartera_enviar_csv(array $filas, array $columnas, string $filename): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, array_values($columnas));
    foreach ($filas as $f) {
        $row = [];
        foreach (array_keys($columnas) as $k) {
            $row[] = $f[$k] ?? '';
        }
        fputcsv($out, $row);
    }
    fclose($out);
}

/**
 * @param array<string, mixed> $filtros
 * @return array{filas: list<array>, resumen: array<string, mixed>}
 */
function reporte_cartera_vencimientos(PDO $pdo, int $idPlantel, ?string $fechaCorte = null, array $filtros = []): array
{
    $fechaCorte = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fechaCorte) ? $fechaCorte : date('Y-m-d');
    $filas = [];
    $porSemaforo = ['amarillo' => 0, 'naranja' => 0, 'rojo' => 0];
    $totalAdeudo = 0.0;

    foreach (reporte_cartera_alumnos_activos_ids($pdo, $idPlantel) as $idAlumno) {
        $ec = pago_estado_cuenta($pdo, $idAlumno, $fechaCorte);
        if (!$ec['ok']) {
            continue;
        }
        $adeudo = (float) ($ec['resumen']['adeudo_colegiatura'] ?? 0);
        if ($adeudo < 0.01) {
            continue;
        }
        $lineasColeg = array_values(array_filter(
            $ec['lineas_adeudo'] ?? [],
            fn($l) => ($l['tipo'] ?? '') !== 'inscripcion'
        ));
        if ($lineasColeg === []) {
            continue;
        }

        $mesesAtraso = reporte_cartera_meses_atraso_desde_lineas($lineasColeg, $fechaCorte);
        $sem = reporte_cartera_semaforo($mesesAtraso);
        $porSemaforo[$sem['semaforo']] = ($porSemaforo[$sem['semaforo']] ?? 0) + 1;
        $totalAdeudo += $adeudo;

        $periodos = array_map(fn($l) => (string) ($l['periodo'] ?? ''), $lineasColeg);
        $al = $ec['alumno'] ?? [];
        $forma = 'mensual';
        foreach ($ec['inscripciones'] ?? [] as $ins) {
            if ((float) ($ins['adeudo_colegiatura'] ?? 0) > 0) {
                $forma = (string) ($ins['forma_pago'] ?? 'mensual');
                break;
            }
        }

        $filas[] = [
            'id_alumno' => $idAlumno,
            'numero_control' => $al['numero_control'] ?? '',
            'nombre' => reporte_cartera_nombre_alumno($al),
            'telefono' => trim((string) ($al['telefono'] ?? $al['celular'] ?? '')),
            'adeudo' => $adeudo,
            'adeudo_fmt' => catalog_format_mxn($adeudo),
            'periodos_vencidos' => count($lineasColeg),
            'periodo_mas_antiguo' => min($periodos),
            'detalle_periodos' => implode(', ', array_slice($periodos, 0, 4))
                . (count($periodos) > 4 ? '…' : ''),
            'meses_atraso' => $mesesAtraso,
            'semaforo' => $sem['semaforo'],
            'semaforo_label' => $sem['label'],
            'forma_pago' => $forma,
            'lineas' => $lineasColeg,
        ];
    }

    usort($filas, static function ($a, $b) {
        $ord = ['rojo' => 0, 'naranja' => 1, 'amarillo' => 2];
        $ca = $ord[$a['semaforo'] ?? ''] ?? 9;
        $cb = $ord[$b['semaforo'] ?? ''] ?? 9;
        if ($ca !== $cb) {
            return $ca <=> $cb;
        }

        return ($b['adeudo'] ?? 0) <=> ($a['adeudo'] ?? 0);
    });

    $filas = reporte_cartera_enriquecer_filas($pdo, $idPlantel, $filas);
    $filas = reporte_cartera_aplicar_filtros($filas, $filtros);
    $totalAdeudo = array_sum(array_map(fn($f) => (float) ($f['adeudo'] ?? 0), $filas));
    $porSemaforo = ['amarillo' => 0, 'naranja' => 0, 'rojo' => 0];
    foreach ($filas as $f) {
        $s = (string) ($f['semaforo'] ?? '');
        if (isset($porSemaforo[$s])) {
            $porSemaforo[$s]++;
        }
    }

    return [
        'filas' => $filas,
        'resumen' => [
            'total_alumnos' => count($filas),
            'total_adeudo' => round($totalAdeudo, 2),
            'total_adeudo_fmt' => catalog_format_mxn($totalAdeudo),
            'por_semaforo' => $porSemaforo,
            'fecha_corte' => $fechaCorte,
        ],
    ];
}

/**
 * @param array<string, mixed> $filtros
 * @return array{filas: list<array>, resumen: array<string, mixed>, rango: array<string, string>}
 */
function reporte_cartera_proyeccion(PDO $pdo, int $idPlantel, string $modo, ?string $fechaRef = null, array $filtros = []): array
{
    $rango = reporte_cartera_rango_proyeccion_siguiente($modo, $fechaRef);
    $desde = $rango['desde'];
    $hasta = $rango['hasta'];
    $filas = [];
    $totalEsperado = 0.0;
    $totalAdeudoPrevio = 0.0;
    $alumnosEsperados = 0;

    foreach (reporte_cartera_alumnos_activos_ids($pdo, $idPlantel) as $idAlumno) {
        $ecHoy = pago_estado_cuenta($pdo, $idAlumno, date('Y-m-d'));
        if (!$ecHoy['ok']) {
            continue;
        }
        $ecFin = pago_estado_cuenta($pdo, $idAlumno, $hasta);
        if (!$ecFin['ok']) {
            continue;
        }

        $adeudoHoy = (float) ($ecHoy['resumen']['adeudo_colegiatura'] ?? 0);
        $lineasFin = array_values(array_filter(
            $ecFin['lineas_adeudo'] ?? [],
            fn($l) => ($l['tipo'] ?? '') !== 'inscripcion'
        ));

        $cargosNuevos = [];
        $cargosPrevios = [];
        foreach ($lineasFin as $l) {
            $periodo = (string) ($l['periodo'] ?? '');
            $saldo = (float) ($l['saldo'] ?? 0);
            if ($saldo < 0.01) {
                continue;
            }
            if (reporte_cartera_periodo_en_rango($periodo, $desde, $hasta)) {
                $cargosNuevos[] = $l;
            } elseif ($periodo !== '' && !reporte_cartera_periodo_en_rango($periodo, $desde, $hasta)) {
                $cargosPrevios[] = $l;
            }
        }

        $montoNuevo = array_sum(array_map(fn($l) => (float) ($l['saldo'] ?? 0), $cargosNuevos));
        $montoPrevio = array_sum(array_map(fn($l) => (float) ($l['saldo'] ?? 0), $cargosPrevios));

        if ($montoNuevo < 0.01 && $montoPrevio < 0.01 && $adeudoHoy < 0.01) {
            continue;
        }

        $forma = 'mensual';
        foreach ($ecHoy['inscripciones'] ?? [] as $ins) {
            $forma = (string) ($ins['forma_pago'] ?? 'mensual');
            break;
        }
        $habito = reporte_cartera_habitual_pago($pdo, $idAlumno, $forma);
        $fechaProbable = reporte_cartera_predecir_fecha_pago($forma, $habito, $desde, $hasta);
        $montoProyectado = $montoNuevo + ($montoPrevio > 0 ? $montoPrevio * 0.35 : 0);

        if ($montoNuevo >= 0.01 || $montoPrevio >= 0.01) {
            $alumnosEsperados++;
        }
        $totalEsperado += $montoNuevo;
        $totalAdeudoPrevio += $montoPrevio;

        $al = $ecHoy['alumno'] ?? [];
        $filas[] = [
            'id_alumno' => $idAlumno,
            'numero_control' => $al['numero_control'] ?? '',
            'nombre' => reporte_cartera_nombre_alumno($al),
            'forma_pago' => $forma,
            'monto_periodo' => round($montoNuevo, 2),
            'monto_periodo_fmt' => catalog_format_mxn($montoNuevo),
            'adeudo_previo' => round($montoPrevio, 2),
            'adeudo_previo_fmt' => catalog_format_mxn($montoPrevio),
            'monto_proyectado' => round($montoProyectado, 2),
            'monto_proyectado_fmt' => catalog_format_mxn($montoProyectado),
            'fecha_probable' => $fechaProbable,
            'fecha_probable_fmt' => $fechaProbable ? date('d/m/Y', strtotime($fechaProbable)) : '—',
            'habito_dia' => $habito['dia_mediana'],
            'habito_confianza' => $habito['confianza'],
            'periodos_nuevos' => count($cargosNuevos),
            'detalle' => implode('; ', array_map(
                fn($l) => ($l['detalle'] ?? $l['periodo'] ?? '') . ' ' . catalog_format_mxn((float) ($l['saldo'] ?? 0)),
                array_slice($cargosNuevos, 0, 3)
            )),
        ];
    }

    usort($filas, static fn($a, $b) => ($b['monto_proyectado'] ?? 0) <=> ($a['monto_proyectado'] ?? 0));

    $filas = reporte_cartera_enriquecer_filas($pdo, $idPlantel, $filas);
    $filas = reporte_cartera_aplicar_filtros($filas, $filtros);

    $totalEsperado = array_sum(array_map(fn($f) => (float) ($f['monto_periodo'] ?? 0), $filas));
    $totalAdeudoPrevio = array_sum(array_map(fn($f) => (float) ($f['adeudo_previo'] ?? 0), $filas));
    $alumnosEsperados = count($filas);
    $metaRecuperacion = round($totalAdeudoPrevio * 0.35, 2);

    return [
        'filas' => $filas,
        'rango' => $rango,
        'resumen' => [
            'modo' => $modo,
            'alumnos' => $alumnosEsperados,
            'cargos_nuevos' => round($totalEsperado, 2),
            'cargos_nuevos_fmt' => catalog_format_mxn($totalEsperado),
            'adeudo_previo' => round($totalAdeudoPrevio, 2),
            'adeudo_previo_fmt' => catalog_format_mxn($totalAdeudoPrevio),
            'recuperacion_estimada' => $metaRecuperacion,
            'recuperacion_estimada_fmt' => catalog_format_mxn($metaRecuperacion),
            'meta_total' => round($totalEsperado + $metaRecuperacion, 2),
            'meta_total_fmt' => catalog_format_mxn($totalEsperado + $metaRecuperacion),
            'nota_meta' => 'La meta incluye cargos del periodo más ~35% de adeudo previo recuperable según historial.',
        ],
    ];
}
