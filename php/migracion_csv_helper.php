<?php

/**
 * Importación selectiva legado → HAY vía CSV (sin conexión a BD legado en el servidor).
 */

function migracion_csv_puede(): bool
{
    $rol = function_exists('rbac_rol_real') ? rbac_rol_real() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['supervisor', 'gerente', 'director', 'admin'], true);
}

function migracion_csv_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_migracion_csv_map (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entidad VARCHAR(40) NOT NULL,
            id_legacy VARCHAR(80) NOT NULL,
            id_hay INT UNSIGNED NOT NULL,
            detalle VARCHAR(255) NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_migcsv_ent_leg (entidad, id_legacy),
            KEY idx_migcsv_hay (entidad, id_hay)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS hay_migracion_csv_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tipo VARCHAR(40) NOT NULL,
            nivel VARCHAR(20) NOT NULL DEFAULT \'info\',
            mensaje TEXT NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_migcsv_log_tipo (tipo, creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function migracion_csv_log(PDO $pdo, string $tipo, string $nivel, string $mensaje): void
{
    migracion_csv_ensure_schema($pdo);
    $pdo->prepare(
        'INSERT INTO hay_migracion_csv_log (tipo, nivel, mensaje) VALUES (?,?,?)'
    )->execute([$tipo, $nivel, $mensaje]);
}

function migracion_csv_map_get(PDO $pdo, string $entidad, string $idLegacy): ?int
{
    $st = $pdo->prepare(
        'SELECT id_hay FROM hay_migracion_csv_map WHERE entidad = ? AND id_legacy = ? LIMIT 1'
    );
    $st->execute([$entidad, $idLegacy]);
    $v = $st->fetchColumn();

    return $v === false ? null : (int) $v;
}

function migracion_csv_map_set(PDO $pdo, string $entidad, string $idLegacy, int $idHay, ?string $detalle = null): void
{
    $pdo->prepare(
        'INSERT INTO hay_migracion_csv_map (entidad, id_legacy, id_hay, detalle)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE id_hay = VALUES(id_hay), detalle = VALUES(detalle)'
    )->execute([$entidad, $idLegacy, $idHay, $detalle]);
}

/**
 * @return array{headers:list<string>, rows:list<array<string, string>>, error?:string}
 */
function migracion_csv_leer_archivo(string $tmpPath): array
{
    if (!is_readable($tmpPath)) {
        return ['headers' => [], 'rows' => [], 'error' => 'No se pudo leer el archivo'];
    }
    $fh = fopen($tmpPath, 'r');
    if ($fh === false) {
        return ['headers' => [], 'rows' => [], 'error' => 'No se pudo abrir el CSV'];
    }
    $header = fgetcsv($fh);
    if ($header === false || $header === []) {
        fclose($fh);

        return ['headers' => [], 'rows' => [], 'error' => 'CSV vacío'];
    }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
    $headers = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
    $rows = [];
    while (($data = fgetcsv($fh)) !== false) {
        if ($data === [null] || $data === false) {
            continue;
        }
        $row = [];
        foreach ($headers as $i => $col) {
            $row[$col] = isset($data[$i]) ? trim((string) $data[$i]) : '';
        }
        // skip empty
        $allEmpty = true;
        foreach ($row as $v) {
            if ($v !== '') {
                $allEmpty = false;
                break;
            }
        }
        if ($allEmpty) {
            continue;
        }
        $rows[] = $row;
    }
    fclose($fh);

    return ['headers' => $headers, 'rows' => $rows];
}

function migracion_csv_resolver_plantel(PDO $pdo, string $slugOrName, ?int $forzarId = null): ?int
{
    if ($forzarId !== null && $forzarId > 0) {
        return $forzarId;
    }
    $q = trim($slugOrName);
    if ($q === '') {
        return function_exists('plantel_id_activo') ? plantel_id_activo() : null;
    }
    $st = $pdo->prepare(
        'SELECT id_plantel FROM planteles
         WHERE LOWER(slug) = LOWER(?) OR LOWER(nombre) = LOWER(?) OR LOWER(nombre) LIKE LOWER(?)
         ORDER BY id_plantel ASC LIMIT 1'
    );
    $st->execute([$q, $q, '%' . $q . '%']);
    $id = $st->fetchColumn();

    return $id === false ? null : (int) $id;
}

function migracion_csv_resolver_especialidad(PDO $pdo, string $clave): ?int
{
    $clave = strtoupper(trim($clave));
    if ($clave === '') {
        return null;
    }
    $st = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE UPPER(clave) = ? LIMIT 1');
    $st->execute([$clave]);
    $id = $st->fetchColumn();

    return $id === false ? null : (int) $id;
}

/**
 * @return list<int>
 */
function migracion_csv_parse_dias(string $dias): array
{
    $out = [];
    foreach (preg_split('/[,\s;]+/', $dias) ?: [] as $p) {
        $p = trim($p);
        if ($p === '' || !ctype_digit($p)) {
            continue;
        }
        $n = (int) $p;
        if ($n >= 0 && $n <= 6) {
            $out[] = $n;
        }
    }

    return array_values(array_unique($out));
}

function migracion_csv_infer_codigo_horario(array $dias, string $horaInicio): string
{
    sort($dias);
    if ($dias === [6]) {
        return 'S';
    }
    if ($dias === [0]) {
        return 'D';
    }
    $h = 8;
    if (preg_match('/^(\d{1,2})/', $horaInicio, $m)) {
        $h = (int) $m[1];
    }

    return $h >= 14 ? 'V' : 'M';
}

function migracion_csv_hora_fin(string $horaInicio, int $minutos = 120): string
{
    $horaInicio = trim($horaInicio);
    if ($horaInicio === '') {
        return '10:00';
    }
    if (!str_contains($horaInicio, ':')) {
        $horaInicio .= ':00';
    }
    $ts = strtotime('2000-01-01 ' . $horaInicio);
    if ($ts === false) {
        return '10:00';
    }

    return date('H:i', $ts + $minutos * 60);
}

function migracion_csv_resolver_fase(
    PDO $pdo,
    int $idEspecialidad,
    string $claveFaseEstimada,
    string $fechaInicio
): ?int {
    if (!function_exists('fase_listar')) {
        fase_ensure_schema($pdo);
    }
    $fases = fase_listar($pdo, $idEspecialidad);
    if ($fases === []) {
        return grupo_primera_fase($pdo, $idEspecialidad);
    }
    $clave = strtoupper(trim($claveFaseEstimada));
    if ($clave !== '') {
        foreach ($fases as $f) {
            $cf = strtoupper(trim((string) ($f['clave_fase'] ?? '')));
            $nf = strtoupper(trim((string) ($f['nombre_fase'] ?? '')));
            if ($cf === $clave || $nf === $clave || str_contains($nf, $clave)) {
                return (int) $f['id_fase'];
            }
        }
    }
    // Índice por semanas
    $semanasPorFase = 4;
    $st = $pdo->prepare('SELECT duracion_fase_semanas FROM especialidades WHERE id_especialidad = ?');
    $st->execute([$idEspecialidad]);
    $d = (int) $st->fetchColumn();
    if ($d > 0) {
        $semanasPorFase = $d;
    }
    $ts = strtotime($fechaInicio);
    $idx = 0;
    if ($ts !== false) {
        $semanas = (int) floor(max(0, time() - $ts) / 86400 / 7);
        $idx = (int) floor($semanas / max(1, $semanasPorFase));
        $idx = max(0, min(count($fases) - 1, $idx));
    }

    return (int) ($fases[$idx]['id_fase'] ?? $fases[0]['id_fase']);
}

function migracion_csv_buscar_profesor(PDO $pdo, int $idPlantel, string $username, string $nombreSugerido): ?int
{
    $username = trim($username);
    if ($username !== '') {
        $st = $pdo->prepare(
            'SELECT id_usuario FROM usuarios
             WHERE (username = ? OR email LIKE ?) AND id_plantel = ?
             LIMIT 1'
        );
        $st->execute([$username, $username . '%', $idPlantel]);
        $id = $st->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        // sin filtro plantel
        $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = ? OR email LIKE ? LIMIT 1');
        $st->execute([$username, $username . '%']);
        $id = $st->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
    }
    $nom = trim(preg_replace('/\s+/', ' ', $nombreSugerido) ?? '');
    if ($nom === '') {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT id_usuario FROM usuarios
         WHERE id_plantel = ?
           AND LOWER(CONCAT(nombre, \' \', apellido)) = LOWER(?)
         LIMIT 1'
    );
    $st->execute([$idPlantel, $nom]);
    $id = $st->fetchColumn();

    return $id === false ? null : (int) $id;
}

function migracion_csv_asegurar_aula(PDO $pdo, int $idPlantel, string $codigo): ?int
{
    $codigo = trim($codigo);
    if ($codigo === '') {
        return null;
    }
    aula_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT id_aula FROM plantel_aulas WHERE id_plantel = ? AND codigo = ? LIMIT 1');
    $st->execute([$idPlantel, $codigo]);
    $id = $st->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $res = aula_guardar($pdo, $idPlantel, [
        'codigo' => $codigo,
        'nombre' => $codigo,
        'capacidad' => 20,
        'activo' => 1,
        'todas_especialidades' => 1,
        'tiene_pizarron' => 1,
    ]);

    return !empty($res['ok']) ? (int) ($res['id_aula'] ?? 0) ?: null : null;
}

/**
 * @return array{ok:bool, message:string, creados?:int, actualizados?:int, errores?:list<string>}
 */
function migracion_csv_importar_personal(PDO $pdo, array $rows, ?int $forzarPlantel = null): array
{
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    migracion_csv_ensure_schema($pdo);
    if (function_exists('usuario_ensure_schema')) {
        usuario_ensure_schema($pdo);
    }
    $creados = 0;
    $actualizados = 0;
    $errores = [];
    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $idLeg = trim((string) ($r['id_usuario_legado'] ?? ''));
        $nombres = trim((string) ($r['nombres'] ?? ''));
        $apPat = trim((string) ($r['apellido_paterno'] ?? ''));
        $apMat = trim((string) ($r['apellido_materno'] ?? ''));
        if ($nombres === '' && $apPat === '') {
            continue;
        }
        $idPlantel = migracion_csv_resolver_plantel($pdo, (string) ($r['plantel_slug'] ?? ''), $forzarPlantel);
        if ($idPlantel === null || $idPlantel <= 0) {
            $errores[] = "Línea {$line}: plantel no resuelto";
            continue;
        }
        $rol = trim((string) ($r['rol'] ?? 'profesor'));
        if ($rol === '') {
            $rol = 'profesor';
        }
        $username = trim((string) ($r['username'] ?? ''));
        $emailLeg = strtolower(trim((string) ($r['email_legado'] ?? '')));
        if ($username === '') {
            $local = strtolower(preg_replace('/[^a-z0-9]/i', '', substr($nombres, 0, 1) . $apPat) ?? '');
            $username = $local !== '' ? $local : ('user' . ($idLeg !== '' ? $idLeg : $line));
        }
        $email = $emailLeg;
        if ($email === '' || !str_contains($email, '@')) {
            $dom = defined('INSTITUTIONAL_EMAIL_DOMAIN') ? INSTITUTIONAL_EMAIL_DOMAIN : 'cncm.edu.mx';
            $email = $username . '@' . $dom;
        }
        $existing = null;
        if ($idLeg !== '') {
            $existing = migracion_csv_map_get($pdo, 'usuario', $idLeg);
        }
        if ($existing === null) {
            $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = ? OR email = ? LIMIT 1');
            $st->execute([$username, $email]);
            $ex = $st->fetchColumn();
            if ($ex !== false) {
                $existing = (int) $ex;
            }
        }
        $apellido = trim($apPat . ' ' . $apMat);
        if ($existing !== null && $existing > 0) {
            $pdo->prepare(
                'UPDATE usuarios SET nombre=?, apellido=?, rol=?, id_plantel=?, email=? WHERE id_usuario=?'
            )->execute([$nombres, $apellido, $rol, $idPlantel, $email, $existing]);
            if ($idLeg !== '') {
                migracion_csv_map_set($pdo, 'usuario', $idLeg, $existing, $username);
            }
            $actualizados++;
        } else {
            $pass = function_exists('cuenta_password_inicial') ? cuenta_password_inicial() : 'Cncm*1234';
            $pdo->prepare(
                'INSERT INTO usuarios (nombre, apellido, username, email, password, rol, departamento, id_plantel, fecha_creacion)
                 VALUES (?,?,?,?,?,?,?,?,NOW())'
            )->execute([
                $nombres,
                $apellido,
                $username,
                $email,
                password_hash($pass, PASSWORD_BCRYPT),
                $rol,
                '',
                $idPlantel,
            ]);
            $idHay = (int) $pdo->lastInsertId();
            if ($idLeg !== '') {
                migracion_csv_map_set($pdo, 'usuario', $idLeg, $idHay, $username);
            }
            $creados++;
        }
    }
    migracion_csv_log($pdo, 'personal', 'info', "Personal: {$creados} creados, {$actualizados} actualizados");

    return [
        'ok' => true,
        'message' => "{$creados} creado(s), {$actualizados} actualizado(s)",
        'creados' => $creados,
        'actualizados' => $actualizados,
        'errores' => array_slice($errores, 0, 30),
    ];
}

/**
 * @return array{ok:bool, message:string, creados?:int, errores?:list<string>}
 */
function migracion_csv_importar_aulas(PDO $pdo, array $rows, ?int $forzarPlantel = null): array
{
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    aula_ensure_schema($pdo);
    $creados = 0;
    $errores = [];
    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $codigo = trim((string) ($r['codigo'] ?? ''));
        if ($codigo === '') {
            continue;
        }
        $idPlantel = migracion_csv_resolver_plantel($pdo, (string) ($r['plantel_slug'] ?? ''), $forzarPlantel);
        if ($idPlantel === null) {
            $errores[] = "Línea {$line}: plantel no resuelto";
            continue;
        }
        $id = migracion_csv_asegurar_aula($pdo, $idPlantel, $codigo);
        if ($id) {
            // actualizar nombre si viene
            $nombre = trim((string) ($r['nombre'] ?? ''));
            if ($nombre !== '') {
                $pdo->prepare('UPDATE plantel_aulas SET nombre = ? WHERE id_aula = ?')->execute([$nombre, $id]);
            }
            $creados++;
        } else {
            $errores[] = "Línea {$line}: no se pudo crear aula {$codigo}";
        }
    }

    return [
        'ok' => true,
        'message' => "{$creados} aula(s) asegurada(s)",
        'creados' => $creados,
        'errores' => array_slice($errores, 0, 30),
    ];
}

/**
 * @return array{ok:bool, message:string, creados?:int, omitidos?:int, errores?:list<string>}
 */
function migracion_csv_importar_grupos(PDO $pdo, array $rows, ?int $forzarPlantel = null): array
{
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    migracion_csv_ensure_schema($pdo);
    fase_ensure_schema($pdo);
    grupo_clave_ensure_schema($pdo);
    asistencia_ensure_schema($pdo);
    if (function_exists('plantel_ensure_column')) {
        plantel_ensure_column($pdo, 'grupos', 'clave_anterior', 'VARCHAR(50) NULL', 'clave');
        plantel_ensure_column($pdo, 'grupos', 'id_aula', 'INT UNSIGNED NULL', 'aula');
    }
    if (function_exists('aula_ensure_schema')) {
        aula_ensure_schema($pdo);
    }

    $creados = 0;
    $omitidos = 0;
    $errores = [];

    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $idLeg = trim((string) ($r['id_grupo_legado'] ?? ''));
        if ($idLeg !== '' && migracion_csv_map_get($pdo, 'grupo', $idLeg) !== null) {
            $omitidos++;
            continue;
        }
        $idPlantel = migracion_csv_resolver_plantel(
            $pdo,
            (string) ($r['plantel_slug'] ?? $r['sucursal'] ?? ''),
            $forzarPlantel
        );
        if ($idPlantel === null) {
            $errores[] = "Línea {$line}: plantel no resuelto";
            continue;
        }
        $infantil = !empty($r['infantil']) && (string) $r['infantil'] !== '0';
        $claveEsp = strtoupper(trim((string) ($r['clave_especialidad'] ?? '')));
        if ($claveEsp === '' && $infantil) {
            $claveEsp = 'ING-K';
        }
        if ($claveEsp === '') {
            $errores[] = "Línea {$line}: falta clave_especialidad";
            continue;
        }
        $esKids = $infantil || in_array($claveEsp, ['ING-K', 'ING-K-EXT', 'COMP-K'], true)
            || str_ends_with($claveEsp, '-K');

        $fecha = trim((string) ($r['fecha_inicio_estimada'] ?? ''));
        if ($fecha === '') {
            $fecha = trim((string) ($r['fecha_inicio_legado'] ?? ''));
        }
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }
        $dias = migracion_csv_parse_dias((string) ($r['dias_clase'] ?? ''));
        $horaIni = trim((string) ($r['hora_inicio'] ?? '08:00'));
        if ($horaIni === '') {
            $horaIni = '08:00';
        }
        $horaFin = trim((string) ($r['hora_fin'] ?? ''));
        if ($horaFin === '') {
            $horaFin = migracion_csv_hora_fin($horaIni, $esKids ? 240 : 120);
        }
        $idProf = migracion_csv_buscar_profesor(
            $pdo,
            $idPlantel,
            (string) ($r['profesor_username'] ?? ''),
            (string) ($r['profesor_sugerido'] ?? '')
        );
        $idAula = migracion_csv_asegurar_aula($pdo, $idPlantel, (string) ($r['aula_codigo'] ?? ''));
        $claveFaseEst = trim((string) ($r['clave_fase_estimada'] ?? ''));
        $claveAnterior = trim((string) ($r['clave_legado'] ?? ''));
        $horarioTexto = trim((string) ($r['horario_legado'] ?? ''));
        if ($horarioTexto === '' && $dias !== []) {
            $horarioTexto = 'Días ' . implode(',', $dias) . ' ' . $horaIni;
        }

        try {
            if ($esKids) {
                $idEspIng = grupo_id_especialidad_infantil($pdo, 'ingles');
                $idEspComp = grupo_id_especialidad_infantil($pdo, 'computacion');
                $idFaseIng = $idEspIng
                    ? migracion_csv_resolver_fase($pdo, $idEspIng, $claveFaseEst, $fecha)
                    : null;
                $idFaseComp = $idEspComp
                    ? migracion_csv_resolver_fase($pdo, $idEspComp, $claveFaseEst, $fecha)
                    : null;
                $res = grupo_infantil_crear_pareja(
                    $pdo,
                    $idPlantel,
                    $fecha,
                    $idProf,
                    $idFaseIng,
                    $idFaseComp,
                    $dias,
                    $horaIni,
                    $horaFin,
                    $horarioTexto !== '' ? $horarioTexto : null
                );
                if (empty($res['ok'])) {
                    $errores[] = "Línea {$line}: " . ($res['message'] ?? 'error kids');
                    continue;
                }
                $idIng = (int) $res['id_grupo_ingles'];
                $idComp = (int) $res['id_grupo_computacion'];
                if ($claveAnterior !== '') {
                    $pdo->prepare('UPDATE grupos SET clave_anterior = ? WHERE id_grupo IN (?,?)')
                        ->execute([$claveAnterior, $idIng, $idComp]);
                }
                if ($idAula) {
                    $pdo->prepare('UPDATE grupos SET id_aula = ?, aula = ? WHERE id_grupo IN (?,?)')
                        ->execute([$idAula, (string) ($r['aula_codigo'] ?? ''), $idIng, $idComp]);
                }
                // codigo_horario
                $codH = migracion_csv_infer_codigo_horario($dias !== [] ? $dias : [6], $horaIni);
                $pdo->prepare('UPDATE grupos SET codigo_horario = ? WHERE id_grupo IN (?,?)')
                    ->execute([$codH, $idIng, $idComp]);
                if ($idLeg !== '') {
                    migracion_csv_map_set($pdo, 'grupo', $idLeg, $idIng, 'kids_ingles');
                    migracion_csv_map_set($pdo, 'grupo_comp', $idLeg, $idComp, 'kids_computacion');
                }
                $creados += 2;
            } else {
                $idEsp = migracion_csv_resolver_especialidad($pdo, $claveEsp);
                if ($idEsp === null) {
                    $errores[] = "Línea {$line}: especialidad {$claveEsp} no existe en HAY";
                    continue;
                }
                $area = 'I';
                if (str_starts_with($claveEsp, 'COMP') || str_starts_with($claveEsp, 'C')) {
                    $area = 'C';
                } elseif (str_starts_with($claveEsp, 'PREP-AB') || $claveEsp === 'PA') {
                    $area = 'PA';
                } elseif (str_starts_with($claveEsp, 'PREP')) {
                    $area = 'PE';
                }
                $ext = str_contains($claveEsp, 'EXT');
                $codH = migracion_csv_infer_codigo_horario($dias !== [] ? $dias : [6], $horaIni);
                $gen = grupo_clave_generar($pdo, $idPlantel, $area, $codH, $ext, false, '');
                $clave = (string) ($gen['clave'] ?? ('G' . $line));
                $idFase = migracion_csv_resolver_fase($pdo, $idEsp, $claveFaseEst, $fecha);
                $pdo->prepare(
                    'INSERT INTO grupos (
                        id_plantel, clave, clave_anterior, fecha_inicio, id_profesor, id_aula, aula,
                        id_especialidad, id_fase_actual, horario_texto,
                        codigo_area, codigo_horario, es_extensivo, es_personalizado, numero_secuencial
                     ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $idPlantel,
                    $clave,
                    $claveAnterior !== '' ? $claveAnterior : null,
                    $fecha,
                    $idProf,
                    $idAula,
                    trim((string) ($r['aula_codigo'] ?? '')) ?: null,
                    $idEsp,
                    $idFase,
                    $horarioTexto !== '' ? $horarioTexto : null,
                    $gen['codigo_area'] ?? $area,
                    $gen['codigo_horario'] ?? $codH,
                    !empty($gen['es_extensivo']) ? 1 : 0,
                    0,
                    $gen['numero_secuencial'] ?? null,
                ]);
                $idGrupo = (int) $pdo->lastInsertId();
                if ($dias !== []) {
                    grupo_guardar_horarios($pdo, $idGrupo, $dias, $horaIni, $horaFin);
                }
                if (function_exists('tutor_asignar_grupo')) {
                    tutor_asignar_grupo($pdo, $idGrupo);
                }
                if ($idLeg !== '') {
                    migracion_csv_map_set($pdo, 'grupo', $idLeg, $idGrupo, $clave);
                }
                $creados++;
            }
        } catch (Throwable $e) {
            $errores[] = "Línea {$line}: " . $e->getMessage();
        }
    }

    migracion_csv_log($pdo, 'grupos', 'info', "Grupos: {$creados} creados, {$omitidos} omitidos");

    return [
        'ok' => ($creados + $omitidos) > 0 || $errores === [],
        'message' => "{$creados} grupo(s) creado(s), {$omitidos} omitido(s)",
        'creados' => $creados,
        'omitidos' => $omitidos,
        'errores' => array_slice($errores, 0, 40),
    ];
}

/**
 * @return array{ok:bool, message:string, creados?:int, vinculados?:int, errores?:list<string>}
 */
function migracion_csv_importar_alumnos(PDO $pdo, array $rows, ?int $forzarPlantel = null): array
{
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    migracion_csv_ensure_schema($pdo);
    alumno_ensure_schema($pdo);
    $creados = 0;
    $vinculados = 0;
    $errores = [];

    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $nc = trim((string) ($r['numero_control'] ?? ''));
        $nombres = trim((string) ($r['nombres'] ?? ''));
        $apPat = trim((string) ($r['apellido_paterno'] ?? ''));
        $apMat = trim((string) ($r['apellido_materno'] ?? ''));
        $idGrupoLeg = trim((string) ($r['id_grupo_legado'] ?? ''));
        if ($nombres === '' && $nc === '') {
            continue;
        }
        $idPlantel = migracion_csv_resolver_plantel($pdo, (string) ($r['plantel_slug'] ?? ''), $forzarPlantel);
        if ($idPlantel === null) {
            $errores[] = "Línea {$line}: plantel no resuelto";
            continue;
        }
        $idGrupo = $idGrupoLeg !== '' ? migracion_csv_map_get($pdo, 'grupo', $idGrupoLeg) : null;
        $idGrupoComp = $idGrupoLeg !== '' ? migracion_csv_map_get($pdo, 'grupo_comp', $idGrupoLeg) : null;
        $gc = [];
        if ($idGrupo === null || $idGrupo <= 0) {
            $errores[] = "Línea {$line}: grupo legado {$idGrupoLeg} no importado aún";
            continue;
        }
        $gSt = $pdo->prepare('SELECT * FROM grupos WHERE id_grupo = ? LIMIT 1');
        $gSt->execute([$idGrupo]);
        $grupo = $gSt->fetch(PDO::FETCH_ASSOC);
        if (!$grupo) {
            $errores[] = "Línea {$line}: grupo HAY inexistente";
            continue;
        }
        $idEsp = (int) ($grupo['id_especialidad'] ?? 0);
        $fecha = trim((string) ($r['fecha_inicio'] ?? ''));
        if ($fecha === '') {
            $fecha = substr((string) ($grupo['fecha_inicio'] ?? date('Y-m-d')), 0, 10);
        }
        $formaPago = strtolower(trim((string) ($r['forma_pago'] ?? 'mensual')));
        if (!in_array($formaPago, ['mensual', 'semanal'], true)) {
            $formaPago = 'mensual';
        }
        $idAsesor = null;
        $idAsesorLeg = trim((string) ($r['id_asesor_legado'] ?? ''));
        if ($idAsesorLeg !== '') {
            $idAsesor = migracion_csv_map_get($pdo, 'usuario', $idAsesorLeg);
        }

        $idAlumno = null;
        $idAlLeg = trim((string) ($r['id_alumno_legado'] ?? ''));
        if ($idAlLeg !== '') {
            $idAlumno = migracion_csv_map_get($pdo, 'alumno', $idAlLeg);
        }
        if (($idAlumno === null || $idAlumno <= 0) && $nc !== '') {
            $st = $pdo->prepare(
                'SELECT id_alumno FROM alumnos WHERE numero_control = ? AND id_plantel = ? LIMIT 1'
            );
            $st->execute([$nc, $idPlantel]);
            $ex = $st->fetchColumn();
            if ($ex !== false) {
                $idAlumno = (int) $ex;
            }
        }

        try {
            if ($idAlumno === null || $idAlumno <= 0) {
                if ($nc === '') {
                    $nc = function_exists('alumno_generar_numero_control')
                        ? alumno_generar_numero_control($pdo, $idPlantel)
                        : ('TMP' . time() . $line);
                }
                $pdo->prepare(
                    'INSERT INTO alumnos (
                        id_grupo, id_plantel, numero_control, nombre, apellido, nombres,
                        apellido_paterno, apellido_materno, estado, activo, forma_pago,
                        id_especialidad, email, telefono, fecha_alta, creado_en
                     ) VALUES (?,?,?,?,?,?,?,?,\'activo\',1,?,?,?,?,?,?)'
                )->execute([
                    $idGrupo,
                    $idPlantel,
                    $nc,
                    $nombres,
                    $apPat,
                    $nombres,
                    $apPat,
                    $apMat !== '' ? $apMat : null,
                    $formaPago,
                    $idEsp ?: null,
                    trim((string) ($r['email'] ?? '')) ?: null,
                    trim((string) ($r['telefono'] ?? '')) ?: null,
                    $fecha,
                    $fecha . ' 09:00:00',
                ]);
                $idAlumno = (int) $pdo->lastInsertId();
                $creados++;
            } else {
                $pdo->prepare(
                    'UPDATE alumnos SET id_grupo=?, id_especialidad=?, estado=\'activo\', activo=1,
                     forma_pago=?, telefono=COALESCE(NULLIF(?, \'\'), telefono),
                     email=COALESCE(NULLIF(?, \'\'), email)
                     WHERE id_alumno=?'
                )->execute([
                    $idGrupo,
                    $idEsp ?: null,
                    $formaPago,
                    trim((string) ($r['telefono'] ?? '')),
                    trim((string) ($r['email'] ?? '')),
                    $idAlumno,
                ]);
                $vinculados++;
            }
            if ($idAlLeg !== '') {
                migracion_csv_map_set($pdo, 'alumno', $idAlLeg, $idAlumno, $nc);
            }
            if ($idAsesor) {
                try {
                    $pdo->prepare('UPDATE alumnos SET id_asesor = ? WHERE id_alumno = ?')
                        ->execute([$idAsesor, $idAlumno]);
                } catch (Throwable $e) {
                    // columna puede no existir
                }
            }

            $idFase = (int) ($grupo['id_fase_actual'] ?? 0) ?: null;
            $pdo->prepare(
                'INSERT INTO alumno_grupos (id_alumno, id_grupo, activo, fecha_inicio, id_fase_entrada, creado_en)
                 VALUES (?, ?, 1, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    activo = 1,
                    fecha_inicio = VALUES(fecha_inicio),
                    id_fase_entrada = COALESCE(VALUES(id_fase_entrada), id_fase_entrada)'
            )->execute([$idAlumno, $idGrupo, $fecha, $idFase, $fecha . ' 09:00:00']);

            if ($idGrupoComp && $idGrupoComp > 0) {
                $g2 = $pdo->prepare('SELECT id_fase_actual, id_especialidad FROM grupos WHERE id_grupo = ?');
                $g2->execute([$idGrupoComp]);
                $gc = $g2->fetch(PDO::FETCH_ASSOC) ?: [];
                $pdo->prepare(
                    'INSERT INTO alumno_grupos (id_alumno, id_grupo, activo, fecha_inicio, id_fase_entrada, creado_en)
                     VALUES (?, ?, 1, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE activo = 1, fecha_inicio = VALUES(fecha_inicio)'
                )->execute([
                    $idAlumno,
                    $idGrupoComp,
                    $fecha,
                    (int) ($gc['id_fase_actual'] ?? 0) ?: null,
                    $fecha . ' 09:00:00',
                ]);
                $pdo->prepare('UPDATE alumnos SET inscripcion_kids_modo = \'dual\' WHERE id_alumno = ?')
                    ->execute([$idAlumno]);
            }

            // alumno_especialidades mínima sin congelar montos (usuario los define después)
            if ($idEsp > 0 && function_exists('pago_ensure_schema')) {
                pago_ensure_schema($pdo);
                $chk = $pdo->prepare(
                    'SELECT id_alumno_especialidad FROM alumno_especialidades
                     WHERE id_alumno = ? AND id_especialidad = ? AND activo = 1 LIMIT 1'
                );
                $chk->execute([$idAlumno, $idEsp]);
                if (!$chk->fetchColumn()) {
                    try {
                        $pdo->prepare(
                            'INSERT INTO alumno_especialidades
                             (id_alumno, id_especialidad, forma_pago, fecha_inscripcion, activo)
                             VALUES (?,?,?,?,1)'
                        )->execute([$idAlumno, $idEsp, $formaPago, $fecha]);
                    } catch (Throwable $e) {
                        // esquema puede variar
                    }
                }
            }
            if ($idGrupoComp > 0) {
                $idEspComp = (int) ($gc['id_especialidad'] ?? 0);
                if ($idEspComp > 0) {
                    try {
                        $chk = $pdo->prepare(
                            'SELECT id_alumno_especialidad FROM alumno_especialidades
                             WHERE id_alumno = ? AND id_especialidad = ? AND activo = 1 LIMIT 1'
                        );
                        $chk->execute([$idAlumno, $idEspComp]);
                        if (!$chk->fetchColumn()) {
                            $pdo->prepare(
                                'INSERT INTO alumno_especialidades
                                 (id_alumno, id_especialidad, forma_pago, fecha_inscripcion, activo)
                                 VALUES (?,?,?,?,1)'
                            )->execute([$idAlumno, $idEspComp, $formaPago, $fecha]);
                        }
                    } catch (Throwable $e) {
                        // ignore
                    }
                }
            }
        } catch (Throwable $e) {
            $errores[] = "Línea {$line}: " . $e->getMessage();
        }
    }

    migracion_csv_log($pdo, 'alumnos', 'info', "Alumnos: {$creados} creados, {$vinculados} vinculados");

    return [
        'ok' => ($creados + $vinculados) > 0 || $errores === [],
        'message' => "{$creados} creado(s), {$vinculados} vinculado(s)",
        'creados' => $creados,
        'vinculados' => $vinculados,
        'errores' => array_slice($errores, 0, 40),
    ];
}

/**
 * @return array{ok:bool, message:string, creados?:int, omitidos?:int, errores?:list<string>}
 */
function migracion_csv_importar_pagos(PDO $pdo, array $rows, ?int $forzarPlantel = null): array
{
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    migracion_csv_ensure_schema($pdo);
    pago_ensure_schema($pdo);
    $creados = 0;
    $omitidos = 0;
    $errores = [];
    $idPlantelSesion = $forzarPlantel ?: (function_exists('plantel_id_activo') ? plantel_id_activo() : 0);

    foreach ($rows as $i => $r) {
        $line = $i + 2;
        $idLeg = trim((string) ($r['id_pago_legado'] ?? ''));
        if ($idLeg !== '' && migracion_csv_map_get($pdo, 'pago', $idLeg) !== null) {
            $omitidos++;
            continue;
        }
        $nc = trim((string) ($r['numero_control'] ?? ''));
        $idAlLeg = trim((string) ($r['id_alumno_legado'] ?? ''));
        $idAlumno = null;
        if ($idAlLeg !== '') {
            $idAlumno = migracion_csv_map_get($pdo, 'alumno', $idAlLeg);
        }
        if (($idAlumno === null || $idAlumno <= 0) && $nc !== '') {
            $st = $pdo->prepare(
                'SELECT id_alumno FROM alumnos WHERE numero_control = ?'
                . ($idPlantelSesion > 0 ? ' AND id_plantel = ?' : '')
                . ' LIMIT 1'
            );
            $params = [$nc];
            if ($idPlantelSesion > 0) {
                $params[] = $idPlantelSesion;
            }
            $st->execute($params);
            $ex = $st->fetchColumn();
            if ($ex !== false) {
                $idAlumno = (int) $ex;
            }
        }
        if ($idAlumno === null || $idAlumno <= 0) {
            $errores[] = "Línea {$line}: alumno no encontrado ({$nc})";
            continue;
        }
        $monto = (float) str_replace([',', ' '], ['', ''], (string) ($r['monto'] ?? '0'));
        if ($monto <= 0) {
            continue;
        }
        $tipo = strtolower(trim((string) ($r['tipo'] ?? 'abono')));
        if (!in_array($tipo, ['inscripcion', 'mensualidad', 'semanal', 'abono', 'producto', 'otro'], true)) {
            $tipo = 'abono';
        }
        $fecha = trim((string) ($r['fecha'] ?? ''));
        if ($fecha === '') {
            $fecha = date('Y-m-d H:i:s');
        } elseif (strlen($fecha) === 10) {
            $fecha .= ' 12:00:00';
        }
        $folio = trim((string) ($r['folio'] ?? ''));
        if ($folio === '' && $idLeg !== '') {
            $folio = 'MIG-' . $idLeg;
        }
        $forma = trim((string) ($r['forma_pago'] ?? 'Efectivo')) ?: 'Efectivo';
        $concepto = trim((string) ($r['concepto'] ?? 'Pago migración')) ?: 'Pago migración';

        $stAl = $pdo->prepare('SELECT id_plantel, id_especialidad FROM alumnos WHERE id_alumno = ?');
        $stAl->execute([$idAlumno]);
        $al = $stAl->fetch(PDO::FETCH_ASSOC) ?: [];
        $idPlantel = (int) ($al['id_plantel'] ?? $idPlantelSesion);

        try {
            $pdo->prepare(
                'INSERT INTO alumno_pagos (
                    id_alumno, id_plantel, id_especialidad, tipo, folio, monto, forma_pago,
                    concepto, id_usuario, creado_en
                 ) VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $idAlumno,
                $idPlantel ?: null,
                (int) ($al['id_especialidad'] ?? 0) ?: null,
                $tipo,
                $folio !== '' ? $folio : null,
                $monto,
                $forma,
                $concepto,
                $_SESSION['user_id'] ?? null,
                $fecha,
            ]);
            $idPago = (int) $pdo->lastInsertId();
            if ($idLeg !== '') {
                migracion_csv_map_set($pdo, 'pago', $idLeg, $idPago);
            }
            $creados++;
        } catch (Throwable $e) {
            $errores[] = "Línea {$line}: " . $e->getMessage();
        }
    }

    migracion_csv_log($pdo, 'pagos', 'info', "Pagos: {$creados} creados, {$omitidos} omitidos");

    return [
        'ok' => $creados > 0 || $omitidos > 0 || $errores === [],
        'message' => "{$creados} pago(s) creado(s), {$omitidos} omitido(s)",
        'creados' => $creados,
        'omitidos' => $omitidos,
        'errores' => array_slice($errores, 0, 40),
    ];
}

/**
 * Provisión batch de correos/Moodle para alumnos y staff recién migrados.
 *
 * @return array{ok:bool, message:string, alumnos_ok?:int, alumnos_fail?:int, staff_ok?:int, staff_fail?:int, errores?:list<string>}
 */
function migracion_csv_provisionar_cuentas(PDO $pdo, ?int $idPlantel = null, int $limite = 200): array
{
    if (!migracion_csv_puede()) {
        return ['ok' => false, 'message' => 'No autorizado'];
    }
    $idPlantel = $idPlantel ?: (function_exists('plantel_id_activo') ? plantel_id_activo() : 0);
    $alOk = $alFail = $stOk = $stFail = 0;
    $errores = [];

    // Alumnos mapeados sin moodle_user_id
    $sql = 'SELECT DISTINCT m.id_hay AS id_alumno, a.id_plantel
            FROM hay_migracion_csv_map m
            INNER JOIN alumnos a ON a.id_alumno = m.id_hay
            WHERE m.entidad = \'alumno\'';
    $params = [];
    if ($idPlantel > 0) {
        $sql .= ' AND a.id_plantel = ?';
        $params[] = $idPlantel;
    }
    $sql .= ' ORDER BY m.id_hay ASC LIMIT ' . max(1, min(500, $limite));
    $st = $pdo->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idAl = (int) $row['id_alumno'];
        $idp = (int) $row['id_plantel'];
        if (!function_exists('cuenta_externa_preparar_alumno')) {
            $errores[] = 'cuenta_externa_preparar_alumno no disponible';
            break;
        }
        $res = cuenta_externa_preparar_alumno($pdo, $idAl, $idp);
        if (!empty($res['ok'])) {
            $alOk++;
            // También asegurar fila usuarios alumno si existe helper
            if (function_exists('usuario_crear_cuenta_alumno')) {
                try {
                    usuario_crear_cuenta_alumno($pdo, $idAl, $idp);
                } catch (Throwable $e) {
                    // no bloquear
                }
            }
        } else {
            $alFail++;
            $errores[] = 'Alumno #' . $idAl . ': ' . ($res['message'] ?? 'error');
        }
    }

    // Staff mapeado
    $sqlU = 'SELECT DISTINCT m.id_hay AS id_usuario
             FROM hay_migracion_csv_map m
             INNER JOIN usuarios u ON u.id_usuario = m.id_hay
             WHERE m.entidad = \'usuario\'';
    $paramsU = [];
    if ($idPlantel > 0) {
        $sqlU .= ' AND u.id_plantel = ?';
        $paramsU[] = $idPlantel;
    }
    $sqlU .= ' ORDER BY m.id_hay ASC LIMIT ' . max(1, min(200, $limite));
    $stU = $pdo->prepare($sqlU);
    $stU->execute($paramsU);
    foreach ($stU->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idU = (int) $row['id_usuario'];
        if (function_exists('cuenta_externa_provisionar_staff')) {
            $res = cuenta_externa_provisionar_staff($pdo, $idU, true);
            if (!empty($res['ok'])) {
                $stOk++;
            } else {
                $stFail++;
                $errores[] = 'Usuario #' . $idU . ': ' . ($res['message'] ?? 'error');
            }
        } elseif (function_exists('moodle_user_ensure_staff')) {
            $res = moodle_user_ensure_staff($pdo, $idU);
            if (!empty($res['ok'])) {
                $stOk++;
            } else {
                $stFail++;
                $errores[] = 'Usuario #' . $idU . ' Moodle: ' . ($res['message'] ?? 'error');
            }
        }
    }

    $msg = "Alumnos OK {$alOk} / fallos {$alFail}; Staff OK {$stOk} / fallos {$stFail}";
    migracion_csv_log($pdo, 'provision', 'info', $msg);

    return [
        'ok' => ($alOk + $stOk) > 0 || ($alFail + $stFail) === 0,
        'message' => $msg,
        'alumnos_ok' => $alOk,
        'alumnos_fail' => $alFail,
        'staff_ok' => $stOk,
        'staff_fail' => $stFail,
        'errores' => array_slice($errores, 0, 30),
    ];
}

/** @return array{ok:bool, message:string, ...} */
function migracion_csv_importar_tipo(PDO $pdo, string $tipo, string $tmpPath, ?int $idPlantel = null): array
{
    $parsed = migracion_csv_leer_archivo($tmpPath);
    if (!empty($parsed['error'])) {
        return ['ok' => false, 'message' => $parsed['error']];
    }
    $rows = $parsed['rows'];
    if ($rows === []) {
        return ['ok' => false, 'message' => 'Sin filas de datos'];
    }

    return match ($tipo) {
        'personal' => migracion_csv_importar_personal($pdo, $rows, $idPlantel),
        'aulas' => migracion_csv_importar_aulas($pdo, $rows, $idPlantel),
        'grupos' => migracion_csv_importar_grupos($pdo, $rows, $idPlantel),
        'alumnos' => migracion_csv_importar_alumnos($pdo, $rows, $idPlantel),
        'pagos' => migracion_csv_importar_pagos($pdo, $rows, $idPlantel),
        default => ['ok' => false, 'message' => 'Tipo de CSV no reconocido'],
    };
}
