<?php
/**
 * Actualización masiva de personal (nombre, apellido, rol, plantel).
 */
define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_helpers.php';

auth_ensure_email_column($pdo);
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rolActor = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : (string) ($_SESSION['rol'] ?? '');
$puede = in_array($rolActor, ['supervisor', 'admin', 'director', 'gerente'], true)
    || (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total());
if (!$puede) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = trim((string) ($_POST['usuarios'] ?? ''));
$decoded = $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($decoded) || $decoded === []) {
    echo json_encode(['status' => 'error', 'message' => 'Sin filas para guardar'], JSON_UNESCAPED_UNICODE);
    exit;
}

$idScope = plantel_scope_id($pdo);
$esAdmin = function_exists('plantel_es_admin') && plantel_es_admin();
$plantelesOk = [];
foreach (plantel_list_accesibles($pdo, true) as $p) {
    $plantelesOk[(int) $p['id_plantel']] = true;
}
if ($plantelesOk === [] && $idScope > 0) {
    $plantelesOk[$idScope] = true;
}

plantel_ensure_column($pdo, 'usuarios', 'id_rol', 'INT UNSIGNED NULL', 'rol');
if (function_exists('rbac_db_ensure_schema') && !rbac_db_tablas_listas($pdo)) {
    rbac_db_ensure_schema($pdo);
}

$ok = 0;
$errores = [];

foreach ($decoded as $i => $row) {
    if (!is_array($row)) {
        continue;
    }
    $id = (int) ($row['id_usuario'] ?? 0);
    $nombre = trim((string) ($row['nombre'] ?? ''));
    $apellido = trim((string) ($row['apellido'] ?? ''));
    $rol = trim((string) ($row['rol'] ?? ''));
    $idPlantelReg = (int) ($row['id_plantel'] ?? 0);

    if ($id <= 0) {
        continue;
    }
    if ($nombre === '' || $apellido === '') {
        $errores[] = "Usuario #{$id}: nombre y apellido son obligatorios";
        continue;
    }

    $stHome = $pdo->prepare('SELECT id_usuario, id_plantel, rol FROM usuarios WHERE id_usuario = ? AND rol <> ? LIMIT 1');
    $stHome->execute([$id, 'alumno']);
    $actual = $stHome->fetch(PDO::FETCH_ASSOC);
    if (!$actual) {
        $errores[] = "Usuario #{$id}: no encontrado o es alumno";
        continue;
    }
    $homePlantel = (int) ($actual['id_plantel'] ?? 0);
    if (!$esAdmin && $homePlantel !== $idScope) {
        $errores[] = "Usuario #{$id}: no pertenece al plantel activo";
        continue;
    }
    if ($esAdmin && $homePlantel > 0 && empty($plantelesOk[$homePlantel]) && $homePlantel !== $idScope) {
        $errores[] = "Usuario #{$id}: plantel de origen no accesible";
        continue;
    }

    if (!$esAdmin) {
        $idPlantelReg = $idScope;
    }
    if ($idPlantelReg <= 0) {
        $idPlantelReg = $homePlantel > 0 ? $homePlantel : $idScope;
    }
    if ($idPlantelReg > 0 && empty($plantelesOk[$idPlantelReg]) && !plantel_find($pdo, $idPlantelReg)) {
        $errores[] = "Usuario #{$id}: plantel destino inválido";
        continue;
    }
    if ($esAdmin && $idPlantelReg > 0 && empty($plantelesOk[$idPlantelReg])) {
        // permitir si está en catálogo aunque no esté en “accesibles” (supervisor total)
        if (!(function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total())) {
            $errores[] = "Usuario #{$id}: sin acceso al plantel destino";
            continue;
        }
    }

    $rolValido = rbac_validar_rol_usuario($pdo, $rol !== '' ? $rol : (string) ($actual['rol'] ?? 'profesor'));
    if (!$rolValido || $rolValido === 'alumno') {
        $errores[] = "Usuario #{$id}: rol no válido";
        continue;
    }
    if ($rolValido === 'supervisor' && rbac_rol_real() !== 'supervisor') {
        $errores[] = "Usuario #{$id}: no puede asignar supervisora";
        continue;
    }
    $rolRow = rbac_rol_por_clave($pdo, $rolValido);
    $idRolUpd = (int) ($rolRow['id_rol'] ?? 0);
    $depto = rbac_departamento_para_rol($pdo, $rolValido, $idRolUpd);
    if (!in_array($depto, ['ingles', 'computacion', 'administrativo', 'ventas', 'otro'], true)) {
        $depto = 'administrativo';
    }

    try {
        $pdo->prepare(
            'UPDATE usuarios
             SET nombre = ?, apellido = ?, rol = ?, id_rol = ?, departamento = ?, id_plantel = ?
             WHERE id_usuario = ?'
        )->execute([
            $nombre,
            $apellido,
            $rolValido,
            $idRolUpd > 0 ? $idRolUpd : null,
            $depto,
            $idPlantelReg > 0 ? $idPlantelReg : null,
            $id,
        ]);
        $ok++;
    } catch (Throwable $e) {
        $errores[] = "Usuario #{$id}: " . $e->getMessage();
    }
}

echo json_encode([
    'status' => $ok > 0 && $errores === [] ? 'ok' : ($ok > 0 ? 'ok' : 'error'),
    'message' => "{$ok} usuario(s) actualizado(s)" . ($errores !== [] ? (' — ' . count($errores) . ' error(es)') : ''),
    'actualizados' => $ok,
    'errores' => array_slice($errores, 0, 20),
    'seccion' => 'ver_usuarios',
], JSON_UNESCAPED_UNICODE);
