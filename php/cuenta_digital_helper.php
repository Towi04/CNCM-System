<?php
/**
 * Vincular cuentas Google / Moodle existentes con HAY y unificar username.
 */

function cuenta_digital_ensure_schema(PDO $pdo): void
{
    if (function_exists('alumno_ensure_schema')) {
        alumno_ensure_schema($pdo);
    }
    if (function_exists('usuario_ensure_schema')) {
        usuario_ensure_schema($pdo);
    }
    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'usuarios', 'moodle_user_id', 'INT UNSIGNED NULL', 'id_alumno');
    }
}

function cuenta_digital_puede_gestionar_staff(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('admin_usuarios');
}

function cuenta_digital_sanitize_username(string $username): string
{
    return function_exists('moodle_sanitize_username')
        ? moodle_sanitize_username($username)
        : strtolower(preg_replace('/[^a-z0-9._-]/', '', trim($username)));
}

/** @return array{ok:bool,message?:string,user?:array} */
function cuenta_digital_moodle_resolver(string $ref): array
{
    $ref = trim($ref);
    if ($ref === '') {
        return ['ok' => false, 'message' => 'Indique username, correo o ID Moodle'];
    }
    if (!function_exists('moodle_user_find_by_field')) {
        return ['ok' => false, 'message' => 'Moodle no disponible'];
    }

    if (ctype_digit($ref)) {
        $idM = (int) $ref;
        if ($idM > 0 && function_exists('moodle_user_get_by_id')) {
            $byId = moodle_user_get_by_id($idM);
            if (!empty($byId['ok'])) {
                return $byId;
            }
        }
    }

    $tries = [];
    if (str_contains($ref, '@')) {
        $tries[] = ['field' => 'email', 'value' => strtolower($ref)];
    }
    $tries[] = ['field' => 'username', 'value' => cuenta_digital_sanitize_username($ref)];
    if (ctype_digit($ref)) {
        $tries[] = ['field' => 'idnumber', 'value' => $ref];
    }

    foreach ($tries as $try) {
        if ($try['value'] === '') {
            continue;
        }
        $find = moodle_user_find_by_field($try['field'], $try['value']);
        if (!empty($find['users'][0])) {
            return [
                'ok' => true,
                'user' => $find['users'][0],
                'message' => 'Encontrado por ' . $try['field'],
            ];
        }
    }

    return ['ok' => false, 'message' => 'Usuario Moodle no encontrado para: ' . $ref];
}

/** @return array{ok:bool,message?:string,existe?:bool,email?:string} */
function cuenta_digital_google_verificar(string $email): array
{
    $email = strtolower(trim($email));
    if ($email === '' || !str_contains($email, '@')) {
        return ['ok' => false, 'message' => 'Correo Google inválido'];
    }
    if (!function_exists('google_usuario_existe')) {
        return ['ok' => false, 'message' => 'Google no configurado'];
    }

    return google_usuario_existe($email);
}

/**
 * Busca cuentas externas sin guardar (preview).
 *
 * @return array<string, mixed>
 */
function cuenta_digital_buscar_externas(string $googleEmail = '', string $moodleRef = ''): array
{
    $out = ['ok' => true, 'google' => null, 'moodle' => null];

    $googleEmail = strtolower(trim($googleEmail));
    if ($googleEmail !== '') {
        $g = cuenta_digital_google_verificar($googleEmail);
        $out['google'] = [
            'email' => $googleEmail,
            'existe' => !empty($g['existe']),
            'ok' => !empty($g['ok']),
            'message' => !empty($g['existe'])
                ? 'Cuenta Google encontrada'
                : ((string) ($g['message'] ?? 'No encontrada en Google Workspace')),
        ];
    }

    $moodleRef = trim($moodleRef);
    if ($moodleRef !== '') {
        $m = cuenta_digital_moodle_resolver($moodleRef);
        $u = (array) ($m['user'] ?? []);
        $out['moodle'] = [
            'ok' => !empty($m['ok']),
            'message' => (string) ($m['message'] ?? ''),
            'id' => (int) ($u['id'] ?? 0) ?: null,
            'username' => (string) ($u['username'] ?? ''),
            'email' => (string) ($u['email'] ?? ''),
            'firstname' => (string) ($u['firstname'] ?? ''),
            'lastname' => (string) ($u['lastname'] ?? ''),
        ];
    }

    return $out;
}

/** @return array{ok:bool,message:string} */
function cuenta_digital_hay_username_disponible(PDO $pdo, string $username, int $exceptIdUsuario = 0): array
{
    $username = cuenta_digital_sanitize_username($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Username vacío o inválido'];
    }
    $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = ? AND id_usuario <> ? LIMIT 1');
    $st->execute([$username, max(0, $exceptIdUsuario)]);
    if ($st->fetchColumn()) {
        return ['ok' => false, 'message' => 'El username «' . $username . '» ya está en uso en HAY'];
    }

    return ['ok' => true, 'message' => 'Disponible', 'username' => $username];
}

/** @return array{ok:bool,message:string,detalle?:array} */
function cuenta_digital_unificar_username(
    PDO $pdo,
    int $idUsuario,
    string $usernameDeseado,
    bool $actualizarMoodle = true,
    ?int $idMoodle = null
): array {
    $usernameDeseado = cuenta_digital_sanitize_username($usernameDeseado);
    if ($usernameDeseado === '') {
        return ['ok' => false, 'message' => 'Username inválido'];
    }

    $disp = cuenta_digital_hay_username_disponible($pdo, $usernameDeseado, $idUsuario);
    if (empty($disp['ok'])) {
        return $disp;
    }

    $st = $pdo->prepare('SELECT id_usuario, username, email, moodle_user_id FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return ['ok' => false, 'message' => 'Usuario HAY no encontrado'];
    }

    $detalle = ['hay' => ['ok' => true, 'de' => $u['username'], 'a' => $usernameDeseado]];
    $pdo->prepare('UPDATE usuarios SET username = ? WHERE id_usuario = ?')->execute([$usernameDeseado, $idUsuario]);

    $idM = $idMoodle ?? (int) ($u['moodle_user_id'] ?? 0);
    if ($actualizarMoodle && $idM > 0 && function_exists('moodle_user_update_fields')) {
        $upd = moodle_user_update_fields($idM, ['username' => $usernameDeseado]);
        $detalle['moodle'] = $upd;
        if (empty($upd['ok'])) {
            return [
                'ok' => false,
                'message' => 'HAY actualizado pero Moodle falló: ' . ($upd['message'] ?? ''),
                'detalle' => $detalle,
            ];
        }
    }

    return [
        'ok' => true,
        'message' => 'Username unificado: ' . $usernameDeseado,
        'username' => $usernameDeseado,
        'detalle' => $detalle,
    ];
}

/**
 * Vincula Google y/o Moodle existentes a un alumno.
 *
 * @param array{google_email?:string,moodle_ref?:string,username_unificado?:string,sync_moodle_username?:bool} $opts
 * @return array<string, mixed>
 */
function cuenta_digital_vincular_alumno(PDO $pdo, int $idAlumno, int $idPlantel, array $opts): array
{
    if (!function_exists('cuenta_alumno_puede_gestionar') || !cuenta_alumno_puede_gestionar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }

    cuenta_digital_ensure_schema($pdo);

    $st = $pdo->prepare(
        'SELECT id_alumno, id_plantel, numero_control, nombres, apellido_paterno, apellido_materno, email, id_usuario, moodle_user_id
         FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$idAlumno, $idPlantel]);
    $al = $st->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    $msgs = [];
    $detalle = [];

    $googleEmail = strtolower(trim((string) ($opts['google_email'] ?? '')));
    if ($googleEmail !== '') {
        $g = cuenta_digital_google_verificar($googleEmail);
        if (empty($g['ok']) || empty($g['existe'])) {
            return ['ok' => false, 'message' => 'Google: ' . ($g['message'] ?? 'cuenta no encontrada')];
        }
        $pdo->prepare('UPDATE alumnos SET email = ? WHERE id_alumno = ?')->execute([$googleEmail, $idAlumno]);
        $idU = (int) ($al['id_usuario'] ?? 0);
        if ($idU > 0) {
            $pdo->prepare('UPDATE usuarios SET email = ? WHERE id_usuario = ?')->execute([$googleEmail, $idU]);
        }
        $detalle['google'] = ['ok' => true, 'email' => $googleEmail];
        $msgs[] = 'Google vinculado (' . $googleEmail . ')';
    }

    $moodleRef = trim((string) ($opts['moodle_ref'] ?? ''));
    $idMoodle = 0;
    if ($moodleRef !== '') {
        $m = cuenta_digital_moodle_resolver($moodleRef);
        if (empty($m['ok'])) {
            return ['ok' => false, 'message' => 'Moodle: ' . ($m['message'] ?? 'no encontrado')];
        }
        $mu = (array) ($m['user'] ?? []);
        $idMoodle = (int) ($mu['id'] ?? 0);
        if ($idMoodle <= 0) {
            return ['ok' => false, 'message' => 'Moodle sin ID válido'];
        }
        if (function_exists('moodle_user_id_save')) {
            moodle_user_id_save($pdo, $idAlumno, $idMoodle);
        } else {
            $pdo->prepare('UPDATE alumnos SET moodle_user_id = ? WHERE id_alumno = ?')->execute([$idMoodle, $idAlumno]);
        }
        $detalle['moodle'] = [
            'ok' => true,
            'id' => $idMoodle,
            'username' => (string) ($mu['username'] ?? ''),
            'email' => (string) ($mu['email'] ?? ''),
        ];
        $msgs[] = 'Moodle vinculado (#' . $idMoodle . ', user ' . ($mu['username'] ?? '') . ')';
    } else {
        $idMoodle = (int) ($al['moodle_user_id'] ?? 0);
    }

    $usernameUnificado = trim((string) ($opts['username_unificado'] ?? ''));
    $syncMoodle = !empty($opts['sync_moodle_username']);
    if ($usernameUnificado !== '') {
        $idU = (int) ($al['id_usuario'] ?? 0);
        if ($idU <= 0 && function_exists('usuario_crear_cuenta_alumno')) {
            $cre = usuario_crear_cuenta_alumno($pdo, $idAlumno, $idPlantel);
            if (!empty($cre['ok']) || !empty($cre['vinculado'])) {
                $idU = (int) ($cre['id_usuario'] ?? 0);
                $st->execute([$idAlumno, $idPlantel]);
                $al = $st->fetch(PDO::FETCH_ASSOC) ?: $al;
            }
        }
        if ($idU <= 0) {
            return ['ok' => false, 'message' => 'Primero cree la cuenta HAY del alumno'];
        }
        $uni = cuenta_digital_unificar_username($pdo, $idU, $usernameUnificado, $syncMoodle, $idMoodle > 0 ? $idMoodle : null);
        $detalle['unificar'] = $uni;
        if (empty($uni['ok'])) {
            return array_merge($uni, ['detalle' => $detalle]);
        }
        $msgs[] = $uni['message'];
    } elseif ($syncMoodle && $idMoodle > 0) {
        $idU = (int) ($al['id_usuario'] ?? 0);
        if ($idU > 0) {
            $stU = $pdo->prepare('SELECT username FROM usuarios WHERE id_usuario = ? LIMIT 1');
            $stU->execute([$idU]);
            $hayUser = (string) ($stU->fetchColumn() ?: '');
            if ($hayUser !== '' && function_exists('moodle_user_update_fields')) {
                $upd = moodle_user_update_fields($idMoodle, ['username' => $hayUser]);
                $detalle['moodle_sync'] = $upd;
                if (!empty($upd['ok'])) {
                    $msgs[] = 'Moodle username = ' . $hayUser;
                }
            }
        }
    }

    return [
        'ok' => true,
        'message' => $msgs !== [] ? implode(' · ', $msgs) : 'Vinculación completada',
        'detalle' => $detalle,
        'estado' => function_exists('cuenta_alumno_estado')
            ? cuenta_alumno_estado($pdo, $idAlumno, $idPlantel)
            : null,
    ];
}

/**
 * Vincula Google y/o Moodle existentes a un usuario del personal.
 *
 * @param array{google_email?:string,moodle_ref?:string,username_unificado?:string,sync_moodle_username?:bool} $opts
 * @return array<string, mixed>
 */
function cuenta_digital_vincular_staff(PDO $pdo, int $idUsuario, array $opts): array
{
    if (!cuenta_digital_puede_gestionar_staff()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }

    cuenta_digital_ensure_schema($pdo);

    $st = $pdo->prepare(
        'SELECT id_usuario, nombre, apellido, username, email, moodle_user_id, id_alumno
         FROM usuarios WHERE id_usuario = ? LIMIT 1'
    );
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return ['ok' => false, 'message' => 'Usuario no encontrado'];
    }

    $msgs = [];
    $detalle = [];

    $googleEmail = strtolower(trim((string) ($opts['google_email'] ?? '')));
    if ($googleEmail !== '') {
        $g = cuenta_digital_google_verificar($googleEmail);
        if (empty($g['ok']) || empty($g['existe'])) {
            return ['ok' => false, 'message' => 'Google: ' . ($g['message'] ?? 'cuenta no encontrada')];
        }
        $pdo->prepare('UPDATE usuarios SET email = ? WHERE id_usuario = ?')->execute([$googleEmail, $idUsuario]);
        $detalle['google'] = ['ok' => true, 'email' => $googleEmail];
        $msgs[] = 'Google vinculado (' . $googleEmail . ')';
    }

    $moodleRef = trim((string) ($opts['moodle_ref'] ?? ''));
    $idMoodle = 0;
    if ($moodleRef !== '') {
        $m = cuenta_digital_moodle_resolver($moodleRef);
        if (empty($m['ok'])) {
            return ['ok' => false, 'message' => 'Moodle: ' . ($m['message'] ?? 'no encontrado')];
        }
        $mu = (array) ($m['user'] ?? []);
        $idMoodle = (int) ($mu['id'] ?? 0);
        $pdo->prepare('UPDATE usuarios SET moodle_user_id = ? WHERE id_usuario = ?')->execute([$idMoodle, $idUsuario]);
        $detalle['moodle'] = [
            'ok' => true,
            'id' => $idMoodle,
            'username' => (string) ($mu['username'] ?? ''),
        ];
        $msgs[] = 'Moodle vinculado (#' . $idMoodle . ')';
    } else {
        $idMoodle = (int) ($u['moodle_user_id'] ?? 0);
    }

    $usernameUnificado = trim((string) ($opts['username_unificado'] ?? ''));
    $syncMoodle = !empty($opts['sync_moodle_username']);
    if ($usernameUnificado !== '') {
        $uni = cuenta_digital_unificar_username($pdo, $idUsuario, $usernameUnificado, $syncMoodle, $idMoodle > 0 ? $idMoodle : null);
        $detalle['unificar'] = $uni;
        if (empty($uni['ok'])) {
            return array_merge($uni, ['detalle' => $detalle]);
        }
        $msgs[] = $uni['message'];
    } elseif ($syncMoodle && $idMoodle > 0) {
        $hayUser = cuenta_digital_sanitize_username((string) ($u['username'] ?? ''));
        if ($hayUser !== '' && function_exists('moodle_user_update_fields')) {
            $upd = moodle_user_update_fields($idMoodle, ['username' => $hayUser]);
            $detalle['moodle_sync'] = $upd;
            if (!empty($upd['ok'])) {
                $msgs[] = 'Moodle username = ' . $hayUser;
            }
        }
    }

    return [
        'ok' => true,
        'message' => $msgs !== [] ? implode(' · ', $msgs) : 'Vinculación completada',
        'detalle' => $detalle,
        'estado' => cuenta_digital_estado_staff($pdo, $idUsuario),
    ];
}

/** Estado de cuentas del personal. */
function cuenta_digital_estado_staff(PDO $pdo, int $idUsuario): array
{
    cuenta_digital_ensure_schema($pdo);

    $st = $pdo->prepare(
        'SELECT id_usuario, nombre, apellido, username, email, moodle_user_id, suspendido, rol
         FROM usuarios WHERE id_usuario = ? LIMIT 1'
    );
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return ['ok' => false, 'message' => 'Usuario no encontrado'];
    }

    $email = strtolower(trim((string) ($u['email'] ?? '')));
    $passInicial = function_exists('cuenta_password_inicial') ? cuenta_password_inicial() : 'Cncm*1234';

    $googleCfg = function_exists('google_config_status') ? google_config_status() : ['ok' => false];
    $googleActivo = false;
    $googleMsg = '';
    if (!empty($googleCfg['ok']) && $email !== '') {
        $gEx = google_usuario_existe($email);
        $googleActivo = !empty($gEx['existe']);
        $googleMsg = $googleActivo ? 'Cuenta Google vinculada' : 'Correo en HAY pero no encontrado en Google';
    } elseif (empty($googleCfg['ok'])) {
        $googleMsg = (string) ($googleCfg['message'] ?? 'Google no configurado');
    } else {
        $googleMsg = 'Sin correo institucional en HAY';
    }

    $idMoodle = (int) ($u['moodle_user_id'] ?? 0);
    $moodleUsername = null;
    $moodleEmail = null;
    $moodleMsg = 'Sin Moodle vinculado';
    if ($idMoodle > 0 && function_exists('moodle_user_get_by_id')) {
        $mf = moodle_user_get_by_id($idMoodle);
        if (!empty($mf['ok'])) {
            $mu = (array) ($mf['user'] ?? []);
            $moodleUsername = (string) ($mu['username'] ?? '');
            $moodleEmail = (string) ($mu['email'] ?? '');
            $moodleMsg = 'Moodle #' . $idMoodle;
        } else {
            $moodleMsg = 'ID guardado #' . $idMoodle . ' (API no devolvió datos)';
        }
    } elseif (function_exists('moodle_enabled') && moodle_enabled() && $email !== '') {
        $m = cuenta_digital_moodle_resolver($email);
        if (!empty($m['ok'])) {
            $mu = (array) ($m['user'] ?? []);
            $idMoodle = (int) ($mu['id'] ?? 0);
            $moodleUsername = (string) ($mu['username'] ?? '');
            $moodleMsg = 'Detectado por correo (no vinculado en HAY)';
        }
    }

    return [
        'ok' => true,
        'tipo' => 'staff',
        'id_usuario' => $idUsuario,
        'puede_gestionar' => cuenta_digital_puede_gestionar_staff(),
        'password_inicial' => $passInicial,
        'google' => [
            'configurado' => !empty($googleCfg['ok']),
            'activo' => $googleActivo,
            'email' => $email !== '' ? $email : null,
            'mensaje' => $googleMsg,
        ],
        'hay' => [
            'activo' => true,
            'id_usuario' => $idUsuario,
            'username' => (string) ($u['username'] ?? ''),
            'email' => $email,
            'suspendido' => !empty($u['suspendido']),
            'mensaje' => 'Cuenta portal HAY',
        ],
        'moodle' => [
            'configurado' => function_exists('moodle_enabled') && moodle_enabled(),
            'activo' => $idMoodle > 0,
            'id_moodle' => $idMoodle > 0 ? $idMoodle : null,
            'username' => $moodleUsername,
            'email' => $moodleEmail,
            'mensaje' => $moodleMsg,
            'vinculado_en_hay' => (int) ($u['moodle_user_id'] ?? 0) > 0,
        ],
    ];
}
