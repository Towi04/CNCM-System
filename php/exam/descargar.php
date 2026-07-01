<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/load.php';

use HayExam\InglesExamService;

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

global $pdo;

$id = strtoupper(trim($_GET['id'] ?? ''));
$tipo = trim($_GET['tipo'] ?? 'pdf');
$base = dirname(__DIR__, 2);

if ($tipo === 'hoja') {
    require_once __DIR__ . '/AnswerSheetService.php';
    $sheetSvc = new \HayExam\AnswerSheetService($pdo, $base);
    try {
        $rel = $sheetSvc->generarHojaUniversal();
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'No se pudo generar la hoja: ' . htmlspecialchars($e->getMessage());
        exit;
    }
    $path = $base . '/' . ltrim(str_replace('\\', '/', $rel), '/');
    if (!is_file($path)) {
        http_response_code(404);
        echo 'Archivo no encontrado';
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="answer_sheet.html"');
    $html = (string)file_get_contents($path);
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '', 3)), '/') . '/';
    $examDir = $appBase . trim(str_replace('\\', '/', dirname($rel)), '/') . '/';
    if (stripos($html, '<base ') === false && stripos($html, '<head>') !== false) {
        $html = preg_replace('/<head>/i', '<head><base href="' . htmlspecialchars($examDir, ENT_QUOTES, 'UTF-8') . '">', $html, 1);
    }
    echo $html;
    exit;
}

if ($id === '') {
    http_response_code(400);
    echo 'ID requerido';
    exit;
}

$svc = new InglesExamService($pdo, $base);
$row = $svc->obtenerPorId($id);

if (!$row) {
    http_response_code(404);
    echo 'Examen no encontrado en la base de datos.';
    exit;
}

$id = $svc->normalizarIdExamen($row['id_examen']);

try {
    if ($tipo === 'csv') {
        $rel = $svc->asegurarArchivoCsv($id);
    } else {
        $rel = $svc->asegurarArchivoPdf($id);
    }
} catch (Throwable $e) {
    http_response_code(404);
    echo htmlspecialchars($e->getMessage());
    exit;
}

$path = $base . '/' . ltrim(str_replace('\\', '/', $rel), '/');

if (!is_file($path)) {
    http_response_code(404);
    echo 'No se pudo localizar el archivo del examen.';
    exit;
}

$safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string)$row['nombre_examen']);

if ($tipo === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $safeName . '_' . $id . '_respuestas.csv"');
    readfile($path);
    exit;
}

if (preg_match('/\.html$/i', $path)) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $safeName . '_' . $id . '.html"');
    $html = (string)file_get_contents($path);
    $html = str_replace(
        '45 preguntas · 10 vocab · 20 gramática · 2 columnas',
        '45 preguntas: 10 vocabulario · 20 gramática · 5 listening · 6 reading · 2 writing · 2 speaking',
        $html
    );
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '', 3)), '/') . '/';
    $examDir = $appBase . trim(str_replace('\\', '/', dirname($rel)), '/') . '/';
    if (stripos($html, '<base ') === false && stripos($html, '<head>') !== false) {
        $html = preg_replace('/<head>/i', '<head><base href="' . htmlspecialchars($examDir, ENT_QUOTES, 'UTF-8') . '">', $html, 1);
    }
    echo $html;
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $safeName . '_' . $id . '.pdf"');
readfile($path);
