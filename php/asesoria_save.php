<?php
require_once __DIR__ . '/../config.php';
global $pdo;
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard.php?seccion=asesorias");
    exit;
}

$profesorId = (int)$_SESSION['user_id'];
$anio = (int)($_POST['anio'] ?? 0);
$semana = (int)($_POST['semana'] ?? 0);
$disp = $_POST['disp'] ?? [];

if ($anio <= 0) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'seccion' => 'asesorias'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header("Location: ../dashboard.php?seccion=asesorias&error=1");
    exit;
}

try {
    $pdo->beginTransaction();

    // Borramos lo existente de esa semana para reinsertar (simple)
    $idPlantel = plantel_id_activo();
    $stmt = $pdo->prepare(
        'DELETE FROM asesoria_disp WHERE id_plantel = ? AND id_profesor = ? AND anio = ? AND semana = ?'
    );
    $stmt->execute([$idPlantel, $profesorId, $anio, $semana]);
    $stmtIns = $pdo->prepare(
        'INSERT INTO asesoria_disp (id_plantel, id_profesor, anio, semana, dow, hora, disponible) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($disp as $dow => $hours) {
        $dow = (int)$dow;
        if (!is_array($hours)) continue;
        foreach ($hours as $h => $v) {
            $h = (int)$h;
            $v = ((string)$v === '1') ? 1 : 0;
            $stmtIns->execute([$idPlantel, $profesorId, $anio, $semana, $dow, $h, $v]);
        }
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'seccion' => 'asesorias'], JSON_UNESCAPED_UNICODE);
    exit;
}

header("Location: ../dashboard.php?seccion=asesorias&ok=1");
exit;

?>
