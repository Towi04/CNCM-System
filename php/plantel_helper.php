<?php

/**
 * Planteles: esquema, sesión activa y filtros por sede.
 */

function plantel_ensure_schema(PDO $pdo): void
{
    if (function_exists('hay_schema_ddl_habilitado') && !hay_schema_ddl_habilitado($pdo)) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS planteles (
            id_plantel INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(40) NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_plantel),
            UNIQUE KEY uq_planteles_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    plantel_ensure_column($pdo, 'planteles', 'razon_social', "VARCHAR(160) NOT NULL DEFAULT 'GRUPO EDUCATIVO CNCM'", 'nombre');
    plantel_ensure_column($pdo, 'planteles', 'direccion', 'VARCHAR(255) NULL', 'razon_social');
    plantel_ensure_column($pdo, 'planteles', 'rfc', 'VARCHAR(20) NULL', 'direccion');
    plantel_ensure_column($pdo, 'planteles', 'telefono', 'VARCHAR(30) NULL', 'rfc');
    plantel_ensure_column($pdo, 'planteles', 'email_contacto', "VARCHAR(120) NULL DEFAULT 'corporativo@cncm.com.mx'", 'telefono');
    plantel_ensure_column($pdo, 'planteles', 'logo_url', 'VARCHAR(255) NULL', 'email_contacto');

    $count = (int) $pdo->query('SELECT COUNT(*) FROM planteles')->fetchColumn();
    if ($count === 0) {
        $ins = $pdo->prepare('INSERT INTO planteles (slug, nombre, orden) VALUES (?, ?, ?)');
        foreach ([
            ['salamanca', 'Plantel Salamanca', 1],
            ['celaya', 'Plantel Celaya', 2],
            ['guerrero', 'Plantel Guerrero', 3],
            ['fuentes', 'Plantel Fuentes', 4],
        ] as $row) {
            $ins->execute($row);
        }
    }

    plantel_seed_datos_ticket($pdo);

    plantel_ensure_column($pdo, 'grupos', 'id_plantel', 'INT UNSIGNED NULL', 'id_grupo');
    plantel_ensure_column($pdo, 'alumnos', 'id_plantel', 'INT UNSIGNED NULL', 'id_alumno');
    plantel_ensure_column($pdo, 'usuarios', 'id_plantel', 'INT UNSIGNED NULL', 'id_usuario');
    plantel_ensure_column($pdo, 'asesoria_disp', 'id_plantel', 'INT UNSIGNED NULL', 'id');

    $defaultId = plantel_default_id($pdo);

    if (hay_meta_get($pdo, 'plantel_backfill_done') !== '1') {
        $pdo->exec(
            'UPDATE grupos SET id_plantel = ' . (int) $defaultId . ' WHERE id_plantel IS NULL'
        );
        $pdo->exec(
            'UPDATE alumnos a INNER JOIN grupos g ON g.id_grupo = a.id_grupo
             SET a.id_plantel = g.id_plantel WHERE a.id_plantel IS NULL OR a.id_plantel <> g.id_plantel'
        );
        $pdo->exec(
            'UPDATE alumnos a
             INNER JOIN (
                SELECT ag.id_alumno, MIN(g.id_plantel) AS id_plantel
                FROM alumno_grupos ag
                INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
                WHERE ag.activo = 1
                GROUP BY ag.id_alumno
             ) x ON x.id_alumno = a.id_alumno
             SET a.id_plantel = x.id_plantel
             WHERE a.id_plantel IS NULL'
        );
        $pdo->exec(
            'UPDATE usuarios SET id_plantel = ' . (int) $defaultId
            . " WHERE id_plantel IS NULL AND rol <> 'admin'"
        );

        if (plantel_table_exists($pdo, 'exam_generados')) {
            $pdo->exec(
                'UPDATE exam_generados SET id_plantel = ' . (int) $defaultId . ' WHERE id_plantel IS NULL'
            );
        }
        hay_meta_set($pdo, 'plantel_backfill_done', '1');
    }

    if (plantel_table_exists($pdo, 'exam_generados')) {
        plantel_ensure_column($pdo, 'exam_generados', 'id_plantel', 'INT UNSIGNED NULL', 'id_examen');
    }
}

function plantel_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function plantel_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (!plantel_table_exists($pdo, $table)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);

    return (bool) $stmt->fetchColumn();
}

function plantel_ensure_column(
    PDO $pdo,
    string $table,
    string $column,
    string $definition,
    string $after
): void {
    if (!plantel_table_exists($pdo, $table)) {
        return;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    if ($stmt->fetchColumn()) {
        return;
    }

    // Varios módulos antiguos ponen "AFTER col" dentro de $definition; normalizar.
    if (preg_match('/\s+AFTER\s+`?([a-zA-Z0-9_]+)`?\s*$/i', $definition, $m)) {
        $definition = preg_replace('/\s+AFTER\s+`?[a-zA-Z0-9_]+`?\s*$/i', '', $definition);
        $after = $m[1];
    }

    $definition = preg_replace('/\s+/', ' ', trim($definition));
    $afterClause = '';
    if ($after !== '') {
        $stmt->execute([$table, $after]);
        if ($stmt->fetchColumn()) {
            $afterClause = ' AFTER `' . str_replace('`', '', $after) . '`';
        }
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}{$afterClause}");
}

function plantel_default_id(PDO $pdo): int
{
    $stmt = $pdo->prepare('SELECT id_plantel FROM planteles WHERE slug = ? LIMIT 1');
    $stmt->execute(['salamanca']);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    return (int) $pdo->query('SELECT id_plantel FROM planteles ORDER BY orden LIMIT 1')->fetchColumn();
}

/** @return array<int, array{id_plantel:int, slug:string, nombre:string}> */
function plantel_list(PDO $pdo, bool $soloActivos = true): array
{
    $sql = 'SELECT id_plantel, slug, nombre, activo, orden FROM planteles';
    if ($soloActivos) {
        $sql .= ' WHERE activo = 1';
    }
    $sql .= ' ORDER BY orden ASC, nombre ASC';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id_plantel' => (int) $r['id_plantel'],
            'slug' => $r['slug'],
            'nombre' => $r['nombre'],
            'activo' => (int) $r['activo'],
            'orden' => (int) $r['orden'],
        ];
    }
    return $out;
}

/** @return array<string, string> slug => nombre (activos) */
function plantel_catalog_nombres(PDO $pdo): array
{
    $out = [];
    foreach (plantel_list($pdo, true) as $p) {
        $out[$p['slug']] = $p['nombre'];
    }
    return $out;
}

/** Valores de ticket para plantel Salamanca (referencia producción). */
function plantel_seed_datos_ticket(PDO $pdo): void
{
    $defaults = [
        'salamanca' => [
            'razon_social' => 'GRUPO EDUCATIVO CNCM',
            'direccion' => 'Portal de los Bravo #121, Col. Centro, SALAMANCA, Guanajuato',
            'rfc' => '11PBT0170N',
            'telefono' => '4641130666',
            'email_contacto' => 'corporativo@cncm.com.mx',
        ],
    ];
    foreach ($defaults as $slug => $data) {
        $st = $pdo->prepare('SELECT id_plantel, direccion FROM planteles WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || trim((string) ($row['direccion'] ?? '')) !== '') {
            continue;
        }
        $pdo->prepare(
            'UPDATE planteles SET razon_social = ?, direccion = ?, rfc = ?, telefono = ?, email_contacto = ?
             WHERE id_plantel = ?'
        )->execute([
            $data['razon_social'],
            $data['direccion'],
            $data['rfc'],
            $data['telefono'],
            $data['email_contacto'],
            (int) $row['id_plantel'],
        ]);
    }
}

/** Slugs con imagen de fondo en src/{slug}.png */
function plantel_fondo_slugs(): array
{
    return ['salamanca', 'celaya', 'guerrero', 'fuentes'];
}

/** URL de fondo visual del plantel activo (null si no hay imagen). */
function plantel_fondo_imagen(?string $slug = null): ?string
{
    $slug = strtolower(trim($slug ?? (string) ($_SESSION['plantel_slug'] ?? '')));
    if ($slug === '' || !in_array($slug, plantel_fondo_slugs(), true)) {
        return null;
    }
    $rel = 'src/' . $slug . '.png';
    $abs = dirname(__DIR__) . '/' . $rel;
    if (!is_file($abs)) {
        return null;
    }

    return function_exists('hay_asset_url') ? hay_asset_url($rel) : $rel;
}

/** Clases CSS para el área de contenido según plantel. */
function plantel_fondo_clases(?string $slug = null): string
{
    $slug = strtolower(trim($slug ?? (string) ($_SESSION['plantel_slug'] ?? '')));
    if ($slug === '' || plantel_fondo_imagen($slug) === null) {
        return '';
    }

    return 'plantel-fondo plantel-fondo--' . preg_replace('/[^a-z0-9\-]/', '', $slug);
}

/** @return array<string, mixed> Datos de encabezado/pie para ticket térmico. */
function plantel_datos_ticket(?array $plantel): array
{
    $nombre = trim((string) ($plantel['nombre'] ?? 'Plantel CNCM'));
    $logoRel = trim((string) ($plantel['logo_url'] ?? ''));
    if ($logoRel === '') {
        $logoRel = 'src/logo.png';
    }
    $logoUrl = function_exists('hay_asset_url') ? hay_asset_url($logoRel) : $logoRel;

    return [
        'nombre' => $nombre,
        'razon_social' => trim((string) ($plantel['razon_social'] ?? '')) ?: 'GRUPO EDUCATIVO CNCM',
        'direccion' => trim((string) ($plantel['direccion'] ?? '')),
        'rfc' => trim((string) ($plantel['rfc'] ?? '')),
        'telefono' => trim((string) ($plantel['telefono'] ?? '')),
        'email_contacto' => trim((string) ($plantel['email_contacto'] ?? '')) ?: 'corporativo@cncm.com.mx',
        'logo_url' => $logoUrl,
    ];
}

function plantel_find(PDO $pdo, int|string $idOrSlug): ?array
{
    if (is_numeric($idOrSlug)) {
        $stmt = $pdo->prepare('SELECT * FROM planteles WHERE id_plantel = ? LIMIT 1');
        $stmt->execute([(int) $idOrSlug]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM planteles WHERE slug = ? LIMIT 1');
        $stmt->execute([trim((string) $idOrSlug)]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function plantel_id_activo(): int
{
    if (!empty($_SESSION['plantel_id']) && is_numeric($_SESSION['plantel_id'])) {
        return (int) $_SESSION['plantel_id'];
    }
    return 0;
}

/** ID del plantel en sesión; nunca 0 si hay planteles en BD. */
function plantel_scope_id(PDO $pdo): int
{
    plantel_inicializar_sesion($pdo);
    $id = plantel_id_activo();
    if ($id > 0) {
        return $id;
    }
    return plantel_default_id($pdo);
}

function plantel_usuario_home_id(PDO $pdo, ?int $idUsuario = null): int
{
    $idUsuario = $idUsuario ?? (int) ($_SESSION['user_id'] ?? 0);
    if ($idUsuario <= 0) {
        return 0;
    }
    $st = $pdo->prepare('SELECT id_plantel FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$idUsuario]);
    return (int) ($st->fetchColumn() ?: 0);
}

/** Planteles adicionales temporales asignados a un usuario (cobertura en otra sede). */
function plantel_usuario_planteles_extra(PDO $pdo, ?int $idUsuario = null): array
{
    $idUsuario = $idUsuario ?? (int) ($_SESSION['user_id'] ?? 0);
    if ($idUsuario <= 0) {
        return [];
    }
    if (!function_exists('rbac_db_tablas_listas') || !rbac_db_tablas_listas($pdo)) {
        return [];
    }
    try {
        $st = $pdo->prepare(
            'SELECT id_plantel FROM usuario_planteles
             WHERE id_usuario = ? AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())
             ORDER BY id_plantel'
        );
        $st->execute([$idUsuario]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (PDOException $e) {
        return [];
    }
}

/** Roles que pueden recibir sedes temporales además de su plantel asignado. */
function plantel_roles_con_apoyo_temporal(): array
{
    return ['asesor', 'admin', 'coordinador'];
}

/** IDs de sedes que el rol efectivo puede usar; null = todas las activas. */
function plantel_ids_permitidos(PDO $pdo): ?array
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return null;
    }
    $alcance = $_SESSION['rbac_alcance_planteles'] ?? null;
    if ($alcance === 'todos') {
        return null;
    }

    $ids = [];
    if ($alcance === 'lista') {
        $lista = $_SESSION['rbac_planteles_ids'] ?? [];
        if (is_array($lista) && $lista !== []) {
            $ids = array_map('intval', $lista);
        } elseif (function_exists('rbac_rol_por_clave')) {
            $rol = rbac_rol_por_clave($pdo, rbac_rol_efectivo());
            if ($rol) {
                $ids = rbac_rol_planteles_ids($pdo, (int) $rol['id_rol']);
            }
        }
    } else {
        $home = plantel_usuario_home_id($pdo);
        if ($home > 0) {
            $ids[] = $home;
        }
    }

    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';
    if (in_array($rol, plantel_roles_con_apoyo_temporal(), true)) {
        $ids = array_merge($ids, plantel_usuario_planteles_extra($pdo));
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));

    return $ids !== [] ? $ids : [];
}

/** @return list<array{id_plantel:int, slug:string, nombre:string}> */
function plantel_list_accesibles(PDO $pdo, bool $soloActivos = true): array
{
    $todos = plantel_list($pdo, $soloActivos);
    $ids = plantel_ids_permitidos($pdo);
    if ($ids === null) {
        return $todos;
    }
    $set = array_flip($ids);

    return array_values(array_filter(
        $todos,
        static fn ($p) => isset($set[(int) $p['id_plantel']])
    ));
}

function plantel_puede_elegir_sede(PDO $pdo): bool
{
    return count(plantel_list_accesibles($pdo, true)) > 1;
}

/** Gerente/supervisor o rol con alcance múltiple pueden cambiar de sede. */
function plantel_puede_cambiar_a(PDO $pdo, int $idPlantel): bool
{
    if ($idPlantel <= 0) {
        return false;
    }
    $p = plantel_find($pdo, $idPlantel);
    if (!$p || (int) $p['activo'] !== 1) {
        return false;
    }
    $ids = plantel_ids_permitidos($pdo);
    if ($ids === null) {
        return true;
    }

    return in_array($idPlantel, $ids, true);
}

function plantel_usuario_pertenece(PDO $pdo, int $idUsuario, ?int $idPlantel = null): bool
{
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    if ($idUsuario <= 0 || $idPlantel <= 0) {
        return false;
    }
    $st = $pdo->prepare('SELECT id_plantel FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$idUsuario]);

    return (int) ($st->fetchColumn() ?: 0) === $idPlantel;
}

function plantel_enforce_alumno(PDO $pdo, int $idAlumno, ?int $idPlantel = null): bool
{
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    if ($idAlumno <= 0 || $idPlantel <= 0) {
        return false;
    }
    $st = $pdo->prepare('SELECT 1 FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1');
    $st->execute([$idAlumno, $idPlantel]);

    return (bool) $st->fetchColumn();
}

function plantel_enforce_grupo(PDO $pdo, int $idGrupo, ?int $idPlantel = null): bool
{
    return plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel ?? plantel_scope_id($pdo));
}

function plantel_restringir_sesion_usuario(PDO $pdo): void
{
    $activo = plantel_id_activo();
    if ($activo > 0 && plantel_puede_cambiar_a($pdo, $activo)) {
        return;
    }
    $permitidos = plantel_list_accesibles($pdo, true);
    if ($permitidos !== []) {
        $p = plantel_find($pdo, (int) $permitidos[0]['id_plantel']);
        if ($p) {
            plantel_set_sesion($p);
            return;
        }
    }
    $home = plantel_usuario_home_id($pdo);
    if ($home > 0) {
        $p = plantel_find($pdo, $home);
        if ($p) {
            plantel_set_sesion($p);
        }
    }
}

function plantel_set_sesion(array $plantel): void
{
    $_SESSION['plantel_id'] = (int) $plantel['id_plantel'];
    $_SESSION['plantel_slug'] = $plantel['slug'];
    $_SESSION['plantel_nombre'] = $plantel['nombre'];
}

function plantel_inicializar_sesion(PDO $pdo, ?int $preferido = null): void
{
    $omitirMigracion = defined('HAY_SKIP_SCHEMA_BOOTSTRAP') && HAY_SKIP_SCHEMA_BOOTSTRAP === true;
    if (!$omitirMigracion) {
        plantel_ensure_schema($pdo);
    }

    if ($preferido > 0) {
        $p = plantel_find($pdo, $preferido);
        if ($p && (int) $p['activo'] === 1) {
            plantel_set_sesion($p);
            return;
        }
    }

    if (!empty($_SESSION['plantel_id'])) {
        $p = plantel_find($pdo, $_SESSION['plantel_id']);
        if ($p && (int) $p['activo'] === 1) {
            plantel_set_sesion($p);
            return;
        }
    }

    $slug = $_SESSION['plantel_slug'] ?? $_SESSION['plantel_id'] ?? null;
    if (is_string($slug) && !is_numeric($slug)) {
        $p = plantel_find($pdo, $slug);
        if ($p && (int) $p['activo'] === 1) {
            plantel_set_sesion($p);
            return;
        }
    }

    $p = plantel_find($pdo, plantel_default_id($pdo));
    if ($p) {
        plantel_set_sesion($p);
    }

    plantel_restringir_sesion_usuario($pdo);
}

/** Puede operar en más de una sede o en todas (selector, altas en otra sede). */
function plantel_es_admin(): bool
{
    global $pdo;
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
    if (!($pdo instanceof PDO)) {
        return in_array($rol, ['admin', 'gerente'], true);
    }
    $ids = plantel_ids_permitidos($pdo);

    return $ids === null || count($ids) > 1;
}

function plantel_grupo_pertenece(PDO $pdo, int $idGrupo, ?int $idPlantel = null): bool
{
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    if ($idGrupo <= 0 || $idPlantel <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM grupos WHERE id_grupo = ? AND id_plantel = ? LIMIT 1');
    $stmt->execute([$idGrupo, $idPlantel]);
    return (bool) $stmt->fetchColumn();
}

function plantel_matricula_existe(PDO $pdo, string $matricula, int $idPlantel, int $excluirAlumno = 0): bool
{
    if ($matricula === '') {
        return false;
    }
    $sql = 'SELECT 1 FROM alumnos WHERE matricula = ? AND id_plantel = ?';
    $params = [$matricula, $idPlantel];
    if ($excluirAlumno > 0) {
        $sql .= ' AND id_alumno <> ?';
        $params[] = $excluirAlumno;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

/** Metadatos de instalación (evita migraciones pesadas en cada request). */
function hay_meta_ensure_table(PDO $pdo): void
{
    static $listo = false;
    if ($listo) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_app_meta (
            clave VARCHAR(64) NOT NULL,
            valor VARCHAR(255) NOT NULL,
            actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $listo = true;
}

function hay_meta_get(PDO $pdo, string $clave): ?string
{
    hay_meta_ensure_table($pdo);
    $st = $pdo->prepare('SELECT valor FROM hay_app_meta WHERE clave = ? LIMIT 1');
    $st->execute([$clave]);
    $v = $st->fetchColumn();

    return $v === false ? null : (string) $v;
}

function hay_meta_set(PDO $pdo, string $clave, string $valor): void
{
    hay_meta_ensure_table($pdo);
    $pdo->prepare(
        'INSERT INTO hay_app_meta (clave, valor) VALUES (?,?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
    )->execute([$clave, $valor]);
}
