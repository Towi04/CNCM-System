<?php

/**
 * Áreas HAY múltiples por usuario (un solo acceso Google/Moodle).
 */

function hay_eval_migrate_multi_area(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!function_exists('plantel_ensure_column')) {
        return;
    }

    plantel_ensure_column(
        $pdo,
        'hay_area',
        'moodle_course_examen_id',
        'INT UNSIGNED NULL COMMENT \'Curso Moodle examen conocimientos candidatos/profesor\'',
        'descripcion'
    );
    plantel_ensure_column(
        $pdo,
        'hay_area',
        'alias_especialidad',
        'VARCHAR(255) NULL COMMENT \'Aliases separados por coma para mapear especialidad\'',
        'moodle_course_examen_id'
    );

    $idx = $pdo->query("SHOW INDEX FROM hay_area_usuario WHERE Key_name = 'PRIMARY'")->fetch(PDO::FETCH_ASSOC);
    $col = (string) ($idx['Column_name'] ?? '');
    if ($col === 'id_usuario') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS hay_area_usuario_new (
                id_usuario INT UNSIGNED NOT NULL,
                id_area INT UNSIGNED NOT NULL,
                es_principal TINYINT(1) NOT NULL DEFAULT 0,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id_usuario, id_area),
                KEY idx_hay_au_area (id_area),
                KEY idx_hay_au_principal (id_usuario, es_principal)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $rows = $pdo->query('SELECT id_usuario, id_area FROM hay_area_usuario')->fetchAll(PDO::FETCH_ASSOC);
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO hay_area_usuario_new (id_usuario, id_area, es_principal) VALUES (?,?,1)'
        );
        foreach ($rows as $r) {
            $ins->execute([(int) $r['id_usuario'], (int) $r['id_area']]);
        }
        $stU = $pdo->query(
            'SELECT id_usuario, id_hay_area FROM usuarios WHERE id_hay_area IS NOT NULL AND id_hay_area > 0'
        );
        $ins2 = $pdo->prepare(
            'INSERT IGNORE INTO hay_area_usuario_new (id_usuario, id_area, es_principal) VALUES (?,?,1)
             ON DUPLICATE KEY UPDATE es_principal = GREATEST(es_principal, VALUES(es_principal))'
        );
        foreach ($stU->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $ins2->execute([(int) $u['id_usuario'], (int) $u['id_hay_area']]);
        }
        $pdo->exec('DROP TABLE hay_area_usuario');
        $pdo->exec('RENAME TABLE hay_area_usuario_new TO hay_area_usuario');
    } else {
        plantel_ensure_column($pdo, 'hay_area_usuario', 'es_principal', 'TINYINT(1) NOT NULL DEFAULT 0', 'id_area');
        plantel_ensure_column($pdo, 'hay_area_usuario', 'creado_en', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'es_principal');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS docente_prospecto_area (
            id_prospecto INT UNSIGNED NOT NULL,
            id_area INT UNSIGNED NOT NULL,
            es_principal TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id_prospecto, id_area),
            KEY idx_dpa_area (id_area)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    hay_eval_backfill_prospecto_areas($pdo);
}

function hay_eval_backfill_prospecto_areas(PDO $pdo): void
{
    $st = $pdo->query(
        "SELECT id_prospecto, especialidad, id_hay_area FROM docente_prospecto
         WHERE id_prospecto NOT IN (SELECT id_prospecto FROM docente_prospecto_area) LIMIT 500"
    );
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($rows as $p) {
        $ids = [];
        if (!empty($p['id_hay_area'])) {
            $ids[] = (int) $p['id_hay_area'];
        }
        foreach (hay_eval_parse_especialidades_texto((string) ($p['especialidad'] ?? '')) as $esp) {
            $a = hay_eval_resolver_area_especialidad($pdo, $esp);
            if ($a) {
                $ids[] = (int) $a['id_area'];
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids) {
            docente_prospecto_guardar_areas($pdo, (int) $p['id_prospecto'], $ids, $ids[0]);
        }
    }
}

/** @return list<string> */
function hay_eval_parse_especialidades_texto(string $texto): array
{
    $texto = trim($texto);
    if ($texto === '') {
        return [];
    }
    $parts = preg_split('/[,;\/|]+/', $texto) ?: [];

    return array_values(array_filter(array_map('trim', $parts)));
}

/** @return array<string,mixed>|null */
function hay_eval_resolver_area_especialidad(PDO $pdo, string $especialidad): ?array
{
    hay_eval_ensure_schema($pdo);
    $esp = trim($especialidad);
    if ($esp === '') {
        return null;
    }
    $clave = catalog_normalizar_clave($esp, 40);
    $candidates = array_unique(array_filter([
        $clave,
        'PROF_' . $clave,
        str_replace('PROF_', '', $clave),
    ]));
    foreach ($candidates as $c) {
        $a = hay_eval_area_por_clave($pdo, $c);
        if ($a) {
            return $a;
        }
    }
    $like = '%' . $esp . '%';
    $st = $pdo->prepare(
        'SELECT * FROM hay_area WHERE activo = 1 AND (nombre LIKE ? OR clave LIKE ? OR alias_especialidad LIKE ?) LIMIT 1'
    );
    $st->execute([$like, $like, $like]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @return list<array<string,mixed>> */
function hay_eval_areas_usuario(PDO $pdo, int $idUsuario): array
{
    hay_eval_migrate_multi_area($pdo);
    $st = $pdo->prepare(
        'SELECT a.id_area, a.clave, a.nombre, a.moodle_course_examen_id, au.es_principal
         FROM hay_area_usuario au
         INNER JOIN hay_area a ON a.id_area = au.id_area AND a.activo = 1
         WHERE au.id_usuario = ?
         ORDER BY au.es_principal DESC, a.nombre ASC'
    );
    $st->execute([$idUsuario]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        return $rows;
    }

    $stL = $pdo->prepare('SELECT id_hay_area FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $stL->execute([$idUsuario]);
    $idLegacy = (int) ($stL->fetchColumn() ?: 0);
    if ($idLegacy > 0) {
        $a = hay_eval_obtener_area($pdo, $idLegacy);
        if ($a) {
            return [[
                'id_area' => (int) $a['id_area'],
                'clave' => $a['clave'],
                'nombre' => $a['nombre'],
                'moodle_course_examen_id' => $a['moodle_course_examen_id'] ?? null,
                'es_principal' => 1,
            ]];
        }
    }

    return [];
}

function hay_eval_area_principal(PDO $pdo, int $idUsuario): ?int
{
    foreach (hay_eval_areas_usuario($pdo, $idUsuario) as $a) {
        if (!empty($a['es_principal'])) {
            return (int) $a['id_area'];
        }
    }
    $areas = hay_eval_areas_usuario($pdo, $idUsuario);

    return $areas ? (int) $areas[0]['id_area'] : null;
}

/** @param list<int> $idAreas */
function hay_eval_asignar_areas_usuario(PDO $pdo, int $idUsuario, array $idAreas, ?int $idPrincipal = null): array
{
    hay_eval_migrate_multi_area($pdo);
    $idAreas = array_values(array_unique(array_filter(array_map('intval', $idAreas))));
    if (!$idAreas) {
        return ['ok' => false, 'message' => 'Indique al menos un área'];
    }
    if ($idPrincipal === null || !in_array($idPrincipal, $idAreas, true)) {
        $idPrincipal = $idAreas[0];
    }

    $pdo->prepare('DELETE FROM hay_area_usuario WHERE id_usuario = ?')->execute([$idUsuario]);
    $ins = $pdo->prepare(
        'INSERT INTO hay_area_usuario (id_usuario, id_area, es_principal) VALUES (?,?,?)'
    );
    foreach ($idAreas as $idA) {
        $ins->execute([$idUsuario, $idA, $idA === $idPrincipal ? 1 : 0]);
    }
    $pdo->prepare('UPDATE usuarios SET id_hay_area = ? WHERE id_usuario = ?')
        ->execute([$idPrincipal, $idUsuario]);

    return ['ok' => true, 'message' => 'Áreas asignadas', 'id_areas' => $idAreas, 'id_principal' => $idPrincipal];
}

function hay_eval_moodle_examen_area(PDO $pdo, int $idArea): int
{
    $a = hay_eval_obtener_area($pdo, $idArea);
    if (!$a) {
        return 0;
    }
    $course = (int) ($a['moodle_course_examen_id'] ?? 0);
    if ($course > 0) {
        return $course;
    }
    $st = $pdo->prepare(
        'SELECT moodle_course_id FROM expediente_requisito
         WHERE activo = 1 AND categoria = \'candidato_profesor\' AND moodle_course_id IS NOT NULL
         ORDER BY orden LIMIT 1'
    );
    $st->execute();

    return (int) ($st->fetchColumn() ?: 0);
}

/** @return list<array<string,mixed>> */
function docente_prospecto_areas(PDO $pdo, int $idProspecto): array
{
    hay_eval_migrate_multi_area($pdo);
    $st = $pdo->prepare(
        'SELECT a.id_area, a.clave, a.nombre, a.moodle_course_examen_id, dpa.es_principal
         FROM docente_prospecto_area dpa
         INNER JOIN hay_area a ON a.id_area = dpa.id_area
         WHERE dpa.id_prospecto = ?
         ORDER BY dpa.es_principal DESC, a.nombre ASC'
    );
    $st->execute([$idProspecto]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        return $rows;
    }

    $p = docente_prospecto_obtener($pdo, $idProspecto);
    if (!$p) {
        return [];
    }
    $ids = [];
    if (!empty($p['id_hay_area'])) {
        $ids[] = (int) $p['id_hay_area'];
    }
    foreach (hay_eval_parse_especialidades_texto((string) ($p['especialidad'] ?? '')) as $esp) {
        $a = hay_eval_resolver_area_especialidad($pdo, $esp);
        if ($a) {
            $ids[] = (int) $a['id_area'];
        }
    }
    $ids = array_values(array_unique(array_filter($ids)));
    if ($ids) {
        docente_prospecto_guardar_areas($pdo, $idProspecto, $ids, $ids[0]);

        return docente_prospecto_areas($pdo, $idProspecto);
    }

    return [];
}

/** @param list<int> $idAreas */
function docente_prospecto_guardar_areas(PDO $pdo, int $idProspecto, array $idAreas, ?int $idPrincipal = null): void
{
    hay_eval_migrate_multi_area($pdo);
    $idAreas = array_values(array_unique(array_filter(array_map('intval', $idAreas))));
    if (!$idAreas) {
        return;
    }
    if ($idPrincipal === null || !in_array($idPrincipal, $idAreas, true)) {
        $idPrincipal = $idAreas[0];
    }
    $pdo->prepare('DELETE FROM docente_prospecto_area WHERE id_prospecto = ?')->execute([$idProspecto]);
    $ins = $pdo->prepare(
        'INSERT INTO docente_prospecto_area (id_prospecto, id_area, es_principal) VALUES (?,?,?)'
    );
    foreach ($idAreas as $idA) {
        $ins->execute([$idProspecto, $idA, $idA === $idPrincipal ? 1 : 0]);
    }
    $pdo->prepare('UPDATE docente_prospecto SET id_hay_area = ? WHERE id_prospecto = ? AND id_plantel = ?')
        ->execute([$idPrincipal, $idProspecto, plantel_scope_id($pdo)]);
}

/** @param list<int>|null $idAreas */
function docente_prospecto_sincronizar_areas_desde_texto(PDO $pdo, int $idProspecto, string $especialidad, ?array $idAreas = null): void
{
    if ($idAreas !== null && $idAreas !== []) {
        docente_prospecto_guardar_areas($pdo, $idProspecto, $idAreas);

        return;
    }
    $ids = [];
    foreach (hay_eval_parse_especialidades_texto($especialidad) as $esp) {
        $a = hay_eval_resolver_area_especialidad($pdo, $esp);
        if ($a) {
            $ids[] = (int) $a['id_area'];
        }
    }
    if ($ids) {
        docente_prospecto_guardar_areas($pdo, $idProspecto, $ids);
    }
}

function hay_eval_usuario_pertenece_area(PDO $pdo, int $idUsuario, int $idArea): bool
{
    foreach (hay_eval_areas_usuario($pdo, $idUsuario) as $a) {
        if ((int) $a['id_area'] === $idArea) {
            return true;
        }
    }

    return false;
}
