<?php

/**
 * Reclutamiento docente: prospectos, clase muestra, DISC y decisión final.
 */

function docente_prospecto_puede_gestionar(): bool
{
    if (function_exists('profesor_eval_puede_gestionar') && profesor_eval_puede_gestionar()) {
        return true;
    }
    return in_array(rbac_rol_efectivo(), ['gerente', 'supervisor', 'admin', 'coordinador', 'director'], true);
}

function docente_prospecto_es_candidato_usuario(PDO $pdo, int $idUsuario): ?array
{
    docente_prospecto_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT * FROM docente_prospecto WHERE id_usuario_candidato = ? AND estado NOT IN (\'contratado\') LIMIT 1'
    );
    $st->execute([$idUsuario]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function docente_prospecto_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS docente_prospecto (
            id_prospecto INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_plantel INT UNSIGNED NOT NULL,
            estado ENUM(
                'nuevo','clase_muestra_agendada','evaluado','apto_disc',
                'disc_completo','contratado','no_contratado','bolsa'
            ) NOT NULL DEFAULT 'nuevo',
            nombres VARCHAR(120) NOT NULL,
            apellido_paterno VARCHAR(80) NOT NULL,
            apellido_materno VARCHAR(80) NULL,
            telefono VARCHAR(30) NULL,
            email VARCHAR(160) NULL,
            curp VARCHAR(20) NULL,
            especialidad VARCHAR(120) NULL,
            disponibilidad VARCHAR(120) NULL,
            fecha_clase_muestra DATETIME NULL,
            puntaje_showclass DECIMAL(5,2) NULL,
            showclass_aprobado TINYINT(1) NOT NULL DEFAULT 0,
            disc_resultado_id INT UNSIGNED NULL,
            decision_final ENUM('pendiente','contratar','no_contratar','bolsa') NOT NULL DEFAULT 'pendiente',
            motivo_no_contratacion TEXT NULL,
            categoria_no_contratacion VARCHAR(60) NULL,
            recontactar_en DATE NULL,
            segunda_oportunidad TINYINT(1) NOT NULL DEFAULT 0,
            id_usuario_registro INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_prospecto),
            KEY idx_dp_plantel_estado (id_plantel, estado),
            KEY idx_dp_contacto (email, telefono, curp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS docente_prospecto_evento (
            id_evento INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_prospecto INT UNSIGNED NOT NULL,
            tipo ENUM('nota','agenda','disc','decision','seguimiento') NOT NULL,
            detalle TEXT NOT NULL,
            fecha_evento DATETIME NULL,
            id_usuario INT UNSIGNED NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_evento),
            KEY idx_dpe_prospecto (id_prospecto, tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS docente_showclass_eval (
            id_eval INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_prospecto INT UNSIGNED NOT NULL,
            puntaje_total DECIMAL(5,2) NOT NULL DEFAULT 0,
            aprobada TINYINT(1) NOT NULL DEFAULT 0,
            rubrica_json JSON NOT NULL,
            comentarios TEXT NULL,
            evaluado_por INT UNSIGNED NULL,
            evaluado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_eval),
            KEY idx_dse_prospecto (id_prospecto)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/** @return list<array{codigo:string,nombre:string,maximo:int}> */
function docente_showclass_rubrica_base(): array
{
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO && function_exists('profesor_360_criterios_rubrica')) {
        profesor_360_ensure_schema($pdo);
        $st = $pdo->prepare(
            "SELECT id_rubrica FROM docente_rubrica_area WHERE tipo = 'showclass' AND clave = 'INGLES' AND activo = 1 LIMIT 1"
        );
        $st->execute();
        $idRub = (int) $st->fetchColumn();
        if ($idRub > 0) {
            $crit = profesor_360_criterios_rubrica($pdo, $idRub);
            if ($crit !== []) {
                return array_map(static fn ($c) => [
                    'codigo' => (string) $c['codigo'],
                    'nombre' => (string) $c['nombre'],
                    'maximo' => (int) $c['maximo'],
                ], $crit);
            }
        }
    }

    return [
        ['codigo' => 'lesson_plan', 'nombre' => 'Plan de clase', 'maximo' => 10],
        ['codigo' => 'english_level', 'nombre' => 'Nivel de inglés', 'maximo' => 10],
        ['codigo' => 'classroom_management', 'nombre' => 'Manejo de grupo', 'maximo' => 10],
        ['codigo' => 'communication', 'nombre' => 'Comunicación e instrucciones', 'maximo' => 10],
        ['codigo' => 'methodology', 'nombre' => 'Metodología didáctica', 'maximo' => 10],
        ['codigo' => 'engagement', 'nombre' => 'Participación del alumno', 'maximo' => 10],
        ['codigo' => 'closure_feedback', 'nombre' => 'Cierre y retroalimentación', 'maximo' => 10],
    ];
}

function docente_prospecto_showclass_rubrica(PDO $pdo, int $idProspecto): array
{
    $p = docente_prospecto_obtener($pdo, $idProspecto);
    $idRub = (int) ($p['id_rubrica'] ?? 0);
    if ($idRub <= 0) {
        $clave = catalog_normalizar_clave((string) ($p['especialidad'] ?? 'INGLES'), 40) ?: 'INGLES';
        $st = $pdo->prepare(
            "SELECT id_rubrica FROM docente_rubrica_area WHERE tipo = 'showclass' AND clave = ? AND activo = 1 LIMIT 1"
        );
        $st->execute([$clave]);
        $idRub = (int) $st->fetchColumn();
    }
    if ($idRub <= 0) {
        return docente_showclass_rubrica_base();
    }
    $crit = profesor_360_criterios_rubrica($pdo, $idRub);

    return array_map(static fn ($c) => [
        'codigo' => (string) $c['codigo'],
        'nombre' => (string) $c['nombre'],
        'maximo' => (int) $c['maximo'],
    ], $crit);
}

function docente_prospecto_nombre(array $p): string
{
    return trim(($p['nombres'] ?? '') . ' ' . ($p['apellido_paterno'] ?? '') . ' ' . ($p['apellido_materno'] ?? ''));
}

function docente_prospecto_registrar_evento(
    PDO $pdo,
    int $idProspecto,
    string $tipo,
    string $detalle,
    ?string $fechaEvento,
    ?int $idUsuario
): void {
    $pdo->prepare(
        'INSERT INTO docente_prospecto_evento (id_prospecto, tipo, detalle, fecha_evento, id_usuario)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$idProspecto, $tipo, $detalle, $fechaEvento, $idUsuario]);
}

/** @return list<array<string,mixed>> */
function docente_prospecto_listar(PDO $pdo, ?string $filtro = null): array
{
    docente_prospecto_ensure_schema($pdo);
    $idPlantel = plantel_scope_id($pdo);
    $extra = '';
    $params = [$idPlantel];
    if ($filtro && in_array($filtro, ['nuevo', 'clase_muestra_agendada', 'evaluado', 'apto_disc', 'disc_completo', 'contratado', 'no_contratado', 'bolsa'], true)) {
        $extra = ' AND p.estado = ?';
        $params[] = $filtro;
    }
    $st = $pdo->prepare(
        "SELECT p.*,
                u.nombre AS registro_nombre, u.apellido AS registro_apellido
         FROM docente_prospecto p
         LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
         WHERE p.id_plantel = ? {$extra}
         ORDER BY p.creado_en DESC"
    );
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string,mixed>|null */
function docente_prospecto_obtener(PDO $pdo, int $idProspecto): ?array
{
    $st = $pdo->prepare('SELECT * FROM docente_prospecto WHERE id_prospecto = ? AND id_plantel = ?');
    $st->execute([$idProspecto, plantel_scope_id($pdo)]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function docente_prospecto_guardar(PDO $pdo, array $data, int $idUsuario, int $idProspecto = 0): array
{
    docente_prospecto_ensure_schema($pdo);
    $idPlantel = plantel_scope_id($pdo);
    $nombres = trim((string) ($data['nombres'] ?? ''));
    $apPat = trim((string) ($data['apellido_paterno'] ?? ''));
    if ($nombres === '' || $apPat === '') {
        return ['ok' => false, 'message' => 'Nombre y apellido paterno son obligatorios'];
    }

    $fechaClase = trim((string) ($data['fecha_clase_muestra'] ?? ''));
    $fechaClase = $fechaClase !== '' ? $fechaClase : null;
    $estado = trim((string) ($data['estado'] ?? 'nuevo'));
    $validos = ['nuevo', 'clase_muestra_agendada', 'evaluado', 'apto_disc', 'disc_completo', 'contratado', 'no_contratado', 'bolsa'];
    if (!in_array($estado, $validos, true)) {
        $estado = 'nuevo';
    }

    $params = [
        $estado,
        $nombres,
        $apPat,
        trim((string) ($data['apellido_materno'] ?? '')) ?: null,
        trim((string) ($data['telefono'] ?? '')) ?: null,
        trim((string) ($data['email'] ?? '')) ?: null,
        strtoupper(trim((string) ($data['curp'] ?? ''))) ?: null,
        trim((string) ($data['especialidad'] ?? '')) ?: null,
        trim((string) ($data['disponibilidad'] ?? '')) ?: null,
        $fechaClase,
    ];

    if ($idProspecto > 0) {
        $sql = 'UPDATE docente_prospecto
                SET estado=?, nombres=?, apellido_paterno=?, apellido_materno=?, telefono=?, email=?, curp=?,
                    especialidad=?, disponibilidad=?, fecha_clase_muestra=?
                WHERE id_prospecto=? AND id_plantel=?';
        $pdo->prepare($sql)->execute(array_merge($params, [$idProspecto, $idPlantel]));
        docente_prospecto_registrar_evento($pdo, $idProspecto, 'nota', 'Prospecto actualizado', null, $idUsuario);
        $idFinal = $idProspecto;
    } else {
        $sql = 'INSERT INTO docente_prospecto
            (id_plantel, estado, nombres, apellido_paterno, apellido_materno, telefono, email, curp,
             especialidad, disponibilidad, fecha_clase_muestra, id_usuario_registro)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $pdo->prepare($sql)->execute(array_merge([$idPlantel], $params, [$idUsuario]));
        $idFinal = (int) $pdo->lastInsertId();
        docente_prospecto_registrar_evento($pdo, $idFinal, 'nota', 'Prospecto creado', null, $idUsuario);
        if ($fechaClase) {
            docente_prospecto_registrar_evento($pdo, $idFinal, 'agenda', 'Clase muestra agendada', $fechaClase, $idUsuario);
        }
    }

    $areasPost = [];
    if (!empty($data['areas']) && is_array($data['areas'])) {
        $areasPost = array_map('intval', $data['areas']);
    } elseif (!empty($data['areas_json'])) {
        $areasPost = json_decode((string) $data['areas_json'], true) ?: [];
    }
    if (function_exists('docente_prospecto_sincronizar_areas_desde_texto')) {
        docente_prospecto_sincronizar_areas_desde_texto(
            $pdo,
            $idFinal,
            trim((string) ($data['especialidad'] ?? '')),
            $areasPost ?: null
        );
    }

    return [
        'ok' => true,
        'message' => $idProspecto > 0 ? 'Prospecto actualizado' : 'Prospecto guardado',
        'id_prospecto' => $idFinal,
    ];
}

function docente_prospecto_guardar_showclass(
    PDO $pdo,
    int $idProspecto,
    array $puntajes,
    string $comentarios,
    int $idUsuario
): array {
    $p = docente_prospecto_obtener($pdo, $idProspecto);
    if (!$p) {
        return ['ok' => false, 'message' => 'Prospecto no encontrado'];
    }
    $rubrica = docente_prospecto_showclass_rubrica($pdo, $idProspecto);
    $items = [];
    $total = 0.0;
    $max = 0;
    foreach ($rubrica as $r) {
        $cod = $r['codigo'];
        $m = (int) $r['maximo'];
        $v = (float) ($puntajes[$cod] ?? 0);
        if ($v < 0) {
            $v = 0;
        }
        if ($v > $m) {
            $v = (float) $m;
        }
        $items[] = [
            'codigo' => $cod,
            'nombre' => $r['nombre'],
            'maximo' => $m,
            'puntaje' => $v,
        ];
        $total += $v;
        $max += $m;
    }
    $pct = $max > 0 ? round(($total / $max) * 100, 2) : 0.0;
    $aprobada = $pct >= 75.0 ? 1 : 0;
    $json = json_encode($items, JSON_UNESCAPED_UNICODE);

    $pdo->prepare(
        'INSERT INTO docente_showclass_eval (id_prospecto, puntaje_total, aprobada, rubrica_json, comentarios, evaluado_por)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$idProspecto, $pct, $aprobada, $json, trim($comentarios) ?: null, $idUsuario]);

    $nuevoEstado = $aprobada ? 'apto_disc' : 'evaluado';
    $pdo->prepare(
        'UPDATE docente_prospecto
         SET puntaje_showclass = ?, showclass_aprobado = ?, estado = ?
         WHERE id_prospecto = ? AND id_plantel = ?'
    )->execute([$pct, $aprobada, $nuevoEstado, $idProspecto, plantel_scope_id($pdo)]);

    docente_prospecto_registrar_evento(
        $pdo,
        $idProspecto,
        'nota',
        'Evaluación clase muestra: ' . $pct . '%',
        null,
        $idUsuario
    );

    return [
        'ok' => true,
        'message' => $aprobada ? 'Clase muestra aprobada; habilite DISC.' : 'Clase muestra registrada',
        'puntaje_total' => $pct,
        'aprobada' => (bool) $aprobada,
    ];
}

function docente_prospecto_vincular_disc(PDO $pdo, int $idProspecto, int $idDiscRes): void
{
    $pdo->prepare(
        'UPDATE docente_prospecto
         SET disc_resultado_id = ?, estado = IF(showclass_aprobado = 1, \'disc_completo\', estado)
         WHERE id_prospecto = ? AND id_plantel = ?'
    )->execute([$idDiscRes, $idProspecto, plantel_scope_id($pdo)]);
    docente_prospecto_registrar_evento($pdo, $idProspecto, 'disc', 'DISC completado', null, (int) ($_SESSION['user_id'] ?? 0));
}

function docente_prospecto_guardar_decision(
    PDO $pdo,
    int $idProspecto,
    string $decision,
    string $categoria,
    string $motivo,
    ?string $recontactarEn,
    bool $segundaOportunidad,
    int $idUsuario
): array {
    $p = docente_prospecto_obtener($pdo, $idProspecto);
    if (!$p) {
        return ['ok' => false, 'message' => 'Prospecto no encontrado'];
    }
    $validas = ['contratar', 'no_contratar', 'bolsa'];
    if (!in_array($decision, $validas, true)) {
        return ['ok' => false, 'message' => 'Decisión inválida'];
    }
    if ($decision !== 'contratar' && trim($motivo) === '') {
        return ['ok' => false, 'message' => 'Indique motivo de no contratación'];
    }
    $estado = $decision === 'contratar' ? 'contratado' : ($decision === 'bolsa' ? 'bolsa' : 'no_contratado');
    $pdo->prepare(
        'UPDATE docente_prospecto
         SET decision_final = ?, estado = ?, categoria_no_contratacion = ?, motivo_no_contratacion = ?,
             recontactar_en = ?, segunda_oportunidad = ?
         WHERE id_prospecto = ? AND id_plantel = ?'
    )->execute([
        $decision,
        $estado,
        trim($categoria) ?: null,
        trim($motivo) ?: null,
        $recontactarEn ?: null,
        $segundaOportunidad ? 1 : 0,
        $idProspecto,
        plantel_scope_id($pdo),
    ]);

    docente_prospecto_registrar_evento($pdo, $idProspecto, 'decision', 'Decisión: ' . $decision, null, $idUsuario);
    return ['ok' => true, 'message' => 'Decisión guardada'];
}

/** @return list<array<string,mixed>> */
function docente_prospecto_listar_bolsa(PDO $pdo, ?string $filtro = null): array
{
    $idPlantel = plantel_scope_id($pdo);
    $where = "WHERE id_plantel = ? AND decision_final IN ('no_contratar','bolsa')";
    $params = [$idPlantel];
    if ($filtro === 'apto_no_contratado') {
        $where .= " AND showclass_aprobado = 1 AND decision_final IN ('no_contratar','bolsa')";
    } elseif ($filtro === 'disponibilidad') {
        $where .= " AND categoria_no_contratacion = 'disponibilidad'";
    } elseif ($filtro === 'segunda_oportunidad') {
        $where .= ' AND segunda_oportunidad = 1';
    }
    $st = $pdo->prepare("SELECT * FROM docente_prospecto {$where} ORDER BY COALESCE(recontactar_en, DATE('2999-12-31')) ASC, actualizado_en DESC");
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function docente_prospecto_coincidencias(PDO $pdo, string $email, string $telefono, string $curp, int $exceptId = 0): array
{
    $cond = [];
    $params = [];
    if ($email !== '') {
        $cond[] = 'email = ?';
        $params[] = $email;
    }
    if ($telefono !== '') {
        $cond[] = 'telefono = ?';
        $params[] = $telefono;
    }
    if ($curp !== '') {
        $cond[] = 'curp = ?';
        $params[] = strtoupper($curp);
    }
    if ($cond === []) {
        return [];
    }
    $sql = 'SELECT id_prospecto, id_plantel, nombres, apellido_paterno, estado, decision_final, motivo_no_contratacion
            FROM docente_prospecto
            WHERE (' . implode(' OR ', $cond) . ')';
    if ($exceptId > 0) {
        $sql .= ' AND id_prospecto <> ?';
        $params[] = $exceptId;
    }
    $sql .= ' ORDER BY actualizado_en DESC LIMIT 10';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function docente_prospecto_crear_acceso_candidato(PDO $pdo, int $idProspecto, int $idUsuarioReg): array
{
    usuario_ensure_schema($pdo);
    profesor_360_ensure_schema($pdo);
    $p = docente_prospecto_obtener($pdo, $idProspecto);
    if (!$p) {
        return ['ok' => false, 'message' => 'Prospecto no encontrado'];
    }
    if (!empty($p['id_usuario_candidato'])) {
        return ['ok' => true, 'message' => 'El candidato ya tiene acceso', 'id_usuario' => (int) $p['id_usuario_candidato']];
    }
    $email = trim((string) ($p['email'] ?? ''));
    if ($email === '') {
        return ['ok' => false, 'message' => 'Indique correo del candidato'];
    }
    $st = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE email = ? OR username = ? LIMIT 1');
    $st->execute([$email, $email]);
    if ($st->fetchColumn()) {
        return ['ok' => false, 'message' => 'Ya existe un usuario con ese correo'];
    }

    $nombre = trim((string) ($p['nombres'] ?? ''));
    $apellido = trim(($p['apellido_paterno'] ?? '') . ' ' . ($p['apellido_materno'] ?? ''));
    $pass = function_exists('cuenta_password_inicial') ? cuenta_password_inicial() : 'Cncm*1234';
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $idPlantel = (int) $p['id_plantel'];

    $pdo->prepare(
        'INSERT INTO usuarios (nombre, apellido, username, email, password, rol, id_plantel, debe_cambiar_password, fecha_creacion)
         VALUES (?,?,?,?,?,\'profesor\',?,1,NOW())'
    )->execute([$nombre, $apellido, $email, $email, $hash, $idPlantel]);
    $idUser = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE docente_prospecto SET id_usuario_candidato = ? WHERE id_prospecto = ? AND id_plantel = ?')
        ->execute([$idUser, $idProspecto, plantel_scope_id($pdo)]);

    docente_prospecto_registrar_evento($pdo, $idProspecto, 'nota', 'Acceso candidato creado (usuario #' . $idUser . ')', null, $idUsuarioReg);

    $moodleMsg = '';
    if (function_exists('moodle_user_ensure_staff')) {
        $moodle = moodle_user_ensure_staff($pdo, $idUser);
        if (!empty($moodle['ok'])) {
            $moodleMsg = ' · Moodle: ' . (string) ($moodle['message'] ?? 'OK');
            docente_prospecto_registrar_evento(
                $pdo,
                $idProspecto,
                'nota',
                'Alta Moodle candidato — ' . (string) ($moodle['message'] ?? 'OK'),
                null,
                $idUsuarioReg
            );
        } else {
            $moodleMsg = ' · Moodle pendiente: ' . (string) ($moodle['message'] ?? 'Error');
        }
    }

    return [
        'ok' => true,
        'message' => 'Acceso creado. Contraseña inicial: ' . $pass . $moodleMsg,
        'id_usuario' => $idUser,
        'password_inicial' => $pass,
    ];
}

function docente_prospecto_contratar(PDO $pdo, int $idProspecto, string $emailGoogle, int $idUsuarioReg): array
{
    profesor_360_ensure_schema($pdo);
    $p = docente_prospecto_obtener($pdo, $idProspecto);
    if (!$p) {
        return ['ok' => false, 'message' => 'Prospecto no encontrado'];
    }
    if (($p['decision_final'] ?? '') !== 'contratar' && ($p['estado'] ?? '') !== 'contratado') {
        return ['ok' => false, 'message' => 'Primero registre la decisión de contratación'];
    }

    $idUser = (int) ($p['id_usuario_profesor'] ?? 0);
    if ($idUser <= 0) {
        $idUser = (int) ($p['id_usuario_candidato'] ?? 0);
    }

    $googleMsg = '';
    $emailGoogle = trim($emailGoogle);
    if ($emailGoogle !== '' && function_exists('google_crear_usuario')) {
        $parts = preg_split('/\s+/', docente_prospecto_nombre($p)) ?: [];
        $resG = google_crear_usuario([
            'nombre' => $parts[0] ?? $p['nombres'],
            'apellido' => trim(($p['apellido_paterno'] ?? '') . ' ' . ($p['apellido_materno'] ?? '')),
            'email' => $emailGoogle,
        ]);
        if (!empty($resG['ok'])) {
            $googleMsg = ' Cuenta Google creada/verificada.';
        } elseif (!empty($resG['message'])) {
            $googleMsg = ' Google: ' . $resG['message'];
        }
    }

    if ($idUser <= 0) {
        $crear = docente_prospecto_crear_acceso_candidato($pdo, $idProspecto, $idUsuarioReg);
        if (!$crear['ok']) {
            return $crear;
        }
        $idUser = (int) $crear['id_usuario'];
    }

    $pdo->prepare(
        'UPDATE docente_prospecto SET id_usuario_profesor = ?, email_google = ?, estado = \'contratado\', decision_final = \'contratar\'
         WHERE id_prospecto = ? AND id_plantel = ?'
    )->execute([$idUser, $emailGoogle ?: null, $idProspecto, plantel_scope_id($pdo)]);

    if ($emailGoogle !== '') {
        $pdo->prepare('UPDATE usuarios SET email = ? WHERE id_usuario = ?')->execute([$emailGoogle, $idUser]);
    }

    if (function_exists('docente_prospecto_areas') && function_exists('hay_eval_asignar_areas_usuario')) {
        $areasPros = docente_prospecto_areas($pdo, $idProspecto);
        $idAreas = array_map(static fn ($a) => (int) $a['id_area'], $areasPros);
        $idPrincipal = 0;
        foreach ($areasPros as $a) {
            if (!empty($a['es_principal'])) {
                $idPrincipal = (int) $a['id_area'];
                break;
            }
        }
        if ($idAreas) {
            hay_eval_asignar_areas_usuario($pdo, $idUser, $idAreas, $idPrincipal ?: null);
        }
    } elseif (function_exists('hay_eval_area_por_clave')) {
        $claveArea = catalog_normalizar_clave((string) ($p['especialidad'] ?? 'PROF_INGLES'), 40) ?: 'PROF_INGLES';
        $area = hay_eval_area_por_clave($pdo, $claveArea) ?: hay_eval_area_por_clave($pdo, 'PROF_INGLES');
        if ($area) {
            $pdo->prepare('UPDATE usuarios SET id_hay_area = ? WHERE id_usuario = ?')
                ->execute([(int) $area['id_area'], $idUser]);
            $pdo->prepare('UPDATE docente_prospecto SET id_hay_area = ? WHERE id_prospecto = ?')
                ->execute([(int) $area['id_area'], $idProspecto]);
        }
    }

    docente_prospecto_registrar_evento($pdo, $idProspecto, 'decision', 'Contratación formalizada — usuario #' . $idUser, null, $idUsuarioReg);

    return ['ok' => true, 'message' => 'Profesor activado en el sistema.' . $googleMsg, 'id_usuario' => $idUser];
}

/** @return array<string,mixed>|null */
function docente_prospecto_resultados_candidato(PDO $pdo, int $idProspecto): ?array
{
    $p = docente_prospecto_obtener($pdo, $idProspecto);
    if (!$p) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT * FROM docente_showclass_eval WHERE id_prospecto = ? ORDER BY evaluado_en DESC LIMIT 1'
    );
    $st->execute([$idProspecto]);
    $show = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($show) {
        $show['rubrica'] = json_decode((string) ($show['rubrica_json'] ?? '[]'), true) ?: [];
    }
    $disc = null;
    if (!empty($p['disc_resultado_id'])) {
        $stD = $pdo->prepare('SELECT * FROM disc_res WHERE id = ? LIMIT 1');
        $stD->execute([(int) $p['disc_resultado_id']]);
        $disc = $stD->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    return [
        'prospecto' => $p,
        'showclass' => $show,
        'disc' => $disc,
    ];
}
