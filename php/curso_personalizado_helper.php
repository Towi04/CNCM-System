<?php

/**
 * Cursos personalizados: contrato sin inscripción global, pagos diferidos.
 */

function curso_personalizado_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS curso_personalizado (
            id_curso INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            titulo VARCHAR(160) NOT NULL,
            duracion_semanas SMALLINT UNSIGNED NULL,
            costo_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            num_pagos TINYINT UNSIGNED NOT NULL DEFAULT 1,
            id_especialidad_ref INT UNSIGNED NULL,
            id_grupo INT UNSIGNED NULL,
            temario_json TEXT NULL,
            estado ENUM(\'activo\',\'completado\',\'cancelado\') NOT NULL DEFAULT \'activo\',
            id_usuario_registro INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_curso),
            KEY idx_cp_alumno (id_alumno),
            KEY idx_cp_plantel (id_plantel, estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS curso_personalizado_pago (
            id_pago_prog INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_curso INT UNSIGNED NOT NULL,
            numero TINYINT UNSIGNED NOT NULL DEFAULT 1,
            monto DECIMAL(12,2) NOT NULL DEFAULT 0,
            fecha_programada DATE NULL,
            pagado TINYINT(1) NOT NULL DEFAULT 0,
            id_pago_alumno INT UNSIGNED NULL,
            pagado_en DATETIME NULL,
            PRIMARY KEY (id_pago_prog),
            UNIQUE KEY uq_cpp_curso_num (id_curso, numero),
            KEY idx_cpp_curso (id_curso, pagado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function curso_personalizado_puede_gestionar(): bool
{
    return function_exists('rbac_cap') && rbac_cap('curso_personalizado_gestionar');
}

/** @return array{ok:bool,message:string,id_curso?:int} */
function curso_personalizado_crear(PDO $pdo, array $data): array
{
    curso_personalizado_ensure_schema($pdo);
    $idAlumno = (int) ($data['id_alumno'] ?? 0);
    $titulo = trim((string) ($data['titulo'] ?? ''));
    $costo = round((float) ($data['costo_total'] ?? 0), 2);
    $numPagos = max(1, min(24, (int) ($data['num_pagos'] ?? 1)));
    if ($idAlumno <= 0 || $titulo === '' || $costo <= 0) {
        return ['ok' => false, 'message' => 'Alumno, título y costo son obligatorios'];
    }
    $idPlantel = (int) ($data['id_plantel'] ?? plantel_id_activo());
    $pdo->prepare(
        'INSERT INTO curso_personalizado (
            id_alumno, id_plantel, titulo, duracion_semanas, costo_total, num_pagos,
            id_especialidad_ref, temario_json, id_usuario_registro
        ) VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idAlumno,
        $idPlantel,
        $titulo,
        ($data['duracion_semanas'] ?? '') !== '' ? max(1, (int) $data['duracion_semanas']) : null,
        $costo,
        $numPagos,
        (int) ($data['id_especialidad_ref'] ?? 0) ?: null,
        !empty($data['temario_json']) ? json_encode($data['temario_json'], JSON_UNESCAPED_UNICODE) : null,
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
    ]);
    $idCurso = (int) $pdo->lastInsertId();
    $montoPago = round($costo / $numPagos, 2);
    $resto = $costo;
    $ins = $pdo->prepare(
        'INSERT INTO curso_personalizado_pago (id_curso, numero, monto, fecha_programada) VALUES (?,?,?,?)'
    );
    for ($i = 1; $i <= $numPagos; $i++) {
        $m = $i < $numPagos ? $montoPago : $resto;
        $resto -= $m;
        $fecha = !empty($data['fechas'][$i - 1]) ? $data['fechas'][$i - 1] : null;
        $ins->execute([$idCurso, $i, $m, $fecha]);
    }

    return ['ok' => true, 'message' => 'Contrato creado', 'id_curso' => $idCurso];
}

/** @return list<array<string, mixed>> */
function curso_personalizado_listar_alumno(PDO $pdo, int $idAlumno): array
{
    curso_personalizado_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT c.*, (SELECT COUNT(*) FROM curso_personalizado_pago p WHERE p.id_curso = c.id_curso AND p.pagado = 0) AS pagos_pendientes
         FROM curso_personalizado c WHERE c.id_alumno = ? AND c.estado = \'activo\' ORDER BY c.creado_en DESC'
    );
    $st->execute([$idAlumno]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function curso_personalizado_pagos_pendientes(PDO $pdo, int $idCurso): array
{
    curso_personalizado_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM curso_personalizado_pago WHERE id_curso = ? AND pagado = 0 ORDER BY numero ASC'
    );
    $st->execute([$idCurso]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function curso_personalizado_marcar_pago(PDO $pdo, int $idPagoProg, int $idPagoAlumno): void
{
    curso_personalizado_ensure_schema($pdo);
    $pdo->prepare(
        'UPDATE curso_personalizado_pago SET pagado = 1, id_pago_alumno = ?, pagado_en = NOW() WHERE id_pago_prog = ?'
    )->execute([$idPagoAlumno, $idPagoProg]);
}
