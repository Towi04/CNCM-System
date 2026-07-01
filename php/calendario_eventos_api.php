<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !calendario_puede_editar_administrativo()) {
    hay_json_response(['status' => 'error', 'message' => 'Sin permiso para calendario administrativo']);
    exit;
}

calendario_migrate_schema($pdo);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$idPlantel = plantel_id_activo();

if ($action === 'listar') {
    $mes = (int) ($_GET['mes'] ?? date('n'));
    $anio = (int) ($_GET['anio'] ?? date('Y'));
    $desde = sprintf('%d-%02d-01', $anio, max(1, min(12, $mes)));
    $hasta = (new DateTimeImmutable($desde))->modify('last day of this month')->format('Y-m-d');

    $st = $pdo->prepare(
        'SELECT e.*, GROUP_CONCAT(CONCAT(a.tipo_audiencia, ":", a.valor) SEPARATOR "|") AS audiencia_raw
         FROM calendario_evento_admin e
         LEFT JOIN calendario_evento_audiencia a ON a.id_evento = e.id
         WHERE e.fecha <= ? AND (e.fecha_fin IS NULL OR e.fecha_fin >= ?)
           AND (e.id_plantel IS NULL OR e.id_plantel = ?)
         GROUP BY e.id
         ORDER BY e.fecha, e.hora_inicio'
    );
    $st->execute([$hasta, $desde, $idPlantel]);
    $eventos = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $audiencia = [];
        if (!empty($r['audiencia_raw'])) {
            foreach (explode('|', $r['audiencia_raw']) as $part) {
                [$t, $v] = array_pad(explode(':', $part, 2), 2, '');
                if ($t !== '') {
                    $audiencia[] = ['tipo' => $t, 'valor' => $v];
                }
            }
        }
        unset($r['audiencia_raw']);
        $r['audiencia'] = $audiencia;
        $eventos[] = $r;
    }

    hay_json_response([
        'status' => 'ok',
        'eventos' => $eventos,
        'tipos' => calendario_evento_tipos(),
    ]);
    exit;
}

if ($action === 'guardar') {
    $id = (int) ($_POST['id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = $_POST['tipo'] ?? 'evento';
    $fecha = trim($_POST['fecha'] ?? '');
    $fechaFin = trim($_POST['fecha_fin'] ?? '') ?: null;
    $horaIni = trim($_POST['hora_inicio'] ?? '') ?: null;
    $horaFin = trim($_POST['hora_fin'] ?? '') ?: null;
    $lugar = trim($_POST['lugar'] ?? '') ?: null;
    $audienciaJson = $_POST['audiencia'] ?? '[]';

    if ($titulo === '' || $fecha === '') {
        hay_json_response(['status' => 'error', 'message' => 'Título y fecha son obligatorios']);
        exit;
    }

    $tipos = array_keys(calendario_evento_tipos());
    if (!in_array($tipo, $tipos, true)) {
        $tipo = 'evento';
    }

    if ($id > 0) {
        $pdo->prepare(
            'UPDATE calendario_evento_admin SET titulo=?, descripcion=?, tipo=?, fecha=?, fecha_fin=?,
             hora_inicio=?, hora_fin=?, lugar=?, actualizado_en=NOW() WHERE id=?'
        )->execute([$titulo, $descripcion, $tipo, $fecha, $fechaFin, $horaIni, $horaFin, $lugar, $id]);
    } else {
        $pdo->prepare(
            'INSERT INTO calendario_evento_admin (titulo, descripcion, tipo, fecha, fecha_fin, hora_inicio, hora_fin, lugar, id_plantel, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $titulo, $descripcion, $tipo, $fecha, $fechaFin, $horaIni, $horaFin, $lugar, $idPlantel, (int) $_SESSION['user_id'],
        ]);
        $id = (int) $pdo->lastInsertId();
    }

    $pdo->prepare('DELETE FROM calendario_evento_audiencia WHERE id_evento = ?')->execute([$id]);
    $audiencia = json_decode($audienciaJson, true);
    if (is_array($audiencia)) {
        $ins = $pdo->prepare(
            'INSERT INTO calendario_evento_audiencia (id_evento, tipo_audiencia, valor) VALUES (?,?,?)'
        );
        foreach ($audiencia as $a) {
            $t = $a['tipo'] ?? '';
            $v = trim((string) ($a['valor'] ?? ''));
            if (!in_array($t, ['todos', 'rol', 'departamento', 'usuario'], true)) {
                continue;
            }
            if ($t !== 'todos' && $v === '') {
                continue;
            }
            $ins->execute([$id, $t, $v]);
        }
    }

    hay_json_response(['status' => 'ok', 'message' => 'Evento guardado', 'id' => $id]);
    exit;
}

if ($action === 'publicar') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        hay_json_response(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }
    $res = calendario_evento_publicar($pdo, $id, (int) $_SESSION['user_id']);
    hay_json_response(['status' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    exit;
}

if ($action === 'eliminar') {
    $id = (int) ($_POST['id'] ?? 0);
    $pdo->prepare('DELETE FROM calendario_evento_admin WHERE id = ?')->execute([$id]);
    hay_json_response(['status' => 'ok', 'message' => 'Evento eliminado']);
    exit;
}

hay_json_response(['status' => 'error', 'message' => 'Acción no válida']);
