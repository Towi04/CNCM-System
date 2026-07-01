<?php
require_once __DIR__ . '/../config.php';
if (!alumno_portal_puede_ver()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idVersion = (int) ($_GET['id_version'] ?? 0);
$titulo = trim((string) ($_GET['titulo'] ?? 'Libro'));
$token = (string) ($_GET['token'] ?? '');
$idAlumno = alumno_portal_id_o_detener();
if ($idAlumno <= 0) {
    echo '<div class="alert">Seleccione un alumno para previsualizar.</div>';
    return;
}

if ($idVersion <= 0 || $token === '' || !academico_libro_alumno_puede_ver($pdo, $idAlumno, $idVersion)) {
    echo '<div class="alert">No tiene acceso a este libro.</div>';
    return;
}

$uid = (int) ($_SESSION['user_id'] ?? 0);
$tokCheck = academico_libro_stream_validar_token($token);
if (empty($tokCheck['ok']) || (int) ($tokCheck['user_id'] ?? 0) !== $uid || (int) ($tokCheck['id_version'] ?? 0) !== $idVersion) {
    $token = academico_libro_stream_token($idVersion, $uid);
}

$pdfUrl = hay_asset_url('php/libro_pdf_stream.php') . '?token=' . rawurlencode($token);
$nc = '';
$st = $pdo->prepare('SELECT numero_control FROM alumnos WHERE id_alumno = ? LIMIT 1');
$st->execute([$idAlumno]);
$nc = (string) ($st->fetchColumn() ?: '');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/libro_lector.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="libro-lector-wrap" id="libro-lector-app"
  data-pdf-url="<?php echo htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8'); ?>"
  data-watermark="<?php echo htmlspecialchars($nc, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="libro-lector-toolbar">
    <button type="button" class="secondary" onclick="cargarSeccion('alumno_mis_libros')">← Mis libros</button>
    <span class="libro-lector-title"><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></span>
    <div class="libro-lector-nav">
      <button type="button" id="libro-prev" class="secondary" title="Página anterior">‹</button>
      <span id="libro-pagina-label">—</span>
      <button type="button" id="libro-next" class="secondary" title="Página siguiente">›</button>
    </div>
  </div>
  <div class="libro-lector-canvas-wrap" id="libro-canvas-wrap">
    <canvas id="libro-canvas"></canvas>
    <div class="libro-lector-watermark" id="libro-watermark" aria-hidden="true"></div>
  </div>
  <p class="libro-lector-aviso">Solo lectura en línea · CNCM</p>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/libro_lector.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
