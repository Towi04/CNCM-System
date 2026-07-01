<?php

/**
 * Apertura y posponimiento de grupos: autorización previa al inicio y ajuste de colegiaturas.
 */

define('GRUPO_APERTURA_DIAS_PREAVISO', 5);

function grupo_apertura_ensure_schema(PDO $pdo): void
{
    grupo_clave_ensure_schema($pdo);

    plantel_ensure_column(
        $pdo,
        'grupos',
        'estado_apertura',
        "ENUM('programado','pendiente_autorizacion','autorizado','iniciado') NOT NULL DEFAULT 'programado'",
        'fecha_inicio'
    );
    plantel_ensure_column($pdo, 'grupos', 'min_alumnos', 'SMALLINT UNSIGNED NULL', 'estado_apertura');
    plantel_ensure_column($pdo, 'grupos', 'dias_preaviso', 'TINYINT UNSIGNED NOT NULL DEFAULT 5', 'min_alumnos');
    plantel_ensure_column($pdo, 'grupos', 'id_autoriza_apertura', 'INT UNSIGNED NULL', 'dias_preaviso');
    plantel_ensure_column($pdo, 'grupos', 'autorizado_en', 'DATETIME NULL', 'id_autoriza_apertura');
    plantel_ensure_column($pdo, 'grupos', 'pospuestos', 'TINYINT UNSIGNED NOT NULL DEFAULT 0', 'autorizado_en');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_apertura_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo INT UNSIGNED NOT NULL,
            accion ENUM(\'pendiente\',\'autorizado\',\'pospuesto\') NOT NULL,
            fecha_anterior DATE NULL,
            fecha_nueva DATE NULL,
            motivo TEXT NULL,
            id_usuario INT UNSIGNED NULL,
            pagos_remapeados INT UNSIGNED NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_gal_grupo (id_grupo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function grupo_apertura_puede_gestionar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('grupo_autorizar_apertura')) {
        return true;
    }
    $rol = rbac_rol_efectivo();

    return in_array($rol, ['supervisor', 'admin', 'gerente', 'director', 'coordinador', 'coordinacion'], true)
        || (function_exists('grupo_plan_puede_editar') && grupo_plan_puede_editar());
}

/** @return array<string, mixed>|null */
function grupo_apertura_obtener(PDO $pdo, int $idGrupo, ?int $idPlantel = null): ?array
{
    grupo_apertura_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    $st = $pdo->prepare(
        'SELECT g.*, e.nombre AS especialidad_nombre,
                (SELECT COUNT(*) FROM alumno_grupos ag
                 INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.id_plantel = g.id_plantel
                 WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1) AS total_alumnos
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_grupo = ? AND g.id_plantel = ?
         LIMIT 1'
    );
    $st->execute([$idGrupo, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function grupo_apertura_etiqueta_estado(string $estado): string
{
    return match ($estado) {
        'pendiente_autorizacion' => 'Pendiente de autorización',
        'autorizado' => 'Autorizado para abrir',
        'iniciado' => 'En curso',
        default => 'Programado',
    };
}

function grupo_apertura_sync_estados(PDO $pdo, ?int $idPlantel = null): void
{
    grupo_apertura_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    $hoy = date('Y-m-d');

    $pdo->prepare(
        "UPDATE grupos SET estado_apertura = 'iniciado'
         WHERE id_plantel = ? AND fecha_inicio <= ? AND estado_apertura IN ('programado','pendiente_autorizacion','autorizado')"
    )->execute([$idPlantel, $hoy]);

    $st = $pdo->prepare(
        "SELECT id_grupo, fecha_inicio, dias_preaviso, estado_apertura
         FROM grupos
         WHERE id_plantel = ? AND estado_apertura IN ('programado','pendiente_autorizacion')
           AND fecha_inicio > ?"
    );
    $st->execute([$idPlantel, $hoy]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $idG = (int) $g['id_grupo'];
        $dias = max(1, (int) ($g['dias_preaviso'] ?? GRUPO_APERTURA_DIAS_PREAVISO));
        $limite = date('Y-m-d', strtotime($g['fecha_inicio'] . " -{$dias} days"));
        if ($hoy >= $limite && ($g['estado_apertura'] ?? '') === 'programado') {
            $pdo->prepare("UPDATE grupos SET estado_apertura = 'pendiente_autorizacion' WHERE id_grupo = ?")
                ->execute([$idG]);
            $pdo->prepare(
                'INSERT INTO grupo_apertura_log (id_grupo, accion, fecha_anterior, motivo)
                 VALUES (?, \'pendiente\', ?, ?)'
            )->execute([
                $idG,
                $g['fecha_inicio'],
                'Entró en ventana de autorización (' . $dias . ' días antes del inicio)',
            ]);
        }
    }
}

/** @return list<array<string, mixed>> */
function grupo_apertura_listar_pendientes(PDO $pdo, ?int $idPlantel = null): array
{
    grupo_apertura_sync_estados($pdo, $idPlantel);
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    $st = $pdo->prepare(
        "SELECT g.id_grupo, g.clave, g.fecha_inicio, g.estado_apertura, g.min_alumnos, g.pospuestos,
                g.codigo_horario, g.id_especialidad, e.nombre AS especialidad_nombre,
                (SELECT COUNT(*) FROM alumno_grupos ag
                 INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.id_plantel = g.id_plantel
                 WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1) AS total_alumnos
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_plantel = ?
           AND g.estado_apertura IN ('programado','pendiente_autorizacion')
           AND g.fecha_inicio >= CURDATE()
         ORDER BY g.fecha_inicio ASC, g.clave ASC"
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function grupo_apertura_cumple_minimo(array $grupo): bool
{
    $min = (int) ($grupo['min_alumnos'] ?? 0);
    if ($min <= 0) {
        return true;
    }

    return (int) ($grupo['total_alumnos'] ?? 0) >= $min;
}

/** @return array{ok: bool, message: string} */
function grupo_apertura_autorizar(PDO $pdo, int $idGrupo, int $idUsuario, ?int $idPlantel = null): array
{
    if (!grupo_apertura_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso para autorizar apertura'];
    }
    grupo_apertura_ensure_schema($pdo);
    $g = grupo_apertura_obtener($pdo, $idGrupo, $idPlantel);
    if (!$g) {
        return ['ok' => false, 'message' => 'Grupo no encontrado'];
    }
    if (in_array($g['estado_apertura'] ?? '', ['autorizado', 'iniciado'], true)) {
        return ['ok' => false, 'message' => 'El grupo ya está autorizado o en curso'];
    }
    if (!grupo_apertura_cumple_minimo($g)) {
        $min = (int) ($g['min_alumnos'] ?? 0);
        $tot = (int) ($g['total_alumnos'] ?? 0);

        return [
            'ok' => false,
            'message' => "No alcanza el mínimo de alumnos ({$tot}/{$min}). Posponer o esperar más inscripciones.",
        ];
    }

    $pdo->prepare(
        "UPDATE grupos SET estado_apertura = 'autorizado', id_autoriza_apertura = ?, autorizado_en = NOW()
         WHERE id_grupo = ?"
    )->execute([$idUsuario > 0 ? $idUsuario : null, $idGrupo]);

    $pdo->prepare(
        'INSERT INTO grupo_apertura_log (id_grupo, accion, fecha_anterior, id_usuario, motivo)
         VALUES (?, \'autorizado\', ?, ?, ?)'
    )->execute([
        $idGrupo,
        $g['fecha_inicio'],
        $idUsuario > 0 ? $idUsuario : null,
        'Apertura autorizada',
    ]);

    return ['ok' => true, 'message' => 'Grupo autorizado para iniciar el ' . date('d/m/Y', strtotime($g['fecha_inicio']))];
}

/**
 * Posponer grupo: nueva fecha de inicio y remapeo de colegiaturas ya pagadas.
 * @return array{ok: bool, message: string, pagos_remapeados?: int}
 */
function grupo_apertura_posponer(
    PDO $pdo,
    int $idGrupo,
    string $nuevaFecha,
    string $motivo,
    int $idUsuario,
    ?int $idPlantel = null
): array {
    if (!grupo_apertura_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso para posponer grupos'];
    }
    grupo_apertura_ensure_schema($pdo);
    $nuevaFecha = trim($nuevaFecha);
    if ($nuevaFecha === '' || strtotime($nuevaFecha) === false) {
        return ['ok' => false, 'message' => 'Fecha de inicio inválida'];
    }
    $nuevaFecha = date('Y-m-d', strtotime($nuevaFecha));
    $g = grupo_apertura_obtener($pdo, $idGrupo, $idPlantel);
    if (!$g) {
        return ['ok' => false, 'message' => 'Grupo no encontrado'];
    }
    if (($g['estado_apertura'] ?? '') === 'iniciado') {
        return ['ok' => false, 'message' => 'No se puede posponer un grupo que ya inició'];
    }
    $fechaAnterior = (string) $g['fecha_inicio'];
    if ($nuevaFecha <= $fechaAnterior) {
        return ['ok' => false, 'message' => 'La nueva fecha debe ser posterior a la actual (' . date('d/m/Y', strtotime($fechaAnterior)) . ')'];
    }

    $remap = pago_remap_colegiaturas_por_pospon_grupo($pdo, $idGrupo, $fechaAnterior, $nuevaFecha);

    $pdo->prepare(
        "UPDATE grupos SET fecha_inicio = ?, estado_apertura = 'programado',
         id_autoriza_apertura = NULL, autorizado_en = NULL, pospuestos = pospuestos + 1
         WHERE id_grupo = ?"
    )->execute([$nuevaFecha, $idGrupo]);

    $motivo = trim($motivo) ?: 'Grupo pospuesto';
    $pdo->prepare(
        'INSERT INTO grupo_apertura_log (id_grupo, accion, fecha_anterior, fecha_nueva, motivo, id_usuario, pagos_remapeados)
         VALUES (?, \'pospuesto\', ?, ?, ?, ?, ?)'
    )->execute([
        $idGrupo,
        $fechaAnterior,
        $nuevaFecha,
        $motivo,
        $idUsuario > 0 ? $idUsuario : null,
        (int) ($remap['remap_count'] ?? 0),
    ]);

    grupo_apertura_notificar_alumnos($pdo, $idGrupo, $fechaAnterior, $nuevaFecha, $motivo);

    $msg = 'Grupo pospuesto al ' . date('d/m/Y', strtotime($nuevaFecha));
    if ((int) ($remap['remap_count'] ?? 0) > 0) {
        $msg .= '. Se ajustaron ' . (int) $remap['remap_count'] . ' pago(s) de colegiatura al nuevo periodo.';
    }

    return ['ok' => true, 'message' => $msg, 'pagos_remapeados' => (int) ($remap['remap_count'] ?? 0)];
}

function grupo_apertura_notificar_alumnos(
    PDO $pdo,
    int $idGrupo,
    string $fechaAnterior,
    string $fechaNueva,
    string $motivo
): void {
    if (!function_exists('academico_notificar_usuario')) {
        return;
    }
    $st = $pdo->prepare(
        'SELECT a.id_usuario, a.nombre, a.apellido
         FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.id_usuario IS NOT NULL'
    );
    $st->execute([$idGrupo]);
    $g = $pdo->prepare('SELECT clave FROM grupos WHERE id_grupo = ?');
    $g->execute([$idGrupo]);
    $clave = (string) ($g->fetchColumn() ?: 'grupo');
    $txt = 'El grupo ' . $clave . ' se pospuso del '
        . date('d/m/Y', strtotime($fechaAnterior)) . ' al '
        . date('d/m/Y', strtotime($fechaNueva)) . '. ' . $motivo
        . ' Las colegiaturas anticipadas se aplican al nuevo periodo de inicio.';

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $al) {
        $idU = (int) ($al['id_usuario'] ?? 0);
        if ($idU > 0) {
            academico_notificar_usuario($pdo, $idU, 'Grupo pospuesto', $txt, 'grupos');
        }
    }
}

function grupo_apertura_inicializar(PDO $pdo, int $idGrupo, ?int $minAlumnos = null): void
{
    grupo_apertura_ensure_schema($pdo);
    $sql = "UPDATE grupos SET estado_apertura = 'programado', dias_preaviso = ?";
    $params = [GRUPO_APERTURA_DIAS_PREAVISO];
    if ($minAlumnos !== null && $minAlumnos > 0) {
        $sql .= ', min_alumnos = ?';
        $params[] = $minAlumnos;
    }
    $sql .= ' WHERE id_grupo = ?';
    $params[] = $idGrupo;
    $pdo->prepare($sql)->execute($params);
}
