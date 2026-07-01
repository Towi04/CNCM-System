<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/load.php';

use HayExam\BancoInglesService;

function banco_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    banco_json(['status' => 'error', 'message' => 'Sesión no válida.'], 401);
}

global $pdo;
$svc = new BancoInglesService($pdo);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            $tipo = trim($_GET['tipo'] ?? '');
            if (!$svc->tipoValido($tipo)) {
                throw new InvalidArgumentException('Tipo no válido.');
            }
            $fase = isset($_GET['fase']) && trim($_GET['fase']) !== '' ? trim($_GET['fase']) : null;
            $page = max(1, (int)($_GET['page'] ?? 1));
            $result = $svc->listar($tipo, $fase, $page, 25);
            banco_json(['status' => 'ok', 'tipo' => $tipo, 'meta' => BancoInglesService::TIPOS[$tipo], ...$result]);

        case 'obtener':
            $tipo = trim($_GET['tipo'] ?? '');
            $id = (int)($_GET['id'] ?? 0);
            if (!$svc->tipoValido($tipo) || $id < 1) {
                throw new InvalidArgumentException('Parámetros inválidos.');
            }
            $row = $svc->obtener($tipo, $id);
            if (!$row) {
                banco_json(['status' => 'error', 'message' => 'No encontrado.'], 404);
            }
            banco_json(['status' => 'ok', 'item' => $row, 'meta' => BancoInglesService::TIPOS[$tipo]]);

        case 'crear':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Método no permitido.');
            }
            $tipo = trim($_POST['tipo'] ?? '');
            if (!$svc->tipoValido($tipo)) {
                throw new InvalidArgumentException('Tipo no válido.');
            }
            $data = [];
            foreach (BancoInglesService::TIPOS[$tipo]['campos'] as $c) {
                if (isset($_POST[$c])) {
                    $data[$c] = $_POST[$c];
                }
            }
            $newId = $svc->crear($tipo, $data);
            banco_json(['status' => 'ok', 'message' => 'Pregunta agregada correctamente.', 'id' => $newId]);

        case 'guardar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Método no permitido.');
            }
            $tipo = trim($_POST['tipo'] ?? '');
            $id = (int)($_POST['id'] ?? 0);
            if (!$svc->tipoValido($tipo) || $id < 1) {
                throw new InvalidArgumentException('Parámetros inválidos.');
            }
            $data = [];
            foreach (BancoInglesService::TIPOS[$tipo]['campos'] as $c) {
                if (isset($_POST[$c])) {
                    $data[$c] = $_POST[$c];
                }
            }
            $svc->actualizar($tipo, $id, $data);
            banco_json(['status' => 'ok', 'message' => 'Registro actualizado.']);

        case 'eliminar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Método no permitido.');
            }
            $tipo = trim($_POST['tipo'] ?? '');
            $id = (int)($_POST['id'] ?? 0);
            if (!$svc->tipoValido($tipo) || $id < 1) {
                throw new InvalidArgumentException('Parámetros inválidos.');
            }
            $svc->eliminar($tipo, $id);
            banco_json(['status' => 'ok', 'message' => 'Registro eliminado.']);

        case 'importar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Método no permitido.');
            }
            $tipo = trim($_POST['tipo'] ?? '');
            if (!$svc->tipoValido($tipo)) {
                throw new InvalidArgumentException('Tipo no válido.');
            }
            if (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
                throw new InvalidArgumentException('Seleccione un archivo CSV.');
            }
            $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                throw new InvalidArgumentException('Solo se permiten archivos .csv');
            }
            $n = $svc->importarCsv($tipo, $_FILES['csv_file']['tmp_name']);
            banco_json(['status' => 'ok', 'message' => "Se importaron {$n} registro(s) correctamente.", 'importados' => $n]);

        case 'fases':
            $tipo = trim($_GET['tipo'] ?? '');
            if (!$svc->tipoValido($tipo)) {
                throw new InvalidArgumentException('Tipo no válido.');
            }
            banco_json(['status' => 'ok', 'fases' => $svc->getFasesDisponibles($tipo)]);

        case 'tipos':
            $tipos = [];
            foreach (BancoInglesService::TIPOS as $k => $v) {
                $tipos[$k] = ['label' => $v['label'], 'headers' => $v['csv_headers']];
            }
            banco_json(['status' => 'ok', 'tipos' => $tipos]);

        default:
            banco_json(['status' => 'error', 'message' => 'Acción no válida.'], 400);
    }
} catch (Throwable $e) {
    banco_json(['status' => 'error', 'message' => $e->getMessage()], 400);
}
