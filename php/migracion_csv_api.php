<?php
define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('migracion_csv_puede') || !migracion_csv_puede()) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

migracion_csv_ensure_schema($pdo);
$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
$idPlantel = (int) ($_POST['id_plantel'] ?? plantel_id_activo());

try {
    if ($action === 'estado') {
        $maps = [];
        foreach (['usuario', 'grupo', 'grupo_comp', 'alumno', 'pago'] as $ent) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM hay_migracion_csv_map WHERE entidad = ?');
            $st->execute([$ent]);
            $maps[$ent] = (int) $st->fetchColumn();
        }
        $logs = $pdo->query(
            'SELECT tipo, nivel, mensaje, creado_en FROM hay_migracion_csv_log
             ORDER BY id DESC LIMIT 15'
        )->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'status' => 'ok',
            'mapas' => $maps,
            'logs' => $logs,
            'id_plantel' => $idPlantel,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'preview') {
        if (empty($_FILES['csv']['tmp_name'])) {
            echo json_encode(['status' => 'error', 'message' => 'Adjunte un CSV'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $parsed = migracion_csv_leer_archivo($_FILES['csv']['tmp_name']);
        if (!empty($parsed['error'])) {
            echo json_encode(['status' => 'error', 'message' => $parsed['error']], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $sample = array_slice($parsed['rows'], 0, 8);
        echo json_encode([
            'status' => 'ok',
            'headers' => $parsed['headers'],
            'total_filas' => count($parsed['rows']),
            'muestra' => $sample,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'import') {
        $tipo = strtolower(trim((string) ($_POST['tipo'] ?? '')));
        if (empty($_FILES['csv']['tmp_name'])) {
            echo json_encode(['status' => 'error', 'message' => 'Adjunte un CSV'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $res = migracion_csv_importar_tipo($pdo, $tipo, $_FILES['csv']['tmp_name'], $idPlantel > 0 ? $idPlantel : null);
        echo json_encode([
            'status' => !empty($res['ok']) ? 'ok' : 'error',
            'message' => $res['message'] ?? '',
            'creados' => $res['creados'] ?? null,
            'actualizados' => $res['actualizados'] ?? null,
            'vinculados' => $res['vinculados'] ?? null,
            'omitidos' => $res['omitidos'] ?? null,
            'errores' => $res['errores'] ?? [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'provision') {
        $limite = (int) ($_POST['limite'] ?? 200);
        $res = migracion_csv_provisionar_cuentas($pdo, $idPlantel > 0 ? $idPlantel : null, $limite);
        echo json_encode([
            'status' => !empty($res['ok']) ? 'ok' : 'error',
            'message' => $res['message'] ?? '',
            'alumnos_ok' => $res['alumnos_ok'] ?? 0,
            'alumnos_fail' => $res['alumnos_fail'] ?? 0,
            'staff_ok' => $res['staff_ok'] ?? 0,
            'staff_fail' => $res['staff_fail'] ?? 0,
            'errores' => $res['errores'] ?? [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
