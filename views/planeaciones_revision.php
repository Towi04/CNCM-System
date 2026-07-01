<?php

require_once __DIR__ . '/../config.php';

planeacion_ensure_schema($pdo);



if (!planeacion_puede_revisar()) {

    echo '<div class="alert">Solo coordinación puede revisar planeaciones de clase.</div>';

    return;

}



$idPlantel = plantel_scope_id($pdo);

$grupos = planeacion_grupos_usuario($pdo, $idPlantel);

$profesores = planeacion_profesores_filtro($pdo, $idPlantel);

$pendientes = planeacion_contar_pendientes($pdo, $idPlantel);

$estados = planeacion_estados_etiquetas();

?>

<link rel="stylesheet" href="css/admin_catalogo.css">

<link rel="stylesheet" href="css/hay_buttons.css">

<link rel="stylesheet" href="css/planeacion_revision.css">



<div class="catalog-wrap">

  <h2>Revisar planeaciones</h2>

  <p style="color:#666;">

    Apruebe o envíe observaciones a las planeaciones que los profesores registran por grupo y fase.

    <?php if ($pendientes > 0): ?>

      <strong style="color:#c62828;"><?php echo (int) $pendientes; ?> pendiente(s)</strong>

    <?php endif; ?>

  </p>



  <div id="plan-rev-msg" class="catalog-alert" style="display:none;"></div>



  <div class="plan-rev-filtros">

    <div class="field">

      <label for="plan-rev-filtro-estado">Estado</label>

      <select id="plan-rev-filtro-estado">

        <option value="enviada">Pendientes de revisión</option>

        <option value="observada">Con observaciones</option>

        <option value="revisada">Aprobadas</option>

        <option value="todas">Todas</option>

      </select>

    </div>

    <div class="field">

      <label for="plan-rev-filtro-profesor">Profesor</label>

      <select id="plan-rev-filtro-profesor">

        <option value="">Todos los profesores</option>

        <?php foreach ($profesores as $pr): ?>

        <option value="<?php echo (int) $pr['id_usuario']; ?>"><?php echo htmlspecialchars($pr['nombre'] ?? ''); ?></option>

        <?php endforeach; ?>

      </select>

    </div>

    <div class="field">

      <label for="plan-rev-filtro-grupo">Grupo</label>

      <select id="plan-rev-filtro-grupo">

        <option value="">Todos los grupos</option>

        <?php foreach ($grupos as $g): ?>

        <option value="<?php echo (int) $g['id_grupo']; ?>"><?php echo htmlspecialchars($g['clave'] ?? ''); ?></option>

        <?php endforeach; ?>

      </select>

    </div>

    <button type="button" class="secondary" id="plan-rev-refrescar"><i class="fas fa-sync"></i> Actualizar</button>

    <button type="button" class="primary" onclick="cargarSeccion('planeaciones')">Nueva planeación</button>

  </div>



  <div id="plan-rev-list">

    <p style="color:#666;">Cargando…</p>

  </div>

</div>



<div class="catalog-modal" id="modal-plan-rev">

  <div class="catalog-modal__panel plan-rev-modal" role="dialog" aria-labelledby="modal-plan-rev-titulo">

    <header class="plan-rev-modal__header">

      <div>

        <h3 id="modal-plan-rev-titulo">Detalle de planeación</h3>

        <div id="modal-plan-rev-meta" class="plan-rev-modal__meta"></div>

      </div>

      <button type="button" class="plan-rev-modal__close" id="btn-plan-cerrar-x" title="Cerrar" aria-label="Cerrar">

        <i class="fas fa-times"></i>

      </button>

    </header>



    <div class="plan-rev-modal__body">

      <div id="modal-plan-rev-hilo" class="plan-rev-modal__hilo"></div>

      <div id="modal-plan-rev-contenido" class="plan-rev-modal__contenido"></div>



      <div id="modal-plan-rev-acciones-revisar" class="plan-rev-modal__revisar" style="display:none;">

        <label for="modal-plan-rev-nota-revisar">Observaciones para el profesor</label>

        <textarea id="modal-plan-rev-nota-revisar" rows="3" placeholder="Opcional al aprobar; obligatorio si envía observaciones"></textarea>

        <div class="plan-rev-modal__acciones">

          <button type="button" class="primary" id="btn-plan-aprobar"><i class="fas fa-check"></i> Aprobar</button>

          <button type="button" class="secondary" id="btn-plan-observar"><i class="fas fa-comment"></i> Marcar con observaciones</button>

        </div>

      </div>

    </div>



    <footer class="plan-rev-modal__footer">

      <label for="modal-plan-rev-nota">Agregar observación al historial</label>

      <textarea id="modal-plan-rev-nota" rows="2" placeholder="Quedará visible para el profesor y coordinación"></textarea>

      <div class="plan-rev-modal__footer-actions">

        <button type="button" class="secondary" id="btn-plan-comentar"><i class="fas fa-plus"></i> Agregar observación</button>

        <label style="font-size:0.88rem; display:none;" id="lbl-plan-marcar-obs">

          <input type="checkbox" id="modal-plan-marcar-obs"> Notificar al profesor como observada

        </label>

        <button type="button" class="secondary" id="btn-plan-cerrar">Cerrar</button>

      </div>

    </footer>

  </div>

</div>



<script>

(function () {

  const api = 'php/planeacion_revision_api.php';

  const listEl = document.getElementById('plan-rev-list');

  const msgEl = document.getElementById('plan-rev-msg');

  const modal = document.getElementById('modal-plan-rev');

  let planActual = null;

  let puedeRevisar = false;

  const estados = <?php echo json_encode($estados, JSON_UNESCAPED_UNICODE); ?>;



  function escHtml(s) {

    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

  }



  function formatContenido(text) {

    return escHtml(text)

      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

  }



  function cerrarModal() {

    modal?.classList.remove('is-open');

    planActual = null;

  }



  function renderHilo(obs) {

    const box = document.getElementById('modal-plan-rev-hilo');

    if (!box) return;

    if (!obs || !obs.length) {

      box.innerHTML = '<p style="color:#888; font-size:0.9rem; margin:0;">Sin observaciones en el historial.</p>';

      return;

    }

    let html = '<div style="font-size:0.88rem;"><strong>Historial de observaciones</strong>';

    obs.forEach((o) => {

      const rol = o.autor_rol === 'profesor' ? 'Profesor' : 'Coordinación';

      const badge = o.es_reenvio == 1 ? ' · <em>reenvío</em>' : '';

      const cls = o.autor_rol === 'profesor' ? 'plan-rev-hilo-item plan-rev-hilo-item--prof' : 'plan-rev-hilo-item';

      html += '<div class="' + cls + '">' +

        '<div class="plan-rev-hilo-item__meta">' + escHtml(o.autor_nombre || rol) + ' · ' + escHtml(String(o.creado_en || '').slice(0, 16)) + badge + '</div>' +

        '<div>' + escHtml(o.comentario) + '</div></div>';

    });

    html += '</div>';

    box.innerHTML = html;

  }



  function msg(text, ok) {

    if (!msgEl) return;

    msgEl.style.display = text ? 'block' : 'none';

    msgEl.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');

    msgEl.textContent = text || '';

  }



  function badge(estado) {

    const colors = { enviada: '#1565c0', observada: '#e65100', revisada: '#2e7d32', borrador: '#888' };

    const c = colors[estado] || '#666';

    return '<span style="color:' + c + '; font-weight:600;">' + (estados[estado] || estado) + '</span>';

  }



  function renderList(items) {

    if (!items || !items.length) {

      listEl.innerHTML = '<p>No hay planeaciones con este filtro.</p>';

      return;

    }

    let html = '<div class="catalog-table-wrap"><table class="catalog-table"><thead><tr>' +

      '<th>Fecha</th><th>Grupo</th><th>Fase</th><th>Profesor</th><th>Tema</th><th>Estado</th><th></th></tr></thead><tbody>';

    items.forEach((p) => {

      const fase = (p.clave_fase || '') + (p.nombre_fase ? ' — ' + p.nombre_fase : '');

      html += '<tr><td>' + escHtml(p.fecha) + '</td><td><strong>' + escHtml(p.grupo_clave) + '</strong></td>' +

        '<td>' + escHtml(fase) + '</td><td>' + escHtml(p.profesor_nombre || '—') + '</td><td>' + escHtml(p.titulo) + '</td>' +

        '<td>' + badge(p.estado) + '</td>' +

        '<td><button type="button" class="secondary btn-ver-plan" data-id="' + p.id_planeacion + '">Revisar</button></td></tr>';

    });

    html += '</tbody></table></div>';

    listEl.innerHTML = html;

    listEl.querySelectorAll('.btn-ver-plan').forEach((btn) => {

      btn.addEventListener('click', () => abrirDetalle(btn.dataset.id));

    });

  }



  async function cargar() {

    const estado = document.getElementById('plan-rev-filtro-estado')?.value || 'enviada';

    const grupo = document.getElementById('plan-rev-filtro-grupo')?.value || '';

    const profesor = document.getElementById('plan-rev-filtro-profesor')?.value || '';

    let url = api + '?action=listar&estado=' + encodeURIComponent(estado);

    if (grupo) url += '&id_grupo=' + encodeURIComponent(grupo);

    if (profesor) url += '&id_profesor=' + encodeURIComponent(profesor);

    listEl.innerHTML = '<p style="color:#666;">Cargando…</p>';

    try {

      const r = await fetch(url);

      const d = await r.json();

      if (d.status !== 'ok') throw new Error(d.message || 'Error');

      renderList(d.items || []);

    } catch (e) {

      listEl.innerHTML = '<p class="alert">Error al cargar.</p>';

    }

  }



  async function abrirDetalle(id) {

    const r = await fetch(api + '?action=detalle&id_planeacion=' + encodeURIComponent(id));

    const d = await r.json();

    if (d.status !== 'ok') { alert(d.message || 'Error'); return; }

    planActual = d.planeacion;

    puedeRevisar = !!d.puede_revisar;

    document.getElementById('modal-plan-rev-titulo').textContent = planActual.titulo || 'Planeación';

    document.getElementById('modal-plan-rev-meta').innerHTML =

      '<strong>' + escHtml(planActual.grupo_clave || '') + '</strong> · ' + escHtml(planActual.esp_nombre || '') +

      ' · ' + escHtml(planActual.clave_fase || '') + ' ' + escHtml(planActual.nombre_fase || '') +

      '<br>Profesor: ' + escHtml(planActual.profesor_nombre || '—') +

      ' · Sesión: ' + escHtml(planActual.fecha || '') +

      ' · ' + badge(planActual.estado);

    renderHilo(d.observaciones || []);

    document.getElementById('modal-plan-rev-contenido').innerHTML = formatContenido(planActual.contenido || '');

    document.getElementById('modal-plan-rev-nota').value = '';

    document.getElementById('modal-plan-rev-nota-revisar').value = '';

    document.getElementById('modal-plan-marcar-obs').checked = false;



    const accionesRevisar = document.getElementById('modal-plan-rev-acciones-revisar');

    const lblMarcar = document.getElementById('lbl-plan-marcar-obs');

    const esEnviada = planActual.estado === 'enviada';

    accionesRevisar.style.display = esEnviada && puedeRevisar ? 'block' : 'none';

    lblMarcar.style.display = puedeRevisar && !esEnviada ? 'inline-flex' : 'none';

    modal?.classList.add('is-open');

    document.querySelector('.plan-rev-modal__body')?.scrollTo(0, 0);

  }



  async function revisar(estado) {

    if (!planActual) return;

    const nota = document.getElementById('modal-plan-rev-nota-revisar')?.value?.trim() || '';

    if (estado === 'observada' && !nota) {

      alert('Escriba las observaciones para el profesor.');

      return;

    }

    const fd = new FormData();

    fd.append('action', 'revisar');

    fd.append('id_planeacion', planActual.id_planeacion);

    fd.append('estado', estado);

    fd.append('nota', nota);

    const r = await fetch(api, { method: 'POST', body: fd });

    const d = await r.json();

    msg(d.message || '', d.status === 'ok');

    if (d.status === 'ok') {

      cerrarModal();

      cargar();

    }

  }



  async function comentar() {

    if (!planActual) return;

    const nota = document.getElementById('modal-plan-rev-nota')?.value?.trim() || '';

    if (!nota) {

      alert('Escriba la observación.');

      return;

    }

    const fd = new FormData();

    fd.append('action', 'comentar');

    fd.append('id_planeacion', planActual.id_planeacion);

    fd.append('nota', nota);

    if (document.getElementById('modal-plan-marcar-obs')?.checked) {

      fd.append('marcar_observada', '1');

    }

    const r = await fetch(api, { method: 'POST', body: fd });

    const d = await r.json();

    msg(d.message || '', d.status === 'ok');

    if (d.status === 'ok') {

      abrirDetalle(planActual.id_planeacion);

      cargar();

    }

  }



  document.getElementById('plan-rev-filtro-estado')?.addEventListener('change', cargar);

  document.getElementById('plan-rev-filtro-grupo')?.addEventListener('change', cargar);

  document.getElementById('plan-rev-filtro-profesor')?.addEventListener('change', cargar);

  document.getElementById('plan-rev-refrescar')?.addEventListener('click', cargar);

  document.getElementById('btn-plan-aprobar')?.addEventListener('click', () => revisar('revisada'));

  document.getElementById('btn-plan-observar')?.addEventListener('click', () => revisar('observada'));

  document.getElementById('btn-plan-comentar')?.addEventListener('click', comentar);

  document.getElementById('btn-plan-cerrar')?.addEventListener('click', cerrarModal);

  document.getElementById('btn-plan-cerrar-x')?.addEventListener('click', cerrarModal);

  modal?.addEventListener('click', (e) => {

    if (e.target === modal) cerrarModal();

  });



  cargar();

})();

</script>


