<?php

/** Código del último fallo: rcpt_mailbox | auth | connect | data | otro */
function mail_last_error_code(): string
{
    return $GLOBALS['mail_last_error_code'] ?? 'otro';
}

function mail_set_error(string $code, string $logMessage): void
{
    $GLOBALS['mail_last_error_code'] = $code;
    mail_log($logMessage);
}

function mail_log(string $message): void
{
    if (!defined('MAIL_DEBUG') || !MAIL_DEBUG) {
        return;
    }
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
    @file_put_contents($dir . '/mail.log', $line, FILE_APPEND);
}

function mail_resolve_ipv4(string $host): ?string
{
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $host;
    }

    $records = @dns_get_record($host, DNS_A);
    if (is_array($records)) {
        foreach ($records as $r) {
            if (!empty($r['ip'])) {
                return $r['ip'];
            }
        }
    }

    $ip = @gethostbyname($host);
    return ($ip && $ip !== $host) ? $ip : null;
}

function mail_smtp_context(string $sslPeerName = '')
{
    $ssl = [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
    ];
    if ($sslPeerName !== '') {
        $ssl['peer_name'] = $sslPeerName;
        $ssl['SNI_enabled'] = true;
    }

    return stream_context_create(['ssl' => $ssl]);
}

/** Planes de conexión: varios hosts/puertos (Neubox suele bloquear smtp.gmail.com). */
function mail_smtp_build_plans(string $primaryHost, int $port, string $secure): array
{
    $useSsl = ($secure === 'ssl' || $port === 465);
    $isGoogle = stripos($primaryHost, 'gmail.com') !== false
        || stripos($primaryHost, 'google.com') !== false
        || (defined('SMTP_USE_GOOGLE_RELAY') && SMTP_USE_GOOGLE_RELAY);

    $hosts = [$primaryHost];
    if ($isGoogle) {
        if (!in_array('smtp-relay.gmail.com', $hosts, true)) {
            $hosts[] = 'smtp-relay.gmail.com';
        }
        if (!in_array('smtp.gmail.com', $hosts, true)) {
            $hosts[] = 'smtp.gmail.com';
        }
    }

    $plans = [];
    foreach (array_unique($hosts) as $h) {
        if ($useSsl) {
            $plans[] = ['host' => $h, 'scheme' => 'ssl', 'port' => $port ?: 465, 'starttls' => false];
        } else {
            $plans[] = ['host' => $h, 'scheme' => 'tcp', 'port' => $port ?: 587, 'starttls' => true];
            $plans[] = ['host' => $h, 'scheme' => 'ssl', 'port' => 465, 'starttls' => false];
            if ($h === 'smtp-relay.gmail.com') {
                $plans[] = ['host' => $h, 'scheme' => 'tcp', 'port' => 25, 'starttls' => true];
            }
        }
    }
    return $plans;
}

/**
 * @return array{fp: resource, use_starttls: bool, host: string}|null
 */
function mail_smtp_open_connection(string $host): ?array
{
    mail_log('=== mail_helper v3 — inicio conexión SMTP ===');

    $port = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
    $secure = defined('SMTP_SECURE') ? strtolower(trim((string) SMTP_SECURE)) : 'tls';
    $plans = mail_smtp_build_plans($host, $port, $secure);

    foreach ($plans as $plan) {
        $h = $plan['host'];
        $ipv4 = mail_resolve_ipv4($h);
        $peer = $ipv4 ?? $h;
        $target = $plan['scheme'] . '://' . $peer . ':' . $plan['port'];
        mail_log('Intentando ' . $target . ' [' . $h . '] starttls=' . ($plan['starttls'] ? '1' : '0'));

        $ctx = stream_context_create([
            'socket' => ['bindto' => '0:0'],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'peer_name' => $h,
                'SNI_enabled' => true,
            ],
        ]);

        $fp = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if ($fp) {
            mail_log('Conectado: ' . $target);
            return [
                'fp' => $fp,
                'use_starttls' => $plan['starttls'],
                'host' => $h,
            ];
        }

        mail_log("Fallo {$target}: {$errstr} ({$errno})");
    }

    mail_set_error(
        'connect',
        'El servidor Neubox no puede conectar a Gmail (error 99 / puerto bloqueado). '
        . 'Use SMTP relay de Google (smtp-relay.gmail.com) con la IP del servidor autorizada en Admin Console, '
        . 'o pida a Neubox abrir salida TCP 587 y 465 hacia Google.'
    );
    return null;
}

function mail_send_php(string $to, string $subject, string $bodyHtml): bool
{
    $fromEmail = defined('APP_FROM_EMAIL') ? APP_FROM_EMAIL : 'noreply@cncm.edu.mx';
    $fromName = defined('APP_FROM_NAME') ? APP_FROM_NAME : app_display_name();

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
    ];

    $ok = @mail($to, $subject, $bodyHtml, implode("\r\n", $headers));
    if (!$ok) {
        mail_log("mail() falló al enviar a {$to}");
    }
    return $ok;
}

function mail_send_smtp(string $to, string $subject, string $bodyHtml): bool
{
    $user = defined('SMTP_USER') ? SMTP_USER : '';
    $pass = defined('SMTP_PASS') ? SMTP_PASS : '';
    $fromEmail = ($user !== '') ? $user : (defined('APP_FROM_EMAIL') ? APP_FROM_EMAIL : 'noreply@cncm.edu.mx');
    $fromName = defined('APP_FROM_NAME') ? APP_FROM_NAME : app_display_name();

    $host = defined('SMTP_HOST') ? SMTP_HOST : '';
    if ($host === '') {
        mail_log('SMTP_HOST no configurado');
        return false;
    }

    $conn = mail_smtp_open_connection($host);
    if (!$conn) {
        return false;
    }

    $fp = $conn['fp'];
    $useStartTls = $conn['use_starttls'];
    $GLOBALS['mail_last_error_code'] = '';

    stream_set_timeout($fp, 25);
    $read = function () use ($fp): string {
        $data = '';
        while ($line = @fgets($fp, 515)) {
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $command, bool $logCmd = true) use ($fp, $read): string {
        if ($logCmd && stripos($command, 'AUTH') === false && !preg_match('/^[A-Za-z0-9+\/=]{8,}$/', $command)) {
            mail_log('>> ' . $command);
        }
        fwrite($fp, $command . "\r\n");
        $resp = $read();
        if ($logCmd) {
            mail_log('<< ' . trim(preg_replace('/\s+/', ' ', $resp)));
        }
        return $resp;
    };

    $greet = $read();
    if (strpos($greet, '220') !== 0) {
        mail_log('Saludo SMTP inválido: ' . trim($greet));
        fclose($fp);
        return false;
    }

    $ehloHost = preg_replace('/[^a-zA-Z0-9.\-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
    $ehlo = $cmd('EHLO ' . $ehloHost);

    if ($useStartTls && stripos($ehlo, 'STARTTLS') !== false) {
        $tls = $cmd('STARTTLS');
        if (strpos($tls, '220') !== 0) {
            mail_log('STARTTLS falló');
            fclose($fp);
            return false;
        }
        $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$crypto) {
            mail_log('No se pudo activar cifrado TLS');
            fclose($fp);
            return false;
        }
        $ehlo = $cmd('EHLO ' . $ehloHost);
    }

    $skipAuth = (defined('SMTP_SKIP_AUTH') && SMTP_SKIP_AUTH)
        || (defined('SMTP_USE_GOOGLE_RELAY') && SMTP_USE_GOOGLE_RELAY && $user === '');

    if (!$skipAuth && $user !== '' && $pass !== '') {
        $auth = $cmd('AUTH LOGIN');
        if (strpos($auth, '334') !== 0) {
            mail_log('AUTH LOGIN no disponible');
            fclose($fp);
            return false;
        }
        $cmd(base64_encode($user), false);
        $passResp = $cmd(base64_encode($pass), false);
        if (strpos($passResp, '235') !== 0) {
            mail_set_error('auth', 'Usuario o contraseña SMTP incorrectos');
            fclose($fp);
            return false;
        }
    } elseif (!$skipAuth && ($user === '' || $pass === '')) {
        mail_log('Sin autenticación SMTP (relay por IP o credenciales vacías)');
    }

    $fromResp = $cmd('MAIL FROM:<' . $fromEmail . '>');
    if (strpos($fromResp, '250') !== 0) {
        mail_log('Remitente rechazado por el servidor');
        fclose($fp);
        return false;
    }

    $toResp = $cmd('RCPT TO:<' . $to . '>');
    if (strpos($toResp, '250') !== 0 && strpos($toResp, '251') !== 0) {
        $detail = trim($toResp);
        if (stripos($detail, '550') !== false || stripos($detail, 'no such user') !== false) {
            mail_set_error(
                'rcpt_mailbox',
                "El servidor de correo no tiene creado el buzón {$to} (550 No Such User Here)"
            );
        } else {
            mail_set_error('rcpt', 'Destinatario rechazado: ' . $detail);
        }
        fclose($fp);
        return false;
    }

    $dataInit = $cmd('DATA');
    if (strpos($dataInit, '354') !== 0) {
        mail_log('Comando DATA no aceptado');
        fclose($fp);
        return false;
    }

    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= 'Subject: ' . mail_encode_subject($subject) . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $message = $headers . "\r\n" . $bodyHtml;
    $message = preg_replace("/\r\n\./", "\r\n..", $message);
    fwrite($fp, $message . "\r\n.\r\n");
    $dataResp = $read();
    mail_log('<< ' . trim(preg_replace('/\s+/', ' ', $dataResp)));
    $cmd('QUIT', false);
    fclose($fp);

    if (strpos($dataResp, '250') !== 0) {
        mail_log('El servidor no aceptó el mensaje');
        return false;
    }

    mail_log("Correo enviado OK a {$to}");
    return true;
}

function mail_encode_subject(string $subject): string
{
    if (preg_match('/[^\x20-\x7E]/', $subject)) {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }
    return $subject;
}

function mail_send(string $to, string $subject, string $bodyHtml): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_log('Correo destino inválido');
        return false;
    }

    if (defined('MAIL_DRIVER') && MAIL_DRIVER === 'smtp') {
        return mail_send_smtp($to, $subject, $bodyHtml);
    }

    return mail_send_php($to, $subject, $bodyHtml);
}

function mail_is_configured(): bool
{
    if (defined('MAIL_DRIVER') && MAIL_DRIVER === 'smtp') {
        if (!defined('SMTP_HOST') || SMTP_HOST === '') {
            return false;
        }
        if (defined('SMTP_USE_GOOGLE_RELAY') && SMTP_USE_GOOGLE_RELAY) {
            return true;
        }
        $pass = defined('SMTP_PASS') ? SMTP_PASS : '';
        $placeholders = ['TU_CONTRASEÑA_AQUI', 'PEGA_AQUI_CONTRASEÑA_DE_APLICACION', 'contraseña_de_aplicacion_16_caracteres'];
        if (in_array($pass, $placeholders, true)) {
            return false;
        }
        return defined('SMTP_USER') && SMTP_USER !== '' && $pass !== '';
    }
    return true;
}

function mail_last_error_hint(): string
{
    $log = dirname(__DIR__) . '/logs/mail.log';
    if (!is_file($log)) {
        return '';
    }
    $lines = array_filter(array_map('trim', explode("\n", file_get_contents($log))));
    return $lines ? end($lines) : '';
}
