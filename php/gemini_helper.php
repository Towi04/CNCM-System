<?php
declare(strict_types=1);

/** Modelos Gemini en orden de preferencia (fallback ante 429/404). */
function hay_gemini_modelos(): array
{
    $custom = defined('GEMINI_MODEL') && GEMINI_MODEL !== '' ? [(string) GEMINI_MODEL] : [];
    $defaults = [
        'gemini-2.0-flash-lite',
        'gemini-1.5-flash',
        'gemini-1.5-flash-8b',
        'gemini-2.0-flash',
    ];

    return array_values(array_unique(array_merge($custom, $defaults)));
}

function hay_gemini_api_key(): string
{
    if (function_exists('planeacion_helper_loaded')) {
        // noop — planeacion_helper defines hay_gemini_api_key
    }
    if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') {
        return (string) GEMINI_API_KEY;
    }
    $env = getenv('GEMINI_API_KEY');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    $root = defined('HAY_ROOT') ? HAY_ROOT : dirname(__DIR__);
    $envFile = $root . DIRECTORY_SEPARATOR . 'env';
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), 'GEMINI_API_KEY=')) {
                return trim(substr(trim($line), strlen('GEMINI_API_KEY=')));
            }
        }
    }

    return '';
}

/**
 * @param array<string, mixed> $payloadExtra
 * @return array{ok:bool,http_code:int,model?:string,text?:string,message?:string,hint?:string,raw?:string}
 */
function hay_gemini_request(string $prompt, array $payloadExtra = []): array
{
    $apiKey = hay_gemini_api_key();
    if ($apiKey === '') {
        return ['ok' => false, 'http_code' => 0, 'message' => 'Falta GEMINI_API_KEY'];
    }

    $payload = array_merge([
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 1200,
        ],
    ], $payloadExtra);

    $last = ['ok' => false, 'http_code' => 0, 'message' => 'Sin respuesta'];

    foreach (hay_gemini_modelos() as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . ':generateContent?key=' . urlencode($apiKey);

        $raw = null;
        $httpCode = 0;
        $errDetail = null;

        if (function_exists('curl_init')) {
            $sslVerify = !filter_var(getenv('GEMINI_TEST_INSECURE_SSL') ?: '0', FILTER_VALIDATE_BOOLEAN);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 45,
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
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'timeout' => 45,
                ],
            ];
            $raw = @file_get_contents($url, false, stream_context_create($opts));
            $httpCode = 200;
            if ($raw === false) {
                $errDetail = 'file_get_contents falló';
            }
        }

        if (!is_string($raw) || $raw === '') {
            $last = [
                'ok' => false,
                'http_code' => $httpCode,
                'message' => 'Error llamando a Gemini (' . $model . ')',
                'hint' => $errDetail,
            ];
            continue;
        }

        if ($httpCode === 429 || $httpCode === 503) {
            $last = [
                'ok' => false,
                'http_code' => $httpCode,
                'model' => $model,
                'message' => 'Cuota o servicio no disponible en ' . $model,
                'hint' => 'Probando siguiente modelo…',
                'raw' => mb_substr($raw, 0, 300),
            ];
            continue;
        }

        if ($httpCode >= 400) {
            $bodyData = json_decode($raw, true);
            $apiErr = is_array($bodyData) ? ($bodyData['error']['message'] ?? '') : '';
            $last = [
                'ok' => false,
                'http_code' => $httpCode,
                'model' => $model,
                'message' => 'Gemini HTTP ' . $httpCode . ' (' . $model . ')',
                'hint' => $httpCode === 403
                    ? 'Clave inválida o API no habilitada.'
                    : (string) $apiErr,
                'raw' => mb_substr($raw, 0, 300),
            ];
            if ($httpCode === 404) {
                continue;
            }
            return $last;
        }

        $data = json_decode($raw, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!is_string($text) || trim($text) === '') {
            $last = [
                'ok' => false,
                'http_code' => $httpCode,
                'model' => $model,
                'message' => 'Gemini no devolvió texto (' . $model . ')',
            ];
            continue;
        }

        return [
            'ok' => true,
            'http_code' => $httpCode,
            'model' => $model,
            'text' => $text,
        ];
    }

    if ($last['http_code'] === 429) {
        $last['hint'] = 'Cuota agotada en todos los modelos probados. Revise Google AI Studio o espere reinicio de cuota.';
    }

    return $last;
}
