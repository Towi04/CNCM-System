<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !calendario_puede_ver_consulta($pdo)) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso para ver el calendario']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$idUsuario = (int) $_SESSION['user_id'];

if ($action === 'capas') {
    hay_json_response([
        'status' => 'ok',
        'capas' => calendario_capas_consulta($pdo, $idUsuario),
        'solo_lectura' => (function_exists('rbac_cap') && rbac_cap('menu_calendario_consulta') && !calendario_puede_editar_administrativo())
            || (rbac_rol_efectivo() === 'profesor' && empty(calendario_modelos_editables_usuario())),
    ]);
    exit;
}

if ($action === 'mes_combinado') {
    $anio = (int) ($_GET['anio'] ?? $_POST['anio'] ?? date('Y'));
    $mes = (int) ($_GET['mes'] ?? $_POST['mes'] ?? date('n'));
    $capasRaw = $_GET['capas'] ?? $_POST['capas'] ?? '';
    if (is_array($capasRaw)) {
        $capas = $capasRaw;
    } else {
        $capas = array_filter(array_map('trim', explode(',', (string) $capasRaw)));
    }
    if ($capas === []) {
        $capas = array_map(static fn ($c) => $c['id'], calendario_capas_consulta($pdo, $idUsuario));
    }

    hay_json_response([
        'status' => 'ok',
        'anio' => $anio,
        'mes' => $mes,
        'dias' => calendario_mes_combinado($pdo, $anio, $mes, $capas, plantel_id_activo(), $idUsuario),
        'colores' => calendario_tipos_colores(),
    ]);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
