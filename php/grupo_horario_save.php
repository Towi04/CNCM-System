<?php
require __DIR__ . '/../config.php';

if (!plantel_es_admin() && ($_SESSION['rol'] ?? '') !== 'gerente') {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idGrupo = (int) ($_POST['id_grupo'] ?? 0);
$dias = $_POST['dia_semana'] ?? [];
$horaInicio = trim($_POST['hora_inicio'] ?? '08:00');
$horaFin = trim($_POST['hora_fin'] ?? '12:00');

if ($idGrupo <= 0 || !plantel_grupo_pertenece($pdo, $idGrupo)) {
    hay_json_response(['status' => 'error', 'message' => 'Grupo inválido']);
    exit;
}

if (!is_array($dias)) {
    $dias = [$dias];
}

try {
    $pdo->prepare('DELETE FROM grupo_horarios WHERE id_grupo = ?')->execute([$idGrupo]);
    $ins = $pdo->prepare(
        'INSERT INTO grupo_horarios (id_grupo, dia_semana, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)'
    );
    foreach ($dias as $d) {
        $d = (int) $d;
        if ($d >= 0 && $d <= 6) {
            $ins->execute([$idGrupo, $d, $horaInicio, $horaFin]);
        }
    }
    hay_json_response(['status' => 'ok', 'message' => 'Horarios guardados', 'seccion' => 'grupos']);
} catch (PDOException $e) {
    hay_json_response(['status' => 'error', 'message' => $e->getMessage()]);
}
