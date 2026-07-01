<?php
declare(strict_types=1);

require_once __DIR__ . '/gemini_helper.php';
require_once __DIR__ . '/openrouter_helper.php';

/** @return 'openrouter'|'gemini'|'' */
function hay_ai_provider(): string
{
    $pref = defined('HAY_AI_PROVIDER') ? strtolower(trim((string) HAY_AI_PROVIDER)) : 'auto';

    if ($pref === 'openrouter') {
        return hay_openrouter_api_key() !== '' ? 'openrouter' : '';
    }
    if ($pref === 'gemini') {
        return hay_gemini_api_key() !== '' ? 'gemini' : '';
    }

    if (hay_openrouter_api_key() !== '') {
        return 'openrouter';
    }
    if (hay_gemini_api_key() !== '') {
        return 'gemini';
    }

    return '';
}

function hay_ai_configured(): bool
{
    return hay_ai_provider() !== '';
}

function hay_ai_provider_label(): string
{
    $p = hay_ai_provider();
    if ($p === 'openrouter') {
        return 'OpenRouter (' . hay_openrouter_model() . ')';
    }
    if ($p === 'gemini') {
        return 'Gemini';
    }
    return 'IA';
}

/**
 * @param array<string, mixed> $options
 * @return array{ok:bool,http_code:int,model?:string,text?:string,message?:string,hint?:string,raw?:string,provider?:string}
 */
function hay_ai_request(string $prompt, array $options = []): array
{
    $provider = hay_ai_provider();
    if ($provider === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'message' => 'Falta configurar OPENROUTER_API_KEY o GEMINI_API_KEY en config.local.php',
        ];
    }

    if ($provider === 'openrouter') {
        return hay_openrouter_request($prompt, $options);
    }

    $extra = [];
    if (isset($options['temperature'])) {
        $extra['generationConfig']['temperature'] = (float) $options['temperature'];
    }
    if (isset($options['max_tokens'])) {
        $extra['generationConfig']['maxOutputTokens'] = (int) $options['max_tokens'];
    }

    $res = hay_gemini_request($prompt, $extra);
    $res['provider'] = 'gemini';

    return $res;
}
