<?php

/**
 * Validación de parciales para graduación (piloto inglés) y alertas automáticas.
 */

function graduacion_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS graduacion_alerta (
            id_alerta INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_alumno INT UNSIGNED NOT NULL,
            id_grupo INT UNSIGNED NOT NULL,
            id_especialidad INT UNSIGNED NOT NULL,
            id_fase_actual INT UNSIGNED NULL,
            estado ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
            motivo_decision VARCHAR(500) NULL,
            decidido_por INT UNSIGNED NULL,
            fecha_alerta DATE NOT NULL,
            fecha_fin_estimada DATE NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_alerta),
            UNIQUE KEY uq_grad_alumno_grupo (id_alumno, id_grupo),
            KEY idx_grad_estado (id_plantel, estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/** @return list<array{id_fase: int, clave_fase: string, nombre_fase: string}> */
function graduacion_parciales_requeridos(PDO $pdo, int $idEspecialidad): array
{
    $fases = fase_listar($pdo, $idEspecialidad);
    $out = [];
    foreach ($fases as $f) {
        if (($f['tipo_contenido'] ?? 'regular') === 'regular') {
            $out[] = [
                'id_fase' => (int) $f['id_fase'],
                'clave_fase' => (string) ($f['clave_fase'] ?? ''),
                'nombre_fase' => (string) ($f['nombre_fase'] ?? ''),
            ];
        }
    }
    return $out;
}

/**
 * Parciales sin calificación capturada o sin aprobar.
 *
 * @return list<array<string, mixed>>
 */
function graduacion_parciales_pendientes_alumno(PDO $pdo, int $idAlumno, int $idEspecialidad): array
{
    $req = graduacion_parciales_requeridos($pdo, $idEspecialidad);
    if ($req === []) {
        return [];
    }
    $pendientes = [];
    foreach ($req as $f) {
        $st = $pdo->prepare(
            'SELECT promedio, aprobado FROM alumno_calificacion_parcial WHERE id_alumno = ? AND id_fase = ? LIMIT 1'
        );
        $st->execute([$idAlumno, $f['id_fase']]);
        $cal = $st->fetch(PDO::FETCH_ASSOC);
        if (!$cal || (int) ($cal['aprobado'] ?? 0) !== 1) {
            $pendientes[] = array_merge($f, [
                'promedio' => $cal['promedio'] ?? null,
                'motivo' => !$cal ? 'Sin calificación' : 'No aprobado (< 6)',
            ]);
        }
    }
    return $pendientes;
}

function graduacion_puede_solicitar(PDO $pdo, int $idAlumno, int $idEspecialidad): array
{
    $pend = graduacion_parciales_pendientes_alumno($pdo, $idAlumno, $idEspecialidad);
    if ($pend !== []) {
        return [
            'ok' => false,
            'message' => 'Faltan calificaciones en ' . count($pend) . ' parcial(es)',
            'pendientes' => $pend,
        ];
    }
    return ['ok' => true, 'message' => 'Cumple parciales para graduación'];
}

function graduacion_puede_ver(): bool
{
    return rbac_cap('menu_especialidades') || rbac_cap('menu_alumnos');
}

function graduacion_puede_decidir(): bool
{
    return in_array(rbac_rol_efectivo(), ['gerente', 'supervisor', 'admin'], true);
}

/** Grupo de referencia para alertas cuando el alumno fue fusionado. */
function graduacion_grupo_referencia_alumno(PDO $pdo, int $idAlumno, int $idGrupoActual): int
{
    if (function_exists('grupo_fusion_graduacion_grupo_alumno')) {
        return grupo_fusion_graduacion_grupo_alumno($pdo, $idAlumno, $idGrupoActual);
    }

    return $idGrupoActual;
}

function graduacion_es_fase_previa_proyecto_final(PDO $pdo, int $idEspecialidad, int $idFaseActual): bool
{
    if ($idEspecialidad <= 0 || $idFaseActual <= 0) {
        return false;
    }
    $fases = fase_listar($pdo, $idEspecialidad);
    if (count($fases) < 2) {
        return false;
    }
    $idxFinalProyecto = -1;
    foreach ($fases as $i => $f) {
        if (($f['tipo_contenido'] ?? '') === 'proyecto_final') {
            $idxFinalProyecto = $i;
            break;
        }
    }
    if ($idxFinalProyecto <= 0) {
        return false;
    }
    return (int) ($fases[$idxFinalProyecto - 1]['id_fase'] ?? 0) === $idFaseActual;
}

function graduacion_fecha_fin_estimada_grupo(array $grupo): ?string
{
    if (empty($grupo['fecha_inicio'])) {
        return null;
    }
    $duracionMeses = (int) ($grupo['duracion_meses'] ?? 12);
    try {
        $ini = new DateTimeImmutable((string) $grupo['fecha_inicio']);
        return $ini->modify('+' . $duracionMeses . ' months')->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

function graduacion_cumple_ventana_tres_meses(?string $fechaFinEstimada): bool
{
    if (!$fechaFinEstimada) {
        return false;
    }
    $hoy = new DateTimeImmutable('today');
    try {
        $fin = new DateTimeImmutable($fechaFinEstimada);
    } catch (Exception $e) {
        return false;
    }
    if ($fin < $hoy) {
        return false;
    }
    $max = $hoy->modify('+3 months');
    return $fin <= $max;
}

/**
 * Regla combinada: última fase antes de proyecto final + ventana 3 meses.
 */
function graduacion_generar_alertas_automaticas(PDO $pdo, ?int $idPlantel = null): array
{
    graduacion_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $st = $pdo->prepare(
        "SELECT g.id_grupo, g.id_plantel, g.clave, g.id_fase_actual, g.fecha_inicio,
                e.id_especialidad, e.duracion_meses
         FROM grupos g
         INNER JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_plantel = ?"
    );
    $st->execute([$idPlantel]);
    $creadas = 0;
    $actualizadas = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $idGrupo = (int) $g['id_grupo'];
        $idEsp = (int) $g['id_especialidad'];
        $idFase = (int) ($g['id_fase_actual'] ?? 0);
        if (!graduacion_es_fase_previa_proyecto_final($pdo, $idEsp, $idFase)) {
            continue;
        }
        $fechaFin = graduacion_fecha_fin_estimada_grupo($g);
        if (!graduacion_cumple_ventana_tres_meses($fechaFin)) {
            continue;
        }

        $al = $pdo->prepare(
            "SELECT ag.id_alumno
             FROM alumno_grupos ag
             WHERE ag.id_grupo = ? AND ag.activo = 1
             UNION
             SELECT fa.id_alumno
             FROM grupo_fusion_alumno fa
             INNER JOIN grupo_fusion_plan p ON p.id_fusion_plan = fa.id_fusion_plan
             WHERE fa.id_grupo_graduacion = ? AND fa.separado = 0
               AND p.estado IN ('activa', 'separada')"
        );
        $al->execute([$idGrupo, $idGrupo]);
        foreach ($al->fetchAll(PDO::FETCH_COLUMN) as $idAlumno) {
            $chk = $pdo->prepare('SELECT id_alerta, estado FROM graduacion_alerta WHERE id_alumno = ? AND id_grupo = ? LIMIT 1');
            $chk->execute([(int) $idAlumno, $idGrupo]);
            $prev = $chk->fetch(PDO::FETCH_ASSOC);
            if ($prev) {
                if (($prev['estado'] ?? '') === 'pendiente') {
                    $pdo->prepare(
                        'UPDATE graduacion_alerta
                         SET id_fase_actual = ?, fecha_alerta = CURDATE(), fecha_fin_estimada = ?
                         WHERE id_alerta = ?'
                    )->execute([$idFase, $fechaFin, (int) $prev['id_alerta']]);
                    $actualizadas++;
                }
                continue;
            }
            $pdo->prepare(
                "INSERT INTO graduacion_alerta
                (id_plantel, id_alumno, id_grupo, id_especialidad, id_fase_actual, estado, fecha_alerta, fecha_fin_estimada)
                VALUES (?, ?, ?, ?, ?, 'pendiente', CURDATE(), ?)"
            )->execute([$idPlantel, (int) $idAlumno, $idGrupo, $idEsp, $idFase, $fechaFin]);
            $creadas++;
        }
    }

    return ['creadas' => $creadas, 'actualizadas' => $actualizadas];
}

/** @return list<array<string,mixed>> */
function graduacion_listar_alertas(PDO $pdo, ?int $idPlantel = null, string $estado = 'pendiente'): array
{
    graduacion_ensure_schema($pdo);
    $idPlantel = $idPlantel ?? plantel_id_activo();
    $extra = '';
    $params = [$idPlantel];
    if (in_array($estado, ['pendiente', 'aprobado', 'rechazado'], true)) {
        $extra = ' AND ga.estado = ?';
        $params[] = $estado;
    }
    $st = $pdo->prepare(
        "SELECT ga.*, g.clave AS grupo_clave, e.nombre AS especialidad_nombre, e.clave AS especialidad_clave,
                f.clave_fase, f.nombre_fase,
                a.numero_control,
                TRIM(CONCAT(COALESCE(a.nombres, a.nombre, ''), ' ', COALESCE(a.apellido_paterno, a.apellido, ''))) AS alumno_nombre,
                u.nombre AS decidido_nombre, u.apellido AS decidido_apellido
         FROM graduacion_alerta ga
         INNER JOIN grupos g ON g.id_grupo = ga.id_grupo
         INNER JOIN alumnos a ON a.id_alumno = ga.id_alumno
         LEFT JOIN especialidades e ON e.id_especialidad = ga.id_especialidad
         LEFT JOIN especialidad_fases f ON f.id_fase = ga.id_fase_actual
         LEFT JOIN usuarios u ON u.id_usuario = ga.decidido_por
         WHERE ga.id_plantel = ? {$extra}
         ORDER BY ga.fecha_fin_estimada ASC, ga.creado_en DESC"
    );
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function graduacion_decidir_alerta(PDO $pdo, int $idAlerta, string $estado, string $motivo, int $idUsuario): array
{
    if (!in_array($estado, ['aprobado', 'rechazado'], true)) {
        return ['ok' => false, 'message' => 'Estado inválido'];
    }
    $motivo = trim($motivo);
    if ($motivo === '') {
        return ['ok' => false, 'message' => 'Escriba el motivo de la decisión'];
    }
    $st = $pdo->prepare('SELECT id_alerta FROM graduacion_alerta WHERE id_alerta = ? AND id_plantel = ?');
    $st->execute([$idAlerta, plantel_id_activo()]);
    if (!$st->fetchColumn()) {
        return ['ok' => false, 'message' => 'Alerta no encontrada'];
    }
    $pdo->prepare(
        'UPDATE graduacion_alerta
         SET estado = ?, motivo_decision = ?, decidido_por = ?, actualizado_en = NOW()
         WHERE id_alerta = ?'
    )->execute([$estado, $motivo, $idUsuario, $idAlerta]);

    return ['ok' => true, 'message' => 'Decisión guardada'];
}
