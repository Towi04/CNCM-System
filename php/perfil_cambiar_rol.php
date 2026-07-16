<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

$accion = trim((string) ($_POST['accion'] ?? 'cambiar'));
$rol = strtolower(trim((string) ($_POST['rol'] ?? '')));

if ($accion === 'restaurar' || $rol === '' || $rol === 'real') {
    rbac_restaurar_rol_real();
    echo json_encode([
        'status' => 'ok',
        'message' => 'Vista restaurada a su rol real.',
        'rol' => rbac_rol_efectivo(),
        'rol_real' => rbac_rol_real(),
        'rol_label' => rbac_etiqueta_rol(),
        'simulando' => false,
    ]);
    exit;
}

if (!rbac_puede_simular_rol()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Su cuenta no puede cambiar la vista de rol.']);
    exit;
}

if (!rbac_establecer_rol_simulado($rol)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Rol no válido.']);
    exit;
}

echo json_encode([
    'status' => 'ok',
    'message' => 'Ahora ve el sistema como: ' . rbac_etiqueta_rol($rol),
    'rol' => rbac_rol_efectivo(),
    'rol_real' => rbac_rol_real(),
    'rol_label' => rbac_etiqueta_rol(),
    'simulando' => rbac_esta_simulando_rol(),
]);
