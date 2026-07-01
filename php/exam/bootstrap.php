<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/load.php';

use HayExam\InglesExamService;

function exam_ingles_service(): InglesExamService
{
    global $pdo;
    return new InglesExamService($pdo, dirname(__DIR__, 2));
}

function exam_json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function exam_require_session(): void
{
    if (!isset($_SESSION['user_id'])) {
        exam_json_response(['status' => 'error', 'message' => 'Sesión no válida.'], 401);
    }
}
