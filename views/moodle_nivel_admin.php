<?php
require_once __DIR__ . '/../config.php';
if (!moodle_nivel_puede_administrar()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para administrar Moodle por nivel.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$cobertura = moodle_fase_cobertura_especialidad($pdo);
$moodleOk = function_exists('moodle_enabled') && moodle_enabled();
$api = function_exists('hay_asset_url') ? hay_asset_url('php/moodle_nivel_api.php') : 'php/moodle_nivel_api.php';
?>
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fab fa-moodle"></i> Moodle por nivel</h2>
    <p style="color:#666;margin:0;">
      Configure <code>moodle_course_id</code> en fases (Especialidades → fases). Al inscribir o avanzar grupo, el alumno se enrola al curso del bloque correspondiente.
    </p>
  </div>

  <?php if (!$moodleOk): ?>
  <div class="catalog-alert catalog-alert--error">Moodle no está configurado en <code>config.local.php</code>.</div>
  <?php else: ?>
  <div class="catalog-toolbar" style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap;">
    <button type="button" class="primary" id="btn-moodle-sync"><i class="fas fa-sync"></i> Sincronizar alumnos activos (plantel)</button>
    <button type="button" class="secondary" onclick="cargarSeccion('esp_fases')"><i class="fas fa-cogs"></i> Editar fases / cursos</button>
  </div>
  <div id="moodle-nivel-msg" class="catalog-alert" style="display:none;"></div>
  <?php endif; ?>

  <div class="catalog-table-wrap">
    <table class="catalog-table">
      <thead>
        <tr>
          <th>Especialidad</th>
          <th>Fases totales</th>
          <th>Con curso Moodle</th>
          <th>Cobertura</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cobertura as $c): ?>
        <tr>
          <td><strong><?php echo htmlspecialchars($c['clave'] ?? ''); ?></strong> — <?php echo htmlspecialchars($c['nombre'] ?? ''); ?></td>
          <td><?php echo (int) ($c['total_fases'] ?? 0); ?></td>
          <td><?php echo (int) ($c['fases_con_curso'] ?? 0); ?></td>
          <td>
            <?php
            $pct = (float) ($c['pct'] ?? 0);
            $color = $pct >= 80 ? '#2e7d32' : ($pct >= 40 ? '#f57c00' : '#c62828');
            ?>
            <span style="color:<?php echo $color; ?>;font-weight:700;"><?php echo $pct; ?>%</span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if ($cobertura === []): ?>
        <tr><td colspan="4">No hay especialidades activas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($moodleOk): ?>
<script>
(function () {
  const api = <?php echo json_encode($api, JSON_UNESCAPED_UNICODE); ?>;
  const msg = document.getElementById('moodle-nivel-msg');
  document.getElementById('btn-moodle-sync')?.addEventListener('click', async function () {
    if (!confirm('¿Inscribir alumnos activos en Moodle según la fase actual de cada grupo?')) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert';
    msg.textContent = 'Sincronizando…';
    try {
      const fd = new FormData();
      fd.append('action', 'sync_plantel');
      const res = await fetch(api, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      msg.className = 'catalog-alert catalog-alert--' + (data.status === 'ok' ? 'success' : 'error');
      msg.textContent = data.message || (data.status === 'ok' ? 'Listo' : 'Error');
    } catch (e) {
      msg.className = 'catalog-alert catalog-alert--error';
      msg.textContent = e.message || 'Error de red';
    }
  });
})();
</script>
<?php endif; ?>
