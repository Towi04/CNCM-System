<?php

require_once __DIR__ . '/../config.php';

if (!credencial_puede_diseñar()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para diseñar credenciales.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$plantillas = credencial_plantillas_listar($pdo, $idPlantel);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/documento_emitido.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap doc-plantilla-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-id-card"></i> Plantillas de credencial</h2>
    <p style="color:#666;">Configure frente y reverso en milímetros. El formato estándar es 85.6 × 54 mm.</p>
  </div>

  <div class="catalog-toolbar doc-plantilla-toolbar">
    <div>
      <label>Plantilla</label>
      <select id="cred-pl-select"><option value="">— Nueva —</option></select>
    </div>
    <div>
      <label>Nombre</label>
      <input type="text" id="cred-pl-nombre" placeholder="Credencial estándar">
    </div>
    <div>
      <label>Ancho (mm)</label>
      <input type="number" id="cred-pl-ancho" value="85.6" min="40" max="150" step="0.1">
    </div>
    <div>
      <label>Alto (mm)</label>
      <input type="number" id="cred-pl-alto" value="54" min="30" max="120" step="0.1">
    </div>
    <div>
      <label>Fondo frente</label>
      <input type="file" id="cred-pl-fondo-frente" accept="image/jpeg,image/png,image/webp">
    </div>
    <div>
      <label>Fondo reverso</label>
      <input type="file" id="cred-pl-fondo-reverso" accept="image/jpeg,image/png,image/webp">
    </div>
    <label style="display:flex;align-items:center;gap:6px;">
      <input type="checkbox" id="cred-pl-activo" checked> Activa
    </label>
    <div style="align-self:flex-end;">
      <button type="button" class="primary" id="btn-cred-pl-guardar">Guardar plantilla</button>
    </div>
  </div>

  <div style="display:flex;gap:8px;margin:14px 0;">
    <button type="button" class="primary cred-lado-btn" data-lado="frente">Frente</button>
    <button type="button" class="secondary cred-lado-btn" data-lado="reverso">Reverso</button>
  </div>

  <div class="doc-pl-layout">
    <div class="doc-pl-campos">
      <h3>Campos del <span id="cred-lado-label">frente</span></h3>
      <p style="font-size:.88rem;color:#666;">X/Y desde la esquina superior izquierda; W define ancho de texto o imagen.</p>
      <div id="cred-pl-campos-list"></div>
      <button type="button" class="secondary" id="btn-cred-pl-add-campo">+ Agregar campo</button>
    </div>
    <div>
      <h3 style="margin-top:0;">Vista previa</h3>
      <div class="doc-pl-preview" id="cred-pl-preview"></div>
    </div>
  </div>
</div>

<script>
window.HAY_CRED_PLANTILLA = <?php echo json_encode([
    'api' => hay_asset_url('php/credencial_api.php'),
    'assetRoot' => hay_asset_url(''),
    'campos' => credencial_campos_disponibles(),
    'plantillas' => $plantillas,
    'defaultsFrente' => credencial_campos_default('frente'),
    'defaultsReverso' => credencial_campos_default('reverso'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/admin_credencial_plantilla.js?v=20260820'), ENT_QUOTES, 'UTF-8'); ?>"></script>
