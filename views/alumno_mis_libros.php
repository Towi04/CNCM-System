<?php
require_once __DIR__ . '/../config.php';
if (!alumno_portal_puede_ver()) {
    echo '<div class="alert">Portal solo para alumnos.</div>';
    return;
}

$idAlumno = alumno_portal_id_o_detener();
if ($idAlumno <= 0) {
    return;
}

$libros = academico_libro_listar_alumno($pdo, $idAlumno);
$uid = (int) ($_SESSION['user_id'] ?? 0);
$streamBase = hay_asset_url('php/libro_pdf_stream.php');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-book-open"></i> Mis libros</h2>
    <p style="color:#666;">Lectura en línea de tus materiales CNCM. No está disponible la descarga ni la impresión desde aquí.</p>
  </div>

  <button type="button" class="secondary" style="margin-bottom:12px;" onclick="cargarSeccion('alumno_portal_inicio')">← Inicio</button>

  <?php if ($libros === []): ?>
    <div class="welcome-card" style="padding:16px;">
      <p style="margin:0; color:#666;">Aún no hay libros activos para tu especialidad. Si necesitas el material, contacta a tu plantel.</p>
    </div>
  <?php else: ?>
    <div style="display:grid; gap:12px;">
      <?php foreach ($libros as $lb):
        $token = academico_libro_stream_token((int) $lb['id_version'], $uid);
        $tipoLabel = match ($lb['tipo'] ?? '') {
            'studentbook' => 'Student book',
            'workbook' => 'Workbook',
            default => (string) ($lb['tipo'] ?? 'Libro'),
        };
      ?>
      <div class="welcome-card" style="padding:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
          <strong><?php echo htmlspecialchars($lb['titulo'] ?? ''); ?></strong>
          <div style="color:#666; font-size:0.9rem;">
            <?php echo htmlspecialchars($lb['esp_nombre'] ?? ''); ?>
            · <?php echo htmlspecialchars($tipoLabel); ?>
            · Versión <?php echo htmlspecialchars($lb['etiqueta'] ?? ''); ?>
            <?php if (!empty($lb['num_paginas'])): ?> · <?php echo (int) $lb['num_paginas']; ?> págs.<?php endif; ?>
            <?php if (!empty($lb['pagina_inicio_workbook'])): ?>
              <br><span style="font-size:0.85rem;">Incluye Workbook desde pág. <?php echo (int) $lb['pagina_inicio_workbook']; ?></span>
            <?php endif; ?>
          </div>
        </div>
        <button type="button" class="primary btn-abrir-libro"
          data-version="<?php echo (int) $lb['id_version']; ?>"
          data-titulo="<?php echo htmlspecialchars($lb['titulo'] ?? 'Libro', ENT_QUOTES, 'UTF-8'); ?>"
          data-token="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
          <i class="fas fa-eye"></i> Leer en línea
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.btn-abrir-libro').forEach((btn) => {
  btn.addEventListener('click', () => {
    const q = new URLSearchParams({
      id_version: btn.dataset.version,
      titulo: btn.dataset.titulo || 'Libro',
      token: btn.dataset.token,
    });
    cargarSeccion('alumno_libro_lector', q.toString());
  });
});
</script>
