<?php
require_once __DIR__ . '/../config.php';
asesoria_ensure_schema($pdo);

if (!asesoria_puede_agendar()) {
    echo '<div class="alert">Sin permiso para agendar asesorías.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$materiasPrep = GRUPO_MATERIAS_PREP;
$api = hay_asset_url('php/asesoria_api.php');
$idAlumnoPre = (int) ($_GET['id_alumno'] ?? 0);
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">

<div class="catalog-wrap">
  <h2><i class="fas fa-user-clock"></i> Agendar asesoría</h2>
  <p style="color:#666;">Busque al alumno, elija tipo y horario con profesor disponible. Mínimo un día de anticipación (salvo autorización).</p>
  <div id="ase-ag-msg" class="catalog-alert" style="display:none;"></div>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Buscar alumno (control o nombre)</label>
      <input type="text" id="ase-ag-buscar" placeholder="Número de control…" value="<?php echo $idAlumnoPre > 0 ? '' : ''; ?>">
    </div>
    <button type="button" class="primary" id="ase-ag-btn-buscar">Buscar</button>
  </div>

  <div id="ase-ag-alumno" style="display:none; margin:16px 0; padding:14px; background:#f5f8ff; border-radius:10px;"></div>

  <form id="form-ase-agendar" style="display:none;">
    <div class="catalog-form-grid">
      <div class="full">
        <label>Tipo de asesoría</label>
        <select id="ase-ag-tipo" name="tipo" required></select>
      </div>
      <div>
        <label>Grupo (si aplica falta)</label>
        <select id="ase-ag-grupo" name="id_grupo"><option value="">—</option></select>
      </div>
      <div>
        <label>Materia / clave</label>
        <select id="ase-ag-materia" name="materia_clave">
          <option value="">General</option>
          <?php foreach ($materiasPrep as $clave => $nom): ?>
          <option value="<?php echo htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($nom); ?></option>
          <?php endforeach; ?>
          <option value="ingles">Inglés</option>
          <option value="computacion">Computación</option>
        </select>
      </div>
      <div class="full">
        <label>Tema</label>
        <input type="text" id="ase-ag-tema" name="tema" required maxlength="200" placeholder="Tema a revisar">
      </div>
      <div>
        <label>Profesor</label>
        <select id="ase-ag-profesor" name="id_profesor" required></select>
      </div>
      <div>
        <label>Fecha</label>
        <input type="date" id="ase-ag-fecha" name="fecha" required>
      </div>
      <div>
        <label>Hora inicio</label>
        <select id="ase-ag-hora" name="hora_inicio" required></select>
      </div>
      <div>
        <label>Forma de pago</label>
        <select name="forma_pago"><option>Efectivo</option><option>Tarjeta</option><option>Transferencia</option></select>
      </div>
      <div style="display:flex;align-items:center;gap:8px;padding-top:24px;">
        <label><input type="checkbox" id="ase-ag-mismo-tema" name="mismo_tema" value="1" checked> Mismo tema (grupal)</label>
      </div>
      <div style="display:flex;align-items:center;gap:8px;padding-top:24px;">
        <label><input type="checkbox" id="ase-ag-moodle" name="moodle_verificado" value="1"> Verificación manual Moodle</label>
      </div>
      <div id="ase-ag-moodle-status" style="display:none; padding:8px 12px; border-radius:8px; font-size:0.9rem;"></div>
      <div class="full" id="ase-ag-companeros-wrap" style="display:none;">
        <label>Compañeros (misma falta / mismo tema, máx. 2 adicionales)</label>
        <div style="display:flex;gap:8px;margin:6px 0;">
          <input type="text" id="ase-ag-comp-buscar" placeholder="Control del compañero…" style="flex:1;">
          <button type="button" class="secondary" id="ase-ag-comp-add">Agregar</button>
        </div>
        <ul id="ase-ag-comp-list" style="list-style:none;padding:0;margin:0;"></ul>
      </div>
      <?php if (asesoria_puede_autorizar_mismo_dia()): ?>
      <div style="display:flex;align-items:center;gap:8px;padding-top:24px;">
        <label><input type="checkbox" id="ase-ag-hoy" name="autorizar_mismo_dia" value="1"> Autorizar mismo día</label>
      </div>
      <?php endif; ?>
    </div>
    <input type="hidden" id="ase-ag-id-alumno" name="id_alumno">
    <input type="hidden" id="ase-ag-id-especialidad" name="id_especialidad" value="">
    <p id="ase-ag-costo" style="margin:12px 0; font-weight:700;"></p>
    <button type="submit" class="primary">Confirmar agendado</button>
  </form>
</div>

<script>
(function () {
  const api = <?php echo json_encode($api, JSON_UNESCAPED_UNICODE); ?>;
  const msg = document.getElementById('ase-ag-msg');
  const boxAl = document.getElementById('ase-ag-alumno');
  const form = document.getElementById('form-ase-agendar');
  let alumnoActual = null;
  let companeros = [];
  const manana = new Date(); manana.setDate(manana.getDate() + 1);
  document.getElementById('ase-ag-fecha').min = manana.toISOString().slice(0, 10);

  function showMsg(t, ok) {
    msg.style.display = t ? 'block' : 'none';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = t || '';
  }

  async function buscarAlumno() {
    const q = document.getElementById('ase-ag-buscar').value.trim();
    if (!q) return;
    const r = await fetch(api + '?action=buscar_alumno&q=' + encodeURIComponent(q));
    const d = await r.json();
    if (d.status !== 'ok' || !d.items || !d.items.length) { showMsg('Alumno no encontrado', false); return; }
    const al = d.items[0];
    const r2 = await fetch(api + '?action=alumno_info&id_alumno=' + al.id_alumno);
    const info = await r2.json();
    if (info.status !== 'ok') { showMsg(info.message || 'Error', false); return; }
    alumnoActual = info;
    boxAl.style.display = 'block';
    boxAl.innerHTML = '<strong>' + (info.alumno.nombre_completo || '') + '</strong> · Control ' + (info.alumno.numero_control || '') +
      (info.en_personalizado ? ' · <span style="color:#c62828">Personalizado (sin asesoría por falta)</span>' : '') +
      ' · Créditos: ' + (info.credito_saldo || 0) + ' h';
    document.getElementById('ase-ag-id-alumno').value = al.id_alumno;
    const selTipo = document.getElementById('ase-ag-tipo');
    selTipo.innerHTML = (info.tipos || []).map(t =>
      '<option value="' + t + '">' + (<?php echo json_encode(ASESORIA_TIPOS, JSON_UNESCAPED_UNICODE); ?>[t] || t) + '</option>'
    ).join('');
    const selG = document.getElementById('ase-ag-grupo');
    selG.innerHTML = '<option value="">—</option>' + (info.grupos || []).map(g =>
      '<option value="' + g.id_grupo + '" data-esp="' + (g.id_especialidad || '') + '">' + g.clave + (g.esp_nombre ? ' · ' + g.esp_nombre : '') + '</option>'
    ).join('');
    form.style.display = 'block';
    companeros = [];
    renderCompaneros();
    showMsg('', true);
    cargarProfesores();
    onTipoGrupoChange();
  }

  function renderCompaneros() {
    const ul = document.getElementById('ase-ag-comp-list');
    ul.innerHTML = companeros.map((c, i) =>
      '<li style="padding:6px 0;display:flex;justify-content:space-between;align-items:center;">' +
      '<span>' + (c.nombre || c.numero_control || c.id_alumno) + '</span>' +
      '<button type="button" class="secondary" data-i="' + i + '">Quitar</button></li>'
    ).join('');
    ul.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('click', () => {
        companeros.splice(parseInt(btn.dataset.i, 10), 1);
        renderCompaneros();
      });
    });
  }

  async function agregarCompanero() {
    const q = document.getElementById('ase-ag-comp-buscar').value.trim();
    if (!q) return;
    const maxExtra = document.getElementById('ase-ag-mismo-tema').checked ? 2 : 1;
    if (companeros.length >= maxExtra) {
      showMsg('Máximo ' + (maxExtra + 1) + ' alumnos en esta cita', false);
      return;
    }
    const r = await fetch(api + '?action=buscar_alumno&q=' + encodeURIComponent(q));
    const d = await r.json();
    if (d.status !== 'ok' || !d.items || !d.items.length) { showMsg('Compañero no encontrado', false); return; }
    const al = d.items[0];
    if (String(al.id_alumno) === String(document.getElementById('ase-ag-id-alumno').value)) return;
    if (companeros.some(c => String(c.id_alumno) === String(al.id_alumno))) return;
    companeros.push({ id_alumno: al.id_alumno, numero_control: al.numero_control, nombre: al.nombre_completo || al.nombre });
    document.getElementById('ase-ag-comp-buscar').value = '';
    renderCompaneros();
  }

  async function verificarMoodle() {
    const tipo = document.getElementById('ase-ag-tipo').value;
    const box = document.getElementById('ase-ag-moodle-status');
    const chkManual = document.getElementById('ase-ag-moodle');
    if (tipo !== 'falta_gratis') {
      box.style.display = 'none';
      return;
    }
    const idAlumno = document.getElementById('ase-ag-id-alumno').value;
    const idGrupo = document.getElementById('ase-ag-grupo').value;
    if (!idAlumno || !idGrupo) {
      box.style.display = 'none';
      return;
    }
    box.style.display = 'block';
    box.textContent = 'Verificando Moodle…';
    box.style.background = '#fff8e1';
    const r = await fetch(api + '?action=moodle_verificar&id_alumno=' + idAlumno + '&id_grupo=' + idGrupo);
    const d = await r.json();
    if (d.completado) {
      box.textContent = 'Moodle: actividades completadas ✓';
      box.style.background = '#e8f5e9';
      chkManual.checked = true;
    } else {
      box.textContent = 'Moodle: sin actividades completadas. Marque verificación manual si coordinación lo autoriza.';
      box.style.background = '#ffebee';
      chkManual.checked = false;
    }
  }

  function onTipoGrupoChange() {
    const tipo = document.getElementById('ase-ag-tipo').value;
    const wrap = document.getElementById('ase-ag-companeros-wrap');
    const selG = document.getElementById('ase-ag-grupo');
    const idEsp = selG.selectedOptions[0]?.dataset?.esp || '';
    document.getElementById('ase-ag-id-especialidad').value = idEsp;
    wrap.style.display = (tipo === 'falta_gratis' || tipo === 'pagada_cross' || tipo === 'pagada_materia') ? 'block' : 'none';
    verificarMoodle();
    cargarProfesores();
  }

  async function cargarProfesores() {
    const materia = document.getElementById('ase-ag-materia').value;
    const tipo = document.getElementById('ase-ag-tipo').value;
    const idEsp = document.getElementById('ase-ag-id-especialidad').value;
    const r = await fetch(api + '?action=profesores&materia_clave=' + encodeURIComponent(materia) +
      (idEsp ? '&id_especialidad=' + idEsp : '') + (tipo === 'kids_dual' ? '&kids_dual=1' : ''));
    const d = await r.json();
    const sel = document.getElementById('ase-ag-profesor');
    sel.innerHTML = (d.profesores || []).map(p => '<option value="' + p.id_usuario + '">' + p.nombre + '</option>').join('');
    cargarSlots();
  }

  async function cargarSlots() {
    const idProf = document.getElementById('ase-ag-profesor').value;
    const fecha = document.getElementById('ase-ag-fecha').value;
    if (!idProf || !fecha) return;
    const r = await fetch(api + '?action=slots&id_profesor=' + idProf + '&desde=' + fecha + '&hasta=' + fecha);
    const d = await r.json();
    const sel = document.getElementById('ase-ag-hora');
    sel.innerHTML = (d.slots || []).filter(s => s.fecha === fecha).map(s =>
      '<option value="' + s.hora + '">' + String(s.hora).padStart(2, '0') + ':00</option>'
    ).join('') || '<option value="">Sin horarios</option>';
  }

  document.getElementById('ase-ag-btn-buscar').addEventListener('click', buscarAlumno);
  document.getElementById('ase-ag-materia').addEventListener('change', cargarProfesores);
  document.getElementById('ase-ag-tipo').addEventListener('change', onTipoGrupoChange);
  document.getElementById('ase-ag-grupo').addEventListener('change', onTipoGrupoChange);
  document.getElementById('ase-ag-mismo-tema').addEventListener('change', () => { if (!document.getElementById('ase-ag-mismo-tema').checked && companeros.length > 1) companeros = companeros.slice(0, 1); renderCompaneros(); });
  document.getElementById('ase-ag-profesor').addEventListener('change', cargarSlots);
  document.getElementById('ase-ag-fecha').addEventListener('change', cargarSlots);
  document.getElementById('ase-ag-comp-add').addEventListener('click', agregarCompanero);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    fd.append('action', 'agendar');
    if (companeros.length) {
      const alumnos = [{ id_alumno: fd.get('id_alumno'), id_grupo: fd.get('id_grupo') || '' }];
      companeros.forEach(c => alumnos.push({ id_alumno: c.id_alumno, id_grupo: fd.get('id_grupo') || '' }));
      fd.append('alumnos_json', JSON.stringify(alumnos));
    }
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    showMsg(d.message || '', d.status === 'ok');
    if (d.status === 'ok') {
      form.reset();
      form.style.display = 'none';
      boxAl.style.display = 'none';
    }
  });

  <?php if ($idAlumnoPre > 0): ?>
  document.getElementById('ase-ag-id-alumno').value = '<?php echo $idAlumnoPre; ?>';
  fetch(api + '?action=alumno_info&id_alumno=<?php echo $idAlumnoPre; ?>').then(r => r.json()).then(info => {
    if (info.status === 'ok') {
      alumnoActual = info;
      document.getElementById('ase-ag-buscar').value = info.alumno.numero_control || '';
      buscarAlumno();
    }
  });
  <?php endif; ?>
})();
</script>
