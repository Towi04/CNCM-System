<?php

/**
 * Constancias y diplomas con plantillas personalizables, QR y vigencia.
 */

define('DOCUMENTO_UPLOAD_DIR', 'uploads/documentos');
define('DOCUMENTO_PLANTILLA_DIR', 'uploads/documentos/plantillas');
define('DOCUMENTO_EMITIDO_DIR', 'uploads/documentos/emitidos');
define('DOCUMENTO_FIRMA_DIR', 'uploads/documentos/firmas');

function documento_ensure_schema(PDO $pdo): void
{
    catalog_ensure_schema($pdo);
    alumno_ensure_schema($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS documento_plantilla (
            id_plantilla INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tipo ENUM(\'constancia\',\'diploma\') NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            id_plantel INT UNSIGNED NULL,
            fondo_path VARCHAR(255) NULL,
            ancho_mm DECIMAL(6,2) NOT NULL DEFAULT 215.9,
            alto_mm DECIMAL(6,2) NOT NULL DEFAULT 279.4,
            campos_json JSON NULL,
            firma_path VARCHAR(255) NULL,
            vigencia_dias SMALLINT UNSIGNED NOT NULL DEFAULT 90,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_plantilla),
            KEY idx_dp_tipo (tipo),
            KEY idx_dp_plantel (id_plantel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_documento (
            id_documento INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tipo ENUM(\'constancia\',\'diploma\') NOT NULL,
            id_alumno INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            id_grupo INT UNSIGNED NULL,
            id_plantilla INT UNSIGNED NOT NULL,
            id_producto INT UNSIGNED NULL,
            id_pago INT UNSIGNED NULL,
            folio VARCHAR(32) NOT NULL,
            token_verificacion CHAR(32) NOT NULL,
            campos_opciones JSON NULL,
            campos_extra JSON NULL,
            estado ENUM(\'pendiente_pago\',\'pagada\',\'expirada\',\'cancelada\') NOT NULL DEFAULT \'pendiente_pago\',
            vigente_hasta DATE NULL,
            pdf_path VARCHAR(255) NULL,
            solicitado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            pagado_en DATETIME NULL,
            pagado_por INT UNSIGNED NULL,
            generado_en DATETIME NULL,
            entregado_en DATETIME NULL,
            entregado_por INT UNSIGNED NULL,
            PRIMARY KEY (id_documento),
            UNIQUE KEY uq_ad_folio (folio),
            UNIQUE KEY uq_ad_token (token_verificacion),
            KEY idx_ad_alumno (id_alumno),
            KEY idx_ad_estado (estado),
            KEY idx_ad_grupo (id_grupo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'productos', 'es_constancia', 'TINYINT(1) NOT NULL DEFAULT 0', 'activo');
        plantel_ensure_column($pdo, 'alumno_documento', 'entregado_en', 'DATETIME NULL', 'generado_en');
        plantel_ensure_column($pdo, 'alumno_documento', 'entregado_por', 'INT UNSIGNED NULL', 'entregado_en');
    }

    $st = $pdo->query("SELECT id_producto FROM productos WHERE clave = 'CONST-EST' LIMIT 1");
    if (!$st->fetchColumn()) {
        $pdo->exec(
            "INSERT INTO productos (clave, nombre, descripcion, precio, clave_sat, unidad_sat, activo, visible, es_constancia, orden)
             VALUES ('CONST-EST', 'Constancia de estudios', 'Constancia oficial generada por el sistema HAY', 150.00, '01010101', 'E48', 1, 0, 1, 900)"
        );
    } else {
        $pdo->exec("UPDATE productos SET es_constancia = 1 WHERE clave = 'CONST-EST'");
    }

    foreach ([DOCUMENTO_UPLOAD_DIR, DOCUMENTO_PLANTILLA_DIR, DOCUMENTO_EMITIDO_DIR, DOCUMENTO_FIRMA_DIR] as $d) {
        $abs = dirname(__DIR__) . '/' . $d;
        if (!is_dir($abs)) {
            @mkdir($abs, 0755, true);
        }
    }

    $cnt = (int) $pdo->query('SELECT COUNT(*) FROM documento_plantilla')->fetchColumn();
    if ($cnt === 0) {
        $pdo->prepare(
            'INSERT INTO documento_plantilla (tipo, nombre, campos_json, vigencia_dias) VALUES (?,?,?,?)'
        )->execute([
            'constancia',
            'Constancia estándar',
            json_encode(documento_plantilla_campos_default('constancia'), JSON_UNESCAPED_UNICODE),
            90,
        ]);
        $pdo->prepare(
            'INSERT INTO documento_plantilla (tipo, nombre, campos_json, vigencia_dias) VALUES (?,?,?,?)'
        )->execute([
            'diploma',
            'Diploma estándar',
            json_encode(documento_plantilla_campos_default('diploma'), JSON_UNESCAPED_UNICODE),
            3650,
        ]);
    }
}

/** @return array<string, array{label: string, manual?: bool, grupo?: string}> */
function documento_campos_disponibles(string $tipo = 'constancia'): array
{
    $base = [
        'nombre_completo' => ['label' => 'Nombre completo del alumno', 'grupo' => 'Alumno'],
        'numero_control' => ['label' => 'Número de control', 'grupo' => 'Alumno'],
        'curp' => ['label' => 'CURP', 'manual' => true, 'grupo' => 'Alumno'],
        'especialidad' => ['label' => 'Especialidad / carrera', 'grupo' => 'Académico'],
        'grupo_clave' => ['label' => 'Grupo actual', 'grupo' => 'Académico'],
        'horario' => ['label' => 'Horario de clases', 'grupo' => 'Académico'],
        'calificaciones' => ['label' => 'Calificaciones por fase', 'grupo' => 'Académico'],
        'tiempo_estudio' => ['label' => 'Tiempo de estudio', 'grupo' => 'Académico'],
        'plantel_nombre' => ['label' => 'Nombre del plantel', 'grupo' => 'Institución'],
        'fecha_emision' => ['label' => 'Fecha de emisión', 'grupo' => 'Documento'],
        'folio' => ['label' => 'Folio del documento', 'grupo' => 'Documento'],
        'vigencia_hasta' => ['label' => 'Válida hasta', 'grupo' => 'Documento'],
        'qr_verificacion' => ['label' => 'Código QR de verificación', 'grupo' => 'Documento'],
        'texto_proposito' => ['label' => 'Propósito de la constancia', 'manual' => true, 'grupo' => 'Documento'],
    ];
    if ($tipo === 'diploma') {
        $base['curso_nombre'] = ['label' => 'Nombre del curso', 'grupo' => 'Académico'];
        $base['fecha_fin_curso'] = ['label' => 'Fecha fin de curso', 'grupo' => 'Académico'];
    }

    return $base;
}

function documento_puede_configurar_plantillas(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('menu_admin')) {
        return true;
    }
    return in_array(rbac_rol_efectivo(), ['director', 'supervisor', 'coordinador'], true);
}

function documento_puede_marcar_pagada(): bool
{
    if (function_exists('reporte_financiero_puede_ver') && reporte_financiero_puede_ver()) {
        return true;
    }
    return in_array(rbac_rol_efectivo(), ['director', 'supervisor', 'admin', 'gerente'], true);
}

function documento_puede_mostrador(): bool
{
    return documento_puede_marcar_pagada();
}

function documento_puede_entregar(): bool
{
    return documento_puede_mostrador();
}

/** @return array<string, string> */
function documento_estado_labels(): array
{
    return [
        'pendiente_pago' => 'Pendiente de pago',
        'pagada' => 'Pagada / emitida',
        'expirada' => 'Expirada',
        'cancelada' => 'Cancelada',
    ];
}

/** @param array<string, mixed> $doc */
function documento_mostrador_enriquecer(array $doc): array
{
    $estado = (string) ($doc['estado'] ?? '');
    $doc['estado_label'] = documento_estado_labels()[$estado] ?? $estado;
    $doc['tipo_label'] = ($doc['tipo'] ?? '') === 'diploma' ? 'Diploma' : 'Constancia';
    $doc['pdf_url'] = ($estado === 'pagada')
        ? hay_asset_url('documento_pdf.php?id=' . (int) ($doc['id_documento'] ?? 0))
        : null;
    $doc['verify_url'] = !empty($doc['token_verificacion'])
        ? documento_url_verificacion((string) $doc['token_verificacion'])
        : null;
    $doc['puede_imprimir'] = $estado === 'pagada';
    $doc['vigente'] = $estado === 'pagada'
        && (empty($doc['vigente_hasta']) || $doc['vigente_hasta'] >= date('Y-m-d'));
    $doc['entregado'] = !empty($doc['entregado_en']);
    $doc['puede_entregar'] = $estado === 'pagada' && empty($doc['entregado_en']);

    return $doc;
}

/** @return list<array<string, mixed>> */
function documento_mostrador_listar_alumno(PDO $pdo, int $idAlumno, int $idPlantel, int $limite = 40): array
{
    documento_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT d.* FROM alumno_documento d
         WHERE d.id_alumno = ? AND d.id_plantel = ?
         ORDER BY d.solicitado_en DESC
         LIMIT ' . max(1, min(80, $limite))
    );
    $st->execute([$idAlumno, $idPlantel]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map('documento_mostrador_enriquecer', $rows);
}

/** @return array<string, mixed>|null */
function documento_mostrador_por_folio(PDO $pdo, string $ref, int $idPlantel): ?array
{
    documento_ensure_schema($pdo);
    $ref = trim($ref);
    if ($ref === '') {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT d.*, CONCAT(a.nombres, \' \', a.apellido_paterno, \' \', COALESCE(a.apellido_materno,\'\')) AS alumno_nombre,
                a.numero_control
         FROM alumno_documento d
         INNER JOIN alumnos a ON a.id_alumno = d.id_alumno
         WHERE d.id_plantel = ? AND (d.folio = ? OR d.token_verificacion = ?)
         LIMIT 1'
    );
    $st->execute([$idPlantel, $ref, $ref]);
    $doc = $st->fetch(PDO::FETCH_ASSOC);

    return $doc ? documento_mostrador_enriquecer($doc) : null;
}

/** @return array{ok: bool, message?: string, modo?: string, alumno?: array, documentos?: list, documento?: array} */
function documento_mostrador_buscar(PDO $pdo, string $q, int $idPlantel): array
{
    if (!documento_puede_mostrador()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $q = trim($q);
    if ($q === '') {
        return ['ok' => false, 'message' => 'Indique número de control o folio del documento'];
    }

    if (strlen($q) >= 6 && preg_match('/^[A-Za-z0-9-]+$/', $q)) {
        $doc = documento_mostrador_por_folio($pdo, $q, $idPlantel);
        if ($doc) {
            return [
                'ok' => true,
                'modo' => 'folio',
                'documento' => $doc,
                'alumno' => [
                    'id_alumno' => (int) ($doc['id_alumno'] ?? 0),
                    'nombre_completo' => $doc['alumno_nombre'] ?? '',
                    'numero_control' => $doc['numero_control'] ?? '',
                ],
                'documentos' => [$doc],
            ];
        }
    }

    if (!function_exists('pago_buscar_alumno_control')) {
        return ['ok' => false, 'message' => 'Búsqueda no disponible'];
    }
    $al = pago_buscar_alumno_control($pdo, $q, $idPlantel);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno o documento no encontrado'];
    }

    return [
        'ok' => true,
        'modo' => 'alumno',
        'alumno' => $al,
        'documentos' => documento_mostrador_listar_alumno($pdo, (int) $al['id_alumno'], $idPlantel),
    ];
}

function documento_puede_gestionar_diplomas(): bool
{
    return in_array(rbac_rol_efectivo(), ['coordinador', 'director', 'supervisor'], true);
}

function documento_producto_constancia(PDO $pdo): ?array
{
    documento_ensure_schema($pdo);
    $st = $pdo->query("SELECT * FROM productos WHERE clave = 'CONST-EST' AND activo = 1 LIMIT 1");

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @return list<array<string, mixed>> */
function documento_plantillas_listar(PDO $pdo, ?string $tipo = null, ?int $idPlantel = null): array
{
    documento_ensure_schema($pdo);
    $sql = 'SELECT * FROM documento_plantilla WHERE activo = 1';
    $params = [];
    if ($tipo !== null && $tipo !== '') {
        $sql .= ' AND tipo = ?';
        $params[] = $tipo;
    }
    if ($idPlantel !== null && $idPlantel > 0) {
        $sql .= ' AND (id_plantel IS NULL OR id_plantel = ?)';
        $params[] = $idPlantel;
    }
    $sql .= ' ORDER BY tipo, nombre';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function documento_plantilla_obtener(PDO $pdo, int $idPlantilla): ?array
{
    documento_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM documento_plantilla WHERE id_plantilla = ? LIMIT 1');
    $st->execute([$idPlantilla]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['campos_json']) && is_string($row['campos_json'])) {
        $row['campos_json'] = json_decode($row['campos_json'], true);
    }

    return $row ?: null;
}

/** @param array<string, mixed> $data */
function documento_plantilla_guardar(PDO $pdo, array $data): array
{
    documento_ensure_schema($pdo);
    if (!documento_puede_configurar_plantillas()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $id = (int) ($data['id_plantilla'] ?? 0);
    $tipo = in_array($data['tipo'] ?? '', ['constancia', 'diploma'], true) ? $data['tipo'] : 'constancia';
    $nombre = trim((string) ($data['nombre'] ?? ''));
    if ($nombre === '') {
        return ['ok' => false, 'message' => 'Nombre obligatorio'];
    }
    $campos = $data['campos_json'] ?? [];
    if (is_string($campos)) {
        $campos = json_decode($campos, true) ?: [];
    }
    $params = [
        $tipo,
        $nombre,
        !empty($data['id_plantel']) ? (int) $data['id_plantel'] : null,
        (float) ($data['ancho_mm'] ?? 215.9),
        (float) ($data['alto_mm'] ?? 279.4),
        json_encode(array_values($campos), JSON_UNESCAPED_UNICODE),
        (int) ($data['vigencia_dias'] ?? 90),
    ];
    if ($id > 0) {
        $params[] = $id;
        $pdo->prepare(
            'UPDATE documento_plantilla SET tipo=?, nombre=?, id_plantel=?, ancho_mm=?, alto_mm=?, campos_json=?, vigencia_dias=? WHERE id_plantilla=?'
        )->execute($params);
    } else {
        $pdo->prepare(
            'INSERT INTO documento_plantilla (tipo, nombre, id_plantel, ancho_mm, alto_mm, campos_json, vigencia_dias) VALUES (?,?,?,?,?,?,?)'
        )->execute($params);
        $id = (int) $pdo->lastInsertId();
    }

    return ['ok' => true, 'message' => 'Plantilla guardada', 'id_plantilla' => $id];
}

function documento_subir_imagen_plantilla(array $file, string $subdir): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Archivo no recibido'];
    }
    $mime = mime_content_type($file['tmp_name']) ?: '';
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) {
        return ['ok' => false, 'message' => 'Use JPG o PNG'];
    }
    $dir = $subdir === 'firma' ? DOCUMENTO_FIRMA_DIR : DOCUMENTO_PLANTILLA_DIR;
    $name = $subdir . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $rel = $dir . '/' . $name;
    $abs = dirname(__DIR__) . '/' . $rel;
    if (!move_uploaded_file($file['tmp_name'], $abs)) {
        return ['ok' => false, 'message' => 'No se pudo guardar'];
    }

    return ['ok' => true, 'path' => $rel];
}

function documento_generar_folio(PDO $pdo): string
{
    return 'DOC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function documento_generar_token(): string
{
    return bin2hex(random_bytes(16));
}

/** @param list<string> $opciones @param array<string, mixed> $extra */
function documento_resolver_datos_alumno(
    PDO $pdo,
    int $idAlumno,
    int $idPlantel,
    array $opciones,
    array $extra = [],
    ?array $docMeta = null
): array {
    $al = alumno_obtener($pdo, $idAlumno, $idPlantel);
    if (!$al) {
        return [];
    }
    $datos = [];
    $nombre = alumno_nombre_completo($al);
    $grupos = alumno_portal_grupos_activos($pdo, $idAlumno);
    $grupo = $grupos[0] ?? [];
    $idGrupo = (int) ($grupo['id_grupo'] ?? 0);
    $horario = '';
    if ($idGrupo > 0) {
        $gh = $pdo->prepare('SELECT horario_texto FROM grupos WHERE id_grupo = ?');
        $gh->execute([$idGrupo]);
        $horario = (string) ($gh->fetchColumn() ?: '');
    }

    $plantel = plantel_find($pdo, $idPlantel);
    $califs = alumno_calificaciones_fase($pdo, $idAlumno);
    $calText = [];
    foreach ($califs as $c) {
        if (($c['calificacion'] ?? '') !== '' && $c['calificacion'] !== null) {
            $calText[] = ($c['nombre_fase'] ?? '') . ': ' . $c['calificacion'];
        }
    }

    $fechaInicio = $al['fecha_inscripcion'] ?? $al['creado_en'] ?? null;
    $tiempoEstudio = '—';
    if ($fechaInicio) {
        try {
            $d1 = new DateTimeImmutable(substr((string) $fechaInicio, 0, 10));
            $d2 = new DateTimeImmutable('today');
            $meses = ((int) $d2->format('Y') - (int) $d1->format('Y')) * 12 + ((int) $d2->format('n') - (int) $d1->format('n'));
            $tiempoEstudio = $meses > 0 ? $meses . ' mes(es)' : 'Menos de 1 mes';
        } catch (Throwable $e) {
        }
    }

    $map = [
        'nombre_completo' => $nombre,
        'numero_control' => (string) ($al['numero_control'] ?? ''),
        'curp' => trim((string) ($extra['curp'] ?? $al['curp'] ?? '')),
        'especialidad' => (string) ($grupo['especialidad'] ?? $al['especialidad_nombre'] ?? ''),
        'grupo_clave' => (string) ($grupo['clave'] ?? ''),
        'horario' => $horario,
        'calificaciones' => implode('; ', $calText) ?: 'Sin calificaciones registradas',
        'tiempo_estudio' => $tiempoEstudio,
        'plantel_nombre' => (string) ($plantel['nombre'] ?? $plantel['clave'] ?? ''),
        'fecha_emision' => date('d/m/Y'),
        'folio' => (string) ($docMeta['folio'] ?? ''),
        'vigencia_hasta' => !empty($docMeta['vigente_hasta']) ? date('d/m/Y', strtotime($docMeta['vigente_hasta'])) : '',
        'texto_proposito' => trim((string) ($extra['texto_proposito'] ?? 'A quien corresponda')),
        'curso_nombre' => (string) ($extra['curso_nombre'] ?? $grupo['especialidad'] ?? ''),
        'fecha_fin_curso' => (string) ($extra['fecha_fin_curso'] ?? date('d/m/Y')),
    ];

    foreach ($opciones as $k) {
        if (isset($map[$k])) {
            $datos[$k] = $map[$k];
        }
    }

    return $datos;
}

/** @param list<string> $opciones */
function documento_solicitar_constancia(
    PDO $pdo,
    int $idAlumno,
    int $idPlantel,
    int $idPlantilla,
    array $opciones,
    array $extra = []
): array {
    documento_ensure_schema($pdo);
    if (!alumno_portal_exigir_propio($idAlumno) && !documento_puede_marcar_pagada()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    $pl = documento_plantilla_obtener($pdo, $idPlantilla);
    if (!$pl || ($pl['tipo'] ?? '') !== 'constancia') {
        return ['ok' => false, 'message' => 'Plantilla no válida'];
    }
    $prod = documento_producto_constancia($pdo);
    $camposDisp = documento_campos_disponibles('constancia');
    foreach ($camposDisp as $k => $meta) {
        if (!empty($meta['manual']) && in_array($k, $opciones, true) && trim((string) ($extra[$k] ?? '')) === '') {
            return ['ok' => false, 'message' => 'Complete el campo: ' . ($meta['label'] ?? $k)];
        }
    }

    $folio = documento_generar_folio($pdo);
    $token = documento_generar_token();
    $pdo->prepare(
        'INSERT INTO alumno_documento (tipo, id_alumno, id_plantel, id_plantilla, id_producto, folio, token_verificacion, campos_opciones, campos_extra, estado)
         VALUES (\'constancia\',?,?,?,?,?,?,?,?, \'pendiente_pago\')'
    )->execute([
        $idAlumno,
        $idPlantel,
        $idPlantilla,
        $prod['id_producto'] ?? null,
        $folio,
        $token,
        json_encode(array_values($opciones), JSON_UNESCAPED_UNICODE),
        json_encode($extra, JSON_UNESCAPED_UNICODE),
    ]);

    return [
        'ok' => true,
        'message' => 'Solicitud registrada. Pase a recepción a pagar para obtener su constancia.',
        'id_documento' => (int) $pdo->lastInsertId(),
        'folio' => $folio,
        'precio' => (float) ($prod['precio'] ?? 0),
        'producto' => $prod['nombre'] ?? 'Constancia de estudios',
    ];
}

/** @return list<array<string, mixed>> */
function documento_listar_pendientes(PDO $pdo, int $idPlantel, ?string $estado = null): array
{
    documento_ensure_schema($pdo);
    $sql = 'SELECT d.*, CONCAT(a.nombres, \' \', a.apellido_paterno, \' \', COALESCE(a.apellido_materno,\'\')) AS alumno_nombre,
                   a.numero_control, p.nombre AS producto_nombre, p.precio
            FROM alumno_documento d
            INNER JOIN alumnos a ON a.id_alumno = d.id_alumno
            LEFT JOIN productos p ON p.id_producto = d.id_producto
            WHERE d.id_plantel = ?';
    $params = [$idPlantel];
    if ($estado) {
        $sql .= ' AND d.estado = ?';
        $params[] = $estado;
    } else {
        $sql .= ' AND d.estado IN (\'pendiente_pago\', \'pagada\')';
    }
    $sql .= ' ORDER BY d.solicitado_en DESC LIMIT 200';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function documento_cobros_pendientes_alumno(PDO $pdo, int $idAlumno, ?int $idPlantel = null): array
{
    documento_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT d.id_documento, d.id_alumno, d.id_producto, d.folio, d.solicitado_en,
                COALESCE(p.precio, 0) AS precio, p.nombre AS producto_nombre
         FROM alumno_documento d
         LEFT JOIN productos p ON p.id_producto = d.id_producto
         WHERE d.id_plantel = ? AND d.id_alumno = ? AND d.tipo = \'constancia\'
           AND d.estado = \'pendiente_pago\' AND d.id_pago IS NULL
         ORDER BY d.solicitado_en DESC'
    );
    $st->execute([$idPlantel, $idAlumno]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Vincula pago de caja a solicitud de constancia y genera PDF. */
function documento_aplicar_pago_pos(
    PDO $pdo,
    int $idDocumento,
    int $idPago,
    int $idPlantel,
    int $idUsuario
): array {
    documento_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM alumno_documento WHERE id_documento = ? AND id_plantel = ? AND tipo = \'constancia\''
    );
    $st->execute([$idDocumento, $idPlantel]);
    $doc = $st->fetch(PDO::FETCH_ASSOC);
    if (!$doc) {
        return ['ok' => false, 'message' => 'Constancia no encontrada'];
    }
    if (($doc['estado'] ?? '') === 'pagada') {
        return [
            'ok' => true,
            'message' => 'La constancia ya estaba pagada',
            'id_documento' => $idDocumento,
            'pdf_url' => hay_asset_url('documento_pdf.php?id=' . $idDocumento),
        ];
    }
    if (($doc['estado'] ?? '') !== 'pendiente_pago') {
        return ['ok' => false, 'message' => 'Estado de constancia no válido'];
    }

    return documento_marcar_pagada($pdo, $idDocumento, $idPlantel, $idUsuario, $idPago)
        + ['id_documento' => $idDocumento, 'pdf_url' => hay_asset_url('documento_pdf.php?id=' . $idDocumento)];
}

function documento_contar_pendientes_plantel(PDO $pdo, int $idPlantel): int
{
    documento_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM alumno_documento WHERE id_plantel = ? AND tipo = 'constancia' AND estado = 'pendiente_pago'"
    );
    $st->execute([$idPlantel]);

    return (int) $st->fetchColumn();
}

function documento_contar_pendientes_entrega_plantel(PDO $pdo, int $idPlantel, ?string $tipo = null): int
{
    documento_ensure_schema($pdo);
    $sql = "SELECT COUNT(*) FROM alumno_documento
            WHERE id_plantel = ? AND estado = 'pagada' AND entregado_en IS NULL";
    $params = [$idPlantel];
    if ($tipo === 'diploma' || $tipo === 'constancia') {
        $sql .= ' AND tipo = ?';
        $params[] = $tipo;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return (int) $st->fetchColumn();
}

/** @return list<array<string, mixed>> */
function documento_entrega_listar(PDO $pdo, int $idPlantel, ?string $tipo = null, int $limite = 120): array
{
    if (!documento_puede_entregar()) {
        return [];
    }
    documento_ensure_schema($pdo);
    $sql = "SELECT d.*, CONCAT(a.nombres, ' ', a.apellido_paterno, ' ', COALESCE(a.apellido_materno,'')) AS alumno_nombre,
                   a.numero_control, g.clave AS grupo_clave,
                   CONCAT(u.nombre, ' ', u.apellido) AS entregado_por_nombre
            FROM alumno_documento d
            INNER JOIN alumnos a ON a.id_alumno = d.id_alumno
            LEFT JOIN grupos g ON g.id_grupo = d.id_grupo
            LEFT JOIN usuarios u ON u.id_usuario = d.entregado_por
            WHERE d.id_plantel = ? AND d.estado = 'pagada' AND d.entregado_en IS NULL";
    $params = [$idPlantel];
    if ($tipo === 'diploma' || $tipo === 'constancia') {
        $sql .= ' AND d.tipo = ?';
        $params[] = $tipo;
    }
    $sql .= " ORDER BY CASE WHEN d.tipo = 'diploma' THEN 0 ELSE 1 END, d.pagado_en ASC, d.solicitado_en ASC
              LIMIT " . max(1, min(200, $limite));
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return array_map('documento_mostrador_enriquecer', $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

/** @return array{ok: bool, message: string} */
function documento_marcar_entregado(PDO $pdo, int $idDocumento, int $idPlantel, int $idUsuario): array
{
    if (!documento_puede_entregar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    documento_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM alumno_documento WHERE id_documento = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$idDocumento, $idPlantel]);
    $doc = $st->fetch(PDO::FETCH_ASSOC);
    if (!$doc) {
        return ['ok' => false, 'message' => 'Documento no encontrado'];
    }
    if (($doc['estado'] ?? '') !== 'pagada') {
        return ['ok' => false, 'message' => 'Solo se entregan documentos ya pagados o emitidos'];
    }
    if (!empty($doc['entregado_en'])) {
        return ['ok' => false, 'message' => 'Este documento ya fue marcado como entregado'];
    }

    $pdo->prepare(
        'UPDATE alumno_documento SET entregado_en = NOW(), entregado_por = ? WHERE id_documento = ?'
    )->execute([$idUsuario > 0 ? $idUsuario : null, $idDocumento]);

    $tipo = ($doc['tipo'] ?? '') === 'diploma' ? 'Diploma' : 'Constancia';

    return ['ok' => true, 'message' => $tipo . ' entregado · folio ' . ($doc['folio'] ?? '')];
}

/** @return list<array<string, mixed>> */
function documento_listar_alumno(PDO $pdo, int $idAlumno): array
{
    documento_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT d.*, p.nombre AS plantilla_nombre FROM alumno_documento d
         LEFT JOIN documento_plantilla p ON p.id_plantilla = d.id_plantilla
         WHERE d.id_alumno = ? ORDER BY d.solicitado_en DESC'
    );
    $st->execute([$idAlumno]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['puede_ver'] = ($r['estado'] ?? '') === 'pagada'
            && (empty($r['vigente_hasta']) || $r['vigente_hasta'] >= date('Y-m-d'));
    }
    unset($r);

    return $rows;
}

function documento_marcar_pagada(PDO $pdo, int $idDocumento, int $idPlantel, int $idUsuario, ?int $idPago = null): array
{
    documento_ensure_schema($pdo);
    if (!documento_puede_marcar_pagada()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $st = $pdo->prepare('SELECT * FROM alumno_documento WHERE id_documento = ? AND id_plantel = ?');
    $st->execute([$idDocumento, $idPlantel]);
    $doc = $st->fetch(PDO::FETCH_ASSOC);
    if (!$doc) {
        return ['ok' => false, 'message' => 'Documento no encontrado'];
    }
    if (($doc['estado'] ?? '') === 'pagada') {
        return ['ok' => false, 'message' => 'Ya estaba pagada'];
    }
    if (($doc['estado'] ?? '') !== 'pendiente_pago') {
        return ['ok' => false, 'message' => 'Estado no válido'];
    }

    $pl = documento_plantilla_obtener($pdo, (int) $doc['id_plantilla']);
    $vigenciaDias = (int) ($pl['vigencia_dias'] ?? 90);
    $vigenteHasta = (new DateTimeImmutable('today'))->modify('+' . $vigenciaDias . ' days')->format('Y-m-d');

    $pdo->prepare(
        'UPDATE alumno_documento SET estado=\'pagada\', pagado_en=NOW(), pagado_por=?, id_pago=?, vigente_hasta=? WHERE id_documento=?'
    )->execute([$idUsuario, $idPago, $vigenteHasta, $idDocumento]);

    $doc['vigente_hasta'] = $vigenteHasta;
    $doc['estado'] = 'pagada';
    $gen = documento_generar_pdf($pdo, (int) $doc['id_documento']);
    if (!$gen['ok']) {
        return ['ok' => true, 'message' => 'Marcada como pagada, pero el PDF falló: ' . ($gen['message'] ?? ''), 'warning' => true];
    }

    return ['ok' => true, 'message' => 'Constancia pagada y generada. Folio ' . $doc['folio']];
}

function documento_qr_data_uri(string $url): string
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('Endroid\QrCode\QrCode') && class_exists('Endroid\QrCode\Writer\PngWriter')) {
            try {
                $qr = \Endroid\QrCode\QrCode::create($url)->setSize(180)->setMargin(4);
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($qr);

                return 'data:image/png;base64,' . base64_encode($result->getString());
            } catch (Throwable $e) {
            }
        }
    }
    if (class_exists('HayExam\ExamPdfHelper')) {
        return \HayExam\ExamPdfHelper::qrDataUri($url);
    }

    return '';
}

function documento_url_verificacion(string $token): string
{
    return hay_asset_url('documento_verificar.php?token=' . rawurlencode($token));
}

/** @return array{ok: bool, message?: string, es_pdf?: bool, contenido?: string, mime?: string, filename?: string, pdf_path?: string} */
function documento_generar_pdf(PDO $pdo, int $idDocumento): array
{
    documento_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM alumno_documento WHERE id_documento = ?');
    $st->execute([$idDocumento]);
    $doc = $st->fetch(PDO::FETCH_ASSOC);
    if (!$doc || ($doc['estado'] ?? '') !== 'pagada') {
        return ['ok' => false, 'message' => 'Documento no disponible para generar'];
    }

    $pl = documento_plantilla_obtener($pdo, (int) $doc['id_plantilla']);
    if (!$pl) {
        return ['ok' => false, 'message' => 'Plantilla no encontrada'];
    }

    $opciones = json_decode((string) ($doc['campos_opciones'] ?? '[]'), true) ?: [];
    $extra = json_decode((string) ($doc['campos_extra'] ?? '{}'), true) ?: [];
    if (!in_array('qr_verificacion', $opciones, true) && ($pl['tipo'] ?? '') === 'constancia') {
        $opciones[] = 'qr_verificacion';
    }
    if (!in_array('folio', $opciones, true)) {
        $opciones[] = 'folio';
    }

    $valores = documento_resolver_datos_alumno(
        $pdo,
        (int) $doc['id_alumno'],
        (int) $doc['id_plantel'],
        $opciones,
        $extra,
        $doc
    );
    $verifyUrl = documento_url_verificacion((string) $doc['token_verificacion']);
    $valores['qr_verificacion'] = documento_qr_data_uri($verifyUrl);

    $html = documento_render_html_plantilla($pl, $valores, $verifyUrl);
    $filename = ($doc['tipo'] ?? 'doc') . '_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $doc['folio']) . '.pdf';

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('Dompdf\Dompdf') && class_exists('Dompdf\Options')) {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $w = (float) ($pl['ancho_mm'] ?? 215.9);
            $h = (float) ($pl['alto_mm'] ?? 279.4);
            /** @var array<int, float> $paperPts */
            $paperPts = [0.0, 0.0, $w * 2.83465, $h * 2.83465];
            $dompdf->setPaper($paperPts, 'portrait');
            $dompdf->render();
            $pdf = $dompdf->output();
            $rel = DOCUMENTO_EMITIDO_DIR . '/' . $filename;
            $abs = dirname(__DIR__) . '/' . $rel;
            file_put_contents($abs, $pdf);
            $pdo->prepare('UPDATE alumno_documento SET pdf_path=?, generado_en=NOW() WHERE id_documento=?')
                ->execute([$rel, $idDocumento]);

            return [
                'ok' => true,
                'es_pdf' => true,
                'contenido' => $pdf,
                'mime' => 'application/pdf',
                'filename' => $filename,
                'pdf_path' => $rel,
            ];
        }
    }

    return ['ok' => false, 'message' => 'Dompdf no disponible'];
}

/** @param array<string, mixed> $plantilla @param array<string, mixed> $valores */
function documento_render_html_plantilla(array $plantilla, array $valores, string $verifyUrl): string
{
    $w = (float) ($plantilla['ancho_mm'] ?? 215.9);
    $h = (float) ($plantilla['alto_mm'] ?? 279.4);
    $campos = $plantilla['campos_json'] ?? [];
    if (is_string($campos)) {
        $campos = json_decode($campos, true) ?: [];
    }
    if ($campos === []) {
        $campos = documento_plantilla_campos_default((string) ($plantilla['tipo'] ?? 'constancia'));
    }

    $fondo = '';
    if (!empty($plantilla['fondo_path'])) {
        $abs = dirname(__DIR__) . '/' . ltrim((string) $plantilla['fondo_path'], '/');
        if (is_file($abs)) {
            $mime = mime_content_type($abs) ?: 'image/png';
            $fondo = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($abs));
        }
    }
    $firma = '';
    if (!empty($plantilla['firma_path'])) {
        $abs = dirname(__DIR__) . '/' . ltrim((string) $plantilla['firma_path'], '/');
        if (is_file($abs)) {
            $mime = mime_content_type($abs) ?: 'image/png';
            $firma = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($abs));
        }
    }

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; }
        .page { position: relative; width: ' . $w . 'mm; height: ' . $h . 'mm; overflow: hidden; }
        .bg { position: absolute; left: 0; top: 0; width: 100%; height: 100%; z-index: 0; }
        .field { position: absolute; z-index: 1; }
        .field-center { text-align: center; width: 100%; left: 0 !important; }
    </style></head><body><div class="page">';
    if ($fondo !== '') {
        $html .= '<img class="bg" src="' . htmlspecialchars($fondo) . '" alt="">';
    }
    foreach ($campos as $c) {
        $campo = (string) ($c['campo'] ?? '');
        if ($campo === '' || $campo === 'firma_digital') {
            continue;
        }
        $x = (float) ($c['x_mm'] ?? 20);
        $y = (float) ($c['y_mm'] ?? 20);
        $fs = (float) ($c['font_size'] ?? 11);
        $align = (string) ($c['align'] ?? 'left');
        $width = (float) ($c['width_mm'] ?? 0);
        if ($campo === 'qr_verificacion') {
            $qr = (string) ($valores['qr_verificacion'] ?? '');
            if ($qr !== '') {
                $html .= '<img class="field" src="' . htmlspecialchars($qr) . '" style="left:' . $x . 'mm;top:' . $y . 'mm;width:28mm;height:28mm;">';
            }
            continue;
        }
        $texto = (string) ($valores[$campo] ?? '');
        $style = 'left:' . $x . 'mm;top:' . $y . 'mm;font-size:' . $fs . 'pt;';
        if ($width > 0) {
            $style .= 'width:' . $width . 'mm;';
        }
        if ($align === 'center') {
            $style .= 'text-align:center;';
        }
        $html .= '<div class="field" style="' . $style . '">' . htmlspecialchars($texto) . '</div>';
    }
    if ($firma !== '') {
        $html .= '<img class="field" src="' . htmlspecialchars($firma) . '" style="left:130mm;top:230mm;width:60mm;">';
    }
    $html .= '<div class="field" style="left:15mm;top:' . ($h - 12) . 'mm;font-size:7pt;color:#555;">Verifique: ' . htmlspecialchars($verifyUrl) . '</div>';
    $html .= '</div></body></html>';

    return $html;
}

/** @return list<array<string, mixed>> */
function documento_plantilla_campos_default(string $tipo): array
{
    if ($tipo === 'diploma') {
        return [
            ['campo' => 'nombre_completo', 'x_mm' => 30, 'y_mm' => 120, 'font_size' => 18, 'align' => 'center', 'width_mm' => 155],
            ['campo' => 'curso_nombre', 'x_mm' => 30, 'y_mm' => 145, 'font_size' => 12, 'align' => 'center', 'width_mm' => 155],
            ['campo' => 'fecha_emision', 'x_mm' => 30, 'y_mm' => 170, 'font_size' => 11, 'align' => 'center', 'width_mm' => 155],
            ['campo' => 'qr_verificacion', 'x_mm' => 170, 'y_mm' => 240, 'font_size' => 10],
        ];
    }

    return [
        ['campo' => 'nombre_completo', 'x_mm' => 25, 'y_mm' => 70, 'font_size' => 14, 'align' => 'center', 'width_mm' => 165],
        ['campo' => 'numero_control', 'x_mm' => 25, 'y_mm' => 90, 'font_size' => 11, 'align' => 'center', 'width_mm' => 165],
        ['campo' => 'texto_proposito', 'x_mm' => 25, 'y_mm' => 110, 'font_size' => 10, 'width_mm' => 165],
        ['campo' => 'calificaciones', 'x_mm' => 25, 'y_mm' => 140, 'font_size' => 9, 'width_mm' => 165],
        ['campo' => 'horario', 'x_mm' => 25, 'y_mm' => 170, 'font_size' => 9, 'width_mm' => 165],
        ['campo' => 'folio', 'x_mm' => 25, 'y_mm' => 200, 'font_size' => 9],
        ['campo' => 'vigencia_hasta', 'x_mm' => 25, 'y_mm' => 210, 'font_size' => 9],
        ['campo' => 'qr_verificacion', 'x_mm' => 170, 'y_mm' => 230, 'font_size' => 10],
    ];
}

function documento_verificar_token(PDO $pdo, string $token): ?array
{
    documento_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT d.*, CONCAT(a.nombres, \' \', a.apellido_paterno) AS alumno_nombre, a.numero_control
         FROM alumno_documento d
         INNER JOIN alumnos a ON a.id_alumno = d.id_alumno
         WHERE d.token_verificacion = ? LIMIT 1'
    );
    $st->execute([trim($token)]);
    $doc = $st->fetch(PDO::FETCH_ASSOC);
    if (!$doc) {
        return null;
    }
    if (($doc['estado'] ?? '') !== 'pagada') {
        $doc['valido'] = false;
        $doc['motivo'] = 'Documento no emitido o pendiente de pago';

        return $doc;
    }
    if (!empty($doc['vigente_hasta']) && $doc['vigente_hasta'] < date('Y-m-d')) {
        $pdo->prepare('UPDATE alumno_documento SET estado=\'expirada\' WHERE id_documento=?')->execute([$doc['id_documento']]);
        $doc['valido'] = false;
        $doc['motivo'] = 'Documento expirado el ' . date('d/m/Y', strtotime($doc['vigente_hasta']));

        return $doc;
    }
    $doc['valido'] = true;

    return $doc;
}

/** @return array{ok: bool, message: string, generados?: int, id_documentos?: list<int>} */
function documento_generar_diplomas_grupo(PDO $pdo, int $idGrupo, int $idPlantel, int $idUsuario): array
{
    documento_ensure_schema($pdo);
    if (!documento_puede_gestionar_diplomas()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $st = $pdo->prepare('SELECT clave, id_especialidad FROM grupos WHERE id_grupo = ? AND id_plantel = ?');
    $st->execute([$idGrupo, $idPlantel]);
    $grupo = $st->fetch(PDO::FETCH_ASSOC);
    if (!$grupo) {
        return ['ok' => false, 'message' => 'Grupo no encontrado'];
    }

    $plantillas = documento_plantillas_listar($pdo, 'diploma', $idPlantel);
    $idPlantilla = (int) ($plantillas[0]['id_plantilla'] ?? 0);
    if ($idPlantilla <= 0) {
        return ['ok' => false, 'message' => 'Configure una plantilla de diploma primero'];
    }

    $stA = $pdo->prepare(
        'SELECT ag.id_alumno FROM alumno_grupos ag WHERE ag.id_grupo = ?'
    );
    $stA->execute([$idGrupo]);
    $ids = array_map('intval', $stA->fetchAll(PDO::FETCH_COLUMN));
    if ($ids === []) {
        return ['ok' => false, 'message' => 'No hay alumnos en el grupo'];
    }

    $esp = $pdo->prepare('SELECT nombre FROM especialidades WHERE id_especialidad = ?');
    $esp->execute([(int) ($grupo['id_especialidad'] ?? 0)]);
    $espNombre = (string) ($esp->fetchColumn() ?: $grupo['clave']);

    $generados = 0;
    $idsDoc = [];
    foreach ($ids as $idAlumno) {
        $ex = $pdo->prepare(
            'SELECT id_documento FROM alumno_documento WHERE id_alumno=? AND id_grupo=? AND tipo=\'diploma\' LIMIT 1'
        );
        $ex->execute([$idAlumno, $idGrupo]);
        if ($ex->fetchColumn()) {
            continue;
        }
        $folio = documento_generar_folio($pdo);
        $token = documento_generar_token();
        $pl = documento_plantilla_obtener($pdo, $idPlantilla);
        $vigencia = (new DateTimeImmutable('today'))->modify('+' . (int) ($pl['vigencia_dias'] ?? 3650) . ' days')->format('Y-m-d');
        $opciones = ['nombre_completo', 'curso_nombre', 'fecha_emision', 'folio', 'qr_verificacion', 'numero_control'];
        $extra = ['curso_nombre' => $espNombre, 'fecha_fin_curso' => date('d/m/Y')];

        $pdo->prepare(
            'INSERT INTO alumno_documento (tipo, id_alumno, id_plantel, id_grupo, id_plantilla, folio, token_verificacion, campos_opciones, campos_extra, estado, vigente_hasta, pagado_en, pagado_por)
             VALUES (\'diploma\',?,?,?,?,?,?,?,?,\'pagada\',?,NOW(),?)'
        )->execute([
            $idAlumno, $idPlantel, $idGrupo, $idPlantilla, $folio, $token,
            json_encode($opciones, JSON_UNESCAPED_UNICODE),
            json_encode($extra, JSON_UNESCAPED_UNICODE),
            $vigencia, $idUsuario,
        ]);
        $idDoc = (int) $pdo->lastInsertId();
        documento_generar_pdf($pdo, $idDoc);
        $generados++;
        $idsDoc[] = $idDoc;
    }

    return [
        'ok' => true,
        'message' => $generados . ' diploma(s) generado(s) para el grupo ' . $grupo['clave'],
        'generados' => $generados,
        'id_documentos' => $idsDoc,
    ];
}

function documento_obtener(PDO $pdo, int $idDocumento, ?int $idAlumno = null): ?array
{
    documento_ensure_schema($pdo);
    $sql = 'SELECT * FROM alumno_documento WHERE id_documento = ?';
    $params = [$idDocumento];
    if ($idAlumno !== null && $idAlumno > 0) {
        $sql .= ' AND id_alumno = ?';
        $params[] = $idAlumno;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
