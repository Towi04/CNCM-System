<?php

if (!defined('INSTITUTIONAL_EMAIL_DOMAIN')) {
    define('INSTITUTIONAL_EMAIL_DOMAIN', 'cncm.edu.mx');
}

/**
 * Normaliza el identificador de acceso (usuario o correo institucional).
 *
 * @return array{local: string, email: string}
 */
function auth_normalize_login(string $input): array
{
    $input = trim(strtolower($input));
    if (strpos($input, '@') !== false) {
        $parts = explode('@', $input, 2);
        $local = trim($parts[0]);
        $email = $input;
    } else {
        $local = $input;
        $email = $local . '@' . INSTITUTIONAL_EMAIL_DOMAIN;
    }

    return ['local' => $local, 'email' => $email];
}

function auth_is_institutional_email(string $email): bool
{
    $email = trim(strtolower($email));
    $domain = strtolower(INSTITUTIONAL_EMAIL_DOMAIN);
    return (bool) preg_match('/^[^@\s]+@' . preg_quote($domain, '/') . '$/i', $email);
}

function auth_institutional_email(string $username): string
{
    return trim(strtolower($username)) . '@' . INSTITUTIONAL_EMAIL_DOMAIN;
}

/**
 * Busca usuario por nombre de usuario o correo (completo o solo local).
 */
function auth_find_user_by_login(PDO $pdo, string $rawInput): ?array
{
    $norm = auth_normalize_login($rawInput);
    if ($norm['local'] === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM usuarios
         WHERE LOWER(username) = ?
            OR (email IS NOT NULL AND email != "" AND LOWER(email) = ?)
         LIMIT 1'
    );
    $stmt->execute([$norm['local'], $norm['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function auth_app_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '.') {
        $base = '';
    } else {
        $base = rtrim($dir, '/');
        // Si el script está en /php/, subir un nivel
        if (substr($base, -4) === '/php') {
            $base = dirname($base);
        }
    }

    return $scheme . '://' . $host . $base;
}

function auth_send_mail(string $to, string $subject, string $bodyHtml): bool
{
    require_once __DIR__ . '/mail_helper.php';
    return mail_send($to, $subject, $bodyHtml);
}

function auth_ensure_password_reset_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token_hash),
            INDEX idx_user (id_usuario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function auth_ensure_email_column(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'email'");
    if (!$stmt->fetch()) {
        $pdo->exec(
            "ALTER TABLE usuarios ADD COLUMN email VARCHAR(120) NULL DEFAULT NULL AFTER username"
        );
        try {
            $pdo->exec('CREATE UNIQUE INDEX idx_usuarios_email ON usuarios (email)');
        } catch (PDOException $e) {
            // Índice ya existente
        }
    }
}
