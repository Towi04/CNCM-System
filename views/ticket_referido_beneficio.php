<?php
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id']) || !preregistro_puede_acceder()) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

$idReferido = (int) ($_GET['id_referido'] ?? 0);
$idPlantel = plantel_scope_id($pdo);
$ticket = $idReferido > 0 ? referido_datos_ticket($pdo, $idReferido, $idPlantel) : null;
if (!$ticket) {
    http_response_code(404);
    echo 'Comprobante no encontrado';
    exit;
}

$autoPrint = !empty($_GET['print']);
$copia = !empty($_GET['copia']);
$ticketCss = hay_asset_url('css/ticket_termico.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crédito referido <?php echo htmlspecialchars($ticket['folio']); ?></title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($ticketCss, ENT_QUOTES, 'UTF-8'); ?>">
  <style>
  .ticket-firma { margin-top:24px; border-top:1px dashed #000; padding-top:8px; font-size:11px; }
  .ticket-firma-line { border-bottom:1px solid #000; height:28px; margin:8px 0; }
  <?php if ($copia): ?>.ticket-copia-marca { font-weight:bold; text-align:center; margin-bottom:8px; }<?php endif; ?>
  </style>
</head>
<body class="ticket-termico-page">
  <div class="ticket-termico-no-print">
    <button type="button" onclick="window.print()">Imprimir</button>
    <?php if ($ticket['requiere_firma']): ?>
    <button type="button" id="btn-firma-ok">Marcar firmado</button>
    <?php endif; ?>
  </div>

  <div class="ticket-termico">
    <?php if ($copia): ?><p class="ticket-copia-marca">COPIA — FIRMA DEL REFERIDOR</p><?php endif; ?>
    <?php $pt = $ticket['plantel_ticket'] ?? []; ?>
    <?php if (!empty($pt['razon_social'])): ?><p><strong><?php echo htmlspecialchars($pt['razon_social']); ?></strong></p><?php endif; ?>
    <p>Comprobante de crédito por referido</p>
    <p>Folio <?php echo htmlspecialchars($ticket['folio']); ?></p>
    <p>Fecha <?php echo htmlspecialchars($ticket['fecha_fmt']); ?> · <?php echo htmlspecialchars($ticket['hora_fmt']); ?></p>
    <hr>
    <p><strong>Referidor (recibe beneficio)</strong></p>
    <p><?php echo htmlspecialchars($ticket['alumno']); ?></p>
    <p>No. control <?php echo htmlspecialchars($ticket['numero_control']); ?></p>
    <p class="ticket-termico__total-banner">Crédito aplicado: <?php echo htmlspecialchars($ticket['monto_fmt']); ?></p>
    <hr>
    <p><strong>Nuevo inscrito referido</strong></p>
    <p><?php echo htmlspecialchars($ticket['inscrito_nombre']); ?> (<?php echo htmlspecialchars($ticket['inscrito_control']); ?>)</p>
    <p>Curso: <?php echo htmlspecialchars($ticket['especialidad']); ?></p>
    <?php if (!empty($ticket['grupo'])): ?><p>Grupo: <?php echo htmlspecialchars($ticket['grupo']); ?></p><?php endif; ?>
    <p style="font-size:11px; margin-top:12px;">
      Este comprobante justifica el crédito en caja del referidor. Conserve copia firmada.
    </p>
    <div class="ticket-firma">
      <p>Firma del referidor (enterado del descuento/crédito):</p>
      <div class="ticket-firma-line"></div>
      <p>Nombre: _________________________</p>
    </div>
  </div>

  <?php if ($autoPrint): ?><script>window.addEventListener('load', () => window.print());</script><?php endif; ?>
  <script>
  document.getElementById('btn-firma-ok')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'marcar_firma');
    fd.append('id_referido', '<?php echo $idReferido; ?>');
    fd.append('copia_impresa', '<?php echo $copia ? '1' : '0'; ?>');
    const r = await fetch('<?php echo htmlspecialchars(hay_asset_url('php/referido_api.php'), ENT_QUOTES, 'UTF-8'); ?>', {
      method: 'POST', body: fd, credentials: 'same-origin'
    });
    const d = await r.json();
    alert(d.message || 'Listo');
  });
  </script>
</body>
</html>
