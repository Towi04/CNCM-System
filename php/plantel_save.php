<?php
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !plantel_es_admin()) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int) ($_POST['id_plantel'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$slug = strtolower(trim($_POST['slug'] ?? ''));
$orden = (int) ($_POST['orden'] ?? 0);
$activo = isset($_POST['activo']) ? 1 : 0;
$razonSocial = trim($_POST['razon_social'] ?? '') ?: 'GRUPO EDUCATIVO CNCM';
$direccion = trim($_POST['direccion'] ?? '');
$rfc = trim($_POST['rfc'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$emailContacto = trim($_POST['email_contacto'] ?? '') ?: 'corporativo@cncm.com.mx';
$logoUrl = trim($_POST['logo_url'] ?? '');

if ($nombre === '') {
    echo json_encode(['status' => 'error', 'message' => 'El nombre es obligatorio'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($slug === '') {
    $slug = preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($nombre)));
    $slug = trim($slug, '-');
}
if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,38}$/', $slug)) {
    echo json_encode(['status' => 'error', 'message' => 'Slug inválido (solo letras, números y guiones)'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($id > 0) {
        $dup = $pdo->prepare('SELECT id_plantel FROM planteles WHERE slug = ? AND id_plantel <> ? LIMIT 1');
        $dup->execute([$slug, $id]);
        if ($dup->fetchColumn()) {
            echo json_encode(['status' => 'error', 'message' => 'Ese slug ya existe'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt = $pdo->prepare(
            'UPDATE planteles SET slug = ?, nombre = ?, orden = ?, activo = ?,
             razon_social = ?, direccion = ?, rfc = ?, telefono = ?, email_contacto = ?, logo_url = ?
             WHERE id_plantel = ?'
        );
        $stmt->execute([
            $slug, $nombre, $orden, $activo,
            $razonSocial, $direccion ?: null, $rfc ?: null, $telefono ?: null,
            $emailContacto, $logoUrl ?: null, $id,
        ]);
    } else {
        $dup = $pdo->prepare('SELECT id_plantel FROM planteles WHERE slug = ? LIMIT 1');
        $dup->execute([$slug]);
        if ($dup->fetchColumn()) {
            echo json_encode(['status' => 'error', 'message' => 'Ese slug ya existe'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO planteles (slug, nombre, orden, activo, razon_social, direccion, rfc, telefono, email_contacto, logo_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $slug, $nombre, $orden, $activo,
            $razonSocial, $direccion ?: null, $rfc ?: null, $telefono ?: null,
            $emailContacto, $logoUrl ?: null,
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Plantel guardado',
        'seccion' => 'admin_planteles',
        'id_plantel' => $id,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
