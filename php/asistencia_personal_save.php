<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !asistencia_puede_registrar_personal_manual()) {
    http_response_code(403);
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idUsuario = (int) ($_POST['id_usuario'] ?? 0);
$fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
$horaLlegada = trim($_POST['hora_llegada'] ?? '');
$horaSalida = trim($_POST['hora_salida'] ?? '');
$nota = trim($_POST['nota'] ?? '');
$idPlantel = plantel_id_activo();
$idReg = (int) $_SESSION['user_id'];

if ($idUsuario <= 0 || $horaLlegada === '') {
    hay_json_response(['status' => 'error', 'message' => 'Usuario y hora de llegada son obligatorios']);
    exit;
}

$pdo->prepare(
    'INSERT INTO asistencia_personal (id_usuario, id_plantel, fecha, hora_llegada, hora_salida, origen, id_usuario_registro, nota)
     VALUES (?, ?, ?, ?, ?, \'recepcion\', ?, ?)
     ON DUPLICATE KEY UPDATE
       hora_llegada = VALUES(hora_llegada),
       hora_salida = VALUES(hora_salida),
       origen = \'recepcion\',
       id_usuario_registro = VALUES(id_usuario_registro),
       nota = VALUES(nota)'
)->execute([
    $idUsuario,
    $idPlantel,
    $fecha,
    $horaLlegada,
    $horaSalida !== '' ? $horaSalida : null,
    $idReg,
    $nota !== '' ? $nota : null,
]);

hay_json_response(['status' => 'ok', 'message' => 'Asistencia de personal guardada']);
