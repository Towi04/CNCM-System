<?php
require_once __DIR__ . '/../config.php';
if (!aula_puede_gestionar()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para administrar aulas.</div>';
    return;
}

aula_ensure_schema($pdo);
$idPlantel = plantel_scope_id($pdo);
$plantelNombre = $_SESSION['plantel_nombre'] ?? 'Plantel';
$especialidades = $pdo->query('SELECT id_especialidad, nombre FROM especialidades WHERE activo = 1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
$tiposAula = aula_tipos();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-door-open"></i> Catálogo de aulas — <?php echo htmlspecialchars($plantelNombre); ?></h2>
    <p style="color:#666;">Registre aulas con capacidad, equipamiento, tipo de espacio, especialidades permitidas y fotos (máx. <?php echo AULA_FOTO_MAX; ?>).</p>
  </div>

  <div style="display:grid; grid-template-columns: minmax(300px, 380px) 1fr; gap:20px; align-items:start;">
    <form id="mapa-form" class="catalog-toolbar" style="flex-direction:column; align-items:stretch;" data-no-global-ajax enctype="multipart/form-data">
      <h3 id="mapa-form-titulo" style="margin:0 0 8px;">Nueva aula</h3>
      <input type="hidden" name="id_aula" id="mapa-id-aula">
      <label>Código *</label>
      <input name="codigo" id="mapa-codigo" required maxlength="40" placeholder="Ej. A-101">
      <label>Nombre</label>
      <input name="nombre" id="mapa-nombre" maxlength="120" placeholder="Aula principal">
      <label>Piso / zona</label>
      <input name="piso" id="mapa-piso" maxlength="30" placeholder="Planta baja">
      <label>Tipo de espacio</label>
      <select id="mapa-tipo">
        <?php foreach ($tiposAula as $k => $lbl): ?>
        <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($lbl); ?></option>
        <?php endforeach; ?>
      </select>
      <label>Capacidad base (alumnos)</label>
      <input type="number" name="capacidad" id="mapa-capacidad" min="1" max="200" value="20">
      <label style="display:flex;align-items:center;gap:8px;margin-top:8px;">
        <input type="checkbox" id="mapa-cap-flex"> Permite aumentar sillas día a día (capacidad flexible)
      </label>
      <p style="font-size:0.82rem;color:#666;margin:4px 0 8px;">Si no está marcado, la capacidad base es el máximo fijo del aula.</p>
      <fieldset style="border:1px solid #ddd;border-radius:8px;padding:8px 12px;margin:8px 0;">
        <legend style="font-size:0.9rem;">Equipamiento</legend>
        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="mapa-pizarron" checked> Pizarrón</label>
        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="mapa-proyector"> Proyector</label>
        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="mapa-tv"> TV / pantalla</label>
        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="mapa-pc"> PC / equipo de cómputo</label>
      </fieldset>
      <label style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" id="mapa-todas-esp" checked> Todas las especialidades pueden usar este aula
      </label>
      <div id="mapa-esp-wrap" hidden>
        <label>Solo estas especialidades</label>
        <div id="mapa-esp-box" style="max-height:160px; overflow-y:auto; border:1px solid #ddd; padding:8px; border-radius:8px;"></div>
      </div>
      <label>Notas</label>
      <textarea name="notas" id="mapa-notas" rows="2"></textarea>
      <label style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" id="mapa-activo" checked> Activa
      </label>
      <div id="mapa-fotos-wrap" hidden>
        <label>Fotos del aula</label>
        <div id="mapa-fotos" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;"></div>
        <input type="file" id="mapa-foto-input" accept="image/jpeg,image/png,image/webp" style="font-size:0.85rem;">
      </div>
      <div style="display:flex; gap:8px; margin-top:8px;">
        <button type="submit" class="primary">Guardar aula</button>
        <button type="button" class="secondary" id="btn-mapa-limpiar">Limpiar</button>
      </div>
      <p id="mapa-msg" style="font-size:0.88rem; color:#2e7d32; margin:8px 0 0;"></p>
    </form>

    <div class="catalog-table-wrap">
      <table class="catalog-table" id="mapa-aulas-tabla">
        <thead>
          <tr><th>Aula</th><th>Tipo</th><th>Piso</th><th>Cap.</th><th>Equipo</th><th>Especialidades</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<script>
window.HAY_MAPA_AULAS_CONFIG = <?php echo json_encode([
    'api' => hay_asset_url('php/aula_api.php'),
    'especialidades' => $especialidades,
    'tipos' => $tiposAula,
    'fotoMax' => AULA_FOTO_MAX,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(hay_asset_url('js/admin_mapa_plantel.js?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>"></script>
