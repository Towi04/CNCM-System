<?php
require_once __DIR__ . '/../config.php';
if (!asesor_puede_grupos_fases()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso.</div>';
    return;
}
$especialidades = $pdo->query('SELECT id_especialidad, nombre FROM especialidades WHERE activo = 1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-search"></i> Grupos por fase</h2>
    <p style="color:#666; margin:0;">Seleccione la fase que busca (ej. Excel) y vea qué grupos la cursan ahora o la iniciarán, con fecha, horario y profesor.</p>
  </div>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Especialidad</label>
      <select id="gf-esp">
        <option value="">— Elija —</option>
        <?php foreach ($especialidades as $e): ?>
        <option value="<?php echo (int)$e['id_especialidad']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="flex:1;">
      <label>Fase</label>
      <select id="gf-fase" disabled><option value="">Primero especialidad</option></select>
    </div>
    <button type="button" class="primary" id="gf-buscar">Buscar</button>
  </div>

  <p id="gf-resumen" style="color:#666;"></p>
  <div class="catalog-table-wrap hay-dt-panel gf-tabla-panel">
    <table class="catalog-table gf-tabla" id="gf-tabla">
      <thead>
        <tr>
          <th>Grupo</th><th>Especialidad</th><th>Fase</th><th>Estado</th>
          <th>Inicia fase</th><th>Inicio grupo</th><th>Horario</th><th>Profesor</th><th>Aula</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>
<script>
window.HAY_GRUPOS_FASES = <?php echo json_encode(['api' => hay_asset_url('php/asesor_grupos_fases_api.php')], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/asesor_grupos_fases.js?v=20260604'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<style>
.gf-tabla-panel .dataTables_wrapper { width: 100%; }
.gf-tabla { table-layout: auto; width: 100% !important; }
.gf-tabla th, .gf-tabla td { white-space: nowrap; vertical-align: middle; }
</style>
