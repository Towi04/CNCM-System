<?php
/**
 * Descargas CSV del extractor local (XAMPP).
 */
require_once __DIR__ . '/lib.php';

try {
    $pdo = migcsv_pdo();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error de conexión: ' . $e->getMessage();
    exit;
}

$tipo = strtolower(trim((string) ($_GET['tipo'] ?? '')));
$idSucursal = (int) ($_GET['id_sucursal'] ?? 0);
$filtroPath = __DIR__ . '/grupos_filtro.txt';
$filtro = migcsv_leer_filtro_grupos($filtroPath);
$idsGrupos = migcsv_resolver_ids_grupos($pdo, $filtro);

// Si hay sucursal y no hay filtro, no exportar todos los grupos del mundo: exigir filtro
if (in_array($tipo, ['grupos', 'alumnos', 'pagos'], true) && $idsGrupos === []) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Defina grupos activos en grupos_filtro.txt (un id o clave por línea) antes de exportar {$tipo}.";
    exit;
}

if ($idSucursal > 0 && $idsGrupos !== []) {
    $ph = implode(',', array_fill(0, count($idsGrupos), '?'));
    $st = $pdo->prepare("SELECT id FROM grupos WHERE id IN ($ph) AND id_sucursal = ?");
    $st->execute(array_merge($idsGrupos, [$idSucursal]));
    $idsGrupos = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

switch ($tipo) {
    case 'diagnostico':
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(migcsv_diagnostico($pdo), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;

    case 'personal':
        $rows = migcsv_export_personal($pdo, $idSucursal > 0 ? $idSucursal : null);
        migcsv_csv_enviar('01_personal.csv', [
            'id_usuario_legado', 'nombres', 'apellido_paterno', 'apellido_materno',
            'email_legado', 'rol_legado', 'rol', 'plantel_slug', 'username', 'notas',
        ], $rows);

    case 'aulas':
        $slug = '';
        if ($idSucursal > 0) {
            $st = $pdo->prepare('SELECT nombre FROM sucursales WHERE id = ?');
            $st->execute([$idSucursal]);
            $slug = migcsv_slug_sucursal((string) $st->fetchColumn());
        }
        migcsv_csv_enviar('02_aulas.csv', [
            'plantel_slug', 'codigo', 'nombre', 'piso', 'capacidad', 'notas',
        ], migcsv_plantilla_aulas($slug !== '' ? $slug : null));

    case 'grupos':
        $rows = migcsv_export_grupos($pdo, $idsGrupos);
        migcsv_csv_enviar('03_grupos_activos.csv', [
            'id_grupo_legado', 'clave_legado', 'id_sucursal_legado', 'sucursal', 'plantel_slug',
            'especialidad_legado', 'clave_especialidad', 'infantil',
            'fecha_inicio_legado', 'fecha_inicio_estimada', 'clave_fase_estimada',
            'horario_legado', 'dias_clase', 'hora_inicio', 'hora_fin',
            'id_profesor_legado', 'profesor_sugerido', 'profesores_grupo', 'profesor_username',
            'aula_codigo', 'status_legado', 'max_alumnos', 'alumnos_activos_count',
        ], $rows);

    case 'alumnos':
        $rows = migcsv_export_alumnos($pdo, $idsGrupos);
        migcsv_csv_enviar('04_alumnos.csv', [
            'id_alumno_legado', 'numero_control', 'nombres', 'apellido_paterno', 'apellido_materno',
            'email', 'telefono', 'id_sucursal_legado', 'plantel_slug', 'id_grupo_legado',
            'fecha_inicio', 'forma_pago', 'id_asesor_legado', 'asesor_nombre', 'status_legado',
        ], $rows);

    case 'pagos':
        $rows = migcsv_export_pagos($pdo, $idsGrupos);
        migcsv_csv_enviar('05_pagos.csv', [
            'id_pago_legado', 'numero_control', 'id_alumno_legado', 'fecha', 'monto',
            'forma_pago', 'tipo', 'concepto', 'folio',
        ], $rows);

    default:
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'tipo inválido. Use: diagnostico|personal|aulas|grupos|alumnos|pagos';
        exit;
}
