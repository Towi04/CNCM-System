<?php

/**
 * Solicitudes de cambio de plantel de alumnos, autorizadas por el plantel destino.
 */

function alumno_plantel_transfer_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_cambio_plantel (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_plantel_origen INT UNSIGNED NOT NULL,
            id_plantel_destino INT UNSIGNED NOT NULL,
            estado ENUM(\'pendiente\',\'autorizado\',\'rechazado\',\'cancelado\') NOT NULL DEFAULT \'pendiente\',
            solicitado_por INT UNSIGNED NOT NULL,
            solicitado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            motivo TEXT NULL,
            autorizado_por INT UNSIGNED NULL,
            autorizado_en DATETIME NULL,
            notas_destino TEXT NULL,
            migrado_en DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_acp_destino_estado (id_plantel_destino, estado),
            KEY idx_acp_origen_estado (id_plantel_origen, estado),
            KEY idx_acp_alumno (id_alumno, solicitado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function alumno_plantel_transfer_puede_solicitar(): bool
{
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';
    if ($rol === 'supervisor') {
        return !function_exists('rbac_cap') || rbac_cap('menu_alumno_cambio_plantel');
    }
    if ($rol !== 'director') {
        return false;
    }
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }
    $idHome = function_exists('plantel_usuario_home_id') ? (int) plantel_usuario_home_id($pdo) : 0;
    $idActivo = function_exists('plantel_scope_id') ? (int) plantel_scope_id($pdo) : 0;
    if ($idHome <= 0 || $idHome !== $idActivo) {
        return false;
    }

    return !function_exists('rbac_cap') || rbac_cap('menu_alumno_cambio_plantel');
}

function alumno_plantel_transfer_puede_autorizar(int $idPlantelDestino): bool
{
    if ($idPlantelDestino <= 0) {
        return false;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';
    if ($rol === 'supervisor') {
        return !function_exists('rbac_cap') || rbac_cap('menu_alumno_cambio_plantel');
    }
    if ($rol !== 'director' || (function_exists('rbac_cap') && !rbac_cap('menu_alumno_cambio_plantel'))) {
        return false;
    }

    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }
    $idHome = function_exists('plantel_usuario_home_id') ? (int) plantel_usuario_home_id($pdo) : 0;

    return $idHome > 0 && $idHome === $idPlantelDestino;
}

/** @return list<array<string, mixed>> */
function alumno_plantel_transfer_planteles_destino(PDO $pdo, int $idOrigen): array
{
    $st = $pdo->prepare(
        'SELECT id_plantel, nombre, slug
         FROM planteles
         WHERE activo = 1 AND id_plantel <> ?
         ORDER BY orden, nombre'
    );
    $st->execute([$idOrigen]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string, mixed>> */
function alumno_plantel_transfer_buscar_alumnos(PDO $pdo, int $idPlantelOrigen, string $control): array
{
    if (!alumno_plantel_transfer_puede_solicitar() || $idPlantelOrigen <= 0) {
        return [];
    }
    $control = trim($control);
    if ($control === '') {
        return [];
    }
    $like = '%' . $control . '%';
    $st = $pdo->prepare(
        'SELECT a.id_alumno, a.numero_control, a.matricula,
                TRIM(CONCAT(COALESCE(NULLIF(a.nombres, \'\'), a.nombre, \'\'), \' \',
                            COALESCE(a.apellido_paterno, a.apellido, \'\'), \' \',
                            COALESCE(a.apellido_materno, \'\'))) AS alumno,
                a.estado
         FROM alumnos a
         WHERE a.id_plantel = ?
           AND (a.numero_control = ? OR a.matricula = ? OR a.numero_control LIKE ? OR a.matricula LIKE ?)
         ORDER BY (a.numero_control = ?) DESC, alumno
         LIMIT 20'
    );
    $st->execute([$idPlantelOrigen, $control, $control, $like, $like, $control]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{ok:bool,message:string,id?:int} */
function alumno_plantel_transfer_solicitar(
    PDO $pdo,
    int $idAlumno,
    int $idPlantelOrigen,
    int $idPlantelDestino,
    string $motivo,
    int $idUsuario
): array {
    if (!alumno_plantel_transfer_puede_solicitar()) {
        return ['ok' => false, 'message' => 'Sin permiso para solicitar cambios de plantel.'];
    }
    if ($idAlumno <= 0 || $idPlantelOrigen <= 0 || $idPlantelDestino <= 0 || $idPlantelOrigen === $idPlantelDestino) {
        return ['ok' => false, 'message' => 'Alumno o plantel destino no válido.'];
    }
    $motivo = trim($motivo);
    if ($motivo === '') {
        return ['ok' => false, 'message' => 'Indique el motivo del cambio de plantel.'];
    }
    $st = $pdo->prepare('SELECT id_plantel FROM alumnos WHERE id_alumno = ? LIMIT 1');
    $st->execute([$idAlumno]);
    if ((int) $st->fetchColumn() !== $idPlantelOrigen) {
        return ['ok' => false, 'message' => 'El alumno ya no pertenece al plantel origen.'];
    }
    $st = $pdo->prepare('SELECT 1 FROM planteles WHERE id_plantel = ? AND activo = 1 LIMIT 1');
    $st->execute([$idPlantelDestino]);
    if (!$st->fetchColumn()) {
        return ['ok' => false, 'message' => 'El plantel destino no existe o está inactivo.'];
    }

    alumno_plantel_transfer_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT id FROM alumno_cambio_plantel
         WHERE id_alumno = ? AND estado = \'pendiente\' LIMIT 1'
    );
    $st->execute([$idAlumno]);
    if ($st->fetchColumn()) {
        return ['ok' => false, 'message' => 'El alumno ya tiene una solicitud pendiente.'];
    }

    $pdo->prepare(
        'INSERT INTO alumno_cambio_plantel (
            id_alumno, id_plantel_origen, id_plantel_destino, solicitado_por, motivo
         ) VALUES (?, ?, ?, ?, ?)'
    )->execute([$idAlumno, $idPlantelOrigen, $idPlantelDestino, $idUsuario, $motivo]);

    return ['ok' => true, 'message' => 'Solicitud enviada al director del plantel destino.', 'id' => (int) $pdo->lastInsertId()];
}

/** @return list<array<string, mixed>> */
function alumno_plantel_transfer_listar(PDO $pdo, int $idPlantel, string $tipo = 'historial'): array
{
    alumno_plantel_transfer_ensure_schema($pdo);
    $params = [];
    $where = '';
    if ($tipo === 'entrantes') {
        $where = 't.id_plantel_destino = ? AND t.estado = \'pendiente\'';
        $params[] = $idPlantel;
    } else {
        $where = '(t.id_plantel_origen = ? OR t.id_plantel_destino = ?)';
        $params[] = $idPlantel;
        $params[] = $idPlantel;
    }
    $st = $pdo->prepare(
        'SELECT t.*, po.nombre AS plantel_origen, pd.nombre AS plantel_destino,
                a.numero_control,
                TRIM(CONCAT(COALESCE(NULLIF(a.nombres, \'\'), a.nombre, \'\'), \' \',
                            COALESCE(a.apellido_paterno, a.apellido, \'\'), \' \',
                            COALESCE(a.apellido_materno, \'\'))) AS alumno,
                TRIM(CONCAT(COALESCE(us.nombre, \'\'), \' \', COALESCE(us.apellido, \'\'))) AS solicitado_por_nombre,
                TRIM(CONCAT(COALESCE(ua.nombre, \'\'), \' \', COALESCE(ua.apellido, \'\'))) AS autorizado_por_nombre
         FROM alumno_cambio_plantel t
         INNER JOIN alumnos a ON a.id_alumno = t.id_alumno
         INNER JOIN planteles po ON po.id_plantel = t.id_plantel_origen
         INNER JOIN planteles pd ON pd.id_plantel = t.id_plantel_destino
         LEFT JOIN usuarios us ON us.id_usuario = t.solicitado_por
         LEFT JOIN usuarios ua ON ua.id_usuario = t.autorizado_por
         WHERE ' . $where . '
         ORDER BY (t.estado = \'pendiente\') DESC, t.solicitado_en DESC
         LIMIT 200'
    );
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function alumno_plantel_transfer_actualizar_tabla_si_aplica(
    PDO $pdo,
    string $tabla,
    string $columnaAlumno,
    int $idAlumno,
    int $idDestino
): void {
    if (!plantel_table_exists($pdo, $tabla)
        || !plantel_column_exists($pdo, $tabla, $columnaAlumno)
        || !plantel_column_exists($pdo, $tabla, 'id_plantel')) {
        return;
    }
    $pdo->prepare("UPDATE `$tabla` SET id_plantel = ? WHERE `$columnaAlumno` = ?")
        ->execute([$idDestino, $idAlumno]);
}

/** @return array{ok:bool,message:string} */
function alumno_plantel_transfer_ejecutar(
    PDO $pdo,
    int $idSolicitud,
    int $idUsuario,
    string $notasDestino = ''
): array {
    alumno_plantel_transfer_ensure_schema($pdo);
    if ($idSolicitud <= 0) {
        return ['ok' => false, 'message' => 'Solicitud no válida.'];
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM alumno_cambio_plantel WHERE id = ? FOR UPDATE');
        $st->execute([$idSolicitud]);
        $solicitud = $st->fetch(PDO::FETCH_ASSOC);
        if (!$solicitud || $solicitud['estado'] !== 'pendiente') {
            throw new RuntimeException('La solicitud ya fue resuelta o no existe.');
        }
        $idDestino = (int) $solicitud['id_plantel_destino'];
        $idOrigen = (int) $solicitud['id_plantel_origen'];
        $idAlumno = (int) $solicitud['id_alumno'];
        if (!alumno_plantel_transfer_puede_autorizar($idDestino)) {
            throw new RuntimeException('Solo el director del plantel destino puede autorizar.');
        }

        $selectUsuario = plantel_column_exists($pdo, 'alumnos', 'id_usuario') ? ', id_usuario' : '';
        $st = $pdo->prepare("SELECT id_plantel$selectUsuario FROM alumnos WHERE id_alumno = ? FOR UPDATE");
        $st->execute([$idAlumno]);
        $alumno = $st->fetch(PDO::FETCH_ASSOC);
        if (!$alumno || (int) $alumno['id_plantel'] !== $idOrigen) {
            throw new RuntimeException('El alumno ya no pertenece al plantel origen.');
        }

        $setGrupo = plantel_column_exists($pdo, 'alumnos', 'id_grupo') ? ', id_grupo = NULL' : '';
        $pdo->prepare("UPDATE alumnos SET id_plantel = ?$setGrupo WHERE id_alumno = ?")
            ->execute([$idDestino, $idAlumno]);

        alumno_plantel_transfer_actualizar_tabla_si_aplica($pdo, 'alumno_pagos', 'id_alumno', $idAlumno, $idDestino);
        alumno_plantel_transfer_actualizar_tabla_si_aplica($pdo, 'alumno_documento', 'id_alumno', $idAlumno, $idDestino);
        alumno_plantel_transfer_actualizar_tabla_si_aplica($pdo, 'preregistros', 'id_alumno_vinculado', $idAlumno, $idDestino);
        alumno_plantel_transfer_actualizar_tabla_si_aplica($pdo, 'alumno_huellas', 'id_alumno', $idAlumno, $idDestino);

        if (plantel_table_exists($pdo, 'alumno_grupos')) {
            $pdo->prepare(
                'UPDATE alumno_grupos SET activo = 0, fecha_baja = COALESCE(fecha_baja, CURDATE())
                 WHERE id_alumno = ? AND activo = 1'
            )->execute([$idAlumno]);
        }

        $idUsuarioAlumno = (int) ($alumno['id_usuario'] ?? 0);
        if (plantel_table_exists($pdo, 'usuarios') && plantel_column_exists($pdo, 'usuarios', 'id_plantel')) {
            $condiciones = [];
            $params = [$idDestino];
            if ($idUsuarioAlumno > 0) {
                $condiciones[] = 'id_usuario = ?';
                $params[] = $idUsuarioAlumno;
            }
            if (plantel_column_exists($pdo, 'usuarios', 'id_alumno')) {
                $condiciones[] = 'id_alumno = ?';
                $params[] = $idAlumno;
            }
            if ($condiciones !== []) {
                $pdo->prepare('UPDATE usuarios SET id_plantel = ? WHERE ' . implode(' OR ', $condiciones))
                    ->execute($params);
            }
        }
        if ($idUsuarioAlumno > 0) {
            alumno_plantel_transfer_actualizar_tabla_si_aplica(
                $pdo,
                'usuario_huellas',
                'id_usuario',
                $idUsuarioAlumno,
                $idDestino
            );
        }

        $pdo->prepare(
            'UPDATE alumno_cambio_plantel
             SET estado = \'autorizado\', autorizado_por = ?, autorizado_en = NOW(),
                 notas_destino = ?, migrado_en = NOW()
             WHERE id = ?'
        )->execute([$idUsuario, trim($notasDestino) ?: null, $idSolicitud]);

        $pdo->commit();

        return ['ok' => true, 'message' => 'Cambio autorizado. El alumno debe inscribirse en grupos del plantel destino.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('alumno_plantel_transfer_ejecutar: ' . $e->getMessage());

        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

/** @return array{ok:bool,message:string} */
function alumno_plantel_transfer_rechazar(
    PDO $pdo,
    int $idSolicitud,
    int $idUsuario,
    string $notasDestino
): array {
    alumno_plantel_transfer_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM alumno_cambio_plantel WHERE id = ? LIMIT 1');
    $st->execute([$idSolicitud]);
    $solicitud = $st->fetch(PDO::FETCH_ASSOC);
    if (!$solicitud || $solicitud['estado'] !== 'pendiente') {
        return ['ok' => false, 'message' => 'La solicitud ya fue resuelta o no existe.'];
    }
    if (!alumno_plantel_transfer_puede_autorizar((int) $solicitud['id_plantel_destino'])) {
        return ['ok' => false, 'message' => 'Sin permiso para resolver esta solicitud.'];
    }
    $up = $pdo->prepare(
        'UPDATE alumno_cambio_plantel
         SET estado = \'rechazado\', autorizado_por = ?, autorizado_en = NOW(), notas_destino = ?
         WHERE id = ? AND estado = \'pendiente\''
    );
    $up->execute([$idUsuario, trim($notasDestino) ?: null, $idSolicitud]);

    return $up->rowCount() > 0
        ? ['ok' => true, 'message' => 'Solicitud rechazada.']
        : ['ok' => false, 'message' => 'La solicitud ya fue resuelta.'];
}

/** @return array{ok:bool,message:string} */
function alumno_plantel_transfer_cancelar(PDO $pdo, int $idSolicitud): array
{
    if (!alumno_plantel_transfer_puede_solicitar()) {
        return ['ok' => false, 'message' => 'Sin permiso.'];
    }
    $idOrigen = function_exists('plantel_scope_id') ? (int) plantel_scope_id($pdo) : 0;
    $st = $pdo->prepare(
        'UPDATE alumno_cambio_plantel
         SET estado = \'cancelado\'
         WHERE id = ? AND id_plantel_origen = ? AND estado = \'pendiente\''
    );
    $st->execute([$idSolicitud, $idOrigen]);

    return $st->rowCount() > 0
        ? ['ok' => true, 'message' => 'Solicitud cancelada.']
        : ['ok' => false, 'message' => 'No se pudo cancelar la solicitud.'];
}
