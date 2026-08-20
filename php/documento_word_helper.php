<?php

/**
 * Generación de constancias editables en Word sin dependencias de Composer.
 */

function documento_word_xml(string $valor): string
{
    return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function documento_word_parrafo(string $texto, bool $centrado = false, bool $negrita = false, int $tamano = 22): string
{
    $alineacion = $centrado ? '<w:jc w:val="center"/>' : '';
    $bold = $negrita ? '<w:b/>' : '';

    return '<w:p><w:pPr>' . $alineacion . '<w:spacing w:after="180"/></w:pPr>'
        . '<w:r><w:rPr>' . $bold . '<w:sz w:val="' . $tamano . '"/></w:rPr>'
        . '<w:t xml:space="preserve">' . documento_word_xml($texto) . '</w:t></w:r></w:p>';
}

/** @return array{ok:bool,message?:string,path?:string,url?:string,filename?:string} */
function documento_generar_docx(PDO $pdo, int $idDocumento): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'message' => 'La extensión ZipArchive no está disponible'];
    }

    documento_ensure_schema($pdo);
    $doc = documento_obtener($pdo, $idDocumento);
    if (!$doc || ($doc['tipo'] ?? '') !== 'constancia' || ($doc['estado'] ?? '') !== 'pagada') {
        return ['ok' => false, 'message' => 'Constancia no disponible para generar'];
    }

    $opciones = [
        'nombre_completo', 'numero_control', 'especialidad', 'plantel_nombre',
        'fecha_emision', 'vigencia_hasta', 'folio',
    ];
    $extra = json_decode((string) ($doc['campos_extra'] ?? '{}'), true) ?: [];
    $datos = documento_resolver_datos_alumno(
        $pdo,
        (int) $doc['id_alumno'],
        (int) $doc['id_plantel'],
        $opciones,
        $extra,
        $doc
    );
    $verifyUrl = documento_url_verificacion((string) $doc['token_verificacion']);
    $proposito = trim((string) ($extra['texto_proposito'] ?? 'A quien corresponda'));
    $fechaSolicitud = !empty($doc['solicitado_en'])
        ? date('d/m/Y', strtotime((string) $doc['solicitado_en']))
        : '';

    $body = documento_word_parrafo((string) ($datos['plantel_nombre'] ?? 'CNCM'), true, true, 28)
        . documento_word_parrafo('CONSTANCIA DE ESTUDIOS', true, true, 32)
        . documento_word_parrafo($proposito . ':')
        . documento_word_parrafo(
            'Por medio de la presente se hace constar que '
            . (string) ($datos['nombre_completo'] ?? '')
            . ', con número de control ' . (string) ($datos['numero_control'] ?? '')
            . ', se encuentra registrado(a) en la especialidad '
            . ((string) ($datos['especialidad'] ?? '') ?: 'correspondiente') . '.'
        )
        . documento_word_parrafo('Folio: ' . (string) ($datos['folio'] ?? ''), false, true)
        . documento_word_parrafo('Fecha de solicitud: ' . $fechaSolicitud)
        . documento_word_parrafo('Fecha de emisión: ' . (string) ($datos['fecha_emision'] ?? ''))
        . documento_word_parrafo('Vigencia: ' . ((string) ($datos['vigencia_hasta'] ?? '') ?: 'Sin fecha límite'))
        . documento_word_parrafo('Plantel: ' . (string) ($datos['plantel_nombre'] ?? ''))
        . documento_word_parrafo('Verificación en línea: ' . $verifyUrl)
        . documento_word_parrafo('Atentamente', true, false, 22)
        . documento_word_parrafo('Dirección del plantel', true, true, 22);

    $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body>' . $body
        . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'
        . '</w:body></w:document>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
        . '<w:name w:val="Normal"/><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="22"/></w:rPr>'
        . '</w:style></w:styles>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '</Relationships>';

    $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $now = gmdate('Y-m-d\TH:i:s\Z');
    $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
        . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>Constancia ' . documento_word_xml((string) $doc['folio']) . '</dc:title>'
        . '<dc:creator>CNCM HAY</dc:creator>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
        . '</cp:coreProperties>';

    $filename = 'constancia_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $doc['folio']) . '.docx';
    $dir = dirname(__DIR__) . '/' . DOCUMENTO_EMITIDO_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'message' => 'No se pudo crear la carpeta de documentos'];
    }
    $rel = DOCUMENTO_EMITIDO_DIR . '/' . $filename;
    $abs = dirname(__DIR__) . '/' . $rel;

    $zip = new ZipArchive();
    if ($zip->open($abs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['ok' => false, 'message' => 'No se pudo crear el archivo Word'];
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->addFromString('word/styles.xml', $stylesXml);
    $zip->addFromString('word/_rels/document.xml.rels', $docRels);
    $zip->addFromString('docProps/core.xml', $core);
    if (!$zip->close() || !is_file($abs)) {
        return ['ok' => false, 'message' => 'No se pudo finalizar el archivo Word'];
    }

    return [
        'ok' => true,
        'path' => $rel,
        'url' => hay_asset_url('php/documento_word.php?id_documento=' . $idDocumento),
        'filename' => $filename,
    ];
}
