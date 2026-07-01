<?php
require_once __DIR__ . '/../config.php';
if (!planeacion_prompt_puede_configurar()) {
    echo '<div class="alert">Solo coordinación y dirección pueden configurar las plantillas de planeación con IA.</div>';
    return;
}

planeacion_prompt_ensure_schema($pdo);

$especialidades = $pdo->query(
    'SELECT id_especialidad, clave, nombre FROM especialidades WHERE activo = 1 ORDER BY orden, nombre'
)->fetchAll(PDO::FETCH_ASSOC);

$idEsp = (int) ($_GET['id_especialidad'] ?? ($especialidades[0]['id_especialidad'] ?? 0));
$apiUrl = hay_asset_url('php/planeacion_prompt_api.php');
$placeholders = planeacion_prompt_placeholders();
?>
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-robot"></i> Plantillas IA — Planeación de clase</h2>
  </div>

  <p style="color:#666; margin-top:0; max-width:920px;">
    Defina el prompt que la IA usará al generar planeaciones para cada especialidad.
    Use etiquetas como <code>&lt;&lt;Tema&gt;&gt;</code> o <code>&lt;&lt;Intereses&gt;&gt;</code> para insertar datos del grupo,
    la fase y el perfil de los alumnos. Cada especialidad puede tener su propia metodología pedagógica.
  </p>

  <div id="prompt-msg" class="catalog-alert" style="display:none;"></div>

  <div class="catalog-toolbar" style="align-items:flex-end;">
    <div class="field" style="min-width:280px;">
      <label>Especialidad</label>
      <select id="prompt-esp-select">
        <?php foreach ($especialidades as $e): ?>
          <option value="<?php echo (int) $e['id_especialidad']; ?>"<?php echo (int) $e['id_especialidad'] === $idEsp ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($e['clave'] . ' — ' . $e['nombre']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <button type="button" class="secondary" id="btn-prompt-reset">Restaurar predeterminada</button>
    </div>
    <div class="field">
      <button type="button" class="secondary" id="btn-prompt-preview">Vista previa</button>
    </div>
    <div class="field">
      <button type="button" class="primary" id="btn-prompt-save">Guardar plantilla</button>
    </div>
  </div>

  <div style="display:grid; grid-template-columns: minmax(0,1fr) 280px; gap:20px; align-items:start;">
    <div>
      <label for="prompt-plantilla" style="font-weight:600; display:block; margin-bottom:6px;">
        Prompt / instrucciones para la IA
        <span id="prompt-badge-custom" style="display:none; font-weight:normal; color:#b45309; font-size:0.85rem;"> · personalizada</span>
        <span id="prompt-badge-default" style="display:none; font-weight:normal; color:#666; font-size:0.85rem;"> · usando plantilla predeterminada</span>
      </label>
      <textarea id="prompt-plantilla" rows="28" style="width:100%; font-family:Consolas, 'Courier New', monospace; font-size:0.88rem; line-height:1.45; padding:12px; border:1px solid #ddd; border-radius:8px; resize:vertical;" spellcheck="false"></textarea>
      <p style="color:#888; font-size:0.85rem; margin:8px 0 0;">
        La IA recibirá este texto con las etiquetas ya sustituidas por los datos reales al pulsar «Sugerir con IA» en Planeaciones.
      </p>
    </div>

    <aside style="background:#f8f9fa; border:1px solid #e8e8e8; border-radius:10px; padding:14px;">
      <h4 style="margin:0 0 10px; font-size:0.95rem;">Etiquetas disponibles</h4>
      <p style="margin:0 0 10px; font-size:0.82rem; color:#666;">Clic para insertar en el cursor:</p>
      <div id="prompt-tags" style="display:flex; flex-direction:column; gap:6px; max-height:520px; overflow:auto;">
        <?php foreach ($placeholders as $tag => $desc): ?>
          <button type="button" class="prompt-tag-btn" data-tag="<?php echo htmlspecialchars($tag); ?>" title="<?php echo htmlspecialchars($desc); ?>" style="text-align:left; padding:6px 10px; border:1px solid #ddd; border-radius:6px; background:#fff; cursor:pointer; font-size:0.82rem;">
            <code>&lt;&lt;<?php echo htmlspecialchars($tag); ?>&gt;&gt;</code>
          </button>
        <?php endforeach; ?>
      </div>
    </aside>
  </div>
</div>

<div class="catalog-modal" id="modal-prompt-preview">
  <div class="catalog-modal__panel" style="max-width:900px; max-height:90vh; display:flex; flex-direction:column;">
    <h3 style="margin-top:0;">Vista previa del prompt resuelto</h3>
    <p id="preview-fuente" style="color:#666; font-size:0.88rem; margin:0 0 10px;"></p>
    <pre id="preview-texto" style="flex:1; overflow:auto; background:#1e1e1e; color:#e8e8e8; padding:14px; border-radius:8px; font-size:0.82rem; white-space:pre-wrap; margin:0 0 14px;"></pre>
    <div style="display:flex; justify-content:flex-end;">
      <button type="button" id="btn-cerrar-preview">Cerrar</button>
    </div>
  </div>
</div>

<script>
(function () {
  const apiUrl = <?php echo json_encode($apiUrl, JSON_UNESCAPED_UNICODE); ?>;
  const selectEsp = document.getElementById('prompt-esp-select');
  const textarea = document.getElementById('prompt-plantilla');
  const msg = document.getElementById('prompt-msg');
  const modalPreview = document.getElementById('modal-prompt-preview');
  if (modalPreview && modalPreview.parentElement !== document.body) document.body.appendChild(modalPreview);

  let dirty = false;

  function showMsg(ok, text) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text;
  }

  function setBadge(esPersonalizada) {
    const custom = document.getElementById('prompt-badge-custom');
    const def = document.getElementById('prompt-badge-default');
    if (custom) custom.style.display = esPersonalizada ? '' : 'none';
    if (def) def.style.display = esPersonalizada ? 'none' : '';
  }

  async function cargar(idEsp) {
    if (!idEsp) return;
    try {
      const res = await fetch(apiUrl + '?action=get&id_especialidad=' + encodeURIComponent(idEsp), {
        headers: { 'X-Requested-With': 'fetch' },
      });
      const data = await res.json();
      if (data.status !== 'ok') {
        showMsg(false, data.message || 'Error al cargar');
        return;
      }
      textarea.value = data.plantilla || '';
      setBadge(!!data.es_personalizada);
      dirty = false;
    } catch (e) {
      showMsg(false, 'Error de red al cargar la plantilla.');
    }
  }

  selectEsp?.addEventListener('change', () => {
    if (dirty && !confirm('Hay cambios sin guardar. ¿Cambiar de especialidad?')) {
      selectEsp.value = selectEsp.dataset.last || selectEsp.value;
      return;
    }
    selectEsp.dataset.last = selectEsp.value;
    cargar(parseInt(selectEsp.value, 10));
    const params = new URLSearchParams();
    params.set('id_especialidad', selectEsp.value);
    if (typeof history !== 'undefined' && history.replaceState) {
      history.replaceState(null, '', '?' + params.toString());
    }
  });

  textarea?.addEventListener('input', () => { dirty = true; });

  document.querySelectorAll('.prompt-tag-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const tag = btn.getAttribute('data-tag');
      const insert = '<<' + tag + '>>';
      const ta = textarea;
      const start = ta.selectionStart;
      const end = ta.selectionEnd;
      const before = ta.value.substring(0, start);
      const after = ta.value.substring(end);
      ta.value = before + insert + after;
      ta.selectionStart = ta.selectionEnd = start + insert.length;
      ta.focus();
      dirty = true;
    });
  });

  document.getElementById('btn-prompt-save')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id_especialidad', selectEsp.value);
    fd.append('plantilla', textarea.value);
    try {
      const res = await fetch(apiUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      showMsg(data.status === 'ok', data.message || '');
      if (data.status === 'ok') {
        dirty = false;
        setBadge(textarea.value.trim() !== '');
      }
    } catch (e) {
      showMsg(false, 'Error al guardar.');
    }
  });

  document.getElementById('btn-prompt-reset')?.addEventListener('click', async () => {
    if (!confirm('¿Restaurar la plantilla predeterminada de esta especialidad? Se perderá la personalización guardada.')) return;
    const fd = new FormData();
    fd.append('action', 'reset');
    fd.append('id_especialidad', selectEsp.value);
    try {
      const res = await fetch(apiUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      showMsg(data.status === 'ok', data.message || '');
      if (data.status === 'ok') {
        textarea.value = data.plantilla || '';
        dirty = false;
        setBadge(false);
      }
    } catch (e) {
      showMsg(false, 'Error al restaurar.');
    }
  });

  document.getElementById('btn-prompt-preview')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'preview');
    fd.append('id_especialidad', selectEsp.value);
    fd.append('plantilla', textarea.value);
    try {
      const res = await fetch(apiUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      if (data.status !== 'ok') {
        showMsg(false, data.message || 'Error en vista previa');
        return;
      }
      document.getElementById('preview-texto').textContent = data.prompt_resuelto || '';
      document.getElementById('preview-fuente').textContent =
        data.fuente_datos === 'grupo'
          ? 'Datos de un grupo real (si está disponible).'
          : 'Datos de ejemplo — al generar la planeación se usarán los datos del grupo seleccionado.';
      modalPreview.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    } catch (e) {
      showMsg(false, 'Error de red en vista previa.');
    }
  });

  document.getElementById('btn-cerrar-preview')?.addEventListener('click', () => {
    modalPreview.classList.remove('is-open');
    document.body.style.overflow = '';
  });

  if (selectEsp) {
    selectEsp.dataset.last = selectEsp.value;
    cargar(parseInt(selectEsp.value, 10));
  }
})();
</script>
