<?php
require_once __DIR__ . '/../config.php';
planeacion_ensure_schema($pdo);

if (!planeacion_puede_crear()) {
    echo '<div class="alert">No tienes permiso para registrar planeaciones.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$grupos = planeacion_grupos_usuario($pdo, $idPlantel);
$esProfesor = profesor_portal_es_profesor();
$iaLabel = function_exists('hay_ai_provider_label') ? hay_ai_provider_label() : 'IA';
$iaConfigured = function_exists('hay_ai_configured') && hay_ai_configured();
?>

<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/hay_buttons.css">

<div class="result-container">
  <div class="result-header">
    <h2>Planeaciones de clase</h2>
    <p style="color:#666; margin:8px 0 0;">
      <?php if ($esProfesor): ?>
        Registre la planeación por grupo y el parcial (fase) que va a impartir.
      <?php else: ?>
        Registro de planeaciones por grupo y fase.
      <?php endif; ?>
    </p>
    <div class="disc-actions" style="margin-top:12px;">
      <?php if ($esProfesor): ?>
      <button type="button" onclick="cargarSeccion('profesor_portal')">← Portal docente</button>
      <?php else: ?>
      <button type="button" onclick="cargarSeccion('grupos')">Volver a grupos</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($grupos === []): ?>
    <p>No hay grupos disponibles para planeación en este plantel.</p>
  <?php else: ?>

  <div id="plan-msg" class="catalog-alert" style="display:none; margin-bottom:12px;"></div>

  <div class="patron-desc">
    <form id="form-planeacion" method="POST" action="php/planeacion_save.php">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <div>
          <label><strong>Grupo</strong></label><br>
          <select name="id_grupo" id="plan-grupo" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
            <option value="">Selecciona…</option>
            <?php foreach ($grupos as $g): ?>
              <option value="<?php echo (int) $g['id_grupo']; ?>"
                data-fase="<?php echo (int) ($g['id_fase_actual'] ?? 0); ?>">
                <?php echo htmlspecialchars($g['clave'] . (isset($g['esp_nombre']) ? ' · ' . $g['esp_nombre'] : '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div id="plan-grupo-info" style="margin-top:8px; color:#666; font-size:0.9rem;"></div>
          <div id="plan-gustos-grupo" style="display:none; margin-top:10px; padding:12px; background:#f3f8ff; border:1px solid #bbdefb; border-radius:10px; font-size:0.9rem;"></div>
        </div>
        <div>
          <label><strong>Parcial / fase a planear</strong></label><br>
          <select name="id_fase" id="plan-fase" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;" disabled>
            <option value="">Primero elija un grupo…</option>
          </select>
        </div>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 180px; gap: 10px; margin-top:10px;">
        <div>
          <label><strong>Fecha de la sesión</strong></label><br>
          <input type="date" name="fecha" required value="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
        </div>
        <div>
          <div id="plan-conteos" style="margin-top:28px; color:#666; font-weight:700;"></div>
        </div>
      </div>

      <div style="margin-top:10px;">
        <label><strong>Tema / título de la clase</strong></label><br>
        <input name="titulo" id="plan-tema" required maxlength="160" placeholder="Ej. Present perfect vs past simple"
          style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
      </div>

      <div style="margin-top:10px;">
        <label><strong>Planeación</strong></label><br>
        <textarea name="contenido" id="plan-contenido" required rows="12"
          placeholder="Objetivo, inicio, desarrollo, cierre, materiales…"
          style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;"></textarea>
      </div>

      <div style="margin-top:10px;">
        <label><strong>Nota para coordinación (opcional)</strong></label><br>
        <textarea name="nota_profesor" id="plan-nota-prof" rows="2" maxlength="500"
          placeholder="Comentario o contexto para quien revisa esta planeación"
          style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;"></textarea>
      </div>

      <div class="disc-actions" style="justify-content:flex-start; gap:8px; flex-wrap:wrap; margin-top:12px;">
        <button class="primary" type="submit">Guardar planeación</button>
        <button type="button" class="secondary" id="btn-plan-ia" disabled>
          <i class="fas fa-magic"></i> Sugerir con IA<?php echo $iaConfigured ? ' (' . htmlspecialchars($iaLabel, ENT_QUOTES, 'UTF-8') . ')' : ''; ?>
        </button>
      </div>
      <p id="btn-plan-ia-hint" style="font-size:0.82rem; color:#888; margin-top:8px;">
        Elija un <strong>grupo</strong> para habilitar la sugerencia con IA. También necesita fase y tema antes de generar.
      </p>
    </form>
  </div>

  <?php endif; ?>

  <?php if ($esProfesor): ?>
  <section style="margin-top:32px;">
    <h3>Mis planeaciones enviadas</h3>
    <p style="color:#666; font-size:0.9rem;">Estado de revisión por coordinación académica.</p>
    <div id="plan-mis-list"><p style="color:#888;">Cargando…</p></div>
  </section>
  <?php elseif (function_exists('planeacion_puede_revisar') && planeacion_puede_revisar()): ?>
  <p style="margin-top:20px;">
    <button type="button" class="secondary" onclick="cargarSeccion('planeaciones_revision')">
      <i class="fas fa-clipboard-check"></i> Ir a revisar planeaciones de profesores
    </button>
  </p>
  <?php endif; ?>
</div>

<div class="catalog-modal" id="modal-plan-prof">
  <div class="catalog-modal__dialog" style="max-width:760px; max-height:92vh; overflow:auto;">
    <h3 id="modal-plan-prof-titulo">Planeación</h3>
    <div id="modal-plan-prof-meta" style="color:#666; font-size:0.9rem; margin-bottom:10px;"></div>

    <div id="modal-plan-prof-hilo" style="margin-bottom:14px;"></div>

    <div id="modal-plan-prof-lectura" style="display:none;">
      <div id="modal-plan-prof-contenido" style="white-space:pre-wrap; background:#f8f8f8; padding:14px; border-radius:8px; margin-bottom:12px; max-height:35vh; overflow:auto;"></div>
    </div>

    <div id="modal-plan-prof-edicion" style="display:none;">
      <input type="hidden" id="edit-plan-id">
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
        <div>
          <label>Fecha sesión</label>
          <input type="date" id="edit-plan-fecha" style="width:100%; padding:8px;">
        </div>
        <div>
          <label>Fase</label>
          <select id="edit-plan-fase" style="width:100%; padding:8px;"></select>
        </div>
      </div>
      <label>Título</label>
      <input type="text" id="edit-plan-titulo" maxlength="160" style="width:100%; padding:8px; margin-bottom:8px;">
      <label>Planeación</label>
      <textarea id="edit-plan-contenido" rows="10" style="width:100%; padding:8px; margin-bottom:8px;"></textarea>
      <label>Nota al reenviar (opcional)</label>
      <textarea id="edit-plan-nota" rows="2" placeholder="Indique qué corrigió o aclaración para coordinación" style="width:100%; padding:8px;"></textarea>
      <div style="margin-top:10px;">
        <button type="button" class="primary" id="btn-plan-reenviar"><i class="fas fa-paper-plane"></i> Guardar y reenviar</button>
      </div>
    </div>

    <div style="margin-top:14px; padding-top:12px; border-top:1px solid #eee;">
      <label>Agregar observación a esta planeación</label>
      <textarea id="modal-plan-prof-comentario" rows="2" style="width:100%; padding:8px; margin:6px 0;" placeholder="Su nota quedará en el historial"></textarea>
      <button type="button" class="secondary" id="btn-plan-comentar-prof">Agregar observación</button>
      <button type="button" class="secondary" id="btn-plan-cerrar-prof" style="margin-left:8px;">Cerrar</button>
    </div>
  </div>
</div>

<script>
(function () {
  const iaBtnHtml = <?php echo json_encode('<i class="fas fa-magic"></i> Sugerir con IA' . ($iaConfigured ? ' (' . $iaLabel . ')' : ''), JSON_UNESCAPED_UNICODE); ?>;
  const selGrupo = document.getElementById('plan-grupo');
  const selFase = document.getElementById('plan-fase');
  const infoGrupo = document.getElementById('plan-grupo-info');
  const gustosGrupo = document.getElementById('plan-gustos-grupo');
  const conteos = document.getElementById('plan-conteos');
  const btnIa = document.getElementById('btn-plan-ia');
  const btnIaHint = document.getElementById('btn-plan-ia-hint');
  const msg = document.getElementById('plan-msg');
  const form = document.getElementById('form-planeacion');

  function setIaHint(text) {
    if (btnIaHint) btnIaHint.innerHTML = text;
  }

  function setIaEnabled(on, hint) {
    if (btnIa) btnIa.disabled = !on;
    if (hint) setIaHint(hint);
  }

  function showMsg(text, ok) {
    if (!msg) return;
    msg.style.display = text ? 'block' : 'none';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = text || '';
  }

  async function cargarFases() {
    const gid = selGrupo?.value;
    if (!selFase) return;
    selFase.innerHTML = '<option value="">Cargando fases…</option>';
    setIaEnabled(false, 'Cargando fases del grupo…');
    infoGrupo.textContent = '';
    if (gustosGrupo) { gustosGrupo.style.display = 'none'; gustosGrupo.innerHTML = ''; }
    conteos.textContent = '';
    if (!gid) {
      selFase.innerHTML = '<option value="">Primero elija un grupo…</option>';
      selFase.disabled = true;
      setIaEnabled(false, 'Elija un <strong>grupo</strong> para habilitar la sugerencia con IA.');
      return;
    }
    try {
      const r = await fetch('php/planeacion_fases_api.php?id_grupo=' + encodeURIComponent(gid));
      const d = await r.json();
      if (d.status !== 'ok') throw new Error(d.message || 'Error');
      infoGrupo.textContent = (d.grupo?.especialidad || '') + (d.grupo?.fase_actual ? ' · Parcial actual del grupo: ' + d.grupo.fase_actual : '');
      if (gustosGrupo && d.gustos) {
        const g = d.gustos;
        if (g.con_perfil > 0) {
          gustosGrupo.style.display = 'block';
          gustosGrupo.innerHTML = '<strong><i class="fas fa-heart"></i> Gustos del grupo</strong> (' + g.con_perfil + ' de ' + g.total_alumnos + ' alumnos con perfil)'
            + (g.resumen_html || '')
            + '<p style="margin:8px 0 0;color:#555;">La sugerencia con IA usará estos intereses para personalizar ejemplos.</p>';
        }
      }
      if (!d.fases || !d.fases.length) {
        selFase.innerHTML = '<option value="">Sin fases configuradas</option>';
        selFase.disabled = true;
        setIaEnabled(false, 'Este grupo no tiene fases/parciales configurados; no se puede usar IA.');
        return;
      }
      selFase.innerHTML = d.fases.map((f) =>
        '<option value="' + f.id_fase + '"' + (f.sugerida ? ' selected' : '') + '>' +
        (f.clave_fase || f.nombre_fase || ('Fase ' + f.id_fase)) +
        (f.nombre_fase && f.clave_fase ? ' — ' + f.nombre_fase : '') +
        '</option>'
      ).join('');
      selFase.disabled = false;
      setIaEnabled(true, 'Listo: escriba el <strong>tema</strong> y pulse Sugerir con IA. Revise y adapte antes de guardar.');
      refrescarConteos();
    } catch (e) {
      selFase.innerHTML = '<option value="">Error al cargar fases</option>';
      selFase.disabled = true;
      setIaEnabled(false, 'No se pudieron cargar las fases del grupo.');
      showMsg(e.message || 'No se pudieron cargar las fases', false);
    }
  }

  function refrescarConteos() {
    const gid = selGrupo?.value;
    if (!gid || !conteos) { if (conteos) conteos.textContent = ''; return; }
    fetch('php/grupo_stats.php?id=' + encodeURIComponent(gid) + '&t=' + Date.now(), { cache: 'no-store' })
      .then((r) => r.json())
      .then((d) => {
        conteos.textContent = 'Alumnos activos: ' + (d.activos_total ?? 0) + ' · Asistieron (30d): ' + (d.activos_30d ?? 0);
      })
      .catch(() => { conteos.textContent = ''; });
  }

  selGrupo?.addEventListener('change', cargarFases);

  btnIa?.addEventListener('click', async () => {
    const gid = selGrupo?.value;
    const tema = document.getElementById('plan-tema')?.value?.trim();
    const idFase = selFase?.value;
    const out = document.getElementById('plan-contenido');
    if (!gid || !tema || !idFase) {
      alert('Seleccione grupo, fase y escriba el tema.');
      return;
    }
    btnIa.disabled = true;
    btnIa.textContent = 'Generando…';
    showMsg('', true);
    const fd = new FormData();
    fd.append('id_grupo', gid);
    fd.append('id_fase', idFase);
    fd.append('tema', tema);
    fd.append('duracion', '50');
    try {
      const r = await fetch('php/gemini_sugerir_planeacion.php', {
        method: 'POST',
        body: fd,
        cache: 'no-store',
        headers: { 'X-Requested-With': 'fetch' },
      });
      const d = await r.json();
      if (d.status !== 'ok') {
        let errMsg = d.message || 'Error IA';
        if (d.api_error) errMsg += ' — ' + d.api_error;
        if (d.hint) errMsg += ' ' + d.hint;
        throw new Error(errMsg);
      }
      out.value = d.sugerencia;
      showMsg('Sugerencia generada. Revise y edite antes de guardar.', true);
    } catch (err) {
      showMsg('No se pudo generar sugerencia: ' + err.message, false);
    }
    btnIa.disabled = false;
    btnIa.innerHTML = iaBtnHtml;
    if (selGrupo?.value && selFase?.value) {
      setIaEnabled(true, 'Listo: escriba el <strong>tema</strong> y pulse Sugerir con IA. Revise y adapte antes de guardar.');
    }
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    showMsg('Guardando…', true);
    try {
      const fd = new FormData(form);
      const r = await fetch(form.action, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'fetch' },
      });
      const d = await r.json();
      if (d.status !== 'ok') throw new Error(d.message || 'Error al guardar');
      showMsg('Planeación guardada.', true);
      document.getElementById('plan-contenido').value = '';
      document.getElementById('plan-tema').value = '';
      <?php if ($esProfesor): ?>if (typeof cargarMisPlaneaciones === 'function') cargarMisPlaneaciones();<?php endif; ?>
    } catch (err) {
      showMsg(err.message || 'Error al guardar', false);
    }
  });

  <?php if ($esProfesor): ?>
  const apiPlan = 'php/planeacion_revision_api.php';
  const modalProf = document.getElementById('modal-plan-prof');
  let planProfActual = null;

  function escHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function renderHilo(obs) {
    const box = document.getElementById('modal-plan-prof-hilo');
    if (!box) return;
    if (!obs || !obs.length) {
      box.innerHTML = '<p style="color:#888; font-size:0.9rem;">Sin observaciones aún.</p>';
      return;
    }
    let html = '<div style="font-size:0.88rem;"><strong>Historial de observaciones</strong>';
    obs.forEach((o) => {
      const rol = o.autor_rol === 'profesor' ? 'Profesor' : 'Coordinación';
      const badge = o.es_reenvio == 1 ? ' · <em>reenvío</em>' : '';
      html += '<div style="margin:8px 0; padding:10px; border-left:3px solid ' + (o.autor_rol === 'profesor' ? '#1565c0' : '#e65100') + '; background:#fafafa;">' +
        '<div style="color:#666;">' + escHtml(o.autor_nombre || rol) + ' · ' + escHtml(String(o.creado_en || '').slice(0, 16)) + badge + '</div>' +
        '<div style="margin-top:4px;">' + escHtml(o.comentario) + '</div></div>';
    });
    html += '</div>';
    box.innerHTML = html;
  }

  async function abrirPlaneacionProf(id, editar) {
    const r = await fetch(apiPlan + '?action=detalle&id_planeacion=' + encodeURIComponent(id));
    const d = await r.json();
    if (d.status !== 'ok') { alert(d.message || 'Error'); return; }
    planProfActual = d.planeacion;
    document.getElementById('modal-plan-prof-titulo').textContent = planProfActual.titulo || 'Planeación';
    document.getElementById('modal-plan-prof-meta').textContent =
      (planProfActual.grupo_clave || '') + ' · ' + (planProfActual.clave_fase || '') + ' · ' + (planProfActual.fecha || '');
    renderHilo(d.observaciones || []);
    document.getElementById('modal-plan-prof-comentario').value = '';

    const lectura = document.getElementById('modal-plan-prof-lectura');
    const edicion = document.getElementById('modal-plan-prof-edicion');
    if (editar && d.puede_reenviar) {
      lectura.style.display = 'none';
      edicion.style.display = 'block';
      document.getElementById('edit-plan-id').value = planProfActual.id_planeacion;
      document.getElementById('edit-plan-fecha').value = planProfActual.fecha || '';
      document.getElementById('edit-plan-titulo').value = planProfActual.titulo || '';
      document.getElementById('edit-plan-contenido').value = planProfActual.contenido || '';
      document.getElementById('edit-plan-nota').value = '';
      const sel = document.getElementById('edit-plan-fase');
      sel.innerHTML = (d.fases || []).map((f) =>
        '<option value="' + f.id_fase + '"' + (String(f.id_fase) === String(planProfActual.id_fase) ? ' selected' : '') + '>' +
        escHtml(f.clave_fase || f.nombre_fase) + '</option>'
      ).join('');
    } else {
      edicion.style.display = 'none';
      lectura.style.display = 'block';
      document.getElementById('modal-plan-prof-contenido').textContent = planProfActual.contenido || '';
    }
    modalProf?.classList.add('is-open');
  }

  async function cargarMisPlaneaciones() {
    const el = document.getElementById('plan-mis-list');
    if (!el) return;
    try {
      const r = await fetch(apiPlan + '?action=mis_planeaciones');
      const d = await r.json();
      if (d.status !== 'ok' || !d.items || !d.items.length) {
        el.innerHTML = '<p>Aún no ha registrado planeaciones.</p>';
        return;
      }
      const est = d.estados || {};
      let html = '<table class="catalog-table"><thead><tr><th>Fecha</th><th>Grupo</th><th>Fase</th><th>Tema</th><th>Estado</th><th>Obs.</th><th></th></tr></thead><tbody>';
      d.items.forEach((p) => {
        html += '<tr><td>' + escHtml(p.fecha) + '</td><td>' + escHtml(p.grupo_clave) + '</td>' +
          '<td>' + escHtml(p.clave_fase) + '</td><td>' + escHtml(p.titulo) + '</td>' +
          '<td>' + escHtml(est[p.estado] || p.estado) + '</td>' +
          '<td>' + (p.num_observaciones || 0) + '</td>' +
          '<td style="white-space:nowrap;">' +
          '<button type="button" class="secondary btn-ver-mi-plan" data-id="' + p.id_planeacion + '">Ver</button> ';
        if (p.puede_reenviar) {
          html += '<button type="button" class="primary btn-edit-mi-plan" data-id="' + p.id_planeacion + '">Editar</button>';
        }
        html += '</td></tr>';
      });
      html += '</tbody></table>';
      el.innerHTML = html;
      el.querySelectorAll('.btn-ver-mi-plan').forEach((b) => b.addEventListener('click', () => abrirPlaneacionProf(b.dataset.id, false)));
      el.querySelectorAll('.btn-edit-mi-plan').forEach((b) => b.addEventListener('click', () => abrirPlaneacionProf(b.dataset.id, true)));
    } catch (e) {
      el.innerHTML = '<p>No se pudo cargar el historial.</p>';
    }
  }

  document.getElementById('btn-plan-reenviar')?.addEventListener('click', async () => {
    if (!planProfActual) return;
    const fd = new FormData();
    fd.append('action', 'reenviar');
    fd.append('id_planeacion', planProfActual.id_planeacion);
    fd.append('fecha', document.getElementById('edit-plan-fecha').value);
    fd.append('id_fase', document.getElementById('edit-plan-fase').value);
    fd.append('titulo', document.getElementById('edit-plan-titulo').value);
    fd.append('contenido', document.getElementById('edit-plan-contenido').value);
    fd.append('nota', document.getElementById('edit-plan-nota').value);
    const r = await fetch(apiPlan, { method: 'POST', body: fd });
    const d = await r.json();
    showMsg(d.message || '', d.status === 'ok');
    if (d.status === 'ok') {
      modalProf?.classList.remove('is-open');
      cargarMisPlaneaciones();
    }
  });

  document.getElementById('btn-plan-comentar-prof')?.addEventListener('click', async () => {
    if (!planProfActual) return;
    const fd = new FormData();
    fd.append('action', 'comentar');
    fd.append('id_planeacion', planProfActual.id_planeacion);
    fd.append('nota', document.getElementById('modal-plan-prof-comentario').value);
    const r = await fetch(apiPlan, { method: 'POST', body: fd });
    const d = await r.json();
    showMsg(d.message || '', d.status === 'ok');
    if (d.status === 'ok') abrirPlaneacionProf(planProfActual.id_planeacion, false);
  });

  document.getElementById('btn-plan-cerrar-prof')?.addEventListener('click', () => modalProf?.classList.remove('is-open'));

  cargarMisPlaneaciones();
  <?php endif; ?>
})();
</script>
