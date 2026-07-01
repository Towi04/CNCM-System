<?php
require_once __DIR__ . '/../config.php';
global $pdo;
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

$gid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($gid <= 0 || !plantel_grupo_pertenece($pdo, $gid)) {
    echo json_encode(['activos_total' => 0, 'activos_30d' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM alumnos WHERE id_grupo = ? AND activo = 1");
    $stmt->execute([$gid]);
    $activosTotal = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT id_alumno)
        FROM asistencias
        WHERE id_grupo = ?
          AND presente = 1
          AND fecha >= (CURRENT_DATE - INTERVAL 30 DAY)
    ");
    $stmt->execute([$gid]);
    $activos30d = (int)$stmt->fetchColumn();

    echo json_encode(['activos_total' => $activosTotal, 'activos_30d' => $activos30d], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['activos_total' => 0, 'activos_30d' => 0], JSON_UNESCAPED_UNICODE);
}

