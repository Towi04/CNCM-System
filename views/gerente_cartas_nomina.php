<?php
require_once __DIR__ . '/../config.php';
if (!rbac_cap('menu_gerente_cartas')) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

gerente_cartas_ensure_schema($pdo);
$idPlantel = plantel_scope_id($pdo);
$periodo = trim($_GET['semana'] ?? $_GET['mes'] ?? gerente_semana_actual());
if (!preg_match('/^\d{4}-W\d{2}$/', $periodo) && !preg_match('/^\d{4}-\d{2}$/', $periodo)) {
    $periodo = gerente_semana_actual();
}

$st = $pdo->prepare(
    "SELECT id_usuario, nombre, apellido FROM usuarios
     WHERE id_plantel = ? AND rol = 'asesor' AND (suspendido IS NULL OR suspendido = 0)
     ORDER BY nombre, apellido"
);
$st->execute([$idPlantel]);
$asesores = $st->fetchAll(PDO::FETCH_ASSOC);

$st = $pdo->prepare(
    'SELECT id_usuario_asesor FROM asesor_cartas_periodo WHERE id_plantel = ? AND periodo_mes = ?'
);
$st->execute([$idPlantel, $periodo]);
$marcados = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-envelope-open-text"></i> Cartas — comisión nómina</h2>
    <p style="color:#666;">Marque qué asesores salieron a repartir cartas en la semana seleccionada (comisiones semanales).</p>
  </div>

  <div class="catalog-toolbar">
    <label>Semana</label>
    <input type="week" id="cartas-semana" value="<?php echo htmlspecialchars($periodo); ?>">
    <button type="button" class="primary" id="btn-cartas-recargar">Cargar</button>
  </div>

  <form id="form-cartas-nomina">
    <input type="hidden" name="periodo_mes" value="<?php echo htmlspecialchars($periodo); ?>">
    <div class="welcome-card" style="padding:16px; margin-top:12px;">
      <?php if (empty($asesores)): ?>
        <p style="color:#888;">No hay asesores activos en este plantel.</p>
      <?php else: ?>
        <?php foreach ($asesores as $a): ?>
          <label style="display:block; margin-bottom:8px;">
            <input type="checkbox" name="asesores[]" value="<?php echo (int) $a['id_usuario']; ?>"
              <?php echo in_array((int) $a['id_usuario'], $marcados, true) ? 'checked' : ''; ?>>
            <?php echo htmlspecialchars(trim($a['nombre'] . ' ' . $a['apellido'])); ?>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <button type="submit" class="primary" style="margin-top:12px;">Guardar designación</button>
    <span id="cartas-msg" style="margin-left:12px;"></span>
  </form>
</div>

<script>
(function () {
  document.getElementById('btn-cartas-recargar')?.addEventListener('click', function () {
    const w = document.getElementById('cartas-semana')?.value || '';
    cargarSeccion('gerente_cartas_nomina', 'semana=' + encodeURIComponent(w));
  });
  document.getElementById('form-cartas-nomina')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const w = document.getElementById('cartas-semana')?.value;
    if (w) fd.set('periodo_mes', w);
    fd.append('action', 'guardar');
    const msg = document.getElementById('cartas-msg');
    try {
      const res = await fetch('php/gerente_cartas_api.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (msg) {
        msg.textContent = data.message || '';
        msg.style.color = data.status === 'ok' ? '#2e7d32' : '#c62828';
      }
    } catch (err) {
      if (msg) { msg.textContent = 'Error de conexión'; msg.style.color = '#c62828'; }
    }
  });
})();
</script>
