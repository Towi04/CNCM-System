<?php
require_once __DIR__ . '/../config.php';
if (!catalog_puede_administrar()) {
    echo '<div class="alert">Solo administradores y gerentes pueden gestionar especialidades.</div>';
    return;
}

$filtros = [
    'q' => trim($_GET['q'] ?? ''),
    'visible' => $_GET['visible'] ?? '',
    'es_fija' => $_GET['es_fija'] ?? '',
    'activo' => $_GET['activo'] ?? '',
];
$rows = catalog_listar_especialidades($pdo, $filtros);
$modalidades = catalog_modalidades_etiquetas();
$edadDefaults = [];
foreach (array_keys($modalidades) as $mk) {
    $edadDefaults[$mk] = catalog_edad_default_modalidad($mk);
}
$espDeleteUrl = hay_asset_url('php/especialidad_delete.php');
$espToggleUrl = hay_asset_url('php/especialidad_toggle.php');
$puedeEditarCostos = catalog_puede_editar_costos();
operativo_cncm_ensure_schema($pdo);
?>
<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-graduation-cap"></i> Especialidades (cursos)</h2>
    <button type="button" class="primary" id="btn-nueva-especialidad">Nueva especialidad</button>
  </div>

  <p style="color:#666; margin-top:0;">
    Cada especialidad define precio <strong>referencia</strong> (ventas) y <strong>apoyo educativo</strong> (cobro habitual).
    <?php if ($puedeEditarCostos): ?>
      Solo supervisión puede editar tarifas; al inscribir se congelan en el alumno.
    <?php else: ?>
      Las tarifas las edita supervisión; usted puede ajustar modalidad, edades y visibilidad.
    <?php endif; ?>
  </p>

  <div id="respuesta-especialidades" class="catalog-alert" style="display:none;"></div>

  <form class="catalog-toolbar" method="get" id="form-filtros-esp" onsubmit="return false;">
    <div class="field">
      <label>Buscar</label>
      <input type="search" name="q" id="filtro-q-esp" value="<?php echo htmlspecialchars($filtros['q']); ?>" placeholder="Clave, nombre…">
    </div>
    <div class="field">
      <label>Visible</label>
      <select name="visible" id="filtro-visible-esp">
        <option value="">Todas</option>
        <option value="1"<?php echo $filtros['visible'] === '1' ? ' selected' : ''; ?>>Sí</option>
        <option value="0"<?php echo $filtros['visible'] === '0' ? ' selected' : ''; ?>>No</option>
      </select>
    </div>
    <div class="field">
      <label>Tipo</label>
      <select name="es_fija" id="filtro-fija-esp">
        <option value="">Todas</option>
        <option value="1"<?php echo $filtros['es_fija'] === '1' ? ' selected' : ''; ?>>Fija</option>
        <option value="0"<?php echo $filtros['es_fija'] === '0' ? ' selected' : ''; ?>>Temporal</option>
      </select>
    </div>
    <div class="field">
      <label>Estado</label>
      <select name="activo" id="filtro-activo-esp">
        <option value="">Todas</option>
        <option value="1"<?php echo $filtros['activo'] === '1' ? ' selected' : ''; ?>>Activas</option>
        <option value="0"<?php echo $filtros['activo'] === '0' ? ' selected' : ''; ?>>Inactivas</option>
      </select>
    </div>
    <div class="field" style="align-self:flex-end;">
      <button type="button" class="primary" id="btn-buscar-esp">Buscar</button>
    </div>
  </form>

  <div class="catalog-table-wrap">
    <?php if (empty($rows)): ?>
      <p>No hay especialidades con esos filtros.</p>
    <?php else: ?>
      <table class="catalog-table display hay-paged-table" id="tabla-especialidades" style="width:100%;">
        <thead>
          <tr>
            <th>Clave</th>
            <th>Nombre</th>
            <th>Modalidad</th>
            <th>Edad</th>
            <th>Inscripción</th>
            <th>Colegiatura</th>
            <th>Cuatrim. / Anual</th>
            <th>Duración</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $e):
            $stCartas = $pdo->prepare('SELECT * FROM especialidad_tarifa_cartas WHERE id_especialidad = ? LIMIT 1');
            $stCartas->execute([(int) $e['id_especialidad']]);
            $cartas = $stCartas->fetch(PDO::FETCH_ASSOC) ?: [];
            $e['cartas_inscripcion_ref'] = $cartas['costo_inscripcion_ref'] ?? null;
            $e['cartas_inscripcion_apoyo'] = $cartas['costo_inscripcion_apoyo'] ?? null;
            $e['cartas_mensualidad_ref'] = $cartas['costo_mensualidad_ref'] ?? null;
            $e['cartas_mensualidad_apoyo'] = $cartas['costo_mensualidad_apoyo'] ?? null;
            $mod = $e['modalidad'] ?? 'regular';
            $modLabel = $modalidades[$mod] ?? $mod;
            $edadTxt = catalog_edad_rango_texto(
                isset($e['edad_min']) && $e['edad_min'] !== '' ? (int) $e['edad_min'] : null,
                isset($e['edad_max']) && $e['edad_max'] !== '' ? (int) $e['edad_max'] : null
            );
            $inscCuat = !empty($e['inscripcion_por_cuatrimestre']);
            $cuat = (float) ($e['costo_cuatrimestre'] ?? 0);
            $anual = (float) ($e['costo_anual'] ?? 0);
          ?>
            <tr data-row='<?php echo htmlspecialchars(json_encode($e, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>'>
              <td><strong><?php echo htmlspecialchars($e['clave']); ?></strong></td>
              <td>
                <?php echo htmlspecialchars($e['nombre']); ?>
                <?php if (!empty($e['descripcion'])): ?>
                  <br><small style="color:#888;"><?php echo htmlspecialchars(mb_strimwidth($e['descripcion'], 0, 80, '…')); ?></small>
                <?php endif; ?>
              </td>
              <td><span class="catalog-badge catalog-badge--muted"><?php echo htmlspecialchars($modLabel); ?></span></td>
              <td><?php echo htmlspecialchars($edadTxt); ?></td>
              <td>
                <?php echo catalog_format_mxn((float) $e['costo_inscripcion']); ?>
                <?php if ($inscCuat): ?><br><small>/ cuatrimestre</small><?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars(catalog_colegiatura_resumen($e)); ?></td>
              <td>
                <?php if ($cuat > 0): ?>
                  <?php echo catalog_format_mxn($cuat); ?>/cuat.
                  <?php if (!empty($e['parciales_por_cuatrimestre'])): ?>
                    <br><small><?php echo (int) $e['parciales_por_cuatrimestre']; ?> parcial(es)/cuat.</small>
                  <?php endif; ?>
                <?php elseif ($anual > 0): ?>
                  <?php echo catalog_format_mxn($anual); ?>/año
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td>
                <?php echo (int) $e['duracion_meses']; ?> mes(es)
                <?php if (!empty($e['duracion_semanas'])): ?>
                  <br><small><?php echo (int) $e['duracion_semanas']; ?> sem.</small>
                <?php endif; ?>
              </td>
              <td>
                <div class="catalog-estado-actions" data-id="<?php echo (int) $e['id_especialidad']; ?>">
                  <button type="button" class="btn-icon btn-esp-toggle" data-campo="visible" title="<?php echo (int) $e['visible'] ? 'Ocultar' : 'Mostrar'; ?>">
                    <i class="fas fa-eye<?php echo (int) $e['visible'] ? '' : '-slash'; ?>"></i>
                  </button>
                  <button type="button" class="btn-icon btn-esp-toggle" data-campo="activo" title="<?php echo (int) $e['activo'] ? 'Desactivar' : 'Activar'; ?>">
                    <i class="fas fa-<?php echo (int) $e['activo'] ? 'times' : 'check'; ?>"></i>
                  </button>
                  <button type="button" class="btn-icon btn-esp-toggle" data-campo="es_fija" title="<?php echo (int) $e['es_fija'] ? 'Marcar temporal' : 'Marcar fija'; ?>">
                    <i class="fas fa-<?php echo (int) $e['es_fija'] ? 'thumbtack' : 'calendar-alt'; ?>"></i>
                  </button>
                </div>
              </td>
              <td>
                <div class="catalog-actions">
                  <button type="button" class="btn-editar-esp" title="Editar"><i class="fas fa-pen"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="catalog-modal" id="modal-especialidad">
  <div class="catalog-modal__panel" style="max-width:720px;">
    <h3 id="modal-esp-titulo" style="margin-top:0;">Especialidad</h3>
    <form id="form-especialidad" action="php/especialidad_save.php" method="POST" data-no-global-ajax>
      <input type="hidden" name="id_especialidad" id="esp-id" value="0">
      <div class="catalog-form-grid">
        <div>
          <label>Clave</label>
          <input type="text" name="clave" id="esp-clave" required maxlength="30" placeholder="ING">
        </div>
        <div>
          <label>Nombre</label>
          <input type="text" name="nombre" id="esp-nombre" required maxlength="120">
        </div>
        <div class="full">
          <label>Descripción</label>
          <textarea name="descripcion" id="esp-descripcion" rows="2"></textarea>
        </div>

        <div class="full" style="border-top:1px solid #eee; padding-top:12px; margin-top:4px;">
          <strong>Modalidad y edad</strong>
        </div>
        <div>
          <label>Modalidad</label>
          <select name="modalidad" id="esp-modalidad">
            <?php foreach ($modalidades as $k => $lbl): ?>
              <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($lbl); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Duración fase (semanas)</label>
          <input type="number" name="duracion_fase_semanas" id="esp-duracion-fase" min="1" value="4">
        </div>
        <div>
          <label>Edad mínima (años)</label>
          <input type="number" name="edad_min" id="esp-edad-min" min="0" max="99" placeholder="Ej. 13">
        </div>
        <div>
          <label>Edad máxima (años)</label>
          <input type="number" name="edad_max" id="esp-edad-max" min="0" max="99" placeholder="Ej. 19">
          <small style="color:#888;">Vacío = sin límite en ese extremo</small>
        </div>

        <div class="full" style="border-top:1px solid #eee; padding-top:12px; margin-top:4px;">
          <strong>Costos (referencia / apoyo educativo)</strong>
          <?php if (!$puedeEditarCostos): ?>
            <br><small style="color:#888;">Solo supervisión puede modificar montos.</small>
          <?php endif; ?>
        </div>
        <div>
          <label>Inscripción referencia ($)</label>
          <input type="number" name="costo_inscripcion_referencia" id="esp-inscripcion-ref" min="0" step="0.01" value="0" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
        </div>
        <div>
          <label>Inscripción apoyo ($)</label>
          <input type="number" name="costo_inscripcion_apoyo" id="esp-inscripcion-apoyo" min="0" step="0.01" value="0" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
          <input type="hidden" name="costo_inscripcion" id="esp-inscripcion">
        </div>
        <div id="wrap-insc-cuat" style="display:flex; align-items:flex-end;">
          <label style="margin:0;"><input type="checkbox" name="inscripcion_por_cuatrimestre" id="esp-insc-cuat" value="1"> Inscripción por cuatrimestre</label>
        </div>
        <div id="wrap-costo-mensual">
          <label>Mensualidad ref. / apoyo ($)</label>
          <input type="number" name="costo_mensualidad_referencia" id="esp-mensualidad-ref" min="0" step="0.01" value="0" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
          <input type="number" name="costo_mensualidad_apoyo" id="esp-mensualidad-apoyo" min="0" step="0.01" value="0" style="margin-top:6px;" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
          <input type="hidden" name="costo_mensualidad" id="esp-mensualidad">
        </div>
        <div id="wrap-costo-pronto">
          <label>Pronto pago ref. / apoyo ($)</label>
          <input type="number" name="costo_pronto_pago_referencia" id="esp-pronto-ref" min="0" step="0.01" value="0" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
          <input type="number" name="costo_pronto_pago_apoyo" id="esp-pronto-apoyo" min="0" step="0.01" value="0" style="margin-top:6px;" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
          <input type="hidden" name="costo_pronto_pago" id="esp-pronto">
          <small style="color:#888;">Primeros 6 días del mes</small>
        </div>
        <div id="wrap-costo-semanal">
          <label>Semanal ref. / apoyo ($)</label>
          <input type="number" name="costo_semanal_referencia" id="esp-semanal-ref" min="0" step="0.01" value="0" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
          <input type="number" name="costo_semanal_apoyo" id="esp-semanal-apoyo" min="0" step="0.01" value="0" style="margin-top:6px;" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
          <input type="hidden" name="costo_semanal" id="esp-semanal">
        </div>
        <div class="full" style="border-top:1px dashed #eee; padding-top:10px;">
          <strong>Promoción cartas (opcional)</strong>
        </div>
        <div>
          <label>Inscripción cartas ref. / apoyo</label>
          <input type="number" name="cartas_inscripcion_ref" id="esp-cartas-insc-ref" min="0" step="0.01" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
          <input type="number" name="cartas_inscripcion_apoyo" id="esp-cartas-insc-apoyo" min="0" step="0.01" value="450" style="margin-top:6px;" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
        </div>
        <div>
          <label>Mensualidad cartas ref. / apoyo</label>
          <input type="number" name="cartas_mensualidad_ref" id="esp-cartas-men-ref" min="0" step="0.01" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
          <input type="number" name="cartas_mensualidad_apoyo" id="esp-cartas-men-apoyo" min="0" step="0.01" style="margin-top:6px;" <?php echo $puedeEditarCostos ? '' : 'readonly'; ?>>
        </div>
        <div class="full" style="border-top:1px dashed #eee; padding-top:10px;">
          <strong>Venta temporal (opcional)</strong>
        </div>
        <div>
          <label>Inicio venta</label>
          <input type="date" name="fecha_inicio_venta" id="esp-fecha-inicio">
        </div>
        <div>
          <label>Fin venta</label>
          <input type="date" name="fecha_fin_venta" id="esp-fecha-fin">
        </div>
        <div class="full" style="border-top:1px solid #eee; padding-top:12px;">
          <strong>Descuento al referidor (alumno que recomienda)</strong>
        </div>
        <div>
          <label>Tipo de beneficio</label>
          <select name="referido_tipo" id="esp-referido-tipo">
            <option value="semana_colegiatura">Una semana de colegiatura (usa costo semanal)</option>
            <option value="monto_fijo">Monto fijo ($)</option>
            <option value="inscripcion_fija">Monto fijo de inscriución</option>
          </select>
        </div>
        <div>
          <label>Valor personalizado ($)</label>
          <input type="number" name="referido_valor" id="esp-referido-valor" min="0" step="0.01" placeholder="Vacío = automático">
          <small style="color:#888;">Ej. curso de verano con descuento distinto</small>
        </div>
        <div id="wrap-costo-cuatrimestre">
          <label>Colegiatura cuatrimestre ($)</label>
          <input type="number" name="costo_cuatrimestre" id="esp-cuatrimestre" min="0" step="0.01" value="0">
          <small style="color:#888;">Pago cada 4 meses (prep. escolarizada u otros)</small>
        </div>
        <div id="wrap-parciales-cuat">
          <label>Parciales por cuatrimestre</label>
          <input type="number" name="parciales_por_cuatrimestre" id="esp-parciales-cuat" min="0" max="12" value="0">
        </div>
        <div id="wrap-costo-anual">
          <label>Colegiatura anual ($)</label>
          <input type="number" name="costo_anual" id="esp-anual" min="0" step="0.01" value="0">
          <small style="color:#888;">Pago anual (prep. abierta u otros programas)</small>
        </div>

        <div>
          <label>Duración (meses)</label>
          <input type="number" name="duracion_meses" id="esp-meses" min="1" value="12">
        </div>
        <div>
          <label>Duración (semanas, opcional)</label>
          <input type="number" name="duracion_semanas" id="esp-semanas" min="1" placeholder="Ej. 48">
        </div>
        <div>
          <label>Orden</label>
          <input type="number" name="orden" id="esp-orden" min="0" value="0">
        </div>
        <div class="full" style="display:flex; flex-wrap:wrap; gap:16px;">
          <input type="hidden" name="es_fija" id="esp-fija-hidden" value="1">
          <input type="hidden" name="visible" id="esp-visible-hidden" value="1">
          <input type="hidden" name="activo" id="esp-activo-hidden" value="1">
          <label><input type="checkbox" name="inscripcion_abierta" id="esp-inscripcion-abierta" value="1" checked> Inscripción abierta</label>
          <p style="margin:0; color:#666; font-size:0.85rem; width:100%;">Visible, activa y fija/temporal se cambian con los iconos en la tabla.</p>
        </div>
      </div>
      <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
        <button type="button" id="btn-cerrar-esp">Cancelar</button>
        <button type="submit" class="primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<div class="catalog-modal" id="modal-esp-sustituir">
  <div class="catalog-modal__panel" style="max-width:560px;">
    <h3 id="modal-esp-sust-titulo" style="margin-top:0;">Desactivar especialidad</h3>
    <p id="esp-sust-intro" style="color:#555; margin:0 0 12px;"></p>
    <div id="esp-sust-grupos-wrap" style="display:none; margin-bottom:14px;">
      <p id="esp-sust-num-grupos" style="margin:0 0 8px;"></p>
      <ul id="esp-sust-lista-grupos" style="margin:0; padding-left:20px; max-height:140px; overflow:auto; color:#444;"></ul>
    </div>
    <div id="esp-sust-select-wrap" style="display:none;">
      <label for="esp-sust-select" style="font-weight:600; display:block; margin-bottom:6px;">Sustituir en todos los grupos por</label>
      <select id="esp-sust-select" style="width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:8px;">
        <option value="">— Elija especialidad —</option>
      </select>
      <p id="esp-sust-sin-opciones" style="display:none; color:#b45309; margin:8px 0 0; font-size:0.9rem;">
        No hay otra especialidad activa. Active o cree una antes de desactivar esta.
      </p>
    </div>
    <p id="esp-sust-sin-grupos" style="display:none; color:#666; margin:0;">No hay grupos vinculados; solo se desactivará en el catálogo.</p>
    <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
      <button type="button" id="btn-cancelar-esp-sust">Cancelar</button>
      <button type="button" class="primary danger" id="btn-confirmar-esp-sust">Desactivar</button>
    </div>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('modal-especialidad');
  const modalSust = document.getElementById('modal-esp-sustituir');
  [modal, modalSust].forEach((m) => { if (m && m.parentElement !== document.body) document.body.appendChild(m); });
  const espDeleteUrl = <?php echo json_encode($espDeleteUrl, JSON_UNESCAPED_UNICODE); ?>;
  const espToggleUrl = <?php echo json_encode($espToggleUrl, JSON_UNESCAPED_UNICODE); ?>;
  let espSustPendiente = null;
  const form = document.getElementById('form-especialidad');
  const msg = document.getElementById('respuesta-especialidades');
  const edadDefaults = <?php echo json_encode($edadDefaults, JSON_UNESCAPED_UNICODE); ?>;

  if (window.HayDataTable) {
    HayDataTable.init('#tabla-especialidades', {
      order: [[1, 'asc']],
      columnDefs: [{ orderable: false, targets: [8, 9] }],
    });
  }

  function syncHiddenEstado(data) {
    const defs = { es_fija: 'esp-fija-hidden', visible: 'esp-visible-hidden', activo: 'esp-activo-hidden' };
    Object.keys(defs).forEach((k) => {
      const el = document.getElementById(defs[k]);
      if (!el) return;
      if (data) el.value = Number(data[k]) ? '1' : '0';
      else if (k === 'es_fija') el.value = '1';
      else el.value = '1';
    });
  }

  function refreshEstadoIcons(wrap, row) {
    if (!wrap || !row) return;
    const vis = wrap.querySelector('[data-campo="visible"]');
    const act = wrap.querySelector('[data-campo="activo"]');
    const fij = wrap.querySelector('[data-campo="es_fija"]');
    if (vis) {
      vis.title = Number(row.visible) ? 'Ocultar' : 'Mostrar';
      vis.innerHTML = '<i class="fas fa-eye' + (Number(row.visible) ? '' : '-slash') + '"></i>';
    }
    if (act) {
      act.title = Number(row.activo) ? 'Desactivar' : 'Activar';
      act.innerHTML = '<i class="fas fa-' + (Number(row.activo) ? 'times' : 'check') + '"></i>';
    }
    if (fij) {
      fij.title = Number(row.es_fija) ? 'Marcar temporal' : 'Marcar fija';
      fij.innerHTML = '<i class="fas fa-' + (Number(row.es_fija) ? 'thumbtack' : 'calendar-alt') + '"></i>';
    }
  }

  function showMsg(ok, text) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text;
  }

  function toggleCostFields() {
    const mod = document.getElementById('esp-modalidad')?.value || 'regular';
    const prepEsc = mod === 'prep_escolarizada';
    const prepAb = mod === 'prep_abierta';
    const regularLike = ['regular', 'kids', 'extensivo'].includes(mod);

    const show = (id, on) => {
      const el = document.getElementById(id);
      if (el) el.style.display = on ? '' : 'none';
    };

    show('wrap-costo-mensual', regularLike && !prepEsc && !prepAb);
    show('wrap-costo-pronto', regularLike && !prepEsc && !prepAb);
    show('wrap-costo-semanal', regularLike && !prepEsc && !prepAb);
    // Cuatrimestre, anualidad e inscripción por cuatrimestre siempre visibles
    show('wrap-costo-cuatrimestre', true);
    show('wrap-parciales-cuat', true);
    show('wrap-insc-cuat', true);
    show('wrap-costo-anual', true);
  }

  function aplicarEdadSugerida(force) {
    const mod = document.getElementById('esp-modalidad')?.value || 'regular';
    const def = edadDefaults[mod] || {};
    const minEl = document.getElementById('esp-edad-min');
    const maxEl = document.getElementById('esp-edad-max');
    if (force || !minEl.value) minEl.value = def.min != null ? def.min : '';
    if (force || !maxEl.value) maxEl.value = def.max != null ? def.max : '';
  }

  function abrirModal(data) {
    document.getElementById('modal-esp-titulo').textContent = data ? 'Editar especialidad' : 'Nueva especialidad';
    document.getElementById('esp-id').value = data ? data.id_especialidad : '0';
    document.getElementById('esp-clave').value = data ? data.clave : '';
    document.getElementById('esp-nombre').value = data ? data.nombre : '';
    document.getElementById('esp-descripcion').value = data ? (data.descripcion || '') : '';
    document.getElementById('esp-modalidad').value = data ? (data.modalidad || 'regular') : 'regular';
    document.getElementById('esp-duracion-fase').value = data ? (data.duracion_fase_semanas || 4) : '4';
    document.getElementById('esp-edad-min').value = data && data.edad_min != null && data.edad_min !== '' ? data.edad_min : '';
    document.getElementById('esp-edad-max').value = data && data.edad_max != null && data.edad_max !== '' ? data.edad_max : '';
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v ?? ''; };
    set('esp-inscripcion-ref', data ? (data.costo_inscripcion_referencia ?? data.costo_inscripcion) : '0');
    set('esp-inscripcion-apoyo', data ? (data.costo_inscripcion_apoyo ?? data.costo_inscripcion) : '0');
    set('esp-inscripcion', data ? (data.costo_inscripcion_apoyo ?? data.costo_inscripcion) : '0');
    document.getElementById('esp-insc-cuat').checked = data ? Number(data.inscripcion_por_cuatrimestre) === 1 : false;
    set('esp-mensualidad-ref', data ? (data.costo_mensualidad_referencia ?? '') : '0');
    set('esp-mensualidad-apoyo', data ? (data.costo_mensualidad_apoyo ?? data.costo_mensualidad) : '0');
    set('esp-mensualidad', data ? (data.costo_mensualidad_apoyo ?? data.costo_mensualidad) : '0');
    set('esp-pronto-ref', data ? (data.costo_pronto_pago_referencia ?? '') : '0');
    set('esp-pronto-apoyo', data ? (data.costo_pronto_pago_apoyo ?? data.costo_pronto_pago) : '0');
    set('esp-pronto', data ? (data.costo_pronto_pago_apoyo ?? data.costo_pronto_pago) : '0');
    set('esp-semanal-ref', data ? (data.costo_semanal_referencia ?? '') : '0');
    set('esp-semanal-apoyo', data ? (data.costo_semanal_apoyo ?? data.costo_semanal) : '0');
    set('esp-semanal', data ? (data.costo_semanal_apoyo ?? data.costo_semanal) : '0');
    set('esp-cartas-insc-ref', data?.cartas_inscripcion_ref ?? '');
    set('esp-cartas-insc-apoyo', data?.cartas_inscripcion_apoyo ?? '450');
    set('esp-cartas-men-ref', data?.cartas_mensualidad_ref ?? '');
    set('esp-cartas-men-apoyo', data?.cartas_mensualidad_apoyo ?? '');
    set('esp-fecha-inicio', data?.fecha_inicio_venta ?? '');
    set('esp-fecha-fin', data?.fecha_fin_venta ?? '');
    document.getElementById('esp-cuatrimestre').value = data && data.costo_cuatrimestre ? data.costo_cuatrimestre : '0';
    document.getElementById('esp-parciales-cuat').value = data ? (data.parciales_por_cuatrimestre || 0) : '0';
    document.getElementById('esp-anual').value = data && data.costo_anual ? data.costo_anual : '0';
    document.getElementById('esp-meses').value = data ? data.duracion_meses : '12';
    document.getElementById('esp-semanas').value = data && data.duracion_semanas ? data.duracion_semanas : '';
    document.getElementById('esp-orden').value = data ? data.orden : '0';
    document.getElementById('esp-inscripcion-abierta').checked = data ? Number(data.inscripcion_abierta ?? 1) === 1 : true;
    syncHiddenEstado(data);
    document.getElementById('esp-referido-tipo').value = data?.referido_tipo || 'semana_colegiatura';
    document.getElementById('esp-referido-valor').value = data?.referido_valor ?? '';
    if (!data) aplicarEdadSugerida(true);
    toggleCostFields();
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function cerrarModal() {
    modal.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  document.getElementById('esp-modalidad')?.addEventListener('change', () => {
    aplicarEdadSugerida(true);
    toggleCostFields();
  });
  document.getElementById('esp-insc-cuat')?.addEventListener('change', toggleCostFields);

  document.getElementById('btn-nueva-especialidad')?.addEventListener('click', () => abrirModal(null));
  document.getElementById('btn-cerrar-esp')?.addEventListener('click', cerrarModal);

  document.querySelectorAll('.btn-editar-esp').forEach((btn) => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      try { abrirModal(JSON.parse(tr.getAttribute('data-row'))); } catch (err) { console.error(err); }
    });
  });

  function cerrarModalSust() {
    modalSust?.classList.remove('is-open');
    document.body.style.overflow = '';
    espSustPendiente = null;
  }

  function abrirModalSust(preview) {
    const esp = preview.especialidad || {};
    espSustPendiente = { id: esp.id_especialidad, requiere: !!preview.requiere_sustituto };
    document.getElementById('esp-sust-intro').textContent =
      'Va a desactivar «' + (esp.nombre || '') + '» (' + (esp.clave || '') + ').';
    const wrapGrupos = document.getElementById('esp-sust-grupos-wrap');
    const wrapSelect = document.getElementById('esp-sust-select-wrap');
    const sinGrupos = document.getElementById('esp-sust-sin-grupos');
    const num = preview.num_grupos || 0;
    const sel = document.getElementById('esp-sust-select');
    const sinOpc = document.getElementById('esp-sust-sin-opciones');
    const btnOk = document.getElementById('btn-confirmar-esp-sust');

    if (num > 0) {
      wrapGrupos.style.display = '';
      sinGrupos.style.display = 'none';
      document.getElementById('esp-sust-num-grupos').textContent =
        num + ' grupo(s) registrado(s) con esta especialidad se actualizarán en la base de datos.';
      const lista = document.getElementById('esp-sust-lista-grupos');
      const muestra = preview.grupos_muestra || [];
      let html = '';
      muestra.forEach((g) => {
        const pl = g.plantel_nombre ? ' · ' + g.plantel_nombre : '';
        html += '<li>' + (g.clave || g.id_grupo) + pl + '</li>';
      });
      if (num > muestra.length) {
        html += '<li style="color:#888;">… y ' + (num - muestra.length) + ' más</li>';
      }
      lista.innerHTML = html || '<li>(sin detalle)</li>';
      wrapSelect.style.display = '';
      sel.innerHTML = '<option value="">— Elija especialidad —</option>';
      (preview.sustitutos || []).forEach((s) => {
        const o = document.createElement('option');
        o.value = s.id_especialidad;
        o.textContent = (s.clave || '') + ' — ' + (s.nombre || '');
        sel.appendChild(o);
      });
      const haySust = (preview.sustitutos || []).length > 0;
      sel.disabled = !haySust;
      sinOpc.style.display = haySust ? 'none' : '';
      btnOk.disabled = preview.requiere_sustituto && !haySust;
    } else {
      wrapGrupos.style.display = 'none';
      wrapSelect.style.display = 'none';
      sinGrupos.style.display = '';
      sinOpc.style.display = 'none';
      btnOk.disabled = false;
    }
    modalSust?.classList.add('is-open');
  }

  async function confirmarDesactivarEsp() {
    if (!espSustPendiente) return;
    const sel = document.getElementById('esp-sust-select');
    const idSust = sel && sel.value ? parseInt(sel.value, 10) : 0;
    if (espSustPendiente.requiere && (!idSust || idSust <= 0)) {
      showMsg(false, 'Seleccione la especialidad de reemplazo para los grupos.');
      return;
    }
    const fd = new FormData();
    fd.append('id_especialidad', espSustPendiente.id);
    if (idSust > 0) fd.append('id_especialidad_sustituta', idSust);
    const btnOk = document.getElementById('btn-confirmar-esp-sust');
    btnOk.disabled = true;
    try {
      const res = await fetch(espDeleteUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      showMsg(data.status === 'ok', data.message || '');
      if (data.status === 'ok') {
        cerrarModalSust();
        if (data.seccion) cargarSeccion(data.seccion);
      }
    } catch (err) {
      showMsg(false, 'Error de red al desactivar.');
    } finally {
      btnOk.disabled = false;
    }
  }

  document.getElementById('btn-cancelar-esp-sust')?.addEventListener('click', cerrarModalSust);
  document.getElementById('btn-confirmar-esp-sust')?.addEventListener('click', confirmarDesactivarEsp);

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const apoyoInsc = document.getElementById('esp-inscripcion-apoyo')?.value || '0';
    fd.set('costo_inscripcion', apoyoInsc);
    fd.set('costo_mensualidad', document.getElementById('esp-mensualidad-apoyo')?.value || '0');
    fd.set('costo_pronto_pago', document.getElementById('esp-pronto-apoyo')?.value || '0');
    fd.set('costo_semanal', document.getElementById('esp-semanal-apoyo')?.value || '0');
    const chkMap = {
      inscripcion_abierta: 'esp-inscripcion-abierta',
      inscripcion_por_cuatrimestre: 'esp-insc-cuat',
    };
    Object.keys(chkMap).forEach((n) => {
      if (!document.getElementById(chkMap[n]).checked) fd.delete(n);
    });
    const res = await fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();
    showMsg(data.status === 'ok', data.message || '');
    if (data.status === 'ok' && data.seccion) {
      cerrarModal();
      cargarSeccion(data.seccion);
    }
  });

  document.getElementById('btn-buscar-esp')?.addEventListener('click', () => {
    const params = new URLSearchParams();
    const q = document.getElementById('filtro-q-esp').value;
    const visible = document.getElementById('filtro-visible-esp').value;
    const es_fija = document.getElementById('filtro-fija-esp').value;
    const activo = document.getElementById('filtro-activo-esp').value;
    if (q) params.set('q', q);
    if (visible !== '') params.set('visible', visible);
    if (es_fija !== '') params.set('es_fija', es_fija);
    if (activo !== '') params.set('activo', activo);
    cargarSeccion('admin_especialidades', params);
  });
})();
</script>
