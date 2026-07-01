<?php
require_once __DIR__ . '/../config.php';
if (!profesor_360_puede_gestionar()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}
$tipo = (string) ($_GET['tipo'] ?? 'showclass');
$rubricas = profesor_360_listar_rubricas($pdo, $tipo);
$api = hay_asset_url('php/profesor_360_api.php');
$tipos = [
    'showclass' => 'Clase muestra (candidatos)',
    '360_alumno' => '360 — Alumno',
    '360_coordinador' => '360 — Coordinador',
    '360_auto' => '360 — Auto-evaluación',
    '360_adjunto' => '360 — Profesor adjunto',
];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css')); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-list-alt"></i> Rúbricas de evaluación docente</h2>
    <button type="button" class="secondary" onclick="cargarSeccion('profesor_360_ciclos')">← Ciclos 360</button>
  </div>

  <div style="margin-bottom:14px;">
    <?php foreach ($tipos as $k => $label): ?>
    <button type="button" class="<?php echo $k === $tipo ? 'primary' : 'secondary'; ?>"
      onclick="cargarSeccion('docente_rubricas','tipo=<?php echo urlencode($k); ?>')"><?php echo htmlspecialchars($label); ?></button>
    <?php endforeach; ?>
  </div>

  <div id="msg-rub" class="catalog-alert" style="display:none;"></div>

  <?php foreach ($rubricas as $rub): ?>
  <section style="margin-bottom:20px;padding:14px;border:1px solid #eee;border-radius:10px;">
    <h3><?php echo htmlspecialchars($rub['nombre']); ?> (<?php echo htmlspecialchars($rub['clave']); ?>)</h3>
    <form class="form-rubrica" data-id="<?php echo (int) $rub['id_rubrica']; ?>">
      <input type="hidden" name="id_rubrica" value="<?php echo (int) $rub['id_rubrica']; ?>">
      <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($rub['tipo']); ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">
        <input name="clave" value="<?php echo htmlspecialchars($rub['clave']); ?>" placeholder="Clave área (INGLES, CONTABILIDAD…)">
        <input name="nombre" value="<?php echo htmlspecialchars($rub['nombre']); ?>" placeholder="Nombre rúbrica">
      </div>
      <table class="catalog-table" data-criterios>
        <thead><tr><th>Código</th><th>Criterio</th><th>Máx</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rub['criterios'] as $c): ?>
          <tr>
            <td><input name="codigo" value="<?php echo htmlspecialchars($c['codigo']); ?>" style="width:100%;"></td>
            <td><input name="nombre_c" value="<?php echo htmlspecialchars($c['nombre']); ?>" style="width:100%;"></td>
            <td><input name="maximo" type="number" value="<?php echo (int) $c['maximo']; ?>" min="1" max="100" style="width:70px;"></td>
            <td><button type="button" class="secondary btn-del-row">✕</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <button type="button" class="secondary btn-add-row" style="margin-top:8px;">+ Criterio</button>
      <button type="submit" class="primary" style="margin-top:8px;">Guardar rúbrica</button>
    </form>
  </section>
  <?php endforeach; ?>

  <?php if ($tipo === 'showclass'): ?>
  <p style="font-size:0.85rem;color:#666;">Para inglés puede importar la matriz completa desde <strong>Configurar HAY → Import Profesor Inglés</strong> y crear rúbricas adicionales por clave de especialidad.</p>
  <?php endif; ?>
</div>
<script>
(function(){
  const api = <?php echo json_encode($api); ?>;
  const msg = document.getElementById('msg-rub');
  function show(t,ok){ msg.style.display='block'; msg.className='catalog-alert '+(ok?'catalog-alert--ok':'catalog-alert--error'); msg.textContent=t; }
  document.querySelectorAll('.btn-add-row').forEach(b=>b.addEventListener('click',()=>{
    const tb = b.closest('section').querySelector('[data-criterios] tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = '<td><input name="codigo" style="width:100%"></td><td><input name="nombre_c" style="width:100%"></td><td><input name="maximo" type="number" value="10" min="1" style="width:70px"></td><td><button type="button" class="secondary btn-del-row">✕</button></td>';
    tb.appendChild(tr);
    tr.querySelector('.btn-del-row').addEventListener('click',()=>tr.remove());
  }));
  document.querySelectorAll('.btn-del-row').forEach(b=>b.addEventListener('click',()=>b.closest('tr').remove()));
  document.querySelectorAll('.form-rubrica').forEach(f=>f.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const criterios = [];
    f.querySelectorAll('[data-criterios] tbody tr').forEach(tr=>{
      criterios.push({
        codigo: tr.querySelector('[name=codigo]')?.value||'',
        nombre: tr.querySelector('[name=nombre_c]')?.value||'',
        maximo: parseInt(tr.querySelector('[name=maximo]')?.value||10,10)
      });
    });
    const fd = new FormData();
    fd.append('action','guardar_rubrica');
    fd.append('id_rubrica', f.querySelector('[name=id_rubrica]').value);
    fd.append('tipo', f.querySelector('[name=tipo]').value);
    fd.append('clave', f.querySelector('[name=clave]').value);
    fd.append('nombre', f.querySelector('[name=nombre]').value);
    fd.append('criterios', JSON.stringify(criterios));
    const r = await fetch(api,{method:'POST',body:fd,credentials:'same-origin'});
    const d = await r.json(); show(d.message,d.status==='ok');
  }));
})();
</script>
