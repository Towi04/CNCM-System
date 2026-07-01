<?php
require_once __DIR__ . '/../config.php';
asesoria_ensure_schema($pdo);

if (!asesoria_puede_administrar()) {
    echo '<div class="catalog-alert catalog-alert--error">Sin permiso para editar tabulador.</div>';
    return;
}

$api = hay_asset_url('php/asesoria_api.php');
?>
<link rel="stylesheet" href="css/admin_catalogo.css">

<div class="catalog-wrap">
  <h2><i class="fas fa-sliders-h"></i> Tabulador de asesorías</h2>
  <p style="color:#666;">Montos cobrados a alumnos y pagados a profesores por sesión de 1 hora.</p>
  <div id="ase-tab-msg" class="catalog-alert" style="display:none;"></div>
  <div class="catalog-table-wrap" style="margin-top:16px;">
    <table class="catalog-table" id="ase-tab-table">
      <thead><tr><th>Clave</th><th>Concepto</th><th>Cobro alumno</th><th>Pago profesor</th><th></th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
(function () {
  const api = <?php echo json_encode($api, JSON_UNESCAPED_UNICODE); ?>;
  const tbody = document.querySelector('#ase-tab-table tbody');
  const msg = document.getElementById('ase-tab-msg');

  async function cargar() {
    const r = await fetch(api + '?action=tabulador_listar');
    const d = await r.json();
    tbody.innerHTML = (d.items || []).map(row => `
      <tr data-id="${row.id_tabulador}">
        <td><code>${row.clave}</code></td>
        <td><input type="text" class="tab-nombre" value="${row.nombre||''}" style="width:100%;padding:6px;"></td>
        <td><input type="number" step="0.01" class="tab-mal" value="${row.monto_alumno||0}" style="width:100px;padding:6px;"></td>
        <td><input type="number" step="0.01" class="tab-mpr" value="${row.monto_profesor||0}" style="width:100px;padding:6px;"></td>
        <td><button type="button" class="primary btn-guardar-tab">Guardar</button></td>
      </tr>`).join('');
    tbody.querySelectorAll('.btn-guardar-tab').forEach(btn => btn.addEventListener('click', guardarFila));
  }

  async function guardarFila(e) {
    const tr = e.target.closest('tr');
    const fd = new FormData();
    fd.append('action', 'tabulador_guardar');
    fd.append('id_tabulador', tr.dataset.id);
    fd.append('clave', tr.querySelector('code').textContent);
    fd.append('nombre', tr.querySelector('.tab-nombre').value);
    fd.append('monto_alumno', tr.querySelector('.tab-mal').value);
    fd.append('monto_profesor', tr.querySelector('.tab-mpr').value);
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    msg.style.display = 'block';
    msg.className = 'catalog-alert ' + (d.status === 'ok' ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = d.message || '';
  }

  cargar();
})();
</script>
