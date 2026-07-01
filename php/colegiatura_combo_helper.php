<?php

/**
 * Colegiaturas con descuento: combinación de especialidades o promociones (cartas, hot sale, etc.).
 */

/** Solo el supervisor gestiona estas reglas (no gerente ni director). */
function combo_puede_administrar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('admin_colegiaturas_descuento')) {
        return true;
    }

    return function_exists('rbac_rol_real')
        ? rbac_rol_real() === 'supervisor'
        : (($_SESSION['rol_real'] ?? $_SESSION['rol'] ?? '') === 'supervisor');
}

/** @return array<string, string> */
function combo_tipos_regla(): array
{
    return [
        'combinacion' => 'Combinación de especialidades',
        'promocion' => 'Promoción / campaña',
    ];
}

/** @return array<string, string> */
function combo_categorias_promocion(): array
{
    return [
        'cartas' => 'Cartas',
        'hot_sale' => 'Hot sale',
        'buen_fin' => 'Buen fin',
        'promocion' => 'Otra promoción',
    ];
}

function combo_etiqueta_regla(array $r): string
{
    if (($r['tipo'] ?? 'combinacion') === 'combinacion') {
        return 'Combinación';
    }
    $cats = combo_categorias_promocion();
    $c = $r['categoria_promo'] ?? 'promocion';

    return $cats[$c] ?? 'Promoción';
}

function combo_ensure_schema(PDO $pdo): void
{
    pago_ensure_schema($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS reglas_colegiatura_combo (
            id_regla INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre VARCHAR(160) NOT NULL,
            claves_combo VARCHAR(255) NOT NULL COMMENT "Claves ordenadas CSV ej. COMP-K,ING-K",
            min_especialidades TINYINT UNSIGNED NOT NULL DEFAULT 2,
            motivo VARCHAR(255) NULL,
            id_autoriza INT UNSIGNED NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_regla),
            KEY idx_regla_claves (claves_combo),
            KEY idx_regla_activo (activo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS regla_combo_tarifa (
            id_tarifa INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_regla INT UNSIGNED NOT NULL,
            id_especialidad INT UNSIGNED NOT NULL,
            costo_inscripcion DECIMAL(12,2) NOT NULL DEFAULT 0,
            costo_mensualidad DECIMAL(12,2) NOT NULL DEFAULT 0,
            costo_pronto_pago DECIMAL(12,2) NOT NULL DEFAULT 0,
            costo_semanal DECIMAL(12,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id_tarifa),
            UNIQUE KEY uq_regla_esp (id_regla, id_especialidad),
            KEY idx_tarifa_regla (id_regla)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    plantel_ensure_column($pdo, 'regla_combo_tarifa', 'costo_inscripcion_referencia', 'DECIMAL(12,2) NULL', 'costo_inscripcion');
    plantel_ensure_column($pdo, 'regla_combo_tarifa', 'costo_inscripcion_apoyo', 'DECIMAL(12,2) NULL', 'costo_inscripcion_referencia');
    plantel_ensure_column($pdo, 'regla_combo_tarifa', 'costo_anual', 'DECIMAL(12,2) NULL', 'costo_semanal');

    plantel_ensure_column($pdo, 'reglas_colegiatura_combo', 'tipo', "VARCHAR(24) NOT NULL DEFAULT 'combinacion'", 'motivo');
    plantel_ensure_column($pdo, 'reglas_colegiatura_combo', 'categoria_promo', 'VARCHAR(40) NULL', 'tipo');

    plantel_ensure_column($pdo, 'alumno_especialidades', 'id_regla_combo', 'INT UNSIGNED NULL', 'cuatrimestre_actual');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'base_costo_inscripcion', 'DECIMAL(12,2) NULL', 'id_regla_combo');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'base_costo_mensualidad', 'DECIMAL(12,2) NULL', 'base_costo_inscripcion');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'base_costo_pronto_pago', 'DECIMAL(12,2) NULL', 'base_costo_mensualidad');
    plantel_ensure_column($pdo, 'alumno_especialidades', 'base_costo_semanal', 'DECIMAL(12,2) NULL', 'base_costo_pronto_pago');
    plantel_ensure_column($pdo, 'alumnos', 'id_regla_colegiatura_pref', 'INT UNSIGNED NULL', 'inscripcion_kids_modo');
    plantel_ensure_column($pdo, 'alumnos', 'inscripcion_kids_modo', "VARCHAR(20) NOT NULL DEFAULT ''", 'fecha_baja_temporal');
    plantel_ensure_column($pdo, 'alumnos', 'motivo_baja_temporal', 'VARCHAR(255) NULL', 'inscripcion_kids_modo');

    try {
        combo_seed_ejemplos($pdo);
    } catch (Throwable $e) {
        error_log('combo_seed_ejemplos: ' . $e->getMessage());
    }
    try {
        combo_reparar_tarifas_infantil($pdo);
    } catch (Throwable $e) {
        error_log('combo_reparar_tarifas_infantil: ' . $e->getMessage());
    }
}

function combo_clave_especialidad(PDO $pdo, int $idEspecialidad): string
{
    if ($idEspecialidad <= 0) {
        return '';
    }
    $st = $pdo->prepare('SELECT UPPER(clave) FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $st->execute([$idEspecialidad]);

    return strtoupper(trim((string) $st->fetchColumn()));
}

/** Regla cuyo combo incluye ING-K y/o COMP-K. */
function combo_regla_es_infantil(array $regla): bool
{
    $claves = array_map(
        static fn($c) => strtoupper(trim((string) $c)),
        explode(',', (string) ($regla['claves_combo'] ?? ''))
    );

    foreach ($claves as $c) {
        if ($c === 'ING-K' || $c === 'COMP-K' || str_ends_with($c, '-K')) {
            return true;
        }
    }

    return false;
}

/**
 * Asegura filas en regla_combo_tarifa para ING-K y COMP-K en reglas infantiles.
 */
function combo_reparar_tarifas_infantil(PDO $pdo): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    $kids = combo_ids_kids($pdo);
    if ($kids['ingles'] <= 0 || $kids['computacion'] <= 0) {
        return;
    }

    $reglas = $pdo->query('SELECT * FROM reglas_colegiatura_combo WHERE activo = 1')->fetchAll(PDO::FETCH_ASSOC);
    $ins = $pdo->prepare(
        'INSERT IGNORE INTO regla_combo_tarifa (id_regla, id_especialidad, costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal)
         VALUES (?,?,?,?,?,?)'
    );

    foreach ($reglas as $regla) {
        if (!combo_regla_es_infantil($regla)) {
            continue;
        }
        $idRegla = (int) ($regla['id_regla'] ?? 0);
        if ($idRegla <= 0) {
            continue;
        }
        foreach (['ING-K' => $kids['ingles'], 'COMP-K' => $kids['computacion']] as $clave => $idEsp) {
            $chk = $pdo->prepare(
                'SELECT 1 FROM regla_combo_tarifa WHERE id_regla = ? AND id_especialidad = ? LIMIT 1'
            );
            $chk->execute([$idRegla, $idEsp]);
            if ($chk->fetchColumn()) {
                continue;
            }
            $stEsp = $pdo->prepare(
                'SELECT costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal FROM especialidades WHERE id_especialidad = ? LIMIT 1'
            );
            $stEsp->execute([$idEsp]);
            $e = $stEsp->fetch(PDO::FETCH_ASSOC);
            if (!$e) {
                continue;
            }
            $ins->execute([
                $idRegla,
                $idEsp,
                $e['costo_inscripcion'],
                $e['costo_mensualidad'],
                $e['costo_pronto_pago'],
                $e['costo_semanal'],
            ]);
        }
    }
}

/**
 * Resuelve la especialidad del alumno dentro de una regla (por id o clave kids/adulto).
 */
function combo_resolver_especialidad_en_regla(PDO $pdo, int $idRegla, int $idEspAlumno): int
{
    if ($idRegla <= 0 || $idEspAlumno <= 0) {
        return $idEspAlumno;
    }
    if (combo_tarifa_fila_regla($pdo, $idRegla, $idEspAlumno)) {
        return $idEspAlumno;
    }

    $regla = combo_regla_por_id($pdo, $idRegla);
    if (!$regla) {
        return $idEspAlumno;
    }

    $claveAlumno = combo_clave_especialidad($pdo, $idEspAlumno);
    $mapKids = [
        'ING' => 'ING-K',
        'ING-EXT' => 'ING-K-EXT',
        'COMP' => 'COMP-K',
        'COMP24' => 'COMP-K',
        'COMP25' => 'COMP-K',
    ];
    if (combo_regla_es_infantil($regla) && isset($mapKids[$claveAlumno])) {
        $st = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE UPPER(clave) = ? LIMIT 1');
        $st->execute([$mapKids[$claveAlumno]]);
        $idAlt = (int) $st->fetchColumn();
        if ($idAlt > 0 && combo_tarifa_fila_regla($pdo, $idRegla, $idAlt)) {
            return $idAlt;
        }
    }

    $especialidades = combo_especialidades_de_regla($pdo, $idRegla);
    foreach ($especialidades as $e) {
        $claveRegla = strtoupper(trim((string) ($e['clave'] ?? '')));
        if ($claveRegla !== '' && $claveRegla === $claveAlumno) {
            return (int) $e['id_especialidad'];
        }
    }

    if (combo_regla_es_infantil($regla)) {
        foreach (['ING-K', 'COMP-K'] as $claveKids) {
            if ($claveAlumno === $claveKids) {
                foreach ($especialidades as $e) {
                    if (strtoupper(trim((string) ($e['clave'] ?? ''))) === $claveKids) {
                        return (int) $e['id_especialidad'];
                    }
                }
            }
        }
        if (count($especialidades) >= 1) {
            return (int) $especialidades[0]['id_especialidad'];
        }
    }

    return $idEspAlumno;
}

function combo_normalizar_claves(array $claves): string
{
    $claves = array_values(array_unique(array_filter(array_map('trim', $claves))));
    sort($claves, SORT_STRING);
    return implode(',', $claves);
}

/** @return array<int, string> */
function combo_claves_alumno_activas(PDO $pdo, int $idAlumno): array
{
    $stmt = $pdo->prepare(
        'SELECT e.clave FROM alumno_especialidades ae
         INNER JOIN especialidades e ON e.id_especialidad = ae.id_especialidad
         WHERE ae.id_alumno = ? AND ae.activo = 1
         ORDER BY e.clave'
    );
    $stmt->execute([$idAlumno]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function combo_regla_por_id(PDO $pdo, int $idRegla): ?array
{
    if ($idRegla <= 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM reglas_colegiatura_combo WHERE id_regla = ? AND activo = 1 LIMIT 1');
    $st->execute([$idRegla]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @return array<int, array<string, mixed>> */
function combo_listar_para_inscripcion(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT id_regla, nombre, tipo, categoria_promo, motivo, claves_combo, min_especialidades
         FROM reglas_colegiatura_combo WHERE activo = 1 ORDER BY tipo ASC, nombre ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['tipo_label'] = combo_etiqueta_regla($r);
        $r['resumen'] = $r['nombre'] . ' — ' . $r['claves_combo'];
        if (!empty($r['motivo'])) {
            $r['resumen'] .= ' (' . $r['motivo'] . ')';
        }
    }
    unset($r);

    return $rows;
}

/** @return list<array{id_especialidad:int,nombre:string,clave:string}> */
function combo_especialidades_de_regla(PDO $pdo, int $idRegla): array
{
    combo_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT t.id_especialidad, e.nombre, e.clave
         FROM regla_combo_tarifa t
         INNER JOIN especialidades e ON e.id_especialidad = t.id_especialidad
         WHERE t.id_regla = ?
         ORDER BY e.nombre'
    );
    $st->execute([$idRegla]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Fila de tarifa de una regla para la especialidad del alumno (por id o por clave). */
function combo_tarifa_fila_regla(PDO $pdo, int $idRegla, int $idEspecialidad): ?array
{
    if ($idRegla <= 0 || $idEspecialidad <= 0) {
        return null;
    }
    combo_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal, id_especialidad
         FROM regla_combo_tarifa WHERE id_regla = ? AND id_especialidad = ? LIMIT 1'
    );
    $st->execute([$idRegla, $idEspecialidad]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }

    $stClave = $pdo->prepare('SELECT clave FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $stClave->execute([$idEspecialidad]);
    $clave = strtoupper(trim((string) $stClave->fetchColumn()));
    if ($clave === '') {
        return null;
    }

    $st2 = $pdo->prepare(
        'SELECT t.costo_inscripcion, t.costo_mensualidad, t.costo_pronto_pago, t.costo_semanal, t.id_especialidad
         FROM regla_combo_tarifa t
         INNER JOIN especialidades e ON e.id_especialidad = t.id_especialidad
         WHERE t.id_regla = ? AND UPPER(e.clave) = ? LIMIT 1'
    );
    $st2->execute([$idRegla, $clave]);

    return $st2->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Aplica tarifas de una regla a la especialidad del alumno (inscripción + colegiatura).
 */
function combo_aplicar_tarifa_regla_especialidad(
    PDO $pdo,
    int $idAlumno,
    int $idEspecialidad,
    int $idRegla
): bool {
    if ($idAlumno <= 0 || $idEspecialidad <= 0 || $idRegla <= 0) {
        return false;
    }
    $tarifa = combo_tarifa_fila_regla($pdo, $idRegla, $idEspecialidad);
    if (!$tarifa) {
        return false;
    }
    $st = $pdo->prepare(
        'SELECT id_alumno_especialidad FROM alumno_especialidades
         WHERE id_alumno = ? AND id_especialidad = ? AND activo = 1
         ORDER BY id_alumno_especialidad DESC LIMIT 1'
    );
    $st->execute([$idAlumno, $idEspecialidad]);
    $idAe = (int) $st->fetchColumn();
    if ($idAe <= 0) {
        return false;
    }
    combo_respaldar_tarifas_base($pdo, $idAe);
    $pdo->prepare(
        'UPDATE alumno_especialidades SET
            costo_inscripcion = ?, costo_mensualidad = ?, costo_pronto_pago = ?, costo_semanal = ?,
            id_regla_combo = ?
         WHERE id_alumno_especialidad = ?'
    )->execute([
        $tarifa['costo_inscripcion'],
        $tarifa['costo_mensualidad'],
        $tarifa['costo_pronto_pago'],
        $tarifa['costo_semanal'],
        $idRegla,
        $idAe,
    ]);
    if (function_exists('pago_actualizar_inscripcion_cubierta')) {
        pago_actualizar_inscripcion_cubierta($pdo, $idAlumno, $idEspecialidad, $idAe);
    }

    return true;
}

/** Totales de inscripción (referencia vs descuento) para una regla combo. */
function combo_totales_inscripcion_regla(PDO $pdo, int $idRegla, ?array $regla = null): array
{
    combo_ensure_schema($pdo);
    $regla = $regla ?? combo_regla_por_id($pdo, $idRegla);
    if (!$regla) {
        return ['referencia' => 0.0, 'apoyo' => 0.0, 'descuento' => 0.0];
    }

    $st = $pdo->prepare(
        'SELECT t.costo_inscripcion, t.costo_inscripcion_referencia, t.costo_inscripcion_apoyo,
                e.costo_inscripcion AS esp_inscripcion,
                e.costo_inscripcion_referencia AS esp_inscripcion_referencia,
                e.costo_inscripcion_apoyo AS esp_inscripcion_apoyo
         FROM regla_combo_tarifa t
         INNER JOIN especialidades e ON e.id_especialidad = t.id_especialidad
         WHERE t.id_regla = ?'
    );
    $st->execute([$idRegla]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $sumRef = 0.0;
    $sumApoyo = 0.0;
    $inscsRegla = [];

    foreach ($rows as $row) {
        $ref = (float) ($row['costo_inscripcion_referencia'] ?? 0);
        if ($ref <= 0) {
            $ref = (float) ($row['esp_inscripcion_referencia'] ?? 0);
        }
        $apoyo = (float) ($row['costo_inscripcion_apoyo'] ?? 0);
        if ($apoyo <= 0) {
            $apoyo = (float) ($row['esp_inscripcion_apoyo'] ?? $row['esp_inscripcion'] ?? 0);
        }
        if ($ref <= 0 && $apoyo > 0) {
            $ref = $apoyo * 2;
        }
        $sumRef += $ref;
        $sumApoyo += $apoyo;
        $inscsRegla[] = (float) ($row['costo_inscripcion'] ?? 0);
    }

    $descuento = array_sum($inscsRegla);
    if (combo_regla_es_infantil($regla) && count($inscsRegla) >= 2) {
        $unicos = array_unique($inscsRegla);
        if (count($unicos) === 1) {
            $descuento = (float) $inscsRegla[0];
        } elseif ($sumApoyo > 0 && $descuento > $sumApoyo) {
            $descuento = $sumApoyo;
        }
    }

    return [
        'referencia' => round($sumRef, 2),
        'apoyo' => round($sumApoyo, 2),
        'descuento' => round($descuento, 2),
    ];
}

/** Tarifa de inscripción de una regla para una especialidad. */
function combo_tarifa_inscripcion_regla(PDO $pdo, int $idRegla, int $idEspecialidad): ?float
{
    $row = combo_tarifa_fila_regla($pdo, $idRegla, $idEspecialidad);

    return $row ? (float) ($row['costo_inscripcion'] ?? 0) : null;
}

/**
 * Vista previa del resumen de inscripción con tarifa de descuento (sin aplicar en BD).
 *
 * @return array<string, mixed>
 */
function combo_resumen_descuento_preview(PDO $pdo, int $idAlumno, int $idEspecialidad, int $idRegla): array
{
    if (!function_exists('inscripcion_resumen_alumno')) {
        return ['ok' => false, 'message' => 'Módulo de inscripción no disponible'];
    }
    $idEspTarifa = combo_resolver_especialidad_en_regla($pdo, $idRegla, $idEspecialidad);
    $base = inscripcion_resumen_alumno($pdo, $idAlumno, $idEspTarifa > 0 ? $idEspTarifa : $idEspecialidad);
    if (!$base['ok']) {
        return $base;
    }
    if ($idRegla <= 0) {
        return $base;
    }

    $regla = combo_regla_por_id($pdo, $idRegla);
    if (!$regla) {
        return ['ok' => false, 'message' => 'Regla de descuento no encontrada'];
    }

    $totales = combo_totales_inscripcion_regla($pdo, $idRegla, $regla);
    $costoDesc = $totales['descuento'] > 0
        ? $totales['descuento']
        : combo_tarifa_inscripcion_regla($pdo, $idRegla, $idEspTarifa);
    if ($costoDesc === null) {
        $msg = combo_regla_es_infantil($regla)
            ? 'Esta regla infantil no tiene tarifas para ING-K/COMP-K. Revise Colegiaturas con descuento.'
            : 'Esta regla no define inscripción para la especialidad del alumno';

        return ['ok' => false, 'message' => $msg];
    }

    $costoBase = $totales['referencia'] > 0
        ? $totales['referencia']
        : (float) ($totales['apoyo'] > 0 ? $totales['apoyo'] * 2 : ($base['costo_inscripcion'] ?? 0));
    if ($costoBase <= $costoDesc) {
        $costoBase = (float) ($base['costo_inscripcion'] ?? 0);
        if ($costoBase <= $costoDesc && $totales['apoyo'] > $costoDesc) {
            $costoBase = $totales['apoyo'];
        }
    }

    $pagado = (float) ($base['pagado_inscripcion'] ?? 0);
    $saldo = max(0.0, round((float) $costoDesc - $pagado, 2));
    $creditoApartado = (float) ($base['credito_apartado_pendiente'] ?? 0);
    if ($creditoApartado > 0) {
        $saldo = max(0.0, round($saldo - $creditoApartado, 2));
    }

    return array_merge($base, [
        'costo_inscripcion' => (float) $costoDesc,
        'costo_inscripcion_base' => $costoBase,
        'costo_inscripcion_referencia' => $totales['referencia'],
        'costo_inscripcion_apoyo' => $totales['apoyo'],
        'saldo_inscripcion' => $saldo,
        'inscripcion_cubierta' => $saldo <= 0.009,
        'descuento_aplicado_preview' => true,
        'id_regla_descuento' => $idRegla,
        'regla_descuento' => (string) ($regla['nombre'] ?? ''),
        'regla_descuento_tipo' => combo_etiqueta_regla($regla),
    ]);
}

/** @return array<string, mixed>|null */
function combo_detalle_para_inscripcion(PDO $pdo, int $idRegla, int $idEspAlumno, int $idAlumno = 0): ?array
{
    $regla = combo_regla_por_id($pdo, $idRegla);
    if (!$regla) {
        return null;
    }
    $idEspResuelto = combo_resolver_especialidad_en_regla($pdo, $idRegla, $idEspAlumno);
    $especialidades = combo_especialidades_de_regla($pdo, $idRegla);
    $tipo = (string) ($regla['tipo'] ?? 'combinacion');
    $minEsp = max(1, (int) ($regla['min_especialidades'] ?? 1));
    $requiereVariosGrupos = $tipo === 'combinacion' && $minEsp >= 2 && count($especialidades) >= 2;
    $adicionales = [];
    foreach ($especialidades as $e) {
        if ((int) $e['id_especialidad'] !== $idEspResuelto) {
            $adicionales[] = $e;
        }
    }

    $tarifaActual = $idEspResuelto > 0 ? combo_tarifa_fila_regla($pdo, $idRegla, $idEspResuelto) : null;

    $resumenPreview = null;
    if ($idAlumno > 0 && $idEspResuelto > 0) {
        $prev = combo_resumen_descuento_preview($pdo, $idAlumno, $idEspResuelto, $idRegla);
        if ($prev['ok'] ?? false) {
            $resumenPreview = $prev;
        }
    }

    return [
        'regla' => $regla,
        'tipo' => $tipo,
        'tipo_label' => combo_etiqueta_regla($regla),
        'min_especialidades' => $minEsp,
        'requiere_varios_grupos' => $requiereVariosGrupos,
        'es_regla_infantil' => combo_regla_es_infantil($regla),
        'id_especialidad_resuelta' => $idEspResuelto,
        'especialidades' => $especialidades,
        'especialidades_adicionales' => $adicionales,
        'tarifa_especialidad_actual' => $tarifaActual,
        'totales_inscripcion' => combo_totales_inscripcion_regla($pdo, $idRegla, $regla),
        'resumen_preview' => $resumenPreview,
    ];
}

function combo_alumno_establecer_regla_pref(PDO $pdo, int $idAlumno, ?int $idRegla): void
{
    $pdo->prepare('UPDATE alumnos SET id_regla_colegiatura_pref = ? WHERE id_alumno = ?')
        ->execute([$idRegla > 0 ? $idRegla : null, $idAlumno]);
}

function combo_respaldar_tarifas_base(PDO $pdo, int $idAlumnoEspecialidad): void
{
    $pdo->prepare(
        'UPDATE alumno_especialidades SET
            base_costo_inscripcion = COALESCE(base_costo_inscripcion, costo_inscripcion),
            base_costo_mensualidad = COALESCE(base_costo_mensualidad, costo_mensualidad),
            base_costo_pronto_pago = COALESCE(base_costo_pronto_pago, costo_pronto_pago),
            base_costo_semanal = COALESCE(base_costo_semanal, costo_semanal)
         WHERE id_alumno_especialidad = ?'
    )->execute([$idAlumnoEspecialidad]);
}

function combo_restaurar_tarifas_sin_descuento(PDO $pdo, int $idAlumno): void
{
    $pdo->prepare(
        'UPDATE alumno_especialidades SET
            costo_inscripcion = COALESCE(base_costo_inscripcion, costo_inscripcion),
            costo_mensualidad = COALESCE(base_costo_mensualidad, costo_mensualidad),
            costo_pronto_pago = COALESCE(base_costo_pronto_pago, costo_pronto_pago),
            costo_semanal = COALESCE(base_costo_semanal, costo_semanal),
            id_regla_combo = NULL
         WHERE id_alumno = ? AND activo = 1 AND override_supervisor = 0'
    )->execute([$idAlumno]);
}

function combo_buscar_regla_aplicable(PDO $pdo, array $clavesAlumno): ?array
{
    if ($clavesAlumno === []) {
        return null;
    }
    $firmaAlumno = combo_normalizar_claves($clavesAlumno);
    $reglas = $pdo->query(
        'SELECT * FROM reglas_colegiatura_combo WHERE activo = 1 ORDER BY min_especialidades DESC, CHAR_LENGTH(claves_combo) DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reglas as $r) {
        $firmaRegla = combo_normalizar_claves(explode(',', $r['claves_combo']));
        if ($firmaRegla === '') {
            continue;
        }
        $clavesRegla = explode(',', $firmaRegla);
        $tieneTodas = true;
        foreach ($clavesRegla as $c) {
            if (!in_array($c, $clavesAlumno, true)) {
                $tieneTodas = false;
                break;
            }
        }
        if (!$tieneTodas) {
            continue;
        }
        $minReq = max(1, (int) $r['min_especialidades']);
        if (count($clavesAlumno) < $minReq) {
            continue;
        }
        if (($r['tipo'] ?? 'combinacion') === 'combinacion' && count($clavesAlumno) < 2) {
            continue;
        }
        if ($firmaAlumno === $firmaRegla) {
            return $r;
        }
        if (count($clavesRegla) === count($clavesAlumno)) {
            return $r;
        }
    }

    foreach ($reglas as $r) {
        $firmaRegla = combo_normalizar_claves(explode(',', $r['claves_combo']));
        $clavesRegla = explode(',', $firmaRegla);
        $ok = true;
        foreach ($clavesRegla as $c) {
            if (!in_array($c, $clavesAlumno, true)) {
                $ok = false;
                break;
            }
        }
        if ($ok && count($clavesAlumno) >= (int) $r['min_especialidades']) {
            return $r;
        }
    }

    return null;
}

/**
 * Aplica una regla concreta (elección en inscripción). Permite tarifa parcial si aún no cursa todas las materias.
 */
function combo_aplicar_regla_id(PDO $pdo, int $idAlumno, int $idRegla, bool $forzarParcial = false): array
{
    $regla = combo_regla_por_id($pdo, $idRegla);
    if (!$regla) {
        return ['ok' => false, 'message' => 'Regla de descuento no encontrada'];
    }
    $claves = combo_claves_alumno_activas($pdo, $idAlumno);
    $clavesRegla = explode(',', combo_normalizar_claves(explode(',', $regla['claves_combo'])));
    $tieneAlguna = false;
    foreach ($clavesRegla as $c) {
        if ($c !== '' && in_array($c, $claves, true)) {
            $tieneAlguna = true;
            break;
        }
    }
    if (!$forzarParcial && !$tieneAlguna) {
        return ['ok' => false, 'message' => 'El alumno no tiene especialidades de esta regla'];
    }

    return combo_aplicar_regla_interna($pdo, $idAlumno, $regla, $forzarParcial);
}

/**
 * Actualiza tarifas congeladas según regla de descuento vigente para el alumno.
 */
function pago_aplicar_reglas_combo(PDO $pdo, int $idAlumno): array
{
    $stPref = $pdo->prepare('SELECT id_regla_colegiatura_pref FROM alumnos WHERE id_alumno = ? LIMIT 1');
    $stPref->execute([$idAlumno]);
    $idPref = (int) $stPref->fetchColumn();

    $claves = combo_claves_alumno_activas($pdo, $idAlumno);
    $regla = null;

    if ($idPref > 0) {
        $regla = combo_regla_por_id($pdo, $idPref);
        if ($regla) {
            $clavesRegla = explode(',', combo_normalizar_claves(explode(',', $regla['claves_combo'])));
            $activasEnRegla = 0;
            foreach ($clavesRegla as $c) {
                if ($c !== '' && in_array($c, $claves, true)) {
                    $activasEnRegla++;
                }
            }
            $minReq = max(1, (int) $regla['min_especialidades']);
            if ($activasEnRegla < $minReq && ($regla['tipo'] ?? '') === 'combinacion') {
                return combo_aplicar_regla_interna($pdo, $idAlumno, $regla, true);
            }
        }
    }

    if (!$regla) {
        $regla = combo_buscar_regla_aplicable($pdo, $claves);
    }

    if (!$regla) {
        combo_restaurar_tarifas_sin_descuento($pdo, $idAlumno);
        return ['ok' => true, 'aplicada' => false, 'message' => 'Sin descuento aplicable'];
    }

    return combo_aplicar_regla_interna($pdo, $idAlumno, $regla, false);
}

/** @param array<string, mixed> $regla */
function combo_aplicar_regla_interna(PDO $pdo, int $idAlumno, array $regla, bool $parcial): array
{

    $tarifas = $pdo->prepare(
        'SELECT * FROM regla_combo_tarifa WHERE id_regla = ?'
    );
    $tarifas->execute([(int) $regla['id_regla']]);
    $porEsp = [];
    foreach ($tarifas->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $porEsp[(int) $t['id_especialidad']] = $t;
    }

    $aes = $pdo->prepare(
        'SELECT ae.id_alumno_especialidad, ae.id_especialidad FROM alumno_especialidades ae
         WHERE ae.id_alumno = ? AND ae.activo = 1 AND ae.override_supervisor = 0'
    );
    $aes->execute([$idAlumno]);
    $upd = $pdo->prepare(
        'UPDATE alumno_especialidades SET
            costo_inscripcion = ?, costo_mensualidad = ?, costo_pronto_pago = ?, costo_semanal = ?,
            id_regla_combo = ?
         WHERE id_alumno_especialidad = ?'
    );

    $clavesRegla = explode(',', combo_normalizar_claves(explode(',', $regla['claves_combo'])));
    $clavesActivas = combo_claves_alumno_activas($pdo, $idAlumno);
    $todasActivas = true;
    foreach ($clavesRegla as $c) {
        if ($c !== '' && !in_array($c, $clavesActivas, true)) {
            $todasActivas = false;
            break;
        }
    }
    if (($regla['tipo'] ?? 'combinacion') === 'combinacion' && !$todasActivas && !$parcial) {
        combo_restaurar_tarifas_sin_descuento($pdo, $idAlumno);
        return ['ok' => true, 'aplicada' => false, 'message' => 'Descuento por combinación solo con todas las materias activas'];
    }

    $n = 0;
    foreach ($aes->fetchAll(PDO::FETCH_ASSOC) as $ae) {
        $idEsp = (int) $ae['id_especialidad'];
        if (!isset($porEsp[$idEsp])) {
            continue;
        }
        combo_respaldar_tarifas_base($pdo, (int) $ae['id_alumno_especialidad']);
        $t = $porEsp[$idEsp];
        $upd->execute([
            $t['costo_inscripcion'],
            $t['costo_mensualidad'],
            $t['costo_pronto_pago'],
            $t['costo_semanal'],
            (int) $regla['id_regla'],
            (int) $ae['id_alumno_especialidad'],
        ]);
        $n++;
    }

    if ($todasActivas || ($regla['tipo'] ?? '') !== 'combinacion') {
        $pdo->prepare('UPDATE alumnos SET id_regla_colegiatura_pref = NULL WHERE id_alumno = ?')->execute([$idAlumno]);
    }

    return [
        'ok' => true,
        'aplicada' => $n > 0,
        'id_regla' => (int) $regla['id_regla'],
        'nombre' => $regla['nombre'],
        'especialidades_actualizadas' => $n,
        'parcial' => $parcial && !$todasActivas,
    ];
}

/** @return array<int, array<string, mixed>> */
function combo_listar_reglas(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT r.*, CONCAT(u.nombre, " ", u.apellido) AS autor_nombre
         FROM reglas_colegiatura_combo r
         LEFT JOIN usuarios u ON u.id_usuario = r.id_autoriza
         ORDER BY r.creado_en DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $st = $pdo->prepare('SELECT t.*, e.clave, e.nombre FROM regla_combo_tarifa t INNER JOIN especialidades e ON e.id_especialidad = t.id_especialidad WHERE t.id_regla = ?');
        $st->execute([(int) $r['id_regla']]);
        $r['tarifas'] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($r);

    return $rows;
}

function combo_guardar_regla(PDO $pdo, array $data, int $idAutoriza): array
{
    $nombre = trim($data['nombre'] ?? '');
    $claves = $data['claves'] ?? [];
    if (!is_array($claves)) {
        $claves = array_filter(array_map('trim', explode(',', (string) $claves)));
    }
    $tipo = trim((string) ($data['tipo'] ?? 'combinacion'));
    if (!in_array($tipo, ['combinacion', 'promocion'], true)) {
        $tipo = 'combinacion';
    }
    $categoriaPromo = null;
    if ($tipo === 'promocion') {
        $cat = trim((string) ($data['categoria_promo'] ?? 'promocion'));
        if (!isset(combo_categorias_promocion()[$cat])) {
            $cat = 'promocion';
        }
        $categoriaPromo = $cat;
    }

    $firma = combo_normalizar_claves($claves);
    if ($nombre === '' || $firma === '') {
        return ['ok' => false, 'message' => 'Nombre y al menos una especialidad en la regla'];
    }
    if ($tipo === 'combinacion' && count(explode(',', $firma)) < 2) {
        return ['ok' => false, 'message' => 'Una combinación requiere al menos dos especialidades'];
    }

    $minEsp = $tipo === 'combinacion' ? max(2, count($claves)) : max(1, count($claves));

    $tarifas = $data['tarifas'] ?? [];
    if (!is_array($tarifas) || count($tarifas) < 1) {
        return ['ok' => false, 'message' => 'Define tarifa por especialidad en la combinación'];
    }

    $idRegla = (int) ($data['id_regla'] ?? 0);
    if ($idRegla > 0) {
        $pdo->prepare(
            'UPDATE reglas_colegiatura_combo SET nombre=?, claves_combo=?, min_especialidades=?, motivo=?, tipo=?, categoria_promo=?, id_autoriza=?
             WHERE id_regla=?'
        )->execute([
            $nombre, $firma, $minEsp, trim($data['motivo'] ?? '') ?: null, $tipo, $categoriaPromo, $idAutoriza, $idRegla,
        ]);
        $pdo->prepare('DELETE FROM regla_combo_tarifa WHERE id_regla = ?')->execute([$idRegla]);
    } else {
        $pdo->prepare(
            'INSERT INTO reglas_colegiatura_combo (nombre, claves_combo, min_especialidades, motivo, tipo, categoria_promo, id_autoriza)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $nombre, $firma, $minEsp, trim($data['motivo'] ?? '') ?: null, $tipo, $categoriaPromo, $idAutoriza,
        ]);
        $idRegla = (int) $pdo->lastInsertId();
    }

    $ins = $pdo->prepare(
        'INSERT INTO regla_combo_tarifa (id_regla, id_especialidad, costo_inscripcion, costo_inscripcion_referencia, costo_inscripcion_apoyo,
         costo_mensualidad, costo_pronto_pago, costo_semanal, costo_anual)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    foreach ($tarifas as $t) {
        $idEsp = (int) ($t['id_especialidad'] ?? 0);
        if ($idEsp <= 0) {
            continue;
        }
        $stEsp = $pdo->prepare(
            'SELECT costo_inscripcion, costo_inscripcion_referencia, costo_inscripcion_apoyo FROM especialidades WHERE id_especialidad = ? LIMIT 1'
        );
        $stEsp->execute([$idEsp]);
        $esp = $stEsp->fetch(PDO::FETCH_ASSOC) ?: [];
        $inscDesc = catalog_money($t['costo_inscripcion'] ?? 0);
        $inscRef = catalog_money($t['costo_inscripcion_referencia'] ?? ($esp['costo_inscripcion_referencia'] ?? 0));
        if ($inscRef <= 0) {
            $apTmp = catalog_money($t['costo_inscripcion_apoyo'] ?? ($esp['costo_inscripcion_apoyo'] ?? $esp['costo_inscripcion'] ?? 0));
            $inscRef = $apTmp > 0 ? round($apTmp * 2, 2) : 0;
        }
        $inscApoyo = catalog_money($t['costo_inscripcion_apoyo'] ?? ($esp['costo_inscripcion_apoyo'] ?? $esp['costo_inscripcion'] ?? 0));
        $ins->execute([
            $idRegla,
            $idEsp,
            $inscDesc,
            $inscRef > 0 ? $inscRef : null,
            $inscApoyo > 0 ? $inscApoyo : null,
            catalog_money($t['costo_mensualidad'] ?? 0),
            catalog_money($t['costo_pronto_pago'] ?? 0),
            catalog_money($t['costo_semanal'] ?? 0),
            catalog_money($t['costo_anual'] ?? 0) ?: null,
        ]);
    }

    combo_reaplicar_regla_alumnos($pdo, $idRegla);

    return ['ok' => true, 'message' => 'Regla guardada', 'id_regla' => $idRegla];
}

function combo_reaplicar_regla_alumnos(PDO $pdo, int $idRegla): void
{
    $regla = $pdo->prepare('SELECT claves_combo FROM reglas_colegiatura_combo WHERE id_regla = ?');
    $regla->execute([$idRegla]);
    $clavesRegla = explode(',', (string) $regla->fetchColumn());

    $alumnos = $pdo->query(
        'SELECT DISTINCT id_alumno FROM alumno_especialidades WHERE activo = 1'
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($alumnos as $idAl) {
        $claves = combo_claves_alumno_activas($pdo, (int) $idAl);
        $ok = true;
        foreach ($clavesRegla as $c) {
            if ($c !== '' && !in_array($c, $claves, true)) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            pago_aplicar_reglas_combo($pdo, (int) $idAl);
        }
    }
}

function combo_id_autoriza_default(PDO $pdo): int
{
    $id = (int) $pdo->query(
        "SELECT id_usuario FROM usuarios WHERE rol IN ('admin','gerente') ORDER BY id_usuario ASC LIMIT 1"
    )->fetchColumn();
    return $id > 0 ? $id : 1;
}

function combo_seed_ejemplos(PDO $pdo): void
{
    $n = (int) $pdo->query('SELECT COUNT(*) FROM reglas_colegiatura_combo')->fetchColumn();
    if ($n > 0) {
        return;
    }
    $idAutor = combo_id_autoriza_default($pdo);

    $ids = [];
    foreach (['ING-K', 'COMP-K', 'ING', 'COMP'] as $clave) {
        $st = $pdo->prepare('SELECT id_especialidad, costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal FROM especialidades WHERE clave = ? LIMIT 1');
        $st->execute([$clave]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $ids[$clave] = $row;
        }
    }

    if (isset($ids['ING-K'], $ids['COMP-K'])) {
        $pdo->prepare(
            'INSERT INTO reglas_colegiatura_combo (nombre, claves_combo, min_especialidades, motivo, id_autoriza)
             VALUES (?,?,?,?,?)'
        )->execute([
            'Infantil — Inglés + Computación',
            'COMP-K,ING-K',
            2,
            'Paquete infantil autorizado',
            $idAutor,
        ]);
        $idR = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare(
            'INSERT INTO regla_combo_tarifa (id_regla, id_especialidad, costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal) VALUES (?,?,?,?,?,?)'
        );
        $desc = 0.85;
        foreach (['ING-K', 'COMP-K'] as $c) {
            $e = $ids[$c];
            $ins->execute([
                $idR, (int) $e['id_especialidad'],
                round((float) $e['costo_inscripcion'] * $desc, 2),
                round((float) $e['costo_mensualidad'] * $desc, 2),
                round((float) $e['costo_pronto_pago'] * $desc, 2),
                round((float) $e['costo_semanal'] * $desc, 2),
            ]);
        }
    }

    if (isset($ids['ING'], $ids['COMP'])) {
        $pdo->prepare(
            'INSERT INTO reglas_colegiatura_combo (nombre, claves_combo, min_especialidades, motivo, id_autoriza)
             VALUES (?,?,?,?,?)'
        )->execute([
            'Inglés + Informática (adultos)',
            'COMP,ING',
            2,
            'Descuento segunda especialidad',
            $idAutor,
        ]);
        $idR = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare(
            'INSERT INTO regla_combo_tarifa (id_regla, id_especialidad, costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal) VALUES (?,?,?,?,?,?)'
        );
        $eIng = $ids['ING'];
        $eComp = $ids['COMP'];
        $ins->execute([$idR, (int) $eIng['id_especialidad'], $eIng['costo_inscripcion'], $eIng['costo_mensualidad'], $eIng['costo_pronto_pago'], $eIng['costo_semanal']]);
        $ins->execute([
            $idR, (int) $eComp['id_especialidad'],
            $eComp['costo_inscripcion'],
            round((float) $eComp['costo_mensualidad'] * 0.9, 2),
            round((float) $eComp['costo_pronto_pago'] * 0.9, 2),
            round((float) $eComp['costo_semanal'] * 0.9, 2),
        ]);
    }
}

/** IDs de especialidades kids por clave */
function combo_ids_kids(PDO $pdo): array
{
    $out = ['ingles' => 0, 'computacion' => 0];
    foreach (['ING-K' => 'ingles', 'COMP-K' => 'computacion'] as $clave => $key) {
        $st = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE clave = ? LIMIT 1');
        $st->execute([$clave]);
        $out[$key] = (int) $st->fetchColumn();
    }
    return $out;
}

function alumno_inscribir_kids(
    PDO $pdo,
    int $idAlumno,
    ?int $idGrupoIngles,
    ?int $idGrupoComputacion,
    string $formaPago = 'mensual'
): array {
    if ($idGrupoIngles <= 0 && $idGrupoComputacion <= 0) {
        return ['ok' => false, 'message' => 'Selecciona al menos un grupo (inglés y/o computación)'];
    }

    $kids = combo_ids_kids($pdo);
    $modo = 'dual';
    if ($idGrupoIngles > 0 && $idGrupoComputacion <= 0) {
        $modo = 'solo_ingles';
    } elseif ($idGrupoIngles <= 0 && $idGrupoComputacion > 0) {
        $modo = 'solo_computacion';
    }

    $fecha = date('Y-m-d');
    $idPlantel = plantel_id_activo();

    if ($idGrupoIngles > 0) {
        if (!plantel_grupo_pertenece($pdo, $idGrupoIngles, $idPlantel)) {
            return ['ok' => false, 'message' => 'Grupo de inglés no válido'];
        }
        alumno_asignar_grupo($pdo, $idAlumno, $idGrupoIngles);
        if ($kids['ingles'] > 0) {
            pago_crear_inscripcion($pdo, $idAlumno, $kids['ingles'], $formaPago, $fecha);
        }
    }
    if ($idGrupoComputacion > 0) {
        if (!plantel_grupo_pertenece($pdo, $idGrupoComputacion, $idPlantel)) {
            return ['ok' => false, 'message' => 'Grupo de computación no válido'];
        }
        alumno_asignar_grupo($pdo, $idAlumno, $idGrupoComputacion);
        if ($kids['computacion'] > 0) {
            pago_crear_inscripcion($pdo, $idAlumno, $kids['computacion'], $formaPago, $fecha);
        }
    }

    $pdo->prepare('UPDATE alumnos SET inscripcion_kids_modo = ?, estado = \'activo\' WHERE id_alumno = ?')
        ->execute([$modo, $idAlumno]);

    $combo = pago_aplicar_reglas_combo($pdo, $idAlumno);

    return [
        'ok' => true,
        'message' => 'Inscripción infantil registrada' . ($combo['aplicada'] ? ' · ' . $combo['nombre'] : ''),
        'modo' => $modo,
        'combo' => $combo,
    ];
}

function alumno_asignar_grupo(PDO $pdo, int $idAlumno, int $idGrupo, bool $ubicacionExamen = false): void
{
    $idPlantel = plantel_id_activo();
    if (!plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel)) {
        throw new PDOException('Grupo no válido para este plantel');
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $idFaseEntrada = null;
        $g = $pdo->prepare('SELECT id_fase_actual FROM grupos WHERE id_grupo = ? AND id_plantel = ? LIMIT 1');
        $g->execute([$idGrupo, $idPlantel]);
        $idFaseEntrada = $g->fetchColumn();
        $idFaseEntrada = $idFaseEntrada ? (int) $idFaseEntrada : null;

        $prevGrupos = $pdo->prepare(
            'SELECT ag.id_grupo FROM alumno_grupos ag
             INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
             WHERE ag.id_alumno = ? AND ag.activo = 1 AND ag.id_grupo != ?
               AND g.id_plantel = ? AND (g.id_especialidad <=> ?)'
        );
        $idEspNuevo = function_exists('alumno_grupo_especialidad')
            ? alumno_grupo_especialidad($pdo, $idGrupo)
            : null;
        if ($idEspNuevo === null) {
            $gEsp = $pdo->prepare('SELECT id_especialidad FROM grupos WHERE id_grupo = ? LIMIT 1');
            $gEsp->execute([$idGrupo]);
            $v = $gEsp->fetchColumn();
            $idEspNuevo = $v !== false && $v !== null ? (int) $v : null;
        }
        $prevGrupos->execute([$idAlumno, $idGrupo, $idPlantel, $idEspNuevo]);
        $gruposAnteriores = array_map('intval', $prevGrupos->fetchAll(PDO::FETCH_COLUMN));

        if ($gruposAnteriores !== []) {
            $placeholders = implode(',', array_fill(0, count($gruposAnteriores), '?'));
            $pdo->prepare(
                "UPDATE alumno_grupos SET activo = 0, fecha_baja = CURDATE()
                 WHERE id_alumno = ? AND id_grupo IN ($placeholders)"
            )->execute(array_merge([$idAlumno], $gruposAnteriores));
        }

        $pdo->prepare(
            'INSERT INTO alumno_grupos (id_alumno, id_grupo, activo, fecha_inicio, id_fase_entrada, ubicacion_examen)
             VALUES (?, ?, 1, CURDATE(), ?, ?)
             ON DUPLICATE KEY UPDATE activo = 1, fecha_baja = NULL, id_fase_entrada = COALESCE(VALUES(id_fase_entrada), id_fase_entrada),
             ubicacion_examen = GREATEST(ubicacion_examen, VALUES(ubicacion_examen))'
        )->execute([$idAlumno, $idGrupo, $idFaseEntrada, $ubicacionExamen ? 1 : 0]);

        $pdo->prepare('UPDATE alumnos SET id_grupo = ? WHERE id_alumno = ?')->execute([$idGrupo, $idAlumno]);

        if ($gruposAnteriores !== [] && function_exists('reporte_semanal_log_cambio_grupo')) {
            reporte_semanal_log_cambio_grupo($pdo, $idPlantel, $idAlumno, $idGrupo, $gruposAnteriores);
        }

        if ($ownTx) {
            $pdo->commit();
        }

        if (!defined('SEED_RUNNING') && function_exists('academico_notificar_profesor_alumno_nuevo')) {
            academico_notificar_profesor_alumno_nuevo($pdo, $idGrupo, $idAlumno, $ubicacionExamen);
        }

        if (function_exists('moodle_alumno_inscribir_por_grupo')) {
            try {
                moodle_alumno_inscribir_por_grupo($pdo, $idAlumno, $idGrupo);
            } catch (Throwable $e) {
                error_log('moodle_alumno_inscribir_por_grupo: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function alumno_inscribir_especialidad(
    PDO $pdo,
    int $idAlumno,
    int $idEspecialidad,
    ?int $idGrupo,
    string $formaPago = 'mensual'
): array {
    $idPlantel = plantel_id_activo();
    $chk = $pdo->prepare('SELECT id_alumno FROM alumnos WHERE id_alumno = ? AND id_plantel = ?');
    $chk->execute([$idAlumno, $idPlantel]);
    if (!$chk->fetchColumn()) {
        return ['ok' => false, 'message' => 'Alumno no encontrado'];
    }

    $idAe = pago_crear_inscripcion($pdo, $idAlumno, $idEspecialidad, $formaPago, date('Y-m-d'));
    if ($idGrupo > 0) {
        if (!plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel)) {
            return ['ok' => false, 'message' => 'Grupo no válido'];
        }
        $esPer = function_exists('inscripcion_grupo_es_personalizado')
            && inscripcion_grupo_es_personalizado($pdo, $idGrupo);
        if (!$esPer && !inscripcion_puede_asignar_grupo($pdo, $idAlumno, $idEspecialidad)) {
            return [
                'ok' => false,
                'message' => 'Debe cubrir la inscripción antes de asignar al grupo',
                'saldo' => inscripcion_saldo_pendiente($pdo, $idAlumno, $idEspecialidad),
                'id_alumno_especialidad' => $idAe,
            ];
        }
        if (function_exists('ubicacion_asignar_grupo_validado')) {
            $asign = ubicacion_asignar_grupo_validado($pdo, $idAlumno, $idGrupo);
            if (!$asign['ok']) {
                return $asign;
            }
        } else {
            alumno_asignar_grupo($pdo, $idAlumno, $idGrupo);
        }
        $gEsp = $pdo->prepare('SELECT id_especialidad FROM grupos WHERE id_grupo = ?');
        $gEsp->execute([$idGrupo]);
        $idEspGrupo = (int) $gEsp->fetchColumn();
        if ($idEspGrupo > 0 && $idEspGrupo !== $idEspecialidad) {
            pago_crear_inscripcion($pdo, $idAlumno, $idEspGrupo, $formaPago, date('Y-m-d'));
        }
    }

    $combo = pago_aplicar_reglas_combo($pdo, $idAlumno);

    return [
        'ok' => true,
        'message' => 'Especialidad inscrita' . ($combo['aplicada'] ? ' · Tarifa combo: ' . $combo['nombre'] : ''),
        'id_alumno_especialidad' => $idAe,
        'combo' => $combo,
    ];
}

function alumno_baja_temporal(PDO $pdo, int $idAlumno, string $motivo): array
{
    $motivo = trim($motivo);
    if ($motivo === '') {
        return ['ok' => false, 'message' => 'Indica el motivo de la baja temporal'];
    }
    $idPlantel = plantel_id_activo();
    $fecha = date('Y-m-d');
    if (function_exists('reporte_semanal_registrar_movimiento')) {
        reporte_semanal_ensure_schema($pdo);
        $st = $pdo->prepare('SELECT id_grupo FROM alumno_grupos WHERE id_alumno = ? AND activo = 1');
        $st->execute([$idAlumno]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $idG) {
            reporte_semanal_registrar_movimiento(
                $pdo, $idPlantel, $idAlumno, (int) $idG, 'B', $fecha,
                null, $motivo, null, 'manual'
            );
        }
    }
    $hasta = date('Y-m-d', strtotime('+' . pago_inscripcion_vigencia_meses_alumno($pdo, $idAlumno) . ' months'));
    $pdo->prepare(
        'UPDATE alumnos SET estado = \'baja\', fecha_baja_temporal = CURDATE(),
         inscripcion_vigente_hasta = ?, motivo_baja_temporal = ?
         WHERE id_alumno = ?'
    )->execute([$hasta, $motivo, $idAlumno]);

    if (function_exists('pago_marcar_pausa_colegiatura')) {
        pago_marcar_pausa_colegiatura($pdo, $idAlumno);
    }

    if (function_exists('usuario_suspension_por_baja_alumno')) {
        usuario_suspension_por_baja_alumno($pdo, $idAlumno, $motivo);
    }

    return [
        'ok' => true,
        'message' => 'Baja temporal registrada. Inscripción vigente hasta ' . date('d/m/Y', strtotime($hasta)),
        'inscripcion_vigente_hasta' => $hasta,
    ];
}

function alumno_reactivar(PDO $pdo, int $idAlumno): array
{
    if (function_exists('pago_reanudar_colegiatura_tras_baja')) {
        pago_reanudar_colegiatura_tras_baja($pdo, $idAlumno);
    }
    $pdo->prepare(
        'UPDATE alumnos SET estado = \'activo\', fecha_baja_temporal = NULL, motivo_baja_temporal = NULL
         WHERE id_alumno = ?'
    )->execute([$idAlumno]);

    if (function_exists('usuario_suspension_reactivar_si_baja')) {
        usuario_suspension_reactivar_si_baja($pdo, $idAlumno);
    }

    return ['ok' => true, 'message' => 'Alumno reactivado. Las colegiaturas continúan posponiendo los meses de baja.'];
}
