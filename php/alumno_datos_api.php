<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !alumno_datos_puede_editar()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado'], 403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hay_json_response(['status' => 'error', 'message' => 'Método no permitido'], 405);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$idAlumno = (int) ($_POST['id_alumno'] ?? 0);
if ($idAlumno <= 0) {
    hay_json_response(['status' => 'error', 'message' => 'Alumno inválido'], 422);
    exit;
}

alumno_ensure_schema($pdo);

$actual = alumno_obtener($pdo, $idAlumno, $idPlantel);
if (!$actual) {
    hay_json_response(['status' => 'error', 'message' => 'Alumno no encontrado'], 404);
    exit;
}

$nombres = trim((string) ($_POST['nombres'] ?? ''));
$apellidoPaterno = trim((string) ($_POST['apellido_paterno'] ?? ''));
$apellidoMaterno = trim((string) ($_POST['apellido_materno'] ?? ''));
if ($nombres === '' || $apellidoPaterno === '') {
    hay_json_response(['status' => 'error', 'message' => 'Nombre y apellido paterno son obligatorios'], 422);
    exit;
}

$fechaNacimiento = trim((string) ($_POST['fecha_nacimiento'] ?? ''));
if ($fechaNacimiento !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNacimiento)) {
    hay_json_response(['status' => 'error', 'message' => 'Fecha de nacimiento inválida'], 422);
    exit;
}

$nuevoNombre = [
    'nombres' => $nombres,
    'apellido_paterno' => $apellidoPaterno,
    'apellido_materno' => $apellidoMaterno,
];
$valNombre = alumno_datos_validar_cambio_nombre($actual, $nuevoNombre);
if (empty($valNombre['ok'])) {
    hay_json_response([
        'status' => 'error',
        'message' => $valNombre['message'] ?? 'Cambio de nombre no autorizado',
        'cambio_drastico_nombre' => !empty($valNombre['drastico']),
    ], 403);
    exit;
}

$telefono = trim((string) ($_POST['telefono'] ?? ''));
$telefono2 = trim((string) ($_POST['telefono2'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    hay_json_response(['status' => 'error', 'message' => 'Correo inválido'], 422);
    exit;
}

$domicilio = trim((string) ($_POST['domicilio'] ?? ''));
$colonia = trim((string) ($_POST['colonia'] ?? ''));
$municipio = trim((string) ($_POST['municipio'] ?? ''));
$codigoPostal = trim((string) ($_POST['codigo_postal'] ?? ''));
$edad = null;
if ($fechaNacimiento !== '') {
    try {
        $nac = new DateTimeImmutable($fechaNacimiento);
        $edad = (int) $nac->diff(new DateTimeImmutable('today'))->y;
    } catch (Throwable $e) {
        $edad = null;
    }
}

$pdo->prepare(
    'UPDATE alumnos
     SET nombres = ?, apellido_paterno = ?, apellido_materno = ?,
         nombre = ?, apellido = ?,
         telefono = ?, telefono2 = ?, email = ?,
         fecha_nacimiento = ?, edad = ?,
         domicilio = ?, colonia = ?, municipio = ?, codigo_postal = ?
     WHERE id_alumno = ? AND id_plantel = ?'
)->execute([
    $nombres,
    $apellidoPaterno,
    $apellidoMaterno !== '' ? $apellidoMaterno : null,
    $nombres,
    $apellidoPaterno,
    $telefono !== '' ? $telefono : null,
    $telefono2 !== '' ? $telefono2 : null,
    $email !== '' ? $email : null,
    $fechaNacimiento !== '' ? $fechaNacimiento : null,
    $edad,
    $domicilio !== '' ? $domicilio : null,
    $colonia !== '' ? $colonia : null,
    $municipio !== '' ? $municipio : null,
    $codigoPostal !== '' ? $codigoPostal : null,
    $idAlumno,
    $idPlantel,
]);

if (!empty($actual['id_usuario'])) {
    $pdo->prepare(
        'UPDATE usuarios SET nombre = ?, apellido = ?, email = COALESCE(?, email) WHERE id_usuario = ?'
    )->execute([
        $nombres,
        trim($apellidoPaterno . ' ' . $apellidoMaterno),
        $email !== '' ? $email : null,
        (int) $actual['id_usuario'],
    ]);
}

hay_json_response([
    'status' => 'ok',
    'message' => !empty($valNombre['drastico'])
        ? 'Datos actualizados. Cambio drástico de nombre autorizado.'
        : 'Datos del alumno actualizados.',
    'seccion' => 'alumno_detalle',
    'params' => 'id=' . $idAlumno,
]);
