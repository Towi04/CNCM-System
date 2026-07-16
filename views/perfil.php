<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert">Sesión no válida.</div>';
    return;
}

global $pdo;
$stmt = $pdo->prepare(
    'SELECT id_usuario, nombre, apellido, username, email, rol, departamento, avatar, fecha_creacion, codigo_huella
     FROM usuarios WHERE id_usuario = ? LIMIT 1'
);
$stmt->execute([(int) $_SESSION['user_id']]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    echo '<div class="alert">No se encontró su usuario.</div>';
    return;
}

$iniciales = user_avatar_iniciales($u);
$avatarSrc = user_avatar_public_url($u['avatar'] ?? null);
$tieneFoto = $avatarSrc !== null;
$avatarUploadUrl = hay_asset_url('php/perfil_avatar.php');

$rolReal = rbac_rol_real();
$rolEfectivo = rbac_rol_efectivo();
$simulando = rbac_esta_simulando_rol();
$puedeSimular = rbac_puede_simular_rol();
$rolesSimular = rbac_roles_para_simular();
$labelsRol = rbac_roles_etiquetas();
?>
<link rel="stylesheet" href="css/resultados.css">

<div class="result-container">
  <div class="result-header">
    <h2><i class="fas fa-user-circle"></i> Mi perfil</h2>
  </div>

  <div class="welcome-card perfil-card">
    <div class="perfil-avatar-block">
      <div class="perfil-avatar-preview<?php echo $tieneFoto ? ' has-photo' : ''; ?>" id="perfil-avatar-preview">
        <span class="perfil-avatar-iniciales" id="perfil-iniciales"><?php echo htmlspecialchars($iniciales); ?></span>
        <?php if ($tieneFoto): ?>
          <img
            src="<?php echo htmlspecialchars($avatarSrc); ?>?t=<?php echo time(); ?>"
            alt="Foto de perfil"
            class="perfil-avatar-img"
            id="perfil-avatar-img"
          >
        <?php endif; ?>
      </div>

      <div class="perfil-avatar-actions">
        <form
          id="form-avatar-upload"
          action="<?php echo htmlspecialchars($avatarUploadUrl, ENT_QUOTES, 'UTF-8'); ?>"
          method="POST"
          enctype="multipart/form-data"
          data-no-global-ajax
        >
          <label class="btn-guardar perfil-btn-upload">
            <i class="fas fa-camera"></i> Subir foto
            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
          </label>
        </form>

        <?php if ($tieneFoto): ?>
          <form id="form-avatar-remove" action="php/perfil_avatar.php" method="POST" data-no-global-ajax>
            <input type="hidden" name="action" value="remove">
            <button type="submit" class="perfil-btn-remove" id="btn-avatar-remove">
              <i class="fas fa-trash-alt"></i> Quitar foto
            </button>
          </form>
        <?php else: ?>
          <form id="form-avatar-remove" action="php/perfil_avatar.php" method="POST" data-no-global-ajax style="display:none;">
            <input type="hidden" name="action" value="remove">
            <button type="submit" class="perfil-btn-remove" id="btn-avatar-remove">
              <i class="fas fa-trash-alt"></i> Quitar foto
            </button>
          </form>
        <?php endif; ?>
      </div>

      <p class="perfil-avatar-hint">JPG, PNG, WebP o GIF. Máximo 2 MB. Sin foto se muestran sus iniciales.</p>
      <div id="respuesta-avatar" class="perfil-avatar-msg" style="display:none;" role="status"></div>
    </div>

    <div class="perfil-datos">
      <p style="margin:0 0 16px; color:#666;">Datos de su cuenta en el sistema HAY.</p>
      <table class="perfil-tabla">
        <tr><td>Nombre</td><td><strong><?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?></strong></td></tr>
        <tr><td>Usuario</td><td><?php echo htmlspecialchars($u['username']); ?></td></tr>
        <?php if (!empty($u['email'])): ?>
        <tr><td>Correo</td><td><?php echo htmlspecialchars($u['email']); ?></td></tr>
        <?php endif; ?>
        <tr><td>Rol en sistema</td><td><strong><?php echo htmlspecialchars(rbac_etiqueta_rol($u['rol'] ?? '')); ?></strong> <code style="font-size:12px;"><?php echo htmlspecialchars($u['rol'] ?? ''); ?></code></td></tr>
        <?php if (rbac_esta_simulando_rol()): ?>
        <tr><td>Vista actual</td><td><span style="color:#b45309;"><?php echo htmlspecialchars(rbac_etiqueta_rol()); ?> (simulada)</span></td></tr>
        <?php endif; ?>
        <tr><td>Departamento</td><td><?php echo htmlspecialchars($u['departamento'] ?? '—'); ?></td></tr>
        <?php if (!empty($u['fecha_creacion'])): ?>
        <tr><td>Registro</td><td><?php echo htmlspecialchars($u['fecha_creacion']); ?></td></tr>
        <?php endif; ?>
      </table>

      <div class="perfil-tour-block" style="margin-top:20px; padding:14px; background:#f5f5f5; border-radius:10px;">
        <h3 style="margin:0 0 8px; font-size:1rem;"><i class="fas fa-route"></i> Asistente guiado</h3>
        <p style="margin:0 0 10px; font-size:0.88rem; color:#555;">
          Vuelve a ver los tours de ayuda en cada pantalla (Inicio, panel gerente, portal alumno, etc.).
        </p>
        <button type="button" class="btn-guardar" id="btn-tour-reiniciar">Reactivar tours</button>
        <span id="tour-reiniciar-msg" style="margin-left:10px; font-size:0.88rem;"></span>
      </div>

      <?php if (($u['rol'] ?? '') !== 'alumno'): ?>
      <div class="perfil-huella-block" style="margin-top:20px; padding:14px; background:#f0f7ff; border-radius:10px; border:1px solid #bbdefb;">
        <h3 style="margin:0 0 8px; font-size:1rem;"><i class="fas fa-fingerprint"></i> Mi PIN en el lector</h3>
        <p style="margin:0 0 10px; font-size:0.88rem; color:#555;">
          Para checar entrada en el lector del plantel (no desde su celular). Debe coincidir con el ID al enrolar su huella en el dispositivo.
        </p>
        <form id="form-perfil-huella" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
          <input
            type="text"
            name="codigo_huella"
            value="<?php echo htmlspecialchars($u['codigo_huella'] ?? ''); ?>"
            inputmode="numeric"
            maxlength="40"
            placeholder="PIN del lector"
            style="padding:10px; border:1px solid #ddd; border-radius:8px; flex:1; min-width:140px;"
          >
          <button type="submit" class="btn-guardar">Guardar PIN</button>
        </form>
        <div id="perfil-huella-msg" class="perfil-avatar-msg" style="display:none;" role="status"></div>
      </div>
      <?php endif; ?>
      <?php if ($puedeSimular || $simulando): ?>
      <div class="perfil-rol-block" id="perfil-rol-block">
        <h3 style="margin:24px 0 10px; font-size:1.05rem;"><i class="fas fa-eye"></i> Ver el sistema como otro rol</h3>
        <p style="margin:0 0 12px; color:#666; font-size:14px;">
          Para capacitar o explicar a otras áreas sin cambiar su cuenta. Su rol real sigue siendo
          <strong><?php echo htmlspecialchars(rbac_etiqueta_rol(rbac_rol_real())); ?></strong>.
        </p>
        <div class="perfil-rol-actions">
          <?php if ($rolesSimular !== []): ?>
          <select id="perfil-rol-select" class="perfil-rol-select" aria-label="Seleccionar rol de vista">
            <?php foreach ($rolesSimular as $r): ?>
              <?php if ($r === rbac_rol_real()) { continue; } ?>
              <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $r === rbac_rol_efectivo() ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars(rbac_etiqueta_rol($r)); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn-guardar" id="perfil-rol-aplicar">Aplicar vista</button>
          <?php endif; ?>
          <?php if (rbac_esta_simulando_rol()): ?>
          <button type="button" class="perfil-btn-remove" id="perfil-rol-restaurar">Volver a mi rol</button>
          <?php endif; ?>
        </div>
        <div id="perfil-rol-msg" class="perfil-avatar-msg" style="display:none;" role="status"></div>
      </div>
      <?php endif; ?>

      <p style="margin-top:20px;">
        <button type="button" class="btn-guardar" onclick="cargarSeccion('cambiar_password')">
          <i class="fas fa-key"></i> Cambiar contraseña
        </button>
      </p>
    </div>
  </div>
</div>

<style>
.perfil-card {
  text-align: left;
  max-width: 720px;
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 28px;
  align-items: start;
}
@media (max-width: 640px) {
  .perfil-card { grid-template-columns: 1fr; }
}
.perfil-avatar-block { text-align: center; }
.perfil-avatar-preview {
  position: relative;
  width: 120px;
  height: 120px;
  margin: 0 auto 14px;
  border-radius: 50%;
  background: #5a6a82;
  overflow: hidden;
  border: 3px solid var(--azul, #11458b);
}
.perfil-avatar-iniciales {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: 700;
  color: #fff;
  z-index: 0;
}
.perfil-avatar-preview.has-photo .perfil-avatar-iniciales {
  display: none;
}

.perfil-avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: 1;
}
.perfil-avatar-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: stretch;
}
.perfil-btn-upload {
  cursor: pointer;
  margin: 0;
  text-align: center;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.perfil-btn-upload input { display: none; }
.perfil-btn-remove {
  padding: 10px 14px;
  border: 1px solid #e0b4b4;
  background: #fff5f5;
  color: #b91c2c;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
}
.perfil-btn-remove:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.perfil-btn-remove:not(:disabled):hover { background: #ffe8e8; }
.perfil-avatar-hint {
  margin: 10px 0 0;
  font-size: 12px;
  color: #888;
  max-width: 200px;
}
.perfil-avatar-msg {
  margin-top: 10px;
  padding: 8px 10px;
  border-radius: 6px;
  font-size: 13px;
}
.perfil-avatar-msg.ok { background: #e8f5e9; color: #2e7d32; }
.perfil-avatar-msg.err { background: #ffebee; color: #c62828; }
.perfil-tabla { width: 100%; border-collapse: collapse; font-size: 15px; }
.perfil-tabla td { padding: 10px 0; vertical-align: top; }
.perfil-tabla td:first-child { color: #666; width: 38%; }
.perfil-rol-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}
.perfil-rol-select {
  flex: 1 1 200px;
  min-width: 180px;
  padding: 10px 12px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
}
</style>

<script>
(function () {
  const msg = document.getElementById('respuesta-avatar');
  const preview = document.getElementById('perfil-avatar-preview');
  const btnRemove = document.getElementById('btn-avatar-remove');

  function showMsg(text, ok) {
    if (!msg) return;
    msg.style.display = 'block';
    msg.className = 'perfil-avatar-msg ' + (ok ? 'ok' : 'err');
    msg.textContent = text;
  }

  function setPreview(url) {
    let img = document.getElementById('perfil-avatar-img');
    if (url) {
      const resolved = typeof window.hayResolveAssetUrl === 'function'
        ? window.hayResolveAssetUrl(url)
        : url;
      if (!img && preview) {
        img = document.createElement('img');
        img.id = 'perfil-avatar-img';
        img.className = 'perfil-avatar-img';
        img.alt = 'Foto de perfil';
        preview.appendChild(img);
      }
      img.src = resolved + (resolved.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
      img.style.display = '';
      preview?.classList.add('has-photo');
      if (btnRemove) {
        btnRemove.disabled = false;
        btnRemove.title = '';
        const formRm = btnRemove.closest('form');
        if (formRm) formRm.style.display = '';
      }
    } else if (img) {
      img.remove();
      preview?.classList.remove('has-photo');
      if (btnRemove) {
        btnRemove.disabled = true;
        btnRemove.title = 'No hay foto personalizada';
        const formRm = btnRemove.closest('form');
        if (formRm) formRm.style.display = 'none';
      }
    }
    if (typeof window.hayUpdateSidebarAvatar === 'function') {
      window.hayUpdateSidebarAvatar(url || null);
    }
  }

  const formUp = document.getElementById('form-avatar-upload');
  if (formUp) {
    const fileInput = formUp.querySelector('input[type="file"]');
    fileInput?.addEventListener('change', () => {
      if (fileInput.files && fileInput.files[0]) {
        formUp.requestSubmit();
      }
    });
    formUp.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(formUp);
      try {
        let data;
        if (typeof window.hayFetchJson === 'function') {
          const out = await window.hayFetchJson(formUp.action, { method: 'POST', body: fd });
          data = out.data;
        } else {
          const res = await fetch(formUp.action, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'fetch' },
          });
          data = await res.json();
        }
        const ok = data.status === 'ok';
        showMsg(data.message || (ok ? 'Foto actualizada' : 'No se pudo guardar la foto'), ok);
        if (ok) {
          setPreview(data.avatar_url || null);
          fileInput.value = '';
        }
      } catch (err) {
        showMsg(err.message || 'Error al subir la imagen', false);
      }
    });
  }

  const formRm = document.getElementById('form-avatar-remove');
  if (formRm) {
    formRm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!confirm('¿Quitar su foto de perfil y usar las iniciales?')) return;
      const fd = new FormData(formRm);
      try {
        const res = await fetch(formRm.action, {
          method: 'POST',
          body: fd,
          headers: { 'X-Requested-With': 'fetch' },
        });
        const data = await res.json();
        showMsg(data.message || '', data.status === 'ok');
        if (data.status === 'ok') {
          setPreview(null);
        }
      } catch (err) {
        showMsg('Error al quitar la foto', false);
      }
    });
  }
  const msgRol = document.getElementById('perfil-rol-msg');
  const btnAplicarRol = document.getElementById('perfil-rol-aplicar');
  const btnRestaurarRol = document.getElementById('perfil-rol-restaurar');
  const selRol = document.getElementById('perfil-rol-select');

  async function postRol(fd) {
    const res = await fetch('php/perfil_cambiar_rol.php', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'fetch' },
    });
    return res.json();
  }

  function showRolMsg(text, ok) {
    if (!msgRol) return;
    msgRol.style.display = 'block';
    msgRol.className = 'perfil-avatar-msg ' + (ok ? 'ok' : 'err');
    msgRol.textContent = text;
  }

  btnAplicarRol?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('rol', selRol?.value || '');
    try {
      const data = await postRol(fd);
      showRolMsg(data.message || '', data.status === 'ok');
      if (data.status === 'ok') setTimeout(() => location.reload(), 500);
    } catch (err) {
      showRolMsg('Error al cambiar la vista', false);
    }
  });

  btnRestaurarRol?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('accion', 'restaurar');
    try {
      const data = await postRol(fd);
      if (data.status === 'ok') location.reload();
      else showRolMsg(data.message || 'Error', false);
    } catch (err) {
      showRolMsg('Error de conexión', false);
    }
  });

  if (window.location.hash === '#rol') {
    document.getElementById('perfil-rol-block')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  document.getElementById('btn-tour-reiniciar')?.addEventListener('click', async function () {
    const msg = document.getElementById('tour-reiniciar-msg');
    if (typeof window.hayTourResetAll !== 'function') {
      if (msg) msg.textContent = 'Tour no disponible en esta sesión.';
      return;
    }
    try {
      const data = await window.hayTourResetAll();
      if (msg) {
        msg.textContent = data.message || (data.status === 'ok' ? 'Listo' : 'Error');
        msg.style.color = data.status === 'ok' ? '#2e7d32' : '#c62828';
      }
    } catch (e) {
      if (msg) { msg.textContent = 'Error de conexión'; msg.style.color = '#c62828'; }
    }
  });
})();
</script>
