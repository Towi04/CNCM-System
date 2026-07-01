<?php

/**
 * Protocolo académico de inscripción: edad, ubicación/fase, autorizaciones.
 */

function inscripcion_protocolo_puede_autorizar(): bool
{
    if (!function_exists('rbac_cap')) {
        return false;
    }
    return rbac_cap('inscripcion_autorizar_edad')
        || rbac_cap('inscripcion_autorizar_ubicacion')
        || in_array(rbac_rol_efectivo(), ['coordinador', 'director', 'supervisor'], true);
}

function inscripcion_protocolo_puede_solicitar(): bool
{
    return function_exists('rbac_cap') && rbac_cap('inscripcion_solicitar_autorizacion');
}

/** @return array{ok:bool,requiere_auth:bool,tipo?:string,message?:string} */
function inscripcion_protocolo_validar_edad(PDO $pdo, array $esp, ?int $edad): array
{
    if (empty($esp['nombre']) && !empty($esp['esp_nombre'])) {
        $esp['nombre'] = $esp['esp_nombre'];
    }
    $v = catalog_validar_edad_especialidad($esp, $edad);
    if ($v['ok']) {
        return ['ok' => true, 'requiere_auth' => false];
    }

    return [
        'ok' => false,
        'requiere_auth' => true,
        'tipo' => 'edad',
        'message' => $v['message'] ?? 'Edad fuera de rango',
    ];
}

/** @return array{ok:bool,requiere_auth:bool,tipo?:string,message?:string} */
function inscripcion_protocolo_validar_grupo(PDO $pdo, int $idAlumno, int $idGrupo, ?int $edad = null): array
{
    operativo_cncm_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT g.*, e.edad_min, e.edad_max, e.nombre AS esp_nombre, e.modalidad
         FROM grupos g
         INNER JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_grupo = ? LIMIT 1'
    );
    $st->execute([$idGrupo]);
    $grupo = $st->fetch(PDO::FETCH_ASSOC);
    if (!$grupo) {
        return ['ok' => false, 'requiere_auth' => false, 'message' => 'Grupo no encontrado'];
    }

    if ($edad === null && $idAlumno > 0 && function_exists('alumno_obtener_edad')) {
        $edad = alumno_obtener_edad($pdo, $idAlumno);
    }

    $edadVal = inscripcion_protocolo_validar_edad($pdo, $grupo, $edad);
    if (!$edadVal['ok'] && $edadVal['requiere_auth']) {
        if (inscripcion_protocolo_auth_aprobada($pdo, $idAlumno, $idGrupo, 'edad')) {
            $edadVal = ['ok' => true, 'requiere_auth' => false];
        } else {
            return $edadVal;
        }
    }

    $idFaseGrupo = (int) ($grupo['id_fase_actual'] ?? 0);
    if ($idFaseGrupo <= 0 && function_exists('fase_listar')) {
        $fases = plan_version_fases($pdo, (int) $grupo['id_especialidad']);
        $idFaseGrupo = !empty($fases) ? (int) $fases[0]['id_fase'] : 0;
    }

    $ubicacionPendiente = false;
    if (function_exists('ubicacion_grupos_permitidos_inscripcion')) {
        $permitidos = ubicacion_grupos_permitidos_inscripcion($pdo, $idAlumno, (int) $grupo['id_especialidad']);
        if ($permitidos !== null && !in_array($idGrupo, $permitidos, true)) {
            $ubicacionPendiente = true;
        }
    } elseif ($idFaseGrupo > 0 && function_exists('fase_listar')) {
        $fases = plan_version_fases($pdo, (int) $grupo['id_especialidad']);
        $primera = !empty($fases) ? (int) $fases[0]['id_fase'] : 0;
        if ($primera > 0 && $idFaseGrupo !== $primera) {
            $stAg = $pdo->prepare(
                'SELECT ubicacion_examen FROM alumno_grupos WHERE id_alumno = ? AND activo = 1 LIMIT 1'
            );
            $stAg->execute([$idAlumno]);
            if (!(int) $stAg->fetchColumn()) {
                $ubicacionPendiente = true;
            }
        }
    }

    if ($ubicacionPendiente) {
        if (inscripcion_protocolo_auth_aprobada($pdo, $idAlumno, $idGrupo, 'ubicacion')) {
            return ['ok' => true, 'requiere_auth' => false];
        }

        return [
            'ok' => false,
            'requiere_auth' => true,
            'tipo' => 'ubicacion',
            'message' => 'El grupo no es fase inicial. Se requiere autorización de coordinación, dirección o supervisor.',
        ];
    }

    return ['ok' => true, 'requiere_auth' => false];
}

function inscripcion_protocolo_auth_aprobada(PDO $pdo, int $idAlumno, int $idGrupo, string $tipo): bool
{
    operativo_cncm_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT 1 FROM inscripcion_autorizacion
         WHERE id_alumno = ? AND id_grupo = ? AND estado = 'aprobada'
           AND (tipo = ? OR tipo = 'ambos')
         ORDER BY id_auth DESC LIMIT 1"
    );
    $st->execute([$idAlumno, $idGrupo, $tipo]);

    return (bool) $st->fetchColumn();
}

/** Indica si el usuario en sesión puede autorizar excepciones por edad. */
function inscripcion_usuario_sesion_puede_autorizar_edad(): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');
    if (in_array($rol, ['admin', 'gerente', 'supervisor'], true)) {
        return true;
    }

    return inscripcion_protocolo_puede_autorizar();
}

/** Metadatos del asistente de inscripción (autorización por edad). */
function inscripcion_asistente_meta_autorizacion(): array
{
    return [
        'puede_autorizar_edad' => inscripcion_usuario_sesion_puede_autorizar_edad(),
        'username_sesion' => trim((string) ($_SESSION['username'] ?? '')),
    ];
}

/** ¿La edad del alumno requiere autorización para el grupo elegido? */
function inscripcion_edad_requiere_autorizacion_grupo(PDO $pdo, int $idAlumno, int $idGrupo): array
{
    if ($idAlumno <= 0 || $idGrupo <= 0) {
        return ['requiere' => false, 'mensaje' => ''];
    }
    $proto = inscripcion_protocolo_validar_grupo($pdo, $idAlumno, $idGrupo);
    if (!empty($proto['ok'])) {
        return ['requiere' => false, 'mensaje' => ''];
    }
    if (!empty($proto['requiere_auth']) && ($proto['tipo'] ?? '') === 'edad') {
        return [
            'requiere' => true,
            'mensaje' => (string) ($proto['message'] ?? 'Edad fuera de rango'),
        ];
    }

    return ['requiere' => false, 'mensaje' => ''];
}

/**
 * Registra autorización por edad aprobada en el momento del cobro (asistente de inscripción).
 */
function inscripcion_protocolo_aprobar_edad_inline(
    PDO $pdo,
    int $idAlumno,
    int $idGrupo,
    int $idAutoriza,
    ?int $idPreregistro = null,
    string $motivo = ''
): void {
    operativo_cncm_ensure_schema($pdo);
    if ($idAlumno <= 0 || $idGrupo <= 0 || $idAutoriza <= 0) {
        return;
    }
    if (inscripcion_protocolo_auth_aprobada($pdo, $idAlumno, $idGrupo, 'edad')) {
        return;
    }
    $st = $pdo->prepare('SELECT id_especialidad FROM grupos WHERE id_grupo = ? LIMIT 1');
    $st->execute([$idGrupo]);
    $idEsp = (int) $st->fetchColumn();
    $pdo->prepare(
        'INSERT INTO inscripcion_autorizacion (
            id_plantel, id_alumno, id_preregistro, id_grupo, id_especialidad, tipo, estado, motivo, id_solicita, id_autoriza, autorizado_en
        ) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        plantel_id_activo(),
        $idAlumno,
        $idPreregistro > 0 ? $idPreregistro : null,
        $idGrupo,
        $idEsp > 0 ? $idEsp : null,
        'edad',
        'aprobada',
        $motivo !== '' ? $motivo : 'Autorización en asistente de inscripción',
        (int) ($_SESSION['user_id'] ?? 0) ?: $idAutoriza,
        $idAutoriza,
    ]);
}

/**
 * Verifica credenciales del autorizador y, si procede, registra la autorización por edad.
 *
 * @return array{ok:bool,message?:string,id_usuario?:int}
 */
function inscripcion_verificar_y_aprobar_edad(
    PDO $pdo,
    int $idAlumno,
    int $idGrupo,
    string $usuario,
    string $password,
    ?int $idPreregistro = null,
    string $motivo = ''
): array {
    if (!function_exists('preregistro_verificar_autorizador')) {
        require_once __DIR__ . '/preregistro_helper.php';
    }
    $auth = preregistro_verificar_autorizador($pdo, $usuario, $password);
    if (empty($auth['ok'])) {
        return $auth;
    }
    inscripcion_protocolo_aprobar_edad_inline(
        $pdo,
        $idAlumno,
        $idGrupo,
        (int) ($auth['id_usuario'] ?? 0),
        $idPreregistro,
        $motivo
    );

    return $auth;
}

/** @return array{ok:bool,message:string,id_auth?:int} */
function inscripcion_protocolo_solicitar(PDO $pdo, array $data): array
{
    operativo_cncm_ensure_schema($pdo);
    if (!inscripcion_protocolo_puede_solicitar() && !inscripcion_protocolo_puede_autorizar()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    $tipo = (string) ($data['tipo'] ?? 'edad');
    if (!in_array($tipo, ['edad', 'ubicacion', 'ambos'], true)) {
        $tipo = 'edad';
    }
    $pdo->prepare(
        'INSERT INTO inscripcion_autorizacion (
            id_plantel, id_alumno, id_preregistro, id_grupo, id_especialidad, tipo, estado, motivo, id_solicita
        ) VALUES (?,?,?,?,?,?,\'pendiente\',?,?)'
    )->execute([
        (int) ($data['id_plantel'] ?? plantel_id_activo()),
        (int) ($data['id_alumno'] ?? 0) ?: null,
        (int) ($data['id_preregistro'] ?? 0) ?: null,
        (int) ($data['id_grupo'] ?? 0) ?: null,
        (int) ($data['id_especialidad'] ?? 0) ?: null,
        $tipo,
        trim((string) ($data['motivo'] ?? '')),
        (int) ($_SESSION['user_id'] ?? 0),
    ]);

    return ['ok' => true, 'message' => 'Solicitud registrada', 'id_auth' => (int) $pdo->lastInsertId()];
}

/** @return array{ok:bool,message:string} */
function inscripcion_protocolo_resolver(PDO $pdo, int $idAuth, string $estado, ?string $motivo = null): array
{
    operativo_cncm_ensure_schema($pdo);
    if (!inscripcion_protocolo_puede_autorizar()) {
        return ['ok' => false, 'message' => 'No autorizado para aprobar'];
    }
    if (!in_array($estado, ['aprobada', 'rechazada'], true)) {
        return ['ok' => false, 'message' => 'Estado inválido'];
    }
    $pdo->prepare(
        'UPDATE inscripcion_autorizacion SET estado = ?, id_autoriza = ?, autorizado_en = NOW(), motivo = COALESCE(?, motivo)
         WHERE id_auth = ? AND estado = \'pendiente\''
    )->execute([$estado, (int) ($_SESSION['user_id'] ?? 0), $motivo, $idAuth]);

    return ['ok' => true, 'message' => $estado === 'aprobada' ? 'Autorización aprobada' : 'Solicitud rechazada'];
}

/** @return list<array<string, mixed>> */
function inscripcion_protocolo_pendientes(PDO $pdo, int $idPlantel): array
{
    operativo_cncm_ensure_schema($pdo);
    $st = $pdo->prepare(
        "SELECT ia.*, a.nombres, a.apellido_paterno, a.numero_control, g.clave AS grupo_clave
         FROM inscripcion_autorizacion ia
         LEFT JOIN alumnos a ON a.id_alumno = ia.id_alumno
         LEFT JOIN grupos g ON g.id_grupo = ia.id_grupo
         WHERE ia.id_plantel = ? AND ia.estado = 'pendiente'
         ORDER BY ia.creado_en ASC"
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
