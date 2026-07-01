<?php
/**
 * Integración Google Workspace (Admin SDK Directory API).
 * Requiere: composer require google/apiclient + google_key.json (cuenta de servicio).
 *
 * En config.local.php (opcional):
 *   define('GOOGLE_KEY_FILE', __DIR__ . '/google_key.json');
 *   define('GOOGLE_ADMIN_EMAIL', 'admin@cncm.edu.mx');  // delegación domain-wide
 *   define('GOOGLE_WORKSPACE_DOMAIN', 'cncm.edu.mx');
 */

if (!defined('GOOGLE_KEY_FILE')) {
    define('GOOGLE_KEY_FILE', (defined('HAY_ROOT') ? HAY_ROOT : dirname(__DIR__)) . '/google_key.json');
}
if (!defined('GOOGLE_ADMIN_EMAIL')) {
    define('GOOGLE_ADMIN_EMAIL', 'englishcoordinator@cncm.edu.mx');
}
if (!defined('GOOGLE_WORKSPACE_DOMAIN')) {
    define('GOOGLE_WORKSPACE_DOMAIN', 'cncm.edu.mx');
}

/** @var bool|null */
$googleAutoloadOk = null;

/** @var string|null */
$googleAutoloadPath = null;

/** Rutas candidatas para vendor/autoload.php (orden de preferencia). */
function google_autoload_candidates(): array
{
    $root = defined('HAY_ROOT') ? (string) HAY_ROOT : dirname(__DIR__);
    $candidates = [];
    if (defined('HAY_COMPOSER_AUTOLOAD') && HAY_COMPOSER_AUTOLOAD !== '') {
        $candidates[] = (string) HAY_COMPOSER_AUTOLOAD;
    }
    $candidates[] = rtrim($root, '/\\') . '/vendor/autoload.php';
    $candidates[] = dirname(__DIR__) . '/vendor/autoload.php';
    // Por si vendor quedó un nivel arriba del docroot (algunos hostings).
    $candidates[] = dirname($root) . '/vendor/autoload.php';

    return array_values(array_unique($candidates));
}

/** Autoload mínimo de google/apiclient v1.x si falta el autoload de Composer. */
function google_load_legacy_apiclient(string $root): bool
{
    $legacy = rtrim($root, '/\\') . '/vendor/google/apiclient/src/Google/autoload.php';
    if (!is_file($legacy)) {
        return false;
    }
    require_once $legacy;

    return class_exists('Google_Client');
}

function google_autoload_path(): ?string
{
    global $googleAutoloadPath;
    if ($googleAutoloadPath !== null) {
        return $googleAutoloadPath !== '' ? $googleAutoloadPath : null;
    }
    foreach (google_autoload_candidates() as $path) {
        if (is_file($path)) {
            $googleAutoloadPath = $path;

            return $path;
        }
    }
    $googleAutoloadPath = '';

    return null;
}

function google_autoload_ok(): bool
{
    global $googleAutoloadOk;
    if ($googleAutoloadOk !== null) {
        return $googleAutoloadOk;
    }

    $path = google_autoload_path();
    if ($path !== null) {
        require_once $path;
        $googleAutoloadOk = class_exists('Google_Client');

        return $googleAutoloadOk;
    }

    $root = defined('HAY_ROOT') ? (string) HAY_ROOT : dirname(__DIR__);
    if (google_load_legacy_apiclient($root)) {
        $googleAutoloadOk = true;
        global $googleAutoloadPath;
        $googleAutoloadPath = rtrim($root, '/\\') . '/vendor/google/apiclient/src/Google/autoload.php';

        return true;
    }

    $googleAutoloadOk = false;

    return false;
}

/** @return array<string, mixed> */
function google_autoload_diagnostico(): array
{
    global $googleAutoloadOk;
    $root = defined('HAY_ROOT') ? (string) HAY_ROOT : dirname(__DIR__);
    $vendorDir = rtrim($root, '/\\') . '/vendor';
    $candidates = google_autoload_candidates();
    $found = [];
    foreach ($candidates as $p) {
        $found[$p] = is_file($p);
    }

    return [
        'hay_root' => $root,
        'vendor_dir' => $vendorDir,
        'vendor_dir_existe' => is_dir($vendorDir),
        'autoload_candidatos' => $found,
        'autoload_usado' => google_autoload_path() ?: ($googleAutoloadOk ? 'legacy-google-apiclient' : null),
        'google_client_disponible' => google_autoload_ok(),
    ];
}

function google_key_path(): string
{
    return (string) GOOGLE_KEY_FILE;
}

function google_enabled(): bool
{
    return is_file(google_key_path()) && google_autoload_ok();
}

/** @return array{ok:bool,message:string} */
function google_config_status(): array
{
    if (!is_file(google_key_path())) {
        return ['ok' => false, 'message' => 'No se encontró google_key.json en ' . google_key_path()];
    }
    if (!google_autoload_ok()) {
        $diag = google_autoload_diagnostico();
        $msg = 'No se encontró vendor/autoload.php.';
        if (empty($diag['vendor_dir_existe'])) {
            $msg .= ' La carpeta vendor/ no está en ' . ($diag['vendor_dir'] ?? 'HAY_ROOT/vendor')
                . '. Suba la carpeta vendor completa (FTP) o ejecute composer install en el servidor.';
        } else {
            $msg .= ' Existe vendor/ pero falta autoload.php — vuelva a subir vendor/ completa desde su PC.';
        }

        return array_merge(['ok' => false, 'message' => $msg], $diag);
    }
    $json = json_decode((string) file_get_contents(google_key_path()), true);
    if (!is_array($json) || ($json['type'] ?? '') !== 'service_account') {
        return ['ok' => false, 'message' => 'google_key.json no es una clave de cuenta de servicio válida.'];
    }
    if (trim((string) GOOGLE_ADMIN_EMAIL) === '') {
        return ['ok' => false, 'message' => 'Defina GOOGLE_ADMIN_EMAIL en config.local.php (administrador para delegación).'];
    }

    return [
        'ok' => true,
        'message' => 'Configuración local OK',
        'client_email' => (string) ($json['client_email'] ?? ''),
        'admin_delegado' => (string) GOOGLE_ADMIN_EMAIL,
        'dominio' => (string) GOOGLE_WORKSPACE_DOMAIN,
    ];
}

/**
 * Cliente autenticado con delegación domain-wide (cuenta de servicio actúa como admin).
 *
 * @param list<string>|string $scopes
 * @return Google_Client|null
 */
function google_client($scopes = null)
{
    if (!google_enabled()) {
        return null;
    }

    $scopeList = $scopes ?? [Google_Service_Directory::ADMIN_DIRECTORY_USER_READONLY];
    if (!is_array($scopeList)) {
        $scopeList = [$scopeList];
    }

    $json = json_decode((string) file_get_contents(google_key_path()));
    if (!$json || empty($json->client_email) || empty($json->private_key)) {
        return null;
    }

    $client = new Google_Client();
    $client->setApplicationName(app_display_name());

    $cred = new Google_Auth_AssertionCredentials(
        (string) $json->client_email,
        $scopeList,
        (string) $json->private_key,
        'notasecret',
        'http://oauth.net/grant_type/jwt/1.0/bearer',
        (string) GOOGLE_ADMIN_EMAIL
    );
    $client->setAssertionCredentials($cred);

    return $client;
}

/** Scope delegado en Google Admin (domain-wide). No usar .readonly si no está en la delegación. */
function google_directory_scope(bool $write = false): string
{
    return Google_Service_Directory::ADMIN_DIRECTORY_USER;
}

/** @return Google_Service_Directory|null */
function google_directory_service(bool $write = false)
{
    $client = google_client(google_directory_scope($write));
    if (!$client) {
        return null;
    }

    return new Google_Service_Directory($client);
}

/**
 * Prueba conexión: lista 1 usuario del dominio (solo lectura).
 *
 * @return array{ok:bool,message:string,detail?:array}
 */
function google_test_connection(): array
{
    $cfg = google_config_status();
    if (!$cfg['ok']) {
        return ['ok' => false, 'message' => $cfg['message']];
    }

    try {
        $dir = google_directory_service(false);
        if (!$dir) {
            return ['ok' => false, 'message' => 'No se pudo inicializar el cliente de Google.'];
        }

        $domain = trim((string) GOOGLE_WORKSPACE_DOMAIN);
        $result = $dir->users->listUsers([
            'domain' => $domain,
            'maxResults' => 1,
            'orderBy' => 'email',
        ]);

        $total = null;
        if (is_object($result) && method_exists($result, 'getUsers')) {
            $users = $result->getUsers();
            $total = is_array($users) ? count($users) : 0;
        }

        return [
            'ok' => true,
            'message' => 'Conexión con Google Workspace OK (Admin SDK Directory).',
            'detail' => [
                'dominio' => $domain,
                'admin_delegado' => (string) GOOGLE_ADMIN_EMAIL,
                'client_email' => $cfg['client_email'] ?? '',
                'muestra_usuarios' => $total,
            ],
        ];
    } catch (Google_Service_Exception $e) {
        return ['ok' => false, 'message' => google_parse_api_error($e)];
    } catch (Google_Auth_Exception $e) {
        return ['ok' => false, 'message' => google_parse_api_error($e)];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => google_parse_api_error($e)];
    }
}

function google_parse_api_error(Throwable $e): string
{
    $raw = $e->getMessage();
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) && preg_match('/\{.*"error".*\}/s', $raw, $m)) {
        $decoded = json_decode($m[0], true);
    }
    if (is_array($decoded)) {
        $err = $decoded['error'] ?? $decoded;
        $code = is_array($err) ? ($err['code'] ?? '') : (string) $err;
        $msg = is_array($err) ? ($err['message'] ?? '') : '';
        if ($msg === '' && !empty($decoded['error_description'])) {
            $msg = (string) $decoded['error_description'];
        }
        if ($code === 'unauthorized_client' || str_contains(strtolower($msg), 'unauthorized')) {
            $clientId = '';
            if (is_file(google_key_path())) {
                $j = json_decode((string) file_get_contents(google_key_path()), true);
                $clientId = (string) ($j['client_id'] ?? '');
            }
            $hint = 'Configure delegación domain-wide en Google Admin: Seguridad → '
                . 'Controles de API → Delegación en todo el dominio → Añadir cliente. '
                . 'Client ID: ' . ($clientId !== '' ? $clientId : '(ver google_key.json)')
                . ' · Scope: https://www.googleapis.com/auth/admin.directory.user'
                . ' (si delegó solo ese scope, no añada .readonly por separado en el código).';

            return 'Google no autorizó la cuenta de servicio. ' . $hint;
        }
        if ($msg !== '') {
            return 'Error de Google API: ' . $msg;
        }
    }

    if (str_contains(strtolower($raw), 'unauthorized_client')) {
        return google_parse_api_error(new Exception('{"error":"unauthorized_client"}'));
    }

    return 'Error de Google: ' . $raw;
}

/**
 * Comprueba si un correo ya existe en Google Workspace.
 *
 * @return array{ok:bool,existe:bool,message?:string}
 */
function google_usuario_existe(string $email): array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return ['ok' => false, 'existe' => false, 'message' => 'Correo vacío'];
    }

    $cfg = google_config_status();
    if (!$cfg['ok']) {
        return ['ok' => false, 'existe' => false, 'message' => $cfg['message']];
    }

    try {
        $dir = google_directory_service(false);
        if (!$dir) {
            return ['ok' => false, 'existe' => false, 'message' => 'No se pudo inicializar el cliente de Google.'];
        }

        $dir->users->get($email);

        return ['ok' => true, 'existe' => true];
    } catch (Google_Service_Exception $e) {
        $code = (int) $e->getCode();
        if ($code === 404) {
            return ['ok' => true, 'existe' => false];
        }

        return ['ok' => false, 'existe' => false, 'message' => google_parse_api_error($e)];
    } catch (Throwable $e) {
        return ['ok' => false, 'existe' => false, 'message' => google_parse_api_error($e)];
    }
}

/**
 * Crea un usuario en Google Workspace.
 *
 * @param array{nombre:string,apellido:string,email_solicitado:string,password_inicial:string} $datosUsuario
 * @return array{ok:bool,message:string,email?:string}
 */
function google_crear_usuario(array $datosUsuario): array
{
    $cfg = google_config_status();
    if (!$cfg['ok']) {
        return ['ok' => false, 'message' => $cfg['message']];
    }

    $nombre = trim((string) ($datosUsuario['nombre'] ?? ''));
    $apellido = trim((string) ($datosUsuario['apellido'] ?? ''));
    $email = strtolower(trim((string) ($datosUsuario['email_solicitado'] ?? '')));
    $password = (string) ($datosUsuario['password_inicial'] ?? '');

    if ($nombre === '' || $apellido === '' || $email === '' || $password === '') {
        return ['ok' => false, 'message' => 'Nombre, apellido, correo y contraseña inicial son obligatorios.'];
    }

    try {
        $dir = google_directory_service(true);
        if (!$dir) {
            return ['ok' => false, 'message' => 'No se pudo inicializar el cliente de Google.'];
        }

        $name = new Google_Service_Directory_UserName();
        $name->setGivenName($nombre);
        $name->setFamilyName($apellido);

        $user = new Google_Service_Directory_User();
        $user->setName($name);
        $user->setPrimaryEmail($email);
        $user->setPassword($password);
        $user->setChangePasswordAtNextLogin(true);

        $created = $dir->users->insert($user);

        return [
            'ok' => true,
            'message' => 'Usuario creado en Google Workspace.',
            'email' => $created->getPrimaryEmail(),
        ];
    } catch (Google_Service_Exception $e) {
        return ['ok' => false, 'message' => google_parse_api_error($e)];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Error general: ' . $e->getMessage()];
    }
}

/**
 * Restablece contraseña de un usuario en Google Workspace.
 *
 * @return array{ok:bool,message:string,email?:string}
 */
function google_reset_password(string $email, string $password): array
{
    $email = strtolower(trim($email));
    if ($email === '' || $password === '') {
        return ['ok' => false, 'message' => 'Correo y contraseña son obligatorios'];
    }

    $cfg = google_config_status();
    if (!$cfg['ok']) {
        return ['ok' => false, 'message' => $cfg['message']];
    }

    try {
        $dir = google_directory_service(true);
        if (!$dir) {
            return ['ok' => false, 'message' => 'No se pudo inicializar el cliente de Google.'];
        }

        $user = new Google_Service_Directory_User();
        $user->setPassword($password);
        $user->setChangePasswordAtNextLogin(true);
        $dir->users->update($email, $user);

        return [
            'ok' => true,
            'message' => 'Contraseña de Google restablecida. El alumno deberá cambiarla en el primer acceso.',
            'email' => $email,
        ];
    } catch (Google_Service_Exception $e) {
        return ['ok' => false, 'message' => google_parse_api_error($e)];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Error general: ' . $e->getMessage()];
    }
}

/**
 * Suspende o reactiva un usuario en Google Workspace.
 *
 * @return array{ok:bool,message:string,email?:string}
 */
function google_usuario_set_suspendido(string $email, bool $suspendido): array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return ['ok' => false, 'message' => 'Correo vacío'];
    }

    $cfg = google_config_status();
    if (!$cfg['ok']) {
        return ['ok' => true, 'message' => 'Google no configurado (omitido)', 'email' => $email];
    }

    try {
        $dir = google_directory_service(true);
        if (!$dir) {
            return ['ok' => false, 'message' => 'No se pudo inicializar el cliente de Google.'];
        }

        $user = new Google_Service_Directory_User();
        $user->setSuspended($suspendido);
        $dir->users->update($email, $user);

        return [
            'ok' => true,
            'message' => $suspendido ? 'Cuenta Google suspendida' : 'Cuenta Google reactivada',
            'email' => $email,
        ];
    } catch (Google_Service_Exception $e) {
        return ['ok' => false, 'message' => google_parse_api_error($e)];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Error Google: ' . $e->getMessage()];
    }
}
