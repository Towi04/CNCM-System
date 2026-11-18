<?php

/**
 * Promote develop → main y control de mantenimiento (solo supervisor).
 * Usa GitHub API con token en config.local.php (HAY_GITHUB_TOKEN).
 */

function supervisor_deploy_puede(): bool
{
    return function_exists('hay_maintenance_puede_gestionar')
        ? hay_maintenance_puede_gestionar()
        : (function_exists('rbac_es_supervisor_cuenta') && rbac_es_supervisor_cuenta());
}

function supervisor_deploy_csrf_token(): string
{
    if (empty($_SESSION['hay_supervisor_deploy_csrf'])) {
        $_SESSION['hay_supervisor_deploy_csrf'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['hay_supervisor_deploy_csrf'];
}

function supervisor_deploy_csrf_ok(?string $token): bool
{
    $expected = (string) ($_SESSION['hay_supervisor_deploy_csrf'] ?? '');

    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

function supervisor_deploy_repo(): string
{
    if (defined('HAY_GITHUB_REPO') && trim((string) HAY_GITHUB_REPO) !== '') {
        return trim((string) HAY_GITHUB_REPO);
    }

    return 'Towi04/CNCM-System';
}

function supervisor_deploy_token(): string
{
    if (defined('HAY_GITHUB_TOKEN')) {
        return trim((string) HAY_GITHUB_TOKEN);
    }

    return '';
}

function supervisor_deploy_configurado(): bool
{
    return supervisor_deploy_token() !== '' && str_contains(supervisor_deploy_repo(), '/');
}

/**
 * @return array{ok:bool,status:int,body:mixed,message?:string}
 */
function supervisor_deploy_github_request(string $method, string $path, ?array $json = null): array
{
    $token = supervisor_deploy_token();
    if ($token === '') {
        return ['ok' => false, 'status' => 0, 'body' => null, 'message' => 'Falta HAY_GITHUB_TOKEN en config.local.php'];
    }
    $url = 'https://api.github.com' . $path;
    $headers = [
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . $token,
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: CNCM-HAY-SupervisorDeploy',
    ];
    if ($json !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'body' => null, 'message' => $err ?: 'Error cURL'];
        }
        $body = json_decode($raw, true);
        if (!is_array($body) && $raw !== '' && $raw !== 'null') {
            $body = ['raw' => $raw];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $body,
            'message' => is_array($body) ? (string) ($body['message'] ?? '') : '',
        ];
    }

    $opts = [
        'http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'timeout' => 60,
            'ignore_errors' => true,
        ],
    ];
    if ($json !== null) {
        $opts['http']['content'] = json_encode($json, JSON_UNESCAPED_UNICODE);
    }
    $raw = @file_get_contents($url, false, stream_context_create($opts));
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    $body = is_string($raw) ? json_decode($raw, true) : null;

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => is_array($body) ? $body : null,
        'message' => is_array($body) ? (string) ($body['message'] ?? '') : 'Sin respuesta GitHub',
    ];
}

/** @return array{ok:bool,message:string,sha_develop?:string,sha_main?:string,ahead?:bool} */
function supervisor_deploy_estado_ramas(): array
{
    $repo = supervisor_deploy_repo();
    $dev = supervisor_deploy_github_request('GET', '/repos/' . $repo . '/commits/develop');
    $main = supervisor_deploy_github_request('GET', '/repos/' . $repo . '/commits/main');
    if (empty($dev['ok']) || empty($main['ok'])) {
        return [
            'ok' => false,
            'message' => 'No se pudieron leer ramas: '
                . trim(($dev['message'] ?? '') . ' / ' . ($main['message'] ?? ''), ' /'),
        ];
    }
    $shaDev = (string) (($dev['body']['sha'] ?? ''));
    $shaMain = (string) (($main['body']['sha'] ?? ''));
    $cmp = supervisor_deploy_github_request(
        'GET',
        '/repos/' . $repo . '/compare/main...develop'
    );
    $ahead = 0;
    if (!empty($cmp['ok']) && is_array($cmp['body'])) {
        $ahead = (int) ($cmp['body']['ahead_by'] ?? 0);
    }

    return [
        'ok' => true,
        'message' => $ahead > 0
            ? ("develop está {$ahead} commit(s) adelante de main")
            : 'develop y main están al mismo punto (o sin commits pendientes)',
        'sha_develop' => substr($shaDev, 0, 7),
        'sha_main' => substr($shaMain, 0, 7),
        'ahead' => $ahead > 0,
        'ahead_by' => $ahead,
    ];
}

/**
 * Fusiona develop → main en GitHub (dispara deploy de producción por push a main).
 *
 * @return array{ok:bool,message:string,html_url?:string}
 */
function supervisor_deploy_promover_a_main(string $confirm): array
{
    if (strtoupper(trim($confirm)) !== 'PROMOVER') {
        return ['ok' => false, 'message' => 'Escriba PROMOVER para confirmar'];
    }
    if (!supervisor_deploy_configurado()) {
        return [
            'ok' => false,
            'message' => 'Configure HAY_GITHUB_TOKEN (y opcional HAY_GITHUB_REPO) en config.local.php del servidor',
        ];
    }
    $repo = supervisor_deploy_repo();
    $estado = supervisor_deploy_estado_ramas();
    if (!empty($estado['ok']) && empty($estado['ahead'])) {
        return [
            'ok' => true,
            'message' => 'Nada que promover: main ya incluye develop. '
                . 'Si el deploy no corrió, use Actions → Deploy GCP → production.',
        ];
    }

    $res = supervisor_deploy_github_request('POST', '/repos/' . $repo . '/merges', [
        'base' => 'main',
        'head' => 'develop',
        'commit_message' => 'Promote develop → main (supervisor desde HAY)',
    ]);
    if (!empty($res['ok'])) {
        $sha = substr((string) ($res['body']['sha'] ?? ''), 0, 7);
        $url = 'https://github.com/' . $repo . '/actions';

        return [
            'ok' => true,
            'message' => 'Merge OK' . ($sha !== '' ? " ({$sha})" : '')
                . '. Se disparó el deploy a producción (revise Actions).',
            'html_url' => $url,
            'sha' => $sha,
        ];
    }
    // 204/409 conflict etc.
    $msg = (string) ($res['message'] ?? 'Merge rechazado');
    if ((int) ($res['status'] ?? 0) === 409) {
        $msg = 'Conflicto al fusionar develop → main. Resuelva en GitHub y reintente.';
    }

    return ['ok' => false, 'message' => $msg . ' (HTTP ' . (int) ($res['status'] ?? 0) . ')'];
}
