<?php
require_once __DIR__ . '/../config.php';
if (!combo_puede_administrar()) {
    echo '<div class="alert">Solo el supervisor puede gestionar colegiaturas con descuento.</div>';
    return;
}

$reglas = combo_listar_reglas($pdo);
$especialidades = $pdo->query(
    'SELECT id_especialidad, clave, nombre, costo_inscripcion, costo_inscripcion_referencia, costo_inscripcion_apoyo,
            costo_mensualidad, costo_pronto_pago, costo_semanal, costo_anual
     FROM especialidades WHERE activo = 1 ORDER BY orden, nombre'
)->fetchAll(PDO::FETCH_ASSOC);
$tiposRegla = combo_tipos_regla();
$categoriasPromo = combo_categorias_promocion();
$saveUrl = hay_asset_url('php/combo_regla_save.php');
?>
<link rel="stylesheet" href="css/admin_catalogo.css?v=20260604">
<link rel="stylesheet" href="css/punto_venta.css">
<link rel="stylesheet" href="css/hay_icon_buttons.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-tags"></i> Colegiaturas con descuento</h2>
    <button type="button" class="primary" id="btn-nueva-regla-combo">Nueva regla</button>
  </div>

  <p style="color:#666;">
    Defina tarifas por <strong>combinación</strong> (ej. Inglés + Informática, Inglés Kids + Comp Kids) o por
    <strong>promoción</strong> (cartas, hot sale, buen fin). En inscripción, recepción elegirá si aplica un descuento.
    El descuento por combinación solo se mantiene mientras el alumno esté activo en <em>todas</em> las materias de la regla;
    si deja una, vuelve a la colegiatura congelada sin descuento.
  </p>

  <div id="resp-combo" class="catalog-alert" style="display:none;"></div>

  <?php if (empty($reglas)): ?>
    <p>No hay reglas registradas.</p>
  <?php else: ?>
    <div class="catalog-table-wrap hay-dt-panel">
      <table class="catalog-table display" id="tabla-reglas-descuento" style="width:100%;">
        <thead>
          <tr><th>Regla</th><th>Tipo</th><th>Especialidades</th><th>Tarifas</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($reglas as $r): ?>
            <tr data-regla='<?php echo htmlspecialchars(json_encode($r, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>'>
              <td>
                <strong><?php echo htmlspecialchars($r['nombre']); ?></strong>
                <?php if (!empty($r['motivo'])): ?>
                  <br><small><?php echo htmlspecialchars($r['motivo']); ?></small>
                <?php endif; ?>
              </td>
              <td><span class="catalog-badge catalog-badge--muted"><?php echo htmlspecialchars(combo_etiqueta_regla($r)); ?></span></td>
              <td><?php echo htmlspecialchars($r['claves_combo']); ?></td>
              <td style="font-size:0.85rem;">
                <?php foreach ($r['tarifas'] as $t): ?>
                  <?php echo htmlspecialchars($t['clave']); ?>:
                  M <?php echo catalog_format_mxn($t['costo_mensualidad']); ?><br>
                <?php endforeach; ?>
              </td>
              <td class="catalog-actions--icons">
                <button type="button" class="btn-icon-only btn-icon-only--edit btn-edit-regla" title="Editar">
                  <i class="fas fa-pen"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div id="modal-regla-combo" class="catalog-modal">
  <div class="catalog-modal__panel" style="width:min(640px,96vw); max-height:90vh; overflow:auto;">
    <h3 style="margin-top:0;">Regla de colegiatura con descuento</h3>
    <input type="hidden" id="regla-id" value="0">
    <div class="catalog-form-grid">
      <div class="full">
        <label>Nombre de la regla</label>
        <input type="text" id="regla-nombre" placeholder="Ej. Inglés + Informática adultos">
      </div>
      <div>
        <label>Tipo</label>
        <select id="regla-tipo">
          <?php foreach ($tiposRegla as $k => $lbl): ?>
            <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($lbl); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="wrap-categoria-promo">
        <label>Campaña</label>
        <select id="regla-categoria-promo">
          <?php foreach ($categoriasPromo as $k => $lbl): ?>
            <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($lbl); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="full">
        <label>Especialidades (Ctrl+clic para varias)</label>
        <select id="regla-claves" multiple size="6" style="width:100%;">
          <?php foreach ($especialidades as $e): ?>
            <option value="<?php echo htmlspecialchars($e['clave']); ?>"
              data-id="<?php echo (int) $e['id_especialidad']; ?>"
              data-insc="<?php echo (float) $e['costo_inscripcion']; ?>"
              data-insc-ref="<?php echo (float) ($e['costo_inscripcion_referencia'] ?? ($e['costo_inscripcion'] * 2)); ?>"
              data-insc-apoyo="<?php echo (float) ($e['costo_inscripcion_apoyo'] ?? $e['costo_inscripcion']); ?>"
              data-men="<?php echo (float) $e['costo_mensualidad']; ?>"
              data-pp="<?php echo (float) $e['costo_pronto_pago']; ?>"
              data-sem="<?php echo (float) $e['costo_semanal']; ?>"
              data-anual="<?php echo (float) ($e['costo_anual'] ?? 0); ?>">
              <?php echo htmlspecialchars($e['clave'] . ' — ' . $e['nombre']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="full">
        <label>Motivo / notas</label>
        <input type="text" id="regla-motivo" placeholder="Ej. Cartas febrero, paquete infantil…">
      </div>
    </div>
    <div id="regla-tarifas-grid" class="combo-tarifas-wrap"></div>
    <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
      <button type="button" id="regla-cancel">Cancelar</button>
      <button type="button" class="primary" id="regla-save">Guardar regla</button>
    </div>
  </div>
</div>

<script>
(function() {
  const modal = document.getElementById('modal-regla-combo');
  if (modal && modal.parentElement !== document.body) document.body.appendChild(modal);
  const saveUrl = <?php echo json_encode($saveUrl, JSON_UNESCAPED_UNICODE); ?>;

  if (window.HayDataTable) {
    HayDataTable.init('#tabla-reglas-descuento', { order: [[0, 'asc']], columnDefs: [{ orderable: false, targets: 4 }] });
  }

  function toggleTipoPromo() {
    const esPromo = document.getElementById('regla-tipo')?.value === 'promocion';
    const wrap = document.getElementById('wrap-categoria-promo');
    if (wrap) wrap.style.display = esPromo ? '' : 'none';
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function buildTarifaGrid() {
    const sel = document.getElementById('regla-claves');
    const grid = document.getElementById('regla-tarifas-grid');
    if (!grid || !sel) return;
    const selected = Array.from(sel.selectedOptions);
    if (!selected.length) {
      grid.innerHTML = '';
      return;
    }
    grid.innerHTML =
      '<div class="combo-tarifas-head">' +
      '<h4>Tarifa por especialidad</h4>' +
      '<span>Defina inscripción y colegiatura con descuento para cada especialidad elegida.</span>' +
      '</div>';
    const campos = [
      { key: 'insc_ref', label: 'Insc. referencia', data: 'inscRef' },
      { key: 'insc_apoyo', label: 'Insc. apoyo (normal)', data: 'inscApoyo' },
      { key: 'insc', label: 'Insc. con descuento', data: 'insc' },
      { key: 'men', label: 'Mensualidad', data: 'men' },
      { key: 'pp', label: 'Pronto pago', data: 'pp' },
      { key: 'sem', label: 'Semanal', data: 'sem' },
      { key: 'anual', label: 'Anual (si aplica)', data: 'anual' },
    ];
    selected.forEach((opt) => {
      const id = opt.dataset.id;
      const card = document.createElement('article');
      card.className = 'combo-tarifa-card';
      let fieldsHtml = '';
      campos.forEach((c) => {
        fieldsHtml +=
          '<div class="combo-tarifa-field">' +
          '<label>' + c.label + '</label>' +
          '<input type="number" step="0.01" min="0" data-id="' + id + '" data-campo="' + c.key + '" value="' +
          escapeHtml(opt.dataset[c.data] || '0') + '">' +
          '</div>';
      });
      card.innerHTML =
        '<div class="combo-tarifa-card__title">' + escapeHtml(opt.textContent) + '</div>' +
        '<div class="combo-tarifa-card__grid">' + fieldsHtml + '</div>';
      grid.appendChild(card);
    });
  }

  function modalTieneDatos() {
    const nombre = (document.getElementById('regla-nombre')?.value || '').trim();
    const motivo = (document.getElementById('regla-motivo')?.value || '').trim();
    const claves = document.getElementById('regla-claves')?.selectedOptions?.length || 0;
    return nombre !== '' || motivo !== '' || claves > 0;
  }

  function intentarCerrarModal() {
    if (modalTieneDatos() && !window.confirm('¿Salir sin guardar? Se perderán los datos del registro.')) {
      return;
    }
    cerrarModal();
  }

  function abrirModal(data) {
    document.getElementById('regla-id').value = data ? data.id_regla : '0';
    document.getElementById('regla-nombre').value = data ? data.nombre : '';
    document.getElementById('regla-motivo').value = data ? (data.motivo || '') : '';
    const tipo = data ? (data.tipo || 'combinacion') : 'combinacion';
    document.getElementById('regla-tipo').value = tipo === 'promocion' ? 'promocion' : 'combinacion';
    document.getElementById('regla-categoria-promo').value = data?.categoria_promo || 'promocion';
    toggleTipoPromo();
    const sel = document.getElementById('regla-claves');
    const claves = data ? (data.claves_combo || '').split(',') : [];
    Array.from(sel.options).forEach(o => { o.selected = claves.includes(o.value); });
    buildTarifaGrid();
    if (data && data.tarifas) {
      data.tarifas.forEach(t => {
        document.querySelectorAll('#regla-tarifas-grid input[data-id="' + t.id_especialidad + '"]').forEach(inp => {
          const map = {
            insc: t.costo_inscripcion,
            insc_ref: t.costo_inscripcion_referencia,
            insc_apoyo: t.costo_inscripcion_apoyo,
            men: t.costo_mensualidad,
            pp: t.costo_pronto_pago,
            sem: t.costo_semanal,
            anual: t.costo_anual,
          };
          if (map[inp.dataset.campo] != null) inp.value = map[inp.dataset.campo];
        });
      });
    }
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function cerrarModal() {
    modal.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  document.getElementById('regla-tipo')?.addEventListener('change', toggleTipoPromo);
  document.getElementById('regla-claves')?.addEventListener('change', buildTarifaGrid);
  document.getElementById('btn-nueva-regla-combo')?.addEventListener('click', () => abrirModal(null));
  document.getElementById('regla-cancel')?.addEventListener('click', intentarCerrarModal);

  document.querySelectorAll('.btn-edit-regla').forEach(btn => {
    btn.addEventListener('click', () => {
      try { abrirModal(JSON.parse(btn.closest('tr').getAttribute('data-regla'))); } catch (e) {}
    });
  });

  document.getElementById('regla-save')?.addEventListener('click', async () => {
    const claves = Array.from(document.getElementById('regla-claves').selectedOptions).map(o => o.value);
    const tarifas = [];
    document.querySelectorAll('#regla-tarifas-grid input[data-id]').forEach(inp => {
      const id = inp.dataset.id;
      let t = tarifas.find(x => String(x.id_especialidad) === id);
      if (!t) { t = { id_especialidad: +id }; tarifas.push(t); }
      const map = {
        insc: 'costo_inscripcion',
        insc_ref: 'costo_inscripcion_referencia',
        insc_apoyo: 'costo_inscripcion_apoyo',
        men: 'costo_mensualidad',
        pp: 'costo_pronto_pago',
        sem: 'costo_semanal',
        anual: 'costo_anual',
      };
      t[map[inp.dataset.campo]] = inp.value;
    });
    const fd = new FormData();
    fd.append('id_regla', document.getElementById('regla-id').value);
    fd.append('nombre', document.getElementById('regla-nombre').value);
    fd.append('tipo', document.getElementById('regla-tipo').value);
    fd.append('categoria_promo', document.getElementById('regla-categoria-promo').value);
    claves.forEach(c => fd.append('claves[]', c));
    fd.append('motivo', document.getElementById('regla-motivo').value);
    fd.append('tarifas', JSON.stringify(tarifas));
    const r = await fetch(saveUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await r.json();
    const resp = document.getElementById('resp-combo');
    resp.style.display = 'block';
    resp.textContent = data.message;
    resp.className = 'catalog-alert catalog-alert--' + (data.status === 'ok' ? 'ok' : 'error');
    if (data.status === 'ok') {
      cerrarModal();
      if (data.seccion) cargarSeccion(data.seccion);
    }
  });

  toggleTipoPromo();
})();
</script>
