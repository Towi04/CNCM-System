<?php

/**
 * Fases por especialidad — coordinación académica.
 */

define('FASE_DURACION_DEFAULT', 4);

function fase_ensure_schema(PDO $pdo): void
{
    catalog_ensure_schema($pdo);
    alumno_ensure_schema($pdo);

    plantel_ensure_column($pdo, 'especialidades', 'modalidad', "ENUM('regular','kids','prep_abierta','prep_escolarizada','extensivo') NOT NULL DEFAULT 'regular'", 'descripcion');
    plantel_ensure_column($pdo, 'especialidades', 'duracion_fase_semanas', 'SMALLINT UNSIGNED NOT NULL DEFAULT 4', 'modalidad');
    plantel_ensure_column($pdo, 'especialidades', 'inscripcion_por_cuatrimestre', 'TINYINT(1) NOT NULL DEFAULT 0', 'duracion_fase_semanas');
    plantel_ensure_column($pdo, 'especialidades', 'parciales_por_cuatrimestre', 'TINYINT UNSIGNED NOT NULL DEFAULT 0', 'inscripcion_por_cuatrimestre');

    plantel_ensure_column($pdo, 'especialidad_fases', 'descripcion', 'TEXT NULL', 'nombre_fase');
    plantel_ensure_column($pdo, 'especialidad_fases', 'temas', 'TEXT NULL', 'descripcion');
    plantel_ensure_column($pdo, 'especialidad_fases', 'practicas_sugeridas', 'TEXT NULL', 'temas');
    plantel_ensure_column($pdo, 'especialidad_fases', 'asesoria', 'TEXT NULL', 'practicas_sugeridas');
    plantel_ensure_column($pdo, 'especialidad_fases', 'duracion_semanas', 'SMALLINT UNSIGNED NULL', 'asesoria');
    plantel_ensure_column($pdo, 'especialidad_fases', 'clave_fase', 'VARCHAR(40) NULL', 'duracion_semanas');
    plantel_ensure_column($pdo, 'especialidad_fases', 'nivel_cefr', 'VARCHAR(20) NULL', 'clave_fase');
    plantel_ensure_column($pdo, 'especialidad_fases', 'num_parcial', 'TINYINT UNSIGNED NULL', 'nivel_cefr');
    plantel_ensure_column($pdo, 'especialidad_fases', 'objetivo_parcial', 'TEXT NULL', 'asesoria');
    plantel_ensure_column($pdo, 'especialidad_fases', 'tipo_contenido', "ENUM('regular','proyecto_nivel','proyecto_final') NOT NULL DEFAULT 'regular'", 'objetivo_parcial');
    plantel_ensure_column($pdo, 'especialidad_fases', 'eval_listening', 'TEXT NULL', 'tipo_contenido');
    plantel_ensure_column($pdo, 'especialidad_fases', 'eval_reading', 'TEXT NULL', 'eval_listening');
    plantel_ensure_column($pdo, 'especialidad_fases', 'eval_writing', 'TEXT NULL', 'eval_reading');
    plantel_ensure_column($pdo, 'especialidad_fases', 'eval_speaking', 'TEXT NULL', 'eval_writing');
    plantel_ensure_column($pdo, 'especialidad_fases', 'eval_grammar', 'TEXT NULL', 'eval_speaking');
    plantel_ensure_column($pdo, 'especialidad_fases', 'eval_vocabulary', 'TEXT NULL', 'eval_grammar');
    plantel_ensure_column($pdo, 'especialidad_fases', 'vocabulario_resumen', 'TEXT NULL', 'eval_vocabulary');
    plantel_ensure_column($pdo, 'especialidad_fases', 'gramatica_resumen', 'TEXT NULL', 'vocabulario_resumen');
    plantel_ensure_column($pdo, 'especialidad_fases', 'eval_criterios_json', 'TEXT NULL', 'gramatica_resumen');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS fase_temario_semana (
            id_semana INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_fase INT UNSIGNED NOT NULL,
            semana TINYINT UNSIGNED NOT NULL,
            titulo_leccion VARCHAR(160) NULL,
            objetivo TEXT NULL,
            vocabulario TEXT NULL,
            gramatica TEXT NULL,
            listening TEXT NULL,
            reading TEXT NULL,
            writing TEXT NULL,
            speaking TEXT NULL,
            notas TEXT NULL,
            es_examen TINYINT(1) NOT NULL DEFAULT 0,
            proyecto_tipo VARCHAR(80) NULL,
            PRIMARY KEY (id_semana),
            UNIQUE KEY uq_fase_semana (id_fase, semana),
            KEY idx_fts_fase (id_fase)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    try {
        fase_seed_especialidades_catalogo($pdo);
        fase_seed_fases_desde_catalogo($pdo);
        fase_sync_ingles_nomenclatura($pdo);
    } catch (Throwable $e) {
        error_log('fase_seed: ' . $e->getMessage());
    }
}

function fase_puede_editar(): bool
{
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
    return in_array($rol, ['admin', 'director', 'coordinador', 'profesor', 'supervisor'], true);
}

/** Inglés adulto, extensivo o kids (clave ING, ING-EXT, ING-K, ING-K-EXT…). */
function fase_es_especialidad_ingles(?array $esp): bool
{
    if (!$esp) {
        return false;
    }
    $clave = strtoupper(trim((string) ($esp['clave'] ?? '')));

    return $clave !== '' && preg_match('/^ING(?:-|$)/', $clave) === 1;
}

/** @return list<array{nombre: string, descripcion: string}> */
function fase_eval_criterios_genericos(array $f): array
{
    $raw = trim((string) ($f['eval_criterios_json'] ?? ''));
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $nombre = trim((string) ($item['nombre'] ?? ''));
        $desc = trim((string) ($item['descripcion'] ?? ''));
        if ($nombre === '' && $desc === '') {
            continue;
        }
        $out[] = ['nombre' => $nombre, 'descripcion' => $desc];
    }

    return $out;
}

/** @return array<int, array<string, mixed>> */
function fase_listar_directo(PDO $pdo, int $idEspecialidad, ?int $idPlanVersion = null): array
{
    $sql = 'SELECT * FROM especialidad_fases WHERE id_especialidad = ? AND activo = 1';
    $params = [$idEspecialidad];
    if ($idPlanVersion !== null && $idPlanVersion > 0) {
        $sql .= ' AND id_plan_version = ?';
        $params[] = $idPlanVersion;
    }
    $sql .= ' ORDER BY orden ASC, id_fase ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fase_listar(PDO $pdo, int $idEspecialidad, ?int $idPlanVersion = null): array
{
    if (function_exists('plan_version_fases') && ($idPlanVersion === null || $idPlanVersion > 0)) {
        return plan_version_fases($pdo, $idEspecialidad, $idPlanVersion);
    }

    return fase_listar_directo($pdo, $idEspecialidad, $idPlanVersion);
}

/** @return array<int, array<string, mixed>> */
function fase_temario_semanas(PDO $pdo, int $idFase): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM fase_temario_semana WHERE id_fase = ? ORDER BY semana ASC'
        );
        $stmt->execute([$idFase]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/** @return array<int, list<array<string, mixed>>> id_fase => semanas */
function fase_temario_semanas_por_especialidad(PDO $pdo, int $idEspecialidad): array
{
    $out = [];
    try {
        $stmt = $pdo->prepare(
            'SELECT s.* FROM fase_temario_semana s
             INNER JOIN especialidad_fases f ON f.id_fase = s.id_fase
             WHERE f.id_especialidad = ? AND f.activo = 1
             ORDER BY f.orden ASC, s.semana ASC'
        );
        $stmt->execute([$idEspecialidad]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['id_fase'];
            $out[$id][] = $row;
        }
    } catch (PDOException $e) {
        // tabla aún no creada
    }
    return $out;
}

/** @return array<string, mixed>|null */
function fase_obtener(PDO $pdo, int $idFase): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM especialidad_fases WHERE id_fase = ? LIMIT 1');
    $stmt->execute([$idFase]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['semanas'] = fase_temario_semanas($pdo, $idFase);
    return $row;
}

function fase_guardar(PDO $pdo, array $d): array
{
    $idEsp = (int) ($d['id_especialidad'] ?? 0);
    $nombre = trim($d['nombre_fase'] ?? '');
    if ($idEsp <= 0 || $nombre === '') {
        return ['ok' => false, 'message' => 'Especialidad y nombre de fase son obligatorios'];
    }

    $idFase = (int) ($d['id_fase'] ?? 0);
    $params = [
        $idEsp,
        $nombre,
        trim($d['clave_fase'] ?? '') ?: null,
        (int) ($d['orden'] ?? 0),
        (int) ($d['duracion_semanas'] ?? 0) ?: null,
        trim($d['descripcion'] ?? '') ?: null,
        trim($d['temas'] ?? '') ?: null,
        trim($d['practicas_sugeridas'] ?? '') ?: null,
        trim($d['asesoria'] ?? '') ?: null,
        (int) ($d['activo'] ?? 1),
    ];

    if ($idFase > 0) {
        $pdo->prepare(
            'UPDATE especialidad_fases SET id_especialidad=?, nombre_fase=?, clave_fase=?, orden=?,
             duracion_semanas=?, descripcion=?, temas=?, practicas_sugeridas=?, asesoria=?, activo=?
             WHERE id_fase=?'
        )->execute(array_merge($params, [$idFase]));
    } else {
        $pdo->prepare(
            'INSERT INTO especialidad_fases (
                id_especialidad, nombre_fase, clave_fase, orden, duracion_semanas,
                descripcion, temas, practicas_sugeridas, asesoria, activo
            ) VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute($params);
        $idFase = (int) $pdo->lastInsertId();
    }

    fase_guardar_temario_fase($pdo, $idFase, $d);

    if (function_exists('fase_ensure_moodle_columns')) {
        fase_ensure_moodle_columns($pdo);
        $courseId = (int) ($d['moodle_course_id'] ?? 0) ?: null;
        $shortname = trim((string) ($d['moodle_shortname'] ?? '')) ?: null;
        if ($courseId === null && $shortname !== null && function_exists('moodle_course_find_by_shortname')) {
            $found = moodle_course_find_by_shortname($shortname);
            if (!empty($found['ok']) && !empty($found['id'])) {
                $courseId = (int) $found['id'];
            }
        }
        try {
            $pdo->prepare(
                'UPDATE especialidad_fases SET moodle_course_id = ?, moodle_shortname = ? WHERE id_fase = ?'
            )->execute([$courseId, $shortname, $idFase]);
        } catch (PDOException $e) {
            // columnas moodle aún no migradas
        }
    }

    return ['ok' => true, 'message' => 'Fase guardada', 'id_fase' => $idFase];
}

function fase_guardar_temario_fase(PDO $pdo, int $idFase, array $d): void
{
    if ($idFase <= 0) {
        return;
    }

    $esIngles = false;
    try {
        $stEsp = $pdo->prepare(
            'SELECT e.clave FROM especialidad_fases f
             INNER JOIN especialidades e ON e.id_especialidad = f.id_especialidad
             WHERE f.id_fase = ? LIMIT 1'
        );
        $stEsp->execute([$idFase]);
        $espRow = $stEsp->fetch(PDO::FETCH_ASSOC);
        $esIngles = fase_es_especialidad_ingles($espRow ?: null);
    } catch (PDOException $e) {
        $esIngles = false;
    }

    try {
        $evalJson = null;
        if (!$esIngles && array_key_exists('eval_criterios_json', $d)) {
            $items = $d['eval_criterios_json'];
            if (is_string($items)) {
                $items = json_decode($items, true) ?: [];
            }
            if (!is_array($items)) {
                $items = [];
            }
            $norm = [];
            foreach ($items as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $nombre = trim((string) ($it['nombre'] ?? ''));
                $desc = trim((string) ($it['descripcion'] ?? ''));
                if ($nombre === '' && $desc === '') {
                    continue;
                }
                $norm[] = ['nombre' => $nombre, 'descripcion' => $desc];
            }
            $evalJson = $norm === [] ? null : json_encode($norm, JSON_UNESCAPED_UNICODE);
        }

        $evalListening = $esIngles ? (trim($d['eval_listening'] ?? '') ?: null) : null;
        $evalReading = $esIngles ? (trim($d['eval_reading'] ?? '') ?: null) : null;
        $evalWriting = $esIngles ? (trim($d['eval_writing'] ?? '') ?: null) : null;
        $evalSpeaking = $esIngles ? (trim($d['eval_speaking'] ?? '') ?: null) : null;
        $evalGrammar = $esIngles ? (trim($d['eval_grammar'] ?? '') ?: null) : null;
        $evalVocabulary = $esIngles ? (trim($d['eval_vocabulary'] ?? '') ?: null) : null;
        $vocabResumen = $esIngles ? (trim($d['vocabulario_resumen'] ?? '') ?: null) : null;
        $gramResumen = $esIngles ? (trim($d['gramatica_resumen'] ?? '') ?: null) : null;

        $pdo->prepare(
            'UPDATE especialidad_fases SET
                objetivo_parcial = ?,
                eval_listening = ?, eval_reading = ?, eval_writing = ?,
                eval_speaking = ?, eval_grammar = ?, eval_vocabulary = ?,
                vocabulario_resumen = ?, gramatica_resumen = ?,
                eval_criterios_json = ?
             WHERE id_fase = ?'
        )->execute([
            trim($d['objetivo_parcial'] ?? '') ?: null,
            $evalListening,
            $evalReading,
            $evalWriting,
            $evalSpeaking,
            $evalGrammar,
            $evalVocabulary,
            $vocabResumen,
            $gramResumen,
            $evalJson,
            $idFase,
        ]);
    } catch (PDOException $e) {
        // columnas temario no migradas aún
    }

    $semanasJson = $d['semanas_json'] ?? '';
    if ($semanasJson === '') {
        return;
    }
    $semanas = json_decode($semanasJson, true);
    if (!is_array($semanas)) {
        return;
    }

    try {
        $upsert = $pdo->prepare(
            'INSERT INTO fase_temario_semana (
                id_fase, semana, titulo_leccion, objetivo, vocabulario, gramatica,
                listening, reading, writing, speaking, notas, es_examen, proyecto_tipo
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                titulo_leccion=VALUES(titulo_leccion), objetivo=VALUES(objetivo),
                vocabulario=VALUES(vocabulario), gramatica=VALUES(gramatica),
                listening=VALUES(listening), reading=VALUES(reading),
                writing=VALUES(writing), speaking=VALUES(speaking),
                notas=VALUES(notas), es_examen=VALUES(es_examen),
                proyecto_tipo=VALUES(proyecto_tipo)'
        );
        foreach ($semanas as $s) {
            $sem = (int) ($s['semana'] ?? 0);
            if ($sem < 1 || $sem > 4) {
                continue;
            }
            $upsert->execute([
                $idFase,
                $sem,
                trim($s['titulo_leccion'] ?? '') ?: null,
                trim($s['objetivo'] ?? '') ?: null,
                trim($s['vocabulario'] ?? '') ?: null,
                $esIngles ? (trim($s['gramatica'] ?? '') ?: null) : null,
                trim($s['listening'] ?? '') ?: null,
                trim($s['reading'] ?? '') ?: null,
                trim($s['writing'] ?? '') ?: null,
                trim($s['speaking'] ?? '') ?: null,
                trim($s['notas'] ?? '') ?: null,
                !empty($s['es_examen']) ? 1 : 0,
                trim($s['proyecto_tipo'] ?? '') ?: null,
            ]);
        }
    } catch (PDOException $e) {
        error_log('fase_guardar_temario_semana: ' . $e->getMessage());
    }
}

function fase_resumen_temario_fila(array $f, array $semanas): string
{
    if (!empty($f['objetivo_parcial'])) {
        return mb_strimwidth(strip_tags($f['objetivo_parcial']), 0, 140, '…');
    }
    if ($semanas !== []) {
        $n = count($semanas);
        $g = $semanas[0]['objetivo'] ?? $semanas[0]['titulo_leccion'] ?? '';
        if ($g) {
            return mb_strimwidth($g, 0, 120, '…') . " (+{$n} sem.)";
        }
        return "{$n} semana(s) cargadas";
    }
    if (!empty($f['temas'])) {
        return mb_strimwidth($f['temas'], 0, 120, '…');
    }
    return '— Sin temario';
}

function fase_eliminar(PDO $pdo, int $idFase): array
{
    $pdo->prepare('UPDATE especialidad_fases SET activo = 0 WHERE id_fase = ?')->execute([$idFase]);
    return ['ok' => true, 'message' => 'Fase desactivada'];
}

function fase_duracion_default_especialidad(array $esp): int
{
    $mod = $esp['modalidad'] ?? 'regular';
    if ($mod === 'prep_abierta') {
        return 2;
    }
    return (int) ($esp['duracion_fase_semanas'] ?? FASE_DURACION_DEFAULT) ?: FASE_DURACION_DEFAULT;
}

function fase_seed_especialidades_catalogo(PDO $pdo): void
{
    if (function_exists('hay_meta_get') && hay_meta_get($pdo, 'fase_catalogo_seeded') === '1') {
        return;
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM especialidades')->fetchColumn();
    if ($count > 0) {
        if (function_exists('hay_meta_set')) {
            hay_meta_set($pdo, 'fase_catalogo_seeded', '1');
        }
        return;
    }

    $defs = [
        ['ING', 'Inglés', 'regular', 4, 0, 0, 500, 1200, 1100, 350, 12, 48],
        ['ING-EXT', 'Extensivo Inglés', 'extensivo', 4, 0, 0, 400, 1000, 950, 300, 12, 48],
        ['COMP24', 'Informática 2024', 'regular', 4, 0, 0, 500, 1300, 1200, 380, 12, 48],
        ['COMP25', 'Informática 2025', 'regular', 4, 0, 0, 500, 1300, 1200, 380, 12, 48],
        ['COMP-K', 'Computación Infantil', 'kids', 4, 0, 0, 450, 1100, 1000, 320, 12, 48],
        ['ING-K', 'Inglés Infantil', 'kids', 4, 0, 0, 450, 1100, 1000, 320, 12, 48],
        ['COMP-EXT', 'Extensivo Informática', 'extensivo', 4, 0, 0, 400, 1000, 950, 300, 12, 48],
        ['ING-K-EXT', 'Extensivo Inglés Kids', 'extensivo', 4, 0, 0, 400, 1000, 950, 300, 12, 48],
        ['PREP-AB', 'Preparatoria Abierta', 'prep_abierta', 2, 0, 0, 600, 1400, 1300, 0, 24, null],
        ['PREP-ESC', 'Preparatoria Escolarizada', 'prep_escolarizada', 0, 1, 3, 700, 1500, 1400, 0, 24, null],
        ['COMP', 'Informática', 'regular', 4, 0, 0, 500, 1300, 1200, 380, 12, 48],
    ];

    $ins = $pdo->prepare(
        'INSERT IGNORE INTO especialidades (
            clave, nombre, descripcion, modalidad, duracion_fase_semanas,
            inscripcion_por_cuatrimestre, parciales_por_cuatrimestre,
            costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal,
            duracion_meses, duracion_semanas, es_fija, visible, orden
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,1,?)'
    );

    $orden = 1;
    foreach ($defs as $r) {
        [$clave, $nombre, $mod, $durFase, $inscCuat, $parciales, $ci, $cm, $cpp, $cs, $dm, $ds] = $r;
        $ins->execute([
            $clave, $nombre, 'Colegiatura congelada al inscribirse.', $mod, $durFase,
            $inscCuat, $parciales, $ci, $cm, $cpp, $cs, $dm, $ds, $orden++,
        ]);
    }

    if (function_exists('hay_meta_set')) {
        hay_meta_set($pdo, 'fase_catalogo_seeded', '1');
    }
}

function fase_seed_fases_desde_catalogo(PDO $pdo): void
{
    $map = fase_catalogo_por_clave();
    foreach ($map as $clave => $fases) {
        $stmt = $pdo->prepare('SELECT id_especialidad, duracion_fase_semanas, modalidad FROM especialidades WHERE clave = ? LIMIT 1');
        $stmt->execute([$clave]);
        $esp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$esp) {
            continue;
        }
        $idEsp = (int) $esp['id_especialidad'];
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM especialidad_fases WHERE id_especialidad = ?');
        $cnt->execute([$idEsp]);
        if ((int) $cnt->fetchColumn() > 0) {
            continue;
        }
        fase_insertar_catalogo($pdo, $idEsp, $fases, fase_duracion_default_especialidad($esp));
    }
}

/**
 * Catálogo inglés adulto: nivel + número de parcial (4 semanas c/u).
 *
 * @return list<array{nombre: string, clave: string}>
 */
function fase_catalogo_ingles_estructurado(string $claveEspecialidad): array
{
    if ($claveEspecialidad === 'ING') {
        $items = fase_generar_parciales_por_niveles(
            ['A1', 'A1+', 'A2', 'A2+', 'B1', 'B1+']
        );
        $items = array_merge(
            $items,
            fase_generar_parciales_por_niveles(['Proyecto final'], 1, 2)
        );
        return $items;
    }
    if ($claveEspecialidad === 'ING-EXT') {
        return fase_generar_parciales_por_niveles(['B2', 'B2+', 'C1', 'C1+']);
    }
    return [];
}

/**
 * Nombre visible para el alumno (reportes, portal).
 */
function fase_nombre_visible_alumno(string $nivel, int $parcial): string
{
    return $nivel . ' - Parcial ' . $parcial;
}

/**
 * Código administrativo compacto: A1-1, A1-2, A1+3, B2+4, PF-1…
 */
function fase_codigo_admin(string $nivel, int $parcial): string
{
    if ($nivel === 'Proyecto final') {
        return 'PF-' . $parcial;
    }
    if (str_ends_with($nivel, '+')) {
        return $nivel . $parcial;
    }

    return $nivel . '-' . $parcial;
}

/**
 * @param list<string> $niveles
 * @return list<array{nombre: string, clave: string, nivel: string, parcial: int}>
 */
function fase_generar_parciales_por_niveles(array $niveles, int $parcialInicio = 1, int $parcialFin = 4): array
{
    $items = [];
    foreach ($niveles as $nivel) {
        for ($p = $parcialInicio; $p <= $parcialFin; $p++) {
            $items[] = [
                'nivel' => $nivel,
                'parcial' => $p,
                'nombre' => fase_nombre_visible_alumno($nivel, $p),
                'clave' => fase_codigo_admin($nivel, $p),
            ];
        }
    }
    return $items;
}

/**
 * Interpreta encabezado del temario Excel: "Level A1 1-4" → nivel A1, parcial 1.
 *
 * @return array{nivel: string, parcial: int}|null
 */
function fase_parsear_encabezado_temario(string $titulo): ?array
{
    $titulo = trim($titulo);
    if (!preg_match('/^Level\s+(.+?)\s+(\d+)\s*-\s*(\d+)\s*$/iu', $titulo, $m)) {
        return null;
    }
    $nivel = trim($m[1]);
    if (preg_match('/^A12\+?$/i', $nivel)) {
        $nivel = str_ireplace('A12', 'A2', $nivel);
    }
    $semIni = (int) $m[2];
    $parcial = (int) floor(($semIni - 1) / 4) + 1;
    if ($parcial < 1 || $parcial > 4) {
        return null;
    }

    return ['nivel' => $nivel, 'parcial' => $parcial];
}

/**
 * @param list<string>|list<array{nombre: string, clave: string}> $catalogo
 */
function fase_insertar_catalogo(PDO $pdo, int $idEsp, array $catalogo, int $durSemanas): void
{
    $ins = $pdo->prepare(
        'INSERT INTO especialidad_fases (
            id_especialidad, nombre_fase, clave_fase, nivel_cefr, num_parcial, orden, duracion_semanas, activo
        ) VALUES (?,?,?,?,?,?,?,1)'
    );
    foreach ($catalogo as $i => $item) {
        if (is_array($item)) {
            $nombre = $item['nombre'];
            $clave = $item['clave'];
            $nivel = $item['nivel'] ?? null;
            $parcial = $item['parcial'] ?? null;
        } else {
            $nombre = (string) $item;
            $clave = $nombre;
            $nivel = null;
            $parcial = null;
        }
        $ins->execute([$idEsp, $nombre, $clave, $nivel, $parcial, $i + 1, $durSemanas]);
    }
}

/**
 * Importa temas desde Excel (.xlsx) al plan de fases de inglés.
 *
 * @return array{ok: bool, message: string, actualizadas?: int}
 */
function fase_importar_temario_xlsx(PDO $pdo, string $rutaXlsx, string $claveEsp = 'ING'): array
{
    if (!is_file($rutaXlsx) || !class_exists('ZipArchive')) {
        return ['ok' => false, 'message' => 'Archivo no encontrado o ZipArchive no disponible'];
    }

    $stmt = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE clave = ? LIMIT 1');
    $stmt->execute([$claveEsp]);
    $idEsp = (int) $stmt->fetchColumn();
    if ($idEsp <= 0) {
        return ['ok' => false, 'message' => 'Especialidad no encontrada: ' . $claveEsp];
    }

    $hojas = fase_xlsx_leer_hojas_temario($rutaXlsx);
    if ($hojas === []) {
        return ['ok' => false, 'message' => 'No se leyeron hojas del temario'];
    }

    $mapStmt = $pdo->prepare(
        'SELECT id_fase, clave_fase FROM especialidad_fases WHERE id_especialidad = ? AND activo = 1'
    );
    $mapStmt->execute([$idEsp]);
    $porCodigo = [];
    foreach ($mapStmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        if (!empty($f['clave_fase'])) {
            $porCodigo[$f['clave_fase']] = (int) $f['id_fase'];
        }
    }

    $extClaves = ['B2', 'B2+', 'C1', 'C1+'];
  $esExt = ($claveEsp === 'ING-EXT');

    $actualizadas = 0;
    $upd = $pdo->prepare(
        'UPDATE especialidad_fases SET descripcion = ?, temas = ? WHERE id_fase = ?'
    );

    foreach ($hojas as $hoja) {
        $parsed = fase_parsear_encabezado_temario($hoja['titulo']);
        if (!$parsed) {
            continue;
        }
        if ($esExt && !in_array($parsed['nivel'], $extClaves, true)) {
            continue;
        }
        if (!$esExt && in_array($parsed['nivel'], $extClaves, true)) {
            continue;
        }

        $codigo = fase_codigo_admin($parsed['nivel'], $parsed['parcial']);
        if (!isset($porCodigo[$codigo])) {
            continue;
        }

        $temas = trim($hoja['resumen'] ?? '');
        if ($temas === '') {
            continue;
        }

        $desc = 'Semanas ' . ($hoja['semana_ini'] ?? '') . '-' . ($hoja['semana_fin'] ?? '')
            . ' · ' . $parsed['nivel'] . ' parcial ' . $parsed['parcial'];
        $upd->execute([$desc, $temas, $porCodigo[$codigo]]);
        $actualizadas++;
    }

    return [
        'ok' => true,
        'message' => "Temario importado: $actualizadas fase(s) actualizadas en $claveEsp",
        'actualizadas' => $actualizadas,
    ];
}

/**
 * @return list<array{titulo: string, semana_ini: int, semana_fin: int, resumen: string}>
 */
function fase_xlsx_leer_hojas_temario(string $ruta): array
{
    $zip = new ZipArchive();
    if ($zip->open($ruta) !== true) {
        return [];
    }

    $ss = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $root = simplexml_load_string($ssXml);
        if ($root) {
            $root->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($root->xpath('//m:si') as $si) {
                $parts = [];
                foreach ($si->xpath('.//m:t') as $t) {
                    $parts[] = (string) $t;
                }
                $ss[] = implode('', $parts);
            }
        }
    }

    $out = [];
    for ($i = 1; $i <= 64; $i++) {
        $name = "xl/worksheets/sheet{$i}.xml";
        $xml = $zip->getFromName($name);
        if (!$xml) {
            continue;
        }
        $sheet = simplexml_load_string($xml);
        if (!$sheet) {
            continue;
        }
        $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = $sheet->xpath('//m:sheetData/m:row');
        if (!$rows) {
            continue;
        }

        $titulo = '';
        $goals = [];
        $semanaIni = 0;
        $semanaFin = 0;

        foreach ($rows as $row) {
            $cells = [];
            foreach ($row->xpath('m:c') as $c) {
                $ref = (string) $c['r'];
                preg_match('/^([A-Z]+)/', $ref, $cm);
                $col = $cm[1] ?? 'A';
                $t = (string) $c['t'];
                $v = (string) $c->v;
                if ($v === '') {
                    continue;
                }
                $val = ($t === 's' && isset($ss[(int) $v])) ? $ss[(int) $v] : $v;
                $cells[$col] = trim($val);
            }
            if ($cells === []) {
                continue;
            }
            $a = $cells['A'] ?? '';
            if ($titulo === '' && stripos($a, 'Level ') === 0) {
                $titulo = $a;
                if (preg_match('/(\d+)\s*-\s*(\d+)/', $a, $wm)) {
                    $semanaIni = (int) $wm[1];
                    $semanaFin = (int) $wm[2];
                }
                continue;
            }
            if (stripos($a, 'Week ') === 0 && isset($cells['C']) && stripos($cells['C'], 'Goal') !== false) {
                $goal = $cells['D'] ?? ($cells['C'] ?? '');
                if ($goal !== '' && stripos($goal, 'Goal') === false) {
                    $goals[] = preg_replace('/^Goal:\s*/i', '', $goal);
                }
            }
        }

        if ($titulo !== '') {
            $out[] = [
                'titulo' => $titulo,
                'semana_ini' => $semanaIni,
                'semana_fin' => $semanaFin,
                'resumen' => implode("\n", array_slice($goals, 0, 12)),
            ];
        }
    }

    $zip->close();
    return $out;
}

/** Renombra fases ING / ING-EXT al catálogo nivel + parcial (idempotente). */
function fase_sync_ingles_nomenclatura(PDO $pdo): void
{
    foreach (['ING', 'ING-EXT'] as $claveEsp) {
        $catalogo = fase_catalogo_ingles_estructurado($claveEsp);
        if ($catalogo === []) {
            continue;
        }

        $stmt = $pdo->prepare(
            'SELECT id_especialidad, duracion_fase_semanas FROM especialidades WHERE clave = ? LIMIT 1'
        );
        $stmt->execute([$claveEsp]);
        $esp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$esp) {
            continue;
        }

        $idEsp = (int) $esp['id_especialidad'];
        $dur = (int) ($esp['duracion_fase_semanas'] ?? FASE_DURACION_DEFAULT) ?: FASE_DURACION_DEFAULT;

        $existentes = $pdo->prepare(
            'SELECT id_fase, orden FROM especialidad_fases
             WHERE id_especialidad = ? AND activo = 1 ORDER BY orden ASC, id_fase ASC'
        );
        $existentes->execute([$idEsp]);
        $rows = $existentes->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            fase_insertar_catalogo($pdo, $idEsp, $catalogo, $dur);
            continue;
        }

        $upd = $pdo->prepare(
            'UPDATE especialidad_fases SET nombre_fase = ?, clave_fase = ?, nivel_cefr = ?, num_parcial = ?,
             orden = ?, duracion_semanas = ? WHERE id_fase = ?'
        );
        $n = min(count($rows), count($catalogo));
        for ($i = 0; $i < $n; $i++) {
            $upd->execute([
                $catalogo[$i]['nombre'],
                $catalogo[$i]['clave'],
                $catalogo[$i]['nivel'] ?? null,
                $catalogo[$i]['parcial'] ?? null,
                $i + 1,
                $dur,
                (int) $rows[$i]['id_fase'],
            ]);
        }

        if (count($catalogo) > count($rows)) {
            $ins = $pdo->prepare(
                'INSERT INTO especialidad_fases (
                    id_especialidad, nombre_fase, clave_fase, nivel_cefr, num_parcial, orden, duracion_semanas, activo
                ) VALUES (?,?,?,?,?,?,?,1)'
            );
            for ($i = count($rows); $i < count($catalogo); $i++) {
                $ins->execute([
                    $idEsp,
                    $catalogo[$i]['nombre'],
                    $catalogo[$i]['clave'],
                    $catalogo[$i]['nivel'] ?? null,
                    $catalogo[$i]['parcial'] ?? null,
                    $i + 1,
                    $dur,
                ]);
            }
        }

        if (count($rows) > count($catalogo)) {
            $des = $pdo->prepare('UPDATE especialidad_fases SET activo = 0 WHERE id_fase = ?');
            for ($i = count($catalogo); $i < count($rows); $i++) {
                $des->execute([(int) $rows[$i]['id_fase']]);
            }
        }
    }
}

/** @return array<string, list<string>> */
function fase_catalogo_por_clave(): array
{
    $ing = array_map(
        static fn (array $x) => $x['nombre'],
        fase_catalogo_ingles_estructurado('ING')
    );

    $extIng = array_map(
        static fn (array $x) => $x['nombre'],
        fase_catalogo_ingles_estructurado('ING-EXT')
    );

    $comp24 = [
        'WINDOWS', 'WORD', 'POWER POINT', 'EXCEL B.', 'EXCEL Av.', 'PROJECT',
        'CORELDRAW', 'PHOTOSHOP', 'ILLUSTRATOR', 'INDESIGN', 'AFTER EFFECTS', 'PREMIER',
        'MYSQL', 'HTML 5', 'JAVASCRIPT', 'PHP', 'LOGICA', 'JAVA',
        'ANDROID', 'ANDROID 2', 'MANTENIMIENTO', 'REDES', 'Proyecto 1', 'Proyecto 2',
    ];

    $comp25 = [
        'S.O. DESTREZA DIGITAL', 'INTERNET Y RS', 'WORKSPACE', 'SMART TECH', 'WORD', 'POWERPOINT',
        'EXCEL BCO', 'EXCEL AV', 'MAQUETACION Y PHP', 'HTML5 CSS', 'JAVASCRIPT', 'PLATAFORMAS WEB',
        'LOGICA', 'JAVA', 'ANDROID 1', 'ANDROID 2', 'REDES', 'ELECTRONICA', 'MNTTO', 'BASE DE DATOS',
        'PROYECTO FINAL',
    ];

    $compKids = [
        'WINDOWS', 'INTERNET', 'DESTREZA DIGITAL', 'WORD', 'EXCEL', 'POWERPOINT',
        'GOOGLE WORKSPACE', 'CANVA', 'VIRTUAL DJ', 'FILMORA', 'ANIMATE', 'AFTER EFFECTS',
        'PROYECTO FINAL',
    ];

    $ingKids = [];
    foreach (['F', 'J'] as $p) {
        for ($b = 1; $b <= 24; $b += 4) {
            $ingKids[] = $p . $b . '-' . ($b + 3);
        }
    }
    $ingKids[] = 'PROYECTO FINAL';

    $compExt = [
        'AUDACITY', 'CAMTASIA', 'COREL DRAW', 'PHOTOSHOP', 'MEDIBANG', 'LMMS',
        'MODELADO 3D', 'MODELADO DE CIRCUITO', 'LOGICA', 'BLOCKLY', 'SCRATCH',
        'INNOVACIÓN TECNOLÓGICA', 'PROYECTO FINAL 2',
    ];

    $ingKidsExt = [];
    foreach (['Y', 'T'] as $p) {
        for ($b = 1; $b <= 24; $b += 4) {
            $ingKidsExt[] = $p . $b . '-' . ($b + 3);
        }
    }
    $ingKidsExt[] = 'PROYECTO FINAL 2';

    $prepAb = [
        'Prope 1', 'Prope 2', 'Compresión de textos', 'lectura redacción', 'Redacción II',
        'temas sociales', 'Historia de Mexico I', 'Historia de Mexico II', 'Historia contemporánea',
        'Desarrollo humano', 'Filosofía y sociología', 'Salud publica',
        'Quimica 1', 'Quimica 2', 'Biología 1', 'Biología 2',
        'Matematicas 1', 'Matematicas 2', 'Matematicas 3', 'Matematicas 4',
        'Fisica 1', 'Fisica 2', 'Fisica 3', 'Fisica 4',
        'Computación 1', 'Computación 2', 'Computación 3', 'Computación 4',
    ];

    return [
        'ING' => $ing,
        'ING-EXT' => $extIng,
        'COMP24' => $comp24,
        'COMP25' => $comp25,
        'COMP-K' => $compKids,
        'ING-K' => $ingKids,
        'COMP-EXT' => $compExt,
        'ING-K-EXT' => $ingKidsExt,
        'PREP-AB' => $prepAb,
        'COMP' => array_slice($comp25, 0, 12),
    ];
}
