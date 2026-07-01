<?php

define('AULA_FOTO_DIR', 'uploads/aulas');
define('AULA_FOTO_MAX', 3);

/** @return array<string, string> */
function aula_tipos(): array
{
    return [
        'aula' => 'Aula',
        'lab_computo' => 'Laboratorio de cómputo',
        'lab_quimica' => 'Laboratorio de química',
        'taller_robotica' => 'Taller de robótica',
        'mantenimiento' => 'Mantenimiento',
    ];
}

function aula_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS plantel_aulas (
            id_aula INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            codigo VARCHAR(40) NOT NULL,
            nombre VARCHAR(120) NULL,
            piso VARCHAR(30) NULL,
            capacidad INT UNSIGNED NOT NULL DEFAULT 20,
            tiene_pizarron TINYINT(1) NOT NULL DEFAULT 1,
            tiene_proyector TINYINT(1) NOT NULL DEFAULT 0,
            tiene_tv TINYINT(1) NOT NULL DEFAULT 0,
            tiene_pc TINYINT(1) NOT NULL DEFAULT 0,
            tipo_aula VARCHAR(30) NOT NULL DEFAULT \'aula\',
            es_laboratorio TINYINT(1) NOT NULL DEFAULT 0,
            capacidad_flexible TINYINT(1) NOT NULL DEFAULT 0,
            todas_especialidades TINYINT(1) NOT NULL DEFAULT 1,
            notas TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_aula),
            UNIQUE KEY uq_aula_plantel_codigo (id_plantel, codigo),
            KEY idx_aula_plantel (id_plantel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    plantel_ensure_column($pdo, 'plantel_aulas', 'tiene_pizarron', 'TINYINT(1) NOT NULL DEFAULT 1', 'capacidad');
    plantel_ensure_column($pdo, 'plantel_aulas', 'tiene_pc', 'TINYINT(1) NOT NULL DEFAULT 0', 'tiene_tv');
    plantel_ensure_column($pdo, 'plantel_aulas', 'tipo_aula', "VARCHAR(30) NOT NULL DEFAULT 'aula'", 'tiene_pc');
    plantel_ensure_column($pdo, 'plantel_aulas', 'capacidad_flexible', 'TINYINT(1) NOT NULL DEFAULT 0', 'tipo_aula');
    plantel_ensure_column($pdo, 'plantel_aulas', 'todas_especialidades', 'TINYINT(1) NOT NULL DEFAULT 1', 'capacidad_flexible');
    plantel_ensure_column($pdo, 'grupos', 'id_aula', 'INT UNSIGNED NULL', 'aula');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS aula_especialidades (
            id_aula INT UNSIGNED NOT NULL,
            id_especialidad INT UNSIGNED NOT NULL,
            permitido TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id_aula, id_especialidad),
            KEY idx_ae_esp (id_especialidad)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS aula_fotos (
            id_foto INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_aula INT UNSIGNED NOT NULL,
            orden TINYINT UNSIGNED NOT NULL DEFAULT 1,
            ruta VARCHAR(255) NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_foto),
            KEY idx_af_aula (id_aula)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function aula_puede_gestionar(): bool
{
    if (function_exists('rbac_cap') && rbac_cap('menu_gestion_aulas')) {
        return true;
    }
    return in_array(rbac_rol_efectivo(), ['coordinador', 'director', 'supervisor', 'gerente'], true);
}

/** @return list<array<string, mixed>> */
function aula_listar_plantel(PDO $pdo, int $idPlantel, bool $soloActivas = false): array
{
    aula_ensure_schema($pdo);
    $sql = 'SELECT * FROM plantel_aulas WHERE id_plantel = ?';
    if ($soloActivas) {
        $sql .= ' AND activo = 1';
    }
    $sql .= ' ORDER BY piso, codigo';
    $st = $pdo->prepare($sql);
    $st->execute([$idPlantel]);
    $aulas = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($aulas as &$a) {
        $idAula = (int) $a['id_aula'];
        $a['especialidades'] = aula_especialidades($pdo, $idAula);
        $a['fotos'] = aula_fotos($pdo, $idAula);
        $a['tipo_label'] = aula_tipos()[$a['tipo_aula'] ?? 'aula'] ?? ($a['tipo_aula'] ?? 'Aula');
    }
    unset($a);

    return $aulas;
}

/** @return list<array<string, mixed>> */
function aula_especialidades(PDO $pdo, int $idAula): array
{
    $st = $pdo->prepare(
        'SELECT ae.id_especialidad, ae.permitido, e.nombre, e.clave
         FROM aula_especialidades ae
         INNER JOIN especialidades e ON e.id_especialidad = ae.id_especialidad
         WHERE ae.id_aula = ?
         ORDER BY e.nombre'
    );
    $st->execute([$idAula]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string, mixed>> */
function aula_fotos(PDO $pdo, int $idAula): array
{
    aula_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT id_foto, orden, ruta, creado_en FROM aula_fotos WHERE id_aula = ? ORDER BY orden, id_foto'
    );
    $st->execute([$idAula]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['url'] = hay_asset_url($r['ruta']);
    }
    unset($r);

    return $rows;
}

function aula_permite_especialidad(array $aula, int $idEspecialidad): bool
{
    if (!empty($aula['todas_especialidades'])) {
        return true;
    }
    foreach ($aula['especialidades'] ?? [] as $e) {
        if ((int) ($e['id_especialidad'] ?? 0) === $idEspecialidad && !empty($e['permitido'])) {
            return true;
        }
    }

    return false;
}

function aula_capacidad_efectiva(array $aula, int $cupoGrupo): int
{
    $cap = max(1, (int) ($aula['capacidad'] ?? 1));
    if (!empty($aula['capacidad_flexible']) && $cupoGrupo > $cap) {
        return $cupoGrupo;
    }

    return $cap;
}

/** @param array<string, mixed> $data */
function aula_guardar(PDO $pdo, int $idPlantel, array $data, ?int $idAula = null): array
{
    aula_ensure_schema($pdo);
    $codigo = trim((string) ($data['codigo'] ?? ''));
    if ($codigo === '') {
        return ['ok' => false, 'message' => 'Código de aula obligatorio'];
    }
    $capacidad = max(1, (int) ($data['capacidad'] ?? 20));
    $tipos = array_keys(aula_tipos());
    $tipoAula = (string) ($data['tipo_aula'] ?? 'aula');
    if (!in_array($tipoAula, $tipos, true)) {
        $tipoAula = 'aula';
    }
    $esLab = in_array($tipoAula, ['lab_computo', 'lab_quimica', 'taller_robotica'], true) ? 1 : 0;
    $todasEsp = !empty($data['todas_especialidades']) ? 1 : 0;

    $params = [
        $codigo,
        trim((string) ($data['nombre'] ?? '')) ?: null,
        trim((string) ($data['piso'] ?? '')) ?: null,
        $capacidad,
        !empty($data['tiene_pizarron']) ? 1 : 0,
        !empty($data['tiene_proyector']) ? 1 : 0,
        !empty($data['tiene_tv']) ? 1 : 0,
        !empty($data['tiene_pc']) ? 1 : 0,
        $tipoAula,
        $esLab,
        !empty($data['capacidad_flexible']) ? 1 : 0,
        $todasEsp,
        trim((string) ($data['notas'] ?? '')) ?: null,
        !empty($data['activo']) ? 1 : 0,
    ];

    if ($idAula > 0) {
        $params[] = $idAula;
        $params[] = $idPlantel;
        $pdo->prepare(
            'UPDATE plantel_aulas SET codigo=?, nombre=?, piso=?, capacidad=?,
             tiene_pizarron=?, tiene_proyector=?, tiene_tv=?, tiene_pc=?, tipo_aula=?, es_laboratorio=?,
             capacidad_flexible=?, todas_especialidades=?, notas=?, activo=?
             WHERE id_aula=? AND id_plantel=?'
        )->execute($params);
    } else {
        $pdo->prepare(
            'INSERT INTO plantel_aulas
             (id_plantel, codigo, nombre, piso, capacidad, tiene_pizarron, tiene_proyector, tiene_tv, tiene_pc,
              tipo_aula, es_laboratorio, capacidad_flexible, todas_especialidades, notas, activo)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute(array_merge([$idPlantel], $params));
        $idAula = (int) $pdo->lastInsertId();
    }

    if (!$todasEsp) {
        aula_guardar_especialidades($pdo, $idAula, $data['especialidades'] ?? []);
    } else {
        $pdo->prepare('DELETE FROM aula_especialidades WHERE id_aula = ?')->execute([$idAula]);
    }

    return ['ok' => true, 'message' => 'Aula guardada', 'id_aula' => $idAula];
}

/** @param list<int|string> $idsEsp */
function aula_guardar_especialidades(PDO $pdo, int $idAula, array $idsEsp): void
{
    $pdo->prepare('DELETE FROM aula_especialidades WHERE id_aula = ?')->execute([$idAula]);
    if ($idsEsp === []) {
        return;
    }
    $ins = $pdo->prepare(
        'INSERT INTO aula_especialidades (id_aula, id_especialidad, permitido) VALUES (?,?,1)'
    );
    foreach ($idsEsp as $idEsp) {
        $idEsp = (int) $idEsp;
        if ($idEsp > 0) {
            $ins->execute([$idAula, $idEsp]);
        }
    }
}

/** @param array<string, mixed>|null $file */
function aula_subir_foto(PDO $pdo, int $idPlantel, int $idAula, ?array $file): array
{
    aula_ensure_schema($pdo);
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'message' => 'No se recibió archivo'];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Error al subir la imagen'];
    }

    $chk = $pdo->prepare('SELECT id_aula FROM plantel_aulas WHERE id_aula = ? AND id_plantel = ?');
    $chk->execute([$idAula, $idPlantel]);
    if (!$chk->fetch()) {
        return ['ok' => false, 'message' => 'Aula no encontrada'];
    }

    $st = $pdo->prepare('SELECT COUNT(*) FROM aula_fotos WHERE id_aula = ?');
    $st->execute([$idAula]);
    if ((int) $st->fetchColumn() >= AULA_FOTO_MAX) {
        return ['ok' => false, 'message' => 'Máximo ' . AULA_FOTO_MAX . ' fotos por aula'];
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['ok' => false, 'message' => 'Formato no permitido (jpg, png, webp)'];
    }
    if (($file['size'] ?? 0) > 4 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'La imagen no debe superar 4 MB'];
    }

    $dirAbs = HAY_ROOT . '/' . AULA_FOTO_DIR;
    if (!is_dir($dirAbs) && !mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
        return ['ok' => false, 'message' => 'No se pudo crear carpeta de fotos'];
    }

    $nombre = 'aula_' . $idAula . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $rutaRel = AULA_FOTO_DIR . '/' . $nombre;
    $dest = HAY_ROOT . '/' . $rutaRel;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'message' => 'No se pudo guardar la imagen'];
    }

    $stOrd = $pdo->prepare('SELECT COALESCE(MAX(orden),0)+1 FROM aula_fotos WHERE id_aula = ?');
    $stOrd->execute([$idAula]);
    $orden = (int) $stOrd->fetchColumn();

    $pdo->prepare('INSERT INTO aula_fotos (id_aula, orden, ruta) VALUES (?,?,?)')
        ->execute([$idAula, $orden, $rutaRel]);

    return [
        'ok' => true,
        'message' => 'Foto guardada',
        'id_foto' => (int) $pdo->lastInsertId(),
        'ruta' => $rutaRel,
        'url' => hay_asset_url($rutaRel),
    ];
}

function aula_eliminar_foto(PDO $pdo, int $idPlantel, int $idFoto): array
{
    aula_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT f.id_foto, f.ruta, f.id_aula
         FROM aula_fotos f
         INNER JOIN plantel_aulas a ON a.id_aula = f.id_aula AND a.id_plantel = ?
         WHERE f.id_foto = ?'
    );
    $st->execute([$idPlantel, $idFoto]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'message' => 'Foto no encontrada'];
    }
    $pdo->prepare('DELETE FROM aula_fotos WHERE id_foto = ?')->execute([$idFoto]);
    $abs = HAY_ROOT . '/' . ($row['ruta'] ?? '');
    if (is_file($abs)) {
        @unlink($abs);
    }

    return ['ok' => true, 'message' => 'Foto eliminada'];
}

function aula_eliminar(PDO $pdo, int $idPlantel, int $idAula): array
{
    aula_ensure_schema($pdo);
    foreach (aula_fotos($pdo, $idAula) as $f) {
        aula_eliminar_foto($pdo, $idPlantel, (int) $f['id_foto']);
    }
    $pdo->prepare('DELETE FROM aula_especialidades WHERE id_aula = ?')->execute([$idAula]);
    $pdo->prepare('DELETE FROM plantel_aulas WHERE id_aula = ? AND id_plantel = ?')->execute([$idAula, $idPlantel]);

    return ['ok' => true, 'message' => 'Aula eliminada'];
}
