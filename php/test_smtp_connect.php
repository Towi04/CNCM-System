<?php
/**
 * Prueba de correo (solo administrador / uso local).
 * Uso: php/test_smtp_connect.php?to=tu@cncm.edu.mx
 */
define('INSTITUTIONAL_EMAIL_DOMAIN', 'cncm.edu.mx');
define('APP_FROM_EMAIL', 'noreply@cncm.edu.mx');
require __DIR__ . '/../php/branding.php';
define('APP_FROM_NAME', APP_DISPLAY_NAME);

if (is_file(__DIR__ . '/../config.mail.php')) {
    require __DIR__ . '/../config.mail.php';
} else {
    die("Falta config.mail.php\n");
}

require __DIR__ . '/mail_helper.php';

header('Content-Type: text/plain; charset=utf-8');

echo "SMTP_HOST: " . SMTP_HOST . "\n";
echo "SMTP_PORT: " . SMTP_PORT . "\n";
echo "SMTP_SECURE: " . SMTP_SECURE . "\n";
echo "SMTP_USER: " . SMTP_USER . "\n\n";

$to = trim($_GET['to'] ?? '');
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "Agrega ?to=correo@cncm.edu.mx en la URL para enviar prueba.\n";
    exit;
}

$ok = mail_send($to, 'Prueba recuperación — ' . app_display_name(), '<p>Si lees esto, el SMTP funciona.</p>');
echo $ok ? "Resultado: ENVIADO\n" : "Resultado: FALLO\n";

$log = dirname(__DIR__) . '/logs/mail.log';
if (is_file($log)) {
    echo "\n--- mail.log ---\n";
    $content = file_get_contents($log);
    echo substr($content, -3000);
}
