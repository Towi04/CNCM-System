(function () {
  const cfg = window.HAY_REP_SEM_CONFIG || {};
  const api = cfg.api || 'php/reporte_semanal_api.php';

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function getParams() {
    const modo = document.getElementById('rep-sem-modo')?.value || 'semana';
    const anio = parseInt(document.getElementById('rep-sem-anio')?.value, 10) || cfg.actual?.anio || new Date().getFullYear();
    let desde = parseInt(document.getElementById('rep-sem-semana')?.value, 10) || cfg.actual?.semana || 1;
    let hasta = desde;
    if (modo === 'rango') {
      desde = parseInt(document.getElementById('rep-sem-desde')?.value, 10) || desde;
      hasta = parseInt(document.getElementById('rep-sem-hasta')?.value, 10) || desde;
    }
    return { modo, anio, semana_desde: desde, semana_hasta: hasta };
  }

  function toggleFields() {
    const modo = document.getElementById('rep-sem-modo')?.value || 'semana';
    document.querySelectorAll('.rep-sem-field--semana').forEach((el) => {
      el.hidden = modo !== 'semana';
    });
    document.querySelectorAll('.rep-sem-field--rango').forEach((el) => {
      el.hidden = modo !== 'rango';
    });
  }

  function renderTotales(t) {
    if (!t) return '';
    const cards = [
      ['A', t.A_inicio, 'Al inicio'],
      ['I', t.I, 'Inicios'],
      ['R', t.R, 'Reingresos'],
      ['+C', t.C_POS, 'Cambio +'],
      ['B', t.B, 'Bajas'],
      ['−C', t.C_NEG, 'Cambio −'],
      ['FC', t.FC, 'Fin curso'],
      ['T', t.T_fin, 'Al cierre'],
    ];
    let html = '<div class="rep-sem-totales">';
    cards.forEach(([lbl, val]) => {
      html += `<div class="rep-sem-total-card"><div class="rep-sem-total-card__val">${esc(val ?? 0)}</div><div class="rep-sem-total-card__lbl">${esc(lbl)}</div></div>`;
    });
    const des = t.desercion ?? 0;
    const desClass = des >= 0 ? 'rep-sem-total-card--destacado' : 'rep-sem-total-card--destacado';
    html += `<div class="rep-sem-total-card ${desClass}"><div class="rep-sem-total-card__val">${esc(t.desercion_label ?? des)}</div><div class="rep-sem-total-card__lbl">Deserción / crecimiento</div></div>`;
    html += '</div>';
    return html;
  }

  function renderGruposTabla(grupos) {
    if (!grupos || !grupos.length) {
      return '<p class="rep-sem-esp-meta">Sin grupos en esta especialidad.</p>';
    }
    const filas = grupos.map((g) =>
      `<tr>
        <td class="rep-sem-clave">${esc(g.clave)}</td>
        <td class="rep-sem-prof">${esc(g.profesor)}</td>
        <td>${esc(g.dias)}</td>
        <td class="rep-sem-hor">${esc(g.horario)}</td>
        <td>${g.A ?? 0}</td>
        <td>${g.I ?? 0}</td>
        <td>${g.R ?? 0}</td>
        <td>${g.C_POS ?? 0}</td>
        <td>${g.B ?? 0}</td>
        <td>${g.C_NEG ?? 0}</td>
        <td>${g.FC ?? 0}</td>
        <td><strong>${g.T ?? 0}</strong></td>
      </tr>`
    ).join('');
    return `<table class="rep-sem-tabla">
      <thead><tr>
        <th>Clave</th><th>Profesor</th><th>Día</th><th>Horario</th>
        <th>A</th><th>I</th><th>R</th><th>+C</th><th>B</th><th>−C</th><th>FC</th><th>T</th>
      </tr></thead>
      <tbody>${filas}</tbody>
    </table>`;
  }

  function renderSemana(sem) {
    let html = `<div class="rep-sem-semana-block">
      <h3>${esc(sem.etiqueta)}</h3>`;
    if (sem.totales) {
      html += renderTotales({
        A_inicio: sem.totales.A,
        I: sem.totales.I,
        R: sem.totales.R,
        C_POS: sem.totales.C_POS,
        B: sem.totales.B,
        C_NEG: sem.totales.C_NEG,
        FC: sem.totales.FC,
        T_fin: sem.totales.T,
        desercion: sem.totales.desercion,
        desercion_label: (sem.totales.desercion >= 0 ? '+' : '') + sem.totales.desercion,
      });
    }
    (sem.especialidades || []).forEach((esp) => {
      html += `<div class="rep-sem-esp">
        <h3>${esc(esp.nombre)}</h3>
        <p class="rep-sem-esp-meta">Alumnos con asistencia esta semana: <strong>${esp.asistencia_unicos ?? 0}</strong></p>
        ${renderGruposTabla(esp.grupos)}
      </div>`;
    });
    html += '</div>';
    return html;
  }

  function render(data) {
    const wrap = document.getElementById('rep-sem-contenido');
    if (!wrap) return;
    let html = `<div class="rep-sem-leyenda">
      <strong>Leyenda:</strong> A = activos semana anterior · I = inicios · R = reingresos · +C/−C = cambio de horario ·
      B = baja (sin asistencia ni asesoría) · FC = fin de curso · T = A + I + R − B − FC
    </div>`;

    if (data.resumen_especialidades && data.resumen_especialidades.length) {
      html += '<div class="rep-sem-esp"><h3>Resumen — alumnos con asistencia por especialidad</h3><table class="rep-sem-tabla"><thead><tr><th>Especialidad</th><th>Asistieron (únicos)</th></tr></thead><tbody>';
      data.resumen_especialidades.forEach((e) => {
        html += `<tr><td class="rep-sem-clave">${esc(e.nombre)}</td><td><strong>${e.asistencia_unicos ?? 0}</strong></td></tr>`;
      });
      html += '</tbody></table></div>';
    }

    html += '<h3 style="margin:20px 0 10px;">Totales del plantel (periodo seleccionado)</h3>';
    html += renderTotales(data.totales);

    (data.semanas || []).forEach((sem) => {
      html += renderSemana(sem);
    });

    wrap.innerHTML = html;
  }

  async function generar() {
    const p = getParams();
    const loading = document.getElementById('rep-sem-loading');
    const wrap = document.getElementById('rep-sem-contenido');
    if (loading) loading.hidden = false;
    if (wrap) wrap.innerHTML = '';

    const url = new URL(api, window.location.href);
    url.searchParams.set('accion', 'generar');
    url.searchParams.set('modo', p.modo);
    url.searchParams.set('anio', String(p.anio));
    url.searchParams.set('semana_desde', String(p.semana_desde));
    url.searchParams.set('semana_hasta', String(p.semana_hasta));

    try {
      const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
      const data = await res.json();
      if (data.status !== 'ok') {
        if (wrap) wrap.innerHTML = '<div class="catalog-alert catalog-alert--error">' + esc(data.message || 'Error') + '</div>';
        return;
      }
      const periodo = document.getElementById('rep-sem-periodo');
      if (periodo && data.semanas && data.semanas.length) {
        const s0 = data.semanas[0];
        const sN = data.semanas[data.semanas.length - 1];
        periodo.textContent = data.semanas.length === 1
          ? s0.etiqueta
          : 'Semanas ' + s0.semana + '–' + sN.semana + ' / ' + p.anio;
      }
      render(data);
    } catch (_) {
      if (wrap) wrap.innerHTML = '<div class="catalog-alert catalog-alert--error">Error de conexión</div>';
    } finally {
      if (loading) loading.hidden = true;
    }
  }

  document.getElementById('rep-sem-modo')?.addEventListener('change', toggleFields);
  document.getElementById('btn-rep-sem-generar')?.addEventListener('click', generar);

  toggleFields();
  generar();
})();
