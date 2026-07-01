<?php
require_once __DIR__ . '/../config.php';
if (!ventas_comision_puede_administrar()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso. Solo supervisor o gerente pueden configurar comisiones.</div>';
    return;
}
$puedeEditar = ventas_comision_puede_editar();
$api = hay_asset_url('php/ventas_comision_api.php');
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<div class="catalog-wrap" id="vc-admin-wrap"<?php echo $puedeEditar ? '' : ' data-solo-lectura="1"'; ?>>
  <div class="catalog-header">
    <h2><i class="fas fa-sliders-h"></i> Comisiones y tabuladores</h2>
    <p style="color:#666; margin:0;">
      <?php if ($puedeEditar): ?>
      Configure reglas por especialidad, tabulador de sueldo base e autorizaciones temporales. Cada cambio de reglas queda en historial.
      <?php else: ?>
      Consulta de reglas y tabuladores (solo lectura). Puede ocultar la comisión del gerente al mostrar el esquema a un asesor.
      <?php endif; ?>
    </p>
  </div>

  <div id="vc-admin-msg" class="catalog-alert" hidden></div>

  <div class="catalog-toolbar" style="margin-bottom:12px; flex-wrap:wrap; gap:12px;">
    <label class="vc-ocultar-gerente" style="display:flex; align-items:center; gap:8px; cursor:pointer;">
      <input type="checkbox" id="vc-ocultar-com-gerente">
      Ocultar comisión del gerente (vista para asesor)
    </label>
  </div>

  <div class="catalog-tabs" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
    <button type="button" class="primary vc-tab active" data-tab="reglas">Especialidades</button>
    <button type="button" class="vc-tab" data-tab="tabulador">Tabulador sueldo</button>
    <button type="button" class="vc-tab" data-tab="override">Autorizaciones</button>
    <button type="button" class="vc-tab" data-tab="gerente" id="vc-tab-gerente">Sobrecomisión gerente</button>
  </div>

  <section id="vc-panel-reglas">
    <div class="catalog-table-wrap hay-dt-panel">
      <table class="catalog-table" id="vc-tabla-esp">
        <thead>
          <tr>
            <th>Especialidad</th>
            <th>Comisión asesor</th>
            <th class="vc-col-gerente">Sobrecom. gerente</th>
            <th>Cuenta tabulador</th>
            <th>Tipo</th>
            <th></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </section>

  <section id="vc-panel-tabulador" style="display:none;">
    <div class="catalog-toolbar">
      <button type="button" class="primary" id="vc-nuevo-tabulador">Nuevo tabulador</button>
    </div>
    <div class="catalog-table-wrap">
      <table class="catalog-table" id="vc-tabla-tab">
        <thead>
          <tr><th>Nombre</th><th>Periodo</th><th>Vigente</th><th>Tramos</th><th></th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div id="vc-tabulador-form" class="vc-tabulador-panel" hidden style="background:#f9f9f9; padding:16px; border-radius:8px; margin-top:12px;">
      <h3>Nuevo tabulador de sueldo base</h3>
      <div class="field"><label>Nombre</label><input type="text" id="vc-tab-nombre" style="width:100%;"></div>
      <div class="field"><label>Periodo de conteo</label>
        <select id="vc-tab-periodo"><option value="semana">Semana</option><option value="mes">Mes</option><option value="dia">Día</option></select>
      </div>
      <div class="field"><label>Vigente desde</label><input type="date" id="vc-tab-desde"></div>
      <div class="field"><label><input type="checkbox" id="vc-tab-cerrar" checked> Cerrar tabuladores anteriores del mismo periodo</label></div>
      <p><strong>Tramos (inscripciones → sueldo base)</strong></p>
      <div id="vc-tramos"></div>
      <button type="button" id="vc-add-tramo">+ Tramo</button>
      <div style="margin-top:12px;"><button type="button" class="primary" id="vc-guardar-tabulador">Guardar tabulador</button></div>
    </div>
  </section>

  <section id="vc-panel-override" style="display:none;">
    <div id="vc-override-form" style="background:#f9f9f9; padding:16px; border-radius:8px; margin-bottom:12px;">
      <h3>Autorización especial (semana o día)</h3>
      <div class="catalog-toolbar">
        <div class="field"><label>Asesor (vacío = todos)</label><select id="vc-ov-asesor"><option value="">— Todos —</option></select></div>
        <div class="field"><label>Desde</label><input type="date" id="vc-ov-desde"></div>
        <div class="field"><label>Hasta</label><input type="date" id="vc-ov-hasta"></div>
        <div class="field"><label>Periodo</label><select id="vc-ov-periodo"><option value="semana">Semana</option><option value="dia">Día</option></select></div>
        <div class="field"><label>Tabulador temporal</label><select id="vc-ov-tab"><option value="">— Seleccione —</option></select></div>
        <div class="field"><label>Motivo</label><input type="text" id="vc-ov-motivo" style="min-width:200px;"></div>
        <button type="button" class="primary" id="vc-guardar-override">Registrar</button>
      </div>
    </div>
    <table class="catalog-table" id="vc-tabla-ov"><thead><tr><th>Asesor</th><th>Rango</th><th>Tabulador</th><th>Motivo</th></tr></thead><tbody></tbody></table>
  </section>

  <section id="vc-panel-gerente" style="display:none;">
    <div class="catalog-toolbar">
      <select id="vc-ger-periodo"><option value="semana">Semana</option><option value="mes">Mes</option></select>
      <input type="date" id="vc-ger-fecha">
      <button type="button" class="primary" id="vc-ger-buscar">Ver</button>
    </div>
    <div id="vc-ger-resumen"></div>
    <table class="catalog-table" id="vc-tabla-ger"><thead><tr><th>Asesor</th><th>Ops</th><th>Sobrecomisión</th></tr></thead><tbody></tbody></table>
  </section>

  <div class="catalog-modal" id="vc-modal-regla">
    <div class="catalog-modal__panel">
      <h3 id="vc-regla-titulo">Reglas de comisión</h3>
      <input type="hidden" id="vc-regla-id">
      <label>Tipo comisión</label>
      <select id="vc-regla-tipo" style="width:100%; margin:6px 0;">
        <option value="fija">Fija ($)</option>
        <option value="pct_inscripcion">% del monto inscrito</option>
        <option value="personalizado_pct">Personalizado (10% automático)</option>
      </select>
      <label>Comisión asesor ($)</label><input type="number" id="vc-regla-ca" step="0.01" style="width:100%;">
      <label>% asesor</label><input type="number" id="vc-regla-cap" step="0.01" style="width:100%;">
      <div class="vc-field-gerente">
      <label>Sobrecomisión gerente ($)</label><input type="number" id="vc-regla-cg" step="0.01" style="width:100%;">
      <label>% gerente</label><input type="number" id="vc-regla-cgp" step="0.01" style="width:100%;">
      </div>
      <label><input type="checkbox" id="vc-regla-tab"> Cuenta para tabulador de sueldo base</label>
      <label>Motivo del cambio</label><input type="text" id="vc-regla-motivo" style="width:100%;">
      <div style="margin-top:12px; display:flex; gap:8px;">
        <button type="button" class="primary" id="vc-regla-guardar">Guardar</button>
        <button type="button" id="vc-regla-cerrar">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>window.__hayVentasComisionAdmin = { api: <?php echo json_encode($api, JSON_UNESCAPED_UNICODE); ?>, soloLectura: <?php echo $puedeEditar ? 'false' : 'true'; ?> };</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/ventas_comisiones_admin.js?v=20260612'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>if (window.hayVentasComisionAdminInit) window.hayVentasComisionAdminInit();</script>
