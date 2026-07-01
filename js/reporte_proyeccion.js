(function () {

  const cfg = window.HAY_REPORTE_PROYECCION || {};

  const api = cfg.api || 'php/reporte_cartera_api.php';

  const exportUrl = cfg.export || 'php/reporte_cartera_export.php';

  let modo = 'mes';



  function esc(s) {

    const d = document.createElement('div');

    d.textContent = s == null ? '' : String(s);

    return d.innerHTML;

  }



  function filtrosParams() {

    const p = new URLSearchParams();

    const esp = document.getElementById('proy-esp')?.value || '';

    const grupo = document.getElementById('proy-grupo')?.value || '';

    const forma = document.getElementById('proy-forma')?.value || '';

    const q = document.getElementById('proy-q')?.value?.trim() || '';

    if (esp) p.set('id_especialidad', esp);

    if (grupo) p.set('id_grupo', grupo);

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

    llenarSelect('proy-esp', cat.especialidades, 'id_especialidad', 'nombre');

    llenarSelect('proy-grupo', cat.grupos, 'id_grupo', 'clave');

  }



  function habitoLabel(h, conf) {

    if (!h) return '—';

    const cls = 'habito-' + (conf || 'sin_datos');

    return `<span class="${cls}">Día ${esc(h)} del mes</span> <small>(${esc(conf || '')})</small>`;

  }



  function renderResumen(res, rango) {

    const box = document.getElementById('proy-resumen');

    const rangoEl = document.getElementById('proy-rango');

    const nota = document.getElementById('proy-nota');

    if (rangoEl && rango) rangoEl.textContent = rango.etiqueta || '';

    if (nota && res) nota.textContent = res.nota_meta || '';

    if (!box || !res) return;

    box.innerHTML = `

      <div class="reporte-cartera-card"><small>Alumnos esperados</small><strong>${esc(res.alumnos)}</strong></div>

      <div class="reporte-cartera-card"><small>Cargos del periodo</small><strong>${esc(res.cargos_nuevos_fmt)}</strong></div>

      <div class="reporte-cartera-card"><small>Adeudo previo</small><strong>${esc(res.adeudo_previo_fmt)}</strong></div>

      <div class="reporte-cartera-card"><small>Recuperación est.</small><strong>${esc(res.recuperacion_estimada_fmt)}</strong></div>

      <div class="reporte-cartera-card" style="border-color:#11458B;"><small>Meta total sugerida</small><strong>${esc(res.meta_total_fmt)}</strong></div>

    `;

  }



  async function cargar() {

    const fecha = document.getElementById('proy-fecha')?.value || '';

    const tbody = document.querySelector('#proy-tabla tbody');

    if (tbody) tbody.innerHTML = '<tr><td colspan="10" style="color:#888;">Calculando proyección…</td></tr>';



    const p = filtrosParams();

    p.set('accion', 'proyeccion');

    p.set('modo', modo);

    p.set('fecha', fecha);



    const r = await fetch(api + '?' + p.toString(), { credentials: 'same-origin' });

    const data = await r.json();

    if (data.status !== 'ok') {

      if (tbody) tbody.innerHTML = '<tr><td colspan="10" style="color:#b71c1c;">' + esc(data.message || 'Error') + '</td></tr>';

      return;

    }



    renderResumen(data.resumen, data.rango);

    const filas = data.filas || [];

    if (!filas.length) {

      tbody.innerHTML = '<tr><td colspan="10" style="color:#888;">No hay cargos proyectados con estos filtros.</td></tr>';

      return;

    }



    tbody.innerHTML = filas.map((f) => `

      <tr>

        <td>${esc(f.numero_control)}</td>

        <td><strong>${esc(f.nombre)}</strong><br><span style="color:#666;font-size:0.82rem;">${esc(f.detalle || '')}</span></td>

        <td>${esc(f.grupo_clave || '—')}</td>

        <td>${esc(f.forma_pago)}</td>

        <td>${esc(f.monto_periodo_fmt)}</td>

        <td>${esc(f.adeudo_previo_fmt)}</td>

        <td><strong>${esc(f.monto_proyectado_fmt)}</strong></td>

        <td>${esc(f.fecha_probable_fmt)}</td>

        <td>${habitoLabel(f.habito_dia, f.habito_confianza)}</td>

        <td><button type="button" class="secondary btn-proy-adeudo" data-control="${esc(f.numero_control)}">Adeudo</button></td>

      </tr>

    `).join('');



    tbody.querySelectorAll('.btn-proy-adeudo').forEach((btn) => {

      btn.addEventListener('click', () => {

        if (typeof cargarSeccion === 'function') {

          cargarSeccion('consulta_adeudo', 'control=' + encodeURIComponent(btn.dataset.control || ''));

        }

      });

    });

  }



  function exportar() {

    const fecha = document.getElementById('proy-fecha')?.value || '';

    const p = filtrosParams();

    p.set('tipo', 'proyeccion');

    p.set('modo', modo);

    p.set('fecha', fecha);

    window.location.href = exportUrl + '?' + p.toString();

  }



  document.querySelectorAll('#proy-modo-tabs button').forEach((btn) => {

    btn.addEventListener('click', () => {

      document.querySelectorAll('#proy-modo-tabs button').forEach((b) => b.classList.remove('active'));

      btn.classList.add('active');

      modo = btn.dataset.modo || 'mes';

      cargar();

    });

  });



  document.getElementById('btn-proy-cargar')?.addEventListener('click', cargar);

  document.getElementById('btn-proy-export')?.addEventListener('click', exportar);

  document.getElementById('btn-proy-print')?.addEventListener('click', () => window.print());

  ['proy-esp', 'proy-grupo', 'proy-forma'].forEach((id) => {

    document.getElementById(id)?.addEventListener('change', cargar);

  });

  document.getElementById('proy-q')?.addEventListener('keydown', (e) => {

    if (e.key === 'Enter') cargar();

  });



  cargarCatalogo().then(cargar);

})();

