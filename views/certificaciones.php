<?php
require_once __DIR__ . '/../config.php';
if (!certificacion_puede_acceder()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para certificaciones.</div>';
    return;
}

$puedeAdmin = certificacion_puede_administrar();
$puedeSupervisar = certificacion_puede_supervisar();
$tiposDoc = certificacion_tipos_documento();
$estados = certificacion_estados_etiquetas();
$familias = certificacion_familias();
$camposAccesoLabels = certificacion_campos_acceso_labels();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/certificaciones.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap cert-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-certificate"></i> Certificaciones</h2>
    <p style="color:#666; margin:0;">Consulta protocolos, requisitos y documentación. Registra solicitudes de alumnos o público en general.</p>
  </div>

  <div class="cert-tabs">
    <button type="button" class="cert-tab active" data-tab="catalogo">Catálogo</button>
    <button type="button" class="cert-tab" data-tab="solicitudes">Solicitudes</button>
    <button type="button" class="primary" id="cert-btn-nueva"><i class="fas fa-plus"></i> Nueva solicitud</button>
  </div>

  <div class="cert-panel" id="cert-panel-catalogo">
    <div class="catalog-toolbar">
      <div class="field" style="flex:1;">
        <label>Buscar certificación</label>
        <input type="search" id="cert-buscar" placeholder="TOEFL, Cambridge, organismo…">
      </div>
      <?php if ($puedeAdmin): ?>
      <div class="field" style="align-self:flex-end;">
        <button type="button" class="secondary" id="cert-btn-config"><i class="fas fa-cog"></i> Configurar producto</button>
      </div>
      <?php endif; ?>
    </div>
    <p id="cert-cat-loading" hidden><i class="fas fa-spinner fa-spin"></i> Cargando…</p>
    <div id="cert-catalogo-grid" class="cert-grid"></div>
  </div>

  <div class="cert-panel" id="cert-panel-solicitudes" hidden>
    <div class="catalog-toolbar">
      <div class="field">
        <label>Estado</label>
        <select id="cert-filtro-estado">
          <option value="">Todos</option>
          <?php foreach ($estados as $k => $lbl): ?>
          <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($lbl); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="flex:1;">
        <label>Buscar</label>
        <input type="search" id="cert-buscar-sol" placeholder="Alumno, control, certificación…">
      </div>
    </div>
    <div class="catalog-table-wrap">
      <table class="catalog-table" id="cert-tabla-solicitudes">
        <thead>
          <tr>
            <th>Fecha</th><th>Alumno</th><th>Control</th><th>Certificación</th>
            <th>Examen</th><th>Estado</th><th></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Detalle certificación -->
<div class="cert-modal" id="cert-modal-detalle" hidden>
  <div class="cert-modal-box cert-modal-box--wide">
    <button type="button" class="cert-modal-close" data-close>&times;</button>
    <div id="cert-detalle-body"></div>
    <div style="margin-top:16px; text-align:right;">
      <button type="button" class="primary" id="cert-detalle-solicitar">Registrar solicitud</button>
    </div>
  </div>
</div>

<!-- Nueva solicitud -->
<div class="cert-modal" id="cert-modal-solicitud" hidden>
  <div class="cert-modal-box cert-modal-box--wide">
    <button type="button" class="cert-modal-close" data-close>&times;</button>
    <h3 style="margin-top:0;">Nueva solicitud de certificación</h3>
    <form id="cert-form-solicitud">
      <div class="cert-form-grid">
        <div class="full">
          <label>Certificación *</label>
          <select id="cert-sol-producto" required></select>
        </div>
        <div class="full">
          <label>Alumno registrado (opcional)</label>
          <select id="cert-sol-alumno"><option value="">— Nuevo / sin alumno —</option></select>
        </div>
        <div id="cert-sol-nuevo">
          <label>Nombres *</label><input type="text" id="cert-sol-nombres">
          <label>Apellido paterno *</label><input type="text" id="cert-sol-apPat">
          <label>Apellido materno</label><input type="text" id="cert-sol-apMat">
          <label>Teléfono</label><input type="tel" id="cert-sol-tel">
          <label>Correo</label><input type="email" id="cert-sol-email">
        </div>
        <div>
          <label>Fecha preferida de examen</label>
          <input type="date" id="cert-sol-fecha">
        </div>
        <div>
          <label>Hora preferida (horario de oficina)</label>
          <input type="time" id="cert-sol-hora">
        </div>
        <div class="full">
          <label>Notas</label>
          <textarea id="cert-sol-notas" rows="2"></textarea>
        </div>
      </div>
      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
        <button type="button" class="secondary" data-close>Cancelar</button>
        <button type="submit" class="primary">Guardar solicitud</button>
      </div>
    </form>
    <p id="cert-sol-msg" class="catalog-alert" style="display:none; margin-top:10px;"></p>
  </div>
</div>

<!-- Expediente solicitud -->
<div class="cert-modal" id="cert-modal-expediente" hidden>
  <div class="cert-modal-box cert-modal-box--wide">
    <button type="button" class="cert-modal-close" data-close>&times;</button>
    <div id="cert-expediente-body"></div>
  </div>
</div>

<?php if ($puedeAdmin): ?>
<div class="cert-modal" id="cert-modal-config" hidden>
  <div class="cert-modal-box cert-modal-box--wide">
    <button type="button" class="cert-modal-close" data-close>&times;</button>
    <h3 style="margin-top:0;">Configurar certificación (producto)</h3>
    <p style="color:#666; margin-top:-6px;">
      Si la certificación aún no existe como producto, créala primero en <a href="#" onclick="cargarSeccion('admin_productos'); return false;">Productos</a>.
    </p>
    <form id="cert-form-config">
      <input type="hidden" id="cert-cfg-id" value="0">
      <div class="cert-form-grid">
        <div class="full">
          <label>Producto del catálogo *</label>
          <select id="cert-cfg-producto" required></select>
        </div>
        <div class="full">
          <label>Familia / plantilla de flujo *</label>
          <select id="cert-cfg-familia" required>
            <?php foreach ($familias as $k => $fam): ?>
            <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($fam['label']); ?></option>
            <?php endforeach; ?>
          </select>
          <p id="cert-cfg-familia-hint" style="font-size:0.85rem; color:#666; margin:6px 0 0;"></p>
        </div>
        <div><label>Organismo / proveedor</label><input type="text" id="cert-cfg-organismo" placeholder="ETS, Cambridge…"></div>
        <div><label>Software requerido</label><input type="text" id="cert-cfg-software-nombre" placeholder="Examity, Safe Exam Browser…"></div>
        <div class="full"><label>URL descarga software</label><input type="url" id="cert-cfg-software-url" placeholder="https://…"></div>
        <div class="full"><label>Instrucciones software</label><textarea id="cert-cfg-software-inst" rows="2"></textarea></div>
        <div class="full"><label>Protocolo para presentar el examen</label><textarea id="cert-cfg-protocolo" rows="4" placeholder="Pasos que debe seguir el alumno…"></textarea></div>
        <div class="full"><label>Reglamento (texto para el asesor)</label><textarea id="cert-cfg-reglamento" rows="3"></textarea></div>
        <div class="full">
          <label>PDF reglamento (opcional)</label>
          <input type="file" id="cert-cfg-reglamento-file" accept=".pdf,image/*">
          <p id="cert-cfg-reglamento-link" style="font-size:0.85rem; color:#666;"></p>
        </div>
        <div class="full">
          <label><input type="checkbox" id="cert-cfg-req-reglamento"> El alumno debe firmar y subir el reglamento</label>
        </div>
        <div class="full">
          <label>Documentos requeridos</label>
          <div class="cert-docs-checks">
            <?php foreach ($tiposDoc as $k => $lbl): ?>
            <label><input type="checkbox" name="docs_req" value="<?php echo htmlspecialchars($k); ?>"> <?php echo htmlspecialchars($lbl); ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="full"><label>Notas para asesores</label><textarea id="cert-cfg-notas" rows="2"></textarea></div>
        <div><label>Comisión asesor (default)</label><input type="number" step="0.01" min="0" id="cert-cfg-com-asesor" value="0"></div>
        <div><label>Comisión gerente (default)</label><input type="number" step="0.01" min="0" id="cert-cfg-com-gerente" value="0"></div>
        <div class="full">
          <label>Campos del expediente (plantilla Excel)</label>
          <p style="font-size:0.85rem; color:#666; margin:4px 0 8px;">
            Elija qué columnas aplican a esta certificación y si las llena el asesor, el alumno o el supervisor.
          </p>
          <div id="cert-cfg-campos" style="max-height:240px; overflow:auto; border:1px solid #e8e8e8; border-radius:8px; padding:10px;"></div>
        </div>
      </div>
      <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
        <button type="button" class="secondary" data-close>Cancelar</button>
        <button type="submit" class="primary">Guardar</button>
      </div>
    </form>
    <p id="cert-cfg-msg" class="catalog-alert" style="display:none; margin-top:10px;"></p>
  </div>
</div>
<?php endif; ?>

<script>
window.HAY_CERT_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/certificacion_api.php'),
    'comisionesPartial' => hay_asset_url('php/certificacion_expediente_comisiones.php'),
    'comisionesSave' => hay_asset_url('php/certificacion_comision_save.php'),
    'puedeAdmin' => $puedeAdmin,
    'puedeSupervisar' => $puedeSupervisar,
    'tiposDoc' => $tiposDoc,
    'estados' => $estados,
    'familias' => $familias,
    'camposAccesoLabels' => $camposAccesoLabels,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/certificaciones.js?v=20260527'), ENT_QUOTES, 'UTF-8'); ?>"></script>
