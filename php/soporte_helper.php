<?php

/**
 * Reportes de soporte técnico (errores y sugerencias del sistema).
 */

define('SOPORTE_UPLOAD_MAX', 5 * 1024 * 1024);
define('SOPORTE_UPLOAD_DIR', 'uploads/soporte');
define('SOPORTE_MAX_ADJUNTOS', 5);

function soporte_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS soporte_reporte (
            id_reporte INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NULL,
            tipo ENUM(\'error\',\'sugerencia\') NOT NULL DEFAULT \'error\',
            mensaje TEXT NOT NULL,
            adjuntos_json TEXT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_reporte),
            KEY idx_soporte_usuario (id_usuario),
            KEY idx_soporte_plantel (id_plantel),
            KEY idx_soporte_creado (creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $dir = dirname(__DIR__) . '/' . SOPORTE_UPLOAD_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

function soporte_puede_enviar(): bool
{
    return !empty($_SESSION['user_id']);
}

/** @return list<int> */
function soporte_usuarios_supervisores(PDO $pdo): array
{
    $st = $pdo->query(
        "SELECT u.id_usuario
         FROM usuarios u
         LEFT JOIN roles r ON r.id_rol = u.id_rol
         WHERE u.activo = 1
           AND (u.rol = 'supervisor' OR r.clave = 'supervisor' OR COALESCE(r.acceso_total, 0) = 1)"
    );

    return array_values(array_unique(array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id_usuario'))));
}

/** @return array{ok:bool, message:string, path?:string} */
function soporte_guardar_adjunto(array $file): array
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Error al subir la imagen'];
    }
    if (($file['size'] ?? 0) > SOPORTE_UPLOAD_MAX) {
        return ['ok' => false, 'message' => 'La imagen supera 5 MB'];
    }

    $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
    $mime = $finfo ? $finfo->file($file['tmp_name']) : (string) ($file['type'] ?? '');
    if ($mime === '' || $mime === 'application/octet-stream') {
        $mime = match (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => '',
        };
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'message' => 'Solo se permiten imágenes JPG, PNG, WebP o GIF'];
    }

    $dir = dirname(__DIR__) . '/' . SOPORTE_UPLOAD_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $name = 'sop_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'message' => 'No se pudo guardar la imagen'];
    }

    return ['ok' => true, 'path' => SOPORTE_UPLOAD_DIR . '/' . $name];
}

/** @return array{ok:bool, message:string, id_reporte?:int} */
function soporte_enviar_reporte(PDO $pdo, array $data, array $files): array
{
    soporte_ensure_schema($pdo);

    $idUsuario = (int) ($data['id_usuario'] ?? $_SESSION['user_id'] ?? 0);
    if ($idUsuario <= 0) {
        return ['ok' => false, 'message' => 'Sesión no válida'];
    }

    $tipo = trim((string) ($data['tipo'] ?? 'error'));
    if (!in_array($tipo, ['error', 'sugerencia'], true)) {
        $tipo = 'error';
    }

    $mensaje = trim((string) ($data['mensaje'] ?? ''));
    if ($mensaje === '') {
        return ['ok' => false, 'message' => 'Escriba el mensaje'];
    }
    if (mb_strlen($mensaje) > 4000) {
        return ['ok' => false, 'message' => 'El mensaje es demasiado largo'];
    }

    $idPlantel = (int) ($data['id_plantel'] ?? ($_SESSION['id_plantel'] ?? 0));
    if ($idPlantel <= 0) {
        $idPlantel = null;
    }
    $adjuntos = [];

    if (!empty($files['adjuntos'])) {
        $lista = $files['adjuntos'];
        if (!is_array($lista['name'] ?? null)) {
            $lista = [
                'name' => [$lista['name'] ?? ''],
                'type' => [$lista['type'] ?? ''],
                'tmp_name' => [$lista['tmp_name'] ?? ''],
                'error' => [$lista['error'] ?? UPLOAD_ERR_NO_FILE],
                'size' => [$lista['size'] ?? 0],
            ];
        }
        $n = min(count($lista['name']), SOPORTE_MAX_ADJUNTOS);
        for ($i = 0; $i < $n; $i++) {
            if (($lista['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $up = soporte_guardar_adjunto([
                'name' => $lista['name'][$i] ?? '',
                'type' => $lista['type'][$i] ?? '',
                'tmp_name' => $lista['tmp_name'][$i] ?? '',
                'error' => $lista['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $lista['size'][$i] ?? 0,
            ]);
            if (!$up['ok']) {
                return $up;
            }
            if (!empty($up['path'])) {
                $adjuntos[] = $up['path'];
            }
        }
    }

    $st = $pdo->prepare(
        'INSERT INTO soporte_reporte (id_usuario, id_plantel, tipo, mensaje, adjuntos_json)
         VALUES (?, ?, ?, ?, ?)'
    );
    $st->execute([
        $idUsuario,
        $idPlantel,
        $tipo,
        $mensaje,
        $adjuntos === [] ? null : json_encode($adjuntos, JSON_UNESCAPED_UNICODE),
    ]);
    $idReporte = (int) $pdo->lastInsertId();

    soporte_notificar_supervisores($pdo, $idReporte, $idUsuario, $tipo, $mensaje, $adjuntos);

    return ['ok' => true, 'message' => 'Reporte enviado. Los supervisores han sido notificados.', 'id_reporte' => $idReporte];
}

/** @param list<string> $adjuntos */
function soporte_notificar_supervisores(
    PDO $pdo,
    int $idReporte,
    int $idUsuario,
    string $tipo,
    string $mensaje,
    array $adjuntos
): void {
    if (!function_exists('academico_notificar_usuario')) {
        return;
    }

    $st = $pdo->prepare(
        'SELECT nombre, apellido, rol FROM usuarios WHERE id_usuario = ? LIMIT 1'
    );
    $st->execute([$idUsuario]);
    $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $autor = trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''));
    if ($autor === '') {
        $autor = 'Usuario #' . $idUsuario;
    }
    $rol = trim((string) ($u['rol'] ?? ''));

    $titulo = $tipo === 'sugerencia' ? 'Sugerencia del sistema' : 'Reporte de error';
    $resumen = mb_strimwidth(preg_replace('/\s+/', ' ', $mensaje), 0, 180, '…');
    $det = $autor . ($rol !== '' ? ' (' . $rol . ')' : '') . ': ' . $resumen;
    if ($adjuntos !== []) {
        $det .= ' · ' . count($adjuntos) . ' imagen(es)';
    }

    $params = 'id=' . $idReporte;
    foreach (soporte_usuarios_supervisores($pdo) as $idSup) {
        if ($idSup === $idUsuario) {
            continue;
        }
        academico_notificar_usuario(
            $pdo,
            $idSup,
            'soporte_reporte',
            $titulo,
            $det,
            'soporte_tecnico',
            $params
        );
    }
}

/** @return list<array<string, mixed>> */
function soporte_listar_recientes(PDO $pdo, int $idUsuario, int $limite = 10): array
{
    soporte_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT id_reporte, tipo, mensaje, adjuntos_json, creado_en
         FROM soporte_reporte
         WHERE id_usuario = ?
         ORDER BY creado_en DESC
         LIMIT ?'
    );
    $st->bindValue(1, $idUsuario, PDO::PARAM_INT);
    $st->bindValue(2, $limite, PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll(PDO::FETCH_ASSOC);
}
