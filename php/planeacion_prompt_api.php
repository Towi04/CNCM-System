<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión no iniciada']);
    exit;
}

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

if ($action === 'placeholders') {
    hay_json_response([
        'status' => 'ok',
        'placeholders' => planeacion_prompt_placeholders(),
    ]);
    exit;
}

if (!planeacion_prompt_puede_configurar()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso para configurar plantillas']);
    exit;
}

planeacion_prompt_ensure_schema($pdo);

if ($action === 'get') {
    $idEsp = (int) ($_GET['id_especialidad'] ?? 0);
    if ($idEsp <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Especialidad requerida']);
        exit;
    }
    $st = $pdo->prepare(
        'SELECT id_especialidad, clave, nombre, modalidad, prompt_planeacion FROM especialidades WHERE id_especialidad = ? LIMIT 1'
    );
    $st->execute([$idEsp]);
    $esp = $st->fetch(PDO::FETCH_ASSOC);
    if (!$esp) {
        hay_json_response(['status' => 'error', 'message' => 'Especialidad no encontrada']);
        exit;
    }
    $custom = planeacion_prompt_obtener_raw($pdo, $idEsp);
    hay_json_response([
        'status' => 'ok',
        'especialidad' => [
            'id_especialidad' => (int) $esp['id_especialidad'],
            'clave' => $esp['clave'],
            'nombre' => $esp['nombre'],
        ],
        'plantilla' => $custom ?? planeacion_prompt_plantilla_default($esp),
        'es_personalizada' => $custom !== null,
        'es_ingles' => planeacion_prompt_es_ingles($esp),
        'plantilla_default' => planeacion_prompt_plantilla_default($esp),
        'placeholders' => planeacion_prompt_placeholders(),
    ]);
    exit;
}

if ($action === 'save') {
    $idEsp = (int) ($_POST['id_especialidad'] ?? 0);
    $plantilla = trim((string) ($_POST['plantilla'] ?? ''));
    $res = planeacion_prompt_guardar($pdo, $idEsp, $plantilla);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
    ]);
    exit;
}

if ($action === 'reset') {
    $idEsp = (int) ($_POST['id_especialidad'] ?? 0);
    if ($idEsp <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Especialidad requerida']);
        exit;
    }
    $pdo->prepare('UPDATE especialidades SET prompt_planeacion = NULL WHERE id_especialidad = ?')
        ->execute([$idEsp]);
    $st = $pdo->prepare('SELECT id_especialidad, clave, nombre, modalidad FROM especialidades WHERE id_especialidad = ? LIMIT 1');
    $st->execute([$idEsp]);
    $esp = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    hay_json_response([
        'status' => 'ok',
        'message' => 'Plantilla restaurada al valor predeterminado.',
        'plantilla' => planeacion_prompt_plantilla_default($esp),
        'es_personalizada' => false,
    ]);
    exit;
}

if ($action === 'preview') {
    $idEsp = (int) ($_POST['id_especialidad'] ?? 0);
    $plantilla = trim((string) ($_POST['plantilla'] ?? ''));
    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);
    $idFase = (int) ($_POST['id_fase'] ?? 0);
    $tema = trim((string) ($_POST['tema'] ?? 'Tema de ejemplo'));
    $duracion = trim((string) ($_POST['duracion'] ?? '50'));

    if ($plantilla === '' && $idEsp > 0) {
        $plantilla = planeacion_prompt_obtener($pdo, $idEsp);
    }
    if ($plantilla === '') {
        hay_json_response(['status' => 'error', 'message' => 'Plantilla vacía']);
        exit;
    }

    if ($idGrupo > 0 && planeacion_puede_grupo($pdo, $idGrupo)) {
        $grupo = planeacion_grupo_detalle($pdo, $idGrupo) ?: [];
        $fase = $idFase > 0
            ? (planeacion_prompt_fase_detalle($pdo, $idFase) ?: [])
            : [];
        if ($fase === []) {
            foreach (planeacion_fases_grupo($pdo, $idGrupo) as $f) {
                if ($idFase <= 0 || (int) $f['id_fase'] === $idFase) {
                    $fase = planeacion_prompt_fase_detalle($pdo, (int) $f['id_fase']) ?: $f;
                    break;
                }
            }
        }
        $activos = 0;
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM alumno_grupos ag
                 INNER JOIN alumnos a ON a.id_alumno = ag.id_alumno
                 WHERE ag.id_grupo = ? AND ag.activo = 1 AND a.estado = \'activo\''
            );
            $st->execute([$idGrupo]);
            $activos = (int) $st->fetchColumn();
        } catch (Throwable $e) {
            $activos = 0;
        }
        $vars = planeacion_prompt_vars_contexto($pdo, $grupo, $fase, $tema, $duracion, $activos);
        $fuente = 'grupo';
    } else {
        $vars = planeacion_prompt_vars_ejemplo();
        $fuente = 'ejemplo';
    }

    hay_json_response([
        'status' => 'ok',
        'prompt_resuelto' => planeacion_prompt_aplicar($plantilla, $vars),
        'variables' => $vars,
        'fuente_datos' => $fuente,
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
