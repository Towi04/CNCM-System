<?php
/**
 * Diagnóstico de sesión PHP (cookie path, carpeta writable, persistencia).
 * 1) Abrir este archivo  2) Clic en "Paso 2"  3) Debe decir SESION OK
 */
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/session_helper.php';
hay_session_start();

$paso = (int) ($_GET['paso'] ?? 1);

if ($paso === 1) {
    $_SESSION['hay_diag'] = 'ok_' . time();
    $msg = 'Valor guardado en sesión. Continúe al paso 2.';
} else {
    $ok = isset($_SESSION['hay_diag']) && strpos((string) $_SESSION['hay_diag'], 'ok_') === 0;
    $msg = $ok ? 'SESION OK — la sesión persiste entre peticiones.' : 'SESION FALLO — no se leyó el valor guardado.';
}

$savePath = session_save_path();
$customPath = hay_session_save_path();
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Diag sesión HAY</title></head>
<body style="font-family:sans-serif;max-width:640px;margin:2rem auto;">
<h1>Diagnóstico sesión HAY</h1>
<p><strong><?php echo htmlspecialchars($msg); ?></strong></p>
<ul>
  <li>session_id: <?php echo htmlspecialchars(session_id()); ?></li>
  <li>cookie path: <?php echo htmlspecialchars(hay_app_cookie_path()); ?></li>
  <li>HAY_WEB_ROOT: <?php echo defined('HAY_WEB_ROOT') ? htmlspecialchars((string) HAY_WEB_ROOT) : '(no definido)'; ?></li>
  <li>HTTPS detectado: <?php echo hay_request_is_https() ? 'sí' : 'no'; ?></li>
  <li>session.save_path (activo): <?php echo htmlspecialchars($savePath ?: '(default servidor)'); ?></li>
  <li>storage/sessions writable: <?php echo $customPath ? 'sí' : 'no'; ?></li>
  <li>HTTP_HOST: <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? ''); ?></li>
</ul>
<?php if ($paso === 1): ?>
<p><a href="diag_session.php?paso=2">Paso 2 — probar lectura de sesión</a></p>
<?php else: ?>
<p><a href="diag_session.php">Reiniciar prueba</a></p>
<?php endif; ?>
</body></html>
