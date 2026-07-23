<?php

/**
 * Roles y permisos de menú / vistas.
 * rol_real = rol en BD; rol en sesión = rol efectivo (puede simularse para capacitación).
 */

/** @return array<string, string> */
/** Normaliza valores legacy de usuarios.rol (antes de migración 006). */
function rbac_normalizar_rol_clave(string $rol): string
{
    $rol = strtolower(trim($rol));
    if ($rol === '') {
        return 'profesor';
    }
    static $map = [
        'coordinacion' => 'coordinador',
        'recepcion' => 'admin',
        'imagen' => 'admin',
        'ventas' => 'asesor',
        'asesor ventas' => 'asesor',
        'asesor de ventas' => 'asesor',
        'caja' => 'admin',
        'supervisora' => 'supervisor',
        'supervisora general' => 'supervisor',
        'direccion general' => 'supervisor',
    ];

    return $map[$rol] ?? $rol;
}

/** Roles que no deben degradarse por un id_rol desalineado en usuarios. */
function rbac_roles_prioritarios_cuenta(): array
{
    return ['supervisor', 'director'];
}

/**
 * Rol efectivo de la cuenta: respeta supervisor/director en usuarios.rol
 * aunque id_rol apunte a otro rol del sistema.
 */
function rbac_resolver_rol_cuenta(PDO $pdo, array $u): string
{
    $rolTexto = rbac_normalizar_rol_clave((string) ($u['rol'] ?? ''));
    if ($rolTexto === '0') {
        $rolTexto = '';
    }
    if (in_array($rolTexto, rbac_roles_prioritarios_cuenta(), true)) {
        return $rolTexto;
    }

    $idRol = (int) ($u['id_rol'] ?? 0);
    $claveId = '';
    if ($idRol > 0 && function_exists('rbac_rol_por_id')) {
        $rowRol = rbac_rol_por_id($pdo, $idRol);
        if ($rowRol && !empty($rowRol['clave'])) {
            $claveId = rbac_normalizar_rol_clave((string) $rowRol['clave']);
        }
    }
    if (in_array($claveId, rbac_roles_prioritarios_cuenta(), true)) {
        return $claveId;
    }
    if ($claveId !== '') {
        if ($rolTexto === '' || $rolTexto === 'profesor') {
            return $claveId;
        }
        $privCount = function_exists('rbac_rol_privilegios')
            ? count(rbac_rol_privilegios($pdo, $idRol))
            : 0;
        $enJerarquia = function_exists('rbac_db_mapa_jerarquia')
            && isset(rbac_db_mapa_jerarquia()[$claveId]);
        if ($privCount > 0 || $enJerarquia) {
            return $claveId;
        }
    }

    return $rolTexto !== '' ? $rolTexto : 'profesor';
}

/** @return list<string> */
function rbac_roles_candidatos_cuenta(PDO $pdo, array $u): array
{
    $out = [];
    $t = rbac_normalizar_rol_clave((string) ($u['rol'] ?? ''));
    if ($t !== '' && $t !== '0') {
        $out[] = $t;
    }
    $idRol = (int) ($u['id_rol'] ?? 0);
    if ($idRol > 0 && function_exists('rbac_rol_por_id')) {
        $rowRol = rbac_rol_por_id($pdo, $idRol);
        if ($rowRol && !empty($rowRol['clave'])) {
            $c = rbac_normalizar_rol_clave((string) $rowRol['clave']);
            if ($c !== '' && !in_array($c, $out, true)) {
                $out[] = $c;
            }
        }
    }

    return $out;
}

function rbac_roles_etiquetas(): array
{
    return [
        'supervisor' => 'Supervisora / Dirección general',
        'director' => 'Director de plantel',
        'gerente' => 'Gerente de ventas',
        'coordinador' => 'Coordinador académico',
        'admin' => 'Recepción / Caja',
        'profesor' => 'Profesor',
        'asesor' => 'Asesor de ventas',
        'alumno' => 'Alumno',
    ];
}

function rbac_inicializar_sesion_tras_login(array $usuario): void
{
    global $pdo;
    $rol = 'profesor';
    if (isset($pdo) && $pdo instanceof PDO) {
        $rol = rbac_resolver_rol_cuenta($pdo, $usuario);
    } else {
        $rol = rbac_normalizar_rol_clave((string) ($usuario['rol'] ?? 'profesor'));
        if ($rol === '0') {
            $rol = 'profesor';
        }
    }
    $_SESSION['rol_real'] = $rol;
    $_SESSION['rol'] = $rol;
    $_SESSION['id_rol'] = (int) ($usuario['id_rol'] ?? 0) ?: null;
    unset($_SESSION['rol_simulado'], $_SESSION['rol_simulado_desde']);
    if (function_exists('rbac_db_cargar_sesion_rol')) {
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            if (function_exists('rbac_db_asegurar_jerarquia_roles')) {
                rbac_db_asegurar_jerarquia_roles($pdo);
            }
            rbac_db_cargar_sesion_rol($pdo, $rol);
        }
    }
    rbac_supervisor_aplicar_sesion();
}

function rbac_rol_efectivo(): string
{
    $rol = strtolower(trim((string) ($_SESSION['rol'] ?? 'profesor')));
    if ($rol !== '' && function_exists('rbac_normalizar_rol_clave')) {
        $rol = rbac_normalizar_rol_clave($rol);
    }

    return $rol !== '' ? $rol : 'profesor';
}

function rbac_rol_real(): string
{
    $rol = rbac_normalizar_rol_clave((string) ($_SESSION['rol_real'] ?? rbac_rol_efectivo()));

    return $rol !== '' ? $rol : 'profesor';
}

function rbac_esta_simulando_rol(): bool
{
    return !empty($_SESSION['rol_simulado']) && rbac_rol_efectivo() !== rbac_rol_real();
}

/** La cuenta real es supervisora (aunque esté simulando otro rol). */
function rbac_es_supervisor_cuenta(): bool
{
    return rbac_rol_real() === 'supervisor';
}

/**
 * ¿Opera ahora como supervisor?
 * Si está simulando otro rol, NO: la vista debe comportarse como el rol elegido.
 */
function rbac_es_supervisor(): bool
{
    if (rbac_esta_simulando_rol()) {
        return rbac_rol_efectivo() === 'supervisor';
    }

    return rbac_rol_real() === 'supervisor' || rbac_rol_efectivo() === 'supervisor';
}

/** Supervisor o rol con acceso_total en sesión o en BD. */
function rbac_tiene_acceso_total(): bool
{
    // Al simular, nunca heredar el acceso total de la cuenta real.
    if (rbac_esta_simulando_rol()) {
        return !empty($_SESSION['rbac_acceso_total']) && rbac_rol_efectivo() === 'supervisor';
    }

    if (rbac_es_supervisor()) {
        return true;
    }

    if (!empty($_SESSION['rbac_acceso_total'])) {
        return true;
    }

    $idUsuario = (int) ($_SESSION['user_id'] ?? 0);
    if ($idUsuario <= 0) {
        return false;
    }

    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return false;
    }

    try {
        if (rbac_cuenta_tiene_acceso_total_bd($pdo, $idUsuario)) {
            if (function_exists('rbac_reparar_sesion_desde_cuenta_bd')) {
                rbac_reparar_sesion_desde_cuenta_bd($pdo, $idUsuario);
            }

            return true;
        }
    } catch (Throwable $e) {
        error_log('rbac_tiene_acceso_total: ' . $e->getMessage());
    }

    return false;
}

/** Fuerza flags de sesión para supervisor (no aplica mientras simula otro rol). */
function rbac_supervisor_aplicar_sesion(): void
{
    if (rbac_esta_simulando_rol()) {
        return;
    }
    if (!rbac_es_supervisor_cuenta() && rbac_rol_efectivo() !== 'supervisor') {
        return;
    }
    $_SESSION['rbac_acceso_total'] = 1;
    $_SESSION['rbac_alcance_planteles'] = 'todos';
    if (function_exists('rbac_privilegios_catalogo')) {
        $_SESSION['rbac_caps'] = array_keys(rbac_privilegios_catalogo());
    }
}

/** Alta de personal (supervisora siempre; demás roles según privilegio admin_usuarios). */
function rbac_puede_registrar_usuarios(): bool
{
    if (rbac_es_supervisor()) {
        return true;
    }

    return rbac_cap('admin_usuarios');
}

/** Puede usar el selector «ver como otro rol». */
function rbac_puede_simular_rol(): bool
{
    return rbac_roles_para_simular() !== [] || rbac_esta_simulando_rol();
}

/** @return list<string> */
function rbac_roles_para_simular(): array
{
    $real = rbac_rol_real();
    return match ($real) {
        'supervisor' => ['director', 'gerente', 'coordinador', 'admin', 'asesor', 'profesor', 'alumno'],
        'director' => ['gerente', 'coordinador', 'admin', 'asesor', 'profesor', 'alumno'],
        'gerente' => ['asesor', 'alumno'],
        'coordinador' => ['profesor', 'alumno'],
        default => [],
    };
}

function rbac_establecer_rol_simulado(string $rol): bool
{
    if (!rbac_puede_simular_rol()) {
        return false;
    }
    $rol = strtolower(trim($rol));
    if ($rol === rbac_rol_real()) {
        rbac_restaurar_rol_real();
        return true;
    }
    if (!in_array($rol, rbac_roles_para_simular(), true)) {
        return false;
    }
    $_SESSION['rol'] = $rol;
    $_SESSION['rol_simulado'] = 1;
    $_SESSION['rol_simulado_desde'] = time();
    if ($rol !== 'alumno') {
        unset($_SESSION['rol_simulado_id_alumno']);
    }
    rbac_recargar_sesion_rol_efectivo();
    return true;
}

function rbac_restaurar_rol_real(): void
{
    $_SESSION['rol'] = rbac_rol_real();
    unset($_SESSION['rol_simulado'], $_SESSION['rol_simulado_desde'], $_SESSION['rol_simulado_id_alumno']);
    rbac_recargar_sesion_rol_efectivo();
}

function rbac_recargar_sesion_rol_efectivo(): void
{
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return;
    }
    if (function_exists('rbac_db_cargar_sesion_rol')) {
        rbac_db_cargar_sesion_rol($pdo, rbac_rol_efectivo());
    }
    if (function_exists('plantel_restringir_sesion_usuario')) {
        plantel_restringir_sesion_usuario($pdo);
    }
}

/**
 * Permisos de menú y acciones. Claves estables para ir ampliando el sistema.
 */
function rbac_cap(string $cap): bool
{
    if (rbac_tiene_acceso_total()) {
        return true;
    }

    $rol = rbac_rol_efectivo();

    if (function_exists('rbac_usuario_cap_override')) {
        $override = rbac_usuario_cap_override($cap);
        if ($override !== null) {
            return $override;
        }
    }

    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO && function_exists('rbac_db_cap')) {
        if (rbac_db_cap($pdo, $cap, $rol)) {
            return true;
        }
    }

    static $map = null;
    if ($map === null && function_exists('rbac_db_mapa_jerarquia')) {
        $map = [];
        foreach (rbac_db_mapa_jerarquia() as $claveRol => $privs) {
            if (!empty($privs['__acceso_total__'])) {
                continue;
            }
            foreach ($privs as $p) {
                if (is_string($p)) {
                    $map[$claveRol][$p] = true;
                }
            }
        }
    }

    if (is_array($map) && isset($map[$rol][$cap])) {
        return (bool) $map[$rol][$cap];
    }

    // Capacidades transversales por rol real: solo fuera de simulación
    // (al simular, la vista debe coincidir con el rol elegido).
    if (!rbac_esta_simulando_rol()) {
        $real = rbac_rol_real();
        if ($cap === 'admin_planteles' && $real === 'supervisor') {
            return true;
        }
        if ($cap === 'admin_calendario' && in_array($real, ['supervisor', 'director'], true)) {
            return true;
        }
        if ($cap === 'admin_catalogo' && in_array($real, ['supervisor', 'director'], true)) {
            return true;
        }
        if (in_array($cap, rbac_cap_solo_supervisor(), true) && $real === 'supervisor') {
            return true;
        }
    }
    if ($cap === 'menu_calendario' && function_exists('calendario_puede_ver_menu') && calendario_puede_ver_menu()) {
        return true;
    }
    if ($cap === 'menu_calendario_admin' && function_exists('calendario_puede_editar_administrativo') && calendario_puede_editar_administrativo()) {
        return true;
    }
    if ($cap === 'menu_calendario_consulta' && function_exists('calendario_puede_ver_consulta')) {
        global $pdo;
        return isset($pdo) && $pdo instanceof PDO && calendario_puede_ver_consulta($pdo);
    }
    if ($cap === 'reporte_academico_ver' && function_exists('rbac_db_cap') && isset($pdo) && rbac_db_cap($pdo, $cap, $rol)) {
        return true;
    }

    static $asistencia = [
        'asistencia_lista_grupo' => ['admin', 'director', 'profesor', 'supervisor', 'coordinador'],
        'asistencia_movil' => ['admin', 'director', 'supervisor'],
        'asistencia_puntualidad' => ['admin', 'director', 'supervisor'],
        'asistencia_personal_manual' => ['director', 'supervisor'],
        'asistencia_config_huella' => ['director', 'supervisor'],
        'asistencia_checada' => ['admin', 'director', 'supervisor', 'profesor', 'coordinador'],
        'asistencia_eliminar_registro' => ['admin', 'director', 'supervisor'],
    ];
    if (isset($asistencia[$cap]) && in_array($rol, $asistencia[$cap], true)) {
        return true;
    }

    return false;
}

/** Acceso administrativo de catálogo (especialidades, productos). */
function rbac_puede_administrar_catalogo(): bool
{
    return rbac_cap('admin_catalogo');
}

function rbac_etiqueta_rol(?string $rol = null): string
{
    $rol = $rol ?? rbac_rol_efectivo();
    $labels = rbac_roles_etiquetas();
    return $labels[$rol] ?? strtoupper($rol);
}

/** Compatibilidad con sesiones abiertas antes de rol_real. */
function rbac_reparar_sesion_legacy(): void
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    if (!isset($_SESSION['rol_real']) && isset($_SESSION['rol'])) {
        $_SESSION['rol_real'] = $_SESSION['rol'];
    }
}

rbac_reparar_sesion_legacy();

/** Roles de ventas / captación (asesor y gerente). */
function rbac_roles_ventas(): array
{
    return ['asesor', 'gerente', 'supervisor', 'director'];
}

/** Cuenta con acceso total según BD (no depende de la sesión). */
function rbac_cuenta_tiene_acceso_total_bd(PDO $pdo, int $idUsuario): bool
{
    if ($idUsuario <= 0) {
        return false;
    }
    $st = $pdo->prepare(
        'SELECT u.rol, r.clave AS rol_clave, COALESCE(r.acceso_total, 0) AS acceso_total
         FROM usuarios u
         LEFT JOIN roles r ON r.id_rol = u.id_rol
         WHERE u.id_usuario = ? LIMIT 1'
    );
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return false;
    }
    if ((int) ($u['acceso_total'] ?? 0) === 1) {
        return true;
    }
    foreach (rbac_roles_candidatos_cuenta($pdo, $u) as $clave) {
        if ($clave === 'supervisor') {
            return true;
        }
    }

    return false;
}

/** @return list<string> */
function rbac_roles_con_preregistro(): array
{
    return ['admin', 'gerente', 'asesor', 'supervisor', 'director', 'coordinador'];
}

/** Rol de la cuenta en BD coincide con alguno de la lista (sin depender de sesión). */
function rbac_cuenta_en_roles_bd(PDO $pdo, int $idUsuario, array $roles): bool
{
    if ($idUsuario <= 0) {
        return false;
    }
    $roles = array_map(static fn($r) => rbac_normalizar_rol_clave((string) $r), $roles);
    $st = $pdo->prepare('SELECT rol, id_rol FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return false;
    }
    foreach (rbac_roles_candidatos_cuenta($pdo, $u) as $clave) {
        if (in_array($clave, $roles, true)) {
            return true;
        }
    }

    return false;
}

/** Privilegio en BD: role_privilegios, otorgado individual o jerarquía por rol. */
function rbac_cuenta_tiene_privilegio_bd(PDO $pdo, int $idUsuario, string $cap): bool
{
    if ($idUsuario <= 0 || $cap === '') {
        return false;
    }
    if (rbac_cuenta_tiene_acceso_total_bd($pdo, $idUsuario)) {
        return true;
    }

    try {
        $deny = $pdo->prepare(
            "SELECT 1 FROM usuario_privilegios
             WHERE id_usuario = ? AND privilegio = ? AND tipo = 'denegar'
               AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())
             LIMIT 1"
        );
        $deny->execute([$idUsuario, $cap]);
        if ($deny->fetchColumn()) {
            return false;
        }
        $otorga = $pdo->prepare(
            "SELECT 1 FROM usuario_privilegios
             WHERE id_usuario = ? AND privilegio = ? AND tipo = 'otorgar'
               AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())
             LIMIT 1"
        );
        $otorga->execute([$idUsuario, $cap]);
        if ($otorga->fetchColumn()) {
            return true;
        }
    } catch (PDOException $e) {
        // tabla opcional
    }

    $st = $pdo->prepare(
        'SELECT 1 FROM usuarios u
         INNER JOIN role_privilegios rp ON rp.id_rol = u.id_rol
         WHERE u.id_usuario = ? AND rp.privilegio = ? LIMIT 1'
    );
    $st->execute([$idUsuario, $cap]);
    if ($st->fetchColumn()) {
        return true;
    }

    $u = $pdo->prepare('SELECT rol, id_rol FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $u->execute([$idUsuario]);
    $row = $u->fetch(PDO::FETCH_ASSOC) ?: [];
    $mapa = function_exists('rbac_db_mapa_jerarquia') ? rbac_db_mapa_jerarquia() : [];
    foreach (rbac_roles_candidatos_cuenta($pdo, $row) as $clave) {
        if (!empty($mapa[$clave]['__acceso_total__'])) {
            return true;
        }
        $privs = $mapa[$clave] ?? [];
        if (in_array($cap, $privs, true)) {
            return true;
        }
    }

    return false;
}

/** Repara sesión si la BD indica supervisor / acceso total. */
function rbac_reparar_sesion_desde_cuenta_bd(PDO $pdo, int $idUsuario): void
{
    if ($idUsuario <= 0) {
        return;
    }
    if (!rbac_cuenta_tiene_acceso_total_bd($pdo, $idUsuario)) {
        return;
    }
    $_SESSION['rol_real'] = 'supervisor';
    if (rbac_esta_simulando_rol()) {
        // Conservar vista simulada: no reaplicar caps de supervisor.
        if (function_exists('rbac_db_cargar_sesion_rol')) {
            rbac_db_cargar_sesion_rol($pdo, rbac_rol_efectivo());
        }

        return;
    }
    $_SESSION['rol'] = 'supervisor';
    rbac_supervisor_aplicar_sesion();
    if (function_exists('rbac_db_cargar_sesion_rol')) {
        rbac_db_cargar_sesion_rol($pdo, 'supervisor');
    }
}

/** Comprueba si el usuario logueado tiene uno de los roles indicados (sesión + BD). */
function rbac_usuario_en_roles(array $roles): bool
{
    $roles = array_map(static fn($r) => rbac_normalizar_rol_clave((string) $r), $roles);

    // En simulación, solo el rol efectivo cuenta para permisos de vista.
    if (rbac_esta_simulando_rol()) {
        return in_array(rbac_rol_efectivo(), $roles, true);
    }

    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO && !empty($_SESSION['user_id'])) {
        $st = $pdo->prepare('SELECT rol, id_rol FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $st->execute([(int) $_SESSION['user_id']]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            foreach (rbac_roles_candidatos_cuenta($pdo, $u) as $rolBd) {
                if (in_array($rolBd, $roles, true)) {
                    return true;
                }
            }
        }
    }

    return in_array(rbac_rol_efectivo(), $roles, true) || in_array(rbac_rol_real(), $roles, true);
}

/**
 * Sincroniza rol y privilegios desde BD en cada petición (dashboard y vistas AJAX).
 */
function rbac_sincronizar_sesion_usuario(PDO $pdo): void
{
    static $hecho = false;
    if ($hecho || empty($_SESSION['user_id'])) {
        return;
    }
    $hecho = true;

    try {
        $st = $pdo->prepare('SELECT id_usuario, rol, id_rol FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $st->execute([(int) $_SESSION['user_id']]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return;
        }

        $rolBd = rbac_resolver_rol_cuenta($pdo, $u);
        $idRol = (int) ($u['id_rol'] ?? 0);

        if (function_exists('rbac_db_asegurar_jerarquia_roles')) {
            rbac_db_asegurar_jerarquia_roles($pdo);
        }
        if (function_exists('rbac_db_reparar_supervisor')) {
            rbac_db_reparar_supervisor($pdo);
        }

        $_SESSION['rol_real'] = $rolBd;
        $_SESSION['id_rol'] = $idRol > 0 ? $idRol : null;
        if (empty($_SESSION['rol_simulado'])) {
            $_SESSION['rol'] = $rolBd;
        }

        if (function_exists('rbac_db_cargar_sesion_rol')) {
            rbac_db_cargar_sesion_rol($pdo, rbac_rol_efectivo());
        }
        if (function_exists('rbac_reparar_sesion_desde_cuenta_bd')) {
            rbac_reparar_sesion_desde_cuenta_bd($pdo, (int) $_SESSION['user_id']);
        } else {
            rbac_supervisor_aplicar_sesion();
        }
        if (function_exists('plantel_restringir_sesion_usuario')) {
            plantel_restringir_sesion_usuario($pdo);
        }

        $stPwd = $pdo->prepare('SELECT debe_cambiar_password FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stPwd->execute([(int) $_SESSION['user_id']]);
        $_SESSION['debe_cambiar_password'] = (int) $stPwd->fetchColumn();
    } catch (Throwable $e) {
        error_log('rbac_sincronizar_sesion_usuario: ' . $e->getMessage());
    }
}

/** @deprecated use rbac_sincronizar_sesion_usuario */
function rbac_reparar_sesion_desde_bd(): void
{
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return;
    }
    rbac_sincronizar_sesion_usuario($pdo);
}

if (is_file(__DIR__ . '/rbac_db_helper.php')) {
    require_once __DIR__ . '/rbac_db_helper.php';
}
