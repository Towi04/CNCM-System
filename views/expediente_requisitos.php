<?php
require_once __DIR__ . '/../config.php';
if (!expediente_documental_puede_configurar()) {
    echo '<div class="alert">Solo supervisión puede configurar requisitos documentales.</div>';
    return;
}
$rows = expediente_documental_listar_requisitos_admin($pdo);
$cats = expediente_documental_categorias();
$tipos = expediente_documental_tipos_verificacion();
$apiUrl = hay_asset_url('php/expediente_documental_api.php');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css')); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css')); ?>">

<div class="catalog-wrap" id="exp-req-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-clipboard-list"></i> Requisitos documentales</h2>
    <p style="color:#666;">Defina qué documentos debe entregar cada tipo de persona (candidatos, profesores, alumnos SEP, personal).</p>
    <button type="button" class="primary" id="exp-req-nuevo">Nuevo requisito</button>
  </div>

  <div id="exp-req-msg" class="catalog-alert" style="display:none;"></div>

  <table class="catalog-table">
    <thead>
      <tr>
        <th>Clave</th><th>Nombre</th><th>Categoría</th><th>Tipo</th><th>Curso Moodle</th><th>Oblig.</th><th>Activo</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr data-id="<?php echo (int) $r['id_requisito']; ?>">
        <td><?php echo htmlspecialchars($r['clave']); ?></td>
        <td><?php echo htmlspecialchars($r['nombre']); ?></td>
        <td><?php echo htmlspecialchars($cats[$r['categoria']] ?? $r['categoria']); ?></td>
        <td><?php echo htmlspecialchars($tipos[$r['tipo_verificacion']] ?? $r['tipo_verificacion']); ?></td>
        <td><?php echo (int) ($r['moodle_course_id'] ?? 0) ?: '—'; ?></td>
        <td><?php echo (int) ($r['obligatorio'] ?? 0) ? 'Sí' : 'No'; ?></td>
        <td><?php echo (int) ($r['activo'] ?? 0) ? 'Sí' : 'No'; ?></td>
        <td><button type="button" class="secondary exp-req-edit" data-row='<?php echo htmlspecialchars(json_encode($r, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>'>Editar</button></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <dialog id="exp-req-dialog" style="max-width:560px;width:95%;padding:16px;border-radius:10px;">
    <form id="exp-req-form" data-no-global-ajax="1">
      <input type="hidden" name="action" value="save_requisito">
      <input type="hidden" name="id_requisito" id="exp-req-id" value="0">
      <h3 id="exp-req-titulo">Requisito</h3>
      <div style="display:grid;gap:8px;">
        <input name="clave" id="exp-req-clave" placeholder="Clave (ej. CERT_CONOCIMIENTO)" required>
        <input name="nombre" id="exp-req-nombre" placeholder="Nombre visible" required>
        <textarea name="descripcion" id="exp-req-desc" placeholder="Instrucciones para quien sube" rows="3"></textarea>
        <select name="categoria" id="exp-req-cat">
          <?php foreach ($cats as $k => $v): ?>
          <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
          <?php endforeach; ?>
        </select>
        <select name="tipo_verificacion" id="exp-req-tipo">
          <?php foreach ($tipos as $k => $v): ?>
          <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
          <?php endforeach; ?>
        </select>
        <input name="moodle_course_id" id="exp-req-moodle" type="number" min="0" placeholder="ID curso Moodle (examen alternativo)">
        <input name="umbral_aprobacion" id="exp-req-umbral" type="number" step="0.01" placeholder="Umbral aprobación (ej. 70)">
        <input name="orden" id="exp-req-orden" type="number" placeholder="Orden">
        <input name="roles_json" id="exp-req-roles" placeholder="Roles extra (coma): profesor,coordinador">
        <label><input type="checkbox" name="obligatorio" id="exp-req-oblig" value="1" checked> Obligatorio</label>
        <label><input type="checkbox" name="activo" id="exp-req-activo" value="1" checked> Activo</label>
      </div>
      <div style="margin-top:12px;display:flex;gap:8px;">
        <button type="submit" class="primary">Guardar</button>
        <button type="button" class="secondary" id="exp-req-cancel">Cancelar</button>
      </div>
    </form>
  </dialog>
</div>

<script src="<?php echo htmlspecialchars(hay_asset_url('js/expediente_documentos.js')); ?>"></script>
<script>
window.hayExpedienteReqInit = function () {
  hayExpediente.initRequisitos({ api: <?php echo json_encode($apiUrl); ?> });
};
hayExpedienteReqInit();
</script>
