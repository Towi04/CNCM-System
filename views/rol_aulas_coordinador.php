<?php
require_once __DIR__ . '/../config.php';
if (!rol_aula_puede_gestionar()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para gestionar el rol de aulas.</div>';
    return;
}

rol_aula_ensure_schema($pdo);
$plantelNombre = $_SESSION['plantel_nombre'] ?? 'Plantel';
$mesActual = (int) date('n');
$anioActual = (int) date('Y');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/rol_aulas.css?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap rol-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-th-large"></i> Rol de aulas — <?php echo htmlspecialchars($plantelNombre); ?></h2>
    <p style="color:#666;">Genere la asignación mensual de grupos a aulas, ajuste manualmente y publique cuando no haya conflictos.</p>
  </div>

  <div class="catalog-toolbar rol-toolbar">
    <div>
      <label>Mes</label>
      <select id="rol-mes">
        <?php
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        for ($m = 1; $m <= 12; $m++):
        ?>
        <option value="<?php echo $m; ?>"<?php echo $m === $mesActual ? ' selected' : ''; ?>><?php echo $meses[$m]; ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div>
      <label>Año</label>
      <input type="number" id="rol-anio" value="<?php echo $anioActual; ?>" min="2020" max="2100" style="width:90px;">
    </div>
    <div style="align-self:flex-end;display:flex;gap:8px;flex-wrap:wrap;">
      <button type="button" class="primary" id="btn-rol-generar"><i class="fas fa-magic"></i> Generar rol</button>
      <button type="button" class="secondary" id="btn-rol-validar"><i class="fas fa-check-circle"></i> Validar</button>
      <button type="button" class="primary" id="btn-rol-publicar" style="background:#1b5e20;"><i class="fas fa-bullhorn"></i> Publicar</button>
      <button type="button" class="secondary" id="btn-rol-pdf"><i class="fas fa-file-pdf"></i> Exportar PDF</button>
    </div>
  </div>

  <div id="rol-estado" class="rol-estado"></div>
  <div id="rol-conflictos" class="rol-conflictos" hidden></div>

  <div class="rol-layout">
    <div class="rol-panel">
      <h3>Grupos</h3>
      <p class="rol-hint">Arrastre un grupo sobre un aula o use el selector para reasignar.</p>
      <div id="rol-grupos" class="rol-lista"></div>
    </div>
    <div class="rol-panel rol-panel--aulas">
      <h3>Aulas y asignaciones</h3>
      <div id="rol-aulas" class="rol-aulas-grid"></div>
      <div class="rol-sin-aula">
        <h4>Sin aula asignada</h4>
        <div id="rol-pendientes" class="rol-dropzone" data-aula=""></div>
      </div>
    </div>
  </div>
</div>

<script>
window.HAY_ROL_AULAS_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/rol_aula_api.php'),
    'pdf' => hay_asset_url('php/rol_aula_pdf.php'),
    'mes' => $mesActual,
    'anio' => $anioActual,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/rol_aulas_coordinador.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>
