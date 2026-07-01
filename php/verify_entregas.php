<?php
declare(strict_types=1);

/**
 * Verificación de entregas recientes (OMR, cronología PDF, menú supervisor, Gemini, reportes, Moodle, HAY 360).
 * CLI: php php/verify_entregas.php
 * Web: supervisor o localhost (requiere config.php / sesión).
 */
$cli = PHP_SAPI === 'cli';
$root = dirname(__DIR__);

/** @var list<array{ok:bool,label:string,detail:string}> */
$checks = [];

function verify_add(bool $ok, string $label, string $detail = ''): void
{
    global $checks;
    $checks[] = ['ok' => $ok, 'label' => $label, 'detail' => $detail];
}

$pdo = null;

if ($cli) {
    if (is_readable($root . '/config.local.php')) {
        require_once $root . '/config.local.php';
    }
    require_once $root . '/php/rbac_helper.php';
    require_once $root . '/php/plantel_helper.php';
    require_once $root . '/php/academico_helper.php';
    require_once $root . '/php/asistencia_helper.php';
    require_once $root . '/php/cronologia_helper.php';
    require_once $root . '/php/menu_config.php';
    require_once $root . '/php/gemini_helper.php';
    require_once $root . '/php/openrouter_helper.php';
    require_once $root . '/php/ai_helper.php';
    require_once $root . '/php/exam/load.php';
    require_once $root . '/php/reporte_academico_helper.php';
    require_once $root . '/php/moodle_fase_helper.php';
    require_once $root . '/php/profesor_eval_helper.php';
    verify_add(true, 'Conexión MySQL', 'Omitida en CLI — probar en servidor con BD');
} else {
    header('Content-Type: text/plain; charset=utf-8');
    $esLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    require $root . '/config.php';
    if (!$esLocal && (!function_exists('rbac_es_supervisor') || !rbac_es_supervisor())) {
        http_response_code(403);
        echo "Sin permiso.\n";
        exit;
    }
    verify_add(isset($pdo) && $pdo instanceof PDO, 'Conexión MySQL', isset($pdo) && $pdo instanceof PDO ? 'OK' : 'Sin PDO');
}

verify_add(is_file($root . '/php/cronologia_pdf.php'), 'Cronología PDF endpoint', 'php/cronologia_pdf.php');
verify_add(function_exists('cronologia_generar_pdf'), 'Cronología helpers PDF/matriz', '');
verify_add(function_exists('menu_cncm_vista_por_rol'), 'Menú supervisor por roles', '');
verify_add(
    class_exists('HayExam\\AnswerSheetLayout'),
    'OMR AnswerSheetLayout',
    class_exists('HayExam\\AnswerSheetLayout') ? 'v' . \HayExam\AnswerSheetLayout::VERSION : ''
);
$hoja = $root . '/uploads/examenes/hoja_respuestas_universal.html';
if (!is_file($hoja) && class_exists('HayExam\\AnswerSheetTemplate')) {
    $dir = dirname($hoja);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($hoja, \HayExam\AnswerSheetTemplate::renderUniversal());
}
verify_add(
    is_file($hoja),
    'Hoja Answer Sheet v5',
    is_file($hoja) ? basename($hoja) . ' lista para imprimir' : 'Genere: php/exam/descargar.php?tipo=hoja'
);
verify_add(function_exists('hay_ai_request'), 'IA planeación (OpenRouter/Gemini)', function_exists('hay_ai_configured') && hay_ai_configured() ? hay_ai_provider_label() : 'sin clave IA');
verify_add(is_file($root . '/php/reporte_academico_export.php'), 'Export reporte académico', '');
verify_add(function_exists('moodle_fase_cobertura_especialidad'), 'Moodle cobertura por especialidad', '');
verify_add(function_exists('profesor_eval_vincular_hay_global'), 'Vinculación eval 360 → HAY', '');
verify_add(is_file($root . '/views/moodle_nivel_admin.php'), 'Vista Moodle por nivel', '');

if ($pdo instanceof PDO) {
    try {
        $nGrupos = (int) $pdo->query('SELECT COUNT(*) FROM grupos')->fetchColumn();
        verify_add(true, 'Grupos en BD', (string) $nGrupos);
    } catch (Throwable $e) {
        verify_add(false, 'Consulta grupos', $e->getMessage());
    }
}

$okCount = 0;
$failCount = 0;
echo "=== HAY verify_entregas ===\n\n";
foreach ($checks as $c) {
    $mark = $c['ok'] ? '[OK]' : '[FAIL]';
    echo $mark . ' ' . $c['label'];
    if ($c['detail'] !== '') {
        echo ' — ' . $c['detail'];
    }
    echo "\n";
    $c['ok'] ? $okCount++ : $failCount++;
}

echo "\nResumen: {$okCount} OK, {$failCount} fallos\n";
exit($failCount > 0 ? 1 : 0);
