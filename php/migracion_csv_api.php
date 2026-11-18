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

    echo json_encode(['status' => 'error', 'message' => 'Acción no válida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
