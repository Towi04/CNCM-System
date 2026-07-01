<?php
require_once __DIR__ . '/../config.php';
if (!rol_aula_puede_ver()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para consultar el rol de aulas.</div>';
    return;
}

rol_aula_ensure_schema($pdo);
$idPlantel = plantel_scope_id($pdo);
$plantelNombre = $_SESSION['plantel_nombre'] ?? 'Plantel';
$mesActual = (int) date('n');
$anioActual = (int) date('Y');

$pub = rol_aula_obtener_periodo($pdo, $idPlantel, $anioActual, $mesActual);
if ($pub && ($pub['estado'] ?? '') === 'publicado') {
    $detalle = rol_aula_obtener($pdo, (int) $pub['id_publicacion'], $idPlantel);
} else {
    $ultima = rol_aula_ultima_publicada($pdo, $idPlantel);
    $detalle = $ultima ? rol_aula_obtener($pdo, (int) $ultima['id_publicacion'], $idPlantel) : null;
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/rol_aulas.css?v=20260623'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap rol-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-door-closed"></i> Consulta rol de aulas — <?php echo htmlspecialchars($plantelNombre); ?></h2>
    <p style="color:#666;">Vista de solo lectura del rol publicado.</p>
  </div>

  <div class="catalog-toolbar rol-toolbar">
    <div>
      <label>Mes</label>
      <select id="rol-cons-mes">
        <?php
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        for ($m = 1; $m <= 12; $m++):
        ?>
        <option value="<?php echo $m; ?>"<?php echo $m === $mesActual ? ' selected' : ''; ?>><?php echo $meses[$m]; ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div>
      <label>Año</label>
      <input type="number" id="rol-cons-anio" value="<?php echo $anioActual; ?>" min="2020" max="2100" style="width:90px;">
    </div>
    <div style="align-self:flex-end;display:flex;gap:8px;">
      <button type="button" class="primary" id="btn-rol-cons-cargar"><i class="fas fa-sync"></i> Consultar</button>
      <button type="button" class="secondary" id="btn-rol-cons-pdf"><i class="fas fa-file-pdf"></i> Exportar PDF</button>
    </div>
  </div>

  <div id="rol-cons-estado" class="rol-estado"></div>

  <div class="catalog-table-wrap">
    <table class="catalog-table" id="rol-cons-tabla">
      <thead>
        <tr>
          <th>Grupo</th>
          <th>Especialidad</th>
          <th>Alumnos</th>
          <th>Profesor</th>
          <th>Horario</th>
          <th>Aula</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$detalle || empty($detalle['asignaciones'])): ?>
        <tr><td colspan="6" style="color:#888;">No hay rol publicado para mostrar.</td></tr>
        <?php else: ?>
        <?php foreach ($detalle['asignaciones'] as $a): ?>
        <tr>
          <td><strong><?php echo htmlspecialchars($a['grupo_clave'] ?? ''); ?></strong></td>
          <td><?php echo htmlspecialchars($a['esp_nombre'] ?? '—'); ?></td>
          <td><?php echo (int) ($a['total_alumnos'] ?? 0); ?></td>
          <td><?php echo htmlspecialchars(trim($a['profesor_nombre'] ?? '') ?: '—'); ?></td>
          <td><?php echo htmlspecialchars($a['horario_texto'] ?? '—'); ?></td>
          <td>
            <?php if (!empty($a['aula_codigo'])): ?>
            <strong><?php echo htmlspecialchars($a['aula_codigo']); ?></strong>
            <?php if (!empty($a['aula_nombre'])): ?>
            <br><span style="color:#666;font-size:0.85rem;"><?php echo htmlspecialchars($a['aula_nombre']); ?></span>
            <?php endif; ?>
            <?php else: ?>
            <span style="color:#b71c1c;">Sin asignar</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
window.HAY_ROL_AULAS_CONSULTA = <?php echo json_encode([
    'api' => hay_asset_url('php/rol_aula_api.php'),
    'pdf' => hay_asset_url('php/rol_aula_pdf.php'),
], JSON_UNESCAPED_UNICODE); ?>;

(function () {
  const api = (window.HAY_ROL_AULAS_CONSULTA || {}).api || 'php/rol_aula_api.php';

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  async function cargar() {
    const mes = document.getElementById('rol-cons-mes').value;
    const anio = document.getElementById('rol-cons-anio').value;
    const estado = document.getElementById('rol-cons-estado');
    const tbody = document.querySelector('#rol-cons-tabla tbody');
    estado.textContent = 'Cargando…';
    const r = await fetch(api + '?accion=obtener&mes=' + mes + '&anio=' + anio, { credentials: 'same-origin' });
    const data = await r.json();
    const pub = data.publicacion;
    if (!pub) {
      estado.textContent = 'No existe rol para este periodo.';
      tbody.innerHTML = '<tr><td colspan="6" style="color:#888;">Sin datos.</td></tr>';
      return;
    }
    const st = pub.estado === 'publicado' ? 'Publicado' : 'Borrador (no publicado aún)';
    estado.textContent = 'Periodo ' + String(mes).padStart(2, '0') + '/' + anio + ' — ' + st;
    const rows = pub.asignaciones || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="color:#888;">Sin asignaciones.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map((a) => {
      const aula = a.aula_codigo
        ? `<strong>${esc(a.aula_codigo)}</strong>${a.aula_nombre ? '<br><span style="color:#666;font-size:0.85rem;">' + esc(a.aula_nombre) + '</span>' : ''}`
        : '<span style="color:#b71c1c;">Sin asignar</span>';
      return `<tr>
        <td><strong>${esc(a.grupo_clave)}</strong></td>
        <td>${esc(a.esp_nombre || '—')}</td>
        <td>${esc(a.total_alumnos)}</td>
        <td>${esc(a.profesor_nombre || '—')}</td>
        <td>${esc(a.horario_texto || '—')}</td>
        <td>${aula}</td>
      </tr>`;
    }).join('');
  }

  document.getElementById('btn-rol-cons-cargar')?.addEventListener('click', cargar);

  document.getElementById('btn-rol-cons-pdf')?.addEventListener('click', () => {
    const mes = document.getElementById('rol-cons-mes').value;
    const anio = document.getElementById('rol-cons-anio').value;
    const pdf = (window.HAY_ROL_AULAS_CONSULTA || {}).pdf || 'php/rol_aula_pdf.php';
    const url = new URL(pdf, window.location.href);
    url.searchParams.set('mes', mes);
    url.searchParams.set('anio', anio);
    window.open(url.toString(), '_blank');
  });
})();
</script>
