<?php
require_once __DIR__ . '/../config.php';
global $pdo;
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$profesorId = (int)($_SESSION['user_id'] ?? 0);
if ($profesorId <= 0) { echo "<div class='alert'>Sesión no iniciada.</div>"; return; }

// Semana/ año según regla: semana inicia domingo (MySQL WEEK(date,0))
$hoy = new DateTime('now');
$anio = (int)$hoy->format('Y');
$semana = (int)$hoy->format('W'); // fallback visual; el guardado usa "semana" seleccionada

// Permitimos seleccionar manualmente (por GET) para navegar entre semanas
$anioSel = isset($_GET['anio']) ? (int)$_GET['anio'] : $anio;
$semSel = isset($_GET['semana']) ? (int)$_GET['semana'] : 0;
if ($semSel <= 0) {
  // semana "escuela": usamos WEEK(CURDATE(),0) consultando MySQL para ser exactos con domingo
  try {
    $w = $pdo->query("SELECT WEEK(CURDATE(), 0)")->fetchColumn();
    $semSel = (int)$w;
  } catch (Throwable $e) {
    $semSel = (int)$hoy->format('W');
  }
}

?>
<link rel="stylesheet" href="css/resultados.css">

<div class="result-container">
  <div class="result-header">
    <h2>Asesorías · Disponibilidad</h2>
    <p style="margin:0; color:#666; font-weight:700;">Selecciona la semana (la escuela inicia semana en domingo).</p>
    <div class="disc-actions">
      <button type="button" onclick="cargarSeccion('grupos')">Volver</button>
    </div>
  </div>

  <div class="patron-desc">
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
      <div>
        <label><strong>Año</strong></label><br>
        <input id="ase-anio" type="number" value="<?php echo (int)$anioSel; ?>" min="2020" max="2100" style="width:140px; padding:10px; border:1px solid #ddd; border-radius:10px;">
      </div>
      <div>
        <label><strong>Semana</strong></label><br>
        <input id="ase-semana" type="number" value="<?php echo (int)$semSel; ?>" min="0" max="53" style="width:140px; padding:10px; border:1px solid #ddd; border-radius:10px;">
      </div>
      <button class="primary" type="button" onclick="cargarGrid()">Cargar</button>
    </div>

    <div id="ase-grid" style="margin-top:14px;"></div>
  </div>
</div>

<script>
  function cargarGrid() {
    const anio = Number(document.getElementById('ase-anio').value || 0);
    const semana = Number(document.getElementById('ase-semana').value || 0);
    const out = document.getElementById('ase-grid');
    out.innerHTML = 'Cargando...';
    fetch('views/asesorias_grid.php?anio=' + encodeURIComponent(anio) + '&semana=' + encodeURIComponent(semana) + '&t=' + Date.now(), { cache: 'no-store' })
      .then(r => r.text())
      .then(html => { out.innerHTML = html; })
      .catch(() => { out.innerHTML = 'Error al cargar.'; });
  }
  cargarGrid();
</script>

