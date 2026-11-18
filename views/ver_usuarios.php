<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/auth_helpers.php';
auth_ensure_email_column($pdo);
usuario_ensure_schema($pdo);
login_security_ensure_schema($pdo);
$puedeDesbloquear = login_security_puede_desbloquear();

$idPlantel = plantel_scope_id($pdo);
$esAdminGlobal = function_exists('plantel_es_admin') && plantel_es_admin();
$verTodos = $esAdminGlobal && !empty($_GET['todos']);
if (function_exists('rbac_usuario_ensure_permisos_flag')) {
    rbac_usuario_ensure_permisos_flag($pdo);
}

$sql = "SELECT u.id_usuario, u.nombre, u.apellido, u.username, u.email, u.rol, u.departamento,
               u.fecha_creacion, u.suspendido, u.ultimo_acceso, u.id_alumno, u.id_plantel,
               u.login_fallidos, u.login_bloqueado_hasta,
               u.huella_registrada, u.codigo_huella, u.permisos_personalizados,
               p.nombre AS plantel_nombre,
               COALESCE(r.nombre, u.rol) AS rol_nombre
        FROM usuarios u
        LEFT JOIN planteles p ON p.id_plantel = u.id_plantel
        LEFT JOIN roles r ON r.id_rol = u.id_rol
        WHERE u.rol != 'alumno'";
$params = [];
if ($verTodos) {
    $idsAcc = [];
    foreach (plantel_list_accesibles($pdo, true) as $p) {
        $idsAcc[] = (int) $p['id_plantel'];
    }
    if ($idsAcc !== [] && !(function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total())) {
        $in = implode(',', array_fill(0, count($idsAcc), '?'));
        $sql .= " AND u.id_plantel IN ($in)";
        $params = $idsAcc;
    }
} else {
    $sql .= ' AND u.id_plantel = ?';
    $params[] = $idPlantel;
}
$sql .= ' ORDER BY u.id_usuario DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rolesForm = function_exists('rbac_roles_para_formulario') ? rbac_roles_para_formulario($pdo) : [];
$listaPlanteles = plantel_list_accesibles($pdo, true);
if ($listaPlanteles === [] && function_exists('plantel_list')) {
    $listaPlanteles = plantel_list($pdo, true);
}
$puedeBulk = in_array(
    function_exists('rbac_rol_efectivo') ? rbac_rol_efectivo() : (string) ($_SESSION['rol'] ?? ''),
    ['supervisor', 'admin', 'director', 'gerente'],
    true
) || (function_exists('rbac_tiene_acceso_total') && rbac_tiene_acceso_total());
$bulkApi = hay_asset_url('php/usuario_bulk_update.php');
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">
<link rel="stylesheet" href="css/hay_icon_buttons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-users"></i> Personal del plantel</h2>
    <p style="color:#666; margin:0 0 8px;">
      <?php if ($verTodos): ?>
        Usuarios de <strong>todos los planteles accesibles</strong>.
      <?php else: ?>
        Solo usuarios asignados a <strong><?php echo htmlspecialchars($_SESSION['plantel_nombre'] ?? 'esta sede'); ?></strong>.
      <?php endif; ?>
    </p>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <button type="button" class="primary" onclick="cargarSeccion('crear_usuario')">Nuevo usuario</button>
      <button type="button" class="secondary" onclick="cargarSeccion('prospectos_profesor')">Prospectos profesor</button>
      <?php if ($esAdminGlobal): ?>
        <?php if ($verTodos): ?>
          <button type="button" class="secondary" onclick="cargarSeccion('ver_usuarios')">Solo plantel activo</button>
        <?php else: ?>
          <button type="button" class="secondary" onclick="cargarSeccion('ver_usuarios', 'todos=1')">Ver todos los planteles</button>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($puedeBulk && !empty($usuarios)): ?>
        <button type="button" class="secondary" id="btn-usuarios-bulk-toggle">
          <i class="fas fa-edit"></i> Editar todos
        </button>
        <button type="button" class="primary" id="btn-usuarios-bulk-save" style="display:none;">
          <i class="fas fa-save"></i> Guardar cambios
        </button>
        <button type="button" class="secondary" id="btn-usuarios-bulk-cancel" style="display:none;">Cancelar</button>
      <?php endif; ?>
    </div>
  </div>

  <p style="color:#666; margin-top:0;">Personal con acceso institucional. Los alumnos tienen usuario = # control y se gestionan desde su perfil.</p>
  <div id="usuarios-bulk-msg" class="catalog-alert" style="display:none; margin-bottom:12px;"></div>

  <div class="catalog-table-wrap hay-dt-panel">
    <?php if (empty($usuarios)): ?>
      <p>No hay usuarios en este plantel.</p>
    <?php else: ?>
      <table id="tabla-usuarios" class="catalog-table display hay-paged-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Apellido</th>
            <th>Usuario</th>
            <th>Rol</th>
            <th>Plantel</th>
            <th>Último acceso</th>
            <th>Huella</th>
            <th>Alumno</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
            <?php
              $idU = (int) $u['id_usuario'];
              $rolClave = (string) ($u['rol'] ?? '');
              $idPlU = (int) ($u['id_plantel'] ?? 0);
            ?>
            <tr data-id-usuario="<?php echo $idU; ?>">
              <td><?php echo $idU; ?></td>
              <td>
                <span class="usuarios-view-cell"><?php echo htmlspecialchars((string) $u['nombre']); ?></span>
                <input type="text" class="usuarios-bulk-input" data-field="nombre"
                  value="<?php echo htmlspecialchars((string) $u['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                  style="display:none; width:100%; min-width:110px; padding:6px 8px;">
              </td>
              <td>
                <span class="usuarios-view-cell"><?php echo htmlspecialchars((string) $u['apellido']); ?></span>
                <input type="text" class="usuarios-bulk-input" data-field="apellido"
                  value="<?php echo htmlspecialchars((string) $u['apellido'], ENT_QUOTES, 'UTF-8'); ?>"
                  style="display:none; width:100%; min-width:110px; padding:6px 8px;">
              </td>
              <td><?php echo htmlspecialchars($u['username']); ?></td>
              <td>
                <span class="usuarios-view-cell">
                  <span class="catalog-badge catalog-badge--muted"><?php echo htmlspecialchars($u['rol_nombre'] ?? $rolClave); ?></span>
                  <?php if (!empty($u['permisos_personalizados'])): ?>
                    <span class="catalog-badge catalog-badge--warn" title="Tiene permisos distintos al rol base">Personalizado</span>
                  <?php endif; ?>
                  <?php if ((int)($u['suspendido'] ?? 0)): ?>
                    <span class="catalog-badge catalog-badge--danger">Suspendido</span>
                  <?php endif; ?>
                  <?php if (login_security_usuario_bloqueado_por_intentos($u)): ?>
                    <span class="catalog-badge catalog-badge--danger" title="Bloqueado por intentos fallidos de login">Login bloqueado</span>
                  <?php elseif ((int)($u['login_fallidos'] ?? 0) > 0): ?>
                    <span class="catalog-badge catalog-badge--warn" title="Intentos fallidos recientes"><?php echo (int)$u['login_fallidos']; ?> fallo(s)</span>
                  <?php endif; ?>
                </span>
                <select class="usuarios-bulk-input" data-field="rol" style="display:none; min-width:140px; padding:6px 8px;">
                  <?php foreach ($rolesForm as $rf): ?>
                    <?php $clave = (string) ($rf['clave'] ?? ''); ?>
                    <option value="<?php echo htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>"
                      <?php echo $clave === $rolClave ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars((string) ($rf['nombre'] ?? $clave)); ?>
                    </option>
                  <?php endforeach; ?>
                  <?php if ($rolClave !== '' && !in_array($rolClave, array_column($rolesForm, 'clave'), true)): ?>
                    <option value="<?php echo htmlspecialchars($rolClave, ENT_QUOTES, 'UTF-8'); ?>" selected>
                      <?php echo htmlspecialchars($rolClave); ?> (actual)
                    </option>
                  <?php endif; ?>
                </select>
              </td>
              <td>
                <span class="usuarios-view-cell"><?php echo htmlspecialchars($u['plantel_nombre'] ?? '—'); ?></span>
                <select class="usuarios-bulk-input" data-field="id_plantel"
                  style="display:none; min-width:150px; padding:6px 8px;"
                  <?php echo $esAdminGlobal ? '' : 'disabled'; ?>>
                  <?php foreach ($listaPlanteles as $p): ?>
                    <?php $idp = (int) $p['id_plantel']; ?>
                    <option value="<?php echo $idp; ?>" <?php echo $idp === $idPlU ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars((string) ($p['nombre'] ?? $p['slug'] ?? ('#' . $idp))); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><?php echo $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : '—'; ?></td>
              <td>
                <?php if ((int)($u['huella_registrada'] ?? 0)): ?>
                  <span class="catalog-badge catalog-badge--ok" title="Huella registrada"><i class="fas fa-fingerprint"></i> Sí</span>
                <?php else: ?>
                  <span class="catalog-badge catalog-badge--muted">No</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($u['id_alumno'])): ?>
                  <span class="catalog-badge catalog-badge--ok">Vinculado</span>
                  <div style="margin-top:6px; display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="secondary" style="padding:6px 10px;" onclick="verAlumno(<?php echo (int)$u['id_alumno']; ?>)">Ver</button>
                    <button type="button" class="primary" style="padding:6px 10px;" onclick="inscribirUsuarioComoAlumno(<?php echo (int)$u['id_alumno']; ?>)">Inscribir a grupo</button>
                  </div>
                <?php else: ?>
                  <button type="button" class="secondary" onclick="vincularAlumno(<?php echo (int)$u['id_usuario']; ?>)">Crear perfil alumno</button>
                <?php endif; ?>
              </td>
              <td class="catalog-actions catalog-actions--icons">
                <?php if (huella_puede_enrolar_usuario()): ?>
                <button type="button" class="btn-icon-only" title="Registrar huella" onclick="cargarSeccion('usuario_huella_enroll', 'id=<?php echo $idU; ?>')">
                  <i class="fas fa-fingerprint"></i>
                </button>
                <?php endif; ?>
                <button type="button" class="btn-icon-only btn-icon-only--edit" title="Editar" onclick="editarUsuario(<?php echo $idU; ?>)">
                  <i class="fas fa-pen"></i>
                </button>
                <?php if ($puedeDesbloquear && login_security_usuario_bloqueado_por_intentos($u)): ?>
                <button type="button" class="btn-icon-only" title="Desbloquear login" onclick="desbloquearLogin(<?php echo $idU; ?>)">
                  <i class="fas fa-unlock"></i>
                </button>
                <?php endif; ?>
                <button type="button" class="btn-icon-only btn-icon-only--danger" title="Eliminar" onclick="eliminarUsuario(<?php echo $idU; ?>)">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/partials/modal_inscripcion_wizard.php'; ?>
<script src="js/inscripcion_wizard.js?v=20260624"></script>
<script>
(function () {
  const bulkApi = <?php echo json_encode($bulkApi, JSON_UNESCAPED_UNICODE); ?>;
  let bulkMode = false;
  let dt = null;

  function showBulkMsg(text, ok) {
    const el = document.getElementById('usuarios-bulk-msg');
    if (!el) return;
    el.style.display = 'block';
    el.textContent = text || '';
    el.className = 'catalog-alert ' + (ok ? 'ok' : 'err');
  }

  function setBulkMode(on) {
    bulkMode = !!on;
    document.querySelectorAll('.usuarios-view-cell').forEach((el) => {
      el.style.display = on ? 'none' : '';
    });
    document.querySelectorAll('.usuarios-bulk-input').forEach((el) => {
      el.style.display = on ? '' : 'none';
    });
    const t = document.getElementById('btn-usuarios-bulk-toggle');
    const s = document.getElementById('btn-usuarios-bulk-save');
    const c = document.getElementById('btn-usuarios-bulk-cancel');
    if (t) t.style.display = on ? 'none' : '';
    if (s) s.style.display = on ? '' : 'none';
    if (c) c.style.display = on ? '' : 'none';
    if (dt && dt.page) {
      // mantener paginación usable en edición
    }
  }

  document.getElementById('btn-usuarios-bulk-toggle')?.addEventListener('click', () => {
    setBulkMode(true);
    showBulkMsg('Edite nombre, apellido, rol y plantel. Luego pulse Guardar cambios.', true);
  });
  document.getElementById('btn-usuarios-bulk-cancel')?.addEventListener('click', () => {
    setBulkMode(false);
    const msg = document.getElementById('usuarios-bulk-msg');
    if (msg) msg.style.display = 'none';
  });

  document.getElementById('btn-usuarios-bulk-save')?.addEventListener('click', async () => {
    const rows = [];
    document.querySelectorAll('#tabla-usuarios tbody tr[data-id-usuario]').forEach((tr) => {
      const id = parseInt(tr.getAttribute('data-id-usuario') || '0', 10);
      if (!id) return;
      const get = (field) => tr.querySelector('.usuarios-bulk-input[data-field="' + field + '"]');
      const nombre = (get('nombre')?.value || '').trim();
      const apellido = (get('apellido')?.value || '').trim();
      const rol = (get('rol')?.value || '').trim();
      const idPlantel = parseInt(get('id_plantel')?.value || '0', 10);
      rows.push({ id_usuario: id, nombre, apellido, rol, id_plantel: idPlantel });
    });
    if (!rows.length) {
      showBulkMsg('No hay filas para guardar', false);
      return;
    }
    if (!confirm('¿Guardar cambios de ' + rows.length + ' usuario(s)?')) return;
    const fd = new FormData();
    fd.append('usuarios', JSON.stringify(rows));
    showBulkMsg('Guardando…', true);
    try {
      const r = await fetch(bulkApi, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
      const d = await r.json();
      let text = d.message || '';
      if (d.errores && d.errores.length) {
        text += ' — ' + d.errores.slice(0, 3).join('; ');
      }
      showBulkMsg(text || (d.status === 'ok' ? 'Guardado' : 'Error'), d.status === 'ok');
      if (d.status === 'ok') {
        setTimeout(() => {
          const q = <?php echo $verTodos ? json_encode('todos=1') : 'null'; ?>;
          cargarSeccion('ver_usuarios', q);
        }, 600);
      }
    } catch (e) {
      showBulkMsg('Error de red al guardar', false);
    }
  });

  if (window.HayDataTable?.init) {
    dt = window.HayDataTable.init('#tabla-usuarios', {
      order: [[0, 'desc']],
      pageLength: 50,
      columnDefs: [{ orderable: false, targets: 9 }],
    });
  }
})();

function editarUsuario(id) {
  cargarSeccion('usuario_editar', 'id=' + id);
}
function eliminarUsuario(id) {
  if (!confirm('¿Eliminar este usuario?')) return;
  const fd = new FormData();
  fd.append('id_usuario', id);
  fetch('php/usuario_delete.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })
    .then(r => r.json())
    .then(d => {
      alert(d.message || (d.status === 'ok' ? 'Eliminado' : 'Error'));
      if (d.status === 'ok') cargarSeccion('ver_usuarios');
    })
    .catch(() => alert('No se pudo eliminar'));
}

function verAlumno(idAlumno) {
  cargarSeccion('alumno_detalle', 'id=' + idAlumno);
}

async function vincularAlumno(idUsuario) {
  if (!confirm('¿Crear y vincular perfil de alumno para este usuario?')) return;
  const fd = new FormData();
  fd.append('id_usuario', idUsuario);
  try {
    const r = await fetch('php/usuario_vincular_alumno.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const d = await r.json();
    alert(d.message || (d.status === 'ok' ? 'Listo' : 'Error'));
    if (d.status === 'ok') cargarSeccion('ver_usuarios');
  } catch (e) {
    alert('No se pudo vincular.');
  }
}

function inscribirUsuarioComoAlumno(idAlumno) {
  if (!window.HayInscripcionWizard) {
    alert('Asistente de inscripción no disponible.');
    return;
  }
  window.HayInscripcionWizard.openFromAlumno(idAlumno, 0, 0, () => cargarSeccion('ver_usuarios'));
}

async function desbloquearLogin(idUsuario) {
  if (!confirm('¿Desbloquear el acceso por login de este usuario?')) return;
  const fd = new FormData();
  fd.append('action', 'desbloquear');
  fd.append('id_usuario', idUsuario);
  try {
    const { data } = await hayFetchJson('php/usuario_login_api.php', { method: 'POST', body: fd });
    alert(data.message || (data.status === 'ok' ? 'Desbloqueado' : 'Error'));
    if (data.status === 'ok') cargarSeccion('ver_usuarios');
  } catch (e) {
    alert(e.message || 'No se pudo desbloquear');
  }
}
</script>
