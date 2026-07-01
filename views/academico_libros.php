<?php
require_once __DIR__ . '/../config.php';
academico_libro_ensure_schema($pdo);

if (!academico_libro_puede_gestionar()) {
    echo '<div class="alert">Sin permiso para gestionar libros.</div>';
    return;
}

$especialidades = $pdo->query(
    'SELECT id_especialidad, clave, nombre FROM especialidades WHERE activo = 1 ORDER BY orden ASC, nombre ASC'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$api = hay_asset_url('php/academico_libro_api.php');
$pdftotext = academico_libro_pdftotext_disponible();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/hay_buttons.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-book"></i> Libros institucionales</h2>
    <p style="color:#666;">Suba versiones PDF por especialidad, indexe para el Tutor IA y active la versión que ven los alumnos.</p>
  </div>

  <?php if (!$pdftotext): ?>
  <div class="catalog-alert catalog-alert--error" style="margin-bottom:12px;">
    <strong>pdftotext no detectado.</strong>
    Suba <code>pdftotext</code> y <code>pdfinfo</code> (binarios <strong>Linux</strong>, no .exe) en
    <code>bin/poppler/</code> con permiso 755. Guía: <code>docs/POPPLER_HOSTING.md</code>.
    Los alumnos pueden leer PDFs en Mis libros aunque falte Poppler.
  </div>
  <?php endif; ?>

  <div id="libros-msg" class="catalog-alert" style="display:none; margin-bottom:12px;"></div>

  <div class="welcome-card" style="padding:16px; margin-bottom:16px;">
    <h3 style="margin:0 0 10px;">Nuevo libro (catálogo)</h3>
    <form id="form-nuevo-libro" style="display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:10px; align-items:end;">
      <div>
        <label>Especialidad</label>
        <select name="id_especialidad" required style="width:100%; padding:8px;">
          <option value="">—</option>
          <?php foreach ($especialidades as $e): ?>
          <option value="<?php echo (int) $e['id_especialidad']; ?>"><?php echo htmlspecialchars(($e['clave'] ?? '') . ' — ' . ($e['nombre'] ?? '')); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Tipo</label>
        <select name="tipo" required style="width:100%; padding:8px;">
          <option value="studentbook">Student book</option>
          <option value="workbook">Workbook</option>
          <option value="libro_profesor">Libro profesor</option>
          <option value="guia_profesor">Guía profesor</option>
        </select>
      </div>
      <div>
        <label>Título</label>
        <input name="titulo" required maxlength="200" placeholder="Ej. ING Student Book" style="width:100%; padding:8px;">
      </div>
      <button type="submit" class="primary">Crear</button>
    </form>
  </div>

  <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
    <button type="button" class="secondary" id="btn-sync-moodle"><i class="fas fa-sync"></i> Sincronizar Moodle</button>
    <select id="filtro-esp" style="padding:8px;">
      <option value="">Todas las especialidades</option>
      <?php foreach ($especialidades as $e): ?>
      <option value="<?php echo (int) $e['id_especialidad']; ?>"><?php echo htmlspecialchars($e['clave'] ?? ''); ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div id="libros-list"><p style="color:#888;">Cargando…</p></div>
</div>

<div class="catalog-modal" id="modal-version" hidden>
  <div class="catalog-modal__dialog" style="max-width:520px;">
    <h3>Subir versión PDF</h3>
    <form id="form-version">
      <input type="hidden" name="id_libro" id="ver-id-libro">
      <div style="margin-bottom:10px;">
        <label>Etiqueta de versión (ej. 2025.1)</label>
        <input name="etiqueta" required maxlength="40" style="width:100%; padding:8px;">
      </div>
      <div style="margin-bottom:10px;">
        <label>Archivo PDF</label>
        <input type="file" name="pdf" accept="application/pdf,.pdf" required>
      </div>
      <label><input type="checkbox" name="activo_alumno" value="1"> Activar para alumnos al subir</label><br>
      <label><input type="checkbox" name="activo_rag" value="1" checked> Activar para Tutor IA (RAG)</label>
      <div style="margin-top:14px;">
        <button type="submit" class="primary">Subir</button>
        <button type="button" class="secondary" id="btn-cerrar-version">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const api = <?php echo json_encode($api, JSON_UNESCAPED_UNICODE); ?>;
  const list = document.getElementById('libros-list');
  const msg = document.getElementById('libros-msg');
  const modal = document.getElementById('modal-version');
  const filtro = document.getElementById('filtro-esp');

  function showMsg(text, ok) {
    if (!msg) return;
    msg.style.display = text ? 'block' : 'none';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text || '';
  }

  async function cargarLibros() {
    const idEsp = filtro?.value || '';
    const url = api + '?action=listar' + (idEsp ? '&id_especialidad=' + encodeURIComponent(idEsp) : '');
    const r = await fetch(url, { credentials: 'same-origin' });
    const d = await r.json();
    if (d.status !== 'ok') throw new Error(d.message || 'Error');
    if (!d.libros?.length) {
      list.innerHTML = '<p>No hay libros registrados. Cree uno arriba.</p>';
      return;
    }
    let html = '<table class="catalog-table" style="width:100%;"><thead><tr><th>Especialidad</th><th>Tipo</th><th>Título</th><th>Versiones</th><th>Alumno / RAG</th><th></th></tr></thead><tbody>';
    d.libros.forEach((lb) => {
      html += '<tr><td>' + esc(lb.esp_clave) + '</td><td>' + esc(lb.tipo) + '</td><td>' + esc(lb.titulo) + '</td>'
        + '<td>' + (lb.num_versiones || 0) + '</td>'
        + '<td>' + esc(lb.version_alumno || '—') + ' / ' + esc(lb.version_rag || '—') + '</td>'
        + '<td><button type="button" class="secondary btn-ver-libro" data-id="' + lb.id_libro + '">Versiones</button></td></tr>';
    });
    html += '</tbody></table>';
    list.innerHTML = html;
    list.querySelectorAll('.btn-ver-libro').forEach((b) => b.addEventListener('click', () => verVersiones(b.dataset.id)));
  }

  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;');
  }

  let currentLibroId = null;

  async function verVersiones(idLibro) {
    currentLibroId = idLibro;
    const r = await fetch(api + '?action=versiones&id_libro=' + idLibro, { credentials: 'same-origin' });
    const d = await r.json();
    if (d.status !== 'ok') { alert(d.message); return; }
    let html = '<h3>Versiones del libro #' + idLibro + '</h3>'
      + '<button type="button" class="primary" id="btn-nueva-ver" style="margin-bottom:10px;">+ Nueva versión PDF</button>';
    if (!d.versiones?.length) {
      html += '<p>Sin versiones aún.</p>';
    } else {
      html += '<table class="catalog-table"><thead><tr><th>Etiqueta</th><th>Páginas</th><th>Estado</th><th>Chunks / Emb.</th><th>Alumno</th><th>RAG</th><th></th></tr></thead><tbody>';
      d.versiones.forEach((v) => {
        html += '<tr><td>' + esc(v.etiqueta) + '</td><td>' + (v.num_paginas || '—') + '</td><td>' + esc(v.estado_indexacion) + '</td>'
          + '<td>' + (v.chunks || 0) + ' / ' + (v.embeddings || 0) + '</td>'
          + '<td>' + (v.activo_alumno == 1 ? '✓' : '—') + '</td><td>' + (v.activo_rag == 1 ? '✓' : '—') + '</td><td>'
          + '<button type="button" class="secondary btn-act-alu" data-id="' + v.id_version + '">Alumno</button> '
          + '<button type="button" class="secondary btn-act-rag" data-id="' + v.id_version + '">RAG</button> '
          + '<button type="button" class="secondary btn-idx" data-id="' + v.id_version + '">Indexar</button></td></tr>';
      });
      html += '</tbody></table>';
    }
    html += '<button type="button" class="secondary" id="btn-volver-libros" style="margin-top:12px;">← Volver</button>';
    list.innerHTML = html;
    document.getElementById('btn-volver-libros')?.addEventListener('click', cargarLibros);
    document.getElementById('btn-nueva-ver')?.addEventListener('click', () => {
      document.getElementById('ver-id-libro').value = idLibro;
      modal.hidden = false;
    });
    list.querySelectorAll('.btn-act-alu').forEach((b) => b.addEventListener('click', () => activar(b.dataset.id, 'alumno')));
    list.querySelectorAll('.btn-act-rag').forEach((b) => b.addEventListener('click', () => activar(b.dataset.id, 'rag')));
    list.querySelectorAll('.btn-idx').forEach((b) => b.addEventListener('click', () => indexar(b.dataset.id)));
  }

  async function activar(id, modo) {
    const fd = new FormData();
    fd.append('action', 'activar_version');
    fd.append('id_version', id);
    fd.append('modo', modo);
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    showMsg(d.message, d.status === 'ok');
    if (d.status === 'ok' && currentLibroId) verVersiones(currentLibroId);
  }

  async function indexar(id) {
    if (!confirm('Indexar PDF (páginas + embeddings). Puede tardar varios minutos.')) return;
    showMsg('Indexando… no cierre esta ventana.', true);
    const fd = new FormData();
    fd.append('action', 'indexar');
    fd.append('id_version', id);
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    showMsg(d.message, d.status === 'ok');
    if (d.status === 'ok' && currentLibroId) verVersiones(currentLibroId);
  }

  document.getElementById('form-nuevo-libro')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('action', 'crear_libro');
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    showMsg(d.message, d.status === 'ok');
    if (d.status === 'ok') { e.target.reset(); cargarLibros(); }
  });

  document.getElementById('form-version')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('action', 'subir_version');
    if (e.target.activo_alumno?.checked) fd.set('activo_alumno', '1');
    if (e.target.activo_rag?.checked) fd.set('activo_rag', '1');
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    showMsg(d.message, d.status === 'ok');
    if (d.status === 'ok') { modal.hidden = true; e.target.reset(); cargarLibros(); }
  });

  document.getElementById('btn-cerrar-version')?.addEventListener('click', () => { modal.hidden = true; });
  document.getElementById('btn-sync-moodle')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'sync_moodle');
    if (filtro?.value) fd.append('id_especialidad', filtro.value);
    showMsg('Sincronizando Moodle…', true);
    const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    showMsg(d.message, d.status === 'ok');
  });

  filtro?.addEventListener('change', cargarLibros);
  cargarLibros().catch((e) => { list.innerHTML = '<p class="alert">' + e.message + '</p>'; });
})();
</script>
