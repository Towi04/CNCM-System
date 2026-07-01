<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$idUsuario = (int) $_SESSION['user_id'];

if ($action === 'buscar_alumno') {
    if (!asesoria_puede_agendar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $items = function_exists('asistencia_buscar_alumnos')
        ? asistencia_buscar_alumnos($pdo, $q, $idPlantel, 10)
        : [];
    hay_json_response(['status' => 'ok', 'items' => $items]);
    exit;
}

if ($action === 'alumno_info') {
    if (!asesoria_puede_agendar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $idAlumno = (int) ($_GET['id_alumno'] ?? 0);
    $elig = asesoria_alumno_elegible($pdo, $idAlumno, $idPlantel);
    if (!$elig['ok']) {
        hay_json_response(['status' => 'error', 'message' => $elig['message']]);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'alumno' => $elig['alumno'],
        'tipos' => asesoria_tipos_disponibles($pdo, $idAlumno, $idPlantel),
        'credito_saldo' => asesoria_credito_saldo($pdo, $idAlumno, $idPlantel),
        'en_personalizado' => asesoria_alumno_en_personalizado($pdo, $idAlumno),
        'grupos' => asesoria_alumno_grupos_activos($pdo, $idAlumno),
    ]);
    exit;
}

if ($action === 'profesores') {
    if (!asesoria_puede_agendar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $materia = trim((string) ($_GET['materia_clave'] ?? ''));
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    $kidsDual = !empty($_GET['kids_dual']);
    hay_json_response([
        'status' => 'ok',
        'profesores' => asesoria_profesores_para_materia($pdo, $idPlantel, $materia, $idEsp ?: null, $kidsDual),
    ]);
    exit;
}

if ($action === 'slots') {
    if (!asesoria_puede_agendar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $idProf = (int) ($_GET['id_profesor'] ?? 0);
    $desde = trim((string) ($_GET['desde'] ?? date('Y-m-d', strtotime('+1 day'))));
    $hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d', strtotime('+14 days'))));
    hay_json_response([
        'status' => 'ok',
        'slots' => asesoria_slots_disponibles($pdo, $idPlantel, $idProf, $desde, $hasta),
    ]);
    exit;
}

if ($action === 'listar') {
    if (!asesoria_puede_ver_calendario()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $filtros = [];
    if (!empty($_GET['fecha'])) {
        $filtros['fecha'] = $_GET['fecha'];
    }
    if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
        $filtros['desde'] = $_GET['desde'];
        $filtros['hasta'] = $_GET['hasta'];
    }
    if (!empty($_GET['estado'])) {
        $filtros['estado'] = $_GET['estado'];
    }
    if (!empty($_GET['id_profesor'])) {
        $filtros['id_profesor'] = (int) $_GET['id_profesor'];
    }
    if (rbac_rol_efectivo() === 'profesor' && !asesoria_puede_administrar()) {
        $filtros['id_profesor'] = $idUsuario;
    }
    $items = asesoria_listar($pdo, $idPlantel, $filtros, 100);
    foreach ($items as &$it) {
        $it['alumnos'] = asesoria_cita_alumnos($pdo, (int) $it['id_cita']);
    }
    unset($it);
    hay_json_response(['status' => 'ok', 'items' => $items, 'estados' => ASESORIA_ESTADOS, 'tipos' => ASESORIA_TIPOS]);
    exit;
}

if ($action === 'detalle') {
    $idCita = (int) ($_GET['id_cita'] ?? 0);
    $cita = asesoria_cita_obtener($pdo, $idCita, $idPlantel);
    if (!$cita) {
        hay_json_response(['status' => 'error', 'message' => 'No encontrada']);
        exit;
    }
    if (rbac_rol_efectivo() === 'profesor' && (int) $cita['id_profesor'] !== $idUsuario && !asesoria_puede_administrar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'cita' => $cita,
        'alumnos' => asesoria_cita_alumnos($pdo, $idCita),
    ]);
    exit;
}

if ($action === 'agendar') {
    $data = $_POST;
    $data['id_plantel'] = $idPlantel;
    if (!empty($data['alumnos_json'])) {
        $alumnos = json_decode((string) $data['alumnos_json'], true);
        if (is_array($alumnos) && $alumnos !== []) {
            $results = [];
            $idCita = 0;
            foreach ($alumnos as $i => $al) {
                $payload = array_merge($data, $al);
                if ($idCita > 0) {
                    $payload['id_cita_agrupar'] = $idCita;
                }
                $res = asesoria_agendar($pdo, $payload, $idUsuario);
                if (!$res['ok']) {
                    hay_json_response($res);
                    exit;
                }
                $idCita = (int) ($res['id_cita'] ?? $idCita);
                $results[] = $res;
            }
            hay_json_response(['status' => 'ok', 'message' => 'Asesoría grupal agendada', 'id_cita' => $idCita]);
            exit;
        }
    }
    $res = asesoria_agendar($pdo, $data, $idUsuario);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'estado') {
    if (!asesoria_puede_agendar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $idCita = (int) ($_POST['id_cita'] ?? 0);
    $estado = trim((string) ($_POST['estado'] ?? ''));
    if ($estado === 'cancelada_a_tiempo') {
        $res = asesoria_cancelar_a_tiempo($pdo, $idCita, trim((string) ($_POST['motivo'] ?? '')));
    } else {
        $res = asesoria_cambiar_estado($pdo, $idCita, $estado, [
            'id_plantel' => $idPlantel,
            'forma_pago' => $_POST['forma_pago'] ?? 'Efectivo',
            'num_presentes' => (int) ($_POST['num_presentes'] ?? 0),
        ]);
    }
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'tabulador_listar') {
    if (!asesoria_puede_administrar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    hay_json_response(['status' => 'ok', 'items' => asesoria_tabulador_listar($pdo, $idPlantel)]);
    exit;
}

if ($action === 'tabulador_guardar') {
    $res = asesoria_tabulador_guardar($pdo, $_POST, $idPlantel);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'materias_listar') {
    $idProf = (int) ($_GET['id_usuario'] ?? $_POST['id_usuario'] ?? 0);
    if ($idProf <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Usuario requerido']);
        exit;
    }
    hay_json_response(['status' => 'ok', 'items' => profesor_asesoria_materia_listar($pdo, $idProf, $idPlantel)]);
    exit;
}

if ($action === 'materias_guardar') {
    $idProf = (int) ($_POST['id_usuario'] ?? 0);
    $items = json_decode((string) ($_POST['items'] ?? '[]'), true) ?: [];
    $res = profesor_asesoria_materia_guardar($pdo, $idProf, $items, $idPlantel);
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'credito_otorgar') {
    if (!asesoria_puede_administrar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $res = asesoria_credito_otorgar(
        $pdo,
        (int) ($_POST['id_alumno'] ?? 0),
        (float) ($_POST['horas'] ?? 1),
        trim((string) ($_POST['origen'] ?? 'director_cortesia')),
        [
            'id_plantel' => $idPlantel,
            'solo_individual' => !empty($_POST['solo_individual']),
            'notas' => $_POST['notas'] ?? '',
            'id_usuario' => $idUsuario,
        ]
    );
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

if ($action === 'moodle_verificar') {
    $idAlumno = (int) ($_GET['id_alumno'] ?? 0);
    $idGrupo = (int) ($_GET['id_grupo'] ?? 0);
    $ok = asesoria_moodle_verificar_alumno($pdo, $idAlumno, $idGrupo, $_GET['semana_falta'] ?? null);
    hay_json_response(['status' => 'ok', 'completado' => $ok]);
    exit;
}

if ($action === 'nomina_importar') {
    $res = asesoria_nomina_importar(
        $pdo,
        (int) ($_POST['id_liquidacion'] ?? 0),
        $idPlantel,
        $_POST['desde'] ?? null,
        $_POST['hasta'] ?? null
    );
    hay_json_response(array_merge(['status' => $res['ok'] ? 'ok' : 'error'], $res));
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);

/** @return list<array<string, mixed>> */
function asesoria_alumno_grupos_activos(PDO $pdo, int $idAlumno): array
{
    $st = $pdo->prepare(
        'SELECT ag.id_grupo, g.clave, g.id_especialidad, e.nombre AS esp_nombre, g.es_personalizado
         FROM alumno_grupos ag
         INNER JOIN grupos g ON g.id_grupo = ag.id_grupo
         LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE ag.id_alumno = ? AND ag.activo = 1
         ORDER BY g.clave'
    );
    $st->execute([$idAlumno]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
