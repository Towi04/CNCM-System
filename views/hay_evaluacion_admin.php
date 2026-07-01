<?php
require_once __DIR__ . '/_bootstrap.php';
if (!isset($_SESSION['user_id']) || !hay_eval_puede_gestionar()) {
    echo '<div class="alert">No autorizado.</div>';
    return;
}
$areas = hay_eval_listar_areas($pdo);
$anio = (int) ($_GET['anio'] ?? date('Y'));
$mes = (int) ($_GET['mes'] ?? date('n'));
$idArea = (int) ($_GET['id_area'] ?? ($areas[0]['id_area'] ?? 0));
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_eval.css">

<div class="hay-eval-wrap">
  <h2>Evaluar personal (HAY)</h2>
  <div class="hay-eval-toolbar">
    <label>Área</label>
    <select id="hay-ev-area">
      <?php foreach ($areas as $a): ?>
      <option value="<?php echo (int) $a['id_area']; ?>"<?php echo (int) $a['id_area'] === $idArea ? ' selected' : ''; ?>>
        <?php echo htmlspecialchars($a['nombre'], ENT_QUOTES, 'UTF-8'); ?>
      </option>
      <?php endforeach; ?>
    </select>
    <label>Año</label>
    <input type="number" id="hay-ev-anio" value="<?php echo $anio; ?>" min="2020" max="2099" style="width:90px;">
    <label>Mes</label>
    <input type="number" id="hay-ev-mes" value="<?php echo $mes; ?>" min="1" max="12" style="width:70px;">
    <button type="button" class="primary" id="hay-ev-cargar">Cargar</button>
  </div>
  <table class="hay-paged-table display" id="tabla-hay-eval" style="width:100%;">
    <thead>
      <tr>
        <th>Colaborador</th>
        <th>Rol</th>
        <th>Estado</th>
        <th>Puntos</th>
        <th></th>
      </tr>
    </thead>
    <tbody id="hay-ev-tbody"></tbody>
  </table>
</div>

<script>
(function () {
  const api = 'php/hay_eval_api.php';
  const tbody = document.getElementById('hay-ev-tbody');

  async function cargar() {
    const idArea = document.getElementById('hay-ev-area')?.value;
    const anio = document.getElementById('hay-ev-anio')?.value;
    const mes = document.getElementById('hay-ev-mes')?.value;
    const { data } = await hayFetchJson(api + '?action=colaboradores&id_area=' + idArea + '&anio=' + anio + '&mes=' + mes);
    if (!tbody) return;
    tbody.innerHTML = '';
    if (data.status !== 'ok') {
      tbody.innerHTML = '<tr><td colspan="5">' + (data.message || 'Error') + '</td></tr>';
      return;
    }
    (data.colaboradores || []).forEach((c) => {
      const tr = document.createElement('tr');
      const est = c.estado_eval === 'cerrado' ? 'Cerrado' : (c.estado_eval ? 'Borrador' : 'Sin iniciar');
      tr.innerHTML =
        '<td>' + (c.nombre_completo || '') + '</td>' +
        '<td>' + (c.rol || '') + '</td>' +
        '<td>' + est + '</td>' +
        '<td>' + (c.puntos_total || 0) + '</td>' +
        '<td><button type="button" class="primary hay-ev-open" data-eval="' + (c.id_eval || 0) + '">Evaluar</button></td>';
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll('.hay-ev-open').forEach((btn) => {
      btn.addEventListener('click', () => {
        cargarSeccion('hay_evaluacion_form', 'id_eval=' + btn.dataset.eval);
      });
    });
  }

  document.getElementById('hay-ev-cargar')?.addEventListener('click', cargar);
  cargar();
})();
</script>
