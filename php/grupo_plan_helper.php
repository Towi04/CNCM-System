<?php

/**
 * Planificación de parciales por mes (solo coordinación).
 * El alumno ve la secuencia normal; el temario comprimido y qué retomar quedan internos.
 */

function grupo_plan_puede_editar(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
    if ($rol === 'profesor') {
        return false;
    }
    if (function_exists('rbac_cap') && rbac_cap('menu_grupo_plan')) {
        return in_array($rol, ['coordinador', 'coordinacion', 'director', 'admin', 'gerente', 'supervisor'], true);
    }

    return in_array($rol, ['coordinador', 'coordinacion', 'director', 'admin', 'gerente', 'supervisor'], true);
}

function grupo_plan_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_plan_periodo (
            id_plan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo INT UNSIGNED NOT NULL,
            anio SMALLINT UNSIGNED NOT NULL,
            mes TINYINT UNSIGNED NOT NULL COMMENT "1-12",
            id_fase_registro INT UNSIGNED NOT NULL COMMENT "Parcial que se registra al alumno",
            fases_temario_json JSON NULL COMMENT "Parciales cuyo temario se imparte",
            nota_coordinador TEXT NULL,
            temas_retomar TEXT NULL,
            pendiente_retomar TINYINT(1) NOT NULL DEFAULT 0,
            cerrado TINYINT(1) NOT NULL DEFAULT 0,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_plan),
            UNIQUE KEY uq_grupo_anio_mes (id_grupo, anio, mes),
            KEY idx_gpp_grupo (id_grupo),
            KEY idx_gpp_pendiente (id_grupo, pendiente_retomar)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

/** @return array<string, mixed>|null */
function grupo_plan_obtener(PDO $pdo, int $idGrupo, int $anio, int $mes): ?array
{
    $st = $pdo->prepare(
        'SELECT p.*, fr.clave_fase AS clave_registro, fr.nombre_fase AS nombre_registro
         FROM grupo_plan_periodo p
         INNER JOIN especialidad_fases fr ON fr.id_fase = p.id_fase_registro
         WHERE p.id_grupo = ? AND p.anio = ? AND p.mes = ?'
    );
    $st->execute([$idGrupo, $anio, $mes]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['fases_temario'] = grupo_plan_decode_fases_temario($pdo, $row['fases_temario_json'] ?? null);
    return $row;
}

/** @return list<array<string, mixed>> */
function grupo_plan_listar_grupo(PDO $pdo, int $idGrupo, ?int $anio = null): array
{
    $sql = 'SELECT p.*, fr.clave_fase AS clave_registro, fr.nombre_fase AS nombre_registro
            FROM grupo_plan_periodo p
            INNER JOIN especialidad_fases fr ON fr.id_fase = p.id_fase_registro
            WHERE p.id_grupo = ?';
    $params = [$idGrupo];
    if ($anio !== null) {
        $sql .= ' AND p.anio = ?';
        $params[] = $anio;
    }
    $sql .= ' ORDER BY p.anio DESC, p.mes DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['fases_temario'] = grupo_plan_decode_fases_temario($pdo, $row['fases_temario_json'] ?? null);
        $out[] = $row;
    }
    return $out;
}

/** @return list<array<string, mixed>> */
function grupo_plan_pendientes_retomar(PDO $pdo, int $idGrupo): array
{
    $st = $pdo->prepare(
        'SELECT p.*, fr.nombre_fase AS nombre_registro
         FROM grupo_plan_periodo p
         INNER JOIN especialidad_fases fr ON fr.id_fase = p.id_fase_registro
         WHERE p.id_grupo = ? AND p.pendiente_retomar = 1 AND (p.temas_retomar IS NOT NULL OR p.nota_coordinador IS NOT NULL)
         ORDER BY p.anio, p.mes'
    );
    $st->execute([$idGrupo]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['fases_temario'] = grupo_plan_decode_fases_temario($pdo, $row['fases_temario_json'] ?? null);
        $out[] = $row;
    }
    return $out;
}

/**
 * @param list<int> $idsFasesTemario
 * @return array{ok: bool, message: string}
 */
function grupo_plan_guardar(
    PDO $pdo,
    int $idGrupo,
    int $anio,
    int $mes,
    int $idFaseRegistro,
    array $idsFasesTemario,
    string $notaCoordinador = '',
    string $temasRetomar = '',
    bool $pendienteRetomar = false,
    ?int $idUsuario = null
): array {
    if ($mes < 1 || $mes > 12) {
        return ['ok' => false, 'message' => 'Mes inválido'];
    }
    if ($idFaseRegistro <= 0) {
        return ['ok' => false, 'message' => 'Indique el parcial que se registra al alumno'];
    }

    $idsFasesTemario = array_values(array_unique(array_map('intval', $idsFasesTemario)));
    if (!in_array($idFaseRegistro, $idsFasesTemario, true)) {
        $idsFasesTemario[] = $idFaseRegistro;
    }

    $json = json_encode($idsFasesTemario, JSON_UNESCAPED_UNICODE);
    $hayCompresion = count($idsFasesTemario) > 1;
    if ($hayCompresion && trim($temasRetomar) === '' && trim($notaCoordinador) === '') {
        return [
            'ok' => false,
            'message' => 'Si imparte temario de más de un parcial, indique qué temas retomar o una nota para coordinación.',
        ];
    }

    $pdo->prepare(
        'INSERT INTO grupo_plan_periodo (
            id_grupo, anio, mes, id_fase_registro, fases_temario_json,
            nota_coordinador, temas_retomar, pendiente_retomar, id_usuario
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            id_fase_registro = VALUES(id_fase_registro),
            fases_temario_json = VALUES(fases_temario_json),
            nota_coordinador = VALUES(nota_coordinador),
            temas_retomar = VALUES(temas_retomar),
            pendiente_retomar = VALUES(pendiente_retomar),
            id_usuario = VALUES(id_usuario)'
    )->execute([
        $idGrupo,
        $anio,
        $mes,
        $idFaseRegistro,
        $json,
        trim($notaCoordinador) ?: null,
        trim($temasRetomar) ?: null,
        $pendienteRetomar || $hayCompresion ? 1 : 0,
        $idUsuario,
    ]);

    return ['ok' => true, 'message' => 'Plan del periodo guardado'];
}

function grupo_plan_marcar_retomado(PDO $pdo, int $idPlan): void
{
    $pdo->prepare('UPDATE grupo_plan_periodo SET pendiente_retomar = 0 WHERE id_plan = ?')->execute([$idPlan]);
}

/** Parcial que el alumno debe ver para un mes (registro oficial). */
function grupo_plan_fase_registro_alumno(PDO $pdo, int $idGrupo, int $anio, int $mes): ?int
{
    $p = grupo_plan_obtener($pdo, $idGrupo, $anio, $mes);
    return $p ? (int) $p['id_fase_registro'] : null;
}

/** Temario interno del mes (puede incluir varias fases). @return list<int> */
function grupo_plan_fases_temario_ids(PDO $pdo, int $idGrupo, int $anio, int $mes): array
{
    $p = grupo_plan_obtener($pdo, $idGrupo, $anio, $mes);
    if (!$p) {
        return [];
    }
    return array_map(static fn ($f) => (int) $f['id_fase'], $p['fases_temario']);
}

/** @return list<array{id_fase: int, clave_fase: string, nombre_fase: string}> */
function grupo_plan_decode_fases_temario(PDO $pdo, ?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    $ids = json_decode($json, true);
    if (!is_array($ids) || $ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare(
        "SELECT id_fase, clave_fase, nombre_fase FROM especialidad_fases WHERE id_fase IN ($placeholders) ORDER BY orden"
    );
    $st->execute(array_map('intval', $ids));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Etiqueta amigable para coordinador (sin lenguaje de “adelanto” hacia el alumno). */
function grupo_plan_resumen_coordinador(array $plan): string
{
    $reg = $plan['clave_registro'] ?? $plan['nombre_registro'] ?? '';
    $temas = $plan['fases_temario'] ?? [];
    $otros = array_filter($temas, static fn ($f) => (int) $f['id_fase'] !== (int) ($plan['id_fase_registro'] ?? 0));
    if ($otros === []) {
        return 'Registro alumno: ' . $reg;
    }
    $extra = implode(', ', array_map(static fn ($f) => $f['clave_fase'] ?: $f['nombre_fase'], $otros));
    return 'Registro alumno: ' . $reg . ' · Temario impartido también: ' . $extra;
}

/** Meses en español */
function grupo_plan_mes_label(int $mes): string
{
    $n = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return $n[$mes] ?? (string) $mes;
}
