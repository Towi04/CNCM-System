<?php
require_once __DIR__ . '/../config.php';
if (!inscripcion_protocolo_puede_autorizar()) {
    echo '<div class="alert">Solo coordinación, dirección o supervisor pueden autorizar inscripciones.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$rows = inscripcion_protocolo_pendientes($pdo, $idPlantel);
$apiUrl = hay_asset_url('php/inscripcion_protocolo_api.php');
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-user-check"></i> Autorizaciones de inscripción</h2>
    <button type="button" class="secondary" onclick="cargarSeccion('inscripcion_autorizaciones')">Actualizar</button>
  </div>
  <p style="color:#666;">Solicitudes por edad fuera de rango o ubicación en grupo no inicial.</p>
  <div id="msg-auth-insc" class="catalog-alert" style="display:none;"></div>

  <div class="catalog-table-wrap">
    <?php if ($rows === []): ?>
      <p>No hay solicitudes pendientes.</p>
    <?php else: ?>
      <table class="catalog-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Alumno</th>
            <th>Grupo</th>
            <th>Tipo</th>
            <th>Motivo</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr data-id="<?php echo (int) $r['id_auth']; ?>">
            <td><?php echo !empty($r['creado_en']) ? date('d/m/Y H:i', strtotime($r['creado_en'])) : '—'; ?></td>
            <td>
              <?php echo htmlspecialchars(trim(($r['nombres'] ?? '') . ' ' . ($r['apellido_paterno'] ?? '')) ?: '—'); ?>
              <?php if (!empty($r['numero_control'])): ?>
                <br><small>#<?php echo htmlspecialchars($r['numero_control']); ?></small>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($r['grupo_clave'] ?? '—'); ?></td>
            <td><?php echo htmlspecialchars($r['tipo'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['motivo'] ?? ''); ?></td>
            <td>
              <button type="button" class="primary btn-auth-ok" data-id="<?php echo (int) $r['id_auth']; ?>">Aprobar</button>
              <button type="button" class="secondary btn-auth-no" data-id="<?php echo (int) $r['id_auth']; ?>">Rechazar</button>
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
  const api = <?php echo json_encode($apiUrl, JSON_UNESCAPED_UNICODE); ?>;
  const msg = document.getElementById('msg-auth-insc');

  function showMsg(ok, text) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text;
  }

  async function resolver(id, estado) {
    const motivo = estado === 'rechazada' ? (prompt('Motivo del rechazo (opcional):') || '') : '';
    const fd = new FormData();
    fd.append('action', 'resolver');
    fd.append('id_auth', id);
    fd.append('estado', estado);
    if (motivo) fd.append('motivo', motivo);
    const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await r.json();
    showMsg(data.status === 'ok', data.message || '');
    if (data.status === 'ok') cargarSeccion('inscripcion_autorizaciones');
  }

  document.querySelectorAll('.btn-auth-ok').forEach((btn) => {
    btn.addEventListener('click', () => resolver(btn.dataset.id, 'aprobada'));
  });
  document.querySelectorAll('.btn-auth-no').forEach((btn) => {
    btn.addEventListener('click', () => resolver(btn.dataset.id, 'rechazada'));
  });
})();
</script>
