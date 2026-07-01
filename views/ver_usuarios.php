<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/auth_helpers.php';
auth_ensure_email_column($pdo);
usuario_ensure_schema($pdo);
login_security_ensure_schema($pdo);
$puedeDesbloquear = login_security_puede_desbloquear();

$idPlantel = plantel_scope_id($pdo);
if (function_exists('rbac_usuario_ensure_permisos_flag')) {
    rbac_usuario_ensure_permisos_flag($pdo);
}
$sql = "SELECT u.id_usuario, u.nombre, u.apellido, u.username, u.email, u.rol, u.departamento,
               u.fecha_creacion, u.suspendido, u.ultimo_acceso, u.id_alumno,
               u.login_fallidos, u.login_bloqueado_hasta,
               u.huella_registrada, u.codigo_huella, u.permisos_personalizados,
               p.nombre AS plantel_nombre,
               COALESCE(r.nombre, u.rol) AS rol_nombre
        FROM usuarios u
        LEFT JOIN planteles p ON p.id_plantel = u.id_plantel
        LEFT JOIN roles r ON r.id_rol = u.id_rol
        WHERE u.id_plantel = ? AND u.rol != 'alumno'
        ORDER BY u.id_usuario DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$idPlantel]);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rolesLabel = [
    'admin' => 'Administrador',
    'gerente' => 'Gerente',
    'profesor' => 'Profesor',
    'asesor' => 'Asesor',
    'alumno' => 'Alumno',
];
?>
<link rel="stylesheet" href="css/admin_catalogo.css">
<link rel="stylesheet" href="css/hay_buttons.css">
<link rel="stylesheet" href="css/hay_icon_buttons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<div class="catalog-wrap">
  <div class="catalog-header">
    <h2><i class="fas fa-users"></i> Personal del plantel</h2>
    <p style="color:#666; margin:0 0 8px;">Solo usuarios asignados a <strong><?php echo htmlspecialchars($_SESSION['plantel_nombre'] ?? 'esta sede'); ?></strong>.</p>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <button type="button" class="primary" onclick="cargarSeccion('crear_usuario')">Nuevo usuario</button>
      <button type="button" class="secondary" onclick="cargarSeccion('prospectos_profesor')">Prospectos profesor</button>
    </div>
  </div>

  <p style="color:#666; margin-top:0;">Personal con acceso institucional. Los alumnos tienen usuario = # control y se gestionan desde su perfil.</p>

  <div class="catalog-table-wrap hay-dt-panel">
    <?php if (empty($usuarios)): ?>
      <p>No hay usuarios en este plantel.</p>
    <?php else: ?>
      <table id="tabla-usuarios" class="catalog-table display hay-paged-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Nombre</th>
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
            <tr>
              <td><?php echo (int)$u['id_usuario']; ?></td>
              <td><?php echo htmlspecialchars(trim($u['nombre'] . ' ' . $u['apellido'])); ?></td>
              <td><?php echo htmlspecialchars($u['username']); ?></td>
              <td>
                <span class="catalog-badge catalog-badge--muted"><?php echo htmlspecialchars($u['rol_nombre'] ?? ($rolesLabel[$u['rol']] ?? $u['rol'])); ?></span>
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
              </td>
              <td><?php echo htmlspecialchars($u['plantel_nombre'] ?? '—'); ?></td>
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
                <button type="button" class="btn-icon-only" title="Registrar huella" onclick="cargarSeccion('usuario_huella_enroll', 'id=<?php echo (int)$u['id_usuario']; ?>')">
                  <i class="fas fa-fingerprint"></i>
                </button>
                <?php endif; ?>
                <button type="button" class="btn-icon-only btn-icon-only--edit" title="Editar" onclick="editarUsuario(<?php echo (int)$u['id_usuario']; ?>)">
                  <i class="fas fa-pen"></i>
                </button>
                <?php if ($puedeDesbloquear && login_security_usuario_bloqueado_por_intentos($u)): ?>
                <button type="button" class="btn-icon-only" title="Desbloquear login" onclick="desbloquearLogin(<?php echo (int)$u['id_usuario']; ?>)">
                  <i class="fas fa-unlock"></i>
                </button>
                <?php endif; ?>
                <button type="button" class="btn-icon-only btn-icon-only--danger" title="Eliminar" onclick="eliminarUsuario(<?php echo (int)$u['id_usuario']; ?>)">
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
  window.HayDataTable?.init('#tabla-usuarios', {
    order: [[0, 'desc']],
    columnDefs: [{ orderable: false, targets: 8 }],
  });
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
