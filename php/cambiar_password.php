<?php

require_once __DIR__ . '/../config.php';



header('Content-Type: application/json; charset=utf-8');



if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida. Vuelva a iniciar sesión.']);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);

    exit;

}



$actual = (string) ($_POST['password_actual'] ?? '');

$nueva = (string) ($_POST['password_nueva'] ?? '');

$confirm = (string) ($_POST['password_confirm'] ?? '');



if ($actual === '' || $nueva === '' || $confirm === '') {

    echo json_encode(['status' => 'error', 'message' => 'Complete todos los campos.']);

    exit;

}



if (strlen($nueva) < 6) {

    echo json_encode(['status' => 'error', 'message' => 'La nueva contraseña debe tener al menos 6 caracteres.']);

    exit;

}



if ($nueva !== $confirm) {

    echo json_encode(['status' => 'error', 'message' => 'La confirmación no coincide con la nueva contraseña.']);

    exit;

}



$id = (int) $_SESSION['user_id'];



$stmt = $pdo->prepare('SELECT password, debe_cambiar_password FROM usuarios WHERE id_usuario = ? LIMIT 1');

$stmt->execute([$id]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$row || !password_verify($actual, $row['password'])) {

    echo json_encode(['status' => 'error', 'message' => 'La contraseña actual no es correcta.']);

    exit;

}



if (password_verify($nueva, $row['password'])) {

    echo json_encode(['status' => 'error', 'message' => 'La nueva contraseña debe ser diferente a la actual.']);

    exit;

}



$hash = password_hash($nueva, PASSWORD_BCRYPT);

$pdo->prepare('UPDATE usuarios SET password = ?, debe_cambiar_password = 0 WHERE id_usuario = ?')

    ->execute([$hash, $id]);



$_SESSION['debe_cambiar_password'] = 0;



if (function_exists('rbac_sincronizar_sesion_usuario')) {

    rbac_sincronizar_sesion_usuario($pdo);

}



echo json_encode([

    'status' => 'ok',

    'message' => 'Contraseña actualizada correctamente. Ya puede usar el sistema.',

    'debe_completar_perfil' => function_exists('alumno_debe_completar_perfil')
        && alumno_debe_completar_perfil($pdo, $id),

    'debe_aceptar_acuerdo' => function_exists('alumno_debe_aceptar_acuerdo')
        && alumno_debe_aceptar_acuerdo($pdo, $id),

]);

