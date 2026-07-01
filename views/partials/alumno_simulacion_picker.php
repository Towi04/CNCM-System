<?php
/** Selector de alumno para vista simulada (supervisor / staff). */
$apiOperativo = function_exists('hay_asset_url') ? hay_asset_url('php/operativo_panel_api.php') : 'php/operativo_panel_api.php';
$apiPortal = function_exists('hay_asset_url') ? hay_asset_url('php/alumno_portal_api.php') : 'php/alumno_portal_api.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-eye"></i> Vista de alumno (capacitación)</h2>
    <p style="color:#666; max-width:640px;">
      Está viendo el portal como alumno. Elija un alumno del plantel para previsualizar calificaciones, libros, pagos y demás secciones.
    </p>
  </div>

  <div class="welcome-card" style="max-width:560px; padding:20px;">
    <label for="sim-alumno-q" style="display:block; font-weight:600; margin-bottom:8px;">Buscar alumno</label>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <input type="search" id="sim-alumno-q" placeholder="Número de control o nombre" style="flex:1; min-width:200px; padding:10px 12px; border:1px solid #ccc; border-radius:6px;">
      <button type="button" class="primary" id="sim-alumno-buscar"><i class="fas fa-search"></i> Buscar</button>
    </div>
    <div id="sim-alumno-resultados" style="margin-top:12px;"></div>
    <p id="sim-alumno-msg" style="margin-top:10px; font-size:0.9rem; color:#666;"></p>
  </div>
</div>

<script>
(function () {
  const apiOp = <?php echo json_encode($apiOperativo, JSON_UNESCAPED_UNICODE); ?>;
  const apiPortal = <?php echo json_encode($apiPortal, JSON_UNESCAPED_UNICODE); ?>;
  const qEl = document.getElementById('sim-alumno-q');
  const resEl = document.getElementById('sim-alumno-resultados');
  const msgEl = document.getElementById('sim-alumno-msg');

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  async function buscar() {
    const q = (qEl.value || '').trim();
    if (q.length < 2) {
      msgEl.textContent = 'Escriba al menos 2 caracteres.';
      return;
    }
    msgEl.textContent = 'Buscando…';
    resEl.innerHTML = '';
    try {
      const url = apiOp + '?accion=buscar_alumno&q=' + encodeURIComponent(q);
      const r = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
      const data = await r.json();
      if (data.status !== 'ok' || !data.alumno) {
        if (Array.isArray(data.sugerencias) && data.sugerencias.length) {
          resEl.innerHTML = data.sugerencias.map((s) =>
            '<button type="button" class="secondary sim-alumno-pick" style="display:block;width:100%;margin:6px 0;text-align:left;" data-id="' + esc(String(s.id_alumno || '')) + '">' +
            esc((s.nombre || s.nombre_completo || '') + ' · ' + (s.numero_control || s.control || '')) +
            '</button>'
          ).join('');
          resEl.querySelectorAll('.sim-alumno-pick').forEach((btn) => btn.addEventListener('click', elegir));
          msgEl.textContent = data.message || 'Elija un alumno:';
          return;
        }
        msgEl.textContent = data.message || 'No se encontró alumno.';
        return;
      }
      const a = data.alumno;
      const nombre = esc((a.nombre_completo || a.nombre || '').trim());
      const control = esc(a.numero_control || a.control || '');
      resEl.innerHTML =
        '<div class="welcome-card" style="padding:12px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">' +
          '<div><strong>' + nombre + '</strong><br><span style="color:#666;">Control ' + control + '</span></div>' +
          '<button type="button" class="primary" id="sim-alumno-elegir" data-id="' + esc(String(a.id_alumno || '')) + '">Ver portal de este alumno</button>' +
        '</div>';
      document.getElementById('sim-alumno-elegir').addEventListener('click', elegir);
      msgEl.textContent = '';
    } catch (e) {
      msgEl.textContent = 'Error de conexión.';
    }
  }

  async function elegir(ev) {
    const id = parseInt((ev.currentTarget.getAttribute('data-id') || ev.currentTarget.dataset.id || '0'), 10);
    if (!id) return;
    msgEl.textContent = 'Aplicando…';
    const fd = new FormData();
    fd.append('action', 'set_alumno_simulacion');
    fd.append('id_alumno', String(id));
    try {
      const r = await fetch(apiPortal, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await r.json();
      if (data.status === 'ok') {
        if (typeof cargarSeccion === 'function') {
          cargarSeccion('alumno_portal_inicio');
        } else {
          location.reload();
        }
        return;
      }
      msgEl.textContent = data.message || 'No se pudo seleccionar.';
    } catch (e) {
      msgEl.textContent = 'Error de conexión.';
    }
  }

  document.getElementById('sim-alumno-buscar').addEventListener('click', buscar);
  qEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      buscar();
    }
  });
})();
</script>
