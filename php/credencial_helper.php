<?php

/**
 * Credenciales de alumnos con plantilla por plantel y verificación pública.
 */

if (!defined('CREDENCIAL_PLANTILLA_DIR')) {
    define('CREDENCIAL_PLANTILLA_DIR', 'uploads/credenciales/plantillas');
}
if (!defined('CREDENCIAL_PDF_DIR')) {
    define('CREDENCIAL_PDF_DIR', 'uploads/credenciales/emitidas');
}

function credencial_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS credencial_plantilla (
            id_plantilla INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            ancho_mm DECIMAL(6,2) NOT NULL DEFAULT 85.60,
            alto_mm DECIMAL(6,2) NOT NULL DEFAULT 54.00,
            fondo_frente_path VARCHAR(255) NULL,
            fondo_reverso_path VARCHAR(255) NULL,
            campos_frente_json JSON NULL,
            campos_reverso_json JSON NULL,
            actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            actualizado_por INT UNSIGNED NULL,
            PRIMARY KEY (id_plantilla),
            KEY idx_credencial_plantel (id_plantel, activo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_credencial (
            id_credencial INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            id_plantilla INT UNSIGNED NOT NULL,
            numero_control VARCHAR(50) NOT NULL,
            token_verificacion CHAR(32) NOT NULL,
            vigencia_inicio DATE NOT NULL,
            vigencia_fin DATE NOT NULL,
            especialidad_nombre VARCHAR(180) NULL,
            nombre_completo VARCHAR(200) NOT NULL,
            foto_path VARCHAR(255) NULL,
            pdf_path VARCHAR(255) NULL,
            generado_por INT UNSIGNED NULL,
            generado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_credencial),
            UNIQUE KEY uq_credencial_token (token_verificacion),
            KEY idx_credencial_alumno (id_alumno, generado_en),
            KEY idx_credencial_control (numero_control, activo),
            KEY idx_credencial_plantel (id_plantel, activo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    foreach ([CREDENCIAL_PLANTILLA_DIR, CREDENCIAL_PDF_DIR] as $rel) {
        $abs = dirname(__DIR__) . '/' . $rel;
        if (function_exists('hay_upload_preparar_directorio')) {
            hay_upload_preparar_directorio($abs, $rel === CREDENCIAL_PDF_DIR ? 'pdf' : 'images');
        } elseif (!is_dir($abs)) {
            @mkdir($abs, 0755, true);
        }
    }
}

function credencial_puede_diseñar(): bool
{
    return (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total())
        || (function_exists('rbac_cap') && (rbac_cap('admin_planteles') || rbac_cap('menu_credenciales_diseno')))
        || (function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() === 'supervisor');
}

function credencial_puede_generar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('menu_credenciales')) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : (string) ($_SESSION['rol'] ?? '');

    return in_array($rol, ['admin', 'director', 'supervisor'], true);
}

/** @return array<string, array{label:string}> */
function credencial_campos_disponibles(): array
{
    return [
        'numero_control' => ['label' => 'Número de control'],
        'nombre_completo' => ['label' => 'Nombre completo'],
        'especialidad' => ['label' => 'Especialidad'],
        'foto' => ['label' => 'Fotografía'],
        'cct' => ['label' => 'CCT'],
        'rvoe' => ['label' => 'RVOE'],
        'vigencia' => ['label' => 'Vigencia'],
        'plantel_nombre' => ['label' => 'Nombre del plantel'],
        'qr_verificacion' => ['label' => 'QR de verificación'],
    ];
}

/** @return list<array<string,mixed>> */
function credencial_campos_default(string $lado): array
{
    if ($lado === 'reverso') {
        return [
            ['campo' => 'cct', 'x_mm' => 7, 'y_mm' => 8, 'font_size' => 7, 'align' => 'left', 'width_mm' => 50],
            ['campo' => 'rvoe', 'x_mm' => 7, 'y_mm' => 14, 'font_size' => 7, 'align' => 'left', 'width_mm' => 50],
            ['campo' => 'vigencia', 'x_mm' => 7, 'y_mm' => 22, 'font_size' => 8, 'align' => 'left', 'width_mm' => 48],
            ['campo' => 'qr_verificacion', 'x_mm' => 61, 'y_mm' => 28, 'font_size' => 8, 'align' => 'center', 'width_mm' => 18],
        ];
    }

    return [
        ['campo' => 'plantel_nombre', 'x_mm' => 5, 'y_mm' => 5, 'font_size' => 8, 'align' => 'center', 'width_mm' => 75],
        ['campo' => 'foto', 'x_mm' => 5, 'y_mm' => 15, 'font_size' => 8, 'align' => 'left', 'width_mm' => 21],
        ['campo' => 'nombre_completo', 'x_mm' => 29, 'y_mm' => 17, 'font_size' => 8, 'align' => 'left', 'width_mm' => 50],
        ['campo' => 'numero_control', 'x_mm' => 29, 'y_mm' => 29, 'font_size' => 8, 'align' => 'left', 'width_mm' => 50],
        ['campo' => 'especialidad', 'x_mm' => 29, 'y_mm' => 37, 'font_size' => 7, 'align' => 'left', 'width_mm' => 50],
    ];
}

function credencial_vigencia_meses(PDO $pdo, int $idAlumno): int
{
    $colEspecialidad = function_exists('plantel_column_exists')
        && plantel_column_exists($pdo, 'especialidades', 'es_plantilla_personalizado');
    $selectEspecialidad = $colEspecialidad ? ', e.es_plantilla_personalizado' : ', 0 AS es_plantilla_personalizado';
    $st = $pdo->prepare(
        'SELECT g.es_personalizado, g.codigo_area, g.clave, e.clave AS especialidad_clave,
                e.nombre AS especialidad_nombre' . $selectEspecialidad . '
         FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE ag.id_alumno = ? AND ag.activo = 1'
    );
    $st->execute([$idAlumno]);
    $grupos = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($grupos === []) {
        $selectFlag = $colEspecialidad ? ', e.es_plantilla_personalizado' : ', 0 AS es_plantilla_personalizado';
        $stEsp = $pdo->prepare(
            'SELECT e.clave AS especialidad_clave, e.nombre AS especialidad_nombre' . $selectFlag . '
             FROM alumnos a
             LEFT JOIN especialidades e ON e.id_especialidad = a.id_especialidad
             WHERE a.id_alumno = ? LIMIT 1'
        );
        $stEsp->execute([$idAlumno]);
        $esp = $stEsp->fetch(PDO::FETCH_ASSOC) ?: [];
        $claveEsp = strtoupper(trim((string) ($esp['especialidad_clave'] ?? '')));
        $nombreEsp = strtoupper(trim((string) ($esp['especialidad_nombre'] ?? '')));
        $esPersonalizada = (int) ($esp['es_plantilla_personalizado'] ?? 0) === 1
            || str_contains($claveEsp, 'PERSONAL')
            || str_contains($nombreEsp, 'PERSONALIZ');

        return $esPersonalizada ? 2 : 12;
    }
    foreach ($grupos as $grupo) {
        $area = strtoupper(trim((string) ($grupo['codigo_area'] ?? '')));
        $claveGrupo = strtoupper(trim((string) ($grupo['clave'] ?? '')));
        $claveEsp = strtoupper(trim((string) ($grupo['especialidad_clave'] ?? '')));
        $nombreEsp = strtoupper(trim((string) ($grupo['especialidad_nombre'] ?? '')));
        $personalizado = (int) ($grupo['es_personalizado'] ?? 0) === 1
            || (int) ($grupo['es_plantilla_personalizado'] ?? 0) === 1
            || $area === 'PER'
            || str_starts_with($claveGrupo, 'PER-')
            || str_contains($claveEsp, 'PERSONAL')
            || str_contains($nombreEsp, 'PERSONALIZ');
        if (!$personalizado) {
            return 12;
        }
    }

    return 2;
}

/** @return list<array<string,mixed>> */
function credencial_plantillas_listar(PDO $pdo, int $idPlantel, bool $soloActivas = false): array
{
    credencial_ensure_schema($pdo);
    $sql = 'SELECT * FROM credencial_plantilla WHERE id_plantel = ?';
    if ($soloActivas) {
        $sql .= ' AND activo = 1';
    }
    $sql .= ' ORDER BY activo DESC, actualizado_en DESC, nombre';
    $st = $pdo->prepare($sql);
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function credencial_plantilla_obtener(PDO $pdo, int $idPlantilla, ?int $idPlantel = null): ?array
{
    credencial_ensure_schema($pdo);
    $sql = 'SELECT * FROM credencial_plantilla WHERE id_plantilla = ?';
    $params = [$idPlantilla];
    if ($idPlantel !== null && $idPlantel > 0) {
        $sql .= ' AND id_plantel = ?';
        $params[] = $idPlantel;
    }
    $st = $pdo->prepare($sql . ' LIMIT 1');
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    foreach (['campos_frente_json', 'campos_reverso_json'] as $key) {
        if (is_string($row[$key] ?? null)) {
            $row[$key] = json_decode($row[$key], true) ?: [];
        }
    }

    return $row;
}

/** @param mixed $campos @return list<array<string,mixed>> */
function credencial_normalizar_campos($campos): array
{
    if (is_string($campos)) {
        $campos = json_decode($campos, true) ?: [];
    }
    if (!is_array($campos)) {
        return [];
    }
    $permitidos = array_keys(credencial_campos_disponibles());
    $out = [];
    foreach ($campos as $campo) {
        if (!is_array($campo) || !in_array((string) ($campo['campo'] ?? ''), $permitidos, true)) {
            continue;
        }
        $out[] = [
            'campo' => (string) $campo['campo'],
            'x_mm' => max(0, min(200, (float) ($campo['x_mm'] ?? 0))),
            'y_mm' => max(0, min(200, (float) ($campo['y_mm'] ?? 0))),
            'font_size' => max(4, min(40, (float) ($campo['font_size'] ?? 8))),
            'align' => in_array(($campo['align'] ?? ''), ['left', 'center', 'right'], true) ? $campo['align'] : 'left',
            'width_mm' => max(0, min(200, (float) ($campo['width_mm'] ?? 0))),
        ];
    }

    return $out;
}

/** @param array<string,mixed> $data */
function credencial_plantilla_guardar(PDO $pdo, array $data, int $idUsuario): array
{
    if (!credencial_puede_diseñar()) {
        return ['ok' => false, 'message' => 'Sin permiso para diseñar credenciales'];
    }
    credencial_ensure_schema($pdo);
    $idPlantel = (int) ($data['id_plantel'] ?? 0);
    $id = (int) ($data['id_plantilla'] ?? 0);
    $nombre = trim((string) ($data['nombre'] ?? ''));
    if ($idPlantel <= 0 || $nombre === '') {
        return ['ok' => false, 'message' => 'Plantel y nombre son obligatorios'];
    }
    $ancho = max(40, min(150, (float) ($data['ancho_mm'] ?? 85.6)));
    $alto = max(30, min(120, (float) ($data['alto_mm'] ?? 54)));
    $frente = credencial_normalizar_campos($data['campos_frente_json'] ?? []);
    $reverso = credencial_normalizar_campos($data['campos_reverso_json'] ?? []);
    $params = [
        $idPlantel,
        $nombre,
        !empty($data['activo']) ? 1 : 0,
        $ancho,
        $alto,
        json_encode($frente, JSON_UNESCAPED_UNICODE),
        json_encode($reverso, JSON_UNESCAPED_UNICODE),
        $idUsuario > 0 ? $idUsuario : null,
    ];
    if ($id > 0) {
        $existente = credencial_plantilla_obtener($pdo, $id, $idPlantel);
        if (!$existente) {
            return ['ok' => false, 'message' => 'Plantilla no encontrada en este plantel'];
        }
        $params[] = $id;
        $pdo->prepare(
            'UPDATE credencial_plantilla
             SET id_plantel=?, nombre=?, activo=?, ancho_mm=?, alto_mm=?,
                 campos_frente_json=?, campos_reverso_json=?, actualizado_por=?
             WHERE id_plantilla=?'
        )->execute($params);
    } else {
        $pdo->prepare(
            'INSERT INTO credencial_plantilla
             (id_plantel, nombre, activo, ancho_mm, alto_mm, campos_frente_json, campos_reverso_json, actualizado_por)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute($params);
        $id = (int) $pdo->lastInsertId();
    }

    return ['ok' => true, 'message' => 'Plantilla guardada', 'id_plantilla' => $id];
}

function credencial_subir_fondo(array $file, string $lado): array
{
    if (!in_array($lado, ['frente', 'reverso'], true)) {
        return ['ok' => false, 'message' => 'Lado no válido'];
    }
    if (!function_exists('hay_upload_guardar')) {
        return ['ok' => false, 'message' => 'Carga segura no disponible'];
    }
    $basename = $lado . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5));
    $res = hay_upload_guardar(
        $file,
        dirname(__DIR__) . '/' . CREDENCIAL_PLANTILLA_DIR,
        $basename,
        HAY_UPLOAD_MIME_IMAGE,
        8 * 1024 * 1024,
        true,
        true
    );
    if (!$res['ok']) {
        return $res;
    }

    return ['ok' => true, 'path' => CREDENCIAL_PLANTILLA_DIR . '/' . $res['filename']];
}

function credencial_plantilla_activa(PDO $pdo, int $idPlantel): ?array
{
    $plantillas = credencial_plantillas_listar($pdo, $idPlantel, true);
    if ($plantillas !== []) {
        return credencial_plantilla_obtener($pdo, (int) $plantillas[0]['id_plantilla'], $idPlantel);
    }
    $pdo->prepare(
        'INSERT INTO credencial_plantilla
         (id_plantel, nombre, campos_frente_json, campos_reverso_json)
         VALUES (?,?,?,?)'
    )->execute([
        $idPlantel,
        'Credencial estándar',
        json_encode(credencial_campos_default('frente'), JSON_UNESCAPED_UNICODE),
        json_encode(credencial_campos_default('reverso'), JSON_UNESCAPED_UNICODE),
    ]);

    return credencial_plantilla_obtener($pdo, (int) $pdo->lastInsertId(), $idPlantel);
}

/** @return list<array<string,mixed>> */
function credencial_listar_alumno(PDO $pdo, int $idAlumno, int $limite = 10): array
{
    credencial_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT c.*, p.nombre AS plantilla_nombre
         FROM alumno_credencial c
         LEFT JOIN credencial_plantilla p ON p.id_plantilla = c.id_plantilla
         WHERE c.id_alumno = ?
         ORDER BY c.generado_en DESC
         LIMIT ' . max(1, min(50, $limite))
    );
    $st->execute([$idAlumno]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function credencial_generar(
    PDO $pdo,
    int $idAlumno,
    int $idPlantilla = 0,
    ?int $idUsuario = null
): array {
    if (!credencial_puede_generar()) {
        return ['ok' => false, 'message' => 'Sin permiso para generar credenciales'];
    }
    credencial_ensure_schema($pdo);
    $idPlantel = plantel_scope_id($pdo);
    $alumno = alumno_obtener($pdo, $idAlumno, $idPlantel);
    if (!$alumno) {
        return ['ok' => false, 'message' => 'Alumno no encontrado en el plantel activo'];
    }
    $plantilla = $idPlantilla > 0
        ? credencial_plantilla_obtener($pdo, $idPlantilla, $idPlantel)
        : credencial_plantilla_activa($pdo, $idPlantel);
    if (!$plantilla || (int) ($plantilla['activo'] ?? 0) !== 1) {
        return ['ok' => false, 'message' => 'No hay una plantilla activa para este plantel'];
    }

    $stEsp = $pdo->prepare(
        'SELECT DISTINCT e.nombre
         FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE ag.id_alumno = ? AND ag.activo = 1 AND e.nombre IS NOT NULL
         ORDER BY e.nombre'
    );
    $stEsp->execute([$idAlumno]);
    $especialidades = array_filter(array_map('trim', $stEsp->fetchAll(PDO::FETCH_COLUMN) ?: []));
    $especialidad = implode(' / ', $especialidades)
        ?: trim((string) ($alumno['especialidad_nombre'] ?? ''));
    $inicio = new DateTimeImmutable('today');
    $meses = credencial_vigencia_meses($pdo, $idAlumno);
    $fin = $inicio->modify('+' . $meses . ' months');
    $token = bin2hex(random_bytes(16));
    $idUsuario = $idUsuario ?? (int) ($_SESSION['user_id'] ?? 0);
    $numeroControl = trim((string) ($alumno['numero_control'] ?? $alumno['matricula'] ?? ''));
    if ($numeroControl === '') {
        return ['ok' => false, 'message' => 'El alumno no tiene número de control'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE alumno_credencial SET activo = 0 WHERE id_alumno = ? AND activo = 1')
            ->execute([$idAlumno]);
        $pdo->prepare(
            'INSERT INTO alumno_credencial (
                id_alumno, id_plantel, id_plantilla, numero_control, token_verificacion,
                vigencia_inicio, vigencia_fin, especialidad_nombre, nombre_completo, foto_path,
                generado_por, activo
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)'
        )->execute([
            $idAlumno,
            $idPlantel,
            (int) $plantilla['id_plantilla'],
            $numeroControl,
            $token,
            $inicio->format('Y-m-d'),
            $fin->format('Y-m-d'),
            $especialidad ?: null,
            alumno_nombre_completo($alumno),
            trim((string) ($alumno['foto'] ?? '')) ?: null,
            $idUsuario > 0 ? $idUsuario : null,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => 'No se pudo generar la credencial: ' . $e->getMessage()];
    }

    return [
        'ok' => true,
        'message' => 'Credencial generada',
        'id_credencial' => $id,
        'token' => $token,
        'vigencia_meses' => $meses,
        'vigencia_fin' => $fin->format('Y-m-d'),
    ];
}

function credencial_obtener(PDO $pdo, int $idCredencial): ?array
{
    credencial_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT * FROM alumno_credencial WHERE id_credencial = ? LIMIT 1');
    $st->execute([$idCredencial]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function credencial_url_verificacion(string $token): string
{
    $path = hay_asset_url('credencial_verificar.php?token=' . rawurlencode($token));
    $host = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return $path;
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    return ($https ? 'https://' : 'http://') . $host . $path;
}

function credencial_imagen_data_uri(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        $bin = @file_get_contents($path);
        if ($bin === false) {
            return '';
        }
        $mime = str_ends_with(strtolower(parse_url($path, PHP_URL_PATH) ?: ''), '.jpg') ? 'image/jpeg' : 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }
    $rel = ltrim(str_replace('\\', '/', $path), '/');
    if (str_contains($rel, '..')) {
        return '';
    }
    $abs = dirname(__DIR__) . '/' . $rel;
    if (!is_file($abs)) {
        return '';
    }
    $mime = mime_content_type($abs) ?: 'image/png';

    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($abs));
}

/** @param array<string,mixed> $plantilla @param array<string,mixed> $valores */
function credencial_render_html(array $plantilla, array $valores): string
{
    $w = (float) ($plantilla['ancho_mm'] ?? 85.6);
    $h = (float) ($plantilla['alto_mm'] ?? 54);
    $verifyUrl = (string) ($valores['verify_url'] ?? '');
    $valores['qr_verificacion'] = function_exists('documento_qr_data_uri')
        ? documento_qr_data_uri($verifyUrl)
        : '';
    $valores['foto'] = credencial_imagen_data_uri((string) ($valores['foto'] ?? ''));
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        @page { margin:0; size:' . $w . 'mm ' . $h . 'mm; }
        html, body { margin:0; padding:0; font-family:DejaVu Sans, sans-serif; }
        .card { position:relative; width:' . $w . 'mm; height:' . $h . 'mm; overflow:hidden; page-break-after:always; }
        .card:last-child { page-break-after:auto; }
        .bg { position:absolute; inset:0; width:100%; height:100%; z-index:0; }
        .field { position:absolute; z-index:1; line-height:1.15; overflow:hidden; }
        .photo { object-fit:cover; }
    </style></head><body>';
    foreach (['frente', 'reverso'] as $lado) {
        $campos = $plantilla['campos_' . $lado . '_json'] ?? [];
        if (is_string($campos)) {
            $campos = json_decode($campos, true) ?: [];
        }
        if ($campos === []) {
            $campos = credencial_campos_default($lado);
        }
        $fondo = credencial_imagen_data_uri((string) ($plantilla['fondo_' . $lado . '_path'] ?? ''));
        $html .= '<div class="card card-' . $lado . '">';
        if ($fondo !== '') {
            $html .= '<img class="bg" src="' . htmlspecialchars($fondo, ENT_QUOTES, 'UTF-8') . '" alt="">';
        }
        foreach ($campos as $campoDef) {
            $campo = (string) ($campoDef['campo'] ?? '');
            if ($campo === '') {
                continue;
            }
            $x = (float) ($campoDef['x_mm'] ?? 0);
            $y = (float) ($campoDef['y_mm'] ?? 0);
            $fs = (float) ($campoDef['font_size'] ?? 8);
            $width = (float) ($campoDef['width_mm'] ?? 0);
            $align = in_array(($campoDef['align'] ?? ''), ['left', 'center', 'right'], true)
                ? $campoDef['align']
                : 'left';
            $style = 'left:' . $x . 'mm;top:' . $y . 'mm;font-size:' . $fs . 'pt;text-align:' . $align . ';';
            if ($width > 0) {
                $style .= 'width:' . $width . 'mm;';
            }
            if (in_array($campo, ['foto', 'qr_verificacion'], true)) {
                $src = (string) ($valores[$campo] ?? '');
                if ($src !== '') {
                    $imgW = $width > 0 ? $width : ($campo === 'foto' ? 21 : 18);
                    $imgH = $campo === 'foto' ? min(28, $imgW * 1.25) : $imgW;
                    $html .= '<img class="field ' . ($campo === 'foto' ? 'photo' : 'qr') . '" src="'
                        . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" style="left:' . $x . 'mm;top:' . $y
                        . 'mm;width:' . $imgW . 'mm;height:' . $imgH . 'mm;" alt="">';
                }
                continue;
            }
            $html .= '<div class="field" style="' . $style . '">'
                . htmlspecialchars((string) ($valores[$campo] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</body></html>';

    return $html;
}

/** @return array{ok:bool,message?:string,contenido?:string,filename?:string,pdf_path?:string} */
function credencial_generar_pdf(PDO $pdo, int $idCredencial): array
{
    $credencial = credencial_obtener($pdo, $idCredencial);
    if (!$credencial) {
        return ['ok' => false, 'message' => 'Credencial no encontrada'];
    }
    $plantilla = credencial_plantilla_obtener(
        $pdo,
        (int) $credencial['id_plantilla'],
        (int) $credencial['id_plantel']
    );
    if (!$plantilla) {
        return ['ok' => false, 'message' => 'Plantilla no encontrada'];
    }
    $plantel = plantel_find($pdo, (int) $credencial['id_plantel']) ?: [];
    $valores = [
        'numero_control' => (string) $credencial['numero_control'],
        'nombre_completo' => (string) $credencial['nombre_completo'],
        'especialidad' => (string) ($credencial['especialidad_nombre'] ?? ''),
        'foto' => (string) ($credencial['foto_path'] ?? ''),
        'cct' => (string) ($plantel['cct'] ?? ''),
        'rvoe' => (string) ($plantel['rvoe'] ?? ''),
        'vigencia' => date('d/m/Y', strtotime((string) $credencial['vigencia_inicio']))
            . ' al ' . date('d/m/Y', strtotime((string) $credencial['vigencia_fin'])),
        'plantel_nombre' => (string) ($plantel['nombre'] ?? ''),
        'verify_url' => credencial_url_verificacion((string) $credencial['token_verificacion']),
    ];
    $html = credencial_render_html($plantilla, $valores);
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
    if (!class_exists('Dompdf\\Dompdf') || !class_exists('Dompdf\\Options')) {
        return ['ok' => false, 'message' => 'Dompdf no disponible'];
    }
    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $w = (float) ($plantilla['ancho_mm'] ?? 85.6);
    $h = (float) ($plantilla['alto_mm'] ?? 54);
    $dompdf->setPaper([0.0, 0.0, $w * 2.83465, $h * 2.83465]);
    $dompdf->render();
    $pdf = $dompdf->output();
    $filename = 'credencial_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $credencial['numero_control'])
        . '_' . $idCredencial . '.pdf';
    $rel = CREDENCIAL_PDF_DIR . '/' . $filename;
    $abs = dirname(__DIR__) . '/' . $rel;
    if (@file_put_contents($abs, $pdf) === false) {
        return ['ok' => false, 'message' => 'No se pudo guardar el PDF'];
    }
    $pdo->prepare('UPDATE alumno_credencial SET pdf_path = ? WHERE id_credencial = ?')
        ->execute([$rel, $idCredencial]);

    return ['ok' => true, 'contenido' => $pdf, 'filename' => $filename, 'pdf_path' => $rel];
}

function credencial_verificar(PDO $pdo, string $token = '', string $control = ''): ?array
{
    credencial_ensure_schema($pdo);
    $token = trim($token);
    $control = trim($control);
    if ($token === '' && $control === '') {
        return null;
    }
    if ($token !== '') {
        $st = $pdo->prepare(
            'SELECT c.*, p.nombre AS plantel_nombre, p.cct, p.rvoe
             FROM alumno_credencial c
             LEFT JOIN planteles p ON p.id_plantel = c.id_plantel
             WHERE c.token_verificacion = ? LIMIT 1'
        );
        $st->execute([$token]);
    } else {
        $st = $pdo->prepare(
            'SELECT c.*, p.nombre AS plantel_nombre, p.cct, p.rvoe
             FROM alumno_credencial c
             LEFT JOIN planteles p ON p.id_plantel = c.id_plantel
             WHERE c.numero_control = ?
             ORDER BY c.activo DESC, c.generado_en DESC LIMIT 1'
        );
        $st->execute([$control]);
    }
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['valido'] = (int) ($row['activo'] ?? 0) === 1
        && (string) ($row['vigencia_inicio'] ?? '') <= date('Y-m-d')
        && (string) ($row['vigencia_fin'] ?? '') >= date('Y-m-d');
    if (!(int) ($row['activo'] ?? 0)) {
        $row['motivo'] = 'Credencial reemplazada o inactiva';
    } elseif ((string) ($row['vigencia_inicio'] ?? '') > date('Y-m-d')) {
        $row['motivo'] = 'La vigencia todavía no inicia';
    } elseif ((string) ($row['vigencia_fin'] ?? '') < date('Y-m-d')) {
        $row['motivo'] = 'Credencial expirada el ' . date('d/m/Y', strtotime((string) $row['vigencia_fin']));
    }

    return $row;
}
