<?php
require_once __DIR__ . '/../config.php';
if (!rbac_puede_centro_permisos()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para administrar roles y permisos.</div>';
    return;
}

$puedeRoles = rbac_puede_administrar_roles();
$puedePersonal = rbac_usuario_puede_gestionar_privilegios();
$tab = trim($_GET['tab'] ?? ($puedeRoles ? 'roles' : 'personal'));
$idUsuarioPre = (int) ($_GET['id_usuario'] ?? 0);
if ($idUsuarioPre > 0 && $puedePersonal) {
    $tab = 'personal';
}
if (!in_array($tab, ['roles', 'personal'], true)) {
    $tab = $puedeRoles ? 'roles' : 'personal';
}
if ($tab === 'roles' && !$puedeRoles) {
    $tab = 'personal';
}
if ($tab === 'personal' && !$puedePersonal) {
    $tab = 'roles';
}

$rolesApi = hay_asset_url('php/rbac_roles_api.php');
$permApi = hay_asset_url('php/rbac_permisos_api.php');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap" id="admin-permisos-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-user-shield"></i> Roles y permisos</h2>
    <p style="color:#666; margin:0;">
      Defina qué puede hacer cada <strong>rol</strong> y, si hace falta, ajuste permisos
      <strong>personalizados</strong> para una persona sin cambiar el rol del resto del equipo.
    </p>
  </div>

  <div class="catalog-toolbar" style="margin-bottom:16px; gap:8px;">
    <?php if ($puedeRoles): ?>
    <button type="button" class="<?php echo $tab === 'roles' ? 'primary' : 'secondary'; ?>"
      onclick="cargarSeccion('admin_roles','tab=roles')">Roles del sistema</button>
    <?php endif; ?>
    <?php if ($puedePersonal): ?>
    <button type="button" class="<?php echo $tab === 'personal' ? 'primary' : 'secondary'; ?>"
      onclick="cargarSeccion('admin_roles','tab=personal')">Permisos por persona</button>
    <?php endif; ?>
  </div>

  <?php if ($tab === 'roles' && $puedeRoles): ?>
  <div id="tab-roles">
    <p style="color:#666; font-size:0.9rem;">
      Cree roles reutilizables. Al registrar personal nuevo solo elija el rol; los permisos vienen de aquí.
    </p>
    <button type="button" class="primary" id="ar-nuevo" style="margin-bottom:12px;">Nuevo rol</button>

    <div class="catalog-table-wrap hay-dt-panel" style="margin-bottom:20px;">
      <table class="catalog-table" id="ar-tabla-roles">
        <thead>
          <tr><th>Clave</th><th>Nombre</th><th>Sedes</th><th>Privilegios</th><th>Sistema</th><th>Activo</th><th></th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <div id="ar-editor" class="welcome-card" style="display:none; padding:16px;">
      <h3 id="ar-editor-titulo">Editar rol</h3>
      <input type="hidden" id="ar-id-rol" value="0">
      <div class="catalog-toolbar" style="flex-wrap:wrap;">
        <div class="field"><label>Clave (única)</label><input type="text" id="ar-clave" pattern="[a-z0-9_]+" placeholder="ej. coordinador_ventas"></div>
        <div class="field"><label>Nombre</label><input type="text" id="ar-nombre" style="min-width:220px;"></div>
        <div class="field full"><label>Descripción</label><input type="text" id="ar-desc" style="width:100%;"></div>
        <div class="field"><label><input type="checkbox" id="ar-acceso-total"> Acceso total (supervisión)</label></div>
        <div class="field"><label><input type="checkbox" id="ar-activo" checked> Activo</label></div>
        <div class="field full">
          <label>Acceso a planteles</label>
          <select id="ar-alcance-planteles" style="min-width:280px;">
            <option value="solo_usuario">Solo el plantel del usuario</option>
            <option value="lista">Sedes seleccionadas</option>
            <option value="todos">Todas las sedes</option>
          </select>
        </div>
      </div>
      <div id="ar-planteles-wrap" style="display:none; margin:12px 0;">
        <p><strong>Sedes permitidas</strong></p>
        <div id="ar-planteles-grid" style="display:flex; flex-wrap:wrap; gap:10px;"></div>
      </div>
      <div id="ar-priv-wrap">
        <p><strong>Privilegios / vistas del menú</strong></p>
        <div id="ar-priv-grid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:8px; max-height:360px; overflow:auto;"></div>
      </div>
      <div style="margin-top:12px; display:flex; gap:8px;">
        <button type="button" class="primary" id="ar-guardar">Guardar rol</button>
        <button type="button" class="secondary" id="ar-cancelar">Cancelar</button>
      </div>
      <p id="ar-msg" class="catalog-alert" style="display:none; margin-top:10px;"></p>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'personal' && $puedePersonal): ?>
  <div id="tab-personal">
    <p style="color:#666; font-size:0.9rem; margin-top:0;">
      Elija una persona, asigne su <strong>rol base</strong> y opcionalmente otorgue o deniegue vistas puntuales.
      Si tiene ajustes propios, quedará marcada como <span class="catalog-badge catalog-badge--warn">Permisos personalizados</span>.
    </p>

    <div class="catalog-toolbar" style="flex-wrap:wrap; margin-bottom:12px;">
      <div class="field">
        <label>Buscar</label>
        <input type="search" id="ap-buscar" placeholder="Nombre, usuario o correo…" style="min-width:220px;">
      </div>
      <?php if (rbac_tiene_acceso_total()): ?>
      <div class="field" style="align-self:flex-end;">
        <label><input type="checkbox" id="ap-todos-planteles"> Todos los planteles</label>
      </div>
      <?php endif; ?>
      <div class="field" style="align-self:flex-end;">
        <button type="button" class="primary" id="ap-btn-buscar">Buscar</button>
      </div>
    </div>

    <div class="catalog-table-wrap hay-dt-panel" style="margin-bottom:16px;">
      <table class="catalog-table" id="ap-tabla-personal">
        <thead>
          <tr>
            <th>Nombre</th><th>Usuario</th><th>Rol</th><th>Plantel</th><th>Estado</th><th></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <div id="ap-editor" class="welcome-card" style="display:none; padding:16px;">
      <h3 id="ap-editor-titulo">Permisos de usuario</h3>
      <input type="hidden" id="ap-id-usuario" value="0">

      <div class="catalog-toolbar" style="flex-wrap:wrap; align-items:flex-end;">
        <div class="field">
          <label>Rol base</label>
          <select id="ap-id-rol" style="min-width:240px;"></select>
        </div>
        <button type="button" class="primary" id="ap-guardar-rol">Aplicar rol</button>
        <button type="button" class="secondary" id="ap-limpiar-custom">Quitar personalizados</button>
      </div>

      <p id="ap-rol-hint" style="font-size:0.88rem; color:#666; margin:8px 0 12px;"></p>

      <p><strong>Ajustes individuales</strong> <small>(+ otorgar / − denegar respecto al rol)</small></p>
      <div id="ap-priv-grid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:10px; max-height:420px; overflow:auto;"></div>

      <div style="margin-top:12px; display:flex; gap:8px;">
        <button type="button" class="primary" id="ap-guardar-priv">Guardar permisos personalizados</button>
        <button type="button" class="secondary" id="ap-cancelar">Cerrar</button>
      </div>
      <p id="ap-msg" class="catalog-alert" style="display:none; margin-top:10px;"></p>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
window.__hayAdminPermisos = {
  tab: <?php echo json_encode($tab, JSON_UNESCAPED_UNICODE); ?>,
  rolesApi: <?php echo json_encode($rolesApi, JSON_UNESCAPED_UNICODE); ?>,
  permApi: <?php echo json_encode($permApi, JSON_UNESCAPED_UNICODE); ?>,
  puedeRoles: <?php echo $puedeRoles ? 'true' : 'false'; ?>,
  puedePersonal: <?php echo $puedePersonal ? 'true' : 'false'; ?>,
  idUsuario: <?php echo $idUsuarioPre; ?>
};
</script>
<?php if ($tab === 'roles' && $puedeRoles): ?>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/admin_roles.js?v=20260610'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
if (window.HayAdminRoles && window.__hayAdminPermisos) {
  window.HayAdminRoles.init({ api: window.__hayAdminPermisos.rolesApi });
}
</script>
<?php endif; ?>
<?php if ($tab === 'personal' && $puedePersonal): ?>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/admin_permisos_personal.js?v=20260610'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
if (window.HayAdminPermisosPersonal) window.HayAdminPermisosPersonal.init(window.__hayAdminPermisos);
</script>
<?php endif; ?>
