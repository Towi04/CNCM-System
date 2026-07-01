<?php
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$auth = pago_verificar_autorizador(
    $pdo,
    trim($_POST['usuario_autoriza'] ?? ''),
    (string) ($_POST['password_autoriza'] ?? '')
);
if (!$auth['ok']) {
    hay_json_response(['status' => 'error', 'message' => $auth['message']]);
    exit;
}

$idAlumno = (int) ($_POST['id_alumno'] ?? 0);
if ($idAlumno <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'Alumno inválido']);
    exit;
}

$pdo->prepare(
    'INSERT INTO alumno_becas (
        id_alumno, id_alumno_especialidad, aplicar_a, tipo, valor,
        fecha_inicio, fecha_fin, motivo, id_autoriza
    ) VALUES (?,?,?,?,?,?,?,?,?)'
)->execute([
    $idAlumno,
    (int) ($_POST['id_alumno_especialidad'] ?? 0) ?: null,
    $_POST['aplicar_a'] ?? 'colegiatura',
    $_POST['tipo'] ?? 'porcentaje',
    catalog_money($_POST['valor'] ?? 0),
    $_POST['fecha_inicio'] ?? date('Y-m-d'),
    trim($_POST['fecha_fin'] ?? '') ?: null,
    trim($_POST['motivo'] ?? 'Beca autorizada'),
    $auth['id_usuario'],
]);

hay_json_response(['status' => 'ok', 'message' => 'Beca registrada y autorizada']);
