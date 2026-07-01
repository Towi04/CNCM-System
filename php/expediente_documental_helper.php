<?php

/**
 * Expediente documental: requisitos configurables, entregas y validación (SEP, candidatos, personal).
 */

define('EXPEDIENTE_UPLOAD_MAX', 8 * 1024 * 1024);
define('EXPEDIENTE_UPLOAD_DIR', 'uploads/expediente');

function expediente_documental_puede_configurar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('expediente_requisitos_admin')) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['supervisor', 'gerente'], true);
}

function expediente_documental_puede_evaluar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('expediente_evaluar')) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['supervisor', 'director', 'coordinador', 'coordinacion', 'gerente'], true);
}

function expediente_documental_puede_consultar(): bool
{
    if (expediente_documental_puede_evaluar() || expediente_documental_puede_configurar()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('expediente_consultar')) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['admin'], true);
}

function expediente_documental_puede_ver_mi_expediente(): bool
{
    if (!empty($_SESSION['user_id'])) {
        return true;
    }
    if (function_exists('alumno_portal_puede_ver') && alumno_portal_puede_ver()) {
        return true;
    }

    return false;
}

function expediente_documental_categorias(): array
{
    return [
        'general' => 'General (todos)',
        'candidato_profesor' => 'Candidato a profesor',
        'profesor' => 'Profesor contratado',
        'alumno_sep' => 'Alumno — trámite certificado SEP',
        'personal' => 'Personal administrativo',
    ];
}

function expediente_documental_tipos_verificacion(): array
{
    return [
        'documento' => 'Solo documento',
        'certificacion' => 'Certificación (omite examen Moodle si se aprueba)',
        'examen_moodle' => 'Examen Moodle (alternativa sin certificación)',
    ];
}

function expediente_documental_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS expediente_requisito (
            id_requisito INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NULL,
            clave VARCHAR(40) NOT NULL,
            nombre VARCHAR(160) NOT NULL,
            descripcion TEXT NULL,
            categoria ENUM('general','candidato_profesor','profesor','alumno_sep','personal') NOT NULL DEFAULT 'general',
            roles_json JSON NULL COMMENT 'Roles HAY adicionales; vacío = solo por categoría',
            obligatorio TINYINT(1) NOT NULL DEFAULT 1,
            tipo_verificacion ENUM('documento','certificacion','examen_moodle') NOT NULL DEFAULT 'documento',
            moodle_course_id INT UNSIGNED NULL,
            umbral_aprobacion DECIMAL(5,2) NULL DEFAULT 70.00,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 100,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_requisito),
            UNIQUE KEY uq_exp_req_plantel_clave (id_plantel, clave),
            KEY idx_exp_req_cat (categoria, activo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS expediente_entrega (
            id_entrega INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_requisito INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            tipo_entidad ENUM('usuario','alumno','prospecto') NOT NULL,
            id_entidad INT UNSIGNED NOT NULL,
            ruta VARCHAR(255) NULL,
            nombre_original VARCHAR(200) NULL,
            estado ENUM('pendiente','aprobado','rechazado','exento') NOT NULL DEFAULT 'pendiente',
            puntaje DECIMAL(6,2) NULL,
            origen_puntaje ENUM('documento','moodle','manual') NULL,
            comentario_rechazo TEXT NULL,
            moodle_inscrito TINYINT(1) NOT NULL DEFAULT 0,
            id_usuario_subio INT UNSIGNED NULL,
            id_usuario_evaluo INT UNSIGNED NULL,
            evaluado_en DATETIME NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_entrega),
            UNIQUE KEY uq_exp_entrega (id_requisito, tipo_entidad, id_entidad),
            KEY idx_exp_ent_plantel (id_plantel, estado),
            KEY idx_exp_ent_entidad (tipo_entidad, id_entidad)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    expediente_documental_seed_requisitos($pdo);
    expediente_documental_migrate_area_column($pdo);
}

function expediente_documental_migrate_area_column(PDO $pdo): void
{
    if (!function_exists('plantel_ensure_column')) {
        return;
    }
    plantel_ensure_column(
        $pdo,
        'expediente_entrega',
        'id_hay_area',
        'INT UNSIGNED NOT NULL DEFAULT 0 COMMENT \'0=general; >0 certificación por área\'',
        'id_entidad'
    );
    try {
        $pdo->exec('ALTER TABLE expediente_entrega DROP INDEX uq_exp_entrega');
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec(
            'ALTER TABLE expediente_entrega ADD UNIQUE KEY uq_exp_entrega (id_requisito, tipo_entidad, id_entidad, id_hay_area)'
        );
    } catch (Throwable $e) {
    }
}

/** @return list<array{id_area:int,nombre:string,clave:string}> */
function expediente_documental_areas_entidad(PDO $pdo, string $tipoEntidad, int $idEntidad): array
{
    if ($tipoEntidad === 'prospecto' && function_exists('docente_prospecto_areas')) {
        return array_map(static fn ($a) => [
            'id_area' => (int) $a['id_area'],
            'nombre' => (string) ($a['nombre'] ?? ''),
            'clave' => (string) ($a['clave'] ?? ''),
        ], docente_prospecto_areas($pdo, $idEntidad));
    }
    if ($tipoEntidad === 'usuario' && function_exists('hay_eval_areas_usuario')) {
        return array_map(static fn ($a) => [
            'id_area' => (int) $a['id_area'],
            'nombre' => (string) ($a['nombre'] ?? ''),
            'clave' => (string) ($a['clave'] ?? ''),
        ], hay_eval_areas_usuario($pdo, $idEntidad));
    }

    return [];
}

function expediente_documental_seed_requisitos(PDO $pdo): void
{
    $semilla = [
        [
            'clave' => 'CERT_CONOCIMIENTO',
            'nombre' => 'Certificación de conocimientos del área',
            'descripcion' => 'Certificado oficial o constancia que avale el dominio del idioma/materia. Si no cuenta con ella, se aplicará el examen Moodle institucional.',
            'categoria' => 'candidato_profesor',
            'tipo_verificacion' => 'certificacion',
            'orden' => 10,
        ],
        [
            'clave' => 'INE',
            'nombre' => 'Identificación oficial (INE)',
            'descripcion' => 'Copia legible por ambos lados.',
            'categoria' => 'candidato_profesor',
            'tipo_verificacion' => 'documento',
            'orden' => 20,
        ],
        [
            'clave' => 'CURP_DOC',
            'nombre' => 'CURP',
            'descripcion' => 'Documento CURP actualizado.',
            'categoria' => 'candidato_profesor',
            'tipo_verificacion' => 'documento',
            'orden' => 30,
        ],
        [
            'clave' => 'SEP_ACTA_NAC',
            'nombre' => 'Acta de nacimiento',
            'descripcion' => 'Para trámite de certificado SEP.',
            'categoria' => 'alumno_sep',
            'tipo_verificacion' => 'documento',
            'orden' => 10,
        ],
        [
            'clave' => 'SEP_CURP',
            'nombre' => 'CURP (alumno)',
            'descripcion' => 'Documento CURP para certificado SEP.',
            'categoria' => 'alumno_sep',
            'tipo_verificacion' => 'documento',
            'orden' => 20,
        ],
        [
            'clave' => 'SEP_COMPROBANTE',
            'nombre' => 'Comprobante de domicilio',
            'descripcion' => 'No mayor a 3 meses.',
            'categoria' => 'alumno_sep',
            'tipo_verificacion' => 'documento',
            'orden' => 30,
        ],
        [
            'clave' => 'SEP_FOTOS',
            'nombre' => 'Fotografías tamaño infantil',
            'descripcion' => 'Según requisitos SEP vigentes.',
            'categoria' => 'alumno_sep',
            'tipo_verificacion' => 'documento',
            'orden' => 40,
        ],
    ];

    $st = $pdo->prepare(
        'INSERT IGNORE INTO expediente_requisito
         (id_plantel, clave, nombre, descripcion, categoria, obligatorio, tipo_verificacion, orden, activo)
         VALUES (NULL, ?, ?, ?, ?, 1, ?, ?, 1)'
    );
    foreach ($semilla as $s) {
        $st->execute([
            $s['clave'],
            $s['nombre'],
            $s['descripcion'],
            $s['categoria'],
            $s['tipo_verificacion'],
            (int) $s['orden'],
        ]);
    }
}

function expediente_documental_upload_dir(string $subdir = ''): string
{
    $base = dirname(__DIR__) . '/' . trim(EXPEDIENTE_UPLOAD_DIR, '/');
    if ($subdir !== '') {
        $base .= '/' . preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $subdir);
    }
    if (!is_dir($base)) {
        @mkdir($base, 0755, true);
    }

    return $base;
}

/** @return array{ok:bool,message?:string,path?:string} */
function expediente_documental_guardar_archivo(array $file, string $subdir, string $prefix): array
{
    $dir = expediente_documental_upload_dir($subdir);
    $basename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $prefix) ?: 'doc';
    $res = hay_upload_guardar(
        $file,
        $dir,
        $basename . '_' . bin2hex(random_bytes(8)),
        HAY_UPLOAD_MIME_IMAGE_PDF,
        EXPEDIENTE_UPLOAD_MAX,
        true
    );
    if (!$res['ok']) {
        return ['ok' => false, 'message' => $res['message'] ?? 'No se pudo guardar'];
    }

    $rel = EXPEDIENTE_UPLOAD_DIR . '/' . ($subdir !== '' ? $subdir . '/' : '') . ($res['filename'] ?? '');

    return ['ok' => true, 'path' => $rel];
}

/** @return list<array<string,mixed>> */
function expediente_documental_entidades_usuario(PDO $pdo, int $idUsuario): array
{
    $out = [];
    $st = $pdo->prepare(
        'SELECT id_usuario, nombre, apellido, rol FROM usuarios WHERE id_usuario = ? LIMIT 1'
    );
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $out[] = [
            'tipo' => 'usuario',
            'id' => (int) $u['id_usuario'],
            'label' => trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')) . ' (personal)',
            'rol' => (string) ($u['rol'] ?? ''),
        ];
    }

    if (function_exists('docente_prospecto_es_candidato_usuario')) {
        $pros = docente_prospecto_es_candidato_usuario($pdo, $idUsuario);
        if ($pros) {
            $out[] = [
                'tipo' => 'prospecto',
                'id' => (int) $pros['id_prospecto'],
                'label' => docente_prospecto_nombre($pros) . ' (candidato)',
                'rol' => 'candidato_profesor',
            ];
        }
    }

    $idAlumno = (int) ($_SESSION['id_alumno_link'] ?? 0);
    if ($idAlumno <= 0 && function_exists('alumno_portal_id_sesion')) {
        $idAlumno = alumno_portal_id_sesion();
    }
    if ($idAlumno > 0) {
        $stA = $pdo->prepare(
            'SELECT id_alumno, nombres, apellido_paterno, apellido_materno FROM alumnos WHERE id_alumno = ? LIMIT 1'
        );
        $stA->execute([$idAlumno]);
        $al = $stA->fetch(PDO::FETCH_ASSOC);
        if ($al) {
            $nom = trim(($al['nombres'] ?? '') . ' ' . ($al['apellido_paterno'] ?? '') . ' ' . ($al['apellido_materno'] ?? ''));
            $out[] = [
                'tipo' => 'alumno',
                'id' => (int) $al['id_alumno'],
                'label' => $nom . ' (alumno)',
                'rol' => 'alumno',
            ];
        }
    }

    return $out;
}

/** @return list<string> */
function expediente_documental_categorias_entidad(string $tipoEntidad, string $rol = ''): array
{
    $cats = ['general'];
    if ($tipoEntidad === 'prospecto') {
        $cats[] = 'candidato_profesor';
    } elseif ($tipoEntidad === 'alumno') {
        $cats[] = 'alumno_sep';
    } elseif ($tipoEntidad === 'usuario') {
        if ($rol === 'profesor') {
            $cats[] = 'profesor';
            $cats[] = 'candidato_profesor';
        } elseif ($rol !== '' && $rol !== 'alumno') {
            $cats[] = 'personal';
        }
    }

    return array_values(array_unique($cats));
}

function expediente_documental_requisito_aplica(array $req, string $tipoEntidad, string $rol): bool
{
    $cats = expediente_documental_categorias_entidad($tipoEntidad, $rol);
    if (!in_array($req['categoria'] ?? '', $cats, true)) {
        return false;
    }
    $rolesJson = $req['roles_json'] ?? null;
    if ($rolesJson === null || $rolesJson === '' || $rolesJson === '[]') {
        return true;
    }
    if (is_string($rolesJson)) {
        $rolesJson = json_decode($rolesJson, true);
    }
    if (!is_array($rolesJson) || $rolesJson === []) {
        return true;
    }

    return in_array($rol, $rolesJson, true);
}

/** @return list<array<string,mixed>> */
function expediente_documental_requisitos_para_entidad(
    PDO $pdo,
    string $tipoEntidad,
    int $idEntidad,
    string $rol = ''
): array {
    expediente_documental_ensure_schema($pdo);
    $idPlantel = plantel_scope_id($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM expediente_requisito
         WHERE activo = 1 AND (id_plantel IS NULL OR id_plantel = ?)
         ORDER BY orden ASC, nombre ASC'
    );
    $st->execute([$idPlantel]);
    $rows = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (expediente_documental_requisito_aplica($r, $tipoEntidad, $rol)) {
            $rows[] = $r;
        }
    }

    return $rows;
}

/** @return array<string,mixed>|null */
function expediente_documental_entrega(
    PDO $pdo,
    int $idRequisito,
    string $tipoEntidad,
    int $idEntidad,
    int $idHayArea = 0
): ?array {
    $st = $pdo->prepare(
        'SELECT e.*, r.nombre AS requisito_nombre, r.tipo_verificacion, r.moodle_course_id, r.umbral_aprobacion,
                a.nombre AS area_nombre
         FROM expediente_entrega e
         INNER JOIN expediente_requisito r ON r.id_requisito = e.id_requisito
         LEFT JOIN hay_area a ON a.id_area = e.id_hay_area AND e.id_hay_area > 0
         WHERE e.id_requisito = ? AND e.tipo_entidad = ? AND e.id_entidad = ? AND e.id_hay_area = ?
         LIMIT 1'
    );
    $st->execute([$idRequisito, $tipoEntidad, $idEntidad, max(0, $idHayArea)]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @return list<array<string,mixed>> */
function expediente_documental_listar_con_entregas(
    PDO $pdo,
    string $tipoEntidad,
    int $idEntidad,
    string $rol = ''
): array {
    $reqs = expediente_documental_requisitos_para_entidad($pdo, $tipoEntidad, $idEntidad, $rol);
    $areas = expediente_documental_areas_entidad($pdo, $tipoEntidad, $idEntidad);
    $out = [];
    foreach ($reqs as $r) {
        $porArea = in_array($r['tipo_verificacion'] ?? '', ['certificacion', 'examen_moodle'], true) && $areas !== [];
        if ($porArea) {
            foreach ($areas as $ar) {
                $idA = (int) $ar['id_area'];
                $reqCopy = $r;
                $reqCopy['nombre'] = $r['nombre'] . ' — ' . ($ar['nombre'] ?? 'Área');
                $courseArea = function_exists('hay_eval_moodle_examen_area')
                    ? hay_eval_moodle_examen_area($pdo, $idA) : 0;
                if ($courseArea > 0) {
                    $reqCopy['moodle_course_id'] = $courseArea;
                }
                $ent = expediente_documental_entrega($pdo, (int) $r['id_requisito'], $tipoEntidad, $idEntidad, $idA);
                $out[] = [
                    'requisito' => $reqCopy,
                    'entrega' => $ent,
                    'id_hay_area' => $idA,
                    'area_nombre' => $ar['nombre'] ?? '',
                ];
            }
        } else {
            $ent = expediente_documental_entrega($pdo, (int) $r['id_requisito'], $tipoEntidad, $idEntidad, 0);
            $out[] = [
                'requisito' => $r,
                'entrega' => $ent,
                'id_hay_area' => 0,
                'area_nombre' => '',
            ];
        }
    }

    return $out;
}

function expediente_documental_puede_gestionar_entidad(
    PDO $pdo,
    string $tipoEntidad,
    int $idEntidad,
    int $idUsuario
): bool {
    if (expediente_documental_puede_consultar() || expediente_documental_puede_evaluar()) {
        return true;
    }
    foreach (expediente_documental_entidades_usuario($pdo, $idUsuario) as $e) {
        if ($e['tipo'] === $tipoEntidad && (int) $e['id'] === $idEntidad) {
            return true;
        }
    }

    return false;
}

/** @return array{ok:bool,message:string,id_entrega?:int} */
function expediente_documental_subir(
    PDO $pdo,
    int $idRequisito,
    string $tipoEntidad,
    int $idEntidad,
    array $file,
    int $idUsuario,
    int $idHayArea = 0
): array {
    expediente_documental_ensure_schema($pdo);
    if (!expediente_documental_puede_gestionar_entidad($pdo, $tipoEntidad, $idEntidad, $idUsuario)) {
        return ['ok' => false, 'message' => 'Sin permiso para subir en esta entidad'];
    }

    $st = $pdo->prepare('SELECT * FROM expediente_requisito WHERE id_requisito = ? AND activo = 1 LIMIT 1');
    $st->execute([$idRequisito]);
    $req = $st->fetch(PDO::FETCH_ASSOC);
    if (!$req) {
        return ['ok' => false, 'message' => 'Requisito no encontrado'];
    }

    $rol = '';
    foreach (expediente_documental_entidades_usuario($pdo, $idUsuario) as $e) {
        if ($e['tipo'] === $tipoEntidad && (int) $e['id'] === $idEntidad) {
            $rol = (string) ($e['rol'] ?? '');
            break;
        }
    }
    if ($rol === '' && $tipoEntidad === 'usuario') {
        $stU = $pdo->prepare('SELECT rol FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stU->execute([$idEntidad]);
        $rol = (string) ($stU->fetchColumn() ?: '');
    }
    if (!expediente_documental_requisito_aplica($req, $tipoEntidad, $rol)) {
        return ['ok' => false, 'message' => 'Este documento no aplica para usted'];
    }

    $subdir = $tipoEntidad . '_' . $idEntidad;
    $up = expediente_documental_guardar_archivo($file, $subdir, (string) $req['clave']);
    if (!$up['ok']) {
        return $up;
    }

    $prev = expediente_documental_entrega($pdo, $idRequisito, $tipoEntidad, $idEntidad, $idHayArea);
    if ($prev && !empty($prev['ruta'])) {
        $full = dirname(__DIR__) . '/' . ltrim((string) $prev['ruta'], '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }

    $idPlantel = plantel_scope_id($pdo);
    if ($prev) {
        $pdo->prepare(
            'UPDATE expediente_entrega
             SET ruta = ?, nombre_original = ?, estado = \'pendiente\', puntaje = NULL, origen_puntaje = NULL,
                 comentario_rechazo = NULL, id_usuario_subio = ?, id_usuario_evaluo = NULL, evaluado_en = NULL,
                 actualizado_en = NOW()
             WHERE id_entrega = ?'
        )->execute([
            $up['path'],
            $file['name'] ?? null,
            $idUsuario,
            (int) $prev['id_entrega'],
        ]);
        $idEntrega = (int) $prev['id_entrega'];
    } else {
        $pdo->prepare(
            'INSERT INTO expediente_entrega
             (id_requisito, id_plantel, tipo_entidad, id_entidad, id_hay_area, ruta, nombre_original, estado, id_usuario_subio)
             VALUES (?,?,?,?,?,?,?, \'pendiente\',?)'
        )->execute([
            $idRequisito,
            $idPlantel,
            $tipoEntidad,
            $idEntidad,
            max(0, $idHayArea),
            $up['path'],
            $file['name'] ?? null,
            $idUsuario,
        ]);
        $idEntrega = (int) $pdo->lastInsertId();
    }

    return ['ok' => true, 'message' => 'Documento cargado. Pendiente de revisión.', 'id_entrega' => $idEntrega];
}

/** @return array{ok:bool,message:string} */
function expediente_documental_evaluar(
    PDO $pdo,
    int $idEntrega,
    string $estado,
    ?float $puntaje,
    string $comentario,
    int $idEvaluador
): array {
    if (!expediente_documental_puede_evaluar()) {
        return ['ok' => false, 'message' => 'Sin permiso para evaluar documentos'];
    }
    $validos = ['aprobado', 'rechazado', 'exento'];
    if (!in_array($estado, $validos, true)) {
        return ['ok' => false, 'message' => 'Estado no válido'];
    }

    $st = $pdo->prepare(
        'SELECT e.*, r.tipo_verificacion, r.moodle_course_id, r.nombre AS req_nombre
         FROM expediente_entrega e
         INNER JOIN expediente_requisito r ON r.id_requisito = e.id_requisito
         WHERE e.id_entrega = ? AND e.id_plantel = ? LIMIT 1'
    );
    $st->execute([$idEntrega, plantel_scope_id($pdo)]);
    $ent = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ent) {
        return ['ok' => false, 'message' => 'Entrega no encontrada'];
    }

    $origen = null;
    if ($estado === 'aprobado' || $estado === 'exento') {
        if ($puntaje !== null) {
            $origen = 'documento';
        }
    }

    $pdo->prepare(
        'UPDATE expediente_entrega
         SET estado = ?, puntaje = ?, origen_puntaje = ?, comentario_rechazo = ?,
             id_usuario_evaluo = ?, evaluado_en = NOW(), actualizado_en = NOW()
         WHERE id_entrega = ?'
    )->execute([
        $estado,
        $puntaje,
        $origen,
        $estado === 'rechazado' ? ($comentario ?: null) : null,
        $idEvaluador,
        $idEntrega,
    ]);

    $msg = 'Documento ' . $estado;
    if ($estado === 'rechazado') {
        $inscr = expediente_documental_inscribir_examen_moodle($pdo, $idEntrega);
        if (!empty($inscr['message'])) {
            $msg .= '. ' . $inscr['message'];
        }
    }

    if (($ent['tipo_entidad'] ?? '') === 'prospecto' && in_array($estado, ['aprobado', 'exento'], true) && $puntaje !== null) {
        $idPros = (int) $ent['id_entidad'];
        if (function_exists('docente_prospecto_registrar_evento')) {
            docente_prospecto_registrar_evento(
                $pdo,
                $idPros,
                'nota',
                'Certificación aprobada — puntaje ' . $puntaje,
                null,
                $idEvaluador
            );
        }
    }

    return ['ok' => true, 'message' => $msg];
}

/** @return array{ok:bool,message:string,moodle_user_id?:int} */
function expediente_documental_moodle_user_id(PDO $pdo, string $tipoEntidad, int $idEntidad): array
{
    if ($tipoEntidad === 'usuario' || $tipoEntidad === 'prospecto') {
        $idUser = 0;
        if ($tipoEntidad === 'usuario') {
            $idUser = $idEntidad;
        } else {
            $st = $pdo->prepare('SELECT id_usuario_candidato FROM docente_prospecto WHERE id_prospecto = ? LIMIT 1');
            $st->execute([$idEntidad]);
            $idUser = (int) ($st->fetchColumn() ?: 0);
        }
        if ($idUser <= 0) {
            return ['ok' => false, 'message' => 'El candidato no tiene usuario HAY'];
        }
        $stM = $pdo->prepare('SELECT moodle_user_id FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stM->execute([$idUser]);
        $idM = (int) ($stM->fetchColumn() ?: 0);
        if ($idM <= 0 && function_exists('moodle_user_ensure_staff')) {
            $crear = moodle_user_ensure_staff($pdo, $idUser);
            if (empty($crear['ok'])) {
                return ['ok' => false, 'message' => (string) ($crear['message'] ?? 'Error Moodle')];
            }
            $idM = (int) ($crear['id_moodle'] ?? 0);
        }

        return $idM > 0
            ? ['ok' => true, 'message' => 'OK', 'moodle_user_id' => $idM, 'id_usuario' => $idUser]
            : ['ok' => false, 'message' => 'Usuario Moodle no disponible'];
    }

    if ($tipoEntidad === 'alumno' && function_exists('moodle_user_ensure_alumno')) {
        $st = $pdo->prepare('SELECT id_plantel FROM alumnos WHERE id_alumno = ? LIMIT 1');
        $st->execute([$idEntidad]);
        $idPlantel = (int) ($st->fetchColumn() ?: plantel_scope_id($pdo));
        $m = moodle_user_ensure_alumno($pdo, $idEntidad, $idPlantel);
        if (empty($m['ok'])) {
            return ['ok' => false, 'message' => (string) ($m['message'] ?? 'Error Moodle alumno')];
        }
        $stM = $pdo->prepare('SELECT moodle_user_id FROM alumnos WHERE id_alumno = ? LIMIT 1');
        $stM->execute([$idEntidad]);
        $idM = (int) ($stM->fetchColumn() ?: 0);

        return $idM > 0
            ? ['ok' => true, 'message' => 'OK', 'moodle_user_id' => $idM]
            : ['ok' => false, 'message' => 'Alumno sin cuenta Moodle'];
    }

    return ['ok' => false, 'message' => 'Entidad no soportada para Moodle'];
}

/** @return array{ok:bool,message:string} */
function expediente_documental_inscribir_examen_moodle(PDO $pdo, int $idEntrega): array
{
    $st = $pdo->prepare(
        'SELECT e.*, r.moodle_course_id, r.tipo_verificacion, r.nombre AS req_nombre
         FROM expediente_entrega e
         INNER JOIN expediente_requisito r ON r.id_requisito = e.id_requisito
         WHERE e.id_entrega = ? LIMIT 1'
    );
    $st->execute([$idEntrega]);
    $ent = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ent) {
        return ['ok' => false, 'message' => 'Entrega no encontrada'];
    }
    $courseId = (int) ($ent['moodle_course_id'] ?? 0);
    $idHayArea = (int) ($ent['id_hay_area'] ?? 0);
    if ($idHayArea > 0 && function_exists('hay_eval_moodle_examen_area')) {
        $courseArea = hay_eval_moodle_examen_area($pdo, $idHayArea);
        if ($courseArea > 0) {
            $courseId = $courseArea;
        }
    }
    if ($courseId <= 0) {
        return ['ok' => true, 'message' => 'Sin curso Moodle configurado para este requisito/área'];
    }
    if ((int) ($ent['moodle_inscrito'] ?? 0) === 1) {
        return ['ok' => true, 'message' => 'Ya inscrito en examen Moodle'];
    }

    $mUser = expediente_documental_moodle_user_id($pdo, (string) $ent['tipo_entidad'], (int) $ent['id_entidad']);
    if (empty($mUser['ok'])) {
        return $mUser;
    }
    if (!function_exists('moodle_enrol_user_in_course')) {
        return ['ok' => false, 'message' => 'Integración Moodle no disponible'];
    }

    $enrol = moodle_enrol_user_in_course((int) $mUser['moodle_user_id'], $courseId);
    if (!empty($enrol['ok'])) {
        $pdo->prepare('UPDATE expediente_entrega SET moodle_inscrito = 1, actualizado_en = NOW() WHERE id_entrega = ?')
            ->execute([$idEntrega]);
    }

    return [
        'ok' => !empty($enrol['ok']),
        'message' => (string) ($enrol['message'] ?? 'Inscripción Moodle'),
    ];
}

/** @return array{ok:bool,message:string,puntaje?:float} */
function expediente_documental_sync_moodle(PDO $pdo, int $idEntrega): array
{
    $st = $pdo->prepare(
        'SELECT e.*, r.moodle_course_id, r.umbral_aprobacion, r.tipo_verificacion
         FROM expediente_entrega e
         INNER JOIN expediente_requisito r ON r.id_requisito = e.id_requisito
         WHERE e.id_entrega = ? LIMIT 1'
    );
    $st->execute([$idEntrega]);
    $ent = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ent) {
        return ['ok' => false, 'message' => 'Entrega no encontrada'];
    }
    if (in_array($ent['estado'] ?? '', ['aprobado', 'exento'], true) && ($ent['origen_puntaje'] ?? '') === 'documento') {
        return ['ok' => true, 'message' => 'Ya aprobado por certificación', 'puntaje' => (float) ($ent['puntaje'] ?? 0)];
    }

    $courseId = (int) ($ent['moodle_course_id'] ?? 0);
    $idHayArea = (int) ($ent['id_hay_area'] ?? 0);
    if ($idHayArea > 0 && function_exists('hay_eval_moodle_examen_area')) {
        $courseArea = hay_eval_moodle_examen_area($pdo, $idHayArea);
        if ($courseArea > 0) {
            $courseId = $courseArea;
        }
    }
    if ($courseId <= 0) {
        return ['ok' => false, 'message' => 'Configure el curso Moodle en el área HAY o en el requisito'];
    }

    $mUser = expediente_documental_moodle_user_id($pdo, (string) $ent['tipo_entidad'], (int) $ent['id_entidad']);
    if (empty($mUser['ok'])) {
        return $mUser;
    }
    if (!function_exists('moodle_grade_for_user_course')) {
        return ['ok' => false, 'message' => 'Moodle no disponible'];
    }

    $grade = moodle_grade_for_user_course((int) $mUser['moodle_user_id'], $courseId);
    if (empty($grade['ok'])) {
        return ['ok' => false, 'message' => (string) ($grade['message'] ?? 'Sin calificación en Moodle')];
    }

    $nota = (float) ($grade['grade'] ?? 0);
    $umbral = (float) ($ent['umbral_aprobacion'] ?? 70);
    $estado = $nota >= $umbral ? 'aprobado' : 'pendiente';

    $pdo->prepare(
        'UPDATE expediente_entrega
         SET puntaje = ?, origen_puntaje = \'moodle\', estado = ?, actualizado_en = NOW()
         WHERE id_entrega = ?'
    )->execute([$nota, $estado, $idEntrega]);

    if (($ent['tipo_entidad'] ?? '') === 'prospecto' && $estado === 'aprobado') {
        $idPros = (int) $ent['id_entidad'];
        if (function_exists('docente_prospecto_registrar_evento')) {
            docente_prospecto_registrar_evento(
                $pdo,
                $idPros,
                'nota',
                'Examen Moodle aprobado — ' . $nota . ' pts',
                null,
                (int) ($_SESSION['user_id'] ?? 0)
            );
        }
    }

    return [
        'ok' => true,
        'message' => $estado === 'aprobado' ? 'Calificación Moodle registrada' : 'Calificación obtenida, aún bajo umbral',
        'puntaje' => $nota,
        'estado' => $estado,
    ];
}

/** @return array{ok:bool,message:string,id_requisito?:int} */
function expediente_documental_guardar_requisito(PDO $pdo, array $data, int $idRequisito = 0): array
{
    if (!expediente_documental_puede_configurar()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    expediente_documental_ensure_schema($pdo);

    $clave = catalog_normalizar_clave((string) ($data['clave'] ?? ''), 40);
    $nombre = trim((string) ($data['nombre'] ?? ''));
    if ($clave === '' || $nombre === '') {
        return ['ok' => false, 'message' => 'Clave y nombre son obligatorios'];
    }

    $categoria = (string) ($data['categoria'] ?? 'general');
    if (!isset(expediente_documental_categorias()[$categoria])) {
        $categoria = 'general';
    }
    $tipoVer = (string) ($data['tipo_verificacion'] ?? 'documento');
    if (!isset(expediente_documental_tipos_verificacion()[$tipoVer])) {
        $tipoVer = 'documento';
    }

    $roles = $data['roles_json'] ?? [];
    if (is_string($roles)) {
        $roles = array_filter(array_map('trim', explode(',', $roles)));
    }
    $rolesJson = json_encode(array_values((array) $roles), JSON_UNESCAPED_UNICODE);

    $params = [
        trim((string) ($data['id_plantel'] ?? '')) !== '' ? (int) $data['id_plantel'] : null,
        $clave,
        $nombre,
        trim((string) ($data['descripcion'] ?? '')) ?: null,
        $categoria,
        $rolesJson,
        (int) ($data['obligatorio'] ?? 1) ? 1 : 0,
        $tipoVer,
        (int) ($data['moodle_course_id'] ?? 0) ?: null,
        ($data['umbral_aprobacion'] ?? '') !== '' ? (float) $data['umbral_aprobacion'] : 70.0,
        (int) ($data['orden'] ?? 100),
        (int) ($data['activo'] ?? 1) ? 1 : 0,
    ];

    if ($idRequisito > 0) {
        $params[] = $idRequisito;
        $pdo->prepare(
            'UPDATE expediente_requisito
             SET id_plantel=?, clave=?, nombre=?, descripcion=?, categoria=?, roles_json=?, obligatorio=?,
                 tipo_verificacion=?, moodle_course_id=?, umbral_aprobacion=?, orden=?, activo=?
             WHERE id_requisito=?'
        )->execute($params);

        return ['ok' => true, 'message' => 'Requisito actualizado', 'id_requisito' => $idRequisito];
    }

    $pdo->prepare(
        'INSERT INTO expediente_requisito
         (id_plantel, clave, nombre, descripcion, categoria, roles_json, obligatorio,
          tipo_verificacion, moodle_course_id, umbral_aprobacion, orden, activo)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute($params);
    $idNew = (int) $pdo->lastInsertId();

    return ['ok' => true, 'message' => 'Requisito creado', 'id_requisito' => $idNew];
}

/** @return list<array<string,mixed>> */
function expediente_documental_listar_requisitos_admin(PDO $pdo): array
{
    expediente_documental_ensure_schema($pdo);
    $idPlantel = plantel_scope_id($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM expediente_requisito
         WHERE id_plantel IS NULL OR id_plantel = ?
         ORDER BY categoria, orden, nombre'
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function expediente_documental_buscar_entidades(PDO $pdo, string $q, int $limite = 25): array
{
    expediente_documental_ensure_schema($pdo);
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $idPlantel = plantel_scope_id($pdo);
    $like = '%' . $q . '%';
    $out = [];

    $st = $pdo->prepare(
        'SELECT id_alumno, nombres, apellido_paterno, apellido_materno, numero_control
         FROM alumnos WHERE id_plantel = ? AND (
            nombres LIKE ? OR apellido_paterno LIKE ? OR numero_control LIKE ? OR email LIKE ?
         ) ORDER BY nombres LIMIT ' . (int) $limite
    );
    $st->execute([$idPlantel, $like, $like, $like, $like]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $out[] = [
            'tipo' => 'alumno',
            'id' => (int) $a['id_alumno'],
            'label' => trim(($a['nombres'] ?? '') . ' ' . ($a['apellido_paterno'] ?? '')) . ' · NC ' . ($a['numero_control'] ?? ''),
        ];
    }

    $stU = $pdo->prepare(
        'SELECT id_usuario, nombre, apellido, email, rol FROM usuarios
         WHERE id_plantel = ? AND (nombre LIKE ? OR apellido LIKE ? OR email LIKE ?)
         ORDER BY nombre LIMIT ' . (int) $limite
    );
    $stU->execute([$idPlantel, $like, $like, $like]);
    foreach ($stU->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $out[] = [
            'tipo' => 'usuario',
            'id' => (int) $u['id_usuario'],
            'label' => trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')) . ' · ' . ($u['rol'] ?? ''),
        ];
    }

    $stP = $pdo->prepare(
        'SELECT id_prospecto, nombres, apellido_paterno, email FROM docente_prospecto
         WHERE id_plantel = ? AND (nombres LIKE ? OR apellido_paterno LIKE ? OR email LIKE ?)
         ORDER BY nombres LIMIT ' . (int) $limite
    );
    $stP->execute([$idPlantel, $like, $like, $like]);
    foreach ($stP->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $out[] = [
            'tipo' => 'prospecto',
            'id' => (int) $p['id_prospecto'],
            'label' => trim(($p['nombres'] ?? '') . ' ' . ($p['apellido_paterno'] ?? '')) . ' (candidato)',
        ];
    }

    return $out;
}

function expediente_documental_puede_ver_archivo(PDO $pdo, int $idEntrega, int $idUsuario): bool
{
    $st = $pdo->prepare('SELECT tipo_entidad, id_entidad FROM expediente_entrega WHERE id_entrega = ? LIMIT 1');
    $st->execute([$idEntrega]);
    $ent = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ent) {
        return false;
    }

    return expediente_documental_puede_gestionar_entidad(
        $pdo,
        (string) $ent['tipo_entidad'],
        (int) $ent['id_entidad'],
        $idUsuario
    );
}
