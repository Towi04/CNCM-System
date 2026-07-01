<?php

/**
 * Reporte semanal de asistencia / plantilla — semanas domingo–sábado (1–52).
 */

function reporte_semanal_puede_ver(): bool
{
    return function_exists('reporte_academico_puede_ver') && reporte_academico_puede_ver();
}

function reporte_semanal_ensure_schema(PDO $pdo): void
{
    alumno_ensure_schema($pdo);
    asistencia_ensure_schema($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS reporte_semanal_movimiento (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_alumno INT UNSIGNED NOT NULL,
            id_grupo INT UNSIGNED NOT NULL,
            id_grupo_otro INT UNSIGNED NULL,
            tipo ENUM(\'I\',\'R\',\'C_POS\',\'C_NEG\',\'B\',\'FC\') NOT NULL,
            fecha DATE NOT NULL,
            anio SMALLINT UNSIGNED NOT NULL,
            semana TINYINT UNSIGNED NOT NULL,
            nota VARCHAR(500) NULL,
            id_usuario INT UNSIGNED NULL,
            origen ENUM(\'auto\',\'manual\') NOT NULL DEFAULT \'auto\',
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rsm_mov (id_alumno, id_grupo, anio, semana, tipo),
            KEY idx_rsm_plantel_sem (id_plantel, anio, semana),
            KEY idx_rsm_grupo (id_grupo, anio, semana)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** Etiquetas de días (domingo = 0). */
function reporte_semanal_dias_nombre(): array
{
    return ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
}

/**
 * Semana escolar: domingo–sábado. Semana 1 = semana que contiene el 1 de enero.
 *
 * @return array{anio:int, semana:int, inicio:string, fin:string, etiqueta:string}
 */
function reporte_semanal_desde_fecha(string $fecha): array
{
    $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d');
    $ts = strtotime($fecha);
    $anio = (int) date('Y', $ts);
    $jan1 = strtotime($anio . '-01-01');
    $jan1Dow = (int) date('w', $jan1);
    $firstSunday = strtotime('-' . $jan1Dow . ' days', $jan1);
    $weekStartTs = strtotime('+' . (int) floor(($ts - $firstSunday) / 604800) . ' weeks', $firstSunday);
    if ($weekStartTs < $firstSunday) {
        $anio--;
        $jan1 = strtotime($anio . '-01-01');
        $jan1Dow = (int) date('w', $jan1);
        $firstSunday = strtotime('-' . $jan1Dow . ' days', $jan1);
        $weekStartTs = strtotime('+' . (int) floor(($ts - $firstSunday) / 604800) . ' weeks', $firstSunday);
    }
    $semana = (int) floor(($weekStartTs - $firstSunday) / 604800) + 1;
    if ($semana > 52) {
        $semana = 52;
    }
    $inicio = date('Y-m-d', $weekStartTs);
    $fin = date('Y-m-d', strtotime('+6 days', $weekStartTs));
    return [
        'anio' => $anio,
        'semana' => $semana,
        'inicio' => $inicio,
        'fin' => $fin,
        'etiqueta' => 'Semana ' . $semana . ' · ' . date('d/m', $weekStartTs) . '–' . date('d/m/Y', strtotime($fin)),
    ];
}

/** @return array{inicio:string, fin:string} */
function reporte_semanal_rango(int $anio, int $semana): array
{
    $semana = max(1, min(52, $semana));
    $jan1 = strtotime($anio . '-01-01');
    $jan1Dow = (int) date('w', $jan1);
    $firstSunday = strtotime('-' . $jan1Dow . ' days', $jan1);
    $weekStartTs = strtotime('+' . ($semana - 1) . ' weeks', $firstSunday);
    return [
        'inicio' => date('Y-m-d', $weekStartTs),
        'fin' => date('Y-m-d', strtotime('+6 days', $weekStartTs)),
    ];
}

/** @return list<array{anio:int, semana:int, inicio:string, fin:string, etiqueta:string}> */
function reporte_semanal_listar_semanas(int $anio, ?int $mes = null): array
{
    $out = [];
    for ($s = 1; $s <= 52; $s++) {
        $r = reporte_semanal_rango($anio, $s);
        if ($mes !== null) {
            $mIni = (int) date('n', strtotime($r['inicio']));
            $mFin = (int) date('n', strtotime($r['fin']));
            if ($mIni !== $mes && $mFin !== $mes) {
                continue;
            }
        }
        $out[] = [
            'anio' => $anio,
            'semana' => $s,
            'inicio' => $r['inicio'],
            'fin' => $r['fin'],
            'etiqueta' => 'S' . $s . ' (' . date('d/m', strtotime($r['inicio'])) . '–' . date('d/m', strtotime($r['fin'])) . ')',
        ];
    }
    return $out;
}

/** @return list<array{anio:int, semana:int, inicio:string, fin:string}> */
function reporte_semanal_rango_semanas(int $anio, int $desde, int $hasta): array
{
    $desde = max(1, min(52, $desde));
    $hasta = max($desde, min(52, $hasta));
    $out = [];
    for ($s = $desde; $s <= $hasta; $s++) {
        $r = reporte_semanal_rango($anio, $s);
        $out[] = ['anio' => $anio, 'semana' => $s, 'inicio' => $r['inicio'], 'fin' => $r['fin']];
    }
    return $out;
}

function reporte_semanal_conteo_activos(PDO $pdo, int $idGrupo, string $fecha): int
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.estado = \'activo\'
           AND ag.fecha_inicio <= ?
           AND (ag.fecha_baja IS NULL OR ag.fecha_baja > ?)'
    );
    $st->execute([$idGrupo, $fecha, $fecha]);
    return (int) $st->fetchColumn();
}

function reporte_semanal_grupo_horario(PDO $pdo, int $idGrupo): array
{
    $st = $pdo->prepare(
        'SELECT dia_semana, hora_inicio, hora_fin FROM grupo_horarios
         WHERE id_grupo = ? AND activo = 1 ORDER BY dia_semana, hora_inicio'
    );
    $st->execute([$idGrupo]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $dias = reporte_semanal_dias_nombre();
    $partes = [];
    foreach ($rows as $r) {
        $d = $dias[(int) $r['dia_semana']] ?? '?';
        $hi = substr((string) $r['hora_inicio'], 0, 5);
        $hf = substr((string) $r['hora_fin'], 0, 5);
        $partes[] = $d . ' ' . $hi . '–' . $hf;
    }
    $g = $pdo->prepare('SELECT horario_texto FROM grupos WHERE id_grupo = ? LIMIT 1');
    $g->execute([$idGrupo]);
    $txt = trim((string) $g->fetchColumn());
    return [
        'dias' => count($rows),
        'horario' => $txt !== '' ? $txt : ($partes ? implode(' · ', $partes) : '—'),
        'dias_detalle' => $partes,
    ];
}

/** Fechas con clase programada en el rango. */
function reporte_semanal_fechas_clase(PDO $pdo, int $idGrupo, string $inicio, string $fin): array
{
    $st = $pdo->prepare(
        'SELECT DISTINCT dia_semana FROM grupo_horarios WHERE id_grupo = ? AND activo = 1'
    );
    $st->execute([$idGrupo]);
    $dows = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if ($dows === []) {
        return [];
    }
    $fechas = [];
    $cur = strtotime($inicio);
    $end = strtotime($fin);
    while ($cur <= $end) {
        if (in_array((int) date('w', $cur), $dows, true)) {
            $fechas[] = date('Y-m-d', $cur);
        }
        $cur = strtotime('+1 day', $cur);
    }
    return $fechas;
}

function reporte_semanal_alumno_asistio(PDO $pdo, int $idAlumno, int $idGrupo, string $inicio, string $fin): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM asistencias
         WHERE id_alumno = ? AND id_grupo = ? AND fecha BETWEEN ? AND ? AND presente = 1 LIMIT 1'
    );
    $st->execute([$idAlumno, $idGrupo, $inicio, $fin]);
    return (bool) $st->fetchColumn();
}

function reporte_semanal_contar_movimientos(PDO $pdo, int $idGrupo, int $anio, int $semana): array
{
    $st = $pdo->prepare(
        'SELECT tipo, COUNT(*) AS n FROM reporte_semanal_movimiento
         WHERE id_grupo = ? AND anio = ? AND semana = ?
         GROUP BY tipo'
    );
    $st->execute([$idGrupo, $anio, $semana]);
    $map = ['I' => 0, 'R' => 0, 'C_POS' => 0, 'C_NEG' => 0, 'B' => 0, 'FC' => 0];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $map[$r['tipo']] = (int) $r['n'];
    }
    return $map;
}

function reporte_semanal_registrar_movimiento(
    PDO $pdo,
    int $idPlantel,
    int $idAlumno,
    int $idGrupo,
    string $tipo,
    string $fecha,
    ?int $idGrupoOtro = null,
    ?string $nota = null,
    ?int $idUsuario = null,
    string $origen = 'auto'
): bool {
    reporte_semanal_ensure_schema($pdo);
    $tipos = ['I', 'R', 'C_POS', 'C_NEG', 'B', 'FC'];
    if (!in_array($tipo, $tipos, true)) {
        return false;
    }
    $sem = reporte_semanal_desde_fecha($fecha);
    try {
        $pdo->prepare(
            'INSERT INTO reporte_semanal_movimiento
                (id_plantel, id_alumno, id_grupo, id_grupo_otro, tipo, fecha, anio, semana, nota, id_usuario, origen)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                fecha = VALUES(fecha),
                id_grupo_otro = COALESCE(VALUES(id_grupo_otro), id_grupo_otro),
                nota = COALESCE(VALUES(nota), nota),
                id_usuario = COALESCE(VALUES(id_usuario), id_usuario),
                origen = VALUES(origen)'
        )->execute([
            $idPlantel, $idAlumno, $idGrupo, $idGrupoOtro, $tipo, $fecha,
            $sem['anio'], $sem['semana'], $nota, $idUsuario, $origen,
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/** Detecta I, cambios, bajas y reingresos para una semana. */
function reporte_semanal_sincronizar(PDO $pdo, int $idPlantel, int $anio, int $semana): void
{
    reporte_semanal_ensure_schema($pdo);
    $rango = reporte_semanal_rango($anio, $semana);
    $ini = $rango['inicio'];
    $fin = $rango['fin'];
    $prevFin = date('Y-m-d', strtotime('-1 day', strtotime($ini)));
    $prevSem = reporte_semanal_desde_fecha($prevFin);
    $prevRango = reporte_semanal_rango($prevSem['anio'], $prevSem['semana']);

    $grupos = $pdo->prepare('SELECT id_grupo FROM grupos WHERE id_plantel = ?');
    $grupos->execute([$idPlantel]);
    $idsGrupos = array_map('intval', $grupos->fetchAll(PDO::FETCH_COLUMN));

    foreach ($idsGrupos as $idGrupo) {
        $stAg = $pdo->prepare(
            'SELECT ag.id_alumno, ag.fecha_inicio, ag.fecha_baja, a.estado
             FROM alumno_grupos ag
             INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
             WHERE ag.id_grupo = ?'
        );
        $stAg->execute([$idGrupo]);
        foreach ($stAg->fetchAll(PDO::FETCH_ASSOC) as $ag) {
            $idAlumno = (int) $ag['id_alumno'];
            $fIni = (string) ($ag['fecha_inicio'] ?? '');
            $fBaja = $ag['fecha_baja'] ?? null;

            if ($fIni >= $ini && $fIni <= $fin) {
                $prev = $pdo->prepare(
                    'SELECT COUNT(*) FROM alumno_grupos
                     WHERE id_alumno = ? AND id_grupo = ? AND fecha_inicio < ?'
                );
                $prev->execute([$idAlumno, $idGrupo, $ini]);
                $esPrimera = (int) $prev->fetchColumn() === 0;

                $otroGrupo = $pdo->prepare(
                    'SELECT id_grupo FROM alumno_grupos
                     WHERE id_alumno = ? AND id_grupo != ? AND activo = 1
                       AND fecha_inicio < ?
                     LIMIT 1'
                );
                $otroGrupo->execute([$idAlumno, $idGrupo, $ini]);
                $idOtro = $otroGrupo->fetchColumn();

                if ($idOtro) {
                    reporte_semanal_registrar_movimiento(
                        $pdo, $idPlantel, $idAlumno, $idGrupo, 'C_POS', $fIni, (int) $idOtro
                    );
                } elseif ($esPrimera) {
                    reporte_semanal_registrar_movimiento($pdo, $idPlantel, $idAlumno, $idGrupo, 'I', $fIni);
                }
            }

            if ($fBaja && $fBaja >= $ini && $fBaja <= $fin) {
                $nuevo = $pdo->prepare(
                    'SELECT id_grupo FROM alumno_grupos
                     WHERE id_alumno = ? AND id_grupo != ? AND activo = 1
                       AND fecha_inicio >= ? AND fecha_inicio <= ?
                     LIMIT 1'
                );
                $nuevo->execute([$idAlumno, $idGrupo, $ini, $fin]);
                $idNuevo = $nuevo->fetchColumn();
                if ($idNuevo) {
                    reporte_semanal_registrar_movimiento(
                        $pdo, $idPlantel, $idAlumno, $idGrupo, 'C_NEG', $fBaja, (int) $idNuevo
                    );
                } elseif (($ag['estado'] ?? '') === 'baja') {
                    reporte_semanal_registrar_movimiento($pdo, $idPlantel, $idAlumno, $idGrupo, 'B', $fBaja);
                }
            }
        }

        $activosIni = $pdo->prepare(
            'SELECT ag.id_alumno FROM alumno_grupos ag
             INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
             WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.estado = \'activo\'
               AND ag.fecha_inicio <= ? AND (ag.fecha_baja IS NULL OR ag.fecha_baja > ?)'
        );
        $activosIni->execute([$idGrupo, $ini, $ini]);
        $fechasClase = reporte_semanal_fechas_clase($pdo, $idGrupo, $ini, $fin);

        foreach ($activosIni->fetchAll(PDO::FETCH_COLUMN) as $idAlumno) {
            $idAlumno = (int) $idAlumno;

            $yaCneg = $pdo->prepare(
                'SELECT 1 FROM reporte_semanal_movimiento
                 WHERE id_alumno = ? AND id_grupo = ? AND anio = ? AND semana = ? AND tipo = \'C_NEG\' LIMIT 1'
            );
            $yaCneg->execute([$idAlumno, $idGrupo, $anio, $semana]);
            if ($yaCneg->fetchColumn()) {
                continue;
            }

            $yaFc = $pdo->prepare(
                'SELECT 1 FROM reporte_semanal_movimiento
                 WHERE id_alumno = ? AND id_grupo = ? AND anio = ? AND semana = ? AND tipo = \'FC\' LIMIT 1'
            );
            $yaFc->execute([$idAlumno, $idGrupo, $anio, $semana]);
            if ($yaFc->fetchColumn()) {
                continue;
            }

            if ($fechasClase === []) {
                continue;
            }

            $asistio = reporte_semanal_alumno_asistio($pdo, $idAlumno, $idGrupo, $ini, $fin);
            $asistioPrev = reporte_semanal_alumno_asistio(
                $pdo, $idAlumno, $idGrupo, $prevRango['inicio'], $prevRango['fin']
            );

            if (!$asistio) {
                $dupB = $pdo->prepare(
                    'SELECT 1 FROM reporte_semanal_movimiento
                     WHERE id_alumno = ? AND id_grupo = ? AND anio = ? AND semana = ? AND tipo = \'B\' LIMIT 1'
                );
                $dupB->execute([$idAlumno, $idGrupo, $anio, $semana]);
                if (!$dupB->fetchColumn()) {
                    reporte_semanal_registrar_movimiento($pdo, $idPlantel, $idAlumno, $idGrupo, 'B', $fin);
                }
            } elseif (!$asistioPrev) {
                $esNuevo = $pdo->prepare(
                    'SELECT 1 FROM reporte_semanal_movimiento
                     WHERE id_alumno = ? AND id_grupo = ? AND anio = ? AND semana = ? AND tipo IN (\'I\',\'C_POS\') LIMIT 1'
                );
                $esNuevo->execute([$idAlumno, $idGrupo, $anio, $semana]);
                if (!$esNuevo->fetchColumn()) {
                    $dupR = $pdo->prepare(
                        'SELECT 1 FROM reporte_semanal_movimiento
                         WHERE id_alumno = ? AND id_grupo = ? AND anio = ? AND semana = ? AND tipo = \'R\' LIMIT 1'
                    );
                    $dupR->execute([$idAlumno, $idGrupo, $anio, $semana]);
                    if (!$dupR->fetchColumn()) {
                        reporte_semanal_registrar_movimiento($pdo, $idPlantel, $idAlumno, $idGrupo, 'R', $fin);
                    }
                }
            }
        }
    }
}

function reporte_semanal_conteo_plantel_activos(PDO $pdo, int $idPlantel, string $fecha): int
{
    $st = $pdo->prepare(
        'SELECT COUNT(DISTINCT ag.id_alumno) FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         WHERE g.id_plantel = ? AND ag.activo = 1 AND a.estado = \'activo\'
           AND ag.fecha_inicio <= ? AND (ag.fecha_baja IS NULL OR ag.fecha_baja > ?)'
    );
    $st->execute([$idPlantel, $fecha, $fecha]);
    return (int) $st->fetchColumn();
}

function reporte_semanal_contar_mov_plantel(PDO $pdo, int $idPlantel, int $anio, int $semanaDesde, int $semanaHasta): array
{
    $st = $pdo->prepare(
        'SELECT tipo, COUNT(DISTINCT id_alumno) AS n FROM reporte_semanal_movimiento
         WHERE id_plantel = ? AND anio = ? AND semana BETWEEN ? AND ?
         GROUP BY tipo'
    );
    $st->execute([$idPlantel, $anio, $semanaDesde, $semanaHasta]);
    $map = ['I' => 0, 'R' => 0, 'C_POS' => 0, 'C_NEG' => 0, 'B' => 0, 'FC' => 0];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $map[$r['tipo']] = (int) $r['n'];
    }
    return $map;
}

function reporte_semanal_metricas_grupo(
    PDO $pdo,
    int $idGrupo,
    int $anio,
    int $semana
): array {
    $rango = reporte_semanal_rango($anio, $semana);
    $prevFin = date('Y-m-d', strtotime('-1 day', strtotime($rango['inicio'])));
    $A = reporte_semanal_conteo_activos($pdo, $idGrupo, $prevFin);
    $mov = reporte_semanal_contar_movimientos($pdo, $idGrupo, $anio, $semana);
    $I = $mov['I'];
    $R = $mov['R'];
    $cPos = $mov['C_POS'];
    $B = $mov['B'];
    $cNeg = $mov['C_NEG'];
    $FC = $mov['FC'];
    $T = max(0, $A + $I + $R - $B - $FC);
    $hor = reporte_semanal_grupo_horario($pdo, $idGrupo);
    return compact('A', 'I', 'R', 'cPos', 'B', 'cNeg', 'FC', 'T') + [
        'C_POS' => $cPos,
        'C_NEG' => $cNeg,
        'dias' => $hor['dias'],
        'horario' => $hor['horario'],
        'inicio' => $rango['inicio'],
        'fin' => $rango['fin'],
    ];
}

/**
 * @return array{
 *   semanas:list<array>,
 *   resumen_especialidades:list<array>,
 *   por_especialidad:list<array>,
 *   totales:array,
 *   meta:array
 * }
 */
function reporte_semanal_generar(
    PDO $pdo,
    int $idPlantel,
    int $anio,
    int $semanaDesde,
    int $semanaHasta,
    string $modo = 'semana'
): array {
    reporte_semanal_ensure_schema($pdo);

    if ($modo === 'mes') {
        $actual = reporte_semanal_desde_fecha(date('Y-m-d'));
        $mes = (int) date('n', strtotime($actual['inicio']));
        if ($anio <= 0) {
            $anio = $actual['anio'];
        }
        $semanas = reporte_semanal_listar_semanas($anio, $mes);
        if ($semanas === []) {
            $semanaDesde = 1;
            $semanaHasta = 4;
        } else {
            $semanaDesde = $semanas[0]['semana'];
            $semanaHasta = $semanas[count($semanas) - 1]['semana'];
        }
    } elseif ($modo === 'anio') {
        $semanaDesde = 1;
        $semanaHasta = 52;
    } else {
        $semanaDesde = max(1, min(52, $semanaDesde));
        $semanaHasta = max($semanaDesde, min(52, $semanaHasta));
    }

    foreach (reporte_semanal_rango_semanas($anio, $semanaDesde, $semanaHasta) as $sw) {
        reporte_semanal_sincronizar($pdo, $idPlantel, $sw['anio'], $sw['semana']);
    }

    $stG = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.id_especialidad,
                e.nombre AS esp_nombre, e.clave AS esp_clave,
                CONCAT(u.nombre, \' \', u.apellido) AS profesor
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
         WHERE g.id_plantel = ?
         ORDER BY e.nombre, g.clave'
    );
    $stG->execute([$idPlantel]);
    $grupos = $stG->fetchAll(PDO::FETCH_ASSOC);

    $semanasOut = [];
    $asistPorEsp = [];
    $porEsp = [];

    foreach (reporte_semanal_rango_semanas($anio, $semanaDesde, $semanaHasta) as $sw) {
        $semData = [
            'anio' => $sw['anio'],
            'semana' => $sw['semana'],
            'inicio' => $sw['inicio'],
            'fin' => $sw['fin'],
            'etiqueta' => 'Semana ' . $sw['semana'] . ' (' . date('d/m', strtotime($sw['inicio'])) . '–' . date('d/m', strtotime($sw['fin'])) . ')',
            'especialidades' => [],
            'totales' => [
                'A' => 0, 'I' => 0, 'R' => 0, 'C_POS' => 0, 'B' => 0, 'C_NEG' => 0, 'FC' => 0, 'T' => 0,
                'asistencia_unicos' => 0,
            ],
        ];

        $alumnosAsistSem = [];

        foreach ($grupos as $g) {
            $idGrupo = (int) $g['id_grupo'];
            $idEsp = (int) ($g['id_especialidad'] ?? 0);
            $m = reporte_semanal_metricas_grupo($pdo, $idGrupo, $sw['anio'], $sw['semana']);

            $stAs = $pdo->prepare(
                'SELECT DISTINCT a.id_alumno FROM asistencias asi
                 INNER JOIN alumnos a ON a.id_alumno = asi.id_alumno
                 WHERE asi.id_grupo = ? AND asi.fecha BETWEEN ? AND ? AND asi.presente = 1'
            );
            $stAs->execute([$idGrupo, $sw['inicio'], $sw['fin']]);
            foreach ($stAs->fetchAll(PDO::FETCH_COLUMN) as $idAl) {
                $alumnosAsistSem[$idEsp . '-' . (int) $idAl] = true;
            }

            if (!isset($porEsp[$idEsp])) {
                $porEsp[$idEsp] = [
                    'id_especialidad' => $idEsp,
                    'nombre' => $g['esp_nombre'] ?? 'Sin especialidad',
                    'clave' => $g['esp_clave'] ?? '',
                    'grupos' => [],
                ];
            }

            $fila = [
                'id_grupo' => $idGrupo,
                'clave' => $g['clave'],
                'profesor' => trim($g['profesor'] ?? '') ?: '—',
                'dias' => $m['dias'],
                'horario' => $m['horario'],
                'A' => $m['A'],
                'I' => $m['I'],
                'R' => $m['R'],
                'C_POS' => $m['C_POS'],
                'B' => $m['B'],
                'C_NEG' => $m['C_NEG'],
                'FC' => $m['FC'],
                'T' => $m['T'],
            ];

            if (!isset($semData['especialidades'][$idEsp])) {
                $semData['especialidades'][$idEsp] = [
                    'id_especialidad' => $idEsp,
                    'nombre' => $g['esp_nombre'] ?? 'Sin especialidad',
                    'asistencia_unicos' => 0,
                    'grupos' => [],
                ];
            }
            $semData['especialidades'][$idEsp]['grupos'][] = $fila;

            if ($sw['semana'] === $semanaHasta || $modo === 'semana') {
                $porEsp[$idEsp]['grupos'][] = $fila + ['semana' => $sw['semana']];
            }

            $semData['totales']['A'] += $m['A'];
            $semData['totales']['I'] += $m['I'];
            $semData['totales']['R'] += $m['R'];
            $semData['totales']['C_POS'] += $m['C_POS'];
            $semData['totales']['B'] += $m['B'];
            $semData['totales']['C_NEG'] += $m['C_NEG'];
            $semData['totales']['FC'] += $m['FC'];
            $semData['totales']['T'] += $m['T'];
        }

        foreach ($semData['especialidades'] as $idEsp => &$espRow) {
            $cnt = 0;
            foreach ($alumnosAsistSem as $k => $_) {
                if (strpos($k, $idEsp . '-') === 0) {
                    $cnt++;
                }
            }
            $espRow['asistencia_unicos'] = $cnt;
            $asistPorEsp[$idEsp] = ($asistPorEsp[$idEsp] ?? 0) + $cnt;
        }
        unset($espRow);

        $semData['totales']['asistencia_unicos'] = count($alumnosAsistSem);
        $semData['especialidades'] = array_values($semData['especialidades']);

        $semData['totales']['desercion'] = $semData['totales']['T'] - $semData['totales']['A'];
        $semanasOut[] = $semData;
    }

    $resumenEsp = [];
    foreach ($asistPorEsp as $idEsp => $cnt) {
        $nom = 'Sin especialidad';
        foreach ($grupos as $g) {
            if ((int) ($g['id_especialidad'] ?? 0) === (int) $idEsp) {
                $nom = $g['esp_nombre'] ?? $nom;
                break;
            }
        }
        $resumenEsp[] = [
            'id_especialidad' => (int) $idEsp,
            'nombre' => $nom,
            'asistencia_unicos' => $cnt,
        ];
    }
    usort($resumenEsp, static fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

    $rangoIni = reporte_semanal_rango($anio, $semanaDesde);
    $rangoFin = reporte_semanal_rango($anio, $semanaHasta);
    $prevFin = date('Y-m-d', strtotime('-1 day', strtotime($rangoIni['inicio'])));
    $totIni = reporte_semanal_conteo_plantel_activos($pdo, $idPlantel, $prevFin);
    $totFin = reporte_semanal_conteo_plantel_activos($pdo, $idPlantel, $rangoFin['fin']);
    $movPlantel = reporte_semanal_contar_mov_plantel($pdo, $idPlantel, $anio, $semanaDesde, $semanaHasta);

    $desercion = $totFin - $totIni;

    return [
        'semanas' => $semanasOut,
        'resumen_especialidades' => $resumenEsp,
        'por_especialidad' => array_values($porEsp),
        'totales' => [
            'A_inicio' => $totIni,
            'I' => $movPlantel['I'],
            'R' => $movPlantel['R'],
            'C_POS' => $movPlantel['C_POS'],
            'B' => $movPlantel['B'],
            'C_NEG' => $movPlantel['C_NEG'],
            'FC' => $movPlantel['FC'],
            'T_fin' => $totFin,
            'desercion' => $desercion,
            'desercion_label' => $desercion >= 0
                ? '+' . $desercion . ' (crecimiento)'
                : (string) $desercion,
        ],
        'meta' => [
            'anio' => $anio,
            'semana_desde' => $semanaDesde,
            'semana_hasta' => $semanaHasta,
            'modo' => $modo,
        ],
    ];
}

function reporte_semanal_log_cambio_grupo(
    PDO $pdo,
    int $idPlantel,
    int $idAlumno,
    int $idGrupoNuevo,
    array $gruposAnteriores
): void {
    if (!function_exists('reporte_semanal_registrar_movimiento')) {
        return;
    }
    $fecha = date('Y-m-d');
    foreach ($gruposAnteriores as $idGrupoAnt) {
        $idGrupoAnt = (int) $idGrupoAnt;
        if ($idGrupoAnt === $idGrupoNuevo) {
            continue;
        }
        reporte_semanal_registrar_movimiento($pdo, $idPlantel, $idAlumno, $idGrupoAnt, 'C_NEG', $fecha, $idGrupoNuevo);
    }
    if ($gruposAnteriores !== []) {
        reporte_semanal_registrar_movimiento($pdo, $idPlantel, $idAlumno, $idGrupoNuevo, 'C_POS', $fecha, (int) $gruposAnteriores[0]);
    }
}
