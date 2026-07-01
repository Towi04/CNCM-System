<?php

/**
 * Suspensión de usuarios: personal (HAY + Google + Moodle), alumnos por adeudo (portal limitado), baja.
 */

const USUARIO_SUSPENSION_TIPOS_STAFF = ['staff', 'admin', 'inactividad'];
const USUARIO_SUSPENSION_PORTAL_ADEUDO = 'adeudo';

function usuario_suspension_ensure_schema(PDO $pdo): void
{
    if (function_exists('usuario_ensure_schema')) {
        usuario_ensure_schema($pdo);
    }
    plantel_ensure_column($pdo, 'usuarios', 'suspension_tipo', "VARCHAR(24) NULL", 'suspendido');
    plantel_ensure_column($pdo, 'usuarios', 'suspension_motivo', 'VARCHAR(500) NULL', 'suspension_tipo');
    plantel_ensure_column($pdo, 'usuarios', 'suspension_en', 'DATETIME NULL', 'suspension_motivo');
    plantel_ensure_column($pdo, 'usuarios', 'suspension_por', 'INT UNSIGNED NULL', 'suspension_en');
}

function usuario_suspension_tipo(array $usuario): string
{
    return strtolower(trim((string) ($usuario['suspension_tipo'] ?? '')));
}

function usuario_suspension_esta_activa(array $usuario): bool
{
    return (int) ($usuario['suspendido'] ?? 0) === 1;
}

/** Mensaje si el login debe rechazarse por completo; null = puede entrar (quizá modo limitado). */
function usuario_suspension_mensaje_login_bloqueado(array $usuario): ?string
{
    if (!usuario_suspension_esta_activa($usuario)) {
        return null;
    }
    $tipo = usuario_suspension_tipo($usuario);
    $motivo = trim((string) ($usuario['suspension_motivo'] ?? ''));

    if ($tipo === USUARIO_SUSPENSION_PORTAL_ADEUDO) {
        return null;
    }

    if (($usuario['rol'] ?? '') === 'alumno' && $tipo === 'baja') {
        return 'Su cuenta está suspendida por baja temporal o definitiva.'
            . ($motivo !== '' ? ' Motivo: ' . $motivo : '')
            . ' Comuníquese con recepción para reactivarla.';
    }

    if (in_array($tipo, USUARIO_SUSPENSION_TIPOS_STAFF, true) || (($usuario['rol'] ?? '') !== 'alumno' && $tipo === '')) {
        $base = match ($tipo) {
            'staff' => 'Su acceso al sistema fue suspendido (personal ya no activo).',
            'inactividad' => 'Cuenta suspendida por inactividad prolongada.',
            'admin' => 'Su cuenta fue suspendida por administración.',
            default => 'Su cuenta está suspendida.',
        };

        return $base . ($motivo !== '' ? ' ' . $motivo : '') . ' Contacte a recepción o coordinación.';
    }

    if ($tipo === 'baja') {
        return 'Cuenta suspendida. ' . ($motivo !== '' ? $motivo : 'Contacte a recepción.');
    }

    return 'Cuenta suspendida. Contacte a recepción.';
}

/** Modo de portal limitado tras login (solo alumnos). */
function usuario_suspension_modo_portal(array $usuario): ?string
{
    if (!usuario_suspension_esta_activa($usuario)) {
        return null;
    }
    if (($usuario['rol'] ?? '') !== 'alumno') {
        return null;
    }
    if (usuario_suspension_tipo($usuario) === USUARIO_SUSPENSION_PORTAL_ADEUDO) {
        return USUARIO_SUSPENSION_PORTAL_ADEUDO;
    }

    return null;
}

/** Secciones permitidas con portal limitado por adeudo. */
function usuario_suspension_secciones_portal_adeudo(): array
{
    return [
        'alumno_cuenta_suspendida',
        'alumno_mi_perfil',
        'perfil',
        'cambiar_password',
    ];
}

function usuario_suspension_puede_acceder_seccion(string $seccion): bool
{
    $modo = $_SESSION['suspension_portal'] ?? null;
    if ($modo !== USUARIO_SUSPENSION_PORTAL_ADEUDO) {
        return true;
    }

    return in_array($seccion, usuario_suspension_secciones_portal_adeudo(), true);
}

function usuario_suspension_puede_gestionar_staff(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';

    return in_array($rol, ['admin', 'director', 'supervisor', 'gerente'], true);
}

function usuario_suspension_puede_gestionar_alumno(): bool
{
    if (usuario_suspension_puede_gestionar_staff()) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';

    return in_array($rol, ['recepcion', 'caja', 'coordinador', 'coordinacion'], true);
}

function usuario_por_id(PDO $pdo, int $idUsuario): ?array
{
    $st = $pdo->prepare('SELECT * FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$idUsuario]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function usuario_id_desde_alumno(PDO $pdo, int $idAlumno): int
{
    $st = $pdo->prepare('SELECT id_usuario FROM alumnos WHERE id_alumno = ? LIMIT 1');
    $st->execute([$idAlumno]);

    return (int) $st->fetchColumn();
}

/** @return array{ok:bool,message:string,detalle?:array<string,mixed>} */
function usuario_suspension_aplicar(
    PDO $pdo,
    int $idUsuario,
    string $tipo,
    string $motivo,
    ?int $idQuien = null,
    bool $syncExternas = false
): array {
    usuario_suspension_ensure_schema($pdo);
    $u = usuario_por_id($pdo, $idUsuario);
    if (!$u) {
        return ['ok' => false, 'message' => 'Usuario no encontrado'];
    }

    $tipo = strtolower(trim($tipo));
    $motivo = trim($motivo);
    if ($motivo === '') {
        $motivo = match ($tipo) {
            'staff' => 'Personal dado de baja / renuncia',
            USUARIO_SUSPENSION_PORTAL_ADEUDO => 'Adeudo pendiente — suspensión recepción',
            'baja' => 'Baja académica',
            default => 'Suspensión administrativa',
        };
    }

    $pdo->prepare(
        'UPDATE usuarios SET suspendido = 1, suspension_tipo = ?, suspension_motivo = ?,
         suspension_en = NOW(), suspension_por = ? WHERE id_usuario = ?'
    )->execute([$tipo, mb_substr($motivo, 0, 500), $idQuien ?: null, $idUsuario]);

    $detalle = [];
    if ($syncExternas && ($u['rol'] ?? '') !== 'alumno') {
        $detalle = usuario_suspension_sync_externas($pdo, $idUsuario, true);
    }

    return [
        'ok' => true,
        'message' => 'Usuario suspendido',
        'detalle' => $detalle,
    ];
}

/** @return array{ok:bool,message:string,detalle?:array<string,mixed>} */
function usuario_suspension_reactivar(PDO $pdo, int $idUsuario, ?int $idQuien = null, bool $syncExternas = false): array
{
    usuario_suspension_ensure_schema($pdo);
    $u = usuario_por_id($pdo, $idUsuario);
    if (!$u) {
        return ['ok' => false, 'message' => 'Usuario no encontrado'];
    }

    $eraStaff = ($u['rol'] ?? '') !== 'alumno';

    $pdo->prepare(
        'UPDATE usuarios SET suspendido = 0, suspension_tipo = NULL, suspension_motivo = NULL,
         suspension_en = NULL, suspension_por = NULL WHERE id_usuario = ?'
    )->execute([$idUsuario]);

    if (function_exists('login_security_limpiar_bloqueo')) {
        login_security_limpiar_bloqueo($pdo, $idUsuario);
    }

    $detalle = [];
    if ($syncExternas && $eraStaff) {
        $detalle = usuario_suspension_sync_externas($pdo, $idUsuario, false);
    }

    return [
        'ok' => true,
        'message' => 'Usuario reactivado',
        'detalle' => $detalle,
    ];
}

/** @return array{google?:array,moodle?:array} */
function usuario_suspension_sync_externas(PDO $pdo, int $idUsuario, bool $suspendido): array
{
    $out = [];
    $u = usuario_por_id($pdo, $idUsuario);
    if (!$u) {
        return $out;
    }
    $email = strtolower(trim((string) ($u['email'] ?? '')));

    if ($email !== '' && function_exists('google_usuario_set_suspendido')) {
        $out['google'] = google_usuario_set_suspendido($email, $suspendido);
    }
    if (function_exists('moodle_usuario_set_suspendido')) {
        $out['moodle'] = moodle_usuario_set_suspendido($pdo, $idUsuario, $suspendido);
    }

    return $out;
}

/** @return array{ok:bool,message:string} */
function usuario_suspender_staff(PDO $pdo, int $idUsuario, string $motivo, int $idAdmin): array
{
    if (!usuario_suspension_puede_gestionar_staff()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $u = usuario_por_id($pdo, $idUsuario);
    if (!$u || ($u['rol'] ?? '') === 'alumno') {
        return ['ok' => false, 'message' => 'Solo aplica a personal (no alumnos)'];
    }
    if ((int) $idUsuario === $idAdmin) {
        return ['ok' => false, 'message' => 'No puede suspender su propia cuenta'];
    }

    return usuario_suspension_aplicar($pdo, $idUsuario, 'staff', $motivo, $idAdmin, true);
}

/** @return array{ok:bool,message:string} */
function usuario_suspender_alumno_adeudo(PDO $pdo, int $idAlumno, string $motivo, int $idAdmin): array
{
    if (!usuario_suspension_puede_gestionar_alumno()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $idUsuario = usuario_id_desde_alumno($pdo, $idAlumno);
    if ($idUsuario <= 0) {
        return ['ok' => false, 'message' => 'El alumno no tiene usuario en el sistema'];
    }

    return usuario_suspension_aplicar($pdo, $idUsuario, USUARIO_SUSPENSION_PORTAL_ADEUDO, $motivo, $idAdmin, false);
}

/** @return array{ok:bool,message:string} */
function usuario_reactivar_alumno_acceso(PDO $pdo, int $idAlumno, int $idAdmin): array
{
    if (!usuario_suspension_puede_gestionar_alumno()) {
        return ['ok' => false, 'message' => 'Sin permiso'];
    }
    $idUsuario = usuario_id_desde_alumno($pdo, $idAlumno);
    if ($idUsuario <= 0) {
        return ['ok' => false, 'message' => 'Sin usuario vinculado'];
    }

    return usuario_suspension_reactivar($pdo, $idUsuario, $idAdmin, false);
}

/** Limpia suspensión por baja académica al reactivar alumno. */
function usuario_suspension_reactivar_si_baja(PDO $pdo, int $idAlumno): void
{
    $idUsuario = usuario_id_desde_alumno($pdo, $idAlumno);
    if ($idUsuario <= 0) {
        return;
    }
    $u = usuario_por_id($pdo, $idUsuario);
    if ($u && usuario_suspension_esta_activa($u) && usuario_suspension_tipo($u) === 'baja') {
        usuario_suspension_reactivar($pdo, $idUsuario, null, false);
    }
}

/** Sincroniza suspensión de usuario al registrar baja académica. */
function usuario_suspension_por_baja_alumno(PDO $pdo, int $idAlumno, string $motivo): void
{
    $idUsuario = usuario_id_desde_alumno($pdo, $idAlumno);
    if ($idUsuario <= 0) {
        return;
    }
    usuario_suspension_aplicar($pdo, $idUsuario, 'baja', $motivo, null, false);
}

/** Alumno al corriente en colegiaturas (beneficio tutor IA). */
function usuario_alumno_al_corriente(PDO $pdo, int $idAlumno): bool
{
    if ($idAlumno <= 0 || !function_exists('pago_estado_cuenta')) {
        return true;
    }
    try {
        $ec = pago_estado_cuenta($pdo, $idAlumno);
        $adeudo = (float) ($ec['resumen']['adeudo_colegiatura'] ?? 0);

        return $adeudo < 0.01;
    } catch (Throwable $e) {
        error_log('usuario_alumno_al_corriente: ' . $e->getMessage());

        return false;
    }
}

function usuario_alumno_tiene_grupo_activo(PDO $pdo, int $idAlumno): bool
{
    if ($idAlumno <= 0) {
        return false;
    }
    $st = $pdo->prepare(
        'SELECT 1 FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.estado = \'activo\'
         WHERE ag.id_alumno = ? AND ag.activo = 1 LIMIT 1'
    );
    $st->execute([$idAlumno]);

    return (bool) $st->fetchColumn();
}

/** Alumno egresado / fin de curso sin grupo activo — conserva perfil, sin tutor. */
function usuario_alumno_es_egresado_sin_grupo(PDO $pdo, int $idAlumno): bool
{
    if ($idAlumno <= 0) {
        return false;
    }
    $st = $pdo->prepare('SELECT estado FROM alumnos WHERE id_alumno = ? LIMIT 1');
    $st->execute([$idAlumno]);
    $est = (string) ($st->fetchColumn() ?: '');

    return in_array($est, ['graduado', 'egresado'], true) || !usuario_alumno_tiene_grupo_activo($pdo, $idAlumno);
}

/** ¿Puede usar el Tutor IA? */
function usuario_alumno_puede_tutor(PDO $pdo, int $idAlumno): bool
{
    if ($idAlumno <= 0) {
        return false;
    }
    if (!usuario_alumno_al_corriente($pdo, $idAlumno)) {
        return false;
    }
    if (!usuario_alumno_tiene_grupo_activo($pdo, $idAlumno)) {
        return false;
    }
    $idUsuario = usuario_id_desde_alumno($pdo, $idAlumno);
    if ($idUsuario > 0) {
        $u = usuario_por_id($pdo, $idUsuario);
        if ($u && usuario_suspension_modo_portal($u) === USUARIO_SUSPENSION_PORTAL_ADEUDO) {
            return false;
        }
    }

    return true;
}

function usuario_suspension_etiqueta(array $usuario): string
{
    if (!usuario_suspension_esta_activa($usuario)) {
        return '';
    }

    return match (usuario_suspension_tipo($usuario)) {
        'staff' => 'Personal suspendido',
        USUARIO_SUSPENSION_PORTAL_ADEUDO => 'Suspendido por adeudo',
        'baja' => 'Suspendido (baja)',
        'inactividad' => 'Inactividad',
        'admin' => 'Suspendido',
        default => 'Suspendido',
    };
}

function usuario_suspension_aplicar_sesion(array $usuario): void
{
    $modo = usuario_suspension_modo_portal($usuario);
    if ($modo !== null) {
        $_SESSION['suspension_portal'] = $modo;
        $_SESSION['suspension_motivo'] = trim((string) ($usuario['suspension_motivo'] ?? ''));
    } else {
        unset($_SESSION['suspension_portal'], $_SESSION['suspension_motivo']);
    }
}
