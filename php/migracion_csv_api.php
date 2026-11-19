<?php
define('HAY_SKIP_SCHEMA_BOOTSTRAP', true);
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('migracion_csv_puede') || !migracion_csv_puede()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

migracion_csv_ensure_schema($pdo);
$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
$idPlantel = (int) ($_POST['id_plantel'] ?? plantel_id_activo());

if ($action === 'export_legado') {
    // Descarga CSV desde la BD legado en el servidor (LEGACY_DB_*), sin XAMPP.
    if (function_exists('set_time_limit')) {
        @set_time_limit(120);
    }
    $lib = dirname(__DIR__) . '/tools/migracion_csv/lib.php';
    if (!is_readable($lib)) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo 'Falta tools/migracion_csv/lib.php en el servidor.';
        exit;
    }
    require_once $lib;
    $conn = function_exists('legacy_import_legacy_connection')
        ? legacy_import_legacy_connection()
        : ['ok' => false, 'pdo' => null, 'error' => 'legacy_import no disponible'];
    if (empty($conn['ok']) || !($conn['pdo'] instanceof PDO)) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(400);
        echo 'Sin conexión al legado. Configure LEGACY_DB_HOST/NAME/USER/PASS en config.local.php. '
            . (string) ($conn['error'] ?? '');
        exit;
    }
    /** @var PDO $pdoLeg */
    $pdoLeg = $conn['pdo'];
    $tipoExp = strtolower(trim((string) ($_GET['tipo'] ?? $_POST['tipo'] ?? '')));
    // staff_emails no sale del legado; usar personal o plantilla de ejemplo
    if ($tipoExp === 'staff_emails') {
        $tipoExp = 'personal';
    }
    $idSucursal = (int) ($_GET['id_sucursal'] ?? $_POST['id_sucursal'] ?? 0);
    $filtroRaw = (string) ($_GET['filtro'] ?? $_POST['filtro'] ?? '');
    $filtro = [];
    foreach (preg_split('/[\r\n,;]+/', $filtroRaw) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $filtro[] = $line;
    }
    $filtroAlRaw = (string) ($_GET['filtro_alumnos'] ?? $_POST['filtro_alumnos'] ?? '');
    $filtroAlumnos = [];
    foreach (preg_split('/\R/', $filtroAlRaw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        // Encabezados CSV: ignorar
        $low = strtolower($line);
        if ($low === 'numero_control' || $low === 'id_alumno_legado'
            || str_starts_with($low, 'numero_control,') || str_starts_with($low, 'id_alumno_legado,')) {
            continue;
        }
        // Valor suelto o fila CSV: preferir columna numero_control si hay encabezado implícito
        if (str_contains($line, ',')) {
            $parts = str_getcsv($line);
            $first = trim((string) ($parts[0] ?? ''));
            if ($first !== '' && strcasecmp($first, 'id_alumno_legado') !== 0
                && strcasecmp($first, 'numero_control') !== 0) {
                $filtroAlumnos[] = $first;
            }
            continue;
        }
        $filtroAlumnos[] = $line;
    }
    $idsGrupos = migcsv_resolver_ids_grupos($pdoLeg, $filtro);
    $idsAlumnosFiltro = migcsv_resolver_ids_alumnos($pdoLeg, $filtroAlumnos, true);
    if (in_array($tipoExp, ['grupos', 'alumnos'], true) && $idsGrupos === []) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(400);
        echo 'Indique ids o claves de grupos activos (uno por línea) para exportar ' . $tipoExp . '.';
        exit;
    }
    if ($tipoExp === 'pagos' && $idsAlumnosFiltro === [] && $idsGrupos === []) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(400);
        echo 'Para pagos indique numero_control (uno por línea, o suba el CSV de alumnos) '
            . 'o, en su defecto, claves de grupos. Prefijo id:123 solo si necesita el id interno legado.';
        exit;
    }
    if ($idSucursal > 0 && $idsGrupos !== []) {
        $ph = implode(',', array_fill(0, count($idsGrupos), '?'));
        $st = $pdoLeg->prepare("SELECT id FROM grupos WHERE id IN ($ph) AND id_sucursal = ?");
        $st->execute(array_merge($idsGrupos, [$idSucursal]));
        $idsGrupos = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    try {
        switch ($tipoExp) {
            case 'diagnostico':
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(migcsv_diagnostico($pdoLeg), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            case 'personal':
                $rows = migcsv_export_personal($pdoLeg, $idSucursal > 0 ? $idSucursal : null);
                migcsv_csv_enviar('01_personal.csv', [
                    'id_usuario_legado', 'nombres', 'apellido_paterno', 'apellido_materno',
                    'email_legado', 'rol_legado', 'rol', 'plantel_slug', 'username', 'notas',
                ], $rows);
                // unreachable
            case 'aulas':
                $slug = '';
                if ($idSucursal > 0) {
                    $st = $pdoLeg->prepare('SELECT nombre FROM sucursales WHERE id = ?');
                    $st->execute([$idSucursal]);
                    $slug = migcsv_slug_sucursal((string) $st->fetchColumn());
                }
                migcsv_csv_enviar('02_aulas.csv', [
                    'plantel_slug', 'codigo', 'nombre', 'piso', 'capacidad', 'notas',
                ], migcsv_plantilla_aulas($slug !== '' ? $slug : null));
            case 'grupos':
                $rows = migcsv_export_grupos($pdoLeg, $idsGrupos);
                migcsv_csv_enviar('03_grupos_activos.csv', [
                    'clave_legado', 'plantel_slug', 'sucursal', 'id_sucursal_legado',
                    'especialidad_legado', 'clave_especialidad', 'infantil',
                    'fecha_inicio_legado', 'fecha_inicio_estimada', 'clave_fase_estimada',
                    'horario_legado', 'dias_clase', 'hora_inicio', 'hora_fin',
                    'profesor_sugerido', 'profesores_grupo', 'profesor_username', 'id_profesor_legado',
                    'aula_codigo', 'status_legado', 'max_alumnos', 'alumnos_activos_count',
                    'id_grupo_legado',
                ], $rows);
            case 'alumnos':
                $rows = migcsv_export_alumnos($pdoLeg, $idsGrupos);
                migcsv_csv_enviar('04_alumnos.csv', [
                    'id_alumno_legado', 'numero_control', 'nombres', 'apellido_paterno', 'apellido_materno',
                    'email', 'telefono', 'plantel_slug', 'id_sucursal_legado',
                    'clave_legado', 'fecha_inicio', 'forma_pago',
                    'id_asesor_legado', 'asesor_nombre', 'status_legado',
                    'id_grupo_legado',
                ], $rows);
            case 'pagos':
                $rows = $idsAlumnosFiltro !== []
                    ? migcsv_export_pagos_por_alumnos($pdoLeg, $idsAlumnosFiltro)
                    : migcsv_export_pagos($pdoLeg, $idsGrupos);
                migcsv_csv_enviar('05_pagos.csv', [
                    'id_pago_legado', 'numero_control', 'id_alumno_legado', 'fecha', 'monto',
                    'forma_pago', 'tipo', 'concepto', 'folio',
                ], $rows);
            default:
                header('Content-Type: text/plain; charset=utf-8');
                http_response_code(400);
                echo 'Tipo no válido para exportar del legado. Use: personal|aulas|grupos|alumnos|pagos';
                exit;
        }
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo 'Error al exportar del legado: ' . $e->getMessage();
        exit;
    }
}

if ($action === 'staff_template' || $action === 'csv_ejemplo') {
    $tipoEj = strtolower(trim((string) ($_GET['tipo'] ?? $_POST['tipo'] ?? '')));
    if ($action === 'staff_template' && $tipoEj === '') {
        $tipoEj = 'staff_emails';
    }
    $slugEj = null;
    if (function_exists('plantel_list')) {
        $planteles = plantel_list($pdo, true);
        $slugEj = (string) ($planteles[0]['slug'] ?? 'guerrero');
        if ($idPlantel > 0) {
            foreach ($planteles as $p) {
                if ((int) ($p['id_plantel'] ?? 0) === $idPlantel) {
                    $slugEj = (string) ($p['slug'] ?? $slugEj);
                    break;
                }
            }
        }
    }
    $ej = migracion_csv_ejemplo($pdo, $tipoEj !== '' ? $tipoEj : 'staff_emails', $slugEj);
    if ($ej === null) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(400);
        echo 'Tipo no válido. Use: staff_emails|personal|aulas|grupos|alumnos|pagos';
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $ej['filename'] . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, $ej['headers']);
    foreach ($ej['rows'] as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    if ($action === 'estado') {
        $maps = [];
        foreach (['usuario', 'grupo', 'grupo_comp', 'alumno', 'pago'] as $ent) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM hay_migracion_csv_map WHERE entidad = ?');
            $st->execute([$ent]);
            $maps[$ent] = (int) $st->fetchColumn();
        }
        // Varias claves de mapa por alumno (nc:, NC plano, id legado) ≠ fichas duplicadas
        $st = $pdo->prepare(
            'SELECT COUNT(DISTINCT id_hay) FROM hay_migracion_csv_map WHERE entidad = ?'
        );
        $st->execute(['alumno']);
        $maps['alumno_unicos'] = (int) $st->fetchColumn();
        $logs = $pdo->query(
            'SELECT tipo, nivel, mensaje, creado_en FROM hay_migracion_csv_log
             ORDER BY id DESC LIMIT 15'
        )->fetchAll(PDO::FETCH_ASSOC);
        $legado = ['ok' => false, 'db' => '', 'error' => '', 'sucursales' => []];
        if (function_exists('legacy_import_legacy_connection')) {
            $conn = legacy_import_legacy_connection();
            $legado['ok'] = !empty($conn['ok']);
            $legado['error'] = (string) ($conn['error'] ?? '');
            $legado['db'] = defined('LEGACY_DB_NAME') ? (string) LEGACY_DB_NAME : '';
            if (!empty($conn['ok']) && $conn['pdo'] instanceof PDO) {
                try {
                    $legado['sucursales'] = $conn['pdo']->query(
                        'SELECT id, nombre FROM sucursales ORDER BY id'
                    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Throwable $e) {
                    $legado['sucursales'] = [];
                }
            }
        } else {
            $legado['error'] = 'legacy_import no disponible';
        }
        echo json_encode([
            'status' => 'ok',
            'mapas' => $maps,
            'logs' => $logs,
            'id_plantel' => $idPlantel,
            'legado' => $legado,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'preview') {
        if (empty($_FILES['csv']['tmp_name'])) {
            echo json_encode(['status' => 'error', 'message' => 'Adjunte un CSV'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $tipoPreview = strtolower(trim((string) ($_POST['tipo'] ?? '')));
        if ($tipoPreview === 'staff_emails') {
            $offset = (int) ($_POST['offset'] ?? 0);
            $limit = (int) ($_POST['limit'] ?? 50);
            $res = migracion_csv_preview_staff_emails(
                $pdo,
                $_FILES['csv']['tmp_name'],
                $idPlantel > 0 ? $idPlantel : null,
                $offset,
                $limit
            );
            echo json_encode([
                'status' => !empty($res['ok']) ? 'ok' : 'error',
                'message' => $res['message'] ?? '',
                'headers' => $res['headers'] ?? [],
                'total_filas' => $res['total_filas'] ?? 0,
                'offset' => $res['offset'] ?? $offset,
                'limit' => $res['limit'] ?? $limit,
                'muestra' => $res['muestra'] ?? [],
                'errores' => $res['errores'] ?? [],
                'roles' => $res['roles'] ?? migracion_csv_staff_roles_opciones(),
                'role_labels' => $res['role_labels'] ?? [],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($tipoPreview === 'grupos') {
            $offset = (int) ($_POST['offset'] ?? 0);
            $limit = (int) ($_POST['limit'] ?? 50);
            $res = migracion_csv_preview_grupos(
                $pdo,
                $_FILES['csv']['tmp_name'],
                $idPlantel > 0 ? $idPlantel : null,
                $offset,
                $limit
            );
            echo json_encode([
                'status' => !empty($res['ok']) ? 'ok' : 'error',
                'message' => $res['message'] ?? '',
                'headers' => $res['headers'] ?? [],
                'total_filas' => $res['total_filas'] ?? 0,
                'offset' => $res['offset'] ?? $offset,
                'limit' => $res['limit'] ?? $limit,
                'muestra' => $res['muestra'] ?? [],
                'errores' => $res['errores'] ?? [],
                'especialidades' => $res['especialidades'] ?? [],
                'profesores' => $res['profesores'] ?? [],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($tipoPreview === 'alumnos') {
            $offset = (int) ($_POST['offset'] ?? 0);
            $limit = (int) ($_POST['limit'] ?? 50);
            $soloAlertas = !empty($_POST['solo_alertas']) && (string) $_POST['solo_alertas'] !== '0';
            $res = migracion_csv_preview_alumnos(
                $pdo,
                $_FILES['csv']['tmp_name'],
                $idPlantel > 0 ? $idPlantel : null,
                $offset,
                $limit,
                $soloAlertas
            );
            echo json_encode([
                'status' => !empty($res['ok']) ? 'ok' : 'error',
                'message' => $res['message'] ?? '',
                'headers' => $res['headers'] ?? [],
                'total_filas' => $res['total_filas'] ?? 0,
                'total_csv' => $res['total_csv'] ?? 0,
                'con_alerta' => $res['con_alerta'] ?? 0,
                'offset' => $res['offset'] ?? $offset,
                'limit' => $res['limit'] ?? $limit,
                'solo_alertas' => !empty($res['solo_alertas']),
                'muestra' => $res['muestra'] ?? [],
                'errores' => $res['errores'] ?? [],
                'grupos_faltantes' => $res['grupos_faltantes'] ?? [],
                'alertas_resumen' => $res['alertas_resumen'] ?? [],
            ], JSON_UNESCAPED_UNICODE);
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
            'offset' => 0,
            'limit' => count($sample),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'import') {
        // Staff+Google/Moodle por fila puede superar el límite por defecto (30–60s).
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ignore_user_abort(true);
        $tipo = strtolower(trim((string) ($_POST['tipo'] ?? '')));
        if (empty($_FILES['csv']['tmp_name'])) {
            echo json_encode(['status' => 'error', 'message' => 'Adjunte un CSV'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($tipo === 'staff_emails') {
            $overrides = [];
            $rawOv = trim((string) ($_POST['overrides'] ?? ''));
            if ($rawOv !== '') {
                $decoded = json_decode($rawOv, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $em => $vals) {
                        $emKey = strtolower(trim((string) $em));
                        if ($emKey === '' || !is_array($vals)) {
                            continue;
                        }
                        $overrides[$emKey] = [
                            'rol' => trim((string) ($vals['rol'] ?? '')),
                            'nombres' => trim((string) ($vals['nombres'] ?? '')),
                            'apellido' => trim((string) ($vals['apellido'] ?? '')),
                        ];
                    }
                }
            }
            $res = migracion_csv_importar_staff_emails(
                $pdo,
                $_FILES['csv']['tmp_name'],
                $idPlantel > 0 ? $idPlantel : null,
                true,
                $overrides
            );
        } elseif ($tipo === 'grupos') {
            $overrides = [];
            $rawOv = trim((string) ($_POST['overrides'] ?? ''));
            if ($rawOv !== '') {
                $decoded = json_decode($rawOv, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $idLeg => $vals) {
                        $key = trim((string) $idLeg);
                        if ($key === '' || !is_array($vals)) {
                            continue;
                        }
                        $idProf = (int) ($vals['id_profesor'] ?? 0);
                        // Clave por línea: "123@L5" o "L5" (parejas kids / sin id legado)
                        if (preg_match('/^(.+)@L(\d+)$/', $key, $mKey)) {
                            $idBase = $mKey[1];
                            $lineN = (int) $mKey[2];
                            if (!isset($overrides[$idBase]) || !is_array($overrides[$idBase])) {
                                $overrides[$idBase] = [];
                            }
                            if (!isset($overrides[$idBase]['profesores_por_linea']) || !is_array($overrides[$idBase]['profesores_por_linea'])) {
                                $overrides[$idBase]['profesores_por_linea'] = [];
                            }
                            if ($idProf > 0) {
                                $overrides[$idBase]['profesores_por_linea'][$lineN] = $idProf;
                            }
                            $esp = strtoupper(trim((string) ($vals['clave_especialidad'] ?? '')));
                            if ($esp !== '') {
                                $overrides[$idBase]['clave_especialidad'] = $esp;
                            }
                            continue;
                        }
                        if (preg_match('/^L(\d+)$/', $key, $mLine)) {
                            if ($idProf > 0) {
                                $overrides[$key] = ['id_profesor' => $idProf];
                            }
                            continue;
                        }
                        $entry = [
                            'clave_especialidad' => strtoupper(trim((string) ($vals['clave_especialidad'] ?? ''))),
                        ];
                        if ($idProf > 0) {
                            $entry['id_profesor'] = $idProf;
                        }
                        if (isset($vals['profesores_por_linea']) && is_array($vals['profesores_por_linea'])) {
                            $porLinea = [];
                            foreach ($vals['profesores_por_linea'] as $ln => $pid) {
                                $pid = (int) $pid;
                                if ($pid > 0) {
                                    $porLinea[(int) $ln] = $pid;
                                }
                            }
                            if ($porLinea !== []) {
                                $entry['profesores_por_linea'] = $porLinea;
                            }
                        }
                        $overrides[$key] = $entry;
                    }
                }
            }
            $parsed = migracion_csv_leer_archivo($_FILES['csv']['tmp_name']);
            if (!empty($parsed['error'])) {
                echo json_encode(['status' => 'error', 'message' => $parsed['error']], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $res = migracion_csv_importar_grupos(
                $pdo,
                $parsed['rows'],
                $idPlantel > 0 ? $idPlantel : null,
                $overrides
            );
        } elseif ($tipo === 'alumnos') {
            // Por defecto NO provisiona Google/Moodle: confirmar datos y luego «Re-provisionar mapeados»
            $provAl = !empty($_POST['provisionar']) && (string) $_POST['provisionar'] !== '0';
            $parsedAl = migracion_csv_leer_archivo($_FILES['csv']['tmp_name']);
            if (!empty($parsedAl['error'])) {
                echo json_encode(['status' => 'error', 'message' => $parsedAl['error']], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $res = migracion_csv_importar_alumnos(
                $pdo,
                $parsedAl['rows'],
                $idPlantel > 0 ? $idPlantel : null,
                $provAl
            );
        } else {
            $res = migracion_csv_importar_tipo($pdo, $tipo, $_FILES['csv']['tmp_name'], $idPlantel > 0 ? $idPlantel : null);
        }
        echo json_encode([
            'status' => !empty($res['ok']) ? 'ok' : 'error',
            'message' => $res['message'] ?? '',
            'creados' => $res['creados'] ?? null,
            'actualizados' => $res['actualizados'] ?? null,
            'google_creados' => $res['google_creados'] ?? null,
            'moodle_ok' => $res['moodle_ok'] ?? null,
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

    if ($action === 'import_pagos_legado') {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
        $filtroAlRaw = (string) ($_POST['filtro_alumnos'] ?? '');
        $usarMapa = !empty($_POST['usar_mapa']) && (string) $_POST['usar_mapa'] !== '0';
        $ncs = migracion_csv_parse_filtro_numeros_control($filtroAlRaw);
        if ($ncs === [] && $usarMapa) {
            $ncs = migracion_csv_ncs_desde_mapa_alumnos($pdo, $idPlantel > 0 ? $idPlantel : null);
        }
        if ($ncs === []) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Pegue numero_control (o suba el CSV de alumnos) o marque «Usar alumnos ya mapeados».',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $offset = max(0, (int) ($_POST['offset'] ?? 0));
        $lote = (int) ($_POST['lote'] ?? 40);
        $res = migracion_csv_importar_pagos_desde_legado(
            $pdo,
            $ncs,
            $idPlantel > 0 ? $idPlantel : null,
            $offset,
            $lote
        );
        echo json_encode([
            'status' => !empty($res['ok']) ? 'ok' : 'error',
            'message' => $res['message'] ?? '',
            'done' => !empty($res['done']),
            'offset' => $res['offset'] ?? $offset,
            'next_offset' => $res['next_offset'] ?? $offset,
            'total_nc' => $res['total_nc'] ?? count($ncs),
            'lote_nc' => $res['lote_nc'] ?? 0,
            'pagos_leidos' => $res['pagos_leidos'] ?? 0,
            'creados' => $res['creados'] ?? 0,
            'omitidos' => $res['omitidos'] ?? 0,
            'nc_sin_legado' => $res['nc_sin_legado'] ?? [],
            'errores' => $res['errores'] ?? [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'revertir_grupos') {
        $confirm = strtoupper(trim((string) ($_POST['confirm'] ?? '')));
        $todos = !empty($_POST['todos']) && (string) $_POST['todos'] !== '0';
        if ($confirm === 'TODOS') {
            $todos = true;
        }
        if ($confirm !== 'REVERTIR' && $confirm !== 'TODOS') {
            echo json_encode([
                'status' => 'error',
                'message' => 'Para confirmar escriba REVERTIR (mapa) o TODOS (vaciar plantel)',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $borrarAlumnos = !empty($_POST['borrar_alumnos']) && (string) $_POST['borrar_alumnos'] !== '0';
        $res = migracion_csv_revertir_grupos(
            $pdo,
            $idPlantel > 0 ? $idPlantel : null,
            $borrarAlumnos,
            $todos
        );
        echo json_encode([
            'status' => !empty($res['ok']) ? 'ok' : 'error',
            'message' => $res['message'] ?? '',
            'grupos_borrados' => $res['grupos_borrados'] ?? 0,
            'mapas_borrados' => $res['mapas_borrados'] ?? 0,
            'alumnos_desvinculados' => $res['alumnos_desvinculados'] ?? 0,
            'grupos_restantes_plantel' => $res['grupos_restantes_plantel'] ?? null,
            'errores' => $res['errores'] ?? [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
