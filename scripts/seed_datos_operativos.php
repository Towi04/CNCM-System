<?php
/**
 * Datos operativos de prueba: pagos (6 meses), asistencias, calificaciones,
 * preregistros, evaluación 360, asistencia de profesores.
 * Requiere haber ejecutado antes: scripts/seed_datos_prueba.php
 *
 * CLI: php scripts/seed_datos_operativos.php
 */
declare(strict_types=1);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (!defined('HAY_SKIP_SCHEMA_BOOTSTRAP')) {
        define('HAY_SKIP_SCHEMA_BOOTSTRAP', false);
    }
    require __DIR__ . '/../config.php';
}

define('SEED_RUNNING', true);
const SEED_OP_TAG = 'seed_operativo_2025';
const SEED_BASE_TAG = 'seed_prueba_2025';

function seed_op_log(string $msg): void
{
    echo $msg . (PHP_SAPI === 'cli' ? "\n" : "<br>\n");
}

/** @return list<array<string, mixed>> */
function seed_op_grupos_demo(PDO $pdo): array
{
    $st = $pdo->prepare(
        "SELECT g.*, u.id_usuario AS id_profesor_user
         FROM grupos g
         LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
         WHERE g.aula LIKE ?
         ORDER BY g.id_plantel, g.id_grupo"
    );
    $st->execute(['%' . SEED_BASE_TAG . '%']);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string, mixed>> */
function seed_op_alumnos_grupo(PDO $pdo, int $idGrupo): array
{
    $st = $pdo->prepare(
        "SELECT a.*, ag.id_grupo
         FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         WHERE ag.id_grupo = ? AND ag.activo = 1
         ORDER BY a.id_alumno"
    );
    $st->execute([$idGrupo]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function seed_op_ensure_horario(PDO $pdo, int $idGrupo): void
{
    $chk = $pdo->prepare('SELECT COUNT(*) FROM grupo_horarios WHERE id_grupo = ?');
    $chk->execute([$idGrupo]);
    if ((int) $chk->fetchColumn() > 0) {
        return;
    }
    $pdo->prepare(
        'INSERT INTO grupo_horarios (id_grupo, dia_semana, hora_inicio, hora_fin, activo)
         VALUES (?, 6, \'09:00:00\', \'13:00:00\', 1)'
    )->execute([$idGrupo]);
}

function seed_op_pagos_alumno(
    PDO $pdo,
    array $alumno,
    int $idUsuarioCaja,
    int $mesesAtras = 6
): int {
    $idAlumno = (int) $alumno['id_alumno'];
    $idPlantel = (int) $alumno['id_plantel'];
    $idEsp = (int) ($alumno['id_especialidad'] ?? 0);

    $chk = $pdo->prepare(
        "SELECT COUNT(*) FROM alumno_pagos WHERE id_alumno = ? AND concepto LIKE ?"
    );
    $chk->execute([$idAlumno, '%' . SEED_OP_TAG . '%']);
    if ((int) $chk->fetchColumn() > 0) {
        return 0;
    }

    $stAe = $pdo->prepare(
        'SELECT id_alumno_especialidad, costo_mensualidad, costo_pronto_pago, costo_inscripcion
         FROM alumno_especialidades WHERE id_alumno = ? AND id_especialidad = ? LIMIT 1'
    );
    $stAe->execute([$idAlumno, $idEsp]);
    $ae = $stAe->fetch(PDO::FETCH_ASSOC);
    if (!$ae) {
        if ($idEsp > 0 && function_exists('pago_crear_inscripcion')) {
            pago_crear_inscripcion($pdo, $idAlumno, $idEsp, 'mensual', date('Y-m-d', strtotime('-6 months')));
            $stAe->execute([$idAlumno, $idEsp]);
            $ae = $stAe->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!$ae) {
        return 0;
    }

    $idAe = (int) $ae['id_alumno_especialidad'];
    try {
        $pdo->prepare('UPDATE alumno_especialidades SET fecha_inscripcion = ? WHERE id_alumno_especialidad = ?')
            ->execute([date('Y-m-d', strtotime('-6 months')), $idAe]);
    } catch (PDOException $e) {
        // opcional
    }
    $mensual = (float) ($ae['costo_mensualidad'] ?? 1200);
    $pronto = (float) ($ae['costo_pronto_pago'] ?? $mensual * 0.9);
    $inscripcion = (float) ($ae['costo_inscripcion'] ?? 500);

    $insertados = 0;
    $hoy = new DateTimeImmutable('today');

    $insStmt = $pdo->prepare(
        'INSERT INTO alumno_pagos (
            id_alumno, id_plantel, id_especialidad, tipo, id_alumno_especialidad,
            folio, monto, forma_pago, concepto, periodo_ref, id_usuario, creado_en
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    );

    $insInsc = $pdo->prepare(
        "SELECT 1 FROM alumno_pagos WHERE id_alumno = ? AND tipo = 'inscripcion' LIMIT 1"
    );
    $insInsc->execute([$idAlumno]);
    if (!$insInsc->fetchColumn() && $inscripcion > 0) {
        $fecha = $hoy->modify('-5 months')->format('Y-m-d') . ' 10:00:00';
        $insStmt->execute([
            $idAlumno, $idPlantel, $idEsp ?: null, 'inscripcion', $idAe,
            'SEED-INSC-' . $idAlumno, $inscripcion, 'Efectivo',
            'Inscripción ' . SEED_OP_TAG, 'INSCRIPCION', $idUsuarioCaja, $fecha,
        ]);
        $insertados++;
        $pdo->prepare('UPDATE alumno_especialidades SET inscripcion_cubierta = 1 WHERE id_alumno_especialidad = ?')
            ->execute([$idAe]);
    }

    for ($i = $mesesAtras; $i >= 1; $i--) {
        $mes = $hoy->modify("first day of -{$i} months");
        $periodo = $mes->format('Y-m');
        $diaPago = random_int(1, 8);
        $fechaPago = $mes->format('Y-m-') . str_pad((string) $diaPago, 2, '0', STR_PAD_LEFT) . ' 11:30:00';
        $monto = $diaPago <= 6 ? $pronto : $mensual;

        if ($i === 1 && ($idAlumno % 5) === 0) {
            continue;
        }
        if ($i === 2 && ($idAlumno % 7) === 0) {
            $insStmt->execute([
                $idAlumno, $idPlantel, $idEsp ?: null, 'abono', $idAe,
                'SEED-ABO-' . $idAlumno . '-' . $periodo, round($monto * 0.4, 2), 'Efectivo',
                'Abono parcial ' . SEED_OP_TAG, $periodo, $idUsuarioCaja, $fechaPago,
            ]);
            $insertados++;
            continue;
        }

        $insStmt->execute([
            $idAlumno, $idPlantel, $idEsp ?: null, 'mensualidad', $idAe,
            'SEED-MEN-' . $idAlumno . '-' . $periodo, $monto, random_int(0, 1) ? 'Efectivo' : 'Tarjeta',
            'Colegiatura ' . SEED_OP_TAG, $periodo, $idUsuarioCaja, $fechaPago,
        ]);
        $insertados++;
    }

    return $insertados;
}

function seed_op_asistencias_grupo(PDO $pdo, int $idGrupo, array $alumnos): int
{
    $chk = $pdo->prepare(
        "SELECT COUNT(*) FROM asistencias WHERE id_grupo = ? AND origen = 'recepcion'
         AND fecha >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)"
    );
    $chk->execute([$idGrupo]);
    if ((int) $chk->fetchColumn() > 20) {
        return 0;
    }

    $insertados = 0;
    $hoy = new DateTimeImmutable('today');
    for ($w = 24; $w >= 1; $w--) {
        $fecha = $hoy->modify("-{$w} weeks")->modify('saturday');
        if ((int) $fecha->format('N') !== 6) {
            $fecha = $fecha->modify('last saturday');
        }
        $fechaStr = $fecha->format('Y-m-d');
        if ($fecha > $hoy) {
            continue;
        }
        [$anio, $semana] = asistencia_calc_semana($fechaStr);

        foreach ($alumnos as $al) {
            $idAlumno = (int) $al['id_alumno'];
            $presente = random_int(1, 100) <= 88 ? 1 : 0;
            $hora = $presente ? sprintf('%02d:%02d:00', random_int(8, 10), random_int(0, 59)) : null;
            try {
                $pdo->prepare(
                    'INSERT INTO asistencias (id_grupo, id_alumno, fecha, anio, semana, presente, origen, hora_llegada)
                     VALUES (?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE presente = GREATEST(presente, VALUES(presente)),
                       hora_llegada = COALESCE(hora_llegada, VALUES(hora_llegada))'
                )->execute([
                    $idGrupo, $idAlumno, $fechaStr, $anio, $semana, $presente,
                    $presente ? 'huella' : 'recepcion', $hora,
                ]);
                $insertados++;
            } catch (PDOException $e) {
                // duplicado u otro
            }
        }
    }

    return $insertados;
}

function seed_op_calificaciones_grupo(
    PDO $pdo,
    array $grupo,
    array $alumnos,
    int $idUsuarioProf
): int {
    $idGrupo = (int) $grupo['id_grupo'];
    $idFase = (int) ($grupo['id_fase_actual'] ?? 0);
    if ($idFase <= 0) {
        return 0;
    }

    $chk = $pdo->prepare(
        'SELECT COUNT(*) FROM alumno_calificacion_parcial WHERE id_grupo = ? AND id_fase = ?'
    );
    $chk->execute([$idGrupo, $idFase]);
    if ((int) $chk->fetchColumn() >= count($alumnos) - 1) {
        return 0;
    }

    $rubrica = function_exists('academico_rubrica_default') ? academico_rubrica_default() : [];
    if ($rubrica !== [] && function_exists('calificaciones_guardar_rubrica')) {
        calificaciones_guardar_rubrica($pdo, $idGrupo, $idFase, $rubrica, $idUsuarioProf);
    }

    $guardados = 0;
    $criterios = function_exists('calificaciones_obtener_rubrica')
        ? calificaciones_obtener_rubrica($pdo, $idGrupo, $idFase)
        : $rubrica;

    foreach ($alumnos as $idx => $al) {
        $idAlumno = (int) $al['id_alumno'];
        $notas = [];
        foreach ($criterios as $c) {
            $base = ($idx % 4 === 0) ? random_int(5, 7) : random_int(6, 10);
            $notas[$c['codigo']] = $base;
        }
        if (!function_exists('calificaciones_guardar_alumno')) {
            continue;
        }
        $res = calificaciones_guardar_alumno(
            $pdo,
            $idAlumno,
            $idFase,
            $idGrupo,
            $notas,
            $criterios,
            $idUsuarioProf,
            SEED_OP_TAG
        );
        if ($res['ok'] ?? false) {
            $guardados++;
            if (!($res['aprobado'] ?? true) && ($idx % 6) === 0) {
                $pdo->prepare(
                    'UPDATE alumno_grupos SET en_riesgo_academico = 1 WHERE id_alumno = ? AND id_grupo = ?'
                )->execute([$idAlumno, $idGrupo]);
            }
        }
    }

    return $guardados;
}

function seed_op_asistencia_profesores(PDO $pdo, int $idPlantel, array $profIds): int
{
    $n = 0;
    foreach ($profIds as $idProf) {
        if ($idProf <= 0) {
            continue;
        }
        $chk = $pdo->prepare(
            'SELECT COUNT(*) FROM asistencia_personal WHERE id_usuario = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
        );
        $chk->execute([$idProf]);
        if ((int) $chk->fetchColumn() > 10) {
            continue;
        }
        for ($d = 30; $d >= 1; $d--) {
            $fecha = date('Y-m-d', strtotime("-{$d} days"));
            if ((int) date('N', strtotime($fecha)) === 7) {
                continue;
            }
            $hora = sprintf('%02d:%02d:00', random_int(7, 8), random_int(0, 45));
            try {
                $pdo->prepare(
                    'INSERT INTO asistencia_personal (id_usuario, id_plantel, fecha, hora_llegada, origen)
                     VALUES (?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE hora_llegada = LEAST(hora_llegada, VALUES(hora_llegada))'
                )->execute([$idProf, $idPlantel, $fecha, $hora, 'huella']);
                $n++;
            } catch (PDOException $e) {
                // ignore
            }
        }
    }

    return $n;
}

function seed_op_preregistros(PDO $pdo, int $idPlantel, int $idAsesor, int $idEsp): int
{
    $chk = $pdo->prepare(
        "SELECT COUNT(*) FROM preregistros WHERE id_plantel = ? AND observaciones LIKE ?"
    );
    $chk->execute([$idPlantel, '%' . SEED_OP_TAG . '%']);
    if ((int) $chk->fetchColumn() > 0) {
        return 0;
    }

    $nombres = [
        ['Ana', 'Prueba', 'Uno'],
        ['Luis', 'Prueba', 'Dos'],
        ['María', 'Prueba', 'Tres'],
    ];
    $creados = 0;
    foreach ($nombres as $i => $parts) {
        $pdo->prepare(
            'INSERT INTO preregistros (
                id_plantel, id_usuario_registro, id_especialidad, nombres, apellido_paterno, apellido_materno,
                telefono, email, medio_entero, estado, tiene_apartado, monto_apartado, observaciones
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $idPlantel,
            $idAsesor,
            $idEsp,
            $parts[0],
            $parts[1],
            $parts[2],
            '477' . random_int(1000000, 9999999),
            strtolower($parts[0]) . '.prueba' . $i . '@ejemplo.com',
            'redes_sociales',
            $i === 0 ? 'activo' : 'pendiente',
            $i === 0 ? 1 : 0,
            $i === 0 ? 500.0 : null,
            'Preregistro demo ' . SEED_OP_TAG,
        ]);
        $creados++;
    }

    return $creados;
}

function seed_op_eval_profesor(PDO $pdo, int $idProf, int $idPlantel, int $idEvaluador): int
{
    $anio = (int) date('Y');
    $mes = (int) date('n');
    if ($mes === 1) {
        $mes = 12;
        $anio--;
    } else {
        $mes--;
    }

    if (profesor_eval_obtener($pdo, $idProf, $idPlantel, $anio, $mes)) {
        return 0;
    }

    $calc = profesor_eval_calcular_metricas_auto($pdo, $idProf, $idPlantel, $anio, $mes);
    $puntosAuto = [];
    foreach (profesor_eval_criterios_auto() as $c) {
        $cod = $c['codigo'];
        $puntosAuto[$cod] = (int) ($calc['metricas'][$cod]['puntos_sugeridos'] ?? 0);
    }
    $puntosManual = [];
    foreach (profesor_eval_criterios_manual() as $c) {
        $puntosManual[$c['codigo']] = (int) round($c['maximo'] * (random_int(65, 92) / 100));
    }

    $res = profesor_eval_guardar(
        $pdo,
        $idProf,
        $idPlantel,
        $anio,
        $mes,
        $puntosAuto,
        $puntosManual,
        $calc['metricas'],
        'Evaluación demo ' . SEED_OP_TAG,
        true,
        $idEvaluador
    );

    return ($res['ok'] ?? false) ? 1 : 0;
}

// ——— Main ———
seed_op_log('=== Seed operativo (pagos, asistencia, calificaciones) ===');

try {
    hay_bootstrap_schema($pdo);
} catch (Throwable $e) {
    seed_op_log('Aviso bootstrap: ' . $e->getMessage());
}

$grupos = seed_op_grupos_demo($pdo);
if ($grupos === []) {
    seed_op_log('No hay grupos demo. Ejecute primero: php scripts/seed_datos_prueba.php');
    if (PHP_SAPI === 'cli') {
        exit(1);
    }
    return;
}

$idIng = (int) $pdo->query("SELECT id_especialidad FROM especialidades WHERE clave = 'ING' LIMIT 1")->fetchColumn();
$totPagos = 0;
$totAsist = 0;
$totCal = 0;
$totPrereg = 0;
$totEval = 0;

foreach ($grupos as $grupo) {
    $idPlantel = (int) $grupo['id_plantel'];
    $idGrupo = (int) $grupo['id_grupo'];
    $idProf = (int) ($grupo['id_profesor'] ?? 0);

    $_SESSION['plantel_id'] = $idPlantel;
    $_SESSION['user_id'] = $idProf > 0 ? $idProf : 1;

    $slug = $pdo->prepare('SELECT slug FROM planteles WHERE id_plantel = ?');
    $slug->execute([$idPlantel]);
    $slugName = (string) $slug->fetchColumn();

    seed_op_log('');
    seed_op_log("[{$slugName}] Grupo {$grupo['clave']}");

    seed_op_ensure_horario($pdo, $idGrupo);
    $alumnos = seed_op_alumnos_grupo($pdo, $idGrupo);
    if ($alumnos === []) {
        seed_op_log('  Sin alumnos en el grupo');
        continue;
    }

    $stAdmin = $pdo->prepare(
        "SELECT id_usuario FROM usuarios WHERE id_plantel = ? AND rol IN ('admin','gerente') AND suspendido = 0 LIMIT 1"
    );
    $stAdmin->execute([$idPlantel]);
    $idCaja = (int) ($stAdmin->fetchColumn() ?: $idProf);

    $gPagos = 0;
    foreach ($alumnos as $al) {
        $gPagos += seed_op_pagos_alumno($pdo, $al, $idCaja, 6);
    }
    $totPagos += $gPagos;
    seed_op_log("  Pagos (6 meses): {$gPagos} movimientos");

    $nAs = seed_op_asistencias_grupo($pdo, $idGrupo, $alumnos);
    $totAsist += $nAs;
    seed_op_log("  Asistencias (sábados ~6 meses): {$nAs} registros");

    $nCal = seed_op_calificaciones_grupo($pdo, $grupo, $alumnos, $idProf);
    $totCal += $nCal;
    seed_op_log("  Calificaciones parcial actual: {$nCal} alumnos");

    $totEval += seed_op_eval_profesor($pdo, $idProf, $idPlantel, $idCaja);

    $profesDelPlantel = $pdo->prepare(
        "SELECT id_usuario FROM usuarios WHERE id_plantel = ? AND rol = 'profesor' AND username LIKE 'demo.%' LIMIT 5"
    );
    $profesDelPlantel->execute([$idPlantel]);
    $profIds = array_map('intval', $profesDelPlantel->fetchAll(PDO::FETCH_COLUMN));
    seed_op_asistencia_profesores($pdo, $idPlantel, $profIds);
}

$planteles = $pdo->query('SELECT id_plantel, slug FROM planteles WHERE activo = 1')->fetchAll(PDO::FETCH_ASSOC);
foreach ($planteles as $pl) {
    $idP = (int) $pl['id_plantel'];
    $stAs = $pdo->prepare(
        "SELECT id_usuario FROM usuarios WHERE id_plantel = ? AND rol = 'asesor' AND suspendido = 0 LIMIT 1"
    );
    $stAs->execute([$idP]);
    $idAsesor = (int) ($stAs->fetchColumn() ?: 0);
    if ($idAsesor > 0 && $idIng > 0) {
        $totPrereg += seed_op_preregistros($pdo, $idP, $idAsesor, $idIng);
    }
}

seed_op_log('');
seed_op_log('=== Resumen ===');
seed_op_log("Grupos demo: " . count($grupos));
seed_op_log("Preregistros demo creados (por plantel sin duplicar): {$totPrereg}");
seed_op_log("Evaluaciones 360 cerradas (mes anterior): {$totEval}");
seed_op_log('');
seed_op_log('Pruebe: Punto de venta, Asistencias, Calificaciones en Grupos, Evaluación 360, Pre-registro.');
seed_op_log('=== Listo ===');
