(function () {
  const cfg = window.HAY_REGISTROS_CONFIG || {};
  const api = cfg.api || 'php/asistencia_registros_api.php';
  const vistaDefault = cfg.vista_default || 'checados';
  const puedeRegistrar = !!cfg.puede_registrar;
  const puedeBaja = cfg.puede_baja !== false;
  let bajaContext = null;
  let estadosContacto = cfg.estados_contacto || {};
  let buscarTimer = null;

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function fmtHora(h) {
    if (!h) return '—';
    return String(h).substring(0, 5);
  }

  function origenLabel(o) {
    const m = { huella: 'Huella', movil: 'Móvil', recepcion: 'Recepción', manual: 'Manual' };
    return m[o] || o || '—';
  }

  function getFiltros() {
    return {
      fecha: document.getElementById('reg-fecha')?.value || '',
      q: document.getElementById('reg-buscar')?.value?.trim() || '',
      vista: document.getElementById('reg-vista')?.value || vistaDefault,
      tipo: document.getElementById('reg-tipo')?.value || cfg.tipo_default || 'ambos',
      hora_desde: document.getElementById('reg-hora-desde')?.value || '',
      hora_hasta: document.getElementById('reg-hora-hasta')?.value || '',
      id_grupo: document.getElementById('reg-grupo')?.value || '',
      todos_grupos: document.getElementById('reg-todos-grupos')?.checked ? '1' : '',
    };
  }

  function setRondinMsg(text, ok) {
    const el = document.getElementById('rondin-msg');
    if (!el) return;
    el.textContent = text || '';
    el.className = 'asist-checada-msg' + (text ? (ok ? ' ok' : ' err') : '');
  }

  async function apiPost(params) {
    const fd = new FormData();
    Object.keys(params || {}).forEach((k) => {
      if (params[k] != null && params[k] !== '') fd.append(k, String(params[k]));
    });
    const res = await fetch(api, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin',
    });
    return res.json();
  }

  function renderEstadoOptions(selected) {
    return Object.keys(estadosContacto).map((k) =>
      `<option value="${esc(k)}"${k === selected ? ' selected' : ''}>${esc(estadosContacto[k])}</option>`
    ).join('');
  }

  function estadoBadge(estado) {
    const label = estadosContacto[estado] || estado || 'Pendiente';
    const cls = estado && estado !== 'pendiente' ? ' asist-falt-estado--ok' : '';
    return `<span class="asist-falt-estado${cls}">${esc(label)}</span>`;
  }

  function renderSugerencias(alumnos, onPick) {
    const box = document.getElementById('rondin-sugerencias');
    if (!box) return;
    if (!alumnos || !alumnos.length) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    box.hidden = false;
    box.innerHTML = alumnos.map((a) =>
      `<button type="button" class="asist-rondin-sug" data-id="${a.id_alumno}">
        <strong>${esc(a.nombre)}</strong>
        <span>${esc(a.numero_control || 'Sin control')}</span>
      </button>`
    ).join('');
    box.querySelectorAll('.asist-rondin-sug').forEach((btn) => {
      btn.addEventListener('click', () => onPick(parseInt(btn.dataset.id, 10)));
    });
  }

  async function registrarRecepcion(payload) {
    const f = getFiltros();
    return apiPost({ accion: 'registrar_recepcion', fecha: f.fecha, ...payload });
  }

  async function marcarPresente(payload, btn) {
    if (btn) {
      btn.disabled = true;
      btn.textContent = '…';
    }
    try {
      const data = await registrarRecepcion(payload);
      if (data.status === 'multiples' && (data.alumnos || []).length) {
        setRondinMsg(data.message || 'Elija el alumno correcto', false);
        renderSugerencias(data.alumnos, (id) => marcarPresente({ id_alumno: id }, null));
        if (btn) { btn.disabled = false; btn.textContent = 'Marcar presente'; }
        return;
      }
      if (data.status === 'ok') {
        setRondinMsg(
          (data.duplicado ? 'Ya estaba registrado: ' : 'Presente: ') +
            (data.alumno?.nombre || '') +
            (data.alumno?.numero_control ? ' (' + data.alumno.numero_control + ')' : ''),
          true
        );
        if (payload.q) {
          const inp = document.getElementById('rondin-buscar');
          if (inp) inp.value = '';
        }
        renderSugerencias([]);
        await cargar();
      } else {
        setRondinMsg(data.message || 'No se pudo registrar', false);
        if (btn) { btn.disabled = false; btn.textContent = 'Presente'; }
      }
    } catch (_) {
      setRondinMsg('Error de conexión', false);
      if (btn) { btn.disabled = false; btn.textContent = 'Presente'; }
    }
  }

  async function buscarAlumnosAutocomplete(q) {
    if (q.length < 2) {
      renderSugerencias([]);
      return;
    }
    try {
      const url = new URL(api, window.location.href);
      url.searchParams.set('accion', 'buscar_alumno');
      url.searchParams.set('q', q);
      const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
      const data = await res.json();
      if (data.status === 'ok') {
        renderSugerencias(data.alumnos || [], (id) => marcarPresente({ id_alumno: id }, null));
      }
    } catch (_) { /* ignore */ }
  }

  function bindPresenteButtons() {
    document.querySelectorAll('.asist-falt-presente').forEach((btn) => {
      btn.addEventListener('click', () => {
        marcarPresente({ id_alumno: btn.dataset.id, id_grupo: btn.dataset.grupo || '' }, btn);
      });
    });
  }

  function bindNotaButtons() {
    document.querySelectorAll('.asist-falt-guardar-nota').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const row = btn.closest('.asist-falt-nota-row');
        if (!row) return;
        const msg = row.querySelector('.asist-falt-nota-msg');
        btn.disabled = true;
        try {
          const data = await apiPost({
            accion: 'guardar_nota_falta',
            fecha: getFiltros().fecha,
            id_alumno: row.dataset.id,
            id_grupo: row.dataset.grupo,
            estado_contacto: row.querySelector('.asist-falt-estado')?.value || 'pendiente',
            observacion: row.querySelector('.asist-falt-obs')?.value?.trim() || '',
          });
          if (msg) {
            msg.textContent = data.message || '';
            msg.className = 'asist-falt-nota-msg ' + (data.status === 'ok' ? 'ok' : 'err');
          }
          if (data.status === 'ok') {
            const badgeCell = document.querySelector(`.asist-falt-badge-cell[data-key="${row.dataset.id}-${row.dataset.grupo}"]`);
            if (badgeCell) badgeCell.innerHTML = estadoBadge(data.estado_contacto);
          }
        } catch (_) {
          if (msg) { msg.textContent = 'Error de conexión'; msg.className = 'asist-falt-nota-msg err'; }
        }
        btn.disabled = false;
      });
    });
  }

  function bindBajaButtons() {
    document.querySelectorAll('.asist-falt-baja').forEach((btn) => {
      btn.addEventListener('click', () => {
        const modal = document.getElementById('modal-rondin-baja');
        if (!modal) return;
        document.getElementById('baja-id-alumno').value = btn.dataset.id || '';
        document.getElementById('baja-id-grupo').value = btn.dataset.grupo || '';
        document.getElementById('baja-nombre-alumno').textContent = btn.dataset.nombre || '';
        document.getElementById('baja-motivo-rondin').value = '';
        document.getElementById('baja-tipo-rondin').value = 'temporal';
        modal.hidden = false;
      });
    });
  }

  async function confirmarBajaRondin() {
    const modal = document.getElementById('modal-rondin-baja');
    const motivo = document.getElementById('baja-motivo-rondin')?.value?.trim();
    if (!motivo) { alert('Indique el motivo'); return; }
    const tipo = document.getElementById('baja-tipo-rondin')?.value || 'temporal';
    if (tipo === 'definitiva' && !confirm('¿Baja definitiva? El alumno no regresará.')) return;
    const data = await apiPost({
      accion: 'registrar_baja',
      id_alumno: document.getElementById('baja-id-alumno')?.value,
      id_grupo: document.getElementById('baja-id-grupo')?.value,
      tipo_baja: tipo,
      motivo,
    });
    alert(data.message || '');
    if (modal) modal.hidden = true;
    if (data.status === 'ok') cargar();
  }

  function renderFaltantes(grupos) {
    const wrap = document.getElementById('reg-faltantes-wrap');
    if (!wrap) return;
    if (!grupos || !grupos.length) {
      wrap.innerHTML = '<p class="asist-checada-hint">No hay faltantes con estos filtros — todos checaron o no hay clase hoy.</p>';
      return;
    }
    wrap.innerHTML = grupos.map((g) => {
      const horario = g.hora_inicio ? ` · ${fmtHora(g.hora_inicio)}–${fmtHora(g.hora_fin)}` : '';
      const aula = g.aula ? ` · Aula ${esc(g.aula)}` : '';
      const accColSpan = (puedeRegistrar ? 1 : 0) + (puedeBaja ? 1 : 0);
      const accionTh = accColSpan ? `<th colspan="${accColSpan}">Acciones</th>` : '';
      const notaTh = cfg.puede_notas !== false ? '<th>Seguimiento</th>' : '';
      const filas = (g.faltantes || []).map((a) => {
        const key = `${a.id_alumno}-${g.id_grupo}`;
        let acciones = '';
        if (puedeRegistrar || puedeBaja) {
          acciones = '<td class="asist-falt-acciones">';
          if (puedeRegistrar) {
            acciones += `<button type="button" class="primary asist-falt-presente" data-id="${a.id_alumno}" data-grupo="${g.id_grupo}">Presente</button> `;
          }
          if (puedeBaja) {
            acciones += `<button type="button" class="asist-falt-baja" data-id="${a.id_alumno}" data-grupo="${g.id_grupo}" data-nombre="${esc(a.nombre)}">Baja</button>`;
          }
          acciones += '</td>';
        }
        const estado = a.estado_contacto || 'pendiente';
        const obs = a.observacion || '';
        const notaRow = cfg.puede_notas !== false ? `
          <tr class="asist-falt-nota-row" data-id="${a.id_alumno}" data-grupo="${g.id_grupo}">
            <td colspan="${2 + (cfg.puede_notas !== false ? 1 : 0) + (accColSpan ? 1 : 0)}">
              <div class="asist-falt-nota-form">
                <label>Estado del contacto</label>
                <select class="asist-falt-estado">${renderEstadoOptions(estado)}</select>
                <label>Observación (motivo, acuerdos, cuándo volver a llamar…)</label>
                <textarea class="asist-falt-obs" rows="2" placeholder="Ej. No contestó · Agendar asesoría viernes · Duelo familiar — no volver a marcar esta semana">${esc(obs)}</textarea>
                <div class="asist-falt-nota-actions">
                  <button type="button" class="secondary asist-falt-guardar-nota">Guardar seguimiento</button>
                  <span class="asist-falt-nota-msg"></span>
                </div>
              </div>
            </td>
          </tr>` : '';
        const colSpan = (puedeRegistrar ? 3 : 2) + (cfg.puede_notas !== false ? 1 : 0);
        return `<tr>
          <td>${esc(a.numero_control || '—')}</td>
          <td>${esc(a.nombre)}</td>
          ${cfg.puede_notas !== false ? `<td class="asist-falt-badge-cell" data-key="${key}">${estadoBadge(estado)}</td>` : ''}
          ${acciones}
        </tr>${notaRow}`;
      }).join('');
      const colSpanHead = 2 + (cfg.puede_notas !== false ? 1 : 0) + (accColSpan ? 1 : 0);
      return `
        <details class="asist-falt-grupo" open>
          <summary>
            <strong>${esc(g.clave)}</strong>${aula}${horario}
            <span class="asist-falt-badge">${g.total_faltantes} sin checar</span>
            <span class="asist-checada-hint"> / ${g.total_inscritos} inscritos</span>
          </summary>
          ${g.profesor_nombre ? `<p class="asist-checada-meta">${esc(g.profesor_nombre)} · ${esc(g.especialidad_nombre || '')}</p>` : ''}
          <table class="asist-punt-tabla asist-falt-tabla">
            <thead><tr><th>Control</th><th>Nombre</th>${notaTh}${accionTh}</tr></thead>
            <tbody>${filas || `<tr><td colspan="${colSpanHead}">—</td></tr>`}</tbody>
          </table>
        </details>`;
    }).join('');
    bindPresenteButtons();
    bindBajaButtons();
    bindNotaButtons();
  }

  function bindDeleteButtons() {
    document.querySelectorAll('.asist-reg-del').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('¿Eliminar este registro de asistencia?')) return;
        const data = await apiPost({ accion: 'eliminar', tipo: btn.dataset.tipo, id: btn.dataset.id });
        alert(data.message || '');
        if (data.status === 'ok') cargar();
      });
    });
  }

  async function cargar() {
    const f = getFiltros();
    const url = new URL(api, window.location.href);
    url.searchParams.set('accion', 'listar');
    Object.keys(f).forEach((k) => { if (f[k]) url.searchParams.set(k, f[k]); });

    const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
    const data = await res.json();
    if (data.status !== 'ok') return;

    if (data.estados_contacto) estadosContacto = data.estados_contacto;

    const vista = f.vista;
    const wrapFalt = document.getElementById('reg-faltantes-wrap');
    const wrapChec = document.getElementById('reg-checados-wrap');

    if (vista === 'faltantes') {
      if (wrapChec) wrapChec.hidden = true;
      if (wrapFalt) wrapFalt.hidden = false;
      renderFaltantes(data.faltantes_grupos || []);
      const totalFalt = (data.faltantes_grupos || []).reduce((n, g) => n + (g.total_faltantes || 0), 0);
      document.getElementById('reg-total').textContent =
        totalFalt + ' alumno(s) sin checar en ' + (data.faltantes_grupos || []).length + ' grupo(s)';
      return;
    }

    if (wrapFalt) {
      wrapFalt.hidden = vista !== 'todos';
      if (vista === 'todos') renderFaltantes(data.faltantes_grupos || []);
      else wrapFalt.innerHTML = '';
    }
    if (wrapChec) wrapChec.hidden = false;

    const colA = cfg.puede_eliminar ? 6 : 5;
    const colP = cfg.puede_eliminar ? 6 : 5;
    const tbA = document.querySelector('#tabla-reg-alumnos tbody');
    const tbP = document.querySelector('#tabla-reg-personal tbody');
    const titA = document.getElementById('titulo-alumnos');
    const titP = document.getElementById('titulo-personal');

    const showAl = f.tipo === 'ambos' || f.tipo === 'alumno';
    const showPer = f.tipo === 'ambos' || f.tipo === 'personal';

    if (titA) titA.hidden = !showAl;
    if (titA?.nextElementSibling) titA.nextElementSibling.hidden = !showAl;
    if (titP) titP.hidden = !showPer;
    if (titP?.nextElementSibling) titP.nextElementSibling.hidden = !showPer;

    if (tbA && showAl) {
      tbA.innerHTML = (data.alumnos || []).map((r) => {
        let del = '';
        if (cfg.puede_eliminar) {
          del = `<td><button type="button" class="asist-reg-del" data-tipo="alumno" data-id="${r.id}" title="Eliminar"><i class="fas fa-trash"></i></button></td>`;
        }
        return `<tr>
          <td>${fmtHora(r.hora_llegada)}</td>
          <td>${esc(r.numero_control)}</td>
          <td>${esc(r.nombre)}</td>
          <td>${esc(r.grupo_clave)}</td>
          <td>${esc(origenLabel(r.origen))}</td>${del}
        </tr>`;
      }).join('') || `<tr><td colspan="${colA}">Sin registros de alumnos</td></tr>`;
    } else if (tbA) tbA.innerHTML = '';

    if (tbP && showPer) {
      tbP.innerHTML = (data.personal || []).map((r) => {
        let del = '';
        if (cfg.puede_eliminar) {
          del = `<td><button type="button" class="asist-reg-del" data-tipo="personal" data-id="${r.id}" title="Eliminar"><i class="fas fa-trash"></i></button></td>`;
        }
        return `<tr>
          <td>${fmtHora(r.hora_llegada)}</td>
          <td>${fmtHora(r.hora_salida)}</td>
          <td>${esc(r.nombre)}</td>
          <td>${esc(r.rol)}</td>
          <td>${esc(origenLabel(r.origen))}</td>${del}
        </tr>`;
      }).join('') || `<tr><td colspan="${colP}">Sin registros de personal</td></tr>`;
    } else if (tbP) tbP.innerHTML = '';

    bindDeleteButtons();

    document.getElementById('reg-total').textContent =
      'Checados: ' + (data.alumnos?.length || 0) + ' alumnos, ' + (data.personal?.length || 0) + ' personal';
  }

  document.getElementById('btn-reg-filtrar')?.addEventListener('click', cargar);
  document.getElementById('reg-buscar')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') cargar();
  });
  document.getElementById('reg-vista')?.addEventListener('change', cargar);

  const inpBuscar = document.getElementById('rondin-buscar');
  inpBuscar?.addEventListener('input', () => {
    clearTimeout(buscarTimer);
    const q = inpBuscar.value.trim();
    buscarTimer = setTimeout(() => buscarAlumnosAutocomplete(q), 280);
  });
  inpBuscar?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const q = inpBuscar.value.trim();
      if (!q) {
        setRondinMsg('Escriba número de control, nombre o apellido', false);
        return;
      }
      marcarPresente({ q });
    }
    if (e.key === 'Escape') renderSugerencias([]);
  });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.asist-rondin-quick')) renderSugerencias([]);
  });

  document.getElementById('btn-rondin-registrar')?.addEventListener('click', () => {
    const q = document.getElementById('rondin-buscar')?.value?.trim();
    if (!q) {
      setRondinMsg('Escriba número de control, nombre o apellido', false);
      return;
    }
    marcarPresente({ q });
  });

  document.getElementById('btn-baja-rondin-confirm')?.addEventListener('click', confirmarBajaRondin);
  document.getElementById('btn-baja-rondin-cancel')?.addEventListener('click', () => {
    const modal = document.getElementById('modal-rondin-baja');
    if (modal) modal.hidden = true;
  });

  cargar();
})();
