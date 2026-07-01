<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_id']) || !calendario_puede_ver_consulta($pdo)) {
    echo '<div class="alert">No tiene permiso para ver el calendario institucional.</div>';
    return;
}

$anio = (int) ($_GET['anio'] ?? date('Y'));
$mes = (int) ($_GET['mes'] ?? date('n'));
$capas = calendario_capas_consulta($pdo);
$soloLectura = rbac_rol_efectivo() === 'profesor' && empty(calendario_modelos_editables_usuario());
$nombresMes = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/admin_calendario.css">

<div class="catalog-wrap cal-consulta-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-calendar-check"></i> Calendario institucional</h2>
    <?php if (!$soloLectura && calendario_puede_ver_menu()): ?>
      <a href="#" data-seccion="admin_calendario" style="font-size:0.85rem;">Editar calendarios escolares →</a>
    <?php endif; ?>
  </div>

  <?php if ($soloLectura): ?>
    <p class="cal-consulta-aviso"><i class="fas fa-eye"></i> Vista de solo lectura según los grupos que imparte.</p>
  <?php else: ?>
    <p style="color:#666; max-width:920px;">
      Marque las áreas que desea ver en el mismo mes. Incluye calendario académico de inglés y especialidades (vacaciones y actividades), prepa y eventos administrativos.
    </p>
  <?php endif; ?>

  <?php if (empty($capas)): ?>
    <p class="catalog-alert catalog-alert--warn">No hay calendarios asignados a su perfil. Si imparte clases, verifique que tenga grupos asignados.</p>
  <?php else: ?>

  <div class="cal-capas-filtro" id="cal-capas-filtro">
    <?php foreach ($capas as $c): ?>
      <label class="cal-capa-check">
        <input type="checkbox" name="capa" value="<?php echo htmlspecialchars($c['id']); ?>" checked>
        <span class="cal-capa-badge cal-capa-badge--<?php echo htmlspecialchars($c['id']); ?>">
          <?php echo htmlspecialchars($c['prefijo']); ?>
        </span>
        <?php echo htmlspecialchars($c['label']); ?>
      </label>
    <?php endforeach; ?>
  </div>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Año</label>
      <input type="number" id="cc-anio" value="<?php echo $anio; ?>" min="2020" max="2040">
    </div>
    <div class="field">
      <label>Mes</label>
      <select id="cc-mes">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?php echo $m; ?>"<?php echo $m === $mes ? ' selected' : ''; ?>><?php echo $nombresMes[$m]; ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <button type="button" class="primary" id="btn-cc-cargar">Actualizar</button>
  </div>

  <div id="cc-detalle" class="cal-consulta-detalle" hidden></div>
  <div id="cc-msg" class="catalog-alert" style="display:none;"></div>

  <div class="cal-grid-wrap">
    <div class="cal-grid-header">
      <span>Dom</span><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span>
    </div>
    <div class="cal-grid cal-grid--consulta" id="cc-grid"></div>
  </div>
  <?php endif; ?>
</div>

<script>
(function() {
  const api = 'php/calendario_consulta_api.php';
  const grid = document.getElementById('cc-grid');
  if (!grid) return;

  const msg = document.getElementById('cc-msg');
  const detalle = document.getElementById('cc-detalle');
  const hoy = new Date().toISOString().slice(0, 10);

  const coloresTipo = {
    cierre_plantel: '#37474f',
    sin_clase_abierto: '#fff8e1',
    asueto: '#f3e5f5',
    vacacion_sabado: '#e3f2fd',
    vacacion_cuatrimestre: '#e0f2f1',
    admin_junta_directiva: '#1565c0',
    admin_junta_personal: '#1976d2',
    admin_capacitacion: '#2e7d32',
    admin_evento: '#6a1b9a',
    admin_otro: '#5d4037',
  };

  function show(t, ok) {
    msg.style.display = 'block';
    msg.className = 'catalog-alert catalog-alert--' + (ok ? 'ok' : 'error');
    msg.textContent = t;
  }

  function capasActivas() {
    return Array.from(document.querySelectorAll('#cal-capas-filtro input:checked')).map((el) => el.value);
  }

  function colorMarca(m) {
    if (m.tipo && m.tipo.startsWith('admin_')) {
      return coloresTipo[m.tipo] || '#eceff1';
    }
    return coloresTipo[m.tipo] || '#f5f5f5';
  }

  function renderGrid(dias) {
    grid.innerHTML = '';
    if (!dias.length) return;
    const primerDow = dias[0].dow;
    for (let i = 0; i < primerDow; i++) {
      const empty = document.createElement('div');
      empty.className = 'cal-day is-empty';
      grid.appendChild(empty);
    }
    dias.forEach((d) => {
      const cell = document.createElement('button');
      cell.type = 'button';
      cell.className = 'cal-day cal-day--consulta' + (d.fecha === hoy ? ' is-today' : '');
      if (!d.marcas || !d.marcas.length) {
        cell.innerHTML = '<div class="cal-day__num">' + d.dia + '</div>';
        cell.disabled = true;
        cell.style.cursor = 'default';
      } else {
        let tags = '<div class="cal-day__num">' + d.dia + '</div>';
        d.marcas.forEach((m) => {
          const bg = colorMarca(m);
          const fg = (m.tipo === 'cierre_plantel') ? '#fff' : '#222';
          tags += '<div class="cal-day__tag cal-day__tag--capa" style="background:' + bg + ';color:' + fg + '" ' +
            'data-json="' + encodeURIComponent(JSON.stringify(m)) + '">' +
            '<strong>' + m.prefijo + '</strong> ' + (m.etiqueta || '') + '</div>';
        });
        cell.innerHTML = tags;
        cell.onclick = () => mostrarDetalle(d);
      }
      grid.appendChild(cell);
    });
  }

  function mostrarDetalle(d) {
    detalle.hidden = false;
    let html = '<h4 style="margin:0 0 8px;">' + d.fecha + '</h4><ul style="margin:0;padding-left:18px;">';
    (d.marcas || []).forEach((m) => {
      html += '<li><strong>[' + m.prefijo + ']</strong> ' + (m.etiqueta || '') +
        (m.detalle ? ' — <span style="color:#666">' + m.detalle + '</span>' : '') + '</li>';
    });
    html += '</ul>';
    detalle.innerHTML = html;
  }

  async function cargar() {
    const capas = capasActivas();
    if (!capas.length) {
      show('Seleccione al menos un área para mostrar.', false);
      grid.innerHTML = '';
      return;
    }
    const anio = document.getElementById('cc-anio').value;
    const mes = document.getElementById('cc-mes').value;
    const r = await fetch(api + '?action=mes_combinado&anio=' + anio + '&mes=' + mes + '&capas=' + encodeURIComponent(capas.join(',')));
    const data = await r.json();
    if (data.status !== 'ok') {
      show(data.message || 'Error', false);
      return;
    }
    msg.style.display = 'none';
    renderGrid(data.dias || []);
  }

  document.getElementById('btn-cc-cargar').onclick = cargar;
  document.querySelectorAll('#cal-capas-filtro input').forEach((cb) => {
    cb.addEventListener('change', cargar);
  });

  cargar();
})();
</script>
