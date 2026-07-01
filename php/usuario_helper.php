<?php

define('USUARIO_ALUMNO_PASSWORD_DEFAULT', '12345678');
define('USUARIO_MESES_SUSPENSION_AUTO', 6);

function usuario_password_default_moodle(string $numeroControl): string
{
    if (function_exists('cuenta_password_inicial')) {
        return cuenta_password_inicial();
    }

    return defined('CUENTA_PASSWORD_INICIAL') ? CUENTA_PASSWORD_INICIAL : 'Cncm*1234';
}

function usuario_ensure_schema(PDO $pdo): void
{
    if (!function_exists('auth_ensure_email_column')) {
        require_once __DIR__ . '/auth_helpers.php';
    }
    auth_ensure_email_column($pdo);
    try {
        $pdo->exec("UPDATE usuarios SET email = NULL WHERE email = ''");
    } catch (PDOException $e) {
        // ignorar
    }
    plantel_ensure_column($pdo, 'usuarios', 'id_alumno', 'INT UNSIGNED NULL', 'id_plantel');
    plantel_ensure_column($pdo, 'usuarios', 'debe_cambiar_password', 'TINYINT(1) NOT NULL DEFAULT 0', 'id_alumno');
    plantel_ensure_column($pdo, 'usuarios', 'suspendido', 'TINYINT(1) NOT NULL DEFAULT 0', 'debe_cambiar_password');
    plantel_ensure_column($pdo, 'usuarios', 'ultimo_acceso', 'DATETIME NULL', 'suspendido');
    plantel_ensure_column($pdo, 'alumnos', 'id_usuario', 'INT UNSIGNED NULL', 'id_preregistro');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS prospectos_profesor (
            id_prospecto INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_usuario_registro INT UNSIGNED NOT NULL,
            estado ENUM(\'entrevista\',\'evaluacion\',\'contratado\',\'rechazado\',\'contactar_despues\') NOT NULL DEFAULT \'entrevista\',
            nombres VARCHAR(120) NOT NULL,
            apellido_paterno VARCHAR(80) NOT NULL,
            apellido_materno VARCHAR(80) NULL,
            telefono VARCHAR(30) NULL,
            email_personal VARCHAR(160) NULL,
            especialidad VARCHAR(120) NULL,
            observaciones TEXT NULL,
            motivo_no_contratacion TEXT NULL,
            id_usuario_final INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_prospecto),
            KEY idx_pp_plantel (id_plantel),
            KEY idx_pp_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function usuario_moodle_asegurar_alumno(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    if (!function_exists('moodle_user_ensure_alumno') || !function_exists('moodle_enabled') || !moodle_enabled()) {
        return ['ok' => true, 'message' => 'Moodle omitido'];
    }

    $mRes = moodle_user_ensure_alumno($pdo, $idAlumno, $idPlantel);
    if (empty($mRes['ok'])) {
        return [
            'ok' => false,
            'message' => (string) ($mRes['message'] ?? 'No se pudo crear usuario en Moodle'),
            'moodle_raw' => $mRes['moodle_raw'] ?? null,
        ];
    }

    return $mRes;
}

function usuario_crear_cuenta_alumno(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    usuario_ensure_schema($pdo);
    $a = $pdo->prepare(
        'SELECT id_alumno, nombres, apellido_paterno, apellido_materno, numero_control, id_usuario
         FROM alumnos WHERE id_alumno = ? AND id_plantel = ? LIMIT 1'
    );
    $a->execute([$idAlumno, $idPlantel]);
    $al = $a->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }
    if (!empty($al['id_usuario'])) {
        $mRes = usuario_moodle_asegurar_alumno($pdo, $idAlumno, $idPlantel);
        if (empty($mRes['ok'])) {
            return $mRes;
        }

        return [
            'ok' => true,
            'message' => 'El alumno ya tiene usuario',
            'id_usuario' => (int) $al['id_usuario'],
            'moodle' => $mRes['message'] ?? null,
        ];
    }

    $username = trim((string) ($al['numero_control'] ?? ''));
    if ($username === '') {
        return ['ok' => false, 'message' => 'El alumno no tiene número de control'];
    }

    if (function_exists('cuenta_externa_preparar_alumno')) {
        $prep = cuenta_externa_preparar_alumno($pdo, $idAlumno, $idPlantel);
        if (empty($prep['ok'])) {
            return $prep;
        }
    } elseif (function_exists('moodle_user_ensure_alumno')) {
        $mRes = usuario_moodle_asegurar_alumno($pdo, $idAlumno, $idPlantel);
        if (empty($mRes['ok'])) {
            return $mRes;
        }
    }

    $emailInst = function_exists('cuenta_email_alumno')
        ? cuenta_email_alumno($username)
        : (strtolower($username) . '@' . INSTITUTIONAL_EMAIL_DOMAIN);

    $dup = $pdo->prepare('SELECT id_usuario, id_alumno FROM usuarios WHERE username = ? OR LOWER(email) = ? LIMIT 1');
    $dup->execute([$username, $emailInst]);
    $existente = $dup->fetch(PDO::FETCH_ASSOC);
    if ($existente) {
        $idUsuario = (int) $existente['id_usuario'];
        $pdo->prepare('UPDATE alumnos SET id_usuario = ? WHERE id_alumno = ?')->execute([$idUsuario, $idAlumno]);
        if (empty($existente['id_alumno'])) {
            $pdo->prepare('UPDATE usuarios SET id_alumno = ? WHERE id_usuario = ?')->execute([$idAlumno, $idUsuario]);
        }

        $mRes = usuario_moodle_asegurar_alumno($pdo, $idAlumno, $idPlantel);
        if (empty($mRes['ok'])) {
            return $mRes;
        }

        return [
            'ok' => true,
            'vinculado' => true,
            'message' => 'Cuenta existente vinculada al alumno',
            'id_usuario' => $idUsuario,
            'username' => $username,
            'moodle' => $mRes['message'] ?? null,
        ];
    }

    $passPlain = usuario_password_default_moodle($username);
    $passHash = password_hash($passPlain, PASSWORD_BCRYPT);
    $nombre = trim($al['nombres'] ?? '');
    $apellido = trim(($al['apellido_paterno'] ?? '') . ' ' . ($al['apellido_materno'] ?? ''));

    try {
        $pdo->prepare(
            'INSERT INTO usuarios (nombre, apellido, username, email, password, rol, departamento, id_plantel,
             debe_cambiar_password, suspendido, id_alumno, fecha_creacion)
             VALUES (?,?,?,?,?,?,?,?,1,0,?,NOW())'
        )->execute([
            $nombre,
            $apellido,
            $username,
            $emailInst,
            $passHash,
            'alumno',
            '',
            $idPlantel,
            $idAlumno,
        ]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'idx_usuarios_email') || (int) $e->getCode() === 23000) {
            $dup->execute([$username]);
            $existente = $dup->fetch(PDO::FETCH_ASSOC);
            if ($existente) {
                $idUsuario = (int) $existente['id_usuario'];
                $pdo->prepare('UPDATE alumnos SET id_usuario = ? WHERE id_alumno = ?')->execute([$idUsuario, $idAlumno]);

                $mRes = usuario_moodle_asegurar_alumno($pdo, $idAlumno, $idPlantel);
                if (empty($mRes['ok'])) {
                    return $mRes;
                }

                return [
                    'ok' => true,
                    'vinculado' => true,
                    'message' => 'Cuenta existente vinculada al alumno',
                    'id_usuario' => $idUsuario,
                    'username' => $username,
                    'moodle' => $mRes['message'] ?? null,
                ];
            }
        }
        throw $e;
    }

    $idUsuario = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE alumnos SET id_usuario = ? WHERE id_alumno = ?')->execute([$idUsuario, $idAlumno]);

    $mRes = usuario_moodle_asegurar_alumno($pdo, $idAlumno, $idPlantel);

    return [
        'ok' => true,
        'message' => 'Usuario alumno creado. Usuario: ' . $username . ' · Correo: ' . $emailInst . ' · Contraseña inicial: ' . $passPlain
            . (empty($mRes['ok']) ? ' · Moodle pendiente: ' . ($mRes['message'] ?? '') : ''),
        'id_usuario' => $idUsuario,
        'username' => $username,
        'email' => $emailInst,
        'moodle_ok' => !empty($mRes['ok']),
        'moodle' => $mRes['message'] ?? null,
        'moodle_raw' => empty($mRes['ok']) ? ($mRes['moodle_raw'] ?? null) : null,
    ];
}

function usuario_reset_password_alumno(PDO $pdo, int $idAlumno): array
{
    $stmt = $pdo->prepare('SELECT id_usuario, numero_control FROM alumnos WHERE id_alumno = ? LIMIT 1');
    $stmt->execute([$idAlumno]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $idU = (int) ($row['id_usuario'] ?? 0);
    if ($idU <= 0) {
        return ['ok' => false, 'message' => 'El alumno no tiene usuario en el sistema'];
    }
    $passPlain = usuario_password_default_moodle((string) ($row['numero_control'] ?? ''));
    $hash = password_hash($passPlain, PASSWORD_BCRYPT);
    $pdo->prepare(
        'UPDATE usuarios SET password = ?, debe_cambiar_password = 1, suspendido = 0 WHERE id_usuario = ?'
    )->execute([$hash, $idU]);

    if (function_exists('login_security_limpiar_bloqueo')) {
        login_security_limpiar_bloqueo($pdo, $idU);
    }

    if (function_exists('moodle_user_reset_password')) {
        $username = trim((string) ($row['numero_control'] ?? ''));
        $moodleUser = function_exists('moodle_sanitize_username')
            ? moodle_sanitize_username($username)
            : $username;
        if ($moodleUser !== '') {
            moodle_user_reset_password($moodleUser, $passPlain);
        }
    }
    return [
        'ok' => true,
        'message' => 'Contraseña restablecida a ' . $passPlain,
    ];
}

function usuario_vincular_como_alumno(PDO $pdo, int $idUsuario): array
{
    usuario_ensure_schema($pdo);
    alumno_ensure_schema($pdo);

    $idPlantel = plantel_scope_id($pdo);
    $st = $pdo->prepare(
        "SELECT id_usuario, id_plantel, id_alumno, nombre, apellido, username, email, codigo_huella
         FROM usuarios
         WHERE id_usuario = ?
           AND (id_plantel = ? OR id_plantel IS NULL)
           AND rol <> 'alumno'
         LIMIT 1"
    );
    $st->execute([$idUsuario, $idPlantel]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        return ['ok' => false, 'message' => 'Usuario no encontrado en este plantel'];
    }
    if (!empty($u['id_alumno'])) {
        return [
            'ok' => true,
            'message' => 'Este usuario ya tiene perfil de alumno vinculado',
            'id_alumno' => (int) $u['id_alumno'],
        ];
    }

    $numeroControl = 'P' . (int) $idUsuario; // control de personal (no colisiona con alumnos numéricos)
    $dup = $pdo->prepare('SELECT id_alumno FROM alumnos WHERE id_plantel = ? AND numero_control = ? LIMIT 1');
    $dup->execute([$idPlantel, $numeroControl]);
    if ($dup->fetchColumn()) {
        $numeroControl = 'P' . (int) $idUsuario . '-' . date('ymd');
    }

    $nombre = trim((string) ($u['nombre'] ?? ''));
    $apellido = trim((string) ($u['apellido'] ?? ''));
    if ($nombre === '' || $apellido === '') {
        return ['ok' => false, 'message' => 'El usuario debe tener nombre y apellido'];
    }

    $codigoHuella = trim((string) ($u['codigo_huella'] ?? '')) ?: (string) $idUsuario;
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO alumnos (id_plantel, numero_control, nombres, apellido_paterno, apellido_materno, email, estado, codigo_huella)
             VALUES (?,?,?,?,?,?,\'activo\',?)'
        )->execute([
            $idPlantel,
            $numeroControl,
            $nombre,
            $apellido,
            null,
            trim((string) ($u['email'] ?? '')) ?: null,
            $codigoHuella,
        ]);
        $idAlumno = (int) $pdo->lastInsertId();

        $pdo->prepare('UPDATE usuarios SET id_alumno = ? WHERE id_usuario = ?')->execute([$idAlumno, $idUsuario]);
        // También reflejar el código en el repositorio de códigos para búsqueda/validación
        if (function_exists('asistencia_sync_codigo_huella')) {
            asistencia_sync_codigo_huella($pdo, 'alumno', $idAlumno, $codigoHuella, $idPlantel);
        }
        $pdo->commit();

        if (function_exists('moodle_user_ensure_alumno')) {
            moodle_user_ensure_alumno($pdo, $idAlumno, $idPlantel);
        }

        return [
            'ok' => true,
            'message' => 'Perfil alumno creado y vinculado. No. control: ' . $numeroControl,
            'id_alumno' => $idAlumno,
            'numero_control' => $numeroControl,
        ];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function usuario_actualizar_acceso(PDO $pdo, int $idUsuario): void
{
    $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?')
        ->execute([$idUsuario]);
}

function usuario_verificar_suspension_inactividad(PDO $pdo, array $usuario): ?string
{
    if (($usuario['rol'] ?? '') !== 'alumno') {
        return null;
    }
    if ((int) ($usuario['suspendido'] ?? 0)) {
        if (function_exists('usuario_suspension_mensaje_login_bloqueado')) {
            return usuario_suspension_mensaje_login_bloqueado($usuario);
        }

        return 'Tu cuenta está suspendida. Contacta a recepción.';
    }
    $ultimo = $usuario['ultimo_acceso'] ?? null;
    if (!$ultimo) {
        return null;
    }
    $limite = strtotime('-' . USUARIO_MESES_SUSPENSION_AUTO . ' months');
    if (strtotime($ultimo) < $limite) {
        $idU = (int) ($usuario['id_usuario'] ?? 0);
        if ($idU > 0 && function_exists('usuario_suspension_aplicar')) {
            usuario_suspension_aplicar(
                $pdo,
                $idU,
                'inactividad',
                'Suspensión automática por inactividad (' . USUARIO_MESES_SUSPENSION_AUTO . ' meses)',
                null,
                false
            );
        } else {
            $pdo->prepare('UPDATE usuarios SET suspendido = 1 WHERE id_usuario = ?')->execute([$idU]);
        }

        return 'Cuenta suspendida por inactividad (más de ' . USUARIO_MESES_SUSPENSION_AUTO . ' meses). Contacta a recepción.';
    }

    return null;
}

function usuario_puede_gestionar_alumnos(): bool
{
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
    return in_array($rol, ['admin', 'profesor', 'gerente', 'supervisor'], true);
}
