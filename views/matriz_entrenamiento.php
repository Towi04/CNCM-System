<?php
require_once __DIR__ . '/_bootstrap.php';
/** @var PDO $pdo */
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert">Sesión no válida.</div>';
    return;
}
$puedeMatriz = function_exists('rbac_cap') && rbac_cap('menu_matriz_entrenamiento');
if (!$puedeMatriz && function_exists('rbac_usuario_en_roles')) {
    $puedeMatriz = rbac_usuario_en_roles(['asesor', 'gerente', 'profesor', 'admin', 'supervisor', 'director', 'coordinador']);
}
if (!$puedeMatriz) {
    echo '<div class="alert">No tiene permiso para ver la matriz de entrenamiento.</div>';
    return;
}
$idUser = (int) $_SESSION['user_id'];
$periodo = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['periodo'] ?? '')) ? $_GET['periodo'] : date('Y-m');
$idAreaSel = (int) ($_GET['id_area'] ?? 0);
$areasUsuario = function_exists('hay_eval_areas_usuario') ? hay_eval_areas_usuario($pdo, $idUser) : [];
$matriz = hay_eval_matriz_usuario($pdo, $idUser, $periodo, $idAreaSel ?: null);
$puedeMarcar = hay_eval_puede_matriz_marcar() && (int) ($_GET['id_usuario'] ?? 0) > 0;
if ($puedeMarcar) {
    $idUser = (int) $_GET['id_usuario'];
    $matriz = hay_eval_matriz_usuario($pdo, $idUser, $periodo, $idAreaSel ?: null);
}
?>
<link rel="stylesheet" href="css/hay_eval.css">

<div class="hay-eval-wrap">
  <h2>Matriz de entrenamiento</h2>
  <p style="margin:0 0 12px;"><button type="button" class="secondary" onclick="cargarSeccion('mi_evaluacion'<?php echo $idAreaSel ? ',\'id_area=' . $idAreaSel . '\'' : ''; ?>)">← Mi evaluación HAY</button></p>
  <?php if (count($areasUsuario) > 1 && !$puedeMarcar): ?>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
    <?php foreach ($areasUsuario as $ar): ?>
    <button type="button" class="<?php echo (int) ($matriz['id_area'] ?? 0) === (int) $ar['id_area'] ? 'primary' : 'secondary'; ?>"
      onclick="cargarSeccion('matriz_entrenamiento','periodo=<?php echo urlencode($periodo); ?>&id_area=<?php echo (int) $ar['id_area']; ?>')">
      <?php echo htmlspecialchars($ar['nombre']); ?>
    </button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if (empty($matriz['ok']) && !empty($matriz['message'])): ?>
  <p class="alert"><?php echo htmlspecialchars($matriz['message'], ENT_QUOTES, 'UTF-8'); ?></p>
  <?php if (function_exists('rbac_rol_efectivo') && rbac_rol_efectivo() === 'asesor'): ?>
  <p style="color:#666; font-size:0.9rem;">El área <strong>Asesor de ventas</strong> se crea automáticamente. Cierre sesión y vuelva a entrar si acaba de actualizar el sistema.</p>
  <?php endif; ?>
  <?php else: ?>
  <p>Periodo: <strong><?php echo htmlspecialchars($matriz['periodo'] ?? $periodo, ENT_QUOTES, 'UTF-8'); ?></strong>
    — Nivel referencia: <?php echo (int) ($matriz['nivel_actual'] ?? 1); ?></p>

  <div id="hay-matriz-list">
    <?php foreach ($matriz['capacitaciones'] ?? [] as $cap): ?>
    <div class="hay-matriz-item" data-id="<?php echo (int) $cap['id_capacitacion']; ?>">
      <?php if ($puedeMarcar): ?>
      <input type="checkbox" class="hay-cap-chk" data-id="<?php echo (int) $cap['id_capacitacion']; ?>"
        <?php echo !empty($cap['completada']) ? ' checked' : ''; ?>>
      <?php else: ?>
      <span><?php echo !empty($cap['completada']) ? '✓' : '○'; ?></span>
      <?php endif; ?>
      <div>
        <strong><?php echo htmlspecialchars($cap['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
        <br><small><?php echo htmlspecialchars($cap['tipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($puedeMarcar): ?>
  <input type="hidden" id="hay-matriz-user" value="<?php echo $idUser; ?>">
  <input type="hidden" id="hay-matriz-periodo" value="<?php echo htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($puedeMarcar): ?>
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
      await hayFetchJson(api, { method: 'POST', body: fd });
    });
  });
})();
</script>
<?php endif; ?>
