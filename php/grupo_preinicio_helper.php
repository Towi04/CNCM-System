<?php

function grupo_preinicio_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_preinicio_contacto (
            id_contacto INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_grupo INT UNSIGNED NOT NULL,
            id_alumno INT UNSIGNED NOT NULL,
            contactado TINYINT(1) NOT NULL DEFAULT 0,
            fecha_contacto DATETIME NULL,
            medio ENUM(\'telefono\',\'whatsapp\',\'presencial\',\'correo\',\'otro\') NULL,
            notas VARCHAR(500) NULL,
            id_usuario_registro INT UNSIGNED NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_contacto),
            UNIQUE KEY uq_gpc_grupo_alumno (id_grupo, id_alumno),
            KEY idx_gpc_plantel (id_plantel),
            KEY idx_gpc_grupo (id_grupo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function grupo_preinicio_puede_ver(): bool
{
    if (function_exists('rbac_usuario_en_roles') && rbac_usuario_en_roles(['asesor', 'gerente', 'supervisor', 'admin', 'director'])) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_asesor_preinicio');
}

function grupo_preinicio_puede_editar(): bool
{
    return grupo_preinicio_puede_ver();
}

/**
 * Grupos próximos a iniciar con alumnos inscritos y estado de contacto.
 *
 * @return list<array<string, mixed>>
 */
function grupo_preinicio_listar_grupos(PDO $pdo, int $idPlantel, int $diasVentana = 21): array
{
    grupo_preinicio_ensure_schema($pdo);
    if (function_exists('asistencia_ensure_schema')) {
        asistencia_ensure_schema($pdo);
    }

    $hoy = date('Y-m-d');
    $hasta = date('Y-m-d', strtotime('+' . max(1, $diasVentana) . ' days'));

    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.fecha_inicio, e.nombre AS especialidad,
                (SELECT COUNT(*) FROM alumno_grupos ag WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1) AS total_alumnos
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_plantel = ? AND g.fecha_inicio >= ?
         ORDER BY g.fecha_inicio ASC, g.clave ASC'
    );
    $st->execute([$idPlantel, $hoy]);
    $gruposRaw = $st->fetchAll(PDO::FETCH_ASSOC);
    $grupos = [];

    foreach ($gruposRaw as $g) {
        $idGrupo = (int) $g['id_grupo'];
        $primerDia = function_exists('reporte_presentados_primer_dia_clase')
            ? reporte_presentados_primer_dia_clase($pdo, $idGrupo)
            : ($g['fecha_inicio'] ?? null);
        if ($primerDia === null || $primerDia > $hasta) {
            continue;
        }
        if ($primerDia < $hoy) {
            continue;
        }
        $g['primer_dia_clase'] = $primerDia;
        $g['contactados'] = grupo_preinicio_contar_contactados($pdo, $idGrupo);
        $g['pendientes'] = max(0, (int) $g['total_alumnos'] - (int) $g['contactados']);
        $grupos[] = $g;
    }

    return $grupos;
}

function grupo_preinicio_contar_contactados(PDO $pdo, int $idGrupo): int
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM grupo_preinicio_contacto WHERE id_grupo = ? AND contactado = 1'
    );
    $st->execute([$idGrupo]);

    return (int) $st->fetchColumn();
}

/**
 * Alumnos de un grupo con estado de contacto pre-inicio.
 *
 * @return list<array<string, mixed>>
 */
function grupo_preinicio_listar_alumnos(PDO $pdo, int $idGrupo, int $idPlantel): array
{
    grupo_preinicio_ensure_schema($pdo);

    $st = $pdo->prepare(
        'SELECT a.id_alumno, a.numero_control,
                TRIM(CONCAT(a.nombres, \' \', a.apellido_paterno, \' \', IFNULL(a.apellido_materno,\'\'))) AS nombre,
                a.telefono, a.celular, a.email,
                gpc.id_contacto, gpc.contactado, gpc.fecha_contacto, gpc.medio, gpc.notas
         FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         LEFT JOIN grupo_preinicio_contacto gpc ON gpc.id_grupo = ag.id_grupo AND gpc.id_alumno = a.id_alumno
         WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.id_plantel = ?
         ORDER BY a.apellido_paterno, a.nombres'
    );
    $st->execute([$idGrupo, $idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array{ok:bool,message:string} */
function grupo_preinicio_guardar_contacto(PDO $pdo, int $idPlantel, array $data): array
{
    grupo_preinicio_ensure_schema($pdo);

    $idGrupo = (int) ($data['id_grupo'] ?? 0);
    $idAlumno = (int) ($data['id_alumno'] ?? 0);
    $idUsuario = (int) ($_SESSION['user_id'] ?? 0);
    if ($idGrupo <= 0 || $idAlumno <= 0 || $idUsuario <= 0) {
        return ['ok' => false, 'message' => 'Datos incompletos'];
    }

    $stG = $pdo->prepare('SELECT 1 FROM grupos WHERE id_grupo = ? AND id_plantel = ? LIMIT 1');
    $stG->execute([$idGrupo, $idPlantel]);
    if (!$stG->fetchColumn()) {
        return ['ok' => false, 'message' => 'Grupo no encontrado'];
    }

    $contactado = !empty($data['contactado']) ? 1 : 0;
    $medio = trim((string) ($data['medio'] ?? ''));
    $mediosValidos = ['telefono', 'whatsapp', 'presencial', 'correo', 'otro'];
    $medioSql = in_array($medio, $mediosValidos, true) ? $medio : null;
    $notas = trim((string) ($data['notas'] ?? ''));
    $fechaContacto = $contactado ? date('Y-m-d H:i:s') : null;

    $st = $pdo->prepare(
        'INSERT INTO grupo_preinicio_contacto (
            id_plantel, id_grupo, id_alumno, contactado, fecha_contacto, medio, notas, id_usuario_registro
        ) VALUES (?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            contactado = VALUES(contactado),
            fecha_contacto = VALUES(fecha_contacto),
            medio = VALUES(medio),
            notas = VALUES(notas),
            id_usuario_registro = VALUES(id_usuario_registro),
            actualizado_en = CURRENT_TIMESTAMP'
    );
    $st->execute([
        $idPlantel, $idGrupo, $idAlumno, $contactado, $fechaContacto, $medioSql,
        $notas !== '' ? $notas : null, $idUsuario,
    ]);

    return ['ok' => true, 'message' => 'Contacto guardado'];
}
