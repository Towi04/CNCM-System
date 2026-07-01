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

$id = (int)($_POST['id_alumno'] ?? 0);
if ($id <= 0) {
    header("Location: ../dashboard.php?seccion=alumnos&error=1");
    exit;
}

try {
    $stmt = $pdo->prepare(
        'UPDATE alumnos SET activo = IF(activo = 1, 0, 1)
         WHERE id_alumno = ? AND id_plantel = ?'
    );
    $stmt->execute([$id, plantel_id_activo()]);
} catch (PDOException $e) {
    // ignore
}

header("Location: ../dashboard.php?seccion=alumnos");
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'seccion' => 'alumnos'], JSON_UNESCAPED_UNICODE);
    exit;
}
exit;

