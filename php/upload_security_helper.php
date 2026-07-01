<?php

/**
 * Validación y almacenamiento seguro de archivos subidos.
 * - MIME real (finfo), magic bytes, escaneo de contenido malicioso
 * - Extensión derivada del MIME (nunca del nombre del cliente)
 * - Re-codificación de imágenes (elimina payloads embebidos)
 * - .htaccess en carpetas de uploads (bloqueo de ejecución)
 */

if (!defined('HAY_UPLOAD_HTACCESS')) {
    define('HAY_UPLOAD_HTACCESS', true);
}

/** @var array<string, string> */
const HAY_UPLOAD_MIME_IMAGE = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

/** @var array<string, string> */
const HAY_UPLOAD_MIME_IMAGE_PDF = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];

/** @var array<string, string> */
const HAY_UPLOAD_MIME_PDF = [
    'application/pdf' => 'pdf',
];

/**
 * Valida un upload antes de guardarlo.
 *
 * @param array<string, string> $allowedMimes mime => ext
 * @return array{ok: bool, message?: string, mime?: string, ext?: string}
 */
function hay_upload_validar(array $file, array $allowedMimes, int $maxBytes, bool $requerido = true): array
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $requerido
            ? ['ok' => false, 'message' => 'No se recibió ningún archivo']
            : ['ok' => true, 'mime' => '', 'ext' => ''];
    }

    $err = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($err !== UPLOAD_ERR_OK) {
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'message' => 'El archivo supera el tamaño máximo permitido'];
        }

        return ['ok' => false, 'message' => 'Error al subir el archivo (código ' . $err . ')'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'Archivo no válido'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return ['ok' => false, 'message' => 'El archivo está vacío'];
    }
    if ($size > $maxBytes) {
        $mb = round($maxBytes / (1024 * 1024), 1);

        return ['ok' => false, 'message' => 'El archivo supera ' . $mb . ' MB'];
    }

    $nombreCliente = (string) ($file['name'] ?? '');
    if (str_contains($nombreCliente, "\0") || preg_match('/\.(php|phtml|phar|cgi|pl|asp|aspx|jsp|sh|exe|bat|cmd)(\.|$)/i', $nombreCliente)) {
        return ['ok' => false, 'message' => 'Nombre de archivo no permitido'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if ($mime === '' || !isset($allowedMimes[$mime])) {
        return ['ok' => false, 'message' => 'Formato de archivo no permitido'];
    }

    if (!hay_upload_verificar_magic_bytes($file['tmp_name'], $mime)) {
        return ['ok' => false, 'message' => 'El contenido del archivo no coincide con su tipo'];
    }

    if (!hay_upload_escanear_contenido($file['tmp_name'], $mime)) {
        return ['ok' => false, 'message' => 'El archivo contiene contenido no permitido'];
    }

    return ['ok' => true, 'mime' => $mime, 'ext' => $allowedMimes[$mime]];
}

/**
 * Guarda un archivo validado en disco de forma segura.
 *
 * @param array<string, string> $allowedMimes
 * @return array{ok: bool, message?: string, path?: string, abs?: string, filename?: string}
 */
function hay_upload_guardar(
    array $file,
    string $absDir,
    string $basename,
    array $allowedMimes,
    int $maxBytes,
    bool $requerido = true,
    bool $reencodeImage = true
): array {
    $val = hay_upload_validar($file, $allowedMimes, $maxBytes, $requerido);
    if (!$val['ok']) {
        if (!$requerido && ($val['message'] ?? '') === 'No se recibió ningún archivo') {
            return ['ok' => true, 'path' => null];
        }

        return ['ok' => false, 'message' => $val['message'] ?? 'Archivo no válido'];
    }
    if (empty($val['mime'])) {
        return ['ok' => true, 'path' => null];
    }

    hay_upload_preparar_directorio($absDir, hay_upload_tipo_directorio($allowedMimes));

    $basename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $basename) ?: 'archivo';
    $filename = $basename . '.' . $val['ext'];
    $dest = rtrim(str_replace('\\', '/', $absDir), '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'message' => 'No se pudo guardar el archivo en el servidor'];
    }

    $fin = hay_upload_finalizar_en_disco($dest, (string) $val['mime'], $reencodeImage);
    if (!$fin['ok']) {
        @unlink($dest);

        return $fin;
    }

    if ($fin['filename'] ?? '') {
        $filename = (string) $fin['filename'];
        $dest = rtrim(str_replace('\\', '/', $absDir), '/') . '/' . $filename;
    }

    return [
        'ok' => true,
        'message' => 'Archivo guardado',
        'path' => $filename,
        'abs' => $dest,
        'filename' => $filename,
    ];
}

/** @param array<string, string> $allowedMimes */
function hay_upload_tipo_directorio(array $allowedMimes): string
{
    $onlyPdf = count($allowedMimes) === 1 && isset($allowedMimes['application/pdf']);
    $hasPdf = isset($allowedMimes['application/pdf']);
    if ($onlyPdf) {
        return 'pdf';
    }
    if ($hasPdf) {
        return 'mixed';
    }

    return 'images';
}

function hay_upload_preparar_directorio(string $absDir, string $tipo = 'mixed'): void
{
    if (!is_dir($absDir)) {
        @mkdir($absDir, 0755, true);
    }
    if (HAY_UPLOAD_HTACCESS) {
        hay_upload_escribir_htaccess($absDir, $tipo);
    }
}

function hay_upload_escribir_htaccess(string $absDir, string $tipo = 'mixed'): void
{
    if (!is_dir($absDir) || !is_writable($absDir)) {
        return;
    }
    $path = rtrim($absDir, '/\\') . '/.htaccess';
    $mtime = is_file($path) ? (int) filemtime($path) : 0;
    if ($mtime > 0 && (time() - $mtime) < 86400) {
        return;
    }

    $bloqueo = <<<'HT'
# HAY — bloqueo de ejecución de scripts en uploads
<FilesMatch "\.(?i:php|phtml|php3|php4|php5|php7|php8|phps|phar|cgi|pl|asp|aspx|jsp|sh|exe|bat|cmd)$">
    Require all denied
</FilesMatch>
Options -Indexes -ExecCGI
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .phar
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .phar

HT;

    $extra = match ($tipo) {
        'images' => "<FilesMatch \"\\.(?i:jpe?g|png|gif|webp)$\">\n    Require all granted\n</FilesMatch>\n",
        'pdf' => "<FilesMatch \"\\.(?i:pdf)$\">\n    Require all granted\n</FilesMatch>\n",
        default => '',
    };

    @file_put_contents($path, $bloqueo . $extra);
}

function hay_upload_verificar_magic_bytes(string $path, string $mime): bool
{
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return false;
    }
    $head = fread($fh, 16) ?: '';
    fclose($fh);

    return match ($mime) {
        'image/jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
        'image/png' => str_starts_with($head, "\x89PNG\r\n\x1a\n"),
        'image/gif' => str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a'),
        'image/webp' => str_starts_with($head, 'RIFF') && str_contains(substr($head, 0, 16), 'WEBP'),
        'application/pdf' => str_starts_with($head, '%PDF'),
        default => false,
    };
}

function hay_upload_escanear_contenido(string $path, string $mime): bool
{
    if ($mime === 'application/pdf') {
        $chunk = (string) @file_get_contents($path, false, null, 0, 65536);

        return !preg_match('/\/JavaScript|\/JS|\/OpenAction|\/AA\s/i', $chunk);
    }

    $chunk = (string) @file_get_contents($path, false, null, 0, 8192);
    if ($chunk === '') {
        return true;
    }
    $lower = strtolower($chunk);

    return !preg_match('/<\?(?:php|=)|<\?|script\s|eval\s*\(|base64_decode\s*\(/i', $lower);
}

/**
 * Post-proceso: permisos, re-codificación de imagen.
 *
 * @return array{ok: bool, message?: string, filename?: string}
 */
function hay_upload_finalizar_en_disco(string $dest, string $mime, bool $reencodeImage = true): array
{
    if (!is_file($dest) || filesize($dest) <= 0) {
        return ['ok' => false, 'message' => 'El archivo no se guardó correctamente'];
    }

    @chmod($dest, 0644);

    if (!$reencodeImage || !str_starts_with($mime, 'image/') || $mime === 'image/gif') {
        return ['ok' => true];
    }

    if (!function_exists('imagecreatefromstring')) {
        return ['ok' => true];
    }

    $raw = @file_get_contents($dest);
    if ($raw === false) {
        return ['ok' => false, 'message' => 'No se pudo leer la imagen'];
    }
    $img = @imagecreatefromstring($raw);
    if ($img === false) {
        return ['ok' => false, 'message' => 'La imagen no es válida'];
    }

    $dir = dirname($dest);
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => pathinfo($dest, PATHINFO_EXTENSION) ?: 'jpg',
    };
    $base = pathinfo($dest, PATHINFO_FILENAME);
    $newDest = $dir . '/' . $base . '_safe.' . $ext;

    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($img, $newDest, 90),
        'image/png' => imagepng($img, $newDest, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($img, $newDest, 90) : false,
        default => false,
    };
    imagedestroy($img);

    if (!$saved || !is_file($newDest)) {
        return ['ok' => false, 'message' => 'No se pudo procesar la imagen de forma segura'];
    }

    @unlink($dest);
    @rename($newDest, $dest);
    @chmod($dest, 0644);

    return ['ok' => true, 'filename' => basename($dest)];
}

/** Asegura .htaccess en árbol uploads (una vez por request). */
function hay_upload_asegurar_arbol(): void
{
    static $done = false;
    if ($done || !HAY_UPLOAD_HTACCESS) {
        return;
    }
    $done = true;

    $root = dirname(__DIR__) . '/uploads';
    if (!is_dir($root)) {
        @mkdir($root, 0755, true);
    }
    hay_upload_escribir_htaccess($root, 'mixed');

    $subdirs = [
        'avatars' => 'images',
        'alumnos/fotos' => 'images',
        'preregistros' => 'mixed',
        'preregistros/fotos' => 'images',
        'preregistros/csf' => 'mixed',
        'expediente' => 'mixed',
        'certificacion' => 'mixed',
        'documentos' => 'mixed',
        'soporte' => 'mixed',
        'aulas' => 'images',
    ];
    foreach ($subdirs as $rel => $tipo) {
        $abs = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_dir($abs)) {
            hay_upload_escribir_htaccess($abs, $tipo);
        }
    }
}

/**
 * Ruta relativa desde raíz del proyecto.
 */
function hay_upload_ruta_relativa(string $uploadBaseDir, string $subdir, string $filename): string
{
    $base = trim(str_replace('\\', '/', $uploadBaseDir), '/');
    $sub = trim(str_replace('\\', '/', $subdir), '/');

    return $sub !== '' ? $base . '/' . $sub . '/' . $filename : $base . '/' . $filename;
}
