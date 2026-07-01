<?php

define('ALUMNO_FOTO_DIR', 'uploads/alumnos/fotos');

/** Edad del alumno: fecha de nacimiento, columna edad o pre-registro vinculado. */
function alumno_obtener_edad(PDO $pdo, int $idAlumno): ?int
{
    if ($idAlumno <= 0) {
        return null;
    }
    alumno_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT fecha_nacimiento, edad, id_preregistro FROM alumnos WHERE id_alumno = ? LIMIT 1'
    );
    $st->execute([$idAlumno]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    if (!empty($row['fecha_nacimiento'])) {
        return (int) floor((time() - strtotime((string) $row['fecha_nacimiento'])) / (365.25 * 86400));
    }
    if (isset($row['edad']) && (int) $row['edad'] > 0) {
        return (int) $row['edad'];
    }
    $idPr = (int) ($row['id_preregistro'] ?? 0);
    if ($idPr > 0) {
        $pr = $pdo->prepare('SELECT fecha_nacimiento, edad FROM preregistros WHERE id_preregistro = ? LIMIT 1');
        $pr->execute([$idPr]);
        $p = $pr->fetch(PDO::FETCH_ASSOC);
        if ($p) {
            if (!empty($p['fecha_nacimiento'])) {
                return (int) floor((time() - strtotime((string) $p['fecha_nacimiento'])) / (365.25 * 86400));
            }
            if (isset($p['edad']) && (int) $p['edad'] > 0) {
                return (int) $p['edad'];
            }
        }
    }

    return null;
}

function alumno_ensure_schema(PDO $pdo): void
{
    plantel_ensure_schema($pdo);
    catalog_ensure_schema($pdo);

    plantel_ensure_column($pdo, 'grupos', 'id_especialidad', 'INT UNSIGNED NULL', 'fecha_inicio');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumnos (
            id_alumno INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo INT UNSIGNED NULL,
            id_plantel INT UNSIGNED NULL,
            nombre VARCHAR(120) NOT NULL,
            apellido VARCHAR(120) NOT NULL,
            matricula VARCHAR(60) NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            fecha_alta DATE NOT NULL DEFAULT (CURRENT_DATE),
            PRIMARY KEY (id_alumno)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $cols = [
        'numero_control' => "VARCHAR(12) NULL AFTER id_alumno",
        'nombres' => "VARCHAR(120) NULL AFTER numero_control",
        'apellido_paterno' => "VARCHAR(80) NULL AFTER nombres",
        'apellido_materno' => "VARCHAR(80) NULL AFTER apellido_paterno",
        'foto' => "VARCHAR(255) NULL AFTER apellido_materno",
        'estado' => "ENUM('activo','baja','graduado') NOT NULL DEFAULT 'activo' AFTER foto",
        'forma_pago' => "ENUM('mensual','semanal') NOT NULL DEFAULT 'mensual' AFTER estado",
        'id_usuario_asesor' => "INT UNSIGNED NULL AFTER forma_pago",
        'id_especialidad' => "INT UNSIGNED NULL AFTER id_usuario_asesor",
        'id_preregistro' => "INT UNSIGNED NULL AFTER id_especialidad",
        'pagos_programados' => "SMALLINT UNSIGNED NULL AFTER id_preregistro",
        'email' => "VARCHAR(160) NULL AFTER pagos_programados",
        'telefono' => "VARCHAR(30) NULL AFTER email",
        'fecha_nacimiento' => 'DATE NULL AFTER telefono',
        'edad' => 'SMALLINT UNSIGNED NULL AFTER fecha_nacimiento',
        'moodle_user_id' => 'INT UNSIGNED NULL AFTER id_usuario',
        'creado_en' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER fecha_alta',
    ];
    foreach ($cols as $col => $def) {
        plantel_ensure_column($pdo, 'alumnos', $col, $def, 'id_alumno');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_grupos (
            id_alumno_grupo INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_grupo INT UNSIGNED NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            fecha_inicio DATE NOT NULL DEFAULT (CURRENT_DATE),
            fecha_baja DATE NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_alumno_grupo),
            UNIQUE KEY uq_alumno_grupo (id_alumno, id_grupo),
            KEY idx_ag_alumno (id_alumno),
            KEY idx_ag_grupo (id_grupo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_pagos (
            id_pago INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_especialidad INT UNSIGNED NULL,
            folio VARCHAR(20) NULL,
            monto DECIMAL(12,2) NOT NULL DEFAULT 0,
            forma_pago VARCHAR(40) NULL,
            concepto TEXT NULL,
            cubrio TEXT NULL,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_pago),
            KEY idx_pago_alumno (id_alumno)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS especialidad_fases (
            id_fase INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_especialidad INT UNSIGNED NOT NULL,
            nombre_fase VARCHAR(80) NOT NULL,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_fase),
            KEY idx_fase_esp (id_especialidad)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_calificaciones_fase (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_fase INT UNSIGNED NOT NULL,
            calificacion DECIMAL(5,2) NULL,
            observaciones VARCHAR(255) NULL,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_alumno_fase (id_alumno, id_fase)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_notas (
            id_nota INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            id_usuario INT UNSIGNED NULL,
            nota TEXT NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_nota)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS alumno_documentos (
            id_documento INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_alumno INT UNSIGNED NOT NULL,
            tipo VARCHAR(60) NOT NULL,
            nombre VARCHAR(160) NOT NULL,
            ruta VARCHAR(255) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_documento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    alumno_migrate_legacy_rows($pdo);
    alumno_seed_fases_ejemplo($pdo);
}

function alumno_migrate_legacy_rows(PDO $pdo): void
{
    $rows = $pdo->query(
        "SELECT id_alumno, nombre, apellido, matricula, id_grupo, id_plantel, activo, numero_control, nombres
         FROM alumnos WHERE nombres IS NULL OR nombres = '' LIMIT 5000"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $id = (int) $r['id_alumno'];
        $nombres = trim($r['nombre'] ?? '');
        $apPat = trim($r['apellido'] ?? '');
        $estado = (int) ($r['activo'] ?? 1) ? 'activo' : 'baja';

        if (empty($r['numero_control'])) {
            $nc = !empty($r['matricula']) ? preg_replace('/\D/', '', $r['matricula']) : '';
            if ($nc === '') {
                $nc = alumno_generar_numero_control($pdo, (int) ($r['id_plantel'] ?? plantel_default_id($pdo)));
            }
            $pdo->prepare('UPDATE alumnos SET numero_control = ? WHERE id_alumno = ?')->execute([$nc, $id]);
        }

        $pdo->prepare(
            'UPDATE alumnos SET nombres = ?, apellido_paterno = ?, estado = ? WHERE id_alumno = ?'
        )->execute([$nombres, $apPat, $estado, $id]);

        if (!empty($r['id_grupo'])) {
            $ins = $pdo->prepare(
                'INSERT IGNORE INTO alumno_grupos (id_alumno, id_grupo, activo) VALUES (?, ?, ?)'
            );
            $ins->execute([$id, (int) $r['id_grupo'], (int) $r['activo']]);
        }
    }
}

/** Sin número de control = prospecto vinculado al pre-registro, aún no inscrito al grupo. */
function alumno_es_prospecto(array $al): bool
{
    return trim((string) ($al['numero_control'] ?? '')) === '';
}

/** Asigna número de control la primera vez que el alumno queda inscrito. */
function alumno_asignar_numero_control_inscripcion(PDO $pdo, int $idAlumno): ?string
{
    $st = $pdo->prepare('SELECT numero_control, id_plantel FROM alumnos WHERE id_alumno = ? LIMIT 1');
    $st->execute([$idAlumno]);
    $al = $st->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        return null;
    }
    $nc = trim((string) ($al['numero_control'] ?? ''));
    if ($nc !== '') {
        return $nc;
    }
    $idPlantel = (int) ($al['id_plantel'] ?? 0) ?: plantel_id_activo();
    $nc = alumno_generar_numero_control($pdo, $idPlantel);
    $pdo->prepare('UPDATE alumnos SET numero_control = ? WHERE id_alumno = ?')->execute([$nc, $idAlumno]);

    return $nc;
}

function alumno_generar_numero_control(PDO $pdo, int $idPlantel): string
{
    $stmt = $pdo->prepare(
        'SELECT MAX(CAST(numero_control AS UNSIGNED)) FROM alumnos
         WHERE id_plantel = ? AND numero_control REGEXP \'^[0-9]+$\''
    );
    $stmt->execute([$idPlantel]);
    $max = (int) $stmt->fetchColumn();
    if ($max < 10000) {
        $max = 10000;
    }
    return (string) ($max + 1);
}

function alumno_seed_fases_ejemplo(PDO $pdo): void
{
    $esp = $pdo->query("SELECT id_especialidad FROM especialidades WHERE clave = 'ING' LIMIT 1")->fetchColumn();
    if (!$esp) {
        return;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM especialidad_fases WHERE id_especialidad = ?');
    $stmt->execute([$esp]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }
    $ins = $pdo->prepare('INSERT INTO especialidad_fases (id_especialidad, nombre_fase, orden) VALUES (?, ?, ?)');
    foreach (['A1 1-4', 'A1 5-8', 'A2 1-4', 'A2 5-8', 'B1 1-4', 'B1 5-8'] as $i => $f) {
        $ins->execute([$esp, $f, $i + 1]);
    }
}

function alumno_puede_ver(): bool
{
    return isset($_SESSION['user_id']);
}

function alumno_nombre_completo(array $a): string
{
    $n = trim($a['nombres'] ?? $a['nombre'] ?? '');
    $p = trim($a['apellido_paterno'] ?? $a['apellido'] ?? '');
    $m = trim($a['apellido_materno'] ?? '');
    return trim($n . ' ' . $p . ' ' . $m);
}

function alumno_pagos_totales(array $a, ?array $esp): int
{
    if (!empty($a['pagos_programados'])) {
        return (int) $a['pagos_programados'];
    }
    if (!$esp) {
        return 0;
    }
    if (($a['forma_pago'] ?? 'mensual') === 'semanal') {
        return (int) ($esp['duracion_semanas'] ?? $esp['duracion_meses'] * 4);
    }
    return (int) ($esp['duracion_meses'] ?? 0);
}

/** @return array<int, array<string, mixed>> */
function alumno_listar(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    $sql = "SELECT a.*,
            CONCAT(u.nombre, ' ', u.apellido) AS asesor_nombre,
            e.nombre AS especialidad_nombre,
            e.duracion_meses, e.duracion_semanas,
            (SELECT GROUP_CONCAT(g.clave ORDER BY g.clave SEPARATOR ', ')
             FROM alumno_grupos ag
             INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
             WHERE ag.id_alumno = a.id_alumno AND ag.activo = 1) AS grupos_txt,
            (SELECT COUNT(*) FROM alumno_grupos ag2 WHERE ag2.id_alumno = a.id_alumno AND ag2.activo = 1) AS num_grupos,
            (SELECT COUNT(*) FROM alumno_pagos ap WHERE ap.id_alumno = a.id_alumno) AS pagos_hechos,
            CASE
              WHEN a.pagos_programados IS NOT NULL AND a.pagos_programados > 0 THEN a.pagos_programados
              WHEN a.forma_pago = 'semanal' THEN COALESCE(e.duracion_semanas, e.duracion_meses * 4, 0)
              ELSE COALESCE(e.duracion_meses, 0)
            END AS pagos_total
            FROM alumnos a
            LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario_asesor
            LEFT JOIN especialidades e ON e.id_especialidad = a.id_especialidad
            WHERE a.id_plantel = ?";
    $params = [$idPlantel];

    if (!empty($filtros['estado'])) {
        if ($filtros['estado'] === 'todos') {
            // sin filtro de estado
        } else {
            $sql .= ' AND a.estado = ?';
            $params[] = $filtros['estado'];
        }
    } else {
        $sql .= " AND a.estado = 'activo'";
    }
    if (!empty($filtros['id_especialidad'])) {
        $sql .= ' AND a.id_especialidad = ?';
        $params[] = (int) $filtros['id_especialidad'];
    }
    if (!empty($filtros['sin_grupos'])) {
        $sql .= ' AND NOT EXISTS (
            SELECT 1 FROM alumno_grupos ags WHERE ags.id_alumno = a.id_alumno AND ags.activo = 1
        )';
    }
    if (!empty($filtros['forma_pago'])) {
        $sql .= ' AND a.forma_pago = ?';
        $params[] = $filtros['forma_pago'];
    }
    if (!empty($filtros['q'])) {
        $sql .= ' AND (
            a.numero_control LIKE ? OR a.nombres LIKE ? OR a.apellido_paterno LIKE ?
            OR a.apellido_materno LIKE ? OR a.nombre LIKE ? OR a.apellido LIKE ?
            OR a.matricula LIKE ?
        )';
        $like = '%' . $filtros['q'] . '%';
        $params = array_merge($params, array_fill(0, 7, $like));
    }

    $sql .= ' ORDER BY a.id_alumno DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['nombre_completo'] = alumno_nombre_completo($r);
        $r['pagos_total'] = (int) ($r['pagos_total'] ?? 0);
    }
    unset($r);
    return $rows;
}

function alumno_obtener(PDO $pdo, int $idAlumno, int $idPlantel): ?array
{
    $stmt = $pdo->prepare(
        "SELECT a.*, CONCAT(u.nombre, ' ', u.apellido) AS asesor_nombre, e.nombre AS especialidad_nombre,
                e.duracion_meses, e.duracion_semanas
         FROM alumnos a
         LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario_asesor
         LEFT JOIN especialidades e ON e.id_especialidad = a.id_especialidad
         WHERE a.id_alumno = ? AND a.id_plantel = ? LIMIT 1"
    );
    $stmt->execute([$idAlumno, $idPlantel]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['nombre_completo'] = alumno_nombre_completo($row);
    $row['pagos_total'] = alumno_pagos_totales($row, [
        'duracion_meses' => $row['duracion_meses'],
        'duracion_semanas' => $row['duracion_semanas'],
    ]);
    $pg = $pdo->prepare('SELECT COUNT(*) FROM alumno_pagos WHERE id_alumno = ?' . pago_sql_filtro_activos());
    $pg->execute([$idAlumno]);
    $row['pagos_hechos'] = (int) $pg->fetchColumn();
    return $row;
}

/** @return array<int, array<string, mixed>> */
function alumno_grupos_historial(PDO $pdo, int $idAlumno): array
{
    $stmt = $pdo->prepare(
        'SELECT ag.*, g.clave, g.fecha_inicio, e.nombre AS especialidad_nombre
         FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE ag.id_alumno = ?
         ORDER BY ag.fecha_inicio DESC'
    );
    $stmt->execute([$idAlumno]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<int, array<string, mixed>> */
function alumno_pagos_lista(PDO $pdo, int $idAlumno, ?int $idEspecialidad = null): array
{
    $sql = 'SELECT ap.*,
            CASE
                WHEN TRIM(COALESCE(ap.cubrio, \'\')) <> \'\' THEN ap.cubrio
                WHEN COALESCE(ap.tipo, \'\') = \'inscripcion\' THEN \'Inscripción\'
                WHEN COALESCE(ap.tipo, \'\') = \'mensualidad\' THEN \'Colegiatura\'
                WHEN COALESCE(ap.tipo, \'\') = \'abono\' THEN \'Abono\'
                WHEN COALESCE(ap.tipo, \'\') = \'producto\' THEN \'Producto\'
                ELSE ap.concepto
            END AS cubrio_calc,
            e.nombre AS especialidad_nombre,
            CONCAT(u.nombre, " ", u.apellido) AS recibio_nombre
            FROM alumno_pagos ap
            LEFT JOIN especialidades e ON e.id_especialidad = ap.id_especialidad
            LEFT JOIN usuarios u ON u.id_usuario = ap.id_usuario
            WHERE ap.id_alumno = ?' . pago_sql_filtro_activos('ap');
    $params = [$idAlumno];
    if ($idEspecialidad > 0) {
        $sql .= ' AND ap.id_especialidad = ?';
        $params[] = $idEspecialidad;
    }
    $sql .= ' ORDER BY ap.creado_en DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        if (array_key_exists('cubrio_calc', $r)) {
            $r['cubrio'] = $r['cubrio_calc'];
            unset($r['cubrio_calc']);
        }
    }
    unset($r);
    return $rows;
}

function alumno_calificaciones_fase(PDO $pdo, int $idAlumno): array
{
    $stmt = $pdo->prepare(
        'SELECT ef.id_fase, ef.nombre_fase, ef.id_especialidad, e.nombre AS especialidad_nombre,
                ac.calificacion, ac.observaciones
         FROM especialidad_fases ef
         INNER JOIN especialidades e ON e.id_especialidad = ef.id_especialidad
         LEFT JOIN alumno_calificaciones_fase ac ON ac.id_fase = ef.id_fase AND ac.id_alumno = ?
         WHERE ef.activo = 1
           AND ef.id_especialidad IN (
               SELECT id_especialidad FROM alumno_especialidades WHERE id_alumno = ? AND activo = 1
               UNION
               SELECT DISTINCT g.id_especialidad FROM alumno_grupos ag
               INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
               WHERE ag.id_alumno = ? AND g.id_especialidad IS NOT NULL
           )
         ORDER BY e.nombre, ef.orden'
    );
    $stmt->execute([$idAlumno, $idAlumno, $idAlumno]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function alumno_estado_label(string $estado): string
{
    return ['activo' => 'Activo', 'baja' => 'Baja', 'graduado' => 'Graduado'][$estado] ?? $estado;
}

define('ALUMNO_FOTO_MAX_BYTES', 3 * 1024 * 1024);
define('ALUMNO_FOTO_MIME', [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
]);

function alumno_foto_upload_dir_abs(): string
{
    $dir = dirname(__DIR__) . '/' . ALUMNO_FOTO_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function alumno_foto_is_uploaded_path(?string $path): bool
{
    $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
    return strpos($path, ALUMNO_FOTO_DIR . '/') === 0;
}

function alumno_foto_delete_file(?string $relativePath): void
{
    if (!$relativePath || !alumno_foto_is_uploaded_path($relativePath)) {
        return;
    }
    $abs = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
    if (is_file($abs)) {
        @unlink($abs);
    }
}

/** URL pública de la foto del alumno. */
function alumno_foto_public_url(?string $foto): ?string
{
    $foto = trim((string) $foto);
    if ($foto === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $foto)) {
        return $foto;
    }

    $rel = ltrim(str_replace('\\', '/', $foto), '/');
    if (alumno_foto_is_uploaded_path($rel) || strpos($rel, 'uploads/preregistros/fotos/') === 0) {
        return function_exists('hay_asset_url') ? hay_asset_url($rel) : $rel;
    }

    $root = dirname(__DIR__);
    if (is_file($root . '/' . $rel)) {
        return function_exists('hay_asset_url') ? hay_asset_url($rel) : $rel;
    }

    return null;
}

/**
 * @return array{ok: bool, message: string, path?: string}
 */
function alumno_foto_save_upload(int $idAlumno, array $file): array
{
    if ($idAlumno <= 0) {
        return ['ok' => false, 'message' => 'Alumno no válido'];
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'No se recibió ninguna imagen'];
    }
    if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Error al subir el archivo'];
    }
    if (!empty($file['size']) && (int) $file['size'] > ALUMNO_FOTO_MAX_BYTES) {
        return ['ok' => false, 'message' => 'La imagen no debe superar 3 MB'];
    }

    $val = hay_upload_validar($file, ALUMNO_FOTO_MIME, ALUMNO_FOTO_MAX_BYTES, true);
    if (!$val['ok']) {
        return ['ok' => false, 'message' => $val['message'] ?? 'Imagen no válida'];
    }

    $dir = alumno_foto_upload_dir_abs();
    hay_upload_preparar_directorio($dir, 'images');
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['ok' => false, 'message' => 'No se puede escribir en uploads/alumnos/fotos'];
    }

    $ext = $val['ext'];
    $basename = 'alumno_' . $idAlumno;
    $dest = $dir . '/' . $basename . '.' . $ext;
    $relative = ALUMNO_FOTO_DIR . '/' . $basename . '.' . $ext;

    foreach (glob($dir . '/alumno_' . $idAlumno . '.*') ?: [] as $old) {
        if (is_file($old) && realpath($old) !== realpath($dest)) {
            @unlink($old);
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'message' => 'No se pudo guardar la imagen en el servidor'];
    }

    $fin = hay_upload_finalizar_en_disco($dest, (string) $val['mime'], true);
    if (!$fin['ok']) {
        @unlink($dest);

        return ['ok' => false, 'message' => $fin['message'] ?? 'Imagen no válida'];
    }
    if (!empty($fin['filename'])) {
        $relative = ALUMNO_FOTO_DIR . '/' . $fin['filename'];
    }

    return ['ok' => true, 'message' => 'Foto del alumno actualizada', 'path' => $relative];
}

/** Copia la foto del pre-registro al alumno al inscribir. */
function alumno_foto_copiar_desde_preregistro(?string $preregFoto, int $idAlumno): ?string
{
    if ($idAlumno <= 0 || !$preregFoto) {
        return null;
    }

    $root = dirname(__DIR__);
    $srcRel = ltrim(str_replace('\\', '/', $preregFoto), '/');
    $srcAbs = $root . '/' . $srcRel;
    if (!is_file($srcAbs)) {
        return null;
    }

    $ext = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        $ext = 'jpg';
    }
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }

    $dir = alumno_foto_upload_dir_abs();
    $basename = 'alumno_' . $idAlumno . '.' . $ext;
    $destAbs = $dir . '/' . $basename;
    $relative = ALUMNO_FOTO_DIR . '/' . $basename;

    foreach (glob($dir . '/alumno_' . $idAlumno . '.*') ?: [] as $old) {
        @unlink($old);
    }

    if (!@copy($srcAbs, $destAbs)) {
        return null;
    }

    return $relative;
}

function alumno_foto_asignar(PDO $pdo, int $idAlumno, ?string $path): void
{
    if ($idAlumno <= 0) {
        return;
    }
    $pdo->prepare('UPDATE alumnos SET foto = ? WHERE id_alumno = ?')->execute([
        $path ?: null,
        $idAlumno,
    ]);
}
