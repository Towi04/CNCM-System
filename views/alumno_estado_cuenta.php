<?php
require_once __DIR__ . '/../config.php';
$id = (int) ($_GET['id'] ?? 0);
if (rbac_rol_efectivo() === 'alumno') {
    if (!alumno_portal_puede_ver()) {
        echo '<div class="alert">Portal solo para alumnos.</div>';
        return;
    }
    $idPropio = alumno_portal_id_sesion();
    if ($idPropio <= 0) {
        alumno_portal_id_o_detener();
        return;
    }
    if ($id > 0 && $id !== $idPropio) {
        echo '<div class="alert">Solo puede consultar su propio estado de cuenta.</div>';
        return;
    }
    if ($id <= 0) {
        $id = $idPropio;
    }
} elseif ($id <= 0) {
    echo '<div class="alert">Indique el alumno.</div>';
    return;
}
$fecha = trim($_GET['fecha'] ?? date('Y-m-d'));
$ec = pago_estado_cuenta($pdo, $id, $fecha);

if (!$ec['ok']) {
    echo '<div class="alert">' . htmlspecialchars($ec['message'] ?? 'Error') . '</div>';
    return;
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/estado_cuenta.css'), ENT_QUOTES, 'UTF-8'); ?>">
<?php if (rbac_rol_efectivo() === 'alumno'): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(hay_asset_url('css/alumno_portal.css'), ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>

<div class="ec-wrap no-print" style="margin-bottom:12px;">
  <?php if (rbac_rol_efectivo() === 'alumno'): ?>
    <button type="button" class="secondary" onclick="cargarSeccion('alumno_portal_inicio')">← Inicio</button>
  <?php else: ?>
    <button type="button" onclick="history.back()">← Volver</button>
  <?php endif; ?>
  <button type="button" class="primary" onclick="window.print()" style="margin-left:8px;"><i class="fas fa-print"></i> Imprimir</button>
</div>

<div class="ec-wrap">
  <?php include __DIR__ . '/partials/estado_cuenta_body.php'; ?>
</div>
