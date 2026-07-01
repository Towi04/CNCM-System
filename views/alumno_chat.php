<?php
require_once __DIR__ . '/../config.php';
if (!alumno_portal_puede_ver()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idAlumno = alumno_portal_id_o_detener();
if ($idAlumno <= 0) {
    return;
}

$idPlantel = plantel_scope_id($pdo);
$salas = alumno_portal_ensure_chat_salas($pdo, $idPlantel, $idAlumno);
$idSalaIni = (int) ($salas[0]['id_sala'] ?? 0);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-comments"></i> Mensajes</h2>
    <p style="color:#666;">Chat con tu grupo, recepción y coordinación.</p>
  </div>

  <button type="button" class="secondary" style="margin-bottom:12px;" onclick="cargarSeccion('alumno_portal_inicio')">← Inicio</button>

  <?php if (empty($salas)): ?>
    <p style="color:#888;">No hay salas de chat disponibles. Cuando esté inscrito en un grupo podrá escribir aquí.</p>
  <?php else: ?>
  <div class="ap-chat-layout">
    <div class="ap-chat-salas" id="ap-chat-salas">
      <?php foreach ($salas as $i => $s): ?>
        <button type="button" class="ap-chat-sala-btn<?php echo $i === 0 ? ' active' : ''; ?>"
          data-id="<?php echo (int) $s['id_sala']; ?>">
          <?php echo htmlspecialchars($s['nombre'] ?? ''); ?>
          <br><small style="color:#666;"><?php echo htmlspecialchars($s['tipo'] ?? ''); ?></small>
        </button>
      <?php endforeach; ?>
    </div>
    <div class="ap-chat-panel">
      <div class="ap-chat-msgs" id="ap-chat-msgs"></div>
      <form class="ap-chat-form" id="ap-chat-form">
        <input type="text" id="ap-chat-input" placeholder="Escriba su mensaje…" maxlength="2000" autocomplete="off">
        <button type="submit" class="primary">Enviar</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  const api = 'php/alumno_portal_api.php';
  let salaActiva = <?php echo (int) $idSalaIni; ?>;
  const msgsEl = document.getElementById('ap-chat-msgs');
  const form = document.getElementById('ap-chat-form');
  const input = document.getElementById('ap-chat-input');
  let pollTimer = null;

  function renderMensajes(list) {
    if (!msgsEl) return;
    msgsEl.innerHTML = '';
    if (!list || !list.length) {
      msgsEl.innerHTML = '<p style="color:#888;text-align:center;">Sin mensajes aún. Escriba el primero.</p>';
      return;
    }
    list.forEach((m) => {
      const div = document.createElement('div');
      div.className = 'ap-chat-msg' + (m.id_alumno ? ' mine' : '');
      const hora = m.creado_en ? new Date(m.creado_en.replace(' ', 'T')).toLocaleString() : '';
      div.innerHTML = '<div class="ap-chat-msg-bubble"><strong>' + escapeHtml(m.autor_nombre || '') + '</strong>'
        + '<br>' + escapeHtml(m.mensaje || '')
        + '<br><small style="color:#888;">' + escapeHtml(hora) + '</small></div>';
      msgsEl.appendChild(div);
    });
    msgsEl.scrollTop = msgsEl.scrollHeight;
  }

  function escapeHtml(t) {
    return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  async function cargarMensajes() {
    if (!salaActiva) return;
    try {
      const res = await fetch(api + '?action=mensajes&id_sala=' + salaActiva, { credentials: 'same-origin' });
      const data = await res.json();
      if (data.status === 'ok') renderMensajes(data.mensajes);
    } catch (e) {}
  }

  document.querySelectorAll('.ap-chat-sala-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.ap-chat-sala-btn').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      salaActiva = parseInt(btn.dataset.id, 10);
      cargarMensajes();
    });
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const txt = (input?.value || '').trim();
    if (!txt || !salaActiva) return;
    const fd = new FormData();
    fd.append('action', 'enviar');
    fd.append('id_sala', salaActiva);
    fd.append('mensaje', txt);
    try {
      const res = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (data.status === 'ok') {
        input.value = '';
        cargarMensajes();
      } else {
        alert(data.message || 'No se pudo enviar');
      }
    } catch (err) {
      alert('Error de conexión');
    }
  });

  if (salaActiva) {
    cargarMensajes();
    pollTimer = setInterval(cargarMensajes, 15000);
  }
  window.addEventListener('beforeunload', () => { if (pollTimer) clearInterval(pollTimer); });
})();
</script>
