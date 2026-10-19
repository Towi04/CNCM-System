<?php
require_once __DIR__ . '/../config.php';
if (!migracion_csv_puede()) {
    echo '<div class="alert">Solo supervisión, gerencia, dirección o admin puede usar la migración CSV.</div>';
    return;
}
migracion_csv_ensure_schema($pdo);
$apiUrl = hay_asset_url('php/migracion_csv_api.php');
$idPlantel = (int) plantel_id_activo();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-file-csv"></i> Migración CSV (legado → HAY)</h2>
    <p style="color:#666; margin:0; max-width:900px;">
      Suba los CSV generados con el extractor local (<code>tools/migracion_csv/</code> en XAMPP).
      Orden recomendado: <strong>personal → aulas → grupos → alumnos → pagos → provisión de cuentas</strong>.
      Los kids crean pareja IK+CK; la fase se toma de <code>clave_fase_estimada</code> / fecha de inicio.
    </p>
  </div>

  <div id="migcsv-estado" class="catalog-alert" style="display:block; margin-bottom:12px;">Cargando estado…</div>
  <div id="migcsv-msg" class="catalog-alert" style="display:none; margin-bottom:12px;"></div>

  <div class="catalog-toolbar" style="flex-wrap:wrap; gap:12px; align-items:flex-end;">
    <div class="field">
      <label>Tipo de CSV</label>
      <select id="migcsv-tipo">
        <option value="personal">01 Personal</option>
        <option value="aulas">02 Aulas</option>
        <option value="grupos">03 Grupos activos</option>
        <option value="alumnos">04 Alumnos</option>
        <option value="pagos">05 Pagos</option>
      </select>
    </div>
    <div class="field">
      <label>Archivo CSV</label>
      <input type="file" id="migcsv-file" accept=".csv,text/csv">
    </div>
    <button type="button" class="secondary" id="migcsv-preview"><i class="fas fa-search"></i> Vista previa</button>
    <button type="button" class="primary" id="migcsv-import"><i class="fas fa-upload"></i> Importar</button>
    <button type="button" class="secondary" id="migcsv-provision" title="Google Workspace + Moodle para registros mapeados">
      <i class="fas fa-user-plus"></i> Provisionar correos / Moodle
    </button>
  </div>

  <div class="catalog-table-wrap" style="margin-top:14px;">
    <table class="catalog-table" id="migcsv-tabla">
      <thead><tr id="migcsv-thead"><th>Vista previa</th></tr></thead>
      <tbody id="migcsv-tbody">
        <tr><td style="color:#888;">Seleccione un CSV y pulse Vista previa.</td></tr>
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px; font-size:0.9rem; color:#555; max-width:900px;">
    <p><strong>Claves especialidad:</strong>
      <code>ING</code>, <code>ING-EXT</code>, <code>ING-K</code>, <code>COMP</code>/<code>COMP24</code>/<code>COMP25</code>,
      <code>COMP-EXT</code>, <code>COMP-K</code>, <code>PREP-AB</code>, <code>PREP-ESC</code>.
    </p>
    <p><strong>Días:</strong> <code>0</code>=domingo … <code>6</code>=sábado (ej. <code>6</code> o <code>2,4</code>).</p>
    <p>
      Extractor local:
      <a href="<?php echo htmlspecialchars(hay_asset_url('tools/migracion_csv/'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
        tools/migracion_csv/
      </a>
      (en el servidor solo sirve de documentación; ejecútelo en XAMPP contra el legado).
    </p>
  </div>
</div>

<script>
window.HAY_MIGCSV = <?php echo json_encode([
    'api' => $apiUrl,
    'id_plantel' => $idPlantel,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/migracion_csv.js?v=20261019'), ENT_QUOTES, 'UTF-8'); ?>"></script>
