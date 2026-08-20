<?php

const GRUPO_DIVISION_UMBRAL = 15;

function grupo_division_ensure_schema(PDO $pdo): void
{
    if (function_exists('grupo_clave_ensure_schema')) {
        grupo_clave_ensure_schema($pdo);
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_division (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_grupo_origen INT UNSIGNED NOT NULL,
            id_grupo_nuevo INT UNSIGNED NOT NULL,
            asignacion_original_json JSON NOT NULL,
            asignacion_nuevo_json JSON NOT NULL,
            estado ENUM(\'borrador\',\'confirmado\',\'cancelado\') NOT NULL DEFAULT \'borrador\',
            creado_por INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmado_por INT UNSIGNED NULL,
            confirmado_en DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_gd_plantel_estado (id_plantel, estado),
            KEY idx_gd_origen (id_grupo_origen),
            KEY idx_gd_nuevo (id_grupo_nuevo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function grupo_division_puede(): bool
{
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : '';
    if (!in_array($rol, ['coordinador', 'admin', 'director', 'supervisor'], true)) {
        return false;
    }

    return !function_exists('rbac_cap') || rbac_cap('menu_grupo_division');
}

/** @return list<array<string, mixed>> */
function grupo_division_listar_grupos(PDO $pdo, int $idPlantel): array
{
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.fecha_inicio, g.codigo_horario, g.horario_texto,
                e.nombre AS especialidad,
                TRIM(CONCAT(COALESCE(u.nombre, \'\'), \' \', COALESCE(u.apellido, \'\'))) AS profesor,
                COUNT(DISTINCT ag.id_alumno) AS total_alumnos
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
         LEFT JOIN alumno_grupos ag ON ag.id_grupo = g.id_grupo AND ag.activo = 1
         WHERE g.id_plantel = ?
         GROUP BY g.id_grupo, g.clave, g.fecha_inicio, g.codigo_horario, g.horario_texto,
                  e.nombre, u.nombre, u.apellido
         ORDER BY total_alumnos DESC, g.clave'
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string, mixed>> */
function grupo_division_alumnos(PDO $pdo, int $idGrupo, int $idPlantel): array
{
    $st = $pdo->prepare(
        'SELECT a.id_alumno, a.numero_control, a.fecha_nacimiento, a.edad,
                COALESCE(
                    CASE WHEN a.fecha_nacimiento IS NOT NULL
                         THEN TIMESTAMPDIFF(YEAR, a.fecha_nacimiento, CURDATE()) END,
                    NULLIF(a.edad, 0)
                ) AS edad_calculada,
                TRIM(CONCAT(COALESCE(NULLIF(a.nombres, \'\'), a.nombre, \'\'), \' \',
                            COALESCE(a.apellido_paterno, a.apellido, \'\'), \' \',
                            COALESCE(a.apellido_materno, \'\'))) AS alumno
         FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.id_plantel = ?
         WHERE ag.id_grupo = ? AND ag.activo = 1
         ORDER BY (edad_calculada IS NULL), edad_calculada ASC, a.fecha_nacimiento DESC, alumno'
    );
    $st->execute([$idPlantel, $idGrupo]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{clave:string,numero_secuencial:int,codigo_area:string,codigo_horario:string,es_extensivo:int,es_personalizado:int} */
function grupo_division_generar_clave(PDO $pdo, int $idPlantel, array $grupo): array
{
    $personalizado = !empty($grupo['es_personalizado']);
    $area = strtoupper(trim((string) ($grupo['codigo_area'] ?? 'I')));
    $horario = strtoupper(trim((string) ($grupo['codigo_horario'] ?? 'S')));
    if (!in_array($area, ['I', 'C', 'PA', 'PE'], true)) {
        $area = 'I';
    }
    if (!in_array($horario, array_keys(GRUPO_HORARIOS), true)) {
        $horario = 'S';
    }
    $nombrePersonalizado = $personalizado
        ? preg_replace('/^PER-/i', '', (string) ($grupo['clave'] ?? 'GRUPO')) . '-DIV'
        : '';
    $gen = grupo_clave_generar(
        $pdo,
        $idPlantel,
        $area,
        $horario,
        !empty($grupo['es_extensivo']),
        $personalizado,
        $nombrePersonalizado
    );
    $base = (string) $gen['clave'];
    $clave = $base;
    $n = 2;
    $st = $pdo->prepare('SELECT 1 FROM grupos WHERE id_plantel = ? AND clave = ? LIMIT 1');
    while (true) {
        $st->execute([$idPlantel, $clave]);
        if (!$st->fetchColumn()) {
            break;
        }
        $clave = $base . '-' . $n++;
    }
    $gen['clave'] = $clave;

    return $gen;
}

/** Crea el grupo vacío clonando los campos y relaciones docentes/horarios. */
function grupo_division_clonar_grupo(PDO $pdo, int $idPlantel, int $idGrupoOrigen, ?array $gen = null): array
{
    $st = $pdo->prepare('SELECT * FROM grupos WHERE id_grupo = ? AND id_plantel = ? LIMIT 1');
    $st->execute([$idGrupoOrigen, $idPlantel]);
    $grupo = $st->fetch(PDO::FETCH_ASSOC);
    if (!$grupo) {
        throw new RuntimeException('Grupo origen no encontrado en este plantel.');
    }
    $gen = $gen ?? grupo_division_generar_clave($pdo, $idPlantel, $grupo);

    $columnasDb = $pdo->query('SHOW COLUMNS FROM grupos')->fetchAll(PDO::FETCH_COLUMN);
    $excluir = [
        'id_grupo', 'clave', 'clave_anterior', 'clave_actualizada_en', 'clave_actualizada_por',
        'fusiones_total', 'fusion_desfase', 'id_grupo_pareja_infantil',
    ];
    $columnas = ['clave'];
    $valores = [$gen['clave']];
    foreach ($columnasDb as $col) {
        $col = (string) $col;
        if (in_array($col, $excluir, true) || $col === 'clave') {
            continue;
        }
        $columnas[] = $col;
        if ($col === 'numero_secuencial') {
            $valores[] = (int) ($gen['numero_secuencial'] ?? 0) ?: null;
        } elseif ($col === 'codigo_area') {
            $valores[] = $gen['codigo_area'] ?? ($grupo[$col] ?? null);
        } elseif ($col === 'codigo_horario') {
            $valores[] = ($gen['codigo_horario'] ?? '') ?: ($grupo[$col] ?? null);
        } else {
            $valores[] = $grupo[$col] ?? null;
        }
    }
    $colsSql = implode(', ', array_map(static fn (string $c): string => "`$c`", $columnas));
    $marks = implode(', ', array_fill(0, count($columnas), '?'));
    $pdo->prepare("INSERT INTO grupos ($colsSql) VALUES ($marks)")->execute($valores);
    $idNuevo = (int) $pdo->lastInsertId();

    if (plantel_table_exists($pdo, 'grupo_horarios')) {
        $pdo->prepare(
            'INSERT INTO grupo_horarios (id_grupo, dia_semana, hora_inicio, hora_fin, activo)
             SELECT ?, dia_semana, hora_inicio, hora_fin, activo
             FROM grupo_horarios WHERE id_grupo = ?'
        )->execute([$idNuevo, $idGrupoOrigen]);
    }
    if (plantel_table_exists($pdo, 'grupo_docente')) {
        $pdo->prepare(
            'INSERT INTO grupo_docente (id_grupo, id_profesor, materia_clave, materia_nombre, es_titular, activo)
             SELECT ?, id_profesor, materia_clave, materia_nombre, es_titular, activo
             FROM grupo_docente WHERE id_grupo = ?'
        )->execute([$idNuevo, $idGrupoOrigen]);
    }

    return ['id_grupo' => $idNuevo, 'clave' => $gen['clave']];
}

/** @return array{ok:bool,message:string,id?:int,division?:array<string,mixed>} */
function grupo_division_proponer(
    PDO $pdo,
    int $idPlantel,
    int $idGrupo,
    int $idUsuario
): array {
    if (!grupo_division_puede()) {
        return ['ok' => false, 'message' => 'Sin permiso para dividir grupos.'];
    }
    grupo_division_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT id FROM grupo_division
         WHERE id_plantel = ? AND id_grupo_origen = ? AND estado = \'borrador\' LIMIT 1'
    );
    $st->execute([$idPlantel, $idGrupo]);
    $existente = (int) $st->fetchColumn();
    if ($existente > 0) {
        return [
            'ok' => true,
            'message' => 'Ya existe un borrador para este grupo.',
            'id' => $existente,
            'division' => grupo_division_obtener($pdo, $idPlantel, $existente),
        ];
    }

    $alumnos = grupo_division_alumnos($pdo, $idGrupo, $idPlantel);
    if (count($alumnos) < 2) {
        return ['ok' => false, 'message' => 'Se requieren al menos dos alumnos activos para dividir el grupo.'];
    }
    $mitad = (int) ceil(count($alumnos) / 2);
    $original = array_map('intval', array_column(array_slice($alumnos, 0, $mitad), 'id_alumno'));
    $nuevo = array_map('intval', array_column(array_slice($alumnos, $mitad), 'id_alumno'));

    $stGrupo = $pdo->prepare('SELECT * FROM grupos WHERE id_grupo = ? AND id_plantel = ? LIMIT 1');
    $stGrupo->execute([$idGrupo, $idPlantel]);
    $grupoOrigen = $stGrupo->fetch(PDO::FETCH_ASSOC);
    if (!$grupoOrigen) {
        return ['ok' => false, 'message' => 'Grupo origen no encontrado.'];
    }
    // Reservar la clave antes de la transacción: los helpers de claves aseguran su propio esquema.
    $claveNueva = grupo_division_generar_clave($pdo, $idPlantel, $grupoOrigen);

    $pdo->beginTransaction();
    try {
        $grupoNuevo = grupo_division_clonar_grupo($pdo, $idPlantel, $idGrupo, $claveNueva);
        $pdo->prepare(
            'INSERT INTO grupo_division (
                id_plantel, id_grupo_origen, id_grupo_nuevo,
                asignacion_original_json, asignacion_nuevo_json, creado_por
             ) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $idPlantel,
            $idGrupo,
            $grupoNuevo['id_grupo'],
            json_encode($original, JSON_UNESCAPED_UNICODE),
            json_encode($nuevo, JSON_UNESCAPED_UNICODE),
            $idUsuario > 0 ? $idUsuario : null,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();

        return [
            'ok' => true,
            'message' => count($alumnos) > GRUPO_DIVISION_UMBRAL
                ? 'Borrador creado por edad.'
                : 'Borrador creado. Confirme que desea dividir un grupo de 15 alumnos o menos.',
            'id' => $id,
            'division' => grupo_division_obtener($pdo, $idPlantel, $id),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('grupo_division_proponer: ' . $e->getMessage());

        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

/** @return array<string, mixed>|null */
function grupo_division_obtener(PDO $pdo, int $idPlantel, int $id): ?array
{
    grupo_division_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT d.*, go.clave AS clave_original, gn.clave AS clave_nueva
         FROM grupo_division d
         INNER JOIN grupos go ON go.id_grupo = d.id_grupo_origen
         INNER JOIN grupos gn ON gn.id_grupo = d.id_grupo_nuevo
         WHERE d.id = ? AND d.id_plantel = ? LIMIT 1'
    );
    $st->execute([$id, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['asignacion_original'] = array_map('intval', json_decode((string) $row['asignacion_original_json'], true) ?: []);
    $row['asignacion_nuevo'] = array_map('intval', json_decode((string) $row['asignacion_nuevo_json'], true) ?: []);
    $alumnos = grupo_division_alumnos($pdo, (int) $row['id_grupo_origen'], $idPlantel);
    $porId = [];
    foreach ($alumnos as $alumno) {
        $porId[(int) $alumno['id_alumno']] = $alumno;
    }
    foreach (array_unique(array_merge($row['asignacion_original'], $row['asignacion_nuevo'])) as $idAlumno) {
        if (isset($porId[$idAlumno])) {
            continue;
        }
        $stA = $pdo->prepare(
            'SELECT id_alumno, numero_control, fecha_nacimiento, edad AS edad_calculada,
                    TRIM(CONCAT(COALESCE(NULLIF(nombres, \'\'), nombre, \'\'), \' \',
                                COALESCE(apellido_paterno, apellido, \'\'))) AS alumno
             FROM alumnos WHERE id_alumno = ? LIMIT 1'
        );
        $stA->execute([$idAlumno]);
        $a = $stA->fetch(PDO::FETCH_ASSOC);
        if ($a) {
            $porId[$idAlumno] = $a;
        }
    }
    $row['alumnos'] = array_values($porId);

    return $row;
}

/** @return list<array<string, mixed>> */
function grupo_division_listar_borradores(PDO $pdo, int $idPlantel): array
{
    grupo_division_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT d.id, d.estado, d.creado_en, go.clave AS clave_original, gn.clave AS clave_nueva,
                JSON_LENGTH(d.asignacion_original_json) AS total_original,
                JSON_LENGTH(d.asignacion_nuevo_json) AS total_nuevo
         FROM grupo_division d
         INNER JOIN grupos go ON go.id_grupo = d.id_grupo_origen
         INNER JOIN grupos gn ON gn.id_grupo = d.id_grupo_nuevo
         WHERE d.id_plantel = ? AND d.estado = \'borrador\'
         ORDER BY d.creado_en DESC'
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{ok:bool,message:string} */
function grupo_division_confirmar(
    PDO $pdo,
    int $idPlantel,
    int $idDivision,
    array $idsOriginal,
    array $idsNuevo,
    int $idUsuario
): array {
    if (!grupo_division_puede()) {
        return ['ok' => false, 'message' => 'Sin permiso para confirmar divisiones.'];
    }
    grupo_division_ensure_schema($pdo);
    $idsOriginal = array_values(array_unique(array_filter(array_map('intval', $idsOriginal), static fn ($id) => $id > 0)));
    $idsNuevo = array_values(array_unique(array_filter(array_map('intval', $idsNuevo), static fn ($id) => $id > 0)));
    if ($idsOriginal === [] || $idsNuevo === [] || array_intersect($idsOriginal, $idsNuevo) !== []) {
        return ['ok' => false, 'message' => 'Ambos grupos deben tener alumnos y no puede haber duplicados.'];
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM grupo_division WHERE id = ? AND id_plantel = ? FOR UPDATE');
        $st->execute([$idDivision, $idPlantel]);
        $division = $st->fetch(PDO::FETCH_ASSOC);
        if (!$division || $division['estado'] !== 'borrador') {
            throw new RuntimeException('El borrador no existe o ya fue confirmado.');
        }
        $idOriginal = (int) $division['id_grupo_origen'];
        $idNuevo = (int) $division['id_grupo_nuevo'];
        $stActuales = $pdo->prepare(
            'SELECT ag.id_alumno
             FROM alumno_grupos ag
             INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno AND a.id_plantel = ?
             WHERE ag.id_grupo = ? AND ag.activo = 1
             FOR UPDATE'
        );
        $stActuales->execute([$idPlantel, $idOriginal]);
        $actuales = array_map('intval', $stActuales->fetchAll(PDO::FETCH_COLUMN));
        $recibidos = array_values(array_unique(array_merge($idsOriginal, $idsNuevo)));
        sort($actuales);
        sort($recibidos);
        if ($actuales !== $recibidos) {
            throw new RuntimeException('La lista activa del grupo cambió. Genere una propuesta nueva antes de confirmar.');
        }

        $marks = implode(',', array_fill(0, count($idsNuevo), '?'));
        $pdo->prepare(
            "UPDATE alumno_grupos
             SET activo = 0, fecha_baja = COALESCE(fecha_baja, CURDATE())
             WHERE id_grupo = ? AND activo = 1 AND id_alumno IN ($marks)"
        )->execute(array_merge([$idOriginal], $idsNuevo));

        $ins = $pdo->prepare(
            'INSERT INTO alumno_grupos (id_alumno, id_grupo, activo, fecha_inicio, fecha_baja)
             VALUES (?, ?, 1, CURDATE(), NULL)
             ON DUPLICATE KEY UPDATE activo = 1, fecha_inicio = CURDATE(), fecha_baja = NULL'
        );
        foreach ($idsNuevo as $idAlumno) {
            $ins->execute([$idAlumno, $idNuevo]);
        }
        if (plantel_column_exists($pdo, 'alumnos', 'id_grupo')) {
            $pdo->prepare(
                "UPDATE alumnos SET id_grupo = ? WHERE id_alumno IN ($marks)"
            )->execute(array_merge([$idNuevo], $idsNuevo));
        }

        $pdo->prepare(
            'UPDATE grupo_division
             SET asignacion_original_json = ?, asignacion_nuevo_json = ?,
                 estado = \'confirmado\', confirmado_por = ?, confirmado_en = NOW()
             WHERE id = ?'
        )->execute([
            json_encode($idsOriginal, JSON_UNESCAPED_UNICODE),
            json_encode($idsNuevo, JSON_UNESCAPED_UNICODE),
            $idUsuario > 0 ? $idUsuario : null,
            $idDivision,
        ]);
        $pdo->commit();

        return ['ok' => true, 'message' => 'División confirmada y asignaciones actualizadas.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('grupo_division_confirmar: ' . $e->getMessage());

        return ['ok' => false, 'message' => $e->getMessage()];
    }
}
