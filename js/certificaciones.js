(function () {
  const cfg = window.HAY_CERT_CONFIG || {};
  const api = cfg.api || 'php/certificacion_api.php';
  let catalogo = [];
  let productoDetalleId = 0;

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function fmtMxn(n) {
    return '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fmtFecha(s) {
    if (!s) return '—';
    const p = String(s).slice(0, 10).split('-');
    if (p.length !== 3) return esc(s);
    return p[2] + '/' + p[1] + '/' + p[0];
  }

  function fmtHora(s) {
    if (!s) return '—';
    const p = String(s).slice(0, 5);
    return esc(p);
  }

  function familiaHint(key) {
    const fam = (cfg.familias || {})[key];
    return fam?.instruccion_alumno || '';
  }

  function renderCamposAcceso(campos, labels, valores, prefix) {
    if (!campos?.length) return '';
    return campos.map((c) => {
      const val = valores?.[c] || '';
      const inputType = c.startsWith('url_') ? 'url' : (c === 'password_acceso' ? 'text' : 'text');
      const isTextarea = c === 'notas_entrega' || c === 'sede_direccion';
      if (prefix === 'view') {
        if (!val) return '';
        return `<p><strong>${esc(labels[c] || c)}:</strong> ${c.startsWith('url_') && val ? `<a href="${esc(val)}" target="_blank" rel="noopener">${esc(val)}</a>` : esc(val)}</p>`;
      }
      if (isTextarea) {
        return `<div class="full"><label>${esc(labels[c] || c)}</label><textarea name="${esc(c)}" rows="2">${esc(val)}</textarea></div>`;
      }
      return `<div><label>${esc(labels[c] || c)}</label><input type="${inputType}" name="${esc(c)}" value="${esc(val)}"></div>`;
    }).join('');
  }

  function openModal(id) {
    document.getElementById(id)?.removeAttribute('hidden');
  }
  function closeModal(el) {
    const m = el.closest('.cert-modal');
    if (m) m.hidden = true;
  }

  document.querySelectorAll('.cert-modal-close, [data-close]').forEach((btn) => {
    btn.addEventListener('click', (e) => closeModal(e.target));
  });

  document.querySelectorAll('.cert-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      if (tab.id === 'cert-btn-nueva') return;
      document.querySelectorAll('.cert-tab').forEach((t) => t.classList.remove('active'));
      tab.classList.add('active');
      const name = tab.getAttribute('data-tab');
      document.getElementById('cert-panel-catalogo').hidden = name !== 'catalogo';
      document.getElementById('cert-panel-solicitudes').hidden = name !== 'solicitudes';
      if (name === 'solicitudes') cargarSolicitudes();
    });
  });

  async function cargarCatalogo() {
    const q = document.getElementById('cert-buscar')?.value?.trim() || '';
    const loading = document.getElementById('cert-cat-loading');
    const grid = document.getElementById('cert-catalogo-grid');
    if (loading) loading.hidden = false;
    try {
      const url = new URL(api, window.location.href);
      url.searchParams.set('action', 'catalogo');
      if (q) url.searchParams.set('q', q);
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      catalogo = data.certificaciones || [];
      if (!catalogo.length) {
        grid.innerHTML = '<p style="color:#888; padding:16px;">No hay certificaciones configuradas. '
          + (cfg.puedeAdmin ? 'Use «Configurar producto» para vincular un producto del catálogo.' : 'Contacte al administrador.')
          + '</p>';
        return;
      }
      grid.innerHTML = catalogo.map((c) => `
        <article class="cert-card" data-id="${c.id_producto}">
          <h4>${esc(c.nombre)}</h4>
          ${c.organismo ? `<p class="cert-card-org">${esc(c.organismo)}</p>` : ''}
          <p class="cert-card-precio">${esc(c.precio_fmt || fmtMxn(c.precio))}</p>
          <p class="cert-card-meta">${esc(c.familia_label || '')}${c.familia_label ? ' · ' : ''}${(c.docs_requeridos || []).length} doc(s)</p>
          <button type="button" class="secondary cert-ver" data-id="${c.id_producto}">Ver detalle</button>
        </article>
      `).join('');
      grid.querySelectorAll('.cert-ver, .cert-card').forEach((el) => {
        el.addEventListener('click', (e) => {
          if (e.target.closest('.cert-ver') || e.currentTarget.classList.contains('cert-card')) {
            const id = parseInt((e.target.closest('[data-id]') || el).getAttribute('data-id'), 10);
            if (id) verDetalle(id);
          }
        });
      });
    } catch (err) {
      grid.innerHTML = `<p style="color:#c62828;">${esc(err.message)}</p>`;
    } finally {
      if (loading) loading.hidden = true;
    }
  }

  async function verDetalle(idProducto) {
    productoDetalleId = idProducto;
    const url = new URL(api, window.location.href);
    url.searchParams.set('action', 'detalle');
    url.searchParams.set('id_producto', String(idProducto));
    const res = await fetch(url.toString(), { credentials: 'same-origin' });
    const data = await res.json();
    if (data.status !== 'ok') { alert(data.message); return; }
    const c = data.certificacion;
    const body = document.getElementById('cert-detalle-body');
    body.innerHTML = `
      <h3 style="margin-top:0;">${esc(c.nombre)}</h3>
      <p><strong>Clave:</strong> ${esc(c.clave)} · <strong>Costo:</strong> ${esc(c.precio_fmt)}</p>
      ${c.organismo ? `<p><strong>Organismo:</strong> ${esc(c.organismo)}</p>` : ''}
      ${c.familia_label ? `<p><strong>Familia:</strong> ${esc(c.familia_label)}</p>` : ''}
      ${c.instruccion_alumno ? `<div class="cert-block cert-block--info"><h4>Flujo para el alumno</h4><p>${esc(c.instruccion_alumno)}</p></div>` : ''}
      ${c.descripcion ? `<p>${esc(c.descripcion)}</p>` : ''}
      ${c.protocolo ? `<div class="cert-block"><h4>Protocolo de presentación</h4><pre class="cert-pre">${esc(c.protocolo)}</pre></div>` : ''}
      ${c.reglamento_texto ? `<div class="cert-block"><h4>Reglamento</h4><pre class="cert-pre">${esc(c.reglamento_texto)}</pre></div>` : ''}
      ${c.reglamento_pdf_url ? `<p><a href="${esc(c.reglamento_pdf_url)}" target="_blank" rel="noopener">Descargar reglamento (PDF)</a></p>` : ''}
      ${c.requiere_reglamento_firmado ? '<p><span class="catalog-badge catalog-badge--warn">Requiere reglamento firmado</span></p>' : ''}
      ${c.software_nombre ? `<div class="cert-block"><h4>Software: ${esc(c.software_nombre)}</h4>
        ${c.software_url ? `<p><a href="${esc(c.software_url)}" target="_blank" rel="noopener">Descargar / acceder</a></p>` : ''}
        ${c.software_instrucciones ? `<pre class="cert-pre">${esc(c.software_instrucciones)}</pre>` : ''}</div>` : ''}
      ${(c.docs_requeridos_labels || []).length ? `<div class="cert-block"><h4>Documentos requeridos</h4><ul>${c.docs_requeridos_labels.map((l) => '<li>' + esc(l) + '</li>').join('')}</ul></div>` : ''}
      ${c.notas_asesor ? `<div class="cert-block cert-block--info"><h4>Notas para asesores</h4><pre class="cert-pre">${esc(c.notas_asesor)}</pre></div>` : ''}
    `;
    openModal('cert-modal-detalle');
  }

  document.getElementById('cert-detalle-solicitar')?.addEventListener('click', () => {
    closeModal(document.getElementById('cert-detalle-solicitar'));
    abrirNuevaSolicitud(productoDetalleId);
  });

  async function cargarSolicitudes() {
    const tbody = document.querySelector('#cert-tabla-solicitudes tbody');
    const estado = document.getElementById('cert-filtro-estado')?.value || '';
    const q = document.getElementById('cert-buscar-sol')?.value?.trim() || '';
    const url = new URL(api, window.location.href);
    url.searchParams.set('action', 'solicitudes');
    if (estado) url.searchParams.set('estado', estado);
    if (q) url.searchParams.set('q', q);
    try {
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const data = await res.json();
      const filas = data.solicitudes || [];
      const estLabels = cfg.estados || {};
      if (!filas.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="color:#888;">Sin solicitudes</td></tr>';
        return;
      }
      tbody.innerHTML = filas.map((s) => `<tr>
        <td>${fmtFecha(s.creado_en)}</td>
        <td>${esc(s.alumno)}</td>
        <td>${esc(s.numero_control)}</td>
        <td>${esc(s.certificacion)}</td>
        <td>${fmtFecha(s.fecha_examen || s.fecha_solicitada)} ${s.hora_solicitada ? fmtHora(s.hora_solicitada) : ''}</td>
        <td><span class="catalog-badge">${esc(estLabels[s.estado] || s.estado)}</span></td>
        <td><button type="button" class="secondary cert-abrir-sol" data-id="${s.id_solicitud}">Abrir</button></td>
      </tr>`).join('');
      tbody.querySelectorAll('.cert-abrir-sol').forEach((btn) => {
        btn.addEventListener('click', () => abrirExpediente(parseInt(btn.getAttribute('data-id'), 10)));
      });
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="7" style="color:#c62828;">${esc(err.message)}</td></tr>`;
    }
  }

  async function abrirExpediente(idSolicitud) {
    const url = new URL(api, window.location.href);
    url.searchParams.set('action', 'solicitud');
    url.searchParams.set('id_solicitud', String(idSolicitud));
    const res = await fetch(url.toString(), { credentials: 'same-origin' });
    const data = await res.json();
    if (data.status !== 'ok') { alert(data.message); return; }
    const s = data.solicitud;
    const puedeSupervisar = data.puede_supervisar || cfg.puedeSupervisar;
    const accLabels = data.campos_acceso_labels || cfg.camposAccesoLabels || {};
    const famCfg = s.familia_config || {};
    const estOpts = Object.entries(cfg.estados || {}).map(([k, v]) =>
      `<option value="${k}"${s.estado === k ? ' selected' : ''}>${esc(v)}</option>`).join('');
    const tipos = cfg.tiposDoc || {};
    const docsHtml = (s.docs_requeridos || []).map((tipo) => {
      const sub = (s.documentos || []).find((d) => d.tipo === tipo);
      return `<div class="cert-doc-row">
        <strong>${esc(tipos[tipo] || tipo)}</strong>
        ${sub ? `<a href="${esc(sub.url)}" target="_blank" rel="noopener">Ver archivo</a>` : '<span style="color:#c62828;">Pendiente</span>'}
        <form class="cert-upload-form" data-tipo="${esc(tipo)}" data-id="${s.id_solicitud}">
          <input type="file" accept=".pdf,image/*" required>
          <button type="submit" class="secondary">Subir</button>
        </form>
      </div>`;
    }).join('');

    const horaSolVal = (s.hora_solicitada || '').slice(0, 5);
    const horaConfVal = (s.hora_confirmada || '').slice(0, 5);
    const acc = s.acceso_vigente || {};
    const accesoView = renderCamposAcceso(s.campos_acceso || [], accLabels, acc, 'view');
    const accesoForm = renderCamposAcceso(s.campos_acceso || [], accLabels, acc, 'form');

    const panelConfirmar = puedeSupervisar && !s.fecha_confirmada && (s.estado === 'pendiente_confirmacion' || s.fecha_solicitada)
      ? `<div class="cert-block cert-block--warn">
        <h4>Confirmación del supervisor académico</h4>
        <p>El alumno solicitó: <strong>${fmtFecha(s.fecha_solicitada)} ${fmtHora(s.hora_solicitada)}</strong></p>
        <form id="cert-form-confirmar" class="cert-form-grid">
          <input type="hidden" name="id_solicitud" value="${s.id_solicitud}">
          <div><label>Fecha confirmada *</label><input type="date" name="fecha_confirmada" value="${esc((s.fecha_solicitada || '').slice(0, 10))}" required></div>
          <div><label>Hora confirmada *</label><input type="time" name="hora_confirmada" value="${esc(horaSolVal)}" required></div>
          ${famCfg.presencial ? `<div class="full"><label>Dirección de la sede *</label><textarea name="sede_direccion" rows="2" required>${esc(s.sede_direccion || '')}</textarea></div>` : ''}
          <div><label>Contacto supervisor</label><input type="text" name="contacto_supervisor" placeholder="Teléfono o correo"></div>
          <div><label>Nombre supervisor</label><input type="text" name="contacto_nombre"></div>
          <div class="full"><button type="submit" class="primary">Confirmar fecha y hora</button></div>
        </form>
      </div>` : '';

    const panelAccesos = puedeSupervisar && famCfg.requiere_credenciales_separadas && s.fecha_confirmada
      && (s.estado === 'pendiente_credenciales' || !acc.id_acceso)
      ? `<div class="cert-block cert-block--info">
        <h4>Entregar datos de acceso al alumno</h4>
        <p>Examen confirmado: <strong>${fmtFecha(s.fecha_confirmada)} ${fmtHora(s.hora_confirmada)}</strong></p>
        <form id="cert-form-accesos" class="cert-form-grid">
          <input type="hidden" name="id_solicitud" value="${s.id_solicitud}">
          ${accesoForm}
          <div class="full"><button type="submit" class="primary">Guardar datos de acceso</button></div>
        </form>
      </div>` : '';

    const panelAccesoVigente = acc.id_acceso
      ? `<div class="cert-block cert-block--ok">
        <h4>Datos de acceso vigentes</h4>
        ${accesoView || '<p>Confirmación registrada.</p>'}
        ${s.fecha_confirmada ? `<p><strong>Fecha examen:</strong> ${fmtFecha(s.fecha_confirmada)} ${fmtHora(s.hora_confirmada)}</p>` : ''}
        ${s.sede_direccion ? `<p><strong>Sede:</strong> ${esc(s.sede_direccion)}</p>` : ''}
      </div>` : '';

    const histReag = (s.historial_reagendamientos || []).length
      ? `<details class="cert-block"><summary>Historial de reagendamientos (${s.reagendamientos || 0})</summary><ul>${
        s.historial_reagendamientos.map((r) => `<li>${fmtFecha(r.fecha_anterior)} ${fmtHora(r.hora_anterior)} → ${fmtFecha(r.fecha_nueva)} ${fmtHora(r.hora_nueva)}${r.motivo ? ' — ' + esc(r.motivo) : ''}</li>`).join('')
      }</ul></details>` : '';

    const panelReagendar = s.estado !== 'cancelada' && s.estado !== 'completada'
      ? `<div class="cert-block">
        <h4>Reagendar examen</h4>
        <p style="font-size:0.9rem; color:#666;">En la mayoría de los casos los datos de acceso cambian; el supervisor deberá confirmar la nueva fecha y enviar credenciales nuevas.</p>
        <form id="cert-form-reagendar" class="cert-form-grid">
          <input type="hidden" name="id_solicitud" value="${s.id_solicitud}">
          <div><label>Nueva fecha *</label><input type="date" name="fecha_nueva" required></div>
          <div><label>Nueva hora *</label><input type="time" name="hora_nueva" required></div>
          <div class="full"><label>Motivo</label><textarea name="motivo" rows="2"></textarea></div>
          <div class="full"><button type="submit" class="secondary">Solicitar reagendamiento</button></div>
        </form>
      </div>` : '';

    document.getElementById('cert-expediente-body').innerHTML = `
      <h3 style="margin-top:0;">${esc(s.certificacion)}</h3>
      <p><span class="catalog-badge">${esc((cfg.estados || {})[s.estado] || s.estado)}</span>
        ${s.familia_label ? ` · <strong>${esc(s.familia_label)}</strong>` : ''}</p>
      <p><strong>Alumno:</strong> ${esc(s.alumno)} (${esc(s.numero_control)})</p>
      <p><strong>Tel:</strong> ${esc(s.telefono || '—')} · <strong>Email:</strong> ${esc(s.email || '—')}</p>
      <p><strong>Fecha/hora solicitada:</strong> ${fmtFecha(s.fecha_solicitada)} ${fmtHora(s.hora_solicitada)}</p>
      ${s.fecha_confirmada ? `<p><strong>Fecha/hora confirmada:</strong> ${fmtFecha(s.fecha_confirmada)} ${fmtHora(s.hora_confirmada)}</p>` : ''}
      ${s.sede_direccion && !acc.id_acceso ? `<p><strong>Sede:</strong> ${esc(s.sede_direccion)}</p>` : ''}
      ${famCfg.instruccion_alumno ? `<div class="cert-block"><h4>Instrucciones para el alumno</h4><p>${esc(famCfg.instruccion_alumno)}</p></div>` : ''}
      ${s.protocolo ? `<div class="cert-block"><h4>Protocolo</h4><pre class="cert-pre">${esc(s.protocolo)}</pre></div>` : ''}
      ${s.software_nombre ? `<p><strong>Software:</strong> ${esc(s.software_nombre)} ${s.software_url ? `<a href="${esc(s.software_url)}" target="_blank">Enlace</a>` : ''}</p>` : ''}
      ${panelConfirmar}
      ${panelAccesos}
      ${panelAccesoVigente}
      ${panelReagendar}
      ${histReag}
      <div id="cert-comisiones-mount" class="cert-comisiones-mount">Cargando comisiones…</div>
      <form id="cert-form-expediente" style="margin:16px 0;">
        <input type="hidden" name="id_solicitud" value="${s.id_solicitud}">
        <div class="cert-form-grid">
          <div><label>Estado</label><select name="estado">${estOpts}</select></div>
          <div><label>Fecha solicitada</label><input type="date" name="fecha_solicitada" value="${esc((s.fecha_solicitada || '').slice(0, 10))}"></div>
          <div><label>Hora solicitada</label><input type="time" name="hora_solicitada" value="${esc(horaSolVal)}"></div>
          <div class="full"><label>Notas</label><textarea name="notas" rows="2">${esc(s.notas || '')}</textarea></div>
        </div>
        <button type="submit" class="secondary">Guardar cambios</button>
      </form>
      <h4>Documentos</h4>
      ${docsHtml || '<p style="color:#888;">Esta certificación no requiere documentos adicionales.</p>'}
      <p id="cert-exp-msg" class="catalog-alert" style="display:none; margin-top:10px;"></p>
    `;

    async function postAccion(action, formEl) {
      const fd = new FormData(formEl);
      fd.append('action', action);
      const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
      return r.json();
    }

    function showExpMsg(d) {
      const msg = document.getElementById('cert-exp-msg');
      msg.style.display = 'block';
      msg.className = 'catalog-alert ' + (d.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');
      msg.textContent = d.message || '';
    }

    document.getElementById('cert-form-expediente')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const d = await postAccion('actualizar_solicitud', e.target);
      showExpMsg(d);
      if (d.status === 'ok') cargarSolicitudes();
    });

    document.getElementById('cert-form-confirmar')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const d = await postAccion('confirmar_fecha', e.target);
      showExpMsg(d);
      if (d.status === 'ok') { cargarSolicitudes(); abrirExpediente(idSolicitud); }
    });

    document.getElementById('cert-form-accesos')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const d = await postAccion('guardar_accesos', e.target);
      showExpMsg(d);
      if (d.status === 'ok') { cargarSolicitudes(); abrirExpediente(idSolicitud); }
    });

    document.getElementById('cert-form-reagendar')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const d = await postAccion('reagendar', e.target);
      showExpMsg(d);
      if (d.status === 'ok') { cargarSolicitudes(); abrirExpediente(idSolicitud); }
    });

    document.querySelectorAll('.cert-upload-form').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData();
        fd.append('action', 'subir_documento');
        fd.append('id_solicitud', form.getAttribute('data-id'));
        fd.append('tipo', form.getAttribute('data-tipo'));
        fd.append('archivo', form.querySelector('input[type=file]').files[0]);
        const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await r.json();
        if (d.status === 'ok') abrirExpediente(idSolicitud);
        else alert(d.message || 'Error');
      });
    });

    cargarPanelComisiones(idSolicitud);

    openModal('cert-modal-expediente');
  }

  function bindFormComisiones(idSolicitud) {
    const form = document.querySelector('#cert-panel-comisiones .cert-form-comisiones');
    if (!form || form.dataset.bound) return;
    form.dataset.bound = '1';
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      fd.append('fragment', '1');
      const msg = form.querySelector('.cert-com-msg');
      try {
        const r = await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' });
        const html = await r.text();
        const mount = document.getElementById('cert-comisiones-mount');
        if (mount) {
          mount.innerHTML = html;
          bindFormComisiones(idSolicitud);
        }
        if (msg) {
          msg.style.color = '#2e7d32';
          msg.textContent = 'Guardado.';
        }
        cargarSolicitudes();
      } catch (err) {
        if (msg) {
          msg.style.color = '#c62828';
          msg.textContent = err.message || 'Error';
        }
      }
    });
  }

  async function cargarPanelComisiones(idSolicitud) {
    const mount = document.getElementById('cert-comisiones-mount');
    if (!mount) return;
    try {
      const url = cfg.comisionesPartial || 'php/certificacion_expediente_comisiones.php';
      const r = await fetch(url + '?id_solicitud=' + encodeURIComponent(String(idSolicitud)), { credentials: 'same-origin' });
      mount.innerHTML = await r.text();
      bindFormComisiones(idSolicitud);
    } catch (err) {
      mount.innerHTML = '<p class="catalog-alert catalog-alert--error">' + esc(err.message) + '</p>';
    }
  }

  async function cargarSelectProductos(selectEl, selectedId) {
    const url = new URL(api, window.location.href);
    url.searchParams.set('action', 'catalogo');
    const res = await fetch(url.toString(), { credentials: 'same-origin' });
    const data = await res.json();
    selectEl.innerHTML = '<option value="">Seleccione…</option>';
    (data.certificaciones || []).forEach((c) => {
      const o = document.createElement('option');
      o.value = c.id_producto;
      o.textContent = c.nombre + ' — ' + (c.precio_fmt || '');
      if (selectedId && parseInt(selectedId, 10) === c.id_producto) o.selected = true;
      selectEl.appendChild(o);
    });
  }

  async function cargarAlumnosSelect() {
    const sel = document.getElementById('cert-sol-alumno');
    if (!sel) return;
    const r = await fetch(api + '?action=alumnos', { credentials: 'same-origin' });
    const data = await r.json();
    sel.innerHTML = '<option value="">— Nuevo alumno de certificación —</option>';
    (data.alumnos || []).forEach((a) => {
      const o = document.createElement('option');
      o.value = a.id_alumno;
      o.textContent = (a.nombre_completo || '').trim() + ' · ' + (a.numero_control || '');
      sel.appendChild(o);
    });
  }

  function abrirNuevaSolicitud(idProducto) {
    cargarSelectProductos(document.getElementById('cert-sol-producto'), idProducto || 0);
    cargarAlumnosSelect();
    document.getElementById('cert-form-solicitud')?.reset();
    if (idProducto) document.getElementById('cert-sol-producto').value = String(idProducto);
    openModal('cert-modal-solicitud');
  }

  document.getElementById('cert-btn-nueva')?.addEventListener('click', () => abrirNuevaSolicitud(0));

  document.getElementById('cert-sol-alumno')?.addEventListener('change', () => {
    const id = parseInt(document.getElementById('cert-sol-alumno').value, 10);
    document.getElementById('cert-sol-nuevo').style.opacity = id > 0 ? '0.5' : '1';
  });

  document.getElementById('cert-form-solicitud')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'crear_solicitud');
    fd.append('id_producto', document.getElementById('cert-sol-producto').value);
    fd.append('id_alumno', document.getElementById('cert-sol-alumno').value || '0');
    fd.append('nombres', document.getElementById('cert-sol-nombres').value);
    fd.append('apellido_paterno', document.getElementById('cert-sol-apPat').value);
    fd.append('apellido_materno', document.getElementById('cert-sol-apMat').value);
    fd.append('telefono', document.getElementById('cert-sol-tel').value);
    fd.append('email', document.getElementById('cert-sol-email').value);
    fd.append('fecha_solicitada', document.getElementById('cert-sol-fecha').value);
    fd.append('hora_solicitada', document.getElementById('cert-sol-hora').value);
    fd.append('notas', document.getElementById('cert-sol-notas').value);
    const msg = document.getElementById('cert-sol-msg');
    try {
      const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      msg.style.display = 'block';
      msg.className = 'catalog-alert ' + (d.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');
      msg.textContent = d.message || '';
      if (d.status === 'ok') {
        setTimeout(() => {
          closeModal(msg);
          if (d.id_solicitud) abrirExpediente(d.id_solicitud);
        }, 600);
      }
    } catch (err) {
      msg.style.display = 'block';
      msg.className = 'catalog-alert catalog-alert--error';
      msg.textContent = err.message;
    }
  });

  let catalogoCampos = [];

  async function cargarCatalogoCampos() {
    const r = await fetch(api + '?action=campos_catalogo', { credentials: 'same-origin' });
    const d = await r.json();
    catalogoCampos = d.campos || [];
    const box = document.getElementById('cert-cfg-campos');
    if (!box) return;
    box.innerHTML = catalogoCampos.map((c) => `
      <label style="display:flex; align-items:center; gap:8px; margin:4px 0; font-size:0.88rem;">
        <input type="checkbox" data-campo-clave="${esc(c.clave)}" class="cert-campo-chk">
        <span style="flex:1;">${esc(c.etiqueta)} <small style="color:#888;">(${esc(c.categoria)})</small></span>
        <select data-campo-llenado="${esc(c.clave)}" class="cert-campo-llenado" disabled>
          <option value="asesor">Asesor</option>
          <option value="alumno">Alumno</option>
          <option value="supervisor">Supervisor</option>
        </select>
      </label>
    `).join('');
    box.querySelectorAll('.cert-campo-chk').forEach((chk) => {
      chk.addEventListener('change', () => {
        const sel = box.querySelector(`[data-campo-llenado="${chk.dataset.campoClave}"]`);
        if (sel) sel.disabled = !chk.checked;
      });
    });
  }

  function leerCamposConfigSeleccionados() {
    const box = document.getElementById('cert-cfg-campos');
    if (!box) return [];
    const out = [];
    let orden = 0;
    box.querySelectorAll('.cert-campo-chk:checked').forEach((chk) => {
      const clave = chk.dataset.campoClave;
      const sel = box.querySelector(`[data-campo-llenado="${clave}"]`);
      out.push({
        clave_campo: clave,
        obligatorio: 0,
        llenado_por: sel?.value || 'asesor',
        orden: orden++,
      });
    });
    return out;
  }

  function aplicarCamposProducto(campos) {
    const box = document.getElementById('cert-cfg-campos');
    if (!box) return;
    const map = {};
    (campos || []).forEach((c) => { map[c.clave_campo] = c; });
    box.querySelectorAll('.cert-campo-chk').forEach((chk) => {
      const clave = chk.dataset.campoClave;
      const cfg = map[clave];
      chk.checked = !!cfg;
      const sel = box.querySelector(`[data-campo-llenado="${clave}"]`);
      if (sel) {
        sel.disabled = !chk.checked;
        if (cfg?.llenado_por) sel.value = cfg.llenado_por;
      }
    });
  }

  if (cfg.puedeAdmin) {
    cargarCatalogoCampos();
    document.getElementById('cert-btn-config')?.addEventListener('click', async () => {
      const sel = document.getElementById('cert-cfg-producto');
      const r = await fetch(api + '?action=productos_sin_meta', { credentials: 'same-origin' });
      const data = await r.json();
      sel.innerHTML = '<option value="">Seleccione producto…</option>';
      (data.productos || []).forEach((p) => {
        const o = document.createElement('option');
        o.value = p.id_producto;
        o.textContent = p.nombre + ' (' + p.clave + ')';
        sel.appendChild(o);
      });
      document.getElementById('cert-form-config')?.reset();
      openModal('cert-modal-config');
    });

    document.getElementById('cert-cfg-producto')?.addEventListener('change', async () => {
      const id = parseInt(document.getElementById('cert-cfg-producto').value, 10);
      if (!id) return;
      const url = new URL(api, window.location.href);
      url.searchParams.set('action', 'detalle');
      url.searchParams.set('id_producto', String(id));
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const data = await res.json();
      const c = data.certificacion || {};
      document.getElementById('cert-cfg-organismo').value = c.organismo || '';
      const famSel = document.getElementById('cert-cfg-familia');
      if (famSel && c.familia) famSel.value = c.familia;
      document.getElementById('cert-cfg-familia-hint').textContent = familiaHint(famSel?.value || c.familia || 'certiport');
      document.getElementById('cert-cfg-protocolo').value = c.protocolo || '';
      document.getElementById('cert-cfg-reglamento').value = c.reglamento_texto || '';
      document.getElementById('cert-cfg-req-reglamento').checked = !!Number(c.requiere_reglamento_firmado);
      document.getElementById('cert-cfg-software-nombre').value = c.software_nombre || '';
      document.getElementById('cert-cfg-software-url').value = c.software_url || '';
      document.getElementById('cert-cfg-software-inst').value = c.software_instrucciones || '';
      document.getElementById('cert-cfg-notas').value = c.notas_asesor || '';
      document.getElementById('cert-cfg-com-asesor').value = c.comision_asesor_default ?? 0;
      document.getElementById('cert-cfg-com-gerente').value = c.comision_gerente_default ?? 0;
      document.querySelectorAll('input[name=docs_req]').forEach((cb) => {
        cb.checked = (c.docs_requeridos || []).includes(cb.value);
      });
      const link = document.getElementById('cert-cfg-reglamento-link');
      link.innerHTML = c.reglamento_pdf_url ? `<a href="${esc(c.reglamento_pdf_url)}" target="_blank">Reglamento actual</a>` : '';
      const r2 = await fetch(api + '?action=campos_producto&id_producto=' + id, { credentials: 'same-origin' });
      const d2 = await r2.json();
      aplicarCamposProducto(d2.campos || []);
    });

    document.getElementById('cert-cfg-familia')?.addEventListener('change', () => {
      const key = document.getElementById('cert-cfg-familia').value;
      document.getElementById('cert-cfg-familia-hint').textContent = familiaHint(key);
      const fam = (cfg.familias || {})[key];
      if (!fam) return;
      document.getElementById('cert-cfg-req-reglamento').checked = (fam.docs || []).includes('reglamento_firmado');
      document.querySelectorAll('input[name=docs_req]').forEach((cb) => {
        cb.checked = (fam.docs || []).includes(cb.value);
      });
    });

    document.getElementById('cert-form-config')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData();
      fd.append('action', 'guardar_meta');
      fd.append('id_producto', document.getElementById('cert-cfg-producto').value);
      fd.append('familia', document.getElementById('cert-cfg-familia').value);
      fd.append('organismo', document.getElementById('cert-cfg-organismo').value);
      fd.append('protocolo', document.getElementById('cert-cfg-protocolo').value);
      fd.append('reglamento_texto', document.getElementById('cert-cfg-reglamento').value);
      fd.append('requiere_reglamento_firmado', document.getElementById('cert-cfg-req-reglamento').checked ? '1' : '');
      fd.append('software_nombre', document.getElementById('cert-cfg-software-nombre').value);
      fd.append('software_url', document.getElementById('cert-cfg-software-url').value);
      fd.append('software_instrucciones', document.getElementById('cert-cfg-software-inst').value);
      fd.append('notas_asesor', document.getElementById('cert-cfg-notas').value);
      fd.append('comision_asesor_default', document.getElementById('cert-cfg-com-asesor').value);
      fd.append('comision_gerente_default', document.getElementById('cert-cfg-com-gerente').value);
      const docs = [];
      document.querySelectorAll('input[name=docs_req]:checked').forEach((cb) => docs.push(cb.value));
      fd.append('documentos_requeridos', JSON.stringify(docs));
      const f = document.getElementById('cert-cfg-reglamento-file');
      if (f?.files?.[0]) fd.append('reglamento_archivo', f.files[0]);
      const msg = document.getElementById('cert-cfg-msg');
      const r = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (d.status === 'ok') {
        const idProd = document.getElementById('cert-cfg-producto').value;
        const fd2 = new FormData();
        fd2.append('action', 'guardar_campos_producto');
        fd2.append('id_producto', idProd);
        fd2.append('campos', JSON.stringify(leerCamposConfigSeleccionados()));
        await fetch(api, { method: 'POST', body: fd2, credentials: 'same-origin' });
      }
      msg.style.display = 'block';
      msg.className = 'catalog-alert ' + (d.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');
      msg.textContent = d.message || '';
      if (d.status === 'ok') cargarCatalogo();
    });
  }

  document.getElementById('cert-buscar')?.addEventListener('input', () => {
    clearTimeout(window._certBuscarT);
    window._certBuscarT = setTimeout(cargarCatalogo, 350);
  });
  document.getElementById('cert-filtro-estado')?.addEventListener('change', cargarSolicitudes);
  document.getElementById('cert-buscar-sol')?.addEventListener('input', () => {
    clearTimeout(window._certSolT);
    window._certSolT = setTimeout(cargarSolicitudes, 350);
  });

  cargarCatalogo();
})();
