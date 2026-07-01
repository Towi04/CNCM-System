<?php

require_once __DIR__ . '/../config.php';

$rolesForm = rbac_roles_para_formulario($pdo);
$listaPlanteles = plantel_list_accesibles($pdo, true);
$plantelActivo = plantel_scope_id($pdo);
$puedeElegirPlantel = count($listaPlanteles) > 1;

if (!$puedeElegirPlantel) {
    $listaPlanteles = array_values(array_filter(
        $listaPlanteles,
        static fn ($p) => (int) $p['id_plantel'] === $plantelActivo
    ));
}

$passInicial = function_exists('cuenta_password_inicial') ? cuenta_password_inicial() : 'Cncm*1234';
$sugerirUrl = htmlspecialchars(hay_asset_url('php/cuenta_email_sugerir.php'), ENT_QUOTES, 'UTF-8');
$dominio = function_exists('cuenta_dominio_email') ? cuenta_dominio_email() : INSTITUTIONAL_EMAIL_DOMAIN;

?>
<style>
.registro-google-card {
  grid-column: 1 / -1;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid #c5cae9;
  border-radius: 10px;
  background: linear-gradient(135deg, #f8f9ff 0%, #eef2ff 100%);
  cursor: pointer;
  user-select: none;
}
.registro-google-card input[type="checkbox"] {
  width: 18px;
  height: 18px;
  margin-top: 2px;
  flex-shrink: 0;
  cursor: pointer;
}
.registro-google-card__body { flex: 1; min-width: 0; }
.registro-google-card__title {
  font-weight: 600;
  color: #1a237e;
  margin: 0 0 4px;
  line-height: 1.35;
}
.registro-google-card__hint {
  font-size: 0.85rem;
  color: #555;
  margin: 0;
  line-height: 1.45;
}
.registro-cuenta-preview {
  grid-column: 1 / -1;
  padding: 12px 14px;
  border-radius: 8px;
  background: #f5f5f5;
  border: 1px solid #e0e0e0;
  font-size: 0.9rem;
}
.registro-cuenta-preview strong { color: #333; }
.registro-cuenta-manual { display: none; }
.registro-cuenta-manual.is-visible { display: block; }
</style>

<div class="card">
    <h2><i class="fas fa-user-plus"></i> Registrar Nuevo Miembro del Personal</h2>
    <hr>

    <form id="formRegistroPersonal" class="grid-form" action="<?php echo htmlspecialchars(hay_asset_url('php/process_registro.php'), ENT_QUOTES, 'UTF-8'); ?>" method="POST">

        <div class="field">
            <label>Nombre(s)</label>
            <input type="text" name="nombre" id="registro-nombre" required>
        </div>
        <div class="field">
            <label>Apellido(s)</label>
            <input type="text" name="apellido" id="registro-apellido" required placeholder="Paterno Materno">
        </div>

        <label class="registro-google-card" for="registro-ya-google">
            <input type="checkbox" name="ya_tiene_google" id="registro-ya-google" value="1">
            <span class="registro-google-card__body">
                <p class="registro-google-card__title">Ya tiene cuenta en Google Workspace</p>
                <p class="registro-google-card__hint">Solo verificaremos el correo existente y crearemos acceso en HAY y Moodle (no se crea cuenta nueva en Google).</p>
            </span>
        </label>

        <div id="registro-cuenta-preview" class="registro-cuenta-preview">
            <div><strong>Correo:</strong> <span id="registro-preview-email">—</span></div>
            <div style="margin-top:4px;"><strong>Usuario HAY:</strong> <span id="registro-preview-user">—</span></div>
            <p style="font-size:0.82rem;color:#666;margin:8px 0 0;">Se genera automáticamente a partir del nombre (iniciales + apellido paterno).</p>
        </div>

        <div class="field full-width registro-cuenta-manual" id="registro-cuenta-manual-wrap">
            <label>Correo institucional existente</label>
            <input type="text" id="registro-cuenta-manual" autocomplete="off" placeholder="usuario@<?php echo htmlspecialchars($dominio, ENT_QUOTES, 'UTF-8'); ?>">
            <p style="font-size:0.85rem;color:#666;margin:6px 0 0;">Puede escribir el correo completo o solo la parte antes del @.</p>
        </div>

        <input type="hidden" name="email" id="registro-email" value="">
        <input type="hidden" name="username" id="registro-username" value="">

        <div class="field">
            <label>Contraseña inicial</label>
            <input type="text" name="password" id="registro-password" readonly value="<?php echo htmlspecialchars($passInicial, ENT_QUOTES, 'UTF-8'); ?>">
            <p style="font-size:0.85rem;color:#666;margin:6px 0 0;">HAY, Google y Moodle. Deberá cambiarla al primer acceso.</p>
        </div>

        <div class="field">
            <label>Rol</label>
            <?php if (empty($rolesForm)): ?>
            <p class="catalog-alert catalog-alert--error">No hay roles en la base de datos. Entre a <strong>Administración → Roles y permisos</strong>.</p>
            <?php else: ?>
            <select name="id_rol" id="registro-id-rol" required>
                <?php foreach ($rolesForm as $rf): ?>
                <option value="<?php echo (int) $rf['id_rol']; ?>" data-clave="<?php echo htmlspecialchars($rf['clave'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($rf['nombre']); ?>
                    <?php if (!empty($rf['clave'])): ?> (<?php echo htmlspecialchars($rf['clave']); ?>)<?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="rol" id="registro-rol-clave" value="<?php echo htmlspecialchars($rolesForm[0]['clave'] ?? 'profesor', ENT_QUOTES, 'UTF-8'); ?>">
            <p style="font-size:0.85rem;color:#666;margin:6px 0 0;">
              Solo elija el rol; los permisos se definen en <strong>Administración → Roles y permisos</strong>.
              Para ajustes puntuales de una persona use la pestaña <em>Permisos por persona</em>.
            </p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label>Plantel</label>
            <?php if ($puedeElegirPlantel): ?>
            <select name="id_plantel" required>
                <?php foreach ($listaPlanteles as $pl): ?>
                    <option value="<?php echo (int)$pl['id_plantel']; ?>"<?php echo (int)$pl['id_plantel'] === $plantelActivo ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars($pl['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="hidden" name="id_plantel" value="<?php echo (int)$plantelActivo; ?>">
            <input type="text" readonly value="<?php echo htmlspecialchars($_SESSION['plantel_nombre'] ?? 'Plantel'); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; background:#f5f5f5;">
            <?php endif; ?>
        </div>

        <div class="full-width">
            <button type="submit" class="btn-guardar"<?php echo empty($rolesForm) ? ' disabled' : ''; ?>>Guardar Registro</button>
        </div>
    </form>

    <div id="respuesta-registro" style="margin-top: 20px; padding: 10px; border-radius: 5px; display: none;"></div>
</div>

<script>
(function () {
  const sel = document.getElementById('registro-id-rol');
  const hid = document.getElementById('registro-rol-clave');
  const chkGoogle = document.getElementById('registro-ya-google');
  const inpNombre = document.getElementById('registro-nombre');
  const inpApellido = document.getElementById('registro-apellido');
  const hidEmail = document.getElementById('registro-email');
  const hidUsername = document.getElementById('registro-username');
  const previewEmail = document.getElementById('registro-preview-email');
  const previewUser = document.getElementById('registro-preview-user');
  const previewBox = document.getElementById('registro-cuenta-preview');
  const manualWrap = document.getElementById('registro-cuenta-manual-wrap');
  const manualInp = document.getElementById('registro-cuenta-manual');
  const sugerirUrl = <?php echo json_encode($sugerirUrl, JSON_UNESCAPED_UNICODE); ?>;
  const dominio = <?php echo json_encode($dominio, JSON_UNESCAPED_UNICODE); ?>;

  function syncRol() {
    if (!sel || !hid) return;
    const opt = sel.options[sel.selectedIndex];
    hid.value = opt ? (opt.getAttribute('data-clave') || '') : '';
  }
  sel?.addEventListener('change', syncRol);
  syncRol();

  function aplicarCuenta(email, username) {
    if (hidEmail) hidEmail.value = email || '';
    if (hidUsername) hidUsername.value = username || '';
    if (previewEmail) previewEmail.textContent = email || '—';
    if (previewUser) previewUser.textContent = username || '—';
  }

  function parseCuentaInput(raw) {
    raw = (raw || '').trim().toLowerCase();
    if (!raw) return { email: '', username: '' };
    if (raw.includes('@')) {
      const local = raw.split('@')[0];
      return { email: raw, username: local };
    }
    return { email: raw + '@' + dominio, username: raw.replace(/[^a-z0-9._-]/g, '') };
  }

  function syncModoGoogle() {
    const manual = chkGoogle && chkGoogle.checked;
    if (previewBox) previewBox.style.display = manual ? 'none' : '';
    if (manualWrap) manualWrap.classList.toggle('is-visible', manual);
    if (manual) {
      if (manualInp) manualInp.required = true;
      const c = parseCuentaInput(manualInp?.value || '');
      aplicarCuenta(c.email, c.username);
    } else {
      if (manualInp) { manualInp.required = false; manualInp.value = ''; }
      sugerirCorreo();
    }
  }

  let sugerirTimer = null;
  async function sugerirCorreo() {
    if (!inpNombre || !inpApellido || (chkGoogle && chkGoogle.checked)) return;
    const nombre = inpNombre.value.trim();
    const apellido = inpApellido.value.trim();
    if (nombre === '' || apellido === '') {
      aplicarCuenta('', '');
      return;
    }
    try {
      const url = sugerirUrl + '?nombre=' + encodeURIComponent(nombre) + '&apellido=' + encodeURIComponent(apellido);
      const res = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      if (data && data.ok) {
        aplicarCuenta(data.email || '', data.username || '');
      }
    } catch (e) { /* ignore */ }
  }

  function debounceSugerir() {
    clearTimeout(sugerirTimer);
    sugerirTimer = setTimeout(sugerirCorreo, 350);
  }

  manualInp?.addEventListener('input', function () {
    const c = parseCuentaInput(manualInp.value);
    aplicarCuenta(c.email, c.username);
  });

  chkGoogle?.addEventListener('change', syncModoGoogle);
  inpNombre?.addEventListener('input', debounceSugerir);
  inpApellido?.addEventListener('input', debounceSugerir);

  document.getElementById('formRegistroPersonal')?.addEventListener('submit', function (e) {
    if (chkGoogle?.checked) {
      const c = parseCuentaInput(manualInp?.value || '');
      if (!c.email) {
        e.preventDefault();
        alert('Indique el correo institucional existente');
        manualInp?.focus();
        return;
      }
      aplicarCuenta(c.email, c.username);
    } else if (!hidEmail?.value || !hidUsername?.value) {
      e.preventDefault();
      alert('Complete nombre y apellido para generar el correo');
      return;
    }
  });

  syncModoGoogle();
})();
</script>
