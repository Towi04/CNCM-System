<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_id']) || !hay_eval_puede_configurar()) {
    echo '<div class="alert">No autorizado.</div>';
    return;
}
try {
    $areas = hay_eval_listar_areas($pdo, false);
} catch (Throwable $e) {
    error_log('hay_config_rubrica: ' . $e->getMessage());
    echo '<div class="alert">No se pudo cargar la configuración HAY. Verifique que las tablas hay_* existan en la base de datos.</div>';
    return;
}
$idArea = (int) ($_GET['id_area'] ?? ($areas[0]['id_area'] ?? 0));
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_eval.css">

<div class="hay-eval-wrap">
  <h2>Configurar evaluación HAY</h2>
  <p style="color:#666; margin-bottom:14px;">Defina rubros, aspectos a evaluar y opciones con puntaje por área de trabajo.</p>
  <p style="color:#666; margin-bottom:10px; font-size:0.92rem;">
    La plantilla <strong>Profesor Inglés</strong> se carga desde <code>scripts/hay_xlsm_dump.txt</code> (MCERL, certificación, tecnología, etc.). Solo aplica a esa área.
  </p>

  <div class="hay-eval-toolbar">
    <label>Área</label>
    <select id="hay-cfg-area">
      <option value="0">— Seleccione —</option>
      <?php foreach ($areas as $a): ?>
      <option value="<?php echo (int) $a['id_area']; ?>"<?php echo (int) $a['id_area'] === $idArea ? ' selected' : ''; ?>>
        <?php echo htmlspecialchars($a['nombre'], ENT_QUOTES, 'UTF-8'); ?>
      </option>
      <?php endforeach; ?>
    </select>
    <button type="button" class="secondary" id="hay-btn-nueva-area">Nueva área</button>
    <button type="button" class="primary" id="hay-btn-seed">Importar Profesor Inglés (Excel)</button>
    <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.9rem;margin-left:8px;">
      <input type="checkbox" id="hay-seed-forzar" value="1"> Reimportar si ya existe
    </label>
    <button type="button" class="secondary" id="hay-btn-publicar">Publicar rúbrica</button>
  </div>

  <div id="hay-cfg-msg" class="catalog-alert" style="display:none;"></div>

  <div class="hay-tabs">
    <button type="button" class="hay-tab is-active" data-tab="rubrica">Rúbrica</button>
    <button type="button" class="hay-tab" data-tab="niveles">Niveles y salario</button>
    <button type="button" class="hay-tab" data-tab="matriz">Matriz capacitación</button>
  </div>

  <div id="hay-tab-rubrica" class="hay-tab-panel is-active">
    <div id="hay-rubrica-tree">Seleccione un área.</div>
    <div style="margin-top:16px; padding:14px; background:#f9f9f9; border-radius:10px;">
      <h4 style="margin:0 0 10px;">Agregar aspecto</h4>
      <input type="hidden" id="hay-new-id-rubro" value="0">
      <label>Rubro</label>
      <select id="hay-new-rubro" style="width:100%; margin:6px 0 10px; padding:8px;"></select>
      <label>Código (sin espacios)</label>
      <input type="text" id="hay-new-codigo" placeholder="nivel_mcerl" style="width:100%; margin:6px 0 10px; padding:8px;">
      <label>Nombre del aspecto</label>
      <input type="text" id="hay-new-nombre" placeholder="Nivel en el MCERL" style="width:100%; margin:6px 0 10px; padding:8px;">
      <button type="button" class="primary" id="hay-btn-add-aspecto">Agregar aspecto</button>
    </div>
  </div>

  <div id="hay-tab-niveles" class="hay-tab-panel">
    <p style="font-size:0.88rem; color:#666;">Cinco niveles por área (rangos de puntos y sueldo base sugerido).</p>
    <div id="hay-niveles-list"></div>
    <button type="button" class="secondary" id="hay-btn-add-nivel" style="margin-top:10px;">Guardar nivel (formulario abajo)</button>
    <div style="margin-top:14px; display:grid; grid-template-columns: repeat(auto-fill,minmax(140px,1fr)); gap:10px;">
      <input type="number" id="hay-nv-num" min="1" max="5" placeholder="Nº 1-5">
      <input type="text" id="hay-nv-nombre" placeholder="Nombre nivel">
      <input type="number" id="hay-nv-min" placeholder="Pts mín">
      <input type="number" id="hay-nv-max" placeholder="Pts máx">
      <input type="number" id="hay-nv-sueldo" step="0.01" placeholder="Sueldo base">
    </div>
    <button type="button" class="primary" id="hay-btn-save-nivel" style="margin-top:10px;">Guardar nivel</button>
  </div>

  <div id="hay-tab-matriz" class="hay-tab-panel">
    <p style="font-size:0.88rem; color:#666;">Capacitaciones obligatorias por nivel y extras mensuales (marcado manual por el jefe).</p>
    <div id="hay-cap-list"></div>
    <div style="margin-top:14px;">
      <input type="text" id="hay-cap-nombre" placeholder="Nombre capacitación" style="width:100%; padding:8px; margin-bottom:8px;">
      <select id="hay-cap-tipo" style="width:100%; padding:8px; margin-bottom:8px;">
        <option value="obligatoria_nivel">Obligatoria por nivel</option>
        <option value="mensual_extra">Extra mensual</option>
      </select>
      <button type="button" class="primary" id="hay-btn-save-cap">Agregar capacitación</button>
    </div>
  </div>
</div>

<script>
(function () {
  const api = 'php/hay_eval_config_api.php';
  const areaSel = document.getElementById('hay-cfg-area');
  const msg = document.getElementById('hay-cfg-msg');
  let rubricaCache = null;

  function showMsg(ok, text) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text;
  }

  function idArea() { return parseInt(areaSel?.value || '0', 10); }

  async function apiPost(action, body) {
    const fd = body instanceof FormData ? body : new URLSearchParams(body);
    if (!(body instanceof FormData)) fd.append('action', action);
    else fd.append('action', action);
    const { data } = await hayFetchJson(api, { method: 'POST', body: fd });
    return data;
  }

  function renderRubrica(r) {
    rubricaCache = r;
    const tree = document.getElementById('hay-rubrica-tree');
    const rubSel = document.getElementById('hay-new-rubro');
    if (!tree) return;
    if (!r || !r.rubros) { tree.textContent = 'Sin datos'; return; }
    rubSel.innerHTML = '';
    let html = '';
    r.rubros.forEach((rub) => {
      const o = document.createElement('option');
      o.value = rub.id_rubro;
      o.textContent = rub.titulo;
      rubSel.appendChild(o);
      html += '<div class="hay-rubro-block"><div class="hay-rubro-head">' + rub.titulo + '</div>';
      (rub.aspectos || []).forEach((asp) => {
        html += '<div class="hay-aspecto-row" data-aspecto="' + asp.id_aspecto + '">';
        html += '<div class="hay-aspecto-name">' + asp.nombre + ' <small style="color:#888;">(' + asp.codigo + ')</small></div>';
        html += '<table class="hay-opciones-table"><thead><tr><th>Opción</th><th>Pts</th><th></th></tr></thead><tbody>';
        (asp.opciones || []).forEach((op) => {
          html += '<tr><td>' + op.etiqueta + '</td><td>' + op.puntos + '</td><td><button type="button" class="link hay-del-op" data-id="' + op.id_opcion + '">Quitar</button></td></tr>';
        });
        html += '</tbody></table>';
        html += '<div style="margin-top:8px; display:flex; gap:6px; flex-wrap:wrap;">';
        html += '<input type="text" class="hay-add-op-label" placeholder="Etiqueta" data-asp="' + asp.id_aspecto + '" style="flex:2; padding:6px;">';
        html += '<input type="number" class="hay-add-op-pts" placeholder="Pts" data-asp="' + asp.id_aspecto + '" style="width:80px; padding:6px;">';
        html += '<button type="button" class="secondary hay-add-op-btn" data-asp="' + asp.id_aspecto + '">+ Opción</button>';
        html += '</div></div>';
      });
      html += '</div>';
    });
    tree.innerHTML = html;
    tree.querySelectorAll('.hay-add-op-btn').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const aspId = btn.dataset.asp;
        const row = btn.closest('.hay-aspecto-row');
        const label = row.querySelector('.hay-add-op-label')?.value?.trim();
        const pts = parseInt(row.querySelector('.hay-add-op-pts')?.value || '0', 10);
        if (!label) return;
        const d = await apiPost('guardar_opcion', { id_aspecto: aspId, etiqueta: label, puntos: pts });
        if (d.status === 'ok') loadRubrica(); else showMsg(false, d.message);
      });
    });
    tree.querySelectorAll('.hay-del-op').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('¿Quitar esta opción?')) return;
        await apiPost('desactivar_opcion', { id_opcion: btn.dataset.id });
        loadRubrica();
      });
    });
  }

  async function loadRubrica() {
    const id = idArea();
    if (!id) return;
    const { data } = await hayFetchJson(api + '?action=rubrica&id_area=' + id);
    if (data.status === 'ok') renderRubrica(data.rubrica);
  }

  async function loadNiveles() {
    const id = idArea();
    const el = document.getElementById('hay-niveles-list');
    if (!id || !el) return;
    const { data } = await hayFetchJson(api + '?action=listar_niveles&id_area=' + id);
    if (data.status !== 'ok') return;
    el.innerHTML = (data.niveles || []).map((n) =>
      '<p><strong>' + n.numero + '. ' + n.nombre_display + '</strong> — ' + n.puntos_min + ' a ' + n.puntos_max +
      (n.sueldo_base ? ' · ' + n.sueldo_base : '') + '</p>'
    ).join('') || '<p style="color:#888;">Sin niveles definidos.</p>';
  }

  async function loadCaps() {
    const id = idArea();
    const el = document.getElementById('hay-cap-list');
    if (!id || !el) return;
    const { data } = await hayFetchJson(api + '?action=listar_capacitaciones&id_area=' + id);
    if (data.status !== 'ok') return;
    el.innerHTML = (data.capacitaciones || []).map((c) =>
      '<p>' + c.nombre + ' <em>(' + c.tipo + ')</em></p>'
    ).join('') || '<p style="color:#888;">Sin capacitaciones.</p>';
  }

  document.querySelectorAll('.hay-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.hay-tab').forEach((t) => t.classList.remove('is-active'));
      document.querySelectorAll('.hay-tab-panel').forEach((p) => p.classList.remove('is-active'));
      tab.classList.add('is-active');
      document.getElementById('hay-tab-' + tab.dataset.tab)?.classList.add('is-active');
      if (tab.dataset.tab === 'niveles') loadNiveles();
      if (tab.dataset.tab === 'matriz') loadCaps();
    });
  });

  areaSel?.addEventListener('change', () => {
    loadRubrica();
    loadNiveles();
    loadCaps();
  });

  document.getElementById('hay-btn-add-aspecto')?.addEventListener('click', async () => {
    const d = await apiPost('guardar_aspecto', {
      id_rubro: document.getElementById('hay-new-rubro')?.value,
      codigo: document.getElementById('hay-new-codigo')?.value,
      nombre: document.getElementById('hay-new-nombre')?.value,
    });
    if (d.status === 'ok') { showMsg(true, 'Aspecto guardado'); loadRubrica(); }
    else showMsg(false, d.message);
  });

  document.getElementById('hay-btn-publicar')?.addEventListener('click', async () => {
    const d = await apiPost('publicar', { id_area: idArea() });
    showMsg(d.status === 'ok', d.message || '');
  });

  document.getElementById('hay-btn-seed')?.addEventListener('click', async () => {
    const forzar = document.getElementById('hay-seed-forzar')?.checked ? '1' : '0';
    if (forzar === '1' && !confirm('¿Reimportar la rúbrica de Profesor Inglés desde el Excel? Solo si aún no hay evaluaciones guardadas.')) {
      return;
    }
    const d = await apiPost('seed_profesor_ingles', { forzar });
    showMsg(d.status === 'ok', d.message || '');
    if (d.id_area) { areaSel.value = String(d.id_area); loadRubrica(); loadNiveles(); }
  });

  document.getElementById('hay-btn-save-nivel')?.addEventListener('click', async () => {
    const d = await apiPost('guardar_nivel', {
      id_area: idArea(),
      numero: document.getElementById('hay-nv-num')?.value,
      nombre_display: document.getElementById('hay-nv-nombre')?.value,
      puntos_min: document.getElementById('hay-nv-min')?.value,
      puntos_max: document.getElementById('hay-nv-max')?.value,
      sueldo_base: document.getElementById('hay-nv-sueldo')?.value,
    });
    showMsg(d.status === 'ok', d.message || 'Nivel guardado');
    loadNiveles();
  });

  document.getElementById('hay-btn-save-cap')?.addEventListener('click', async () => {
    const d = await apiPost('guardar_capacitacion', {
      id_area: idArea(),
      nombre: document.getElementById('hay-cap-nombre')?.value,
      tipo: document.getElementById('hay-cap-tipo')?.value,
      obligatoria: 1,
    });
    showMsg(d.status === 'ok', 'Capacitación guardada');
    loadCaps();
  });

  document.getElementById('hay-btn-nueva-area')?.addEventListener('click', async () => {
    const clave = prompt('Clave del área (ej. ASESOR_VENTAS):');
    const nombre = prompt('Nombre visible:');
    if (!clave || !nombre) return;
    const d = await apiPost('guardar_area', { clave, nombre, roles: 'asesor' });
    showMsg(d.status === 'ok', d.message || 'Área creada');
    if (d.id_area) location.reload();
  });

  if (idArea()) { loadRubrica(); loadNiveles(); }
})();
</script>
