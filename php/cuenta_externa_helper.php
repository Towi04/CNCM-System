<?php
/**
 * Provisión de cuentas institucionales: Google Workspace + Moodle + HAY.
 */

if (!defined('CUENTA_PASSWORD_INICIAL')) {
    define('CUENTA_PASSWORD_INICIAL', 'Cncm*1234');
}

function cuenta_password_inicial(): string
{
    return CUENTA_PASSWORD_INICIAL;
}

/**
 * A partir de correo o usuario local, obtiene ambos campos para la BD.
 *
 * @return array{ok:bool,message?:string,email?:string,username?:string}
 */
function cuenta_resolver_identidad(string $input): array
{
    $input = strtolower(trim($input));
    if ($input === '') {
        return ['ok' => false, 'message' => 'Indique correo o nombre de usuario'];
    }

    $dominio = cuenta_dominio_email();
    if (str_contains($input, '@')) {
        $email = $input;
        $local = explode('@', $input, 2)[0];
    } else {
        $local = preg_replace('/[^a-z0-9._-]/', '', $input);
        if ($local === '') {
            return ['ok' => false, 'message' => 'Usuario inválido'];
        }
        $email = $local . '@' . $dominio;
    }

    if (!function_exists('auth_is_institutional_email') || !auth_is_institutional_email($email)) {
        return ['ok' => false, 'message' => 'El correo debe ser institucional (@' . $dominio . ')'];
    }

    return ['ok' => true, 'email' => $email, 'username' => $local];
}

function cuenta_dominio_email(): string
{
    if (defined('GOOGLE_WORKSPACE_DOMAIN') && trim((string) GOOGLE_WORKSPACE_DOMAIN) !== '') {
        return strtolower(trim((string) GOOGLE_WORKSPACE_DOMAIN));
    }

    return defined('INSTITUTIONAL_EMAIL_DOMAIN')
        ? strtolower((string) INSTITUTIONAL_EMAIL_DOMAIN)
        : 'cncm.edu.mx';
}

function cuenta_normalizar_token(string $texto): string
{
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
    $texto = strtolower($texto);

    return (string) preg_replace('/[^a-z0-9]/', '', $texto);
}

/** Correo institucional del alumno: {numero_control}@dominio */
function cuenta_email_alumno(string $numeroControl): string
{
    $local = cuenta_normalizar_token($numeroControl);
    if ($local === '') {
        return '';
    }

    return $local . '@' . cuenta_dominio_email();
}

/**
 * Genera la parte local del correo del personal (sin @dominio).
 * Ej.: Luis Enrique Tovar → letovar; colisión con ltovar → latovar.
 */
function cuenta_generar_local_part_personal(PDO $pdo, string $nombres, string $apellidoPaterno): string
{
    $nombresParts = array_values(array_filter(preg_split('/\s+/u', trim($nombres)) ?: []));
    $apellido = cuenta_normalizar_token($apellidoPaterno);
    if ($apellido === '' || empty($nombresParts)) {
        return '';
    }

    $initials = '';
    foreach ($nombresParts as $part) {
        $initials .= cuenta_normalizar_token(mb_substr($part, 0, 1));
    }
    $local = $initials . $apellido;
    if ($local !== '' && !cuenta_local_part_ocupado($pdo, $local)) {
        return $local;
    }

    $firstName = (string) ($nombresParts[0] ?? '');
    $restInitials = '';
    for ($i = 1, $c = count($nombresParts); $i < $c; $i++) {
        $restInitials .= cuenta_normalizar_token(mb_substr($nombresParts[$i], 0, 1));
    }

    $lenMax = mb_strlen($firstName);
    for ($len = 2; $len <= $lenMax; $len++) {
        $prefix = cuenta_normalizar_token(mb_substr($firstName, 0, $len)) . $restInitials;
        $candidate = $prefix . $apellido;
        if ($candidate !== '' && !cuenta_local_part_ocupado($pdo, $candidate)) {
            return $candidate;
        }
    }

    $suffix = 2;
    while (cuenta_local_part_ocupado($pdo, $local . $suffix)) {
        $suffix++;
    }

    return $local . $suffix;
}

function cuenta_email_personal(PDO $pdo, string $nombres, string $apellidoPaterno): string
{
    $local = cuenta_generar_local_part_personal($pdo, $nombres, $apellidoPaterno);

    return $local !== '' ? ($local . '@' . cuenta_dominio_email()) : '';
}

/** Primer token del campo apellido(s) como apellido paterno para el correo. */
function cuenta_apellido_paterno_desde_campo(string $apellidos): string
{
    $parts = array_values(array_filter(preg_split('/\s+/u', trim($apellidos)) ?: []));

    return (string) ($parts[0] ?? '');
}

function cuenta_local_part_ocupado(PDO $pdo, string $localPart): bool
{
    $localPart = cuenta_normalizar_token($localPart);
    if ($localPart === '') {
        return true;
    }
    $email = $localPart . '@' . cuenta_dominio_email();

    $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE LOWER(email) = ? OR LOWER(username) = ? LIMIT 1');
    $st->execute([$email, $localPart]);
    if ($st->fetchColumn()) {
        return true;
    }

    if (function_exists('google_enabled') && google_enabled() && function_exists('google_usuario_existe')) {
        $g = google_usuario_existe($email);
        if (!empty($g['ok']) && !empty($g['existe'])) {
            return true;
        }
    }

    return false;
}

/**
 * Crea o verifica usuario Google; no falla si Google no está configurado.
 *
 * @return array{ok:bool,message:string,email?:string,creado?:bool,omitido?:bool}
 */
function cuenta_google_asegurar(string $nombre, string $apellido, string $email, bool $soloVerificar = false): array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return ['ok' => false, 'message' => 'Correo institucional vacío'];
    }

    if (!function_exists('google_enabled') || !google_enabled()) {
        return ['ok' => true, 'message' => 'Google no configurado (omitido)', 'email' => $email, 'omitido' => true];
    }

    if (!function_exists('google_usuario_existe')) {
        return ['ok' => false, 'message' => 'Helper de Google no disponible'];
    }

    $existe = google_usuario_existe($email);
    if (empty($existe['ok'])) {
        return ['ok' => false, 'message' => (string) ($existe['message'] ?? 'No se pudo consultar Google')];
    }

    if (!empty($existe['existe'])) {
        return ['ok' => true, 'message' => 'Cuenta Google existente', 'email' => $email, 'creado' => false];
    }

    if ($soloVerificar) {
        return [
            'ok' => false,
            'message' => 'El correo ' . $email . ' no existe en Google Workspace. Desmarque "Ya tiene cuenta Google" para crearla.',
        ];
    }

    if (!function_exists('google_crear_usuario')) {
        return ['ok' => false, 'message' => 'Helper de Google no disponible'];
    }

    $res = google_crear_usuario([
        'nombre' => $nombre,
        'apellido' => $apellido,
        'email_solicitado' => $email,
        'password_inicial' => cuenta_password_inicial(),
    ]);
    if (empty($res['ok'])) {
        return $res;
    }

    return [
        'ok' => true,
        'message' => (string) ($res['message'] ?? 'Usuario creado en Google'),
        'email' => (string) ($res['email'] ?? $email),
        'creado' => true,
    ];
}

/**
 * Provisión Google + actualización de email del alumno antes de HAY/Moodle.
 *
 * @return array{ok:bool,message:string,email?:string}
 */
function cuenta_externa_preparar_alumno(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    $st = $pdo->prepare(
        'SELECT id_alumno, nombres, apellido_paterno, apellido_materno, numero_control, email
         FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$idAlumno, $idPlantel]);
    $al = $st->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    $nc = trim((string) ($al['numero_control'] ?? ''));
    if ($nc === '') {
        return ['ok' => false, 'message' => 'El alumno no tiene número de control'];
    }

    $email = cuenta_email_alumno($nc);
    $nombre = trim((string) ($al['nombres'] ?? ''));
    $apellido = trim((string) (($al['apellido_paterno'] ?? '') . ' ' . ($al['apellido_materno'] ?? '')));

    $google = cuenta_google_asegurar($nombre, $apellido, $email, false);
    if (empty($google['ok'])) {
        return $google;
    }

    $pdo->prepare('UPDATE alumnos SET email = ? WHERE id_alumno = ?')->execute([$email, $idAlumno]);

    $msgs = [(string) ($google['message'] ?? 'Correo institucional listo')];
    $moodleOk = true;
    $moodleError = null;

    if (function_exists('moodle_user_ensure_alumno')) {
        $moodle = moodle_user_ensure_alumno($pdo, $idAlumno, $idPlantel);
        if (empty($moodle['ok'])) {
            $moodleOk = false;
            $moodleError = $moodle;
            $msgs[] = 'Moodle pendiente: ' . (string) ($moodle['message'] ?? 'No se pudo crear usuario');
        } else {
            $msgs[] = (string) ($moodle['message'] ?? 'Moodle OK');
        }
    }

    return [
        'ok' => true,
        'message' => implode(' · ', array_filter($msgs)),
        'email' => $email,
        'moodle_ok' => $moodleOk,
        'moodle_error' => $moodleError,
    ];
}

/**
 * Provisión Google + Moodle para personal recién registrado.
 *
 * @return array{ok:bool,message:string,email?:string}
 */
function cuenta_externa_provisionar_staff(
    PDO $pdo,
    int $idUsuario,
    bool $yaTieneGoogle,
    ?string $emailOverride = null,
    ?string $passwordPlain = null
): array {
    $st = $pdo->prepare(
        'SELECT id_usuario, nombre, apellido, username, email FROM usuarios WHERE id_usuario = ? LIMIT 1'
    );
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return ['ok' => false, 'message' => 'Usuario no encontrado'];
    }

    $email = strtolower(trim($emailOverride ?: (string) ($u['email'] ?? '')));
    if ($email === '') {
        return ['ok' => false, 'message' => 'Correo institucional vacío'];
    }

    $google = cuenta_google_asegurar(
        trim((string) ($u['nombre'] ?? '')),
        trim((string) ($u['apellido'] ?? '')),
        $email,
        $yaTieneGoogle
    );
    if (empty($google['ok'])) {
        return $google;
    }

    $msgs = [(string) ($google['message'] ?? '')];
    $moodleOk = true;
    $moodleError = null;
    $pass = ($passwordPlain !== null && $passwordPlain !== '')
        ? $passwordPlain
        : cuenta_password_inicial();

    if (function_exists('moodle_user_ensure_staff')) {
        $moodle = moodle_user_ensure_staff($pdo, $idUsuario, $pass);
        if (empty($moodle['ok'])) {
            // No bloquear el alta en HAY: Moodle puede fallar por política/duplicado.
            $moodleOk = false;
            $moodleError = $moodle;
            $msgs[] = 'Moodle pendiente: ' . (string) ($moodle['message'] ?? 'No se pudo crear usuario');
        } else {
            $msgs[] = (string) ($moodle['message'] ?? 'Moodle OK');
        }
    }

    return [
        'ok' => true,
        'message' => implode(' · ', array_filter($msgs)),
        'email' => $email,
        'moodle_ok' => $moodleOk,
        'moodle_error' => $moodleError,
    ];
}
