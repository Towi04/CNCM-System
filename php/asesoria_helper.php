<?php

/**
 * Asesorías académicas — schema, permisos y elegibilidad.
 */

const ASESORIA_TIPOS = [
    'falta_gratis' => 'Falta a clase (gratis)',
    'pagada_materia' => 'Materia inscrita (pago)',
    'pagada_cross' => 'Materia no inscrita (pago)',
    'regularizacion' => 'Regularización inscripción tardía',
    'kids' => 'Kids (1 materia)',
    'kids_dual' => 'Kids (inglés + computación)',
];

const ASESORIA_ESTADOS = [
    'agendada' => 'Agendada',
    'confirmada' => 'Confirmada',
    'impartida' => 'Impartida',
    'np' => 'No presentado',
    'cancelada_a_tiempo' => 'Cancelada a tiempo',
    'reagendada' => 'Reagendada',
    'cancelada' => 'Cancelada',
];

const ASESORIA_TABULADOR_DEFAULTS = [
    'alumno_materia_externa' => ['nombre' => 'Alumno — materia externa', 'monto_alumno' => 200, 'monto_profesor' => 0],
    'alumno_reagendar_np' => ['nombre' => 'Alumno — reagendar por NP', 'monto_alumno' => 50, 'monto_profesor' => 0],
    'prof_1_alumno' => ['nombre' => 'Profesor — 1 alumno', 'monto_alumno' => 0, 'monto_profesor' => 50],
    'prof_2plus_mismo_tema' => ['nombre' => 'Profesor — 2+ alumnos mismo tema', 'monto_alumno' => 0, 'monto_profesor' => 80],
    'prof_np_sin_clase' => ['nombre' => 'Profesor — NP (presentado sin alumno)', 'monto_alumno' => 0, 'monto_profesor' => 50],
];

function asesoria_ensure_schema(PDO $pdo): void
{
    if (function_exists('hay_schema_aplicar_migraciones')) {
        hay_schema_aplicar_migraciones($pdo);
    }

    plantel_ensure_column($pdo, 'especialidades', 'asesoria_requiere_moodle', 'TINYINT(1) NOT NULL DEFAULT 1', 'activo');
    plantel_ensure_column($pdo, 'especialidades', 'asesoria_costo_default', 'DECIMAL(12,2) NULL', 'asesoria_requiere_moodle');

    if (function_exists('asesoria_tabulador_ensure_defaults')) {
        asesoria_tabulador_ensure_defaults($pdo);
    }
}

function asesoria_puede_agendar(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('asesoria_agendar')) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';

    return in_array($rol, ['admin', 'recepcion', 'caja', 'director', 'coordinador', 'coordinacion', 'supervisor'], true);
}

function asesoria_puede_administrar(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('asesoria_tabulador')) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';

    return in_array($rol, ['director', 'supervisor', 'coordinador', 'coordinacion', 'gerente'], true);
}

function asesoria_puede_autorizar_mismo_dia(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('asesoria_autorizar_mismo_dia')) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';

    return in_array($rol, ['director', 'supervisor', 'coordinador', 'coordinacion'], true);
}

function asesoria_puede_ver_calendario(): bool
{
    return asesoria_puede_agendar() || asesoria_puede_administrar()
        || (function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() === 'profesor');
}

/** Alumno elegible para asesoría (inscrito activo o baja con vigencia). */
function asesoria_alumno_elegible(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    $st = $pdo->prepare(
        'SELECT a.*, CONCAT(a.nombre, \' \', a.apellido_paterno) AS nombre_completo
         FROM alumnos a WHERE a.id_alumno = ? AND a.id_plantel = ? LIMIT 1'
    );
    $st->execute([$idAlumno, $idPlantel]);
    $a = $st->fetch(PDO::FETCH_ASSOC);
    if (!$a) {
        return ['ok' => false, 'message' => 'Alumno no encontrado en este plantel'];
    }
    $est = (string) ($a['estado'] ?? '');
    if ($est === 'graduado' || $est === 'egresado') {
        return ['ok' => false, 'message' => 'Alumnos egresados no pueden solicitar asesorías. Ofrezca curso personalizado.'];
    }
    if ($est === 'baja') {
        $hasta = $a['inscripcion_vigente_hasta'] ?? null;
        if (!$hasta || strtotime((string) $hasta) < strtotime('today')) {
            return ['ok' => false, 'message' => 'Alumno de baja sin inscripción vigente. Use personalizado o reinscripción.'];
        }
    }

    return ['ok' => true, 'alumno' => $a];
}

function asesoria_alumno_en_personalizado(PDO $pdo, int $idAlumno): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         WHERE ag.id_alumno = ? AND ag.activo = 1
           AND (g.es_personalizado = 1 OR g.clave LIKE \'PER-%\')
         LIMIT 1'
    );
    $st->execute([$idAlumno]);

    return (bool) $st->fetchColumn();
}

function asesoria_especialidad_requiere_moodle(PDO $pdo, int $idEspecialidad): bool
{
    if ($idEspecialidad <= 0) {
        return true;
    }
    $st = $pdo->prepare('SELECT asesoria_requiere_moodle FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $st->execute([$idEspecialidad]);
    $v = $st->fetchColumn();

    return $v === null || (int) $v === 1;
}

function asesoria_max_alumnos(bool $mismoTema): int
{
    return $mismoTema ? 3 : 2;
}

function asesoria_tipos_disponibles(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    $elig = asesoria_alumno_elegible($pdo, $idAlumno, $idPlantel);
    if (!$elig['ok']) {
        return [];
    }
    $enPer = asesoria_alumno_en_personalizado($pdo, $idAlumno);
    $out = [];
    if (!$enPer) {
        $out[] = 'falta_gratis';
    }
    $out[] = 'pagada_materia';
    $out[] = 'pagada_cross';
    if (function_exists('asesoria_credito_saldo') && asesoria_credito_saldo($pdo, $idAlumno, $idPlantel) > 0) {
        $out[] = 'regularizacion';
    }
    $st = $pdo->prepare(
        'SELECT 1 FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         INNER JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE ag.id_alumno = ? AND ag.activo = 1 AND e.clave IN (\'K\',\'KIDS\') LIMIT 1'
    );
    $st->execute([$idAlumno]);
    if ($st->fetchColumn()) {
        $out[] = 'kids';
        $out[] = 'kids_dual';
    }

    return array_values(array_unique($out));
}

function asesoria_cita_obtener(PDO $pdo, int $idCita, int $idPlantel): ?array
{
    asesoria_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT c.*, CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre
         FROM asesoria_cita c
         LEFT JOIN usuarios u ON u.id_usuario = c.id_profesor
         WHERE c.id_cita = ? AND c.id_plantel = ? LIMIT 1'
    );
    $st->execute([$idCita, $idPlantel]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function asesoria_cita_alumnos(PDO $pdo, int $idCita): array
{
    $st = $pdo->prepare(
        'SELECT ca.*, CONCAT(a.nombre, \' \', a.apellido_paterno) AS alumno_nombre, a.numero_control
         FROM asesoria_cita_alumno ca
         INNER JOIN alumnos a ON a.id_alumno = ca.id_alumno
         WHERE ca.id_cita = ?
         ORDER BY ca.id ASC'
    );
    $st->execute([$idCita]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function asesoria_cita_num_alumnos(PDO $pdo, int $idCita): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM asesoria_cita_alumno WHERE id_cita = ?');
    $st->execute([$idCita]);

    return (int) $st->fetchColumn();
}

function asesoria_listar(PDO $pdo, int $idPlantel, array $filtros = [], int $limite = 80): array
{
    asesoria_ensure_schema($pdo);
    $sql = 'SELECT c.*, CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre,
                   (SELECT COUNT(*) FROM asesoria_cita_alumno ca WHERE ca.id_cita = c.id_cita) AS num_alumnos
            FROM asesoria_cita c
            LEFT JOIN usuarios u ON u.id_usuario = c.id_profesor
            WHERE c.id_plantel = ?';
    $params = [$idPlantel];

    if (!empty($filtros['fecha'])) {
        $sql .= ' AND c.fecha = ?';
        $params[] = $filtros['fecha'];
    }
    if (!empty($filtros['desde']) && !empty($filtros['hasta'])) {
        $sql .= ' AND c.fecha BETWEEN ? AND ?';
        $params[] = $filtros['desde'];
        $params[] = $filtros['hasta'];
    }
    if (!empty($filtros['estado'])) {
        $sql .= ' AND c.estado = ?';
        $params[] = $filtros['estado'];
    }
    if (!empty($filtros['id_profesor'])) {
        $sql .= ' AND c.id_profesor = ?';
        $params[] = (int) $filtros['id_profesor'];
    }
    if (!empty($filtros['id_alumno'])) {
        $sql .= ' AND EXISTS (SELECT 1 FROM asesoria_cita_alumno ca WHERE ca.id_cita = c.id_cita AND ca.id_alumno = ?)';
        $params[] = (int) $filtros['id_alumno'];
    }
    if (!empty($filtros['activas'])) {
        $sql .= ' AND c.estado IN (\'agendada\',\'confirmada\')';
    }

    $sql .= ' ORDER BY c.fecha ASC, c.hora_inicio ASC LIMIT ' . max(1, min(200, $limite));
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function asesoria_horas_cancelacion_gratis(): int
{
    return 2;
}

function asesoria_fecha_permitida(string $fecha, bool $autorizadoMismoDia): bool
{
    $hoy = date('Y-m-d');
    if ($fecha < $hoy) {
        return false;
    }
    if ($fecha === $hoy && !$autorizadoMismoDia) {
        return false;
    }

    return true;
}
