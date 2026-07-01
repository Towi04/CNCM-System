<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Sesión expirada');
}

$idEntrega = (int) ($_GET['id'] ?? 0);
$idUser = (int) $_SESSION['user_id'];
if ($idEntrega <= 0 || !expediente_documental_puede_ver_archivo($pdo, $idEntrega, $idUser)) {
    http_response_code(403);
    exit('Sin permiso');
}

$st = $pdo->prepare('SELECT ruta, nombre_original FROM expediente_entrega WHERE id_entrega = ? LIMIT 1');
$st->execute([$idEntrega]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row || empty($row['ruta'])) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

$path = dirname(__DIR__) . '/' . ltrim((string) $row['ruta'], '/');
if (!is_file($path)) {
    http_response_code(404);
    exit('Archivo no disponible');
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
$name = preg_replace('/[^a-zA-Z0-9._\-]/', '_', (string) ($row['nombre_original'] ?? 'documento'));

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
readfile($path);
exit;
