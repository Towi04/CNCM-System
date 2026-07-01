<?php
require_once __DIR__ . '/../config.php';
$rolReal = function_exists('rbac_rol_real') ? rbac_rol_real() : '';
if (!in_array($rolReal, ['supervisor', 'gerente'], true)) {
    echo '<div class="alert">Solo supervisión o gerencia puede configurar el mapeo legado.</div>';
    return;
}
legacy_import_ensure_schema($pdo);
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/legacy_mapeo.css">

<div class="catalog-wrap legacy-mapeo-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-exchange-alt"></i> Equivalencias legado → HAY</h2>
    <p style="color:#666; margin:0;">
      Defina qué sucursal del sistema anterior corresponde a cada plantel HAY.
      Para <strong>grupos y su especialidad</strong> use
      <a href="#" onclick="cargarSeccion('legacy_mapeo_grupos'); return false;">Grupos: sustituir especialidad</a>.
    </p>
  </div>

  <div id="legacy-stats" class="legacy-stats"></div>

  <div class="legacy-tabs">
    <button type="button" class="legacy-tab is-active" data-tab="plantel">Planteles / sucursales</button>
    <button type="button" class="legacy-tab" data-tab="especialidad">Especialidades</button>
  </div>

  <div id="panel-plantel" class="legacy-panel">
    <table class="catalog-table legacy-mapeo-table">
      <thead>
        <tr>
          <th>ID legado</th>
          <th>Nombre legado</th>
          <th>Mapeo actual</th>
          <th>Corresponde en HAY</th>
          <th>Acción</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="tbody-plantel"></tbody>
    </table>
  </div>

  <div id="panel-especialidad" class="legacy-panel" style="display:none;">
    <table class="catalog-table legacy-mapeo-table">
      <thead>
        <tr>
          <th>ID legado</th>
          <th>Nombre legado</th>
          <th>Mapeo actual</th>
          <th>Corresponde en HAY</th>
          <th>Acción</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="tbody-especialidad"></tbody>
    </table>
  </div>
</div>

<script>
(function () {
  const api = 'php/legacy_mapeo_api.php';
  let hayPlanteles = [];
  let hayEsp = [];

  function optHay(list, selected) {
    let h = '<option value="">— Elegir —</option>';
    list.forEach((r) => {
      const id = r.id_plantel || r.id_especialidad;
      const label = (r.nombre || '') + (r.clave ? ' (' + r.clave + ')' : '');
      const sel = selected && Number(selected) === Number(id) ? ' selected' : '';
      h += '<option value="' + id + '"' + sel + '>' + label + '</option>';
    });
    return h;
  }

  function rowHtml(entidad, item, hayList) {
    const sug = item.id_hay_sugerido || item.id_hay_equiv || item.id_hay_map || '';
    const mapTxt = item.id_hay_map
      ? 'Importado #' + item.id_hay_map
      : (item.modo === 'omitir' ? 'Omitido' : '—');
    return `<tr data-legacy="${item.id_legacy}">
      <td>${item.id_legacy}</td>
      <td><strong>${item.nombre_legacy}</strong></td>
      <td>${mapTxt}${item.modo ? ' · ' + item.modo : ''}</td>
      <td><select class="legacy-sel-hay">${optHay(hayList, sug)}</select></td>
      <td>
        <select class="legacy-sel-modo">
          <option value="usar"${item.modo === 'usar' ? ' selected' : ''}>Usar existente</option>
          <option value="omitir"${item.modo === 'omitir' ? ' selected' : ''}>Omitir</option>
          <option value="crear"${item.modo === 'crear' ? ' selected' : ''}>Crear nuevo al importar</option>
        </select>
      </td>
      <td><button type="button" class="primary btn-save-row">Guardar</button></td>
    </tr>`;
  }

  async function loadStats() {
    const { data } = await hayFetchJson(api + '?action=stats');
    if (data.status !== 'ok') return;
    const el = document.getElementById('legacy-stats');
    let html = '<p><strong>Resumen:</strong> ';
    (data.maps || []).forEach((m) => { html += m.entidad + ': ' + m.n + ' · '; });
    html += 'equivalencias manuales: ' + (data.equivalencias || 0);
    html += ' · pagos con etiqueta legado: ' + (data.pagos_importados || 0);
    html += '</p>';
    el.innerHTML = html;
  }

  async function loadPlanteles() {
    const { data } = await hayFetchJson(api + '?action=list_planteles');
    if (data.status !== 'ok') throw new Error(data.message);
    hayPlanteles = data.hay || [];
    document.getElementById('tbody-plantel').innerHTML = (data.legacy || [])
      .map((r) => rowHtml('plantel', r, hayPlanteles)).join('');
  }

  async function loadEsp() {
    const { data } = await hayFetchJson(api + '?action=list_especialidades');
    if (data.status !== 'ok') throw new Error(data.message);
    hayEsp = data.hay || [];
    document.getElementById('tbody-especialidad').innerHTML = (data.legacy || [])
      .map((r) => rowHtml('especialidad', r, hayEsp)).join('');
  }

  async function saveRow(entidad, tr) {
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('entidad', entidad);
    fd.append('id_legacy', tr.dataset.legacy);
    fd.append('modo', tr.querySelector('.legacy-sel-modo').value);
    fd.append('id_hay', tr.querySelector('.legacy-sel-hay').value);
    const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
    if (data.status !== 'ok') throw new Error(data.message || 'Error');
    alert(data.message || 'Guardado');
    loadStats();
  }

  document.querySelectorAll('.legacy-tab').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.legacy-tab').forEach((b) => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      const t = btn.dataset.tab;
      document.getElementById('panel-plantel').style.display = t === 'plantel' ? '' : 'none';
      document.getElementById('panel-especialidad').style.display = t === 'especialidad' ? '' : 'none';
    });
  });

  document.getElementById('panel-plantel').addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-save-row');
    if (!btn) return;
    saveRow('plantel', btn.closest('tr'));
  });
  document.getElementById('panel-especialidad').addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-save-row');
    if (!btn) return;
    saveRow('especialidad', btn.closest('tr'));
  });

  (async () => {
    try {
      await loadStats();
      await Promise.all([loadPlanteles(), loadEsp()]);
    } catch (err) {
      alert(err.message || 'Error al cargar');
    }
  })();
})();
</script>
