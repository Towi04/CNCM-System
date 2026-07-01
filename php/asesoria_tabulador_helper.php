<?php

/**
 * Tabulador de tarifas asesorías (alumno y profesor).
 */

function asesoria_tabulador_ensure_defaults(PDO $pdo, ?int $idPlantel = null): void
{
    if (!function_exists('asesoria_ensure_schema')) {
        return;
    }
    asesoria_ensure_schema($pdo);
    foreach (ASESORIA_TABULADOR_DEFAULTS as $clave => $def) {
        $st = $pdo->prepare(
            'SELECT id_tabulador FROM asesoria_tabulador
             WHERE clave = ? AND (id_plantel IS NULL OR id_plantel = ?) AND activo = 1 LIMIT 1'
        );
        $st->execute([$clave, $idPlantel ?? plantel_id_activo()]);
        if ($st->fetchColumn()) {
            continue;
        }
        $pdo->prepare(
            'INSERT INTO asesoria_tabulador (id_plantel, clave, nombre, monto_alumno, monto_profesor, activo)
             VALUES (?,?,?,?,?,1)'
        )->execute([
            $idPlantel,
            $clave,
            $def['nombre'],
            $def['monto_alumno'],
            $def['monto_profesor'],
        ]);
    }
}

function asesoria_tabulador_por_clave(PDO $pdo, string $clave, ?int $idPlantel = null): ?array
{
    asesoria_tabulador_ensure_defaults($pdo, $idPlantel);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT * FROM asesoria_tabulador
         WHERE clave = ? AND activo = 1
           AND (id_plantel = ? OR id_plantel IS NULL)
           AND (vigente_desde IS NULL OR vigente_desde <= CURDATE())
           AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())
         ORDER BY id_plantel DESC
         LIMIT 1'
    );
    $st->execute([$clave, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }
    $def = ASESORIA_TABULADOR_DEFAULTS[$clave] ?? null;

    return $def ? [
        'clave' => $clave,
        'nombre' => $def['nombre'],
        'monto_alumno' => $def['monto_alumno'],
        'monto_profesor' => $def['monto_profesor'],
    ] : null;
}

function asesoria_tabulador_monto(PDO $pdo, string $clave, string $campo = 'monto_alumno', ?int $idPlantel = null): float
{
    $t = asesoria_tabulador_por_clave($pdo, $clave, $idPlantel);
    if (!$t) {
        return 0.0;
    }

    return round((float) ($t[$campo] ?? 0), 2);
}

function asesoria_tabulador_listar(PDO $pdo, ?int $idPlantel = null): array
{
    asesoria_tabulador_ensure_defaults($pdo, $idPlantel);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT * FROM asesoria_tabulador
         WHERE (id_plantel IS NULL OR id_plantel = ?)
         ORDER BY clave ASC, id_plantel DESC'
    );
    $st->execute([$idPlantel]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $seen = [];
    $out = [];
    foreach ($rows as $r) {
        $k = $r['clave'];
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $r;
    }

    return $out;
}

function asesoria_tabulador_guardar(PDO $pdo, array $data, ?int $idPlantel = null): array
{
    if (!asesoria_puede_administrar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $id = (int) ($data['id_tabulador'] ?? 0);
    $clave = trim((string) ($data['clave'] ?? ''));
    $nombre = trim((string) ($data['nombre'] ?? ''));
    $mAl = round((float) ($data['monto_alumno'] ?? 0), 2);
    $mPr = round((float) ($data['monto_profesor'] ?? 0), 2);
    if ($clave === '' || $nombre === '') {
        return ['ok' => false, 'message' => 'Clave y nombre requeridos'];
    }
    $idPlantel = $idPlantel ?? plantel_id_activo();
    if ($id > 0) {
        $pdo->prepare(
            'UPDATE asesoria_tabulador SET nombre = ?, monto_alumno = ?, monto_profesor = ?, activo = 1
             WHERE id_tabulador = ?'
        )->execute([$nombre, $mAl, $mPr, $id]);
    } else {
        $pdo->prepare(
            'INSERT INTO asesoria_tabulador (id_plantel, clave, nombre, monto_alumno, monto_profesor, activo)
             VALUES (?,?,?,?,?,1)'
        )->execute([$idPlantel, $clave, $nombre, $mAl, $mPr]);
    }

    return ['ok' => true, 'message' => 'Tabulador guardado'];
}

function asesoria_calcular_pago_profesor(PDO $pdo, string $estado, int $numPresentes, bool $mismoTema, ?int $idPlantel = null): float
{
    if (in_array($estado, ['cancelada_a_tiempo', 'cancelada', 'reagendada'], true)) {
        return 0.0;
    }
    if ($estado === 'np') {
        return asesoria_tabulador_monto($pdo, 'prof_np_sin_clase', 'monto_profesor', $idPlantel);
    }
    if ($estado !== 'impartida') {
        return 0.0;
    }
    if ($numPresentes >= 2 && $mismoTema) {
        return asesoria_tabulador_monto($pdo, 'prof_2plus_mismo_tema', 'monto_profesor', $idPlantel);
    }

    return asesoria_tabulador_monto($pdo, 'prof_1_alumno', 'monto_profesor', $idPlantel) * max(1, $numPresentes);
}

function asesoria_calcular_cobro_alumno(PDO $pdo, string $tipo, bool $esReagendarNp = false, ?int $idEspecialidad = null, ?int $idPlantel = null): float
{
    if ($esReagendarNp) {
        return asesoria_tabulador_monto($pdo, 'alumno_reagendar_np', 'monto_alumno', $idPlantel);
    }
    if (in_array($tipo, ['falta_gratis', 'regularizacion'], true)) {
        return 0.0;
    }
    if ($tipo === 'pagada_cross') {
        return asesoria_tabulador_monto($pdo, 'alumno_materia_externa', 'monto_alumno', $idPlantel);
    }
    if ($tipo === 'pagada_materia' && $idEspecialidad > 0) {
        $st = $pdo->prepare('SELECT asesoria_costo_default FROM especialidades WHERE id_especialidad = ? LIMIT 1');
        $st->execute([$idEspecialidad]);
        $c = $st->fetchColumn();
        if ($c !== null && $c !== false && (float) $c > 0) {
            return round((float) $c, 2);
        }
    }

    return asesoria_tabulador_monto($pdo, 'alumno_materia_externa', 'monto_alumno', $idPlantel);
}
