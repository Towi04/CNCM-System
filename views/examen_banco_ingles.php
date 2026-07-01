<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/exam/load.php';

use HayExam\BancoInglesService;

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$tipos = BancoInglesService::TIPOS;
$tipoInicial = array_key_first($tipos);
?>

<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/examenes.css">

<div class="result-container banco-container">
  <div class="result-header">
    <h2><i class="fas fa-database"></i> Banco de preguntas — Inglés</h2>
  </div>

  <div id="banco-msg" class="exam-msg"></div>

  <!-- Pestañas por tipo -->
  <div class="banco-tabs" id="banco-tabs">
    <?php foreach ($tipos as $key => $meta): ?>
      <button type="button" class="banco-tab<?php echo $key === $tipoInicial ? ' active' : ''; ?>" data-tipo="<?php echo htmlspecialchars($key); ?>">
        <?php echo htmlspecialchars($meta['label']); ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- Barra de acciones -->
  <div class="banco-toolbar">
    <div class="banco-toolbar-left">
      <label>Filtrar fase:</label>
      <select id="filtro-fase">
        <option value="">Todas</option>
      </select>
      <button type="button" id="btn-refrescar"><i class="fas fa-sync-alt"></i> Actualizar</button>
    </div>
    <div class="banco-toolbar-right">
      <button type="button" class="primary" id="btn-nueva-pregunta">
        <i class="fas fa-plus"></i> Nueva pregunta
      </button>
      <a href="#" id="btn-descargar-ejemplo" class="btn-outline" download>
        <i class="fas fa-download"></i> Descargar CSV de ejemplo
      </a>
    </div>
  </div>

  <!-- Importar CSV -->
  <div class="banco-import-box">
    <h3><i class="fas fa-file-upload"></i> Importar preguntas desde CSV</h3>
    <p class="banco-hint" id="csv-estructura-hint"></p>
    <form id="form-import-csv" enctype="multipart/form-data" data-no-global-ajax>
      <input type="hidden" name="action" value="importar">
      <input type="hidden" name="tipo" id="import-tipo" value="<?php echo htmlspecialchars($tipoInicial); ?>">
      <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
      <button type="submit" class="primary"><i class="fas fa-upload"></i> Subir e importar</button>
    </form>
  </div>

  <!-- Tabla de registros -->
  <div id="banco-loading" style="display:none; padding:20px; color:#666;">Cargando…</div>
  <div class="banco-table-wrap">
    <table class="banco-table" id="banco-table">
      <thead id="banco-thead"></thead>
      <tbody id="banco-tbody"></tbody>
    </table>
  </div>
  <p id="banco-empty" style="display:none; color:#888; padding:16px 0;">No hay registros para este tipo/fase.</p>

  <div class="banco-pagination" id="banco-pagination"></div>
</div>

<!-- Modal editar -->
<div class="banco-modal-overlay" id="modal-editar" style="display:none;">
  <div class="banco-modal">
    <div class="banco-modal-header">
      <h3 id="modal-titulo">Editar registro</h3>
      <button type="button" class="banco-modal-close" id="modal-cerrar">&times;</button>
    </div>
    <form id="form-editar" data-no-global-ajax>
      <input type="hidden" name="action" id="edit-action" value="guardar">
      <input type="hidden" name="tipo" id="edit-tipo">
      <input type="hidden" name="id" id="edit-id">
      <div id="edit-campos" class="banco-edit-grid"></div>
      <div class="banco-modal-actions">
        <button type="button" id="btn-cancelar-edit">Cancelar</button>
        <button type="submit" class="primary" id="btn-submit-edit">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
  const API = 'php/exam/banco_api.php';
  const EJEMPLO = 'php/exam/banco_ejemplo_csv.php';
  const TIPOS = <?php echo json_encode(array_map(fn($t) => [
    'label' => $t['label'],
    'headers' => $t['csv_headers'],
    'campos' => $t['campos'],
  ], $tipos), JSON_UNESCAPED_UNICODE); ?>;

  let tipoActual = <?php echo json_encode($tipoInicial); ?>;
  let pageActual = 1;

  const msgBox = document.getElementById('banco-msg');
  const thead = document.getElementById('banco-thead');
  const tbody = document.getElementById('banco-tbody');
  const filtroFase = document.getElementById('filtro-fase');
  const hintBox = document.getElementById('csv-estructura-hint');
  const btnEjemplo = document.getElementById('btn-descargar-ejemplo');

  const labels = {
    fase: 'Fase', pregunta: 'Pregunta', opcion_a: 'Opción A', opcion_b: 'Opción B',
    opcion_c: 'Opción C', opcion_d: 'Opción D', respuesta: 'Respuesta',
    id_audio: 'ID Audio', nombre_audio: 'Nombre audio', link_audio: 'Link audio', script_audio: 'Script del audio',
    id_lectura: 'ID Lectura', nombre_lectura: 'Nombre lectura', lectura: 'Texto lectura',
  };

  function showMsg(text, ok) {
    msgBox.textContent = text;
    msgBox.className = 'exam-msg ' + (ok ? 'ok' : 'err');
    msgBox.style.display = 'block';
    setTimeout(() => { msgBox.style.display = 'none'; }, 6000);
  }

  function updateHint() {
    const h = TIPOS[tipoActual].headers;
    hintBox.innerHTML = '<strong>Columnas requeridas:</strong> ' + h.join(', ');
    btnEjemplo.href = EJEMPLO + '?tipo=' + encodeURIComponent(tipoActual);
    document.getElementById('import-tipo').value = tipoActual;
    cargarFasesFiltro();
  }

  async function cargarFasesFiltro() {
    try {
      const res = await fetch(API + '?action=fases&tipo=' + encodeURIComponent(tipoActual));
      const data = await res.json();
      if (data.status !== 'ok') return;
      const sel = filtroFase;
      const cur = sel.value;
      sel.innerHTML = '<option value="">Todas</option>';
      data.fases.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f;
        opt.textContent = f;
        sel.appendChild(opt);
      });
      sel.value = cur;
    } catch (e) {}
  }

  document.querySelectorAll('.banco-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.banco-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      tipoActual = tab.dataset.tipo;
      pageActual = 1;
      updateHint();
      cargarLista();
    });
  });

  filtroFase.addEventListener('change', () => { pageActual = 1; cargarLista(); });
  document.getElementById('btn-refrescar').addEventListener('click', cargarLista);

  async function cargarLista() {
    document.getElementById('banco-loading').style.display = 'block';
    document.getElementById('banco-empty').style.display = 'none';
    const fase = filtroFase.value;
    let url = API + '?action=listar&tipo=' + encodeURIComponent(tipoActual) + '&page=' + pageActual;
    if (fase) url += '&fase=' + encodeURIComponent(fase);

    try {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message || 'Error');

      actualizarFiltroFases(data.items);
      renderTabla(data);
      renderPaginacion(data);
    } catch (e) {
      showMsg(e.message || 'Error al cargar', false);
      tbody.innerHTML = '';
    }
    document.getElementById('banco-loading').style.display = 'none';
  }

  function actualizarFiltroFases(items) {
    const fasesSet = new Set();
    items.forEach(r => fasesSet.add(r.fase));
    const current = filtroFase.value;
    const existing = Array.from(filtroFase.options).map(o => o.value).filter(v => v);
    fasesSet.forEach(f => {
      if (!existing.includes(String(f))) {
        const opt = document.createElement('option');
        opt.value = f;
        opt.textContent = f;
        filtroFase.appendChild(opt);
      }
    });
    filtroFase.value = current;
  }

  function cellClass(campo) {
    if (campo === 'pregunta') return 'col-pregunta';
    if (campo === 'lectura') return 'col-lectura';
    return '';
  }

  function truncar(val, max) {
    const s = String(val ?? '');
    return s.length > max ? s.substring(0, max - 1) + '…' : s;
  }

  function renderTabla(data) {
    const campos = TIPOS[tipoActual].campos;
    thead.innerHTML = '<tr><th>ID</th>' + campos.map(c =>
      '<th class="' + cellClass(c) + '">' + (labels[c] || c) + '</th>'
    ).join('') + '<th style="width:120px">Acciones</th></tr>';

    if (!data.items.length) {
      tbody.innerHTML = '';
      document.getElementById('banco-empty').style.display = 'block';
      return;
    }
    document.getElementById('banco-empty').style.display = 'none';

    tbody.innerHTML = data.items.map(row => {
      const cells = campos.map(c => {
        let v = row[c] ?? '';
        const max = (c === 'pregunta' || c === 'lectura') ? 120 : 50;
        v = truncar(v, max);
        return '<td class="' + cellClass(c) + '" title="' + escAttr(String(row[c] ?? '')) + '">' + escHtml(String(v)) + '</td>';
      }).join('');
      return '<tr><td>' + row.id + '</td>' + cells +
        '<td class="banco-actions">' +
        '<button type="button" class="btn-icon" title="Editar" data-edit="' + row.id + '"><i class="fas fa-edit"></i></button> ' +
        '<button type="button" class="btn-icon btn-danger" title="Eliminar" data-del="' + row.id + '"><i class="fas fa-trash"></i></button>' +
        '</td></tr>';
    }).join('');

    tbody.querySelectorAll('[data-edit]').forEach(btn => {
      btn.addEventListener('click', () => abrirEditar(parseInt(btn.dataset.edit, 10)));
    });
    tbody.querySelectorAll('[data-del]').forEach(btn => {
      btn.addEventListener('click', () => eliminar(parseInt(btn.dataset.del, 10)));
    });
  }

  function renderPaginacion(data) {
    const el = document.getElementById('banco-pagination');
    if (data.pages <= 1) { el.innerHTML = '<span>Total: ' + data.total + ' registro(s)</span>'; return; }
    let html = '<span>Total: ' + data.total + ' · Página ' + data.page + ' de ' + data.pages + '</span> ';
    if (data.page > 1) html += '<button type="button" data-p="' + (data.page - 1) + '">← Anterior</button> ';
    if (data.page < data.pages) html += '<button type="button" data-p="' + (data.page + 1) + '">Siguiente →</button>';
    el.innerHTML = html;
    el.querySelectorAll('[data-p]').forEach(b => {
      b.addEventListener('click', () => { pageActual = parseInt(b.dataset.p, 10); cargarLista(); });
    });
  }

  function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function escAttr(s) {
    return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
  }

  function renderCamposFormulario(valores) {
    const cont = document.getElementById('edit-campos');
    cont.innerHTML = '';
    TIPOS[tipoActual].campos.forEach(c => {
      const val = valores[c] ?? '';
      const isLong = c === 'pregunta' || c === 'lectura' || c === 'script_audio';
      let field;
      if (isLong) {
        const reqLong = (c === 'pregunta' || c === 'lectura') ? ' required' : '';
        const rows = c === 'script_audio' ? '6' : '4';
        field = '<textarea name="' + c + '" rows="' + rows + '"' + reqLong + '>' + escHtml(String(val)) + '</textarea>';
      } else {
        const req = c === 'nombre_audio' || c === 'nombre_lectura' ? '' : ' required';
        const placeholder = c === 'fase' ? ' placeholder="Ej. A1 1-4 o Windows"' : '';
        field = '<input name="' + c + '" type="text" value="' + escAttr(String(val)) + '"' + req + placeholder + '>';
      }
      cont.innerHTML += '<label>' + (labels[c] || c) + field + '</label>';
    });
  }

  async function abrirEditar(id) {
    const res = await fetch(API + '?action=obtener&tipo=' + encodeURIComponent(tipoActual) + '&id=' + id);
    const data = await res.json();
    if (data.status !== 'ok') { showMsg(data.message || 'Error', false); return; }

    document.getElementById('modal-titulo').textContent = 'Editar registro';
    document.getElementById('edit-action').value = 'guardar';
    document.getElementById('btn-submit-edit').textContent = 'Guardar cambios';
    document.getElementById('edit-tipo').value = tipoActual;
    document.getElementById('edit-id').value = id;
    renderCamposFormulario(data.item);
    document.getElementById('modal-editar').style.display = 'flex';
  }

  function abrirNueva() {
    document.getElementById('modal-titulo').textContent = 'Nueva pregunta — ' + TIPOS[tipoActual].label;
    document.getElementById('edit-action').value = 'crear';
    document.getElementById('btn-submit-edit').textContent = 'Agregar pregunta';
    document.getElementById('edit-tipo').value = tipoActual;
    document.getElementById('edit-id').value = '';
    const vacio = {};
    TIPOS[tipoActual].campos.forEach(c => { vacio[c] = ''; });
    renderCamposFormulario(vacio);
    document.getElementById('modal-editar').style.display = 'flex';
  }

  document.getElementById('btn-nueva-pregunta').addEventListener('click', abrirNueva);

  document.getElementById('modal-cerrar').addEventListener('click', cerrarModal);
  document.getElementById('btn-cancelar-edit').addEventListener('click', cerrarModal);
  function cerrarModal() { document.getElementById('modal-editar').style.display = 'none'; }

  document.getElementById('form-editar').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    if (fd.get('action') === 'crear') {
      fd.delete('id');
    }
    try {
      const res = await fetch(API, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      showMsg(data.message, true);
      cerrarModal();
      cargarLista();
    } catch (err) {
      showMsg(err.message || 'Error al guardar', false);
    }
  });

  async function eliminar(id) {
    if (!confirm('¿Eliminar este registro? Esta acción no se puede deshacer.')) return;
    const fd = new FormData();
    fd.append('action', 'eliminar');
    fd.append('tipo', tipoActual);
    fd.append('id', id);
    try {
      const res = await fetch(API, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      showMsg(data.message, true);
      cargarLista();
    } catch (err) {
      showMsg(err.message || 'Error al eliminar', false);
    }
  }

  document.getElementById('form-import-csv').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.set('tipo', tipoActual);
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
      const res = await fetch(API, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      showMsg(data.message, true);
      this.reset();
      cargarLista();
    } catch (err) {
      showMsg(err.message || 'Error al importar', false);
    }
    btn.disabled = false;
  });

  updateHint();
  cargarLista();
})();
</script>
