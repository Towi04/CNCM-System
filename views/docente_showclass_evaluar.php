<?php
require_once __DIR__ . '/../config.php';
$idProspecto = (int) ($_GET['id'] ?? 0);
if (!docente_prospecto_puede_gestionar()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}
$p = $idProspecto > 0 ? docente_prospecto_obtener($pdo, $idProspecto) : null;
if (!$p) {
    echo '<div class="alert">Prospecto no encontrado.</div>';
    return;
}
$rubrica = docente_prospecto_showclass_rubrica($pdo, $idProspecto);
$api = hay_asset_url('php/docente_prospecto_api.php');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css')); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/profesor_eval.css')); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-chalkboard"></i> Clase muestra — <?php echo htmlspecialchars(docente_prospecto_nombre($p)); ?></h2>
    <button type="button" class="secondary" onclick="cargarSeccion('docente_prospectos')">← Volver</button>
  </div>
  <p style="color:#666;">Especialidad: <?php echo htmlspecialchars($p['especialidad'] ?? '—'); ?>
    <?php if (!empty($p['fecha_clase_muestra'])): ?> · Fecha: <?php echo date('d/m/Y H:i', strtotime($p['fecha_clase_muestra'])); ?><?php endif; ?></p>

  <div id="msg-showclass" class="catalog-alert" style="display:none;"></div>

  <form id="form-showclass-page" data-no-global-ajax="1" style="max-width:640px;">
    <input type="hidden" name="action" value="save_showclass">
    <input type="hidden" name="id_prospecto" value="<?php echo $idProspecto; ?>">
    <?php foreach ($rubrica as $item): ?>
    <div class="field" style="margin-bottom:12px;">
      <label><?php echo htmlspecialchars($item['nombre']); ?> (0–<?php echo (int) $item['maximo']; ?>)</label>
      <input type="number" name="puntaje_<?php echo htmlspecialchars($item['codigo']); ?>"
             min="0" max="<?php echo (int) $item['maximo']; ?>" step="0.5" value="0" style="width:100%;">
    </div>
    <?php endforeach; ?>
    <textarea name="comentarios" rows="4" placeholder="Comentarios de la clase muestra" style="width:100%;"></textarea>
    <p style="font-size:0.85rem;color:#666;">Aprobación automática con ≥ 75% del total.</p>
    <button type="submit" class="primary" style="margin-top:10px;">Guardar evaluación</button>
  </form>
</div>
<script>
(function () {
  const api = <?php echo json_encode($api); ?>;
  const msg = document.getElementById('msg-showclass');
  document.getElementById('form-showclass-page')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const r = await fetch(api, { method: 'POST', body: new FormData(e.target), credentials: 'same-origin' });
    const d = await r.json();
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (d.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = d.message || '';
    if (d.status === 'ok') setTimeout(() => cargarSeccion('docente_prospectos'), 1200);
  });
})();
</script>
