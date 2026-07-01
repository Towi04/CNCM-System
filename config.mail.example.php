<?php
define('MAIL_DRIVER', 'smtp');
define('MAIL_DEBUG', true);

// Relay Google (Neubox / hosting que bloquea smtp.gmail.com)
define('SMTP_USE_GOOGLE_RELAY', true);
define('SMTP_HOST', 'smtp-relay.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_SKIP_AUTH', true);

// Contraseña de aplicación (si el hosting permite smtp.gmail.com)
// define('SMTP_USE_GOOGLE_RELAY', false);
// define('SMTP_HOST', 'smtp.gmail.com');
// define('SMTP_PORT', 465);
// define('SMTP_SECURE', 'ssl');
// define('SMTP_USER', 'noreply@cncm.edu.mx');
// define('SMTP_PASS', 'xxxx';
