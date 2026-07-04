<?php
require_once __DIR__ . '/../config.php';
$rolReal = function_exists('rbac_rol_real') ? rbac_rol_real() : '';
if (!in_array($rolReal, ['supervisor', 'gerente'], true)) {
    echo '<div class="alert">Solo supervisión o gerencia.</div>';
    return;
}
legacy_import_ensure_schema($pdo);
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/legacy_mapeo.css">

<div class="catalog-wrap legacy-mapeo-wrap legacy-grupos-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-users-class"></i> Grupos legado: sustituir especialidad</h2>
    <p style="color:#666; margin:0 0 12px;">
      Consulta la base <strong><?php echo htmlspecialchars(defined('LEGACY_DB_NAME') ? LEGACY_DB_NAME : 'legado'); ?></strong>
      y define qué <strong>especialidad HAY</strong> debe usarse para cada especialidad del sistema anterior.
      Los <strong>grupos</strong> se conservan (clave, horario, plantel); solo cambia la especialidad asignada al importar o al aplicar.
    </p>
    <ol style="color:#555; font-size:14px; margin:0 0 14px; padding-left:20px;">
      <li>En cada fila elija la especialidad HAY que sustituye a la del legado.</li>
      <li>Pulse <strong>Guardar</strong> en la fila (o use equivalencias ya guardadas).</li>
      <li>Si los grupos ya están en HAY, pulse <strong>Aplicar a grupos importados</strong>.</li>
      <li>Grupos nuevos: importe la fase <strong>grupos</strong> en
        <a href="#" onclick="cargarSeccion('legacy_migracion'); return false;">Asistente migración legado</a>.</li>
    </ol>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <button type="button" class="primary" id="btn-aplicar-grupos">Aplicar a grupos ya importados en HAY</button>
      <button type="button" class="secondary" id="btn-simular-grupos">Simular aplicación</button>
      <a href="#" class="secondary" style="padding:10px 14px; text-decoration:none;" onclick="cargarSeccion('legacy_mapeo'); return false;">Otras equivalencias (planteles)</a>
    </div>
  </div>

  <div id="legacy-grupos-stats" class="legacy-stats"></div>
  <div id="legacy-grupos-msg" class="catalog-alert" style="display:none;"></div>

  <table class="catalog-table legacy-mapeo-table legacy-grupos-table">
    <thead>
      <tr>
        <th>Esp. legado (ID)</th>
        <th>Nombre legado</th>
        <th># Grupos</th>
        <th>Ejemplos de grupos legado</th>
        <th>Sustituir por especialidad HAY</th>
        <th>Vista previa</th>
        <th></th>
      </tr>
    </thead>
    <tbody id="tbody-esp-grupos"></tbody>
  </table>
</div>

<script>
(function () {
  const api = 'php/legacy_mapeo_api.php';
  let hayEsp = [];

  function showMsg(text, ok) {
    const el = document.getElementById('legacy-grupos-msg');
    el.style.display = 'block';
    el.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--err');
    el.textContent = text;
  }

  function optHay(selected) {
    let h = '<option value="">— Elija especialidad HAY —</option>';
    hayEsp.forEach((r) => {
      const sel = selected && Number(selected) === Number(r.id_especialidad) ? ' selected' : '';
      h += '<option value="' + r.id_especialidad + '"' + sel + '>' +
        (r.nombre || '') + (r.clave ? ' (' + r.clave + ')' : '') + '</option>';
    });
    return h;
  }

  function muestrasHtml(list) {
    if (!list || !list.length) return '<span style="color:#888;">—</span>';
    return '<ul class="legacy-grp-muestras">' + list.map((g) => {
      const cl = g.clave || ('ID ' + g.id);
      const h = [g.horario, g.dias].filter(Boolean).join(' ');
      return '<li><code>' + cl + '</code>' + (h ? ' · ' + h : '') + '</li>';
    }).join('') + '</ul>';
  }

  function rowHtml(item) {
    const sug = item.id_hay_equiv || item.id_hay_sugerido || item.id_hay_map || '';
    const prev = item.nombre_hay_efectivo
      ? '<span class="legacy-prev-ok">' + item.nombre_hay_efectivo + '</span>'
      : '<span style="color:#b45309;">Sin definir</span>';
    return `<tr data-legacy="${item.id_legacy}">
      <td>${item.id_legacy}</td>
      <td><strong>${item.nombre_legacy}</strong></td>
      <td>${item.num_grupos || 0}</td>
      <td>${muestrasHtml(item.muestras_grupos)}</td>
      <td><select class="legacy-sel-hay">${optHay(sug)}</select></td>
      <td class="legacy-prev-cell">${prev}</td>
      <td><button type="button" class="primary btn-save-esp">Guardar</button></td>
    </tr>`;
  }

  async function load() {
    const { data } = await hayFetchJson(api + '?action=list_especialidades_grupos');
    if (data.status !== 'ok') throw new Error(data.message);
    hayEsp = data.hay || [];
    document.getElementById('tbody-esp-grupos').innerHTML =
      (data.legacy || []).map(rowHtml).join('');
    let stats = 'Especialidades en legado: ' + (data.legacy || []).length;
    stats += ' · Grupos sin id_especialidad en legado: ' + (data.grupos_sin_id_especialidad || 0);
    document.getElementById('legacy-grupos-stats').textContent = stats;
  }

  async function saveRow(tr) {
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('entidad', 'especialidad');
    fd.append('id_legacy', tr.dataset.legacy);
    fd.append('modo', 'usar');
    fd.append('id_hay', tr.querySelector('.legacy-sel-hay').value);
    const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
    if (data.status !== 'ok') throw new Error(data.message);
    const sel = tr.querySelector('.legacy-sel-hay');
    const opt = sel.options[sel.selectedIndex];
    tr.querySelector('.legacy-prev-cell').innerHTML =
      '<span class="legacy-prev-ok">' + (opt ? opt.text : '') + '</span>';
    showMsg('Equivalencia guardada para especialidad legado #' + tr.dataset.legacy, true);
  }

  async function applyGrupos(dryRun) {
    const fd = new FormData();
    fd.append('action', 'apply_grupos_especialidad');
    if (dryRun) fd.append('dry_run', '1');
    const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
    if (data.status !== 'ok') throw new Error(data.message);
    showMsg(data.message || 'Listo', true);
  }

  document.getElementById('tbody-esp-grupos').addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-save-esp');
    if (!btn) return;
    saveRow(btn.closest('tr')).catch((err) => showMsg(err.message, false));
  });

  document.getElementById('btn-aplicar-grupos').addEventListener('click', () => {
    if (!confirm('¿Actualizar la especialidad en todos los grupos HAY ya importados del legado?')) return;
    applyGrupos(false).catch((err) => showMsg(err.message, false));
  });
  document.getElementById('btn-simular-grupos').addEventListener('click', () => {
    applyGrupos(true).catch((err) => showMsg(err.message, false));
  });

  load().catch((err) => showMsg(err.message || 'Error al cargar legado', false));
})();
</script>
