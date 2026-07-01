<?php

/**
 * Versionado de planes de estudio por especialidad.
 */

function plan_version_ensure_schema(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }
    fase_ensure_schema($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS plan_estudio_version (
            id_plan_version INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_especialidad INT UNSIGNED NOT NULL,
            version_label VARCHAR(40) NOT NULL,
            vigente_desde DATE NULL,
            activo_para_nuevos TINYINT(1) NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            id_usuario INT UNSIGNED NULL,
            PRIMARY KEY (id_plan_version),
            KEY idx_pev_esp (id_especialidad, activo_para_nuevos)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_plan_asignado (
            id_asignacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_especialidad INT UNSIGNED NOT NULL,
            id_plan_version INT UNSIGNED NOT NULL,
            fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_asignacion),
            UNIQUE KEY uq_apa_alumno_esp (id_alumno, id_especialidad),
            KEY idx_apa_plan (id_plan_version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    plantel_ensure_column($pdo, 'especialidad_fases', 'id_plan_version', 'INT UNSIGNED NULL', 'id_especialidad');

    if (hay_meta_get($pdo, 'plan_version_v1_seeded') !== '1') {
        plan_version_seed_inicial($pdo);
        hay_meta_set($pdo, 'plan_version_v1_seeded', '1');
    }
}

function plan_version_seed_inicial(PDO $pdo): void
{
    $st = $pdo->query('SELECT id_especialidad FROM especialidades WHERE activo = 1');
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $idEsp) {
        $idEsp = (int) $idEsp;
        $chk = $pdo->prepare('SELECT id_plan_version FROM plan_estudio_version WHERE id_especialidad = ? LIMIT 1');
        $chk->execute([$idEsp]);
        $idPlan = (int) ($chk->fetchColumn() ?: 0);
        if ($idPlan <= 0) {
            $pdo->prepare(
                'INSERT INTO plan_estudio_version (id_especialidad, version_label, vigente_desde, activo_para_nuevos)
                 VALUES (?, ?, CURDATE(), 1)'
            )->execute([$idEsp, 'v1']);
            $idPlan = (int) $pdo->lastInsertId();
        }
        $pdo->prepare(
            'UPDATE especialidad_fases SET id_plan_version = ? WHERE id_especialidad = ? AND (id_plan_version IS NULL OR id_plan_version = 0)'
        )->execute([$idPlan, $idEsp]);
    }
}

/** @return array<string, mixed>|null */
function plan_version_activo_nuevos(PDO $pdo, int $idEspecialidad): ?array
{
    plan_version_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM plan_estudio_version
         WHERE id_especialidad = ? AND activo_para_nuevos = 1
         ORDER BY id_plan_version DESC LIMIT 1'
    );
    $st->execute([$idEspecialidad]);
    $r = $st->fetch(PDO::FETCH_ASSOC);

    return $r ?: null;
}

/** @return list<array<string, mixed>> */
function plan_version_listar(PDO $pdo, int $idEspecialidad): array
{
    plan_version_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT pv.*, (SELECT COUNT(*) FROM especialidad_fases ef WHERE ef.id_plan_version = pv.id_plan_version) AS num_fases
         FROM plan_estudio_version pv
         WHERE pv.id_especialidad = ?
         ORDER BY pv.id_plan_version DESC'
    );
    $st->execute([$idEspecialidad]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Asigna plan vigente al inscribir alumno en especialidad. */
function plan_version_asignar_alumno(PDO $pdo, int $idAlumno, int $idEspecialidad, ?int $idPlan = null): int
{
    plan_version_ensure_schema($pdo);
    if ($idPlan === null || $idPlan <= 0) {
        $activo = plan_version_activo_nuevos($pdo, $idEspecialidad);
        $idPlan = $activo ? (int) $activo['id_plan_version'] : 0;
    }
    if ($idPlan <= 0) {
        return 0;
    }
    $pdo->prepare(
        'INSERT INTO alumno_plan_asignado (id_alumno, id_especialidad, id_plan_version)
         VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE id_plan_version = VALUES(id_plan_version), fecha_asignacion = NOW()'
    )->execute([$idAlumno, $idEspecialidad, $idPlan]);

    return $idPlan;
}

/** @return int Plan asignado o activo para nuevos. */
function plan_version_para_alumno(PDO $pdo, int $idAlumno, int $idEspecialidad): int
{
    plan_version_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT id_plan_version FROM alumno_plan_asignado WHERE id_alumno = ? AND id_especialidad = ? LIMIT 1'
    );
    $st->execute([$idAlumno, $idEspecialidad]);
    $id = (int) ($st->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }
    $activo = plan_version_activo_nuevos($pdo, $idEspecialidad);

    return $activo ? (int) $activo['id_plan_version'] : 0;
}

/** Publica nueva versión copiando fases del plan activo. */
function plan_version_publicar(PDO $pdo, int $idEspecialidad, string $label): array
{
    plan_version_ensure_schema($pdo);
    $label = trim($label) !== '' ? trim($label) : ('v' . date('Y'));
    $activo = plan_version_activo_nuevos($pdo, $idEspecialidad);
    $pdo->prepare('UPDATE plan_estudio_version SET activo_para_nuevos = 0 WHERE id_especialidad = ?')
        ->execute([$idEspecialidad]);
    $pdo->prepare(
        'INSERT INTO plan_estudio_version (id_especialidad, version_label, vigente_desde, activo_para_nuevos, id_usuario)
         VALUES (?,?,CURDATE(),1,?)'
    )->execute([$idEspecialidad, $label, (int) ($_SESSION['user_id'] ?? 0) ?: null]);
    $idNuevo = (int) $pdo->lastInsertId();
    $idOrigen = $activo ? (int) $activo['id_plan_version'] : 0;
    if ($idOrigen > 0) {
        $fases = $pdo->prepare('SELECT * FROM especialidad_fases WHERE id_plan_version = ? AND activo = 1');
        $fases->execute([$idOrigen]);
        foreach ($fases->fetchAll(PDO::FETCH_ASSOC) as $f) {
            unset($f['id_fase']);
            $f['id_plan_version'] = $idNuevo;
            $cols = array_keys($f);
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $pdo->prepare('INSERT INTO especialidad_fases (' . implode(',', $cols) . ') VALUES (' . $ph . ')')
                ->execute(array_values($f));
        }
    }

    return ['ok' => true, 'id_plan_version' => $idNuevo, 'label' => $label];
}

/** @return list<array<string, mixed>> */
function plan_version_fases(PDO $pdo, int $idEspecialidad, ?int $idPlan = null): array
{
    plan_version_ensure_schema($pdo);
    if ($idPlan === null || $idPlan <= 0) {
        $idPlan = plan_version_activo_nuevos($pdo, $idEspecialidad)['id_plan_version'] ?? 0;
    }
    if ($idPlan <= 0) {
        return function_exists('fase_listar_directo')
            ? fase_listar_directo($pdo, $idEspecialidad)
            : [];
    }
    $st = $pdo->prepare(
        'SELECT * FROM especialidad_fases WHERE id_especialidad = ? AND id_plan_version = ? AND activo = 1 ORDER BY orden ASC'
    );
    $st->execute([$idEspecialidad, $idPlan]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
