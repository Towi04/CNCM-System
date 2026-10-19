<?php
/**
 * Extracción selectiva desde BD legado (XAMPP) → CSV para importar en HAY.
 */

function migcsv_config(): array
{
    $path = __DIR__ . '/config.php';
    if (!is_readable($path)) {
        return [];
    }
    $cfg = require $path;

    return is_array($cfg) ? $cfg : [];
}

function migcsv_pdo(): PDO
{
    $cfg = migcsv_config();
    if ($cfg === []) {
        throw new RuntimeException('Falta config.php. Copie config.example.php → config.php');
    }
    $host = $cfg['db_host'] ?? '127.0.0.1';
    $port = (int) ($cfg['db_port'] ?? 3306);
    $name = $cfg['db_name'] ?? '';
    $user = $cfg['db_user'] ?? 'root';
    $pass = (string) ($cfg['db_pass'] ?? '');
    $charset = $cfg['db_charset'] ?? 'utf8mb4';
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function migcsv_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $st->execute([$table]);

    return (bool) $st->fetchColumn();
}

function migcsv_column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $st->execute([$table, $column]);

    return (bool) $st->fetchColumn();
}

/** @return list<int|string> */
function migcsv_leer_filtro_grupos(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $out = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $out[] = $line;
    }

    return $out;
}

/**
 * Resuelve ids de grupos a partir de ids numéricos o claves.
 *
 * @param list<int|string> $filtro
 * @return list<int>
 */
function migcsv_resolver_ids_grupos(PDO $pdo, array $filtro): array
{
    if ($filtro === []) {
        return [];
    }
    $ids = [];
    $claves = [];
    foreach ($filtro as $item) {
        $s = trim((string) $item);
        if ($s === '') {
            continue;
        }
        if (ctype_digit($s)) {
            $ids[] = (int) $s;
        } else {
            $claves[] = $s;
        }
    }
    $out = $ids;
    if ($claves !== [] && migcsv_column_exists($pdo, 'grupos', 'clave')) {
        $ph = implode(',', array_fill(0, count($claves), '?'));
        $st = $pdo->prepare("SELECT id FROM grupos WHERE clave IN ($ph)");
        $st->execute($claves);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $out[] = (int) $id;
        }
    }

    return array_values(array_unique(array_filter($out, static fn ($v) => $v > 0)));
}

/** @return array<string, mixed> */
function migcsv_diagnostico(PDO $pdo): array
{
    $out = [
        'database' => (string) $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'tablas' => [],
        'por_sucursal' => [],
    ];
    foreach (['sucursales', 'grupos', 'grupos_dias', 'grupos_materias', 'alumnos', 'alumnos_grupos', 'asistencias', 'pagos', 'abonos', 'users', 'especialidades'] as $t) {
        if (!migcsv_table_exists($pdo, $t)) {
            $out['tablas'][$t] = null;
            continue;
        }
        $out['tablas'][$t] = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    }

    if (!migcsv_table_exists($pdo, 'sucursales') || !migcsv_table_exists($pdo, 'grupos')) {
        return $out;
    }

    $sql = 'SELECT s.id, s.nombre,
            (SELECT COUNT(*) FROM grupos g WHERE g.id_sucursal = s.id) AS grupos,
            (SELECT COUNT(*) FROM grupos g WHERE g.id_sucursal = s.id AND LOWER(COALESCE(g.status,\'\')) LIKE \'%activ%\') AS grupos_activos
            FROM sucursales s ORDER BY s.id';
    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as $r) {
        $sid = (int) $r['id'];
        $extra = [
            'id_sucursal' => $sid,
            'nombre' => $r['nombre'],
            'grupos' => (int) $r['grupos'],
            'grupos_activos_status' => (int) $r['grupos_activos'],
            'grupos_dias' => 0,
            'grupos_materias' => 0,
            'asistencias' => 0,
        ];
        if (migcsv_table_exists($pdo, 'grupos_dias')) {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM grupos_dias gd
                 INNER JOIN grupos g ON g.id = gd.id_grupo WHERE g.id_sucursal = ?'
            );
            $st->execute([$sid]);
            $extra['grupos_dias'] = (int) $st->fetchColumn();
        }
        if (migcsv_table_exists($pdo, 'grupos_materias')) {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM grupos_materias gm
                 INNER JOIN grupos g ON g.id = gm.id_grupo WHERE g.id_sucursal = ?'
            );
            $st->execute([$sid]);
            $extra['grupos_materias'] = (int) $st->fetchColumn();
        }
        if (migcsv_table_exists($pdo, 'asistencias')) {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM asistencias a
                 INNER JOIN grupos g ON g.id = a.id_grupo WHERE g.id_sucursal = ?'
            );
            $st->execute([$sid]);
            $extra['asistencias'] = (int) $st->fetchColumn();
        }
        $out['por_sucursal'][] = $extra;
    }

    return $out;
}

/**
 * Normaliza dayofweek legado → 0=domingo … 6=sábado (PHP date('w')).
 * Acepta 0–6 tal cual; si viene 1–7 (MySQL DAYOFWEEK), convierte 1→0 … 7→6.
 */
function migcsv_normalizar_dow(?int $dow): ?int
{
    if ($dow === null) {
        return null;
    }
    if ($dow >= 0 && $dow <= 6) {
        return $dow;
    }
    if ($dow >= 1 && $dow <= 7) {
        // MySQL DAYOFWEEK: 1=Sunday … 7=Saturday
        return $dow === 7 ? 6 : $dow - 1;
    }

    return null;
}

function migcsv_inferir_dow_desde_texto(string $texto): array
{
    $t = mb_strtoupper($texto, 'UTF-8');
    $map = [
        0 => ['DOMINGO', 'DOM'],
        1 => ['LUNES', 'LUN'],
        2 => ['MARTES', 'MAR'],
        3 => ['MIERCOLES', 'MIÉRCOLES', 'MIE', 'MIÉ'],
        4 => ['JUEVES', 'JUE'],
        5 => ['VIERNES', 'VIE'],
        6 => ['SABADO', 'SÁBADO', 'SAB', 'SÁB'],
    ];
    $out = [];
    foreach ($map as $n => $words) {
        foreach ($words as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $t)) {
                $out[] = $n;
                break;
            }
        }
    }

    return array_values(array_unique($out));
}

/**
 * @param list<int> $idsGrupos
 * @return array<int, array{dias:list<int>, hora_inicio:?string, hora_fin:?string}>
 */
function migcsv_horarios_por_grupo(PDO $pdo, array $idsGrupos): array
{
    $out = [];
    foreach ($idsGrupos as $id) {
        $out[$id] = ['dias' => [], 'hora_inicio' => null, 'hora_fin' => null];
    }
    if ($idsGrupos === [] || !migcsv_table_exists($pdo, 'grupos_dias')) {
        return $out;
    }
    $ph = implode(',', array_fill(0, count($idsGrupos), '?'));
    $st = $pdo->prepare(
        "SELECT id_grupo, dayofweek, dia, hora_inicio, hora_final
         FROM grupos_dias WHERE id_grupo IN ($ph) ORDER BY id_grupo, hora_inicio"
    );
    $st->execute($idsGrupos);
    foreach ($st->fetchAll() as $r) {
        $gid = (int) $r['id_grupo'];
        $dow = migcsv_normalizar_dow(isset($r['dayofweek']) ? (int) $r['dayofweek'] : null);
        if ($dow === null && !empty($r['dia'])) {
            $inf = migcsv_inferir_dow_desde_texto((string) $r['dia']);
            $dow = $inf[0] ?? null;
        }
        if ($dow !== null) {
            $out[$gid]['dias'][] = $dow;
        }
        if ($out[$gid]['hora_inicio'] === null && !empty($r['hora_inicio'])) {
            $out[$gid]['hora_inicio'] = substr((string) $r['hora_inicio'], 0, 5);
        }
        if ($out[$gid]['hora_fin'] === null && !empty($r['hora_final'])) {
            $out[$gid]['hora_fin'] = substr((string) $r['hora_final'], 0, 5);
        }
    }
    foreach ($out as $gid => $h) {
        $out[$gid]['dias'] = array_values(array_unique($h['dias']));
        sort($out[$gid]['dias']);
    }

    return $out;
}

/**
 * @param list<int> $idsGrupos
 * @return array<int, array{id:?int, nombre:string, todos:string}>
 */
function migcsv_profesores_por_grupo(PDO $pdo, array $idsGrupos): array
{
    $out = [];
    foreach ($idsGrupos as $id) {
        $out[$id] = ['id' => null, 'nombre' => '', 'todos' => ''];
    }
    if ($idsGrupos === [] || !migcsv_table_exists($pdo, 'grupos_materias')) {
        return $out;
    }
    $ph = implode(',', array_fill(0, count($idsGrupos), '?'));
    $hasOrden = migcsv_column_exists($pdo, 'grupos_materias', 'orden');
    $order = $hasOrden ? 'ORDER BY gm.id_grupo, gm.orden ASC, gm.id ASC' : 'ORDER BY gm.id_grupo, gm.id ASC';
    $st = $pdo->prepare(
        "SELECT gm.id_grupo, gm.id_profesor,
                CONCAT(COALESCE(u.nombres,''), ' ', COALESCE(u.apellido_paterno,''), ' ', COALESCE(u.apellido_materno,'')) AS nombre
         FROM grupos_materias gm
         LEFT JOIN users u ON u.id = gm.id_profesor
         WHERE gm.id_grupo IN ($ph) AND gm.id_profesor IS NOT NULL
         $order"
    );
    $st->execute($idsGrupos);
    $seen = [];
    foreach ($st->fetchAll() as $r) {
        $gid = (int) $r['id_grupo'];
        $pid = (int) $r['id_profesor'];
        $nom = trim(preg_replace('/\s+/', ' ', (string) $r['nombre']) ?? '');
        if ($nom === '') {
            continue;
        }
        $key = $gid . ':' . $pid;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        if ($out[$gid]['id'] === null) {
            $out[$gid]['id'] = $pid;
            $out[$gid]['nombre'] = $nom;
        }
        $out[$gid]['todos'] = $out[$gid]['todos'] === ''
            ? $nom
            : ($out[$gid]['todos'] . ' | ' . $nom);
    }

    return $out;
}

/**
 * Primera asistencia del alumno en el grupo cuyo día de semana ∈ $diasClase (0–6).
 *
 * @param list<int> $diasClase
 */
function migcsv_primera_asistencia_en_dia_clase(
    PDO $pdo,
    int $idAlumno,
    int $idGrupo,
    array $diasClase
): ?string {
    if (!migcsv_table_exists($pdo, 'asistencias')) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT DATE(fecha) AS f FROM asistencias
         WHERE id_alumno = ? AND id_grupo = ?
         ORDER BY fecha ASC'
    );
    $st->execute([$idAlumno, $idGrupo]);
    $diasSet = array_fill_keys($diasClase, true);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $f) {
        $f = (string) $f;
        if ($f === '') {
            continue;
        }
        $ts = strtotime($f);
        if ($ts === false) {
            continue;
        }
        $dow = (int) date('w', $ts);
        if ($diasClase === [] || isset($diasSet[$dow])) {
            return $f;
        }
    }

    return null;
}

/**
 * Moda de fechas; en empate, la más antigua entre las empatadas.
 *
 * @param list<string> $fechas Y-m-d
 */
function migcsv_moda_fecha(array $fechas): ?string
{
    if ($fechas === []) {
        return null;
    }
    $counts = array_count_values($fechas);
    arsort($counts);
    $max = reset($counts);
    $candidatas = [];
    foreach ($counts as $f => $c) {
        if ($c === $max) {
            $candidatas[] = $f;
        }
    }
    sort($candidatas);

    return $candidatas[0] ?? null;
}

/**
 * Estima índice de fase (0-based) por semanas desde fecha_inicio hasta hoy.
 * Asume ~4 semanas por parcial salvo que se pase $semanasPorFase.
 */
function migcsv_estimar_indice_fase(string $fechaInicio, int $numFases = 12, int $semanasPorFase = 4): int
{
    $ts = strtotime($fechaInicio);
    if ($ts === false) {
        return 0;
    }
    $dias = max(0, (int) floor((time() - $ts) / 86400));
    $semanas = (int) floor($dias / 7);
    $idx = (int) floor($semanas / max(1, $semanasPorFase));

    return max(0, min($numFases - 1, $idx));
}

/** Etiquetas tentativas ING (A1-1 …); otras especialidades usan F1, F2… */
function migcsv_etiqueta_fase_estimada(string $espNombre, int $indice): string
{
    $n = mb_strtoupper($espNombre, 'UTF-8');
    $esIngles = str_contains($n, 'INGL') || str_contains($n, 'ENGLISH');
    if ($esIngles) {
        $labels = [
            'A1-1', 'A1-2', 'A1-3', 'A2-1', 'A2-2', 'A2-3',
            'B1-1', 'B1-2', 'B1-3', 'B2-1', 'B2-2', 'B2-3',
            'PN', 'PF',
        ];

        return $labels[$indice] ?? ('F' . ($indice + 1));
    }

    return 'F' . ($indice + 1);
}

function migcsv_csv_enviar(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        return;
    }
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $h) {
            $line[] = $row[$h] ?? '';
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

function migcsv_slug_sucursal(string $nombre): string
{
    $s = mb_strtolower(trim($nombre), 'UTF-8');
    $s = strtr($s, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
    ]);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;

    return trim($s, '-') ?: 'plantel';
}

function migcsv_map_rol_legado(?string $roleName): string
{
    $r = mb_strtolower(trim((string) $roleName), 'UTF-8');
    if ($r === '') {
        return 'profesor';
    }
    if (str_contains($r, 'supervisor') || str_contains($r, 'super')) {
        return 'supervisor';
    }
    if (str_contains($r, 'director')) {
        return 'director';
    }
    if (str_contains($r, 'gerente') || str_contains($r, 'coord')) {
        return 'coordinador';
    }
    if (str_contains($r, 'asesor') || str_contains($r, 'venta')) {
        return 'asesor';
    }
    if (str_contains($r, 'recep') || str_contains($r, 'caja')) {
        return 'recepcion';
    }
    if (str_contains($r, 'admin')) {
        return 'admin';
    }

    return 'profesor';
}

/** @return list<array<string, mixed>> */
function migcsv_export_personal(PDO $pdo, ?int $idSucursal = null): array
{
    if (!migcsv_table_exists($pdo, 'users')) {
        return [];
    }
    $del = migcsv_column_exists($pdo, 'users', 'deleted_at') ? ' WHERE u.deleted_at IS NULL' : '';
    $rows = $pdo->query("SELECT u.* FROM users u{$del} ORDER BY u.id")->fetchAll();
    $out = [];
    foreach ($rows as $u) {
        $uid = (int) $u['id'];
        $sucursales = [];
        if (migcsv_table_exists($pdo, 'sucursales_usuarios')) {
            $st = $pdo->prepare(
                'SELECT s.id, s.nombre FROM sucursales_usuarios su
                 INNER JOIN sucursales s ON s.id = su.id_sucursal
                 WHERE su.id_usuario = ?'
            );
            $st->execute([$uid]);
            $sucursales = $st->fetchAll();
        }
        if ($idSucursal !== null && $idSucursal > 0) {
            $ok = false;
            foreach ($sucursales as $s) {
                if ((int) $s['id'] === $idSucursal) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok && (int) ($u['id_ultima_sucursal'] ?? 0) !== $idSucursal) {
                continue;
            }
        }
        $rolName = '';
        if (migcsv_table_exists($pdo, 'model_has_roles') && migcsv_table_exists($pdo, 'roles')) {
            $st = $pdo->prepare(
                'SELECT r.name, r.display_name FROM model_has_roles mhr
                 INNER JOIN roles r ON r.id = mhr.role_id
                 WHERE mhr.model_id = ? LIMIT 1'
            );
            $st->execute([$uid]);
            $rr = $st->fetch();
            if ($rr) {
                $rolName = (string) ($rr['display_name'] ?: $rr['name']);
            }
        }
        $plantel = '';
        if ($sucursales !== []) {
            $plantel = migcsv_slug_sucursal((string) $sucursales[0]['nombre']);
        } elseif (!empty($u['id_ultima_sucursal']) && migcsv_table_exists($pdo, 'sucursales')) {
            $st = $pdo->prepare('SELECT nombre FROM sucursales WHERE id = ?');
            $st->execute([(int) $u['id_ultima_sucursal']]);
            $plantel = migcsv_slug_sucursal((string) $st->fetchColumn());
        }
        $out[] = [
            'id_usuario_legado' => $uid,
            'nombres' => trim((string) ($u['nombres'] ?? '')),
            'apellido_paterno' => trim((string) ($u['apellido_paterno'] ?? '')),
            'apellido_materno' => trim((string) ($u['apellido_materno'] ?? '')),
            'email_legado' => trim((string) ($u['email'] ?? '')),
            'rol_legado' => $rolName,
            'rol' => migcsv_map_rol_legado($rolName),
            'plantel_slug' => $plantel,
            'username' => '',
            'notas' => '',
        ];
    }

    return $out;
}

/**
 * @param list<int> $idsGrupos
 * @return list<array<string, mixed>>
 */
function migcsv_export_grupos(PDO $pdo, array $idsGrupos): array
{
    if ($idsGrupos === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($idsGrupos), '?'));
    $st = $pdo->prepare(
        "SELECT g.*, e.nombre AS especialidad_nombre, s.nombre AS sucursal_nombre
         FROM grupos g
         LEFT JOIN especialidades e ON e.id = g.id_especialidad
         LEFT JOIN sucursales s ON s.id = g.id_sucursal
         WHERE g.id IN ($ph)
         ORDER BY g.id_sucursal, g.id"
    );
    $st->execute($idsGrupos);
    $grupos = $st->fetchAll();
    $horarios = migcsv_horarios_por_grupo($pdo, $idsGrupos);
    $profs = migcsv_profesores_por_grupo($pdo, $idsGrupos);

    // Alumnos inscritos por grupo
    $alumnosPorGrupo = [];
    if (migcsv_table_exists($pdo, 'alumnos_grupos')) {
        $stAg = $pdo->prepare(
            "SELECT id_alumno, id_grupo, fecha_inicio, status FROM alumnos_grupos
             WHERE id_grupo IN ($ph)
               AND (status IS NULL OR status = '' OR status = 'Inscrito')"
        );
        $stAg->execute($idsGrupos);
        foreach ($stAg->fetchAll() as $ag) {
            $alumnosPorGrupo[(int) $ag['id_grupo']][] = $ag;
        }
    }

    $out = [];
    foreach ($grupos as $g) {
        $gid = (int) $g['id'];
        $h = $horarios[$gid] ?? ['dias' => [], 'hora_inicio' => null, 'hora_fin' => null];
        $dias = $h['dias'];
        if ($dias === [] && !empty($g['dias'])) {
            $dias = migcsv_inferir_dow_desde_texto((string) $g['dias'] . ' ' . (string) ($g['horario'] ?? ''));
        }
        $horaIni = $h['hora_inicio'];
        if ($horaIni === null && !empty($g['horario']) && preg_match('/(\d{1,2}:\d{2})/', (string) $g['horario'], $m)) {
            $horaIni = $m[1];
        }

        $fechasAlumno = [];
        foreach ($alumnosPorGrupo[$gid] ?? [] as $ag) {
            $idAl = (int) $ag['id_alumno'];
            $f = migcsv_primera_asistencia_en_dia_clase($pdo, $idAl, $gid, $dias);
            if ($f === null && !empty($ag['fecha_inicio'])) {
                $f = (string) $ag['fecha_inicio'];
            }
            if ($f !== null) {
                $fechasAlumno[] = $f;
            }
        }
        $fechaEst = migcsv_moda_fecha($fechasAlumno);
        if ($fechaEst === null && !empty($g['fecha_inicio'])) {
            $fechaEst = substr((string) $g['fecha_inicio'], 0, 10);
        }
        $espNom = (string) ($g['especialidad_nombre'] ?? '');
        $idxFase = $fechaEst ? migcsv_estimar_indice_fase($fechaEst) : 0;
        $faseEst = $fechaEst ? migcsv_etiqueta_fase_estimada($espNom, $idxFase) : '';

        $p = $profs[$gid] ?? ['id' => null, 'nombre' => '', 'todos' => ''];
        $infantil = !empty($g['infantil']) ? '1' : '0';

        $out[] = [
            'id_grupo_legado' => $gid,
            'clave_legado' => (string) ($g['clave'] ?? ''),
            'id_sucursal_legado' => (int) ($g['id_sucursal'] ?? 0),
            'sucursal' => (string) ($g['sucursal_nombre'] ?? ''),
            'plantel_slug' => migcsv_slug_sucursal((string) ($g['sucursal_nombre'] ?? '')),
            'especialidad_legado' => $espNom,
            'clave_especialidad' => $infantil === '1' ? 'ING-K' : '',
            'infantil' => $infantil,
            'fecha_inicio_legado' => !empty($g['fecha_inicio']) ? substr((string) $g['fecha_inicio'], 0, 10) : '',
            'fecha_inicio_estimada' => $fechaEst ?? '',
            'clave_fase_estimada' => $faseEst,
            'horario_legado' => trim((string) ($g['horario'] ?? '') . ' ' . (string) ($g['dias'] ?? '')),
            'dias_clase' => implode(',', $dias),
            'hora_inicio' => $horaIni ?? '',
            'hora_fin' => $h['hora_fin'] ?? '',
            'id_profesor_legado' => $p['id'] ?? '',
            'profesor_sugerido' => $p['nombre'],
            'profesores_grupo' => $p['todos'],
            'profesor_username' => '',
            'aula_codigo' => '',
            'status_legado' => (string) ($g['status'] ?? ''),
            'max_alumnos' => $g['max_alumnos'] ?? '',
            'alumnos_activos_count' => count($alumnosPorGrupo[$gid] ?? []),
        ];
    }

    return $out;
}

/**
 * @param list<int> $idsGrupos
 * @return list<array<string, mixed>>
 */
function migcsv_export_alumnos(PDO $pdo, array $idsGrupos): array
{
    if ($idsGrupos === [] || !migcsv_table_exists($pdo, 'alumnos_grupos')) {
        return [];
    }
    $horarios = migcsv_horarios_por_grupo($pdo, $idsGrupos);
    $ph = implode(',', array_fill(0, count($idsGrupos), '?'));
    $delAl = migcsv_column_exists($pdo, 'alumnos', 'deleted_at') ? ' AND a.deleted_at IS NULL' : '';
    $st = $pdo->prepare(
        "SELECT ag.id_grupo, ag.fecha_inicio AS ag_fecha_inicio, ag.status AS ag_status,
                a.*, s.nombre AS sucursal_nombre,
                CONCAT(COALESCE(ua.nombres,''),' ',COALESCE(ua.apellido_paterno,''),' ',COALESCE(ua.apellido_materno,'')) AS asesor_nombre
         FROM alumnos_grupos ag
         INNER JOIN alumnos a ON a.id = ag.id_alumno
         LEFT JOIN sucursales s ON s.id = a.id_sucursal
         LEFT JOIN users ua ON ua.id = a.id_asesor_educativo
         WHERE ag.id_grupo IN ($ph)
           AND (ag.status IS NULL OR ag.status = '' OR ag.status = 'Inscrito')
           $delAl
         ORDER BY ag.id_grupo, a.id"
    );
    $st->execute($idsGrupos);
    $out = [];
    $seen = [];
    foreach ($st->fetchAll() as $r) {
        $idAl = (int) $r['id'];
        $gid = (int) $r['id_grupo'];
        $key = $idAl . ':' . $gid;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $dias = $horarios[$gid]['dias'] ?? [];
        if ($dias === []) {
            $stG = $pdo->prepare('SELECT dias, horario FROM grupos WHERE id = ?');
            $stG->execute([$gid]);
            $g = $stG->fetch() ?: [];
            $dias = migcsv_inferir_dow_desde_texto((string) ($g['dias'] ?? '') . ' ' . (string) ($g['horario'] ?? ''));
        }
        $fechaIni = migcsv_primera_asistencia_en_dia_clase($pdo, $idAl, $gid, $dias);
        if ($fechaIni === null && !empty($r['ag_fecha_inicio'])) {
            $fechaIni = (string) $r['ag_fecha_inicio'];
        }
        $nc = (int) ($r['nuevo_numero_control'] ?? 0);
        if ($nc <= 0) {
            $nc = (int) ($r['numero_control'] ?? 0);
        }
        // Asesor desde inscripción si falta
        $idAsesor = (int) ($r['id_asesor_educativo'] ?? 0);
        $asesorNom = trim(preg_replace('/\s+/', ' ', (string) ($r['asesor_nombre'] ?? '')) ?? '');
        if ($idAsesor <= 0 && migcsv_table_exists($pdo, 'inscripciones')) {
            $stI = $pdo->prepare(
                'SELECT i.id_asesor,
                        CONCAT(COALESCE(u.nombres,\'\'),\' \',COALESCE(u.apellido_paterno,\'\'),\' \',COALESCE(u.apellido_materno,\'\')) AS nom
                 FROM inscripciones i
                 LEFT JOIN users u ON u.id = i.id_asesor
                 WHERE i.id_alumno = ? AND i.id_grupo = ?
                 ORDER BY i.id DESC LIMIT 1'
            );
            $stI->execute([$idAl, $gid]);
            $ins = $stI->fetch();
            if ($ins && !empty($ins['id_asesor'])) {
                $idAsesor = (int) $ins['id_asesor'];
                $asesorNom = trim(preg_replace('/\s+/', ' ', (string) $ins['nom']) ?? '');
            }
        }
        $out[] = [
            'id_alumno_legado' => $idAl,
            'numero_control' => $nc > 0 ? (string) $nc : '',
            'nombres' => trim((string) ($r['nombres'] ?? '')),
            'apellido_paterno' => trim((string) ($r['apellido_paterno'] ?? '')),
            'apellido_materno' => trim((string) ($r['apellido_materno'] ?? '')),
            'email' => trim((string) ($r['email'] ?? '')),
            'telefono' => trim((string) (($r['telefono'] ?? '') ?: ($r['celular'] ?? ''))),
            'id_sucursal_legado' => (int) ($r['id_sucursal'] ?? 0),
            'plantel_slug' => migcsv_slug_sucursal((string) ($r['sucursal_nombre'] ?? '')),
            'id_grupo_legado' => $gid,
            'fecha_inicio' => $fechaIni ?? '',
            'forma_pago' => trim((string) ($r['forma_pago'] ?? 'mensual')),
            'id_asesor_legado' => $idAsesor > 0 ? $idAsesor : '',
            'asesor_nombre' => $asesorNom,
            'status_legado' => (string) ($r['status'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @param list<int> $idsGrupos
 * @return list<array<string, mixed>>
 */
function migcsv_export_pagos(PDO $pdo, array $idsGrupos): array
{
    if ($idsGrupos === [] || !migcsv_table_exists($pdo, 'pagos') || !migcsv_table_exists($pdo, 'alumnos_grupos')) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($idsGrupos), '?'));
    $stAl = $pdo->prepare(
        "SELECT DISTINCT ag.id_alumno FROM alumnos_grupos ag
         WHERE ag.id_grupo IN ($ph)
           AND (ag.status IS NULL OR ag.status = '' OR ag.status = 'Inscrito')"
    );
    $stAl->execute($idsGrupos);
    $idsAlumnos = array_map('intval', $stAl->fetchAll(PDO::FETCH_COLUMN));
    if ($idsAlumnos === []) {
        return [];
    }
    $ncMap = [];
    $phA = implode(',', array_fill(0, count($idsAlumnos), '?'));
    $stNc = $pdo->prepare(
        "SELECT id, COALESCE(NULLIF(nuevo_numero_control, 0), numero_control) AS nc FROM alumnos WHERE id IN ($phA)"
    );
    $stNc->execute($idsAlumnos);
    foreach ($stNc->fetchAll() as $r) {
        $ncMap[(int) $r['id']] = (string) ($r['nc'] ?? '');
    }

    $out = [];
    $pgDel = migcsv_column_exists($pdo, 'pagos', 'deleted_at') ? ' AND p.deleted_at IS NULL' : '';

    if (migcsv_table_exists($pdo, 'abonos')) {
        $abDel = migcsv_column_exists($pdo, 'abonos', 'deleted_at') ? ' AND a.deleted_at IS NULL' : '';
        $st = $pdo->prepare(
            "SELECT a.id AS id_abono, a.monto AS monto_abono, a.created_at AS abono_en,
                    p.id AS id_pago, p.folio, p.fecha, p.forma_pago, p.id_alumno, p.monto AS monto_pago,
                    ap.concepto, ap.tipo AS tipo_cargo
             FROM abonos a
             INNER JOIN pagos p ON p.id = a.id_pago
             LEFT JOIN alumnos_pagos ap ON ap.id = a.id_alumno_pago
             WHERE p.id_alumno IN ($phA) $abDel $pgDel
             ORDER BY a.id"
        );
        $st->execute($idsAlumnos);
        foreach ($st->fetchAll() as $r) {
            $idAl = (int) $r['id_alumno'];
            $concepto = (string) ($r['concepto'] ?? '');
            $tipo = migcsv_tipo_pago_desde_concepto($concepto, (string) ($r['tipo_cargo'] ?? ''));
            $out[] = [
                'id_pago_legado' => 'abono-' . (int) $r['id_abono'],
                'numero_control' => $ncMap[$idAl] ?? '',
                'id_alumno_legado' => $idAl,
                'fecha' => !empty($r['fecha']) ? substr((string) $r['fecha'], 0, 19) : (string) ($r['abono_en'] ?? ''),
                'monto' => $r['monto_abono'] ?? $r['monto_pago'] ?? '',
                'forma_pago' => (string) ($r['forma_pago'] ?? 'Efectivo'),
                'tipo' => $tipo,
                'concepto' => $concepto !== '' ? $concepto : 'Pago legado',
                'folio' => $r['folio'] !== null && $r['folio'] !== '' ? ('LEG-' . $r['folio']) : '',
            ];
        }
    }

    // Pagos sin abonos
    $st = $pdo->prepare(
        "SELECT p.id, p.folio, p.fecha, p.monto, p.forma_pago, p.id_alumno
         FROM pagos p
         WHERE p.id_alumno IN ($phA) $pgDel
           AND NOT EXISTS (
             SELECT 1 FROM abonos a2 WHERE a2.id_pago = p.id"
        . (migcsv_column_exists($pdo, 'abonos', 'deleted_at') ? ' AND a2.deleted_at IS NULL' : '')
        . ')
         ORDER BY p.id'
    );
    if (migcsv_table_exists($pdo, 'abonos')) {
        $st->execute($idsAlumnos);
        foreach ($st->fetchAll() as $r) {
            $idAl = (int) $r['id_alumno'];
            $out[] = [
                'id_pago_legado' => 'pago-' . (int) $r['id'],
                'numero_control' => $ncMap[$idAl] ?? '',
                'id_alumno_legado' => $idAl,
                'fecha' => !empty($r['fecha']) ? substr((string) $r['fecha'], 0, 19) : '',
                'monto' => $r['monto'] ?? '',
                'forma_pago' => (string) ($r['forma_pago'] ?? 'Efectivo'),
                'tipo' => 'abono',
                'concepto' => 'Pago legado',
                'folio' => $r['folio'] !== null && $r['folio'] !== '' ? ('LEG-' . $r['folio']) : '',
            ];
        }
    }

    return $out;
}

function migcsv_tipo_pago_desde_concepto(string $concepto, string $tipoCargo = ''): string
{
    $c = mb_strtolower($concepto . ' ' . $tipoCargo, 'UTF-8');
    if (str_contains($c, 'inscrip')) {
        return 'inscripcion';
    }
    if (str_contains($c, 'semanal')) {
        return 'semanal';
    }
    if (str_contains($c, 'colegiat') || str_contains($c, 'mensual')) {
        return 'mensualidad';
    }

    return 'abono';
}

/** Plantilla vacía de aulas (manual). */
function migcsv_plantilla_aulas(?string $plantelSlug = null): array
{
    return [[
        'plantel_slug' => $plantelSlug ?? '',
        'codigo' => 'A1',
        'nombre' => 'Aula 1',
        'piso' => '',
        'capacidad' => '20',
        'notas' => 'Completar códigos reales; capacidad/proyector se editan después en HAY',
    ]];
}
