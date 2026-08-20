<?php

function audifonos_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS audifonos_inventario (
            id_plantel INT UNSIGNED NOT NULL,
            cantidad_total INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id_plantel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS audifonos_prestamo (
            id_prestamo INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_profesor INT UNSIGNED NOT NULL,
            cantidad INT UNSIGNED NOT NULL,
            prestado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            prestado_por INT UNSIGNED NULL,
            devuelto_en DATETIME NULL,
            devuelto_por INT UNSIGNED NULL,
            cantidad_devuelta INT UNSIGNED NOT NULL DEFAULT 0,
            estado ENUM(\'prestado\',\'devuelto\',\'devuelto_parcial\',\'con_falla\') NOT NULL DEFAULT \'prestado\',
            falla_reportada TEXT NULL,
            notas TEXT NULL,
            PRIMARY KEY (id_prestamo),
            KEY idx_audifonos_plantel_estado (id_plantel, estado),
            KEY idx_audifonos_profesor (id_profesor),
            KEY idx_audifonos_prestado (prestado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function audifonos_puede_gestionar(): bool
{
    return function_exists('rbac_cap') && rbac_cap('menu_audifonos');
}

/** @return list<array<string,mixed>> */
function audifonos_profesores(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        "SELECT id_usuario, CONCAT(nombre, ' ', apellido) AS nombre
         FROM usuarios
         WHERE id_plantel = ? AND rol = 'profesor' AND COALESCE(suspendido, 0) = 0
         ORDER BY nombre, apellido"
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array{total:int,prestados:int,disponibles:int} */
function audifonos_existencias(PDO $pdo, int $idPlantel, bool $bloquear = false): array
{
    if (!$pdo->inTransaction()) {
        audifonos_ensure_schema($pdo);
    }
    $sql = 'SELECT cantidad_total FROM audifonos_inventario WHERE id_plantel = ?' . ($bloquear ? ' FOR UPDATE' : '');
    $st = $pdo->prepare($sql);
    $st->execute([$idPlantel]);
    $total = $st->fetchColumn();
    if ($total === false) {
        $pdo->prepare('INSERT IGNORE INTO audifonos_inventario (id_plantel, cantidad_total) VALUES (?,0)')
            ->execute([$idPlantel]);
        $total = 0;
        if ($bloquear) {
            $st->execute([$idPlantel]);
            $total = $st->fetchColumn();
        }
    }
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(cantidad - cantidad_devuelta), 0)
         FROM audifonos_prestamo
         WHERE id_plantel = ? AND cantidad_devuelta < cantidad'
    );
    $st->execute([$idPlantel]);
    $prestados = max(0, (int) $st->fetchColumn());

    return [
        'total' => max(0, (int) $total),
        'prestados' => $prestados,
        'disponibles' => max(0, (int) $total - $prestados),
    ];
}

/** @return list<array<string,mixed>> */
function audifonos_prestamos_activos(PDO $pdo, int $idPlantel): array
{
    audifonos_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT p.*, CONCAT(u.nombre, ' ', u.apellido) AS profesor_nombre,
                CONCAT(up.nombre, ' ', up.apellido) AS prestado_por_nombre
         FROM audifonos_prestamo p
         INNER JOIN usuarios u ON u.id_usuario = p.id_profesor
         LEFT JOIN usuarios up ON up.id_usuario = p.prestado_por
         WHERE p.id_plantel = ? AND p.cantidad_devuelta < p.cantidad
         ORDER BY p.prestado_en ASC"
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string,mixed>> */
function audifonos_historial(PDO $pdo, int $idPlantel, int $limite = 80): array
{
    audifonos_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT p.*, CONCAT(u.nombre, ' ', u.apellido) AS profesor_nombre
         FROM audifonos_prestamo p
         INNER JOIN usuarios u ON u.id_usuario = p.id_profesor
         WHERE p.id_plantel = ?
         ORDER BY p.prestado_en DESC
         LIMIT " . max(1, min(200, $limite))
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string,mixed> */
function audifonos_resumen(PDO $pdo, int $idPlantel): array
{
    return [
        'inventario' => audifonos_existencias($pdo, $idPlantel),
        'profesores' => audifonos_profesores($pdo, $idPlantel),
        'prestamos' => audifonos_prestamos_activos($pdo, $idPlantel),
        'historial' => audifonos_historial($pdo, $idPlantel),
    ];
}

/** @return array{ok:bool,message:string} */
function audifonos_actualizar_stock(PDO $pdo, int $idPlantel, int $cantidad): array
{
    if (!audifonos_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    if ($cantidad < 0) {
        return ['ok' => false, 'message' => 'La cantidad total no puede ser negativa'];
    }
    $existencias = audifonos_existencias($pdo, $idPlantel);
    if ($cantidad < $existencias['prestados']) {
        return ['ok' => false, 'message' => 'El total no puede ser menor que los audífonos prestados'];
    }
    $pdo->prepare(
        'INSERT INTO audifonos_inventario (id_plantel, cantidad_total) VALUES (?,?)
         ON DUPLICATE KEY UPDATE cantidad_total = VALUES(cantidad_total)'
    )->execute([$idPlantel, $cantidad]);

    return ['ok' => true, 'message' => 'Existencia total actualizada'];
}

/** @return array{ok:bool,message:string,id_prestamo?:int} */
function audifonos_prestar(
    PDO $pdo,
    int $idPlantel,
    int $idProfesor,
    int $cantidad,
    int $idUsuario,
    string $notas = ''
): array {
    if (!audifonos_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    audifonos_ensure_schema($pdo);
    if ($idProfesor <= 0 || $cantidad <= 0) {
        return ['ok' => false, 'message' => 'Seleccione profesor y una cantidad válida'];
    }
    $st = $pdo->prepare(
        "SELECT id_usuario FROM usuarios
         WHERE id_usuario = ? AND id_plantel = ? AND rol = 'profesor' AND COALESCE(suspendido,0) = 0"
    );
    $st->execute([$idProfesor, $idPlantel]);
    if (!$st->fetchColumn()) {
        return ['ok' => false, 'message' => 'Profesor no válido para este plantel'];
    }

    try {
        $pdo->beginTransaction();
        $existencias = audifonos_existencias($pdo, $idPlantel, true);
        if ($cantidad > $existencias['disponibles']) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'No hay suficientes audífonos disponibles'];
        }
        $pdo->prepare(
            'INSERT INTO audifonos_prestamo
             (id_plantel, id_profesor, cantidad, prestado_por, notas)
             VALUES (?,?,?,?,?)'
        )->execute([$idPlantel, $idProfesor, $cantidad, $idUsuario ?: null, trim($notas) ?: null]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();

        return ['ok' => true, 'message' => 'Préstamo registrado', 'id_prestamo' => $id];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('audifonos_prestar: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'No se pudo registrar el préstamo'];
    }
}

/** @return array{ok:bool,message:string} */
function audifonos_devolver(
    PDO $pdo,
    int $idPlantel,
    int $idPrestamo,
    int $cantidad,
    int $idUsuario,
    bool $conFalla = false,
    string $falla = '',
    string $notas = ''
): array {
    if (!audifonos_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    audifonos_ensure_schema($pdo);
    if ($idPrestamo <= 0 || $cantidad <= 0) {
        return ['ok' => false, 'message' => 'Cantidad de devolución no válida'];
    }
    if ($conFalla && trim($falla) === '') {
        return ['ok' => false, 'message' => 'Describa la falla reportada'];
    }

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare(
            'SELECT * FROM audifonos_prestamo
             WHERE id_prestamo = ? AND id_plantel = ? FOR UPDATE'
        );
        $st->execute([$idPrestamo, $idPlantel]);
        $prestamo = $st->fetch(PDO::FETCH_ASSOC);
        if (!$prestamo) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Préstamo no encontrado'];
        }
        $pendiente = max(0, (int) $prestamo['cantidad'] - (int) $prestamo['cantidad_devuelta']);
        if ($pendiente <= 0) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'El préstamo ya fue devuelto'];
        }
        if ($cantidad > $pendiente) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'La devolución supera la cantidad pendiente'];
        }

        $devuelta = (int) $prestamo['cantidad_devuelta'] + $cantidad;
        $completa = $devuelta >= (int) $prestamo['cantidad'];
        $fallaAcumulada = trim((string) ($prestamo['falla_reportada'] ?? ''));
        if ($conFalla) {
            $fallaAcumulada = trim($fallaAcumulada . ($fallaAcumulada !== '' ? "\n" : '') . trim($falla));
        }
        $estado = $fallaAcumulada !== '' ? 'con_falla' : ($completa ? 'devuelto' : 'devuelto_parcial');
        $notaAcumulada = trim((string) ($prestamo['notas'] ?? ''));
        if (trim($notas) !== '') {
            $notaAcumulada = trim($notaAcumulada . ($notaAcumulada !== '' ? "\n" : '') . trim($notas));
        }
        $pdo->prepare(
            'UPDATE audifonos_prestamo
             SET cantidad_devuelta = ?, estado = ?, devuelto_en = ?, devuelto_por = ?,
                 falla_reportada = ?, notas = ?
             WHERE id_prestamo = ?'
        )->execute([
            $devuelta,
            $estado,
            $completa ? date('Y-m-d H:i:s') : null,
            $idUsuario ?: null,
            $fallaAcumulada !== '' ? $fallaAcumulada : null,
            $notaAcumulada !== '' ? $notaAcumulada : null,
            $idPrestamo,
        ]);
        $pdo->commit();

        return [
            'ok' => true,
            'message' => $completa
                ? ($estado === 'con_falla' ? 'Devolución completa con falla registrada' : 'Devolución completa registrada')
                : 'Devolución parcial registrada; quedan ' . ((int) $prestamo['cantidad'] - $devuelta),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('audifonos_devolver: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'No se pudo registrar la devolución'];
    }
}
