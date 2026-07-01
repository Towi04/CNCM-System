<?php
require_once __DIR__ . '/../config.php';
$idGrupo = (int) ($_GET['id_grupo'] ?? 0);
if (!calificaciones_puede_capturar_grupo($pdo, $idGrupo)) {
    echo '<div class="alert">No puede configurar ponderaciones de este grupo.</div>';
    return;
}

$grupo = calificaciones_cargar_grupo($pdo, $idGrupo);
if (!$grupo) {
    echo '<div class="alert">Grupo no encontrado.</div>';
    return;
}

$idEsp = (int) ($grupo['id_especialidad'] ?? 0);
$fases = $idEsp ? fase_listar($pdo, $idEsp) : [];
$idFaseSug = calificaciones_fase_sugerida($pdo, $grupo);
$labels = calificaciones_criterios_etiquetas();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/grupo_calificaciones.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap gc-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-balance-scale"></i> Ponderación — <?php echo grupo_clave_html($grupo); ?></h2>
    <p class="gc-hint">Defina los pesos de evaluación <strong>antes de capturar la primera calificación</strong>. La suma debe ser 100%.</p>
    <button type="button" class="secondary" onclick="cargarSeccion('profesor_portal')">Volver al portal</button>
  </div>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Parcial (fase)</label>
      <select id="gp-fase">
        <?php foreach ($fases as $f): ?>
        <option value="<?php echo (int)$f['id_fase']; ?>"<?php echo (int)$f['id_fase'] === $idFaseSug ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars(($f['clave_fase'] ?? '') . ' — ' . ($f['nombre_fase'] ?? '')); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="button" class="secondary" id="btn-gp-cargar">Cargar</button>
    <button type="button" class="primary" id="btn-gp-guardar">Guardar ponderación</button>
    <button type="button" class="secondary" onclick="cargarSeccion('grupo_calificaciones', 'id_grupo=<?php echo $idGrupo; ?>')">Ir a calificaciones</button>
  </div>

  <div id="gp-msg" class="catalog-alert" style="display:none;"></div>
  <div id="gp-rubrica" class="gc-rubrica-grid"></div>
</div>

<script>
(function () {
  const idGrupo = <?php echo (int) $idGrupo; ?>;
  const api = 'php/grupo_calificaciones_api.php?id_grupo=' + idGrupo;
  const labels = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
  let rubrica = [];

  const msg = document.getElementById('gp-msg');
  function show(t, ok) {
    msg.style.display = 'block';
    msg.className = 'catalog-alert catalog-alert--' + (ok ? 'ok' : 'error');
    msg.textContent = t;
  }

  function renderRubrica() {
    const box = document.getElementById('gp-rubrica');
    box.innerHTML = '';
    rubrica.forEach((c, i) => {
      const div = document.createElement('div');
      div.className = 'gc-rub-item';
      div.innerHTML = '<label>' + (labels[c.codigo] || c.codigo) + '</label>' +
        '<input type="number" min="0" max="100" step="0.1" data-idx="' + i + '" value="' + (c.peso_pct || 0) + '"> %';
      box.appendChild(div);
    });
  }

  function leerRubrica() {
    return rubrica.map((c, i) => {
      const inp = document.querySelector('#gp-rubrica input[data-idx="' + i + '"]');
      return { codigo: c.codigo, peso_pct: parseFloat(inp?.value || 0), obligatorio: !!c.obligatorio };
    });
  }

  async function cargar() {
    const idFase = document.getElementById('gp-fase').value;
    const res = await fetch(api + '&action=cargar&id_fase=' + encodeURIComponent(idFase), {
      headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin',
    });
    const data = await res.json();
    if (data.status !== 'ok') { show(data.message || 'Error', false); return; }
    rubrica = data.rubrica || [];
    renderRubrica();
    if (data.rubrica_guardada) {
      show('Ponderación ya definida para este parcial. Puede ajustarla si lo necesita.', true);
    } else {
      show('Aún no ha guardado la ponderación de este parcial. Ajuste los porcentajes y pulse Guardar.', false);
    }
  }

  async function guardar() {
    const idFase = document.getElementById('gp-fase').value;
    const fd = new FormData();
    fd.append('action', 'guardar_rubrica');
    fd.append('id_fase', idFase);
    fd.append('criterios', JSON.stringify(leerRubrica()));
    const res = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();
    show(data.message || '', data.status === 'ok');
    if (data.status === 'ok') cargar();
  }

  document.getElementById('btn-gp-cargar').onclick = cargar;
  document.getElementById('btn-gp-guardar').onclick = guardar;
  cargar();
})();
</script>
