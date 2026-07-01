<?php
/**
 * Purga de datos operativos desde el navegador (solo supervisor).
 * URL: php/purge_datos_operativos_run.php
 */
declare(strict_types=1);

if (!defined('HAY_PURGE_QUIET_WARNINGS')) {
    define('HAY_PURGE_QUIET_WARNINGS', true);
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
    ini_set('display_errors', '0');
}

require __DIR__ . '/../config.php';
require_once __DIR__ . '/purge_operativo_helper.php';

if (!isset($_SESSION['user_id']) || !purge_puede_ejecutar()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Solo el rol <strong>supervisor</strong> puede ejecutar esta purga.</p>';
    exit;
}

$confirm = trim((string) ($_POST['confirm'] ?? $_GET['confirm'] ?? ''));
$soloDemo = !empty($_POST['solo_demo']) || !empty($_GET['solo_demo']);
$legacyCatalogo = !empty($_POST['legacy_catalogo']) || !empty($_GET['legacy_catalogo']);

if ($confirm !== PURGE_CONFIRM_PHRASE) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Purga de datos</title></head>';
    echo '<body style="font-family:sans-serif;padding:24px;max-width:720px;line-height:1.5;">';
    echo '<h1 style="color:#b71c1c;">Purga de datos operativos</h1>';
    echo '<p><strong>Esta acción es irreversible.</strong> Elimina alumnos, grupos, pagos, pre-registros, asistencias, importación legacy y datos de prueba.</p>';
    echo '<p><strong>Se conserva:</strong> planteles, roles, usuarios del personal real, catálogo de especialidades/productos, rúbrica HAY y bancos de exámenes.</p>';
    echo '<div style="background:#fff3e0;border:1px solid #ffb74d;border-radius:8px;padding:14px;margin:16px 0;">';
    echo '<strong>Texto de confirmación (copie y pegue):</strong><br>';
    echo '<code style="font-size:1.2rem;display:block;margin-top:8px;padding:8px;background:#fff;">' . htmlspecialchars(PURGE_CONFIRM_PHRASE, ENT_QUOTES, 'UTF-8') . '</code>';
    echo '<p style="margin:8px 0 0;font-size:0.9rem;color:#666;">Mayúsculas, con espacio entre las dos palabras. No use comillas.</p>';
    echo '</div>';
    echo '<form method="post" style="margin-top:20px;padding:16px;border:1px solid #e0e0e0;border-radius:12px;">';
    echo '<p>Confirme escribiendo el texto anterior:</p>';
    echo '<input type="text" name="confirm" required style="width:100%;padding:10px;font-size:1rem;margin-bottom:12px;" autocomplete="off">';
    echo '<label style="display:block;margin:8px 0;"><input type="checkbox" name="solo_demo" value="1"> <strong>Solo</strong> datos de prueba (seed demo) — no borra el resto</label>';
    echo '<label style="display:block;margin:8px 0;"><input type="checkbox" name="legacy_catalogo" value="1"> También borrar catálogos importados LEG_* (especialidades y grupos legacy)</label>';
    echo '<p style="font-size:0.88rem;color:#666;">Para vaciar todo (alumnos, grupos, asistencias, productos, mapas legacy): <strong>no marque</strong> la casilla de solo prueba.</p>';
    echo '<button type="submit" style="margin-top:12px;padding:12px 20px;background:#b71c1c;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">Ejecutar purga</button>';
    echo '</form>';
    echo '<p style="color:#666;font-size:0.9rem;margin-top:20px;">CLI: <code>php scripts/purge_datos_operativos.php --confirm=' . htmlspecialchars(PURGE_CONFIRM_PHRASE, ENT_QUOTES, 'UTF-8') . '</code></p>';
    echo '</body></html>';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

try {
    purge_log('=== Purga de datos HAY ===');
    $res = purge_datos_operativos($pdo, [
        'solo_demo' => $soloDemo,
        'legacy_catalogo' => $legacyCatalogo,
    ], static function (string $m): void {
        purge_log($m);
    });
    if (!$res['ok']) {
        http_response_code(500);
        echo 'ERROR: ' . $res['message'] . "\n";
        if (!empty($res['errores'])) {
            echo "\nErrores:\n";
            foreach ($res['errores'] as $err) {
                echo '  - ' . $err . "\n";
            }
        }
        if (!empty($res['restantes'])) {
            echo "\nTablas con datos restantes:\n";
            foreach ($res['restantes'] as $t => $n) {
                echo "  - {$t}: {$n}\n";
            }
        }
        exit;
    }
    purge_log($res['message']);
    purge_log('Total filas afectadas (aprox.): ' . $res['filas']);
    if (!empty($res['restantes'])) {
        purge_log("\nADVERTENCIA — tablas que aún tienen filas:");
        foreach ($res['restantes'] as $t => $n) {
            purge_log("  - {$t}: {$n}");
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR:\n" . $e->getMessage();
}
