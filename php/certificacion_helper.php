<?php

/**
 * Certificaciones: catálogo enriquecido, solicitudes, documentos y especialidad CERT.
 */

define('CERT_UPLOAD_MAX', 5 * 1024 * 1024);
define('CERT_UPLOAD_DIR', 'uploads/certificaciones');
define('CERT_CLAVE_ESPECIALIDAD', 'CERT');

function certificacion_puede_acceder(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('menu_certificaciones')) {
        return true;
    }
    return function_exists('asesor_puede_cert_preregistro') && asesor_puede_cert_preregistro();
}

function certificacion_puede_preregistro_asesor(): bool
{
    return function_exists('asesor_puede_cert_preregistro') && asesor_puede_cert_preregistro();
}

function certificacion_puede_administrar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('admin_catalogo')) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['supervisor', 'gerente'], true);
}

function certificacion_ensure_schema(PDO $pdo): void
{
    catalog_ensure_schema($pdo);
    alumno_ensure_schema($pdo);

    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column(
            $pdo,
            'productos',
            'es_certificacion',
            'TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'1=aparece en módulo certificaciones\'',
            'controla_inventario'
        );
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS producto_certificacion (
            id_producto INT UNSIGNED NOT NULL,
            organismo VARCHAR(120) NULL,
            protocolo TEXT NULL COMMENT \'Pasos para presentar el examen\',
            reglamento_texto TEXT NULL,
            reglamento_pdf VARCHAR(255) NULL,
            requiere_reglamento_firmado TINYINT(1) NOT NULL DEFAULT 0,
            software_nombre VARCHAR(160) NULL,
            software_url VARCHAR(500) NULL,
            software_instrucciones TEXT NULL,
            documentos_requeridos JSON NULL COMMENT \'Lista de tipos: INE, CURP, reglamento_firmado, etc.\',
            notas_asesor TEXT NULL,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_producto)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS certificacion_solicitudes (
            id_solicitud INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_alumno INT UNSIGNED NOT NULL,
            id_producto INT UNSIGNED NOT NULL,
            id_usuario_registro INT UNSIGNED NULL,
            id_pago INT UNSIGNED NULL,
            estado ENUM(
                \'pre_registro\',\'documentos_pendientes\',\'pendiente_confirmacion\',\'pendiente_credenciales\',
                \'lista_para_examen\',\'en_proceso\',\'completada\',\'cancelada\',\'reagendamiento\'
            ) NOT NULL DEFAULT \'pre_registro\',
            fecha_examen DATE NULL,
            fecha_solicitada DATE NULL,
            hora_solicitada TIME NULL,
            fecha_confirmada DATE NULL,
            hora_confirmada TIME NULL,
            notas TEXT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_solicitud),
            KEY idx_cert_sol_plantel (id_plantel, estado),
            KEY idx_cert_sol_alumno (id_alumno),
            KEY idx_cert_sol_producto (id_producto)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS certificacion_documentos (
            id_documento INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_solicitud INT UNSIGNED NOT NULL,
            tipo VARCHAR(40) NOT NULL,
            nombre_original VARCHAR(200) NULL,
            ruta VARCHAR(255) NOT NULL,
            validado TINYINT(1) NOT NULL DEFAULT 0,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_documento),
            KEY idx_cert_doc_solicitud (id_solicitud),
            UNIQUE KEY uq_cert_doc_tipo (id_solicitud, tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    certificacion_seed_especialidad($pdo);
    certificacion_migrate_columns($pdo);
    if (function_exists('certificacion_campos_ensure_schema')) {
        certificacion_campos_ensure_schema($pdo);
    }
    if (function_exists('comision_cert_ensure_schema')) {
        comision_cert_ensure_schema($pdo);
    }
}

function certificacion_migrate_columns(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }

    plantel_ensure_column(
        $pdo,
        'producto_certificacion',
        'familia',
        "VARCHAR(40) NOT NULL DEFAULT 'certiport' COMMENT 'Plantilla: cambridge_online, toefl, etc.'",
        'organismo'
    );

    plantel_ensure_column($pdo, 'certificacion_solicitudes', 'fecha_solicitada', 'DATE NULL AFTER fecha_examen', 'fecha_examen');
    plantel_ensure_column($pdo, 'certificacion_solicitudes', 'hora_solicitada', 'TIME NULL AFTER fecha_solicitada', 'fecha_solicitada');
    plantel_ensure_column($pdo, 'certificacion_solicitudes', 'fecha_confirmada', 'DATE NULL AFTER hora_solicitada', 'hora_solicitada');
    plantel_ensure_column($pdo, 'certificacion_solicitudes', 'hora_confirmada', 'TIME NULL AFTER fecha_confirmada', 'hora_confirmada');
    plantel_ensure_column($pdo, 'certificacion_solicitudes', 'fecha_confirmada_en', 'DATETIME NULL AFTER hora_confirmada', 'hora_confirmada');
    plantel_ensure_column($pdo, 'certificacion_solicitudes', 'id_supervisor_confirma', 'INT UNSIGNED NULL AFTER fecha_confirmada_en', 'fecha_confirmada_en');
    plantel_ensure_column($pdo, 'certificacion_solicitudes', 'sede_direccion', 'TEXT NULL AFTER id_supervisor_confirma', 'id_supervisor_confirma');
    plantel_ensure_column($pdo, 'certificacion_solicitudes', 'reagendamientos', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER sede_direccion', 'sede_direccion');
    plantel_ensure_column($pdo, 'certificacion_solicitudes', 'motivo_reagendamiento', 'TEXT NULL AFTER reagendamientos', 'reagendamientos');
    plantel_ensure_column(
        $pdo,
        'certificacion_solicitudes',
        'datos_formulario',
        'JSON NULL COMMENT \'Valores capturados según plantilla del producto\'',
        'notas'
    );

    try {
        $pdo->exec(
            "ALTER TABLE certificacion_solicitudes MODIFY COLUMN estado ENUM(
                'pre_registro','documentos_pendientes','pendiente_confirmacion','pendiente_credenciales',
                'lista_para_examen','en_proceso','completada','cancelada','reagendamiento'
            ) NOT NULL DEFAULT 'pre_registro'"
        );
    } catch (PDOException $e) {
        // instalación nueva ya tiene el ENUM correcto en CREATE TABLE
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS certificacion_accesos (
            id_acceso INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_solicitud INT UNSIGNED NOT NULL,
            vigente TINYINT(1) NOT NULL DEFAULT 1,
            usuario VARCHAR(120) NULL,
            password_acceso VARCHAR(120) NULL,
            institution_id VARCHAR(80) NULL,
            id_examen_alumno VARCHAR(80) NULL,
            clave_dia VARCHAR(80) NULL,
            url_examen VARCHAR(500) NULL,
            url_software VARCHAR(500) NULL,
            url_zoom VARCHAR(500) NULL,
            clave_grupo VARCHAR(80) NULL,
            voucher VARCHAR(120) NULL,
            codigo_curso VARCHAR(120) NULL,
            sede_direccion TEXT NULL,
            contacto_supervisor VARCHAR(200) NULL,
            contacto_nombre VARCHAR(120) NULL,
            notas_entrega TEXT NULL,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_acceso),
            KEY idx_cert_acc_sol (id_solicitud, vigente)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS certificacion_reagendamientos (
            id_reagendamiento INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_solicitud INT UNSIGNED NOT NULL,
            id_acceso_anterior INT UNSIGNED NULL,
            fecha_anterior DATE NULL,
            hora_anterior TIME NULL,
            fecha_nueva DATE NULL,
            hora_nueva TIME NULL,
            motivo TEXT NULL,
            credenciales_nuevas TINYINT(1) NOT NULL DEFAULT 1,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_reagendamiento),
            KEY idx_cert_reag_sol (id_solicitud)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** @return array<string, array<string, mixed>> */
function certificacion_familias(): array
{
    return [
        'cambridge_online' => [
            'label' => 'Cambridge en línea',
            'docs' => ['reglamento_firmado'],
            'alumno_solicita_fecha_hora' => true,
            'supervisor_confirma_fecha' => true,
            'requiere_credenciales_separadas' => true,
            'campos_acceso' => ['usuario', 'password_acceso', 'institution_id', 'url_examen', 'contacto_supervisor', 'contacto_nombre'],
            'instruccion_alumno' => 'Sube el reglamento firmado e indica fecha y hora preferida (horario de oficina). El supervisor confirmará y te enviará usuario, contraseña, Institution ID y enlace al examen.',
        ],
        'cambridge_presencial' => [
            'label' => 'Cambridge presencial',
            'docs' => ['reglamento_firmado'],
            'alumno_solicita_fecha_hora' => true,
            'supervisor_confirma_fecha' => true,
            'presencial' => true,
            'requiere_credenciales_separadas' => false,
            'campos_acceso' => ['sede_direccion', 'contacto_supervisor', 'contacto_nombre'],
            'instruccion_alumno' => 'Sube el reglamento firmado e indica fecha y hora preferida. Recibirás la dirección de la sede (fuera del plantel), fecha y hora confirmadas.',
        ],
        'uks' => [
            'label' => 'UKS',
            'docs' => ['reglamento_firmado'],
            'alumno_solicita_fecha_hora' => true,
            'supervisor_confirma_fecha' => true,
            'requiere_credenciales_separadas' => true,
            'campos_acceso' => ['id_examen_alumno', 'clave_dia', 'url_examen'],
            'instruccion_alumno' => 'Reglamento firmado + fecha/hora preferida. Recibirás ID único, clave del día y enlace al examen.',
        ],
        'toefl' => [
            'label' => 'TOEFL',
            'docs' => ['INE'],
            'alumno_solicita_fecha_hora' => true,
            'supervisor_confirma_fecha' => true,
            'requiere_credenciales_separadas' => true,
            'campos_acceso' => ['id_examen_alumno', 'clave_dia', 'url_software', 'url_zoom', 'url_examen'],
            'instruccion_alumno' => 'Sube tu INE e indica fecha/hora preferida. Recibirás enlace de software, enlace Zoom (varía por fecha), ID de alumno y clave del día.',
        ],
        'oxford' => [
            'label' => 'Oxford',
            'docs' => [],
            'alumno_solicita_fecha_hora' => true,
            'supervisor_confirma_fecha' => true,
            'requiere_credenciales_separadas' => true,
            'campos_acceso' => ['usuario', 'password_acceso', 'institution_id'],
            'instruccion_alumno' => 'Indica fecha y hora preferida. No se requieren documentos; el supervisor proporcionará usuario, contraseña e ID de institución.',
        ],
        'itep' => [
            'label' => 'ITEP',
            'docs' => [],
            'alumno_solicita_fecha_hora' => true,
            'supervisor_confirma_fecha' => true,
            'requiere_credenciales_separadas' => true,
            'campos_acceso' => ['usuario', 'password_acceso', 'url_software'],
            'instruccion_alumno' => 'Indica fecha y hora preferida. Recibirás ID de usuario, contraseña y enlace de descarga del software.',
        ],
        'certiport' => [
            'label' => 'Computación (Certiport)',
            'docs' => [],
            'alumno_solicita_fecha_hora' => true,
            'supervisor_confirma_fecha' => true,
            'requiere_credenciales_separadas' => true,
            'campos_acceso' => ['clave_grupo', 'voucher', 'codigo_curso', 'url_examen', 'notas_entrega'],
            'instruccion_alumno' => 'Crea tu cuenta en Certiport. Indica fecha/hora preferida. Recibirás clave de grupo, voucher del examen y, si aplica, código del curso.',
        ],
    ];
}

/** @return array<string, string> */
function certificacion_campos_acceso_labels(): array
{
    return [
        'usuario' => 'Usuario de acceso',
        'password_acceso' => 'Contraseña',
        'institution_id' => 'Institution ID',
        'id_examen_alumno' => 'ID del alumno (examen)',
        'clave_dia' => 'Clave del día',
        'url_examen' => 'Enlace al examen',
        'url_software' => 'Enlace descarga software',
        'url_zoom' => 'Enlace Zoom (por fecha)',
        'clave_grupo' => 'Clave de grupo (Certiport)',
        'voucher' => 'Voucher del examen',
        'codigo_curso' => 'Código de acceso al curso',
        'sede_direccion' => 'Dirección de la sede',
        'contacto_supervisor' => 'Contacto del supervisor',
        'contacto_nombre' => 'Nombre del supervisor',
        'notas_entrega' => 'Instrucciones adicionales al alumno',
    ];
}

function certificacion_familia_config(?string $familia): array
{
    $familias = certificacion_familias();
    $key = $familia !== null && isset($familias[$familia]) ? $familia : 'certiport';

    return $familias[$key];
}

/** Aplica documentos y flags por defecto según familia si no están definidos. */
function certificacion_aplicar_defaults_familia(array $data): array
{
    $fam = certificacion_familia_config($data['familia'] ?? 'certiport');
    if (empty($data['documentos_requeridos']) && !empty($fam['docs'])) {
        $data['documentos_requeridos'] = $fam['docs'];
    }
    if (!isset($data['requiere_reglamento_firmado']) && in_array('reglamento_firmado', $fam['docs'] ?? [], true)) {
        $data['requiere_reglamento_firmado'] = 1;
    }

    return $data;
}

/** @return array<string, string> */
function certificacion_tipos_documento(): array
{
    return [
        'INE' => 'Identificación oficial (INE)',
        'CURP' => 'CURP',
        'reglamento_firmado' => 'Reglamento firmado',
        'comprobante_domicilio' => 'Comprobante de domicilio',
        'acta_nacimiento' => 'Acta de nacimiento',
        'foto' => 'Fotografía',
        'comprobante_pago' => 'Comprobante de pago',
        'otro' => 'Otro documento',
    ];
}

function certificacion_seed_especialidad(PDO $pdo): void
{
    $st = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE clave = ? LIMIT 1');
    $st->execute([CERT_CLAVE_ESPECIALIDAD]);
    if ($st->fetchColumn()) {
        return;
    }

    $pdo->prepare(
        'INSERT INTO especialidades (
            clave, nombre, descripcion, costo_inscripcion, costo_mensualidad,
            costo_pronto_pago, costo_semanal, duracion_meses, es_fija, visible, activo, orden,
            modalidad, duracion_fase_semanas, inscripcion_por_cuatrimestre, parciales_por_cuatrimestre
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        CERT_CLAVE_ESPECIALIDAD,
        'Certificaciones',
        'Alumnos que solicitan certificaciones internacionales u otras evaluaciones. Sin fases ni parciales.',
        0, 0, 0, 0, 1, 0, 1, 1, 99,
        'regular', 0, 0, 0,
    ]);
}

function certificacion_id_especialidad(PDO $pdo): int
{
    certificacion_seed_especialidad($pdo);
    $st = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE clave = ? LIMIT 1');
    $st->execute([CERT_CLAVE_ESPECIALIDAD]);

    return (int) ($st->fetchColumn() ?: 0);
}

function certificacion_upload_dir(string $subdir = ''): string
{
    $base = dirname(__DIR__) . '/' . CERT_UPLOAD_DIR;
    if ($subdir !== '') {
        $base .= '/' . trim($subdir, '/');
    }
    if (!is_dir($base)) {
        mkdir($base, 0755, true);
    }

    return $base;
}

/** @return array{ok:bool, path?:string|null, message?:string} */
function certificacion_guardar_archivo(array $file, string $subdir, string $prefix): array
{
    $dir = certificacion_upload_dir($subdir);
    $res = hay_upload_guardar(
        $file,
        $dir,
        $prefix . '_' . bin2hex(random_bytes(8)),
        HAY_UPLOAD_MIME_IMAGE_PDF,
        CERT_UPLOAD_MAX,
        true
    );
    if (!$res['ok']) {
        return ['ok' => false, 'message' => $res['message'] ?? 'No se pudo guardar'];
    }

    return ['ok' => true, 'path' => CERT_UPLOAD_DIR . '/' . trim($subdir, '/') . '/' . ($res['filename'] ?? '')];
}

function certificacion_public_url(?string $path): ?string
{
    if ($path === null || trim($path) === '') {
        return null;
    }
    if (function_exists('hay_asset_url')) {
        return hay_asset_url($path);
    }

    return $path;
}

/** @return list<string> */
function certificacion_parse_documentos_requeridos(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }
    $dec = json_decode($json, true);
    if (!is_array($dec)) {
        return [];
    }
    $tipos = array_keys(certificacion_tipos_documento());
    $out = [];
    foreach ($dec as $t) {
        $t = (string) $t;
        if (in_array($t, $tipos, true)) {
            $out[] = $t;
        }
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function certificacion_listar_catalogo(PDO $pdo, int $idPlantel, ?string $q = null): array
{
    certificacion_ensure_schema($pdo);
    $params = [];
    $sql = 'SELECT p.id_producto, p.clave, p.nombre, p.descripcion, p.precio, p.activo, p.visible,
                   c.organismo, c.familia, c.protocolo, c.requiere_reglamento_firmado, c.software_nombre,
                   c.documentos_requeridos
            FROM productos p
            INNER JOIN producto_certificacion c ON c.id_producto = p.id_producto
            WHERE p.es_certificacion = 1 AND p.activo = 1';
    if ($q !== null && trim($q) !== '') {
        $like = '%' . trim($q) . '%';
        $sql .= ' AND (p.nombre LIKE ? OR p.clave LIKE ? OR c.organismo LIKE ? OR p.descripcion LIKE ?)';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    $sql .= ' ORDER BY p.orden ASC, p.nombre ASC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['precio_fmt'] = catalog_format_mxn((float) ($r['precio'] ?? 0));
        $r['docs_requeridos'] = certificacion_parse_documentos_requeridos($r['documentos_requeridos'] ?? null);
        $fam = certificacion_familia_config($r['familia'] ?? null);
        $r['familia_label'] = $fam['label'] ?? '';
    }
    unset($r);

    return $rows;
}

/** @return array<string, mixed>|null */
function certificacion_obtener_detalle(PDO $pdo, int $idProducto): ?array
{
    certificacion_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT p.*, c.organismo, c.familia, c.protocolo, c.reglamento_texto, c.reglamento_pdf,
                c.requiere_reglamento_firmado, c.software_nombre, c.software_url,
                c.software_instrucciones, c.documentos_requeridos, c.notas_asesor,
                c.comision_asesor_default, c.comision_gerente_default
         FROM productos p
         LEFT JOIN producto_certificacion c ON c.id_producto = p.id_producto
         WHERE p.id_producto = ? LIMIT 1'
    );
    $st->execute([$idProducto]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['precio_fmt'] = catalog_format_mxn((float) ($row['precio'] ?? 0));
    $row['docs_requeridos'] = certificacion_parse_documentos_requeridos($row['documentos_requeridos'] ?? null);
    $row['reglamento_pdf_url'] = certificacion_public_url($row['reglamento_pdf'] ?? null);
    $labels = certificacion_tipos_documento();
    $row['docs_requeridos_labels'] = array_map(static fn($k) => $labels[$k] ?? $k, $row['docs_requeridos']);
    $fam = certificacion_familia_config($row['familia'] ?? null);
    $row['familia_label'] = $fam['label'] ?? '';
    $row['familia_config'] = $fam;
    $row['campos_acceso'] = $fam['campos_acceso'] ?? [];
    $row['instruccion_alumno'] = $fam['instruccion_alumno'] ?? '';

    return $row;
}

/** @param array<string, mixed> $data */
function certificacion_guardar_meta(PDO $pdo, int $idProducto, array $data): array
{
    certificacion_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT id_producto FROM productos WHERE id_producto = ? LIMIT 1');
    $st->execute([$idProducto]);
    if (!$st->fetchColumn()) {
        return ['ok' => false, 'message' => 'Producto no encontrado'];
    }

    $data = certificacion_aplicar_defaults_familia($data);
    $familia = trim((string) ($data['familia'] ?? 'certiport'));
    if (!isset(certificacion_familias()[$familia])) {
        $familia = 'certiport';
    }

    $docs = $data['documentos_requeridos'] ?? [];
    if (is_string($docs)) {
        $docs = array_filter(array_map('trim', explode(',', $docs)));
    }
    if (!is_array($docs)) {
        $docs = [];
    }
    $docsJson = json_encode(array_values(array_intersect($docs, array_keys(certificacion_tipos_documento()))), JSON_UNESCAPED_UNICODE);

    $pdo->prepare('UPDATE productos SET es_certificacion = 1, controla_inventario = 0 WHERE id_producto = ?')
        ->execute([$idProducto]);

    $exists = $pdo->prepare('SELECT id_producto FROM producto_certificacion WHERE id_producto = ?');
    $exists->execute([$idProducto]);
    $fields = [
        $familia,
        trim((string) ($data['organismo'] ?? '')),
        trim((string) ($data['protocolo'] ?? '')),
        trim((string) ($data['reglamento_texto'] ?? '')),
        trim((string) ($data['reglamento_pdf'] ?? '')) ?: null,
        !empty($data['requiere_reglamento_firmado']) ? 1 : 0,
        trim((string) ($data['software_nombre'] ?? '')),
        trim((string) ($data['software_url'] ?? '')),
        trim((string) ($data['software_instrucciones'] ?? '')),
        $docsJson,
        trim((string) ($data['notas_asesor'] ?? '')),
        catalog_money($data['comision_asesor_default'] ?? 0),
        catalog_money($data['comision_gerente_default'] ?? 0),
    ];

    if ($exists->fetchColumn()) {
        $pdo->prepare(
            'UPDATE producto_certificacion SET familia=?, organismo=?, protocolo=?, reglamento_texto=?, reglamento_pdf=?,
             requiere_reglamento_firmado=?, software_nombre=?, software_url=?, software_instrucciones=?,
             documentos_requeridos=?, notas_asesor=?, comision_asesor_default=?, comision_gerente_default=? WHERE id_producto=?'
        )->execute([...$fields, $idProducto]);
    } else {
        $pdo->prepare(
            'INSERT INTO producto_certificacion (
                id_producto, familia, organismo, protocolo, reglamento_texto, reglamento_pdf,
                requiere_reglamento_firmado, software_nombre, software_url, software_instrucciones,
                documentos_requeridos, notas_asesor, comision_asesor_default, comision_gerente_default
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([$idProducto, ...$fields]);
    }

    return ['ok' => true, 'message' => 'Información de certificación guardada'];
}

/** @return array{ok:bool, id_alumno?:int, message?:string} */
function certificacion_crear_o_vincular_alumno(PDO $pdo, int $idPlantel, array $data): array
{
    $idAlumno = (int) ($data['id_alumno'] ?? 0);
    if ($idAlumno > 0) {
        if (!plantel_enforce_alumno($pdo, $idAlumno, $idPlantel)) {
            return ['ok' => false, 'message' => 'El alumno no pertenece a este plantel'];
        }
        certificacion_asegurar_inscripcion_cert($pdo, $idAlumno);

        return ['ok' => true, 'id_alumno' => $idAlumno];
    }

    $nombres = trim((string) ($data['nombres'] ?? ''));
    $apPat = trim((string) ($data['apellido_paterno'] ?? ''));
    if ($nombres === '' || $apPat === '') {
        return ['ok' => false, 'message' => 'Nombre y apellido paterno son obligatorios'];
    }

    $idEsp = certificacion_id_especialidad($pdo);
    $nc = alumno_generar_numero_control($pdo, $idPlantel);
    $apMat = trim((string) ($data['apellido_materno'] ?? ''));

    $pdo->prepare(
        'INSERT INTO alumnos (
            id_plantel, numero_control, nombres, apellido_paterno, apellido_materno,
            nombre, apellido, telefono, email, estado, forma_pago, id_especialidad, fecha_alta
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,CURDATE())'
    )->execute([
        $idPlantel,
        $nc,
        $nombres,
        $apPat,
        $apMat,
        $nombres,
        trim($apPat . ' ' . $apMat),
        trim((string) ($data['telefono'] ?? '')) ?: null,
        trim((string) ($data['email'] ?? '')) ?: null,
        'activo',
        'mensual',
        $idEsp ?: null,
    ]);
    $idAlumno = (int) $pdo->lastInsertId();
    certificacion_asegurar_inscripcion_cert($pdo, $idAlumno);
    if (function_exists('usuario_crear_cuenta_alumno')) {
        try {
            usuario_crear_cuenta_alumno($pdo, $idAlumno, $idPlantel);
        } catch (Throwable $e) {
            error_log('certificacion usuario alumno: ' . $e->getMessage());
        }
    }

    return ['ok' => true, 'id_alumno' => $idAlumno, 'numero_control' => $nc];
}

function certificacion_asegurar_inscripcion_cert(PDO $pdo, int $idAlumno): void
{
    $idEsp = certificacion_id_especialidad($pdo);
    if ($idEsp <= 0) {
        return;
    }
    pago_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT id_alumno_especialidad FROM alumno_especialidades WHERE id_alumno = ? AND id_especialidad = ? LIMIT 1'
    );
    $st->execute([$idAlumno, $idEsp]);
    if ($st->fetchColumn()) {
        return;
    }
    $pdo->prepare(
        'INSERT INTO alumno_especialidades (
            id_alumno, id_especialidad, forma_pago, fecha_inscripcion,
            costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal,
            duracion_meses, inscripcion_cubierta, activo
        ) VALUES (?,?,?,CURDATE(),0,0,0,0,1,1,1)'
    )->execute([$idAlumno, $idEsp, 'mensual']);
}

function certificacion_puede_supervisar(): bool
{
    if (certificacion_puede_administrar()) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['admin', 'supervisor', 'gerente'], true);
}

/** Editar comisiones y precio en expediente (supervisor / gerente / admin catálogo). */
function certificacion_puede_editar_comisiones(): bool
{
    return certificacion_puede_administrar() || certificacion_puede_supervisar();
}

function certificacion_parse_fecha_hora(?string $fecha, ?string $hora): array
{
    $fecha = trim((string) $fecha);
    $hora = trim((string) $hora);
    if ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $fecha = '';
    }
    if ($hora !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
        $hora = '';
    }
    if ($hora !== '' && strlen($hora) === 5) {
        $hora .= ':00';
    }

    return ['fecha' => $fecha, 'hora' => $hora];
}

function certificacion_parse_hora(?string $hora): ?string
{
    $hora = trim((string) $hora);
    if ($hora === '') {
        return null;
    }
    if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
        return $hora . ':00';
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
        return $hora;
    }

    return null;
}

function certificacion_calcular_estado_inicial(array $det, string $fechaSol, string $horaSol): string
{
    $docsReq = $det['docs_requeridos'] ?? [];
    if (!empty($det['requiere_reglamento_firmado']) && !in_array('reglamento_firmado', $docsReq, true)) {
        $docsReq[] = 'reglamento_firmado';
    }
    if ($docsReq !== []) {
        return 'documentos_pendientes';
    }
    if ($fechaSol !== '' && $horaSol !== '' && !empty(certificacion_familia_config($det['familia'] ?? null)['supervisor_confirma_fecha'])) {
        return 'pendiente_confirmacion';
    }

    return 'pre_registro';
}

/** @param array<string, mixed> $datosForm @param array<string, mixed> $data */
function certificacion_merge_datos_formulario_base(array $datosForm, array $data): array
{
    $pairs = [
        'nombres' => ['nombre', 'nombres'],
        'apellido_paterno' => ['apellido_paterno'],
        'apellido_materno' => ['apellido_materno'],
        'telefono' => ['telefono'],
        'email' => ['email'],
    ];
    foreach ($pairs as $src => $keys) {
        $val = trim((string) ($data[$src] ?? ''));
        if ($val === '') {
            continue;
        }
        foreach ($keys as $k) {
            if (!isset($datosForm[$k]) || trim((string) $datosForm[$k]) === '') {
                $datosForm[$k] = $val;
            }
        }
    }

    return $datosForm;
}

/** @return array{ok:bool, id_solicitud?:int, message?:string} */
function certificacion_crear_solicitud(PDO $pdo, int $idPlantel, array $data): array
{
    certificacion_ensure_schema($pdo);
    $idProducto = (int) ($data['id_producto'] ?? 0);
    if ($idProducto <= 0) {
        return ['ok' => false, 'message' => 'Seleccione una certificación'];
    }

    $det = certificacion_obtener_detalle($pdo, $idProducto);
    if (!$det || !(int) ($det['es_certificacion'] ?? 0)) {
        return ['ok' => false, 'message' => 'Certificación no configurada'];
    }

    $al = certificacion_crear_o_vincular_alumno($pdo, $idPlantel, $data);
    if (!$al['ok']) {
        return $al;
    }
    $idAlumno = (int) $al['id_alumno'];

    $fh = certificacion_parse_fecha_hora($data['fecha_solicitada'] ?? $data['fecha_examen'] ?? '', $data['hora_solicitada'] ?? '');
    $fechaSol = $fh['fecha'];
    $horaSol = $fh['hora'];

    $docsReq = $det['docs_requeridos'] ?? [];
    if (!empty($det['requiere_reglamento_firmado']) && !in_array('reglamento_firmado', $docsReq, true)) {
        $docsReq[] = 'reglamento_firmado';
    }
    $estado = certificacion_calcular_estado_inicial($det, $fechaSol, $horaSol);

    $datosForm = $data['datos_formulario'] ?? null;
    if (!is_array($datosForm)) {
        $datosForm = [];
    }
    $datosForm = certificacion_merge_datos_formulario_base($datosForm, $data);
    $datosFormJson = $datosForm !== []
        ? json_encode($datosForm, JSON_UNESCAPED_UNICODE)
        : null;

    $defaults = comision_cert_defaults_producto($pdo, $idProducto);
    $puedeDefinirComision = certificacion_puede_administrar();
    if ($puedeDefinirComision) {
        $precio = isset($data['precio_cobrado']) && $data['precio_cobrado'] !== ''
            ? catalog_money($data['precio_cobrado']) : $defaults['precio'];
        $comA = isset($data['comision_asesor']) && $data['comision_asesor'] !== ''
            ? catalog_money($data['comision_asesor']) : $defaults['comision_asesor'];
        $comG = isset($data['comision_gerente']) && $data['comision_gerente'] !== ''
            ? catalog_money($data['comision_gerente']) : $defaults['comision_gerente'];
    } else {
        $precio = $defaults['precio'];
        $comA = $defaults['comision_asesor'];
        $comG = $defaults['comision_gerente'];
    }
    $idAsesor = (int) ($data['id_usuario_asesor'] ?? $_SESSION['user_id'] ?? 0) ?: null;

    $pdo->prepare(
        'INSERT INTO certificacion_solicitudes (
            id_plantel, id_alumno, id_producto, id_usuario_registro, id_usuario_asesor, estado,
            fecha_examen, fecha_solicitada, hora_solicitada, notas, datos_formulario,
            precio_cobrado, comision_asesor, comision_gerente
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idPlantel,
        $idAlumno,
        $idProducto,
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
        $idAsesor,
        $estado,
        null,
        $fechaSol !== '' ? $fechaSol : null,
        $horaSol !== '' ? $horaSol : null,
        trim((string) ($data['notas'] ?? '')) ?: null,
        $datosFormJson,
        $precio,
        $comA,
        $comG,
    ]);

    $idSol = (int) $pdo->lastInsertId();
    comision_cert_registrar_historial($pdo, $idSol, $precio, $comA, $comG, 'Registro inicial');

    return [
        'ok' => true,
        'message' => 'Solicitud registrada',
        'id_solicitud' => $idSol,
        'id_alumno' => $idAlumno,
    ];
}

/** @return list<array<string, mixed>> */
function certificacion_listar_solicitudes(PDO $pdo, int $idPlantel, ?string $estado = null, ?string $q = null): array
{
    certificacion_ensure_schema($pdo);
    $params = [$idPlantel];
    $sql = 'SELECT s.*, p.nombre AS certificacion, p.clave AS cert_clave, p.precio,
                   a.numero_control,
                   TRIM(CONCAT(a.nombres, \' \', a.apellido_paterno, \' \', IFNULL(a.apellido_materno,\'\'))) AS alumno
            FROM certificacion_solicitudes s
            INNER JOIN productos p ON p.id_producto = s.id_producto
            INNER JOIN alumnos a ON a.id_alumno = s.id_alumno
            WHERE s.id_plantel = ?';
    if ($estado !== null && $estado !== '') {
        $sql .= ' AND s.estado = ?';
        $params[] = $estado;
    }
    if ($q !== null && trim($q) !== '') {
        $like = '%' . trim($q) . '%';
        $sql .= ' AND (a.nombres LIKE ? OR a.apellido_paterno LIKE ? OR a.numero_control LIKE ? OR p.nombre LIKE ?)';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    $sql .= ' ORDER BY s.creado_en DESC LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string, mixed>|null */
function certificacion_obtener_solicitud(PDO $pdo, int $idSolicitud, ?int $idPlantel = null): ?array
{
    certificacion_ensure_schema($pdo);
    $params = [$idSolicitud];
    $sql = 'SELECT s.*, p.nombre AS certificacion, p.clave AS cert_clave, p.precio, p.id_producto,
                   a.numero_control, a.nombres, a.apellido_paterno, a.apellido_materno, a.telefono, a.email,
                   c.organismo, c.familia, c.protocolo, c.reglamento_texto, c.reglamento_pdf, c.requiere_reglamento_firmado,
                   c.software_nombre, c.software_url, c.software_instrucciones, c.documentos_requeridos, c.notas_asesor
            FROM certificacion_solicitudes s
            INNER JOIN productos p ON p.id_producto = s.id_producto
            INNER JOIN alumnos a ON a.id_alumno = s.id_alumno
            LEFT JOIN producto_certificacion c ON c.id_producto = s.id_producto
            WHERE s.id_solicitud = ?';
    if ($idPlantel !== null && $idPlantel > 0) {
        $sql .= ' AND s.id_plantel = ?';
        $params[] = $idPlantel;
    }
    $sql .= ' LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $row['alumno'] = trim(($row['nombres'] ?? '') . ' ' . ($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? ''));
    $row['precio_fmt'] = catalog_format_mxn((float) ($row['precio'] ?? 0));
    $row['docs_requeridos'] = certificacion_parse_documentos_requeridos($row['documentos_requeridos'] ?? null);
    $labels = certificacion_tipos_documento();
    $row['docs_requeridos_labels'] = array_map(static fn($k) => $labels[$k] ?? $k, $row['docs_requeridos']);
    $row['reglamento_pdf_url'] = certificacion_public_url($row['reglamento_pdf'] ?? null);

    $stDoc = $pdo->prepare('SELECT * FROM certificacion_documentos WHERE id_solicitud = ? ORDER BY tipo');
    $stDoc->execute([$idSolicitud]);
    $docs = $stDoc->fetchAll(PDO::FETCH_ASSOC);
    foreach ($docs as &$d) {
        $d['url'] = certificacion_public_url($d['ruta'] ?? null);
        $d['tipo_label'] = $labels[$d['tipo'] ?? ''] ?? ($d['tipo'] ?? '');
    }
    unset($d);
    $row['documentos'] = $docs;

    $subidos = array_column($docs, 'tipo');
    $row['docs_pendientes'] = array_values(array_diff($row['docs_requeridos'], $subidos));

    $fam = certificacion_familia_config($row['familia'] ?? null);
    $row['familia_label'] = $fam['label'] ?? '';
    $row['familia_config'] = $fam;
    $row['campos_acceso'] = $fam['campos_acceso'] ?? [];
    $row['campos_acceso_labels'] = certificacion_campos_acceso_labels();
    $row['acceso_vigente'] = certificacion_obtener_acceso_vigente($pdo, $idSolicitud);
    $row['historial_reagendamientos'] = certificacion_historial_reagendamientos($pdo, $idSolicitud);
    $row['historial_accesos'] = certificacion_historial_accesos($pdo, $idSolicitud);

    return $row;
}

/** @return list<array<string, mixed>> */
function certificacion_solicitudes_alumno(PDO $pdo, int $idAlumno): array
{
    certificacion_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT s.*, p.nombre AS certificacion, p.precio
         FROM certificacion_solicitudes s
         INNER JOIN productos p ON p.id_producto = s.id_producto
         WHERE s.id_alumno = ?
         ORDER BY s.creado_en DESC'
    );
    $st->execute([$idAlumno]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @param array<string, mixed> $data */
function certificacion_actualizar_solicitud(PDO $pdo, int $idSolicitud, int $idPlantel, array $data): array
{
    $sol = certificacion_obtener_solicitud($pdo, $idSolicitud, $idPlantel);
    if (!$sol) {
        return ['ok' => false, 'message' => 'Solicitud no encontrada'];
    }

    $estado = trim((string) ($data['estado'] ?? $sol['estado'] ?? ''));
    $validEstados = array_keys(certificacion_estados_etiquetas());
    if (!in_array($estado, $validEstados, true)) {
        $estado = (string) $sol['estado'];
    }

    $fechaSol = array_key_exists('fecha_solicitada', $data)
        ? trim((string) $data['fecha_solicitada'])
        : ($sol['fecha_solicitada'] ?? '');
    $horaSol = array_key_exists('hora_solicitada', $data)
        ? certificacion_parse_hora($data['hora_solicitada'] ?? null)
        : ($sol['hora_solicitada'] ?? null);

    $fechaExamen = array_key_exists('fecha_examen', $data)
        ? trim((string) $data['fecha_examen'])
        : ($sol['fecha_examen'] ?? '');
    if ($fechaSol !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaSol)) {
        return ['ok' => false, 'message' => 'Fecha solicitada inválida'];
    }

    $notas = array_key_exists('notas', $data) ? trim((string) $data['notas']) : ($sol['notas'] ?? '');

    $pdo->prepare(
        'UPDATE certificacion_solicitudes SET estado = ?, fecha_examen = ?, fecha_solicitada = ?,
         hora_solicitada = ?, notas = ? WHERE id_solicitud = ?'
    )->execute([
        $estado,
        ($fechaExamen !== '' ? $fechaExamen : ($fechaSol !== '' ? $fechaSol : null)),
        $fechaSol !== '' ? $fechaSol : null,
        $horaSol,
        $notas !== '' ? $notas : null,
        $idSolicitud,
    ]);

    certificacion_recalcular_estado_solicitud($pdo, $idSolicitud);

    return ['ok' => true, 'message' => 'Solicitud actualizada'];
}

function certificacion_subir_documento(
    PDO $pdo,
    int $idSolicitud,
    int $idPlantel,
    string $tipo,
    array $file
): array {
    $sol = certificacion_obtener_solicitud($pdo, $idSolicitud, $idPlantel);
    if (!$sol) {
        return ['ok' => false, 'message' => 'Solicitud no encontrada'];
    }

    $tipos = certificacion_tipos_documento();
    if (!isset($tipos[$tipo])) {
        return ['ok' => false, 'message' => 'Tipo de documento no válido'];
    }

    $up = certificacion_guardar_archivo($file, 'solicitud_' . $idSolicitud, $tipo);
    if (!$up['ok']) {
        return $up;
    }

    $st = $pdo->prepare('SELECT id_documento, ruta FROM certificacion_documentos WHERE id_solicitud = ? AND tipo = ?');
    $st->execute([$idSolicitud, $tipo]);
    $prev = $st->fetch(PDO::FETCH_ASSOC);
    if ($prev && !empty($prev['ruta'])) {
        $full = dirname(__DIR__) . '/' . ltrim($prev['ruta'], '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }

    if ($prev) {
        $pdo->prepare(
            'UPDATE certificacion_documentos SET ruta = ?, nombre_original = ?, validado = 0, id_usuario = ?, creado_en = NOW()
             WHERE id_documento = ?'
        )->execute([
            $up['path'],
            $file['name'] ?? null,
            (int) ($_SESSION['user_id'] ?? 0) ?: null,
            (int) $prev['id_documento'],
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO certificacion_documentos (id_solicitud, tipo, nombre_original, ruta, id_usuario)
             VALUES (?,?,?,?,?)'
        )->execute([
            $idSolicitud,
            $tipo,
            $file['name'] ?? null,
            $up['path'],
            (int) ($_SESSION['user_id'] ?? 0) ?: null,
        ]);
    }

    certificacion_recalcular_estado_solicitud($pdo, $idSolicitud);

    return ['ok' => true, 'message' => 'Documento guardado', 'path' => $up['path']];
}

function certificacion_recalcular_estado_solicitud(PDO $pdo, int $idSolicitud): void
{
    $sol = certificacion_obtener_solicitud($pdo, $idSolicitud);
    if (!$sol || in_array($sol['estado'] ?? '', ['completada', 'cancelada'], true)) {
        return;
    }

    $nuevo = (string) ($sol['estado'] ?? 'pre_registro');

    if (($sol['docs_pendientes'] ?? []) !== []) {
        $nuevo = 'documentos_pendientes';
    } elseif (!empty($sol['fecha_solicitada']) && !empty($sol['hora_solicitada']) && empty($sol['fecha_confirmada'])) {
        $nuevo = 'pendiente_confirmacion';
    } elseif (!empty($sol['fecha_confirmada'])) {
        $fam = certificacion_familia_config($sol['familia'] ?? null);
        if (!empty($fam['requiere_credenciales_separadas']) && empty($sol['acceso_vigente'])) {
            $nuevo = 'pendiente_credenciales';
        } elseif (empty($fam['requiere_credenciales_separadas']) || !empty($sol['acceso_vigente'])) {
            $nuevo = 'lista_para_examen';
        }
    }

    if ($nuevo !== ($sol['estado'] ?? '')) {
        $pdo->prepare('UPDATE certificacion_solicitudes SET estado = ? WHERE id_solicitud = ?')
            ->execute([$nuevo, $idSolicitud]);
    }
}

/** @return array<string, mixed>|null */
function certificacion_obtener_acceso_vigente(PDO $pdo, int $idSolicitud): ?array
{
    $st = $pdo->prepare(
        'SELECT * FROM certificacion_accesos WHERE id_solicitud = ? AND vigente = 1 ORDER BY id_acceso DESC LIMIT 1'
    );
    $st->execute([$idSolicitud]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function certificacion_historial_accesos(PDO $pdo, int $idSolicitud): array
{
    $st = $pdo->prepare(
        'SELECT a.*, CONCAT(u.nombre, \' \', u.apellido) AS entregado_por
         FROM certificacion_accesos a
         LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
         WHERE a.id_solicitud = ? ORDER BY a.creado_en DESC'
    );
    $st->execute([$idSolicitud]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string, mixed>> */
function certificacion_historial_reagendamientos(PDO $pdo, int $idSolicitud): array
{
    $st = $pdo->prepare(
        'SELECT r.*, CONCAT(u.nombre, \' \', u.apellido) AS registrado_por
         FROM certificacion_reagendamientos r
         LEFT JOIN usuarios u ON u.id_usuario = r.id_usuario
         WHERE r.id_solicitud = ? ORDER BY r.creado_en DESC'
    );
    $st->execute([$idSolicitud]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Supervisor confirma fecha y hora del examen. */
function certificacion_confirmar_fecha(PDO $pdo, int $idSolicitud, int $idPlantel, array $data): array
{
    if (!certificacion_puede_supervisar()) {
        return ['ok' => false, 'message' => 'Solo el supervisor académico puede confirmar la fecha'];
    }

    $sol = certificacion_obtener_solicitud($pdo, $idSolicitud, $idPlantel);
    if (!$sol) {
        return ['ok' => false, 'message' => 'Solicitud no encontrada'];
    }

    $fecha = trim((string) ($data['fecha_confirmada'] ?? ''));
    $hora = certificacion_parse_hora($data['hora_confirmada'] ?? null);
    if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return ['ok' => false, 'message' => 'Indique la fecha confirmada del examen'];
    }
    if ($hora === null) {
        return ['ok' => false, 'message' => 'Indique la hora confirmada del examen'];
    }

    $sede = trim((string) ($data['sede_direccion'] ?? ''));
    $contactoSupervisor = trim((string) ($data['contacto_supervisor'] ?? ''));
    $contactoNombre = trim((string) ($data['contacto_nombre'] ?? ''));
    $fam = certificacion_familia_config($sol['familia'] ?? null);
    if (!empty($fam['presencial']) && $sede === '') {
        return ['ok' => false, 'message' => 'Indique la dirección de la sede para examen presencial'];
    }

    $nuevoEstado = !empty($fam['requiere_credenciales_separadas']) ? 'pendiente_credenciales' : 'lista_para_examen';

    $pdo->prepare(
        'UPDATE certificacion_solicitudes SET estado = ?, fecha_confirmada = ?, hora_confirmada = ?,
         fecha_confirmada_en = NOW(), id_supervisor_confirma = ?, sede_direccion = ?, fecha_examen = ?
         WHERE id_solicitud = ?'
    )->execute([
        $nuevoEstado,
        $fecha,
        $hora,
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
        $sede !== '' ? $sede : null,
        $fecha,
        $idSolicitud,
    ]);

    if (empty($fam['requiere_credenciales_separadas']) && ($contactoSupervisor !== '' || $contactoNombre !== '')) {
        $pdo->prepare('UPDATE certificacion_accesos SET vigente = 0 WHERE id_solicitud = ? AND vigente = 1')
            ->execute([$idSolicitud]);
        $pdo->prepare(
            'INSERT INTO certificacion_accesos (id_solicitud, vigente, sede_direccion, contacto_supervisor, contacto_nombre, id_usuario)
             VALUES (?,1,?,?,?,?)'
        )->execute([
            $idSolicitud,
            $sede !== '' ? $sede : null,
            $contactoSupervisor !== '' ? $contactoSupervisor : null,
            $contactoNombre !== '' ? $contactoNombre : null,
            (int) ($_SESSION['user_id'] ?? 0) ?: null,
        ]);
    }

    $msg = $nuevoEstado === 'lista_para_examen'
        ? 'Fecha, hora y sede confirmadas. El alumno ya puede presentar el examen.'
        : 'Fecha y hora confirmadas. Proporcione los datos de acceso al alumno.';

    return ['ok' => true, 'message' => $msg];
}

/** @param array<string, mixed> $data */
function certificacion_guardar_accesos(PDO $pdo, int $idSolicitud, int $idPlantel, array $data): array
{
    if (!certificacion_puede_supervisar()) {
        return ['ok' => false, 'message' => 'Solo el supervisor puede entregar credenciales'];
    }

    $sol = certificacion_obtener_solicitud($pdo, $idSolicitud, $idPlantel);
    if (!$sol) {
        return ['ok' => false, 'message' => 'Solicitud no encontrada'];
    }
    if (empty($sol['fecha_confirmada'])) {
        return ['ok' => false, 'message' => 'Primero confirme la fecha y hora del examen'];
    }

    $pdo->prepare('UPDATE certificacion_accesos SET vigente = 0 WHERE id_solicitud = ? AND vigente = 1')
        ->execute([$idSolicitud]);

    $cols = [
        'usuario', 'password_acceso', 'institution_id', 'id_examen_alumno', 'clave_dia',
        'url_examen', 'url_software', 'url_zoom', 'clave_grupo', 'voucher', 'codigo_curso',
        'sede_direccion', 'contacto_supervisor', 'contacto_nombre', 'notas_entrega',
    ];
    $vals = [];
    foreach ($cols as $c) {
        $vals[$c] = trim((string) ($data[$c] ?? '')) ?: null;
    }
    if ($vals['sede_direccion'] === null && !empty($sol['sede_direccion'])) {
        $vals['sede_direccion'] = $sol['sede_direccion'];
    }

    $pdo->prepare(
        'INSERT INTO certificacion_accesos (
            id_solicitud, vigente, usuario, password_acceso, institution_id, id_examen_alumno, clave_dia,
            url_examen, url_software, url_zoom, clave_grupo, voucher, codigo_curso,
            sede_direccion, contacto_supervisor, contacto_nombre, notas_entrega, id_usuario
        ) VALUES (?,1,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idSolicitud,
        $vals['usuario'],
        $vals['password_acceso'],
        $vals['institution_id'],
        $vals['id_examen_alumno'],
        $vals['clave_dia'],
        $vals['url_examen'],
        $vals['url_software'],
        $vals['url_zoom'],
        $vals['clave_grupo'],
        $vals['voucher'],
        $vals['codigo_curso'],
        $vals['sede_direccion'],
        $vals['contacto_supervisor'],
        $vals['contacto_nombre'],
        $vals['notas_entrega'],
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
    ]);

    $pdo->prepare('UPDATE certificacion_solicitudes SET estado = ? WHERE id_solicitud = ?')
        ->execute(['lista_para_examen', $idSolicitud]);

    return ['ok' => true, 'message' => 'Datos de acceso registrados para el alumno'];
}

function certificacion_reagendar(PDO $pdo, int $idSolicitud, int $idPlantel, array $data): array
{
    $sol = certificacion_obtener_solicitud($pdo, $idSolicitud, $idPlantel);
    if (!$sol) {
        return ['ok' => false, 'message' => 'Solicitud no encontrada'];
    }

    $fechaNueva = trim((string) ($data['fecha_nueva'] ?? $data['fecha_solicitada'] ?? ''));
    $horaNueva = certificacion_parse_hora($data['hora_nueva'] ?? $data['hora_solicitada'] ?? null);
    $motivo = trim((string) ($data['motivo'] ?? ''));
    if ($fechaNueva === '' || $horaNueva === null) {
        return ['ok' => false, 'message' => 'Indique la nueva fecha y hora solicitada'];
    }

    $accesoPrev = certificacion_obtener_acceso_vigente($pdo, $idSolicitud);
    if ($accesoPrev) {
        $pdo->prepare('UPDATE certificacion_accesos SET vigente = 0 WHERE id_acceso = ?')
            ->execute([(int) $accesoPrev['id_acceso']]);
    }

    $pdo->prepare(
        'INSERT INTO certificacion_reagendamientos (
            id_solicitud, id_acceso_anterior, fecha_anterior, hora_anterior, fecha_nueva, hora_nueva,
            motivo, credenciales_nuevas, id_usuario
        ) VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([
        $idSolicitud,
        $accesoPrev ? (int) $accesoPrev['id_acceso'] : null,
        $sol['fecha_confirmada'] ?? $sol['fecha_solicitada'],
        $sol['hora_confirmada'] ?? $sol['hora_solicitada'],
        $fechaNueva,
        $horaNueva,
        $motivo !== '' ? $motivo : null,
        1,
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
    ]);

    $reag = (int) ($sol['reagendamientos'] ?? 0) + 1;
    $pdo->prepare(
        'UPDATE certificacion_solicitudes SET estado = ?, reagendamientos = ?, motivo_reagendamiento = ?,
         fecha_solicitada = ?, hora_solicitada = ?, fecha_confirmada = NULL, hora_confirmada = NULL,
         fecha_confirmada_en = NULL, id_supervisor_confirma = NULL, sede_direccion = NULL, fecha_examen = ?
         WHERE id_solicitud = ?'
    )->execute([
        'pendiente_confirmacion',
        $reag,
        $motivo !== '' ? $motivo : 'Reagendamiento',
        $fechaNueva,
        $horaNueva,
        $fechaNueva,
        $idSolicitud,
    ]);

    return [
        'ok' => true,
        'message' => 'Reagendamiento registrado. El supervisor debe confirmar la nueva fecha y enviar nuevos datos de acceso.',
    ];
}

/**
 * Certificaciones con precio acordado y sin pago en caja.
 *
 * @return list<array<string, mixed>>
 */
function certificacion_cobros_pendientes_alumno(PDO $pdo, int $idAlumno, ?int $idPlantel = null): array
{
    certificacion_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        'SELECT s.id_solicitud, s.id_alumno, s.id_producto, s.precio_cobrado, s.comision_asesor, s.comision_gerente,
                s.estado, p.nombre AS certificacion, p.clave AS cert_clave
         FROM certificacion_solicitudes s
         INNER JOIN productos p ON p.id_producto = s.id_producto
         WHERE s.id_plantel = ? AND s.id_alumno = ? AND s.id_pago IS NULL
           AND s.estado NOT IN (\'cancelada\')
           AND COALESCE(s.precio_cobrado, 0) > 0
         ORDER BY s.creado_en DESC'
    );
    $st->execute([$idPlantel, $idAlumno]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vincula pago de caja a solicitud y registra comisión de ventas (una sola vez).
 *
 * @return array{ok:bool,message:string}
 */
function certificacion_aplicar_pago(
    PDO $pdo,
    int $idSolicitud,
    int $idPago,
    int $idPlantel,
    ?float $montoPagado = null
): array {
    certificacion_ensure_schema($pdo);
    $sol = certificacion_obtener_solicitud($pdo, $idSolicitud, $idPlantel);
    if (!$sol) {
        return ['ok' => false, 'message' => 'Solicitud de certificación no encontrada'];
    }
    if (!empty($sol['id_pago'])) {
        return ['ok' => true, 'message' => 'La certificación ya tenía pago registrado'];
    }

    $monto = $montoPagado ?? catalog_money($sol['precio_cobrado'] ?? 0);
    $pdo->prepare(
        'UPDATE certificacion_solicitudes SET id_pago = ?, actualizado_en = NOW() WHERE id_solicitud = ? AND id_plantel = ?'
    )->execute([$idPago, $idSolicitud, $idPlantel]);

    if (function_exists('ventas_registrar_movimiento_certificacion')) {
        ventas_registrar_movimiento_certificacion(
            $pdo,
            $idPlantel,
            $idSolicitud,
            (int) $sol['id_alumno'],
            (int) $sol['id_producto'],
            $monto,
            $idPago
        );
    }

    return ['ok' => true, 'message' => 'Pago de certificación vinculado'];
}

/** @return array<string, string> */
function certificacion_estados_etiquetas(): array
{
    return [
        'pre_registro' => 'Pre-registro',
        'documentos_pendientes' => 'Documentos pendientes',
        'pendiente_confirmacion' => 'Pendiente confirmar fecha/hora',
        'pendiente_credenciales' => 'Pendiente datos de acceso',
        'lista_para_examen' => 'Lista para examen',
        'en_proceso' => 'En proceso',
        'completada' => 'Completada',
        'cancelada' => 'Cancelada',
        'reagendamiento' => 'Reagendamiento',
    ];
}
