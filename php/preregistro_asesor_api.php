<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!preregistro_puede_reasignar_comision()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso']);
    exit;
}

$idPlantel = plantel_scope_id($pdo);
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'opciones') {
    hay_json_response([
        'status' => 'ok',
        'asesores' => preregistro_asesores_comision_opciones($pdo, $idPlantel),
    ]);
    exit;
}

if ($action === 'buscar_entrevistas') {
    $q = trim($_GET['q'] ?? '');
    hay_json_response([
        'status' => 'ok',
        'items' => preregistro_entrevistas_buscar($pdo, $idPlantel, $q),
    ]);
    exit;
}

if ($action === 'detalle') {
    $id = (int) ($_GET['id_preregistro'] ?? 0);
    if ($id <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Id inválido']);
        exit;
    }
    $st = $pdo->prepare(
        'SELECT p.*,
                CONCAT(u.nombre, \' \', u.apellido) AS captura_nombre,
                CONCAT(uc.nombre, \' \', uc.apellido) AS comision_asesor_nombre,
                CONCAT(ue.nombre, \' \', ue.apellido) AS entrevista_asesor_nombre,
                ent.id_entrevista, ent.creado_en AS entrevista_fecha,
                CONCAT(ent.nombres, \' \', ent.apellido_paterno) AS entrevista_nombre
         FROM preregistros p
         INNER JOIN usuarios u ON u.id_usuario = p.id_usuario_registro
         LEFT JOIN asesor_entrevistas ent ON ent.id_entrevista = p.id_entrevista_origen
         LEFT JOIN usuarios uc ON uc.id_usuario = p.id_usuario_asesor
         LEFT JOIN usuarios ue ON ue.id_usuario = ent.id_usuario_asesor
         WHERE p.id_preregistro = ? AND p.id_plantel = ? LIMIT 1'
    );
    $st->execute([$id, $idPlantel]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        hay_json_response(['status' => 'error', 'message' => 'No encontrado']);
        exit;
    }
    $res = preregistro_resolver_comision($row);
    hay_json_response([
        'status' => 'ok',
        'preregistro' => [
            'id_preregistro' => (int) $row['id_preregistro'],
            'nombre' => preregistro_nombre_completo($row),
            'captura_nombre' => $row['captura_nombre'] ?? '',
            'comision_cncm' => !empty($row['comision_cncm']),
            'id_usuario_asesor' => (int) ($row['id_usuario_asesor'] ?? 0) ?: null,
            'comision_label' => preregistro_comision_label_from_row($row),
            'id_entrevista_origen' => (int) ($row['id_entrevista_origen'] ?? 0) ?: null,
            'entrevista' => !empty($row['id_entrevista']) ? [
                'id_entrevista' => (int) $row['id_entrevista'],
                'nombre' => trim($row['entrevista_nombre'] ?? ''),
                'asesor_nombre' => $row['entrevista_asesor_nombre'] ?? '',
                'fecha' => !empty($row['entrevista_fecha']) ? date('d/m/Y', strtotime($row['entrevista_fecha'])) : '',
            ] : null,
            'resolver' => $res,
        ],
    ]);
    exit;
}

if ($action === 'asignar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id_preregistro'] ?? 0);
    if ($id <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'Id inválido']);
        exit;
    }
    $cncm = !empty($_POST['comision_cncm']);
    $idAsesor = (int) ($_POST['id_usuario_asesor'] ?? 0);
    $idEnt = (int) ($_POST['id_entrevista'] ?? 0);
    $res = preregistro_asignar_comision($pdo, $id, $idPlantel, [
        'comision_cncm' => $cncm,
        'id_usuario_asesor' => $idAsesor > 0 ? $idAsesor : null,
        'id_entrevista' => $idEnt,
        'motivo' => trim((string) ($_POST['motivo'] ?? '')),
    ]);
    hay_json_response([
        'status' => $res['ok'] ? 'ok' : 'error',
        'message' => $res['message'],
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
