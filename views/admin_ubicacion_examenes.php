<?php
require_once __DIR__ . '/../config.php';
if (!function_exists('ubicacion_examen_puede_administrar') || !ubicacion_examen_puede_administrar()) {
    echo '<div class="alert">Solo coordinación académica puede administrar exámenes de ubicación.</div>';
    return;
}

ubicacion_examen_ensure_schema($pdo);

$especialidades = $pdo->query(
    "SELECT id_especialidad, clave, nombre FROM especialidades WHERE activo = 1 ORDER BY orden, nombre"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-clipboard-list"></i> Exámenes de ubicación (Moodle)</h2>
    <button type="button" class="primary" id="btn-nuevo-examen-ub">Nuevo examen</button>
  </div>

  <p style="color:#666; margin-top:0;">
    Configure qué curso Moodle se asigna al inscribir un alumno con la opción <strong>Ubicación</strong>.
    Inglés suele tener un solo examen; computación puede tener varios (por fase o nivel destino).
  </p>

  <div id="ubex-msg" class="catalog-alert" style="display:none;"></div>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Especialidad</label>
      <select id="ubex-filtro-esp" style="min-width:220px;">
        <option value="">Todas</option>
        <?php foreach ($especialidades as $e): ?>
          <option value="<?php echo (int) $e['id_especialidad']; ?>">
            <?php echo htmlspecialchars($e['nombre'] . ' (' . $e['clave'] . ')'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="button" class="primary" id="btn-ubex-listar">Actualizar</button>
    <button type="button" class="secondary" id="btn-ubex-sync-moodle">Cargar cursos Moodle</button>
  </div>

  <div class="catalog-table-wrap">
    <table class="catalog-table" id="ubex-tabla">
      <thead>
        <tr>
          <th>Especialidad</th>
          <th>Nombre</th>
          <th>Fase (opc.)</th>
          <th>Curso Moodle</th>
          <th>Activo</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="ubex-tbody">
        <tr><td colspan="6" style="color:#888;">Cargando…</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="catalog-modal" id="modal-ubex" style="display:none;">
  <div class="catalog-modal__panel" style="max-width:520px;">
    <h3 id="ubex-modal-titulo">Examen de ubicación</h3>
    <input type="hidden" id="ubex-id" value="0">
    <div class="catalog-form-grid">
      <div class="full-width">
        <label>Especialidad *</label>
        <select id="ubex-esp" style="width:100%; padding:8px;">
          <?php foreach ($especialidades as $e): ?>
            <option value="<?php echo (int) $e['id_especialidad']; ?>">
              <?php echo htmlspecialchars($e['nombre']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="full-width">
        <label>Fase destino (opcional)</label>
        <select id="ubex-fase" style="width:100%; padding:8px;">
          <option value="">— Cualquier / no aplica —</option>
        </select>
      </div>
      <div class="full-width">
        <label>Nombre *</label>
        <input type="text" id="ubex-nombre" style="width:100%; padding:8px;" placeholder="Ej. Examen de inglés general">
      </div>
      <div class="full-width">
        <label>Descripción</label>
        <textarea id="ubex-desc" rows="2" style="width:100%;"></textarea>
      </div>
      <div>
        <label>Número ID del curso Moodle (idnumber) *</label>
        <input type="text" id="ubex-idnumber" style="width:100%; padding:8px;" placeholder="4">
        <small style="color:#666;">El mismo valor que ve en Moodle → Configuración del curso → Número ID del curso.</small>
      </div>
      <div>
        <label>Shortname Moodle *</label>
        <input type="text" id="ubex-shortname" style="width:100%; padding:8px;" placeholder="Exam">
      </div>
      <input type="hidden" id="ubex-course-id" value="0">
      <div>
        <label>Orden</label>
        <input type="number" id="ubex-orden" value="0" style="width:100%; padding:8px;">
      </div>
      <div style="align-self:end;">
        <label><input type="checkbox" id="ubex-activo" checked> Activo</label>
      </div>
    </div>
    <p id="ubex-cursos-hint" style="font-size:0.85rem; color:#666;"></p>
    <div style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
      <button type="button" id="ubex-cancel">Cancelar</button>
      <button type="button" class="primary" id="ubex-guardar">Guardar</button>
    </div>
  </div>
</div>

<script>
(function() {
  const api = 'php/ubicacion_examen_api.php';
  const msg = document.getElementById('ubex-msg');
  const tbody = document.getElementById('ubex-tbody');
  const modal = document.getElementById('modal-ubex');
  let cursosMoodle = [];

  function showMsg(ok, text) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text || '';
  }

  async function loadFases(idEsp) {
    const sel = document.getElementById('ubex-fase');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Cualquier / no aplica —</option>';
    if (!idEsp) return;
    const r = await fetch(api + '?action=fases&id_especialidad=' + idEsp, { credentials: 'same-origin' });
    const d = await r.json();
    (d.fases || []).forEach((f) => {
      const o = document.createElement('option');
      o.value = f.id_fase;
      o.textContent = (f.clave_fase || '') + ' — ' + (f.nombre_fase || '');
      sel.appendChild(o);
    });
  }

  async function listar() {
    const idEsp = document.getElementById('ubex-filtro-esp')?.value || '';
    const url = api + '?action=listar' + (idEsp ? '&id_especialidad=' + idEsp : '');
    const r = await fetch(url, { credentials: 'same-origin' });
    const d = await r.json();
    if (d.status !== 'ok') {
      tbody.innerHTML = '<tr><td colspan="6">' + (d.message || 'Error') + '</td></tr>';
      return;
    }
    const items = d.items || [];
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="color:#888;">Sin exámenes. Cree uno (ej. inglés → curso EXAM).</td></tr>';
      return;
    }
    tbody.innerHTML = items.map((it) =>
      '<tr>' +
      '<td>' + (it.esp_nombre || '') + '</td>' +
      '<td><strong>' + (it.nombre || '') + '</strong>' +
        (it.descripcion ? '<br><span style="font-size:0.85rem;color:#666;">' + it.descripcion + '</span>' : '') + '</td>' +
      '<td>' + (it.nombre_fase || '—') + '</td>' +
      '<td>' + (it.moodle_idnumber ? 'idnumber ' + it.moodle_idnumber : '—') +
        (it.moodle_shortname ? ' · ' + it.moodle_shortname : '') +
        (it.moodle_course_id ? ' <span style="color:#888;font-size:0.8rem;">(#' + it.moodle_course_id + ')</span>' : '') + '</td>' +
      '<td>' + (parseInt(it.activo, 10) ? 'Sí' : 'No') + '</td>' +
      '<td><button type="button" class="secondary ubex-edit" data-id="' + it.id_examen + '">Editar</button> ' +
      '<button type="button" class="secondary ubex-del" data-id="' + it.id_examen + '">Eliminar</button></td>' +
      '</tr>'
    ).join('');
  }

  async function syncCursos() {
    const hint = document.getElementById('ubex-cursos-hint');
    if (hint) hint.textContent = 'Consultando Moodle…';
    const r = await fetch(api + '?action=cursos_moodle', { credentials: 'same-origin' });
    const d = await r.json();
    if (d.status !== 'ok' || !d.moodle_enabled) {
      if (hint) hint.textContent = d.message || 'Moodle no configurado';
      return;
    }
    cursosMoodle = d.cursos || [];
    if (hint) {
      hint.textContent = cursosMoodle.length
        ? cursosMoodle.length + ' cursos. Ej.: ' + cursosMoodle.slice(0, 5).map((c) => {
            const inum = (c.idnumber || '').trim();
            return c.shortname + ' (id=' + c.id + (inum ? ', idnumber=' + inum : '') + ')';
          }).join(', ')
        : 'Sin cursos en Moodle';
    }
  }

  function abrirModal(item) {
    document.getElementById('ubex-id').value = item ? item.id_examen : 0;
    document.getElementById('ubex-modal-titulo').textContent = item ? 'Editar examen' : 'Nuevo examen';
    document.getElementById('ubex-esp').value = item ? item.id_especialidad : (document.getElementById('ubex-filtro-esp')?.value || document.getElementById('ubex-esp').value);
    loadFases(parseInt(document.getElementById('ubex-esp').value, 10)).then(() => {
      if (item) {
        document.getElementById('ubex-fase').value = item.id_fase || '';
        document.getElementById('ubex-nombre').value = item.nombre || '';
        document.getElementById('ubex-desc').value = item.descripcion || '';
        document.getElementById('ubex-idnumber').value = item.moodle_idnumber || item.moodle_course_id || '';
        document.getElementById('ubex-shortname').value = item.moodle_shortname || '';
        document.getElementById('ubex-course-id').value = item.moodle_course_id || '';
        document.getElementById('ubex-orden').value = item.orden || 0;
        document.getElementById('ubex-activo').checked = parseInt(item.activo, 10) === 1;
      } else {
        document.getElementById('ubex-fase').value = '';
        document.getElementById('ubex-nombre').value = '';
        document.getElementById('ubex-desc').value = '';
        document.getElementById('ubex-idnumber').value = '';
        document.getElementById('ubex-shortname').value = '';
        document.getElementById('ubex-course-id').value = '';
        document.getElementById('ubex-orden').value = 0;
        document.getElementById('ubex-activo').checked = true;
      }
    });
    modal.style.display = 'flex';
    modal.classList.add('is-open');
  }

  document.getElementById('btn-ubex-listar')?.addEventListener('click', listar);
  document.getElementById('btn-ubex-sync-moodle')?.addEventListener('click', syncCursos);
  document.getElementById('btn-nuevo-examen-ub')?.addEventListener('click', () => abrirModal(null));
  document.getElementById('ubex-cancel')?.addEventListener('click', () => { modal.style.display = 'none'; modal.classList.remove('is-open'); });
  document.getElementById('ubex-esp')?.addEventListener('change', (e) => loadFases(parseInt(e.target.value, 10)));

  document.getElementById('ubex-shortname')?.addEventListener('change', (e) => {
    const sn = e.target.value.trim();
    if (!sn || !cursosMoodle.length) return;
    const c = cursosMoodle.find((x) => x.shortname.toLowerCase() === sn.toLowerCase());
    if (c) {
      document.getElementById('ubex-idnumber').value = (c.idnumber || '').trim() || document.getElementById('ubex-idnumber').value;
      document.getElementById('ubex-course-id').value = c.id;
    }
  });

  document.getElementById('ubex-guardar')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'guardar');
    fd.append('id_examen', document.getElementById('ubex-id').value);
    fd.append('id_especialidad', document.getElementById('ubex-esp').value);
    fd.append('id_fase', document.getElementById('ubex-fase').value);
    fd.append('nombre', document.getElementById('ubex-nombre').value);
    fd.append('descripcion', document.getElementById('ubex-desc').value);
    fd.append('moodle_idnumber', document.getElementById('ubex-idnumber').value.trim());
    fd.append('moodle_course_id', document.getElementById('ubex-course-id').value || '0');
    fd.append('moodle_shortname', document.getElementById('ubex-shortname').value);
    fd.append('orden', document.getElementById('ubex-orden').value);
    if (document.getElementById('ubex-activo').checked) fd.append('activo', '1');
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    showMsg(d.status === 'ok', d.message || '');
    if (d.status === 'ok') {
      modal.style.display = 'none';
      modal.classList.remove('is-open');
      listar();
    }
  });

  tbody?.addEventListener('click', async (e) => {
    const edit = e.target.closest('.ubex-edit');
    const del = e.target.closest('.ubex-del');
    if (edit) {
      const id = parseInt(edit.dataset.id, 10);
      const r = await fetch(api + '?action=listar', { credentials: 'same-origin' });
      const d = await r.json();
      const item = (d.items || []).find((x) => parseInt(x.id_examen, 10) === id);
      if (item) abrirModal(item);
      return;
    }
    if (del) {
      if (!confirm('¿Eliminar este examen del catálogo?')) return;
      const fd = new FormData();
      fd.append('action', 'eliminar');
      fd.append('id_examen', del.dataset.id);
      const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      showMsg(d.status === 'ok', d.message || '');
      if (d.status === 'ok') listar();
    }
  });

  listar();
  syncCursos();
})();
</script>
