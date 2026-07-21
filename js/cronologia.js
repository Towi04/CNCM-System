(function () {
  const cfg = window.HAY_CRONOLOGIA_CONFIG || {};
  const api = cfg.api || 'php/cronologia_api.php';
  let vistaActual = 'matriz';

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function paramsBase() {
    return {
      id_especialidad: document.getElementById('cron-esp')?.value || '',
      id_profesor: document.getElementById('cron-prof')?.value || '',
      q: document.getElementById('cron-q')?.value?.trim() || '',
      estado: document.getElementById('cron-estado')?.value || '',
      semanas_atras: document.getElementById('cron-atras')?.value || '6',
      semanas_adelante: document.getElementById('cron-adelante')?.value || '14',
    };
  }

  function buildUrl(extra) {
    const url = new URL(api, window.location.href);
    const p = { ...paramsBase(), ...extra };
    Object.keys(p).forEach((k) => { if (p[k] !== '' && p[k] != null) url.searchParams.set(k, p[k]); });
    return url.toString();
  }

  function buildPdfUrl() {
    const url = new URL(cfg.pdf || 'php/cronologia_pdf.php', window.location.href);
    const p = paramsBase();
    Object.keys(p).forEach((k) => { if (p[k] !== '' && p[k] != null) url.searchParams.set(k, p[k]); });
    return url.toString();
  }

  function alumnosClass(n) {
    return n <= 5 ? 'cron-alumnos-bajo' : 'cron-alumnos-ok';
  }

  function renderPlanilla(data) {
    const el = document.getElementById('cron-planilla');
    if (!el) return;
    const meses = data.meses || [];
    const semanas = data.semanas || [];
    const grupos = data.grupos || [];
    const semActual = data.semana_actual || '';

    if (!grupos.length) {
      el.innerHTML = '<p style="padding:20px;color:#888;">No hay grupos con estos filtros.</p>';
      return;
    }

    let html = '<table class="cron-table"><thead>';
    html += '<tr><th class="cron-col-fija" rowspan="2">Horario</th>';
    html += '<th class="cron-col-fija cron-col-fija-2" rowspan="2">Grupo</th>';
    html += '<th class="cron-col-fija cron-col-fija-3" rowspan="2">Alumnos</th>';
    html += '<th class="cron-col-fija cron-col-fija-4" rowspan="2">Aula</th>';
    html += '<th class="cron-col-fija cron-col-fija-5" rowspan="2">Día</th>';
    html += '<th class="cron-col-fija cron-col-fija-6" rowspan="2">Profesor</th>';
    meses.forEach((m) => {
      html += '<th class="cron-th-mes" colspan="' + m.semanas.length + '">' + esc(m.month_label) + '</th>';
    });
    html += '</tr><tr>';
    semanas.forEach((s) => {
      const cls = s.key === semActual ? ' cron-th-semana actual' : ' cron-th-semana';
      html += '<th class="' + cls.trim() + '">' + s.iso_week + '</th>';
    });
    html += '</tr></thead><tbody>';

    let espActual = '';
    let horarioActual = '';
    let horarioRowspan = 0;
    let horarioRows = [];

    function flushHorario() {
      horarioRows.forEach((row, i) => {
        if (i === 0 && horarioRowspan > 1) {
          row.html = row.html.replace('<!--HORARIO-->', '<td class="cron-horario-cell cron-col-fija" rowspan="' + horarioRowspan + '">' + esc(horarioActual) + '</td>');
        } else if (horarioRowspan <= 1) {
          row.html = row.html.replace('<!--HORARIO-->', '<td class="cron-horario-cell cron-col-fija">' + esc(horarioActual) + '</td>');
        } else {
          row.html = row.html.replace('<!--HORARIO-->', '');
        }
        html += row.html;
      });
      horarioRows = [];
      horarioRowspan = 0;
    }

    grupos.forEach((g) => {
      if (g.esp_nombre !== espActual) {
        flushHorario();
        horarioActual = '';
        espActual = g.esp_nombre;
        html += '<tr class="cron-esp-bar"><td class="cron-esp-bar-fija" colspan="6">' + esc(g.esp_nombre || g.esp_clave || 'Sin especialidad') + '</td>';
        for (let si = 0; si < semanas.length; si++) {
          html += '<td class="cron-esp-bar-fill">&nbsp;</td>';
        }
        html += '</tr>';
      }

      if (g.horario !== horarioActual) {
        flushHorario();
        horarioActual = g.horario;
      }

      const finCls = g.estado_grupo === 'fin_curso' ? ' cron-fin-curso' : '';
      let row = '<tr class="' + finCls.trim() + '">';
      row += '<!--HORARIO-->';
      row += '<td class="cron-col-fija cron-col-fija-2"><strong>' + esc(g.clave) + '</strong></td>';
      row += '<td class="cron-col-fija cron-col-fija-3 ' + alumnosClass(g.total_alumnos) + '">' + g.total_alumnos + '</td>';
      row += '<td class="cron-col-fija cron-col-fija-4">' + esc(g.aula) + '</td>';
      row += '<td class="cron-col-fija cron-col-fija-5">' + esc(g.dia) + '</td>';
      row += '<td class="cron-col-fija cron-col-fija-6" title="' + esc(g.profesor_nombre) + '">' + esc(g.profesor_nombre) + '</td>';

      semanas.forEach((s) => {
        const c = (g.celdas && g.celdas[s.key]) || {};
        let cls = 'cron-celda-fase';
        if (c.es_actual) cls += ' actual';
        else if (c.mostrar && c.semana_parcial === 1) cls += ' inicio-parcial';
        row += '<td class="' + cls + '" title="' + esc(c.fase_clave || '') + '">';
        row += c.mostrar ? esc(c.texto) : '';
        row += '</td>';
      });
      row += '</tr>';

      horarioRows.push({ html: row });
      horarioRowspan++;
    });
    flushHorario();

    html += '</tbody></table>';
    el.innerHTML = html;
  }

  function estadoBadge(est) {
    const map = {
      al_dia: ['Al día', 'ok'],
      atrasado: ['Atrasado', 'warn'],
      adelantado: ['Adelantado', 'info'],
    };
    const m = map[est] || [est, ''];
    return '<span class="cron-badge cron-badge--' + m[1] + '">' + esc(m[0]) + '</span>';
  }

  function renderTarjetas(grupos) {
    const wrap = document.getElementById('cron-tarjetas');
    if (!wrap) return;
    if (!grupos.length) {
      wrap.innerHTML = '<p style="color:#888;">No hay grupos con estos filtros.</p>';
      return;
    }
    wrap.innerHTML = grupos.map((g) => {
      const proy = (g.proyeccion || []).map((p) =>
        '<li>+' + p.semanas_adelante + ' sem (' + esc(p.fecha_ref) + '): <strong>' + esc(p.fase_clave) + '</strong></li>'
      ).join('');
      return '<details class="cron-grupo-card" open>' +
        '<summary><strong>' + esc(g.clave) + '</strong> · ' + esc(g.esp_nombre || '') + ' · ' + esc(g.profesor_nombre) +
        estadoBadge(g.estado) +
        '<span class="cron-meta">S' + g.semanas_lectivas + ' · sem ' + g.semana_parcial + '/4 · ' + g.total_alumnos + ' alumnos</span></summary>' +
        '<div class="cron-grupo-body">' +
        '<p>Inicio: ' + esc(g.fecha_inicio) + ' · Aula: ' + esc(g.aula || '—') + '</p>' +
        '<p><strong>Fase según calendario:</strong> ' + esc(g.fase_esperada_clave) + '</p>' +
        '<p><strong>Fase registrada:</strong> ' + esc(g.fase_actual_clave) + '</p>' +
        (proy ? '<ul class="cron-proy">' + proy + '</ul>' : '') +
        '</div></details>';
    }).join('');
  }

  async function cargar() {
    const totalEl = document.getElementById('cron-total');
    const planilla = document.getElementById('cron-planilla');
    const tarjetas = document.getElementById('cron-tarjetas');

    if (planilla && vistaActual === 'matriz') {
      planilla.innerHTML = '<p style="padding:20px;color:#888;"><i class="fas fa-spinner fa-spin"></i> Calculando cronología…</p>';
    }

    try {
      if (vistaActual === 'matriz') {
        const res = await fetch(buildUrl({ vista: 'matriz' }), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
        const data = await res.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Error');
        if (totalEl) totalEl.textContent = (data.total || 0) + ' grupo(s) · semana actual resaltada en amarillo';
        renderPlanilla(data);
      } else {
        const res = await fetch(buildUrl({ vista: 'tarjetas', semanas_proyeccion: '8' }), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
        const data = await res.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Error');
        const grupos = data.grupos || [];
        if (totalEl) totalEl.textContent = grupos.length + ' grupo(s)';
        renderTarjetas(grupos);
      }
    } catch (err) {
      if (planilla && vistaActual === 'matriz') planilla.innerHTML = '<p style="padding:20px;color:#c62828;">' + esc(err.message) + '</p>';
      if (tarjetas && vistaActual === 'tarjetas') tarjetas.innerHTML = '<p style="color:#c62828;">' + esc(err.message) + '</p>';
    }
  }

  function setVista(v) {
    vistaActual = v;
    document.querySelectorAll('.cron-vista-tab').forEach((t) => {
      t.classList.toggle('active', t.dataset.vista === v);
    });
    document.getElementById('cron-planilla-wrap').style.display = v === 'matriz' ? 'block' : 'none';
    document.getElementById('cron-tarjetas').style.display = v === 'tarjetas' ? 'block' : 'none';
    cargar();
  }

  document.getElementById('btn-cron-generar')?.addEventListener('click', cargar);
  document.getElementById('btn-cron-pdf')?.addEventListener('click', () => {
    if (vistaActual !== 'matriz') {
      alert('La exportación PDF está disponible en la vista Planilla.');
      return;
    }
    const url = buildPdfUrl();
    window.open(url, '_blank', 'noopener');
  });
  document.querySelectorAll('.cron-vista-tab').forEach((tab) => {
    tab.addEventListener('click', () => setVista(tab.dataset.vista || 'matriz'));
  });
  ['cron-esp', 'cron-estado', 'cron-prof'].forEach((id) => {
    document.getElementById(id)?.addEventListener('change', cargar);
  });

  cargar();
})();
