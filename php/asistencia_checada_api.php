<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if (!asistencia_puede_checada()) {
    hay_json_response(['status' => 'error', 'message' => 'No autorizado para checada']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$idOperador = (int) $_SESSION['user_id'];
$accion = trim($_POST['accion'] ?? $_GET['accion'] ?? '');

if ($accion === 'registrar') {
    $codigo = trim($_POST['codigo_huella'] ?? '');
    $como = trim((string) ($_POST['asistencia_como'] ?? ''));
    if ($codigo === '') {
        hay_json_response(['status' => 'error', 'message' => 'Indique el número de control (huella)']);
        exit;
    }

    // Si el código corresponde a personal que también es alumno, pedir elección.
    if ($como === '') {
        $st = $pdo->prepare(
            "SELECT u.id_usuario, u.id_alumno, CONCAT(u.nombre,' ',u.apellido) AS nombre
             FROM usuarios u
             WHERE u.codigo_huella = ? AND u.rol <> 'alumno' AND (u.id_plantel = ? OR u.id_plantel IS NULL)
             LIMIT 1"
        );
        $st->execute([$codigo, $idPlantel]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u && !empty($u['id_alumno'])) {
            $idAlumno = (int) $u['id_alumno'];
            $idGrupo = asistencia_resolver_grupo_activo($pdo, $idAlumno, $idPlantel, date('Y-m-d H:i:s')) ?? 0;
            if ($idGrupo > 0) {
                hay_json_response([
                    'status' => 'choose',
                    'message' => 'Este personal también está inscrito como alumno. ¿Registrar asistencia como personal o como alumno?',
                    'codigo_huella' => $codigo,
                    'id_usuario' => (int) $u['id_usuario'],
                    'id_alumno' => $idAlumno,
                    'nombre' => trim((string) ($u['nombre'] ?? '')),
                ]);
                exit;
            }
        }
    }

    $identHint = null;
    if ($como === 'alumno') {
        $st = $pdo->prepare(
            "SELECT u.id_alumno,
                    CONCAT(COALESCE(a.apellido_paterno,a.apellido,''),' ',COALESCE(a.nombres,a.nombre,'')) AS nombre_alumno
             FROM usuarios u
             INNER JOIN alumnos a ON a.id_alumno = u.id_alumno
             WHERE u.codigo_huella = ? AND u.rol <> 'alumno' AND a.id_plantel = ? LIMIT 1"
        );
        $st->execute([$codigo, $idPlantel]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['id_alumno'])) {
            hay_json_response(['status' => 'error', 'message' => 'Este usuario no tiene perfil de alumno vinculado']);
            exit;
        }
        $identHint = ['tipo' => 'alumno', 'id_referencia' => (int) $row['id_alumno'], 'nombre' => $row['nombre_alumno'] ?? ''];
    }

    $res = asistencia_procesar_checada_web($pdo, $codigo, $idPlantel, $idOperador, $identHint);
    hay_json_response(array_merge(['status' => ($res['ok'] ?? false) ? 'ok' : 'error'], $res));
    exit;
}

if ($accion === 'identificar_muestra' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sample = trim((string) ($_POST['sample'] ?? ''));
    $como = trim((string) ($_POST['asistencia_como'] ?? ''));
    if ($sample === '') {
        hay_json_response([
            'status' => 'error',
            'ok' => false,
            'tipo' => 'desconocido',
            'message' => 'No se recibió la muestra de huella',
        ]);
        exit;
    }

    $ident = huella_identificar_por_muestra($pdo, $sample, $idPlantel);
    if (empty($ident['ok']) || empty($ident['codigo_huella'])) {
        hay_json_response(array_merge([
            'status' => 'error',
            'ok' => false,
            'tipo' => 'desconocido',
        ], $ident));
        exit;
    }

    // Igual que "registrar": si personal también es alumno, pedir confirmación
    if ($como === '' && !empty($ident['tipo']) && in_array((string) $ident['tipo'], ['personal', 'usuario'], true)) {
        $st = $pdo->prepare(
            "SELECT id_usuario, id_alumno, CONCAT(nombre,' ',apellido) AS nombre
             FROM usuarios
             WHERE id_usuario = ? AND (id_plantel = ? OR id_plantel IS NULL) LIMIT 1"
        );
        $st->execute([(int) ($ident['id_referencia'] ?? 0), $idPlantel]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u && !empty($u['id_alumno'])) {
            $idAlumno = (int) $u['id_alumno'];
            $idGrupo = asistencia_resolver_grupo_activo($pdo, $idAlumno, $idPlantel, date('Y-m-d H:i:s')) ?? 0;
            if ($idGrupo > 0) {
                hay_json_response([
                    'status' => 'choose',
                    'message' => 'Este personal también está inscrito como alumno. ¿Registrar asistencia como personal o como alumno?',
                    'codigo_huella' => (string) $ident['codigo_huella'],
                    'id_usuario' => (int) $u['id_usuario'],
                    'id_alumno' => $idAlumno,
                    'nombre' => trim((string) ($u['nombre'] ?? '')),
                ]);
                exit;
            }
        }
    }

    $identHint = $ident;
    if ($como === 'alumno' && !empty($ident['codigo_huella'])) {
        $st = $pdo->prepare(
            "SELECT u.id_alumno,
                    CONCAT(COALESCE(a.apellido_paterno,a.apellido,''),' ',COALESCE(a.nombres,a.nombre,'')) AS nombre_alumno
             FROM usuarios u
             INNER JOIN alumnos a ON a.id_alumno = u.id_alumno
             WHERE u.codigo_huella = ? AND u.rol <> 'alumno' AND a.id_plantel = ? LIMIT 1"
        );
        $st->execute([(string) $ident['codigo_huella'], $idPlantel]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['id_alumno'])) {
            $identHint = ['tipo' => 'alumno', 'id_referencia' => (int) $row['id_alumno'], 'nombre' => $row['nombre_alumno'] ?? ''];
        }
    }

    $res = asistencia_procesar_checada_web($pdo, (string) $ident['codigo_huella'], $idPlantel, $idOperador, $identHint);
    hay_json_response(array_merge(['status' => ($res['ok'] ?? false) ? 'ok' : 'error'], $res));
    exit;
}

if ($accion === 'poll') {
    $since = (int) ($_GET['since'] ?? $_POST['since'] ?? 0);
    $eventos = asistencia_poll_eventos_checada($pdo, $idPlantel, $since);
    $maxId = $since;
    foreach ($eventos as $ev) {
        if (!empty($ev['id_evento'])) {
            $maxId = max($maxId, (int) $ev['id_evento']);
        }
    }
    hay_json_response([
        'status' => 'ok',
        'eventos' => $eventos,
        'since' => $maxId,
    ]);
    exit;
}

if ($accion === 'ultimo_evento_id') {
    $st = $pdo->prepare('SELECT COALESCE(MAX(id_evento), 0) FROM huella_eventos WHERE id_plantel = ?');
    $st->execute([$idPlantel]);
    hay_json_response(['status' => 'ok', 'since' => (int) $st->fetchColumn()]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
