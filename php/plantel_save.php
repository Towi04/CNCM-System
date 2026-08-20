<?php
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$puedeGestionar = isset($_SESSION['user_id'])
    && (plantel_es_admin() || (function_exists('rbac_cap') && rbac_cap('admin_planteles')));
if (!$puedeGestionar) {
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
$cct = trim($_POST['cct'] ?? '');
$rvoe = trim($_POST['rvoe'] ?? '');
$prepaNombreSep = trim($_POST['prepa_nombre_sep'] ?? '');
$prepaCct = trim($_POST['prepa_cct'] ?? '');
$prepaRvoe = trim($_POST['prepa_rvoe'] ?? '');
$prepaLogoUrl = trim($_POST['prepa_logo_url'] ?? '');
$prepaDireccion = trim($_POST['prepa_direccion'] ?? '');

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
             razon_social = ?, direccion = ?, rfc = ?, telefono = ?, email_contacto = ?, logo_url = ?,
             cct = ?, rvoe = ?, prepa_nombre_sep = ?, prepa_cct = ?, prepa_rvoe = ?,
             prepa_logo_url = ?, prepa_direccion = ?
             WHERE id_plantel = ?'
        );
        $stmt->execute([
            $slug, $nombre, $orden, $activo,
            $razonSocial, $direccion ?: null, $rfc ?: null, $telefono ?: null,
            $emailContacto, $logoUrl ?: null,
            $cct ?: null, $rvoe ?: null, $prepaNombreSep ?: null, $prepaCct ?: null,
            $prepaRvoe ?: null, $prepaLogoUrl ?: null, $prepaDireccion ?: null, $id,
        ]);
    } else {
        $dup = $pdo->prepare('SELECT id_plantel FROM planteles WHERE slug = ? LIMIT 1');
        $dup->execute([$slug]);
        if ($dup->fetchColumn()) {
            echo json_encode(['status' => 'error', 'message' => 'Ese slug ya existe'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO planteles (
                slug, nombre, orden, activo, razon_social, direccion, rfc, telefono, email_contacto, logo_url,
                cct, rvoe, prepa_nombre_sep, prepa_cct, prepa_rvoe, prepa_logo_url, prepa_direccion
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $slug, $nombre, $orden, $activo,
            $razonSocial, $direccion ?: null, $rfc ?: null, $telefono ?: null,
            $emailContacto, $logoUrl ?: null,
            $cct ?: null, $rvoe ?: null, $prepaNombreSep ?: null, $prepaCct ?: null,
            $prepaRvoe ?: null, $prepaLogoUrl ?: null, $prepaDireccion ?: null,
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
