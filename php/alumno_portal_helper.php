<?php
/**
 * Portal del alumno: avisos, chat, Moodle, resúmenes.
 */

function alumno_portal_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $path = dirname(__DIR__) . '/sql/migrations/017_alumno_portal.sql';
    if (!is_file($path)) {
        return;
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        return;
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
        }
    }
}

function alumno_portal_es_alumno(): bool
{
    return function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() === 'alumno';
}

/** Staff en «vista de capacitación» simulando portal alumno. */
function alumno_portal_es_vista_simulada(): bool
{
    if (!function_exists('rbac_rol_efectivo') || rbac_rol_efectivo() !== 'alumno') {
        return false;
    }
    if (function_exists('rbac_esta_simulando_rol') && rbac_esta_simulando_rol()) {
        return true;
    }
    // Cuenta staff con rol efectivo alumno (p. ej. flag legacy ausente)
    return function_exists('rbac_rol_real') && rbac_rol_real() !== 'alumno';
}

/** Cuenta real de alumno (no staff previsualizando). */
function alumno_portal_es_cuenta_alumno_real(): bool
{
    return alumno_portal_es_alumno()
        && function_exists('rbac_rol_real')
        && rbac_rol_real() === 'alumno';
}

/** ID del alumno vinculado a la sesión (propio o previsualización en vista simulada). */
function alumno_portal_id_sesion(): int
{
    if (alumno_portal_es_vista_simulada()) {
        $sim = (int) ($_SESSION['rol_simulado_id_alumno'] ?? 0);
        if ($sim > 0) {
            return $sim;
        }
    }

    return (int) ($_SESSION['id_alumno_link'] ?? 0);
}

function alumno_portal_puede_ver(): bool
{
    if (alumno_portal_es_alumno()) {
        if (alumno_portal_id_sesion() > 0) {
            return true;
        }
        // Staff previsualizando portal alumno (supervisor, director, etc.)
        if (alumno_portal_es_vista_simulada()) {
            return true;
        }

        return false;
    }

    return function_exists('rbac_cap') && (
        rbac_cap('menu_alumno_portal')
        || rbac_cap('menu_alumno_calificaciones')
    );
}

/** Vista simulada sin alumno elegido aún. */
function alumno_portal_requiere_seleccion_simulacion(): bool
{
    return alumno_portal_es_vista_simulada() && alumno_portal_id_sesion() <= 0;
}

/**
 * ID del alumno activo o 0 si la vista debe detenerse (picker / alerta ya impreso).
 */
function alumno_portal_id_o_detener(): int
{
    $id = alumno_portal_id_sesion();
    if ($id > 0) {
        return $id;
    }
    if (alumno_portal_requiere_seleccion_simulacion()) {
        include dirname(__DIR__) . '/views/partials/alumno_simulacion_picker.php';

        return 0;
    }

    echo '<div class="alert">No hay registro de alumno vinculado a su cuenta.</div>';

    return 0;
}

/** @return array{status:string,message?:string,id_alumno?:int} */
function alumno_portal_establecer_alumno_simulacion(PDO $pdo, int $idAlumno): array
{
    if (!alumno_portal_es_vista_simulada()) {
        return ['status' => 'error', 'message' => 'Solo disponible en vista simulada de alumno.'];
    }
    if ($idAlumno <= 0) {
        unset($_SESSION['rol_simulado_id_alumno']);

        return ['status' => 'ok', 'message' => 'Selección quitada.'];
    }

    $row = alumno_portal_fila($pdo, $idAlumno);
    if (!$row) {
        return ['status' => 'error', 'message' => 'Alumno no encontrado.'];
    }

    $idPlantel = plantel_scope_id($pdo);
    if ($idPlantel > 0 && (int) ($row['id_plantel'] ?? 0) !== $idPlantel) {
        if (!function_exists('rbac_tiene_acceso_total') || !rbac_tiene_acceso_total()) {
            return ['status' => 'error', 'message' => 'El alumno pertenece a otro plantel.'];
        }
    }

    $_SESSION['rol_simulado_id_alumno'] = $idAlumno;

    return [
        'status' => 'ok',
        'message' => 'Alumno seleccionado para previsualización.',
        'id_alumno' => $idAlumno,
    ];
}

/** Verifica que el alumno solo acceda a su registro. */
function alumno_portal_exigir_propio(int $idAlumno): bool
{
    if (!alumno_portal_es_alumno()) {
        return true;
    }
    $propio = alumno_portal_id_sesion();

    return $propio > 0 && $propio === $idAlumno;
}

/** @return array<string, mixed>|null */
function alumno_portal_fila(PDO $pdo, int $idAlumno): ?array
{
    alumno_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT a.*, p.nombre AS plantel_nombre
         FROM alumnos a
         LEFT JOIN planteles p ON p.id_plantel = a.id_plantel
         WHERE a.id_alumno = ? LIMIT 1'
    );
    $st->execute([$idAlumno]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** Grupos activos del alumno con profesor. */
function alumno_portal_grupos_activos(PDO $pdo, int $idAlumno): array
{
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, e.nombre AS especialidad,
                CONCAT(u.nombre, \' \', u.apellido) AS profesor
         FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         LEFT JOIN usuarios u ON u.id_usuario = g.id_profesor
         WHERE ag.id_alumno = ? AND ag.activo = 1
         ORDER BY g.clave'
    );
    $st->execute([$idAlumno]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Avisos para el alumno: plantel, sus grupos, calificaciones recientes.
 *
 * @return list<array{tipo:string,titulo:string,mensaje:string,enlace?:string,prioridad:string,creado?:string}>
 */
function alumno_portal_notificaciones(PDO $pdo, int $idAlumno, int $idPlantel): array
{
    alumno_portal_ensure_schema($pdo);
    $items = [];
    $grupos = alumno_portal_grupos_activos($pdo, $idAlumno);
    $idsGrupo = array_map(static fn($g) => (int) $g['id_grupo'], $grupos);

    $sqlAviso = "SELECT * FROM alumno_aviso
         WHERE id_plantel = ? AND activo = 1
           AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())";
    $paramsAviso = [$idPlantel];
    if ($idsGrupo !== []) {
        $ph = implode(',', array_fill(0, count($idsGrupo), '?'));
        $sqlAviso .= " AND (id_grupo IS NULL OR id_grupo IN ($ph))";
        $paramsAviso = array_merge($paramsAviso, $idsGrupo);
    } else {
        $sqlAviso .= ' AND id_grupo IS NULL';
    }
    $sqlAviso .= ' ORDER BY creado_en DESC LIMIT 15';
    $st = $pdo->prepare($sqlAviso);
    $st->execute($paramsAviso);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $items[] = [
            'tipo' => 'aviso',
            'titulo' => $a['titulo'],
            'mensaje' => $a['mensaje'],
            'prioridad' => 'media',
            'creado' => $a['creado_en'] ?? '',
            'enlace' => 'alumno_portal_inicio',
        ];
    }

    $st = $pdo->prepare(
        'SELECT c.promedio, c.aprobado, c.observaciones, c.actualizado_en,
                f.nombre_fase, e.nombre AS especialidad, g.clave AS grupo
         FROM alumno_calificacion_parcial c
         INNER JOIN especialidad_fases f ON f.id_fase = c.id_fase
         INNER JOIN especialidades e ON e.id_especialidad = f.id_especialidad
         LEFT JOIN grupos g ON g.id_grupo = c.id_grupo
         WHERE c.id_alumno = ? AND c.actualizado_en >= DATE_SUB(NOW(), INTERVAL 60 DAY)
         ORDER BY c.actualizado_en DESC LIMIT 8'
    );
    $st->execute([$idAlumno]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $msg = ($c['especialidad'] ?? '') . ' · ' . ($c['nombre_fase'] ?? 'Parcial')
            . ' — calificación ' . ($c['promedio'] ?? '—');
        if ((int) ($c['aprobado'] ?? 0)) {
            $msg .= ' (aprobado)';
        }
        if (!empty($c['observaciones'])) {
            $msg .= '. ' . mb_substr((string) $c['observaciones'], 0, 120);
        }
        $items[] = [
            'tipo' => 'calificacion',
            'titulo' => 'Calificación actualizada',
            'mensaje' => $msg,
            'prioridad' => 'alta',
            'creado' => $c['actualizado_en'] ?? '',
            'enlace' => 'alumno_mis_calificaciones',
        ];
    }

    if (function_exists('pago_estado_cuenta')) {
        $ec = pago_estado_cuenta($pdo, $idAlumno);
        if (!empty($ec['ok'])) {
            $adeudo = (float) ($ec['resumen']['adeudo_colegiatura'] ?? 0);
            if ($adeudo > 0.01) {
                $items[] = [
                    'tipo' => 'pago',
                    'titulo' => 'Adeudo pendiente',
                    'mensaje' => 'Tiene un saldo de ' . catalog_format_mxn($adeudo) . ' en colegiaturas.',
                    'prioridad' => 'alta',
                    'enlace' => 'alumno_estado_cuenta&id=' . $idAlumno,
                ];
            }
        }
    }

    $idUser = (int) ($_SESSION['user_id'] ?? 0);
    if ($idUser > 0 && function_exists('notificaciones_usuario_bd')) {
        foreach (notificaciones_usuario_bd($pdo, $idUser, 10) as $n) {
            $items[] = [
                'tipo' => 'sistema',
                'titulo' => $n['titulo'] ?? 'Aviso',
                'mensaje' => $n['mensaje'] ?? '',
                'prioridad' => $n['prioridad'] ?? 'media',
                'enlace' => $n['enlace'] ?? '',
            ];
        }
    }

    $nChat = alumno_portal_chat_respuestas_recientes($pdo, $idPlantel, $idAlumno);
    if ($nChat > 0) {
        $items[] = [
            'tipo' => 'chat',
            'titulo' => 'Mensajes sin leer',
            'mensaje' => 'Tiene ' . $nChat . ' respuesta(s) reciente(s) de recepción o coordinación.',
            'prioridad' => 'alta',
            'enlace' => 'alumno_chat',
        ];
    }

    usort($items, static function ($a, $b) {
        $ord = ['alta' => 0, 'media' => 1, 'baja' => 2];
        $pa = $ord[$a['prioridad'] ?? 'media'] ?? 9;
        $pb = $ord[$b['prioridad'] ?? 'media'] ?? 9;
        if ($pa !== $pb) {
            return $pa <=> $pb;
        }

        return strcmp($b['creado'] ?? '', $a['creado'] ?? '');
    });

    return $items;
}

/** Cursos Moodle del alumno. */
function alumno_portal_cursos_moodle(PDO $pdo, int $idAlumno): array
{
    alumno_ensure_schema($pdo);
    $st = $pdo->prepare('SELECT moodle_user_id, numero_control FROM alumnos WHERE id_alumno = ? LIMIT 1');
    $st->execute([$idAlumno]);
    $al = $st->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        return ['ok' => false, 'message' => 'Alumno no encontrado', 'cursos' => []];
    }

    $moodleId = (int) ($al['moodle_user_id'] ?? 0);
    if ($moodleId <= 0 && function_exists('moodle_user_get_by_field') && !empty($al['numero_control'])) {
        $found = moodle_user_get_by_field('username', (string) $al['numero_control']);
        if (!empty($found['id'])) {
            $moodleId = (int) $found['id'];
        }
    }

    if ($moodleId <= 0 || !function_exists('moodle_enabled') || !moodle_enabled()) {
        return [
            'ok' => false,
            'message' => 'Cuenta Moodle no vinculada o Moodle no configurado.',
            'cursos' => [],
            'moodle_url' => defined('MOODLE_URL') ? MOODLE_URL : '',
        ];
    }

    if (!function_exists('moodle_api_call')) {
        return ['ok' => false, 'message' => 'API Moodle no disponible', 'cursos' => []];
    }

    $res = moodle_api_call('core_enrol_get_users_courses', ['userid' => $moodleId]);
    if (empty($res['ok'])) {
        return [
            'ok' => false,
            'message' => $res['message'] ?? 'No se pudieron cargar los cursos',
            'cursos' => [],
            'moodle_url' => defined('MOODLE_URL') ? MOODLE_URL : '',
        ];
    }

    $base = rtrim((string) (defined('MOODLE_URL') ? MOODLE_URL : ''), '/');
    $cursos = [];
    foreach ((array) ($res['data'] ?? []) as $c) {
        if (!is_array($c)) {
            continue;
        }
        $id = (int) ($c['id'] ?? 0);
        $cursos[] = [
            'id' => $id,
            'nombre' => $c['fullname'] ?? $c['shortname'] ?? 'Curso',
            'corto' => $c['shortname'] ?? '',
            'url' => $base !== '' && $id > 0 ? $base . '/course/view.php?id=' . $id : $base,
        ];
    }

    return [
        'ok' => true,
        'cursos' => $cursos,
        'moodle_url' => $base,
        'moodle_user_id' => $moodleId,
    ];
}

function alumno_portal_ensure_chat_salas(PDO $pdo, int $idPlantel, int $idAlumno): array
{
    alumno_portal_ensure_schema($pdo);
    $salas = [];

    foreach (['recepcion' => 'Recepción', 'coordinacion' => 'Coordinación'] as $tipo => $nombre) {
        $stChk = $pdo->prepare('SELECT id_sala FROM alumno_chat_sala WHERE id_plantel = ? AND tipo = ? LIMIT 1');
        $stChk->execute([$idPlantel, $tipo]);
        if (!$stChk->fetchColumn()) {
            $pdo->prepare('INSERT INTO alumno_chat_sala (id_plantel, tipo, id_grupo, nombre) VALUES (?,?,NULL,?)')
                ->execute([$idPlantel, $tipo, $nombre]);
        }
    }

    foreach (alumno_portal_grupos_activos($pdo, $idAlumno) as $g) {
        $idG = (int) $g['id_grupo'];
        $nom = 'Grupo ' . ($g['clave'] ?? $idG);
        $stChk = $pdo->prepare('SELECT id_sala FROM alumno_chat_sala WHERE id_plantel = ? AND tipo = ? AND id_grupo = ? LIMIT 1');
        $stChk->execute([$idPlantel, 'grupo', $idG]);
        if (!$stChk->fetchColumn()) {
            $pdo->prepare('INSERT INTO alumno_chat_sala (id_plantel, tipo, id_grupo, nombre) VALUES (?,?,?,?)')
                ->execute([$idPlantel, 'grupo', $idG, $nom]);
        }
    }

    $st = $pdo->prepare(
        "SELECT s.* FROM alumno_chat_sala s
         WHERE s.id_plantel = ?
           AND (s.tipo IN ('recepcion','coordinacion')
                OR (s.tipo = 'grupo' AND s.id_grupo IN (
                    SELECT ag.id_grupo FROM alumno_grupos ag
                    WHERE ag.id_alumno = ? AND ag.activo = 1
                )))
         ORDER BY FIELD(s.tipo,'grupo','recepcion','coordinacion'), s.nombre"
    );
    $st->execute([$idPlantel, $idAlumno]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function alumno_portal_chat_mensajes(PDO $pdo, int $idSala, int $limite = 80): array
{
    alumno_portal_ensure_schema($pdo);
    $limite = max(10, min(200, $limite));
    $st = $pdo->prepare(
        "SELECT * FROM alumno_chat_mensaje WHERE id_sala = ?
         ORDER BY creado_en DESC LIMIT $limite"
    );
    $st->execute([$idSala]);
    $rows = array_reverse($st->fetchAll(PDO::FETCH_ASSOC) ?: []);

    return $rows;
}

function alumno_portal_chat_enviar(
    PDO $pdo,
    int $idSala,
    int $idAlumno,
    string $mensaje,
    string $autorNombre
): array {
    alumno_portal_ensure_schema($pdo);
    $mensaje = trim($mensaje);
    if ($mensaje === '') {
        return ['ok' => false, 'message' => 'Escriba un mensaje'];
    }
    if (mb_strlen($mensaje) > 2000) {
        return ['ok' => false, 'message' => 'Mensaje demasiado largo'];
    }

    $st = $pdo->prepare(
        'INSERT INTO alumno_chat_mensaje (id_sala, id_alumno, autor_nombre, mensaje)
         VALUES (?,?,?,?)'
    );
    $st->execute([$idSala, $idAlumno, $autorNombre, $mensaje]);

    return ['ok' => true, 'id_mensaje' => (int) $pdo->lastInsertId()];
}

/** Respuestas de staff en las salas del alumno (últimos 7 días). */
function alumno_portal_chat_respuestas_recientes(PDO $pdo, int $idPlantel, int $idAlumno, int $dias = 7): int
{
    alumno_portal_ensure_schema($pdo);
    $salas = alumno_portal_ensure_chat_salas($pdo, $idPlantel, $idAlumno);
    $ids = array_values(array_filter(array_map(static fn($s) => (int) ($s['id_sala'] ?? 0), $salas)));
    if ($ids === []) {
        return 0;
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $dias = max(1, min(30, $dias));
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM alumno_chat_mensaje
         WHERE id_sala IN ($ph)
           AND id_usuario IS NOT NULL AND id_usuario > 0
           AND creado_en >= DATE_SUB(NOW(), INTERVAL $dias DAY)"
    );
    $st->execute($ids);

    return (int) $st->fetchColumn();
}

function academico_alumno_portal_puede(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    if (function_exists('rbac_cap') && rbac_cap('menu_academico')) {
        return true;
    }

    return function_exists('profesor_portal_es_profesor') && profesor_portal_es_profesor();
}

/** Grupos que el usuario staff puede usar para avisos. */
function academico_alumno_portal_grupos(PDO $pdo, int $idPlantel): array
{
    if (function_exists('profesor_portal_es_profesor') && profesor_portal_es_profesor()) {
        $idProf = (int) ($_SESSION['user_id'] ?? 0);

        return profesor_portal_grupos($pdo, $idProf);
    }
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, e.nombre AS esp_nombre
         FROM grupos g
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_plantel = ?
         ORDER BY g.clave'
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function academico_alumno_portal_puede_grupo(PDO $pdo, int $idGrupo, int $idPlantel): bool
{
    if ($idGrupo <= 0) {
        return function_exists('rbac_cap') && rbac_cap('menu_academico');
    }
    foreach (academico_alumno_portal_grupos($pdo, $idPlantel) as $g) {
        if ((int) ($g['id_grupo'] ?? 0) === $idGrupo) {
            return true;
        }
    }

    return false;
}

/** @return list<array<string, mixed>> */
function alumno_aviso_listar_staff(PDO $pdo, int $idPlantel, int $limite = 50): array
{
    alumno_portal_ensure_schema($pdo);
    $limite = max(5, min(200, $limite));
    $grupos = academico_alumno_portal_grupos($pdo, $idPlantel);
    $ids = array_map(static fn($g) => (int) ($g['id_grupo'] ?? 0), $grupos);
    $ids = array_values(array_filter($ids));

    if (function_exists('rbac_cap') && rbac_cap('menu_academico')) {
        $st = $pdo->prepare(
            "SELECT a.*, g.clave AS grupo_clave
             FROM alumno_aviso a
             LEFT JOIN grupos g ON g.id_grupo = a.id_grupo
             WHERE a.id_plantel = ?
             ORDER BY a.creado_en DESC LIMIT $limite"
        );
        $st->execute([$idPlantel]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($ids === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare(
        "SELECT a.*, g.clave AS grupo_clave
         FROM alumno_aviso a
         LEFT JOIN grupos g ON g.id_grupo = a.id_grupo
         WHERE a.id_plantel = ? AND (a.id_grupo IS NULL OR a.id_grupo IN ($ph))
         ORDER BY a.creado_en DESC LIMIT $limite"
    );
    $st->execute(array_merge([$idPlantel], $ids));

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function alumno_aviso_guardar_staff(PDO $pdo, int $idPlantel, array $data): array
{
    alumno_portal_ensure_schema($pdo);
    $titulo = trim((string) ($data['titulo'] ?? ''));
    $mensaje = trim((string) ($data['mensaje'] ?? ''));
    $idGrupo = isset($data['id_grupo']) && $data['id_grupo'] !== '' ? (int) $data['id_grupo'] : null;
    $vigente = trim((string) ($data['vigente_hasta'] ?? ''));
    $activo = (int) ($data['activo'] ?? 1) ? 1 : 0;
    $idAviso = (int) ($data['id_aviso'] ?? 0);

    if ($titulo === '' || $mensaje === '') {
        return ['ok' => false, 'message' => 'Título y mensaje son obligatorios'];
    }
    if ($idGrupo !== null && $idGrupo > 0 && !academico_alumno_portal_puede_grupo($pdo, $idGrupo, $idPlantel)) {
        return ['ok' => false, 'message' => 'No puede publicar avisos en ese grupo'];
    }
    if ($idGrupo === null && !(function_exists('rbac_cap') && rbac_cap('menu_academico'))) {
        return ['ok' => false, 'message' => 'Solo coordinación puede publicar avisos de todo el plantel'];
    }

    $autor = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''));
    $idUser = (int) ($_SESSION['user_id'] ?? 0);
    $vigenteSql = $vigente !== '' ? $vigente : null;

    if ($idAviso > 0) {
        $st = $pdo->prepare(
            'UPDATE alumno_aviso SET id_grupo = ?, titulo = ?, mensaje = ?, vigente_hasta = ?, activo = ?
             WHERE id_aviso = ? AND id_plantel = ?'
        );
        $st->execute([$idGrupo, $titulo, $mensaje, $vigenteSql, $activo, $idAviso, $idPlantel]);
        if ($st->rowCount() === 0) {
            return ['ok' => false, 'message' => 'Aviso no encontrado'];
        }

        return ['ok' => true, 'id_aviso' => $idAviso];
    }

    $st = $pdo->prepare(
        'INSERT INTO alumno_aviso (id_plantel, id_grupo, titulo, mensaje, id_usuario_autor, autor_nombre, vigente_hasta, activo)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $st->execute([$idPlantel, $idGrupo, $titulo, $mensaje, $idUser ?: null, $autor !== '' ? $autor : null, $vigenteSql, $activo]);

    return ['ok' => true, 'id_aviso' => (int) $pdo->lastInsertId()];
}

function academico_alumno_portal_chat_salas(PDO $pdo, int $idPlantel): array
{
    alumno_portal_ensure_schema($pdo);
    foreach (['recepcion' => 'Recepción', 'coordinacion' => 'Coordinación'] as $tipo => $nombre) {
        $stChk = $pdo->prepare('SELECT id_sala FROM alumno_chat_sala WHERE id_plantel = ? AND tipo = ? LIMIT 1');
        $stChk->execute([$idPlantel, $tipo]);
        if (!$stChk->fetchColumn()) {
            $pdo->prepare('INSERT INTO alumno_chat_sala (id_plantel, tipo, id_grupo, nombre) VALUES (?,?,NULL,?)')
                ->execute([$idPlantel, $tipo, $nombre]);
        }
    }
    if (function_exists('rbac_cap') && rbac_cap('menu_academico')) {
        $stG = $pdo->prepare('SELECT id_grupo, clave FROM grupos WHERE id_plantel = ? ORDER BY clave');
        $stG->execute([$idPlantel]);
        foreach ($stG->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $idG = (int) ($g['id_grupo'] ?? 0);
            if ($idG <= 0) {
                continue;
            }
            $stChk = $pdo->prepare('SELECT id_sala FROM alumno_chat_sala WHERE id_plantel = ? AND tipo = ? AND id_grupo = ? LIMIT 1');
            $stChk->execute([$idPlantel, 'grupo', $idG]);
            if (!$stChk->fetchColumn()) {
                $pdo->prepare('INSERT INTO alumno_chat_sala (id_plantel, tipo, id_grupo, nombre) VALUES (?,?,?,?)')
                    ->execute([$idPlantel, 'grupo', $idG, 'Grupo ' . ($g['clave'] ?? $idG)]);
            }
        }
    }
    $st = $pdo->prepare(
        "SELECT s.*, g.clave AS grupo_clave
         FROM alumno_chat_sala s
         LEFT JOIN grupos g ON g.id_grupo = s.id_grupo
         WHERE s.id_plantel = ?
         ORDER BY FIELD(s.tipo,'grupo','recepcion','coordinacion'), s.nombre"
    );
    $st->execute([$idPlantel]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function alumno_portal_chat_enviar_staff(
    PDO $pdo,
    int $idSala,
    int $idUsuario,
    string $mensaje,
    string $autorNombre
): array {
    alumno_portal_ensure_schema($pdo);
    $mensaje = trim($mensaje);
    if ($mensaje === '') {
        return ['ok' => false, 'message' => 'Escriba un mensaje'];
    }
    if (mb_strlen($mensaje) > 2000) {
        return ['ok' => false, 'message' => 'Mensaje demasiado largo'];
    }

    $st = $pdo->prepare(
        'INSERT INTO alumno_chat_mensaje (id_sala, id_usuario, id_alumno, autor_nombre, mensaje)
         VALUES (?,?,NULL,?,?)'
    );
    $st->execute([$idSala, $idUsuario, $autorNombre, $mensaje]);

    return ['ok' => true, 'id_mensaje' => (int) $pdo->lastInsertId()];
}

/** Resumen de pagos para dashboard. */
function alumno_portal_resumen_pagos(PDO $pdo, int $idAlumno): array
{
    if (!function_exists('pago_estado_cuenta')) {
        return ['adeudo' => 0, 'ultimos' => []];
    }
    $ec = pago_estado_cuenta($pdo, $idAlumno);
    $ultimos = [];
    if (!empty($ec['ok']) && !empty($ec['pagos_colegiatura'])) {
        $ultimos = array_slice($ec['pagos_colegiatura'], 0, 5);
    }

    return [
        'adeudo' => (float) ($ec['resumen']['adeudo_colegiatura'] ?? 0),
        'pagado' => (float) ($ec['resumen']['colegiatura_pagada'] ?? 0),
        'ultimos' => $ultimos,
        'ok' => !empty($ec['ok']),
    ];
}
