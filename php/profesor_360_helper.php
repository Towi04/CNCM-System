<?php

/**
 * Evaluación 360 de profesores: ciclos, 4 fuentes (alumno, coordinador, auto, adjunto).
 */

function profesor_360_puede_gestionar(): bool
{
    if (function_exists('profesor_eval_puede_gestionar') && profesor_eval_puede_gestionar()) {
        return true;
    }
    return in_array(rbac_rol_efectivo(), ['coordinador', 'director', 'supervisor'], true);
}

function profesor_360_puede_evaluar_como(string $tipo): bool
{
    $rol = rbac_rol_efectivo();
    return match ($tipo) {
        'coordinador' => in_array($rol, ['coordinador', 'director', 'supervisor'], true),
        'auto' => $rol === 'profesor',
        'adjunto' => $rol === 'profesor',
        'alumno' => $rol === 'alumno',
        default => false,
    };
}

function profesor_360_ensure_schema(PDO $pdo): void
{
    docente_prospecto_ensure_schema($pdo);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS docente_rubrica_area (
            id_rubrica INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clave VARCHAR(40) NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            tipo ENUM('showclass','nivel','360_alumno','360_coordinador','360_auto','360_adjunto') NOT NULL DEFAULT 'showclass',
            id_especialidad INT UNSIGNED NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_rubrica),
            UNIQUE KEY uq_dra_clave_tipo (clave, tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS docente_rubrica_criterio (
            id_criterio INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_rubrica INT UNSIGNED NOT NULL,
            codigo VARCHAR(60) NOT NULL,
            nombre VARCHAR(200) NOT NULL,
            maximo SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_criterio),
            UNIQUE KEY uq_drc_rubrica_cod (id_rubrica, codigo),
            KEY idx_drc_rubrica (id_rubrica)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'docente_prospecto', 'id_rubrica', 'INT UNSIGNED NULL', 'especialidad');
        plantel_ensure_column($pdo, 'docente_prospecto', 'id_usuario_candidato', 'INT UNSIGNED NULL', 'id_usuario_registro');
        plantel_ensure_column($pdo, 'docente_prospecto', 'id_usuario_profesor', 'INT UNSIGNED NULL', 'id_usuario_candidato');
        plantel_ensure_column($pdo, 'docente_prospecto', 'email_google', 'VARCHAR(160) NULL', 'email');
        plantel_ensure_column($pdo, 'docente_prospecto', 'id_hay_area', 'INT UNSIGNED NULL', 'email_google');
    }

    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'profesor_360_participante', 'id_hay_area', 'INT UNSIGNED NOT NULL DEFAULT 0', 'id_profesor');
    }
    try {
        $pdo->exec('ALTER TABLE profesor_360_participante DROP INDEX uq_p360_part');
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec(
            'ALTER TABLE profesor_360_participante ADD UNIQUE KEY uq_p360_part (id_ciclo, id_profesor, id_hay_area)'
        );
    } catch (Throwable $e) {
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS profesor_360_ciclo (
            id_ciclo INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            anio SMALLINT UNSIGNED NOT NULL,
            mes TINYINT UNSIGNED NOT NULL,
            titulo VARCHAR(120) NULL,
            inicio_alumno DATETIME NULL,
            fin_alumno DATETIME NULL,
            inicio_adjunto DATETIME NULL,
            fin_adjunto DATETIME NULL,
            inicio_auto DATETIME NULL,
            fin_auto DATETIME NULL,
            inicio_coord DATETIME NULL,
            fin_coord DATETIME NULL,
            estado ENUM('borrador','abierto','cerrado','publicado') NOT NULL DEFAULT 'borrador',
            publicado_en DATETIME NULL,
            id_usuario_creador INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_ciclo),
            UNIQUE KEY uq_p360_ciclo (id_plantel, anio, mes)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS profesor_360_participante (
            id_participante INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_ciclo INT UNSIGNED NOT NULL,
            id_profesor INT UNSIGNED NOT NULL,
            id_adjunto INT UNSIGNED NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_participante),
            UNIQUE KEY uq_p360_part (id_ciclo, id_profesor),
            KEY idx_p360_adj (id_adjunto)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS profesor_360_eval (
            id_eval INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_ciclo INT UNSIGNED NOT NULL,
            id_profesor INT UNSIGNED NOT NULL,
            id_evaluador INT UNSIGNED NOT NULL,
            tipo ENUM('alumno','coordinador','auto','adjunto') NOT NULL,
            id_grupo INT UNSIGNED NULL,
            id_alumno INT UNSIGNED NULL,
            estado ENUM('borrador','cerrado') NOT NULL DEFAULT 'borrador',
            puntaje_total DECIMAL(8,2) NOT NULL DEFAULT 0,
            puntaje_max DECIMAL(8,2) NOT NULL DEFAULT 0,
            rubrica_json JSON NOT NULL,
            observaciones TEXT NULL,
            anonimo TINYINT(1) NOT NULL DEFAULT 1,
            cerrado_en DATETIME NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_eval),
            KEY idx_p360_eval_ciclo_prof (id_ciclo, id_profesor, tipo),
            KEY idx_p360_eval_evaluador (id_evaluador, tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    profesor_360_seed_rubricas_default($pdo);
}

function profesor_360_seed_rubricas_default(PDO $pdo): void
{
    if (function_exists('hay_meta_get') && hay_meta_get($pdo, 'profesor_360_rubricas_v1') === '1') {
        return;
    }

    $tiposShow = docente_showclass_rubrica_base();
    $idRub = profesor_360_guardar_rubrica($pdo, [
        'clave' => 'INGLES',
        'nombre' => 'Clase muestra — Inglés',
        'tipo' => 'showclass',
        'criterios' => array_map(static fn ($c) => [
            'codigo' => $c['codigo'],
            'nombre' => $c['nombre'],
            'maximo' => $c['maximo'],
        ], $tiposShow),
    ]);

    $alumno = [
        ['codigo' => 'claridad', 'nombre' => 'Claridad al explicar', 'maximo' => 10],
        ['codigo' => 'paciencia', 'nombre' => 'Paciencia y trato', 'maximo' => 10],
        ['codigo' => 'dominio', 'nombre' => 'Dominio del tema', 'maximo' => 10],
        ['codigo' => 'motivacion', 'nombre' => 'Motivación en clase', 'maximo' => 10],
        ['codigo' => 'retro', 'nombre' => 'Retroalimentación útil', 'maximo' => 10],
    ];
    profesor_360_guardar_rubrica($pdo, [
        'clave' => 'GENERAL',
        'nombre' => '360 Alumno → Profesor',
        'tipo' => '360_alumno',
        'criterios' => $alumno,
    ]);
    profesor_360_guardar_rubrica($pdo, [
        'clave' => 'GENERAL',
        'nombre' => '360 Coordinador → Profesor',
        'tipo' => '360_coordinador',
        'criterios' => $alumno,
    ]);
    profesor_360_guardar_rubrica($pdo, [
        'clave' => 'GENERAL',
        'nombre' => '360 Auto-evaluación',
        'tipo' => '360_auto',
        'criterios' => $alumno,
    ]);
    profesor_360_guardar_rubrica($pdo, [
        'clave' => 'GENERAL',
        'nombre' => '360 Profesor adjunto',
        'tipo' => '360_adjunto',
        'criterios' => $alumno,
    ]);

    if (function_exists('hay_meta_set')) {
        hay_meta_set($pdo, 'profesor_360_rubricas_v1', '1');
    }
}

/** @return list<array<string,mixed>> */
function profesor_360_listar_rubricas(PDO $pdo, ?string $tipo = null): array
{
    profesor_360_ensure_schema($pdo);
    $sql = 'SELECT * FROM docente_rubrica_area WHERE activo = 1';
    $params = [];
    if ($tipo !== null && $tipo !== '') {
        $sql .= ' AND tipo = ?';
        $params[] = $tipo;
    }
    $sql .= ' ORDER BY tipo, nombre';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['criterios'] = profesor_360_criterios_rubrica($pdo, (int) $r['id_rubrica']);
    }
    unset($r);

    return $rows;
}

/** @return list<array<string,mixed>> */
function profesor_360_criterios_rubrica(PDO $pdo, int $idRubrica): array
{
    $st = $pdo->prepare(
        'SELECT * FROM docente_rubrica_criterio WHERE id_rubrica = ? AND activo = 1 ORDER BY orden, id_criterio'
    );
    $st->execute([$idRubrica]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function profesor_360_guardar_rubrica(PDO $pdo, array $data): int
{
    profesor_360_ensure_schema($pdo);
    $clave = catalog_normalizar_clave((string) ($data['clave'] ?? ''), 40) ?: 'GENERAL';
    $nombre = trim((string) ($data['nombre'] ?? ''));
    $tipo = (string) ($data['tipo'] ?? 'showclass');
    $validTipos = ['showclass', 'nivel', '360_alumno', '360_coordinador', '360_auto', '360_adjunto'];
    if (!in_array($tipo, $validTipos, true)) {
        $tipo = 'showclass';
    }
    $id = (int) ($data['id_rubrica'] ?? 0);

    if ($id > 0) {
        $pdo->prepare('UPDATE docente_rubrica_area SET clave=?, nombre=?, tipo=? WHERE id_rubrica=?')
            ->execute([$clave, $nombre, $tipo, $id]);
    } else {
        $st = $pdo->prepare('SELECT id_rubrica FROM docente_rubrica_area WHERE clave = ? AND tipo = ? LIMIT 1');
        $st->execute([$clave, $tipo]);
        $id = (int) $st->fetchColumn();
        if ($id <= 0) {
            $pdo->prepare('INSERT INTO docente_rubrica_area (clave, nombre, tipo) VALUES (?,?,?)')
                ->execute([$clave, $nombre ?: $clave, $tipo]);
            $id = (int) $pdo->lastInsertId();
        }
    }

    if (!empty($data['criterios']) && is_array($data['criterios'])) {
        $pdo->prepare('UPDATE docente_rubrica_criterio SET activo = 0 WHERE id_rubrica = ?')->execute([$id]);
        $ins = $pdo->prepare(
            'INSERT INTO docente_rubrica_criterio (id_rubrica, codigo, nombre, maximo, orden) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), maximo=VALUES(maximo), orden=VALUES(orden), activo=1'
        );
        $ord = 0;
        foreach ($data['criterios'] as $c) {
            $cod = catalog_normalizar_clave((string) ($c['codigo'] ?? ''), 60);
            if ($cod === '') {
                continue;
            }
            $ord += 10;
            $ins->execute([
                $id,
                $cod,
                trim((string) ($c['nombre'] ?? $cod)),
                max(1, (int) ($c['maximo'] ?? 10)),
                $ord,
            ]);
        }
    }

    return $id;
}

function profesor_360_rubrica_por_tipo(PDO $pdo, string $tipo, string $claveArea = 'GENERAL'): ?array
{
    $st = $pdo->prepare(
        'SELECT * FROM docente_rubrica_area WHERE tipo = ? AND clave = ? AND activo = 1 LIMIT 1'
    );
    $st->execute([$tipo, $claveArea]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $st->execute([$tipo, 'GENERAL']);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row) {
        return null;
    }
    $row['criterios'] = profesor_360_criterios_rubrica($pdo, (int) $row['id_rubrica']);

    return $row;
}

/** @return list<array<string,mixed>> */
function profesor_360_listar_ciclos(PDO $pdo, int $idPlantel): array
{
    profesor_360_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT c.*, u.nombre AS creador_nombre, u.apellido AS creador_apellido
         FROM profesor_360_ciclo c
         LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario_creador
         WHERE c.id_plantel = ?
         ORDER BY c.anio DESC, c.mes DESC'
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function profesor_360_obtener_ciclo(PDO $pdo, int $idCiclo): ?array
{
    $st = $pdo->prepare('SELECT * FROM profesor_360_ciclo WHERE id_ciclo = ? LIMIT 1');
    $st->execute([$idCiclo]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function profesor_360_guardar_ciclo(PDO $pdo, array $data, int $idUsuario): array
{
    profesor_360_ensure_schema($pdo);
    $idPlantel = (int) ($data['id_plantel'] ?? plantel_scope_id($pdo));
    $anio = (int) ($data['anio'] ?? date('Y'));
    $mes = (int) ($data['mes'] ?? date('n'));
    if ($mes < 1 || $mes > 12) {
        return ['ok' => false, 'message' => 'Mes inválido'];
    }
    $id = (int) ($data['id_ciclo'] ?? 0);
    $fields = [
        'titulo' => trim((string) ($data['titulo'] ?? '')) ?: null,
        'inicio_alumno' => profesor_360_dt($data['inicio_alumno'] ?? null),
        'fin_alumno' => profesor_360_dt($data['fin_alumno'] ?? null),
        'inicio_adjunto' => profesor_360_dt($data['inicio_adjunto'] ?? null),
        'fin_adjunto' => profesor_360_dt($data['fin_adjunto'] ?? null),
        'inicio_auto' => profesor_360_dt($data['inicio_auto'] ?? null),
        'fin_auto' => profesor_360_dt($data['fin_auto'] ?? null),
        'inicio_coord' => profesor_360_dt($data['inicio_coord'] ?? null),
        'fin_coord' => profesor_360_dt($data['fin_coord'] ?? null),
    ];

    if ($id > 0) {
        $pdo->prepare(
            'UPDATE profesor_360_ciclo SET titulo=?, inicio_alumno=?, fin_alumno=?, inicio_adjunto=?, fin_adjunto=?,
             inicio_auto=?, fin_auto=?, inicio_coord=?, fin_coord=? WHERE id_ciclo=? AND id_plantel=?'
        )->execute([
            $fields['titulo'], $fields['inicio_alumno'], $fields['fin_alumno'],
            $fields['inicio_adjunto'], $fields['fin_adjunto'], $fields['inicio_auto'], $fields['fin_auto'],
            $fields['inicio_coord'], $fields['fin_coord'], $id, $idPlantel,
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO profesor_360_ciclo (id_plantel, anio, mes, titulo, inicio_alumno, fin_alumno,
             inicio_adjunto, fin_adjunto, inicio_auto, fin_auto, inicio_coord, fin_coord, id_usuario_creador)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $idPlantel, $anio, $mes, $fields['titulo'],
            $fields['inicio_alumno'], $fields['fin_alumno'], $fields['inicio_adjunto'], $fields['fin_adjunto'],
            $fields['inicio_auto'], $fields['fin_auto'], $fields['inicio_coord'], $fields['fin_coord'], $idUsuario,
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    return ['ok' => true, 'id_ciclo' => $id, 'message' => 'Ciclo guardado'];
}

function profesor_360_dt(?string $v): ?string
{
    $v = trim((string) $v);
    if ($v === '') {
        return null;
    }
    $ts = strtotime($v);

    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function profesor_360_ciclo_tipo_abierto(?array $ciclo, string $tipo): bool
{
    if (!$ciclo || ($ciclo['estado'] ?? '') === 'borrador') {
        return false;
    }
    if (in_array($ciclo['estado'] ?? '', ['cerrado', 'publicado'], true)) {
        return false;
    }
    $map = [
        'alumno' => ['inicio_alumno', 'fin_alumno'],
        'adjunto' => ['inicio_adjunto', 'fin_adjunto'],
        'auto' => ['inicio_auto', 'fin_auto'],
        'coordinador' => ['inicio_coord', 'fin_coord'],
    ];
    [$ini, $fin] = $map[$tipo] ?? ['', ''];
    $now = time();
    if (!empty($ciclo[$ini]) && strtotime((string) $ciclo[$ini]) > $now) {
        return false;
    }
    if (!empty($ciclo[$fin]) && strtotime((string) $ciclo[$fin]) < $now) {
        return false;
    }

    return true;
}

function profesor_360_publicar_ciclo(PDO $pdo, int $idCiclo): array
{
    $c = profesor_360_obtener_ciclo($pdo, $idCiclo);
    if (!$c) {
        return ['ok' => false, 'message' => 'Ciclo no encontrado'];
    }
    $pdo->prepare("UPDATE profesor_360_ciclo SET estado = 'abierto' WHERE id_ciclo = ?")->execute([$idCiclo]);

    return ['ok' => true, 'message' => 'Evaluaciones abiertas según fechas configuradas'];
}

function profesor_360_cerrar_ciclo(PDO $pdo, int $idCiclo): array
{
    $pdo->prepare("UPDATE profesor_360_ciclo SET estado = 'cerrado' WHERE id_ciclo = ?")->execute([$idCiclo]);

    return ['ok' => true, 'message' => 'Ciclo cerrado'];
}

function profesor_360_publicar_resultados(PDO $pdo, int $idCiclo): array
{
    $pdo->prepare("UPDATE profesor_360_ciclo SET estado = 'publicado', publicado_en = NOW() WHERE id_ciclo = ?")
        ->execute([$idCiclo]);

    return ['ok' => true, 'message' => 'Resultados publicados en perfiles docentes'];
}

/** @return list<array<string,mixed>> */
function profesor_360_profesores_plantel(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        "SELECT u.id_usuario, u.nombre, u.apellido, u.email,
                CONCAT(u.nombre,' ',u.apellido) AS nombre_completo
         FROM usuarios u
         WHERE u.id_plantel = ? AND u.rol = 'profesor' AND COALESCE(u.suspendido,0) = 0
         ORDER BY u.apellido, u.nombre"
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function profesor_360_guardar_participantes(PDO $pdo, int $idCiclo, array $filas): array
{
    profesor_360_ensure_schema($pdo);
    $pdo->prepare('DELETE FROM profesor_360_participante WHERE id_ciclo = ?')->execute([$idCiclo]);
    $ins = $pdo->prepare(
        'INSERT INTO profesor_360_participante (id_ciclo, id_profesor, id_adjunto, activo) VALUES (?,?,?,1)'
    );
    foreach ($filas as $f) {
        $idProf = (int) ($f['id_profesor'] ?? 0);
        if ($idProf <= 0) {
            continue;
        }
        $idAdj = (int) ($f['id_adjunto'] ?? 0) ?: null;
        $ins->execute([$idCiclo, $idProf, $idAdj]);
    }

    return ['ok' => true, 'message' => 'Participantes actualizados'];
}

/** @return list<array<string,mixed>> */
function profesor_360_participantes(PDO $pdo, int $idCiclo): array
{
    $st = $pdo->prepare(
        'SELECT p.*, CONCAT(pr.nombre,\' \',pr.apellido) AS profesor_nombre,
                CONCAT(ad.nombre,\' \',ad.apellido) AS adjunto_nombre
         FROM profesor_360_participante p
         INNER JOIN usuarios pr ON pr.id_usuario = p.id_profesor
         LEFT JOIN usuarios ad ON ad.id_usuario = p.id_adjunto
         WHERE p.id_ciclo = ? AND p.activo = 1
         ORDER BY pr.apellido, pr.nombre'
    );
    $st->execute([$idCiclo]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function profesor_360_calcular_puntajes(array $criterios, array $puntajes): array
{
    $items = [];
    $total = 0.0;
    $max = 0.0;
    foreach ($criterios as $c) {
        $cod = (string) ($c['codigo'] ?? '');
        $m = (float) ($c['maximo'] ?? 10);
        $v = (float) ($puntajes[$cod] ?? 0);
        if ($v < 0) {
            $v = 0;
        }
        if ($v > $m) {
            $v = $m;
        }
        $items[] = [
            'codigo' => $cod,
            'nombre' => (string) ($c['nombre'] ?? $cod),
            'maximo' => $m,
            'puntaje' => $v,
        ];
        $total += $v;
        $max += $m;
    }

    return [
        'items' => $items,
        'total' => round($total, 2),
        'max' => round($max, 2),
        'pct' => $max > 0 ? round(($total / $max) * 100, 2) : 0,
    ];
}

function profesor_360_guardar_eval(
    PDO $pdo,
    int $idCiclo,
    int $idProfesor,
    int $idEvaluador,
    string $tipo,
    array $puntajes,
    string $observaciones,
    ?int $idGrupo,
    ?int $idAlumno,
    bool $cerrar
): array {
    profesor_360_ensure_schema($pdo);
    $ciclo = profesor_360_obtener_ciclo($pdo, $idCiclo);
    if (!$ciclo) {
        return ['ok' => false, 'message' => 'Ciclo no encontrado'];
    }
    if (!profesor_360_ciclo_tipo_abierto($ciclo, $tipo) && !profesor_360_puede_gestionar()) {
        return ['ok' => false, 'message' => 'Este tipo de evaluación no está abierto'];
    }

    $rub = profesor_360_rubrica_por_tipo($pdo, '360_' . ($tipo === 'coordinador' ? 'coordinador' : $tipo));
    if (!$rub) {
        return ['ok' => false, 'message' => 'Rúbrica no configurada'];
    }
    $calc = profesor_360_calcular_puntajes($rub['criterios'], $puntajes);
    $json = json_encode($calc['items'], JSON_UNESCAPED_UNICODE) ?: '[]';
    $estado = $cerrar ? 'cerrado' : 'borrador';

    $st = $pdo->prepare(
        'SELECT id_eval FROM profesor_360_eval
         WHERE id_ciclo=? AND id_profesor=? AND id_evaluador=? AND tipo=?
           AND (id_grupo <=> ?) AND (id_alumno <=> ?) LIMIT 1'
    );
    $st->execute([$idCiclo, $idProfesor, $idEvaluador, $tipo, $idGrupo, $idAlumno]);
    $idEval = (int) $st->fetchColumn();

    if ($idEval > 0) {
        $pdo->prepare(
            'UPDATE profesor_360_eval SET rubrica_json=?, puntaje_total=?, puntaje_max=?, observaciones=?, estado=?, cerrado_en=IF(?=\'cerrado\',NOW(),cerrado_en)
             WHERE id_eval=?'
        )->execute([$json, $calc['total'], $calc['max'], trim($observaciones) ?: null, $estado, $estado, $idEval]);
    } else {
        $pdo->prepare(
            'INSERT INTO profesor_360_eval (id_ciclo, id_profesor, id_evaluador, tipo, id_grupo, id_alumno,
             estado, puntaje_total, puntaje_max, rubrica_json, observaciones, anonimo, cerrado_en)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,1,IF(?=\'cerrado\',NOW(),NULL))'
        )->execute([
            $idCiclo, $idProfesor, $idEvaluador, $tipo, $idGrupo, $idAlumno,
            $estado, $calc['total'], $calc['max'], $json, trim($observaciones) ?: null, $estado,
        ]);
        $idEval = (int) $pdo->lastInsertId();
    }

    return [
        'ok' => true,
        'id_eval' => $idEval,
        'message' => $cerrar ? 'Evaluación cerrada' : 'Borrador guardado',
        'pct' => $calc['pct'],
    ];
}

/** Evaluaciones pendientes para el usuario actual. */
function profesor_360_pendientes_usuario(PDO $pdo, int $idUsuario, string $rol): array
{
    $idPlantel = plantel_scope_id($pdo);
    $out = [];
    $st = $pdo->prepare(
        "SELECT * FROM profesor_360_ciclo WHERE id_plantel = ? AND estado IN ('abierto') ORDER BY anio DESC, mes DESC"
    );
    $st->execute([$idPlantel]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ciclo) {
        $idCiclo = (int) $ciclo['id_ciclo'];
        if ($rol === 'alumno' && profesor_360_ciclo_tipo_abierto($ciclo, 'alumno')) {
            $profes = profesor_360_profesores_de_alumno($pdo, $idUsuario, $idPlantel);
            foreach ($profes as $pr) {
                if (!profesor_360_eval_existe($pdo, $idCiclo, (int) $pr['id_profesor'], $idUsuario, 'alumno', (int) $pr['id_grupo'], $idUsuario)) {
                    $out[] = [
                        'tipo' => 'alumno',
                        'id_ciclo' => $idCiclo,
                        'id_profesor' => (int) $pr['id_profesor'],
                        'profesor_nombre' => $pr['nombre_completo'],
                        'id_grupo' => (int) $pr['id_grupo'],
                    ];
                }
            }
        }
        if ($rol === 'profesor') {
            if (profesor_360_ciclo_tipo_abierto($ciclo, 'auto')
                && !profesor_360_eval_existe($pdo, $idCiclo, $idUsuario, $idUsuario, 'auto', null, null)) {
                $out[] = ['tipo' => 'auto', 'id_ciclo' => $idCiclo, 'id_profesor' => $idUsuario];
            }
            if (profesor_360_ciclo_tipo_abierto($ciclo, 'adjunto')) {
                $stA = $pdo->prepare(
                    'SELECT p.id_profesor, CONCAT(u.nombre,\' \',u.apellido) AS nombre
                     FROM profesor_360_participante p
                     INNER JOIN usuarios u ON u.id_usuario = p.id_profesor
                     WHERE p.id_ciclo = ? AND p.id_adjunto = ?'
                );
                $stA->execute([$idCiclo, $idUsuario]);
                foreach ($stA->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (!profesor_360_eval_existe($pdo, $idCiclo, (int) $row['id_profesor'], $idUsuario, 'adjunto', null, null)) {
                        $out[] = [
                            'tipo' => 'adjunto',
                            'id_ciclo' => $idCiclo,
                            'id_profesor' => (int) $row['id_profesor'],
                            'profesor_nombre' => $row['nombre'],
                        ];
                    }
                }
            }
        }
        if (profesor_360_puede_gestionar() && profesor_360_ciclo_tipo_abierto($ciclo, 'coordinador')) {
            foreach (profesor_360_participantes($pdo, $idCiclo) as $p) {
                if (!profesor_360_eval_existe($pdo, $idCiclo, (int) $p['id_profesor'], $idUsuario, 'coordinador', null, null)) {
                    $out[] = [
                        'tipo' => 'coordinador',
                        'id_ciclo' => $idCiclo,
                        'id_profesor' => (int) $p['id_profesor'],
                        'profesor_nombre' => $p['profesor_nombre'],
                    ];
                }
            }
        }
    }

    return $out;
}

function profesor_360_eval_existe(
    PDO $pdo,
    int $idCiclo,
    int $idProfesor,
    int $idEvaluador,
    string $tipo,
    ?int $idGrupo,
    ?int $idAlumno
): bool {
    $st = $pdo->prepare(
        'SELECT 1 FROM profesor_360_eval
         WHERE id_ciclo=? AND id_profesor=? AND id_evaluador=? AND tipo=? AND estado=\'cerrado\'
           AND (id_grupo <=> ?) AND (id_alumno <=> ?) LIMIT 1'
    );
    $st->execute([$idCiclo, $idProfesor, $idEvaluador, $tipo, $idGrupo, $idAlumno]);

    return (bool) $st->fetchColumn();
}

/** @return list<array<string,mixed>> */
function profesor_360_profesores_de_alumno(PDO $pdo, int $idUsuario, int $idPlantel): array
{
    $idAlumno = (int) ($_SESSION['id_alumno_link'] ?? 0);
    if ($idAlumno <= 0) {
        $stA = $pdo->prepare('SELECT id_alumno FROM alumnos WHERE id_usuario = ? LIMIT 1');
        $stA->execute([$idUsuario]);
        $idAlumno = (int) $stA->fetchColumn();
    }
    if ($idAlumno <= 0) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT DISTINCT g.id_profesor, g.id_grupo, CONCAT(u.nombre,\' \',u.apellido) AS nombre_completo
         FROM alumno_grupo ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo AND g.id_plantel = ?
         INNER JOIN usuarios u ON u.id_usuario = g.id_profesor
         WHERE ag.id_alumno = ? AND ag.estatus = \'activo\''
    );
    $st->execute([$idPlantel, $idAlumno]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Resultados publicados para perfil docente (observaciones anónimas). */
function profesor_360_resultados_profesor(PDO $pdo, int $idProfesor): array
{
    $st = $pdo->prepare(
        "SELECT e.*, c.anio, c.mes, c.titulo, c.estado AS estado_ciclo
         FROM profesor_360_eval e
         INNER JOIN profesor_360_ciclo c ON c.id_ciclo = e.id_ciclo
         WHERE e.id_profesor = ? AND c.estado = 'publicado' AND e.estado = 'cerrado'
         ORDER BY c.anio DESC, c.mes DESC, e.tipo"
    );
    $st->execute([$idProfesor]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['rubrica'] = json_decode((string) ($r['rubrica_json'] ?? '[]'), true) ?: [];
        $r['pct'] = (float) ($r['puntaje_max'] ?? 0) > 0
            ? round(((float) $r['puntaje_total'] / (float) $r['puntaje_max']) * 100, 1) : 0;
        if ((int) ($r['anonimo'] ?? 1) === 1 && ($r['tipo'] ?? '') === 'alumno') {
            $r['evaluador_label'] = 'Alumno (anónimo)';
        } else {
            $r['evaluador_label'] = ucfirst((string) ($r['tipo'] ?? ''));
        }
    }
    unset($r);

    return $rows;
}

function profesor_360_resumen_ciclo_profesor(PDO $pdo, int $idCiclo, int $idProfesor): array
{
    $st = $pdo->prepare(
        'SELECT tipo, COUNT(*) AS n, AVG(puntaje_total / NULLIF(puntaje_max,0) * 100) AS pct_prom
         FROM profesor_360_eval
         WHERE id_ciclo = ? AND id_profesor = ? AND estado = \'cerrado\'
         GROUP BY tipo'
    );
    $st->execute([$idCiclo, $idProfesor]);
    $byTipo = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byTipo[$row['tipo']] = [
            'count' => (int) $row['n'],
            'pct_prom' => round((float) ($row['pct_prom'] ?? 0), 1),
        ];
    }

    return $byTipo;
}
