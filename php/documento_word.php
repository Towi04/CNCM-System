<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

$idDocumento = (int) ($_GET['id_documento'] ?? 0);
if ($idDocumento <= 0) {
    http_response_code(400);
    echo 'Documento no válido';
    exit;
}

$doc = documento_obtener($pdo, $idDocumento);
if (!$doc || ($doc['tipo'] ?? '') !== 'constancia') {
    http_response_code(404);
    echo 'Constancia no encontrada';
    exit;
}

$esAlumno = function_exists('alumno_portal_es_alumno') && alumno_portal_es_alumno();
if ($esAlumno) {
    if ((int) ($doc['id_alumno'] ?? 0) !== alumno_portal_id_sesion()) {
        http_response_code(403);
        echo 'No autorizado';
        exit;
    }
    if (($doc['estado'] ?? '') !== 'pagada') {
        http_response_code(403);
        echo 'Disponible después del pago en recepción';
        exit;
    }
    if (!empty($doc['vigente_hasta']) && $doc['vigente_hasta'] < date('Y-m-d')) {
        http_response_code(410);
        echo 'Documento expirado';
        exit;
    }
} else {
    $puede = documento_puede_mostrador()
        || documento_puede_configurar_plantillas()
        || documento_puede_entregar();
    $accesoTotal = function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total();
    if (
        !$puede
        || (!$accesoTotal && (int) ($doc['id_plantel'] ?? 0) !== plantel_scope_id($pdo))
    ) {
        http_response_code(403);
        echo 'No autorizado';
        exit;
    }
}

$generado = documento_generar_docx($pdo, $idDocumento);
if (empty($generado['ok']) || empty($generado['path'])) {
    http_response_code(500);
    echo $generado['message'] ?? 'No se pudo generar el archivo Word';
    exit;
}

$abs = dirname(__DIR__) . '/' . ltrim((string) $generado['path'], '/');
if (!is_file($abs)) {
    http_response_code(500);
    echo 'No se encontró el archivo generado';
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . ($generado['filename'] ?? 'constancia.docx') . '"');
header('Content-Length: ' . filesize($abs));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($abs);
