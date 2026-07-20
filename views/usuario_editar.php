<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/auth_helpers.php';
global $pdo;

auth_ensure_email_column($pdo);
if (function_exists('usuario_ensure_schema')) {
    usuario_ensure_schema($pdo);
}
if (function_exists('usuario_suspension_ensure_schema')) {
    usuario_suspension_ensure_schema($pdo);
}
if (function_exists('asistencia_ensure_schema')) {
    asistencia_ensure_schema($pdo);
}
if (function_exists('huella_ensure_schema')) {
    huella_ensure_schema($pdo);
}
if (function_exists('rbac_db_ensure_schema')) {
    rbac_db_ensure_schema($pdo);
}
plantel_ensure_column($pdo, 'usuarios', 'id_rol', 'INT UNSIGNED NULL', 'rol');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo "<div class='alert'>ID inválido.</div>"; return; }

$idPlantel = plantel_scope_id($pdo);

$colsBase = 'id_usuario, nombre, apellido, username, email, rol, id_rol, departamento, id_plantel';
$colsExtra = [
    'codigo_huella',
    'huella_registrada',
    'huella_registrada_en',
    'suspendido',
    'suspension_tipo',
    'suspension_motivo',
    'suspension_en',
];
$colsSql = $colsBase;
foreach ($colsExtra as $col) {
    if (function_exists('plantel_column_exists') && plantel_column_exists($pdo, 'usuarios', $col)) {
        $colsSql .= ', ' . $col;
    } elseif (!function_exists('plantel_column_exists')) {
        $colsSql .= ', ' . $col;
    }
}

try {
    $stmt = $pdo->prepare("SELECT {$colsSql} FROM usuarios WHERE id_usuario = ? LIMIT 1");
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback mínimo si alguna columna aún no existe en el hosting
    error_log('usuario_editar SELECT: ' . $e->getMessage());
    $stmt = $pdo->prepare(
        'SELECT id_usuario, nombre, apellido, username, email, rol, departamento, id_plantel
         FROM usuarios WHERE id_usuario = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $u['id_rol'] = $u['id_rol'] ?? null;
        $u['codigo_huella'] = $u['codigo_huella'] ?? null;
        $u['huella_registrada'] = $u['huella_registrada'] ?? 0;
        $u['huella_registrada_en'] = $u['huella_registrada_en'] ?? null;
        $u['suspendido'] = $u['suspendido'] ?? 0;
        $u['suspension_tipo'] = $u['suspension_tipo'] ?? null;
        $u['suspension_motivo'] = $u['suspension_motivo'] ?? null;
        $u['suspension_en'] = $u['suspension_en'] ?? null;
    }
}

$rolesForm = function_exists('rbac_roles_para_formulario') ? rbac_roles_para_formulario($pdo) : [];
if (!$u) { echo "<div class='alert'>Usuario no existe.</div>"; return; }
$esAdminGlobal = function_exists('plantel_es_admin') && plantel_es_admin();
if (!$esAdminGlobal && (int)($u['id_plantel'] ?? 0) !== $idPlantel) {
    echo "<div class='alert'>Este usuario no pertenece al plantel activo.</div>";
    return;
}

$listaPlanteles = plantel_list_accesibles($pdo, true);
$puedePrivilegios = function_exists('rbac_usuario_puede_gestionar_privilegios') && rbac_usuario_puede_gestionar_privilegios();
$privApi = hay_asset_url('php/usuario_privilegios_api.php');
$uplApi = hay_asset_url('php/usuario_planteles_api.php');
$rolUsuarioEdit = strtolower(trim((string) ($u['rol'] ?? '')));
$puedeAsesoriaMaterias = in_array($rolUsuarioEdit, ['profesor', 'docente', 'coordinador', 'coordinacion', 'director'], true)
    || (function_exists('asesoria_puede_administrar') && asesoria_puede_administrar());
$puedePlantelesTemp = $puedePrivilegios && function_exists('plantel_roles_con_apoyo_temporal')
    && in_array($rolUsuarioEdit, plantel_roles_con_apoyo_temporal(), true);
$puedeCuentasDigitales = function_exists('cuenta_digital_puede_gestionar_staff') && cuenta_digital_puede_gestionar_staff();
$puedeSuspenderStaff = function_exists('usuario_suspension_puede_gestionar_staff') && usuario_suspension_puede_gestionar_staff()
    && ($u['rol'] ?? '') !== 'alumno';
$suspApi = hay_asset_url('php/usuario_suspension_api.php');
$estaSuspendido = (int) ($u['suspendido'] ?? 0) === 1;
$etiquetaSusp = function_exists('usuario_suspension_etiqueta') ? usuario_suspension_etiqueta($u) : '';
?>

<link rel="stylesheet" href="css/resultados.css">

<div class="result-container">
  <div class="result-header">
    <h2>Editar usuario</h2>
    <div class="disc-actions">
      <?php if ($puedeAsesoriaMaterias): ?>
      <button type="button" class="secondary" onclick="cargarSeccion('profesor_asesoria_materias', 'id_usuario=<?php echo (int)$u['id_usuario']; ?>')">Materias asesorías</button>
      <?php endif; ?>
      <button type="button" onclick="cargarSeccion('ver_usuarios')">Volver</button>
    </div>
  </div>

  <div class="patron-desc">
    <form method="POST" action="php/usuario_update.php">
      <input type="hidden" name="id_usuario" value="<?php echo (int)$u['id_usuario']; ?>">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div>
          <label><strong>Nombre</strong></label><br>
          <input name="nombre" required value="<?php echo htmlspecialchars($u['nombre']); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
        </div>
        <div>
          <label><strong>Apellido</strong></label><br>
          <input name="apellido" required value="<?php echo htmlspecialchars($u['apellido']); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
        </div>
        <div>
          <label><strong>Usuario</strong></label><br>
          <input name="username" required value="<?php echo htmlspecialchars($u['username']); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
        </div>
        <div>
          <label><strong>Correo institucional</strong></label><br>
          <input type="email" name="email" required value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" placeholder="usuario@cncm.edu.mx" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
        </div>
        <div>
          <label><strong>Plantel</strong></label><br>
          <?php if (plantel_es_admin()): ?>
          <select name="id_plantel" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
            <option value="">— Sin plantel (admin global) —</option>
            <?php foreach ($listaPlanteles as $pl): ?>
              <option value="<?php echo (int)$pl['id_plantel']; ?>"<?php echo (int)($u['id_plantel'] ?? 0) === (int)$pl['id_plantel'] ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars($pl['nombre']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php else: ?>
          <input type="hidden" name="id_plantel" value="<?php echo (int)$idPlantel; ?>">
          <input type="text" readonly value="<?php echo htmlspecialchars($_SESSION['plantel_nombre'] ?? ''); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px; background:#f5f5f5;">
          <?php endif; ?>
        </div>
        <div>
          <label><strong>Rol</strong></label><br>
          <?php
          $idRolSel = (int) ($u['id_rol'] ?? 0);
          if ($idRolSel <= 0 && !empty($u['rol'])) {
              foreach ($rolesForm as $rf) {
                  if (($rf['clave'] ?? '') === $u['rol']) {
                      $idRolSel = (int) $rf['id_rol'];
                      break;
                  }
              }
          }
          ?>
          <select name="id_rol" id="editar-id-rol" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;" required>
            <?php foreach ($rolesForm as $rf): ?>
              <option value="<?php echo (int) $rf['id_rol']; ?>" data-clave="<?php echo htmlspecialchars($rf['clave'], ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo $idRolSel === (int) $rf['id_rol'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($rf['nombre']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="rol" id="editar-rol-clave" value="<?php echo htmlspecialchars($u['rol'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
          <label><strong>Departamento</strong></label><br>
          <input type="text" readonly value="<?php echo htmlspecialchars($u['departamento'] ?? '—'); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px; background:#f5f5f5;">
          <p style="font-size:0.82rem;color:#666;margin:4px 0 0;">Se actualiza al cambiar el rol (según tabla <code>roles</code>).</p>
        </div>
        <div>
          <label><strong>Reset password (opcional)</strong></label><br>
          <input type="password" name="password" placeholder="Dejar vacío para no cambiar" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px;">
        </div>
        <?php if (function_exists('huella_puede_enrolar_usuario') && huella_puede_enrolar_usuario()): ?>
        <div class="full-width" style="grid-column:1/-1; padding:16px; background:#e8f4fd; border:1px solid #90caf9; border-radius:10px;">
          <h3 style="margin:0 0 8px; font-size:1rem;"><i class="fas fa-fingerprint"></i> Huella digital (U.areU 5300)</h3>
          <?php if ((int)($u['huella_registrada'] ?? 0)): ?>
            <p style="color:#2e7d32; font-size:0.88rem; margin:0 0 10px;">
              <i class="fas fa-check-circle"></i> Huella registrada
              <?php if (!empty($u['huella_registrada_en'])): ?>
                · <?php echo date('d/m/Y H:i', strtotime($u['huella_registrada_en'])); ?>
              <?php endif; ?>
            </p>
          <?php else: ?>
            <p style="font-size:0.88rem; color:#555; margin:0 0 10px;">Sin huella registrada. Use el botón para capturar 3 lecturas en el lector de recepción.</p>
          <?php endif; ?>
          <button type="button" class="primary" onclick="cargarSeccion('usuario_huella_enroll', 'id=<?php echo (int)$u['id_usuario']; ?>')">
            <i class="fas fa-fingerprint"></i> Registrar / actualizar huella
          </button>
        </div>
        <?php endif; ?>
      </div>

      <div id="usuario-edit-msg" style="display:none; margin-top:12px; padding:10px; border-radius:8px;"></div>

      <div class="disc-actions" style="justify-content:flex-start; margin-top:14px;">
        <button class="primary" type="submit">Guardar cambios</button>
      </div>
    </form>

    <?php if ($puedeSuspenderStaff): ?>
    <div id="staff-suspension-panel" style="margin-top:24px; padding:16px; border:1px solid <?php echo $estaSuspendido ? '#ef9a9a' : '#c5e1a5'; ?>; border-radius:12px; background:<?php echo $estaSuspendido ? '#ffebee' : '#f1f8e9'; ?>;">
      <h3 style="margin:0 0 8px;"><i class="fas fa-user-slash"></i> Estado de acceso</h3>
      <p style="font-size:0.85rem;color:#555;margin:0 0 12px;">
        Al suspender personal se bloquea el acceso a HAY y, si tiene cuentas vinculadas, también en <strong>Google</strong> y <strong>Moodle</strong>.
        No se elimina el usuario; puede reactivarse si regresa.
      </p>
      <p id="staff-susp-estado" style="margin:0 0 12px; font-weight:600;">
        <?php if ($estaSuspendido): ?>
          <span style="color:#c62828;"><?php echo htmlspecialchars($etiquetaSusp ?: 'Suspendido'); ?></span>
          <?php if (!empty($u['suspension_motivo'])): ?>
            — <?php echo htmlspecialchars($u['suspension_motivo']); ?>
          <?php endif; ?>
          <?php if (!empty($u['suspension_en'])): ?>
            <br><span style="font-weight:400;font-size:0.85rem;color:#666;">Desde <?php echo date('d/m/Y H:i', strtotime($u['suspension_en'])); ?></span>
          <?php endif; ?>
        <?php else: ?>
          <span style="color:#2e7d32;">Activo</span>
        <?php endif; ?>
      </p>
      <div id="staff-susp-form" style="<?php echo $estaSuspendido ? 'display:none;' : ''; ?>">
        <label><strong>Motivo de suspensión</strong></label>
        <input type="text" id="staff-susp-motivo" placeholder="Ej. Renuncia, fin de contrato…" style="width:100%;max-width:480px;padding:10px;border:1px solid #ddd;border-radius:10px;margin:6px 0 10px;">
        <button type="button" class="secondary" id="btn-staff-suspender" style="background:#ffcdd2;border-color:#e57373;">Suspender acceso</button>
      </div>
      <?php if ($estaSuspendido): ?>
        <button type="button" class="primary" id="btn-staff-reactivar">Reactivar acceso</button>
      <?php endif; ?>
      <div id="staff-susp-msg" style="display:none;margin-top:10px;padding:10px;border-radius:8px;white-space:pre-wrap;"></div>
    </div>
    <?php endif; ?>

    <?php if ($puedeCuentasDigitales): ?>
    <div id="staff-cuentas-panel" style="margin-top:24px; padding:16px; border:1px solid #90caf9; border-radius:12px; background:#e8f4fd;">
      <h3 style="margin:0 0 8px;"><i class="fas fa-cloud"></i> Cuentas digitales (Google / Moodle)</h3>
      <p style="font-size:0.85rem;color:#555;margin:0 0 12px;">Vincule cuentas existentes o unifique el username entre HAY y Moodle.</p>
      <div id="staff-cuentas-loading" style="color:#666;">Cargando…</div>
      <div id="staff-cuentas-content" style="display:none;"></div>
      <div id="staff-cuentas-msg" style="display:none;margin-top:10px;padding:10px;border-radius:8px;"></div>
    </div>
    <?php endif; ?>

    <?php if ($puedePrivilegios): ?>
    <div style="margin-top:16px;">
      <button type="button" class="secondary" onclick="cargarSeccion('admin_roles','tab=personal&id_usuario=<?php echo (int)$u['id_usuario']; ?>')">
        <i class="fas fa-user-shield"></i> Gestionar permisos en Roles y permisos
      </button>
    </div>
    <div style="margin-top:24px; padding:16px; border:1px solid #e0e0e0; border-radius:12px;">
      <h3 style="margin:0 0 8px;">Privilegios individuales (rápido)</h3>
      <p style="font-size:0.85rem;color:#666;margin:0 0 12px;">Otorgar o denegar vistas puntuales. Para vista completa use <strong>Roles y permisos</strong>.</p>
      <div id="upriv-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;"></div>
      <div id="upriv-msg" style="display:none;margin-top:10px;padding:10px;border-radius:8px;"></div>
      <button type="button" class="primary" id="btn-guardar-upriv" style="margin-top:12px;">Guardar privilegios</button>
    </div>
    <?php endif; ?>

    <?php if ($puedePlantelesTemp): ?>
    <div style="margin-top:24px; padding:16px; border:1px solid #e0e0e0; border-radius:12px;">
      <h3 style="margin:0 0 8px;">Sedes temporales de apoyo</h3>
      <p style="font-size:0.85rem;color:#666;margin:0 0 12px;">Para cuando el asesor, recepcionista o coordinador cubre puesto en otro plantel.</p>
      <div id="upl-list" style="display:flex;flex-direction:column;gap:8px;"></div>
      <button type="button" class="secondary" id="btn-upl-add" style="margin-top:10px;">+ Agregar sede</button>
      <div id="upl-msg" style="display:none;margin-top:10px;padding:10px;border-radius:8px;"></div>
      <button type="button" class="primary" id="btn-guardar-upl" style="margin-top:12px;">Guardar sedes temporales</button>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  const selRol = document.getElementById('editar-id-rol');
  const hidRol = document.getElementById('editar-rol-clave');
  function syncRolEdit() {
    if (!selRol || !hidRol) return;
    const opt = selRol.options[selRol.selectedIndex];
    hidRol.value = opt ? (opt.getAttribute('data-clave') || '') : '';
  }
  selRol?.addEventListener('change', syncRolEdit);
  syncRolEdit();

  const form = document.querySelector('.patron-desc form');
  const msg = document.getElementById('usuario-edit-msg');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    try {
      const res = await fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      if (msg) {
        msg.style.display = 'block';
        msg.textContent = data.message || (data.status === 'ok' ? 'Guardado' : 'Error');
        msg.style.background = data.status === 'ok' ? '#e8f5e9' : '#ffebee';
        msg.style.color = data.status === 'ok' ? '#2e7d32' : '#c62828';
      }
      if (data.status === 'ok' && data.seccion) {
        setTimeout(() => cargarSeccion(data.seccion), 800);
      }
    } catch (err) {
      if (msg) { msg.style.display = 'block'; msg.textContent = 'Error de conexión'; }
    }
  });

  <?php if ($puedePrivilegios): ?>
  const uprivApi = <?php echo json_encode($privApi, JSON_UNESCAPED_UNICODE); ?>;
  const uprivId = <?php echo (int) $u['id_usuario']; ?>;
  const uprivGrid = document.getElementById('upriv-grid');
  const uprivMsg = document.getElementById('upriv-msg');

  async function cargarUpriv() {
    const r = await fetch(uprivApi + '?action=listar&id_usuario=' + uprivId, { headers: { 'X-Requested-With': 'fetch' } });
    const data = await r.json();
    if (data.status !== 'ok' || !uprivGrid) return;
    const actual = {};
    (data.privilegios || []).forEach((p) => { actual[p.privilegio] = p; });
    const restr = new Set(data.restringidos || []);
    const grupos = {};
    Object.entries(data.catalogo || {}).forEach(([k, v]) => {
      const g = v.grupo || 'Otros';
      if (!grupos[g]) grupos[g] = [];
      grupos[g].push({ key: k, label: v.label, restringido: restr.has(k) && !data.es_supervisor });
    });
    let html = '';
    Object.keys(grupos).sort().forEach((g) => {
      html += '<div style="grid-column:1/-1;font-weight:600;margin-top:8px;">' + g + '</div>';
      grupos[g].forEach((item) => {
        const row = actual[item.key] || {};
        const tipo = row.tipo || '';
        const chk = tipo === 'otorgar' ? 'checked' : '';
        const den = tipo === 'denegar' ? 'checked' : '';
        const hasta = row.vigente_hasta || '';
        const dis = item.restringido ? ' disabled title="Solo supervisión"' : '';
        html += `<label style="display:flex;flex-direction:column;gap:4px;padding:8px;border:1px solid #eee;border-radius:8px;font-size:0.85rem;">
          <span>${item.label}</span>
          <span style="display:flex;gap:8px;align-items:center;">
            <label><input type="radio" name="upriv-${item.key}" value="" data-priv="${item.key}" ${!chk && !den ? 'checked' : ''}${dis}> Rol</label>
            <label><input type="radio" name="upriv-${item.key}" value="otorgar" data-priv="${item.key}" ${chk}${dis}> +</label>
            <label><input type="radio" name="upriv-${item.key}" value="denegar" data-priv="${item.key}" ${den}${dis}> −</label>
          </span>
          <input type="date" class="upriv-hasta" data-priv="${item.key}" value="${hasta}" placeholder="Vigencia"${dis}>
        </label>`;
      });
    });
    uprivGrid.innerHTML = html;
  }

  document.getElementById('btn-guardar-upriv')?.addEventListener('click', async () => {
    const items = [];
    uprivGrid?.querySelectorAll('input[type=radio]:checked').forEach((rad) => {
      const priv = rad.getAttribute('data-priv');
      const val = rad.value;
      if (!priv || val === '') return;
      const hasta = uprivGrid.querySelector('.upriv-hasta[data-priv="' + priv + '"]')?.value || '';
      items.push({ privilegio: priv, tipo: val, vigente_hasta: hasta || null });
    });
    const fd = new FormData();
    fd.append('action', 'guardar');
    fd.append('id_usuario', String(uprivId));
    fd.append('items', JSON.stringify(items));
    const r = await fetch(uprivApi, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await r.json();
    if (uprivMsg) {
      uprivMsg.style.display = 'block';
      uprivMsg.textContent = data.message || '';
      uprivMsg.style.background = data.status === 'ok' ? '#e8f5e9' : '#ffebee';
    }
  });

  cargarUpriv();
  <?php endif; ?>

  <?php if ($puedePlantelesTemp): ?>
  const uplApi = <?php echo json_encode($uplApi, JSON_UNESCAPED_UNICODE); ?>;
  const uplId = <?php echo (int) $u['id_usuario']; ?>;
  const uplList = document.getElementById('upl-list');
  const uplMsg = document.getElementById('upl-msg');
  let uplCatalogo = [];
  let uplHome = 0;

  function uplRowHtml(row) {
    const idPl = row?.id_plantel || '';
    const hasta = row?.vigente_hasta || '';
    const motivo = row?.motivo || '';
    let opts = '<option value="">— Sede —</option>';
    uplCatalogo.forEach((p) => {
      if (Number(p.id_plantel) === uplHome) return;
      const sel = Number(idPl) === Number(p.id_plantel) ? ' selected' : '';
      opts += `<option value="${p.id_plantel}"${sel}>${p.nombre}</option>`;
    });
    return `<div class="upl-row" style="display:grid;grid-template-columns:1fr 140px 1fr auto;gap:8px;align-items:center;">
      <select class="upl-plantel" style="padding:8px;border:1px solid #ddd;border-radius:8px;">${opts}</select>
      <input type="date" class="upl-hasta" value="${hasta}" style="padding:8px;border:1px solid #ddd;border-radius:8px;">
      <input type="text" class="upl-motivo" value="${motivo}" placeholder="Motivo (opcional)" style="padding:8px;border:1px solid #ddd;border-radius:8px;">
      <button type="button" class="secondary upl-del">Quitar</button>
    </div>`;
  }

  async function cargarUpl() {
    const r = await fetch(uplApi + '?action=listar&id_usuario=' + uplId, { headers: { 'X-Requested-With': 'fetch' } });
    const data = await r.json();
    if (data.status !== 'ok' || !uplList) return;
    uplCatalogo = data.catalogo || [];
    uplHome = Number(data.id_plantel_home || 0);
    uplList.innerHTML = '';
    (data.planteles || []).forEach((row) => {
      uplList.insertAdjacentHTML('beforeend', uplRowHtml(row));
    });
    uplList.querySelectorAll('.upl-del').forEach((btn) => {
      btn.addEventListener('click', () => btn.closest('.upl-row')?.remove());
    });
  }

  document.getElementById('btn-upl-add')?.addEventListener('click', () => {
    uplList?.insertAdjacentHTML('beforeend', uplRowHtml({}));
    const last = uplList?.querySelector('.upl-row:last-child .upl-del');
    last?.addEventListener('click', () => last.closest('.upl-row')?.remove());
  });

  document.getElementById('btn-guardar-upl')?.addEventListener('click', async () => {
    const items = [];
    uplList?.querySelectorAll('.upl-row').forEach((row) => {
      const idPl = row.querySelector('.upl-plantel')?.value;
      if (!idPl) return;
      items.push({
        id_plantel: Number(idPl),
        vigente_hasta: row.querySelector('.upl-hasta')?.value || null,
        motivo: row.querySelector('.upl-motivo')?.value || null,
      });
    });
    const fd = new FormData();
    fd.append('action', 'guardar');
    fd.append('id_usuario', String(uplId));
    fd.append('items', JSON.stringify(items));
    const r = await fetch(uplApi, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await r.json();
    if (uplMsg) {
      uplMsg.style.display = 'block';
      uplMsg.textContent = data.message || '';
      uplMsg.style.background = data.status === 'ok' ? '#e8f5e9' : '#ffebee';
    }
    if (data.status === 'ok') cargarUpl();
  });

  cargarUpl();
  <?php endif; ?>

  <?php if ($puedeCuentasDigitales): ?>
  const staffId = <?php echo (int) $u['id_usuario']; ?>;
  const staffCuentasMsg = document.getElementById('staff-cuentas-msg');
  function staffCuentasShowMsg(text, ok) {
    if (!staffCuentasMsg) return;
    staffCuentasMsg.style.display = text ? 'block' : 'none';
    staffCuentasMsg.textContent = text || '';
    staffCuentasMsg.style.background = ok ? '#e8f5e9' : '#ffebee';
    staffCuentasMsg.style.color = ok ? '#2e7d32' : '#c62828';
  }
  async function staffCuentasApi(action, extra) {
    const fd = new FormData();
    fd.append('id_usuario', staffId);
    fd.append('action', action);
    Object.keys(extra || {}).forEach((k) => fd.append(k, extra[k]));
    const r = await fetch('php/usuario_cuenta_api.php', { method: 'POST', body: fd });
    return r.json();
  }
  function staffCuentasRender(est) {
    const box = document.getElementById('staff-cuentas-content');
    if (!box || !est) return;
    const g = est.google || {};
    const h = est.hay || {};
    const m = est.moodle || {};
    box.innerHTML = `
      <p><strong>HAY:</strong> <code>${h.username || ''}</code> · ${h.email || ''}</p>
      <p><strong>Google:</strong> ${g.activo ? '✓' : '—'} ${g.email || ''} — ${g.mensaje || ''}</p>
      <p><strong>Moodle:</strong> ${m.activo ? '✓' : '—'} ${m.username ? 'user ' + m.username : ''} ${m.id_moodle ? 'ID ' + m.id_moodle : ''} — ${m.mensaje || ''}</p>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; max-width:720px; margin-top:12px;">
        <label>Correo Google<br><input type="email" id="staff-vinc-google" value="${g.email || h.email || ''}" style="width:100%;padding:8px;"></label>
        <label>Moodle (user/email/ID)<br><input type="text" id="staff-vinc-moodle" value="${m.username || (m.id_moodle || '')}" style="width:100%;padding:8px;"></label>
        <label>Username unificado<br><input type="text" id="staff-vinc-user" value="${h.username || ''}" style="width:100%;padding:8px;"></label>
        <label style="display:flex;align-items:center;gap:8px;margin-top:22px;"><input type="checkbox" id="staff-vinc-sync" checked> Sync Moodle username</label>
      </div>
      <div style="margin-top:12px;">
        <button type="button" class="secondary" id="staff-vinc-buscar">Buscar</button>
        <button type="button" class="primary" id="staff-vinc-guardar">Vincular</button>
        <button type="button" class="secondary" id="staff-prov-moodle">Crear Moodle si falta</button>
      </div>
      <pre id="staff-vinc-preview" style="display:none;margin-top:10px;padding:8px;background:#fff;border:1px solid #ddd;font-size:0.82rem;white-space:pre-wrap;"></pre>
    `;
    document.getElementById('staff-vinc-buscar')?.addEventListener('click', async () => {
      const data = await staffCuentasApi('buscar', {
        google_email: document.getElementById('staff-vinc-google')?.value || '',
        moodle_ref: document.getElementById('staff-vinc-moodle')?.value || '',
      });
      const pre = document.getElementById('staff-vinc-preview');
      if (pre) { pre.style.display = 'block'; pre.textContent = JSON.stringify(data.resultado || data, null, 2); }
    });
    document.getElementById('staff-vinc-guardar')?.addEventListener('click', async () => {
      if (!confirm('¿Vincular cuentas con este usuario?')) return;
      const data = await staffCuentasApi('vincular', {
        google_email: document.getElementById('staff-vinc-google')?.value || '',
        moodle_ref: document.getElementById('staff-vinc-moodle')?.value || '',
        username_unificado: document.getElementById('staff-vinc-user')?.value || '',
        sync_moodle_username: document.getElementById('staff-vinc-sync')?.checked ? '1' : '',
      });
      staffCuentasShowMsg(data.message || '', data.status === 'ok');
      if (data.estado) staffCuentasRender(data.estado);
    });
    document.getElementById('staff-prov-moodle')?.addEventListener('click', async () => {
      const data = await staffCuentasApi('provisionar', { servicio: 'all' });
      staffCuentasShowMsg(data.message || '', data.status === 'ok');
      if (data.estado) staffCuentasRender(data.estado);
    });
  }
  async function staffCuentasCargar() {
    const loading = document.getElementById('staff-cuentas-loading');
    const content = document.getElementById('staff-cuentas-content');
    try {
      const r = await fetch('php/usuario_cuenta_api.php?action=estado&id_usuario=' + staffId);
      const data = await r.json();
      if (loading) loading.style.display = 'none';
      if (content) content.style.display = 'block';
      if (data.status === 'ok' && data.estado) staffCuentasRender(data.estado);
      else staffCuentasShowMsg(data.message || 'Error', false);
    } catch (e) {
      if (loading) loading.style.display = 'none';
      staffCuentasShowMsg('Error de conexión', false);
    }
  }
  staffCuentasCargar();
  <?php endif; ?>

  <?php if ($puedeSuspenderStaff): ?>
  const suspApi = <?php echo json_encode($suspApi, JSON_UNESCAPED_UNICODE); ?>;
  const suspId = <?php echo (int) $u['id_usuario']; ?>;
  const suspMsg = document.getElementById('staff-susp-msg');
  function suspShowMsg(text, ok) {
    if (!suspMsg) return;
    suspMsg.style.display = 'block';
    suspMsg.textContent = text;
    suspMsg.style.background = ok ? '#e8f5e9' : '#ffebee';
  }
  document.getElementById('btn-staff-suspender')?.addEventListener('click', async () => {
    const motivo = document.getElementById('staff-susp-motivo')?.value?.trim() || '';
    if (!confirm('¿Suspender el acceso de este usuario en HAY, Google y Moodle?')) return;
    const fd = new FormData();
    fd.append('action', 'suspender_staff');
    fd.append('id_usuario', String(suspId));
    fd.append('motivo', motivo);
    try {
      const r = await fetch(suspApi, { method: 'POST', body: fd });
      const data = await r.json();
      suspShowMsg(data.message || '', data.status === 'ok');
      if (data.status === 'ok') setTimeout(() => cargarSeccion('usuario_editar', 'id=' + suspId), 900);
    } catch (e) { suspShowMsg('Error de conexión', false); }
  });
  document.getElementById('btn-staff-reactivar')?.addEventListener('click', async () => {
    if (!confirm('¿Reactivar el acceso de este usuario?')) return;
    const fd = new FormData();
    fd.append('action', 'reactivar_staff');
    fd.append('id_usuario', String(suspId));
    try {
      const r = await fetch(suspApi, { method: 'POST', body: fd });
      const data = await r.json();
      suspShowMsg(data.message || '', data.status === 'ok');
      if (data.status === 'ok') setTimeout(() => cargarSeccion('usuario_editar', 'id=' + suspId), 900);
    } catch (e) { suspShowMsg('Error de conexión', false); }
  });
  <?php endif; ?>
})();
</script>

