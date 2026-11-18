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
      Para personal activo use <strong>Personal por correos</strong> (email + plantel): completa nombre desde Google o legado,
      crea el usuario en HAY, crea la cuenta en Google Workspace si no existe, y provisiona Moodle.
      Después podrá importar alumnos activos por grupos.
    </p>
  </div>

  <div id="migcsv-estado" class="catalog-alert" style="display:block; margin-bottom:12px;">Cargando estado…</div>
  <div id="migcsv-msg" class="catalog-alert" style="display:none; margin-bottom:12px;"></div>

  <div class="catalog-toolbar" style="flex-wrap:wrap; gap:12px; align-items:flex-end;">
    <div class="field">
      <label>Tipo de CSV</label>
      <select id="migcsv-tipo">
        <option value="staff_emails">Personal por correos (recomendado)</option>
        <option value="personal">01 Personal (legado completo)</option>
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
    <button type="button" class="secondary" id="migcsv-template" title="Descargar plantilla CSV">
      <i class="fas fa-download"></i> Plantilla correos
    </button>
    <button type="button" class="secondary" id="migcsv-provision" title="Google Workspace + Moodle para registros mapeados">
      <i class="fas fa-user-plus"></i> Re-provisionar mapeados
    </button>
  </div>

  <div class="catalog-toolbar" style="margin-top:8px; gap:10px; align-items:center; flex-wrap:wrap;">
    <label for="migcsv-page-size" style="margin:0;">Filas por página</label>
    <select id="migcsv-page-size">
      <option value="25">25</option>
      <option value="50" selected>50</option>
      <option value="100">100</option>
    </select>
    <button type="button" class="secondary" id="migcsv-prev" disabled>← Anterior</button>
    <span id="migcsv-pager-info" style="color:#555; font-size:0.9rem;"></span>
    <button type="button" class="secondary" id="migcsv-next" disabled>Siguiente →</button>
  </div>

  <div class="catalog-table-wrap" style="margin-top:14px; overflow-x:auto;">
    <table class="catalog-table" id="migcsv-tabla" style="width:100%; min-width:960px; table-layout:auto; border-collapse:collapse;">
      <thead id="migcsv-thead">
        <tr><th>Vista previa</th></tr>
      </thead>
      <tbody id="migcsv-tbody">
        <tr><td style="color:#888;">Seleccione un CSV y pulse Vista previa.</td></tr>
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px; font-size:0.9rem; color:#555; max-width:900px;">
    <p><strong>Personal por correos:</strong> columnas mínimas <code>email</code>, <code>plantel_slug</code>.
      Opcionales: <code>rol</code> (default <code>profesor</code>), <code>nombres</code>, <code>apellido_paterno</code>, <code>apellido_materno</code>.
      Orden del nombre: CSV → Google → legado → inferido del correo (<code>fuente_nombre=email</code>).
      En la tabla puede editar <strong>rol</strong>, <strong>nombres</strong> y <strong>apellido</strong> antes de Importar (útil si Google va a crear la cuenta).
    </p>
    <p><strong>Al Importar:</strong> crea/actualiza usuario en HAY, crea Google si no existe (con esos nombres) y provisiona Moodle.
    </p>
    <p><strong>Columna Google:</strong>
      <code>existe</code> = ya hay cuenta Workspace;
      <code>crear</code> = se creará al importar;
      <code>error: …</code> = fallo de API (no confundir con “ya existe”).
    </p>
    <p><strong>Planteles (slug):</strong>
      <?php
        $slugs = [];
        if (function_exists('plantel_list')) {
            foreach (plantel_list($pdo, true) as $p) {
                $slugs[] = '<code>' . htmlspecialchars($p['slug'], ENT_QUOTES, 'UTF-8') . '</code>';
            }
        }
        echo $slugs !== [] ? implode(', ', $slugs) : 'configure planteles en HAY';
      ?>
    </p>
    <p><strong>Claves especialidad (alumnos/grupos):</strong>
      <code>ING</code>, <code>ING-EXT</code>, <code>ING-K</code>, <code>COMP</code>/<code>COMP24</code>/<code>COMP25</code>,
      <code>COMP-EXT</code>, <code>COMP-K</code>, <code>PREP-AB</code>, <code>PREP-ESC</code>.
    </p>
  </div>
</div>

<script>
window.HAY_MIGCSV = <?php
$migRoles = function_exists('migracion_csv_staff_roles_opciones')
    ? migracion_csv_staff_roles_opciones()
    : ['profesor', 'asesor', 'gerente', 'coordinador', 'recepcion', 'imagen', 'marketing', 'admin', 'director', 'supervisor', 'manuales'];
$migLabels = function_exists('rbac_roles_etiquetas') ? rbac_roles_etiquetas() : [];
$migRoleLabels = [];
foreach ($migRoles as $claveRol) {
    $migRoleLabels[$claveRol] = $migLabels[$claveRol] ?? $claveRol;
}
echo json_encode([
    'api' => $apiUrl,
    'id_plantel' => $idPlantel,
    'roles' => $migRoles,
    'role_labels' => $migRoleLabels,
], JSON_UNESCAPED_UNICODE);
?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/migracion_csv.js?v=20261117d'), ENT_QUOTES, 'UTF-8'); ?>"></script>
