<?php

/**
 * Docentes asignados a un grupo (uno o varios por materia).
 * grupos.id_profesor se mantiene como titular/principal por compatibilidad.
 */

/** @var array<string, string> Materias típicas de preparatoria (PA / PE). */
const GRUPO_MATERIAS_PREP = [
    'matematicas' => 'Matemáticas',
    'espanol' => 'Español / Lectura y redacción',
    'fisica' => 'Física',
    'quimica' => 'Química',
    'biologia' => 'Biología',
    'historia' => 'Historia',
    'formacion_civica' => 'Formación cívica / Temas sociales',
    'filosofia' => 'Filosofía / Desarrollo humano',
    'salud' => 'Salud pública',
    'computacion' => 'Computación',
    'ingles' => 'Inglés',
];

function grupo_docente_ensure_schema(PDO $pdo): void
{
    academico_ensure_schema($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_docente (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo INT UNSIGNED NOT NULL,
            id_profesor INT UNSIGNED NOT NULL,
            materia_clave VARCHAR(80) NOT NULL DEFAULT \'\',
            materia_nombre VARCHAR(160) NULL,
            es_titular TINYINT(1) NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_grupo_docente_materia (id_grupo, materia_clave),
            KEY idx_grupo_docente_prof (id_profesor, id_grupo),
            KEY idx_grupo_docente_grupo (id_grupo, activo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    grupo_docente_backfill_legacy($pdo);
}

function grupo_docente_backfill_legacy(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $rows = $pdo->query(
        'SELECT g.id_grupo, g.id_profesor
         FROM grupos g
         WHERE g.id_profesor IS NOT NULL AND g.id_profesor > 0
           AND NOT EXISTS (SELECT 1 FROM grupo_docente gd WHERE gd.id_grupo = g.id_grupo)
         LIMIT 5000'
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        return;
    }

    $ins = $pdo->prepare(
        'INSERT INTO grupo_docente (id_grupo, id_profesor, materia_clave, materia_nombre, es_titular, activo)
         VALUES (?,?,?,?,1,1)'
    );
    foreach ($rows as $r) {
        $ins->execute([(int) $r['id_grupo'], (int) $r['id_profesor'], '', 'General', 1]);
    }
}

/** Áreas / tipos de grupo que suelen requerir varios docentes por materia. */
function grupo_docente_requiere_multi_materia(array $ctx): bool
{
    $codigoArea = strtoupper(trim((string) ($ctx['codigo_area'] ?? '')));
    $personalizado = !empty($ctx['es_personalizado']) || !empty($ctx['personalizado']);
    $extensivo = !empty($ctx['es_extensivo']) || !empty($ctx['extensivo']);

    if (in_array($codigoArea, ['PA', 'PE'], true)) {
        return true;
    }
    if ($personalizado || $extensivo) {
        return true;
    }

    $clave = strtoupper(trim((string) ($ctx['clave'] ?? '')));
    if (str_starts_with($clave, 'PA') || str_starts_with($clave, 'PE') || str_starts_with($clave, 'PER-')) {
        return true;
    }
    if (str_starts_with($clave, 'E') && (str_contains($clave, 'PA') || str_contains($clave, 'PE'))) {
        return true;
    }

    return false;
}

/** @return list<array{clave: string, nombre: string}> */
function grupo_docente_materias_sugeridas(array $ctx): array
{
    $codigoArea = strtoupper(trim((string) ($ctx['codigo_area'] ?? '')));
    if (in_array($codigoArea, ['PA', 'PE'], true)) {
        $out = [];
        foreach (GRUPO_MATERIAS_PREP as $k => $n) {
            $out[] = ['clave' => $k, 'nombre' => $n];
        }

        return $out;
    }

    return [['clave' => '', 'nombre' => 'General']];
}

/** @return list<array<string, mixed>> */
function grupo_docente_listar_profesores_plantel(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        "SELECT u.id_usuario, u.nombre, u.apellido, u.rol, u.id_hay_area,
                GROUP_CONCAT(DISTINCT ha.nombre ORDER BY ha.nombre SEPARATOR ', ') AS hay_areas
         FROM usuarios u
         LEFT JOIN hay_area_usuario hau ON hau.id_usuario = u.id_usuario
         LEFT JOIN hay_area ha ON ha.id_area = hau.id_area
         WHERE u.id_plantel = ? AND u.suspendido = 0
           AND u.rol IN ('profesor','gerente','supervisor')
         GROUP BY u.id_usuario, u.nombre, u.apellido, u.rol, u.id_hay_area
         ORDER BY u.nombre, u.apellido"
    );
    $st->execute([$idPlantel]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $r['nombre_completo'] = trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? ''));
        if (empty($r['hay_areas']) && !empty($r['id_hay_area'])) {
            $stA = $pdo->prepare('SELECT nombre FROM hay_area WHERE id_area = ? LIMIT 1');
            $stA->execute([(int) $r['id_hay_area']]);
            $r['hay_areas'] = (string) ($stA->fetchColumn() ?: '');
        }
    }
    unset($r);

    return $rows;
}

/** @return list<array<string, mixed>> */
function grupo_docente_listar_grupo(PDO $pdo, int $idGrupo): array
{
    grupo_docente_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT gd.*, CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre
         FROM grupo_docente gd
         INNER JOIN usuarios u ON u.id_usuario = gd.id_profesor
         WHERE gd.id_grupo = ? AND gd.activo = 1
         ORDER BY gd.es_titular DESC, gd.materia_nombre ASC, gd.id ASC'
    );
    $st->execute([$idGrupo]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function grupo_docente_profesor_imparte(PDO $pdo, int $idGrupo, int $idProfesor): bool
{
    if ($idGrupo <= 0 || $idProfesor <= 0) {
        return false;
    }
    grupo_docente_ensure_schema($pdo);

    $st = $pdo->prepare('SELECT id_profesor FROM grupos WHERE id_grupo = ? LIMIT 1');
    $st->execute([$idGrupo]);
    if ((int) $st->fetchColumn() === $idProfesor) {
        return true;
    }

    $st2 = $pdo->prepare(
        'SELECT 1 FROM grupo_docente WHERE id_grupo = ? AND id_profesor = ? AND activo = 1 LIMIT 1'
    );
    $st2->execute([$idGrupo, $idProfesor]);

    return (bool) $st2->fetchColumn();
}

/**
 * Fragmento SQL AND para filtrar grupos por profesor (titular o asignado por materia).
 * Añade dos placeholders ? con el mismo id_profesor.
 */
function grupo_docente_sql_filtro_profesor(string $aliasGrupo = 'g'): string
{
    return "({$aliasGrupo}.id_profesor = ? OR EXISTS (
        SELECT 1 FROM grupo_docente gd
        WHERE gd.id_grupo = {$aliasGrupo}.id_grupo AND gd.id_profesor = ? AND gd.activo = 1
    ))";
}

/** @param list<int> $params Referencia: se añaden dos veces el id del profesor. */
function grupo_docente_bind_filtro_profesor(int $idProfesor, array &$params): void
{
    $params[] = $idProfesor;
    $params[] = $idProfesor;
}

/**
 * @param list<array{id_profesor: int, materia_clave?: string, materia_nombre?: string, es_titular?: bool}> $asignaciones
 */
function grupo_docente_guardar(PDO $pdo, int $idGrupo, int $idPlantel, array $asignaciones): array
{
    grupo_docente_ensure_schema($pdo);

    if ($idGrupo <= 0 || !plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel)) {
        return ['ok' => false, 'message' => 'Grupo no válido'];
    }

    $limpias = [];
    $vistos = [];
    foreach ($asignaciones as $a) {
        $idProf = (int) ($a['id_profesor'] ?? 0);
        if ($idProf <= 0) {
            continue;
        }
        if (!plantel_usuario_pertenece($pdo, $idProf, $idPlantel)) {
            return ['ok' => false, 'message' => 'Profesor no pertenece al plantel'];
        }
        $clave = grupo_docente_normalizar_clave((string) ($a['materia_clave'] ?? ''));
        $nombre = trim((string) ($a['materia_nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = $clave !== '' ? (GRUPO_MATERIAS_PREP[$clave] ?? ucfirst(str_replace('_', ' ', $clave))) : 'General';
        }
        if (isset($vistos[$clave])) {
            continue;
        }
        $vistos[$clave] = true;
        $limpias[] = [
            'id_profesor' => $idProf,
            'materia_clave' => $clave,
            'materia_nombre' => $nombre,
            'es_titular' => !empty($a['es_titular']),
        ];
    }

    $pdo->prepare('UPDATE grupo_docente SET activo = 0 WHERE id_grupo = ?')->execute([$idGrupo]);

    if ($limpias === []) {
        $pdo->prepare('UPDATE grupos SET id_profesor = NULL WHERE id_grupo = ? AND id_plantel = ?')
            ->execute([$idGrupo, $idPlantel]);

        return ['ok' => true, 'message' => 'Sin docentes asignados', 'id_profesor_titular' => null];
    }

    $ins = $pdo->prepare(
        'INSERT INTO grupo_docente (id_grupo, id_profesor, materia_clave, materia_nombre, es_titular, activo)
         VALUES (?,?,?,?,?,1)
         ON DUPLICATE KEY UPDATE id_profesor = VALUES(id_profesor), materia_nombre = VALUES(materia_nombre),
             es_titular = VALUES(es_titular), activo = 1'
    );

    $titular = null;
    foreach ($limpias as $i => $a) {
        $esTit = $a['es_titular'] || ($titular === null && $i === 0);
        if ($esTit) {
            $titular = $a['id_profesor'];
        }
        $ins->execute([
            $idGrupo,
            $a['id_profesor'],
            $a['materia_clave'],
            $a['materia_nombre'],
            $esTit ? 1 : 0,
        ]);
    }

    if ($titular === null) {
        $titular = $limpias[0]['id_profesor'];
    }

    $pdo->prepare('UPDATE grupos SET id_profesor = ? WHERE id_grupo = ? AND id_plantel = ?')
        ->execute([$titular, $idGrupo, $idPlantel]);

    return ['ok' => true, 'message' => 'Docentes guardados', 'id_profesor_titular' => $titular];
}

/** @return list<array{id_profesor: int, materia_clave: string, materia_nombre: string, es_titular: bool}> */
function grupo_docente_parse_post(array $post): array
{
    $out = [];

    if (!empty($post['docente']) && is_array($post['docente'])) {
        foreach ($post['docente'] as $clave => $idProf) {
            $idProf = (int) $idProf;
            if ($idProf <= 0) {
                continue;
            }
            $claveStr = is_string($clave) ? $clave : '';
            $out[] = [
                'id_profesor' => $idProf,
                'materia_clave' => $claveStr,
                'materia_nombre' => (string) ($post['docente_nombre'][$claveStr] ?? ''),
                'es_titular' => !empty($post['docente_titular']) && (string) $post['docente_titular'] === $claveStr,
            ];
        }
    }

    if ($out === [] && !empty($post['docente_materia']) && is_array($post['docente_materia'])) {
        $materias = $post['docente_materia'];
        $profesores = $post['docente_profesor'] ?? [];
        $titularIdx = (int) ($post['docente_titular_idx'] ?? 0);
        foreach ($materias as $i => $mat) {
            $idProf = (int) ($profesores[$i] ?? 0);
            if ($idProf <= 0) {
                continue;
            }
            $mat = trim((string) $mat);
            $clave = grupo_docente_normalizar_clave($mat);
            $out[] = [
                'id_profesor' => $idProf,
                'materia_clave' => $clave,
                'materia_nombre' => $mat,
                'es_titular' => $titularIdx === (int) $i,
            ];
        }
    }

    $idUnico = (int) ($post['id_profesor'] ?? 0);
    if ($out === [] && $idUnico > 0) {
        $out[] = [
            'id_profesor' => $idUnico,
            'materia_clave' => '',
            'materia_nombre' => 'General',
            'es_titular' => true,
        ];
    }

    return $out;
}

function grupo_docente_normalizar_clave(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (isset(GRUPO_MATERIAS_PREP[$raw])) {
        return $raw;
    }
    $k = strtolower($raw);
    $k = preg_replace('/[^a-z0-9]+/', '_', $k) ?? $k;
    $k = trim($k, '_');

    return substr($k, 0, 80);
}

function grupo_docente_puede_gestionar(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    $rol = rbac_rol_efectivo();

    return in_array($rol, ['coordinador', 'coordinacion', 'director', 'supervisor', 'gerente', 'admin'], true);
}

/** Etiqueta legible de docentes del grupo para listados. */
function grupo_docente_etiqueta_listado(PDO $pdo, int $idGrupo, ?string $fallbackNombre = null): string
{
    $docentes = grupo_docente_listar_grupo($pdo, $idGrupo);
    if ($docentes === []) {
        return trim((string) $fallbackNombre) ?: '—';
    }
    if (count($docentes) === 1) {
        return trim((string) ($docentes[0]['profesor_nombre'] ?? '')) ?: '—';
    }
    $parts = [];
    foreach ($docentes as $d) {
        $nom = trim((string) ($d['profesor_nombre'] ?? ''));
        $mat = trim((string) ($d['materia_nombre'] ?? ''));
        if ($mat !== '' && $mat !== 'General') {
            $parts[] = $mat . ': ' . $nom;
        } else {
            $parts[] = $nom;
        }
    }

    return implode(' · ', array_filter($parts));
}
