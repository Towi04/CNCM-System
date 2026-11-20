<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id']) || !credencial_puede_generar()) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$idAlumno = (int) ($_GET['id_alumno'] ?? 0);
$idCredencial = (int) ($_GET['id_credencial'] ?? 0);
$idGrupo = (int) ($_GET['id_grupo'] ?? 0);
$idPlantilla = (int) ($_GET['id_plantilla'] ?? 0);
$idPlantel = plantel_scope_id($pdo);

// Impresión masiva por grupo: un PDF con frente/reverso de cada alumno activo.
if ($idGrupo > 0) {
    @set_time_limit(180);
    $lote = credencial_generar_grupo($pdo, $idGrupo, $idPlantilla, (int) $_SESSION['user_id']);
    if (!$lote['ok']) {
        http_response_code(422);
        echo htmlspecialchars((string) ($lote['message'] ?? 'No se pudieron generar las credenciales'));
        if (!empty($lote['errores']) && is_array($lote['errores'])) {
            echo "\n\n" . htmlspecialchars(implode("\n", array_slice($lote['errores'], 0, 10)));
        }
        exit;
    }
    $pdf = credencial_generar_pdf_lote(
        $pdo,
        $lote['id_credenciales'] ?? [],
        (string) ($lote['clave'] ?? 'grupo')
    );
    if (!$pdf['ok']) {
        http_response_code(500);
        echo htmlspecialchars((string) ($pdf['message'] ?? 'No se pudo generar el PDF del grupo'));
        exit;
    }
    if ((int) ($lote['omitidos'] ?? 0) > 0) {
        header('X-Credenciales-Omitidas: ' . (int) $lote['omitidos']);
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . ($pdf['filename'] ?? 'credenciales_grupo.pdf') . '"');
    header('Cache-Control: private, no-store');
    echo $pdf['contenido'];
    exit;
}

if ($idCredencial <= 0) {
    if ($idAlumno <= 0) {
        http_response_code(400);
        echo 'Alumno o grupo no válido';
        exit;
    }
    $res = credencial_generar($pdo, $idAlumno, $idPlantilla, (int) $_SESSION['user_id']);
    if (!$res['ok']) {
        http_response_code(422);
        echo htmlspecialchars((string) ($res['message'] ?? 'No se pudo generar la credencial'));
        exit;
    }
    $idCredencial = (int) $res['id_credencial'];
}

$credencial = credencial_obtener($pdo, $idCredencial);
if (!$credencial || (int) $credencial['id_plantel'] !== $idPlantel) {
    http_response_code(404);
    echo 'Credencial no encontrada';
    exit;
}

$pdf = credencial_generar_pdf($pdo, $idCredencial);
if (!$pdf['ok']) {
    http_response_code(500);
    echo htmlspecialchars((string) ($pdf['message'] ?? 'No se pudo generar el PDF'));
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . ($pdf['filename'] ?? 'credencial.pdf') . '"');
header('Cache-Control: private, no-store');
echo $pdf['contenido'];
