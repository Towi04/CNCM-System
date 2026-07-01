<?php
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

usuario_ensure_schema($pdo);
$id = (int) ($_POST['id_prospecto'] ?? 0);
$idPlantel = plantel_id_activo();
$nombres = trim($_POST['nombres'] ?? '');
$apPat = trim($_POST['apellido_paterno'] ?? '');
if ($nombres === '' || $apPat === '') {
    hay_json_response(['status' => 'error', 'message' => 'Nombre obligatorio']);
    exit;
}
$estado = $_POST['estado'] ?? 'entrevista';
$validos = ['entrevista', 'evaluacion', 'contratado', 'rechazado', 'contactar_despues'];
if (!in_array($estado, $validos, true)) {
    $estado = 'entrevista';
}

$params = [
    $nombres, $apPat, trim($_POST['apellido_materno'] ?? ''),
    trim($_POST['telefono'] ?? '') ?: null,
    trim($_POST['email_personal'] ?? '') ?: null,
    trim($_POST['especialidad'] ?? '') ?: null,
    trim($_POST['observaciones'] ?? '') ?: null,
    trim($_POST['motivo_no_contratacion'] ?? '') ?: null,
    $estado,
];

if ($id > 0) {
    $params[] = $id;
    $params[] = $idPlantel;
    $pdo->prepare(
        'UPDATE prospectos_profesor SET nombres=?, apellido_paterno=?, apellido_materno=?, telefono=?, email_personal=?,
         especialidad=?, observaciones=?, motivo_no_contratacion=?, estado=? WHERE id_prospecto=? AND id_plantel=?'
    )->execute($params);
} else {
    $pdo->prepare(
        'INSERT INTO prospectos_profesor (id_plantel, id_usuario_registro, nombres, apellido_paterno, apellido_materno,
         telefono, email_personal, especialidad, observaciones, motivo_no_contratacion, estado)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    )->execute(array_merge([$idPlantel, (int)$_SESSION['user_id']], $params));
}

hay_json_response(['status' => 'ok', 'message' => 'Prospecto guardado', 'seccion' => 'prospectos_profesor']);
