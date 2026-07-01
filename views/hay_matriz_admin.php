<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_id']) || !hay_eval_puede_matriz_marcar()) {
    echo '<div class="alert">No autorizado.</div>';
    return;
}
try {
    $areas = hay_eval_listar_areas($pdo);
} catch (Throwable $e) {
    error_log('hay_matriz_admin areas: ' . $e->getMessage());
    echo '<div class="alert">No se pudo cargar la matriz. Verifique las tablas hay_* en la base de datos.</div>';
    return;
}
$idPlantel = plantel_id_activo();
$idArea = (int) ($_GET['id_area'] ?? ($areas[0]['id_area'] ?? 0));
$periodo = date('Y-m');
try {
    $cols = $idArea > 0 ? hay_eval_listar_colaboradores_area($pdo, $idArea, $idPlantel) : [];
} catch (Throwable $e) {
    error_log('hay_matriz_admin cols: ' . $e->getMessage());
    $cols = [];
}
?>
<link rel="stylesheet" href="css/hay_eval.css">
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="hay-eval-wrap">
  <h2>Matriz de entrenamiento — seguimiento</h2>
  <p style="color:#666;">Marque si el colaborador completó cada capacitación del periodo.</p>
  <div class="hay-eval-toolbar">
    <select id="hay-ma-area">
      <?php foreach ($areas as $a): ?>
      <option value="<?php echo (int) $a['id_area']; ?>"<?php echo (int) $a['id_area'] === $idArea ? ' selected' : ''; ?>>
        <?php echo htmlspecialchars($a['nombre'], ENT_QUOTES, 'UTF-8'); ?>
      </option>
      <?php endforeach; ?>
    </select>
    <input type="month" id="hay-ma-periodo" value="<?php echo htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8'); ?>">
  </div>
  <div class="hay-dt-panel catalog-table-wrap">
    <table id="hay-ma-tabla" class="display hay-paged-table" style="width:100%;">
      <thead>
        <tr><th>Colaborador</th><th>Rol</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($cols as $c): ?>
        <tr>
          <td><?php echo htmlspecialchars($c['nombre_completo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($c['rol'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <button type="button" class="secondary hay-ma-user" data-id="<?php echo (int) $c['id_usuario']; ?>">Ver matriz</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div id="hay-ma-frame" style="margin-top:16px; border-top:1px solid #eee; padding-top:16px;"></div>
</div>

<script>
(function () {
  if (window.HayDataTable) {
    HayDataTable.init('#hay-ma-tabla', { order: [[0, 'asc']] });
  }
  document.querySelectorAll('.hay-ma-user').forEach((btn) => {
    btn.addEventListener('click', () => {
      const area = document.getElementById('hay-ma-area')?.value || '';
      const per = document.getElementById('hay-ma-periodo')?.value || '';
      cargarSeccion('matriz_entrenamiento', 'id_usuario=' + btn.dataset.id + '&periodo=' + per + '&id_area=' + area);
    });
  });
})();
</script>
