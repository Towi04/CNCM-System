<?php
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    hay_json_response(['status' => 'error', 'message' => 'Sesión expirada'], 401);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$idUser = (int) $_SESSION['user_id'];

if ($action === 'list_mine') {
    if (!expediente_documental_puede_ver_mi_expediente()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $tipo = trim((string) ($_GET['tipo_entidad'] ?? ''));
    $idEnt = (int) ($_GET['id_entidad'] ?? 0);
    $entidades = expediente_documental_entidades_usuario($pdo, $idUser);
    if ($tipo !== '' && $idEnt > 0) {
        $entidades = array_values(array_filter(
            $entidades,
            static fn(array $e): bool => $e['tipo'] === $tipo && (int) $e['id'] === $idEnt
        ));
    }
    $data = [];
    foreach ($entidades as $e) {
        $data[] = [
            'entidad' => $e,
            'items' => expediente_documental_listar_con_entregas(
                $pdo,
                (string) $e['tipo'],
                (int) $e['id'],
                (string) ($e['rol'] ?? '')
            ),
        ];
    }
    hay_json_response(['status' => 'ok', 'entidades' => $entidades, 'expedientes' => $data]);
    exit;
}

if ($action === 'upload') {
    $idReq = (int) ($_POST['id_requisito'] ?? 0);
    $tipo = trim((string) ($_POST['tipo_entidad'] ?? ''));
    $idEnt = (int) ($_POST['id_entidad'] ?? 0);
    $file = $_FILES['archivo'] ?? [];
    $res = expediente_documental_subir($pdo, $idReq, $tipo, $idEnt, $file, $idUser, (int) ($_POST['id_hay_area'] ?? 0));
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'id_entrega' => (int) ($res['id_entrega'] ?? 0),
        'seccion' => 'mi_expediente_documentos',
    ]);
    exit;
}

if ($action === 'evaluar') {
    if (!expediente_documental_puede_evaluar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $puntaje = ($_POST['puntaje'] ?? '') !== '' ? (float) $_POST['puntaje'] : null;
    $res = expediente_documental_evaluar(
        $pdo,
        (int) ($_POST['id_entrega'] ?? 0),
        trim((string) ($_POST['estado'] ?? '')),
        $puntaje,
        trim((string) ($_POST['comentario'] ?? '')),
        $idUser
    );
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'seccion' => 'expediente_consulta',
    ]);
    exit;
}

if ($action === 'sync_moodle') {
    $idEntrega = (int) ($_POST['id_entrega'] ?? $_GET['id_entrega'] ?? 0);
    $puede = expediente_documental_puede_evaluar();
    if (!$puede) {
        $st = $pdo->prepare('SELECT tipo_entidad, id_entidad FROM expediente_entrega WHERE id_entrega = ? LIMIT 1');
        $st->execute([$idEntrega]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $puede = $row && expediente_documental_puede_gestionar_entidad(
            $pdo,
            (string) $row['tipo_entidad'],
            (int) $row['id_entidad'],
            $idUser
        );
    }
    if (!$puede) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $res = expediente_documental_sync_moodle($pdo, $idEntrega);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'puntaje' => $res['puntaje'] ?? null,
        'estado' => $res['estado'] ?? null,
    ]);
    exit;
}

if ($action === 'inscribir_moodle') {
    if (!expediente_documental_puede_evaluar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $res = expediente_documental_inscribir_examen_moodle($pdo, (int) ($_POST['id_entrega'] ?? 0));
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'save_requisito') {
    $res = expediente_documental_guardar_requisito(
        $pdo,
        $_POST,
        (int) ($_POST['id_requisito'] ?? 0)
    );
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
        'id_requisito' => (int) ($res['id_requisito'] ?? 0),
        'seccion' => 'expediente_requisitos',
    ]);
    exit;
}

if ($action === 'list_requisitos') {
    if (!expediente_documental_puede_configurar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    hay_json_response(['status' => 'ok', 'rows' => expediente_documental_listar_requisitos_admin($pdo)]);
    exit;
}

if ($action === 'consulta') {
    if (!expediente_documental_puede_consultar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    $tipo = trim((string) ($_GET['tipo_entidad'] ?? ''));
    $idEnt = (int) ($_GET['id_entidad'] ?? 0);
    if ($tipo === '' || $idEnt <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Indique persona']);
        exit;
    }
    $rol = '';
    if ($tipo === 'usuario') {
        $st = $pdo->prepare('SELECT rol FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $st->execute([$idEnt]);
        $rol = (string) ($st->fetchColumn() ?: '');
    }
    hay_json_response([
        'status' => 'ok',
        'items' => expediente_documental_listar_con_entregas($pdo, $tipo, $idEnt, $rol),
    ]);
    exit;
}

if ($action === 'buscar') {
    if (!expediente_documental_puede_consultar()) {
        hay_json_response(['status' => 'error', 'message' => 'Sin permiso'], 403);
        exit;
    }
    hay_json_response([
        'status' => 'ok',
        'results' => expediente_documental_buscar_entidades($pdo, trim((string) ($_GET['q'] ?? ''))),
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida'], 400);
