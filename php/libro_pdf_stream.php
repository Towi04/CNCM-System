<?php
declare(strict_types=1);

/**
 * Stream protegido de PDF para lector alumno (sin URL directa al archivo).
 * Soporta Range para PDF.js.
 */
require_once __DIR__ . '/../config.php';

academico_libro_ensure_schema($pdo);

$token = (string) ($_GET['token'] ?? '');
$tok = academico_libro_stream_validar_token($token);
if (empty($tok['ok'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acceso denegado';
    exit;
}

$idVersion = (int) ($tok['id_version'] ?? 0);
$userId = (int) ($tok['user_id'] ?? 0);

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] !== $userId) {
    http_response_code(403);
    echo 'Sesión inválida';
    exit;
}

$idAlumno = function_exists('alumno_portal_id_sesion') ? alumno_portal_id_sesion() : 0;
if ($idAlumno <= 0) {
    $st = $pdo->prepare('SELECT id_alumno FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$userId]);
    $idAlumno = (int) $st->fetchColumn();
}

if ($idAlumno <= 0 || !academico_libro_alumno_puede_ver($pdo, $idAlumno, $idVersion)) {
    http_response_code(403);
    echo 'Sin permiso para este libro';
    exit;
}

$st = $pdo->prepare('SELECT ruta_pdf FROM academico_libro_version WHERE id_version = ? AND activo_alumno = 1 LIMIT 1');
$st->execute([$idVersion]);
$ruta = (string) ($st->fetchColumn() ?: '');
$path = academico_libro_path_abs($ruta);

if ($ruta === '' || !is_readable($path)) {
    http_response_code(404);
    echo 'Archivo no encontrado';
    exit;
}

$size = filesize($path);
if ($size === false) {
    http_response_code(500);
    echo 'Error de lectura';
    exit;
}

header('Content-Type: application/pdf');
header('Accept-Ranges: bytes');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="libro-cncm.pdf"');

$start = 0;
$end = $size - 1;
$httpCode = 200;

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') {
        $start = (int) $m[1];
    }
    if ($m[2] !== '') {
        $end = (int) $m[2];
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    $httpCode = 206;
    header("Content-Range: bytes $start-$end/$size");
}

$length = $end - $start + 1;
http_response_code($httpCode);
header('Content-Length: ' . $length);

$fp = fopen($path, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}
fseek($fp, $start);
$buf = 8192;
$sent = 0;
while (!feof($fp) && $sent < $length) {
    $read = min($buf, $length - $sent);
    $chunk = fread($fp, $read);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    $sent += strlen($chunk);
}
fclose($fp);
