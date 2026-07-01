<?php
declare(strict_types=1);

/**
 * Libros institucionales versionados, indexación PDF, embeddings y acceso alumno.
 */

define('ACADEMICO_LIBRO_DIR', 'uploads/libros');

function academico_libro_ensure_schema(PDO $pdo): void
{
    if (function_exists('academico_material_ensure_schema')) {
        academico_material_ensure_schema($pdo);
    }
    fase_ensure_schema($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS academico_libro (
            id_libro INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_especialidad INT UNSIGNED NOT NULL,
            tipo ENUM(\'studentbook\',\'workbook\',\'libro_profesor\',\'guia_profesor\') NOT NULL,
            titulo VARCHAR(200) NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_libro),
            UNIQUE KEY uq_libro_esp_tipo (id_especialidad, tipo),
            KEY idx_libro_esp (id_especialidad, activo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS academico_libro_version (
            id_version INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_libro INT UNSIGNED NOT NULL,
            etiqueta VARCHAR(40) NOT NULL,
            ruta_pdf VARCHAR(500) NOT NULL,
            num_paginas SMALLINT UNSIGNED NULL,
            hash_sha256 CHAR(64) NULL,
            activo_alumno TINYINT(1) NOT NULL DEFAULT 0,
            activo_rag TINYINT(1) NOT NULL DEFAULT 0,
            estado_indexacion ENUM(\'pendiente\',\'procesando\',\'listo\',\'error\') NOT NULL DEFAULT \'pendiente\',
            error_indexacion TEXT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_version),
            KEY idx_ver_libro (id_libro),
            KEY idx_ver_rag (activo_rag, estado_indexacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS academico_material_embedding (
            id_embedding BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_material INT UNSIGNED NOT NULL,
            id_version INT UNSIGNED NOT NULL,
            modelo VARCHAR(80) NOT NULL,
            embedding_json JSON NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_embedding),
            UNIQUE KEY uq_mat_model (id_material, modelo),
            KEY idx_emb_version (id_version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    plantel_ensure_column($pdo, 'academico_material', 'id_libro', 'INT UNSIGNED NULL', 'activo');
    plantel_ensure_column($pdo, 'academico_material', 'id_version', 'INT UNSIGNED NULL', 'id_libro');
    plantel_ensure_column($pdo, 'academico_libro_version', 'pagina_inicio_workbook', 'SMALLINT UNSIGNED NULL', 'num_paginas');
}

function academico_libro_root_abs(): string
{
    $root = defined('HAY_ROOT') ? HAY_ROOT : dirname(__DIR__);

    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ACADEMICO_LIBRO_DIR);
}

function academico_libro_puede_gestionar(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if (function_exists('planeacion_puede_revisar') && planeacion_puede_revisar()) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_academico');
}

function academico_embedding_modelo(): string
{
    if (defined('OPENROUTER_EMBEDDING_MODEL') && OPENROUTER_EMBEDDING_MODEL !== '') {
        return (string) OPENROUTER_EMBEDDING_MODEL;
    }

    return 'openai/text-embedding-3-small';
}

/** @return list<array<string, mixed>> */
function academico_libro_listar(PDO $pdo, ?int $idEspecialidad = null): array
{
    academico_libro_ensure_schema($pdo);
    $sql = 'SELECT l.*, e.clave AS esp_clave, e.nombre AS esp_nombre,
            (SELECT COUNT(*) FROM academico_libro_version v WHERE v.id_libro = l.id_libro) AS num_versiones,
            (SELECT v.etiqueta FROM academico_libro_version v WHERE v.id_libro = l.id_libro AND v.activo_alumno = 1 LIMIT 1) AS version_alumno,
            (SELECT v.etiqueta FROM academico_libro_version v WHERE v.id_libro = l.id_libro AND v.activo_rag = 1 LIMIT 1) AS version_rag
            FROM academico_libro l
            INNER JOIN especialidades e ON e.id_especialidad = l.id_especialidad
            WHERE l.activo = 1';
    $params = [];
    if ($idEspecialidad !== null && $idEspecialidad > 0) {
        $sql .= ' AND l.id_especialidad = ?';
        $params[] = $idEspecialidad;
    }
    $sql .= ' ORDER BY e.orden ASC, l.tipo ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, mixed>|null */
function academico_libro_obtener(PDO $pdo, int $idLibro): ?array
{
    academico_libro_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT l.*, e.clave AS esp_clave, e.nombre AS esp_nombre
         FROM academico_libro l
         INNER JOIN especialidades e ON e.id_especialidad = l.id_especialidad
         WHERE l.id_libro = ? LIMIT 1'
    );
    $st->execute([$idLibro]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @return list<array<string, mixed>> */
function academico_libro_versiones(PDO $pdo, int $idLibro): array
{
    academico_libro_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT v.*,
            (SELECT COUNT(*) FROM academico_material m WHERE m.id_version = v.id_version) AS chunks,
            (SELECT COUNT(*) FROM academico_material_embedding em
             INNER JOIN academico_material m ON m.id_material = em.id_material
             WHERE m.id_version = v.id_version) AS embeddings
         FROM academico_libro_version v
         WHERE v.id_libro = ?
         ORDER BY v.creado_en DESC'
    );
    $st->execute([$idLibro]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function academico_libro_tipo_material(string $tipoLibro): string
{
    return match ($tipoLibro) {
        'studentbook' => 'studentbook',
        'workbook' => 'workbook',
        'libro_profesor' => 'libro_profesor',
        'guia_profesor' => 'guia_profesor',
        default => 'pdf_fragmento',
    };
}

/** Carpeta con pdftotext/pdfinfo (hosting compartido). Ver docs/POPPLER_HOSTING.md */
function academico_libro_poppler_bin_dir(): string
{
    if (defined('HAY_POPPLER_BIN_DIR') && HAY_POPPLER_BIN_DIR !== '') {
        return rtrim(str_replace('\\', '/', (string) HAY_POPPLER_BIN_DIR), '/');
    }
    $root = defined('HAY_ROOT') ? HAY_ROOT : dirname(__DIR__);
    $def = $root . '/bin/poppler';
    if (is_dir($def)) {
        return $def;
    }

    return '';
}

function academico_libro_poppler_lib_dir(): string
{
    if (defined('HAY_POPPLER_LIB_DIR') && HAY_POPPLER_LIB_DIR !== '') {
        return rtrim(str_replace('\\', '/', (string) HAY_POPPLER_LIB_DIR), '/');
    }
    $bin = academico_libro_poppler_bin_dir();
    if ($bin !== '') {
        foreach (['lib', '../lib', 'lib64'] as $sub) {
            $try = $bin . '/' . $sub;
            if (is_dir($try)) {
                return $try;
            }
        }
    }

    return '';
}

function academico_libro_bin_executable(string $name): string
{
    $dir = academico_libro_poppler_bin_dir();
    if ($dir !== '') {
        foreach ([$name, $name . '.exe'] as $file) {
            $path = $dir . '/' . $file;
            if (is_file($path) && (is_executable($path) || DIRECTORY_SEPARATOR === '\\')) {
                return $path;
            }
        }
        // Archivo presente pero sin bit ejecutable (común tras FTP)
        $plain = $dir . '/' . $name;
        if (is_file($plain)) {
            return $plain;
        }
    }

    return $name;
}

function academico_libro_shell_prefix(): string
{
    $lib = academico_libro_poppler_lib_dir();
    if ($lib === '' || DIRECTORY_SEPARATOR === '\\') {
        return '';
    }

    return 'LD_LIBRARY_PATH=' . escapeshellarg($lib) . ' ';
}

function academico_libro_shell_run(string $cmd): ?string
{
    if (!function_exists('shell_exec')) {
        return null;
    }
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    if (in_array('shell_exec', $disabled, true)) {
        return null;
    }

    return shell_exec(academico_libro_shell_prefix() . $cmd);
}

function academico_libro_pdftotext_disponible(): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $bin = academico_libro_bin_executable('pdftotext');
    $out = academico_libro_shell_run(escapeshellarg($bin) . ' -v 2>&1');

    return $cache = is_string($out) && stripos($out, 'pdftotext') !== false;
}

function academico_libro_pdf_num_paginas(string $pathAbs): ?int
{
    if (!is_readable($pathAbs)) {
        return null;
    }
    $bin = academico_libro_bin_executable('pdfinfo');
    $out = academico_libro_shell_run(escapeshellarg($bin) . ' ' . escapeshellarg($pathAbs) . ' 2>&1');
    if (is_string($out) && preg_match('/^Pages:\s+(\d+)/mi', $out, $m)) {
        return (int) $m[1];
    }

    return null;
}

function academico_libro_extraer_pagina(string $pathAbs, int $pagina): string
{
    if (!academico_libro_pdftotext_disponible() || !is_readable($pathAbs) || $pagina < 1) {
        return '';
    }
    $bin = academico_libro_bin_executable('pdftotext');
    $cmd = escapeshellarg($bin) . ' -f ' . (int) $pagina . ' -l ' . (int) $pagina . ' -layout '
        . escapeshellarg($pathAbs) . ' - 2>&1';
    $out = academico_libro_shell_run($cmd);

    return is_string($out) ? trim($out) : '';
}

/** @return array{ok:bool,message:string,id_version?:int} */
function academico_libro_version_subir(
    PDO $pdo,
    int $idLibro,
    string $etiqueta,
    array $file,
    bool $activoAlumno = false,
    bool $activoRag = true,
): array {
    academico_libro_ensure_schema($pdo);
    $libro = academico_libro_obtener($pdo, $idLibro);
    if (!$libro) {
        return ['ok' => false, 'message' => 'Libro no encontrado'];
    }
    $etiqueta = trim($etiqueta);
    if ($etiqueta === '') {
        return ['ok' => false, 'message' => 'Indique etiqueta de versión (ej. 2025.1)'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Error al subir el PDF'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    $mime = (string) ($file['type'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'message' => 'Archivo no válido'];
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext !== 'pdf' && !str_contains($mime, 'pdf')) {
        return ['ok' => false, 'message' => 'Solo se aceptan archivos PDF'];
    }

    $dir = academico_libro_root_abs() . DIRECTORY_SEPARATOR . $idLibro;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['ok' => false, 'message' => 'No se pudo crear carpeta de libros'];
    }

    $safeEtiqueta = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $etiqueta) ?: 'v';
    $rel = ACADEMICO_LIBRO_DIR . '/' . $idLibro . '/' . $safeEtiqueta . '.pdf';
    $abs = academico_libro_root_abs() . DIRECTORY_SEPARATOR . $idLibro . DIRECTORY_SEPARATOR . $safeEtiqueta . '.pdf';

    if (!move_uploaded_file($tmp, $abs)) {
        return ['ok' => false, 'message' => 'No se pudo guardar el PDF'];
    }

    $hash = hash_file('sha256', $abs) ?: null;
    $paginas = academico_libro_pdf_num_paginas($abs);

    $pdo->prepare(
        'INSERT INTO academico_libro_version (id_libro, etiqueta, ruta_pdf, num_paginas, hash_sha256, activo_alumno, activo_rag, estado_indexacion)
         VALUES (?, ?, ?, ?, ?, 0, 0, \'pendiente\')'
    )->execute([$idLibro, $etiqueta, $rel, $paginas, $hash]);
    $idVersion = (int) $pdo->lastInsertId();

    if ($activoAlumno) {
        academico_libro_version_activar($pdo, $idVersion, 'alumno');
    }
    if ($activoRag) {
        academico_libro_version_activar($pdo, $idVersion, 'rag');
    }

    return ['ok' => true, 'message' => 'Versión subida', 'id_version' => $idVersion];
}

/** @return array{ok:bool,message:string} */
function academico_libro_version_activar(PDO $pdo, int $idVersion, string $modo): array
{
    academico_libro_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT v.id_version, v.id_libro FROM academico_libro_version v WHERE v.id_version = ? LIMIT 1'
    );
    $st->execute([$idVersion]);
    $ver = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ver) {
        return ['ok' => false, 'message' => 'Versión no encontrada'];
    }
    $idLibro = (int) $ver['id_libro'];
    if ($modo === 'alumno') {
        $pdo->prepare('UPDATE academico_libro_version SET activo_alumno = 0 WHERE id_libro = ?')->execute([$idLibro]);
        $pdo->prepare('UPDATE academico_libro_version SET activo_alumno = 1 WHERE id_version = ?')->execute([$idVersion]);

        return ['ok' => true, 'message' => 'Versión activa para alumnos'];
    }
    if ($modo === 'rag') {
        $pdo->prepare('UPDATE academico_libro_version SET activo_rag = 0 WHERE id_libro = ?')->execute([$idLibro]);
        $pdo->prepare('UPDATE academico_libro_version SET activo_rag = 1 WHERE id_version = ?')->execute([$idVersion]);

        return ['ok' => true, 'message' => 'Versión activa para RAG'];
    }

    return ['ok' => false, 'message' => 'Modo no válido'];
}

function academico_libro_path_abs(string $rutaRel): string
{
    $root = defined('HAY_ROOT') ? HAY_ROOT : dirname(__DIR__);
    $rel = ltrim(str_replace(['\\', '..'], ['/', ''], $rutaRel), '/');

    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
}

/** Semana sugerida por página según temario de la especialidad. */
function academico_libro_semana_por_pagina(PDO $pdo, int $idEspecialidad, int $pagina, int $totalPaginas): ?int
{
    if ($totalPaginas <= 0) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT MAX(s.semana) FROM fase_temario_semana s
         INNER JOIN especialidad_fases f ON f.id_fase = s.id_fase AND f.activo = 1
         WHERE f.id_especialidad = ?'
    );
    $st->execute([$idEspecialidad]);
    $maxSem = (int) $st->fetchColumn();
    if ($maxSem <= 0) {
        return null;
    }
    $ratio = $pagina / $totalPaginas;

    return max(1, min($maxSem, (int) ceil($ratio * $maxSem)));
}

/** Detecta en qué página empieza la sección Workbook (mismo PDF físico). */
function academico_libro_detectar_pagina_workbook(string $pathAbs, int $paginas): ?int
{
    if (!academico_libro_pdftotext_disponible() || $paginas < 2) {
        return null;
    }
    for ($p = 2; $p <= $paginas; $p++) {
        $texto = mb_strtolower(academico_libro_extraer_pagina($pathAbs, $p));
        if ($texto === '') {
            continue;
        }
        $head = mb_substr($texto, 0, 500);
        if (preg_match('/\bwork\s*book\b/u', $head)
            || preg_match('/\bworkbook\b/u', $head)
            || preg_match('/\bcuaderno\s+de\s+(ejercicios|trabajo)\b/u', $head)) {
            return $p;
        }
    }

    return null;
}

function academico_libro_tipo_por_pagina(string $tipoLibro, int $pagina, ?int $paginaWorkbook): string
{
    if ($paginaWorkbook !== null && $paginaWorkbook > 0 && $pagina >= $paginaWorkbook) {
        return 'workbook';
    }

    return academico_libro_tipo_material($tipoLibro);
}

/**
 * Indexa PDF página por página y genera embeddings.
 * @return array{ok:bool,message:string,paginas?:int,chunks?:int,embeddings?:int}
 */
function academico_libro_version_indexar(PDO $pdo, int $idVersion, bool $regenerarEmbeddings = true): array
{
    academico_libro_ensure_schema($pdo);
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    if (!academico_libro_pdftotext_disponible()) {
        return ['ok' => false, 'message' => 'Instale poppler (pdftotext/pdfinfo) en el servidor para indexar PDFs'];
    }

    $st = $pdo->prepare(
        'SELECT v.*, l.id_especialidad, l.tipo, l.titulo AS libro_titulo
         FROM academico_libro_version v
         INNER JOIN academico_libro l ON l.id_libro = v.id_libro
         WHERE v.id_version = ? LIMIT 1'
    );
    $st->execute([$idVersion]);
    $ver = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ver) {
        return ['ok' => false, 'message' => 'Versión no encontrada'];
    }

    $path = academico_libro_path_abs((string) $ver['ruta_pdf']);
    if (!is_readable($path)) {
        return ['ok' => false, 'message' => 'PDF no encontrado en disco'];
    }

    $pdo->prepare('UPDATE academico_libro_version SET estado_indexacion = \'procesando\', error_indexacion = NULL WHERE id_version = ?')
        ->execute([$idVersion]);

    try {
        $paginas = (int) ($ver['num_paginas'] ?? 0);
        if ($paginas <= 0) {
            $paginas = academico_libro_pdf_num_paginas($path) ?? 0;
        }
        if ($paginas <= 0) {
            throw new RuntimeException('No se pudo determinar el número de páginas');
        }

        $pdo->prepare('UPDATE academico_libro_version SET num_paginas = ? WHERE id_version = ?')->execute([$paginas, $idVersion]);

        $paginaWorkbook = (int) ($ver['pagina_inicio_workbook'] ?? 0);
        if ($paginaWorkbook <= 0) {
            $paginaWorkbook = academico_libro_detectar_pagina_workbook($path, $paginas) ?? 0;
            if ($paginaWorkbook > 0) {
                $pdo->prepare('UPDATE academico_libro_version SET pagina_inicio_workbook = ? WHERE id_version = ?')
                    ->execute([$paginaWorkbook, $idVersion]);
            }
        }

        $pdo->prepare('DELETE FROM academico_material_embedding WHERE id_version = ?')->execute([$idVersion]);
        $pdo->prepare('DELETE FROM academico_material WHERE id_version = ?')->execute([$idVersion]);

        $tipoLibro = (string) $ver['tipo'];
        $idEsp = (int) $ver['id_especialidad'];
        $idLibro = (int) $ver['id_libro'];
        $chunks = 0;
        $embeddings = 0;
        $modelo = academico_embedding_modelo();
        $wbMsg = $paginaWorkbook > 0 ? " (Workbook desde p.{$paginaWorkbook})" : '';

        $ins = $pdo->prepare(
            'INSERT INTO academico_material
             (tipo, id_especialidad, semana, pagina_inicio, pagina_fin, titulo, descripcion, contenido_texto,
              ruta_archivo, id_libro, id_version, activo)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,1)'
        );

        for ($p = 1; $p <= $paginas; $p++) {
            $texto = academico_libro_extraer_pagina($path, $p);
            if ($texto === '') {
                continue;
            }
            $tipoMat = academico_libro_tipo_por_pagina($tipoLibro, $p, $paginaWorkbook > 0 ? $paginaWorkbook : null);
            $semana = academico_libro_semana_por_pagina($pdo, $idEsp, $p, $paginas);
            $sec = $tipoMat === 'workbook' ? 'Workbook' : 'Student Book';
            $titulo = ($ver['libro_titulo'] ?? 'Libro') . " — {$sec} p.{$p}";
            $ins->execute([
                $tipoMat,
                $idEsp,
                $semana,
                $p,
                $p,
                mb_substr($titulo, 0, 220),
                'Versión ' . ($ver['etiqueta'] ?? ''),
                mb_substr($texto, 0, 65000),
                (string) $ver['ruta_pdf'],
                $idLibro,
                $idVersion,
            ]);
            $idMat = (int) $pdo->lastInsertId();
            $chunks++;

            if ($regenerarEmbeddings && function_exists('hay_openrouter_embedding')) {
                $emb = hay_openrouter_embedding(mb_substr($texto, 0, 6000));
                if (!empty($emb['ok']) && !empty($emb['vector'])) {
                    $pdo->prepare(
                        'INSERT INTO academico_material_embedding (id_material, id_version, modelo, embedding_json)
                         VALUES (?,?,?,?)
                         ON DUPLICATE KEY UPDATE embedding_json = VALUES(embedding_json)'
                    )->execute([
                        $idMat,
                        $idVersion,
                        $modelo,
                        json_encode($emb['vector'], JSON_UNESCAPED_UNICODE),
                    ]);
                    $embeddings++;
                }
            }
        }

        $pdo->prepare('UPDATE academico_libro_version SET estado_indexacion = \'listo\', error_indexacion = NULL WHERE id_version = ?')
            ->execute([$idVersion]);

        return [
            'ok' => true,
            'message' => "Indexado: {$chunks} páginas, {$embeddings} embeddings{$wbMsg}",
            'paginas' => $paginas,
            'chunks' => $chunks,
            'embeddings' => $embeddings,
            'pagina_workbook' => $paginaWorkbook > 0 ? $paginaWorkbook : null,
        ];
    } catch (Throwable $e) {
        $pdo->prepare('UPDATE academico_libro_version SET estado_indexacion = \'error\', error_indexacion = ? WHERE id_version = ?')
            ->execute([mb_substr($e->getMessage(), 0, 2000), $idVersion]);

        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function hay_cosine_similarity(array $a, array $b): float
{
    $dot = 0.0;
    $na = 0.0;
    $nb = 0.0;
    $n = min(count($a), count($b));
    for ($i = 0; $i < $n; $i++) {
        $x = (float) $a[$i];
        $y = (float) $b[$i];
        $dot += $x * $y;
        $na += $x * $x;
        $nb += $y * $y;
    }
    if ($na <= 0.0 || $nb <= 0.0) {
        return 0.0;
    }

    return $dot / (sqrt($na) * sqrt($nb));
}

/**
 * Búsqueda semántica en embeddings activos.
 * @return list<array<string, mixed>>
 */
function academico_material_buscar_semantico(PDO $pdo, string $pregunta, ?int $idEspecialidad = null, int $limite = 8): array
{
    try {
        academico_libro_ensure_schema($pdo);
        if (!function_exists('hay_openrouter_embedding')) {
            return [];
        }
        $emb = hay_openrouter_embedding(mb_substr(trim($pregunta), 0, 6000));
        if (empty($emb['ok']) || empty($emb['vector'])) {
            return [];
        }
        $queryVec = $emb['vector'];
        $modelo = academico_embedding_modelo();

        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $st->execute(['academico_material_embedding']);
        if (!$st->fetchColumn()) {
            return [];
        }

        $sql = 'SELECT m.*, em.embedding_json,
            v.etiqueta AS version_etiqueta, l.titulo AS libro_titulo
            FROM academico_material_embedding em
            INNER JOIN academico_material m ON m.id_material = em.id_material AND m.activo = 1
            INNER JOIN academico_libro_version v ON v.id_version = m.id_version AND v.activo_rag = 1
            INNER JOIN academico_libro l ON l.id_libro = v.id_libro AND l.activo = 1
            WHERE em.modelo = ?';
        $params = [$modelo];
        if ($idEspecialidad !== null && $idEspecialidad > 0) {
            $sql .= ' AND m.id_especialidad = ?';
            $params[] = $idEspecialidad;
        }
        $sql .= ' LIMIT 500';

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $scored = [];
        foreach ($rows as $r) {
            $vec = json_decode((string) ($r['embedding_json'] ?? '[]'), true);
            if (!is_array($vec)) {
                continue;
            }
            $score = hay_cosine_similarity($queryVec, $vec);
            if ($score < 0.25) {
                continue;
            }
            $r['_score'] = $score;
            $scored[] = $r;
        }
        usort($scored, static fn ($x, $y) => ($y['_score'] <=> $x['_score']));

        return array_slice($scored, 0, $limite);
    } catch (Throwable $e) {
        error_log('academico_material_buscar_semantico: ' . $e->getMessage());

        return [];
    }
}

/** @return list<int> */
function academico_libro_especialidades_alumno(PDO $pdo, int $idAlumno): array
{
    $ids = [];
    $st = $pdo->prepare(
        'SELECT DISTINCT g.id_especialidad FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         WHERE ag.id_alumno = ? AND ag.activo = 1 AND a.estado = \'activo\'
           AND g.id_especialidad IS NOT NULL'
    );
    $st->execute([$idAlumno]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
        $ids[] = (int) $id;
    }
    $st2 = $pdo->prepare('SELECT id_especialidad FROM alumnos WHERE id_alumno = ? AND id_especialidad IS NOT NULL LIMIT 1');
    $st2->execute([$idAlumno]);
    $extra = (int) $st2->fetchColumn();
    if ($extra > 0 && !in_array($extra, $ids, true)) {
        $ids[] = $extra;
    }

    return $ids;
}

function academico_libro_alumno_puede_ver(PDO $pdo, int $idAlumno, int $idVersion): bool
{
    if ($idAlumno <= 0 || $idVersion <= 0) {
        return false;
    }
    academico_libro_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT l.id_especialidad FROM academico_libro_version v
         INNER JOIN academico_libro l ON l.id_libro = v.id_libro
         WHERE v.id_version = ? AND v.activo_alumno = 1 AND l.activo = 1 LIMIT 1'
    );
    $st->execute([$idVersion]);
    $idEsp = (int) $st->fetchColumn();
    if ($idEsp <= 0) {
        return false;
    }

    return in_array($idEsp, academico_libro_especialidades_alumno($pdo, $idAlumno), true);
}

/** @return list<array<string, mixed>> */
function academico_libro_listar_alumno(PDO $pdo, int $idAlumno): array
{
    $espIds = academico_libro_especialidades_alumno($pdo, $idAlumno);
    if ($espIds === []) {
        return [];
    }
    academico_libro_ensure_schema($pdo);
    $in = implode(',', array_fill(0, count($espIds), '?'));
    $st = $pdo->prepare(
        "SELECT l.id_libro, l.tipo, l.titulo, e.nombre AS esp_nombre, e.clave AS esp_clave,
                v.id_version, v.etiqueta, v.num_paginas, v.ruta_pdf, v.pagina_inicio_workbook
         FROM academico_libro l
         INNER JOIN especialidades e ON e.id_especialidad = l.id_especialidad
         INNER JOIN academico_libro_version v ON v.id_libro = l.id_libro AND v.activo_alumno = 1
         WHERE l.activo = 1 AND l.id_especialidad IN ($in)
           AND l.tipo IN ('studentbook','workbook')
         ORDER BY e.orden ASC, l.tipo ASC"
    );
    $st->execute($espIds);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function academico_libro_stream_token(int $idVersion, int $userId, int $ttlSec = 7200): string
{
    $secret = defined('HAY_SESSION_SECRET') ? HAY_SESSION_SECRET : 'hay-libro';
    $exp = time() + $ttlSec;
    $payload = $idVersion . '|' . $userId . '|' . $exp;
    $sig = hash_hmac('sha256', $payload, $secret);

    return base64_encode($payload . '|' . $sig);
}

/** @return array{ok:bool,message?:string,id_version?:int,user_id?:int} */
function academico_libro_stream_validar_token(string $token): array
{
    $raw = base64_decode($token, true);
    if ($raw === false || !str_contains($raw, '|')) {
        return ['ok' => false, 'message' => 'Token inválido'];
    }
    $parts = explode('|', $raw);
    if (count($parts) !== 4) {
        return ['ok' => false, 'message' => 'Token inválido'];
    }
    [$idVersion, $userId, $exp, $sig] = $parts;
    $secret = defined('HAY_SESSION_SECRET') ? HAY_SESSION_SECRET : 'hay-libro';
    $payload = $idVersion . '|' . $userId . '|' . $exp;
    if (!hash_equals(hash_hmac('sha256', $payload, $secret), $sig)) {
        return ['ok' => false, 'message' => 'Token inválido'];
    }
    if ((int) $exp < time()) {
        return ['ok' => false, 'message' => 'Token expirado'];
    }

    return ['ok' => true, 'id_version' => (int) $idVersion, 'user_id' => (int) $userId];
}

/** @return array{ok:bool,message:string,id_libro?:int} */
function academico_libro_crear(PDO $pdo, int $idEspecialidad, string $tipo, string $titulo): array
{
    academico_libro_ensure_schema($pdo);
    $titulo = trim($titulo);
    if ($idEspecialidad <= 0 || $titulo === '') {
        return ['ok' => false, 'message' => 'Especialidad y título requeridos'];
    }
    $tipos = ['studentbook', 'workbook', 'libro_profesor', 'guia_profesor'];
    if (!in_array($tipo, $tipos, true)) {
        return ['ok' => false, 'message' => 'Tipo de libro no válido'];
    }
    try {
        $pdo->prepare('INSERT INTO academico_libro (id_especialidad, tipo, titulo) VALUES (?,?,?)')
            ->execute([$idEspecialidad, $tipo, mb_substr($titulo, 0, 200)]);
    } catch (PDOException $e) {
        return ['ok' => false, 'message' => 'Ya existe un libro de ese tipo para la especialidad'];
    }

    return ['ok' => true, 'message' => 'Libro creado', 'id_libro' => (int) $pdo->lastInsertId()];
}
