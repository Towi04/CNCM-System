<?php

/**
 * Cola de facturación para recepción: datos fiscales pendientes en pre-registros.
 */

function cola_facturacion_puede_ver(): bool
{
    return function_exists('preregistro_puede_gestionar_factura') && preregistro_puede_gestionar_factura();
}

function cola_facturacion_contar(PDO $pdo, ?int $idPlantel = null): int
{
    if (!cola_facturacion_puede_ver()) {
        return 0;
    }
    preregistro_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM preregistros
         WHERE id_plantel = ? AND requiere_factura = 1 AND factura_datos_pendientes = 1
           AND estado <> 'perdido'"
    );
    $st->execute([$idPlantel]);

    return (int) $st->fetchColumn();
}

/** @return list<array<string, mixed>> */
function cola_facturacion_listar(PDO $pdo, ?int $idPlantel = null): array
{
    preregistro_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    $st = $pdo->prepare(
        "SELECT p.*, a.numero_control, a.id_alumno, e.nombre AS esp_nombre
         FROM preregistros p
         LEFT JOIN alumnos a ON a.id_alumno = p.id_alumno_vinculado
         LEFT JOIN especialidades e ON e.id_especialidad = p.id_especialidad
         WHERE p.id_plantel = ? AND p.requiere_factura = 1 AND p.factura_datos_pendientes = 1
           AND p.estado <> 'perdido'
         ORDER BY p.actualizado_en ASC, p.creado_en ASC"
    );
    $st->execute([$idPlantel]);
    $labels = preregistro_labels()['estado'];
    $items = [];

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $faltan = preregistro_factura_campos_pendientes($row);
        $nombre = trim(($row['nombres'] ?? '') . ' ' . ($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? ''));
        $estado = (string) ($row['estado'] ?? 'activo');
        $csf = (string) ($row['factura_constancia_path'] ?? '');
        $items[] = [
            'id_preregistro' => (int) $row['id_preregistro'],
            'nombre' => $nombre,
            'numero_control' => (string) ($row['numero_control'] ?? ''),
            'id_alumno' => (int) ($row['id_alumno'] ?? 0),
            'estado' => $estado,
            'estado_label' => $labels[$estado] ?? $estado,
            'esp_nombre' => (string) ($row['esp_nombre'] ?? ''),
            'telefono' => (string) ($row['telefono'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'creado_en' => (string) ($row['creado_en'] ?? ''),
            'actualizado_en' => (string) ($row['actualizado_en'] ?? ''),
            'factura_rfc' => (string) ($row['factura_rfc'] ?? ''),
            'factura_curp' => (string) ($row['factura_curp'] ?? ''),
            'factura_telefono' => (string) ($row['factura_telefono'] ?? ''),
            'factura_razon_social' => (string) ($row['factura_razon_social'] ?? ''),
            'factura_correo' => (string) ($row['factura_correo'] ?? ''),
            'factura_domicilio_fiscal' => (string) ($row['factura_domicilio_fiscal'] ?? ''),
            'factura_constancia_path' => $csf,
            'factura_constancia_url' => $csf !== '' ? hay_asset_url($csf) : '',
            'campos_faltantes' => $faltan,
            'campos_faltantes_txt' => $faltan !== [] ? implode(', ', $faltan) : '',
        ];
    }

    return $items;
}

/** @return array{ok: bool, message: string, completo?: bool, faltan?: list<string>} */
function cola_facturacion_guardar(
    PDO $pdo,
    int $idPrereg,
    int $idPlantel,
    array $input,
    ?array $fileConstancia = null
): array {
    if (!cola_facturacion_puede_ver()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }

    $st = $pdo->prepare('SELECT * FROM preregistros WHERE id_preregistro = ? AND id_plantel = ? LIMIT 1');
    $st->execute([$idPrereg, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'message' => 'Pre-registro no encontrado'];
    }
    if ((int) ($row['requiere_factura'] ?? 0) !== 1) {
        return ['ok' => false, 'message' => 'Este registro no solicita factura'];
    }

    $csfPath = (string) ($row['factura_constancia_path'] ?? '');
    if ($fileConstancia !== null && !empty($fileConstancia['tmp_name'])) {
        $up = preregistro_guardar_archivo($fileConstancia, PREREG_CSF_DIR, 'csf');
        if (!$up['ok']) {
            return ['ok' => false, 'message' => $up['message'] ?? 'Error al subir constancia'];
        }
        if (!empty($up['path'])) {
            preregistro_borrar_archivo($csfPath !== '' ? $csfPath : null);
            $csfPath = (string) $up['path'];
        }
    }

    $data = $row;
    $data['factura_rfc'] = strtoupper(trim((string) ($input['factura_rfc'] ?? $row['factura_rfc'] ?? '')));
    $data['factura_curp'] = strtoupper(trim((string) ($input['factura_curp'] ?? $row['factura_curp'] ?? '')));
    $data['factura_telefono'] = trim((string) ($input['factura_telefono'] ?? $row['factura_telefono'] ?? $row['telefono'] ?? ''));
    $data['factura_razon_social'] = trim((string) ($input['factura_razon_social'] ?? $row['factura_razon_social'] ?? ''));
    $data['factura_correo'] = trim((string) ($input['factura_correo'] ?? $row['factura_correo'] ?? $row['email'] ?? ''));
    $data['factura_domicilio_fiscal'] = trim((string) ($input['factura_domicilio_fiscal'] ?? $row['factura_domicilio_fiscal'] ?? ''));
    $data['factura_constancia_path'] = $csfPath;
    $data['requiere_factura'] = 1;
    $data['id_plantel'] = $idPlantel;

    $pdo->prepare(
        'UPDATE preregistros SET
            factura_rfc = ?, factura_curp = ?, factura_telefono = ?,
            factura_razon_social = ?, factura_correo = ?, factura_domicilio_fiscal = ?,
            factura_constancia_path = ?, actualizado_en = NOW()
         WHERE id_preregistro = ? AND id_plantel = ?'
    )->execute([
        $data['factura_rfc'] ?: null,
        $data['factura_curp'] ?: null,
        $data['factura_telefono'] ?: null,
        $data['factura_razon_social'] ?: null,
        $data['factura_correo'] ?: null,
        $data['factura_domicilio_fiscal'] ?: null,
        $csfPath !== '' ? $csfPath : null,
        $idPrereg,
        $idPlantel,
    ]);

    preregistro_evaluar_alertas_guardado($pdo, $idPrereg, $data);
    $faltan = preregistro_factura_campos_pendientes($data);

    if ($faltan === []) {
        return [
            'ok' => true,
            'message' => 'Datos de factura completos. Se quitó de la cola.',
            'completo' => true,
            'faltan' => [],
        ];
    }

    return [
        'ok' => true,
        'message' => 'Datos guardados. Aún faltan: ' . implode(', ', $faltan) . '.',
        'completo' => false,
        'faltan' => $faltan,
    ];
}

/** @return array{ok: bool, message: string} */
function cola_facturacion_quitar_solicitud(PDO $pdo, int $idPrereg, int $idPlantel): array
{
    if (!cola_facturacion_puede_ver()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }

    $st = $pdo->prepare('SELECT * FROM preregistros WHERE id_preregistro = ? AND id_plantel = ? LIMIT 1');
    $st->execute([$idPrereg, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'message' => 'Pre-registro no encontrado'];
    }

    $pdo->prepare(
        'UPDATE preregistros SET requiere_factura = 0, factura_datos_pendientes = 0, actualizado_en = NOW()
         WHERE id_preregistro = ? AND id_plantel = ?'
    )->execute([$idPrereg, $idPlantel]);

    $data = $row;
    $data['requiere_factura'] = 0;
    $data['id_plantel'] = $idPlantel;
    preregistro_evaluar_alertas_guardado($pdo, $idPrereg, $data);

    return ['ok' => true, 'message' => 'Se quitó la solicitud de factura del registro.'];
}
