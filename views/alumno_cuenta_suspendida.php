<?php
require_once __DIR__ . '/_bootstrap.php';
/** @var PDO $pdo */

if (!alumno_portal_puede_ver()) {
    echo '<p>Sin permiso.</p>';
    return;
}

$modo = $_SESSION['suspension_portal'] ?? null;
if ($modo !== USUARIO_SUSPENSION_PORTAL_ADEUDO) {
    echo '<p>Esta pantalla solo aplica a cuentas suspendidas por adeudo.</p>';
    return;
}

$idAlumno = alumno_portal_id_o_detener();
if ($idAlumno <= 0) {
    return;
}
$motivo = trim((string) ($_SESSION['suspension_motivo'] ?? ''));
$adeudo = 0.0;
if ($idAlumno > 0 && function_exists('pago_estado_cuenta')) {
    $ec = pago_estado_cuenta($pdo, $idAlumno);
    $adeudo = (float) ($ec['resumen']['adeudo_colegiatura'] ?? 0);
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">

<div class="result-container" style="max-width:640px; margin:0 auto;">
  <div style="padding:24px; background:#fff3e0; border:1px solid #ffb74d; border-radius:12px;">
    <h2 style="margin:0 0 12px; color:#e65100;"><i class="fas fa-pause-circle"></i> Acceso limitado</h2>
    <p style="margin:0 0 12px; line-height:1.5;">
      Su usuario se encuentra <strong>temporalmente suspendido por adeudo pendiente</strong>.
      Para rehabilitar el acceso completo al sistema (grupos, tutor IA, etc.), regularice sus pagos y
      comuníquese con <strong>recepción</strong> de su plantel.
    </p>
    <?php if ($adeudo > 0.01): ?>
      <p style="margin:0 0 12px; font-size:1.1rem;">
        Adeudo actual en colegiaturas: <strong><?php echo catalog_format_mxn($adeudo); ?></strong>
      </p>
    <?php endif; ?>
    <?php if ($motivo !== ''): ?>
      <p style="margin:0; color:#666; font-size:0.9rem;">Observación: <?php echo htmlspecialchars($motivo); ?></p>
    <?php endif; ?>
  </div>

  <div style="margin-top:20px; display:flex; flex-wrap:wrap; gap:10px;">
    <button type="button" class="secondary" onclick="cargarSeccion('alumno_mi_perfil')">Ver mi perfil</button>
    <button type="button" class="secondary" onclick="cargarSeccion('perfil')">Mi cuenta</button>
  </div>

  <p style="margin-top:20px; color:#888; font-size:0.85rem;">
    El Tutor IA y otras funciones académicas requieren estar al corriente con sus mensualidades y tener un grupo activo.
  </p>
</div>
