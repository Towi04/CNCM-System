<?php

/**
 * Importación selectiva legado → HAY vía CSV (sin conexión a BD legado en el servidor).
 */

function migracion_csv_puede(): bool
{
    $rol = function_exists('rbac_rol_real') ? rbac_rol_real() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['supervisor', 'gerente', 'director', 'admin'], true);
}

function migracion_csv_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_migracion_csv_map (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entidad VARCHAR(40) NOT NULL,
            id_legacy VARCHAR(80) NOT NULL,
            id_hay INT UNSIGNED NOT NULL,
            detalle VARCHAR(255) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_migcsv_ent_leg (entidad, id_legacy),
            KEY idx_migcsv_hay (entidad, id_hay)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_migracion_csv_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tipo VARCHAR(40) NOT NULL,
            nivel VARCHAR(20) NOT NULL DEFAULT \'info\',
            mensaje TEXT NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_migcsv_log_tipo (tipo, creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function migracion_csv_log(PDO $pdo, string $tipo, string $nivel, string $mensaje): void
{
    migracion_csv_ensure_schema($pdo);
    $pdo->prepare(
        'INSERT INTO hay_migracion_csv_log (tipo, nivel, mensaje) VALUES (?,?,?)'
    )->execute([$tipo, $nivel, $mensaje]);
}

function migracion_csv_map_get(PDO $pdo, string $entidad, string $idLegacy): ?int
{
    $idLegacy = migracion_csv_norm_id_legado($idLegacy);
    if ($idLegacy === '') {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT id_hay FROM hay_migracion_csv_map WHERE entidad = ? AND id_legacy = ? LIMIT 1'
    );
    $st->execute([$entidad, $idLegacy]);
    $v = $st->fetchColumn();

    return $v === false ? null : (int) $v;
}

function migracion_csv_map_set(PDO $pdo, string $entidad, string $idLegacy, int $idHay, ?string $detalle = null): void
{
    $idLegacy = migracion_csv_norm_id_legado($idLegacy);
    if ($idLegacy === '') {
        return;
    }
    $pdo->prepare(
        'INSERT INTO hay_migracion_csv_map (entidad, id_legacy, id_hay, detalle)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE id_hay = VALUES(id_hay), detalle = VALUES(detalle)'
    )->execute([$entidad, $idLegacy, $idHay, $detalle]);
}

/**
 * Normaliza ids legado (trim, BOM, Excel 959.0 → 959).
 */
function migracion_csv_norm_id_legado(string $id): string
{
    $id = trim($id);
    $id = preg_replace('/^\xEF\xBB\xBF/', '', $id) ?? $id;
    $id = trim($id, " \t\n\r\0\x0B\"'");
    if ($id === '') {
        return '';
    }
    if (preg_match('/^(\d+)\.0+$/', $id, $m)) {
        return $m[1];
    }
    if (preg_match('/^\d+$/', $id)) {
        // Quitar ceros a la izquierda sin perder el id "0"
        return (string) ((int) $id);
    }

    return $id;
}

/** Longitud máxima de alumnos.telefono / telefono2 (VARCHAR(30)). */
function migracion_csv_telefono_max_len(): int
{
    return 30;
}

/**
 * Limpia y trunca teléfono para que quepa en VARCHAR(30).
 */
function migracion_csv_norm_telefono(string $tel, ?int $max = null): string
{
    $max = $max ?? migracion_csv_telefono_max_len();
    $tel = trim($tel);
    $tel = preg_replace('/\s+/u', ' ', $tel) ?? $tel;
    if ($tel === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($tel) > $max) {
            return mb_substr($tel, 0, $max);
        }

        return $tel;
    }
    if (strlen($tel) > $max) {
        return substr($tel, 0, $max);
    }

    return $tel;
}

function migracion_csv_telefono_len(string $tel): int
{
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($tel);
    }

    return strlen($tel);
}

/**
 * @return list<string>
 */
function migracion_csv_map_keys_muestra(PDO $pdo, string $entidad, int $limit = 25): array
{
    $limit = max(1, min(100, $limit));
    $st = $pdo->prepare(
        'SELECT id_legacy FROM hay_migracion_csv_map WHERE entidad = ? ORDER BY id_legacy ASC LIMIT ' . (int) $limit
    );
    $st->execute([$entidad]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $k) {
        $out[] = (string) $k;
    }

    return $out;
}

/**
 * Asegura alias id↔clave en el mapa de grupos (y kids CK si aplica).
 */
function migracion_csv_map_alias_grupo(
    PDO $pdo,
    string $idLeg,
    string $claveLegado,
    int $idHay,
    ?int $idHayComp = null,
    ?string $detalle = null
): void {
    $idLeg = migracion_csv_norm_id_legado($idLeg);
    $claveLegado = trim($claveLegado);
    if ($idLeg !== '') {
        migracion_csv_map_set($pdo, 'grupo', $idLeg, $idHay, $detalle);
        if ($idHayComp !== null && $idHayComp > 0) {
            migracion_csv_map_set($pdo, 'grupo_comp', $idLeg, $idHayComp, $detalle);
        }
    }
    if ($claveLegado !== '' && $claveLegado !== $idLeg) {
        migracion_csv_map_set($pdo, 'grupo', $claveLegado, $idHay, $detalle ?: ('alias_clave; id_legado=' . $idLeg));
        if ($idHayComp !== null && $idHayComp > 0) {
            migracion_csv_map_set($pdo, 'grupo_comp', $claveLegado, $idHayComp, $detalle ?: ('alias_clave; id_legado=' . $idLeg));
        }
    }
}

/**
 * @return array{headers:list<string>, rows:list<array<string, string>>, error?:string}
 */
function migracion_csv_leer_archivo(string $tmpPath): array
{
    if (!is_readable($tmpPath)) {
        return ['headers' => [], 'rows' => [], 'error' => 'No se pudo leer el archivo'];
    }
    $fh = fopen($tmpPath, 'r');
    if ($fh === false) {
        return ['headers' => [], 'rows' => [], 'error' => 'No se pudo abrir el CSV'];
    }
    $header = fgetcsv($fh);
    if ($header === false || $header === []) {
        fclose($fh);

        return ['headers' => [], 'rows' => [], 'error' => 'CSV vacío'];
    }
    // Excel a veces escribe BOM UTF-8 dos veces; quitar todos al inicio de cada encabezado.
    $headers = [];
    foreach ($header as $i => $h) {
        $h = (string) $h;
        while (str_starts_with($h, "\xEF\xBB\xBF")) {
            $h = substr($h, 3);
        }
        $h = preg_replace('/^\x{FEFF}+/u', '', $h) ?? $h;
        $headers[] = strtolower(trim($h));
    }
    $rows = [];
    while (($data = fgetcsv($fh)) !== false) {
        if ($data === [null] || $data === false) {
            continue;
        }
        $row = [];
        foreach ($headers as $i => $col) {
            $row[$col] = isset($data[$i]) ? trim((string) $data[$i]) : '';
        }
        // skip empty
        $allEmpty = true;
        foreach ($row as $v) {
            if ($v !== '') {
                $allEmpty = false;
                break;
            }
        }
        if ($allEmpty) {
            continue;
        }
        $rows[] = $row;
    }
    fclose($fh);

    return ['headers' => $headers, 'rows' => $rows];
}

/**
 * Resuelve plantel por slug/nombre del CSV.
 * El plantel de sesión ($forzarId) solo aplica si la fila no trae plantel_slug
 * (antes se forzaba siempre y todos caían en la sede activa).
 */
function migracion_csv_resolver_plantel(PDO $pdo, string $slugOrName, ?int $forzarId = null): ?int
{
    $q = trim($slugOrName);
    if ($q !== '') {
        $candidates = array_values(array_unique(array_filter([
            $q,
            str_replace([' ', '-'], '_', strtolower($q)),
            str_replace([' ', '_'], '-', strtolower($q)),
        ], static fn ($v) => $v !== '')));

        foreach ($candidates as $cand) {
            $st = $pdo->prepare(
                'SELECT id_plantel FROM planteles
                 WHERE LOWER(slug) = LOWER(?) OR LOWER(nombre) = LOWER(?)
                 ORDER BY id_plantel ASC LIMIT 1'
            );
            $st->execute([$cand, $cand]);
            $id = $st->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
        }

        // Coincidencia parcial por slug o nombre (ej. irapuato_guerrero ≈ plantel_irapuato_guerrero)
        foreach ($candidates as $cand) {
            $st = $pdo->prepare(
                'SELECT id_plantel FROM planteles
                 WHERE LOWER(slug) LIKE LOWER(?) OR LOWER(nombre) LIKE LOWER(?)
                 ORDER BY id_plantel ASC LIMIT 1'
            );
            $like = '%' . $cand . '%';
            $st->execute([$like, $like]);
            $id = $st->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
        }

        // Slug del CSV no existe: no silenciar con la sede de sesión
        return null;
    }

    if ($forzarId !== null && $forzarId > 0) {
        return $forzarId;
    }

    return function_exists('plantel_id_activo') ? plantel_id_activo() : null;
}

/**
 * Atajos de área (I/C/IK/CK…) → clave de catálogo de especialidades (ING/COMP/ING-K…).
 * Las claves de grupo (IS1, CK3…) las genera HAY; no van en clave_especialidad.
 */
function migracion_csv_normalizar_clave_especialidad(string $raw): string
{
    $c = strtoupper(trim($raw));
    $c = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $c);
    if ($c === '') {
        return '';
    }
    static $map = [
        'I' => 'ING',
        'INGLES' => 'ING',
        'C' => 'COMP',
        'COMPU' => 'COMP',
        'COMPUTACION' => 'COMP',
        'INFORMATICA' => 'COMP',
        'IK' => 'ING-K',
        'CK' => 'COMP-K',
        'K' => 'ING-K',
        'INGLES-K' => 'ING-K',
        'INGLES_K' => 'ING-K',
        'COMPUTACION-K' => 'COMP-K',
        'COMPUTACION_K' => 'COMP-K',
        'PA' => 'PREP-AB',
        'PREPA' => 'PREP-AB',
        'PREPA-AB' => 'PREP-AB',
        'PREPA-ABIERTA' => 'PREP-AB',
        'PE' => 'PREP-ESC',
        'PREPA-ESC' => 'PREP-ESC',
        'EI' => 'ING-EXT',
        'EIS' => 'ING-EXT',
        'EC' => 'COMP-EXT',
    ];

    return $map[$c] ?? $c;
}

/**
 * Candidatos de clave de especialidad a buscar en catálogo (incluye atajos y años COMP24/25).
 *
 * @return list<string>
 */
function migracion_csv_candidatos_especialidad(string $clave): array
{
    $clave = migracion_csv_normalizar_clave_especialidad($clave);
    if ($clave === '') {
        return [];
    }
    $out = [$clave];
    $extra = match ($clave) {
        'ING' => ['I', 'INGLES'],
        'COMP' => ['C', 'COMP25', 'COMP24', 'COMPUTACION', 'INFORMATICA'],
        'ING-K' => ['IK', 'INGLES-K', 'INGLES_K'],
        'COMP-K' => ['CK', 'COMPUTACION-K'],
        'PREP-AB' => ['PA', 'PREPA-ABIERTA'],
        'PREP-ESC' => ['PE', 'PREPA-ESC'],
        'ING-EXT' => ['EI', 'EIS'],
        'COMP-EXT' => ['EC'],
        default => [],
    };
    foreach ($extra as $e) {
        if (!in_array($e, $out, true)) {
            $out[] = $e;
        }
    }

    return $out;
}

function migracion_csv_resolver_especialidad(PDO $pdo, string $clave): ?int
{
    $candidatos = migracion_csv_candidatos_especialidad($clave);
    if ($candidatos === []) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT id_especialidad FROM especialidades WHERE activo = 1 AND UPPER(clave) = ? LIMIT 1'
    );
    foreach ($candidatos as $c) {
        $st->execute([strtoupper($c)]);
        $id = $st->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
    }
    // Sin filtro activo (por si el catálogo no marca activo)
    $st2 = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE UPPER(clave) = ? LIMIT 1');
    foreach ($candidatos as $c) {
        $st2->execute([strtoupper($c)]);
        $id = $st2->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
    }

    return null;
}

/**
 * Fecha CSV → Y-m-d. Acepta ISO y formatos Excel MX (d/m/Y, d-m-Y).
 */
function migracion_csv_parse_fecha(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    // Excel serial (raro en CSV texto, pero por si acaso)
    if (preg_match('/^\d{4,5}(?:\.\d+)?$/', $raw)) {
        $n = (int) $raw;
        if ($n > 20000 && $n < 80000) {
            $ts = strtotime('1899-12-30 +' . $n . ' days');
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }
    }
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    // d/m/Y o d-m-Y (México)
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $raw, $m)) {
        $d = (int) $m[1];
        $mo = (int) $m[2];
        $y = (int) $m[3];
        if ($y < 100) {
            $y += $y >= 70 ? 1900 : 2000;
        }
        // Si el primer número > 12, seguro es día; si ambos <= 12, preferir d/m/Y (MX)
        if ($d > 31 || $mo > 12) {
            // posible m/d/Y mal leído: intercambiar si ayuda
            if ($mo <= 31 && $d <= 12) {
                [$d, $mo] = [$mo, $d];
            }
        }
        if (checkdate($mo, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
    }
    $ts = strtotime($raw);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return $raw;
}

/**
 * Área / extensivo para grupo_clave_* a partir de clave_especialidad HAY.
 *
 * @return array{area:string, extensivo:bool, kids:bool}
 */
function migracion_csv_area_desde_clave_especialidad(string $claveEsp): array
{
    $claveEsp = migracion_csv_normalizar_clave_especialidad($claveEsp);
    $kids = in_array($claveEsp, ['ING-K', 'ING-K-EXT', 'COMP-K'], true)
        || str_ends_with($claveEsp, '-K')
        || $claveEsp === 'IK'
        || $claveEsp === 'CK';
    $area = 'I';
    if ($kids) {
        $area = 'K';
    } elseif (str_starts_with($claveEsp, 'COMP') || $claveEsp === 'C') {
        $area = 'C';
    } elseif (str_starts_with($claveEsp, 'PREP-AB') || $claveEsp === 'PA') {
        $area = 'PA';
    } elseif (str_starts_with($claveEsp, 'PREP') || $claveEsp === 'PE') {
        $area = 'PE';
    }
    $ext = str_contains($claveEsp, 'EXT');

    return ['area' => $area, 'extensivo' => $ext, 'kids' => $kids];
}

/**
 * Asegura especialidades infantiles mínimas si el catálogo no las tiene.
 */
function migracion_csv_asegurar_especialidades_kids(PDO $pdo): void
{
    if (function_exists('catalog_ensure_schema')) {
        catalog_ensure_schema($pdo);
    }
    $defs = [
        ['ING-K', 'Inglés Infantil', 'kids'],
        ['COMP-K', 'Computación Infantil', 'kids'],
    ];
    foreach ($defs as [$clave, $nombre, $mod]) {
        if (migracion_csv_resolver_especialidad($pdo, $clave) !== null) {
            continue;
        }
        try {
            $pdo->prepare(
                'INSERT IGNORE INTO especialidades (
                    clave, nombre, descripcion, modalidad, duracion_fase_semanas,
                    inscripcion_por_cuatrimestre, parciales_por_cuatrimestre,
                    costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal,
                    duracion_meses, duracion_semanas, es_fija, visible, orden
                 ) VALUES (?,?,?,?,4,0,0,450,1100,1000,320,12,48,1,1,5)'
            )->execute([
                $clave,
                $nombre,
                'Creada automáticamente por Migración CSV (faltaba en el catálogo).',
                $mod,
            ]);
        } catch (Throwable $e) {
            // esquema puede variar; el import fallará con mensaje claro si sigue faltando
        }
    }
}

/**
 * Catálogo activo para el selector de especialidad en preview de grupos.
 *
 * @return list<array{clave:string, nombre:string, id_especialidad:int}>
 */
function migracion_csv_especialidades_opciones(PDO $pdo): array
{
    if (function_exists('catalog_ensure_schema')) {
        catalog_ensure_schema($pdo);
    }
    try {
        $rows = $pdo->query(
            "SELECT id_especialidad, clave, nombre
             FROM especialidades
             WHERE activo = 1
             ORDER BY orden ASC, nombre ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $clave = strtoupper(trim((string) ($r['clave'] ?? '')));
        if ($clave === '') {
            continue;
        }
        $out[] = [
            'id_especialidad' => (int) $r['id_especialidad'],
            'clave' => $clave,
            'nombre' => trim((string) ($r['nombre'] ?? $clave)),
        ];
    }

    return $out;
}

/**
 * Vista previa paginada de grupos: marca especialidad faltante/desconocida y sugiere clave HAY.
 *
 * @return array{ok:bool, message?:string, headers?:list<string>, total_filas?:int, offset?:int, limit?:int, muestra?:list<array<string,mixed>>, errores?:list<string>, especialidades?:list<array{clave:string,nombre:string}>, profesores?:list<array{id_usuario:int,nombre:string,username:string,id_plantel:int}>}
 */
function migracion_csv_preview_grupos(
    PDO $pdo,
    string $tmpPath,
    ?int $forzarPlantel = null,
    int $offset = 0,
    int $limit = 50
): array {
    migracion_csv_ensure_schema($pdo);
    if (function_exists('grupo_clave_ensure_schema')) {
        grupo_clave_ensure_schema($pdo);
    }
    $parsed = migracion_csv_leer_archivo($tmpPath);
    if (!empty($parsed['error'])) {
        return ['ok' => false, 'message' => $parsed['error']];
    }
    $rows = $parsed['rows'];
    $total = count($rows);
    $offset = max(0, $offset);
    $limit = max(10, min(200, $limit));
    $slice = array_slice($rows, $offset, $limit);
    $especialidades = migracion_csv_especialidades_opciones($pdo);

    // Conteos de clave legado en TODO el CSV (para detectar pareja kids por filas repetidas)
    $conteoKeys = [];
    $ordenKeys = [];
    foreach ($rows as $idx => $rr) {
        $k = migracion_csv_grupo_row_key($rr, (int) $idx);
        if (!isset($conteoKeys[$k])) {
            $conteoKeys[$k] = 0;
            $ordenKeys[$k] = [];
        }
        $conteoKeys[$k]++;
        $ordenKeys[$k][] = $idx;
    }

    // Profesores por plantel (cache) + lista global para el desplegable
    /** @var array<int, list<array{id_usuario:int,nombre:string,username:string,id_plantel:int}>> $profCache */
    $profCache = [];
    $profesoresAll = [];
    $seenProf = [];
    $plantelesCsv = [];
    if ($forzarPlantel) {
        $plantelesCsv[$forzarPlantel] = true;
    }
    foreach ($rows as $rr) {
        $idp = migracion_csv_resolver_plantel(
            $pdo,
            (string) ($rr['plantel_slug'] ?? $rr['sucursal'] ?? ''),
            $forzarPlantel
        );
        if ($idp) {
            $plantelesCsv[$idp] = true;
        }
    }
    foreach (array_keys($plantelesCsv) as $idp) {
        $lista = migracion_csv_profesores_opciones($pdo, (int) $idp);
        $profCache[(int) $idp] = $lista;
        foreach ($lista as $p) {
            $pid = (int) $p['id_usuario'];
            if (!isset($seenProf[$pid])) {
                $seenProf[$pid] = true;
                $profesoresAll[] = $p;
            }
        }
    }
    if ($profesoresAll === []) {
        $profesoresAll = migracion_csv_profesores_opciones($pdo, null);
    }
    usort($profesoresAll, static function ($a, $b) {
        return strcasecmp((string) $a['nombre'], (string) $b['nombre']);
    });

    $headers = [
        'clave_legado',
        'clave_especialidad',
        'esp_estado',
        'clave_nueva_sugerida',
        'plantel_slug',
        'infantil',
        'fecha_inicio',
        'dias_clase',
        'hora_inicio',
        'profesor_csv',
        'profesor_sugerido',
        'prof_estado',
        'aula_codigo',
        'id_grupo_legado',
        'accion',
    ];
    $errores = [];
    $muestra = [];
    $faltanEsp = 0;
    $faltanProf = 0;
    $faltanFecha = 0;

    foreach ($slice as $i => $r) {
        $line = $offset + $i + 2;
        $globalIdx = $offset + $i;
        $idLeg = trim((string) ($r['id_grupo_legado'] ?? ''));
        $claveLeg = trim((string) ($r['clave_legado'] ?? ''));
        $claveEspRaw = strtoupper(trim((string) ($r['clave_especialidad'] ?? '')));
        $claveEsp = migracion_csv_normalizar_clave_especialidad($claveEspRaw);
        $flagInfantil = migracion_csv_parse_flag01($r['infantil'] ?? null);
        $metaKids = $claveEsp !== '' ? migracion_csv_area_desde_clave_especialidad($claveEsp) : ['kids' => false];
        if ($claveEsp === '' && $flagInfantil === true) {
            $claveEsp = 'ING-K';
            $metaKids = migracion_csv_area_desde_clave_especialidad($claveEsp);
        }
        $rowKey = migracion_csv_grupo_row_key($r, $globalIdx);
        $nDup = (int) ($conteoKeys[$rowKey] ?? 1);
        $posDup = 1;
        if (isset($ordenKeys[$rowKey])) {
            $pos = array_search($globalIdx, $ordenKeys[$rowKey], true);
            $posDup = $pos === false ? 1 : ((int) $pos + 1);
        }
        // Pareja por clave repetida solo si no está marcado adulto (infantil=0)
        $esParejaCsv = $nDup >= 2 && $flagInfantil !== false && ($claveLeg !== '' || $idLeg !== '');
        if ($esParejaCsv && $flagInfantil === false) {
            $esParejaCsv = false;
        }
        if ($flagInfantil === false && empty($metaKids['kids'])) {
            $esKids = false;
            $esParejaCsv = false;
        } elseif ($flagInfantil === true || !empty($metaKids['kids'])) {
            $esKids = true;
        } else {
            $esKids = $esParejaCsv;
        }

        $ya = ($claveLeg !== '' && migracion_csv_map_get($pdo, 'grupo', $claveLeg) !== null)
            || ($idLeg !== '' && migracion_csv_map_get($pdo, 'grupo', $idLeg) !== null);
        $espEstado = 'ok';
        $accion = $ya ? 'omitir (ya mapeado)' : 'crear';
        if (!$ya && $esParejaCsv && $esKids) {
            $accion = $posDup === 1
                ? 'kids inglés (pareja CSV 1/' . $nDup . ')'
                : 'kids cómputo (pareja CSV ' . $posDup . '/' . $nDup . ')';
        } elseif (!$ya && $esKids) {
            $accion = 'crear pareja kids (2h+2h)';
        }
        $claveNueva = '';
        $idPlantel = migracion_csv_resolver_plantel(
            $pdo,
            (string) ($r['plantel_slug'] ?? $r['sucursal'] ?? ''),
            $forzarPlantel
        );

        $profCsv = trim((string) ($r['profesor_sugerido'] ?? ''));
        $profUser = trim((string) ($r['profesor_username'] ?? ''));
        if ($profCsv === '' && $profUser !== '') {
            $profCsv = $profUser;
        }
        $candidatos = $idPlantel && isset($profCache[$idPlantel])
            ? $profCache[$idPlantel]
            : $profesoresAll;
        $matchProf = migracion_csv_mejor_profesor($profUser, $profCsv, $candidatos);
        $profEstado = (string) $matchProf['estado'];
        $profesorId = $matchProf['id_usuario'];
        if ($profEstado === 'sin_match' || $profEstado === 'vacio') {
            if (!$ya && ($profCsv !== '' || $profUser !== '')) {
                $faltanProf++;
            }
        }

        // En pareja CSV la especialidad puede ir vacía (se asume ING-K / COMP-K)
        if ($claveEsp === '' && !$esParejaCsv && !$esKids) {
            $espEstado = 'falta';
            $faltanEsp++;
            if (!$ya) {
                $accion = 'definir especialidad';
            }
        } elseif ($claveEsp !== '' && migracion_csv_resolver_especialidad($pdo, $claveEsp) === null && !$esParejaCsv) {
            $espEstado = 'desconocida';
            $faltanEsp++;
            if (!$ya) {
                $accion = 'corregir especialidad';
            }
            $errores[] = "Línea {$line}: especialidad {$claveEsp} no existe en HAY";
        }

        $fechaRawPrev = trim((string) ($r['fecha_inicio_estimada'] ?? ''));
        if ($fechaRawPrev === '') {
            $fechaRawPrev = trim((string) ($r['fecha_inicio_legado'] ?? ''));
        }
        $fechaPrev = migracion_csv_parse_fecha($fechaRawPrev);
        if ($fechaPrev === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPrev)) {
            $fechaPrev = '';
            if (!$ya) {
                $faltanFecha++;
                if ($accion === 'crear' || str_starts_with($accion, 'kids') || str_starts_with($accion, 'crear pareja')) {
                    $accion = 'corregir fecha inicio';
                }
                $errores[] = "Línea {$line}: fecha de inicio inválida o vacía"
                    . ($fechaRawPrev !== '' ? " («{$fechaRawPrev}»)" : '');
            }
        }

        if ($idPlantel && !$ya && ($espEstado === 'ok' || $esKids) && $fechaPrev !== '') {
            try {
                $dias = migracion_csv_parse_dias((string) ($r['dias_clase'] ?? ''));
                $horaIni = trim((string) ($r['hora_inicio'] ?? '08:00')) ?: '08:00';
                $codH = migracion_csv_infer_codigo_horario($dias !== [] ? $dias : [6], $horaIni);
                if ($esKids) {
                    if (function_exists('grupo_infantil_vista_previa')) {
                        $prev = grupo_infantil_vista_previa($pdo, $idPlantel);
                        $claveNueva = (string) ($prev['clave'] ?? ((string) ($prev['clave_ingles'] ?? '') . ' + ' . (string) ($prev['clave_computacion'] ?? '')));
                    } else {
                        $claveNueva = '(pareja kids)';
                    }
                } elseif ($claveEsp !== '' && function_exists('grupo_clave_vista_previa')) {
                    $meta = migracion_csv_area_desde_clave_especialidad($claveEsp);
                    $prev = grupo_clave_vista_previa(
                        $pdo,
                        $idPlantel,
                        $meta['area'],
                        $codH,
                        $meta['extensivo'],
                        false,
                        ''
                    );
                    $claveNueva = (string) ($prev['clave'] ?? '');
                }
            } catch (Throwable $e) {
                $claveNueva = '(se genera al importar)';
            }
        }

        $muestra[] = [
            'id_grupo_legado' => $idLeg,
            'clave_legado' => $claveLeg,
            'clave_especialidad' => $claveEsp,
            'esp_estado' => $espEstado,
            'clave_nueva_sugerida' => $claveNueva,
            'plantel_slug' => trim((string) ($r['plantel_slug'] ?? $r['sucursal'] ?? '')),
            'infantil' => $esKids ? '1' : '0',
            'infantil_csv' => $flagInfantil === null ? '' : ($flagInfantil ? '1' : '0'),
            'fecha_inicio' => $fechaPrev !== '' ? $fechaPrev : ($fechaRawPrev !== '' ? '¿' . $fechaRawPrev : '(vacía)'),
            'dias_clase' => trim((string) ($r['dias_clase'] ?? '')),
            'hora_inicio' => trim((string) ($r['hora_inicio'] ?? '')),
            'profesor_csv' => $profCsv,
            'profesor_sugerido' => $matchProf['nombre'] !== '' ? $matchProf['nombre'] : $profCsv,
            'profesor_id' => $profesorId,
            'prof_estado' => $profEstado,
            'aula_codigo' => trim((string) ($r['aula_codigo'] ?? '')),
            'accion' => $accion,
            '_line' => $line,
            '_id_plantel' => $idPlantel,
        ];
    }

    $msg = "Vista previa grupos: {$total} fila(s)";
    if ($faltanEsp > 0) {
        $msg .= ". {$faltanEsp} en esta página sin especialidad válida — elíjala en la tabla antes de Importar.";
    }
    if ($faltanFecha > 0) {
        $msg .= ". {$faltanFecha} sin fecha de inicio válida (YYYY-MM-DD o DD/MM/YYYY) — corríjala en el CSV; no se usará la fecha de hoy.";
    }
    if ($faltanProf > 0) {
        $msg .= ". {$faltanProf} sin profesor claro — elija en el desplegable (se preselecciona el nombre más parecido).";
    }
    $msg .= ' Kids: repita la misma clave/id legado en 2 filas (inglés + cómputo) o use ING-K (parte 2h+2h).';

    return [
        'ok' => true,
        'message' => $msg,
        'headers' => $headers,
        'total_filas' => $total,
        'offset' => $offset,
        'limit' => $limit,
        'muestra' => $muestra,
        'errores' => array_slice($errores, 0, 20),
        'especialidades' => $especialidades,
        'profesores' => $profesoresAll,
    ];
}

/**
 * @return list<int>
 */
function migracion_csv_parse_dias(string $dias): array
{
    $out = [];
    foreach (preg_split('/[,\s;]+/', $dias) ?: [] as $p) {
        $p = trim($p);
        if ($p === '' || !ctype_digit($p)) {
            continue;
        }
        $n = (int) $p;
        if ($n >= 0 && $n <= 6) {
            $out[] = $n;
        }
    }

    return array_values(array_unique($out));
}

function migracion_csv_infer_codigo_horario(array $dias, string $horaInicio): string
{
    sort($dias);
    if ($dias === [6]) {
        return 'S';
    }
    if ($dias === [0]) {
        return 'D';
    }
    $h = 8;
    if (preg_match('/^(\d{1,2})/', $horaInicio, $m)) {
        $h = (int) $m[1];
    }

    return $h >= 14 ? 'V' : 'M';
}

function migracion_csv_hora_fin(string $horaInicio, int $minutos = 120): string
{
    $horaInicio = trim($horaInicio);
    if ($horaInicio === '') {
        return '10:00';
    }
    if (!str_contains($horaInicio, ':')) {
        $horaInicio .= ':00';
    }
    $ts = strtotime('2000-01-01 ' . $horaInicio);
    if ($ts === false) {
        return '10:00';
    }

    return date('H:i', $ts + $minutos * 60);
}

function migracion_csv_resolver_fase(
    PDO $pdo,
    int $idEspecialidad,
    string $claveFaseEstimada,
    string $fechaInicio
): ?int {
    if (!function_exists('fase_listar')) {
        fase_ensure_schema($pdo);
    }
    $fases = fase_listar($pdo, $idEspecialidad);
    if ($fases === []) {
        return grupo_primera_fase($pdo, $idEspecialidad);
    }
    $clave = strtoupper(trim($claveFaseEstimada));
    if ($clave !== '') {
        foreach ($fases as $f) {
            $cf = strtoupper(trim((string) ($f['clave_fase'] ?? '')));
            $nf = strtoupper(trim((string) ($f['nombre_fase'] ?? '')));
            if ($cf === $clave || $nf === $clave || str_contains($nf, $clave)) {
                return (int) $f['id_fase'];
            }
        }
    }
    // Índice por semanas
    $semanasPorFase = 4;
    $st = $pdo->prepare('SELECT duracion_fase_semanas FROM especialidades WHERE id_especialidad = ?');
    $st->execute([$idEspecialidad]);
    $d = (int) $st->fetchColumn();
    if ($d > 0) {
        $semanasPorFase = $d;
    }
    $ts = strtotime($fechaInicio);
    $idx = 0;
    if ($ts !== false) {
        $semanas = (int) floor(max(0, time() - $ts) / 86400 / 7);
        $idx = (int) floor($semanas / max(1, $semanasPorFase));
        $idx = max(0, min(count($fases) - 1, $idx));
    }

    return (int) ($fases[$idx]['id_fase'] ?? $fases[0]['id_fase']);
}

/**
 * Texto comparable: minúsculas, sin acentos, solo letras/números/espacios.
 */
function migracion_csv_texto_normalizado(string $texto): string
{
    if (function_exists('alumno_nombre_normalizado_para_comparar')) {
        return alumno_nombre_normalizado_para_comparar($texto);
    }
    $texto = trim($texto);
    if ($texto === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if (is_string($ascii) && $ascii !== '') {
            $texto = $ascii;
        }
    }
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = preg_replace('/[^a-z0-9\s]/i', ' ', $texto) ?? '';
    $texto = preg_replace('/\s+/', ' ', $texto) ?? '';

    return trim($texto);
}

/**
 * Similitud 0–100 entre dos nombres (ignora acentos, mayúsculas y orden de tokens).
 */
function migracion_csv_similitud_nombre(string $a, string $b): float
{
    $na = migracion_csv_texto_normalizado($a);
    $nb = migracion_csv_texto_normalizado($b);
    if ($na === '' || $nb === '') {
        return 0.0;
    }
    if ($na === $nb) {
        return 100.0;
    }
    $ta = preg_split('/\s+/', $na) ?: [];
    $tb = preg_split('/\s+/', $nb) ?: [];
    sort($ta);
    sort($tb);
    $sa = implode(' ', $ta);
    $sb = implode(' ', $tb);
    if ($sa === $sb) {
        return 99.0;
    }
    $pct = 0.0;
    similar_text($na, $nb, $pct);
    $pct2 = 0.0;
    similar_text($sa, $sb, $pct2);
    $best = max((float) $pct, (float) $pct2);
    // Bonus si todos los tokens del CSV aparecen en HAY (o viceversa)
    if ($ta !== [] && $tb !== []) {
        $setB = array_fill_keys($tb, true);
        $hit = 0;
        foreach ($ta as $tok) {
            if (isset($setB[$tok])) {
                $hit++;
            }
        }
        $cover = 100.0 * $hit / max(count($ta), count($tb));
        $best = max($best, $cover);
    }

    return $best;
}

/**
 * Profesores (y roles docentes) de un plantel para el desplegable de migración.
 *
 * @return list<array{id_usuario:int,nombre:string,username:string,id_plantel:int}>
 */
function migracion_csv_profesores_opciones(PDO $pdo, ?int $idPlantel = null): array
{
    $sql = "SELECT id_usuario, username, email, nombre, apellido, id_plantel, rol
            FROM usuarios
            WHERE suspendido = 0
              AND rol IN ('profesor','gerente','supervisor','coordinador')";
    $params = [];
    if ($idPlantel !== null && $idPlantel > 0) {
        $sql .= ' AND id_plantel = ?';
        $params[] = $idPlantel;
    }
    $sql .= ' ORDER BY nombre ASC, apellido ASC';
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $nom = trim((string) ($r['nombre'] ?? '') . ' ' . (string) ($r['apellido'] ?? ''));
        if ($nom === '') {
            $nom = trim((string) ($r['username'] ?? $r['email'] ?? '')) ?: ('#' . (int) $r['id_usuario']);
        }
        $out[] = [
            'id_usuario' => (int) $r['id_usuario'],
            'nombre' => $nom,
            'username' => trim((string) ($r['username'] ?? '')),
            'id_plantel' => (int) ($r['id_plantel'] ?? 0),
        ];
    }

    return $out;
}

/**
 * Elige el profesor HAY más parecido al texto del CSV.
 *
 * @param list<array{id_usuario:int,nombre:string,username?:string}> $candidatos
 * @return array{id_usuario:?int, score:float, estado:string, nombre:string}
 */
function migracion_csv_mejor_profesor(
    string $username,
    string $nombreSugerido,
    array $candidatos,
    float $umbral = 75.0
): array {
    $username = trim($username);
    $nombreSugerido = trim(preg_replace('/\s+/', ' ', $nombreSugerido) ?? '');
    $vacio = ['id_usuario' => null, 'score' => 0.0, 'estado' => 'vacio', 'nombre' => ''];
    if ($username === '' && $nombreSugerido === '') {
        return $vacio;
    }
    if ($candidatos === []) {
        return [
            'id_usuario' => null,
            'score' => 0.0,
            'estado' => 'sin_match',
            'nombre' => $nombreSugerido !== '' ? $nombreSugerido : $username,
        ];
    }

    $bestId = null;
    $bestScore = -1.0;
    $bestNombre = '';
    $exacto = false;
    $userNorm = migracion_csv_texto_normalizado($username);
    $nomNorm = migracion_csv_texto_normalizado($nombreSugerido);

    foreach ($candidatos as $c) {
        $id = (int) ($c['id_usuario'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $cn = (string) ($c['nombre'] ?? '');
        $cu = (string) ($c['username'] ?? '');
        $score = 0.0;

        if ($username !== '') {
            if (strcasecmp($cu, $username) === 0) {
                $score = 100.0;
                $exacto = true;
            } elseif ($userNorm !== '' && migracion_csv_texto_normalizado($cu) === $userNorm) {
                $score = 100.0;
                $exacto = true;
            } elseif ($userNorm !== '' && str_contains(migracion_csv_texto_normalizado($cu), $userNorm)) {
                $score = max($score, 92.0);
            }
        }
        if ($nombreSugerido !== '') {
            $s = migracion_csv_similitud_nombre($nombreSugerido, $cn);
            if ($nomNorm !== '' && migracion_csv_texto_normalizado($cn) === $nomNorm) {
                $s = 100.0;
                $exacto = true;
            }
            $score = max($score, $s);
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestId = $id;
            $bestNombre = $cn;
        }
    }

    if ($bestId === null || $bestScore < $umbral) {
        return [
            'id_usuario' => null,
            'score' => max(0.0, $bestScore),
            'estado' => 'sin_match',
            'nombre' => $nombreSugerido !== '' ? $nombreSugerido : $username,
        ];
    }

    return [
        'id_usuario' => $bestId,
        'score' => $bestScore,
        'estado' => ($exacto || $bestScore >= 99.0) ? 'ok' : 'aproximado',
        'nombre' => $bestNombre,
    ];
}

/**
 * Resuelve id de profesor: username exacto, nombre exacto (sin acentos) o fuzzy.
 */
function migracion_csv_buscar_profesor(PDO $pdo, int $idPlantel, string $username, string $nombreSugerido): ?int
{
    $username = trim($username);
    if ($username !== '') {
        $st = $pdo->prepare(
            'SELECT id_usuario FROM usuarios
             WHERE (username = ? OR email LIKE ?) AND id_plantel = ?
             LIMIT 1'
        );
        $st->execute([$username, $username . '%', $idPlantel]);
        $id = $st->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = ? OR email LIKE ? LIMIT 1');
        $st->execute([$username, $username . '%']);
        $id = $st->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
    }

    $candidatos = migracion_csv_profesores_opciones($pdo, $idPlantel);
    $match = migracion_csv_mejor_profesor($username, $nombreSugerido, $candidatos);
    if ($match['id_usuario'] !== null) {
        return (int) $match['id_usuario'];
    }

    // Último intento: todos los planteles (nombre muy parecido)
    if ($nombreSugerido !== '' || $username !== '') {
        $matchGlobal = migracion_csv_mejor_profesor(
            $username,
            $nombreSugerido,
            migracion_csv_profesores_opciones($pdo, null),
            90.0
        );
        if ($matchGlobal['id_usuario'] !== null) {
            return (int) $matchGlobal['id_usuario'];
        }
    }

    return null;
}

/**
 * Lee id_profesor override (por línea CSV, clave_legado o id_grupo_legado).
 *
 * @param array<string, array<string, mixed>> $overrides
 */
function migracion_csv_override_id_profesor(
    array $overrides,
    string $idLeg,
    int $line,
    string $claveLegado = ''
): ?int {
    $bases = [];
    $claveLegado = trim($claveLegado);
    if ($claveLegado !== '') {
        $bases[] = $claveLegado;
    }
    $idLeg = migracion_csv_norm_id_legado($idLeg);
    if ($idLeg !== '' && !in_array($idLeg, $bases, true)) {
        $bases[] = $idLeg;
    }
    foreach ($bases as $base) {
        $keyed = $base . '@L' . $line;
        if (isset($overrides[$keyed]) && is_array($overrides[$keyed])
            && !empty($overrides[$keyed]['id_profesor']) && (int) $overrides[$keyed]['id_profesor'] > 0) {
            return (int) $overrides[$keyed]['id_profesor'];
        }
        if (!isset($overrides[$base]) || !is_array($overrides[$base])) {
            continue;
        }
        $porLinea = $overrides[$base]['profesores_por_linea'] ?? null;
        if (is_array($porLinea) && isset($porLinea[$line]) && (int) $porLinea[$line] > 0) {
            return (int) $porLinea[$line];
        }
        if (is_array($porLinea) && isset($porLinea[(string) $line]) && (int) $porLinea[(string) $line] > 0) {
            return (int) $porLinea[(string) $line];
        }
        if (!empty($overrides[$base]['id_profesor']) && (int) $overrides[$base]['id_profesor'] > 0) {
            return (int) $overrides[$base]['id_profesor'];
        }
    }
    $lineKey = 'L' . $line;
    if (isset($overrides[$lineKey]['id_profesor']) && (int) $overrides[$lineKey]['id_profesor'] > 0) {
        return (int) $overrides[$lineKey]['id_profesor'];
    }

    return null;
}

/**
 * Override de especialidad por clave_legado o id_grupo_legado.
 *
 * @param array<string, array<string, mixed>> $overrides
 */
function migracion_csv_override_clave_especialidad(array $overrides, string $idLeg, string $claveLegado): string
{
    foreach ([trim($claveLegado), migracion_csv_norm_id_legado($idLeg)] as $base) {
        if ($base === '' || !isset($overrides[$base]) || !is_array($overrides[$base])) {
            continue;
        }
        $esp = strtoupper(trim((string) ($overrides[$base]['clave_especialidad'] ?? '')));
        if ($esp !== '') {
            return $esp;
        }
    }

    return '';
}

function migracion_csv_asegurar_aula(PDO $pdo, int $idPlantel, string $codigo): ?int
{
    $codigo = trim($codigo);
    if ($codigo === '') {
        return null;
    }
    aula_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT id_aula FROM plantel_aulas WHERE id_plantel = ? AND codigo = ? LIMIT 1');
    $st->execute([$idPlantel, $codigo]);
    $id = $st->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $res = aula_guardar($pdo, $idPlantel, [
        'codigo' => $codigo,
        'nombre' => $codigo,
        'capacidad' => 20,
        'activo' => 1,
        'todas_especialidades' => 1,
        'tiene_pizarron' => 1,
    ]);

    return !empty($res['ok']) ? (int) ($res['id_aula'] ?? 0) ?: null : null;
}

/**
 * @return array{ok:bool, message:string, creados?:int, actualizados?:int, errores?:list<string>}
 */
function migracion_csv_importar_personal(PDO $pdo, array $rows, ?int $forzarPlantel = null): array
{
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    migracion_csv_ensure_schema($pdo);
    if (function_exists('usuario_ensure_schema')) {
        usuario_ensure_schema($pdo);
    }
    $creados = 0;
    $actualizados = 0;
    $errores = [];
    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $idLeg = trim((string) ($r['id_usuario_legado'] ?? ''));
        $nombres = trim((string) ($r['nombres'] ?? ''));
        $apPat = trim((string) ($r['apellido_paterno'] ?? ''));
        $apMat = trim((string) ($r['apellido_materno'] ?? ''));
        if ($nombres === '' && $apPat === '') {
            continue;
        }
        $idPlantel = migracion_csv_resolver_plantel($pdo, (string) ($r['plantel_slug'] ?? ''), $forzarPlantel);
        if ($idPlantel === null || $idPlantel <= 0) {
            $errores[] = "Línea {$line}: plantel no resuelto";
            continue;
        }
        $rol = trim((string) ($r['rol'] ?? 'profesor'));
        if ($rol === '') {
            $rol = 'profesor';
        }
        $username = trim((string) ($r['username'] ?? ''));
        $emailLeg = strtolower(trim((string) ($r['email_legado'] ?? '')));
        if ($username === '') {
            $local = strtolower(preg_replace('/[^a-z0-9]/i', '', substr($nombres, 0, 1) . $apPat) ?? '');
            $username = $local !== '' ? $local : ('user' . ($idLeg !== '' ? $idLeg : $line));
        }
        $email = $emailLeg;
        if ($email === '' || !str_contains($email, '@')) {
            $dom = defined('INSTITUTIONAL_EMAIL_DOMAIN') ? INSTITUTIONAL_EMAIL_DOMAIN : 'cncm.edu.mx';
            $email = $username . '@' . $dom;
        }
        $existing = null;
        if ($idLeg !== '') {
            $existing = migracion_csv_map_get($pdo, 'usuario', $idLeg);
        }
        if ($existing === null) {
            $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = ? OR email = ? LIMIT 1');
            $st->execute([$username, $email]);
            $ex = $st->fetchColumn();
            if ($ex !== false) {
                $existing = (int) $ex;
            }
        }
        $apellido = trim($apPat . ' ' . $apMat);
        if ($existing !== null && $existing > 0) {
            $pdo->prepare(
                'UPDATE usuarios SET nombre=?, apellido=?, rol=?, id_plantel=?, email=? WHERE id_usuario=?'
            )->execute([$nombres, $apellido, $rol, $idPlantel, $email, $existing]);
            if ($idLeg !== '') {
                migracion_csv_map_set($pdo, 'usuario', $idLeg, $existing, $username);
            }
            $actualizados++;
        } else {
            $pass = function_exists('cuenta_password_inicial') ? cuenta_password_inicial() : 'Cncm*1234';
            $depto = function_exists('rbac_departamento_para_rol')
                ? rbac_departamento_para_rol($pdo, $rol)
                : 'administrativo';
            if (!in_array($depto, ['ingles', 'computacion', 'administrativo', 'ventas', 'otro'], true)) {
                $depto = 'administrativo';
            }
            $pdo->prepare(
                'INSERT INTO usuarios (nombre, apellido, username, email, password, rol, departamento, id_plantel, fecha_creacion)
                 VALUES (?,?,?,?,?,?,?,?,NOW())'
            )->execute([
                $nombres,
                $apellido,
                $username,
                $email,
                password_hash($pass, PASSWORD_BCRYPT),
                $rol,
                $depto,
                $idPlantel,
            ]);
            $idHay = (int) $pdo->lastInsertId();
            if ($idLeg !== '') {
                migracion_csv_map_set($pdo, 'usuario', $idLeg, $idHay, $username);
            }
            $creados++;
        }
    }
    migracion_csv_log($pdo, 'personal', 'info', "Personal: {$creados} creados, {$actualizados} actualizados");

    return [
        'ok' => true,
        'message' => "{$creados} creado(s), {$actualizados} actualizado(s)",
        'creados' => $creados,
        'actualizados' => $actualizados,
        'errores' => array_slice($errores, 0, 30),
    ];
}

/**
 * @return array{ok:bool, message:string, creados?:int, errores?:list<string>}
 */
function migracion_csv_importar_aulas(PDO $pdo, array $rows, ?int $forzarPlantel = null): array
{
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    aula_ensure_schema($pdo);
    $creados = 0;
    $errores = [];
    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $codigo = trim((string) ($r['codigo'] ?? ''));
        if ($codigo === '') {
            continue;
        }
        $idPlantel = migracion_csv_resolver_plantel($pdo, (string) ($r['plantel_slug'] ?? ''), $forzarPlantel);
        if ($idPlantel === null) {
            $errores[] = "Línea {$line}: plantel no resuelto";
            continue;
        }
        $id = migracion_csv_asegurar_aula($pdo, $idPlantel, $codigo);
        if ($id) {
            // actualizar nombre si viene
            $nombre = trim((string) ($r['nombre'] ?? ''));
            if ($nombre !== '') {
                $pdo->prepare('UPDATE plantel_aulas SET nombre = ? WHERE id_aula = ?')->execute([$nombre, $id]);
            }
            $creados++;
        } else {
            $errores[] = "Línea {$line}: no se pudo crear aula {$codigo}";
        }
    }

    return [
        'ok' => true,
        'message' => "{$creados} aula(s) asegurada(s)",
        'creados' => $creados,
        'errores' => array_slice($errores, 0, 30),
    ];
}

/**
 * Interpreta flags CSV 0/1 (y variantes Excel/texto).
 * null = vacío / no indicado.
 */
function migracion_csv_parse_flag01(mixed $v): ?bool
{
    if ($v === null) {
        return null;
    }
    if (is_bool($v)) {
        return $v;
    }
    if (is_int($v) || is_float($v)) {
        if ((float) $v == 0.0) {
            return false;
        }
        if ((float) $v == 1.0) {
            return true;
        }
    }
    $s = strtolower(trim((string) $v));
    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
    if ($s === '' || $s === '-' || $s === '?' ) {
        return null;
    }
    // Excel a veces deja "0.0"
    if (preg_match('/^0+(?:\.0+)?$/', $s)) {
        return false;
    }
    if (preg_match('/^1+(?:\.0+)?$/', $s)) {
        return true;
    }
    if (in_array($s, ['false', 'no', 'n', 'off', 'falso'], true)) {
        return false;
    }
    if (in_array($s, ['true', 'si', 'sí', 's', 'yes', 'y', 'on', 'verdadero'], true)) {
        return true;
    }

    return null;
}

/** Normaliza clave_legado para agrupar (mayúsculas, sin espacios raros). */
function migracion_csv_norm_clave_legado(string $clave): string
{
    $clave = strtoupper(trim($clave));
    $clave = str_replace(["\xC2\xA0", "\t", "\r", "\n"], ' ', $clave);
    $clave = preg_replace('/\s+/u', '', $clave) ?? $clave;

    return $clave;
}

/**
 * Clave para agrupar filas del CSV de grupos (legado).
 * Preferir clave_legado sola (IK+CK del mismo legado deben juntarse aunque
 * plantel_slug venga vacío o distinto entre filas). Si no hay clave, id_grupo_legado.
 */
function migracion_csv_grupo_row_key(array $r, ?int $rowIndex = null): string
{
    $clave = migracion_csv_norm_clave_legado((string) ($r['clave_legado'] ?? $r['_clave_anterior'] ?? ''));
    if ($clave !== '') {
        return 'clave:' . $clave;
    }
    $idLeg = migracion_csv_norm_id_legado((string) ($r['id_grupo_legado'] ?? $r['_id_leg'] ?? ''));
    if ($idLeg !== '') {
        return 'id:' . $idLeg;
    }
    $line = (int) ($r['_line'] ?? 0);
    if ($line > 0) {
        return 'row:L' . $line;
    }
    if ($rowIndex !== null) {
        return 'row:i' . $rowIndex;
    }
    $plantel = strtolower(trim((string) ($r['plantel_slug'] ?? $r['sucursal'] ?? '')));

    return 'row:orphan|' . $plantel . '|' . md5(json_encode($r) ?: uniqid('', true));
}

/**
 * Decide si un grupo es kids: respeta infantil=0/1 del CSV.
 * Solo infiere pareja por filas repetidas si infantil no está en 0.
 *
 * @param list<array<string,mixed>> $filasNormalizadas
 */
function migracion_csv_filas_son_kids(array $filasNormalizadas, int $nFilas): bool
{
    $algunaTrue = false;
    $algunaFalse = false;
    $algunaEspKids = false;
    foreach ($filasNormalizadas as $f) {
        if (array_key_exists('_flag_infantil', $f)) {
            $flag = $f['_flag_infantil'];
        } else {
            $flag = migracion_csv_parse_flag01($f['infantil'] ?? null);
        }
        if ($flag === true) {
            $algunaTrue = true;
        } elseif ($flag === false) {
            $algunaFalse = true;
        }
        if (!empty($f['_es_kids_esp'])) {
            $algunaEspKids = true;
        } else {
            $esp = strtoupper(trim((string) ($f['_clave_esp'] ?? $f['clave_especialidad'] ?? '')));
            if ($esp !== '') {
                $meta = migracion_csv_area_desde_clave_especialidad($esp);
                if (!empty($meta['kids'])) {
                    $algunaEspKids = true;
                }
            }
        }
    }
    // Explícito: si TODAS las filas dicen infantil=0 y ninguna especialidad kids → adulto
    if ($algunaFalse && !$algunaTrue && !$algunaEspKids) {
        return false;
    }
    if ($algunaTrue || $algunaEspKids) {
        return true;
    }
    // Sin flag: 2+ filas misma clave → pareja kids (conveniencia)
    if ($nFilas >= 2) {
        return true;
    }
    foreach ($filasNormalizadas as $f) {
        if (!empty($f['_es_kids'])) {
            return true;
        }
    }

    return false;
}

/**
 * Normaliza una fila de grupo para import (overrides, fechas, horas).
 *
 * @return array<string, mixed>|null
 */
function migracion_csv_grupo_normalizar_fila(array $r, int $line, array $overrides): ?array
{
    $idLeg = migracion_csv_norm_id_legado((string) ($r['id_grupo_legado'] ?? ''));
    $r['id_grupo_legado'] = $idLeg;
    $claveAnterior = trim((string) ($r['clave_legado'] ?? ''));
    $ovEsp = migracion_csv_override_clave_especialidad($overrides, $idLeg, $claveAnterior);
    if ($ovEsp !== '') {
        $r['clave_especialidad'] = $ovEsp;
    }
    $flagInfantil = migracion_csv_parse_flag01($r['infantil'] ?? null);
    $claveEsp = migracion_csv_normalizar_clave_especialidad((string) ($r['clave_especialidad'] ?? ''));
    if ($claveEsp === '' && $flagInfantil === true) {
        $claveEsp = 'ING-K';
    }
    $r['clave_especialidad'] = $claveEsp;
    $metaEsp = $claveEsp !== '' ? migracion_csv_area_desde_clave_especialidad($claveEsp) : ['area' => 'I', 'extensivo' => false, 'kids' => false];
    // Por fila: el flag 0 gana sobre especialidad solo si no es *-K
    if ($flagInfantil === false && empty($metaEsp['kids'])) {
        $esKids = false;
    } elseif ($flagInfantil === true || !empty($metaEsp['kids'])) {
        $esKids = true;
    } else {
        $esKids = false;
    }
    $r['_flag_infantil'] = $flagInfantil;
    $r['_es_kids_esp'] = !empty($metaEsp['kids']);

    $fechaRaw = trim((string) ($r['fecha_inicio_estimada'] ?? ''));
    if ($fechaRaw === '') {
        $fechaRaw = trim((string) ($r['fecha_inicio_legado'] ?? ''));
    }
    $fecha = migracion_csv_parse_fecha($fechaRaw);
    if ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $fecha = '';
    }
    // No usar "hoy" como fallback: fechas inventadas marcan pendiente_autorizacion / distorsionan fase.
    $dias = migracion_csv_parse_dias((string) ($r['dias_clase'] ?? ''));
    $horaIni = trim((string) ($r['hora_inicio'] ?? '08:00'));
    if ($horaIni === '') {
        $horaIni = '08:00';
    }
    $horaFin = trim((string) ($r['hora_fin'] ?? ''));
    if ($horaFin === '') {
        $horaFin = migracion_csv_hora_fin($horaIni, $esKids ? 120 : 120);
    }
    if ($claveAnterior === '' && $idLeg !== '') {
        $claveAnterior = 'legado#' . $idLeg;
    }
    $horarioTexto = trim((string) ($r['horario_legado'] ?? ''));
    if ($horarioTexto === '' && $dias !== []) {
        $horarioTexto = 'Días ' . implode(',', $dias) . ' ' . $horaIni;
    }

    $r['_line'] = $line;
    $r['_id_leg'] = $idLeg;
    $r['_clave_esp'] = $claveEsp;
    $r['_meta_esp'] = $metaEsp;
    $r['_es_kids'] = $esKids;
    $r['_fecha'] = $fecha;
    $r['_fecha_raw'] = $fechaRaw;
    $r['_dias'] = $dias;
    $r['_hora_ini'] = $horaIni;
    $r['_hora_fin'] = $horaFin;
    $r['_clave_anterior'] = $claveAnterior;
    $r['_horario_texto'] = $horarioTexto;
    $r['_clave_fase'] = trim((string) ($r['clave_fase_estimada'] ?? ''));
    $r['_id_profesor_override'] = migracion_csv_override_id_profesor($overrides, $idLeg, $line, $claveAnterior);

    return $r;
}

/**
 * Resuelve profesor de una fila normalizada (override UI → búsqueda fuzzy).
 */
function migracion_csv_resolver_profesor_fila(PDO $pdo, int $idPlantel, array $r): ?int
{
    $ov = $r['_id_profesor_override'] ?? null;
    if ($ov !== null && (int) $ov > 0) {
        return (int) $ov;
    }

    return migracion_csv_buscar_profesor(
        $pdo,
        $idPlantel,
        (string) ($r['profesor_username'] ?? ''),
        (string) ($r['profesor_sugerido'] ?? '')
    );
}

/**
 * Aplica horario/aula a un grupo kids ya creado.
 *
 * @param list<int> $dias
 */
function migracion_csv_grupo_aplicar_horario_aula(
    PDO $pdo,
    int $idGrupo,
    array $dias,
    string $horaIni,
    string $horaFin,
    ?int $idAula,
    string $aulaCodigo,
    ?string $horarioTexto
): void {
    if ($dias !== []) {
        grupo_guardar_horarios($pdo, $idGrupo, $dias, $horaIni, $horaFin);
    }
    $codH = migracion_csv_infer_codigo_horario($dias !== [] ? $dias : [6], $horaIni);
    $txt = $horarioTexto;
    if (($txt === null || $txt === '') && $dias !== [] && function_exists('grupo_horario_texto_desde_dias')) {
        $txt = grupo_horario_texto_desde_dias($dias, $horaIni, $horaFin);
    }
    $pdo->prepare('UPDATE grupos SET codigo_horario = ?, horario_texto = COALESCE(?, horario_texto) WHERE id_grupo = ?')
        ->execute([$codH, $txt !== '' ? $txt : null, $idGrupo]);
    if ($idAula) {
        $pdo->prepare('UPDATE grupos SET id_aula = ?, aula = ? WHERE id_grupo = ?')
            ->execute([$idAula, $aulaCodigo !== '' ? $aulaCodigo : null, $idGrupo]);
    }
}

/**
 * Decide qué fila es inglés vs computación en una pareja de 2 filas.
 *
 * @param list<array<string,mixed>> $pair
 * @return array{0:array<string,mixed>,1:array<string,mixed>} [ingles, computacion]
 */
function migracion_csv_kids_ordenar_filas(array $pair): array
{
    $a = $pair[0];
    $b = $pair[1];
    $espA = (string) ($a['_clave_esp'] ?? '');
    $espB = (string) ($b['_clave_esp'] ?? '');
    if (str_starts_with($espB, 'ING') && str_starts_with($espA, 'COMP')) {
        return [$b, $a];
    }
    if (str_starts_with($espA, 'COMP') && !str_starts_with($espB, 'COMP')) {
        return [$b, $a];
    }
    // Por defecto: 1ª fila = inglés, 2ª = computación (08:00 aula / 10:00 lab)
    return [$a, $b];
}

/**
 * @param array<string, array{clave_especialidad?:string,id_profesor?:int,profesores_por_linea?:array<int,int>}> $overrides
 * @return array{ok:bool, message:string, creados?:int, omitidos?:int, errores?:list<string>}
 */
function migracion_csv_importar_grupos(PDO $pdo, array $rows, ?int $forzarPlantel = null, array $overrides = []): array
{
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    migracion_csv_ensure_schema($pdo);
    fase_ensure_schema($pdo);
    grupo_clave_ensure_schema($pdo);
    asistencia_ensure_schema($pdo);
    migracion_csv_asegurar_especialidades_kids($pdo);
    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'grupos', 'clave_anterior', 'VARCHAR(50) NULL', 'clave');
        plantel_ensure_column($pdo, 'grupos', 'id_aula', 'INT UNSIGNED NULL', 'aula');
    }
    if (function_exists('aula_ensure_schema')) {
        aula_ensure_schema($pdo);
    }

    $creados = 0;
    $omitidos = 0;
    $errores = [];

    /** @var array<string, list<array<string,mixed>>> $grupos */
    $grupos = [];
    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $norm = migracion_csv_grupo_normalizar_fila($r, $line, $overrides);
        if ($norm === null) {
            continue;
        }
        $key = migracion_csv_grupo_row_key($norm, $i);
        if (!isset($grupos[$key])) {
            $grupos[$key] = [];
        }
        $grupos[$key][] = $norm;
    }

    foreach ($grupos as $key => $filas) {
        $primera = $filas[0];
        $line = (int) ($primera['_line'] ?? 2);
        $idLeg = (string) ($primera['_id_leg'] ?? '');
        $nFilas = count($filas);

        // Respeta infantil=0; solo agrupa pareja kids si el CSV lo indica o no niega el flag
        $esKids = migracion_csv_filas_son_kids($filas, $nFilas);

        $claveAntYa = (string) ($primera['_clave_anterior'] ?? '');
        $idLegNorm = migracion_csv_norm_id_legado($idLeg);
        $idHayYa = null;
        $idCompYa = null;
        foreach ([$idLegNorm, $claveAntYa] as $kTry) {
            if ($kTry === '') {
                continue;
            }
            $found = migracion_csv_map_get($pdo, 'grupo', $kTry);
            if ($found !== null && $found > 0) {
                $idHayYa = $found;
                $idCompYa = migracion_csv_map_get($pdo, 'grupo_comp', $kTry);
                break;
            }
        }
        if ($idHayYa !== null) {
            // Reimport con id tras un mapa solo-por-clave (o viceversa): completar alias sin duplicar
            migracion_csv_map_alias_grupo(
                $pdo,
                $idLegNorm,
                $claveAntYa,
                $idHayYa,
                $idCompYa,
                'alias_reimport'
            );
            $omitidos += $esKids ? 2 : 1;
            continue;
        }

        $idPlantel = migracion_csv_resolver_plantel(
            $pdo,
            (string) ($primera['plantel_slug'] ?? $primera['sucursal'] ?? ''),
            $forzarPlantel
        );
        if ($idPlantel === null) {
            $errores[] = "Línea {$line}: plantel no resuelto";
            continue;
        }

        $fechaPrimera = (string) ($primera['_fecha'] ?? '');
        if ($fechaPrimera === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPrimera)) {
            $rawF = trim((string) ($primera['_fecha_raw'] ?? $primera['fecha_inicio_estimada'] ?? $primera['fecha_inicio_legado'] ?? ''));
            $errores[] = "Línea {$line}: fecha de inicio inválida o vacía"
                . ($rawF !== '' ? " («{$rawF}»)" : '')
                . ' — use YYYY-MM-DD o DD/MM/YYYY desde el legado (no se inventa la de hoy)';
            continue;
        }

        try {
            if ($esKids) {
                $ing = $primera;
                $comp = null;
                if ($nFilas >= 2) {
                    [$ing, $comp] = migracion_csv_kids_ordenar_filas(array_slice($filas, 0, 2));
                    if ($nFilas > 2) {
                        $errores[] = "Línea {$line}: clave legado repetida {$nFilas} veces; se usaron las 2 primeras (inglés/cómputo)";
                    }
                } else {
                    // Una sola fila: solo ING-K parte 2h+2h. CK sola NO crea pareja (evita duplicar
                    // cuando la fila IK no se agrupó por clave/plantel distinto).
                    $espPrim = strtoupper((string) ($ing['_clave_esp'] ?? ''));
                    $esSoloComp = str_starts_with($espPrim, 'COMP') || $espPrim === 'CK';
                    if ($esSoloComp) {
                        $claveLegMsg = (string) ($ing['_clave_anterior'] ?? $ing['clave_legado'] ?? '');
                        $errores[] = "Línea {$line}: fila cómputo kids"
                            . ($claveLegMsg !== '' ? " («{$claveLegMsg}»)" : '')
                            . ' sin su fila de inglés con la misma clave_legado — no se crea (impide duplicar IK+CK).'
                            . ' Suba ambas filas (IK y CK) con la misma clave_legado.';
                        continue;
                    }
                    // Una sola fila ING-K: bloque continuo 2h + 2h el mismo día
                    $ingRow = $ing;
                    $compRow = $ing;
                    $hi = (string) $ingRow['_hora_ini'];
                    $ingRow['_hora_fin'] = migracion_csv_hora_fin($hi, 120);
                    $compRow['_hora_ini'] = $ingRow['_hora_fin'];
                    $compRow['_hora_fin'] = migracion_csv_hora_fin((string) $compRow['_hora_ini'], 120);
                    $compRow['_horario_texto'] = '';
                    $ing = $ingRow;
                    $comp = $compRow;
                }

                $claveEspIng = (string) ($ing['_clave_esp'] ?? '');
                if ($claveEspIng === '' || str_starts_with($claveEspIng, 'COMP')) {
                    $claveEspIng = 'ING-K';
                }
                $idProf = migracion_csv_resolver_profesor_fila($pdo, $idPlantel, $ing);
                if ($idProf === null) {
                    $idProf = migracion_csv_resolver_profesor_fila($pdo, $idPlantel, $comp);
                }
                $idEspIng = grupo_id_especialidad_infantil($pdo, 'ingles');
                $idEspComp = grupo_id_especialidad_infantil($pdo, 'computacion');
                $fecha = $fechaPrimera;
                $claveFase = (string) ($ing['_clave_fase'] ?? '');
                $idFaseIng = $idEspIng ? migracion_csv_resolver_fase($pdo, $idEspIng, $claveFase, $fecha) : null;
                $idFaseComp = $idEspComp ? migracion_csv_resolver_fase($pdo, $idEspComp, $claveFase, $fecha) : null;

                // Crear pareja con horario provisional de inglés; luego se reescribe cada uno
                $diasProv = $ing['_dias'] !== [] ? $ing['_dias'] : ($comp['_dias'] ?? []);
                $res = grupo_infantil_crear_pareja(
                    $pdo,
                    $idPlantel,
                    $fecha,
                    $idProf,
                    $idFaseIng,
                    $idFaseComp,
                    $diasProv !== [] ? $diasProv : [6],
                    (string) $ing['_hora_ini'],
                    (string) $ing['_hora_fin'],
                    (string) ($ing['_horario_texto'] ?? '') ?: null
                );
                if (empty($res['ok'])) {
                    $errores[] = "Línea {$line}: " . ($res['message'] ?? 'error kids');
                    continue;
                }
                $idIng = (int) $res['id_grupo_ingles'];
                $idComp = (int) $res['id_grupo_computacion'];
                $claveIng = (string) ($res['clave_ingles'] ?? '');
                $claveComp = (string) ($res['clave_computacion'] ?? '');

                $idAulaIng = migracion_csv_asegurar_aula($pdo, $idPlantel, (string) ($ing['aula_codigo'] ?? ''));
                $idAulaComp = migracion_csv_asegurar_aula($pdo, $idPlantel, (string) ($comp['aula_codigo'] ?? ''));
                migracion_csv_grupo_aplicar_horario_aula(
                    $pdo,
                    $idIng,
                    $ing['_dias'] !== [] ? $ing['_dias'] : $diasProv,
                    (string) $ing['_hora_ini'],
                    (string) $ing['_hora_fin'],
                    $idAulaIng,
                    (string) ($ing['aula_codigo'] ?? ''),
                    (string) ($ing['_horario_texto'] ?? '')
                );
                migracion_csv_grupo_aplicar_horario_aula(
                    $pdo,
                    $idComp,
                    $comp['_dias'] !== [] ? $comp['_dias'] : $diasProv,
                    (string) $comp['_hora_ini'],
                    (string) $comp['_hora_fin'],
                    $idAulaComp,
                    (string) ($comp['aula_codigo'] ?? ''),
                    (string) ($comp['_horario_texto'] ?? '')
                );

                $claveAnterior = (string) ($ing['_clave_anterior'] ?? '');
                if ($claveAnterior !== '') {
                    $pdo->prepare('UPDATE grupos SET clave_anterior = ? WHERE id_grupo IN (?,?)')
                        ->execute([$claveAnterior, $idIng, $idComp]);
                }
                // Profesor de la 2ª fila si difiere
                $idProfComp = migracion_csv_resolver_profesor_fila($pdo, $idPlantel, $comp);
                if ($idProfComp && $idProfComp !== $idProf) {
                    $pdo->prepare('UPDATE grupos SET id_profesor = ? WHERE id_grupo = ?')
                        ->execute([$idProfComp, $idComp]);
                }

                $mapKey = migracion_csv_map_key_grupo($idLeg, $claveAnterior);
                if ($mapKey !== '') {
                    migracion_csv_map_set($pdo, 'grupo', $mapKey, $idIng, 'kids_ingles; legado=' . $claveAnterior . '; hay=' . $claveIng);
                    migracion_csv_map_set($pdo, 'grupo_comp', $mapKey, $idComp, 'kids_computacion; legado=' . $claveAnterior . '; hay=' . $claveComp);
                    // Si hubo id y clave distintos, mapear ambos para que alumnos.csv enlace con cualquiera
                    if ($idLeg !== '' && $claveAnterior !== '' && $idLeg !== $claveAnterior) {
                        migracion_csv_map_set($pdo, 'grupo', $claveAnterior, $idIng, 'kids_ingles; alias_clave; hay=' . $claveIng);
                        migracion_csv_map_set($pdo, 'grupo_comp', $claveAnterior, $idComp, 'kids_computacion; alias_clave; hay=' . $claveComp);
                    }
                }
                migracion_csv_grupo_set_estado_por_fecha($pdo, [$idIng, $idComp], $fecha);
                $creados += 2;
            } else {
                $r = $primera;
                $claveEsp = (string) ($r['_clave_esp'] ?? '');
                if ($claveEsp === '') {
                    $errores[] = "Línea {$line}: falta clave_especialidad (defínala en la vista previa)";
                    continue;
                }
                $metaEsp = $r['_meta_esp'];
                $idEsp = migracion_csv_resolver_especialidad($pdo, $claveEsp);
                if ($idEsp === null) {
                    $errores[] = "Línea {$line}: especialidad {$claveEsp} no existe en HAY";
                    continue;
                }
                $dias = $r['_dias'];
                $horaIni = (string) $r['_hora_ini'];
                $horaFin = (string) $r['_hora_fin'];
                $idProf = migracion_csv_resolver_profesor_fila($pdo, $idPlantel, $r);
                $idAula = migracion_csv_asegurar_aula($pdo, $idPlantel, (string) ($r['aula_codigo'] ?? ''));
                $area = $metaEsp['area'];
                $ext = !empty($metaEsp['extensivo']);
                $codH = migracion_csv_infer_codigo_horario($dias !== [] ? $dias : [6], $horaIni);
                $gen = grupo_clave_generar($pdo, $idPlantel, $area, $codH, $ext, false, '');
                $clave = (string) ($gen['clave'] ?? ('G' . $line));
                $idFase = migracion_csv_resolver_fase($pdo, $idEsp, (string) $r['_clave_fase'], (string) $r['_fecha']);
                $pdo->prepare(
                    'INSERT INTO grupos (
                        id_plantel, clave, clave_anterior, fecha_inicio, id_profesor, id_aula, aula,
                        id_especialidad, id_fase_actual, horario_texto,
                        codigo_area, codigo_horario, es_extensivo, es_personalizado, numero_secuencial
                     ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $idPlantel,
                    $clave,
                    (string) $r['_clave_anterior'] !== '' ? (string) $r['_clave_anterior'] : null,
                    (string) $r['_fecha'],
                    $idProf,
                    $idAula,
                    trim((string) ($r['aula_codigo'] ?? '')) ?: null,
                    $idEsp,
                    $idFase,
                    (string) $r['_horario_texto'] !== '' ? (string) $r['_horario_texto'] : null,
                    $gen['codigo_area'] ?? $area,
                    $gen['codigo_horario'] ?? $codH,
                    !empty($gen['es_extensivo']) ? 1 : 0,
                    0,
                    $gen['numero_secuencial'] ?? null,
                ]);
                $idGrupo = (int) $pdo->lastInsertId();
                if ($dias !== []) {
                    grupo_guardar_horarios($pdo, $idGrupo, $dias, $horaIni, $horaFin);
                }
                if (function_exists('tutor_asignar_grupo')) {
                    tutor_asignar_grupo($pdo, $idGrupo);
                }
                $claveAntAdulto = (string) ($r['_clave_anterior'] ?? '');
                $mapKey = migracion_csv_map_key_grupo($idLeg, $claveAntAdulto);
                if ($mapKey !== '') {
                    migracion_csv_map_set(
                        $pdo,
                        'grupo',
                        $mapKey,
                        $idGrupo,
                        'legado=' . $claveAntAdulto . '; hay=' . $clave . '; esp=' . $claveEsp
                    );
                    if ($idLeg !== '' && $claveAntAdulto !== '' && $idLeg !== $claveAntAdulto) {
                        migracion_csv_map_set(
                            $pdo,
                            'grupo',
                            $claveAntAdulto,
                            $idGrupo,
                            'alias_clave; hay=' . $clave . '; esp=' . $claveEsp
                        );
                    }
                }
                migracion_csv_grupo_set_estado_por_fecha($pdo, [$idGrupo], (string) $r['_fecha']);
                $creados++;
            }
        } catch (Throwable $e) {
            $errores[] = "Línea {$line}: " . $e->getMessage();
        }
    }

    migracion_csv_log($pdo, 'grupos', 'info', "Grupos: {$creados} creados, {$omitidos} omitidos");

    return [
        'ok' => ($creados + $omitidos) > 0 || $errores === [],
        'message' => "{$creados} grupo(s) creado(s), {$omitidos} omitido(s)",
        'creados' => $creados,
        'omitidos' => $omitidos,
        'errores' => array_slice($errores, 0, 40),
    ];
}

/**
 * Clave del mapa legado→HAY: preferir clave_legado (uso operativo); si no, id.
 */
function migracion_csv_map_key_grupo(string $idLeg, string $claveAnterior): string
{
    $claveAnterior = trim($claveAnterior);
    if ($claveAnterior !== '') {
        return $claveAnterior;
    }

    return migracion_csv_norm_id_legado($idLeg);
}

/**
 * Busca grupos HAY por clave_anterior (varios candidatos).
 *
 * @param list<string> $candidatos
 * @return list<array<string,mixed>>
 */
function migracion_csv_grupos_por_clave_anterior(PDO $pdo, ?int $idPlantel, array $candidatos): array
{
    $cands = [];
    foreach ($candidatos as $c) {
        $c = trim((string) $c);
        if ($c !== '' && !in_array($c, $cands, true)) {
            $cands[] = $c;
        }
    }
    if ($cands === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($cands), '?'));
    $sql = 'SELECT g.id_grupo, g.clave, g.id_plantel, e.clave AS esp_clave
            FROM grupos g
            LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
            WHERE g.clave_anterior IN (' . $ph . ')';
    $params = $cands;
    if ($idPlantel !== null && $idPlantel > 0) {
        $sql .= ' AND g.id_plantel = ?';
        $params[] = $idPlantel;
    }
    $sql .= ' ORDER BY g.id_grupo ASC';
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @param list<array<string,mixed>> $rows
 * @return array{0:?int,1:?int}
 */
function migracion_csv_pares_desde_filas_grupo(array $rows): array
{
    if ($rows === []) {
        return [null, null];
    }
    if (count($rows) === 1) {
        return [(int) $rows[0]['id_grupo'], null];
    }
    $idIng = null;
    $idComp = null;
    foreach ($rows as $row) {
        $esp = strtoupper(trim((string) ($row['esp_clave'] ?? '')));
        $cl = strtoupper(trim((string) ($row['clave'] ?? '')));
        if (str_starts_with($esp, 'COMP') || str_starts_with($cl, 'CK')) {
            $idComp = (int) $row['id_grupo'];
        } elseif (str_starts_with($esp, 'ING') || str_starts_with($cl, 'IK')) {
            $idIng = (int) $row['id_grupo'];
        }
    }
    if ($idIng && $idComp) {
        return [$idIng, $idComp];
    }

    return [(int) $rows[0]['id_grupo'], isset($rows[1]) ? (int) $rows[1]['id_grupo'] : null];
}

/**
 * Resuelve grupo(s) HAY para un alumno: primero por clave_legado, luego id, luego clave_anterior.
 * Si encuentra match, rellena alias id↔clave en el mapa.
 *
 * @return array{0:?int,1:?int} [id_grupo (inglés/adulto), id_grupo_comp (kids) o null]
 */
function migracion_csv_resolver_grupos_para_alumno(
    PDO $pdo,
    int $idPlantel,
    string $idGrupoLeg,
    string $claveLegado
): array {
    $idGrupoLeg = migracion_csv_norm_id_legado($idGrupoLeg);
    $claveLegado = trim($claveLegado);
    $keys = [];
    if ($claveLegado !== '') {
        foreach ([$claveLegado, strtoupper($claveLegado)] as $ck) {
            if (!in_array($ck, $keys, true)) {
                $keys[] = $ck;
            }
        }
    }
    if ($idGrupoLeg !== '') {
        foreach ([$idGrupoLeg, 'legado#' . $idGrupoLeg] as $ik) {
            if (!in_array($ik, $keys, true)) {
                $keys[] = $ik;
            }
        }
    }
    foreach ($keys as $k) {
        $id = migracion_csv_map_get($pdo, 'grupo', $k);
        if ($id !== null && $id > 0) {
            $idComp = migracion_csv_map_get($pdo, 'grupo_comp', $k);
            // Completar alias id↔clave para próximos imports
            migracion_csv_map_alias_grupo($pdo, $idGrupoLeg, $claveLegado, $id, $idComp, 'alias_resolver');

            return [$id, $idComp];
        }
    }

    $candidatos = [];
    if ($claveLegado !== '') {
        $candidatos[] = $claveLegado;
    }
    if ($idGrupoLeg !== '') {
        $candidatos[] = $idGrupoLeg;
        $candidatos[] = 'legado#' . $idGrupoLeg;
    }
    $rows = migracion_csv_grupos_por_clave_anterior($pdo, $idPlantel, $candidatos);
    if ($rows === [] && $idPlantel > 0) {
        // Último recurso: mismo clave_anterior en otro plantel (mapa/plantel desfasado)
        $rows = migracion_csv_grupos_por_clave_anterior($pdo, null, $candidatos);
    }
    [$idG, $idC] = migracion_csv_pares_desde_filas_grupo($rows);
    if ($idG !== null && $idG > 0) {
        migracion_csv_map_alias_grupo(
            $pdo,
            $idGrupoLeg,
            $claveLegado !== '' ? $claveLegado : ('legado#' . $idGrupoLeg),
            $idG,
            $idC,
            'alias_clave_anterior'
        );
    }

    return [$idG, $idC];
}

/**
 * @return array{ok:bool, message:string, creados?:int, vinculados?:int, errores?:list<string>}
 */
/**
 * Importa alumnos HAY (ficha + vínculo a grupo). Por defecto NO crea Google/Moodle/HAY login;
 * use Migración CSV → Re-provisionar mapeados cuando confirme que los datos están bien.
 *
 * @param list<array<string,mixed>> $rows
 * @return array{ok:bool,message:string,creados?:int,vinculados?:int,cuentas_ok?:int,cuentas_fail?:int,errores?:list<string>}
 */
function migracion_csv_importar_alumnos(
    PDO $pdo,
    array $rows,
    ?int $forzarPlantel = null,
    bool $provisionarCuentas = false
): array {
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    migracion_csv_ensure_schema($pdo);
    alumno_ensure_schema($pdo);
    if ($provisionarCuentas && function_exists('usuario_ensure_schema')) {
        usuario_ensure_schema($pdo);
    }
    $creados = 0;
    $vinculados = 0;
    $cuentasOk = 0;
    $cuentasFail = 0;
    $errores = [];
    /** @var array<string, true> $gruposFaltantes */
    $gruposFaltantes = [];

    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $nc = trim((string) ($r['numero_control'] ?? ''));
        $nombres = trim((string) ($r['nombres'] ?? ''));
        $apPat = trim((string) ($r['apellido_paterno'] ?? ''));
        $apMat = trim((string) ($r['apellido_materno'] ?? ''));
        $idGrupoLeg = migracion_csv_norm_id_legado((string) ($r['id_grupo_legado'] ?? ''));
        $claveGrupoLeg = trim((string) ($r['clave_grupo_legado'] ?? $r['clave_legado'] ?? ''));
        if ($nombres === '' && $nc === '') {
            continue;
        }
        $idPlantel = migracion_csv_resolver_plantel($pdo, (string) ($r['plantel_slug'] ?? ''), $forzarPlantel);
        if ($idPlantel === null) {
            $errores[] = "Línea {$line}: plantel no resuelto";
            continue;
        }
        [$idGrupo, $idGrupoComp] = migracion_csv_resolver_grupos_para_alumno(
            $pdo,
            $idPlantel,
            $idGrupoLeg,
            $claveGrupoLeg
        );
        $gc = [];
        if ($idGrupo === null || $idGrupo <= 0) {
            $ref = $claveGrupoLeg !== '' ? $claveGrupoLeg : $idGrupoLeg;
            $gruposFaltantes[$ref !== '' ? $ref : '(vacío)'] = true;
            $extra = $claveGrupoLeg !== '' && $idGrupoLeg !== ''
                ? " (clave={$claveGrupoLeg}, id={$idGrupoLeg})"
                : '';
            $errores[] = "Línea {$line}: grupo legado {$ref}{$extra} no está en el mapa HAY"
                . ' — importe 03_grupos con esa misma clave_legado (filtro por clave, no por id interno)';
            continue;
        }
        $gSt = $pdo->prepare('SELECT * FROM grupos WHERE id_grupo = ? LIMIT 1');
        $gSt->execute([$idGrupo]);
        $grupo = $gSt->fetch(PDO::FETCH_ASSOC);
        if (!$grupo) {
            $errores[] = "Línea {$line}: grupo HAY inexistente";
            continue;
        }
        $idEsp = (int) ($grupo['id_especialidad'] ?? 0);
        $fecha = migracion_csv_parse_fecha((string) ($r['fecha_inicio'] ?? ''));
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = substr((string) ($grupo['fecha_inicio'] ?? date('Y-m-d')), 0, 10);
        }
        $formaPago = strtolower(trim((string) ($r['forma_pago'] ?? 'mensual')));
        if (!in_array($formaPago, ['mensual', 'semanal'], true)) {
            $formaPago = 'mensual';
        }
        $idAsesor = null;
        $idAsesorLeg = trim((string) ($r['id_asesor_legado'] ?? ''));
        if ($idAsesorLeg !== '') {
            $idAsesor = migracion_csv_map_get($pdo, 'usuario', $idAsesorLeg);
        }

        $idAlumno = null;
        $idAlLeg = trim((string) ($r['id_alumno_legado'] ?? ''));
        if ($idAlLeg !== '') {
            $idAlumno = migracion_csv_map_get($pdo, 'alumno', $idAlLeg);
        }
        if (($idAlumno === null || $idAlumno <= 0) && $nc !== '') {
            $st = $pdo->prepare(
                'SELECT id_alumno FROM alumnos WHERE numero_control = ? AND id_plantel = ? LIMIT 1'
            );
            $st->execute([$nc, $idPlantel]);
            $ex = $st->fetchColumn();
            if ($ex !== false) {
                $idAlumno = (int) $ex;
            }
        }

        try {
            if ($idAlumno === null || $idAlumno <= 0) {
                if ($nc === '') {
                    $nc = function_exists('alumno_generar_numero_control')
                        ? alumno_generar_numero_control($pdo, $idPlantel)
                        : ('TMP' . time() . $line);
                }
                $pdo->prepare(
                    'INSERT INTO alumnos (
                        id_grupo, id_plantel, numero_control, nombre, apellido, nombres,
                        apellido_paterno, apellido_materno, estado, activo, forma_pago,
                        id_especialidad, email, telefono, fecha_alta, creado_en
                     ) VALUES (?,?,?,?,?,?,?,?,\'activo\',1,?,?,?,?,?,?)'
                )->execute([
                    $idGrupo,
                    $idPlantel,
                    $nc,
                    $nombres,
                    $apPat,
                    $nombres,
                    $apPat,
                    $apMat !== '' ? $apMat : null,
                    $formaPago,
                    $idEsp ?: null,
                    trim((string) ($r['email'] ?? '')) ?: null,
                    migracion_csv_norm_telefono((string) ($r['telefono'] ?? '')) ?: null,
                    $fecha,
                    $fecha . ' 09:00:00',
                ]);
                $idAlumno = (int) $pdo->lastInsertId();
                $creados++;
            } else {
                $pdo->prepare(
                    'UPDATE alumnos SET id_grupo=?, id_especialidad=?, estado=\'activo\', activo=1,
                     forma_pago=?, telefono=COALESCE(NULLIF(?, \'\'), telefono),
                     email=COALESCE(NULLIF(?, \'\'), email)
                     WHERE id_alumno=?'
                )->execute([
                    $idGrupo,
                    $idEsp ?: null,
                    $formaPago,
                    migracion_csv_norm_telefono((string) ($r['telefono'] ?? '')),
                    trim((string) ($r['email'] ?? '')),
                    $idAlumno,
                ]);
                $vinculados++;
            }
            // Mapa NC acotado por plantel (el NC se repite entre planteles)
            if ($nc !== '') {
                migracion_csv_map_set(
                    $pdo,
                    'alumno',
                    migracion_csv_map_key_nc($idPlantel, $nc),
                    $idAlumno,
                    $nc
                );
            }
            if ($idAlLeg !== '') {
                migracion_csv_map_set($pdo, 'alumno', $idAlLeg, $idAlumno, $nc !== '' ? $nc : null);
            }
            if ($idAsesor) {
                try {
                    $pdo->prepare('UPDATE alumnos SET id_asesor = ? WHERE id_alumno = ?')
                        ->execute([$idAsesor, $idAlumno]);
                } catch (Throwable $e) {
                    // columna puede no existir
                }
            }

            $idFase = (int) ($grupo['id_fase_actual'] ?? 0) ?: null;
            $pdo->prepare(
                'INSERT INTO alumno_grupos (id_alumno, id_grupo, activo, fecha_inicio, id_fase_entrada, creado_en)
                 VALUES (?, ?, 1, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    activo = 1,
                    fecha_inicio = VALUES(fecha_inicio),
                    id_fase_entrada = COALESCE(VALUES(id_fase_entrada), id_fase_entrada)'
            )->execute([$idAlumno, $idGrupo, $fecha, $idFase, $fecha . ' 09:00:00']);

            if ($idGrupoComp && $idGrupoComp > 0) {
                $g2 = $pdo->prepare('SELECT id_fase_actual, id_especialidad FROM grupos WHERE id_grupo = ?');
                $g2->execute([$idGrupoComp]);
                $gc = $g2->fetch(PDO::FETCH_ASSOC) ?: [];
                $pdo->prepare(
                    'INSERT INTO alumno_grupos (id_alumno, id_grupo, activo, fecha_inicio, id_fase_entrada, creado_en)
                     VALUES (?, ?, 1, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE activo = 1, fecha_inicio = VALUES(fecha_inicio)'
                )->execute([
                    $idAlumno,
                    $idGrupoComp,
                    $fecha,
                    (int) ($gc['id_fase_actual'] ?? 0) ?: null,
                    $fecha . ' 09:00:00',
                ]);
                $pdo->prepare('UPDATE alumnos SET inscripcion_kids_modo = \'dual\' WHERE id_alumno = ?')
                    ->execute([$idAlumno]);
            }

            // alumno_especialidades mínima sin congelar montos (usuario los define después)
            if ($idEsp > 0 && function_exists('pago_ensure_schema')) {
                pago_ensure_schema($pdo);
                $chk = $pdo->prepare(
                    'SELECT id_alumno_especialidad FROM alumno_especialidades
                     WHERE id_alumno = ? AND id_especialidad = ? AND activo = 1 LIMIT 1'
                );
                $chk->execute([$idAlumno, $idEsp]);
                if (!$chk->fetchColumn()) {
                    try {
                        $pdo->prepare(
                            'INSERT INTO alumno_especialidades
                             (id_alumno, id_especialidad, forma_pago, fecha_inscripcion, activo)
                             VALUES (?,?,?,?,1)'
                        )->execute([$idAlumno, $idEsp, $formaPago, $fecha]);
                    } catch (Throwable $e) {
                        // esquema puede variar
                    }
                }
            }
            if ($idGrupoComp > 0) {
                $idEspComp = (int) ($gc['id_especialidad'] ?? 0);
                if ($idEspComp > 0) {
                    try {
                        $chk = $pdo->prepare(
                            'SELECT id_alumno_especialidad FROM alumno_especialidades
                             WHERE id_alumno = ? AND id_especialidad = ? AND activo = 1 LIMIT 1'
                        );
                        $chk->execute([$idAlumno, $idEspComp]);
                        if (!$chk->fetchColumn()) {
                            $pdo->prepare(
                                'INSERT INTO alumno_especialidades
                                 (id_alumno, id_especialidad, forma_pago, fecha_inscripcion, activo)
                                 VALUES (?,?,?,?,1)'
                            )->execute([$idAlumno, $idEspComp, $formaPago, $fecha]);
                        }
                    } catch (Throwable $e) {
                        // ignore
                    }
                }
            }

            // Google / usuario HAY / Moodle: solo si se pidió explícitamente
            if ($provisionarCuentas && function_exists('usuario_crear_cuenta_alumno')) {
                try {
                    $cuenta = usuario_crear_cuenta_alumno($pdo, $idAlumno, $idPlantel);
                    if (!empty($cuenta['ok']) || !empty($cuenta['vinculado'])) {
                        $cuentasOk++;
                        if (isset($cuenta['moodle_ok']) && empty($cuenta['moodle_ok']) && !empty($cuenta['moodle'])) {
                            $errores[] = "Línea {$line} (NC {$nc}): HAY/Google OK; Moodle pendiente — "
                                . (string) $cuenta['moodle'];
                        }
                    } else {
                        $cuentasFail++;
                        $errores[] = "Línea {$line} (NC {$nc}): cuenta — "
                            . (string) ($cuenta['message'] ?? 'no se pudo crear Google/HAY/Moodle');
                    }
                } catch (Throwable $e) {
                    $cuentasFail++;
                    $errores[] = "Línea {$line} (NC {$nc}): cuenta — " . $e->getMessage();
                }
            }
        } catch (Throwable $e) {
            $errores[] = "Línea {$line}: " . $e->getMessage();
        }
    }

    $msg = "{$creados} creado(s), {$vinculados} vinculado(s)";
    if (!$provisionarCuentas) {
        $msg .= '; sin cuentas Google/Moodle (use «Re-provisionar mapeados» cuando confirme los datos)';
    } elseif ($cuentasOk + $cuentasFail > 0) {
        $msg .= "; cuentas OK {$cuentasOk}" . ($cuentasFail > 0 ? " / fallos {$cuentasFail}" : '');
    }
    if ($gruposFaltantes !== []) {
        $faltan = array_keys($gruposFaltantes);
        sort($faltan, SORT_NATURAL);
        $msg .= '. Claves/grupos legado sin mapa: ' . implode(', ', array_slice($faltan, 0, 20))
            . (count($faltan) > 20 ? '…' : '');
        $muestraMapa = migracion_csv_map_keys_muestra($pdo, 'grupo', 15);
        if ($muestraMapa !== []) {
            $errores[] = 'Claves de grupo YA mapeadas (muestra): ' . implode(', ', $muestraMapa)
                . '. Si falta alguna, descargue e importe de nuevo 03_grupos filtrando por esas claves.';
        } else {
            $errores[] = 'No hay ningún grupo en el mapa. Importe 03_grupos (por clave_legado) antes que alumnos.';
        }
    }
    migracion_csv_log($pdo, 'alumnos', 'info', 'Alumnos: ' . $msg);

    return [
        'ok' => ($creados + $vinculados) > 0 || $errores === [],
        'message' => $msg,
        'creados' => $creados,
        'vinculados' => $vinculados,
        'cuentas_ok' => $cuentasOk,
        'cuentas_fail' => $cuentasFail,
        'grupos_faltantes' => array_keys($gruposFaltantes),
        'errores' => array_slice($errores, 0, 40),
    ];
}

/**
 * Clave de mapa NC acotada por plantel (el número de control no es único global).
 */
function migracion_csv_map_key_nc(int $idPlantel, string $nc): string
{
    return 'p' . $idPlantel . ':nc:' . trim($nc);
}

/**
 * Resuelve alumno HAY por NC dentro de un plantel (nunca cruza planteles).
 */
function migracion_csv_resolver_alumno_por_nc(PDO $pdo, string $nc, int $idPlantel): ?int
{
    $nc = trim($nc);
    if ($nc === '' || $idPlantel <= 0) {
        return null;
    }
    $mapped = migracion_csv_map_get($pdo, 'alumno', migracion_csv_map_key_nc($idPlantel, $nc));
    if ($mapped !== null && $mapped > 0) {
        $st = $pdo->prepare('SELECT id_alumno FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1');
        $st->execute([$mapped, $idPlantel]);
        if ($st->fetchColumn() !== false) {
            return $mapped;
        }
    }
    $st = $pdo->prepare(
        'SELECT id_alumno FROM alumnos WHERE numero_control = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$nc, $idPlantel]);
    $ex = $st->fetchColumn();

    return $ex === false ? null : (int) $ex;
}

/**
 * Resuelve el usuario HAY que recibió el pago (mapa personal/usuario legado).
 */
function migracion_csv_resolver_recibio_pago(PDO $pdo, array $r): ?int
{
    $idRec = trim((string) ($r['id_recibio_legado'] ?? $r['id_usuario_legado'] ?? ''));
    if ($idRec === '' || $idRec === '0') {
        return null;
    }
    $mapped = migracion_csv_map_get($pdo, 'usuario', $idRec);
    if ($mapped !== null && $mapped > 0) {
        return $mapped;
    }

    return null;
}

/**
 * Id de sucursal legado que corresponde al plantel HAY (slug/nombre o mapa).
 */
function migracion_csv_resolver_sucursal_legado(PDO $pdoHay, PDO $pdoLeg, int $idPlantel): ?int
{
    if ($idPlantel <= 0) {
        return null;
    }
    migracion_csv_ensure_schema($pdoHay);
    $st = $pdoHay->prepare(
        'SELECT id_legacy FROM hay_migracion_csv_map WHERE entidad = \'plantel\' AND id_hay = ? LIMIT 1'
    );
    $st->execute([$idPlantel]);
    $leg = $st->fetchColumn();
    if ($leg !== false && ctype_digit((string) $leg) && (int) $leg > 0) {
        return (int) $leg;
    }

    $st = $pdoHay->prepare('SELECT slug, nombre FROM planteles WHERE id_plantel = ? LIMIT 1');
    $st->execute([$idPlantel]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        return null;
    }
    $slug = strtolower(trim((string) ($p['slug'] ?? '')));
    $nombre = trim((string) ($p['nombre'] ?? ''));
    if (!function_exists('migcsv_table_exists') || !migcsv_table_exists($pdoLeg, 'sucursales')) {
        return null;
    }
    try {
        $rows = $pdoLeg->query('SELECT id, nombre FROM sucursales')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return null;
    }
    foreach ($rows as $s) {
        $sid = (int) ($s['id'] ?? 0);
        $sn = trim((string) ($s['nombre'] ?? ''));
        if ($sid <= 0) {
            continue;
        }
        $slugSuc = function_exists('migcsv_slug_sucursal')
            ? migcsv_slug_sucursal($sn)
            : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $sn) ?? '');
        if ($slug !== '' && $slugSuc === $slug) {
            return $sid;
        }
        if ($nombre !== '' && strcasecmp($sn, $nombre) === 0) {
            return $sid;
        }
    }

    return null;
}

/**
 * @return array{ok:bool, message:string, creados?:int, omitidos?:int, actualizados?:int, errores?:list<string>}
 */
function migracion_csv_importar_pagos(PDO $pdo, array $rows, ?int $forzarPlantel = null): array
{
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    migracion_csv_ensure_schema($pdo);
    pago_ensure_schema($pdo);
    $creados = 0;
    $omitidos = 0;
    $actualizados = 0;
    $errores = [];
    $idPlantelSesion = $forzarPlantel ?: (function_exists('plantel_id_activo') ? plantel_id_activo() : 0);
    if ($idPlantelSesion <= 0) {
        return [
            'ok' => false,
            'message' => 'Seleccione el plantel activo: el número de control no es único entre planteles',
            'creados' => 0,
            'omitidos' => 0,
            'actualizados' => 0,
            'errores' => ['Falta id_plantel para importar pagos'],
        ];
    }

    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $idLeg = trim((string) ($r['id_pago_legado'] ?? ''));
        $idRecibioHay = migracion_csv_resolver_recibio_pago($pdo, $r);
        if ($idLeg !== '') {
            $idPagoExistente = migracion_csv_map_get($pdo, 'pago', $idLeg);
            if ($idPagoExistente !== null && $idPagoExistente > 0) {
                // Reimport: corregir «Recibió» si ahora resolvemos el cobrador del legado
                if ($idRecibioHay !== null && $idRecibioHay > 0) {
                    try {
                        $stCur = $pdo->prepare(
                            'SELECT id_usuario, id_plantel FROM alumno_pagos WHERE id_pago = ? LIMIT 1'
                        );
                        $stCur->execute([$idPagoExistente]);
                        $curRow = $stCur->fetch(PDO::FETCH_ASSOC) ?: [];
                        $curPlantel = (int) ($curRow['id_plantel'] ?? 0);
                        if ($curPlantel === $idPlantelSesion) {
                            $cur = (int) ($curRow['id_usuario'] ?? 0);
                            if ($cur !== $idRecibioHay) {
                                $pdo->prepare('UPDATE alumno_pagos SET id_usuario = ? WHERE id_pago = ?')
                                    ->execute([$idRecibioHay, $idPagoExistente]);
                                $actualizados++;
                            }
                        }
                    } catch (Throwable $e) {
                        // ignore
                    }
                }
                $omitidos++;
                continue;
            }
        }
        $nc = trim((string) ($r['numero_control'] ?? ''));
        $idAlLeg = trim((string) ($r['id_alumno_legado'] ?? ''));
        $idAlumno = null;
        // NC solo dentro del plantel activo (no usar mapa global nc:…)
        if ($nc !== '') {
            $idAlumno = migracion_csv_resolver_alumno_por_nc($pdo, $nc, $idPlantelSesion);
        }
        if (($idAlumno === null || $idAlumno <= 0) && $idAlLeg !== '') {
            $mapped = migracion_csv_map_get($pdo, 'alumno', $idAlLeg);
            if ($mapped !== null && $mapped > 0) {
                $st = $pdo->prepare(
                    'SELECT id_alumno FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1'
                );
                $st->execute([$mapped, $idPlantelSesion]);
                if ($st->fetchColumn() !== false) {
                    $idAlumno = $mapped;
                }
            }
        }
        if ($idAlumno === null || $idAlumno <= 0) {
            $errores[] = "Línea {$line}: alumno no encontrado en este plantel"
                . ($nc !== '' ? " (NC {$nc})" : ($idAlLeg !== '' ? " (id legado {$idAlLeg})" : ''));
            continue;
        }
        $monto = (float) str_replace([',', ' '], ['', ''], (string) ($r['monto'] ?? '0'));
        if ($monto <= 0) {
            continue;
        }
        $tipo = strtolower(trim((string) ($r['tipo'] ?? 'abono')));
        if (!in_array($tipo, ['inscripcion', 'mensualidad', 'semanal', 'abono', 'producto', 'otro'], true)) {
            $tipo = 'abono';
        }
        $fecha = trim((string) ($r['fecha'] ?? ''));
        if ($fecha === '') {
            $fecha = date('Y-m-d H:i:s');
        } elseif (strlen($fecha) === 10) {
            $fecha .= ' 12:00:00';
        }
        $folio = trim((string) ($r['folio'] ?? ''));
        if ($folio === '' && $idLeg !== '') {
            $folio = 'MIG-' . $idLeg;
        }
        $forma = trim((string) ($r['forma_pago'] ?? 'Efectivo')) ?: 'Efectivo';
        $concepto = trim((string) ($r['concepto'] ?? 'Pago migración')) ?: 'Pago migración';

        $stAl = $pdo->prepare('SELECT id_plantel, id_especialidad FROM alumnos WHERE id_alumno = ?');
        $stAl->execute([$idAlumno]);
        $al = $stAl->fetch(PDO::FETCH_ASSOC) ?: [];
        $idPlantelAl = (int) ($al['id_plantel'] ?? 0);
        if ($idPlantelAl !== $idPlantelSesion) {
            $errores[] = "Línea {$line}: NC {$nc} pertenece a otro plantel; omitido";
            continue;
        }
        $idPlantel = $idPlantelSesion;

        // Cobrador del legado (id_recibio → mapa usuario). Nunca forzar al operador actual.
        $idUsuarioPago = $idRecibioHay;

        try {
            $pdo->prepare(
                'INSERT INTO alumno_pagos (
                    id_alumno, id_plantel, id_especialidad, tipo, folio, monto, forma_pago,
                    concepto, id_usuario, creado_en
                 ) VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $idAlumno,
                $idPlantel ?: null,
                (int) ($al['id_especialidad'] ?? 0) ?: null,
                $tipo,
                $folio !== '' ? $folio : null,
                $monto,
                $forma,
                $concepto,
                $idUsuarioPago,
                $fecha,
            ]);
            $idPago = (int) $pdo->lastInsertId();
            if ($idLeg !== '') {
                migracion_csv_map_set($pdo, 'pago', $idLeg, $idPago);
            }
            $creados++;
        } catch (Throwable $e) {
            $errores[] = "Línea {$line}: " . $e->getMessage();
        }
    }

    $msg = "{$creados} pago(s) creado(s), {$omitidos} omitido(s)";
    if ($actualizados > 0) {
        $msg .= ", {$actualizados} cobrador(es) actualizado(s) desde legado";
    }
    migracion_csv_log($pdo, 'pagos', 'info', 'Pagos: ' . $msg);

    return [
        'ok' => $creados > 0 || $omitidos > 0 || $actualizados > 0 || $errores === [],
        'message' => $msg,
        'creados' => $creados,
        'omitidos' => $omitidos,
        'actualizados' => $actualizados,
        'errores' => array_slice($errores, 0, 40),
    ];
}

/**
 * Parsea lista de numero_control (líneas, CSV suelto o columna).
 *
 * @return list<string>
 */
function migracion_csv_parse_filtro_numeros_control(string $raw): array
{
    $out = [];
    $seen = [];
    foreach (preg_split('/\R/', $raw) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $low = strtolower($line);
        if ($low === 'numero_control' || $low === 'id_alumno_legado'
            || str_starts_with($low, 'numero_control,') || str_starts_with($low, 'id_alumno_legado,')) {
            continue;
        }
        if (str_contains($line, ',')) {
            $parts = str_getcsv($line);
            $first = trim((string) ($parts[0] ?? ''));
            if ($first === '' || strcasecmp($first, 'numero_control') === 0
                || strcasecmp($first, 'id_alumno_legado') === 0) {
                continue;
            }
            $line = $first;
        }
        if ($line === '' || isset($seen[$line])) {
            continue;
        }
        $seen[$line] = true;
        $out[] = $line;
    }

    return $out;
}

/**
 * NCs de alumnos ya en el mapa HAY (opcionalmente filtrados por plantel).
 *
 * @return list<string>
 */
function migracion_csv_ncs_desde_mapa_alumnos(PDO $pdo, ?int $idPlantel = null): array
{
    migracion_csv_ensure_schema($pdo);
    if ($idPlantel !== null && $idPlantel > 0) {
        $st = $pdo->prepare(
            'SELECT DISTINCT a.numero_control
             FROM hay_migracion_csv_map m
             INNER JOIN alumnos a ON a.id_alumno = m.id_hay
             WHERE m.entidad = \'alumno\' AND a.numero_control IS NOT NULL AND a.numero_control != \'\'
               AND a.id_plantel = ?
             ORDER BY a.numero_control'
        );
        $st->execute([$idPlantel]);
    } else {
        $st = $pdo->query(
            'SELECT DISTINCT a.numero_control
             FROM hay_migracion_csv_map m
             INNER JOIN alumnos a ON a.id_alumno = m.id_hay
             WHERE m.entidad = \'alumno\' AND a.numero_control IS NOT NULL AND a.numero_control != \'\'
             ORDER BY a.numero_control'
        );
    }
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $nc) {
        $nc = trim((string) $nc);
        if ($nc !== '') {
            $out[] = $nc;
        }
    }

    return array_values(array_unique($out));
}

/**
 * Trae pagos del legado e importa a HAY por lotes de numero_control
 * (sin descargar/subir CSV de pagos).
 *
 * @param list<string> $numerosControl
 * @return array{
 *   ok:bool, message:string, done?:bool, offset?:int, next_offset?:int,
 *   total_nc?:int, lote_nc?:int, nc_procesados?:int, nc_sin_legado?:list<string>,
 *   pagos_leidos?:int, creados?:int, omitidos?:int, errores?:list<string>
 * }
 */
function migracion_csv_importar_pagos_desde_legado(
    PDO $pdoHay,
    array $numerosControl,
    ?int $idPlantel = null,
    int $offset = 0,
    int $loteNc = 40,
    ?int $idSucursalLegado = null
): array {
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    $ncs = [];
    $seen = [];
    foreach ($numerosControl as $nc) {
        $nc = trim((string) $nc);
        if ($nc === '' || isset($seen[$nc])) {
            continue;
        }
        $seen[$nc] = true;
        $ncs[] = $nc;
    }
    if ($ncs === []) {
        return ['ok' => false, 'message' => 'Indique numero_control (pegue la lista o use el mapa de alumnos)'];
    }
    if ($idPlantel === null || $idPlantel <= 0) {
        return [
            'ok' => false,
            'message' => 'Seleccione el plantel HAY de destino: el número de control se repite entre planteles '
                . '(p. ej. 100 en Guerrero ≠ 100 en Celaya)',
        ];
    }

    $lib = dirname(__DIR__) . '/tools/migracion_csv/lib.php';
    if (!is_readable($lib)) {
        return ['ok' => false, 'message' => 'Falta tools/migracion_csv/lib.php'];
    }
    require_once $lib;
    if (!function_exists('legacy_import_legacy_connection')) {
        return ['ok' => false, 'message' => 'legacy_import no disponible'];
    }
    $conn = legacy_import_legacy_connection();
    if (empty($conn['ok']) || !($conn['pdo'] instanceof PDO)) {
        return [
            'ok' => false,
            'message' => 'Sin conexión al legado. Configure LEGACY_DB_* en config.local.php. '
                . (string) ($conn['error'] ?? ''),
        ];
    }
    /** @var PDO $pdoLeg */
    $pdoLeg = $conn['pdo'];

    // Preferir sucursal elegida en el desplegable (nombres HAY≠legado); fallback por slug
    $idSucursal = ($idSucursalLegado !== null && $idSucursalLegado > 0)
        ? $idSucursalLegado
        : migracion_csv_resolver_sucursal_legado($pdoHay, $pdoLeg, $idPlantel);
    if ($idSucursal === null || $idSucursal <= 0) {
        return [
            'ok' => false,
            'message' => 'Elija la sucursal legado en el desplegable (los nombres no tienen que coincidir '
                . 'con el plantel HAY).',
        ];
    }

    $totalNc = count($ncs);
    $loteNc = max(5, min(100, $loteNc));
    $offset = max(0, $offset);
    if ($offset >= $totalNc) {
        return [
            'ok' => true,
            'done' => true,
            'message' => 'Nada pendiente',
            'offset' => $offset,
            'next_offset' => $offset,
            'total_nc' => $totalNc,
            'lote_nc' => 0,
            'nc_procesados' => 0,
            'pagos_leidos' => 0,
            'creados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];
    }

    $lote = array_slice($ncs, $offset, $loteNc);
    // Solo alumnos de la sucursal del plantel activo
    $idsLeg = migcsv_resolver_ids_alumnos($pdoLeg, $lote, true, $idSucursal);
    $ncSinLegado = [];
    if (count($idsLeg) < count($lote)) {
        // Detectar cuáles no resolvieron (consulta inversa cara; estimar por diferencia)
        $found = [];
        if ($idsLeg !== []) {
            $ph = implode(',', array_fill(0, count($idsLeg), '?'));
            $hasNuevo = migcsv_column_exists($pdoLeg, 'alumnos', 'nuevo_numero_control');
            $sql = $hasNuevo
                ? "SELECT CAST(COALESCE(NULLIF(nuevo_numero_control, 0), numero_control) AS CHAR) AS nc
                   FROM alumnos WHERE id IN ($ph)"
                : "SELECT CAST(numero_control AS CHAR) AS nc FROM alumnos WHERE id IN ($ph)";
            try {
                $st = $pdoLeg->prepare($sql);
                $st->execute($idsLeg);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $ncFound) {
                    $found[trim((string) $ncFound)] = true;
                }
            } catch (Throwable $e) {
                // ignore
            }
        }
        foreach ($lote as $nc) {
            if (!isset($found[$nc])) {
                $ncSinLegado[] = $nc;
            }
        }
    }

    $rows = $idsLeg !== [] ? migcsv_export_pagos_por_alumnos($pdoLeg, $idsLeg) : [];
    $import = migracion_csv_importar_pagos($pdoHay, $rows, $idPlantel);
    $next = $offset + count($lote);
    $done = $next >= $totalNc;
    $creados = (int) ($import['creados'] ?? 0);
    $omitidos = (int) ($import['omitidos'] ?? 0);
    $actualizados = (int) ($import['actualizados'] ?? 0);
    $errores = $import['errores'] ?? [];
    if ($ncSinLegado !== []) {
        $errores[] = 'NC sin match en legado (lote): ' . implode(', ', array_slice($ncSinLegado, 0, 15))
            . (count($ncSinLegado) > 15 ? '…' : '');
    }

    $msg = 'Plantel #' . $idPlantel . ' / sucursal legado #' . $idSucursal
        . ' · lote NC ' . ($offset + 1) . '–' . $next . ' / ' . $totalNc
        . ': ' . count($rows) . ' pago(s) leído(s) → '
        . $creados . ' creado(s), ' . $omitidos . ' omitido(s)';
    if ($actualizados > 0) {
        $msg .= ', ' . $actualizados . ' cobrador(es) corregido(s)';
    }
    if ($ncSinLegado !== []) {
        $msg .= '; ' . count($ncSinLegado) . ' NC sin legado en esta sucursal';
    }
    if ($done) {
        $msg .= '. Completado.';
    }

    migracion_csv_log($pdoHay, 'pagos', 'info', $msg);

    return [
        'ok' => !empty($import['ok']) || $creados > 0 || $omitidos > 0 || $actualizados > 0 || $rows === [],
        'done' => $done,
        'message' => $msg,
        'offset' => $offset,
        'next_offset' => $next,
        'total_nc' => $totalNc,
        'lote_nc' => count($lote),
        'nc_procesados' => count($lote),
        'nc_sin_legado' => array_slice($ncSinLegado, 0, 40),
        'pagos_leidos' => count($rows),
        'creados' => $creados,
        'omitidos' => $omitidos,
        'actualizados' => $actualizados,
        'errores' => array_slice($errores, 0, 40),
    ];
}

/**
 * Borra pagos de Migración CSV de un plantel (mapa + filas alumno_pagos).
 *
 * @return array{ok:bool, message:string, pagos?:int, mapas?:int}
 */
function migracion_csv_purge_pagos_plantel(PDO $pdo, int $idPlantel, bool $dryRun = false): array
{
    if ($idPlantel <= 0) {
        return ['ok' => false, 'message' => 'Plantel inválido'];
    }
    migracion_csv_ensure_schema($pdo);
    if (function_exists('pago_ensure_schema')) {
        pago_ensure_schema($pdo);
    }

    $st = $pdo->prepare(
        'SELECT m.id AS id_map, m.id_hay AS id_pago, m.id_legacy
         FROM hay_migracion_csv_map m
         INNER JOIN alumno_pagos p ON p.id_pago = m.id_hay
         WHERE m.entidad = \'pago\' AND p.id_plantel = ?'
    );
    $st->execute([$idPlantel]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $idsPago = [];
    foreach ($rows as $r) {
        $id = (int) ($r['id_pago'] ?? 0);
        if ($id > 0) {
            $idsPago[$id] = true;
        }
    }
    $idsPago = array_keys($idsPago);
    $nMap = count($rows);
    $nPagos = count($idsPago);

    if ($nPagos === 0) {
        return [
            'ok' => true,
            'message' => 'No hay pagos de migración CSV en este plantel',
            'pagos' => 0,
            'mapas' => 0,
        ];
    }

    if ($dryRun) {
        return [
            'ok' => true,
            'message' => "Se borrarían {$nPagos} pago(s) y {$nMap} clave(s) de mapa",
            'pagos' => $nPagos,
            'mapas' => $nMap,
        ];
    }

    $pdo->beginTransaction();
    try {
        $ph = implode(',', array_fill(0, count($idsPago), '?'));
        $pdo->prepare("DELETE FROM alumno_pagos WHERE id_pago IN ($ph)")->execute($idsPago);
        $pdo->prepare(
            "DELETE FROM hay_migracion_csv_map WHERE entidad = 'pago' AND id_hay IN ($ph)"
        )->execute($idsPago);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => 'Error al borrar: ' . $e->getMessage()];
    }

    migracion_csv_log(
        $pdo,
        'pagos',
        'info',
        "Purge pagos plantel #{$idPlantel}: {$nPagos} pago(s), {$nMap} mapa(s)"
    );

    return [
        'ok' => true,
        'message' => "Borrados {$nPagos} pago(s) migrados y {$nMap} clave(s) de mapa del plantel",
        'pagos' => $nPagos,
        'mapas' => $nMap,
    ];
}

/**
 * Tablas hijas típicas de grupos (se ignoran si no existen).
 *
 * @return list<string>
 */
function migracion_csv_tablas_hijas_grupo(): array
{
    return [
        'grupo_horarios',
        'alumno_grupos',
        'grupo_docente',
        'grupo_suplencia',
        'grupo_preinicio_contacto',
        'grupo_fusion_log',
        'grupo_apertura_log',
        'grupo_plan_periodo',
        'asistencias',
        'planeaciones',
        'planeacion_semanal',
        'evaluaciones',
        'calificaciones',
        'tutor_grupo',
        'tutor_asignaciones',
    ];
}

/**
 * Estado de apertura al importar: pasado → iniciado; futuro → programado
 * (evita "pendiente de autorización" por fechas inventadas = hoy).
 *
 * @param list<int> $idsGrupo
 */
function migracion_csv_grupo_set_estado_por_fecha(PDO $pdo, array $idsGrupo, string $fechaYmd): void
{
    $ids = [];
    foreach ($idsGrupo as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = true;
        }
    }
    $ids = array_keys($ids);
    if ($ids === [] || $fechaYmd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaYmd)) {
        return;
    }
    if (function_exists('grupo_apertura_ensure_schema')) {
        try {
            grupo_apertura_ensure_schema($pdo);
        } catch (Throwable $e) {
            return;
        }
    }
    $hoy = date('Y-m-d');
    $estado = ($fechaYmd <= $hoy) ? 'iniciado' : 'programado';
    $ph = implode(',', array_fill(0, count($ids), '?'));
    try {
        $pdo->prepare("UPDATE grupos SET estado_apertura = ? WHERE id_grupo IN ($ph)")
            ->execute([$estado, ...$ids]);
    } catch (Throwable $e) {
        // columna puede no existir aún
    }
}

/**
 * Borra grupos creados por Migración CSV (mapa grupo/grupo_comp), limpia vínculos
 * y reconstruye el contador de claves del plantel para reintentar el import.
 *
 * @return array{
 *   ok:bool, message:string, grupos_borrados?:int, mapas_borrados?:int,
 *   alumnos_desvinculados?:int, planteles?:list<int>, errores?:list<string>
 * }
 */
function migracion_csv_revertir_grupos(
    PDO $pdo,
    ?int $idPlantel = null,
    bool $borrarAlumnosMapeados = false,
    bool $todosLosDelPlantel = false
): array {
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    $idPlantel = $idPlantel && $idPlantel > 0
        ? $idPlantel
        : (function_exists('plantel_id_activo') ? (int) plantel_id_activo() : 0);
    if ($idPlantel <= 0) {
        return ['ok' => false, 'message' => 'Seleccione un plantel activo'];
    }

    return migracion_csv_purgar_grupos_plantel($pdo, $idPlantel, $todosLosDelPlantel, $borrarAlumnosMapeados);
}

/**
 * Purga grupos de un plantel (CLI / revertir UI).
 * - $todosLosDelPlantel=false: solo los del mapa migración CSV
 * - $todosLosDelPlantel=true: TODOS los grupos del plantel (reintento limpio)
 *
 * @return array{
 *   ok:bool, message:string, grupos_borrados?:int, mapas_borrados?:int,
 *   alumnos_desvinculados?:int, planteles?:list<int>, errores?:list<string>
 * }
 */
function migracion_csv_purgar_grupos_plantel(
    PDO $pdo,
    int $idPlantel,
    bool $todosLosDelPlantel = false,
    bool $borrarAlumnosMapeados = false
): array {
    if ($idPlantel <= 0) {
        return ['ok' => false, 'message' => 'id_plantel inválido'];
    }
    migracion_csv_ensure_schema($pdo);
    if (function_exists('grupo_clave_ensure_schema')) {
        grupo_clave_ensure_schema($pdo);
    }

    $errores = [];
    $ids = [];
    try {
        if ($todosLosDelPlantel) {
            $st = $pdo->prepare('SELECT id_grupo FROM grupos WHERE id_plantel = ?');
            $st->execute([$idPlantel]);
        } else {
            // Mapa + grupos con clave_anterior (sello del import CSV). Un import con
            // error de TX puede dejar grupos sin fila en el mapa.
            $tieneClaveAnt = function_exists('plantel_column_exists')
                ? plantel_column_exists($pdo, 'grupos', 'clave_anterior')
                : true;
            if ($tieneClaveAnt) {
                $st = $pdo->prepare(
                    "SELECT g.id_grupo
                     FROM grupos g
                     WHERE g.id_plantel = ?
                       AND (
                         EXISTS (
                           SELECT 1 FROM hay_migracion_csv_map m
                           WHERE m.id_hay = g.id_grupo AND m.entidad IN ('grupo','grupo_comp')
                         )
                         OR (g.clave_anterior IS NOT NULL AND TRIM(g.clave_anterior) <> '')
                       )"
                );
            } else {
                $st = $pdo->prepare(
                    "SELECT DISTINCT m.id_hay
                     FROM hay_migracion_csv_map m
                     INNER JOIN grupos g ON g.id_grupo = m.id_hay
                     WHERE m.entidad IN ('grupo', 'grupo_comp')
                       AND g.id_plantel = ?"
                );
            }
            $st->execute([$idPlantel]);
        }
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'No se pudo listar grupos: ' . $e->getMessage()];
    }

    $idList = array_keys($ids);
    if ($idList === []) {
        $mapasHuerfanos = 0;
        try {
            $mapasHuerfanos = (int) $pdo->exec(
                "DELETE m FROM hay_migracion_csv_map m
                 LEFT JOIN grupos g ON g.id_grupo = m.id_hay
                 WHERE m.entidad IN ('grupo','grupo_comp') AND g.id_grupo IS NULL"
            );
        } catch (Throwable $e) {
            // ignore
        }
        $nPlantel = 0;
        try {
            $stN = $pdo->prepare('SELECT COUNT(*) FROM grupos WHERE id_plantel = ?');
            $stN->execute([$idPlantel]);
            $nPlantel = (int) $stN->fetchColumn();
        } catch (Throwable $e) {
            $nPlantel = 0;
        }
        if (function_exists('grupo_clave_reconstruir_secuencias')) {
            grupo_clave_reconstruir_secuencias($pdo, $idPlantel);
        }
        $ambito = $todosLosDelPlantel ? 'todos los del plantel' : 'migrados';
        migracion_csv_log(
            $pdo,
            'grupos',
            'info',
            "Purga grupos ({$ambito}): nada que borrar; secuencias reconstruidas plantel #{$idPlantel}"
        );

        $msg = 'No había grupos'
            . ($todosLosDelPlantel ? ' en este plantel' : ' en el mapa/clave_anterior de este plantel')
            . '. Contador de claves reiniciado/reconstruido'
            . ($mapasHuerfanos > 0 ? " ({$mapasHuerfanos} mapa(s) limpiados)" : '') . '.';
        if (!$todosLosDelPlantel && $nPlantel > 0) {
            $msg .= " Quedan {$nPlantel} grupo(s) en el plantel sin mapa (p. ej. import a medias)."
                . ' Use «Revertir» otra vez y escriba TODOS para vaciar el plantel.';
        }

        return [
            'ok' => true,
            'message' => $msg,
            'grupos_borrados' => 0,
            'mapas_borrados' => $mapasHuerfanos,
            'alumnos_desvinculados' => 0,
            'grupos_restantes_plantel' => $nPlantel,
            'planteles' => [$idPlantel],
            'errores' => [],
        ];
    }

    $ph = implode(',', array_fill(0, count($idList), '?'));
    $alumnosDesvinculados = 0;
    $gruposBorrados = 0;
    $mapasBorrados = 0;

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }
    try {
        if (function_exists('plantel_table_exists') ? plantel_table_exists($pdo, 'alumno_grupos') : true) {
            try {
                $stAg = $pdo->prepare("DELETE FROM alumno_grupos WHERE id_grupo IN ($ph)");
                $stAg->execute($idList);
                $alumnosDesvinculados += $stAg->rowCount();
            } catch (Throwable $e) {
                $errores[] = 'alumno_grupos: ' . $e->getMessage();
            }
        }
        try {
            $stA = $pdo->prepare('UPDATE alumnos SET id_grupo = NULL WHERE id_grupo IN (' . $ph . ')');
            $stA->execute($idList);
        } catch (Throwable $e) {
            // columna puede no existir / NOT NULL
        }

        foreach (migracion_csv_tablas_hijas_grupo() as $tabla) {
            if ($tabla === 'alumno_grupos') {
                continue;
            }
            if (function_exists('plantel_table_exists') && !plantel_table_exists($pdo, $tabla)) {
                continue;
            }
            try {
                $pdo->prepare("DELETE FROM {$tabla} WHERE id_grupo IN ($ph)")->execute($idList);
            } catch (Throwable $e) {
                // tabla sin columna id_grupo u otra causa: no bloquear
            }
        }

        if ($borrarAlumnosMapeados) {
            try {
                $stAl = $pdo->prepare(
                    "SELECT DISTINCT m.id_hay
                     FROM hay_migracion_csv_map m
                     INNER JOIN alumnos a ON a.id_alumno = m.id_hay
                     WHERE m.entidad = 'alumno' AND a.id_plantel = ?"
                );
                $stAl->execute([$idPlantel]);
                foreach ($stAl->fetchAll(PDO::FETCH_COLUMN) ?: [] as $aid) {
                    $aid = (int) $aid;
                    if ($aid <= 0) {
                        continue;
                    }
                    try {
                        if (function_exists('plantel_table_exists') && plantel_table_exists($pdo, 'pagos')) {
                            $pagoIds = [];
                            try {
                                $stP = $pdo->prepare('SELECT id_pago FROM pagos WHERE id_alumno = ?');
                                $stP->execute([$aid]);
                                foreach ($stP->fetchAll(PDO::FETCH_COLUMN) ?: [] as $pid) {
                                    $pid = (int) $pid;
                                    if ($pid > 0) {
                                        $pagoIds[] = $pid;
                                    }
                                }
                            } catch (Throwable $e) {
                                $pagoIds = [];
                            }
                            $pdo->prepare('DELETE FROM pagos WHERE id_alumno = ?')->execute([$aid]);
                            if ($pagoIds !== []) {
                                $phP = implode(',', array_fill(0, count($pagoIds), '?'));
                                $pdo->prepare(
                                    "DELETE FROM hay_migracion_csv_map WHERE entidad = 'pago' AND id_hay IN ($phP)"
                                )->execute($pagoIds);
                            }
                        }
                        $pdo->prepare("DELETE FROM hay_migracion_csv_map WHERE entidad = 'alumno' AND id_hay = ?")
                            ->execute([$aid]);
                        $pdo->prepare('DELETE FROM alumno_grupos WHERE id_alumno = ?')->execute([$aid]);
                        $pdo->prepare('DELETE FROM alumnos WHERE id_alumno = ? AND id_plantel = ?')
                            ->execute([$aid, $idPlantel]);
                    } catch (Throwable $e) {
                        $errores[] = 'alumno #' . $aid . ': ' . $e->getMessage();
                    }
                }
            } catch (Throwable $e) {
                $errores[] = 'limpieza alumnos: ' . $e->getMessage();
            }
        }

        $stDel = $pdo->prepare("DELETE FROM grupos WHERE id_grupo IN ($ph) AND id_plantel = ?");
        $stDel->execute([...$idList, $idPlantel]);
        $gruposBorrados = $stDel->rowCount();

        $stMap = $pdo->prepare(
            "DELETE FROM hay_migracion_csv_map
             WHERE entidad IN ('grupo','grupo_comp') AND id_hay IN ($ph)"
        );
        $stMap->execute($idList);
        $mapasBorrados = $stMap->rowCount();

        if ($todosLosDelPlantel) {
            try {
                // Mapas huérfanos de este plantel (ids ya borrados)
                $mapasBorrados += (int) $pdo->exec(
                    "DELETE m FROM hay_migracion_csv_map m
                     LEFT JOIN grupos g ON g.id_grupo = m.id_hay
                     WHERE m.entidad IN ('grupo','grupo_comp') AND g.id_grupo IS NULL"
                );
            } catch (Throwable $e) {
                // ignore
            }
        }

        if ($ownTx && $pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'ok' => false,
            'message' => 'Error al purgar grupos: ' . $e->getMessage(),
            'errores' => $errores,
        ];
    }

    // Fuera de la TX: DDL/rebuild no debe tumbar el commit de los DELETE
    if (function_exists('grupo_clave_reconstruir_secuencias')) {
        try {
            grupo_clave_reconstruir_secuencias($pdo, $idPlantel);
        } catch (Throwable $e) {
            $errores[] = 'reconstruir claves: ' . $e->getMessage();
        }
    }

    $ambito = $todosLosDelPlantel ? ' (todos del plantel)' : ' (mapa migración)';
    $msg = "{$gruposBorrados} grupo(s) borrado(s){$ambito}, {$mapasBorrados} mapa(s) limpiado(s)";
    if ($alumnosDesvinculados > 0) {
        $msg .= ", {$alumnosDesvinculados} vínculo(s) alumno-grupo quitado(s)";
    }
    $msg .= '; contador de claves reconstruido para el plantel.';
    migracion_csv_log($pdo, 'grupos', 'info', 'Purga grupos: ' . $msg);

    return [
        'ok' => true,
        'message' => $msg,
        'grupos_borrados' => $gruposBorrados,
        'mapas_borrados' => $mapasBorrados,
        'alumnos_desvinculados' => $alumnosDesvinculados,
        'planteles' => [$idPlantel],
        'errores' => array_slice($errores, 0, 20),
    ];
}

/**
 * Provisión batch de correos/Moodle para alumnos y staff recién migrados.
 *
 * @return array{ok:bool, message:string, alumnos_ok?:int, alumnos_fail?:int, staff_ok?:int, staff_fail?:int, errores?:list<string>}
 */
function migracion_csv_provisionar_cuentas(PDO $pdo, ?int $idPlantel = null, int $limite = 200): array
{
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    $idPlantel = $idPlantel ?: (function_exists('plantel_id_activo') ? plantel_id_activo() : 0);
    $alOk = $alFail = $stOk = $stFail = 0;
    $errores = [];

    // Alumnos mapeados sin moodle_user_id
    $sql = 'SELECT DISTINCT m.id_hay AS id_alumno, a.id_plantel
            FROM hay_migracion_csv_map m
            INNER JOIN alumnos a ON a.id_alumno = m.id_hay
            WHERE m.entidad = \'alumno\'';
    $params = [];
    if ($idPlantel > 0) {
        $sql .= ' AND a.id_plantel = ?';
        $params[] = $idPlantel;
    }
    $sql .= ' ORDER BY m.id_hay ASC LIMIT ' . max(1, min(500, $limite));
    $st = $pdo->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idAl = (int) $row['id_alumno'];
        $idp = (int) $row['id_plantel'];
        if (!function_exists('cuenta_externa_preparar_alumno')) {
            $errores[] = 'cuenta_externa_preparar_alumno no disponible';
            break;
        }
        $res = cuenta_externa_preparar_alumno($pdo, $idAl, $idp);
        if (!empty($res['ok'])) {
            $alOk++;
            // También asegurar fila usuarios alumno si existe helper
            if (function_exists('usuario_crear_cuenta_alumno')) {
                try {
                    usuario_crear_cuenta_alumno($pdo, $idAl, $idp);
                } catch (Throwable $e) {
                    // no bloquear
                }
            }
        } else {
            $alFail++;
            $errores[] = 'Alumno #' . $idAl . ': ' . ($res['message'] ?? 'error');
        }
    }

    // Staff mapeado
    $sqlU = 'SELECT DISTINCT m.id_hay AS id_usuario
             FROM hay_migracion_csv_map m
             INNER JOIN usuarios u ON u.id_usuario = m.id_hay
             WHERE m.entidad = \'usuario\'';
    $paramsU = [];
    if ($idPlantel > 0) {
        $sqlU .= ' AND u.id_plantel = ?';
        $paramsU[] = $idPlantel;
    }
    $sqlU .= ' ORDER BY m.id_hay ASC LIMIT ' . max(1, min(200, $limite));
    $stU = $pdo->prepare($sqlU);
    $stU->execute($paramsU);
    foreach ($stU->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idU = (int) $row['id_usuario'];
        if (function_exists('cuenta_externa_provisionar_staff')) {
            // false = crear Google Workspace si el correo aún no existe
            $res = cuenta_externa_provisionar_staff($pdo, $idU, false);
            if (!empty($res['ok'])) {
                $stOk++;
            } else {
                $stFail++;
                $errores[] = 'Usuario #' . $idU . ': ' . ($res['message'] ?? 'error');
            }
        } elseif (function_exists('moodle_user_ensure_staff')) {
            $res = moodle_user_ensure_staff($pdo, $idU);
            if (!empty($res['ok'])) {
                $stOk++;
            } else {
                $stFail++;
                $errores[] = 'Usuario #' . $idU . ' Moodle: ' . ($res['message'] ?? 'error');
            }
        }
    }

    $msg = "Alumnos OK {$alOk} / fallos {$alFail}; Staff OK {$stOk} / fallos {$stFail}";
    migracion_csv_log($pdo, 'provision', 'info', $msg);

    return [
        'ok' => ($alOk + $stOk) > 0 || ($alFail + $stFail) === 0,
        'message' => $msg,
        'alumnos_ok' => $alOk,
        'alumnos_fail' => $alFail,
        'staff_ok' => $stOk,
        'staff_fail' => $stFail,
        'errores' => array_slice($errores, 0, 30),
    ];
}

/**
 * Plantilla CSV mínima: email + plantel (+ rol opcional / nombres opcionales).
 *
 * @return list<string>
 */
function migracion_csv_staff_emails_headers(): array
{
    return [
        'email',
        'plantel_slug',
        'rol',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
    ];
}

/**
 * CSV de ejemplo (encabezados + 2–3 filas) para el tipo de migración elegido.
 *
 * @return array{filename:string, headers:list<string>, rows:list<list<string>>}|null
 */
function migracion_csv_ejemplo(PDO $pdo, string $tipo, ?string $slugPreferido = null): ?array
{
    $tipo = strtolower(trim($tipo));
    $slug = trim((string) $slugPreferido);
    if ($slug === '' && function_exists('plantel_list')) {
        $planteles = plantel_list($pdo, true);
        $slug = (string) ($planteles[0]['slug'] ?? 'guerrero');
    }
    if ($slug === '') {
        $slug = 'guerrero';
    }

    switch ($tipo) {
        case 'staff_emails':
            return [
                'filename' => 'ejemplo_personal_correos.csv',
                'headers' => migracion_csv_staff_emails_headers(),
                'rows' => [
                    ['juan.perez@cncm.edu.mx', $slug, 'profesor', '', '', ''],
                    ['maria.lopez@cncm.edu.mx', $slug, 'asesor', 'María', 'López', 'García'],
                    ['recepcion.' . $slug . '@cncm.edu.mx', $slug, 'recepcion', '', '', ''],
                ],
            ];

        case 'personal':
            return [
                'filename' => 'ejemplo_01_personal.csv',
                'headers' => [
                    'id_usuario_legado', 'nombres', 'apellido_paterno', 'apellido_materno',
                    'email_legado', 'rol_legado', 'rol', 'plantel_slug', 'username', 'notas',
                ],
                'rows' => [
                    ['101', 'Juan', 'Pérez', 'López', 'juan@ejemplo.com', 'Docente', 'profesor', $slug, 'juan.perez', ''],
                    ['102', 'Ana', 'Ruiz', '', '', 'Asesor', 'asesor', $slug, '', ''],
                    ['103', 'Luis', 'Hernández', 'Mora', '', 'Gerente', 'gerente', $slug, 'luis.hernandez', ''],
                ],
            ];

        case 'aulas':
            return [
                'filename' => 'ejemplo_02_aulas.csv',
                'headers' => ['plantel_slug', 'codigo', 'nombre', 'piso', 'capacidad', 'notas'],
                'rows' => [
                    [$slug, 'A1', 'Aula 1', '1', '12', ''],
                    [$slug, 'A2', 'Aula 2', '1', '12', ''],
                    [$slug, 'LAB', 'Laboratorio', 'PB', '16', 'cómputo kids'],
                ],
            ];

        case 'grupos':
            return [
                'filename' => 'ejemplo_03_grupos.csv',
                'headers' => [
                    'clave_legado', 'plantel_slug', 'clave_especialidad', 'infantil',
                    'fecha_inicio_estimada', 'clave_fase_estimada', 'dias_clase', 'hora_inicio', 'hora_fin',
                    'profesor_sugerido', 'profesor_username', 'aula_codigo',
                ],
                'rows' => [
                    // Adulto inglés sábado mañana (atajo I = ING; también vale ING)
                    ['IS100', $slug, 'I', '0', '2025-01-11', 'F1', '6', '08:00', '12:00', 'Jose Perez', '', 'A1'],
                    // Kids: misma clave en 2 filas (IK/CK = ING-K/COMP-K)
                    ['IKCF100', $slug, 'IK', '1', '2025-01-11', 'F1', '6', '08:00', '10:00', 'Maria Lopez', '', 'A1'],
                    ['IKCF100', $slug, 'CK', '1', '2025-01-11', 'F1', '6', '10:00', '12:00', 'Maria Lopez', '', 'LAB'],
                ],
            ];

        case 'alumnos':
            return [
                'filename' => 'ejemplo_04_alumnos.csv',
                'headers' => [
                    'id_alumno_legado', 'numero_control', 'nombres', 'apellido_paterno', 'apellido_materno',
                    'email', 'telefono', 'plantel_slug', 'clave_legado', 'fecha_inicio',
                    'forma_pago', 'id_asesor_legado', 'asesor_nombre',
                ],
                'rows' => [
                    ['9001', '2501001', 'Pedro', 'Ramírez', 'Solís', '', '5551112233', $slug, 'IS100', '2025-01-11', 'mensual', '', ''],
                    ['9002', '2501002', 'Lucía', 'Gómez', 'Nava', 'lucia@ejemplo.com', '', $slug, 'IS100', '2025-01-11', 'mensual', '', ''],
                    ['9003', '2501003', 'Diego', 'Torres', '', '', '', $slug, 'IKCF100', '2025-01-11', 'mensual', '', ''],
                ],
            ];

        case 'pagos':
            return [
                'filename' => 'ejemplo_05_pagos.csv',
                'headers' => [
                    'id_pago_legado', 'numero_control', 'id_alumno_legado', 'id_recibio_legado',
                    'fecha', 'monto', 'forma_pago', 'tipo', 'concepto', 'folio',
                ],
                'rows' => [
                    ['7001', '2501001', '9001', '101', '2025-01-15', '1200', 'efectivo', 'colegiatura', 'Enero', 'F-1001'],
                    ['7002', '2501002', '9002', '101', '2025-01-16', '1200', 'transferencia', 'colegiatura', 'Enero', 'F-1002'],
                    ['7003', '2501003', '9003', '102', '2025-01-18', '800', 'efectivo', 'inscripcion', 'Inscripción', 'F-1003'],
                ],
            ];

        default:
            return null;
    }
}

/** @return array{nombre:string, apellido:string} */
function migracion_csv_nombre_desde_email(string $email): array
{
    $local = strtolower((string) (explode('@', $email, 2)[0] ?? 'usuario'));
    $parts = preg_split('/[._\-]+/', $local) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts), static fn ($p) => $p !== ''));
    $nombre = isset($parts[0]) ? ucfirst($parts[0]) : 'Usuario';
    $apellido = count($parts) > 1
        ? implode(' ', array_map('ucfirst', array_slice($parts, 1)))
        : 'Staff';

    return ['nombre' => $nombre, 'apellido' => $apellido];
}

/**
 * Busca nombre en BD legado (si LEGACY_DB_* está configurado).
 *
 * @return array{ok:bool, nombres?:string, apellido_paterno?:string, apellido_materno?:string, id?:int}|null
 */
function migracion_csv_legacy_user_by_email(string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '' || !function_exists('legacy_import_pdo_legacy')) {
        return null;
    }
    try {
        $leg = legacy_import_pdo_legacy();
        if (!$leg instanceof PDO) {
            return null;
        }
        $st = $leg->prepare('SELECT * FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $nombres = trim((string) ($row['nombres'] ?? $row['name'] ?? $row['nombre'] ?? ''));
        $apPat = trim((string) ($row['apellido_paterno'] ?? $row['last_name'] ?? $row['apellido'] ?? ''));
        $apMat = trim((string) ($row['apellido_materno'] ?? ''));

        return [
            'ok' => true,
            'id' => (int) ($row['id'] ?? 0),
            'nombres' => $nombres,
            'apellido_paterno' => $apPat,
            'apellido_materno' => $apMat,
        ];
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Resuelve nombres: CSV → Google → legado → email.
 *
 * @param array<string,string> $row
 * @return array{
 *   email:string, nombres:string, apellido:string, fuente_nombre:string,
 *   google_existe:?bool, google_error?:string, id_legado?:int
 * }
 */
function migracion_csv_resolver_identidad_staff(array $row): array
{
    $email = strtolower(trim((string) ($row['email'] ?? $row['correo'] ?? '')));
    $nombres = trim((string) ($row['nombres'] ?? $row['nombre'] ?? ''));
    $apPat = trim((string) ($row['apellido_paterno'] ?? ''));
    $apMat = trim((string) ($row['apellido_materno'] ?? ''));
    $apellido = trim($apPat . ' ' . $apMat);
    if ($apellido === '' && isset($row['apellido'])) {
        $apellido = trim((string) $row['apellido']);
    }

    $fuente = 'csv';
    $googleExiste = null;
    $googleError = null;
    $idLegado = null;

    // Siempre consultar Google en preview/import para saber existe|crear|error de API
    // (no confundir "error" con "ya existe": existe se muestra como "existe").
    if ($email !== '' && function_exists('google_usuario_obtener')) {
        $g = google_usuario_obtener($email);
        if (!empty($g['ok'])) {
            $googleExiste = !empty($g['existe']);
            if ($googleExiste) {
                if ($nombres === '' && trim((string) ($g['nombre'] ?? '')) !== '') {
                    $nombres = trim((string) $g['nombre']);
                    $fuente = 'google';
                }
                if ($apellido === '' && trim((string) ($g['apellido'] ?? '')) !== '') {
                    $apellido = trim((string) $g['apellido']);
                    $fuente = 'google';
                }
            }
        } else {
            $googleError = (string) ($g['message'] ?? 'Error Google');
        }
    }

    if (($nombres === '' || $apellido === '') && $email !== '') {
        $leg = migracion_csv_legacy_user_by_email($email);
        if (is_array($leg) && !empty($leg['ok'])) {
            $idLegado = (int) ($leg['id'] ?? 0) ?: null;
            if ($nombres === '' && trim((string) ($leg['nombres'] ?? '')) !== '') {
                $nombres = trim((string) $leg['nombres']);
                $fuente = 'legado';
            }
            if ($apellido === '') {
                $ap = trim(
                    trim((string) ($leg['apellido_paterno'] ?? '')) . ' '
                    . trim((string) ($leg['apellido_materno'] ?? ''))
                );
                if ($ap !== '') {
                    $apellido = $ap;
                    $fuente = 'legado';
                }
            }
        }
    }

    if ($nombres === '' || $apellido === '') {
        $fb = migracion_csv_nombre_desde_email($email !== '' ? $email : 'usuario@cncm.edu.mx');
        if ($nombres === '') {
            $nombres = $fb['nombre'];
        }
        if ($apellido === '') {
            $apellido = $fb['apellido'];
        }
        if ($fuente === 'csv') {
            $fuente = 'email';
        }
    }

    return [
        'email' => $email,
        'nombres' => $nombres,
        'apellido' => $apellido,
        'fuente_nombre' => $fuente,
        'google_existe' => $googleExiste,
        'google_error' => $googleError,
        'id_legado' => $idLegado,
    ];
}

/**
 * Roles sugeridos en la vista previa / import de personal por correos.
 *
 * @return list<string>
 */
function migracion_csv_staff_roles_opciones(): array
{
    return [
        'profesor', 'asesor', 'gerente', 'coordinador',
        'recepcion', 'imagen', 'marketing',
        'admin', 'director', 'supervisor', 'manuales',
    ];
}

/**
 * Vista previa enriquecida del CSV de personal por correos.
 *
 * @return array{
 *   ok:bool, message?:string, headers?:list<string>, muestra?:list<array<string,string>>,
 *   total_filas?:int, offset?:int, limit?:int, errores?:list<string>, roles?:list<string>
 * }
 */
function migracion_csv_preview_staff_emails(
    PDO $pdo,
    string $tmpPath,
    ?int $idPlantel = null,
    int $offset = 0,
    int $limit = 50
): array {
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    $parsed = migracion_csv_leer_archivo($tmpPath);
    if (!empty($parsed['error'])) {
        return ['ok' => false, 'message' => $parsed['error']];
    }
    $rows = $parsed['rows'];
    if ($rows === []) {
        return ['ok' => false, 'message' => 'Sin filas de datos'];
    }

    $total = count($rows);
    $limit = max(10, min(100, $limit));
    $offset = max(0, min($offset, max(0, $total - 1)));

    $headers = [
        'email', 'plantel_slug', 'rol', 'nombres', 'apellido', 'fuente_nombre',
        'google', 'hay', 'accion',
    ];
    $muestra = [];
    $errores = [];
    $end = min($total, $offset + $limit);
    for ($i = $offset; $i < $end; $i++) {
        $r = $rows[$i];
        $id = migracion_csv_resolver_identidad_staff($r);
        $email = $id['email'];
        $plantelRaw = trim((string) ($r['plantel_slug'] ?? $r['plantel'] ?? ''));
        $idP = migracion_csv_resolver_plantel($pdo, $plantelRaw, $idPlantel);
        $rol = trim((string) ($r['rol'] ?? ''));
        if ($rol === '') {
            $rol = 'profesor';
        }
        $hayId = null;
        if ($email !== '') {
            $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE LOWER(email) = ? LIMIT 1');
            $st->execute([$email]);
            $ex = $st->fetchColumn();
            if ($ex !== false) {
                $hayId = (int) $ex;
            }
        }
        if ($id['google_existe'] === null) {
            $gErr = trim((string) ($id['google_error'] ?? ''));
            $googleLabel = $gErr !== ''
                ? ('error: ' . (function_exists('mb_substr') ? mb_substr($gErr, 0, 80) : substr($gErr, 0, 80)))
                : '?';
            if ($gErr !== '' && count($errores) < 5) {
                $errores[] = 'Google API: ' . $gErr;
            }
        } else {
            $googleLabel = $id['google_existe'] ? 'existe' : 'crear';
        }
        $accion = $hayId ? 'actualizar+provisionar' : 'crear+provisionar';
        if ($email === '' || !str_contains($email, '@')) {
            $accion = 'ERROR: email';
            $errores[] = 'Fila ' . ($i + 2) . ': correo inválido';
        } elseif ($idP === null || $idP <= 0) {
            $accion = 'ERROR: plantel';
            $errores[] = 'Fila ' . ($i + 2) . ': plantel no resuelto (' . $plantelRaw . ')';
        }
        $muestra[] = [
            'email' => $email,
            'plantel_slug' => $plantelRaw,
            'rol' => $rol,
            'nombres' => $id['nombres'],
            'apellido' => $id['apellido'],
            'fuente_nombre' => $id['fuente_nombre'],
            'google' => $googleLabel,
            'hay' => $hayId ? ('#' . $hayId) : 'nuevo',
            'accion' => $accion,
            'fila' => $i + 2,
        ];
    }

    $roles = migracion_csv_staff_roles_opciones();
    $labels = function_exists('rbac_roles_etiquetas') ? rbac_roles_etiquetas() : [];
    $roleLabels = [];
    foreach ($roles as $clave) {
        $roleLabels[$clave] = $labels[$clave] ?? $clave;
    }

    return [
        'ok' => true,
        'headers' => $headers,
        'muestra' => $muestra,
        'total_filas' => $total,
        'offset' => $offset,
        'limit' => $limit,
        'errores' => $errores,
        'roles' => $roles,
        'role_labels' => $roleLabels,
        'message' => $total . ' fila(s); mostrando ' . ($offset + 1) . '–' . $end,
    ];
}

/**
 * Importa personal activo por email+plantel y provisiona Google (crea si falta) + Moodle.
 *
 * CSV mínimo: email, plantel_slug
 * Opcional: rol, nombres, apellido_paterno, apellido_materno
 * $overrides: mapa email → {rol?, nombres?, apellido?} desde la UI de vista previa
 *
 * @param array<string, array{rol?:string, nombres?:string, apellido?:string}> $overrides
 * @return array{ok:bool,message:string,creados?:int,actualizados?:int,google_creados?:int,moodle_ok?:int,errores?:list<string>}
 */
function migracion_csv_importar_staff_emails(
    PDO $pdo,
    string $tmpPath,
    ?int $idPlantel = null,
    bool $provisionar = true,
    array $overrides = []
): array {
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    migracion_csv_ensure_schema($pdo);
    if (function_exists('usuario_ensure_schema')) {
        usuario_ensure_schema($pdo);
    }
    if (!function_exists('cuenta_externa_provisionar_staff') && is_file(__DIR__ . '/cuenta_externa_helper.php')) {
        require_once __DIR__ . '/cuenta_externa_helper.php';
    }

    $parsed = migracion_csv_leer_archivo($tmpPath);
    if (!empty($parsed['error'])) {
        return ['ok' => false, 'message' => $parsed['error']];
    }
    $rows = $parsed['rows'];
    if ($rows === []) {
        return ['ok' => false, 'message' => 'Sin filas de datos'];
    }

    $rolesOk = migracion_csv_staff_roles_opciones();
    $creados = 0;
    $actualizados = 0;
    $googleCreados = 0;
    $moodleOk = 0;
    $errores = [];

    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $id = migracion_csv_resolver_identidad_staff($r);
        $email = $id['email'];
        if ($email === '' || !str_contains($email, '@')) {
            $errores[] = "Línea {$line}: correo inválido";
            continue;
        }
        $ov = $overrides[$email] ?? $overrides[strtolower($email)] ?? [];
        if (!is_array($ov)) {
            $ov = [];
        }
        $plantelRaw = trim((string) ($r['plantel_slug'] ?? $r['plantel'] ?? ''));
        $idP = migracion_csv_resolver_plantel($pdo, $plantelRaw, $idPlantel);
        if ($idP === null || $idP <= 0) {
            $errores[] = "Línea {$line}: plantel no resuelto ({$plantelRaw})";
            continue;
        }
        $rol = trim((string) ($ov['rol'] ?? $r['rol'] ?? ''));
        if ($rol === '') {
            $rol = 'profesor';
        }
        if (function_exists('rbac_normalizar_rol_clave')) {
            $rol = rbac_normalizar_rol_clave($rol);
        }
        if (!in_array($rol, $rolesOk, true)) {
            $errores[] = "Línea {$line}: rol no permitido ({$rol})";
            continue;
        }
        $nombres = trim((string) ($ov['nombres'] ?? ''));
        if ($nombres === '') {
            $nombres = $id['nombres'];
        }
        $apellido = trim((string) ($ov['apellido'] ?? ''));
        if ($apellido === '') {
            $apellido = $id['apellido'];
        }
        $username = strtolower((string) (explode('@', $email, 2)[0] ?? ''));
        $username = preg_replace('/[^a-z0-9._-]/', '', $username) ?? '';
        if ($username === '') {
            $username = 'user' . $line;
        }

        $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE LOWER(email) = ? OR username = ? LIMIT 1');
        $st->execute([$email, $username]);
        $existing = $st->fetchColumn();
        $idHay = $existing !== false ? (int) $existing : 0;

        $passPlain = function_exists('cuenta_password_inicial') ? cuenta_password_inicial() : 'Cncm*1234';
        $depto = function_exists('rbac_departamento_para_rol')
            ? rbac_departamento_para_rol($pdo, $rol)
            : 'administrativo';
        if (!in_array($depto, ['ingles', 'computacion', 'administrativo', 'ventas', 'otro'], true)) {
            $depto = 'administrativo';
        }

        if ($idHay > 0) {
            $pdo->prepare(
                'UPDATE usuarios SET nombre=?, apellido=?, rol=?, departamento=?, id_plantel=?, email=?, username=? WHERE id_usuario=?'
            )->execute([$nombres, $apellido, $rol, $depto, $idP, $email, $username, $idHay]);
            $actualizados++;
        } else {
            // Evitar choque de username
            $stU = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = ? LIMIT 1');
            $stU->execute([$username]);
            if ($stU->fetchColumn() !== false) {
                $username .= $line;
            }
            $pdo->prepare(
                'INSERT INTO usuarios (nombre, apellido, username, email, password, rol, departamento, id_plantel, fecha_creacion)
                 VALUES (?,?,?,?,?,?,?,?,NOW())'
            )->execute([
                $nombres,
                $apellido,
                $username,
                $email,
                password_hash($passPlain, PASSWORD_BCRYPT),
                $rol,
                $depto,
                $idP,
            ]);
            $idHay = (int) $pdo->lastInsertId();
            $creados++;
        }

        $mapKey = $id['id_legado'] ? (string) $id['id_legado'] : $email;
        migracion_csv_map_set($pdo, 'usuario', $mapKey, $idHay, $email);

        if (!$provisionar) {
            continue;
        }

        if (function_exists('cuenta_externa_provisionar_staff')) {
            $prov = cuenta_externa_provisionar_staff($pdo, $idHay, false, $email, $passPlain);
            if (empty($prov['ok'])) {
                $errores[] = "Línea {$line} ({$email}): " . ($prov['message'] ?? 'provisión falló');
                continue;
            }
            $msg = (string) ($prov['message'] ?? '');
            if (stripos($msg, 'creado en Google') !== false || stripos($msg, 'Usuario creado') !== false) {
                $googleCreados++;
            }
            if (!empty($prov['moodle_ok'])) {
                $moodleOk++;
            } elseif (array_key_exists('moodle_ok', $prov) && empty($prov['moodle_ok'])) {
                $errores[] = "Línea {$line} ({$email}): " . ($prov['message'] ?? 'Moodle pendiente');
            }
        } elseif (function_exists('moodle_user_ensure_staff')) {
            $m = moodle_user_ensure_staff($pdo, $idHay, $passPlain);
            if (!empty($m['ok'])) {
                $moodleOk++;
            } else {
                $errores[] = "Línea {$line} Moodle: " . ($m['message'] ?? 'error');
            }
        }
    }

    $msg = "Personal correos: {$creados} creados, {$actualizados} actualizados"
        . ($provisionar ? "; Google nuevos ~{$googleCreados}; Moodle OK {$moodleOk}" : '');
    migracion_csv_log($pdo, 'staff_emails', 'info', $msg);

    return [
        'ok' => ($creados + $actualizados) > 0,
        'message' => $msg,
        'creados' => $creados,
        'actualizados' => $actualizados,
        'google_creados' => $googleCreados,
        'moodle_ok' => $moodleOk,
        'errores' => array_slice($errores, 0, 40),
    ];
}

/**
 * Vista previa de alumnos: grupo mapeado + alertas (teléfono, NC, etc.).
 * Las filas con alerta aparecen primero. Con $soloAlertas solo se paginan esas.
 *
 * @return array{
 *   ok:bool, message?:string, headers?:list<string>, muestra?:list<array<string,mixed>>,
 *   total_filas?:int, total_csv?:int, con_alerta?:int, offset?:int, limit?:int,
 *   errores?:list<string>, grupos_faltantes?:list<string>, alertas_resumen?:array<string,int>,
 *   solo_alertas?:bool
 * }
 */
function migracion_csv_preview_alumnos(
    PDO $pdo,
    string $tmpPath,
    ?int $idPlantel = null,
    int $offset = 0,
    int $limit = 50,
    bool $soloAlertas = false
): array {
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    migracion_csv_ensure_schema($pdo);
    $parsed = migracion_csv_leer_archivo($tmpPath);
    if (!empty($parsed['error'])) {
        return ['ok' => false, 'message' => $parsed['error']];
    }
    $rows = $parsed['rows'];
    if ($rows === []) {
        return ['ok' => false, 'message' => 'Sin filas de datos'];
    }

    $totalCsv = count($rows);
    $limit = max(10, min(100, $limit));
    $telMax = migracion_csv_telefono_max_len();
    $headers = [
        'fila',
        'alerta',
        'numero_control',
        'nombres',
        'apellido_paterno',
        'telefono',
        'clave_legado',
        'grupo_hay',
        'grupo_estado',
        'accion',
        'plantel_slug',
        'id_alumno_legado',
        'id_grupo_legado',
    ];

    /** @var array<string, true> $faltantes */
    $faltantes = [];
    /** @var array<string, int> $resumen */
    $resumen = [
        'plantel' => 0,
        'grupo_faltante' => 0,
        'sin_nc' => 0,
        'sin_nombre' => 0,
        'telefono_largo' => 0,
        'nc_duplicado' => 0,
    ];
    /** @var array<string, int> $ncPrimeraFila */
    $ncPrimeraFila = [];
    /** @var list<array<string,mixed>> $evaluadas */
    $evaluadas = [];
    $errores = [];

    foreach ($rows as $i => $r) {
        $filaCsv = $i + 2;
        $idG = migracion_csv_norm_id_legado((string) ($r['id_grupo_legado'] ?? ''));
        $claveG = trim((string) ($r['clave_grupo_legado'] ?? $r['clave_legado'] ?? ''));
        $plantelRaw = trim((string) ($r['plantel_slug'] ?? ''));
        $nc = trim((string) ($r['numero_control'] ?? ''));
        $nombres = trim((string) ($r['nombres'] ?? ''));
        $telRaw = trim((string) ($r['telefono'] ?? ''));
        $idP = migracion_csv_resolver_plantel($pdo, $plantelRaw, $idPlantel);
        $grupoHay = '';
        $estado = 'ok';
        $accion = 'crear/vincular';
        /** @var list<string> $alertas */
        $alertas = [];

        if ($nombres === '' && $nc === '') {
            continue;
        }
        if ($nc === '') {
            $alertas[] = 'sin numero_control';
            $resumen['sin_nc']++;
            $accion = 'AVISO: sin NC';
        } elseif (isset($ncPrimeraFila[$nc])) {
            $alertas[] = 'NC duplicado (también fila ' . $ncPrimeraFila[$nc] . ')';
            $resumen['nc_duplicado']++;
        } else {
            $ncPrimeraFila[$nc] = $filaCsv;
        }
        if ($nombres === '') {
            $alertas[] = 'sin nombres';
            $resumen['sin_nombre']++;
        }
        if ($telRaw !== '' && migracion_csv_telefono_len($telRaw) > $telMax) {
            $alertas[] = 'teléfono >' . $telMax . ' chars (se truncará)';
            $resumen['telefono_largo']++;
        }

        if ($idP === null || $idP <= 0) {
            $estado = 'plantel';
            $accion = 'ERROR: plantel';
            $alertas[] = 'plantel no resuelto';
            $resumen['plantel']++;
            $errores[] = 'Fila ' . $filaCsv . ': plantel no resuelto';
        } else {
            [$gid, $gidComp] = migracion_csv_resolver_grupos_para_alumno($pdo, $idP, $idG, $claveG);
            if ($gid === null || $gid <= 0) {
                $estado = 'faltante';
                $accion = 'ERROR: importe grupo';
                $ref = $claveG !== '' ? $claveG : ($idG !== '' ? $idG : '(vacío)');
                $faltantes[$ref] = true;
                $alertas[] = 'grupo no mapeado';
                $resumen['grupo_faltante']++;
                $errores[] = 'Fila ' . $filaCsv . ': grupo legado ' . $ref . ' no mapeado (use clave_legado)';
            } else {
                $grupoHay = '#' . $gid . ($gidComp ? ('+#' . $gidComp) : '');
                if ($gidComp) {
                    $accion = 'crear/vincular (kids dual)';
                }
            }
        }

        $evaluadas[] = [
            'fila' => $filaCsv,
            'alerta' => $alertas !== [] ? implode('; ', $alertas) : '',
            'tiene_alerta' => $alertas !== [],
            'id_alumno_legado' => migracion_csv_norm_id_legado((string) ($r['id_alumno_legado'] ?? '')),
            'numero_control' => $nc,
            'nombres' => $nombres,
            'apellido_paterno' => trim((string) ($r['apellido_paterno'] ?? '')),
            'telefono' => $telRaw,
            'plantel_slug' => $plantelRaw,
            'id_grupo_legado' => $idG,
            'clave_legado' => $claveG,
            'grupo_hay' => $grupoHay,
            'grupo_estado' => $estado,
            'accion' => $accion,
        ];
    }

    $conAlertaAll = 0;
    foreach ($evaluadas as $row) {
        if (!empty($row['tiene_alerta'])) {
            $conAlertaAll++;
        }
    }

    // Filas con alerta primero (para corregir antes de importar)
    usort($evaluadas, static function (array $a, array $b): int {
        $ha = !empty($a['tiene_alerta']) ? 1 : 0;
        $hb = !empty($b['tiene_alerta']) ? 1 : 0;
        if ($ha !== $hb) {
            return $hb <=> $ha;
        }

        return ((int) $a['fila']) <=> ((int) $b['fila']);
    });

    if ($soloAlertas) {
        $evaluadas = array_values(array_filter(
            $evaluadas,
            static fn (array $row): bool => !empty($row['tiene_alerta'])
        ));
    }

    $total = count($evaluadas);
    $offset = max(0, min($offset, max(0, $total > 0 ? $total - 1 : 0)));
    $slice = array_slice($evaluadas, $offset, $limit);
    $muestra = [];
    foreach ($slice as $row) {
        unset($row['tiene_alerta']);
        $muestra[] = $row;
    }

    $faltanList = array_keys($faltantes);
    sort($faltanList, SORT_NATURAL);

    $msg = $totalCsv . ' alumno(s) en CSV';
    if ($conAlertaAll > 0) {
        $msg .= ' · ' . $conAlertaAll . ' con alerta';
    } else {
        $msg .= ' · sin alertas';
    }
    if ($soloAlertas) {
        $msg .= ' (filtro: solo alertas, ' . $total . ')';
    }
    $partesAlerta = [];
    foreach ($resumen as $k => $n) {
        if ($n > 0) {
            $partesAlerta[] = str_replace('_', ' ', $k) . ': ' . $n;
        }
    }
    if ($partesAlerta !== []) {
        $msg .= ' — ' . implode(', ', $partesAlerta);
    }
    if ($faltanList !== []) {
        $msg .= '; grupos legado sin mapa: ' . implode(', ', array_slice($faltanList, 0, 15))
            . (count($faltanList) > 15 ? '…' : '');
    }

    return [
        'ok' => true,
        'headers' => $headers,
        'muestra' => $muestra,
        'total_filas' => $total,
        'total_csv' => $totalCsv,
        'con_alerta' => $conAlertaAll,
        'offset' => $offset,
        'limit' => $limit,
        'solo_alertas' => $soloAlertas,
        'errores' => array_slice(array_values(array_unique($errores)), 0, 40),
        'grupos_faltantes' => $faltanList,
        'alertas_resumen' => $resumen,
        'message' => $msg,
    ];
}

/**
 * @return array{ok:bool, message:string, ...}
 */
function migracion_csv_importar_tipo(
    PDO $pdo,
    string $tipo,
    string $tmpPath,
    ?int $idPlantel = null,
    bool $provisionarAlumnos = false
): array {
    $parsed = migracion_csv_leer_archivo($tmpPath);
    if (!empty($parsed['error'])) {
        return ['ok' => false, 'message' => $parsed['error']];
    }
    $rows = $parsed['rows'];
    if ($rows === []) {
        return ['ok' => false, 'message' => 'Sin filas de datos'];
    }

    return match ($tipo) {
        'personal' => migracion_csv_importar_personal($pdo, $rows, $idPlantel),
        'staff_emails' => migracion_csv_importar_staff_emails($pdo, $tmpPath, $idPlantel, true),
        'aulas' => migracion_csv_importar_aulas($pdo, $rows, $idPlantel),
        'grupos' => migracion_csv_importar_grupos($pdo, $rows, $idPlantel),
        'alumnos' => migracion_csv_importar_alumnos($pdo, $rows, $idPlantel, $provisionarAlumnos),
        'pagos' => migracion_csv_importar_pagos($pdo, $rows, $idPlantel),
        default => ['ok' => false, 'message' => 'Tipo de CSV no reconocido'],
    };
}
