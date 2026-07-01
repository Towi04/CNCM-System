<?php
require_once __DIR__ . '/../config.php';
if (!asistencia_puede_tomar()) {
    echo '<div class="alert">No tienes permiso para registrar asistencias.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$fecha = trim($_GET['fecha'] ?? date('Y-m-d'));
$filtros = [
    'id_grupo' => (int) ($_GET['grupo'] ?? 0),
    'id_profesor' => (int) ($_GET['profesor'] ?? 0),
    'aula' => trim($_GET['aula'] ?? ''),
    'solo_con_clase_hoy' => !isset($_GET['todos_grupos']),
];
if (isset($_GET['todos_grupos'])) {
    $filtros['solo_con_clase_hoy'] = false;
}

$sesiones = asistencia_sesiones_del_dia($pdo, $idPlantel, $fecha, $filtros);

$profesores = $pdo->prepare(
    "SELECT DISTINCT u.id_usuario, u.nombre, u.apellido FROM usuarios u
     INNER JOIN grupos g ON g.id_profesor = u.id_usuario
     WHERE g.id_plantel = ? ORDER BY u.nombre"
);
$profesores->execute([$idPlantel]);
$listaProfesores = $profesores->fetchAll(PDO::FETCH_ASSOC);

$grupos = $pdo->prepare('SELECT id_grupo, clave, aula FROM grupos WHERE id_plantel = ? ORDER BY clave');
$grupos->execute([$idPlantel]);
$listaGrupos = $grupos->fetchAll(PDO::FETCH_ASSOC);

$aulas = $pdo->prepare(
    'SELECT DISTINCT aula FROM grupos WHERE id_plantel = ? AND aula IS NOT NULL AND aula <> \'\' ORDER BY aula'
);
$aulas->execute([$idPlantel]);
$listaAulas = $aulas->fetchAll(PDO::FETCH_COLUMN);

$grupoSel = (int) ($_GET['grupo'] ?? 0);
?>
<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/asistencia.css">

<div class="asist-wrap">
  <div class="asist-toolbar">
    <div>
      <label>Fecha</label>
      <input type="date" id="asist-fecha" value="<?php echo htmlspecialchars($fecha); ?>">
    </div>
    <div>
      <label>Profesor</label>
      <select id="asist-profesor">
        <option value="">Todos</option>
        <?php foreach ($listaProfesores as $p): ?>
          <option value="<?php echo (int)$p['id_usuario']; ?>"<?php echo $filtros['id_profesor'] === (int)$p['id_usuario'] ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Grupo</label>
      <select id="asist-grupo-filtro">
        <option value="">Todos</option>
        <?php foreach ($listaGrupos as $g): ?>
          <option value="<?php echo (int)$g['id_grupo']; ?>"<?php echo $filtros['id_grupo'] === (int)$g['id_grupo'] ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($g['clave']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Aula</label>
      <select id="asist-aula">
        <option value="">Todas</option>
        <?php foreach ($listaAulas as $aula): ?>
          <option value="<?php echo htmlspecialchars($aula); ?>"<?php echo $filtros['aula'] === $aula ? ' selected' : ''; ?>>
            <?php echo htmlspecialchars($aula); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="display:flex; align-items:center; gap:6px; margin-top:18px;">
        <input type="checkbox" id="asist-todos-grupos"<?php echo !$filtros['solo_con_clase_hoy'] ? ' checked' : ''; ?>>
        Mostrar todos los grupos
      </label>
    </div>
    <div>
      <button type="button" class="primary" id="btn-filtrar-asist">Buscar</button>
    </div>
  </div>

  <div class="asist-acciones-top" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
    <?php if (function_exists('asistencia_puede_checada') && asistencia_puede_checada()): ?>
    <button type="button" class="primary" onclick="cargarSeccion('asistencia_checada')">
      <i class="fas fa-fingerprint"></i> Terminal de checada
    </button>
    <?php endif; ?>
    <button type="button" class="primary" onclick="cargarSeccion('asistencia_faltantes')">
      <i class="fas fa-door-open"></i> Rondín (sin huella)
    </button>
    <button type="button" onclick="cargarSeccion('asistencia_registros')">
      <i class="fas fa-list-alt"></i> Registros / corregir
    </button>
    <?php if (asistencia_puede_ver_puntualidad()): ?>
    <button type="button" onclick="cargarSeccion('asistencia_puntualidad')">
      <i class="fas fa-clock"></i> Puntualidad personal
    </button>
    <?php endif; ?>
  </div>

  <p style="color:#666; margin:0 0 12px;">
    Selecciona una sesión para tomar lista. Los alumnos que ya checaron con huella o móvil aparecen con hora de llegada.
    Recepción solo registra faltantes o quienes no usaron el sensor (origen «Recepción»).
  </p>

  <div class="asist-sesiones" id="lista-sesiones">
    <?php if (empty($sesiones)): ?>
      <p>No hay grupos/clases para esta fecha y filtros. Configura horarios en el grupo o marca «Mostrar todos los grupos».</p>
    <?php else: ?>
      <?php foreach ($sesiones as $s): ?>
        <div class="asist-sesion-card<?php echo $grupoSel === (int)$s['id_grupo'] ? ' is-selected' : ''; ?>"
             data-grupo="<?php echo (int)$s['id_grupo']; ?>"
             role="button"
             tabindex="0">
          <div>
            <strong><?php echo htmlspecialchars($s['clave']); ?></strong>
            <?php if (!empty($s['especialidad_nombre'])): ?>
              · <?php echo htmlspecialchars($s['especialidad_nombre']); ?>
            <?php endif; ?>
            <div class="asist-sesion-card__meta">
              <?php if (!empty($s['profesor_nombre'])): ?>
                <i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars(trim($s['profesor_nombre'])); ?>
              <?php endif; ?>
              <?php if (!empty($s['aula'])): ?> · Aula <?php echo htmlspecialchars($s['aula']); ?><?php endif; ?>
              <?php if (!empty($s['hora_inicio'])): ?>
                · <?php echo substr($s['hora_inicio'], 0, 5); ?>–<?php echo substr($s['hora_fin'], 0, 5); ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="asist-contador">
            <div class="asist-contador__item">
              <div class="asist-contador__num"><?php echo (int)$s['total_alumnos']; ?></div>
              <div class="asist-contador__label">Inscritos</div>
            </div>
            <div class="asist-contador__item asist-contador__item--ok">
              <div class="asist-contador__num"><?php echo (int)$s['presentes']; ?></div>
              <div class="asist-contador__label">Presentes</div>
            </div>
            <div class="asist-contador__item asist-contador__item--huella">
              <div class="asist-contador__num"><?php echo (int)$s['por_huella']; ?></div>
              <div class="asist-contador__label">Por huella</div>
            </div>
            <div class="asist-contador__item asist-contador__item--falta">
              <div class="asist-contador__num"><?php echo (int)$s['faltantes_estimados']; ?></div>
              <div class="asist-contador__label">Faltan</div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div id="asist-toma-area"></div>

  <?php if (function_exists('rbac_rol_real') && in_array(rbac_rol_real(), ['supervisor', 'gerente'], true)): ?>
  <div class="asist-huella-test">
    <strong>Prueba sensor de huella</strong> (ID interno del lector = <code>codigo_huella</code> en ficha del alumno):<br>
    <input type="text" id="test-codigo-huella" placeholder="Código huella" style="margin:6px 8px 0 0; padding:8px;">
    <button type="button" id="btn-test-huella">Simular checada</button>
    <span id="test-huella-msg" style="margin-left:8px;"></span>
    <p style="margin:8px 0 0; color:#888;">
      URL del dispositivo: <code><?php echo htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . dirname($_SERVER['SCRIPT_NAME'] ?? '') . '/php/asistencia_huella_api.php'); ?></code>
    </p>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  function buildQuery(extra) {
    const p = new URLSearchParams();
    p.set('fecha', document.getElementById('asist-fecha').value);
    const prof = document.getElementById('asist-profesor').value;
    const grp = document.getElementById('asist-grupo-filtro').value;
    const aula = document.getElementById('asist-aula').value;
    if (prof) p.set('profesor', prof);
    if (grp) p.set('grupo', grp);
    if (aula) p.set('aula', aula);
    if (document.getElementById('asist-todos-grupos').checked) p.set('todos_grupos', '1');
    if (extra) {
      Object.keys(extra).forEach((k) => p.set(k, extra[k]));
    }
    return p;
  }

  document.getElementById('btn-filtrar-asist')?.addEventListener('click', () => {
    cargarSeccion('asistencia', buildQuery());
  });

  function cargarToma(grupoId) {
    const fecha = document.getElementById('asist-fecha').value;
    const area = document.getElementById('asist-toma-area');
    if (!grupoId) {
      area.innerHTML = '';
      return;
    }
    area.innerHTML = '<p>Cargando lista…</p>';
    fetch('views/asistencia_lista.php?grupo=' + grupoId + '&fecha=' + encodeURIComponent(fecha) + '&t=' + Date.now(), {
      headers: { 'X-Requested-With': 'fetch' },
    })
      .then((r) => r.text())
      .then((html) => {
        area.innerHTML = html;
        ejecutarScripts(area);
      })
      .catch(() => { area.innerHTML = '<p>Error al cargar.</p>'; });
  }

  document.querySelectorAll('.asist-sesion-card').forEach((card) => {
    const open = () => {
      document.querySelectorAll('.asist-sesion-card').forEach((c) => c.classList.remove('is-selected'));
      card.classList.add('is-selected');
      cargarToma(card.dataset.grupo);
    };
    card.addEventListener('click', open);
    card.addEventListener('keydown', (e) => { if (e.key === 'Enter') open(); });
  });

  <?php if ($grupoSel > 0): ?>
  cargarToma(<?php echo $grupoSel; ?>);
  <?php endif; ?>

  document.getElementById('btn-test-huella')?.addEventListener('click', async () => {
    const codigo = document.getElementById('test-codigo-huella').value.trim();
    const msg = document.getElementById('test-huella-msg');
    if (!codigo) return;
    const fd = new FormData();
    fd.append('codigo_huella', codigo);
    const res = await fetch('php/asistencia_huella_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    msg.textContent = data.message || '';
    msg.style.color = data.ok ? 'green' : '#c62828';
    if (data.ok) cargarSeccion('asistencia', buildQuery({ grupo: document.querySelector('.asist-sesion-card.is-selected')?.dataset.grupo || '' }));
  });
})();
</script>
