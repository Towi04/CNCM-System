<?php
declare(strict_types=1);

function hay_openrouter_api_key(): string
{
    if (defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY !== '') {
        return (string) OPENROUTER_API_KEY;
    }
    $env = getenv('OPENROUTER_API_KEY');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    $root = defined('HAY_ROOT') ? HAY_ROOT : dirname(__DIR__);
    $envFile = $root . DIRECTORY_SEPARATOR . 'env';
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'OPENROUTER_API_KEY=')) {
                return trim(substr($line, strlen('OPENROUTER_API_KEY=')));
            }
        }
    }

    return '';
}

function hay_openrouter_model(): string
{
    if (defined('OPENROUTER_MODEL') && OPENROUTER_MODEL !== '') {
        return (string) OPENROUTER_MODEL;
    }
    return 'openai/gpt-4o';
}

function hay_openrouter_site_url(): string
{
    if (defined('OPENROUTER_SITE_URL') && OPENROUTER_SITE_URL !== '') {
        return (string) OPENROUTER_SITE_URL;
    }
    $root = function_exists('hay_web_root')
        ? hay_web_root()
        : (defined('HAY_WEB_ROOT') ? (string) HAY_WEB_ROOT : '/');
    if (!str_starts_with($root, 'http')) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return rtrim($scheme . '://' . $host . rtrim($root, '/'), '/');
    }
    return rtrim($root, '/');
}

function hay_openrouter_site_name(): string
{
    if (defined('OPENROUTER_SITE_NAME') && OPENROUTER_SITE_NAME !== '') {
        return (string) OPENROUTER_SITE_NAME;
    }
    return function_exists('app_display_name') ? app_display_name() : 'Sistema CNCM';
}

/**
 * @param array<int, array{role:string,content:string}> $messages
 * @param array<string, mixed> $options
 * @return array{ok:bool,http_code:int,model?:string,text?:string,message?:string,hint?:string,raw?:string,provider?:string,usage?:array<string,int|float>}
 */
function hay_openrouter_chat(array $messages, array $options = []): array
{
    $apiKey = hay_openrouter_api_key();
    if ($apiKey === '') {
        return ['ok' => false, 'http_code' => 0, 'message' => 'Falta OPENROUTER_API_KEY', 'provider' => 'openrouter'];
    }

    $model = (string) ($options['model'] ?? hay_openrouter_model());
    $temperature = (float) ($options['temperature'] ?? 0.7);
    $maxTokens = (int) ($options['max_tokens'] ?? 1200);

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'HTTP-Referer: ' . hay_openrouter_site_url(),
        'X-Title: ' . hay_openrouter_site_name(),
    ];

    $url = 'https://openrouter.ai/api/v1/chat/completions';
    $raw = null;
    $httpCode = 0;
    $errDetail = null;

    if (function_exists('curl_init')) {
        $sslVerify = !filter_var(getenv('OPENROUTER_TEST_INSECURE_SSL') ?: '0', FILTER_VALIDATE_BOOLEAN);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            $errDetail = 'cURL: ' . curl_error($ch);
        }
        curl_close($ch);
    } else {
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => 90,
            ],
        ];
        $raw = @file_get_contents($url, false, stream_context_create($opts));
        $httpCode = 200;
        if ($raw === false) {
            $errDetail = 'file_get_contents falló';
        }
    }

    if (!is_string($raw) || $raw === '') {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'model' => $model,
            'message' => 'Error llamando a OpenRouter',
            'hint' => $errDetail,
            'provider' => 'openrouter',
        ];
    }

    $data = json_decode($raw, true);

    if ($httpCode === 429 || $httpCode === 503) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'model' => $model,
            'message' => 'OpenRouter: cuota o servicio no disponible',
            'hint' => is_array($data) ? ($data['error']['message'] ?? '') : '',
            'raw' => mb_substr($raw, 0, 300),
            'provider' => 'openrouter',
        ];
    }

    if ($httpCode >= 400) {
        $apiErr = is_array($data) ? ($data['error']['message'] ?? $data['error'] ?? '') : '';
        if (is_array($apiErr)) {
            $apiErr = json_encode($apiErr, JSON_UNESCAPED_UNICODE);
        }
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'model' => $model,
            'message' => 'OpenRouter HTTP ' . $httpCode,
            'hint' => $httpCode === 401 || $httpCode === 403
                ? 'Clave inválida o sin créditos. Revise openrouter.ai/keys'
                : (string) $apiErr,
            'raw' => mb_substr($raw, 0, 300),
            'provider' => 'openrouter',
        ];
    }

    $text = $data['choices'][0]['message']['content'] ?? '';
    if (!is_string($text) || trim($text) === '') {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'model' => $model,
            'message' => 'OpenRouter no devolvió texto',
            'raw' => mb_substr($raw, 0, 300),
            'provider' => 'openrouter',
        ];
    }

    $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

    return [
        'ok' => true,
        'http_code' => $httpCode,
        'model' => (string) ($data['model'] ?? $model),
        'text' => $text,
        'provider' => 'openrouter',
        'usage' => [
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
        ],
    ];
}

/**
 * @param array<string, mixed> $options temperature, max_tokens, model
 * @return array{ok:bool,http_code:int,model?:string,text?:string,message?:string,hint?:string,raw?:string,provider?:string}
 */
function hay_openrouter_request(string $prompt, array $options = []): array
{
    $res = hay_openrouter_chat([['role' => 'user', 'content' => $prompt]], $options);
    unset($res['usage']);
    return $res;
}

function hay_openrouter_embedding_model(): string
{
    if (function_exists('academico_embedding_modelo')) {
        return academico_embedding_modelo();
    }
    if (defined('OPENROUTER_EMBEDDING_MODEL') && OPENROUTER_EMBEDDING_MODEL !== '') {
        return (string) OPENROUTER_EMBEDDING_MODEL;
    }

    return 'openai/text-embedding-3-small';
}

/**
 * @return array{ok:bool,http_code:int,model?:string,vector?:list<float>,message?:string,hint?:string,provider?:string}
 */
function hay_openrouter_embedding(string $text): array
{
    $apiKey = hay_openrouter_api_key();
    if ($apiKey === '') {
        return ['ok' => false, 'http_code' => 0, 'message' => 'Falta OPENROUTER_API_KEY', 'provider' => 'openrouter'];
    }
    $text = trim($text);
    if ($text === '') {
        return ['ok' => false, 'http_code' => 0, 'message' => 'Texto vacío', 'provider' => 'openrouter'];
    }

    $model = hay_openrouter_embedding_model();
    $payload = json_encode(['model' => $model, 'input' => $text], JSON_UNESCAPED_UNICODE);
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'HTTP-Referer: ' . hay_openrouter_site_url(),
        'X-Title: ' . hay_openrouter_site_name(),
    ];
    $url = 'https://openrouter.ai/api/v1/embeddings';
    $raw = null;
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

    if (!is_string($raw) || $raw === '') {
        return ['ok' => false, 'http_code' => $httpCode, 'message' => 'Error embeddings OpenRouter', 'provider' => 'openrouter'];
    }

    $data = json_decode($raw, true);
    if ($httpCode >= 400) {
        $err = is_array($data) ? ($data['error']['message'] ?? '') : '';

        return ['ok' => false, 'http_code' => $httpCode, 'message' => 'OpenRouter embeddings HTTP ' . $httpCode, 'hint' => (string) $err, 'provider' => 'openrouter'];
    }

    $vec = $data['data'][0]['embedding'] ?? null;
    if (!is_array($vec)) {
        return ['ok' => false, 'http_code' => $httpCode, 'message' => 'Embedding vacío', 'provider' => 'openrouter'];
    }

    return [
        'ok' => true,
        'http_code' => $httpCode,
        'model' => (string) ($data['model'] ?? $model),
        'vector' => array_map('floatval', $vec),
        'provider' => 'openrouter',
    ];
}
