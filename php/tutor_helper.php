<?php
declare(strict_types=1);

/**
 * Tutor Académico Institucional — bootstrap, permisos y utilidades.
 */

require_once __DIR__ . '/tutor/TutorRepository.php';
require_once __DIR__ . '/tutor/ConversationRepository.php';
require_once __DIR__ . '/tutor/MessageRepository.php';
require_once __DIR__ . '/tutor/AiLogRepository.php';
require_once __DIR__ . '/tutor/AcademicContextRetriever.php';
require_once __DIR__ . '/tutor/MaterialContextRetriever.php';
require_once __DIR__ . '/tutor/InstitutionalSystemContextRetriever.php';
require_once __DIR__ . '/tutor/AIService.php';
require_once __DIR__ . '/tutor/TutorAccess.php';
require_once __DIR__ . '/tutor/TutorSeeder.php';
require_once __DIR__ . '/tutor/TutorService.php';

use HayTutor\TutorAccess;
use HayTutor\TutorSeeder;
use HayTutor\TutorService;

function tutor_access(PDO $pdo): TutorAccess
{
    return new TutorAccess($pdo);
}

function tutor_ensure_schema(PDO $pdo): void
{
    try {
        if (function_exists('fase_ensure_schema')) {
            fase_ensure_schema($pdo);
        }
        if (function_exists('usuario_ensure_schema')) {
            usuario_ensure_schema($pdo);
        }
        if (function_exists('asistencia_ensure_schema')) {
            asistencia_ensure_schema($pdo);
        }
    } catch (Throwable $e) {
        error_log('tutor_ensure_schema deps: ' . $e->getMessage());
    }

    try {
        plantel_ensure_column($pdo, 'grupos', 'id_tutor', 'INT UNSIGNED NULL', 'id_especialidad');
    } catch (Throwable $e) {
        error_log('tutor_ensure_schema id_tutor: ' . $e->getMessage());
    }

    if (function_exists('hay_schema_aplicar_migraciones')) {
        hay_schema_aplicar_migraciones($pdo);
    }
    if (function_exists('alumno_perfil_ensure_schema')) {
        alumno_perfil_ensure_schema($pdo);
    }
    if (function_exists('academico_material_ensure_schema')) {
        try {
            academico_material_ensure_schema($pdo);
        } catch (Throwable $e) {
            error_log('tutor_ensure_schema academico_material: ' . $e->getMessage());
        }
    }
    if (function_exists('academico_libro_ensure_schema')) {
        try {
            academico_libro_ensure_schema($pdo);
        } catch (Throwable $e) {
            error_log('tutor_ensure_schema academico_libro: ' . $e->getMessage());
        }
    }

    tutor_ensure_ia_logs_table($pdo);

    try {
        plantel_ensure_column($pdo, 'tutor_conversaciones', 'archivada', 'TINYINT(1) NOT NULL DEFAULT 0', 'origen');
    } catch (Throwable $e) {
        error_log('tutor_ensure_schema archivada: ' . $e->getMessage());
    }

    try {
        (new TutorSeeder($pdo))->seedIfEmpty();
        TutorAccess::backfillGrupos($pdo);
    } catch (Throwable $e) {
        error_log('tutor_seed: ' . $e->getMessage());
    }
}

function tutor_tabla_lista(PDO $pdo, string $tabla): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $st->execute([$tabla]);

    return (bool) $st->fetchColumn();
}

function tutor_schema_listo(PDO $pdo): bool
{
    return tutor_tabla_lista($pdo, 'tutor_tutores')
        && tutor_tabla_lista($pdo, 'tutor_conversaciones')
        && tutor_tabla_lista($pdo, 'tutor_mensajes')
        && tutor_tabla_lista($pdo, 'tutor_ia_logs');
}

/** Crea tutor_ia_logs si faltó en migración 018/020 (evita 500 al registrar prompts). */
function tutor_ensure_ia_logs_table(PDO $pdo): void
{
    if (tutor_tabla_lista($pdo, 'tutor_ia_logs')) {
        return;
    }
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS tutor_ia_logs (
                id_log INT UNSIGNED NOT NULL AUTO_INCREMENT,
                id_usuario INT UNSIGNED NOT NULL,
                id_conversacion INT UNSIGNED NULL,
                id_tutor INT UNSIGNED NULL,
                prompt_enviado MEDIUMTEXT NOT NULL,
                respuesta_recibida MEDIUMTEXT NULL,
                modelo VARCHAR(80) NOT NULL,
                tokens_prompt INT UNSIGNED NOT NULL DEFAULT 0,
                tokens_respuesta INT UNSIGNED NOT NULL DEFAULT 0,
                tokens_total INT UNSIGNED NOT NULL DEFAULT 0,
                costo_estimado DECIMAL(10,6) NOT NULL DEFAULT 0,
                http_code SMALLINT UNSIGNED NULL,
                provider VARCHAR(32) NOT NULL DEFAULT \'openrouter\',
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id_log),
                KEY idx_tutor_log_usuario (id_usuario, creado_en),
                KEY idx_tutor_log_conv (id_conversacion)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $e) {
        error_log('tutor_ensure_ia_logs_table: ' . $e->getMessage());
    }
}

function tutor_service(PDO $pdo): TutorService
{
    return new TutorService($pdo);
}

function tutor_puede_usar(): bool
{
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    if (!($pdo instanceof PDO)) {
        return false;
    }
    tutor_ensure_schema($pdo);

    if (function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() === 'alumno') {
        $idAlumno = function_exists('alumno_portal_id_sesion') ? alumno_portal_id_sesion() : 0;
        if ($idAlumno <= 0) {
            $st = $pdo->prepare('SELECT id_alumno FROM usuarios WHERE id_usuario = ? LIMIT 1');
            $st->execute([(int) $_SESSION['user_id']]);
            $idAlumno = (int) $st->fetchColumn();
        }
        if ($idAlumno > 0 && function_exists('usuario_alumno_puede_tutor') && !usuario_alumno_puede_tutor($pdo, $idAlumno)) {
            return false;
        }
    }

    return tutor_access($pdo)->puedeUsar((int) $_SESSION['user_id']);
}

function tutor_puede_administrar(): bool
{
    if (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total()) {
        return true;
    }
    return function_exists('rbac_cap') && rbac_cap('tutor_administrar');
}

function tutor_sanitize_text(string $text, int $max = 4000): string
{
    $text = trim(strip_tags($text));
    if (mb_strlen($text) > $max) {
        $text = mb_substr($text, 0, $max);
    }

    return $text;
}

function tutor_csrf_secret(): string
{
    if (defined('HAY_CSRF_SECRET') && HAY_CSRF_SECRET !== '') {
        return (string) HAY_CSRF_SECRET;
    }
    if (defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY !== '') {
        return (string) OPENROUTER_API_KEY;
    }

    return 'hay-tutor-csrf-' . (defined('HAY_ROOT') ? HAY_ROOT : __DIR__);
}

/** Token derivado de sesión + usuario (no requiere persistir en $_SESSION). */
function tutor_csrf_compute(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (function_exists('hay_session_start')) {
            hay_session_start();
        } elseif (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    $sid = session_id();
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($sid === '' || $uid <= 0) {
        return '';
    }

    if (function_exists('hay_session_release_lock')) {
        hay_session_release_lock();
    }

    return hash_hmac('sha256', $sid . '|' . $uid, tutor_csrf_secret());
}

function tutor_csrf_token(): string
{
    return tutor_csrf_compute();
}

function tutor_csrf_validate(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }
    $expected = tutor_csrf_compute();

    return $expected !== '' && hash_equals($expected, $token);
}

function tutor_moodle_api_key(): string
{
    if (defined('TUTOR_MOODLE_API_KEY') && TUTOR_MOODLE_API_KEY !== '') {
        return (string) TUTOR_MOODLE_API_KEY;
    }
    $env = getenv('TUTOR_MOODLE_API_KEY');

    return is_string($env) ? $env : '';
}

function tutor_json(array $data, int $code = 200): void
{
    if (function_exists('hay_json_response')) {
        hay_json_response($data, $code);
        return;
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

function tutor_require_session(): int
{
    if (empty($_SESSION['user_id'])) {
        tutor_json(['status' => 'error', 'message' => 'Sesión no iniciada'], 401);
        exit;
    }

    return (int) $_SESSION['user_id'];
}

function tutor_require_permiso(): int
{
    $uid = tutor_require_session();
    global $pdo;
    if ($pdo instanceof PDO && !tutor_access($pdo)->puedeUsar($uid)) {
        tutor_json(['status' => 'error', 'message' => 'Sin permiso para usar el tutor'], 403);
        exit;
    }

    return $uid;
}

function tutor_usuario_puede_tutor(int $userId, int $tutorId): bool
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return false;
    }

    return tutor_access($pdo)->puedeTutor($userId, $tutorId);
}

function tutor_asignar_grupo(PDO $pdo, int $idGrupo): void
{
    TutorAccess::asignarTutorGrupo($pdo, $idGrupo);
}

function tutor_require_csrf_post(): void
{
    $xhr = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    if ($xhr !== 'fetch' && $xhr !== 'xmlhttprequest') {
        tutor_json(['status' => 'error', 'message' => 'Solicitud no válida'], 403);
        exit;
    }

    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_TUTOR_CSRF'] ?? '';
    if (!tutor_csrf_validate(is_string($token) ? $token : null)) {
        tutor_json(['status' => 'error', 'message' => 'Token CSRF inválido'], 403);
        exit;
    }
}

function tutor_especialidad_icon(string $esp): string
{
    return match ($esp) {
        'ingles' => 'fa-language',
        'computacion' => 'fa-laptop-code',
        'preparatoria' => 'fa-graduation-cap',
        'kids' => 'fa-child',
        default => 'fa-robot',
    };
}

function tutor_especialidad_label(string $esp): string
{
    return match ($esp) {
        'ingles' => 'Inglés',
        'computacion' => 'Computación',
        'preparatoria' => 'Preparatoria',
        'kids' => 'Kids',
        default => 'General',
    };
}
