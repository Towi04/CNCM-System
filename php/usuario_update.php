<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_helpers.php';

auth_ensure_email_column($pdo);
global $pdo;
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($_POST['id_usuario'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$username = trim($_POST['username'] ?? '');
$emailRaw = trim($_POST['email'] ?? '');
$rol = trim($_POST['rol'] ?? '');
$idRol = (int) ($_POST['id_rol'] ?? 0);
$depto = '';
$idPlantelReg = (int) ($_POST['id_plantel'] ?? 0);
$pass = (string)($_POST['password'] ?? '');
$codigoHuella = isset($_POST['codigo_huella']) ? (string) $_POST['codigo_huella'] : null;

if ($id <= 0 || $nombre === '' || $apellido === '' || $username === '' || $emailRaw === '') {
    echo json_encode(['status' => 'error', 'message' => 'Campos requeridos incompletos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$idScope = plantel_scope_id($pdo);
$stHome = $pdo->prepare('SELECT id_plantel FROM usuarios WHERE id_usuario = ? LIMIT 1');
$stHome->execute([$id]);
if ((int)($stHome->fetchColumn() ?: 0) !== $idScope) {
    echo json_encode(['status' => 'error', 'message' => 'Usuario no pertenece a este plantel'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!plantel_es_admin()) {
    $idPlantelReg = $idScope;
}

$email = strtolower($emailRaw);
if (!auth_is_institutional_email($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Correo institucional inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Evitar username duplicado
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = ? AND id_usuario <> ? LIMIT 1");
    $stmt->execute([$username, $id]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['status' => 'error', 'message' => 'Ese username ya existe'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario <> ? LIMIT 1");
    $stmt->execute([$email, $id]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['status' => 'error', 'message' => 'Ese correo ya está en uso'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($idPlantelReg > 0 && !plantel_find($pdo, $idPlantelReg)) {
        echo json_encode(['status' => 'error', 'message' => 'Plantel inválido'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $plt = $idPlantelReg > 0 ? $idPlantelReg : null;

    plantel_ensure_column($pdo, 'usuarios', 'id_rol', 'INT UNSIGNED NULL', 'rol');
    if (!rbac_db_tablas_listas($pdo)) {
        rbac_db_ensure_schema($pdo);
    }
    if ($idRol > 0) {
        $rRow = rbac_rol_por_id($pdo, $idRol);
        $rol = $rRow ? (string) $rRow['clave'] : $rol;
    }
    $rolValido = rbac_validar_rol_usuario($pdo, $rol);
    if (!$rolValido) {
        echo json_encode(['status' => 'error', 'message' => 'Rol no válido'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($rolValido === 'supervisor' && rbac_rol_real() !== 'supervisor') {
        echo json_encode(['status' => 'error', 'message' => 'No puede asignar el rol de supervisora'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $rolRow = rbac_rol_por_clave($pdo, $rolValido);
    $idRolUpd = (int) ($rolRow['id_rol'] ?? 0);
    $depto = rbac_departamento_para_rol($pdo, $rolValido, $idRolUpd);

    if (trim($pass) !== '') {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, apellido=?, username=?, email=?, rol=?, id_rol=?, departamento=?, id_plantel=?, password=? WHERE id_usuario=?");
        $stmt->execute([$nombre, $apellido, $username, $email, $rolValido, $idRolUpd ?: null, $depto, $plt, $hash, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, apellido=?, username=?, email=?, rol=?, id_rol=?, departamento=?, id_plantel=? WHERE id_usuario=?");
        $stmt->execute([$nombre, $apellido, $username, $email, $rolValido, $idRolUpd ?: null, $depto, $plt, $id]);
    }

    if ($codigoHuella !== null && huella_puede_editar_usuario()) {
        $pinRes = huella_asignar_usuario($pdo, $id, $codigoHuella, $plt ?? plantel_id_activo());
        if (!$pinRes['ok']) {
            echo json_encode(['status' => 'error', 'message' => $pinRes['message']], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Usuario actualizado',
        'seccion' => 'ver_usuarios',
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

