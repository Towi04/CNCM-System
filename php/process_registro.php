<?php
define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida. Vuelva a iniciar sesión.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!rbac_puede_registrar_usuarios()) {
    echo json_encode(['status' => 'error', 'message' => 'Sin permiso para registrar personal.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no válido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    auth_ensure_email_column($pdo);
    if (function_exists('usuario_ensure_schema')) {
        usuario_ensure_schema($pdo);
    }
    plantel_ensure_column($pdo, 'usuarios', 'id_rol', 'INT UNSIGNED NULL', 'rol');
    if (!rbac_db_tablas_listas($pdo)) {
        rbac_db_ensure_schema($pdo);
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $emailRaw = trim($_POST['email'] ?? '');
    $cuentaRaw = trim($_POST['cuenta'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');
    $yaTieneGoogle = !empty($_POST['ya_tiene_google']);
    $rol = trim((string) ($_POST['rol'] ?? ''));
    $idRol = (int) ($_POST['id_rol'] ?? 0);
    $idScope = plantel_scope_id($pdo);
    $idPlantelReg = (int) ($_POST['id_plantel'] ?? $idScope);
    if (!plantel_es_admin()) {
        $idPlantelReg = $idScope;
    }

    $fecha = date('Y-m-d H:i:s');

    if ($nombre === '' || $apellido === '') {
        echo json_encode(['status' => 'error', 'message' => 'Nombre y apellido son obligatorios'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($pass === '') {
        $pass = cuenta_password_inicial();
    }

    if (!$yaTieneGoogle) {
        $apPaterno = cuenta_apellido_paterno_desde_campo($apellido);
        $emailGen = cuenta_email_personal($pdo, $nombre, $apPaterno);
        if ($emailGen === '') {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo generar el correo institucional'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $emailRaw = $emailGen;
        $username = explode('@', $emailGen, 2)[0];
    } elseif ($cuentaRaw !== '' && function_exists('cuenta_resolver_identidad')) {
        $resolved = cuenta_resolver_identidad($cuentaRaw);
        if (empty($resolved['ok'])) {
            echo json_encode(['status' => 'error', 'message' => $resolved['message'] ?? 'Cuenta inválida'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $emailRaw = (string) ($resolved['email'] ?? '');
        $username = (string) ($resolved['username'] ?? '');
    } elseif ($emailRaw === '' && $username !== '' && function_exists('cuenta_resolver_identidad')) {
        $resolved = cuenta_resolver_identidad($username);
        if (!empty($resolved['ok'])) {
            $emailRaw = (string) ($resolved['email'] ?? '');
            $username = (string) ($resolved['username'] ?? '');
        }
    } elseif ($emailRaw !== '' && $username === '' && function_exists('cuenta_resolver_identidad')) {
        $resolved = cuenta_resolver_identidad($emailRaw);
        if (!empty($resolved['ok'])) {
            $emailRaw = (string) ($resolved['email'] ?? '');
            $username = (string) ($resolved['username'] ?? '');
        }
    }

    if ($username === '' || $emailRaw === '') {
        echo json_encode(['status' => 'error', 'message' => 'Usuario y correo institucional son obligatorios'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $email = strtolower($emailRaw);
    if (!auth_is_institutional_email($email)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'El correo debe ser institucional (@' . INSTITUTIONAL_EMAIL_DOMAIN . ')',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $localPart = explode('@', $email, 2)[0];
    if (strtolower($username) !== $localPart) {
        $username = $localPart;
    }

    $stmt = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['status' => 'error', 'message' => 'Ese nombre de usuario ya existe.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['status' => 'error', 'message' => 'Ese correo institucional ya está registrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($idPlantelReg <= 0 || !plantel_find($pdo, $idPlantelReg)) {
        echo json_encode(['status' => 'error', 'message' => 'Plantel inválido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($idRol > 0) {
        $rRow = rbac_rol_por_id($pdo, $idRol);
        $rol = $rRow ? (string) $rRow['clave'] : $rol;
    }
    $rolValido = rbac_validar_rol_usuario($pdo, $rol);
    if (!$rolValido) {
        echo json_encode(['status' => 'error', 'message' => 'Seleccione un rol válido de la lista.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($rolValido === 'supervisor' && rbac_rol_real() !== 'supervisor') {
        echo json_encode(['status' => 'error', 'message' => 'No puede asignar el rol de supervisora'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rolRow = rbac_rol_por_clave($pdo, $rolValido);
    $idRolIns = (int) ($rolRow['id_rol'] ?? 0);
    $depto = rbac_departamento_para_rol($pdo, $rolValido, $idRolIns);

    $password_hashed = password_hash($pass, PASSWORD_BCRYPT);

    $sql = 'INSERT INTO usuarios (nombre, apellido, username, email, password, rol, id_rol, departamento, id_plantel, avatar, fecha_creacion, debe_cambiar_password)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $nombre,
        $apellido,
        $username,
        $email,
        $password_hashed,
        $rolValido,
        $idRolIns > 0 ? $idRolIns : null,
        $depto,
        $idPlantelReg,
        '',
        $fecha,
    ]);

    $idNuevo = (int) $pdo->lastInsertId();
    $ext = ['ok' => true, 'message' => ''];
    if ($idNuevo > 0) {
        try {
            $pdo->prepare('UPDATE usuarios SET codigo_huella = ? WHERE id_usuario = ?')
                ->execute([(string) $idNuevo, $idNuevo]);
        } catch (PDOException $e) {
            // columna ausente en instalaciones antiguas
        }

        $ext = cuenta_externa_provisionar_staff($pdo, $idNuevo, $yaTieneGoogle, $email);
        // Solo Google bloquea el alta (sin correo institucional no hay acceso).
        // Moodle puede quedar pendiente sin borrar el usuario HAY.
        if (empty($ext['ok'])) {
            $pdo->prepare('DELETE FROM usuarios WHERE id_usuario = ?')->execute([$idNuevo]);
            echo json_encode([
                'status' => 'error',
                'message' => $ext['message'] ?? 'No se pudo provisionar Google/Moodle',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $msgExtra = '';
    if ($idNuevo > 0 && !empty($ext['message'])) {
        $msgExtra = ' · ' . $ext['message'];
    }
    $avisoMoodle = '';
    if ($idNuevo > 0 && isset($ext['moodle_ok']) && empty($ext['moodle_ok'])) {
        $avisoMoodle = ' El usuario quedó registrado en HAY; sincronice Moodle después si hace falta.';
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Usuario registrado. Correo: ' . $email . ' · Contraseña inicial: ' . $pass . $msgExtra
            . ' · Número de control (huella): ' . ($idNuevo > 0 ? $idNuevo : '—')
            . $avisoMoodle,
        'moodle_ok' => $ext['moodle_ok'] ?? true,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('process_registro: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo registrar: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
