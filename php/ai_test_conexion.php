<?php
/**
 * Prueba del proveedor de IA configurado (OpenRouter o Gemini).
 * CLI: php php/ai_test_conexion.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (is_readable($root . '/config.local.php')) {
    require_once $root . '/config.local.php';
}

require_once __DIR__ . '/ai_helper.php';

$isCli = PHP_SAPI === 'cli';
$out = static function (array $data) use ($isCli): void {
    if ($isCli) {
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
};

$provider = hay_ai_provider();
if ($provider === '') {
    $out([
        'status' => 'error',
        'message' => 'Configure OPENROUTER_API_KEY o GEMINI_API_KEY en config.local.php',
    ]);
    exit(1);
}

$res = hay_ai_request('Responde solo la palabra OK.', ['max_tokens' => 16]);

if (!$res['ok']) {
    $out([
        'status' => 'error',
        'provider' => $provider,
        'message' => $res['message'] ?? 'Error de IA',
        'http_code' => $res['http_code'] ?? 0,
        'model' => $res['model'] ?? null,
        'hint' => $res['hint'] ?? null,
    ]);
    exit(1);
}

$out([
    'status' => 'ok',
    'message' => 'Conexión con ' . hay_ai_provider_label() . ' correcta',
    'provider' => $provider,
    'http_code' => $res['http_code'],
    'model' => $res['model'],
    'respuesta_prueba' => trim((string) ($res['text'] ?? '')),
]);
exit(0);
