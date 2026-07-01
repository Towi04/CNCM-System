<?php

define('AVATAR_UPLOAD_DIR', 'uploads/avatars');
define('AVATAR_MAX_BYTES', 2 * 1024 * 1024);

/** Asegura columna avatar en usuarios (migración ligera). */
function user_avatar_ensure_schema(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }
    plantel_ensure_column($pdo, 'usuarios', 'avatar', 'VARCHAR(255) NULL', 'rol');
}

/** Normaliza ruta guardada en BD (siempre relativa uploads/avatars/...). */
function user_avatar_normalize_stored_path(?string $avatar): string
{
    $avatar = trim((string) $avatar);
    if ($avatar === '') {
        return '';
    }

    $legacyDefaults = [
        'default_avatar.png',
        'default_avatar.jpg',
        'icono.png',
        'src/icono.png',
    ];
    if (in_array(strtolower($avatar), array_map('strtolower', $legacyDefaults), true)) {
        return '';
    }

    if (preg_match('#^https?://#i', $avatar)) {
        $path = parse_url($avatar, PHP_URL_PATH);
        $avatar = $path !== false && $path !== null ? (string) $path : $avatar;
    }

    $avatar = ltrim(str_replace('\\', '/', $avatar), '/');
    if (function_exists('hay_web_root')) {
        $root = trim((string) hay_web_root(), '/');
        if ($root !== '' && $root !== '/' && stripos($avatar, $root . '/') === 0) {
            $avatar = substr($avatar, strlen($root) + 1);
        }
    }

    return $avatar;
}
define('AVATAR_ALLOWED_MIME', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
]);

/**
 * Normaliza la ruta del avatar del usuario para la UI.
 * Devuelve URL relativa válida o null (mostrar iniciales).
 */
function user_avatar_src(?string $avatar): ?string
{
    $avatar = trim((string) $avatar);
    $legacyDefaults = [
        '',
        'default_avatar.png',
        'default_avatar.jpg',
        'icono.png',
        'src/icono.png',
    ];

    if (in_array(strtolower($avatar), array_map('strtolower', $legacyDefaults), true)) {
        return null;
    }

    if (preg_match('#^https?://#i', $avatar)) {
        return $avatar;
    }

    $root = dirname(__DIR__);
    $candidates = [$avatar];
    if (strpos($avatar, '/') === false) {
        $candidates[] = 'src/' . $avatar;
    }

    foreach ($candidates as $rel) {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        if ($rel !== '' && is_file($root . '/' . $rel)) {
            return $rel;
        }
    }

    return null;
}

/** URL pública del avatar (con prefijo del sitio si aplica). */
function user_avatar_public_url(?string $avatar): ?string
{
    $avatar = user_avatar_normalize_stored_path($avatar);
    if ($avatar === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $avatar)) {
        return $avatar;
    }

    $rel = ltrim(str_replace('\\', '/', $avatar), '/');
    if (user_avatar_is_uploaded_path($rel)) {
        $abs = dirname(__DIR__) . '/' . $rel;
        if (!is_file($abs)) {
            return null;
        }

        return function_exists('hay_asset_url') ? hay_asset_url($rel) : $rel;
    }

    $src = user_avatar_src($avatar);
    if ($src === null) {
        return null;
    }

    return function_exists('hay_asset_url') ? hay_asset_url($src) : $src;
}

function user_avatar_is_uploaded_path(?string $path): bool
{
    $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
    return strpos($path, AVATAR_UPLOAD_DIR . '/') === 0;
}

function user_avatar_upload_dir_abs(): string
{
    $dir = dirname(__DIR__) . '/' . AVATAR_UPLOAD_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function user_avatar_delete_file(?string $relativePath): void
{
    if (!$relativePath || !user_avatar_is_uploaded_path($relativePath)) {
        return;
    }
    $abs = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
    if (is_file($abs)) {
        @unlink($abs);
    }
}

function user_avatar_iniciales(array $user): string
{
    $n = !empty($user['nombre']) ? mb_substr($user['nombre'], 0, 1) : 'U';
    $a = !empty($user['apellido']) ? mb_substr($user['apellido'], 0, 1) : '';
    return strtoupper($n . $a);
}

/**
 * @return array{ok: bool, message: string, path?: string}
 */
function user_avatar_save_upload(int $userId, array $file): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'message' => 'Usuario no válido'];
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'message' => 'La imagen supera el tamaño máximo permitido (2 MB)'];
        }
        if ($err !== UPLOAD_ERR_OK && $err !== UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'message' => 'Error al subir el archivo (código ' . $err . ')'];
        }
        return ['ok' => false, 'message' => 'No se recibió ninguna imagen. Elija un archivo JPG, PNG, WebP o GIF.'];
    }
    if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
        $err = (int) $file['error'];
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'message' => 'La imagen supera el tamaño máximo permitido (2 MB)'];
        }
        return ['ok' => false, 'message' => 'Error al subir el archivo (código ' . $err . ')'];
    }
    if (!empty($file['size']) && (int) $file['size'] > AVATAR_MAX_BYTES) {
        return ['ok' => false, 'message' => 'La imagen no debe superar 2 MB'];
    }

    $val = hay_upload_validar($file, AVATAR_ALLOWED_MIME, AVATAR_MAX_BYTES, true);
    if (!$val['ok']) {
        return ['ok' => false, 'message' => $val['message'] ?? 'Archivo no válido'];
    }

    $ext = $val['ext'];
    $dir = user_avatar_upload_dir_abs();
    hay_upload_preparar_directorio($dir, 'images');
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['ok' => false, 'message' => 'No se puede escribir en la carpeta de avatares (uploads/avatars). Contacte al administrador.'];
    }
    $basename = 'user_' . $userId;
    $dest = $dir . '/' . $basename . '.' . $ext;
    $relative = AVATAR_UPLOAD_DIR . '/' . $basename . '.' . $ext;

    foreach (glob($dir . '/user_' . $userId . '.*') ?: [] as $old) {
        if (is_file($old) && realpath($old) !== realpath($dest)) {
            @unlink($old);
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'message' => 'No se pudo guardar la imagen. Verifique permisos de uploads/avatars en el servidor.'];
    }

    $fin = hay_upload_finalizar_en_disco($dest, (string) $val['mime'], true);
    if (!$fin['ok']) {
        @unlink($dest);

        return ['ok' => false, 'message' => $fin['message'] ?? 'Imagen no válida'];
    }
    if (!empty($fin['filename'])) {
        $relative = AVATAR_UPLOAD_DIR . '/' . $fin['filename'];
        $dest = $dir . '/' . $fin['filename'];
    }

    if (!is_file($dest) || filesize($dest) <= 0) {
        @unlink($dest);

        return ['ok' => false, 'message' => 'La imagen no se guardó en el servidor. Revise permisos de la carpeta uploads/avatars.'];
    }

    return ['ok' => true, 'message' => 'Foto actualizada', 'path' => $relative, 'abs' => $dest];
}

function user_avatar_remove(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'message' => 'Usuario no válido'];
    }

    $stmt = $pdo->prepare('SELECT avatar FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $stmt->execute([$userId]);
    $current = $stmt->fetchColumn();

    user_avatar_delete_file($current !== false ? (string) $current : null);

    $upd = $pdo->prepare('UPDATE usuarios SET avatar = ? WHERE id_usuario = ?');
    $upd->execute(['', $userId]);

    user_avatar_refresh_session($pdo, $userId);

    return ['ok' => true, 'message' => 'Foto de perfil eliminada'];
}

function user_avatar_refresh_session(PDO $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT avatar FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stmt->execute([$userId]);
        $fetched = $stmt->fetchColumn();
        $raw = trim($fetched !== false ? (string) $fetched : '');
        $path = user_avatar_normalize_stored_path($raw);
        if ($path === '' && user_avatar_is_uploaded_path($raw)) {
            $path = ltrim(str_replace('\\', '/', $raw), '/');
        }
        $_SESSION['avatar'] = $path;
        if ($path !== '' && $path !== $raw && user_avatar_is_uploaded_path($path)) {
            $fix = $pdo->prepare('UPDATE usuarios SET avatar = ? WHERE id_usuario = ?');
            $fix->execute([$path, $userId]);
        } elseif ($path === '' && $raw !== '' && !user_avatar_is_uploaded_path($raw)) {
            $_SESSION['avatar'] = '';
        }
    } catch (PDOException $e) {
        $_SESSION['avatar'] = $_SESSION['avatar'] ?? '';
    }
}
