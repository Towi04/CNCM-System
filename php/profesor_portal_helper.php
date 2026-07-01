<?php

/**
 * Portal del profesor: horario, permisos, documentos.
 */

function profesor_portal_ensure_schema(PDO $pdo): void
{
    academico_ensure_schema($pdo);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS profesor_permiso_solicitud (
            id_solicitud INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NOT NULL,
            motivo TEXT NOT NULL,
            estado ENUM(\'pendiente\',\'aprobado\',\'rechazado\') NOT NULL DEFAULT \'pendiente\',
            revisado_por INT UNSIGNED NULL,
            comentario_revision VARCHAR(500) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_solicitud),
            KEY idx_perm_prof (id_usuario, estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function profesor_portal_es_profesor(): bool
{
    return rbac_rol_efectivo() === 'profesor';
}

/** @return list<array<string, mixed>> */
function profesor_portal_grupos(PDO $pdo, int $idProfesor, ?int $idPlantel = null): array
{
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT g.*, e.nombre AS esp_nombre, e.clave AS esp_clave,
                f.clave_fase, f.nombre_fase,
                pa.codigo AS aula_codigo, pa.nombre AS aula_nombre, pa.piso AS aula_piso,
                pa.tipo_aula, pa.capacidad AS aula_capacidad
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual
         LEFT JOIN plantel_aulas pa ON pa.id_aula = g.id_aula
         WHERE g.id_plantel = ? AND ' . grupo_docente_sql_filtro_profesor('g') . '
         ORDER BY g.fecha_inicio DESC, g.clave ASC'
    );
    $st->execute([$idPlantel, $idProfesor, $idProfesor]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $tipos = aula_tipos();
    foreach ($rows as &$r) {
        $r['posicion'] = academico_posicion_grupo($pdo, $r);
        if (!empty($r['aula_codigo'])) {
            $r['aula_label'] = $r['aula_codigo'];
            if (!empty($r['aula_nombre'])) {
                $r['aula_label'] .= ' — ' . $r['aula_nombre'];
            }
            if (!empty($r['aula_piso'])) {
                $r['aula_label'] .= ' (' . $r['aula_piso'] . ')';
            }
            $r['aula_tipo_label'] = $tipos[$r['tipo_aula'] ?? 'aula'] ?? '';
        } elseif (!empty($r['aula'])) {
            $r['aula_label'] = (string) $r['aula'];
            $r['aula_tipo_label'] = '';
        } else {
            $r['aula_label'] = '';
            $r['aula_tipo_label'] = '';
        }
    }
    unset($r);
    return $rows;
}

function profesor_portal_crear_permiso(
    PDO $pdo,
    int $idUsuario,
    string $fechaInicio,
    string $fechaFin,
    string $motivo
): array {
    $motivo = trim($motivo);
    if ($motivo === '') {
        return ['ok' => false, 'message' => 'Indique el motivo'];
    }
    try {
        $ini = new DateTimeImmutable($fechaInicio);
        $fin = new DateTimeImmutable($fechaFin);
    } catch (Exception $e) {
        return ['ok' => false, 'message' => 'Fechas inválidas'];
    }
    if ($fin < $ini) {
        return ['ok' => false, 'message' => 'La fecha fin debe ser posterior al inicio'];
    }

    $pdo->prepare(
        'INSERT INTO profesor_permiso_solicitud (id_usuario, id_plantel, fecha_inicio, fecha_fin, motivo)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$idUsuario, plantel_id_activo(), $ini->format('Y-m-d'), $fin->format('Y-m-d'), $motivo]);

    profesor_portal_notificar_revision($pdo, $idUsuario, $ini, $fin);

    return ['ok' => true, 'message' => 'Solicitud enviada a coordinación'];
}

function profesor_portal_notificar_revision(
    PDO $pdo,
    int $idProfesor,
    DateTimeInterface $ini,
    DateTimeInterface $fin
): void {
    $u = $pdo->prepare('SELECT nombre, apellido FROM usuarios WHERE id_usuario = ?');
    $u->execute([$idProfesor]);
    $prof = $u->fetch(PDO::FETCH_ASSOC);
    $nombre = trim(($prof['nombre'] ?? '') . ' ' . ($prof['apellido'] ?? ''));

    $st = $pdo->prepare(
        "SELECT id_usuario FROM usuarios
         WHERE suspendido = 0 AND rol IN ('coordinador', 'coordinacion', 'director', 'gerente', 'supervisor', 'admin')
           AND (id_plantel IS NULL OR id_plantel = ?)"
    );
    $st->execute([plantel_id_activo()]);
    $msg = $nombre . ' solicita permiso del ' . $ini->format('d/m/Y') . ' al ' . $fin->format('d/m/Y') . '.';
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        academico_notificar_usuario(
            $pdo,
            (int) $uid,
            'permiso_profesor',
            'Permiso de profesor',
            $msg,
            'bandeja_aprobaciones',
            null
        );
    }
}

/** @return list<array<string, mixed>> */
function profesor_portal_mis_permisos(PDO $pdo, int $idUsuario): array
{
    $st = $pdo->prepare(
        'SELECT * FROM profesor_permiso_solicitud WHERE id_usuario = ? ORDER BY creado_en DESC LIMIT 20'
    );
    $st->execute([$idUsuario]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function profesor_portal_puede_revisar_permisos(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('permiso_docente_aprobar_final')) {
        return true;
    }
    $rol = rbac_rol_efectivo();

    return in_array($rol, ['coordinador', 'coordinacion', 'director', 'supervisor', 'gerente', 'admin'], true);
}

/** @return list<array<string, mixed>> */
function profesor_portal_permisos_pendientes(PDO $pdo, ?int $idPlantel = null): array
{
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        "SELECT s.*, u.nombre, u.apellido
         FROM profesor_permiso_solicitud s
         INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
         WHERE s.id_plantel = ? AND s.estado = 'pendiente'
         ORDER BY s.fecha_inicio ASC"
    );
    $st->execute([$idPlantel]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function profesor_portal_resolver_permiso(
    PDO $pdo,
    int $idSolicitud,
    string $estado,
    string $comentario,
    int $idRevisor
): array {
    if (!in_array($estado, ['aprobado', 'rechazado'], true)) {
        return ['ok' => false, 'message' => 'Estado inválido'];
    }
    $pdo->prepare(
        'UPDATE profesor_permiso_solicitud SET estado = ?, revisado_por = ?, comentario_revision = ? WHERE id_solicitud = ?'
    )->execute([$estado, $idRevisor, trim($comentario) ?: null, $idSolicitud]);

    $st = $pdo->prepare('SELECT id_usuario FROM profesor_permiso_solicitud WHERE id_solicitud = ?');
    $st->execute([$idSolicitud]);
    $uid = (int) $st->fetchColumn();
    if ($uid > 0) {
        academico_notificar_usuario(
            $pdo,
            $uid,
            'permiso_resuelto',
            'Solicitud de permiso ' . $estado,
            'Su solicitud fue ' . $estado . '.' . ($comentario !== '' ? ' ' . $comentario : ''),
            'profesor_portal',
            null
        );
    }

    return ['ok' => true, 'message' => 'Solicitud actualizada'];
}
