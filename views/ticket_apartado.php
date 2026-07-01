<?php
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id']) || !preregistro_puede_acceder()) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$folio = trim((string) ($_GET['folio'] ?? ''));
$idPlantel = plantel_id_activo();

$ticket = preregistro_datos_ticket_apartado($pdo, $id, $idPlantel);
if (!$ticket || ($folio !== '' && ($ticket['folio'] ?? '') !== $folio)) {
    http_response_code(404);
    echo 'Comprobante no encontrado';
    exit;
}

$autoPrint = !empty($_GET['print']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Comprobante apartado <?php echo htmlspecialchars($ticket['folio']); ?></title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; margin: 0; padding: 16px; color: #111; }
    .ticket { max-width: 320px; margin: 0 auto; border: 1px dashed #999; padding: 16px; }
    .ticket h1 { font-size: 1rem; text-align: center; margin: 0 0 4px; }
    .ticket .sub { text-align: center; font-size: 0.78rem; color: #555; margin-bottom: 12px; }
    .ticket dl { margin: 0; font-size: 0.88rem; }
    .ticket dt { font-weight: 700; margin-top: 8px; }
    .ticket dd { margin: 2px 0 0; }
    .ticket .monto { text-align: center; font-size: 1.4rem; font-weight: 700; margin: 14px 0; }
    .ticket .pie { font-size: 0.72rem; text-align: center; color: #666; margin-top: 14px; border-top: 1px solid #ddd; padding-top: 8px; }
    .no-print { text-align: center; margin-bottom: 16px; }
    @media print {
      .no-print { display: none; }
      body { padding: 0; }
      .ticket { border: none; max-width: 100%; }
    }
  </style>
</head>
<body>
  <div class="no-print">
    <button type="button" onclick="window.print()">Imprimir</button>
    <button type="button" onclick="window.close()">Cerrar</button>
  </div>
  <div class="ticket">
    <h1>COMPROBANTE DE APARTADO</h1>
    <div class="sub"><?php echo htmlspecialchars($ticket['plantel']); ?></div>
    <dl>
      <dt>Folio</dt>
      <dd><?php echo htmlspecialchars($ticket['folio']); ?></dd>
      <dt>Fecha</dt>
      <dd><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha']))); ?></dd>
      <dt>Prospecto</dt>
      <dd><?php echo htmlspecialchars($ticket['prospecto']); ?></dd>
      <?php if ($ticket['telefono']): ?>
      <dt>Teléfono</dt>
      <dd><?php echo htmlspecialchars($ticket['telefono']); ?></dd>
      <?php endif; ?>
      <dt>Especialidad</dt>
      <dd><?php echo htmlspecialchars($ticket['especialidad']); ?></dd>
      <dt>Asesor</dt>
      <dd><?php echo htmlspecialchars($ticket['asesor']); ?></dd>
      <dt>Forma de pago</dt>
      <dd><?php echo htmlspecialchars($ticket['forma_pago'] ?? 'Efectivo'); ?></dd>
    </dl>
    <div class="monto"><?php echo htmlspecialchars($ticket['monto_fmt']); ?></div>
    <div class="pie">
      Este apartado se aplicará al pago de inscripción.<br>
      Conserve este comprobante.
    </div>
  </div>
  <?php if ($autoPrint): ?>
  <script>window.onload = function () { window.print(); };</script>
  <?php endif; ?>
</body>
</html>
