<?php
/**
 * Instalador web de dependencias (Composer).
 * Ejecutar desde el navegador estando logueado, o por SSH: php php/exam/instalar_dependencias.php
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/ExamPdfHelper.php';

$esCli = (php_sapi_name() === 'cli');
if (!$esCli && !isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$raiz = dirname(__DIR__, 2);
$vendor = $raiz . '/vendor/autoload.php';
$log = [];

function inst_log(string $msg): void
{
    global $log;
    $log[] = $msg;
    if (php_sapi_name() === 'cli') {
        echo $msg . PHP_EOL;
    }
}

if (is_file($vendor) && \HayExam\ExamPdfHelper::tieneDompdf()) {
    inst_log('OK: Las dependencias ya están instaladas (PDF automático disponible).');
} else {
    $composerPhar = $raiz . '/composer.phar';
    if (!is_file($composerPhar)) {
        inst_log('Descargando composer.phar...');
        $url = 'https://getcomposer.org/download/latest-stable/composer.phar';
        $ctx = stream_context_create(['http' => ['timeout' => 120]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            inst_log('ERROR: No se pudo descargar Composer. Descárguelo manualmente desde https://getcomposer.org');
        } else {
            file_put_contents($composerPhar, $data);
            inst_log('composer.phar guardado en la raíz del proyecto.');
        }
    }

    if (is_file($composerPhar)) {
        inst_log('Ejecutando: php composer.phar install (puede tardar 1-3 minutos)...');
        $cmd = 'php ' . escapeshellarg($composerPhar) . ' install --no-interaction --working-dir=' . escapeshellarg($raiz) . ' 2>&1';
        $out = [];
        $code = 0;
        exec($cmd, $out, $code);
        foreach ($out as $line) {
            inst_log($line);
        }
        if ($code !== 0) {
            inst_log('ERROR: composer install falló (código ' . $code . ').');
            inst_log('Alternativa: en cPanel busque "PHP Composer" o use Terminal SSH.');
        } elseif (is_file($vendor)) {
            inst_log('OK: Instalación completada. PDF automático habilitado.');
        }
    }
}

if (!$esCli) {
    header('Content-Type: text/html; charset=utf-8');
    $tienePdf = \HayExam\ExamPdfHelper::tieneDompdf();
    ?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Instalar PDF/QR</title>
<style>
body{font-family:sans-serif;max-width:720px;margin:40px auto;padding:0 20px;color:#222;}
.ok{color:#0a0;background:#e8fff0;padding:12px;border-radius:8px;}
.err{color:#900;background:#fff0f0;padding:12px;border-radius:8px;}
pre{background:#f5f5f5;padding:12px;border-radius:8px;overflow:auto;font-size:12px;}
.btn{display:inline-block;margin-top:16px;padding:10px 18px;background:#11458B;color:#fff;text-decoration:none;border-radius:8px;}
</style></head><body>
<h1>Instalación de dependencias (PDF y QR)</h1>
<?php if ($tienePdf): ?>
<p class="ok"><strong>Listo.</strong> El servidor puede generar PDF automáticamente.</p>
<?php else: ?>
<p class="err"><strong>PDF automático no instalado.</strong> El sistema igual genera exámenes en HTML (Ctrl+P para guardar PDF) y QR sin Composer.</p>
<?php endif; ?>
<h3>Registro del proceso</h3>
<pre><?php echo htmlspecialchars(implode("\n", $log)); ?></pre>
<h3>Si falla la instalación automática</h3>
<ol>
<li>Entre a su hosting (cPanel) → <strong>Terminal</strong> o <strong>SSH</strong></li>
<li>Ejecute: <code>cd public_html/hay_system</code> (ajuste la ruta de su proyecto)</li>
<li>Ejecute: <code>php composer.phar install</code> o <code>composer install</code></li>
</ol>
<p>Si no tiene Terminal, use el instalador de Composer en cPanel o suba la carpeta <code>vendor</code> desde otra PC donde sí tenga Composer.</p>
<a class="btn" href="../../dashboard.php">Volver al panel</a>
</body></html>
    <?php
}
