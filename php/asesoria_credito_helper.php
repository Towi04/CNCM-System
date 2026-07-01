<?php

/**
 * Créditos de asesoría (regularización, cortesía, etc.).
 */

function asesoria_credito_saldo(PDO $pdo, int $idAlumno, ?int $idPlantel = null): float
{
    asesoria_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(horas_otorgadas - horas_usadas), 0) FROM asesoria_credito
         WHERE id_alumno = ? AND id_plantel = ?
           AND (vence_en IS NULL OR vence_en >= CURDATE())'
    );
    $st->execute([$idAlumno, $idPlantel]);

    return round((float) $st->fetchColumn(), 2);
}

function asesoria_credito_listar(PDO $pdo, int $idAlumno, ?int $idPlantel = null): array
{
    asesoria_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT * FROM asesoria_credito
         WHERE id_alumno = ? AND id_plantel = ?
         ORDER BY creado_en DESC'
    );
    $st->execute([$idAlumno, $idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function asesoria_credito_otorgar(
    PDO $pdo,
    int $idAlumno,
    float $horas,
    string $origen,
    array $opts = []
): array {
    asesoria_ensure_schema($pdo);
    $idPlantel = (int) ($opts['id_plantel'] ?? plantel_id_activo());
    $horas = max(0.5, min(10, $horas));
    $soloInd = !empty($opts['solo_individual']) ? 1 : 0;
    if ($origen === 'inscripcion_tardia') {
        $soloInd = 1;
    }
    $pdo->prepare(
        'INSERT INTO asesoria_credito (
            id_alumno, id_plantel, origen, horas_otorgadas, solo_individual,
            vence_en, id_grupo, semana_falta, notas, id_usuario_otorga
         ) VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idAlumno,
        $idPlantel,
        $origen,
        $horas,
        $soloInd,
        $opts['vence_en'] ?? null,
        $opts['id_grupo'] ?? null,
        $opts['semana_falta'] ?? null,
        isset($opts['notas']) ? mb_substr(trim((string) $opts['notas']), 0, 500) : null,
        $opts['id_usuario'] ?? ($_SESSION['user_id'] ?? null),
    ]);

    return ['ok' => true, 'message' => 'Crédito otorgado', 'id_credito' => (int) $pdo->lastInsertId()];
}

/** Tras inscripción tardía a grupo en curso: otorgar créditos de regularización. */
function inscripcion_asesoria_aplicar_regularizacion(PDO $pdo, int $idAlumno, int $idGrupo, array $opts): void
{
    $horas = (float) ($opts['asesoria_horas_regularizacion'] ?? 0);
    if ($horas <= 0) {
        return;
    }
    asesoria_credito_otorgar($pdo, $idAlumno, $horas, 'inscripcion_tardia', [
        'id_plantel' => plantel_id_activo(),
        'solo_individual' => true,
        'id_grupo' => $idGrupo,
        'notas' => trim((string) ($opts['asesoria_notas'] ?? 'Regularización inscripción tardía')),
        'id_usuario' => (int) ($_SESSION['user_id'] ?? 0),
    ]);
}

function asesoria_credito_consumir(PDO $pdo, int $idAlumno, float $horas = 1.0, ?int $idPlantel = null): ?int
{
    asesoria_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT id_credito, horas_otorgadas, horas_usadas, solo_individual FROM asesoria_credito
         WHERE id_alumno = ? AND id_plantel = ?
           AND horas_usadas < horas_otorgadas
           AND (vence_en IS NULL OR vence_en >= CURDATE())
         ORDER BY creado_en ASC'
    );
    $st->execute([$idAlumno, $idPlantel]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $disp = (float) $c['horas_otorgadas'] - (float) $c['horas_usadas'];
        if ($disp + 0.001 < $horas) {
            continue;
        }
        $pdo->prepare('UPDATE asesoria_credito SET horas_usadas = horas_usadas + ? WHERE id_credito = ?')
            ->execute([$horas, (int) $c['id_credito']]);

        return (int) $c['id_credito'];
    }

    return null;
}

/** Grupo en curso con inicio hace ≤21 días (inscripción tardía / regularización). */
function inscripcion_grupo_es_inscripcion_tardia(PDO $pdo, int $idGrupo): array
{
    $st = $pdo->prepare(
        'SELECT g.fecha_inicio, g.clave, g.id_especialidad,
                COALESCE(e.costo_semanal_apoyo, e.costo_semanal, 0) AS costo_semanal
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_grupo = ? LIMIT 1'
    );
    $st->execute([$idGrupo]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['aplica' => false];
    }
    $fechaInicio = (string) ($row['fecha_inicio'] ?? '');
    if ($fechaInicio === '' || $fechaInicio >= date('Y-m-d')) {
        return ['aplica' => false, 'clave' => $row['clave'] ?? ''];
    }
    $dias = (int) floor((strtotime('today') - strtotime($fechaInicio)) / 86400);
    if ($dias > 21) {
        return ['aplica' => false, 'dias' => $dias, 'clave' => $row['clave'] ?? ''];
    }

    return [
        'aplica' => true,
        'dias' => $dias,
        'clave' => $row['clave'] ?? '',
        'id_especialidad' => (int) ($row['id_especialidad'] ?? 0),
        'costo_semana' => round((float) ($row['costo_semanal'] ?? 0), 2),
    ];
}

/** Meta para wizard Avanzado: semana extra y créditos de regularización. */
function inscripcion_asesoria_meta_grupo(PDO $pdo, int $idGrupo, int $idAlumno): array
{
    $tardia = inscripcion_grupo_es_inscripcion_tardia($pdo, $idGrupo);
    if (!$tardia['aplica']) {
        return ['inscripcion_tardia' => false];
    }
    $costoSem = (float) ($tardia['costo_semana'] ?? 0);
    if ($idAlumno > 0 && $tardia['id_especialidad'] > 0) {
        $ae = $pdo->prepare(
            'SELECT costo_semanal FROM alumno_especialidades
             WHERE id_alumno = ? AND id_especialidad = ? LIMIT 1'
        );
        $ae->execute([$idAlumno, (int) $tardia['id_especialidad']]);
        $semAe = $ae->fetchColumn();
        if ($semAe !== false && (float) $semAe > 0) {
            $costoSem = round((float) $semAe, 2);
        }
    }

    return [
        'inscripcion_tardia' => true,
        'dias_desde_inicio' => (int) ($tardia['dias'] ?? 0),
        'grupo_clave' => $tardia['clave'] ?? '',
        'costo_semana_extra' => $costoSem,
        'costo_semana_extra_fmt' => catalog_format_mxn($costoSem),
        'max_horas_regularizacion' => 3,
    ];
}

/** Cobro opcional de semana extra tras inscripción tardía. */
function inscripcion_asesoria_aplicar_semana_extra(PDO $pdo, int $idAlumno, int $idGrupo, array $opts): array
{
    if (empty($opts['asesoria_semana_extra'])) {
        return ['ok' => true, 'message' => 'Sin semana extra'];
    }
    if (!empty($opts['asesoria_exonerar_semana'])) {
        $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';
        $puede = in_array($rol, ['director', 'supervisor', 'gerente', 'admin'], true)
            || (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total());
        if (!$puede) {
            return ['ok' => false, 'message' => 'Solo director puede exonerar la semana extra'];
        }

        return ['ok' => true, 'message' => 'Semana extra exonerada'];
    }
    $meta = inscripcion_asesoria_meta_grupo($pdo, $idGrupo, $idAlumno);
    if (empty($meta['inscripcion_tardia'])) {
        return ['ok' => false, 'message' => 'El grupo no aplica semana extra'];
    }
    $monto = round((float) ($meta['costo_semana_extra'] ?? 0), 2);
    if ($monto <= 0) {
        return ['ok' => true, 'message' => 'Semana extra sin costo configurado'];
    }
    $g = $pdo->prepare('SELECT id_especialidad, clave FROM grupos WHERE id_grupo = ? LIMIT 1');
    $g->execute([$idGrupo]);
    $grupo = $g->fetch(PDO::FETCH_ASSOC) ?: [];
    $idEsp = (int) ($grupo['id_especialidad'] ?? 0);
    $formaPago = trim((string) ($opts['forma_pago'] ?? 'Efectivo'));
    $res = pago_registrar($pdo, [
        'id_alumno' => $idAlumno,
        'id_especialidad' => $idEsp ?: null,
        'tipo' => 'colegiatura',
        'monto' => $monto,
        'concepto' => 'Semana extra — inscripción tardía — ' . ($grupo['clave'] ?? ''),
        'forma_pago_efectivo' => $formaPago !== '' ? $formaPago : 'Efectivo',
        'id_usuario' => (int) ($_SESSION['user_id'] ?? 0),
    ]);
    if (!$res['ok']) {
        return $res;
    }

    return [
        'ok' => true,
        'message' => 'Semana extra cobrada: ' . catalog_format_mxn($monto),
        'id_pago_semana' => (int) ($res['id_pago'] ?? 0),
    ];
}

function asesoria_credito_tiene_individual(PDO $pdo, int $idAlumno, ?int $idPlantel = null): bool
{
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT 1 FROM asesoria_credito
         WHERE id_alumno = ? AND id_plantel = ? AND solo_individual = 1
           AND horas_usadas < horas_otorgadas
           AND (vence_en IS NULL OR vence_en >= CURDATE()) LIMIT 1'
    );
    $st->execute([$idAlumno, $idPlantel]);

    return (bool) $st->fetchColumn();
}
