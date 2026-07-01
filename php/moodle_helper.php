<?php
/**
 * Integración Moodle (REST Web Services).
 *
 * Requiere definir en config.local.php:
 * - MOODLE_URL   (ej. https://cncm.edu.mx/courses)
 * - MOODLE_TOKEN (token del usuario de servicio)
 */

if (!defined('MOODLE_URL')) {
    define('MOODLE_URL', '');
}
if (!defined('MOODLE_TOKEN')) {
    define('MOODLE_TOKEN', '');
}

function moodle_enabled(): bool
{
    return trim((string) MOODLE_URL) !== '' && trim((string) MOODLE_TOKEN) !== '';
}

/** URL base de Moodle sin barra final (normaliza host canónico www). */
function moodle_base_url(): string
{
    $base = rtrim(trim((string) MOODLE_URL), '/');
    if ($base === '') {
        return '';
    }
    $parts = parse_url($base);
    if (!is_array($parts) || empty($parts['host'])) {
        return $base;
    }
    $host = strtolower((string) $parts['host']);
    // Moodle en CNCM redirige 303 de cncm.edu.mx → www.cncm.edu.mx
    if ($host === 'cncm.edu.mx') {
        $parts['host'] = 'www.cncm.edu.mx';
        $scheme = ($parts['scheme'] ?? 'https') . '://';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = ($user !== '' || $pass !== '') ? $user . $pass . '@' : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        $base = $scheme . $auth . $parts['host'] . $port . $path . $query . $fragment;
    }
    return $base;
}

/** @return array<string,mixed> */
function moodle_api_call(string $functionName, array $params = []): array
{
    if (!moodle_enabled()) {
        return ['ok' => false, 'message' => 'Moodle no configurado'];
    }
    $base = moodle_base_url();
    $serverUrl = $base . '/webservice/rest/server.php'
        . '?wstoken=' . urlencode((string) MOODLE_TOKEN)
        . '&wsfunction=' . urlencode($functionName)
        . '&moodlewsrestformat=json';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $serverUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    if (defined('CURLOPT_POSTREDIR')) {
        curl_setopt($ch, CURLOPT_POSTREDIR, 7);
    }

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'message' => 'Error de conexión cURL: ' . $err];
    }
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    $body = trim((string) $response);
    $decoded = json_decode($body, true);

    // Varias funciones Moodle (enrol_manual_enrol_users, core_user_update_users, etc.)
    // responden HTTP 200 con cuerpo literal "null" cuando la operación fue exitosa.
    if ($http >= 200 && $http < 300 && ($body === '' || $body === 'null' || $decoded === null)) {
        return ['ok' => true, 'data' => null];
    }

    if (!is_array($decoded)) {
        $hint = '';
        if ($http === 303 || $http === 301 || $http === 302) {
            $hint = ' Moodle redirigió la petición; use MOODLE_URL con el dominio final (ej. https://www.cncm.edu.mx/courses).';
        } elseif ($http === 403) {
            $hint = ' Acceso denegado (403): revise firewall del hosting o que el token y los Web services estén activos en Moodle.';
        } elseif ($http === 404) {
            $hint = ' Ruta no encontrada: MOODLE_URL debe ser la carpeta raíz de Moodle (donde está /webservice/rest/server.php).';
        }
        $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
        if ($snippet !== '' && strlen($snippet) > 120) {
            $snippet = substr($snippet, 0, 120) . '…';
        }
        $extra = $snippet !== '' ? ' Detalle: ' . $snippet : '';
        if ($effectiveUrl !== '' && $effectiveUrl !== $serverUrl) {
            $extra .= ' URL final: ' . $effectiveUrl;
        }
        return ['ok' => false, 'message' => 'Respuesta inválida de Moodle (HTTP ' . $http . ').' . $hint . $extra];
    }
    if (!empty($decoded['exception'])) {
        $msg = (string) ($decoded['message'] ?? 'Error en Moodle');
        return ['ok' => false, 'message' => $msg, 'raw' => $decoded];
    }

    return ['ok' => true, 'data' => $decoded];
}

/** @return array{ok:bool,message:string,site?:array} */
function moodle_test_connection(): array
{
    $res = moodle_api_call('core_webservice_get_site_info', []);
    if (empty($res['ok'])) {
        return ['ok' => false, 'message' => (string) ($res['message'] ?? 'Error')];
    }
    return ['ok' => true, 'message' => 'Conexión OK', 'site' => (array) ($res['data'] ?? [])];
}

function moodle_password_inicial(): string
{
    return function_exists('cuenta_password_inicial')
        ? cuenta_password_inicial()
        : (defined('CUENTA_PASSWORD_INICIAL') ? CUENTA_PASSWORD_INICIAL : 'Cncm*1234');
}

/** Prefijo opcional para username Moodle (vacío = usar número de control tal cual, ej. 14580). */
function moodle_username_prefix(): string
{
    if (!defined('MOODLE_USERNAME_PREFIX')) {
        return '';
    }

    return strtolower(trim((string) MOODLE_USERNAME_PREFIX));
}

/** Moodle no permite @ en username; solo minúsculas, números, . _ - */
function moodle_sanitize_username(string $username): string
{
    $original = $username;
    $username = strtolower(trim($username));
    if ($username === '') {
        return '';
    }
    if (str_contains($username, '@')) {
        $username = explode('@', $username, 2)[0];
    }
    $username = (string) preg_replace('/[^a-z0-9._-]/', '', $username);
    $username = ltrim($username, '.-_');

    if ($username !== '') {
        return $username;
    }

    return 'user' . substr(md5($original !== '' ? $original : uniqid('hay', true)), 0, 8);
}

/** ID Moodle conocido por número de control (fallback si la API de búsqueda no responde). */
function moodle_user_id_from_control_map(string $numeroControl): int
{
    if (!defined('MOODLE_USER_ID_BY_CONTROL') || !is_array(MOODLE_USER_ID_BY_CONTROL)) {
        return 0;
    }
    $nc = trim($numeroControl);
    if ($nc === '') {
        return 0;
    }

    return (int) (MOODLE_USER_ID_BY_CONTROL[$nc] ?? 0);
}

/** Username Moodle para alumno (por defecto el número de control sanitizado). */
function moodle_username_from_numero_control(string $numeroControl): string
{
    $san = moodle_sanitize_username($numeroControl);
    if ($san === '') {
        $pfx = moodle_username_prefix();

        return $pfx !== '' ? $pfx . 'alumno' : 'alumno';
    }
    $pfx = moodle_username_prefix();
    if ($pfx !== '' && ctype_digit($san)) {
        return $pfx . $san;
    }

    return $san;
}

/** @return list<string> */
function moodle_username_candidates_for_payload(array $payload): array
{
    $raw = trim((string) ($payload['username'] ?? ''));
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $candidates = [];

    $add = static function (string $u) use (&$candidates): void {
        $u = moodle_sanitize_username($u);
        if ($u !== '' && !in_array($u, $candidates, true)) {
            $candidates[] = $u;
        }
    };

    if ($raw !== '') {
        $add($raw);
        if (ctype_digit(moodle_sanitize_username($raw))) {
            $add(moodle_username_from_numero_control($raw));
        }
    }
    if ($email !== '' && str_contains($email, '@')) {
        $local = explode('@', $email, 2)[0];
        $add($local);
        if (ctype_digit($local)) {
            $add(moodle_username_from_numero_control($local));
        }
    }

    return $candidates;
}

/** Normaliza campos de texto para la API de Moodle. */
function moodle_user_payload_normalize(array $payload): array
{
    $payload['username'] = moodle_sanitize_username((string) ($payload['username'] ?? ''));
    $payload['firstname'] = mb_substr(trim((string) ($payload['firstname'] ?? 'Usuario')), 0, 100);
    $payload['lastname'] = mb_substr(trim((string) ($payload['lastname'] ?? 'Alumno')), 0, 100);
    $payload['email'] = strtolower(trim((string) ($payload['email'] ?? '')));
    if ($payload['firstname'] === '') {
        $payload['firstname'] = $payload['username'] !== '' ? $payload['username'] : 'Usuario';
    }
    if ($payload['lastname'] === '') {
        $payload['lastname'] = 'Alumno';
    }

    return $payload;
}

function moodle_username_from_email(string $email, string $fallback = ''): string
{
    $email = strtolower(trim($email));
    if ($email !== '' && str_contains($email, '@')) {
        $local = explode('@', $email, 2)[0];
        $san = moodle_sanitize_username($local);
        if ($san !== '') {
            return $san;
        }
    }

    return moodle_sanitize_username($fallback);
}

/** @return array<string, string> */
function moodle_force_password_change_prefs(int $index = 0): array
{
    return [
        'users[' . $index . '][preferences][0][type]' => 'auth_forcepasswordchange',
        'users[' . $index . '][preferences][0][value]' => '1',
    ];
}

function moodle_user_payload_from_alumno(array $al): array
{
    $numeroControl = trim((string) ($al['numero_control'] ?? ''));
    $firstname = trim((string) ($al['nombres'] ?? $al['nombre'] ?? ''));
    $lastname = trim((string) ($al['apellido_paterno'] ?? $al['apellido'] ?? ''));
    $lastname2 = trim((string) ($al['apellido_materno'] ?? ''));
    $lastnameFull = trim($lastname . ($lastname2 !== '' ? ' ' . $lastname2 : ''));
    $email = strtolower(trim((string) ($al['email'] ?? '')));
    if ($email === '' && $numeroControl !== '') {
        if (function_exists('cuenta_email_alumno')) {
            $email = cuenta_email_alumno($numeroControl);
        } else {
            $email = strtolower($numeroControl) . '@' . INSTITUTIONAL_EMAIL_DOMAIN;
        }
    }
    $username = $numeroControl !== ''
        ? moodle_username_from_numero_control($numeroControl)
        : moodle_username_from_email($email, 'alumno');
    $password = moodle_password_inicial();

    return moodle_user_payload_normalize([
        'username' => $username,
        'password' => $password,
        'firstname' => $firstname !== '' ? $firstname : ($numeroControl !== '' ? $numeroControl : $username),
        'lastname' => $lastnameFull !== '' ? $lastnameFull : ($numeroControl !== '' ? $numeroControl : $username),
        'email' => $email,
        'auth' => 'manual',
        'idnumber' => $numeroControl !== '' ? $numeroControl : '',
    ]);
}

/** Indica si Moodle devolvió un fallo de lectura/escritura en BD (suele ser collation en el servidor). */
function moodle_es_error_bd(array $res): bool
{
    $msg = strtolower((string) ($res['message'] ?? ''));
    $code = strtolower((string) (($res['raw']['errorcode'] ?? '') ?: ''));
    $exc = strtolower((string) (($res['raw']['exception'] ?? '') ?: ''));

    return str_contains($msg, 'error reading from database')
        || str_contains($msg, 'error writing to database')
        || str_contains($code, 'dmlreadexception')
        || str_contains($code, 'dmlwriteexception')
        || str_contains($exc, 'dml_read_exception')
        || str_contains($exc, 'dml_write_exception');
}

/** Mensaje orientativo cuando Moodle falla por BD interna. */
function moodle_hint_error_bd(): string
{
    return 'Error interno de la base de datos de Moodle (collation/UTF-8). '
        . 'En el servidor Moodle ejecute: php admin/cli/mysql_collation.php --collation=utf8mb4_unicode_ci '
        . 'y verifique dbcollation en config.php de Moodle. '
        . 'Pruebe también crear el usuario manualmente en Administración → Usuarios; si falla igual, confirma el problema en Moodle.';
}

/** @return array{ok:bool,message:string,data?:array,id_moodle?:int,raw?:mixed,intento?:string} */
function moodle_user_create_api(array $payload, int $index = 0): array
{
    $payload = moodle_user_payload_normalize($payload);
    $pfx = 'users[' . $index . ']';
    $base = [
        $pfx . '[username]' => $payload['username'],
        $pfx . '[password]' => $payload['password'],
        $pfx . '[firstname]' => $payload['firstname'],
        $pfx . '[lastname]' => $payload['lastname'],
        $pfx . '[email]' => $payload['email'],
        $pfx . '[auth]' => $payload['auth'] ?? 'manual',
    ];
    if (!empty($payload['idnumber'])) {
        $base[$pfx . '[idnumber]'] = (string) $payload['idnumber'];
    }

    $sinAuth = $base;
    unset($sinAuth[$pfx . '[auth]']);

    $soloCreatePassword = [
        $pfx . '[username]' => $payload['username'],
        $pfx . '[firstname]' => $payload['firstname'],
        $pfx . '[lastname]' => $payload['lastname'],
        $pfx . '[email]' => $payload['email'],
        $pfx . '[createpassword]' => 1,
    ];

    $intentos = [
        ['params' => $sinAuth, 'label' => 'sin_auth'],
        ['params' => $base, 'label' => 'con_auth_manual'],
        ['params' => $soloCreatePassword, 'label' => 'solo_createpassword'],
        ['params' => array_merge($sinAuth, moodle_force_password_change_prefs($index)), 'label' => 'sin_auth_y_cambio_password'],
    ];

    $ultimo = ['ok' => false, 'message' => 'No se pudo crear usuario en Moodle', 'intentos_log' => []];
    foreach ($intentos as $intento) {
        $create = moodle_api_call('core_user_create_users', $intento['params']);
        $ultimo['intentos_log'][] = [
            'label' => $intento['label'],
            'ok' => !empty($create['ok']),
            'message' => $create['message'] ?? null,
            'raw' => $create['raw'] ?? null,
        ];
        if (!empty($create['ok'])) {
            $create['intento'] = $intento['label'];

            return $create;
        }
        $create['intento'] = $intento['label'];
        $ultimo = array_merge($ultimo, $create);
    }

    if (moodle_es_error_bd($ultimo)) {
        $ultimo['message'] = (string) ($ultimo['message'] ?? 'Error en Moodle') . ' — ' . moodle_hint_error_bd();
    }

    return $ultimo;
}

/** Rol estudiante Moodle (por defecto 5). */
function moodle_student_role_id(): int
{
    return defined('MOODLE_STUDENT_ROLE_ID') ? (int) MOODLE_STUDENT_ROLE_ID : 5;
}

/** @return array<string, int> */
function moodle_course_map_by_shortname(): array
{
    if (!defined('MOODLE_COURSE_BY_SHORTNAME') || !is_array(MOODLE_COURSE_BY_SHORTNAME)) {
        return [];
    }
    $out = [];
    foreach (MOODLE_COURSE_BY_SHORTNAME as $sn => $id) {
        $sn = trim((string) $sn);
        if ($sn !== '' && (int) $id > 1) {
            $out[strtolower($sn)] = (int) $id;
        }
    }

    return $out;
}

/** @return array<string, int> */
function moodle_course_map_by_idnumber(): array
{
    if (!defined('MOODLE_COURSE_BY_IDNUMBER') || !is_array(MOODLE_COURSE_BY_IDNUMBER)) {
        return [];
    }
    $out = [];
    foreach (MOODLE_COURSE_BY_IDNUMBER as $num => $id) {
        $num = trim((string) $num);
        if ($num !== '' && (int) $id > 1) {
            $out[$num] = (int) $id;
        }
    }

    return $out;
}

/**
 * Mapa manual cuando el token Moodle no lista cursos (permisos / categoría oculta).
 *
 * @return array{ok:bool,message:string,id?:int,shortname?:string,idnumber?:string,resuelto_por?:string,hint?:string}|null
 */
function moodle_course_map_lookup(?string $idnumber, ?string $shortname): ?array
{
    $idnumber = trim((string) $idnumber);
    $shortname = trim((string) $shortname);
    $bySn = moodle_course_map_by_shortname();
    $byNum = moodle_course_map_by_idnumber();

    if ($shortname !== '' && isset($bySn[strtolower($shortname)])) {
        $id = (int) $bySn[strtolower($shortname)];

        return [
            'ok' => true,
            'message' => 'Curso desde MOODLE_COURSE_BY_SHORTNAME',
            'id' => $id,
            'shortname' => $shortname,
            'idnumber' => $idnumber,
            'resuelto_por' => 'config_shortname',
            'hint' => 'ID interno ' . $id . ' (config.local.php)',
        ];
    }

    if ($idnumber !== '' && isset($byNum[$idnumber])) {
        $id = (int) $byNum[$idnumber];

        return [
            'ok' => true,
            'message' => 'Curso desde MOODLE_COURSE_BY_IDNUMBER',
            'id' => $id,
            'shortname' => $shortname,
            'idnumber' => $idnumber,
            'resuelto_por' => 'config_idnumber',
            'hint' => 'idnumber ' . $idnumber . ' → ID interno ' . $id . ' (config.local.php)',
        ];
    }

    return null;
}

function moodle_es_invalidparameter(array $res): bool
{
    $code = strtolower((string) (($res['raw']['errorcode'] ?? '') ?: ''));
    $msg = strtolower((string) ($res['message'] ?? ''));

    return $code === 'invalidparameter' || str_contains($msg, 'invalid parameter');
}

/** @return list<array<string,mixed>> */
function moodle_courses_raw(): array
{
    if (!moodle_enabled()) {
        return [];
    }
    $res = moodle_api_call('core_course_get_courses', []);
    if (empty($res['ok']) || !is_array($res['data'] ?? null)) {
        return [];
    }

    $out = [];
    foreach ($res['data'] as $c) {
        if (!is_array($c) || (int) ($c['id'] ?? 0) <= 1) {
            continue;
        }
        $out[] = $c;
    }

    return $out;
}

/** @return list<array<string,mixed>> */
function moodle_list_courses(): array
{
    $out = [];
    foreach (moodle_courses_raw() as $c) {
        $out[] = [
            'id' => (int) $c['id'],
            'shortname' => (string) ($c['shortname'] ?? ''),
            'fullname' => (string) ($c['fullname'] ?? ''),
            'idnumber' => (string) ($c['idnumber'] ?? ''),
            'visible' => (int) ($c['visible'] ?? 1),
        ];
    }
    usort($out, static fn($a, $b) => strcmp($a['shortname'], $b['shortname']));

    return $out;
}

/**
 * Busca curso recorriendo core_course_get_courses (incluye idnumber).
 *
 * @return array{ok:bool,message:string,id?:int,shortname?:string,idnumber?:string,resuelto_por?:string,hint?:string}
 */
function moodle_course_scan_catalog(int $courseId, string $shortname = ''): array
{
    $shortname = trim($shortname);
    $courses = moodle_list_courses();
    if ($courses === []) {
        return ['ok' => false, 'message' => 'Moodle no devolvió cursos visibles para el token'];
    }

    if ($shortname !== '') {
        foreach ($courses as $c) {
            if (strcasecmp((string) ($c['shortname'] ?? ''), $shortname) === 0) {
                return [
                    'ok' => true,
                    'message' => 'Curso encontrado',
                    'id' => (int) $c['id'],
                    'shortname' => (string) $c['shortname'],
                    'idnumber' => (string) ($c['idnumber'] ?? ''),
                    'resuelto_por' => 'shortname_scan',
                ];
            }
        }
    }

    if ($courseId > 0) {
        $needle = (string) $courseId;
        foreach ($courses as $c) {
            $idNum = trim((string) ($c['idnumber'] ?? ''));
            if ($idNum !== '' && $idNum === $needle) {
                return [
                    'ok' => true,
                    'message' => 'Curso encontrado',
                    'id' => (int) $c['id'],
                    'shortname' => (string) ($c['shortname'] ?? ''),
                    'idnumber' => $idNum,
                    'resuelto_por' => 'idnumber_scan',
                    'hint' => 'El valor ' . $courseId . ' es idnumber en Moodle; el ID interno real es ' . (int) $c['id'],
                ];
            }
        }
    }

    if ($courseId > 1) {
        foreach ($courses as $c) {
            if ((int) ($c['id'] ?? 0) === $courseId) {
                return [
                    'ok' => true,
                    'message' => 'Curso encontrado',
                    'id' => $courseId,
                    'shortname' => (string) ($c['shortname'] ?? ''),
                    'idnumber' => (string) ($c['idnumber'] ?? ''),
                    'resuelto_por' => 'course_id_scan',
                ];
            }
        }
    }

    return ['ok' => false, 'message' => 'Sin coincidencias en el catálogo visible de Moodle'];
}

/** @return array{ok:bool,message:string,id?:int,shortname?:string,idnumber?:string,resuelto_por?:string} */
function moodle_course_find_by_field(string $field, string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return ['ok' => false, 'message' => 'Valor vacío'];
    }

    $res = moodle_api_call('core_course_get_courses_by_field', [
        'field' => $field,
        'value' => $value,
    ]);
    if (!empty($res['ok'])) {
        $courses = (array) (($res['data']['courses'] ?? []) ?: []);
        foreach ($courses as $c) {
            if (!is_array($c)) {
                continue;
            }
            $id = (int) ($c['id'] ?? 0);
            if ($id > 1) {
                return [
                    'ok' => true,
                    'message' => 'Curso encontrado',
                    'id' => $id,
                    'shortname' => (string) ($c['shortname'] ?? ''),
                    'idnumber' => (string) ($c['idnumber'] ?? ''),
                    'resuelto_por' => $field,
                ];
            }
        }
    }

    return ['ok' => false, 'message' => 'Sin coincidencias para ' . $field . '=' . $value];
}

/** @return array{ok:bool,message:string,id?:int,shortname?:string,idnumber?:string,resuelto_por?:string} */
function moodle_course_find_by_shortname(string $shortname): array
{
    $shortname = trim($shortname);
    if ($shortname === '') {
        return ['ok' => false, 'message' => 'Nombre corto vacío'];
    }

    foreach ([$shortname, strtoupper($shortname), strtolower($shortname), ucfirst(strtolower($shortname))] as $try) {
        $byField = moodle_course_find_by_field('shortname', $try);
        if (!empty($byField['ok'])) {
            return $byField;
        }
    }

    foreach (moodle_list_courses() as $c) {
        if (strcasecmp($c['shortname'], $shortname) === 0) {
            return [
                'ok' => true,
                'message' => 'Curso encontrado',
                'id' => (int) $c['id'],
                'shortname' => (string) $c['shortname'],
                'resuelto_por' => 'shortname_list',
            ];
        }
    }

    return ['ok' => false, 'message' => 'Curso no encontrado en Moodle: ' . $shortname];
}

/**
 * Resuelve ID interno de curso Moodle (no confundir con "Número ID del curso" / idnumber en Moodle).
 *
 * @return array{ok:bool,message:string,id?:int,shortname?:string,idnumber?:string,resuelto_por?:string,hint?:string}
 */
function moodle_course_resolve_id(int $courseId, ?string $shortname = null): array
{
    $shortname = trim((string) $shortname);
    if ($shortname !== '') {
        $bySn = moodle_course_find_by_shortname($shortname);
        if (!empty($bySn['ok'])) {
            return [
                'ok' => true,
                'id' => (int) $bySn['id'],
                'shortname' => (string) ($bySn['shortname'] ?? $shortname),
                'idnumber' => (string) ($bySn['idnumber'] ?? ''),
                'resuelto_por' => (string) ($bySn['resuelto_por'] ?? 'shortname'),
            ];
        }
    }

    if ($courseId > 1) {
        $byId = moodle_course_find_by_field('id', (string) $courseId);
        if (!empty($byId['ok']) && (int) ($byId['id'] ?? 0) === $courseId) {
            return [
                'ok' => true,
                'id' => $courseId,
                'shortname' => (string) ($byId['shortname'] ?? ''),
                'idnumber' => (string) ($byId['idnumber'] ?? ''),
                'resuelto_por' => 'course_id',
            ];
        }

        // Muy común: en Moodle se copia "Número ID del curso" (idnumber=4) pensando que es el ID interno.
        $byIdNumber = moodle_course_find_by_field('idnumber', (string) $courseId);
        if (!empty($byIdNumber['ok'])) {
            return [
                'ok' => true,
                'id' => (int) $byIdNumber['id'],
                'shortname' => (string) ($byIdNumber['shortname'] ?? ''),
                'idnumber' => (string) ($byIdNumber['idnumber'] ?? ''),
                'resuelto_por' => 'idnumber',
                'hint' => 'El valor ' . $courseId . ' es idnumber en Moodle; el ID interno real es ' . (int) $byIdNumber['id'],
            ];
        }

        foreach (moodle_list_courses() as $c) {
            if ((int) $c['id'] === $courseId) {
                return [
                    'ok' => true,
                    'id' => $courseId,
                    'shortname' => (string) ($c['shortname'] ?? ''),
                    'idnumber' => (string) ($c['idnumber'] ?? ''),
                    'resuelto_por' => 'course_id_list',
                ];
            }
        }
    }

    $scan = moodle_course_scan_catalog($courseId, $shortname);
    if (!empty($scan['ok'])) {
        return $scan;
    }

    $detalle = 'id=' . $courseId;
    if ($shortname !== '') {
        $detalle .= ', shortname=' . $shortname;
    }
    $visibles = moodle_list_courses();
    $ejemplos = array_slice(array_map(static function (array $c): string {
        $idNum = trim((string) ($c['idnumber'] ?? ''));

        return ($c['shortname'] ?? '') . ' (id=' . ($c['id'] ?? '')
            . ($idNum !== '' ? ', idnumber=' . $idNum : '') . ')';
    }, $visibles), 0, 8);

    return [
        'ok' => false,
        'message' => 'Curso Moodle no encontrado (' . $detalle . ')',
        'hint' => 'Configure idnumber y shortname en Exámenes de ubicación; HAY resolverá el ID interno automáticamente.',
        'cursos_visibles' => count($visibles),
        'ejemplos' => $ejemplos,
    ];
}

/**
 * Resuelve curso Moodle para exámenes de ubicación.
 * Prioridad: shortname → idnumber (visible en Moodle) → ID interno guardado.
 *
 * @return array{ok:bool,message:string,id?:int,shortname?:string,idnumber?:string,resuelto_por?:string,hint?:string}
 */
function moodle_course_resolve_for_examen(?string $idnumber, ?string $shortname, int $courseIdFallback = 0): array
{
    $idnumber = trim((string) $idnumber);
    $shortname = trim((string) $shortname);

    $mapped = moodle_course_map_lookup($idnumber, $shortname);
    if ($mapped !== null) {
        return $mapped;
    }

    if ($shortname !== '') {
        $bySn = moodle_course_find_by_shortname($shortname);
        if (!empty($bySn['ok'])) {
            return [
                'ok' => true,
                'id' => (int) $bySn['id'],
                'shortname' => (string) ($bySn['shortname'] ?? $shortname),
                'idnumber' => (string) ($bySn['idnumber'] ?? $idnumber),
                'resuelto_por' => 'shortname',
            ];
        }
    }

    if ($idnumber !== '') {
        $byField = moodle_course_find_by_field('idnumber', $idnumber);
        if (!empty($byField['ok'])) {
            return [
                'ok' => true,
                'id' => (int) $byField['id'],
                'shortname' => (string) ($byField['shortname'] ?? $shortname),
                'idnumber' => (string) ($byField['idnumber'] ?? $idnumber),
                'resuelto_por' => 'idnumber',
                'hint' => 'idnumber ' . $idnumber . ' → ID interno Moodle ' . (int) $byField['id'],
            ];
        }

        foreach (moodle_list_courses() as $c) {
            if (trim((string) ($c['idnumber'] ?? '')) === $idnumber) {
                return [
                    'ok' => true,
                    'id' => (int) $c['id'],
                    'shortname' => (string) ($c['shortname'] ?? $shortname),
                    'idnumber' => $idnumber,
                    'resuelto_por' => 'idnumber_scan',
                    'hint' => 'idnumber ' . $idnumber . ' → ID interno Moodle ' . (int) $c['id'],
                ];
            }
        }
    }

    if ($courseIdFallback >= 100) {
        return [
            'ok' => true,
            'id' => $courseIdFallback,
            'shortname' => $shortname,
            'idnumber' => $idnumber,
            'resuelto_por' => 'course_id_interno_guardado',
        ];
    }

    if ($courseIdFallback > 1) {
        $byStored = moodle_course_resolve_id($courseIdFallback, $shortname !== '' ? $shortname : null);
        if (!empty($byStored['ok'])) {
            return $byStored;
        }
    }

    $mapped = moodle_course_map_lookup($idnumber, $shortname);
    if ($mapped !== null) {
        return $mapped;
    }

    $detalle = [];
    if ($idnumber !== '') {
        $detalle[] = 'idnumber=' . $idnumber;
    }
    if ($shortname !== '') {
        $detalle[] = 'shortname=' . $shortname;
    }
    if ($courseIdFallback > 0) {
        $detalle[] = 'course_id=' . $courseIdFallback;
    }
    $visibles = count(moodle_list_courses());

    return [
        'ok' => false,
        'message' => 'Curso Moodle no encontrado' . ($detalle !== [] ? ' (' . implode(', ', $detalle) . ')' : ''),
        'hint' => 'El token Moodle devolvió ' . $visibles . ' curso(s). Si Exam no aparece, agregue en config.local.php: '
            . 'MOODLE_COURSE_BY_SHORTNAME y MOODLE_COURSE_BY_IDNUMBER (ej. Exam→168, 4→168).',
        'cursos_visibles' => $visibles,
    ];
}

/** @return array{ok:bool,message:string,raw?:mixed} */
function moodle_user_already_enrolled_in_course(int $moodleUserId, int $courseId): array
{
    if ($moodleUserId <= 0 || $courseId <= 0) {
        return ['ok' => false, 'message' => 'Parámetros inválidos'];
    }

    $res = moodle_api_call('core_enrol_get_users_courses', ['userid' => $moodleUserId]);
    if (empty($res['ok'])) {
        return ['ok' => false, 'message' => (string) ($res['message'] ?? 'No se pudo consultar cursos del usuario')];
    }

    foreach ((array) ($res['data'] ?? []) as $c) {
        if (is_array($c) && (int) ($c['id'] ?? 0) === $courseId) {
            return ['ok' => true, 'message' => 'Ya inscrito en el curso'];
        }
    }

    return ['ok' => false, 'message' => 'No inscrito'];
}

function moodle_hint_enrol_invalidparameter(int $courseId, int $roleId): string
{
    return 'Revise en Moodle: (1) ID del curso en Exámenes de ubicación, (2) inscripción manual activa en el curso '
        . '(Administración del curso → Usuarios → Métodos de inscripción → Inscripción manual), '
        . '(3) rol estudiante (roleid=' . $roleId . '). Curso #' . $courseId . '.';
}

/**
 * Inscribe manualmente a un usuario Moodle en un curso.
 *
 * @return array{ok:bool,message:string,raw?:mixed,course_id?:int,role_id?:int}
 */
function moodle_enrol_user_in_course(int $moodleUserId, int $courseId, ?int $roleId = null): array
{
    if (!moodle_enabled()) {
        return ['ok' => false, 'message' => 'Moodle no configurado'];
    }
    if ($moodleUserId <= 0 || $courseId <= 0) {
        return ['ok' => false, 'message' => 'Usuario o curso Moodle inválido (userid=' . $moodleUserId . ', courseid=' . $courseId . ')'];
    }

    $ya = moodle_user_already_enrolled_in_course($moodleUserId, $courseId);
    if (!empty($ya['ok'])) {
        return ['ok' => true, 'message' => 'Ya inscrito en curso Moodle', 'course_id' => $courseId];
    }

    $roleId = $roleId ?? moodle_student_role_id();
    $res = moodle_api_call('enrol_manual_enrol_users', [
        'enrolments[0][roleid]' => $roleId,
        'enrolments[0][userid]' => $moodleUserId,
        'enrolments[0][courseid]' => $courseId,
    ]);
    if (!empty($res['ok'])) {
        return ['ok' => true, 'message' => 'Inscrito en curso Moodle', 'course_id' => $courseId, 'role_id' => $roleId];
    }

    $msg = (string) ($res['message'] ?? 'No se pudo inscribir en Moodle');
    $code = strtolower((string) (($res['raw']['errorcode'] ?? '') ?: ''));
    if (str_contains(strtolower($msg), 'invalid parameter') || $code === 'invalidparameter') {
        $msg .= ' — ' . moodle_hint_enrol_invalidparameter($courseId, $roleId);
    }

    return [
        'ok' => false,
        'message' => $msg,
        'raw' => $res['raw'] ?? null,
        'course_id' => $courseId,
        'role_id' => $roleId,
    ];
}

/**
 * Localiza un alumno por id_alumno o número de control/matrícula.
 * Si no está en el plantel de sesión, busca en otros planteles (diagnóstico).
 *
 * @return array{
 *   ok:bool,
 *   message:string,
 *   id_alumno?:int,
 *   id_plantel?:int,
 *   alumno?:array,
 *   resuelto_por?:string,
 *   diagnostico?:array
 * }
 */
function moodle_alumno_resolver(PDO $pdo, string $ref, int $idPlantelSesion): array
{
    $ref = trim($ref);
    if ($ref === '') {
        return ['ok' => false, 'message' => 'Indique id_alumno o numero_control'];
    }

    $selectCols = 'id_alumno, id_plantel, numero_control, nombres, apellido_paterno, apellido_materno, email';

    $fetchEnPlantel = static function (PDO $pdo, int $idAlumno, int $idPlantel) use ($selectCols): ?array {
        $st = $pdo->prepare(
            "SELECT {$selectCols} FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1"
        );
        $st->execute([$idAlumno, $idPlantel]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    };

    if ($idPlantelSesion > 0 && function_exists('pago_buscar_alumno_control')) {
        $byRef = pago_buscar_alumno_control($pdo, $ref, $idPlantelSesion);
        if ($byRef) {
            $idAlumno = (int) ($byRef['id_alumno'] ?? 0);
            $idPlantel = (int) ($byRef['id_plantel'] ?? $idPlantelSesion);
            $al = $fetchEnPlantel($pdo, $idAlumno, $idPlantel) ?: $byRef;

            return [
                'ok' => true,
                'id_alumno' => $idAlumno,
                'id_plantel' => $idPlantel,
                'alumno' => $al,
                'resuelto_por' => 'plantel_sesion',
            ];
        }
    }

    $globalSt = $pdo->prepare(
        "SELECT {$selectCols} FROM alumnos
         WHERE numero_control = ? OR matricula = ?"
    );
    $globalSt->execute([$ref, $ref]);
    $porControl = $globalSt->fetchAll(PDO::FETCH_ASSOC);

    if (count($porControl) === 1) {
        $al = $porControl[0];

        return [
            'ok' => true,
            'id_alumno' => (int) $al['id_alumno'],
            'id_plantel' => (int) $al['id_plantel'],
            'alumno' => $al,
            'resuelto_por' => 'numero_control',
        ];
    }

    if (ctype_digit($ref)) {
        $idNum = (int) $ref;
        if ($idNum > 0) {
            $st = $pdo->prepare("SELECT {$selectCols} FROM alumnos WHERE id_alumno = ? LIMIT 1");
            $st->execute([$idNum]);
            $al = $st->fetch(PDO::FETCH_ASSOC);
            if ($al) {
                return [
                    'ok' => true,
                    'id_alumno' => $idNum,
                    'id_plantel' => (int) $al['id_plantel'],
                    'alumno' => $al,
                    'resuelto_por' => 'id_alumno',
                ];
            }
        }
    }

    $diag = [
        'ref' => $ref,
        'id_plantel_sesion' => $idPlantelSesion,
        'hint' => 'Use el id interno (columna data-id al editar alumno) o el número de control con ?control=14578',
    ];

    if (count($porControl) > 1) {
        $diag['coincidencias'] = array_map(static function (array $r): array {
            return [
                'id_alumno' => (int) ($r['id_alumno'] ?? 0),
                'id_plantel' => (int) ($r['id_plantel'] ?? 0),
                'numero_control' => (string) ($r['numero_control'] ?? ''),
            ];
        }, $porControl);
        $diag['hint'] = 'Hay varios alumnos con ese número de control; indique id_plantel o id_alumno exacto.';

        return ['ok' => false, 'message' => 'Varios alumnos coinciden', 'diagnostico' => $diag];
    }

    if (ctype_digit($ref)) {
        $st = $pdo->prepare(
            'SELECT id_alumno, id_plantel, numero_control FROM alumnos
             WHERE id_alumno = ? OR numero_control = ? OR matricula = ? LIMIT 5'
        );
        $st->execute([(int) $ref, $ref, $ref]);
        $cerca = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($cerca) {
            $diag['sugerencias'] = $cerca;
        }
    }

    return ['ok' => false, 'message' => 'Alumno no encontrado', 'diagnostico' => $diag];
}

/** Referencia de alumno desde GET (id_alumno, control o numero_control). */
function moodle_test_ref_alumno_desde_request(): string
{
    foreach (['id_alumno', 'control', 'numero_control'] as $key) {
        $v = trim((string) ($_GET[$key] ?? ''));
        if ($v !== '') {
            return $v;
        }
    }

    return '';
}

/** ID Moodle guardado en alumnos.moodle_user_id (evita depender solo de la API de búsqueda). */
function moodle_alumno_ensure_schema(PDO $pdo): void
{
    if (function_exists('alumno_ensure_schema')) {
        alumno_ensure_schema($pdo);
    }
    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'alumnos', 'moodle_user_id', 'INT UNSIGNED NULL', 'id_usuario');
    }
}

function moodle_user_id_from_alumno_row(array $al): int
{
    return (int) ($al['moodle_user_id'] ?? 0);
}

function moodle_user_id_save(PDO $pdo, int $idAlumno, int $idMoodle): void
{
    if ($idAlumno <= 0 || $idMoodle <= 0) {
        return;
    }
    moodle_alumno_ensure_schema($pdo);
    $pdo->prepare('UPDATE alumnos SET moodle_user_id = ? WHERE id_alumno = ? AND (moodle_user_id IS NULL OR moodle_user_id = 0 OR moodle_user_id = ?)')
        ->execute([$idMoodle, $idAlumno, $idMoodle]);
}

/** Persiste id Moodle en alumnos cuando la operación fue exitosa. */
function moodle_user_ensure_finish(PDO $pdo, int $idAlumno, array $result): array
{
    if (!empty($result['ok']) && (int) ($result['id_moodle'] ?? 0) > 0) {
        moodle_user_id_save($pdo, $idAlumno, (int) $result['id_moodle']);
    }

    return $result;
}

/** @return array{ok:bool,message:string,id_moodle?:int} */
function moodle_user_ensure_alumno(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    if (!moodle_enabled()) {
        return ['ok' => false, 'message' => 'Moodle no configurado'];
    }

    moodle_alumno_ensure_schema($pdo);

    $st = $pdo->prepare(
        'SELECT id_alumno, numero_control, nombres, apellido_paterno, apellido_materno, email, moodle_user_id
         FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$idAlumno, $idPlantel]);
    $al = $st->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    $idStored = moodle_user_id_from_alumno_row($al);
    if ($idStored > 0) {
        return moodle_user_ensure_finish($pdo, $idAlumno, [
            'ok' => true,
            'message' => 'Usuario Moodle desde registro HAY (moodle_user_id)',
            'id_moodle' => $idStored,
        ]);
    }

    $payload = moodle_user_payload_from_alumno($al);
    if (trim((string) $payload['username']) === '') {
        return ['ok' => false, 'message' => 'Alumno sin número de control'];
    }

    $findDiag = moodle_user_find_for_payload($payload);
    $users = (array) ($findDiag['users'] ?? []);
    if (!empty($users)) {
        $idM = (int) ($users[0]['id'] ?? 0);
        if ($idM > 0) {
            return moodle_user_ensure_finish($pdo, $idAlumno, [
                'ok' => true,
                'message' => (string) ($findDiag['message'] ?? 'Usuario Moodle encontrado'),
                'id_moodle' => $idM,
                'find_attempts' => $findDiag['attempts'] ?? null,
            ]);
        }
    }

    $idMap = moodle_user_id_from_control_map((string) ($al['numero_control'] ?? ''));
    if ($idMap > 0) {
        return moodle_user_ensure_finish($pdo, $idAlumno, [
            'ok' => true,
            'message' => 'Usuario Moodle resuelto por mapa MOODLE_USER_ID_BY_CONTROL',
            'id_moodle' => $idMap,
            'find_attempts' => $findDiag['attempts'] ?? null,
            'aviso' => 'La API no localizó al usuario; revise permisos del servicio web en Moodle.',
        ]);
    }

    $create = moodle_user_create_api($payload, 0);
    if (empty($create['ok'])) {
        $findRetry = moodle_user_find_for_payload($payload);
        $idRetry = (int) ($findRetry['id_moodle'] ?? 0);
        if ($idRetry > 0) {
            return moodle_user_ensure_finish($pdo, $idAlumno, [
                'ok' => true,
                'message' => 'Usuario Moodle ya existía (detectado tras crear)',
                'id_moodle' => $idRetry,
                'find_attempts' => $findRetry['attempts'] ?? null,
            ]);
        }

        $msg = (string) ($create['message'] ?? 'No se pudo crear usuario en Moodle');
        if (!empty($findDiag['message'])) {
            $msg .= ' | Búsqueda: ' . $findDiag['message'];
        }

        $idMap = moodle_user_id_from_control_map((string) ($al['numero_control'] ?? ''));
        if ($idMap > 0) {
            return moodle_user_ensure_finish($pdo, $idAlumno, [
                'ok' => true,
                'message' => 'Usuario Moodle resuelto por mapa MOODLE_USER_ID_BY_CONTROL',
                'id_moodle' => $idMap,
                'find_attempts' => $findDiag['attempts'] ?? null,
                'aviso' => 'La API no localizó al usuario; revise permisos del servicio web en Moodle.',
            ]);
        }

        $msgLower = strtolower($msg);
        $probableDuplicado = str_contains($msgLower, 'invalid parameter')
            || str_contains($msgLower, 'invalidparameter');
        $hint = $probableDuplicado
            ? 'El usuario probablemente YA EXISTE en Moodle (p. ej. username '
                . ($payload['username'] ?? '')
                . ' en mdl_user) pero core_user_get_users_by_field no lo devuelve. '
                . 'Revise en Moodle: Servicios web → funciones core_user_get_users_by_field y core_user_get_users '
                . 'en el servicio del token, y que el usuario del token tenga permiso para ver usuarios (moodle/user:viewalldetails). '
                . 'Mientras tanto puede definir MOODLE_USER_ID_BY_CONTROL en config.local.php.'
            : 'Si el alumno ya está en Moodle, confirme correo '
                . $payload['email']
                . ' y habilite core_user_get_users_by_field. Si no existe, revise política de contraseñas.';

        return [
            'ok' => false,
            'message' => $msg,
            'tipo' => 'moodle_usuario',
            'moodle_raw' => $create['raw'] ?? null,
            'find_attempts' => $findDiag['attempts'] ?? null,
            'payload' => $payload,
            'moodle_intento' => $create['intento'] ?? null,
            'intentos_log' => $create['intentos_log'] ?? null,
            'probable_usuario_duplicado' => $probableDuplicado,
            'hint' => $hint,
        ];
    }
    $created = (array) ($create['data'] ?? []);
    $idM = (int) (($created[0]['id'] ?? 0) ?: 0);

    return moodle_user_ensure_finish($pdo, $idAlumno, [
        'ok' => true,
        'message' => 'Usuario Moodle creado',
        'id_moodle' => $idM,
    ]);
}

/** @return array{ok:bool,message:string} */
function moodle_user_reset_password(string $username, string $password): array
{
    if (!moodle_enabled()) {
        return ['ok' => false, 'message' => 'Moodle no configurado'];
    }

    $find = moodle_api_call('core_user_get_users', [
        'criteria[0][key]' => 'username',
        'criteria[0][value]' => $username,
    ]);
    if (empty($find['ok'])) {
        return ['ok' => false, 'message' => (string) ($find['message'] ?? 'No se pudo consultar usuario en Moodle')];
    }
    $users = (array) (($find['data']['users'] ?? []) ?: []);
    if (empty($users) || empty($users[0]['id'])) {
        return ['ok' => false, 'message' => 'Usuario no existe en Moodle'];
    }
    $idM = (int) $users[0]['id'];

    $upd = moodle_api_call('core_user_update_users', array_merge([
        'users[0][id]' => $idM,
        'users[0][password]' => $password,
    ], moodle_force_password_change_prefs(0)));
    if (empty($upd['ok'])) {
        return ['ok' => false, 'message' => (string) ($upd['message'] ?? 'No se pudo restablecer password en Moodle')];
    }
    return ['ok' => true, 'message' => 'Password Moodle actualizado'];
}

/** @return array{ok:bool,message:string} */
function moodle_usuario_set_suspendido(PDO $pdo, int $idUsuario, bool $suspendido): array
{
    if (!moodle_enabled()) {
        return ['ok' => true, 'message' => 'Moodle no configurado (omitido)'];
    }
    if ($idUsuario <= 0) {
        return ['ok' => false, 'message' => 'Usuario no válido'];
    }

    $st = $pdo->prepare('SELECT username, email, moodle_user_id FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return ['ok' => false, 'message' => 'Usuario HAY no encontrado'];
    }

    $idM = (int) ($u['moodle_user_id'] ?? 0);
    if ($idM <= 0) {
        $username = moodle_sanitize_username((string) ($u['username'] ?? ''));
        if ($username === '' && !empty($u['email'])) {
            $username = moodle_username_from_email((string) $u['email'], $username);
        }
        if ($username !== '') {
            $find = moodle_api_call('core_user_get_users', [
                'criteria[0][key]' => 'username',
                'criteria[0][value]' => $username,
            ]);
            if (!empty($find['ok'])) {
                $users = (array) (($find['data']['users'] ?? []) ?: []);
                $idM = (int) ($users[0]['id'] ?? 0);
            }
        }
    }

    if ($idM <= 0) {
        return ['ok' => true, 'message' => 'Sin usuario Moodle vinculado (omitido)'];
    }

    $upd = moodle_api_call('core_user_update_users', [
        'users[0][id]' => $idM,
        'users[0][suspended]' => $suspendido ? 1 : 0,
    ]);
    if (empty($upd['ok'])) {
        return ['ok' => false, 'message' => (string) ($upd['message'] ?? 'No se pudo actualizar Moodle')];
    }

    if (!$suspendido && $idM > 0) {
        $pdo->prepare('UPDATE usuarios SET moodle_user_id = ? WHERE id_usuario = ? AND (moodle_user_id IS NULL OR moodle_user_id = 0)')
            ->execute([$idM, $idUsuario]);
    }

    return [
        'ok' => true,
        'message' => $suspendido ? 'Usuario Moodle suspendido' : 'Usuario Moodle reactivado',
    ];
}

/** Obtiene usuario Moodle por ID interno. */
function moodle_user_get_by_id(int $idMoodle): array
{
    if ($idMoodle <= 0 || !moodle_enabled()) {
        return ['ok' => false, 'message' => 'ID Moodle inválido'];
    }

    $find = moodle_user_find_by_field('id', (string) $idMoodle);
    if (!empty($find['users'][0])) {
        return ['ok' => true, 'user' => $find['users'][0]];
    }

    return [
        'ok' => false,
        'message' => (string) ($find['message'] ?? 'Usuario Moodle no encontrado'),
    ];
}

/** Actualiza campos de un usuario Moodle (username, email, etc.). */
function moodle_user_update_fields(int $idMoodle, array $fields): array
{
    if ($idMoodle <= 0 || !moodle_enabled()) {
        return ['ok' => false, 'message' => 'Moodle no configurado o ID inválido'];
    }

    $params = ['users[0][id]' => $idMoodle];
    if (!empty($fields['username'])) {
        $params['users[0][username]'] = moodle_sanitize_username((string) $fields['username']);
    }
    if (!empty($fields['email'])) {
        $params['users[0][email]'] = strtolower(trim((string) $fields['email']));
    }
    if (!empty($fields['firstname'])) {
        $params['users[0][firstname]'] = mb_substr(trim((string) $fields['firstname']), 0, 100);
    }
    if (!empty($fields['lastname'])) {
        $params['users[0][lastname]'] = mb_substr(trim((string) $fields['lastname']), 0, 100);
    }
    if (isset($fields['idnumber']) && $fields['idnumber'] !== '') {
        $params['users[0][idnumber]'] = (string) $fields['idnumber'];
    }

    if (count($params) <= 1) {
        return ['ok' => false, 'message' => 'Sin campos para actualizar'];
    }

    $upd = moodle_api_call('core_user_update_users', $params);
    if (empty($upd['ok'])) {
        return [
            'ok' => false,
            'message' => (string) ($upd['message'] ?? 'No se pudo actualizar usuario Moodle'),
            'raw' => $upd['raw'] ?? null,
        ];
    }

    return ['ok' => true, 'message' => 'Usuario Moodle actualizado'];
}

/** @return list<array<string,mixed>> */
function moodle_user_extract_list_from_api(array $res): array
{
    if (empty($res['ok'])) {
        return [];
    }
    $data = $res['data'] ?? [];
    if (!is_array($data)) {
        return [];
    }
    if (isset($data['users']) && is_array($data['users'])) {
        return $data['users'];
    }
    if (isset($data[0]) && is_array($data[0])) {
        return $data;
    }

    return [];
}

/** @return array{ok:bool,users:array,message?:string,raw?:mixed,metodo?:string} */
function moodle_user_find_by_field(string $field, string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return ['ok' => false, 'users' => [], 'message' => 'Valor vacío'];
    }

    $byField = moodle_api_call('core_user_get_users_by_field', [
        'field' => $field,
        'values[0]' => $value,
    ]);
    $users = moodle_user_extract_list_from_api($byField);
    if ($users !== []) {
        return ['ok' => true, 'users' => $users, 'metodo' => 'get_users_by_field'];
    }

    $byCriteria = moodle_api_call('core_user_get_users', [
        'criteria[0][key]' => $field,
        'criteria[0][value]' => $value,
    ]);
    $users = moodle_user_extract_list_from_api($byCriteria);
    if ($users !== []) {
        return ['ok' => true, 'users' => $users, 'metodo' => 'get_users'];
    }

    $err = $byField;
    if (empty($err['ok']) && !empty($byCriteria['ok'])) {
        $err = $byCriteria;
    }

    $apiOk = !empty($byField['ok']) || !empty($byCriteria['ok']);
    $sinPermisoVer = $apiOk && $users === [] && empty($err['raw']['exception']);

    return [
        'ok' => $apiOk,
        'users' => [],
        'message' => $apiOk
            ? ($sinPermisoVer
                ? 'Sin coincidencias para ' . $field . '=' . $value
                    . ' (la API respondió OK pero vacía: el usuario del token suele carecer de moodle/user:viewdetails)'
                : 'Sin coincidencias en Moodle para ' . $field . '=' . $value)
            : (string) ($err['message'] ?? 'Error al buscar usuario en Moodle'),
        'raw' => $err['raw'] ?? ($byField['raw'] ?? null),
        'metodo' => 'get_users_by_field',
        'by_field_ok' => !empty($byField['ok']),
        'by_criteria_ok' => !empty($byCriteria['ok']),
    ];
}

/**
 * Busca usuario Moodle por correo (prioridad), username e idnumber.
 *
 * @return array{ok:bool,users:array,id_moodle?:int,message?:string,metodo?:string,attempts?:array}
 */
function moodle_user_find_for_payload(array $payload): array
{
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $attempts = [];

    $tries = [['field' => 'email', 'value' => $email]];
    foreach (moodle_username_candidates_for_payload($payload) as $uname) {
        $tries[] = ['field' => 'username', 'value' => $uname];
        $tries[] = ['field' => 'idnumber', 'value' => $uname];
    }
    if (!empty($payload['idnumber'])) {
        $tries[] = ['field' => 'idnumber', 'value' => (string) $payload['idnumber']];
    }

    foreach ($tries as $try) {
        if ($try['value'] === '') {
            continue;
        }
        $find = moodle_user_find_by_field($try['field'], $try['value']);
        $attempts[] = [
            'field' => $try['field'],
            'value' => $try['value'],
            'ok' => !empty($find['users']),
            'message' => $find['message'] ?? null,
            'metodo' => $find['metodo'] ?? null,
            'by_field_ok' => $find['by_field_ok'] ?? null,
            'by_criteria_ok' => $find['by_criteria_ok'] ?? null,
            'raw' => $find['raw'] ?? null,
        ];
        if (!empty($find['users'])) {
            $idM = (int) ($find['users'][0]['id'] ?? 0);

            return [
                'ok' => true,
                'users' => $find['users'],
                'id_moodle' => $idM,
                'message' => 'Usuario encontrado por ' . $try['field'],
                'metodo' => $find['metodo'] ?? null,
                'attempts' => $attempts,
            ];
        }
    }

    return [
        'ok' => false,
        'users' => [],
        'message' => 'Usuario no encontrado en Moodle (correo, username ni idnumber)',
        'attempts' => $attempts,
    ];
}

function moodle_user_find_by_username_or_email(string $username, string $email = ''): array
{
    $found = moodle_user_find_for_payload([
        'username' => $username,
        'email' => $email,
    ]);

    return (array) ($found['users'] ?? []);
}

/** Payload Moodle para usuario del personal. */
function moodle_user_payload_from_staff(array $u): array
{
    $email = strtolower(trim((string) ($u['email'] ?? '')));
    if ($email === '' || strpos($email, '@') === false) {
        $email = 'staff' . (int) ($u['id_usuario'] ?? 0) . '@' . INSTITUTIONAL_EMAIL_DOMAIN;
    }

    return [
        'username' => moodle_username_from_email($email, 'staff' . (int) ($u['id_usuario'] ?? 0)),
        'password' => moodle_password_inicial(),
        'firstname' => trim((string) ($u['nombre'] ?? 'Staff')),
        'lastname' => trim((string) ($u['apellido'] ?? '')),
        'email' => $email,
        'auth' => 'manual',
    ];
}

/** @return array{ok:bool,message:string,id_moodle?:int} */
function moodle_user_ensure_staff(PDO $pdo, int $idUsuario): array
{
    if (!moodle_enabled()) {
        return ['ok' => true, 'message' => 'Moodle no configurado (omitido)'];
    }

    $st = $pdo->prepare(
        'SELECT id_usuario, nombre, apellido, email FROM usuarios WHERE id_usuario = ? LIMIT 1'
    );
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return ['ok' => false, 'message' => 'Usuario no encontrado'];
    }

    $payload = moodle_user_payload_from_staff($u);
    $users = moodle_user_find_by_username_or_email($payload['username'], $payload['email']);
    if (!empty($users)) {
        $idM = (int) ($users[0]['id'] ?? 0);
        if ($idM > 0) {
            cuenta_digital_ensure_schema($pdo);
            $pdo->prepare('UPDATE usuarios SET moodle_user_id = ? WHERE id_usuario = ?')->execute([$idM, $idUsuario]);

            return ['ok' => true, 'message' => 'Usuario Moodle existente', 'id_moodle' => $idM];
        }
    }

    $create = moodle_user_create_api($payload, 0);
    if (empty($create['ok'])) {
        return [
            'ok' => false,
            'message' => (string) ($create['message'] ?? 'No se pudo crear usuario en Moodle'),
            'moodle_raw' => $create['raw'] ?? null,
        ];
    }
    $created = (array) ($create['data'] ?? []);
    $idM = (int) (($created[0]['id'] ?? 0) ?: 0);
    if ($idM > 0) {
        cuenta_digital_ensure_schema($pdo);
        $pdo->prepare('UPDATE usuarios SET moodle_user_id = ? WHERE id_usuario = ?')->execute([$idM, $idUsuario]);
    }

    return ['ok' => true, 'message' => 'Usuario Moodle creado', 'id_moodle' => $idM];
}

/**
 * Calificación de un usuario en curso Moodle (para Know-how / capacitaciones).
 *
 * @return array{ok:bool, grade?:float, message?:string}
 */
function moodle_grade_for_user_course(int $moodleUserId, int $courseId): array
{
    if (!moodle_enabled() || $moodleUserId <= 0 || $courseId <= 0) {
        return ['ok' => false, 'message' => 'Moodle no configurado o IDs inválidos'];
    }
    $res = moodle_api_call('gradereport_user_get_grade_items', [
        'courseid' => $courseId,
        'userid' => $moodleUserId,
    ]);
    if (empty($res['ok'])) {
        return ['ok' => false, 'message' => $res['message'] ?? 'Error Moodle'];
    }
    $items = $res['data']['usergrades'][0]['gradeitems'] ?? [];
    $best = null;
    foreach ($items as $it) {
        if (!isset($it['graderaw'])) {
            continue;
        }
        $g = (float) $it['graderaw'];
        if ($best === null || $g > $best) {
            $best = $g;
        }
    }
    if ($best === null) {
        return ['ok' => false, 'message' => 'Sin calificación en el curso'];
    }

    return ['ok' => true, 'grade' => $best];
}

/**
 * Resuelve opción HAY según calificación Moodle (mapeo lineal simple; configurable después).
 */
function moodle_resolver_opcion_por_nota(PDO $pdo, int $idAspecto, float $nota, float $notaMax = 100.0): ?int
{
    $st = $pdo->prepare(
        'SELECT id_opcion, puntos FROM hay_opcion
         WHERE id_aspecto = ? AND activo = 1 AND origen = \'moodle\'
         ORDER BY puntos ASC'
    );
    $st->execute([$idAspecto]);
    $opts = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$opts) {
        return null;
    }
    $pct = $notaMax > 0 ? min(1.0, max(0.0, $nota / $notaMax)) : 0;
    $idx = (int) floor($pct * (count($opts) - 1));

    return (int) ($opts[$idx]['id_opcion'] ?? $opts[0]['id_opcion']);
}

/**
 * Sincroniza respuestas automáticas Moodle para un periodo de evaluación (Fase 4).
 */
function hay_eval_sync_moodle_respuestas(PDO $pdo, int $idEval, int $idUsuario): array
{
    if (!function_exists('hay_eval_obtener_periodo')) {
        require_once __DIR__ . '/hay_eval_helper.php';
    }
    $eval = hay_eval_obtener_periodo($pdo, $idEval);
    if (!$eval) {
        return ['ok' => false, 'message' => 'Evaluación no encontrada'];
    }
    $st = $pdo->prepare(
        'SELECT a.id_aspecto, o.moodle_course_id FROM hay_aspecto a
         INNER JOIN hay_opcion o ON o.id_aspecto = a.id_aspecto AND o.origen = \'moodle\'
         WHERE a.activo = 1 AND o.moodle_course_id IS NOT NULL AND o.activo = 1'
    );
    $st->execute();
    $aplicadas = 0;
    $u = $pdo->prepare('SELECT email FROM usuarios WHERE id_usuario = ?');
    $u->execute([$idUsuario]);
    $email = $u->fetchColumn();
    if (!$email) {
        return ['ok' => false, 'message' => 'Usuario sin email para Moodle'];
    }
    $find = moodle_api_call('core_user_get_users', [
        'criteria[0][key]' => 'email',
        'criteria[0][value]' => $email,
    ]);
    if (empty($find['ok']) || empty($find['data']['users'][0]['id'])) {
        return ['ok' => false, 'message' => 'Usuario no encontrado en Moodle'];
    }
    $moodleUid = (int) $find['data']['users'][0]['id'];
    $respuestas = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $gr = moodle_grade_for_user_course($moodleUid, (int) $row['moodle_course_id']);
        if (!$gr['ok']) {
            continue;
        }
        $idOp = moodle_resolver_opcion_por_nota($pdo, (int) $row['id_aspecto'], (float) $gr['grade']);
        if ($idOp) {
            $respuestas[(int) $row['id_aspecto']] = $idOp;
            $aplicadas++;
        }
    }
    if ($respuestas) {
        hay_eval_guardar_respuestas($pdo, $idEval, $respuestas);
    }

    return ['ok' => true, 'aplicadas' => $aplicadas, 'message' => 'Sincronizadas ' . $aplicadas . ' aspectos desde Moodle'];
}

