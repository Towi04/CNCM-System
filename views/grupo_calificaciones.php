<?php
require_once __DIR__ . '/../config.php';
$idGrupo = (int) ($_GET['id_grupo'] ?? 0);
if (!calificaciones_puede_capturar_grupo($pdo, $idGrupo)) {
    echo '<div class="alert">No puede capturar calificaciones de este grupo.</div>';
    return;
}

$grupo = calificaciones_cargar_grupo($pdo, $idGrupo);
if (!$grupo) {
    echo '<div class="alert">Grupo no encontrado.</div>';
    return;
}
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/grupo_calificaciones.css">
<link rel="stylesheet" href="css/hay_icon_buttons.css">

<div class="catalog-wrap gc-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-edit"></i> Calificaciones — <?php echo grupo_clave_html($grupo); ?></h2>
    <button type="button" class="secondary" onclick="cargarSeccion('grupos')">Volver a grupos</button>
  </div>

  <p class="gc-hint">
    Escala <strong>1–10</strong> · Mínimo aprobatorio <strong><?php echo ACADEMICO_NOTA_MINIMA; ?></strong>.
    Ajuste ponderaciones por parcial (deben sumar 100%). Coordinación puede editar igual que el profesor.
  </p>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Parcial (fase)</label>
      <select id="gc-fase"></select>
    </div>
    <button type="button" class="secondary" id="btn-gc-cargar">Cargar parcial</button>
    <button type="button" class="primary" id="btn-gc-guardar-todo">Guardar todas</button>
  </div>

  <div id="gc-posicion" class="gc-posicion" style="display:none;"></div>
  <div id="gc-msg" class="catalog-alert" style="display:none;"></div>

  <details class="gc-rubrica-box" id="gc-rubrica-box" open>
    <summary><strong>Ponderación del parcial</strong> (suma 100%)</summary>
    <div id="gc-rubrica" class="gc-rubrica-grid"></div>
    <button type="button" class="secondary" id="btn-gc-rubrica" style="margin-top:8px;">Guardar ponderación</button>
  </details>

  <div class="gc-table-wrap">
    <table class="gc-table" id="gc-table">
      <thead>
        <tr id="gc-thead-row">
          <th>Alumno</th>
          <th>Control</th>
        </tr>
      </thead>
      <tbody id="gc-tbody">
        <tr><td colspan="8" style="color:#888;">Seleccione un parcial y pulse Cargar.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
(function() {
  const idGrupo = <?php echo (int) $idGrupo; ?>;
  const api = 'php/grupo_calificaciones_api.php?id_grupo=' + idGrupo;
  let rubrica = [];
  let labels = {};
  let idFase = 0;

  const msg = document.getElementById('gc-msg');
  function show(t, ok) {
    msg.style.display = 'block';
    msg.className = 'catalog-alert catalog-alert--' + (ok ? 'ok' : 'error');
    msg.textContent = t;
  }

  function renderRubrica() {
    const box = document.getElementById('gc-rubrica');
    box.innerHTML = '';
    rubrica.forEach((c, i) => {
      const div = document.createElement('div');
      div.className = 'gc-rub-item';
      div.innerHTML =
        '<label>' + (labels[c.codigo] || c.codigo) + '</label>' +
        '<input type="number" min="0" max="100" step="0.1" data-idx="' + i + '" value="' + (c.peso_pct || 0) + '"> %';
      box.appendChild(div);
    });
  }

  function leerRubrica() {
    return rubrica.map((c, i) => {
      const inp = document.querySelector('#gc-rubrica input[data-idx="' + i + '"]');
      return { codigo: c.codigo, peso_pct: parseFloat(inp?.value || 0), obligatorio: !!c.obligatorio };
    });
  }

  function calcPromedio(notas) {
    let suma = 0, peso = 0;
    rubrica.forEach((c) => {
      const v = notas[c.codigo];
      if (v === '' || v === null || v === undefined) return;
      const p = parseFloat(c.peso_pct) || 0;
      suma += parseFloat(v) * p;
      peso += p;
    });
    if (peso <= 0) return null;
    return Math.round((suma / peso) * 100) / 100;
  }

  function renderTabla(alumnos) {
    const thead = document.getElementById('gc-thead-row');
    const tbody = document.getElementById('gc-tbody');
    thead.innerHTML = '';
    ['Alumno', 'Control'].forEach((t) => {
      const th = document.createElement('th');
      th.textContent = t;
      thead.appendChild(th);
    });
    rubrica.forEach((c) => {
      const th = document.createElement('th');
      th.textContent = labels[c.codigo] || c.codigo;
      thead.appendChild(th);
    });
    ['Prom.', ''].forEach((t) => {
      const th = document.createElement('th');
      th.textContent = t;
      thead.appendChild(th);
    });

    tbody.innerHTML = '';
    if (!alumnos.length) {
      tbody.innerHTML = '<tr><td colspan="' + (rubrica.length + 4) + '">Sin alumnos activos en el grupo.</td></tr>';
      return;
    }
    alumnos.forEach((a) => {
      const tr = document.createElement('tr');
      if (a.en_riesgo_academico == 1) tr.className = 'gc-row--riesgo';
      let html = '<td>' + escapeHtml(a.nombre_completo) + '</td><td>' + escapeHtml(a.numero_control || '') + '</td>';
      rubrica.forEach((c) => {
        const v = a.notas && a.notas[c.codigo] !== undefined ? a.notas[c.codigo] : '';
        html += '<td><input type="number" min="1" max="10" step="0.1" class="gc-nota" data-alumno="' + a.id_alumno + '" data-cod="' + c.codigo + '" value="' + v + '"></td>';
      });
      const prom = a.promedio != null ? a.promedio : '';
      const badge = a.aprobado == 1 ? 'gc-ok' : (a.aprobado == 0 ? 'gc-fail' : '');
      html += '<td class="gc-prom ' + badge + '" data-prom="' + a.id_alumno + '">' + prom + '</td>';
      html += '<td><button type="button" class="btn-icon-only btn-icon-only--ok gc-save-one" data-alumno="' + a.id_alumno + '" title="Guardar"><i class="fas fa-save"></i></button></td>';
      tr.innerHTML = html;
      tbody.appendChild(tr);
    });

    tbody.querySelectorAll('.gc-nota').forEach((inp) => {
      inp.addEventListener('input', () => {
        const idA = inp.dataset.alumno;
        const notas = {};
        tbody.querySelectorAll('.gc-nota[data-alumno="' + idA + '"]').forEach((n) => {
          notas[n.dataset.cod] = n.value;
        });
        const p = calcPromedio(notas);
        const cell = tbody.querySelector('[data-prom="' + idA + '"]');
        if (cell) {
          cell.textContent = p !== null ? p : '';
          cell.className = 'gc-prom' + (p !== null && p >= <?php echo ACADEMICO_NOTA_MINIMA; ?> ? ' gc-ok' : (p !== null ? ' gc-fail' : ''));
        }
      });
    });

    tbody.querySelectorAll('.gc-save-one').forEach((btn) => {
      btn.onclick = () => guardarAlumno(parseInt(btn.dataset.alumno, 10));
    });
  }

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function notasDeAlumno(idAlumno) {
    const notas = {};
    document.querySelectorAll('.gc-nota[data-alumno="' + idAlumno + '"]').forEach((n) => {
      if (n.value !== '') notas[n.dataset.cod] = n.value;
    });
    return notas;
  }

  async function guardarAlumno(idAlumno) {
    const fd = new FormData();
    fd.append('action', 'guardar_alumno');
    fd.append('id_fase', idFase);
    fd.append('id_alumno', idAlumno);
    fd.append('notas', JSON.stringify(notasDeAlumno(idAlumno)));
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    show(d.message, d.status === 'ok');
    return d.status === 'ok';
  }

  async function cargar() {
    idFase = parseInt(document.getElementById('gc-fase').value, 10);
    const r = await fetch(api + '&action=cargar&id_fase=' + idFase);
    const d = await r.json();
    if (d.status !== 'ok') { show(d.message || 'Error', false); return; }

    labels = d.criterios_labels || {};
    rubrica = d.rubrica || [];
    idFase = d.id_fase;
    renderRubrica();
    renderTabla(d.alumnos || []);

    const pos = d.posicion || {};
    const posEl = document.getElementById('gc-posicion');
    posEl.style.display = 'block';
    posEl.textContent = 'Sesiones lectivas: ' + (pos.semanas_lectivas || 0) +
      ' · Semana ' + (pos.semana_parcial || 1) + '/4 del parcial en curso (calendario).';
    msg.style.display = 'none';
  }

  async function init() {
    const r = await fetch(api + '&action=cargar');
    const d = await r.json();
    if (d.status !== 'ok') { show(d.message || 'Error', false); return; }
    const sel = document.getElementById('gc-fase');
    sel.innerHTML = '';
    (d.fases || []).forEach((f) => {
      const o = document.createElement('option');
      o.value = f.id_fase;
      o.textContent = (f.clave_fase || '') + ' — ' + (f.nombre_fase || '');
      if (f.id_fase === d.id_fase) o.selected = true;
      sel.appendChild(o);
    });
    labels = d.criterios_labels || {};
    rubrica = d.rubrica || [];
    idFase = d.id_fase;
    renderRubrica();
    renderTabla(d.alumnos || []);
    const pos = d.posicion || {};
    document.getElementById('gc-posicion').style.display = 'block';
    document.getElementById('gc-posicion').textContent =
      'Sesiones lectivas: ' + (pos.semanas_lectivas || 0) + ' · Semana ' + (pos.semana_parcial || 1) + '/4';
  }

  document.getElementById('btn-gc-cargar').onclick = cargar;

  document.getElementById('btn-gc-rubrica').onclick = async () => {
    rubrica = leerRubrica();
    const fd = new FormData();
    fd.append('action', 'guardar_rubrica');
    fd.append('id_fase', idFase);
    fd.append('criterios', JSON.stringify(rubrica));
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    show(d.message, d.status === 'ok');
  };

  document.getElementById('btn-gc-guardar-todo').onclick = async () => {
    rubrica = leerRubrica();
    const lote = [];
    document.querySelectorAll('#gc-tbody tr').forEach((tr) => {
      const inp = tr.querySelector('.gc-nota');
      if (!inp) return;
      const idA = parseInt(inp.dataset.alumno, 10);
      lote.push({ id_alumno: idA, notas: notasDeAlumno(idA) });
    });
    const fd = new FormData();
    fd.append('action', 'guardar_lote');
    fd.append('id_fase', idFase);
    fd.append('alumnos', JSON.stringify(lote));
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    show(d.message, d.status === 'ok');
    if (d.status === 'ok') cargar();
  };

  init();
})();
</script>
