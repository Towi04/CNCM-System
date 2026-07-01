<?php
require_once __DIR__ . '/../config.php';
global $pdo;
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard.php?seccion=alumnos");
    exit;
}

$gid = (int)($_POST['id_grupo'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$matricula = trim($_POST['matricula'] ?? '');
$matricula = $matricula === '' ? null : $matricula;

if ($gid <= 0 || $nombre === '' || $apellido === '') {
    header("Location: ../dashboard.php?seccion=alumnos&error=1");
    exit;
}

if (!plantel_grupo_pertenece($pdo, $gid)) {
    header("Location: ../dashboard.php?seccion=alumnos&error=plantel");
    exit;
}

$idPlantel = plantel_id_activo();
if ($matricula !== null && plantel_matricula_existe($pdo, $matricula, $idPlantel)) {
    header("Location: ../dashboard.php?seccion=alumnos&error=matricula");
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO alumnos (id_grupo, id_plantel, nombre, apellido, matricula, activo) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([$gid, $idPlantel, $nombre, $apellido, $matricula]);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok', 'seccion' => 'alumnos'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header("Location: ../dashboard.php?seccion=alumnos&ok=1");
    exit;
} catch (PDOException $e) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'seccion' => 'alumnos'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header("Location: ../dashboard.php?seccion=alumnos&error=bd");
    exit;
}

?>
