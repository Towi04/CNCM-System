<?php

/**
 * Planeaciones de clase por grupo y fase (profesor).
 */

function planeacion_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS planeaciones (
            id_planeacion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo INT UNSIGNED NOT NULL,
            id_profesor INT NULL,
            id_fase INT UNSIGNED NULL,
            fecha DATE NOT NULL,
            anio SMALLINT UNSIGNED NOT NULL,
            semana TINYINT UNSIGNED NOT NULL,
            titulo VARCHAR(160) NOT NULL,
            contenido MEDIUMTEXT NOT NULL,
            estado ENUM(\'borrador\',\'enviada\',\'revisada\',\'observada\') NOT NULL DEFAULT \'enviada\',
            nota_revision TEXT NULL,
            id_revisor INT UNSIGNED NULL,
            revisado_en DATETIME NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_planeacion),
            KEY idx_plan_grupo_fecha (id_grupo, fecha),
            KEY idx_plan_grupo_anio_sem (id_grupo, anio, semana),
            KEY idx_plan_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    plantel_ensure_column($pdo, 'planeaciones', 'id_fase', 'INT UNSIGNED NULL', 'id_profesor');
    plantel_ensure_column($pdo, 'planeaciones', 'estado', "ENUM('borrador','enviada','revisada','observada') NOT NULL DEFAULT 'enviada'", 'contenido');
    plantel_ensure_column($pdo, 'planeaciones', 'nota_revision', 'TEXT NULL', 'estado');
    plantel_ensure_column($pdo, 'planeaciones', 'id_revisor', 'INT UNSIGNED NULL', 'nota_revision');
    plantel_ensure_column($pdo, 'planeaciones', 'revisado_en', 'DATETIME NULL', 'id_revisor');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS planeacion_observacion (
            id_obs BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_planeacion BIGINT UNSIGNED NOT NULL,
            id_usuario INT UNSIGNED NOT NULL,
            autor_rol VARCHAR(20) NOT NULL,
            comentario TEXT NOT NULL,
            es_reenvio TINYINT(1) NOT NULL DEFAULT 0,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_obs),
            KEY idx_plan_obs_plan (id_planeacion, creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function planeacion_puede_crear(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    $rol = rbac_rol_efectivo();
    if (in_array($rol, ['supervisor', 'gerente', 'admin', 'director', 'coordinador', 'coordinacion'], true)) {
        return true;
    }

    return $rol === 'profesor';
}

function planeacion_puede_revisar(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('planeaciones_revisar')) {
        return true;
    }
    $rol = rbac_rol_efectivo();

    return in_array($rol, ['coordinador', 'coordinacion', 'director', 'supervisor'], true);
}

function planeacion_autor_rol(): string
{
    if (profesor_portal_es_profesor()) {
        return 'profesor';
    }
    if (planeacion_puede_revisar()) {
        return 'coordinacion';
    }

    return rbac_rol_efectivo() ?: 'staff';
}

/** @return array<string, mixed>|null */
function planeacion_obtener_sin_plantel(PDO $pdo, int $idPlaneacion): ?array
{
    planeacion_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT p.*, g.clave AS grupo_clave, g.id_plantel,
                e.nombre AS esp_nombre,
                f.clave_fase, f.nombre_fase,
                CONCAT(up.nombre, \' \', up.apellido) AS profesor_nombre,
                CONCAT(ur.nombre, \' \', ur.apellido) AS revisor_nombre
         FROM planeaciones p
         INNER JOIN grupos g ON g.id_grupo = p.id_grupo
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN especialidad_fases f ON f.id_fase = p.id_fase
         LEFT JOIN usuarios up ON up.id_usuario = p.id_profesor
         LEFT JOIN usuarios ur ON ur.id_usuario = p.id_revisor
         WHERE p.id_planeacion = ?
         LIMIT 1'
    );
    $st->execute([$idPlaneacion]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function planeacion_puede_ver(PDO $pdo, int $idPlaneacion, int $idPlantel): bool
{
    $plan = planeacion_obtener($pdo, $idPlaneacion, $idPlantel);
    if (!$plan) {
        return false;
    }
    if (planeacion_puede_revisar()) {
        return true;
    }
    $idUsuario = (int) ($_SESSION['user_id'] ?? 0);

    return $idUsuario > 0 && (int) ($plan['id_profesor'] ?? 0) === $idUsuario;
}

function planeacion_puede_reenviar(PDO $pdo, int $idPlaneacion, int $idPlantel): bool
{
    if (!profesor_portal_es_profesor()) {
        return false;
    }
    $plan = planeacion_obtener($pdo, $idPlaneacion, $idPlantel);
    if (!$plan || (int) ($plan['id_profesor'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0)) {
        return false;
    }

    return in_array($plan['estado'] ?? '', ['observada', 'enviada', 'borrador'], true);
}

function planeacion_puede_comentar(PDO $pdo, int $idPlaneacion, int $idPlantel): bool
{
    return planeacion_puede_ver($pdo, $idPlaneacion, $idPlantel);
}

/** @return list<array<string, mixed>> */
function planeacion_observaciones_listar(PDO $pdo, int $idPlaneacion): array
{
    planeacion_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT o.*, CONCAT(u.nombre, \' \', u.apellido) AS autor_nombre
         FROM planeacion_observacion o
         LEFT JOIN usuarios u ON u.id_usuario = o.id_usuario
         WHERE o.id_planeacion = ?
         ORDER BY o.creado_en ASC, o.id_obs ASC'
    );
    $st->execute([$idPlaneacion]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($rows !== []) {
        return $rows;
    }

    $plan = planeacion_obtener_sin_plantel($pdo, $idPlaneacion);
    if ($plan && trim((string) ($plan['nota_revision'] ?? '')) !== '') {
        return [[
            'id_obs' => 0,
            'id_planeacion' => $idPlaneacion,
            'id_usuario' => (int) ($plan['id_revisor'] ?? 0),
            'autor_rol' => 'coordinacion',
            'comentario' => $plan['nota_revision'],
            'es_reenvio' => 0,
            'creado_en' => $plan['revisado_en'] ?? $plan['creado_en'] ?? date('Y-m-d H:i:s'),
            'autor_nombre' => $plan['revisor_nombre'] ?? 'Coordinación',
        ]];
    }

    return [];
}

function planeacion_observacion_agregar(
    PDO $pdo,
    int $idPlaneacion,
    int $idUsuario,
    string $autorRol,
    string $comentario,
    bool $esReenvio = false
): void {
    planeacion_ensure_schema($pdo);
    $comentario = trim($comentario);
    if ($comentario === '') {
        return;
    }
    $pdo->prepare(
        'INSERT INTO planeacion_observacion (id_planeacion, id_usuario, autor_rol, comentario, es_reenvio)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$idPlaneacion, $idUsuario, $autorRol, $comentario, $esReenvio ? 1 : 0]);
}

/** @return array{ok:bool,message:string} */
function planeacion_agregar_comentario(
    PDO $pdo,
    int $idPlaneacion,
    string $comentario,
    int $idUsuario,
    int $idPlantel,
    bool $marcarObservada = false
): array {
    if (!planeacion_puede_comentar($pdo, $idPlaneacion, $idPlantel)) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $comentario = trim($comentario);
    if ($comentario === '') {
        return ['ok' => false, 'message' => 'Escriba la observación'];
    }

    $plan = planeacion_obtener($pdo, $idPlaneacion, $idPlantel);
    if (!$plan) {
        return ['ok' => false, 'message' => 'Planeación no encontrada'];
    }

    $rol = planeacion_autor_rol();
    planeacion_observacion_agregar($pdo, $idPlaneacion, $idUsuario, $rol, $comentario);

    if ($marcarObservada && planeacion_puede_revisar() && ($plan['estado'] ?? '') !== 'observada') {
        $pdo->prepare(
            'UPDATE planeaciones SET estado = \'observada\', nota_revision = ?, id_revisor = ?, revisado_en = NOW()
             WHERE id_planeacion = ?'
        )->execute([$comentario, $idUsuario, $idPlaneacion]);
        planeacion_notificar_profesor_revision($pdo, $plan, 'observada', $comentario, $idUsuario);
    } elseif ($rol === 'coordinacion' && ($plan['estado'] ?? '') === 'enviada') {
        $pdo->prepare(
            'UPDATE planeaciones SET nota_revision = ?, id_revisor = ?, revisado_en = NOW() WHERE id_planeacion = ?'
        )->execute([$comentario, $idUsuario, $idPlaneacion]);
    } elseif ($rol === 'profesor' && (int) ($plan['id_profesor'] ?? 0) === $idUsuario) {
        planeacion_notificar_coordinacion_comentario($pdo, $idPlantel, $plan, $comentario);
    }

    return ['ok' => true, 'message' => 'Observación registrada.'];
}

/** @return array{ok:bool,message:string} */
function planeacion_reenviar(
    PDO $pdo,
    int $idPlaneacion,
    array $data,
    int $idUsuario,
    int $idPlantel
): array {
    if (!planeacion_puede_reenviar($pdo, $idPlaneacion, $idPlantel)) {
        return ['ok' => false, 'message' => 'No puede editar esta planeación'];
    }

    $plan = planeacion_obtener($pdo, $idPlaneacion, $idPlantel);
    if (!$plan) {
        return ['ok' => false, 'message' => 'Planeación no encontrada'];
    }

    $contenido = trim((string) ($data['contenido'] ?? $plan['contenido']));
    $fecha = trim((string) ($data['fecha'] ?? $plan['fecha']));
    $idFase = (int) ($data['id_fase'] ?? $plan['id_fase']);
    $nota = trim((string) ($data['nota'] ?? ''));

    if ($contenido === '' || $fecha === '' || $idFase <= 0) {
        return ['ok' => false, 'message' => 'Complete contenido, fecha y fase'];
    }

    $fases = planeacion_fases_grupo($pdo, (int) $plan['id_grupo']);
    $idsFase = array_map(static fn ($f) => (int) $f['id_fase'], $fases);
    if (!in_array($idFase, $idsFase, true)) {
        return ['ok' => false, 'message' => 'Fase inválida para este grupo'];
    }

    // Título automático desde el temario de la fase (el profesor no lo define).
    $faseDetalle = function_exists('planeacion_prompt_fase_detalle')
        ? (planeacion_prompt_fase_detalle($pdo, $idFase) ?: [])
        : [];
    if ($faseDetalle === []) {
        foreach ($fases as $f) {
            if ((int) ($f['id_fase'] ?? 0) === $idFase) {
                $faseDetalle = $f;
                break;
            }
        }
    }
    $titulo = function_exists('planeacion_titulo_desde_fase')
        ? planeacion_titulo_desde_fase($pdo, $faseDetalle)
        : trim((string) ($data['titulo'] ?? $plan['titulo']));
    if ($titulo === '') {
        $titulo = trim((string) ($plan['titulo'] ?? '')) ?: 'Planeación de clase';
    }

    $stmt = $pdo->prepare('SELECT YEAR(?), WEEK(?, 0)');
    $stmt->execute([$fecha, $fecha]);
    $calc = $stmt->fetch(PDO::FETCH_NUM);
    $anio = isset($calc[0]) ? (int) $calc[0] : (int) date('Y');
    $semana = isset($calc[1]) ? (int) $calc[1] : 0;

    $pdo->prepare(
        'UPDATE planeaciones SET titulo = ?, contenido = ?, fecha = ?, id_fase = ?,
            anio = ?, semana = ?, estado = \'enviada\', revisado_en = NULL, id_revisor = NULL, nota_revision = NULL
         WHERE id_planeacion = ?'
    )->execute([$titulo, $contenido, $fecha, $idFase, $anio, $semana, $idPlaneacion]);

    $msgReenvio = 'Planeación actualizada y reenviada a coordinación.';
    if ($nota !== '') {
        $msgReenvio .= ' Nota: ' . $nota;
    }
    planeacion_observacion_agregar($pdo, $idPlaneacion, $idUsuario, 'profesor', $msgReenvio, true);

    planeacion_notificar_coordinacion_nueva(
        $pdo,
        $idPlantel,
        (int) $plan['id_grupo'],
        $titulo,
        $fecha,
        $idUsuario
    );

    return ['ok' => true, 'message' => 'Planeación reenviada a coordinación.'];
}

/** @param array<string, mixed> $plan */
function planeacion_notificar_coordinacion_comentario(PDO $pdo, int $idPlantel, array $plan, string $comentario): void
{
    if (!function_exists('academico_notificar_usuario')) {
        return;
    }
    if (function_exists('academico_ensure_schema')) {
        academico_ensure_schema($pdo);
    }
    $msg = ($plan['grupo_clave'] ?? 'Grupo') . ' · ' . ($plan['titulo'] ?? '') . ' — ' . mb_substr($comentario, 0, 180);
    $st = $pdo->prepare(
        "SELECT id_usuario FROM usuarios
         WHERE activo = 1 AND (suspendido IS NULL OR suspendido = 0)
           AND rol IN ('coordinador', 'coordinacion', 'director', 'supervisor')
           AND (id_plantel IS NULL OR id_plantel = ?)"
    );
    $st->execute([$idPlantel]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        $idU = (int) $uid;
        if ($idU <= 0 || $idU === (int) ($plan['id_profesor'] ?? 0)) {
            continue;
        }
        academico_notificar_usuario(
            $pdo,
            $idU,
            'planeacion_comentario',
            'Observación del profesor',
            $msg,
            'planeaciones_revision',
            'id=' . (int) ($plan['id_planeacion'] ?? 0)
        );
    }
}

function planeacion_puede_grupo(PDO $pdo, int $idGrupo, ?int $idPlantel = null): bool
{
    if ($idGrupo <= 0 || !planeacion_puede_crear()) {
        return false;
    }
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    if (!plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel)) {
        return false;
    }
    $rol = rbac_rol_efectivo();
    if ($rol !== 'profesor') {
        return true;
    }

    return grupo_docente_profesor_imparte($pdo, $idGrupo, (int) ($_SESSION['user_id'] ?? 0));
}

/** @return list<array<string, mixed>> */
function planeacion_grupos_usuario(PDO $pdo, ?int $idPlantel = null): array
{
    $idPlantel = $idPlantel ?? plantel_scope_id($pdo);
    $rol = rbac_rol_efectivo();
    if ($rol === 'profesor') {
        return profesor_portal_grupos($pdo, (int) ($_SESSION['user_id'] ?? 0), $idPlantel);
    }
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.id_especialidad, g.id_fase_actual,
                e.nombre AS esp_nombre, f.clave_fase, f.nombre_fase
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual
         WHERE g.id_plantel = ?
         ORDER BY g.clave ASC'
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function planeacion_fases_grupo(PDO $pdo, int $idGrupo): array
{
    $st = $pdo->prepare('SELECT id_especialidad, id_fase_actual FROM grupos WHERE id_grupo = ? LIMIT 1');
    $st->execute([$idGrupo]);
    $g = $st->fetch(PDO::FETCH_ASSOC);
    if (!$g || empty($g['id_especialidad'])) {
        return [];
    }
    if (!function_exists('fase_listar')) {
        return [];
    }

    return fase_listar($pdo, (int) $g['id_especialidad']);
}

/** @return array<string, mixed>|null */
function planeacion_grupo_detalle(PDO $pdo, int $idGrupo): ?array
{
    $st = $pdo->prepare(
        'SELECT g.*, e.nombre AS esp_nombre, e.clave AS esp_clave,
                f.clave_fase, f.nombre_fase,
                CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual
         LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
         WHERE g.id_grupo = ? LIMIT 1'
    );
    $st->execute([$idGrupo]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return array<string, string> */
function planeacion_estados_etiquetas(): array
{
    return [
        'borrador' => 'Borrador',
        'enviada' => 'Pendiente de revisión',
        'revisada' => 'Revisada / aprobada',
        'observada' => 'Con observaciones',
    ];
}

/** @return array<string, mixed>|null */
function planeacion_obtener(PDO $pdo, int $idPlaneacion, int $idPlantel): ?array
{
    planeacion_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT p.*, g.clave AS grupo_clave, g.id_plantel,
                e.nombre AS esp_nombre,
                f.clave_fase, f.nombre_fase,
                CONCAT(up.nombre, \' \', up.apellido) AS profesor_nombre,
                CONCAT(ur.nombre, \' \', ur.apellido) AS revisor_nombre
         FROM planeaciones p
         INNER JOIN grupos g ON g.id_grupo = p.id_grupo
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN especialidad_fases f ON f.id_fase = p.id_fase
         LEFT JOIN usuarios up ON up.id_usuario = p.id_profesor
         LEFT JOIN usuarios ur ON ur.id_usuario = p.id_revisor
         WHERE p.id_planeacion = ? AND g.id_plantel = ?
         LIMIT 1'
    );
    $st->execute([$idPlaneacion, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function planeacion_listar(PDO $pdo, int $idPlantel, array $filtros = [], int $limite = 50): array
{
    planeacion_ensure_schema($pdo);
    $sql = 'SELECT p.*, g.clave AS grupo_clave,
                e.nombre AS esp_nombre,
                f.clave_fase, f.nombre_fase,
                CONCAT(up.nombre, \' \', up.apellido) AS profesor_nombre,
                CONCAT(ur.nombre, \' \', ur.apellido) AS revisor_nombre
         FROM planeaciones p
         INNER JOIN grupos g ON g.id_grupo = p.id_grupo
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN especialidad_fases f ON f.id_fase = p.id_fase
         LEFT JOIN usuarios up ON up.id_usuario = p.id_profesor
         LEFT JOIN usuarios ur ON ur.id_usuario = p.id_revisor
         WHERE g.id_plantel = ?';
    $params = [$idPlantel];

    $estado = trim((string) ($filtros['estado'] ?? ''));
    if ($estado !== '') {
        $sql .= ' AND p.estado = ?';
        $params[] = $estado;
    }
    $idProf = (int) ($filtros['id_profesor'] ?? 0);
    if ($idProf > 0) {
        $sql .= ' AND p.id_profesor = ?';
        $params[] = $idProf;
    }
    $idGrupo = (int) ($filtros['id_grupo'] ?? 0);
    if ($idGrupo > 0) {
        $sql .= ' AND p.id_grupo = ?';
        $params[] = $idGrupo;
    }

    $sql .= ' ORDER BY FIELD(p.estado, \'enviada\', \'observada\', \'revisada\', \'borrador\'), p.creado_en DESC';
    $sql .= ' LIMIT ' . max(1, min(100, $limite));

    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Profesores del plantel para filtro de revisión de planeaciones. */
function planeacion_profesores_filtro(PDO $pdo, int $idPlantel): array
{
    if (function_exists('grupo_docente_listar_profesores_plantel')) {
        $rows = grupo_docente_listar_profesores_plantel($pdo, $idPlantel);
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id_usuario'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id_usuario' => $id,
                'nombre' => $r['nombre_completo'] ?? trim(($r['nombre'] ?? '') . ' ' . ($r['apellido'] ?? '')),
            ];
        }

        return $out;
    }

    planeacion_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT DISTINCT p.id_profesor AS id_usuario,
                CONCAT(u.nombre, \' \', u.apellido) AS nombre
         FROM planeaciones p
         INNER JOIN grupos g ON g.id_grupo = p.id_grupo
         INNER JOIN usuarios u ON u.id_usuario = p.id_profesor
         WHERE g.id_plantel = ?
         ORDER BY nombre ASC'
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function planeacion_contar_pendientes(PDO $pdo, int $idPlantel): int
{
    planeacion_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM planeaciones p
         INNER JOIN grupos g ON g.id_grupo = p.id_grupo
         WHERE g.id_plantel = ? AND p.estado = \'enviada\''
    );
    $st->execute([$idPlantel]);

    return (int) $st->fetchColumn();
}

/** @return array{ok:bool,message:string} */
function planeacion_resolver_revision(
    PDO $pdo,
    int $idPlaneacion,
    string $estado,
    string $nota,
    int $idRevisor,
    int $idPlantel
): array {
    if (!planeacion_puede_revisar()) {
        return ['ok' => false, 'message' => 'Sin permiso para revisar planeaciones'];
    }
    if (!in_array($estado, ['revisada', 'observada'], true)) {
        return ['ok' => false, 'message' => 'Estado de revisión inválido'];
    }
    $nota = trim($nota);
    if ($estado === 'observada' && $nota === '') {
        return ['ok' => false, 'message' => 'Indique las observaciones para el profesor'];
    }

    $plan = planeacion_obtener($pdo, $idPlaneacion, $idPlantel);
    if (!$plan) {
        return ['ok' => false, 'message' => 'Planeación no encontrada'];
    }
    if (($plan['estado'] ?? '') !== 'enviada') {
        return ['ok' => false, 'message' => 'Solo se revisan planeaciones pendientes'];
    }

    $pdo->prepare(
        'UPDATE planeaciones SET estado = ?, nota_revision = ?, id_revisor = ?, revisado_en = NOW()
         WHERE id_planeacion = ?'
    )->execute([$estado, $nota !== '' ? $nota : null, $idRevisor, $idPlaneacion]);

    if ($nota !== '') {
        planeacion_observacion_agregar($pdo, $idPlaneacion, $idRevisor, 'coordinacion', $nota);
    }

    planeacion_notificar_profesor_revision($pdo, $plan, $estado, $nota, $idRevisor);

    return [
        'ok' => true,
        'message' => $estado === 'revisada' ? 'Planeación aprobada.' : 'Observaciones enviadas al profesor.',
    ];
}

/** @param array<string, mixed> $plan */
function planeacion_notificar_profesor_revision(
    PDO $pdo,
    array $plan,
    string $estado,
    string $nota,
    int $idRevisor
): void {
    $idProf = (int) ($plan['id_profesor'] ?? 0);
    if ($idProf <= 0 || !function_exists('academico_notificar_usuario')) {
        return;
    }
    if (function_exists('academico_ensure_schema')) {
        academico_ensure_schema($pdo);
    }
    $titulo = $estado === 'revisada' ? 'Planeación aprobada' : 'Planeación con observaciones';
    $msg = ($plan['grupo_clave'] ?? 'Grupo') . ' · ' . ($plan['titulo'] ?? '');
    if ($plan['fecha'] ?? '') {
        $msg .= ' · sesión ' . date('d/m/Y', strtotime((string) $plan['fecha']));
    }
    if ($nota !== '') {
        $msg .= ' — ' . mb_substr($nota, 0, 200);
    }
    academico_notificar_usuario(
        $pdo,
        $idProf,
        'planeacion_revision',
        $titulo,
        $msg,
        'planeaciones',
        null
    );
}

function planeacion_notificar_coordinacion_nueva(
    PDO $pdo,
    int $idPlantel,
    int $idGrupo,
    string $titulo,
    string $fecha,
    int $idProfesor
): void {
    if (!function_exists('academico_notificar_usuario')) {
        return;
    }
    if (function_exists('academico_ensure_schema')) {
        academico_ensure_schema($pdo);
    }
    $grupo = planeacion_grupo_detalle($pdo, $idGrupo);
    $prof = '';
    if ($idProfesor > 0) {
        $st = $pdo->prepare('SELECT CONCAT(nombre, \' \', apellido) FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $st->execute([$idProfesor]);
        $prof = trim((string) $st->fetchColumn());
    }
    $msg = ($grupo['clave'] ?? 'Grupo') . ' · ' . $titulo . ' · sesión ' . date('d/m/Y', strtotime($fecha));
    if ($prof !== '') {
        $msg .= ' — ' . $prof;
    }
    $st = $pdo->prepare(
        "SELECT id_usuario FROM usuarios
         WHERE activo = 1 AND (suspendido IS NULL OR suspendido = 0)
           AND rol IN ('coordinador', 'coordinacion', 'director', 'supervisor')
           AND (id_plantel IS NULL OR id_plantel = ?)"
    );
    $st->execute([$idPlantel]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        $idU = (int) $uid;
        if ($idU <= 0 || $idU === $idProfesor) {
            continue;
        }
        academico_notificar_usuario(
            $pdo,
            $idU,
            'planeacion_nueva',
            'Nueva planeación por revisar',
            $msg,
            'planeaciones_revision',
            null
        );
    }
}

/**
 * Perfiles de gustos de alumnos activos en un grupo.
 * @return list<array{id_alumno:int,nombre:string,gustos:string,intereses:array<string,string>}>
 */
function planeacion_grupo_perfiles_gustos(PDO $pdo, int $idGrupo): array
{
    if ($idGrupo <= 0) {
        return [];
    }
    if (function_exists('alumno_perfil_ensure_schema')) {
        alumno_perfil_ensure_schema($pdo);
    }
    $st = $pdo->prepare(
        'SELECT a.id_alumno,
                TRIM(CONCAT(COALESCE(a.nombres,a.nombre,\'\'), \' \', COALESCE(a.apellido_paterno,a.apellido,\'\'))) AS nombre,
                a.perfil_gustos, a.perfil_intereses_json, a.perfil_completado
         FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.estado = \'activo\'
         ORDER BY nombre ASC'
    );
    $st->execute([$idGrupo]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        if ((int) ($row['perfil_completado'] ?? 0) !== 1) {
            continue;
        }
        $json = $row['perfil_intereses_json'] ?? null;
        if (is_string($json)) {
            $json = json_decode($json, true);
        }
        $out[] = [
            'id_alumno' => (int) $row['id_alumno'],
            'nombre' => trim((string) ($row['nombre'] ?? '')),
            'gustos' => trim((string) ($row['perfil_gustos'] ?? '')),
            'intereses' => is_array($json) ? $json : [],
        ];
    }

    return $out;
}

/** Resumen textual de gustos del grupo para prompts de IA (planeación / tutor). */
function planeacion_grupo_gustos_texto(PDO $pdo, int $idGrupo): string
{
    $perfiles = planeacion_grupo_perfiles_gustos($pdo, $idGrupo);
    if ($perfiles === []) {
        return '';
    }

    $lineas = ['[INTERESES DEL GRUPO — personalizar ejemplos y actividades]'];
    $hobbies = [];
    $materias = [];
    $aprendizaje = [];
    foreach ($perfiles as $p) {
        $nom = $p['nombre'] !== '' ? $p['nombre'] : 'Alumno';
        if ($p['gustos'] !== '') {
            $lineas[] = "- {$nom}: {$p['gustos']}";
        }
        $int = $p['intereses'];
        if (!empty($int['hobbies'])) {
            $hobbies[] = (string) $int['hobbies'];
        }
        if (!empty($int['materias_favoritas'])) {
            $materias[] = (string) $int['materias_favoritas'];
        }
        if (!empty($int['como_aprende'])) {
            $aprendizaje[] = (string) $int['como_aprende'];
        }
    }

    if ($hobbies !== []) {
        $lineas[] = 'Pasatiempos frecuentes en el grupo: ' . implode('; ', array_unique($hobbies));
    }
    if ($materias !== []) {
        $lineas[] = 'Temas que les interesan: ' . implode('; ', array_unique($materias));
    }
    if ($aprendizaje !== []) {
        $lineas[] = 'Formas de aprender preferidas: ' . implode('; ', array_unique($aprendizaje));
    }
    $lineas[] = 'Incluya en la planeación ejemplos o dinámicas alineadas a estos intereses cuando sea pedagógicamente útil.';

    return implode("\n", $lineas);
}

/** @return array{total_alumnos:int,con_perfil:int,perfiles:list<array<string,mixed>>,resumen_html:string} */
function planeacion_grupo_gustos_resumen(PDO $pdo, int $idGrupo): array
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.estado = \'activo\''
    );
    $st->execute([$idGrupo]);
    $total = (int) $st->fetchColumn();
    $perfiles = planeacion_grupo_perfiles_gustos($pdo, $idGrupo);
    $html = '';
    if ($perfiles !== []) {
        $html = '<ul style="margin:8px 0 0; padding-left:18px;">';
        foreach (array_slice($perfiles, 0, 8) as $p) {
            $html .= '<li><strong>' . htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') . ':</strong> '
                . htmlspecialchars(mb_substr($p['gustos'], 0, 120), ENT_QUOTES, 'UTF-8') . '</li>';
        }
        if (count($perfiles) > 8) {
            $html .= '<li>… y ' . (count($perfiles) - 8) . ' más</li>';
        }
        $html .= '</ul>';
    }

    return [
        'total_alumnos' => $total,
        'con_perfil' => count($perfiles),
        'perfiles' => $perfiles,
        'resumen_html' => $html,
    ];
}
