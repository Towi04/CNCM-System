<?php

/**
 * Transferencias bancarias: pendientes de confirmación (BBVA / Bancoppel / HSBC).
 */

define('PAGO_TRANSFER_COMPROBANTE_DIR', 'uploads/pagos/comprobantes');
define('PAGO_TRANSFER_COMPROBANTE_MAX', 4 * 1024 * 1024);

function pago_transferencia_ensure_schema(PDO $pdo): void
{
    if (function_exists('operativo_cncm_pagos_campos')) {
        operativo_cncm_pagos_campos($pdo);
        return;
    }
    if (!function_exists('plantel_ensure_column')) {
        return;
    }
    pago_ensure_schema($pdo);
    plantel_ensure_column($pdo, 'alumno_pagos', 'cuenta_banco', "ENUM('bbva','bancoppel','hsbc') NULL", 'medio_pago');
    plantel_ensure_column($pdo, 'alumno_pagos', 'transfer_estado', "ENUM('pendiente','confirmado','rechazado') NULL", 'cuenta_banco');
    plantel_ensure_column($pdo, 'alumno_pagos', 'comprobante_path', 'VARCHAR(255) NULL', 'transfer_estado');
    plantel_ensure_column($pdo, 'alumno_pagos', 'transfer_confirmado_por', 'INT UNSIGNED NULL', 'comprobante_path');
    plantel_ensure_column($pdo, 'alumno_pagos', 'transfer_confirmado_en', 'DATETIME NULL', 'transfer_confirmado_por');
}

function pago_transferencia_puede_confirmar(): bool
{
    if (function_exists('rbac_es_supervisor') && rbac_es_supervisor()) {
        return true;
    }
    if (function_exists('rbac_rol_real') && rbac_rol_real() === 'supervisor') {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_transferencias_confirmar');
}

function pago_transferencia_puede_ver(): bool
{
    if (pago_transferencia_puede_confirmar()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('menu_transferencias_ver')) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';

    return in_array($rol, ['admin', 'recepcion', 'caja', 'director'], true);
}

/** @return list<string> */
function pago_transferencia_cuentas(): array
{
    return ['bbva', 'bancoppel', 'hsbc'];
}

function pago_transferencia_cuenta_label(string $cuenta): string
{
    return match (strtolower($cuenta)) {
        'bbva' => 'BBVA',
        'bancoppel' => 'Bancoppel',
        'hsbc' => 'HSBC',
        default => strtoupper($cuenta),
    };
}

function pago_transferencia_normalizar_cuenta(?string $cuenta): ?string
{
    $c = strtolower(trim((string) $cuenta));
    if (!in_array($c, pago_transferencia_cuentas(), true)) {
        return null;
    }

    return $c;
}

function pago_transferencia_upload_dir_abs(): string
{
    $dir = dirname(__DIR__) . '/' . PAGO_TRANSFER_COMPROBANTE_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

/**
 * @param array<string, mixed> $file
 * @return array{ok:bool, message?:string, path?:string|null}
 */
function pago_transferencia_guardar_comprobante(array $file, int $idAlumno = 0): array
{
    if (empty($file['tmp_name']) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null];
    }
    if (!function_exists('hay_upload_guardar')) {
        return ['ok' => false, 'message' => 'Carga de archivos no disponible'];
    }
    $basename = 'tr_' . ($idAlumno > 0 ? $idAlumno . '_' : '') . bin2hex(random_bytes(8));
    $res = hay_upload_guardar(
        $file,
        pago_transferencia_upload_dir_abs(),
        $basename,
        HAY_UPLOAD_MIME_IMAGE_PDF,
        PAGO_TRANSFER_COMPROBANTE_MAX,
        false
    );
    if (!$res['ok']) {
        return ['ok' => false, 'message' => $res['message'] ?? 'No se pudo guardar el comprobante'];
    }
    if (empty($res['filename'])) {
        return ['ok' => true, 'path' => null];
    }

    return ['ok' => true, 'path' => PAGO_TRANSFER_COMPROBANTE_DIR . '/' . $res['filename']];
}

function pago_transferencia_url_comprobante(?string $path): ?string
{
    $path = ltrim(str_replace('\\', '/', (string) $path), '/');
    if ($path === '' || strpos($path, PAGO_TRANSFER_COMPROBANTE_DIR . '/') !== 0) {
        return null;
    }
    if (!is_file(dirname(__DIR__) . '/' . $path)) {
        return null;
    }

    return function_exists('hay_asset_url') ? hay_asset_url($path) : $path;
}

/**
 * @return list<array<string, mixed>>
 */
function pago_transferencia_listar_pendientes(PDO $pdo, int $idPlantel, ?string $desde = null, ?string $hasta = null): array
{
    pago_transferencia_ensure_schema($pdo);
    $sql = 'SELECT p.*, a.nombre AS alumno_nombre, a.apellido_paterno, a.apellido_materno,
                   a.numero_control, a.matricula,
                   CONCAT(u.nombre, \' \', u.apellido) AS registro_nombre
            FROM alumno_pagos p
            INNER JOIN alumnos a ON a.id_alumno = p.id_alumno
            LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
            WHERE p.id_plantel = ?
              AND p.medio_pago = \'transferencia\'
              AND p.transfer_estado = \'pendiente\'
              AND (p.estado = \'activo\' OR p.estado IS NULL)';
    $params = [$idPlantel];
    if ($desde && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $sql .= ' AND DATE(p.creado_en) >= ?';
        $params[] = $desde;
    }
    if ($hasta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $sql .= ' AND DATE(p.creado_en) <= ?';
        $params[] = $hasta;
    }
    $sql .= ' ORDER BY p.creado_en ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['cuenta_banco_label'] = pago_transferencia_cuenta_label((string) ($r['cuenta_banco'] ?? ''));
        $r['comprobante_url'] = pago_transferencia_url_comprobante($r['comprobante_path'] ?? null);
        $r['alumno_nombre_completo'] = trim(
            ($r['alumno_nombre'] ?? '') . ' ' . ($r['apellido_paterno'] ?? '') . ' ' . ($r['apellido_materno'] ?? '')
        );
        $r['monto_fmt'] = function_exists('catalog_format_mxn')
            ? catalog_format_mxn((float) ($r['monto'] ?? 0))
            : number_format((float) ($r['monto'] ?? 0), 2);
    }
    unset($r);

    return $rows;
}

/**
 * Comprobantes (confirmados o pendientes) para evidencia de corte.
 * @return list<array<string, mixed>>
 */
function pago_transferencia_listar_comprobantes(
    PDO $pdo,
    int $idPlantel,
    ?string $desde = null,
    ?string $hasta = null,
    ?string $estado = null
): array {
    pago_transferencia_ensure_schema($pdo);
    $sql = 'SELECT p.*, a.nombre AS alumno_nombre, a.apellido_paterno, a.apellido_materno,
                   a.numero_control, a.matricula,
                   CONCAT(u.nombre, \' \', u.apellido) AS registro_nombre,
                   CONCAT(uc.nombre, \' \', uc.apellido) AS confirmo_nombre
            FROM alumno_pagos p
            INNER JOIN alumnos a ON a.id_alumno = p.id_alumno
            LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
            LEFT JOIN usuarios uc ON uc.id_usuario = p.transfer_confirmado_por
            WHERE p.id_plantel = ?
              AND p.medio_pago = \'transferencia\'
              AND (p.estado = \'activo\' OR p.estado IS NULL)';
    $params = [$idPlantel];
    if ($estado !== null && $estado !== '' && in_array($estado, ['pendiente', 'confirmado', 'rechazado'], true)) {
        $sql .= ' AND p.transfer_estado = ?';
        $params[] = $estado;
    }
    if ($desde && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $sql .= ' AND DATE(p.creado_en) >= ?';
        $params[] = $desde;
    }
    if ($hasta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $sql .= ' AND DATE(p.creado_en) <= ?';
        $params[] = $hasta;
    }
    $sql .= ' ORDER BY p.creado_en DESC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['cuenta_banco_label'] = pago_transferencia_cuenta_label((string) ($r['cuenta_banco'] ?? ''));
        $r['comprobante_url'] = pago_transferencia_url_comprobante($r['comprobante_path'] ?? null);
        $r['alumno_nombre_completo'] = trim(
            ($r['alumno_nombre'] ?? '') . ' ' . ($r['apellido_paterno'] ?? '') . ' ' . ($r['apellido_materno'] ?? '')
        );
        $r['monto_fmt'] = function_exists('catalog_format_mxn')
            ? catalog_format_mxn((float) ($r['monto'] ?? 0))
            : number_format((float) ($r['monto'] ?? 0), 2);
    }
    unset($r);

    return $rows;
}

function pago_transferencia_obtener(PDO $pdo, int $idPago): ?array
{
    pago_transferencia_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM alumno_pagos WHERE id_pago = ? LIMIT 1');
    $st->execute([$idPago]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function pago_transferencia_confirmar(PDO $pdo, int $idPago, int $idUsuario): array
{
    if (!pago_transferencia_puede_confirmar()) {
        return ['ok' => false, 'message' => 'Sin permiso para confirmar transferencias'];
    }
    $pago = pago_transferencia_obtener($pdo, $idPago);
    if (!$pago) {
        return ['ok' => false, 'message' => 'Pago no encontrado'];
    }
    if ((int) ($pago['id_plantel'] ?? 0) !== plantel_id_activo()) {
        return ['ok' => false, 'message' => 'El pago no pertenece a este plantel'];
    }
    if (($pago['medio_pago'] ?? '') !== 'transferencia') {
        return ['ok' => false, 'message' => 'El pago no es una transferencia'];
    }
    if (($pago['transfer_estado'] ?? '') !== 'pendiente') {
        return ['ok' => false, 'message' => 'La transferencia ya fue procesada'];
    }
    if (($pago['estado'] ?? 'activo') === 'anulado') {
        return ['ok' => false, 'message' => 'El pago está anulado'];
    }

    $pdo->prepare(
        'UPDATE alumno_pagos
         SET transfer_estado = \'confirmado\',
             transfer_confirmado_por = ?,
             transfer_confirmado_en = NOW()
         WHERE id_pago = ?'
    )->execute([$idUsuario, $idPago]);

    $tipo = (string) ($pago['tipo'] ?? 'abono');
    $idAlumno = (int) $pago['id_alumno'];
    if ($tipo === 'inscripcion') {
        $idAe = (int) ($pago['id_alumno_especialidad'] ?? 0);
        $idEsp = (int) ($pago['id_especialidad'] ?? 0);
        if ($idAe > 0 && $idEsp > 0) {
            pago_actualizar_inscripcion_cubierta($pdo, $idAlumno, $idEsp, $idAe);
        }
        pago_sync_inscripcion_global($pdo, $idAlumno);
    }

    return ['ok' => true, 'message' => 'Transferencia confirmada; ya cuenta en adeudo y corte'];
}

function pago_transferencia_rechazar(PDO $pdo, int $idPago, int $idUsuario, string $motivo = ''): array
{
    if (!pago_transferencia_puede_confirmar()) {
        return ['ok' => false, 'message' => 'Sin permiso para rechazar transferencias'];
    }
    $pago = pago_transferencia_obtener($pdo, $idPago);
    if (!$pago) {
        return ['ok' => false, 'message' => 'Pago no encontrado'];
    }
    if ((int) ($pago['id_plantel'] ?? 0) !== plantel_id_activo()) {
        return ['ok' => false, 'message' => 'El pago no pertenece a este plantel'];
    }
    if (($pago['transfer_estado'] ?? '') !== 'pendiente') {
        return ['ok' => false, 'message' => 'La transferencia ya fue procesada'];
    }

    $motivo = trim($motivo);
    $conceptoExtra = $motivo !== '' ? (' [Rechazo: ' . mb_substr($motivo, 0, 180) . ']') : '';
    $pdo->prepare(
        'UPDATE alumno_pagos
         SET transfer_estado = \'rechazado\',
             transfer_confirmado_por = ?,
             transfer_confirmado_en = NOW(),
             concepto = CONCAT(COALESCE(concepto, \'\'), ?)
         WHERE id_pago = ?'
    )->execute([$idUsuario, $conceptoExtra, $idPago]);

    return ['ok' => true, 'message' => 'Transferencia rechazada'];
}

/**
 * Adjunta o reemplaza comprobante en un pago de transferencia.
 * @param array<string, mixed> $file
 */
function pago_transferencia_adjuntar_comprobante(PDO $pdo, int $idPago, array $file): array
{
    $pago = pago_transferencia_obtener($pdo, $idPago);
    if (!$pago) {
        return ['ok' => false, 'message' => 'Pago no encontrado'];
    }
    $up = pago_transferencia_guardar_comprobante($file, (int) ($pago['id_alumno'] ?? 0));
    if (!$up['ok']) {
        return $up;
    }
    if (empty($up['path'])) {
        return ['ok' => false, 'message' => 'Seleccione un archivo de comprobante'];
    }
    $pdo->prepare('UPDATE alumno_pagos SET comprobante_path = ? WHERE id_pago = ?')
        ->execute([$up['path'], $idPago]);

    return [
        'ok' => true,
        'message' => 'Comprobante guardado',
        'path' => $up['path'],
        'url' => pago_transferencia_url_comprobante($up['path']),
    ];
}

/**
 * Registro desde portal alumno (o recepción con comprobante).
 * @param array<string, mixed> $data
 * @param array<string, mixed>|null $file
 */
function pago_transferencia_registrar(PDO $pdo, array $data, ?array $file = null): array
{
    pago_transferencia_ensure_schema($pdo);
    $cuenta = pago_transferencia_normalizar_cuenta($data['cuenta_banco'] ?? null);
    if ($cuenta === null) {
        return ['ok' => false, 'message' => 'Seleccione la cuenta bancaria (BBVA, Bancoppel o HSBC)'];
    }
    $comprobantePath = null;
    if ($file !== null) {
        $up = pago_transferencia_guardar_comprobante($file, (int) ($data['id_alumno'] ?? 0));
        if (!$up['ok']) {
            return $up;
        }
        $comprobantePath = $up['path'] ?? null;
    }

    $payload = array_merge($data, [
        'cuenta_banco' => $cuenta,
        'medio_pago' => 'transferencia',
        'forma_pago_efectivo' => 'Transferencia',
        'transfer_estado' => 'pendiente',
        'comprobante_path' => $comprobantePath,
        'concepto' => trim((string) ($data['concepto'] ?? '')) !== ''
            ? $data['concepto']
            : ('Transferencia ' . pago_transferencia_cuenta_label($cuenta)),
    ]);

    return pago_registrar($pdo, $payload);
}
