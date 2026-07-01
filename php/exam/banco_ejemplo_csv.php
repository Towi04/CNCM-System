<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/load.php';

use HayExam\BancoInglesService;

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$tipo = trim($_GET['tipo'] ?? '');
$svc = new BancoInglesService($GLOBALS['pdo']);

if (!$svc->tipoValido($tipo)) {
    http_response_code(400);
    echo 'Tipo no válido';
    exit;
}

$label = BancoInglesService::TIPOS[$tipo]['label'];
$filename = 'ejemplo_' . $tipo . '_ingles.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF";
echo $svc->generarCsvEjemplo($tipo);
