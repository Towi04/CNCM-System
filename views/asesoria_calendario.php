<?php
require_once __DIR__ . '/../config.php';
asesoria_ensure_schema($pdo);

if (!asesoria_puede_ver_calendario()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$desde = trim((string) ($_GET['desde'] ?? date('Y-m-d')));
$hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d', strtotime('+7 days'))));
$api = hay_asset_url('php/asesoria_api.php');
$esProfesor = rbac_rol_efectivo() === 'profesor' && !asesoria_puede_administrar();
?>
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">
  <h2><i class="fas fa-calendar-week"></i> Calendario de asesorías</h2>
  <div class="catalog-toolbar">
    <div class="field"><label>Desde</label><input type="date" id="ase-cal-desde" value="<?php echo htmlspecialchars($desde); ?>"></div>
    <div class="field"><label>Hasta</label><input type="date" id="ase-cal-hasta" value="<?php echo htmlspecialchars($hasta); ?>"></div>
    <?php if (!$esProfesor): ?>
    <div class="field"><label>Profesor</label>
      <select id="ase-cal-prof"><option value="">Todos</option>
        <?php foreach (asesoria_profesores_para_materia($pdo, $idPlantel, '') as $p): ?>
        <option value="<?php echo (int) $p['id_usuario']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button type="button" class="primary" id="ase-cal-cargar">Cargar</button>
  </div>
  <div id="ase-cal-list"><p style="color:#888;">Cargando…</p></div>
</div>

<script>
(function () {
  const api = <?php echo json_encode($api, JSON_UNESCAPED_UNICODE); ?>;
  const estados = <?php echo json_encode(ASESORIA_ESTADOS, JSON_UNESCAPED_UNICODE); ?>;
  const list = document.getElementById('ase-cal-list');

  async function cargar() {
    let url = api + '?action=listar&desde=' + encodeURIComponent(document.getElementById('ase-cal-desde').value) +
      '&hasta=' + encodeURIComponent(document.getElementById('ase-cal-hasta').value);
    const prof = document.getElementById('ase-cal-prof');
    if (prof && prof.value) url += '&id_profesor=' + prof.value;
    list.innerHTML = 'Cargando…';
    const r = await fetch(url);
    const d = await r.json();
    if (!d.items || !d.items.length) { list.innerHTML = '<p>Sin citas en el rango.</p>'; return; }
    let html = '<div class="catalog-table-wrap"><table class="catalog-table"><thead><tr><th>Fecha</th><th>Hora</th><th>Profesor</th><th>Tema</th><th>Alumnos</th><th>Estado</th></tr></thead><tbody>';
    d.items.forEach(c => {
      const al = (c.alumnos || []).map(a => a.alumno_nombre).join(', ');
      html += '<tr><td>' + c.fecha + '</td><td>' + c.hora_inicio + ':00</td><td>' + (c.profesor_nombre||'') +
        '</td><td>' + (c.tema||'') + '</td><td>' + al + '</td><td>' + (estados[c.estado]||c.estado) + '</td></tr>';
    });
    html += '</tbody></table></div>';
    list.innerHTML = html;
  }
  document.getElementById('ase-cal-cargar').addEventListener('click', cargar);
  cargar();
})();
</script>
