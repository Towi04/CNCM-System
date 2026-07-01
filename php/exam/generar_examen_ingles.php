<?php
require_once __DIR__ . '/bootstrap.php';
exam_require_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exam_json_response(['status' => 'error', 'message' => 'Método no permitido.'], 405);
}

$tipo = trim($_POST['tipo'] ?? '');
$fasesRaw = $_POST['fases'] ?? [];
if (!is_array($fasesRaw)) {
    $fasesRaw = array_filter(array_map('trim', explode(',', (string)$fasesRaw)));
}
$fases = array_values(array_unique(array_filter(array_map('trim', $fasesRaw), fn($f) => $f !== '')));

$nombre = trim($_POST['nombre_examen'] ?? '');
$idFusion = !empty($_POST['id_fusion']) ? (int)$_POST['id_fusion'] : null;
$guardarFusion = !empty($_POST['guardar_fusion']);
$nombreFusion = trim($_POST['nombre_fusion'] ?? '');

try {
    $svc = exam_ingles_service();

    if (!empty($_POST['solo_importar_csv']) && !empty($_FILES['csv_fusion']['tmp_name']) && is_uploaded_file($_FILES['csv_fusion']['tmp_name'])) {
        $tipoCsv = trim($_POST['tipo_csv'] ?? 'vocabulario');
        if ($nombreFusion === '') {
            throw new InvalidArgumentException('Indique un nombre para la fusión al importar CSV.');
        }
        $idFusion = (int)($_POST['id_fusion'] ?? 0);
        if ($idFusion < 1) {
            $idFusion = $svc->crearFusion($nombreFusion, $fases, (int)$_SESSION['user_id']);
        }
        $importados = $svc->importarCsvFusion($tipoCsv, $idFusion, $_FILES['csv_fusion']['tmp_name']);
        exam_json_response([
            'status' => 'ok',
            'message' => "Se importaron {$importados} registros a la fusión «{$nombreFusion}».",
            'id_fusion' => $idFusion,
            'seccion' => 'examen_generar',
        ]);
    }

    $result = $svc->generar([
        'tipo' => $tipo,
        'fases' => $fases,
        'nombre' => $nombre,
        'id_fusion' => $idFusion,
        'guardar_fusion' => $guardarFusion,
        'nombre_fusion' => $nombreFusion,
        'id_profesor' => (int)$_SESSION['user_id'],
    ]);

    exam_json_response([
        'status' => 'ok',
        'message' => 'Examen generado correctamente.',
        'id_examen' => $result['id_examen'],
        'nombre' => $result['nombre'],
        'pdf_url' => $result['pdf_url'],
        'csv_url' => $result['csv_url'],
        'hoja_url' => $result['hoja_url'] ?? null,
        'seccion' => 'examen_generar',
    ]);
} catch (Throwable $e) {
    exam_json_response([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], 400);
}
