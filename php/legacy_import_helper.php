<?php

/**
 * Importación ETL: base de datos Laravel legado → HAY.
 * Respeta el modelo HAY; solo transforma y mapea datos.
 */

define('LEGACY_PREREG_STATUS', ['Pre-Registro', '']);

function legacy_import_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_legacy_equivalence (
            entidad VARCHAR(32) NOT NULL,
            id_legacy BIGINT UNSIGNED NOT NULL,
            id_hay INT UNSIGNED NULL,
            modo ENUM(\'usar\',\'omitir\',\'crear\') NOT NULL DEFAULT \'usar\',
            notas VARCHAR(255) NULL,
            actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (entidad, id_legacy),
            KEY idx_equiv_hay (entidad, id_hay)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_legacy_map (
            entidad VARCHAR(32) NOT NULL,
            id_legacy BIGINT UNSIGNED NOT NULL,
            id_hay INT UNSIGNED NOT NULL,
            notas VARCHAR(255) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (entidad, id_legacy),
            KEY idx_legacy_hay (entidad, id_hay)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    try {
        $pdo->exec("UPDATE hay_legacy_map SET entidad = 'plantel' WHERE entidad = 'planteles'");
    } catch (Throwable $e) {
        /* ignore */
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_legacy_import_log (
            id_log INT UNSIGNED NOT NULL AUTO_INCREMENT,
            fase VARCHAR(40) NOT NULL,
            nivel ENUM(\'info\',\'warn\',\'error\') NOT NULL DEFAULT \'info\',
            mensaje TEXT NOT NULL,
            id_legacy BIGINT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_log),
            KEY idx_legacy_log_fase (fase, creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function legacy_import_pdo_legacy(): ?PDO
{
    $host = defined('LEGACY_DB_HOST') ? LEGACY_DB_HOST : '';
    $name = defined('LEGACY_DB_NAME') ? LEGACY_DB_NAME : '';
    $user = defined('LEGACY_DB_USER') ? LEGACY_DB_USER : '';
    $pass = defined('LEGACY_DB_PASS') ? LEGACY_DB_PASS : '';
    if ($host === '' || $name === '') {
        return null;
    }
    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function legacy_import_log(PDO $pdo, string $fase, string $nivel, string $mensaje, ?int $idLegacy = null): void
{
    $pdo->prepare(
        'INSERT INTO hay_legacy_import_log (fase, nivel, mensaje, id_legacy) VALUES (?,?,?,?)'
    )->execute([$fase, $nivel, $mensaje, $idLegacy]);
}

function legacy_entidad_normalize(string $entidad): string
{
    $e = strtolower(trim($entidad));
    if ($e === 'planteles') {
        return 'plantel';
    }
    return $e;
}

function legacy_equiv_get(PDO $pdo, string $entidad, int $idLegacy): ?array
{
    $entidad = legacy_entidad_normalize($entidad);
    $st = $pdo->prepare(
        'SELECT id_hay, modo FROM hay_legacy_equivalence WHERE entidad = ? AND id_legacy = ? LIMIT 1'
    );
    $st->execute([$entidad, $idLegacy]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function legacy_equiv_save(PDO $pdo, string $entidad, int $idLegacy, ?int $idHay, string $modo, ?string $notas = null): void
{
    $entidad = legacy_entidad_normalize($entidad);
    $modo = in_array($modo, ['usar', 'omitir', 'crear'], true) ? $modo : 'usar';
    $pdo->prepare(
        'INSERT INTO hay_legacy_equivalence (entidad, id_legacy, id_hay, modo, notas)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE id_hay = VALUES(id_hay), modo = VALUES(modo), notas = VALUES(notas)'
    )->execute([$entidad, $idLegacy, $idHay, $modo, $notas]);
    if ($modo === 'usar' && $idHay !== null && $idHay > 0) {
        legacy_map_set($pdo, $entidad, $idLegacy, $idHay, 'equivalencia_manual');
    }
}

/** Resuelve ID HAY: equivalencia manual → mapa importación → null. */
function legacy_resolve_hay_id(PDO $pdo, string $entidad, int $idLegacy): ?int
{
    if ($idLegacy <= 0) {
        return null;
    }
    $entidad = legacy_entidad_normalize($entidad);
    $eq = legacy_equiv_get($pdo, $entidad, $idLegacy);
    if ($eq) {
        if ($eq['modo'] === 'omitir') {
            return null;
        }
        if ($eq['modo'] === 'usar' && !empty($eq['id_hay'])) {
            return (int) $eq['id_hay'];
        }
    }
    return legacy_map_get($pdo, $entidad, $idLegacy);
}

function legacy_map_get(PDO $pdo, string $entidad, int $idLegacy): ?int
{
    $entidad = legacy_entidad_normalize($entidad);
    $st = $pdo->prepare(
        'SELECT id_hay FROM hay_legacy_map WHERE entidad = ? AND id_legacy = ? LIMIT 1'
    );
    $st->execute([$entidad, $idLegacy]);
    $v = $st->fetchColumn();
    return $v !== false ? (int) $v : null;
}

function legacy_map_set(PDO $pdo, string $entidad, int $idLegacy, int $idHay, ?string $notas = null): void
{
    $entidad = legacy_entidad_normalize($entidad);
    $pdo->prepare(
        'INSERT INTO hay_legacy_map (entidad, id_legacy, id_hay, notas)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE id_hay = VALUES(id_hay), notas = VALUES(notas)'
    )->execute([$entidad, $idLegacy, $idHay, $notas]);
}

function legacy_import_reset_map(PDO $pdo): void
{
    $pdo->exec('TRUNCATE TABLE hay_legacy_map');
    $pdo->exec('TRUNCATE TABLE hay_legacy_import_log');
}

function legacy_import_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $st->execute([$table]);
    return (bool) $st->fetchColumn();
}

function legacy_import_slug(string $text): string
{
    $s = strtolower(trim($text));
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'sede';
}

function legacy_import_plantel_match(PDO $hay, string $nombre): ?int
{
    $nombre = trim($nombre);
    $slug = legacy_import_slug($nombre);
    $st = $hay->prepare(
        'SELECT id_plantel FROM planteles
         WHERE slug = ? OR LOWER(nombre) = LOWER(?) LIMIT 1'
    );
    $st->execute([$slug, $nombre]);
    $id = $st->fetchColumn();
    return $id !== false ? (int) $id : null;
}

function legacy_import_map_medio(?string $como): string
{
    $c = strtoupper(trim((string) $como));
    $map = [
        'REDES SOCIALES (FACEBOOK, INSTAGRAM, PAGINA WEB, GOOGLE )' => 'redes_sociales',
        'REDES SOCIALES (FACEBOOK, INSTAGRAM, PAGINA WEB, GOOGLE)' => 'redes_sociales',
        'PUBLICIDAD(VOLANTES, CARTELES, ANUNCIOS)' => 'publicidad',
        'CARTAS' => 'cartas',
        'PASANDO' => 'pasando',
        'RECOMENDADO' => 'recomendado',
        'CRM' => 'crm',
        'CITA DE CRM' => 'cita_crm',
        'OTRO' => 'otro',
    ];
    return $map[$c] ?? 'otro';
}

function legacy_import_map_grado(mixed $jsonOrText): ?string
{
    if ($jsonOrText === null || $jsonOrText === '') {
        return null;
    }
    if (is_string($jsonOrText) && $jsonOrText[0] === '[') {
        $arr = json_decode($jsonOrText, true);
        $first = is_array($arr) && isset($arr[0]) ? strtoupper((string) $arr[0]) : '';
    } else {
        $first = strtoupper(trim((string) $jsonOrText));
    }
    $m = [
        'PRIMARIA' => 'primaria',
        'SECUNDARIA' => 'secundaria',
        'PREPARATORIA' => 'preparatoria',
        'UNIVERSIDAD' => 'universidad',
        'OTROS' => 'otros',
    ];
    return $m[$first] ?? 'otros';
}

function legacy_import_map_rol(?string $roleName): string
{
    $r = strtolower(trim((string) $roleName));
    $map = [
        'admin' => 'admin',
        'administrador' => 'admin',
        'gerente' => 'gerente',
        'supervisor' => 'supervisor',
        'profesor' => 'profesor',
        'teacher' => 'profesor',
        'asesor' => 'asesor',
        'ventas' => 'asesor',
        'recepcion' => 'recepcion',
        'alumno' => 'alumno',
    ];
    return $map[$r] ?? 'asesor';
}

function legacy_import_user_role(PDO $leg, int $userId): string
{
    if (!legacy_import_table_exists($leg, 'model_has_roles')) {
        return 'asesor';
    }
    $st = $leg->prepare(
        'SELECT r.name FROM roles r
         INNER JOIN model_has_roles mhr ON mhr.role_id = r.id
         WHERE mhr.model_id = ? AND mhr.model_type LIKE ?
         LIMIT 1'
    );
    $st->execute([$userId, '%User%']);
    $name = $st->fetchColumn();
    return legacy_import_map_rol($name !== false ? (string) $name : null);
}

function legacy_import_user_plantel(PDO $leg, int $userId): ?int
{
    if (!legacy_import_table_exists($leg, 'sucursales_usuarios')) {
        return null;
    }
    $st = $leg->prepare(
        'SELECT id_sucursal FROM sucursales_usuarios WHERE id_usuario = ? LIMIT 1'
    );
    $st->execute([$userId]);
    $sid = $st->fetchColumn();
    return $sid !== false ? (int) $sid : null;
}

function legacy_import_username_from_user(array $u): string
{
    $email = trim((string) ($u['email'] ?? ''));
    if ($email !== '' && strpos($email, '@') !== false) {
        return strtolower(explode('@', $email, 2)[0]);
    }
    return 'u' . (int) ($u['id'] ?? 0);
}

/** @return array{inserted:int,skipped:int,errors:int} */
function legacy_import_fase_planteles(PDO $hay, PDO $leg, bool $dryRun = false): array
{
    $fase = 'plantel';
    $ins = $skipped = $errors = 0;
    $rows = $leg->query('SELECT id, nombre FROM sucursales ORDER BY id')->fetchAll();
    foreach ($rows as $row) {
        $lid = (int) $row['id'];
        $resolved = legacy_resolve_hay_id($hay, $fase, $lid);
        if ($resolved !== null) {
            if (!$dryRun) {
                legacy_map_set($hay, $fase, $lid, $resolved, 'ya_mapeado');
            }
            $skipped++;
            continue;
        }
        $eq = legacy_equiv_get($hay, $fase, $lid);
        if ($eq && $eq['modo'] === 'omitir') {
            $skipped++;
            continue;
        }
        $nombre = trim((string) $row['nombre']);
        $idHay = null;
        if ($eq && $eq['modo'] === 'usar' && !empty($eq['id_hay'])) {
            $idHay = (int) $eq['id_hay'];
        } else {
            $idHay = legacy_import_plantel_match($hay, $nombre);
        }
        if ($idHay === null && ($eq['modo'] ?? '') !== 'crear' && !$eq) {
            $idHay = legacy_import_plantel_match($hay, $nombre);
        }
        if ($idHay === null && !$dryRun && (!$eq || ($eq['modo'] ?? '') === 'crear')) {
            $slug = legacy_import_slug($nombre);
            $chk = $hay->prepare('SELECT id_plantel FROM planteles WHERE slug = ? LIMIT 1');
            $chk->execute([$slug]);
            if ($chk->fetchColumn()) {
                $slug .= '-leg-' . $lid;
            }
            $hay->prepare(
                'INSERT INTO planteles (slug, nombre, orden, activo) VALUES (?,?,?,1)'
            )->execute([$slug, $nombre, 10 + $lid]);
            $idHay = (int) $hay->lastInsertId();
            $ins++;
        } elseif ($idHay !== null) {
            $skipped++;
        }
        if ($idHay !== null && !$dryRun) {
            legacy_map_set($hay, $fase, $lid, $idHay, $nombre);
        }
    }
    legacy_import_log($hay, 'planteles', 'info', "Planteles: $ins nuevos, $skipped omitidos/mapeados");
    return ['inserted' => $ins, 'skipped' => $skipped, 'errors' => $errors];
}

/** @return array{inserted:int,skipped:int,errors:int} */
function legacy_import_fase_especialidades(PDO $hay, PDO $leg, bool $dryRun = false): array
{
    $fase = 'especialidad';
    $ins = $skipped = $errors = 0;
    catalog_ensure_schema($hay);
    $rows = $leg->query(
        'SELECT id, id_sucursal, nombre, descripcion,
                precio_inscripcion, precio_mensualidad, precio_mensualidad_pronto_pago, precio_semanal
         FROM especialidades ORDER BY id'
    )->fetchAll();
    foreach ($rows as $row) {
        $lid = (int) $row['id'];
        if (legacy_map_get($hay, $fase, $lid) !== null) {
            $skipped++;
            continue;
        }
        $resolved = legacy_resolve_hay_id($hay, $fase, $lid);
        if ($resolved !== null) {
            if (!$dryRun) {
                legacy_map_set($hay, $fase, $lid, $resolved, 'ya_mapeado');
            }
            $skipped++;
            continue;
        }
        $eq = legacy_equiv_get($hay, $fase, $lid);
        if ($eq && $eq['modo'] === 'omitir') {
            $skipped++;
            continue;
        }
        $nombre = trim((string) $row['nombre']);
        $idHay = false;
        if ($eq && $eq['modo'] === 'usar' && !empty($eq['id_hay'])) {
            $idHay = (int) $eq['id_hay'];
        } else {
            $st = $hay->prepare('SELECT id_especialidad FROM especialidades WHERE LOWER(nombre) = LOWER(?) LIMIT 1');
            $st->execute([$nombre]);
            $idHay = $st->fetchColumn();
        }
        if ($idHay === false) {
            $clave = catalog_normalizar_clave('LEG_' . $lid);
            if (!$dryRun) {
                $hay->prepare(
                    'INSERT INTO especialidades (
                        clave, nombre, descripcion, costo_inscripcion, costo_mensualidad,
                        visible, orden, modalidad
                     ) VALUES (?,?,?,?,?,1,?,?)'
                )->execute([
                    $clave,
                    $nombre,
                    trim((string) ($row['descripcion'] ?? '')),
                    (float) ($row['precio_inscripcion'] ?? 0),
                    (float) ($row['precio_mensualidad'] ?? 0),
                    100 + $lid,
                    'regular',
                ]);
                $idHay = (int) $hay->lastInsertId();
                $ins++;
            }
        } else {
            $idHay = (int) $idHay;
            $skipped++;
        }
        if (!$dryRun && isset($idHay)) {
            legacy_map_set($hay, $fase, $lid, (int) $idHay);
        }
    }
    legacy_import_log($hay, 'especialidades', 'info', "Especialidades: $ins nuevas, $skipped omitidas");
    return ['inserted' => $ins, 'skipped' => $skipped, 'errors' => $errors];
}

/** @return array{inserted:int,skipped:int,errors:int} */
function legacy_import_fase_usuarios(PDO $hay, PDO $leg, bool $dryRun = false): array
{
    $fase = 'usuario';
    $ins = $skipped = $errors = 0;
    usuario_ensure_schema($hay);
    $del = legacy_import_table_exists($leg, 'users') && legacy_import_column_exists($leg, 'users', 'deleted_at')
        ? ' WHERE deleted_at IS NULL' : '';
    $rows = $leg->query('SELECT * FROM users' . $del)->fetchAll();
    foreach ($rows as $u) {
        $lid = (int) $u['id'];
        if (legacy_map_get($hay, $fase, $lid) !== null) {
            $skipped++;
            continue;
        }
        $username = legacy_import_username_from_user($u);
        $dup = $hay->prepare('SELECT id_usuario FROM usuarios WHERE username = ? LIMIT 1');
        $dup->execute([$username]);
        $existing = $dup->fetchColumn();
        if ($existing) {
            legacy_map_set($hay, $fase, $lid, (int) $existing, 'username_existente');
            $skipped++;
            continue;
        }
        $legacySuc = legacy_import_user_plantel($leg, $lid);
        $idPlantel = $legacySuc !== null
            ? (legacy_resolve_hay_id($hay, 'plantel', $legacySuc) ?? plantel_default_id($hay))
            : plantel_default_id($hay);
        $rol = legacy_import_user_role($leg, $lid);
        if (!$dryRun) {
            $hay->prepare(
                'INSERT INTO usuarios (nombre, apellido, username, email, password, rol, departamento, id_plantel, fecha_creacion)
                 VALUES (?,?,?,?,?,?,?,?,NOW())'
            )->execute([
                trim((string) ($u['nombres'] ?? 'Usuario')),
                trim(((string) ($u['apellido_paterno'] ?? '')) . ' ' . ((string) ($u['apellido_materno'] ?? ''))),
                $username,
                trim((string) ($u['email'] ?? '')),
                $u['password'] ?? password_hash('Cambiar123!', PASSWORD_BCRYPT),
                $rol,
                '',
                $idPlantel,
            ]);
            $idHay = (int) $hay->lastInsertId();
            legacy_map_set($hay, $fase, $lid, $idHay);
            $ins++;
        }
    }
    legacy_import_log($hay, 'usuarios', 'info', "Usuarios: $ins nuevos, $skipped omitidos");
    return ['inserted' => $ins, 'skipped' => $skipped, 'errors' => $errors];
}

function legacy_import_column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $st->execute([$table, $column]);
    return (bool) $st->fetchColumn();
}

/** Columnas existentes en una tabla del legado (para armar SELECT sin 1054). */
function legacy_import_legacy_columns(PDO $leg, string $table): array
{
    static $cache = [];
    $db = (string) $leg->query('SELECT DATABASE()')->fetchColumn();
    $key = $db . '.' . $table;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $st = $leg->prepare(
        'SELECT column_name FROM information_schema.columns
         WHERE table_schema = ? AND table_name = ?
         ORDER BY ordinal_position'
    );
    $st->execute([$db, $table]);
    $cache[$key] = array_map('strtolower', $st->fetchAll(PDO::FETCH_COLUMN));
    return $cache[$key];
}

/** @param list<string> $candidates Orden deseado; solo se incluyen las que existan. */
function legacy_import_select_cols(PDO $leg, string $table, array $candidates): string
{
    $have = array_flip(legacy_import_legacy_columns($leg, $table));
    $pick = [];
    foreach ($candidates as $col) {
        $c = strtolower($col);
        if (isset($have[$c])) {
            $pick[] = $col;
        }
    }
    if ($pick === []) {
        throw new RuntimeException('La tabla legado «' . $table . '» no tiene columnas utilizables.');
    }
    return implode(', ', $pick);
}

/** @return array{inserted:int,skipped:int,errors:int} */
function legacy_import_fase_productos(PDO $hay, PDO $leg, bool $dryRun = false): array
{
    $fase = 'producto';
    $ins = $skipped = 0;
    if (!legacy_import_table_exists($leg, 'productos')) {
        return ['inserted' => 0, 'skipped' => 0, 'errors' => 0];
    }
    catalog_ensure_schema($hay);
    $rows = $leg->query('SELECT * FROM productos ORDER BY id')->fetchAll();
    foreach ($rows as $row) {
        $lid = (int) $row['id'];
        if (legacy_map_get($hay, $fase, $lid) !== null) {
            $skipped++;
            continue;
        }
        $nombre = trim((string) ($row['nombre'] ?? 'Producto'));
        $clave = catalog_normalizar_clave((string) ($row['clave'] ?? 'LEG_P' . $lid), 40);
        if (!$dryRun) {
            $hay->prepare(
                'INSERT INTO productos (clave, nombre, descripcion, precio, activo, orden)
                 VALUES (?,?,?,?,1,?)'
            )->execute([
                $clave,
                $nombre,
                trim((string) ($row['descripcion'] ?? '')),
                (float) ($row['precio'] ?? 0),
                50 + $lid,
            ]);
            legacy_map_set($hay, $fase, $lid, (int) $hay->lastInsertId());
            $ins++;
        }
    }
    legacy_import_log($hay, 'productos', 'info', "Productos: $ins nuevos");
    return ['inserted' => $ins, 'skipped' => $skipped, 'errors' => 0];
}

/**
 * Resuelve id_especialidad HAY para un grupo del legado (usa equivalencias manuales).
 */
function legacy_grupo_resolve_especialidad_hay(PDO $hay, PDO $leg, array $g): ?int
{
    if (!empty($g['id_especialidad'])) {
        $id = legacy_resolve_hay_id($hay, 'especialidad', (int) $g['id_especialidad']);
        if ($id !== null) {
            return $id;
        }
    }
    $nombre = trim((string) ($g['especialidad'] ?? ''));
    if ($nombre === '' || !legacy_import_table_exists($leg, 'especialidades')) {
        return null;
    }
    $st = $leg->prepare(
        'SELECT id FROM especialidades
         WHERE UPPER(TRIM(nombre)) = UPPER(TRIM(?))
            OR UPPER(TRIM(nombre)) LIKE UPPER(TRIM(?))
         ORDER BY id LIMIT 1'
    );
    $st->execute([$nombre, $nombre . '%']);
    $lid = $st->fetchColumn();
    if ($lid === false) {
        return null;
    }
    return legacy_resolve_hay_id($hay, 'especialidad', (int) $lid);
}

/** Actualiza id_especialidad en grupos HAY ya importados según equivalencias. */
function legacy_import_fase_grupos_remap_especialidad(PDO $hay, PDO $leg, bool $dryRun = false): array
{
    $upd = $sinMap = $sinEsp = 0;
    $maps = $hay->query(
        "SELECT id_legacy, id_hay FROM hay_legacy_map WHERE entidad = 'grupo'"
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($maps === []) {
        legacy_import_log($hay, 'grupos_remap_esp', 'warn', 'No hay grupos mapeados en HAY');
        return ['inserted' => 0, 'skipped' => 0, 'errors' => 0];
    }
    $cols = legacy_import_select_cols($leg, 'grupos', [
        'id', 'id_especialidad', 'especialidad',
    ]);
    $stLeg = $leg->prepare("SELECT {$cols} FROM grupos WHERE id = ? LIMIT 1");
    $stUpd = $hay->prepare('UPDATE grupos SET id_especialidad = ? WHERE id_grupo = ?');
    foreach ($maps as $m) {
        $stLeg->execute([(int) $m['id_legacy']]);
        $g = $stLeg->fetch(PDO::FETCH_ASSOC);
        if (!$g) {
            $sinMap++;
            continue;
        }
        $idEspHay = legacy_grupo_resolve_especialidad_hay($hay, $leg, $g);
        if ($idEspHay === null) {
            $sinEsp++;
            continue;
        }
        if (!$dryRun) {
            $stUpd->execute([$idEspHay, (int) $m['id_hay']]);
            $upd++;
        }
    }
    legacy_import_log(
        $hay,
        'grupos_remap_esp',
        'info',
        "Grupos: $upd especialidad actualizada, $sinEsp sin equivalencia, $sinMap sin fila legado"
    );
    return ['inserted' => $upd, 'skipped' => $sinEsp + $sinMap, 'errors' => 0];
}

/** @return array{inserted:int,skipped:int,errors:int} */
function legacy_import_fase_grupos(PDO $hay, PDO $leg, bool $dryRun = false): array
{
    $fase = 'grupo';
    $ins = $skipped = $errors = 0;
    asistencia_ensure_schema($hay);
    $cols = legacy_import_select_cols($leg, 'grupos', [
        'id',
        'id_sucursal',
        'fecha_inicio',
        'horario',
        'dias',
        'horario_texto',
        'especialidad',
        'clave',
        'id_especialidad',
        'id_profesor',
        'aula',
        'status',
    ]);
    $hasClave = legacy_import_column_exists($leg, 'grupos', 'clave');
    $hasIdEsp = legacy_import_column_exists($leg, 'grupos', 'id_especialidad');
    $hasProf = legacy_import_column_exists($leg, 'grupos', 'id_profesor');
    $rows = $leg->query("SELECT {$cols} FROM grupos ORDER BY id")->fetchAll();
    foreach ($rows as $g) {
        $lid = (int) $g['id'];
        if (legacy_map_get($hay, $fase, $lid) !== null) {
            $skipped++;
            continue;
        }
        $idPlantel = legacy_resolve_hay_id($hay, 'plantel', (int) ($g['id_sucursal'] ?? 0)) ?? plantel_default_id($hay);
        $clave = $hasClave && !empty($g['clave'])
            ? trim((string) $g['clave'])
            : ('LEG-G' . $lid);
        $dup = $hay->prepare('SELECT id_grupo FROM grupos WHERE clave = ? LIMIT 1');
        $dup->execute([$clave]);
        if ($dup->fetchColumn()) {
            $clave = $clave . '-L' . $lid;
        }
        $idEsp = legacy_grupo_resolve_especialidad_hay($hay, $leg, $g);
        $idProf = null;
        if ($hasProf && !empty($g['id_profesor'])) {
            $idProf = legacy_resolve_hay_id($hay, 'usuario', (int) $g['id_profesor']);
        }
        $horario = trim((string) ($g['horario_texto'] ?? ''));
        if ($horario === '') {
            $horario = trim((string) ($g['horario'] ?? '') . ' ' . (string) ($g['dias'] ?? ''));
        }
        $fecha = $g['fecha_inicio'] ?? date('Y-m-d');
        if (!$dryRun) {
            try {
                $hay->prepare(
                    'INSERT INTO grupos (
                        id_plantel, clave, fecha_inicio, id_profesor, aula, id_especialidad,
                        horario_texto, codigo_area, es_extensivo, es_personalizado
                     ) VALUES (?,?,?,?,?,?,?,?,0,0)'
                )->execute([
                    $idPlantel,
                    $clave,
                    substr((string) $fecha, 0, 10),
                    $idProf,
                    null,
                    $idEsp,
                    $horario !== '' ? $horario : null,
                    'LEG',
                ]);
                legacy_map_set($hay, $fase, $lid, (int) $hay->lastInsertId());
                $ins++;
            } catch (PDOException $e) {
                $errors++;
                legacy_import_log($hay, 'grupos', 'error', $e->getMessage(), $lid);
            }
        }
    }
    legacy_import_log($hay, 'grupos', 'info', "Grupos: $ins nuevos, $skipped omitidos, $errors errores");
    return ['inserted' => $ins, 'skipped' => $skipped, 'errors' => $errors];
}

function legacy_import_is_preregistro_row(array $a): bool
{
    $st = trim((string) ($a['status'] ?? ''));
    return $st === '' || strcasecmp($st, 'Pre-Registro') === 0;
}

function legacy_import_is_alumno_row(array $a): bool
{
    return strcasecmp(trim((string) ($a['status'] ?? '')), 'Alumno') === 0;
}

/** @return array{inserted:int,skipped:int,errors:int} */
function legacy_import_fase_preregistros(PDO $hay, PDO $leg, bool $dryRun = false): array
{
    $fase = 'preregistro';
    $ins = $skipped = $errors = 0;
    preregistro_ensure_schema($hay);
    $del = legacy_import_column_exists($leg, 'alumnos', 'deleted_at') ? ' WHERE deleted_at IS NULL' : '';
    $rows = $leg->query('SELECT * FROM alumnos' . $del)->fetchAll();
    foreach ($rows as $a) {
        if (!legacy_import_is_preregistro_row($a)) {
            continue;
        }
        $lid = (int) $a['id'];
        if (legacy_map_get($hay, $fase, $lid) !== null) {
            $skipped++;
            continue;
        }
        $idPlantel = legacy_resolve_hay_id($hay, 'plantel', (int) ($a['id_sucursal'] ?? 0)) ?? plantel_default_id($hay);
        $idReg = legacy_map_get($hay, 'usuario', (int) ($a['id_asesor_educativo'] ?? 0))
            ?? (int) ($_SESSION['user_id'] ?? 1);
        if ($idReg <= 0) {
            $idReg = 1;
        }
        $idEsp = !empty($a['id_especialidad'])
            ? legacy_resolve_hay_id($hay, 'especialidad', (int) $a['id_especialidad'])
            : null;
        if (!$dryRun) {
            $hay->prepare(
                'INSERT INTO preregistros (
                    id_plantel, id_usuario_registro, id_especialidad, estado,
                    nombres, apellido_paterno, apellido_materno, fecha_nacimiento, edad,
                    medio_entero, medio_entero_otro, domicilio, colonia, municipio,
                    telefono, telefono2, email, codigo_postal, ocupacion, grado_estudios,
                    padre_tutor, objetivo_inscripcion, enfermedad_cronica, enfermedad_detalle,
                    observaciones, requiere_factura, factura_rfc, factura_curp, factura_telefono,
                    factura_razon_social, factura_correo, factura_domicilio_fiscal, creado_en
                 ) VALUES (
                    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
                 )'
            )->execute([
                $idPlantel,
                $idReg,
                $idEsp,
                'pendiente',
                trim((string) ($a['nombres'] ?? '')),
                trim((string) ($a['apellido_paterno'] ?? '')),
                trim((string) ($a['apellido_materno'] ?? '')),
                $a['fecha_nacimiento'] ?? null,
                isset($a['edad']) ? (int) $a['edad'] : null,
                legacy_import_map_medio($a['como_supiste_nosotros'] ?? null),
                null,
                trim((string) ($a['domicilio'] ?? '')),
                trim((string) ($a['colonia'] ?? '')),
                trim((string) ($a['municipio'] ?? '')),
                trim((string) ($a['telefono'] ?? $a['celular'] ?? '')),
                trim((string) ($a['celular'] ?? '')),
                trim((string) ($a['email'] ?? '')),
                trim((string) ($a['codigo_postal'] ?? '')),
                trim((string) ($a['ocupacion'] ?? '')),
                legacy_import_map_grado($a['grado_estudios'] ?? null),
                trim((string) ($a['tutor'] ?? '')),
                trim((string) ($a['objetivo_inscripcion'] ?? '')),
                !empty($a['enfermedad_cronica']) ? 1 : 0,
                is_string($a['enfermedad_cronica'] ?? null) ? $a['enfermedad_cronica'] : null,
                trim((string) ($a['observaciones'] ?? '')),
                !empty($a['solicitud_factura']) ? 1 : 0,
                trim((string) ($a['rfc'] ?? '')),
                trim((string) ($a['curp'] ?? '')),
                trim((string) ($a['telefono_general'] ?? '')),
                trim((string) ($a['razon_social'] ?? '')),
                trim((string) ($a['correo_general'] ?? '')),
                trim((string) ($a['domicilio_fiscal'] ?? '')),
                $a['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
            legacy_map_set($hay, $fase, $lid, (int) $hay->lastInsertId());
            $ins++;
        }
    }
    legacy_import_log($hay, 'preregistros', 'info', "Pre-registros: $ins nuevos, $skipped omitidos");
    return ['inserted' => $ins, 'skipped' => $skipped, 'errors' => $errors];
}

/** @return array{inserted:int,skipped:int,errors:int} */
function legacy_import_fase_alumnos(PDO $hay, PDO $leg, bool $dryRun = false): array
{
    $fase = 'alumno';
    $ins = $skipped = $errors = 0;
    alumno_ensure_schema($hay);
    $del = legacy_import_column_exists($leg, 'alumnos', 'deleted_at') ? ' WHERE deleted_at IS NULL' : '';
    $rows = $leg->query('SELECT * FROM alumnos' . $del)->fetchAll();
    foreach ($rows as $a) {
        if (!legacy_import_is_alumno_row($a)) {
            continue;
        }
        $lid = (int) $a['id'];
        if (legacy_map_get($hay, $fase, $lid) !== null) {
            $skipped++;
            continue;
        }
        $nc = trim((string) ($a['nuevo_numero_control'] ?? $a['numero_control'] ?? ''));
        if ($nc === '') {
            $errors++;
            legacy_import_log($hay, 'alumnos', 'warn', 'Alumno sin número de control', $lid);
            continue;
        }
        $dup = $hay->prepare('SELECT id_alumno FROM alumnos WHERE numero_control = ? LIMIT 1');
        $dup->execute([$nc]);
        if ($dup->fetchColumn()) {
            $skipped++;
            legacy_map_set($hay, $fase, $lid, (int) $dup->fetchColumn(), 'dup_nc');
            continue;
        }
        $idPlantel = legacy_resolve_hay_id($hay, 'plantel', (int) ($a['id_sucursal'] ?? 0)) ?? plantel_default_id($hay);
        $idEsp = !empty($a['id_especialidad'])
            ? legacy_resolve_hay_id($hay, 'especialidad', (int) $a['id_especialidad'])
            : null;
        $idPrereg = legacy_map_get($hay, 'preregistro', $lid);
        $forma = in_array(strtolower((string) ($a['forma_pago'] ?? 'mensual')), ['semanal'], true)
            ? 'semanal' : 'mensual';
        if (!$dryRun) {
            $hay->prepare(
                'INSERT INTO alumnos (
                    id_plantel, numero_control, nombres, apellido_paterno, apellido_materno,
                    foto, estado, forma_pago, id_especialidad, id_preregistro, email, telefono,
                    fecha_alta, codigo_huella
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $idPlantel,
                $nc,
                trim((string) ($a['nombres'] ?? '')),
                trim((string) ($a['apellido_paterno'] ?? '')),
                trim((string) ($a['apellido_materno'] ?? '')),
                trim((string) ($a['foto'] ?? '')),
                'activo',
                $forma,
                $idEsp,
                $idPrereg,
                trim((string) ($a['email'] ?? '')),
                trim((string) ($a['telefono'] ?? $a['celular'] ?? '')),
                substr((string) ($a['created_at'] ?? date('Y-m-d')), 0, 10),
                $nc,
            ]);
            legacy_map_set($hay, $fase, $lid, (int) $hay->lastInsertId());
            $ins++;
        }
    }
    legacy_import_log($hay, 'alumnos', 'info', "Alumnos: $ins nuevos, $skipped omitidos, $errors sin control");
    return ['inserted' => $ins, 'skipped' => $skipped, 'errors' => $errors];
}

/** @return array{inserted:int,skipped:int,errors:int} */
function legacy_import_fase_alumno_grupos(PDO $hay, PDO $leg, bool $dryRun = false): array
{
    $ins = $skipped = 0;
    if (!legacy_import_table_exists($leg, 'alumnos_grupos')) {
        return ['inserted' => 0, 'skipped' => 0, 'errors' => 0];
    }
    $agCols = legacy_import_select_cols($leg, 'alumnos_grupos', [
        'id',
        'id_alumno',
        'id_grupo',
        'fecha_inicio',
        'status',
        'fecha_final',
    ]);
    $hasStatus = legacy_import_column_exists($leg, 'alumnos_grupos', 'status');
    $where = $hasStatus
        ? " WHERE status IS NULL OR status = '' OR status = 'Inscrito'"
        : '';
    $rows = $leg->query("SELECT {$agCols} FROM alumnos_grupos{$where}")->fetchAll();
    foreach ($rows as $r) {
        $idAl = legacy_map_get($hay, 'alumno', (int) $r['id_alumno']);
        $idGr = legacy_map_get($hay, 'grupo', (int) $r['id_grupo']);
        if ($idAl === null || $idGr === null) {
            $skipped++;
            continue;
        }
        if (!$dryRun) {
            $hay->prepare(
                'INSERT IGNORE INTO alumno_grupos (id_alumno, id_grupo, activo, fecha_inicio)
                 VALUES (?,?,1,?)'
            )->execute([
                $idAl,
                $idGr,
                $r['fecha_inicio'] ?? date('Y-m-d'),
            ]);
            $hay->prepare('UPDATE alumnos SET id_grupo = ? WHERE id_alumno = ? AND id_grupo IS NULL')
                ->execute([$idGr, $idAl]);
            $ins++;
        }
    }
    legacy_import_log($hay, 'alumno_grupos', 'info', "Relaciones grupo: $ins, omitidos $skipped");
    return ['inserted' => $ins, 'skipped' => $skipped, 'errors' => 0];
}

/** @return array{inserted:int,skipped:int,errors:int} */
function legacy_import_fase_alumno_especialidades(PDO $hay, PDO $leg, bool $dryRun = false): array
{
    $ins = $skipped = 0;
    pago_ensure_schema($hay);
    if (!legacy_import_table_exists($leg, 'alumnos_especialidades')) {
        return ['inserted' => 0, 'skipped' => 0, 'errors' => 0];
    }
    $rows = $leg->query('SELECT * FROM alumnos_especialidades ORDER BY id')->fetchAll();
    foreach ($rows as $r) {
        $idAl = legacy_map_get($hay, 'alumno', (int) ($r['id_alumno'] ?? 0));
        $idEsp = legacy_map_get($hay, 'especialidad', (int) ($r['id_especialidad'] ?? 0));
        if ($idAl === null || $idEsp === null) {
            $skipped++;
            continue;
        }
        $forma = in_array(strtolower((string) ($r['forma_pago'] ?? 'mensual')), ['semanal'], true)
            ? 'semanal' : 'mensual';
        if (!$dryRun) {
            $hay->prepare(
                'INSERT INTO alumno_especialidades (
                    id_alumno, id_especialidad, forma_pago, fecha_inscripcion,
                    costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal, activo
                 ) VALUES (?,?,?,?,?,?,?,?,1)
                 ON DUPLICATE KEY UPDATE forma_pago = VALUES(forma_pago)'
            )->execute([
                $idAl,
                $idEsp,
                $forma,
                substr((string) ($r['fecha_inicio'] ?? date('Y-m-d')), 0, 10),
                (float) ($r['monto'] ?? 0),
                (float) ($r['monto'] ?? 0),
                (float) ($r['monto_pronto_pago'] ?? 0),
                (float) ($r['monto'] ?? 0),
            ]);
            $ins++;
        }
    }
    legacy_import_log($hay, 'alumno_especialidades', 'info', "Alumno-especialidad: $ins filas");
    return ['inserted' => $ins, 'skipped' => $skipped, 'errors' => 0];
}

function legacy_import_pago_insert(
    PDO $hay,
    bool $dryRun,
    string $fase,
    int $legacyKey,
    int $idAl,
    array $r,
    string $suffix
): bool {
    if (legacy_map_get($hay, $fase, $legacyKey) !== null) {
        return false;
    }
    $concepto = trim((string) ($r['concepto'] ?? 'Importación legado'));
    $tipo = stripos($concepto, 'inscripcion') !== false ? 'inscripcion'
        : (stripos($concepto, 'colegiatura') !== false ? 'mensualidad' : 'abono');
    $idUser = legacy_resolve_hay_id($hay, 'usuario', (int) ($r['id_recibio'] ?? 0));
    $monto = (float) ($r['monto'] ?? $r['monto_abono'] ?? 0);
    if ($monto <= 0) {
        return false;
    }
    if (!$dryRun) {
        $hay->prepare(
            'INSERT INTO alumno_pagos (
                id_alumno, folio, monto, forma_pago, concepto, tipo, id_usuario, creado_en
             ) VALUES (?,?,?,?,?,?,?,?)'
        )->execute([
            $idAl,
            'LEG-' . ($r['folio'] ?? $legacyKey),
            $monto,
            'Efectivo',
            ($concepto !== '' ? $concepto : 'Pago legado') . $suffix,
            $tipo,
            $idUser,
            $r['fecha'] ?? $r['abono_en'] ?? $r['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        legacy_map_set($hay, $fase, $legacyKey, (int) $hay->lastInsertId());
    }
    return true;
}

/** Importa pagos del legado (abonos + pagos sin abono desglosado). */
function legacy_import_fase_pagos(PDO $hay, PDO $leg, bool $dryRun = false): array
{
    $fase = 'pago';
    $ins = $skipped = $sinAlumno = 0;
    pago_ensure_schema($hay);
    if (!legacy_import_table_exists($leg, 'pagos')) {
        legacy_import_log($hay, 'pagos', 'warn', 'Tabla pagos no encontrada en legado');
        return ['inserted' => 0, 'skipped' => 0, 'errors' => 0];
    }
    $pgDel = legacy_import_column_exists($leg, 'pagos', 'deleted_at') ? ' WHERE p.deleted_at IS NULL' : '';

    if (legacy_import_table_exists($leg, 'abonos')) {
        $abDel = legacy_import_column_exists($leg, 'abonos', 'deleted_at') ? ' AND a.deleted_at IS NULL' : '';
        $sql = "SELECT a.id AS id_abono, a.monto AS monto_abono, a.created_at AS abono_en,
                       p.id AS id_pago, p.folio, p.fecha, p.id_alumno, p.id_recibio,
                       ap.concepto, p.monto
                FROM abonos a
                INNER JOIN pagos p ON p.id = a.id_pago
                LEFT JOIN alumnos_pagos ap ON ap.id = a.id_alumno_pago
                WHERE 1=1 $abDel" . (legacy_import_column_exists($leg, 'pagos', 'deleted_at') ? ' AND p.deleted_at IS NULL' : '') . '
                ORDER BY a.id';
        foreach ($leg->query($sql)->fetchAll() as $r) {
            $lid = (int) $r['id_abono'];
            $idAl = legacy_resolve_hay_id($hay, 'alumno', (int) ($r['id_alumno'] ?? 0));
            if ($idAl === null) {
                $sinAlumno++;
                continue;
            }
            if (legacy_import_pago_insert($hay, $dryRun, $fase, $lid, $idAl, $r, ' [abono legado #' . $lid . ']')) {
                $ins++;
            } else {
                $skipped++;
            }
        }
    }

    $wherePagos = ['NOT EXISTS (SELECT 1 FROM abonos a2 WHERE a2.id_pago = p.id)'];
    if (legacy_import_column_exists($leg, 'pagos', 'deleted_at')) {
        $wherePagos[] = 'p.deleted_at IS NULL';
    }
    $sqlPagos = 'SELECT p.id AS id_pago, p.folio, p.fecha, p.monto, p.id_alumno, p.id_recibio, p.created_at
                 FROM pagos p WHERE ' . implode(' AND ', $wherePagos) . ' ORDER BY p.id';
    foreach ($leg->query($sqlPagos)->fetchAll() as $r) {
        $lid = (int) $r['id_pago'];
        $mapKey = 1000000000 + $lid;
        if (legacy_map_get($hay, $fase, $mapKey) !== null || legacy_map_get($hay, $fase, $lid) !== null) {
            $skipped++;
            continue;
        }
        $idAl = legacy_resolve_hay_id($hay, 'alumno', (int) ($r['id_alumno'] ?? 0));
        if ($idAl === null) {
            $sinAlumno++;
            continue;
        }
        $r['concepto'] = 'Pago legado folio ' . ($r['folio'] ?? $lid);
        if (legacy_import_pago_insert($hay, $dryRun, $fase, $mapKey, $idAl, $r, ' [pago legado #' . $lid . ']')) {
            $ins++;
        } else {
            $skipped++;
        }
    }

    legacy_import_log(
        $hay,
        'pagos',
        'info',
        "Pagos: $ins importados, $skipped omitidos, $sinAlumno sin alumno mapeado"
    );
    return ['inserted' => $ins, 'skipped' => $skipped, 'errors' => $sinAlumno];
}

/**
 * @return array<string, array{inserted:int,skipped:int,errors:int}>
 */
function legacy_import_run(PDO $hay, PDO $leg, string $fase = 'all', bool $dryRun = false): array
{
    legacy_import_ensure_schema($hay);
    hay_bootstrap_schema($hay);

    $fases = [
        'planteles' => 'legacy_import_fase_planteles',
        'especialidades' => 'legacy_import_fase_especialidades',
        'usuarios' => 'legacy_import_fase_usuarios',
        'productos' => 'legacy_import_fase_productos',
        'grupos' => 'legacy_import_fase_grupos',
        'grupos_remap_esp' => 'legacy_import_fase_grupos_remap_especialidad',
        'preregistros' => 'legacy_import_fase_preregistros',
        'alumnos' => 'legacy_import_fase_alumnos',
        'alumno_grupos' => 'legacy_import_fase_alumno_grupos',
        'alumno_especialidades' => 'legacy_import_fase_alumno_especialidades',
        'pagos' => 'legacy_import_fase_pagos',
    ];

    $run = $fase === 'all' ? array_keys($fases) : [$fase];
    $out = [];
    foreach ($run as $name) {
        if (!isset($fases[$name])) {
            throw new InvalidArgumentException('Fase desconocida: ' . $name);
        }
        $fn = $fases[$name];
        $out[$name] = $fn($hay, $leg, $dryRun);
    }
    return $out;
}
