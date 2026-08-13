<?php
require_once __DIR__ . '/../config.php';
$id = (int) ($_GET['id'] ?? 0);
if (rbac_rol_efectivo() === 'alumno') {
    if (!alumno_portal_puede_ver()) {
        echo '<div class="alert">Portal solo para alumnos.</div>';
        return;
    }
    $idPropio = alumno_portal_id_sesion();
    if ($idPropio <= 0) {
        alumno_portal_id_o_detener();
        return;
    }
    if ($id > 0 && $id !== $idPropio) {
        echo '<div class="alert">Solo puede consultar su propio estado de cuenta.</div>';
        return;
    }
    if ($id <= 0) {
        $id = $idPropio;
    }
} elseif ($id <= 0) {
    echo '<div class="alert">Indique el alumno.</div>';
    return;
}
$fecha = trim($_GET['fecha'] ?? date('Y-m-d'));
$ec = pago_estado_cuenta($pdo, $id, $fecha);

if (!$ec['ok']) {
    echo '<div class="alert">' . htmlspecialchars($ec['message'] ?? 'Error') . '</div>';
    return;
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/estado_cuenta.css'), ENT_QUOTES, 'UTF-8'); ?>">
<?php if (rbac_rol_efectivo() === 'alumno'): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>

<div class="ec-wrap no-print" style="margin-bottom:12px;">
  <?php if (rbac_rol_efectivo() === 'alumno'): ?>
    <button type="button" class="secondary" onclick="cargarSeccion('alumno_portal_inicio')">← Inicio</button>
  <?php else: ?>
    <button type="button" onclick="history.back()">← Volver</button>
  <?php endif; ?>
  <button type="button" class="primary" onclick="window.print()" style="margin-left:8px;"><i class="fas fa-print"></i> Imprimir</button>
</div>

<?php if (rbac_rol_efectivo() === 'alumno'): ?>
<div class="ec-wrap no-print" style="margin-bottom:16px; padding:14px; background:#f8fafc; border-radius:10px;">
  <h3 style="margin:0 0 8px;">Registrar transferencia</h3>
  <p style="margin:0 0 10px; color:#64748b; font-size:0.9rem;">
    Indique el depósito realizado. Quedará pendiente hasta que supervisión confirme.
  </p>
  <form id="form-alumno-transferencia" enctype="multipart/form-data">
    <div style="display:grid; gap:10px; max-width:420px;">
      <div>
        <label>Monto ($)</label>
        <input type="number" name="monto" min="0.01" step="0.01" required style="width:100%; padding:8px;">
      </div>
      <div>
        <label>Cuenta bancaria</label>
        <select name="cuenta_banco" required style="width:100%; padding:8px;">
          <option value="">— Seleccione —</option>
          <option value="bbva">BBVA</option>
          <option value="bancoppel">Bancoppel</option>
          <option value="hsbc">HSBC</option>
        </select>
      </div>
      <div>
        <label>Comprobante (opcional)</label>
        <input type="file" name="comprobante" accept="image/*,.pdf" style="width:100%;">
      </div>
      <div>
        <label>Notas</label>
        <input type="text" name="concepto" placeholder="Referencia u observación" style="width:100%; padding:8px;">
      </div>
      <button type="submit" class="primary">Enviar transferencia</button>
    </div>
  </form>
  <p id="alumno-tr-msg" style="display:none; margin-top:10px;"></p>
</div>
<script>
(function () {
  document.getElementById('form-alumno-transferencia')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('accion', 'registrar_alumno');
    fd.append('tipo', 'abono');
    const msg = document.getElementById('alumno-tr-msg');
    try {
      const res = await fetch('php/pago_transferencia_api.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      if (msg) {
        msg.style.display = 'block';
        msg.style.color = data.ok || data.status === 'ok' ? '#166534' : '#b91c1c';
        msg.textContent = data.message || '';
      }
      if (data.ok || data.status === 'ok') {
        e.target.reset();
        setTimeout(() => location.reload(), 800);
      }
    } catch (err) {
      if (msg) {
        msg.style.display = 'block';
        msg.style.color = '#b91c1c';
        msg.textContent = err.message || 'Error de red';
      }
    }
  });
})();
</script>
<?php endif; ?>

<div class="ec-wrap">
  <?php include __DIR__ . '/partials/estado_cuenta_body.php'; ?>
</div>
