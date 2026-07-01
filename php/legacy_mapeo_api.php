<?php
require dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/legacy_import_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rolReal = function_exists('rbac_rol_real') ? rbac_rol_real() : ($_SESSION['rol'] ?? '');
if (!in_array($rolReal, ['supervisor', 'gerente'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Sin permiso'], JSON_UNESCAPED_UNICODE);
    exit;
}

$leg = legacy_import_pdo_legacy();
if (!$leg) {
    echo json_encode(['status' => 'error', 'message' => 'Configure LEGACY_DB_* en config.local.php'], JSON_UNESCAPED_UNICODE);
    exit;
}

legacy_import_ensure_schema($pdo);
$action = trim($_GET['action'] ?? $_POST['action'] ?? 'list');

try {
    if ($action === 'list_planteles') {
        $legacy = $leg->query('SELECT id, nombre FROM sucursales ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
        $hayRows = $pdo->query('SELECT id_plantel, slug, nombre FROM planteles WHERE activo = 1 ORDER BY orden, nombre')->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($legacy as $row) {
            $lid = (int) $row['id'];
            $eq = legacy_equiv_get($pdo, 'plantel', $lid);
            $mapped = legacy_map_get($pdo, 'plantel', $lid);
            $sugerido = legacy_import_plantel_match($pdo, trim((string) $row['nombre']));
            $out[] = [
                'id_legacy' => $lid,
                'nombre_legacy' => $row['nombre'],
                'id_hay_map' => $mapped,
                'id_hay_equiv' => $eq ? (int) ($eq['id_hay'] ?? 0) : null,
                'modo' => $eq['modo'] ?? '',
                'id_hay_sugerido' => $sugerido,
            ];
        }
        echo json_encode(['status' => 'ok', 'legacy' => $out, 'hay' => $hayRows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'list_especialidades' || $action === 'list_especialidades_grupos') {
        $cols = legacy_import_select_cols($leg, 'especialidades', ['id', 'nombre', 'descripcion']);
        $legacy = $leg->query("SELECT {$cols} FROM especialidades ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
        $hayRows = $pdo->query(
            'SELECT id_especialidad, clave, nombre FROM especialidades WHERE visible = 1 ORDER BY orden, nombre'
        )->fetchAll(PDO::FETCH_ASSOC);
        $hasGrpEspId = legacy_import_column_exists($leg, 'grupos', 'id_especialidad');
        $countByEsp = [];
        if ($hasGrpEspId) {
            foreach ($leg->query(
                'SELECT id_especialidad, COUNT(*) AS n FROM grupos
                 WHERE id_especialidad IS NOT NULL GROUP BY id_especialidad'
            )->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $countByEsp[(int) $c['id_especialidad']] = (int) $c['n'];
            }
        }
        $out = [];
        foreach ($legacy as $row) {
            $lid = (int) $row['id'];
            $eq = legacy_equiv_get($pdo, 'especialidad', $lid);
            $mapped = legacy_map_get($pdo, 'especialidad', $lid);
            $st = $pdo->prepare('SELECT id_especialidad FROM especialidades WHERE LOWER(nombre) = LOWER(?) LIMIT 1');
            $st->execute([trim((string) ($row['nombre'] ?? ''))]);
            $sugerido = $st->fetchColumn();
            $muestras = [];
            if ($hasGrpEspId && isset($countByEsp[$lid]) && $countByEsp[$lid] > 0) {
                $gCols = legacy_import_select_cols($leg, 'grupos', ['id', 'clave', 'horario', 'dias']);
                $gq = $leg->prepare(
                    "SELECT {$gCols} FROM grupos WHERE id_especialidad = ? ORDER BY id LIMIT 8"
                );
                $gq->execute([$lid]);
                $muestras = $gq->fetchAll(PDO::FETCH_ASSOC);
            }
            $idHayEfectivo = null;
            if ($eq && $eq['modo'] === 'usar' && !empty($eq['id_hay'])) {
                $idHayEfectivo = (int) $eq['id_hay'];
            } elseif ($mapped) {
                $idHayEfectivo = $mapped;
            } elseif ($sugerido !== false) {
                $idHayEfectivo = (int) $sugerido;
            }
            $hayNombre = '';
            if ($idHayEfectivo) {
                foreach ($hayRows as $h) {
                    if ((int) $h['id_especialidad'] === $idHayEfectivo) {
                        $hayNombre = $h['nombre'];
                        break;
                    }
                }
            }
            $out[] = [
                'id_legacy' => $lid,
                'nombre_legacy' => $row['nombre'] ?? '',
                'num_grupos' => $countByEsp[$lid] ?? 0,
                'muestras_grupos' => $muestras,
                'id_hay_map' => $mapped,
                'id_hay_equiv' => $eq ? (int) ($eq['id_hay'] ?? 0) : null,
                'modo' => $eq['modo'] ?? '',
                'id_hay_sugerido' => $sugerido !== false ? (int) $sugerido : null,
                'id_hay_efectivo' => $idHayEfectivo,
                'nombre_hay_efectivo' => $hayNombre,
            ];
        }
        $gruposSinId = 0;
        if (legacy_import_column_exists($leg, 'grupos', 'especialidad')) {
            $gruposSinId = (int) $leg->query(
                'SELECT COUNT(*) FROM grupos WHERE (id_especialidad IS NULL OR id_especialidad = 0)
                 AND especialidad IS NOT NULL AND TRIM(especialidad) <> \'\''
            )->fetchColumn();
        }
        echo json_encode([
            'status' => 'ok',
            'legacy' => $out,
            'hay' => $hayRows,
            'grupos_sin_id_especialidad' => $gruposSinId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'apply_grupos_especialidad' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        @set_time_limit(0);
        $dryRun = !empty($_POST['dry_run']);
        $res = legacy_import_fase_grupos_remap_especialidad($pdo, $leg, $dryRun);
        echo json_encode([
            'status' => 'ok',
            'message' => $dryRun
                ? 'Simulación: se actualizarían ' . $res['inserted'] . ' grupos'
                : 'Especialidad actualizada en ' . $res['inserted'] . ' grupos HAY',
            'result' => $res,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $entidad = legacy_entidad_normalize(trim($_POST['entidad'] ?? ''));
        $idLegacy = (int) ($_POST['id_legacy'] ?? 0);
        $modo = trim($_POST['modo'] ?? 'usar');
        $idHay = (int) ($_POST['id_hay'] ?? 0);
        if ($idLegacy <= 0 || !in_array($entidad, ['plantel', 'especialidad'], true)) {
            throw new InvalidArgumentException('Datos inválidos');
        }
        if ($modo === 'usar' && $idHay <= 0) {
            throw new InvalidArgumentException('Seleccione un registro HAY o use Omitir / Crear nuevo');
        }
        legacy_equiv_save($pdo, $entidad, $idLegacy, $modo === 'usar' ? $idHay : null, $modo);
        echo json_encode(['status' => 'ok', 'message' => 'Equivalencia guardada'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'stats') {
        $maps = $pdo->query(
            'SELECT entidad, COUNT(*) AS n FROM hay_legacy_map GROUP BY entidad'
        )->fetchAll(PDO::FETCH_ASSOC);
        $equiv = (int) $pdo->query('SELECT COUNT(*) FROM hay_legacy_equivalence')->fetchColumn();
        $pagosHay = (int) $pdo->query("SELECT COUNT(*) FROM alumno_pagos WHERE concepto LIKE '%legado%'")->fetchColumn();
        echo json_encode([
            'status' => 'ok',
            'maps' => $maps,
            'equivalencias' => $equiv,
            'pagos_importados' => $pagosHay,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Acción desconocida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
