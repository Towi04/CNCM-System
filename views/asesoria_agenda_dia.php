<?php
require_once __DIR__ . '/../config.php';
asesoria_ensure_schema($pdo);

if (!asesoria_puede_agendar()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$fecha = trim((string) ($_GET['fecha'] ?? date('Y-m-d')));
$api = hay_asset_url('php/asesoria_api.php');
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">

<div class="catalog-wrap">
  <h2><i class="fas fa-calendar-day"></i> Agenda de asesorías</h2>
  <p style="color:#666;">Confirme con alumnos y profesores; marque impartida, no presentado o cancelación a tiempo.</p>

  <div class="catalog-toolbar">
    <div class="field">
      <label>Fecha</label>
      <input type="date" id="ase-dia-fecha" value="<?php echo htmlspecialchars($fecha); ?>">
    </div>
    <button type="button" class="secondary" id="ase-dia-ayer">Ayer</button>
    <button type="button" class="secondary" id="ase-dia-hoy">Hoy</button>
    <button type="button" class="secondary" id="ase-dia-manana">Mañana</button>
    <button type="button" class="primary" id="ase-dia-cargar">Actualizar</button>
  </div>

  <div id="ase-dia-msg" class="catalog-alert" style="display:none;"></div>
  <div id="ase-dia-list"><p style="color:#888;">Cargando…</p></div>
</div>

<script>
(function () {
  const api = <?php echo json_encode($api, JSON_UNESCAPED_UNICODE); ?>;
  const list = document.getElementById('ase-dia-list');
  const msg = document.getElementById('ase-dia-msg');
  const inpFecha = document.getElementById('ase-dia-fecha');
  const estados = <?php echo json_encode(ASESORIA_ESTADOS, JSON_UNESCAPED_UNICODE); ?>;

  function showMsg(t, ok) {
    msg.style.display = t ? 'block' : 'none';
    msg.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    msg.textContent = t || '';
  }

  function shiftDay(n) {
    const d = new Date(inpFecha.value + 'T12:00:00');
    d.setDate(d.getDate() + n);
    inpFecha.value = d.toISOString().slice(0, 10);
    cargar();
  }

  async function cambiarEstado(idCita, estado) {
    if (estado === 'np' && !confirm('¿Marcar como no presentado? Se cobrará reagendar al alumno y pago reducido al profesor.')) return;
    if (estado === 'impartida' && !confirm('¿Confirmar que se impartió la asesoría?')) return;
    const fd = new FormData();
    fd.append('action', 'estado');
    fd.append('id_cita', String(idCita));
    fd.append('estado', estado);
    const r = await fetch(api, { method: 'POST', body: fd });
    const d = await r.json();
    showMsg(d.message || '', d.status === 'ok');
    if (d.status === 'ok') cargar();
  }

  async function cargar() {
    const f = inpFecha.value;
    list.innerHTML = '<p style="color:#888;">Cargando…</p>';
    const r = await fetch(api + '?action=listar&fecha=' + encodeURIComponent(f));
    const d = await r.json();
    if (d.status !== 'ok' || !d.items || !d.items.length) {
      list.innerHTML = '<p>Sin asesorías agendadas para esta fecha.</p>';
      return;
    }
    let html = '<div class="catalog-table-wrap"><table class="catalog-table"><thead><tr>' +
      '<th>Hora</th><th>Profesor</th><th>Tema</th><th>Alumnos</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
    d.items.forEach(c => {
      const al = (c.alumnos || []).map(a => a.alumno_nombre + (a.numero_control ? ' (' + a.numero_control + ')' : '')).join(', ');
      html += '<tr><td>' + String(c.hora_inicio).padStart(2,'0') + ':00</td><td>' + (c.profesor_nombre || '') + '</td>' +
        '<td>' + (c.tema || '') + '</td><td>' + al + '</td><td>' + (estados[c.estado] || c.estado) + '</td><td style="white-space:nowrap;">';
      if (['agendada','confirmada'].includes(c.estado)) {
        html += '<button type="button" class="secondary btn-ase-conf" data-id="' + c.id_cita + '" data-e="confirmada">Confirmar</button> ';
        html += '<button type="button" class="primary btn-ase-est" data-id="' + c.id_cita + '" data-e="impartida">Impartida</button> ';
        html += '<button type="button" class="secondary btn-ase-est" data-id="' + c.id_cita + '" data-e="np">NP</button> ';
        html += '<button type="button" class="secondary btn-ase-cancel" data-id="' + c.id_cita + '">Cancelar</button>';
      }
      html += '</td></tr>';
    });
    html += '</tbody></table></div>';
    list.innerHTML = html;
    list.querySelectorAll('.btn-ase-est, .btn-ase-conf').forEach(b => b.addEventListener('click', () => cambiarEstado(b.dataset.id, b.dataset.e)));
    list.querySelectorAll('.btn-ase-cancel').forEach(b => b.addEventListener('click', async () => {
      const fd = new FormData(); fd.append('action', 'estado'); fd.append('id_cita', b.dataset.id); fd.append('estado', 'cancelada_a_tiempo');
      fd.append('motivo', prompt('Motivo cancelación') || 'Cancelación a tiempo');
      const r = await fetch(api, { method: 'POST', body: fd }); const d = await r.json();
      showMsg(d.message, d.status === 'ok'); if (d.status === 'ok') cargar();
    }));
  }

  document.getElementById('ase-dia-cargar').addEventListener('click', cargar);
  document.getElementById('ase-dia-hoy').addEventListener('click', () => { inpFecha.value = new Date().toISOString().slice(0,10); cargar(); });
  document.getElementById('ase-dia-ayer').addEventListener('click', () => shiftDay(-1));
  document.getElementById('ase-dia-manana').addEventListener('click', () => shiftDay(1));
  inpFecha.addEventListener('change', cargar);
  cargar();
})();
</script>
