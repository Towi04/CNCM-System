(function () {
  const cfg = window.HAY_NOMINA || {};
  const api = cfg.api || 'php/nomina_api.php';
  const exportBase = cfg.export || 'php/nomina_export.php';
  const puedeAjustar = !!cfg.puede_ajustar;
  const idLiqInicial = Number(cfg.id_liquidacion || 0);
  const supPrefill = cfg.sup_prefill || {};
  const pdfBase = cfg.pdf || 'php/nomina_pdf.php';

  let catalogo = { tipos_pago: {}, areas: [], niveles_por_area: {} };
  let personal = [];
  let liquidaciones = [];
  let idLiqActiva = 0;
  let liqActual = null;
  let supCatalogo = { motivos: {}, reglas: {}, grupos: [], profesores: [], suplencias: [] };

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function fmtMxn(n) {
    const x = Number(n) || 0;
    return '$' + x.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function fmtFecha(s) {
    if (!s) return '—';
    const p = String(s).slice(0, 10).split('-');
    if (p.length !== 3) return s;
    return p[2] + '/' + p[1] + '/' + p[0];
  }

  async function apiGet(params) {
    const r = await fetch(api + '?' + new URLSearchParams(params).toString(), { credentials: 'same-origin' });
    return r.json();
  }

  async function apiPost(body) {
    const r = await fetch(api, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(body).toString(),
    });
    return r.json();
  }

  async function actualizarRango() {
    const tipo = document.getElementById('nom-tipo-periodo')?.value || 'quincena';
    const fecha = document.getElementById('nom-fecha')?.value || '';
    const data = await apiGet({ accion: 'rango', tipo_periodo: tipo, fecha });
    const el = document.getElementById('nom-rango-preview');
    if (el && data.status === 'ok') {
      el.textContent = data.rango?.etiqueta || '';
    }
  }

  function renderListaLiq() {
    const ul = document.getElementById('nom-lista-liq');
    if (!ul) return;
    if (!liquidaciones.length) {
      ul.innerHTML = '<li style="color:#888;">Sin liquidaciones aún.</li>';
      return;
    }
    ul.innerHTML = liquidaciones.map((l) => {
      const active = Number(l.id_liquidacion) === idLiqActiva ? ' active' : '';
      const estado = l.estado === 'cerrada' ? 'cerrada' : 'borrador';
      return `<li><button type="button" class="nom-liq-btn${active}" data-id="${l.id_liquidacion}">
        <strong>${esc(l.etiqueta || l.tipo_periodo)}</strong>
        <small>${esc(l.total_fmt || fmtMxn(l.total))} · ${esc(estado)}</small>
      </button></li>`;
    }).join('');
    ul.querySelectorAll('.nom-liq-btn').forEach((btn) => {
      btn.addEventListener('click', () => verLiquidacion(Number(btn.dataset.id)));
    });
  }

  function lineaConceptoHtml(ln) {
    const manual = Number(ln.es_manual) === 1 ? ' <span class="nom-badge-manual" title="Ajuste manual">manual</span>' : '';
    const qty = ln.cantidad != null && Number(ln.cantidad) !== 1 ? ` × ${ln.cantidad}` : '';
    const tar = ln.tarifa != null && Number(ln.tarifa) > 0 ? ` @ ${fmtMxn(ln.tarifa)}` : '';
    return esc(ln.concepto) + qty + tar + ' · <strong>' + fmtMxn(ln.importe) + '</strong>' + manual;
  }

  function renderBitacora(ajustes) {
    if (!ajustes || !ajustes.length) {
      return '<p class="nom-ajustes-empty">Sin ajustes manuales registrados.</p>';
    }
    return `<div class="catalog-table-wrap">
      <table class="catalog-table nom-ajustes-tabla">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Acción</th>
            <th>Persona</th>
            <th>Cambio</th>
            <th>Observación (no se imprime)</th>
            <th>Editor</th>
          </tr>
        </thead>
        <tbody>
          ${ajustes.map((a) => {
            let cambio = '';
            if (a.accion === 'agregar') {
              cambio = esc(a.concepto_despues) + ' ' + fmtMxn(a.importe_despues);
            } else if (a.accion === 'editar') {
              cambio = esc(a.concepto_antes) + ' ' + fmtMxn(a.importe_antes) + ' → ' + esc(a.concepto_despues) + ' ' + fmtMxn(a.importe_despues);
            } else {
              cambio = 'Eliminó «' + esc(a.concepto_antes) + '» ' + fmtMxn(a.importe_antes);
            }
            return `<tr>
              <td><small>${esc(fmtFecha(a.creado_en))}</small></td>
              <td>${esc(a.accion)}</td>
              <td>${esc(a.afectado_nombre)}</td>
              <td>${cambio}</td>
              <td class="nom-obs-cell">${esc(a.observacion)}</td>
              <td><small>${esc(a.editor_nombre)}</small></td>
            </tr>`;
          }).join('')}
        </tbody>
      </table>
    </div>`;
  }

  function renderDetalle(liq) {
    const box = document.getElementById('nom-detalle');
    if (!box || !liq) return;
    liqActual = liq;
    const cerrada = liq.estado === 'cerrada';
    const usuarios = liq.por_usuario || [];
    const lineas = liq.lineas || [];
    const editable = puedeAjustar && !cerrada;

    box.innerHTML = `
      <div class="nomina-resumen">
        <div class="reporte-cartera-card"><small>Periodo</small><strong>${esc(liq.etiqueta || '')}</strong></div>
        <div class="reporte-cartera-card"><small>Total</small><strong>${esc(liq.total_fmt || fmtMxn(liq.total))}</strong></div>
        <div class="reporte-cartera-card"><small>Estado</small><strong><span class="badge-estado badge-${esc(liq.estado)}">${esc(liq.estado)}</span></strong></div>
        <div class="reporte-cartera-card"><small>Personas</small><strong>${usuarios.length}</strong></div>
      </div>
      <div class="nomina-acciones">
        <button type="button" class="secondary" id="btn-nom-exp-det"><i class="fas fa-file-csv"></i> Exportar detalle</button>
        <button type="button" class="secondary" id="btn-nom-exp-res"><i class="fas fa-file-csv"></i> Exportar resumen</button>
        <button type="button" class="secondary" id="btn-nom-exp-pdf"><i class="fas fa-file-pdf"></i> PDF sobres</button>
        ${editable ? '<button type="button" class="primary" id="btn-nom-add-linea"><i class="fas fa-plus"></i> Agregar concepto manual</button>' : ''}
        ${cerrada ? '' : '<button type="button" class="secondary" id="btn-nom-cerrar"><i class="fas fa-lock"></i> Cerrar nómina</button>'}
      </div>
      ${editable ? '<p class="nom-ajuste-hint"><i class="fas fa-info-circle"></i> Use «Agregar concepto manual» para horas extra, descuentos o pagos extraordinarios. Solo esas líneas se pueden editar o eliminar; las calculadas se actualizan al recalcular.</p>' : ''}
      <h3 class="nom-seccion-titulo">Detalle por persona</h3>
      <div class="catalog-table-wrap">
        <table class="catalog-table">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Rol</th>
              <th>Conceptos</th>
              <th>Subtotal</th>
              ${editable ? '<th></th>' : ''}
            </tr>
          </thead>
          <tbody>
            ${usuarios.length ? usuarios.map((u) => `
              <tr>
                <td><strong>${esc(u.nombre)}</strong></td>
                <td>${esc(u.rol)}</td>
                <td>${(u.lineas || []).map((ln) => lineaConceptoHtml(ln)).join('<br>')}</td>
                <td><strong>${fmtMxn(u.subtotal)}</strong></td>
                ${editable ? `<td>${(u.lineas || []).map((ln) => {
                  if (Number(ln.es_manual) !== 1) return '<span class="nom-linea-auto">—</span>';
                  return `<button type="button" class="linkish btn-edit-linea" data-id="${ln.id_linea}">Editar</button>
                   <button type="button" class="linkish btn-del-linea" data-id="${ln.id_linea}">Eliminar</button>`;
                }).join('<br>')}</td>` : ''}
              </tr>
            `).join('') : '<tr><td colspan="' + (editable ? 5 : 4) + '" style="color:#888;">Sin conceptos en esta liquidación.</td></tr>'}
          </tbody>
        </table>
      </div>
      <details class="nom-bitacora" open>
        <summary><i class="fas fa-clipboard-list"></i> Bitácora de ajustes manuales <small>(solo consulta interna)</small></summary>
        ${renderBitacora(liq.ajustes || [])}
      </details>
      <div id="nom-modal-ajuste" class="nom-modal" hidden>
        <div class="nom-modal-backdrop"></div>
        <form class="nom-modal-card" id="form-ajuste-linea">
          <h3 id="nom-modal-titulo">Concepto manual</h3>
          <input type="hidden" id="aj-id-linea" value="">
          <label>Persona
            <select id="aj-usuario" required>${personal.map((p) =>
              `<option value="${p.id_usuario}">${esc(p.nombre_completo)} (${esc(p.rol)})</option>`
            ).join('')}</select>
          </label>
          <label>Concepto
            <input type="text" id="aj-concepto" required placeholder="Ej. Hora extra evento">
          </label>
          <div class="nom-modal-row">
            <label>Cantidad <input type="number" id="aj-cantidad" step="0.25" value="1"></label>
            <label>Tarifa <input type="number" id="aj-tarifa" step="0.01" value="0"></label>
            <label>Importe <input type="number" id="aj-importe" step="0.01" placeholder="Auto"></label>
          </div>
          <label>Observación interna <span class="req">*</span>
            <textarea id="aj-observacion" rows="3" required placeholder="Motivo del ajuste (no se imprime en nómina)"></textarea>
          </label>
          <div class="nom-modal-actions">
            <button type="button" class="secondary" id="btn-aj-cancel">Cancelar</button>
            <button type="submit" class="primary">Guardar</button>
          </div>
        </form>
      </div>
    `;

    document.getElementById('btn-nom-exp-det')?.addEventListener('click', () => {
      window.location.href = exportBase + '?id_liquidacion=' + liq.id_liquidacion + '&modo=detalle';
    });
    document.getElementById('btn-nom-exp-res')?.addEventListener('click', () => {
      window.location.href = exportBase + '?id_liquidacion=' + liq.id_liquidacion + '&modo=resumen';
    });
    document.getElementById('btn-nom-exp-pdf')?.addEventListener('click', () => {
      window.open(pdfBase + '?id_liquidacion=' + liq.id_liquidacion, '_blank', 'noopener');
    });
    document.getElementById('btn-nom-cerrar')?.addEventListener('click', async () => {
      if (!confirm('¿Cerrar esta nómina? No podrá recalcularse ni editarse manualmente.')) return;
      const res = await apiPost({ accion: 'cerrar', id_liquidacion: String(liq.id_liquidacion) });
      alert(res.message || (res.status === 'ok' ? 'Cerrada' : 'Error'));
      if (res.status === 'ok') {
        await cargarCatalogo();
        verLiquidacion(liq.id_liquidacion);
      }
    });

    wireModalAjuste(liq);
    box.querySelectorAll('.btn-edit-linea').forEach((btn) => {
      btn.addEventListener('click', () => abrirModalEditar(Number(btn.dataset.id)));
    });
    box.querySelectorAll('.btn-del-linea').forEach((btn) => {
      btn.addEventListener('click', () => eliminarLinea(Number(btn.dataset.id)));
    });
    document.getElementById('btn-nom-add-linea')?.addEventListener('click', abrirModalAgregar);
  }

  function wireModalAjuste(liq) {
    const modal = document.getElementById('nom-modal-ajuste');
    const form = document.getElementById('form-ajuste-linea');
    const close = () => { if (modal) modal.hidden = true; };
    modal?.querySelector('.nom-modal-backdrop')?.addEventListener('click', close);
    document.getElementById('btn-aj-cancel')?.addEventListener('click', close);
    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const idLinea = document.getElementById('aj-id-linea')?.value || '';
      const body = {
        concepto: document.getElementById('aj-concepto')?.value || '',
        id_usuario: document.getElementById('aj-usuario')?.value || '',
        cantidad: document.getElementById('aj-cantidad')?.value || '1',
        tarifa: document.getElementById('aj-tarifa')?.value || '0',
        importe: document.getElementById('aj-importe')?.value || '',
        observacion: document.getElementById('aj-observacion')?.value || '',
      };
      let res;
      if (idLinea) {
        res = await apiPost({ accion: 'linea_editar', id_linea: idLinea, ...body });
      } else {
        res = await apiPost({ accion: 'linea_agregar', id_liquidacion: String(liq.id_liquidacion), ...body });
      }
      if (res.status !== 'ok') {
        alert(res.message || 'No se pudo guardar');
        return;
      }
      close();
      await cargarCatalogo();
      verLiquidacion(liq.id_liquidacion);
    });
  }

  function abrirModalAgregar() {
    const modal = document.getElementById('nom-modal-ajuste');
    if (!modal) return;
    document.getElementById('nom-modal-titulo').textContent = 'Agregar concepto manual';
    document.getElementById('aj-id-linea').value = '';
    document.getElementById('aj-concepto').value = '';
    document.getElementById('aj-cantidad').value = '1';
    document.getElementById('aj-tarifa').value = '0';
    document.getElementById('aj-importe').value = '';
    document.getElementById('aj-observacion').value = '';
    document.getElementById('aj-usuario').disabled = false;
    modal.hidden = false;
  }

  function abrirModalEditar(idLinea) {
    const ln = (liqActual?.lineas || []).find((l) => Number(l.id_linea) === idLinea);
    if (!ln || Number(ln.es_manual) !== 1) {
      alert('Solo se pueden editar conceptos agregados manualmente');
      return;
    }
    const modal = document.getElementById('nom-modal-ajuste');
    if (!modal) return;
    document.getElementById('nom-modal-titulo').textContent = 'Editar concepto';
    document.getElementById('aj-id-linea').value = String(idLinea);
    document.getElementById('aj-usuario').value = String(ln.id_usuario);
    document.getElementById('aj-usuario').disabled = true;
    document.getElementById('aj-concepto').value = ln.concepto || '';
    document.getElementById('aj-cantidad').value = ln.cantidad ?? '1';
    document.getElementById('aj-tarifa').value = ln.tarifa ?? '0';
    document.getElementById('aj-importe').value = ln.importe ?? '';
    document.getElementById('aj-observacion').value = '';
    modal.hidden = false;
  }

  async function eliminarLinea(idLinea) {
    const obs = prompt('Motivo de la eliminación (observación interna, obligatoria):');
    if (obs == null) return;
    if (!obs.trim()) {
      alert('Debe indicar el motivo');
      return;
    }
    const res = await apiPost({ accion: 'linea_eliminar', id_linea: String(idLinea), observacion: obs.trim() });
    alert(res.message || (res.status === 'ok' ? 'Eliminado' : 'Error'));
    if (res.status === 'ok' && liqActual) {
      await cargarCatalogo();
      verLiquidacion(liqActual.id_liquidacion);
    }
  }

  async function verLiquidacion(id) {
    idLiqActiva = id;
    renderListaLiq();
    const data = await apiGet({ accion: 'obtener', id_liquidacion: String(id) });
    if (data.status !== 'ok') {
      document.getElementById('nom-detalle').innerHTML = '<p style="color:#b71c1c;">' + esc(data.message || 'Error') + '</p>';
      return;
    }
    renderDetalle(data.liquidacion);
  }

  function opcionesTipoPago(selected) {
    const tipos = catalogo.tipos_pago || {};
    return Object.entries(tipos).map(([k, v]) =>
      `<option value="${esc(k)}"${k === selected ? ' selected' : ''}>${esc(v)}</option>`
    ).join('');
  }

  function opcionesAreas(selected) {
    return (catalogo.areas || []).map((a) =>
      `<option value="${a.id_area}"${Number(a.id_area) === Number(selected) ? ' selected' : ''}>${esc(a.nombre)}</option>`
    ).join('');
  }

  function opcionesNiveles(idArea, selected) {
    const niveles = catalogo.niveles_por_area?.[idArea] || catalogo.niveles_por_area?.[String(idArea)] || [];
    return niveles.map((n) =>
      `<option value="${n.id_nivel}"${Number(n.id_nivel) === Number(selected) ? ' selected' : ''}>${esc(n.nombre_display || n.numero)} (${fmtMxn(n.sueldo_base)}/mes)</option>`
    ).join('');
  }

  function renderConfig() {
    const tbody = document.querySelector('#nom-config-tabla tbody');
    if (!tbody) return;
    if (!personal.length) {
      tbody.innerHTML = '<tr><td colspan="8" style="color:#888;">No hay personal en este plantel.</td></tr>';
      return;
    }
    tbody.innerHTML = personal.map((p) => {
      const c = p.config || {};
      const cd = p.config_docencia || {};
      const idArea = c.id_hay_area || p.id_hay_area || '';
      const idNivel = c.id_hay_nivel || '';
      return `<tr class="nomina-config-row" data-id="${p.id_usuario}">
        <td><strong>${esc(p.nombre_completo)}</strong></td>
        <td>${esc(p.rol)}</td>
        <td><select class="cfg-tipo">${opcionesTipoPago(c.tipo_pago)}</select></td>
        <td><input type="number" class="cfg-monto" step="0.01" placeholder="Fijo" value="${c.monto_fijo != null ? c.monto_fijo : ''}" title="Monto fijo"></td>
        <td>
          <select class="cfg-area"><option value="">—</option>${opcionesAreas(idArea)}</select>
          <select class="cfg-nivel"><option value="">—</option>${opcionesNiveles(idArea, idNivel)}</select>
        </td>
        <td><input type="number" class="cfg-tarifa-doc" step="0.01" placeholder="$/hr docencia" value="${cd.tarifa_hora != null ? cd.tarifa_hora : 100}" title="Tarifa por hora de clase"></td>
        <td><input type="text" class="cfg-notas" value="${esc(c.notas || '')}"></td>
        <td><button type="button" class="secondary btn-cfg-save">Guardar</button></td>
      </tr>`;
    }).join('');

    tbody.querySelectorAll('.cfg-area').forEach((sel) => {
      sel.addEventListener('change', () => {
        const tr = sel.closest('tr');
        const nivelSel = tr?.querySelector('.cfg-nivel');
        if (nivelSel) {
          nivelSel.innerHTML = '<option value="">—</option>' + opcionesNiveles(sel.value, '');
        }
      });
    });

    tbody.querySelectorAll('.btn-cfg-save').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const tr = btn.closest('tr');
        const id = tr?.dataset.id;
        if (!id) return;
        btn.disabled = true;
        const res = await apiPost({
          accion: 'guardar_config',
          id_usuario: id,
          alcance: 'principal',
          tipo_pago: tr.querySelector('.cfg-tipo')?.value || '',
          monto_fijo: tr.querySelector('.cfg-monto')?.value || '',
          id_hay_area: tr.querySelector('.cfg-area')?.value || '',
          id_hay_nivel: tr.querySelector('.cfg-nivel')?.value || '',
          notas: tr.querySelector('.cfg-notas')?.value || '',
        });
        const resDoc = await apiPost({
          accion: 'guardar_config',
          id_usuario: id,
          alcance: 'docencia',
          tarifa_hora: tr.querySelector('.cfg-tarifa-doc')?.value || '',
          id_hay_area: tr.querySelector('.cfg-area')?.value || '',
        });
        btn.disabled = false;
        alert(res.message || resDoc.message || (res.status === 'ok' ? 'Guardado' : 'Error'));
        if (res.status === 'ok') cargarCatalogo();
      });
    });
  }

  function optsFromMap(map, selected) {
    return Object.entries(map || {}).map(([k, v]) =>
      `<option value="${esc(k)}"${k === selected ? ' selected' : ''}>${esc(v)}</option>`
    ).join('');
  }

  function renderSuplencias() {
    const tbody = document.querySelector('#sup-tabla tbody');
    if (!tbody) return;
    const rows = supCatalogo.suplencias || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" style="color:#888;">Sin suplencias activas.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map((s) => {
      const apoyo = s.pago_titular_concepto
        ? esc(s.pago_titular_concepto) + (s.pago_titular_monto ? ' ' + fmtMxn(s.pago_titular_monto) : '') + (s.pago_titular_horas ? ' (' + s.pago_titular_horas + ' hr)' : '')
        : '—';
      const notas = s.notas ? esc(s.notas) : '<span style="color:#aaa;">—</span>';
      return `<tr>
        <td><strong>${esc(s.grupo_clave)}</strong></td>
        <td>${esc(s.titular_nombre)}</td>
        <td>${esc(s.suplente_nombre || '—')}</td>
        <td>${fmtFecha(s.fecha_inicio)} – ${fmtFecha(s.fecha_fin)}</td>
        <td><small>${esc(s.regla_label)}</small></td>
        <td><small>${apoyo}</small></td>
        <td class="nom-sup-notas"><small>${notas}</small></td>
        <td>
          <button type="button" class="linkish btn-sup-edit" data-id="${s.id_suplencia}">Editar</button>
          <button type="button" class="linkish btn-sup-cancel" data-id="${s.id_suplencia}">Cancelar</button>
        </td>
      </tr>`;
    }).join('');

    tbody.querySelectorAll('.btn-sup-edit').forEach((btn) => {
      btn.addEventListener('click', () => editarSuplencia(Number(btn.dataset.id)));
    });
    tbody.querySelectorAll('.btn-sup-cancel').forEach((btn) => {
      btn.addEventListener('click', () => cancelarSuplencia(Number(btn.dataset.id)));
    });
  }

  function llenarSelectsSuplencia() {
    const selGrupo = document.getElementById('sup-grupo');
    const selTit = document.getElementById('sup-titular');
    const selSup = document.getElementById('sup-suplente');
    const selMot = document.getElementById('sup-motivo');
    const selReg = document.getElementById('sup-regla');
    if (selGrupo) {
      selGrupo.innerHTML = '<option value="">Seleccione…</option>' + (supCatalogo.grupos || []).map((g) =>
        `<option value="${g.id_grupo}" data-prof="${g.id_profesor || ''}">${esc(g.clave)}${g.profesor_nombre ? ' · ' + esc(g.profesor_nombre) : ''}</option>`
      ).join('');
    }
    const profOpts = (supCatalogo.profesores || []).map((p) =>
      `<option value="${p.id_usuario}">${esc(p.nombre + ' ' + p.apellido)} (${esc(p.rol)})</option>`
    ).join('');
    if (selTit) selTit.innerHTML = '<option value="">—</option>' + profOpts;
    if (selSup) selSup.innerHTML = '<option value="">—</option>' + profOpts;
    if (selMot) selMot.innerHTML = optsFromMap(supCatalogo.motivos);
    if (selReg) selReg.innerHTML = optsFromMap(supCatalogo.reglas);
  }

  function limpiarFormSuplencia() {
    document.getElementById('sup-id').value = '';
    document.getElementById('sup-grupo').value = '';
    document.getElementById('sup-titular').value = '';
    document.getElementById('sup-suplente').value = '';
    document.getElementById('sup-motivo').value = 'enfermedad';
    document.getElementById('sup-regla').value = 'solo_suplente';
    document.getElementById('sup-concepto').value = '';
    document.getElementById('sup-monto').value = '';
    document.getElementById('sup-horas').value = '';
    document.getElementById('sup-notas').value = '';
  }

  function editarSuplencia(id) {
    const s = (supCatalogo.suplencias || []).find((x) => Number(x.id_suplencia) === id);
    if (!s) return;
    document.getElementById('sup-id').value = String(id);
    document.getElementById('sup-grupo').value = String(s.id_grupo);
    document.getElementById('sup-titular').value = String(s.id_profesor_titular);
    document.getElementById('sup-suplente').value = s.id_profesor_suplente ? String(s.id_profesor_suplente) : '';
    document.getElementById('sup-desde').value = (s.fecha_inicio || '').slice(0, 10);
    document.getElementById('sup-hasta').value = (s.fecha_fin || '').slice(0, 10);
    document.getElementById('sup-motivo').value = s.motivo || 'enfermedad';
    document.getElementById('sup-regla').value = s.regla_pago || 'solo_suplente';
    document.getElementById('sup-concepto').value = s.pago_titular_concepto || '';
    document.getElementById('sup-monto').value = s.pago_titular_monto ?? '';
    document.getElementById('sup-horas').value = s.pago_titular_horas ?? '';
    document.getElementById('sup-notas').value = s.notas || '';
    document.querySelector('#nomina-tabs button[data-tab="suplencias"]')?.click();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async function cancelarSuplencia(id) {
    if (!confirm('¿Cancelar esta suplencia?')) return;
    const res = await apiPost({ accion: 'suplencia_cancelar', id_suplencia: String(id) });
    alert(res.message || (res.status === 'ok' ? 'Cancelada' : 'Error'));
    if (res.status === 'ok') cargarSuplencias();
  }

  async function guardarSuplencia() {
    const res = await apiPost({
      accion: 'suplencia_guardar',
      id_suplencia: document.getElementById('sup-id')?.value || '',
      id_grupo: document.getElementById('sup-grupo')?.value || '',
      id_profesor_titular: document.getElementById('sup-titular')?.value || '',
      id_profesor_suplente: document.getElementById('sup-suplente')?.value || '',
      fecha_inicio: document.getElementById('sup-desde')?.value || '',
      fecha_fin: document.getElementById('sup-hasta')?.value || '',
      motivo: document.getElementById('sup-motivo')?.value || '',
      regla_pago: document.getElementById('sup-regla')?.value || '',
      pago_titular_concepto: document.getElementById('sup-concepto')?.value || '',
      pago_titular_monto: document.getElementById('sup-monto')?.value || '',
      pago_titular_horas: document.getElementById('sup-horas')?.value || '',
      notas: document.getElementById('sup-notas')?.value || '',
    });
    alert(res.message || (res.status === 'ok' ? 'Guardado' : 'Error'));
    if (res.status === 'ok') {
      limpiarFormSuplencia();
      cargarSuplencias();
    }
  }

  async function cargarSuplencias() {
    const data = await apiGet({ accion: 'suplencia_catalogo' });
    if (data.status !== 'ok') return;
    supCatalogo = {
      motivos: data.motivos || {},
      reglas: data.reglas || {},
      grupos: data.grupos || [],
      profesores: data.profesores || [],
      suplencias: data.suplencias || [],
    };
    llenarSelectsSuplencia();
    renderSuplencias();
  }

  async function cargarCatalogo() {
    const data = await apiGet({ accion: 'catalogo' });
    if (data.status !== 'ok') return;
    catalogo = data.catalogo || catalogo;
    personal = data.personal || [];
    liquidaciones = data.liquidaciones || [];
    renderListaLiq();
    renderConfig();
  }

  async function generar() {
    const tipo = document.getElementById('nom-tipo-periodo')?.value || 'quincena';
    const fecha = document.getElementById('nom-fecha')?.value || '';
    const btn = document.getElementById('btn-nom-generar');
    if (btn) btn.disabled = true;
    const res = await apiPost({ accion: 'generar', tipo_periodo: tipo, fecha });
    if (btn) btn.disabled = false;
    if (res.status !== 'ok') {
      alert(res.message || 'No se pudo generar');
      return;
    }
    await cargarCatalogo();
    if (res.id_liquidacion) {
      verLiquidacion(res.id_liquidacion);
    } else if (res.liquidacion) {
      renderDetalle(res.liquidacion);
    }
    alert(res.message || 'Nómina generada');
  }

  document.querySelectorAll('#nomina-tabs button').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#nomina-tabs button').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      const tab = btn.dataset.tab;
      document.getElementById('nomina-panel-liquidacion').hidden = tab !== 'liquidacion';
      document.getElementById('nomina-panel-suplencias').hidden = tab !== 'suplencias';
      document.getElementById('nomina-panel-config').hidden = tab !== 'config';
      if (tab === 'suplencias' && !(supCatalogo.grupos || []).length) {
        cargarSuplencias();
      }
    });
  });

  document.getElementById('sup-grupo')?.addEventListener('change', (e) => {
    const opt = e.target.selectedOptions[0];
    const idProf = opt?.dataset?.prof;
    if (idProf) document.getElementById('sup-titular').value = idProf;
  });

  document.getElementById('btn-sup-guardar')?.addEventListener('click', guardarSuplencia);
  document.getElementById('btn-sup-limpiar')?.addEventListener('click', limpiarFormSuplencia);
  document.getElementById('nom-tipo-periodo')?.addEventListener('change', actualizarRango);
  document.getElementById('nom-fecha')?.addEventListener('change', actualizarRango);
  document.getElementById('btn-nom-generar')?.addEventListener('click', generar);
  document.getElementById('btn-nom-hay')?.addEventListener('click', () => {
    if (typeof cargarSeccion === 'function') cargarSeccion('hay_config_rubrica');
  });

  function aplicarSupPrefill() {
    const pf = supPrefill || {};
    if (pf.tab === 'suplencias') {
      document.querySelector('#nomina-tabs button[data-tab="suplencias"]')?.click();
    }
    if (!pf.sup_titular && !pf.sup_grupo) return;
    if (pf.sup_titular) document.getElementById('sup-titular').value = String(pf.sup_titular);
    if (pf.sup_grupo) document.getElementById('sup-grupo').value = String(pf.sup_grupo);
    if (pf.sup_desde) document.getElementById('sup-desde').value = pf.sup_desde;
    if (pf.sup_hasta) document.getElementById('sup-hasta').value = pf.sup_hasta;
    if (pf.sup_notas) document.getElementById('sup-notas').value = pf.sup_notas;
    document.getElementById('sup-motivo').value = 'enfermedad';
    document.getElementById('sup-regla').value = 'solo_suplente';
  }

  actualizarRango();
  cargarCatalogo().then(() => {
    if (supPrefill.tab === 'suplencias') {
      cargarSuplencias().then(aplicarSupPrefill);
    }
    if (idLiqInicial > 0) {
      verLiquidacion(idLiqInicial);
    }
  });
})();
