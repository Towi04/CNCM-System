<?php
require_once __DIR__ . '/../config.php';
$id = (int) ($_GET['id'] ?? 0);
$idPlantel = plantel_id_activo();
$p = [];
if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM prospectos_profesor WHERE id_prospecto = ? AND id_plantel = ?');
    $st->execute([$id, $idPlantel]);
    $p = $st->fetch(PDO::FETCH_ASSOC) ?: [];
}
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><?php echo $id ? 'Prospecto profesor' : 'Nuevo prospecto profesor'; ?></h2>
    <button type="button" class="secondary" onclick="cargarSeccion('prospectos_profesor')">← Lista</button>
  </div>
  <div id="msg-pp" class="catalog-alert" style="display:none;"></div>
  <form id="form-pp">
    <input type="hidden" name="id_prospecto" value="<?php echo $id; ?>">
    <div class="prereg-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
      <div><label>Nombres *</label><input name="nombres" required value="<?php echo htmlspecialchars($p['nombres'] ?? ''); ?>"></div>
      <div><label>Apellido paterno *</label><input name="apellido_paterno" required value="<?php echo htmlspecialchars($p['apellido_paterno'] ?? ''); ?>"></div>
      <div><label>Apellido materno</label><input name="apellido_materno" value="<?php echo htmlspecialchars($p['apellido_materno'] ?? ''); ?>"></div>
      <div><label>Teléfono</label><input name="telefono" value="<?php echo htmlspecialchars($p['telefono'] ?? ''); ?>"></div>
      <div><label>Correo personal</label><input type="email" name="email_personal" value="<?php echo htmlspecialchars($p['email_personal'] ?? ''); ?>"></div>
      <div><label>Especialidad / área</label><input name="especialidad" value="<?php echo htmlspecialchars($p['especialidad'] ?? ''); ?>"></div>
      <div><label>Estado</label>
        <select name="estado">
          <?php foreach (['entrevista','evaluacion','contratado','rechazado','contactar_despues'] as $e): ?>
            <option value="<?php echo $e; ?>"<?php echo ($p['estado'] ?? '') === $e ? ' selected' : ''; ?>><?php echo $e; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="full"><label>Observaciones</label><textarea name="observaciones" rows="2"><?php echo htmlspecialchars($p['observaciones'] ?? ''); ?></textarea></div>
      <div class="full"><label>Motivo si no se contrató / seguimiento</label><textarea name="motivo_no_contratacion" rows="2"><?php echo htmlspecialchars($p['motivo_no_contratacion'] ?? ''); ?></textarea></div>
    </div>
    <p style="color:#888; font-size:0.9rem;">Evaluación inicial: use el menú Sistema HAY / exámenes con acceso temporal (próximo paso: vincular usuario limitado).</p>
    <button type="submit" class="primary" style="margin-top:16px;">Guardar prospecto</button>
  </form>
</div>

<script>
document.getElementById('form-pp')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const r = await fetch('php/prospecto_profesor_save.php', { method: 'POST', body: fd });
  const data = await r.json();
  const msg = document.getElementById('msg-pp');
  msg.style.display = 'block';
  msg.className = 'catalog-alert catalog-alert--' + (data.status === 'ok' ? 'ok' : 'error');
  msg.textContent = data.message || '';
  if (data.status === 'ok') cargarSeccion('prospectos_profesor');
});
</script>
