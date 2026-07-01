<?php
require_once __DIR__ . '/../config.php';
if (!academico_alumno_portal_puede()) {
    echo '<div class="alert">Sin permiso.</div>';
    return;
}

$idPlantel = plantel_scope_id($pdo);
$grupos = academico_alumno_portal_grupos($pdo, $idPlantel);
$puedePlantel = function_exists('rbac_cap') && rbac_cap('menu_academico');
$tab = trim($_GET['tab'] ?? 'avisos');
$avisos = alumno_aviso_listar_staff($pdo, $idPlantel);
$salas = academico_alumno_portal_chat_salas($pdo, $idPlantel);
$idSalaIni = (int) ($salas[0]['id_sala'] ?? 0);
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/admin_catalogo.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-bullhorn"></i> Avisos y mensajes alumnos</h2>
    <p style="color:#666;">Publique avisos en el portal del alumno y responda en el chat.</p>
  </div>

  <div class="catalog-toolbar" style="margin-bottom:16px;">
    <button type="button" class="<?php echo $tab === 'avisos' ? 'primary' : 'secondary'; ?>" onclick="cargarSeccion('academico_portal_alumno','tab=avisos')">Avisos</button>
    <button type="button" class="<?php echo $tab === 'chat' ? 'primary' : 'secondary'; ?>" onclick="cargarSeccion('academico_portal_alumno','tab=chat')">Mensajes</button>
  </div>

  <div id="acad-portal-alert" class="catalog-alert" style="display:none;"></div>

  <?php if ($tab === 'chat'): ?>
    <?php if (empty($salas)): ?>
      <p style="color:#888;">No hay salas de chat. Se crean al abrir esta pantalla o cuando un alumno escribe.</p>
    <?php else: ?>
    <div class="ap-chat-layout">
      <div class="ap-chat-salas" id="acad-chat-salas">
        <?php foreach ($salas as $i => $s): ?>
          <button type="button" class="ap-chat-sala-btn<?php echo $i === 0 ? ' active' : ''; ?>"
            data-id="<?php echo (int) $s['id_sala']; ?>">
            <?php echo htmlspecialchars($s['nombre'] ?? ''); ?>
            <?php if (!empty($s['grupo_clave'])): ?>
              <br><small><?php echo htmlspecialchars($s['grupo_clave']); ?></small>
            <?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="ap-chat-panel">
        <div class="ap-chat-msgs" id="acad-chat-msgs"></div>
        <form class="ap-chat-form" id="acad-chat-form">
          <input type="text" id="acad-chat-input" placeholder="Responder al alumno…" maxlength="2000" autocomplete="off">
          <button type="submit" class="primary">Enviar</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  <?php else: ?>

  <div class="welcome-card" style="padding:16px; margin-bottom:16px;">
    <h3 style="margin:0 0 12px;">Nuevo aviso</h3>
    <form id="form-aviso" style="display:grid; gap:10px; max-width:640px;">
      <input type="hidden" name="id_aviso" id="aviso-id" value="0">
      <label>Título <input type="text" name="titulo" id="aviso-titulo" required maxlength="160" style="width:100%;"></label>
      <label>Mensaje <textarea name="mensaje" id="aviso-mensaje" required rows="4" style="width:100%;"></textarea></label>
      <label>Grupo (vacío = todo el plantel)
        <select name="id_grupo" id="aviso-grupo" style="width:100%;" <?php echo $puedePlantel ? '' : ''; ?>>
          <?php if ($puedePlantel): ?><option value="">Todo el plantel</option><?php endif; ?>
          <?php foreach ($grupos as $g): ?>
            <option value="<?php echo (int) $g['id_grupo']; ?>">
              <?php echo htmlspecialchars(($g['clave'] ?? '') . ' — ' . ($g['esp_nombre'] ?? '')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php if (!$puedePlantel && empty($grupos)): ?>
        <p style="color:#c62828;">No tiene grupos asignados para publicar avisos.</p>
      <?php endif; ?>
      <label>Vigente hasta (opcional) <input type="date" name="vigente_hasta" id="aviso-vigente" style="width:100%;"></label>
      <label><input type="checkbox" name="activo" id="aviso-activo" value="1" checked> Activo</label>
      <div>
        <button type="submit" class="primary">Publicar aviso</button>
        <button type="button" class="secondary" id="aviso-limpiar">Limpiar</button>
      </div>
    </form>
  </div>

  <div class="catalog-table-wrap">
    <table class="catalog-table">
      <thead>
        <tr>
          <th>Título</th>
          <th>Alcance</th>
          <th>Vigencia</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($avisos)): ?>
          <tr><td colspan="5" style="color:#888;">Sin avisos publicados.</td></tr>
        <?php else: foreach ($avisos as $a): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($a['titulo'] ?? ''); ?></strong></td>
            <td><?php echo !empty($a['id_grupo']) ? htmlspecialchars($a['grupo_clave'] ?? 'Grupo') : 'Todo el plantel'; ?></td>
            <td><?php echo !empty($a['vigente_hasta']) ? date('d/m/Y', strtotime($a['vigente_hasta'])) : '—'; ?></td>
            <td><?php echo (int) ($a['activo'] ?? 0) ? 'Activo' : 'Inactivo'; ?></td>
            <td>
              <button type="button" class="secondary btn-edit-aviso"
                data-id="<?php echo (int) $a['id_aviso']; ?>"
                data-titulo="<?php echo htmlspecialchars($a['titulo'] ?? '', ENT_QUOTES); ?>"
                data-mensaje="<?php echo htmlspecialchars($a['mensaje'] ?? '', ENT_QUOTES); ?>"
                data-grupo="<?php echo (int) ($a['id_grupo'] ?? 0); ?>"
                data-vigente="<?php echo htmlspecialchars($a['vigente_hasta'] ?? ''); ?>"
                data-activo="<?php echo (int) ($a['activo'] ?? 0); ?>">Editar</button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  const api = 'php/academico_alumno_portal_api.php';
  const alertEl = document.getElementById('acad-portal-alert');

  function showAlert(msg, ok) {
    if (!alertEl) return;
    alertEl.textContent = msg;
    alertEl.className = 'catalog-alert ' + (ok ? 'catalog-alert--ok' : 'catalog-alert--error');
    alertEl.style.display = 'block';
    setTimeout(() => { alertEl.style.display = 'none'; }, 4000);
  }

  const formAviso = document.getElementById('form-aviso');
  formAviso?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(formAviso);
    fd.append('action', 'aviso_guardar');
    if (!document.getElementById('aviso-activo')?.checked) fd.set('activo', '0');
    try {
      const res = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (data.status === 'ok') {
        showAlert('Aviso guardado', true);
        cargarSeccion('academico_portal_alumno', 'tab=avisos');
      } else {
        showAlert(data.message || 'Error', false);
      }
    } catch (err) {
      showAlert('Error de conexión', false);
    }
  });

  document.getElementById('aviso-limpiar')?.addEventListener('click', () => {
    document.getElementById('aviso-id').value = '0';
    formAviso?.reset();
    document.getElementById('aviso-activo').checked = true;
  });

  document.querySelectorAll('.btn-edit-aviso').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.getElementById('aviso-id').value = btn.dataset.id || '0';
      document.getElementById('aviso-titulo').value = btn.dataset.titulo || '';
      document.getElementById('aviso-mensaje').value = btn.dataset.mensaje || '';
      const g = btn.dataset.grupo || '';
      const sel = document.getElementById('aviso-grupo');
      if (sel) sel.value = g === '0' ? '' : g;
      document.getElementById('aviso-vigente').value = btn.dataset.vigente || '';
      document.getElementById('aviso-activo').checked = btn.dataset.activo === '1';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  let salaActiva = <?php echo (int) $idSalaIni; ?>;
  const msgsEl = document.getElementById('acad-chat-msgs');
  const chatForm = document.getElementById('acad-chat-form');
  const chatInput = document.getElementById('acad-chat-input');
  let pollTimer = null;

  function escapeHtml(t) {
    return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function renderMensajes(list) {
    if (!msgsEl) return;
    msgsEl.innerHTML = '';
    if (!list || !list.length) {
      msgsEl.innerHTML = '<p style="color:#888;text-align:center;">Sin mensajes.</p>';
      return;
    }
    list.forEach((m) => {
      const div = document.createElement('div');
      const esStaff = !!m.id_usuario;
      div.className = 'ap-chat-msg' + (esStaff ? ' mine' : '');
      const hora = m.creado_en ? new Date(m.creado_en.replace(' ', 'T')).toLocaleString() : '';
      div.innerHTML = '<div class="ap-chat-msg-bubble"><strong>' + escapeHtml(m.autor_nombre || '') + '</strong>'
        + '<br>' + escapeHtml(m.mensaje || '')
        + '<br><small style="color:#888;">' + escapeHtml(hora) + '</small></div>';
      msgsEl.appendChild(div);
    });
    msgsEl.scrollTop = msgsEl.scrollHeight;
  }

  async function cargarMensajes() {
    if (!salaActiva) return;
    try {
      const res = await fetch(api + '?action=mensajes&id_sala=' + salaActiva, { credentials: 'same-origin' });
      const data = await res.json();
      if (data.status === 'ok') renderMensajes(data.mensajes);
    } catch (e) {}
  }

  document.querySelectorAll('#acad-chat-salas .ap-chat-sala-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#acad-chat-salas .ap-chat-sala-btn').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      salaActiva = parseInt(btn.dataset.id, 10);
      cargarMensajes();
    });
  });

  chatForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const txt = (chatInput?.value || '').trim();
    if (!txt || !salaActiva) return;
    const fd = new FormData();
    fd.append('action', 'enviar');
    fd.append('id_sala', salaActiva);
    fd.append('mensaje', txt);
    try {
      const res = await fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (data.status === 'ok') {
        chatInput.value = '';
        cargarMensajes();
      } else {
        alert(data.message || 'No se pudo enviar');
      }
    } catch (err) {
      alert('Error de conexión');
    }
  });

  if (salaActiva && msgsEl) {
    cargarMensajes();
    pollTimer = setInterval(cargarMensajes, 15000);
    window.addEventListener('beforeunload', () => { if (pollTimer) clearInterval(pollTimer); });
  }
})();
</script>
