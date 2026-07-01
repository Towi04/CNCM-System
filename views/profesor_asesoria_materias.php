<?php
require_once __DIR__ . '/../config.php';
asesoria_ensure_schema($pdo);

$idUsuario = (int) ($_GET['id_usuario'] ?? 0);
if ($idUsuario <= 0) {
    echo '<div class="alert">Indique id_usuario en la URL.</div>';
    return;
}
if (!asesoria_puede_administrar() && !asesoria_puede_agendar()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$st = $pdo->prepare('SELECT id_usuario, nombre, apellido, rol FROM usuarios WHERE id_usuario = ? LIMIT 1');
$st->execute([$idUsuario]);
$u = $st->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    echo '<div class="alert">Usuario no encontrado.</div>';
    return;
}

$materiasPrep = GRUPO_MATERIAS_PREP;
$items = profesor_asesoria_materia_listar($pdo, $idUsuario, $idPlantel);
$api = hay_asset_url('php/asesoria_api.php');
?>
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">
  <h2>Materias de asesoría — <?php echo htmlspecialchars(trim($u['nombre'] . ' ' . $u['apellido'])); ?></h2>
  <p style="color:#666;">Defina qué materias y niveles puede impartir en asesorías.</p>
  <div id="pam-msg" class="catalog-alert" style="display:none;"></div>
  <div id="pam-rows"></div>
  <button type="button" class="secondary" id="pam-add" style="margin-top:10px;">+ Materia</button>
  <button type="button" class="primary" id="pam-save" style="margin-top:10px;">Guardar</button>
</div>

<script>
(function () {
  const api = <?php echo json_encode($api, JSON_UNESCAPED_UNICODE); ?>;
  const idU = <?php echo $idUsuario; ?>;
  const materias = <?php echo json_encode($materiasPrep, JSON_UNESCAPED_UNICODE); ?>;
  let rows = <?php echo json_encode($items, JSON_UNESCAPED_UNICODE); ?>;
  const box = document.getElementById('pam-rows');

  function render() {
    box.innerHTML = rows.map((r, i) => {
      const opts = Object.entries(materias).map(([k,v]) =>
        '<option value="' + k + '"' + (r.materia_clave === k ? ' selected' : '') + '>' + v + '</option>'
      ).join('');
      return `<div style="display:grid;grid-template-columns:1fr 120px 100px auto;gap:8px;margin-bottom:8px;" data-i="${i}">
        <select class="pam-clave">${opts}</select>
        <select class="pam-nivel"><option value="general">General</option><option value="kids"${r.nivel==='kids'?' selected':''}>Kids</option><option value="adulto"${r.nivel==='adulto'?' selected':''}>Adulto</option></select>
        <label><input type="checkbox" class="pam-dual" ${r.puede_kids_dual==1?'checked':''}> Kids dual</label>
        <button type="button" class="secondary pam-del">Quitar</button></div>`;
    }).join('');
    box.querySelectorAll('.pam-del').forEach(b => b.addEventListener('click', () => {
      rows.splice(+b.closest('[data-i]').dataset.i, 1); render();
    }));
  }

  document.getElementById('pam-add').addEventListener('click', () => {
    rows.push({ materia_clave: 'matematicas', nivel: 'general', puede_kids_dual: 0 });
    render();
  });

  document.getElementById('pam-save').addEventListener('click', async () => {
    const items = [...box.querySelectorAll('[data-i]')].map(el => ({
      materia_clave: el.querySelector('.pam-clave').value,
      materia_nombre: el.querySelector('.pam-clave').selectedOptions[0]?.text || '',
      nivel: el.querySelector('.pam-nivel').value,
      puede_kids_dual: el.querySelector('.pam-dual').checked ? 1 : 0,
    }));
    const fd = new FormData();
    fd.append('action', 'materias_guardar');
    fd.append('id_usuario', String(idU));
    fd.append('items', JSON.stringify(items));
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    const m = document.getElementById('pam-msg');
    m.style.display = 'block';
    m.className = 'catalog-alert ' + (d.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');
    m.textContent = d.message || '';
  });

  render();
})();
</script>
