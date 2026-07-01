<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$puedeGestionar = rol_aula_puede_gestionar();
$puedeVer = rol_aula_puede_ver();
if (!$puedeGestionar && !$puedeVer) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$plantel = plantel_find($pdo, $idPlantel);
$tituloPlantel = trim((string) ($plantel['nombre'] ?? $plantel['clave'] ?? ''));
$anio = (int) ($_GET['anio'] ?? date('Y'));
$mes = max(1, min(12, (int) ($_GET['mes'] ?? date('n'))));
$soloPublicado = !$puedeGestionar;

try {
    $res = rol_aula_generar_pdf($pdo, $idPlantel, $anio, $mes, $tituloPlantel, $soloPublicado);
    if (!$res['ok']) {
        http_response_code(404);
        echo htmlspecialchars($res['message'] ?? 'No se pudo generar el documento', ENT_QUOTES, 'UTF-8');
        exit;
    }

    header('Content-Type: ' . $res['mime']);
    $disp = !empty($res['es_pdf']) ? 'inline' : 'inline';
    header('Content-Disposition: ' . $disp . '; filename="' . $res['filename'] . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $res['contenido'];
} catch (Throwable $e) {
    error_log('rol_aula_pdf: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
