<?php

/**
 * Comisiones de certificación (asesor + sobrecomisión gerente) con historial.
 */

function comision_cert_ensure_schema(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }

    plantel_ensure_column(
        $pdo,
        'producto_certificacion',
        'comision_asesor_default',
        'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'notas_asesor'
    );
    plantel_ensure_column(
        $pdo,
        'producto_certificacion',
        'comision_gerente_default',
        'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'comision_asesor_default'
    );

    plantel_ensure_column(
        $pdo,
        'certificacion_solicitudes',
        'precio_cobrado',
        'DECIMAL(12,2) NULL COMMENT \'Precio al registrar (histórico)\'',
        'notas'
    );
    plantel_ensure_column(
        $pdo,
        'certificacion_solicitudes',
        'comision_asesor',
        'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'precio_cobrado'
    );
    plantel_ensure_column(
        $pdo,
        'certificacion_solicitudes',
        'comision_gerente',
        'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'comision_asesor'
    );
    plantel_ensure_column(
        $pdo,
        'certificacion_solicitudes',
        'id_usuario_asesor',
        'INT UNSIGNED NULL',
        'id_usuario_registro'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS certificacion_comision_historial (
            id_historial INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_solicitud INT UNSIGNED NOT NULL,
            precio_cobrado DECIMAL(12,2) NULL,
            comision_asesor DECIMAL(12,2) NOT NULL DEFAULT 0,
            comision_gerente DECIMAL(12,2) NOT NULL DEFAULT 0,
            id_usuario INT UNSIGNED NULL,
            motivo VARCHAR(255) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_historial),
            KEY idx_cert_com_hist_sol (id_solicitud, creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** @return array{comision_asesor:float,comision_gerente:float,precio:float} */
function comision_cert_defaults_producto(PDO $pdo, int $idProducto): array
{
    comision_cert_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT p.precio, pc.comision_asesor_default, pc.comision_gerente_default
         FROM productos p
         LEFT JOIN producto_certificacion pc ON pc.id_producto = p.id_producto
         WHERE p.id_producto = ? LIMIT 1'
    );
    $st->execute([$idProducto]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'precio' => (float) ($r['precio'] ?? 0),
        'comision_asesor' => (float) ($r['comision_asesor_default'] ?? 0),
        'comision_gerente' => (float) ($r['comision_gerente_default'] ?? 0),
    ];
}

function comision_cert_registrar_historial(
    PDO $pdo,
    int $idSolicitud,
    ?float $precio,
    float $comAsesor,
    float $comGerente,
    ?string $motivo = null
): void {
    comision_cert_ensure_schema($pdo);
    $pdo->prepare(
        'INSERT INTO certificacion_comision_historial
            (id_solicitud, precio_cobrado, comision_asesor, comision_gerente, id_usuario, motivo)
         VALUES (?,?,?,?,?,?)'
    )->execute([
        $idSolicitud,
        $precio,
        $comAsesor,
        $comGerente,
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
        $motivo,
    ]);
}

/** @return array{ok:bool,message:string} */
function comision_cert_actualizar_solicitud(PDO $pdo, int $idSolicitud, int $idPlantel, array $data): array
{
    comision_cert_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT id_solicitud, precio_cobrado, comision_asesor, comision_gerente
         FROM certificacion_solicitudes WHERE id_solicitud = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$idSolicitud, $idPlantel]);
    $ant = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ant) {
        return ['ok' => false, 'message' => 'Solicitud no encontrada'];
    }

    $precio = isset($data['precio_cobrado']) ? catalog_money($data['precio_cobrado']) : (float) ($ant['precio_cobrado'] ?? 0);
    $comA = isset($data['comision_asesor']) ? catalog_money($data['comision_asesor']) : (float) $ant['comision_asesor'];
    $comG = isset($data['comision_gerente']) ? catalog_money($data['comision_gerente']) : (float) $ant['comision_gerente'];
    $motivo = trim((string) ($data['motivo'] ?? 'Ajuste de comisiones'));

    comision_cert_registrar_historial(
        $pdo,
        $idSolicitud,
        (float) ($ant['precio_cobrado'] ?? 0),
        (float) $ant['comision_asesor'],
        (float) $ant['comision_gerente'],
        'Antes de: ' . $motivo
    );

    $pdo->prepare(
        'UPDATE certificacion_solicitudes
         SET precio_cobrado = ?, comision_asesor = ?, comision_gerente = ?, actualizado_en = NOW()
         WHERE id_solicitud = ? AND id_plantel = ?'
    )->execute([$precio, $comA, $comG, $idSolicitud, $idPlantel]);

    return ['ok' => true, 'message' => 'Comisiones actualizadas (se guardó historial)'];
}

/** @return list<array<string,mixed>> */
function comision_cert_historial_solicitud(PDO $pdo, int $idSolicitud): array
{
    comision_cert_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT h.*, CONCAT(u.nombre, \' \', u.apellido) AS usuario_nombre
         FROM certificacion_comision_historial h
         LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario
         WHERE h.id_solicitud = ?
         ORDER BY h.creado_en DESC'
    );
    $st->execute([$idSolicitud]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}
