<?php
require_once __DIR__ . '/../config.php';
if (!curso_personalizado_puede_gestionar()) {
    echo '<div class="alert">Sin permiso para gestionar cursos personalizados.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
curso_personalizado_ensure_schema($pdo);
$st = $pdo->prepare(
    "SELECT c.*, a.numero_control,
            CONCAT(a.nombres, ' ', a.apellido_paterno) AS alumno_nombre
     FROM curso_personalizado c
     INNER JOIN alumnos a ON a.id_alumno = c.id_alumno
     WHERE c.id_plantel = ? AND c.estado = 'activo'
     ORDER BY c.creado_en DESC LIMIT 80"
);
$st->execute([$idPlantel]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$apiUrl = hay_asset_url('php/curso_personalizado_api.php');
?>
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-file-contract"></i> Cursos personalizados</h2>
    <button type="button" class="primary" id="btn-nuevo-curso-per">Nuevo contrato</button>
  </div>
  <p style="color:#666;">Contratos sin inscripción global; pagos diferidos en punto de venta.</p>
  <div id="msg-cp" class="catalog-alert" style="display:none;"></div>

  <div class="catalog-table-wrap">
    <?php if ($rows === []): ?>
      <p>Sin contratos activos.</p>
    <?php else: ?>
      <table class="catalog-table">
        <thead>
          <tr>
            <th>Alumno</th>
            <th>Título</th>
            <th>Costo total</th>
            <th>Pagos</th>
            <th>Creado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <?php echo htmlspecialchars($r['alumno_nombre'] ?? ''); ?>
              <br><small>#<?php echo htmlspecialchars($r['numero_control'] ?? ''); ?></small>
            </td>
            <td><?php echo htmlspecialchars($r['titulo'] ?? ''); ?></td>
            <td><?php echo catalog_format_mxn((float) ($r['costo_total'] ?? 0)); ?></td>
            <td><?php echo (int) ($r['num_pagos'] ?? 1); ?></td>
            <td><?php echo !empty($r['creado_en']) ? date('d/m/Y', strtotime($r['creado_en'])) : '—'; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="catalog-modal" id="modal-curso-per">
  <div class="catalog-modal__panel" style="max-width:520px;">
    <h3 style="margin-top:0;">Nuevo curso personalizado</h3>
    <form id="form-curso-per" data-no-global-ajax>
      <div class="catalog-form-grid">
        <div class="full">
          <label>ID alumno</label>
          <input type="number" name="id_alumno" id="cp-id-alumno" min="1" required>
        </div>
        <div class="full">
          <label>Título del curso</label>
          <input type="text" name="titulo" id="cp-titulo" required maxlength="160" placeholder="TOEFL, AutoCAD…">
        </div>
        <div>
          <label>Costo total ($)</label>
          <input type="number" name="costo_total" id="cp-costo" min="0.01" step="0.01" required>
        </div>
        <div>
          <label>Número de pagos</label>
          <input type="number" name="num_pagos" id="cp-pagos" min="1" max="24" value="1">
        </div>
        <div>
          <label>Duración (semanas)</label>
          <input type="number" name="duracion_semanas" id="cp-semanas" min="1" placeholder="Opcional">
        </div>
      </div>
      <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
        <button type="button" id="btn-cerrar-cp">Cancelar</button>
        <button type="submit" class="primary">Crear contrato</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const api = <?php echo json_encode($apiUrl, JSON_UNESCAPED_UNICODE); ?>;
  const modal = document.getElementById('modal-curso-per');
  const msg = document.getElementById('msg-cp');
  if (modal && modal.parentElement !== document.body) document.body.appendChild(modal);

  function showMsg(ok, text) {
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text;
  }

  document.getElementById('btn-nuevo-curso-per')?.addEventListener('click', () => {
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  });
  document.getElementById('btn-cerrar-cp')?.addEventListener('click', () => {
    modal.classList.remove('is-open');
    document.body.style.overflow = '';
  });

  document.getElementById('form-curso-per')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('action', 'crear');
    const r = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await r.json();
    showMsg(data.status === 'ok', data.message || '');
    if (data.status === 'ok') {
      modal.classList.remove('is-open');
      cargarSeccion('curso_personalizado_admin');
    }
  });
})();
</script>
