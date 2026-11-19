<?php
require_once __DIR__ . '/../config.php';
if (!migracion_csv_puede()) {
    echo '<div class="alert">Solo supervisión, gerencia, dirección o admin puede usar la migración CSV.</div>';
    return;
}
migracion_csv_ensure_schema($pdo);
$apiUrl = hay_asset_url('php/migracion_csv_api.php');
$idPlantel = (int) plantel_id_activo();
$slugsTxt = '';
if (function_exists('plantel_list')) {
    $parts = [];
    foreach (plantel_list($pdo, true) as $p) {
        $parts[] = '<code>' . htmlspecialchars((string) $p['slug'], ENT_QUOTES, 'UTF-8') . '</code>';
    }
    $slugsTxt = $parts !== [] ? implode(', ', $parts) : 'configure planteles en HAY';
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-file-csv"></i> Migración CSV (legado → HAY)</h2>
    <p style="color:#666; margin:0; max-width:900px;">
      Descargue del legado en el servidor (o suba un CSV), revise con <strong>Vista previa</strong> e
      <strong>Importar</strong> en orden: personal → aulas → grupos → alumnos → pagos.
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
    <button type="button" class="secondary" id="migcsv-template" title="Descargar CSV de ejemplo del tipo seleccionado">
      <i class="fas fa-download"></i> <span id="migcsv-template-label">CSV de ejemplo</span>
    </button>
    <button type="button" class="secondary" id="migcsv-provision" title="Google Workspace + Moodle para registros mapeados">
      <i class="fas fa-user-plus"></i> Re-provisionar mapeados
    </button>
    <button type="button" class="secondary" id="migcsv-revertir-grupos" title="Borra grupos del plantel (mapa o TODOS) y reinicia el contador de claves" style="border-color:#c47b00; color:#8a5a00;">
      <i class="fas fa-undo"></i> Revertir / vaciar grupos
    </button>
  </div>

  <div class="catalog-card" style="margin-top:14px; padding:14px 16px; border:1px solid #dde3ea; border-radius:10px; background:#fff; max-width:960px;">
    <h3 style="margin:0 0 8px; font-size:1rem;"><i class="fas fa-database"></i> Descargar desde BD legado (servidor)</h3>
    <p id="migcsv-legado-status" style="margin:0 0 10px; color:#666; font-size:0.9rem;">Comprobando LEGACY_DB_*…</p>
    <div class="catalog-toolbar" style="flex-wrap:wrap; gap:12px; align-items:flex-end; margin:0;">
      <div class="field" style="flex:1; min-width:220px;">
        <label for="migcsv-filtro">Claves de grupo (una por línea)</label>
        <textarea id="migcsv-filtro" rows="4" placeholder="IKCF0085&#10;IKCF0098&#10;IS100" style="width:100%; min-width:220px; font-family:ui-monospace,monospace; font-size:0.85rem;"></textarea>
      </div>
      <div class="field" style="flex:1; min-width:220px;">
        <label for="migcsv-filtro-alumnos">Alumnos para pagos (<code>id_alumno_legado</code>)</label>
        <textarea id="migcsv-filtro-alumnos" rows="4" placeholder="9001&#10;9002&#10;9003" style="width:100%; min-width:220px; font-family:ui-monospace,monospace; font-size:0.85rem;"></textarea>
        <label style="margin-top:6px; font-size:0.8rem; color:#666;">
          O cargar CSV de alumnos:
          <input type="file" id="migcsv-filtro-alumnos-file" accept=".csv,text/csv" style="display:block; margin-top:4px; font-size:0.8rem;">
        </label>
      </div>
      <div class="field">
        <label for="migcsv-sucursal">Sucursal legado (opcional)</label>
        <select id="migcsv-sucursal">
          <option value="0">Todas</option>
        </select>
      </div>
      <button type="button" class="primary" id="migcsv-export-legado" title="Genera el CSV del tipo seleccionado desde la BD legado del servidor">
        <i class="fas fa-cloud-download-alt"></i> Descargar del legado
      </button>
    </div>
    <p style="margin:10px 0 0; color:#777; font-size:0.85rem;">
      Requiere <code>LEGACY_DB_*</code>. Grupos/alumnos: pegue las <strong>claves</strong> de grupo
      (como en el legado). Pagos: basta pegar <code>id_alumno_legado</code>
      (o subir el CSV de alumnos ya descargado).
    </p>
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
    <p style="margin:0 0 8px;">
      <strong>CSV de ejemplo:</strong> el botón de arriba descarga columnas + 2–3 filas de muestra
      según el tipo seleccionado. Planteles válidos: <?php echo $slugsTxt; ?>.
    </p>
    <p style="margin:0 0 8px; color:#777;">
      En grupos, <code>clave_especialidad</code> es el catálogo (<code>ING</code>/<code>COMP</code>/<code>ING-K</code>
      o atajos <code>I</code>/<code>C</code>/<code>IK</code>/<code>CK</code>/<code>PA</code>).
      La clave de grupo (<code>IS1</code>, <code>CK3</code>…) la genera HAY.
      <code>infantil</code>: <code>0</code> adulto, <code>1</code> kids. Fechas: <code>YYYY-MM-DD</code> o <code>DD/MM/YYYY</code>.
    </p>
    <p style="margin:0 0 8px; color:#777;">
      <strong>Alumnos:</strong> no se migran solos con los grupos. Use el <em>mismo</em> filtro
      de <code>clave_legado</code> con el que importó <code>03_grupos</code>, descargue
      <code>04_alumnos</code> → Vista previa (<code>grupo_estado</code>) → Importar.
      El enlace es por <strong>clave de grupo</strong>. Si falta una clave en el mapa, vuelva a
      descargar/importar <code>03_grupos</code> incluyendo esa clave.
    </p>
    <p style="margin:0; color:#777;">
      Al importar alumnos se crea (si falta) usuario HAY + Google + Moodle
      (mismas reglas: NC / correo institucional).
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
<script src="<?php echo htmlspecialchars(hay_asset_url('js/migracion_csv.js?v=20261119d'), ENT_QUOTES, 'UTF-8'); ?>"></script>
