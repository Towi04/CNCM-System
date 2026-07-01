<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

$idGrupo = (int) ($_GET['id_grupo'] ?? $_POST['id_grupo'] ?? 0);
if (!isset($_SESSION['user_id']) || !calificaciones_puede_capturar_grupo($pdo, $idGrupo)) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso para este grupo']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$idUsuario = (int) $_SESSION['user_id'];

if ($action === 'cargar') {
    $grupo = calificaciones_cargar_grupo($pdo, $idGrupo);
    if (!$grupo) {
        hay_json_response(['status' => 'error', 'message' => 'Grupo no encontrado']);
        exit;
    }
    $idFase = (int) ($_GET['id_fase'] ?? $_POST['id_fase'] ?? 0);
    if ($idFase <= 0) {
        $idFase = calificaciones_fase_sugerida($pdo, $grupo);
    }
    $idEsp = (int) ($grupo['id_especialidad'] ?? 0);
    $fases = $idEsp ? fase_listar($pdo, $idEsp) : [];
    $rubrica = $idFase > 0 ? calificaciones_obtener_rubrica($pdo, $idGrupo, $idFase) : [];
    $rubricaGuardada = $idFase > 0 && calificaciones_rubrica_guardada($pdo, $idGrupo, $idFase);
    $alumnos = $idFase > 0 ? calificaciones_listar_alumnos($pdo, $idGrupo, $idFase) : [];
    $pos = academico_posicion_grupo($pdo, $grupo);

    hay_json_response([
        'status' => 'ok',
        'grupo' => [
            'id_grupo' => (int) $grupo['id_grupo'],
            'clave' => $grupo['clave'],
            'id_fase_actual' => (int) ($grupo['id_fase_actual'] ?? 0),
        ],
        'fases' => array_map(static fn ($f) => [
            'id_fase' => (int) $f['id_fase'],
            'clave_fase' => $f['clave_fase'] ?? '',
            'nombre_fase' => $f['nombre_fase'] ?? '',
        ], $fases),
        'id_fase' => $idFase,
        'rubrica' => $rubrica,
        'rubrica_guardada' => $rubricaGuardada,
        'criterios_labels' => calificaciones_criterios_etiquetas(),
        'alumnos' => $alumnos,
        'posicion' => $pos,
        'nota_minima' => ACADEMICO_NOTA_MINIMA,
    ]);
    exit;
}

if ($action === 'guardar_rubrica') {
    $idFase = (int) ($_POST['id_fase'] ?? 0);
    $criterios = json_decode($_POST['criterios'] ?? '[]', true);
    if ($idFase <= 0 || !is_array($criterios)) {
        hay_json_response(['status' => 'error', 'message' => 'Datos inválidos']);
        exit;
    }
    $res = calificaciones_guardar_rubrica($pdo, $idGrupo, $idFase, $criterios, $idUsuario);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'guardar_alumno') {
    $idAlumno = (int) ($_POST['id_alumno'] ?? 0);
    $idFase = (int) ($_POST['id_fase'] ?? 0);
    $notas = json_decode($_POST['notas'] ?? '{}', true);
    $obs = trim($_POST['observaciones'] ?? '');
    if ($idAlumno <= 0 || $idFase <= 0 || !is_array($notas)) {
        hay_json_response(['status' => 'error', 'message' => 'Datos inválidos']);
        exit;
    }
    $rubrica = calificaciones_obtener_rubrica($pdo, $idGrupo, $idFase);
    $res = calificaciones_guardar_alumno($pdo, $idAlumno, $idFase, $idGrupo, $notas, $rubrica, $idUsuario, $obs);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'promedio' => $res['promedio'] ?? null,
        'aprobado' => $res['aprobado'] ?? null,
    ]);
    exit;
}

if ($action === 'guardar_lote') {
    $idFase = (int) ($_POST['id_fase'] ?? 0);
    $lote = json_decode($_POST['alumnos'] ?? '[]', true);
    if ($idFase <= 0 || !is_array($lote)) {
        hay_json_response(['status' => 'error', 'message' => 'Datos inválidos']);
        exit;
    }
    $rubrica = calificaciones_obtener_rubrica($pdo, $idGrupo, $idFase);
    $ok = 0;
    $errores = [];
    foreach ($lote as $row) {
        $idAlumno = (int) ($row['id_alumno'] ?? 0);
        $notas = $row['notas'] ?? [];
        if ($idAlumno <= 0 || !is_array($notas)) {
            continue;
        }
        $res = calificaciones_guardar_alumno(
            $pdo,
            $idAlumno,
            $idFase,
            $idGrupo,
            $notas,
            $rubrica,
            $idUsuario,
            $row['observaciones'] ?? null
        );
        if ($res['ok']) {
            $ok++;
        } else {
            $errores[] = $res['message'];
        }
    }
    hay_json_response([
        'status' => 'ok',
        'message' => "Guardadas {$ok} calificaciones" . ($errores ? '. Algunos errores.' : ''),
        'guardadas' => $ok,
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
