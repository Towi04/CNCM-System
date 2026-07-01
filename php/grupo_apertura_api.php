<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

require_once __DIR__ . '/grupo_apertura_helper.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$idUsuario = (int) ($_SESSION['user_id'] ?? 0);

if ($action === 'listar') {
    if (!grupo_apertura_puede_gestionar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $lista = grupo_apertura_listar_pendientes($pdo);
    foreach ($lista as &$g) {
        $g['estado_label'] = grupo_apertura_etiqueta_estado((string) ($g['estado_apertura'] ?? 'programado'));
        $g['cumple_minimo'] = grupo_apertura_cumple_minimo($g);
    }
    unset($g);
    hay_json_response(['status' => 'ok', 'grupos' => $lista, 'total' => count($lista)]);
    exit;
}

if ($action === 'autorizar') {
    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);
    $res = grupo_apertura_autorizar($pdo, $idGrupo, $idUsuario);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'seccion' => 'grupo_apertura',
    ]);
    exit;
}

if ($action === 'posponer') {
    $idGrupo = (int) ($_POST['id_grupo'] ?? 0);
    $nuevaFecha = trim((string) ($_POST['nueva_fecha'] ?? ''));
    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    $res = grupo_apertura_posponer($pdo, $idGrupo, $nuevaFecha, $motivo, $idUsuario);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'pagos_remapeados' => $res['pagos_remapeados'] ?? 0,
        'seccion' => 'grupo_apertura',
    ]);
    exit;
}

if ($action === 'detalle') {
    $idGrupo = (int) ($_GET['id_grupo'] ?? $_POST['id_grupo'] ?? 0);
    $g = grupo_apertura_obtener($pdo, $idGrupo);
    if (!$g) {
        hay_json_response(['status' => 'error', 'message' => 'Grupo no encontrado'], 404);
        exit;
    }
    $g['estado_label'] = grupo_apertura_etiqueta_estado((string) ($g['estado_apertura'] ?? 'programado'));
    $g['cumple_minimo'] = grupo_apertura_cumple_minimo($g);
    $log = $pdo->prepare(
        'SELECT * FROM grupo_apertura_log WHERE id_grupo = ? ORDER BY creado_en DESC LIMIT 20'
    );
    $log->execute([$idGrupo]);
    hay_json_response(['status' => 'ok', 'grupo' => $g, 'historial' => $log->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
