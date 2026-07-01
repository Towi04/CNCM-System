(function () {
  const cfg = window.HAY_GRUPO_FUSION_CONFIG || {};
  const api = cfg.api || 'php/grupo_fusion_api.php';
  const umbral = cfg.umbral || 5;
  let puedeGestionar = !!cfg.puede_gestionar;
  let kidsConfig = { disponible: false };

  let matrizData = { fases: [], grupos: [] };
  let modoActual = 'simple';
  let pickA = null;
  let pickB = null;
  let pickIngA = null;
  let pickIngB = null;
  let pickCompA = null;
  let pickCompB = null;
  let lastSim = null;
  let planesLista = [];
  let planSeleccionado = null;

  const ESTADO_LABEL = {
    borrador: 'Borrador',
    planificada: 'Planificada',
    activa: 'Activa',
    separada: 'Separada',
    completada: 'Completada',
    cancelada: 'Cancelada',
  };

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function toast(msg, isErr) {
    const el = document.getElementById('gf-toast');
    if (!el) {
      if (isErr) window.alert(msg);
      return;
    }
    el.textContent = msg;
    el.className = 'gf-toast' + (isErr ? ' gf-toast--err' : ' gf-toast--ok');
    el.hidden = false;
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.hidden = true; }, 5000);
  }

  function esDual() {
    return modoActual === 'kids_dual';
  }

  function paramsBase() {
    const p = {
      id_profesor: document.getElementById('gf-prof')?.value || '',
      q: document.getElementById('gf-q')?.value?.trim() || '',
      estado: document.getElementById('gf-estado')?.value || '',
      solo_recomendados: document.getElementById('gf-solo-rec')?.checked ? '1' : '',
      modo: modoActual,
    };
    if (!esDual()) {
      p.id_especialidad = document.getElementById('gf-esp')?.value || '';
    }
    return p;
  }

  function buildUrl(extra) {
    const url = new URL(api, window.location.href);
    const p = { ...paramsBase(), ...extra };
    Object.keys(p).forEach((k) => {
      if (p[k] !== '' && p[k] != null) url.searchParams.set(k, p[k]);
    });
    return url.toString();
  }

  async function postAccion(accion, body) {
    const fd = new FormData();
    fd.set('accion', accion);
    Object.keys(body || {}).forEach((k) => {
      if (body[k] != null && body[k] !== '') fd.set(k, body[k]);
    });
    const res = await fetch(api, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' },
    });
    return res.json();
  }

  function alumnosClass(n, rec) {
    if (rec) return 'gf-alumnos-bajo';
    return n <= umbral ? 'gf-alumnos-bajo' : 'gf-alumnos-ok';
  }

  function estadoBadge(estado) {
    return '<span class="gf-estado gf-estado--' + esc(estado) + '">' + esc(ESTADO_LABEL[estado] || estado) + '</span>';
  }

  function planBadgesHtml(planes) {
    if (!planes || !planes.length) return '';
    return planes.map((p) =>
      '<span class="gf-plan-badge gf-plan-badge--' + esc(p.estado) + '" title="' +
      esc('Plan #' + p.id_fusion_plan + ' · ' + (ESTADO_LABEL[p.estado] || p.estado)) + '">' +
      esc(p.estado === 'activa' ? 'Fusión activa' : (ESTADO_LABEL[p.estado] || p.estado)) +
      '</span>'
    ).join(' ');
  }

  function temarioBadge(temario) {
    if (!temario || !temario.tiene_compresion) return '';
    const n = (temario.pendientes || []).length;
    const title = n ? ('Temario comprimido · ' + n + ' pendiente(s) de retomar') : 'Temario comprimido este mes';
    return '<span class="gf-badge-temario" title="' + esc(title) + '">Temario</span>';
  }

  function sugerirGrupoResultante(sim) {
    if (!sim) return 0;
    const a = sim.grupo_a || {};
    const b = sim.grupo_b || {};
    if ((a.total_alumnos || 0) >= (b.total_alumnos || 0)) return a.id_grupo;
    return b.id_grupo;
  }

  function resetPicks() {
    pickA = pickB = null;
    pickIngA = pickIngB = pickCompA = pickCompB = null;
    lastSim = null;
    const resEl = document.getElementById('gf-sim-resultado');
    if (resEl) resEl.innerHTML = '';
  }

  function updatePickUI() {
    const ta = document.getElementById('gf-pick-a-text');
    const tb = document.getElementById('gf-pick-b-text');
    const btn = document.getElementById('btn-gf-simular');
    const hint = document.getElementById('gf-sim-hint');

    if (esDual()) {
      if (ta) {
        ta.textContent = pickIngA
          ? 'ING ' + pickIngA.clave + ' · ' + pickIngA.total_alumnos + ' al.'
          : '—';
      }
      if (tb) {
        tb.textContent = pickIngB
          ? 'ING ' + pickIngB.clave + ' · ' + pickIngB.total_alumnos + ' al.'
          : '—';
      }
      if (hint) {
        hint.innerHTML = 'Inglés: filas A/B arriba. Computación: filas A/B abajo. ' +
          (pickCompA && pickCompB
            ? 'COMP ' + esc(pickCompA.clave) + ' + ' + esc(pickCompB.clave)
            : 'Seleccione también dos grupos de cómputo.');
      }
      if (btn) {
        btn.disabled = !(pickIngA && pickIngB && pickCompA && pickCompB &&
          pickIngA.id_grupo !== pickIngB.id_grupo &&
          pickCompA.id_grupo !== pickCompB.id_grupo);
      }
    } else {
      if (ta) {
        ta.textContent = pickA
          ? pickA.clave + ' · ' + pickA.total_alumnos + ' al. · ' + pickA.fase_clave
          : '—';
      }
      if (tb) {
        tb.textContent = pickB
          ? pickB.clave + ' · ' + pickB.total_alumnos + ' al. · ' + pickB.fase_clave
          : '—';
      }
      if (hint) hint.textContent = 'Haga clic en dos filas de la planilla (o use los botones A / B) para elegir los grupos.';
      if (btn) btn.disabled = !(pickA && pickB && pickA.id_grupo !== pickB.id_grupo);
    }

    document.querySelectorAll('.gf-row').forEach((row) => {
      const id = parseInt(row.dataset.idGrupo, 10);
      const mat = row.dataset.matriz || 'ing';
      let selA = false;
      let selB = false;
      if (esDual()) {
        if (mat === 'ing') {
          selA = pickIngA && pickIngA.id_grupo === id;
          selB = pickIngB && pickIngB.id_grupo === id;
        } else {
          selA = pickCompA && pickCompA.id_grupo === id;
          selB = pickCompB && pickCompB.id_grupo === id;
        }
      } else {
        selA = pickA && pickA.id_grupo === id;
        selB = pickB && pickB.id_grupo === id;
      }
      row.classList.toggle('gf-row-sel-a', selA);
      row.classList.toggle('gf-row-sel-b', selB);
    });
  }

  function toggleRowPick(grupo, matriz) {
    if (!grupo) return;
    if (esDual()) {
      if (matriz === 'ing') {
        if (pickIngA && pickIngA.id_grupo === grupo.id_grupo) pickIngA = null;
        else if (pickIngB && pickIngB.id_grupo === grupo.id_grupo) pickIngB = null;
        else if (!pickIngA) pickIngA = grupo;
        else if (!pickIngB) pickIngB = grupo;
        else pickIngB = grupo;
      } else {
        if (pickCompA && pickCompA.id_grupo === grupo.id_grupo) pickCompA = null;
        else if (pickCompB && pickCompB.id_grupo === grupo.id_grupo) pickCompB = null;
        else if (!pickCompA) pickCompA = grupo;
        else if (!pickCompB) pickCompB = grupo;
        else pickCompB = grupo;
      }
    } else {
      if (pickA && pickA.id_grupo === grupo.id_grupo) pickA = null;
      else if (pickB && pickB.id_grupo === grupo.id_grupo) pickB = null;
      else if (!pickA) pickA = grupo;
      else if (!pickB) pickB = grupo;
      else pickB = grupo;
    }
    lastSim = null;
    document.getElementById('gf-sim-resultado').innerHTML = '';
    updatePickUI();
  }

  function renderResumen(data) {
    const el = document.getElementById('gf-resumen');
    if (!el) return;
    if (esDual()) {
      el.innerHTML =
        '<div class="gf-stat"><strong>' + (data.total_ing || 0) + '</strong> grupos ING-K</div>' +
        '<div class="gf-stat"><strong>' + (data.total_comp || 0) + '</strong> grupos COMP-K</div>' +
        '<div class="gf-stat gf-stat--warn"><strong>' + (data.recomendados_ing || 0) + '</strong> ING recomiendan fusión</div>' +
        '<div class="gf-stat gf-stat--warn"><strong>' + (data.recomendados_comp || 0) + '</strong> COMP recomiendan fusión</div>';
      return;
    }
    el.innerHTML =
      '<div class="gf-stat"><strong>' + (data.total || 0) + '</strong> grupo(s) en planilla</div>' +
      '<div class="gf-stat gf-stat--warn"><strong>' + (data.recomendados || 0) + '</strong> recomiendan fusión (≤' + umbral + ' alumnos)</div>' +
      '<div class="gf-stat"><strong>' + (data.fases || []).length + '</strong> fases en el plan</div>';
  }

  function renderTablaPlanilla(containerId, data, matrizKey) {
    const el = document.getElementById(containerId);
    if (!el) return;
    const fases = data.fases || [];
    const grupos = data.grupos || [];
    const mat = matrizKey || 'ing';

    if (!fases.length) {
      el.innerHTML = '<p style="padding:16px;color:#888;">Sin fases configuradas.</p>';
      return;
    }
    if (!grupos.length) {
      el.innerHTML = '<p style="padding:16px;color:#888;">No hay grupos con estos filtros.</p>';
      return;
    }

    let html = '';
    if (esDual()) {
      html += '<h4 class="gf-planilla-titulo">' + (mat === 'ing' ? 'Inglés kids' : 'Computación kids') + '</h4>';
    }
    html += '<table class="gf-table"><thead><tr>';
    html += '<th class="gf-col-fija" rowspan="2">Sel.</th>';
    html += '<th class="gf-col-fija gf-col-fija-2" rowspan="2">Grupo</th>';
    html += '<th class="gf-col-fija gf-col-fija-3" rowspan="2">Alumnos</th>';
    html += '<th class="gf-col-fija gf-col-fija-4" rowspan="2">Fase</th>';
    fases.forEach((f) => {
      html += '<th class="gf-th-fase' + (f.es_repaso ? ' repaso' : '') + '">' + esc(f.clave_fase || '') + '</th>';
    });
    html += '</tr></thead><tbody>';

    grupos.forEach((g) => {
      const rec = g.recomienda_fusion;
      const rowCls = 'gf-row' + (rec ? ' gf-row-rec' : '') + ((g.planes || []).length ? ' gf-row-plan' : '');
      html += '<tr class="' + rowCls + '" data-id-grupo="' + g.id_grupo + '" data-matriz="' + mat + '">';
      html += '<td class="gf-col-fija">●</td>';
      html += '<td class="gf-col-fija gf-col-fija-2"><strong>' + esc(g.clave) + '</strong>';
      if (rec) html += '<span class="gf-badge-rec">Fusión</span>';
      html += planBadgesHtml(g.planes);
      html += temarioBadge(g.temario);
      html += '</td>';
      html += '<td class="gf-col-fija gf-col-fija-3 ' + alumnosClass(g.total_alumnos, rec) + '">' + g.total_alumnos + '</td>';
      html += '<td class="gf-col-fija gf-col-fija-4">' + esc(g.fase_clave) + '</td>';
      (g.celdas || []).forEach((c) => {
        const mark = c.estado === 'actual' ? '●' : (c.estado === 'pasada' ? '✓' : '');
        html += '<td class="gf-celda-fase ' + c.estado + '">' + mark + '</td>';
      });
      html += '</tr>';
    });
    html += '</tbody></table>';
    el.innerHTML = html;

    el.querySelectorAll('.gf-row').forEach((row) => {
      const id = parseInt(row.dataset.idGrupo, 10);
      const m = row.dataset.matriz || mat;
      const g = grupos.find((x) => x.id_grupo === id);
      row.addEventListener('click', () => toggleRowPick(g, m));
    });
  }

  function renderPlanilla(data) {
    matrizData = data;
    if (data.puede_gestionar != null) puedeGestionar = !!data.puede_gestionar;

    if (esDual()) {
      renderResumen(data);
      renderTablaPlanilla('gf-planilla', data.ingles || {}, 'ing');
      const compEl = document.getElementById('gf-planilla-comp');
      if (compEl) compEl.hidden = false;
      renderTablaPlanilla('gf-planilla-comp', data.computacion || {}, 'comp');
      document.getElementById('gf-planilla-wrap')?.classList.add('gf-dual-layout');
      updatePickUI();
      return;
    }

    document.getElementById('gf-planilla-comp').hidden = true;
    document.getElementById('gf-planilla-wrap')?.classList.remove('gf-dual-layout');
    renderResumen(data);
    populateFaseDestino(data.fases || []);
    renderTablaPlanilla('gf-planilla', data, 'ing');
    updatePickUI();
  }

  function populateFaseDestino(fases) {
    const sel = document.getElementById('gf-fase-destino');
    if (!sel) return;
    const cur = sel.value;
    sel.innerHTML = '<option value="">Automática (siguiente fase)</option>';
    fases.forEach((f) => {
      const o = document.createElement('option');
      o.value = f.id_fase;
      o.textContent = (f.clave_fase || 'Fase ' + (f.indice + 1)) + ' — ' + f.nombre_fase + (f.es_repaso ? ' (repaso)' : '');
      sel.appendChild(o);
    });
    if (cur) sel.value = cur;
  }

  function renderTemarioBlock(label, tem) {
    if (!tem || (!tem.tiene_compresion && !(tem.pendientes || []).length)) return '';
    let html = '<div class="gf-temario-block"><strong>' + esc(label) + ' — plan de parciales:</strong><ul>';
    if (tem.plan_mes && tem.plan_mes.compresion) {
      html += '<li>Mes actual: registro ' + esc(tem.plan_mes.clave_registro) +
        ', temario ' + esc((tem.plan_mes.fases_temario || []).join(', ')) + '</li>';
    }
    (tem.pendientes || []).forEach((p) => {
      html += '<li>Pendiente retomar: ' + esc(p.temas_retomar || p.nota || p.clave_registro) + '</li>';
    });
    html += '</ul></div>';
    return html;
  }

  function renderPlanesLista(planes) {
    const el = document.getElementById('gf-planes-lista');
    if (!el) return;
    planesLista = planes || [];
    if (!planesLista.length) {
      el.innerHTML = '<p class="gf-planes-empty">No hay planes abiertos.</p>';
      return;
    }
    let html = '<div class="gf-planes-grid">';
    planesLista.forEach((p) => {
      const id = p.id_fusion_plan;
      const sel = planSeleccionado === id ? ' gf-plan-card--sel' : '';
      html += '<button type="button" class="gf-plan-card' + sel + '" data-id-plan="' + id + '">';
      html += '<span class="gf-plan-card-top">' + estadoBadge(p.estado) + ' <small>#' + id + '</small>';
      if (p.tipo === 'kids_dual') html += ' <span class="gf-badge-dual">Dual</span>';
      html += '</span>';
      html += '<strong>' + esc(p.clave_a) + ' + ' + esc(p.clave_b) + '</strong>';
      html += '<span class="gf-plan-card-sub">→ ' + esc(p.clave_resultante || '?') + ' · ' + esc(p.dest_clave || '') + '</span>';
      html += '</button>';
    });
    html += '</div>';
    el.innerHTML = html;
    el.querySelectorAll('.gf-plan-card').forEach((btn) => {
      btn.addEventListener('click', () => cargarPlanDetalle(parseInt(btn.dataset.idPlan, 10)));
    });
  }

  function renderPlanDetalle(plan) {
    const el = document.getElementById('gf-plan-detalle');
    if (!el || !plan) {
      if (el) el.hidden = true;
      return;
    }
    planSeleccionado = plan.id_fusion_plan;
    el.hidden = false;

    const pend = plan.pendientes_fase || plan.fases_pendientes || [];
    const alumnos = plan.alumnos_fusion || [];
    const vinc = plan.plan_vinculado || null;

    let html = '<div class="gf-plan-det-inner">';
    html += '<div class="gf-plan-det-head"><h4>Plan #' + plan.id_fusion_plan + ' ' + estadoBadge(plan.estado) + '</h4>';
    html += '<button type="button" class="secondary btn-sm" id="gf-plan-cerrar">Cerrar</button></div>';
    html += '<p><strong>' + esc(plan.clave_a) + '</strong> + <strong>' + esc(plan.clave_b) + '</strong> → <strong>' + esc(plan.clave_resultante) + '</strong></p>';

    if (vinc) {
      html += '<p class="gf-plan-vinc">Plan vinculado #' + vinc.id_fusion_plan + ': ' +
        esc(vinc.clave_a) + ' + ' + esc(vinc.clave_b) + ' → ' + esc(vinc.clave_resultante) +
        ' <button type="button" class="secondary btn-sm gf-btn-ver-vinc" data-id="' + vinc.id_fusion_plan + '">Ver</button></p>';
    }

    if (pend.length) {
      html += '<div class="gf-plan-pend"><strong>Fases pendientes:</strong><ul>';
      pend.forEach((f) => {
        html += '<li class="' + (f.estado === 'completada' ? 'done' : '') + '">' + esc(f.clave_fase || f.nombre_fase);
        if (puedeGestionar && plan.estado === 'activa' && f.estado !== 'completada' && f.id) {
          html += ' <button type="button" class="secondary btn-sm gf-btn-completar-pend" data-id="' + f.id + '">Hecha</button>';
        }
        html += '</li>';
      });
      html += '</ul></div>';
    }

    if (alumnos.length) {
      html += '<p><strong>Alumnos:</strong> ' + alumnos.length + ' (graduación por grupo origen)</p>';
    }

    html += '<p><button type="button" class="secondary btn-sm" onclick="cargarSeccion(\'grupo_plan\', \'id_grupo=' +
      (plan.id_grupo_resultante || plan.id_grupo_a) + '\')"><i class="fas fa-calendar-check"></i> Plan de parciales</button></p>';

    if (plan.notas) html += '<p class="gf-plan-notas"><em>' + esc(plan.notas) + '</em></p>';

    html += '<div class="gf-plan-acciones">';
    if (puedeGestionar) {
      if (plan.estado === 'borrador') {
        html += '<button type="button" class="primary" id="gf-btn-confirmar">Confirmar</button>';
        html += '<button type="button" class="danger" id="gf-btn-cancelar">Cancelar</button>';
      }
      if (plan.estado === 'planificada') {
        html += '<button type="button" class="primary" id="gf-btn-activar">Activar fusión</button>';
        html += '<button type="button" class="danger" id="gf-btn-cancelar">Cancelar</button>';
        if (vinc && vinc.estado === 'planificada') {
          html += '<p class="gf-readonly-hint">Active también el plan vinculado #' + vinc.id_fusion_plan + ' (otra materia).</p>';
        }
      }
      if (plan.estado === 'activa' && pend.length) {
        html += '<button type="button" class="warning" id="gf-btn-separar">Separar atrasados</button>';
      }
    }
    html += '</div></div>';
    el.innerHTML = html;

    document.getElementById('gf-plan-cerrar')?.addEventListener('click', () => {
      planSeleccionado = null;
      el.hidden = true;
      renderPlanesLista(planesLista);
    });
    document.getElementById('gf-btn-confirmar')?.addEventListener('click', () => accionPlan('confirmar', plan.id_fusion_plan));
    document.getElementById('gf-btn-activar')?.addEventListener('click', () => {
      if (!window.confirm('¿Activar la fusión? Se moverán los alumnos al grupo resultante.')) return;
      accionPlan('activar', plan.id_fusion_plan);
    });
    document.getElementById('gf-btn-separar')?.addEventListener('click', () => {
      if (!window.confirm('¿Separar alumnos al grupo atrasado?')) return;
      accionPlan('separar', plan.id_fusion_plan);
    });
    document.getElementById('gf-btn-cancelar')?.addEventListener('click', () => {
      if (!window.confirm('¿Cancelar plan?')) return;
      accionPlan('cancelar', plan.id_fusion_plan);
    });
    el.querySelectorAll('.gf-btn-ver-vinc').forEach((b) => {
      b.addEventListener('click', () => cargarPlanDetalle(parseInt(b.dataset.id, 10)));
    });
    el.querySelectorAll('.gf-btn-completar-pend').forEach((btn) => {
      btn.addEventListener('click', async () => {
        try {
          const data = await postAccion('completar_pendiente', { id_pendiente: btn.dataset.id });
          if (data.status !== 'ok') throw new Error(data.message);
          toast(data.message);
          cargarPlanDetalle(plan.id_fusion_plan);
        } catch (err) { toast(err.message, true); }
      });
    });
  }

  async function accionPlan(accion, idPlan) {
    try {
      const data = await postAccion(accion, { id_fusion_plan: idPlan });
      if (data.status !== 'ok') throw new Error(data.message);
      toast(data.message);
      if (accion === 'cancelar') {
        planSeleccionado = null;
        document.getElementById('gf-plan-detalle').hidden = true;
      } else if (data.plan) {
        renderPlanDetalle(data.plan);
      }
      cargarPlanes();
      cargar();
    } catch (err) { toast(err.message, true); }
  }

  async function cargarPlanes() {
    const el = document.getElementById('gf-planes-lista');
    if (!el) return;
    try {
      const extra = esDual() ? {} : { id_especialidad: document.getElementById('gf-esp')?.value || '' };
      const res = await fetch(buildUrl({ accion: 'listar_planes', ...extra }), {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' },
      });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      renderPlanesLista(data.planes || []);
    } catch (err) {
      el.innerHTML = '<p class="gf-planes-err">' + esc(err.message) + '</p>';
    }
  }

  async function cargarPlanDetalle(idPlan) {
    try {
      const url = new URL(api, window.location.href);
      url.searchParams.set('accion', 'obtener');
      url.searchParams.set('id_fusion_plan', idPlan);
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      renderPlanDetalle(data.plan);
      renderPlanesLista(planesLista);
    } catch (err) { toast(err.message, true); }
  }

  async function cargar() {
    const planilla = document.getElementById('gf-planilla');
    if (!esDual() && !document.getElementById('gf-esp')?.value) {
      if (planilla) planilla.innerHTML = '<p style="padding:20px;color:#888;">No hay especialidades con grupos.</p>';
      return;
    }
    if (esDual() && !kidsConfig.disponible) {
      if (planilla) planilla.innerHTML = '<p style="padding:20px;color:#c62828;">Infantil dual no disponible (faltan ING-K / COMP-K).</p>';
      return;
    }
    if (planilla) planilla.innerHTML = '<p style="padding:20px;color:#888;"><i class="fas fa-spinner fa-spin"></i> Cargando…</p>';

    try {
      const res = await fetch(buildUrl({ accion: 'matriz' }), { credentials: 'same-origin' });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      renderPlanilla(data);
      cargarPlanes();
    } catch (err) {
      if (planilla) planilla.innerHTML = '<p style="padding:20px;color:#c62828;">' + esc(err.message) + '</p>';
    }
  }

  function renderSimulacion(sim) {
    const el = document.getElementById('gf-sim-resultado');
    if (!el) return;
    lastSim = sim;

    if (sim.modo === 'kids_dual') {
      renderSimulacionDual(sim);
      return;
    }

    const fd = sim.fase_destino || {};
    const ga = sim.grupo_a || {};
    const gb = sim.grupo_b || {};
    let html = '<div class="gf-sim-ok">';
    html += '<p><strong>A:</strong> ' + esc(ga.clave) + ' (' + ga.total_alumnos + ' al.) · <strong>B:</strong> ' + esc(gb.clave) + ' (' + gb.total_alumnos + ' al.)</p>';
    html += '<p><strong>Total:</strong> ' + sim.alumnos_combinados + ' alumnos · <strong>Fase destino:</strong> ' + esc(fd.clave_fase) + '</p>';
    html += renderTemarioBlock('Grupo A', sim.temario_a);
    html += renderTemarioBlock('Grupo B', sim.temario_b);
    if ((sim.fases_saltadas || []).length) {
      html += '<div class="gf-sim-fases-salt"><strong>Fases a retomar (' + esc(sim.grupo_atrasado_clave) + '):</strong><ul>';
      sim.fases_saltadas.forEach((f) => { html += '<li>' + esc(f.clave_fase) + '</li>'; });
      html += '</ul></div>';
    }
    if (sim.notas) { html += '<ul>'; sim.notas.forEach((n) => { html += '<li>' + esc(n) + '</li>'; }); html += '</ul>'; }
    if (puedeGestionar) html += renderGuardarSimple(sim);
    html += '</div>';
    el.innerHTML = html;
    document.getElementById('btn-gf-guardar')?.addEventListener('click', guardarPlan);
  }

  function renderSimulacionDual(sim) {
    const el = document.getElementById('gf-sim-resultado');
    const ing = sim.ingles || {};
    const comp = sim.computacion || {};
    let html = '<div class="gf-sim-ok gf-sim-dual">';
    html += '<p><strong>Fusión dual infantil</strong> · ' + (sim.alumnos_dual || 0) + ' alumno(s) cursan ambas materias</p>';
    html += '<div class="gf-dual-cols">';
    html += '<div><h4>Inglés</h4><p>' + esc(ing.grupo_a?.clave) + ' + ' + esc(ing.grupo_b?.clave) + ' → ' + esc(ing.fase_destino?.clave_fase) + '</p></div>';
    html += '<div><h4>Computación</h4><p>' + esc(comp.grupo_a?.clave) + ' + ' + esc(comp.grupo_b?.clave) + ' → ' + esc(comp.fase_destino?.clave_fase) + '</p></div>';
    html += '</div>';
    if (sim.notas) { html += '<ul>'; sim.notas.forEach((n) => { html += '<li>' + esc(n) + '</li>'; }); html += '</ul>'; }
    if (puedeGestionar) {
      html += '<div class="gf-sim-guardar">';
      html += '<p>Se crearán <strong>dos planes vinculados</strong> (confirmar y activar cada materia).</p>';
      html += '<input type="text" id="gf-notas-plan" placeholder="Notas del plan dual" style="width:100%;margin-bottom:8px;">';
      html += '<button type="button" class="primary" id="btn-gf-guardar-dual"><i class="fas fa-save"></i> Guardar planes dual</button>';
      html += '</div>';
    }
    html += '</div>';
    el.innerHTML = html;
    document.getElementById('btn-gf-guardar-dual')?.addEventListener('click', guardarPlanDual);
  }

  function renderGuardarSimple(sim) {
    const ga = sim.grupo_a || {};
    const gb = sim.grupo_b || {};
    const idRes = sugerirGrupoResultante(sim);
    return '<div class="gf-sim-guardar">' +
      '<select id="gf-grupo-resultante">' +
      '<option value="' + ga.id_grupo + '"' + (idRes === ga.id_grupo ? ' selected' : '') + '>' + esc(ga.clave) + '</option>' +
      '<option value="' + gb.id_grupo + '"' + (idRes === gb.id_grupo ? ' selected' : '') + '>' + esc(gb.clave) + '</option>' +
      '</select>' +
      '<input type="date" id="gf-fecha-prevista">' +
      '<input type="text" id="gf-notas-plan" placeholder="Notas">' +
      '<button type="button" class="primary" id="btn-gf-guardar"><i class="fas fa-save"></i> Guardar borrador</button></div>';
  }

  async function guardarPlan() {
    if (!pickA || !pickB || !lastSim) return;
    try {
      const data = await postAccion('guardar', {
        modo: 'simple',
        id_grupo_a: pickA.id_grupo,
        id_grupo_b: pickB.id_grupo,
        id_fase_destino: document.getElementById('gf-fase-destino')?.value || '',
        id_grupo_resultante: document.getElementById('gf-grupo-resultante')?.value || sugerirGrupoResultante(lastSim),
        fecha_prevista: document.getElementById('gf-fecha-prevista')?.value || '',
        notas: document.getElementById('gf-notas-plan')?.value?.trim() || '',
      });
      if (data.status !== 'ok') throw new Error(data.message);
      toast(data.message);
      if (data.id_fusion_plan) {
        planSeleccionado = data.id_fusion_plan;
        if (data.plan) renderPlanDetalle(data.plan);
      }
      cargarPlanes();
      cargar();
    } catch (err) { toast(err.message, true); }
  }

  async function guardarPlanDual() {
    if (!pickIngA || !pickIngB || !pickCompA || !pickCompB || !lastSim) return;
    try {
      const data = await postAccion('guardar', {
        modo: 'kids_dual',
        id_grupo_ing_a: pickIngA.id_grupo,
        id_grupo_ing_b: pickIngB.id_grupo,
        id_grupo_comp_a: pickCompA.id_grupo,
        id_grupo_comp_b: pickCompB.id_grupo,
        id_grupo_resultante_ing: sugerirGrupoResultante(lastSim.ingles),
        id_grupo_resultante_comp: sugerirGrupoResultante(lastSim.computacion),
        notas: document.getElementById('gf-notas-plan')?.value?.trim() || '',
      });
      if (data.status !== 'ok') throw new Error(data.message);
      toast(data.message);
      if (data.id_fusion_plan) {
        planSeleccionado = data.id_fusion_plan;
        if (data.plan) renderPlanDetalle(data.plan);
      }
      cargarPlanes();
      cargar();
    } catch (err) { toast(err.message, true); }
  }

  async function simular() {
    const el = document.getElementById('gf-sim-resultado');
    if (el) el.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Simulando…</p>';

    const url = new URL(api, window.location.href);
    url.searchParams.set('accion', 'simular');

    if (esDual()) {
      if (!pickIngA || !pickIngB || !pickCompA || !pickCompB) return;
      url.searchParams.set('modo', 'kids_dual');
      url.searchParams.set('id_grupo_ing_a', pickIngA.id_grupo);
      url.searchParams.set('id_grupo_ing_b', pickIngB.id_grupo);
      url.searchParams.set('id_grupo_comp_a', pickCompA.id_grupo);
      url.searchParams.set('id_grupo_comp_b', pickCompB.id_grupo);
    } else {
      if (!pickA || !pickB) return;
      url.searchParams.set('id_grupo_a', pickA.id_grupo);
      url.searchParams.set('id_grupo_b', pickB.id_grupo);
      const dest = document.getElementById('gf-fase-destino')?.value;
      if (dest) url.searchParams.set('id_fase_destino', dest);
    }

    try {
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const data = await res.json();
      if (data.status !== 'ok') throw new Error(data.message);
      renderSimulacion(data.simulacion);
    } catch (err) {
      lastSim = null;
      if (el) el.innerHTML = '<div class="gf-sim-err">' + esc(err.message) + '</div>';
    }
  }

  function aplicarModoUI() {
    const espWrap = document.getElementById('gf-esp-wrap');
    const destWrap = document.querySelector('.gf-pick-dest');
    if (espWrap) espWrap.hidden = esDual();
    if (destWrap) destWrap.hidden = esDual();
    resetPicks();
    cargar();
  }

  async function initKidsConfig() {
    try {
      const url = new URL(api, window.location.href);
      url.searchParams.set('accion', 'especialidades');
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const data = await res.json();
      if (data.kids) kidsConfig = data.kids;
      const optDual = document.querySelector('#gf-modo option[value="kids_dual"]');
      if (optDual && !kidsConfig.disponible) {
        optDual.disabled = true;
        optDual.textContent += ' (no configurado)';
      }
    } catch (e) { /* ignore */ }
  }

  document.getElementById('btn-gf-cargar')?.addEventListener('click', cargar);
  document.getElementById('btn-gf-simular')?.addEventListener('click', simular);
  document.getElementById('btn-gf-planes-refresh')?.addEventListener('click', cargarPlanes);
  document.getElementById('gf-modo')?.addEventListener('change', (ev) => {
    modoActual = ev.target.value || 'simple';
    aplicarModoUI();
  });
  document.getElementById('gf-clear-a')?.addEventListener('click', () => { if (esDual()) pickIngA = null; else pickA = null; updatePickUI(); });
  document.getElementById('gf-clear-b')?.addEventListener('click', () => { if (esDual()) pickIngB = null; else pickB = null; updatePickUI(); });
  ['gf-esp', 'gf-estado', 'gf-prof', 'gf-solo-rec'].forEach((id) => {
    document.getElementById(id)?.addEventListener('change', cargar);
  });

  if (!document.getElementById('gf-toast')) {
    const t = document.createElement('div');
    t.id = 'gf-toast';
    t.hidden = true;
    t.className = 'gf-toast';
    document.querySelector('.gf-wrap')?.appendChild(t);
  }

  initKidsConfig().then(cargar);
})();
