<?php
require_once __DIR__ . '/../config.php';
if (!rbac_cap('menu_gerente_matriz')) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$periodo = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['periodo'] ?? '')) ? $_GET['periodo'] : date('Y-m');
$resumen = gerente_matriz_resumen_equipo($pdo, $idPlantel, $periodo);
$puedeMarcar = function_exists('hay_eval_puede_matriz_marcar') && hay_eval_puede_matriz_marcar();
$idVer = (int) ($_GET['id_usuario'] ?? 0);

if ($idVer > 0 && $puedeMarcar) {
    $matriz = hay_eval_matriz_usuario($pdo, $idVer, $periodo);
    $st = $pdo->prepare('SELECT nombre, apellido FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $st->execute([$idVer]);
    $colab = $st->fetch(PDO::FETCH_ASSOC);
    $nombreColab = trim(($colab['nombre'] ?? '') . ' ' . ($colab['apellido'] ?? ''));
    ?>
<link rel="stylesheet" href="css/hay_eval.css">
<div class="hay-eval-wrap">
  <h2>Matriz — <?php echo htmlspecialchars($nombreColab); ?></h2>
  <p>
    <button type="button" class="secondary" onclick="cargarSeccion('gerente_matriz_entrenamiento','periodo=<?php echo urlencode($periodo); ?>')">← Volver al equipo</button>
    Periodo: <strong><?php echo htmlspecialchars($periodo); ?></strong>
  </p>
  <?php if (empty($matriz['ok']) && !empty($matriz['message'])): ?>
    <p class="alert"><?php echo htmlspecialchars($matriz['message']); ?></p>
  <?php else: ?>
    <div id="hay-matriz-list">
      <?php foreach ($matriz['capacitaciones'] ?? [] as $cap): ?>
      <div class="hay-matriz-item" data-id="<?php echo (int) $cap['id_capacitacion']; ?>">
        <input type="checkbox" class="hay-cap-chk" data-id="<?php echo (int) $cap['id_capacitacion']; ?>"
          <?php echo !empty($cap['completada']) ? ' checked' : ''; ?>>
        <div>
          <strong><?php echo htmlspecialchars($cap['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
          <br><small><?php echo htmlspecialchars($cap['tipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <input type="hidden" id="hay-matriz-user" value="<?php echo $idVer; ?>">
    <input type="hidden" id="hay-matriz-periodo" value="<?php echo htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
</div>
<script>
(function () {
  const api = 'php/hay_eval_api.php';
  const uid = document.getElementById('hay-matriz-user')?.value;
  const per = document.getElementById('hay-matriz-periodo')?.value;
  document.querySelectorAll('.hay-cap-chk').forEach((chk) => {
    chk.addEventListener('change', async () => {
      const fd = new FormData();
      fd.append('action', 'marcar_capacitacion');
      fd.append('id_usuario', uid);
      fd.append('id_capacitacion', chk.dataset.id);
      fd.append('periodo', per);
      fd.append('completada', chk.checked ? '1' : '0');
      await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    });
  });
})();
</script>
    <?php
    return;
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="css/hay_eval.css">

<div class="catalog-wrap hay-eval-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-graduation-cap"></i> Matriz de entrenamiento — Equipo</h2>
    <p style="color:#666;">
      Área: <strong><?php echo htmlspecialchars($resumen['area_nombre'] ?? 'Asesor de ventas'); ?></strong>
      — Vista gerente del plantel <?php echo htmlspecialchars($_SESSION['plantel_nombre'] ?? ''); ?>.
    </p>
  </div>

  <div class="catalog-toolbar">
    <label>Periodo</label>
    <input type="month" id="gm-periodo" value="<?php echo htmlspecialchars($periodo); ?>">
    <button type="button" class="primary" id="btn-gm-periodo">Cargar</button>
    <button type="button" class="secondary" onclick="cargarSeccion('gerente_hay_portal')">← Portal HAY</button>
    <button type="button" class="secondary" onclick="cargarSeccion('matriz_entrenamiento')">Mi matriz personal</button>
  </div>

  <?php if (empty($resumen['ok'])): ?>
    <p class="alert"><?php echo htmlspecialchars($resumen['message'] ?? 'No se pudo cargar el equipo.'); ?></p>
  <?php elseif (empty($resumen['equipo'])): ?>
    <p style="color:#888;">No hay asesores activos en este plantel.</p>
  <?php else: ?>
  <div class="catalog-table-wrap">
    <table class="catalog-table">
      <thead>
        <tr>
          <th>Colaborador</th>
          <th>Rol</th>
          <th>Nivel HAY</th>
          <th>Puntos</th>
          <th>Capacitaciones</th>
          <th>Avance</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($resumen['equipo'] as $e): ?>
        <tr>
          <td><?php echo htmlspecialchars($e['nombre']); ?></td>
          <td><?php echo htmlspecialchars($e['rol']); ?></td>
          <td><?php echo htmlspecialchars($e['nivel']); ?></td>
          <td><?php echo (int) $e['puntos']; ?></td>
          <td><?php echo (int) $e['capacitaciones_hechas']; ?> / <?php echo (int) $e['capacitaciones_total']; ?></td>
          <td>
            <div style="background:#eee; border-radius:6px; height:8px; min-width:80px;">
              <div style="background:#2e7d32; width:<?php echo min(100, (int) $e['pct']); ?>%; height:8px; border-radius:6px;"></div>
            </div>
            <small><?php echo (int) $e['pct']; ?>%</small>
          </td>
          <td>
            <?php if ($puedeMarcar): ?>
            <button type="button" class="secondary btn-gm-ver" data-id="<?php echo (int) $e['id_usuario']; ?>">Marcar / ver</button>
            <?php else: ?>
            <button type="button" class="secondary btn-gm-ver" data-id="<?php echo (int) $e['id_usuario']; ?>">Ver</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <p style="color:#888; font-size:0.85rem; margin-top:16px;">
    Para otras áreas institucionales use <em>Matriz admin</em> desde el portal HAY. Aquí solo se listan asesores y gerentes del plantel actual.
  </p>
</div>

<script>
(function () {
  document.getElementById('btn-gm-periodo')?.addEventListener('click', function () {
    const p = document.getElementById('gm-periodo')?.value || '';
    cargarSeccion('gerente_matriz_entrenamiento', 'periodo=' + encodeURIComponent(p));
  });
  document.querySelectorAll('.btn-gm-ver').forEach((btn) => {
    btn.addEventListener('click', function () {
      const p = document.getElementById('gm-periodo')?.value || '';
      cargarSeccion('gerente_matriz_entrenamiento', 'id_usuario=' + btn.dataset.id + '&periodo=' + encodeURIComponent(p));
    });
  });
})();
</script>
