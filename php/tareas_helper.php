<?php

function tareas_puede_usar(): bool
{
    return !empty($_SESSION['user_id'])
        && function_exists('rbac_rol_efectivo')
        && rbac_rol_efectivo() !== 'alumno'
        && function_exists('rbac_cap')
        && rbac_cap('menu_tareas');
}

function tareas_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS staff_tarea (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            descripcion TEXT NULL,
            fecha_limite DATE NOT NULL,
            estado ENUM('pendiente','hecha','pospuesta','cancelada') NOT NULL DEFAULT 'pendiente',
            creado_por INT UNSIGNED NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            asignado_a INT UNSIGNED NULL,
            hecha_en DATETIME NULL,
            hecha_por INT UNSIGNED NULL,
            pospuesta_hasta DATE NULL,
            notas TEXT NULL,
            PRIMARY KEY (id),
            KEY idx_staff_tarea_plantel_estado (id_plantel, estado),
            KEY idx_staff_tarea_fecha (fecha_limite),
            KEY idx_staff_tarea_asignado (asignado_a)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function tareas_fecha_valida(string $fecha): bool
{
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return $dt !== false && $dt->format('Y-m-d') === $fecha;
}

/** @return list<array<string,mixed>> */
function tareas_personal_plantel(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        "SELECT id_usuario, CONCAT_WS(' ', nombre, apellido) AS nombre, rol
         FROM usuarios
         WHERE id_plantel = ? AND rol <> 'alumno' AND COALESCE(suspendido, 0) = 0
         ORDER BY nombre, apellido"
    );
    $st->execute([$idPlantel]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string,mixed>> */
function tareas_listar(PDO $pdo, int $idPlantel, string $filtro = 'pendientes'): array
{
    $where = "t.id_plantel = ? AND t.estado IN ('pendiente','pospuesta')";
    if ($filtro === 'vencidas') {
        $where .= ' AND COALESCE(t.pospuesta_hasta, t.fecha_limite) < CURDATE()';
    } elseif ($filtro === 'hechas') {
        $where = "t.id_plantel = ? AND t.estado = 'hecha'";
    }
    $st = $pdo->prepare(
        "SELECT t.*, COALESCE(t.pospuesta_hasta, t.fecha_limite) AS fecha_efectiva,
                CONCAT_WS(' ', uc.nombre, uc.apellido) AS creado_por_nombre,
                CONCAT_WS(' ', ua.nombre, ua.apellido) AS asignado_a_nombre,
                CONCAT_WS(' ', uh.nombre, uh.apellido) AS hecha_por_nombre
         FROM staff_tarea t
         LEFT JOIN usuarios uc ON uc.id_usuario = t.creado_por
         LEFT JOIN usuarios ua ON ua.id_usuario = t.asignado_a
         LEFT JOIN usuarios uh ON uh.id_usuario = t.hecha_por
         WHERE {$where}
         ORDER BY
           CASE WHEN t.estado IN ('pendiente','pospuesta')
                     AND COALESCE(t.pospuesta_hasta, t.fecha_limite) < CURDATE() THEN 0 ELSE 1 END,
           COALESCE(t.pospuesta_hasta, t.fecha_limite),
           t.creado_en DESC"
    );
    $st->execute([$idPlantel]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array{ok:bool,message:string,id?:int} */
function tareas_crear(PDO $pdo, int $idPlantel, int $idUsuario, array $data): array
{
    $titulo = trim((string) ($data['titulo'] ?? ''));
    $descripcion = trim((string) ($data['descripcion'] ?? ''));
    $fecha = trim((string) ($data['fecha_limite'] ?? ''));
    $notas = trim((string) ($data['notas'] ?? ''));
    $asignadoA = max(0, (int) ($data['asignado_a'] ?? 0));
    if ($titulo === '' || mb_strlen($titulo) > 180 || !tareas_fecha_valida($fecha)) {
        return ['ok' => false, 'message' => 'Capture un título y una fecha límite válida'];
    }
    if ($asignadoA > 0) {
        $asignado = $pdo->prepare(
            "SELECT 1 FROM usuarios
             WHERE id_usuario = ? AND id_plantel = ? AND rol <> 'alumno' AND COALESCE(suspendido, 0) = 0"
        );
        $asignado->execute([$asignadoA, $idPlantel]);
        if (!$asignado->fetchColumn()) {
            return ['ok' => false, 'message' => 'La persona asignada no pertenece al personal del plantel'];
        }
    }
    $st = $pdo->prepare(
        'INSERT INTO staff_tarea
           (id_plantel, titulo, descripcion, fecha_limite, creado_por, asignado_a, notas)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([
        $idPlantel,
        $titulo,
        $descripcion !== '' ? $descripcion : null,
        $fecha,
        $idUsuario,
        $asignadoA > 0 ? $asignadoA : null,
        $notas !== '' ? $notas : null,
    ]);
    return ['ok' => true, 'message' => 'Tarea creada', 'id' => (int) $pdo->lastInsertId()];
}

/** @return array{ok:bool,message:string} */
function tareas_marcar_hecha(PDO $pdo, int $idPlantel, int $idTarea, int $idUsuario): array
{
    $st = $pdo->prepare(
        "UPDATE staff_tarea
         SET estado = 'hecha', hecha_en = NOW(), hecha_por = ?
         WHERE id = ? AND id_plantel = ? AND estado IN ('pendiente','pospuesta')"
    );
    $st->execute([$idUsuario, $idTarea, $idPlantel]);
    return $st->rowCount() > 0
        ? ['ok' => true, 'message' => 'Tarea marcada como hecha']
        : ['ok' => false, 'message' => 'La tarea no existe o ya fue atendida'];
}

/** @return array{ok:bool,message:string} */
function tareas_posponer(PDO $pdo, int $idPlantel, int $idTarea, string $fecha): array
{
    if (!tareas_fecha_valida($fecha) || $fecha < date('Y-m-d')) {
        return ['ok' => false, 'message' => 'Seleccione una nueva fecha válida'];
    }
    $st = $pdo->prepare(
        "UPDATE staff_tarea
         SET estado = 'pospuesta', pospuesta_hasta = ?
         WHERE id = ? AND id_plantel = ? AND estado IN ('pendiente','pospuesta')"
    );
    $st->execute([$fecha, $idTarea, $idPlantel]);
    return $st->rowCount() > 0
        ? ['ok' => true, 'message' => 'Tarea pospuesta']
        : ['ok' => false, 'message' => 'La tarea no existe o ya fue atendida'];
}
