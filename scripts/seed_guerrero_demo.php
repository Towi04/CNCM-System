<?php
/**
 * Demo completo solo para plantel Guerrero.
 * Incluye: aulas, profesores, grupos históricos (varias especialidades),
 * alumnos, pagos, asistencias, calificaciones por fase, preregistros,
 * entrevistas, cortes de caja, reporte semanal y rol de aulas.
 *
 * CLI:  php scripts/seed_guerrero_demo.php
 * Web:  php/seed_guerrero_demo_run.php?confirm=1
 *
 * Contraseña de usuarios demo: 1234
 * Etiqueta idempotente: seed_guerrero_demo_2026
 */
declare(strict_types=1);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (!defined('HAY_SKIP_SCHEMA_BOOTSTRAP')) {
        define('HAY_SKIP_SCHEMA_BOOTSTRAP', false);
    }
    require __DIR__ . '/../config.php';
}

if (!function_exists('auth_ensure_email_column')) {
    require_once __DIR__ . '/../php/auth_helpers.php';
}

define('SEED_RUNNING', true);
const SEED_G_PASSWORD = '1234';
const SEED_G_TAG = 'seed_guerrero_demo_2026';

$passHash = password_hash(SEED_G_PASSWORD, PASSWORD_BCRYPT);

function seed_g_log(string $msg): void
{
    echo $msg . (PHP_SAPI === 'cli' ? "\n" : "<br>\n");
}

function seed_g_fail(string $msg): void
{
    seed_g_log('ERROR: ' . $msg);
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException($msg);
    }
    exit(1);
}

function seed_g_usuario(
    PDO $pdo,
    string $username,
    string $nombre,
    string $apellido,
    string $rol,
    int $idPlantel,
    string $departamento,
    string $passHash
): int {
    auth_ensure_email_column($pdo);
    $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $existing = $st->fetchColumn();
    if ($existing) {
        seed_g_log("  · Usuario ya existe: {$username}");
        return (int) $existing;
    }

    $email = $username . '@' . (defined('INSTITUTIONAL_EMAIL_DOMAIN') ? INSTITUTIONAL_EMAIL_DOMAIN : 'cncm.edu.mx');
    $pdo->prepare(
        'INSERT INTO usuarios (nombre, apellido, username, email, password, rol, departamento, id_plantel, fecha_creacion)
         VALUES (?,?,?,?,?,?,?,?,NOW())'
    )->execute([$nombre, $apellido, $username, $email, $passHash, $rol, $departamento, $idPlantel]);

    $id = (int) $pdo->lastInsertId();
    seed_g_log("  + Usuario {$username} ({$rol})");
    return $id;
}

function seed_g_especialidad_id(PDO $pdo, string ...$claves): int
{
    foreach ($claves as $clave) {
        $st = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE clave = ? AND activo = 1 LIMIT 1');
        $st->execute([$clave]);
        $id = (int) $st->fetchColumn();
        if ($id > 0) {
            return $id;
        }
    }
    return 0;
}

/** @return list<array<string, mixed>> */
function seed_g_fases(PDO $pdo, int $idEsp): array
{
    if ($idEsp <= 0) {
        return [];
    }
    if (function_exists('fase_listar')) {
        return fase_listar($pdo, $idEsp);
    }
    $st = $pdo->prepare(
        'SELECT * FROM especialidad_fases WHERE id_especialidad = ? AND activo = 1 ORDER BY orden ASC, id_fase ASC'
    );
    $st->execute([$idEsp]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function seed_g_aula(PDO $pdo, int $idPlantel, array $data): int
{
    if (!function_exists('aula_guardar')) {
        return 0;
    }
    $st = $pdo->prepare('SELECT id_aula FROM plantel_aulas WHERE id_plantel = ? AND codigo = ? LIMIT 1');
    $st->execute([$idPlantel, $data['codigo']]);
    $id = (int) ($st->fetchColumn() ?: 0);
    $res = aula_guardar($pdo, $idPlantel, array_merge(['activo' => 1, 'todas_especialidades' => 1], $data), $id ?: null);
    if (!($res['ok'] ?? false)) {
        seed_g_log('  (aviso aula ' . $data['codigo'] . ': ' . ($res['message'] ?? 'error') . ')');
        return $id;
    }
    $idAula = (int) ($res['id_aula'] ?? $id);
    if ($id <= 0) {
        seed_g_log('  + Aula ' . $data['codigo']);
    }
    return $idAula;
}

function seed_g_clave(PDO $pdo, int $idPlantel, string $area, string $horario): string
{
    if (function_exists('grupo_clave_generar')) {
        try {
            $gen = grupo_clave_generar($pdo, $idPlantel, $area, $horario, false, false);
            return (string) $gen['clave'];
        } catch (Throwable $e) {
            // fallback abajo
        }
    }
    $pref = $area . $horario;
    $n = (int) $pdo->query(
        'SELECT COUNT(*) FROM grupos WHERE id_plantel = ' . (int) $idPlantel . ' AND clave LIKE ' . $pdo->quote($pref . '%')
    )->fetchColumn();
    return $pref . str_pad((string) (100 + $n + random_int(1, 40)), 3, '0', STR_PAD_LEFT);
}

/**
 * @param list<int> $diasSemana PHP date('w'): 0=Dom … 6=Sáb
 * @return array{id_grupo:int,clave:string}|null
 */
function seed_g_crear_grupo(
    PDO $pdo,
    int $idPlantel,
    int $idProfesor,
    int $idEsp,
    ?int $idFase,
    string $fechaInicio,
    string $area,
    string $horario,
    string $horarioTexto,
    array $diasSemana,
    string $horaInicio,
    string $horaFin,
    ?int $idAula,
    string $codigoAula
): ?array {
    $clave = seed_g_clave($pdo, $idPlantel, $area, $horario);
    $aulaLabel = $codigoAula . ' ' . SEED_G_TAG;

    try {
        $pdo->prepare(
            'INSERT INTO grupos (
                id_plantel, clave, fecha_inicio, id_profesor, id_especialidad, id_fase_actual,
                codigo_area, codigo_horario, es_extensivo, es_personalizado, aula, id_aula, horario_texto
             ) VALUES (?,?,?,?,?,?,?,?,0,0,?,?,?)'
        )->execute([
            $idPlantel, $clave, $fechaInicio, $idProfesor, $idEsp ?: null, $idFase,
            $area, $horario, $aulaLabel, $idAula, $horarioTexto,
        ]);
    } catch (PDOException $e) {
        $pdo->prepare(
            'INSERT INTO grupos (id_plantel, clave, fecha_inicio, id_profesor, aula)
             VALUES (?,?,?,?,?)'
        )->execute([$idPlantel, $clave, $fechaInicio, $idProfesor, $aulaLabel]);
    }

    $idGrupo = (int) $pdo->lastInsertId();
    if ($idGrupo <= 0) {
        return null;
    }

    foreach ([
        ['id_especialidad', $idEsp ?: null],
        ['id_fase_actual', $idFase],
        ['id_aula', $idAula],
        ['horario_texto', $horarioTexto],
        ['codigo_area', $area],
        ['codigo_horario', $horario],
        ['aula', $aulaLabel],
    ] as [$col, $val]) {
        if ($val === null || $val === '') {
            continue;
        }
        try {
            $pdo->prepare("UPDATE grupos SET {$col} = ? WHERE id_grupo = ?")->execute([$val, $idGrupo]);
        } catch (PDOException $e2) {
            // columna opcional
        }
    }

    try {
        $pdo->prepare("UPDATE grupos SET estado_apertura = 'iniciado' WHERE id_grupo = ?")->execute([$idGrupo]);
    } catch (PDOException $e) {
        // opcional
    }

    foreach ($diasSemana as $dia) {
        try {
            $pdo->prepare(
                'INSERT INTO grupo_horarios (id_grupo, dia_semana, hora_inicio, hora_fin, activo)
                 VALUES (?,?,?,?,1)'
            )->execute([$idGrupo, (int) $dia, $horaInicio, $horaFin]);
        } catch (PDOException $e) {
            // ignore
        }
    }

    seed_g_log("  + Grupo {$clave} (inicio {$fechaInicio})");
    return ['id_grupo' => $idGrupo, 'clave' => $clave];
}

function seed_g_crear_alumno(
    PDO $pdo,
    int $idPlantel,
    int $idGrupo,
    int $idEsp,
    string $nombres,
    string $apPaterno,
    string $apMaterno,
    string $fechaAlta,
    string $passHash
): int {
    $nc = function_exists('alumno_generar_numero_control')
        ? alumno_generar_numero_control($pdo, $idPlantel)
        : ('G' . date('y') . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT));
    $apellido = trim($apPaterno . ' ' . $apMaterno);

    $pdo->prepare(
        'INSERT INTO alumnos (
            id_plantel, id_grupo, numero_control, nombres, apellido_paterno, apellido_materno,
            nombre, apellido, estado, forma_pago, id_especialidad, fecha_alta
         ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idPlantel, $idGrupo, $nc, $nombres, $apPaterno, $apMaterno,
        $nombres, $apellido, 'activo', 'mensual', $idEsp ?: null, $fechaAlta,
    ]);
    $idAlumno = (int) $pdo->lastInsertId();

    try {
        $pdo->prepare(
            'INSERT INTO alumno_grupos (id_alumno, id_grupo, activo, fecha_inicio) VALUES (?,?,1,?)
             ON DUPLICATE KEY UPDATE activo = 1, fecha_inicio = VALUES(fecha_inicio)'
        )->execute([$idAlumno, $idGrupo, $fechaAlta]);
    } catch (PDOException $e) {
        // tabla opcional
    }

    if ($idEsp > 0 && function_exists('pago_crear_inscripcion')) {
        try {
            pago_crear_inscripcion($pdo, $idAlumno, $idEsp, 'mensual', $fechaAlta);
        } catch (Throwable $e) {
            // ignore
        }
    }

    if (function_exists('usuario_crear_cuenta_alumno')) {
        try {
            $cuenta = usuario_crear_cuenta_alumno($pdo, $idAlumno, $idPlantel);
            if (!empty($cuenta['ok']) && !empty($cuenta['id_usuario'])) {
                $pdo->prepare('UPDATE usuarios SET password = ?, debe_cambiar_password = 0 WHERE id_usuario = ?')
                    ->execute([$passHash, $cuenta['id_usuario']]);
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    seed_g_log("    · Alumno #{$nc} {$nombres} {$apPaterno}");
    return $idAlumno;
}

function seed_g_pagos_alumno(PDO $pdo, array $alumno, int $idUsuarioCaja, string $fechaAlta): int
{
    $idAlumno = (int) $alumno['id_alumno'];
    $idPlantel = (int) $alumno['id_plantel'];
    $idEsp = (int) ($alumno['id_especialidad'] ?? 0);

    $chk = $pdo->prepare('SELECT COUNT(*) FROM alumno_pagos WHERE id_alumno = ? AND concepto LIKE ?');
    $chk->execute([$idAlumno, '%' . SEED_G_TAG . '%']);
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
        return 0;
    }

    $idAe = (int) $ae['id_alumno_especialidad'];
    try {
        $pdo->prepare('UPDATE alumno_especialidades SET fecha_inscripcion = ? WHERE id_alumno_especialidad = ?')
            ->execute([$fechaAlta, $idAe]);
    } catch (PDOException $e) {
        // opcional
    }

    $mensual = (float) ($ae['costo_mensualidad'] ?? 1200);
    $pronto = (float) ($ae['costo_pronto_pago'] ?? $mensual * 0.9);
    $inscripcion = (float) ($ae['costo_inscripcion'] ?? 500);
    $insertados = 0;

    $hasFechaPago = false;
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM alumno_pagos LIKE \'fecha_pago\'')->fetch();
        $hasFechaPago = (bool) $cols;
    } catch (PDOException $e) {
        $hasFechaPago = false;
    }

    $colsBase = 'id_alumno, id_plantel, id_especialidad, tipo, id_alumno_especialidad,
            folio, monto, forma_pago, concepto, periodo_ref, id_usuario, creado_en';
    $valsBase = '?,?,?,?,?,?,?,?,?,?,?,?';
    if ($hasFechaPago) {
        $colsBase .= ', fecha_pago';
        $valsBase .= ',?';
    }
    $insStmt = $pdo->prepare("INSERT INTO alumno_pagos ({$colsBase}) VALUES ({$valsBase})");

    $inicio = new DateTimeImmutable($fechaAlta);
    $hoy = new DateTimeImmutable('today');

    $fechaInsc = $inicio->format('Y-m-d') . ' 10:00:00';
    $params = [
        $idAlumno, $idPlantel, $idEsp ?: null, 'inscripcion', $idAe,
        'GDEMO-INSC-' . $idAlumno, $inscripcion, 'Efectivo',
        'Inscripción ' . SEED_G_TAG, 'INSCRIPCION', $idUsuarioCaja, $fechaInsc,
    ];
    if ($hasFechaPago) {
        $params[] = $inicio->format('Y-m-d');
    }
    $insInsc = $pdo->prepare("SELECT 1 FROM alumno_pagos WHERE id_alumno = ? AND tipo = 'inscripcion' LIMIT 1");
    $insInsc->execute([$idAlumno]);
    if (!$insInsc->fetchColumn() && $inscripcion > 0) {
        $insStmt->execute($params);
        $insertados++;
        try {
            $pdo->prepare('UPDATE alumno_especialidades SET inscripcion_cubierta = 1 WHERE id_alumno_especialidad = ?')
                ->execute([$idAe]);
        } catch (PDOException $e) {
            // opcional
        }
    }

    $cursor = $inicio->modify('first day of this month');
    while ($cursor <= $hoy) {
        $periodo = $cursor->format('Y-m');
        if ($cursor->format('Y-m') === $hoy->format('Y-m') && ($idAlumno % 5) === 0) {
            $cursor = $cursor->modify('+1 month');
            continue;
        }
        $diaPago = min(28, random_int(1, 10));
        $fechaPago = $cursor->format('Y-m-') . str_pad((string) $diaPago, 2, '0', STR_PAD_LEFT);
        if ($fechaPago > $hoy->format('Y-m-d')) {
            $cursor = $cursor->modify('+1 month');
            continue;
        }
        $monto = $diaPago <= 6 ? $pronto : $mensual;
        $params = [
            $idAlumno, $idPlantel, $idEsp ?: null, 'mensualidad', $idAe,
            'GDEMO-MEN-' . $idAlumno . '-' . $periodo, $monto,
            random_int(0, 1) ? 'Efectivo' : 'Tarjeta',
            'Colegiatura ' . SEED_G_TAG, $periodo, $idUsuarioCaja, $fechaPago . ' 11:30:00',
        ];
        if ($hasFechaPago) {
            $params[] = $fechaPago;
        }
        $insStmt->execute($params);
        $insertados++;
        $cursor = $cursor->modify('+1 month');
    }

    return $insertados;
}

/** @param list<array<string,mixed>> $alumnos */
function seed_g_asistencias(PDO $pdo, int $idGrupo, array $alumnos, string $fechaInicio, array $diasSemana): int
{
    $chk = $pdo->prepare(
        "SELECT COUNT(*) FROM asistencias WHERE id_grupo = ? AND fecha >= ?"
    );
    $chk->execute([$idGrupo, $fechaInicio]);
    if ((int) $chk->fetchColumn() > 30) {
        return 0;
    }

    $insertados = 0;
    $inicio = new DateTimeImmutable($fechaInicio);
    $hoy = new DateTimeImmutable('today');
    $diasSet = array_fill_keys(array_map('intval', $diasSemana), true);

    for ($d = $inicio; $d <= $hoy; $d = $d->modify('+1 day')) {
        $w = (int) $d->format('w');
        if (!isset($diasSet[$w])) {
            continue;
        }
        $fechaStr = $d->format('Y-m-d');
        [$anio, $semana] = function_exists('asistencia_calc_semana')
            ? asistencia_calc_semana($fechaStr)
            : [(int) $d->format('o'), (int) $d->format('W')];

        foreach ($alumnos as $al) {
            $idAlumno = (int) $al['id_alumno'];
            $presente = random_int(1, 100) <= 87 ? 1 : 0;
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
                // ignore
            }
        }
    }

    return $insertados;
}

/**
 * @param list<array<string,mixed>> $alumnos
 * @param list<array<string,mixed>> $fasesCompletadas incluye fase actual
 */
function seed_g_calificaciones(
    PDO $pdo,
    int $idGrupo,
    array $alumnos,
    array $fasesCompletadas,
    int $idUsuarioProf
): int {
    if ($fasesCompletadas === [] || !function_exists('calificaciones_guardar_alumno')) {
        return 0;
    }

    $rubrica = function_exists('academico_rubrica_default') ? academico_rubrica_default() : [];
    $guardados = 0;

    foreach ($fasesCompletadas as $fase) {
        $idFase = (int) ($fase['id_fase'] ?? 0);
        if ($idFase <= 0) {
            continue;
        }

        $chk = $pdo->prepare(
            'SELECT COUNT(*) FROM alumno_calificacion_parcial WHERE id_grupo = ? AND id_fase = ?'
        );
        $chk->execute([$idGrupo, $idFase]);
        if ((int) $chk->fetchColumn() >= max(1, count($alumnos) - 1)) {
            continue;
        }

        if ($rubrica !== [] && function_exists('calificaciones_guardar_rubrica')) {
            calificaciones_guardar_rubrica($pdo, $idGrupo, $idFase, $rubrica, $idUsuarioProf);
        }
        $criterios = function_exists('calificaciones_obtener_rubrica')
            ? calificaciones_obtener_rubrica($pdo, $idGrupo, $idFase)
            : $rubrica;

        foreach ($alumnos as $idx => $al) {
            $idAlumno = (int) $al['id_alumno'];
            $notas = [];
            foreach ($criterios as $c) {
                $base = ($idx % 5 === 0) ? random_int(5, 7) : random_int(7, 10);
                $notas[$c['codigo']] = $base;
            }
            $res = calificaciones_guardar_alumno(
                $pdo,
                $idAlumno,
                $idFase,
                $idGrupo,
                $notas,
                $criterios,
                $idUsuarioProf,
                SEED_G_TAG
            );
            if ($res['ok'] ?? false) {
                $guardados++;
                if (!($res['aprobado'] ?? true) && ($idx % 7) === 0) {
                    try {
                        $pdo->prepare(
                            'UPDATE alumno_grupos SET en_riesgo_academico = 1 WHERE id_alumno = ? AND id_grupo = ?'
                        )->execute([$idAlumno, $idGrupo]);
                    } catch (PDOException $e) {
                        // opcional
                    }
                }
            }
        }
    }

    return $guardados;
}

function seed_g_asistencia_personal(PDO $pdo, int $idPlantel, array $userIds): int
{
    $n = 0;
    foreach ($userIds as $idUser) {
        if ($idUser <= 0) {
            continue;
        }
        $chk = $pdo->prepare(
            'SELECT COUNT(*) FROM asistencia_personal WHERE id_usuario = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 45 DAY)'
        );
        $chk->execute([$idUser]);
        if ((int) $chk->fetchColumn() > 15) {
            continue;
        }
        for ($d = 45; $d >= 1; $d--) {
            $fecha = date('Y-m-d', strtotime("-{$d} days"));
            if ((int) date('N', strtotime($fecha)) === 7) {
                continue;
            }
            $hora = sprintf('%02d:%02d:00', random_int(7, 8), random_int(0, 50));
            try {
                $pdo->prepare(
                    'INSERT INTO asistencia_personal (id_usuario, id_plantel, fecha, hora_llegada, origen)
                     VALUES (?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE hora_llegada = LEAST(hora_llegada, VALUES(hora_llegada))'
                )->execute([$idUser, $idPlantel, $fecha, $hora, 'huella']);
                $n++;
            } catch (PDOException $e) {
                // ignore
            }
        }
    }
    return $n;
}

function seed_g_preregistros_entrevistas(PDO $pdo, int $idPlantel, int $idAsesor, array $espIds): array
{
    $chk = $pdo->prepare('SELECT COUNT(*) FROM preregistros WHERE id_plantel = ? AND observaciones LIKE ?');
    $chk->execute([$idPlantel, '%' . SEED_G_TAG . '%']);
    if ((int) $chk->fetchColumn() > 0) {
        return ['prereg' => 0, 'entrevistas' => 0];
    }

    $prospectos = [
        ['Valeria', 'Ramírez', 'López', 17, 'secundaria', 'redes_sociales', 'León', 'Centro', '37000', 'activo', 1],
        ['Diego', 'Hernández', 'Cruz', 22, 'preparatoria', 'recomendado', 'León', 'San Miguel', '37290', 'activo', 0],
        ['Paola', 'Martínez', 'Soto', 19, 'preparatoria', 'publicidad', 'Silao', 'Las Américas', '36100', 'pendiente', 0],
        ['Andrés', 'García', 'Nava', 28, 'universidad', 'pasando', 'León', 'La Martinica', '37530', 'activo', 1],
        ['Jimena', 'Torres', 'Vega', 15, 'secundaria', 'cartas', 'León', 'Cerrito de Jerez', '37190', 'pendiente', 0],
        ['Ricardo', 'Flores', 'Mora', 35, 'universidad', 'recomendado', 'Guanajuato', 'Marfil', '36250', 'perdido', 0],
        ['Sofía', 'Mendoza', 'Ríos', 18, 'preparatoria', 'redes_sociales', 'León', 'El Coecillo', '37320', 'inscrito', 1],
        ['Bruno', 'Castillo', 'Pérez', 21, 'preparatoria', 'publicidad', 'León', 'Lindavista', '37440', 'activo', 0],
        ['Camila', 'Ruiz', 'Ortega', 16, 'secundaria', 'pasando', 'San Francisco del Rincón', 'Centro', '36300', 'pendiente', 0],
        ['Héctor', 'Vargas', 'Delgado', 40, 'otros', 'otro', 'León', 'Obrera', '37300', 'perdido', 0],
        ['Natalia', 'Sánchez', 'Luna', 24, 'universidad', 'recomendado', 'León', 'Jardines del Moral', '37160', 'activo', 1],
        ['Iván', 'Guerrero', 'Salas', 20, 'preparatoria', 'cartas', 'León', 'Los Castillos', '37238', 'activo', 0],
        ['Elena', 'Navarro', 'Ponce', 14, 'secundaria', 'redes_sociales', 'León', 'Delta', '37545', 'pendiente', 0],
        ['Oscar', 'Reyes', 'Campos', 31, 'universidad', 'pasando', 'León', 'Valle del Campestre', '37138', 'activo', 0],
        ['Fernanda', 'Aguilar', 'Ibarra', 26, 'preparatoria', 'recomendado', 'Purísima del Rincón', 'Centro', '36400', 'inscrito', 1],
    ];

    $mediosOk = ['redes_sociales', 'publicidad', 'cartas', 'pasando', 'recomendado', 'otro'];
    $espList = array_values(array_filter($espIds));
    $creados = 0;
    $entrevistas = 0;
    $espIdx = 0;

    foreach ($prospectos as $i => $p) {
        [$nom, $ap, $am, $edad, $grado, $medio, $muni, $colonia, $cp, $estado, $apartado] = $p;
        if (!in_array($medio, $mediosOk, true)) {
            $medio = 'otro';
        }
        $idEsp = $espList[$espIdx % max(1, count($espList))] ?? null;
        $espIdx++;
        $fnac = (new DateTimeImmutable('today'))->modify("-{$edad} years")->modify('-' . random_int(0, 200) . ' days')->format('Y-m-d');

        $idEntrevista = 0;
        try {
            $_SESSION['user_id'] = $idAsesor;
            if (function_exists('asesor_entrevista_guardar')) {
                $er = asesor_entrevista_guardar($pdo, $idPlantel, [
                    'id_usuario_asesor' => $idAsesor,
                    'id_usuario_registra' => $idAsesor,
                    'nombres' => $nom,
                    'apellido_paterno' => $ap,
                    'apellido_materno' => $am,
                    'telefono' => '477' . random_int(1000000, 9999999),
                    'email' => strtolower($nom) . '.' . $i . '@ejemplo.com',
                    'observaciones' => 'Entrevista demo ' . SEED_G_TAG,
                ]);
                if ($er['ok'] ?? false) {
                    $idEntrevista = (int) $er['id_entrevista'];
                    $entrevistas++;
                    $estadoEnt = match ($estado) {
                        'inscrito' => 'inscrito',
                        'perdido' => 'descartado',
                        'pendiente' => 'contacto',
                        default => 'preregistro',
                    };
                    $pdo->prepare('UPDATE asesor_entrevistas SET estado = ? WHERE id_entrevista = ?')
                        ->execute([$estadoEnt, $idEntrevista]);
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        $params = [
            $idPlantel,
            $idAsesor,
            $idEsp,
            $nom,
            $ap,
            $am,
            $fnac,
            $edad,
            $medio,
            $medio === 'otro' ? 'Volante feria' : null,
            'Calle Demo ' . ($i + 1),
            $colonia,
            $muni,
            '477' . random_int(1000000, 9999999),
            strtolower($nom) . '.demo' . $i . '@ejemplo.com',
            $cp,
            $edad < 18 ? 'Estudiante' : 'Empleado',
            $grado,
            $edad < 18 ? 'Tutor Demo' : null,
            'Demo demográfico ' . SEED_G_TAG,
            $estado,
            $apartado ? 1 : 0,
            $apartado ? 500.0 : null,
        ];

        try {
            $pdo->prepare(
                'INSERT INTO preregistros (
                    id_plantel, id_usuario_registro, id_especialidad, nombres, apellido_paterno, apellido_materno,
                    fecha_nacimiento, edad, medio_entero, medio_entero_otro, domicilio, colonia, municipio,
                    telefono, email, codigo_postal, ocupacion, grado_estudios, padre_tutor, observaciones,
                    estado, tiene_apartado, monto_apartado
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute($params);
            $idPrereg = (int) $pdo->lastInsertId();
            $creados++;

            if ($idEntrevista > 0 && $idPrereg > 0) {
                try {
                    $pdo->prepare('UPDATE preregistros SET id_entrevista_origen = ? WHERE id_preregistro = ?')
                        ->execute([$idEntrevista, $idPrereg]);
                    $pdo->prepare(
                        "UPDATE asesor_entrevistas SET id_preregistro = ?, estado = IF(estado='contacto','preregistro',estado) WHERE id_entrevista = ?"
                    )->execute([$idPrereg, $idEntrevista]);
                } catch (PDOException $e) {
                    // columnas opcionales
                }
            }
            if ($estado === 'perdido') {
                try {
                    $pdo->prepare(
                        'UPDATE preregistros SET categoria_perdido = ?, motivo_perdido = ? WHERE id_preregistro = ?'
                    )->execute(['precio', 'No pudo cubrir colegiatura (demo)', $idPrereg]);
                } catch (PDOException $e) {
                    // ignore
                }
            }
            if ($estado === 'pendiente') {
                try {
                    $pdo->prepare(
                        'UPDATE preregistros SET categoria_pendiente = ?, motivo_pendiente = ? WHERE id_preregistro = ?'
                    )->execute(['espera_grupo', 'Espera apertura de grupo (demo)', $idPrereg]);
                } catch (PDOException $e) {
                    // ignore
                }
            }
        } catch (PDOException $e) {
            seed_g_log('  (aviso preregistro: ' . $e->getMessage() . ')');
        }
    }

    return ['prereg' => $creados, 'entrevistas' => $entrevistas];
}

function seed_g_cortes_caja(PDO $pdo, int $idPlantel, int $idUsuario): int
{
    if (!function_exists('reporte_corte_caja_guardar')) {
        return 0;
    }
    $n = 0;
    for ($d = 14; $d >= 1; $d--) {
        $fecha = date('Y-m-d', strtotime("-{$d} days"));
        if ((int) date('N', strtotime($fecha)) >= 6) {
            continue;
        }
        foreach (['A', 'B'] as $cuenta) {
            $exist = function_exists('reporte_corte_caja_obtener')
                ? reporte_corte_caja_obtener($pdo, $idPlantel, $fecha, $cuenta)
                : null;
            if ($exist && str_contains((string) ($exist['notas'] ?? ''), SEED_G_TAG)) {
                continue;
            }
            $ingreso = round(random_int(3500, 18000) / 10, 2);
            $retiros = round(random_int(0, 1500) / 10, 2);
            $comprobantes = round(random_int(200, 4000) / 10, 2);
            $efectivo = round($ingreso - $retiros - $comprobantes + random_int(-50, 50), 2);
            $res = reporte_corte_caja_guardar($pdo, $idPlantel, $idUsuario, [
                'fecha' => $fecha,
                'cuenta' => $cuenta,
                'ingreso_sistema' => $ingreso,
                'retiros' => $retiros,
                'comprobantes' => $comprobantes,
                'efectivo_contado' => max(0, $efectivo),
                'notas' => 'Corte demo ' . SEED_G_TAG,
            ]);
            if ($res['ok'] ?? false) {
                $n++;
            }
        }
    }
    return $n;
}

function seed_g_reporte_semanal(PDO $pdo, int $idPlantel): int
{
    if (!function_exists('reporte_semanal_sincronizar') || !function_exists('reporte_semanal_desde_fecha')) {
        return 0;
    }
    $n = 0;
    for ($w = 6; $w >= 0; $w--) {
        $fecha = date('Y-m-d', strtotime("-{$w} weeks"));
        $ref = reporte_semanal_desde_fecha($fecha);
        $anio = (int) ($ref['anio'] ?? date('Y'));
        $semana = (int) ($ref['semana'] ?? 1);
        try {
            reporte_semanal_sincronizar($pdo, $idPlantel, $anio, $semana);
            $n++;
        } catch (Throwable $e) {
            seed_g_log('  (aviso semanal ' . $anio . '-W' . $semana . ': ' . $e->getMessage() . ')');
        }
    }
    return $n;
}

function seed_g_rol_aulas(PDO $pdo, int $idPlantel, int $idUsuario): bool
{
    if (!function_exists('rol_aula_generar') || !function_exists('rol_aula_publicar')) {
        return false;
    }
    $anio = (int) date('Y');
    $mes = (int) date('n');
    $gen = rol_aula_generar($pdo, $idPlantel, $anio, $mes, $idUsuario);
    if (!($gen['ok'] ?? false)) {
        seed_g_log('  (aviso rol aulas: ' . ($gen['message'] ?? 'no generado') . ')');
        return false;
    }
    $idPub = (int) ($gen['id_publicacion'] ?? 0);
    if ($idPub <= 0) {
        return false;
    }
    $pub = rol_aula_publicar($pdo, $idPlantel, $idPub, $idUsuario);
    if (!($pub['ok'] ?? false)) {
        seed_g_log('  (aviso publicar rol: ' . ($pub['message'] ?? 'error') . ')');
        return false;
    }
    seed_g_log('  + Rol de aulas publicado ' . $anio . '-' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT));
    return true;
}

// ——— Ejecución ———
seed_g_log('=== Seed demo Guerrero (' . SEED_G_TAG . ') ===');
seed_g_log('Contraseña usuarios: ' . SEED_G_PASSWORD);
seed_g_log('');

try {
    if (function_exists('hay_bootstrap_schema')) {
        hay_bootstrap_schema($pdo);
    }
} catch (Throwable $e) {
    seed_g_log('Aviso bootstrap: ' . $e->getMessage());
}

foreach (['aula_ensure_schema', 'rol_aula_ensure_schema', 'preregistro_ensure_schema', 'asesor_ensure_schema', 'reporte_semanal_ensure_schema'] as $fn) {
    if (function_exists($fn)) {
        try {
            $fn($pdo);
        } catch (Throwable $e) {
            seed_g_log("Aviso {$fn}: " . $e->getMessage());
        }
    }
}

$stPl = $pdo->prepare("SELECT id_plantel, nombre FROM planteles WHERE slug = 'guerrero' AND activo = 1 LIMIT 1");
$stPl->execute();
$plantel = $stPl->fetch(PDO::FETCH_ASSOC);
if (!$plantel) {
    seed_g_fail('No existe plantel activo con slug guerrero');
}
$idPlantel = (int) $plantel['id_plantel'];
$_SESSION['plantel_id'] = $idPlantel;
seed_g_log('Plantel: ' . $plantel['nombre'] . " (#{$idPlantel})");

$idIng = seed_g_especialidad_id($pdo, 'ING');
$idComp = seed_g_especialidad_id($pdo, 'COMP25', 'COMP', 'COMP24');
$idPrep = seed_g_especialidad_id($pdo, 'PREP-AB', 'PREP-ESC');
if ($idIng <= 0) {
    seed_g_fail('Falta especialidad ING activa');
}
seed_g_log("Especialidades: ING={$idIng}, COMP={$idComp}, PREP={$idPrep}");

$fasesIng = seed_g_fases($pdo, $idIng);
$fasesComp = $idComp > 0 ? seed_g_fases($pdo, $idComp) : [];
$fasesPrep = $idPrep > 0 ? seed_g_fases($pdo, $idPrep) : [];

// ——— Personal ———
seed_g_log('');
seed_g_log('--- Personal Guerrero ---');
$idGerente = seed_g_usuario($pdo, 'demo.g.deysi', 'Deysi', 'Guerrero', 'gerente', $idPlantel, 'Dirección', $passHash);
$idAsesor = seed_g_usuario($pdo, 'demo.g.mejia', 'Roberto', 'Mejía', 'asesor', $idPlantel, 'Ventas', $passHash);
seed_g_usuario($pdo, 'demo.g.sharoom', 'Sharoom', 'López', 'profesor', $idPlantel, 'Coordinación Inglés', $passHash);
seed_g_usuario($pdo, 'demo.g.manuel', 'Manuel', 'Ríos', 'profesor', $idPlantel, 'Coordinación Computación y Preparatoria', $passHash);
$idRecepcion = seed_g_usuario($pdo, 'demo.g.karla', 'Karla', 'Núñez', 'admin', $idPlantel, 'Recepción', $passHash);

$profDefs = [
    ['demo.g.prof.pedro', 'Pedro', 'Guerrero'],
    ['demo.g.prof.pablo', 'Pablo', 'Guerrero'],
    ['demo.g.prof.penelope', 'Penélope', 'Guerrero'],
    ['demo.g.prof.marina', 'Marina', 'Guerrero'],
    ['demo.g.prof.raul', 'Raúl', 'Guerrero'],
];
$profIds = [];
foreach ($profDefs as [$u, $n, $a]) {
    $profIds[] = seed_g_usuario($pdo, $u, $n, $a, 'profesor', $idPlantel, 'Profesor demo Guerrero', $passHash);
}

// ——— Aulas ———
seed_g_log('');
seed_g_log('--- Aulas ---');
$aulasDef = [
    ['codigo' => 'A1', 'nombre' => 'Aula 1', 'piso' => 'PB', 'capacidad' => 18, 'tiene_pizarron' => 1, 'tiene_proyector' => 1, 'tipo_aula' => 'aula'],
    ['codigo' => 'A2', 'nombre' => 'Aula 2', 'piso' => 'PB', 'capacidad' => 20, 'tiene_pizarron' => 1, 'tiene_proyector' => 1, 'tipo_aula' => 'aula'],
    ['codigo' => 'A3', 'nombre' => 'Aula 3', 'piso' => '1', 'capacidad' => 16, 'tiene_pizarron' => 1, 'tiene_tv' => 1, 'tipo_aula' => 'aula'],
    ['codigo' => 'A4', 'nombre' => 'Aula 4', 'piso' => '1', 'capacidad' => 22, 'tiene_pizarron' => 1, 'tiene_proyector' => 1, 'tipo_aula' => 'aula'],
    ['codigo' => 'A5', 'nombre' => 'Aula 5', 'piso' => '2', 'capacidad' => 15, 'tiene_pizarron' => 1, 'tipo_aula' => 'aula'],
    ['codigo' => 'LAB1', 'nombre' => 'Lab Cómputo 1', 'piso' => '2', 'capacidad' => 20, 'tiene_pc' => 1, 'tiene_proyector' => 1, 'tipo_aula' => 'lab_computo'],
];
$aulaIds = [];
foreach ($aulasDef as $ad) {
    $aulaIds[$ad['codigo']] = seed_g_aula($pdo, $idPlantel, $ad);
}

// ——— Grupos (idempotente) ———
seed_g_log('');
seed_g_log('--- Grupos y alumnos ---');
$chkG = $pdo->prepare('SELECT COUNT(*) FROM grupos WHERE id_plantel = ? AND aula LIKE ?');
$chkG->execute([$idPlantel, '%' . SEED_G_TAG . '%']);
$yaGrupos = (int) $chkG->fetchColumn();

$alumnosNombres = [
    ['Guadalupe', 'García', 'López'], ['Genaro', 'González', 'Martínez'], ['Gustavo', 'Gutiérrez', 'Hernández'],
    ['Gabriela', 'Guerrero', 'Soto'], ['Gael', 'Galván', 'Reyes'], ['Gloria', 'Gómez', 'Vargas'],
    ['Gerardo', 'Gil', 'Mendoza'], ['Griselda', 'Granados', 'Cruz'], ['Gonzalo', 'Guerra', 'Flores'],
    ['Gemma', 'Galindo', 'Ramos'], ['Gisela', 'Gordillo', 'Navarro'], ['Gabino', 'Garza', 'Silva'],
    ['Guillermina', 'Gamboa', 'Ortiz'], ['Gaspar', 'Gálvez', 'Medina'], ['Greta', 'Guevara', 'Campos'],
    ['Gina', 'Godínez', 'Paredes'], ['Guido', 'Guzmán', 'Tapia'], ['Galia', 'Gallego', 'Nieto'],
    ['Giovanni', 'Garrido', 'Quintero'], ['Gilda', 'Grijalva', 'Salinas'], ['German', 'Gómez', 'Uribe'],
    ['Gracia', 'Gallegos', 'Valencia'], ['Guillermo', 'Gaitán', 'Wong'], ['Gina', 'Guajardo', 'Zavala'],
    ['Gabriel', 'Góngora', 'Acosta'], ['Gala', 'Garrido', 'Becerra'], ['Guido', 'Guardado', 'Carrillo'],
    ['Giselle', 'Guzmán', 'Dávila'], ['Gerson', 'Galicia', 'Escamilla'], ['Gina', 'Gómez', 'Fierro'],
    ['Gustavo', 'Guerra', 'Huerta'], ['Gloria', 'Galván', 'Ibáñez'], ['Gerardo', 'García', 'Juárez'],
    ['Grisel', 'González', 'Lara'], ['Gael', 'Guerrero', 'Márquez'], ['Gabriela', 'Gil', 'Nava'],
];

/**
 * Definición de grupos demo.
 * fases_index: índice 0-based de fase actual en el catálogo (debe tener fases previas para historial).
 */
$gruposPlan = [
    [
        'label' => 'ING sábado avanzado',
        'id_esp' => $idIng,
        'fases' => $fasesIng,
        'fase_idx' => min(3, max(0, count($fasesIng) - 1)),
        'meses_atras' => 7,
        'area' => 'I',
        'horario' => 'S',
        'horario_texto' => 'Sábados 09:00-13:00',
        'dias' => [6],
        'hora_i' => '09:00:00',
        'hora_f' => '13:00:00',
        'aula' => 'A1',
        'prof' => 0,
        'alumnos' => 6,
    ],
    [
        'label' => 'ING domingo intermedio',
        'id_esp' => $idIng,
        'fases' => $fasesIng,
        'fase_idx' => min(2, max(0, count($fasesIng) - 1)),
        'meses_atras' => 5,
        'area' => 'I',
        'horario' => 'D',
        'horario_texto' => 'Domingos 09:00-13:00',
        'dias' => [0],
        'hora_i' => '09:00:00',
        'hora_f' => '13:00:00',
        'aula' => 'A2',
        'prof' => 1,
        'alumnos' => 6,
    ],
    [
        'label' => 'ING entre semana',
        'id_esp' => $idIng,
        'fases' => $fasesIng,
        'fase_idx' => min(1, max(0, count($fasesIng) - 1)),
        'meses_atras' => 3,
        'area' => 'I',
        'horario' => 'M',
        'horario_texto' => 'Lun-Mié 18:00-20:00',
        'dias' => [1, 3],
        'hora_i' => '18:00:00',
        'hora_f' => '20:00:00',
        'aula' => 'A3',
        'prof' => 2,
        'alumnos' => 5,
    ],
];

if ($idComp > 0) {
    $gruposPlan[] = [
        'label' => 'COMP sábado',
        'id_esp' => $idComp,
        'fases' => $fasesComp,
        'fase_idx' => min(2, max(0, count($fasesComp) - 1)),
        'meses_atras' => 6,
        'area' => 'C',
        'horario' => 'S',
        'horario_texto' => 'Sábados 09:00-13:00',
        'dias' => [6],
        'hora_i' => '09:00:00',
        'hora_f' => '13:00:00',
        'aula' => 'LAB1',
        'prof' => 3,
        'alumnos' => 6,
    ];
    $gruposPlan[] = [
        'label' => 'COMP entre semana',
        'id_esp' => $idComp,
        'fases' => $fasesComp,
        'fase_idx' => min(1, max(0, count($fasesComp) - 1)),
        'meses_atras' => 4,
        'area' => 'C',
        'horario' => 'V',
        'horario_texto' => 'Mar-Jue 17:00-19:00',
        'dias' => [2, 4],
        'hora_i' => '17:00:00',
        'hora_f' => '19:00:00',
        'aula' => 'A4',
        'prof' => 3,
        'alumnos' => 5,
    ];
}

if ($idPrep > 0) {
    $gruposPlan[] = [
        'label' => 'PREP abierta',
        'id_esp' => $idPrep,
        'fases' => $fasesPrep,
        'fase_idx' => min(1, max(0, count($fasesPrep) - 1)),
        'meses_atras' => 8,
        'area' => 'P',
        'horario' => 'S',
        'horario_texto' => 'Sábados 08:00-14:00',
        'dias' => [6],
        'hora_i' => '08:00:00',
        'hora_f' => '14:00:00',
        'aula' => 'A5',
        'prof' => 4,
        'alumnos' => 6,
    ];
}

$alumnoCursor = 0;
$totPagos = 0;
$totAsist = 0;
$totCal = 0;
$idCaja = $idRecepcion > 0 ? $idRecepcion : $idGerente;

if ($yaGrupos >= count($gruposPlan)) {
    seed_g_log('  (Ya hay ' . $yaGrupos . ' grupos con etiqueta ' . SEED_G_TAG . ' — omitiendo creación)');
    $stExist = $pdo->prepare(
        'SELECT g.* FROM grupos g WHERE g.id_plantel = ? AND g.aula LIKE ? ORDER BY g.id_grupo'
    );
    $stExist->execute([$idPlantel, '%' . SEED_G_TAG . '%']);
    $gruposExistentes = $stExist->fetchAll(PDO::FETCH_ASSOC);
    foreach ($gruposExistentes as $grupo) {
        $idGrupo = (int) $grupo['id_grupo'];
        $idProf = (int) ($grupo['id_profesor'] ?? 0);
        $stAl = $pdo->prepare(
            'SELECT a.* FROM alumno_grupos ag INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
             WHERE ag.id_grupo = ? AND ag.activo = 1'
        );
        $stAl->execute([$idGrupo]);
        $als = $stAl->fetchAll(PDO::FETCH_ASSOC);
        $fechaIni = (string) ($grupo['fecha_inicio'] ?? date('Y-m-d', strtotime('-6 months')));
        $dias = [6];
        try {
            $stH = $pdo->prepare('SELECT dia_semana FROM grupo_horarios WHERE id_grupo = ? AND activo = 1');
            $stH->execute([$idGrupo]);
            $dias = array_map('intval', $stH->fetchAll(PDO::FETCH_COLUMN)) ?: [6];
        } catch (PDOException $e) {
            // ignore
        }
        foreach ($als as $al) {
            $totPagos += seed_g_pagos_alumno($pdo, $al, $idCaja, $fechaIni);
        }
        $totAsist += seed_g_asistencias($pdo, $idGrupo, $als, $fechaIni, $dias);
        $idEspG = (int) ($grupo['id_especialidad'] ?? 0);
        $fasesG = seed_g_fases($pdo, $idEspG);
        $idFaseAct = (int) ($grupo['id_fase_actual'] ?? 0);
        $fasesHasta = [];
        foreach ($fasesG as $f) {
            $fasesHasta[] = $f;
            if ((int) $f['id_fase'] === $idFaseAct) {
                break;
            }
        }
        if ($fasesHasta === [] && $idFaseAct > 0) {
            $fasesHasta = [['id_fase' => $idFaseAct]];
        }
        $totCal += seed_g_calificaciones($pdo, $idGrupo, $als, $fasesHasta, $idProf ?: $idGerente);
    }
} else {
    foreach ($gruposPlan as $gp) {
        seed_g_log('');
        seed_g_log('[' . $gp['label'] . ']');
        $fases = $gp['fases'];
        $faseIdx = (int) $gp['fase_idx'];
        $idFase = !empty($fases[$faseIdx]['id_fase']) ? (int) $fases[$faseIdx]['id_fase'] : null;
        $fechaInicio = (new DateTimeImmutable('today'))
            ->modify('-' . (int) $gp['meses_atras'] . ' months')
            ->modify('saturday')
            ->format('Y-m-d');
        $idProf = $profIds[(int) $gp['prof']] ?? $profIds[0];
        $codigoAula = (string) $gp['aula'];
        $idAula = $aulaIds[$codigoAula] ?? null;

        $grupo = seed_g_crear_grupo(
            $pdo,
            $idPlantel,
            $idProf,
            (int) $gp['id_esp'],
            $idFase,
            $fechaInicio,
            (string) $gp['area'],
            (string) $gp['horario'],
            (string) $gp['horario_texto'],
            $gp['dias'],
            (string) $gp['hora_i'],
            (string) $gp['hora_f'],
            $idAula,
            $codigoAula
        );
        if (!$grupo) {
            continue;
        }

        $als = [];
        for ($a = 0; $a < (int) $gp['alumnos']; $a++) {
            $nom = $alumnosNombres[$alumnoCursor % count($alumnosNombres)];
            $alumnoCursor++;
            try {
                $idAl = seed_g_crear_alumno(
                    $pdo,
                    $idPlantel,
                    $grupo['id_grupo'],
                    (int) $gp['id_esp'],
                    $nom[0],
                    $nom[1],
                    $nom[2],
                    $fechaInicio,
                    $passHash
                );
                $stAl = $pdo->prepare('SELECT * FROM alumnos WHERE id_alumno = ?');
                $stAl->execute([$idAl]);
                $row = $stAl->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $als[] = $row;
                    $totPagos += seed_g_pagos_alumno($pdo, $row, $idCaja, $fechaInicio);
                }
            } catch (Throwable $e) {
                seed_g_log('    ERROR alumno: ' . $e->getMessage());
            }
        }

        $totAsist += seed_g_asistencias($pdo, $grupo['id_grupo'], $als, $fechaInicio, $gp['dias']);
        $fasesHasta = array_slice($fases, 0, $faseIdx + 1);
        if ($fasesHasta === [] && $idFase) {
            $fasesHasta = [['id_fase' => $idFase]];
        }
        $nCal = seed_g_calificaciones($pdo, $grupo['id_grupo'], $als, $fasesHasta, $idProf);
        $totCal += $nCal;
        seed_g_log("  Pagos: +movimientos · Asistencias: lotes · Calificaciones fase(s): {$nCal}");
    }
}

// ——— Preregistros / entrevistas ———
seed_g_log('');
seed_g_log('--- Preregistros y entrevistas ---');
$cap = seed_g_preregistros_entrevistas($pdo, $idPlantel, $idAsesor, [$idIng, $idComp, $idPrep]);
seed_g_log('  Preregistros: ' . $cap['prereg'] . ' · Entrevistas: ' . $cap['entrevistas']);

// ——— Asistencia personal ———
seed_g_log('');
seed_g_log('--- Asistencia personal (checador) ---');
$nPers = seed_g_asistencia_personal($pdo, $idPlantel, array_merge([$idRecepcion, $idAsesor, $idGerente], $profIds));
seed_g_log("  Registros: {$nPers}");

// ——— Cortes de caja ———
seed_g_log('');
seed_g_log('--- Cortes de caja ---');
$nCortes = seed_g_cortes_caja($pdo, $idPlantel, $idCaja);
seed_g_log("  Cortes guardados: {$nCortes}");

// ——— Reporte semanal ———
seed_g_log('');
seed_g_log('--- Reporte semanal ---');
$nSem = seed_g_reporte_semanal($pdo, $idPlantel);
seed_g_log("  Semanas sincronizadas: {$nSem}");

// ——— Rol de aulas ———
seed_g_log('');
seed_g_log('--- Rol de aulas ---');
seed_g_rol_aulas($pdo, $idPlantel, $idGerente);

seed_g_log('');
seed_g_log('=== Resumen Guerrero ===');
seed_g_log('Etiqueta: ' . SEED_G_TAG);
seed_g_log("Pagos insertados (aprox. movimientos nuevos): {$totPagos}");
seed_g_log("Asistencias (filas tocadas): {$totAsist}");
seed_g_log("Calificaciones (alumno-fase): {$totCal}");
seed_g_log('');
seed_g_log('Logins demo:');
seed_g_log('  Dirección: demo.g.deysi / ' . SEED_G_PASSWORD);
seed_g_log('  Asesor:    demo.g.mejia / ' . SEED_G_PASSWORD);
seed_g_log('  Recepción: demo.g.karla / ' . SEED_G_PASSWORD);
seed_g_log('  Profesores: demo.g.prof.pedro (y pablo/penelope/marina/raul) / ' . SEED_G_PASSWORD);
seed_g_log('  Alumnos: número de control / ' . SEED_G_PASSWORD);
seed_g_log('=== Listo ===');
