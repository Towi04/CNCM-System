<?php

declare(strict_types=1);



require __DIR__ . '/config.php';



$idDoc = (int) ($_GET['id'] ?? 0);

$idAlumno = alumno_portal_id_sesion();



if ($idDoc <= 0) {

    http_response_code(400);

    echo 'Documento no válido';

    exit;

}



$doc = documento_obtener($pdo, $idDoc, $idAlumno > 0 ? $idAlumno : null);

if (!$doc) {

    http_response_code(404);

    echo 'No encontrado';

    exit;

}



$esStaff = documento_puede_marcar_pagada() || documento_puede_configurar_plantillas();

if (!$esStaff) {

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

}



$rel = (string) ($doc['pdf_path'] ?? '');

$abs = __DIR__ . '/' . ltrim($rel, '/');

if ($rel === '' || !is_file($abs)) {

    $gen = documento_generar_pdf($pdo, $idDoc);

    if (!$gen['ok']) {

        http_response_code(500);

        echo $gen['message'] ?? 'Error al generar PDF';

        exit;

    }

    header('Content-Type: application/pdf');

    header('Content-Disposition: inline; filename="' . ($gen['filename'] ?? 'documento.pdf') . '"');

    echo $gen['contenido'];

    exit;

}



header('Content-Type: application/pdf');

header('Content-Disposition: inline; filename="documento_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($doc['folio'] ?? '')) . '.pdf"');

header('Cache-Control: private, max-age=3600');

readfile($abs);

