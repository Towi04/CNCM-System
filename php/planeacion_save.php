<?php
require_once __DIR__ . '/../config.php';
planeacion_ensure_schema($pdo);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php?seccion=planeaciones');
    exit;
}

$gid = (int) ($_POST['id_grupo'] ?? 0);
$idFase = (int) ($_POST['id_fase'] ?? 0);
$fecha = trim($_POST['fecha'] ?? '');
$titulo = trim($_POST['titulo'] ?? '');
$contenido = trim($_POST['contenido'] ?? '');
$profesorId = (int) ($_SESSION['user_id'] ?? 0);

$json = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch';

function planeacion_save_respond(bool $ok, string $message, bool $json): void
{
    if ($json) {
        hay_json_response(['status' => $ok ? 'ok' : 'error', 'message' => $message]);
        exit;
    }
    header('Location: ../dashboard.php?seccion=planeaciones&' . ($ok ? 'ok=1' : 'error=1'));
    exit;
}

if ($gid <= 0 || $idFase <= 0 || $fecha === '' || $titulo === '' || $contenido === '') {
    planeacion_save_respond(false, 'Complete grupo, fase, fecha, tema y planeación.', $json);
}

if (!planeacion_puede_grupo($pdo, $gid)) {
    planeacion_save_respond(false, 'No puede registrar planeación para este grupo.', $json);
}

$fases = planeacion_fases_grupo($pdo, $gid);
$idsFase = array_map(static fn ($f) => (int) $f['id_fase'], $fases);
if (!in_array($idFase, $idsFase, true)) {
    planeacion_save_respond(false, 'La fase seleccionada no corresponde a la especialidad del grupo.', $json);
}

try {
    $stmt = $pdo->prepare('SELECT YEAR(?), WEEK(?, 0)');
    $stmt->execute([$fecha, $fecha]);
    $calc = $stmt->fetch(PDO::FETCH_NUM);
    $anio = isset($calc[0]) ? (int) $calc[0] : (int) date('Y');
    $semana = isset($calc[1]) ? (int) $calc[1] : 0;

    $stmt = $pdo->prepare(
        'INSERT INTO planeaciones (id_grupo, id_profesor, id_fase, fecha, anio, semana, titulo, contenido, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'enviada\')'
    );
    $stmt->execute([$gid, $profesorId ?: null, $idFase, $fecha, $anio, $semana, $titulo, $contenido]);
    $idPlantel = plantel_scope_id($pdo);
    $notaInicial = trim((string) ($_POST['nota_profesor'] ?? ''));
    if ($notaInicial !== '') {
        planeacion_observacion_agregar($pdo, (int) $pdo->lastInsertId(), $profesorId, 'profesor', $notaInicial);
    }
    planeacion_notificar_coordinacion_nueva($pdo, $idPlantel, $gid, $titulo, $fecha, $profesorId);

    planeacion_save_respond(true, 'Planeación guardada.', $json);
} catch (PDOException $e) {
    error_log('planeacion_save: ' . $e->getMessage());
    planeacion_save_respond(false, 'Error al guardar en base de datos.', $json);
}
