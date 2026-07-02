<?php

/**
 * Carga historica de grupos: altas retroactivas de grupo, alumnos, pagos y calificaciones.
 */

function supervisor_grupos_historico_puede_ver(): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if (function_exists('rbac_es_supervisor') && rbac_es_supervisor()) {
        return true;
    }
    $rol = function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : ($_SESSION['rol'] ?? '');

    return in_array($rol, ['director', 'admin'], true);
}

function supervisor_grupos_historico_ensure_schema(PDO $pdo): void
{
    alumno_ensure_schema($pdo);
    pago_ensure_schema($pdo);
    academico_ensure_schema($pdo);
    grupo_clave_ensure_schema($pdo);

    plantel_ensure_column($pdo, 'grupos', 'clave_anterior', 'VARCHAR(50) NULL', 'clave');
    plantel_ensure_column($pdo, 'grupos', 'clave_actualizada_en', 'DATETIME NULL', 'clave_anterior');
    plantel_ensure_column($pdo, 'grupos', 'clave_actualizada_por', 'INT UNSIGNED NULL', 'clave_actualizada_en');
    plantel_ensure_column($pdo, 'alumno_pagos', 'fecha_pago', 'DATE NULL', 'creado_en');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS grupo_clave_historial (
            id_historial INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_grupo INT UNSIGNED NOT NULL,
            clave_anterior VARCHAR(50) NOT NULL,
            clave_nueva VARCHAR(50) NOT NULL,
            motivo VARCHAR(255) NULL,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_historial),
            KEY idx_gch_grupo (id_grupo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** @return list<array<string, mixed>> */
function supervisor_grupos_historico_especialidades(PDO $pdo): array
{
    $st = $pdo->query(
        'SELECT id_especialidad, clave, nombre
         FROM especialidades
         WHERE activo = 1
         ORDER BY orden ASC, nombre ASC'
    );

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function supervisor_grupos_historico_fases(PDO $pdo): array
{
    $st = $pdo->query(
        'SELECT id_fase, id_especialidad, clave_fase, nombre_fase, orden
         FROM especialidad_fases
         WHERE activo = 1
         ORDER BY id_especialidad, orden, nombre_fase'
    );

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return list<array<string, mixed>> */
function supervisor_grupos_historico_grupos(PDO $pdo, int $idPlantel): array
{
    supervisor_grupos_historico_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.clave_anterior, g.fecha_inicio, g.id_especialidad, g.id_fase_actual,
                g.horario_texto, e.nombre AS especialidad_nombre, e.clave AS especialidad_clave,
                f.nombre_fase, f.clave_fase,
                COUNT(DISTINCT ag.id_alumno) AS alumnos,
                COUNT(DISTINCT ap.id_pago) AS pagos,
                COUNT(DISTINCT ac.id_calificacion) AS calificaciones
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN especialidad_fases f ON f.id_fase = g.id_fase_actual
         LEFT JOIN alumno_grupos ag ON ag.id_grupo = g.id_grupo
         LEFT JOIN alumno_pagos ap ON ap.id_alumno = ag.id_alumno AND (ap.estado = \'activo\' OR ap.estado IS NULL)
         LEFT JOIN alumno_calificacion_parcial ac ON ac.id_grupo = g.id_grupo
         WHERE g.id_plantel = ?
         GROUP BY g.id_grupo, g.clave, g.clave_anterior, g.fecha_inicio, g.id_especialidad,
                  g.id_fase_actual, g.horario_texto, e.nombre, e.clave, f.nombre_fase, f.clave_fase
         ORDER BY g.fecha_inicio DESC, g.id_grupo DESC
         LIMIT 200'
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string, mixed> */
function supervisor_grupos_historico_contexto(PDO $pdo, int $idPlantel): array
{
    supervisor_grupos_historico_ensure_schema($pdo);

    return [
        'especialidades' => supervisor_grupos_historico_especialidades($pdo),
        'fases' => supervisor_grupos_historico_fases($pdo),
        'grupos' => supervisor_grupos_historico_grupos($pdo, $idPlantel),
    ];
}

/** @return array<string, mixed>|null */
function supervisor_grupos_historico_grupo(PDO $pdo, int $idGrupo, int $idPlantel): ?array
{
    if ($idGrupo <= 0) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT g.*, e.nombre AS especialidad_nombre
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_grupo = ? AND g.id_plantel = ?
         LIMIT 1'
    );
    $st->execute([$idGrupo, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return array<string, mixed>|null */
function supervisor_grupos_historico_especialidad(PDO $pdo, int $idEspecialidad): ?array
{
    if ($idEspecialidad <= 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM especialidades WHERE id_especialidad = ? AND activo = 1 LIMIT 1');
    $st->execute([$idEspecialidad]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function supervisor_grupos_historico_fecha(?string $fecha, string $campo): string
{
    $fecha = trim((string) $fecha);
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
    if (!$dt || $dt->format('Y-m-d') !== $fecha) {
        throw new InvalidArgumentException("Fecha invalida en {$campo}");
    }

    return $fecha;
}

function supervisor_grupos_historico_clave(string $clave): string
{
    $clave = strtoupper(trim($clave));
    $clave = preg_replace('/\s+/', '', $clave) ?? '';
    if ($clave === '' || mb_strlen($clave) > 50) {
        throw new InvalidArgumentException('La clave del grupo es obligatoria y no debe exceder 50 caracteres');
    }

    return $clave;
}

/** @return array{codigo_area:?string,codigo_horario:?string,es_extensivo:int,es_personalizado:int,numero_secuencial:?int} */
function supervisor_grupos_historico_meta_clave(string $clave): array
{
    $clave = strtoupper(trim($clave));
    if (preg_match('/^(E?)(PA|PE|I|K|C)([SDMV])(\d+)$/', $clave, $m)) {
        return [
            'codigo_area' => $m[2],
            'codigo_horario' => $m[3],
            'es_extensivo' => $m[1] === 'E' ? 1 : 0,
            'es_personalizado' => 0,
            'numero_secuencial' => (int) $m[4],
        ];
    }
    if (str_starts_with($clave, 'PER-')) {
        return [
            'codigo_area' => 'PER',
            'codigo_horario' => null,
            'es_extensivo' => 0,
            'es_personalizado' => 1,
            'numero_secuencial' => null,
        ];
    }

    return [
        'codigo_area' => null,
        'codigo_horario' => null,
        'es_extensivo' => 0,
        'es_personalizado' => 0,
        'numero_secuencial' => null,
    ];
}

/** @return array<string, mixed> */
function supervisor_grupos_historico_crear_grupo(PDO $pdo, int $idPlantel, array $data, int $idUsuario): array
{
    supervisor_grupos_historico_ensure_schema($pdo);
    $idEsp = (int) ($data['id_especialidad'] ?? 0);
    $esp = supervisor_grupos_historico_especialidad($pdo, $idEsp);
    if (!$esp) {
        throw new InvalidArgumentException('Seleccione una especialidad valida');
    }
    $fecha = supervisor_grupos_historico_fecha($data['fecha_inicio'] ?? '', 'fecha_inicio');
    $claveNueva = supervisor_grupos_historico_clave((string) ($data['clave_nueva'] ?? ''));
    $claveAnterior = trim((string) ($data['clave_anterior'] ?? ''));
    $claveAnterior = $claveAnterior !== '' ? supervisor_grupos_historico_clave($claveAnterior) : null;
    $idFase = (int) ($data['id_fase_actual'] ?? 0) ?: null;
    $horarioTexto = trim((string) ($data['horario_texto'] ?? '')) ?: null;
    $meta = supervisor_grupos_historico_meta_clave($claveNueva);

    $dup = $pdo->prepare('SELECT id_grupo FROM grupos WHERE id_plantel = ? AND clave = ? LIMIT 1');
    $dup->execute([$idPlantel, $claveNueva]);
    if ((int) ($dup->fetchColumn() ?: 0) > 0) {
        throw new InvalidArgumentException('Ya existe un grupo con esa clave en el plantel');
    }

    $st = $pdo->prepare(
        'INSERT INTO grupos (
            id_plantel, clave, clave_anterior, fecha_inicio, id_especialidad, id_fase_actual,
            horario_texto, codigo_area, codigo_horario, es_extensivo, es_personalizado,
            numero_secuencial, clave_actualizada_en, clave_actualizada_por
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)'
    );
    $st->execute([
        $idPlantel,
        $claveNueva,
        $claveAnterior,
        $fecha,
        $idEsp,
        $idFase,
        $horarioTexto,
        $meta['codigo_area'],
        $meta['codigo_horario'],
        $meta['es_extensivo'],
        $meta['es_personalizado'],
        $meta['numero_secuencial'],
        $idUsuario > 0 ? $idUsuario : null,
    ]);
    $idGrupo = (int) $pdo->lastInsertId();

    if ($idGrupo > 0 && plantel_column_exists($pdo, 'grupos', 'estado_apertura')) {
        $pdo->prepare("UPDATE grupos SET estado_apertura = 'iniciado' WHERE id_grupo = ?")->execute([$idGrupo]);
    }

    if ($claveAnterior) {
        supervisor_grupos_historico_registrar_clave_historial(
            $pdo,
            $idGrupo,
            $claveAnterior,
            $claveNueva,
            'Alta historica inicial',
            $idUsuario
        );
    }

    return [
        'id_grupo' => $idGrupo,
        'clave' => $claveNueva,
        'message' => 'Grupo historico creado',
    ];
}

function supervisor_grupos_historico_registrar_clave_historial(
    PDO $pdo,
    int $idGrupo,
    string $claveAnterior,
    string $claveNueva,
    ?string $motivo,
    int $idUsuario
): void {
    $st = $pdo->prepare(
        'INSERT INTO grupo_clave_historial (id_grupo, clave_anterior, clave_nueva, motivo, id_usuario)
         VALUES (?, ?, ?, ?, ?)'
    );
    $st->execute([
        $idGrupo,
        $claveAnterior,
        $claveNueva,
        $motivo !== '' ? $motivo : null,
        $idUsuario > 0 ? $idUsuario : null,
    ]);
}

/** @return array<string, mixed> */
function supervisor_grupos_historico_actualizar_clave(PDO $pdo, int $idPlantel, array $data, int $idUsuario): array
{
    supervisor_grupos_historico_ensure_schema($pdo);
    $idGrupo = (int) ($data['id_grupo'] ?? 0);
    $grupo = supervisor_grupos_historico_grupo($pdo, $idGrupo, $idPlantel);
    if (!$grupo) {
        throw new InvalidArgumentException('Grupo no encontrado en el plantel actual');
    }
    $claveNueva = supervisor_grupos_historico_clave((string) ($data['clave_nueva'] ?? ''));
    $claveActual = supervisor_grupos_historico_clave((string) ($grupo['clave'] ?? ''));
    if ($claveNueva === $claveActual) {
        throw new InvalidArgumentException('La clave nueva es igual a la clave actual');
    }
    $claveAnteriorInput = trim((string) ($data['clave_anterior'] ?? ''));
    $claveAnterior = $claveAnteriorInput !== ''
        ? supervisor_grupos_historico_clave($claveAnteriorInput)
        : $claveActual;
    $motivo = trim((string) ($data['motivo'] ?? 'Normalizacion de clave'));
    $meta = supervisor_grupos_historico_meta_clave($claveNueva);

    $dup = $pdo->prepare('SELECT id_grupo FROM grupos WHERE id_plantel = ? AND clave = ? AND id_grupo <> ? LIMIT 1');
    $dup->execute([$idPlantel, $claveNueva, $idGrupo]);
    if ((int) ($dup->fetchColumn() ?: 0) > 0) {
        throw new InvalidArgumentException('Ya existe otro grupo con esa clave en el plantel');
    }

    $pdo->prepare(
        'UPDATE grupos
         SET clave = ?,
             clave_anterior = COALESCE(NULLIF(?, \'\'), NULLIF(clave_anterior, \'\'), ?),
             codigo_area = ?,
             codigo_horario = ?,
             es_extensivo = ?,
             es_personalizado = ?,
             numero_secuencial = ?,
             clave_actualizada_en = NOW(),
             clave_actualizada_por = ?
         WHERE id_grupo = ? AND id_plantel = ?'
    )->execute([
        $claveNueva,
        $claveAnterior,
        $claveActual,
        $meta['codigo_area'],
        $meta['codigo_horario'],
        $meta['es_extensivo'],
        $meta['es_personalizado'],
        $meta['numero_secuencial'],
        $idUsuario > 0 ? $idUsuario : null,
        $idGrupo,
        $idPlantel,
    ]);

    supervisor_grupos_historico_registrar_clave_historial($pdo, $idGrupo, $claveActual, $claveNueva, $motivo, $idUsuario);

    return [
        'id_grupo' => $idGrupo,
        'clave' => $claveNueva,
        'clave_anterior' => $claveAnterior,
        'message' => 'Clave del grupo actualizada y clave anterior conservada',
    ];
}

/** @return list<string> */
function supervisor_grupos_historico_lineas(string $texto): array
{
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $lineas = [];
    foreach (explode("\n", $texto) as $linea) {
        $linea = trim($linea);
        if ($linea !== '') {
            $lineas[] = $linea;
        }
    }

    return $lineas;
}

/** @return list<string> */
function supervisor_grupos_historico_partes(string $linea): array
{
    $sep = str_contains($linea, '|') ? '|' : (str_contains($linea, "\t") ? "\t" : ',');
    $partes = array_map(static fn ($v) => trim((string) $v), explode($sep, $linea));

    return array_values($partes);
}

/** @return array{nombres:string,apellido_paterno:string,apellido_materno:string} */
function supervisor_grupos_historico_parse_nombre(string $nombreCompleto): array
{
    $nombreCompleto = trim(preg_replace('/\s+/', ' ', $nombreCompleto) ?? '');
    if ($nombreCompleto === '') {
        throw new InvalidArgumentException('Nombre de alumno vacio');
    }
    $partes = explode(' ', $nombreCompleto);
    if (count($partes) === 1) {
        return ['nombres' => $partes[0], 'apellido_paterno' => '', 'apellido_materno' => ''];
    }
    if (count($partes) === 2) {
        return ['nombres' => $partes[0], 'apellido_paterno' => $partes[1], 'apellido_materno' => ''];
    }
    $apellidoMaterno = array_pop($partes);
    $apellidoPaterno = array_pop($partes);

    return [
        'nombres' => implode(' ', $partes),
        'apellido_paterno' => (string) $apellidoPaterno,
        'apellido_materno' => (string) $apellidoMaterno,
    ];
}

/** @return array<string, float|int|null> */
function supervisor_grupos_historico_tarifas(array $esp): array
{
    return [
        'costo_inscripcion' => (float) ($esp['costo_inscripcion_apoyo'] ?? $esp['costo_inscripcion'] ?? 0),
        'costo_mensualidad' => (float) ($esp['costo_mensualidad_apoyo'] ?? $esp['costo_mensualidad'] ?? 0),
        'costo_pronto_pago' => (float) ($esp['costo_pronto_pago_apoyo'] ?? $esp['costo_pronto_pago'] ?? 0),
        'costo_semanal' => (float) ($esp['costo_semanal_apoyo'] ?? $esp['costo_semanal'] ?? 0),
        'duracion_meses' => (int) ($esp['duracion_meses'] ?? 12) ?: 12,
        'duracion_semanas' => isset($esp['duracion_semanas']) ? (int) $esp['duracion_semanas'] : null,
    ];
}

function supervisor_grupos_historico_alumno_por_control(PDO $pdo, string $numeroControl, int $idPlantel): int
{
    if ($numeroControl === '') {
        return 0;
    }
    $st = $pdo->prepare('SELECT id_alumno FROM alumnos WHERE numero_control = ? AND id_plantel = ? LIMIT 1');
    $st->execute([$numeroControl, $idPlantel]);

    return (int) ($st->fetchColumn() ?: 0);
}

function supervisor_grupos_historico_asegurar_especialidad_alumno(
    PDO $pdo,
    int $idAlumno,
    int $idEsp,
    string $fechaInscripcion,
    string $formaPago,
    array $esp
): int {
    $tarifa = supervisor_grupos_historico_tarifas($esp);
    $st = $pdo->prepare(
        'INSERT INTO alumno_especialidades (
            id_alumno, id_especialidad, forma_pago, fecha_inscripcion,
            costo_inscripcion, costo_mensualidad, costo_pronto_pago, costo_semanal,
            duracion_meses, duracion_semanas, inscripcion_cubierta, activo, creado_en
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?)
         ON DUPLICATE KEY UPDATE
            forma_pago = VALUES(forma_pago),
            fecha_inscripcion = LEAST(fecha_inscripcion, VALUES(fecha_inscripcion)),
            creado_en = LEAST(creado_en, VALUES(creado_en)),
            activo = 1'
    );
    $st->execute([
        $idAlumno,
        $idEsp,
        $formaPago,
        $fechaInscripcion,
        $tarifa['costo_inscripcion'],
        $tarifa['costo_mensualidad'],
        $tarifa['costo_pronto_pago'],
        $tarifa['costo_semanal'],
        $tarifa['duracion_meses'],
        $tarifa['duracion_semanas'],
        $fechaInscripcion . ' 09:00:00',
    ]);
    $sel = $pdo->prepare('SELECT id_alumno_especialidad FROM alumno_especialidades WHERE id_alumno = ? AND id_especialidad = ? LIMIT 1');
    $sel->execute([$idAlumno, $idEsp]);

    return (int) ($sel->fetchColumn() ?: 0);
}

/** @return array<string, mixed> */
function supervisor_grupos_historico_cargar_alumnos(PDO $pdo, int $idPlantel, array $data): array
{
    supervisor_grupos_historico_ensure_schema($pdo);
    $idGrupo = (int) ($data['id_grupo'] ?? 0);
    $grupo = supervisor_grupos_historico_grupo($pdo, $idGrupo, $idPlantel);
    if (!$grupo) {
        throw new InvalidArgumentException('Seleccione un grupo valido');
    }
    $idEsp = (int) ($grupo['id_especialidad'] ?? 0);
    $esp = supervisor_grupos_historico_especialidad($pdo, $idEsp);
    if (!$esp) {
        throw new InvalidArgumentException('El grupo no tiene especialidad valida');
    }
    $fecha = supervisor_grupos_historico_fecha($data['fecha_inscripcion'] ?? '', 'fecha_inscripcion');
    $formaPago = (string) ($data['forma_pago'] ?? 'mensual');
    $formaPago = $formaPago === 'semanal' ? 'semanal' : 'mensual';
    $lineas = supervisor_grupos_historico_lineas((string) ($data['alumnos_text'] ?? ''));
    if ($lineas === []) {
        throw new InvalidArgumentException('Pegue al menos un alumno');
    }

    $creados = 0;
    $vinculados = 0;
    $errores = [];
    $pdo->beginTransaction();
    try {
        foreach ($lineas as $idx => $linea) {
            try {
                $partes = supervisor_grupos_historico_partes($linea);
                $nombreRaw = $partes[0] ?? '';
                $telefono = $partes[1] ?? '';
                $email = $partes[2] ?? '';
                $numeroControl = $partes[3] ?? '';
                if ($numeroControl !== '') {
                    $numeroControl = preg_replace('/\s+/', '', $numeroControl) ?? '';
                }
                $nombre = supervisor_grupos_historico_parse_nombre($nombreRaw);
                $idAlumno = supervisor_grupos_historico_alumno_por_control($pdo, $numeroControl, $idPlantel);
                if ($idAlumno <= 0) {
                    if ($numeroControl === '') {
                        $numeroControl = alumno_generar_numero_control($pdo, $idPlantel);
                    }
                    $ins = $pdo->prepare(
                        'INSERT INTO alumnos (
                            id_grupo, id_plantel, numero_control, nombre, apellido, nombres,
                            apellido_paterno, apellido_materno, estado, activo, forma_pago,
                            id_especialidad, email, telefono, fecha_alta, creado_en
                         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'activo\', 1, ?, ?, ?, ?, ?, ?)'
                    );
                    $ins->execute([
                        $idGrupo,
                        $idPlantel,
                        $numeroControl,
                        $nombre['nombres'],
                        $nombre['apellido_paterno'],
                        $nombre['nombres'],
                        $nombre['apellido_paterno'],
                        $nombre['apellido_materno'],
                        $formaPago,
                        $idEsp,
                        $email !== '' ? $email : null,
                        $telefono !== '' ? $telefono : null,
                        $fecha,
                        $fecha . ' 09:00:00',
                    ]);
                    $idAlumno = (int) $pdo->lastInsertId();
                    $creados++;
                } else {
                    $pdo->prepare(
                        'UPDATE alumnos
                         SET id_grupo = ?, id_especialidad = ?, estado = \'activo\', activo = 1,
                             forma_pago = ?, fecha_alta = LEAST(fecha_alta, ?),
                             creado_en = LEAST(creado_en, ?)
                         WHERE id_alumno = ? AND id_plantel = ?'
                    )->execute([$idGrupo, $idEsp, $formaPago, $fecha, $fecha . ' 09:00:00', $idAlumno, $idPlantel]);
                    $vinculados++;
                }

                $pdo->prepare(
                    'INSERT INTO alumno_grupos (id_alumno, id_grupo, activo, fecha_inicio, id_fase_entrada, creado_en)
                     VALUES (?, ?, 1, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        activo = 1,
                        fecha_inicio = VALUES(fecha_inicio),
                        id_fase_entrada = VALUES(id_fase_entrada),
                        creado_en = LEAST(creado_en, VALUES(creado_en))'
                )->execute([
                    $idAlumno,
                    $idGrupo,
                    $fecha,
                    (int) ($grupo['id_fase_actual'] ?? 0) ?: null,
                    $fecha . ' 09:00:00',
                ]);

                supervisor_grupos_historico_asegurar_especialidad_alumno($pdo, $idAlumno, $idEsp, $fecha, $formaPago, $esp);
            } catch (Throwable $e) {
                $errores[] = 'Linea ' . ($idx + 1) . ': ' . $e->getMessage();
            }
        }
        if ($creados + $vinculados === 0) {
            throw new RuntimeException('No se pudo cargar ningun alumno');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'creados' => $creados,
        'vinculados' => $vinculados,
        'errores' => $errores,
        'message' => 'Alumnos cargados: ' . ($creados + $vinculados),
    ];
}

/** @return array<int, array<string, mixed>> */
function supervisor_grupos_historico_alumnos_grupo(PDO $pdo, int $idGrupo): array
{
    $st = $pdo->prepare(
        "SELECT a.id_alumno, a.numero_control,
                LOWER(TRIM(CONCAT(COALESCE(a.nombres, a.nombre, ''), ' ', COALESCE(a.apellido_paterno, a.apellido, ''), ' ', COALESCE(a.apellido_materno, '')))) AS nombre_busqueda
         FROM alumno_grupos ag
         INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
         WHERE ag.id_grupo = ?"
    );
    $st->execute([$idGrupo]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int) $row['id_alumno']] = $row;
    }

    return $out;
}

function supervisor_grupos_historico_match_alumno(array $alumnos, string $ref): int
{
    $ref = trim($ref);
    if ($ref === '') {
        return 0;
    }
    foreach ($alumnos as $id => $al) {
        if ($ref === (string) ($al['numero_control'] ?? '')) {
            return (int) $id;
        }
    }
    $refNorm = mb_strtolower(trim(preg_replace('/\s+/', ' ', $ref) ?? ''));
    foreach ($alumnos as $id => $al) {
        $nombre = (string) ($al['nombre_busqueda'] ?? '');
        if ($nombre !== '' && (str_contains($nombre, $refNorm) || str_contains($refNorm, $nombre))) {
            return (int) $id;
        }
    }

    return 0;
}

function supervisor_grupos_historico_pago_tipo(string $tipo): string
{
    $tipo = strtolower(trim($tipo));
    if (in_array($tipo, ['inscripcion', 'mensualidad', 'semanal', 'abono', 'producto', 'otro'], true)) {
        return $tipo;
    }

    return 'mensualidad';
}

/** @return array<string, mixed> */
function supervisor_grupos_historico_cargar_pagos(PDO $pdo, int $idPlantel, array $data, int $idUsuario): array
{
    supervisor_grupos_historico_ensure_schema($pdo);
    $idGrupo = (int) ($data['id_grupo'] ?? 0);
    $grupo = supervisor_grupos_historico_grupo($pdo, $idGrupo, $idPlantel);
    if (!$grupo) {
        throw new InvalidArgumentException('Seleccione un grupo valido');
    }
    $idEsp = (int) ($grupo['id_especialidad'] ?? 0);
    $alumnos = supervisor_grupos_historico_alumnos_grupo($pdo, $idGrupo);
    if ($alumnos === []) {
        throw new InvalidArgumentException('El grupo no tiene alumnos cargados');
    }
    $lineas = supervisor_grupos_historico_lineas((string) ($data['pagos_text'] ?? ''));
    if ($lineas === []) {
        throw new InvalidArgumentException('Pegue al menos un pago');
    }

    $guardados = 0;
    $errores = [];
    $pdo->beginTransaction();
    try {
        foreach ($lineas as $idx => $linea) {
            try {
                $partes = supervisor_grupos_historico_partes($linea);
                $idAlumno = supervisor_grupos_historico_match_alumno($alumnos, $partes[0] ?? '');
                if ($idAlumno <= 0) {
                    throw new InvalidArgumentException('Alumno no encontrado en el grupo');
                }
                $monto = (float) str_replace([',', '$'], ['', ''], (string) ($partes[1] ?? '0'));
                if ($monto <= 0) {
                    throw new InvalidArgumentException('Monto invalido');
                }
                $fechaPago = supervisor_grupos_historico_fecha($partes[2] ?? '', 'fecha_pago');
                $tipo = supervisor_grupos_historico_pago_tipo($partes[3] ?? 'mensualidad');
                $formaPago = trim((string) ($partes[4] ?? 'efectivo')) ?: 'efectivo';
                $concepto = trim((string) ($partes[5] ?? 'Carga historica de colegiatura'));
                $periodo = trim((string) ($partes[6] ?? '')) ?: null;

                $idAe = 0;
                $stAe = $pdo->prepare(
                    'SELECT id_alumno_especialidad FROM alumno_especialidades
                     WHERE id_alumno = ? AND id_especialidad = ? LIMIT 1'
                );
                $stAe->execute([$idAlumno, $idEsp]);
                $idAe = (int) ($stAe->fetchColumn() ?: 0);

                $pdo->prepare(
                    'INSERT INTO alumno_pagos (
                        id_alumno, id_plantel, id_especialidad, id_alumno_especialidad,
                        tipo, monto, forma_pago, concepto, cubrio, periodo_ref,
                        id_usuario, creado_en, fecha_pago
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $idAlumno,
                    $idPlantel,
                    $idEsp ?: null,
                    $idAe > 0 ? $idAe : null,
                    $tipo,
                    $monto,
                    $formaPago,
                    $concepto,
                    $concepto,
                    $periodo,
                    $idUsuario > 0 ? $idUsuario : null,
                    $fechaPago . ' 12:00:00',
                    $fechaPago,
                ]);
                $guardados++;
            } catch (Throwable $e) {
                $errores[] = 'Linea ' . ($idx + 1) . ': ' . $e->getMessage();
            }
        }
        if ($guardados === 0) {
            throw new RuntimeException('No se pudo guardar ningun pago');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'guardados' => $guardados,
        'errores' => $errores,
        'message' => 'Pagos historicos guardados: ' . $guardados,
    ];
}

/** @return array<string, mixed> */
function supervisor_grupos_historico_cargar_calificaciones(PDO $pdo, int $idPlantel, array $data, int $idUsuario): array
{
    supervisor_grupos_historico_ensure_schema($pdo);
    $idGrupo = (int) ($data['id_grupo'] ?? 0);
    $grupo = supervisor_grupos_historico_grupo($pdo, $idGrupo, $idPlantel);
    if (!$grupo) {
        throw new InvalidArgumentException('Seleccione un grupo valido');
    }
    $idFase = (int) ($data['id_fase'] ?? 0);
    if ($idFase <= 0) {
        throw new InvalidArgumentException('Seleccione la materia/fase');
    }
    $alumnos = supervisor_grupos_historico_alumnos_grupo($pdo, $idGrupo);
    if ($alumnos === []) {
        throw new InvalidArgumentException('El grupo no tiene alumnos cargados');
    }
    $lineas = supervisor_grupos_historico_lineas((string) ($data['calificaciones_text'] ?? ''));
    if ($lineas === []) {
        throw new InvalidArgumentException('Pegue al menos una calificacion');
    }

    $guardadas = 0;
    $errores = [];
    $pdo->beginTransaction();
    try {
        foreach ($lineas as $idx => $linea) {
            try {
                $partes = supervisor_grupos_historico_partes($linea);
                $idAlumno = supervisor_grupos_historico_match_alumno($alumnos, $partes[0] ?? '');
                if ($idAlumno <= 0) {
                    throw new InvalidArgumentException('Alumno no encontrado en el grupo');
                }
                $promedio = (float) str_replace(',', '.', (string) ($partes[1] ?? '0'));
                if ($promedio < 0 || $promedio > 10) {
                    throw new InvalidArgumentException('La calificacion debe estar entre 0 y 10');
                }
                $obs = trim((string) ($partes[2] ?? 'Carga historica'));
                $aprobado = $promedio >= ACADEMICO_NOTA_MINIMA ? 1 : 0;
                $notas = json_encode(['historico' => $promedio], JSON_UNESCAPED_UNICODE);
                $pdo->prepare(
                    'INSERT INTO alumno_calificacion_parcial
                     (id_alumno, id_fase, id_grupo, notas_json, promedio, aprobado, capturado_por, editado_por, observaciones)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        id_grupo = VALUES(id_grupo),
                        notas_json = VALUES(notas_json),
                        promedio = VALUES(promedio),
                        aprobado = VALUES(aprobado),
                        editado_por = VALUES(editado_por),
                        observaciones = VALUES(observaciones),
                        actualizado_en = NOW()'
                )->execute([
                    $idAlumno,
                    $idFase,
                    $idGrupo,
                    $notas,
                    $promedio,
                    $aprobado,
                    $idUsuario > 0 ? $idUsuario : null,
                    $idUsuario > 0 ? $idUsuario : null,
                    $obs !== '' ? $obs : null,
                ]);
                $pdo->prepare(
                    'INSERT INTO alumno_calificaciones_fase (id_alumno, id_fase, calificacion, observaciones)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion), observaciones = VALUES(observaciones)'
                )->execute([$idAlumno, $idFase, $promedio, $obs !== '' ? $obs : null]);
                $guardadas++;
            } catch (Throwable $e) {
                $errores[] = 'Linea ' . ($idx + 1) . ': ' . $e->getMessage();
            }
        }
        if ($guardadas === 0) {
            throw new RuntimeException('No se pudo guardar ninguna calificacion');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'guardadas' => $guardadas,
        'errores' => $errores,
        'message' => 'Calificaciones historicas guardadas: ' . $guardadas,
    ];
}
