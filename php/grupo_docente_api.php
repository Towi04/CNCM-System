<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autenticado'], 401);
    exit;
}

if (!grupo_docente_puede_gestionar()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));
$idPlantel = plantel_scope_id($pdo);

if ($action === 'listar') {
    $idGrupo = (int) ($_GET['id_grupo'] ?? 0);
    if ($idGrupo <= 0 || !plantel_grupo_pertenece($pdo, $idGrupo, $idPlantel)) {
        hay_json_response(['status' => 'error', 'message' => 'Grupo no válido']);
        exit;
    }
    $st = $pdo->prepare(
        'SELECT g.id_grupo, g.clave, g.codigo_area, g.es_extensivo, g.es_personalizado, e.nombre AS esp_nombre
         FROM grupos g LEFT JOIN especialidades e ON e.id_especialidad = g.id_especialidad
         WHERE g.id_grupo = ? AND g.id_plantel = ? LIMIT 1'
    );
    $st->execute([$idGrupo, $idPlantel]);
    $grupo = $st->fetch(PDO::FETCH_ASSOC);
    if (!$grupo) {
        hay_json_response(['status' => 'error', 'message' => 'Grupo no encontrado']);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'grupo' => $grupo,
        'docentes' => grupo_docente_listar_grupo($pdo, $idGrupo),
        'profesores' => array_map(static function ($p) {
            $lbl = trim($p['nombre_completo'] ?? '');
            if (!empty($p['hay_areas'])) {
                $lbl .= ' · ' . $p['hay_areas'];
            }

            return ['id' => (int) $p['id_usuario'], 'label' => $lbl];
        }, grupo_docente_listar_profesores_plantel($pdo, $idPlantel)),
        'materias_sugeridas' => grupo_docente_materias_sugeridas([
            'codigo_area' => $grupo['codigo_area'] ?? '',
            'es_personalizado' => (int) ($grupo['es_personalizado'] ?? 0),
            'es_extensivo' => (int) ($grupo['es_extensivo'] ?? 0),
            'clave' => $grupo['clave'] ?? '',
        ]),
        'multi_materia' => grupo_docente_requiere_multi_materia([
            'codigo_area' => $grupo['codigo_area'] ?? '',
            'es_personalizado' => (int) ($grupo['es_personalizado'] ?? 0),
            'es_extensivo' => (int) ($grupo['es_extensivo'] ?? 0),
            'clave' => $grupo['clave'] ?? '',
        ]),
    ]);
    exit;
}

if ($action === 'guardar') {
    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);
    $asignaciones = grupo_docente_parse_post($_POST);
    $res = grupo_docente_guardar($pdo, $idGrupo, $idPlantel, $asignaciones);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'] ?? '',
        'id_profesor_titular' => $res['id_profesor_titular'] ?? null,
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
