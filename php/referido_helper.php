<?php

/**
 * Alumno recomendado por otro alumno activo: descuento al referidor + tickets.
 */

function referido_ensure_schema(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }

    plantel_ensure_column(
        $pdo,
        'especialidades',
        'referido_tipo',
        "ENUM('semana_colegiatura','monto_fijo','inscripcion_fija') NOT NULL DEFAULT 'semana_colegiatura'",
        'costo_semanal'
    );
    plantel_ensure_column(
        $pdo,
        'especialidades',
        'referido_valor',
        'DECIMAL(12,2) NULL COMMENT \'Monto fijo o % según tipo; NULL=usa costo semanal\'',
        'referido_tipo'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS inscripcion_referidos (
            id_referido INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_alumno_inscrito INT UNSIGNED NOT NULL,
            id_alumno_referidor INT UNSIGNED NOT NULL,
            id_especialidad INT UNSIGNED NOT NULL,
            id_grupo INT UNSIGNED NULL,
            id_pago_inscripcion INT UNSIGNED NULL,
            id_pago_beneficio INT UNSIGNED NULL,
            monto_beneficio DECIMAL(12,2) NOT NULL DEFAULT 0,
            tipo_beneficio VARCHAR(40) NOT NULL DEFAULT \'semana_colegiatura\',
            id_usuario_registro INT UNSIGNED NULL,
            firma_referidor_at DATETIME NULL,
            ticket_copia_impresa TINYINT(1) NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_referido),
            KEY idx_ref_inscrito (id_alumno_inscrito),
            KEY idx_ref_referidor (id_alumno_referidor),
            KEY idx_ref_plantel_fecha (id_plantel, creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** @return list<array<string,mixed>> */
function referido_buscar_alumno_activo(PDO $pdo, int $idPlantel, string $q): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $like = '%' . $q . '%';
    $st = $pdo->prepare(
        "SELECT id_alumno, numero_control,
                TRIM(CONCAT(nombres, ' ', apellido_paterno, ' ', IFNULL(apellido_materno,''))) AS nombre_completo,
                forma_pago, id_especialidad
         FROM alumnos
         WHERE id_plantel = ? AND estado = 'activo'
           AND (numero_control LIKE ? OR nombres LIKE ? OR apellido_paterno LIKE ? OR matricula LIKE ?)
         ORDER BY apellido_paterno, nombres
         LIMIT 15"
    );
    $st->execute([$idPlantel, $like, $like, $like, $like]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Calcula monto de beneficio para el referidor según especialidad del curso inscrito. */
function referido_calcular_beneficio(PDO $pdo, int $idEspecialidad, array $referidor): float
{
    referido_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT referido_tipo, referido_valor, costo_semanal, costo_inscripcion, nombre
         FROM especialidades WHERE id_especialidad = ? LIMIT 1'
    );
    $st->execute([$idEspecialidad]);
    $esp = $st->fetch(PDO::FETCH_ASSOC);
    if (!$esp) {
        return 0.0;
    }

    $tipo = (string) ($esp['referido_tipo'] ?? 'semana_colegiatura');
    $valor = $esp['referido_valor'] !== null ? (float) $esp['referido_valor'] : null;

    return match ($tipo) {
        'monto_fijo' => $valor ?? (float) ($esp['costo_semanal'] ?? 0),
        'inscripcion_fija' => $valor ?? (float) ($esp['costo_inscripcion'] ?? 0),
        default => $valor ?? (float) ($esp['costo_semanal'] ?? 0),
    };
}

/** @return array{ok:bool,message:string,id_referido?:int,id_pago_beneficio?:int,ticket_url?:string,monto?:float} */
function referido_aplicar_tras_inscripcion(
    PDO $pdo,
    int $idPlantel,
    int $idAlumnoInscrito,
    int $idAlumnoReferidor,
    int $idEspecialidad,
    int $idGrupo,
    ?int $idPagoInscripcion
): array {
    referido_ensure_schema($pdo);

    if ($idAlumnoInscrito === $idAlumnoReferidor) {
        return ['ok' => false, 'message' => 'El alumno no puede referirse a sí mismo'];
    }

    $ref = $pdo->prepare(
        "SELECT id_alumno, numero_control, nombres, apellido_paterno, estado, forma_pago
         FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1"
    );
    $ref->execute([$idAlumnoReferidor, $idPlantel]);
    $alRef = $ref->fetch(PDO::FETCH_ASSOC);
    if (!$alRef || ($alRef['estado'] ?? '') !== 'activo') {
        return ['ok' => false, 'message' => 'El referidor debe ser un alumno activo'];
    }

    $monto = referido_calcular_beneficio($pdo, $idEspecialidad, $alRef);
    if ($monto <= 0) {
        return ['ok' => false, 'message' => 'No hay monto de beneficio configurado para esta especialidad'];
    }

    $stEsp = $pdo->prepare('SELECT referido_tipo, nombre FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $stEsp->execute([$idEspecialidad]);
    $espMeta = $stEsp->fetch(PDO::FETCH_ASSOC) ?: [];
    $tipoBen = (string) ($espMeta['referido_tipo'] ?? 'semana_colegiatura');

    $nuevo = $pdo->prepare('SELECT numero_control, nombres, apellido_paterno FROM alumnos WHERE id_alumno = ? LIMIT 1');
    $nuevo->execute([$idAlumnoInscrito]);
    $alNuevo = $nuevo->fetch(PDO::FETCH_ASSOC) ?: [];

    pago_ensure_schema($pdo);
    $folio = 'REF-' . date('ymd') . '-' . substr((string) microtime(true), -4);
    $concepto = 'Crédito por referido — nuevo ' . ($alNuevo['numero_control'] ?? $idAlumnoInscrito);

    $pago = pago_registrar($pdo, [
        'id_alumno' => $idAlumnoReferidor,
        'id_especialidad' => (int) ($alRef['id_especialidad'] ?? 0) ?: null,
        'tipo' => 'abono',
        'monto' => $monto,
        'concepto' => $concepto,
        'forma_pago_efectivo' => 'Crédito referido',
        'folio' => $folio,
        'motivo_descuento' => 'Bono referido (' . ($espMeta['nombre'] ?? '') . ')',
        'monto_descuento' => 0,
    ]);

    if (!$pago['ok']) {
        return $pago;
    }

    $idPagoBen = (int) ($pago['id_pago'] ?? 0);

    $pdo->prepare(
        'INSERT INTO inscripcion_referidos (
            id_plantel, id_alumno_inscrito, id_alumno_referidor, id_especialidad, id_grupo,
            id_pago_inscripcion, id_pago_beneficio, monto_beneficio, tipo_beneficio, id_usuario_registro
        ) VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idPlantel,
        $idAlumnoInscrito,
        $idAlumnoReferidor,
        $idEspecialidad,
        $idGrupo > 0 ? $idGrupo : null,
        $idPagoInscripcion > 0 ? $idPagoInscripcion : null,
        $idPagoBen,
        $monto,
        $tipoBen,
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
    ]);

    $idReferido = (int) $pdo->lastInsertId();

    return [
        'ok' => true,
        'message' => 'Beneficio por referido aplicado',
        'id_referido' => $idReferido,
        'id_pago_beneficio' => $idPagoBen,
        'monto' => $monto,
        'ticket_url' => 'views/ticket_referido_beneficio.php?id_referido=' . $idReferido . '&print=1',
    ];
}

/** @return array<string,mixed>|null */
function referido_datos_ticket(PDO $pdo, int $idReferido, int $idPlantel): ?array
{
    referido_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT r.*,
                ai.numero_control AS control_inscrito,
                TRIM(CONCAT(ai.nombres, \' \', ai.apellido_paterno)) AS nombre_inscrito,
                ar.numero_control AS control_referidor,
                TRIM(CONCAT(ar.nombres, \' \', ar.apellido_paterno)) AS nombre_referidor,
                e.nombre AS esp_nombre, g.clave AS grupo_clave
         FROM inscripcion_referidos r
         INNER JOIN alumnos ai ON ai.id_alumno = r.id_alumno_inscrito
         INNER JOIN alumnos ar ON ar.id_alumno = r.id_alumno_referidor
         LEFT JOIN especialidades e ON e.id_especialidad = r.id_especialidad
         LEFT JOIN grupos g ON g.id_grupo = r.id_grupo
         WHERE r.id_referido = ? AND r.id_plantel = ? LIMIT 1'
    );
    $st->execute([$idReferido, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $pt = function_exists('plantel_ticket_datos') ? plantel_ticket_datos($pdo, $idPlantel) : [];

    return [
        'plantel_ticket' => $pt,
        'folio' => 'REF-' . $idReferido,
        'fecha_fmt' => date('d-m-Y', strtotime($row['creado_en'] ?? 'now')),
        'hora_fmt' => date('H:i:s', strtotime($row['creado_en'] ?? 'now')),
        'alumno' => $row['nombre_referidor'],
        'numero_control' => $row['control_referidor'],
        'monto_fmt' => catalog_format_mxn((float) $row['monto_beneficio']),
        'monto' => (float) $row['monto_beneficio'],
        'inscrito_nombre' => $row['nombre_inscrito'],
        'inscrito_control' => $row['control_inscrito'],
        'especialidad' => $row['esp_nombre'] ?? '',
        'grupo' => $row['grupo_clave'] ?? '',
        'tipo_beneficio' => $row['tipo_beneficio'] ?? '',
        'recibio' => trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? '')),
        'requiere_firma' => empty($row['firma_referidor_at']),
    ];
}

function referido_marcar_firma(PDO $pdo, int $idReferido, int $idPlantel, bool $copiaImpresa = false): void
{
    referido_ensure_schema($pdo);
    $pdo->prepare(
        'UPDATE inscripcion_referidos
         SET firma_referidor_at = NOW(), ticket_copia_impresa = ?
         WHERE id_referido = ? AND id_plantel = ?'
    )->execute([$copiaImpresa ? 1 : 0, $idReferido, $idPlantel]);
}
