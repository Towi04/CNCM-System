<?php
require_once __DIR__ . '/../config.php';
if (!graduacion_puede_decidir()) {
    echo '<div class="alert">Sin permiso para alertas de graduación.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
graduacion_generar_alertas_automaticas($pdo, $idPlantel);
$estado = trim((string) ($_GET['estado'] ?? 'pendiente'));
$rows = graduacion_listar_alertas($pdo, $idPlantel, $estado);
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2>Alertas de graduación</h2>
    <div style="display:flex; gap:8px;">
      <button type="button" class="secondary" id="btn-sync-graduacion">Sincronizar alertas</button>
      <button type="button" class="secondary" onclick="cargarSeccion('graduacion_alertas', 'estado=pendiente')">Pendientes</button>
      <button type="button" class="secondary" onclick="cargarSeccion('graduacion_alertas', 'estado=aprobado')">Aprobadas</button>
      <button type="button" class="secondary" onclick="cargarSeccion('graduacion_alertas', 'estado=rechazado')">Rechazadas</button>
    </div>
  </div>
  <p style="color:#666;">Se genera alerta cuando el alumno está en la fase previa al proyecto final y el término estimado está a 3 meses o menos.</p>
  <div id="msg-grad-alerta" class="catalog-alert" style="display:none;"></div>

  <div class="catalog-table-wrap">
    <?php if ($rows === []): ?>
      <p>Sin alertas para este estado.</p>
    <?php else: ?>
      <table class="catalog-table">
        <thead>
          <tr>
            <th>Alumno</th>
            <th>Grupo</th>
            <th>Parcial actual</th>
            <th>Fin estimado</th>
            <th>Estado</th>
            <th>Decisión</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <?php echo htmlspecialchars($r['alumno_nombre'] ?? ''); ?>
              <br><small>#<?php echo htmlspecialchars($r['numero_control'] ?? ''); ?></small>
            </td>
            <td><?php echo htmlspecialchars($r['grupo_clave'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['clave_fase'] ?? $r['nombre_fase'] ?? '—'); ?></td>
            <td><?php echo !empty($r['fecha_fin_estimada']) ? date('d/m/Y', strtotime($r['fecha_fin_estimada'])) : '—'; ?></td>
            <td><?php echo htmlspecialchars($r['estado']); ?></td>
            <td>
              <?php if (($r['estado'] ?? '') === 'pendiente'): ?>
                <form class="form-grad-decision" data-id="<?php echo (int) $r['id_alerta']; ?>">
                  <select name="estado" style="width:100%; margin-bottom:6px;">
                    <option value="aprobado">Aprobar para graduación</option>
                    <option value="rechazado">Pendiente / rechazar</option>
                  </select>
                  <textarea name="motivo_decision" rows="2" required style="width:100%; margin-bottom:6px;" placeholder="Motivo"></textarea>
                  <button type="submit" class="primary" style="width:100%;">Guardar</button>
                </form>
              <?php else: ?>
                <strong><?php echo htmlspecialchars($r['estado']); ?></strong>
                <?php if (!empty($r['motivo_decision'])): ?>
                  <br><small><?php echo htmlspecialchars($r['motivo_decision']); ?></small>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  const msg = document.getElementById('msg-grad-alerta');
  document.getElementById('btn-sync-graduacion')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'sync_alerts');
    try {
      const { data } = await hayFetchJson('php/graduacion_api.php', { method: 'POST', body: fd });
      if (msg) {
        msg.style.display = 'block';
        msg.className = 'catalog-alert catalog-alert--ok';
        msg.textContent = data.message || '';
      }
      if (data.seccion) cargarSeccion(data.seccion);
    } catch (e) {
      if (msg) {
        msg.style.display = 'block';
        msg.className = 'catalog-alert catalog-alert--error';
        msg.textContent = e.message || 'Error';
      }
    }
  });

  document.querySelectorAll('.form-grad-decision').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData();
      fd.append('action', 'decidir_alerta');
      fd.append('id_alerta', form.dataset.id);
      fd.append('estado', form.querySelector('[name=estado]').value);
      fd.append('motivo_decision', form.querySelector('[name=motivo_decision]').value);
      try {
        const { data } = await hayFetchJson('php/graduacion_api.php', { method: 'POST', body: fd });
        if (msg) {
          msg.style.display = 'block';
          msg.className = 'catalog-alert ' + (data.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');
          msg.textContent = data.message || '';
        }
        if (data.status === 'ok' && data.seccion) cargarSeccion(data.seccion);
      } catch (err) {
        if (msg) {
          msg.style.display = 'block';
          msg.className = 'catalog-alert catalog-alert--error';
          msg.textContent = err.message || 'Error';
        }
      }
    });
  });
})();
</script>
