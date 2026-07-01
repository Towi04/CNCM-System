<?php

/**
 * Sistema HAY genérico: rúbrica configurable, evaluación mensual, niveles y matriz.
 */

function hay_eval_puede_configurar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('hay_eval_configurar')) {
        return true;
    }
    return rbac_rol_efectivo() === 'supervisor' || rbac_rol_real() === 'supervisor';
}

function hay_eval_puede_gestionar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('hay_eval_gestionar')) {
        return true;
    }
    return in_array(rbac_rol_efectivo(), ['coordinador', 'director', 'gerente', 'supervisor'], true);
}

function hay_eval_puede_matriz_marcar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('hay_matriz_marcar')) {
        return true;
    }
    return hay_eval_puede_gestionar();
}

function hay_eval_rubros_default(): array
{
    return [
        ['clave' => 'know_how', 'titulo' => 'Know-how', 'orden' => 10],
        ['clave' => 'accountability', 'titulo' => 'Accountability', 'orden' => 20],
        ['clave' => 'problem_solving', 'titulo' => 'Problem Solving', 'orden' => 30],
        ['clave' => 'environment', 'titulo' => 'Environment', 'orden' => 40],
    ];
}

function hay_eval_tablas_listas(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM hay_area LIMIT 1');

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function hay_eval_ensure_schema(PDO $pdo): void
{
    if (function_exists('hay_schema_ddl_habilitado') && !hay_schema_ddl_habilitado($pdo)) {
        if (hay_eval_tablas_listas($pdo)) {
            return;
        }
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_area (
            id_area INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clave VARCHAR(40) NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            descripcion VARCHAR(255) NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_area),
            UNIQUE KEY uq_hay_area_clave (clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_area_rol (
            id_area INT UNSIGNED NOT NULL,
            rol_clave VARCHAR(40) NOT NULL,
            PRIMARY KEY (id_area, rol_clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_area_usuario (
            id_usuario INT UNSIGNED NOT NULL,
            id_area INT UNSIGNED NOT NULL,
            PRIMARY KEY (id_usuario),
            KEY idx_hay_au_area (id_area)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_rubro (
            id_rubro INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_area INT UNSIGNED NOT NULL,
            clave VARCHAR(40) NOT NULL,
            titulo VARCHAR(120) NOT NULL,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_rubro),
            UNIQUE KEY uq_hay_rubro_area (id_area, clave),
            KEY idx_hay_rubro_area (id_area)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_aspecto (
            id_aspecto INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_rubro INT UNSIGNED NOT NULL,
            codigo VARCHAR(60) NOT NULL,
            nombre VARCHAR(160) NOT NULL,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            origen_default ENUM(\'manual\',\'moodle\',\'sistema\') NOT NULL DEFAULT \'manual\',
            regla_auto JSON NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_aspecto),
            UNIQUE KEY uq_hay_aspecto_rubro (id_rubro, codigo),
            KEY idx_hay_aspecto_rubro (id_rubro)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_opcion (
            id_opcion INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_aspecto INT UNSIGNED NOT NULL,
            etiqueta VARCHAR(200) NOT NULL,
            puntos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            origen ENUM(\'manual\',\'moodle\',\'sistema\') NOT NULL DEFAULT \'manual\',
            moodle_course_id INT UNSIGNED NULL,
            moodle_activity_id INT UNSIGNED NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_opcion),
            KEY idx_hay_opcion_aspecto (id_aspecto)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_config_version (
            id_version INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_area INT UNSIGNED NOT NULL,
            numero INT UNSIGNED NOT NULL DEFAULT 1,
            publicada TINYINT(1) NOT NULL DEFAULT 0,
            vigente_desde DATE NULL,
            snapshot_json LONGTEXT NULL,
            publicada_en DATETIME NULL,
            id_usuario INT UNSIGNED NULL,
            PRIMARY KEY (id_version),
            KEY idx_hay_cv_area (id_area, publicada)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_eval_periodo (
            id_eval INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT UNSIGNED NOT NULL,
            id_plantel INT UNSIGNED NOT NULL,
            id_area INT UNSIGNED NOT NULL,
            anio SMALLINT UNSIGNED NOT NULL,
            mes TINYINT UNSIGNED NOT NULL,
            estado ENUM(\'borrador\',\'cerrado\') NOT NULL DEFAULT \'borrador\',
            puntos_total INT UNSIGNED NOT NULL DEFAULT 0,
            id_nivel_resultado INT UNSIGNED NULL,
            observaciones TEXT NULL,
            evaluado_por INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_eval),
            UNIQUE KEY uq_hay_eval_periodo (id_usuario, id_plantel, id_area, anio, mes),
            KEY idx_hay_eval_plantel (id_plantel, anio, mes)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_eval_respuesta (
            id_respuesta INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_eval INT UNSIGNED NOT NULL,
            id_aspecto INT UNSIGNED NOT NULL,
            id_opcion INT UNSIGNED NULL,
            puntos_aplicados SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            es_automatico TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id_respuesta),
            UNIQUE KEY uq_hay_eval_resp (id_eval, id_aspecto),
            KEY idx_hay_er_eval (id_eval)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_nivel_cargo (
            id_nivel INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_area INT UNSIGNED NOT NULL,
            numero TINYINT UNSIGNED NOT NULL,
            nombre_display VARCHAR(80) NOT NULL,
            puntos_min INT UNSIGNED NOT NULL DEFAULT 0,
            puntos_max INT UNSIGNED NOT NULL DEFAULT 0,
            sueldo_base DECIMAL(12,2) NULL,
            notas_comision VARCHAR(255) NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_nivel),
            UNIQUE KEY uq_hay_nivel_area (id_area, numero),
            KEY idx_hay_nivel_area (id_area)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_capacitacion (
            id_capacitacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_area INT UNSIGNED NOT NULL,
            id_nivel_min TINYINT UNSIGNED NULL,
            nombre VARCHAR(160) NOT NULL,
            descripcion VARCHAR(255) NULL,
            tipo ENUM(\'obligatoria_nivel\',\'mensual_extra\') NOT NULL DEFAULT \'obligatoria_nivel\',
            obligatoria TINYINT(1) NOT NULL DEFAULT 1,
            moodle_course_id INT UNSIGNED NULL,
            orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_capacitacion),
            KEY idx_hay_cap_area (id_area)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_capacitacion_cumplimiento (
            id_cumplimiento INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT UNSIGNED NOT NULL,
            id_capacitacion INT UNSIGNED NOT NULL,
            periodo CHAR(7) NOT NULL,
            completada TINYINT(1) NOT NULL DEFAULT 0,
            marcado_por INT UNSIGNED NULL,
            marcado_en DATETIME NULL,
            notas VARCHAR(255) NULL,
            PRIMARY KEY (id_cumplimiento),
            UNIQUE KEY uq_hay_cap_cumpl (id_usuario, id_capacitacion, periodo),
            KEY idx_hay_cc_usuario (id_usuario, periodo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    plantel_ensure_column($pdo, 'usuarios', 'id_hay_area', 'INT UNSIGNED NULL', 'rol');
    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column(
            $pdo,
            'hay_aspecto',
            'origen_default',
            "ENUM('manual','moodle','sistema') NOT NULL DEFAULT 'manual'",
            'orden'
        );
        plantel_ensure_column($pdo, 'hay_aspecto', 'regla_auto', 'JSON NULL', 'origen_default');
        plantel_ensure_column($pdo, 'hay_aspecto', 'activo', 'TINYINT(1) NOT NULL DEFAULT 1', 'regla_auto');
    }
    if (hay_meta_get($pdo, 'hay_area_asesor_ready') !== '1') {
        hay_eval_asegurar_area_asesor($pdo);
        if (hay_eval_area_por_clave($pdo, 'ASESOR_VENTAS')) {
            hay_meta_set($pdo, 'hay_area_asesor_ready', '1');
        }
    }
    if (function_exists('hay_eval_migrate_multi_area')) {
        hay_eval_migrate_multi_area($pdo);
    }
}

/**
 * Crea área HAY para asesores de ventas (rol asesor) si no existe.
 */
function hay_eval_asegurar_area_asesor(PDO $pdo): void
{
    if (hay_eval_area_por_clave($pdo, 'ASESOR_VENTAS')) {
        return;
    }
    $res = hay_eval_guardar_area($pdo, [
        'clave' => 'ASESOR_VENTAS',
        'nombre' => 'Asesor de ventas',
        'descripcion' => 'Evaluación y capacitación HAY para asesores comerciales',
        'roles' => ['asesor'],
    ]);
    if (!$res['ok']) {
        return;
    }
    $idArea = (int) $res['id_area'];
    $nivelesExcel = [
        ['numero' => 1, 'nombre' => 'Nivel A', 'min' => 1001, 'max' => 2000],
        ['numero' => 2, 'nombre' => 'Nivel B', 'min' => 2001, 'max' => 3000],
        ['numero' => 3, 'nombre' => 'Nivel C', 'min' => 3001, 'max' => 4000],
        ['numero' => 4, 'nombre' => 'Nivel D', 'min' => 4001, 'max' => 5000],
        ['numero' => 5, 'nombre' => 'Nivel E', 'min' => 5001, 'max' => 6000],
    ];
    foreach ($nivelesExcel as $nv) {
        hay_eval_guardar_nivel($pdo, [
            'id_area' => $idArea,
            'numero' => $nv['numero'],
            'nombre_display' => $nv['nombre'],
            'puntos_min' => $nv['min'],
            'puntos_max' => $nv['max'],
        ]);
    }
}

/**
 * Resumen para portal del colaborador: nivel actual, meta siguiente y capacitaciones pendientes.
 *
 * @return array<string, mixed>
 */
function hay_eval_resumen_portal_colaborador(PDO $pdo, int $idUsuario, ?int $idArea = null): array
{
    $idArea = $idArea > 0 ? $idArea : hay_eval_area_usuario($pdo, $idUsuario);
    if (!$idArea) {
        return ['ok' => false, 'message' => 'Sin área HAY asignada'];
    }

    $stArea = $pdo->prepare('SELECT nombre FROM hay_area WHERE id_area = ? LIMIT 1');
    $stArea->execute([$idArea]);
    $areaNombre = (string) ($stArea->fetchColumn() ?: '');

    $niveles = hay_eval_listar_niveles($pdo, $idArea);
    $periodos = hay_eval_listar_periodos_usuario($pdo, $idUsuario, $idArea);
    $ultimo = $periodos[0] ?? null;
    $puntosActuales = $ultimo ? (int) ($ultimo['puntos_total'] ?? 0) : 0;
    $nivelActual = $ultimo['nivel_nombre'] ?? null;

    if (function_exists('profesor_eval_puntos_hay_globales')) {
        $idPlantel = (int) ($_SESSION['plantel_id'] ?? 0);
        if ($idPlantel <= 0 && function_exists('plantel_scope_id')) {
            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                $idPlantel = plantel_scope_id($pdo);
            }
        }
        $eval360 = $idPlantel > 0 ? profesor_eval_puntos_hay_globales($pdo, $idUsuario, $idPlantel) : null;
        if ($eval360 && (int) ($eval360['puntos_total'] ?? 0) > $puntosActuales) {
            $puntosActuales = (int) $eval360['puntos_total'];
            $nivelActual = $eval360['nivel'] ?? $nivelActual;
        }
    }
    $nivelNum = 0;
    $siguienteNivel = null;
    $puntosFaltan = null;

    foreach ($niveles as $nv) {
        $pmin = (int) $nv['puntos_min'];
        $pmax = (int) $nv['puntos_max'];
        if ($puntosActuales >= $pmin && $puntosActuales <= $pmax) {
            $nivelNum = (int) $nv['numero'];
            $nivelActual = $nv['nombre_display'];
        }
        if ($puntosActuales < $pmin && $siguienteNivel === null) {
            $siguienteNivel = $nv;
            $puntosFaltan = max(0, $pmin - $puntosActuales);
            break;
        }
    }
    if ($siguienteNivel === null && $niveles !== []) {
        $ult = $niveles[count($niveles) - 1];
        if ($puntosActuales > (int) $ult['puntos_max']) {
            $nivelNum = (int) $ult['numero'];
            $nivelActual = $ult['nombre_display'];
        }
    }

    $matriz = hay_eval_matriz_usuario($pdo, $idUsuario, null, $idArea);
    $pendientes = 0;
    foreach ($matriz['capacitaciones'] ?? [] as $cap) {
        if ((int) ($cap['obligatoria'] ?? 0) && !(int) ($cap['completada'] ?? 0)) {
            $pendientes++;
        }
    }

    return [
        'ok' => true,
        'id_area' => $idArea,
        'area_nombre' => $areaNombre,
        'puntos_actuales' => $puntosActuales,
        'nivel_actual' => $nivelActual,
        'nivel_numero' => $nivelNum,
        'siguiente_nivel' => $siguienteNivel,
        'puntos_faltan' => $puntosFaltan,
        'niveles' => $niveles,
        'capacitaciones_pendientes' => $pendientes,
        'periodos' => $periodos,
        'matriz' => $matriz,
    ];
}

function hay_eval_listar_areas(PDO $pdo, bool $soloActivas = true): array
{
    hay_eval_ensure_schema($pdo);
    $sql = 'SELECT * FROM hay_area';
    if ($soloActivas) {
        $sql .= ' WHERE activo = 1';
    }
    $sql .= ' ORDER BY nombre ASC';

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function hay_eval_obtener_area(PDO $pdo, int $idArea): ?array
{
    if ($idArea <= 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM hay_area WHERE id_area = ? LIMIT 1');
    $st->execute([$idArea]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hay_eval_area_por_clave(PDO $pdo, string $clave): ?array
{
    $st = $pdo->prepare('SELECT * FROM hay_area WHERE clave = ? LIMIT 1');
    $st->execute([$clave]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hay_eval_guardar_area(PDO $pdo, array $data): array
{
    hay_eval_ensure_schema($pdo);
    $id = (int) ($data['id_area'] ?? 0);
    $clave = catalog_normalizar_clave((string) ($data['clave'] ?? ''), 40);
    $nombre = trim((string) ($data['nombre'] ?? ''));
    if ($clave === '' || $nombre === '') {
        return ['ok' => false, 'message' => 'Clave y nombre son obligatorios'];
    }

    if ($id > 0) {
        $moodleEx = (int) ($data['moodle_course_examen_id'] ?? 0) ?: null;
        $alias = trim((string) ($data['alias_especialidad'] ?? '')) ?: null;
        $pdo->prepare(
            'UPDATE hay_area SET clave=?, nombre=?, descripcion=?, activo=?, moodle_course_examen_id=?, alias_especialidad=? WHERE id_area=?'
        )->execute([
            $clave,
            $nombre,
            trim((string) ($data['descripcion'] ?? '')) ?: null,
            !empty($data['activo']) ? 1 : 0,
            $moodleEx,
            $alias,
            $id,
        ]);
    } else {
        $pdo->prepare('INSERT INTO hay_area (clave, nombre, descripcion, activo) VALUES (?,?,?,1)')
            ->execute([$clave, $nombre, trim((string) ($data['descripcion'] ?? '')) ?: null]);
        $id = (int) $pdo->lastInsertId();
        hay_eval_crear_rubros_default($pdo, $id);
    }

    $roles = $data['roles'] ?? [];
    if (is_array($roles)) {
        $pdo->prepare('DELETE FROM hay_area_rol WHERE id_area = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO hay_area_rol (id_area, rol_clave) VALUES (?,?)');
        foreach ($roles as $rol) {
            $rol = trim((string) $rol);
            if ($rol !== '') {
                $ins->execute([$id, $rol]);
            }
        }
    }

    return ['ok' => true, 'id_area' => $id];
}

function hay_eval_crear_rubros_default(PDO $pdo, int $idArea): void
{
    $ins = $pdo->prepare('INSERT IGNORE INTO hay_rubro (id_area, clave, titulo, orden) VALUES (?,?,?,?)');
    foreach (hay_eval_rubros_default() as $r) {
        $ins->execute([$idArea, $r['clave'], $r['titulo'], $r['orden']]);
    }
}

function hay_eval_rubrica_completa(PDO $pdo, int $idArea): array
{
    $area = hay_eval_obtener_area($pdo, $idArea);
    if (!$area) {
        return [];
    }

    $rubros = $pdo->prepare(
        'SELECT * FROM hay_rubro WHERE id_area = ? AND activo = 1 ORDER BY orden, id_rubro'
    );
    $rubros->execute([$idArea]);
    $listaRubros = [];

    $stAsp = $pdo->prepare(
        'SELECT * FROM hay_aspecto WHERE id_rubro = ? AND activo = 1 ORDER BY orden, id_aspecto'
    );
    $stOpt = $pdo->prepare(
        'SELECT * FROM hay_opcion WHERE id_aspecto = ? AND activo = 1 ORDER BY orden, puntos DESC, id_opcion'
    );

    foreach ($rubros->fetchAll(PDO::FETCH_ASSOC) as $rub) {
        $stAsp->execute([(int) $rub['id_rubro']]);
        $aspectos = [];
        foreach ($stAsp->fetchAll(PDO::FETCH_ASSOC) as $asp) {
            $stOpt->execute([(int) $asp['id_aspecto']]);
            $aspectos[] = $asp + ['opciones' => $stOpt->fetchAll(PDO::FETCH_ASSOC)];
        }
        $listaRubros[] = $rub + ['aspectos' => $aspectos];
    }

    $roles = $pdo->prepare('SELECT rol_clave FROM hay_area_rol WHERE id_area = ?');
    $roles->execute([$idArea]);

    return [
        'area' => $area,
        'rubros' => $listaRubros,
        'roles' => array_column($roles->fetchAll(PDO::FETCH_ASSOC), 'rol_clave'),
    ];
}

function hay_eval_guardar_rubro(PDO $pdo, array $data): array
{
    $idArea = (int) ($data['id_area'] ?? 0);
    $clave = trim((string) ($data['clave'] ?? ''));
    $titulo = trim((string) ($data['titulo'] ?? ''));
    if ($idArea <= 0 || $clave === '' || $titulo === '') {
        return ['ok' => false, 'message' => 'Datos incompletos'];
    }
    $id = (int) ($data['id_rubro'] ?? 0);
    $orden = (int) ($data['orden'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('UPDATE hay_rubro SET titulo=?, orden=? WHERE id_rubro=? AND id_area=?')
            ->execute([$titulo, $orden, $id, $idArea]);
    } else {
        $pdo->prepare('INSERT INTO hay_rubro (id_area, clave, titulo, orden) VALUES (?,?,?,?)')
            ->execute([$idArea, $clave, $titulo, $orden]);
        $id = (int) $pdo->lastInsertId();
    }

    return ['ok' => true, 'id_rubro' => $id];
}

function hay_eval_guardar_aspecto(PDO $pdo, array $data): array
{
    hay_eval_ensure_schema($pdo);
    $idRubro = (int) ($data['id_rubro'] ?? 0);
    $codigo = catalog_normalizar_clave((string) ($data['codigo'] ?? ''), 60);
    $nombre = trim((string) ($data['nombre'] ?? ''));
    if ($idRubro <= 0 || $codigo === '' || $nombre === '') {
        return ['ok' => false, 'message' => 'Rubro, código y nombre son obligatorios'];
    }
    $origen = in_array($data['origen_default'] ?? 'manual', ['manual', 'moodle', 'sistema'], true)
        ? $data['origen_default'] : 'manual';
    $id = (int) ($data['id_aspecto'] ?? 0);
    $orden = (int) ($data['orden'] ?? 0);
    try {
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE hay_aspecto SET codigo=?, nombre=?, orden=?, origen_default=? WHERE id_aspecto=?'
            )->execute([$codigo, $nombre, $orden, $origen, $id]);
        } else {
            $pdo->prepare(
                'INSERT INTO hay_aspecto (id_rubro, codigo, nombre, orden, origen_default) VALUES (?,?,?,?,?)'
            )->execute([$idRubro, $codigo, $nombre, $orden, $origen]);
            $id = (int) $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return ['ok' => false, 'message' => 'Ya existe un aspecto con ese código en el rubro seleccionado'];
        }
        throw $e;
    }

    return ['ok' => true, 'id_aspecto' => $id];
}

function hay_eval_guardar_opcion(PDO $pdo, array $data): array
{
    $idAspecto = (int) ($data['id_aspecto'] ?? 0);
    $etiqueta = trim((string) ($data['etiqueta'] ?? ''));
    $puntos = max(0, (int) ($data['puntos'] ?? 0));
    if ($idAspecto <= 0 || $etiqueta === '') {
        return ['ok' => false, 'message' => 'Aspecto y etiqueta son obligatorios'];
    }
    $origen = in_array($data['origen'] ?? 'manual', ['manual', 'moodle', 'sistema'], true)
        ? $data['origen'] : 'manual';
    $id = (int) ($data['id_opcion'] ?? 0);
    $orden = (int) ($data['orden'] ?? 0);
    $moodleCourse = (int) ($data['moodle_course_id'] ?? 0) ?: null;
    if ($id > 0) {
        $pdo->prepare(
            'UPDATE hay_opcion SET etiqueta=?, puntos=?, orden=?, origen=?, moodle_course_id=? WHERE id_opcion=?'
        )->execute([$etiqueta, $puntos, $orden, $origen, $moodleCourse, $id]);
    } else {
        $pdo->prepare(
            'INSERT INTO hay_opcion (id_aspecto, etiqueta, puntos, orden, origen, moodle_course_id) VALUES (?,?,?,?,?,?)'
        )->execute([$idAspecto, $etiqueta, $puntos, $orden, $origen, $moodleCourse]);
        $id = (int) $pdo->lastInsertId();
    }

    return ['ok' => true, 'id_opcion' => $id];
}

function hay_eval_desactivar_opcion(PDO $pdo, int $idOpcion): bool
{
    $pdo->prepare('UPDATE hay_opcion SET activo = 0 WHERE id_opcion = ?')->execute([$idOpcion]);

    return true;
}

function hay_eval_desactivar_aspecto(PDO $pdo, int $idAspecto): bool
{
    $pdo->prepare('UPDATE hay_opcion SET activo = 0 WHERE id_aspecto = ?')->execute([$idAspecto]);
    $pdo->prepare('UPDATE hay_aspecto SET activo = 0 WHERE id_aspecto = ?')->execute([$idAspecto]);

    return true;
}

function hay_eval_publicar_version(PDO $pdo, int $idArea, int $idUsuario): array
{
    $snap = hay_eval_rubrica_completa($pdo, $idArea);
    if (empty($snap['area'])) {
        return ['ok' => false, 'message' => 'Área no encontrada'];
    }
    $st = $pdo->prepare('SELECT COALESCE(MAX(numero),0)+1 FROM hay_config_version WHERE id_area = ?');
    $st->execute([$idArea]);
    $num = (int) $st->fetchColumn();
    $json = json_encode($snap, JSON_UNESCAPED_UNICODE);
    $pdo->prepare(
        'INSERT INTO hay_config_version (id_area, numero, publicada, vigente_desde, snapshot_json, publicada_en, id_usuario)
         VALUES (?,?,1,CURDATE(),?,NOW(),?)'
    )->execute([$idArea, $num, $json, $idUsuario > 0 ? $idUsuario : null]);
    $pdo->prepare('UPDATE hay_config_version SET publicada = 0 WHERE id_area = ? AND id_version <> ?')
        ->execute([$idArea, (int) $pdo->lastInsertId()]);

    return ['ok' => true, 'numero' => $num, 'message' => 'Rúbrica publicada (versión ' . $num . ')'];
}

function hay_eval_area_usuario(PDO $pdo, int $idUsuario, ?string $rol = null, ?int $idAreaPreferida = null): ?int
{
    hay_eval_ensure_schema($pdo);
    if ($idAreaPreferida > 0 && function_exists('hay_eval_usuario_pertenece_area')
        && hay_eval_usuario_pertenece_area($pdo, $idUsuario, $idAreaPreferida)) {
        return $idAreaPreferida;
    }
    if (function_exists('hay_eval_area_principal')) {
        $principal = hay_eval_area_principal($pdo, $idUsuario);
        if ($principal) {
            return $principal;
        }
    }
    $st = $pdo->prepare('SELECT id_hay_area FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$idUsuario]);
    $direct = (int) $st->fetchColumn();
    if ($direct > 0) {
        return $direct;
    }
    $rol = $rol ?? (function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? ''));
    if ($rol === '') {
        return null;
    }
    $q = $pdo->prepare(
        'SELECT ar.id_area FROM hay_area_rol ar
         INNER JOIN hay_area a ON a.id_area = ar.id_area AND a.activo = 1
         WHERE ar.rol_clave = ? LIMIT 1'
    );
    $q->execute([$rol]);

    return (int) $q->fetchColumn() ?: null;
}

function hay_eval_listar_colaboradores_area(PDO $pdo, int $idArea, int $idPlantel): array
{
    $roles = $pdo->prepare('SELECT rol_clave FROM hay_area_rol WHERE id_area = ?');
    $roles->execute([$idArea]);
    $roleList = array_column($roles->fetchAll(PDO::FETCH_ASSOC), 'rol_clave');
    if (!$roleList) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($roleList), '?'));
    $sql = "SELECT DISTINCT u.id_usuario, u.nombre, u.apellido, u.rol, u.email,
                   CONCAT(u.nombre,' ',u.apellido) AS nombre_completo,
                   au.id_area AS area_override
            FROM usuarios u
            LEFT JOIN hay_area_usuario au ON au.id_usuario = u.id_usuario AND au.id_area = ?
            WHERE COALESCE(u.suspendido, 0) = 0 AND u.id_plantel = ?
              AND (u.rol IN ($placeholders) OR au.id_area IS NOT NULL)
            ORDER BY u.apellido, u.nombre";
    $params = array_merge([$idArea, $idPlantel], $roleList);
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hay_eval_obtener_periodo(PDO $pdo, int $idEval): ?array
{
    $st = $pdo->prepare('SELECT * FROM hay_eval_periodo WHERE id_eval = ? LIMIT 1');
    $st->execute([$idEval]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hay_eval_obtener_o_crear_periodo(
    PDO $pdo,
    int $idUsuario,
    int $idPlantel,
    int $idArea,
    int $anio,
    int $mes
): array {
    $st = $pdo->prepare(
        'SELECT * FROM hay_eval_periodo
         WHERE id_usuario=? AND id_plantel=? AND id_area=? AND anio=? AND mes=? LIMIT 1'
    );
    $st->execute([$idUsuario, $idPlantel, $idArea, $anio, $mes]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }
    $pdo->prepare(
        'INSERT INTO hay_eval_periodo (id_usuario, id_plantel, id_area, anio, mes, estado)
         VALUES (?,?,?,?,?,\'borrador\')'
    )->execute([$idUsuario, $idPlantel, $idArea, $anio, $mes]);

    return hay_eval_obtener_periodo($pdo, (int) $pdo->lastInsertId()) ?: [];
}

function hay_eval_cargar_respuestas(PDO $pdo, int $idEval): array
{
    $st = $pdo->prepare(
        'SELECT r.*, a.codigo, a.nombre AS aspecto_nombre, o.etiqueta
         FROM hay_eval_respuesta r
         INNER JOIN hay_aspecto a ON a.id_aspecto = r.id_aspecto
         LEFT JOIN hay_opcion o ON o.id_opcion = r.id_opcion
         WHERE r.id_eval = ?'
    );
    $st->execute([$idEval]);
    $map = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int) $row['id_aspecto']] = $row;
    }

    return $map;
}

function hay_eval_guardar_respuestas(PDO $pdo, int $idEval, array $respuestas): array
{
    $eval = hay_eval_obtener_periodo($pdo, $idEval);
    if (!$eval) {
        return ['ok' => false, 'message' => 'Evaluación no encontrada'];
    }
    if (($eval['estado'] ?? '') === 'cerrado') {
        return ['ok' => false, 'message' => 'El periodo está cerrado'];
    }

    $total = 0;
    $del = $pdo->prepare('DELETE FROM hay_eval_respuesta WHERE id_eval = ?');
    $del->execute([$idEval]);
    $ins = $pdo->prepare(
        'INSERT INTO hay_eval_respuesta (id_eval, id_aspecto, id_opcion, puntos_aplicados, es_automatico)
         VALUES (?,?,?,?,0)'
    );

    foreach ($respuestas as $idAspecto => $idOpcion) {
        $idAspecto = (int) $idAspecto;
        $idOpcion = (int) $idOpcion;
        if ($idAspecto <= 0 || $idOpcion <= 0) {
            continue;
        }
        $opt = $pdo->prepare('SELECT puntos FROM hay_opcion WHERE id_opcion = ? AND id_aspecto = ? LIMIT 1');
        $opt->execute([$idOpcion, $idAspecto]);
        $pts = (int) $opt->fetchColumn();
        $ins->execute([$idEval, $idAspecto, $idOpcion, $pts]);
        $total += $pts;
    }

    $pdo->prepare('UPDATE hay_eval_periodo SET puntos_total = ? WHERE id_eval = ?')
        ->execute([$total, $idEval]);

    return ['ok' => true, 'puntos_total' => $total];
}

function hay_eval_nivel_desde_puntos(PDO $pdo, int $idArea, int $puntos): ?array
{
    $st = $pdo->prepare(
        'SELECT * FROM hay_nivel_cargo
         WHERE id_area = ? AND activo = 1 AND puntos_min <= ? AND puntos_max >= ?
         ORDER BY numero ASC LIMIT 1'
    );
    $st->execute([$idArea, $puntos, $puntos]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }
    $st2 = $pdo->prepare(
        'SELECT * FROM hay_nivel_cargo WHERE id_area = ? AND activo = 1 ORDER BY puntos_max DESC LIMIT 1'
    );
    $st2->execute([$idArea]);

    return $st2->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hay_eval_cerrar_periodo(PDO $pdo, int $idEval, int $idEvaluador, ?string $observaciones = null): array
{
    $eval = hay_eval_obtener_periodo($pdo, $idEval);
    if (!$eval) {
        return ['ok' => false, 'message' => 'Evaluación no encontrada'];
    }
    if (($eval['estado'] ?? '') === 'cerrado') {
        return ['ok' => false, 'message' => 'Ya estaba cerrada'];
    }

    $rubrica = hay_eval_rubrica_completa($pdo, (int) $eval['id_area']);
    $aspectosReq = 0;
    $resp = hay_eval_cargar_respuestas($pdo, $idEval);
    foreach ($rubrica['rubros'] as $rub) {
        foreach ($rub['aspectos'] as $asp) {
            $aspectosReq++;
            if (empty($resp[(int) $asp['id_aspecto']]['id_opcion'])) {
                return [
                    'ok' => false,
                    'message' => 'Falta calificar: ' . ($asp['nombre'] ?? $asp['codigo']),
                ];
            }
        }
    }

    $puntos = (int) ($eval['puntos_total'] ?? 0);
    $nivel = hay_eval_nivel_desde_puntos($pdo, (int) $eval['id_area'], $puntos);
    $idNivel = $nivel ? (int) $nivel['id_nivel'] : null;

    $pdo->prepare(
        'UPDATE hay_eval_periodo SET estado=\'cerrado\', evaluado_por=?, observaciones=?, id_nivel_resultado=?
         WHERE id_eval=?'
    )->execute([
        $idEvaluador > 0 ? $idEvaluador : null,
        $observaciones,
        $idNivel,
        $idEval,
    ]);

    return [
        'ok' => true,
        'puntos_total' => $puntos,
        'nivel' => $nivel,
        'message' => 'Evaluación cerrada',
    ];
}

function hay_eval_listar_periodos_usuario(PDO $pdo, int $idUsuario, ?int $idArea = null): array
{
    $sql = 'SELECT e.*, a.nombre AS area_nombre, n.nombre_display AS nivel_nombre
            FROM hay_eval_periodo e
            INNER JOIN hay_area a ON a.id_area = e.id_area
            LEFT JOIN hay_nivel_cargo n ON n.id_nivel = e.id_nivel_resultado
            WHERE e.id_usuario = ? AND e.estado = \'cerrado\'';
    $params = [$idUsuario];
    if ($idArea > 0) {
        $sql .= ' AND e.id_area = ?';
        $params[] = $idArea;
    }
    $sql .= ' ORDER BY e.anio DESC, e.mes DESC';

    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hay_eval_guardar_nivel(PDO $pdo, array $data): array
{
    $idArea = (int) ($data['id_area'] ?? 0);
    $numero = (int) ($data['numero'] ?? 0);
    $nombre = trim((string) ($data['nombre_display'] ?? ''));
    if ($idArea <= 0 || $numero < 1 || $numero > 5 || $nombre === '') {
        return ['ok' => false, 'message' => 'Área, número (1-5) y nombre son obligatorios'];
    }
    $id = (int) ($data['id_nivel'] ?? 0);
    $pmin = max(0, (int) ($data['puntos_min'] ?? 0));
    $pmax = max($pmin, (int) ($data['puntos_max'] ?? 0));
    $sueldo = isset($data['sueldo_base']) && $data['sueldo_base'] !== ''
        ? (float) $data['sueldo_base'] : null;

    if ($id > 0) {
        $pdo->prepare(
            'UPDATE hay_nivel_cargo SET numero=?, nombre_display=?, puntos_min=?, puntos_max=?, sueldo_base=?, notas_comision=?
             WHERE id_nivel=? AND id_area=?'
        )->execute([
            $numero, $nombre, $pmin, $pmax, $sueldo,
            trim((string) ($data['notas_comision'] ?? '')) ?: null,
            $id, $idArea,
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO hay_nivel_cargo (id_area, numero, nombre_display, puntos_min, puntos_max, sueldo_base, notas_comision)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $idArea, $numero, $nombre, $pmin, $pmax, $sueldo,
            trim((string) ($data['notas_comision'] ?? '')) ?: null,
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    return ['ok' => true, 'id_nivel' => $id];
}

function hay_eval_listar_niveles(PDO $pdo, int $idArea): array
{
    $st = $pdo->prepare(
        'SELECT * FROM hay_nivel_cargo WHERE id_area = ? AND activo = 1 ORDER BY numero ASC'
    );
    $st->execute([$idArea]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hay_eval_guardar_capacitacion(PDO $pdo, array $data): array
{
    $idArea = (int) ($data['id_area'] ?? 0);
    $nombre = trim((string) ($data['nombre'] ?? ''));
    if ($idArea <= 0 || $nombre === '') {
        return ['ok' => false, 'message' => 'Área y nombre son obligatorios'];
    }
    $tipo = in_array($data['tipo'] ?? '', ['obligatoria_nivel', 'mensual_extra'], true)
        ? $data['tipo'] : 'obligatoria_nivel';
    $id = (int) ($data['id_capacitacion'] ?? 0);
    $nivelMin = (int) ($data['id_nivel_min'] ?? 0) ?: null;
    $moodleCourse = (int) ($data['moodle_course_id'] ?? 0) ?: null;

    if ($id > 0) {
        $pdo->prepare(
            'UPDATE hay_capacitacion SET nombre=?, descripcion=?, tipo=?, id_nivel_min=?, obligatoria=?, moodle_course_id=?, orden=?
             WHERE id_capacitacion=?'
        )->execute([
            $nombre,
            trim((string) ($data['descripcion'] ?? '')) ?: null,
            $tipo,
            $nivelMin,
            !empty($data['obligatoria']) ? 1 : 0,
            $moodleCourse,
            (int) ($data['orden'] ?? 0),
            $id,
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO hay_capacitacion (id_area, id_nivel_min, nombre, descripcion, tipo, obligatoria, moodle_course_id, orden)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([
            $idArea, $nivelMin, $nombre,
            trim((string) ($data['descripcion'] ?? '')) ?: null,
            $tipo,
            !empty($data['obligatoria']) ? 1 : 0,
            $moodleCourse,
            (int) ($data['orden'] ?? 0),
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    return ['ok' => true, 'id_capacitacion' => $id];
}

function hay_eval_listar_capacitaciones(PDO $pdo, int $idArea, ?int $nivelNum = null): array
{
    $sql = 'SELECT * FROM hay_capacitacion WHERE id_area = ? AND activo = 1';
    $params = [$idArea];
    if ($nivelNum !== null && $nivelNum > 0) {
        $sql .= ' AND (id_nivel_min IS NULL OR id_nivel_min <= ?)';
        $params[] = $nivelNum;
    }
    $sql .= ' ORDER BY tipo, orden, nombre';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function hay_eval_marcar_capacitacion(
    PDO $pdo,
    int $idUsuario,
    int $idCapacitacion,
    string $periodo,
    bool $completada,
    int $marcadoPor,
    ?string $notas = null
): array {
    $periodo = preg_match('/^\d{4}-\d{2}$/', $periodo) ? $periodo : date('Y-m');
    $pdo->prepare(
        'INSERT INTO hay_capacitacion_cumplimiento (id_usuario, id_capacitacion, periodo, completada, marcado_por, marcado_en, notas)
         VALUES (?,?,?,?,?,NOW(),?)
         ON DUPLICATE KEY UPDATE completada=VALUES(completada), marcado_por=VALUES(marcado_por),
         marcado_en=NOW(), notas=VALUES(notas)'
    )->execute([
        $idUsuario, $idCapacitacion, $periodo, $completada ? 1 : 0,
        $marcadoPor > 0 ? $marcadoPor : null, $notas,
    ]);

    return ['ok' => true];
}

function hay_eval_matriz_usuario(PDO $pdo, int $idUsuario, ?string $periodo = null, ?int $idArea = null): array
{
    $periodo = $periodo ?? date('Y-m');
    $idArea = $idArea > 0 ? $idArea : hay_eval_area_usuario($pdo, $idUsuario);
    if (!$idArea) {
        return ['ok' => false, 'message' => 'Sin área HAY asignada', 'capacitaciones' => []];
    }

    $ultima = $pdo->prepare(
        'SELECT id_nivel_resultado, puntos_total FROM hay_eval_periodo
         WHERE id_usuario = ? AND id_area = ? AND estado = \'cerrado\'
         ORDER BY anio DESC, mes DESC LIMIT 1'
    );
    $ultima->execute([$idUsuario, $idArea]);
    $ult = $ultima->fetch(PDO::FETCH_ASSOC);
    $nivelNum = 1;
    if (!empty($ult['id_nivel_resultado'])) {
        $n = $pdo->prepare('SELECT numero FROM hay_nivel_cargo WHERE id_nivel = ?');
        $n->execute([(int) $ult['id_nivel_resultado']]);
        $nivelNum = (int) $n->fetchColumn() ?: 1;
    }

    $caps = hay_eval_listar_capacitaciones($pdo, $idArea, $nivelNum);
    $stC = $pdo->prepare(
        'SELECT id_capacitacion, completada, marcado_en, notas FROM hay_capacitacion_cumplimiento
         WHERE id_usuario = ? AND periodo = ?'
    );
    $stC->execute([$idUsuario, $periodo]);
    $cumpl = [];
    foreach ($stC->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cumpl[(int) $c['id_capacitacion']] = $c;
    }

    foreach ($caps as &$cap) {
        $id = (int) $cap['id_capacitacion'];
        $cap['completada'] = (int) ($cumpl[$id]['completada'] ?? 0);
        $cap['marcado_en'] = $cumpl[$id]['marcado_en'] ?? null;
        $cap['notas'] = $cumpl[$id]['notas'] ?? null;
    }
    unset($cap);

    return [
        'ok' => true,
        'id_area' => $idArea,
        'periodo' => $periodo,
        'nivel_actual' => $nivelNum,
        'ultima_eval' => $ult,
        'capacitaciones' => $caps,
    ];
}

/**
 * Elimina aspectos/opciones del área (solo si no hay evaluaciones guardadas).
 */
function hay_eval_wipe_rubrica_area(PDO $pdo, int $idArea): array
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM hay_eval_periodo WHERE id_area = ?');
    $st->execute([$idArea]);
    if ((int) $st->fetchColumn() > 0) {
        return [
            'ok' => false,
            'message' => 'No se puede reimportar: ya hay evaluaciones en esta área. Edite la rúbrica manualmente.',
        ];
    }

    $rubros = $pdo->prepare('SELECT id_rubro FROM hay_rubro WHERE id_area = ?');
    $rubros->execute([$idArea]);
    $idsRubro = array_map('intval', array_column($rubros->fetchAll(PDO::FETCH_ASSOC), 'id_rubro'));
    if (!$idsRubro) {
        return ['ok' => true];
    }

    $ph = implode(',', array_fill(0, count($idsRubro), '?'));
    $asp = $pdo->prepare("SELECT id_aspecto FROM hay_aspecto WHERE id_rubro IN ($ph)");
    $asp->execute($idsRubro);
    $idsAsp = array_map('intval', array_column($asp->fetchAll(PDO::FETCH_ASSOC), 'id_aspecto'));
    if ($idsAsp) {
        $pha = implode(',', array_fill(0, count($idsAsp), '?'));
        $pdo->prepare("DELETE FROM hay_opcion WHERE id_aspecto IN ($pha)")->execute($idsAsp);
        $pdo->prepare("DELETE FROM hay_aspecto WHERE id_aspecto IN ($pha)")->execute($idsAsp);
    }

    return ['ok' => true];
}

/**
 * Importa aspectos y opciones desde scripts/hay_xlsm_dump.txt (solo Profesor Inglés).
 */
function hay_eval_aplicar_rubrica_xlsm(PDO $pdo, int $idArea, array $parsed): array
{
    $rubrosDb = [];
    $stR = $pdo->prepare('SELECT id_rubro, clave FROM hay_rubro WHERE id_area = ?');
    $stR->execute([$idArea]);
    foreach ($stR->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rubrosDb[$r['clave']] = (int) $r['id_rubro'];
    }

    $ordenAsp = 0;
    $totalOpciones = 0;
    foreach ($parsed['aspects'] as $aspect) {
        $claveRubro = $aspect['rubro'] ?? 'know_how';
        $idRubro = $rubrosDb[$claveRubro] ?? null;
        if (!$idRubro) {
            continue;
        }
        $ordenAsp += 10;
        $origen = ($aspect['codigo'] ?? '') === 'MOODLE' ? 'moodle' : 'manual';
        $aspRes = hay_eval_guardar_aspecto($pdo, [
            'id_rubro' => $idRubro,
            'codigo' => $aspect['codigo'],
            'nombre' => $aspect['nombre'],
            'orden' => $ordenAsp,
            'origen_default' => $origen,
        ]);
        if (!$aspRes['ok']) {
            continue;
        }
        $idAsp = (int) $aspRes['id_aspecto'];
        foreach ($aspect['opciones'] as $op) {
            $r = hay_eval_guardar_opcion($pdo, [
                'id_aspecto' => $idAsp,
                'etiqueta' => $op['etiqueta'],
                'puntos' => (int) $op['puntos'],
                'orden' => (int) ($op['orden'] ?? 0),
                'origen' => $origen,
            ]);
            if ($r['ok']) {
                $totalOpciones++;
            }
        }
    }

    return [
        'ok' => true,
        'aspectos' => count($parsed['aspects']),
        'opciones' => $totalOpciones,
    ];
}

/**
 * Semilla área Profesor Inglés desde hay_xlsm_dump.txt (hoja Opciones a evaluar).
 * Solo aplica al área INGLÉS / rol profesor; no usar para otras áreas.
 *
 * @param bool $forzar Si el área existe, borra rúbrica vacía de evaluaciones y reimporta
 */
function hay_eval_seed_profesor_ingles(PDO $pdo, bool $forzar = false): array
{
    require_once __DIR__ . '/hay_xlsm_parser.php';

    hay_eval_ensure_schema($pdo);
    $parsed = hay_xlsm_parse_opciones_ingles();
    if (!$parsed['ok']) {
        return $parsed;
    }

    $existente = hay_eval_area_por_clave($pdo, 'PROF_INGLES');
    if ($existente) {
        $idArea = (int) $existente['id_area'];
        if (!$forzar) {
            return [
                'ok' => true,
                'message' => 'El área Profesor Inglés ya existe. Marque reimportar para cargar de nuevo desde hay_xlsm_dump.txt.',
                'id_area' => $idArea,
                'skipped' => true,
            ];
        }
        $wipe = hay_eval_wipe_rubrica_area($pdo, $idArea);
        if (!$wipe['ok']) {
            return $wipe;
        }
    } else {
        $res = hay_eval_guardar_area($pdo, [
            'clave' => 'PROF_INGLES',
            'nombre' => 'Profesor — Inglés',
            'descripcion' => 'Rúbrica importada desde matriz Excel (hay_xlsm_dump.txt). Solo inglés.',
            'roles' => ['profesor'],
        ]);
        if (!$res['ok']) {
            return $res;
        }
        $idArea = (int) $res['id_area'];
    }

    $import = hay_eval_aplicar_rubrica_xlsm($pdo, $idArea, $parsed);
    if (!$import['ok']) {
        return $import;
    }

    $stNv = $pdo->prepare('SELECT COUNT(*) FROM hay_nivel_cargo WHERE id_area = ?');
    $stNv->execute([$idArea]);
    if ((int) $stNv->fetchColumn() === 0) {
        $nivelesExcel = [
            ['numero' => 1, 'nombre' => 'Nivel A', 'min' => 1001, 'max' => 2000],
            ['numero' => 2, 'nombre' => 'Nivel B', 'min' => 2001, 'max' => 3000],
            ['numero' => 3, 'nombre' => 'Nivel C', 'min' => 3001, 'max' => 4000],
            ['numero' => 4, 'nombre' => 'Nivel D', 'min' => 4001, 'max' => 5000],
            ['numero' => 5, 'nombre' => 'Nivel E', 'min' => 5001, 'max' => 6000],
        ];
        foreach ($nivelesExcel as $nv) {
            hay_eval_guardar_nivel($pdo, [
                'id_area' => $idArea,
                'numero' => $nv['numero'],
                'nombre_display' => $nv['nombre'],
                'puntos_min' => $nv['min'],
                'puntos_max' => $nv['max'],
            ]);
        }
    }

    hay_eval_publicar_version($pdo, $idArea, (int) ($_SESSION['user_id'] ?? 0));

    $msg = sprintf(
        'Profesor Inglés: %d aspectos, %d opciones desde hay_xlsm_dump.txt',
        (int) ($import['aspectos'] ?? 0),
        (int) ($import['opciones'] ?? 0)
    );

    return [
        'ok' => true,
        'id_area' => $idArea,
        'message' => $msg,
        'reimported' => $forzar && (bool) $existente,
    ];
}

/**
 * Placeholder: sincronizar métricas automáticas (Fase 5).
 */
function hay_eval_sincronizar_metricas_auto(PDO $pdo, int $idEval): array
{
    $eval = hay_eval_obtener_periodo($pdo, $idEval);
    if (!$eval) {
        return ['ok' => false, 'message' => 'Evaluación no encontrada'];
    }
    $aplicadas = 0;
    if (function_exists('moodle_enabled') && moodle_enabled() && function_exists('hay_eval_sync_moodle_respuestas')) {
        $m = hay_eval_sync_moodle_respuestas($pdo, $idEval, (int) $eval['id_usuario']);
        if (!empty($m['ok'])) {
            $aplicadas += (int) ($m['aplicadas'] ?? 0);
        }
    }
    if (function_exists('profesor_eval_calcular_metricas_auto') && function_exists('profesor_eval_rubrica_categorias')) {
        $idU = (int) $eval['id_usuario'];
        $st = $pdo->prepare('SELECT rol FROM usuarios WHERE id_usuario = ?');
        $st->execute([$idU]);
        if (($st->fetchColumn() ?? '') === 'profesor') {
            $aplicadas += 0;
        }
    }

    return [
        'ok' => true,
        'message' => $aplicadas > 0
            ? 'Sincronización parcial (' . $aplicadas . ' aspectos)'
            : 'Configure aspectos con origen moodle/sistema o use evaluación manual',
        'aplicadas' => $aplicadas,
    ];
}

/**
 * Vincular sueldo sugerido con tabulador ventas si el área es asesor (Fase 5).
 */
function hay_eval_sueldo_sugerido_usuario(PDO $pdo, int $idUsuario, int $idArea): ?float
{
    $ultima = $pdo->prepare(
        'SELECT id_nivel_resultado FROM hay_eval_periodo
         WHERE id_usuario = ? AND id_area = ? AND estado = \'cerrado\'
         ORDER BY anio DESC, mes DESC LIMIT 1'
    );
    $ultima->execute([$idUsuario, $idArea]);
    $idNivel = (int) $ultima->fetchColumn();
    if ($idNivel <= 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT sueldo_base FROM hay_nivel_cargo WHERE id_nivel = ?');
    $st->execute([$idNivel]);
    $sb = $st->fetchColumn();

    return $sb !== false && $sb !== null ? (float) $sb : null;
}
