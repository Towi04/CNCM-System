<?php

function rol_aula_ensure_schema(PDO $pdo): void
{
    aula_ensure_schema($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS rol_aulas_publicacion (
            id_publicacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            anio SMALLINT UNSIGNED NOT NULL,
            mes TINYINT UNSIGNED NOT NULL,
            estado ENUM(\'borrador\',\'publicado\') NOT NULL DEFAULT \'borrador\',
            notas TEXT NULL,
            creado_por INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            publicado_por INT UNSIGNED NULL,
            publicado_en DATETIME NULL,
            PRIMARY KEY (id_publicacion),
            UNIQUE KEY uq_rol_aulas_plantel_periodo (id_plantel, anio, mes),
            KEY idx_rol_aulas_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS rol_aulas_asignacion (
            id_asignacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_publicacion INT UNSIGNED NOT NULL,
            id_grupo INT UNSIGNED NOT NULL,
            id_aula INT UNSIGNED NULL,
            cupo_grupo INT UNSIGNED NOT NULL DEFAULT 0,
            cupo_aula INT UNSIGNED NULL,
            es_manual TINYINT(1) NOT NULL DEFAULT 0,
            notas VARCHAR(255) NULL,
            PRIMARY KEY (id_asignacion),
            UNIQUE KEY uq_rol_asig_pub_grupo (id_publicacion, id_grupo),
            KEY idx_rol_asig_aula (id_aula),
            KEY idx_rol_asig_pub (id_publicacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function rol_aula_puede_gestionar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('menu_rol_aulas_gestionar')) {
        return true;
    }
    return in_array(rbac_rol_efectivo(), ['coordinador', 'director', 'supervisor'], true);
}

function rol_aula_puede_ver(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('menu_rol_aulas_consulta')) {
        return true;
    }
    if (rol_aula_puede_gestionar()) {
        return true;
    }
    return in_array(rbac_rol_efectivo(), ['admin', 'gerente'], true);
}

/** @return list<array<string, mixed>> */
function rol_aula_grupos_activos(PDO $pdo, int $idPlantel): array
{
    asistencia_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.id_especialidad, g.id_profesor, g.id_aula, g.aula, g.horario_texto,
                e.nombre AS esp_nombre, e.clave AS esp_clave,
                CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre,
                (SELECT COUNT(*) FROM alumno_grupos ag
                 INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.id_plantel = g.id_plantel
                 WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1) AS total_alumnos
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
         WHERE g.id_plantel = ?
           AND (SELECT COUNT(*) FROM alumno_grupos ag2
                INNER JOIN alumnos a2 ON a2.id_alumno = ag2.id_alumno
                WHERE ag2.id_grupo = g.id_grupo AND ag2.activo = 1) > 0
         ORDER BY total_alumnos DESC, g.clave'
    );
    $st->execute([$idPlantel]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['horarios'] = rol_aula_horarios_grupo($pdo, (int) $r['id_grupo']);
        $r['total_alumnos'] = (int) ($r['total_alumnos'] ?? 0);
    }
    unset($r);

    return $rows;
}

/** @return list<array<string, mixed>> */
function rol_aula_horarios_grupo(PDO $pdo, int $idGrupo): array
{
    asistencia_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT dia_semana, hora_inicio, hora_fin FROM grupo_horarios
         WHERE id_grupo = ? AND activo = 1 ORDER BY dia_semana, hora_inicio'
    );
    $st->execute([$idGrupo]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function rol_aula_horarios_solapan(array $h1, array $h2): bool
{
    if ((int) ($h1['dia_semana'] ?? -1) !== (int) ($h2['dia_semana'] ?? -2)) {
        return false;
    }
    $ini1 = strtotime((string) ($h1['hora_inicio'] ?? '00:00:00'));
    $fin1 = strtotime((string) ($h1['hora_fin'] ?? '00:00:00'));
    $ini2 = strtotime((string) ($h2['hora_inicio'] ?? '00:00:00'));
    $fin2 = strtotime((string) ($h2['hora_fin'] ?? '00:00:00'));

    return $ini1 < $fin2 && $ini2 < $fin1;
}

function rol_aula_grupos_conflicto_horario(array $grupoA, array $grupoB): bool
{
    foreach ($grupoA['horarios'] ?? [] as $ha) {
        foreach ($grupoB['horarios'] ?? [] as $hb) {
            if (rol_aula_horarios_solapan($ha, $hb)) {
                return true;
            }
        }
    }

    return false;
}

/** @param array<int, array<string, mixed>> $gruposPorId */
function rol_aula_conflicto_en_aula(array $gruposPorId, int $idGrupo, int $idAula, array $asignacionesActuales): bool
{
    $gNuevo = $gruposPorId[$idGrupo] ?? null;
    if (!$gNuevo) {
        return true;
    }
    foreach ($asignacionesActuales as $idOtro => $idAulaOtro) {
        if ($idOtro === $idGrupo || (int) $idAulaOtro !== $idAula) {
            continue;
        }
        $gOtro = $gruposPorId[$idOtro] ?? null;
        if ($gOtro && rol_aula_grupos_conflicto_horario($gNuevo, $gOtro)) {
            return true;
        }
    }

    return false;
}

function rol_aula_aula_compatible(array $aula, array $grupo): bool
{
    if (empty($aula['activo'])) {
        return false;
    }
    $cupo = max(1, (int) ($grupo['total_alumnos'] ?? 1));
    if (!aula_permite_especialidad($aula, (int) ($grupo['id_especialidad'] ?? 0))) {
        return false;
    }
    $capEf = aula_capacidad_efectiva($aula, $cupo);

    return $capEf >= $cupo;
}

/** @return array<string, mixed>|null */
function rol_aula_ultima_publicada(PDO $pdo, int $idPlantel): ?array
{
    rol_aula_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM rol_aulas_publicacion
         WHERE id_plantel = ? AND estado = \'publicado\'
         ORDER BY anio DESC, mes DESC LIMIT 1'
    );
    $st->execute([$idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return array<string, mixed>|null */
function rol_aula_obtener_periodo(PDO $pdo, int $idPlantel, int $anio, int $mes): ?array
{
    rol_aula_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM rol_aulas_publicacion WHERE id_plantel = ? AND anio = ? AND mes = ? LIMIT 1'
    );
    $st->execute([$idPlantel, $anio, $mes]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return array<string, mixed>|null */
function rol_aula_obtener(PDO $pdo, int $idPublicacion, int $idPlantel): ?array
{
    rol_aula_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM rol_aulas_publicacion WHERE id_publicacion = ? AND id_plantel = ? LIMIT 1'
    );
    $st->execute([$idPublicacion, $idPlantel]);
    $pub = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pub) {
        return null;
    }
    $pub['asignaciones'] = rol_aula_asignaciones($pdo, (int) $pub['id_publicacion'], $idPlantel);

    return $pub;
}

/** @return list<array<string, mixed>> */
function rol_aula_asignaciones(PDO $pdo, int $idPublicacion, int $idPlantel): array
{
    $st = $pdo->prepare(
        'SELECT ra.*, g.clave AS grupo_clave, g.id_especialidad, g.id_profesor, g.horario_texto,
                e.nombre AS esp_nombre,
                CONCAT(u.nombre, \' \', u.apellido) AS profesor_nombre,
                pa.codigo AS aula_codigo, pa.nombre AS aula_nombre, pa.capacidad AS aula_capacidad,
                pa.capacidad_flexible, pa.tipo_aula,
                (SELECT COUNT(*) FROM alumno_grupos ag
                 INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
                 WHERE ag.id_grupo = g.id_grupo AND ag.activo = 1) AS total_alumnos
         FROM rol_aulas_asignacion ra
         INNER JOIN grupos g ON g.id_grupo = ra.id_grupo AND g.id_plantel = ?
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
         LEFT JOIN plantel_aulas pa ON pa.id_aula = ra.id_aula
         WHERE ra.id_publicacion = ?
         ORDER BY COALESCE(pa.codigo, \'ZZZ\'), g.clave'
    );
    $st->execute([$idPlantel, $idPublicacion]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['horarios'] = rol_aula_horarios_grupo($pdo, (int) $r['id_grupo']);
        $r['total_alumnos'] = (int) ($r['total_alumnos'] ?? $r['cupo_grupo'] ?? 0);
        $r['tipo_label'] = aula_tipos()[$r['tipo_aula'] ?? 'aula'] ?? '';
    }
    unset($r);

    return $rows;
}

/** @return array{ok: bool, message: string, id_publicacion?: int} */
function rol_aula_generar(PDO $pdo, int $idPlantel, int $anio, int $mes, int $idUsuario): array
{
    rol_aula_ensure_schema($pdo);
    $mes = max(1, min(12, $mes));
    $grupos = rol_aula_grupos_activos($pdo, $idPlantel);
    if ($grupos === []) {
        return ['ok' => false, 'message' => 'No hay grupos activos con alumnos'];
    }

    $aulas = aula_listar_plantel($pdo, $idPlantel, true);
    if ($aulas === []) {
        return ['ok' => false, 'message' => 'Registre aulas activas antes de generar el rol'];
    }

    $existente = rol_aula_obtener_periodo($pdo, $idPlantel, $anio, $mes);
    if ($existente && ($existente['estado'] ?? '') === 'publicado') {
        return ['ok' => false, 'message' => 'El rol de este mes ya está publicado. Cree uno nuevo en borrador desde otro periodo o contacte soporte.'];
    }

    if ($existente) {
        $idPub = (int) $existente['id_publicacion'];
        $pdo->prepare('DELETE FROM rol_aulas_asignacion WHERE id_publicacion = ?')->execute([$idPub]);
        $pdo->prepare(
            'UPDATE rol_aulas_publicacion SET creado_por = ?, creado_en = NOW(), estado = \'borrador\',
             publicado_por = NULL, publicado_en = NULL WHERE id_publicacion = ?'
        )->execute([$idUsuario, $idPub]);
    } else {
        $pdo->prepare(
            'INSERT INTO rol_aulas_publicacion (id_plantel, anio, mes, estado, creado_por) VALUES (?,?,?,\'borrador\',?)'
        )->execute([$idPlantel, $anio, $mes, $idUsuario]);
        $idPub = (int) $pdo->lastInsertId();
    }

    $prevMap = [];
    $ultima = rol_aula_ultima_publicada($pdo, $idPlantel);
    if ($ultima) {
        $prevAsig = rol_aula_asignaciones($pdo, (int) $ultima['id_publicacion'], $idPlantel);
        foreach ($prevAsig as $a) {
            if (!empty($a['id_aula'])) {
                $prevMap[(int) $a['id_grupo']] = (int) $a['id_aula'];
            }
        }
    }

    $gruposPorId = [];
    foreach ($grupos as $g) {
        $gruposPorId[(int) $g['id_grupo']] = $g;
    }

    $aulasPorId = [];
    foreach ($aulas as $a) {
        $aulasPorId[(int) $a['id_aula']] = $a;
    }

    $asignaciones = [];
    $sinAsignar = [];

    usort($grupos, static function ($a, $b) {
        $cmp = ($b['total_alumnos'] ?? 0) <=> ($a['total_alumnos'] ?? 0);
        return $cmp !== 0 ? $cmp : strcmp((string) ($a['clave'] ?? ''), (string) ($b['clave'] ?? ''));
    });

    foreach ($grupos as $g) {
        $idGrupo = (int) $g['id_grupo'];
        $cupo = max(1, (int) $g['total_alumnos']);
        $idPrev = $prevMap[$idGrupo] ?? (int) ($g['id_aula'] ?? 0);
        $candidatos = [];

        if ($idPrev > 0 && isset($aulasPorId[$idPrev])) {
            $candidatos[] = $idPrev;
        }
        foreach ($aulasPorId as $idA => $aula) {
            if ($idA === $idPrev) {
                continue;
            }
            $candidatos[] = $idA;
        }

        $elegido = null;
        foreach ($candidatos as $idAula) {
            $aula = $aulasPorId[$idAula] ?? null;
            if (!$aula || !rol_aula_aula_compatible($aula, $g)) {
                continue;
            }
            if (rol_aula_conflicto_en_aula($gruposPorId, $idGrupo, $idAula, $asignaciones)) {
                continue;
            }
            $elegido = $idAula;
            break;
        }

        if ($elegido !== null) {
            $asignaciones[$idGrupo] = $elegido;
        } else {
            $sinAsignar[] = $g['clave'] ?? (string) $idGrupo;
            $asignaciones[$idGrupo] = null;
        }
    }

    $ins = $pdo->prepare(
        'INSERT INTO rol_aulas_asignacion (id_publicacion, id_grupo, id_aula, cupo_grupo, cupo_aula, es_manual)
         VALUES (?,?,?,?,?,0)'
    );
    foreach ($grupos as $g) {
        $idGrupo = (int) $g['id_grupo'];
        $idAula = $asignaciones[$idGrupo] ?? null;
        $cupo = max(1, (int) $g['total_alumnos']);
        $cupoAula = null;
        if ($idAula && isset($aulasPorId[$idAula])) {
            $cupoAula = aula_capacidad_efectiva($aulasPorId[$idAula], $cupo);
        }
        $ins->execute([$idPub, $idGrupo, $idAula, $cupo, $cupoAula]);
    }

    $msg = 'Rol generado en borrador con ' . count($grupos) . ' grupos.';
    if ($sinAsignar !== []) {
        $msg .= ' Sin aula: ' . implode(', ', array_slice($sinAsignar, 0, 8));
        if (count($sinAsignar) > 8) {
            $msg .= '…';
        }
    }

    return ['ok' => true, 'message' => $msg, 'id_publicacion' => $idPub];
}

/** @param list<array{id_grupo: int, id_aula: int|null}> $cambios */
function rol_aula_guardar_asignaciones(PDO $pdo, int $idPlantel, int $idPublicacion, array $cambios): array
{
    rol_aula_ensure_schema($pdo);
    $pub = rol_aula_obtener($pdo, $idPublicacion, $idPlantel);
    if (!$pub) {
        return ['ok' => false, 'message' => 'Publicación no encontrada'];
    }
    if (($pub['estado'] ?? '') === 'publicado') {
        return ['ok' => false, 'message' => 'El rol ya está publicado; no se puede editar'];
    }

    $aulas = aula_listar_plantel($pdo, $idPlantel, true);
    $aulasPorId = [];
    foreach ($aulas as $a) {
        $aulasPorId[(int) $a['id_aula']] = $a;
    }
    $grupos = rol_aula_grupos_activos($pdo, $idPlantel);
    $gruposPorId = [];
    foreach ($grupos as $g) {
        $gruposPorId[(int) $g['id_grupo']] = $g;
    }

    $map = [];
    foreach ($pub['asignaciones'] as $a) {
        $map[(int) $a['id_grupo']] = $a;
    }

    foreach ($cambios as $c) {
        $idGrupo = (int) ($c['id_grupo'] ?? 0);
        $idAula = isset($c['id_aula']) && $c['id_aula'] !== '' && $c['id_aula'] !== null
            ? (int) $c['id_aula'] : null;
        if ($idGrupo <= 0 || !isset($map[$idGrupo])) {
            continue;
        }
        if ($idAula !== null && !isset($aulasPorId[$idAula])) {
            return ['ok' => false, 'message' => 'Aula no válida para el grupo ' . ($map[$idGrupo]['grupo_clave'] ?? $idGrupo)];
        }
        $g = $gruposPorId[$idGrupo] ?? null;
        if ($idAula !== null && $g && !rol_aula_aula_compatible($aulasPorId[$idAula], $g)) {
            return [
                'ok' => false,
                'message' => 'El aula no es compatible (capacidad o especialidad) con ' . ($g['clave'] ?? $idGrupo),
            ];
        }
        $map[$idGrupo]['id_aula'] = $idAula;
        $map[$idGrupo]['es_manual'] = 1;
    }

  // Rebuild assignment map for conflict check
    $asigSimple = [];
    foreach ($map as $idG => $row) {
        $asigSimple[$idG] = $row['id_aula'] ?? null;
    }
    foreach ($map as $idG => $row) {
        $idA = $row['id_aula'] ?? null;
        if ($idA && rol_aula_conflicto_en_aula($gruposPorId, $idG, (int) $idA, $asigSimple)) {
            return [
                'ok' => false,
                'message' => 'Conflicto de horario: el grupo ' . ($row['grupo_clave'] ?? $idG)
                    . ' choca con otro grupo en la misma aula',
            ];
        }
    }

    $upd = $pdo->prepare(
        'UPDATE rol_aulas_asignacion SET id_aula = ?, cupo_aula = ?, es_manual = 1 WHERE id_publicacion = ? AND id_grupo = ?'
    );
    foreach ($cambios as $c) {
        $idGrupo = (int) ($c['id_grupo'] ?? 0);
        if ($idGrupo <= 0 || !isset($map[$idGrupo])) {
            continue;
        }
        $idAula = $map[$idGrupo]['id_aula'] ?? null;
        $cupo = (int) ($map[$idGrupo]['total_alumnos'] ?? $map[$idGrupo]['cupo_grupo'] ?? 1);
        $cupoAula = null;
        if ($idAula && isset($aulasPorId[$idAula])) {
            $cupoAula = aula_capacidad_efectiva($aulasPorId[$idAula], $cupo);
        }
        $upd->execute([$idAula, $cupoAula, $idPublicacion, $idGrupo]);
    }

    return ['ok' => true, 'message' => 'Asignaciones actualizadas'];
}

/** @return array{ok: bool, message: string, conflictos?: list<array<string, mixed>>} */
function rol_aula_validar(PDO $pdo, int $idPlantel, int $idPublicacion): array
{
    $pub = rol_aula_obtener($pdo, $idPublicacion, $idPlantel);
    if (!$pub) {
        return ['ok' => false, 'message' => 'Publicación no encontrada'];
    }

    $grupos = rol_aula_grupos_activos($pdo, $idPlantel);
    $gruposPorId = [];
    foreach ($grupos as $g) {
        $gruposPorId[(int) $g['id_grupo']] = $g;
    }
    $aulas = aula_listar_plantel($pdo, $idPlantel, true);
    $aulasPorId = [];
    foreach ($aulas as $a) {
        $aulasPorId[(int) $a['id_aula']] = $a;
    }

    $conflictos = [];
    $asigPorAula = [];

    foreach ($pub['asignaciones'] as $a) {
        $idGrupo = (int) $a['id_grupo'];
        $idAula = $a['id_aula'] ?? null;
        $clave = $a['grupo_clave'] ?? (string) $idGrupo;
        $g = $gruposPorId[$idGrupo] ?? null;

        if ($idAula === null || $idAula === '') {
            $conflictos[] = ['tipo' => 'sin_aula', 'grupo' => $clave, 'mensaje' => 'Grupo sin aula asignada'];
            continue;
        }
        $aula = $aulasPorId[(int) $idAula] ?? null;
        if (!$aula) {
            $conflictos[] = ['tipo' => 'aula_invalida', 'grupo' => $clave, 'mensaje' => 'Aula inactiva o inexistente'];
            continue;
        }
        if ($g && !rol_aula_aula_compatible($aula, $g)) {
            $conflictos[] = [
                'tipo' => 'incompatible',
                'grupo' => $clave,
                'mensaje' => 'Capacidad o especialidad no compatible con ' . ($aula['codigo'] ?? ''),
            ];
        }
        $asigPorAula[(int) $idAula][] = $a;
    }

    foreach ($asigPorAula as $idAula => $lista) {
        $n = count($lista);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $gi = $gruposPorId[(int) $lista[$i]['id_grupo']] ?? null;
                $gj = $gruposPorId[(int) $lista[$j]['id_grupo']] ?? null;
                if ($gi && $gj && rol_aula_grupos_conflicto_horario($gi, $gj)) {
                    $conflictos[] = [
                        'tipo' => 'horario',
                        'grupo' => ($lista[$i]['grupo_clave'] ?? '') . ' / ' . ($lista[$j]['grupo_clave'] ?? ''),
                        'mensaje' => 'Choque de horario en aula ' . ($lista[$i]['aula_codigo'] ?? $idAula),
                    ];
                }
            }
        }
    }

    if ($conflictos !== []) {
        return ['ok' => false, 'message' => 'Se encontraron ' . count($conflictos) . ' conflicto(s)', 'conflictos' => $conflictos];
    }

    return ['ok' => true, 'message' => 'Sin conflictos. Listo para publicar.'];
}

/** @return array{ok: bool, message: string, notificados?: int} */
function rol_aula_publicar(PDO $pdo, int $idPlantel, int $idPublicacion, int $idUsuario): array
{
    $valid = rol_aula_validar($pdo, $idPlantel, $idPublicacion);
    if (!$valid['ok']) {
        return $valid;
    }

    $pub = rol_aula_obtener($pdo, $idPublicacion, $idPlantel);
    if (!$pub) {
        return ['ok' => false, 'message' => 'Publicación no encontrada'];
    }
    if (($pub['estado'] ?? '') === 'publicado') {
        return ['ok' => false, 'message' => 'Ya estaba publicado'];
    }

    $pdo->prepare(
        'UPDATE rol_aulas_publicacion SET estado = \'publicado\', publicado_por = ?, publicado_en = NOW() WHERE id_publicacion = ?'
    )->execute([$idUsuario, $idPublicacion]);

    $periodo = sprintf('%02d/%d', (int) $pub['mes'], (int) $pub['anio']);
    $notificados = 0;

    foreach ($pub['asignaciones'] as $a) {
        $idGrupo = (int) $a['id_grupo'];
        $idAula = (int) ($a['id_aula'] ?? 0);
        $codigoAula = $a['aula_codigo'] ?? '';
        $nombreAula = trim($a['aula_nombre'] ?? '') ?: $codigoAula;
        $claveGrupo = $a['grupo_clave'] ?? '';

        if ($idAula > 0) {
            $pdo->prepare('UPDATE grupos SET id_aula = ?, aula = ? WHERE id_grupo = ? AND id_plantel = ?')
                ->execute([$idAula, $codigoAula, $idGrupo, $idPlantel]);
        }

        $titulo = 'Aula asignada — ' . $periodo;
        $mensaje = 'Tu grupo ' . $claveGrupo . ' estará en el aula '
            . $nombreAula . ($codigoAula !== '' ? ' (' . $codigoAula . ')' : '') . ' durante ' . $periodo . '.';

        if (!empty($a['id_profesor'])) {
            academico_notificar_usuario(
                $pdo,
                (int) $a['id_profesor'],
                'rol_aulas',
                $titulo,
                $mensaje,
                'profesor_portal',
                'mes=' . (int) $pub['mes'] . '&anio=' . (int) $pub['anio']
            );
            $notificados++;
        }

        $stAl = $pdo->prepare(
            'SELECT a.id_usuario FROM alumno_grupos ag
             INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
             WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.id_usuario IS NOT NULL'
        );
        $stAl->execute([$idGrupo]);
        foreach ($stAl->fetchAll(PDO::FETCH_COLUMN) as $idUser) {
            $idUser = (int) $idUser;
            if ($idUser > 0) {
                academico_notificar_usuario(
                    $pdo,
                    $idUser,
                    'rol_aulas',
                    $titulo,
                    $mensaje,
                    'alumno_portal',
                    null
                );
                $notificados++;
            }
        }
    }

    return [
        'ok' => true,
        'message' => 'Rol publicado. Notificaciones enviadas: ' . $notificados,
        'notificados' => $notificados,
    ];
}

/** @return list<array<string, mixed>> */
function rol_aula_listar_publicaciones(PDO $pdo, int $idPlantel, int $limite = 12): array
{
    rol_aula_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT p.*, CONCAT(u.nombre, \' \', u.apellido) AS creado_nombre,
                (SELECT COUNT(*) FROM rol_aulas_asignacion ra WHERE ra.id_publicacion = p.id_publicacion) AS total_grupos
         FROM rol_aulas_publicacion p
         LEFT JOIN usuarios u ON u.id_usuario = p.creado_por
         WHERE p.id_plantel = ?
         ORDER BY p.anio DESC, p.mes DESC
         LIMIT ' . max(1, min(24, $limite))
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<int, string> */
function rol_aula_meses_nombres(): array
{
    return [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
}

/** @param array<string, mixed> $pub */
function rol_aula_render_pdf_html(array $pub, string $tituloPlantel = ''): string
{
    $meses = rol_aula_meses_nombres();
    $mes = (int) ($pub['mes'] ?? 0);
    $anio = (int) ($pub['anio'] ?? 0);
    $periodo = ($meses[$mes] ?? '') . ' ' . $anio;
    $borrador = ($pub['estado'] ?? '') !== 'publicado';
    $asignaciones = $pub['asignaciones'] ?? [];

    $h = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

    $porAula = [];
    $sinAula = [];
    foreach ($asignaciones as $a) {
        if (!empty($a['id_aula'])) {
            $key = (string) $a['id_aula'];
            if (!isset($porAula[$key])) {
                $porAula[$key] = [
                    'codigo' => $a['aula_codigo'] ?? '',
                    'nombre' => $a['aula_nombre'] ?? '',
                    'capacidad' => $a['aula_capacidad'] ?? '',
                    'tipo' => aula_tipos()[$a['tipo_aula'] ?? 'aula'] ?? '',
                    'grupos' => [],
                ];
            }
            $porAula[$key]['grupos'][] = $a;
        } else {
            $sinAula[] = $a;
        }
    }
    uasort($porAula, static fn ($a, $b) => strcmp((string) $a['codigo'], (string) $b['codigo']));

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 16px; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #11458B; text-align: center; }
        .sub { text-align: center; color: #444; margin-bottom: 14px; font-size: 12px; }
        .borrador { background: #fff3e0; color: #e65100; text-align: center; padding: 6px; margin-bottom: 12px; font-weight: bold; border: 1px solid #ffb74d; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #bbb; padding: 6px 8px; vertical-align: top; }
        th { background: #11458B; color: #fff; font-size: 10px; text-transform: uppercase; }
        .aula-head { background: #e3f2fd; font-weight: bold; font-size: 13px; }
        .aula-meta { color: #555; font-size: 10px; font-weight: normal; }
        .grupo-clave { font-weight: bold; font-size: 12px; }
        tr:nth-child(even) td { background: #fafafa; }
        .pie { font-size: 9px; color: #666; text-align: center; margin-top: 16px; }
        .sin-aula th { background: #b71c1c; }
    </style></head><body>';

    $titulo = 'Rol de aulas';
    if ($tituloPlantel !== '') {
        $titulo .= ' — ' . $tituloPlantel;
    }
    $html .= '<h1>' . $h($titulo) . '</h1>';
    $html .= '<p class="sub">Periodo: <strong>' . $h($periodo) . '</strong> · '
        . count($asignaciones) . ' grupo(s) · Generado ' . date('d/m/Y H:i') . '</p>';

    if ($borrador) {
        $html .= '<div class="borrador">BORRADOR — No publicar en el periódico mural hasta confirmar y publicar en el sistema</div>';
    }

    if ($porAula === [] && $sinAula === []) {
        $html .= '<p style="text-align:center;color:#888;">Sin asignaciones para este periodo.</p>';
    } else {
        foreach ($porAula as $aula) {
            $meta = 'Cap. ' . (int) ($aula['capacidad'] ?: 0);
            if ($aula['tipo'] !== '') {
                $meta .= ' · ' . $aula['tipo'];
            }
            if ($aula['nombre'] !== '') {
                $meta .= ' · ' . $aula['nombre'];
            }
            $html .= '<table><thead><tr>';
            $html .= '<th colspan="5" class="aula-head">Aula ' . $h($aula['codigo'])
                . ' <span class="aula-meta">' . $h($meta) . '</span></th></tr>';
            $html .= '<tr><th>Grupo</th><th>Especialidad</th><th>Alumnos</th><th>Profesor</th><th>Horario</th></tr>';
            $html .= '</thead><tbody>';
            foreach ($aula['grupos'] as $g) {
                $html .= '<tr>';
                $html .= '<td class="grupo-clave">' . $h($g['grupo_clave'] ?? '') . '</td>';
                $html .= '<td>' . $h($g['esp_nombre'] ?? '—') . '</td>';
                $html .= '<td style="text-align:center;">' . (int) ($g['total_alumnos'] ?? 0) . '</td>';
                $html .= '<td>' . $h(trim($g['profesor_nombre'] ?? '') ?: '—') . '</td>';
                $html .= '<td>' . $h($g['horario_texto'] ?? '—') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        if ($sinAula !== []) {
            $html .= '<table class="sin-aula"><thead><tr><th colspan="5">Grupos sin aula asignada</th></tr>';
            $html .= '<tr><th>Grupo</th><th>Especialidad</th><th>Alumnos</th><th>Profesor</th><th>Horario</th></tr></thead><tbody>';
            foreach ($sinAula as $g) {
                $html .= '<tr>';
                $html .= '<td class="grupo-clave">' . $h($g['grupo_clave'] ?? '') . '</td>';
                $html .= '<td>' . $h($g['esp_nombre'] ?? '—') . '</td>';
                $html .= '<td style="text-align:center;">' . (int) ($g['total_alumnos'] ?? 0) . '</td>';
                $html .= '<td>' . $h(trim($g['profesor_nombre'] ?? '') ?: '—') . '</td>';
                $html .= '<td>' . $h($g['horario_texto'] ?? '—') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }
    }

    $html .= '<p class="pie">Grupo Educativo CNCM · Sistema HAY · Documento para periódico mural y consulta interna</p>';
    $html .= '</body></html>';

    return $html;
}

/**
 * @return array{ok:bool, es_pdf:bool, contenido:string, mime:string, filename:string, message?:string}
 */
function rol_aula_generar_pdf(PDO $pdo, int $idPlantel, int $anio, int $mes, string $tituloPlantel = '', bool $soloPublicado = false): array
{
    rol_aula_ensure_schema($pdo);
    $row = rol_aula_obtener_periodo($pdo, $idPlantel, $anio, $mes);
    if (!$row) {
        return ['ok' => false, 'es_pdf' => false, 'contenido' => '', 'mime' => 'text/plain', 'filename' => '', 'message' => 'No hay rol para este periodo'];
    }
    if ($soloPublicado && ($row['estado'] ?? '') !== 'publicado') {
        return ['ok' => false, 'es_pdf' => false, 'contenido' => '', 'mime' => 'text/plain', 'filename' => '', 'message' => 'El rol de este periodo aún no está publicado'];
    }

    $pub = rol_aula_obtener($pdo, (int) $row['id_publicacion'], $idPlantel);
    if (!$pub) {
        return ['ok' => false, 'es_pdf' => false, 'contenido' => '', 'mime' => 'text/plain', 'filename' => '', 'message' => 'No se pudo cargar el rol'];
    }

    $meses = rol_aula_meses_nombres();
    $html = rol_aula_render_pdf_html($pub, $tituloPlantel);
    $slugMes = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $meses[$mes] ?? 'mes') ?? 'mes');
    $filename = 'rol_aulas_' . $slugMes . '_' . $anio . '.pdf';

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('Dompdf\Dompdf') && class_exists('Dompdf\Options')) {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();

            return [
                'ok' => true,
                'es_pdf' => true,
                'contenido' => $dompdf->output(),
                'mime' => 'application/pdf',
                'filename' => $filename,
            ];
        }
    }

    $bar = '<div style="background:#e8f0fa;padding:8px;margin-bottom:10px;text-align:center;">'
        . '<button type="button" onclick="window.print()" style="background:#11458B;color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-weight:bold;">'
        . 'Imprimir / Guardar como PDF</button></div>';
    $htmlPrint = preg_replace('/<body>/i', '<body>' . $bar, $html, 1);

    return [
        'ok' => true,
        'es_pdf' => false,
        'contenido' => $htmlPrint,
        'mime' => 'text/html; charset=UTF-8',
        'filename' => str_replace('.pdf', '.html', $filename),
        'message' => 'Dompdf no instalado; use Imprimir → Guardar como PDF en el navegador.',
    ];
}
