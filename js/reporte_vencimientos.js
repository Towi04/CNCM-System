(function () {

  const cfg = window.HAY_REPORTE_CARTERA || {};

  const api = cfg.api || 'php/reporte_cartera_api.php';

  const exportUrl = cfg.export || 'php/reporte_cartera_export.php';



  function esc(s) {

    const d = document.createElement('div');

    d.textContent = s == null ? '' : String(s);

    return d.innerHTML;

  }



  function filtrosParams() {

    const p = new URLSearchParams();

    const esp = document.getElementById('venc-esp')?.value || '';

    const grupo = document.getElementById('venc-grupo')?.value || '';

    const sem = document.getElementById('venc-semaforo')?.value || '';

    const forma = document.getElementById('venc-forma')?.value || '';

    const q = document.getElementById('venc-q')?.value?.trim() || '';

    if (esp) p.set('id_especialidad', esp);

    if (grupo) p.set('id_grupo', grupo);

    if (sem) p.set('semaforo', sem);

    if (forma) p.set('forma_pago', forma);

    if (q) p.set('q', q);

    return p;

  }



  function llenarSelect(id, items, valueKey, labelKey) {

    const sel = document.getElementById(id);

    if (!sel) return;

    const val = sel.value;

    sel.innerHTML = '<option value="">Todos</option>';

    (items || []).forEach((it) => {

      const o = document.createElement('option');

      o.value = String(it[valueKey] ?? '');

      o.textContent = it[labelKey] ?? it.nombre ?? o.value;

      sel.appendChild(o);

    });

    if (val) sel.value = val;

  }



  async function cargarCatalogo() {

    const r = await fetch(api + '?accion=catalogo', { credentials: 'same-origin' });

    const data = await r.json();

    if (data.status !== 'ok') return;

    const cat = data.catalogo || {};

    llenarSelect('venc-esp', cat.especialidades, 'id_especialidad', 'nombre');

    llenarSelect('venc-grupo', cat.grupos, 'id_grupo', 'clave');

  }



  function renderResumen(res) {

    const box = document.getElementById('venc-resumen');

    if (!box || !res) return;

    const ps = res.por_semaforo || {};

    box.innerHTML = `

      <div class="reporte-cartera-card"><small>Alumnos con adeudo</small><strong>${esc(res.total_alumnos)}</strong></div>

      <div class="reporte-cartera-card"><small>Adeudo total</small><strong>${esc(res.total_adeudo_fmt)}</strong></div>

      <div class="reporte-cartera-card"><small>Amarillo (1 mes)</small><strong>${esc(ps.amarillo || 0)}</strong></div>

      <div class="reporte-cartera-card"><small>Naranja (2–3 meses)</small><strong>${esc(ps.naranja || 0)}</strong></div>

      <div class="reporte-cartera-card"><small>Rojo (+3 meses)</small><strong>${esc(ps.rojo || 0)}</strong></div>

    `;

  }



  async function cargar() {

    const fecha = document.getElementById('venc-fecha')?.value || '';

    const tbody = document.querySelector('#venc-tabla tbody');

    if (tbody) tbody.innerHTML = '<tr><td colspan="10" style="color:#888;">Calculando…</td></tr>';



    const p = filtrosParams();

    p.set('accion', 'vencimientos');

    p.set('fecha', fecha);



    const r = await fetch(api + '?' + p.toString(), { credentials: 'same-origin' });

    const data = await r.json();

    if (data.status !== 'ok') {

      if (tbody) tbody.innerHTML = '<tr><td colspan="10" style="color:#b71c1c;">' + esc(data.message || 'Error') + '</td></tr>';

      return;

    }



    renderResumen(data.resumen);

    const filas = data.filas || [];

    if (!filas.length) {

      tbody.innerHTML = '<tr><td colspan="10" style="color:#888;">No hay alumnos con colegiatura vencida con estos filtros.</td></tr>';

      return;

    }



    tbody.innerHTML = filas.map((f) => `

      <tr class="row-semaforo-${esc(f.semaforo)}">

        <td><span class="reporte-cartera-semaforo semaforo-${esc(f.semaforo)}">${esc(f.semaforo_label)}</span></td>

        <td>${esc(f.numero_control)}</td>

        <td><strong>${esc(f.nombre)}</strong></td>

        <td>${esc(f.grupo_clave || '—')}</td>

        <td>${esc(f.telefono || '—')}</td>

        <td>${esc(f.forma_pago)}</td>

        <td>${esc(f.periodos_vencidos)} <span style="color:#666;font-size:0.82rem;">${esc(f.detalle_periodos)}</span></td>

        <td>${esc(f.periodo_mas_antiguo)}</td>

        <td><strong>${esc(f.adeudo_fmt)}</strong></td>

        <td><button type="button" class="secondary btn-venc-adeudo" data-id="${f.id_alumno}" data-control="${esc(f.numero_control)}">Ver adeudo</button></td>

      </tr>

    `).join('');



    tbody.querySelectorAll('.btn-venc-adeudo').forEach((btn) => {

      btn.addEventListener('click', () => {

        const q = btn.dataset.control || '';

        if (typeof cargarSeccion === 'function') {

          cargarSeccion('consulta_adeudo', 'control=' + encodeURIComponent(q));

        }

      });

    });

  }



  function exportar() {

    const fecha = document.getElementById('venc-fecha')?.value || '';

    const p = filtrosParams();

    p.set('tipo', 'vencimientos');

    p.set('fecha', fecha);

    window.location.href = exportUrl + '?' + p.toString();

  }



  document.getElementById('btn-venc-cargar')?.addEventListener('click', cargar);

  document.getElementById('btn-venc-export')?.addEventListener('click', exportar);

  document.getElementById('btn-venc-print')?.addEventListener('click', () => window.print());

  ['venc-esp', 'venc-grupo', 'venc-semaforo', 'venc-forma'].forEach((id) => {

    document.getElementById(id)?.addEventListener('change', cargar);

  });

  document.getElementById('venc-q')?.addEventListener('keydown', (e) => {

    if (e.key === 'Enter') cargar();

  });



  cargarCatalogo().then(cargar);

})();

