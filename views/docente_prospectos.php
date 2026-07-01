<?php
require_once __DIR__ . '/../config.php';
if (!docente_prospecto_puede_gestionar()) {
    echo '<div class="alert">Sin permiso para reclutamiento docente.</div>';
    return;
}

$filtro = trim((string) ($_GET['estado'] ?? ''));
$rows = docente_prospecto_listar($pdo, $filtro !== '' ? $filtro : null);
$estados = [
    'nuevo' => 'Nuevo',
    'clase_muestra_agendada' => 'Clase muestra agendada',
    'evaluado' => 'Evaluado',
    'apto_disc' => 'Apto para DISC',
    'disc_completo' => 'DISC completo',
    'contratado' => 'Contratado',
    'no_contratado' => 'No contratado',
    'bolsa' => 'Bolsa',
];
$hayAreas = function_exists('hay_eval_listar_areas') ? hay_eval_listar_areas($pdo) : [];
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">
<link rel="stylesheet" href="css/hay_icon_buttons.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2>Reclutamiento docente</h2>
    <div style="display:flex; gap:8px;">
      <button type="button" class="primary" onclick="cargarSeccion('docente_prospectos', 'nuevo=1')">Nuevo prospecto</button>
      <button type="button" class="secondary" onclick="cargarSeccion('docente_bolsa')">Bolsa de candidatos</button>
    </div>
  </div>

  <div id="msg-docente" class="catalog-alert" style="display:none;"></div>

  <?php if (isset($_GET['nuevo'])): ?>
    <section style="margin:14px 0 18px; padding:12px; border:1px solid #eee; border-radius:10px;">
      <h3 style="margin-top:0;">Nuevo prospecto</h3>
      <form id="form-docente-nuevo" data-no-global-ajax="1">
        <input type="hidden" name="action" value="save">
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
          <input name="nombres" placeholder="Nombres*" required>
          <input name="apellido_paterno" placeholder="Apellido paterno*" required>
          <input name="apellido_materno" placeholder="Apellido materno">
          <input name="telefono" placeholder="Teléfono">
          <input name="email" placeholder="Correo personal">
          <input name="curp" placeholder="CURP">
          <input name="especialidad" placeholder="Especialidades (ej. Inglés, Informática)">
          <label style="grid-column:span 3;">Áreas HAY (evaluación / Moodle por área)</label>
          <select name="areas[]" multiple size="4" style="grid-column:span 3;">
            <?php foreach ($hayAreas as $a): ?>
            <option value="<?php echo (int) $a['id_area']; ?>"><?php echo htmlspecialchars($a['nombre']); ?></option>
            <?php endforeach; ?>
          </select>
          <input name="disponibilidad" placeholder="Disponibilidad">
          <input type="datetime-local" name="fecha_clase_muestra" placeholder="Fecha clase muestra">
          <select name="estado">
            <?php foreach ($estados as $k => $v): ?>
            <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="primary" style="margin-top:10px;">Guardar prospecto</button>
      </form>
    </section>
  <?php endif; ?>

  <div class="catalog-table-wrap">
    <?php if ($rows === []): ?>
      <p>No hay prospectos docentes registrados.</p>
    <?php else: ?>
      <table class="catalog-table">
        <thead>
          <tr>
            <th>Prospecto</th>
            <th>Estado</th>
            <th>Clase muestra</th>
            <th>DISC</th>
            <th>Decisión</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <strong><?php echo htmlspecialchars(docente_prospecto_nombre($r)); ?></strong>
              <br><small><?php echo htmlspecialchars($r['telefono'] ?? ''); ?> · <?php echo htmlspecialchars($r['email'] ?? ''); ?></small>
              <?php
              $areasP = function_exists('docente_prospecto_areas') ? docente_prospecto_areas($pdo, (int) $r['id_prospecto']) : [];
              if ($areasP): ?>
              <br><small>Áreas: <?php echo htmlspecialchars(implode(', ', array_column($areasP, 'nombre'))); ?></small>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($estados[$r['estado']] ?? $r['estado']); ?></td>
            <td>
              <?php if (!empty($r['fecha_clase_muestra'])): ?>
                <?php echo date('d/m/Y H:i', strtotime($r['fecha_clase_muestra'])); ?>
              <?php else: ?>—<?php endif; ?>
              <?php if ($r['puntaje_showclass'] !== null): ?>
                <br><small><?php echo htmlspecialchars((string) $r['puntaje_showclass']); ?>% <?php echo (int) ($r['showclass_aprobado'] ?? 0) ? '✓' : '✗'; ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($r['disc_resultado_id'])): ?>
                <span>Completado #<?php echo (int) $r['disc_resultado_id']; ?></span>
              <?php elseif ((int) ($r['showclass_aprobado'] ?? 0) === 1): ?>
                <button type="button" class="secondary btn-ir-disc" data-id="<?php echo (int) $r['id_prospecto']; ?>">Aplicar DISC</button>
              <?php else: ?>
                <span style="color:#999;">No habilitado</span>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($r['decision_final'] ?? 'pendiente'); ?></td>
            <td>
              <button type="button" class="secondary btn-showclass" data-id="<?php echo (int) $r['id_prospecto']; ?>">Evaluar clase</button>
              <button type="button" class="secondary btn-showclass-page" data-id="<?php echo (int) $r['id_prospecto']; ?>">Vista eval.</button>
              <button type="button" class="secondary btn-acceso" data-id="<?php echo (int) $r['id_prospecto']; ?>">Crear acceso</button>
              <button type="button" class="secondary btn-expediente" data-id="<?php echo (int) $r['id_prospecto']; ?>">Documentos</button>
              <button type="button" class="secondary btn-decision" data-id="<?php echo (int) $r['id_prospecto']; ?>">Decidir</button>
              <?php if (($r['decision_final'] ?? '') === 'contratar'): ?>
              <button type="button" class="primary btn-contratar" data-id="<?php echo (int) $r['id_prospecto']; ?>">Activar contratación</button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="catalog-modal" id="modal-showclass">
  <div class="catalog-modal__panel">
    <h3 style="margin-top:0;">Evaluación clase muestra</h3>
    <form id="form-showclass" data-no-global-ajax="1">
      <input type="hidden" name="action" value="save_showclass">
      <input type="hidden" name="id_prospecto" id="showclass-id" value="0">
      <?php foreach (docente_showclass_rubrica_base() as $item): ?>
      <div style="margin-bottom:8px;">
        <label><?php echo htmlspecialchars($item['nombre']); ?> (0-<?php echo (int) $item['maximo']; ?>)</label>
        <input type="number" name="puntaje_<?php echo htmlspecialchars($item['codigo']); ?>" min="0" max="<?php echo (int) $item['maximo']; ?>" step="0.5" value="0" style="width:100%;">
      </div>
      <?php endforeach; ?>
      <textarea name="comentarios" rows="3" placeholder="Comentarios de la clase" style="width:100%; margin-top:6px;"></textarea>
      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
        <button type="button" class="secondary" id="showclass-cancel">Cancelar</button>
        <button type="submit" class="primary">Guardar evaluación</button>
      </div>
    </form>
  </div>
</div>

<div class="catalog-modal" id="modal-decision-docente">
  <div class="catalog-modal__panel">
    <h3 style="margin-top:0;">Decisión final</h3>
    <form id="form-decision-docente" data-no-global-ajax="1">
      <input type="hidden" name="action" value="save_decision">
      <input type="hidden" name="id_prospecto" id="decision-id" value="0">
      <div style="margin-bottom:8px;">
        <label>Decisión</label>
        <select name="decision_final" id="decision-final" style="width:100%;">
          <option value="contratar">Contratar</option>
          <option value="no_contratar">No contratar</option>
          <option value="bolsa">Apto pero a bolsa</option>
        </select>
      </div>
      <div style="margin-bottom:8px;">
        <label>Categoría de no contratación</label>
        <select name="categoria_no_contratacion" style="width:100%;">
          <option value="">—</option>
          <option value="disponibilidad">Disponibilidad</option>
          <option value="perfil">Perfil no compatible</option>
          <option value="tecnica">Competencia técnica</option>
          <option value="disc">Resultado DISC</option>
          <option value="otro">Otro</option>
        </select>
      </div>
      <textarea name="motivo_no_contratacion" id="decision-motivo" rows="3" placeholder="Motivo (obligatorio si no se contrata)" style="width:100%; margin-bottom:8px;"></textarea>
      <div style="margin-bottom:8px;">
        <label>Recontactar en</label>
        <input type="date" name="recontactar_en" style="width:100%;">
      </div>
      <label style="display:flex; align-items:center; gap:6px; margin-bottom:8px;">
        <input type="checkbox" name="segunda_oportunidad" value="1"> Marcar segunda oportunidad
      </label>
      <div style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="secondary" id="decision-cancel">Cancelar</button>
        <button type="submit" class="primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const msg = document.getElementById('msg-docente');
  const showModal = document.getElementById('modal-showclass');
  const decisionModal = document.getElementById('modal-decision-docente');

  function showMsg(ok, text) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text || '';
  }

  document.getElementById('form-docente-nuevo')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const { data } = await hayFetchJson('php/docente_prospecto_api.php', { method: 'POST', body: new FormData(e.target) });
      showMsg(data.status === 'ok', data.message || '');
      if (data.status === 'ok' && data.seccion) cargarSeccion(data.seccion);
    } catch (err) { showMsg(false, err.message); }
  });

  document.querySelectorAll('.btn-showclass').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.getElementById('showclass-id').value = btn.dataset.id;
      showModal?.classList.add('is-open');
    });
  });
  document.getElementById('showclass-cancel')?.addEventListener('click', () => showModal?.classList.remove('is-open'));
  document.getElementById('form-showclass')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const { data } = await hayFetchJson('php/docente_prospecto_api.php', { method: 'POST', body: new FormData(e.target) });
      showModal.classList.remove('is-open');
      showMsg(data.status === 'ok', data.message || '');
      if (data.status === 'ok' && data.seccion) cargarSeccion(data.seccion);
    } catch (err) { showMsg(false, err.message); }
  });

  document.querySelectorAll('.btn-decision').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.getElementById('decision-id').value = btn.dataset.id;
      decisionModal?.classList.add('is-open');
    });
  });
  document.getElementById('decision-cancel')?.addEventListener('click', () => decisionModal?.classList.remove('is-open'));
  document.getElementById('form-decision-docente')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const decision = form.querySelector('[name=decision_final]').value;
    const motivo = form.querySelector('[name=motivo_no_contratacion]').value.trim();
    if (decision !== 'contratar' && !motivo) {
      alert('El motivo es obligatorio cuando no se contrata.');
      return;
    }
    try {
      const { data } = await hayFetchJson('php/docente_prospecto_api.php', { method: 'POST', body: new FormData(form) });
      decisionModal.classList.remove('is-open');
      showMsg(data.status === 'ok', data.message || '');
      if (data.status === 'ok' && data.seccion) cargarSeccion(data.seccion);
    } catch (err) { showMsg(false, err.message); }
  });

  document.querySelectorAll('.btn-ir-disc').forEach((btn) => {
    btn.addEventListener('click', () => {
      const q = new URLSearchParams();
      q.set('prospecto_docente', btn.dataset.id);
      cargarSeccion('examen_disc', q);
    });
  });

  document.querySelectorAll('.btn-showclass-page').forEach((btn) => {
    btn.addEventListener('click', () => cargarSeccion('docente_showclass_evaluar', 'id=' + btn.dataset.id));
  });

  document.querySelectorAll('.btn-expediente').forEach((btn) => {
    btn.addEventListener('click', () => {
      cargarSeccion('expediente_consulta', 'tipo=prospecto&id=' + btn.dataset.id);
    });
  });

  document.querySelectorAll('.btn-acceso').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!confirm('¿Crear acceso al sistema para que el candidato consulte sus resultados?')) return;
      try {
        const fd = new FormData();
        fd.append('action', 'crear_acceso');
        fd.append('id_prospecto', btn.dataset.id);
        const { data } = await hayFetchJson('php/docente_prospecto_api.php', { method: 'POST', body: fd });
        showMsg(data.status === 'ok', data.message || '');
        if (data.status === 'ok' && data.seccion) cargarSeccion(data.seccion);
      } catch (err) { showMsg(false, err.message); }
    });
  });

  document.querySelectorAll('.btn-contratar').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const emailGoogle = prompt('Correo Google institucional del nuevo profesor:', '');
      if (emailGoogle === null) return;
      try {
        const fd = new FormData();
        fd.append('action', 'contratar');
        fd.append('id_prospecto', btn.dataset.id);
        fd.append('email_google', emailGoogle);
        const { data } = await hayFetchJson('php/docente_prospecto_api.php', { method: 'POST', body: fd });
        showMsg(data.status === 'ok', data.message || '');
        if (data.status === 'ok' && data.seccion) cargarSeccion(data.seccion);
      } catch (err) { showMsg(false, err.message); }
    });
  });
})();
</script>
