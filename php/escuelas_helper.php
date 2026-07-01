<?php

function escuelas_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS escuelas_externas (
            id_escuela INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            nombre VARCHAR(200) NOT NULL,
            direccion VARCHAR(255) NULL,
            colonia VARCHAR(120) NULL,
            municipio VARCHAR(120) NULL,
            contacto_nombre VARCHAR(160) NULL,
            contacto_telefono VARCHAR(30) NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_escuela),
            KEY idx_ee_plantel (id_plantel, activo),
            KEY idx_ee_nombre (nombre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS escuela_visita (
            id_visita INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            id_escuela INT UNSIGNED NOT NULL,
            id_usuario_asesor INT UNSIGNED NOT NULL,
            fecha_visita DATE NOT NULL,
            cartas_entregadas INT UNSIGNED NOT NULL DEFAULT 0,
            notas VARCHAR(500) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_visita),
            KEY idx_ev_plantel_fecha (id_plantel, fecha_visita),
            KEY idx_ev_escuela (id_escuela)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'preregistros', 'id_escuela_origen', 'INT UNSIGNED NULL', 'medio_entero_otro');
        plantel_ensure_column($pdo, 'alumnos', 'id_escuela_origen', 'INT UNSIGNED NULL', 'id_especialidad');
    }
}

function escuelas_puede_gestionar(): bool
{
    if (function_exists('rbac_usuario_en_roles') && rbac_usuario_en_roles(['gerente', 'supervisor', 'admin'])) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_gerente_escuelas');
}

function escuelas_puede_ver_reporte(): bool
{
    if (function_exists('rbac_usuario_en_roles') && rbac_usuario_en_roles(['gerente', 'supervisor', 'admin', 'director'])) {
        return true;
    }

    return function_exists('rbac_cap') && rbac_cap('menu_reporte_escuelas');
}

/** @return list<array<string, mixed>> */
function escuelas_listar(PDO $pdo, int $idPlantel, bool $soloActivas = true): array
{
    escuelas_ensure_schema($pdo);
    $sql = 'SELECT * FROM escuelas_externas WHERE id_plantel = ?';
    if ($soloActivas) {
        $sql .= ' AND activo = 1';
    }
    $sql .= ' ORDER BY nombre ASC';
    $st = $pdo->prepare($sql);
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array{ok:bool,message:string,id_escuela?:int} */
function escuelas_guardar(PDO $pdo, int $idPlantel, array $data): array
{
    escuelas_ensure_schema($pdo);

    $nombre = trim((string) ($data['nombre'] ?? ''));
    if ($nombre === '') {
        return ['ok' => false, 'message' => 'Indique el nombre de la escuela'];
    }

    $id = (int) ($data['id_escuela'] ?? 0);
    $params = [
        $nombre,
        trim((string) ($data['direccion'] ?? '')) ?: null,
        trim((string) ($data['colonia'] ?? '')) ?: null,
        trim((string) ($data['municipio'] ?? '')) ?: null,
        trim((string) ($data['contacto_nombre'] ?? '')) ?: null,
        trim((string) ($data['contacto_telefono'] ?? '')) ?: null,
        !empty($data['activo']) ? 1 : 0,
    ];

    if ($id > 0) {
        $params[] = $id;
        $params[] = $idPlantel;
        $st = $pdo->prepare(
            'UPDATE escuelas_externas SET nombre=?, direccion=?, colonia=?, municipio=?,
             contacto_nombre=?, contacto_telefono=?, activo=?
             WHERE id_escuela = ? AND id_plantel = ?'
        );
        $st->execute($params);
        if ($st->rowCount() === 0) {
            return ['ok' => false, 'message' => 'Escuela no encontrada'];
        }

        return ['ok' => true, 'message' => 'Escuela actualizada', 'id_escuela' => $id];
    }

    $st = $pdo->prepare(
        'INSERT INTO escuelas_externas (
            id_plantel, nombre, direccion, colonia, municipio, contacto_nombre, contacto_telefono, activo
        ) VALUES (?,?,?,?,?,?,?,?)'
    );
    $st->execute(array_merge([$idPlantel], $params));

    return ['ok' => true, 'message' => 'Escuela registrada', 'id_escuela' => (int) $pdo->lastInsertId()];
}

/** @return array{ok:bool,message:string} */
function escuelas_eliminar(PDO $pdo, int $idPlantel, int $idEscuela): array
{
    escuelas_ensure_schema($pdo);
    if ($idEscuela <= 0) {
        return ['ok' => false, 'message' => 'ID inválido'];
    }
    $st = $pdo->prepare('UPDATE escuelas_externas SET activo = 0 WHERE id_escuela = ? AND id_plantel = ?');
    $st->execute([$idEscuela, $idPlantel]);

    return ['ok' => true, 'message' => 'Escuela desactivada'];
}

/** @return array{ok:bool,message:string,id_visita?:int} */
function escuelas_guardar_visita(PDO $pdo, int $idPlantel, array $data): array
{
    escuelas_ensure_schema($pdo);

    $idEscuela = (int) ($data['id_escuela'] ?? 0);
    $idAsesor = (int) ($data['id_usuario_asesor'] ?? $_SESSION['user_id'] ?? 0);
    $fecha = trim((string) ($data['fecha_visita'] ?? ''));
    if ($idEscuela <= 0 || $fecha === '') {
        return ['ok' => false, 'message' => 'Escuela y fecha son obligatorios'];
    }

    $stE = $pdo->prepare('SELECT 1 FROM escuelas_externas WHERE id_escuela = ? AND id_plantel = ? LIMIT 1');
    $stE->execute([$idEscuela, $idPlantel]);
    if (!$stE->fetchColumn()) {
        return ['ok' => false, 'message' => 'Escuela no encontrada'];
    }

    $cartas = max(0, (int) ($data['cartas_entregadas'] ?? 0));
    $notas = trim((string) ($data['notas'] ?? ''));
    $id = (int) ($data['id_visita'] ?? 0);

    if ($id > 0) {
        $st = $pdo->prepare(
            'UPDATE escuela_visita SET id_escuela=?, id_usuario_asesor=?, fecha_visita=?,
             cartas_entregadas=?, notas=? WHERE id_visita = ? AND id_plantel = ?'
        );
        $st->execute([$idEscuela, $idAsesor, $fecha, $cartas, $notas ?: null, $id, $idPlantel]);

        return ['ok' => true, 'message' => 'Visita actualizada', 'id_visita' => $id];
    }

    $st = $pdo->prepare(
        'INSERT INTO escuela_visita (id_plantel, id_escuela, id_usuario_asesor, fecha_visita, cartas_entregadas, notas)
         VALUES (?,?,?,?,?,?)'
    );
    $st->execute([$idPlantel, $idEscuela, $idAsesor, $fecha, $cartas, $notas ?: null]);

    return ['ok' => true, 'message' => 'Visita registrada', 'id_visita' => (int) $pdo->lastInsertId()];
}

/**
 * Reporte de visitas y captación por escuela.
 *
 * @param array{desde?:string,hasta?:string,id_escuela?:int} $filtros
 * @return array{filas:list<array>,resumen:array}
 */
function escuelas_reporte(PDO $pdo, int $idPlantel, array $filtros = []): array
{
    escuelas_ensure_schema($pdo);
    if (function_exists('preregistro_ensure_schema')) {
        preregistro_ensure_schema($pdo);
    }

    $desde = !empty($filtros['desde']) ? (string) $filtros['desde'] : date('Y-m-01');
    $hasta = !empty($filtros['hasta']) ? (string) $filtros['hasta'] : date('Y-m-d');
    $idEscuela = !empty($filtros['id_escuela']) ? (int) $filtros['id_escuela'] : 0;

    $params = [$idPlantel, $desde, $hasta];
    $sql = "SELECT ee.id_escuela, ee.nombre AS escuela, ee.municipio,
                   COUNT(DISTINCT ev.id_visita) AS visitas,
                   COALESCE(SUM(ev.cartas_entregadas), 0) AS cartas_entregadas,
                   COUNT(DISTINCT pr.id_preregistro) AS preregistros,
                   COUNT(DISTINCT CASE WHEN pr.estado = 'inscrito' THEN pr.id_preregistro END) AS inscritos
            FROM escuelas_externas ee
            LEFT JOIN escuela_visita ev ON ev.id_escuela = ee.id_escuela
                AND ev.fecha_visita BETWEEN ? AND ?
            LEFT JOIN preregistros pr ON pr.id_escuela_origen = ee.id_escuela
                AND pr.id_plantel = ee.id_plantel
                AND DATE(pr.creado_en) BETWEEN ? AND ?
            WHERE ee.id_plantel = ? AND ee.activo = 1";
    $params = [$desde, $hasta, $desde, $hasta, $idPlantel];
    if ($idEscuela > 0) {
        $sql .= ' AND ee.id_escuela = ?';
        $params[] = $idEscuela;
    }
    $sql .= ' GROUP BY ee.id_escuela, ee.nombre, ee.municipio ORDER BY ee.nombre ASC';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);

    $totalVisitas = 0;
    $totalCartas = 0;
    $totalPrereg = 0;
    $totalInscritos = 0;
    foreach ($filas as $f) {
        $totalVisitas += (int) $f['visitas'];
        $totalCartas += (int) $f['cartas_entregadas'];
        $totalPrereg += (int) $f['preregistros'];
        $totalInscritos += (int) $f['inscritos'];
    }

    return [
        'filas' => $filas,
        'resumen' => [
            'escuelas' => count($filas),
            'visitas' => $totalVisitas,
            'cartas_entregadas' => $totalCartas,
            'preregistros' => $totalPrereg,
            'inscritos' => $totalInscritos,
        ],
    ];
}

/**
 * Último asesor que visitó la escuela (para reparto cartas).
 */
function escuelas_ultimo_asesor_visita(PDO $pdo, int $idEscuela): int
{
    escuelas_ensure_schema($pdo);
    if ($idEscuela <= 0) {
        return 0;
    }
    $st = $pdo->prepare(
        'SELECT id_usuario_asesor FROM escuela_visita
         WHERE id_escuela = ? ORDER BY fecha_visita DESC, id_visita DESC LIMIT 1'
    );
    $st->execute([$idEscuela]);

    return (int) $st->fetchColumn();
}
