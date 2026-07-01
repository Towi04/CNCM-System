<?php
define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !rbac_puede_registrar_usuarios()) {
    echo json_encode(['ok' => false, 'message' => 'Sin permiso'], JSON_UNESCAPED_UNICODE);
    exit;
}

$nombre = trim((string) ($_GET['nombre'] ?? $_POST['nombre'] ?? ''));
$apellido = trim((string) ($_GET['apellido'] ?? $_POST['apellido'] ?? ''));

if ($nombre === '' || $apellido === '') {
    echo json_encode(['ok' => false, 'message' => 'Nombre y apellido requeridos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$apPaterno = cuenta_apellido_paterno_desde_campo($apellido);
$email = cuenta_email_personal($pdo, $nombre, $apPaterno);
if ($email === '') {
    echo json_encode(['ok' => false, 'message' => 'No se pudo generar correo'], JSON_UNESCAPED_UNICODE);
    exit;
}

$local = explode('@', $email, 2)[0];
echo json_encode([
    'ok' => true,
    'email' => $email,
    'username' => $local,
], JSON_UNESCAPED_UNICODE);
