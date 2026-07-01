<?php

/**
 * Catálogo: especialidades, productos e inventario por plantel.
 */

function catalog_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS especialidades (
            id_especialidad INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clave VARCHAR(30) NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            descripcion TEXT NULL,
            costo_inscripcion DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            costo_mensualidad DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            costo_pronto_pago DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            costo_semanal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            duracion_meses SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            duracion_semanas SMALLINT UNSIGNED NULL,
            es_fija TINYINT(1) NOT NULL DEFAULT 0,
            visible TINYINT(1) NOT NULL DEFAULT 1,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_especialidad),
            UNIQUE KEY uq_especialidades_clave (clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS productos (
            id_producto INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clave VARCHAR(40) NOT NULL,
            nombre VARCHAR(160) NOT NULL,
            descripcion TEXT NULL,
            precio DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            clave_sat VARCHAR(20) NOT NULL DEFAULT \'01010101\',
            unidad_sat VARCHAR(10) NOT NULL DEFAULT \'H87\',
            gratis_profesor TINYINT(1) NOT NULL DEFAULT 0,
            visible TINYINT(1) NOT NULL DEFAULT 1,
            descontinuado TINYINT(1) NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            stock_minimo INT UNSIGNED NOT NULL DEFAULT 5,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_producto),
            UNIQUE KEY uq_productos_clave (clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS producto_inventario (
            id_inventario INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_producto INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            existencia INT NOT NULL DEFAULT 0,
            stock_minimo INT UNSIGNED NULL,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_inventario),
            UNIQUE KEY uq_inv_producto_plantel (id_producto, id_plantel),
            KEY idx_inv_plantel (id_plantel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS producto_movimientos (
            id_movimiento INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_producto INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            tipo ENUM(\'entrada\',\'merma\',\'ajuste\',\'salida\') NOT NULL,
            cantidad INT UNSIGNED NOT NULL,
            notas TEXT NULL,
            estado ENUM(\'pendiente\',\'aplicado\',\'cancelado\') NOT NULL DEFAULT \'pendiente\',
            id_usuario_registro INT UNSIGNED NULL,
            id_usuario_confirma INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmado_en DATETIME NULL,
            PRIMARY KEY (id_movimiento),
            KEY idx_mov_plantel_estado (id_plantel, estado),
            KEY idx_mov_producto (id_producto)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    catalog_seed_especialidades($pdo);
    catalog_ensure_especialidad_operativo($pdo);
    catalog_ensure_producto_operativo($pdo);
}

function catalog_ensure_producto_operativo(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }
    plantel_ensure_column(
        $pdo,
        'productos',
        'controla_inventario',
        'TINYINT(1) NOT NULL DEFAULT 1 COMMENT \'0=sin control de existencia (servicios)\'',
        'stock_minimo'
    );
}

/** Campos operativos CNCM: modalidad, edades, cuatrimestre. */
function catalog_ensure_especialidad_operativo(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }
    plantel_ensure_column($pdo, 'especialidades', 'modalidad', "ENUM('regular','kids','prep_abierta','prep_escolarizada','extensivo') NOT NULL DEFAULT 'regular'", 'descripcion');
    plantel_ensure_column($pdo, 'especialidades', 'duracion_fase_semanas', 'SMALLINT UNSIGNED NOT NULL DEFAULT 4', 'modalidad');
    plantel_ensure_column($pdo, 'especialidades', 'inscripcion_por_cuatrimestre', 'TINYINT(1) NOT NULL DEFAULT 0', 'duracion_fase_semanas');
    plantel_ensure_column($pdo, 'especialidades', 'parciales_por_cuatrimestre', 'TINYINT UNSIGNED NOT NULL DEFAULT 0', 'inscripcion_por_cuatrimestre');
    plantel_ensure_column($pdo, 'especialidades', 'edad_min', 'TINYINT UNSIGNED NULL', 'parciales_por_cuatrimestre');
    plantel_ensure_column($pdo, 'especialidades', 'edad_max', 'TINYINT UNSIGNED NULL', 'edad_min');
    plantel_ensure_column($pdo, 'especialidades', 'costo_cuatrimestre', 'DECIMAL(12,2) NULL', 'edad_max');
    plantel_ensure_column($pdo, 'especialidades', 'costo_anual', 'DECIMAL(12,2) NULL', 'costo_cuatrimestre');
    plantel_ensure_column($pdo, 'especialidades', 'inscripcion_abierta', 'TINYINT(1) NOT NULL DEFAULT 1', 'visible');

    catalog_sync_edades_especialidades($pdo);
    catalog_tarifa_historial_ensure_schema($pdo);
}

/** @return array<string, string> */
function catalog_modalidades_etiquetas(): array
{
    return [
        'regular' => 'Regular (mensual / semanal)',
        'kids' => 'Infantil (Kids)',
        'extensivo' => 'Extensivo',
        'prep_abierta' => 'Preparatoria abierta',
        'prep_escolarizada' => 'Preparatoria escolarizada',
    ];
}

/** Edades sugeridas por modalidad (protocolo CNCM). */
function catalog_edad_default_modalidad(string $modalidad): array
{
    return match ($modalidad) {
        'kids' => ['min' => 8, 'max' => 12],
        'prep_abierta' => ['min' => 18, 'max' => null],
        'prep_escolarizada' => ['min' => 14, 'max' => 19],
        'extensivo', 'regular' => ['min' => 13, 'max' => null],
        default => ['min' => null, 'max' => null],
    };
}

function catalog_sync_edades_especialidades(PDO $pdo): void
{
    $st = $pdo->query(
        "SELECT id_especialidad, modalidad, edad_min, edad_max, costo_cuatrimestre, clave
         FROM especialidades WHERE activo = 1"
    );
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int) $row['id_especialidad'];
        $mod = $row['modalidad'] ?? 'regular';
        $def = catalog_edad_default_modalidad($mod);
        $updates = [];
        $params = [];

        if ($row['edad_min'] === null && $def['min'] !== null) {
            $updates[] = 'edad_min = ?';
            $params[] = $def['min'];
        }
        if ($row['edad_max'] === null && $def['max'] !== null) {
            $updates[] = 'edad_max = ?';
            $params[] = $def['max'];
        }
        if ($mod === 'prep_escolarizada' && ($row['costo_cuatrimestre'] === null || (float) $row['costo_cuatrimestre'] <= 0)) {
            $updates[] = 'costo_cuatrimestre = ?';
            $params[] = 650.0;
        }
        if ($updates !== []) {
            $params[] = $id;
            $pdo->prepare('UPDATE especialidades SET ' . implode(', ', $updates) . ' WHERE id_especialidad = ?')
                ->execute($params);
        }
    }
}

function catalog_edad_rango_texto(?int $min, ?int $max): string
{
    if ($min !== null && $max !== null) {
        return $min . '–' . $max . ' años';
    }
    if ($min !== null) {
        return 'Desde ' . $min . ' años';
    }
    if ($max !== null) {
        return 'Hasta ' . $max . ' años';
    }

    return 'Sin restricción';
}

/** Valida edad del prospecto contra la especialidad. */
function catalog_validar_edad_especialidad(array $esp, ?int $edad): array
{
    $min = isset($esp['edad_min']) && $esp['edad_min'] !== '' ? (int) $esp['edad_min'] : null;
    $max = isset($esp['edad_max']) && $esp['edad_max'] !== '' ? (int) $esp['edad_max'] : null;
    if ($edad === null || $edad < 0) {
        if ($min !== null || $max !== null) {
            return [
                'ok' => false,
                'message' => 'Indique la fecha de nacimiento para validar la edad requerida (' . catalog_edad_rango_texto($min, $max) . ')',
            ];
        }

        return ['ok' => true];
    }
    if ($min !== null && $edad < $min) {
        return [
            'ok' => false,
            'message' => 'Edad ' . $edad . ' años: mínimo ' . $min . ' para «' . ($esp['nombre'] ?? '') . '»',
        ];
    }
    if ($max !== null && $edad > $max) {
        return [
            'ok' => false,
            'message' => 'Edad ' . $edad . ' años: máximo ' . $max . ' para «' . ($esp['nombre'] ?? '') . '»',
        ];
    }

    return ['ok' => true];
}

/** Texto corto de colegiatura según modalidad. */
function catalog_colegiatura_resumen(array $e): string
{
    $mod = $e['modalidad'] ?? 'regular';
    if ($mod === 'prep_escolarizada' || !empty($e['inscripcion_por_cuatrimestre'])) {
        $cuat = (float) ($e['costo_cuatrimestre'] ?? 0);
        if ($cuat > 0) {
            return catalog_format_mxn($cuat) . '/cuat.';
        }
    }
    if ($mod === 'prep_abierta' && (float) ($e['costo_anual'] ?? 0) > 0) {
        return catalog_format_mxn((float) $e['costo_anual']) . '/año';
    }
    $parts = [];
    if ((float) ($e['costo_mensualidad'] ?? 0) > 0) {
        $parts[] = catalog_format_mxn((float) $e['costo_mensualidad']) . '/mes';
    }
    if ((float) ($e['costo_semanal'] ?? 0) > 0) {
        $parts[] = catalog_format_mxn((float) $e['costo_semanal']) . '/sem.';
    }

    return $parts !== [] ? implode(' · ', $parts) : '—';
}

function catalog_seed_especialidades(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM especialidades')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $ins = $pdo->prepare(
        'INSERT INTO especialidades (
            clave, nombre, descripcion, costo_inscripcion, costo_mensualidad,
            costo_pronto_pago, costo_semanal, duracion_meses, duracion_semanas,
            es_fija, visible, orden
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $rows = [
        ['ING', 'Inglés', 'Curso de inglés — colegiatura congelada al inscribirse.', 500, 1200, 1100, 350, 12, 48, 1, 1, 1],
        ['COMP', 'Informática', 'Curso de computación — colegiatura congelada al inscribirse.', 500, 1300, 1200, 380, 12, 48, 1, 1, 2],
        ['VERANO', 'Curso de verano', 'Especialidad temporal de verano.', 300, 900, 850, 250, 2, 8, 0, 0, 10],
    ];
    foreach ($rows as $r) {
        $ins->execute($r);
    }
}

function catalog_puede_administrar(): bool
{
    return function_exists('rbac_puede_administrar_catalogo')
        ? rbac_puede_administrar_catalogo()
        : in_array($_SESSION['rol'] ?? '', ['admin', 'director', 'supervisor'], true);
}

function catalog_puede_editar_costos(): bool
{
    return function_exists('operativo_cncm_puede_editar_costos') && operativo_cncm_puede_editar_costos();
}

function catalog_puede_confirmar_inventario(): bool
{
    return catalog_puede_administrar();
}

function catalog_money(mixed $value): float
{
    return round((float) $value, 2);
}

function catalog_normalizar_clave(string $clave, int $maxLen = 30): string
{
    $clave = strtoupper(preg_replace('/\s+/', '_', trim($clave)));
    $clave = preg_replace('/[^A-Z0-9_\-]/', '', $clave);
    return substr($clave, 0, $maxLen);
}

/** @return array<int, array<string, mixed>> */
function catalog_listar_especialidades(PDO $pdo, array $filtros = []): array
{
    $sql = 'SELECT * FROM especialidades WHERE 1=1';
    $params = [];

    if (!empty($filtros['q'])) {
        $sql .= ' AND (clave LIKE ? OR nombre LIKE ? OR descripcion LIKE ?)';
        $like = '%' . $filtros['q'] . '%';
        $params = array_merge($params, [$like, $like, $like]);
    }
    if (isset($filtros['visible']) && $filtros['visible'] !== '') {
        $sql .= ' AND visible = ?';
        $params[] = (int) $filtros['visible'];
    }
    if (isset($filtros['es_fija']) && $filtros['es_fija'] !== '') {
        $sql .= ' AND es_fija = ?';
        $params[] = (int) $filtros['es_fija'];
    }
    if (isset($filtros['activo']) && $filtros['activo'] !== '') {
        $sql .= ' AND activo = ?';
        $params[] = (int) $filtros['activo'];
    }

    $sql .= ' ORDER BY orden ASC, nombre ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<int, array<string, mixed>> */
function catalog_listar_productos(PDO $pdo, array $filtros = [], ?int $idPlantel = null): array
{
    $sql = 'SELECT p.*';
    $params = [];

    if ($idPlantel > 0) {
        $sql .= ', COALESCE(i.existencia, 0) AS existencia,
                  COALESCE(i.stock_minimo, p.stock_minimo) AS stock_alerta,
                  (COALESCE(i.existencia, 0) <= COALESCE(i.stock_minimo, p.stock_minimo)) AS bajo_stock';
        $sql .= ' FROM productos p
                  LEFT JOIN producto_inventario i ON i.id_producto = p.id_producto AND i.id_plantel = ?';
        $params[] = $idPlantel;
    } else {
        $sql .= ' FROM productos p';
    }

    $sql .= ' WHERE 1=1';

    if (!empty($filtros['q'])) {
        $sql .= ' AND (p.clave LIKE ? OR p.nombre LIKE ? OR p.descripcion LIKE ? OR p.clave_sat LIKE ?)';
        $like = '%' . $filtros['q'] . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    if (isset($filtros['visible']) && $filtros['visible'] !== '') {
        $sql .= ' AND p.visible = ?';
        $params[] = (int) $filtros['visible'];
    }
    if (isset($filtros['descontinuado']) && $filtros['descontinuado'] !== '') {
        $sql .= ' AND p.descontinuado = ?';
        $params[] = (int) $filtros['descontinuado'];
    }
    if (isset($filtros['activo']) && $filtros['activo'] !== '') {
        $sql .= ' AND p.activo = ?';
        $params[] = (int) $filtros['activo'];
    }
    if (!empty($filtros['solo_bajo_stock']) && $idPlantel > 0) {
        $sql .= ' AND COALESCE(i.existencia, 0) <= COALESCE(i.stock_minimo, p.stock_minimo)';
    }

    $sql .= ' ORDER BY p.orden ASC, p.nombre ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function catalog_ensure_inventario_row(PDO $pdo, int $idProducto, int $idPlantel): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO producto_inventario (id_producto, id_plantel, existencia)
         VALUES (?, ?, 0)'
    );
    $stmt->execute([$idProducto, $idPlantel]);
}

function catalog_aplicar_movimiento(PDO $pdo, int $idMovimiento): array
{
    $stmt = $pdo->prepare('SELECT * FROM producto_movimientos WHERE id_movimiento = ? LIMIT 1');
    $stmt->execute([$idMovimiento]);
    $mov = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$mov) {
        return ['ok' => false, 'message' => 'Movimiento no encontrado'];
    }
    if ($mov['estado'] !== 'pendiente') {
        return ['ok' => false, 'message' => 'El movimiento ya fue procesado'];
    }

    $delta = (int) $mov['cantidad'];
    if (in_array($mov['tipo'], ['merma', 'salida'], true)) {
        $delta = -$delta;
    }

    catalog_ensure_inventario_row($pdo, (int) $mov['id_producto'], (int) $mov['id_plantel']);

    $upd = $pdo->prepare(
        'UPDATE producto_inventario SET existencia = existencia + ? WHERE id_producto = ? AND id_plantel = ?'
    );
    $upd->execute([$delta, $mov['id_producto'], $mov['id_plantel']]);

    $chk = $pdo->prepare(
        'SELECT existencia FROM producto_inventario WHERE id_producto = ? AND id_plantel = ?'
    );
    $chk->execute([$mov['id_producto'], $mov['id_plantel']]);
    if ((int) $chk->fetchColumn() < 0) {
        return ['ok' => false, 'message' => 'No hay existencia suficiente para este movimiento'];
    }

    $pdo->prepare(
        'UPDATE producto_movimientos SET estado = \'aplicado\', id_usuario_confirma = ?, confirmado_en = NOW()
         WHERE id_movimiento = ?'
    )->execute([(int) ($_SESSION['user_id'] ?? 0), $idMovimiento]);

    return ['ok' => true, 'message' => 'Movimiento aplicado'];
}

/** @return array<int, array<string, mixed>> */
function catalog_movimientos_pendientes(PDO $pdo, int $idPlantel): array
{
    $stmt = $pdo->prepare(
        'SELECT m.*, p.nombre AS producto_nombre, p.clave AS producto_clave
         FROM producto_movimientos m
         INNER JOIN productos p ON p.id_producto = m.id_producto
         WHERE m.id_plantel = ? AND m.estado = \'pendiente\'
         ORDER BY m.creado_en ASC'
    );
    $stmt->execute([$idPlantel]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function catalog_format_mxn(float $monto): string
{
    return '$' . number_format($monto, 2, '.', ',');
}

/** Historial de tarifas del catálogo (no afecta colegiaturas ya congeladas en alumno_especialidades). */
function catalog_tarifa_historial_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS especialidad_tarifa_historial (
            id_hist INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_especialidad INT UNSIGNED NOT NULL,
            tarifa_anterior JSON NOT NULL,
            tarifa_nueva JSON NOT NULL,
            alumnos_con_tarifa_congelada INT UNSIGNED NOT NULL DEFAULT 0,
            id_usuario INT UNSIGNED NULL,
            motivo VARCHAR(255) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_hist),
            KEY idx_eth_esp (id_especialidad, creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** @return array<string, mixed> */
function catalog_tarifa_snapshot_row(array $row): array
{
    return [
        'costo_inscripcion' => catalog_money($row['costo_inscripcion_apoyo'] ?? $row['costo_inscripcion'] ?? 0),
        'costo_inscripcion_referencia' => catalog_money($row['costo_inscripcion_referencia'] ?? 0),
        'costo_inscripcion_apoyo' => catalog_money($row['costo_inscripcion_apoyo'] ?? $row['costo_inscripcion'] ?? 0),
        'costo_mensualidad' => catalog_money($row['costo_mensualidad_apoyo'] ?? $row['costo_mensualidad'] ?? 0),
        'costo_mensualidad_referencia' => catalog_money($row['costo_mensualidad_referencia'] ?? 0),
        'costo_mensualidad_apoyo' => catalog_money($row['costo_mensualidad_apoyo'] ?? $row['costo_mensualidad'] ?? 0),
        'costo_pronto_pago' => catalog_money($row['costo_pronto_pago_apoyo'] ?? $row['costo_pronto_pago'] ?? 0),
        'costo_pronto_pago_referencia' => catalog_money($row['costo_pronto_pago_referencia'] ?? 0),
        'costo_pronto_pago_apoyo' => catalog_money($row['costo_pronto_pago_apoyo'] ?? $row['costo_pronto_pago'] ?? 0),
        'costo_semanal' => catalog_money($row['costo_semanal_apoyo'] ?? $row['costo_semanal'] ?? 0),
        'costo_semanal_referencia' => catalog_money($row['costo_semanal_referencia'] ?? 0),
        'costo_semanal_apoyo' => catalog_money($row['costo_semanal_apoyo'] ?? $row['costo_semanal'] ?? 0),
        'costo_cuatrimestre' => $row['costo_cuatrimestre'] !== null ? catalog_money($row['costo_cuatrimestre']) : null,
        'costo_anual' => $row['costo_anual'] !== null ? catalog_money($row['costo_anual']) : null,
        'referido_tipo' => $row['referido_tipo'] ?? null,
        'referido_valor' => $row['referido_valor'] !== null ? catalog_money($row['referido_valor']) : null,
    ];
}

function catalog_tarifas_cambiaron(array $anterior, array $nueva): bool
{
    foreach (array_keys($nueva) as $k) {
        $a = $anterior[$k] ?? null;
        $b = $nueva[$k] ?? null;
        if ($a === null && $b === null) {
            continue;
        }
        if (is_numeric($a) && is_numeric($b) && abs((float) $a - (float) $b) < 0.009) {
            continue;
        }
        if ((string) $a !== (string) $b) {
            return true;
        }
    }

    return false;
}

function catalog_contar_alumnos_tarifa_congelada(PDO $pdo, int $idEspecialidad): int
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM alumno_especialidades WHERE id_especialidad = ? AND activo = 1'
    );
    $st->execute([$idEspecialidad]);

    return (int) $st->fetchColumn();
}

/**
 * Registra cambio de tarifas en catálogo antes de actualizar especialidades.
 *
 * @return array{registrado:bool,alumnos_congelados:int}
 */
function catalog_registrar_cambio_tarifa(PDO $pdo, int $idEspecialidad, array $tarifaNueva, ?string $motivo = null): array
{
    catalog_tarifa_historial_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $st->execute([$idEspecialidad]);
    $ant = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ant) {
        return ['registrado' => false, 'alumnos_congelados' => 0];
    }

    $snapAnt = catalog_tarifa_snapshot_row($ant);
    if (!catalog_tarifas_cambiaron($snapAnt, $tarifaNueva)) {
        return ['registrado' => false, 'alumnos_congelados' => catalog_contar_alumnos_tarifa_congelada($pdo, $idEspecialidad)];
    }

    $congelados = catalog_contar_alumnos_tarifa_congelada($pdo, $idEspecialidad);
    $pdo->prepare(
        'INSERT INTO especialidad_tarifa_historial (
            id_especialidad, tarifa_anterior, tarifa_nueva, alumnos_con_tarifa_congelada, id_usuario, motivo
        ) VALUES (?,?,?,?,?,?)'
    )->execute([
        $idEspecialidad,
        json_encode($snapAnt, JSON_UNESCAPED_UNICODE),
        json_encode($tarifaNueva, JSON_UNESCAPED_UNICODE),
        $congelados,
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
        $motivo ?: 'Actualización de tarifas en catálogo',
    ]);

    return ['registrado' => true, 'alumnos_congelados' => $congelados];
}

function catalog_contar_grupos_por_especialidad(PDO $pdo, int $idEspecialidad): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM grupos WHERE id_especialidad = ?');
    $st->execute([$idEspecialidad]);

    return (int) $st->fetchColumn();
}

/** @return array<int, array<string, mixed>> */
function catalog_listar_grupos_muestra_especialidad(PDO $pdo, int $idEspecialidad, int $limit = 12): array
{
    $limit = max(1, min(50, $limit));
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.aula, p.nombre AS plantel_nombre
         FROM grupos g
         LEFT JOIN planteles p ON p.id_plantel = g.id_plantel
         WHERE g.id_especialidad = ?
         ORDER BY g.clave ASC
         LIMIT ' . $limit
    );
    $st->execute([$idEspecialidad]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<int, array<string, mixed>> */
function catalog_listar_especialidades_activas(PDO $pdo, ?int $excluirId = null): array
{
    $sql = 'SELECT id_especialidad, clave, nombre, modalidad FROM especialidades WHERE activo = 1';
    $params = [];
    if ($excluirId > 0) {
        $sql .= ' AND id_especialidad <> ?';
        $params[] = $excluirId;
    }
    $sql .= ' ORDER BY orden ASC, nombre ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed>|null */
function catalog_especialidad_obtener_basico(PDO $pdo, int $idEspecialidad): ?array
{
    $st = $pdo->prepare(
        'SELECT id_especialidad, clave, nombre, activo, visible FROM especialidades WHERE id_especialidad = ? LIMIT 1'
    );
    $st->execute([$idEspecialidad]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Desactiva una especialidad y, si hay grupos vinculados, reasigna id_especialidad al sustituto.
 *
 * @return array{ok: bool, message: string, grupos_actualizados?: int}
 */
function catalog_especialidad_desactivar_con_sustitucion(PDO $pdo, int $idOrigen, ?int $idDestino): array
{
    $origen = catalog_especialidad_obtener_basico($pdo, $idOrigen);
    if (!$origen) {
        return ['ok' => false, 'message' => 'Especialidad no encontrada'];
    }

    $numGrupos = catalog_contar_grupos_por_especialidad($pdo, $idOrigen);
    $destino = null;

    if ($numGrupos > 0) {
        if ($idDestino === null || $idDestino <= 0) {
            return ['ok' => false, 'message' => 'Seleccione la especialidad con la que sustituir los grupos'];
        }
        if ($idDestino === $idOrigen) {
            return ['ok' => false, 'message' => 'La especialidad de reemplazo debe ser distinta'];
        }
        $destino = catalog_especialidad_obtener_basico($pdo, $idDestino);
        if (!$destino || !(int) $destino['activo']) {
            return ['ok' => false, 'message' => 'La especialidad de reemplazo no existe o está inactiva'];
        }
    }

    $pdo->beginTransaction();
    try {
        $actualizados = 0;
        if ($numGrupos > 0 && $idDestino > 0) {
            $up = $pdo->prepare('UPDATE grupos SET id_especialidad = ? WHERE id_especialidad = ?');
            $up->execute([$idDestino, $idOrigen]);
            $actualizados = $up->rowCount();
        }
        $pdo->prepare('UPDATE especialidades SET activo = 0, visible = 0 WHERE id_especialidad = ?')
            ->execute([$idOrigen]);
        $pdo->commit();

        $msg = 'Especialidad «' . $origen['nombre'] . '» desactivada';
        if ($actualizados > 0 && $destino) {
            $msg .= '. ' . $actualizados . ' grupo(s) pasaron a «' . $destino['nombre'] . '»';
        }

        return ['ok' => true, 'message' => $msg, 'grupos_actualizados' => $actualizados];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => 'Error BD: ' . $e->getMessage()];
    }
}
