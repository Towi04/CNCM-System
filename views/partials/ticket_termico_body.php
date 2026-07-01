<?php
/** @var array<string, mixed> $ticket */
/** @var string $tituloTicket ej. pago, inscripcion */
$pt = $ticket['plantel_ticket'] ?? [];
$desglose = $ticket['desglose'] ?? [];
$tituloTicket = $tituloTicket ?? 'pago';
?>
<div class="ticket-termico">
  <?php if (!empty($pt['logo_url'])): ?>
  <img class="ticket-termico__logo" src="<?php echo htmlspecialchars($pt['logo_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
  <?php endif; ?>

  <div class="ticket-termico__head">
    <?php if (!empty($pt['razon_social'])): ?>
    <p class="ticket-termico__razon"><?php echo htmlspecialchars($pt['razon_social']); ?></p>
    <?php endif; ?>
    <?php if (!empty($pt['direccion'])): ?>
    <p><?php echo htmlspecialchars($pt['direccion']); ?></p>
    <?php endif; ?>
    <?php if (!empty($pt['rfc'])): ?>
    <p>RFC <?php echo htmlspecialchars($pt['rfc']); ?></p>
    <?php endif; ?>
    <?php if (!empty($pt['nombre'])): ?>
    <p>Sucursal <?php echo htmlspecialchars($pt['nombre']); ?></p>
    <?php endif; ?>
    <?php if (!empty($pt['telefono'])): ?>
    <p>Tel <?php echo htmlspecialchars($pt['telefono']); ?></p>
    <?php endif; ?>
  </div>

  <div class="ticket-termico__meta">
    <p>Fecha <?php echo htmlspecialchars($ticket['fecha_fmt'] ?? date('d-m-Y')); ?>
      Hora <?php echo htmlspecialchars($ticket['hora_fmt'] ?? date('H:i:s')); ?></p>
    <p>Folio: <?php echo htmlspecialchars($ticket['folio'] ?? ''); ?></p>
    <?php if (!empty($ticket['recibio']) || !empty($ticket['cajero'])): ?>
    <p>Recibio <?php echo htmlspecialchars($ticket['recibio'] ?? $ticket['cajero']); ?></p>
    <?php endif; ?>
    <p class="ticket-termico__alumno">Alumno <?php echo htmlspecialchars($ticket['alumno'] ?? ''); ?></p>
    <?php if (!empty($ticket['numero_control'])): ?>
    <p>No Control <?php echo htmlspecialchars($ticket['numero_control']); ?></p>
    <?php endif; ?>
    <?php if ($tituloTicket === 'inscripcion' && !empty($ticket['grupo'])): ?>
    <p>Grupo <?php echo htmlspecialchars($ticket['grupo']); ?></p>
    <?php endif; ?>
  </div>

  <div class="ticket-termico__total-banner">
    Monto Total del pago: <?php echo htmlspecialchars($ticket['monto_fmt'] ?? ''); ?>
  </div>

  <div class="ticket-termico__section-title">Desglose del pago</div>

  <ul class="ticket-termico__lineas">
    <?php foreach ($desglose as $ln): ?>
    <li>
      <span class="desc"><?php echo htmlspecialchars($ln['descripcion'] ?? ''); ?></span>
      <span class="amt"><?php echo htmlspecialchars($ln['monto_fmt'] ?? ''); ?></span>
    </li>
    <?php endforeach; ?>
  </ul>

  <hr class="ticket-termico__sep">
  <div class="ticket-termico__total-row">
    Total: <?php echo htmlspecialchars($ticket['monto_fmt'] ?? ''); ?>
  </div>

  <div class="ticket-termico__pie">
    <p>Efectos fiscales al pago.</p>
    <p>Pago hecho en una sola exhibición.</p>
    <?php if (!empty($pt['email_contacto'])): ?>
    <p>Para cualquier duda o sugerencia enviarnos un correo a <?php echo htmlspecialchars($pt['email_contacto']); ?></p>
    <?php endif; ?>
  </div>
</div>
